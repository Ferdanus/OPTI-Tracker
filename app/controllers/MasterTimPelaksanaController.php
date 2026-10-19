<?php
/**
 * MasterTimPelaksanaController -- Data Master "Tim Pelaksana PO" + "Jabatan Pelaksana PO".
 *
 * - Jabatan Pelaksana PO dikelola di MasterJabatanPelaksanaController (file terpisah, khusus Superadmin).
 * - Tim Pelaksana PO (po_tim_pelaksana): pegawai + layanan + status aktif (TANPA jabatan; jabatan dipilih
 *   langsung di form PO dari Master Jabatan Pelaksana PO). Aksi di tabel hanya hapus (tidak ada ubah).
 *     Superadmin   : semua layanan.
 *     Ketua Tim    : otomatis terkunci ke layanannya sendiri, boleh tambah/ubah/hapus.
 *     Tim Kerja    : terkunci ke layanannya, HANYA LIHAT (ubah konstanta TIM_KERJA_KELOLA kalau mau boleh kelola).
 * - Dipakai form PO lewat MasterTimPelaksanaController::ambilAktif($db, $divisi)
 *   (sudah dipanggil PoKegiatanController::ambilMasterPelaksanaUntukPo).
 *
 *   GET  /master-tim-pelaksana                      halaman Tim Pelaksana
 *   POST /master-tim-pelaksana/tambah               layanan, items=[{peg}]      (bisa banyak sekaligus)
 *   POST /master-tim-pelaksana/ubah                 (dinonaktifkan: anggota tidak punya data yang bisa diubah)
 *   POST /master-tim-pelaksana/hapus                ids=[..]
 *   POST /master-tim-pelaksana/@id/aktif            aktif=0|1
 *
 * Kompatibel PHP 7.2.
 */
class MasterTimPelaksanaController extends Controller {

    /** true = Tim Kerja boleh tambah/ubah/hapus di layanannya sendiri. */
    const TIM_KERJA_KELOLA = false;

    /** kunci layanan => label */
    protected static $LAYANAN = [
        'selulosa'     => 'OPTI Selulosa',
        'lingkungan'   => 'OPTI Lingkungan',
        'pengujian'    => 'Pengujian',
        'kalibrasi'    => 'Kalibrasi',
        'sertifikasi'  => 'Sertifikasi',
        'pendampingan' => 'Pendampingan Teknis',
        'inspeksi'     => 'Inspeksi',
        'profisiensi'  => 'Uji Profisiensi',
    ];

    /* ============================================================
     * AKSES
     * ============================================================ */

    /** Layanan yang terkunci untuk user ini ('' = bebas / superadmin, null = tidak punya akses). */
    protected function layananTerkunci() {
        if ($this->isSuperadmin()) { return ''; }
        $role = $this->getUserRole();
        if ($role === 'ketua_tim') {
            $l = strtolower(trim((string) $this->getUserLayanan()));
        } elseif ($role === 'tim_kerja') {
            $l = strtolower(trim((string) $this->getDivisiTimKerja()));
        } else {
            return null;
        }
        return isset(self::$LAYANAN[$l]) ? $l : null;
    }

    protected function bisaKelola() {
        if ($this->isSuperadmin()) { return true; }
        $role = $this->getUserRole();
        if ($role === 'ketua_tim') { return $this->layananTerkunci() !== null; }
        if ($role === 'tim_kerja') { return self::TIM_KERJA_KELOLA && $this->layananTerkunci() !== null; }
        return false;
    }

    protected function izinkan($json = false, $tulis = false, $khususSuperadmin = false) {
        $this->requireAuth();
        $lock = $this->layananTerkunci();
        $ok = ($lock !== null);
        if ($ok && $tulis) { $ok = $this->bisaKelola(); }
        if ($ok && $khususSuperadmin) { $ok = $this->isSuperadmin(); }
        if ($ok) { return; }
        if ($json) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses untuk aksi ini.'], 403); }
        $this->setFlashError('Anda tidak punya akses ke halaman Master Tim Pelaksana PO.');
        $this->f3->reroute('/dashboard');
        exit;
    }

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ============================================================
     * SKEMA (dibuat otomatis kalau belum ada)
     * ============================================================ */

    protected function pastikanSkema() {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS po_jabatan_pelaksana (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT, nama VARCHAR(80) NOT NULL, aktif TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_jabatan_nama (nama)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $this->db->exec("CREATE TABLE IF NOT EXISTS po_tim_pelaksana (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT, id_user INT NOT NULL, layanan VARCHAR(30) NOT NULL,
                id_jabatan INT UNSIGNED NOT NULL DEFAULT 0, aktif TINYINT(1) NOT NULL DEFAULT 1, created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id),
                UNIQUE KEY uq_user_layanan (id_user, layanan), KEY idx_layanan (layanan, aktif)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // tabel lama: id_jabatan dulu wajib diisi; sekarang anggota tidak punya jabatan -> beri default 0
            $kol = $this->db->exec("SHOW COLUMNS FROM po_tim_pelaksana LIKE 'id_jabatan'");
            if (!empty($kol[0]) && !isset($kol[0]['Default'])) {
                $this->db->exec("ALTER TABLE po_tim_pelaksana MODIFY id_jabatan INT UNSIGNED NOT NULL DEFAULT 0");
            }
            $n = $this->db->exec("SELECT COUNT(*) AS n FROM po_jabatan_pelaksana");
            if ((int) ($n[0]['n'] ?? 0) === 0) {
                $this->db->exec("INSERT IGNORE INTO po_jabatan_pelaksana (nama) VALUES ('Penanggung Jawab'), ('Ketua Pelaksana'), ('Anggota')");
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /* ============================================================
     * DATA
     * ============================================================ */

    protected function dataTim() {
        $lock = $this->layananTerkunci();
        $sql = "SELECT id, id_user, layanan, id_jabatan, aktif FROM po_tim_pelaksana";
        $par = [];
        if ($lock) { $sql .= " WHERE layanan = ?"; $par[1] = $lock; }
        $sql .= " ORDER BY id ASC";
        $out = [];
        foreach ($this->db->exec($sql, $par) as $r) {
            $out[] = ['id' => (int) $r['id'], 'peg' => (int) $r['id_user'], 'lay' => $r['layanan'],
                      'jab' => (int) $r['id_jabatan'], 'aktif' => (int) $r['aktif']];
        }
        return $out;
    }

    protected function dataJabatan() {
        $out = [];
        foreach ($this->db->exec("SELECT id, nama, aktif FROM po_jabatan_pelaksana ORDER BY id ASC") as $r) {
            $out[] = ['id' => (int) $r['id'], 'nama' => $r['nama'], 'aktif' => (int) $r['aktif']];
        }
        return $out;
    }

    protected function dataPegawai() {
        $out = [];
        foreach ($this->db->exec("SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC") as $r) {
            $out[] = ['id' => (int) $r['id_user'], 'n' => (string) $r['nama_user']];
        }
        return $out;
    }

    protected function dataLayanan() {
        $lock = $this->layananTerkunci();
        $out = [];
        foreach (self::$LAYANAN as $k => $v) {
            if ($lock === '' || $lock === $k) { $out[] = [$k, $v]; }
        }
        return $out;
    }

    protected function keluarkanState($pesan) {
        $this->keluarkanJson(['ok' => true, 'pesan' => $pesan, 'tim' => $this->dataTim(), 'jab' => $this->dataJabatan()]);
    }

    /** Huruf kecil untuk pencarian di sisi klien (data-cari). */
    protected function kecil($t) {
        return function_exists('mb_strtolower') ? mb_strtolower((string) $t, 'UTF-8') : strtolower((string) $t);
    }

    /** Baris tabel Tim Pelaksana yang siap di-<repeat> di view (nama sudah di-join). */
    protected function barisTim() {
        $peg = [];
        foreach ($this->dataPegawai() as $p) { $peg[$p['id']] = $p['n']; }
        $out = [];
        $no = 0;
        foreach ($this->dataTim() as $t) {
            $no++;
            $nama = isset($peg[$t['peg']]) ? $peg[$t['peg']] : '(pegawai dihapus)';
            $out[] = [
                'id'        => $t['id'],
                'no'        => $no,
                'peg'       => $t['peg'],
                'pegawai'   => $nama,
                'cari'      => $this->kecil($nama),
                'lay'       => $t['lay'],
                'layanan'   => isset(self::$LAYANAN[$t['lay']]) ? self::$LAYANAN[$t['lay']] : $t['lay'],
                'aktif'     => (int) $t['aktif'],
            ];
        }
        return $out;
    }

    protected function json($v) {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Dipakai form PO: anggota tim pelaksana AKTIF di satu layanan.
     * Format tiap baris: nama saja (jabatan & tugas dikosongkan; jabatan dipilih sendiri di form PO).
     */
    public static function ambilAktif($db, $divisi) {
        $d = strtolower(trim((string) $divisi));
        if (!isset(self::$LAYANAN[$d])) { return []; }
        try {
            $rows = $db->exec(
                "SELECT u.nama_user AS nama
                   FROM po_tim_pelaksana t
                   JOIN tb_arsipuser u ON u.id_user = t.id_user
                  WHERE t.layanan = ? AND t.aktif = 1
                  ORDER BY u.nama_user ASC", [1 => $d]);
        } catch (\Exception $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['nama' => (string) $r['nama'], 'jabatan' => '', 'tugas_tanggung_jawab' => ''];
        }
        return $out;
    }

    /** Dipakai form PO: nama jabatan AKTIF (urut sesuai Master Jabatan) buat pilihan kolom jabatan/tugas. */
    public static function ambilJabatanAktif($db) {
        try {
            $rows = $db->exec("SELECT nama FROM po_jabatan_pelaksana WHERE aktif = 1 ORDER BY id ASC");
        } catch (\Exception $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) { $out[] = (string) $r['nama']; }
        return $out;
    }

    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /master-tim-pelaksana */
    public function index($f3) {
        $this->izinkan();
        $ok = $this->pastikanSkema();
        $lock = $this->layananTerkunci();
        $kelola = $this->bisaKelola();
        $cfg = ['su' => $this->isSuperadmin(), 'kelola' => $kelola, 'lock' => (string) $lock, 'hal' => 'tim'];

        $rows = $ok ? $this->barisTim() : [];
        $aktif = 0;
        foreach ($rows as $r) { if ($r['aktif']) { $aktif++; } }

        $layOpts = [];
        foreach ($this->dataLayanan() as $l) { $layOpts[] = ['k' => $l[0], 'v' => $l[1]]; }

        if ($this->isSuperadmin()) {
            $sub = 'Daftar nama pegawai yang bisa dipilih di bagian Tim Pelaksana pada form PO. Jabatan dipilih langsung di form PO (daftar jabatannya dikelola di menu Jabatan Pelaksana PO).';
        } else {
            $nama = isset(self::$LAYANAN[$lock]) ? self::$LAYANAN[$lock] : '';
            $sub = 'Daftar tim pelaksana layanan ' . $nama . '.' . ($kelola ? ' Tambahkan pegawai yang masuk tim ini.' : ' Anda hanya bisa melihat daftar ini.');
        }

        // tabel di view dirender dengan <repeat> dari variabel *_rows; *_json hanya bahan form Tambah/Ubah
        $f3->set('mtp_tim_rows',     $rows);
        $f3->set('mtp_n_semua',      count($rows));
        $f3->set('mtp_n_aktif',      $aktif);
        $f3->set('mtp_n_non',        count($rows) - $aktif);
        $f3->set('mtp_lay_opts',     $layOpts);
        $f3->set('mtp_lock',         (string) $lock);
        $f3->set('mtp_kelola',       $kelola);
        $f3->set('mtp_subjudul',     $sub);
        $f3->set('mtp_pegawai_json', $this->json($ok ? $this->dataPegawai() : []));
        $f3->set('mtp_layanan_json', $this->json($this->dataLayanan()));
        $f3->set('mtp_tim_json',     $this->json($ok ? $this->dataTim() : []));
        $f3->set('mtp_cfg_json',     $this->json($cfg));
        $f3->set('error_message',    $ok ? null : 'Tabel master belum bisa dibuat otomatis (izin CREATE TABLE kurang). Jalankan migration_master_tim_pelaksana.sql.');
        $this->render('master-tim-pelaksana/index.html', 'Master Tim Pelaksana PO', 'master_tim_pelaksana');
    }

    /* ============================================================
     * TIM PELAKSANA
     * ============================================================ */

    /** POST /master-tim-pelaksana/tambah */
    public function tambah($f3) {
        $this->izinkan(true, true);
        $this->pastikanSkema();
        $lock = $this->layananTerkunci();
        $lay = $lock ?: strtolower(trim((string) $f3->get('POST.layanan')));
        if (!isset(self::$LAYANAN[$lay])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Layanan tidak valid.'], 422); }

        $items = json_decode((string) $f3->get('POST.items'), true);
        if (!is_array($items) || !count($items)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Isi minimal satu anggota.'], 422); }

        $sudahPeg = [];
        foreach ($items as $it) {
            $peg = (int) (isset($it['peg']) ? $it['peg'] : 0);
            if ($peg <= 0) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Ada baris yang belum lengkap.'], 422); }
            if (isset($sudahPeg[$peg])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Ada pegawai yang dipilih dua kali.'], 422); }
            $sudahPeg[$peg] = 0;
        }

        $ids = array_keys($sudahPeg);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $par = []; foreach ($ids as $i => $v) { $par[$i + 1] = $v; }
        $ada = $this->db->exec("SELECT id_user FROM tb_arsipuser WHERE id_user IN ($in)", $par);
        if (count($ada) !== count($ids)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Ada pegawai yang tidak ditemukan.'], 422); }
        $dobel = $this->db->exec("SELECT id_user FROM po_tim_pelaksana WHERE layanan = ? AND id_user IN ($in)", array_merge([1 => $lay], array_combine(range(2, count($ids) + 1), $ids)));
        if (count($dobel)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Ada pegawai yang sudah terdaftar di layanan ini.'], 422); }

        $uid = (int) $this->getUserId();
        try {
            $this->db->begin();
            foreach ($sudahPeg as $peg => $jab) {
                $this->db->exec("INSERT INTO po_tim_pelaksana (id_user, layanan, id_jabatan, aktif, created_by) VALUES (?, ?, 0, 1, ?)",
                    [1 => $peg, 2 => $lay, 3 => $uid]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
        $this->keluarkanState(count($sudahPeg) . ' anggota ditambahkan ke ' . self::$LAYANAN[$lay] . '.');
    }

    /** POST /master-tim-pelaksana/ubah -- tidak dipakai lagi (anggota tim hanya pegawai + layanan, tidak ada yang diubah). */
    public function ubah($f3) {
        $this->izinkan(true, true);
        $this->keluarkanJson(['ok' => false, 'pesan' => 'Anggota tim tidak bisa diubah. Hapus lalu tambahkan lagi bila perlu.'], 405);
    }

    /** POST /master-tim-pelaksana/hapus */
    public function hapus($f3) {
        $this->izinkan(true, true);
        $ids = json_decode((string) $f3->get('POST.ids'), true);
        if (!is_array($ids) || !count($ids)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Tidak ada data yang dipilih.'], 422); }
        $milik = [];
        foreach ($this->dataTim() as $t) { $milik[$t['id']] = 1; }
        $n = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if (!isset($milik[$id])) { continue; }
            $this->db->exec("DELETE FROM po_tim_pelaksana WHERE id = ?", [1 => $id]);
            $n++;
        }
        $this->keluarkanState($n . ' anggota dihapus.');
    }

    /** POST /master-tim-pelaksana/@id/aktif */
    public function aktif($f3, $params) {
        $this->izinkan(true, true);
        $id = (int) $params['id'];
        $milik = [];
        foreach ($this->dataTim() as $t) { $milik[$t['id']] = 1; }
        if (!isset($milik[$id])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anggota tidak ditemukan.'], 404); }
        $a = ((int) $f3->get('POST.aktif')) ? 1 : 0;
        $this->db->exec("UPDATE po_tim_pelaksana SET aktif = ? WHERE id = ?", [1 => $a, 2 => $id]);
        $this->keluarkanState($a ? 'Anggota diaktifkan.' : 'Anggota dinonaktifkan.');
    }
}