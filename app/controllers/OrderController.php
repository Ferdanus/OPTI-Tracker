<?php

/**
 * Controller untuk mengelola Permohonan Order Layanan OPTI
 * Dilengkapi Guard Permission (Order: Admin Order & Superadmin; Approve: Pejabat & Superadmin)
 */
class OrderController extends Controller {

    /**
     * Halaman Notifikasi & Kotak Disposisi Masuk
     * Route: GET /disposisi-masuk
     */
    public function disposisiMasuk($f3) {
        $this->requirePermission('order:tinjau', '/order');

        $role = $this->getUserRole();
        $layanan = $this->getUserLayanan();
        $filterDivisi = $f3->get('GET.divisi') ?? ($role === 'ketua_tim' ? $layanan : '');

        $sqlWhere = "1=1";
        $params = array();
        if (!empty($filterDivisi) && in_array($filterDivisi, array('selulosa', 'lingkungan'))) {
            $sqlWhere .= " AND o.jenis_layanan_opti = ?";
            $params[1] = $filterDivisi;
        }

        // 1. Permintaan Masuk Baru (Perlu Kaji Ulang & Tunjuk PIC)
        $sqlPerluKajiUlang = "SELECT o.*, 
                                     c.nmcustomer AS nama_perusahaan, c.pt_cv,
                                     COALESCE(NULLIF(c.contactperson_opti, ''), NULLIF(c.contactperson, ''), c.nama_pribadi, '-') AS pic,
                                     COALESCE(NULLIF(c.nohpcontactperson_opti, ''), NULLIF(c.nohpcontactperson, ''), c.notelpcustomer, '-') AS telepon,
                                     sp.permintaan_melalui, sp.penjelasan
                              FROM order_layanan o
                              JOIN tb_customer c ON o.id_customer = c.id_customer
                              LEFT JOIN tb_surat_penawaran sp ON o.id = sp.order_id
                              WHERE {$sqlWhere} 
                                AND o.status IN ('baru', 'permintaan_masuk') 
                                AND o.id NOT IN (SELECT order_id FROM opti_tinjauan_kelayakan)
                              ORDER BY o.id DESC";
        $perluKajiUlang = $this->db->exec($sqlPerluKajiUlang, $params);

        // 2. Proposal / Kalkulasi Masuk Menunggu Persetujuan Ka. Tim
        $sqlPerluApproval = "SELECT o.*, 
                                    c.nmcustomer AS nama_perusahaan, c.pt_cv,
                                    u.nama_user AS pic_nama,
                                    pr.estimasi_total_biaya, pr.file_proposal, pr.status_proposal,
                                    (SELECT COUNT(*) FROM opti_kalkulasi_uji_lingkungan kl WHERE kl.order_id = o.id) AS total_parameter_lingkungan
                             FROM order_layanan o
                             JOIN tb_customer c ON o.id_customer = c.id_customer
                             LEFT JOIN tb_arsipuser u ON o.pic_proposal_id = u.id_user
                             LEFT JOIN opti_proposal_riset pr ON o.id = pr.order_id
                             WHERE {$sqlWhere} 
                               AND o.status_proposal_biaya = 'menunggu_approval'
                             ORDER BY o.id DESC";
        $perluApproval = $this->db->exec($sqlPerluApproval, $params);

        // 3. Permintaan yang Sedang Aktif / Berjalan
        $sqlBerjalan = "SELECT o.*, 
                               c.nmcustomer AS nama_perusahaan, c.pt_cv,
                               u.nama_user AS pic_nama,
                               t.keputusan AS keputusan_tinjauan, t.tanggal_tinjauan
                        FROM order_layanan o
                        JOIN tb_customer c ON o.id_customer = c.id_customer
                        LEFT JOIN tb_arsipuser u ON o.pic_proposal_id = u.id_user
                        LEFT JOIN opti_tinjauan_kelayakan t ON o.id = t.order_id
                        WHERE {$sqlWhere} 
                          AND (
                              (o.id IN (SELECT order_id FROM opti_tinjauan_kelayakan) AND (o.status_proposal_biaya != 'menunggu_approval' OR o.status_proposal_biaya IS NULL))
                              OR o.status NOT IN ('permintaan_masuk', 'baru')
                          )
                        ORDER BY o.id DESC LIMIT 15";
        $sedangBerjalan = $this->db->exec($sqlBerjalan, $params);

        $f3->set('perlu_kaji_ulang', $perluKajiUlang);
        $f3->set('perlu_approval', $perluApproval);
        $f3->set('sedang_berjalan', $sedangBerjalan);
        $f3->set('filter_divisi', $filterDivisi);
        $f3->set('user_role', $role);
        $f3->set('user_layanan', $layanan);

        $this->render('order/disposisi_masuk.html', 'Permintaan Masuk & Disposisi', 'disposisi_masuk');
    }

    /**
     * Menampilkan daftar semua order layanan
     * Route: GET /order
     */
    public function index($f3) {
        $this->requirePermission('order:view', '/po');

        $currentYear  = date('Y');
        $filterTahun  = $f3->exists('GET.tahun') ? trim($f3->get('GET.tahun')) : $currentYear;
        $daftarTahun  = range((int)$currentYear, (int)$currentYear - 4);

        $tabGet       = $f3->get('GET.tab');
        if ($tabGet === 'ditolak') {
            $filterTab = 'ditolak';
        } elseif ($tabGet === 'berlangsung') {
            $filterTab = 'berlangsung';
        } else {
            $filterTab = 'masuk';
        }
        $filterJenis  = $f3->get('GET.jenis_layanan') ?? '';
        $filterStatus = $f3->get('GET.status') ?? '';
        $search       = $f3->get('GET.q') ?? '';

        $userRole = $this->getUserRole();
        $isSuperadmin = $this->isSuperadmin();
        $isKetuaTim = $this->isKetuaTim();
        $userLayanan = $_SESSION['jenis_layanan_opti'] ?? '';

        // Jika Ketua Tim OPTI: Wajib kunci hanya melihat divisi layanannya sendiri (selulosa / lingkungan)
        if ($isKetuaTim && !$isSuperadmin && in_array($userLayanan, ['selulosa', 'lingkungan'])) {
            $filterJenis = $userLayanan;
        }

        $orderModel = new OrderLayanan($this->db);
        
        // Data order sesuai tab yang dipilih
        $daftarOrder = $orderModel->allWithRelasi($filterJenis, $filterStatus, $search, $filterTahun, $filterTab);
        
        // Counter untuk badge 3 tab navigasi
        $countMasuk       = count($orderModel->allWithRelasi($filterJenis, '', '', $filterTahun, 'masuk'));
        $countBerlangsung = count($orderModel->allWithRelasi($filterJenis, '', '', $filterTahun, 'berlangsung'));
        $countDitolak     = count($orderModel->allWithRelasi($filterJenis, '', '', $filterTahun, 'ditolak'));

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();

        $f3->set('daftar_order', $daftarOrder);
        $f3->set('filter_tab', $filterTab);
        $f3->set('count_masuk', $countMasuk);
        $f3->set('count_berlangsung', $countBerlangsung);
        $f3->set('count_ditolak', $countDitolak);
        $f3->set('count_aktif', $countMasuk + $countBerlangsung);
        $f3->set('filter_tahun', $filterTahun);
        $f3->set('daftar_tahun', $daftarTahun);
        $f3->set('filter_jenis_layanan', $filterJenis);
        $f3->set('filter_jenis', $filterJenis);
        $f3->set('filter_status', $filterStatus);
        $f3->set('search_q', $search);
        $f3->set('mask_client_name', $maskEnabled);
        $f3->set('is_locked_divisi', ($isKetuaTim && !$isSuperadmin && in_array($userLayanan, ['selulosa', 'lingkungan'])));
        $f3->set('user_layanan', $userLayanan);

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

        $actionBtn        = $post['action_btn'] ?? 'simpan';
        $statusOrder      = ($actionBtn === 'draft' || $actionBtn === 'save_draft') ? 'draft_disimpan' : 'baru';

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
                'estimasi_biaya'     => $estimasiBiaya,
                'status'             => $statusOrder
            ));

            if ($statusOrder === 'draft_disimpan') {
                $this->setFlashSuccess('Order Layanan berhasil disimpan (Status: <strong>Draft Disimpan</strong>).');
            } else {
                $this->setFlashSuccess('Order Layanan baru berhasil didaftarkan.');
            }
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

        $userRole = $this->getUserRole();
        $isSuperadmin = $this->isSuperadmin();
        $isKetuaTim = $this->isKetuaTim();
        $userLayanan = $_SESSION['jenis_layanan_opti'] ?? '';

        // Validasi Divisi untuk Ketua Tim:
        if ($isKetuaTim && !$isSuperadmin && in_array($userLayanan, ['selulosa', 'lingkungan']) && $order['jenis_layanan_opti'] !== $userLayanan) {
            $this->setFlashError("Akses Ditolak: Anda hanya berwenang mengakses order untuk Divisi OPTI " . ucfirst($userLayanan) . ".");
            $f3->reroute('/order');
            return;
        }

        $tinjauan = $orderModel->getTinjauanKelayakan($id);
        $proposal = $orderModel->getProposalRiset($id);
        $kalkulasiLingkungan = ($order['jenis_layanan_opti'] === 'lingkungan') ? $orderModel->getKalkulasiLingkungan($id) : [];

        $spModel = new SuratPenawaran($this->db);
        $riwayatPenawaran = $spModel->getAllByOrderId($id);
        $penawaran = !empty($riwayatPenawaran) ? end($riwayatPenawaran) : $spModel->getByOrderId($id);
        $totalPenawaran = count($riwayatPenawaran);

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

        $poKegiatan = null;
        try {
            $poKegiatanModel = new PoKegiatan($this->db);
            $poKegiatanModel->load(['order_id = ?', $id]);
            if (!$poKegiatanModel->dry()) {
                $poKegiatan = $poKegiatanModel->cast();
            }
        } catch (\Exception $e) {}
        $f3->set('po_kegiatan', $poKegiatan);

        $bastModel = new OptiBast($this->db);
        $bast = $bastModel->getByOrderId($id);

        $customerModel = new Customer($this->db);
        $daftarCustomerOpti = $customerModel->getRegisteredPelangganOpti();
        $existingIds = array_column($daftarCustomerOpti, 'id_customer');
        if (!empty($order['id_customer']) && !in_array((int)$order['id_customer'], $existingIds)) {
            $custCurrent = $customerModel->getById((int)$order['id_customer']);
            if ($custCurrent && !$custCurrent->dry()) {
                $cData = $custCurrent->cast();
                $cData['nama_perusahaan_bersih'] = Customer::formatNamaPerusahaan($cData['pt_cv'] ?? '', $cData['nmcustomer'] ?? '');
                $cData['pic_bersih'] = $cData['contactperson_opti'] ?: ($cData['contactperson'] ?: ($cData['nama_pribadi'] ?: '-'));
                $cData['hp_bersih'] = $cData['nohpcontactperson_opti'] ?: ($cData['nohpcontactperson'] ?: '-');
                $cData['telp_kantor_bersih'] = $cData['notelpcustomer'] ?: '-';
                $cData['telp_bersih'] = $cData['nohpcontactperson_opti'] ?: ($cData['notelpcustomer'] ?: '-');
                $cData['email_bersih'] = $cData['emailcustomer'] ?: ($cData['emailcustomer_sertifikasi'] ?: '-');
                $cData['alamat_bersih'] = $cData['alamatcustomer_baru'] ?: ($cData['alamatcustomer'] ?: '-');
                array_unshift($daftarCustomerOpti, $cData);
            }
        }
        $f3->set('daftar_customer_opti', $daftarCustomerOpti);
        $daftarCustomer = $customerModel->all();

        $daftarPic = OrderLayanan::getPICSpesialisasiList($this->db, $order['jenis_layanan_opti'] ?? null);

        $suratMasuk = null;
        if (!empty($order['id_surat_masuk'])) {
            try {
                $repoSurat = new \SuratMasukRepository($this->db, $this->dbSekretariat);
                $suratMasuk = $repoSurat->getSuratById((int)$order['id_surat_masuk']);
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
        $f3->set('riwayat_penawaran', $riwayatPenawaran);
        $f3->set('total_penawaran', $totalPenawaran);
        $f3->set('invoices', $invoices);
        $f3->set('riwayat_bayar', $riwayatBayar);
        $f3->set('rekap_keuangan', $rekapKeuangan);
        $f3->set('po_detail', $poDetail);
        $f3->set('kontrak_pks', $kontrakPks);
        $f3->set('jadwal_kerja', $jadwalKerja);
        $f3->set('bast', $bast);
        $f3->set('daftar_customer', $daftarCustomer);
        $f3->set('daftar_pic', $daftarPic);

        $kalkulasiSpm = null;
        if (!empty($order['tanggal_terima_sampel'])) {
            $durasiKerja = !empty($order['durasi_hari_kerja']) ? (int)$order['durasi_hari_kerja'] : 30;
            $kalkulasiSpm = $orderModel->hitungDeadlineHariKerja($order['tanggal_terima_sampel'], $durasiKerja);
        }
        $f3->set('kalkulasi_spm', $kalkulasiSpm);

        // Resolusi Status 8-Tahap Progress Stepper secara berurutan (Strict Sequential Workflow BBSPJIS):
        $isSelulosa = (($order['jenis_layanan_opti'] ?? '') === 'selulosa');
        $isLingkungan = (($order['jenis_layanan_opti'] ?? '') === 'lingkungan');
        $hasLayanan = in_array($order['jenis_layanan_opti'] ?? '', ['selulosa', 'lingkungan']);

        // Step 1: Surat Masuk (selalu selesai karena order sudah tercatat)
        $isSuratMasukDone = true;

        // Step 2: Form Pelayanan (selesai jika status bukan permintaan_masuk/draft dan jenis layanan sudah ditentukan)
        $isFormPelayananDone = !in_array($order['status'] ?? '', ['permintaan_masuk', 'draft_disimpan']) && $hasLayanan;

        // Step 3: Kaji Kelayakan (selesai jika kaji ulang kelayakan disetujui)
        $tinjauanDisetujui = (($order['status_tinjauan'] ?? '') === 'layak') || (!empty($tinjauan) && (($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan'));
        $isTinjauanDone = $isFormPelayananDone && $tinjauanDisetujui;

        // Step 4: Proposal Teknis (Selulosa) / Tarif & Parameter (Lingkungan)
        $hasPenawaran = !empty($penawaran);
        $proposalHasFileAndCost = !empty($proposal) && !empty($proposal['file_proposal']) && (float)($proposal['estimasi_total_biaya'] ?? 0) > 0;

        if ($isSelulosa) {
            // Selulosa: Wajib proposal lengkap & ACC Ka. Tim sebelum penawaran
            $isProposalApproved = $isTinjauanDone && (
                ($proposalHasFileAndCost && (
                    in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) ||
                    in_array($proposal['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])
                )) ||
                $hasPenawaran
            );
            $isStep4Done = $isProposalApproved;
        } elseif ($isLingkungan) {
            // Lingkungan: Tarif & parameter selesai jika parameter sudah dipilih atau penawaran sudah terbit
            $hasKalkulasiLingkungan = !empty($kalkulasiLingkungan) || (float)($order['estimasi_biaya'] ?? 0) > 0;
            $isStep4Done = $isTinjauanDone && ($hasKalkulasiLingkungan || in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) || $hasPenawaran);
            $isProposalApproved = $isStep4Done;
        } else {
            // Layanan belum ditentukan: Step 3 dan Step 4 BELUM SELESAI
            $isProposalApproved = false;
            $isStep4Done = false;
        }

        // Step 5: Penawaran DEAL
        $isPenawaranDeal = $isStep4Done && (
            ($order['status_penawaran'] ?? '') === 'deal' ||
            (!empty($penawaran) && ($penawaran['status_respon_klien'] ?? '') === 'deal')
        );

        // Step 6: Pembayaran
        $isPembayaranLunas = $isPenawaranDeal && (
            ($order['status_keuangan'] ?? '') === 'lunas' ||
            !empty($riwayatBayar)
        );

        // Khusus Lingkungan: Proposal teknis diunggah pasca pembayaran
        $proposalLingkunganUploaded = !empty($proposal) && !empty($proposal['file_proposal']);
        $f3->set('proposal_lingkungan_uploaded', $proposalLingkunganUploaded);

        // Step 7: Petunjuk Operasional (PO)
        $isPoDone = $isPembayaranLunas && !empty($order['po_id']) && (
            in_array($order['status'] ?? '', ['selesai']) ||
            ($order['status_pelaksanaan'] ?? '') === 'laporan_selesai' ||
            !empty($bast)
        );

        // Step 8: BAST
        $isBastDone = $isPoDone && !empty($bast) && (
            in_array($bast['status_bast'] ?? '', ['disetujui', 'selesai', 'terbit']) ||
            ($order['status'] ?? '') === 'selesai' ||
            ($order['status_pelaksanaan'] ?? '') === 'laporan_selesai'
        );

        // Tentukan tahap aktif saat ini (Sequential Step Position)
        if (!$isFormPelayananDone) {
            $currentStep = 2;
        } elseif (!$isTinjauanDone) {
            $currentStep = 3;
        } elseif (!$isStep4Done) {
            $currentStep = 4;
        } elseif (!$isPenawaranDeal) {
            $currentStep = 5;
        } elseif (!$isPembayaranLunas) {
            $currentStep = 6;
        } elseif (!$isPoDone) {
            $currentStep = 7;
        } elseif (!$isBastDone) {
            $currentStep = 8;
        } else {
            $currentStep = 8;
        }

        // Bangun state stepper: Hanya tahap SEBELUM currentStep yang berstatus done!
        $stepper = [];
        for ($s = 1; $s <= 8; $s++) {
            if ($s < $currentStep) {
                $stepper[$s] = ['state' => 'done'];
            } elseif ($s === $currentStep) {
                $stepper[$s] = ['state' => ($s === 8 && $isBastDone) ? 'done' : 'current'];
            } else {
                $stepper[$s] = ['state' => 'waiting'];
            }
        }
        $f3->set('stepper', $stepper);
        $f3->set('current_step', $currentStep);
        $f3->set('proposal_has_file_cost', $proposalHasFileAndCost);
        $f3->set('is_proposal_approved', $isProposalApproved);

        // Panduan Langkah Selanjutnya yang Jelas & Ramah Awam (Non-IT)
        $isFinished = ($isBastDone || ($order['status'] ?? '') === 'selesai' || ($order['status_pelaksanaan'] ?? '') === 'laporan_selesai');

        $langkahBerikutnya = [
            'step' => $currentStep,
            'is_finished' => $isFinished,
            'judul' => '',
            'deskripsi' => '',
            'penanggung_jawab' => '',
            'role_icon' => 'bi-person-gear',
            'tipe_badge' => 'primary',
            'tombol_teks' => '',
            'tombol_url' => '',
            'tombol_modal' => '',
            'tombol_icon' => 'bi-arrow-right-circle-fill',
            'tombol_class' => 'btn-primary',
            'target_card' => '#cardPelayanan',
        ];

        if ($isFinished) {
            $langkahBerikutnya['judul'] = 'Layanan Selesai & Berita Acara (BAST) Telah Diterbitkan';
            $langkahBerikutnya['deskripsi'] = 'Seluruh siklus pengujian dari pendaftaran surat, kaji kelayakan, penawaran harga, pembayaran, pengujian laboratorium, hingga penyerahan dokumen BAST dan Laporan Hasil Uji (LHU) telah tuntas diselesaikan.';
            $langkahBerikutnya['penanggung_jawab'] = 'Layanan Selesai (Tuntas)';
            $langkahBerikutnya['role_icon'] = 'bi-check-circle-fill';
            $langkahBerikutnya['tipe_badge'] = 'success';
            $langkahBerikutnya['tombol_teks'] = 'Lihat Berkas BAST & Hasil';
            $langkahBerikutnya['tombol_url'] = '#cardPoBast';
            $langkahBerikutnya['tombol_icon'] = 'bi-patch-check-fill';
            $langkahBerikutnya['tombol_class'] = 'btn-success';
            $langkahBerikutnya['target_card'] = '#cardPoBast';
        } elseif ($currentStep === 2) {
            $langkahBerikutnya['judul'] = 'Lengkapi Formulir Permintaan Pelayanan Jasa';
            $langkahBerikutnya['deskripsi'] = 'Surat permohonan telah diterima. Tim Kemitraan perlu menentukan laboratorium pelaksana (Lingkungan atau Selulosa) dan mengisi spesifikasi awal sebelum diajukan ke Ketua Tim.';
            $langkahBerikutnya['penanggung_jawab'] = 'Tim Kemitraan / Pelayanan';
            $langkahBerikutnya['role_icon'] = 'bi-person-lines-fill';
            $langkahBerikutnya['tipe_badge'] = 'warning';
            $langkahBerikutnya['tombol_teks'] = 'Buka Formulir Pelayanan';
            $langkahBerikutnya['tombol_url'] = '#cardPelayanan';
            $langkahBerikutnya['tombol_icon'] = 'bi-file-earmark-medical-fill';
            $langkahBerikutnya['tombol_class'] = 'btn-warning text-dark';
            $langkahBerikutnya['target_card'] = '#cardPelayanan';
        } elseif ($currentStep === 3) {
            $langkahBerikutnya['judul'] = 'Lakukan Kaji Ulang Kelayakan Teknis (ISO 17025)';
            $langkahBerikutnya['deskripsi'] = 'Formulir pelayanan telah dikirimkan. Ketua Tim OPTI perlu memeriksa ketersediaan personil, peralatan uji, bahan kimia/reagen, serta metode standar sebelum menyetujui pelaksanaan layanan.';
            $langkahBerikutnya['penanggung_jawab'] = 'Ketua Tim OPTI ' . ($isSelulosa ? 'Selulosa' : ($isLingkungan ? 'Lingkungan' : ''));
            $langkahBerikutnya['role_icon'] = 'bi-clipboard-check-fill';
            $langkahBerikutnya['tipe_badge'] = 'primary';
            $langkahBerikutnya['tombol_teks'] = 'Lakukan Kaji Kelayakan';
            $langkahBerikutnya['tombol_url'] = $f3->get('BASE') . "/order/{$id}/tinjauan";
            $langkahBerikutnya['tombol_icon'] = 'bi-clipboard-check-fill';
            $langkahBerikutnya['tombol_class'] = 'btn-primary';
            $langkahBerikutnya['target_card'] = '#cardKajiKelayakan';
        } elseif ($currentStep === 4) {
            if ($isLingkungan) {
                $hasBiaya = !empty($kalkulasiLingkungan) || (float)($order['estimasi_biaya'] ?? 0) > 0;
                if (!$hasBiaya) {
                    $langkahBerikutnya['judul'] = 'Pilih Parameter Uji & Hitung Tarif Resmi PNBP';
                    $langkahBerikutnya['deskripsi'] = 'Kaji kelayakan disetujui. Silakan tentukan parameter pengujian air/udara/emisi dan jumlah sampel untuk menghasilkan rincian biaya sesuai katalog PP 54/2021.';
                    $langkahBerikutnya['penanggung_jawab'] = 'Tim Teknis Lingkungan';
                    $langkahBerikutnya['role_icon'] = 'bi-calculator-fill';
                    $langkahBerikutnya['tipe_badge'] = 'primary';
                    $langkahBerikutnya['tombol_teks'] = 'Kelola Parameter & Tarif';
                    $langkahBerikutnya['tombol_url'] = $f3->get('BASE') . "/order/{$id}/biaya-lingkungan";
                    $langkahBerikutnya['tombol_icon'] = 'bi-calculator-fill';
                    $langkahBerikutnya['tombol_class'] = 'btn-primary';
                    $langkahBerikutnya['target_card'] = '#cardParameterBiaya';
                } else {
                    $langkahBerikutnya['judul'] = 'Terbitkan Surat Penawaran Harga Resmi';
                    $langkahBerikutnya['deskripsi'] = 'Kalkulasi tarif pengujian sebesar Rp ' . number_format($order['estimasi_biaya'] ?? 0, 0, ',', '.') . ' telah siap. Terbitkan surat penawaran resmi untuk dikirimkan ke pelanggan.';
                    $langkahBerikutnya['penanggung_jawab'] = 'Tim Kemitraan & Pemasaran';
                    $langkahBerikutnya['role_icon'] = 'bi-send-check-fill';
                    $langkahBerikutnya['tipe_badge'] = 'primary';
                    $langkahBerikutnya['tombol_teks'] = 'Terbitkan Surat Penawaran';
                    $langkahBerikutnya['tombol_url'] = $f3->get('BASE') . "/order/{$id}/penawaran/buat";
                    $langkahBerikutnya['tombol_icon'] = 'bi-file-earmark-plus-fill';
                    $langkahBerikutnya['tombol_class'] = 'btn-primary';
                    $langkahBerikutnya['target_card'] = '#cardPenawaran';
                }
            } else {
                if (!$proposalHasFileAndCost) {
                    $langkahBerikutnya['judul'] = 'Unggah Dokumen Proposal Teknis & Estimasi Biaya';
                    $langkahBerikutnya['deskripsi'] = 'Kaji kelayakan disetujui. Tim Teknis Selulosa perlu mengunggah berkas proposal teknis dan menginput estimasi biaya kegiatan pengujian/penelitian.';
                    $langkahBerikutnya['penanggung_jawab'] = 'Tim Teknis Selulosa';
                    $langkahBerikutnya['role_icon'] = 'bi-file-earmark-arrow-up-fill';
                    $langkahBerikutnya['tipe_badge'] = 'primary';
                    $langkahBerikutnya['tombol_teks'] = 'Kelola Proposal Teknis';
                    $langkahBerikutnya['tombol_url'] = '#cardParameterBiaya';
                    $langkahBerikutnya['tombol_modal'] = '#modalProposalTeknis';
                    $langkahBerikutnya['tombol_icon'] = 'bi-upload';
                    $langkahBerikutnya['tombol_class'] = 'btn-primary';
                    $langkahBerikutnya['target_card'] = '#cardParameterBiaya';
                } else {
                    $langkahBerikutnya['judul'] = 'Menunggu Persetujuan Proposal dari Ka. Tim Selulosa';
                    $langkahBerikutnya['deskripsi'] = 'Proposal teknis telah diunggah. Menunggu persetujuan final (ACC) dari Ketua Tim Selulosa sebelum penawaran resmi dapat diterbitkan.';
                    $langkahBerikutnya['penanggung_jawab'] = 'Ketua Tim Selulosa';
                    $langkahBerikutnya['role_icon'] = 'bi-shield-check';
                    $langkahBerikutnya['tipe_badge'] = 'warning';
                    $langkahBerikutnya['tombol_teks'] = 'Periksa Lembar Proposal';
                    $langkahBerikutnya['tombol_url'] = '#cardParameterBiaya';
                    $langkahBerikutnya['tombol_icon'] = 'bi-file-earmark-text-fill';
                    $langkahBerikutnya['tombol_class'] = 'btn-outline-primary';
                    $langkahBerikutnya['target_card'] = '#cardParameterBiaya';
                }
            }
        } elseif ($currentStep === 5) {
            if (empty($penawaran)) {
                $langkahBerikutnya['judul'] = 'Terbitkan Surat Penawaran Harga Resmi';
                $langkahBerikutnya['deskripsi'] = 'Parameter pengujian dan rincian biaya telah disetujui. Terbitkan surat penawaran harga resmi untuk disampaikan kepada pihak pelanggan.';
                $langkahBerikutnya['penanggung_jawab'] = 'Tim Kemitraan & Pemasaran';
                $langkahBerikutnya['role_icon'] = 'bi-file-earmark-plus-fill';
                $langkahBerikutnya['tipe_badge'] = 'primary';
                $langkahBerikutnya['tombol_teks'] = 'Buat Surat Penawaran';
                $langkahBerikutnya['tombol_url'] = $f3->get('BASE') . "/order/{$id}/penawaran/buat";
                $langkahBerikutnya['tombol_icon'] = 'bi-file-earmark-plus-fill';
                $langkahBerikutnya['tombol_class'] = 'btn-primary';
                $langkahBerikutnya['target_card'] = '#cardPenawaran';
            } else {
                $langkahBerikutnya['judul'] = 'Menunggu Konfirmasi Persetujuan Pelanggan (DEAL)';
                $langkahBerikutnya['deskripsi'] = 'Surat Penawaran resmi (' . ($penawaran['nomor_penawaran'] ?? 'Draft') . ') telah diterbitkan. Lakukan komunikasi dengan pelanggan dan catat respon persetujuan (DEAL) pada form di bawah.';
                $langkahBerikutnya['penanggung_jawab'] = 'Pelanggan & Tim Kemitraan';
                $langkahBerikutnya['role_icon'] = 'bi-telephone-forward-fill';
                $langkahBerikutnya['tipe_badge'] = 'warning';
                $langkahBerikutnya['tombol_teks'] = 'Catat Respon Pelanggan';
                $langkahBerikutnya['tombol_url'] = '#cardPenawaran';
                $langkahBerikutnya['tombol_icon'] = 'bi-check2-circle';
                $langkahBerikutnya['tombol_class'] = 'btn-primary';
                $langkahBerikutnya['target_card'] = '#cardPenawaran';
            }
        } elseif ($currentStep === 6) {
            $langkahBerikutnya['judul'] = 'Konfirmasi Penerimaan Pembayaran / Bukti Setor PNBP';
            $langkahBerikutnya['deskripsi'] = 'Penawaran harga telah disetujui (DEAL) oleh pelanggan. Tim Keuangan perlu mencatat bukti transfer pembayaran atau setoran billing PNBP agar pengujian laboratorium dapat dijadwalkan.';
            $langkahBerikutnya['penanggung_jawab'] = 'Pelanggan & Tim Keuangan';
            $langkahBerikutnya['role_icon'] = 'bi-credit-card-2-front-fill';
            $langkahBerikutnya['tipe_badge'] = 'warning';
            $langkahBerikutnya['tombol_teks'] = 'Catat & Konfirmasi Pembayaran';
            $langkahBerikutnya['tombol_url'] = '#cardPembayaran';
            $langkahBerikutnya['tombol_icon'] = 'bi-credit-card-2-front-fill';
            $langkahBerikutnya['tombol_class'] = 'btn-success';
            $langkahBerikutnya['target_card'] = '#cardPembayaran';
        } elseif ($currentStep === 7) {
            if (empty($order['tanggal_terima_sampel'])) {
                $langkahBerikutnya['judul'] = 'Catat Tanggal Penerimaan Fisik Sampel di Laboratorium';
                $langkahBerikutnya['deskripsi'] = 'Pembayaran pelanggan telah dikonfirmasi. Ketika sampel fisik uji tiba di laboratorium, catat tanggal penerimaan untuk mengaktifkan perhitungan batas hari kerja resmi (SPM).';
                $langkahBerikutnya['penanggung_jawab'] = 'Laboratorium & Petugas Penerima Sampel';
                $langkahBerikutnya['role_icon'] = 'bi-box-seam-fill';
                $langkahBerikutnya['tipe_badge'] = 'warning';
                $langkahBerikutnya['tombol_teks'] = 'Catat Tanggal Sampel';
                $langkahBerikutnya['tombol_url'] = '#cardSampelSpm';
                $langkahBerikutnya['tombol_icon'] = 'bi-calendar-check-fill';
                $langkahBerikutnya['tombol_class'] = 'btn-warning text-dark';
                $langkahBerikutnya['target_card'] = '#cardSampelSpm';
            } elseif (empty($order['po_id'])) {
                $deadlineStr = !empty($order['tanggal_deadline_spm']) ? date('d M Y', strtotime($order['tanggal_deadline_spm'])) : '-';
                $langkahBerikutnya['judul'] = 'Terbitkan Petunjuk Operasional (PO) Pengujian Lab';
                $langkahBerikutnya['deskripsi'] = "Sampel fisik telah diterima dan SPM aktif sampai {$deadlineStr}. Terbitkan lembar PO agar personil analis lab segera menjalankan pengujian sesuai parameter.";
                $langkahBerikutnya['penanggung_jawab'] = 'Ketua Tim OPTI';
                $langkahBerikutnya['role_icon'] = 'bi-patch-check-fill';
                $langkahBerikutnya['tipe_badge'] = 'primary';
                $langkahBerikutnya['tombol_teks'] = 'Terbitkan Lembar PO';
                $langkahBerikutnya['tombol_url'] = '#cardPoBast';
                $langkahBerikutnya['tombol_modal'] = '#modalApprove';
                $langkahBerikutnya['tombol_icon'] = 'bi-patch-check-fill';
                $langkahBerikutnya['tombol_class'] = 'btn-primary';
                $langkahBerikutnya['target_card'] = '#cardPoBast';
            } else {
                $langkahBerikutnya['judul'] = 'Laboratorium Sedang Melakukan Pengujian Sampel';
                $langkahBerikutnya['deskripsi'] = 'Petunjuk Operasional (' . ($order['nomor_po'] ?? '-') . ') aktif. Analis laboratorium sedang melakukan pengujian parameter sesuai standar acuan kerja.';
                $langkahBerikutnya['penanggung_jawab'] = 'Laboratorium Penguji';
                $langkahBerikutnya['role_icon'] = 'bi-flask-fill';
                $langkahBerikutnya['tipe_badge'] = 'primary';
                $langkahBerikutnya['tombol_teks'] = 'Buka Monitoring PO';
                $langkahBerikutnya['tombol_url'] = $f3->get('BASE') . "/po/" . $order['po_id'];
                $langkahBerikutnya['tombol_icon'] = 'bi-speedometer2';
                $langkahBerikutnya['tombol_class'] = 'btn-dark';
                $langkahBerikutnya['target_card'] = '#cardPoBast';
            }
        } elseif ($currentStep === 8) {
            $langkahBerikutnya['judul'] = 'Terbitkan Dokumen BAST & Serahkan Laporan Hasil Uji (LHU)';
            $langkahBerikutnya['deskripsi'] = 'Pekerjaan pengujian laboratorium telah selesai. Terbitkan Berita Acara Serah Terima (BAST) untuk menyerahkan hasil pengujian resmi kepada pihak pelanggan.';
            $langkahBerikutnya['penanggung_jawab'] = 'Tim Kemitraan & Laboratorium';
            $langkahBerikutnya['role_icon'] = 'bi-file-earmark-check-fill';
            $langkahBerikutnya['tipe_badge'] = 'primary';
            $langkahBerikutnya['tombol_teks'] = 'Terbitkan Dokumen BAST';
            $langkahBerikutnya['tombol_url'] = $f3->get('BASE') . "/order/{$id}/bast/buat";
            $langkahBerikutnya['tombol_icon'] = 'bi-file-earmark-plus-fill';
            $langkahBerikutnya['tombol_class'] = 'btn-primary';
            $langkahBerikutnya['target_card'] = '#cardPoBast';
        }

        $f3->set('langkah_berikutnya', $langkahBerikutnya);

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

            // Update data surat masuk jika ada dan dikirimkan
            $nomorSurat   = trim($post['nomor_surat'] ?? '');
            $tanggalSurat = trim($post['tanggal_surat'] ?? '');
            $perihal      = trim($post['perihal'] ?? '');

            if (!empty($order['id_surat_masuk'])) {
                $idArsip = (int)$order['id_surat_masuk'];
                $arsipUpdates = [];
                $arsipParams  = [];
                if ($targetCustomerId > 0) {
                    $arsipUpdates[] = "id_customer = ?";
                    $arsipParams[]  = $targetCustomerId;
                }
                if (!empty($nomorSurat)) {
                    $arsipUpdates[] = "nomor_surat = ?";
                    $arsipParams[]  = $nomorSurat;
                }
                if (!empty($tanggalSurat)) {
                    $arsipUpdates[] = "tanggal_surat = ?";
                    $arsipParams[]  = $tanggalSurat;
                }
                if (!empty($perihal)) {
                    $arsipUpdates[] = "perihal = ?";
                    $arsipParams[]  = $perihal;
                }
                if (!empty($arsipUpdates)) {
                    $arsipParams[] = $idArsip;
                    try {
                        $targetDb = $this->dbSekretariat ?: $this->db;
                        $targetDb->exec(
                            "UPDATE tb_arsipsurat SET " . implode(', ', $arsipUpdates) . " WHERE id_arsip = ?",
                            $arsipParams
                        );
                    } catch (\Exception $eArsip) {}
                }
            }

            // Sinkronkan order_layanan jika nomor surat, tanggal masuk, atau perihal berubah
            $orderUpdates = [];
            $orderParams  = [];
            if (!empty($nomorSurat)) {
                $orderUpdates[] = "nomor_surat_masuk = ?";
                $orderParams[]  = $nomorSurat;
            }
            if (!empty($tanggalSurat)) {
                $orderUpdates[] = "tanggal_masuk = ?";
                $orderParams[]  = $tanggalSurat;
            }
            if (!empty($perihal)) {
                $orderUpdates[] = "judul_kegiatan = ?";
                $orderParams[]  = $perihal;
            }
            if (!empty($orderUpdates)) {
                $orderParams[] = $orderId;
                $this->db->exec(
                    "UPDATE order_layanan SET " . implode(', ', $orderUpdates) . " WHERE id = ?",
                    $orderParams
                );
            }

            // Sinkronkan ke tb_surat_penawaran jika sudah ada
            try {
                $spModel = new \DB\SQL\Mapper($this->db, 'tb_surat_penawaran');
                $spModel->load(['order_id = ?', $orderId]);
                if (!$spModel->dry()) {
                    if (!empty($namaPerusahaan)) $spModel->perusahaan = $namaPerusahaan;
                    if (!empty($pic))            $spModel->nama       = $pic;
                    if (!empty($alamat))         $spModel->alamat     = $alamat;
                    $spModel->customer_id = $targetCustomerId;
                    $spModel->save();
                }
            } catch (\Exception $eSp) {}

            $this->setFlashSuccess("Data profil pelanggan / rujukan surat untuk Order #{$order['nomor_order']} berhasil diperbarui!");
        } catch (\Exception $e) {
            $this->setFlashError("Gagal memperbarui data: " . $e->getMessage());
        }

        $f3->reroute("/order/{$orderId}");
    }

    /**
     * Menampilkan form Kaji Ulang Kelayakan Teknis (ISO) & Penunjukan PIC Proposal
     * Route: GET /order/@id/tinjauan
     */
    public function tinjauan($f3, $params) {
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $userRole = $this->getUserRole();
        $isSuperadmin = $this->isSuperadmin();
        $isKetuaTim = $this->isKetuaTim();
        $userLayanan = $_SESSION['jenis_layanan_opti'] ?? '';

        // Validasi Divisi untuk Ketua Tim:
        if ($isKetuaTim && !$isSuperadmin && in_array($userLayanan, ['selulosa', 'lingkungan']) && $order['jenis_layanan_opti'] !== $userLayanan) {
            $this->setFlashError("Akses Ditolak: Anda hanya berwenang mengevaluasi order untuk Divisi OPTI " . ucfirst($userLayanan) . ".");
            $f3->reroute('/order');
            return;
        }

        $tinjauan = $orderModel->getTinjauanKelayakan($id);
        $daftarPic = OrderLayanan::getPICSpesialisasiList($this->db, $order['jenis_layanan_opti'] ?? null);

        // Otomatis arahkan ke Ketua Tim pelaksana terkait jika belum dipilih
        if (empty($order['pic_proposal_id'])) {
            if (($order['jenis_layanan_opti'] ?? '') === 'lingkungan') {
                $order['pic_proposal_id'] = 61; // Andri Taufick Rizaluddin
            } elseif (($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
                $order['pic_proposal_id'] = 3;  // Rina Masriani
            }
        }
        
        $suratMasuk = null;
        if (!empty($order['id_surat_masuk'])) {
            try {
                $repoSurat = new \SuratMasukRepository($this->db, $this->dbSekretariat);
                $suratMasuk = $repoSurat->getSuratById((int)$order['id_surat_masuk']);
            } catch (\Exception $e) {
                // Ignore DB error
            }
        }

        $canEdit = ($this->hasPermission('order:tinjau') || $this->isSuperadmin() || $this->isTimMitra());

        $f3->set('order', $order);
        $f3->set('tinjauan', $tinjauan);
        $f3->set('daftar_pic', $daftarPic);
        $f3->set('surat_masuk', $suratMasuk);
        $f3->set('can_edit', $canEdit);

        $this->render('order/tinjauan_kelayakan.html', "Tinjauan Kelayakan Order #{$order['nomor_order']}", 'order');
    }

    /**
     * Memproses penyimpanan Tinjauan Kelayakan Permintaan & Penunjukan PIC Proposal
     * Route: POST /order/@id/tinjauan
     */
    public function tinjauanPost($f3, $params) {
        $id = (int)($params['id'] ?? 0);

        if (!$this->hasPermission('order:tinjau') && !$this->isSuperadmin() && !$this->isTimMitra()) {
            $this->setFlashError('Akses Ditolak: Kaji kelayakan teknis dan penunjukan PIC merupakan wewenang Ketua Tim OPTI / Tim Mitra.');
            $f3->reroute("/order/{$id}/tinjauan");
            return;
        }

        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $userRole = $this->getUserRole();
        $isSuperadmin = $this->isSuperadmin();
        $isKetuaTim = $this->isKetuaTim();
        $userLayanan = $_SESSION['jenis_layanan_opti'] ?? '';

        // Validasi Divisi untuk Ketua Tim:
        if ($isKetuaTim && !$isSuperadmin && in_array($userLayanan, ['selulosa', 'lingkungan']) && $order['jenis_layanan_opti'] !== $userLayanan) {
            $this->setFlashError("Akses Ditolak: Anda hanya berwenang mengevaluasi order untuk Divisi OPTI " . ucfirst($userLayanan) . ".");
            $f3->reroute('/order');
            return;
        }

        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        try {
            $orderModel = new OrderLayanan($this->db);
            $hasil = $orderModel->simpanTinjauanKelayakan($id, $post, $userId);
            $order = $orderModel->getDetail($id);

            // Kirim notifikasi ke Tim Mitra & PIC
            try {
                if ($hasil['keputusan'] === 'dapat_dilaksanakan') {
                    $picNama = !empty($post['pic_nama']) ? $post['pic_nama'] : 'PIC Terpilih';
                    
                    // Notif ke Tim Mitra
                    \NotificationService::send($this->db, [
                        'order_id'       => $id,
                        'target_role'    => 'admin_order',
                        'target_layanan' => 'semua',
                        'judul'          => 'Kelayakan Teknis ISO Disetujui',
                        'pesan'          => "Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) dinyatakan 'Dapat Dilaksanakan' oleh Ka. Tim OPTI. PIC yang ditugaskan: {$picNama}.",
                        'tipe'           => 'success',
                        'icon'           => 'bi-check-circle-fill',
                        'link_url'       => "/order/{$id}",
                        'created_by'     => $userId,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Ketua Tim OPTI'
                    ]);

                    // Notif ke PIC Peneliti / Tim Kerja
                    if (!empty($post['pic_proposal_id'])) {
                        \NotificationService::send($this->db, [
                            'order_id'       => $id,
                            'target_role'    => 'tim_kerja',
                            'target_user_id' => (int)$post['pic_proposal_id'],
                            'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                            'judul'          => 'Penugasan Dokumen Proposal Teknis',
                            'pesan'          => "Anda ditugaskan sebagai PIC untuk Order #{$order['nomor_order']} ({$order['nama_perusahaan']}). Mohon susun dan unggah dokumen proposal teknis.",
                            'tipe'           => 'primary',
                            'icon'           => 'bi-file-earmark-arrow-up-fill',
                            'link_url'       => "/order/{$id}/proposal",
                            'created_by'     => $userId,
                            'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Ketua Tim OPTI'
                        ]);
                    }
                } else {
                    // Notif penolakan ke Tim Mitra
                    \NotificationService::send($this->db, [
                        'order_id'       => $id,
                        'target_role'    => 'admin_order',
                        'target_layanan' => 'semua',
                        'judul'          => 'Order Tidak Dapat Dilaksanakan',
                        'pesan'          => "Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) dinyatakan 'Tidak Dapat Dilaksanakan' oleh Ka. Tim OPTI.",
                        'tipe'           => 'warning',
                        'icon'           => 'bi-x-circle-fill',
                        'link_url'       => "/order/{$id}",
                        'created_by'     => $userId,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Ketua Tim OPTI'
                    ]);
                }
            } catch (\Exception $eNotif) {
                // Ignore notification errors
            }

            if ($hasil['keputusan'] === 'dapat_dilaksanakan') {
                $this->setFlashSuccess("Kaji Kelayakan Teknis disetujui: <strong>Dapat Dilaksanakan</strong>. Permintaan siap dilanjutkan ke perhitungan tarif &amp; surat penawaran.");
            } else {
                $this->setFlashWarning("Kaji Kelayakan Teknis disimpan. Status: <strong>Tidak Dapat Dilaksanakan (Ditolak)</strong>. Alasan penolakan telah dicatat.");
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
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $proposal = $orderModel->getProposalRiset($id);
        $daftarPic = OrderLayanan::getPICSpesialisasiList($this->db, $order['jenis_layanan_opti'] ?? 'selulosa');

        $isPic = ((int)$this->getUserId() === (int)($order['pic_proposal_id'] ?? 0));
        $canEdit = ($this->hasPermission('order:proposal') || $this->isSuperadmin() || $isPic);

        $f3->set('order', $order);
        $f3->set('proposal', $proposal);
        $f3->set('daftar_pic', $daftarPic);
        $f3->set('can_edit', $canEdit);

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
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        $isPic = ($order && (int)$this->getUserId() === (int)($order['pic_proposal_id'] ?? 0));
        if (!$this->hasPermission('order:proposal') && !$this->isSuperadmin() && !$isPic) {
            $this->setFlashError("Akses Ditolak: Penyusunan proposal teknis & rancop merupakan wewenang PIC Proposal yang ditunjuk.");
            $f3->reroute("/order/{$id}/rancop-selulosa");
            return;
        }

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
            $order = $orderModel->getDetail($id);

            // Kirim notifikasi ke Tim Mitra
            try {
                \NotificationService::send($this->db, [
                    'order_id'       => $id,
                    'target_role'    => 'admin_order',
                    'target_layanan' => 'semua',
                    'judul'          => 'Rancangan Anggaran Riset Disusun',
                    'pesan'          => "Rancangan Percobaan & Anggaran Riset Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) sebesar Rp " . number_format($totalBiayaAktif, 0, ',', '.') . " telah disusun. Siap diterbitkan Surat Penawaran.",
                    'tipe'           => 'info',
                    'icon'           => 'bi-cash-stack',
                    'link_url'       => "/order/{$id}/penawaran/buat",
                    'created_by'     => $userId,
                    'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'PIC Peneliti'
                ]);
            } catch (\Exception $eNotif) {}

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
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $kalkulasiItems = $orderModel->getKalkulasiLingkungan($id);
        $daftarMetode = $this->db->exec("
            SELECT m.*, k.nama_kategori,
                   CASE 
                       WHEN m.kategori_id IN (5, 6, 7) THEN 1
                       WHEN m.kategori_id IN (3, 4) THEN 2
                       ELSE 3
                   END AS prioritas_layanan
            FROM metode_uji m 
            LEFT JOIN kategori_pengujian k ON k.id = m.kategori_id 
            WHERE m.status = 'aktif' 
            ORDER BY prioritas_layanan ASC, m.kategori_id ASC, m.id ASC
        ");
        $daftarLabEksternal = $this->db->exec("SELECT * FROM pengujian_eksternal WHERE status = 'aktif' ORDER BY nama_lembaga ASC");

        $isPic = ((int)$this->getUserId() === (int)($order['pic_proposal_id'] ?? 0));
        $canEdit = ($this->hasPermission('order:kalkulasi_biaya') || $this->isSuperadmin() || $this->isKetuaTim() || $this->isTimMitra() || $isPic);

        $f3->set('order', $order);
        $f3->set('kalkulasi_items', $kalkulasiItems);
        $f3->set('daftar_metode', $daftarMetode);
        $f3->set('daftar_lab_eksternal', $daftarLabEksternal);
        $f3->set('can_edit', $canEdit);

        $this->render('order/form_biaya_lingkungan.html', "Kalkulasi Biaya Pengujian Lingkungan", 'order');
    }

    /**
     * Memproses simpan kalkulasi multi-metode lingkungan
     * Route: POST /order/@id/biaya-lingkungan
     */
    public function biayaLingkunganPost($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        $isPic = ($order && (int)$this->getUserId() === (int)($order['pic_proposal_id'] ?? 0));
        if (!$this->hasPermission('order:kalkulasi_biaya') && !$this->isSuperadmin() && !$this->isKetuaTim() && !$this->isTimMitra() && !$isPic) {
            $this->setFlashError("Akses Ditolak: Perhitungan rincian pengujian merupakan wewenang Ketua Tim / Tim Pelaksana.");
            $f3->reroute("/order/{$id}/biaya-lingkungan");
            return;
        }

        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        $diskon = (float)($post['diskon_penawaran'] ?? 0.0);
        $tglSampel = !empty($post['tanggal_terima_sampel']) ? $post['tanggal_terima_sampel'] : null;
        $spmLayanan = !empty($post['spm_layanan']) ? trim($post['spm_layanan']) : null;

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
                        'durasi_nilai'     => (isset($item['durasi_nilai']) && $item['durasi_nilai'] !== '' && $item['durasi_nilai'] !== null) ? max(1, (int)$item['durasi_nilai']) : null,
                        'durasi_satuan'    => !empty($item['durasi_satuan']) ? trim($item['durasi_satuan']) : 'Hari',
                        'durasi_bulan'     => max(1, (int)($item['durasi_bulan'] ?? 1)),
                        'is_subkontrak'    => !empty($item['is_subkontrak']) ? 1 : 0,
                        'lab_eksternal_id' => !empty($item['lab_eksternal_id']) ? (int)$item['lab_eksternal_id'] : null
                    ];
                }
            }
        }

        try {
            $orderModel = new OrderLayanan($this->db);
            $hasil = $orderModel->simpanKalkulasiLingkungan($id, $items, $diskon, $tglSampel, $userId, $spmLayanan);
            $order = $orderModel->getDetail($id);

            // Kirim notifikasi ke Tim Mitra / Ka Tim
            try {
                $actionBtn = $post['action_btn'] ?? 'siap_penawaran';
                if ($actionBtn === 'kirim_katim') {
                    \NotificationService::send($this->db, [
                        'order_id'       => $id,
                        'target_role'    => 'ketua_tim',
                        'target_layanan' => 'lingkungan',
                        'judul'          => 'Kalkulasi Pengujian Diajukan',
                        'pesan'          => "Kalkulasi pengujian Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) menunggu pemeriksaan Ketua Tim.",
                        'tipe'           => 'primary',
                        'icon'           => 'bi-calculator-fill',
                        'link_url'       => "/order/{$id}",
                        'created_by'     => $userId,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Tim Pelaksana'
                    ]);
                } else {
                    \NotificationService::send($this->db, [
                        'order_id'       => $id,
                        'target_role'    => 'admin_order',
                        'target_layanan' => 'semua',
                        'judul'          => 'Kalkulasi Pengujian Lab Selesai',
                        'pesan'          => "Rincian biaya pengujian Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) sebesar Rp " . number_format($hasil['total_netto'], 0, ',', '.') . " siap dibuatkan penawaran resmi.",
                        'tipe'           => 'info',
                        'icon'           => 'bi-cash-stack',
                        'link_url'       => "/order/{$id}/penawaran/buat",
                        'created_by'     => $userId,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Tim Pelaksana'
                    ]);
                }
            } catch (\Exception $eNotif) {}

            $actionBtn = $post['action_btn'] ?? 'siap_penawaran';
            if ($actionBtn === 'kirim_katim') {
                $this->db->exec("UPDATE order_layanan SET status_proposal_biaya = 'menunggu_approval' WHERE id = ?", array(1 => $id));
                $this->setFlashSuccess("Kalkulasi biaya berhasil disimpan &amp; <strong>diajukan ke Ketua Tim OPTI</strong> untuk diperiksa.");
            } elseif ($actionBtn === 'siap_penawaran' || $this->isKetuaTim() || $this->isSuperadmin()) {
                $this->db->exec("UPDATE order_layanan SET status_proposal_biaya = 'siap_penawaran' WHERE id = ?", array(1 => $id));
                $this->setFlashSuccess(
                    "Kalkulasi parameter dan tarif pengujian berhasil disimpan &amp; disetujui! Status: <strong>Siap Penawaran</strong> (Total Netto: Rp " . number_format($hasil['total_netto'], 0, ',', '.') . "). Tim Mitra kini dapat menerbitkan Surat Penawaran Resmi."
                );
            } else {
                $this->db->exec("UPDATE order_layanan SET status_proposal_biaya = 'draft' WHERE id = ?", array(1 => $id));
                $this->setFlashSuccess(
                    "Draf kalkulasi biaya pengujian lingkungan berhasil disimpan (Total: <strong>Rp " . number_format($hasil['total_netto'], 0, ',', '.') . "</strong>)."
                );
            }
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

        $picProposalId = !empty($f3->get('POST.pic_proposal_id')) ? (int)$f3->get('POST.pic_proposal_id') : 0;
        $sdmTersedia   = !empty($f3->get('POST.sdm_tersedia')) ? 1 : 0;
        $alatTersedia  = (!empty($f3->get('POST.peralatan_tersedia')) || !empty($f3->get('POST.alat_tersedia'))) ? 1 : 0;
        $bahanTersedia = (!empty($f3->get('POST.bahan_tersedia')) || !empty($f3->get('POST.bahan_kimia_tersedia'))) ? 1 : 0;
        $metodeTersedia= (!empty($f3->get('POST.metode_tersedia')) || !empty($f3->get('POST.metode_uji_tersedia'))) ? 1 : 0;
        $sdmCatatan    = trim($f3->get('POST.sdm_catatan') ?? '');

        try {
            // Update PIC Pelaksana jika dipilih
            if ($picProposalId > 0) {
                $this->db->exec("UPDATE order_layanan SET pic_proposal_id = ? WHERE id = ?", [1 => $picProposalId, 2 => $id]);
            }

            // Simpan / Update Checklist SOP Audit Balai
            $chkTinjauan = $this->db->exec("SELECT id FROM opti_tinjauan_kelayakan WHERE order_id = ?", [1 => $id]);
            if (!empty($chkTinjauan)) {
                $this->db->exec(
                    "UPDATE opti_tinjauan_kelayakan 
                     SET sdm_tersedia = ?, peralatan_tersedia = ?, bahan_tersedia = ?, metode_tersedia = ?, sdm_catatan = ? 
                     WHERE order_id = ?",
                    [1 => $sdmTersedia, 2 => $alatTersedia, 3 => $bahanTersedia, 4 => $metodeTersedia, 5 => $sdmCatatan, 6 => $id]
                );
            } else {
                $this->db->exec(
                    "INSERT INTO opti_tinjauan_kelayakan 
                     (order_id, sdm_tersedia, peralatan_tersedia, bahan_tersedia, metode_tersedia, sdm_catatan, keputusan, ditinjau_oleh, tanggal_tinjauan) 
                     VALUES (?, ?, ?, ?, ?, ?, 'dapat_dilaksanakan', ?, NOW())",
                    [1 => $id, 2 => $sdmTersedia, 3 => $alatTersedia, 4 => $bahanTersedia, 5 => $metodeTersedia, 6 => $sdmCatatan, 7 => $this->getUserId() ?? 1]
                );
            }

            $orderModel = new OrderLayanan($this->db);
            $hasil = $orderModel->approve($id, $nomorPo, $biaya);
            $order = $orderModel->getDetail($id);

            // Kirim notifikasi ke Tim Kerja & Ka Tim
            try {
                \NotificationService::send($this->db, [
                    'order_id'       => $id,
                    'po_id'          => $hasil['po_id'] ?? null,
                    'target_role'    => 'tim_kerja',
                    'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                    'judul'          => 'PO Resmi Aktif - Pengujian Lab Dimulai',
                    'pesan'          => "Dokumen PO #{$hasil['nomor_po']} untuk Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) resmi aktif! Pelaksanaan riset/pengujian dapat dimulai.",
                    'tipe'           => 'success',
                    'icon'           => 'bi-gear-wide-connected',
                    'link_url'       => (!empty($hasil['po_id']) ? "/po/{$hasil['po_id']}" : "/po"),
                    'created_by'     => $this->getUserId() ?? 1,
                    'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Pimpinan Balai'
                ]);

                \NotificationService::send($this->db, [
                    'order_id'       => $id,
                    'po_id'          => $hasil['po_id'] ?? null,
                    'target_role'    => 'ketua_tim',
                    'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                    'judul'          => 'PO Resmi Diterbitkan',
                    'pesan'          => "PO #{$hasil['nomor_po']} untuk Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) telah disetujui & diterbitkan.",
                    'tipe'           => 'success',
                    'icon'           => 'bi-patch-check-fill',
                    'link_url'       => (!empty($hasil['po_id']) ? "/po/{$hasil['po_id']}" : "/po"),
                    'created_by'     => $this->getUserId() ?? 1,
                    'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Pimpinan Balai'
                ]);
            } catch (\Exception $eNotif) {}

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

            // Sinkronisasi divisi ke tb_arsipsurat (arsip persuratan)
            if (!empty($order->id_surat_masuk)) {
                $namaLayananArsip = ($jenisOpti === 'selulosa') ? 'OPTI_Selulosa' : 'OPTI_lingkungan';
                try {
                    $this->db->exec(
                        "UPDATE tb_arsipsurat SET nama_layanan = ? WHERE id_arsip = ?",
                        [1 => $namaLayananArsip, 2 => (int)$order->id_surat_masuk]
                    );
                } catch (\Exception $eArsip) {}
            }

            $optiNama = $jenisOpti === 'selulosa' ? 'OPTI Selulosa' : 'OPTI Lingkungan';
            $this->setFlashSuccess("Permohonan berhasil didisposisikan ke <strong>{$optiNama}</strong>. Status order kini beralih menjadi <strong>Order Aktif</strong>.");
            $f3->reroute("/order/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError("Gagal mendisposisikan order: " . $e->getMessage());
            $f3->reroute("/order/{$id}");
        }
    }

    /**
     * Menampilkan Formulir Permintaan Pelayanan Jasa untuk Tim Mitra
     * Route: GET /order/@id/form-pelayanan
     */
    public function formPelayanan($f3, $params) {
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        // Sinkronisasi data dari Surat Masuk jika order berasal dari klaim surat
        if (!empty($order['id_surat_masuk'])) {
            $suratMasukData = null;
            try {
                $repoSurat = new \SuratMasukRepository($this->db, $this->dbSekretariat);
                $suratMasukData = $repoSurat->getSuratById((int)$order['id_surat_masuk']);
            } catch (\Exception $e) {}

            if ($suratMasukData) {
                $picSurat = trim($suratMasukData['pic_pengirim'] ?? ($suratMasukData['nama_pengirim'] ?? ''));
                if (!empty($picSurat)) {
                    $order['pic'] = $picSurat;
                }
                $alamatSurat = trim($suratMasukData['alamat_pengirim'] ?? '');
                if (!empty($alamatSurat)) {
                    $order['alamat'] = $alamatSurat;
                }
                if (!empty($suratMasukData['pengirim'])) {
                    $ptPrefix = !empty($suratMasukData['pt_cv']) ? $suratMasukData['pt_cv'] . ' ' : '';
                    $order['nama_perusahaan'] = $ptPrefix . $suratMasukData['pengirim'];
                    $order['nmcustomer'] = $suratMasukData['pengirim'];
                    $order['pt_cv'] = $suratMasukData['pt_cv'] ?? $order['pt_cv'];
                }
            }
        }

        $spModel = new SuratPenawaran($this->db);
        $spExisting = $spModel->getByOrderId($id);

        $sp = new \DB\SQL\Mapper($this->db, 'tb_surat_penawaran');
        if (!empty($spExisting['id'])) {
            $sp->load(['id = ?', (int)$spExisting['id']]);
            // Jika surat penawaran nama/perusahaan masih kosong atau draft awal, pastikan sesuai dengan PIC surat
            if (empty($sp->nama) || $sp->nama === '-' || $sp->nama === '—') {
                $sp->nama = ($order['pic'] !== '-' && !empty($order['pic'])) ? $order['pic'] : '';
            }
            if (empty($sp->perusahaan) || $sp->perusahaan === '-' || $sp->perusahaan === '—') {
                $sp->perusahaan = $order['nama_perusahaan'] ?: '';
            }
            if (empty($sp->alamat) || $sp->alamat === '-' || $sp->alamat === '—') {
                $sp->alamat = ($order['alamat'] !== '-' && !empty($order['alamat'])) ? $order['alamat'] : '';
            }
        } else {
            $sp->order_id            = $id;
            $sp->nama                = ($order['pic'] !== '-' && !empty($order['pic'])) ? $order['pic'] : '';
            $sp->perusahaan          = $order['nama_perusahaan'] ?: '';
            $sp->alamat              = ($order['alamat'] !== '-' && !empty($order['alamat'])) ? $order['alamat'] : '';
            $sp->nomor_surat         = $spModel->generateNomorSurat();
            $sp->tanggal_surat       = date('Y-m-d');
            $sp->perihal             = 'Permintaan Pelayanan Jasa OPTI - ' . $order['judul_kegiatan'];
            $sp->nominal_penawaran   = (float)($order['estimasi_biaya'] ?: 0);
            $sp->jenis_layanan       = ($order['jenis_layanan_opti'] === 'lingkungan') ? 'lingkungan' : 'selulosa';
            $sp->permintaan_melalui  = 'surat';
            $sp->status_respon_klien = 'draft';
            $sp->status              = 'draft';
            $sp->penjelasan          = $order['deskripsi'] ?: '';
        }

        $arsipUser = new \DB\SQL\Mapper($this->db, 'tb_arsipuser');
        $daftarPegawai = $arsipUser->find(null, ['order' => 'nama_user ASC']);

        $canEdit = ($this->hasPermission('order:form_pelayanan') || $this->isSuperadmin());

        $tinjauan = $orderModel->getTinjauanKelayakan($id);

        $f3->set('order', $order);
        $f3->set('sp', $sp);
        $f3->set('tinjauan', $tinjauan);
        $f3->set('daftar_pegawai', $daftarPegawai);
        $f3->set('can_edit', $canEdit);
        $f3->set('opsi_permintaan', [
            'telepon'          => 'Telepon',
            'fax'              => 'Fax',
            'surat'            => 'Surat',
            'email'            => 'E-mail',
            'datang_langsung'  => 'Datang langsung',
            'pegawai_bbspjis'  => 'Pegawai BBSPJIS',
        ]);
        $f3->set('opsi_bidang', [
            'riset'            => 'Riset',
            'standardisasi'    => 'Standardisasi',
            'pengujian'        => 'Pengujian',
            'sertifikasi'      => 'Sertifikasi',
            'kalibrasi'        => 'Kalibrasi',
            'konsultansi'      => 'Konsultansi',
            'pelatihan_teknis' => 'Pelatihan Teknis',
            'perekayasaan'     => 'Perekayasaan',
            'lainnya'          => 'Lainnya',
        ]);
        $f3->set('opsi_kirim_ke', [
            'selulosa'    => 'Selulosa',
            'lingkungan'  => 'Lingkungan',
        ]);

        $this->render('tim_mitra/surat Pelayanan/form.html', 'Formulir Pelayanan Jasa', 'order');
    }

    /**
     * Memproses simpan Formulir Permintaan Pelayanan Jasa
     * Route: POST /order/@id/form-pelayanan
     */
    public function formPelayananPost($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        if (!$this->hasPermission('order:form_pelayanan') && !$this->isSuperadmin()) {
            $this->setFlashError("Akses Ditolak: Pengisian formulir pelayanan jasa merupakan wewenang Tim Mitra.");
            $f3->reroute("/order/{$id}/form-pelayanan");
            return;
        }

        $post = $f3->get('POST');

        $aksi = $post['aksi'] ?? ($post['action_btn'] ?? 'simpan');
        $rawJenis = $post['jenis_layanan'] ?? ($post['jenis_layanan_opti'] ?? '');
        if ($actionBtn === 'kirim_katim' && !in_array($rawJenis, ['selulosa', 'lingkungan'])) {
            $this->setFlashError("Silakan pilih Divisi OPTI (OPTI Selulosa atau OPTI Lingkungan) sebelum meneruskan ke Ketua Tim.");
            $f3->reroute("/order/{$id}/form-pelayanan");
            return;
        }
        $jenisLayanan = in_array($rawJenis, ['selulosa', 'lingkungan']) ? $rawJenis : 'belum_ditentukan';
        $nama = trim($post['nama'] ?? '');
        $perusahaan = trim($post['perusahaan'] ?? '');
        $alamat = trim($post['alamat'] ?? '');
        $penjelasan = trim($post['penjelasan'] ?? ($post['deskripsi'] ?? ''));
        $permintaanMelalui = trim($post['permintaan_melalui'] ?? 'email');
        $pegawaiId = !empty($post['pegawai_id']) ? (int)$post['pegawai_id'] : null;

        // Parameter Kaji Kelayakan
        $keputusan = $post['keputusan_kelayakan'] ?? 'dapat_dilaksanakan';
        $sdmTersedia = !empty($post['sdm_tersedia']) ? 1 : 0;
        $peralatanTersedia = (!empty($post['peralatan_tersedia']) || !empty($post['alat_tersedia'])) ? 1 : 0;
        $bahanTersedia = (!empty($post['bahan_tersedia']) || !empty($post['bahan_kimia_tersedia'])) ? 1 : 0;
        $metodeTersedia = (!empty($post['metode_tersedia']) || !empty($post['metode_uji_tersedia'])) ? 1 : 0;
        $catatanKelayakan = trim($post['catatan_kelayakan'] ?? '');
        $alasanPenolakan = trim($post['alasan_penolakan'] ?? '');
        $userId = $this->getUserId() ?? 1;

        $sertakanKaji = !empty($post['sertakan_kaji_kelayakan']) && $post['sertakan_kaji_kelayakan'] === '1';

        // Validasi: Alasan penolakan wajib diisi jika keputusan tidak dapat dilaksanakan dan kaji kelayakan disertakan
        if ($actionBtn === 'kirim_katim' && $sertakanKaji && $keputusan === 'tidak_dapat_dilaksanakan' && empty($alasanPenolakan)) {
            $this->setFlashError("Alasan penolakan wajib diisi jika permohonan 'Tidak Dapat Dilaksanakan'.");
            $f3->reroute("/order/{$id}/form-pelayanan");
            return;
        }

        try {
            $orderModel = new OrderLayanan($this->db);
            $order = $orderModel->getById($id);
            if (!$order || $order->dry()) {
                throw new \Exception("Order #{$id} tidak ditemukan.");
            }

            $order->jenis_layanan_opti = $jenisLayanan;
            if (!empty($penjelasan)) $order->deskripsi = $penjelasan;

            // Update tb_customer jika ada perubahan data pelanggan
            if (!empty($order->id_customer)) {
                $custUpdates = [];
                $custParams = [];
                if (!empty($perusahaan)) {
                    $custUpdates[] = "nmcustomer = ?";
                    $custParams[] = $perusahaan;
                }
                if (!empty($nama)) {
                    $custUpdates[] = "contactperson_opti = ?";
                    $custParams[] = $nama;
                }
                if (!empty($alamat)) {
                    $custUpdates[] = "alamatcustomer = ?";
                    $custParams[] = $alamat;
                }
                if (!empty($custUpdates)) {
                    $custParams[] = $order->id_customer;
                    $this->db->exec("UPDATE tb_customer SET " . implode(', ', $custUpdates) . " WHERE id_customer = ?", $custParams);
                }
            }

            // Sinkronisasi ke tb_surat_penawaran jika ada
            try {
                $spModel = new \DB\SQL\Mapper($this->db, 'tb_surat_penawaran');
                $spModel->load(['order_id = ?', $id]);
                if (!$spModel->dry()) {
                    if (!empty($nama)) $spModel->nama = $nama;
                    if (!empty($perusahaan)) $spModel->perusahaan = $perusahaan;
                    if (!empty($alamat)) $spModel->alamat = $alamat;
                    if (!empty($penjelasan)) $spModel->penjelasan = $penjelasan;
                    $spModel->permintaan_melalui = $permintaanMelalui;
                    $spModel->pegawai_id = $pegawaiId;
                    $spModel->jenis_layanan = $jenisLayanan;
                    $spModel->save();
                }
            } catch (\Exception $eSp) {}

            if ($actionBtn === 'kirim_katim') {
                if ($sertakanKaji) {
                    // Simpan hasil Kaji Kelayakan secara terpadu jika diaktifkan Tim Mitra
                    $tinjauanData = [
                        'sdm_tersedia'       => $sdmTersedia,
                        'sdm_catatan'        => $catatanKelayakan,
                        'peralatan_tersedia' => $peralatanTersedia,
                        'peralatan_catatan'  => $catatanKelayakan,
                        'bahan_tersedia'     => $bahanTersedia,
                        'bahan_catatan'      => $catatanKelayakan,
                        'metode_tersedia'    => $metodeTersedia,
                        'metode_catatan'     => $catatanKelayakan,
                        'keputusan'          => $keputusan,
                        'alasan_penolakan'   => $alasanPenolakan
                    ];
                    $orderModel->simpanTinjauanKelayakan($id, $tinjauanData, $userId);

                    // Muat ulang instance order setelah tinjauan disimpan
                    $order->load(['id = ?', $id]);
                    $order->jenis_layanan_opti = $jenisLayanan;
                    if (!empty($penjelasan)) $order->deskripsi = $penjelasan;

                    if ($keputusan === 'dapat_dilaksanakan') {
                        $order->status = 'baru';
                        $order->status_tinjauan = 'layak';
                        $order->status_proposal_biaya = 'draft';
                        $order->save();
                        $this->setFlashSuccess("Permintaan Pelayanan Jasa &amp; Kaji Kelayakan berhasil disimpan (Status: <strong>Order Aktif</strong> - Divisi " . ucfirst($jenisLayanan) . "). Silakan lanjutkan ke Step 4: Perhitungan Biaya / Parameter Uji.");
                    } else {
                        $order->status = 'ditolak';
                        $order->status_tinjauan = 'tidak_layak';
                        $order->alasan_tolak = $alasanPenolakan;
                        $order->tanggal_tolak = date('Y-m-d H:i:s');
                        $order->ditolak_oleh = $userId;
                        $order->save();
                        $this->setFlashWarning("Permintaan Pelayanan Jasa ditandai <strong>Tidak Dapat Dilaksanakan (Ditolak)</strong>.");
                    }
                } else {
                    // STANDAR SOP: Tim Mitra hanya mendisposisikan ke Divisi OPTI
                    // Hapus kaji kelayakan sementara sebelumnya jika ada agar bersih untuk kaji ulang Ketua Tim
                    $this->db->exec("DELETE FROM opti_tinjauan_kelayakan WHERE order_id = ?", [1 => $id]);

                    $order->status = 'baru';
                    $order->jenis_layanan_opti = $jenisLayanan;
                    $order->status_tinjauan = 'menunggu';
                    $order->status_proposal_biaya = 'draft';
                    $order->pic_proposal_id = null; // Biarkan Ketua Tim yang menunjuk PIC
                    if (!empty($penjelasan)) $order->deskripsi = $penjelasan;
                    $order->save();

                    $this->setFlashSuccess("Formulir Pelayanan Jasa berhasil dikirim ke <strong>Ketua Tim OPTI " . ucfirst($jenisLayanan) . "</strong> untuk Kaji Ulang Kelayakan Teknis &amp; Penunjukan PIC (Tahap 3).");
                }
            } else {
                $order->status = 'draft_disimpan';
                $order->save();
                $this->setFlashSuccess("Draf Surat Permintaan Pelayanan Jasa berhasil disimpan (Status: <strong>Draft Disimpan</strong>).");
            }

            // Sinkronisasi divisi ke tb_arsipsurat jika order berasal dari surat masuk
            if (!empty($order->id_surat_masuk)) {
                $namaLayananArsip = ($jenisLayanan === 'selulosa') ? 'OPTI_Selulosa' : 'OPTI_lingkungan';
                try {
                    $this->db->exec(
                        "UPDATE tb_arsipsurat SET nama_layanan = ? WHERE id_arsip = ?",
                        [1 => $namaLayananArsip, 2 => (int)$order->id_surat_masuk]
                    );
                } catch (\Exception $eArsip) {}
            }

            $f3->reroute("/order/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError("Gagal menyimpan form pelayanan: " . $e->getMessage());
            $f3->reroute("/order/{$id}/form-pelayanan");
        }
    }

    /**
     * Halaman Pusat Tugas Proposal Teknis (Khusus PIC & Monitoring Ka Tim / Superadmin)
     * Route: GET /proposal
     */
    public function proposalIndex($f3) {
        $this->requireAuth();
        $userId = (int)$this->getUserId();
        $userRole = $this->getUserRole();
        $isSuperadmin = $this->isSuperadmin();
        $isKetuaTim = $this->isKetuaTim();
        $isTimKerja = ($userRole === 'tim_kerja');

        // Parameter filter status
        $filterStatus = $f3->get('GET.status') ?: 'semua';
        $filterSearch = trim($f3->get('GET.q') ?? '');

        $sql = "SELECT o.id, o.nomor_order, c.nmcustomer AS nama_perusahaan, o.judul_kegiatan, 
                       o.jenis_layanan_opti, o.spm_layanan, o.tanggal_masuk, o.status,
                       o.pic_proposal_id, o.status_proposal_biaya,
                       p.id AS proposal_id, p.judul_proposal, p.durasi_kegiatan, 
                       p.estimasi_total_biaya, p.file_proposal, p.status_proposal, 
                       p.catatan_revisi, p.disetujui_ketua_at,
                       u.nama_user AS pic_nama
                FROM order_layanan o
                LEFT JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN opti_proposal_riset p ON o.id = p.order_id
                LEFT JOIN tb_arsipuser u ON o.pic_proposal_id = u.id_user
                WHERE 1=1 
                  AND (o.status_tinjauan = 'layak' OR o.id IN (SELECT order_id FROM opti_tinjauan_kelayakan WHERE keputusan = 'dapat_dilaksanakan')) ";
        
        $params = [];

        // Scope Hak Akses Khusus:
        // 1. Jika Tim Kerja (PIC Peneliti): HANYA tampilkan proposal yang ditugaskan ke dirinya!
        if ($isTimKerja && !$isSuperadmin) {
            $sql .= " AND o.pic_proposal_id = ? ";
            $params[] = $userId;
        } elseif ($isKetuaTim && !$isSuperadmin) {
            // 2. Jika Ketua Tim: Tampilkan seluruh proposal di divisinya
            $layanan = $_SESSION['jenis_layanan_opti'] ?? '';
            if ($layanan) {
                $sql .= " AND o.jenis_layanan_opti = ? ";
                $params[] = $layanan;
            }
            $sql .= " AND o.pic_proposal_id IS NOT NULL ";
        } else {
            // 3. Superadmin / Pejabat: Tampilkan seluruh order yang memiliki penugasan PIC
            $sql .= " AND o.pic_proposal_id IS NOT NULL ";
        }

        // Filter status proposal
        if ($filterStatus !== 'semua') {
            if ($filterStatus === 'draft' || $filterStatus === 'draft_disimpan') {
                $sql .= " AND (p.status_proposal IN ('draft', 'draft_disimpan') OR p.status_proposal IS NULL) ";
            } elseif ($filterStatus === 'diajukan') {
                $sql .= " AND p.status_proposal = 'diajukan' ";
            } elseif ($filterStatus === 'disetujui') {
                $sql .= " AND p.status_proposal = 'disetujui_ketua' ";
            } elseif ($filterStatus === 'ditolak') {
                $sql .= " AND p.status_proposal = 'ditolak' ";
            }
        }

        // Filter pencarian teks
        if (!empty($filterSearch)) {
            $sql .= " AND (o.nomor_order LIKE ? OR c.nmcustomer LIKE ? OR o.judul_kegiatan LIKE ? OR p.judul_proposal LIKE ?) ";
            $term = "%{$filterSearch}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY o.id DESC";

        $binds = [];
        foreach ($params as $idx => $val) {
            $binds[$idx + 1] = $val;
        }

        $listProposal = $this->db->exec($sql, $binds);

        // Counter Statistik
        $statDraft = 0;
        $statDiajukan = 0;
        $statDisetujui = 0;
        $statDitolak = 0;

        foreach ($listProposal as $item) {
            $st = $item['status_proposal'] ?? 'draft';
            if ($st === 'diajukan') $statDiajukan++;
            elseif ($st === 'disetujui_ketua') $statDisetujui++;
            elseif ($st === 'ditolak') $statDitolak++;
            else $statDraft++;
        }

        $f3->set('list_proposal', $listProposal);
        $f3->set('total_proposal', count($listProposal));
        $f3->set('stat_draft', $statDraft);
        $f3->set('stat_diajukan', $statDiajukan);
        $f3->set('stat_disetujui', $statDisetujui);
        $f3->set('stat_ditolak', $statDitolak);
        $f3->set('filter_status', $filterStatus);
        $f3->set('filter_q', $filterSearch);
        $f3->set('is_tim_kerja', $isTimKerja);

        $this->render('order/proposal_index.html', 'Pusat Tugas Proposal Teknis', 'proposal');
    }

    /**
     * Halaman Unggah & Penyusunan Dokumen Proposal Teknis oleh PIC
     * Route: GET /order/@id/proposal
     */
    public function proposalForm($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $userId = (int)$this->getUserId();
        $userRole = $this->getUserRole();
        $isPic = ($userId > 0 && (int)($order['pic_proposal_id'] ?? 0) === $userId);
        $isSuperadmin = $this->isSuperadmin();
        $isKetuaTim = $this->isKetuaTim();

        // Strict Access Control:
        // Jika user adalah Tim Kerja (PIC), HANYA PIC yang ditugaskan yang boleh membuka proposal ini!
        if ($userRole === 'tim_kerja' && !$isPic && !$isSuperadmin) {
            $this->setFlashError("Akses Ditolak: Anda bukan PIC yang ditugaskan untuk proposal Order #{$order['nomor_order']}. Anda hanya berwenang mengerjakan proposal yang ditugaskan secara khusus kepada Anda.");
            $f3->reroute('/proposal');
            return;
        }

        $proposal = $orderModel->getProposalRiset($id);
        $tinjauan = $orderModel->getTinjauanKelayakan($id);

        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak')
        );
        $proposalDisetujui = (
            (!empty($proposal) && in_array($proposal['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])) ||
            in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui'])
        );

        $canEdit = ($isPic || $isSuperadmin || $this->hasPermission('order:proposal'));
        $canReview = ($isKetuaTim || $isSuperadmin);

        $lockMessage = '';
        if ($proposalDisetujui && !$isSuperadmin) {
            $canEdit = false;
            $lockMessage = "Dokumen proposal teknis telah disetujui oleh Ketua Tim OPTI. Formulir terkunci untuk persiapan penerbitan Surat Penawaran Resmi.";
        } elseif (!$tinjauanSelesai) {
            $canEdit = false;
            $lockMessage = "Kaji Ulang Kelayakan Teknis (Tahap 2) belum selesai atau diputuskan 'Tidak Dapat Dilaksanakan'. Dokumen proposal belum dapat disusun atau diedit.";
        } elseif (!$canEdit) {
            $lockMessage = "Penyusunan dan pengunggahan dokumen proposal teknis merupakan wewenang PIC Proposal yang ditugaskan.";
        }

        // Ambil data surat masuk jika ada
        $suratMasuk = null;
        if (!empty($order['id_surat_masuk'])) {
            try {
                $repoSurat = new \SuratMasukRepository($this->db, $this->dbSekretariat);
                $suratMasuk = $repoSurat->getSuratById((int)$order['id_surat_masuk']);
            } catch (\Exception $e) {}
        }
        if (!$suratMasuk && !empty($order['nama_perusahaan'])) {
            try {
                $rowsSm = $this->db->exec("SELECT * FROM surat_masuk WHERE pengirim LIKE ? ORDER BY id DESC LIMIT 1", [1 => '%' . $order['nama_perusahaan'] . '%']);
                if (!empty($rowsSm)) {
                    $suratMasuk = $rowsSm[0];
                }
            } catch (\Exception $e) {}
        }

        // Parse durasi kegiatan ke angka Hari Kerja
        $durasiStr = $proposal['durasi_kegiatan'] ?? '30 Hari Kerja';
        $durasiHari = 30;
        if (preg_match('/^(\d+)/', trim($durasiStr), $matches)) {
            $durasiHari = (int)$matches[1];
        }

        // Ambil jejak audit & riwayat aktivitas order/proposal
        $activityLogs = $this->db->exec(
            "SELECT * FROM opti_activity_log WHERE order_id = ? ORDER BY id DESC",
            [1 => $id]
        );

        $f3->set('order', $order);
        $f3->set('proposal', $proposal);
        $f3->set('surat_masuk', $suratMasuk);
        $f3->set('tinjauan', $tinjauan);
        $f3->set('activity_logs', $activityLogs);
        $f3->set('durasi_hari', $durasiHari);
        $f3->set('is_pic', $isPic);
        $f3->set('can_edit', $canEdit);
        $f3->set('can_review', $canReview);
        $f3->set('lock_message', $lockMessage);
        $proposalHasFileAndCost = !empty($proposal) && !empty($proposal['file_proposal']) && (float)($proposal['estimasi_total_biaya'] ?? 0) > 0;
        $f3->set('proposal_has_file_cost', $proposalHasFileAndCost);

        $this->render('order/proposal.html', "Dokumen Proposal Teknis - Order #{$order['nomor_order']}", 'proposal');
    }

    /**
     * Simpan / Unggah Dokumen Proposal Teknis & Ajukan ke Ketua Tim
     * Route: POST /order/@id/proposal/simpan
     */
    public function proposalSimpan($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/proposal');
            return;
        }

        $userId = (int)$this->getUserId();
        $userRole = $this->getUserRole();
        $isPic = ($userId > 0 && (int)($order['pic_proposal_id'] ?? 0) === $userId);
        
        // Strict Access Control:
        if ($userRole === 'tim_kerja' && !$isPic && !$this->isSuperadmin()) {
            $this->setFlashError("Akses Ditolak: Anda bukan PIC yang ditugaskan untuk proposal ini.");
            $f3->reroute('/proposal');
            return;
        }

        // 1. Cek apakah proposal sudah disetujui Ka. Tim (Terkunci)
        $existing = $orderModel->getProposalRiset($id);
        $proposalDisetujui = (
            ($existing && in_array($existing['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])) ||
            in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui'])
        );
        $actionType = $f3->get('POST.action_type') ?? 'draft'; // 'draft' atau 'ajukan'

        if ($proposalDisetujui && !$this->isSuperadmin() && $actionType !== 'revisi') {
            $this->setFlashError("Gagal: Dokumen proposal ini telah disetujui oleh Ketua Tim OPTI dan terkunci. Perubahan tidak dapat dilakukan.");
            $f3->reroute("/order/{$id}/proposal");
            return;
        }

        // 2. Cek Prasyarat Kaji Ulang (Tahap 2)
        $tinjauan = $orderModel->getTinjauanKelayakan($id);
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak')
        );
        if (!$tinjauanSelesai) {
            $this->setFlashError("Gagal: Kaji Ulang Kelayakan Teknis (Tahap 2) belum selesai atau berstatus 'Tidak Dapat Dilaksanakan'. Proposal belum dapat disimpan.");
            $f3->reroute("/order/{$id}/proposal");
            return;
        }

        $post = $f3->get('POST');
        $judulProposal = trim($post['judul_proposal'] ?? ($order['judul_kegiatan'] ?? 'Proposal Teknis OPTI'));
        $ruangLingkup = trim($post['ruang_lingkup'] ?? '');
        
        $durasiHari = (int)($post['durasi_hari'] ?? ($post['durasi_angka'] ?? 30));
        if ($durasiHari <= 0) $durasiHari = 30;
        $durasiKegiatan = "{$durasiHari} Hari Kerja";

        $rawBiaya = str_replace(['Rp', '.', ' '], '', $post['estimasi_total_biaya'] ?? '0');
        $rawBiaya = str_replace(',', '.', $rawBiaya);
        $estimasiBiaya = (float)$rawBiaya;

        // 3. Handle File Upload dengan validasi ukuran maksimal (10 MB)
        $filePath = '';
        $files = $f3->get('FILES');
        if (!empty($files['file_proposal']['name'])) {
            $file = $files['file_proposal'];

            // Cek error bawaan PHP upload
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = 'Terjadi kesalahan saat mengunggah file dokumen proposal.';
                if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $errorMsg = 'Ukuran file melebihi batas maksimal server yang diperbolehkan.';
                }
                $this->setFlashError($errorMsg);
                $f3->reroute("/order/{$id}/proposal");
                return;
            }

            // Validasi ukuran maksimal (10 MB)
            $maxFileSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxFileSize) {
                $this->setFlashError('Ukuran file melebihi batas maksimal 10 MB. Harap perkecil atau kompres dokumen Anda.');
                $f3->reroute("/order/{$id}/proposal");
                return;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
            if (!in_array($ext, $allowed)) {
                $this->setFlashError('Format file tidak didukung. Harap upload format PDF, Word (DOC/DOCX), atau Excel (XLS/XLSX).');
                $f3->reroute("/order/{$id}/proposal");
                return;
            }

            $uploadDir = 'public/uploads/proposals/';
            if (!is_dir('c:/xampp/htdocs/Mini OPTI Tracker/' . $uploadDir)) {
                @mkdir('c:/xampp/htdocs/Mini OPTI Tracker/' . $uploadDir, 0777, true);
            }

            $newFileName = 'Proposal_Order_' . $id . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $newFileName;
            $fullDest = 'c:/xampp/htdocs/Mini OPTI Tracker/' . $destPath;

            if (move_uploaded_file($file['tmp_name'], $fullDest)) {
                $filePath = $destPath;
            }
        }

        if (empty($filePath) && $existing && !empty($existing['file_proposal'])) {
            $filePath = $existing['file_proposal'];
        }

        // Validasi jika diajukan ke Ketua Tim: Dokumen proposal fisik wajib diunggah
        if ($actionType === 'ajukan') {
            if (empty($filePath)) {
                $this->setFlashError("Gagal: Berkas dokumen proposal resmi (.pdf/.doc/.docx/.xls/.xlsx) wajib diunggah sebelum dapat diajukan ke Ketua Tim.");
                $f3->reroute("/order/{$id}/proposal");
                return;
            }
            if (($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
                if ($estimasiBiaya <= 0) {
                    $this->setFlashError("Gagal: Estimasi total biaya/anggaran wajib diisi dan harus lebih dari Rp 0 sebelum proposal dapat diajukan.");
                    $f3->reroute("/order/{$id}/proposal");
                    return;
                }
                if (empty($ruangLingkup)) {
                    $this->setFlashError("Gagal: Ruang lingkup riset/kegiatan wajib diisi sebelum proposal dapat diajukan ke Ketua Tim.");
                    $f3->reroute("/order/{$id}/proposal");
                    return;
                }
            }
        }

        $statusProposal = ($actionType === 'ajukan') ? 'diajukan' : 'draft_disimpan';
        if ($statusProposal === 'ditolak' && $actionType === 'ajukan') {
            $statusProposal = 'diajukan';
        }

        try {
            $userNama = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama_user'] ?? 'PIC Peneliti');

            if ($existing) {
                $this->db->exec(
                    "UPDATE opti_proposal_riset SET 
                        judul_proposal = ?, ruang_lingkup = ?, durasi_kegiatan = ?, 
                        estimasi_total_biaya = ?, file_proposal = ?, status_proposal = ?,
                        " . ($actionType === 'ajukan' ? "diajukan_at = NOW(), diajukan_oleh = " . (int)$userId . "," : "") . "
                        updated_at = NOW(), updated_by = ?
                     WHERE order_id = ?",
                    [
                        1 => $judulProposal,
                        2 => $ruangLingkup,
                        3 => $durasiKegiatan,
                        4 => $estimasiBiaya,
                        5 => $filePath,
                        6 => $statusProposal,
                        7 => $userId,
                        8 => $id
                    ]
                );
            } else {
                $this->db->exec(
                    "INSERT INTO opti_proposal_riset (
                        order_id, pic_penyusun_id, spesialisasi, judul_proposal, 
                        ruang_lingkup, durasi_kegiatan, estimasi_total_biaya, file_proposal, status_proposal,
                        diajukan_at, diajukan_oleh, updated_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, " . ($actionType === 'ajukan' ? "NOW(), ?, ?" : "NULL, NULL, ?") . ")",
                    $actionType === 'ajukan' ? [
                        1 => $id,
                        2 => $order['pic_proposal_id'] ?: $userId,
                        3 => $order['jenis_layanan_opti'],
                        4 => $judulProposal,
                        5 => $ruangLingkup,
                        6 => $durasiKegiatan,
                        7 => $estimasiBiaya,
                        8 => $filePath,
                        9 => $statusProposal,
                        10 => $userId,
                        11 => $userId
                    ] : [
                        1 => $id,
                        2 => $order['pic_proposal_id'] ?: $userId,
                        3 => $order['jenis_layanan_opti'],
                        4 => $judulProposal,
                        5 => $ruangLingkup,
                        6 => $durasiKegiatan,
                        7 => $estimasiBiaya,
                        8 => $filePath,
                        9 => $statusProposal,
                        10 => $userId
                    ]
                );
            }

            // Sinkronkan status dan estimasi biaya ke tabel order_layanan
            $statusProposalBiaya = ($actionType === 'ajukan') ? 'menunggu_approval' : 'draft_disimpan';
            $this->db->exec(
                "UPDATE order_layanan SET estimasi_biaya = ?, status_proposal_biaya = ? WHERE id = ?",
                [1 => $estimasiBiaya, 2 => $statusProposalBiaya, 3 => $id]
            );

            // Audit Trail Activity Log
            if ($actionType === 'ajukan') {
                $this->logActivity($id, 'proposal', 'ajukan_ke_ketua', "Dokumen proposal teknis resmi diajukan ke Ketua Tim OPTI oleh {$userNama} (PIC Peneliti).");
            } else {
                $this->logActivity($id, 'proposal', 'simpan_draft', "Draf dokumen proposal teknis disimpan (Status: Draft Disimpan) oleh {$userNama} (PIC Peneliti).");
            }

            // Jika diajukan, kirim notifikasi ke Ka. Tim OPTI & Superadmin
            if ($actionType === 'ajukan') {
                try {
                    \NotificationService::send($this->db, [
                        'order_id'       => $id,
                        'target_role'    => 'ketua_tim',
                        'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                        'judul'          => 'Proposal Teknis Siap Diperiksa',
                        'pesan'          => "PIC Proposal ({$userNama}) telah mengajukan dokumen proposal untuk Order #{$order['nomor_order']} ({$order['nama_perusahaan']}). Mohon periksa dan berikan persetujuan.",
                        'tipe'           => 'info',
                        'icon'           => 'bi-file-earmark-check-fill',
                        'link_url'       => "/order/{$id}/proposal",
                        'created_by'     => $userId,
                        'created_by_name'=> $userNama
                    ]);
                } catch (\Exception $eNotif) {}

                $this->setFlashSuccess("Dokumen proposal teknis berhasil disimpan dan <strong>diajukan ke Ketua Tim OPTI</strong> oleh <strong>{$userNama}</strong> pada " . date('d M Y H:i') . " WIB.");
            } else {
                $this->setFlashSuccess("Draf dokumen proposal teknis berhasil disimpan (Status: <strong>Draft Disimpan</strong>).");
            }
        } catch (\Exception $e) {
            $this->setFlashError("Gagal menyimpan dokumen proposal: " . $e->getMessage());
        }

        $f3->reroute("/order/{$id}/proposal");
    }

    /**
     * Upload berkas proposal / dokumen pendukung oleh PIC
     * Route: POST /order/@id/proposal/upload
     */
    public function uploadProposalFile($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/proposal');
            return;
        }

        $redirect = $f3->get('POST.redirect');
        $redirectUrl = ($redirect === 'detail') ? "/order/{$id}" : "/order/{$id}/proposal";

        $userId = (int)$this->getUserId();
        $userRole = $this->getUserRole();
        $isPic = ($userId > 0 && (int)($order['pic_proposal_id'] ?? 0) === $userId);

        // Access Control:
        if ($userRole === 'tim_kerja' && !empty($order['pic_proposal_id']) && !$isPic && !$this->isSuperadmin()) {
            $this->setFlashError("Akses Ditolak: Anda bukan PIC yang ditugaskan untuk mengunggah berkas proposal Order ini.");
            $f3->reroute($redirectUrl);
            return;
        }

        $isSelulosa = (($order['jenis_layanan_opti'] ?? '') === 'selulosa');

        // 1. Cek apakah proposal sudah disetujui (Terkunci)
        $existing = $orderModel->getProposalRiset($id);
        if ($isSelulosa) {
            $proposalDisetujui = (
                ($existing && in_array($existing['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])) ||
                in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui'])
            );
        } else {
            // Lingkungan: Proposal teknis pasca bayar terkunci jika sudah di-ACC Ketua Tim
            $proposalDisetujui = ($existing && in_array($existing['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan']));
        }
        if ($proposalDisetujui && !$this->isSuperadmin()) {
            $this->setFlashError("Dokumen proposal telah disetujui oleh Ketua Tim OPTI. Berkas terkunci dan tidak dapat diunggah ulang.");
            $f3->reroute($redirectUrl);
            return;
        }

        // 2. Cek Prasyarat Kaji Ulang (Tahap 2 - Khusus Selulosa)
        $tinjauan = $orderModel->getTinjauanKelayakan($id);
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak') ||
            !$isSelulosa
        );
        if (!$tinjauanSelesai) {
            $this->setFlashError("Gagal: Kaji Ulang Kelayakan Teknis (Tahap 2) belum selesai atau 'Tidak Dapat Dilaksanakan'.");
            $f3->reroute($redirectUrl);
            return;
        }
        
        $files = $f3->get('FILES');
        if (empty($files['file_proposal']['name'])) {
            $this->setFlashError('Pilih file dokumen proposal terlebih dahulu.');
            $f3->reroute($redirectUrl);
            return;
        }

        $file = $files['file_proposal'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'Terjadi kesalahan saat mengunggah file dokumen proposal.';
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $errorMsg = 'Ukuran file melebihi batas maksimal server yang diperbolehkan.';
            }
            $this->setFlashError($errorMsg);
            $f3->reroute($redirectUrl);
            return;
        }

        // Validasi ukuran maksimal (10 MB)
        $maxFileSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxFileSize) {
            $this->setFlashError('Ukuran file melebihi batas maksimal 10 MB. Harap perkecil atau kompres dokumen Anda.');
            $f3->reroute($redirectUrl);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($ext, $allowed)) {
            $this->setFlashError('Format file tidak didukung. Harap upload format PDF, Word (DOC/DOCX), atau Excel (XLS/XLSX).');
            $f3->reroute($redirectUrl);
            return;
        }

        $targetDir = 'public/uploads/proposals';
        if (!is_dir('c:/xampp/htdocs/Mini OPTI Tracker/' . $targetDir)) {
            @mkdir('c:/xampp/htdocs/Mini OPTI Tracker/' . $targetDir, 0777, true);
        }

        $filename = ($isSelulosa ? 'Proposal_Selulosa_' : 'Proposal_Lingkungan_') . 'Order_' . $id . '_' . time() . '.' . $ext;
        $dest = $targetDir . '/' . $filename;
        $fullDest = 'c:/xampp/htdocs/Mini OPTI Tracker/' . $dest;

        if (move_uploaded_file($file['tmp_name'], $fullDest)) {
            $initialStatus = (!$isSelulosa) ? 'menunggu_approval' : 'draft';
            if ($existing) {
                $this->db->exec(
                    "UPDATE opti_proposal_riset SET file_proposal = ?, status_proposal = IF(? = 'selulosa', status_proposal, 'menunggu_approval'), updated_at = NOW(), updated_by = ? WHERE order_id = ?",
                    array(1 => $dest, 2 => $order['jenis_layanan_opti'], 3 => $userId, 4 => $id)
                );
            } else {
                $this->db->exec(
                    "INSERT INTO opti_proposal_riset (order_id, pic_penyusun_id, spesialisasi, file_proposal, status_proposal, updated_by) VALUES (?, ?, ?, ?, ?, ?)",
                    array(1 => $id, 2 => $order['pic_proposal_id'] ?: $userId, 3 => $order['jenis_layanan_opti'], 4 => $dest, 5 => $initialStatus, 6 => $userId)
                );
            }
            $userNama = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama_user'] ?? 'PIC Peneliti');
            $this->logActivity($id, 'proposal', 'upload_file', "Berkas dokumen proposal baru ({$filename}) berhasil diunggah oleh {$userNama}.");
            $this->setFlashSuccess('Berkas dokumen proposal berhasil diunggah!');
        } else {
            $this->setFlashError('Gagal mengunggah file dokumen proposal.');
        }

        $f3->reroute($redirectUrl);
    }

    /**
     * PIC Mengajukan / Mengirimkan Proposal ke Ketua Tim
     * Route: POST /order/@id/proposal/kirim-katim
     */
    public function kirimProposalKeKatim($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $redirect = $f3->get('POST.redirect');
        $redirectUrl = ($redirect === 'detail') ? "/order/{$id}" : "/order/{$id}/proposal";

        // Cek Prasyarat Kaji Ulang (Tahap 2)
        $tinjauan = $orderModel->getTinjauanKelayakan($id);
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak')
        );
        if (!$tinjauanSelesai) {
            $this->setFlashError("Gagal: Kaji Ulang Kelayakan Teknis (Tahap 2) belum selesai atau tidak memenuhi syarat.");
            $f3->reroute($redirectUrl);
            return;
        }

        $proposal = $orderModel->getProposalRiset($id);

        // Khusus OPTI Selulosa: Wajib input rincian & upload file dokumen proposal
        if (($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
            if (empty($proposal) || empty($proposal['file_proposal']) || (float)($proposal['estimasi_total_biaya'] ?? 0) <= 0 || empty(trim($proposal['ruang_lingkup'] ?? ''))) {
                $this->setFlashError("Gagal: Untuk OPTI Selulosa, Anda harus mengisi data proposal (ruang lingkup & estimasi biaya) dan mengunggah dokumen proposal sebelum dapat mengajukan ke Ketua Tim.");
                $f3->reroute($redirectUrl);
                return;
            }
        }

        try {
            $userId = (int)$this->getUserId();
            $userNama = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama_user'] ?? 'PIC Peneliti');

            $this->db->exec(
                "UPDATE opti_proposal_riset SET status_proposal = 'diajukan', diajukan_at = NOW(), diajukan_oleh = ?, updated_at = NOW(), updated_by = ? WHERE order_id = ?",
                array(1 => $userId, 2 => $userId, 3 => $id)
            );
            $this->db->exec(
                "UPDATE order_layanan SET status_proposal_biaya = 'menunggu_approval' WHERE id = ?",
                array(1 => $id)
            );

            $this->logActivity($id, 'proposal', 'ajukan_ke_ketua', "Dokumen proposal teknis resmi diajukan ke Ketua Tim OPTI oleh {$userNama} (PIC Peneliti).");

            // Notifikasi ke Ka Tim
            try {
                \NotificationService::send($this->db, [
                    'order_id'       => $id,
                    'target_role'    => 'ketua_tim',
                    'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                    'judul'          => 'Proposal Teknis Siap Diperiksa',
                    'pesan'          => "PIC Proposal ({$userNama}) telah mengajukan dokumen proposal untuk Order #{$order['nomor_order']} ({$order['nama_perusahaan']}). Mohon periksa dan berikan persetujuan.",
                    'tipe'           => 'info',
                    'icon'           => 'bi-file-earmark-check-fill',
                    'link_url'       => "/order/{$id}/proposal",
                    'created_by'     => $userId,
                    'created_by_name'=> $userNama
                ]);
            } catch (\Exception $eNotif) {}

            $this->setFlashSuccess('Proposal teknis berhasil diajukan ke <strong>Ketua Tim OPTI</strong> untuk diperiksa dan disetujui.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal mengirim proposal: ' . $e->getMessage());
        }

        $f3->reroute($redirectUrl);
    }

    /**
     * Ketua Tim Memeriksa & Menyetujui / Meminta Revisi Proposal
     * Route: POST /order/@id/proposal/review-katim
     */
    public function reviewProposalKatim($f3, $params) {
        $this->requirePermission('order:proposal_review', '/order');
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $redirect = $f3->get('POST.redirect');
        $redirectUrl = ($redirect === 'detail') ? "/order/{$id}" : "/order/{$id}/proposal";

        $post = $f3->get('POST');
        $action = $post['action_review'] ?? 'approve';
        $catatan = trim($post['catatan_revisi'] ?? '');

        // Khusus OPTI Selulosa jika approve: Wajib ada input dan upload proposal
        if ($action === 'approve' && ($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
            $propCheck = $orderModel->getProposalRiset($id);
            if (empty($propCheck) || empty($propCheck['file_proposal']) || (float)($propCheck['estimasi_total_biaya'] ?? 0) <= 0) {
                $this->setFlashError("Gagal: Dokumen proposal OPTI Selulosa belum lengkap atau file berkas proposal belum diunggah. Proposal belum dapat disetujui (ACC).");
                $f3->reroute($redirectUrl);
                return;
            }
        }

        try {
            $userNama = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama_user'] ?? 'Ketua Tim OPTI');
            $isLingkungan = (($order['jenis_layanan_opti'] ?? '') === 'lingkungan');
            $labelDokumen = $isLingkungan ? 'Kalkulasi tarif pengujian' : 'Proposal teknis';
            $labelKapital = $isLingkungan ? 'Kalkulasi Tarif Pengujian' : 'Proposal Teknis';
            $linkPic = $isLingkungan ? "/order/{$id}/biaya-lingkungan" : "/order/{$id}/proposal";

            if ($action === 'approve') {
                $this->db->exec(
                    "UPDATE opti_proposal_riset SET 
                        status_proposal = 'disetujui_ketua', 
                        disetujui_ketua_at = NOW(), 
                        disetujui_ketua_oleh = ?, 
                        catatan_revisi = ?, 
                        updated_at = NOW(), 
                        updated_by = ? 
                     WHERE order_id = ?",
                    array(1 => $this->getUserId(), 2 => $catatan, 3 => $this->getUserId(), 4 => $id)
                );
                $this->db->exec(
                    "UPDATE order_layanan SET status_proposal_biaya = 'siap_penawaran' WHERE id = ?",
                    array(1 => $id)
                );

                // Audit Log Persetujuan
                $this->logActivity($id, 'proposal', 'setujui_proposal', "{$labelKapital} resmi disetujui (Approved) oleh {$userNama} (Ketua Tim OPTI). Siap diterbitkan Surat Penawaran.");

                // Kirim notifikasi ke Tim Mitra
                try {
                    \NotificationService::send($this->db, [
                        'order_id'       => $id,
                        'target_role'    => 'admin_order',
                        'target_layanan' => 'semua',
                        'judul'          => "{$labelKapital} Disetujui Ka. Tim",
                        'pesan'          => "{$labelKapital} untuk Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) telah disetujui oleh {$userNama}. Tim Mitra dapat menerbitkan Surat Penawaran resmi.",
                        'tipe'           => 'success',
                        'icon'           => 'bi-award-fill',
                        'link_url'       => "/order/{$id}",
                        'created_by'     => $this->getUserId(),
                        'created_by_name'=> $userNama
                    ]);
                } catch (\Exception $e) {}

                // Kirim notifikasi ke PIC
                if (!empty($order['pic_proposal_id'])) {
                    try {
                        \NotificationService::send($this->db, [
                            'order_id'       => $id,
                            'target_role'    => 'tim_kerja',
                            'target_user_id' => (int)$order['pic_proposal_id'],
                            'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                            'judul'          => "{$labelKapital} Telah Disetujui",
                            'pesan'          => "{$labelKapital} Anda untuk Order #{$order['nomor_order']} telah disetujui oleh Ka. Tim OPTI ({$userNama}).",
                            'tipe'           => 'success',
                            'icon'           => 'bi-check-circle-fill',
                            'link_url'       => $linkPic,
                            'created_by'     => $this->getUserId(),
                            'created_by_name'=> $userNama
                        ]);
                    } catch (\Exception $e) {}
                }

                $this->setFlashSuccess("{$labelKapital} telah <strong>disetujui (Approved)</strong> oleh <strong>{$userNama}</strong> pada " . date('d M Y H:i') . " WIB. Tim Mitra kini dapat menerbitkan Surat Penawaran resmi.");
            } else {
                $this->db->exec(
                    "UPDATE opti_proposal_riset SET 
                        status_proposal = 'ditolak', 
                        catatan_revisi = ?, 
                        direvisi_at = NOW(), 
                        direvisi_oleh = ?, 
                        updated_at = NOW(), 
                        updated_by = ? 
                     WHERE order_id = ?",
                    array(1 => $catatan, 2 => $this->getUserId(), 3 => $this->getUserId(), 4 => $id)
                );
                $this->db->exec(
                    "UPDATE order_layanan SET status_proposal_biaya = 'draft' WHERE id = ?",
                    array(1 => $id)
                );

                // Audit Log Permintaan Revisi
                $this->logActivity($id, 'proposal', 'minta_revisi', "Ketua Tim OPTI ({$userNama}) meminta revisi {$labelDokumen}. Catatan: \"{$catatan}\"");

                // Kirim notifikasi revisi ke PIC
                if (!empty($order['pic_proposal_id'])) {
                    try {
                        \NotificationService::send($this->db, [
                            'order_id'       => $id,
                            'target_role'    => 'tim_kerja',
                            'target_user_id' => (int)$order['pic_proposal_id'],
                            'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                            'judul'          => "Revisi {$labelKapital} Diperlukan",
                            'pesan'          => "Ka. Tim OPTI meminta revisi untuk {$labelDokumen} Order #{$order['nomor_order']}. Catatan: {$catatan}",
                            'tipe'           => 'warning',
                            'icon'           => 'bi-exclamation-diamond-fill',
                            'link_url'       => $linkPic,
                            'created_by'     => $this->getUserId(),
                            'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Ketua Tim OPTI'
                        ]);
                    } catch (\Exception $e) {}
                }

                $this->setFlashWarning('Proposal telah dikembalikan ke PIC Proposal dengan catatan revisi.');
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memproses review proposal: ' . $e->getMessage());
        }

        $f3->reroute($redirectUrl);
    }

    /**
     * Tim Mitra Mencatat Respon Pelanggan (Terima / Tolak Proposal)
     * Route: POST /order/@id/respon-klien
     */
    public function responKlien($f3, $params) {
        $this->requirePermission('order:respon_klien', '/order');
        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $keputusan = $post['keputusan_klien'] ?? 'deal';
        $catatan = trim($post['catatan_klien'] ?? '');
        $nominalBaru = isset($post['nominal_penawaran']) ? (float)$post['nominal_penawaran'] : 0.0;
        $spmBaru = trim($post['spm_layanan'] ?? '');

        try {
            $spModel = new \SuratPenawaran($this->db);
            $sp = $spModel->getByOrderId($id);

            if ($sp) {
                $spModel->updateResponKlien((int)$sp['id'], $keputusan, $catatan, $nominalBaru, $spmBaru);
            } else {
                // Jika belum ada record tb_surat_penawaran, update langsung di order_layanan
                $statusRancop = ($keputusan === 'deal') ? 'deal' : (($keputusan === 'batal') ? 'batal' : 'diskusi');
                $updateSql = "UPDATE order_layanan SET status_penawaran = ?, status_rancop = ?";
                $orderParams = [1 => $keputusan, 2 => $statusRancop];
                
                if ($nominalBaru > 0) {
                    $updateSql .= ", estimasi_biaya = ?";
                    $orderParams[] = $nominalBaru;
                }
                if (!empty($spmBaru)) {
                    $updateSql .= ", spm_layanan = ?";
                    $orderParams[] = $spmBaru;
                }
                $updateSql .= " WHERE id = ?";
                $orderParams[] = $id;
                $this->db->exec($updateSql, $orderParams);
            }

            if ($keputusan === 'deal') {
                $this->db->exec("UPDATE order_layanan SET status_keuangan = 'menunggu_pembayaran' WHERE id = ? AND (status_keuangan IS NULL OR status_keuangan = '' OR status_keuangan = 'belum_ditagih')", [1 => $id]);
                $displayNominal = $nominalBaru > 0 ? $nominalBaru : ($sp['nominal_penawaran'] ?? 0);
                $this->setFlashSuccess("Pelanggan telah <strong>menyetujui penawaran (DEAL)</strong> senilai Rp " . number_format($displayNominal, 0, ',', '.') . "! Status order beralih menjadi <strong>Menunggu Pembayaran</strong>.");
            } elseif ($keputusan === 'nego') {
                $this->setFlashWarning("Hasil negosiasi harga &amp; waktu berhasil disimpan. Penawaran telah diperbarui.");
            } else {
                $this->setFlashWarning("Penawaran telah ditandai <strong>Ditolak / Batal</strong> oleh Pelanggan.");
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal mencatat respon pelanggan: ' . $e->getMessage());
        }

        $f3->reroute("/order/{$id}");
    }

    /**
     * Ketua Tim / Admin mencatat tanggal penerimaan fisik sampel laboratorium
     * dan mengaktifkan kalkulator deadline SPM (H+1 hari kerja mengecualikan akhir pekan & libur nasional)
     * Route: POST /order/@id/terima-sampel
     */
    public function simpanTerimaSampel($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $tanggalSampel = $post['tanggal_terima_sampel'] ?? date('Y-m-d');
        $durasiHariKerja = (int)($post['durasi_hari_kerja'] ?? 30);
        if ($durasiHariKerja <= 0) {
            $durasiHariKerja = 30;
        }

        try {
            $orderModel = new OrderLayanan($this->db);
            $order = $orderModel->getDetail($id);
            if (!$order) {
                throw new \Exception("Order Layanan #{$id} tidak ditemukan.");
            }

            $kalkulasi = $orderModel->simpanPenerimaanSampel($id, $tanggalSampel, $durasiHariKerja);
            $deadlineFormatted = date('d M Y', strtotime($kalkulasi['tanggal_deadline_spm']));
            $jmlLibur = count($kalkulasi['libur_dilewati']);

            // Jejak Audit Activity Log
            $namaPetugas = $_SESSION['nama_lengkap'] ?? 'Ketua Tim Teknis';
            $this->logActivity('order', $id, 'terima_sampel', "Penerimaan fisik sampel dicatat tanggal {$tanggalSampel}. Deadline SPM dihitung resmi: {$kalkulasi['tanggal_deadline_spm']} ({$durasiHariKerja} hari kerja). Dilakukan oleh {$namaPetugas}.");

            $msg = "Penerimaan sampel berhasil dicatat! Batas pengerjaan (SPM) otomatis dihitung mulai H+1 kerja: <strong>{$deadlineFormatted}</strong> ({$durasiHariKerja} hari kerja";
            if ($jmlLibur > 0) {
                $msg .= ", melewati {$jmlLibur} hari libur nasional";
            }
            $msg .= ").";

            $this->setFlashSuccess($msg);
        } catch (\Exception $e) {
            $this->setFlashError('Gagal mencatat penerimaan sampel: ' . $e->getMessage());
        }

        $f3->reroute("/order/{$id}");
    }

    /**
     * Tim Keuangan mengunggah berkas bukti pembayaran pelanggan dan mengonfirmasi lunas
     * Route: POST /order/@id/konfirmasi-pembayaran
     */
    public function konfirmasiPembayaran($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);

        $role = $this->getUserRole();
        $canBayar = $this->isSuperadmin() || $this->isTimMitra() || $role === 'keuangan' || $this->hasPermission('pembayaran:create') || $this->hasPermission('pembayaran:edit');
        if (!$canBayar) {
            $this->setFlashError('Akses Ditolak: Pengunggahan dan verifikasi bukti pembayaran merupakan wewenang Tim Keuangan.');
            $f3->reroute("/order/{$id}");
            return;
        }

        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);
        if (!$order) {
            $this->setFlashError("Order Layanan #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $tanggalBayar = !empty($post['tanggal_bayar']) ? $post['tanggal_bayar'] : date('Y-m-d');
        $keterangan   = trim($post['keterangan'] ?? 'Pembayaran lunas via tagihan balai');
        $jumlah       = (float)($post['jumlah'] ?? ($order['estimasi_biaya'] ?: 0));

        // Upload bukti bayar
        $buktiBayarPath = null;
        if (!empty($_FILES['bukti_bayar']['name']) && $_FILES['bukti_bayar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/bukti_bayar/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowedExts)) {
                $this->setFlashError('Format berkas bukti bayar harus berupa PDF, JPG, JPEG, atau PNG.');
                $f3->reroute("/order/{$id}");
                return;
            }
            $fileName = 'bukti_' . $id . '_' . time() . '.' . $ext;
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], $targetFile)) {
                $buktiBayarPath = $targetFile;
            }
        }

        if (empty($buktiBayarPath) && empty($post['is_edit'])) {
            $this->setFlashError('Harap lampirkan berkas bukti pembayaran pelanggan.');
            $f3->reroute("/order/{$id}");
            return;
        }

        try {
            $payModel = new OptiPembayaran($this->db);
            $existing = $payModel->getByOrderId($id);

            if (!empty($existing) && !empty($buktiBayarPath)) {
                // Update pembayaran yang sudah ada jika upload ulang
                $payId = (int)$existing[0]['id'];
                $this->db->exec(
                    "UPDATE opti_pembayaran SET tanggal_bayar = ?, bukti_bayar = ?, keterangan = ?, verifikator_id = ? WHERE id = ?",
                    array(1 => $tanggalBayar, 2 => $buktiBayarPath, 3 => $keterangan, 4 => $userId, 5 => $payId)
                );
            } else {
                // Tambah data pembayaran baru
                $payModel->tambahPembayaran([
                    'order_id'             => $id,
                    'po_id'                => !empty($order['po_id']) ? (int)$order['po_id'] : null,
                    'termin_ke'            => 1,
                    'tanggal_bayar'        => $tanggalBayar,
                    'jumlah'               => $jumlah > 0 ? $jumlah : 1,
                    'metode_pembayaran'    => 'transfer_bank',
                    'nomor_transaksi_ntpn' => '',
                    'keterangan'           => $keterangan,
                    'bukti_bayar'          => $buktiBayarPath,
                    'status_verifikasi'    => 'terverifikasi',
                    'verifikator_id'       => $userId
                ]);
            }

            // Pastikan status_keuangan di order_layanan menjadi 'lunas'
            $this->db->exec(
                "UPDATE order_layanan SET status_keuangan = 'lunas' WHERE id = ?",
                array(1 => $id)
            );

            $namaPetugas = $_SESSION['nama_lengkap'] ?? 'Tim Keuangan';
            $this->logActivity('order', $id, 'konfirmasi_bayar', "Bukti pembayaran diunggah dan diverifikasi lunas oleh {$namaPetugas}. Tahap pengerjaan pengujian laboratorium dibuka untuk Tim OPTI.");

            $this->setFlashSuccess("Bukti pembayaran berhasil diunggah! Status pembayaran: <strong>Lunas</strong>. Tim OPTI dapat segera memulai pelaksanaan pengujian laboratorium.");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan konfirmasi pembayaran: ' . $e->getMessage());
        }

        $f3->reroute("/order/{$id}");
    }

    /**
     * Stream Berkas Dokumen Proposal secara Inline (Bebas Intersepsi Download / IDM)
     * Route: GET /order/@id/proposal/pdf
     */
    public function previewProposalPdf($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);
        if (!$order) {
            $f3->error(404, 'Data order tidak ditemukan.');
            return;
        }

        $proposal = $orderModel->getProposalRiset($id);
        $filePath = '';
        if (!empty($proposal['file_proposal'])) {
            $target = 'c:/xampp/htdocs/Mini OPTI Tracker/' . ltrim($proposal['file_proposal'], "/\\");
            if (file_exists($target)) {
                $filePath = $target;
            }
        }

        // Jika belum ada file fisik yang diunggah, tidak ada proposal dummy yang ditampilkan
        if (empty($filePath)) {
            $this->setFlashError('Belum ada berkas dokumen proposal yang diunggah untuk order ini.');
            $f3->reroute('/order/' . $id . '/proposal');
            return;
        }

        $filename = basename($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = ($ext === 'pdf') ? 'application/pdf' : 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($filePath));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        while (ob_get_level()) {
            ob_end_clean();
        }
        readfile($filePath);
        exit;
    }

    /**
     * Endpoint Data JSON untuk Pratinjau Proposal Bebas IDM (Zero Interception)
     * Route: GET /order/@id/proposal/raw-data
     */
    public function proposalRawData($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($id);
        if (!$order) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan']);
            exit;
        }

        $proposal = $orderModel->getProposalRiset($id);
        $filePath = '';
        $isUploaded = false;
        if (!empty($proposal['file_proposal'])) {
            $target = 'c:/xampp/htdocs/Mini OPTI Tracker/' . ltrim($proposal['file_proposal'], "/\\");
            if (file_exists($target)) {
                $filePath = $target;
                $isUploaded = true;
            }
        }

        // Jika belum ada file fisik yang diunggah, tidak ada proposal dummy yang ditampilkan
        if (empty($filePath)) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success' => false,
                'message' => 'Belum ada dokumen proposal fisik yang diunggah untuk order ini.'
            ]);
            exit;
        }

        $filename = basename($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $isPdf = ($ext === 'pdf');
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        
        $base64 = '';
        if ($isPdf && file_exists($filePath)) {
            $base64 = base64_encode(file_get_contents($filePath));
        }

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => true,
            'is_pdf' => $isPdf,
            'is_uploaded' => $isUploaded,
            'ext' => $ext,
            'filename' => $filename,
            'size' => $fileSize,
            'base64' => $base64
        ]);
        exit;
    }

    /**
     * Menampilkan format cetak surat pelayanan jasa (sijagur)
     * Route: GET /surat/@id
     */
    public function showSurat($f3, $params)
    {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $order = new OrderLayanan($this->db);
        $data = $order->getDetailSurat($id);

        if (!$data) {
            $this->setFlashError("Order #{$id} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $f3->set('order', $data);
        $f3->set('BASE', $f3->get('BASE'));

        $this->render(
            'order/surat.html',
            'Surat Permintaan Pelayanan Jasa #' . ($data['nomor_order'] ?? $id),
            'order'
        );
    }
}
