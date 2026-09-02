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
                                AND o.status = 'baru' 
                                AND o.id NOT IN (SELECT order_id FROM opti_tinjauan_kelayakan)
                              ORDER BY o.id DESC";
        $perluKajiUlang = $this->db->exec($sqlPerluKajiUlang, $params);

        // 2. Proposal Masuk Menunggu Persetujuan Ka. Tim
        $sqlPerluApproval = "SELECT o.*, 
                                    c.nmcustomer AS nama_perusahaan, c.pt_cv,
                                    u.nama_user AS pic_nama,
                                    pr.estimasi_total_biaya, pr.file_proposal, pr.status_proposal
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

        $filterTab    = $f3->get('GET.tab') === 'ditolak' ? 'ditolak' : 'aktif';
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
        
        // Counter untuk badge tab
        $countAktif = count($orderModel->allWithRelasi($filterJenis, '', '', $filterTahun, 'aktif'));
        $countDitolak = count($orderModel->allWithRelasi($filterJenis, '', '', $filterTahun, 'ditolak'));

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();

        $f3->set('daftar_order', $daftarOrder);
        $f3->set('filter_tab', $filterTab);
        $f3->set('count_aktif', $countAktif);
        $f3->set('count_ditolak', $countDitolak);
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
        
        $suratMasuk = null;
        if (!empty($order['id_surat_masuk']) && $this->dbSekretariat) {
            try {
                $smRows = $this->dbSekretariat->exec("SELECT * FROM surat_masuk WHERE id = ?", array(1 => (int)$order['id_surat_masuk']));
                $suratMasuk = $smRows[0] ?? null;
            } catch (\Exception $e) {
                // Ignore DB error
            }
        }

        $canEdit = ($this->hasPermission('order:tinjau') || $this->isSuperadmin());

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

        if (!$this->hasPermission('order:tinjau') && !$this->isSuperadmin()) {
            $this->setFlashError('Akses Ditolak: Kaji ulang kelayakan teknis dan penunjukan PIC merupakan wewenang Ketua Tim OPTI.');
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
                $this->setFlashSuccess("Tinjauan Kelayakan ISO berhasil disetujui! <strong>PIC Proposal</strong> telah ditugaskan untuk menyusun proposal teknis &amp; rancop.");
            } else {
                $this->setFlashWarning("Tinjauan Kelayakan ISO disimpan. Status: <strong>Tidak Dapat Dilaksanakan (Ditolak)</strong>. Informasi penolakan telah dicatat.");
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
        $daftarMetode = $this->db->exec("SELECT * FROM metode_uji WHERE status = 'aktif' ORDER BY kategori_id ASC, nama_metode ASC");
        $daftarLabEksternal = $this->db->exec("SELECT * FROM pengujian_eksternal WHERE status = 'aktif' ORDER BY nama_lembaga ASC");

        $isPic = ((int)$this->getUserId() === (int)($order['pic_proposal_id'] ?? 0));
        $canEdit = ($this->hasPermission('order:kalkulasi_biaya') || $this->isSuperadmin() || $isPic);

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
        if (!$this->hasPermission('order:kalkulasi_biaya') && !$this->isSuperadmin() && !$isPic) {
            $this->setFlashError("Akses Ditolak: Perhitungan rincian pengujian merupakan wewenang PIC Proposal.");
            $f3->reroute("/order/{$id}/biaya-lingkungan");
            return;
        }

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
            $order = $orderModel->getDetail($id);

            // Kirim notifikasi ke Tim Mitra / Ka Tim
            try {
                $actionBtn = $post['action_btn'] ?? 'save_draft';
                if ($actionBtn === 'kirim_katim') {
                    \NotificationService::send($this->db, [
                        'order_id'       => $id,
                        'target_role'    => 'ketua_tim',
                        'target_layanan' => 'lingkungan',
                        'judul'          => 'Kalkulasi Pengujian Diajukan',
                        'pesan'          => "PIC telah merampungkan kalkulasi pengujian Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) dan menunggu pemeriksaan Anda.",
                        'tipe'           => 'primary',
                        'icon'           => 'bi-calculator-fill',
                        'link_url'       => "/order/{$id}",
                        'created_by'     => $userId,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'PIC Analis'
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
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'PIC Analis'
                    ]);
                }
            } catch (\Exception $eNotif) {}

            $actionBtn = $post['action_btn'] ?? 'save_draft';
            if ($actionBtn === 'kirim_katim') {
                $this->db->exec("UPDATE order_layanan SET status_proposal_biaya = 'menunggu_approval' WHERE id = ?", array(1 => $id));
                $this->setFlashSuccess("Kalkulasi biaya berhasil disimpan &amp; <strong>diajukan ke Ketua Tim OPTI</strong> untuk diperiksa.");
            } else {
                $this->setFlashSuccess(
                    "Kalkulasi biaya pengujian lingkungan berhasil disimpan! Total Netto Penawaran: <strong>Rp " . number_format($hasil['total_netto'], 0, ',', '.') . "</strong>."
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

        try {
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
            if ($this->dbSekretariat) {
                try {
                    $smRows = $this->dbSekretariat->exec("SELECT * FROM surat_masuk WHERE id = ?", [$order['id_surat_masuk']]);
                    if (!empty($smRows)) {
                        $suratMasukData = $smRows[0];
                    }
                } catch (\Exception $e) {}
            }
            if (!$suratMasukData) {
                try {
                    $smRows = $this->db->exec("SELECT * FROM surat_masuk WHERE id = ?", [$order['id_surat_masuk']]);
                    if (!empty($smRows)) {
                        $suratMasukData = $smRows[0];
                    }
                } catch (\Exception $e) {}
            }

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

        $f3->set('order', $order);
        $f3->set('sp', $sp);
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
        $actionBtn = ($aksi === 'kirim') ? 'kirim_katim' : 'save_draft';
        $jenisLayanan = in_array($post['jenis_layanan'] ?? ($post['jenis_layanan_opti'] ?? ''), ['selulosa', 'lingkungan']) ? ($post['jenis_layanan'] ?? $post['jenis_layanan_opti']) : 'selulosa';
        $nama = trim($post['nama'] ?? '');
        $perusahaan = trim($post['perusahaan'] ?? '');
        $alamat = trim($post['alamat'] ?? '');
        $penjelasan = trim($post['penjelasan'] ?? ($post['deskripsi'] ?? ''));
        $permintaanMelalui = trim($post['permintaan_melalui'] ?? 'email');
        $pegawaiId = !empty($post['pegawai_id']) ? (int)$post['pegawai_id'] : null;

        try {
            $orderModel = new OrderLayanan($this->db);
            $order = $orderModel->getById($id);
            if (!$order || $order->dry()) {
                throw new \Exception("Order #{$id} tidak ditemukan.");
            }

            $order->jenis_layanan_opti = $jenisLayanan;
            if (!empty($nama)) $order->pic = $nama;
            if (!empty($perusahaan)) $order->nama_perusahaan = $perusahaan;
            if (!empty($alamat)) $order->alamat = $alamat;
            if (!empty($penjelasan)) $order->deskripsi = $penjelasan;

            if ($actionBtn === 'kirim_katim') {
                $order->status = 'baru'; // Maju ke antrean Kaji Ulang Ketua Tim
                $order->status_tinjauan = 'belum_ditinjau';
                $order->save();
                $this->setFlashSuccess("Surat Permintaan Pelayanan Jasa berhasil disimpan &amp; diteruskan ke <strong>Ketua Tim OPTI (" . ucfirst($jenisLayanan) . ")</strong> untuk kaji ulang kelayakan.");
            } else {
                $order->status = 'draft_disimpan';
                $order->save();
                $this->setFlashSuccess("Draf Surat Permintaan Pelayanan Jasa berhasil disimpan (Status: <strong>Draft Disimpan</strong>).");
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
                $rowsSm = $this->db->exec("SELECT * FROM surat_masuk WHERE id = ?", [1 => (int)$order['id_surat_masuk']]);
                if (!empty($rowsSm)) {
                    $suratMasuk = $rowsSm[0];
                }
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

        $userId = (int)$this->getUserId();
        $userRole = $this->getUserRole();
        $isPic = ($userId > 0 && (int)($order['pic_proposal_id'] ?? 0) === $userId);

        // Strict Access Control:
        if ($userRole === 'tim_kerja' && !$isPic && !$this->isSuperadmin()) {
            $this->setFlashError("Akses Ditolak: Anda bukan PIC yang ditugaskan untuk mengunggah berkas proposal Order ini.");
            $f3->reroute("/order/{$id}/proposal");
            return;
        }

        // 1. Cek apakah proposal sudah disetujui (Terkunci)
        $existing = $orderModel->getProposalRiset($id);
        $proposalDisetujui = (
            ($existing && in_array($existing['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])) ||
            in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui'])
        );
        if ($proposalDisetujui && !$this->isSuperadmin()) {
            $this->setFlashError("Dokumen proposal telah disetujui oleh Ketua Tim OPTI. Berkas terkunci dan tidak dapat diunggah ulang.");
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
            $this->setFlashError("Gagal: Kaji Ulang Kelayakan Teknis (Tahap 2) belum selesai atau 'Tidak Dapat Dilaksanakan'.");
            $f3->reroute("/order/{$id}/proposal");
            return;
        }
        
        $files = $f3->get('FILES');
        if (empty($files['file_proposal']['name'])) {
            $this->setFlashError('Pilih file dokumen proposal terlebih dahulu.');
            $f3->reroute("/order/{$id}/proposal");
            return;
        }

        $file = $files['file_proposal'];
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

        $targetDir = 'public/uploads/proposals';
        if (!is_dir('c:/xampp/htdocs/Mini OPTI Tracker/' . $targetDir)) {
            @mkdir('c:/xampp/htdocs/Mini OPTI Tracker/' . $targetDir, 0777, true);
        }

        $filename = 'Proposal_Order_' . $id . '_' . time() . '.' . $ext;
        $dest = $targetDir . '/' . $filename;
        $fullDest = 'c:/xampp/htdocs/Mini OPTI Tracker/' . $dest;

        if (move_uploaded_file($file['tmp_name'], $fullDest)) {
            if ($existing) {
                $this->db->exec(
                    "UPDATE opti_proposal_riset SET file_proposal = ?, updated_at = NOW(), updated_by = ? WHERE order_id = ?",
                    array(1 => $dest, 2 => $userId, 3 => $id)
                );
            } else {
                $this->db->exec(
                    "INSERT INTO opti_proposal_riset (order_id, pic_penyusun_id, spesialisasi, file_proposal, status_proposal, updated_by) VALUES (?, ?, ?, ?, 'draft', ?)",
                    array(1 => $id, 2 => $order['pic_proposal_id'] ?: $userId, 3 => $order['jenis_layanan_opti'], 4 => $dest, 5 => $userId)
                );
            }
            $userNama = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama_user'] ?? 'PIC Peneliti');
            $this->logActivity($id, 'proposal', 'upload_file', "Berkas dokumen proposal baru ({$filename}) berhasil diunggah oleh {$userNama}.");
            $this->setFlashSuccess('Berkas dokumen proposal berhasil diunggah!');
        } else {
            $this->setFlashError('Gagal mengunggah file dokumen proposal.');
        }

        $f3->reroute("/order/{$id}/proposal");
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

        try {
            $this->db->exec(
                "UPDATE opti_proposal_riset SET status_proposal = 'diajukan' WHERE order_id = ?",
                array(1 => $id)
            );
            $this->db->exec(
                "UPDATE order_layanan SET status_proposal_biaya = 'menunggu_approval' WHERE id = ?",
                array(1 => $id)
            );

            // Notifikasi ke Ka Tim
            try {
                \NotificationService::send($this->db, [
                    'order_id'       => $id,
                    'target_role'    => 'ketua_tim',
                    'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                    'judul'          => 'Proposal Teknis Siap Diperiksa',
                    'pesan'          => "PIC Proposal telah mengajukan dokumen proposal untuk Order #{$order['nomor_order']} ({$order['nama_perusahaan']}). Mohon periksa dan berikan persetujuan.",
                    'tipe'           => 'info',
                    'icon'           => 'bi-file-earmark-check-fill',
                    'link_url'       => "/order/{$id}/proposal",
                    'created_by'     => $this->getUserId(),
                    'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'PIC Peneliti'
                ]);
            } catch (\Exception $eNotif) {}

            $this->setFlashSuccess('Proposal teknis berhasil diajukan ke <strong>Ketua Tim OPTI</strong> untuk diperiksa dan disetujui.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal mengirim proposal: ' . $e->getMessage());
        }

        $f3->reroute("/order/{$id}/proposal");
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
        $post = $f3->get('POST');

        $action = $post['action_review'] ?? 'approve';
        $catatan = trim($post['catatan_revisi'] ?? '');

        try {
            $userNama = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama_user'] ?? 'Ketua Tim OPTI');

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
                $this->logActivity($id, 'proposal', 'setujui_proposal', "Proposal teknis resmi disetujui (Approved) oleh {$userNama} (Ketua Tim OPTI). Siap diterbitkan Surat Penawaran.");

                // Kirim notifikasi ke Tim Mitra
                try {
                    \NotificationService::send($this->db, [
                        'order_id'       => $id,
                        'target_role'    => 'admin_order',
                        'target_layanan' => 'semua',
                        'judul'          => 'Proposal Teknis Disetujui Ka. Tim',
                        'pesan'          => "Proposal untuk Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) telah disetujui oleh {$userNama}. Tim Mitra dapat menerbitkan Surat Penawaran resmi.",
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
                            'judul'          => 'Proposal Teknis Telah Disetujui',
                            'pesan'          => "Proposal teknis Anda untuk Order #{$order['nomor_order']} telah disetujui oleh Ka. Tim OPTI ({$userNama}).",
                            'tipe'           => 'success',
                            'icon'           => 'bi-check-circle-fill',
                            'link_url'       => "/order/{$id}/proposal",
                            'created_by'     => $this->getUserId(),
                            'created_by_name'=> $userNama
                        ]);
                    } catch (\Exception $e) {}
                }

                $this->setFlashSuccess("Proposal teknis telah <strong>disetujui (Approved)</strong> oleh <strong>{$userNama}</strong> pada " . date('d M Y H:i') . " WIB. Tim Mitra kini dapat menerbitkan Surat Penawaran resmi.");
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
                $this->logActivity($id, 'proposal', 'minta_revisi', "Ketua Tim OPTI ({$userNama}) meminta revisi proposal. Catatan: \"{$catatan}\"");

                // Kirim notifikasi revisi ke PIC
                if (!empty($order['pic_proposal_id'])) {
                    try {
                        \NotificationService::send($this->db, [
                            'order_id'       => $id,
                            'target_role'    => 'tim_kerja',
                            'target_user_id' => (int)$order['pic_proposal_id'],
                            'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                            'judul'          => 'Revisi Dokumen Proposal Diperlukan',
                            'pesan'          => "Ka. Tim OPTI meminta revisi untuk proposal Order #{$order['nomor_order']}. Catatan: {$catatan}",
                            'tipe'           => 'warning',
                            'icon'           => 'bi-exclamation-diamond-fill',
                            'link_url'       => "/order/{$id}/proposal",
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

        $f3->reroute("/order/{$id}/proposal");
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

        try {
            if ($keputusan === 'deal') {
                $this->db->exec(
                    "UPDATE order_layanan SET status_penawaran = 'deal', status_rancop = 'deal' WHERE id = ?",
                    array(1 => $id)
                );
                $this->db->exec(
                    "UPDATE tb_surat_penawaran SET status_respon_klien = 'deal', disetujui_klien_at = NOW(), catatan_nego = ? WHERE order_id = ?",
                    array(1 => $catatan, 2 => $id)
                );
                $this->setFlashSuccess("Pelanggan telah <strong>menyetujui proposal (Deal)</strong>! Silakan lanjutkan ke penerbitan Petunjuk Operasional (PO) dan Kontrak PKS.");
            } else {
                $this->db->exec(
                    "UPDATE order_layanan SET status_penawaran = 'batal', status_rancop = 'batal' WHERE id = ?",
                    array(1 => $id)
                );
                $this->db->exec(
                    "UPDATE tb_surat_penawaran SET status_respon_klien = 'batal', catatan_nego = ? WHERE order_id = ?",
                    array(1 => $catatan, 2 => $id)
                );
                $this->setFlashWarning("Proposal telah ditandai <strong>Ditolak / Batal</strong> oleh Pelanggan.");
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal mencatat respon pelanggan: ' . $e->getMessage());
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

        // Jika belum ada file fisik PDF yang diunggah, buat PDF proposal resmi secara dinamis
        if (empty($filePath)) {
            require_once 'c:/xampp/htdocs/Mini OPTI Tracker/app/helpers/fpdf/fpdf.php';
            $pdf = new \FPDF('P', 'mm', 'A4');
            $pdf->SetMargins(20, 15, 20);
            $pdf->AddPage();

            // 1. KOP RESMI BBSPJIS
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 5.5, 'KEMENTERIAN PERINDUSTRIAN REPUBLIK INDONESIA', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 4.8, 'BALAI BESAR STANDARDISASI DAN PELAYANAN JASA INDUSTRI SELULOSA', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(70, 70, 70);
            $pdf->Cell(0, 4, 'Jl. Raya Dayeuhkolot No. 132, Bandung 40258 | Telp. (022) 5202871 | www.bbspjis.kemenperin.go.id', 0, 1, 'C');
            $pdf->Ln(2);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.8);
            $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
            $pdf->SetLineWidth(0.2);
            $pdf->Line(20, $pdf->GetY() + 0.8, 190, $pdf->GetY() + 0.8);
            $pdf->Ln(5);

            // 2. JUDUL DOKUMEN PROPOSAL
            $pdf->SetFont('Arial', 'B', 11.5);
            $pdf->SetTextColor(136, 19, 55); // Maroon BBSPJIS
            $pdf->Cell(0, 6, 'PROPOSAL TEKNIS & RANCANGAN ANGGARAN BIAYA (RAB)', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetTextColor(70, 70, 70);
            $pdf->Cell(0, 4.5, 'LAYANAN OPTIMALISASI TEKNOLOGI INDUSTRI (OPTI) - ' . strtoupper($order['jenis_layanan_opti']), 0, 1, 'C');
            $pdf->Ln(4);

            // 3. METADATA ORDER
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(35, 5, 'Nomor Order', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(70, 5, '#' . $order['nomor_order'], 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, 'Tanggal: ' . date('d F Y'), 0, 1, 'R');

            $pdf->Cell(35, 5, 'Pelanggan / Industri', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, $order['nama_perusahaan'] . ' (' . ($order['pt_cv'] ?: 'Industri') . ')', 0, 1);

            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(35, 5, 'PIC Peneliti Penyusun', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, $order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?? 'Tim Pelaksana OPTI BBSPJIS'), 0, 1);

            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(35, 5, 'Judul Proposal', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->MultiCell(0, 5, $proposal['judul_proposal'] ?: ($order['judul_kegiatan'] ?: 'Layanan Optimalisasi Teknologi Industri'), 0, 'L');
            $pdf->Ln(3);

            // 4. RUANG LINGKUP & METODOLOGI
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5.5, '1. Ruang Lingkup & Metodologi Pengujian / Riset:', 0, 1);
            $pdf->SetFont('Arial', '', 9);
            $lingkup = $proposal['ruang_lingkup'] ?: 'Pengujian parameter mutu, pengamatan karakteristik bahan baku, sampling lapangan, dan formulasi rekomendasi teknologi sesuai standar SNI/ISO/TAPPI terakreditasi ISO/IEC 17025 BBSPJIS.';
            $pdf->MultiCell(0, 4.8, $lingkup, 0, 'J');
            $pdf->Ln(3);

            // 5. DURASI & ESTIMASI BIAYA
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5.5, '2. Rencana Pelaksanaan & Estimasi Anggaran (RAB):', 0, 1);
            
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(8, 5, '', 0, 0);
            $pdf->Cell(45, 5, 'a. Estimasi Durasi', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, $proposal['durasi_kegiatan'] ?: '30 Hari Kerja', 0, 1);

            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(8, 5, '', 0, 0);
            $pdf->Cell(45, 5, 'b. Estimasi Total Biaya', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->SetTextColor(136, 19, 55);
            $pdf->Cell(0, 5, 'Rp ' . number_format((float)($proposal['estimasi_total_biaya'] ?: ($order['estimasi_biaya'] ?: 0)), 0, ',', '.'), 0, 1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(5);

            // 6. STATUS PERSETUJUAN
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5.5, '3. Status Verifikasi Teknis:', 0, 1);
            $pdf->SetFont('Arial', '', 9);
            $statusText = 'Draf Proposal Teknis (Menunggu Persetujuan Ketua Tim OPTI)';
            if (($proposal['status_proposal'] ?? '') === 'disetujui_ketua') {
                $statusText = 'Disetujui Ketua Tim OPTI BBSPJIS (Siap Penerbitan Surat Penawaran Biaya)';
            } elseif (($proposal['status_proposal'] ?? '') === 'ditolak') {
                $statusText = 'Perlu Revisi: ' . ($proposal['catatan_revisi'] ?: '-');
            }
            $pdf->MultiCell(0, 4.8, $statusText, 0, 'L');
            $pdf->Ln(8);

            // 7. TANDA TANGAN
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(95, 4.5, 'Penyusun Proposal (PIC Peneliti)', 0, 0, 'C');
            $pdf->Cell(95, 4.5, 'Mengetahui & Menyetujui (Ka. Tim OPTI)', 0, 1, 'C');
            $pdf->Ln(18);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(95, 4.5, $order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?? 'PIC Peneliti BBSPJIS'), 0, 0, 'C');
            $pdf->Cell(95, 4.5, 'Ketua Tim OPTI ' . ucfirst($order['jenis_layanan_opti']), 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(95, 4, 'BBSPJIS Kemenperin RI', 0, 0, 'C');
            $pdf->Cell(95, 4, 'BBSPJIS Kemenperin RI', 0, 1, 'C');

            $uploadDir = 'c:/xampp/htdocs/Mini OPTI Tracker/public/uploads/proposals';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $tempGenPath = $uploadDir . '/Generated_Proposal_Order_' . $id . '.pdf';
            $pdf->Output('F', $tempGenPath);
            $filePath = $tempGenPath;
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

        // Jika belum ada file fisik PDF yang diunggah, buat PDF proposal resmi secara dinamis
        if (empty($filePath)) {
            require_once 'c:/xampp/htdocs/Mini OPTI Tracker/app/helpers/fpdf/fpdf.php';
            $pdf = new \FPDF('P', 'mm', 'A4');
            $pdf->SetMargins(20, 15, 20);
            $pdf->AddPage();

            // KOP RESMI BBSPJIS
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 5.5, 'KEMENTERIAN PERINDUSTRIAN REPUBLIK INDONESIA', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 4.8, 'BALAI BESAR STANDARDISASI DAN PELAYANAN JASA INDUSTRI SELULOSA', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(70, 70, 70);
            $pdf->Cell(0, 4, 'Jl. Raya Dayeuhkolot No. 132, Bandung 40258 | Telp. (022) 5202871 | www.bbspjis.kemenperin.go.id', 0, 1, 'C');
            $pdf->Ln(2);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.8);
            $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
            $pdf->SetLineWidth(0.2);
            $pdf->Line(20, $pdf->GetY() + 0.8, 190, $pdf->GetY() + 0.8);
            $pdf->Ln(5);

            // JUDUL DOKUMEN PROPOSAL
            $pdf->SetFont('Arial', 'B', 11.5);
            $pdf->SetTextColor(136, 19, 55);
            $pdf->Cell(0, 6, 'PROPOSAL TEKNIS & RANCANGAN ANGGARAN BIAYA (RAB)', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetTextColor(70, 70, 70);
            $pdf->Cell(0, 4.5, 'LAYANAN OPTIMALISASI TEKNOLOGI INDUSTRI (OPTI) - ' . strtoupper($order['jenis_layanan_opti']), 0, 1, 'C');
            $pdf->Ln(4);

            // METADATA
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(35, 5, 'Nomor Order', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(70, 5, '#' . $order['nomor_order'], 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, 'Tanggal: ' . date('d F Y'), 0, 1, 'R');

            $pdf->Cell(35, 5, 'Pelanggan / Industri', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, $order['nama_perusahaan'] . ' (' . ($order['pt_cv'] ?: 'Industri') . ')', 0, 1);

            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(35, 5, 'PIC Peneliti Penyusun', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, $order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?? 'Tim Pelaksana OPTI BBSPJIS'), 0, 1);

            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(35, 5, 'Judul Proposal', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->MultiCell(0, 5, $proposal['judul_proposal'] ?: ($order['judul_kegiatan'] ?: 'Layanan Optimalisasi Teknologi Industri'), 0, 'L');
            $pdf->Ln(3);

            // RUANG LINGKUP
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5.5, '1. Ruang Lingkup & Metodologi Pengujian / Riset:', 0, 1);
            $pdf->SetFont('Arial', '', 9);
            $lingkup = $proposal['ruang_lingkup'] ?: 'Pengujian parameter mutu, pengamatan karakteristik bahan baku, sampling lapangan, dan formulasi rekomendasi teknologi sesuai standar SNI/ISO/TAPPI terakreditasi ISO/IEC 17025 BBSPJIS.';
            $pdf->MultiCell(0, 4.8, $lingkup, 0, 'J');
            $pdf->Ln(3);

            // DURASI & BIAYA
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5.5, '2. Rencana Pelaksanaan & Estimasi Anggaran (RAB):', 0, 1);
            
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(8, 5, '', 0, 0);
            $pdf->Cell(45, 5, 'a. Estimasi Durasi', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, $proposal['durasi_kegiatan'] ?: '30 Hari Kerja', 0, 1);

            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(8, 5, '', 0, 0);
            $pdf->Cell(45, 5, 'b. Estimasi Total Biaya', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->SetTextColor(136, 19, 55);
            $pdf->Cell(0, 5, 'Rp ' . number_format((float)($proposal['estimasi_total_biaya'] ?: ($order['estimasi_biaya'] ?: 0)), 0, ',', '.'), 0, 1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(5);

            // STATUS
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5.5, '3. Status Verifikasi Teknis:', 0, 1);
            $pdf->SetFont('Arial', '', 9);
            $statusText = 'Draf Proposal Teknis (Menunggu Persetujuan Ketua Tim OPTI)';
            if (($proposal['status_proposal'] ?? '') === 'disetujui_ketua') {
                $statusText = 'Disetujui Ketua Tim OPTI BBSPJIS (Siap Penerbitan Surat Penawaran Biaya)';
            } elseif (($proposal['status_proposal'] ?? '') === 'ditolak') {
                $statusText = 'Perlu Revisi: ' . ($proposal['catatan_revisi'] ?: '-');
            }
            $pdf->MultiCell(0, 4.8, $statusText, 0, 'L');
            $pdf->Ln(8);

            // TANDA TANGAN
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(95, 4.5, 'Penyusun Proposal (PIC Peneliti)', 0, 0, 'C');
            $pdf->Cell(95, 4.5, 'Mengetahui & Menyetujui (Ka. Tim OPTI)', 0, 1, 'C');
            $pdf->Ln(18);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(95, 4.5, $order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?? 'PIC Peneliti BBSPJIS'), 0, 0, 'C');
            $pdf->Cell(95, 4.5, 'Ketua Tim OPTI ' . ucfirst($order['jenis_layanan_opti']), 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(95, 4, 'BBSPJIS Kemenperin RI', 0, 0, 'C');
            $pdf->Cell(95, 4, 'BBSPJIS Kemenperin RI', 0, 1, 'C');

            $uploadDir = 'c:/xampp/htdocs/Mini OPTI Tracker/public/uploads/proposals';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $tempGenPath = $uploadDir . '/Generated_Proposal_Order_' . $id . '.pdf';
            $pdf->Output('F', $tempGenPath);
            $filePath = $tempGenPath;
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
}
