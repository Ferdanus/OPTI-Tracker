<?php

/**
 * Model ArsipUser
 * Berinteraksi dengan tabel master pusat balai 'tb_arsipuser' dan tabel penghubung 'opti_user_map'
 * 
 * TODO: Konfirmasi ke admin/DBA tb_arsipuser apakah database OPTI akan satu server MySQL dengan database utama
 * balai (bisa langsung JOIN lintas-database) atau server terpisah (butuh API/koneksi read-only jarak jauh).
 * TODO: Konfirmasi struktur tabel tb_arsipuser masih versi terbaru sebelum development lanjut.
 */
class ArsipUser extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'tb_arsipuser');
    }

    /**
     * Autentikasi user terhadap tabel pusat tb_arsipuser dan ambil role OPTI dari opti_user_map
     */
    public function authenticate(string $login, string $password): array {
        $user = $this->db->exec(
            "SELECT u.*, m.role_opti, m.jenis_layanan_opti, m.is_active AS map_active
             FROM tb_arsipuser u
             LEFT JOIN opti_user_map m ON u.id_user = m.id_user
             WHERE u.login = ? AND (u.status = 1 OR u.status = '1' OR u.status = 'aktif')",
            array(1 => $login)
        );

        if (empty($user)) {
            return array('success' => false, 'message' => 'Username atau login tidak ditemukan atau akun dinonaktifkan.');
        }

        $row = $user[0];

        // Verifikasi password (dukung format bcrypt/argon2 DAN hash MD5 legacy server pusat balai)
        $passwordMatched = false;
        if (!empty($row['password'])) {
            if (password_verify($password, $row['password'])) {
                $passwordMatched = true;
            } elseif (strtolower(md5($password)) === strtolower($row['password'])) {
                $passwordMatched = true;
            } elseif ($row['password'] === $password) {
                $passwordMatched = true;
            }
        }

        if (!$passwordMatched) {
            return array('success' => false, 'message' => 'Password yang Anda masukkan salah.');
        }

        // Ambil role OPTI dari kolom si_opti di tb_arsipuser (atau fallback ke opti_user_map jika ada)
        $rawRole = !empty($row['si_opti']) ? trim($row['si_opti']) : ($row['role_opti'] ?? '');
        $roleOpti = 'user'; // Default: Pegawai Balai umum (Read-Only)
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
        } elseif ($rawRole === 'tim_mitra_industri' || $rawRole === 'admin_order' || $rawRole === 'tim_mitra') {
            $roleOpti = 'tim_mitra_industri';
            $jenisLayananOpti = 'semua';
        } elseif ($rawRole === 'keuangan') {
            $roleOpti = 'keuangan';
            $jenisLayananOpti = 'semua';
        } elseif ($rawRole === 'user' || $rawRole === 'pegawai') {
            $roleOpti = 'user';
            $jenisLayananOpti = 'semua';
        } elseif (!empty($rawRole)) {
            $roleOpti = $rawRole;
            $jenisLayananOpti = $row['jenis_layanan_opti'] ?? 'semua';
        }

        // Fallback untuk superadmin balai
        if (empty($rawRole)) {
            if (!empty($row['bidang']) && in_array(strtolower($row['bidang']), array('all', 'admin'))) {
                $roleOpti = 'superadmin';
            } else {
                $roleOpti = 'user';
            }
        }

        return array(
            'success' => true,
            'user' => array(
                'id_user'            => (int)$row['id_user'],
                'login'              => $row['login'],
                'nama_user'          => $row['nama_user'],
                'bidang'             => $row['bidang'] ?? '',
                'no_hp'              => $row['no_hp'] ?? '',
                'foto_profil'        => $row['nama_avatar'] ?? null,
                'role'               => $roleOpti,
                'jenis_layanan_opti' => $jenisLayananOpti
            )
        );
    }

    /**
     * Ambil data lengkap profil user
     */
    public function getProfil(int $idUser) {
        $user = $this->db->exec(
            "SELECT u.*, m.role_opti, m.jenis_layanan_opti
             FROM tb_arsipuser u
             LEFT JOIN opti_user_map m ON u.id_user = m.id_user
             WHERE u.id_user = ?",
            array(1 => $idUser)
        );
        return $user[0] ?? null;
    }

    /**
     * Update profil kontak pengguna di tb_arsipuser
     */
    public function updateProfil(int $idUser, array $data): bool {
        $namaUser = trim($data['nama_user'] ?? '');
        $noHp     = trim($data['no_hp'] ?? '');
        $avatar   = $data['nama_avatar'] ?? ($data['foto_profil'] ?? null);

        if ($avatar) {
            $this->db->exec(
                "UPDATE tb_arsipuser SET nama_user = ?, no_hp = ?, nama_avatar = ? WHERE id_user = ?",
                array(1 => $namaUser, 2 => $noHp, 3 => $avatar, 4 => $idUser)
            );
        } else {
            $this->db->exec(
                "UPDATE tb_arsipuser SET nama_user = ?, no_hp = ? WHERE id_user = ?",
                array(1 => $namaUser, 2 => $noHp, 3 => $idUser)
            );
        }
        return true;
    }

    /**
     * Ambil data Ketua Tim aktif untuk jenis layanan OPTI tertentu secara dinamis
     */
    public function getKetuaTim(string $jenisLayanan): ?array {
        $res = $this->db->exec(
            "SELECT u.id_user, u.nama_user, u.login, u.no_hp, u.bidang
             FROM tb_arsipuser u
             JOIN opti_user_map m ON u.id_user = m.id_user
             WHERE m.role_opti = 'ketua_tim' AND m.jenis_layanan_opti = ? AND m.is_active = 1
             ORDER BY m.id DESC LIMIT 1",
            array(1 => $jenisLayanan)
        );
        return $res[0] ?? null;
    }

    /**
     * Tetapkan / Ganti Ketua Tim OPTI secara dinamis (tanpa ubah struktur/kode)
     */
    public function setKetuaTim(string $jenisLayanan, int $idUser): bool {
        // Nonaktifkan role ketua tim sebelumnya untuk divisi ini
        $this->db->exec(
            "UPDATE opti_user_map SET role_opti = 'tim_kerja' 
             WHERE role_opti = 'ketua_tim' AND jenis_layanan_opti = ?",
            array(1 => $jenisLayanan)
        );

        // Cek apakah user baru sudah ada di opti_user_map
        $existing = $this->db->exec(
            "SELECT id FROM opti_user_map WHERE id_user = ?",
            array(1 => $idUser)
        );

        if (!empty($existing)) {
            $this->db->exec(
                "UPDATE opti_user_map SET role_opti = 'ketua_tim', jenis_layanan_opti = ?, is_active = 1 WHERE id_user = ?",
                array(1 => $jenisLayanan, 2 => $idUser)
            );
        } else {
            $this->db->exec(
                "INSERT INTO opti_user_map (id_user, jenis_layanan_opti, role_opti, is_active) VALUES (?, ?, 'ketua_tim', 1)",
                array(1 => $idUser, 2 => $jenisLayanan)
            );
        }
        return true;
    }

    /**
     * Ambil seluruh daftar personil internal aktif dari master tb_arsipuser untuk dropdown pilihan pejabat
     */
    public function getAllInternalUsers(): array {
        return $this->db->exec(
            "SELECT id_user, login, nama_user, bidang, no_hp 
             FROM tb_arsipuser 
             WHERE (status = 1 OR status = '1' OR status = 'aktif') AND nama_user IS NOT NULL AND nama_user != '' AND nama_user != '--Pilih--'
             ORDER BY nama_user ASC"
        );
    }
}
