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
            'ketua_tim_pengujian' => array(
                'label' => 'Ketua Tim Pengujian',
                'badge_class' => 'role-badge role-badge-katim-pengujian',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Disposisi & penugasan teknis layanan Pengujian'
            ),
            'ketua_tim_kalibrasi' => array(
                'label' => 'Ketua Tim Kalibrasi',
                'badge_class' => 'role-badge role-badge-katim-kalibrasi',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Disposisi & penugasan teknis layanan Kalibrasi'
            ),
            'ketua_tim_sertifikasi' => array(
                'label' => 'Ketua Tim Sertifikasi',
                'badge_class' => 'role-badge role-badge-katim-sertifikasi',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Disposisi & penugasan teknis layanan Sertifikasi'
            ),
            'ketua_tim_pendampingan' => array(
                'label' => 'Ketua Tim Pendampingan Teknis',
                'badge_class' => 'role-badge role-badge-katim-pendampingan',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Disposisi & penugasan layanan Pendampingan Teknis'
            ),
            'ketua_tim_inspeksi' => array(
                'label' => 'Ketua Tim Inspeksi',
                'badge_class' => 'role-badge role-badge-katim-inspeksi',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Disposisi & penugasan teknis layanan Inspeksi'
            ),
            'ketua_tim_profisiensi' => array(
                'label' => 'Ketua Tim Uji Profisiensi',
                'badge_class' => 'role-badge role-badge-katim-profisiensi',
                'badge_style' => '',
                'icon' => 'bi-award-fill',
                'category' => 'manajemen',
                'desc' => 'Disposisi & penugasan teknis layanan Uji Profisiensi'
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
                'desc' => 'Pelaksana pengujian laboratorium Selulosa',
                'layanan' => 'selulosa',
                'role_map' => 'tim_kerja',
                'akses_opti' => 1,
                'akses_po' => 1
            ),
            'tim_kerja_lingkungan' => array(
                'label' => 'Tim Kerja Lingkungan',
                'badge_class' => 'role-badge role-badge-tk-lingkungan',
                'badge_style' => '',
                'icon' => 'bi-flask-fill',
                'category' => 'teknis',
                'desc' => 'Pelaksana analisis pengujian laboratorium Lingkungan',
                'layanan' => 'lingkungan',
                'role_map' => 'tim_kerja',
                'akses_opti' => 1,
                'akses_po' => 1
            ),
            'pic_po_selulosa' => array(
                'label' => 'PO Selulosa',
                'badge_class' => 'role-badge role-badge-tk-selulosa',
                'badge_style' => '',
                'icon' => 'bi-journal-check',
                'category' => 'teknis',
                'desc' => 'Penyusun Petunjuk Operasional (PO) layanan Selulosa',
                'layanan' => 'selulosa',
                'role_map' => 'pic_po',
                'akses_opti' => 1,
                'akses_po' => 1
            ),
            'pic_po_lingkungan' => array(
                'label' => 'PO Lingkungan',
                'badge_class' => 'role-badge role-badge-tk-lingkungan',
                'badge_style' => '',
                'icon' => 'bi-journal-check',
                'category' => 'teknis',
                'desc' => 'Penyusun Petunjuk Operasional (PO) layanan Lingkungan',
                'layanan' => 'lingkungan',
                'role_map' => 'pic_po',
                'akses_opti' => 1,
                'akses_po' => 1
            ),
            'pic_po_pengujian' => array(
                'label' => 'PO Pengujian',
                'badge_class' => 'role-badge role-badge-default',
                'badge_style' => '',
                'icon' => 'bi-journal-check',
                'category' => 'teknis',
                'desc' => 'Penyusun Petunjuk Operasional (PO) layanan Pengujian',
                'layanan' => 'pengujian',
                'role_map' => 'pic_po',
                'akses_opti' => 0,
                'akses_po' => 1
            ),
            'pic_po_kalibrasi' => array(
                'label' => 'PO Kalibrasi',
                'badge_class' => 'role-badge role-badge-default',
                'badge_style' => '',
                'icon' => 'bi-journal-check',
                'category' => 'teknis',
                'desc' => 'Penyusun Petunjuk Operasional (PO) layanan Kalibrasi',
                'layanan' => 'kalibrasi',
                'role_map' => 'pic_po',
                'akses_opti' => 0,
                'akses_po' => 1
            ),
            'pic_po_sertifikasi' => array(
                'label' => 'PO Sertifikasi',
                'badge_class' => 'role-badge role-badge-default',
                'badge_style' => '',
                'icon' => 'bi-journal-check',
                'category' => 'teknis',
                'desc' => 'Penyusun Petunjuk Operasional (PO) layanan Sertifikasi',
                'layanan' => 'sertifikasi',
                'role_map' => 'pic_po',
                'akses_opti' => 0,
                'akses_po' => 1
            ),
            'pic_po_pendampingan' => array(
                'label' => 'PO Pendampingan Teknis',
                'badge_class' => 'role-badge role-badge-default',
                'badge_style' => '',
                'icon' => 'bi-journal-check',
                'category' => 'teknis',
                'desc' => 'Penyusun Petunjuk Operasional (PO) layanan Pendampingan Teknis',
                'layanan' => 'pendampingan',
                'role_map' => 'pic_po',
                'akses_opti' => 0,
                'akses_po' => 1
            ),
            'pic_po_inspeksi' => array(
                'label' => 'PO Inspeksi',
                'badge_class' => 'role-badge role-badge-default',
                'badge_style' => '',
                'icon' => 'bi-journal-check',
                'category' => 'teknis',
                'desc' => 'Penyusun Petunjuk Operasional (PO) layanan Inspeksi',
                'layanan' => 'inspeksi',
                'role_map' => 'pic_po',
                'akses_opti' => 0,
                'akses_po' => 1
            ),
            'pic_po_profisiensi' => array(
                'label' => 'PO Uji Profisiensi',
                'badge_class' => 'role-badge role-badge-default',
                'badge_style' => '',
                'icon' => 'bi-journal-check',
                'category' => 'teknis',
                'desc' => 'Penyusun Petunjuk Operasional (PO) layanan Uji Profisiensi',
                'layanan' => 'profisiensi',
                'role_map' => 'pic_po',
                'akses_opti' => 0,
                'akses_po' => 1
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
            ),
            'humas' => array(
                'label' => 'Sub Tim Humas',
                'badge_class' => 'role-badge role-badge-humas',
                'badge_style' => '',
                'icon' => 'bi-megaphone-fill',
                'category' => 'manajemen',
                'desc' => 'Layanan informasi publik, sub tim humas & publikasi balai'
            ),
            'ppk' => array(
                'label' => 'PPK',
                'badge_class' => 'role-badge role-badge-ppk',
                'badge_style' => '',
                'icon' => 'bi-person-badge-fill',
                'category' => 'pejabat',
                'desc' => 'Pejabat Pembuat Komitmen verifikasi & legalitas kegiatan'
            )
        );
    }

    /**
     * Master daftar role Terbatas / Tunggal (Super Administrator & Ketua Tim yang dibatasi maksimal 1 orang per role)
     */
    public static function getKetuaRoles(): array {
        return array(
            'superadmin'             => 'Super Administrator',
            'ketua_tim_selulosa'     => 'Ketua Tim Selulosa',
            'ketua_tim_lingkungan'   => 'Ketua Tim Lingkungan',
            'ketua_tim_pengujian'    => 'Ketua Tim Pengujian',
            'ketua_tim_kalibrasi'    => 'Ketua Tim Kalibrasi',
            'ketua_tim_sertifikasi'  => 'Ketua Tim Sertifikasi',
            'ketua_tim_pendampingan' => 'Ketua Tim Pendampingan Teknis',
            'ketua_tim_inspeksi'     => 'Ketua Tim Inspeksi',
            'ketua_tim_profisiensi'  => 'Ketua Tim Uji Profisiensi',
            'ketua_tim_mitra'        => 'Ketua Tim Mitra',
            'ketua_tim_keuangan'     => 'Ketua Tim Keuangan'
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
            'katim_pengujian'           => 'ketua_tim_pengujian',
            'katim_kalibrasi'           => 'ketua_tim_kalibrasi',
            'katim_sertifikasi'         => 'ketua_tim_sertifikasi',
            'katim_pendampingan'        => 'ketua_tim_pendampingan',
            'katim_inspeksi'            => 'ketua_tim_inspeksi',
            'katim_profisiensi'         => 'ketua_tim_profisiensi',
            'katim_mitra'               => 'ketua_tim_mitra',
            'ketua_tim_mitra_industri'  => 'ketua_tim_mitra',
            'katim_keuangan'            => 'ketua_tim_keuangan',
            'tk_selulosa'               => 'tim_kerja_selulosa',
            'tk_lingkungan'             => 'tim_kerja_lingkungan',
            'pic_proposal_selulosa'     => 'tim_kerja_selulosa',
            'pic_proposal_lingkungan'   => 'tim_kerja_lingkungan'
        );
        return $roleAliases[$roleKey] ?? $roleKey;
    }

    /**
     * Konversi baris opti_user_map (role + jenis_layanan) menjadi role_key standar UI
     */
    public static function mapEntryToRoleKey(string $mapRole, string $mapLayanan): string {
        $mapRole = strtolower(trim($mapRole));
        $mapLayanan = strtolower(trim($mapLayanan));
        if ($mapRole === '' || $mapRole === 'user') return '';

        if ($mapRole === 'pic_po' && $mapLayanan !== '' && $mapLayanan !== 'semua') {
            return 'pic_po_' . $mapLayanan;
        }
        if ($mapRole === 'pic_proposal' && $mapLayanan !== '' && $mapLayanan !== 'semua') {
            return 'tim_kerja_' . $mapLayanan;
        }
        if ($mapRole === 'ketua_tim' && $mapLayanan !== '' && $mapLayanan !== 'semua') {
            return 'ketua_tim_' . $mapLayanan;
        }
        if ($mapRole === 'tim_kerja' && $mapLayanan !== '' && $mapLayanan !== 'semua') {
            return 'tim_kerja_' . $mapLayanan;
        }
        if ($mapRole === 'tim_mitra' || $mapRole === 'admin_order') {
            return 'tim_mitra_industri';
        }
        return self::normalizeRoleKey($mapRole);
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
        $filterTab = trim($f3->get('GET.tab') ?? 'semua');

        // Query data pengguna yang aktif ber-role di SILOPTI / PO
        $sql = "SELECT u.id_user AS id, 
                       u.nama_user AS nama_lengkap, 
                       u.si_opti AS role_sistem,
                       u.login,
                       u.bidang,
                       u.id_struktural_tim,
                       m.role AS map_role,
                       m.jenis_layanan AS map_layanan,
                       m.role AS map_role_opti,
                       m.jenis_layanan AS map_layanan_opti,
                       COALESCE(m.akses_opti, 1) AS akses_opti,
                       COALESCE(m.akses_po, 1) AS akses_po
                FROM tb_arsipuser u
                LEFT JOIN opti_user_map m ON u.id_user = m.id_user AND m.status_tampil = 'show' AND m.is_active = 1
                WHERE (
                    (u.si_opti IS NOT NULL AND u.si_opti != '' AND u.si_opti != 'user')
                    OR (m.id IS NOT NULL AND m.role != '' AND m.role != 'user')
                    OR u.id_struktural_tim IN (170, 180, 190, 140, 150, 100, 210, 160, 240, 30, 31)
                )";

        $params = array();
        $paramIdx = 1;

        if (!empty($searchQ)) {
            $sql .= " AND (u.nama_user LIKE ? OR u.id_user = ?)";
            $params[$paramIdx++] = "%{$searchQ}%";
            $params[$paramIdx++] = is_numeric($searchQ) ? (int)$searchQ : 0;
        }

        if (!empty($filterRole)) {
            $sql .= " AND (u.si_opti = ? OR m.role = ?)";
            $params[$paramIdx++] = $filterRole;
        $params[$paramIdx++] = $filterRole;
        }

        $sql .= " GROUP BY u.id_user ORDER BY u.id_user ASC";

        $daftarPenggunaRaw = $this->db->exec($sql, $params);

        // Ambil seluruh baris multi-role aktif dari opti_user_map untuk digabungkan per user
        $allMapRows = $this->db->exec("
            SELECT id_user, role, jenis_layanan, akses_opti, akses_po
            FROM opti_user_map
            WHERE is_active = 1 AND status_tampil = 'show'
            ORDER BY id ASC
        ");
        $userMultiRolesMap = array();
        foreach ($allMapRows as $mr) {
            $uid = (int)$mr['id_user'];
            $rName = trim($mr['role'] ?? '');
            $lName = trim($mr['jenis_layanan'] ?? '');
            $rKey = self::mapEntryToRoleKey($rName, $lName);
            if ($rKey !== '') {
                if (!isset($userMultiRolesMap[$uid])) {
                    $userMultiRolesMap[$uid] = array();
                }
                if (!in_array($rKey, $userMultiRolesMap[$uid], true)) {
                    $userMultiRolesMap[$uid][] = $rKey;
                }
            }
        }

        // Format role label & badge agar mendukung multi-role per user
        $daftarPengguna = array();
        $countTotal = count($daftarPenggunaRaw);
        $countTeknis = 0;
        $countManajemen = 0;
        $countAdmin = 0;
        $countOpti = 0;
        $countPo = 0;

        $strukturalRoleMap = array(
            170 => 'ketua_tim_selulosa',
            180 => 'tim_kerja_selulosa',
            190 => 'ketua_tim_lingkungan',
            140 => 'ketua_tim_pengujian',
            150 => 'ketua_tim_kalibrasi',
            100 => 'ketua_tim_sertifikasi',
            210 => 'ketua_tim_pendampingan',
            160 => 'ketua_tim_inspeksi',
            240 => 'ketua_tim_mitra',
            30  => 'ketua_tim_keuangan',
            31  => 'ppk'
        );

        $nonOptiRoles = array(
            'ppk',
            'ketua_tim_pengujian', 'ketua_tim_kalibrasi', 'ketua_tim_sertifikasi',
            'ketua_tim_pendampingan', 'ketua_tim_inspeksi', 'ketua_tim_profisiensi',
            'pic_po_pengujian', 'pic_po_kalibrasi', 'pic_po_sertifikasi',
            'pic_po_pendampingan', 'pic_po_inspeksi', 'pic_po_profisiensi'
        );

        foreach ($daftarPenggunaRaw as $p) {
            $uid = (int)$p['id'];
            $roleKey = trim($p['role_sistem'] ?? '');
            $idStruktural = (int)($p['id_struktural_tim'] ?? 0);
            $mapRole = trim($p['map_role'] ?: ($p['map_role_opti'] ?? ''));
            $mapLayanan = trim($p['map_layanan'] ?: ($p['map_layanan_opti'] ?? ''));

            $userRoleKeys = array();

            // 1. Dari si_opti atau id_struktural_tim
            if (!empty($roleKey) && $roleKey !== 'user') {
                $userRoleKeys[] = self::normalizeRoleKey($roleKey);
            } elseif (isset($strukturalRoleMap[$idStruktural])) {
                $userRoleKeys[] = $strukturalRoleMap[$idStruktural];
            }

            // 2. Gabungkan dengan semua role aktif di opti_user_map (mendukung >1 role per user, misal Tim Kerja Selulosa + PO Selulosa)
            if (!empty($userMultiRolesMap[$uid])) {
                foreach ($userMultiRolesMap[$uid] as $mrKey) {
                    $normMr = self::normalizeRoleKey($mrKey);
                    if (!in_array($normMr, $userRoleKeys, true)) {
                        $userRoleKeys[] = $normMr;
                    }
                }
            } elseif (empty($userRoleKeys)) {
                $fallbackKey = self::mapEntryToRoleKey($mapRole, $mapLayanan);
                if ($fallbackKey !== '') {
                    $userRoleKeys[] = $fallbackKey;
                }
            }

            if (empty($userRoleKeys)) {
                $userRoleKeys[] = 'user';
            }

            $primaryRoleKey = $userRoleKeys[0];
            $p['role_sistem'] = $primaryRoleKey;
            $p['roles_list'] = array();
            $p['roles_csv'] = implode(',', $userRoleKeys);

            $userAksesOpti = 0;
            $userAksesPo = 0;
            $labelsArr = array();

            foreach ($userRoleKeys as $rk) {
                $rMeta = self::resolveRoleMeta($uid, $rk);
                $p['roles_list'][] = array(
                    'key' => $rk,
                    'label' => $rMeta['label'],
                    'badge_class' => $rMeta['badge_class']
                );
                $labelsArr[] = $rMeta['label'];

                if (in_array($rk, $nonOptiRoles, true)) {
                    $userAksesPo = 1;
                } else {
                    $ro = isset($rMeta['akses_opti']) ? (int)$rMeta['akses_opti'] : 1;
                    $rp = isset($rMeta['akses_po']) ? (int)$rMeta['akses_po'] : 1;
                    if ($ro === 1) $userAksesOpti = 1;
                    if ($rp === 1) $userAksesPo = 1;
                }
            }

            $p['akses_opti'] = $userAksesOpti;
            $p['akses_po'] = $userAksesPo;

            if ($p['akses_opti'] == 1) $countOpti++;
            if ($p['akses_po'] == 1) $countPo++;

            $roleInfo = self::resolveRoleMeta($uid, $primaryRoleKey);
            $p['role_label'] = implode(', ', $labelsArr);
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
        $unassignedUsersRaw = $this->db->exec("
            SELECT id_user, nama_user, login, bidang 
            FROM tb_arsipuser 
            WHERE (si_opti IS NULL OR si_opti = '' OR si_opti = 'user')
            ORDER BY nama_user ASC
        ");

        $unassignedUsers = array();
        foreach ($unassignedUsersRaw as $u) {
            $rawBidang = trim((string)($u['bidang'] ?? ''));
            if (empty($rawBidang) || strtoupper($rawBidang) === 'NULL' || $rawBidang === '-' || strtoupper($rawBidang) === 'NONE') {
                $u['bidang_clean'] = '';
            } else {
                $u['bidang_clean'] = strtoupper($rawBidang);
            }
            $unassignedUsers[] = $u;
        }

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

        // Susun pengelompokan hierarki Pengguna per Divisi Layanan (Ketua Tim / Pejabat Utama sebagai baris induk, Sub Tim / Tim Kerja / PO sebagai sub-baris yang dapat dibuka ketika diklik)
        $divisiSchema = array(
            'selulosa' => array(
                'key' => 'selulosa',
                'title' => 'OPTI Selulosa',
                'lead_roles' => array('ketua_tim_selulosa'),
                'sub_roles' => array('tim_kerja_selulosa', 'pic_po_selulosa')
            ),
            'lingkungan' => array(
                'key' => 'lingkungan',
                'title' => 'OPTI Lingkungan',
                'lead_roles' => array('ketua_tim_lingkungan'),
                'sub_roles' => array('tim_kerja_lingkungan', 'pic_po_lingkungan')
            ),
            'mitra' => array(
                'key' => 'mitra',
                'title' => 'Mitra Industri',
                'lead_roles' => array('ketua_tim_mitra'),
                'sub_roles' => array('tim_mitra_industri', 'humas')
            ),
            'keuangan' => array(
                'key' => 'keuangan',
                'title' => 'Keuangan',
                'lead_roles' => array('ketua_tim_keuangan'),
                'sub_roles' => array('keuangan')
            ),
            'pengujian' => array(
                'key' => 'pengujian',
                'title' => 'Pengujian',
                'lead_roles' => array('ketua_tim_pengujian'),
                'sub_roles' => array('pic_po_pengujian')
            ),
            'kalibrasi' => array(
                'key' => 'kalibrasi',
                'title' => 'Kalibrasi',
                'lead_roles' => array('ketua_tim_kalibrasi'),
                'sub_roles' => array('pic_po_kalibrasi')
            ),
            'sertifikasi' => array(
                'key' => 'sertifikasi',
                'title' => 'Sertifikasi',
                'lead_roles' => array('ketua_tim_sertifikasi'),
                'sub_roles' => array('pic_po_sertifikasi')
            ),
            'pendampingan' => array(
                'key' => 'pendampingan',
                'title' => 'Pendampingan Teknis',
                'lead_roles' => array('ketua_tim_pendampingan'),
                'sub_roles' => array('pic_po_pendampingan')
            ),
            'inspeksi' => array(
                'key' => 'inspeksi',
                'title' => 'Inspeksi',
                'lead_roles' => array('ketua_tim_inspeksi'),
                'sub_roles' => array('pic_po_inspeksi')
            ),
            'profisiensi' => array(
                'key' => 'profisiensi',
                'title' => 'Uji Profisiensi',
                'lead_roles' => array('ketua_tim_profisiensi'),
                'sub_roles' => array('pic_po_profisiensi')
            ),
            'ppk' => array(
                'key' => 'ppk',
                'title' => 'Pejabat Pembuat Komitmen (PPK)',
                'lead_roles' => array('ppk'),
                'sub_roles' => array()
            ),
            'superadmin' => array(
                'key' => 'superadmin',
                'title' => 'Super Administrator',
                'lead_roles' => array('superadmin'),
                'sub_roles' => array()
            )
        );

        $groupedDivisi = array();
        $assignedIds = array();

        foreach ($divisiSchema as $dKey => $dConf) {
            $leaders = array();
            $members = array();

            foreach ($daftarPengguna as $uItem) {
                if (isset($assignedIds[$uItem['id']])) continue;
                $uRoles = explode(',', $uItem['roles_csv'] ?? $uItem['role_sistem']);
                $isLeader = false;
                $isSub = false;
                foreach ($uRoles as $ur) {
                    if (in_array($ur, $dConf['lead_roles'], true)) {
                        $isLeader = true;
                        break;
                    }
                    if (in_array($ur, $dConf['sub_roles'], true)) {
                        $isSub = true;
                    }
                }
                if ($isLeader) {
                    $leaders[] = $uItem;
                    $assignedIds[$uItem['id']] = true;
                } elseif ($isSub) {
                    $members[] = $uItem;
                    $assignedIds[$uItem['id']] = true;
                }
            }

            if (empty($leaders) && empty($members)) {
                continue;
            }

            // Jika Ketua Tim belum ada tapi ada anggota sub tim, jadikan anggota pertama sebagai representasi induk divisi
            $primaryUser = !empty($leaders) ? array_shift($leaders) : array_shift($members);
            // Jika ada lebih dari 1 pejabat di lead_roles (mis. PPK), masukkan sisanya ke sub-list atau baris tersendiri
            $subUsers = array_merge($leaders, $members);

            $aksesOptiGroup = (int)$primaryUser['akses_opti'];
            $aksesPoGroup = (int)$primaryUser['akses_po'];
            $allRolesInGroup = array($primaryUser['role_sistem']);
            $allSearchText = strtolower($primaryUser['id'] . ' ' . $primaryUser['nama_lengkap'] . ' ' . $primaryUser['role_label'] . ' ' . $dConf['title']);

            foreach ($subUsers as $su) {
                if ((int)$su['akses_opti'] === 1) $aksesOptiGroup = 1;
                if ((int)$su['akses_po'] === 1) $aksesPoGroup = 1;
                $allRolesInGroup[] = $su['role_sistem'];
                $allSearchText .= ' ' . strtolower($su['id'] . ' ' . $su['nama_lengkap'] . ' ' . $su['role_label']);
            }

            $groupedDivisi[] = array(
                'divisi_key' => $dKey,
                'divisi_title' => $dConf['title'],
                'leader' => $primaryUser,
                'members' => $subUsers,
                'member_count' => count($subUsers),
                'total_in_divisi' => 1 + count($subUsers),
                'akses_opti' => $aksesOptiGroup,
                'akses_po' => $aksesPoGroup,
                'roles_csv' => implode(',', array_unique($allRolesInGroup)),
                'search_blob' => $allSearchText
            );
        }

        // Tangani jika ada user dengan role lain yang belum terpetakan ke divisiSchema
        foreach ($daftarPengguna as $uItem) {
            if (!isset($assignedIds[$uItem['id']])) {
                $groupedDivisi[] = array(
                    'divisi_key' => 'lainnya_' . $uItem['id'],
                    'divisi_title' => $uItem['role_label'],
                    'leader' => $uItem,
                    'members' => array(),
                    'member_count' => 0,
                    'total_in_divisi' => 1,
                    'akses_opti' => (int)$uItem['akses_opti'],
                    'akses_po' => (int)$uItem['akses_po'],
                    'roles_csv' => $uItem['role_sistem'],
                    'search_blob' => strtolower($uItem['id'] . ' ' . $uItem['nama_lengkap'] . ' ' . $uItem['role_label'])
                );
            }
        }

        $f3->set('grouped_divisi', $groupedDivisi);
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
        $f3->set('count_opti', $countOpti);
        $f3->set('count_po', $countPo);
        $f3->set('filter_tab', $filterTab);
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
        $rawRoleInput = trim($f3->get('POST.role_sistem') ?? '');

        if ($idUser <= 0) {
            $this->setFlashError('Pilih pegawai yang valid dari database.');
            $f3->reroute('/pengguna');
            return;
        }

        $selectedRoles = array_values(array_unique(array_filter(array_map('trim', explode(',', $rawRoleInput)))));
        if (empty($selectedRoles)) {
            $this->setFlashError('Pilih minimal 1 role sistem yang valid.');
            $f3->reroute('/pengguna');
            return;
        }

        $roles = self::getRoleOptions();
        $ketuaRoles = self::getKetuaRoles();
        $validRoles = array();
        $roleLabels = array();

        foreach ($selectedRoles as $rs) {
            if ($rs === 'superadmin') {
                $this->setFlashError('Role Super Administrator bersifat permanen dan tidak dapat ditetapkan kepada pengguna lain.');
                $f3->reroute('/pengguna');
                return;
            }
            if (!isset($roles[$rs])) {
                continue;
            }
            $normRole = self::normalizeRoleKey($rs);
            if (isset($ketuaRoles[$normRole])) {
                $aliasList = array($normRole);
                if ($normRole === 'ketua_tim_selulosa') $aliasList[] = 'katim_selulosa';
                elseif ($normRole === 'ketua_tim_lingkungan') $aliasList[] = 'katim_lingkungan';
                elseif ($normRole === 'ketua_tim_mitra') { $aliasList[] = 'katim_mitra'; $aliasList[] = 'ketua_tim_mitra_industri'; }
                elseif ($normRole === 'ketua_tim_keuangan') $aliasList[] = 'katim_keuangan';

                $placeholders = implode(',', array_fill(0, count($aliasList), '?'));
                $sqlCheck = "SELECT id_user, nama_user FROM tb_arsipuser WHERE si_opti IN ({$placeholders}) AND id_user != ?";
                $checkParams = array();
                $pIdx = 1;
                foreach ($aliasList as $al) { $checkParams[$pIdx++] = $al; }
                $checkParams[$pIdx] = $idUser;

                $existingKetua = $this->db->exec($sqlCheck, $checkParams);
                if (!empty($existingKetua)) {
                    $currKetuaName = $existingKetua[0]['nama_user'];
                    $currKetuaId = $existingKetua[0]['id_user'];
                    $roleTitle = $ketuaRoles[$normRole];
                    $this->setFlashError("Peran <strong>{$roleTitle}</strong> saat ini sudah dijabat oleh <strong>{$currKetuaName}</strong> (#{$currKetuaId}). Sesuai aturan sistem, posisi {$roleTitle} hanya dapat diisi oleh maksimal 1 orang.");
                    $f3->reroute('/pengguna');
                    return;
                }
            }
            $validRoles[] = $rs;
            $roleLabels[] = $roles[$rs]['label'];
        }

        if (empty($validRoles)) {
            $this->setFlashError('Pilih role sistem yang valid.');
            $f3->reroute('/pengguna');
            return;
        }

        $userCheck = $this->db->exec("SELECT id_user, nama_user FROM tb_arsipuser WHERE id_user = ?", array(1 => $idUser));
        if (empty($userCheck)) {
            $this->setFlashError('Data pegawai tidak ditemukan di database.');
            $f3->reroute('/pengguna');
            return;
        }

        $namaUser = $userCheck[0]['nama_user'];
        $primaryRole = $validRoles[0];

        // Update tb_arsipuser dengan role utama
        $this->db->exec(
            "UPDATE tb_arsipuser SET si_opti = ? WHERE id_user = ?",
            array(1 => $primaryRole, 2 => $idUser)
        );

        // Sinkronisasi seluruh multi-role ke opti_user_map
        $this->syncMultiOptiUserMap($idUser, $validRoles);

        $roleLabelText = implode(', ', $roleLabels);
        $this->setFlashSuccess("Pengguna <strong>{$namaUser}</strong> berhasil didaftarkan sebagai <strong>{$roleLabelText}</strong>.");
        $f3->reroute('/pengguna');
    }

    /**
     * Perbarui Role Pengguna SILOPTI (Mendukung Multi-Role)
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
        $rawRoleInput = trim($f3->get('POST.role_sistem') ?? '');

        if ($idUser <= 0) {
            $this->setFlashError('ID pengguna tidak valid.');
            $f3->reroute('/pengguna');
            return;
        }

        if ($idUser === 9006) {
            $this->setFlashError('Peran Super Administrator Utama bersifat permanen dan tidak dapat diubah.');
            $f3->reroute('/pengguna');
            return;
        }

        $selectedRoles = array_values(array_unique(array_filter(array_map('trim', explode(',', $rawRoleInput)))));
        if (empty($selectedRoles)) {
            $this->setFlashError('Pilih minimal 1 role yang valid.');
            $f3->reroute('/pengguna');
            return;
        }

        $roles = self::getRoleOptions();
        $ketuaRoles = self::getKetuaRoles();
        $validRoles = array();
        $roleLabels = array();

        foreach ($selectedRoles as $rs) {
            if ($rs === 'superadmin') {
                $this->setFlashError('Peran Super Administrator Utama bersifat permanen dan tidak dapat dialihkan ke pengguna lain.');
                $f3->reroute('/pengguna');
                return;
            }
            if (!isset($roles[$rs])) {
                continue;
            }
            $normRole = self::normalizeRoleKey($rs);
            if (isset($ketuaRoles[$normRole])) {
                $aliasList = array($normRole);
                if ($normRole === 'ketua_tim_selulosa') $aliasList[] = 'katim_selulosa';
                elseif ($normRole === 'ketua_tim_lingkungan') $aliasList[] = 'katim_lingkungan';
                elseif ($normRole === 'ketua_tim_mitra') { $aliasList[] = 'katim_mitra'; $aliasList[] = 'ketua_tim_mitra_industri'; }
                elseif ($normRole === 'ketua_tim_keuangan') $aliasList[] = 'katim_keuangan';

                $placeholders = implode(',', array_fill(0, count($aliasList), '?'));
                $sqlCheck = "SELECT id_user, nama_user FROM tb_arsipuser WHERE si_opti IN ({$placeholders}) AND id_user != ?";
                $checkParams = array();
                $pIdx = 1;
                foreach ($aliasList as $al) { $checkParams[$pIdx++] = $al; }
                $checkParams[$pIdx] = $idUser;

                $existingKetua = $this->db->exec($sqlCheck, $checkParams);
                if (!empty($existingKetua)) {
                    $currKetuaName = $existingKetua[0]['nama_user'];
                    $currKetuaId = $existingKetua[0]['id_user'];
                    $roleTitle = $ketuaRoles[$normRole];
                    $this->setFlashError("Peran <strong>{$roleTitle}</strong> saat ini sudah dijabat oleh <strong>{$currKetuaName}</strong> (#{$currKetuaId}). Sesuai aturan sistem, posisi {$roleTitle} hanya dapat diisi oleh maksimal 1 orang.");
                    $f3->reroute('/pengguna');
                    return;
                }
            }
            $validRoles[] = $rs;
            $roleLabels[] = $roles[$rs]['label'];
        }

        if (empty($validRoles)) {
            $this->setFlashError('Role yang dipilih tidak valid.');
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
        $primaryRole = $validRoles[0];

        $this->db->exec(
            "UPDATE tb_arsipuser SET si_opti = ? WHERE id_user = ?",
            array(1 => $primaryRole, 2 => $idUser)
        );

        $this->syncMultiOptiUserMap($idUser, $validRoles);

        $roleLabelText = implode(', ', $roleLabels);
        $this->setFlashSuccess("Role untuk <strong>{$namaUser}</strong> berhasil diperbarui menjadi <strong>{$roleLabelText}</strong>.");
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
     * Sinkronisasi multi-role ke opti_user_map (mendukung 1 user memiliki beberapa role sekaligus, misal Tim Kerja Selulosa + PO Selulosa)
     */
    private function syncMultiOptiUserMap(int $idUser, array $roleKeys) {
        // Nonaktifkan/hide semua role lama user terlebih dahulu
        $this->db->exec("UPDATE opti_user_map SET is_active = 0, status_tampil = 'hide' WHERE id_user = ?", array(1 => $idUser));

        foreach ($roleKeys as $siOpti) {
            $this->upsertSingleRoleMap($idUser, $siOpti);
        }
    }

    private function upsertSingleRoleMap(int $idUser, string $siOpti) {
        if (empty($siOpti) || $siOpti === 'user') return;

        $roleOpti = 'tim_kerja';
        $layanan = 'semua';
        $aksesOpti = 1;
        $aksesPo = 1;

        if ($siOpti === 'superadmin') {
            $roleOpti = 'superadmin';
            $layanan = 'semua';
        } elseif (preg_match('/^(?:ketua_tim|katim)_([a-zA-Z0-9_]+)$/', $siOpti, $mKatim)) {
            $sub = strtolower($mKatim[1]);
            if ($sub === 'mitra' || $sub === 'mitra_industri') {
                $roleOpti = 'ketua_tim_mitra';
                $layanan = 'semua';
            } elseif ($sub === 'keuangan') {
                $roleOpti = 'ketua_tim_keuangan';
                $layanan = 'semua';
            } else {
                $roleOpti = 'ketua_tim';
                $layanan = $sub;
                $aksesOpti = in_array($sub, array('selulosa', 'lingkungan'), true) ? 1 : 0;
            }
        } elseif (preg_match('/^pic_po_([a-zA-Z0-9_]+)$/', $siOpti, $mPicPo)) {
            $roleOpti = 'pic_po';
            $layanan = strtolower($mPicPo[1]);
            $aksesOpti = in_array($layanan, array('selulosa', 'lingkungan'), true) ? 1 : 0;
            $aksesPo = 1;
        } elseif ($siOpti === 'tim_kerja_selulosa' || $siOpti === 'tk_selulosa') {
            $roleOpti = 'tim_kerja';
            $layanan = 'selulosa';
        } elseif ($siOpti === 'tim_kerja_lingkungan' || $siOpti === 'tk_lingkungan') {
            $roleOpti = 'tim_kerja';
            $layanan = 'lingkungan';
        } elseif ($siOpti === 'tim_mitra_industri' || $siOpti === 'admin_order' || $siOpti === 'tim_mitra') {
            $roleOpti = 'tim_mitra';
            $layanan = 'mitra';
        } elseif ($siOpti === 'keuangan') {
            $roleOpti = 'keuangan';
            $layanan = 'semua';
        } elseif ($siOpti === 'humas') {
            $roleOpti = 'humas';
            $layanan = 'semua';
        } elseif ($siOpti === 'ppk') {
            $roleOpti = 'ppk';
            $layanan = 'semua';
            $aksesOpti = 0;
            $aksesPo = 1;
        }

        $exist = $this->db->exec(
            "SELECT id FROM opti_user_map WHERE id_user = ? AND jenis_layanan = ? AND role = ? LIMIT 1",
            array(1 => $idUser, 2 => $layanan, 3 => $roleOpti)
        );
        if (!empty($exist)) {
            $this->db->exec(
                "UPDATE opti_user_map
                 SET role = ?, jenis_layanan = ?,
                     akses_opti = ?, akses_po = ?, is_active = 1, status_tampil = 'show'
                 WHERE id = ?",
                array(1 => $roleOpti, 2 => $layanan, 3 => $aksesOpti, 4 => $aksesPo, 5 => (int)$exist[0]['id'])
            );
        } else {
            $this->db->exec(
                "INSERT INTO opti_user_map (id_user, jenis_layanan, role, akses_opti, akses_po, is_active, status_tampil, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, 'show', NOW())",
                array(1 => $idUser, 2 => $layanan, 3 => $roleOpti, 4 => $aksesOpti, 5 => $aksesPo)
            );
        }
    }

    /**
     * Helper sinkronisasi hak akses ke tabel pendukung opti_user_map (Soft-hide, Multi-Layanan, akses_opti & akses_po)
     */
    private function syncOptiUserMap(int $idUser, string $siOpti) {
        if (empty($siOpti) || $siOpti === 'user') {
            $this->db->exec("UPDATE opti_user_map SET is_active = 0, status_tampil = 'hide' WHERE id_user = ?", array(1 => $idUser));
            return;
        }

        $roleOpti = 'tim_kerja';
        $layanan = 'semua';
        $aksesOpti = 1;
        $aksesPo = 1;

        if ($siOpti === 'superadmin') {
            $roleOpti = 'superadmin';
            $layanan = 'semua';
        } elseif (preg_match('/^(?:ketua_tim|katim)_([a-zA-Z0-9_]+)$/', $siOpti, $mKatim)) {
            $sub = strtolower($mKatim[1]);
            if ($sub === 'mitra' || $sub === 'mitra_industri') {
                $roleOpti = 'ketua_tim_mitra';
                $layanan = 'semua';
            } elseif ($sub === 'keuangan') {
                $roleOpti = 'ketua_tim_keuangan';
                $layanan = 'semua';
            } else {
                $roleOpti = 'ketua_tim';
                $layanan = $sub;
                $aksesOpti = in_array($sub, array('selulosa', 'lingkungan'), true) ? 1 : 0;
            }
        } elseif (preg_match('/^pic_po_([a-zA-Z0-9_]+)$/', $siOpti, $mPicPo)) {
            $roleOpti = 'pic_po';
            $layanan = strtolower($mPicPo[1]);
            $aksesOpti = in_array($layanan, array('selulosa', 'lingkungan'), true) ? 1 : 0;
            $aksesPo = 1;
        } elseif (preg_match('/^pic_proposal_([a-zA-Z0-9_]+)$/', $siOpti, $mPicProp)) {
            $roleOpti = 'pic_proposal';
            $layanan = strtolower($mPicProp[1]);
            $aksesOpti = 1;
            $aksesPo = 0;
        } elseif ($siOpti === 'tim_kerja_selulosa' || $siOpti === 'tk_selulosa') {
            $roleOpti = 'tim_kerja';
            $layanan = 'selulosa';
        } elseif ($siOpti === 'tim_kerja_lingkungan' || $siOpti === 'tk_lingkungan') {
            $roleOpti = 'tim_kerja';
            $layanan = 'lingkungan';
        } elseif ($siOpti === 'tim_mitra_industri' || $siOpti === 'admin_order' || $siOpti === 'tim_mitra') {
            $roleOpti = 'tim_mitra';
            $layanan = 'mitra';
        } elseif ($siOpti === 'keuangan') {
            $roleOpti = 'keuangan';
            $layanan = 'semua';
        } elseif ($siOpti === 'humas') {
            $roleOpti = 'humas';
            $layanan = 'semua';
        } elseif ($siOpti === 'ppk') {
            $roleOpti = 'ppk';
            $layanan = 'semua';
            $aksesOpti = 0;
            $aksesPo = 1;
        }

        // Soft-hide role lain yang sedang aktif untuk user ini jika diubah dari form tunggal
        $this->db->exec(
            "UPDATE opti_user_map SET is_active = 0, status_tampil = 'hide' WHERE id_user = ? AND NOT (jenis_layanan = ? AND role = ?)",
            array(1 => $idUser, 2 => $layanan, 3 => $roleOpti)
        );

        $exist = $this->db->exec(
            "SELECT id FROM opti_user_map WHERE id_user = ? AND jenis_layanan = ? AND role = ? LIMIT 1",
            array(1 => $idUser, 2 => $layanan, 3 => $roleOpti)
        );
        if (!empty($exist)) {
            $this->db->exec(
                "UPDATE opti_user_map
                 SET role = ?, jenis_layanan = ?,
                     akses_opti = ?, akses_po = ?, is_active = 1, status_tampil = 'show'
                 WHERE id = ?",
                array(1 => $roleOpti, 2 => $layanan, 3 => $aksesOpti, 4 => $aksesPo, 5 => (int)$exist[0]['id'])
            );
        } else {
            $this->db->exec(
                "INSERT INTO opti_user_map (id_user, jenis_layanan, role, akses_opti, akses_po, is_active, status_tampil, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, 'show', NOW())",
                array(1 => $idUser, 2 => $layanan, 3 => $roleOpti, 4 => $aksesOpti, 5 => $aksesPo)
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