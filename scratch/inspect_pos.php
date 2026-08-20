<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
$pos = $db->exec('SELECT p.id, p.nomor_po, p.biaya, p.status, o.nomor_order, o.jenis_layanan_opti, o.judul_kegiatan FROM po p JOIN order_layanan o ON p.order_id = o.id ORDER BY p.id ASC');
echo "Total POs: " . count($pos) . "\n";
foreach ($pos as $p) {
    echo "PO ID: {$p['id']} | {$p['nomor_po']} | Order: {$p['nomor_order']} ({$p['jenis_layanan_opti']}) | {$p['judul_kegiatan']}\n";
}
