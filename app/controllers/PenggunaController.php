<?php

/**
 * Controller Master Data Pengguna SILOPTI
 * Mengelola pengguna aktif ber-role di sistem SILOPTI bersumber dari tb_arsipuser.
 * Pengguna tanpa role di si_opti dianggap user biasa (view only) dan dapat didaftarkan/diberikan role.
 */
class PenggunaController extends Controller {

    /**
     * Definisi master role resmi di sistem SILOPTI
     */
    public static function getRoleOptions(): array {
        return array(
            'superadmin' => array(
                'label' => 'Super Administrator',
                'badge_class' => 'role-badge role-badge-superadmin',
                'badge_style' => '',
                'icon' => 'bi-shield-fill-check',
                'category' => 'admin',
                'desc' => 'Akses penuh ke seluruh modul dan konfigurasi sistem'
            ),
            'ketua_tim_selulosa' => array(
                'label' => 'Ketua Tim Selulosa',
                'badge_class' => 'role-badge role-badge-katim-selulosa',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Disposisi & penugasan teknis layanan Selulosa'
            ),
            'ketua_tim_lingkungan' => array(
                'label' => 'Ketua Tim Lingkungan',
                'badge_class' => 'role-badge role-badge-katim-lingkungan',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Disposisi & penugasan teknis layanan Lingkungan'
            ),
            'ketua_tim_mitra' => array(
                'label' => 'Ketua Tim Mitra',
                'badge_class' => 'role-badge role-badge-katim-mitra',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Koordinator layanan & administrasi kemitraan industri'
            ),
            'tim_kerja_selulosa' => array(
                'label' => 'Tim Kerja Selulosa',
                'badge_class' => 'role-badge role-badge-tk-selulosa',
                'badge_style' => '',
                'icon' => 'bi-eyedropper',
                'category' => 'teknis',
                'desc' => 'Pelaksana pengujian laboratorium Selulosa'
            ),
            'tim_kerja_lingkungan' => array(
                'label' => 'Tim Kerja Lingkungan',
                'badge_class' => 'role-badge role-badge-tk-lingkungan',
                'badge_style' => '',
                'icon' => 'bi-flask-fill',
                'category' => 'teknis',
                'desc' => 'Pelaksana analisis pengujian laboratorium Lingkungan'
            ),
            'tim_mitra_industri' => array(
                'label' => 'Tim Mitra Industri',
                'badge_class' => 'role-badge role-badge-mitra',
                'badge_style' => '',
                'icon' => 'bi-briefcase-fill',
                'category' => 'manajemen',
                'desc' => 'Penerimaan order dan administrasi klien'
            ),
            'ketua_tim_keuangan' => array(
                'label' => 'Ketua Tim Keuangan',
                'badge_class' => 'role-badge role-badge-katim-keuangan',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Koordinator verifikasi pembayaran & alur keuangan'
            ),
            'keuangan' => array(
                'label' => 'Tim Keuangan',
                'badge_class' => 'role-badge role-badge-keuangan',
                'badge_style' => '',
                'icon' => 'bi-wallet2',
                'category' => 'manajemen',
                'desc' => 'Verifikasi pembayaran'
            )
        );
    }

    /**
     * Master daftar role Terbatas / Tunggal (Super Administrator & Ketua Tim yang dibatasi maksimal 1 orang per role)
     */
    public static function getKetuaRoles(): array {
        return array(
            'superadmin'           => 'Super Administrator',
            'ketua_tim_selulosa'   => 'Ketua Tim Selulosa',
            'ketua_tim_lingkungan' => 'Ketua Tim Lingkungan',
            'ketua_tim_mitra'      => 'Ketua Tim Mitra',
            'ketua_tim_keuangan'   => 'Ketua Tim Keuangan'
        );
    }

    /**
     * Helper normalisasi alias role key
     */
    public static function normalizeRoleKey(string $roleKey): string {
        $roleAliases = array(
            'admin_order'               => 'tim_mitra_industri',
            'tim_mitra'                 => 'tim_mitra_industri',
            'katim_selulosa'            => 'ketua_tim_selulosa',
            'katim_lingkungan'          => 'ketua_tim_lingkungan',
            'katim_mitra'               => 'ketua_tim_mitra',
            'ketua_tim_mitra_industri'  => 'ketua_tim_mitra',
            'katim_keuangan'            => 'ketua_tim_keuangan',
            'tk_selulosa'               => 'tim_kerja_selulosa',
            'tk_lingkungan'             => 'tim_kerja_lingkungan'
        );
        return $roleAliases[$roleKey] ?? $roleKey;
    }

    /**
     * Halaman Utama Master Data Pengguna
     * Menampilkan daftar pengguna yang memiliki role aktif di SILOPTI
     * Route: GET /pengguna atau GET /data-pengguna
     */
    public function index($f3) {
        if (!$this->isLoggedIn()) {
            $f3->reroute('/login');
            return;
        }

        if (!$this->isSuperadmin()) {
            $this->setFlashError('Akses Ditolak: Halaman Master Data Pengguna hanya dapat diakses oleh Super Administrator.');
            $f3->reroute('/dashboard');
            return;
        }

        $searchQ = trim($f3->get('GET.q') ?? '');
        $filterRole = trim($f3->get('GET.role') ?? '');

        // Query data pengguna yang aktif ber-role di SILOPTI (si_opti tidak kosong dan bukan user biasa)
        $sql = "SELECT id_user AS id, 
                       nama_user AS nama_lengkap, 
                       si_opti AS role_sistem,
                       login,
                       bidang
                FROM tb_arsipuser
                WHERE (si_opti IS NOT NULL AND si_opti != '' AND si_opti != 'user')";

        $params = array();
        $paramIdx = 1;

        if (!empty($searchQ)) {
            $sql .= " AND (nama_user LIKE ? OR id_user = ?)";
            $params[$paramIdx++] = "%{$searchQ}%";
            $params[$paramIdx++] = is_numeric($searchQ) ? (int)$searchQ : 0;
        }

        if (!empty($filterRole)) {
            $sql .= " AND si_opti = ?";
            $params[$paramIdx++] = $filterRole;
        }

        $sql .= " ORDER BY id_user ASC";

        $daftarPenggunaRaw = $this->db->exec($sql, $params);

        // Format role label & badge agar representatif dan rapi
        $daftarPengguna = array();
        $countTotal = count($daftarPenggunaRaw);
        $countTeknis = 0;
        $countManajemen = 0;
        $countAdmin = 0;

        foreach ($daftarPenggunaRaw as $p) {
            $roleKey = trim($p['role_sistem'] ?? '');
            $roleInfo = self::resolveRoleMeta((int)$p['id'], $roleKey);

            $p['role_label'] = $roleInfo['label'];
            $p['role_badge_class'] = $roleInfo['badge_class'];
            $p['role_badge_style'] = $roleInfo['badge_style'] ?? '';
            $p['role_icon'] = $roleInfo['icon'] ?? 'bi-person-badge';
            $p['role_category'] = $roleInfo['category'];

            if ($roleInfo['category'] === 'teknis') {
                $countTeknis++;
            } elseif ($roleInfo['category'] === 'admin') {
                $countAdmin++;
            } else {
                $countManajemen++;
            }

            $daftarPengguna[] = $p;
        }

        // Ambil daftar pegawai dari tb_arsipuser yang BELUM memiliki role SILOPTI (tersedia untuk ditambahkan)
        $unassignedUsers = $this->db->exec("
            SELECT id_user, nama_user, login, bidang 
            FROM tb_arsipuser 
            WHERE (si_opti IS NULL OR si_opti = '' OR si_opti = 'user')
            ORDER BY nama_user ASC
        ");

        $totalDbPegawai = $this->db->exec("SELECT COUNT(*) AS cnt FROM tb_arsipuser");
        $countDbTotal = (int)($totalDbPegawai[0]['cnt'] ?? 0);

        // Hitung statistik untuk 5 kartu ringkasan: Tim Mitra, Tim Keuangan, Ka Tim OPTI, PIC Pelaksana, User Non-Role
        $statsRaw = $this->db->exec("
            SELECT 
                SUM(CASE WHEN si_opti IN ('tim_mitra_industri', 'admin_order', 'tim_mitra', 'ketua_tim_mitra', 'katim_mitra', 'ketua_tim_mitra_industri') THEN 1 ELSE 0 END) AS cnt_mitra,
                SUM(CASE WHEN si_opti IN ('keuangan', 'ketua_tim_keuangan', 'katim_keuangan') THEN 1 ELSE 0 END) AS cnt_keuangan,
                SUM(CASE WHEN si_opti IN ('ketua_tim_selulosa', 'ketua_tim_lingkungan', 'ketua_tim_mitra', 'ketua_tim_keuangan', 'katim_selulosa', 'katim_lingkungan', 'katim_mitra', 'katim_keuangan', 'ketua_tim_mitra_industri') THEN 1 ELSE 0 END) AS cnt_katim,
                SUM(CASE WHEN si_opti IN ('tim_kerja_selulosa', 'tim_kerja_lingkungan', 'tk_selulosa', 'tk_lingkungan') THEN 1 ELSE 0 END) AS cnt_pelaksana,
                SUM(CASE WHEN si_opti IS NULL OR si_opti = '' OR si_opti = 'user' THEN 1 ELSE 0 END) AS cnt_non_role
            FROM tb_arsipuser
        ");

        $cntMitra = (int)($statsRaw[0]['cnt_mitra'] ?? 0);
        $cntKeuangan = (int)($statsRaw[0]['cnt_keuangan'] ?? 0);
        $cntKatim = (int)($statsRaw[0]['cnt_katim'] ?? 0);
        $cntPelaksana = (int)($statsRaw[0]['cnt_pelaksana'] ?? 0);
        $cntNonRole = (int)($statsRaw[0]['cnt_non_role'] ?? 0);

        // Ambil daftar role tunggal (Superadmin & Ketua Tim) yang saat ini sedang aktif menjabat (untuk validasi & info UI)
        $currentKetuaList = $this->db->exec("
            SELECT id_user, nama_user, si_opti 
            FROM tb_arsipuser 
            WHERE si_opti IN ('superadmin', 'ketua_tim_selulosa', 'ketua_tim_lingkungan', 'ketua_tim_mitra', 'ketua_tim_keuangan', 'katim_selulosa', 'katim_lingkungan', 'katim_mitra', 'katim_keuangan', 'ketua_tim_mitra_industri')
        ");
        $occupiedKetua = array();
        foreach ($currentKetuaList as $k) {
            $norm = self::normalizeRoleKey($k['si_opti']);
            $occupiedKetua[$norm] = array(
                'id_user' => (int)$k['id_user'],
                'nama_user' => $k['nama_user']
            );
        }

        $f3->set('daftar_pengguna', $daftarPengguna);
        $f3->set('unassigned_users', $unassignedUsers);
        $f3->set('role_options', self::getRoleOptions());
        $f3->set('occupied_ketua', $occupiedKetua);
        $f3->set('occupied_ketua_json', json_encode($occupiedKetua, JSON_UNESCAPED_UNICODE));
        $f3->set('count_total', $countTotal);
        $f3->set('count_db_total', $countDbTotal);
        $f3->set('count_unassigned', count($unassignedUsers));
        $f3->set('cnt_mitra', $cntMitra);
        $f3->set('cnt_keuangan', $cntKeuangan);
        $f3->set('cnt_katim', $cntKatim);
        $f3->set('cnt_pelaksana', $cntPelaksana);
        $f3->set('cnt_non_role', $cntNonRole);
        $f3->set('count_teknis', $countTeknis);
        $f3->set('count_manajemen', $countManajemen);
        $f3->set('count_admin', $countAdmin);
        $f3->set('search_q', $searchQ);
        $f3->set('filter_role', $filterRole);

        $this->render('pengguna/index.html', 'Master Data Pengguna', 'pengguna');
    }

    /**
     * Daftarkan Pegawai dari tb_arsipuser ke SILOPTI dengan memberikan role
     * Route: POST /pengguna/simpan
     */
    public function simpan($f3) {
        if (!$this->isLoggedIn()) {
            $f3->reroute('/login');
            return;
        }

        if (!$this->isSuperadmin()) {
            $this->setFlashError('Akses Ditolak: Pendaftaran role pengguna hanya dapat dilakukan oleh Super Administrator.');
            $f3->reroute('/dashboard');
            return;
        }

        $idUser = (int)($f3->get('POST.id_user') ?? 0);
        $roleSistem = trim($f3->get('POST.role_sistem') ?? '');

        if ($idUser <= 0) {
            $this->setFlashError('Pilih pegawai yang valid dari database.');
            $f3->reroute('/pengguna');
            return;
        }

        $roles = self::getRoleOptions();
        if (!isset($roles[$roleSistem])) {
            $this->setFlashError('Pilih role sistem yang valid.');
            $f3->reroute('/pengguna');
            return;
        }

        // Validasi aturan: Posisi Superadmin & Ketua Tim hanya boleh dijabat oleh 1 orang pengguna
        $normRole = self::normalizeRoleKey($roleSistem);
        $ketuaRoles = self::getKetuaRoles();
        if (isset($ketuaRoles[$normRole])) {
            $aliasList = array($normRole);
            if ($normRole === 'ketua_tim_selulosa') {
                $aliasList[] = 'katim_selulosa';
            } elseif ($normRole === 'ketua_tim_lingkungan') {
                $aliasList[] = 'katim_lingkungan';
            } elseif ($normRole === 'ketua_tim_mitra') {
                $aliasList[] = 'katim_mitra';
                $aliasList[] = 'ketua_tim_mitra_industri';
            } elseif ($normRole === 'ketua_tim_keuangan') {
                $aliasList[] = 'katim_keuangan';
            }

            $placeholders = implode(',', array_fill(0, count($aliasList), '?'));
            $sqlCheck = "SELECT id_user, nama_user FROM tb_arsipuser WHERE si_opti IN ({$placeholders}) AND id_user != ?";
            $checkParams = array();
            $pIdx = 1;
            foreach ($aliasList as $al) {
                $checkParams[$pIdx++] = $al;
            }
            $checkParams[$pIdx] = $idUser;

            $existingKetua = $this->db->exec($sqlCheck, $checkParams);
            if (!empty($existingKetua)) {
                $currKetuaName = $existingKetua[0]['nama_user'];
                $currKetuaId = $existingKetua[0]['id_user'];
                $roleTitle = $ketuaRoles[$normRole];
                $this->setFlashError("Peran <strong>{$roleTitle}</strong> saat ini sudah dijabat oleh <strong>{$currKetuaName}</strong> (#{$currKetuaId}). Sesuai aturan sistem, posisi {$roleTitle} hanya dapat diisi oleh maksimal 1 orang. Silakan ubah atau cabut peran pengguna tersebut terlebih dahulu.");
                $f3->reroute('/pengguna');
                return;
            }
        }

        // Cek apakah pegawai ada di tb_arsipuser
        $userCheck = $this->db->exec("SELECT id_user, nama_user FROM tb_arsipuser WHERE id_user = ?", array(1 => $idUser));
        if (empty($userCheck)) {
            $this->setFlashError('Data pegawai tidak ditemukan di database.');
            $f3->reroute('/pengguna');
            return;
        }

        $namaUser = $userCheck[0]['nama_user'];

        // Update tb_arsipuser
        $this->db->exec(
            "UPDATE tb_arsipuser SET si_opti = ? WHERE id_user = ?",
            array(1 => $roleSistem, 2 => $idUser)
        );

        // Sinkronisasi ke opti_user_map
        $this->syncOptiUserMap($idUser, $roleSistem);

        $roleLabel = $roles[$roleSistem]['label'];
        $this->setFlashSuccess("Pengguna <strong>{$namaUser}</strong> berhasil didaftarkan ke SILOPTI sebagai <strong>{$roleLabel}</strong>.");
        $f3->reroute('/pengguna');
    }

    /**
     * Perbarui Role Pengguna SILOPTI
     * Route: POST /pengguna/ubah
     */
    public function ubah($f3) {
        if (!$this->isLoggedIn()) {
            $f3->reroute('/login');
            return;
        }

        if (!$this->isSuperadmin()) {
            $this->setFlashError('Akses Ditolak: Perubahan role pengguna hanya dapat dilakukan oleh Super Administrator.');
            $f3->reroute('/dashboard');
            return;
        }

        $idUser = (int)($f3->get('POST.id_user') ?? 0);
        $roleSistem = trim($f3->get('POST.role_sistem') ?? '');

        if ($idUser <= 0) {
            $this->setFlashError('ID pengguna tidak valid.');
            $f3->reroute('/pengguna');
            return;
        }

        $roles = self::getRoleOptions();
        if (!isset($roles[$roleSistem])) {
            $this->setFlashError('Role yang dipilih tidak valid.');
            $f3->reroute('/pengguna');
            return;
        }

        if ($idUser === 9006 && $roleSistem !== 'superadmin') {
            $this->setFlashError('Peran Super Administrator Utama (Fajriasa Erdanus) tidak dapat diubah ke peran lain demi integritas sistem.');
            $f3->reroute('/pengguna');
            return;
        }

        // Validasi aturan: Posisi Superadmin & Ketua Tim hanya boleh dijabat oleh 1 orang pengguna
        $normRole = self::normalizeRoleKey($roleSistem);
        $ketuaRoles = self::getKetuaRoles();
        if (isset($ketuaRoles[$normRole])) {
            $aliasList = array($normRole);
            if ($normRole === 'ketua_tim_selulosa') {
                $aliasList[] = 'katim_selulosa';
            } elseif ($normRole === 'ketua_tim_lingkungan') {
                $aliasList[] = 'katim_lingkungan';
            } elseif ($normRole === 'ketua_tim_mitra') {
                $aliasList[] = 'katim_mitra';
                $aliasList[] = 'ketua_tim_mitra_industri';
            } elseif ($normRole === 'ketua_tim_keuangan') {
                $aliasList[] = 'katim_keuangan';
            }

            $placeholders = implode(',', array_fill(0, count($aliasList), '?'));
            $sqlCheck = "SELECT id_user, nama_user FROM tb_arsipuser WHERE si_opti IN ({$placeholders}) AND id_user != ?";
            $checkParams = array();
            $pIdx = 1;
            foreach ($aliasList as $al) {
                $checkParams[$pIdx++] = $al;
            }
            $checkParams[$pIdx] = $idUser;

            $existingKetua = $this->db->exec($sqlCheck, $checkParams);
            if (!empty($existingKetua)) {
                $currKetuaName = $existingKetua[0]['nama_user'];
                $currKetuaId = $existingKetua[0]['id_user'];
                $roleTitle = $ketuaRoles[$normRole];
                $this->setFlashError("Peran <strong>{$roleTitle}</strong> saat ini sudah dijabat oleh <strong>{$currKetuaName}</strong> (#{$currKetuaId}). Sesuai aturan sistem, posisi {$roleTitle} hanya dapat diisi oleh maksimal 1 orang. Silakan ubah atau cabut peran pengguna tersebut terlebih dahulu.");
                $f3->reroute('/pengguna');
                return;
            }
        }

        $userCheck = $this->db->exec("SELECT id_user, nama_user FROM tb_arsipuser WHERE id_user = ?", array(1 => $idUser));
        if (empty($userCheck)) {
            $this->setFlashError('Data pengguna tidak ditemukan.');
            $f3->reroute('/pengguna');
            return;
        }

        $namaUser = $userCheck[0]['nama_user'];

        $this->db->exec(
            "UPDATE tb_arsipuser SET si_opti = ? WHERE id_user = ?",
            array(1 => $roleSistem, 2 => $idUser)
        );

        $this->syncOptiUserMap($idUser, $roleSistem);

        $roleLabel = $roles[$roleSistem]['label'];
        $this->setFlashSuccess("Role untuk <strong>{$namaUser}</strong> berhasil diperbarui menjadi <strong>{$roleLabel}</strong>.");
        $f3->reroute('/pengguna');
    }

    /**
     * Cabut Role Pengguna dari SILOPTI (mengembalikan ke user biasa / view-only)
     * Route: POST /pengguna/hapus
     */
    public function hapus($f3) {
        if (!$this->isLoggedIn()) {
            $f3->reroute('/login');
            return;
        }

        if (!$this->isSuperadmin()) {
            $this->setFlashError('Akses Ditolak: Pencabutan role pengguna hanya dapat dilakukan oleh Super Administrator.');
            $f3->reroute('/dashboard');
            return;
        }

        $idUser = (int)($f3->get('POST.id_user') ?? 0);

        if ($idUser === 9006) {
            $this->setFlashError('Akun Super Administrator Utama (Fajriasa Erdanus) tidak dapat dihapus/dicabut demi keamanan sistem.');
            $f3->reroute('/pengguna');
            return;
        }

        $userCheck = $this->db->exec("SELECT id_user, nama_user FROM tb_arsipuser WHERE id_user = ?", array(1 => $idUser));
        if (empty($userCheck)) {
            $this->setFlashError('Data pengguna tidak ditemukan.');
            $f3->reroute('/pengguna');
            return;
        }

        $namaUser = $userCheck[0]['nama_user'];

        // Reset si_opti menjadi kosong (kembali menjadi pegawai biasa / view only)
        $this->db->exec("UPDATE tb_arsipuser SET si_opti = '' WHERE id_user = ?", array(1 => $idUser));

        // Hapus mapping di opti_user_map
        $this->syncOptiUserMap($idUser, '');

        $this->setFlashSuccess("Hak akses role SILOPTI untuk <strong>{$namaUser}</strong> telah dicabut. Akun kembali menjadi pengguna biasa (view only).");
        $f3->reroute('/pengguna');
    }

    /**
     * Helper sinkronisasi hak akses ke tabel pendukung opti_user_map
     */
    private function syncOptiUserMap(int $idUser, string $siOpti) {
        if (empty($siOpti) || $siOpti === 'user') {
            $this->db->exec("DELETE FROM opti_user_map WHERE id_user = ?", array(1 => $idUser));
            return;
        }

        $roleOpti = 'tim_kerja';
        $layanan = 'semua';

        if ($siOpti === 'superadmin') {
            $roleOpti = 'superadmin';
            $layanan = 'semua';
        } elseif ($siOpti === 'ketua_tim_selulosa' || $siOpti === 'katim_selulosa') {
            $roleOpti = 'ketua_tim';
            $layanan = 'selulosa';
        } elseif ($siOpti === 'ketua_tim_lingkungan' || $siOpti === 'katim_lingkungan') {
            $roleOpti = 'ketua_tim';
            $layanan = 'lingkungan';
        } elseif ($siOpti === 'ketua_tim_mitra' || $siOpti === 'katim_mitra' || $siOpti === 'ketua_tim_mitra_industri') {
            $roleOpti = 'ketua_tim_mitra';
            $layanan = 'semua';
        } elseif ($siOpti === 'ketua_tim_keuangan' || $siOpti === 'katim_keuangan') {
            $roleOpti = 'ketua_tim_keuangan';
            $layanan = 'semua';
        } elseif ($siOpti === 'tim_kerja_selulosa' || $siOpti === 'tk_selulosa') {
            $roleOpti = 'tim_kerja';
            $layanan = 'selulosa';
        } elseif ($siOpti === 'tim_kerja_lingkungan' || $siOpti === 'tk_lingkungan') {
            $roleOpti = 'tim_kerja';
            $layanan = 'lingkungan';
        } elseif ($siOpti === 'tim_mitra_industri' || $siOpti === 'admin_order' || $siOpti === 'tim_mitra') {
            $roleOpti = 'admin_order';
            $layanan = 'semua';
        } elseif ($siOpti === 'keuangan') {
            $roleOpti = 'keuangan';
            $layanan = 'semua';
        }

        $exist = $this->db->exec("SELECT id FROM opti_user_map WHERE id_user = ?", array(1 => $idUser));
        if (!empty($exist)) {
            $this->db->exec(
                "UPDATE opti_user_map SET role_opti = ?, jenis_layanan_opti = ?, is_active = 1 WHERE id_user = ?",
                array(1 => $roleOpti, 2 => $layanan, 3 => $idUser)
            );
        } else {
            $this->db->exec(
                "INSERT INTO opti_user_map (id_user, jenis_layanan_opti, role_opti, is_active, created_at) VALUES (?, ?, ?, 1, NOW())",
                array(1 => $idUser, 2 => $layanan, 3 => $roleOpti)
            );
        }
    }

    /**
     * Helper pemetaan Role di Sistem ke Label dan Badge Color
     */
    public static function resolveRoleMeta(int $id, string $roleKey): array {
        $normKey = self::normalizeRoleKey($roleKey);

        $roles = self::getRoleOptions();
        if (isset($roles[$normKey])) {
            $meta = $roles[$normKey];
            $meta['badge_style'] = $meta['badge_style'] ?? '';
            return $meta;
        }

        return array(
            'label' => !empty($roleKey) ? ucwords(str_replace('_', ' ', $roleKey)) : 'Pengguna Biasa (View Only)',
            'badge_class' => 'role-badge role-badge-default',
            'badge_style' => '',
            'icon' => 'bi-person',
            'category' => 'manajemen',
            'desc' => '-'
        );
    }
}
