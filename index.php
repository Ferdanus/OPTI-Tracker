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
$f3->route('GET /order/@id/edit', 'OrderController->edit');
$f3->route('POST /order/@id/update', 'OrderController->update');
$f3->route('POST /order/@id/hapus', 'OrderController->hapus');
$f3->route('POST /order/@id/approve', 'OrderController->approve');
$f3->route('POST /order/@id/tolak', 'OrderController->tolak');

// ==========================================
// ROUTE MODUL PEMBAYARAN MULTI-TERMIN
// ==========================================
$f3->route('GET /pembayaran', 'PembayaranController->index');
$f3->route('GET /pembayaran/tambah', 'PembayaranController->tambah');
$f3->route('POST /pembayaran/simpan', 'PembayaranController->simpan');
$f3->route('POST /pembayaran/@id/hapus', 'PembayaranController->hapus');

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

// ==========================================
// ROUTE MODUL KONTRAK PKS
// ==========================================
$f3->route('GET /kontrak', 'KontrakController->index');
$f3->route('GET /kontrak/tambah', 'KontrakController->tambah');
$f3->route('POST /kontrak/simpan', 'KontrakController->simpan');
$f3->route('GET /kontrak/@id/edit', 'KontrakController->edit');
$f3->route('POST /kontrak/@id/update', 'KontrakController->update');
$f3->route('POST /kontrak/@id/hapus', 'KontrakController->hapus');

// ==========================================
// ROUTE PENGATURAN KONFIGURASI DINAMIS & PRIVASI
// ==========================================
$f3->route('GET /config', 'ConfigController->index');
$f3->route('GET /pengaturan', 'ConfigController->index');
$f3->route('POST /config/field/@id/update', 'ConfigController->updateField');
$f3->route('POST /config/toggle-masking', 'ConfigController->toggleMasking');
$f3->route('POST /config/set-ketua-tim', 'ConfigController->setKetuaTim');

// Jalankan aplikasi Fat-Free Framework
$f3->run();
