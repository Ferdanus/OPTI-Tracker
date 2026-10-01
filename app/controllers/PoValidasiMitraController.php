<?php
/**
 * PoValidasiKeuanganController
 *
 * PO yang sudah terkirim (status = 'terkirim') langsung masuk ke sini -- TIDAK lewat approval
 * Ketua Tim lagi. Tim Mitra mengecek bukti pembayaran order terkait (surat kesanggupan bayar
 * dan/atau bukti transfer per termin), menulis catatan, lalu memvalidasi.
 *
 * Tombol Validasi hanya aktif kalau order-nya SUDAH LUNAS atau PUNYA surat kesanggupan bayar.
 * Validasi menyetel status_review='disetujui' + disposisi_humas_at -- field yang SAMA dipakai
 * PksKontrakController, jadi begitu divalidasi, PO otomatis muncul di halaman Kontrak Humas
 * tanpa controller itu perlu diubah. PO juga otomatis terkunci dari edit (guard sudah ada
 * di PoKegiatanController berdasarkan status_review).
 *
 *   GET  /po-kegiatan/validasi-keuangan        index: daftar PO terkirim yang belum divalidasi
 *   GET  /po-kegiatan/@id/validasi-detail      JSON: info pembayaran order + dokumen + catatan
 *   POST /po-kegiatan/@id/catatan              simpan catatan saja (tanpa validasi)
 *   POST /po-kegiatan/@id/validasi              validasi -> disetujui + disposisi ke Humas
 *
 * Akses: Tim Mitra (tim_mitra_industri / admin_order / tim_mitra / ketua_tim_mitra) + Superadmin.
 * Kompatibel PHP 7.2. Butuh migration_po_validasi_keuangan.sql.
 */
class PoValidasiMitraController extends Controller {

    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /po-kegiatan/validasi-keuangan */
    public function index($f3) {
        $this->izinkan();

        $errorMessage = null;
        $daftar = [];
        try {
            $rows = $this->db->exec(
                "SELECT p.id, p.nomor_po, p.status_review, p.created_at, p.updated_at,
                        o.id AS order_id, o.nomor_order, o.judul_kegiatan, o.jenis_layanan_opti, o.estimasi_biaya,
                        c.nmcustomer, c.pt_cv,
                        sp.nominal_penawaran, sp.surat_kesanggupan_bayar,
                        COALESCE((SELECT SUM(pb.jumlah) FROM opti_pembayaran pb
                                   WHERE pb.order_id = o.id AND pb.status_verifikasi = 'terverifikasi'), 0) AS total_terbayar,
                        (SELECT MAX(pb.tanggal_bayar) FROM opti_pembayaran pb
                          WHERE pb.order_id = o.id AND pb.status_verifikasi = 'terverifikasi') AS tanggal_bayar_terakhir
                 FROM po_kegiatan p
                 JOIN order_layanan o ON o.id = p.order_id
                 JOIN tb_customer c ON c.id_customer = o.id_customer
                 LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                 WHERE p.status = 'terkirim' AND p.status_review <> 'disetujui'
                 ORDER BY p.created_at DESC"
            );
        } catch (\Exception $e) {
            $rows = [];
            $errorMessage = 'Gagal memuat daftar: ' . $e->getMessage() . ' (sudah menjalankan migration_po_validasi_keuangan.sql?)';
        }

        $daftar = [];
        foreach ($rows as $r) {
            $nilai = !empty($r['nominal_penawaran']) ? (float) $r['nominal_penawaran'] : (float) $r['estimasi_biaya'];
            $terbayar = (float) $r['total_terbayar'];
            $lunas = $nilai > 0 && $terbayar >= $nilai;
            $punyaKesanggupan = !empty($r['surat_kesanggupan_bayar']);

            $daftar[] = [
                'id'                 => (int) $r['id'],
                'nomor'              => (string) $r['nomor_po'],
                'order'              => (string) $r['nomor_order'],
                'mitra'              => $this->formatMitra($r['nmcustomer'], $r['pt_cv']),
                'judul'              => (string) $r['judul_kegiatan'],
                'divisi'             => (string) $r['jenis_layanan_opti'],
                'nilai'              => $nilai,
                'terbayar'           => $terbayar,
                'lunas'              => $lunas,
                'punya_kesanggupan'  => $punyaKesanggupan,
                'bisa_validasi'      => $lunas || $punyaKesanggupan,
                'status_review'      => (string) $r['status_review'],
                'dikirim'            => substr((string) $r['updated_at'] ?: $r['created_at'], 0, 10),
                'tanggal_bayar'      => $r['tanggal_bayar_terakhir'],
            ];
        }

        // [BARU] Tab ke-2 "Sudah Disetujui" -- PO yang udah divalidasi Tim Mitra.
        // Dikelompokkan per bulan+tahun dari tervalidasi_mitra_at (selalu keisi
        // bareng status_review='disetujui', lihat validasi() di bawah), biar
        // frontend bisa nampilin cuma bulan berjalan secara default & browsing
        // bulan lain lewat filter bulan/tahun atau pencarian.
        $daftarDisetujui = [];
        try {
            $rowsDisetujui = $this->db->exec(
                "SELECT p.id, p.nomor_po, p.catatan_tim_mitra, p.tervalidasi_mitra_at, p.disposisi_humas_at,
                        o.nomor_order, o.judul_kegiatan, o.jenis_layanan_opti, o.estimasi_biaya,
                        c.nmcustomer, c.pt_cv,
                        sp.nominal_penawaran
                 FROM po_kegiatan p
                 JOIN order_layanan o ON o.id = p.order_id
                 JOIN tb_customer c ON c.id_customer = o.id_customer
                 LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                 WHERE p.status_review = 'disetujui'
                 ORDER BY p.tervalidasi_mitra_at DESC"
            );
        } catch (\Exception $e) {
            $rowsDisetujui = [];
        }

        foreach ($rowsDisetujui as $r) {
            $nilai = !empty($r['nominal_penawaran']) ? (float) $r['nominal_penawaran'] : (float) $r['estimasi_biaya'];
            $tglValidasi = (string) ($r['tervalidasi_mitra_at'] ?: $r['disposisi_humas_at'] ?: '');
            $ts = $tglValidasi !== '' ? strtotime($tglValidasi) : false;

            $daftarDisetujui[] = [
                'id'       => (int) $r['id'],
                'nomor'    => (string) $r['nomor_po'],
                'order'    => (string) $r['nomor_order'],
                'mitra'    => $this->formatMitra($r['nmcustomer'], $r['pt_cv']),
                'judul'    => (string) $r['judul_kegiatan'],
                'divisi'   => (string) $r['jenis_layanan_opti'],
                'nilai'    => $nilai,
                'catatan'  => (string) ($r['catatan_tim_mitra'] ?? ''),
                'tanggal'  => $tglValidasi !== '' ? substr($tglValidasi, 0, 10) : null,
                'bulan'    => $ts ? (int) date('n', $ts) : null,
                'tahun'    => $ts ? (int) date('Y', $ts) : null,
            ];
        }

        $f3->set('daftar_po_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('daftar_disetujui_json', json_encode($daftarDisetujui, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('bulan_ini', (int) date('n'));
        $f3->set('tahun_ini', (int) date('Y'));
        $f3->set('error_message', $errorMessage);
        $this->render('katim_kerja/po-kegiatan/validasi_mitra.html', 'Validasi Pembayaran PO', 'po_validasi_keuangan');
    }

    /* ============================================================
     * API
     * ============================================================ */

    /** GET /po-kegiatan/@id/validasi-detail */
    public function detailJson($f3, $params) {
        list($po, $order) = $this->akses((int) $params['id']);

        $sp = $this->safeExec(
            "SELECT surat_kesanggupan_bayar, nominal_penawaran FROM tb_surat_penawaran
             WHERE order_id = ? AND status_respon_klien = 'deal' ORDER BY id DESC LIMIT 1",
            [1 => (int) $order['id']]
        );
        $sp = !empty($sp) ? $sp[0] : [];

        $nilai = !empty($sp['nominal_penawaran']) ? (float) $sp['nominal_penawaran'] : (float) $order['estimasi_biaya'];

        $bayarRows = $this->safeExec(
            "SELECT id, termin_ke, jumlah, tanggal_bayar, status_verifikasi, bukti_bayar
             FROM opti_pembayaran WHERE order_id = ? ORDER BY termin_ke ASC",
            [1 => (int) $order['id']]
        );
        $totalTerbayar = 0;
        $termin = [];
        foreach ($bayarRows as $b) {
            if ($b['status_verifikasi'] === 'terverifikasi') { $totalTerbayar += (float) $b['jumlah']; }
            $termin[] = [
                'termin_ke'         => (int) $b['termin_ke'],
                'jumlah'            => (float) $b['jumlah'],
                'tanggal_bayar'     => $b['tanggal_bayar'],
                'status_verifikasi' => $b['status_verifikasi'],
                'url'               => !empty($b['bukti_bayar']) ? ($f3->get('BASE') . '/pembayaran/bukti/' . $this->buatTokenBukti((int) $b['id'])) : null,
            ];
        }

        $kesanggupanAda = !empty($sp['surat_kesanggupan_bayar']);

        $this->keluarkanJson([
            'ok' => true,
            'po' => [
                'id' => (int) $po['id'],
                'nomor' => (string) $po['nomor_po'],
                'catatan' => (string) ($po['catatan_tim_mitra'] ?? ''),
                'sudah_disetujui' => $po['status_review'] === 'disetujui',
            ],
            'order' => ['nomor' => (string) $order['nomor_order'], 'judul' => (string) $order['judul_kegiatan']],
            'nilai' => $nilai,
            'total_terbayar' => $totalTerbayar,
            'lunas' => $nilai > 0 && $totalTerbayar >= $nilai,
            'kesanggupan' => [
                'ada' => $kesanggupanAda,
                'url' => $kesanggupanAda ? ($f3->get('BASE') . '/penawaran/surat-kesanggupan/' . (int) $order['id']) : null,
            ],
            'termin' => $termin,
            'bisa_validasi' => ($nilai > 0 && $totalTerbayar >= $nilai) || $kesanggupanAda,
        ]);
    }

    /** POST /po-kegiatan/@id/catatan -- simpan catatan tanpa validasi */
    public function simpanCatatan($f3, $params) {
        list($po, $order) = $this->akses((int) $params['id']);

        $catatan = trim((string) $f3->get('POST.catatan'));
        try {
            $this->db->exec("UPDATE po_kegiatan SET catatan_tim_mitra = ? WHERE id = ?", [1 => $catatan, 2 => (int) $po['id']]);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan catatan.'], 500);
        }
        $this->keluarkanJson(['ok' => true]);
    }

    /** POST /po-kegiatan/@id/validasi */
    public function validasi($f3, $params) {
        list($po, $order) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();

        if ($po['status_review'] === 'disetujui') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini sudah divalidasi sebelumnya.'], 422);
        }

        // cek ulang di server -- jangan percaya tombol frontend doang
        $sp = $this->safeExec(
            "SELECT surat_kesanggupan_bayar, nominal_penawaran FROM tb_surat_penawaran
             WHERE order_id = ? AND status_respon_klien = 'deal' ORDER BY id DESC LIMIT 1",
            [1 => (int) $order['id']]
        );
        $sp = !empty($sp) ? $sp[0] : [];
        $nilai = !empty($sp['nominal_penawaran']) ? (float) $sp['nominal_penawaran'] : (float) $order['estimasi_biaya'];
        $totalTerbayar = (float) $this->safeExecScalar(
            "SELECT COALESCE(SUM(jumlah),0) FROM opti_pembayaran WHERE order_id = ? AND status_verifikasi = 'terverifikasi'",
            [1 => (int) $order['id']]
        );
        $lunas = $nilai > 0 && $totalTerbayar >= $nilai;
        $kesanggupanAda = !empty($sp['surat_kesanggupan_bayar']);

        if (!$lunas && !$kesanggupanAda) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Belum bisa divalidasi: order ini belum lunas dan tidak ada surat kesanggupan bayar.'], 422);
        }

        $catatan = trim((string) $f3->get('POST.catatan'));

        try {
            $this->db->exec(
                "UPDATE po_kegiatan
                    SET status_review = 'disetujui', disposisi_humas_at = NOW(),
                        tervalidasi_mitra_at = NOW(), tervalidasi_mitra_by = ?,
                        catatan_tim_mitra = ?,
                        reviewed_at = COALESCE(reviewed_at, NOW()), reviewed_by = COALESCE(reviewed_by, ?)
                  WHERE id = ? AND status_review <> 'disetujui'",
                [1 => $uid, 2 => $catatan, 3 => $uid, 4 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan validasi: ' . $e->getMessage()], 500);
        }

        // catatan sistem di thread PO (kalau tabel po_chat ada) -- biar pembuat PO (tim_kerja) kebagian notifikasi
        if (class_exists('PoReviewController')) {
            try {
                \PoReviewController::catatPesanSistem($this->db, (int) $po['id'], $uid, 'katim', 'PO divalidasi Tim Mitra (pembayaran OK) dan didisposisikan ke Humas.');
            } catch (\Exception $e) {}
        }

        $this->keluarkanJson(['ok' => true]);
    }

    /* ============================================================
     * HELPER
     * ============================================================ */

    protected function ambilPo($poId) {
        if ($poId <= 0) { return null; }
        $rows = $this->safeExec(
            "SELECT p.id, p.nomor_po, p.status, p.status_review, p.catatan_tim_mitra, p.order_id
             FROM po_kegiatan p WHERE p.id = ? LIMIT 1",
            [1 => $poId]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    protected function ambilOrder($orderId) {
        $rows = $this->safeExec(
            "SELECT id, nomor_order, judul_kegiatan, estimasi_biaya, jenis_layanan_opti FROM order_layanan WHERE id = ? LIMIT 1",
            [1 => (int) $orderId]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /** Wajib login + role Tim Mitra/Superadmin + PO & order valid. Return [$po, $order] atau langsung jawab JSON error. */
    protected function akses($poId) {
        $this->izinkan(true);
        $po = $this->ambilPo($poId);
        if (!$po) { $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan.'], 404); }
        $order = $this->ambilOrder($po['order_id']);
        if (!$order) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Order terkait tidak ditemukan.'], 404); }
        return [$po, $order];
    }

    protected function izinkan($json = false) {
        $this->requireAuth();
        if ($this->isSuperadmin() || $this->isTimMitra()) { return; }
        if ($json) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke halaman ini.'], 403); }
        $this->f3->error(403, 'Halaman ini hanya untuk Tim Mitra dan Superadmin.');
        exit;
    }

    protected function safeExec($sql, $params = []) {
        try { return $this->db->exec($sql, $params); } catch (\Exception $e) { return []; }
    }

    protected function safeExecScalar($sql, $params = []) {
        $r = $this->safeExec($sql, $params);
        if (empty($r)) { return 0; }
        $row = $r[0];
        return reset($row);
    }

    /** Sama persis dengan PembayaranOptiController::buatTokenBukti() -- kunci BUKTI_KEY yang sama. */
    protected function buatTokenBukti($id) {
        $secret = (string) $this->f3->get('BUKTI_KEY');
        if ($secret === '') { return (string) (int) $id; }
        $key = hash('sha256', $secret, true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt((string) (int) $id, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return rtrim(strtr(base64_encode($iv . $enc), '+/', '-_'), '=');
    }

    protected function formatMitra($nama, $ptCv) {
        $bersih = trim((string) preg_replace('/^((PT|CV|UD)\.?\s+)+/i', '', (string) $nama));
        $badan  = trim((string) $ptCv);
        return ($badan !== '') ? $badan . ' ' . $bersih : $bersih;
    }

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}