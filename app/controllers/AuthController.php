<?php

/**
 * Controller untuk Autentikasi Login, Verifikasi OTP WhatsApp, Sesi Pengguna, dan Profil
 * 
 * CATATAN PENGEMBANGAN:
 * Halaman login dan verifikasi OTP WhatsApp saat ini disembunyikan / dibypass ($bypassLogin = true)
 * agar sistem dapat langsung diakses. Semua kode, file view (login.html, otp.html),
 * dan integrasi WhatsAppService tetap dipertahankan secara utuh dan siap diaktifkan kembali jika diperlukan.
 */
class AuthController extends Controller {

    /**
     * Flag untuk menyembunyikan / membypass halaman login dan OTP (true = bypass langsung ke dashboard)
     */
    protected static $bypassLogin = true;

    /**
     * Tampilkan form login / pemilihan role pengguna 1-klik
     * Route: GET /login
     */
    public function loginGet($f3) {
        if (self::$bypassLogin || $this->isLoggedIn()) {
            $f3->reroute('/dashboard');
            return;
        }

        // Ambil daftar pengguna utama untuk selector 1-klik
        $users = $this->db->exec("
            SELECT u.id_user, u.login, u.nama_user, u.no_hp, u.bidang, u.id_struktural, u.si_opti,
                   COALESCE(u.si_opti, m.role_opti) AS role_opti, 
                   m.jenis_layanan_opti 
            FROM tb_arsipuser u 
            LEFT JOIN opti_user_map m ON u.id_user = m.id_user 
            WHERE u.status = 1 OR u.status = '1' OR u.status = 'aktif'
            ORDER BY 
                CASE 
                    WHEN u.si_opti = 'superadmin' OR m.role_opti = 'superadmin' OR u.bidang = 'all' THEN 1
                    WHEN u.si_opti = 'admin_order' OR m.role_opti = 'admin_order' THEN 2
                    WHEN u.si_opti = 'ketua_tim_selulosa' OR (m.role_opti = 'ketua_tim' AND m.jenis_layanan_opti = 'selulosa') THEN 3
                    WHEN u.si_opti = 'ketua_tim_lingkungan' OR (m.role_opti = 'ketua_tim' AND m.jenis_layanan_opti = 'lingkungan') THEN 4
                    WHEN u.si_opti = 'keuangan' THEN 5
                    WHEN u.si_opti LIKE 'tim_kerja%' OR m.role_opti = 'tim_kerja' THEN 6
                    WHEN u.si_opti = 'admin_kontrak' OR m.role_opti = 'admin_kontrak' THEN 7
                    ELSE 8
                END ASC,
                u.id_user ASC
        ");

        $f3->set('daftar_user_login', $users);
        $f3->set('page_title', 'Akses Pengguna');
        echo \Template::instance()->render('auth/login.html');
    }

    /**
     * Login 1-klik langsung berdasarkan ID User (tanpa OTP saat mode bypass aktif)
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

        // Set sesi pengguna langsung
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
     * Submit form login
     * Route: POST /login
     */
    public function loginPost($f3) {
        if (self::$bypassLogin) {
            $userId = (int)($f3->get('POST.user_id') ?? 0);
            if ($userId > 0) {
                return $this->quickLogin($f3, ['id' => $userId]);
            }
            $f3->reroute('/dashboard');
            return;
        }

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
        $userRow = $this->db->exec("SELECT no_hp FROM tb_arsipuser WHERE id_user = ?", array(1 => (int)$userData['id_user']));
        $noHp = trim($userRow[0]['no_hp'] ?? ($userData['no_hp'] ?? ''));

        if (empty($noHp)) {
            $this->setFlashError("Akun <strong>{$userData['nama_user']}</strong> belum memiliki nomor WhatsApp terdaftar pada sistem.");
            $f3->reroute('/login');
            return;
        }

        $otpResult = WhatsAppService::getOrCreateDailyOtp($this->db, (int)$userData['id_user'], $userData['nama_user'], $noHp, false);

        if (!$otpResult['success']) {
            $this->setFlashError($otpResult['message'] ?? 'Gagal mengirimkan kode OTP ke WhatsApp.');
            $f3->reroute('/login');
            return;
        }

        $pendingData = array(
            'user_id'          => (int)$userData['id_user'],
            'userData'         => $userData,
            'roleOpti'         => $userData['role'],
            'jenisLayananOpti' => $userData['jenis_layanan_opti'],
            'otp_code'         => $otpResult['otp'] ?? '',
            'no_hp'            => $otpResult['phone'] ?? $noHp,
            'masked_phone'     => $otpResult['masked_phone'] ?? WhatsAppService::maskPhoneNumber($noHp),
            'expires_at'       => $otpResult['expires_at'] ?? (time() + 86400),
            'attempts'         => 0
        );
        $_SESSION['otp_pending'] = $pendingData;
        $f3->set('SESSION.otp_pending', $pendingData);

        $this->setFlashSuccess($otpResult['message']);
        $f3->reroute('/login/otp?user_id=' . (int)$userData['id_user']);
    }

    /**
     * Halaman Input Verifikasi OTP WhatsApp
     * Route: GET /login/otp
     */
    public function otpGet($f3) {
        if (self::$bypassLogin || $this->isLoggedIn()) {
            $f3->reroute('/dashboard');
            return;
        }

        $pending = $_SESSION['otp_pending'] ?? ($f3->get('SESSION.otp_pending') ?? null);
        $userId = (int)($pending['user_id'] ?? ($f3->get('GET.user_id') ?? 0));

        if (empty($pending) && $userId > 0) {
            $userModel = new ArsipUser($this->db);
            $profil = $userModel->getProfil($userId);
            if ($profil) {
                $noHp = $profil['no_hp'] ?? '';
                $rawRole = !empty($profil['si_opti']) ? trim($profil['si_opti']) : ($profil['role_opti'] ?? '');
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
                } elseif (!empty($rawRole)) {
                    $roleOpti = $rawRole;
                    $jenisLayananOpti = $profil['jenis_layanan_opti'] ?? 'semua';
                }
                if (empty($rawRole) && !empty($profil['bidang']) && in_array(strtolower($profil['bidang']), ['all', 'admin'])) {
                    $roleOpti = 'superadmin';
                }
                $activeOtp = WhatsAppService::getActiveOtp($this->db, $userId);
                $pending = array(
                    'user_id'          => $userId,
                    'userData'         => $profil,
                    'roleOpti'         => $roleOpti,
                    'jenisLayananOpti' => $jenisLayananOpti,
                    'otp_code'         => $activeOtp['otp_code'] ?? '',
                    'no_hp'            => $noHp,
                    'masked_phone'     => WhatsAppService::maskPhoneNumber($noHp),
                    'expires_at'       => !empty($activeOtp['expired_at']) ? strtotime($activeOtp['expired_at']) : (time() + 86400),
                    'attempts'         => 0
                );
                $_SESSION['otp_pending'] = $pending;
                $f3->set('SESSION.otp_pending', $pending);
            }
        }

        if (empty($pending)) {
            $f3->reroute('/login');
            return;
        }

        $f3->set('user_id', $pending['user_id']);
        $f3->set('nama_user', $pending['userData']['nama_user'] ?? 'Pengguna');
        $f3->set('masked_phone', $pending['masked_phone'] ?? '08******');
        $f3->set('page_title', 'Verifikasi OTP WhatsApp');
        echo \Template::instance()->render('auth/otp.html');
    }

    /**
     * Submit Verifikasi Kode OTP
     * Route: POST /login/otp/verify
     */
    public function otpVerify($f3) {
        if (self::$bypassLogin) {
            $f3->reroute('/dashboard');
            return;
        }

        $pending = $_SESSION['otp_pending'] ?? ($f3->get('SESSION.otp_pending') ?? null);
        $userId = (int)($f3->get('POST.user_id') ?? ($pending['user_id'] ?? 0));
        $inputOtp = trim($f3->get('POST.otp_code') ?? ($f3->get('POST.otp') ?? ''));

        if (empty($pending) && $userId > 0) {
            $userModel = new ArsipUser($this->db);
            $profil = $userModel->getProfil($userId);
            if ($profil) {
                $rawRole = !empty($profil['si_opti']) ? trim($profil['si_opti']) : ($profil['role_opti'] ?? '');
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
                } elseif (!empty($rawRole)) {
                    $roleOpti = $rawRole;
                    $jenisLayananOpti = $profil['jenis_layanan_opti'] ?? 'semua';
                }
                if (empty($rawRole) && !empty($profil['bidang']) && in_array(strtolower($profil['bidang']), ['all', 'admin'])) {
                    $roleOpti = 'superadmin';
                }
                $pending = [
                    'user_id' => $userId,
                    'userData' => $profil,
                    'roleOpti' => $roleOpti,
                    'jenisLayananOpti' => $jenisLayananOpti
                ];
            }
        }

        if (empty($pending)) {
            $this->setFlashError('Sesi verifikasi login telah berakhir. Silakan login kembali.');
            $f3->reroute('/login');
            return;
        }

        $verifyResult = WhatsAppService::verifyOtp($this->db, $userId, $inputOtp);
        if (!$verifyResult['valid']) {
            $this->setFlashError($verifyResult['message']);
            $f3->reroute('/login/otp?user_id=' . $userId);
            return;
        }

        $userData = $pending['userData'];
        $_SESSION['user_id']            = $userData['id_user'];
        $_SESSION['login']              = $userData['login'];
        $_SESSION['username']           = $userData['login'];
        $_SESSION['nama_lengkap']       = $userData['nama_user'];
        $_SESSION['nama_user']          = $userData['nama_user'];
        $_SESSION['role']               = $pending['roleOpti'];
        $_SESSION['jenis_layanan_opti'] = $pending['jenisLayananOpti'];
        $_SESSION['bidang']             = $userData['bidang'] ?? 'all';
        $_SESSION['foto_profil']        = $userData['foto_profil'] ?? null;
        $_SESSION['last_activity']      = time();

        $f3->set('SESSION.user_id', $userData['id_user']);
        $f3->set('SESSION.login', $userData['login']);
        $f3->set('SESSION.username', $userData['login']);
        $f3->set('SESSION.nama_lengkap', $userData['nama_user']);
        $f3->set('SESSION.nama_user', $userData['nama_user']);
        $f3->set('SESSION.role', $pending['roleOpti']);
        $f3->set('SESSION.jenis_layanan_opti', $pending['jenisLayananOpti']);
        $f3->set('SESSION.bidang', $userData['bidang'] ?? 'all');
        $f3->set('SESSION.foto_profil', $userData['foto_profil'] ?? null);

        unset($_SESSION['otp_pending']);
        $f3->clear('SESSION.otp_pending');

        $this->setFlashSuccess("Selamat datang kembali, <strong>{$userData['nama_user']}</strong>! Login berhasil diverifikasi.");
        $f3->reroute('/dashboard');
    }

    /**
     * Kirim Ulang Kode OTP ke WhatsApp
     * Route: GET /login/otp/resend & POST /login/otp/resend
     */
    public function otpResend($f3) {
        if (self::$bypassLogin) {
            $f3->reroute('/dashboard');
            return;
        }

        $pending = $_SESSION['otp_pending'] ?? ($f3->get('SESSION.otp_pending') ?? null);
        $userId = (int)($f3->get('GET.user_id') ?? ($pending['user_id'] ?? 0));

        if ($userId <= 0) {
            $this->setFlashError('Sesi verifikasi login telah berakhir. Silakan login kembali.');
            $f3->reroute('/login');
            return;
        }

        $userModel = new ArsipUser($this->db);
        $userData = $userModel->getProfil($userId);
        $noHp = $userData['no_hp'] ?? '';
        $namaUser = $userData['nama_user'] ?? 'Pengguna';

        $otpResult = WhatsAppService::getOrCreateDailyOtp($this->db, $userId, $namaUser, $noHp, true);

        if (!$otpResult['success']) {
            $this->setFlashError($otpResult['message'] ?? 'Gagal mengirim ulang OTP ke WhatsApp.');
            $f3->reroute('/login/otp?user_id=' . $userId);
            return;
        }

        $this->setFlashSuccess("Kode OTP baru telah berhasil dikirimkan ke WhatsApp ({$otpResult['masked_phone']}).");
        $f3->reroute('/login/otp?user_id=' . $userId);
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
