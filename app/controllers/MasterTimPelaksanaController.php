<?php
/**
 * MasterTimPelaksanaController
 *
 * Data Master "Tim Pelaksana" -- daftar nama + Tugas/Tanggung Jawab baku, DIPISAH
 * PER DIVISI (Lingkungan / Selulosa) lewat TABEL DB YANG TERPISAH JUGA:
 *   - master_tim_pelaksana_lingkungan
 *   - master_tim_pelaksana_selulosa
 * (bukan satu tabel dengan kolom pembeda divisi -- sesuai yang diminta).
 *
 * Dipakai di form PO Kegiatan (lihat PoKegiatanController::create()/edit() yang
 * manggil MasterTimPelaksanaController::ambilAktif($db, $divisi) buat ngisi
 * dropdown "Nama" di bagian Tim Pelaksana -- begitu nama dipilih, kolom
 * Tugas/Tanggung Jawab otomatis keisi dari data master ini).
 *
 * Akses kelola (tambah/edit/nonaktifkan/hapus): Superadmin (bebas divisi mana aja)
 * atau Ketua Tim (CUMA buat divisinya sendiri -- dicek dari role + layanan sesi).
 * Gak ada role baru yang ditambahin -- tetap pakai role & layanan yang udah ada.
 *
 * Kompatibel PHP 7.2.
 */
class MasterTimPelaksanaController extends Controller {

    const DIVISI_VALID = ['lingkungan', 'selulosa'];

    /* ============================================================
     * HELPER STATIS (dipanggil dari controller lain, mis. PoKegiatanController)
     * ============================================================ */

    /** Nama tabel fisik buat divisi tertentu. Melempar exception kalau divisi gak valid (whitelist -- aman dari SQL injection via nama tabel). */
    public static function tabelUntukDivisi($divisi) {
        $d = strtolower(trim((string) $divisi));
        if (!in_array($d, self::DIVISI_VALID, true)) {
            throw new \InvalidArgumentException('Divisi tidak valid: ' . $d);
        }
        return 'master_tim_pelaksana_' . $d;
    }

    /** Bikin tabelnya kalau belum ada (self-healing, gak perlu migration manual). */
    public static function ensureTabel($db, $divisi) {
        $tabel = self::tabelUntukDivisi($divisi);
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS `$tabel` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    nama VARCHAR(150) NOT NULL,
                    tugas_tanggung_jawab VARCHAR(255) NOT NULL,
                    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
                    dibuat_oleh INT UNSIGNED NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\Exception $e) {
            // biarin -- kalau gagal (mis. gak ada izin DDL), query SELECT/INSERT di
            // bawah tetap dicoba jalan apa adanya, cuma gak self-healing.
        }
        return $tabel;
    }

    /**
     * Ambil daftar yang statusnya 'aktif' buat divisi tertentu -- dipakai PoKegiatanController
     * buat ngisi dropdown Tim Pelaksana di form PO. Return [] kalau divisi gak valid / gagal baca.
     */
    public static function ambilAktif($db, $divisi) {
        try {
            $tabel = self::ensureTabel($db, $divisi);
            return $db->exec("SELECT id, nama, tugas_tanggung_jawab FROM `$tabel` WHERE status = 'aktif' ORDER BY nama ASC");
        } catch (\Exception $e) {
            return [];
        }
    }

    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /master-tim-pelaksana/@divisi */
    public function index($f3, $params) {
        $this->requireAuth();
        $divisi = strtolower(trim((string) ($params['divisi'] ?? '')));
        if (!in_array($divisi, self::DIVISI_VALID, true)) {
            $f3->error(404, 'Divisi tidak dikenali.');
            return;
        }
        $this->izinkan($divisi);

        $tabel = self::ensureTabel($this->db, $divisi);

        $search = trim((string) $f3->get('GET.q'));
        $sql = "SELECT * FROM `$tabel` WHERE 1=1";
        $sqlParams = [];
        if ($search !== '') {
            $sql .= " AND nama LIKE ?";
            $sqlParams[] = '%' . $search . '%';
        }
        $sql .= " ORDER BY nama ASC";

        $errorMessage = null;
        try {
            $rows = $this->db->exec($sql, $sqlParams);
        } catch (\Exception $e) {
            $rows = [];
            $errorMessage = 'Gagal memuat daftar: ' . $e->getMessage();
        }

        // [BARU] Daftar nama buat dropdown "Nama" di modal Tambah/Edit -- diambil dari
        // DB Sekretariat (sil2020, tabel tb_arsipuser), BUKAN dari DB silopti2026 ini.
        // Data Tim Pelaksana sendiri (hasil pilihan + tugas/tanggung jawab) tetep
        // kesimpen di DB silopti2026 (tabel master_tim_pelaksana_<divisi>).
        $daftarPegawaiSekretariat = [];
        try {
            $daftarPegawaiSekretariat = $this->dbSekretariat->exec(
                "SELECT id_user, nama_user FROM tb_arsipuser
                 WHERE (status = 1 OR status = '1' OR status = 'aktif')
                 ORDER BY nama_user ASC"
            );
        } catch (\Exception $e) {
            $daftarPegawaiSekretariat = [];
        }

        $f3->set('divisi', $divisi);
        $f3->set('divisi_label', ucfirst($divisi));
        $f3->set('daftar_json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('daftar_pegawai_json', json_encode($daftarPegawaiSekretariat, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('search', $search);
        $f3->set('error_message', $errorMessage);

        $this->render('master-tim-pelaksana/index.html', 'Tim Pelaksana ' . ucfirst($divisi), 'master_tim_pelaksana_' . $divisi);
    }

    /* ============================================================
     * API
     * ============================================================ */

    /** POST /master-tim-pelaksana/@divisi/simpan */
    public function simpan($f3, $params) {
        $divisi = $this->divisiDariParam($params);
        $this->izinkanJson($divisi);
        $tabel = self::ensureTabel($this->db, $divisi);

        [$nama, $tugas] = $this->validasiInput($f3);

        try {
            $this->db->exec(
                "INSERT INTO `$tabel` (nama, tugas_tanggung_jawab, status, dibuat_oleh, created_at, updated_at)
                 VALUES (?, ?, 'aktif', ?, NOW(), NOW())",
                [1 => $nama, 2 => $tugas, 3 => (int) $this->getUserId()]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
        $this->keluarkanJson(['ok' => true]);
    }

    /** POST /master-tim-pelaksana/@divisi/@id/update */
    public function update($f3, $params) {
        $divisi = $this->divisiDariParam($params);
        $this->izinkanJson($divisi);
        $tabel = self::ensureTabel($this->db, $divisi);
        $id = (int) ($params['id'] ?? 0);

        [$nama, $tugas] = $this->validasiInput($f3);

        try {
            $this->db->exec(
                "UPDATE `$tabel` SET nama = ?, tugas_tanggung_jawab = ?, updated_at = NOW() WHERE id = ?",
                [1 => $nama, 2 => $tugas, 3 => $id]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memperbarui: ' . $e->getMessage()], 500);
        }
        $this->keluarkanJson(['ok' => true]);
    }

    /** POST /master-tim-pelaksana/@divisi/@id/toggle-status */
    public function toggleStatus($f3, $params) {
        $divisi = $this->divisiDariParam($params);
        $this->izinkanJson($divisi);
        $tabel = self::ensureTabel($this->db, $divisi);
        $id = (int) ($params['id'] ?? 0);

        try {
            $this->db->exec(
                "UPDATE `$tabel` SET status = IF(status = 'aktif', 'nonaktif', 'aktif'), updated_at = NOW() WHERE id = ?",
                [1 => $id]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengubah status.'], 500);
        }
        $this->keluarkanJson(['ok' => true]);
    }

    /** POST /master-tim-pelaksana/@divisi/@id/hapus */
    public function hapus($f3, $params) {
        $divisi = $this->divisiDariParam($params);
        $this->izinkanJson($divisi);
        $tabel = self::ensureTabel($this->db, $divisi);
        $id = (int) ($params['id'] ?? 0);

        try {
            $this->db->exec("DELETE FROM `$tabel` WHERE id = ?", [1 => $id]);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menghapus.'], 500);
        }
        $this->keluarkanJson(['ok' => true]);
    }

    /* ============================================================
     * HELPER
     * ============================================================ */

    protected function divisiDariParam($params) {
        $divisi = strtolower(trim((string) ($params['divisi'] ?? '')));
        if (!in_array($divisi, self::DIVISI_VALID, true)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Divisi tidak valid.'], 422);
        }
        return $divisi;
    }

    protected function validasiInput($f3) {
        $nama = trim((string) $f3->get('POST.nama'));
        $tugas = trim((string) $f3->get('POST.tugas_tanggung_jawab'));
        if ($nama === '' || $tugas === '') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Nama dan Tugas/Tanggung Jawab wajib diisi.'], 422);
        }
        return [$nama, $tugas];
    }

    /** Wajib login + (Superadmin ATAU Ketua Tim divisi ini). Halaman biasa (reroute). */
    protected function izinkan($divisi) {
        if ($this->isSuperadmin()) { return; }
        $role = $this->getUserRole();
        $layanan = $this->getUserLayanan();
        if ($role === 'ketua_tim' && $layanan === $divisi) { return; }
        $this->f3->error(403, 'Halaman ini hanya untuk Superadmin dan Ketua Tim divisi ' . ucfirst($divisi) . '.');
        exit;
    }

    /** Sama kayak izinkan() tapi jawabnya JSON (buat endpoint AJAX). */
    protected function izinkanJson($divisi) {
        $this->requireAuth();
        if ($this->isSuperadmin()) { return; }
        $role = $this->getUserRole();
        $layanan = $this->getUserLayanan();
        if ($role === 'ketua_tim' && $layanan === $divisi) { return; }
        $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke divisi ini.'], 403);
    }

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
