<?php
require_once __DIR__ . '/../vendor/autoload.php';
$f3 = \Base::instance();
$f3->set('AUTOLOAD', 'app/controllers/|app/models/');
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');
$userModel = new ArsipUser($db);

echo "1. Get current Katim:\n";
$selulosa = $userModel->getKetuaTim('selulosa');
$lingkungan = $userModel->getKetuaTim('lingkungan');
echo "   Selulosa: " . ($selulosa['nama_user'] ?? 'None') . "\n";
echo "   Lingkungan: " . ($lingkungan['nama_user'] ?? 'None') . "\n\n";

echo "2. Testing Dynamic Replacement:\n";
// Let's test swapping or assigning someone else, e.g. ID 12 (Yogi Afiyan) for Selulosa
$userModel->setKetuaTim('selulosa', 12);
$newSelulosa = $userModel->getKetuaTim('selulosa');
echo "   New Katim Selulosa: " . ($newSelulosa['nama_user'] ?? 'None') . " (ID: {$newSelulosa['id_user']})\n";

// Restore to Pak Andri (ID 9001 / 61)
$userModel->setKetuaTim('selulosa', 9001);
$restored = $userModel->getKetuaTim('selulosa');
echo "   Restored Katim Selulosa: " . ($restored['nama_user'] ?? 'None') . " (ID: {$restored['id_user']})\n";

echo "\n3. Testing Internal Users Dropdown Count:\n";
$all = $userModel->getAllInternalUsers();
echo "   Total internal users available for dropdown: " . count($all) . "\n";
