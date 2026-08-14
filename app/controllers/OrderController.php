<?php

/**
 * Controller untuk mengelola Order Layanan
 */
class OrderController extends Controller {

    /**
     * Menampilkan daftar semua order layanan
     * Route: GET /order
     */
    public function index($f3) {
        $orderModel = new OrderLayanan($this->db);
        $daftarOrder = $orderModel->allWithKlien();

        $f3->set('daftar_order', $daftarOrder);
        $this->render('order/index.htm', 'Daftar Order Layanan - Mini OPTI Tracker', 'order');
    }

    /**
     * Menampilkan form penambahan order layanan baru
     * Route: GET /order/tambah
     */
    public function tambah($f3) {
        $klienModel = new Klien($this->db);
        $daftarKlien = $klienModel->all();

        // Jika belum ada klien sama sekali, beri peringatan
        if (count($daftarKlien) === 0) {
            $this->setFlashError('Harap tambahkan data Klien terlebih dahulu sebelum membuat Order Layanan.');
            $f3->reroute('/klien/tambah');
            return;
        }

        $f3->set('daftar_klien', $daftarKlien);
        $this->render('order/form.htm', 'Tambah Order Layanan - Mini OPTI Tracker', 'order');
    }

    /**
     * Memproses penyimpanan order layanan baru
     * Route: POST /order/simpan
     */
    public function simpan($f3) {
        $post = $f3->get('POST');

        $klienId       = (int)($post['klien_id'] ?? 0);
        $judulKegiatan = trim($post['judul_kegiatan'] ?? '');
        $deskripsi     = trim($post['deskripsi'] ?? '');
        $tanggalMasuk  = trim($post['tanggal_masuk'] ?? '');

        // Validasi dasar
        if ($klienId <= 0 || empty($judulKegiatan) || empty($tanggalMasuk)) {
            $this->setFlashError('Klien, Judul Kegiatan, dan Tanggal Masuk wajib diisi!');
            $f3->reroute('/order/tambah');
            return;
        }

        try {
            $orderModel = new OrderLayanan($this->db);
            $orderModel->simpanBaru(array(
                'klien_id'       => $klienId,
                'judul_kegiatan' => $judulKegiatan,
                'deskripsi'      => $deskripsi,
                'tanggal_masuk'  => $tanggalMasuk
            ));

            $this->setFlashSuccess('Order Layanan baru berhasil dibuat dengan status "baru".');
            $f3->reroute('/order');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal membuat order: ' . $e->getMessage());
            $f3->reroute('/order/tambah');
        }
    }

    /**
     * Menyetujui order layanan dan membuat PO otomatis
     * Route: POST /order/@id/approve
     */
    public function approve($f3, $params) {
        $id = (int)($params['id'] ?? 0);

        try {
            $orderModel = new OrderLayanan($this->db);
            $hasil = $orderModel->approve($id);

            $this->setFlashSuccess(
                "Order #{$id} disetujui! Dokumen PO berhasil dibuat otomatis dengan Nomor: <strong>{$hasil['nomor_po']}</strong>."
            );
            $f3->reroute('/order');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyetujui order: ' . $e->getMessage());
            $f3->reroute('/order');
        }
    }

    /**
     * Menolak order layanan
     * Route: POST /order/@id/tolak
     */
    public function tolak($f3, $params) {
        $id = (int)($params['id'] ?? 0);

        try {
            $orderModel = new OrderLayanan($this->db);
            $orderModel->tolak($id);

            $this->setFlashSuccess("Order #{$id} telah ditolak.");
            $f3->reroute('/order');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menolak order: ' . $e->getMessage());
            $f3->reroute('/order');
        }
    }
}
