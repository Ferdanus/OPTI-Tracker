<?php

/**
 * Model OptiUserMap
 * Mengelola pemetaan user master pusat 'tb_arsipuser' ke role & divisi layanan OPTI
 * 
 * TODO: Konfirmasi ke admin/DBA arsipuser apakah boleh menambah kolom di tabel pusat atau tetap menggunakan tabel penghubung ini
 */
class OptiUserMap extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_user_map');
    }

    /**
     * Ambil mapping role berdasarkan ID user pusat
     */
    public function getByUserId(int $idUser) {
        $this->load(array('id_user = ?', $idUser));
        return $this->dry() ? null : $this;
    }

    /**
     * Set atau update role OPTI untuk user tertentu
     */
    public function setRole(int $idUser, string $roleOpti, string $jenisLayananOpti = 'semua') {
        $this->load(array('id_user = ?', $idUser));
        if ($this->dry()) {
            $this->reset();
            $this->id_user            = $idUser;
            $this->role_opti          = $roleOpti;
            $this->jenis_layanan_opti = $jenisLayananOpti;
            $this->is_active          = 1;
            $this->created_at         = date('Y-m-d H:i:s');
        } else {
            $this->role_opti          = $roleOpti;
            $this->jenis_layanan_opti = $jenisLayananOpti;
        }
        $this->save();
        return $this->id;
    }
}
