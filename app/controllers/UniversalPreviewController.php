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

            // 2. Pola Surat Kesanggupan Bayar: /surat-penawaran/@id/surat-kesanggupan/preview
            if (preg_match('#surat-penawaran/(\d+)/surat-kesanggupan/preview#i', $path, $m) || preg_match('#surat-kesanggupan/(\d+)#i', $path, $m)) {
                $spId = (int)$m[1];
                $this->handleSuratKesanggupan($spId);
                return;
            }

            // 2b. [BARU] Pola Lampiran Surat Penawaran (berkas PDF resmi yang sudah
            // tersimpan di uploads/penawaran/, kolom tb_surat_penawaran.file_lampiran):
            // /surat-penawaran/@id/lampiran
            if (preg_match('#surat-penawaran/(\d+)/lampiran#i', $path, $m)) {
                $spId = (int)$m[1];
                $this->handleLampiranPenawaran($spId);
                return;
            }

            // 3. Pola Surat Penawaran: /order/@id/penawaran/cetak ATAU /surat-penawaran/@id/cetak
            if (preg_match('#order/(\d+)/penawaran/cetak#i', $path, $m) || preg_match('#surat-penawaran/(\d+)/(?:cetak|preview)#i', $path, $m)) {
                $targetId = (int)$m[1];
                $spId = (int)($query['id'] ?? 0);
                $this->handleSuratPenawaran($targetId, $spId);
                return;
            }

            // 4. Pola Proposal Teknis: /order/@id/proposal
            if (preg_match('#order/(\d+)/proposal(?:/raw-data)?#i', $path, $m)) {
                $orderId = (int)$m[1];
                $this->handleProposal($orderId);
                return;
            }

            // 5. Pola Surat Masuk: /surat-masuk/(\d+)
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
        $rows = $this->db->exec('SELECT id, nomor_surat, surat_kesanggupan_bayar FROM tb_surat_penawaran WHERE id = ?', [1 => $spId]);
        if (empty($rows) || empty($rows[0]['surat_kesanggupan_bayar'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Surat Kesanggupan Bayar belum diunggah untuk penawaran ini.']);
            return;
        }

        $rawPath = $rows[0]['surat_kesanggupan_bayar'];
        $filePath = $this->resolveFilePath($rawPath, ['storage/kesanggupan_bayar', 'uploads/kesanggupan_bayar']);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik Surat Kesanggupan Bayar tidak ditemukan di server.']);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Surat_Kesanggupan_Bayar_' . $spId);
    }

    /**
     * [BARU] Berkas Surat Penawaran yang beneran tersimpan di server (PDF resmi
     * hasil "Kirim"/"Simpan" terakhir, path-nya ada di tb_surat_penawaran.file_lampiran)
     * -- beda sama handleSuratPenawaran() di bawah yang nge-generate ulang PDF-nya
     * on-the-fly dari data terkini, bukan ngambil berkas yang beneran tersimpan.
     */
    protected function handleLampiranPenawaran($spId)
    {
        $rows = $this->db->exec('SELECT id, nomor_surat, file_lampiran FROM tb_surat_penawaran WHERE id = ?', [1 => $spId]);
        if (empty($rows) || empty($rows[0]['file_lampiran'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas Surat Penawaran belum diunggah/disimpan untuk penawaran ini.']);
            return;
        }

        $filePath = $this->resolveFilePath($rows[0]['file_lampiran'], ['uploads/penawaran', 'storage/penawaran']);
        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik Surat Penawaran tidak ditemukan di server.']);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Surat_Penawaran_' . ($rows[0]['nomor_surat'] ?: $spId));
    }

    protected function handleSuratPenawaran($orderOrSpId, $spIdExplicit = 0)
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

        $pdf = $spController->generatePdfObject($order, $sp);
        $pdfContent = $pdf->Output('S');
        $base64 = base64_encode($pdfContent);
        $filename = 'Surat_Penawaran_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $sp['nomor_surat'] ?: 'BBSPJIS') . '.pdf';

        $this->jsonResponse([
            'success'     => true,
            'is_pdf'      => true,
            'is_image'    => false,
            'filename'    => $filename,
            'mime'        => 'application/pdf',
            'nomor_surat' => $sp['nomor_surat'] ?? '',
            'base64'      => $base64
        ]);
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
        // [FIX] Sebelumnya query manual ke tabel "surat_masuk" di database utama -- salah dua-duanya:
        // (1) tabelnya ada di database SEKRETARIAT (sil2020), bukan database utama, dan (2) nama
        // tabel/kolomnya sendiri udah beda (tb_arsipsurat, id_arsip, nama_berkas, dst), bukan lagi
        // surat_masuk/id/file_path yang lama. Dipake ulang SuratMasukRepository::getSuratById() yang
        // udah nangani kompatibilitas tabel/kolom itu (termasuk join data pengirim dari tb_customer),
        // biar gak dobel logic & otomatis ikut kalau nanti skema sekretariatnya berubah lagi.
        $repo = new \SuratMasukRepository($this->db, $this->dbSekretariat);
        $surat = $repo->getSuratById($suratId);
        if (empty($surat)) {
            $this->jsonResponse(['success' => false, 'message' => 'Data surat permohonan masuk tidak ditemukan.']);
            return;
        }

        // [FIX] Berkas surat masuk yang bener itu di uploads/surat_masuk (root project), BUKAN
        // public/uploads/surat_masuk -- sempat salah nambahin public/ sebagai kandidat duluan,
        // jadinya kepilih berkas yang salah/nyasar dari folder public. Balikin ke uploads/surat_masuk
        // sebagai yang dicoba duluan.
        $filePath = $this->resolveFilePath($surat['file_path'] ?? '', ['uploads/surat_masuk', 'storage/surat_masuk']);

        if (!empty($filePath) && is_file($filePath)) {
            $this->sendFileAsBase64($filePath, 'Surat_Masuk_' . $suratId);
            return;
        }

        // Jika tidak ada file fisik asli, buatkan lembar PDF surat masuk resmi dari data database
        require_once $this->f3->get('ROOT') . '/app/helpers/fpdf/fpdf.php';
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(20, 15, 20);
        $pdf->AddPage();
        
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 7, strtoupper(\Customer::formatNamaPerusahaan($surat['pt_cv'] ?? '', $surat['pengirim'] ?? 'PENGIRIM')), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, ($surat['alamat_pengirim'] ?: 'Kawasan Industri Terpadu, Indonesia') . ' | Telp: ' . ($surat['no_telp_pengirim'] ?: '-'), 0, 1, 'C');
        $pdf->Line(20, 30, 190, 30);
        $pdf->Ln(8);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(30, 6, 'Nomor Surat', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, $surat['nomor_surat'] ?? '-', 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(30, 6, 'Tanggal', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, !empty($surat['tanggal_surat']) ? date('d F Y', strtotime($surat['tanggal_surat'])) : date('d F Y'), 0, 1);

        $pdf->Cell(30, 6, 'Perihal', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->MultiCell(0, 6, $surat['perihal'] ?? 'Permohonan Layanan Pengujian dan Kerjasama');

        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 10);
        $isiSurat = $surat['isi_ringkas'] ?: 'Dengan hormat, bersama surat ini kami mengajukan permohonan pengujian sampel sesuai spesifikasi terlampir kepada Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa (BBSPJIS).';
        $pdf->MultiCell(0, 6, $isiSurat);

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

        $root = rtrim(str_replace('\\', '/', $this->f3->get('ROOT') ?: dirname(__DIR__, 2)), '/');
        $clean = ltrim(urldecode(str_replace('\\', '/', $rawPath)), '/');

        // Bersihkan prefix URL folder seperti /Mini OPTI Tracker/ atau /Mini%20OPTI%20Tracker/
        $clean = preg_replace('#^(?:Mini[\s_]OPTI[\s_]Tracker/|public/|htdocs/)+#i', '', $clean);
        $clean = ltrim($clean, '/');
        $basename = basename($clean);

        $candidates = [
            $root . '/' . $clean,
            $root . '/' . ltrim($rawPath, '/'),
            dirname(__DIR__, 2) . '/' . $clean,
            $rawPath,
        ];

        foreach ($fallbackDirs as $dir) {
            $cleanDir = trim($dir, '/');
            $candidates[] = $root . '/' . $cleanDir . '/' . $basename;
            $candidates[] = dirname(__DIR__, 2) . '/' . $cleanDir . '/' . $basename;
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
