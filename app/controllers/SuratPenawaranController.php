<?php

require_once __DIR__ . '/../helpers/fpdf/fpdf.php';

if (!class_exists('SiloptiSuratPenawaranPdf')) {
    class SiloptiSuratPenawaranPdf extends \FPDF {
        public $jenisLayanan = 'selulosa';

        public function Header() {
            if ($this->PageNo() == 1) {
                $logo = __DIR__ . '/../helpers/fpdf/kemenperin_logo.png';
                if (file_exists($logo)) {
                    // Logo Kemenperin: proporsi presisi sesuai surat penawaran resmi (lebar 44 mm, tinggi ~13.45 mm)
                    $this->Image($logo, 20, 10, 44);
                }

                $this->SetY(10);
                $this->SetX(65);
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor(0, 0, 0);
                $this->Cell(125, 4.3, 'BADAN STANDARDISASI DAN KEBIJAKAN JASA INDUSTRI', 0, 1, 'C');
                
                $this->SetX(65);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(125, 4.6, 'BALAI BESAR STANDARDISASI DAN PELAYANAN', 0, 1, 'C');
                
                $this->SetX(65);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(125, 4.6, 'JASA INDUSTRI SELULOSA', 0, 1, 'C');
                
                $this->SetX(65);
                $this->SetFont('Arial', '', 7.5);
                $this->Cell(125, 3.6, 'Jl. Raya Dayeuhkolot No. 132 Bandung 40258', 0, 1, 'C');
                
                $this->SetX(65);
                $this->SetFont('Arial', '', 7.0);
                $emailBbs = ($this->jenisLayanan === 'lingkungan') ? 'bbpk@bbpk.go.id' : 'bbs@kemenperin.go.id';
                $this->Cell(125, 3.4, 'Telp. (022) 5202980 (Hunting) & 5202871 Fax. (022) 5202871 E-mail : ' . $emailBbs, 0, 1, 'C');
                
                $this->Ln(2.2);

                // Garis Pembatas Ganda Standar Kop Surat Resmi BBSPJIS (Tebal atas, tipis bawah)
                $yLine = $this->GetY();
                $this->SetDrawColor(0, 0, 0);
                
                // Garis tebal atas
                $this->SetLineWidth(0.8);
                $this->Line(20, $yLine, 190, $yLine);
                
                // Garis tipis bawah
                $this->SetLineWidth(0.25);
                $this->Line(20, $yLine + 0.9, 190, $yLine + 0.9);
                
                $this->SetLineWidth(0.2); // reset default
                $this->SetY($yLine + 4.5);
            } else {
                // Halaman 2 ke atas (Standar Dokumen Berlampiran)
                $this->SetY(10);
                $this->SetFont('Arial', '', 9);
                $this->SetTextColor(0, 0, 0);
                $this->Cell(0, 5, $this->PageNo(), 0, 1, 'C');
                $this->Ln(2);
            }
        }

        public function Footer() {
            if ($this->jenisLayanan === 'selulosa') {
                $this->SetY(-14);
                $this->SetFont('Arial', '', 7.5);
                $this->SetTextColor(37, 99, 235); // Biru Zona Integritas BBSPJIS
                $this->Cell(0, 5, 'Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa adalah Wilayah Zona Integritas - Tolak Gratifikasi', 0, 0, 'C');
            }
        }
    }
}

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
        $tampilkanSemua = (int)($f3->get('GET.all') ?? 0);

        $sql = "SELECT sp.*, COALESCE(c.nmcustomer, sp.perusahaan, '-') AS nmcustomer,
                       o.nomor_order,
                       sk.id_surat_keluar,
                       sk.nama_berkas_surat_keluar
                FROM tb_surat_penawaran sp
                LEFT JOIN tb_customer c ON c.id_customer = sp.customer_id
                LEFT JOIN order_layanan o ON o.id = sp.order_id
                LEFT JOIN tb_surat_keluar sk ON (sk.no_surat = sp.nomor_surat AND sk.surat_penawaran = 'Y' AND sk.dipilih_sis_opti = 'Y')
                WHERE 1=1";
        $params = [];

        // Secara default, hanya tampilkan Surat Penawaran aktif/terbaru per order untuk mencegah duplikasi di tabel
        if (!$tampilkanSemua) {
            $sql .= " AND (sp.id IN (
                SELECT COALESCE(o2.surat_penawaran_id, MAX(sp2.id))
                FROM tb_surat_penawaran sp2
                LEFT JOIN order_layanan o2 ON o2.id = sp2.order_id
                GROUP BY COALESCE(sp2.order_id, sp2.id)
            ) OR sp.order_id IS NULL)";
        }

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
            } elseif ($filterStatus === 'nego' || $filterStatus === 'terkirim') {
                $sql .= " AND (sp.status = 'terkirim' OR sp.status_respon_klien = 'terkirim' OR sp.status_respon_klien = 'nego' OR sp.status_respon_klien = 'menunggu')";
            } elseif ($filterStatus === 'draft') {
                $sql .= " AND (sp.status = 'draft' OR sp.status_respon_klien = 'draft' OR sp.status IS NULL OR sp.status = '' OR sp.status = 'nonaktif')";
            } elseif ($filterStatus === 'batal' || $filterStatus === 'tolak') {
                $sql .= " AND (sp.status_respon_klien IN ('batal', 'tolak'))";
            }
        }
        $sql .= ' ORDER BY sp.tanggal_surat DESC, sp.id DESC';

        $daftar = $this->db->exec($sql, $params);

        // Normalisasi nama perusahaan agar tidak terjadi duplikasi "PT PT" dan teks bertumpuk
        foreach ($daftar as &$item) {
            $rawCust = trim($item['nmcustomer'] ?? '');
            $rawPer = trim($item['perusahaan'] ?? '');

            $cleanCust = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*){2,}/i', '$1 ', $rawCust);
            $cleanPer = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*){2,}/i', '$1 ', $rawPer);

            $item['nmcustomer'] = $cleanCust;
            $item['perusahaan'] = $cleanPer;

            $coreCust = strtolower(trim(preg_replace('/^(PT|CV|UD|PD|Yayasan)\.?\s*/i', '', $cleanCust)));
            $corePer = strtolower(trim(preg_replace('/^(PT|CV|UD|PD|Yayasan)\.?\s*/i', '', $cleanPer)));

            if ($coreCust === $corePer || empty($cleanPer) || $cleanPer === '-') {
                $item['display_perusahaan'] = !empty($cleanCust) && $cleanCust !== '-' ? $cleanCust : $cleanPer;
                $item['sub_info'] = !empty($item['nama']) ? 'PIC: ' . $item['nama'] : '';
            } else {
                $item['display_perusahaan'] = $cleanCust;
                $item['sub_info'] = $cleanPer . (!empty($item['nama']) ? ' (PIC: ' . $item['nama'] . ')' : '');
            }
        }
        unset($item);

        $totalSurat    = count($daftar);
        $totalDeal     = count(array_filter($daftar, function ($r) { return ($r['status_respon_klien'] === 'deal' || $r['status'] === 'disetujui'); }));
        $totalNego     = count(array_filter($daftar, function ($r) { return ($r['status_respon_klien'] === 'nego'); }));
        $totalTerkirim = count(array_filter($daftar, function ($r) { return ($r['status'] === 'terkirim' || $r['status_respon_klien'] === 'terkirim' || $r['status_respon_klien'] === 'menunggu'); }));
        $totalDraft    = max(0, $totalSurat - $totalDeal - $totalNego - $totalTerkirim);

        // Ambil daftar order yang siap diterbitkan penawarannya (untuk modal pemilihan order)
        $sqlSiap = "SELECT o.id, o.nomor_order, COALESCE(c.nmcustomer, '-') AS nama_perusahaan, o.jenis_layanan_opti, o.estimasi_biaya, 
                           o.status_proposal_biaya, o.status_penawaran, o.created_at, c.nmcustomer, c.pt_cv
                    FROM order_layanan o
                    LEFT JOIN tb_customer c ON c.id_customer = o.id_customer
                    WHERE (o.status_tinjauan = 'layak' OR o.status NOT IN ('permintaan_masuk', 'draft_disimpan'))
                      AND (
                          (o.jenis_layanan_opti = 'selulosa' AND o.status_proposal_biaya = 'siap_penawaran' AND o.id IN (SELECT order_id FROM opti_proposal_riset WHERE file_proposal IS NOT NULL AND file_proposal != '' AND estimasi_total_biaya > 0 AND status_proposal IN ('disetujui', 'disetujui_ketua', 'disetujui_pimpinan')))
                          OR
                          (o.jenis_layanan_opti = 'lingkungan' AND o.status_proposal_biaya = 'siap_penawaran')
                          OR
                          (o.jenis_layanan_opti NOT IN ('selulosa', 'lingkungan') AND (o.status_proposal_biaya = 'siap_penawaran' OR o.status_penawaran IN ('belum_ada', 'draft', '')))
                      )
                      AND (o.status_penawaran != 'deal' OR o.status_penawaran IS NULL)
                    ORDER BY o.id DESC LIMIT 20";
        $daftarOrderSiap = $this->db->exec($sqlSiap);
        foreach ($daftarOrderSiap as &$ord) {
            $nm = trim($ord['nmcustomer'] ?? '');
            $ptCv = trim($ord['pt_cv'] ?? '');
            if (!empty($ptCv) && stripos($nm, $ptCv) !== 0) {
                $comp = $ptCv . ' ' . $nm;
            } else {
                $comp = $nm ?: '-';
            }
            $ord['nama_perusahaan'] = preg_replace('/^(PT|CV|UD|PD)\.?\s+(PT|CV|UD|PD)\.?\s+/i', '$1 ', $comp);
        }
        unset($ord);

        $f3->set('daftar_penawaran', $daftar);
        $f3->set('daftar_order_siap', $daftarOrderSiap);
        $f3->set('total_surat', $totalSurat);
        $f3->set('total_deal', $totalDeal);
        $f3->set('total_nego', $totalNego);
        $f3->set('total_terkirim', $totalTerkirim);
        $f3->set('total_draft', $totalDraft);
        $f3->set('search', $search);
        $f3->set('filter_layanan', $filterLayanan);
        $f3->set('filter_status', $filterStatus);
        $f3->set('tampilkan_semua', $tampilkanSemua);

        $this->render('tim_mitra/surat Pelayanan/index.html', 'Surat Penawaran', 'surat-penawaran');
    }
    public function edit($f3, $params)
    {
        $id = (int) ($params['id'] ?? 0);

        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');

        if ($id > 0) {
            $sp->load(['id = ?', $id]);
            // Jika surat penawaran terikat ke order, alihkan langsung ke form penawaran order modern
            if (!$sp->dry() && !empty($sp->order_id)) {
                $f3->reroute("/order/{$sp->order_id}/penawaran/buat");
                return;
            }
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
        $nominalRaw             = preg_replace('/[^0-9]/', '', (string)$f3->get('POST.nominal_penawaran'));
        $sp->nominal_penawaran  = (float)($nominalRaw ?: 0);
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

        // Sinkronisasi ke tb_surat_keluar
        $idSuratMasuk = 0;
        if ($orderId > 0) {
            $ordRow = $db->exec("SELECT id_surat_masuk, id_customer FROM order_layanan WHERE id = ?", [1 => $orderId]);
            if (!empty($ordRow)) {
                $idSuratMasuk = (int)($ordRow[0]['id_surat_masuk'] ?? 0);
                if (empty($sp->customer_id) && !empty($ordRow[0]['id_customer'])) {
                    $sp->customer_id = $ordRow[0]['id_customer'];
                }
            }
        }
        $spModel->syncKeSuratKeluar([
            'nomor_surat'        => $sp->nomor_surat,
            'tanggal_surat'      => $sp->tanggal_surat,
            'perihal'            => $sp->perihal,
            'customer_id'        => $sp->customer_id ?? 0,
            'id_arsip'           => $idSuratMasuk,
            'file_lampiran'      => $sp->file_lampiran ?? '',
            'nominal_penawaran'  => $sp->nominal_penawaran ?? 0
        ], $this->dbSekretariat);

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
        $orderId = (int)($f3->get('GET.order_id') ?? 0);
        if ($orderId > 0) {
            $f3->reroute("/order/{$orderId}/penawaran/buat");
            return;
        }
        $f3->reroute('/surat-penawaran?buka_modal=1');
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
        $this->requirePermission('order:edit', '/surat-penawaran');

        $spId = (int)($params['id'] ?? 0);
        $db = $this->db ?? $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $sp->load(['id = ?', $spId]);

        $orderId = 0;
        if (!$sp->dry()) {
            $orderId = (int)($sp['order_id'] ?? 0);
            $nomorSurat = $sp['nomor_surat'] ?? "ID #{$spId}";

            // Hapus surat penawaran
            $sp->erase();

            // Hapus dari tb_surat_keluar
            $idArsip = 0;
            if ($orderId > 0) {
                $orderRow = $db->exec("SELECT id_surat_masuk FROM order_layanan WHERE id = ?", [1 => $orderId]);
                $idArsip = !empty($orderRow) ? (int)($orderRow[0]['id_surat_masuk'] ?? 0) : 0;
            }
            $spModel = new SuratPenawaran($db);
            $spModel->hapusDariSuratKeluar($nomorSurat, $idArsip, $this->dbSekretariat);

            // Kembalikan status order jika terhubung
            if ($orderId > 0) {
                $orderRow = $db->exec("SELECT surat_penawaran_id FROM order_layanan WHERE id = ?", [1 => $orderId]);
                $currentSpId = !empty($orderRow) ? (int)($orderRow[0]['surat_penawaran_id'] ?? 0) : 0;
                if ($currentSpId === $spId) {
                    $prevSp = $db->exec("SELECT id, status_respon_klien, nominal_penawaran FROM tb_surat_penawaran WHERE order_id = ? AND id != ? ORDER BY id DESC LIMIT 1", [1 => $orderId, 2 => $spId]);
                    if (!empty($prevSp)) {
                        $db->exec("UPDATE order_layanan SET surat_penawaran_id = ?, status_penawaran = ?, estimasi_biaya = ? WHERE id = ?", [
                            1 => $prevSp[0]['id'],
                            2 => $prevSp[0]['status_respon_klien'],
                            3 => $prevSp[0]['nominal_penawaran'],
                            4 => $orderId
                        ]);
                    } else {
                        $db->exec(
                            "UPDATE order_layanan SET status_penawaran = 'belum_ada', surat_penawaran_id = NULL WHERE id = ?",
                            [$orderId]
                        );
                    }
                }
            }

            $this->logActivity($orderId, 'surat_penawaran', 'hapus', "Menghapus Surat Penawaran {$nomorSurat}");
            $this->setFlashSuccess("Surat Penawaran {$nomorSurat} berhasil dihapus.");
        } else {
            $this->setFlashError("Surat Penawaran tidak ditemukan.");
        }

        $redirect = $f3->get('GET.redirect') ?? $f3->get('POST.redirect') ?? '';
        if ($redirect === 'order' && $orderId > 0) {
            $f3->reroute("/order/{$orderId}");
            return;
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

        // 1. Validasi Prasyarat Kaji Ulang Kelayakan Teknis (Tahap 2)
        $tinjauan = $orderModel->getTinjauanKelayakan($orderId);
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak')
        );
        if (!$tinjauanSelesai) {
            $this->setFlashError("Gagal: Kaji Ulang Kelayakan Teknis (Tahap 2) belum selesai atau tidak memenuhi syarat.");
            $f3->reroute("/order/{$orderId}");
            return;
        }

        // 2. Validasi Prasyarat Dokumen Proposal (Khusus OPTI Selulosa)
        if (($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
            $proposalCheck = $orderModel->getProposalRiset($orderId);
            $proposalValid = !empty($proposalCheck) && 
                !empty($proposalCheck['file_proposal']) && 
                (float)($proposalCheck['estimasi_total_biaya'] ?? 0) > 0;
            $proposalApproved = $proposalValid && (
                in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) ||
                in_array($proposalCheck['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])
            );

            if (!$proposalValid || !$proposalApproved) {
                $this->setFlashError("Gagal: Untuk OPTI Selulosa, dokumen proposal teknis harus diisi, berkas dokumen proposal wajib diunggah, dan telah disetujui (ACC) oleh Ketua Tim OPTI sebelum dapat menerbitkan Surat Penawaran.");
                $f3->reroute("/order/{$orderId}");
                return;
            }
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
                    $rawPengirim = trim($suratMasukData['pengirim']);
                    $ptCv = trim($suratMasukData['pt_cv'] ?? ($order['pt_cv'] ?? ''));
                    $pengirimTanpaPT = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*)+/i', '', $rawPengirim);
                    $prefix = !empty($ptCv) ? strtoupper($ptCv) . ' ' : (preg_match('/^(PT|CV|UD|PD|Yayasan)\.?\s*/i', $rawPengirim, $mb) ? strtoupper($mb[1]) . ' ' : '');
                    $order['nama_perusahaan'] = trim($prefix . $pengirimTanpaPT);
                    $order['nmcustomer'] = $order['nama_perusahaan'];
                    $order['pt_cv'] = trim($ptCv ?: (preg_match('/^(PT|CV|UD|PD|Yayasan)/i', $prefix, $mb2) ? $mb2[1] : ''));
                }
            }
        }

        $spModel = new SuratPenawaran($this->db);
        $allSp = $spModel->getAllByOrderId($orderId);
        $spExisting = $spModel->getByOrderId($orderId);

        // Bersihkan nama perusahaan dari duplikasi "PT PT"
        if (!empty($spExisting['perusahaan'])) {
            $spExisting['perusahaan'] = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*){2,}/i', '$1 ', $spExisting['perusahaan']);
        }
        if (!empty($order['nama_perusahaan'])) {
            $order['nama_perusahaan'] = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*){2,}/i', '$1 ', $order['nama_perusahaan']);
        }

        $mode = $f3->get('GET.mode') ?? '';
        // Hanya buat revisi baru jika ada permintaan eksplisit ?mode=baru, BUKAN otomatis saat mengedit
        $isRevisiBaru = ($mode === 'baru');
        $nomorSuratOtomatis = $spModel->generateNomorSurat();
        $revisiKe = count($allSp) + ($isRevisiBaru ? 1 : 0);
        if ($revisiKe === 0) {
            $revisiKe = 1;
        }

        $proposal = ($order['jenis_layanan_opti'] === 'selulosa') ? $orderModel->getProposalRiset($orderId) : null;
        $kalkulasi = ($order['jenis_layanan_opti'] === 'lingkungan') ? $orderModel->getKalkulasiLingkungan($orderId) : [];

        $durasiHari = 30;
        $spmVal = trim($order['spm_layanan'] ?? '');
        if (preg_match('/^(\d+)/', $spmVal, $m)) {
            $durasiHari = (int)$m[1];
        } elseif (strpos(strtolower($spmVal), 'bulan') !== false) {
            if (preg_match('/(\d+)\s*bulan/i', $spmVal, $mb)) {
                $durasiHari = (int)$mb[1] * 20;
            }
        } elseif (isset(\OrderLayanan::$SPM_LIST[$spmVal])) {
            $durasiStandar = \OrderLayanan::$SPM_LIST[$spmVal];
            if (preg_match('/(\d+)\s*bulan/i', $durasiStandar, $mb)) {
                $durasiHari = (int)$mb[1] * 20;
            }
        }
        $f3->set('durasi_hari', $durasiHari);

        $canEdit = ($this->hasPermission('penawaran:create') || $this->hasPermission('penawaran:edit') || $this->isSuperadmin() || $this->isAdminOrder());

        $defaultNominal = 0;
        if ($order['jenis_layanan_opti'] === 'lingkungan' && !empty($kalkulasi)) {
            $totalBruto = 0;
            foreach ($kalkulasi as $kIt) {
                $totalBruto += (float)$kIt['total_biaya_item'];
            }
            $diskonPenawaran = (float)($order['diskon_penawaran'] ?? 0);
            $defaultNominal = max(0.0, $totalBruto - $diskonPenawaran);
        }
        $f3->set('default_nominal', $defaultNominal);

        $f3->set('order', $order);
        $f3->set('sp_existing', $spExisting);
        $f3->set('all_sp', $allSp);
        $f3->set('is_revisi_baru', $isRevisiBaru);
        $f3->set('revisi_ke', $revisiKe);
        $f3->set('proposal', $proposal);
        $f3->set('kalkulasi', $kalkulasi);
        $pembuatNama = $_SESSION['user']['nama'] ?? ($_SESSION['nama_lengkap'] ?? ($spExisting['pembuat_nama'] ?? 'Tim Mitra BBSPJIS'));
        $f3->set('nomor_surat_otomatis', $nomorSuratOtomatis);
        $f3->set('pembuat_nama', $pembuatNama);
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

        if (!$this->hasPermission('penawaran:create') && !$this->isSuperadmin() && !$this->isAdminOrder()) {
            $this->setFlashError("Akses Ditolak: Penerbitan Surat Penawaran Resmi merupakan wewenang Tim Mitra.");
            $f3->reroute("/order/{$orderId}/penawaran/buat");
            return;
        }

        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);
        if (!$order) {
            $this->setFlashError("Order Layanan #{$orderId} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        // 1. Validasi Prasyarat Kaji Ulang Kelayakan Teknis (Tahap 2)
        $tinjauan = $orderModel->getTinjauanKelayakan($orderId);
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak')
        );
        if (!$tinjauanSelesai) {
            $this->setFlashError("Gagal: Kaji Ulang Kelayakan Teknis (Tahap 2) belum selesai atau tidak memenuhi syarat.");
            $f3->reroute("/order/{$orderId}");
            return;
        }

        // 2. Validasi Prasyarat Dokumen Proposal (Khusus OPTI Selulosa)
        if (($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
            $proposalCheck = $orderModel->getProposalRiset($orderId);
            $proposalValid = !empty($proposalCheck) && 
                !empty($proposalCheck['file_proposal']) && 
                (float)($proposalCheck['estimasi_total_biaya'] ?? 0) > 0;
            $proposalApproved = $proposalValid && (
                in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) ||
                in_array($proposalCheck['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])
            );

            if (!$proposalValid || !$proposalApproved) {
                $this->setFlashError("Gagal: Dokumen proposal OPTI Selulosa wajib diisi, berkas harus diunggah, dan disetujui Ketua Tim sebelum Surat Penawaran dapat disimpan.");
                $f3->reroute("/order/{$orderId}");
                return;
            }
        }

        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        $actionStatus = $post['action_status'] ?? '';
        if ($actionStatus === 'draft') {
            $post['status_respon_klien'] = 'draft';
        } elseif ($actionStatus === 'nego' || $actionStatus === 'terkirim') {
            if (empty($post['status_respon_klien']) || $post['status_respon_klien'] === 'draft') {
                $post['status_respon_klien'] = 'nego';
            }
        }

        $durasiAngka = (int)($post['durasi_hari'] ?? 0);
        if ($durasiAngka > 0) {
            $post['spm_layanan'] = $durasiAngka . ' Hari Kerja';
        } elseif (empty($post['spm_layanan']) || $post['spm_layanan'] === 'Lainnya') {
            $post['spm_layanan'] = '30 Hari Kerja';
        }

        try {
            $orderModel = new \OrderLayanan($this->db);
            $order = $orderModel->getDetail($orderId);

            // Otomatis generate dan simpan berkas PDF resmi ke folder uploads/penawaran
            $uploadDir = 'uploads/penawaran/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'Penawaran_' . $orderId . '_' . time() . '.pdf';
            $destPath = $uploadDir . $newFileName;

            $spDataForPdf = array_merge($post, [
                'pembuat_nama' => $_SESSION['user']['nama'] ?? ($_SESSION['nama_lengkap'] ?? 'Tim Mitra BBSPJIS')
            ]);
            $pdfObj = $this->generatePdfObject($order, $spDataForPdf);
            $pdfObj->Output('F', $destPath);
            $post['file_lampiran'] = $destPath;

            $spModel = new SuratPenawaran($this->db);
            $hasil = $spModel->buatDariOrder($orderId, $userId, $post);

            $isDraft = ($post['status_respon_klien'] === 'draft');

            // Notifikasi ke Ka Tim OPTI hanya jika surat diterbitkan resmi / terkirim
            if (!$isDraft) {
                try {
                    $orderModel = new \OrderLayanan($this->db);
                    $order = $orderModel->getDetail($orderId);
                    \NotificationService::send($this->db, [
                        'order_id'       => $orderId,
                        'target_role'    => 'ketua_tim',
                        'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                        'judul'          => 'Surat Penawaran Resmi Diterbitkan',
                        'pesan'          => "Surat Penawaran No. {$hasil['nomor_surat']} senilai Rp " . number_format($hasil['nominal'], 0, ',', '.') . " telah dikirimkan ke {$order['nama_perusahaan']}.",
                        'tipe'           => 'info',
                        'icon'           => 'bi-send-check-fill',
                        'link_url'       => "/order/{$orderId}",
                        'created_by'     => $userId,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                    ]);
                } catch (\Exception $eNotif) {}

                $this->setFlashSuccess(
                    "Surat Penawaran Resmi berhasil diterbitkan dengan Nomor: <strong>{$hasil['nomor_surat']}</strong> (Status: Negosiasi, Nominal: Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")."
                );
            } else {
                $this->setFlashSuccess(
                    "Draf Surat Penawaran Nomor <strong>{$hasil['nomor_surat']}</strong> berhasil disimpan (Status: Draft Internal)."
                );
            }
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

            $orderModel = new \OrderLayanan($this->db);
            $order = $orderModel->getDetail($orderId);

            // Jika status respon adalah DEAL, kirim notifikasi ke Pejabat & Ka Tim
            if ($statusRespon === 'deal') {
                $this->db->exec("UPDATE order_layanan SET status_keuangan = 'menunggu_pembayaran' WHERE id = ? AND (status_keuangan IS NULL OR status_keuangan = '' OR status_keuangan = 'belum_ditagih')", [1 => $orderId]);
                try {
                    // Notif ke Kepala Balai / Pejabat PPK
                    \NotificationService::send($this->db, [
                        'order_id'       => $orderId,
                        'target_role'    => 'pejabat',
                        'target_layanan' => 'semua',
                        'judul'          => 'Order DEAL - Menunggu Pembayaran',
                        'pesan'          => "Klien ({$order['nama_perusahaan']}) telah menyetujui penawaran Order #{$order['nomor_order']}. Menunggu verifikasi pembayaran oleh Tim Keuangan.",
                        'tipe'           => 'success',
                        'icon'           => 'bi-hand-thumbs-up-fill',
                        'link_url'       => "/order/{$orderId}",
                        'created_by'     => $this->getUserId() ?? 1,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                    ]);

                    // Notif ke Ka Tim OPTI
                    \NotificationService::send($this->db, [
                        'order_id'       => $orderId,
                        'target_role'    => 'ketua_tim',
                        'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                        'judul'          => 'Penawaran Order DEAL',
                        'pesan'          => "Klien ({$order['nama_perusahaan']}) telah sepakat dengan penawaran Order #{$order['nomor_order']}. Menunggu konfirmasi pembayaran.",
                        'tipe'           => 'success',
                        'icon'           => 'bi-check-circle-fill',
                        'link_url'       => "/order/{$orderId}",
                        'created_by'     => $this->getUserId() ?? 1,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                    ]);
                } catch (\Exception $eNotif) {}

                $this->setFlashSuccess("Klien menyetujui penawaran (<strong>DEAL</strong>). Status order beralih menjadi <strong>Menunggu Pembayaran</strong> oleh Tim Keuangan.");
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
        $nominalRaw   = preg_replace('/[^0-9]/', '', (string)($post['nominal_penawaran'] ?? '0'));
        $nominalBaru  = (float)($nominalRaw ?: 0);
        $orderId      = (int)($post['order_id'] ?? 0);

        try {
            $spModel = new SuratPenawaran($this->db);
            $hasil = $spModel->updateResponKlien($penawaranId, $statusRespon, $catatanNego, $nominalBaru);

            if ($statusRespon === 'deal') {
                if ($orderId > 0) {
                    $this->db->exec("UPDATE order_layanan SET status_keuangan = 'menunggu_pembayaran' WHERE id = ? AND (status_keuangan IS NULL OR status_keuangan = '' OR status_keuangan = 'belum_ditagih')", [1 => $orderId]);
                }
                $this->setFlashSuccess("Klien menyetujui penawaran (<strong>DEAL</strong>). Status order beralih menjadi <strong>Menunggu Pembayaran</strong> oleh Tim Keuangan.");
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
        $spId = (int)($f3->get('GET.id') ?? 0);
        $sp = null;
        if ($spId > 0) {
            $sp = $spModel->getById($spId);
            if ($sp && (int)$sp['order_id'] !== $orderId) {
                $sp = null;
            }
        }
        if (!$sp) {
            $sp = $spModel->getByOrderId($orderId);
        }

        if (!$sp) {
            $this->setFlashError('Surat penawaran untuk order ini belum diterbitkan. Silakan buat penawaran terlebih dahulu.');
            $f3->reroute("/order/{$orderId}/penawaran/buat");
            return;
        }

        $pdf = $this->generatePdfObject($order, $sp);

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="Surat_Penawaran_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $sp['nomor_surat']) . '.pdf"');
            header('Cache-Control: no-cache, no-store, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        $pdf->Output('I', 'Surat_Penawaran_' . $order['nomor_order'] . '.pdf');
        exit;
    }

    /**
     * Endpoint Live Preview PDF Surat Penawaran (Menerima input live dari form)
     * Route: GET|POST /order/@id/penawaran/preview-pdf
     */
    public function previewPdf($f3, $params)
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
        $existingSp = $spModel->getByOrderId($orderId) ?: [];

        // Ambil data live dari POST atau GET
        $nomorSurat   = trim((string)($f3->get('POST.nomor_surat') ?? $f3->get('GET.nomor_surat') ?? ($existingSp['nomor_surat'] ?? $spModel->generateNomorSurat())));
        $tanggalSurat = trim((string)($f3->get('POST.tanggal_surat') ?? $f3->get('GET.tanggal_surat') ?? ($existingSp['tanggal_surat'] ?? date('Y-m-d'))));
        $perusahaan   = trim((string)($f3->get('POST.perusahaan') ?? $f3->get('GET.perusahaan') ?? ($existingSp['perusahaan'] ?? $order['nama_perusahaan'])));
        $pic          = trim((string)($f3->get('POST.nama') ?? $f3->get('GET.nama') ?? ($existingSp['nama'] ?? $order['pic'])));
        $alamat       = trim((string)($f3->get('POST.alamat') ?? $f3->get('GET.alamat') ?? ($existingSp['alamat'] ?? ($order['alamatcustomer'] ?? $order['alamat']))));
        $perihal      = trim((string)($f3->get('POST.perihal') ?? $f3->get('GET.perihal') ?? ($existingSp['perihal'] ?? ('Penawaran Layanan Jasa OPTI - ' . $order['judul_kegiatan']))));
        
        $nominalRaw   = $f3->get('POST.nominal_penawaran') ?? $f3->get('GET.nominal_penawaran');
        $nominal      = ($nominalRaw !== null && $nominalRaw !== '') ? (float)str_replace(['.', ','], '', (string)$nominalRaw) : (float)($existingSp['nominal_penawaran'] ?? $order['estimasi_biaya']);

        $durasiHari   = (int)($f3->get('POST.durasi_hari') ?? $f3->get('GET.durasi_hari') ?? 30);
        $spmLayanan   = trim((string)($f3->get('POST.spm_layanan') ?? $f3->get('GET.spm_layanan') ?? ($durasiHari > 0 ? "{$durasiHari} Hari Kerja" : ($order['spm_layanan'] ?? '30 Hari Kerja'))));

        $spPreview = [
            'nomor_surat'       => $nomorSurat,
            'tanggal_surat'     => $tanggalSurat,
            'perusahaan'        => $perusahaan,
            'nama'              => $pic,
            'alamat'            => $alamat,
            'perihal'           => $perihal,
            'nominal_penawaran' => $nominal,
            'pembuat_nama'      => $_SESSION['user']['nama'] ?? ($_SESSION['nama_lengkap'] ?? ($existingSp['pembuat_nama'] ?? 'Tim Mitra BBSPJIS'))
        ];

        if (!empty($spmLayanan)) {
            $order['spm_layanan'] = $spmLayanan;
        }

        $pdf = $this->generatePdfObject($order, $spPreview);

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="Preview_Penawaran_' . $orderId . '.pdf"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        $pdf->Output('I', 'Preview_Penawaran_' . $orderId . '.pdf');
        exit;
    }

    /**
     * Membangun objek PDF Surat Penawaran Resmi SILOPTI sesuai Tata Naskah Dinas BBSPJIS
     */
    /**
     * Membangun objek PDF Surat Penawaran Resmi SILOPTI
     * Mendukung 2 format resmi BBSPJIS:
     * 1. Selulosa: Format 1-halaman presisi (Referensi: Penawaran IPB)
     * 2. Lingkungan: Format 4-halaman resmi berlampiran & lembar persetujuan (Referensi: PT Tritunggal Artha Makmur)
     */
    public function generatePdfObject(array $order, array $sp): SiloptiSuratPenawaranPdf
    {
        $jenis = strtolower(trim((string)($order['jenis_layanan_opti'] ?? 'selulosa')));
        if ($jenis === 'lingkungan' || strpos($jenis, 'lingkungan') !== false) {
            return $this->buildPdfLingkungan($order, $sp);
        }
        return $this->buildPdfSelulosa($order, $sp);
    }

    /**
     * Format 1: Surat Penawaran Layanan Jasa Selulosa (1 Halaman A4 Presisi Sesuai Penawaran IPB)
     */
    private function buildPdfSelulosa(array $order, array $sp): SiloptiSuratPenawaranPdf
    {
        $pdf = new SiloptiSuratPenawaranPdf('P', 'mm', 'A4');
        $pdf->jenisLayanan = 'selulosa';
        $pdf->AliasNbPages();
        $pdf->SetMargins(20, 12, 20);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $tglFormatted = self::formatTanggalIndoSurat($sp['tanggal_surat'] ?? date('Y-m-d'));
        $noSurat = $sp['nomor_surat'] ?: ('04/SP/BBSPJIS/IX/' . date('Y'));

        // Metadata Surat
        $yMeta = $pdf->GetY();
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(18, 4.5, 'Nomor', 0, 0); $pdf->Cell(4, 4.5, ':', 0, 0); $pdf->Cell(70, 4.5, $noSurat, 0, 1);
        $pdf->Cell(18, 4.5, 'Lampiran', 0, 0); $pdf->Cell(4, 4.5, ':', 0, 0); $pdf->Cell(70, 4.5, '-', 0, 1);
        $halSurat = !empty($sp['perihal']) ? $sp['perihal'] : 'Biaya layanan jasa';
        $pdf->Cell(18, 4.5, 'Hal', 0, 0); $pdf->Cell(4, 4.5, ':', 0, 0); $pdf->Cell(70, 4.5, $halSurat, 0, 1);

        $pdf->SetXY(110, $yMeta);
        $pdf->Cell(80, 4.5, 'Bandung, ' . $tglFormatted, 0, 1, 'R');
        $pdf->SetY($yMeta + 17);

        // Penerima Surat (Yth.)
        $perusahaan = !empty($sp['perusahaan']) ? trim($sp['perusahaan']) : (!empty($order['nama_perusahaan']) ? trim($order['nama_perusahaan']) : 'Pimpinan Perusahaan/Instansi');
        if (!empty($order['pt_cv']) && stripos($perusahaan, $order['pt_cv']) === false && stripos($perusahaan, 'IPB') === false && stripos($perusahaan, 'Pusat') === false) {
            $perusahaan = $order['pt_cv'] . ' ' . $perusahaan;
        }
        $ythTitle = (stripos($perusahaan, 'Pimpinan') === false && stripos($perusahaan, 'Kepala') === false && stripos($perusahaan, 'Direktur') === false)
            ? 'Pimpinan ' . $perusahaan
            : $perusahaan;

        $pic = !empty($sp['nama']) ? trim($sp['nama']) : (!empty($order['pic']) ? trim($order['pic']) : '');
        $alamat = !empty($sp['alamat']) ? trim($sp['alamat']) : (!empty($order['alamat']) ? trim($order['alamat']) : '');

        // Tentukan kota dari alamat jika tersedia
        $kotaTujuan = 'di Tempat';
        if (!empty($alamat)) {
            $lines = preg_split('/[\r\n,]+/', $alamat);
            $lastLine = trim(end($lines));
            if (!empty($lastLine)) {
                $kotaTujuan = 'di ' . $lastLine;
            }
        }

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 4.2, 'Yth. ' . $ythTitle, 0, 1);
        $pdf->Cell(0, 4.2, $kotaTujuan, 0, 1);
        if (!empty($pic) && strcasecmp($pic, $perusahaan) !== 0) {
            $pdf->Cell(0, 4.2, 'u.p. ' . $pic, 0, 1);
        }
        $pdf->Ln(3);

        // Paragraf Pembuka
        $judulKegiatan = !empty($order['judul_kegiatan']) ? $order['judul_kegiatan'] : 'Layanan Jasa Optimalisasi Teknologi Industri (OPTI)';
        $pdf->MultiCell(0, 4.2, 'Menanggapi permintaan Saudara perihal "' . $judulKegiatan . '", dengan ini kami informasikan kegiatan untuk tahap pelaksanaan adalah sebagai berikut:', 0, 'J');
        $pdf->Ln(1.5);

        // Poin 1, 2, 3
        $nominal = (float)($sp['nominal_penawaran'] ?? ($order['estimasi_biaya'] ?? 0));
        $terbilangStr = strtolower(self::terbilang($nominal)) . ' rupiah';

        $pdf->Cell(5, 4.2, '1.', 0, 0);
        $pdf->MultiCell(165, 4.2, 'Judul kegiatan: ' . $judulKegiatan . '.', 0, 'J');

        $pdf->Cell(5, 4.2, '2.', 0, 0);
        $pdf->MultiCell(165, 4.2, 'Biaya kegiatan sebesar Rp ' . number_format($nominal, 0, ',', '.') . ',- (' . $terbilangStr . ').', 0, 'J');

        $pdf->Cell(5, 4.2, '3.', 0, 0);
        $pdf->MultiCell(165, 4.2, 'Ruang lingkup pekerjaan dan waktu pelaksanaan pekerjaan tercantum dalam proposal terlampir.', 0, 'J');
        $pdf->Ln(2);

        // Ketentuan 1 - 5
        $pdf->MultiCell(0, 4.2, 'Disamping itu perlu kami sampaikan pula ketentuan sebagai berikut :', 0, 'L');
        $ketentuan = [
            'Persetujuan terhadap biaya pengujian mohon disampaikan secara tertulis melalui faks atau e-mail.',
            'Pengujian dilaksanakan setelah pembayaran biaya pekerjaan dilakukan dan setelah sampel diterima di laboratorium.',
            'Pembayaran biaya pengujian dilakukan dengan transfer melalui Virtual Account Bank.',
            'Untuk informasi lebih lanjut, dapat menghubungi Bagian Pemasaran pada nomor: 0813 8686 6808 / 0856 590 88 926.',
            'Dilarang memberi gratifikasi dalam bentuk apa pun atas layanan jasa yang kami berikan. Jika terdapat pemberian dan penerimaan gratifikasi, mohon dapat dilaporkan : http://bbs.kemenperin.go.id/kontak-kami/pengaduan-gratifikasi.'
        ];
        foreach ($ketentuan as $idx => $k) {
            $pdf->Cell(5, 4.2, ($idx + 1) . '.', 0, 0);
            $pdf->MultiCell(165, 4.2, $k, 0, 'J');
        }
        $pdf->Ln(2);

        $pdf->MultiCell(0, 4.2, 'Kami menunggu konfirmasi lebih lanjut. Atas perhatian dan kerjasama yang baik, kami sampaikan terima kasih.', 0, 'J');
        $pdf->Ln(3);

        // Bagian Bawah Bersebelahan: Kotak Persetujuan Kiri & Tanda Tangan Kanan
        $yBawah = $pdf->GetY();
        $pdf->Rect(20, $yBawah, 80, 30);
        $pdf->SetXY(22, $yBawah + 1.5);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(76, 3.8, 'Dengan ini kami menyetujui surat penawaran ini :', 0, 1);
        $pdf->SetX(22);
        $pdf->Cell(16, 3.8, 'Nama', 0, 0); $pdf->Cell(3, 3.8, ':', 0, 1);
        $pdf->SetX(22);
        $pdf->Cell(16, 3.8, 'Jabatan', 0, 0); $pdf->Cell(3, 3.8, ':', 0, 1);
        $pdf->SetX(22);
        $pdf->Cell(16, 3.8, 'No. Tlp', 0, 0); $pdf->Cell(3, 3.8, ':', 0, 1);
        $pdf->SetX(22);
        $pdf->Cell(76, 3.8, 'TTD & Stempel', 0, 1);

        // Kanan: Penandatangan Pejabat BBSPJIS
        $signerName = !empty($sp['pembuat_nama']) && $sp['pembuat_nama'] !== 'Tim Mitra Kerjasama BBSPJIS' ? $sp['pembuat_nama'] : 'Hagung Eko Pawoko';
        $pdf->SetXY(120, $yBawah + 1.5);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, 'plh. Kepala', 0, 1, 'C');
        $pdf->SetY($yBawah + 22);
        $pdf->SetX(120);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(70, 4.2, $signerName, 0, 1, 'C');

        // Tembusan (di bawah kotak persetujuan)
        $pdf->SetXY(20, $yBawah + 32);
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell(0, 3, 'Tembusan :', 0, 1);
        $pdf->Cell(3, 3, '1', 0, 0); $pdf->Cell(0, 3, 'Kepala. Bagian Tata Usaha', 0, 1);
        $pdf->Cell(3, 3, '2', 0, 0); $pdf->Cell(0, 3, 'Ketua Tim Layanan Mitra Industri', 0, 1);
        $pdf->Cell(3, 3, '3', 0, 0); $pdf->Cell(0, 3, 'Ketua Tim OPTI Kertas, Selulosa dan Produk Bahan Acuan', 0, 1);

        return $pdf;
    }

    /**
     * Format 2: Surat Penawaran Pengujian Lingkungan (4 Halaman Sesuai PT Tritunggal Artha Makmur)
     */
    private function buildPdfLingkungan(array $order, array $sp): SiloptiSuratPenawaranPdf
    {
        $orderModel = new OrderLayanan($this->db);
        $pdf = new SiloptiSuratPenawaranPdf('P', 'mm', 'A4');
        $pdf->jenisLayanan = 'lingkungan';
        $pdf->AliasNbPages();
        $pdf->SetMargins(20, 12, 20);
        $pdf->SetAutoPageBreak(true, 15);

        $tglFormatted = self::formatTanggalIndoSurat($sp['tanggal_surat'] ?? date('Y-m-d'));
        $noSurat = $sp['nomor_surat'] ?: ('01/SP/BBSPJIS/MS/' . date('m/Y'));
        $perusahaan = !empty($sp['perusahaan']) ? trim($sp['perusahaan']) : (!empty($order['nama_perusahaan']) ? trim($order['nama_perusahaan']) : 'Pimpinan Perusahaan/Instansi');
        if (!empty($order['pt_cv']) && stripos($perusahaan, $order['pt_cv']) === false) {
            $perusahaan = $order['pt_cv'] . ' ' . $perusahaan;
        }
        $alamat = !empty($sp['alamat']) ? trim($sp['alamat']) : (!empty($order['alamat']) ? trim($order['alamat']) : 'di Tempat');
        
        // Ambil kalkulasi rincian biaya lingkungan jika tersedia
        $kalkulasiRaw = !empty($order['id']) ? $orderModel->getKalkulasiLingkungan((int)$order['id']) : [];
        $subtotalBruto = 0;
        if (!empty($kalkulasiRaw)) {
            foreach ($kalkulasiRaw as $item) {
                $subtotalBruto += (float)$item['total_biaya_item'];
            }
        }
        $diskon = (float)($order['diskon_penawaran'] ?? 0);

        if (!empty($kalkulasiRaw) && $subtotalBruto > 0) {
            $nominal = max(0.0, $subtotalBruto - $diskon);
        } else {
            $nominal = (float)($sp['nominal_penawaran'] ?? ($order['estimasi_biaya'] ?? 0));
        }

        $terbilangStr = strtolower(self::terbilang($nominal)) . ' rupiah';
        $judulKegiatan = !empty($order['judul_kegiatan']) ? $order['judul_kegiatan'] : 'Pengujian Sampel Lingkungan BBSPJIS';
        $durasiStr = !empty($order['spm_layanan']) ? $order['spm_layanan'] : '4 bulan';
        $signerLing = !empty($sp['pembuat_nama']) && $sp['pembuat_nama'] !== 'Tim Mitra Kerjasama BBSPJIS' ? $sp['pembuat_nama'] : 'Joko Pratomo';

        // ==========================================
        // HALAMAN 1: SURAT PENGANTAR PENAWARAN RESMI
        // ==========================================
        $pdf->AddPage();
        $yMeta = $pdf->GetY();
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(18, 4.5, 'Nomor', 0, 0); $pdf->Cell(4, 4.5, ':', 0, 0); $pdf->Cell(70, 4.5, $noSurat, 0, 1);
        $pdf->Cell(18, 4.5, 'Lampiran', 0, 0); $pdf->Cell(4, 4.5, ':', 0, 0); $pdf->Cell(70, 4.5, '1 (satu) berkas', 0, 1);
        $halLing = !empty($sp['perihal']) ? $sp['perihal'] : ('Biaya Layanan ' . $judulKegiatan);
        $pdf->Cell(18, 4.5, 'Hal', 0, 0); $pdf->Cell(4, 4.5, ':', 0, 0); $pdf->Cell(70, 4.5, substr($halLing, 0, 45), 0, 1);

        $pdf->SetXY(110, $yMeta);
        $pdf->Cell(80, 4.5, 'Bandung, ' . $tglFormatted, 0, 1, 'R');
        $pdf->SetY($yMeta + 17);

        // Penerima
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 4.2, 'Yth. Pimpinan ' . $perusahaan, 0, 1);
        $alamatLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $alamat));
        foreach ($alamatLines as $alLine) {
            $alLine = trim($alLine);
            if (!empty($alLine)) {
                $pdf->Cell(0, 4.2, $alLine, 0, 1);
            }
        }
        $pdf->Ln(3);

        // Paragraf Pembuka
        $pdf->MultiCell(0, 4.2, 'Menanggapi permintaan Saudara perihal "' . $judulKegiatan . '", dengan ini kami informasikan sebagai berikut:', 0, 'J');
        $pdf->Ln(1.5);

        // Poin 1 s/d 4
        $pdf->Cell(5, 4.2, '1.', 0, 0);
        $pdf->MultiCell(165, 4.2, 'Pelaksanaan pekerjaan mengacu pada metode pengujian standar terakreditasi (OECD 301D: Closed Bottle Test / SNI acuan).', 0, 'J');

        $pdf->Cell(5, 4.2, '2.', 0, 0);
        $pdf->MultiCell(165, 4.2, 'Biaya pekerjaan tersebut adalah sebesar Rp ' . number_format($nominal, 0, ',', '.') . ',- (' . $terbilangStr . ') untuk 1 sampel.', 0, 'J');

        $pdf->Cell(5, 4.2, '3.', 0, 0);
        $pdf->MultiCell(165, 4.2, 'Waktu pelaksanaan selama ' . $durasiStr . ' dengan jadwal pelaksanaan seperti dalam lampiran.', 0, 'J');

        $pdf->Cell(5, 4.2, '4.', 0, 0);
        $pdf->MultiCell(165, 4.2, 'Biaya dan rincian pekerjaan terlampir, dengan ketentuan sebagai berikut:', 0, 'J');

        $subPoin = [
            'a' => 'Biaya pekerjaan ditagihkan dan dibayar melalui Virtual Account Mandiri.',
            'b' => 'Persetujuan terhadap biaya pekerjaan tersebut mohon disampaikan secara tertulis melalui fax atau e-mail.',
            'c' => 'Jadwal pelaksanaan pekerjaan akan disampaikan setelah pembayaran biaya pekerjaan dilakukan.',
            'd' => 'Dilarang memberi gratifikasi dalam bentuk apapun atas layanan jasa yang kami berikan, jika terdapat pemberian dan penerimaan gratifikasi mohon dapat dilaporkan ke : http://bbs.kemenperin.go.id/kontak-kami/pengaduan-gratifikasi.'
        ];
        foreach ($subPoin as $k => $v) {
            $pdf->SetX(25);
            $pdf->Cell(5, 4.2, $k . '.', 0, 0);
            $pdf->MultiCell(160, 4.2, $v, 0, 'J');
        }
        $pdf->Ln(2);

        $pdf->MultiCell(0, 4.2, 'Kami menunggu konfirmasi lebih lanjut. Atas perhatian dan kerja sama yang baik, kami sampaikan terima kasih.', 0, 'J');
        $pdf->Ln(6);

        // Tanda Tangan Cover
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, 'Kepala Bagian Tata Usaha', 0, 1, 'C');
        $pdf->Ln(18);
        $pdf->SetX(120);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(70, 4.2, $signerLing, 0, 1, 'C');

        // ==========================================
        // HALAMAN 2: LAMPIRAN I (TEKNIS & JADWAL)
        // ==========================================
        $pdf->AddPage();
        $pdf->SetY(18);
        $pdf->SetX(130);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(20, 4, 'Lampiran I', 0, 1);
        $pdf->SetX(130);
        $pdf->Cell(15, 4, 'Nomor', 0, 0); $pdf->Cell(3, 4, ':', 0, 0); $pdf->Cell(42, 4, $noSurat, 0, 1);
        $pdf->SetX(130);
        $pdf->Cell(15, 4, 'Tanggal', 0, 0); $pdf->Cell(3, 4, ':', 0, 0); $pdf->Cell(42, 4, $tglFormatted, 0, 1);
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->MultiCell(0, 4.5, strtoupper($judulKegiatan), 0, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 4.2, 'A.  METODE', 0, 1);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(5, 4.2, '', 0, 0);
        $pdf->Cell(0, 4.2, 'OECD 301D Closed Bottle Test / Standar Baku Mutu Terakreditasi', 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 4.2, 'B.  LATAR BELAKANG', 0, 1);
        $pdf->SetFont('Arial', '', 8.5);
        $bgText = "Pelaksanaan uji Biodegradasi dengan metode OECD 301D merupakan salah satu metode yang ada dalam seri OECD 301 untuk menilai sifat ready biodegradability dari suatu senyawa organic. Lebih lanjut metode ini digunakan untuk menguji apakah suatu bahan kimia mudah terbiodegradasi (readily biodegradable) dalam kondisi aerobik pada lingkungan akuatik seperti air permukaan, atau air sungai. Penggunaan metode ini dapat digunakan untuk penilaian produk ramah lingkungan ataupun digunakan dalam rangka pemenuhan sertifikasi ecolabel tipe 2 swadeklarasi.";
        $pdf->MultiCell(0, 4.2, $bgText, 0, 'J');
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 4.2, 'C.  RUANG LINGKUP', 0, 1);
        $pdf->SetFont('Arial', '', 8.5);
        $lingkup = [
            'Metode ini mencakup pengujian sifat ready biodegradability dari sampel uji.',
            'Contoh uji berupa 1 sampel produk ' . $perusahaan . '.',
            'Pengujian dilakukan di laboratorium Biodetox dengan kondisi yang terkontrol.',
            'Validitas metode ini sesuai yang tertera pada metode uji standar.',
            'Periode uji dilakukan dengan inkubasi selama 28 hari dengan 2 bulan sebelum sebagai masa persiapan dan pengkondisian mikroba dan 1 bulan setelah uji untuk evaluasi dan penyiapan laporan.'
        ];
        foreach ($lingkup as $idx => $l) {
            $pdf->Cell(5, 4.2, ($idx + 1) . '.', 0, 0);
            $pdf->MultiCell(165, 4.2, $l, 0, 'J');
        }
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 4.2, 'D.  TAHAPAN KEGIATAN', 0, 1);
        $pdf->SetFont('Arial', '', 8.5);
        $tahapan = [
            'Persiapan alat dan bahan',
            'Persiapan media dan mikroba uji',
            'Pelaksanaan uji',
            'Pengolahan data dan pembuatan laporan'
        ];
        foreach ($tahapan as $idx => $th) {
            $pdf->Cell(5, 4.2, ($idx + 1) . '.', 0, 0);
            $pdf->Cell(165, 4.2, $th, 0, 1);
        }
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 4.2, 'E.  JADWAL', 0, 1);
        $pdf->Ln(1);

        // Tabel Jadwal Gantt
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        $xStart = $pdf->GetX(); // 20
        $yStart = $pdf->GetY();
        $hHeader = 8; // Total tinggi header (4 + 4)
        $hSub = 4;    // Tinggi baris sub-header

        // 1. Kolom No. (rowspan 2)
        $pdf->Rect($xStart, $yStart, 8, $hHeader);
        $pdf->SetXY($xStart, $yStart);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(8, $hHeader, 'No.', 0, 0, 'C');

        // 2. Kolom Kegiatan (rowspan 2)
        $pdf->Rect($xStart + 8, $yStart, 58, $hHeader);
        $pdf->SetXY($xStart + 8, $yStart);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(58, $hHeader, 'Kegiatan', 0, 0, 'C');

        // 3. Header Bulan 1 s/d 4 (Baris 1)
        $xMonth = $xStart + 8 + 58; // 86
        for ($b = 1; $b <= 4; $b++) {
            $pdf->SetXY($xMonth + (($b - 1) * 26), $yStart);
            $pdf->Cell(26, $hSub, 'Bulan ' . $b, 1, 0, 'C');
        }

        // 4. Sub-kolom Minggu 1 s/d 4 untuk tiap Bulan (Baris 2)
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetXY($xMonth, $yStart + $hSub);
        for ($b = 0; $b < 4; $b++) {
            for ($w = 1; $w <= 4; $w++) {
                $pdf->Cell(6.5, $hSub, $w, 1, 0, 'C');
            }
        }

        // Posisikan kursor tepat di bawah header untuk baris data
        $pdf->SetXY($xStart, $yStart + $hHeader);

        $jadwalRows = [
            ['Persiapan alat dan bahan', [1, 2]],
            ['Persiapan media dan mikroba uji', [2, 3]],
            ['Pelaksanaan uji', [4, 5, 6, 7, 8, 9]],
            ['Pengolahan data dan pembuatan laporan', [15, 16]]
        ];

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetFillColor(251, 191, 36); // Amber highlight
        foreach ($jadwalRows as $idx => $jr) {
            $pdf->Cell(8, 5, ($idx + 1), 1, 0, 'C');
            $pdf->Cell(58, 5, '  ' . $jr[0], 1, 0, 'L');
            for ($col = 1; $col <= 16; $col++) {
                $isFill = in_array($col, $jr[1]);
                $pdf->Cell(6.5, 5, '', 1, 0, 'C', $isFill);
            }
            $pdf->Ln(5);
        }
        $pdf->Ln(1.5);
        $pdf->SetFont('Arial', 'I', 7.5);
        $pdf->Cell(0, 4, '*Estimasi waktu ini bersifat minimal, waktu pelaksanaan bisa lebih lama apabila ada kondisi-kondisi tertentu yang terjadi.', 0, 1);

        // ==========================================
        // HALAMAN 3: LAMPIRAN II (RAB)
        // ==========================================
        $pdf->AddPage();
        $pdf->SetY(18);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 5, 'F.  RANCANGAN ANGGARAN BIAYA (RAB)', 0, 1);
        $pdf->Ln(2);

        $kalkulasiRaw = !empty($order['id']) ? $orderModel->getKalkulasiLingkungan((int)$order['id']) : [];

        if (!empty($kalkulasiRaw)) {
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->Cell(8, 6, 'No', 1, 0, 'C');
            $pdf->Cell(75, 6, 'Parameter Pengujian & Standar Baku', 1, 0, 'L');
            $pdf->Cell(25, 6, 'Titik/Sampel', 1, 0, 'C');
            $pdf->Cell(32, 6, 'Tarif Satuan (Rp)', 1, 0, 'R');
            $pdf->Cell(30, 6, 'Subtotal (Rp)', 1, 1, 'R');

            $pdf->SetFont('Arial', '', 8);
            $no = 1;
            $subtotalBruto = 0;
            foreach ($kalkulasiRaw as $item) {
                $subtotalBruto += (float)$item['total_biaya_item'];
                $namaParam = $item['nama_pengujian'];
                if (!empty($item['standar_rujukan'])) {
                    $namaParam .= ' (' . $item['standar_rujukan'] . ')';
                }
                $pdf->Cell(8, 5.5, $no++, 1, 0, 'C');
                $pdf->Cell(75, 5.5, substr($namaParam, 0, 48), 1, 0, 'L');
                $pdf->Cell(25, 5.5, (int)$item['jumlah_sampel'] . ' Titik/Sampel', 1, 0, 'C');
                $pdf->Cell(32, 5.5, number_format((float)$item['tarif_per_sampel'], 0, ',', '.'), 1, 0, 'R');
                $pdf->Cell(30, 5.5, number_format((float)$item['total_biaya_item'], 0, ',', '.'), 1, 1, 'R');
            }

            $diskon = (float)($order['diskon_penawaran'] ?? 0);
            if ($diskon > 0) {
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(140, 5.5, 'Subtotal Biaya Pengujian', 1, 0, 'R');
                $pdf->Cell(30, 5.5, 'Rp ' . number_format($subtotalBruto, 0, ',', '.'), 1, 1, 'R');

                $pdf->Cell(140, 5.5, 'Potongan Diskon Penawaran', 1, 0, 'R');
                $pdf->Cell(30, 5.5, '- Rp ' . number_format($diskon, 0, ',', '.'), 1, 1, 'R');
            }

            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->Cell(140, 6, 'TOTAL NETTO BIAYA PENAWARAN', 1, 0, 'R');
            $pdf->Cell(30, 6, 'Rp ' . number_format($nominal, 0, ',', '.'), 1, 1, 'R');
        } else {
            // Sesuai Dokumen Referensi PT Tritunggal Artha Makmur
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->Cell(8, 6, 'No', 1, 0, 'C');
            $pdf->Cell(82, 6, 'Uraian Kegiatan / Parameter Pengujian', 1, 0, 'L');
            $pdf->Cell(45, 6, 'Biaya (Rp)', 1, 0, 'R');
            $pdf->Cell(35, 6, 'Total Biaya (Rp)', 1, 1, 'R');

            $pdf->SetFont('Arial', '', 8);
            $biayaOperasional = round($nominal * 0.5333);
            $biayaJasaPersiapan = round($nominal * 0.0667);
            $biayaUji = round($nominal * 0.2667);
            $biayaJasaUji = round($nominal * 0.0667);
            $biayaLaporan = $nominal - ($biayaOperasional + $biayaJasaPersiapan + $biayaUji + $biayaJasaUji);

            $pdf->Cell(8, 5, '1', 1, 0, 'C');
            $pdf->Cell(82, 5, 'Tahap Persiapan', 1, 0, 'L');
            $pdf->Cell(45, 5, '', 1, 0, 'R');
            $pdf->Cell(35, 5, '', 1, 1, 'R');

            $pdf->Cell(8, 5, '', 1, 0, 'C');
            $pdf->Cell(82, 5, '    a  Pengadaan bahan dan operasional', 1, 0, 'L');
            $pdf->Cell(45, 5, number_format($biayaOperasional, 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(35, 5, '', 1, 1, 'R');

            $pdf->Cell(8, 5, '', 1, 0, 'C');
            $pdf->Cell(82, 5, '    b  Jasa Tahap Persiapan', 1, 0, 'L');
            $pdf->Cell(45, 5, number_format($biayaJasaPersiapan, 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(35, 5, '', 1, 1, 'R');

            $pdf->Cell(8, 5, '2', 1, 0, 'C');
            $pdf->Cell(82, 5, 'Tahap Pengujian, pengamatan perkecambahan dan analisis data', 1, 0, 'L');
            $pdf->Cell(45, 5, '', 1, 0, 'R');
            $pdf->Cell(35, 5, number_format($nominal, 0, ',', '.'), 1, 1, 'R');

            $pdf->Cell(8, 5, '', 1, 0, 'C');
            $pdf->Cell(82, 5, '    a  Operasional', 1, 0, 'L');
            $pdf->Cell(45, 5, number_format($biayaUji, 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(35, 5, '', 1, 1, 'R');

            $pdf->Cell(8, 5, '', 1, 0, 'C');
            $pdf->Cell(82, 5, '    b  Jasa pengamatan dan analisis data', 1, 0, 'L');
            $pdf->Cell(45, 5, number_format($biayaJasaUji, 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(35, 5, '', 1, 1, 'R');

            $pdf->Cell(8, 5, '3', 1, 0, 'C');
            $pdf->Cell(82, 5, 'Interpretasi hasil, evaluasi, dan pelaporan', 1, 0, 'L');
            $pdf->Cell(45, 5, number_format($biayaLaporan, 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(35, 5, '', 1, 1, 'R');

            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->Cell(90, 6, 'TOTAL BIAYA PENAWARAN', 1, 0, 'R');
            $pdf->Cell(80, 6, 'Rp ' . number_format($nominal, 0, ',', '.'), 1, 1, 'R');
        }
        $pdf->Ln(12);

        // Tanda Tangan RAB
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, 'Kepala Bagian Tata Usaha', 0, 1, 'C');
        $pdf->Ln(18);
        $pdf->SetX(120);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(70, 4.2, $signerLing, 0, 1, 'C');

        // ==========================================
        // HALAMAN 4: LEMBAR PERSETUJUAN SURAT PENAWARAN
        // ==========================================
        $pdf->AddPage();
        $pdf->SetY(20);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Lembar Persetujuan Surat Penawaran', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 4.5, 'Dengan ini menyatakan bahwa telah membaca, memahami, dan menyetujui Surat Penawaran BBSPJIS.', 0, 'J');
        $pdf->Ln(2);
        $pdf->Cell(0, 4.5, 'Selanjutnya kami menyatakan:', 0, 1);
        $pernyataan = [
            'Menyetujui ruang lingkup pekerjaan, biaya layanan, serta syarat dan ketentuan yang tercantum dalam surat penawaran.',
            'Bersedia memenuhi kewajiban pembayaran sesuai ketentuan yang berlaku setelah diterbitkan tagihan (invoice) oleh BBSPJIS.',
            'Persetujuan ini menjadi dasar bagi BBSPJIS untuk memproses permohonan layanan sesuai dengan ketentuan yang berlaku.',
            'Apabila terdapat perubahan ruang lingkup pekerjaan setelah persetujuan ini diberikan, maka akan dilakukan penyesuaian melalui kesepakatan kedua belah pihak.'
        ];
        foreach ($pernyataan as $idx => $p) {
            $pdf->Cell(5, 4.5, ($idx + 1) . '.', 0, 0);
            $pdf->MultiCell(165, 4.5, $p, 0, 'J');
        }
        $pdf->Ln(2);

        $pdf->MultiCell(0, 4.5, 'Demikian Lembar Persetujuan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.', 0, 'J');
        $pdf->Ln(4);

        // Tabel Isian Persetujuan
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Cell(50, 7, 'Tempat, Tanggal', 1, 0, 'L');
        $pdf->Cell(120, 7, 'Bandung, ............................................ ' . date('Y'), 1, 1, 'L');

        $pdf->Cell(50, 7, 'Menyetujui,', 1, 0, 'L');
        $pdf->Cell(120, 7, '', 1, 1, 'L');

        $pdf->Cell(50, 7, 'Nama Perusahaan/Instansi', 1, 0, 'L');
        $pdf->Cell(120, 7, $perusahaan, 1, 1, 'L');

        $pdf->Cell(50, 7, 'Nama Pemohon', 1, 0, 'L');
        $pdf->Cell(120, 7, $pic ?: '', 1, 1, 'L');

        $pdf->Cell(50, 7, 'Jabatan', 1, 0, 'L');
        $pdf->Cell(120, 7, '', 1, 1, 'L');

        $pdf->Cell(50, 7, 'Nomor Telepon (WhatsApp)', 1, 0, 'L');
        $telp = !empty($order['telepon']) ? $order['telepon'] : '';
        $pdf->Cell(120, 7, $telp, 1, 1, 'L');

        $pdf->Cell(50, 26, 'Tanda Tangan dan Cap', 1, 0, 'L');
        $pdf->Cell(120, 26, '', 1, 1, 'L');

        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Cell(0, 4, 'Catatan:', 0, 1);
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 4, 'Mohon mengembalikan lembar persetujuan ini kepada BBSPJIS melalui email atau media komunikasi yang telah ditentukan sebagai dasar proses penerbitan invoice dan pelaksanaan layanan', 0, 'L');

        return $pdf;
    }

    /**
     * Helper format tanggal ke Bahasa Indonesia (Contoh: 7 September 2026)
     */
    private static function formatTanggalIndoSurat(?string $dateStr): string
    {
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $time = !empty($dateStr) ? strtotime($dateStr) : time();
        if (!$time) $time = time();
        $d = date('j', $time);
        $m = (int)date('n', $time);
        $y = date('Y', $time);
        return $d . ' ' . ($bulanIndo[$m] ?? date('F', $time)) . ' ' . $y;
    }

    /**
     * Helper konversi angka ke ejaan Bahasa Indonesia (Terbilang)
     */
    private static function terbilang(float $nilai): string
    {
        $nilai = abs($nilai);
        $huruf = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
        $temp = "";
        if ($nilai < 12) {
            $temp = " " . $huruf[(int)$nilai];
        } else if ($nilai < 20) {
            $temp = self::terbilang($nilai - 10) . " belas";
        } else if ($nilai < 100) {
            $temp = self::terbilang((int)($nilai / 10)) . " puluh " . self::terbilang($nilai % 10);
        } else if ($nilai < 200) {
            $temp = " seratus " . self::terbilang($nilai - 100);
        } else if ($nilai < 1000) {
            $temp = self::terbilang((int)($nilai / 100)) . " ratus " . self::terbilang($nilai % 100);
        } else if ($nilai < 2000) {
            $temp = " seribu " . self::terbilang($nilai - 1000);
        } else if ($nilai < 1000000) {
            $temp = self::terbilang((int)($nilai / 1000)) . " ribu " . self::terbilang($nilai % 1000);
        } else if ($nilai < 1000000000) {
            $temp = self::terbilang((int)($nilai / 1000000)) . " juta " . self::terbilang(fmod($nilai, 1000000));
        } else if ($nilai < 1000000000000) {
            $temp = self::terbilang((int)($nilai / 1000000000)) . " milyar " . self::terbilang(fmod($nilai, 1000000000));
        } else if ($nilai < 1000000000000000) {
            $temp = self::terbilang((int)($nilai / 1000000000000)) . " trilyun " . self::terbilang(fmod($nilai, 1000000000000));
        }
        return trim(preg_replace('/\s+/', ' ', $temp));
    }
}
