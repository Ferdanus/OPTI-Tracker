<?php

require_once __DIR__ . '/Controller.php';

/**
 * UniversalPreviewController
 *
 * Controller terpusat untuk API Pratinjau Dokumen Bebas Intersepsi Download Manager (IDM).
 * Endpoint: GET /api/doc-preview?url={encoded_doc_url}
 *
 * Mengembalikan JSON:
 * {
 *   "success": true,
 *   "is_pdf": true,
 *   "base64": "...",
 *   "filename": "...",
 *   "mime": "application/pdf"
 * }
 */
class UniversalPreviewController extends Controller
{
    /**
     * Endpoint utama untuk fetch base64 data PDF/Gambar/Dokumen Word
     */
    public function getPreviewData($f3)
    {
        $rawUrl = trim($f3->get('GET.url') ?: '');
        if (empty($rawUrl)) {
            $this->jsonResponse(['success' => false, 'message' => 'Parameter URL dokumen wajib diisi.']);
            return;
        }

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

            // 3. Pola Proposal Teknis: /order/@id/proposal/preview ATAU /proposal/preview/@id
            if (preg_match('#(?:order/(\d+)/proposal(?:/preview)?|proposal/preview/(\d+))#i', $path, $m)) {
                $orderId = (int)(!empty($m[1]) ? $m[1] : $m[2]);
                $this->handleProposal($orderId);
                return;
            }

            // 3b. [BARU] Pola Berkas Proposal Teknis Asli (PDF): /order/@id/proposal/file
            if (preg_match('#order/(\d+)/proposal/file#i', $path, $m)) {
                $orderId = (int)$m[1];
                $this->handleProposalFile($orderId);
                return;
            }

            // 4. Pola Surat Penawaran: /order/@id/penawaran/cetak ATAU /surat-penawaran/@id/cetak
            if (preg_match('#(?:order/(\d+)/penawaran/cetak|surat-penawaran/(\d+)/cetak|penawaran/cetak/(\d+))#i', $path, $m)) {
                $orderOrSpId = (int)(!empty($m[1]) ? $m[1] : (!empty($m[2]) ? $m[2] : $m[3]));
                $spIdExplicit = (int)($query['id'] ?? 0);
                $useKop = !isset($query['kop']) || ($query['kop'] !== '0' && $query['kop'] !== 'tanpa_kop');
                $this->handleSuratPenawaran($orderOrSpId, $spIdExplicit, $useKop, $query);
                return;
            }

            // 5. Pola BAST: /order/@id/bast/cetak ATAU /bast/@id/cetak
            if (preg_match('#(?:order/(\d+)/bast/cetak|bast/(\d+)/cetak)#i', $path, $m)) {
                $orderOrBastId = (int)(!empty($m[1]) ? $m[1] : $m[2]);
                $this->handleBast($orderOrBastId);
                return;
            }

            // 6. Pola Berkas Surat Masuk: /surat-masuk/@id/pdf ATAU /order/@id/surat-masuk/pdf
            if (preg_match('#order/(\d+)/surat-masuk/pdf#i', $path, $m)) {
                $orderId = (int)$m[1];
                $this->handleSuratMasukByOrder($orderId);
                return;
            }
            if (preg_match('#surat-masuk/(\d+)/pdf#i', $path, $m)) {
                $suratId = (int)$m[1];
                $this->handleSuratMasuk($suratId);
                return;
            }

            // 7. Pola PO Kegiatan Cetak: /po-kegiatan/cetak/@id ATAU /po-kegiatan/@id/cetak
            if (preg_match('#po-kegiatan/(?:cetak/(\d+)|(\d+)/cetak)#i', $path, $m)) {
                $poId = (int)(!empty($m[1]) ? $m[1] : $m[2]);
                $this->handlePoCetak($poId);
                return;
            }

            // 8. Pola Direct File (misal file proposal, bukti bayar langsung dari URL fisik):
            // /uploads/... atau /storage/... atau .pdf/.jpg
            if (preg_match('#\.(pdf|jpg|jpeg|png|webp|bmp|doc|docx)$#i', $path)) {
                $this->handleDirectFile($path);
                return;
            }

            // Jika pola tidak spesifik, fallback cek apakah URL itu sendiri adalah path file yang tersimpan
            $resolved = $this->resolveFilePath($path);
            if ($resolved && is_file($resolved)) {
                $this->sendFileAsBase64($resolved);
                return;
            }

            $this->jsonResponse(['success' => false, 'message' => 'Pola dokumen tidak dikenali oleh sistem pratinjau.']);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
        }
    }

    /**
     * Berkas Bukti Pembayaran (PDF atau Gambar)
     */
    protected function handleBuktiPembayaran($pembayaranId)
    {
        $rows = $this->db->exec('SELECT file_bukti FROM tb_pembayaran WHERE id = ?', [1 => $pembayaranId]);
        if (empty($rows) || empty($rows[0]['file_bukti'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas bukti pembayaran tidak ditemukan di database.']);
            return;
        }

        $rawPath = $rows[0]['file_bukti'];
        $filePath = $this->resolveFilePath($rawPath, ['uploads/bukti_bayar', 'storage/pembayaran']);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik bukti bayar tidak ditemukan di server.']);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Bukti_Pembayaran_' . $pembayaranId);
    }

    /**
     * Berkas 3 Dokumen DEAL Penawaran (Surat Penawaran TTD, Bukti Persetujuan Klien, Surat Kesanggupan).
     */
    protected function handleDokumenDeal($spId, $jenis)
    {
        $colMap = [
            'penawaran_ttd'     => 'file_penawaran_ttd',
            'bukti_persetujuan' => 'file_bukti_persetujuan',
            'surat_kesanggupan' => 'surat_kesanggupan_bayar',
        ];
        $labelMap = [
            'penawaran_ttd'     => 'Surat Penawaran Bertandatangan',
            'bukti_persetujuan' => 'Bukti Persetujuan Klien',
            'surat_kesanggupan' => 'Surat Kesanggupan',
        ];

        if (!isset($colMap[$jenis])) {
            $this->jsonResponse(['success' => false, 'message' => 'Jenis dokumen deal tidak valid: ' . $jenis]);
            return;
        }

        $col = $colMap[$jenis];
        $label = $labelMap[$jenis];

        $rows = $this->db->exec(
            "SELECT id, order_id, nomor_surat, {$col} 
             FROM tb_surat_penawaran 
             WHERE (id = ? OR order_id = ?) AND {$col} IS NOT NULL AND {$col} <> '' 
             ORDER BY id DESC LIMIT 1",
            [1 => $spId, 2 => $spId]
        );

        if (empty($rows) || empty($rows[0][$col])) {
            $this->jsonResponse(['success' => false, 'message' => "Berkas {$label} belum diunggah untuk order/penawaran ini."]);
            return;
        }

        $rawPath = $rows[0][$col];
        $filePath = $this->resolveFilePath($rawPath, [
            'storage/kesanggupan_bayar',
            'uploads/penawaran',
            'uploads/kesanggupan_bayar',
            'public/uploads/kesanggupan_bayar',
            'public/uploads/penawaran'
        ]);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => "Berkas fisik {$label} tidak ditemukan di server: " . basename($rawPath)]);
            return;
        }

        $docTitle = str_replace(' ', '_', $label) . '_' . ($rows[0]['order_id'] ?? $spId);
        $wordUrl = '/surat-penawaran/' . ($rows[0]['id'] ?? $spId) . '/dokumen-deal/' . $jenis . '/word';
        $this->sendFileAsBase64($filePath, $docTitle, $wordUrl);
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

        $docTitle = 'Surat_Kesanggupan_Bayar_' . ($rows[0]['order_id'] ?? $spId);
        $wordUrl = '/surat-penawaran/' . ($rows[0]['id'] ?? $spId) . '/dokumen-deal/surat_kesanggupan/word';
        $this->sendFileAsBase64($filePath, $docTitle, $wordUrl);
    }

    /**
     * Berkas Surat Penawaran yang tersimpan di server (PDF resmi).
     * Selalu render format PDF terbaru secara konsisten dari satu sumber generator resmi.
     */
    protected function handleLampiranPenawaran($spId)
    {
        $this->handleSuratPenawaran($spId, $spId, true);
    }

    /**
     * Berkas Asli Proposal Teknis (PDF)
     */
    protected function handleProposalFile($propId)
    {
        $rows = $this->db->exec(
            'SELECT id, order_id, file_proposal, judul_proposal 
             FROM opti_proposal_riset 
             WHERE id = ? OR order_id = ? 
             ORDER BY id DESC LIMIT 1',
            [1 => $propId, 2 => $propId]
        );

        if (empty($rows) || empty($rows[0]['file_proposal'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas PDF Proposal Teknis belum diunggah untuk order ini.']);
            return;
        }

        $rawPath = $rows[0]['file_proposal'];
        $filePath = $this->resolveFilePath($rawPath, ['uploads/proposal', 'uploads/proposal_riset', 'storage/proposal']);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik Proposal Teknis tidak ditemukan di server: ' . basename($rawPath)]);
            return;
        }

        $docTitle = 'Proposal_Teknis_' . ($rows[0]['order_id'] ?? $propId);
        $this->sendFileAsBase64($filePath, $docTitle);
    }

    /**
     * Berkas Lampiran Tambahan User pada Surat Penawaran (Hal 3+)
     */
    protected function handleLampiranUserPenawaran($targetId, $spIdExplicit = 0)
    {
        $sp = null;
        if ($spIdExplicit > 0) {
            $r = $this->db->exec('SELECT * FROM tb_surat_penawaran WHERE id = ?', [1 => $spIdExplicit]);
            if (!empty($r)) $sp = $r[0];
        }
        if (!$sp) {
            $r = $this->db->exec(
                'SELECT * FROM tb_surat_penawaran 
                 WHERE id = ? OR order_id = ? 
                 ORDER BY (status_respon_klien = \'deal\') DESC, id DESC LIMIT 1',
                [1 => $targetId, 2 => $targetId]
            );
            if (!empty($r)) $sp = $r[0];
        }

        if (empty($sp) || empty($sp['lampiran_user'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas lampiran teknis pengguna belum diunggah untuk surat penawaran ini.']);
            return;
        }

        $rawPath = $sp['lampiran_user'];
        $filePath = $this->resolveFilePath($rawPath, ['uploads/penawaran/lampiran', 'uploads/penawaran', 'uploads/lampiran']);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik lampiran penawaran tidak ditemukan di server: ' . basename($rawPath)]);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Lampiran_Penawaran_' . ($sp['nomor_surat'] ?? $targetId));
    }

    /**
     * Berkas Surat Masuk berdasarkan order_id
     */
    protected function handleSuratMasukByOrder($orderId)
    {
        $rows = $this->db->exec('SELECT id, file_bukti_permohonan FROM order_layanan WHERE id = ?', [1 => $orderId]);
        if (empty($rows) || empty($rows[0]['file_bukti_permohonan'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas surat permohonan pelanggan belum tersedia untuk order ini.']);
            return;
        }

        $rawPath = $rows[0]['file_bukti_permohonan'];
        $filePath = $this->resolveFilePath($rawPath, ['uploads/surat_masuk', 'uploads/permohonan', 'storage/surat_masuk']);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik surat permohonan tidak ditemukan di server: ' . basename($rawPath)]);
            return;
        }

        $this->sendFileAsBase64($filePath, 'Surat_Permohonan_Order_' . $orderId);
    }

    /**
     * Pratinjau Surat Penawaran Resmi BBSPJIS
     * Render langsung dari Dompdf / generator sistem agar realtime dan bebas intercept download
     */
    protected function handleSuratPenawaran($orderOrSpId, $spIdExplicit = 0, $useKop = true, array $queryParams = [])
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
                'nomor_surat'         => 'DRAFT/SPH/' . date('Y'),
                'tanggal_surat'       => date('Y-m-d'),
                'nominal_penawaran'   => (float)($order['estimasi_total_biaya'] ?? 0),
                'perihal'             => 'Penawaran Layanan ' . ($order['layanan_nama'] ?? 'OPTI'),
                'jabatan_penandatangan' => 'Kepala Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa',
                'nama_penandatangan'  => 'Pejabat BBSPJIS',
                'lampiran_user'       => null,
            ];
        }

        if (!$sp) {
            $this->jsonResponse(['success' => false, 'message' => 'Data Surat Penawaran tidak ditemukan.']);
            return;
        }

        // Tentukan apakah gunakan kop surat atau kosongan
        $f3->set('GET.kop', $useKop ? '1' : '0');
        if (!empty($sp['id'])) {
            $f3->set('GET.id', (string)$sp['id']);
        }
        foreach ($queryParams as $k => $v) {
            if ($k !== 'kop' && $k !== 'id') {
                $f3->set('GET.' . $k, (string)$v);
            }
        }

        // Cek jika berkas PDF fisik penawaran resmi sudah di-generate sebelumnya dan match kop mode
        $physicalFile = null;
        if (!empty($sp['file_lampiran'])) {
            $physicalFile = $this->resolveFilePath($sp['file_lampiran'], ['uploads/penawaran']);
        }

        // Selalu render PDF on-the-fly dari generator resmi SuratPenawaranController
        // agar Pratinjau, Unduh PDF, dan Cetak selalu konsisten satu sumber dengan DOCX
        try {
            $pdfContent = $spController->renderPdfString($orderIdActual, $useKop, $queryParams);
        } catch (\Throwable $eCetak) {
            $pdfContent = null;
        }

        // Fallback hanya jika pembuatan PDF dinamis gagal dan berkas fisik ada
        if (empty($pdfContent) && $physicalFile && is_file($physicalFile)) {
            $pdfContent = file_get_contents($physicalFile);
        }

        if (empty($pdfContent)) {
            $this->jsonResponse(['success' => false, 'message' => 'Gagal mengompilasi pratinjau PDF Surat Penawaran.']);
            return;
        }

        $nomorSurat = preg_replace('#[^a-zA-Z0-9_-]+#', '_', $sp['nomor_surat'] ?? 'Draft');
        $wordUrl = !empty($orderIdActual) ? '/order/' . $orderIdActual . '/penawaran/word' : null;

        $this->jsonResponse([
            'success'   => true,
            'is_pdf'    => true,
            'base64'    => base64_encode($pdfContent),
            'filename'  => 'Surat_Penawaran_' . $nomorSurat . ($useKop ? '' : '_TanpaKop') . '.pdf',
            'mime'      => 'application/pdf',
            'word_url'  => $wordUrl,
        ]);
    }

    /**
     * Pratinjau Proposal Teknis (Word/PDF)
     */
    protected function handleProposal($orderId)
    {
        $proposalModel = new OptiProposalRiset($this->db);
        $prop = $proposalModel->getByOrderId($orderId);

        if (!$prop) {
            $this->jsonResponse(['success' => false, 'message' => 'Proposal Teknis untuk order ini belum dibuat.']);
            return;
        }

        // Jika ada file proposal yang diupload (format PDF/Docx)
        if (!empty($prop['file_proposal'])) {
            $filePath = $this->resolveFilePath($prop['file_proposal'], ['uploads/proposal', 'uploads/proposal_riset']);
            if ($filePath && is_file($filePath)) {
                $this->sendFileAsBase64($filePath, 'Proposal_Teknis_Order_' . $orderId);
                return;
            }
        }

        // Jika tidak ada berkas fisik, fallback ke endpoint download Word proposal teknis
        $this->jsonResponse([
            'success'   => true,
            'is_pdf'    => false,
            'is_word'   => true,
            'filename'  => 'Proposal_Teknis_' . (!empty($prop['nomor_proposal']) ? preg_replace('#[^a-zA-Z0-9_-]+#', '_', $prop['nomor_proposal']) : 'Order_' . $orderId) . '.docx',
            'word_url'  => '/order/' . $orderId . '/proposal/word',
            'ext'       => 'docx',
        ]);
    }

    /**
     * Pratinjau BAST
     */
    protected function handleBast($orderOrBastId)
    {
        $bastModel = new OptiBast($this->db);
        $orderModel = new OrderLayanan($this->db);

        $order = $orderModel->getDetail($orderOrBastId);
        $bast = null;
        if ($order) {
            $bast = $bastModel->getByOrderId($orderOrBastId);
        } else {
            $bast = $bastModel->getById($orderOrBastId);
        }

        if (!$bast) {
            $this->jsonResponse(['success' => false, 'message' => 'Data BAST belum diterbitkan untuk order ini.']);
            return;
        }

        $bastController = new OptiBastController();
        $f3 = \Base::instance();

        ob_start();
        $previousErrorHandler = set_error_handler(function($errno, $errstr) { return true; });
        try {
            $bastController->cetak($f3, ['id' => (int)$bast['id']]);
            $pdfContent = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            $pdfContent = null;
        } finally {
            if ($previousErrorHandler !== null) set_error_handler($previousErrorHandler);
            else restore_error_handler();
        }

        if (empty($pdfContent)) {
            $this->jsonResponse(['success' => false, 'message' => 'Gagal merender dokumen BAST.']);
            return;
        }

        $nomorBast = preg_replace('#[^a-zA-Z0-9_-]+#', '_', $bast['nomor_bast'] ?? 'BAST');
        $this->jsonResponse([
            'success'   => true,
            'is_pdf'    => true,
            'base64'    => base64_encode($pdfContent),
            'filename'  => 'BAST_' . $nomorBast . '.pdf',
            'mime'      => 'application/pdf',
        ]);
    }

    /**
     * Pratinjau Surat Masuk
     */
    protected function handleSuratMasuk($suratId)
    {
        $rows = $this->db->exec('SELECT id, file_surat, nomor_surat FROM tb_suratmasuk WHERE id = ?', [1 => $suratId]);
        if (empty($rows) || empty($rows[0]['file_surat'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas surat masuk tidak ditemukan.']);
            return;
        }

        $rawPath = $rows[0]['file_surat'];
        $filePath = $this->resolveFilePath($rawPath, ['uploads/surat_masuk', 'storage/surat_masuk']);

        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas fisik surat masuk tidak ditemukan di server: ' . basename($rawPath)]);
            return;
        }

        $nomorSurat = preg_replace('#[^a-zA-Z0-9_-]+#', '_', $rows[0]['nomor_surat'] ?? ('Surat_' . $suratId));
        $this->sendFileAsBase64($filePath, 'Surat_Masuk_' . $nomorSurat);
    }

    /**
     * Pratinjau PO Kegiatan
     */
    protected function handlePoCetak($poId)
    {
        $f3 = \Base::instance();
        ob_start();
        $previousErrorHandler = set_error_handler(function($errno, $errstr) { return true; });
        try {
            $controller = new \petunjuk_operasional\PoCetakController();
            $controller->cetak($f3, ['id' => $poId]);
            $pdfContent = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            $pdfContent = null;
        } finally {
            if ($previousErrorHandler !== null) set_error_handler($previousErrorHandler);
            else restore_error_handler();
        }

        if (empty($pdfContent)) {
            $this->jsonResponse(['success' => false, 'message' => 'Gagal merender lembar PO Kegiatan.']);
            return;
        }

        $this->jsonResponse([
            'success'   => true,
            'is_pdf'    => true,
            'base64'    => base64_encode($pdfContent),
            'filename'  => 'PO_Kegiatan_' . $poId . '.pdf',
            'mime'      => 'application/pdf',
        ]);
    }

    /**
     * Berkas langsung dari path fisik (misal /uploads/...)
     */
    protected function handleDirectFile($rawPath)
    {
        $filePath = $this->resolveFilePath($rawPath);
        if (!$filePath || !is_file($filePath)) {
            $this->jsonResponse(['success' => false, 'message' => 'Berkas dokumen fisik tidak ditemukan di server: ' . basename($rawPath)]);
            return;
        }

        $this->sendFileAsBase64($filePath);
    }

    /**
     * Helper resolusi path file relatif / absolut agar aman dan cross-platform
     */
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
            $norm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $c);
            if (is_file($norm)) {
                return $norm;
            }
        }

        return null;
    }

    /**
     * Konversi berkas Word (.doc/.docx) menjadi PDF di server secara background menggunakan LibreOffice/soffice.
     * Hasil PDF disimpan di direktori cache untuk pemanggilan berikutnya yang instan.
     */
    protected function convertWordToPdf($filePath)
    {
        $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $cacheDir = $appRoot . '/storage/cache/doc_pdf';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $fileHash = md5($filePath . '|' . @filemtime($filePath) . '|' . @filesize($filePath));
        $cachedPdf = $cacheDir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '_' . substr($fileHash, 0, 8) . '.pdf';

        if (is_file($cachedPdf) && filesize($cachedPdf) > 0) {
            return $cachedPdf;
        }

        // Cari lokasi binary soffice (LibreOffice)
        $sofficeCandidates = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            'soffice',
            'libreoffice',
        ];

        $sofficeBin = null;
        foreach ($sofficeCandidates as $bin) {
            if (is_file($bin) || @exec("where " . escapeshellarg($bin))) {
                $sofficeBin = $bin;
                break;
            }
        }

        if (!$sofficeBin) {
            return null;
        }

        $cmd = escapeshellarg($sofficeBin) . ' --headless --convert-to pdf --outdir ' . escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $cacheDir)) . ' ' . escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $filePath));

        @exec($cmd, $output, $returnVar);

        $defaultExpected = $cacheDir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.pdf';
        if (is_file($defaultExpected) && filesize($defaultExpected) > 0) {
            @rename($defaultExpected, $cachedPdf);
            return $cachedPdf;
        }

        return null;
    }

    /**
     * Response file dalam bentuk Base64 (PDF atau Gambar)
     */
    protected function sendFileAsBase64($filePath, $defaultName = 'Dokumen', $wordUrl = null)
    {
        $filename = basename($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Jika dokumen adalah berkas Word (.docx atau .doc), otomatis konversi di belakang layar menjadi PDF
        if ($ext === 'docx' || $ext === 'doc') {
            $convertedPdf = $this->convertWordToPdf($filePath);
            if ($convertedPdf && is_file($convertedPdf)) {
                $pdfContent = file_get_contents($convertedPdf);
                if ($pdfContent !== false) {
                    $this->jsonResponse([
                        'success'   => true,
                        'is_pdf'    => true,
                        'is_image'  => false,
                        'filename'  => $filename,
                        'base64'    => base64_encode($pdfContent),
                        'mime'      => 'application/pdf',
                        'ext'       => 'pdf',
                        'original_ext' => $ext,
                        'is_converted_word' => true,
                        'word_url'  => $wordUrl,
                    ]);
                    return;
                }
            }
        }

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
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';

        $this->jsonResponse([
            'success'   => true,
            'is_pdf'    => $isPdf,
            'is_image'  => $isImage,
            'filename'  => $filename ?: ($defaultName . '.' . $ext),
            'base64'    => $base64,
            'mime'      => $mime,
            'ext'       => $ext,
            'word_url'  => $wordUrl,
        ]);
    }

    /**
     * Kirim response JSON terstandarisasi
     */
    protected function jsonResponse($data)
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }
}
