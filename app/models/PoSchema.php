<?php

/**
 * PoSchema -- helper bersama untuk tabel po_kegiatan & po_nomor_urut.
 *
 * Dipakai oleh dua modul sekaligus:
 *   - PO OPTI (PoKegiatanController dkk)                     -> layanan 'opti-lingkungan' / 'opti-selulosa'
 *   - PO Sertifikasi (petunjuk_operasional\PoSertifikasiController) -> layanan 'sertifikasi'
 *
 * Isinya:
 *   1. Self-healing skema (pola yang sama seperti ensureSchemaNomorPo() di PO OPTI):
 *        - po_kegiatan.layanan   VARCHAR(30)  -> asal PO (otomatis, bukan input user)
 *        - isi form PO Sertifikasi disimpan di KOLOM-KOLOM po_kegiatan seperti PO OPTI (dasar, tujuan,
 *          ruang_lingkup, alamat, tim_pelaksana, rab, jadwal, ...) + kolom baru untuk yang OPTI tidak punya
 *          (jenis, nama_perusahaan, no_penawaran, tanggal_penawaran, alamat_bagian, alamat_tampil).
 *          Kolom yang belum ada dibuat otomatis (NULL). Kolom data_po (JSON gabungan versi lama) dipindahkan
 *          ke kolom-kolom itu lalu dihapus.
 *        - po_kegiatan.tanggal_po, id_nomor_urut, tabel po_nomor_urut (sama persis dengan PO OPTI)
 *        - UNIQUE KEY lama di order_id (kalau ada) diganti jadi UNIQUE (order_id, layanan),
 *          karena po_kegiatan.order_id sekarang bisa berisi id order OPTI (order_layanan.id)
 *          ATAU id_arsip_sertifikasi (database SISERTIFIKASI) -- dua ruang angka yang bisa bentrok.
 *   2. Generator Nomor PO (nomorBaru) -- algoritma & format SAMA PERSIS dengan
 *      PoKegiatanController::generateNomorPoBaru(): "N/PO/BBSPJIS/ROMAWI/TAHUN", disimpan di
 *      po_nomor_urut (satu baris per nomor yang diterbitkan, urutan reset tiap bulan+tahun).
 *   3. Filter SQL pembeda PO OPTI vs PO Sertifikasi (bukanSertifikasi) supaya query-query OPTI
 *      yang menggabungkan po_kegiatan.order_id = order_layanan.id tidak "nyasar" membaca PO Sertifikasi.
 *
 * Kompatibel PHP 7.2.
 */
class PoSchema
{
    const SERTIFIKASI = 'sertifikasi';

    /** true bila kolom po_kegiatan.layanan sudah ada (hasil pastikan()). */
    protected static $siap = false;
    /** Pesan kendala skema (mis. foreign key di order_id) -- kosong bila tidak ada. */
    protected static $kendala = null;
    protected static $sudahJalan = false;
    /** Kolom po_kegiatan yang dibutuhkan PO Sertifikasi tapi belum berhasil dibuat. */
    protected static $kolomKurang = array();

    /** Kolom yang dipakai PO Sertifikasi: nama => definisi (dibuat otomatis bila belum ada, selalu NULL-able). */
    protected static $kolomSertifikasi = array(
        // sudah ada di PO OPTI (dipakai bersama)
        'judul_kegiatan' => 'VARCHAR(255) NULL',
        'dasar'          => 'TEXT NULL',
        'dasar_struktur' => 'TEXT NULL',
        'tujuan'         => 'TEXT NULL',
        'ruang_lingkup'  => 'LONGTEXT NULL',
        'alamat'         => 'TEXT NULL',
        'tim_pelaksana'  => 'LONGTEXT NULL',
        'rab'            => 'LONGTEXT NULL',
        'jadwal'         => 'LONGTEXT NULL',
        // khusus sertifikasi (OPTI tidak punya)
        'jenis'              => 'VARCHAR(255) NULL',
        'nama_perusahaan'    => 'VARCHAR(255) NULL',
        'no_penawaran'       => 'VARCHAR(150) NULL',
        'tanggal_penawaran'  => 'DATE NULL',
        'alamat_bagian'      => 'TEXT NULL',
        'alamat_tampil'      => 'TINYINT(1) NULL',
    );
    protected static $dbRef = null;

    public static function siap()
    {
        return self::$siap;
    }

    /** Alasan PO Sertifikasi belum bisa disimpan (kosong = aman). */
    public static function kendalaSertifikasi()
    {
        if (!self::$siap) {
            return 'Kolom po_kegiatan.layanan belum berhasil dibuat otomatis. Cek hak ALTER TABLE user MySQL, atau jalankan manual: ALTER TABLE po_kegiatan ADD COLUMN layanan VARCHAR(30) NULL AFTER order_id;';
        }
        if (!empty(self::$kolomKurang)) {
            $sql = array();
            foreach (self::$kolomKurang as $k) {
                $sql[] = 'ADD COLUMN `' . $k . '` ' . self::$kolomSertifikasi[$k];
            }
            return 'Kolom po_kegiatan berikut belum berhasil dibuat otomatis: ' . implode(', ', self::$kolomKurang)
                 . '. Cek hak ALTER TABLE user MySQL, atau jalankan manual: ALTER TABLE po_kegiatan ' . implode(', ', $sql) . ';';
        }
        // dicek malas (hanya saat PO Sertifikasi disimpan) -- query information_schema tidak perlu jalan di tiap request
        if (self::$kendala === null) {
            self::$kendala = self::$dbRef ? self::cekKendala(self::$dbRef) : '';
        }
        return self::$kendala;
    }

    /**
     * Pastikan skema siap. Aman dipanggil di setiap request: kalau semuanya sudah ada,
     * biayanya cuma 1 query ringan (SHOW COLUMNS FROM po_kegiatan).
     */
    public static function pastikan($db)
    {
        if (self::$sudahJalan) {
            return self::$siap;
        }
        self::$sudahJalan = true;
        self::$dbRef = $db;

        try {
            $ada = self::namaKolom($db);
            if (in_array('layanan', $ada, true) && !self::perluBenahi($ada)) {
                self::$siap = true; // jalur cepat: skema sudah siap
                return true;
            }

            self::bangunSkema($db);
            $ada = self::namaKolom($db);
            self::$siap = in_array('layanan', $ada, true);
            if (self::$siap) {
                self::backfillLayananOpti($db);
                self::migrasiDataPo($db);
                $ada = self::namaKolom($db);
            }
            self::$kolomKurang = array();
            foreach (array_keys(self::$kolomSertifikasi) as $k) {
                if (!in_array($k, $ada, true)) {
                    self::$kolomKurang[] = $k;
                }
            }
        } catch (\Throwable $e) {
            // termasuk kasus tabel po_kegiatan belum ada (SHOW COLUMNS error) -- bukan urusan helper ini
            error_log('[PoSchema] gagal memastikan skema: ' . $e->getMessage());
            self::$siap = false;
        }
        return self::$siap;
    }

    /** Daftar nama kolom po_kegiatan. */
    protected static function namaKolom($db)
    {
        $out = array();
        foreach ($db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) {
            $out[] = $c['Field'];
        }
        return $out;
    }

    /** true bila masih ada kolom sertifikasi yang belum ada, atau kolom data_po lama masih ada. */
    protected static function perluBenahi(array $ada)
    {
        if (in_array('data_po', $ada, true)) {
            return true;
        }
        foreach (array_keys(self::$kolomSertifikasi) as $k) {
            if (!in_array($k, $ada, true)) {
                return true;
            }
        }
        return false;
    }

    /** Buat/ubah semua yang dibutuhkan. Tiap langkah dibungkus sendiri supaya satu gagal tidak menghentikan yang lain. */
    protected static function bangunSkema($db)
    {
        if (!in_array('layanan', self::namaKolom($db), true)) {
            $langkah = array(
                "ALTER TABLE po_kegiatan ADD COLUMN layanan VARCHAR(30) NULL AFTER order_id",
                "ALTER TABLE po_kegiatan ADD INDEX idx_po_layanan (layanan)",
            );
            foreach ($langkah as $sql) {
                try { $db->exec($sql); } catch (\Throwable $e) { error_log('[PoSchema] ' . $e->getMessage()); }
            }
        }

        // kolom isi form: yang belum ada dibuat (NULL-able), yang sudah ada (milik PO OPTI) tidak disentuh
        $ada = self::namaKolom($db);
        foreach (self::$kolomSertifikasi as $nama => $def) {
            if (!in_array($nama, $ada, true)) {
                try { $db->exec('ALTER TABLE po_kegiatan ADD COLUMN `' . $nama . '` ' . $def); } catch (\Throwable $e) { error_log('[PoSchema] ' . $e->getMessage()); }
            }
        }

        // Sama dengan PoKegiatanController::ensureSchemaNomorPo()
        if (empty($db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'tanggal_po'"))) {
            try { $db->exec("ALTER TABLE po_kegiatan ADD COLUMN tanggal_po DATE NULL AFTER nomor_po"); } catch (\Throwable $e) { error_log('[PoSchema] ' . $e->getMessage()); }
        }
        if (empty($db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'id_nomor_urut'"))) {
            try { $db->exec("ALTER TABLE po_kegiatan ADD COLUMN id_nomor_urut INT UNSIGNED NULL AFTER nomor_po"); } catch (\Throwable $e) { error_log('[PoSchema] ' . $e->getMessage()); }
        }
        self::pastikanTabelNomor($db);

        // UNIQUE tunggal di order_id (kalau ada) -> UNIQUE (order_id, layanan)
        try {
            $idx = $db->exec("SHOW INDEX FROM po_kegiatan");
            $perKey = array();
            foreach ($idx as $r) {
                if ((int) $r['Non_unique'] === 0 && $r['Key_name'] !== 'PRIMARY') {
                    $perKey[$r['Key_name']][(int) $r['Seq_in_index']] = $r['Column_name'];
                }
            }
            foreach ($perKey as $nama => $kolom) {
                if (count($kolom) === 1 && reset($kolom) === 'order_id') {
                    try {
                        $db->exec("ALTER TABLE po_kegiatan DROP INDEX `" . str_replace('`', '', $nama) . "`, ADD UNIQUE KEY uniq_po_order_layanan (order_id, layanan)");
                    } catch (\Throwable $e) {
                        error_log('[PoSchema] gagal mengganti UNIQUE order_id: ' . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[PoSchema] ' . $e->getMessage());
        }
    }

    /** Tabel master nomor urut -- definisi SAMA dengan PO OPTI. */
    public static function pastikanTabelNomor($db)
    {
        try {
            $tabelAda = $db->exec("SHOW TABLES LIKE 'po_nomor_urut'");
            if (!empty($tabelAda)) {
                $colsDetail = $db->exec("SHOW COLUMNS FROM po_nomor_urut LIKE 'detail_nomor'");
                if (empty($colsDetail)) {
                    // skema lama (1 baris counter per bulan) -> sama seperti migrasi di PO OPTI
                    $db->exec("DROP TABLE po_nomor_urut");
                }
            }
            $db->exec(
                "CREATE TABLE IF NOT EXISTS po_nomor_urut (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nomor_urut INT UNSIGNED NOT NULL,
                    bulan TINYINT UNSIGNED NOT NULL,
                    tahun SMALLINT UNSIGNED NOT NULL,
                    detail_nomor VARCHAR(60) NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uniq_bulan_tahun_urut (bulan, tahun, nomor_urut),
                    UNIQUE KEY uniq_detail_nomor (detail_nomor)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\Throwable $e) {
            error_log('[PoSchema] ' . $e->getMessage());
        }
    }

    /** PO OPTI yang sudah ada sebelum kolom layanan dibuat: isi dari order_layanan.jenis_layanan_opti. */
    protected static function backfillLayananOpti($db)
    {
        try {
            $db->exec(
                "UPDATE po_kegiatan p
                 JOIN order_layanan o ON o.id = p.order_id
                 SET p.layanan = CONCAT('opti-', o.jenis_layanan_opti)
                 WHERE p.layanan IS NULL AND o.jenis_layanan_opti IN ('lingkungan', 'selulosa')"
            );
        } catch (\Throwable $e) {
            error_log('[PoSchema] backfill layanan gagal: ' . $e->getMessage());
        }
    }

    /**
     * Foreign key di po_kegiatan.order_id (kalau ada) pasti menolak id_arsip_sertifikasi yang
     * tidak ada di order_layanan. FK tidak dihapus otomatis (itu perubahan integritas data) --
     * pesan + SQL-nya ditampilkan supaya bisa dijalankan sadar.
     */
    protected static function cekKendala($db)
    {
        try {
            $fk = $db->exec(
                "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'po_kegiatan'
                   AND COLUMN_NAME = 'order_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if (!empty($fk)) {
                $nama = str_replace('`', '', (string) $fk[0]['CONSTRAINT_NAME']);
                return "po_kegiatan.order_id punya foreign key ke tabel {$fk[0]['REFERENCED_TABLE_NAME']} sehingga tidak bisa menyimpan id_arsip_sertifikasi. "
                     . "Hapus dulu foreign key-nya dengan SQL: ALTER TABLE po_kegiatan DROP FOREIGN KEY `{$nama}`;";
            }
        } catch (\Throwable $e) {
            // information_schema tidak bisa dibaca -> biarkan, error asli DB akan muncul saat simpan
        }
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Pembeda PO OPTI vs Sertifikasi                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Potongan SQL " AND (p.layanan IS NULL OR p.layanan <> 'sertifikasi')".
     * Kosong bila kolom layanan belum ada (supaya query OPTI tidak pernah error).
     */
    public static function bukanSertifikasi($alias = 'p')
    {
        if (!self::$siap) {
            return '';
        }
        $kol = ($alias === '' || $alias === null) ? 'layanan' : $alias . '.layanan';
        return " AND ({$kol} IS NULL OR {$kol} <> '" . self::SERTIFIKASI . "')";
    }

    /** 'lingkungan' / 'selulosa' -> 'opti-lingkungan' / 'opti-selulosa' (selain itu null). */
    public static function layananOpti($jenisLayananOpti)
    {
        $j = strtolower(trim((string) $jenisLayananOpti));
        return in_array($j, array('lingkungan', 'selulosa'), true) ? 'opti-' . $j : null;
    }

    /** Dipanggil setelah PO OPTI baru tersimpan: isi kolom layanan otomatis dari order-nya. */
    public static function tandaiLayananOpti($db, $poId, $orderId)
    {
        if (!self::$siap || (int) $poId <= 0) {
            return;
        }
        try {
            $r = $db->exec('SELECT jenis_layanan_opti FROM order_layanan WHERE id = ?', array(1 => (int) $orderId));
            $layanan = self::layananOpti(isset($r[0]['jenis_layanan_opti']) ? $r[0]['jenis_layanan_opti'] : '');
            if ($layanan !== null) {
                $db->exec('UPDATE po_kegiatan SET layanan = ? WHERE id = ? AND layanan IS NULL', array(1 => $layanan, 2 => (int) $poId));
            }
        } catch (\Throwable $e) {
            error_log('[PoSchema] gagal menandai layanan OPTI: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Nomor PO -- sama persis dengan PoKegiatanController                */
    /* ------------------------------------------------------------------ */

    public static function angkaRomawi($bulan)
    {
        $peta = array(1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
                      7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII');
        return isset($peta[(int) $bulan]) ? $peta[(int) $bulan] : 'I';
    }

    /** Fallback: nomor urut tertinggi dari nomor_po lama (free-text) bulan+tahun itu. */
    protected static function urutanLegacy($db, $bulan, $tahun)
    {
        $max = 0;
        try {
            $rows = $db->exec(
                "SELECT nomor_po FROM po_kegiatan WHERE nomor_po LIKE ?",
                array(1 => '%/' . self::angkaRomawi($bulan) . '/' . $tahun)
            );
            foreach ($rows as $r) {
                if (preg_match('/^\s*(\d+)\s*\//', (string) $r['nomor_po'], $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
        } catch (\Throwable $e) {
        }
        return $max;
    }

    /**
     * Terbitkan Nomor PO baru: "N/PO/BBSPJIS/ROMAWI/TAHUN" (N = MAX(nomor_urut) bulan+tahun itu + 1).
     * Satu baris po_nomor_urut per nomor; UNIQUE KEY menjaga tidak ada nomor ganda -> kalau bentrok
     * (dua proses bersamaan) otomatis dicoba lagi dari angka berikutnya, maksimal 5x.
     *
     * @return array{id:int, detail_nomor:string, nomor_urut:int}
     */
    public static function nomorBaru($db, $tanggalPo, $pastikanTabel = true)
    {
        // $pastikanTabel=false dipakai kalau pemanggil sudah memanggil pastikanTabelNomor() SEBELUM
        // membuka transaksi (DDL seperti CREATE TABLE meng-commit transaksi yang sedang berjalan).
        if ($pastikanTabel) {
            self::pastikanTabelNomor($db);
        }

        $ts    = strtotime((string) $tanggalPo) ?: time();
        $bulan = (int) date('n', $ts);
        $tahun = (int) date('Y', $ts);

        for ($percobaan = 0; $percobaan < 5; $percobaan++) {
            $row = $db->exec(
                'SELECT COALESCE(MAX(nomor_urut), 0) AS maxu FROM po_nomor_urut WHERE bulan = ? AND tahun = ?',
                array(1 => $bulan, 2 => $tahun)
            );
            $maxTercatat = (int) (isset($row[0]['maxu']) ? $row[0]['maxu'] : 0);
            $maxLegacy   = $maxTercatat > 0 ? 0 : self::urutanLegacy($db, $bulan, $tahun);
            $urutanBaru  = max($maxTercatat, $maxLegacy) + 1;
            $detailNomor = $urutanBaru . '/PO/BBSPJIS/' . self::angkaRomawi($bulan) . '/' . $tahun;

            try {
                $db->exec(
                    'INSERT INTO po_nomor_urut (nomor_urut, bulan, tahun, detail_nomor, updated_at) VALUES (?, ?, ?, ?, NOW())',
                    array(1 => $urutanBaru, 2 => $bulan, 3 => $tahun, 4 => $detailNomor)
                );
                $idRow = $db->exec('SELECT LAST_INSERT_ID() AS id');
                return array(
                    'id'           => (int) (isset($idRow[0]['id']) ? $idRow[0]['id'] : 0),
                    'detail_nomor' => $detailNomor,
                    'nomor_urut'   => $urutanBaru,
                );
            } catch (\Throwable $e) {
                continue; // bentrok UNIQUE -> coba nomor berikutnya
            }
        }
        throw new \Exception('Gagal membuat Nomor PO baru, silakan coba simpan ulang.');
    }

    /* ------------------------------------------------------------------ */
    /* INSERT / UPDATE po_kegiatan tanpa Mapper                           */
    /* (skema asli po_kegiatan tidak diketahui pasti -> hanya kolom yang  */
    /* benar-benar ada yang disentuh; kolom NOT NULL tanpa default diisi  */
    /* nilai netral)                                                      */
    /* ------------------------------------------------------------------ */

    protected static function kolomTabel($db)
    {
        $out = array();
        foreach ($db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) {
            $out[$c['Field']] = $c;
        }
        return $out;
    }

    protected static function nilaiNetral($type)
    {
        $t = strtolower((string) $type);
        if (strpos($t, 'enum(') === 0) {
            if (preg_match("/^enum\('((?:[^']|'')*)'/", $t, $m)) {
                return str_replace("''", "'", $m[1]);
            }
            return '';
        }
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|bit|year)/', $t)) {
            return 0;
        }
        if (strpos($t, 'datetime') === 0 || strpos($t, 'timestamp') === 0) {
            return date('Y-m-d H:i:s');
        }
        if (strpos($t, 'date') === 0) {
            return date('Y-m-d');
        }
        if (strpos($t, 'time') === 0) {
            return '00:00:00';
        }
        if (strpos($t, 'json') === 0) {
            return '[]';
        }
        return '';
    }

    /** NULL ke kolom NOT NULL (tanpa NULL) diganti nilai netral supaya tidak error; kolom NULL-able tetap NULL. */
    protected static function sesuaikanNull($kolom, $v)
    {
        if ($v === null && $kolom['Null'] === 'NO') {
            return self::nilaiNetral($kolom['Type']);
        }
        return $v;
    }

    /* ------------------------------------------------------------------ */
    /* Isi form PO Sertifikasi  <->  kolom po_kegiatan                    */
    /* ------------------------------------------------------------------ */

    protected static function teksOrNull($v, $maks = 0)
    {
        if (is_array($v) || $v === null) {
            return null;
        }
        $t = trim((string) $v);
        if ($t === '') {
            return null;
        }
        if ($maks > 0) {
            $t = function_exists('mb_substr') ? mb_substr($t, 0, $maks) : substr($t, 0, $maks);
        }
        return $t;
    }

    protected static function tanggalOrNull($v)
    {
        $t = trim((string) $v);
        $dt = \DateTime::createFromFormat('Y-m-d', $t);
        return ($dt && $dt->format('Y-m-d') === $t) ? $t : null;
    }

    protected static function jsonKolom($v)
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    protected static $romawi = array('I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII');
    protected static $namaBulan = array('Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');

    /** '2026-03-26' -> '26 Maret 2026' ('' bila tidak valid). */
    public static function tanggalIndo($ymd)
    {
        $t = self::tanggalOrNull((string) $ymd);
        if ($t === null) {
            return '';
        }
        return (int) substr($t, 8, 2) . ' ' . self::$namaBulan[(int) substr($t, 5, 2) - 1] . ' ' . substr($t, 0, 4);
    }

    /**
     * Bulan romawi & tahun surat penawaran: selalu dari tanggal surat.
     * @return array{romawi:string, tahun:string}
     */
    public static function romawiTahunPenawaran($tanggal, $romawiManual, $tahunManual)
    {
        // romawi & tahun SELALU mengikuti tanggal surat (tidak bisa diubah manual); parameter manual dipertahankan demi kompatibilitas
        $tgl = self::tanggalOrNull((string) $tanggal);
        $r = '';
        $y = '';
        if ($r === '' && $tgl !== null) { $r = self::$romawi[(int) substr($tgl, 5, 2) - 1]; }
        if ($y === '' && $tgl !== null) { $y = substr($tgl, 0, 4); }
        return array('romawi' => $r, 'tahun' => $y);
    }

    /** 'B/622/BBSPJIS/MS/III/2026' (bagian yang belum ada jadi '...'). */
    public static function nomorPenawaran(array $dp)
    {
        $rt = self::romawiTahunPenawaran(
            isset($dp['tanggal']) ? $dp['tanggal'] : '',
            isset($dp['romawi']) ? $dp['romawi'] : '',
            isset($dp['tahun']) ? $dp['tahun'] : ''
        );
        $n = trim((string) (isset($dp['nomor']) ? $dp['nomor'] : ''));
        return 'B/' . ($n === '' ? '...' : $n) . '/BBSPJIS/MS/' . ($rt['romawi'] === '' ? '...' : $rt['romawi']) . '/' . ($rt['tahun'] === '' ? '....' : $rt['tahun']);
    }

    /** Teks polos satu poin (tag dibuang). */
    protected static function teksPolos($html)
    {
        $t = preg_replace('/<[^>]*>/', ' ', (string) $html);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $t)));
    }

    /** Huruf poin: 0 => a, 1 => b, ... */
    public static function hurufPoin($i)
    {
        $out = '';
        $i = (int) $i;
        do { $out = chr(97 + ($i % 26)) . $out; $i = intdiv($i, 26) - 1; } while ($i >= 0);
        return $out;
    }

    /** Daftar poin Dasar (html) dari isi form. */
    protected static function poinDasar(array $d)
    {
        $out = array();
        foreach ((isset($d['dasarPoin']) && is_array($d['dasarPoin'])) ? $d['dasarPoin'] : array() as $p) {
            $out[] = is_array($p) ? (string) (isset($p['teks']) ? $p['teks'] : '') : '';
        }
        return $out;
    }

    /**
     * Teks polos seluruh Dasar (a. surat penawaran, b... poin, terakhir pembayaran) -- sama seperti kolom `dasar` di PO OPTI.
     * $bayar = kalimat pembayaran (dari data order), null = tidak ditulis.
     */
    public static function komposeDasar(array $d, $bayar = null)
    {
        $dp = (isset($d['dasarPenawaran']) && is_array($d['dasarPenawaran'])) ? $d['dasarPenawaran'] : array();
        $tgl = self::tanggalIndo(isset($dp['tanggal']) ? $dp['tanggal'] : '');
        $baris = array('Surat penawaran Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa '
            . self::nomorPenawaran($dp) . ', Tanggal ' . ($tgl === '' ? '...' : $tgl));
        foreach (self::poinDasar($d) as $h) {
            $baris[] = self::teksPolos($h);
        }
        if ($bayar !== null && $bayar !== '') {
            $baris[] = $bayar;
        }
        foreach ($baris as $i => $b) {
            $baris[$i] = self::hurufPoin($i) . '. ' . $b;
        }
        return implode("\n", $baris);
    }

    /**
     * Isi form (array hasil JSON form) -> nilai kolom po_kegiatan. Hanya kolom ISI FORM;
     * nomor_po, status, order_id, dst. diurus pemanggil. Isian kosong -> NULL.
     * Dasar disimpan seperti PO OPTI: `dasar` = teks gabungan (a., b., ...), `dasar_struktur` = bagian-bagiannya (JSON).
     * Kolom khusus OPTI (alat_bahan, metoda, bahan_kimia, kebutuhan_tambahan, jadwal_mulai/selesai) sengaja
     * tidak disentuh -> tetap NULL untuk PO Sertifikasi.
     * @param string|null $bayar kalimat pembayaran otomatis (dari order) untuk ikut ditulis di kolom `dasar`
     */
    public static function kolomDariForm(array $d, $bayar = null)
    {
        $g = function ($k) use ($d) { return isset($d[$k]) ? $d[$k] : null; };

        $dp = (isset($d['dasarPenawaran']) && is_array($d['dasarPenawaran'])) ? $d['dasarPenawaran'] : array();
        $rt = self::romawiTahunPenawaran(
            isset($dp['tanggal']) ? $dp['tanggal'] : '',
            isset($dp['romawi']) ? $dp['romawi'] : '',
            isset($dp['tahun']) ? $dp['tahun'] : ''
        );
        $tglPenawaran = self::tanggalOrNull(isset($dp['tanggal']) ? (string) $dp['tanggal'] : '');
        $nomorUrut = self::teksOrNull(isset($dp['nomor']) ? $dp['nomor'] : '', 60);
        $dasarStruktur = array(
            'nomor_urut'    => (string) $nomorUrut,
            'bulan_romawi'  => $rt['romawi'],
            'tahun'         => $rt['tahun'],
            'tanggal'       => (string) $tglPenawaran,
            'tentang'       => '',
            'poin'          => self::poinDasar($d),
        );

        $tim = array();
        foreach ((isset($d['tim']) && is_array($d['tim'])) ? $d['tim'] : array() as $t) {
            $t = is_array($t) ? $t : array();
            $tim[] = array(
                'nama'         => trim((string) (isset($t['nama']) ? $t['nama'] : '')),
                'tugas'        => trim((string) (isset($t['tugas']) ? $t['tugas'] : '')),
                'uraian_tugas' => trim((string) (isset($t['uraian']) ? $t['uraian'] : '')),
            );
        }

        $rab = (isset($d['rab']) && is_array($d['rab'])) ? $d['rab'] : array();
        $terima = array();
        foreach ((isset($rab['terima']) && is_array($rab['terima'])) ? $rab['terima'] : array() as $t) {
            $t = is_array($t) ? $t : array();
            $terima[] = array(
                'nama'    => trim((string) (isset($t['nama']) ? $t['nama'] : '')),
                'nominal' => (float) (isset($t['nilai']) && is_numeric($t['nilai']) ? $t['nilai'] : 0),
            );
        }
        $kategori = (isset($rab['induk']) && is_array($rab['induk'])) ? array_values($rab['induk']) : array();

        $jd = (isset($d['jadwal']) && is_array($d['jadwal'])) ? $d['jadwal'] : array();

        return array(
            'jenis'             => self::teksOrNull($g('jenis'), 255),
            'nama_perusahaan'   => self::teksOrNull($g('perusahaan'), 255),
            'no_penawaran'      => $nomorUrut === null ? null : self::nomorPenawaran($dp),
            'tanggal_penawaran' => $tglPenawaran,
            'dasar'             => self::teksOrNull(self::komposeDasar($d, $bayar)),
            'dasar_struktur'    => self::jsonKolom($dasarStruktur),
            'tujuan'            => self::teksOrNull($g('tujuan')),
            'ruang_lingkup'     => self::jsonKolom(array(
                'deskripsi' => (string) $g('ruang'),
                'tabel'     => (isset($d['ruangTbl']) && is_array($d['ruangTbl'])) ? $d['ruangTbl'] : null,
            )),
            'alamat_bagian'     => self::teksOrNull($g('alamatSec')),
            'alamat_tampil'     => !empty($d['showAlamat']) ? 1 : 0,
            'tim_pelaksana'     => self::jsonKolom($tim),
            'rab'               => self::jsonKolom(array(
                'penerimaan'  => array('items' => $terima),
                'pengeluaran' => array('kategori' => $kategori),
            )),
            'jadwal'            => self::jsonKolom(array(
                'mode'  => isset($jd['mode']) ? (string) $jd['mode'] : 'teks',
                'teks'  => (string) $g('jadwalTeks'),
                'bulan' => (isset($jd['bulan']) && is_array($jd['bulan'])) ? $jd['bulan'] : array(),
                'rows'  => (isset($jd['rows']) && is_array($jd['rows'])) ? $jd['rows'] : array(),
            )),
        );
    }

    /**
     * Baris po_kegiatan -> struktur isi form (dibaca halaman form saat edit).
     * Kunci yang datanya tidak ada dihilangkan: halaman form mengisinya dengan nilai awal.
     */
    public static function formDariBaris(array $r)
    {
        $S = array();
        $tgl = isset($r['tanggal_po']) ? (string) $r['tanggal_po'] : '';
        if ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) {
            $S['tanggal'] = date('Y-m-d', strtotime($tgl));
        }
        $S['judul']       = (string) (isset($r['judul_kegiatan']) ? $r['judul_kegiatan'] : '');
        $S['jenis']       = (string) (isset($r['jenis']) ? $r['jenis'] : '');
        $S['perusahaan']  = (string) (isset($r['nama_perusahaan']) ? $r['nama_perusahaan'] : '');
        $S['alamat']      = (string) (isset($r['alamat']) ? $r['alamat'] : '');
        $dsRaw = isset($r['dasar_struktur']) ? json_decode((string) $r['dasar_struktur'], true) : null;
        if (is_array($dsRaw)) {
            $S['dasarPenawaran'] = array(
                'nomor'   => (string) (isset($dsRaw['nomor_urut']) ? $dsRaw['nomor_urut'] : ''),
                'tanggal' => (string) (isset($dsRaw['tanggal']) ? $dsRaw['tanggal'] : ''),
            );
            $S['dasarPoin'] = array();
            foreach ((isset($dsRaw['poin']) && is_array($dsRaw['poin'])) ? $dsRaw['poin'] : array() as $p) {
                $S['dasarPoin'][] = array('teks' => (string) $p);
            }
        }
        $S['tujuan']      = (string) (isset($r['tujuan']) ? $r['tujuan'] : '');
        $S['alamatSec']   = (string) (isset($r['alamat_bagian']) ? $r['alamat_bagian'] : '');
        if (isset($r['alamat_tampil']) && $r['alamat_tampil'] !== null) {
            $S['showAlamat'] = (int) $r['alamat_tampil'];
        }

        $rl = isset($r['ruang_lingkup']) ? json_decode((string) $r['ruang_lingkup'], true) : null;
        if (is_array($rl)) {
            $S['ruang'] = (string) (isset($rl['deskripsi']) ? $rl['deskripsi'] : '');
            if (isset($rl['tabel']) && is_array($rl['tabel'])) {
                $S['ruangTbl'] = $rl['tabel'];
            }
        }
        $tim = isset($r['tim_pelaksana']) ? json_decode((string) $r['tim_pelaksana'], true) : null;
        if (is_array($tim)) {
            $S['tim'] = array();
            foreach ($tim as $t) {
                $t = is_array($t) ? $t : array();
                $S['tim'][] = array(
                    'nama'   => (string) (isset($t['nama']) ? $t['nama'] : ''),
                    'tugas'  => (string) (isset($t['tugas']) ? $t['tugas'] : ''),
                    'uraian' => (string) (isset($t['uraian_tugas']) ? $t['uraian_tugas'] : ''),
                );
            }
        }
        $rab = isset($r['rab']) ? json_decode((string) $r['rab'], true) : null;
        if (is_array($rab)) {
            $S['rab'] = array('terima' => array(), 'induk' => array());
            $items = isset($rab['penerimaan']['items']) && is_array($rab['penerimaan']['items']) ? $rab['penerimaan']['items'] : array();
            foreach ($items as $t) {
                $t = is_array($t) ? $t : array();
                $S['rab']['terima'][] = array(
                    'nama'  => (string) (isset($t['nama']) ? $t['nama'] : ''),
                    'nilai' => isset($t['nominal']) ? $t['nominal'] : 0,
                );
            }
            if (isset($rab['pengeluaran']['kategori']) && is_array($rab['pengeluaran']['kategori'])) {
                $S['rab']['induk'] = array_values($rab['pengeluaran']['kategori']);
            }
        }
        $jd = isset($r['jadwal']) ? json_decode((string) $r['jadwal'], true) : null;
        if (is_array($jd)) {
            $S['jadwal'] = array(
                'mode'  => isset($jd['mode']) ? (string) $jd['mode'] : 'teks',
                'bulan' => isset($jd['bulan']) && is_array($jd['bulan']) ? $jd['bulan'] : array(),
                'rows'  => isset($jd['rows']) && is_array($jd['rows']) ? $jd['rows'] : array(),
            );
            $S['jadwalTeks'] = (string) (isset($jd['teks']) ? $jd['teks'] : '');
        }
        return $S;
    }

    /** Isi form versi lama (dasar = satu kotak teks, noPenawaran/tglPenawaran di Data Mitra) -> bentuk Dasar baru. */
    protected static function normalisasiLama(array $d)
    {
        if (isset($d['dasarPenawaran']) || isset($d['dasarPoin'])) {
            return $d;
        }
        $nomor = ''; $romawi = ''; $tahun = '';
        if (preg_match('#^B/(.*)/BBSPJIS/MS/([IVX]+)/(\d{4})$#', trim((string) (isset($d['noPenawaran']) ? $d['noPenawaran'] : '')), $m)) {
            $nomor = $m[1]; $romawi = $m[2]; $tahun = $m[3];
        }
        $d['dasarPenawaran'] = array('nomor' => $nomor, 'romawi' => '', 'tahun' => '', 'tanggal' => (string) (isset($d['tglPenawaran']) ? $d['tglPenawaran'] : ''));
        $poin = array();
        $html = (string) (isset($d['dasar']) ? $d['dasar'] : '');
        if (preg_match_all('#<li[^>]*>(.*?)</li>#is', $html, $mm) && $mm[1]) {
            foreach ($mm[1] as $li) { $poin[] = array('teks' => $li); }
        } elseif (trim(strip_tags($html)) !== '') {
            $poin[] = array('teks' => $html);
        }
        $d['dasarPoin'] = $poin;
        return $d;
    }

    /**
     * Migrasi sekali jalan: kolom data_po lama (JSON gabungan) -> kolom-kolom po_kegiatan, lalu kolom
     * data_po DIHAPUS. Kolom hanya dihapus bila SEMUA baris berisi berhasil dipindahkan.
     */
    protected static function migrasiDataPo($db)
    {
        try {
            if (empty($db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'data_po'"))) {
                return;
            }
            $ada = self::namaKolom($db);
            foreach (array_keys(self::$kolomSertifikasi) as $k) {
                if (!in_array($k, $ada, true)) {
                    error_log('[PoSchema] migrasi data_po ditunda: kolom ' . $k . ' belum ada');
                    return;
                }
            }
            $semuaAman = true;
            $rows = $db->exec("SELECT id, data_po FROM po_kegiatan WHERE data_po IS NOT NULL AND data_po <> ''");
            foreach ($rows as $r) {
                $d = json_decode((string) $r['data_po'], true);
                if (!is_array($d)) {
                    error_log('[PoSchema] data_po PO #' . $r['id'] . ' tidak bisa dibaca -> kolom data_po TIDAK dihapus');
                    $semuaAman = false;
                    continue;
                }
                try {
                    self::ubahPo($db, (int) $r['id'], self::kolomDariForm(self::normalisasiLama($d)));
                } catch (\Throwable $e) {
                    error_log('[PoSchema] gagal memindahkan data_po PO #' . $r['id'] . ': ' . $e->getMessage());
                    $semuaAman = false;
                }
            }
            if ($semuaAman) {
                $db->exec('ALTER TABLE po_kegiatan DROP COLUMN data_po');
            }
        } catch (\Throwable $e) {
            error_log('[PoSchema] migrasi data_po gagal: ' . $e->getMessage());
        }
    }

    /** @return int id baris baru */
    public static function sisipPo($db, array $nilai)
    {
        $cols = self::kolomTabel($db);
        $data = array();
        foreach ($nilai as $k => $v) {
            if (isset($cols[$k])) {
                $data[$k] = self::sesuaikanNull($cols[$k], $v);
            }
        }
        foreach ($cols as $nama => $c) {
            if (array_key_exists($nama, $data)) {
                continue;
            }
            $extra = strtolower((string) $c['Extra']);
            if ($c['Null'] === 'NO' && $c['Default'] === null
                && strpos($extra, 'auto_increment') === false && strpos($extra, 'generated') === false) {
                $data[$nama] = self::nilaiNetral($c['Type']);
            }
        }
        $namaKol = array();
        $ph      = array();
        $params  = array();
        $i = 1;
        foreach ($data as $k => $v) {
            $namaKol[] = '`' . str_replace('`', '', $k) . '`';
            $ph[]      = '?';
            $params[$i++] = $v;
        }
        $db->exec('INSERT INTO po_kegiatan (' . implode(',', $namaKol) . ') VALUES (' . implode(',', $ph) . ')', $params);
        $r = $db->exec('SELECT LAST_INSERT_ID() AS id');
        return (int) (isset($r[0]['id']) ? $r[0]['id'] : 0);
    }

    public static function ubahPo($db, $id, array $nilai)
    {
        $cols = self::kolomTabel($db);
        $set = array();
        $params = array();
        $i = 1;
        foreach ($nilai as $k => $v) {
            if (isset($cols[$k]) && $k !== 'id') {
                $set[] = '`' . str_replace('`', '', $k) . '` = ?';
                $params[$i++] = self::sesuaikanNull($cols[$k], $v);
            }
        }
        if (empty($set)) {
            return;
        }
        $params[$i] = (int) $id;
        $db->exec('UPDATE po_kegiatan SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    /** Baris po_kegiatan (array) milik order sertifikasi tertentu, atau null. */
    public static function cariPoSertifikasi($db, $orderId)
    {
        $r = $db->exec(
            "SELECT * FROM po_kegiatan WHERE order_id = ? AND layanan = ? ORDER BY id DESC LIMIT 1",
            array(1 => (int) $orderId, 2 => self::SERTIFIKASI)
        );
        return empty($r) ? null : $r[0];
    }

    public static function ambilPoSertifikasi($db, $id)
    {
        $r = $db->exec(
            "SELECT * FROM po_kegiatan WHERE id = ? AND layanan = ? LIMIT 1",
            array(1 => (int) $id, 2 => self::SERTIFIKASI)
        );
        return empty($r) ? null : $r[0];
    }
}
