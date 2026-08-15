<?php

/**
 * Controller untuk menangani Autentikasi (Login & Logout)
 */
class AuthController extends Controller {

    /**
     * Menampilkan Form Login
     * Route: GET /login
     */
    public function loginGet($f3) {
        // Jika sudah login, langsung alihkan ke dashboard PO
        if ($f3->exists('SESSION.user_id')) {
            $f3->reroute('/po');
            return;
        }

        // Tampilkan halaman login (tanpa memanggil render() agar tidak memuat layout umum dashboard)
        $f3->set('page_title', 'Login - Sistem OPTI Tracker');
        echo \Template::instance()->render('auth/login.htm');
    }

    /**
     * Memproses data login user
     * Route: POST /login
     */
    public function loginPost($f3) {
        $username = trim($f3->get('POST.username') ?? '');
        $password = $f3->get('POST.password') ?? '';

        // Validasi input minimal sebelum diproses
        if (empty($username) || empty($password)) {
            $this->setFlashError('Username dan password wajib diisi.');
            $f3->reroute('/login');
            return;
        }

        // Batasi panjang karakter username untuk keamanan input
        if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $this->setFlashError('Format username tidak valid (hanya huruf, angka, dan underscore).');
            $f3->reroute('/login');
            return;
        }

        try {
            $userModel = new User($this->db);
            $result = $userModel->attemptLogin($username, $password);

            if ($result['status'] === 'success') {
                // Keamanan Session Fixation: Regenerasi ID session setelah login berhasil
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }

                // Simpan data aman ke session
                $_SESSION['user_id']      = $result['user']['id'];
                $_SESSION['username']     = $result['user']['username'];
                $_SESSION['nama_lengkap'] = $result['user']['nama_lengkap'];
                $_SESSION['foto_profil']  = $result['user']['foto_profil'] ?? null;
                $_SESSION['last_activity'] = time();

                $this->setFlashSuccess('Selamat datang kembali, ' . htmlspecialchars($result['user']['nama_lengkap']) . '!');
                $f3->reroute('/po');
            } else {
                // Menampilkan pesan error generik (atau lockout detail)
                $this->setFlashError($result['message']);
                $f3->reroute('/login');
            }
        } catch (\Exception $e) {
            // Hindari membocorkan detail error PHP/Database ke user umum
            $this->setFlashError('Terjadi kesalahan pada sistem saat memproses login.');
            $f3->reroute('/login');
        }
    }

    /**
     * Memproses Logout
     * Route: POST /logout
     */
    public function logout($f3) {
        // Hapus session data
        $_SESSION = array();

        // Hapus cookie session jika ada
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

        // Hancurkan session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Redirect ke login
        $f3->reroute('/login');
    }

    /**
     * Menampilkan Halaman Edit Profil
     * Route: GET /profil
     */
    public function profileGet($f3) {
        $userModel = new User($this->db);
        $user = $userModel->getById($_SESSION['user_id']);
        if (!$user) {
            $f3->error(404, 'User tidak ditemukan.');
            return;
        }

        $f3->set('user_data', array(
            'nama_lengkap' => $user->nama_lengkap,
            'username' => $user->username,
            'foto_profil' => $user->foto_profil
        ));

        $this->render('auth/profil.htm', 'Profil Saya - Sistem OPTI Tracker', 'profil');
    }

    /**
     * Memproses Perubahan Profil & Upload Foto
     * Route: POST /profil/simpan
     */
    public function profilePost($f3) {
        // Selalu set Content-Type ke JSON untuk respon AJAX
        header('Content-Type: application/json');

        $namaLengkap  = trim($f3->get('POST.nama_lengkap') ?? '');
        $passwordBaru = $f3->get('POST.password_baru') ?? '';

        if (empty($namaLengkap)) {
            echo json_encode(array('status' => 'error', 'message' => 'Nama Lengkap tidak boleh kosong.'));
            return;
        }

        try {
            $userModel = new User($this->db);
            $user = $userModel->getById($_SESSION['user_id']);
            if (!$user) {
                echo json_encode(array('status' => 'error', 'message' => 'User tidak ditemukan.'));
                return;
            }

            // Simpan nama lengkap lama untuk verifikasi perubahan nama lengkap
            $namaLengkapLama = $user->nama_lengkap;

            // 1. Simpan Nama Lengkap
            $user->nama_lengkap = $namaLengkap;
            $_SESSION['nama_lengkap'] = $namaLengkap;

            // 2. Jika ada password baru atau password sekarang yang diisi
            $passwordSekarang = $f3->get('POST.password_sekarang') ?? '';
            if (!empty($passwordBaru) || !empty($passwordSekarang)) {
                if (empty($passwordSekarang)) {
                    echo json_encode(array('status' => 'error', 'message' => 'Kata sandi saat ini wajib diisi jika ingin mengganti kata sandi.'));
                    return;
                }
                if (empty($passwordBaru)) {
                    echo json_encode(array('status' => 'error', 'message' => 'Kata sandi baru wajib diisi jika ingin mengganti kata sandi.'));
                    return;
                }

                // Verifikasi kata sandi sekarang
                if (!password_verify($passwordSekarang, $user->password_hash)) {
                    // Kembalikan nama lengkap session ke nilai lama karena transaksi gagal
                    $_SESSION['nama_lengkap'] = $namaLengkapLama;
                    echo json_encode(array('status' => 'error', 'message' => 'Kata sandi saat ini salah.'));
                    return;
                }

                if (strlen($passwordBaru) < 8 || !preg_match('/[A-Za-z]/', $passwordBaru) || !preg_match('/[0-9]/', $passwordBaru)) {
                    $_SESSION['nama_lengkap'] = $namaLengkapLama;
                    echo json_encode(array('status' => 'error', 'message' => 'Password baru harus minimal 8 karakter dan mengandung kombinasi huruf & angka.'));
                    return;
                }
                $user->password_hash = password_hash($passwordBaru, PASSWORD_DEFAULT);
            }

            // 3. Proses upload foto profil jika ada berkas yang dikirim
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath   = $_FILES['foto_profil']['tmp_name'];
                $fileName      = $_FILES['foto_profil']['name'];
                $fileSize      = $_FILES['foto_profil']['size'];
                $fileNameCmps  = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $_SESSION['nama_lengkap'] = $namaLengkapLama;
                    echo json_encode(array('status' => 'error', 'message' => 'Format file foto profil tidak didukung (gunakan JPG, JPEG, PNG, atau GIF).'));
                    return;
                }

                // Batas ukuran 2MB
                if ($fileSize > 2 * 1024 * 1024) {
                    $_SESSION['nama_lengkap'] = $namaLengkapLama;
                    echo json_encode(array('status' => 'error', 'message' => 'Ukuran file foto profil melebihi batas 2MB.'));
                    return;
                }

                $uploadFileDir = 'uploads/profile_pics/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                $newFileName = 'profile_' . $user->id . '_' . time() . '.' . $fileExtension;
                $destPath = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    // Hapus foto lama jika ada di disk
                    if ($user->foto_profil && file_exists($user->foto_profil)) {
                        @unlink($user->foto_profil);
                    }
                    $user->foto_profil = $destPath;
                    $_SESSION['foto_profil'] = $destPath;
                } else {
                    $_SESSION['nama_lengkap'] = $namaLengkapLama;
                    echo json_encode(array('status' => 'error', 'message' => 'Gagal memindahkan file foto profil ke server.'));
                    return;
                }
            }

            $user->save();
            echo json_encode(array('status' => 'success', 'message' => 'Profil Anda berhasil diperbarui.'));

        } catch (\Exception $e) {
            if (isset($namaLengkapLama)) {
                $_SESSION['nama_lengkap'] = $namaLengkapLama;
            }
            echo json_encode(array('status' => 'error', 'message' => 'Terjadi kesalahan pada sistem saat memperbarui profil.'));
        }
    }
}
