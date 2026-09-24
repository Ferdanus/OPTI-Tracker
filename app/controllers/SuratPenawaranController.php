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
                // Halaman 2 ke atas (Standar Dokumen Berlampiran BBSPJIS: - 2 -, - 3 -, dst)
                $this->SetY(10);
                $this->SetFont('Arial', '', 9);
                $this->SetTextColor(0, 0, 0);
                $this->Cell(0, 5, '- ' . $this->PageNo() . ' -', 0, 1, 'C');
                $this->Ln(2);
            }
        }

        public function Footer() {
            // Footer kosong
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
        $currentYear   = date('Y');
        $filterTahun   = $f3->exists('GET.tahun') ? trim((string)$f3->get('GET.tahun')) : $currentYear;
        $daftarTahun   = range((int)$currentYear, (int)$currentYear - 4);

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

        // Filter Tahun (sama seperti di daftar order)
        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql      .= ' AND YEAR(COALESCE(sp.tanggal_surat, sp.created_at)) = ?';
            $params[]  = (int)$filterTahun;
        }

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
        $f3->set('filter_tahun', $filterTahun);
        $f3->set('daftar_tahun', $daftarTahun);
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

        // 1. Validasi Prasyarat Kaji Ulang Kelayakan Teknis (Tahap 2 - Khusus Selulosa)
        $tinjauan = $orderModel->getTinjauanKelayakan($orderId);
        $isSelulosa = (($order['jenis_layanan_opti'] ?? '') === 'selulosa');
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak') ||
            !$isSelulosa
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
        // Default selalu terbitkan sebagai revisi baru jika sudah ada surat penawaran sebelumnya
        $isRevisiBaru = !empty($spExisting);
        $nomorSuratOtomatis = $spModel->generateNomorSurat();
        $revisiKe = count($allSp) + 1;

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

        $defaultRuangLingkup = '';
        $f3->set('default_ruang_lingkup', $defaultRuangLingkup);

        if ($order['jenis_layanan_opti'] === 'lingkungan') {
            $f3->set('default_pejabat_nama', 'Joko Pratomo');
            $f3->set('default_jabatan_pejabat', 'Kepala Bagian Tata Usaha');
            $f3->set('default_hal', '');
            $f3->set('default_lampiran_teks', '');
        } else {
            $f3->set('default_pejabat_nama', 'Dodiet Prasetyo');
            $f3->set('default_jabatan_pejabat', 'Kepala');
            $f3->set('default_hal', 'Biaya OPTI');
            $f3->set('default_lampiran_teks', '1 (satu) lembar');
        }

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

        // Audit Tahap 5: Surat Penawaran resmi dibuka / dikelola Tim Mitra
        $userId = (int)$this->getUserId();
        if ($userId > 0) {
            \StageAudit::recordDibaca($this->db, $orderId, 5, $userId, $pembuatNama, 'Tim Mitra');
        }

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

        // 1. Validasi Prasyarat Kaji Ulang Kelayakan Teknis (Tahap 2 - Khusus Selulosa)
        $tinjauan = $orderModel->getTinjauanKelayakan($orderId);
        $isSelulosa = (($order['jenis_layanan_opti'] ?? '') === 'selulosa');
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak') ||
            !$isSelulosa
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

            // Catat audit pengiriman Tahap 5 (Surat Penawaran Resmi)
            if (!$isDraft) {
                \StageAudit::recordKirim(
                    $this->db,
                    $orderId,
                    5,
                    'Surat Penawaran Biaya Resmi',
                    $userId,
                    $_SESSION['nama_lengkap'] ?? ($_SESSION['user']['nama'] ?? 'Tim Mitra'),
                    'Tim Mitra',
                    date('Y-m-d H:i:s'),
                    'Surat Penawaran Resmi Diterbitkan (No: ' . $hasil['nomor_surat'] . ')'
                );

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
        $targetId = (int)($params['id'] ?? 0);
        $spModel  = new SuratPenawaran($this->db);
        $orderModel = new OrderLayanan($this->db);

        $order = $orderModel->getDetail($targetId);
        $sp    = null;

        if ($order) {
            $spId = (int)($f3->get('GET.id') ?? 0);
            if ($spId > 0) {
                $sp = $spModel->getById($spId);
                if ($sp && (int)$sp['order_id'] !== $targetId) {
                    $sp = null;
                }
            }
            if (!$sp) {
                $sp = $spModel->getByOrderId($targetId);
            }
        } else {
            // Coba cari sebagai ID Surat Penawaran langsung
            $sp = $spModel->getById($targetId);
            if ($sp && !empty($sp['order_id'])) {
                $order = $orderModel->getDetail((int)$sp['order_id']);
            }
        }

        if (!$sp) {
            $this->setFlashError('Surat penawaran tidak ditemukan.');
            $f3->reroute('/surat-penawaran');
            return;
        }

        if (!$order) {
            // Fallback order info dari data surat penawaran
            $order = [
                'id'                 => (int)($sp['order_id'] ?? 0),
                'nomor_order'        => 'ORD-OFFLINE',
                'nama_perusahaan'    => $sp['perusahaan'] ?? 'Mitra Balai',
                'pt_cv'              => '',
                'pic'                => $sp['nama'] ?? '-',
                'telepon'            => '-',
                'email'              => '-',
                'alamat'             => $sp['alamat'] ?? '-',
                'jenis_layanan_opti' => $sp['jenis_layanan'] ?? 'selulosa',
                'judul_kegiatan'     => $sp['perihal'] ?? 'Penawaran Layanan Jasa OPTI',
                'estimasi_biaya'     => (float)($sp['nominal_penawaran'] ?? 0),
                'spm_layanan'        => '30 Hari Kerja'
            ];
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
     * Endpoint Data JSON Base64 untuk Pratinjau Surat Penawaran Bebas IDM (Zero Interception)
     * Route: GET /surat-penawaran/@id/raw-data
     * Route: GET /order/@id/penawaran/raw-data
     */
    public function penawaranRawData($f3, $params)
    {
        $this->requireAuth();
        $targetId = (int)($params['id'] ?? 0);
        $spModel  = new SuratPenawaran($this->db);
        $orderModel = new OrderLayanan($this->db);

        $order = $orderModel->getDetail($targetId);
        $sp    = null;

        if ($order) {
            $spId = (int)($f3->get('GET.id') ?? 0);
            if ($spId > 0) {
                $sp = $spModel->getById($spId);
                if ($sp && (int)$sp['order_id'] !== $targetId) {
                    $sp = null;
                }
            }
            if (!$sp) {
                $sp = $spModel->getByOrderId($targetId);
            }
        } else {
            $sp = $spModel->getById($targetId);
            if ($sp && !empty($sp['order_id'])) {
                $order = $orderModel->getDetail((int)$sp['order_id']);
            }
        }

        if (!$sp) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Surat penawaran tidak ditemukan.']);
            exit;
        }

        if (!$order) {
            $order = [
                'id'                 => (int)($sp['order_id'] ?? 0),
                'nomor_order'        => 'ORD-OFFLINE',
                'nama_perusahaan'    => $sp['perusahaan'] ?? 'Mitra Balai',
                'pt_cv'              => '',
                'pic'                => $sp['nama'] ?? '-',
                'telepon'            => '-',
                'email'              => '-',
                'alamat'             => $sp['alamat'] ?? '-',
                'jenis_layanan_opti' => $sp['jenis_layanan'] ?? 'selulosa',
                'judul_kegiatan'     => $sp['perihal'] ?? 'Penawaran Layanan Jasa OPTI',
                'estimasi_biaya'     => (float)($sp['nominal_penawaran'] ?? 0),
                'spm_layanan'        => '30 Hari Kerja'
            ];
        }

        try {
            $pdf = $this->generatePdfObject($order, $sp);
            $pdfContent = $pdf->Output('S');
            $base64 = base64_encode($pdfContent);

            $safeFilename = 'Surat_Penawaran_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $sp['nomor_surat'] ?: 'BBSPJIS') . '.pdf';

            if (!headers_sent()) {
                header('Content-Type: application/json');
                header('Cache-Control: no-cache, no-store, must-revalidate');
            }
            echo json_encode([
                'success'     => true,
                'is_pdf'      => true,
                'filename'    => $safeFilename,
                'nomor_surat' => $sp['nomor_surat'] ?? '',
                'perusahaan'  => $order['nama_perusahaan'] ?? ($sp['perusahaan'] ?? ''),
                'base64'      => $base64
            ]);
            exit;
        } catch (\Exception $e) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Gagal memproses dokumen PDF: ' . $e->getMessage()]);
            exit;
        }
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
            'nomor_surat'        => $nomorSurat,
            'tanggal_surat'      => $tanggalSurat,
            'perusahaan'         => $perusahaan,
            'nama'               => $pic,
            'alamat'             => $alamat,
            'perihal'            => $perihal,
            'hal'                => trim((string)($f3->get('POST.hal') ?? $f3->get('GET.hal') ?? ($existingSp['hal'] ?? ''))),
            'lampiran_teks'      => trim((string)($f3->get('POST.lampiran_teks') ?? $f3->get('GET.lampiran_teks') ?? ($existingSp['lampiran_teks'] ?? ''))),
            'nominal_penawaran'  => $nominal,
            'durasi_hari'        => $durasiHari,
            'ruang_lingkup'      => $f3->get('POST.ruang_lingkup') ?? $f3->get('GET.ruang_lingkup') ?? ($existingSp['ruang_lingkup'] ?? null),
            'jadwal_pelaksanaan' => $f3->get('POST.jadwal_pelaksanaan') ?? $f3->get('GET.jadwal_pelaksanaan') ?? ($existingSp['jadwal_pelaksanaan'] ?? null),
            'catatan_sampel'     => $f3->get('POST.catatan_sampel') ?? $f3->get('GET.catatan_sampel') ?? ($existingSp['catatan_sampel'] ?? null),
            'jabatan_pejabat'    => $f3->get('POST.jabatan_pejabat') ?? $f3->get('GET.jabatan_pejabat') ?? ($existingSp['jabatan_pejabat'] ?? 'Kepala'),
            'pejabat_nama'       => $f3->get('POST.pejabat_nama') ?? $f3->get('GET.pejabat_nama') ?? ($existingSp['pejabat_nama'] ?? 'Dodiet Prasetyo'),
            'pembuat_nama'       => $_SESSION['user']['nama'] ?? ($_SESSION['nama_lengkap'] ?? ($existingSp['pembuat_nama'] ?? 'Tim Mitra BBSPJIS'))
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
     * Mendukung 2 format resmi BBSPJIS:
     * 1. Selulosa: Format 2-halaman (Halaman 1 Surat Penawaran Resmi + Halaman 2 Lembar Persetujuan)
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
     * Format 1: Surat Penawaran Layanan Jasa Selulosa (2 Halaman Presisi: Cover Penawaran + Lembar Persetujuan)
     */
    private function buildPdfSelulosa(array $order, array $sp): SiloptiSuratPenawaranPdf
    {
        $pdf = new SiloptiSuratPenawaranPdf('P', 'mm', 'A4');
        $pdf->jenisLayanan = 'selulosa';
        $pdf->AliasNbPages();
        $pdf->SetMargins(20, 10, 20);
        $pdf->SetAutoPageBreak(false);

        // ==========================================
        // HALAMAN 1: SURAT PENAWARAN RESMI
        // ==========================================
        $pdf->AddPage();

        $tglFormatted = self::formatTanggalIndoSurat($sp['tanggal_surat'] ?? date('Y-m-d'));
        $noSurat = $sp['nomor_surat'] ?: ('01/SP/BBSPJIS/IX/' . date('Y'));
        $lampiranTeks = !empty($sp['lampiran_teks']) ? $sp['lampiran_teks'] : '1 (satu) lembar';
        $halSurat = !empty($sp['hal']) ? $sp['hal'] : (!empty($sp['perihal']) ? $sp['perihal'] : 'Biaya OPTI');

        // Metadata Kiri & Tanggal Kanan
        $yMeta = $pdf->GetY();
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(18, 4.3, 'Nomor', 0, 0); $pdf->Cell(4, 4.3, ':', 0, 0); $pdf->Cell(70, 4.3, $noSurat, 0, 1);
        $pdf->Cell(18, 4.3, 'Lampiran', 0, 0); $pdf->Cell(4, 4.3, ':', 0, 0); $pdf->Cell(70, 4.3, $lampiranTeks, 0, 1);
        $pdf->Cell(18, 4.3, 'Hal', 0, 0); $pdf->Cell(4, 4.3, ':', 0, 0); $pdf->Cell(70, 4.3, $halSurat, 0, 1);

        $pdf->SetXY(110, $yMeta);
        $pdf->Cell(80, 4.3, 'Bandung, ' . $tglFormatted, 0, 1, 'R');
        $pdf->SetY($yMeta + 16);

        // Penerima Surat (Yth.)
        $perusahaan = !empty($sp['perusahaan']) ? trim($sp['perusahaan']) : (!empty($order['nama_perusahaan']) ? trim($order['nama_perusahaan']) : 'Pimpinan Perusahaan/Instansi');
        if (!empty($order['pt_cv']) && stripos($perusahaan, $order['pt_cv']) === false && stripos($perusahaan, 'IPB') === false && stripos($perusahaan, 'Pusat') === false) {
            $perusahaan = $order['pt_cv'] . ' ' . $perusahaan;
        }
        $ythTitle = (stripos($perusahaan, 'Pimpinan') === false && stripos($perusahaan, 'Kepala') === false && stripos($perusahaan, 'Direktur') === false)
            ? 'Pimpinan ' . $perusahaan
            : $perusahaan;

        $alamat = !empty($sp['alamat']) ? trim($sp['alamat']) : (!empty($order['alamatcustomer']) ? trim($order['alamatcustomer']) : (!empty($order['alamat']) ? trim($order['alamat']) : ''));

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 4.2, 'Yth. ' . $ythTitle, 0, 1);
        if (!empty($alamat)) {
            $alamatLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $alamat));
            foreach ($alamatLines as $alLine) {
                $alLine = trim($alLine);
                if (!empty($alLine)) {
                    $pdf->Cell(0, 4.2, $alLine, 0, 1);
                }
            }
        } else {
            $pdf->Cell(0, 4.2, 'di Tempat', 0, 1);
        }
        $pdf->Ln(2.5);

        // Paragraf Pembuka
        $judulKegiatan = !empty($order['judul_kegiatan']) ? $order['judul_kegiatan'] : (!empty($sp['perihal']) ? $sp['perihal'] : 'Layanan Jasa Optimalisasi Teknologi Industri (OPTI)');
        $mediaRaw = !empty($sp['permintaan_melalui']) ? $sp['permintaan_melalui'] : (!empty($order['permintaan_melalui']) ? $order['permintaan_melalui'] : 'email');
        $mediaPhrase = self::formatMediaPermintaan($mediaRaw);
        $pdf->MultiCell(0, 4.2, 'Menanggapi permintaan Saudara ' . $mediaPhrase . ' perihal "' . $judulKegiatan . '", dengan ini kami informasikan rincian kegiatan dan penawaran biaya layanan sebagai berikut:', 0, 'J');
        $pdf->Ln(1.2);

        // Naskah Poin Rincian Penawaran (1 Input Terpadu)
        $rawNaskah = !empty($sp['ruang_lingkup']) ? trim($sp['ruang_lingkup']) : '';
        if (empty($rawNaskah)) {
            $nominal = (float)($sp['nominal_penawaran'] ?? ($order['estimasi_biaya'] ?? 0));
            $terbilangStr = strtolower(self::terbilang($nominal));
            $durasiAngka = (int)($sp['durasi_hari'] ?? (preg_match('/(\d+)/', (string)($order['spm_layanan'] ?? ''), $dm) ? (int)$dm[1] : 20));
            if ($durasiAngka <= 0) $durasiAngka = 20;

            $rawNaskah = "1. Biaya layanan adalah sebesar Rp " . number_format($nominal, 0, ',', '.') . " (" . $terbilangStr . " rupiah), dengan ruang lingkup pekerjaan sebagai berikut:\n" .
                "   1) Beating LBKP hingga 300 mL CSF\n" .
                "   2) Penyediaan stock sebanyak 2 variasi.\n" .
                "   3) Pembuatan handsheet dengan variasi penambahan aditif 2 variasi (sesuai b).\n" .
                "   4) Penampungan air dari pembuatan handsheet setiap variasi ditampung untuk dibawa ke " . $perusahaan . "\n" .
                "   5) Pengujian handsheet meliputi: massa handsheet, gramatur, uji brightness, % retensi.\n" .
                "   6) Evaluasi dan penyusunan laporan teknis.\n" .
                "2. Pelaksanaan kegiatan untuk ruang lingkup 1) sampai dengan 4) akan dilakukan pada tanggal " . $tglFormatted . ".\n" .
                "3. Total waktu pelaksanaan pekerjaan selama " . $durasiAngka . " hari kerja.\n" .
                "4. Contoh aditif yang perlu disediakan sebanyak 50 ml.";
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawNaskah));
        foreach ($lines as $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '') continue;

            if (preg_match('/^(\d+)\.\s*(.*)/', $lineTrim, $mPoint)) {
                // Poin Utama: 1., 2., 3., 4.
                $pdf->Cell(5, 4.2, $mPoint[1] . '.', 0, 0);
                $pdf->MultiCell(165, 4.2, $mPoint[2], 0, 'J');
            } elseif (preg_match('/^(\d+\)|[a-z]\)|\-|\*|\•)\s*(.*)/i', $lineTrim, $mSub)) {
                // Sub-Poin: 1), 2), a), -
                $bullet = $mSub[1];
                if (strlen($bullet) == 1 && ($bullet == '-' || $bullet == '*' || $bullet == '•')) {
                    $bullet = '-';
                }
                $pdf->SetX(25);
                $pdf->Cell(6, 3.9, $bullet, 0, 0);
                $pdf->MultiCell(159, 3.9, $mSub[2], 0, 'J');
            } else {
                // Paragraf / baris lanjutan
                $pdf->SetX(20);
                $pdf->MultiCell(170, 4.2, $lineTrim, 0, 'J');
            }
        }

        $pdf->Ln(1.5);

        // Ketentuan Tambahan (Standard 5 points BBSPJIS)
        $pdf->MultiCell(0, 4.1, 'Disamping itu perlu kami sampaikan pula ketentuan sebagai berikut :', 0, 'L');
        $ketentuan = [
            'Persetujuan terhadap biaya pengujian mohon disampaikan secara tertulis melalui email.',
            'Pengujian dilaksanakan setelah pembayaran biaya dilakukan dan sampel kami terima.',
            'Pembayaran biaya pengujian dilakukan dengan transfer melalui virtual account bank.',
            'Untuk informasi lebih lanjut dapat menghubungi Bagian Layanan nomor: 0813 8686 6808.',
            'Dilarang memberi gratifikasi dalam bentuk apapun atas layanan jasa yang kami berikan, jika terdapat pemberian dan penerimaan gratifikasi mohon dapat dilaporkan ke : http://bbs.kemenperin.go.id/kontak-kami/pengaduan-gratifikasi.'
        ];
        foreach ($ketentuan as $idx => $k) {
            $pdf->Cell(5, 3.9, ($idx + 1) . '.', 0, 0);
            $pdf->MultiCell(165, 3.9, $k, 0, 'J');
        }
        $pdf->Ln(1.5);

        // Paragraf Penutup
        $pdf->MultiCell(0, 4.1, 'Kami menunggu konfirmasi lebih lanjut. Atas perhatian dan kerja sama yang baik, kami sampaikan terima kasih.', 0, 'J');
        $pdf->Ln(4);

        // Tanda Tangan Halaman 1
        $signerRole = !empty($sp['jabatan_pejabat']) ? $sp['jabatan_pejabat'] : 'Kepala';
        $signerName = !empty($sp['pejabat_nama']) ? $sp['pejabat_nama'] : (!empty($sp['pembuat_nama']) && $sp['pembuat_nama'] !== 'Tim Mitra BBSPJIS' ? $sp['pembuat_nama'] : 'Dodiet Prasetyo');

        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, $signerRole . ',', 0, 1, 'C');
        $pdf->Ln(16);
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, $signerName, 0, 1, 'C');

        // ==========================================
        // HALAMAN 2: LAMPIRAN I (LEMBAR PERSETUJUAN)
        // ==========================================
        $pdf->AddPage();

        // Header Lampiran Kanan Atas
        $pdf->SetY(18);
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(70, 4, 'Lampiran I Surat', 0, 1, 'L');
        $pdf->SetX(120);
        $pdf->Cell(15, 4, 'Nomor', 0, 0); $pdf->Cell(3, 4, ':', 0, 0); $pdf->Cell(52, 4, $noSurat, 0, 1);
        $pdf->SetX(120);
        $pdf->Cell(15, 4, 'Tanggal', 0, 0); $pdf->Cell(3, 4, ':', 0, 0); $pdf->Cell(52, 4, $tglFormatted, 0, 1);
        $pdf->Ln(5);

        // Judul Lampiran
        $pdf->SetFont('Arial', 'B', 10.5);
        $pdf->Cell(0, 5, 'Lembar Persetujuan Surat Penawaran', 0, 1, 'C');
        $pdf->Ln(4);

        // Isi Pernyataan
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 4.3, 'Dengan ini menyatakan bahwa telah membaca memahami dan menyetujui Surat Penawaran BBSPJIS.', 0, 'J');
        $pdf->Ln(1);
        $pdf->Cell(0, 4.3, 'Selanjutnya kami menyatakan:', 0, 1);
        $pernyataan = [
            'Menyetujui ruang lingkup pekerjaan biaya layanan serta syarat dan ketentuan yang tercantum dalam surat penawaran.',
            'Bersedia memenuhi kewajiban pembayaran sesuai ketentuan yang berlaku setelah diterbitkan tagihan (invoice) oleh BBSPJIS.',
            'Persetujuan ini menjadi dasar bagi BBSPJIS untuk memproses permohonan layanan sesuai dengan ketentuan yang berlaku.',
            'Apabila terdapat perubahan ruang lingkup pekerjaan setelah persetujuan ini diberikan maka akan dilakukan penyesuaian melalui kesepakatan kedua belah pihak.'
        ];
        foreach ($pernyataan as $idx => $pText) {
            $pdf->Cell(5, 4.1, ($idx + 1) . '.', 0, 0);
            $pdf->MultiCell(165, 4.1, $pText, 0, 'J');
        }
        $pdf->Ln(1);
        $pdf->MultiCell(0, 4.3, 'Demikian Lembar Persetujuan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.', 0, 'J');
        $pdf->Ln(4);

        // Tabel Isian Konfirmasi Klien (Bordered Table)
        $picName = !empty($sp['nama']) ? trim($sp['nama']) : (!empty($order['pic']) ? trim($order['pic']) : '');
        $tblW1 = 55;
        $tblW2 = 115;
        $rowH = 5.6;

        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell($tblW1, $rowH, ' Tempat Tanggal', 1, 0, 'L');
        $pdf->Cell($tblW2, $rowH, '', 1, 1, 'L');

        $pdf->Cell($tblW1, $rowH, ' Menyetujui', 1, 0, 'L');
        $pdf->Cell($tblW2, $rowH, '', 1, 1, 'L');

        $pdf->Cell($tblW1, $rowH, ' Nama Perusahaan/Instansi', 1, 0, 'L');
        $pdf->Cell($tblW2, $rowH, ' ' . $perusahaan, 1, 1, 'L');

        $pdf->Cell($tblW1, $rowH, ' Nama Pemohon', 1, 0, 'L');
        $pdf->Cell($tblW2, $rowH, ' ' . $picName, 1, 1, 'L');

        $pdf->Cell($tblW1, $rowH, ' Jabatan', 1, 0, 'L');
        $pdf->Cell($tblW2, $rowH, '', 1, 1, 'L');

        $pdf->Cell($tblW1, $rowH, ' Nomor Telepon (WhatsApp)', 1, 0, 'L');
        $pdf->Cell($tblW2, $rowH, '', 1, 1, 'L');

        $pdf->Cell($tblW1, 26, ' Tanda Tangan dan Cap', 1, 0, 'L');
        $pdf->Cell($tblW2, 26, '', 1, 1, 'L');
        $pdf->Ln(3);

        // Catatan Bawah
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 3.8, 'Catatan:', 0, 1);
        $pdf->MultiCell(170, 3.8, 'Mohon mengembalikan lembar persetujuan ini kepada BBSPJIS melalui email atau media komunikasi yang telah ditentukan sebagai dasar proses penerbitan invoice dan pelaksanaan layanan.', 0, 'J');
        $pdf->Ln(3);

        // Tanda Tangan BBSPJIS Halaman 2
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, $signerRole . ',', 0, 1, 'C');
        $pdf->Ln(16);
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, $signerName, 0, 1, 'C');

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
        $lampTeks = !empty($sp['lampiran_teks']) ? $sp['lampiran_teks'] : '-';
        $pdf->Cell(18, 4.5, 'Lampiran', 0, 0); $pdf->Cell(4, 4.5, ':', 0, 0); $pdf->Cell(70, 4.5, $lampTeks, 0, 1);
        $halLing = !empty($sp['perihal']) ? $sp['perihal'] : (!empty($sp['hal']) ? $sp['hal'] : '-');
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
        $mediaRaw = !empty($sp['permintaan_melalui']) ? $sp['permintaan_melalui'] : (!empty($order['permintaan_melalui']) ? $order['permintaan_melalui'] : 'email');
        $mediaPhrase = self::formatMediaPermintaan($mediaRaw);
        $pdf->MultiCell(0, 4.2, 'Menanggapi permintaan Saudara ' . $mediaPhrase . ' perihal "' . $judulKegiatan . '", dengan ini kami informasikan sebagai berikut:', 0, 'J');
        $pdf->Ln(1.5);

        // Naskah Poin Rincian Penawaran Lingkungan (Parsed Dinamis)
        $rawNaskah = !empty($sp['ruang_lingkup']) ? trim($sp['ruang_lingkup']) : '';
        $nextPointNum = 1;

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawNaskah));
        foreach ($lines as $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '') continue;

            if (preg_match('/^(\d+)\.\s*(.*)/', $lineTrim, $mPoint)) {
                $pNum = (int)$mPoint[1];
                if ($pNum >= $nextPointNum) {
                    $nextPointNum = $pNum + 1;
                }
                $pdf->Cell(5, 4.2, $mPoint[1] . '.', 0, 0);
                $pdf->MultiCell(165, 4.2, $mPoint[2], 0, 'J');
            } elseif (preg_match('/^(\d+\)|[a-z]\)|\-|\*|\•)\s*(.*)/i', $lineTrim, $mSub)) {
                $bullet = $mSub[1];
                if (strlen($bullet) == 1 && ($bullet == '-' || $bullet == '*' || $bullet == '•')) {
                    $bullet = '-';
                }
                $pdf->SetX(25);
                $pdf->Cell(6, 3.9, $bullet, 0, 0);
                $pdf->MultiCell(159, 3.9, $mSub[2], 0, 'J');
            } else {
                $pdf->SetX(20);
                $pdf->MultiCell(170, 4.2, $lineTrim, 0, 'J');
            }
        }

        // Poin Ketentuan Standar Lingkungan (Auto Fill Template dengan nomor urut dinamis)
        if (stripos($rawNaskah, 'Virtual Account Mandiri') === false) {
            $pdf->Cell(5, 4.2, $nextPointNum . '.', 0, 0);
            $pdf->MultiCell(165, 4.2, 'Biaya dan rincian pekerjaan terlampir, dengan ketentuan sebagai berikut:', 0, 'J');
            $ketentuanLing = [
                'a' => 'Biaya pekerjaan ditagihkan dan dibayar melalui Virtual Account Mandiri.',
                'b' => 'Persetujuan terhadap biaya pekerjaan tersebut mohon disampaikan secara tertulis melalui fax atau e-mail.',
                'c' => 'Jadwal pelaksanaan pekerjaan akan disampaikan setelah pembayaran biaya pekerjaan dilakukan.',
                'd' => 'Dilarang memberi gratifikasi dalam bentuk apapun atas layanan jasa yang kami berikan, jika terdapat pemberian dan penerimaan gratifikasi mohon dapat dilaporkan ke : http://bbs.kemenperin.go.id/kontak-kami/pengaduan-gratifikasi.'
            ];
            foreach ($ketentuanLing as $subK => $subV) {
                $pdf->SetX(25);
                $pdf->Cell(6, 3.9, $subK . '.', 0, 0);
                $pdf->MultiCell(159, 3.9, $subV, 0, 'J');
            }
        }
        $pdf->Ln(2);

        $pdf->MultiCell(0, 4.2, 'Kami menunggu konfirmasi lebih lanjut. Atas perhatian dan kerja sama yang baik, kami sampaikan terima kasih.', 0, 'J');
        $pdf->Ln(6);

        // Tanda Tangan Cover Halaman 1 (Sesuai Referensi BBSPJIS: a.n. Kepala, Kepala Bagian Tata Usaha)
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, 'a.n. Kepala,', 0, 1, 'C');
        $pdf->SetX(120);
        $pdf->Cell(70, 4.2, 'Kepala Bagian Tata Usaha', 0, 1, 'C');
        $pdf->Ln(18);
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 4.2, $signerLing, 0, 1, 'C');

        return $pdf;
    }

    /**
     * Helper penyebutan media permintaan masuk pada naskah pembuka surat penawaran
     */
    public static function formatMediaPermintaan(?string $media): string
    {
        switch (strtolower(trim((string)$media))) {
            case 'surat':
                return 'melalui surat';
            case 'telepon':
            case 'whatsapp':
            case 'wa':
                return 'melalui telepon/WhatsApp';
            case 'datang_langsung':
                return 'secara langsung';
            case 'pegawai_bbspjis':
                return 'melalui Petugas Kemitraan';
            case 'email':
            default:
                return 'melalui e-mail';
        }
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
