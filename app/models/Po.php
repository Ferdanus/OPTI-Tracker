<?php

/**
 * Model Po (Petunjuk Operasional)
 * Mengelola dokumen kerja PO, auto-numbering, dan transisi status
 */
class Po extends \DB\SQL\Mapper {

    // Urutan status linear state machine
    public static $URUTAN_STATUS = array(
        'proposal',
        'kontrak',
        'po_terbit',
        'distribusi',
        'selesai'
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
     * {urut}/PO/OPTI/{bulan_romawi}/{tahun}
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

        return "{$nomorUrut}/PO/OPTI/{$bulanRomawi}/{$tahunSekarang}";
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
        $this->status            = 'proposal'; // Status awal
        $this->tanggal_target    = null;
        $this->tanggal_realisasi = null;
        $this->created_at        = date('Y-m-d H:i:s');
        $this->save();

        $poId = $this->id;

        // Catat ke log audit trail
        $logModel = new PoLogStatus($this->db);
        $logModel->catat(
            $poId,
            null,
            'proposal',
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
     * Ambil detail PO lengkap dengan data Order Layanan dan data Klien
     */
    public function getDetail($id) {
        $hasil = $this->db->exec(
            "SELECT p.*, 
                    o.judul_kegiatan, o.deskripsi AS deskripsi_order, o.tanggal_masuk, o.status AS status_order,
                    k.id AS klien_id, k.nama_perusahaan, k.pic, k.telepon, k.email, k.alamat
             FROM po p
             JOIN order_layanan o ON p.order_id = o.id
             JOIN klien k ON o.klien_id = k.id
             WHERE p.id = ?",
            array(1 => $id)
        );
        return $hasil[0] ?? null;
    }

    /**
     * Ambil daftar semua PO dengan relasi ke Order Layanan dan Klien (support filter)
     */
    public function allWithRelasi($filterBulan = '', $filterTahun = '', $filterStatus = '') {
        $sql = "SELECT p.*, 
                       o.judul_kegiatan, 
                       k.nama_perusahaan
                FROM po p
                JOIN order_layanan o ON p.order_id = o.id
                JOIN klien k ON o.klien_id = k.id
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

        $sql .= " ORDER BY p.id DESC";

        return $this->db->exec($sql, $params);
    }

    /**
     * Mendapatkan tahap status berikutnya (State Machine Linear)
     * proposal -> kontrak -> po_terbit -> distribusi -> selesai
     */
    public static function getNextStatus($currentStatus) {
        $index = array_search($currentStatus, self::$URUTAN_STATUS);
        if ($index === false || $index >= count(self::$URUTAN_STATUS) - 1) {
            return null; // Sudah mencapai tahap akhir (selesai)
        }
        return self::$URUTAN_STATUS[$index + 1];
    }

    /**
     * Label representasi nama status yang ramah dibaca
     */
    public static function getStatusLabel($status) {
        $labels = array(
            'proposal'   => '1. Proposal',
            'kontrak'    => '2. Kontrak',
            'po_terbit'  => '3. PO Terbit',
            'distribusi' => '4. Distribusi',
            'selesai'    => '5. Selesai'
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

        // Update status di PO
        $this->status = $statusBaru;

        // Update data opsional jika diisi
        if (isset($dataTambahan['biaya']) && is_numeric($dataTambahan['biaya'])) {
            $this->biaya = (float)$dataTambahan['biaya'];
        }
        if (!empty($dataTambahan['tanggal_target'])) {
            $this->tanggal_target = $dataTambahan['tanggal_target'];
        }
        if (!empty($dataTambahan['tanggal_realisasi'])) {
            $this->tanggal_realisasi = $dataTambahan['tanggal_realisasi'];
        }

        $this->save();

        // Catat ke log histori status
        $logModel = new PoLogStatus($this->db);
        $logModel->catat(
            $this->id,
            $statusLama,
            $statusBaru,
            $catatan ?: "Status dimajukan dari '{$statusLama}' ke '{$statusBaru}'."
        );

        return $statusBaru;
    }
}
