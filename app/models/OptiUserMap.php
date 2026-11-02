<?php

/**
 * Model OptiUserMap
 * Mengelola pemetaan user master pusat 'tb_arsipuser' ke role & divisi layanan (OPTI & PO)
 * Mendukung multi-layanan, multi-role per user, pemisahan akses_opti / akses_po, dan soft-hide (status_tampil).
 */
class OptiUserMap extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_user_map');
    }

    /**
     * Ambil mapping role aktif berdasarkan ID user pusat
     */
    public function getByUserId(int $idUser) {
        $this->load(array("id_user = ? AND status_tampil = 'show'", $idUser));
        return $this->dry() ? null : $this;
    }

    /**
     * Ambil seluruh daftar role aktif yang dimiliki oleh seorang user
     */
    public function getAllRolesByUserId(int $idUser): array {
        return $this->db->exec(
            "SELECT * FROM opti_user_map WHERE id_user = ? AND status_tampil = 'show' AND is_active = 1 ORDER BY id ASC",
            array(1 => $idUser)
        );
    }

    /**
     * Set atau update role untuk user tertentu pada layanan tertentu
     */
    public function setRole(int $idUser, string $role, string $jenisLayanan = 'semua', int $aksesOpti = 1, int $aksesPo = 1) {
        $this->load(array('id_user = ? AND jenis_layanan = ? AND role = ?', $idUser, $jenisLayanan, $role));
        if ($this->dry()) {
            $this->reset();
            $this->id_user            = $idUser;
            $this->jenis_layanan      = $jenisLayanan;
            $this->role               = $role;
            $this->akses_opti         = $aksesOpti;
            $this->akses_po           = $aksesPo;
            $this->is_active          = 1;
            $this->status_tampil      = 'show';
            $this->created_at         = date('Y-m-d H:i:s');
        } else {
            $this->jenis_layanan      = $jenisLayanan;
            $this->role               = $role;
            $this->akses_opti         = $aksesOpti;
            $this->akses_po           = $aksesPo;
            $this->is_active          = 1;
            $this->status_tampil      = 'show';
        }
        $this->save();
        return $this->id;
    }

    /**
     * Soft-hide role pengguna (tidak menghapus baris dari database demi menjaga histori transaksi)
     */
    public function softHideByUser(int $idUser, ?string $jenisLayanan = null, ?string $role = null): void {
        if ($jenisLayanan !== null && $role !== null) {
            $this->db->exec(
                "UPDATE opti_user_map SET is_active = 0, status_tampil = 'hide' WHERE id_user = ? AND jenis_layanan = ? AND role = ?",
                array(1 => $idUser, 2 => $jenisLayanan, 3 => $role)
            );
        } else {
            $this->db->exec(
                "UPDATE opti_user_map SET is_active = 0, status_tampil = 'hide' WHERE id_user = ?",
                array(1 => $idUser)
            );
        }
    }
}
