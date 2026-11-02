<?php

/**
 * Model ArsipUser
 * Berinteraksi dengan tabel master pusat balai 'tb_arsipuser', 'tb_struktural_tim',
 * dan tabel penghubung 'opti_user_map'
 */
class ArsipUser extends \DB\SQL\Mapper {

    /**
     * Peta id_struktural_tim dari tabel sil2020.tb_struktural_tim ke divisi layanan
     */
    const STRUKTURAL_KETUA_MAP = array(
        'selulosa'     => 170,
        'lingkungan'   => 190,
        'pengujian'    => 140,
        'kalibrasi'    => 150,
        'sertifikasi'  => 100,
        'pendampingan' => 210,
        'inspeksi'     => 160,
        'profisiensi'  => 160,
        'mitra'        => 240,
        'keuangan'     => 30,
        'ppk'          => 31
    );

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'tb_arsipuser');
    }

    /**
     * Resolusi role & jenis layanan dari kombinasi id_struktural_tim, si_opti, dan opti_user_map
     */
    public static function resolveUserRoleAndLayanan(array $row): array {
        $idStrukturalTim = (int)($row['id_struktural_tim'] ?? 0);
        $rawRole = !empty($row['si_opti']) ? trim($row['si_opti']) : trim($row['role'] ?? '');
        $rawLayanan = trim($row['jenis_layanan'] ?? 'semua');

        $roleOpti = 'user';
        $jenisLayananOpti = $rawLayanan !== '' ? $rawLayanan : 'semua';

        // Prioritaskan superadmin
        if ($rawRole === 'superadmin') {
            return array('role' => 'superadmin', 'jenis_layanan_opti' => 'semua');
        }

        // Cek apakah menjabat Ketua Tim / Sub Tim berdasarkan id_struktural_tim di tb_arsipuser (2B)
        $strukturalToDivisi = array(
            170 => array('role' => 'ketua_tim', 'layanan' => 'selulosa'),
            180 => array('role' => 'tim_kerja', 'layanan' => 'selulosa'),
            190 => array('role' => 'ketua_tim', 'layanan' => 'lingkungan'),
            140 => array('role' => 'ketua_tim', 'layanan' => 'pengujian'),
            150 => array('role' => 'ketua_tim', 'layanan' => 'kalibrasi'),
            100 => array('role' => 'ketua_tim', 'layanan' => 'sertifikasi'),
            210 => array('role' => 'ketua_tim', 'layanan' => 'pendampingan'),
            160 => array('role' => 'ketua_tim', 'layanan' => 'inspeksi'),
            240 => array('role' => 'ketua_tim_mitra', 'layanan' => 'semua'),
            30  => array('role' => 'ketua_tim_keuangan', 'layanan' => 'semua'),
            31  => array('role' => 'ppk', 'layanan' => 'semua'),
        );
        if (isset($strukturalToDivisi[$idStrukturalTim]) && (empty($rawRole) || $rawRole === 'user' || strpos($rawRole, 'ketua_tim') !== false || strpos($rawRole, 'katim') !== false || $rawRole === 'ppk' || $rawRole === 'tim_kerja')) {
            return array(
                'role'               => $strukturalToDivisi[$idStrukturalTim]['role'],
                'jenis_layanan_opti' => $strukturalToDivisi[$idStrukturalTim]['layanan']
            );
        }

        if (strpos($rawRole, 'tim_kerja_selulosa') !== false || $rawRole === 'pic_selulosa' || $rawRole === 'pic_po_selulosa' || $rawRole === 'pic_proposal_selulosa') {
            $roleOpti = 'tim_kerja';
            $jenisLayananOpti = 'selulosa';
        } elseif (strpos($rawRole, 'tim_kerja_lingkungan') !== false || $rawRole === 'pic_lingkungan' || $rawRole === 'pic_po_lingkungan' || $rawRole === 'pic_proposal_lingkungan') {
            $roleOpti = 'tim_kerja';
            $jenisLayananOpti = 'lingkungan';
        } elseif (preg_match('/^(?:pic_po|pic_proposal|tim_kerja)_([a-zA-Z0-9_]+)$/', $rawRole, $mPic)) {
            $roleOpti = 'tim_kerja';
            $jenisLayananOpti = strtolower($mPic[1]);
        } elseif (in_array($rawRole, array('pic_po', 'pic_proposal', 'tim_kerja'), true)) {
            $roleOpti = 'tim_kerja';
            $jenisLayananOpti = $rawLayanan !== '' ? $rawLayanan : 'semua';
        } elseif (strpos($rawRole, 'ketua_tim_mitra') !== false || $rawRole === 'katim_mitra') {
            $roleOpti = 'ketua_tim_mitra';
            $jenisLayananOpti = 'semua';
        } elseif (strpos($rawRole, 'ketua_tim_keuangan') !== false || $rawRole === 'katim_keuangan') {
            $roleOpti = 'ketua_tim_keuangan';
            $jenisLayananOpti = 'semua';
        } elseif (preg_match('/^(?:ketua_tim|katim)_([a-zA-Z0-9_]+)$/', $rawRole, $mKatim)) {
            $roleOpti = 'ketua_tim';
            $jenisLayananOpti = strtolower($mKatim[1]);
        } elseif ($rawRole === 'ketua_tim') {
            $roleOpti = 'ketua_tim';
            $jenisLayananOpti = $rawLayanan !== '' ? $rawLayanan : 'semua';
        } elseif ($rawRole === 'tim_mitra_industri' || $rawRole === 'admin_order' || $rawRole === 'tim_mitra') {
            $roleOpti = 'tim_mitra_industri';
            $jenisLayananOpti = 'semua';
        } elseif ($rawRole === 'keuangan') {
            $roleOpti = 'keuangan';
            $jenisLayananOpti = 'semua';
        } elseif ($rawRole === 'humas') {
            $roleOpti = 'humas';
            $jenisLayananOpti = 'semua';
        } elseif ($rawRole === 'ppk') {
            $roleOpti = 'ppk';
            $jenisLayananOpti = 'semua';
        } elseif ($rawRole === 'user' || $rawRole === 'pegawai') {
            $roleOpti = 'user';
            $jenisLayananOpti = 'semua';
        } elseif (!empty($rawRole)) {
            $roleOpti = $rawRole;
            $jenisLayananOpti = $rawLayanan !== '' ? $rawLayanan : 'semua';
        }

        if (empty($rawRole) && $roleOpti === 'user') {
            if (!empty($row['bidang']) && in_array(strtolower($row['bidang']), array('all', 'admin'))) {
                $roleOpti = 'superadmin';
            }
        }

        return array(
            'role'               => $roleOpti,
            'jenis_layanan_opti' => $jenisLayananOpti
        );
    }

    /**
     * Autentikasi user terhadap tabel pusat tb_arsipuser dan ambil role OPTI dari opti_user_map
     */
    public function authenticate(string $login, string $password): array {
        $user = $this->db->exec(
            "SELECT u.*, m.role, m.jenis_layanan, m.role AS role_opti, m.jenis_layanan AS jenis_layanan_opti, m.akses_opti, m.akses_po, m.is_active AS map_active
             FROM tb_arsipuser u
             LEFT JOIN opti_user_map m ON u.id_user = m.id_user AND m.status_tampil = 'show'
             WHERE u.login = ? AND (u.status = 1 OR u.status = '1' OR u.status = 'aktif')
             ORDER BY m.is_active DESC, m.id DESC LIMIT 1",
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

        $resolved = self::resolveUserRoleAndLayanan($row);

        return array(
            'success' => true,
            'user' => array(
                'id_user'            => (int)$row['id_user'],
                'login'              => $row['login'],
                'nama_user'          => $row['nama_user'],
                'bidang'             => $row['bidang'] ?? '',
                'no_hp'              => $row['no_hp'] ?? '',
                'foto_profil'        => $row['nama_avatar'] ?? null,
                'role'               => $resolved['role'],
                'jenis_layanan_opti' => $resolved['jenis_layanan_opti']
            )
        );
    }

    /**
     * Ambil data lengkap profil user
     */
    public function getProfil(int $idUser) {
        $user = $this->db->exec(
            "SELECT u.*, m.role, m.jenis_layanan, m.role AS role_opti, m.jenis_layanan AS jenis_layanan_opti, m.akses_opti, m.akses_po
             FROM tb_arsipuser u
             LEFT JOIN opti_user_map m ON u.id_user = m.id_user AND m.status_tampil = 'show'
             WHERE u.id_user = ?
             ORDER BY m.is_active DESC, m.id DESC LIMIT 1",
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
     * Ambil data Ketua Tim aktif untuk jenis layanan tertentu secara dinamis:
     * 1. Prioritas utama: tb_arsipuser.id_struktural_tim sesuai tb_struktural_tim (2B)
     * 2. Fallback: opti_user_map (status_tampil = 'show' & is_active = 1)
     */
    public function getKetuaTim(string $jenisLayanan): ?array {
        $layananKey = strtolower(trim($jenisLayanan));
        $idStrukturalTim = self::STRUKTURAL_KETUA_MAP[$layananKey] ?? 0;

        if ($idStrukturalTim > 0) {
            try {
                $resStruktural = $this->db->exec(
                    "SELECT u.id_user, u.nama_user, u.login, u.no_hp, u.bidang, u.id_struktural_tim
                     FROM tb_arsipuser u
                     WHERE u.id_struktural_tim = ?
                       AND (u.status = 1 OR u.status = '1' OR u.status = 'aktif')
                     ORDER BY u.id_user ASC LIMIT 1",
                    array(1 => $idStrukturalTim)
                );
                if (!empty($resStruktural)) {
                    return $resStruktural[0];
                }
            } catch (\Throwable $e) {}
        }

        $res = $this->db->exec(
            "SELECT u.id_user, u.nama_user, u.login, u.no_hp, u.bidang, u.id_struktural_tim
             FROM tb_arsipuser u
             JOIN opti_user_map m ON u.id_user = m.id_user
             WHERE m.role = 'ketua_tim' AND m.jenis_layanan = ?
               AND m.is_active = 1 AND m.status_tampil = 'show'
             ORDER BY m.id DESC LIMIT 1",
            array(1 => $layananKey)
        );
        return $res[0] ?? null;
    }

    /**
     * Ambil peta id_user Ketua Tim aktif untuk semua divisi ('selulosa' => id_user, 'lingkungan' => id_user, dst.)
     */
    public static function getKatimMap(\DB\SQL $db, array $divisiList = array('selulosa', 'lingkungan')): array {
        $peta = array();
        foreach ($divisiList as $d) {
            $peta[$d] = 0;
        }
        $model = new self($db);
        foreach ($divisiList as $d) {
            $kt = $model->getKetuaTim($d);
            if (!empty($kt['id_user'])) {
                $peta[$d] = (int)$kt['id_user'];
            }
        }
        return $peta;
    }

    /**
     * Tetapkan / Ganti Ketua Tim di opti_user_map secara dinamis (Soft-hide pejabat lama, TIDAK mengubah id_struktural_tim pusat)
     */
    public function setKetuaTim(string $jenisLayanan, int $idUser): bool {
        $layananKey = strtolower(trim($jenisLayanan));

        // Soft-hide role ketua tim sebelumnya untuk divisi ini di opti_user_map (jangan hard-delete)
        $this->db->exec(
            "UPDATE opti_user_map
             SET is_active = 0, status_tampil = 'hide'
             WHERE role = 'ketua_tim' AND jenis_layanan = ?
               AND id_user != ?",
            array(1 => $layananKey, 2 => $idUser)
        );

        $aksesOpti = in_array($layananKey, array('selulosa', 'lingkungan', 'semua'), true) ? 1 : 0;

        // Cek apakah kombinasi (id_user, jenis_layanan, role='ketua_tim') sudah ada
        $existing = $this->db->exec(
            "SELECT id FROM opti_user_map WHERE id_user = ? AND jenis_layanan = ? AND role = 'ketua_tim' LIMIT 1",
            array(1 => $idUser, 2 => $layananKey)
        );

        if (!empty($existing)) {
            $this->db->exec(
                "UPDATE opti_user_map
                 SET role = 'ketua_tim', jenis_layanan = ?,
                     akses_opti = ?, akses_po = 1,
                     is_active = 1, status_tampil = 'show'
                 WHERE id = ?",
                array(1 => $layananKey, 2 => $aksesOpti, 3 => (int)$existing[0]['id'])
            );
        } else {
            $this->db->exec(
                "INSERT INTO opti_user_map (id_user, jenis_layanan, role, akses_opti, akses_po, is_active, status_tampil, created_at)
                 VALUES (?, ?, 'ketua_tim', ?, 1, 1, 'show', NOW())",
                array(1 => $idUser, 2 => $layananKey, 3 => $aksesOpti)
            );
        }
        return true;
    }

    /**
     * Ambil seluruh daftar personil internal aktif dari master tb_arsipuser untuk dropdown pilihan pejabat
     */
    public function getAllInternalUsers(): array {
        return $this->db->exec(
            "SELECT id_user, login, nama_user, bidang, no_hp, id_struktural_tim
             FROM tb_arsipuser 
             WHERE (status = 1 OR status = '1' OR status = 'aktif') AND nama_user IS NOT NULL AND nama_user != '' AND nama_user != '--Pilih--'
             ORDER BY nama_user ASC"
        );
    }
}
