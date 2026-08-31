<?php

/**
 * Controller untuk Autentikasi Login (terhadap tb_arsipuser), Sesi Pengguna, dan Profil
 */
class AuthController extends Controller {

    /**
     * Tampilkan form login / pemilihan role pengguna 1-klik
     * Route: GET /login
     */
    public function loginGet($f3) {
        if ($this->isLoggedIn()) {
            $f3->reroute('/order');
            return;
        }

        // Ambil daftar pengguna utama untuk selector 1-klik
        $users = $this->db->exec("
            SELECT u.id_user, u.login, u.nama_user, u.bidang, u.id_struktural, 
                   m.role_opti, m.jenis_layanan_opti 
            FROM tb_arsipuser u 
            LEFT JOIN opti_user_map m ON u.id_user = m.id_user 
            WHERE u.status = 1 OR u.status = '1' OR u.status = 'aktif'
            ORDER BY 
                CASE 
                    WHEN m.role_opti = 'superadmin' OR u.bidang = 'all' THEN 1
                    WHEN m.role_opti = 'admin_order' THEN 2
                    WHEN m.role_opti = 'ketua_tim' AND m.jenis_layanan_opti = 'selulosa' THEN 3
                    WHEN m.role_opti = 'ketua_tim' AND m.jenis_layanan_opti = 'lingkungan' THEN 4
                    WHEN m.role_opti = 'pejabat' THEN 5
                    WHEN m.role_opti = 'tim_kerja' THEN 6
                    WHEN m.role_opti = 'admin_kontrak' THEN 7
                    ELSE 8
                END ASC,
                u.id_user ASC
        ");

        $f3->set('daftar_user_login', $users);
        $f3->set('page_title', 'Akses Pengguna');
        echo \Template::instance()->render('auth/login.html');
    }

    /**
     * Login 1-klik langsung berdasarkan ID User
     * Route: GET /login/switch/@id & POST /login/switch
     */
    public function quickLogin($f3, $params) {
        $id = (int)($params['id'] ?? ($f3->get('POST.user_id') ?? 0));
        if ($id <= 0) {
            $this->setFlashError('Pilih akun pengguna yang valid.');
            $f3->reroute('/login');
            return;
        }

        $user = $this->db->exec(
            "SELECT u.*, m.role_opti, m.jenis_layanan_opti, m.is_active AS map_active
             FROM tb_arsipuser u
             LEFT JOIN opti_user_map m ON u.id_user = m.id_user
             WHERE u.id_user = ? AND (u.status = 1 OR u.status = '1' OR u.status = 'aktif')",
            array(1 => $id)
        );

        if (empty($user)) {
            $this->setFlashError('Akun pengguna tidak ditemukan.');
            $f3->reroute('/login');
            return;
        }

        $userData = $user[0];
        $roleOpti = $userData['role_opti'] ?? 'tim_kerja';
        $jenisLayananOpti = $userData['jenis_layanan_opti'] ?? 'semua';

        if (empty($userData['role_opti'])) {
            if (!empty($userData['bidang']) && in_array(strtolower($userData['bidang']), array('all', 'admin'))) {
                $roleOpti = 'superadmin';
            } elseif ((int)$userData['id_struktural'] === 3 || (int)$userData['id_struktural'] === 200) {
                $roleOpti = 'pejabat';
            } elseif ((int)$userData['id_struktural'] === 4) {
                $roleOpti = 'ketua_tim';
            } else {
                $roleOpti = 'superadmin';
            }
        }

        // Simpan data sesi pengguna
        $_SESSION['user_id']            = $userData['id_user'];
        $_SESSION['login']              = $userData['login'];
        $_SESSION['username']           = $userData['login'];
        $_SESSION['nama_lengkap']       = $userData['nama_user'];
        $_SESSION['nama_user']          = $userData['nama_user'];
        $_SESSION['role']               = $roleOpti;
        $_SESSION['jenis_layanan_opti'] = $jenisLayananOpti;
        $_SESSION['bidang']             = $userData['bidang'] ?? 'all';
        $_SESSION['foto_profil']        = $userData['foto_profil'] ?? null;
        $_SESSION['last_activity']      = time();

        $f3->set('SESSION.user_id', $userData['id_user']);
        $f3->set('SESSION.login', $userData['login']);
        $f3->set('SESSION.username', $userData['login']);
        $f3->set('SESSION.nama_lengkap', $userData['nama_user']);
        $f3->set('SESSION.nama_user', $userData['nama_user']);
        $f3->set('SESSION.role', $roleOpti);
        $f3->set('SESSION.jenis_layanan_opti', $jenisLayananOpti);
        $f3->set('SESSION.bidang', $userData['bidang'] ?? 'all');
        $f3->set('SESSION.foto_profil', $userData['foto_profil'] ?? null);

        // Load masking preference
        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();
        $_SESSION['mask_client_name'] = $maskEnabled;
        $f3->set('SESSION.mask_client_name', $maskEnabled);

        $this->setFlashSuccess("Berhasil masuk sebagai <strong>{$userData['nama_user']}</strong> (" . strtoupper(str_replace('_', ' ', $roleOpti)) . ").");
        $f3->reroute('/order');
    }

    /**
     * Proses submit form login
     * Route: POST /login
     */
    public function loginPost($f3) {
        $userId = (int)($f3->get('POST.user_id') ?? 0);
        if ($userId > 0) {
            return $this->quickLogin($f3, ['id' => $userId]);
        }

        $login    = trim($f3->get('POST.login') ?? ($f3->get('POST.username') ?? ''));
        $password = $f3->get('POST.password') ?? '';

        if (empty($login) || empty($password)) {
            $this->setFlashError('Username/Login dan password wajib diisi.');
            $f3->reroute('/login');
            return;
        }

        $userModel = new ArsipUser($this->db);
        $authResult = $userModel->authenticate($login, $password);

        if (!$authResult['success']) {
            $this->setFlashError($authResult['message']);
            $f3->reroute('/login');
            return;
        }

        $userData = $authResult['user'];

        // Simpan data sesi pengguna
        $_SESSION['user_id']            = $userData['id_user'];
        $_SESSION['login']              = $userData['login'];
        $_SESSION['username']           = $userData['login'];
        $_SESSION['nama_lengkap']       = $userData['nama_user'];
        $_SESSION['nama_user']          = $userData['nama_user'];
        $_SESSION['role']               = $userData['role'];
        $_SESSION['jenis_layanan_opti'] = $userData['jenis_layanan_opti'];
        $_SESSION['bidang']             = $userData['bidang'];
        $_SESSION['foto_profil']        = $userData['foto_profil'];
        $_SESSION['last_activity']      = time();

        $f3->set('SESSION.user_id', $userData['id_user']);
        $f3->set('SESSION.login', $userData['login']);
        $f3->set('SESSION.username', $userData['login']);
        $f3->set('SESSION.nama_lengkap', $userData['nama_user']);
        $f3->set('SESSION.nama_user', $userData['nama_user']);
        $f3->set('SESSION.role', $userData['role']);
        $f3->set('SESSION.jenis_layanan_opti', $userData['jenis_layanan_opti']);
        $f3->set('SESSION.bidang', $userData['bidang']);
        $f3->set('SESSION.foto_profil', $userData['foto_profil']);

        // Load masking preference
        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();
        $_SESSION['mask_client_name'] = $maskEnabled;
        $f3->set('SESSION.mask_client_name', $maskEnabled);

        $this->setFlashSuccess("Selamat datang kembali, <strong>{$userData['nama_user']}</strong>!");
        $f3->reroute('/order');
    }

    /**
     * Proses logout
     * Route: POST /logout atau GET /logout
     */
    public function logout($f3) {
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
        $f3->clear('SESSION');
        $this->setFlashSuccess('Anda telah berhasil keluar dari sistem OPTI Tracker.');
        $f3->reroute('/login');
    }

    /**
     * Halaman profil pengguna & pengaturan alert pribadi
     * Route: GET /profil
     */
    public function profileGet($f3) {
        $this->requireAuth();
        $idUser = (int)($f3->get('SESSION.user_id') ?? ($_SESSION['user_id'] ?? 0));

        $userModel = new ArsipUser($this->db);
        $profil = $userModel->getProfil($idUser);

        $alertModel = new OptiUserAlertConfig($this->db);
        $userAlerts = $alertModel->getByUserId($idUser);

        $f3->set('profil', $profil);
        $f3->set('user_alerts', $userAlerts);
        $f3->set('alert_types', OptiUserAlertConfig::$ALERT_TYPES);

        $this->render('auth/profil.html', 'Profil Pengguna & Pengaturan Alert', 'profil');
    }

    /**
     * Proses update data profil pengguna
     * Route: POST /profil/simpan
     */
    public function profilePost($f3) {
        $this->requireAuth();
        $idUser = (int)($f3->get('SESSION.user_id') ?? ($_SESSION['user_id'] ?? 0));
        $post = $f3->get('POST');

        $namaUser = trim($post['nama_user'] ?? '');
        $noHp     = trim($post['no_hp'] ?? '');

        if (empty($namaUser)) {
            $this->setFlashError('Nama pengguna wajib diisi.');
            $f3->reroute('/profil');
            return;
        }

        try {
            $userModel = new ArsipUser($this->db);
            $userModel->updateProfil($idUser, array(
                'nama_user' => $namaUser,
                'no_hp'     => $noHp
            ));

            $_SESSION['nama_lengkap'] = $namaUser;
            $_SESSION['nama_user']    = $namaUser;
            $f3->set('SESSION.nama_lengkap', $namaUser);
            $f3->set('SESSION.nama_user', $namaUser);

            // Pastikan konfigurasi alert otomatis aktif di latar belakang
            $alertModel = new OptiUserAlertConfig($this->db);
            foreach (OptiUserAlertConfig::$ALERT_TYPES as $alertKey => $label) {
                $alertModel->saveUserAlert($idUser, $alertKey, true, 3);
            }

            $this->setFlashSuccess('Data profil berhasil diperbarui.');
            $f3->reroute('/profil');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui profil: ' . $e->getMessage());
            $f3->reroute('/profil');
        }
    }
}
