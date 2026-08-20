<?php

/**
 * Model Customer
 * Berinteraksi dengan tabel master customer pusat balai 'tb_customer'
 * 
 * TODO: Konfirmasi ke admin database bahwa hak akses tb_customer adalah read-only
 * dari sisi OPTI kecuali saat menambah customer baru yang memesan layanan OPTI.
 * TODO: Konfirmasi apakah database OPTI akan satu server MySQL dengan database utama balai (202.150.151.244).
 */
class Customer extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'tb_customer');
    }

    /**
     * Ambil seluruh customer
     */
    public function all() {
        return $this->db->exec("SELECT * FROM tb_customer WHERE showhide = 'show' OR id_layanan_optimalisasi = 1 ORDER BY id_customer DESC");
    }

    /**
     * Ambil daftar customer yang terdaftar di layanan OPTI (flag id_layanan_optimalisasi = 1)
     */
    public function allOpti() {
        return $this->db->exec("SELECT * FROM tb_customer WHERE id_layanan_optimalisasi = 1 ORDER BY id_customer DESC");
    }

    /**
     * Cari customer berdasarkan id_customer
     */
    public function getById(int $idCustomer) {
        $this->load(array('id_customer = ?', $idCustomer));
        return $this->dry() ? null : $this;
    }

    /**
     * Simpan customer baru dengan mengaktifkan flag id_layanan_optimalisasi = 1
     */
    public function simpanBaru(array $data): int {
        $this->reset();
        $this->nmcustomer                 = trim($data['nmcustomer'] ?? ($data['nama_perusahaan'] ?? ''));
        $this->pt_cv                      = trim($data['pt_cv'] ?? 'PT');
        $this->alamatcustomer             = trim($data['alamatcustomer'] ?? ($data['alamat'] ?? ''));
        $this->emailcustomer              = trim($data['emailcustomer'] ?? ($data['email'] ?? ''));
        $this->notelpcustomer             = trim($data['notelpcustomer'] ?? ($data['telepon'] ?? ''));
        $this->contactperson              = trim($data['contactperson'] ?? ($data['pic'] ?? ''));
        $this->id_layanan                 = 1;
        $this->id_layanan_optimalisasi    = 1;
        $this->contactperson_opti         = trim($data['contactperson_opti'] ?? ($data['pic'] ?? ''));
        $this->nohpcontactperson_opti     = trim($data['nohpcontactperson_opti'] ?? ($data['telepon'] ?? ''));
        $this->showhide                   = 'show';
        $this->showhide_sekertaris        = 'show';
        $this->tglinput                   = date('Y-m-d H:i:s');
        $this->tglupdate                  = date('Y-m-d H:i:s');
        $this->save();
        return (int)$this->id_customer;
    }

    /**
     * Update data customer
     */
    public function updateData(int $idCustomer, array $data): bool {
        $this->load(array('id_customer = ?', $idCustomer));
        if ($this->dry()) {
            throw new \Exception("Customer dengan ID #{$idCustomer} tidak ditemukan.");
        }

        $this->nmcustomer                 = trim($data['nmcustomer'] ?? ($data['nama_perusahaan'] ?? $this->nmcustomer));
        $this->pt_cv                      = trim($data['pt_cv'] ?? $this->pt_cv);
        $this->alamatcustomer             = trim($data['alamatcustomer'] ?? ($data['alamat'] ?? $this->alamatcustomer));
        $this->emailcustomer              = trim($data['emailcustomer'] ?? ($data['email'] ?? $this->emailcustomer));
        $this->notelpcustomer             = trim($data['notelpcustomer'] ?? ($data['telepon'] ?? $this->notelpcustomer));
        $this->contactperson              = trim($data['contactperson'] ?? ($data['pic'] ?? $this->contactperson));
        $this->id_layanan_optimalisasi    = 1;
        $this->contactperson_opti         = trim($data['contactperson_opti'] ?? ($data['pic'] ?? $this->contactperson_opti));
        $this->nohpcontactperson_opti     = trim($data['nohpcontactperson_opti'] ?? ($data['telepon'] ?? $this->nohpcontactperson_opti));
        $this->tglupdate                  = date('Y-m-d H:i:s');
        $this->save();
        return true;
    }

    /**
     * Hapus customer jika tidak ada relasi order aktif
     */
    public function hapus(int $idCustomer): bool {
        $orders = $this->db->exec("SELECT COUNT(*) AS total FROM order_layanan WHERE id_customer = ?", array(1 => $idCustomer));
        $totalOrders = (int)($orders[0]['total'] ?? 0);
        if ($totalOrders > 0) {
            throw new \Exception("Customer tidak dapat dihapus karena memiliki {$totalOrders} order layanan yang tercatat.");
        }

        $this->load(array('id_customer = ?', $idCustomer));
        if (!$this->dry()) {
            $this->erase();
        }
        return true;
    }
}
