<?php
/**
 * PoTtd -- penandatangan (tanda tangan) dokumen Petunjuk Operasional, dipakai SEMUA jenis PO
 * (OPTI, Pengujian, Sertifikasi, Kalibrasi, dst).
 *
 * Pejabat dipilih dari master penandatangan (mst_penandatangan_lhu di database sekretariat) yang sama dengan
 * Surat Penawaran. Yang disimpan di PO adalah SALINAN (nama, NIP, jabatan, baris jabatan) saat PO dibuat, bukan cuma
 * id master, supaya PO yang sudah terkirim tidak ikut berubah kalau pejabat di master berganti.
 *
 * Bentuk data satu PO:
 *   [ 'kota' => 'Bandung',
 *     'ppk'        => ['id'=>int, 'nama'=>'', 'nip'=>'', 'jabatan'=>'', 'baris'=>['', ...]],
 *     'ketua'      => (sama),
 *     'mengetahui' => (sama) ]
 * 'baris' = baris jabatan yang tercetak di dokumen (sudah dirapikan, mis. "Kepala" -> "Kepala Balai Besar ...,").
 *
 * Kompatibel PHP 7.2.
 */
class PoTtd
{
    const NAMA_BALAI = 'Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa';
    const KOTA_BAWAAN = 'Bandung';

    protected static $bulan = array('', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');

    public static function slotKosong()
    {
        return array('id' => 0, 'nama' => '', 'nip' => '', 'jabatan' => '', 'baris' => array());
    }

    public static function kosong()
    {
        return array('kota' => self::KOTA_BAWAAN, 'ppk' => self::slotKosong(), 'ketua' => self::slotKosong(), 'mengetahui' => self::slotKosong());
    }

    /* ------------------------------------------------------------------ */
    /* Master penandatangan                                               */
    /* ------------------------------------------------------------------ */

    /** Pecah jabatan mentah (boleh berisi <br>) jadi baris-baris teks bersih. */
    protected static function barisMentah($raw)
    {
        $raw = str_ireplace(array('&nbsp;'), ' ', (string) $raw);
        $parts = preg_split('~<br\s*/?>~i', $raw);
        $out = array();
        foreach ($parts as $p) {
            $p = trim(preg_replace('/\s+/', ' ', strip_tags($p)), " \t\n\r\0\x0B,");
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** Buang "a.n." dari satu teks jabatan: "a.n. Kepala Kepala Bagian X" -> "Kepala Bagian X"; "a.n. Y" -> "Y". */
    public static function tanpaAnTeks($t)
    {
        $t = trim((string) $t);
        if (preg_match('/^a\.?\s?n\.?\s+Kepala[,\s]+(?=\S)/i', $t)) {
            return trim(preg_replace('/^a\.?\s?n\.?\s+Kepala[,\s]+/i', '', $t));
        }
        return trim(preg_replace('/^a\.?\s?n\.?\s+/i', '', $t));
    }

    /** Buang "a.n." dari baris-baris jabatan: baris "a.n. Kepala" yang berdiri sendiri dibuang bila masih ada baris lain. */
    public static function tanpaAn(array $baris)
    {
        $out = array();
        foreach ($baris as $b) {
            $b = trim((string) $b);
            if (count($baris) > 1 && preg_match('/^a\.?\s?n\.?\s+Kepala[,\s]*$/i', $b)) {
                continue;
            }
            $b = self::tanpaAnTeks($b);
            if ($b !== '') {
                $out[] = $b;
            }
        }
        return $out;
    }

    /**
     * Baris jabatan untuk dicetak. "Kepala" saja (boleh berawalan Plh./Plt.) diperpanjang jadi nama balai;
     * "a.n. Kepala Kepala Bagian Tata Usaha" (satu baris) dipecah jadi dua baris.
     */
    public static function barisTampil(array $baris)
    {
        if (count($baris) === 1 && preg_match('/^a\.?\s?n\.?\s+Kepala[,\s]+(.+)$/i', $baris[0], $m)) {
            $baris = array('a.n. Kepala', trim($m[1]));
        }
        $baris = self::tanpaAn($baris);
        $out = array();
        foreach ($baris as $b) {
            if (preg_match('/^((?:plh|plt)\.?\s+)?Kepala$/i', $b, $m)) {
                $b = (isset($m[1]) ? $m[1] : '') . 'Kepala ' . self::NAMA_BALAI . ',';
            }
            $out[] = $b;
        }
        return $out;
    }

    /**
     * Daftar pejabat dari master. $dbSekre = koneksi database sekretariat (sil2020); null -> daftar bawaan.
     * Hanya yang berstatus 'show' (kalau semuanya 'hide' / kolom kosong, semua ditampilkan).
     * Return: list of [id, nama, nip, jabatan, baris, kategori, k]
     */
    public static function daftarMaster($dbSekre)
    {
        $rows = array();
        try {
            if ($dbSekre) {
                $rows = $dbSekre->exec("SELECT id_penandatangan, jabatan, nama, nip, showhide FROM mst_penandatangan_lhu ORDER BY FIELD(showhide, 'show', 'hide'), id_penandatangan DESC");
            }
        } catch (\Throwable $e) {
            error_log('[PoTtd] gagal baca master penandatangan: ' . $e->getMessage());
            $rows = array();
        }
        if (empty($rows)) {
            return self::daftarBawaan();
        }
        $tampil = array();
        $semua = array();
        $lihat = array();
        foreach ($rows as $r) {
            $nama = trim((string) ($r['nama'] ?? ''));
            $baris = self::barisMentah($r['jabatan'] ?? '');
            if ($nama === '') {
                continue;
            }
            $jabatan = implode(' ', $baris);
            $an = (bool) preg_match('/^a\.?\s?n\.?\s/i', $jabatan);
            $key = strtolower($nama . '|' . $jabatan);
            if (isset($lihat[$key])) {
                continue;
            }
            $lihat[$key] = true;
            $bersih = trim(preg_replace('/^(a\.?\s?n\.?|p\.?l\.?[ht]\.?)\s*/i', '', $jabatan));
            $kategori = 'Pejabat & Unit Kerja';
            $k = 'sec';
            if (stripos($bersih, 'Ketua Tim') !== false) {
                $kategori = 'Ketua Tim Kerja';
                $k = 'inf';
            } elseif (stripos($jabatan, 'Kepala') !== false) {
                if (stripos($bersih, 'Bidang') !== false || stripos($bersih, 'Seksi') !== false) {
                    $kategori = 'Pejabat Bidang & Teknis';
                    $k = 'war';
                } else {
                    $kategori = 'Pimpinan Balai & Manajemen';
                    $k = 'pri';
                }
            }
            $item = array(
                'id'       => (int) $r['id_penandatangan'],
                'nama'     => $nama,
                'nip'      => trim((string) ($r['nip'] ?? '')),
                'jabatan'  => self::tanpaAnTeks($jabatan),
                'an'       => $an,
                'baris'    => self::barisTampil($baris ? $baris : array($jabatan)),
                'kategori' => $kategori,
                'k'        => $k,
            );
            $semua[] = $item;
            $sh = strtolower(trim((string) ($r['showhide'] ?? 'show')));
            if ($sh !== 'hide') {
                $tampil[] = $item;
            }
        }
        return $tampil ? $tampil : $semua;
    }

    /** Daftar cadangan bila master tidak terbaca (sama dengan cadangan di Surat Penawaran). */
    protected static function daftarBawaan()
    {
        $d = array(
            array(21, 'Dodiet Prasetyo', 'Kepala'),
            array(18, 'Joko Pratomo', 'Kepala Bagian Tata Usaha'),
            array(20, 'Andar Hermawan', 'Ketua Tim Pelaksanaan Mitra Industri'),
            array(8, 'Emma Safarina Ertaviani', 'Ketua Tim Layanan Mitra Industri'),
            array(10, 'Hendra Yetty', 'Kepala'),
        );
        $out = array();
        foreach ($d as $x) {
            $ketua = stripos($x[2], 'Ketua Tim') !== false;
            $out[] = array(
                'id' => $x[0], 'nama' => $x[1], 'nip' => '', 'jabatan' => $x[2], 'baris' => self::barisTampil(array($x[2])),
                'kategori' => $ketua ? 'Ketua Tim Kerja' : 'Pimpinan Balai & Manajemen', 'k' => $ketua ? 'inf' : 'pri',
            );
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Baca & periksa isian                                               */
    /* ------------------------------------------------------------------ */

    protected static function teks($v, $maks)
    {
        $v = trim(preg_replace('/\s+/', ' ', strip_tags((string) $v)));
        return function_exists('mb_substr') ? mb_substr($v, 0, $maks, 'UTF-8') : substr($v, 0, $maks);
    }

    /** Satu slot dari array mentah (JSON form / hasil decode DB) -> bentuk baku. */
    public static function bersihkanSlot($x)
    {
        $s = self::slotKosong();
        if (!is_array($x)) {
            return $s;
        }
        $s['id'] = (int) ($x['id'] ?? 0);
        $s['nama'] = self::teks($x['nama'] ?? '', 150);
        $s['nip'] = preg_replace('/[^0-9 ]/', '', (string) ($x['nip'] ?? ''));
        $s['jabatan'] = self::tanpaAnTeks(self::teks($x['jabatan'] ?? '', 300));
        $baris = array();
        if (isset($x['baris']) && is_array($x['baris'])) {
            foreach ($x['baris'] as $b) {
                $b = self::teks($b, 200);
                if ($b !== '' && count($baris) < 5) {
                    $baris[] = $b;
                }
            }
        }
        $baris = self::tanpaAn($baris);
        if (!$baris && $s['jabatan'] !== '') {
            $baris = self::barisTampil(array($s['jabatan']));
        }
        $s['baris'] = $baris;
        return $s;
    }

    /** Seluruh blok ttd dari array mentah. $kotaBawaan dipakai bila kota kosong. */
    public static function bersihkan($x, $kotaBawaan = '')
    {
        $x = is_array($x) ? $x : array();
        $kota = self::teks($x['kota'] ?? '', 80);
        if ($kota === '' && $kotaBawaan !== '') {
            $kota = $kotaBawaan;
        }
        return array(
            'kota'       => $kota,
            'ppk'        => self::bersihkanSlot($x['ppk'] ?? null),
            'ketua'      => self::bersihkanSlot($x['ketua'] ?? null),
            'mengetahui' => self::bersihkanSlot($x['mengetahui'] ?? null),
        );
    }

    /** Dari field datar form klasik: ttd_kota, ttd_ppk_nama, ttd_ppk_nip, ttd_ppk_jabatan, ttd_ppk_id, ttd_ppk_baris (JSON), dst. */
    public static function dariPost(array $post, $kotaBawaan = '')
    {
        $raw = array('kota' => $post['ttd_kota'] ?? '');
        foreach (array('ppk', 'ketua', 'mengetahui') as $s) {
            $baris = array();
            if (isset($post['ttd_' . $s . '_baris'])) {
                $dec = json_decode((string) $post['ttd_' . $s . '_baris'], true);
                if (is_array($dec)) {
                    $baris = $dec;
                }
            }
            $raw[$s] = array(
                'id'      => $post['ttd_' . $s . '_id'] ?? 0,
                'nama'    => $post['ttd_' . $s . '_nama'] ?? '',
                'nip'     => $post['ttd_' . $s . '_nip'] ?? '',
                'jabatan' => $post['ttd_' . $s . '_jabatan'] ?? '',
                'baris'   => $baris,
            );
        }
        return self::bersihkan($raw, $kotaBawaan);
    }

    /**
     * Data ttd tersimpan -> bentuk baku. Menerima bentuk BARU ({kota, ppk{id,nama,nip,jabatan,baris}, ketua, mengetahui{...}})
     * dan bentuk LAMA PO Pengujian ({kota, ppk{nama,nip}, ketua{nama,nip}, mengetahui:'tu'|'balai'|'lain', lain{jabatan,nama,nip}})
     * supaya PO yang sudah tersimpan tetap terbaca dan tampil sama seperti dulu.
     */
    public static function dariLama($x, array $o = array())
    {
        $x = is_array($x) ? $x : array();
        if (isset($x['mengetahui']) && is_array($x['mengetahui'])) {
            return self::bersihkan($x);
        }
        $ppkJab = isset($o['ppkJabatan']) ? $o['ppkJabatan'] : 'Pejabat Pembuat Komitmen';
        $ketuaJab = isset($o['ketuaJabatan']) ? $o['ketuaJabatan'] : 'Ketua Tim Kerja';
        $mk = isset($x['mengetahui']) ? (string) $x['mengetahui'] : 'tu';
        $lain = (isset($x['lain']) && is_array($x['lain'])) ? $x['lain'] : array();
        if ($mk === 'lain') {
            $baris = array();
            foreach (explode('|', (string) ($lain['jabatan'] ?? '')) as $b) {
                $b = trim($b);
                if ($b !== '') {
                    $baris[] = $b;
                }
            }
            $meng = array('nama' => $lain['nama'] ?? '', 'nip' => $lain['nip'] ?? '', 'baris' => $baris, 'jabatan' => implode(' ', $baris));
        } elseif ($mk === 'balai') {
            $meng = array('baris' => array('Kepala ' . self::NAMA_BALAI . ','), 'jabatan' => 'Kepala');
        } else {
            $meng = array('baris' => array('a.n. Kepala', 'Kepala Bagian Tata Usaha'), 'jabatan' => 'a.n. Kepala Kepala Bagian Tata Usaha');
        }
        $ppk = (isset($x['ppk']) && is_array($x['ppk'])) ? $x['ppk'] : array();
        $ketua = (isset($x['ketua']) && is_array($x['ketua'])) ? $x['ketua'] : array();
        return self::bersihkan(array(
            'kota'       => $x['kota'] ?? '',
            'ppk'        => array('nama' => $ppk['nama'] ?? '', 'nip' => $ppk['nip'] ?? '', 'jabatan' => $ppkJab, 'baris' => array($ppkJab)),
            'ketua'      => array('nama' => $ketua['nama'] ?? '', 'nip' => $ketua['nip'] ?? '', 'jabatan' => $ketuaJab, 'baris' => array($ketuaJab)),
            'mengetahui' => $meng,
        ));
    }

    /** Pesan kekurangan (untuk aksi Kirim). */
    public static function kekurangan(array $t)
    {
        $m = array();
        if (trim((string) ($t['kota'] ?? '')) === '') {
            $m[] = 'Penandatanganan: kota belum diisi';
        }
        if (trim((string) ($t['ppk']['nama'] ?? '')) === '') {
            $m[] = 'Penandatanganan: pejabat yang menyetujui (Pejabat Pembuat Komitmen) belum dipilih';
        }
        if (trim((string) ($t['ketua']['nama'] ?? '')) === '') {
            $m[] = 'Penandatanganan: pelaksana kegiatan (Ketua Tim) belum dipilih';
        }
        if (trim((string) ($t['mengetahui']['nama'] ?? '')) === '') {
            $m[] = 'Penandatanganan: pejabat yang mengetahui belum dipilih';
        }
        return $m;
    }

    /* ------------------------------------------------------------------ */
    /* Dokumen                                                            */
    /* ------------------------------------------------------------------ */

    protected static function h($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /** "17 April 2026" -> bulan & tahun saja: "April 2026" dari Y-m-d. */
    public static function bulanTahun($tanggal)
    {
        $tanggal = (string) $tanggal;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $tanggal, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            return '';
        }
        return self::$bulan[(int) $m[2]] . ' ' . $m[1];
    }

    protected static function blokBaris(array $s, $bawaan)
    {
        $baris = !empty($s['baris']) ? $s['baris'] : array($bawaan);
        $h = '';
        foreach ($baris as $b) {
            $h .= '<div>' . self::h($b) . '</div>';
        }
        return $h;
    }

    protected static function blokNama(array $s)
    {
        $nama = trim((string) ($s['nama'] ?? ''));
        $nip = trim((string) ($s['nip'] ?? ''));
        if ($nama === '') {
            return '<div style="height:16px">( ............................. )</div>';
        }
        return '<div style="text-decoration:underline">' . self::h($nama) . '</div>' . ($nip !== '' ? '<div>NIP. ' . self::h($nip) . '</div>' : '');
    }

    /**
     * HTML blok tanda tangan (tabel + style sebaris, aman untuk Dompdf maupun browser).
     * $bulanTahun = teks tanggal di atas tanda tangan, mis. "April 2026".
     * $o: ketuaBawaan (jabatan bila belum dipilih), gap (tinggi ruang tanda tangan, px).
     */
    public static function htmlBlok(array $t, $bulanTahun, array $o = array())
    {
        $t = self::bersihkan($t);
        $ketuaBawaan = isset($o['ketuaBawaan']) ? $o['ketuaBawaan'] : 'Ketua Tim Kerja';
        $gap = isset($o['gap']) ? (int) $o['gap'] : 66;
        $kota = trim($t['kota']) !== '' ? self::h($t['kota']) : '.....';
        $tgl = trim((string) $bulanTahun) !== '' ? self::h($bulanTahun) : '.....';
        $td = 'style="border:0;padding:0;vertical-align:top;text-align:center;font-weight:700;';
        return '<div class="ptd-blok" style="margin-top:18px;font-weight:700;page-break-inside:avoid">'
            . '<div style="text-align:right;margin-bottom:14px;font-weight:400">' . $kota . ',&nbsp;&nbsp; ' . $tgl . '</div>'
            . '<table style="width:100%;border-collapse:collapse;table-layout:fixed;border:0"><tr>'
            . '<td ' . $td . 'width:45%"><div>Menyetujui,</div>' . self::blokBaris($t['ppk'], 'Pejabat Pembuat Komitmen') . '</td>'
            . '<td style="border:0;padding:0;width:10%">&nbsp;</td>'
            . '<td ' . $td . 'width:45%"><div>Pelaksana Kegiatan,</div>' . self::blokBaris($t['ketua'], $ketuaBawaan) . '</td>'
            . '</tr><tr>'
            . '<td ' . $td . 'height:' . $gap . 'px">&nbsp;</td><td style="border:0;padding:0">&nbsp;</td><td ' . $td . 'height:' . $gap . 'px">&nbsp;</td>'
            . '</tr><tr>'
            . '<td ' . $td . '">' . self::blokNama($t['ppk']) . '</td><td style="border:0;padding:0">&nbsp;</td><td ' . $td . '">' . self::blokNama($t['ketua']) . '</td>'
            . '</tr></table>'
            . '<table style="width:100%;border-collapse:collapse;table-layout:fixed;border:0;margin-top:24px"><tr><td style="border:0;padding:0;width:15%">&nbsp;</td>'
            . '<td ' . $td . 'width:70%"><div>Mengetahui</div>' . self::blokBaris($t['mengetahui'], 'Kepala ' . self::NAMA_BALAI . ',') . '<div style="height:' . ($gap + 12) . 'px">&nbsp;</div>' . self::blokNama($t['mengetahui']) . '</td>'
            . '<td style="border:0;padding:0;width:15%">&nbsp;</td></tr></table>'
            . '</div>';
    }
}
