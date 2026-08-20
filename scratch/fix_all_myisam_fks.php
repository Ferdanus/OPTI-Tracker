<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');

$constraints = $db->exec("
    SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'mini_opti_tracker' 
      AND REFERENCED_TABLE_NAME IN ('tb_arsipuser', 'tb_customer')
");

echo "Found foreign keys to MyISAM tables:\n";
print_r($constraints);

foreach ($constraints as $c) {
    $table = $c['TABLE_NAME'];
    $fk = $c['CONSTRAINT_NAME'];
    echo "Dropping FK {$fk} on table {$table}...\n";
    try {
        $db->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
        echo " -> Dropped successfully.\n";
    } catch (\Exception $e) {
        echo " -> Error: " . $e->getMessage() . "\n";
    }
}

echo "\nDone cleaning MyISAM foreign keys.\n";
