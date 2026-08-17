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
        $this->render('order/index.html', 'Daftar Order Layanan - Mini OPTI Tracker', 'order');
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
        $this->render('order/form.html', 'Tambah Order Layanan - Mini OPTI Tracker', 'order');
    }

    /**
     * Memproses penyimpanan order layanan baru
     * Route: POST /order/simpan
     */
    public function simpan($f3) {
        $post = $f3->get('POST');

        $klienId         = (int)($post['klien_id'] ?? 0);
        $nomorOrder      = trim($post['nomor_order'] ?? '');
        $tanggalMasuk    = trim($post['tanggal_masuk'] ?? '');
        $judulKegiatan   = trim($post['judul_kegiatan'] ?? '');
        $jenisLayanan    = trim($post['jenis_layanan'] ?? '');
        $jumlahPekerjaan = trim($post['jumlah_pekerjaan'] ?? '');
        $estimasiBiaya   = (float)($post['estimasi_biaya'] ?? 0);
        $deskripsi       = trim($post['deskripsi'] ?? '');

        // Validasi dasar
        if ($klienId <= 0 || empty($tanggalMasuk) || empty($judulKegiatan) || empty($jenisLayanan) || empty($jumlahPekerjaan)) {
            $this->setFlashError('Semua isian bertanda bintang (*) wajib diisi!');
            $f3->reroute('/order/tambah');
            return;
        }

        if (!in_array($jenisLayanan, array('selulosa', 'lingkungan'))) {
            $this->setFlashError('Jenis layanan tidak valid!');
            $f3->reroute('/order/tambah');
            return;
        }

        try {
            $orderModel = new OrderLayanan($this->db);
            $orderModel->simpanBaru(array(
                'klien_id'         => $klienId,
                'nomor_order'      => $nomorOrder,
                'tanggal_masuk'    => $tanggalMasuk,
                'judul_kegiatan'   => $judulKegiatan,
                'jenis_layanan'    => $jenisLayanan,
                'jumlah_pekerjaan' => $jumlahPekerjaan,
                'estimasi_biaya'   => $estimasiBiaya,
                'deskripsi'        => $deskripsi
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
