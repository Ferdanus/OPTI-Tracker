<?php
/**
 * ====================================================================
 * TTD DIGITAL (ditambahkan Kaa): tanda tangan gambar milik pengguna
 * ====================================================================
 * File BARU, berdiri sendiri (tidak mengubah tabel pengguna / ArsipUser). Dipakai halaman Profil Pengguna (kartu "Tanda Tangan Digital")
 * dan blok tanda tangan dokumen PO (PoKegiatanController::capPersetujuan -> PoTtd::htmlBlok).
 *
 *   GET  /profil/ttd/status   JSON {ok, ada, versi}
 *   GET  /profil/ttd/gambar   gambar TTD milik pengguna yang sedang login (hanya pemilik; folder penyimpanan tidak publik)
 *   POST /profil/ttd/unggah   foto/gambar TTD -> latar putih dihapus, dipotong rapi, diperkecil, disimpan PNG transparan
 *   POST /profil/ttd/hapus    hapus TTD milik sendiri
 *
 * Berkas disimpan di storage/ttd/<id_user>.png (di luar folder publik). TTD baru dipasang di dokumen PO SETELAH pemilik menekan
 * Approve (lihat PoKegiatanController::capPersetujuan).
 */
class TtdPenggunaController extends Controller
{
    const MAKS_BYTE = 8388608;   // 8 MB foto mentah
    const LEBAR_SIMPAN = 600;    // px, hasil akhir diperkecil (juga membatasi kualitas bila disalin orang lain)
    const TINGGI_SIMPAN = 260;

    /** Folder penyimpanan (di luar folder publik). */
    public static function dir()
    {
        return dirname(__DIR__, 2) . '/storage/ttd';
    }

    public static function path($idUser)
    {
        return self::dir() . '/' . (int) $idUser . '.png';
    }

    /** data URI PNG milik pengguna, atau '' bila belum upload. Dipakai langsung di HTML dokumen (juga Dompdf). */
    public static function dataUri($idUser)
    {
        $idUser = (int) $idUser;
        if ($idUser <= 0) { return ''; }
        $p = self::path($idUser);
        if (!is_file($p)) { return ''; }
        $b = @file_get_contents($p);
        return ($b === false || $b === '') ? '' : 'data:image/png;base64,' . base64_encode($b);
    }

    protected function json($data, $status = 200)
    {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function status($f3)
    {
        $this->requireAuth();
        $p = self::path((int) $this->getUserId());
        $ada = is_file($p);
        $this->json(['ok' => true, 'ada' => $ada, 'versi' => $ada ? (int) filemtime($p) : 0]);
    }

    public function gambar($f3)
    {
        $this->requireAuth();
        $p = self::path((int) $this->getUserId());
        if (!is_file($p)) { http_response_code(404); exit; }
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($p));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($p);
        exit;
    }

    public function unggah($f3)
    {
        $this->requireAuth();
        $uid = (int) $this->getUserId();
        if ($uid <= 0) { $this->json(['ok' => false, 'pesan' => 'Sesi login tidak valid.'], 401); }
        if (!function_exists('imagecreatefromstring')) {
            $this->json(['ok' => false, 'pesan' => 'Ekstensi GD PHP belum aktif di server, gambar tidak bisa diproses.'], 500);
        }
        if (empty($_FILES['ttd']) || !is_uploaded_file($_FILES['ttd']['tmp_name']) || (int) $_FILES['ttd']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'pesan' => 'Pilih foto / gambar tanda tangan dulu (maksimal 8 MB).'], 422);
        }
        if ((int) $_FILES['ttd']['size'] > self::MAKS_BYTE) {
            $this->json(['ok' => false, 'pesan' => 'Ukuran berkas terlalu besar (maksimal 8 MB).'], 422);
        }
        $raw = @file_get_contents($_FILES['ttd']['tmp_name']);
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_buffer($fi, (string) $raw);
            finfo_close($fi);
        }
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
            $this->json(['ok' => false, 'pesan' => 'Format harus gambar PNG, JPG, atau WEBP.'], 422);
        }
        $ambang = (int) $f3->get('POST.ambang');
        if ($ambang < 120 || $ambang > 250) { $ambang = 215; }
        $warna = ((string) $f3->get('POST.warna') === 'hitam') ? [17, 24, 39] : [30, 58, 138];

        $hasil = self::proses((string) $raw, $ambang, $warna);
        if (!is_string($hasil)) { $this->json(['ok' => false, 'pesan' => (string) $hasil[0]], 422); }

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->json(['ok' => false, 'pesan' => 'Folder penyimpanan TTD tidak bisa dibuat (cek izin folder storage).'], 500);
        }
        if (@file_put_contents(self::path($uid), $hasil, LOCK_EX) === false) {
            $this->json(['ok' => false, 'pesan' => 'Gagal menyimpan TTD (cek izin tulis folder storage/ttd).'], 500);
        }
        $this->json(['ok' => true, 'versi' => (int) filemtime(self::path($uid))]);
    }

    public function hapus($f3)
    {
        $this->requireAuth();
        $p = self::path((int) $this->getUserId());
        if (is_file($p)) { @unlink($p); }
        $this->json(['ok' => true]);
    }

    /**
     * Foto -> PNG transparan. Piksel terang (>= ambang) dihapus (tepi dilembutkan 40 tingkat), goresan dijadikan satu warna tinta,
     * lalu dipotong sesuai goresan dan diperkecil. Mengembalikan string PNG, atau array [pesan] bila gagal.
     */
    public static function proses($data, $ambang, array $warna)
    {
        $src = @imagecreatefromstring($data);
        if (!$src) { return ['Gambar tidak bisa dibaca. Coba foto / berkas lain.']; }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w > 900) {
            // Pengecilan memakai imagecopyresampled di atas latar putih (imagescale + BICUBIC di sebagian versi GD menimbulkan garis hitam di tepi dan latar abu-abu).
            $nh = max(1, (int) round($h * 900 / $w));
            $kecil = imagecreatetruecolor(900, $nh);
            if ($kecil) {
                imagealphablending($kecil, true);
                imagefill($kecil, 0, 0, imagecolorallocate($kecil, 255, 255, 255));
                imagecopyresampled($kecil, $src, 0, 0, 0, 0, 900, $nh, $w, $h);
                imagedestroy($src); $src = $kecil; $w = 900; $h = $nh;
            }
        }
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        $cache = [];
        $minx = $w; $miny = $h; $maxx = -1; $maxy = -1; $tinta = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($x < 2 || $y < 2 || $x >= $w - 2 || $y >= $h - 2) { continue; }   // abaikan 2 px tepi: sisa artefak tepi hasil pengecilan / bingkai foto
                $c = imagecolorat($src, $x, $y);
                if ((($c >> 24) & 127) >= 100) { continue; }   // sudah transparan
                $lum = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
                if ($lum >= $ambang) { continue; }
                $a255 = ($lum > $ambang - 40) ? (int) round(255 * ($ambang - $lum) / 40) : 255;
                $ga = 127 - (int) round($a255 * 127 / 255);
                if (!isset($cache[$ga])) { $cache[$ga] = imagecolorallocatealpha($dst, $warna[0], $warna[1], $warna[2], $ga); }
                imagesetpixel($dst, $x, $y, $cache[$ga]);
                $tinta++;
                if ($x < $minx) { $minx = $x; } if ($x > $maxx) { $maxx = $x; }
                if ($y < $miny) { $miny = $y; } if ($y > $maxy) { $maxy = $y; }
            }
        }
        imagedestroy($src);
        if ($tinta < 200 || $maxx < 0) {
            imagedestroy($dst);
            return ['Goresan tanda tangan tidak terbaca. Pakai foto di kertas putih dengan cahaya merata, atau geser pengaturan latar.'];
        }
        $pad = 4;
        $rect = ['x' => max(0, $minx - $pad), 'y' => max(0, $miny - $pad), 'width' => min($w, $maxx - $minx + 1 + 2 * $pad), 'height' => min($h, $maxy - $miny + 1 + 2 * $pad)];
        $pot = imagecrop($dst, $rect);
        imagedestroy($dst);
        if (!$pot) { return ['Gambar gagal dipotong.']; }
        $cw = imagesx($pot);
        $ch = imagesy($pot);
        $skala = min(self::LEBAR_SIMPAN / $cw, self::TINGGI_SIMPAN / $ch, 1.0);
        if ($skala < 1.0) {
            $nw = max(1, (int) round($cw * $skala)); $nh2 = max(1, (int) round($ch * $skala));
            $k = imagecreatetruecolor($nw, $nh2);
            if ($k) {
                imagealphablending($k, false); imagesavealpha($k, true);
                imagefill($k, 0, 0, imagecolorallocatealpha($k, 0, 0, 0, 127));
                imagecopyresampled($k, $pot, 0, 0, 0, 0, $nw, $nh2, $cw, $ch);
                imagedestroy($pot); $pot = $k;
            }
        }
        imagealphablending($pot, false);
        imagesavealpha($pot, true);
        ob_start();
        imagepng($pot, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($pot);
        return $png;
    }
}
