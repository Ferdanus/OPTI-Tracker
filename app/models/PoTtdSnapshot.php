<?php
/**
 * PoTtdSnapshot -- "foto" gambar tanda tangan yang dipakai di dokumen PO saat PO di-APPROVE.
 *
 * Masalah yang dijawab: Ketua Tim / PPK BLU tidak menjabat selamanya. Gambar TTD aslinya milik akun (storage/ttd/<id_user>.png) dan bisa
 * diganti atau dihapus kapan saja. Tanpa salinan, dokumen PO lama ikut berubah (atau kehilangan TTD) begitu pemiliknya mengganti TTD / berhenti menjabat.
 * Jadi saat approve, gambar TTD dibekukan untuk PO itu (satu baris per PO per peran) dan dokumen selalu memakai salinan ini.
 * Nama, NIP, dan jabatan penandatangan sudah dibekukan per PO di po_kegiatan.ttd_json (lihat PoTtd), jadi tidak perlu diulang di sini.
 *
 * Tabel po_ttd_snapshot (dibuat otomatis): po_id, peran ('ketua' = Ketua Tim, 'ppk' = PPK BLU), user_id (akun yang approve),
 * stempel (waktu approve, dari disetujui_at / validasi_keuangan_at), sumber ('approve' = dibekukan saat tombol Approve; 'lazy' = PO yang di-approve
 * sebelum fitur ini ada, dibekukan saat pertama kali dokumennya dibuka), ttd_uri (data URI PNG; kosong bila akun belum punya TTD saat itu).
 *
 * Approve ulang (PO dikembalikan lalu di-approve lagi) menghasilkan stempel baru, jadi salinannya diganti dengan TTD saat approve yang baru.
 * Kompatibel PHP 7.2. Semua fungsi aman: kegagalan database tidak pernah membuat dokumen PO gagal tampil (jatuh ke TTD akun yang sekarang).
 */
class PoTtdSnapshot
{
    protected static $siap = false;

    /** Kolom di po_kegiatan yang mencatat siapa dan kapan approve, per peran. */
    protected static $kolom = array(
        'ketua' => array('by' => 'disetujui_by', 'at' => 'disetujui_at'),
        'ppk'   => array('by' => 'validasi_keuangan_by', 'at' => 'validasi_keuangan_at'),
    );

    public static function siapkan($db)
    {
        if (self::$siap) {
            return true;
        }
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS po_ttd_snapshot (
                    po_id INT NOT NULL,
                    peran VARCHAR(10) NOT NULL,
                    user_id INT NOT NULL DEFAULT 0,
                    stempel VARCHAR(32) NOT NULL DEFAULT '',
                    sumber VARCHAR(10) NOT NULL DEFAULT 'approve',
                    ttd_uri MEDIUMTEXT NULL,
                    dibuat_at DATETIME NULL,
                    PRIMARY KEY (po_id, peran)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            self::$siap = true;
        } catch (\Throwable $e) {
            error_log('[PoTtdSnapshot] gagal menyiapkan tabel: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /** Gambar TTD akun yang sekarang (data URI), '' bila belum punya. */
    protected static function tandaTanganAkun($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !class_exists('TtdPenggunaController')) {
            return '';
        }
        return (string) \TtdPenggunaController::dataUri($userId);
    }

    protected static function baris($db, $poId, $peran)
    {
        $r = $db->exec('SELECT user_id, stempel, sumber, ttd_uri FROM po_ttd_snapshot WHERE po_id = ? AND peran = ? LIMIT 1', array(1 => (int) $poId, 2 => (string) $peran));
        return !empty($r) ? $r[0] : null;
    }

    protected static function tulis($db, $poId, $peran, $userId, $stempel, $sumber, $uri)
    {
        $ada = self::baris($db, $poId, $peran);
        $sekarang = date('Y-m-d H:i:s');
        if ($ada) {
            $db->exec(
                'UPDATE po_ttd_snapshot SET user_id = ?, stempel = ?, sumber = ?, ttd_uri = ?, dibuat_at = ? WHERE po_id = ? AND peran = ?',
                array(1 => (int) $userId, 2 => (string) $stempel, 3 => (string) $sumber, 4 => (string) $uri, 5 => $sekarang, 6 => (int) $poId, 7 => (string) $peran)
            );
        } else {
            $db->exec(
                'INSERT INTO po_ttd_snapshot (po_id, peran, user_id, stempel, sumber, ttd_uri, dibuat_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                array(1 => (int) $poId, 2 => (string) $peran, 3 => (int) $userId, 4 => (string) $stempel, 5 => (string) $sumber, 6 => (string) $uri, 7 => $sekarang)
            );
        }
    }

    /** Siapa dan kapan approve untuk satu peran, dibaca dari po_kegiatan. Return [userId, stempel] atau null. */
    protected static function approve($db, $poId, $peran)
    {
        if (!isset(self::$kolom[$peran])) {
            return null;
        }
        $k = self::$kolom[$peran];
        try {
            $r = $db->exec('SELECT * FROM po_kegiatan WHERE id = ? LIMIT 1', array(1 => (int) $poId));
        } catch (\Throwable $e) {
            return null;
        }
        if (empty($r)) {
            return null;
        }
        $uid = (int) (isset($r[0][$k['by']]) ? $r[0][$k['by']] : 0);
        $at = trim((string) (isset($r[0][$k['at']]) ? $r[0][$k['at']] : ''));
        if ($uid <= 0 || $at === '' || strpos($at, '0000-00-00') === 0) {
            return null;
        }
        return array($uid, $at);
    }

    /**
     * Dipanggil tepat setelah tombol Approve berhasil (Ketua Tim -> 'ketua', PPK BLU -> 'ppk'): bekukan gambar TTD akun yang approve.
     * Tidak pernah melempar error.
     */
    public static function bekukan($db, $poId, $peran)
    {
        try {
            if (!self::siapkan($db)) {
                return false;
            }
            $a = self::approve($db, $poId, $peran);
            if ($a === null) {
                return false;
            }
            self::tulis($db, $poId, $peran, $a[0], $a[1], 'approve', self::tandaTanganAkun($a[0]));
            return true;
        } catch (\Throwable $e) {
            error_log('[PoTtdSnapshot] gagal membekukan TTD: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Gambar TTD yang dipakai di dokumen PO: salinan yang dibekukan saat approve. Bila belum ada (PO yang di-approve sebelum fitur ini, atau
     * approve ulang tanpa hook), dibekukan sekarang dari TTD akun yang approve. Return data URI, atau '' bila tidak ada.
     */
    public static function gambar($db, $poId, $peran, $userId, $stempel)
    {
        $userId = (int) $userId;
        $stempel = (string) $stempel;
        try {
            if (!self::siapkan($db)) {
                return self::tandaTanganAkun($userId);
            }
            $b = self::baris($db, $poId, $peran);
            if ($b && (int) $b['user_id'] === $userId && (string) $b['stempel'] === $stempel) {
                $uri = (string) $b['ttd_uri'];
                // Salinan 'lazy' yang kosong (akun belum punya TTD saat pertama dibuka) boleh terisi nanti; salinan 'approve' tidak pernah berubah.
                if ($uri === '' && (string) $b['sumber'] === 'lazy') {
                    $baru = self::tandaTanganAkun($userId);
                    if ($baru !== '') {
                        self::tulis($db, $poId, $peran, $userId, $stempel, 'lazy', $baru);
                        return $baru;
                    }
                }
                return $uri;
            }
            $uri = self::tandaTanganAkun($userId);
            self::tulis($db, $poId, $peran, $userId, $stempel, 'lazy', $uri);
            return $uri;
        } catch (\Throwable $e) {
            error_log('[PoTtdSnapshot] gagal membaca salinan TTD: ' . $e->getMessage());
            return self::tandaTanganAkun($userId);
        }
    }
}
