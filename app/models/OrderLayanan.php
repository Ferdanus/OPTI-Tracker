<?php

/**
 * Model OrderLayanan
 * Mengelola permintaan order dari klien dan transisi status order (baru, disetujui, ditolak)
 */
class OrderLayanan extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'order_layanan');
    }

    /**
     * Ambil semua order layanan lengkap dengan nama klien dan info PO terkait
     */
    public function allWithKlien() {
        return $this->db->exec(
            "SELECT o.*, 
                    k.nama_perusahaan, 
                    k.pic,
                    p.id AS po_id,
                    p.nomor_po,
                    p.status AS po_status
             FROM order_layanan o
             JOIN klien k ON o.klien_id = k.id
             LEFT JOIN po p ON o.id = p.order_id
             ORDER BY o.id DESC"
        );
    }

    /**
     * Ambil data order berdasarkan ID
     */
    public function getById($id) {
        $this->load(array('id = ?', $id));
        return $this->dry() ? null : $this;
    }

    /**
     * Simpan order layanan baru dengan status default 'baru'
     */
    public function simpanBaru(array $data) {
        $this->reset();
        $this->klien_id       = (int)$data['klien_id'];
        $this->judul_kegiatan = trim($data['judul_kegiatan']);
        $this->deskripsi      = trim($data['deskripsi'] ?? '');
        $this->tanggal_masuk  = $data['tanggal_masuk'];
        $this->status         = 'baru';
        $this->created_at     = date('Y-m-d H:i:s');
        $this->save();
        return $this->id;
    }

    /**
     * Menyetujui order layanan dan otomatis membuat record PO terkait
     */
    public function approve($id, $biaya = 0) {
        $this->load(array('id = ?', $id));

        if ($this->dry()) {
            throw new \Exception("Order Layanan dengan ID #{$id} tidak ditemukan.");
        }

        if ($this->status !== 'baru') {
            throw new \Exception("Order #{$id} sudah berstatus '{$this->status}' dan tidak dapat disetujui lagi.");
        }

        // 1. Update status order menjadi disetujui
        $this->status = 'disetujui';
        $this->save();

        // 2. Buat PO otomatis melalui model Po
        $poModel = new Po($this->db);
        $poId = $poModel->buatDariOrder($this->id, $biaya);

        return array(
            'order_id' => $this->id,
            'po_id'    => $poId,
            'nomor_po' => $poModel->nomor_po
        );
    }

    /**
     * Menolak order layanan
     */
    public function tolak($id) {
        $this->load(array('id = ?', $id));

        if ($this->dry()) {
            throw new \Exception("Order Layanan dengan ID #{$id} tidak ditemukan.");
        }

        if ($this->status !== 'baru') {
            throw new \Exception("Order #{$id} sudah berstatus '{$this->status}' dan tidak dapat diubah lagi.");
        }

        // Update status order menjadi ditolak
        $this->status = 'ditolak';
        $this->save();
        return true;
    }
}
