<?php
/**
 * PoReviewController
 *
 * Halaman "Daftar PO" untuk Ketua Tim: PO berstatus 'terkirim', di-review lewat panel,
 * plus chat dengan Ketua Pelaksana (pembuat PO). Ketua Pelaksana memakai halaman yang sama
 * (mode 'pembuat') untuk membaca dan membalas catatan.
 *
 * Aturan sisi:
 *  - superadmin dan ketua_tim  -> sisi 'katim'   (ketua_tim dibatasi ke divisinya, sama seperti KetuaTimController)
 *  - tim_kerja                 -> sisi 'pembuat' (hanya PO yang ketua_pelaksana_id-nya = dirinya)
 *
 * Kompatibel PHP 7.2. Tabel: po_chat, po_kegiatan.reviewed_at (lihat migration_po_review_chat.sql).
 */
class PoReviewController extends Controller {

    const DIVISI_VALID = ['selulosa', 'lingkungan'];
    const PANJANG_MAKS = 2000;

    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /po-kegiatan/daftar */
    public function index($f3) {
        $this->requireAuth();

        $mode = $this->modeHalaman();
        if ($mode === null) {
            $f3->error(403, 'Halaman ini hanya untuk Ketua Tim dan Ketua Pelaksana.');
            return;
        }

        $uid = (int) $this->getUserId();
        $sisiLawan = ($mode === 'katim') ? 'pembuat' : 'katim'; // pesan yang belum dibaca pengguna berasal dari sisi lawan

        $sql = "SELECT p.id, p.nomor_po,
                       COALESCE(p.updated_at, p.created_at) AS dikirim_at,
                       p.reviewed_at,
                       o.nomor_order, o.judul_kegiatan, o.jenis_layanan_opti, o.ketua_pelaksana_id,
                       c.nmcustomer AS nama_perusahaan, c.pt_cv,
                       (SELECT COUNT(*) FROM po_chat pc
                         WHERE pc.po_id = p.id AND pc.sisi = ? AND pc.dibaca_at IS NULL) AS chat_unread
                FROM po_kegiatan p
                JOIN order_layanan o ON o.id = p.order_id
                JOIN tb_customer c ON c.id_customer = o.id_customer
                WHERE p.status = 'terkirim'";
        $params = [1 => $sisiLawan];
        $idx = 2;
        $this->tambahScope($mode, $uid, $sql, $params, $idx);
        $sql .= " ORDER BY COALESCE(p.updated_at, p.created_at) DESC";

        $errorMessage = null;
        $rows = [];
        try {
            $rows = $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            $errorMessage = 'Gagal memuat daftar PO: ' . $e->getMessage() . ' (sudah menjalankan migration_po_review_chat.sql?)';
        }

        $idPembuat = [];
        foreach ($rows as $r) { $idPembuat[] = $r['ketua_pelaksana_id']; }
        $peta = $this->petaNamaUser($idPembuat);

        $daftar = [];
        foreach ($rows as $r) {
            $idp = (int) $r['ketua_pelaksana_id'];
            $daftar[] = [
                'id'       => (int) $r['id'],
                'nomor'    => (string) $r['nomor_po'],
                'order'    => (string) $r['nomor_order'],
                'mitra'    => $this->formatMitra($r['nama_perusahaan'], $r['pt_cv']),
                'judul'    => (string) $r['judul_kegiatan'],
                'divisi'   => (string) $r['jenis_layanan_opti'],
                'pembuat'  => isset($peta[$idp]) ? $peta[$idp] : '-',
                'dikirim'  => substr((string) $r['dikirim_at'], 0, 10),
                'reviewed' => !empty($r['reviewed_at']),
                'unread'   => (int) $r['chat_unread'],
            ];
        }

        $f3->set('daftar_po_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('mode', $mode);
        $f3->set('error_message', $errorMessage);

        $judul = ($mode === 'katim') ? 'Daftar PO' : 'Diskusi PO';
        $this->render('katim_kerja/po-kegiatan/daftar.html', $judul, 'po_daftar');
    }

    /* ============================================================
     * API (JSON)
     * ============================================================ */

    /** GET /po-kegiatan/@id/chat -- seluruh pesan pada satu PO */
    public function chatList($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();

        $rows = $this->db->exec(
            "SELECT id, pengirim_id, sisi, pesan, created_at FROM po_chat WHERE po_id = ? ORDER BY id ASC",
            [1 => (int) $po['id']]
        );

        $ids = [];
        foreach ($rows as $r) { $ids[] = $r['pengirim_id']; }
        $peta = $this->petaNamaUser($ids);

        $pesan = [];
        foreach ($rows as $r) {
            $pesan[] = $this->bentukPesan($r, $peta, $uid);
        }

        $this->keluarkanJson(['ok' => true, 'sisi' => $sisi, 'pesan' => $pesan]);
    }

    /** POST /po-kegiatan/@id/chat -- kirim pesan (field: pesan) */
    public function chatKirim($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();

        $teks = trim((string) $f3->get('POST.pesan'));
        if ($teks === '') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Pesan tidak boleh kosong.'], 422);
        }
        if (mb_strlen($teks, 'UTF-8') > self::PANJANG_MAKS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Pesan terlalu panjang (maksimal ' . self::PANJANG_MAKS . ' karakter).'], 422);
        }

        try {
            $this->db->exec(
                "INSERT INTO po_chat (po_id, pengirim_id, sisi, pesan, created_at) VALUES (?, ?, ?, ?, NOW())",
                [1 => (int) $po['id'], 2 => $uid, 3 => $sisi, 4 => $teks]
            );
            $newId = (int) $this->db->lastInsertId();
            $baris = $this->db->exec(
                "SELECT id, pengirim_id, sisi, pesan, created_at FROM po_chat WHERE id = ?",
                [1 => $newId]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengirim pesan.'], 500);
        }

        $peta = $this->petaNamaUser([$uid]);
        $this->keluarkanJson(['ok' => true, 'pesan' => $this->bentukPesan($baris[0], $peta, $uid)]);
    }

    /** POST /po-kegiatan/@id/chat/baca -- tandai pesan dari sisi lawan sebagai sudah dibaca */
    public function chatBaca($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $lawan = ($sisi === 'katim') ? 'pembuat' : 'katim';

        try {
            $this->db->exec(
                "UPDATE po_chat SET dibaca_at = NOW() WHERE po_id = ? AND sisi = ? AND dibaca_at IS NULL",
                [1 => (int) $po['id'], 2 => $lawan]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menandai dibaca.'], 500);
        }

        $this->keluarkanJson(['ok' => true]);
    }

    /** POST /po-kegiatan/@id/review -- Ketua Tim menandai PO sudah direview */
    public function tandaiReview($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);

        if ($sisi !== 'katim') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Hanya Ketua Tim yang dapat menandai review.'], 403);
        }
        if ($po['status'] !== 'terkirim') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Hanya PO berstatus terkirim yang dapat direview.'], 422);
        }

        try {
            $this->db->exec(
                "UPDATE po_kegiatan SET reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND reviewed_at IS NULL",
                [1 => (int) $this->getUserId(), 2 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan status review.'], 500);
        }

        $this->keluarkanJson(['ok' => true]);
    }

    /**
     * GET /po-kegiatan/notif -- ringkasan pesan belum dibaca (satu baris per PO), untuk dropdown lonceng.
     * Belum dipasang ke lonceng topbar; endpoint ini siap dipakai.
     */
    public function notifJson($f3) {
        $this->requireAuth();

        $mode = $this->modeHalaman();
        if ($mode === null) {
            $this->keluarkanJson(['ok' => true, 'total' => 0, 'items' => []]);
        }

        $uid = (int) $this->getUserId();
        $sisiLawan = ($mode === 'katim') ? 'pembuat' : 'katim';

        $sql = "SELECT pc.po_id, p.nomor_po, pc.pengirim_id, pc.pesan, pc.created_at,
                       (SELECT COUNT(*) FROM po_chat x
                         WHERE x.po_id = pc.po_id AND x.sisi = ? AND x.dibaca_at IS NULL) AS jumlah
                FROM po_chat pc
                JOIN po_kegiatan p ON p.id = pc.po_id
                JOIN order_layanan o ON o.id = p.order_id
                WHERE p.status = 'terkirim'
                  AND pc.sisi = ? AND pc.dibaca_at IS NULL
                  AND pc.id = (SELECT MAX(y.id) FROM po_chat y
                                WHERE y.po_id = pc.po_id AND y.sisi = ? AND y.dibaca_at IS NULL)";
        $params = [1 => $sisiLawan, 2 => $sisiLawan, 3 => $sisiLawan];
        $idx = 4;
        $this->tambahScope($mode, $uid, $sql, $params, $idx);
        $sql .= " ORDER BY pc.id DESC LIMIT 15";

        try {
            $rows = $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat notifikasi.'], 500);
        }

        $ids = [];
        foreach ($rows as $r) { $ids[] = $r['pengirim_id']; }
        $peta = $this->petaNamaUser($ids);

        $items = [];
        $total = 0;
        foreach ($rows as $r) {
            $jumlah = (int) $r['jumlah'];
            $total += $jumlah;
            $items[] = [
                'po_id'    => (int) $r['po_id'],
                'nomor'    => (string) $r['nomor_po'],
                'pengirim' => isset($peta[(int) $r['pengirim_id']]) ? $peta[(int) $r['pengirim_id']] : '-',
                'cuplikan' => mb_substr((string) $r['pesan'], 0, 120, 'UTF-8'),
                'waktu'    => (string) $r['created_at'],
                'jumlah'   => $jumlah,
                'url'      => $f3->get('BASE') . '/po-kegiatan/daftar',
            ];
        }

        $this->keluarkanJson(['ok' => true, 'total' => $total, 'items' => $items]);
    }

    /* ============================================================
     * HELPER
     * ============================================================ */

    /** 'katim' | 'pembuat' | null, berdasarkan peran login. */
    protected function modeHalaman() {
        if ($this->isSuperadmin()) { return 'katim'; }
        $role = $this->getUserRole();
        if ($role === 'ketua_tim') { return 'katim'; }
        if ($role === 'tim_kerja') { return 'pembuat'; }
        return null;
    }

    /** Divisi Ketua Tim ('' = semua divisi). Superadmin selalu semua. */
    protected function divisiKatim() {
        if ($this->isSuperadmin()) { return ''; }
        $d = isset($_SESSION['jenis_layanan_opti']) ? (string) $_SESSION['jenis_layanan_opti'] : '';
        return in_array($d, self::DIVISI_VALID, true) ? $d : '';
    }

    /** Tambah filter cakupan data ke SQL (alias tabel: o = order_layanan). */
    protected function tambahScope($mode, $uid, &$sql, &$params, &$idx) {
        if ($mode === 'katim') {
            $div = $this->divisiKatim();
            if ($div !== '') {
                $sql .= " AND o.jenis_layanan_opti = ?";
                $params[$idx++] = $div;
            }
        } else {
            $sql .= " AND o.ketua_pelaksana_id = ?";
            $params[$idx++] = (int) $uid;
        }
    }

    /** Ambil PO + order + mitra. Null kalau tidak ada. */
    protected function ambilPo($poId) {
        $rows = $this->db->exec(
            "SELECT p.id, p.nomor_po, p.status, p.reviewed_at,
                    o.id AS order_id, o.jenis_layanan_opti, o.ketua_pelaksana_id
             FROM po_kegiatan p
             JOIN order_layanan o ON o.id = p.order_id
             WHERE p.id = ? LIMIT 1",
            [1 => (int) $poId]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /** Sisi pengguna terhadap PO ini: 'katim' | 'pembuat' | null (tidak berhak). */
    protected function sisiPengguna(array $po) {
        $mode = $this->modeHalaman();
        if ($mode === 'katim') {
            $div = $this->divisiKatim();
            if ($div !== '' && $div !== $po['jenis_layanan_opti']) { return null; }
            return 'katim';
        }
        $uid = (int) $this->getUserId();
        if ($mode === 'pembuat' && $uid > 0 && $uid === (int) $po['ketua_pelaksana_id']) {
            return 'pembuat';
        }
        return null;
    }

    /** Wajib login + berhak atas PO. Mengembalikan [$po, $sisi] atau langsung menjawab JSON error. */
    protected function akses($poId) {
        $this->requireAuth();

        $po = ($poId > 0) ? $this->ambilPo($poId) : null;
        if (!$po) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan.'], 404);
        }
        $sisi = $this->sisiPengguna($po);
        if ($sisi === null) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke PO ini.'], 403);
        }
        return [$po, $sisi];
    }

    protected function bentukPesan(array $r, array $peta, $uid) {
        $idp = (int) $r['pengirim_id'];
        return [
            'id'    => (int) $r['id'],
            'sisi'  => $r['sisi'],
            'nama'  => isset($peta[$idp]) ? $peta[$idp] : '-',
            'pesan' => (string) $r['pesan'],
            'waktu' => (string) $r['created_at'],
            'saya'  => ($idp === (int) $uid),
        ];
    }

    /** Nama pegawai dari tb_arsipuser (database Sekretariat). Peta: id_user => nama_user. */
    protected function petaNamaUser(array $ids) {
        $bersih = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) { $bersih[$n] = true; }
        }
        if (empty($bersih)) { return []; }

        $in = implode(',', array_keys($bersih));
        $peta = [];
        try {
            $rows = $this->dbSekretariat->exec("SELECT id_user, nama_user FROM tb_arsipuser WHERE id_user IN ($in)");
            foreach ($rows as $r) { $peta[(int) $r['id_user']] = $r['nama_user']; }
        } catch (\Exception $e) {
            // nama tampil '-' kalau database Sekretariat tidak terjangkau
        }
        return $peta;
    }

    /** "PT PT Kertas X" + pt_cv "PT" -> "PT Kertas X" (awalan berulang dibuang, badan usaha dipasang sekali). */
    protected function formatMitra($nama, $ptCv) {
        $bersih = trim((string) preg_replace('/^((PT|CV|UD)\.?\s+)+/i', '', (string) $nama));
        $badan  = trim((string) $ptCv);
        return ($badan !== '') ? $badan . ' ' . $bersih : $bersih;
    }

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}