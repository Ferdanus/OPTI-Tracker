<?php

require_once __DIR__ . '/../vendor/autoload.php';

$f3 = \Base::instance();
$f3->set('DB', new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', ''));

require_once __DIR__ . '/../app/controllers/Controller.php';

class TestMockController extends Controller {
    public function setRole(string $role, string $layanan = 'semua') {
        $_SESSION['role'] = $role;
        $_SESSION['jenis_layanan_opti'] = $layanan;
    }
}

$ctrl = new TestMockController();

echo "=======================================================\n";
echo "PENGUJIAN MATRIKS ROLE & PERMISSION GUARD (PROMPT V5)\n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertPermission($cond, $label) {
    global $passed, $failed;
    if ($cond) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

// 1. Admin/Petugas Order
$ctrl->setRole('admin_order');
assertPermission($ctrl->hasPermission('order:create'), "Admin Order: Boleh create order");
assertPermission($ctrl->hasPermission('order:edit'), "Admin Order: Boleh edit order");
assertPermission($ctrl->hasPermission('pembayaran:create'), "Admin Order: Boleh input pembayaran");
assertPermission(!$ctrl->hasPermission('po:approve'), "Admin Order: DILARANG approve PO");
assertPermission(!$ctrl->hasPermission('kontrak:create'), "Admin Order: DILARANG create kontrak");

// 2. Ketua Tim OPTI
$ctrl->setRole('ketua_tim', 'selulosa');
assertPermission($ctrl->hasPermission('po:create'), "Ketua Tim: Boleh buat PO");
assertPermission($ctrl->hasPermission('po:rab'), "Ketua Tim: Boleh susun RAB");
assertPermission($ctrl->hasPermission('po:jadwal'), "Ketua Tim: Boleh susun jadwal tim");
assertPermission($ctrl->hasPermission('po:evaluasi'), "Ketua Tim: Boleh evaluasi");
assertPermission($ctrl->hasPermission('config:team'), "Ketua Tim: Boleh atur field config tim");
assertPermission(!$ctrl->hasPermission('po:approve'), "Ketua Tim: DILARANG approve PO");

// 3. Approver (Kepala Balai / PPK BLU / Ka.Bag TU)
$ctrl->setRole('pejabat');
assertPermission($ctrl->hasPermission('po:approve'), "Approver: Boleh verifikasi & validasi (approve) PO");
assertPermission($ctrl->hasPermission('po:view'), "Approver: Boleh lihat PO");
assertPermission(!$ctrl->hasPermission('order:create'), "Approver: DILARANG input order baru");
assertPermission(!$ctrl->hasPermission('kontrak:create'), "Approver: DILARANG input kontrak");

// 4. Tim Kerja / Analis
$ctrl->setRole('tim_kerja');
assertPermission($ctrl->hasPermission('po:progress'), "Tim Kerja: Boleh input progres / laporan");
assertPermission(!$ctrl->hasPermission('po:approve'), "Tim Kerja: DILARANG approve PO");
assertPermission(!$ctrl->hasPermission('pembayaran:create'), "Tim Kerja: DILARANG input pembayaran");

// 5. Admin Kontrak
$ctrl->setRole('admin_kontrak');
assertPermission($ctrl->hasPermission('kontrak:create'), "Admin Kontrak: Boleh input kontrak PKS");
assertPermission($ctrl->hasPermission('kontrak:edit'), "Admin Kontrak: Boleh edit kontrak PKS");
assertPermission(!$ctrl->hasPermission('order:create'), "Admin Kontrak: DILARANG input order");
assertPermission(!$ctrl->hasPermission('po:approve'), "Admin Kontrak: DILARANG approve PO");

// 6. Superadmin
$ctrl->setRole('superadmin');
assertPermission($ctrl->hasPermission('order:create'), "Superadmin: Full access order");
assertPermission($ctrl->hasPermission('po:approve'), "Superadmin: Full access approve");
assertPermission($ctrl->hasPermission('kontrak:create'), "Superadmin: Full access kontrak");
assertPermission($ctrl->hasPermission('config:manage'), "Superadmin: Full access config");

echo "\n=======================================================\n";
echo "HASIL: {$passed} BERHASIL, {$failed} GAGAL\n";
echo "=======================================================\n";
