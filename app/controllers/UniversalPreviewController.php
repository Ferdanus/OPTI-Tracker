<?php

class UniversalPreviewController extends Controller
{
    /**
     * Endpoint API Universal untuk Pratinjau Dokumen Bebas IDM (Zero Interception)
     * Route: GET|POST /api/doc-preview
     */
    public function getPreviewData($f3)
    {
        $this->requireAuth();

        // Ambil URL target dari GET param atau POST payload
        $rawUrl = $f3->get('GET.url');
        if (empty($rawUrl)) {
            $body = json_decode($f3->get('BODY') ?? '', true);
            $rawUrl = $body['url'] ?? $f3->get('POST.url') ?? '';
        }

        if (empty($rawUrl)) {
            $this->jsonResponse(['success' => false, 'message' => 'Parameter URL dokumen tidak diberikan.']);
            return;
        }

        // Normalisasi URL
        $parsed = parse_url($rawUrl);
        $path = urldecode($parsed['path'] ?? '');
        $query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        try {
            // 1. Pola Bukti Pembayaran: /pembayaran/bukti/@id
            if (preg_match('#pembayaran/bukti/(\d+)#i', $path, $m)) {
                $pembayaranId = (int)$m[1];
                $this->handleBuktiPembayaran($pembayaranId);
                return;
            }

            // 2a. Pola Dokumen Deal Surat Penawaran: /surat-penawaran/@id/dokumen-deal/@jenis/preview
            if (preg_match('#(?:surat-penawaran|penawaran)/(\d+)/dokumen-deal/([a-zA-Z0-9_-]+)/preview#i', $path, $m)) {
                $spId = (int)$m[1];
                $jenis = $m[2];
                $this->handleDokumenDeal($spId, $jenis);
                return;
            }

            // 2. Pola Surat Kesanggupan Bayar: /surat-penawaran/@id/surat-kesanggupan/preview ATAU /penawaran/surat-kesanggupan/@id
            if (preg_match('#(?:surat-penawaran|penawaran)/(?:(\d+)/surat-kesanggupan|surat-kesanggupan/(\d+))#i', $path, $m) || preg_match('#surat-kesanggupan/(\d+)#i', $path, $m)) {
                $spId = (int)(!empty($m[1]) ? $m[1] : (!empty($m[2]) ? $m[2] : $m[0]));
                $this->handleSuratKesanggupan($spId);
                return;
            }

            // 2b. [BARU] Pola Lampiran User Surat Penawaran:
            // /order/@id/penawaran/lampiran-user ATAU /surat-penawaran/@id/lampiran-user
            if (preg_match('#(?:surat-penawaran|penawaran|order)/(\d+)/(?:penawaran/)?lampiran-user#i', $path, $m)) {
                $targetId = (int)$m[1];
                $spId = (int)($query['id'] ?? 0);
                $this->handleLampiranUserPenawaran($targetId, $spId);
                return;
            }

            // 2c. Pola Lampiran Surat Penawaran:
            // /surat-penawaran/@id/lampiran ATAU /penawaran/@id/lampiran
            if (preg_match('#(?:surat-penawaran|penawaran)/(\d+)/lampiran#i', $path, $m)) {
                $spId = (int)$m[1];
                $this->handleLampiranPenawaran($spId);
                return;
            }

            // 3. Pola Surat Penawaran: /order/@id/penawaran/cetak ATAU /surat-penawaran/@id/(?:cetak|preview|pdf) ATAU /penawaran/@id/(?:cetak|preview|pdf)
            if (preg_match('#order/(\d+)/penawaran/cetak#i', $path, $m) || preg_match('#(?:surat-penawaran|penawaran)/(\d+)/(?:cetak|preview|pdf)#i', $path, $m)) {
                $targetId = (int)$m[1];
                $spId = (int)($query['id'] ?? 0);
                $kopParam = $query['kop'] ?? ($f3->get('GET.kop') ?? $f3->get('POST.kop'));
                $useKop = !isset($kopParam) || ($kopParam !== '0' && $kopParam !== 'tanpa_kop' && $kopParam !== 0 && $kopParam !== false);
                $this->handleSuratPenawaran($targetId, $spId, $useKop);
                return;
            }

            // 4a. Pola Berkas Proposal File (upload): /proposal/@id/file
            if (preg_match('#(?:opti-)?proposal/(\d+)/file#i', $path, $m)) {
                $proposalId = (int)$m[1];
                $this->handleProposalFile($proposalId);
                return;
            }

            // 4b. Pola Proposal Teknis: /order/@id/proposal
            if (preg_match('#order/(\d+)/proposal(?:/raw-data)?#i', $path, $m)) {
                $orderId = (int)$m[1];
                $this->handleProposal($orderId);
                return;
            }

            // 5a. Pola Surat Masuk PDF by Order: /order/@id/surat-masuk-pdf
            if (preg_match('#order/(\d+)/surat-masuk-pdf#i', $path, $m)) {
                $orderId = (int)$m[1];
                $this->handleSuratMasukByOrder($orderId);
                return;
            }

            // 5b. Pola Surat Masuk: /surat-masuk/(\d+)(?:/pdf)?
            if (preg_match('#surat-masuk/(\d+)#i', $path, $m)) {
                $suratId = (int)$m[1];
                $this->handleSuratMasuk($suratId);
                return;
            }

            // 6. Pola PO Kegiatan Cetak: /po-kegiatan/(\d+)/cetak ATAU /po/(\d+)/cetak
            if (preg_match('#(?:po-kegiatan|po)/(\d+)/cetak#i', $path, $m)) {
                $poId = (int)$m[1];
                $this->handlePoCetak($poId);
                return;
            }

            // 7. Pola BAST: /bast/(\d+)/cetak ATAU /order/(\d+)/bast
            if (preg_match('#(?:order/(\d+)/bast|bast/(\d+))#i', $path, $m)) {
                $bastOrOrderId = (int)(!empty($m[1]) ? $m[1] : $m[2]);
                $this->handleBast($bastOrOrderId);
                return;
            }

            // 8. Pola File Fisik Langsung (uploads/, storage/, dll)
            $this->handleDirectFile($path);
            return;

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses dokumen: ' . $e->getMessage()
            ]);
        }
    }

    protected function handleBuktiPembayaran($pembayaranId)
    {
        $rows = $this->db->exec('SELECT id, order_id, bukti_bayar FROM opti_pembayaran WHERE id = ?', [1 => $pembayaranId]);
        if (empty($rows) || empty($rows[0]['bukti_bayar'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Data bukti pembayaran tidak ditemukan.']);
            return;
        }

        $rawPath = $rows[0]['bukti_bayar'];
        $filePath = $this->resolveFilePath($rawPath, ['uploads/bukti_bayar', 'storage/pembayaran']);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik bukti bayar tidak ditemukan di server.']);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Bukti_Pembayaran_' . $pembayaranId);
    }

    protected function handleSuratKesanggupan($spId)
    {
        $rows = $this->db->exec(
            'SELECT id, order_id, nomor_surat, surat_kesanggupan_bayar 
             FROM tb_surat_penawaran 
             WHERE (id = ? OR order_id = ?) AND surat_kesanggupan_bayar IS NOT NULL AND surat_kesanggupan_bayar <> \'\' 
             ORDER BY id DESC LIMIT 1',
            [1 => $spId, 2 => $spId]
        );
        if (empty($rows) || empty($rows[0]['surat_kesanggupan_bayar'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Surat Kesanggupan Bayar belum diunggah untuk order/penawaran ini.']);
            return;
        }

        $rawPath = $rows[0]['surat_kesanggupan_bayar'];
        $filePath = $this->resolveFilePath($rawPath, ['storage/kesanggupan_bayar', 'uploads/kesanggupan_bayar', 'uploads/penawaran', 'public/uploads/kesanggupan_bayar']);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik Surat Kesanggupan Bayar tidak ditemukan di server: ' . basename($rawPath)]);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Surat_Kesanggupan_Bayar_' . ($rows[0]['order_id'] ?? $spId));
    }

    /**
     * Berkas Surat Penawaran yang tersimpan di server (PDF resmi).
     * Jika berkas fisik belum ada tapi data penawaran ada, fallback otomatis render PDF on-the-fly.
     */
    protected function handleLampiranPenawaran($spId)
    {
        $rows = $this->db->exec(
            'SELECT id, order_id, nomor_surat, file_lampiran 
             FROM tb_surat_penawaran 
             WHERE (id = ? OR order_id = ?) AND file_lampiran IS NOT NULL AND file_lampiran <> \'\' 
             ORDER BY (status_respon_klien = \'deal\') DESC, id DESC LIMIT 1',
            [1 => $spId, 2 => $spId]
        );

        if (empty($rows) || empty($rows[0]['file_lampiran'])) {
            // Fallback jika belum upload fisik, generate via template sistem
            $this->handleSuratPenawaran($spId);
            return;
        }

        $filePath = $this->resolveFilePath($rows[0]['file_lampiran'], ['uploads/penawaran', 'storage/penawaran', 'public/uploads/penawaran']);
        if (!$filePath || !is_file($filePath)) {
            // Fallback generate via template sistem jika berkas hilang
            $this->handleSuratPenawaran($rows[0]['id']);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Surat_Penawaran_' . ($rows[0]['nomor_surat'] ?: $spId));
    }

    /**
     * Berkas Proposal Riset Teknis yang diunggah
     */
    protected function handleProposalFile($propId)
    {
        $rows = $this->db->exec(
            'SELECT id, order_id, judul_proposal, file_proposal 
             FROM opti_proposal_riset 
             WHERE (id = ? OR order_id = ?) AND file_proposal IS NOT NULL AND file_proposal <> \'\' 
             ORDER BY id DESC LIMIT 1',
            [1 => $propId, 2 => $propId]
        );

        if (empty($rows) || empty($rows[0]['file_proposal'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas proposal riset teknis tidak ditemukan.']);
            return;
        }

        $filePath = $this->resolveFilePath($rows[0]['file_proposal'], ['public/uploads/proposals', 'uploads/proposals', 'uploads/proposal', 'storage/proposal']);
        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik proposal tidak ditemukan di server: ' . basename($rows[0]['file_proposal'])]);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Proposal_Riset_' . ($rows[0]['order_id'] ?? $propId));
    }

    /**
     * Berkas Lampiran Tambahan yang Diunggah User pada Surat Penawaran (file_lampiran_user)
     */
    protected function handleLampiranUserPenawaran($targetId, $spIdExplicit = 0)
    {
        $spModel = new SuratPenawaran($this->db);
        $sp = null;
        if ($spIdExplicit > 0) {
            $sp = $spModel->getById($spIdExplicit);
        }
        if (!$sp) {
            $sp = $spModel->getByOrderId($targetId);
        }
        if (!$sp) {
            $sp = $spModel->getById($targetId);
        }

        $relPath = '';
        if ($sp && !empty($sp['file_lampiran_user'])) {
            $relPath = $sp['file_lampiran_user'];
        } elseif (!empty($_SESSION['pending_lampiran_sp_' . $targetId])) {
            $relPath = $_SESSION['pending_lampiran_sp_' . $targetId];
        }

        if (empty($relPath)) {
            $orderIdActual = (int)($sp['order_id'] ?? $targetId);
            $propRows = $this->db->exec('SELECT file_proposal FROM opti_proposal_riset WHERE order_id = ? AND file_proposal IS NOT NULL AND file_proposal <> \'\' ORDER BY id DESC LIMIT 1', [1 => $orderIdActual]);
            if (!empty($propRows[0]['file_proposal'])) {
                $relPath = trim((string)$propRows[0]['file_proposal']);
            }
        }

        if (empty($relPath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas lampiran surat penawaran belum diunggah.']);
            return;
        }

        $filePath = $this->resolveFilePath($relPath, ['uploads/penawaran/lampiran', 'uploads/penawaran', 'storage/penawaran', 'public/uploads/proposals']);
        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik lampiran tidak ditemukan di server: ' . basename($relPath)]);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Lampiran_Penawaran_' . ($sp['nomor_surat'] ?? $targetId));
    }

    /**
     * Menampilkan Surat Masuk PDF berdasarkan ID Order
     */
    protected function handleSuratMasukByOrder($orderId)
    {
        $order = $this->db->exec('SELECT id, id_surat_masuk FROM order_layanan WHERE id = ? LIMIT 1', [1 => $orderId]);
        if (empty($order) || empty($order[0]['id_surat_masuk'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Surat masuk permohonan tidak terhubung dengan order ini.']);
            return;
        }
        $this->handleSuratMasuk((int)$order[0]['id_surat_masuk']);
    }

    protected function handleSuratPenawaran($orderOrSpId, $spIdExplicit = 0, $useKop = true)
    {
        $spController = new SuratPenawaranController();
        $f3 = \Base::instance();
        
        $spModel = new SuratPenawaran($this->db);
        $orderModel = new OrderLayanan($this->db);

        $order = $orderModel->getDetail($orderOrSpId);
        $sp = null;

        if ($order) {
            if ($spIdExplicit > 0) {
                $sp = $spModel->getById($spIdExplicit);
            }
            if (!$sp) {
                $sp = $spModel->getByOrderId($orderOrSpId);
            }
        } else {
            $sp = $spModel->getById($orderOrSpId);
            if ($sp && !empty($sp['order_id'])) {
                $order = $orderModel->getDetail((int)$sp['order_id']);
            }
        }

        $orderIdActual = (int)($order['id'] ?? ($sp['order_id'] ?? $orderOrSpId));
        if (!$sp && $order) {
            $sp = [
                'id'                  => 0,
                'order_id'            => (int)$order['id'],
                'nomor_surat'         => $spModel->generateNomorSurat(($order['jenis_layanan_opti'] ?? 'selulosa') === 'selulosa' ? 'SL' : 'LK'),
                'perusahaan'          => $order['nama_perusahaan'] ?? 'Mitra Balai',
                'nama'                => $order['pic'] ?? '-',
                'alamat'              => $order['alamat'] ?? '-',
                'jenis_layanan'       => $order['jenis_layanan_opti'] ?? 'selulosa',
                'perihal'             => $order['judul_kegiatan'] ?? 'Penawaran Layanan Jasa OPTI',
                'nominal_penawaran'   => (float)($order['estimasi_biaya'] ?? 0),
                'tanggal_surat'       => date('Y-m-d'),
                'status_respon_klien' => 'draft',
                'file_lampiran_user'  => $_SESSION['pending_lampiran_sp_' . $orderIdActual] ?? ''
            ];
        }

        if ($sp && empty($sp['file_lampiran_user']) && !empty($_SESSION['pending_lampiran_sp_' . $orderIdActual])) {
            $sp['file_lampiran_user'] = $_SESSION['pending_lampiran_sp_' . $orderIdActual];
        }

        if ($sp && ($order['jenis_layanan_opti'] ?? '') === 'selulosa' && empty($_SESSION['pending_lampiran_sp_' . $orderIdActual])) {
            $propRows = $this->db->exec('SELECT file_proposal FROM opti_proposal_riset WHERE order_id = ? AND file_proposal IS NOT NULL AND file_proposal <> \'\' ORDER BY id DESC LIMIT 1', [1 => $orderIdActual]);
            if (!empty($propRows[0]['file_proposal'])) {
                $sp['file_lampiran_user'] = trim((string)$propRows[0]['file_proposal']);
            }
        }

        if (!$sp) {
            $this->jsonResponse(['success' => false, 'message' => 'Dokumen surat penawaran tidak ditemukan.']);
            return;
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

        $pdf = $spController->generatePdfObject($order, $sp, $useKop);
        $pdfContent = $pdf->Output('S');
        $base64 = base64_encode($pdfContent);
        $suffix = $useKop ? '' : '_Tanpa_Kop';
        $filename = 'Surat_Penawaran_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $sp['nomor_surat'] ?: 'BBSPJIS') . $suffix . '.pdf';

        $respData = [
            'success'     => true,
            'is_pdf'      => true,
            'is_image'    => false,
            'is_penawaran'=> true,
            'filename'    => $filename,
            'mime'        => 'application/pdf',
            'nomor_surat' => $sp['nomor_surat'] ?? '',
            'order_id'    => $orderIdActual,
            'sp_id'       => (int)($sp['id'] ?? 0),
            'word_url'    => '/order/' . $orderIdActual . '/penawaran/word' . (!empty($sp['id']) ? ('?id=' . $sp['id']) : ''),
            'base64'      => $base64
        ];

        // Fallback jika file lampiran PDF menggunakan kompresi PDF 1.5+ yang perlu digabung di browser (pdf-lib)
        if (!$pdf->lampiranMerged && !empty($pdf->lampiranPath) && is_file($pdf->lampiranPath)) {
            $respData['attachment_base64'] = base64_encode(file_get_contents($pdf->lampiranPath));
            $respData['attachment_ext'] = strtolower(pathinfo($pdf->lampiranPath, PATHINFO_EXTENSION));
        }

        $this->jsonResponse($respData);
    }

    protected function handleProposal($orderId)
    {
        $orderModel = new OrderLayanan($this->db);
        $proposal = $orderModel->getProposalRiset($orderId);
        if (empty($proposal) || empty($proposal['file_proposal'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas proposal teknis belum diunggah untuk order ini.']);
            return;
        }

        $filePath = $this->resolveFilePath($proposal['file_proposal'], ['uploads/proposal', 'storage/proposal']);
        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik proposal tidak ditemukan di server.']);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Proposal_Teknis_Order_' . $orderId);
    }

    protected function handleBast($orderOrBastId)
    {
        $bastModel = new OptiBast($this->db);
        $bast = $bastModel->getByOrderId($orderOrBastId);
        if (!$bast) {
            $bast = $bastModel->getById($orderOrBastId);
        }
        if (empty($bast) || empty($bast['file_dokumen_bast'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik dokumen BAST belum diunggah.']);
            return;
        }

        $filePath = $this->resolveFilePath($bast['file_dokumen_bast'], ['uploads/bast', 'storage/bast']);
        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik dokumen BAST tidak ditemukan di server.']);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Dokumen_BAST_' . ($bast['nomor_bast'] ?? $orderOrBastId));
    }

    protected function handleSuratMasuk($suratId)
    {
        $repo = new \SuratMasukRepository($this->db, $this->dbSekretariat);
        $surat = $repo->getSuratById($suratId);
        if (empty($surat)) {
            $this->jsonResponse(['success' => false, 'message' => 'Data surat permohonan masuk tidak ditemukan.']);
            return;
        }

        $filePath = $this->resolveFilePath($surat['file_path'] ?? '', ['uploads/surat_masuk', 'storage/surat_masuk']);

        if (!empty($filePath) && is_file($filePath) && filesize($filePath) > 1000) {
            $this->sendFileAsBase64($filePath, 'Surat_Masuk_' . $suratId);
            return;
        }

        // Generate clean structured official letter
        require_once dirname(__DIR__, 2) . '/app/helpers/fpdf/fpdf.php';
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(20, 15, 20);
        $pdf->AddPage();
        
        // 1. KOP SURAT
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetTextColor(0, 0, 0);
        $namaPerusahaan = strtoupper(\Customer::formatNamaPerusahaan($surat['pt_cv'] ?? '', $surat['pengirim'] ?? 'PENGIRIM'));
        $pdf->Cell(0, 6, $namaPerusahaan, 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 4.5, 'PRODUSEN & JASA INDUSTRI TEKNOLOGI', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->Cell(0, 4, ($surat['alamat_pengirim'] ?: 'Kawasan Industri Terpadu, Indonesia') . ' | Telp: ' . ($surat['no_telp_pengirim'] ?: '-'), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->SetLineWidth(0.2);
        $pdf->Line(20, $pdf->GetY() + 0.8, 190, $pdf->GetY() + 0.8);
        $pdf->Ln(5);

        // 2. METADATA
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(20, 5, 'Nomor', 0, 0);
        $pdf->Cell(4, 5, ':', 0, 0);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(80, 5, $surat['nomor_surat'] ?? '-', 0, 0);
        $pdf->SetFont('Arial', '', 9.5);
        $tglSurat = !empty($surat['tanggal_surat']) ? date('d F Y', strtotime($surat['tanggal_surat'])) : date('d F Y');
        $pdf->Cell(0, 5, 'Bandung, ' . $tglSurat, 0, 1, 'R');

        $pdf->Cell(20, 5, 'Lampiran', 0, 0);
        $pdf->Cell(4, 5, ':', 0, 0);
        $pdf->Cell(0, 5, '1 (satu) berkas spesifikasi teknis', 0, 1);

        $pdf->Cell(20, 5, 'Hal', 0, 0);
        $pdf->Cell(4, 5, ':', 0, 0);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->MultiCell(0, 5, $surat['perihal'] ?? 'Permohonan Layanan Pengujian dan Kerjasama', 0, 'L');
        $pdf->Ln(4);

        // 3. TUJUAN
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(0, 4.5, 'Kepada Yth.', 0, 1);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 4.5, 'Kepala Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa (BBSPJIS)', 0, 1);
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(0, 4.5, 'Kementerian Perindustrian Republik Indonesia', 0, 1);
        $pdf->Cell(0, 4.5, 'Jl. Raya Dayeuhkolot No. 132, Bandung, Jawa Barat 40258', 0, 1);
        $pdf->Ln(4);

        // 4. PEMBUKA
        $pdf->Cell(0, 5, 'Dengan hormat,', 0, 1);
        $pdf->MultiCell(0, 5, "Sehubungan dengan rencana pengujian mutu dan optimalisasi proses industri, bersama ini kami mengajukan permohonan kerjasama pelaksanaan Layanan Optimalisasi Teknologi Industri (OPTI) dengan rincian sebagai berikut:", 0, 'J');
        $pdf->Ln(3);

        // 5. DATA BERSTRUKTUR
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 5, '1. Data Pemohon / Instansi:', 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(8, 4.8, '', 0, 0);
        $pdf->Cell(38, 4.8, 'a. Nama Perusahaan', 0, 0);
        $pdf->Cell(4, 4.8, ':', 0, 0);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 4.8, $namaPerusahaan, 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(8, 4.8, '', 0, 0);
        $pdf->Cell(38, 4.8, 'b. Alamat', 0, 0);
        $pdf->Cell(4, 4.8, ':', 0, 0);
        $pdf->MultiCell(0, 4.8, $surat['alamat_pengirim'] ?: 'Kawasan Industri Terpadu, Indonesia', 0, 'L');
        $pdf->Cell(8, 4.8, '', 0, 0);
        $pdf->Cell(38, 4.8, 'c. Narahubung / PIC', 0, 0);
        $pdf->Cell(4, 4.8, ':', 0, 0);
        $pdf->Cell(0, 4.8, $surat['pic_pengirim'] ?: 'Penanggung Jawab Teknis', 0, 1);
        $pdf->Cell(8, 4.8, '', 0, 0);
        $pdf->Cell(38, 4.8, 'd. Telepon & Email', 0, 0);
        $pdf->Cell(4, 4.8, ':', 0, 0);
        $pdf->Cell(0, 4.8, ($surat['no_telp_pengirim'] ?: '-') . ' / ' . ($surat['email_pengirim'] ?: '-'), 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 5, '2. Rincian Kebutuhan Layanan OPTI:', 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(8, 4.8, '', 0, 0);
        $pdf->Cell(38, 4.8, 'a. Perihal Kegiatan', 0, 0);
        $pdf->Cell(4, 4.8, ':', 0, 0);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->MultiCell(0, 4.8, $surat['perihal'] ?? '-', 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(8, 4.8, '', 0, 0);
        $pdf->Cell(38, 4.8, 'b. Layanan', 0, 0);
        $pdf->Cell(4, 4.8, ':', 0, 0);
        $namaLayananLabel = !empty($surat['nama_layanan_label']) ? $surat['nama_layanan_label'] : 'Layanan Optimalisasi Teknologi Industri (OPTI)';
        $pdf->Cell(0, 4.8, $namaLayananLabel, 0, 1);
        $pdf->Cell(8, 4.8, '', 0, 0);
        $pdf->Cell(38, 4.8, 'c. Ruang Lingkup', 0, 0);
        $pdf->Cell(4, 4.8, ':', 0, 0);
        $pdf->MultiCell(0, 4.8, 'Kajian Teknis, Karakterisasi Laboratorium & Penerbitan Sertifikat / Laporan Hasil Pengujian Resmi Balai', 0, 'L');
        $pdf->Ln(3);

        // 6. PENUTUP
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->MultiCell(0, 4.8, "Demikian surat permohonan ini kami sampaikan. Kami berharap dapat segera menerima Tinjauan Kelayakan Permintaan serta Surat Penawaran Biaya resmi dari BBSPJIS. Atas perhatian dan kerjasama Bapak/Ibu, kami ucapkan terima kasih.", 0, 'J');
        $pdf->Ln(5);

        // 7. TTD
        $pdf->SetX(110);
        $pdf->Cell(80, 4.5, 'Hormat kami,', 0, 1, 'C');
        $pdf->SetX(110);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(80, 4.5, $namaPerusahaan, 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetX(125);
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(50, 4, '[ TTD & STEMPEL RESMI ]', 1, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(8);
        $pdf->SetX(110);
        $pdf->SetFont('Arial', 'BU', 9.5);
        $pdf->Cell(80, 4.5, $surat['pic_pengirim'] ?: 'Pimpinan Perusahaan', 0, 1, 'C');
        $pdf->SetX(110);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(80, 4, 'Direktur', 0, 1, 'C');

        $pdfContent = $pdf->Output('S');
        $base64 = base64_encode($pdfContent);

        $this->jsonResponse([
            'success'  => true,
            'is_pdf'   => true,
            'is_image' => false,
            'filename' => 'Surat_Masuk_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $surat['nomor_surat'] ?: $suratId) . '.pdf',
            'mime'     => 'application/pdf',
            'base64'   => $base64
        ]);
    }

    protected function handlePoCetak($poId)
    {
        $poModel = new Po($this->db);
        $po = $poModel->getById($poId);
        if (!$po) {
            $this->jsonResponse(['success' => false, 'message' => 'Data PO Kegiatan tidak ditemukan.']);
            return;
        }

        $f3 = \Base::instance();
        $orderModel = new OrderLayanan($this->db);
        $orderData = !empty($po['order_id']) ? $orderModel->getDetail((int)$po['order_id']) : null;

        $f3->set('order', $orderData ?: null);
        $f3->set('po', $po);
        $html = \Template::instance()->render('katim_kerja/po-kegiatan/cetak_pdf.html');

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Times-Roman');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        $base64 = base64_encode($pdfContent);
        $filename = 'PO-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $po['nomor_po'] ?? $poId) . '.pdf';

        $this->jsonResponse([
            'success'  => true,
            'is_pdf'   => true,
            'is_image' => false,
            'filename' => $filename,
            'mime'     => 'application/pdf',
            'base64'   => $base64
        ]);
    }

    protected function handleDirectFile($rawPath)
    {
        $filePath = $this->resolveFilePath($rawPath, ['uploads', 'storage', 'uploads/bukti_bayar', 'uploads/kesanggupan_bayar', 'uploads/penawaran', 'uploads/bast', 'uploads/proposal']);
        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas tidak ditemukan: ' . basename($rawPath)]);
            return;
        }

        $this->sendFileAsBase64($filePath, pathinfo($filePath, PATHINFO_FILENAME));
    }

    protected function resolveFilePath($rawPath, $fallbackDirs = [])
    {
        if (empty($rawPath)) return null;

        $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $root = rtrim(str_replace('\\', '/', $this->f3->get('ROOT') ?: $appRoot), '/');
        $clean = ltrim(urldecode(str_replace('\\', '/', $rawPath)), '/');

        // Bersihkan prefix URL folder seperti /Mini OPTI Tracker/ atau /Mini%20OPTI%20Tracker/
        $clean = preg_replace('#^(?:Mini[\s_]OPTI[\s_]Tracker/|public/|htdocs/)+#i', '', $clean);
        $clean = ltrim($clean, '/');
        $basename = basename($clean);

        $candidates = [
            $appRoot . '/' . $clean,
            $appRoot . '/public/' . $clean,
            $appRoot . '/' . ltrim($rawPath, '/'),
            $root . '/' . $clean,
            $root . '/public/' . $clean,
            $root . '/' . ltrim($rawPath, '/'),
            $rawPath,
        ];

        foreach ($fallbackDirs as $dir) {
            $cleanDir = trim($dir, '/');
            $candidates[] = $appRoot . '/' . $cleanDir . '/' . $basename;
            $candidates[] = $appRoot . '/public/' . $cleanDir . '/' . $basename;
            $candidates[] = $root . '/' . $cleanDir . '/' . $basename;
            $candidates[] = $root . '/public/' . $cleanDir . '/' . $basename;
        }

        foreach ($candidates as $c) {
            if (!empty($c) && is_file($c)) {
                return realpath($c);
            }
        }

        return null;
    }

    protected function sendFileAsBase64($filePath, $defaultName = 'Dokumen')
    {
        $filename = basename($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $isPdf = ($ext === 'pdf');
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);

        $mimeMap = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->jsonResponse(['success' => false, 'message' => 'Gagal membaca isi berkas dari server.']);
            return;
        }

        $base64 = base64_encode($content);

        $this->jsonResponse([
            'success'  => true,
            'is_pdf'   => $isPdf,
            'is_image' => $isImage,
            'filename' => $filename ?: ($defaultName . '.' . $ext),
            'ext'      => $ext,
            'mime'     => $mimeMap[$ext] ?? 'application/octet-stream',
            'size'     => filesize($filePath),
            'base64'   => $base64
        ]);
    }

    protected function jsonResponse($data)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        echo json_encode($data);
        exit;
    }
}
