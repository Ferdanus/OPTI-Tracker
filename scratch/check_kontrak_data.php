<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
$rows = $db->exec("
    SELECT c.*, p.nomor_po, o.id_customer, o.judul_kegiatan, cust.nmcustomer, cust.pt_cv 
    FROM kontrak_pks c 
    JOIN po p ON c.po_id = p.id 
    JOIN order_layanan o ON p.order_id = o.id 
    JOIN tb_customer cust ON o.id_customer = cust.id_customer
");
print_r($rows);
