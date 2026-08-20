<?php

/**
 * Base Controller
 * Menyediakan render layout, manajemen sesi, proteksi CSRF, 
 * dan Guard Role / Permission yang terpisah rapi dari mekanisme autentikasi.
 * 
 * Sesuai Matriks Hak Akses SOP Layanan OPTI BBSPJI Selulosa:
 * - Admin/Petugas Order: Input & edit order baru, input pembayaran
 * - Ketua Tim OPTI: Lihat order timnya, buat & edit PO, susun tim & jadwal, atur field tim
 * - Approver (Kepala Balai/PPK/Ka.Bag TU): Verifikasi & validasi tiap tahap PO (approve)
 *   TODO: Konfirmasi ke user asli apakah approver juga boleh reject dengan catatan revisi (bukan cuma ya/tidak).
 * - Tim Kerja: Lihat order tugas, input laporan perkembangan & laporan akhir
 * - Admin Kontrak: Input & edit kontrak PKS
 * - Superadmin: Akses penuh
 */
class Controller {
    protected $f3;
    protected $db;

    /**
     * Matriks Hak Akses / Permission per Role (Logika Murni OPTI)
     */
    protected static $PERMISSION_MATRIX = array(
        'admin_order' => array(
            'order:view', 'order:create', 'order:edit',
            'pembayaran:view', 'pembayaran:create', 'pembayaran:edit',
            'po:view',
            'kontrak:view',
            'alert:manage'
        ),
        'ketua_tim' => array(
            'order:view',
            'pembayaran:view',
            'po:view', 'po:create', 'po:edit', 'po:rab', 'po:jadwal', 'po:evaluasi',
            'kontrak:view',
            'config:team', 'config:manage',
            'alert:manage'
        ),
        'pejabat' => array(
            'order:view',
            'pembayaran:view',
            'po:view', 
            'po:approve', // TODO: Konfirmasi ke user asli apakah approver juga boleh reject dengan catatan revisi (bukan cuma ya/tidak)
            'kontrak:view',
            'alert:manage'
        ),
        'tim_kerja' => array(
            'order:view',
            'pembayaran:view',
            'po:view', 'po:progress', 'po:laporan',
            'kontrak:view',
            'alert:manage'
        ),
        'admin_kontrak' => array(
            'order:view',
            'po:view',
            'kontrak:view', 'kontrak:create', 'kontrak:edit',
            'alert:manage'
        ),
        'superadmin' => array(
            '*' // Akses tanpa batas
        )
    );

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

        // Inject flag hak akses ke view untuk conditional UI rendering
        $this->f3->set('can_manage_order', $this->hasPermission('order:create') || $this->hasPermission('order:edit'));
        $this->f3->set('can_manage_pembayaran', $this->hasPermission('pembayaran:create'));
        $this->f3->set('can_manage_po', $this->hasPermission('po:create') || $this->hasPermission('po:edit'));
        $this->f3->set('can_approve_po', $this->hasPermission('po:approve'));
        $this->f3->set('can_manage_kontrak', $this->hasPermission('kontrak:create') || $this->hasPermission('kontrak:edit'));
        $this->f3->set('can_manage_config', $this->hasPermission('config:manage') || $this->hasPermission('config:team'));
        $this->f3->set('user_role', $this->getUserRole());
        $this->f3->set('user_layanan', $this->getUserLayanan());

        echo \Template::instance()->render('layout.html');
    }

    /**
     * Ambil role user dari session
     */
    public function getUserRole(): string {
        return $_SESSION['role'] ?? 'guest';
    }

    /**
     * Ambil jenis layanan user dari session (selulosa / lingkungan / semua)
     */
    public function getUserLayanan(): string {
        return $_SESSION['jenis_layanan_opti'] ?? 'semua';
    }

    /**
     * Cek apakah user memiliki permission tertentu
     */
    public function hasPermission(string $permission): bool {
        $role = $this->getUserRole();
        if ($role === 'superadmin') {
            return true;
        }

        $allowed = self::$PERMISSION_MATRIX[$role] ?? array();
        return in_array('*', $allowed) || in_array($permission, $allowed);
    }

    /**
     * Guard wajib permission, redirect dengan flash error jika tidak memiliki izin
     */
    public function requirePermission(string $permission, string $redirectUrl = '/po'): void {
        if (!$this->hasPermission($permission)) {
            $this->setFlashError('Akses ditolak: Anda tidak memiliki izin untuk melakukan tindakan ini.');
            $this->f3->reroute($redirectUrl);
            exit;
        }
    }

    /**
     * Cek apakah user sedang login
     */
    public function isLoggedIn(): bool {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Wajibkan autentikasi login
     */
    public function requireAuth(): void {
        if (!$this->isLoggedIn()) {
            $this->f3->reroute('/login');
            exit;
        }
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
     */
    public function beforeroute($f3) {
        $path = $f3->get('PATH');

        // Pastikan CSRF token tersedia dalam sesi
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        $f3->set('csrf_token', $_SESSION['csrf_token']);

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

        // 2. Proteksi Session Timeout (logout otomatis setelah 60 menit tidak aktif)
        if (isset($_SESSION['user_id'])) {
            $now = time();
            $lastActivity = $_SESSION['last_activity'] ?? $now;
            $timeout = 3600; // 60 menit
            
            if (($now - $lastActivity) > $timeout) {
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

        // 3. Proteksi CSRF Global untuk Semua Request POST (kecuali login)
        if ($f3->get('VERB') === 'POST' && $path !== '/login') {
            $postToken = $f3->get('POST.csrf_token');
            if (!$postToken || !hash_equals($_SESSION['csrf_token'] ?? '', $postToken)) {
                // Refresh token jika tidak cocok
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            }
        }
    }
}
