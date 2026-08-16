<?php

/**
 * Controller untuk mengelola Kontrak PKS (Perjanjian Kerja Sama)
 */
class KontrakController extends Controller {

    /**
     * Menampilkan daftar semua kontrak PKS
     * Route: GET /kontrak
     */
    public function index($f3) {
        $kontrakModel = new KontrakPks($this->db);
        $daftarKontrak = $kontrakModel->allWithRelasi();

        $f3->set('daftar_kontrak', $daftarKontrak);
        $this->render('kontrak/index.htm', 'Daftar Kontrak PKS - OPTI Tracker', 'kontrak');
    }

    /**
     * Menampilkan form input kontrak baru
     * Route: GET /kontrak/tambah
     */
    public function tambah($f3) {
        // Ambil daftar PO yang belum memiliki kontrak PKS
        $daftarPo = $this->db->exec(
            "SELECT p.id, p.nomor_po, o.judul_kegiatan, k.nama_perusahaan 
             FROM po p 
             JOIN order_layanan o ON p.order_id = o.id 
             JOIN klien k ON o.klien_id = k.id 
             LEFT JOIN kontrak_pks kp ON p.id = kp.po_id 
             WHERE kp.id IS NULL"
        );

        if (count($daftarPo) === 0) {
            $this->setFlashError('Seluruh Petunjuk Operasional (PO) yang ada sudah memiliki Kontrak PKS.');
            $f3->reroute('/kontrak');
            return;
        }

        $f3->set('daftar_po', $daftarPo);
        $this->render('kontrak/form.htm', 'Input Kontrak PKS Baru - OPTI Tracker', 'kontrak');
    }

    /**
     * Memproses penyimpanan kontrak PKS baru
     * Route: POST /kontrak/simpan
     */
    public function simpan($f3) {
        $post = $f3->get('POST');

        $poId                      = (int)($post['po_id'] ?? 0);
        $nomorPksKlien             = trim($post['nomor_pks_klien'] ?? '');
        $nomorPksBbspjis           = trim($post['nomor_pks_bbspjis'] ?? '');
        $namaPenandatanganKlien    = trim($post['nama_penandatangan_klien'] ?? '');
        $jabatanPenandatanganKlien = trim($post['jabatan_penandatangan_klien'] ?? '');
        $namaPenandatanganBbspjis  = trim($post['nama_penandatangan_bbspjis'] ?? '');
        $jabatanPenandatanganBbspjis = trim($post['jabatan_penandatangan_bbspjis'] ?? '');
        $ruangLingkup              = trim($post['ruang_lingkup'] ?? '');
        $targetMulai               = trim($post['target_mulai'] ?? '');
        $targetSelesai             = trim($post['target_selesai'] ?? '');
        $nilaiKontrak              = (float)($post['nilai_kontrak'] ?? 0);
        $ketentuanPembayaran       = trim($post['ketentuan_pembayaran'] ?? '');
        $tanggalTtd                = trim($post['tanggal_ttd'] ?? '');
        $statusTtd                 = trim($post['status_ttd'] ?? 'belum');

        // Validasi isian wajib
        if ($poId <= 0 || empty($nomorPksKlien) || empty($nomorPksBbspjis) || empty($namaPenandatanganKlien) || empty($namaPenandatanganBbspjis) || empty($targetMulai) || empty($targetSelesai)) {
            $this->setFlashError('Semua isian bertanda bintang (*) wajib diisi!');
            $f3->reroute('/kontrak/tambah');
            return;
        }

        try {
            $kontrakModel = new KontrakPks($this->db);
            $kontrakModel->simpanBaru(array(
                'po_id'                         => $poId,
                'nomor_pks_klien'                => $nomorPksKlien,
                'nomor_pks_bbspjis'             => $nomorPksBbspjis,
                'nama_penandatangan_klien'      => $namaPenandatanganKlien,
                'jabatan_penandatangan_klien'   => $jabatanPenandatanganKlien,
                'nama_penandatangan_bbspjis'    => $namaPenandatanganBbspjis,
                'jabatan_penandatangan_bbspjis' => $jabatanPenandatanganBbspjis,
                'ruang_lingkup'                 => $ruangLingkup,
                'target_mulai'                  => $targetMulai,
                'target_selesai'                => $targetSelesai,
                'nilai_kontrak'                 => $nilaiKontrak,
                'ketentuan_pembayaran'          => $ketentuanPembayaran,
                'tanggal_ttd'                   => $tanggalTtd ?: null,
                'status_ttd'                    => $statusTtd
            ));

            $this->setFlashSuccess('Kontrak PKS baru berhasil ditambahkan.');
            $f3->reroute('/kontrak');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan Kontrak PKS: ' . $e->getMessage());
            $f3->reroute('/kontrak/tambah');
        }
    }
}
