<?php

/**
 * PoKalibrasi -- sumber data order untuk PO Kalibrasi (modal "Pilih Order Kalibrasi" + isi awal form).
 *
 * Sumber: tabel order kalibrasi `tb_arsip_kalibrasi` (kunci id_arsip_kalibrasi) dan tabel daftar alat per order
 * (kolom id_arsip_kalibrasi + id_penawaran_alat). Satu order = satu PO.
 *
 * Tabel order & alat ada di database sikalib2024 (const DB_KALIBRASI). Class ini tetap TIDAK menebak lokasi tabel dan kolom:
 *   - lokasi tabel dicari lewat information_schema (sikalib2024 diutamakan, lalu sil2020, lalu database aplikasi),
 *   - kolom yang dipakai dicek dulu; kolom yang tidak ada dilewati, bukan error,
 *   - tabel alat dideteksi otomatis (tabel yang punya id_arsip_kalibrasi + id_penawaran_alat).
 * Bila deteksi tabel alat salah, isi variabel F3 PO_KALIBRASI_ALAT = 'namaschema.nama_tabel' (mis. di config).
 *
 * Kompatibel PHP 7.2. Tidak mengubah skema database apa pun.
 */
class PoKalibrasi
{
    const LAYANAN = 'kalibrasi';
    const TABEL_ORDER = 'tb_arsip_kalibrasi';
    /** Database utama sistem kalibrasi (tempat tb_arsip_kalibrasi + tabel alat). Dicari lebih dulu sebelum sil2020 dan database aplikasi. */
    const DB_KALIBRASI = 'sikalib2024';

    /** Catatan non-fatal untuk modal (mis. tabel alat tidak ditemukan). */
    protected static $catatan = array();
    protected static $cache = array();

    public static function catatan()
    {
        return self::$catatan;
    }

    /** Nama schema database tempat $tabel berada ('' = tidak ada). sil2020 diutamakan. */
    public static function skema($db, $tabel)
    {
        $k = 'skema:' . $tabel;
        if (!array_key_exists($k, self::$cache)) {
            $r = $db->exec(
                "SELECT TABLE_SCHEMA AS s FROM information_schema.TABLES
                 WHERE TABLE_NAME = ? AND TABLE_SCHEMA IN (?, 'sil2020', DATABASE())
                 ORDER BY (TABLE_SCHEMA = ?) DESC, (TABLE_SCHEMA = 'sil2020') DESC LIMIT 1",
                array(1 => $tabel, 2 => self::DB_KALIBRASI, 3 => self::DB_KALIBRASI)
            );
            self::$cache[$k] = empty($r) ? '' : (string) $r[0]['s'];
        }
        return self::$cache[$k];
    }

    /** Daftar kolom (huruf kecil => true) sebuah tabel. */
    public static function kolom($db, $schema, $tabel)
    {
        $k = 'kolom:' . $schema . '.' . $tabel;
        if (!isset(self::$cache[$k])) {
            $out = array();
            $rows = $db->exec(
                "SELECT COLUMN_NAME AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                array(1 => $schema, 2 => $tabel)
            );
            foreach ($rows as $r) { $out[strtolower((string) $r['n'])] = true; }
            self::$cache[$k] = $out;
        }
        return self::$cache[$k];
    }

    /** Pesan kendala ('' = aman): tabel order harus ada. */
    public static function kendala($db)
    {
        try {
            if (self::skema($db, self::TABEL_ORDER) === '') {
                return 'Tabel ' . self::TABEL_ORDER . ' tidak ditemukan di database sil2020 maupun database aplikasi.';
            }
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] ' . $e->getMessage());
            return 'Gagal memeriksa tabel order kalibrasi. Pastikan user MySQL boleh membaca information_schema.';
        }
        return '';
    }

    /** [schema, tabel, kolom-tautan] tabel alat order kalibrasi, atau null bila tidak ditemukan. */
    public static function tabelAlat($db)
    {
        if (array_key_exists('alat', self::$cache)) { return self::$cache['alat']; }
        $hasil = null;
        $manual = '';
        try { $manual = trim((string) \Base::instance()->get('PO_KALIBRASI_ALAT')); } catch (\Throwable $e) { $manual = ''; }
        if ($manual !== '' && strpos($manual, '.') !== false) {
            list($s, $t) = explode('.', $manual, 2);
            $hasil = array($s, $t);
        } else {
            // schema yang sama dengan tabel order diutamakan
            $skOrder = self::skema($db, self::TABEL_ORDER);
            $r = $db->exec(
                "SELECT TABLE_SCHEMA AS s, TABLE_NAME AS n,
                        SUM(COLUMN_NAME = 'id_arsip_kalibrasi') AS a,
                        SUM(COLUMN_NAME = 'id_penawaran_alat') AS b,
                        SUM(COLUMN_NAME = 'lhu_alat_type_model') AS c
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA IN (?, 'sil2020', DATABASE()) AND TABLE_NAME <> ?
                   AND COLUMN_NAME IN ('id_arsip_kalibrasi', 'id_penawaran_alat', 'lhu_alat_type_model')
                 GROUP BY TABLE_SCHEMA, TABLE_NAME
                 HAVING a > 0 AND (b > 0 OR c > 0)
                 ORDER BY (b > 0) DESC, (c > 0) DESC, (TABLE_SCHEMA = ?) DESC, (TABLE_SCHEMA = 'sil2020') DESC, TABLE_NAME ASC
                 LIMIT 1",
                array(1 => $skOrder, 2 => self::TABEL_ORDER, 3 => $skOrder)
            );
            if (!empty($r)) { $hasil = array((string) $r[0]['s'], (string) $r[0]['n']); }
        }
        if ($hasil !== null) {
            $kol = self::kolom($db, $hasil[0], $hasil[1]);
            if (!isset($kol['id_arsip_kalibrasi'])) { $hasil = null; }
        }
        self::$cache['alat'] = $hasil;
        return $hasil;
    }

    protected static function q($ident)
    {
        return '`' . str_replace('`', '', (string) $ident) . '`';
    }

    /** Nilai kolom pertama yang ada & terisi dari daftar kandidat ('' bila tidak ada). */
    protected static function ambilKol(array $r, array $kandidat)
    {
        foreach ($kandidat as $k) {
            if (isset($r[$k]) && trim((string) $r[$k]) !== '') { return trim((string) $r[$k]); }
        }
        return '';
    }

    protected static function tanggal($v)
    {
        $v = trim((string) $v);
        if ($v === '' || strpos($v, '0000') === 0) { return ''; }
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : '';
    }

    protected static function angka($v)
    {
        if ($v === null || $v === '') { return 0.0; }
        if (is_numeric($v)) { return (float) $v; }
        $s = preg_replace('/[^0-9,.\-]/', '', (string) $v);
        if ($s === '' || $s === '-') { return 0.0; }
        if (strpos($s, ',') !== false) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
        elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) { $s = str_replace('.', '', $s); }
        return (float) $s;
    }

    /**
     * Daftar order (terbaru dulu). $id > 0 -> satu order saja. $q = nomor order / nama mitra / uraian.
     * $kecualiPo = id PO yang sedang diedit: order miliknya tetap muncul. Setiap baris sudah lengkap
     * (alat, nama teknisi) lewat petakan().
     */
    public static function daftar($db, $id = 0, $q = '', $batas = 100, $kecualiPo = 0)
    {
        self::$catatan = array();
        $sk = self::skema($db, self::TABEL_ORDER);
        if ($sk === '') { throw new \RuntimeException('Tabel ' . self::TABEL_ORDER . ' tidak ditemukan.'); }
        $kol = self::kolom($db, $sk, self::TABEL_ORDER);
        if (!isset($kol['id_arsip_kalibrasi'])) { throw new \RuntimeException('Kolom id_arsip_kalibrasi tidak ada di ' . self::TABEL_ORDER . '.'); }

        $join = isset($kol['id_customer']);
        $sql = 'SELECT k.*' . ($join ? ', c.nmcustomer, c.pt_cv, c.alamatcustomer' : '')
             . ' FROM ' . self::q($sk) . '.' . self::q(self::TABEL_ORDER) . ' k'
             . ($join ? ' LEFT JOIN tb_customer c ON c.id_customer = k.id_customer' : '')
             . ' WHERE 1 = 1';
        $args = array();
        $i = 1;
        // Tolerant: hanya buang yang jelas disembunyikan / sudah selesai (nilai persisnya tidak diasumsikan)
        if (isset($kol['showhide'])) { $sql .= " AND (k.showhide IS NULL OR LOWER(k.showhide) NOT IN ('hide', 'hidden', 'sembunyi'))"; }
        // order berstatus selesai TIDAK lagi disembunyikan: tabel order kalibrasi berisi banyak order lama dan semuanya perlu bisa dipilih

        if ($id > 0) {
            $sql .= ' AND k.id_arsip_kalibrasi = ?';
            $args[$i++] = (int) $id;
        } else {
            // satu order = satu PO: sembunyikan order yang sudah punya PO Kalibrasi (bila po_kegiatan sudah punya kolom penautnya)
            if (self::poPunyaKolomOrder($db)) {
                $sql .= ' AND NOT EXISTS (SELECT 1 FROM po_kegiatan pk WHERE pk.id_arsip_kalibrasi = k.id_arsip_kalibrasi AND pk.layanan = ? AND pk.id <> ?)';
                $args[$i++] = self::LAYANAN;
                $args[$i++] = (int) $kecualiPo;
            }
            $q = trim((string) $q);
            if ($q !== '') {
                $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $q) . '%';
                $cari = array();
                foreach (array('nomor_order_lengkap', 'uraian_order', 'keterangan_lokasi') as $kc) {
                    if (isset($kol[$kc])) { $cari[] = 'k.' . $kc . ' LIKE ?'; $args[$i++] = $like; }
                }
                if ($join) {
                    $cari[] = 'c.nmcustomer LIKE ?'; $args[$i++] = $like;
                    $cari[] = 'c.pt_cv LIKE ?'; $args[$i++] = $like;
                }
                if ($cari) { $sql .= ' AND (' . implode(' OR ', $cari) . ')'; }
            }
        }
        $sql .= ' ORDER BY k.id_arsip_kalibrasi DESC LIMIT ' . max(1, (int) $batas);
        $rows = $db->exec($sql, $args);
        if (!$rows) { return array(); }

        $ids = array();
        foreach ($rows as $r) { $ids[] = (int) $r['id_arsip_kalibrasi']; }
        $alat = self::alatUntuk($db, $ids);
        $idTek = array();
        foreach ($rows as $r) { foreach (self::idTeknisi($r) as $t) { $idTek[$t] = true; } }
        $nama = self::namaTeknisi($db, array_keys($idTek));

        $out = array();
        foreach ($rows as $r) {
            $out[] = self::petakan($r, isset($alat[(int) $r['id_arsip_kalibrasi']]) ? $alat[(int) $r['id_arsip_kalibrasi']] : array(), $nama);
        }
        return $out;
    }

    /** Satu order lengkap, atau null. */
    public static function ambil($db, $id)
    {
        if ((int) $id <= 0) { return null; }
        $r = self::daftar($db, (int) $id, '', 1);
        return $r ? $r[0] : null;
    }

    protected static function poPunyaKolomOrder($db)
    {
        if (!isset(self::$cache['po_kol'])) {
            $ada = false;
            try { $ada = (bool) $db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'id_arsip_kalibrasi'"); } catch (\Throwable $e) { $ada = false; }
            self::$cache['po_kol'] = $ada;
        }
        return self::$cache['po_kol'];
    }

    protected static function idTeknisi(array $r)
    {
        $out = array();
        for ($n = 1; $n <= 5; $n++) {
            $v = isset($r['id_teknisi_' . $n]) ? (int) $r['id_teknisi_' . $n] : 0;
            if ($v > 0 && !in_array($v, $out, true)) { $out[] = $v; }
        }
        return $out;
    }

    /** id_user => nama (tb_arsipuser). Gagal / tidak cocok -> larik kosong (modal hanya menampilkan jumlah teknisi). */
    protected static function namaTeknisi($db, array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) { return array(); }
        try {
            $rows = $db->exec(
                'SELECT id_user, nama_user FROM tb_arsipuser WHERE id_user IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                array_combine(range(1, count($ids)), $ids)
            );
        } catch (\Throwable $e) {
            return array();
        }
        $out = array();
        foreach ($rows as $r) { $out[(int) $r['id_user']] = trim((string) $r['nama_user']); }
        return $out;
    }

    /** id order => larik alat. Tabel alat tidak ketemu -> larik kosong + catatan. */
    protected static function alatUntuk($db, array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return array(); }
        $t = null;
        try { $t = self::tabelAlat($db); } catch (\Throwable $e) { error_log('[PO Kalibrasi] ' . $e->getMessage()); }
        if ($t === null) {
            self::$catatan[] = 'Tabel daftar alat tidak ditemukan, jadi alat per order belum tampil. Isi variabel PO_KALIBRASI_ALAT (schema.nama_tabel) bila nama tabelnya sudah diketahui.';
            return array();
        }
        $kol = self::kolom($db, $t[0], $t[1]);
        $urut = isset($kol['id_penawaran_alat']) ? 'a.id_penawaran_alat' : 'a.id_arsip_kalibrasi';
        try {
            $rows = $db->exec(
                'SELECT a.* FROM ' . self::q($t[0]) . '.' . self::q($t[1]) . ' a WHERE a.id_arsip_kalibrasi IN ('
                . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY a.id_arsip_kalibrasi, ' . $urut . ' ASC',
                array_combine(range(1, count($ids)), $ids)
            );
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] gagal baca alat: ' . $e->getMessage());
            self::$catatan[] = 'Daftar alat gagal dibaca dari tabel ' . $t[1] . '.';
            return array();
        }
        // Nama alat: kolom nama di baris alat bila ada; kalau tidak, dari tabel tarif lewat id_tarif_simponi
        // (kolom lhu_alat_* baru terisi saat LHU dibuat, jadi sering kosong)
        $kolNama = array('nama_alat', 'nama_tarif', 'nama_layanan', 'nama_peralatan', 'uraian_alat', 'nama_item', 'nama');
        $idTarif = array();
        foreach ($rows as $r) {
            if (self::ambilKol($r, $kolNama) === '' && isset($r['id_tarif_simponi'])) { $idTarif[] = (int) $r['id_tarif_simponi']; }
        }
        $nmTarif = array();
        if ($idTarif) {
            try { $nmTarif = self::namaTarif($db, $t[0], $t[1], $idTarif); } catch (\Throwable $e) { error_log('[PO Kalibrasi] tarif: ' . $e->getMessage()); }
        }

        $out = array();
        foreach ($rows as $r) {
            if (isset($r['showhide']) && strtolower((string) $r['showhide']) === 'hide') { continue; }
            $tid = isset($r['id_tarif_simponi']) ? (int) $r['id_tarif_simponi'] : 0;
            $nama = self::ambilKol($r, $kolNama);
            if ($nama === '' && isset($nmTarif[$tid])) { $nama = $nmTarif[$tid]; }
            // sementara (nama tarif belum ketemu): tampilkan ID tarifnya supaya baris alat tidak kosong
            if ($nama === '' && $tid > 0) { $nama = 'ID Tarif ' . $tid; }
            $out[(int) $r['id_arsip_kalibrasi']][] = array(
                'nama'      => $nama,
                'tipe'      => self::ambilKol($r, array('lhu_alat_type_model')),
                'merk'      => self::ambilKol($r, array('lhu_alat_merk_buatan')),
                'kapasitas' => self::ambilKol($r, array('lhu_alat_kapasitas')),
                'sn'        => self::ambilKol($r, array('lhu_alat_no_seri')),
                'resolusi'  => self::ambilKol($r, array('lhu_alat_resolusi')),
                'lokasi'    => self::ambilKol($r, array('lhu_lokasi_kalibrasi', 'lhu_lokasi_kalibrasi_2')),
            );
        }
        return $out;
    }

    /**
     * [schema, tabel, kolom nama, daftar kolom] tabel tarif (induk id_tarif_simponi), atau null.
     * Dicari di SEMUA database (bukan hanya sikalib2024): tabel yang menyimpan id_tarif_simponi sebagai kunci utama,
     * atau yang namanya mengandung "tarif". Bila salah tebak, isi variabel F3 PO_KALIBRASI_TARIF = 'schema.nama_tabel'.
     */
    protected static function tabelTarif($db, $schema, $tabelAlat)
    {
        $k = 'tarif:' . $schema . '.' . $tabelAlat;
        if (array_key_exists($k, self::$cache)) { return self::$cache[$k]; }
        $hasil = null;
        $sch = ''; $tbl = '';
        $manual = '';
        try { $manual = trim((string) \Base::instance()->get('PO_KALIBRASI_TARIF')); } catch (\Throwable $e) { $manual = ''; }
        if ($manual !== '' && strpos($manual, '.') !== false) {
            list($sch, $tbl) = explode('.', $manual, 2);
        } else {
            $r = $db->exec(
                "SELECT TABLE_SCHEMA AS s, TABLE_NAME AS n FROM information_schema.COLUMNS
                 WHERE COLUMN_NAME = 'id_tarif_simponi'
                   AND TABLE_SCHEMA NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
                   AND NOT (TABLE_SCHEMA = ? AND TABLE_NAME = ?)
                   AND (COLUMN_KEY = 'PRI' OR TABLE_NAME LIKE '%tarif%')
                 ORDER BY (COLUMN_KEY = 'PRI') DESC, (TABLE_NAME LIKE '%tarif%') DESC, (TABLE_SCHEMA = ?) DESC, CHAR_LENGTH(TABLE_NAME) ASC LIMIT 1",
                array(1 => $schema, 2 => $tabelAlat, 3 => $schema)
            );
            if (!empty($r)) { $sch = (string) $r[0]['s']; $tbl = (string) $r[0]['n']; }
        }
        if ($sch !== '' && $tbl !== '') {
            $kol = $db->exec(
                "SELECT COLUMN_NAME AS n, DATA_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
                array(1 => $sch, 2 => $tbl)
            );
            $ada = array();
            foreach ($kol as $c) { $ada[strtolower((string) $c['n'])] = strtolower((string) $c['t']); }
            $nama = '';
            foreach (array('nama_tarif', 'nama_layanan', 'nama_alat', 'nama_peralatan', 'nama_item', 'nama_jasa', 'nama', 'uraian_tarif', 'uraian', 'jenis_tarif', 'jenis_layanan', 'jenis_alat', 'layanan', 'deskripsi') as $c) {
                if (isset($ada[$c])) { $nama = $c; break; }
            }
            if ($nama === '') {
                foreach ($ada as $c => $tp) {
                    if (in_array($tp, array('varchar', 'text', 'tinytext', 'mediumtext'), true) && preg_match('/(nama|uraian|jenis|layanan|tarif|desk)/i', $c) && !preg_match('/^id_|kode/i', $c)) { $nama = $c; break; }
                }
            }
            if ($ada) { $hasil = array($sch, $tbl, $nama, array_keys($ada)); }
        }
        self::$cache[$k] = $hasil;
        return $hasil;
    }

    /** id_tarif_simponi => nama layanan/alat. */
    protected static function namaTarif($db, $schema, $tabelAlat, array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return array(); }
        $t = self::tabelTarif($db, $schema, $tabelAlat);
        if ($t === null) {
            return array();
        }
        if ($t[2] === '') {
            self::$catatan[] = 'Kolom nama di tabel ' . $t[0] . '.' . $t[1] . ' belum ketemu. Kolomnya: ' . implode(', ', $t[3]) . '.';
            return array();
        }
        $rows = $db->exec(
            'SELECT id_tarif_simponi AS i, ' . self::q($t[2]) . ' AS n FROM ' . self::q($t[0]) . '.' . self::q($t[1])
            . ' WHERE id_tarif_simponi IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            array_combine(range(1, count($ids)), $ids)
        );
        $out = array();
        foreach ($rows as $r) { $out[(int) $r['i']] = trim((string) $r['n']); }
        return $out;
    }

    /** Info teknis untuk memeriksa pemetaan skema (dipanggil lewat ?debug=1, Superadmin saja). */
    public static function diagnosa($db)
    {
        $d = array('schema_order' => self::skema($db, self::TABEL_ORDER));
        // rincian jumlah order (untuk memeriksa mengapa daftar order terasa sedikit)
        try {
            $sk = $d['schema_order'];
            $kolO = self::kolom($db, $sk, self::TABEL_ORDER);
            $tbl = self::q($sk) . '.' . self::q(self::TABEL_ORDER);
            $r = $db->exec('SELECT COUNT(*) AS n FROM ' . $tbl);
            $d['order_total'] = (int) $r[0]['n'];
            foreach (array('showhide', 'status_order_selesai') as $kc) {
                if (isset($kolO[$kc])) {
                    $d['sebaran_' . $kc] = $db->exec('SELECT ' . self::q($kc) . ' AS nilai, COUNT(*) AS n FROM ' . $tbl . ' GROUP BY ' . self::q($kc) . ' ORDER BY n DESC LIMIT 15');
                }
            }
        } catch (\Throwable $e) { $d['order_error'] = $e->getMessage(); }
        $t = self::tabelAlat($db);
        $d['tabel_alat'] = $t;
        if ($t !== null) {
            $d['kolom_alat'] = array_keys(self::kolom($db, $t[0], $t[1]));
            $d['contoh_alat'] = $db->exec('SELECT * FROM ' . self::q($t[0]) . '.' . self::q($t[1]) . ' ORDER BY id_penawaran_alat DESC LIMIT 3');
            $tr = self::tabelTarif($db, $t[0], $t[1]);
            $d['tabel_tarif'] = $tr;
            if ($tr !== null) {
                $d['contoh_tarif'] = $db->exec('SELECT * FROM ' . self::q($tr[0]) . '.' . self::q($tr[1]) . ' LIMIT 3');
            }
        }
        return $d;
    }

    /** Baris tb_arsip_kalibrasi (+ alat + nama teknisi) -> larik untuk JSON modal dan isian form. */
    public static function petakan(array $r, array $alat, array $namaTeknisi)
    {
        $f = function ($nama) use ($r) { return isset($r[$nama]) ? $r[$nama] : null; };
        $mitra = \Customer::formatNamaPerusahaan(isset($r['pt_cv']) ? $r['pt_cv'] : '', isset($r['nmcustomer']) ? $r['nmcustomer'] : '');
        $alamat = trim((string) (isset($r['alamatcustomer']) ? $r['alamatcustomer'] : ''));

        $ks = self::angka($f('struktural_biaya_kerjasama'));
        $ins = self::angka($f('struktural_biaya_kerjasama_insitu'));
        $tr = self::angka($f('struktural_biaya_kerjasama_transportasi'));
        $bl = self::angka($f('struktural_biaya_kerjasama_pembulatan'));
        $pt = self::angka($f('struktural_biaya_kerjasama_petugas'));
        $tot = self::angka($f('struktural_biaya_kerjasama_total'));
        $byk = self::angka($f('struktural_bayar_kerjasama'));
        $byp = self::angka($f('struktural_bayar_kerjasama_petugas'));
        if ($tot <= 0) { $tot = $ks + $ins + $tr + $bl + $pt; }

        // Lokasi: apa adanya dari keterangan_lokasi (order), cadangan lhu_lokasi_kalibrasi (alat); kosong bila tidak tercatat
        $tempat = self::ambilKol($r, array('keterangan_lokasi'));
        if ($tempat === '') {
            foreach ($alat as $a) { if (!empty($a['lokasi'])) { $tempat = $a['lokasi']; break; } }
        }

        $ids = self::idTeknisi($r);
        $nm = array();
        foreach ($ids as $t) { if (isset($namaTeknisi[$t]) && $namaTeknisi[$t] !== '') { $nm[] = $namaTeknisi[$t]; } }

        return array(
            'id'       => (int) $r['id_arsip_kalibrasi'],
            'id_customer' => isset($r['id_customer']) ? (int) $r['id_customer'] : 0,
            'nomor'    => self::ambilKol($r, array('nomor_order_lengkap', 'nomor_order', 'no_order')),
            'mitra'    => $mitra,
            'alamat'   => $alamat,
            'uraian'   => self::ambilKol($r, array('uraian_order')),
            'tempat'   => $tempat,
            'tgl_order'=> self::tanggal($f('tanggal_order')),
            'berangkat'=> self::tanggal($f('tanggal_kalibrasi_berangkat')),
            'pulang'   => self::tanggal($f('tanggal_kalibrasi_pulang')),
            'target'   => self::tanggal($f('tanggal_selesai_target')),
            'jml_teknisi' => count($ids),
            'teknisi'  => (count($nm) === count($ids)) ? $nm : array(),
            'biaya'    => array('kerjasama' => $ks, 'petugas' => $pt, 'insitu' => $ins, 'transportasi' => $tr, 'pembulatan' => $bl, 'total' => $tot, 'bayar_kerjasama' => $byk, 'bayar_petugas' => $byp),
            'alat'     => $alat,
        );
    }
}
