<?php
// Mulai session untuk flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoloader via F3
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} elseif (file_exists('lib/base.php')) {
    require_once 'lib/base.php';
} else {
    die('Fat-Free Framework core tidak ditemukan di folder lib/ atau vendor/.');
}

// Normalisasi REQUEST_URI jika nama folder mengandung spasi (%20)
if (isset($_SERVER['REQUEST_URI'])) {
    $parts = explode('?', $_SERVER['REQUEST_URI'], 2);
    $cleanPath = rawurldecode($parts[0]);
    $_SERVER['REQUEST_URI'] = $cleanPath . (isset($parts[1]) ? '?' . $parts[1] : '');
}

$f3 = \Base::instance();

// Load file konfigurasi
$f3->config('config.ini');

// Inisialisasi koneksi Database ke hive F3 'DB'
try {
    $db = new \DB\SQL(
        $f3->get('db_dns'),
        $f3->get('db_user'),
        $f3->get('db_pass'),
        array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        )
    );
    $f3->set('DB', $db);
} catch (\PDOException $e) {
    $f3->set('db_error', $e->getMessage());
}

// Global Error Handler
$f3->set('ONERROR', function($f3) {
    echo '<div style="font-family: sans-serif; padding: 20px; background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px; margin: 20px;">';
    echo '<h3 style="color: #991b1b; margin-top: 0;">Terjadi Kesalahan (F3 Error)</h3>';
    echo '<p><strong>Pesan:</strong> ' . htmlspecialchars($f3->get('ERROR.text')) . ' (Kode: ' . $f3->get('ERROR.code') . ')</p>';
    if ($f3->get('DEBUG') > 1) {
        echo '<pre style="background: #ffffff; padding: 15px; border-radius: 4px; overflow-x: auto;">' . htmlspecialchars($f3->get('ERROR.trace')) . '</pre>';
    }
    echo '</div>';
});

// Route Beranda -> redirect ke /po
$f3->route('GET /', function($f3) {
    $f3->reroute('/po');
});

// ==========================================
// ROUTE MODUL KLIEN
// ==========================================
$f3->route('GET  /klien', 'KlienController->index');
$f3->route('GET  /klien/tambah', 'KlienController->tambah');
$f3->route('POST /klien/simpan', 'KlienController->simpan');

// ==========================================
// ROUTE MODUL ORDER LAYANAN
// ==========================================
$f3->route('GET  /order', 'OrderController->index');
$f3->route('GET  /order/tambah', 'OrderController->tambah');
$f3->route('POST /order/simpan', 'OrderController->simpan');
$f3->route('POST /order/@id/approve', 'OrderController->approve');
$f3->route('POST /order/@id/tolak', 'OrderController->tolak');

// ==========================================
// ROUTE MODUL PO (DASHBOARD & STATE MACHINE)
// ==========================================
$f3->route('GET  /po', 'PoController->index');
$f3->route('GET  /po/@id', 'PoController->detail');
$f3->route('POST /po/@id/lanjut-status', 'PoController->lanjutStatus');

// Run application
$f3->run();
