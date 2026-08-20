<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');

echo "--- Updating user & PO names to remove Pak/Bu prefixes ---\n";

// Update tb_arsipuser
$db->exec("UPDATE tb_arsipuser SET nama_user = 'Andri Taufick' WHERE id_user = 9001");
$db->exec("UPDATE tb_arsipuser SET nama_user = 'Rina Masriani' WHERE id_user = 9002");

// Update po tim_kerja
$db->exec("UPDATE po SET tim_kerja = 'Tim Analis Pulp - Andri Taufick' WHERE id = 1");
$db->exec("UPDATE po SET tim_kerja = 'Tim Sampling Lingkungan - Rina Masriani' WHERE id = 2");

echo "Database updated successfully!\n";

$users = $db->exec("SELECT id_user, login, nama_user FROM tb_arsipuser WHERE id_user IN (9001, 9002, 61, 3)");
print_r($users);

$pos = $db->exec("SELECT id, nomor_po, tim_kerja FROM po");
print_r($pos);
