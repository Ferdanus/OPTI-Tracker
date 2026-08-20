<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
$db->exec('SET FOREIGN_KEY_CHECKS = 0;');
try {
    $db->exec('ALTER TABLE order_layanan DROP FOREIGN KEY order_layanan_ibfk_1;');
} catch (\Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}
try {
    $db->exec('ALTER TABLE opti_user_map DROP FOREIGN KEY opti_user_map_ibfk_1;');
} catch (\Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1;');
echo "Cleaned DB foreign keys to MyISAM master tables.\n";
