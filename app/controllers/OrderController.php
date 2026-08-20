<?php

/**
 * Controller untuk mengelola Permohonan Order Layanan OPTI
 * Dilengkapi Guard Permission (Order: Admin Order & Superadmin; Approve: Pejabat & Superadmin)
 */
class OrderController extends Controller {

    /**
     * Menampilkan daftar semua order layanan
     * Route: GET /order
     */
    public function index($f3) {
        $this->requirePermission('order:view', '/po');

        $filterJenis  = $f3->get('GET.jenis_layanan') ?? '';
        $filterStatus = $f3->get('GET.status') ?? '';
        $search       = $f3->get('GET.q') ?? '';

        $orderModel = new OrderLayanan($this->db);
        $daftarOrder = $orderModel->allWithRelasi($filterJenis, $filterStatus, $search);

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();

        $f3->set('daftar_order', $daftarOrder);
        $f3->set('filter_jenis', $filterJenis);
        $f3->set('filter_status', $filterStatus);
        $f3->set('search_q', $search);
        $f3->set('mask_client_name', $maskEnabled);

        $this->render('order/index.html', 'Daftar Order Layanan - OPTI Tracker', 'order');
    }

    /**
     * Menampilkan form penambahan order baru
     * Route: GET /order/tambah
     */
    public function tambah($f3) {
        $this->requirePermission('order:create', '/order');

        $customerModel = new Customer($this->db);
        $daftarCustomer = $customerModel->all();

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $configSelulosa = $fieldConfigModel->getConfigForTim('selulosa');
        $configLingkungan = $fieldConfigModel->getConfigForTim('lingkungan');

        $userModel = new ArsipUser($this->db);
        $katimSelulosa = $userModel->getKetuaTim('selulosa');
        $katimLingkungan = $userModel->getKetuaTim('lingkungan');

        $f3->set('order', null);
        $f3->set('daftar_customer', $daftarCustomer);
        $f3->set('daftar_klien', $daftarCustomer);
        $f3->set('spm_list', OrderLayanan::$SPM_LIST);
        $f3->set('lab_internal_list', OrderLayanan::$LAB_INTERNAL_LIST);
        $f3->set('config_selulosa', $configSelulosa);
        $f3->set('config_lingkungan', $configLingkungan);
        $f3->set('katim_selulosa_nama', $katimSelulosa['nama_user'] ?? 'Andri Taufick Rizaluddin');
        $f3->set('katim_lingkungan_nama', $katimLingkungan['nama_user'] ?? 'Rina Masriani');

        $this->render('order/form.html', 'Tambah Order Layanan Baru - OPTI Tracker', 'order');
    }

    /**
     * Memproses penyimpanan order baru
     * Route: POST /order/simpan
     */
    public function simpan($f3) {
        $this->requirePermission('order:create', '/order');

        $post = $f3->get('POST');

        $idCustomer       = (int)($post['id_customer'] ?? ($post['klien_id'] ?? 0));
        $tanggalMasuk     = $post['tanggal_masuk'] ?? date('Y-m-d');
        $judulKegiatan    = trim($post['judul_kegiatan'] ?? '');
        $deskripsi        = trim($post['deskripsi'] ?? '');
        $spmLayanan       = trim($post['spm_layanan'] ?? 'Lainnya');
        $jenisLayananOpti = $post['jenis_layanan_opti'] ?? ($post['jenis_layanan'] ?? 'selulosa');
        $lokasiPelaksanaan= $post['lokasi_pelaksanaan'] ?? ($post['lokasi_uji'] ?? 'internal');
        $labInternal      = $post['lab_internal'] ?? null;
        $lokasiLapangan   = $post['lokasi_lapangan'] ?? null;

        // Spesifikasi Teknis Sampel
        $tipeDataSampel   = trim($post['tipe_data_sampel'] ?? '');
        $jenisSampel      = trim($post['jenis_sampel'] ?? '');
        $volumeBerat      = trim($post['volume_berat'] ?? '');
        $karakteristikSerat = trim($post['karakteristik_serat'] ?? '');
        $karakteristikKimia = trim($post['karakteristik_kimia'] ?? ($post['karakteristik_sampel'] ?? ''));

        $jumlahPekerjaan  = trim($post['jumlah_pekerjaan'] ?? '1 paket kegiatan');
        $estimasiBiaya    = (float)($post['estimasi_biaya'] ?? 0);

        if ($idCustomer <= 0 || empty($judulKegiatan)) {
            $this->setFlashError('Pilih customer dan isi judul kegiatan permohonan layanan.');
            $f3->reroute('/order/tambah');
            return;
        }

        try {
            $orderModel = new OrderLayanan($this->db);
            $orderId = $orderModel->simpanBaru(array(
                'id_customer'        => $idCustomer,
                'nomor_order'        => '',
                'tanggal_masuk'      => $tanggalMasuk,
                'judul_kegiatan'     => $judulKegiatan,
                'deskripsi'          => $deskripsi,
                'spm_layanan'        => $spmLayanan,
                'jenis_layanan_opti' => $jenisLayananOpti,
                'lokasi_pelaksanaan' => $lokasiPelaksanaan,
                'lab_internal'       => $labInternal,
                'lokasi_lapangan'    => $lokasiLapangan,
                'tipe_data_sampel'   => $tipeDataSampel,
                'jenis_sampel'       => $jenisSampel,
                'volume_berat'       => $volumeBerat,
                'karakteristik_serat'=> $karakteristikSerat,
                'karakteristik_kimia'=> $karakteristikKimia,
                'jumlah_pekerjaan'   => $jumlahPekerjaan,
                'estimasi_biaya'     => $estimasiBiaya
            ));

            $this->setFlashSuccess('Order Layanan baru berhasil didaftarkan.');
            $f3->reroute('/order');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan order layanan: ' . $e->getMessage());
            $f3->reroute('/order/tambah');
        }
    }

    /**
     * Menampilkan form edit order
     * Route: GET /order/@id/edit
     */
    public function edit($f3, $params) {
        $this->requirePermission('order:edit', '/order');

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getById($id);

        if (!$order) {
            $this->setFlashError("Order Layanan dengan ID #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $customerModel = new Customer($this->db);
        $daftarCustomer = $customerModel->all();

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $configSelulosa = $fieldConfigModel->getConfigForTim('selulosa');
        $configLingkungan = $fieldConfigModel->getConfigForTim('lingkungan');

        $userModel = new ArsipUser($this->db);
        $katimSelulosa = $userModel->getKetuaTim('selulosa');
        $katimLingkungan = $userModel->getKetuaTim('lingkungan');

        $f3->set('order', $order->cast());
        $f3->set('daftar_customer', $daftarCustomer);
        $f3->set('daftar_klien', $daftarCustomer);
        $f3->set('spm_list', OrderLayanan::$SPM_LIST);
        $f3->set('lab_internal_list', OrderLayanan::$LAB_INTERNAL_LIST);
        $f3->set('config_selulosa', $configSelulosa);
        $f3->set('config_lingkungan', $configLingkungan);
        $f3->set('katim_selulosa_nama', $katimSelulosa['nama_user'] ?? 'Andri Taufick Rizaluddin');
        $f3->set('katim_lingkungan_nama', $katimLingkungan['nama_user'] ?? 'Rina Masriani');

        $this->render('order/form.html', 'Edit Order Layanan - OPTI Tracker', 'order');
    }

    /**
     * Memproses update data order
     * Route: POST /order/@id/update
     */
    public function update($f3, $params) {
        $this->requirePermission('order:edit', '/order');

        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $idCustomer       = (int)($post['id_customer'] ?? ($post['klien_id'] ?? 0));
        $tanggalMasuk     = $post['tanggal_masuk'] ?? date('Y-m-d');
        $judulKegiatan    = trim($post['judul_kegiatan'] ?? '');
        $deskripsi        = trim($post['deskripsi'] ?? '');
        $spmLayanan       = trim($post['spm_layanan'] ?? 'Lainnya');
        $jenisLayananOpti = $post['jenis_layanan_opti'] ?? ($post['jenis_layanan'] ?? 'selulosa');
        $lokasiPelaksanaan= $post['lokasi_pelaksanaan'] ?? ($post['lokasi_uji'] ?? 'internal');
        $labInternal      = $post['lab_internal'] ?? null;
        $lokasiLapangan   = $post['lokasi_lapangan'] ?? null;

        // Spesifikasi Teknis Sampel
        $tipeDataSampel   = trim($post['tipe_data_sampel'] ?? '');
        $jenisSampel      = trim($post['jenis_sampel'] ?? '');
        $volumeBerat      = trim($post['volume_berat'] ?? '');
        $karakteristikSerat = trim($post['karakteristik_serat'] ?? '');
        $karakteristikKimia = trim($post['karakteristik_kimia'] ?? ($post['karakteristik_sampel'] ?? ''));

        $jumlahPekerjaan  = trim($post['jumlah_pekerjaan'] ?? '1 paket kegiatan');
        $estimasiBiaya    = (float)($post['estimasi_biaya'] ?? 0);

        if (empty($judulKegiatan)) {
            $this->setFlashError('Judul kegiatan permohonan layanan wajib diisi.');
            $f3->reroute("/order/{$id}/edit");
            return;
        }

        try {
            $orderModel = new OrderLayanan($this->db);
            $orderModel->updateData($id, array(
                'id_customer'        => $idCustomer,
                'tanggal_masuk'      => $tanggalMasuk,
                'judul_kegiatan'     => $judulKegiatan,
                'deskripsi'          => $deskripsi,
                'spm_layanan'        => $spmLayanan,
                'jenis_layanan_opti' => $jenisLayananOpti,
                'lokasi_pelaksanaan' => $lokasiPelaksanaan,
                'lab_internal'       => $labInternal,
                'lokasi_lapangan'    => $lokasiLapangan,
                'tipe_data_sampel'   => $tipeDataSampel,
                'jenis_sampel'       => $jenisSampel,
                'volume_berat'       => $volumeBerat,
                'karakteristik_serat'=> $karakteristikSerat,
                'karakteristik_kimia'=> $karakteristikKimia,
                'jumlah_pekerjaan'   => $jumlahPekerjaan,
                'estimasi_biaya'     => $estimasiBiaya
            ));

            $this->setFlashSuccess("Data Order Layanan #{$id} berhasil diperbarui.");
            $f3->reroute('/order');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui order: ' . $e->getMessage());
            $f3->reroute("/order/{$id}/edit");
        }
    }

    /**
     * Menyetujui order layanan dan menerbitkan PO otomatis
     * Route: POST /order/@id/approve
     * TODO: Konfirmasi ke user asli apakah approver juga boleh reject dengan catatan revisi (bukan cuma ya/tidak).
     */
    public function approve($f3, $params) {
        $this->requirePermission('po:approve', '/order');

        $id = (int)($params['id'] ?? 0);
        $nomorPo = trim($f3->get('POST.nomor_po') ?? '');
        $biaya   = (float)($f3->get('POST.biaya') ?? 0);

        try {
            $orderModel = new OrderLayanan($this->db);
            $hasil = $orderModel->approve($id, $nomorPo, $biaya);

            $this->setFlashSuccess(
                "Order #{$id} disetujui! Dokumen PO berhasil diterbitkan dengan Nomor: <strong>{$hasil['nomor_po']}</strong>."
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
     * TODO: Konfirmasi ke user asli apakah approver juga boleh reject dengan catatan revisi (bukan cuma ya/tidak).
     */
    public function tolak($f3, $params) {
        $this->requirePermission('po:approve', '/order');

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

    /**
     * Menghapus order layanan
     * Route: POST /order/@id/hapus
     */
    public function hapus($f3, $params) {
        $this->requirePermission('order:edit', '/order');

        $id = (int)($params['id'] ?? 0);

        try {
            $orderModel = new OrderLayanan($this->db);
            $orderModel->hapus($id);

            $this->setFlashSuccess("Order Layanan #{$id} berhasil dihapus.");
            $f3->reroute('/order');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menghapus order: ' . $e->getMessage());
            $f3->reroute('/order');
        }
    }
}
