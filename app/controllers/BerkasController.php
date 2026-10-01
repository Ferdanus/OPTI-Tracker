<?php
/**
 * BerkasController
 *
 * Kumpulan berkas terkait 1 order: Surat Masuk (asli), Surat Penawaran, Proposal (Selulosa saja),
 * dan Bukti Pembayaran (bisa lebih dari 1 termin). Dipakai dari tombol "Lihat Berkas" di daftar PO.
 *
 *   GET /order/@id/berkas            -> JSON daftar kategori + status ada/tidak + URL file
 *   GET /penawaran/@id/lampiran      -> stream file lampiran surat penawaran (id = tb_surat_penawaran.id)
 *   GET /proposal/@id/file           -> stream file proposal riset (id = opti_proposal_riset.id)
 *
 * Bukti pembayaran TIDAK dilayani dari sini -- dipakein lagi route yang udah ada:
 *   GET /pembayaran/bukti/@token (PembayaranOptiController::unduhBukti), token dibikin ulang
 *   di sini pakai kunci BUKTI_KEY yang sama, jadi TIDAK perlu ubah controller pembayaran itu sama sekali.
 *
 * Surat Masuk asli TIDAK dilayani dari sini juga -- dipakein route yang udah ada:
 *   GET /surat-masuk/@id/pdf (SuratMasukController::previewPdf)
 *
 * PENTING: kolom bukti pembayaran diasumsikan `bukti_bayar` (sesuai migration_pembayaran & PembayaranOptiController
 * yang udah dipasang). Kalau kolom aslinya `bukti_pembayaran`, ganti SATU baris di method buktiPembayaranList().
 */
class BerkasController extends Controller {

    /** GET /order/@id/berkas */
    public function index($f3, $params) {
        $this->requireAuth();

        $orderId = (int) $params['id'];
        if ($orderId <= 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Order tidak valid.'], 404);
        }

        $order = $this->safeExec(
            "SELECT id, nomor_order, jenis_layanan_opti, id_surat_masuk FROM order_layanan WHERE id = ? LIMIT 1",
            [1 => $orderId]
        );
        if (empty($order)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Order tidak ditemukan.'], 404);
        }
        $order = $order[0];
        $divisi = strtolower((string) $order['jenis_layanan_opti']);

        $items = [];
        $items[] = $this->itemSuratMasuk($order);
        $items[] = $this->itemPenawaran($orderId);
        if ($divisi === 'selulosa') {
            $items[] = $this->itemProposal($orderId);
        }
        $items[] = $this->itemPembayaran($orderId);

        $this->keluarkanJson([
            'ok'          => true,
            'nomor_order' => (string) $order['nomor_order'],
            'divisi'      => $divisi,
            'items'       => $items,
        ]);
    }

    /** GET /penawaran/@id/lampiran -- id = tb_surat_penawaran.id */
    public function lampiranPenawaran($f3, $params) {
        $this->requireAuth();
        $id = (int) $params['id'];
        $r = $this->safeExec("SELECT file_lampiran FROM tb_surat_penawaran WHERE id = ? LIMIT 1", [1 => $id]);
        $this->streamBerkasKolom(!empty($r) ? $r[0]['file_lampiran'] : null);
    }

    /** GET /proposal/@id/file -- id = opti_proposal_riset.id */
    public function fileProposal($f3, $params) {
        $this->requireAuth();
        $id = (int) $params['id'];
        $r = $this->safeExec("SELECT file_proposal FROM opti_proposal_riset WHERE id = ? LIMIT 1", [1 => $id]);
        $this->streamBerkasKolom(!empty($r) ? $r[0]['file_proposal'] : null);
    }

    /** GET /penawaran/surat-kesanggupan/@id -- id = order_layanan.id (ambil surat penawaran yang deal untuk order itu) */
    public function suratKesanggupan($f3, $params) {
        $this->requireAuth();
        $orderId = (int) $params['id'];
        $r = $this->safeExec(
            "SELECT surat_kesanggupan_bayar FROM tb_surat_penawaran
             WHERE order_id = ? AND status_respon_klien = 'deal' ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        $this->streamBerkasKolom(!empty($r) ? $r[0]['surat_kesanggupan_bayar'] : null);
    }

    /* ============================================================
     * Penyusun tiap kategori
     * ============================================================ */

    protected function itemSuratMasuk(array $order) {
        $ada = false;
        $url = null;
        if (!empty($order['id_surat_masuk'])) {
            try {
                $s = $this->dbSekretariat->exec(
                    "SELECT id_arsip, nama_berkas FROM tb_arsipsurat WHERE id_arsip = ? LIMIT 1",
                    [1 => (int) $order['id_surat_masuk']]
                );
                if (!empty($s) && !empty($s[0]['nama_berkas'])) {
                    $ada = true;
                    $url = $this->f3->get('BASE') . '/surat-masuk/' . (int) $s[0]['id_arsip'] . '/pdf';
                }
            } catch (\Exception $e) {}
        }
        return ['kategori' => 'surat_masuk', 'label' => 'Surat Masuk (Asli)', 'ikon' => 'bi-envelope-open', 'ada' => $ada, 'url' => $url];
    }

    protected function itemPenawaran($orderId) {
        $ada = false;
        $url = null;
        $r = $this->safeExec(
            "SELECT id FROM tb_surat_penawaran
             WHERE order_id = ? AND file_lampiran IS NOT NULL AND file_lampiran <> ''
             ORDER BY (status_respon_klien = 'deal') DESC, id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (!empty($r)) {
            $ada = true;
            $url = $this->f3->get('BASE') . '/penawaran/' . (int) $r[0]['id'] . '/lampiran';
        }
        return ['kategori' => 'penawaran', 'label' => 'Surat Penawaran', 'ikon' => 'bi-file-earmark-text', 'ada' => $ada, 'url' => $url];
    }

    protected function itemProposal($orderId) {
        $ada = false;
        $url = null;
        $r = $this->safeExec(
            "SELECT id FROM opti_proposal_riset
             WHERE order_id = ? AND file_proposal IS NOT NULL AND file_proposal <> '' ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (!empty($r)) {
            $ada = true;
            $url = $this->f3->get('BASE') . '/proposal/' . (int) $r[0]['id'] . '/file';
        }
        return ['kategori' => 'proposal', 'label' => 'Proposal Riset', 'ikon' => 'bi-file-earmark-ruled', 'ada' => $ada, 'url' => $url];
    }

    /** Bukti pembayaran: bisa lebih dari satu termin, satu URL per termin (dipakein token seperti /pembayaran/bukti/@token). */
    protected function itemPembayaran($orderId) {
        $daftar = [];
        // [CEK] kolom bukti_bayar -- ganti jadi bukti_pembayaran di sini kalau ternyata itu nama kolom aslinya.
        $rows = $this->safeExec(
            "SELECT id, termin_ke, jumlah, tanggal_bayar, status_verifikasi FROM opti_pembayaran
             WHERE order_id = ? AND bukti_bayar IS NOT NULL AND bukti_bayar <> '' ORDER BY termin_ke ASC",
            [1 => $orderId]
        );
        foreach ($rows as $r) {
            $daftar[] = [
                'termin_ke'         => (int) $r['termin_ke'],
                'jumlah'            => (float) $r['jumlah'],
                'tanggal_bayar'     => $r['tanggal_bayar'],
                'status_verifikasi' => $r['status_verifikasi'],
                'url'               => $this->f3->get('BASE') . '/pembayaran/bukti/' . $this->buatTokenBukti((int) $r['id']),
            ];
        }
        return ['kategori' => 'pembayaran', 'label' => 'Bukti Pembayaran', 'ikon' => 'bi-receipt', 'ada' => !empty($daftar), 'items' => $daftar];
    }

    /* ============================================================
     * Helper
     * ============================================================ */

    protected function safeExec($sql, $params = []) {
        try {
            return $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Sama persis dengan PembayaranOptiController::buatTokenBukti() -- kunci BUKTI_KEY yang sama
     * bikin token di sini kebaca juga sama unduhBukti() di sana, tanpa perlu ubah file itu.
     */
    protected function buatTokenBukti($id) {
        $secret = (string) $this->f3->get('BUKTI_KEY');
        if ($secret === '') { return (string) (int) $id; } // fallback kalau BUKTI_KEY belum di-set di config.ini
        $key = hash('sha256', $secret, true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt((string) (int) $id, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return rtrim(strtr(base64_encode($iv . $enc), '+/', '-_'), '=');
    }

    /** Stream file dari kolom DB (path relatif). Coba beberapa lokasi umum (ROOT langsung / di dalam public/). */
    protected function streamBerkasKolom($relPath) {
        if (!$relPath) {
            $this->f3->error(404, 'Berkas tidak tersedia.');
            return;
        }
        $relPath = ltrim((string) $relPath, "/\\");
        $root = rtrim((string) $this->f3->get('ROOT'), '/\\');

        $kandidat = [$root . '/' . $relPath, $root . '/public/' . $relPath];
        $path = null;
        foreach ($kandidat as $k) {
            if (is_file($k)) { $path = $k; break; }
        }
        if (!$path) {
            $this->f3->error(404, 'Berkas tidak ditemukan di server. Path yang dicoba: ' . implode(', ', $kandidat));
            return;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeMap = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        header('Content-Type: ' . (isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path);
        exit;
    }

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}