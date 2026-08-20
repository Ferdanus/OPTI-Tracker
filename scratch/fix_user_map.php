<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
$db->exec("UPDATE opti_user_map SET role_opti = 'ketua_tim' WHERE id_user IN (9001, 9002)");
echo "Updated opti_user_map successfully.\n";
