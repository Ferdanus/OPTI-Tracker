<?php

/**
 * Model Po (Petunjuk Operasional)
 * Mengelola dokumen kerja PO, auto-numbering, Map Kendali, dan transisi status
 */
class Po extends \DB\SQL\Mapper {

    // Urutan status linear state machine baru sesuai rekap PO
    public static $URUTAN_STATUS = array(
        'belum_upload',
        'sudah_upload',
        'on_proses',
        'kembali_selesai'
    );

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'po');
    }

    /**
     * Konversi angka bulan (1-12) ke angka Romawi (I-XII)
     */
    public static function bulanKeRomawi($bulan) {
        $romawi = array(
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        );
        return $romawi[(int)$bulan] ?? 'I';
    }

    /**
     * Auto-generate nomor PO dengan format:
     * {urut}/PO/BBSPJIS/{bulan_romawi}/{tahun}
     * Nomor urut akan reset setiap pergantian bulan & tahun
     */
    public function generateNomorPo() {
        $bulanSekarang = (int)date('n');
        $tahunSekarang = (int)date('Y');
        $bulanRomawi   = self::bulanKeRomawi($bulanSekarang);

        // Hitung berapa banyak PO yang dibuat di bulan dan tahun yang sama
        $hasil = $this->db->exec(
            "SELECT COUNT(*) AS total FROM po WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?",
            array(1 => $bulanSekarang, 2 => $tahunSekarang)
        );

        $totalBulanIni = (int)($hasil[0]['total'] ?? 0);
        $nomorUrut     = sprintf('%02d', $totalBulanIni + 1);

        return "{$nomorUrut}/PO/BBSPJIS/{$bulanRomawi}/{$tahunSekarang}";
    }

    /**
     * Membuat PO baru otomatis saat Order Layanan disetujui
     */
    public function buatDariOrder($orderId, $biaya = 0) {
        $nomorPo = $this->generateNomorPo();

        $this->reset();
        $this->order_id          = $orderId;
        $this->nomor_po          = $nomorPo;
        $this->biaya             = $biaya;
        $this->status            = 'belum_upload'; // Status awal baru
        $this->tim_kerja         = null;
        $this->tanggal_keluar    = null;
        $this->tanggal_kembali   = null;
        $this->target_mulai      = null;
        $this->target_selesai    = null;
        $this->realisasi_selesai = null;
        $this->created_at        = date('Y-m-d H:i:s');
        $this->save();

        $poId = $this->id;

        // Catat ke log audit trail
        $logModel = new PoLogStatus($this->db);
        $logModel->catat(
            $poId,
            null,
            'belum_upload',
            'PO otomatis dibuat dari Order Layanan yang telah disetujui.'
        );

        return $poId;
    }

    /**
     * Ambil PO berdasarkan ID
     */
    public function getById($id) {
        $this->load(array('id = ?', $id));
        return $this->dry() ? null : $this;
    }

    /**
     * Ambil detail PO lengkap dengan data Order Layanan, data Klien, dan Kontrak PKS
     */
    public function getDetail($id) {
        $hasil = $this->db->exec(
            "SELECT p.*, 
                    o.nomor_order, o.judul_kegiatan, o.deskripsi AS deskripsi_order, o.tanggal_masuk, o.status AS status_order, o.jenis_layanan, o.jumlah_pekerjaan, o.estimasi_biaya,
                    k.id AS klien_id, k.nama_perusahaan, k.pic, k.telepon, k.email, k.alamat,
                    kp.id AS kontrak_id, kp.nomor_pks_klien, kp.nomor_pks_bbspjis, kp.status_ttd AS kontrak_status
             FROM po p
             JOIN order_layanan o ON p.order_id = o.id
             JOIN klien k ON o.klien_id = k.id
             LEFT JOIN kontrak_pks kp ON p.id = kp.po_id
             WHERE p.id = ?",
            array(1 => $id)
        );
        return $hasil[0] ?? null;
    }

    /**
     * Ambil daftar semua PO dengan relasi ke Order Layanan, Klien, dan Kontrak PKS (support filter)
     */
    public function allWithRelasi($filterBulan = '', $filterTahun = '', $filterStatus = '', $filterJenisLayanan = '', $search = '') {
        $sql = "SELECT p.*, 
                       o.nomor_order,
                       o.judul_kegiatan, 
                       o.jenis_layanan,
                       o.jumlah_pekerjaan,
                       o.estimasi_biaya,
                       o.tanggal_masuk,
                       k.nama_perusahaan,
                       k.pic,
                       k.alamat,
                       k.telepon,
                       k.email,
                       kp.id AS kontrak_id,
                       kp.nomor_pks_klien,
                       kp.nomor_pks_bbspjis
                FROM po p
                JOIN order_layanan o ON p.order_id = o.id
                JOIN klien k ON o.klien_id = k.id
                LEFT JOIN kontrak_pks kp ON p.id = kp.po_id
                WHERE 1=1";
        
        $params = array();
        $paramIdx = 1;

        if (!empty($filterBulan)) {
            $sql .= " AND MONTH(p.created_at) = ?";
            $params[$paramIdx++] = (int)$filterBulan;
        }

        if (!empty($filterTahun)) {
            $sql .= " AND YEAR(p.created_at) = ?";
            $params[$paramIdx++] = (int)$filterTahun;
        }

        if (!empty($filterStatus)) {
            $sql .= " AND p.status = ?";
            $params[$paramIdx++] = $filterStatus;
        }

        if (!empty($filterJenisLayanan)) {
            $sql .= " AND o.jenis_layanan = ?";
            $params[$paramIdx++] = $filterJenisLayanan;
        }

        if (!empty($search)) {
            $sql .= " AND (p.nomor_po LIKE ? OR k.nama_perusahaan LIKE ? OR o.judul_kegiatan LIKE ?)";
            $params[$paramIdx++] = '%' . $search . '%';
            $params[$paramIdx++] = '%' . $search . '%';
            $params[$paramIdx++] = '%' . $search . '%';
        }

        $sql .= " ORDER BY p.id DESC";

        return $this->db->exec($sql, $params);
    }

    /**
     * Mendapatkan tahap status berikutnya (State Machine Linear)
     * belum_upload -> sudah_upload -> on_proses -> kembali_selesai
     */
    public static function getNextStatus($currentStatus) {
        $index = array_search($currentStatus, self::$URUTAN_STATUS);
        if ($index === false || $index >= count(self::$URUTAN_STATUS) - 1) {
            return null; // Sudah mencapai tahap akhir
        }
        return self::$URUTAN_STATUS[$index + 1];
    }

    /**
     * Label representasi nama status yang ramah dibaca
     */
    public static function getStatusLabel($status) {
        $labels = array(
            'belum_upload'   => 'Belum Upload Dokumen sudah Diterima',
            'sudah_upload'   => 'Sudah Upload Dokumen belum Diterima',
            'on_proses'      => 'On Proses',
            'kembali_selesai'=> 'PO sudah kembali-selesai'
        );
        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Memajukan status PO ke 1 tahap berikutnya dan mencatat audit log
     */
    public function majuStatus($id, $catatan = '', array $dataTambahan = array()) {
        $this->load(array('id = ?', $id));

        if ($this->dry()) {
            throw new \Exception("Dokumen PO #{$id} tidak ditemukan.");
        }

        $statusLama = $this->status;
        $statusBaru = self::getNextStatus($statusLama);

        if (!$statusBaru) {
            throw new \Exception("PO #{$id} sudah mencapai tahap akhir ({$statusLama}) dan tidak dapat dimajukan lagi.");
        }

        $this->status = $statusBaru;

        // Update data opsional jika diisi
        if (isset($dataTambahan['biaya']) && is_numeric($dataTambahan['biaya'])) {
            $this->biaya = (float)$dataTambahan['biaya'];
        }
        if (!empty($dataTambahan['tim_kerja'])) {
            $this->tim_kerja = $dataTambahan['tim_kerja'];
        }
        if (!empty($dataTambahan['tanggal_keluar'])) {
            $this->tanggal_keluar = $dataTambahan['tanggal_keluar'];
        }
        if (!empty($dataTambahan['tanggal_kembali'])) {
            $this->tanggal_kembali = $dataTambahan['tanggal_kembali'];
        }
        if (!empty($dataTambahan['target_mulai'])) {
            $this->target_mulai = $dataTambahan['target_mulai'];
        }
        if (!empty($dataTambahan['target_selesai'])) {
            $this->target_selesai = $dataTambahan['target_selesai'];
        }
        if (!empty($dataTambahan['realisasi_selesai'])) {
            $this->realisasi_selesai = $dataTambahan['realisasi_selesai'];
        }

        $this->save();

        // Catat ke log histori status
        $logModel = new PoLogStatus($this->db);
        $logModel->catat(
            $this->id,
            $statusLama,
            $statusBaru,
            $catatan ?: "Status dimajukan dari '" . self::getStatusLabel($statusLama) . "' ke '" . self::getStatusLabel($statusBaru) . "'."
        );

        return $statusBaru;
    }

    /**
     * Logika verifikasi & validasi Map Kendali berjenjang
     */
    public function approveMapKendali($id, $stage) {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("PO #{$id} tidak ditemukan.");
        }

        $validStages = array(
            'proposal' => 'app_proposal',
            'kontrak' => 'app_kontrak',
            'po_adm' => 'app_po_adm',
            'po_mitra' => 'app_po_mitra',
            'po_ppk' => 'app_po_ppk',
            'po_kabag' => 'app_po_kabag',
            'dist_tu' => 'app_dist_tu',
            'dist_kepeg' => 'app_dist_kepeg',
            'dist_keu' => 'app_dist_keu'
        );

        if (!isset($validStages[$stage])) {
            throw new \Exception("Tahap approval '{$stage}' tidak valid.");
        }

        $column = $validStages[$stage];
        $columnDate = $column . '_date';

        // Set approved
        $this->$column = 1;
        $this->$columnDate = date('Y-m-d H:i:s');
        $this->save();

        // Catat ke log audit trail
        $logModel = new PoLogStatus($this->db);
        $logModel->catat(
            $this->id,
            $this->status,
            $this->status,
            "Persetujuan Map Kendali tahap '" . strtoupper($stage) . "' disetujui."
        );

        return true;
    }
}
