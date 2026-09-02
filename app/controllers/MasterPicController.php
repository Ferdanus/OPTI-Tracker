<?php

/**
 * Controller Master Data PIC Peneliti (SILOPTI)
 * Mengelola penugasan peran Peneliti/Penguji langsung pada kolom tb_arsipuser.si_opti
 */
class MasterPicController extends Controller {

    /**
     * Halaman Utama Daftar Master PIC Peneliti
     * Route: GET /master-pic
     */
    public function index($f3) {
        $role = $this->getUserRole();
        if ($role !== 'superadmin' && $role !== 'ketua_tim') {
            $this->setFlashError('Akses ditolak. Halaman ini hanya dapat diakses oleh Super Admin dan Ketua Tim.');
            $f3->reroute('/po');
            return;
        }

        $filterDivisi = trim($f3->get('GET.divisi') ?? '');
        $searchQ = trim($f3->get('GET.q') ?? '');

        // Query Daftar PIC yang saat ini terdaftar di tb_arsipuser.si_opti
        $sql = "SELECT u.id_user, u.login, u.nama_user, u.no_hp, u.bidang, u.id_struktural, u.si_opti,
                       CASE 
                           WHEN u.si_opti LIKE '%lingkungan%' THEN 'lingkungan'
                           WHEN u.si_opti LIKE '%selulosa%' THEN 'selulosa'
                           ELSE 'semua'
                       END AS divisi,
                       (SELECT COUNT(*) FROM opti_proposal_riset pr WHERE pr.pic_penyusun_id = u.id_user) AS total_proposal,
                       (SELECT COUNT(*) FROM order_layanan o WHERE o.pic_proposal_id = u.id_user AND o.status NOT IN ('selesai', 'ditolak')) AS total_order_aktif
                FROM tb_arsipuser u
                WHERE (u.si_opti LIKE 'tim_kerja%' OR u.si_opti = 'pic')
                  AND (u.status = 1 OR u.status = '1' OR u.status = 'aktif')";

        $params = array();
        $idx = 1;

        if (!empty($filterDivisi) && in_array($filterDivisi, ['selulosa', 'lingkungan'])) {
            $sql .= " AND u.si_opti LIKE ?";
            $params[$idx++] = "%{$filterDivisi}%";
        }

        if (!empty($searchQ)) {
            $sql .= " AND (u.nama_user LIKE ? OR u.login LIKE ? OR u.bidang LIKE ? OR u.no_hp LIKE ?)";
            $wildcard = "%{$searchQ}%";
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
        }

        $sql .= " ORDER BY u.nama_user ASC";
        $daftarPic = $this->db->exec($sql, $params);

        // Hitung Metrik Ringkasan
        $countSelulosa = 0;
        $countLingkungan = 0;
        $allPic = $this->db->exec("SELECT si_opti FROM tb_arsipuser WHERE (si_opti LIKE 'tim_kerja%' OR si_opti = 'pic') AND (status = 1 OR status = '1' OR status = 'aktif')");
        foreach ($allPic as $row) {
            if (strpos($row['si_opti'], 'lingkungan') !== false) {
                $countLingkungan++;
            } else {
                $countSelulosa++;
            }
        }

        // Daftar Kandidat Pegawai Balai di tb_arsipuser yang belum menjadi PIC
        $kandidatPegawai = $this->db->exec(
            "SELECT u.id_user, u.login, u.nama_user, u.bidang, u.no_hp
             FROM tb_arsipuser u
             WHERE (u.status = 1 OR u.status = '1' OR u.status = 'aktif')
               AND (u.si_opti IS NULL OR (u.si_opti NOT LIKE 'tim_kerja%' AND u.si_opti != 'pic'))
             ORDER BY u.nama_user ASC"
        );

        $f3->set('daftar_pic', $daftarPic);
        $f3->set('kandidat_pegawai', $kandidatPegawai);
        $f3->set('count_total', count($allPic));
        $f3->set('count_selulosa', $countSelulosa);
        $f3->set('count_lingkungan', $countLingkungan);
        $f3->set('filter_divisi', $filterDivisi);
        $f3->set('search_q', $searchQ);

        $this->render('master_pic/index.html', 'Data Master PIC Peneliti', 'master_pic');
    }

    /**
     * Tambahkan Penugasan PIC Peneliti Baru
     * Route: POST /master-pic/simpan
     */
    public function simpan($f3) {
        $role = $this->getUserRole();
        if ($role !== 'superadmin' && $role !== 'ketua_tim') {
            $this->setFlashError('Akses ditolak.');
            $f3->reroute('/po');
            return;
        }

        $userId = (int)$f3->get('POST.id_user');
        $divisi = strtolower(trim($f3->get('POST.divisi') ?? 'selulosa'));

        if ($userId <= 0) {
            $this->setFlashError('Silakan pilih pegawai balai terlebih dahulu.');
            $f3->reroute('/master-pic');
            return;
        }

        if (!in_array($divisi, ['selulosa', 'lingkungan'])) {
            $divisi = 'selulosa';
        }

        $roleVal = 'tim_kerja_' . $divisi;

        // 1. Update ke tb_arsipuser.si_opti
        $this->db->exec(
            "UPDATE tb_arsipuser SET si_opti = ? WHERE id_user = ?",
            array(1 => $roleVal, 2 => $userId)
        );

        // 2. Sinkronkan ke opti_user_map jika tabel ada
        try {
            $cek = $this->db->exec("SELECT id FROM opti_user_map WHERE id_user = ?", array(1 => $userId));
            if (!empty($cek)) {
                $this->db->exec(
                    "UPDATE opti_user_map SET role_opti = 'tim_kerja', jenis_layanan_opti = ?, is_active = 1 WHERE id_user = ?",
                    array(1 => $divisi, 2 => $userId)
                );
            } else {
                $this->db->exec(
                    "INSERT INTO opti_user_map (id_user, jenis_layanan_opti, role_opti, is_active, created_at) VALUES (?, ?, 'tim_kerja', 1, NOW())",
                    array(1 => $userId, 2 => $divisi)
                );
            }
        } catch (\Exception $e) {
            // Lanjut jika tabel opti_user_map tidak digunakan
        }

        $userRow = $this->db->exec("SELECT nama_user FROM tb_arsipuser WHERE id_user = ?", array(1 => $userId));
        $namaUser = $userRow[0]['nama_user'] ?? "User #{$userId}";

        $this->setFlashSuccess("Pegawai <strong>{$namaUser}</strong> berhasil ditugaskan sebagai PIC Peneliti ({$divisi}).");
        $f3->reroute('/master-pic');
    }

    /**
     * Ubah Divisi Penugasan PIC Peneliti
     * Route: POST /master-pic/ubah-divisi
     */
    public function ubahDivisi($f3) {
        $role = $this->getUserRole();
        if ($role !== 'superadmin' && $role !== 'ketua_tim') {
            $this->setFlashError('Akses ditolak.');
            $f3->reroute('/po');
            return;
        }

        $userId = (int)$f3->get('POST.id_user');
        $divisi = strtolower(trim($f3->get('POST.divisi') ?? 'selulosa'));

        if ($userId <= 0 || !in_array($divisi, ['selulosa', 'lingkungan'])) {
            $this->setFlashError('Data tidak valid.');
            $f3->reroute('/master-pic');
            return;
        }

        $roleVal = 'tim_kerja_' . $divisi;

        $this->db->exec(
            "UPDATE tb_arsipuser SET si_opti = ? WHERE id_user = ?",
            array(1 => $roleVal, 2 => $userId)
        );

        try {
            $this->db->exec(
                "UPDATE opti_user_map SET jenis_layanan_opti = ?, is_active = 1 WHERE id_user = ?",
                array(1 => $divisi, 2 => $userId)
            );
        } catch (\Exception $e) {}

        $this->setFlashSuccess("Divisi penugasan PIC Peneliti berhasil diperbarui menjadi {$divisi}.");
        $f3->reroute('/master-pic');
    }

    /**
     * Cabut Penugasan PIC Peneliti
     * Route: POST /master-pic/hapus
     */
    public function hapus($f3) {
        $role = $this->getUserRole();
        if ($role !== 'superadmin' && $role !== 'ketua_tim') {
            $this->setFlashError('Akses ditolak.');
            $f3->reroute('/po');
            return;
        }

        $userId = (int)$f3->get('POST.id_user');
        if ($userId <= 0) {
            $this->setFlashError('ID Pengguna tidak valid.');
            $f3->reroute('/master-pic');
            return;
        }

        // Ambil nama user untuk pesan
        $userRow = $this->db->exec("SELECT nama_user FROM tb_arsipuser WHERE id_user = ?", array(1 => $userId));
        $namaUser = $userRow[0]['nama_user'] ?? "User #{$userId}";

        // Set si_opti menjadi NULL pada tb_arsipuser
        $this->db->exec(
            "UPDATE tb_arsipuser SET si_opti = NULL WHERE id_user = ?",
            array(1 => $userId)
        );

        try {
            $this->db->exec(
                "UPDATE opti_user_map SET is_active = 0, role_opti = '' WHERE id_user = ?",
                array(1 => $userId)
            );
        } catch (\Exception $e) {}

        $this->setFlashSuccess("Penugasan PIC Peneliti untuk <strong>{$namaUser}</strong> telah dinonaktifkan.");
        $f3->reroute('/master-pic');
    }
}
