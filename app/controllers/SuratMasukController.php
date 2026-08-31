<?php

/**
 * Controller SuratMasukController
 * Modul Integrasi Surat Masuk untuk Tim Mitra
 */
class SuratMasukController extends Controller {

    protected $repo;

    public function __construct() {
        parent::__construct();
        $this->repo = new \SuratMasukRepository($this->db, $this->dbSekretariat);
    }

    /**
     * Tampilkan daftar surat masuk yang siap diklaim & daftar klaim aktif
     */
    public function index() {
        $this->requireAuth();
        $this->requirePermission('surat_masuk:view');

        $userId = $this->getUserId();
        $daftarSurat = array();
        $daftarKlaim = array();
        $daftarRiwayat = array();
        $errorMessage = null;
        $searchQ = trim($this->f3->get('GET.q') ?? '');

        if (!$this->repo->isConnected()) {
            $errorMessage = "Database sekretariat belum terhubung atau sedang offline. Silakan periksa konfigurasi di config.ini.";
        } else {
            try {
                $daftarSurat   = $this->repo->getDaftarSuratOpti();
                $daftarKlaim   = $this->repo->getDaftarPermintaanMasuk($this->getUserRole() === 'superadmin' ? null : $userId);
                $daftarRiwayat = $this->repo->getDaftarRiwayatSurat();

                // Apply search filter if query is present
                if (!empty($searchQ)) {
                    $qLower = strtolower($searchQ);
                    $daftarSurat = array_values(array_filter($daftarSurat, function($s) use ($qLower) {
                        return strpos(strtolower($s['nomor_surat'] ?? ''), $qLower) !== false
                            || strpos(strtolower($s['pengirim'] ?? ''), $qLower) !== false
                            || strpos(strtolower($s['perihal'] ?? ''), $qLower) !== false
                            || strpos(strtolower($s['pic_pengirim'] ?? ''), $qLower) !== false;
                    }));

                    $daftarKlaim = array_values(array_filter($daftarKlaim, function($k) use ($qLower) {
                        return strpos(strtolower($k['nomor_order'] ?? ''), $qLower) !== false
                            || strpos(strtolower($k['nmcustomer'] ?? ''), $qLower) !== false
                            || strpos(strtolower($k['judul_kegiatan'] ?? ''), $qLower) !== false
                            || strpos(strtolower($k['pic'] ?? ''), $qLower) !== false;
                    }));
                }
            } catch (\Exception $e) {
                $errorMessage = "Gagal mengambil data surat: " . $e->getMessage();
            }
        }

        $this->f3->set('daftar_surat', $daftarSurat);
        $this->f3->set('daftar_klaim', $daftarKlaim);
        $this->f3->set('daftar_riwayat', $daftarRiwayat);
        $this->f3->set('total_surat_tersedia', count($daftarSurat));
        $this->f3->set('total_klaim_aktif', count($daftarKlaim));
        $this->f3->set('total_klaim_selesai', count($daftarRiwayat));
        $this->f3->set('search_q', $searchQ);
        $this->f3->set('error_message', $errorMessage);

        $this->render('surat_masuk/index.html', 'Kotak Masuk Permohonan - Tim Mitra', 'surat_masuk');
    }

    /**
     * Aksi Klaim Surat (POST)
     */
    public function klaim() {
        $this->requireAuth();
        $this->requirePermission('surat_masuk:klaim');

        $suratId = (int)($this->f3->get('POST.surat_id') ?? 0);
        $userId  = $this->getUserId();

        if ($suratId <= 0) {
            $this->setFlashError('ID Surat tidak valid.');
            $this->f3->reroute('/surat-masuk');
            return;
        }

        try {
            $orderId = $this->repo->klaimSurat($suratId, $userId);
            $this->setFlashSuccess('Surat berhasil diterima.');
            $this->f3->reroute('/surat-masuk');
            return;
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menerima surat: ' . $e->getMessage());
            $this->f3->reroute('/surat-masuk');
        }
    }

    /**
     * Aksi Batalkan Klaim (POST)
     */
    public function batalkanKlaim() {
        $this->requireAuth();
        $this->requirePermission('surat_masuk:batal');

        $orderId = (int)($this->f3->get('POST.order_id') ?? 0);
        $userId  = $this->getUserId();

        if ($orderId <= 0) {
            $this->setFlashError('ID Order tidak valid.');
            $this->f3->reroute('/surat-masuk');
            return;
        }

        try {
            $this->repo->batalkanKlaim($orderId, $userId);
            $this->setFlashSuccess('Klaim surat berhasil dibatalkan. Status surat telah dikembalikan ke daftar surat masuk.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal membatalkan klaim: ' . $e->getMessage());
        }

        $this->f3->reroute('/surat-masuk');
    }

    /**
     * Alias method untuk batalkanKlaim
     */
    public function batalKlaim() {
        $this->batalkanKlaim();
    }

    /**
     * Alias method untuk previewPdf
     */
    public function previewPdf() {
        $this->viewPdf();
    }

    /**
     * Detail Surat Masuk JSON untuk Pratinjau Dokumen In-App Bebas Intervensi IDM
     */
    public function detailJson() {
        $this->requireAuth();
        $this->requirePermission('surat_masuk:view');

        $suratId = (int)$this->f3->get('PARAMS.id');
        if ($suratId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID Surat tidak valid']);
            exit;
        }

        $surat = $this->repo->getSuratById($suratId);
        if (!$surat) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Surat tidak ditemukan']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $surat['id'],
                'nomor_surat' => $surat['nomor_surat'],
                'tanggal_surat' => date('d F Y', strtotime($surat['tanggal_surat'])),
                'pengirim' => $surat['pengirim'],
                'pt_cv' => $surat['pt_cv'],
                'nama_instansi' => ($surat['pt_cv'] ? $surat['pt_cv'] . ' ' : '') . $surat['pengirim'],
                'alamat_pengirim' => $surat['alamat_pengirim'] ?: 'Kawasan Industri Terpadu, Indonesia',
                'no_telp_pengirim' => $surat['no_telp_pengirim'] ?: '-',
                'email_pengirim' => $surat['email_pengirim'] ?: '-',
                'pic_pengirim' => $surat['pic_pengirim'] ?: 'Penanggung Jawab Teknis',
                'perihal' => $surat['perihal'],
                'file_path' => $surat['file_path'],
                'layanan' => $surat['layanan']
            ]
        ]);
        exit;
    }

    /**
     * Tampilkan Berkas PDF Surat Masuk secara langsung (Bypass Cache & Always Fresh)
     */
    public function viewPdf() {
        $this->requireAuth();
        $this->requirePermission('surat_masuk:view');

        $suratId = (int)$this->f3->get('PARAMS.id');
        if ($suratId <= 0) {
            $this->f3->error(404, 'Surat tidak ditemukan.');
            return;
        }

        $surat = $this->repo->getSuratById($suratId);
        if (!$surat) {
            $this->f3->error(404, 'Data surat permohonan tidak ditemukan.');
            return;
        }

        $filePath = 'c:/xampp/htdocs/Mini OPTI Tracker/' . ltrim($surat['file_path'], "/\\");
        if (!file_exists($filePath)) {
            // Generate clean structured official letter
            require_once 'c:/xampp/htdocs/Mini OPTI Tracker/app/helpers/fpdf/fpdf.php';
            $pdf = new \FPDF('P', 'mm', 'A4');
            $pdf->SetMargins(20, 15, 20);
            $pdf->AddPage();
            
            // 1. KOP SURAT
            $pdf->SetFont('Arial', 'B', 13);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 6, strtoupper(($surat['pt_cv'] ? $surat['pt_cv'].' ' : '').$surat['pengirim']), 0, 1, 'C');
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
            $pdf->Cell(80, 5, $surat['nomor_surat'], 0, 0);
            $pdf->SetFont('Arial', '', 9.5);
            $pdf->Cell(0, 5, 'Bandung, ' . date('d F Y', strtotime($surat['tanggal_surat'])), 0, 1, 'R');

            $pdf->Cell(20, 5, 'Lampiran', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->Cell(0, 5, '1 (satu) berkas spesifikasi teknis', 0, 1);

            $pdf->Cell(20, 5, 'Hal', 0, 0);
            $pdf->Cell(4, 5, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->MultiCell(0, 5, $surat['perihal'], 0, 'L');
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
            $pdf->Cell(0, 4.8, ($surat['pt_cv'] ? $surat['pt_cv'].' ' : '').$surat['pengirim'], 0, 1);
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
            $pdf->Cell(38, 4.8, 'a. Judul Kegiatan', 0, 0);
            $pdf->Cell(4, 4.8, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->MultiCell(0, 4.8, $surat['perihal'], 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(8, 4.8, '', 0, 0);
            $pdf->Cell(38, 4.8, 'b. Layanan Dimohon', 0, 0);
            $pdf->Cell(4, 4.8, ':', 0, 0);
            $pdf->Cell(0, 4.8, 'Layanan Optimalisasi Teknologi Industri (OPTI)', 0, 1);
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
            $pdf->Cell(80, 4.5, ($surat['pt_cv'] ? $surat['pt_cv'].' ' : '').$surat['pengirim'], 0, 1, 'C');
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

            $fileDir = dirname($filePath);
            if (!is_dir($fileDir)) @mkdir($fileDir, 0777, true);
            $pdf->Output('F', $filePath);
        }

        // Send fresh anti-cache headers and stream PDF inline
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        exit;
    }

    /**
     * Halaman Portal Simulasi Sekretariat (Kirim Surat Permohonan Masuk)
     * Route: GET /simulasi-sekretariat
     */
    public function simulasiSekretariat() {
        $this->requirePermission('surat_masuk:registrasi', '/order');
        
        $table = $this->f3->get('db_sekretariat_table') ?: 'surat_masuk';
        $daftarSuratSimulasi = array();
        
        try {
            $sql = "SELECT s.*, o.id as order_id, o.nomor_order, o.status as order_status, o.jenis_layanan_opti
                    FROM `{$table}` s
                    LEFT JOIN `order_layanan` o ON s.id = o.id_surat_masuk
                    ORDER BY s.id DESC";
            $daftarSuratSimulasi = $this->db->exec($sql);
        } catch (\Exception $e) {
            $daftarSuratSimulasi = $this->db->exec("SELECT * FROM `{$table}` ORDER BY id DESC");
        }

        $this->f3->set('daftar_surat_simulasi', $daftarSuratSimulasi);
        $this->f3->set('total_surat', count($daftarSuratSimulasi));
        
        $this->render('surat_masuk/simulasi.html', 'Portal Simulasi Sekretariat - Kirim Surat Masuk', 'simulasi_sekretariat');
    }

    /**
     * Proses kirim surat masuk dari simulasi sekretariat (POST)
     * Route: POST /simulasi-sekretariat/kirim
     */
    public function kirimSimulasi() {
        $this->requireAuth();
        
        $table = $this->f3->get('db_sekretariat_table') ?: 'surat_masuk';
        $post = $this->f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        $nomorSurat = trim($post['nomor_surat'] ?? '');
        $pengirim = trim($post['pengirim'] ?? '');
        $perihal = trim($post['perihal'] ?? '');
        $ptCv = trim($post['pt_cv'] ?? 'PT');
        $alamat = trim($post['alamat_pengirim'] ?? '');
        $pic = trim($post['pic_pengirim'] ?? '');
        $telp = trim($post['no_telp_pengirim'] ?? '');
        $email = trim($post['email_pengirim'] ?? '');
        $tglSurat = !empty($post['tanggal_surat']) ? $post['tanggal_surat'] : date('Y-m-d');
        $namaPengirim = trim($post['nama_pengirim'] ?? $pic);

        if (empty($nomorSurat) || empty($pengirim) || empty($perihal)) {
            $this->setFlashError('Nomor surat, nama instansi pengirim, dan perihal wajib diisi.');
            $this->f3->reroute('/simulasi-sekretariat');
            return;
        }

        // Handle file upload if any
        $filePath = '';
        $files = $this->f3->get('FILES.file_dokumen');
        if (!empty($files['name']) && $files['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'c:/xampp/htdocs/Mini OPTI Tracker/uploads/surat_masuk/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));
            $newFileName = 'surat_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'], $uploadDir . $newFileName)) {
                $filePath = 'uploads/surat_masuk/' . $newFileName;
            }
        }

        $layanan = trim($post['layanan'] ?? 'opti');
        if (empty($layanan)) $layanan = 'opti';

        try {
            $sql = "INSERT INTO `{$table}` (
                        nomor_surat, pengirim, pt_cv, alamat_pengirim, 
                        pic_pengirim, no_telp_pengirim, email_pengirim, 
                        tanggal_surat, nama_pengirim, perihal, file_path, 
                        layanan, status_ambil, created_at, created_by
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, ?, ?, 
                        ?, ?, ?, ?, 
                        ?, 'belum', NOW(), ?
                    )";
            $params = [
                1 => $nomorSurat,
                2 => $pengirim,
                3 => $ptCv,
                4 => $alamat,
                5 => $pic,
                6 => $telp,
                7 => $email,
                8 => $tglSurat,
                9 => $namaPengirim,
                10 => $perihal,
                11 => $filePath,
                12 => $layanan,
                13 => $userId
            ];
            $this->db->exec($sql, $params);

            $this->setFlashSuccess("
                Surat Permohonan dari <strong>{$pengirim}</strong> (No: <strong>{$nomorSurat}</strong>) berhasil didaftarkan dalam Buku Agenda Sekretariat dan diteruskan ke antrean Kotak Masuk Tim Kemitraan.<br>
                <div class='mt-2'>
                    <a href='{$this->f3->get('BASE')}/surat-masuk' class='btn btn-primary btn-sm fw-semibold text-white shadow-sm'>
                        <i class='bi bi-arrow-right-circle me-1'></i> Buka Kotak Masuk Tim Kemitraan (Tinjau &amp; Klaim Surat)
                    </a>
                </div>
            ");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal mendaftarkan surat masuk: ' . $e->getMessage());
        }

        $this->f3->reroute('/simulasi-sekretariat');
    }

    /**
     * Hapus surat simulasi jika untuk keperluan reset demo
     * Route: POST /simulasi-sekretariat/@id/hapus
     */
    public function hapusSimulasi($f3, $params) {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $table = $this->f3->get('db_sekretariat_table') ?: 'surat_masuk';

        if ($id > 0) {
            $this->db->exec("DELETE FROM `{$table}` WHERE id = ?", [1 => $id]);
            $this->setFlashSuccess('Surat simulasi berhasil dihapus dari sistem.');
        }

        $f3->reroute('/simulasi-sekretariat');
    }
}