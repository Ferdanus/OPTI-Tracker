<?php
/**
 * ====================================================================
 * LAPORAN AKHIR (tahap baru, ditambahkan Kaa)
 * ====================================================================
 * Tahap 10 di Detail Order, di antara Kontrak PKS (Tahap 9) dan BAST (sekarang Tahap 11).
 * Sifatnya OPSIONAL (laporan bisa bersifat rahasia): Ketua Pelaksana / Ketua Tim bisa meng-upload PDF laporan akhir, lalu menekan "Selesai".
 * Boleh juga langsung "Selesai" tanpa file. BAST baru terbuka kalau PKS sudah dibuat DAN tahap ini sudah Selesai.
 *
 *   POST /order/@id/laporan-akhir/unggah   upload satu file PDF (maks. 30 MB); field 'file_laporan'
 *   POST /order/@id/laporan-akhir/hapus    hapus file laporan (hanya sebelum Selesai)
 *   POST /order/@id/laporan-akhir/selesai  tandai tahap Selesai (dengan atau tanpa file)
 *   GET  /order/@id/laporan-akhir          lihat PDF (?unduh=1 untuk mengunduh)
 *
 * Data disimpan di kolom order_layanan.laporan_akhir_* (dibuat otomatis oleh OrderLayanan::ensureColumns).
 * File disimpan di storage/laporan_akhir/ (di luar folder publik), dibuka hanya lewat route di atas.
 */
class LaporanAkhirController extends Controller
{
    const MAKS_BYTE = 31457280;   // 30 MB

    protected function dir()
    {
        $d = dirname(__DIR__, 2) . '/storage/laporan_akhir/';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return $d;
    }

    /** Siapa yang boleh upload / hapus / menyelesaikan: Superadmin, Ketua Tim divisi terkait, atau Ketua Pelaksana order ini. */
    protected function bolehKelola(array $order)
    {
        if ($this->isSuperadmin()) { return true; }
        if ($this->isKetuaTimForOrder($order)) { return true; }
        return $this->isTimKerja() && (int) ($order['ketua_pelaksana_id'] ?? 0) === (int) $this->getUserId();
    }

    /** Balasan: JSON untuk permintaan AJAX, selain itu flash + redirect. */
    protected function balas($f3, $ok, $pesan, $kembali, $extra = array())
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array_merge(array('success' => (bool) $ok, 'message' => $pesan), $extra), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($ok) { $this->setFlashSuccess($pesan); } else { $this->setFlashError($pesan); }
        $f3->reroute($kembali);
    }

    /**
     * Muat order + PO + BAST dan cek syarat umum. Mengembalikan array(order, po, pesanError).
     * Syarat: order ada, pengguna berhak, PO sudah bertanda tangan (PO Aktif penuh), belum ada BAST, order belum ditutup.
     */
    protected function konteks($id)
    {
        try { \OrderLayanan::ensureColumns($this->db); } catch (\Throwable $e) {}
        $order = (new \OrderLayanan($this->db))->getDetail($id);
        if (!$order) { return array(null, null, "Order Layanan #{$id} tidak ditemukan."); }
        if (!$this->bolehKelola($order)) {
            return array($order, null, 'Akses Ditolak: Laporan Akhir hanya dapat dikelola oleh Ketua Tim, Ketua Pelaksana order ini, atau Superadmin.');
        }
        try { \PoSchema::pastikan($this->db); } catch (\Throwable $e) {}
        $r = $this->db->exec('SELECT * FROM po_kegiatan WHERE order_id = ?' . \PoSchema::bukanSertifikasi('') . ' ORDER BY id DESC LIMIT 1', array(1 => $id));
        $po = $r ? $r[0] : null;
        if (!$po || empty($po['validasi_keuangan_at']) || empty($po['po_ttd_file'])) {
            return array($order, $po, 'Laporan Akhir baru terbuka setelah PO bertanda tangan di-upload (PO Aktif penuh).');
        }
        $bast = (new \OptiBast($this->db))->getByOrderId($id);
        if (!empty($bast) || ($order['status'] ?? '') === 'selesai') {
            return array($order, $po, 'BAST sudah dibuat / order sudah selesai, Laporan Akhir tidak bisa diubah lagi.');
        }
        return array($order, $po, '');
    }

    protected function namaPengguna()
    {
        return isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : (isset($_SESSION['nama_user']) ? $_SESSION['nama_user'] : 'User');
    }

    // ---------------------------------------------------------------- upload
    public function unggah($f3, $params)
    {
        $this->requireAuth();
        $id = (int) (isset($params['id']) ? $params['id'] : 0);
        $back = "/order/{$id}?tahap=11";
        list($order, $po, $err) = $this->konteks($id);
        if ($err !== '') { return $this->balas($f3, false, $err, $back); }
        // setelah Selesai, file hanya boleh ditambahkan kalau belum ada (tidak boleh diganti)
        if (!empty($order['laporan_akhir_selesai_at']) && !empty($order['laporan_akhir_file'])) {
            return $this->balas($f3, false, 'Laporan Akhir sudah Selesai, file tidak bisa diganti lagi.', $back);
        }

        $file = isset($_FILES['file_laporan']) ? $_FILES['file_laporan'] : null;
        if (!$file || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            $lebih = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
            if ($lebih > 0 && empty($_FILES)) { return $this->balas($f3, false, 'Ukuran file terlalu besar untuk dikirim. Maksimal 30 MB.', $back); }
            return $this->balas($f3, false, 'Pilih file PDF laporan akhir terlebih dahulu.', $back);
        }
        if ((int) $file['error'] === UPLOAD_ERR_INI_SIZE || (int) $file['error'] === UPLOAD_ERR_FORM_SIZE || (int) $file['size'] > self::MAKS_BYTE) {
            return $this->balas($f3, false, 'Ukuran file maksimal 30 MB. Pilih file yang lebih kecil.', $back);
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] <= 0 || !is_uploaded_file($file['tmp_name'])) {
            return $this->balas($f3, false, 'Terjadi kesalahan saat mengunggah file. Coba lagi.', $back);
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            return $this->balas($f3, false, 'Format file harus PDF.', $back);
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $kepala = (string) @file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($mime !== 'application/pdf' || $kepala !== '%PDF-') {
            return $this->balas($f3, false, 'Isi file tidak sesuai dengan format PDF.', $back);
        }

        $dir = $this->dir();
        $simpan = bin2hex(random_bytes(12)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $dir . $simpan)) {
            return $this->balas($f3, false, 'Gagal menyimpan file ke server.', $back);
        }
        $nama = preg_replace('/[\x00-\x1F\\\\\/:*?"<>|]+/u', '-', basename($file['name']));
        $lama = (string) ($order['laporan_akhir_file'] ?? '');
        $oleh = $this->namaPengguna();
        $this->db->exec(
            'UPDATE order_layanan SET laporan_akhir_file = ?, laporan_akhir_nama = ?, laporan_akhir_at = NOW(), laporan_akhir_oleh = ? WHERE id = ?',
            array(1 => $simpan, 2 => $nama, 3 => $oleh, 4 => $id)
        );
        if ($lama !== '' && $lama !== $simpan && basename($lama) === $lama) { @unlink($dir . $lama); }

        $this->logActivity($id, 'laporan_akhir', 'upload_laporan_akhir', 'Upload laporan akhir: ' . $nama);
        return $this->balas($f3, true, 'Laporan akhir berhasil di-upload.', $back, array('nama' => $nama, 'ukuran' => (int) $file['size']));
    }

    // ---------------------------------------------------------------- hapus
    public function hapus($f3, $params)
    {
        $this->requireAuth();
        $id = (int) (isset($params['id']) ? $params['id'] : 0);
        $back = "/order/{$id}?tahap=11";
        list($order, $po, $err) = $this->konteks($id);
        if ($err !== '') { return $this->balas($f3, false, $err, $back); }
        if (!empty($order['laporan_akhir_selesai_at'])) {
            return $this->balas($f3, false, 'Laporan Akhir sudah Selesai, file tidak bisa dihapus.', $back);
        }
        $lama = (string) ($order['laporan_akhir_file'] ?? '');
        $this->db->exec('UPDATE order_layanan SET laporan_akhir_file = NULL, laporan_akhir_nama = NULL, laporan_akhir_at = NULL, laporan_akhir_oleh = NULL WHERE id = ?', array(1 => $id));
        if ($lama !== '' && basename($lama) === $lama) { @unlink($this->dir() . $lama); }
        $this->logActivity($id, 'laporan_akhir', 'hapus_laporan_akhir', 'Hapus file laporan akhir (sebelum diselesaikan)');
        return $this->balas($f3, true, 'File laporan akhir dihapus.', $back);
    }

    // ---------------------------------------------------------------- selesai
    public function selesai($f3, $params)
    {
        $this->requireAuth();
        $id = (int) (isset($params['id']) ? $params['id'] : 0);
        $back = "/order/{$id}?tahap=11";
        list($order, $po, $err) = $this->konteks($id);
        if ($err !== '') { return $this->balas($f3, false, $err, $back); }
        if (!empty($order['laporan_akhir_selesai_at'])) {
            return $this->balas($f3, true, 'Laporan Akhir sudah diselesaikan sebelumnya.', $back);
        }
        $oleh = $this->namaPengguna();
        $this->db->exec('UPDATE order_layanan SET laporan_akhir_selesai_at = NOW(), laporan_akhir_selesai_oleh = ? WHERE id = ?', array(1 => $oleh, 2 => $id));
        $adaFile = !empty($order['laporan_akhir_file']);
        // BAST otomatis: kalau PKS sudah dibuat, draf BAST langsung dibuat dari PKS (Ketua Tim diberi tahu). Kalau PKS belum, dibuat saat PKS selesai.
        try { \OptiBast::siapkanDraf($this->db, $id, (int) $this->getUserId(), $oleh); } catch (\Throwable $e) {}
        $this->logActivity($id, 'laporan_akhir', 'selesai_laporan_akhir', 'Order diselesaikan (Laporan Akhir) ' . ($adaFile ? 'dengan laporan akhir terlampir' : 'tanpa laporan akhir'));
        $pks = !empty($po['kontrak_dibuat_at']);
        $pesan = $pks ? 'Order diselesaikan. BAST sudah disiapkan otomatis untuk Ketua Tim.' : 'Order diselesaikan. BAST disiapkan otomatis setelah PKS dibuat.';
        return $this->balas($f3, true, $pesan, $back, array('pks' => $pks));
    }

    // ---------------------------------------------------------------- lihat / unduh
    public function lihat($f3, $params)
    {
        $this->requireAuth();
        $id = (int) (isset($params['id']) ? $params['id'] : 0);
        if (!$this->hasPermission('order:view') && !$this->isSuperadmin()) {
            $this->setFlashError('Akses Ditolak.');
            $f3->reroute('/order');
            return;
        }
        $r = $this->db->exec('SELECT laporan_akhir_file, laporan_akhir_nama FROM order_layanan WHERE id = ? LIMIT 1', array(1 => $id));
        $nama = $r ? (string) $r[0]['laporan_akhir_file'] : '';
        $path = $this->dir() . $nama;
        if ($nama === '' || basename($nama) !== $nama || !is_file($path)) {
            $this->setFlashError('File laporan akhir tidak ditemukan.');
            $f3->reroute("/order/{$id}");
            return;
        }
        $unduh = (bool) $f3->get('GET.unduh');
        $tampil = (string) $r[0]['laporan_akhir_nama'];
        $tampil = str_replace('/', '-', $tampil !== '' ? $tampil : $nama);
        $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $tampil);
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($tampil));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
