<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
$db->exec("UPDATE tb_arsipuser SET nama_user = REPLACE(nama_user, ' (Demo)', '') WHERE nama_user LIKE '%(Demo)%'");
$db->exec("UPDATE tb_arsipuser SET nama_user = REPLACE(nama_user, '(Demo)', '') WHERE nama_user LIKE '%(Demo)%'");

// Ensure default alert configuration is automatically enabled for all users
$db->exec("UPDATE opti_user_alert_config SET is_enabled = 1");

$rows = $db->exec("SELECT id_user, login, nama_user FROM tb_arsipuser WHERE id_user >= 9000 OR id_user IN (1,3,4,36,61,150,175)");
foreach ($rows as $r) {
    echo "ID: {$r['id_user']} | Login: {$r['login']} | Nama: {$r['nama_user']}\n";
}
