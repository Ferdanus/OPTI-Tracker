<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
$orders = $db->exec('SELECT id, nomor_order, jenis_layanan_opti, id_customer, judul_kegiatan, status_order FROM order_layanan ORDER BY id ASC');
echo "Orders in DB:\n";
print_r($orders);

$pos = $db->exec('SELECT id, order_id, nomor_po, biaya, status FROM po ORDER BY id ASC');
echo "\nPOs in DB:\n";
print_r($pos);
