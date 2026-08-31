<?php

class SuratPenawaranController extends Controller
{


    /**
     * GET /surat-penawaran
     * CATATAN: sesuaikan nama tabel/kolom `customer` (nmcustomer) kalau beda di database kamu.
     */
    public function index($f3)
    {
        $search        = trim((string) $f3->get('GET.q'));
        $filterLayanan = (string) $f3->get('GET.jenis_layanan');
        $filterStatus  = (string) $f3->get('GET.status');

        $sql = "SELECT sp.*, COALESCE(c.nmcustomer, sp.perusahaan, '-') AS nmcustomer,
                       o.nomor_order
                FROM tb_surat_penawaran sp
                LEFT JOIN tb_customer c ON c.id_customer = sp.customer_id
                LEFT JOIN order_layanan o ON o.id = sp.order_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql      .= ' AND (sp.nomor_surat LIKE ? OR sp.perihal LIKE ? OR c.nmcustomer LIKE ? OR sp.perusahaan LIKE ? OR o.nomor_order LIKE ?)';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
        }
        if ($filterLayanan !== '') {
            $sql      .= ' AND sp.jenis_layanan = ?';
            $params[]  = $filterLayanan;
        }
        if ($filterStatus !== '') {
            if ($filterStatus === 'deal') {
                $sql .= " AND (sp.status_respon_klien = 'deal' OR sp.status = 'disetujui')";
            } elseif ($filterStatus === 'terkirim') {
                $sql .= " AND (sp.status = 'terkirim' OR sp.status_respon_klien = 'terkirim' OR sp.status_respon_klien = 'menunggu')";
            } elseif ($filterStatus === 'draft') {
                $sql .= " AND (sp.status = 'draft' OR sp.status_respon_klien = 'draft' OR sp.status IS NULL OR sp.status = '' OR sp.status = 'nonaktif')";
            }
        }
        $sql .= ' ORDER BY sp.tanggal_surat DESC, sp.id DESC';

        $daftar = $this->db->exec($sql, $params);

        $totalSurat    = count($daftar);
        $totalDeal     = count(array_filter($daftar, function ($r) { return ($r['status_respon_klien'] === 'deal' || $r['status'] === 'disetujui'); }));
        $totalTerkirim = count(array_filter($daftar, function ($r) { return ($r['status'] === 'terkirim' || $r['status_respon_klien'] === 'terkirim' || $r['status_respon_klien'] === 'menunggu'); }));
        $totalDraft    = max(0, $totalSurat - $totalDeal - $totalTerkirim);

        $f3->set('daftar_penawaran', $daftar);
        $f3->set('total_surat', $totalSurat);
        $f3->set('total_deal', $totalDeal);
        $f3->set('total_terkirim', $totalTerkirim);
        $f3->set('total_draft', $totalDraft);
        $f3->set('search', $search);
        $f3->set('filter_layanan', $filterLayanan);
        $f3->set('filter_status', $filterStatus);

        $this->render('tim_mitra/surat Pelayanan/index.html', 'Surat Pelayanan', 'surat-penawaran');
    }
    public function edit($f3, $params)
    {
        $id = (int) ($params['id'] ?? 0);

        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');

        if ($id > 0) {
            $sp->load(['id = ?', $id]);
        }

        // kalau record baru (belum ada di DB), set default sesuai bisnis rule:
        // "Pegawai BBSPJIS" otomatis tercentang karena form ini diisi oleh staf
        if ($sp->dry()) {
            $sp->permintaan_melalui = 'pegawai_bbspjis';
            $sp->status = 'draft';
        }

        // daftar pegawai untuk dropdown (dari tb_arsipuser)
        $arsipUser = new DB\SQL\Mapper($db, 'tb_arsipuser');

$daftarPegawai = $arsipUser->find(
    null,
    ['order' => 'nama_user ASC']
);

        $canEdit = ($this->hasPermission('penawaran:create') || $this->hasPermission('penawaran:edit') || $this->isSuperadmin() || $this->isAdminOrder());

        $f3->set('sp', $sp);
        $f3->set('can_edit', $canEdit);
        $f3->set('daftar_pegawai', $daftarPegawai);
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
        
        $this->render('tim_mitra/surat Pelayanan/form.html', 'Surat Penawaran', 'surat-penawaran');
    }
    public function show($f3, $params)
    {
        $id = (int) ($params['id'] ?? 0);
        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $sp->load(['id = ?', $id]);

        $f3->set('sp', $sp);
        echo \Template::instance()->render('surat_penawaran/show.htm');
    }
    public function store($f3, $params)
    {
        $id     = (int) ($params['id'] ?? 0);
        $action = $f3->get('POST.action'); // 'simpan' | 'kirim'

        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');

        if ($id > 0) {
            $sp->load(['id = ?', $id]);
        }

        $spModel = new SuratPenawaran($db);

        $sp->nama               = $f3->get('POST.nama');
        $sp->perusahaan         = $f3->get('POST.perusahaan');
        $sp->alamat             = $f3->get('POST.alamat');
        $sp->permintaan_melalui = $f3->get('POST.permintaan_melalui') ?: 'email';

        $jenisLayanan           = $f3->get('POST.jenis_layanan') ?: 'selulosa';
        $sp->jenis_layanan      = $jenisLayanan;
        $sp->bidang             = ($jenisLayanan === 'selulosa') ? 'riset' : 'pengujian';

        $tglSurat               = $f3->get('POST.tanggal_surat') ?: date('Y-m-d');
        $sp->tanggal_surat      = $tglSurat;

        $nomorSurat             = trim((string)$f3->get('POST.nomor_surat'));
        if (empty($nomorSurat)) {
            $nomorSurat = $spModel->generateNomorSurat($tglSurat);
        }
        $sp->nomor_surat        = $nomorSurat;

        $sp->perihal            = $f3->get('POST.perihal') ?: 'Penawaran Kerjasama Layanan Optimalisasi Teknologi Industri (OPTI)';
        $sp->nominal_penawaran  = (float)($f3->get('POST.nominal_penawaran') ?: 0);
        $sp->penjelasan         = $f3->get('POST.penjelasan');
        $sp->status_respon_klien= $f3->get('POST.status_respon_klien') ?: 'draft';

        $isNew = $sp->dry();

        if ($action === 'kirim') {
            $sp->status = 'terkirim';
            $sp->status_respon_klien = 'terkirim';
            $sp->updated_at = date('Y-m-d H:i:s');
            if ($isNew) {
                $sp->created_at = date('Y-m-d H:i:s');
            }
            $f3->set('SESSION.flash_success', "Surat penawaran <strong>{$nomorSurat}</strong> berhasil diterbitkan dan dikirim ke klien.");
        } else {
            $sp->status = $sp->status ?: 'draft';
            if ($isNew) {
                $sp->created_at = date('Y-m-d H:i:s');
            }
            $f3->set('SESSION.flash_success', "Draf surat penawaran <strong>{$nomorSurat}</strong> berhasil disimpan.");
        }

        $orderId = (int)($f3->get('POST.order_id') ?: ($sp->order_id ?? 0));
        if ($orderId > 0) {
            $sp->order_id = $orderId;
        }

        $sp->save();

        if ($orderId > 0) {
            $orderModel = new OrderLayanan($db);
            $order = $orderModel->getById($orderId);
            if ($order && !$order->dry()) {
                $order->jenis_layanan_opti = $jenisLayanan;
                if (!empty($sp->nama)) $order->pic = $sp->nama;
                if (!empty($sp->alamat)) $order->alamat = $sp->alamat;
                if (!empty($sp->perusahaan)) $order->nama_perusahaan = $sp->perusahaan;
                if (!empty($sp->penjelasan)) $order->deskripsi = $sp->penjelasan;
                if ($action === 'kirim') {
                    $order->status = 'baru';
                    $order->status_tinjauan = 'belum_ditinjau';
                }
                $order->save();
            }
            $f3->reroute("/order/{$orderId}");
            return;
        }

        $f3->reroute('/surat-penawaran');
    }

    public function tambah($f3)
    {
        $this->edit($f3, ['id' => 0]);
    }

    public function create($f3)
    {
        $this->tambah($f3);
    }

    public function simpan($f3, $params)
    {
        $this->store($f3, $params);
    }

    public function update($f3, $params)
    {
        $this->store($f3, $params);
    }

    public function toggleStatus($f3, $params)
    {
        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $sp->load(['id = ?', $params['id']]);

        if (!$sp->dry()) {
            $sp->status = $sp->status === 'aktif' ? 'nonaktif' : 'aktif';
            $sp->save();
            $f3->set('SESSION.flash_success', 'Status surat penawaran berhasil diubah.');
        }

        $f3->reroute('/surat-penawaran');
    }

    public function hapus($f3, $params)
    {
        $this->delete($f3, $params);
    }

    public function delete($f3, $params)
    {
        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $sp->load(['id = ?', $params['id']]);

        if (!$sp->dry()) {
            $sp->erase();
            $f3->set('SESSION.flash_success', 'Surat penawaran berhasil dihapus.');
        }

        $f3->reroute('/surat-penawaran');
    }

    /**
     * Menampilkan form pembuatan Surat Penawaran ter-prefill dari data Order
     * Route: GET /order/@id/penawaran/buat
     */
    public function tambahDariOrder($f3, $params)
    {
        $orderId = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$orderId} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $spModel = new SuratPenawaran($this->db);
        $spExisting = $spModel->getByOrderId($orderId);

        $proposal = ($order['jenis_layanan_opti'] === 'selulosa') ? $orderModel->getProposalRiset($orderId) : null;
        $kalkulasi = ($order['jenis_layanan_opti'] === 'lingkungan') ? $orderModel->getKalkulasiLingkungan($orderId) : [];

        $nomorSuratOtomatis = $spModel->generateNomorSurat();

        $canEdit = ($this->hasPermission('penawaran:create') || $this->hasPermission('penawaran:edit') || $this->isSuperadmin());

        $f3->set('order', $order);
        $f3->set('sp_existing', $spExisting);
        $f3->set('proposal', $proposal);
        $f3->set('kalkulasi', $kalkulasi);
        $f3->set('nomor_surat_otomatis', $nomorSuratOtomatis);
        $f3->set('can_edit', $canEdit);

        $this->render('tim_mitra/surat Pelayanan/form_order.html', "Terbitkan Surat Penawaran - Order #{$order['nomor_order']}", 'surat-penawaran');
    }

    /**
     * Menyimpan Surat Penawaran yang diterbitkan dari Order
     * Route: POST /order/@id/penawaran/simpan
     */
    public function simpanDariOrder($f3, $params)
    {
        $orderId = (int)($params['id'] ?? 0);

        if (!$this->hasPermission('penawaran:create') && !$this->isSuperadmin()) {
            $this->setFlashError("Akses Ditolak: Penerbitan Surat Pelayanan Resmi merupakan wewenang Tim Kemitraan.");
            $f3->reroute("/order/{$orderId}/penawaran/buat");
            return;
        }

        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        try {
            $spModel = new SuratPenawaran($this->db);
            $hasil = $spModel->buatDariOrder($orderId, $userId, $post);

            $this->setFlashSuccess(
                "Surat Penawaran Resmi berhasil diterbitkan dengan Nomor: <strong>{$hasil['nomor_surat']}</strong> (Nominal: Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")."
            );
            $f3->reroute("/order/{$orderId}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menerbitkan surat penawaran: ' . $e->getMessage());
            $f3->reroute("/order/{$orderId}/penawaran/buat");
        }
    }

    /**
     * Alias method untuk tambahDariOrder
     * Route: GET /order/@id/penawaran/buat
     */
    public function buatDariOrder($f3, $params)
    {
        return $this->tambahDariOrder($f3, $params);
    }

    /**
     * Memperbarui status respon klien langsung dari halaman detail order
     * Route: POST /order/@id/penawaran/status
     */
    public function updateStatusKlien($f3, $params)
    {
        $orderId = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');
        $statusRespon = $post['status_respon_klien'] ?? 'draft';
        $catatanNego  = trim($post['catatan_nego'] ?? '');

        $spModel = new SuratPenawaran($this->db);
        $sp = $spModel->getByOrderId($orderId);

        if (!$sp) {
            $this->setFlashError('Surat penawaran untuk order ini belum diterbitkan.');
            $f3->reroute("/order/{$orderId}");
            return;
        }

        try {
            $nominal = (float)($sp['nominal_penawaran'] ?? 0);
            $spModel->updateResponKlien((int)$sp['id'], $statusRespon, $catatanNego, $nominal);

            if ($statusRespon === 'deal') {
                $this->setFlashSuccess("Klien menyetujui penawaran (<strong>DEAL</strong>). Order sekarang siap ditagihkan dan diproses ke penerbitan PO.");
            } else {
                $this->setFlashSuccess("Status respon penawaran berhasil diperbarui menjadi: <strong>" . strtoupper($statusRespon) . "</strong>.");
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui status penawaran: ' . $e->getMessage());
        }

        $f3->reroute("/order/{$orderId}");
    }

    /**
     * Memperbarui respon / negosiasi klien terhadap Surat Penawaran
     * Route: POST /surat-penawaran/@id/respon
     */
    public function updateRespon($f3, $params)
    {
        $penawaranId = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $statusRespon = $post['status_respon_klien'] ?? 'draft';
        $catatanNego  = trim($post['catatan_nego'] ?? '');
        $nominalBaru  = (float)($post['nominal_penawaran'] ?? 0);
        $orderId      = (int)($post['order_id'] ?? 0);

        try {
            $spModel = new SuratPenawaran($this->db);
            $hasil = $spModel->updateResponKlien($penawaranId, $statusRespon, $catatanNego, $nominalBaru);

            if ($statusRespon === 'deal') {
                $this->setFlashSuccess("Klien menyetujui penawaran (<strong>DEAL</strong>). Order sekarang siap ditagihkan oleh Bagian Keuangan dan diproses ke penerbitan PO.");
            } elseif ($statusRespon === 'nego') {
                $this->setFlashWarning("Catatan negosiasi harga klien telah disimpan. Nominal penawaran disesuaikan.");
            } else {
                $this->setFlashSuccess("Status respon klien berhasil diperbarui.");
            }

            if ($orderId > 0) {
                $f3->reroute("/order/{$orderId}");
            } else {
                $f3->reroute('/surat-penawaran');
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui respon penawaran: ' . $e->getMessage());
            $f3->reroute($orderId > 0 ? "/order/{$orderId}" : '/surat-penawaran');
        }
    }

    /**
     * Cetak PDF Surat Penawaran Resmi BBSPJIS
     * Route: GET /order/@id/penawaran/cetak
     */
    public function cetakPdf($f3, $params)
    {
        $this->requireAuth();
        $orderId = (int)($params['id'] ?? 0);

        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);

        if (!$order) {
            $this->f3->error(404, 'Order tidak ditemukan');
            return;
        }

        $spModel = new SuratPenawaran($this->db);
        $sp = $spModel->getByOrderId($orderId);

        if (!$sp) {
            $this->setFlashError('Surat penawaran untuk order ini belum diterbitkan. Silakan buat penawaran terlebih dahulu.');
            $f3->reroute("/order/{$orderId}/penawaran/buat");
            return;
        }

        require_once 'c:/xampp/htdocs/Mini OPTI Tracker/app/helpers/fpdf/fpdf.php';

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(20, 15, 20);
        $pdf->AddPage();

        // 1. KOP SURAT BBSPJIS
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 4.5, 'KEMENTERIAN PERINDUSTRIAN REPUBLIK INDONESIA', 0, 1, 'C');
        $pdf->Cell(0, 4.5, 'BADAN STANDARDISASI DAN KEBIJAKAN JASA INDUSTRI', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 5.5, 'BALAI BESAR STANDARDISASI DAN PELAYANAN JASA INDUSTRI SELULOSA', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->Cell(0, 4, 'Jl. Raya Dayeuhkolot No. 132 Bandung 40258 Telp. (022) 5202871 Fax. (022) 5202872', 0, 1, 'C');
        $pdf->Cell(0, 4, 'Website: bbspjis.kemenperin.go.id | Email: bbspjis@kemenperin.go.id', 0, 1, 'C');
        $pdf->Ln(2);

        // GARIS GANDA KOP
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->SetLineWidth(0.2);
        $pdf->Line(20, $pdf->GetY() + 0.8, 190, $pdf->GetY() + 0.8);
        $pdf->Ln(5);

        // 2. METADATA SURAT
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(22, 5, 'Nomor', 0, 0);
        $pdf->Cell(4, 5, ':', 0, 0);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(75, 5, $sp['nomor_surat'], 0, 0);
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(0, 5, 'Bandung, ' . date('d F Y', strtotime($sp['tanggal_surat'])), 0, 1, 'R');

        $pdf->Cell(22, 5, 'Lampiran', 0, 0);
        $pdf->Cell(4, 5, ':', 0, 0);
        $pdf->Cell(0, 5, '1 (satu) berkas rincian biaya', 0, 1);

        $pdf->Cell(22, 5, 'Hal', 0, 0);
        $pdf->Cell(4, 5, ':', 0, 0);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->MultiCell(0, 5, $sp['perihal'], 0, 'L');
        $pdf->Ln(3);

        // 3. TUJUAN
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(0, 4.5, 'Kepada Yth.', 0, 1);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 4.5, $sp['nama'] ?: 'Pimpinan Perusahaan', 0, 1);
        $pdf->Cell(0, 4.5, $sp['perusahaan'] ?: $order['nama_perusahaan'], 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 4.5, $sp['alamat'] ?: ($order['alamat'] ?: 'Kawasan Industri'), 0, 'L');
        $pdf->Ln(3);

        // 4. PEMBUKA
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(0, 5, 'Dengan hormat,', 0, 1);
        $pdf->MultiCell(0, 5, "Menindaklanjuti permohonan kerjasama pelaksanaan Layanan Optimalisasi Teknologi Industri (OPTI) yang telah diajukan, bersama ini kami sampaikan penawaran biaya dan ruang lingkup pelaksanaan pekerjaan sebagai berikut:", 0, 'J');
        $pdf->Ln(2);

        // 5. RINCIAN BIAYA DUAL WORKFLOW
        if ($order['jenis_layanan_opti'] === 'selulosa') {
            // TABEL TAHAPAN RISET SELULOSA
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5, 'A. Rincian Skenario Tahapan Riset & Rancangan Percobaan (Rancop):', 0, 1);
            
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(12, 6, 'No', 1, 0, 'C', true);
            $pdf->Cell(65, 6, 'Tahapan Riset / Eksperimen', 1, 0, 'L', true);
            $pdf->Cell(55, 6, 'Spesifikasi & Output', 1, 0, 'L', true);
            $pdf->Cell(38, 6, 'Estimasi Biaya (Rp)', 1, 1, 'R', true);

            $tahapan = !empty($order['tahapan_riset_json']) ? json_decode($order['tahapan_riset_json'], true) : [];
            if (empty($tahapan)) {
                $tahapan = [
                    ['nama' => 'Tahap 1: Pengujian Awal & Karakterisasi Sampel', 'keterangan' => 'Analisis proksimat, kadar selulosa, lignin, dan sifat fisik', 'biaya' => $sp['nominal_penawaran'] * 0.35, 'is_active' => true],
                    ['nama' => 'Tahap 2: Optimasi Proses & Formulasi Trial', 'keterangan' => 'Trial rekayasa proses skala lab dan perlakuan kimiawi', 'biaya' => $sp['nominal_penawaran'] * 0.45, 'is_active' => true],
                    ['nama' => 'Tahap 3: Evaluasi Mutu Akhir & Rekomendasi', 'keterangan' => 'Penyusunan Laporan Hasil Uji dan Sertifikat Resmi Balai', 'biaya' => $sp['nominal_penawaran'] * 0.20, 'is_active' => true]
                ];
            }

            $pdf->SetFont('Arial', '', 8.5);
            $no = 1;
            foreach ($tahapan as $t) {
                if (isset($t['is_active']) && !$t['is_active']) continue;
                $biayaItem = (float)($t['biaya'] ?? 0);
                $pdf->Cell(12, 5.5, $no++, 1, 0, 'C');
                $pdf->Cell(65, 5.5, substr($t['nama'] ?? 'Tahapan Riset', 0, 38), 1, 0, 'L');
                $pdf->Cell(55, 5.5, substr($t['keterangan'] ?? '-', 0, 34), 1, 0, 'L');
                $pdf->Cell(38, 5.5, number_format($biayaItem, 0, ',', '.'), 1, 1, 'R');
            }
        } else {
            // TABEL PENGUJIAN LINGKUNGAN
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5, 'A. Rincian Parameter Pengujian Laboratorium & Standar Baku Mutu:', 0, 1);
            
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(10, 6, 'No', 1, 0, 'C', true);
            $pdf->Cell(65, 6, 'Parameter / Metode Pengujian', 1, 0, 'L', true);
            $pdf->Cell(35, 6, 'Standar Acuan', 1, 0, 'L', true);
            $pdf->Cell(25, 6, 'Tarif Satuan', 1, 0, 'R', true);
            $pdf->Cell(15, 6, 'Sampel', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'Subtotal', 1, 1, 'R', true);

            $kalkulasi = $orderModel->getKalkulasiLingkungan($orderId);
            $pdf->SetFont('Arial', '', 8);
            $no = 1;
            foreach ($kalkulasi as $item) {
                $pdf->Cell(10, 5.5, $no++, 1, 0, 'C');
                $pdf->Cell(65, 5.5, substr($item['nama_pengujian'], 0, 38), 1, 0, 'L');
                $pdf->Cell(35, 5.5, substr($item['standar_rujukan'] ?: 'SNI / ASTM', 0, 20), 1, 0, 'L');
                $pdf->Cell(25, 5.5, number_format((float)$item['tarif_per_sampel'], 0, ',', '.'), 1, 0, 'R');
                $pdf->Cell(15, 5.5, $item['jumlah_sampel'], 1, 0, 'C');
                $pdf->Cell(20, 5.5, number_format((float)$item['total_biaya_item'], 0, ',', '.'), 1, 1, 'R');
            }
        }

        // TOTAL BAR
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(250, 240, 242);
        $pdf->Cell(132, 6.5, 'TOTAL NILAI PENAWARAN (PNBP)', 1, 0, 'R', true);
        $pdf->Cell(38, 6.5, 'Rp ' . number_format((float)$sp['nominal_penawaran'], 0, ',', '.'), 1, 1, 'R', true);
        $pdf->Ln(3);

        // 6. KETENTUAN PEMBAYARAN
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 5, 'B. Ketentuan Pembayaran PNBP:', 0, 1);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->MultiCell(0, 4.5, "1. Tarif layanan mengacu pada Peraturan Pemerintah tentang Jenis dan Tarif atas Jenis Penerimaan Negara Bukan Pajak (PNBP) yang berlaku pada Kementerian Perindustrian.\n2. Pembayaran disetorkan langsung ke Rekening Kas Negara melalui Kode Billing SIMPONI / BSI yang akan diterbitkan oleh Bagian Keuangan BBSPJIS.\n3. Penawaran ini berlaku selama 30 (tiga puluh) hari kalender sejak tanggal diterbitkan.", 0, 'L');
        $pdf->Ln(4);

        // 7. PENUTUP & TANDA TANGAN
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->MultiCell(0, 4.5, "Demikian surat penawaran ini kami sampaikan. Apabila Bapak/Ibu menyetujui penawaran ini, mohon dapat menandatangani surat konfirmasi terlampir untuk proses penerbitan Petunjuk Operasional (PO) laboratorium. Atas perhatian dan kerjasamanya, kami ucapkan terima kasih.", 0, 'J');
        $pdf->Ln(5);

        $pdf->SetX(110);
        $pdf->Cell(80, 4.5, 'Kepala Balai Besar,', 0, 1, 'C');
        $pdf->SetX(110);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Cell(80, 4.5, 'u.b. Ketua Tim Kerja Kemitraan & Standardisasi', 0, 1, 'C');
        $pdf->Ln(3);
        $pdf->SetX(125);
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(50, 4, '[ TTD & STEMPEL RESMI BBSPJIS ]', 1, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(8);
        $pdf->SetX(110);
        $pdf->SetFont('Arial', 'BU', 9.5);
        $pdf->Cell(80, 4.5, 'Tim Kemitraan BBSPJIS', 0, 1, 'C');
        $pdf->SetX(110);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(80, 4, 'NIP. 19850715 201012 1 002', 0, 1, 'C');

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Surat_Penawaran_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $sp['nomor_surat']) . '.pdf"');
        $pdf->Output('I', 'Surat_Penawaran_' . $order['nomor_order'] . '.pdf');
        exit;
    }
}
