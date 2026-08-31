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

        $this->render('order/index.html', 'Daftar Order Layanan', 'order');
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

        $this->render('order/form.html', 'Tambah Order Layanan Baru', 'order');
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

        $this->render('order/form.html', 'Edit Order Layanan', 'order');
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
     * Menampilkan detail lengkap satu order layanan
     * Route: GET /order/@id
     */
    public function detail($f3, $params) {
        $this->requirePermission('order:view', '/order');

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $tinjauan = $orderModel->getTinjauanKelayakan($id);
        $proposal = ($order['jenis_layanan_opti'] === 'selulosa') ? $orderModel->getProposalRiset($id) : null;
        $kalkulasiLingkungan = ($order['jenis_layanan_opti'] === 'lingkungan') ? $orderModel->getKalkulasiLingkungan($id) : [];

        $spModel = new SuratPenawaran($this->db);
        $penawaran = $spModel->getByOrderId($id);

        $invModel = new OptiInvoice($this->db);
        $invoices = $invModel->getByOrderId($id);

        $payModel = new OptiPembayaran($this->db);
        $riwayatBayar = $payModel->getByOrderId($id);
        $rekapKeuangan = $payModel->getRekapKeuanganOrder($id);

        $poDetail = null;
        $kontrakPks = null;
        $jadwalKerja = [];
        if (!empty($order['po_id'])) {
            $poModel = new Po($this->db);
            $poDetail = $poModel->getDetail((int)$order['po_id']);
            $kontrakModel = new KontrakPks($this->db);
            $kontrakPks = $kontrakModel->getByPoId((int)$order['po_id']);
            $jadwalModel = new PoJadwalKerja($this->db);
            $jadwalKerja = $jadwalModel->getByPoId((int)$order['po_id']);
        }

        $bastModel = new OptiBast($this->db);
        $bast = $bastModel->getByOrderId($id);

        $customerModel = new Customer($this->db);
        $daftarCustomer = $customerModel->all();

        $suratMasuk = null;
        if (!empty($order['id_surat_masuk']) && $this->dbSekretariat) {
            try {
                $smRows = $this->dbSekretariat->exec("SELECT * FROM surat_masuk WHERE id = ?", array(1 => (int)$order['id_surat_masuk']));
                $suratMasuk = $smRows[0] ?? null;
            } catch (\Exception $e) {
                // Ignore DB error
            }
        }

        $f3->set('order', $order);
        $f3->set('surat_masuk', $suratMasuk);
        $f3->set('tinjauan', $tinjauan);
        $f3->set('proposal', $proposal);
        $f3->set('kalkulasi_lingkungan', $kalkulasiLingkungan);
        $f3->set('penawaran', $penawaran);
        $f3->set('invoices', $invoices);
        $f3->set('riwayat_bayar', $riwayatBayar);
        $f3->set('rekap_keuangan', $rekapKeuangan);
        $f3->set('po_detail', $poDetail);
        $f3->set('kontrak_pks', $kontrakPks);
        $f3->set('jadwal_kerja', $jadwalKerja);
        $f3->set('bast', $bast);
        $f3->set('daftar_customer', $daftarCustomer);

        $this->render('order/detail.html', "Detail Order #{$order['nomor_order']}", 'order');
    }

    /**
     * Update data pelanggan/klien langsung dari halaman Detail Order
     * Route: POST /order/@id/klien/update
     */
    public function updateCustomer($f3, $params) {
        $this->requirePermission('order:edit', '/order');

        $orderId = (int)($params['id'] ?? 0);
        $post    = $f3->get('POST');

        try {
            $orderModel = new OrderLayanan($this->db);
            $order = $orderModel->getDetail($orderId);
            if (!$order) {
                throw new \Exception("Order #{$orderId} tidak ditemukan.");
            }

            $customerModel = new Customer($this->db);
            $pilihCustomerId = (int)($post['pilih_id_customer'] ?? 0);

            if ($pilihCustomerId > 0 && $pilihCustomerId !== (int)$order['id_customer']) {
                // Re-link order to a different existing customer
                $this->db->exec(
                    "UPDATE order_layanan SET id_customer = ? WHERE id = ?",
                    array(1 => $pilihCustomerId, 2 => $orderId)
                );
                $targetCustomerId = $pilihCustomerId;
            } else {
                $targetCustomerId = (int)$order['id_customer'];
            }

            // Update details on the target customer
            $namaPerusahaan = trim($post['nmcustomer'] ?? '');
            $ptCv           = trim($post['pt_cv'] ?? 'PT');
            $pic            = trim($post['pic'] ?? '');
            $telepon        = trim($post['telepon'] ?? '');
            $email          = trim($post['email'] ?? '');
            $alamat         = trim($post['alamat'] ?? '');

            if (!empty($namaPerusahaan)) {
                $customerModel->updateData($targetCustomerId, array(
                    'nmcustomer'             => $namaPerusahaan,
                    'pt_cv'                  => $ptCv,
                    'contactperson'          => $pic,
                    'contactperson_opti'     => $pic,
                    'notelpcustomer'         => $telepon,
                    'nohpcontactperson_opti' => $telepon,
                    'emailcustomer'          => $email,
                    'alamatcustomer'         => $alamat
                ));
            }

            $this->setFlashSuccess("Data pelanggan / klien untuk Order #{$order['nomor_order']} berhasil diperbarui!");
        } catch (\Exception $e) {
            $this->setFlashError("Gagal memperbarui data klien: " . $e->getMessage());
        }

        $f3->reroute("/order/{$orderId}");
    }

    /**
     * Menampilkan formulir Tinjauan Kelayakan Permintaan (Kartu Kendali ISO)
     * Route: GET /order/@id/tinjauan
     */
    public function tinjauan($f3, $params) {
        $this->requirePermission('order:tinjau', '/order');

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $tinjauan = $orderModel->getTinjauanKelayakan($id);

        $f3->set('order', $order);
        $f3->set('tinjauan', $tinjauan);

        $this->render('order/tinjauan_kelayakan.html', "Tinjauan Kelayakan Order #{$order['nomor_order']}", 'order');
    }

    /**
     * Memproses penyimpanan Tinjauan Kelayakan Permintaan
     * Route: POST /order/@id/tinjauan
     */
    public function tinjauanPost($f3, $params) {
        $this->requirePermission('order:tinjau', '/order');

        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        try {
            $orderModel = new OrderLayanan($this->db);
            $hasil = $orderModel->simpanTinjauanKelayakan($id, $post, $userId);

            if ($hasil['keputusan'] === 'dapat_dilaksanakan') {
                $this->setFlashSuccess("Tinjauan Kelayakan ISO berhasil disimpan. Status: <strong>Dapat Dilaksanakan</strong>. Silakan lanjutkan ke penentuan biaya/proposal.");
            } else {
                $this->setFlashWarning("Tinjauan Kelayakan ISO disimpan. Status: <strong>Tidak Dapat Dilaksanakan (Ditolak)</strong>. Order telah dihentikan.");
            }

            $f3->reroute("/order/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan tinjauan kelayakan: ' . $e->getMessage());
            $f3->reroute("/order/{$id}/tinjauan");
        }
    }

    /**
     * Menampilkan form Rancangan Percobaan (Rancop) & Anggaran Riset (Divisi Selulosa)
     * Route: GET /order/@id/biaya-proposal & GET /order/@id/rancop-selulosa
     */
    public function biayaProposal($f3, $params) {
        $this->requirePermission('order:proposal', '/order');

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $proposal = $orderModel->getProposalRiset($id);
        $daftarPic = OrderLayanan::getPICSpesialisasiList($this->db);

        $f3->set('order', $order);
        $f3->set('proposal', $proposal);
        $f3->set('daftar_pic', $daftarPic);

        $this->render('order/rancop_selulosa.html', "Rancangan Percobaan (Rancop) Selulosa", 'order');
    }

    public function rancopSelulosa($f3, $params) {
        return $this->biayaProposal($f3, $params);
    }

    /**
     * Memproses simpan Rancangan Percobaan & RAB dinamis (Divisi Selulosa)
     * Route: POST /order/@id/biaya-proposal & POST /order/@id/rancop-selulosa
     */
    public function biayaProposalPost($f3, $params) {
        $this->requirePermission('order:proposal', '/order');

        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        // Tentukan status rancop & status proposal
        $statusRancop = $post['status_rancop'] ?? 'draft';
        if (($post['action_btn'] ?? '') === 'save_deal') {
            $statusRancop = 'deal';
        }

        // Parse list tahapan eksperimen
        $tahapanList = [];
        $totalBiayaAktif = 0.0;
        if (!empty($post['tahapan']) && is_array($post['tahapan'])) {
            foreach ($post['tahapan'] as $stg) {
                if (!empty($stg['nama'])) {
                    $isActive = !empty($stg['is_active']);
                    $biayaStg = (float)($stg['biaya'] ?? 0);
                    if ($isActive) {
                        $totalBiayaAktif += $biayaStg;
                    }
                    $tahapanList[] = [
                        'nama'       => trim($stg['nama']),
                        'keterangan' => trim($stg['keterangan'] ?? ''),
                        'biaya'      => $biayaStg,
                        'is_active'  => $isActive
                    ];
                }
            }
        }

        // Jika tidak ada input tahapan dinamis, gunakan input total manual
        if (empty($tahapanList)) {
            $totalBiayaAktif = (float)($post['estimasi_total_biaya'] ?? 0);
        }

        // Status proposal F3 kompatibilitas
        $statusProposal = 'draft';
        if ($statusRancop === 'deal') {
            $statusProposal = 'disetujui_pimpinan';
        } elseif ($statusRancop === 'diskusi') {
            $statusProposal = 'diajukan';
        }

        $data = [
            'pic_penyusun_id'      => !empty($post['pic_penyusun_id']) ? (int)$post['pic_penyusun_id'] : null,
            'spesialisasi'         => $post['spesialisasi'] ?? '',
            'judul_proposal'       => $post['judul_proposal'] ?? '',
            'ruang_lingkup'        => $post['ruang_lingkup'] ?? '',
            'durasi_kegiatan'      => $post['durasi_kegiatan'] ?? '3 bulan',
            'estimasi_total_biaya' => $totalBiayaAktif,
            'status_proposal'      => $statusProposal,
            'status_rancop'        => $statusRancop,
            'log_diskusi_klien'    => $post['log_diskusi_klien'] ?? '',
            'tahapan_riset_json'   => json_encode($tahapanList, JSON_UNESCAPED_UNICODE)
        ];

        try {
            $orderModel = new OrderLayanan($this->db);
            $orderModel->simpanProposalSelulosa($id, $data, $userId);

            if ($statusRancop === 'deal') {
                $this->setFlashSuccess("Rancangan Percobaan (Rancop) Selulosa telah disetujui (Deal)! Anggaran Rp " . number_format($totalBiayaAktif, 0, ',', '.') . " siap dibuatkan Surat Penawaran Resmi.");
            } elseif ($statusRancop === 'batal') {
                $this->setFlashWarning("Permohonan riset telah ditandai Batal / Tidak Berlanjut.");
            } else {
                $this->setFlashSuccess("Draf Rancangan Percobaan (Rancop) & Anggaran Riset Selulosa berhasil diperbarui.");
            }

            $f3->reroute("/order/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan rancop: ' . $e->getMessage());
            $f3->reroute("/order/{$id}/rancop-selulosa");
        }
    }

    public function simpanRancopSelulosa($f3, $params) {
        return $this->biayaProposalPost($f3, $params);
    }

    /**
     * Menampilkan form Kalkulator Pengujian Multi-Metode (Divisi Lingkungan)
     * Route: GET /order/@id/biaya-lingkungan
     */
    public function biayaLingkungan($f3, $params) {
        $this->requirePermission('order:kalkulasi_biaya', '/order');

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $kalkulasiItems = $orderModel->getKalkulasiLingkungan($id);
        $daftarMetode = $this->db->exec("SELECT * FROM metode_uji WHERE status = 'aktif' ORDER BY kategori_id ASC, nama_metode ASC");
        $daftarLabEksternal = $this->db->exec("SELECT * FROM pengujian_eksternal WHERE status = 'aktif' ORDER BY nama_lembaga ASC");

        $f3->set('order', $order);
        $f3->set('kalkulasi_items', $kalkulasiItems);
        $f3->set('daftar_metode', $daftarMetode);
        $f3->set('daftar_lab_eksternal', $daftarLabEksternal);

        $this->render('order/form_biaya_lingkungan.html', "Kalkulasi Biaya Pengujian Lingkungan", 'order');
    }

    /**
     * Memproses simpan kalkulasi multi-metode lingkungan
     * Route: POST /order/@id/biaya-lingkungan
     */
    public function biayaLingkunganPost($f3, $params) {
        $this->requirePermission('order:kalkulasi_biaya', '/order');

        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        $diskon = (float)($post['diskon_penawaran'] ?? 0.0);
        $tglSampel = !empty($post['tanggal_terima_sampel']) ? $post['tanggal_terima_sampel'] : null;

        // Parse list item dari form
        $items = [];
        if (!empty($post['items']) && is_array($post['items'])) {
            foreach ($post['items'] as $item) {
                if (!empty($item['nama_pengujian'])) {
                    $items[] = [
                        'sub_layanan'      => $item['sub_layanan'] ?? 'uji_laboratorium',
                        'metode_uji_id'    => !empty($item['metode_uji_id']) ? (int)$item['metode_uji_id'] : null,
                        'nama_pengujian'   => trim($item['nama_pengujian']),
                        'standar_rujukan'  => trim($item['standar_rujukan'] ?? ''),
                        'tarif_per_sampel' => (float)($item['tarif_per_sampel'] ?? 0),
                        'jumlah_sampel'    => max(1, (int)($item['jumlah_sampel'] ?? 1)),
                        'durasi_bulan'     => max(1, (int)($item['durasi_bulan'] ?? 1)),
                        'is_subkontrak'    => !empty($item['is_subkontrak']) ? 1 : 0,
                        'lab_eksternal_id' => !empty($item['lab_eksternal_id']) ? (int)$item['lab_eksternal_id'] : null
                    ];
                }
            }
        }

        try {
            $orderModel = new OrderLayanan($this->db);
            $hasil = $orderModel->simpanKalkulasiLingkungan($id, $items, $diskon, $tglSampel, $userId);

            $this->setFlashSuccess(
                "Kalkulasi biaya pengujian lingkungan berhasil disimpan! Total Netto Penawaran: <strong>Rp " . number_format($hasil['total_netto'], 0, ',', '.') . "</strong>."
            );
            $f3->reroute("/order/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan kalkulasi pengujian: ' . $e->getMessage());
            $f3->reroute("/order/{$id}/biaya-lingkungan");
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

    /**
     * Memproses disposisi permohonan surat masuk ke Ketua Tim / Divisi OPTI
     * Route: POST /order/@id/disposisi
     */
    public function disposisi($f3, $params) {
        $this->requirePermission('order:edit', '/order');

        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $jenisOpti = $post['jenis_layanan_opti'] ?? 'selulosa';
        $picId     = !empty($post['pic_proposal_id']) ? (int)$post['pic_proposal_id'] : null;
        $catatan   = trim($post['catatan_disposisi'] ?? '');

        try {
            $orderModel = new OrderLayanan($this->db);
            $order = $orderModel->getById($id);
            if (!$order) {
                throw new \Exception("Order #{$id} tidak ditemukan.");
            }

            // Update order with selected OPTI division, PIC Katim, and update status to 'baru'
            $currentDeskripsi = $order->deskripsi ?? '';
            $newDeskripsi = !empty($catatan) ? (rtrim($currentDeskripsi) . "\n[Disposisi]: " . $catatan) : $currentDeskripsi;

            $orderModel->updateData($id, array(
                'jenis_layanan_opti' => $jenisOpti,
                'pic_proposal_id'    => $picId,
                'deskripsi'          => $newDeskripsi,
                'status'             => 'baru'
            ));

            $optiNama = $jenisOpti === 'selulosa' ? 'OPTI Selulosa' : 'OPTI Lingkungan';
            $this->setFlashSuccess("Permohonan berhasil didisposisikan ke <strong>{$optiNama}</strong>. Status order kini beralih menjadi <strong>Order Aktif</strong>.");
            $f3->reroute("/order/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError("Gagal mendisposisikan order: " . $e->getMessage());
            $f3->reroute("/order/{$id}");
        }
    }
}
