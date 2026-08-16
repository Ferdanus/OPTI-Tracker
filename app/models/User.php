<?php

/**
 * Model User
 * Menggunakan F3 SQL Mapper untuk berinteraksi dengan tabel 'user'
 */
class User extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'user');
    }

    /**
     * Mencari user berdasarkan username
     */
    public function getByUsername($username) {
        $this->load(array('username = ?', $username));
        return $this->dry() ? null : $this;
    }

    /**
     * Mencari user berdasarkan ID
     */
    public function getById($id) {
        $this->load(array('id = ?', $id));
        return $this->dry() ? null : $this;
    }

    /**
     * Memproses verifikasi login, lockout rate-limiting, dan audit login
     * @param string $username
     * @param string $password
     * @return array
     */
    public function attemptLogin($username, $password) {
        $username = trim($username);
        
        // Cari user berdasarkan username menggunakan parameter binding
        $user = $this->getByUsername($username);

        // Jika user tidak ditemukan
        if (!$user) {
            // Lakukan dummy hashing untuk mencegah timing attack (database enumeration)
            password_verify($password, '$2y$10$nxvUnhIQu63jlijpR.iNl.acYxlQIwjX.QgSP5cLZZTgAjTkzzrd.');
            return array(
                'status' => 'error',
                'message' => 'Username atau password salah.'
            );
        }

        // Cek status keaktifan user
        if (!$user->is_active) {
            return array(
                'status' => 'error',
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.'
            );
        }

        // Cek lockout status
        $now = time();
        if ($user->locked_until) {
            $lockTime = strtotime($user->locked_until);
            if ($lockTime > $now) {
                $sisaMenit = ceil(($lockTime - $now) / 60);
                return array(
                    'status' => 'locked',
                    'message' => "Terlalu banyak percobaan login. Akun Anda dikunci sementara selama {$sisaMenit} menit lagi."
                );
            } else {
                // Kunci sudah melewati waktu (expired), reset status lock
                $user->locked_until = null;
                $user->failed_login_count = 0;
                $user->save();
            }
        }

        // Verifikasi password hash menggunakan password_verify bawaan PHP
        if (password_verify($password, $user->password_hash)) {
            // Login sukses -> reset counter percobaan gagal dan catat login terakhir
            $user->failed_login_count = 0;
            $user->locked_until = null;
            $user->last_login = date('Y-m-d H:i:s');
            $user->save();

            return array(
                'status' => 'success',
                'user' => array(
                    'id' => $user->id,
                    'username' => $user->username,
                    'nama_lengkap' => $user->nama_lengkap,
                    'role' => $user->role,
                    'jenis_layanan' => $user->jenis_layanan,
                    'foto_profil' => $user->foto_profil
                )
            );
        } else {
            // Login gagal -> naikkan failed counter
            $user->failed_login_count += 1;
            
            // Jika mencapai batas 5 kali, kunci akun selama 15 menit (900 detik)
            if ($user->failed_login_count >= 5) {
                $user->locked_until = date('Y-m-d H:i:s', $now + 900);
            }
            $user->save();

            // Jika setelah gagal ini statusnya terkunci, beritahu bahwa akun dikunci
            if ($user->failed_login_count >= 5) {
                return array(
                    'status' => 'locked',
                    'message' => 'Terlalu banyak percobaan login. Akun Anda dikunci sementara selama 15 menit.'
                );
            }

            return array(
                'status' => 'error',
                'message' => 'Username atau password salah.'
            );
        }
    }
}
