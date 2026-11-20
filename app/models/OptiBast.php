<?php

/**
 * Model OptiBast
 * Mengelola Berita Acara Serah Terima (BAST) dan Penutupan & Pengarsipan Order Layanan OPTI
 */
class OptiBast extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_bast');
    }

    /**
     * Generate nomor BAST resmi balai format: {urut}/BAST/BBSPJIS/{bulan_romawi}/{tahun}
     */
    public function generateNomorBast(string $tanggal = ''): string
    {
        $time = !empty($tanggal) ? strtotime($tanggal) : time();
        if (!$time) $time = time();

        $bulanAngka  = (int) date('n', $time);
        $tahun       = date('Y', $time);
        $bulanRomawi = SuratPenawaran::bulanKeRomawi($bulanAngka);

        $pattern = "%/BAST/BBSPJIS/{$bulanRomawi}/{$tahun}";
        $res = $this->db->exec(
            "SELECT nomor_bast FROM opti_bast WHERE nomor_bast LIKE ? ORDER BY id DESC LIMIT 1",
            array(1 => $pattern)
        );

        $urut = 1;
        if (!empty($res) && !empty($res[0]['nomor_bast'])) {
            $parts = explode('/', $res[0]['nomor_bast']);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $urut = (int) $parts[0] + 1;
            }
        }

        $nomorUrutPadded = str_pad((string)$urut, 2, '0', STR_PAD_LEFT);
        return "{$nomorUrutPadded}/BAST/BBSPJIS/{$bulanRomawi}/{$tahun}";
    }

    /**
     * Simpan data BAST baru
     */
    public function simpanBaru(array $data): int {
        $this->reset();
        $this->order_id              = (int)$data['order_id'];
        $this->po_id                 = !empty($data['po_id']) ? (int)$data['po_id'] : null;
        $this->nomor_bast            = !empty($data['nomor_bast']) ? trim($data['nomor_bast']) : $this->generateNomorBast($data['tanggal_bast'] ?? '');
        $this->tanggal_bast         = $data['tanggal_bast'] ?? date('Y-m-d');
        $this->pihak_pertama_nama    = trim($data['pihak_pertama_nama'] ?? 'Kepala BBSPJIS');
        $this->pihak_pertama_jabatan = trim($data['pihak_pertama_jabatan'] ?? 'Kepala Balai Besar');
        $this->pihak_kedua_nama      = trim($data['pihak_kedua_nama'] ?? '');
        $this->pihak_kedua_jabatan   = trim($data['pihak_kedua_jabatan'] ?? '');
        $this->judul_pekerjaan       = trim($data['judul_pekerjaan'] ?? '');
        $this->ringkasan_serah_terima= trim($data['ringkasan_serah_terima'] ?? '');
        $this->file_dokumen_bast     = !empty($data['file_dokumen_bast']) ? $data['file_dokumen_bast'] : null;
        $this->status_bast           = $data['status_bast'] ?? 'draft';
        $this->status_penutupan      = $data['status_penutupan'] ?? 'aktif';
        $this->created_at            = date('Y-m-d H:i:s');
        $this->save();

        $bastId = (int)$this->id;

        // Sinkronkan data BAST ke PO jika terhubung
        if (!empty($this->po_id)) {
            $this->db->exec(
                "UPDATE po SET bast_dokumen = ?, tgl_bast = ? WHERE id = ?",
                array(1 => $this->file_dokumen_bast, 2 => $this->tanggal_bast, 3 => $this->po_id)
            );
        }

        return $bastId;
    }

    /**
     * Update data BAST
     */
    public function updateData(int $id, array $data): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("BAST #{$id} tidak ditemukan.");
        }

        $this->nomor_bast            = !empty($data['nomor_bast']) ? trim($data['nomor_bast']) : $this->nomor_bast;
        $this->tanggal_bast         = $data['tanggal_bast'] ?? $this->tanggal_bast;
        $this->pihak_pertama_nama    = trim($data['pihak_pertama_nama'] ?? $this->pihak_pertama_nama);
        $this->pihak_pertama_jabatan = trim($data['pihak_pertama_jabatan'] ?? $this->pihak_pertama_jabatan);
        $this->pihak_kedua_nama      = trim($data['pihak_kedua_nama'] ?? $this->pihak_kedua_nama);
        $this->pihak_kedua_jabatan   = trim($data['pihak_kedua_jabatan'] ?? $this->pihak_kedua_jabatan);
        $this->judul_pekerjaan       = trim($data['judul_pekerjaan'] ?? $this->judul_pekerjaan);
        $this->ringkasan_serah_terima= trim($data['ringkasan_serah_terima'] ?? $this->ringkasan_serah_terima);
        if (!empty($data['file_dokumen_bast'])) {
            $this->file_dokumen_bast = $data['file_dokumen_bast'];
        }
        $this->status_bast           = $data['status_bast'] ?? $this->status_bast;
        $this->status_penutupan      = $data['status_penutupan'] ?? $this->status_penutupan;
        $this->updated_at            = date('Y-m-d H:i:s');
        $this->save();

        if (!empty($this->po_id)) {
            $this->db->exec(
                "UPDATE po SET bast_dokumen = ?, tgl_bast = ? WHERE id = ?",
                array(1 => $this->file_dokumen_bast, 2 => $this->tanggal_bast, 3 => $this->po_id)
            );
        }

        return true;
    }

    /**
     * Ambil data BAST berdasarkan Order ID
     */
    public function getByOrderId(int $orderId): ?array {
        $res = $this->db->exec(
            "SELECT b.*, u.nama_user AS closed_by_nama 
             FROM opti_bast b 
             LEFT JOIN tb_arsipuser u ON b.closed_by = u.id_user 
             WHERE b.order_id = ? ORDER BY b.id DESC LIMIT 1",
            array(1 => $orderId)
        );
        return $res[0] ?? null;
    }

    /**
     * Ambil data BAST berdasarkan PO ID
     */
    public function getByPoId(int $poId): ?array {
        $res = $this->db->exec(
            "SELECT b.*, u.nama_user AS closed_by_nama 
             FROM opti_bast b 
             LEFT JOIN tb_arsipuser u ON b.closed_by = u.id_user 
             WHERE b.po_id = ? ORDER BY b.id DESC LIMIT 1",
            array(1 => $poId)
        );
        return $res[0] ?? null;
    }

    /**
     * Memproses Penutupan & Pengarsipan Order (Closing Order)
     */
    public function tutupOrder(int $bastId, int $orderId, string $catatan, int $userId): bool {
        $this->load(array('id = ?', $bastId));
        if ($this->dry()) {
            throw new \Exception("BAST #{$bastId} tidak ditemukan.");
        }

        $now = date('Y-m-d H:i:s');
        $this->status_bast      = 'selesai';
        $this->status_penutupan = 'selesai_diarsipkan';
        $this->catatan_penutupan= trim($catatan);
        $this->closed_by        = $userId;
        $this->closed_at        = $now;
        $this->updated_at       = $now;
        $this->save();

        // Update status order_layanan menjadi 'selesai'
        $this->db->exec(
            "UPDATE order_layanan SET status = 'selesai', status_pelaksanaan = 'laporan_selesai' WHERE id = ?",
            array(1 => $orderId)
        );

        // Jika ada PO, pastikan PO juga selesai
        if (!empty($this->po_id)) {
            $this->db->exec(
                "UPDATE po SET status = 'kembali_selesai', realisasi_selesai = COALESCE(realisasi_selesai, CURDATE()) WHERE id = ?",
                array(1 => $this->po_id)
            );

            $logModel = new PoLogStatus($this->db);
            $logModel->catat(
                $this->po_id,
                'kembali_selesai',
                'kembali_selesai',
                "Order & PO resmi DITUTUP & DIARSIPKAN. BAST No: {$this->nomor_bast}. Catatan: {$catatan}"
            );
        }

        return true;
    }

    /* ============================================================
     * Form BAST (nomor urut manual, data dari PKS)
     * ============================================================ */

    const KOLOM = [
        'nomor_urut'               => "VARCHAR(9) NOT NULL DEFAULT ''",
        'id_nomor_urut'            => 'INT UNSIGNED NULL',
        'pihak_pertama_perusahaan' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'pihak_pertama_alamat'     => "VARCHAR(1000) NOT NULL DEFAULT ''",
        'pihak_kedua_nip'          => "VARCHAR(60) NOT NULL DEFAULT ''",
        'mengetahui_nama'          => "VARCHAR(150) NOT NULL DEFAULT ''",
        'mengetahui_jabatan'       => "VARCHAR(150) NOT NULL DEFAULT ''",
        'pks_nomor'                => "VARCHAR(120) NOT NULL DEFAULT ''",
        'pks_tanggal'              => 'DATE NULL',
        'dikirim_at'               => 'DATETIME NULL',          // Katim mengirim BAST ke Humas
        'dikirim_oleh'             => 'INT UNSIGNED NULL',
        'dibuka_humas_at'          => 'DATETIME NULL',          // Humas pertama kali membuka BAST yang dikirim
    ];
    /** Alur status_bast: draft (disiapkan sistem, Katim melengkapi) -> proses_ttd (sudah dikirim ke Humas, menunggu nomor) -> selesai (Humas memberi nomor). */
    const ST_DRAF   = 'draft';
    const ST_KIRIM  = 'proses_ttd';
    const ST_SELESAI = 'selesai';
    const ROMAWI = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    const BULAN  = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    protected static $skemaSiap = null;

    /**
     * Pastikan tabel bast_nomor_urut dan kolom tambahan opti_bast ada (dibuat otomatis kalau belum, satu kali per request).
     * Aturan unik nomor: urut + bulan + tahun (deret BAST sendiri, terpisah dari nomor PKS).
     */
    public static function pastikanSkema($db) {
        if (self::$skemaSiap !== null) { return self::$skemaSiap; }
        self::$skemaSiap = false;
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `bast_nomor_urut` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `nomor_urut` INT UNSIGNED NOT NULL,
                `bulan` TINYINT UNSIGNED NOT NULL,
                `tahun` SMALLINT UNSIGNED NOT NULL,
                `detail_nomor` VARCHAR(60) NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_bast_urut_bulan_tahun` (`tahun`, `bulan`, `nomor_urut`),
                UNIQUE KEY `uniq_bast_detail_nomor` (`detail_nomor`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $ada = array();
            foreach ((array) $db->exec('SHOW COLUMNS FROM opti_bast') as $c) { $ada[$c['Field']] = strtolower((string) $c['Type']); }
            foreach (self::KOLOM as $nama => $def) {
                if (isset($ada[$nama])) { continue; }
                try { $db->exec('ALTER TABLE opti_bast ADD COLUMN `' . $nama . '` ' . $def); } catch (\Throwable $e) { error_log('[BAST] ALTER ' . $nama . ': ' . $e->getMessage()); }
            }
            // kolom lama terlalu pendek untuk judul PKS / nama panjang
            $lebar = array('judul_pekerjaan' => array(1000, 'VARCHAR(1000) NOT NULL'), 'pihak_pertama_nama' => array(150, 'VARCHAR(150) NOT NULL'),
                           'pihak_pertama_jabatan' => array(200, 'VARCHAR(200) NOT NULL'), 'pihak_kedua_nama' => array(150, 'VARCHAR(150) NOT NULL'),
                           'pihak_kedua_jabatan' => array(200, 'VARCHAR(200) NOT NULL'));
            foreach ($lebar as $nama => $cfg) {
                if (isset($ada[$nama]) && preg_match('/^varchar\((\d+)\)/', $ada[$nama], $m) && (int) $m[1] < $cfg[0]) {
                    try { $db->exec('ALTER TABLE opti_bast MODIFY `' . $nama . '` ' . $cfg[1]); } catch (\Throwable $e) { error_log('[BAST] MODIFY ' . $nama . ': ' . $e->getMessage()); }
                }
            }
            // status_bast harus bisa menampung 'proses_ttd' (dipakai sebagai "dikirim ke Humas"); kolom ENUM lama dilebarkan jadi VARCHAR
            if (isset($ada['status_bast']) && strpos($ada['status_bast'], 'enum') === 0 && strpos($ada['status_bast'], 'proses_ttd') === false) {
                try { $db->exec("ALTER TABLE opti_bast MODIFY `status_bast` VARCHAR(20) NOT NULL DEFAULT 'draft'"); } catch (\Throwable $e) { error_log('[BAST] MODIFY status_bast: ' . $e->getMessage()); }
            }
            $ada = array();
            foreach ((array) $db->exec('SHOW COLUMNS FROM opti_bast') as $c) { $ada[$c['Field']] = true; }
            self::$skemaSiap = true;
            foreach (self::KOLOM as $nama => $def) { if (!isset($ada[$nama])) { self::$skemaSiap = false; } }
        } catch (\Throwable $e) {
            error_log('[BAST] pastikanSkema: ' . $e->getMessage());
            self::$skemaSiap = false;
        }
        return self::$skemaSiap;
    }

    /* ============================================================
     * Draf BAST otomatis (dari PKS) dan antrean Humas
     * ============================================================ */

    /** PKS selesai milik order ini (via PO kegiatan), atau null. */
    public static function ambilPks($db, $orderId) {
        try {
            \PoSchema::pastikan($db);
            $r = $db->exec(
                "SELECT k.* FROM pks_kontrak k JOIN po_kegiatan p ON p.id = k.po_id WHERE p.order_id = ?" . \PoSchema::bukanSertifikasi('p') . " AND k.status = 'selesai' ORDER BY k.id DESC LIMIT 1",
                array(1 => (int) $orderId)
            );
            return !empty($r) ? $r[0] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Teks berformat PKS (b/i/u) -> teks polos, satu poin per baris. */
    public static function teksPolos($v) {
        try {
            $baris = array();
            foreach (\PksDocx::barisDariTeks((string) $v) as $b) {
                $t = \PksDocx::teksBaris($b);
                if (trim($t) !== '') { $baris[] = $t; }
            }
            return implode("\n", $baris);
        } catch (\Throwable $e) {
            return trim(strip_tags((string) $v));
        }
    }

    /** Syarat draf otomatis: PO bertanda tangan sudah diunggah, PKS dibuat Humas, dan Laporan Akhir (order diselesaikan) sudah selesai. */
    public static function syaratDrafTerpenuhi($db, $orderId) {
        try {
            \PoSchema::pastikan($db);
            \OrderLayanan::ensureColumns($db);
            $r = $db->exec('SELECT po_ttd_file, kontrak_dibuat_at FROM po_kegiatan WHERE order_id = ?' . \PoSchema::bukanSertifikasi('') . ' ORDER BY id DESC LIMIT 1', array(1 => (int) $orderId));
            if (empty($r) || empty($r[0]['po_ttd_file']) || empty($r[0]['kontrak_dibuat_at'])) { return false; }
            $o = $db->exec('SELECT laporan_akhir_selesai_at FROM order_layanan WHERE id = ? LIMIT 1', array(1 => (int) $orderId));
            return !empty($o[0]['laporan_akhir_selesai_at']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Buat draf BAST otomatis (terisi dari PKS) begitu syaratnya terpenuhi dan order belum punya BAST. Pihak Kedua = Ketua Tim layanan order ini.
     * Aman dipanggil berulang (dari hook Laporan Akhir / PKS dan secara lazy saat halaman dibuka). Mengembalikan id BAST baru, atau null kalau tidak membuat apa pun.
     * Katim diberi notifikasi sekali, saat draf dibuat.
     */
    public static function siapkanDraf($db, $orderId, $oleh = 0, $namaOleh = 'Sistem') {
        $orderId = (int) $orderId;
        if ($orderId <= 0) { return null; }
        $kunci = 'bast_draf_' . $orderId; $kunciDapat = false;
        try {
            if (!self::pastikanSkema($db)) { return null; }
            $ada = $db->exec('SELECT id FROM opti_bast WHERE order_id = ? LIMIT 1', array(1 => $orderId));
            if (!empty($ada) || !self::syaratDrafTerpenuhi($db, $orderId)) { return null; }
            $pks = self::ambilPks($db, $orderId);
            if (!$pks) { return null; }
            try {
                $r = $db->exec('SELECT GET_LOCK(?, 3) AS k', array(1 => $kunci));
                $kunciDapat = !empty($r) && (int) (isset($r[0]['k']) ? $r[0]['k'] : 0) === 1;
            } catch (\Throwable $e) {}
            $ada = $db->exec('SELECT id FROM opti_bast WHERE order_id = ? LIMIT 1', array(1 => $orderId));
            if (!empty($ada)) { return null; }

            $order = (new \OrderLayanan($db))->getDetail($orderId);
            if (!$order) { return null; }
            $layanan = strtolower(trim((string) ($order['jenis_layanan_opti'] ?? '')));
            $katim = null;
            try { $katim = (new \ArsipUser($db))->getKetuaTim($layanan); } catch (\Throwable $e) {}
            $perusahaan = trim((string) $pks['perusahaan']);
            if ($perusahaan === '') { $perusahaan = trim((string) ($order['nama_perusahaan'] ?? '')); }

            $id = (new self($db))->simpanFormulir(array(
                'order_id' => $orderId, 'po_id' => !empty($order['po_id']) ? (int) $order['po_id'] : null,
                'nomor_bast' => 'DRAFT-' . $orderId, 'nomor_urut' => '', 'id_nomor_urut' => null, 'tanggal_bast' => date('Y-m-d'),
                'pihak_pertama_nama' => (string) $pks['ttd_nama'], 'pihak_pertama_jabatan' => (string) $pks['ttd_jabatan'],
                'pihak_pertama_perusahaan' => $perusahaan, 'pihak_pertama_alamat' => self::teksPolos($pks['alamat_perusahaan']),
                'pihak_kedua_nama' => $katim ? trim((string) $katim['nama_user']) : '', 'pihak_kedua_jabatan' => '', 'pihak_kedua_nip' => '',
                'judul_pekerjaan' => self::teksPolos($pks['judul']), 'mengetahui_nama' => '', 'mengetahui_jabatan' => '',
                'pks_nomor' => (string) $pks['nomor_pks'], 'pks_tanggal' => !empty($pks['tanggal_perjanjian']) ? substr((string) $pks['tanggal_perjanjian'], 0, 10) : null,
                'file_dokumen_bast' => null, 'status_bast' => self::ST_DRAF,
            ), null);

            try {
                \NotificationService::send($db, array(
                    'order_id' => $orderId, 'target_role' => 'ketua_tim', 'target_layanan' => $layanan !== '' ? $layanan : 'semua',
                    'judul' => 'BAST Siap Dilengkapi',
                    'pesan' => 'BAST untuk Order #' . (string) ($order['nomor_order'] ?? $orderId) . ' ' . $perusahaan . ' sudah disiapkan otomatis dari PKS. Lengkapi jabatan dan NIP Pihak Kedua, lalu kirim ke Humas.',
                    'tipe' => 'primary', 'icon' => 'bi-file-earmark-text', 'link_url' => '/order/' . $orderId . '/bast',
                    'created_by' => $oleh > 0 ? (int) $oleh : null, 'created_by_name' => $namaOleh,
                ));
            } catch (\Throwable $e) {}
            return $id;
        } catch (\Throwable $e) {
            error_log('[BAST] siapkanDraf order ' . $orderId . ': ' . $e->getMessage());
            return null;
        } finally {
            if ($kunciDapat) { try { $db->exec('SELECT RELEASE_LOCK(?)', array(1 => $kunci)); } catch (\Throwable $e) {} }
        }
    }

    /** Katim mengirim BAST ke Humas: catat waktu + pengirim, buka-tanda Humas direset. */
    public function tandaiDikirim($id, $oleh) {
        $this->db->exec('UPDATE opti_bast SET dikirim_at = NOW(), dikirim_oleh = ?, dibuka_humas_at = NULL WHERE id = ?', array(1 => (int) $oleh, 2 => (int) $id));
    }

    /** Humas membuka BAST yang dikirim (hanya mencatat pembukaan pertama). */
    public function tandaiDibuka($id) {
        $this->db->exec('UPDATE opti_bast SET dibuka_humas_at = NOW() WHERE id = ? AND dibuka_humas_at IS NULL', array(1 => (int) $id));
    }

    /** Daftar BAST untuk halaman Humas. $status: ST_KIRIM (perlu nomor) atau ST_SELESAI. Urut terbaru dikirim / selesai dulu. */
    public static function daftarHumas($db, $status) {
        try {
            self::pastikanSkema($db);
            $urut = ($status === self::ST_SELESAI) ? 'b.updated_at DESC, b.id DESC' : 'b.dikirim_at DESC, b.id DESC';
            return (array) $db->exec(
                "SELECT b.id, b.order_id, b.nomor_bast, b.nomor_urut, b.tanggal_bast, b.status_bast, b.pihak_pertama_perusahaan, b.judul_pekerjaan, b.pks_nomor,
                        b.pihak_kedua_nama, b.dikirim_at, b.dibuka_humas_at, b.updated_at, o.nomor_order, o.jenis_layanan_opti, u.nama_user AS pengirim
                 FROM opti_bast b JOIN order_layanan o ON o.id = b.order_id LEFT JOIN tb_arsipuser u ON u.id_user = b.dikirim_oleh
                 WHERE b.status_bast = ? ORDER BY " . $urut,
                array(1 => $status)
            );
        } catch (\Throwable $e) {
            error_log('[BAST] daftarHumas: ' . $e->getMessage());
            return array();
        }
    }

    /** Jumlah BAST yang sudah dikirim Katim dan menunggu nomor dari Humas (badge menu Humas). */
    public static function hitungMenungguHumas($db) {
        try {
            self::pastikanSkema($db);
            $r = $db->exec("SELECT COUNT(*) AS c FROM opti_bast WHERE status_bast = ?", array(1 => self::ST_KIRIM));
            return (int) (isset($r[0]['c']) ? $r[0]['c'] : 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function detailNomor($urut, $bulan, $tahun) {
        return 'B/' . (int) $urut . '/BBSPJIS/HK/' . self::ROMAWI[(int) $bulan] . '/' . (int) $tahun;
    }

    /**
     * Simpan isian form BAST (baru kalau $idLama kosong, kalau tidak memperbarui barisnya). Mengembalikan id BAST.
     * $d: order_id, po_id, nomor_bast, nomor_urut, id_nomor_urut, tanggal_bast, pihak_*, judul_pekerjaan, mengetahui_*, pks_*, file_dokumen_bast, status_bast
     */
    public function simpanFormulir(array $d, $idLama = null) {
        $kolom = array('nomor_bast', 'nomor_urut', 'id_nomor_urut', 'tanggal_bast', 'pihak_pertama_nama', 'pihak_pertama_jabatan', 'pihak_pertama_perusahaan',
                       'pihak_pertama_alamat', 'pihak_kedua_nama', 'pihak_kedua_jabatan', 'pihak_kedua_nip', 'judul_pekerjaan', 'mengetahui_nama',
                       'mengetahui_jabatan', 'pks_nomor', 'pks_tanggal', 'file_dokumen_bast', 'status_bast');
        $nilai = array();
        foreach ($kolom as $k) { $nilai[$k] = array_key_exists($k, $d) ? $d[$k] : null; }
        if ($idLama) {
            $set = array(); $p = array(); $i = 1;
            foreach ($kolom as $k) { $set[] = '`' . $k . '` = ?'; $p[$i++] = $nilai[$k]; }
            $set[] = 'updated_at = NOW()';
            $p[$i++] = (int) $idLama;
            $this->db->exec('UPDATE opti_bast SET ' . implode(', ', $set) . ' WHERE id = ?', $p);
            $id = (int) $idLama;
        } else {
            $kol = array_merge(array('order_id', 'po_id', 'created_at'), $kolom);
            $ph = array(); $p = array(); $i = 1;
            foreach ($kol as $k) {
                if ($k === 'created_at') { $ph[] = 'NOW()'; continue; }
                $ph[] = '?';
                $p[$i++] = ($k === 'order_id') ? (int) $d['order_id'] : (($k === 'po_id') ? (!empty($d['po_id']) ? (int) $d['po_id'] : null) : $nilai[$k]);
            }
            $this->db->exec('INSERT INTO opti_bast (`' . implode('`, `', $kol) . '`) VALUES (' . implode(', ', $ph) . ')', $p);
            $r = $this->db->exec('SELECT LAST_INSERT_ID() AS id');
            $id = (int) (isset($r[0]['id']) ? $r[0]['id'] : 0);
        }
        // sinkron ke tabel po lama kalau terhubung (sama seperti simpanBaru)
        if (!empty($d['po_id'])) {
            try {
                $this->db->exec('UPDATE po SET bast_dokumen = ?, tgl_bast = ? WHERE id = ?', array(1 => $nilai['file_dokumen_bast'], 2 => $nilai['tanggal_bast'], 3 => (int) $d['po_id']));
            } catch (\Throwable $e) {}
        }
        return $id;
    }

    /**
     * Nomor yang bentrok = urut + bulan + tahun SAMA semua (urut sama beda bulan/tahun boleh).
     * $kecualiId = baris bast_nomor_urut milik BAST ini sendiri.
     * @return array|null ['id', 'detail_nomor', 'nomor_order']
     */
    public function cariBentrok($urut, $bulan, $tahun, $kecualiId = null) {
        $arg = array(1 => (int) $urut, 2 => (int) $bulan, 3 => (int) $tahun, 4 => (int) $kecualiId);
        try {
            $r = $this->db->exec(
                'SELECT n.id, n.detail_nomor, (SELECT o.nomor_order FROM opti_bast b JOIN order_layanan o ON o.id = b.order_id WHERE b.id_nomor_urut = n.id LIMIT 1) AS nomor_order
                   FROM bast_nomor_urut n WHERE n.nomor_urut = ? AND n.bulan = ? AND n.tahun = ? AND n.id <> ? LIMIT 1', $arg);
        } catch (\Throwable $e) {
            $r = $this->db->exec('SELECT n.id, n.detail_nomor, NULL AS nomor_order FROM bast_nomor_urut n WHERE n.nomor_urut = ? AND n.bulan = ? AND n.tahun = ? AND n.id <> ? LIMIT 1', $arg);
        }
        return empty($r) ? null : $r[0];
    }

    public static function pesanBentrok(array $b, $urut, $bulan, $tahun) {
        return 'Nomor urut ' . (int) $urut . ' sudah dipakai BAST lain di ' . self::BULAN[(int) $bulan - 1] . ' ' . (int) $tahun . ' (' . $b['detail_nomor'] .
            (!empty($b['nomor_order']) ? ', Order ' . $b['nomor_order'] : '') . '). Pakai angka lain, atau tanggal BAST di bulan/tahun berbeda: urut yang sama hanya boleh kalau bulan atau tahunnya beda.';
    }

    /**
     * Daftarkan nomor BAST ke bast_nomor_urut. $idLama = baris milik BAST ini (diperbarui), kosong = baris baru.
     * Bentrok: Exception kode 409. @return array{id:int, detail_nomor:string, baru:bool, lama:?array}
     */
    public function daftarkanNomor($urut, $tanggal, $idLama = null) {
        $ts = strtotime((string) $tanggal) ?: time();
        $bulan = (int) date('n', $ts);
        $tahun = (int) date('Y', $ts);
        $detail = self::detailNomor($urut, $bulan, $tahun);

        $namaKunci = 'bast_nomor_' . $tahun . '_' . $bulan;
        $kunciDapat = false;
        try {
            $r = $this->db->exec('SELECT GET_LOCK(?, 5) AS k', array(1 => $namaKunci));
            $kunciDapat = !empty($r) && (int) (isset($r[0]['k']) ? $r[0]['k'] : 0) === 1;
        } catch (\Throwable $e) {}

        try {
            $b = $this->cariBentrok($urut, $bulan, $tahun, $idLama);
            if ($b) { throw new \Exception(self::pesanBentrok($b, $urut, $bulan, $tahun), 409); }
            try {
                if ($idLama) {
                    $lama = $this->db->exec('SELECT * FROM bast_nomor_urut WHERE id = ?', array(1 => (int) $idLama));
                    if (!empty($lama)) {
                        $this->db->exec('UPDATE bast_nomor_urut SET nomor_urut = ?, bulan = ?, tahun = ?, detail_nomor = ?, updated_at = NOW() WHERE id = ?',
                            array(1 => (int) $urut, 2 => $bulan, 3 => $tahun, 4 => $detail, 5 => (int) $idLama));
                        return array('id' => (int) $idLama, 'detail_nomor' => $detail, 'baru' => false, 'lama' => $lama[0]);
                    }
                }
                $this->db->exec('INSERT INTO bast_nomor_urut (nomor_urut, bulan, tahun, detail_nomor, updated_at) VALUES (?, ?, ?, ?, NOW())',
                    array(1 => (int) $urut, 2 => $bulan, 3 => $tahun, 4 => $detail));
                $idRow = $this->db->exec('SELECT LAST_INSERT_ID() AS id');
                return array('id' => (int) (isset($idRow[0]['id']) ? $idRow[0]['id'] : 0), 'detail_nomor' => $detail, 'baru' => true, 'lama' => null);
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), '1062') !== false) {
                    throw new \Exception('Nomor ' . $detail . ' ditolak database karena sudah tercatat.', 409);
                }
                throw new \Exception('Gagal mendaftarkan nomor BAST. Detail: ' . $e->getMessage());
            }
        } finally {
            if ($kunciDapat) {
                try { $this->db->exec('SELECT RELEASE_LOCK(?)', array(1 => $namaKunci)); } catch (\Throwable $e) {}
            }
        }
    }

    /** Lepas baris nomor yang baru didaftarkan (penyimpanan BAST gagal sesudahnya). */
    public function lepasNomor($id) {
        try { $this->db->exec('DELETE FROM bast_nomor_urut WHERE id = ?', array(1 => (int) $id)); } catch (\Throwable $e) {}
    }

    /** Kembalikan baris nomor yang diubah ke nilai semula. */
    public function pulihkanNomor(array $row) {
        try {
            $this->db->exec('UPDATE bast_nomor_urut SET nomor_urut = ?, bulan = ?, tahun = ?, detail_nomor = ? WHERE id = ?',
                array(1 => (int) $row['nomor_urut'], 2 => (int) $row['bulan'], 3 => (int) $row['tahun'], 4 => (string) $row['detail_nomor'], 5 => (int) $row['id']));
        } catch (\Throwable $e) {}
    }

    /**
     * Hapus data BAST
     */
    public function hapus(int $id): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("BAST #{$id} tidak ditemukan.");
        }
        $this->erase();
        return true;
    }
}