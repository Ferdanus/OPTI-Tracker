<?php

/**
 * Controller untuk mengelola Kontrak PKS (Perjanjian Kerja Sama)
 * Dilengkapi Guard Permission (Input/Edit: Admin Kontrak & Superadmin; View: Internal Balai)
 * 
 * TODO: Konfirmasi posisi kontrak dalam alur: apakah PKS ditandatangani sebelum PO terbit,
 * sesudah PO terbit, atau paralel (1 order = 1 PO = 1 kontrak).
 */
class KontrakController extends Controller {

    /**
     * Menampilkan daftar semua kontrak PKS
     * Route: GET /kontrak
     */
    public function index($f3) {
        $this->requirePermission('kontrak:view', '/po');

        $kontrakModel = new KontrakPks($this->db);
        $daftarKontrak = $kontrakModel->allWithRelasi();

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();

        $f3->set('daftar_kontrak', $daftarKontrak);
        $f3->set('mask_client_name', $maskEnabled);

        $this->render('kontrak/index.html', 'Daftar Kontrak PKS - OPTI Tracker', 'kontrak');
    }

    /**
     * Menampilkan form input kontrak baru
     * Route: GET /kontrak/tambah
     */
    public function tambah($f3) {
        $this->requirePermission('kontrak:create', '/kontrak');

        $daftarPo = $this->db->exec(
            "SELECT p.id, p.nomor_po, o.judul_kegiatan, cust.nmcustomer AS nama_perusahaan 
             FROM po p 
             JOIN order_layanan o ON p.order_id = o.id 
             JOIN tb_customer cust ON o.id_customer = cust.id_customer 
             LEFT JOIN kontrak_pks kp ON p.id = kp.po_id 
             WHERE kp.id IS NULL"
        );

        if (count($daftarPo) === 0) {
            $this->setFlashError('Seluruh Petunjuk Operasional (PO) yang ada sudah memiliki Kontrak PKS.');
            $f3->reroute('/kontrak');
            return;
        }

        $f3->set('kontrak', null);
        $f3->set('daftar_po', $daftarPo);
        $this->render('kontrak/form.html', 'Input Kontrak PKS Baru - OPTI Tracker', 'kontrak');
    }

    /**
     * Memproses penyimpanan kontrak PKS baru
     * Route: POST /kontrak/simpan
     */
    public function simpan($f3) {
        $this->requirePermission('kontrak:create', '/kontrak');

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
        $nomorVa                   = trim($post['nomor_va'] ?? '');
        $tanggalTtd                = trim($post['tanggal_ttd'] ?? '');
        $statusTtd                 = trim($post['status_ttd'] ?? 'belum');

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
                'nomor_va'                      => $nomorVa,
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

    /**
     * Menampilkan form edit kontrak PKS
     * Route: GET /kontrak/@id/edit
     */
    public function edit($f3, $params) {
        $this->requirePermission('kontrak:edit', '/kontrak');

        $id = (int)($params['id'] ?? 0);
        $kontrakModel = new KontrakPks($this->db);
        $kontrak = $kontrakModel->getById($id);

        if (!$kontrak) {
            $this->setFlashError("Kontrak PKS #{$id} tidak ditemukan.");
            $f3->reroute('/kontrak');
            return;
        }

        $daftarPo = $this->db->exec(
            "SELECT p.id, p.nomor_po, o.judul_kegiatan, cust.nmcustomer AS nama_perusahaan 
             FROM po p 
             JOIN order_layanan o ON p.order_id = o.id 
             JOIN tb_customer cust ON o.id_customer = cust.id_customer 
             WHERE p.id = ?",
            array(1 => $kontrak->po_id)
        );

        $f3->set('kontrak', $kontrak->cast());
        $f3->set('daftar_po', $daftarPo);
        $this->render('kontrak/form.html', 'Edit Kontrak PKS - OPTI Tracker', 'kontrak');
    }

    /**
     * Memproses update data kontrak PKS
     * Route: POST /kontrak/@id/update
     */
    public function update($f3, $params) {
        $this->requirePermission('kontrak:edit', '/kontrak');

        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

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
        $nomorVa                   = trim($post['nomor_va'] ?? '');
        $tanggalTtd                = trim($post['tanggal_ttd'] ?? '');
        $statusTtd                 = trim($post['status_ttd'] ?? 'belum');

        if (empty($nomorPksKlien) || empty($nomorPksBbspjis) || empty($namaPenandatanganKlien) || empty($namaPenandatanganBbspjis)) {
            $this->setFlashError('Semua isian bertanda bintang (*) wajib diisi!');
            $f3->reroute("/kontrak/{$id}/edit");
            return;
        }

        try {
            $kontrakModel = new KontrakPks($this->db);
            $kontrakModel->updateData($id, array(
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
                'nomor_va'                      => $nomorVa,
                'tanggal_ttd'                   => $tanggalTtd ?: null,
                'status_ttd'                    => $statusTtd
            ));

            $this->setFlashSuccess("Kontrak PKS #{$id} berhasil diperbarui.");
            $f3->reroute('/kontrak');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui Kontrak PKS: ' . $e->getMessage());
            $f3->reroute("/kontrak/{$id}/edit");
        }
    }

    /**
     * Menghapus kontrak PKS
     * Route: POST /kontrak/@id/hapus
     */
    public function hapus($f3, $params) {
        $this->requirePermission('kontrak:edit', '/kontrak');

        $id = (int)($params['id'] ?? 0);

        try {
            $kontrakModel = new KontrakPks($this->db);
            $kontrakModel->hapus($id);

            $this->setFlashSuccess("Kontrak PKS #{$id} berhasil dihapus.");
            $f3->reroute('/kontrak');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menghapus Kontrak PKS: ' . $e->getMessage());
            $f3->reroute('/kontrak');
        }
    }
}
