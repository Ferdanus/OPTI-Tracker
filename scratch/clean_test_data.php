<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');

echo "--- Cleaning up automated test data ---\n";

// Disable FK checks temporarily for safe batch cleanup
$db->exec("SET FOREIGN_KEY_CHECKS = 0");

$db->exec("DELETE FROM po_rincian_anggaran WHERE po_id > 2");
$db->exec("DELETE FROM opti_po_jadwal_kerja WHERE po_id > 2");
$db->exec("DELETE FROM opti_pembayaran WHERE po_id > 2");
$db->exec("DELETE FROM po_log_status WHERE po_id > 2");
$db->exec("DELETE FROM kontrak_pks WHERE po_id > 2");
$db->exec("DELETE FROM po WHERE id > 2");
$db->exec("DELETE FROM order_layanan WHERE id > 2");
$db->exec("DELETE FROM tb_customer WHERE id_customer > 2883");

// Reset Auto Increments
$db->exec("ALTER TABLE po AUTO_INCREMENT = 3");
$db->exec("ALTER TABLE order_layanan AUTO_INCREMENT = 3");
$db->exec("ALTER TABLE po_rincian_anggaran AUTO_INCREMENT = 3");
$db->exec("ALTER TABLE opti_po_jadwal_kerja AUTO_INCREMENT = 3");
$db->exec("ALTER TABLE opti_pembayaran AUTO_INCREMENT = 3");
$db->exec("ALTER TABLE tb_customer AUTO_INCREMENT = 2884");

$db->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "Clean up done successfully!\n\n";

// Check remaining POs
$pos = $db->exec('SELECT p.id, p.nomor_po, p.biaya, p.status, o.nomor_order, o.jenis_layanan_opti, o.judul_kegiatan, cust.nmcustomer, cust.pt_cv FROM po p JOIN order_layanan o ON p.order_id = o.id JOIN tb_customer cust ON o.id_customer = cust.id_customer ORDER BY p.id ASC');
echo "Remaining Active POs in Database: " . count($pos) . "\n";
foreach ($pos as $p) {
    echo " -> PO ID: {$p['id']} | {$p['nomor_po']} | Order: {$p['nomor_order']} ({$p['jenis_layanan_opti']}) | Mitra: {$p['nmcustomer']} ({$p['pt_cv']}) | {$p['judul_kegiatan']}\n";
}
