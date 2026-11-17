<?php
/**
 * app/controllers/PksMitraController.php
 *
 * Menu "PKS" untuk Tim Mitra. Alurnya:
 *   1. Humas menyelesaikan PKS (PksKontrakController)  -> Tim Mitra mendapat pemberitahuan
 *   2. Tim Mitra membuka PKS, mencetaknya, dan menyerahkannya ke klien
 *   3. Klien menandatangani
 *   4. Tim Mitra meng-upload PKS bertanda tangan (satu PDF, atau 1-10 foto yang digabung otomatis jadi satu PDF) -> Humas dan Ketua Tim mendapat pemberitahuan
 *
 * Penanda tersimpan di tabel pks_kontrak. Kolom tambahan dibuat otomatis (pastikanKolom), tidak perlu menjalankan SQL.
 *   mitra_dibaca_at / mitra_dibaca_oleh_nama   : Tim Mitra pertama kali membuka PKS
 *   mitra_cetak_at  / mitra_cetak_oleh_nama    : Tim Mitra pertama kali mencetak / mengunduh PKS
 *   pks_ttd_file / pks_ttd_nama_file / pks_ttd_asal / pks_ttd_at / pks_ttd_oleh_id / pks_ttd_oleh_nama : PKS bertanda tangan
 * File PKS bertanda tangan ada di storage/pks_ttd (di luar folder publik) dan dibuka lewat GET /pks-mitra/@id/ttd.
 *
 * Dokumen PKS-nya sendiri (tampilan, Word, PDF) dibuka lewat route /pks/@id/... milik PksKontrakController (baca-saja untuk Tim Mitra).
 * Kompatibel PHP 7.2.
 */
class PksMitraController extends Controller {

    const TTD_MAKS_BYTE = 31457280;   // 30 MB per upload (PDF akhir)
    const TTD_MAKS_FOTO = 10;         // jumlah foto maksimal dalam satu kali upload

    const KOLOM = [
        'mitra_dibaca_at'        => 'DATETIME NULL',
        'mitra_dibaca_oleh_nama' => 'VARCHAR(150) NULL',
        'mitra_cetak_at'         => 'DATETIME NULL',
        'mitra_cetak_oleh_nama'  => 'VARCHAR(150) NULL',
        'pks_ttd_file'           => 'VARCHAR(255) NULL',
        'pks_ttd_nama_file'      => 'VARCHAR(255) NULL',
        'pks_ttd_asal'           => 'VARCHAR(60) NULL',
        'pks_ttd_at'             => 'DATETIME NULL',
        'pks_ttd_oleh_id'        => 'INT NULL',
        'pks_ttd_oleh_nama'      => 'VARCHAR(150) NULL',
    ];

    const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    protected static $kolomSiap = null;

    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /pks-mitra */
    public function index($f3) {
        $this->izinkan();

        $error = null;
        $daftar = [];
        try {
            if (!self::pastikanKolom($this->db)) {
                throw new \RuntimeException('Kolom penanda PKS belum bisa dibuat otomatis. Jalankan SQL yang ada di catatan (ALTER TABLE pks_kontrak).');
            }
            $rows = $this->db->exec(self::sqlDaftar() . ' ORDER BY COALESCE(k.selesai_at, k.updated_at) DESC');
            foreach ((array) $rows as $r) {
                $daftar[] = $this->susunBaris($r);
            }
        } catch (\Throwable $e) {
            $error = 'Gagal memuat daftar PKS: ' . $e->getMessage();
        }

        $f3->set('pks_mitra_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('error_message', $error);
        $this->render('pks_mitra/index.html', 'Perjanjian Kerja Sama (PKS)', 'pks_mitra');
    }

    /* ============================================================
     * API
     * ============================================================ */

    /** POST /pks-mitra/@id/baca : PKS dibuka Tim Mitra (sekali). */
    public function baca($f3, $params) {
        $this->izinkan(true);
        $poId = (int) $params['id'];
        $k = $this->ambilPks($poId);
        if (!$k) { $this->json(['ok' => false, 'pesan' => 'PKS tidak ditemukan atau belum selesai dibuat Humas.'], 404); }
        $this->tandai($poId, false);
        $this->json(['ok' => true] + $this->tandaiBalas($poId));
    }

    /** POST /pks-mitra/@id/cetak : PKS dicetak / diunduh Tim Mitra (sekali; sekaligus dihitung sudah dibuka). */
    public function cetak($f3, $params) {
        $this->izinkan(true);
        $poId = (int) $params['id'];
        $k = $this->ambilPks($poId);
        if (!$k) { $this->json(['ok' => false, 'pesan' => 'PKS tidak ditemukan atau belum selesai dibuat Humas.'], 404); }
        $this->tandai($poId, true);
        $this->json(['ok' => true] + $this->tandaiBalas($poId));
    }

    /** POST /pks-mitra/@id/upload : PKS bertanda tangan klien (file_pks_ttd[] : satu PDF, atau 1-10 foto JPG/PNG). Balasan JSON. */
    public function upload($f3, $params) {
        $this->izinkan(true);
        $poId = (int) $params['id'];
        $k = $this->ambilPks($poId);
        if (!$k) { $this->json(['ok' => false, 'pesan' => 'PKS tidak ditemukan atau belum selesai dibuat Humas.'], 404); }

        $berkas = $this->kumpulkanBerkas();
        if (!$berkas) {
            $lebih = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
            if ($lebih > 0 && empty($_FILES)) {
                $this->json(['ok' => false, 'pesan' => 'Ukuran file terlalu besar untuk dikirim. Kurangi jumlah foto atau pilih file yang lebih kecil.'], 413);
            }
            $this->json(['ok' => false, 'pesan' => 'Pilih file PKS yang sudah ditandatangani terlebih dahulu.'], 422);
        }
        if (count($berkas) > self::TTD_MAKS_FOTO) {
            $this->json(['ok' => false, 'pesan' => 'Maksimal ' . self::TTD_MAKS_FOTO . ' foto dalam satu kali upload.'], 422);
        }

        $mimeOk = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']];
        $daftarFoto = [];
        $adaPdf = false;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        foreach ($berkas as $file) {
            $salah = '';
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > self::TTD_MAKS_BYTE) {
                $salah = 'Ukuran file maksimal 30 MB. Pilih file yang lebih kecil.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || !is_uploaded_file($file['tmp_name'])) {
                $salah = 'Terjadi kesalahan saat mengunggah file. Coba lagi.';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!isset($mimeOk[$ext])) {
                    $salah = 'Format file harus PDF, JPG, atau PNG.';
                } elseif (!in_array((string) finfo_file($finfo, $file['tmp_name']), $mimeOk[$ext], true)) {
                    $salah = 'Isi file tidak sesuai dengan format PDF, JPG, atau PNG.';
                } elseif ($ext === 'pdf') {
                    $adaPdf = true;
                } else {
                    $daftarFoto[] = ['tmp' => $file['tmp_name'], 'mime' => (string) finfo_file($finfo, $file['tmp_name']), 'ext' => $ext === 'jpeg' ? 'jpg' : $ext];
                }
            }
            if ($salah !== '') { finfo_close($finfo); $this->json(['ok' => false, 'pesan' => $salah], 422); }
        }
        finfo_close($finfo);
        if ($adaPdf && count($berkas) > 1) {
            $this->json(['ok' => false, 'pesan' => 'Upload satu file PDF saja, atau satu/beberapa foto. PDF tidak bisa dicampur dengan foto.'], 422);
        }

        $dir = $this->dirTtd();
        $acak = bin2hex(random_bytes(12));
        $disimpan = $acak . '.pdf';
        $asal = '';
        $namaTampil = '';

        if (!$adaPdf) {
            // Foto (satu atau banyak) -> satu PDF
            try {
                $pdf = '';
                foreach ([[2000, 85], [1600, 70], [1200, 60]] as $mutu) {
                    $pdf = $this->fotoKePdf($daftarFoto, $mutu[0], $mutu[1]);
                    if (strlen($pdf) <= self::TTD_MAKS_BYTE) { break; }
                }
                if (strlen($pdf) > self::TTD_MAKS_BYTE) {
                    $this->json(['ok' => false, 'pesan' => 'Hasil PDF dari foto melebihi 30 MB. Kurangi jumlah foto lalu coba lagi.'], 422);
                }
                if (file_put_contents($dir . $disimpan, $pdf) === false) { throw new \RuntimeException('gagal menulis PDF'); }
                $jml = count($daftarFoto);
                $asal = ($jml > 1) ? ($jml . ' foto') : ('Foto ' . strtoupper($daftarFoto[0]['ext']));
            } catch (\Throwable $e) {
                error_log('[PKS-TTD] konversi foto ke PDF gagal: ' . $e->getMessage());
                @unlink($dir . $disimpan);
                $this->json(['ok' => false, 'pesan' => 'Foto belum bisa diubah menjadi PDF. Coba lagi, atau upload dalam bentuk PDF.'], 500);
            }
            $namaTampil = $this->namaTampil($k);
        } else {
            $file = $berkas[0];
            if (!move_uploaded_file($file['tmp_name'], $dir . $disimpan)) {
                $this->json(['ok' => false, 'pesan' => 'Gagal menyimpan file ke server.'], 500);
            }
            $namaTampil = preg_replace('/[\x00-\x1F\\\\\/:*?"<>|]+/u', '-', basename($file['name']));
        }

        $lama = (string) ($k['pks_ttd_file'] ?? '');
        $sudahAda = ($lama !== '');
        $uid = (int) $this->getUserId();
        $unama = $this->namaUser();
        $this->db->exec(
            'UPDATE pks_kontrak SET pks_ttd_file = ?, pks_ttd_nama_file = ?, pks_ttd_asal = ?, pks_ttd_at = NOW(), pks_ttd_oleh_id = ?, pks_ttd_oleh_nama = ?,
                    mitra_dibaca_at = COALESCE(mitra_dibaca_at, NOW()), mitra_dibaca_oleh_nama = COALESCE(mitra_dibaca_oleh_nama, ?), updated_at = updated_at
              WHERE po_id = ?',
            [1 => $disimpan, 2 => $namaTampil, 3 => $asal, 4 => $uid, 5 => $unama, 6 => $unama, 7 => $poId]
        );
        if ($sudahAda && $lama !== $disimpan && basename($lama) === $lama) { @unlink($dir . $lama); }

        $this->catat($k, $sudahAda, $unama);
        $this->kabariHumasKatim($k, $sudahAda, $unama);

        $pesan = $sudahAda ? 'PKS bertanda tangan diganti dengan file yang baru.' : 'PKS bertanda tangan berhasil di-upload.';
        if ($asal !== '') { $pesan .= (strpos($asal, ' foto') !== false && (int) $asal > 1) ? ' ' . (int) $asal . ' foto otomatis digabung menjadi satu PDF.' : ' Foto otomatis diubah menjadi PDF.'; }
        $baris = $this->ambilPks($poId);
        $this->json(['ok' => true, 'pesan' => $pesan, 'baris' => $baris ? $this->susunBaris($baris) : null]);
    }

    /** GET /pks-mitra/@id/ttd : membuka PKS bertanda tangan (?unduh=1 untuk mengunduh). Tim Mitra, Humas, Admin PKS, Ketua Tim, Superadmin. */
    public function ttd($f3, $params) {
        $this->requireAuth();
        $peran = (string) $this->getUserRole();
        if (!($this->isSuperadmin() || $this->isTimMitra() || in_array($peran, ['humas', 'admin_kontrak', 'ketua_tim'], true))) {
            $f3->error(403, 'Anda tidak punya akses ke PKS bertanda tangan.');
            return;
        }
        $poId = (int) $params['id'];
        try { self::pastikanKolom($this->db); } catch (\Throwable $e) {}
        $r = $this->db->exec('SELECT pks_ttd_file, pks_ttd_nama_file, nomor_pks FROM pks_kontrak WHERE po_id = ? LIMIT 1', [1 => $poId]);
        $nama = !empty($r) ? (string) $r[0]['pks_ttd_file'] : '';
        $path = $this->dirTtd() . $nama;
        if ($nama === '' || basename($nama) !== $nama || !is_file($path)) {
            $f3->error(404, 'PKS bertanda tangan belum di-upload.');
            return;
        }
        $tampil = trim((string) $r[0]['pks_ttd_nama_file']);
        if ($tampil === '') { $tampil = 'PKS-bertanda-tangan.pdf'; }
        $tampil = str_replace('/', '-', $tampil);
        $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $tampil);
        $unduh = (string) $f3->get('GET.unduh') === '1';
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($tampil));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($path);
        exit;
    }

    /* ============================================================
     * NOTIFIKASI (dipanggil juga dari PksKontrakController)
     * ============================================================ */

    /**
     * Humas selesai membuat PKS -> Tim Mitra mendapat pemberitahuan (lonceng + push). Bersifat pelengkap: gagal tidak boleh menggagalkan PKS.
     * $po = baris PO (butuh order_id, nomor_po), $nomorPks = nomor PKS yang baru terbit.
     */
    public static function kabariMitraPksBaru($db, array $po, $nomorPks, $oleh = 'Humas') {
        try {
            $orderId = (int) (isset($po['order_id']) ? $po['order_id'] : 0);
            $pesan = 'Humas sudah membuat PKS No. ' . $nomorPks . ' untuk PO ' . (isset($po['nomor_po']) ? $po['nomor_po'] : '-')
                   . '. Silakan cetak, serahkan ke klien untuk ditandatangani, lalu upload PKS bertanda tangan.';
            \NotificationService::send($db, [
                'order_id'        => $orderId,
                'po_id'           => (int) (isset($po['po_id']) ? $po['po_id'] : (isset($po['id']) ? $po['id'] : 0)),
                'target_role'     => 'tim_mitra',
                'target_layanan'  => 'semua',
                'judul'           => 'PKS Baru dari Humas',
                'pesan'           => $pesan,
                'tipe'            => 'primary',
                'icon'            => 'bi-file-earmark-text',
                'link_url'        => '/pks-mitra',
                'created_by'      => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                'created_by_name' => $oleh,
            ]);
            // push ke HP / browser (lonceng sudah ditangani NotificationService di atas)
            \PoReviewController::kabari($db, self::idPeran($db, ['tim_mitra_industri', 'admin_order', 'tim_mitra', 'ketua_tim_mitra']), 'PKS Baru dari Humas', $pesan, '/pks-mitra', false);
        } catch (\Throwable $e) {
            error_log('[PKS-MITRA] notifikasi PKS baru gagal: ' . $e->getMessage());
        }
    }

    /** Jumlah PKS selesai yang belum dibuka Tim Mitra (badge sidebar). 0 bila kolom belum ada / gagal. */
    public static function hitungBelumDibuka($db) {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $cache = 0;
        try {
            if (!self::pastikanKolom($db)) { return $cache; }
            $res = $db->exec("SELECT COUNT(*) AS c FROM pks_kontrak WHERE status = 'selesai' AND mitra_dibaca_at IS NULL AND (pks_ttd_file IS NULL OR pks_ttd_file = '')");
            $cache = (int) ($res[0]['c'] ?? 0);
        } catch (\Throwable $e) {
            $cache = 0;
        }
        return $cache;
    }

    /** Tambah kolom penanda di pks_kontrak bila belum ada. true bila semua kolom sudah tersedia. */
    public static function pastikanKolom($db) {
        if (self::$kolomSiap !== null) { return self::$kolomSiap; }
        self::$kolomSiap = false;
        try {
            $ada = [];
            foreach ((array) $db->exec('SHOW COLUMNS FROM pks_kontrak') as $c) { $ada[$c['Field']] = true; }
            foreach (self::KOLOM as $nama => $def) {
                if (isset($ada[$nama])) { continue; }
                try { $db->exec('ALTER TABLE pks_kontrak ADD COLUMN `' . $nama . '` ' . $def); } catch (\Throwable $e) { error_log('[PKS-MITRA] ALTER ' . $nama . ': ' . $e->getMessage()); }
            }
            $ada = [];
            foreach ((array) $db->exec('SHOW COLUMNS FROM pks_kontrak') as $c) { $ada[$c['Field']] = true; }
            self::$kolomSiap = true;
            foreach (self::KOLOM as $nama => $def) { if (!isset($ada[$nama])) { self::$kolomSiap = false; } }
        } catch (\Throwable $e) {
            self::$kolomSiap = false;
        }
        return self::$kolomSiap;
    }

    /** Id pengguna aktif dengan salah satu peran (tb_arsipuser.si_opti atau opti_user_map.role). */
    public static function idPeran($db, array $peran) {
        if (!$peran) { return []; }
        $ph = implode(',', array_fill(0, count($peran), '?'));
        $arg = [];
        $i = 1;
        foreach ($peran as $p) { $arg[$i++] = $p; }
        foreach ($peran as $p) { $arg[$i++] = $p; }
        try {
            $baris = $db->exec(
                "SELECT DISTINCT u.id_user
                   FROM tb_arsipuser u
                   LEFT JOIN opti_user_map m ON m.id_user = u.id_user AND m.status_tampil = 'show' AND (m.is_active = 1 OR m.is_active = '1')
                  WHERE (u.status = 1 OR u.status = '1' OR u.status = 'aktif')
                    AND (u.si_opti IN ($ph) OR m.role IN ($ph))",
                $arg
            );
        } catch (\Throwable $e) {
            return [];
        }
        $ids = [];
        foreach ((array) $baris as $b) { $ids[] = (int) $b['id_user']; }
        return $ids;
    }

    /* ============================================================
     * HELPER
     * ============================================================ */

    /** SELECT dasar: PKS yang sudah selesai dibuat Humas, lengkap dengan PO, order, dan mitra. */
    protected static function sqlDaftar() {
        return "SELECT k.po_id, k.nomor_pks, k.status, k.selesai_at, k.updated_at, k.judul AS k_judul, k.biaya AS k_biaya, k.perusahaan,
                       k.mitra_dibaca_at, k.mitra_dibaca_oleh_nama, k.mitra_cetak_at, k.mitra_cetak_oleh_nama,
                       k.pks_ttd_file, k.pks_ttd_nama_file, k.pks_ttd_asal, k.pks_ttd_at, k.pks_ttd_oleh_nama,
                       p.nomor_po, p.judul_kegiatan AS po_judul,
                       o.id AS order_id, o.jenis_layanan_opti, o.judul_kegiatan AS order_judul,
                       c.nmcustomer, c.pt_cv
                  FROM pks_kontrak k
                  JOIN po_kegiatan p ON p.id = k.po_id
                  JOIN order_layanan o ON o.id = p.order_id
                  JOIN tb_customer c ON c.id_customer = o.id_customer
                 WHERE k.status = 'selesai'";
    }

    /** Satu PKS selesai (baris lengkap seperti sqlDaftar), atau null. */
    protected function ambilPks($poId) {
        if ($poId <= 0) { return null; }
        try {
            if (!self::pastikanKolom($this->db)) { return null; }
            $rows = $this->db->exec(self::sqlDaftar() . ' AND k.po_id = ? LIMIT 1', [1 => (int) $poId]);
        } catch (\Throwable $e) {
            return null;
        }
        return !empty($rows) ? $rows[0] : null;
    }

    /** Baris DB -> data untuk halaman. */
    protected function susunBaris(array $r) {
        $base = (string) $this->f3->get('BASE');
        $poId = (int) $r['po_id'];
        $sudahTtd = !empty($r['pks_ttd_file']);
        $judul = trim(html_entity_decode(strip_tags((string) $r['k_judul']), ENT_QUOTES, 'UTF-8'));
        if ($judul === '') { $judul = trim((string) $r['po_judul']); }
        if ($judul === '') { $judul = trim((string) $r['order_judul']); }
        $bersih = trim((string) preg_replace('/^((PT|CV|UD)\.?\s+)+/i', '', (string) $r['nmcustomer']));
        $badan = trim((string) $r['pt_cv']);
        $mitra = ($badan !== '') ? $badan . ' ' . $bersih : $bersih;
        return [
            'id'         => $poId,
            'order_id'   => (int) $r['order_id'],
            'nomor_pks'  => (string) $r['nomor_pks'],
            'nomor_po'   => (string) $r['nomor_po'],
            'mitra'      => $mitra,
            'layanan'    => (string) $r['jenis_layanan_opti'],
            'layanan_label' => ucwords(str_replace('_', ' ', (string) $r['jenis_layanan_opti'])),
            'judul'      => $judul,
            'biaya'      => (int) round((float) $r['k_biaya']),
            'status'     => $sudahTtd ? 'selesai' : 'menunggu',
            'dibuat'     => self::tgl($r['selesai_at'] ?: $r['updated_at'], true),
            'baca'       => self::tgl($r['mitra_dibaca_at'], true),
            'baca_tgl'   => self::tgl($r['mitra_dibaca_at'], false),
            'cetak'      => self::tgl($r['mitra_cetak_at'], true),
            'cetak_tgl'  => self::tgl($r['mitra_cetak_at'], false),
            'ttd'        => $sudahTtd ? self::tgl($r['pks_ttd_at'], true) : '',
            'ttd_tgl'    => $sudahTtd ? self::tgl($r['pks_ttd_at'], false) : '',
            'ttd_oleh'   => $sudahTtd ? (string) $r['pks_ttd_oleh_nama'] : '',
            'ttd_asal'   => $sudahTtd ? (string) $r['pks_ttd_asal'] : '',
            'info_ttd'   => $sudahTtd ? self::infoTtd($r) : '',
            'url_ttd'    => $sudahTtd ? $base . '/pks-mitra/' . $poId . '/ttd?v=' . (int) strtotime((string) $r['pks_ttd_at']) : '',
            'url_ttd_unduh' => $sudahTtd ? $base . '/pks-mitra/' . $poId . '/ttd?unduh=1' : '',
            'url_detail' => !empty($r['order_id']) ? $base . '/order/' . (int) $r['order_id'] : '',
        ];
    }

    /** "Di-upload Andi, 9 Nov 2026, 14:05 · 3 foto digabung jadi PDF". */
    public static function infoTtd(array $r) {
        $waktu = self::tgl(isset($r['pks_ttd_at']) ? $r['pks_ttd_at'] : '', true);
        if (empty($r['pks_ttd_file']) || $waktu === '') { return ''; }
        $oleh = trim((string) (isset($r['pks_ttd_oleh_nama']) ? $r['pks_ttd_oleh_nama'] : ''));
        $t = 'Di-upload ' . ($oleh !== '' ? $oleh . ', ' : '') . $waktu;
        $asal = trim((string) (isset($r['pks_ttd_asal']) ? $r['pks_ttd_asal'] : ''));
        if ($asal !== '') { $t .= ' · ' . $asal . (preg_match('/^\d+\s*foto/i', $asal) ? ' digabung jadi PDF' : ' diubah jadi PDF'); }
        return $t;
    }

    /** '2026-11-09 14:05:00' -> '9 Nov 2026, 14:05' (atau '9 Nov 2026' bila $jam false). '' bila kosong / tidak valid. */
    protected static function tgl($v, $jam) {
        $v = trim((string) $v);
        if ($v === '' || strpos($v, '0000') === 0 || !strtotime($v)) { return ''; }
        $t = strtotime($v);
        return (int) date('j', $t) . ' ' . self::BULAN[(int) date('n', $t) - 1] . ' ' . date('Y', $t) . ($jam ? ', ' . date('H:i', $t) : '');
    }

    /** Catat dibuka / dicetak (sekali, tidak menimpa catatan pertama). updated_at dibiarkan supaya urutan dan versi logo di sisi Humas tidak berubah. */
    protected function tandai($poId, $cetak) {
        $unama = $this->namaUser();
        try {
            $this->db->exec(
                'UPDATE pks_kontrak SET mitra_dibaca_at = COALESCE(mitra_dibaca_at, NOW()), mitra_dibaca_oleh_nama = COALESCE(mitra_dibaca_oleh_nama, ?), updated_at = updated_at WHERE po_id = ?',
                [1 => $unama, 2 => (int) $poId]
            );
            if ($cetak) {
                $this->db->exec(
                    'UPDATE pks_kontrak SET mitra_cetak_at = COALESCE(mitra_cetak_at, NOW()), mitra_cetak_oleh_nama = COALESCE(mitra_cetak_oleh_nama, ?), updated_at = updated_at WHERE po_id = ?',
                    [1 => $unama, 2 => (int) $poId]
                );
            }
        } catch (\Throwable $e) {
            error_log('[PKS-MITRA] tandai gagal: ' . $e->getMessage());
        }
    }

    protected function tandaiBalas($poId) {
        $r = $this->ambilPks($poId);
        if (!$r) { return []; }
        return ['baca' => self::tgl($r['mitra_dibaca_at'], true), 'baca_tgl' => self::tgl($r['mitra_dibaca_at'], false),
                'cetak' => self::tgl($r['mitra_cetak_at'], true), 'cetak_tgl' => self::tgl($r['mitra_cetak_at'], false)];
    }

    protected function catat(array $k, $diganti, $oleh) {
        $orderId = (int) $k['order_id'];
        if ($orderId <= 0) { return; }
        try {
            $this->logActivity($orderId, 'kontrak_pks', $diganti ? 'ganti_pks_ttd' : 'upload_pks_ttd',
                ($diganti ? 'Mengganti' : 'Meng-upload') . ' PKS bertanda tangan klien No. ' . $k['nomor_pks'] . ' (Tim Mitra: ' . $oleh . ')');
        } catch (\Throwable $e) {
            // abaikan
        }
        try {
            \PoReviewController::catatRiwayatOrder($this->db, (int) $k['po_id'], (int) $this->getUserId(),
                'Tim Mitra ' . ($diganti ? 'mengganti' : 'meng-upload') . ' PKS bertanda tangan klien (' . $k['nomor_pks'] . ').', 'pks_ttd_mitra');
        } catch (\Throwable $e) {
            // abaikan
        }
    }

    /** PKS bertanda tangan di-upload -> Humas dan Ketua Tim (divisi order) + Ketua Pelaksana mendapat pemberitahuan. */
    protected function kabariHumasKatim(array $k, $diganti, $oleh) {
        try {
            $judul = $diganti ? 'PKS Bertanda Tangan Diperbarui' : 'PKS Bertanda Tangan Sudah Di-upload';
            $pesan = 'Tim Mitra (' . $oleh . ') ' . ($diganti ? 'memperbarui' : 'meng-upload') . ' PKS bertanda tangan klien No. ' . $k['nomor_pks']
                   . ' (' . (string) $k['nomor_po'] . '). Klien sudah menandatangani PKS.';
            // Humas: bell per peran, push per pengguna
            \NotificationService::send($this->db, [
                'order_id'        => (int) $k['order_id'],
                'po_id'           => (int) $k['po_id'],
                'target_role'     => 'humas',
                'target_layanan'  => 'semua',
                'judul'           => $judul,
                'pesan'           => $pesan,
                'tipe'            => 'success',
                'icon'            => 'bi-patch-check',
                'link_url'        => '/pks?tab=selesai',
                'created_by'      => $this->getUserId(),
                'created_by_name' => $oleh,
            ]);
            \PoReviewController::kabari($this->db, self::idPeran($this->db, ['humas']), $judul, $pesan, '/pks?tab=selesai', false);
            // Ketua Tim divisi + Ketua Pelaksana: bell + push
            $o = $this->db->exec('SELECT ketua_pelaksana_id, jenis_layanan_opti FROM order_layanan WHERE id = ? LIMIT 1', [1 => (int) $k['order_id']]);
            $o = !empty($o) ? $o[0] : [];
            $div = (string) (isset($o['jenis_layanan_opti']) ? $o['jenis_layanan_opti'] : '');
            $katim = \ArsipUser::getKatimMap($this->db, ['selulosa', 'lingkungan']);
            $penerima = [isset($katim[$div]) ? (int) $katim[$div] : 0, (int) (isset($o['ketua_pelaksana_id']) ? $o['ketua_pelaksana_id'] : 0)];
            \PoReviewController::kabari($this->db, $penerima, $judul, $pesan, '/order/' . (int) $k['order_id']);
        } catch (\Throwable $e) {
            error_log('[PKS-MITRA] notifikasi upload gagal: ' . $e->getMessage());
        }
    }

    /** Nama berkas bila PKS bertanda tangan dibuat dari foto: "PKS TTD <nomor>_<mitra>.pdf". */
    protected function namaTampil(array $k) {
        $per = trim((string) preg_replace('/^((PT|CV|UD)\.?\s+)+/i', '', (string) $k['perusahaan']));
        $dasar = 'PKS TTD ' . trim((string) $k['nomor_pks']) . ($per !== '' ? '_' . $per : '');
        $dasar = trim((string) preg_replace('/[\x00-\x1F\\\\\/:*?"<>|]+/u', '-', $dasar));
        $dasar = preg_replace('/\s+/u', ' ', $dasar);
        $dasar = function_exists('mb_substr') ? mb_substr($dasar, 0, 150, 'UTF-8') : substr($dasar, 0, 150);
        return rtrim($dasar, ' .') . '.pdf';
    }

    protected function namaUser() {
        if (!empty($_SESSION['nama_lengkap'])) { return (string) $_SESSION['nama_lengkap']; }
        if (!empty($_SESSION['nama_user'])) { return (string) $_SESSION['nama_user']; }
        return 'Tim Mitra';
    }

    /** Folder penyimpanan PKS bertanda tangan (di luar folder publik; dibuka lewat route /pks-mitra/@id/ttd). */
    protected function dirTtd() {
        $d = dirname(__DIR__, 2) . '/storage/pks_ttd/';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return $d;
    }

    /** Susun $_FILES['file_pks_ttd'] (satu file atau banyak file) menjadi daftar seragam; entri kosong dibuang. */
    protected function kumpulkanBerkas() {
        $out = [];
        if (empty($_FILES['file_pks_ttd'])) { return $out; }
        $fl = $_FILES['file_pks_ttd'];
        if (is_array($fl['name'])) {
            foreach ($fl['name'] as $i => $n) {
                $out[] = ['name' => $n, 'type' => $fl['type'][$i], 'tmp_name' => $fl['tmp_name'][$i], 'error' => $fl['error'][$i], 'size' => $fl['size'][$i]];
            }
        } else {
            $out[] = $fl;
        }
        $hasil = [];
        foreach ($out as $o) { if ($o['error'] !== UPLOAD_ERR_NO_FILE) { $hasil[] = $o; } }
        return $hasil;
    }

    /**
     * Satu atau beberapa foto (JPG/PNG) -> satu PDF A4, satu foto satu halaman sesuai urutan, skala dijaga.
     * Sama dengan konversi foto pada PO bertanda tangan. $daftar = [['tmp'=>..., 'mime'=>...]]. Melempar exception bila gagal.
     */
    protected function fotoKePdf($daftar, $maks = 2000, $kualitas = 85) {
        if (!extension_loaded('gd') || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) { throw new \RuntimeException('GD tidak aktif'); }
        $halaman = [];
        $semuaLandscape = true;
        foreach ($daftar as $item) {
            $tmp = $item['tmp']; $mime = $item['mime'];
            $info = @getimagesize($tmp);
            if (!$info || $info[0] < 1 || $info[1] < 1) { throw new \RuntimeException('gambar tidak terbaca'); }
            $img = @imagecreatefromstring(file_get_contents($tmp));
            if (!$img) { throw new \RuntimeException('gambar tidak bisa dibuka'); }
            // putar sesuai EXIF (foto dari HP)
            if ($mime === 'image/jpeg' && function_exists('exif_read_data') && function_exists('imagerotate')) {
                $ex = @exif_read_data($tmp);
                $o = (is_array($ex) && isset($ex['Orientation'])) ? (int) $ex['Orientation'] : 1;
                $deg = ($o === 3) ? 180 : (($o === 6) ? -90 : (($o === 8) ? 90 : 0));
                if ($deg !== 0) { $rot = @imagerotate($img, $deg, 0); if ($rot) { imagedestroy($img); $img = $rot; } }
            }
            $w = imagesx($img); $h = imagesy($img);
            if (max($w, $h) > $maks && function_exists('imagescale')) {
                $skala = $maks / max($w, $h);
                $kecil = @imagescale($img, max(1, (int) round($w * $skala)), max(1, (int) round($h * $skala)));
                if ($kecil) { imagedestroy($img); $img = $kecil; $w = imagesx($img); $h = imagesy($img); }
            }
            // latar putih (PNG transparan) lalu simpan sebagai JPEG
            $kanvas = imagecreatetruecolor($w, $h);
            imagefill($kanvas, 0, 0, imagecolorallocate($kanvas, 255, 255, 255));
            imagecopy($kanvas, $img, 0, 0, 0, 0, $w, $h);
            ob_start();
            imagejpeg($kanvas, null, (int) $kualitas);
            $data = ob_get_clean();
            imagedestroy($img); imagedestroy($kanvas);
            if ($w <= $h) { $semuaLandscape = false; }
            $halaman[] = ['w' => $w, 'h' => $h, 'data' => $data];
        }
        if (!$halaman) { throw new \RuntimeException('tidak ada foto'); }

        $land = $semuaLandscape;
        $kotakW = $land ? 275.0 : 188.0;   // mm, A4 dikurangi margin 10 mm dan sedikit ruang agar tidak melompat halaman
        $kotakH = $land ? 188.0 : 275.0;
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page{margin:10mm}body{margin:0;text-align:center}.hal{text-align:center}img{display:block;margin:0 auto}</style></head><body>';
        $n = count($halaman);
        foreach ($halaman as $i => $hal) {
            $skala = min($kotakW / $hal['w'], $kotakH / $hal['h']);
            $wMm = round($hal['w'] * $skala, 2); $hMm = round($hal['h'] * $skala, 2);
            $html .= '<div class="hal"' . ($i < $n - 1 ? ' style="page-break-after:always"' : '') . '>'
                   . '<img src="data:image/jpeg;base64,' . base64_encode($hal['data']) . '" style="width:' . $wMm . 'mm;height:' . $hMm . 'mm"></div>';
        }
        $html .= '</body></html>';
        $opt = new \Dompdf\Options();
        $opt->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($opt);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $land ? 'landscape' : 'portrait');
        $dompdf->render();
        $out = $dompdf->output();
        if (strncmp($out, '%PDF', 4) !== 0) { throw new \RuntimeException('PDF tidak valid'); }
        return $out;
    }

    /** Hanya Tim Mitra dan Superadmin. $json = true untuk endpoint API. */
    protected function izinkan($json = false) {
        $this->requireAuth();
        if ($this->isSuperadmin() || $this->isTimMitra()) { return; }
        if ($json) { $this->json(['ok' => false, 'pesan' => 'Menu PKS ini hanya untuk Tim Mitra.'], 403); }
        $this->f3->error(403, 'Menu PKS ini hanya untuk Tim Mitra.');
        exit;
    }

    protected function json($data, $status = 200) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
