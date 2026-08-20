<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
$cols = $db->exec("SHOW COLUMNS FROM tb_arsipuser");
print_r($cols);
