<?php

class Controller {
    protected $f3;
    protected $db;

    public function __construct() {
        $this->f3 = \Base::instance();
        $this->db = $this->f3->get('DB');
    }

    /**
     * Helper render view dengan layout utama
     */
    public function render($viewFile, $pageTitle = 'Mini OPTI Tracker', $activeMenu = '') {
        $this->f3->set('content', $viewFile);
        $this->f3->set('page_title', $pageTitle);
        $this->f3->set('active_menu', $activeMenu);
        echo \Template::instance()->render('layout.html');
    }

    /**
     * Set notifikasi flash sukses
     */
    public function setFlashSuccess($message) {
        $_SESSION['flash_success'] = $message;
    }

    /**
     * Set notifikasi flash error
     */
    public function setFlashError($message) {
        $_SESSION['flash_error'] = $message;
    }

    /**
     * Interceptor global beforeroute
     * Dijalankan otomatis oleh F3 sebelum mengeksekusi method action pada controller mana pun
     */
    public function beforeroute($f3) {
        $path = $f3->get('PATH');

        // Deteksi apakah controller yang diakses adalah AuthController
        $isAuthPage = ($this instanceof AuthController);

        // 1. Pengecekan Autentikasi (wajib login untuk semua controller kecuali AuthController)
        if (!$isAuthPage && !isset($_SESSION['user_id'])) {
            $f3->reroute('/login');
            return;
        }

        // Jika user sudah masuk dan mencoba mengakses form login, langsung arahkan ke /po
        if ($isAuthPage && $path === '/login' && isset($_SESSION['user_id'])) {
            $f3->reroute('/po');
            return;
        }

        // 2. Proteksi Session Timeout (logout otomatis setelah 30 menit tidak aktif)
        if (isset($_SESSION['user_id'])) {
            $now = time();
            $lastActivity = $_SESSION['last_activity'] ?? $now;
            $timeout = 1800; // 30 menit
            
            if (($now - $lastActivity) > $timeout) {
                // Hapus session & hancurkan cookie
                $_SESSION = array();
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(), 
                        '', 
                        time() - 42000, 
                        $params["path"], 
                        $params["domain"], 
                        $params["secure"], 
                        $params["httponly"]
                    );
                }
                session_destroy();
                $f3->reroute('/login?timeout=1');
                return;
            }
            $_SESSION['last_activity'] = $now;
        }

        // 3. Proteksi CSRF Global untuk Semua Request POST
        if ($f3->get('VERB') === 'POST') {
            $postToken = $f3->get('POST.csrf_token');
            if (!$postToken || !hash_equals($_SESSION['csrf_token'] ?? '', $postToken)) {
                error_log("CSRF violation on path: " . $path);
                $f3->error(403, 'Permintaan Ditolak (Invalid CSRF Token). Silakan muat ulang halaman.');
                return;
            }
        }

        // 4. Role-Based Access Control (RBAC)
        if (isset($_SESSION['user_id'])) {
            $userRole = $_SESSION['role'] ?? 'tim_kerja';
            
            if ($userRole === 'superadmin') {
                return;
            }
            
            // Proteksi rute Klien: Semua boleh lihat, tapi tambah/simpan hanya boleh diakses oleh admin_order atau admin_kontrak (atau jadikan admin_order saja)
            if (($path === '/klien/tambah' || $path === '/klien/simpan') && $userRole !== 'admin_order') {
                $f3->error(403, 'Akses Ditolak: Hanya Petugas Order yang dapat menambahkan Klien.');
                return;
            }

            // Proteksi rute Order: Tambah/Simpan hanya untuk admin_order
            if (($path === '/order/tambah' || $path === '/order/simpan') && $userRole !== 'admin_order') {
                $f3->error(403, 'Akses Ditolak: Hanya Petugas Order yang dapat mendaftarkan Order Layanan baru.');
                return;
            }
            
            // Proteksi rute Approve/Tolak Order atau Map Kendali PO hanya untuk pejabat
            if ((strpos($path, '/approve') !== false || strpos($path, '/tolak') !== false) && $userRole !== 'pejabat') {
                $f3->error(403, 'Akses Ditolak: Hanya Pejabat Berwenang yang dapat memberikan persetujuan (approval).');
                return;
            }
            
            // Proteksi rute Kontrak PKS: Semua boleh lihat, tapi tambah/simpan hanya untuk admin_kontrak
            if (strpos($path, '/kontrak') === 0 && ($path === '/kontrak/tambah' || $path === '/kontrak/simpan') && $userRole !== 'admin_kontrak') {
                $f3->error(403, 'Akses Ditolak: Hanya Admin Kontrak yang dapat menginput Kontrak PKS.');
                return;
            }
            
            // Proteksi rute Lanjut Status PO: Hanya untuk ketua_tim
            if (strpos($path, '/lanjut-status') !== false && $userRole !== 'ketua_tim') {
                $f3->error(403, 'Akses Ditolak: Hanya Ketua Tim OPTI yang dapat merubah/melanjutkan status pekerjaan PO.');
                return;
            }
        }
    }
}
