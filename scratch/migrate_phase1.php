<?php
require_once 'lib/base.php';
$f3 = Base::instance();
$f3->config('config.ini');
$db = new \DB\SQL($f3->get('db_dns'), $f3->get('db_user'), $f3->get('db_pass'));

echo "=== MEMULAI MIGRASI FASE 1 ===" . PHP_EOL;

// 1. Kolom baru di order_layanan
$colsOrder = $db->exec("SHOW COLUMNS FROM order_layanan");
$colNamesOrder = array_column($colsOrder, 'Field');

if (!in_array('jenis_sampel', $colNamesOrder)) {
    $db->exec("ALTER TABLE order_layanan ADD COLUMN jenis_sampel VARCHAR(150) NULL AFTER jenis_layanan");
    echo "+ Kolom 'jenis_sampel' ditambahkan ke order_layanan" . PHP_EOL;
}
if (!in_array('volume_berat', $colNamesOrder)) {
    $db->exec("ALTER TABLE order_layanan ADD COLUMN volume_berat VARCHAR(100) NULL AFTER jenis_sampel");
    echo "+ Kolom 'volume_berat' ditambahkan ke order_layanan" . PHP_EOL;
}
if (!in_array('karakteristik_sampel', $colNamesOrder)) {
    $db->exec("ALTER TABLE order_layanan ADD COLUMN karakteristik_sampel TEXT NULL AFTER volume_berat");
    echo "+ Kolom 'karakteristik_sampel' ditambahkan ke order_layanan" . PHP_EOL;
}
if (!in_array('lokasi_uji', $colNamesOrder)) {
    $db->exec("ALTER TABLE order_layanan ADD COLUMN lokasi_uji ENUM('internal', 'lapangan') DEFAULT 'internal' AFTER karakteristik_sampel");
    echo "+ Kolom 'lokasi_uji' ditambahkan ke order_layanan" . PHP_EOL;
}

// 2. Kolom baru di po
$colsPo = $db->exec("SHOW COLUMNS FROM po");
$colNamesPo = array_column($colsPo, 'Field');

if (!in_array('biaya', $colNamesPo)) {
    $db->exec("ALTER TABLE po ADD COLUMN biaya DECIMAL(15,2) DEFAULT 0 AFTER nomor_po");
    echo "+ Kolom 'biaya' ditambahkan ke po" . PHP_EOL;
}
if (!in_array('jenis_sampel', $colNamesPo)) {
    $db->exec("ALTER TABLE po ADD COLUMN jenis_sampel VARCHAR(150) NULL AFTER tim_kerja");
    echo "+ Kolom 'jenis_sampel' ditambahkan ke po" . PHP_EOL;
}
if (!in_array('volume_berat', $colNamesPo)) {
    $db->exec("ALTER TABLE po ADD COLUMN volume_berat VARCHAR(100) NULL AFTER jenis_sampel");
    echo "+ Kolom 'volume_berat' ditambahkan ke po" . PHP_EOL;
}
if (!in_array('karakteristik_sampel', $colNamesPo)) {
    $db->exec("ALTER TABLE po ADD COLUMN karakteristik_sampel TEXT NULL AFTER volume_berat");
    echo "+ Kolom 'karakteristik_sampel' ditambahkan ke po" . PHP_EOL;
}
if (!in_array('lokasi_uji', $colNamesPo)) {
    $db->exec("ALTER TABLE po ADD COLUMN lokasi_uji ENUM('internal', 'lapangan') DEFAULT 'internal' AFTER karakteristik_sampel");
    echo "+ Kolom 'lokasi_uji' ditambahkan ke po" . PHP_EOL;
}

// 3. Tabel po_rincian_biaya
$db->exec("
CREATE TABLE IF NOT EXISTS po_rincian_biaya (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    deskripsi VARCHAR(255) NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
echo "+ Tabel 'po_rincian_biaya' berhasil dibuat/dipastikan ada." . PHP_EOL;

// 4. Pastikan status_ttd di kontrak_pks VARCHAR(50) dan FK ON DELETE CASCADE
try {
    $db->exec("ALTER TABLE kontrak_pks MODIFY status_ttd VARCHAR(50) NOT NULL DEFAULT 'belum'");
    echo "+ Kolom status_ttd di kontrak_pks disesuaikan menjadi VARCHAR(50)." . PHP_EOL;
} catch (Exception $e) {
    echo "Note status_ttd: " . $e->getMessage() . PHP_EOL;
}

echo "=== MIGRASI FASE 1 SELESAI ===" . PHP_EOL;
