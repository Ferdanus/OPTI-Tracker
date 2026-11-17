<?php

/**
 * PoAlurWaktu
 *
 * Menghitung "berapa lama tiap pihak mengerjakan PO" untuk stepper Tahap 8 (detail order).
 * Waktu pengerjaan satu pihak = dari PO diterima pihak itu (didisposisikan ke dia) sampai diteruskan ke pihak berikutnya; waktu menunggu dibuka ikut terhitung.
 *
 * Sumber data, urut prioritas:
 *   1. Tabel po_alur_log (migration_po_alur_log.sql). Diisi trigger pada po_kegiatan, jadi SEMUA putaran revisi tercatat.
 *   2. Kolom jejak di po_kegiatan (dikirim_at, reviewed_at, disetujui_at, mitra_dibaca_at, ppk_dibaca_at, ...). Dipakai untuk PO lama
 *      yang belum punya log: hanya putaran terakhir yang terlihat, jadi rincian putaran revisi tidak tersedia.
 *
 * Satuan hasil: "giliran" (turn) = satu pihak memegang PO dari DITERIMA sampai SELESAI (diteruskan / disetujui / dikirim).
 *   terima  = saat PO sampai ke pihak itu
 *   buka    = saat pihak itu pertama kali membuka PO (NULL bila tidak tercatat)
 *   selesai = saat pihak itu meneruskan / menyetujui
 *
 * Indeks langkah mengikuti OrderController::alurPoTahap8: 1 Peninjauan Ketua Tim, 2 Verifikasi Tim Mitra, 3 Verifikasi PPK BLU, 4 Penandatanganan PO.
 * Langkah 0 (Penyusunan) dan 5 (PO Aktif) tidak punya durasi.
 *
 * Kompatibel PHP 7.2. Fungsi susun() murni (tanpa DB) supaya mudah diuji.
 */
class PoAlurWaktu {

    /** Kolom yang dicatat trigger sebagai "dibuka" */
    private static $KOLOM_BUKA = array('reviewed_at', 'revisi_dibaca_at', 'minta_dibaca_at', 'mitra_dibaca_at', 'ppk_dibaca_at');

    /** Muat log PO dari DB. Mengembalikan array kosong bila tabel belum ada. */
    public static function muat($db, $poId) {
        try {
            $r = $db->exec('SELECT kolom, nilai, waktu FROM po_alur_log WHERE po_id = ? ORDER BY waktu ASC, id ASC', array(1 => (int) $poId));
            return is_array($r) ? $r : array();
        } catch (\Throwable $e) {
            return array();
        }
    }

    /** Format lama: "< 1 mnt", "44 mnt", "1 j 8 mnt", "2 hr 3 j" */
    public static function durasi($detik) {
        if ($detik === null) { return ''; }
        $detik = max(0, (int) $detik);
        if ($detik < 60) { return '< 1 mnt'; }
        $m = (int) round($detik / 60);
        if ($m < 60) { return $m . ' mnt'; }
        $j = intdiv($m, 60); $mm = $m % 60;
        if ($j < 24) { return $j . ' j' . ($mm > 0 ? ' ' . $mm . ' mnt' : ''); }
        $h = intdiv($j, 24); $jj = $j % 24;
        return $h . ' hr' . ($jj > 0 ? ' ' . $jj . ' j' : '');
    }

    private static function ts($v) {
        $v = trim((string) $v);
        if ($v === '' || strpos($v, '0000') === 0) { return null; }
        $t = strtotime($v);
        return $t ? $t : null;
    }

    /**
     * @param array $po     baris po_kegiatan
     * @param array $log    hasil muat() (boleh kosong)
     * @return array  indeks langkah (1..4) => array(
     *     'terima','buka','selesai' (timestamp|null), 'lama' (detik|null: angka di garis),
     *     'revisi' (bool: ada lebih dari satu giliran), 'putaran' (int: jumlah revisi yang diketahui),
     *     'rinci' (bool: rincian putaran tersedia), 'giliran' => list of array(pihak, aksi, terima, buka, selesai, lama))
     */
    public static function susun(array $po, array $log) {
        $lengkap = false;
        foreach ($log as $e) { if ($e['kolom'] === 'dikirim_at') { $lengkap = true; break; } }
        $turns = $lengkap ? self::dariLog($log) : self::dariKolom($po);

        $hasil = array();
        foreach (array(1, 2, 3, 4) as $s) {
            $ts = array();
            foreach ($turns as $t) { if ($t['step'] === $s) { $ts[] = $t; } }
            if (!$ts) { continue; }
            $first = $ts[0]; $last = $ts[count($ts) - 1];
            $rev = count($ts) > 1;
            $putaran = $rev ? (int) floor(count($ts) / 2) : 0;
            if (!$lengkap) {   // PO lama: jumlah putaran dari penghitung ronde
                $putaran = ($s === 1) ? (int) ($po['ronde_revisi'] ?? 0) : (($s === 3) ? (int) ($po['ronde_ppk'] ?? 0) : 0);
                $rev = $putaran > 0;
            }
            $lama = null;
            if ($last['selesai'] !== null && $first['terima'] !== null) {
                $awal = $first['terima'];   // waktu pengerjaan: dari PO diterima (didisposisikan ke pihak itu) sampai diteruskan, termasuk menunggu dibuka
                $lama = max(0, $last['selesai'] - $awal);
            }
            $gl = array();
            if ($lengkap) {
                foreach ($ts as $t) {
                    $aw = $t['terima'];
                    $gl[] = array('pihak' => $t['pihak'], 'aksi' => self::aksi($t), 'terima' => $t['terima'], 'buka' => $t['buka'], 'selesai' => $t['selesai'],
                        'lama' => ($t['selesai'] !== null && $aw !== null) ? max(0, $t['selesai'] - $aw) : null);
                }
            }
            $hasil[$s] = array('terima' => $first['terima'], 'buka' => $first['buka'], 'selesai' => $last['selesai'], 'lama' => $lama,
                'revisi' => $rev, 'putaran' => $putaran, 'rinci' => ($lengkap && $rev), 'giliran' => $gl);
        }
        return $hasil;
    }

    /** Apakah log berisi penanda awal (dikirim_at), tanda log lengkap untuk PO itu. */
    public static function punyaLog(array $log) {
        foreach ($log as $e) { if (isset($e['kolom']) && $e['kolom'] === 'dikirim_at') { return true; } }
        return false;
    }

    /**
     * PO tanpa po_alur_log: susun log tiruan dari Riwayat Aktivitas (opti_activity_log, modul po_kegiatan) + kolom waktu po_kegiatan.
     * Riwayat mencatat jam tiap perpindahan (PPK BLU minta revisi, Ketua Tim dikerjakan sendiri / disposisi, kirim ke PPK BLU, dst).
     * Hasilnya diumpankan ke mesin susun() yang sama. Waktu "dibuka" tidak ada di Riwayat, jadi tidak terisi.
     * @param array $rows baris opti_activity_log: aksi, deskripsi, created_at (urut waktu)
     * @return array log tiruan (kosong bila kolom jangkar tidak lengkap)
     */
    public static function logDariRiwayat(array $po, array $rows) {
        $kirim = isset($po['dikirim_at']) ? self::ts($po['dikirim_at']) : null;
        $setuju = isset($po['disetujui_at']) ? self::ts($po['disetujui_at']) : null;
        if ($kirim === null || $setuju === null) { return array(); }
        $mitra = isset($po['tervalidasi_mitra_at']) ? self::ts($po['tervalidasi_mitra_at']) : null;
        $ppk = isset($po['validasi_keuangan_at']) ? self::ts($po['validasi_keuangan_at']) : null;
        $ttd = isset($po['po_ttd_at']) ? self::ts($po['po_ttd_at']) : null;
        $ev = array();
        $add = function ($k, $v, $t) use (&$ev) { $ev[] = array('kolom' => $k, 'nilai' => $v, 'waktu' => date('Y-m-d H:i:s', $t), '_t' => $t, '_i' => count($ev)); };
        $add('dikirim_at', null, $kirim);
        foreach ($rows as $r) {
            $t = self::ts(isset($r['created_at']) ? $r['created_at'] : '');
            if ($t === null) { continue; }
            $d = mb_strtolower((string) (isset($r['deskripsi']) ? $r['deskripsi'] : ''), 'UTF-8');
            $fasePpk = ($mitra !== null && $t >= $mitra);
            if (!$fasePpk) {   // sebelum diverifikasi Tim Mitra: peninjauan Ketua Tim
                if ($t >= $setuju) { continue; }
                if (strpos($d, 'menunggu review ulang') !== false) { $add('status_review', 'direvisi', $t); }
                elseif (preg_match('/minta (revisi|perbaikan)/u', $d)) { $add('status_review', 'perlu_revisi', $t); }
                continue;
            }
            if ($ppk !== null && $t > $ppk) { continue; }
            if (strpos($d, 'menunggu keputusan ketua tim') !== false) { $add('ppk_status', 'minta', $t); }
            elseif (strpos($d, 'didisposisikan') !== false) { $add('ppk_status', 'ketupel_kerja', $t); }
            elseif (strpos($d, 'dikerjakan sendiri') !== false) { $add('ppk_status', 'katim_kerja', $t); }
            elseif (strpos($d, 'dikirim ke ketua tim') !== false) { $add('ppk_status', 'katim_review', $t); }
            elseif (strpos($d, 'dikirim ke ppk blu') !== false) { $add('ppk_status', '-', $t); }
            elseif (strpos($d, 'minta perbaikan') !== false) { $add('ppk_status', 'ketupel_kerja', $t); }
        }
        // penanda tahap dari kolom waktu (pasti), diletakkan sesudah kejadian Riwayat bila jamnya sama
        $add('status_review', 'disetujui', $setuju);
        if ($mitra !== null) { $add('tervalidasi_mitra_at', null, $mitra); }
        if ($ppk !== null) { $add('validasi_keuangan_at', null, $ppk); }
        if ($ttd !== null) { $add('po_ttd_at', null, $ttd); }
        usort($ev, function ($a, $b) { return ($a['_t'] === $b['_t']) ? ($a['_i'] - $b['_i']) : ($a['_t'] - $b['_t']); });
        $out = array();
        foreach ($ev as $e) { $out[] = array('kolom' => $e['kolom'], 'nilai' => $e['nilai'], 'waktu' => $e['waktu']); }
        return $out;
    }

    /**
     * PO yang berjalan sebelum po_alur_log aktif: susun giliran PERKIRAAN Verifikasi PPK BLU dari kolom waktu po_kegiatan.
     * Yang diketahui pasti: PPK BLU menerima (tervalidasi_mitra_at), meminta revisi (ppk_minta_at, putaran terakhir), PO dikembalikan ke PPK BLU
     * (ppk_status_at), dibuka (ppk_dibaca_at), diverifikasi (validasi_keuangan_at). Siapa memegang PO di tengah (Ketua Tim / Ketua Pelaksana)
     * tidak tercatat, jadi digabung jadi satu giliran "Tim OPTI". Hanya putaran revisi TERAKHIR yang terlihat (kolom ditimpa tiap putaran).
     * @return array|null elemen seperti susun() (dengan 'perkiraan' = teks catatan) atau null bila data tidak cukup / urutannya janggal
     */
    public static function jalurDariKolom(array $po, array $w) {
        $ronde = (int) (isset($po['ronde_ppk']) ? $po['ronde_ppk'] : 0);
        if ($ronde < 1) { return null; }
        $g = function ($k) use ($po) { return isset($po[$k]) ? self::ts($po[$k]) : null; };
        $mitra = $g('tervalidasi_mitra_at'); $minta = $g('ppk_minta_at'); $balik = $g('ppk_status_at'); $buka = $g('ppk_dibaca_at'); $ppk = $g('validasi_keuangan_at');
        if ($minta === null || $balik === null || $ppk === null) { return null; }
        if (!($minta <= $balik && $balik <= $ppk)) { return null; }
        if ($ronde === 1 && ($mitra === null || $mitra > $minta)) { return null; }
        $lama = function ($t, $b, $s) { $aw = $t; return ($s !== null && $aw !== null) ? max(0, $s - $aw) : null; };
        $turns = array();
        if ($ronde === 1) {
            $turns[] = array('pihak' => 'ppk', 'aksi' => 'Memeriksa & meminta revisi', 'terima' => $mitra, 'buka' => null, 'selesai' => $minta, 'lama' => $lama($mitra, null, $minta));
        }
        $turns[] = array('pihak' => 'tim', 'aksi' => 'Revisi dikerjakan Tim OPTI (3 tahap)', 'sub' => array('Ketua Tim menerima permintaan revisi dan mendisposisikannya ke Ketua Pelaksana', 'Ketua Pelaksana memperbaiki PO sesuai catatan PPK BLU', 'Ketua Tim meninjau hasil perbaikan lalu mengirimnya kembali ke PPK BLU'), 'terima' => $minta, 'buka' => null, 'selesai' => $balik, 'lama' => $lama($minta, null, $balik));
        $b3 = ($buka !== null && $buka >= $balik && $buka <= $ppk) ? $buka : null;
        $turns[] = array('pihak' => 'ppk', 'aksi' => 'Memverifikasi PO', 'terima' => $balik, 'buka' => $b3, 'selesai' => $ppk, 'lama' => $lama($balik, $b3, $ppk));
        $w['giliran'] = $turns; $w['rinci'] = true;
        $w['perkiraan'] = 'Perkiraan dari kolom waktu PO. PO ini diproses sebelum pencatatan putaran aktif, jadi tahap Ketua Tim dan Ketua Pelaksana digabung jadi satu giliran (rinciannya ada di kartu detail) dan waktu dibuka tidak selalu diketahui'
            . ($ronde > 1 ? '. Putaran revisi sebelumnya tidak tercatat, hanya putaran terakhir yang tampil.' : '.');
        return $w;
    }

    /**
     * HTML rincian "per putaran" untuk satu langkah yang punya putaran revisi:
     *   - ringkasan siapa memegang PO paling lama (batang bertumpuk per pihak + waktu menunggu dibuka)
     *   - giliran dikelompokkan per putaran revisi (buka/tutup), tiap giliran: pihak, aksi, diterima / dibuka / selesai, lama
     * Nama method dipertahankan (dulu menggambar jalur SVG) supaya pemanggilnya tidak berubah.
     * @param array  $w     elemen hasil susun() untuk langkah itu (harus punya 'giliran')
     * @param int    $now   timestamp sekarang (batas giliran yang masih berjalan)
     * @param int    $idn   tidak dipakai lagi (dipertahankan untuk kompatibilitas)
     * @param string $judul judul panel
     * @return string HTML aman (sudah di-escape); kosong bila data tidak cukup
     */
    public static function jalurHtml(array $w, $now, $idn = 0, $judul = 'Rincian putaran revisi') {
        $g = isset($w['giliran']) ? $w['giliran'] : array();
        if (!$g) { return ''; }
        $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $nama = array('ppk' => 'PPK BLU', 'katim' => 'Ketua Tim OPTI', 'ketupel' => 'Ketua Pelaksana', 'mitra' => 'Tim Mitra', 'tim' => 'Tim OPTI');
        $ini  = array('ppk' => 'PB', 'katim' => 'KT', 'ketupel' => 'KP', 'mitra' => 'TM', 'tim' => 'TO');
        $bln  = array('', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
        $gm = array(); $t0 = null; $t1 = null;
        foreach ($g as $i => $x) {
            if (!isset($nama[$x['pihak']])) { continue; }
            $a = $x['terima'] !== null ? $x['terima'] : $x['buka'];
            if ($a === null) { continue; }
            $b = $x['buka'] !== null ? max($x['buka'], $a) : $a;
            $berjalan = ($x['selesai'] === null);
            $sl = $berjalan ? max($now, $b) : max($x['selesai'], $b);
            $gm[$i] = array('a' => $a, 'b' => $b, 's' => $sl, 'jalan' => $berjalan);
            if ($t0 === null || $a < $t0) { $t0 = $a; }
            if ($t1 === null || $sl > $t1) { $t1 = $sl; }
        }
        if (!$gm || $t0 === null) { return ''; }
        $mulaiHari = date('Ymd', $t0);
        $jam = function ($t) use ($bln, $mulaiHari) {
            return (date('Ymd', $t) === $mulaiHari) ? date('H:i', $t) : (int) date('j', $t) . ' ' . $bln[(int) date('n', $t)] . ' ' . date('H:i', $t);
        };
        // pihak "pemegang utama" langkah ini: PPK BLU (Verifikasi PPK BLU) atau Ketua Tim (Peninjauan Ketua Tim). Tiap giliran miliknya membuka putaran baru.
        $adaPpk = false;
        foreach ($g as $x) { if ($x['pihak'] === 'ppk') { $adaPpk = true; break; } }
        $utama = $adaPpk ? 'ppk' : 'katim';
        $grup = array(); $putaran = 0;
        foreach (array_keys($gm) as $i) {
            $x = $g[$i];
            if ($x['pihak'] === $utama || !$grup) {
                $minta = (strpos((string) $x['aksi'], 'meminta') !== false);
                if ($minta) { $putaran++; $nm = 'Putaran revisi ' . $putaran; }
                elseif ($utama === 'ppk') { $nm = (strpos((string) $x['aksi'], 'Memverifikasi') !== false) ? 'Verifikasi akhir PPK BLU' : 'Pemeriksaan PPK BLU'; }
                else { $nm = (strpos((string) $x['aksi'], 'menyetujui') !== false) ? 'Persetujuan akhir Ketua Tim' : 'Peninjauan Ketua Tim'; }
                $grup[] = array('nama' => $nm, 'it' => array());
            }
            $grup[count($grup) - 1]['it'][] = $i;
        }
        // ringkasan per pihak
        $tot = array(); $tunggu = 0; $kerja = 0;
        foreach ($gm as $i => $m) {
            $p = $g[$i]['pihak']; $kw = max(0, $m['s'] - $m['a']); $tw = max(0, $m['b'] - $m['a']);
            $tot[$p] = (isset($tot[$p]) ? $tot[$p] : 0) + $kw; $kerja += $kw; $tunggu += $tw;
        }
        $semua = max(1, $kerja);
        $warna = array('ppk' => 'ppk', 'katim' => 'katim', 'ketupel' => 'ketupel', 'mitra' => 'mitra', 'tim' => 'tim');
        $stk = ''; $leg = '';
        foreach (array('ppk', 'tim', 'katim', 'ketupel', 'mitra') as $p) {
            if (!isset($tot[$p])) { continue; }
            $stk .= '<div class="pa-s-' . $warna[$p] . '" style="width:' . round($tot[$p] / $semua * 100, 2) . '%" title="' . $e($nama[$p] . ': ' . self::durasi($tot[$p])) . '"></div>';
            $leg .= '<span><i class="pa-s-' . $warna[$p] . '"></i>' . $e($nama[$p]) . ' <b>' . $e(self::durasi($tot[$p])) . '</b></span>';
        }
        $minta = $putaran;   // jumlah putaran revisi = jumlah kali pemegang utama meminta revisi
        $total = ($w['lama'] !== null) ? self::durasi($w['lama']) : self::durasi($t1 - $t0);
        $chips = '<span class="pa-jch">Total <b>' . $e($total) . '</b></span><span class="pa-jch"><b>' . count($gm) . '</b> giliran</span>'
               . (($minta > 0 || !empty($w['putaran'])) ? '<span class="pa-jch"><b>' . ($minta > 0 ? $minta : (int) $w['putaran']) . 'x</b> revisi</span>' : '')
               . (empty($w['perkiraan']) ? '<span class="pa-jch"><b>' . count($tot) . '</b> pihak terlibat</span>' : '');
        $cat = !empty($w['perkiraan']) ? $w['perkiraan'] : (!empty($w['catatan']) ? $w['catatan'] : '');
        $h = '<div class="pa-jh"><h5>' . $e($judul) . '</h5><div class="pa-jc">' . $chips . '</div></div>'
           . ($cat !== '' ? '<div class="pa-jn">' . $e($cat) . '</div>' : '')
           . '<div class="pa-jsum"><div class="pa-jlbl">Siapa memegang PO paling lama</div><div class="pa-jstk">' . $stk . '</div><div class="pa-jleg">' . $leg . '</div></div>';
        $no = 0;
        foreach ($grup as $gi) {
            $ia = $gm[$gi['it'][0]]['a']; $ib = $gm[$gi['it'][count($gi['it']) - 1]]['s'];
            $h .= '<div class="pa-jg open"><button type="button" class="pa-jgb" aria-expanded="true"><span><span class="pa-jgt">' . $e($gi['nama']) . '</span><span class="pa-jgs">' . $e($jam($ia)) . ' &rarr; ' . $e($jam($ib)) . ' · ' . count($gi['it']) . ' giliran</span></span>'
                . '<span class="pa-jgr">' . $e(self::durasi($ib - $ia)) . ' <i aria-hidden="true">&#9660;</i></span></button><div class="pa-jgbd"><div>';
            foreach ($gi['it'] as $i) {
                $x = $g[$i]; $m = $gm[$i]; $no++;
                $lamaTxt = ($x['lama'] !== null) ? self::durasi($x['lama']) : ($m['jalan'] ? self::durasi(max(0, $m['s'] - $m['a'])) : '');
                $tw = ($m['b'] - $m['a'] >= 60) ? self::durasi($m['b'] - $m['a']) : '';
                $h .= '<div class="pa-jrw"><span class="pa-jav ' . $e($x['pihak']) . '" title="' . $e($nama[$x['pihak']]) . '">' . $e($ini[$x['pihak']]) . '</span><div class="pa-jtx"><b>' . $e($nama[$x['pihak']]) . ($x['aksi'] !== '' ? ' · ' . $e($x['aksi']) : '') . '</b>'
                    . (!empty($x['sub']) ? '<ol class="pa-jsub"><li>' . implode('</li><li>', array_map($e, $x['sub'])) . '</li></ol><em class="pa-jem">Waktu tiap tahap di atas tidak tercatat, yang diketahui hanya waktu total giliran ini.</em>' : '')
                    . '<span>Diterima ' . $e($jam($m['a'])) . ($x['buka'] !== null ? ' · Dibuka ' . $e($jam($x['buka'])) : '') . ($m['jalan'] ? ' · Sedang berjalan' : ' · Selesai ' . $e($jam($m['s'])))
                    . ($tw !== '' ? ' · menunggu dibuka ' . $e($tw) : '') . '</span></div><div class="pa-jdu">' . $e($lamaTxt) . '</div></div>';
            }
            $h .= '</div></div></div>';
        }
        return $h;
    }

    /** Teks aksi satu giliran untuk rincian putaran */
    private static function aksi($t) {
        $a = $t['akhir'];
        if ($t['step'] === 1) {
            if ($t['pihak'] === 'ketupel') { return 'Memperbaiki PO'; }
            return $a === 'minta_revisi' ? 'Meninjau & meminta revisi' : ($a === 'setuju' ? 'Meninjau & menyetujui' : 'Meninjau PO');
        }
        if ($t['step'] === 3) {
            if ($t['pihak'] === 'ppk') { return $a === 'minta_revisi' ? 'Memeriksa & meminta revisi' : ($a === 'verifikasi' ? 'Memverifikasi PO' : 'Memeriksa PO'); }
            if ($t['pihak'] === 'ketupel') { return 'Memperbaiki PO'; }
            if (!empty($t['review'])) { return $a === 'disposisi' ? 'Meninjau & meminta perbaikan lagi' : 'Meninjau & mengirim ke PPK BLU'; }
            return $a === 'disposisi' ? 'Mendisposisikan revisi' : 'Memperbaiki PO & mengirim ke PPK BLU';
        }
        return '';
    }

    private static function mulai($step, $pihak, $t, $review = false) {
        return array('step' => $step, 'pihak' => $pihak, 'terima' => $t, 'buka' => null, 'selesai' => null, 'akhir' => '', 'review' => $review);
    }

    /** Mesin giliran dari log kejadian */
    private static function dariLog(array $log) {
        $turns = array(); $cur = null; $pernahSetuju = false;
        $tutup = function ($akhir, $t) use (&$turns, &$cur) {
            if ($cur === null) { return; }
            $cur['selesai'] = $t; $cur['akhir'] = $akhir; $turns[] = $cur; $cur = null;
        };
        foreach ($log as $e) {
            $k = (string) $e['kolom']; $v = (string) ($e['nilai'] ?? ''); $t = self::ts($e['waktu']);
            if ($t === null) { continue; }
            if ($k === 'dikirim_at') {
                if ($cur === null && !$turns) { $cur = self::mulai(1, 'katim', $t); }
            } elseif (in_array($k, self::$KOLOM_BUKA, true)) {
                if ($cur !== null && $cur['buka'] === null) { $cur['buka'] = $t; }
            } elseif ($k === 'status_review') {
                if ($v === 'perlu_revisi' && $cur !== null && $cur['step'] === 1 && $cur['pihak'] === 'katim') { $tutup('minta_revisi', $t); $cur = self::mulai(1, 'ketupel', $t); }
                elseif ($v === 'direvisi' && $cur !== null && $cur['step'] === 1 && $cur['pihak'] === 'ketupel') { $tutup('kirim_revisi', $t); $cur = self::mulai(1, 'katim', $t); }
                elseif ($v === 'disetujui' && $cur !== null && $cur['step'] === 1) { $tutup('setuju', $t); $cur = self::mulai(2, 'mitra', $t); $pernahSetuju = true; }
                elseif ($v === 'menunggu_review' && $pernahSetuju) {   // PO dibuka kembali Ketua Tim: hitung ulang dari sini
                    $turns = array(); $cur = self::mulai(1, 'katim', $t); $pernahSetuju = false;
                }
            } elseif ($k === 'tervalidasi_mitra_at') {
                if ($cur !== null && $cur['step'] === 2) { $tutup('verifikasi', $t); $cur = self::mulai(3, 'ppk', $t); }
            } elseif ($k === 'ppk_status') {
                if ($cur === null || $cur['step'] !== 3) { continue; }
                if ($v === 'minta' && $cur['pihak'] === 'ppk') { $tutup('minta_revisi', $t); $cur = self::mulai(3, 'katim', $t); }
                elseif ($v === 'ketupel_kerja' && $cur['pihak'] === 'katim') { $tutup('disposisi', $t); $cur = self::mulai(3, 'ketupel', $t); }
                elseif ($v === 'katim_review' && $cur['pihak'] === 'ketupel') { $tutup('kirim_katim', $t); $cur = self::mulai(3, 'katim', $t, true); }
                elseif ($v === '-' && $cur['pihak'] === 'katim') { $tutup('kirim_ppk', $t); $cur = self::mulai(3, 'ppk', $t); }
            } elseif ($k === 'validasi_keuangan_at') {
                if ($cur !== null && $cur['step'] === 3 && $cur['pihak'] === 'ppk') { $tutup('verifikasi', $t); $cur = self::mulai(4, 'katim', $t); }
            } elseif ($k === 'po_ttd_at') {
                if ($cur !== null && $cur['step'] === 4) { $tutup('unggah', $t); }
            }
        }
        if ($cur !== null) { $turns[] = $cur; }   // giliran yang masih berjalan (selesai = null)
        return $turns;
    }

    /** PO lama tanpa log: satu giliran per langkah dari kolom jejak (putaran revisi tidak terlihat) */
    private static function dariKolom(array $po) {
        $g = function ($k) use ($po) { return isset($po[$k]) ? self::ts($po[$k]) : null; };
        $baru = function ($buka, $terima) { return ($buka !== null && $terima !== null && $buka < $terima) ? null : $buka; };
        $turns = array();
        $kirim = $g('dikirim_at'); $setuju = $g('disetujui_at'); $mitra = $g('tervalidasi_mitra_at'); $ppk = $g('validasi_keuangan_at'); $ttd = $g('po_ttd_at');
        $rondePpk = (int) ($po['ronde_ppk'] ?? 0);
        if ($kirim !== null) {
            $turns[] = array('step' => 1, 'pihak' => 'katim', 'terima' => $kirim, 'buka' => $baru($g('reviewed_at'), $kirim), 'selesai' => $setuju, 'akhir' => 'setuju', 'review' => false);
        }
        if ($setuju !== null) {
            $turns[] = array('step' => 2, 'pihak' => 'mitra', 'terima' => $setuju, 'buka' => $baru($g('mitra_dibaca_at'), $setuju), 'selesai' => $mitra, 'akhir' => 'verifikasi', 'review' => false);
        }
        if ($mitra !== null) {
            $b = ($rondePpk > 0) ? null : $baru($g('ppk_dibaca_at'), $mitra);
            $turns[] = array('step' => 3, 'pihak' => 'ppk', 'terima' => $mitra, 'buka' => $b, 'selesai' => $ppk, 'akhir' => 'verifikasi', 'review' => false);
        }
        if ($ppk !== null) {
            $turns[] = array('step' => 4, 'pihak' => 'katim', 'terima' => $ppk, 'buka' => null, 'selesai' => $ttd, 'akhir' => 'unggah', 'review' => false);
        }
        return $turns;
    }
}
