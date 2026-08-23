<?php

/**
 * Controller untuk Autentikasi Login (terhadap tb_arsipuser), Sesi Pengguna, dan Profil
 */
class AuthController extends Controller {

    /**
     * Tampilkan form login
     * Route: GET /login
     */
    public function loginGet($f3) {
        if ($this->isLoggedIn()) {
            $f3->reroute('/po');
            return;
        }
        $f3->set('page_title', 'Login - OPTI Tracker BBSPJI Selulosa');
        echo \Template::instance()->render('auth/login.html');
    }

    /**
     * Proses submit form login
     * Route: POST /login
     */
    public function loginPost($f3) {
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
        // Redirect berdasarkan role dari opti_user_map
        if ($userData['role'] === 'admin_order') {
            $f3->reroute('/admin-order');
            return;
        }
        $f3->reroute('/po');
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

        $this->render('auth/profil.html', 'Profil Pengguna & Pengaturan Alert - OPTI Tracker', 'profil');
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
