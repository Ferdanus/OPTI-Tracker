<?php

/**
 * Controller untuk Manajemen Sesi Pengguna, Beralih Peran (Role Switch), dan Profil
 */
class AuthController extends Controller {

    /**
     * Halaman Login dibypass langsung ke Dashboard
     * Route: GET /login
     */
    public function loginGet($f3) {
        $f3->reroute('/dashboard');
    }

    /**
     * Submit login dibypass langsung ke Dashboard
     * Route: POST /login
     */
    public function loginPost($f3) {
        $userId = (int)($f3->get('POST.user_id') ?? 0);
        if ($userId > 0) {
            return $this->quickLogin($f3, ['id' => $userId]);
        }
        $f3->reroute('/dashboard');
    }

    /**
     * Switch Role / Beralih Peran 1-klik langsung tanpa OTP
     * Route: GET /login/switch/@id & POST /login/switch
     */
    public function quickLogin($f3, $params) {
        $id = (int)($params['id'] ?? ($f3->get('POST.user_id') ?? 0));
        if ($id <= 0) {
            $f3->reroute('/dashboard');
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
            $f3->reroute('/dashboard');
            return;
        }

        $userData = $user[0];
        $rawRole = !empty($userData['si_opti']) ? trim($userData['si_opti']) : ($userData['role_opti'] ?? '');
        $roleOpti = 'user';
        $jenisLayananOpti = 'semua';

        if (strpos($rawRole, 'tim_kerja_selulosa') !== false || $rawRole === 'pic_selulosa') {
            $roleOpti = 'tim_kerja';
            $jenisLayananOpti = 'selulosa';
        } elseif (strpos($rawRole, 'tim_kerja_lingkungan') !== false || $rawRole === 'pic_lingkungan') {
            $roleOpti = 'tim_kerja';
            $jenisLayananOpti = 'lingkungan';
        } elseif (strpos($rawRole, 'ketua_tim_selulosa') !== false || $rawRole === 'katim_selulosa') {
            $roleOpti = 'ketua_tim';
            $jenisLayananOpti = 'selulosa';
        } elseif (strpos($rawRole, 'ketua_tim_lingkungan') !== false || $rawRole === 'katim_lingkungan') {
            $roleOpti = 'ketua_tim';
            $jenisLayananOpti = 'lingkungan';
        } elseif ($rawRole === 'keuangan') {
            $roleOpti = 'keuangan';
            $jenisLayananOpti = 'semua';
        } elseif ($rawRole === 'user' || $rawRole === 'pegawai') {
            $roleOpti = 'user';
            $jenisLayananOpti = 'semua';
        } elseif (!empty($rawRole)) {
            $roleOpti = $rawRole;
            $jenisLayananOpti = $userData['jenis_layanan_opti'] ?? 'semua';
        }

        if (empty($rawRole)) {
            if (!empty($userData['bidang']) && in_array(strtolower($userData['bidang']), array('all', 'admin'))) {
                $roleOpti = 'superadmin';
            } else {
                $roleOpti = 'user';
            }
        }

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

        $this->setFlashSuccess("Berhasil beralih peran sebagai <strong>{$userData['nama_user']}</strong> (" . strtoupper(str_replace('_', ' ', $roleOpti)) . ").");
        $f3->reroute('/dashboard');
    }

    /**
     * Halaman Input Verifikasi OTP dibypass langsung ke Dashboard
     * Route: GET /login/otp
     */
    public function otpGet($f3) {
        $f3->reroute('/dashboard');
    }

    /**
     * Submit OTP dibypass langsung ke Dashboard
     * Route: POST /login/otp/verify
     */
    public function otpVerify($f3) {
        $f3->reroute('/dashboard');
    }

    /**
     * Kirim ulang OTP dibypass langsung ke Dashboard
     * Route: GET /login/otp/resend & POST /login/otp/resend
     */
    public function otpResend($f3) {
        $f3->reroute('/dashboard');
    }

    /**
     * Reset sesi ke Super Admin
     * Route: POST /logout atau GET /logout
     */
    public function logout($f3) {
        $_SESSION['user_id']            = 9006;
        $_SESSION['login']              = 'superadmin';
        $_SESSION['username']           = 'superadmin';
        $_SESSION['nama_lengkap']       = 'Super Admin OPTI';
        $_SESSION['nama_user']          = 'Super Admin OPTI';
        $_SESSION['role']               = 'superadmin';
        $_SESSION['jenis_layanan_opti'] = 'semua';
        $_SESSION['bidang']             = 'all';
        $_SESSION['last_activity']      = time();
        $this->setFlashSuccess('Sesi akun telah direset ke Super Admin.');
        $f3->reroute('/dashboard');
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
