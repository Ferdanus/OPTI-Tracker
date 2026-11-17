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
    ];
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