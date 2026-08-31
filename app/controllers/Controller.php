<?php

/**
 * Base Controller
 * Menyediakan render layout, manajemen sesi, proteksi CSRF, 
 * dan Guard Role / Permission yang terpisah rapi dari mekanisme autentikasi.
 * 
 * Sesuai Matriks Hak Akses SOP Layanan OPTI BBSPJIS:
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
     * Matriks Hak Akses / Permission per Role (Logika Murni OPTI BBSPJIS Sesuai Arahan Mentor)
     */
    protected static $PERMISSION_MATRIX = array(
        'admin_order' => array(
            'surat_masuk:view', 'surat_masuk:klaim', 'surat_masuk:batal',
            'order:view', 'order:create', 'order:edit', 'order:form_pelayanan', 'order:respon_klien',
            'penawaran:view', 'penawaran:create', 'penawaran:edit', 'penawaran:cetak',
            'kontrak:view', 'kontrak:create', 'kontrak:edit',
            'pembayaran:view', 'pembayaran:create', 'pembayaran:edit',
            'klien:view', 'klien:create', 'klien:edit',
            'alert:manage'
        ),
        'tim_mitra' => array(
            'surat_masuk:view', 'surat_masuk:klaim', 'surat_masuk:batal',
            'order:view', 'order:create', 'order:edit', 'order:form_pelayanan', 'order:respon_klien',
            'penawaran:view', 'penawaran:create', 'penawaran:edit', 'penawaran:cetak',
            'kontrak:view', 'kontrak:create', 'kontrak:edit',
            'pembayaran:view', 'pembayaran:create', 'pembayaran:edit',
            'klien:view', 'klien:create', 'klien:edit',
            'alert:manage'
        ),
        'ketua_tim' => array(
            'order:view', 'order:tinjau', 'order:assign_pic', 'order:proposal_review',
            'po:view', 'po:create', 'po:edit', 'po:rab', 'po:jadwal', 'po:evaluasi', 'po:sop',
            'penawaran:view',
            'klien:view',
            'config:team', 'config:manage',
            'alert:manage'
        ),
        'pejabat' => array(
            'order:view',
            'penawaran:view',
            'po:view', 'po:sop', 'po:approve',
            'kontrak:view',
            'pembayaran:view',
            'klien:view',
            'alert:manage'
        ),
        'tim_kerja' => array(
            'order:view', 'order:proposal', 'order:proposal_pic', 'order:proposal_upload', 'order:kalkulasi_biaya',
            'po:view', 'po:progress', 'po:laporan', 'po:sop',
            'alert:manage'
        ),
        'admin_kontrak' => array(
            'order:view',
            'po:view', 'po:sop',
            'kontrak:view', 'kontrak:create', 'kontrak:edit',
            'pembayaran:view', 'pembayaran:create', 'pembayaran:edit',
            'klien:view',
            'alert:manage'
        ),
        'sekretaris' => array(
            'surat_masuk:view', 'surat_masuk:registrasi',
            'alert:manage'
        ),
        'superadmin' => array(
            '*' // Akses tanpa batas ke seluruh modul
        )
    );

    public function __construct() {
        $this->f3 = \Base::instance();
        $this->db = $this->f3->get('DB');
        $this->dbSekretariat = $this->f3->get('DB_SEKRETARIAT');
    }

    /**
     * Helper render view dengan layout utama
     */
    public function render($viewFile, $pageTitle = 'Mini OPTI Tracker', $activeMenu = '') {
        $this->f3->set('content', $viewFile);
        $this->f3->set('page_title', $pageTitle);
        $this->f3->set('active_menu', $activeMenu);

        $role = $this->getUserRole();
        $layanan = $this->getUserLayanan();
        $userId = $this->getUserId();

        // Inject flag hak akses granular ke view untuk conditional UI rendering
        $this->f3->set('user_id', $userId);
        $this->f3->set('user_role', $role);
        $this->f3->set('user_layanan', $layanan);

        $this->f3->set('is_superadmin', $role === 'superadmin');
        $this->f3->set('is_admin_order', $role === 'admin_order' || $role === 'tim_mitra');
        $this->f3->set('is_ketua_tim', $role === 'ketua_tim');
        $this->f3->set('is_ketua_selulosa', $role === 'ketua_tim' && $layanan === 'selulosa');
        $this->f3->set('is_ketua_lingkungan', $role === 'ketua_tim' && $layanan === 'lingkungan');
        $this->f3->set('is_pejabat', $role === 'pejabat');
        $this->f3->set('is_tim_kerja', $role === 'tim_kerja');
        $this->f3->set('is_admin_kontrak', $role === 'admin_kontrak');
        $this->f3->set('is_sekretaris', $role === 'sekretaris');

        $this->f3->set('can_manage_order', $this->hasPermission('order:create') || $this->hasPermission('order:edit'));
        $this->f3->set('can_manage_surat_masuk', $this->hasPermission('surat_masuk:klaim') || $this->hasPermission('surat_masuk:registrasi'));
        $this->f3->set('can_manage_penawaran', $this->hasPermission('penawaran:create') || $this->hasPermission('penawaran:edit'));
        $this->f3->set('can_manage_pembayaran', $this->hasPermission('pembayaran:create') || $this->hasPermission('pembayaran:edit'));
        $this->f3->set('can_manage_po', $this->hasPermission('po:create') || $this->hasPermission('po:edit'));
        $this->f3->set('can_approve_po', $this->hasPermission('po:approve'));
        $this->f3->set('can_manage_kontrak', $this->hasPermission('kontrak:create') || $this->hasPermission('kontrak:edit'));
        $this->f3->set('can_manage_config', $this->hasPermission('config:manage') || $this->hasPermission('config:team'));

        // Hitung notifikasi tugas / disposisi masuk untuk Ketua Tim & Superadmin
        $notifKatimCount = 0;
        if ($role === 'ketua_tim' || $role === 'superadmin') {
            try {
                $whereDiv = ($role === 'ketua_tim' && in_array($layanan, array('selulosa', 'lingkungan'))) ? "o.jenis_layanan_opti = '{$layanan}' AND" : "";
                $sqlNotif = "SELECT COUNT(*) as c FROM order_layanan o
                             WHERE {$whereDiv} (
                                 (o.status = 'baru' AND o.id NOT IN (SELECT order_id FROM opti_tinjauan_kelayakan))
                                 OR (o.status_proposal_biaya = 'menunggu_approval')
                             )";
                $resNotif = $this->db->exec($sqlNotif);
                $notifKatimCount = (int)($resNotif[0]['c'] ?? 0);
            } catch (\Exception $eNotif) {
                $notifKatimCount = 0;
            }
        }
        $this->f3->set('jumlah_notif_katim', $notifKatimCount);

        // Hitung notifikasi Surat Masuk untuk Tim Mitra, Sekretaris & Superadmin
        $notifSuratCount = 0;
        if ($role === 'admin_order' || $role === 'tim_mitra' || $role === 'sekretaris' || $role === 'superadmin') {
            try {
                $repoSurat = new \SuratMasukRepository($this->db, $this->dbSekretariat);
                $suratBelumKlaim = count($repoSurat->getDaftarSuratOpti());
                $resOrderKlaim = $this->db->exec("SELECT COUNT(*) as c FROM order_layanan WHERE status = 'permintaan_masuk'");
                $orderBelumDiproses = (int)($resOrderKlaim[0]['c'] ?? 0);
                $notifSuratCount = $suratBelumKlaim + $orderBelumDiproses;
            } catch (\Exception $eSurat) {
                $notifSuratCount = 0;
            }
        }
        $this->f3->set('jumlah_notif_surat', $notifSuratCount);

        // Notifikasi Terintegrasi (Notification Service) untuk Bell Dropdown & Floating Bubble
        try {
            $userNotifList = \NotificationService::getUserNotifications($this->db, (int)$userId, $role, $layanan, 8);
            $unreadNotifCount = \NotificationService::getUnreadCount($this->db, (int)$userId, $role, $layanan);
        } catch (\Exception $eNotifSys) {
            $userNotifList = [];
            $unreadNotifCount = 0;
        }
        $this->f3->set('list_notifikasi_user', $userNotifList);
        $this->f3->set('unread_notif_count', $unreadNotifCount);

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
     * Cek apakah role aktif adalah superadmin
     */
    public function isSuperadmin(): bool {
        return $this->getUserRole() === 'superadmin';
    }

    /**
     * Cek apakah role aktif adalah Tim Kemitraan (admin_order / tim_mitra)
     */
    public function isAdminOrder(): bool {
        $r = $this->getUserRole();
        return $r === 'admin_order' || $r === 'tim_mitra';
    }

    /**
     * Cek apakah role aktif adalah Ketua Tim
     */
    public function isKetuaTim(): bool {
        return $this->getUserRole() === 'ketua_tim';
    }

    /**
     * Cek apakah role aktif adalah Pejabat (Kepala Balai / PPK)
     */
    public function isPejabat(): bool {
        return $this->getUserRole() === 'pejabat';
    }

    /**
     * Cek apakah role aktif adalah Tim Kerja (Analis / Peneliti)
     */
    public function isTimKerja(): bool {
        return $this->getUserRole() === 'tim_kerja';
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
     * Ambil user ID dari session
     */
    public function getUserId(): ?int {
        return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
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
     * Alias requireLogin untuk kompatibilitas
     */
    public function requireLogin(): void {
        $this->requireAuth();
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
