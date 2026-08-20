<?php

/**
 * Model KontrakPks
 * Berinteraksi dengan tabel 'kontrak_pks' menggunakan F3 SQL Mapper
 * 
 * TODO: Konfirmasi posisi kontrak dalam alur: apakah PKS ditandatangani sebelum PO terbit,
 * sesudah PO terbit, atau paralel (1 order = 1 PO = 1 kontrak).
 */
class KontrakPks extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'kontrak_pks');
    }

    /**
     * Ambil data kontrak berdasarkan PO ID
     */
    public function getByPoId(int $poId) {
        $this->load(array('po_id = ?', $poId));
        return $this->dry() ? null : $this;
    }

    /**
     * Ambil data kontrak berdasarkan ID
     */
    public function getById(int $id) {
        $this->load(array('id = ?', $id));
        return $this->dry() ? null : $this;
    }

    /**
     * Ambil semua data kontrak lengkap dengan detail PO, Order, dan Customer
     */
    public function allWithRelasi(): array {
        return $this->db->exec(
            "SELECT c.*, 
                    p.nomor_po, p.status AS po_status,
                    o.judul_kegiatan, o.jenis_layanan_opti, o.spm_layanan,
                    cust.nmcustomer AS nama_perusahaan, cust.pt_cv
             FROM kontrak_pks c
             JOIN po p ON c.po_id = p.id
             JOIN order_layanan o ON p.order_id = o.id
             JOIN tb_customer cust ON o.id_customer = cust.id_customer
             ORDER BY c.id DESC"
        );
    }

    /**
     * Simpan data kontrak baru
     */
    public function simpanBaru(array $data): int {
        $this->reset();
        $this->po_id                         = (int)$data['po_id'];
        $this->nomor_pks_klien                = trim($data['nomor_pks_klien']);
        $this->nomor_pks_bbspjis             = trim($data['nomor_pks_bbspjis']);
        $this->nama_penandatangan_klien      = trim($data['nama_penandatangan_klien']);
        $this->jabatan_penandatangan_klien   = trim($data['jabatan_penandatangan_klien']);
        $this->nama_penandatangan_bbspjis    = trim($data['nama_penandatangan_bbspjis']);
        $this->jabatan_penandatangan_bbspjis = trim($data['jabatan_penandatangan_bbspjis']);
        $this->ruang_lingkup                 = trim($data['ruang_lingkup']);
        $this->target_mulai                  = $data['target_mulai'];
        $this->target_selesai                = $data['target_selesai'];
        $this->nilai_kontrak                 = (float)($data['nilai_kontrak'] ?? 0);
        $this->ketentuan_pembayaran          = trim($data['ketentuan_pembayaran'] ?? '');
        $this->nomor_va                      = trim($data['nomor_va'] ?? '');
        $this->tanggal_ttd                   = !empty($data['tanggal_ttd']) ? $data['tanggal_ttd'] : null;
        $this->status_ttd                    = $data['status_ttd'] ?? 'belum';
        $this->created_at                    = date('Y-m-d H:i:s');
        $this->save();
        return (int)$this->id;
    }

    /**
     * Memperbarui data kontrak PKS
     */
    public function updateData(int $id, array $data): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("Kontrak PKS #{$id} tidak ditemukan.");
        }

        if (!empty($data['po_id'])) {
            $this->po_id = (int)$data['po_id'];
        }
        $this->nomor_pks_klien                = trim($data['nomor_pks_klien']);
        $this->nomor_pks_bbspjis             = trim($data['nomor_pks_bbspjis']);
        $this->nama_penandatangan_klien      = trim($data['nama_penandatangan_klien']);
        $this->jabatan_penandatangan_klien   = trim($data['jabatan_penandatangan_klien']);
        $this->nama_penandatangan_bbspjis    = trim($data['nama_penandatangan_bbspjis']);
        $this->jabatan_penandatangan_bbspjis = trim($data['jabatan_penandatangan_bbspjis']);
        $this->ruang_lingkup                 = trim($data['ruang_lingkup']);
        $this->target_mulai                  = $data['target_mulai'];
        $this->target_selesai                = $data['target_selesai'];
        $this->nilai_kontrak                 = (float)($data['nilai_kontrak'] ?? 0);
        $this->ketentuan_pembayaran          = trim($data['ketentuan_pembayaran'] ?? '');
        $this->nomor_va                      = trim($data['nomor_va'] ?? '');
        $this->tanggal_ttd                   = !empty($data['tanggal_ttd']) ? $data['tanggal_ttd'] : null;
        $this->status_ttd                    = $data['status_ttd'] ?? 'belum';

        $this->save();
        return true;
    }

    /**
     * Menghapus data kontrak PKS
     */
    public function hapus(int $id): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("Kontrak PKS #{$id} tidak ditemukan.");
        }
        $this->erase();
        return true;
    }
}
