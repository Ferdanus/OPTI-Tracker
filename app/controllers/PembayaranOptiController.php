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

    protected $allowedExt  = ['pdf'];
protected $allowedMime = ['application/pdf'];
    protected $maxSize     = 5242880; // 5MB

    /** GET /pembayaran */
    /** GET /pembayaran */
public function index($f3) {
    $this->requirePermission('pembayaran:view', '/dashboard');
    $userRole    = $this->getUserRole();
    $isKetuaTim  = ($userRole === 'ketua_tim');
    $divisiKatim = $_SESSION['jenis_layanan_opti'] ?? '';

    // ---- Tab 1: Baru Masuk ----
    $sqlBaru = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, o.tanggal_masuk,
               o.jenis_layanan_opti,
               c.nmcustomer AS nama_perusahaan, c.pt_cv,
               sp.id AS sp_id, sp.surat_kesanggupan_bayar
        FROM order_layanan o
        JOIN tb_customer c ON o.id_customer = c.id_customer
        LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
        WHERE o.status_penawaran = 'deal'
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

    // ---- Tab 2 & 3 digabung -- basis opti_pembayaran LANGSUNG, bukan status_keuangan ----
    $sqlPunyaPembayaran = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, o.created_at,
                                  o.jenis_layanan_opti, o.disposisi_katim_at,
                                  c.nmcustomer AS nama_perusahaan, c.pt_cv,
                                  sp.id AS sp_id, sp.surat_kesanggupan_bayar, sp.nominal_penawaran,
                                  COALESCE((SELECT SUM(p.jumlah) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi'), 0) AS total_terbayar,
                                  COALESCE((SELECT COUNT(p.id) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.is_kesanggupan_bayar = 0), 0) AS jumlah_termin,
                                  COALESCE((SELECT MAX(p.is_kesanggupan_bayar) FROM opti_pembayaran p WHERE p.order_id = o.id), 0) AS punya_kesanggupan_bayar,
                                  (SELECT MAX(p.tanggal_bayar) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi') AS tanggal_bayar_terakhir
                           FROM order_layanan o
                           JOIN tb_customer c ON o.id_customer = c.id_customer
                           LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                           WHERE o.id IN (SELECT DISTINCT order_id FROM opti_pembayaran)";
    $rowsPembayaran = $this->safeQuery($sqlPunyaPembayaran);

    $daftarBerjalan = [];
    $daftarLunas = [];

    foreach ($rowsPembayaran as $o) {
        $biayaAcuan = !empty($o['nominal_penawaran']) ? (float) $o['nominal_penawaran'] : (float) $o['estimasi_biaya'];
        $terbayar   = (float) $o['total_terbayar'];

        if ($biayaAcuan > 0 && $terbayar >= $biayaAcuan) {
            $t = strtotime($o['tanggal_bayar_terakhir']);
            $o['biaya_acuan']   = $biayaAcuan;
            $o['tanggal_lunas'] = $o['tanggal_bayar_terakhir'];
            $o['tahun_lunas']   = $t ? (int) date('Y', $t) : null;
            $o['bulan_lunas']   = $t ? (int) date('n', $t) : null;
            $daftarLunas[] = $o;
        } else {
            $o['biaya_acuan']  = $biayaAcuan;
            $o['sisa_tagihan'] = max(0, $biayaAcuan - $terbayar);
            $o['persen_bayar'] = $biayaAcuan > 0 ? min(100, round(($terbayar / $biayaAcuan) * 100, 1)) : 0;
            $daftarBerjalan[] = $o;
        }
    }

    usort($daftarLunas, function ($a, $b) { return strtotime($b['tanggal_lunas']) <=> strtotime($a['tanggal_lunas']); });
    usort($daftarBerjalan, function ($a, $b) { return strtotime($b['created_at']) <=> strtotime($a['created_at']); });

    // [Ketua Tim] cuma boleh liat Lunas + Kesanggupan Bayar, dibatasin ke divisinya
    if ($isKetuaTim && in_array($divisiKatim, ['selulosa', 'lingkungan'], true)) {
        $daftarBaru = [];

        $daftarBerjalan = array_values(array_filter($daftarBerjalan, function ($o) use ($divisiKatim) {
            return !empty($o['punya_kesanggupan_bayar']) && $o['jenis_layanan_opti'] === $divisiKatim;
        }));

        $daftarLunas = array_values(array_filter($daftarLunas, function ($o) use ($divisiKatim) {
            return $o['jenis_layanan_opti'] === $divisiKatim;
        }));

        $daftarHistori = [];
    }

    // ---- Tab 4: Histori ----
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
        "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, o.jenis_layanan_opti, o.disposisi_katim_at,
                c.nmcustomer AS nama_perusahaan, c.pt_cv,
                sp.id AS sp_id, sp.nominal_penawaran, sp.surat_kesanggupan_bayar,
                COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id AND status_verifikasi = 'terverifikasi'), 0) AS terbayar,
                COALESCE((SELECT COUNT(id) FROM opti_pembayaran WHERE order_id = o.id AND is_kesanggupan_bayar = 0), 0) AS jumlah_termin_sebelumnya,
                COALESCE((SELECT COUNT(id) FROM opti_pembayaran WHERE order_id = o.id), 0) AS jumlah_baris_total
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
    $order['termin_berikutnya']    = (int)$order['jumlah_termin_sebelumnya'] + 1;
$order['sudah_ada_pembayaran'] = ((int)$order['jumlah_baris_total']) > 0;

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

$orderId       = (int)($post['order_id'] ?? 0);
$isKesanggupan = !empty($post['is_kesanggupan_bayar']); // [BARU] dari toggle Skip Pembayaran

if ($orderId <= 0) {
    $this->setFlashError('Order layanan tidak valid.');
    $f3->reroute('/pembayaran/tambah');
    return;
}

// ===== JALUR SKIP (Surat Kesanggupan Bayar) =====
if ($isKesanggupan) {
    $keterangan = trim($post['keterangan'] ?? '');

    try {
        $pembayaran = new OptiPembayaran($this->db);
        $pembayaran->order_id             = $orderId;
        $pembayaran->po_id                = null;
        $pembayaran->termin_ke            = 0;
        $pembayaran->tanggal_bayar        = date('Y-m-d');
        $pembayaran->jumlah               = 0;
        $pembayaran->metode_pembayaran    = null;
        $pembayaran->nomor_transaksi_ntpn = null;
        $pembayaran->keterangan           = $keterangan ?: 'Dicatat via Surat Kesanggupan Bayar.';
        $pembayaran->bukti_bayar          = null;
        $pembayaran->status_verifikasi    = 'terverifikasi';
        $pembayaran->is_kesanggupan_bayar = 1;
        $pembayaran->verifikator_id       = $this->getUserId() ?? null;
        $pembayaran->created_at           = date('Y-m-d H:i:s');
        $pembayaran->save();

if (!empty($post['disposisi_langsung'])) {
    $this->lakukanDisposisiKatim($orderId, $this->getUserId());
    $this->setFlashSuccess('Order dicatat sebagai Kesanggupan Bayar & sudah didisposisikan ke Ketua Tim.');
} else {
    $this->setFlashSuccess('Order dicatat sebagai Kesanggupan Bayar. Order masuk ke tab "Sedang Berjalan".');
}
$f3->reroute('/pembayaran');
    } catch (\Exception $e) {
        $this->setFlashError('Gagal mencatat: ' . $e->getMessage());
        $f3->reroute('/pembayaran/tambah?order_id=' . $orderId);
    }
    return;
}

// ===== JALUR NORMAL (pembayaran beneran) =====
$terminKe     = (int)($post['termin_ke'] ?? 1);
$tanggalBayar = $post['tanggal_bayar'] ?? date('Y-m-d');
$jumlah       = (float)($post['jumlah'] ?? 0);
$metode       = trim($post['metode_pembayaran'] ?? 'transfer_bank');
$noTransaksi  = trim($post['nomor_transaksi_ntpn'] ?? '');
$keterangan   = trim($post['keterangan'] ?? '');

if ($jumlah <= 0) {
    $this->setFlashError('Masukkan nominal pembayaran valid.');
    $f3->reroute('/pembayaran/tambah?order_id=' . $orderId);
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

if ($statusBaru === 'lunas' && !empty($post['disposisi_langsung'])) {
    $this->lakukanDisposisiKatim($orderId, $this->getUserId());
    $pesanSukses = 'Pembayaran tercatat. Order dinyatakan LUNAS & sudah didisposisikan ke Ketua Tim.';
} elseif ($statusBaru === 'lunas') {
    $pesanSukses = 'Pembayaran tercatat. Order dinyatakan LUNAS.';
} else {
    $pesanSukses = 'Pembayaran termin tercatat.';
}

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

    protected function lakukanDisposisiKatim(int $orderId, ?int $userId): void {
        $rows = $this->safeQuery(
            "SELECT o.nomor_order, o.jenis_layanan_opti, c.nmcustomer AS nama_perusahaan
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer
             WHERE o.id = ?",
            [$orderId]
        );
        if (empty($rows)) return;
        $order = $rows[0];
    
        $this->db->exec(
            "UPDATE order_layanan SET disposisi_katim_at = NOW(), disposisi_katim_oleh = ? WHERE id = ?",
            [1 => $userId, 2 => $orderId]
        );
    
        try {
            \NotificationService::send($this->db, [
                'order_id'        => $orderId,
                'target_role'     => 'ketua_tim',
                'target_layanan'  => $order['jenis_layanan_opti'] ?? 'semua',
                'judul'           => 'Pembayaran Order Sudah Deal',
                'pesan'           => "Order #{$order['nomor_order']} ({$order['nama_perusahaan']}) sudah dinyatakan Deal dari sisi keuangan. Silakan cek halaman Pembayaran untuk detail.",
                'tipe'            => 'success',
                'icon'            => 'bi-cash-coin',
                'link_url'        => "/pembayaran",
                'created_by'      => $userId,
                'created_by_name' => $_SESSION['nama_lengkap'] ?? 'Bagian Keuangan',
            ]);
        } catch (\Exception $eNotif) {}
    }
    /** POST /pembayaran/@id/disposisi-katim */
    public function disposisiKatim($f3, $params) {
        $this->requirePermission('pembayaran:view', '/dashboard');
        $orderId = (int)($params['id'] ?? 0);
        $this->lakukanDisposisiKatim($orderId, $this->getUserId());
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
}