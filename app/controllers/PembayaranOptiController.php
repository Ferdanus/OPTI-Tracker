<?php

/**
 * PembayaranController (mandiri, tidak bergantung tabel `po`)
 *
 * [DIUBAH] index(): filter search/divisi/status/tahun sekarang dibangun
 * SEKALI (whereParts/params) dan dipakai ULANG di kedua query (tabel rekap
 * per-order & tabel histori transaksi mentah) -- sebelumnya filter cuma
 * kepakai di tabel rekap, tabel histori selalu nampilin semua transaksi.
 *
 * [DIUBAH] status_keuangan = 'lunas' secara default DISEMBUNYIKAN dari
 * daftar (biar fokus ke yang masih perlu ditagih), TAPI kalau filter
 * Status Pembayaran dipilih manual ke 'lunas', order itu ikut muncul lagi.
 */
class PembayaranOptiController extends Controller {

    protected $allowedExt  = ['pdf', 'jpg', 'jpeg', 'png'];
    protected $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
    protected $maxSize     = 5242880; // 5MB

    /** GET /pembayaran */
    public function index($f3) {
        $this->requirePermission('pembayaran:view', '/dashboard');
    
        // ---- Tab 1: Baru Masuk (menunggu_pembayaran, belum ada pembayaran sama sekali) ----
        $sqlBaru = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, o.tanggal_masuk,
                           o.jenis_layanan_opti,
                           c.nmcustomer AS nama_perusahaan, c.pt_cv,
                           sp.nominal_penawaran
                    FROM order_layanan o
                    JOIN tb_customer c ON o.id_customer = c.id_customer
                    LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                    WHERE o.status_keuangan = 'menunggu_pembayaran'
                      AND o.id NOT IN (SELECT DISTINCT order_id FROM opti_pembayaran)
                    ORDER BY o.tanggal_masuk DESC";
       $daftarBaru = $this->safeQuery($sqlBaru);
       foreach ($daftarBaru as &$o) {
           $o['biaya_acuan'] = !empty($o['nominal_penawaran']) ? (float)$o['nominal_penawaran'] : (float)$o['estimasi_biaya'];
           $t = strtotime($o['tanggal_masuk']);
           $o['tahun_masuk'] = $t ? (int) date('Y', $t) : null;
           $o['bulan_masuk'] = $t ? (int) date('n', $t) : null;
       }
       unset($o);
    
        // ---- Tab 2: Sedang Berjalan (terbayar_sebagian) ----
        $sqlBerjalan = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya,
                                o.jenis_layanan_opti,
                                c.nmcustomer AS nama_perusahaan, c.pt_cv,
                                sp.nominal_penawaran,
                                COALESCE((SELECT SUM(p.jumlah) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi'), 0) AS total_terbayar,
                                COALESCE((SELECT COUNT(p.id) FROM opti_pembayaran p WHERE p.order_id = o.id), 0) AS jumlah_termin
                         FROM order_layanan o
                         JOIN tb_customer c ON o.id_customer = c.id_customer
                         LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                         WHERE o.status_keuangan = 'terbayar_sebagian'
                         ORDER BY o.created_at DESC";
        $daftarBerjalan = $this->safeQuery($sqlBerjalan);
        foreach ($daftarBerjalan as &$o) {
            $biayaAcuan = !empty($o['nominal_penawaran']) ? (float)$o['nominal_penawaran'] : (float)$o['estimasi_biaya'];
            $o['biaya_acuan']  = $biayaAcuan;
            $o['sisa_tagihan'] = max(0, $biayaAcuan - (float)$o['total_terbayar']);
            $o['persen_bayar'] = $biayaAcuan > 0 ? min(100, round(((float)$o['total_terbayar'] / $biayaAcuan) * 100, 1)) : 0;
        }
        unset($o);
    
        // ---- Tab 3: Lunas (SEMUA, difilter bulan/tahun di JS -- tanggal_lunas dari pembayaran terakhir) ----
        $sqlLunas = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya,
                            o.jenis_layanan_opti,
                            c.nmcustomer AS nama_perusahaan, c.pt_cv,
                            sp.nominal_penawaran,
                            COALESCE((SELECT COUNT(p.id) FROM opti_pembayaran p WHERE p.order_id = o.id), 0) AS jumlah_termin,
                            (SELECT MAX(p.tanggal_bayar) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi') AS tanggal_lunas
                     FROM order_layanan o
                     JOIN tb_customer c ON o.id_customer = c.id_customer
                     LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                     WHERE o.status_keuangan = 'lunas'
                     ORDER BY tanggal_lunas DESC";
        $daftarLunas = $this->safeQuery($sqlLunas);
        foreach ($daftarLunas as &$o) {
            $o['biaya_acuan'] = !empty($o['nominal_penawaran']) ? (float)$o['nominal_penawaran'] : (float)$o['estimasi_biaya'];
            $t = strtotime($o['tanggal_lunas']);
            $o['tahun_lunas'] = $t ? (int) date('Y', $t) : null;
            $o['bulan_lunas'] = $t ? (int) date('n', $t) : null;
        }
        unset($o);
    
        // ---- Tab 4: Histori (SEMUA transaksi mentah, difilter bulan/tahun di JS) ----
        $sqlHistori = "SELECT p.*, o.nomor_order, o.jenis_layanan_opti, c.nmcustomer AS nama_perusahaan
                       FROM opti_pembayaran p
                       JOIN order_layanan o ON p.order_id = o.id
                       JOIN tb_customer c ON o.id_customer = c.id_customer
                       ORDER BY p.tanggal_bayar DESC, p.id DESC";
        $daftarHistori = $this->safeQuery($sqlHistori);
        foreach ($daftarHistori as &$h) {
            $t = strtotime($h['tanggal_bayar']);
            $h['tahun_bayar'] = $t ? (int) date('Y', $t) : null;
            $h['bulan_bayar'] = $t ? (int) date('n', $t) : null;
        }
        unset($h);
    
        $tahunSekarang = (int) date('Y');
        $daftarTahun = [];
        for ($i = 0; $i < 5; $i++) { $daftarTahun[] = $tahunSekarang - $i; }
    
        $f3->set('daftar_baru_json', json_encode($daftarBaru, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_berjalan_json', json_encode($daftarBerjalan, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_lunas_json', json_encode($daftarLunas, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_histori_json', json_encode($daftarHistori, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_tahun', $daftarTahun);
        $f3->set('bulan_sekarang', (int) date('n'));
        $f3->set('tahun_sekarang', $tahunSekarang);
        $this->render('keuangan/pembayaran/index.html', 'Rekapitulasi Keuangan & Pembayaran', 'pembayaran');
    }

    /** GET /pembayaran/tambah */
    /** GET /pembayaran/tambah?order_id=X */
public function tambah($f3) {
    $this->requirePermission('pembayaran:create', '/pembayaran');
    $orderId = (int)($f3->get('GET.order_id') ?? 0);

    if ($orderId <= 0) {
        $f3->set('order', null);
        $this->render('keuangan/pembayaran/form.html', 'Catat Pembayaran', 'pembayaran');
        return;
    }

    $rows = $this->safeQuery(
        "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, o.jenis_layanan_opti,
                c.nmcustomer AS nama_perusahaan, c.pt_cv,
                sp.nominal_penawaran,
                COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id AND status_verifikasi = 'terverifikasi'), 0) AS terbayar,
                COALESCE((SELECT COUNT(id) FROM opti_pembayaran WHERE order_id = o.id), 0) AS jumlah_termin_sebelumnya
         FROM order_layanan o
         JOIN tb_customer c ON o.id_customer = c.id_customer
         LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
         WHERE o.id = ?
         LIMIT 1",
        [$orderId]
    );

    if (empty($rows)) {
        $f3->set('order', null);
        $this->render('keuangan/pembayaran/form.html', 'Catat Pembayaran', 'pembayaran');
        return;
    }

    $order = $rows[0];
    $biayaAcuan = !empty($order['nominal_penawaran']) ? (float)$order['nominal_penawaran'] : (float)$order['estimasi_biaya'];
    $order['biaya_acuan']       = $biayaAcuan;
    $order['sisa_tagihan']      = max(0, $biayaAcuan - (float)$order['terbayar']);
    $order['termin_berikutnya'] = (int)$order['jumlah_termin_sebelumnya'] + 1;

    // Audit Tahap 6: Halaman pencatatan pembayaran dibuka/dibaca oleh Bagian Keuangan
    $userId = (int)$this->getUserId();
    if ($userId > 0 && $orderId > 0) {
        $userNama = $_SESSION['nama_lengkap'] ?? ($_SESSION['user']['nama'] ?? 'Bagian Keuangan');
        \StageAudit::recordDibaca($this->db, $orderId, 6, $userId, $userNama, 'Bagian Keuangan');
    }

    $f3->set('order', $order);

    $this->render('keuangan/pembayaran/form.html', 'Catat Pembayaran', 'pembayaran');
}

    /** POST /pembayaran/simpan */
    public function simpan($f3) {
        $this->requirePermission('pembayaran:create', '/pembayaran');

        $post = $f3->get('POST');

        $orderId      = (int)($post['order_id'] ?? 0);
        $terminKe     = (int)($post['termin_ke'] ?? 1);
        $tanggalBayar = $post['tanggal_bayar'] ?? date('Y-m-d');
        $jumlah       = (float)($post['jumlah'] ?? 0);
        $metode       = trim($post['metode_pembayaran'] ?? 'transfer_bank');
        $noTransaksi  = trim($post['nomor_transaksi_ntpn'] ?? '');
        $keterangan   = trim($post['keterangan'] ?? '');

        if ($orderId <= 0 || $jumlah <= 0) {
            $this->setFlashError('Pilih order layanan dan masukkan nominal pembayaran valid.');
            $f3->reroute('/pembayaran/tambah');
            return;
        }

        // [BARU] cek nominal terhadap sisa tagihan order SEBELUM pembayaran ini disimpan,
        // supaya kalau kelewat (misal salah pilih order / salah ketik nominal) tetap
        // kesimpan (fleksibel buat kasus wajar) tapi user diberi tahu jelas di pesan sukses,
        // bukan cuma diam-diam bikin order jadi "lunas" lalu hilang dari daftar rekap.
        $sisaSebelumBayar = $this->getSisaTagihan($orderId);

// [DIUBAH] overpayment sekarang diblok sebelum kesimpen, bukan warning setelahnya
if ($sisaSebelumBayar !== null && $jumlah > $sisaSebelumBayar) {
    $selisih = $jumlah - $sisaSebelumBayar;
    $this->setFlashError(
        'Nominal yang dimasukkan (Rp ' . number_format($jumlah, 0, ',', '.') . ') melebihi sisa tagihan order ini sebesar '
        . 'Rp ' . number_format($sisaSebelumBayar, 0, ',', '.') . ' (selisih Rp ' . number_format($selisih, 0, ',', '.') . '). '
        . 'Mohon periksa kembali nominal yang dimasukkan, kemungkinan terdapat kesalahan penulisan angka.'
    );
    $f3->reroute('/pembayaran/tambah?order_id=' . $orderId);
    return;
}

        try {
            $buktiBayarFile = $this->validateAndStoreBuktiBayar($f3->get('FILES.bukti_bayar'));
        } catch (\Exception $e) {
            $this->setFlashError($e->getMessage());
            $f3->reroute('/pembayaran/tambah?order_id=' . $orderId);
            return;
        }

        try {
            $pembayaran = new OptiPembayaran($this->db);
            $pembayaran->order_id             = $orderId;
            $pembayaran->po_id                = null;
            $pembayaran->termin_ke            = $terminKe;
            $pembayaran->tanggal_bayar        = $tanggalBayar;
            $pembayaran->jumlah               = $jumlah;
            $pembayaran->metode_pembayaran    = $metode ?: 'transfer_bank';
            $pembayaran->nomor_transaksi_ntpn = $noTransaksi ?: null;
            $pembayaran->keterangan           = $keterangan ?: null;
            $pembayaran->bukti_bayar          = $buktiBayarFile;
            $pembayaran->status_verifikasi    = 'terverifikasi';
            $pembayaran->verifikator_id       = $this->getUserId() ?? null;
            $pembayaran->created_at           = date('Y-m-d H:i:s');
            $pembayaran->save();

            // [PENTING] ini yang nge-update order_layanan.status_keuangan otomatis
            $statusBaru = $this->recalcStatusKeuangan($orderId);

            $pesanSukses = $statusBaru === 'lunas'
            ? 'Pembayaran tercatat. Order dinyatakan LUNAS.'
            : 'Pembayaran termin tercatat.';
        
        $this->setFlashSuccess($pesanSukses);

            $this->setFlashSuccess($pesanSukses);

            $f3->reroute('/pembayaran');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal mencatat pembayaran: ' . $e->getMessage());
            $f3->reroute('/pembayaran/tambah');
        }
    }

    /** POST /pembayaran/@id/hapus */
    public function hapus($f3, $params) {
        $this->requirePermission('pembayaran:edit', '/pembayaran');

        $id = (int)($params['id'] ?? 0);
        $pembayaran = new OptiPembayaran($this->db);
        $pembayaran->load(['id = ?', $id]);

        if (!$pembayaran->dry()) {
            $orderId = $pembayaran->order_id;
            $pembayaran->erase();
            $this->recalcStatusKeuangan($orderId);
            $this->setFlashSuccess('Transaksi pembayaran berhasil dihapus.');
        } else {
            $this->setFlashError('Transaksi tidak ditemukan.');
        }

        $f3->reroute('/pembayaran');
    }

    /** GET /pembayaran/bukti/@id */
    public function unduhBukti($f3, $params) {
        $this->requireAuth();

        $row = $this->safeQuery('SELECT bukti_bayar FROM opti_pembayaran WHERE id = ?', [(int)$params['id']]);
        if (empty($row) || empty($row[0]['bukti_bayar'])) { $f3->error(404); return; }

        $fileName = $row[0]['bukti_bayar'];
        $path = $this->f3->get('ROOT') . '/storage/pembayaran/' . $fileName;
        if (!is_file($path)) { $f3->error(404); return; }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeMap = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * Hitung ulang status_keuangan order berdasarkan total pembayaran
     * (status_verifikasi='terverifikasi') vs nilai acuan (nominal_penawaran
     * yang deal, fallback estimasi_biaya).
     */
    protected function recalcStatusKeuangan($orderId) {
        $totalRows = $this->safeQuery(
            "SELECT COALESCE(SUM(jumlah), 0) AS total FROM opti_pembayaran WHERE order_id = ? AND status_verifikasi = 'terverifikasi'",
            [$orderId]
        );
        $totalBayar = (float)($totalRows[0]['total'] ?? 0);

        $acuanRows = $this->safeQuery(
            "SELECT o.estimasi_biaya, sp.nominal_penawaran
             FROM order_layanan o
             LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
             WHERE o.id = ? LIMIT 1",
            [$orderId]
        );
        if (empty($acuanRows)) return null;

        $biayaAcuan = !empty($acuanRows[0]['nominal_penawaran'])
            ? (float)$acuanRows[0]['nominal_penawaran']
            : (float)$acuanRows[0]['estimasi_biaya'];

        if ($biayaAcuan <= 0) return null;

        $statusBaru = ($totalBayar >= $biayaAcuan) ? 'lunas' : 'terbayar_sebagian';

        $order = new OrderLayanan($this->db);
        $order->load(['id = ?', $orderId]);
        if (!$order->dry()) {
            $order->status_keuangan = $statusBaru;
            $order->save();
        }

        return $statusBaru;
    }

    /**
     * [BARU] Hitung sisa tagihan sebuah order SAAT INI (sebelum pembayaran baru
     * yang sedang diproses ikut dihitung), pakai acuan yang sama persis dengan
     * recalcStatusKeuangan()/index() -- nominal_penawaran yang deal, fallback
     * estimasi_biaya. Dipakai buat deteksi input nominal yang kebablasan.
     */
    protected function getSisaTagihan($orderId) {
        $rows = $this->safeQuery(
            "SELECT o.estimasi_biaya, sp.nominal_penawaran,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id AND status_verifikasi = 'terverifikasi'), 0) AS total_terbayar
             FROM order_layanan o
             LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
             WHERE o.id = ? LIMIT 1",
            [$orderId]
        );
        if (empty($rows)) return null;

        $biayaAcuan = !empty($rows[0]['nominal_penawaran'])
            ? (float)$rows[0]['nominal_penawaran']
            : (float)$rows[0]['estimasi_biaya'];
        $totalTerbayar = (float)$rows[0]['total_terbayar'];

        return max(0, $biayaAcuan - $totalTerbayar);
    }

    protected function validateAndStoreBuktiBayar($file) {
        if (!$file || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new \Exception('Bukti pembayaran wajib diunggah.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Terjadi kesalahan saat mengunggah bukti bayar.');
        }
        if ($file['size'] <= 0 || $file['size'] > $this->maxSize) {
            throw new \Exception('Ukuran bukti bayar maksimal 5MB.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExt, true)) {
            throw new \Exception('Bukti bayar harus berformat PDF, JPG, atau PNG.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('Upload bukti bayar tidak valid.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $this->allowedMime, true)) {
            throw new \Exception('Isi file bukti bayar tidak sesuai format PDF/JPG/PNG yang diizinkan.');
        }

        $destDir = $this->f3->get('ROOT') . '/storage/pembayaran/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $destDir . $safeName)) {
            throw new \Exception('Gagal menyimpan file bukti bayar ke server.');
        }

        return $safeName;
    }

    protected function isMaskClientNameEnabled() {
        try {
            if (class_exists('OptiFieldConfig')) {
                $fieldConfigModel = new OptiFieldConfig($this->db);
                return $fieldConfigModel->isMaskClientNameEnabled();
            }
        } catch (\Exception $e) {
            // diamkan, default ke false
        }
        return false;
    }

    protected function safeQuery($sql, $params = []) {
        try {
            return $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }
}