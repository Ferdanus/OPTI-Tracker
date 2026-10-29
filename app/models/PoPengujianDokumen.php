<?php

/**
 * PoPengujianDokumen -- isi form PO PENGUJIAN (POST) -> data PO -> dokumen 3 halaman A4 (preview di layar & PDF).
 *
 * Dipakai PoPengujianController:
 *   - dariPost()   : field form (create.html) -> larik data PO ("S"), bentuknya sama dengan data tersimpan (formDariBaris()).
 *   - hitung()     : hitungan RAB (penerimaan, pengeluaran per kategori) dan total estimasi menit.
 *   - kolomJadwal(): kolom tanggal jadwal (harian <= 31 hari, selain itu mingguan 4 minggu/bulan).
 *   - fragmen()    : HTML dokumen untuk preview langsung di layar (balasan preview-live).
 *   - htmlPdf()    : HTML dokumen untuk Dompdf (cetak-live): tanpa flex / variabel CSS / calc() / box-shadow.
 *
 * Halaman: 1 = Judul, Dasar, Tujuan, Ruang Lingkup, Tim | 2 = RAB + Estimasi (dipadatkan agar muat 1 lembar,
 * lihat kerapatan()) | 3 = Jadwal + tanda tangan.
 *
 * Kompatibel PHP 7.2.
 */
class PoPengujianDokumen
{
    protected static $bulan = array('', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
    protected static $romawi = array('', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII');

    /* ================================================================== */
    /* Util                                                               */
    /* ================================================================== */

    protected static function h($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    protected static function num($v)
    {
        if (is_array($v) || $v === null || $v === '') {
            return 0.0;
        }
        $s = str_replace(',', '.', trim((string) $v));
        return is_numeric($s) ? (float) $s : 0.0;
    }

    protected static function rp($n)
    {
        $n = (int) round((float) $n);
        return ($n < 0 ? '-' : '') . number_format(abs($n), 0, ',', '.');
    }

    /** Angka tanpa nol di belakang koma ("60" bukan "60.0"). */
    protected static function angka($n)
    {
        $n = (float) $n;
        if ($n == (int) $n) {
            return (string) (int) $n;
        }
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }

    protected static function huruf($i, $kecil = false)
    {
        $base = $kecil ? 97 : 65;
        $out = '';
        do {
            $out = chr($base + ($i % 26)) . $out;
            $i = (int) floor($i / 26) - 1;
        } while ($i >= 0);
        return $out;
    }

    protected static function tglId($iso)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $iso, $m)) {
            return '...';
        }
        return (int) $m[3] . ' ' . self::$bulan[(int) $m[2]] . ' ' . $m[1];
    }

    protected static function hariId($iso)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $iso, $m)) { return ''; }
        $n = (int) date('N', mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]));
        $h = array(1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu');
        return $h[$n];
    }

    protected static function jam($menit)
    {
        return str_replace('.', ',', number_format(round($menit / 60 * 10) / 10, 1, '.', ''));
    }

    protected static function ph($v, $cadangan)
    {
        $v = (string) $v;
        return $v !== '' ? self::h($v) : '<span class="pp-ph">' . self::h($cadangan) . '</span>';
    }

    protected static function teks($v)
    {
        return (is_array($v) || $v === null) ? '' : trim((string) $v);
    }

    protected static function larik($v)
    {
        return is_array($v) ? array_values($v) : array();
    }

    protected static function tanggalValid($v)
    {
        $t = trim((string) $v);
        $dt = \DateTime::createFromFormat('Y-m-d', $t);
        return ($dt && $dt->format('Y-m-d') === $t) ? $t : '';
    }

    /* ================================================================== */
    /* POST -> data PO                                                    */
    /* ================================================================== */

    /**
     * Field form create.html -> larik data PO. Bentuknya sama dengan PoPengujian::formDariBaris() / dataAwal() di form.
     * TIDAK memeriksa kelengkapan (itu urusan controller untuk aksi Kirim); hanya merapikan tipe data.
     */
    /**
     * Baris po_kegiatan (PO tersimpan) -> struktur dokumen S yang sama dengan hasil dariPost(), untuk pratinjau/PDF PO
     * yang sudah disimpan. Kunci yang tidak tersimpan diisi nilai bawaan dariPost().
     */
    public static function dariBaris(array $row)
    {
        $tgl = isset($row['tanggal_po']) ? (string) $row['tanggal_po'] : '';
        $tgl = ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) ? date('Y-m-d', strtotime($tgl)) : \PoSchema::hariIniWib();
        $S = array_merge(self::dariPost(array('tanggal_po' => $tgl)), \PoPengujian::formDariBaris($row));
        $S['tanggal'] = $tgl;
        return $S;
    }

    public static function dariPost(array $p)
    {
        $t = function ($k) use ($p) { return isset($p[$k]) ? self::teks($p[$k]) : ''; };
        $mentah = function ($k) use ($p) { return (isset($p[$k]) && !is_array($p[$k])) ? (string) $p[$k] : ''; };
        $arr = function ($k) use ($p) { return (isset($p[$k]) && is_array($p[$k])) ? array_values($p[$k]) : array(); };

        $S = array(
            'tanggal'      => self::tanggalValid($t('tanggal_po')),
            'jenis'        => $t('jenis_pengujian'),
            'judul'        => $t('judul_kegiatan'),
            'jenisLayanan' => $t('jenis_layanan'),
            'perusahaan'   => $t('nama_perusahaan'),
            'noPenawaran'  => $t('no_penawaran'),
            'lokasi'       => $t('lokasi'),
            'dasar'        => $mentah('dasar'),
            'tujuan'       => $mentah('tujuan'),
            'ruang'        => $mentah('ruang_lingkup'),
            'versiEditor'  => 2,
        );

        // order dalam PO (PO untuk banyak order): daftar dikirim form sebagai JSON; yang tersimpan dibuat ulang dari database oleh controller
        $S['orders'] = array();
        if (isset($p['order_daftar']) && !is_array($p['order_daftar'])) {
            $raw = json_decode((string) $p['order_daftar'], true);
            foreach (is_array($raw) ? $raw : array() as $o) {
                if (!is_array($o)) { continue; }
                $S['orders'][] = array(
                    'id'            => (int) (isset($o['id']) ? $o['id'] : 0),
                    'mitra'         => self::teks(isset($o['mitra']) && !is_array($o['mitra']) ? $o['mitra'] : ''),
                    'nomor_surat'   => self::teks(isset($o['nomor_surat']) && !is_array($o['nomor_surat']) ? $o['nomor_surat'] : ''),
                    'tanggal_surat' => self::teks(isset($o['tanggal_surat']) && !is_array($o['tanggal_surat']) ? $o['tanggal_surat'] : ''),
                    'alamat'        => self::teks(isset($o['alamat']) && !is_array($o['alamat']) ? $o['alamat'] : ''),
                );
            }
        }
        // jadwal pengambilan contoh per perusahaan (opsional, PO banyak order); id order yang tidak ada di PO dibuang
        $smp = (isset($p['sampling_json']) && !is_array($p['sampling_json'])) ? json_decode((string) $p['sampling_json'], true) : null;
        $S['sampling'] = \PoPengujian::samplingBersih($smp, \PoPengujian::idOrderDariForm($S));
        $S['daftarTampil'] = !empty($p['daftar_tampil']);
        $S['daftarKolom'] = \PoPengujian::pilihanKolomDaftar(isset($p['daftar_kolom']) ? $p['daftar_kolom'] : 'auto');
        $S['daftarUrut'] = (isset($p['daftar_urut']) && $p['daftar_urut'] === 'abc') ? 'abc' : 'sel';

        // tim pelaksana
        $nama = $arr('tp_nama'); $tugas = $arr('tp_tugas'); $uraian = $arr('tp_uraian');
        $S['tim'] = array();
        foreach ($nama as $i => $n) {
            $S['tim'][] = array(
                'nama'   => self::teks($n),
                'tugas'  => self::teks(isset($tugas[$i]) ? $tugas[$i] : ''),
                'uraian' => self::teks(isset($uraian[$i]) ? $uraian[$i] : ''),
            );
        }

        // RAB
        $S['rab'] = array(
            'pengujian' => self::num(isset($p['rab_pengujian']) ? $p['rab_pengujian'] : 0),
            'jasa'      => self::num(isset($p['rab_jasa']) ? $p['rab_jasa'] : 0),
            'transport' => self::num(isset($p['rab_transport']) ? $p['rab_transport'] : 0),
            'induk'     => array(),
        );
        $katNama = (isset($p['kat_nama']) && is_array($p['kat_nama'])) ? $p['kat_nama'] : array();
        $sub = function ($kunci, $i) use ($p) {
            return (isset($p[$kunci]) && is_array($p[$kunci]) && isset($p[$kunci][$i]) && is_array($p[$kunci][$i])) ? array_values($p[$kunci][$i]) : array();
        };
        $satu = function ($kunci, $i) use ($p) {
            return (isset($p[$kunci]) && is_array($p[$kunci]) && isset($p[$kunci][$i]) && !is_array($p[$kunci][$i])) ? (string) $p[$kunci][$i] : '';
        };
        foreach ($katNama as $i => $namaKat) {
            $rpN = $sub('kat_rp_nama', $i); $rpP = $sub('kat_rp_persen', $i);
            $rhU = $sub('kat_rh_uraian', $i); $rhQ = $sub('kat_rh_qty', $i); $rhS = $sub('kat_rh_satuan', $i);
            $rhT = $sub('kat_rh_tarif', $i); $rhH = $sub('kat_rh_hari', $i); $rhF = $sub('kat_rh_faktor', $i);
            $rp = array();
            foreach ($rpN as $j => $n) {
                $rp[] = array('nama' => self::teks($n), 'persen' => self::num(isset($rpP[$j]) ? $rpP[$j] : 0));
            }
            $rh = array();
            foreach ($rhU as $j => $u) {
                $hari = isset($rhH[$j]) ? trim((string) $rhH[$j]) : '';
                $fak  = isset($rhF[$j]) ? trim((string) $rhF[$j]) : '';
                $rh[] = array(
                    'uraian' => self::teks($u),
                    'qty'    => self::num(isset($rhQ[$j]) ? $rhQ[$j] : 0),
                    'satuan' => self::teks(isset($rhS[$j]) ? $rhS[$j] : ''),
                    'tarif'  => self::num(isset($rhT[$j]) ? $rhT[$j] : 0),
                    'hari'   => $hari === '' ? '' : self::num($hari),
                    'faktor' => $fak === '' ? '' : self::num($fak),
                );
            }
            $S['rab']['induk'][] = array(
                'nama'    => self::teks($namaKat),
                'tipe'    => in_array($satu('kat_tipe', $i), array('harga', 'perjalanan'), true) ? $satu('kat_tipe', $i) : 'persen',
                'persen'  => self::num(str_replace('%', '', $satu('kat_persen', $i))),
                'nominal' => self::num($satu('kat_nominal', $i)),
                'rinci'   => $satu('kat_rinci', $i) === '1',
                'rp'      => $rp,
                'rh'      => $rh,
                'trip'    => $satu('kat_tipe', $i) === 'perjalanan' ? self::tripBersih($satu('kat_trip', $i)) : array(),
            );
        }

        // estimasi: template p (menit per personel + Keterangan bebas) atau w (waktu pengerjaan)
        $est = array(
            'template' => $t('est_template') === 'w' ? 'w' : 'p',
            'personel' => array(),
            'rows'     => array(),
            'ketHtml'  => $mentah('est_ket_html'),
            'w'        => array('rows' => array()),
        );
        $jmlP = (isset($p['est_jml_personel']) && is_numeric($p['est_jml_personel'])) ? (int) $p['est_jml_personel'] : count($arr('est_personel'));
        $jmlP = max(1, min(12, $jmlP));
        for ($j = 0; $j < $jmlP; $j++) { $est['personel'][] = array('nama' => ''); }
        foreach ($arr('est_kegiatan') as $i => $k) {
            $m = (isset($p['est_menit']) && is_array($p['est_menit']) && isset($p['est_menit'][$i]) && is_array($p['est_menit'][$i])) ? array_values($p['est_menit'][$i]) : array();
            $menit = array();
            for ($j = 0; $j < $jmlP; $j++) {
                $menit[] = (isset($m[$j]) && is_numeric($m[$j])) ? max(0, (int) $m[$j]) : 0;
            }
            $est['rows'][] = array('kegiatan' => self::teks($k), 'menit' => $menit);
        }
        $wm = $arr('estw_menit'); $wt = $arr('estw_tampil'); $wq = $arr('estw_ket');
        foreach ($arr('estw_kegiatan') as $i => $k) {
            $est['w']['rows'][] = array(
                'kegiatan' => self::teks($k),
                'menit'    => (isset($wm[$i]) && is_numeric($wm[$i])) ? max(0, (int) $wm[$i]) : 0,
                'tampil'   => self::teks(isset($wt[$i]) ? $wt[$i] : ''),
                'ket'      => self::teks(isset($wq[$i]) ? $wq[$i] : ''),
            );
        }
        $S['estimasi'] = \PoPengujian::estimasiNormal($est);

        // jadwal (form mengirim satu JSON: jadwal_groups)
        $rows = array();
        $groups = json_decode($mentah('jadwal_groups'), true);
        if (is_array($groups)) {
            foreach ($groups as $gr) {
                $kegs = (is_array($gr) && isset($gr['kegiatan']) && is_array($gr['kegiatan'])) ? $gr['kegiatan'] : array();
                foreach ($kegs as $k) {
                    $k = is_array($k) ? $k : array();
                    $namaK = self::teks(isset($k['nama']) ? $k['nama'] : '');
                    $tanda = array();
                    foreach (self::larik(isset($k['tanda']) ? $k['tanda'] : null) as $x) {
                        if (is_string($x) && $x !== '' && strlen($x) <= 20) { $tanda[] = $x; }
                    }
                    if ($namaK !== '' || $tanda) { $rows[] = array('nama' => $namaK, 'tanda' => $tanda); }
                }
            }
        }
        $S['jadwal'] = array(
            'mulai'   => self::tanggalValid($t('jadwal_mulai')),
            'selesai' => self::tanggalValid($t('jadwal_selesai')),
            'kolom'   => array(),
            'rows'    => $rows,
        );

        // penandatanganan (pejabat dipilih dari master penandatangan, lihat PoTtd)
        $S['ttd'] = \PoTtd::dariPost($p, 'Bandung');
        return $S;
    }

    /* ================================================================== */
    /* Jadwal                                                             */
    /* ================================================================== */

    /**
     * Kolom tanggal jadwal. Sama dengan PoKegiatanController::generateJadwalKolom (PO OPTI): <= 31 hari = harian
     * (Sabtu/Minggu dan $libur otomatis libur), selebihnya mingguan (minggu 1-4 tiap bulan, bulan pertama & terakhir dipotong mengikuti tanggal mulai/selesai).
     * @param string[] $libur daftar 'Y-m-d' hari libur nasional
     * @return array{mode:?string,kolom:array,bulan_header:array}
     */
    public static function kolomJadwal($mulai, $selesai, array $libur = array())
    {
        $kosong = array('mode' => null, 'kolom' => array(), 'bulan_header' => array());
        $mulai = self::tanggalValid($mulai);
        $selesai = self::tanggalValid($selesai);
        if ($mulai === '' || $selesai === '') {
            return $kosong;
        }
        $d1 = new \DateTime($mulai);
        $d2 = new \DateTime($selesai);
        if ($d2 < $d1) {
            return $kosong;
        }
        $y1 = (int) $d1->format('Y');
        $y2 = (int) $d2->format('Y');
        if ($y1 < 1900 || $y2 > 2100 || $y2 - $y1 > 10) {
            return $kosong; // rentang tidak masuk akal
        }
        $hari = $d1->diff($d2)->days + 1;
        $liburSet = array_flip($libur);
        $kolom = array();
        if ($hari > 31) {
            $mode = 'mingguan';
            $y = $y1; $m = (int) $d1->format('n');
            $ye = $y2; $me = (int) $d2->format('n');
            // Minggu dalam bulan: 1 = tgl 1-7, 2 = 8-14, 3 = 15-21, 4 = tgl 22-akhir bulan. Bulan pertama mulai dari minggu tempat
            // tanggal mulai jatuh, bulan terakhir berhenti di minggu tempat tanggal selesai jatuh (bulan di antaranya penuh 4 minggu),
            // jadi tabel mengikuti rentang tanggal yang diisi -- sama dengan PO OPTI.
            $mingguDariTgl = function ($hari) { return min(4, (int) ceil(((int) $hari) / 7)); };
            $mingguAwal = $mingguDariTgl($d1->format('j'));
            $mingguAkhir = $mingguDariTgl($d2->format('j'));
            while ($y < $ye || ($y === $ye && $m <= $me)) {
                $dari = ($y === $y1 && $m === (int) $d1->format('n')) ? $mingguAwal : 1;
                $sampai = ($y === $y2 && $m === $me) ? $mingguAkhir : 4;
                for ($w = $dari; $w <= $sampai; $w++) {
                    $kolom[] = array('key' => $y . '-' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . '-w' . $w, 'label' => (string) $w,
                        'bulan_label' => self::$bulan[$m] . ' ' . $y, 'libur' => false);
                }
                $m++;
                if ($m > 12) { $m = 1; $y++; }
            }
        } else {
            $mode = 'harian';
            $c = clone $d1;
            while ($c <= $d2) {
                $tgl = $c->format('Y-m-d');
                $kolom[] = array('key' => $tgl, 'label' => $c->format('j'),
                    'bulan_label' => self::$bulan[(int) $c->format('n')] . ' ' . $c->format('Y'),
                    'libur' => ((int) $c->format('N') >= 6) || isset($liburSet[$tgl]));
                $c->modify('+1 day');
            }
        }
        return array('mode' => $mode, 'kolom' => $kolom, 'bulan_header' => self::grupBulan($kolom));
    }

    protected static function grupBulan(array $kolom)
    {
        $g = array();
        foreach ($kolom as $k) {
            $l = count($g) - 1;
            if ($l >= 0 && $g[$l]['label'] === $k['bulan_label']) { $g[$l]['span']++; }
            else { $g[] = array('label' => $k['bulan_label'], 'span' => 1); }
        }
        return $g;
    }

    /** Pecah kolom jadwal jadi beberapa tabel (maks. $maks kolom, utuh per bulan). */
    protected static function potongKolom(array $kolom, $maks = 16)
    {
        if (!$kolom) { return array(); }
        $buat = function (array $cols) {
            $bh = PoPengujianDokumen::grupBulanPublik($cols);
            $a = $bh[0]['label']; $z = $bh[count($bh) - 1]['label'];
            return array('label' => $a === $z ? $a : $a . ' – ' . $z, 'kolom' => $cols, 'bulan' => $bh);
        };
        if (count($kolom) <= $maks) { return array($buat($kolom)); }
        $peta = array();
        foreach ($kolom as $k) { $peta[$k['bulan_label']][] = $k; }
        $out = array(); $cur = array();
        foreach ($peta as $mc) {
            if ($cur && count($cur) + count($mc) > $maks) { $out[] = $buat($cur); $cur = array(); }
            $cur = array_merge($cur, $mc);
        }
        if ($cur) { $out[] = $buat($cur); }
        return $out;
    }

    public static function grupBulanPublik(array $kolom)
    {
        return self::grupBulan($kolom);
    }

    /* ================================================================== */
    /* Hitungan                                                           */
    /* ================================================================== */

    /**
     * Kategori "Perjalanan pengambilan contoh": JSON dari form -> daftar perjalanan.
     * Satu perjalanan = {ids: id order, teks: teks perusahaan di dokumen, auto: bool, lok: lokasi, L: baris biaya {u, o, h, t}}.
     */
    public static function tripBersih($json)
    {
        $raw = json_decode((string) $json, true);
        $out = array();
        foreach (is_array($raw) ? $raw : array() as $t) {
            if (!is_array($t)) { continue; }
            $ids = array();
            foreach (isset($t['ids']) && is_array($t['ids']) ? $t['ids'] : array() as $id) {
                if (is_numeric($id) && (int) $id > 0) { $ids[] = (int) $id; }
            }
            $baris = array();
            foreach (isset($t['L']) && is_array($t['L']) ? $t['L'] : array() as $l) {
                if (!is_array($l)) { continue; }
                $o = isset($l['o']) ? self::num($l['o']) : 0; $h = isset($l['h']) ? self::num($l['h']) : 0;
                $baris[] = array(
                    'u' => self::teks(isset($l['u']) ? $l['u'] : ''),
                    'o' => $o > 0 ? $o : '',
                    'h' => $h > 0 ? $h : '',
                    't' => max(0, self::num(isset($l['t']) ? $l['t'] : 0)),
                );
            }
            $out[] = array(
                'ids'  => $ids,
                'teks' => self::teks(isset($t['teks']) ? $t['teks'] : ''),
                'auto' => !empty($t['auto']),
                'lok'  => self::teks(isset($t['lok']) ? $t['lok'] : ''),
                'L'    => $baris,
            );
        }
        return $out;
    }

    /** Jumlah satu baris biaya perjalanan = Orang x Hari x Tarif (Orang / Hari kosong dihitung 1). */
    protected static function jmlBarisTrip(array $l)
    {
        $o = self::num(isset($l['o']) ? $l['o'] : 0); $h = self::num(isset($l['h']) ? $l['h'] : 0);
        return (int) round(($o > 0 ? $o : 1) * ($h > 0 ? $h : 1) * self::num(isset($l['t']) ? $l['t'] : 0));
    }

    protected static function teksBarisTrip(array $l)
    {
        $s = trim((string) (isset($l['u']) ? $l['u'] : ''));
        $o = self::num(isset($l['o']) ? $l['o'] : 0); $h = self::num(isset($l['h']) ? $l['h'] : 0);
        if ($o > 0 && $h > 0) {
            $s .= ' : ' . self::angka($o) . ' orang x ' . self::angka($h) . ' hari @ Rp ' . self::rp(self::num($l['t'])) . ',-';
        }
        return $s;
    }

    protected static function judulTrip(array $t)
    {
        $teks = trim((string) (isset($t['teks']) ? $t['teks'] : ''));
        $lok = trim((string) (isset($t['lok']) ? $t['lok'] : ''));
        return 'Perjalanan dinas pengambilan contoh' . ($teks !== '' ? ' (' . $teks . ')' : '') . ($lok !== '' ? ', ' . $lok : '');
    }

    public static function hitung(array $S)
    {
        $r = $S['rab'];
        $c = array();
        $c['penerimaan'] = self::num($r['pengujian']) + self::num($r['jasa']) + self::num($r['transport']);
        $r4 = function ($x) { return round($x * 10000) / 10000; };
        $c['ind'] = array();
        $langsung = 0.0;
        foreach ($r['induk'] as $d) {
            $o = array('rh' => array(), 'rp' => array(), 'trip' => array(), 'pct' => 0, 'amt' => 0);
            if ($d['tipe'] === 'perjalanan') {
                // biaya langsung: jumlah semua baris biaya semua perjalanan
                $amt = 0;
                foreach (isset($d['trip']) && is_array($d['trip']) ? $d['trip'] : array() as $t) {
                    $baris = array();
                    foreach ($t['L'] as $l) { $baris[] = self::jmlBarisTrip($l); }
                    $o['trip'][] = $baris;
                    $amt += array_sum($baris);
                }
                $o['amt'] = $amt;
                $langsung += $amt;
            } elseif ($d['tipe'] === 'harga') {
                if ($d['rinci']) {
                    foreach ($d['rh'] as $it) {
                        $hari = ($it['hari'] === '' || self::num($it['hari']) == 0) ? 1 : self::num($it['hari']);
                        $fak = ($it['faktor'] === '' || $it['faktor'] === null) ? 100 : self::num($it['faktor']);
                        $o['rh'][] = (int) round(self::num($it['qty']) * self::num($it['tarif']) * $hari * $fak / 100);
                    }
                    $o['amt'] = array_sum($o['rh']);
                } else {
                    $o['amt'] = self::num($d['nominal']);
                }
                $langsung += $o['amt'];
            }
            $c['ind'][] = $o;
        }
        $c['langsung'] = $langsung;
        $c['base'] = $c['penerimaan'] - $langsung;
        foreach ($r['induk'] as $i => $d) {
            if ($d['tipe'] !== 'persen') { continue; }
            $o = $c['ind'][$i];
            if ($d['rinci']) {
                $s = 0.0;
                foreach ($d['rp'] as $x) { $s += self::num($x['persen']); }
                $o['pct'] = $r4($s);
                foreach ($d['rp'] as $x) { $o['rp'][] = (int) round($c['base'] * self::num($x['persen']) / 100); }
            } else {
                $o['pct'] = $r4(self::num($d['persen']));
            }
            $o['amt'] = (int) round($c['base'] * $o['pct'] / 100);
            $c['ind'][$i] = $o;
        }
        $total = 0.0;
        foreach ($c['ind'] as $o) { $total += $o['amt']; }
        $c['total'] = $total;

        $e = $S['estimasi'];
        $n = count($e['personel']);
        $c['tot'] = array();
        for ($j = 0; $j < $n; $j++) {
            $t = 0;
            foreach ($e['rows'] as $row) { $t += isset($row['menit'][$j]) ? (int) $row['menit'][$j] : 0; }
            $c['tot'][] = $t;
        }
        $c['grand'] = array_sum($c['tot']);
        $c['wTotal'] = 0;
        if (isset($e['w']['rows']) && is_array($e['w']['rows'])) {
            foreach ($e['w']['rows'] as $row) { $c['wTotal'] += isset($row['menit']) ? (int) $row['menit'] : 0; }
        }
        return $c;
    }

    /* ================================================================== */
    /* Kerapatan halaman 2                                                */
    /* ================================================================== */

    /**
     * Halaman 2 (RAB + Estimasi) harus muat 1 lembar A4. Server tidak bisa mengukur tata letak, jadi kerapatan
     * (tinggi baris, jarak sel, ukuran huruf) diperkirakan dari isi, dengan model tinggi baris yang dicocokkan ke hasil
     * ukur di browser. Di layar, create.html masih memeriksa ulang setelah preview tampil (fitHalaman2) dan
     * memadatkan lagi bila perlu. Tingkat 0 = longgar, makin besar = makin padat (maks. 20).
     *
     * @return array{k:int,lh:string,tp:string,d:string}
     */
    public static function kerapatan(array $S, array $c)
    {
        $tinggiMaks = 1041 - 48 - 6; // tinggi isi bingkai halaman 2 (px) setelah padding 2 x 24 dan border
        $k = 0;
        while ($k < 20 && self::perkirakanTinggi($S, $c, $k) > $tinggiMaks) { $k++; }
        return self::varKerapatan($k);
    }

    public static function varKerapatan($k)
    {
        $k = max(0, min(20, (int) $k));
        return array(
            'k'  => $k,
            'lh' => number_format(max(1.12, 1.35 - 0.03 * $k), 2, '.', ''),
            'tp' => number_format(max(1, 3 - 0.2 * $k), 1, '.', ''),
            'd'  => number_format(max(0, $k - 5) * 0.25, 2, '.', ''),
        );
    }

    /** Konstanta model tinggi halaman 2 (hasil kalibrasi terhadap ukuran di browser). */
    public static $kal = array('hn' => 0.632, 'hb' => 0.50, 'off' => 2.0, 'ketlh' => 0.89, 'ketoff' => 4.0, 'fix' => 10.4, 'wrab' => 473.8, 'wkeg' => 60.0, 'wket' => 165.8);

    /** Jumlah baris teks perkiraan: $teks dibungkus di kolom selebar $lebarPx dengan huruf $fsPx. */
    protected static function barisTeks($teks, $lebarPx, $fsPx, $tebal = false)
    {
        $teks = trim((string) $teks);
        if ($teks === '') { return 1; }
        $perHuruf = $fsPx * ($tebal ? self::$kal['hb'] : self::$kal['hn']);
        $muat = max(4, (int) floor($lebarPx / $perHuruf));
        $baris = 0;
        foreach (preg_split('/\R/', $teks) as $para) {
            $len = function_exists('mb_strlen') ? mb_strlen($para, 'UTF-8') : strlen($para);
            $baris += max(1, (int) ceil($len / $muat));
        }
        return $baris;
    }

    /** Jumlah baris teks perkiraan untuk HTML dari editor (daftar, <br>, paragraf, sel tabel). */
    protected static function barisHtml($html, $lebarPx, $fsPx)
    {
        $html = (string) $html;
        if (\PoPengujian::teksPolos($html) === '') { return 1; }
        $lebar = $lebarPx - ((stripos($html, '<ol') !== false || stripos($html, '<ul') !== false) ? 22 : 0);
        $t = preg_replace('~<\s*br\s*/?>~i', "\n", $html);
        $t = preg_replace('~</\s*(li|p|div|tr|h[1-6])\s*>~i', "\n", $t);
        $t = preg_replace('~</\s*(td|th)\s*>~i', ' ', $t);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $n = 0;
        foreach (preg_split('/\R/', rtrim($t)) as $ln) {
            $n += trim($ln) === '' ? 1 : self::barisTeks($ln, $lebar, $fsPx);
        }
        return max(1, $n);
    }

    protected static function perkirakanTinggi(array $S, array $c, $k)
    {
        $v = self::varKerapatan($k);
        $lh = (float) $v['lh']; $tp = (float) $v['tp']; $d = (float) $v['d'];
        $fs = (11 - $d) * 4 / 3;          // px, huruf tabel RAB
        $fsE = (10 - $d) * 4 / 3;         // px, huruf tabel estimasi
        $off = self::$kal['off'];
        $baris = function ($n, $f) use ($lh, $tp, $off) { return $n * $f * $lh + 2 * $tp + $off; };

        $total = self::$kal['fix'];
        // judul bagian 5 (tanpa margin atas) + jarak 5
        $total += 11 * 4 / 3 * 1.35 + 5;

        // tabel RAB: baris tetap = a, Penerimaan, 3 item, b, Total pengeluaran
        $total += $baris(1, $fs) * 7;
        foreach ($S['rab']['induk'] as $d1) {
            $total += $baris(1, $fs);
            if ($d1['tipe'] === 'perjalanan') {
                // judul perjalanan (baris biasa) + baris biaya yang dirapatkan (padding atas / bawah 1px)
                foreach (isset($d1['trip']) && is_array($d1['trip']) ? $d1['trip'] : array() as $t) {
                    $total += $baris(self::barisTeks(self::judulTrip($t), self::$kal['wrab'], $fs), $fs);
                    foreach ($t['L'] as $l) {
                        $total += self::barisTeks(self::teksBarisTrip($l), self::$kal['wrab'], $fs) * $fs * $lh + 2 + $off;
                    }
                }
            } elseif (!empty($d1['rinci'])) {
                if ($d1['tipe'] === 'persen') {
                    $total += $baris(1, $fs) * count($d1['rp']);
                } else {
                    foreach ($d1['rh'] as $it) {
                        $label = $it['uraian'] . ' ' . $it['satuan'] . ' (@' . self::rp(self::num($it['tarif'])) . ') x 99 hari x 100%';
                        $total += $baris(self::barisTeks($label, self::$kal['wrab'], $fs), $fs);
                    }
                }
            }
        }

        // judul bagian 6 (margin atas 18 + 5)
        $total += 18 + 11 * 4 / 3 * 1.35 + 5;

        // tabel estimasi
        $e = $S['estimasi'];
        if ($e['template'] === 'w') {
            $total += $baris(1, $fsE) * 2;     // header + Total
            $lebarKeg = 638 - 38 - 0.22 * 638 - 0.30 * 638 - 14 + self::$kal['wkeg'];   // koreksi kalibrasi sama dengan template P
            $lebarKetW = 0.30 * 638 - 14 + 8;
            $rows = $e['w']['rows'];
            $nr = count($rows);
            if ($nr === 0) { $total += $baris(1, $fsE); }
            for ($i = 0; $i < $nr; $i++) {
                // satu kelompok = baris ber-keterangan + baris kosong keterangan di bawahnya (sel keterangan digabung)
                $j = $i + 1;
                while ($j < $nr && $rows[$j]['ket'] === '') { $j++; }
                $sum = 0.0;
                for ($q = $i; $q < $j; $q++) {
                    $tampil = $rows[$q]['tampil'] !== '' ? $rows[$q]['tampil'] : ($rows[$q]['menit'] . ' menit');
                    $n1 = max(self::barisTeks($rows[$q]['kegiatan'], $lebarKeg, $fsE), self::barisTeks($tampil, 0.22 * 638 - 14, $fsE));
                    $sum += $baris($n1, $fsE);
                }
                $kt = $baris(self::barisTeks($rows[$i]['ket'], $lebarKetW, $fsE), $fsE);
                $total += max($sum, $kt);
                $i = $j - 1;
            }
            return $total;
        }
        $n = max(1, count($e['personel']));
        $lebarKeg = max(60, 638 - 34 - $n * 48 - 0.27 * 638) - 14 + self::$kal['wkeg'];
        $total += $baris(1, $fsE) * 2; // header 2 baris
        $isi = 0.0;
        foreach ($e['rows'] as $row) {
            $isi += $baris(self::barisTeks($row['kegiatan'], $lebarKeg, $fsE), $fsE);
        }
        $isi += 12;
        $isi += ($n === 1 ? 40 : 0); // satu personel: kolom Kegiatan sangat lebar, hasil ukur lebih tinggi dari model
        $isi += $baris(1, $fsE) * 3; // Total, Grand Total (2 baris): sel Keterangan ikut membentang sampai sini
        // sel keterangan (satu kotak, rowspan sampai Grand Total): isinya HTML bebas dari editor, lebar 27% kolom
        $ket = self::barisHtml($e['ketHtml'], self::$kal['wket'], $fsE);
        $isiKet = $ket * $fsE * self::$kal['ketlh'] + 16 + self::$kal['ketoff'];
        $total += max($isi, $isiKet);
        return $total;
    }

    /* ================================================================== */
    /* Dokumen                                                            */
    /* ================================================================== */

    protected static function nomorTampil(array $S, $nomorTersimpan)
    {
        if ($nomorTersimpan !== '') {
            return self::h($nomorTersimpan);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $S['tanggal'], $m)) {
            $rr = self::$romawi[(int) $m[2]]; $yy = $m[1];
        } else {
            $rr = '...'; $yy = '....';
        }
        return '&nbsp;&nbsp;&nbsp;&nbsp; /PO/BBSPJIS/SVPK/' . $rr . '/' . $yy;
    }

    protected static function richBlok(array $S, $html)
    {
        $html = (string) $html;
        if ((int) (isset($S['versiEditor']) ? $S['versiEditor'] : 0) < 2) {
            // PO dari editor lama: daftar bernomor tanpa type = huruf (a, b, c)
            $html = str_replace('<ol>', '<ol type="a">', $html);
        }
        $h = \PoPengujian::bersihHtml($html);
        $h = str_replace('[no_penawaran]', self::h($S['noPenawaran'] !== '' ? $S['noPenawaran'] : '...'), $h);
        $jml = (isset($S['orders']) && is_array($S['orders'])) ? count($S['orders']) : 0;
        $h = str_replace('[jumlah_mitra]', $jml > 0 ? (string) $jml : '...', $h);
        if (\PoPengujian::teksPolos($h) === '') {
            return '<div class="pp-rich pp-ph">-</div>';
        }
        return '<div class="pp-rich">' . $h . '</div>';
    }

    /** Tabel daftar mitra (PO banyak order) bernomor 01, 02, ... dalam 1-4 kolom; kosong bila order kurang dari 2 atau dimatikan. */
    protected static function daftarMitra(array $S)
    {
        $o = (isset($S['orders']) && is_array($S['orders'])) ? $S['orders'] : array();
        if (count($o) < 2 || empty($S['daftarTampil'])) { return ''; }
        $nama = array();
        foreach ($o as $x) {
            $n = trim((string) (isset($x['mitra']) ? $x['mitra'] : ''));
            $nama[] = $n !== '' ? $n : '(mitra tidak ditemukan)';
        }
        if ((isset($S['daftarUrut']) ? $S['daftarUrut'] : 'sel') === 'abc') {
            usort($nama, function ($a, $b) {
                return strcasecmp(preg_replace('/^(PT|CV)\.?\s*/i', '', $a), preg_replace('/^(PT|CV)\.?\s*/i', '', $b));
            });
        }
        $n = count($nama);
        $kol = \PoPengujian::kolomDaftar(isset($S['daftarKolom']) ? $S['daftarKolom'] : 'auto', $n);
        $per = (int) ceil($n / $kol);
        $lebarNo = $n > 99 ? 30 : 22;
        $sel = '';
        for ($c = 0; $c < $kol; $c++) {
            $potong = array_slice($nama, $c * $per, $per);
            if (!$potong) { $sel .= '<td style="vertical-align:top">&nbsp;</td>'; continue; }
            $in = '<table class="pp-nb">';
            foreach ($potong as $i => $nm) {
                $no = str_pad((string) ($c * $per + $i + 1), $n > 99 ? 3 : 2, '0', STR_PAD_LEFT);
                $in .= '<tr><td style="width:' . $lebarNo . 'px;vertical-align:top">' . $no . '.</td><td style="vertical-align:top">' . self::h($nm) . '</td></tr>';
            }
            $sel .= '<td style="vertical-align:top">' . $in . '</table></td>';
        }
        return '<div style="padding-left:20px;margin:6px 0 3px"><table class="pp-daftar"><tr>' . $sel . '</tr></table></div>';
    }

    /** Bagian "Alamat" hanya ada kalau Lokasi Kegiatan diisi; bagian sesudahnya bernomor geser satu. */
    protected static function adaAlamat(array $S)
    {
        return trim((string) (isset($S['lokasi']) ? $S['lokasi'] : '')) !== '';
    }

    /** Nomor bagian untuk bagian yang bernomor dasar $dasar (4 = Tim Pelaksana, dst) setelah memperhitungkan bagian Alamat. */
    protected static function noBagian(array $S, $dasar)
    {
        return $dasar + (self::adaAlamat($S) ? 1 : 0);
    }

    protected static function halIsi(array $S, $nomorTersimpan)
    {
        $tim = '';
        foreach ($S['tim'] as $i => $t) {
            $tim .= '<tr><td class="pp-c">' . ($i + 1) . '</td><td>' . self::h($t['nama']) . '</td><td class="pp-c">' . self::h($t['tugas'])
                . '</td><td style="text-align:justify">' . str_replace("\n", '<br>', self::h($t['uraian'])) . '</td></tr>';
        }
        if ($tim === '') {
            $tim = '<tr><td colspan="4" class="pp-c pp-ph">Belum ada anggota</td></tr>';
        }
        return '<div class="pp-page framed" id="pv1"><div class="pp-frame">'
            . '<div class="pp-doc-title">PETUNJUK OPERASIONAL (PO)</div>'
            . '<div class="pp-doc-nomor">Nomor : ' . self::nomorTampil($S, $nomorTersimpan) . '</div>'
            . '<table class="pp-nb pp-meta"><tr><td style="width:50px;font-weight:700;vertical-align:top">Judul</td><td style="width:12px;font-weight:700;vertical-align:top">:</td><td style="font-weight:700;vertical-align:top;text-align:left">' . self::ph($S['judul'], 'Judul kegiatan') . '</td></tr>'
            . '<tr><td style="font-weight:700;vertical-align:top">Jenis</td><td style="font-weight:700;vertical-align:top">:</td><td style="font-weight:700;vertical-align:top;text-align:left">' . self::ph($S['jenisLayanan'], '-') . '</td></tr></table>'
            . '<div class="pp-sec">1.&nbsp;&nbsp; Dasar</div>' . self::richBlok($S, $S['dasar']) . self::daftarMitra($S)
            . '<div class="pp-sec">2.&nbsp;&nbsp; Tujuan Kegiatan</div>' . self::richBlok($S, $S['tujuan'])
            . '<div class="pp-sec">3.&nbsp;&nbsp; Ruang Lingkup</div>' . self::richBlok($S, $S['ruang'])
            . (self::adaAlamat($S) ? '<div class="pp-sec">4.&nbsp;&nbsp; Alamat</div><div class="pp-rich">' . self::h(trim((string) $S['lokasi'])) . '</div>' : '')
            . '<div class="pp-sec">' . self::noBagian($S, 4) . '.&nbsp;&nbsp; Tim Pelaksana Kegiatan</div>'
            . '<table style="font-size:10.5pt"><thead><tr><th style="width:38px">No</th><th style="width:27%">Nama</th><th style="width:28%">Tugas/Tanggung jawab</th><th>Uraian Tugas</th></tr></thead><tbody>'
            . $tim . '</tbody></table>'
            . '</div></div>';
    }

    protected static function rumusTeks(array $it)
    {
        $s = ' (@' . self::rp(self::num($it['tarif'])) . ')';
        if (self::num($it['hari']) > 1) { $s .= ' x ' . self::angka(self::num($it['hari'])) . ' hari'; }
        if ($it['faktor'] !== '' && $it['faktor'] !== null && self::num($it['faktor']) != 100) { $s .= ' x ' . self::angka(self::num($it['faktor'])) . '%'; }
        return $s;
    }

    protected static function labelRh(array $it)
    {
        $q = self::num($it['qty']);
        $sat = trim((string) $it['satuan']);
        $nm = (string) $it['uraian'];
        $jml = $sat !== '' ? self::angka($q) . ' ' . $sat : self::angka($q);
        if (strpos($nm, '[jml]') !== false) {
            $out = str_replace('[jml]', self::h($jml), self::h($nm));
        } else {
            $out = self::h($nm) . ($sat !== '' ? ' ' . self::h($jml) : '');
        }
        return $out . self::h(self::rumusTeks($it));
    }

    /** Satu perjalanan: baris judul (a. Perjalanan dinas ...) lalu baris biaya tanpa garis di antaranya, Rp + jumlah di kolom kanan. */
    protected static function barisPerjalanan(array $t, $ti, array $jumlah)
    {
        $n = count($t['L']);
        $bt = $n ? 'border-bottom:0;' : '';
        $out = '<tr><td style="' . $bt . '"></td><td style="padding-left:14px;' . $bt . '">' . self::huruf($ti, true) . '. ' . self::h(self::judulTrip($t)) . '</td>'
            . '<td style="' . $bt . '"></td><td style="' . $bt . '"></td></tr>';
        foreach ($t['L'] as $i => $l) {
            $gb = 'border-top:0;' . ($i < $n - 1 ? 'border-bottom:0;' : '') . 'padding-top:1px;padding-bottom:1px;';
            $out .= '<tr><td style="' . $gb . '"></td><td style="padding-left:14px;' . $gb . '">' . self::h(self::teksBarisTrip($l)) . '</td>'
                . '<td class="pp-rpk" style="' . $gb . '">Rp</td><td class="pp-r pp-rpk" style="' . $gb . '">' . self::rp(isset($jumlah[$i]) ? $jumlah[$i] : 0) . '</td></tr>';
        }
        return $out;
    }

    protected static function halRab(array $S, array $c, array $v)
    {
        $r = $S['rab'];
        $baris = function ($lbl, $val, $tebal = false, $indent = 0) {
            return '<tr><td></td><td' . ($tebal ? ' class="pp-b"' : '') . ($indent ? ' style="padding-left:' . $indent . 'px"' : '') . '>' . $lbl . '</td>'
                . '<td class="pp-rpk">Rp</td><td class="pp-r pp-rpk' . ($tebal ? ' pp-b' : '') . '">' . PoPengujianDokumen::rpPublik($val) . '</td></tr>';
        };
        $ind = '';
        $hurufTot = array();
        foreach ($r['induk'] as $i => $d) {
            $o = $c['ind'][$i];
            $L = self::huruf($i);
            $hurufTot[] = $L;
            if ($d['tipe'] === 'perjalanan') {
                $ind .= $baris($L . '. ' . self::h($d['nama']), $o['amt'], true);
                foreach (isset($d['trip']) && is_array($d['trip']) ? $d['trip'] : array() as $ti => $t) {
                    $ind .= self::barisPerjalanan($t, $ti, isset($o['trip'][$ti]) ? $o['trip'][$ti] : array());
                }
            } elseif ($d['tipe'] === 'persen') {
                $ind .= $baris($L . '. ' . self::h($d['nama']) . ' (' . self::angka(self::num($o['pct'])) . '% dari Biaya Uji)', $o['amt'], true);
                if ($d['rinci']) {
                    foreach ($d['rp'] as $j => $x) {
                        $ind .= $baris(self::huruf($j, true) . '. ' . self::h($x['nama']) . ' ' . self::angka(self::num($x['persen'])) . '%', $o['rp'][$j], true, 14);
                    }
                }
            } else {
                $ind .= $baris($L . '. ' . self::h($d['nama']), $o['amt'], true);
                if ($d['rinci']) {
                    foreach ($d['rh'] as $j => $it) {
                        $ind .= $baris(self::huruf($j, true) . '. ' . self::labelRh($it), $o['rh'][$j], false, 14);
                    }
                }
            }
        }
        $hurufStr = implode('+', $hurufTot);
        $rab = '<table><colgroup><col style="width:28px"><col><col style="width:36px"><col style="width:118px"></colgroup><tbody>'
            . '<tr><td style="width:28px" class="pp-c pp-b">a</td><td colspan="3" class="pp-b">Laboratorium ' . self::h($S['jenis']) . '</td></tr>'
            . $baris('Penerimaan', $c['penerimaan'], true)
            . $baris('Biaya Pengujian', self::num($r['pengujian']))
            . $baris('Biaya jasa pengambilan contoh', self::num($r['jasa']))
            . $baris('Transportasi', self::num($r['transport']))
            . '<tr><td class="pp-c pp-b">b</td><td class="pp-b">Rencana Pengeluaran</td><td></td><td class="pp-r pp-rpk">' . self::rp($c['base']) . '</td></tr>'
            . $ind
            . $baris('Total Pengeluaran' . ($hurufStr !== '' ? ' (' . $hurufStr . ')' : ''), $c['total'], true)
            . '</tbody></table>';

        $e = $S['estimasi'];
        $judulEst = 'Estimasi Kegiatan Pengambilan Contoh';
        if ($e['template'] === 'w') {
            $judulEst .= ' dan Pengujian';
            $est = self::tabelEstimasiW($e, $c);
        } else {
            $est = self::tabelEstimasiP($e, $c);
        }

        $st = ' style="--lh:' . $v['lh'] . ';--tp:' . $v['tp'] . 'px;--d:' . $v['d'] . 'pt"';
        return '<div class="pp-page framed" id="pv2"><div class="pp-frame"' . $st . '>'
            . '<div class="pp-sec" style="margin-top:0">' . self::noBagian($S, 5) . '.&nbsp;&nbsp; Rencana Anggaran dan Biaya</div>' . $rab
            . '<div class="pp-sec" style="margin-top:18px">' . self::noBagian($S, 6) . '.&nbsp;&nbsp; ' . $judulEst . '</div>' . $est
            . '</div></div>';
    }

    /** Template P: waktu (menit) per personel P1..Pn; Keterangan = satu kotak dari editor, membentang sampai Grand Total. */
    protected static function tabelEstimasiP(array $e, array $c)
    {
        $n = count($e['personel']);
        $kolSpan = max(1, $n);
        $nRows = count($e['rows']);
        $head = '<tr><th rowspan="2" style="width:34px">No</th><th rowspan="2">Kegiatan</th><th colspan="' . $kolSpan . '">Waktu Pengerjaan</th><th rowspan="2" style="width:27%">Keterangan</th></tr><tr>';
        foreach ($e['personel'] as $i => $p) { $head .= '<th style="width:46px">P' . ($i + 1) . '</th>'; }
        if ($n === 0) { $head .= '<th style="width:46px">-</th>'; }
        $head .= '</tr>';
        $ketIsi = \PoPengujian::teksPolos($e['ketHtml']) === '' ? '' : '<div class="pp-ket">' . $e['ketHtml'] . '</div>';
        // baris data + spasi + Total + Grand Total (2 baris)
        $ketTd = '<td rowspan="' . (max(1, $nRows) + ($nRows ? 1 : 0) + 3) . '" style="vertical-align:top;padding-top:6px;text-align:left">' . $ketIsi . '</td>';
        $rows = '';
        foreach ($e['rows'] as $i => $rw) {
            $rows .= '<tr><td class="pp-c">' . ($i + 1) . '</td><td>' . str_replace("\n", '<br>', self::h($rw['kegiatan'])) . '</td>';
            for ($j = 0; $j < $n; $j++) {
                $m = isset($rw['menit'][$j]) ? (int) $rw['menit'][$j] : 0;
                $rows .= '<td class="pp-c">' . ($m ? $m : '-') . '</td>';
            }
            if ($n === 0) { $rows .= '<td class="pp-c">-</td>'; }
            if ($i === 0) { $rows .= $ketTd; }
            $rows .= '</tr>';
        }
        if ($nRows === 0) {
            $rows = '<tr><td colspan="' . ($kolSpan + 2) . '" class="pp-c pp-ph">Belum ada kegiatan</td>' . $ketTd . '</tr>';
        } else {
            $rows .= '<tr><td colspan="' . ($kolSpan + 2) . '" style="height:12px"></td></tr>';
        }
        $cg = '<colgroup><col style="width:34px"><col>';
        for ($j = 0; $j < $kolSpan; $j++) { $cg .= '<col style="width:48px">'; }
        $cg .= '<col style="width:27%"></colgroup>';
        $tot = '';
        foreach ($c['tot'] as $t) { $tot .= '<td class="pp-c">' . $t . '</td>'; }
        if ($n === 0) { $tot .= '<td class="pp-c">0</td>'; }
        return '<table class="pp-est">' . $cg . '<thead>' . $head . '</thead><tbody>' . $rows
            . '<tr><td colspan="2" class="pp-c">Total</td>' . $tot . '</tr>'
            . '<tr><td colspan="2" rowspan="2" class="pp-c">Grand Total</td><td colspan="' . $kolSpan . '" class="pp-c">' . $c['grand'] . ' menit</td></tr>'
            . '<tr><td colspan="' . $kolSpan . '" class="pp-c">' . self::jam($c['grand']) . ' jam</td></tr></tbody></table>';
    }

    /** "8 jam 30 menit" */
    protected static function jamMenit($menit)
    {
        $menit = max(0, (int) $menit);
        return floor($menit / 60) . ' jam ' . ($menit % 60) . ' menit';
    }

    /** Template Waktu Pengerjaan: No | Kegiatan | Waktu pengerjaan | Keterangan (kosong = menyatu dengan baris di atasnya) + Total jam. */
    protected static function tabelEstimasiW(array $e, array $c)
    {
        $rows = $e['w']['rows'];
        $n = count($rows);
        $isi = '';
        if ($n === 0) {
            $isi = '<tr><td colspan="4" class="pp-c pp-ph">Belum ada kegiatan</td></tr>';
        }
        for ($i = 0; $i < $n; $i++) {
            $r = $rows[$i];
            $tampil = $r['tampil'] !== '' ? $r['tampil'] : ($r['menit'] . ' menit');
            $isi .= '<tr><td class="pp-c">' . ($i + 1) . '</td><td>' . self::h($r['kegiatan']) . '</td><td class="pp-c">' . self::h($tampil) . '</td>';
            if ($i === 0 || $r['ket'] !== '') {
                $span = 1;
                while ($i + $span < $n && $rows[$i + $span]['ket'] === '') { $span++; }
                $isi .= '<td rowspan="' . $span . '" class="pp-c" style="vertical-align:top;padding-top:6px">' . self::h($r['ket']) . '</td>';
            }
            $isi .= '</tr>';
        }
        return '<table class="pp-est"><colgroup><col style="width:38px"><col><col style="width:22%"><col style="width:30%"></colgroup>'
            . '<thead><tr><th>No</th><th>Kegiatan</th><th>Waktu pengerjaan</th><th>Keterangan</th></tr></thead><tbody>' . $isi
            . '<tr><td colspan="2" class="pp-c pp-b">Total</td><td class="pp-c pp-b"><i>' . self::jamMenit($c['wTotal']) . '</i></td><td></td></tr></tbody></table>';
    }

    public static function rpPublik($n)
    {
        return self::rp($n);
    }

    protected static function halJadwal(array $S, array $kolom, $denganTtd = true)
    {
        $jd = $S['jadwal'];
        $ch = self::potongKolom($kolom, 16);
        $tabel = '';
        if (!$ch) {
            $tabel = '<table><colgroup><col style="width:38px"><col></colgroup><thead><tr><th style="width:38px">No</th><th>Kegiatan</th></tr></thead><tbody>'
                . '<tr><td colspan="2" class="pp-c pp-ph">Isi tanggal mulai dan selesai untuk menampilkan jadwal</td></tr></tbody></table>';
        }
        foreach ($ch as $cc) {
            $n = count($cc['kolom']);
            $w = (int) floor(52 / max($n, 1));
            $head = '<tr><th rowspan="2" style="width:38px">No</th><th rowspan="2">Kegiatan</th>';
            // nama bulan disingkat (Okt, Nov, ...) bila kolomnya banyak atau ada bulan yang cuma 1-2 kolom (kalau tidak, hurufnya turun satu-satu)
            $singkat = $n > 12;
            foreach ($cc['bulan'] as $m) { if ($m['span'] < 3) { $singkat = true; } }
            foreach ($cc['bulan'] as $m) {
                $lb = $m['label'];
                if ($singkat) { $lb = $m['span'] < 2 ? preg_replace('/^(\S{3})\S*\s+\d\d(\d\d)$/', "$1 '$2", $lb) : preg_replace('/^(\S{3})\S+/', '$1', $lb); }
                $head .= '<th colspan="' . $m['span'] . '"' . ($singkat && $m['span'] < 2 ? ' style="font-size:.7em;padding:2px 0"' : '') . '>' . self::h($lb) . '</th>';
            }
            $head .= '</tr><tr>';
            foreach ($cc['kolom'] as $k) {
                $head .= '<th style="width:' . $w . '%;font-size:.8em;padding:2px 0"' . ($k['libur'] ? ' class="pp-lib"' : '') . '>' . self::h($k['label']) . '</th>';
            }
            $head .= '</tr>';
            $rows = '';
            foreach ($jd['rows'] as $i => $r) {
                $rows .= '<tr style="height:24px"><td class="pp-c">' . ($i + 1) . '.</td><td>' . self::h($r['nama']) . '</td>';
                foreach ($cc['kolom'] as $k) {
                    $cls = $k['libur'] ? 'pp-lib' : (in_array($k['key'], $r['tanda'], true) ? 'pp-dark' : '');
                    $rows .= '<td' . ($cls !== '' ? ' class="' . $cls . '"' : '') . '>&nbsp;</td>';
                }
                $rows .= '</tr>';
            }
            if ($rows === '') {
                $rows = '<tr><td colspan="' . ($n + 2) . '" class="pp-c pp-ph">Belum ada kegiatan</td></tr>';
            }
            $cg = '<colgroup><col style="width:38px"><col>';
            for ($i = 0; $i < $n; $i++) { $cg .= '<col style="width:' . $w . '%">'; }
            $cg .= '</colgroup>';
            $tabel .= (count($ch) > 1 ? '<div style="font-weight:700;margin:10px 0 4px;font-size:.9em">' . self::h($cc['label']) . '</div>' : '')
                . '<table style="margin-bottom:6px">' . $cg . '<thead>' . $head . '</thead><tbody>' . $rows . '</tbody></table>';
        }

        $ttdBlok = $denganTtd ? self::blokTtd($S) : '';
        return '<div class="pp-page framed' . ($denganTtd ? ' pp-last' : '') . '" id="pv3"><div class="pp-frame">'
            . '<div class="pp-sec" style="margin-top:0">' . self::noBagian($S, 7) . '.&nbsp;&nbsp; Jadwal Kegiatan Pengambilan Contoh</div>'
            . $tabel
            . $ttdBlok
            . '</div></div>';
    }

    protected static function blokTtd(array $S)
    {
        return \PoTtd::htmlBlok($S['ttd'], \PoTtd::bulanTahun($S['tanggal']), array('ketuaBawaan' => 'Ketua Tim Pengujian dan Uji Profisiensi', 'gap' => 62));
    }

    /** Bagian opsional "Jadwal Kegiatan Pengambilan Contoh <jenis>" per perusahaan: hanya untuk PO banyak order yang memilih tabel ini. */
    protected static function sampingAktif(array $S)
    {
        return isset($S['orders']) && is_array($S['orders']) && count($S['orders']) > 1
            && isset($S['sampling']['mode']) && $S['sampling']['mode'] === 'tabel';
    }

    /**
     * Halaman jadwal pengambilan contoh (pv4, pv5, ...): tabel No | Tanggal | Nama Perusahaan/Alamat | Petugas Sampling.
     * Dibagi ke beberapa halaman menurut perkiraan tinggi baris; tanda tangan ada di halaman terakhir.
     */
    protected static function halSampling(array $S)
    {
        $smp = $S['sampling'];
        $by = array();
        foreach ($S['orders'] as $o) { if (!empty($o['id'])) { $by[(int) $o['id']] = $o; } }

        // urutkan tanggal naik; tanggal kosong di akhir
        $hari = array_values($smp['hari']);
        usort($hari, function ($a, $b) {
            if ($a['tgl'] === $b['tgl']) { return 0; }
            if ($a['tgl'] === '') { return 1; }
            if ($b['tgl'] === '') { return -1; }
            return strcmp($a['tgl'], $b['tgl']);
        });

        // satuan baris = satu regu; tinggi diperkirakan
        $fs = 13.3; $lh = 18; $lebarNama = 280;
        // perkiraan baris teks 10pt Times (rata-rata lebar huruf ~0,43 em; sengaja agak lebar supaya aman di Dompdf)
        $baris_ = function ($teks, $lebar) use ($fs) {
            $muat = max(6, (int) floor($lebar / ($fs * 0.43)));
            $len = function_exists('mb_strlen') ? mb_strlen($teks, 'UTF-8') : strlen($teks);
            return max(1, (int) ceil($len / $muat));
        };
        $unit = array();
        foreach ($hari as $hi => $h) {
            $regu = $h['regu'] ? $h['regu'] : array(array('orders' => array(), 'ppc' => array(), 'supir' => array()));
            foreach ($regu as $ri => $r) {
                $baris = 0; $isiOrder = array();
                foreach ($r['orders'] as $k => $id) {
                    $o = isset($by[$id]) ? $by[$id] : array('mitra' => '(order tidak ditemukan)', 'alamat' => '');
                    $nm = trim((string) $o['mitra']) !== '' ? trim((string) $o['mitra']) : '(mitra tidak ditemukan)';
                    $tx = $nm . (trim((string) $o['alamat']) !== '' ? ' : ' . trim((string) $o['alamat']) : '');
                    $isiOrder[] = $tx;
                    $baris += $baris_(($k + 1) . '. ' . $tx, $lebarNama);
                }
                $petugas = array();
                foreach ($r['ppc'] as $n) { $petugas[] = 'PPC : ' . $n; }
                foreach ($r['supir'] as $n) { $petugas[] = 'Pengemudi : ' . $n; }
                $bp = 0;
                foreach ($petugas as $t) { $bp += $baris_($t, 150); }
                $tinggi = max($baris * $lh + count($isiOrder) * 3, $bp * $lh, $lh) + 10;
                $unit[] = array('hi' => $hi, 'h' => $tinggi, 'order' => $isiOrder, 'petugas' => $petugas);
            }
        }

        // bagi ke halaman
        $kapAwal = 790; $kapLanjut = 820; $tinggiTtd = 210;
        $halaman = array(array()); $pakai = 0; $kap = $kapAwal;
        foreach ($unit as $u) {
            if ($pakai + $u['h'] > $kap && $halaman[count($halaman) - 1]) {
                $halaman[] = array(); $pakai = 0; $kap = $kapLanjut;
            }
            $halaman[count($halaman) - 1][] = $u;
            $pakai += $u['h'];
        }
        $perluHalamanTtd = ($pakai + $tinggiTtd > $kap);

        $judul = 'Jadwal Kegiatan Pengambilan Contoh' . (trim((string) $S['jenis']) !== '' ? ' ' . trim((string) $S['jenis']) : '');
        $out = '';
        $nomorHari = array(); $urutHari = 0;
        foreach ($halaman as $pi => $isi) {
            $rows = '';
            if (!$isi) {
                $rows = '<tr><td colspan="4" class="pp-c pp-ph">Belum ada jadwal pengambilan contoh</td></tr>';
            }
            // rowspan No & Tanggal per hari pada halaman ini
            $n = count($isi);
            for ($i = 0; $i < $n; $i++) {
                $u = $isi[$i];
                $mulai = ($i === 0 || $isi[$i - 1]['hi'] !== $u['hi']);
                $span = 1;
                if ($mulai) { while ($i + $span < $n && $isi[$i + $span]['hi'] === $u['hi']) { $span++; } }
                if (!isset($nomorHari[$u['hi']])) { $nomorHari[$u['hi']] = ++$urutHari; }
                $cNama = '';
                foreach ($u['order'] as $k => $tx) {
                    $cNama .= '<div style="margin:0 0 3px"><table class="pp-nb"><tr><td style="width:18px;vertical-align:top">' . ($k + 1) . '.</td><td style="vertical-align:top">' . self::h($tx) . '</td></tr></table></div>';
                }
                if ($cNama === '') { $cNama = '<span class="pp-ph">Pilih perusahaan</span>'; }
                $cPet = '';
                foreach ($u['petugas'] as $t) { $cPet .= '<div>' . self::h($t) . '</div>'; }
                $rows .= '<tr>';
                if ($mulai) {
                    $h = $hari[$u['hi']];
                    $tgl = $h['tgl'] !== '' ? self::hariId($h['tgl']) . '<br>' . self::h(self::tglId($h['tgl'])) : '<span class="pp-ph">Tanggal belum diisi</span>';
                    $rows .= '<td class="pp-c" rowspan="' . $span . '">' . $nomorHari[$u['hi']] . '</td><td class="pp-c" rowspan="' . $span . '">' . $tgl . '</td>';
                }
                $rows .= '<td style="vertical-align:top">' . $cNama . '</td><td style="vertical-align:top">' . $cPet . '</td></tr>';
            }
            $terakhir = ($pi === count($halaman) - 1);
            $ttd = ($terakhir && !$perluHalamanTtd) ? self::blokTtd($S) : '';
            $tabel = '<table class="pp-smp"><colgroup><col style="width:38px"><col style="width:118px"><col><col style="width:170px"></colgroup>'
                . '<thead><tr><th>No</th><th>Tanggal/Tanggal</th><th>Nama Perusahaan/Alamat</th><th>Petugas Sampling</th></tr></thead><tbody>' . $rows . '</tbody></table>';
            $out .= '<div class="pp-page framed' . (($terakhir && !$perluHalamanTtd) ? ' pp-last' : '') . '" id="pv' . (4 + $pi) . '"><div class="pp-frame">'
                . '<div class="pp-sec" style="margin-top:0">' . self::noBagian($S, 8) . '.&nbsp;&nbsp; ' . self::h($judul) . ($pi > 0 ? ' (lanjutan)' : '') . '</div>'
                . $tabel . $ttd . '</div></div>';
        }
        if ($perluHalamanTtd) {
            $out .= '<div class="pp-page framed pp-last" id="pv' . (4 + count($halaman)) . '"><div class="pp-frame">' . self::blokTtd($S) . '</div></div>';
        }
        return $out;
    }

    /** Halaman jadwal (+ jadwal pengambilan contoh bila dipilih). */
    protected static function halJadwalLengkap(array $S, array $kolom)
    {
        if (!self::sampingAktif($S)) { return self::halJadwal($S, $kolom, true); }
        return self::halJadwal($S, $kolom, false) . self::halSampling($S);
    }

    public static function hPublik($v)
    {
        return self::h($v);
    }

    /* ---------- CSS ---------- */

    protected static function css($pdf, array $v)
    {
        $a = array(
            '.po-doc-scope{font-family:"Times New Roman",Times,serif;color:#000;text-align:left}',
            '.pp-docpages{width:794px}',
            '.pp-page table{width:100%;border-collapse:collapse;table-layout:fixed}',
            '.pp-page td,.pp-page th{border:1px solid #000;padding:3px 7px;vertical-align:middle;word-wrap:break-word}',
            '.pp-page th{font-weight:700;text-align:center}',
            '.pp-doc-title{text-align:center;font-weight:700;text-decoration:underline;font-size:12pt}',
            '.pp-doc-nomor{text-align:center;font-weight:700;margin-bottom:14px}',
            '.pp-page table.pp-nb td{border:0;padding:1px 0}',
            '.pp-page table.pp-daftar{font-size:10pt}',
            '.pp-page table.pp-smp{font-size:10pt}',
            '.pp-page table.pp-smp td table.pp-nb{table-layout:fixed}',
            '.pp-sec{font-weight:700;margin:14px 0 5px}',
            '.pp-rich{padding-left:20px;text-align:justify}',
            '.pp-rich ol{list-style-type:decimal}',
            '.pp-rich ol[type="a"]{list-style-type:lower-alpha}',
            '.pp-rich ol[type="1"]{list-style-type:decimal}',
            '.pp-rich ol,.pp-rich ul{margin:0 0 3px;padding-left:22px}',
            '.pp-rich ol ol{list-style-type:lower-alpha}',
            '.pp-rich p{margin:0 0 3px}',
            '.pp-rich table{margin:4px 0}',
            '.pp-c{text-align:center}.pp-r{text-align:right}.pp-b{font-weight:700}',
            '.pp-lib{background:#e5e7eb}',
            '.pp-dark{background:#000}',
            '.pp-rpk{white-space:nowrap}',
            '.pp-page table.pp-ttd{table-layout:auto;text-align:center;font-weight:700}',
            '.pp-page table.pp-ttd td{border:0;padding:0;vertical-align:top}',
            '.pp-ttd .blk{width:50%}.pp-ttd .gap{height:78px}.pp-ttd .nm{text-decoration:underline}',
            '.pp-ph{color:#94a3b8}',
            '.pp-ket{text-align:left}',
            '.pp-ket ol{list-style-type:decimal}',
            '.pp-ket ol[type="a"]{list-style-type:lower-alpha}',
            '.pp-ket ol,.pp-ket ul{margin:0 0 3px;padding-left:18px}',
            '.pp-ket ol ol{list-style-type:lower-alpha}',
            '.pp-ket p{margin:0 0 3px}',
            '.pp-ket table{margin:4px 0}',
        );
        if ($pdf) {
            // Dompdf: ukuran eksplisit (tanpa box-sizing / var() / calc / box-shadow). Halaman A4 = 794 x 1123 px.
            $a[] = '@page{size:A4 portrait;margin:0}';
            $a[] = 'body{margin:0;padding:0;font-family:"Times New Roman",Times,serif;color:#000}';
            $a[] = '.pp-page{width:718px;height:1048px;padding:34px 38px;margin:0;font-size:11pt;line-height:1.35;page-break-after:always}';
            $a[] = '.pp-page.pp-last{page-break-after:auto}';
            $a[] = '.pp-frame{border:6px double #000;padding:24px 34px;width:638px;height:992px;overflow:hidden}';
            $a[] = '#pv2 table{font-size:' . ((11 - (float) $v['d'])) . 'pt;line-height:' . $v['lh'] . '}';
            $a[] = '#pv2 table.pp-est{font-size:' . ((10 - (float) $v['d'])) . 'pt}';
            $a[] = '#pv2 td,#pv2 th{padding-top:' . $v['tp'] . 'px;padding-bottom:' . $v['tp'] . 'px}';
        } else {
            $a[] = '.pp-page{width:794px;height:1123px;overflow:hidden;background:#fff;margin:0 0 14px;padding:46px 52px;box-sizing:border-box;font-family:"Times New Roman",Times,serif;font-size:11pt;color:#000;line-height:1.35;box-shadow:0 10px 30px rgba(0,0,0,.35)}';
            $a[] = '.pp-page.framed{padding:34px 38px}';
            $a[] = '.pp-frame{border:2.5px solid #000;box-shadow:inset 0 0 0 4px #fff,inset 0 0 0 5.5px #000;padding:24px 34px;height:1043px;box-sizing:border-box;overflow:hidden}';
            $a[] = '.pp-lib,.pp-dark{-webkit-print-color-adjust:exact;print-color-adjust:exact}';
            $a[] = '#pv2 table{font-size:calc(11pt - var(--d,0pt));line-height:var(--lh,1.35)}';
            $a[] = '#pv2 table.pp-est{font-size:calc(10pt - var(--d,0pt))}';
            $a[] = '#pv2 td,#pv2 th{padding-top:var(--tp,3px);padding-bottom:var(--tp,3px)}';
            $a[] = '@media print{@page{size:A4;margin:0}.pp-page{margin:0;box-shadow:none;page-break-after:always}.pp-page:last-child{page-break-after:auto}}';
        }
        return implode("\n", $a);
    }

    /* ---------- keluaran ---------- */

    protected static function siapkan(array $S, array $opt)
    {
        $c = self::hitung($S);
        $info = self::kolomJadwal($S['jadwal']['mulai'], $S['jadwal']['selesai'], isset($opt['libur']) ? $opt['libur'] : array());
        $v = isset($opt['k']) ? self::varKerapatan($opt['k']) : self::kerapatan($S, $c); // 'k' hanya untuk pengujian
        return array($c, $info['kolom'], $v);
    }

    /**
     * Fragmen HTML untuk preview langsung di layar (dimasukkan ke #poLiveSheetContainer).
     * $opt: nomor (string, nomor PO tersimpan), libur (string[]).
     */
    public static function fragmen(array $S, array $opt = array())
    {
        list($c, $kolom, $v) = self::siapkan($S, $opt);
        $nomor = isset($opt['nomor']) ? (string) $opt['nomor'] : '';
        $pages = self::halIsi($S, $nomor) . self::halRab($S, $c, $v) . self::halJadwalLengkap($S, $kolom);
        return '<style>' . self::css(false, $v) . '</style><div class="po-doc-scope"><div id="poDocSheet" class="pp-docpages">' . $pages . '</div></div>';
    }

    /** Dokumen HTML lengkap untuk Dompdf. */
    public static function htmlPdf(array $S, array $opt = array())
    {
        list($c, $kolom, $v) = self::siapkan($S, $opt);
        $nomor = isset($opt['nomor']) ? (string) $opt['nomor'] : '';
        $v2 = self::varKerapatan(min(20, $v['k'] + 1)); // satu tingkat lebih padat sebagai pengaman: Dompdf mengukur sedikit beda dari browser
        $pages = self::halIsi($S, $nomor) . self::halRab($S, $c, $v2) . self::halJadwalLengkap($S, $kolom);
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Petunjuk Operasional</title><style>' . self::css(true, $v2) . '</style></head><body>'
            . '<div class="po-doc-scope"><div class="pp-docpages">' . $pages . '</div></div></body></html>';
    }
}
