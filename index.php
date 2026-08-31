<?php

// Pastikan file autoload composer dimuat
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Inisialisasi Session Native PHP dengan cookie path global '/' kompatibel PHP 7.2+
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/', '', false, true);
    session_start();
}

// Inisialisasi Fat-Free Framework
$f3 = \Base::instance();

// Muat file konfigurasi
$f3->config('config.ini');

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Penanganan URL dan BASE Path untuk folder dengan spasi di Apache (misal: 'Mini OPTI Tracker')
if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_URI'])) {
    $rawUri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if (!empty($scriptDir) && strpos($rawUri, $scriptDir) === 0) {
        $realPath = substr($rawUri, strlen($scriptDir)) ?: '/';
        $f3->set('BASE', '/Mini%20OPTI%20Tracker');
        $f3->set('PATH', $realPath);
    }
}

// Setup koneksi PDO Database melalui SQL Wrapper F3
try {
    $db = new \DB\SQL(
        $f3->get('db_dns'),
        $f3->get('db_user'),
        $f3->get('db_pass')
    );
    // Simpan object database ke hive F3 agar bisa diakses global
    $f3->set('DB', $db);

    // Setup koneksi kedua (Database Eksternal Sekretariat untuk Surat Masuk)
    try {
        if ($f3->exists('db_sekretariat_dns') && $f3->get('db_sekretariat_dns')) {
            $dbSekretariat = new \DB\SQL(
                $f3->get('db_sekretariat_dns'),
                $f3->get('db_sekretariat_user'),
                $f3->get('db_sekretariat_pass')
            );
            $f3->set('DB_SEKRETARIAT', $dbSekretariat);
        } else {
            $f3->set('DB_SEKRETARIAT', null);
        }
    } catch (\Exception $eSekretariat) {
        // Jangan gagalkan seluruh aplikasi jika DB eksternal sedang offline
        error_log("DB Sekretariat Connection Failed: " . $eSekretariat->getMessage());
        $f3->set('DB_SEKRETARIAT', null);
        $f3->set('DB_SEKRETARIAT_ERROR', $eSekretariat->getMessage());
    }
} catch (\Exception $e) {
    // Tangani jika koneksi database gagal
    echo '<div style="font-family: sans-serif; padding: 20px; background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px; margin: 20px;">';
    echo '<h3 style="color: #991b1b; margin-top: 0;">Gagal Terhubung ke Database MySQL</h3>';
    echo '<p style="color: #7f1d1d;">Pastikan service MySQL di XAMPP sudah berjalan dan konfigurasi database di <code>config.ini</code> sudah benar.</p>';
    echo '<p style="color: #7f1d1d; font-size: 0.85rem;"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
    exit;
}

// Handle global error page
$f3->set('ONERROR', function($f3) {
    $errorCode = $f3->get('ERROR.code');
    $errorText = $f3->get('ERROR.text');

    echo '<div style="font-family: sans-serif; padding: 30px; background: #fff1f2; border-left: 5px solid #e11d48; margin: 20px; border-radius: 8px;">';
    echo '<h2 style="color: #9f1239; margin-top: 0;">Terjadi Kesalahan Aplikasi (' . $errorCode . ')</h2>';
    echo '<p style="color: #4c0519; font-size: 1.1rem;">' . htmlspecialchars($errorText) . '</p>';
    if ($f3->get('DEBUG') > 0) {
        echo '<pre style="background: #ffffff; padding: 15px; border-radius: 4px; overflow-x: auto; border: 1px solid #e2e8f0;">' . htmlspecialchars($f3->get('ERROR.trace')) . '</pre>';
    }
    echo '<a href="' . $f3->get('BASE') . '/po" style="display: inline-block; padding: 8px 16px; background: #9f1239; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 10px;">Kembali ke Beranda</a>';
    echo '</div>';
});

// Route Beranda -> redirect ke /po
$f3->route('GET /', function($f3) {
    $f3->reroute('/po');
});

// ==========================================
// ROUTE AUTENTIKASI & PROFIL
// ==========================================
$f3->route('GET /login', 'AuthController->loginGet');
$f3->route('POST /login', 'AuthController->loginPost');
$f3->route('GET /login/switch/@id', 'AuthController->quickLogin');
$f3->route('POST /login/switch', 'AuthController->quickLogin');
$f3->route('GET /logout', 'AuthController->logout');
$f3->route('POST /logout', 'AuthController->logout');
$f3->route('GET /profil', 'AuthController->profileGet');
$f3->route('POST /profil/simpan', 'AuthController->profilePost');

// ==========================================
// ROUTE MODUL CUSTOMER / KLIEN (tb_customer)
// ==========================================
$f3->route('GET /klien', 'CustomerController->index');
$f3->route('GET /klien/tambah', 'CustomerController->tambah');
$f3->route('POST /klien/simpan', 'CustomerController->simpan');
$f3->route('GET /klien/@id/edit', 'CustomerController->edit');
$f3->route('POST /klien/@id/update', 'CustomerController->update');
$f3->route('POST /klien/@id/hapus', 'CustomerController->hapus');

$f3->route('GET /customer', 'CustomerController->index');
$f3->route('GET /customer/tambah', 'CustomerController->tambah');
$f3->route('POST /customer/simpan', 'CustomerController->simpan');
$f3->route('GET /customer/@id/edit', 'CustomerController->edit');
$f3->route('POST /customer/@id/update', 'CustomerController->update');
$f3->route('POST /customer/@id/hapus', 'CustomerController->hapus');

// ==========================================
// ROUTE MODUL ORDER LAYANAN
// ==========================================
$f3->route('GET /order', 'OrderController->index');
$f3->route('GET /order/tambah', 'OrderController->tambah');
$f3->route('POST /order/simpan', 'OrderController->simpan');
$f3->route('GET /order/@id', 'OrderController->detail');
$f3->route('GET /order/@id/edit', 'OrderController->edit');
$f3->route('POST /order/@id/update', 'OrderController->update');
$f3->route('POST /order/@id/klien/update', 'OrderController->updateCustomer');
$f3->route('POST /order/@id/hapus', 'OrderController->hapus');
$f3->route('POST /order/@id/disposisi', 'OrderController->disposisi');
$f3->route('POST /order/@id/approve', 'OrderController->approve');
$f3->route('POST /order/@id/tolak', 'OrderController->tolak');
$f3->route('GET /order/@id/tinjauan', 'OrderController->tinjauan');
$f3->route('POST /order/@id/tinjauan', 'OrderController->tinjauanPost');
$f3->route('GET /order/@id/biaya-proposal', 'OrderController->biayaProposal');
$f3->route('POST /order/@id/biaya-proposal', 'OrderController->biayaProposalPost');
$f3->route('GET /order/@id/rancop-selulosa', 'OrderController->rancopSelulosa');
$f3->route('POST /order/@id/rancop-selulosa', 'OrderController->simpanRancopSelulosa');
$f3->route('GET /order/@id/biaya-lingkungan', 'OrderController->biayaLingkungan');
$f3->route('POST /order/@id/biaya-lingkungan', 'OrderController->biayaLingkunganPost');

// ==========================================
// ROUTE MODUL PEMBAYARAN MULTI-TERMIN & INVOICE
// ==========================================
$f3->route('GET /pembayaran', 'PembayaranController->index');
$f3->route('GET /pembayaran/tambah', 'PembayaranController->tambah');
$f3->route('POST /pembayaran/simpan', 'PembayaranController->simpan');
$f3->route('POST /pembayaran/@id/hapus', 'PembayaranController->hapus');
$f3->route('GET /order/@id/invoice/buat', 'PembayaranController->invoiceForm');
$f3->route('POST /order/@id/invoice/simpan', 'PembayaranController->invoiceSimpan');
$f3->route('GET /order/@id/pembayaran/tambah', 'PembayaranController->tambahDariOrder');
$f3->route('POST /order/@id/pembayaran/simpan', 'PembayaranController->simpanDariOrder');

// ==========================================
// ROUTE MODUL BAST & PENUTUPAN ORDER
// ==========================================
$f3->route('GET /order/@id/bast/buat', 'BastController->formBast');
$f3->route('POST /order/@id/bast/simpan', 'BastController->simpanBast');
$f3->route('POST /order/@id/bast/tutup', 'BastController->tutupOrder');

// ==========================================
// ROUTE MODUL PO (DASHBOARD & MONITORING)
// ==========================================
$f3->route('GET /po', 'PoController->index');
$f3->route('GET /po/ekspor', 'PoController->ekspor');
$f3->route('GET /po/@id', 'PoController->detail');
$f3->route('POST /po/@id/update', 'PoController->update');
$f3->route('POST /po/@id/hapus', 'PoController->hapus');

// Map Kendali, SOP 19 Tahap, RAB, Jadwal Tim, Evaluasi
$f3->route('POST /po/@id/sop/@tahap/verifikasi', 'PoController->verifikasiSopTahap');
$f3->route('POST /po/@id/sop/@tahap/revisi', 'PoController->revisiSopTahap');
$f3->route('POST /po/@id/sop/skip-perkembangan', 'PoController->skipSopPerkembangan');
$f3->route('POST /po/@id/map/@stage', 'PoController->approveMap');
$f3->route('POST /po/@id/rab/tambah', 'PoController->tambahRab');
$f3->route('POST /po/@id/rab/@rab_id/hapus', 'PoController->hapusRab');
$f3->route('POST /po/@id/jadwal/tambah', 'PoController->tambahJadwal');
$f3->route('POST /po/@id/jadwal/@jadwal_id/status', 'PoController->updateJadwalStatus');
$f3->route('POST /po/@id/jadwal/@jadwal_id/hapus', 'PoController->hapusJadwal');
$f3->route('POST /po/@id/evaluasi', 'PoController->updateEvaluasi');
$f3->route('POST /po/@id/laporan/upload', 'PoController->uploadLaporan');

// ==========================================
// ROUTE MODUL KONTRAK PKS
// ==========================================
$f3->route('GET /kontrak', 'KontrakController->index');
$f3->route('GET /kontrak/tambah', 'KontrakController->tambah');
$f3->route('POST /kontrak/simpan', 'KontrakController->simpan');
$f3->route('GET /kontrak/@id/edit', 'KontrakController->edit');
$f3->route('POST /kontrak/@id/update', 'KontrakController->update');
$f3->route('POST /kontrak/@id/hapus', 'KontrakController->hapus');
$f3->route('GET /po/@id/kontrak/buat', 'KontrakController->tambahDariPo');
$f3->route('POST /po/@id/kontrak/simpan', 'KontrakController->simpanDariPo');

// ==========================================
// ROUTE PENGATURAN KONFIGURASI DINAMIS & PRIVASI
// ==========================================
$f3->route('GET /config', 'ConfigController->index');
$f3->route('GET /pengaturan', 'ConfigController->index');
$f3->route('POST /config/field/@id/update', 'ConfigController->updateField');
$f3->route('POST /config/toggle-masking', 'ConfigController->toggleMasking');
$f3->route('POST /config/set-ketua-tim', 'ConfigController->setKetuaTim');

// ==========================================
// ROUTE ADMIN_ORDER
// ==========================================
$f3->route('GET /admin-order', 'DashboardController->adminOrder');
$f3->route('GET /kategori-uji', 'KategoriUjiController->index');
$f3->route('POST /kategori-uji/simpan', 'KategoriUjiController->simpan');
$f3->route('POST /kategori-uji/update', 'KategoriUjiController->update');
$f3->route('POST /kategori-uji/hapus', 'KategoriUjiController->hapus');

$f3->route('GET /metode-uji', 'MetodeUjiController->index');
$f3->route('POST /metode-uji/simpan', 'MetodeUjiController->simpan');
$f3->route('POST /metode-uji/update', 'MetodeUjiController->update');
$f3->route('POST /metode-uji/hapus', 'MetodeUjiController->hapus');

$f3->route('GET /pengujian-eksternal', 'PengujianEksternalController->index');
$f3->route('POST /pengujian-eksternal/simpan', 'PengujianEksternalController->simpan');
$f3->route('POST /pengujian-eksternal/update', 'PengujianEksternalController->update');
$f3->route('POST /pengujian-eksternal/hapus', 'PengujianEksternalController->hapus');

// ==========================================
// ROUTE SURAT PENAWARAN
// ==========================================
$f3->route('GET /surat-penawaran', 'SuratPenawaranController->index');
$f3->route('GET /surat-penawaran/tambah', 'SuratPenawaranController->tambah');
$f3->route('POST /surat-penawaran/simpan', 'SuratPenawaranController->simpan');
$f3->route('GET /surat-penawaran/@id/edit', 'SuratPenawaranController->edit');
$f3->route('POST /surat-penawaran/@id/update', 'SuratPenawaranController->update');
$f3->route('POST /surat-penawaran/@id/hapus', 'SuratPenawaranController->hapus');
$f3->route('GET /order/@id/penawaran/buat', 'SuratPenawaranController->buatDariOrder');
$f3->route('POST /order/@id/penawaran/simpan', 'SuratPenawaranController->simpanDariOrder');
$f3->route('GET /order/@id/penawaran/cetak', 'SuratPenawaranController->cetakPdf');
$f3->route('POST /order/@id/penawaran/status', 'SuratPenawaranController->updateStatusKlien');

// ==========================================
// ROUTE PROPOSAL
// ==========================================
$f3->route('GET /proposal', 'SuratPenawaranController->proposal');
$f3->route('GET /proposal/tambah', 'SuratPenawaranController->tambahProposal');
$f3->route('POST /proposal/simpan', 'SuratPenawaranController->simpanProposal');
$f3->route('GET /proposal/@id/edit', 'SuratPenawaranController->editProposal');
$f3->route('POST /proposal/@id/update', 'SuratPenawaranController->updateProposal');
$f3->route('POST /proposal/@id/hapus', 'SuratPenawaranController->hapusProposal');

// ==========================================
// ROUTE MODUL SURAT MASUK (INTEGRASI SEKRETARIAT)
// ==========================================
$f3->route('GET /surat-masuk', 'SuratMasukController->index');
$f3->route('POST /surat-masuk/klaim', 'SuratMasukController->klaim');
$f3->route('POST /surat-masuk/batal', 'SuratMasukController->batalKlaim');
$f3->route('GET /surat-masuk/@id/pdf', 'SuratMasukController->previewPdf');
$f3->route('GET /surat-masuk/@id/detail', 'SuratMasukController->detailJson');

// ==========================================
// ROUTE SIMULASI SEKRETARIAT (DEMO PRESENTASI)
// ==========================================
$f3->route('GET /simulasi-sekretariat', 'SuratMasukController->simulasiSekretariat');
$f3->route('POST /simulasi-sekretariat/kirim', 'SuratMasukController->kirimSimulasi');
$f3->route('POST /simulasi-sekretariat/@id/hapus', 'SuratMasukController->hapusSimulasi');

// Jalankan Fat-Free Framework Router
$f3->run();