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

        $search       = trim($f3->get('GET.q') ?? '');
        $filterJenis  = trim($f3->get('GET.jenis_layanan') ?? '');
        $filterStatus = trim($f3->get('GET.status_pembayaran') ?? ''); // '' | menunggu_pembayaran | terbayar_sebagian | lunas
        $filterTahun  = trim($f3->get('GET.tahun') ?? '');

        // [BARU] kondisi WHERE dibangun sekali, dipakai ulang di query rekap & histori
        $whereParts = [];
        $params = [];

        if ($filterStatus !== '') {
            $whereParts[] = 'o.status_keuangan = ?';
            $params[] = $filterStatus;
        } else {
            // default: sembunyikan yang sudah lunas, fokus ke yang masih aktif
            $whereParts[] = "o.status_keuangan IN ('menunggu_pembayaran', 'terbayar_sebagian')";
        }

        if (!empty($filterJenis)) {
            $whereParts[] = 'o.jenis_layanan_opti = ?';
            $params[] = $filterJenis;
        }

        if (!empty($filterTahun)) {
            $whereParts[] = 'YEAR(o.tanggal_masuk) = ?';
            $params[] = $filterTahun;
        }

        if (!empty($search)) {
            $whereParts[] = '(o.nomor_order LIKE ? OR c.nmcustomer LIKE ?)';
            $wildcard = "%{$search}%";
            $params[] = $wildcard;
            $params[] = $wildcard;
        }

        $whereClause = implode(' AND ', $whereParts);

        // ---- Tabel 1: rekap per-order ----
        $sql = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, o.tanggal_masuk,
                       o.jenis_layanan_opti, o.status_keuangan,
                       c.nmcustomer AS nama_perusahaan, c.pt_cv,
                       sp.nominal_penawaran,
                       COALESCE((SELECT SUM(p.jumlah) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi'), 0) AS total_terbayar,
                       COALESCE((SELECT COUNT(p.id) FROM opti_pembayaran p WHERE p.order_id = o.id), 0) AS jumlah_termin
                FROM order_layanan o
                JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                WHERE $whereClause
                ORDER BY o.created_at DESC";

        $daftarOrder = $this->safeQuery($sql, $params);

        $countLunas = 0; $countSebagian = 0; $countBelum = 0;
        $totalTagihan = 0; $totalTerbayar = 0;

        foreach ($daftarOrder as &$o) {
            $biayaAcuan = !empty($o['nominal_penawaran']) ? (float)$o['nominal_penawaran'] : (float)$o['estimasi_biaya'];
            $dibayar    = (float)$o['total_terbayar'];
            $sisa       = max(0, $biayaAcuan - $dibayar);
            $persen     = $biayaAcuan > 0 ? min(100, round(($dibayar / $biayaAcuan) * 100, 1)) : 0;

            $o['biaya_acuan']  = $biayaAcuan;
            $o['sisa_tagihan'] = $sisa;
            $o['persen_bayar'] = $persen;

            if ($dibayar >= $biayaAcuan && $biayaAcuan > 0) {
                $o['status_tampil'] = 'lunas'; $countLunas++;
            } elseif ($dibayar > 0) {
                $o['status_tampil'] = 'sebagian'; $countSebagian++;
            } else {
                $o['status_tampil'] = 'belum'; $countBelum++;
            }

            $totalTagihan  += $biayaAcuan;
            $totalTerbayar += $dibayar;
        }
        unset($o);

        // ---- Tabel 2: histori transaksi mentah -- [DIUBAH] sekarang pakai $whereClause + $params yang SAMA ----
        $riwayatSql = "SELECT p.*, o.nomor_order, c.nmcustomer AS nama_perusahaan
                        FROM opti_pembayaran p
                        JOIN order_layanan o ON p.order_id = o.id
                        JOIN tb_customer c ON o.id_customer = c.id_customer
                        WHERE $whereClause
                        ORDER BY p.tanggal_bayar DESC, p.id DESC";
        $daftarPembayaran = $this->safeQuery($riwayatSql, $params);

        // [BARU] daftar tahun buat dropdown filter, diambil dari data yang beneran ada
        $tahunRows = $this->safeQuery(
            "SELECT DISTINCT YEAR(tanggal_masuk) AS thn FROM order_layanan WHERE tanggal_masuk IS NOT NULL ORDER BY thn DESC"
        );
        $daftarTahun = array_column($tahunRows, 'thn');

        $sisaPiutang     = max(0, $totalTagihan - $totalTerbayar);
        $persenRealisasi = $totalTagihan > 0 ? round(($totalTerbayar / $totalTagihan) * 100, 1) : 0;

        $f3->set('daftar_order', $daftarOrder);
        $f3->set('daftar_pembayaran', $daftarPembayaran);
        $f3->set('daftar_pembayaran_json', json_encode($daftarPembayaran, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_tahun', $daftarTahun);

        $f3->set('total_order', count($daftarOrder));
        $f3->set('total_tagihan', $totalTagihan);
        $f3->set('total_terbayar', $totalTerbayar);
        $f3->set('sisa_piutang', $sisaPiutang);
        $f3->set('persen_realisasi', $persenRealisasi);
        $f3->set('count_lunas', $countLunas);
        $f3->set('count_sebagian', $countSebagian);
        $f3->set('count_belum', $countBelum);

        $f3->set('search_q', $search);
        $f3->set('filter_jenis_layanan', $filterJenis);
        $f3->set('filter_status_pembayaran', $filterStatus); // [BARU]
        $f3->set('filter_tahun', $filterTahun);               // [BARU]
        $f3->set('mask_client_name', $this->isMaskClientNameEnabled());

        $this->render('keuangan/pembayaran/index.html', 'Rekapitulasi Keuangan & Pembayaran', 'pembayaran');
    }

    /** GET /pembayaran/tambah */
    public function tambah($f3) {
        $this->requirePermission('pembayaran:create', '/pembayaran');

        $orderId = (int)($f3->get('GET.order_id') ?? 0);

        $daftarOrder = $this->safeQuery(
            "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, o.status_keuangan,
                    c.nmcustomer AS nama_perusahaan,
                    sp.nominal_penawaran,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id AND status_verifikasi = 'terverifikasi'), 0) AS terbayar,
                    COALESCE((SELECT COUNT(id) FROM opti_pembayaran WHERE order_id = o.id), 0) AS jumlah_termin_sebelumnya
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer
             LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
             WHERE o.status_keuangan IN ('menunggu_pembayaran', 'terbayar_sebagian')
             ORDER BY o.id DESC"
        );

        foreach ($daftarOrder as &$ord) {
            $biayaAcuan = !empty($ord['nominal_penawaran']) ? (float)$ord['nominal_penawaran'] : (float)$ord['estimasi_biaya'];
            $ord['biaya_acuan']       = $biayaAcuan;
            $ord['sisa_tagihan']      = max(0, $biayaAcuan - (float)$ord['terbayar']);
            $ord['termin_berikutnya'] = (int)$ord['jumlah_termin_sebelumnya'] + 1;
        }
        unset($ord);

        $f3->set('daftar_order', $daftarOrder);
        $f3->set('selected_order_id', $orderId);

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

            $this->setFlashSuccess($statusBaru === 'lunas'
                ? 'Pembayaran tercatat. Order dinyatakan LUNAS.'
                : 'Pembayaran termin tercatat.');

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

    protected function validateAndStoreBuktiBayar($file) {
        if (!$file || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
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