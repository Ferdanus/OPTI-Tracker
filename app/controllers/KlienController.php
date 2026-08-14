<?php

/**
 * Controller untuk mengelola data Klien (Master Data)
 */
class KlienController extends Controller {

    /**
     * Menampilkan daftar semua klien
     * Route: GET /klien
     */
    public function index($f3) {
        $klienModel = new Klien($this->db);
        $daftarKlien = $klienModel->all();

        $f3->set('daftar_klien', $daftarKlien);
        $this->render('klien/index.htm', 'Daftar Klien - Mini OPTI Tracker', 'klien');
    }

    /**
     * Menampilkan form penambahan klien baru
     * Route: GET /klien/tambah
     */
    public function tambah($f3) {
        $this->render('klien/form.htm', 'Tambah Klien Baru - Mini OPTI Tracker', 'klien');
    }

    /**
     * Memproses penyimpanan data klien baru
     * Route: POST /klien/simpan
     */
    public function simpan($f3) {
        $post = $f3->get('POST');

        $namaPerusahaan = trim($post['nama_perusahaan'] ?? '');
        $pic            = trim($post['pic'] ?? '');
        $alamat         = trim($post['alamat'] ?? '');
        $telepon        = trim($post['telepon'] ?? '');
        $email          = trim($post['email'] ?? '');

        // Validasi dasar
        if (empty($namaPerusahaan)) {
            $this->setFlashError('Nama Perusahaan wajib diisi!');
            $f3->reroute('/klien/tambah');
            return;
        }

        try {
            $klienModel = new Klien($this->db);
            $klienModel->simpanBaru(array(
                'nama_perusahaan' => $namaPerusahaan,
                'pic'             => $pic,
                'alamat'          => $alamat,
                'telepon'         => $telepon,
                'email'           => $email
            ));

            $this->setFlashSuccess('Data klien "' . htmlspecialchars($namaPerusahaan) . '" berhasil ditambahkan.');
            $f3->reroute('/klien');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan data klien: ' . $e->getMessage());
            $f3->reroute('/klien/tambah');
        }
    }
}
