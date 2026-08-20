<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
// Update order #1 to PT Fajar Surya Wisesa (id_customer = 2)
$db->exec("UPDATE order_layanan SET id_customer = 2 WHERE id = 1 AND id_customer = 1");
echo "Updated order 1 to valid customer 2.\n";
