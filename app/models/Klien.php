<?php

/**
 * Model Klien
 * Menggunakan F3 SQL Mapper untuk berinteraksi dengan tabel 'klien'
 */
class Klien extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        // Hubungkan mapper dengan koneksi DB dan tabel 'klien'
        parent::__construct($db, 'klien');
    }

    /**
     * Mengambil seluruh daftar klien diurutkan dari yang terbaru
     */
    public function all() {
        return $this->find(array(), array('order' => 'id DESC'));
    }

    /**
     * Mencari data klien berdasarkan ID
     */
    public function getById($id) {
        $this->load(array('id = ?', $id));
        return $this->dry() ? null : $this;
    }

    /**
     * Menyimpan data klien baru
     */
    public function simpanBaru(array $data) {
        // Reset state mapper ke kondisi bersih/baru
        $this->reset();
        
        $this->nama_perusahaan = trim($data['nama_perusahaan'] ?? '');
        $this->pic             = trim($data['pic'] ?? '');
        $this->alamat          = trim($data['alamat'] ?? '');
        $this->telepon         = trim($data['telepon'] ?? '');
        $this->email           = trim($data['email'] ?? '');
        $this->created_at      = date('Y-m-d H:i:s');

        $this->save();
        return $this->id;
    }
}
