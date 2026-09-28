<?php
/**
 * Migrasi sekali-pakai: nambahin kolom `metoda` di tabel `po_kegiatan`
 * kalau belum ada. Aman dijalankan berkali-kali (cek dulu sebelum ALTER).
 *
 * CARA PAKAI: buka file ini lewat browser, misal:
 *   http://opti-tracker.test/_migrate_metoda.php
 * Setelah muncul "Selesai", HAPUS file ini dari folder project.
 */

header('Content-Type: text/plain; charset=utf-8');

$configPath = __DIR__ . '/config.ini';
if (!file_exists($configPath)) {
    die("config.ini tidak ditemukan di: $configPath\n");
}
$config = parse_ini_file($configPath, true);
$g = $config['globals'] ?? [];

$dsn  = $g['db_dns']  ?? '';
$user = $g['db_user'] ?? '';
$pass = $g['db_pass'] ?? '';

if (!$dsn) {
    die("db_dns tidak ketemu di config.ini\n");
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\PDOException $e) {
    die("Gagal konek DB: " . $e->getMessage() . "\n");
}

// Ambil nama database dari DSN
preg_match('/dbname=([^;]+)/', $dsn, $m);
$dbname = $m[1] ?? '';
if (!$dbname) {
    die("Tidak bisa baca dbname dari db_dns.\n");
}

echo "Konek ke database: $dbname\n";

// Cek apakah kolom metoda sudah ada
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'po_kegiatan' AND COLUMN_NAME = 'metoda'"
);
$stmt->execute([$dbname]);
$exists = (int) $stmt->fetchColumn() > 0;

if ($exists) {
    echo "Kolom `metoda` SUDAH ADA di tabel `po_kegiatan`. Tidak ada yang diubah.\n";
} else {
    echo "Kolom `metoda` belum ada. Menambahkan...\n";
    try {
        $pdo->exec("ALTER TABLE `po_kegiatan` ADD COLUMN `metoda` TEXT NULL AFTER `alat_bahan`");
    } catch (\PDOException $e) {
        // Kolom acuan `alat_bahan` mungkin gak ada / namanya beda -- fallback tanpa AFTER
        $pdo->exec("ALTER TABLE `po_kegiatan` ADD COLUMN `metoda` TEXT NULL");
    }
    echo "Selesai. Kolom `metoda` (TEXT, nullable) sudah ditambahkan ke `po_kegiatan`.\n";
}

echo "\nSekarang HAPUS file _migrate_metoda.php ini dari folder project ya, biar gak nyangkut.\n";
