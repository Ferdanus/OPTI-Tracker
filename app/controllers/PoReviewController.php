<?php
/**
 * PoReviewController
 *
 * Halaman "Daftar PO": Ketua Tim mereview PO yang dikirim Ketua Pelaksana, memberi catatan revisi per bagian,
 * menyetujui, atau membuka kembali. Ketua Pelaksana menerima catatan, memperbaiki, lalu mengirim revisi.
 *
 * Siklus status_review:
 *   menunggu_review --(Ketua Tim: Minta revisi)--> perlu_revisi --(Pelaksana: Kirim revisi)--> direvisi
 *   direvisi --(Ketua Tim: Minta revisi)--> perlu_revisi                 (putaran revisi bertambah)
 *   menunggu_review / direvisi --(Ketua Tim: Setujui)--> disetujui       (PO terkunci, diteruskan ke Humas -> Tim Mitra -> PPK BLU)
 *   disetujui --(Ketua Tim: Buka kembali)--> menunggu_review
 *
 * Jejak kirim dan baca (NULL pada kolom "dibaca" = belum dibaca):
 *   PO dikirim              dikirim_at  -> dibaca Ketua Tim        reviewed_at
 *   permintaan revisi       minta_at    -> dibaca Ketua Pelaksana  minta_dibaca_at
 *   revisi dikirim balik    revisi_at   -> dibaca Ketua Tim        revisi_dibaca_at
 *
 * Catatan revisi (tabel po_revisi_catatan): draf (hanya terlihat Ketua Tim) -> terbuka (terkirim) -> selesai (ditandai pelaksana).
 *
 * Aturan sisi:
 *  - superadmin dan ketua_tim -> sisi 'katim' (ketua_tim dibatasi ke divisinya). Superadmin tidak menandai "dibaca".
 *  - tim_kerja                -> sisi 'pembuat' (hanya PO yang ketua_pelaksana_id-nya = dirinya)
 *
 * Kompatibel PHP 7.2. Butuh: migration_po_review_chat.sql, migration_po_status_review.sql, migration_po_review_revisi.sql
 * Tanda komentar "sql:nama" dipakai skrip uji untuk mengekstrak query.
 */
class PoReviewController extends Controller {

    const DIVISI_VALID   = ['selulosa', 'lingkungan'];
    const PANJANG_MAKS   = 2000;   // chat
    const CATATAN_MAKS   = 500;    // catatan revisi
    const CATATAN_BATAS  = 100;    // jumlah catatan per PO
    const LIMIT_DAFTAR   = 500;

    protected static $kolomCache = [];
    /** true = mode lihat saja lintas divisi: daftarItems tidak dibatasi divisi / pelaksana (HANYA dipasang oleh jalur baca di bawah). */
    protected $lihatSaja = false;

    /* ============================================================
     * HALAMAN DAN DATA DAFTAR
     * ============================================================ */

    /** GET /po-kegiatan/daftar */
    public function index($f3) {
        $this->requireAuth();

        $mode = $this->modeHalaman();
        if ($mode === null) {
            $f3->error(403, 'Halaman ini hanya untuk Ketua Tim dan Ketua Pelaksana.');
            return;
        }

        $errorMessage = null;
        $daftar = [];
        try {
            $daftar = $this->daftarItems($mode, (int) $this->getUserId(), 0);
            // Panel "Lihat PO" untuk PO berstatus draft (?embed=1&buka=ID&lihat=1&draft=1): PO draft yang diminta ikut dimuat (cakupan divisi / pembuat tetap berlaku)
            $bukaDraft = (int) $f3->get('GET.buka');
            if ($bukaDraft > 0 && (string) $f3->get('GET.draft') === '1') {
                $adaD = false;
                foreach ($daftar as $dD) { if ((int) $dD['id'] === $bukaDraft) { $adaD = true; break; } }
                if (!$adaD) { $daftar = array_merge($daftar, $this->daftarItems($mode, (int) $this->getUserId(), $bukaDraft)); }
            }
            // Panel "hanya lihat" untuk PO divisi / layanan lain (?embed=1&buka=ID&lihat=N&ro=1): PO yang diminta ikut dimuat tanpa batas divisi, hanya untuk dibaca
            $bukaRo = (int) $f3->get('GET.buka');
            if ($bukaRo > 0 && (string) $f3->get('GET.ro') === '1') {
                $ada = false;
                foreach ($daftar as $dRo) { if ((int) $dRo['id'] === $bukaRo) { $ada = true; break; } }
                if (!$ada) {
                    $this->lihatSaja = true;
                    try { $daftar = array_merge($daftar, $this->daftarItems($mode, (int) $this->getUserId(), $bukaRo)); } finally { $this->lihatSaja = false; }
                }
            }
        } catch (\Exception $e) {
            $errorMessage = 'Gagal memuat daftar PO: ' . $e->getMessage() . ' (sudah menjalankan ketiga file migration?)';
        }

        $f3->set('daftar_po_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('mode', ($mode === 'pjt') ? 'pembuat' : $mode);   // PJT memakai tampilan baca saja milik pembuat
        $f3->set('pjt_mode', ($mode === 'pjt') ? '1' : '');
        $f3->set('error_message', $errorMessage);

        $this->render('katim_kerja/po-kegiatan/daftar.html', 'Daftar PO', 'daftar_po');
    }

    /** GET /po-kegiatan/daftar/data -- daftar yang sama dalam bentuk JSON (penyegaran otomatis di halaman) */
    public function data($f3) {
        $this->requireAuth();
        $mode = $this->modeHalaman();
        if ($mode === null) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Tidak ada akses.'], 403);
        }
        try {
            $daftar = $this->daftarItems($mode, (int) $this->getUserId(), 0);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat daftar PO.'], 500);
        }
        $this->keluarkanJson(['ok' => true, 'daftar' => $daftar]);
    }

    /** GET /po-kegiatan/@id/review -- rincian satu PO + catatan revisi yang boleh dilihat pengguna */
    public function detail($f3, $params) {
        // ?lihat=1: baca saja lintas divisi (tanpa draf catatan, tanpa menandai dibaca). Hanya untuk Superadmin, Ketua Tim, dan Tim Kerja.
        if ((string) $f3->get('GET.lihat') === '1') {
            $this->requireAuth();
            if ($this->modeHalaman() === null) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke PO ini.'], 403); }
            $this->lihatSaja = true;
            $this->keluarkanJson($this->paketDetail((int) $params['id'], 'pembuat'));
            return;
        }
        list($po, $sisi) = $this->akses((int) $params['id']);
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /**
     * SEMENTARA (hapus setelah selesai): GET /debug-riwayat/@id (id = ID ORDER). Hanya superadmin.
     * Menampilkan isi Riwayat Aktivitas + chat alur PO order itu, struktur kolom, dan hasil uji tulis (dibatalkan lewat rollback)
     * supaya terlihat PERSIS kenapa kejadian alur PO tidak tercatat di Riwayat.
     */
    public function debugRiwayat($f3, $params) {
        $this->requireAuth();
        if ($this->getUserRole() !== 'superadmin') { $f3->error(403); return; }
        $oid = (int) $params['id'];
        $out = ['order_id' => $oid];
        $try = function ($k, $fn) use (&$out) { try { $out[$k] = $fn(); } catch (\Throwable $e) { $out[$k] = 'GALAT: ' . $e->getMessage(); } };
        $try('po', function () use ($oid) { return $this->db->exec("SELECT id, nomor_po, status, status_review, ppk_status, ppk_dibaca_at, minta_dibaca_at FROM po_kegiatan WHERE order_id = ?", [1 => $oid]); });
        $try('riwayat_terbaru', function () use ($oid) { return $this->db->exec("SELECT id, modul, aksi, deskripsi, user_id, user_nama, user_role, created_at FROM opti_activity_log WHERE order_id = ? ORDER BY id DESC LIMIT 15", [1 => $oid]); });
        $try('riwayat_modul_po_kegiatan', function () use ($oid) { return $this->db->exec("SELECT id, aksi, deskripsi, user_nama, user_role, created_at FROM opti_activity_log WHERE order_id = ? AND modul = 'po_kegiatan' ORDER BY id DESC LIMIT 15", [1 => $oid]); });
        $try('chat_alur_terbaru', function () use ($oid) { return $this->db->exec("SELECT c.id, c.sisi, c.pesan, c.sistem, c.created_at FROM po_chat c JOIN po_kegiatan p ON p.id = c.po_id WHERE p.order_id = ? ORDER BY c.id DESC LIMIT 15", [1 => $oid]); });
        $try('kolom_opti_activity_log', function () { return $this->db->exec("SHOW COLUMNS FROM opti_activity_log"); });
        $try('kolom_po_chat', function () { return $this->db->exec("SHOW COLUMNS FROM po_chat"); });
        $try('mbstring', function () { return function_exists('mb_substr') && function_exists('mb_strtoupper') ? 'ada' : 'TIDAK ADA'; });
        // uji tulis ke Riwayat persis seperti catatRiwayatOrder (lalu dibatalkan)
        $try('uji_tulis_riwayat', function () use ($oid) {
            $this->db->begin();
            try {
                $this->db->exec(
                    "INSERT INTO opti_activity_log (order_id, modul, aksi, deskripsi, user_id, user_nama, user_role, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [1 => $oid, 2 => 'po_kegiatan', 3 => 'minta_revisi', 4 => 'UJI (dibatalkan)', 5 => (int) $this->getUserId(), 6 => 'uji', 7 => (string) $this->getUserRole(), 8 => '127.0.0.1']
                );
                $this->db->rollback();
                return 'BERHASIL (dibatalkan)';
            } catch (\Throwable $e) { $this->db->rollback(); return 'GALAT: ' . $e->getMessage(); }
        });
        // uji ringkasan teks seperti yang dikirim PPK BLU
        $try('uji_ringkas', function () { return \PoReviewController::ringkasRiwayat('Nama Uji (PPK BLU) meminta revisi (2 catatan). Menunggu keputusan Ketua Tim.'); });
        $this->keluarkanJson($out);
    }

    /** SEMENTARA: GET /debug-alur/@id (id order, superadmin saja). Menampilkan isi po_alur_log + trigger untuk memeriksa rincian putaran revisi Tahap 8. Hapus setelah selesai. */
    public function debugAlur($f3, $params) {
        $this->requireAuth();
        if ($this->getUserRole() !== 'superadmin') { $f3->error(403); return; }
        $oid = (int) $params['id'];
        $out = array('order_id' => $oid);
        $try = function ($k, $fn) use (&$out) { try { $out[$k] = $fn(); } catch (\Throwable $e) { $out[$k] = 'GALAT: ' . $e->getMessage(); } };
        $try('trigger_po_kegiatan', function () { $r = $this->db->exec("SHOW TRIGGERS WHERE `Table` = 'po_kegiatan'"); $o = array(); foreach ($r as $t) { $o[] = $t['Trigger'] . ' (' . $t['Event'] . ' ' . $t['Timing'] . ', dibuat ' . (isset($t['Created']) ? $t['Created'] : '?') . ')'; } return $o ? $o : 'TIDAK ADA TRIGGER (migration_po_alur_log.sql belum dijalankan)'; });
        $try('tabel_po_alur_log', function () { $r = $this->db->exec("SHOW TABLES LIKE 'po_alur_log'"); return $r ? 'ada' : 'TIDAK ADA'; });
        $po = array();
        $try('po', function () use ($oid, &$po) { $po = $this->db->exec("SELECT * FROM po_kegiatan WHERE order_id = ?", array(1 => $oid)); $o = array(); foreach ($po as $r) { $x = array(); foreach (array('id', 'nomor_po', 'status', 'status_review', 'ppk_status', 'ronde_ppk', 'ronde_revisi', 'created_at', 'dikirim_at', 'reviewed_at', 'disetujui_at', 'tervalidasi_mitra_at', 'ppk_dibaca_at', 'ppk_minta_at', 'ppk_status_at', 'validasi_keuangan_at', 'po_ttd_at') as $k) { $x[$k] = array_key_exists($k, $r) ? $r[$k] : '(kolom tidak ada)'; } $o[] = $x; } return $o; });
        foreach ($po as $r) {
            $pid = (int) $r['id'];
            $try('log_po_' . $pid, function () use ($pid) { $l = $this->db->exec("SELECT id, kolom, nilai, waktu FROM po_alur_log WHERE po_id = ? ORDER BY waktu, id", array(1 => $pid)); $o = array(); foreach ($l as $e) { $o[] = $e['waktu'] . '  ' . $e['kolom'] . ($e['nilai'] !== null ? ' = ' . $e['nilai'] : ''); } return $o ? $o : 'KOSONG (tidak ada catatan untuk PO ini)'; });
            $try('hasil_susun_po_' . $pid, function () use ($pid, $r) { $h = \PoAlurWaktu::susun($r, \PoAlurWaktu::muat($this->db, $pid)); $o = array(); foreach ($h as $s => $w) { $o['langkah_' . $s] = 'revisi=' . ($w['revisi'] ? 'ya' : 'tidak') . ', rinci=' . ($w['rinci'] ? 'ya' : 'tidak') . ', giliran=' . count($w['giliran']); } return $o; });
        }
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * POST /po-kegiatan/@id/baca (alias /dibuka) -- dipanggil saat PO dibuka.
     * Penerima kiriman yang sedang berlaku dicatat "sudah dibaca" (sekali saja per kiriman):
     *   Ketua Tim      : PO baru (reviewed_at) atau revisi masuk (revisi_dibaca_at)
     *   Ketua Pelaksana: permintaan revisi (minta_dibaca_at)
     * Superadmin hanya melihat, tidak menandai.
     */
    public function baca($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid  = (int) $this->getUserId();
        $role = $this->getUserRole();

        if ($po['status'] === 'terkirim') {
            try {
                if ($sisi === 'katim' && $role === 'ketua_tim') {
                    if ($po['status_review'] === 'menunggu_review') {
                        $this->db->exec(
                            "UPDATE po_kegiatan SET reviewed_at = NOW(), reviewed_by = ?
                              WHERE id = ? AND status_review = 'menunggu_review' AND reviewed_at IS NULL",
                            [1 => $uid, 2 => (int) $po['id']]
                        );
                    } elseif ($po['status_review'] === 'direvisi') {
                        $this->db->exec(
                            "UPDATE po_kegiatan SET revisi_dibaca_at = NOW()
                              WHERE id = ? AND status_review = 'direvisi' AND revisi_dibaca_at IS NULL",
                            [1 => (int) $po['id']]
                        );
                    }
                } elseif ($sisi === 'pembuat' && $role === 'tim_kerja' && $po['status_review'] === 'perlu_revisi') {
                    $this->db->exec(
                        "UPDATE po_kegiatan SET minta_dibaca_at = NOW()
                          WHERE id = ? AND status_review = 'perlu_revisi' AND minta_dibaca_at IS NULL",
                        [1 => (int) $po['id']]
                    );
                }
            } catch (\Exception $e) {
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mencatat pembukaan PO. Sudah menjalankan migration_po_review_revisi.sql?'], 500);
            }
        }

        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /* ============================================================
     * API: CATATAN REVISI
     * ============================================================ */

    /** POST /po-kegiatan/@id/catatan -- Ketua Tim menambah catatan (berstatus draf). Field: bagian, teks */
    public function catatanTambah($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat menambah catatan revisi.');
        $this->wajibStatus($po, ['menunggu_review', 'direvisi'], 'Catatan hanya bisa ditambah saat PO menunggu review Ketua Tim.');

        $bagian = trim((string) $f3->get('POST.bagian'));
        $teks   = trim((string) $f3->get('POST.teks'));
        if (!preg_match('/^[a-z0-9_-]{1,40}$/', $bagian)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Bagian PO tidak valid.'], 422);
        }
        if ($teks === '') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan tidak boleh kosong.'], 422);
        }
        if (mb_strlen($teks, 'UTF-8') > self::CATATAN_MAKS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan terlalu panjang (maksimal ' . self::CATATAN_MAKS . ' karakter).'], 422);
        }

        $n = $this->hitungCatatan((int) $po['id']);
        if ($n['total'] >= self::CATATAN_BATAS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Jumlah catatan pada PO ini sudah mencapai batas ' . self::CATATAN_BATAS . '.'], 422);
        }

        try {
            $this->db->exec(
                "INSERT INTO po_revisi_catatan (po_id, bagian, teks, state, ronde, dibuat_oleh, created_at)
                 VALUES (?, ?, ?, 'draf', 0, ?, NOW())",
                [1 => (int) $po['id'], 2 => $bagian, 3 => $teks, 4 => (int) $this->getUserId()]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan catatan. Sudah menjalankan migration_po_review_revisi.sql?'], 500);
        }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /**
     * POST /po-kegiatan/@id/catatan/@nid -- aksi pada satu catatan. Field: aksi =
     *   hapus        Ketua Tim, catatan draf
     *   selesai      Ketua Pelaksana, saat perlu_revisi, catatan terbuka putaran ini -> selesai
     *   batalselesai Ketua Pelaksana, saat perlu_revisi, catatan selesai putaran ini -> terbuka
     *   buka         Ketua Tim, saat direvisi, catatan selesai putaran ini -> terbuka (dibuka kembali)
     *   batalbuka    Ketua Tim, saat direvisi, catatan terbuka putaran ini -> selesai
     */
    public function catatanAksi($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid  = (int) $this->getUserId();
        $nid  = (int) $params['nid'];
        $aksi = trim((string) $f3->get('POST.aksi'));
        // Superadmin boleh menggantikan Ketua Pelaksana yang sedang mengerjakan revisi (centang / batal centang catatan)
        if ($this->isSuperadmin() && ($aksi === 'selesai' || $aksi === 'batalselesai')) { $sisi = 'pembuat'; }

        try {
            $rows = $this->db->exec(
                "SELECT id, state, ronde FROM po_revisi_catatan WHERE id = ? AND po_id = ? AND tahap = 'review' LIMIT 1",
                [1 => $nid, 2 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca catatan.'], 500);
        }
        if (empty($rows)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan tidak ditemukan.'], 404);
        }
        $c = $rows[0];
        $ronde = (int) $po['ronde_revisi'];
        $sql = '';
        $prm = [];

        if ($aksi === 'hapus') {
            $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat menghapus draf catatan.');
            if ($c['state'] !== 'draf') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Hanya catatan draf yang bisa dihapus.'], 422); }
            $sql = "DELETE FROM po_revisi_catatan WHERE id = ? AND po_id = ? AND state = 'draf'";
            $prm = [1 => $nid, 2 => (int) $po['id']];
        } elseif ($aksi === 'selesai' || $aksi === 'batalselesai') {
            $this->wajibSisi($sisi, 'pembuat', 'Hanya Ketua Pelaksana yang dapat menandai catatan.');
            $this->wajibStatus($po, ['perlu_revisi'], 'Catatan hanya bisa ditandai saat PO berstatus Perlu revisi.');
            if ((int) $c['ronde'] !== $ronde) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini bukan dari putaran revisi yang berjalan.'], 422); }
            if ($aksi === 'selesai') {
                if ($c['state'] !== 'terbuka') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini tidak sedang terbuka.'], 422); }
                $sql = "UPDATE po_revisi_catatan SET state = 'selesai', selesai_oleh = ?, selesai_at = NOW() WHERE id = ? AND po_id = ? AND state = 'terbuka'";
                $prm = [1 => $uid, 2 => $nid, 3 => (int) $po['id']];
            } else {
                if ($c['state'] !== 'selesai') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini belum ditandai diperbaiki.'], 422); }
                $sql = "UPDATE po_revisi_catatan SET state = 'terbuka', selesai_oleh = NULL, selesai_at = NULL WHERE id = ? AND po_id = ? AND state = 'selesai'";
                $prm = [1 => $nid, 2 => (int) $po['id']];
            }
        } elseif ($aksi === 'buka' || $aksi === 'batalbuka') {
            $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat membuka kembali catatan.');
            $this->wajibStatus($po, ['direvisi'], 'Catatan hanya bisa dibuka kembali saat PO berstatus Sudah direvisi.');
            if ((int) $c['ronde'] !== $ronde) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini bukan dari putaran revisi yang berjalan.'], 422); }
            if ($aksi === 'buka') {
                if ($c['state'] !== 'selesai') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini tidak berstatus sudah diperbaiki.'], 422); }
                $sql = "UPDATE po_revisi_catatan SET state = 'terbuka' WHERE id = ? AND po_id = ? AND state = 'selesai'";
            } else {
                if ($c['state'] !== 'terbuka') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini tidak sedang dibuka kembali.'], 422); }
                $sql = "UPDATE po_revisi_catatan SET state = 'selesai' WHERE id = ? AND po_id = ? AND state = 'terbuka'";
            }
            $prm = [1 => $nid, 2 => (int) $po['id']];
        } else {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Aksi tidak dikenal.'], 422);
        }

        try {
            $diubah = $this->db->exec($sql, $prm);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memperbarui catatan.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan sudah berubah. Muat ulang halaman.'], 409);
        }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /* ============================================================
     * API: ALUR REVIEW
     * ============================================================ */

    /** POST /po-kegiatan/@id/minta-revisi -- Ketua Tim mengirim semua catatan (draf + yang dibuka kembali) ke pelaksana */
    public function mintaRevisi($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat meminta revisi.');
        $this->wajibStatus($po, ['menunggu_review', 'direvisi'], 'Revisi hanya bisa diminta saat PO menunggu review.');

        $n = $this->hitungCatatan((int) $po['id']);
        $jumlah = $n['draf'] + $n['terbuka'];
        if ($jumlah < 1) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Belum ada catatan untuk dikirim. Tambahkan catatan dulu.'], 422);
        }

        $ronde = (int) $po['ronde_revisi'] + 1;
        try {
            $this->db->begin();
            $this->db->exec(
                "UPDATE po_revisi_catatan SET state = 'terbuka', ronde = ? WHERE po_id = ? AND tahap = 'review' AND state IN ('draf', 'terbuka')" . $this->sqlSembunyiPjt(),
                [1 => $ronde, 2 => (int) $po['id']]
            );
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan
                    SET status_review = 'perlu_revisi', ronde_revisi = ?, minta_at = NOW(), minta_dibaca_at = NULL,
                        reviewed_at = COALESCE(reviewed_at, NOW()), reviewed_by = COALESCE(reviewed_by, ?)
                  WHERE id = ? AND status_review IN ('menunggu_review', 'direvisi')",
                [1 => $ronde, 2 => $uid, 3 => (int) $po['id']]
            );
            if ((int) $diubah === 0) {
                $this->db->rollback();
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
            }
            if ($this->kolomAda('pjt_status')) {   // masukan PJT yang ikut dikirim dianggap sudah ditinjau Ketua Tim
                $this->db->exec("UPDATE po_kegiatan SET pjt_status = 'ditinjau' WHERE id = ? AND pjt_status = 'masukan'", [1 => (int) $po['id']]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengirim permintaan revisi.'], 500);
        }

        $nama = $this->namaPengguna($uid, 'Ketua Tim');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' meminta revisi (' . $jumlah . ' catatan).');
        $this->perbaruiBaseline((int) $po['id'], 'katim');
        self::kabari($this->db, [(int) $po['ketua_pelaksana_id']], 'Permintaan revisi PO', 'PO ' . $po['nomor_po'] . ': ' . $jumlah . ' catatan revisi dari Ketua Tim.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-kegiatan/@id/kirim-revisi -- Ketua Pelaksana mengirim revisi (semua catatan putaran ini sudah ditandai) */
    public function kirimRevisi($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        if ($this->isSuperadmin()) { $sisi = 'pembuat'; }   // superadmin boleh mengirim revisi menggantikan Ketua Pelaksana
        $this->wajibSisi($sisi, 'pembuat', 'Hanya Ketua Pelaksana yang dapat mengirim revisi.');
        $this->wajibStatus($po, ['perlu_revisi'], 'Revisi hanya bisa dikirim saat PO berstatus Perlu revisi.');

        $n = $this->hitungCatatan((int) $po['id']);
        if ($n['terbuka'] > 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Masih ada ' . $n['terbuka'] . ' catatan yang belum ditandai diperbaiki.'], 422);
        }

        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET status_review = 'direvisi', revisi_at = NOW(), revisi_dibaca_at = NULL
                  WHERE id = ? AND status_review = 'perlu_revisi'",
                [1 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengirim revisi.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
        }

        $nama = $this->namaPengguna($uid, 'Ketua Pelaksana');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'pembuat', $nama . ' mengirim revisi. Menunggu review ulang.');
        $this->perbaruiBaseline((int) $po['id'], 'pembuat');
        // PJT yang sudah memberi masukan (lalu ditinjau Ketua Tim) tetap dilibatkan: revisi yang dikirim ulang kembali menunggu pandangannya (Setujui terkunci lagi sampai PJT selesai atau ditarik).
        // PJT yang sudah ACC dianggap selesai: tidak diminta meninjau lagi.
        // Pembanding tanda merah PJT tidak diperbarui, jadi PJT melihat apa saja yang berubah sejak terakhir ia bertindak.
        if ($this->kolomAda('pjt_status')) {
            try {
                $cekP = $this->db->exec("SELECT pjt_id FROM po_kegiatan WHERE id = ? AND pjt_id IS NOT NULL AND pjt_status = 'ditinjau' LIMIT 1", [1 => (int) $po['id']]);
                if (!empty($cekP)) {
                    $balik = $this->db->exec("UPDATE po_kegiatan SET pjt_status = 'menunggu', pjt_at = NOW(), pjt_catatan = NULL, pjt_selesai_at = NULL WHERE id = ? AND pjt_id = ? AND pjt_status = 'ditinjau'", [1 => (int) $po['id'], 2 => (int) $cekP[0]['pjt_id']]);
                    if ((int) $balik > 0) {
                        self::catatPesanSistem($this->db, $po['id'], $uid, 'pembuat', 'PO hasil revisi kembali menunggu pandangan PJT Lingkungan.');
                        self::kabari($this->db, [(int) $cekP[0]['pjt_id']], 'PO menunggu review PJT', 'PO ' . $po['nomor_po'] . ' sudah direvisi dan menunggu pandangan Anda sebagai PJT Lingkungan.', '/po-kegiatan?tab=tindakan');
                    }
                }
            } catch (\Throwable $ePjt) { /* pelengkap: Ketua Tim tetap bisa melibatkan PJT secara manual */ }
        }
        self::kabari($this->db, self::idKatim($this->db, $po['jenis_layanan_opti']), 'Revisi PO masuk', 'PO ' . $po['nomor_po'] . ' sudah direvisi dan menunggu review ulang.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-kegiatan/@id/setujui -- Ketua Tim menyetujui PO. PO dikunci dan diteruskan ke Tim Mitra dan Humas. */
    public function setujui($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat menyetujui PO.');
        $this->wajibStatus($po, ['menunggu_review', 'direvisi'], 'PO hanya bisa disetujui saat menunggu review.');
        try {
            $dl = $this->db->exec("SELECT COUNT(*) AS c FROM tb_surat_penawaran WHERE order_id = ? AND status_respon_klien = 'deal'", [1 => (int) $po['order_id']]);
            $adaDeal = !empty($dl) && (int) $dl[0]['c'] > 0;
        } catch (\Throwable $eDl) { $adaDeal = false; }
        if (!$adaDeal) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak bisa disetujui: Surat Penawaran order ini belum berstatus DEAL (disetujui klien).'], 422);
        }
        if ($this->kolomAda('pjt_status')) {
            $cekPjt = $this->db->exec("SELECT pjt_status FROM po_kegiatan WHERE id = ?", [1 => (int) $po['id']]);
            if (!empty($cekPjt) && in_array((string) $cekPjt[0]['pjt_status'], ['menunggu', 'masukan'], true)) {
                $this->keluarkanJson(['ok' => false, 'pesan' => 'PO masih menunggu review PJT atau masukan PJT belum ditinjau. Selesaikan itu dulu, atau tarik PJT.'], 422);
            }
        }

        $n = $this->hitungCatatan((int) $po['id']);
        $drafDibuang = 0;
        if ($n['draf'] > 0) {
            // Katim berubah pikiran: catatan draf (belum dikirim) boleh dibuang saat menyetujui, tapi hanya bila sudah dikonfirmasi di layar (buang_draf=1)
            if ((string) $f3->get('POST.buang_draf') !== '1') {
                $this->keluarkanJson(['ok' => false, 'perlu_konfirmasi_draf' => (int) $n['draf'], 'pesan' => 'Ada ' . $n['draf'] . ' catatan draf yang belum dikirim. Konfirmasi dulu bahwa catatan draf boleh dibuang, atau kirim lewat Minta revisi.'], 422);
            }
            $drafDibuang = (int) $n['draf'];
        }
        if ($n['terbuka'] > 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Masih ada ' . $n['terbuka'] . ' catatan yang belum diperbaiki.'], 422);
        }

        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan
                    SET status_review = 'disetujui', disetujui_at = NOW(), disetujui_by = ?, disposisi_humas_at = NOW(),
                        verifikasi_humas_at = NOW(),
                        reviewed_at = COALESCE(reviewed_at, NOW()), reviewed_by = COALESCE(reviewed_by, ?)
                  WHERE id = ? AND status_review IN ('menunggu_review', 'direvisi')",
                [1 => $uid, 2 => $uid, 3 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan persetujuan.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
        }
        if ($drafDibuang > 0) {   // setelah persetujuan tersimpan: buang catatan draf yang belum dikirim
            try { $this->db->exec("DELETE FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review' AND state = 'draf'" . $this->sqlSembunyiPjt(), [1 => (int) $po['id']]); }
            catch (\Throwable $eDr) { error_log('[po_review] gagal membuang catatan draf saat setujui: ' . $eDr->getMessage()); }
        }
        // Bekukan gambar TTD Ketua Tim untuk PO ini (dokumen lama tidak ikut berubah bila TTD akun diganti / pejabat berganti).
        try { if (class_exists('PoTtdSnapshot')) { \PoTtdSnapshot::bekukan($this->db, (int) $po['id'], 'ketua'); } } catch (\Throwable $eSnap) { error_log('[PoTtdSnapshot] ' . $eSnap->getMessage()); }

        $nama = $this->namaPengguna($uid, 'Ketua Tim');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' menyetujui PO' . ($drafDibuang > 0 ? ' (' . $drafDibuang . ' catatan draf dibuang)' : '') . '. PO terkunci dan langsung diteruskan ke Tim Mitra (verifikasi). Humas membukanya di menu PKS setelah Tim Mitra memverifikasi.');
        self::kabari($this->db, [(int) $po['ketua_pelaksana_id']], 'PO disetujui', 'PO ' . $po['nomor_po'] . ' disetujui Ketua Tim.');
        // PO yang disetujui Ketua Tim langsung masuk ke Tim Mitra (verifikasi). Humas baru menerimanya di menu PKS setelah Tim Mitra memverifikasi (tidak ada lagi Daftar PO Humas)
        foreach ([
            ['ketua_tim_mitra', 'PO Menunggu Verifikasi', 'PO ' . $po['nomor_po'] . ' sudah disetujui Ketua Tim, menunggu verifikasi Tim Mitra.', '/po-kegiatan/validasi-mitra'],
        ] as $n) {
            try {
                if (class_exists('NotificationService')) {
                    \NotificationService::send($this->db, [
                        'order_id'        => (int) $po['order_id'],
                        'po_id'           => (int) $po['id'],
                        'target_role'     => $n[0],
                        'target_layanan'  => 'semua',
                        'judul'           => $n[1],
                        'pesan'           => $n[2],
                        'tipe'            => 'primary',
                        'icon'            => 'bi-clipboard-check',
                        'link_url'        => $n[3],
                        'created_by'      => $uid,
                        'created_by_name' => $nama,
                    ]);
                }
            } catch (\Throwable $eNotif) {
                // notifikasi bersifat pelengkap
            }
        }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** Daftar PJT Lingkungan yang boleh dilibatkan Ketua Tim: [['id' => int, 'nama' => string], ...] */
    /** PJT hanya ada di layanan Lingkungan: apakah order PO ini layanan Lingkungan? */
    protected function poLingkungan($poId) {
        try {
            $x = $this->db->exec("SELECT o.jenis_layanan_opti AS j FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id WHERE p.id = ? LIMIT 1", [1 => (int) $poId]);
            return !empty($x) && (string) $x[0]['j'] === 'lingkungan';
        } catch (\Throwable $e) { return false; }
    }

    protected function opsiPjt() {
        $peta = [];   // id_user => nama
        // Sumber utama: role di tb_arsipuser.si_opti (multi-role dipisah koma), sama dengan yang dipakai saat login
        try {
            $rows = $this->dbSekretariat->exec(
                "SELECT id_user, nama_user FROM tb_arsipuser
                  WHERE FIND_IN_SET('pjt_lingkungan', REPLACE(REPLACE(COALESCE(si_opti, ''), ', ', ','), ' ,', ',')) > 0
                     OR FIND_IN_SET('penanggung_jawab_teknis_lingkungan', REPLACE(REPLACE(COALESCE(si_opti, ''), ', ', ','), ' ,', ',')) > 0
                  ORDER BY nama_user ASC"
            );
            foreach ($rows as $r) { $peta[(int) $r['id_user']] = (string) $r['nama_user']; }
        } catch (\Exception $e) {
            // lanjut ke sumber cadangan
        }
        // Cadangan: pemetaan role di opti_user_map
        try {
            $rows = $this->db->exec("SELECT DISTINCT id_user FROM opti_user_map
                                      WHERE (role = 'pjt_lingkungan' OR (role = 'pjt' AND jenis_layanan = 'lingkungan'))
                                        AND is_active = 1 AND status_tampil = 'show'");
            $baru = [];
            foreach ($rows as $r) { if (!isset($peta[(int) $r['id_user']])) { $baru[] = (int) $r['id_user']; } }
            if (!empty($baru)) {
                $nm = $this->petaNamaUser($baru);
                foreach ($baru as $id) { $peta[$id] = isset($nm[$id]) ? $nm[$id] : ('PJT #' . $id); }
            }
        } catch (\Exception $e) {
            // daftar PJT mungkin kosong: pilihan Libatkan PJT tidak tampil
        }
        $out = [];
        foreach ($peta as $id => $nama) { $out[] = ['id' => (int) $id, 'nama' => $nama]; }
        return $out;
    }

    /** POST /po-kegiatan/@id/libatkan-pjt -- Ketua Tim melibatkan PJT Lingkungan untuk memberi pandangan; Setujui terkunci sampai PJT selesai. */
    public function libatkanPjt($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat melibatkan PJT.');
        $this->wajibStatus($po, ['menunggu_review', 'direvisi'], 'PJT hanya bisa dilibatkan saat PO menunggu review Ketua Tim.');
        if (!$this->poLingkungan((int) $po['id'])) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PJT hanya untuk PO layanan Lingkungan.'], 422);
        }
        if (!$this->kolomAda('pjt_status')) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom pjt_status belum ada. Jalankan migration_po_pjt.sql dulu.'], 500);
        }
        $pjtId = (int) $f3->get('POST.pjt_id');
        $namaPjt = '';
        foreach ($this->opsiPjt() as $o) {
            if ($o['id'] === $pjtId) { $namaPjt = $o['nama']; break; }
        }
        if ($pjtId <= 0 || $namaPjt === '') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PJT yang dipilih tidak valid.'], 422);
        }
        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET pjt_status = 'menunggu', pjt_id = ?, pjt_at = NOW(), pjt_catatan = NULL, pjt_selesai_at = NULL
                  WHERE id = ? AND status = 'terkirim' AND status_review IN ('menunggu_review', 'direvisi')
                    AND COALESCE(pjt_status, '') NOT IN ('menunggu', 'masukan')",
                [1 => $pjtId, 2 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal melibatkan PJT.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PJT sudah dilibatkan atau status PO berubah. Muat ulang halaman.'], 409);
        }
        $nama = $this->namaPengguna($uid, 'Ketua Tim');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' melibatkan PJT Lingkungan (' . $namaPjt . ') untuk memberi pandangan atas PO ini. Persetujuan menunggu PJT.');
        $this->perbaruiBaseline((int) $po['id'], 'pjt');   // PJT mulai dari isi dokumen saat ini
        self::kabari($this->db, [$pjtId], 'PO menunggu review PJT', 'PO ' . $po['nomor_po'] . ' menunggu pandangan Anda sebagai PJT Lingkungan.', '/po-kegiatan?tab=tindakan');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-kegiatan/@id/tarik-pjt -- Ketua Tim menarik PJT dari PO ini (tidak jadi dilibatkan); Setujui terbuka lagi. */
    public function tarikPjt($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat menarik PJT.');
        if (!$this->kolomAda('pjt_status')) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom pjt_status belum ada. Jalankan migration_po_pjt.sql dulu.'], 500);
        }
        if ($this->adaDariPjt()) {   // catatan PJT yang belum diteruskan ikut dibuang
            try { $this->db->exec("DELETE FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review' AND dari_pjt = 1 AND state = 'draf'", [1 => (int) $po['id']]); } catch (\Exception $e) {}
        }
        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET pjt_status = NULL, pjt_id = NULL, pjt_at = NULL, pjt_catatan = NULL, pjt_selesai_at = NULL WHERE id = ? AND pjt_status IN ('menunggu', 'masukan')",
                [1 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menarik PJT.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PJT tidak sedang dilibatkan atau sudah selesai. Muat ulang halaman.'], 409);
        }
        $nama = $this->namaPengguna($uid, 'Ketua Tim');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' menarik PJT dari PO ini. Persetujuan kembali ke Ketua Tim.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** Apakah tabel po_revisi_catatan sudah punya kolom dari_pjt (migration tahap 3)? */
    protected function adaDariPjt() {
        static $ada = null;
        if ($ada === null) {
            try {
                $r = $this->db->exec("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'po_revisi_catatan' AND COLUMN_NAME = 'dari_pjt'");
                $ada = !empty($r) && (int) $r[0]['c'] > 0;
            } catch (\Exception $e) { $ada = false; }
        }
        return $ada;
    }

    /** Potongan SQL: sembunyikan draf catatan PJT dari Ketua Tim selama PJT belum menekan Kirim masukan. */
    protected function sqlSembunyiPjt() {
        if (!$this->adaDariPjt()) { return ''; }
        return " AND (dari_pjt = 0 OR state <> 'draf' OR COALESCE((SELECT p2.pjt_status FROM po_kegiatan p2 WHERE p2.id = po_revisi_catatan.po_id), '') <> 'menunggu')";
    }

    /** Jumlah draf catatan PJT (milik PJT yang login) pada PO ini. */
    protected function jumlahDrafPjt($poId, $uid) {
        if (!$this->adaDariPjt()) { return 0; }
        try {
            $r = $this->db->exec("SELECT COUNT(*) AS c FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review' AND dari_pjt = 1 AND state = 'draf' AND dibuat_oleh = ?", [1 => (int) $poId, 2 => (int) $uid]);
            return !empty($r) ? (int) $r[0]['c'] : 0;
        } catch (\Exception $e) { return 0; }
    }

    /** POST /po-kegiatan/@id/pjt-catatan -- PJT menambah catatan per bagian (draf, hanya terlihat PJT). Field: bagian, teks */
    public function pjtCatatanTambah($f3, $params) {
        $po = $this->aksesPjt((int) $params['id']);
        $uid = (int) $this->getUserId();
        if (!$this->adaDariPjt()) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom dari_pjt belum ada. Jalankan migration_po_pjt.sql dulu.'], 500);
        }
        $bagian = trim((string) $f3->get('POST.bagian'));
        $teks   = trim((string) $f3->get('POST.teks'));
        if (!preg_match('/^[a-z0-9_-]{1,40}$/', $bagian)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Bagian PO tidak valid.'], 422);
        }
        if ($teks === '') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan tidak boleh kosong.'], 422);
        }
        if (mb_strlen($teks, 'UTF-8') > self::CATATAN_MAKS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan terlalu panjang (maksimal ' . self::CATATAN_MAKS . ' karakter).'], 422);
        }
        $n = $this->hitungCatatan((int) $po['id']);
        if ($n['total'] >= self::CATATAN_BATAS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Jumlah catatan pada PO ini sudah mencapai batas ' . self::CATATAN_BATAS . '.'], 422);
        }
        try {
            $this->db->exec(
                "INSERT INTO po_revisi_catatan (po_id, bagian, teks, state, ronde, dibuat_oleh, created_at, dari_pjt)
                 VALUES (?, ?, ?, 'draf', 0, ?, NOW(), 1)",
                [1 => (int) $po['id'], 2 => $bagian, 3 => $teks, 4 => $uid]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan catatan.'], 500);
        }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'pjt'));
    }

    /** POST /po-kegiatan/@id/pjt-catatan/@nid -- PJT menghapus draf catatannya sendiri. */
    public function pjtCatatanHapus($f3, $params) {
        $po = $this->aksesPjt((int) $params['id']);
        $uid = (int) $this->getUserId();
        if (!$this->adaDariPjt()) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom dari_pjt belum ada. Jalankan migration_po_pjt.sql dulu.'], 500);
        }
        try {
            $diubah = $this->db->exec(
                "DELETE FROM po_revisi_catatan WHERE id = ? AND po_id = ? AND tahap = 'review' AND dari_pjt = 1 AND state = 'draf' AND dibuat_oleh = ?",
                [1 => (int) $params['nid'], 2 => (int) $po['id'], 3 => $uid]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menghapus catatan.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan tidak ditemukan atau sudah dikirim. Muat ulang halaman.'], 409);
        }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'pjt'));
    }

    /** Akses PJT untuk menanggapi PO yang melibatkannya. Mengembalikan baris PO, atau langsung menjawab JSON error. */
    protected function aksesPjt($poId) {
        $this->requireAuth();
        if (!$this->isPjtLingkungan()) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Hanya PJT Lingkungan yang dapat menanggapi review ini.'], 403);
        }
        if (!$this->kolomAda('pjt_status') || !$this->kolomAda('pjt_catatan')) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom PJT belum lengkap. Jalankan migration_po_pjt.sql dulu.'], 500);
        }
        try {
            $r = $this->db->exec(
                "SELECT p.id, p.nomor_po, p.status, p.status_review, p.pjt_status, p.pjt_id, o.id AS order_id, o.jenis_layanan_opti
                   FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id WHERE p.id = ? LIMIT 1",
                [1 => (int) $poId]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca data PO.'], 500);
        }
        if (empty($r)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan.'], 404); }
        $po = $r[0];
        if ((int) $po['pjt_id'] !== (int) $this->getUserId()) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini tidak melibatkan Anda sebagai PJT.'], 403);
        }
        if ($po['status'] !== 'terkirim' || !in_array($po['status_review'], ['menunggu_review', 'direvisi'], true)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak sedang menunggu review.'], 422);
        }
        if ((string) $po['pjt_status'] !== 'menunggu') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini sudah Anda tanggapi atau PJT ditarik Ketua Tim. Muat ulang halaman.'], 409);
        }
        return $po;
    }

    /** Beri tahu Ketua Tim divisi Lingkungan bahwa PJT sudah menanggapi. */
    protected function kabariKatimLingkungan(array $po, $judul, $pesan) {
        $katim = $this->katimPerDivisi();
        $idk = isset($katim['lingkungan']) ? (int) $katim['lingkungan'] : 0;
        if ($idk > 0) { self::kabari($this->db, [$idk], $judul, $pesan, '/po-kegiatan/daftar'); }
    }

    /** POST /po-kegiatan/@id/pjt-acc -- PJT menyatakan PO sudah sesuai; Ketua Tim boleh memberi persetujuan akhir. */
    public function pjtAcc($f3, $params) {
        $po = $this->aksesPjt((int) $params['id']);
        $uid = (int) $this->getUserId();
        if ($this->jumlahDrafPjt((int) $po['id'], $uid) > 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda masih punya catatan yang belum dikirim. Kirim masukan, atau hapus catatannya dulu sebelum ACC.'], 422);
        }
        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET pjt_status = 'acc', pjt_selesai_at = NOW() WHERE id = ? AND pjt_status = 'menunggu' AND pjt_id = ?",
                [1 => (int) $po['id'], 2 => $uid]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan ACC.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
        }
        $nama = $this->namaPengguna($uid, 'PJT');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', 'PJT Lingkungan (' . $nama . ') memberi ACC atas PO ini. Ketua Tim dapat memberi persetujuan akhir.');
        $this->perbaruiBaseline((int) $po['id'], 'pjt');
        $this->kabariKatimLingkungan($po, 'PJT sudah ACC', 'PO ' . $po['nomor_po'] . ' sudah di-ACC PJT. Menunggu persetujuan akhir Anda.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'pjt'));
    }

    /** POST /po-kegiatan/@id/pjt-masukan -- PJT mengirim catatan-catatannya ke Ketua Tim (belum ke Ketua Pelaksana). */
    public function pjtMasukan($f3, $params) {
        $po = $this->aksesPjt((int) $params['id']);
        $uid = (int) $this->getUserId();
        $n = $this->jumlahDrafPjt((int) $po['id'], $uid);
        if ($n < 1) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Belum ada catatan. Tekan + Catatan di bagian yang perlu diperbaiki dulu.'], 422);
        }
        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET pjt_status = 'masukan', pjt_selesai_at = NOW() WHERE id = ? AND pjt_status = 'menunggu' AND pjt_id = ?",
                [1 => (int) $po['id'], 2 => $uid]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengirim masukan.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
        }
        $nama = $this->namaPengguna($uid, 'PJT');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', 'PJT Lingkungan (' . $nama . ') mengirim ' . $n . ' catatan ke Ketua Tim. Ketua Tim memutuskan catatan mana yang diteruskan.');
        $this->perbaruiBaseline((int) $po['id'], 'pjt');
        $this->kabariKatimLingkungan($po, 'Masukan dari PJT', 'PJT memberi ' . $n . ' catatan atas PO ' . $po['nomor_po'] . '. Mohon ditinjau.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'pjt'));
    }

    /** POST /po-kegiatan/@id/pjt-tinjau -- Ketua Tim menandai masukan PJT sudah ditinjau; Setujui terbuka lagi. */
    public function tinjauPjt($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat meninjau masukan PJT.');
        if (!$this->kolomAda('pjt_status')) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom PJT belum ada. Jalankan migration_po_pjt.sql dulu.'], 500);
        }
        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET pjt_status = 'ditinjau' WHERE id = ? AND pjt_status = 'masukan'",
                [1 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Tidak ada masukan PJT yang menunggu ditinjau. Muat ulang halaman.'], 409);
        }
        $nama = $this->namaPengguna($uid, 'Ketua Tim');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' sudah meninjau masukan PJT.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /* ============================================================
     * TANDA PERUBAHAN (merah): salinan dokumen PO terakhir yang ditinjau tiap pihak
     * ============================================================ */

    /** Simpan salinan HTML dokumen untuk satu pihak. $timpa = true menimpa, false hanya mengisi bila belum ada. */
    public static function simpanBaseline($db, $poId, $sisi, $html, $timpa) {
        if ((string) $html === '' || !in_array($sisi, ['katim', 'pembuat', 'pjt', 'ppk'], true)) { return; }   // 'ppk' = pembanding slide review PPK BLU (diisi saat PPK BLU minta revisi / ajuan perbaikan dimulai)
        try {
            if ($timpa) {
                $db->exec("INSERT INTO po_baseline (po_id, sisi, isi, diperbarui_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE isi = VALUES(isi), diperbarui_at = NOW()",
                    [1 => (int) $poId, 2 => $sisi, 3 => (string) $html]);
            } else {
                $db->exec("INSERT IGNORE INTO po_baseline (po_id, sisi, isi, diperbarui_at) VALUES (?, ?, ?, NOW())",
                    [1 => (int) $poId, 2 => $sisi, 3 => (string) $html]);
            }
        } catch (\Throwable $e) { error_log('[po_baseline] gagal menyimpan pembanding (apakah tabel po_baseline sudah dibuat?): ' . $e->getMessage()); }
    }

    /** Dipanggil saat PO disimpan: setiap pihak (termasuk penyunting) dapat pembanding = isi sebelum diedit (bila belum punya). */
    public static function catatBaselineEdit($db, $poId, $sisiEditor, $htmlSebelum, $htmlSesudah) {
        foreach (['katim', 'pembuat', 'pjt'] as $s) {
            // Semua pihak (termasuk penyunting) mendapat pembanding = isi sebelum diedit bila belum punya, jadi hasil edit tampil merah
            // untuk penyunting juga sampai ia bertindak berikutnya (minta revisi / kirim revisi / ACC / setujui) dan pembandingnya diperbarui.
            self::simpanBaseline($db, $poId, $s, $htmlSebelum, false);
        }
    }

    /** Pembanding satu pihak = isi dokumen sekarang (dipanggil setelah pihak itu bertindak: minta revisi, kirim revisi, ACC, masukan, libatkan PJT). */
    protected function perbaruiBaseline($poId, $sisi) {
        try {
            $k = new \PoKegiatanController();
            self::simpanBaseline($this->db, (int) $poId, $sisi, $k->htmlDokumenPo((int) $poId), true);
        } catch (\Throwable $e) { /* pelengkap */ }
    }

    /** True bila PO sudah final dari sisi review: order selesai, atau disetujui Ketua Tim dan tidak sedang dalam revisi PPK BLU. */
    protected function poSelesaiDitinjau(array $po) {
        if (strtolower(trim((string) ($po['order_status'] ?? ''))) === 'selesai') { return true; }
        if (strtolower(trim((string) ($po['status_review'] ?? ''))) !== 'disetujui') { return false; }
        if (!$this->kolomAda('ppk_status')) { return true; }
        try {
            $r = $this->db->exec("SELECT ppk_status FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $po['id']]);
            $ppk = !empty($r) ? (string) ($r[0]['ppk_status'] ?? '') : '';
            return !in_array($ppk, ['minta', 'katim_kerja', 'ketupel_kerja', 'katim_review'], true);
        } catch (\Throwable $e) { return true; }
    }

    /** GET /po-kegiatan/@id/baseline -- salinan dokumen PO terakhir yang ditinjau pengguna ini ('' bila belum ada). */
    public function baseline($f3, $params) {
        list($po, $sisi) = $this->aksesBaca((int) $params['id']);
        $s = $this->sisiPengguna($po);
        $html = '';
        // Tanda merah "berubah sejak terakhir Anda tinjau" hanya berguna selama PO masih dalam proses review.
        // PO yang sudah disetujui Ketua Tim (dan tidak sedang direvisi PPK BLU) atau yang ordernya sudah selesai: tidak ada pembanding, banner & teks merah tidak tampil.
        if ($s !== null && !$this->poSelesaiDitinjau($po)) {
            try {
                $r = $this->db->exec("SELECT isi FROM po_baseline WHERE po_id = ? AND sisi = ? LIMIT 1", [1 => (int) $po['id'], 2 => $s]);
                if (!empty($r)) { $html = (string) $r[0]['isi']; }
            } catch (\Throwable $e) { $html = ''; error_log('[po_baseline] gagal membaca pembanding: ' . $e->getMessage()); }
        }
        $this->keluarkanJson(['ok' => true, 'html' => $html]);
    }

    /** POST /po-kegiatan/@id/buka-kembali -- Ketua Tim membatalkan persetujuan; PO kembali ke antrean review. */
    public function bukaKembali($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat membuka kembali PO.');
        $this->wajibStatus($po, ['disetujui'], 'PO ini belum berstatus disetujui.');

        $orderId = (int) ($po['order_id'] ?? 0);
        if (strtolower(trim((string) ($po['order_status'] ?? ''))) === 'selesai' && !$this->canEditSubmittedData(true, $orderId)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Order sudah selesai dan dikunci. Hubungi Superadmin untuk membuka izin edit.'], 422);
        }
        if ($this->kolomAda('validasi_keuangan_at')) {
            $cekVal = [];
            try {
                $cekVal = $this->db->exec("SELECT validasi_keuangan_at FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $po['id']]);
            } catch (\Throwable $e) {
                $cekVal = [];
            }
            if (!empty($cekVal[0]['validasi_keuangan_at']) && !$this->canEditSubmittedData(true, $orderId)) {
                $this->keluarkanJson(['ok' => false, 'pesan' => 'PO sudah disetujui PPK BLU dan dikunci. Hubungi Superadmin untuk membuka kembali.'], 422);
            }
        }

        try {
            $setReset = "status = 'terkirim', status_review = 'menunggu_review', disetujui_at = NULL, disetujui_by = NULL, disposisi_humas_at = NULL,
                         reviewed_at = NOW(), reviewed_by = ?";
            foreach (['verifikasi_humas_at', 'verifikasi_humas_by', 'tervalidasi_mitra_at', 'tervalidasi_mitra_by', 'humas_dibaca_at', 'mitra_dibaca_at'] as $kolReset) {
                if ($this->kolomAda($kolReset)) {
                    $setReset .= ", {$kolReset} = NULL";
                }
            }
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan
                    SET {$setReset}
                  WHERE id = ? AND status_review = 'disetujui'",
                [1 => $uid, 2 => (int) $po['id']]
            );
            // validasi Tim Mitra ikut dibatalkan (nama kolom tergantung migration yang dipakai)
            if ((int) $diubah > 0) {
                foreach (['tervalidasi_keuangan', 'validasi_keuangan'] as $dasar) {
                    if ($this->kolomAda($dasar . '_at') && $this->kolomAda($dasar . '_by')) {
                        $this->db->exec(
                            "UPDATE po_kegiatan SET " . $dasar . "_at = NULL, " . $dasar . "_by = NULL WHERE id = ?",
                            [1 => (int) $po['id']]
                        );
                    }
                }
                // [BARU] Revisi PPK BLU (kalau ada) ikut dibatalkan
                if ($this->kolomAda('ppk_status')) {
                    $this->db->exec(
                        "UPDATE po_kegiatan SET ppk_status = NULL, ronde_ppk = 0, ppk_minta_at = NULL, ppk_status_at = NULL, ppk_alasan = NULL, ppk_dibaca_at = NULL WHERE id = ?",
                        [1 => (int) $po['id']]
                    );
                    $this->db->exec("DELETE FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'ppk'", [1 => (int) $po['id']]);
                    $this->db->exec("DELETE FROM po_ppk_perubahan WHERE po_id = ?", [1 => (int) $po['id']]);
                }
            }
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membuka kembali PO.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini belum berstatus disetujui.'], 422);
        }

        $nama = $this->namaPengguna($uid, 'Ketua Tim');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' membuka kembali PO untuk direview. Persetujuan dan verifikasi dibatalkan.');
        self::kabari($this->db, [(int) $po['ketua_pelaksana_id']], 'PO dibuka kembali', 'PO ' . $po['nomor_po'] . ' dibuka kembali oleh Ketua Tim.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /* ============================================================
     * API: CHAT (diskusi bebas per PO)
     * ============================================================ */

    /** GET /po-kegiatan/@id/chat -- seluruh pesan pada satu PO */
    public function chatList($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();

        try {
            $rows = $this->db->exec(
                "SELECT id, pengirim_id, sisi, pesan, sistem, created_at FROM po_chat WHERE po_id = ? ORDER BY id ASC",
                [1 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat chat. Pastikan semua file migration sudah dijalankan.'], 500);
        }

        $ids = [];
        foreach ($rows as $r) { $ids[] = $r['pengirim_id']; }
        $peta = $this->petaNamaUser($ids);

        $pesan = [];
        foreach ($rows as $r) { $pesan[] = $this->bentukPesan($r, $peta, $uid); }

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
                "INSERT INTO po_chat (po_id, pengirim_id, sisi, pesan, sistem, created_at) VALUES (?, ?, ?, ?, 0, NOW())",
                [1 => (int) $po['id'], 2 => $uid, 3 => $sisi, 4 => $teks]
            );
            $newId = (int) $this->db->lastInsertId();
            $baris = $this->db->exec(
                "SELECT id, pengirim_id, sisi, pesan, sistem, created_at FROM po_chat WHERE id = ?",
                [1 => $newId]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengirim pesan.'], 500);
        }

        $nama = $this->namaPengguna($uid, ($sisi === 'katim') ? 'Ketua Tim' : 'Ketua Pelaksana');
        $tujuan = ($sisi === 'katim') ? [(int) $po['ketua_pelaksana_id']] : self::idKatim($this->db, $po['jenis_layanan_opti']);
        self::kabari($this->db, $tujuan, 'Pesan baru di PO ' . $po['nomor_po'], $nama . ': ' . mb_substr($teks, 0, 90, 'UTF-8'), '/po-kegiatan/daftar', false);   // pesan chat: push saja, tanpa lonceng (terlalu ramai)

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

    /* ============================================================
     * API: BERKAS (tab Dokumen: surat kesanggupan bayar dan bukti pembayaran)
     * ============================================================ */

    /**
     * Berkas order untuk tab Berkas pada slide (Surat Masuk Permohonan, Proposal Riset / Teknis, Surat Penawaran Harga, Surat Kesanggupan Bayar).
     * Isi dan keterangannya disamakan dengan modal berkas di detail order (BerkasController). Alamat file memakai route yang hanya butuh
     * login / izin lihat surat (bukan /pembayaran/bukti yang khusus Keuangan). $skbUrl = alamat file Surat Kesanggupan Bayar milik halaman ini.
     */
    protected function dokumenOrder($orderId, $skbUrl = null) {
        $base = (string) $this->f3->get('BASE');
        $orderId = (int) $orderId;
        $baru = function ($kunci, $label, $ikon, $warna, $sub, $kosong = 'Belum Ada') {
            return ['kunci' => $kunci, 'label' => $label, 'ikon' => $ikon, 'warna' => $warna, 'sub' => $sub, 'ket' => '', 'ada' => false, 'url' => null,
                    'badge' => '', 'kosong' => $kosong, 'nomor' => '', 'tanggal' => '', 'pengirim' => '', 'nominal' => 0,
                    'status_respon' => '', 'status_proposal' => '', 'file_name' => '', 'perihal' => ''];
        };
        $hasil = [];
        $o = $this->safeExec("SELECT id_surat_masuk, jenis_layanan_opti FROM order_layanan WHERE id = ? LIMIT 1", [1 => $orderId]);
        $o = !empty($o) ? $o[0] : [];
        $divisi = strtolower((string) (isset($o['jenis_layanan_opti']) ? $o['jenis_layanan_opti'] : ''));

        // 1. Surat Masuk Permohonan
        $sm = $baru('surat_masuk', 'Surat Masuk Permohonan', 'bi-envelope-paper-fill', 'amber', 'Dokumen permohonan kerjasama layanan dari mitra industri', 'Tidak Ada');
        if (!empty($o['id_surat_masuk']) && $this->dbSekretariat) {
            try {
                $s = $this->dbSekretariat->exec(
                    "SELECT a.id_arsip, a.nomor_surat, a.tanggal_surat, a.perihal, a.nama_berkas, COALESCE(c.nmcustomer, 'Mitra Pemohon') AS pengirim, c.pt_cv
                     FROM tb_arsipsurat a LEFT JOIN tb_customer c ON a.id_customer = c.id_customer WHERE a.id_arsip = ? LIMIT 1",
                    [1 => (int) $o['id_surat_masuk']]
                );
                if (!empty($s)) {
                    $sm['ada'] = true;
                    $sm['url'] = $base . '/surat-masuk/' . (int) $s[0]['id_arsip'] . '/pdf';
                    $sm['nomor'] = (string) $s[0]['nomor_surat'];
                    $sm['tanggal'] = (string) $s[0]['tanggal_surat'];
                    $sm['perihal'] = (string) $s[0]['perihal'];
                    $sm['file_name'] = !empty($s[0]['nama_berkas']) ? basename((string) $s[0]['nama_berkas']) : '';
                    $sm['pengirim'] = class_exists('Customer') ? (string) \Customer::formatNamaPerusahaan(isset($s[0]['pt_cv']) ? $s[0]['pt_cv'] : '', $s[0]['pengirim']) : (string) $s[0]['pengirim'];
                }
            } catch (\Throwable $e) { /* data surat masuk bersifat pelengkap */ }
        }
        $sm['ket'] = $sm['ada'] ? 'Surat permohonan resmi dari mitra pemohon telah terdaftar di loket sekretariat.'
                                : 'Order diproses secara mandiri atau belum ditautkan dengan registrasi surat masuk loket balai.';
        $hasil[] = $sm;

        // 2. Proposal Riset / Teknis (hanya bila divisi selulosa atau order memang punya proposal)
        $pr = $this->safeExec("SELECT id, file_proposal, judul_proposal, status_proposal FROM opti_proposal_riset WHERE order_id = ? ORDER BY id DESC LIMIT 1", [1 => $orderId]);
        if ($divisi === 'selulosa' || !empty($pr)) {
            $pr = !empty($pr) ? $pr[0] : [];
            $p = $baru('proposal', 'Proposal Riset / Teknis', 'bi-journal-text', 'danger', 'Dokumen kajian metodologi, parameter analisis, dan perencanaan teknis');
            $stp = (string) (isset($pr['status_proposal']) ? $pr['status_proposal'] : '');
            if (!empty($pr['file_proposal'])) {
                $p['ada'] = true;
                $p['url'] = $base . '/proposal/' . (int) $pr['id'] . '/file';
                $p['file_name'] = basename((string) $pr['file_proposal']);
            }
            $p['status_proposal'] = $stp !== '' ? ucwords(str_replace('_', ' ', $stp)) : '';
            $p['perihal'] = (string) (isset($pr['judul_proposal']) ? $pr['judul_proposal'] : '');
            $p['ket'] = !empty($pr) ? 'Proposal kajian teknis & metodologi riset telah disusun (' . ($p['status_proposal'] !== '' ? $p['status_proposal'] : 'Draft') . ').'
                                    : 'Proposal riset/kajian teknis belum disusun oleh PIC Teknis.';
            if (!empty($pr) && !$p['ada']) { $p['kosong'] = 'Belum Ada File'; }
            $hasil[] = $p;
        }

        // 3. Surat Penawaran Harga
        $sp = $this->safeExec(
            "SELECT id, nomor_surat, tanggal_surat, nominal_penawaran, status_respon_klien, file_lampiran FROM tb_surat_penawaran
             WHERE order_id = ? ORDER BY (status_respon_klien = 'deal') DESC, id DESC LIMIT 1",
            [1 => $orderId]
        );
        $sp = !empty($sp) ? $sp[0] : [];
        $q = $baru('penawaran', 'Surat Penawaran Harga (SPH)', 'bi-file-earmark-text-fill', 'primary', 'Dokumen resmi penawaran tarif PNBP dan ruang lingkup pekerjaan', 'Belum Diterbitkan');
        $st = strtolower((string) (isset($sp['status_respon_klien']) ? $sp['status_respon_klien'] : ''));
        if (!empty($sp['file_lampiran'])) {
            $q['url'] = $base . '/penawaran/' . (int) $sp['id'] . '/lampiran';
            $q['file_name'] = basename((string) $sp['file_lampiran']);
        } elseif (!empty($sp['nomor_surat'])) {
            $q['url'] = $base . '/surat-penawaran/' . $orderId . '/pdf?id=' . (int) $sp['id'];
        }
        $q['ada'] = $q['url'] !== null;
        $q['nomor'] = (string) (isset($sp['nomor_surat']) ? $sp['nomor_surat'] : '');
        $q['tanggal'] = (string) (isset($sp['tanggal_surat']) ? $sp['tanggal_surat'] : '');
        $q['nominal'] = (float) (isset($sp['nominal_penawaran']) ? $sp['nominal_penawaran'] : 0);
        $q['status_respon'] = strtoupper($st);
        if ($st === 'deal') { $q['badge'] = 'Disepakati (DEAL)'; $q['ket'] = 'Surat penawaran harga telah disepakati oleh mitra (Status: DEAL).'; }
        elseif ($st === 'terkirim') { $q['badge'] = 'Terkirim / Negosiasi'; $q['ket'] = 'Surat penawaran harga telah dikirimkan ke mitra dan menunggu konfirmasi persetujuan.'; }
        elseif ($q['ada']) { $q['badge'] = 'Draft Penawaran'; $q['ket'] = 'Surat penawaran telah dirancang dan tersimpan dalam sistem.'; }
        else { $q['ket'] = 'Surat Penawaran Harga (SPH) belum diterbitkan oleh Tim Pemasaran.'; }
        $hasil[] = $q;

        // 4. Surat Kesanggupan Bayar
        $skb = $this->safeExec(
            "SELECT surat_kesanggupan_bayar FROM tb_surat_penawaran WHERE order_id = ? AND surat_kesanggupan_bayar IS NOT NULL AND surat_kesanggupan_bayar <> '' ORDER BY (status_respon_klien = 'deal') DESC, id DESC LIMIT 1",
            [1 => $orderId]
        );
        $b = $baru('kesanggupan', 'Surat Kesanggupan Bayar (SKB)', 'bi-file-earmark-check-fill', 'indigo', 'Surat komitmen penjaminan pembayaran dari mitra sebelum pekerjaan dimulai', 'Tidak Ada');
        $b['ada'] = !empty($skb) && $skbUrl !== null;
        $b['url'] = $b['ada'] ? $skbUrl : null;
        $b['file_name'] = !empty($skb) ? basename((string) $skb[0]['surat_kesanggupan_bayar']) : '';
        $b['ket'] = $b['ada'] ? 'Surat pernyataan kesanggupan pembayaran dari mitra industri telah diunggah.'
                              : 'Mitra memilih skema pelunasan langsung/termin reguler tanpa penjaminan Surat Kesanggupan Bayar.';
        $hasil[] = $b;
        return $hasil;
    }

    /**
     * GET /po-kegiatan/@id/berkas -- ringkasan pembayaran + tautan berkas order dari PO ini.
     * Isinya sama dengan tab Berkas di halaman Humas (PoHumasController::berkas), tapi hak aksesnya mengikuti halaman review ini.
     */
    public function berkas($f3, $params) {
        list($po, $sisi) = $this->aksesBaca((int) $params['id']);
        $orderId = (int) $po['order_id'];

        /* sql:berkas_order */
        $o = $this->safeExec("SELECT nomor_order, judul_kegiatan, estimasi_biaya FROM order_layanan WHERE id = ? LIMIT 1", [1 => $orderId]);
        $o = !empty($o) ? $o[0] : [];
        /* sql:berkas_penawaran */
        $sp = $this->safeExec(
            "SELECT surat_kesanggupan_bayar, nominal_penawaran FROM tb_surat_penawaran
             WHERE order_id = ? AND status_respon_klien = 'deal' ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        $sp = !empty($sp) ? $sp[0] : [];
        /* sql:berkas_pembayaran */
        $bayarRows = $this->safeExec(
            "SELECT id, termin_ke, jumlah, tanggal_bayar, status_verifikasi, bukti_bayar
             FROM opti_pembayaran WHERE order_id = ? AND (is_kesanggupan_bayar = 0 OR is_kesanggupan_bayar IS NULL) ORDER BY termin_ke ASC",
            [1 => $orderId]
        );

        $nilai = !empty($sp['nominal_penawaran']) ? (float) $sp['nominal_penawaran'] : (float) (isset($o['estimasi_biaya']) ? $o['estimasi_biaya'] : 0);
        $totalTerbayar = 0;
        $termin = [];
        foreach ($bayarRows as $b) {
            if ($b['status_verifikasi'] === 'terverifikasi') { $totalTerbayar += (float) $b['jumlah']; }
            $termin[] = [
                'termin_ke'         => (int) $b['termin_ke'],
                'jumlah'            => (float) $b['jumlah'],
                'tanggal_bayar'     => $b['tanggal_bayar'],
                'status_verifikasi' => $b['status_verifikasi'],
                // lewat endpoint halaman ini (hak akses mengikuti review PO), BUKAN /pembayaran/bukti yang khusus Keuangan dan Superadmin
                'url'               => !empty($b['bukti_bayar']) ? ($f3->get('BASE') . '/po-kegiatan/' . (int) $po['id'] . '/berkas/bukti/' . (int) $b['id']) : null,
            ];
        }
        $kesanggupanAda = !empty($sp['surat_kesanggupan_bayar']);

        $this->keluarkanJson([
            'ok'    => true,
            'po'    => ['id' => (int) $po['id'], 'nomor' => (string) $po['nomor_po']],
            'order' => [
                'nomor' => (string) (isset($o['nomor_order']) ? $o['nomor_order'] : ''),
                'judul' => (string) (isset($o['judul_kegiatan']) ? $o['judul_kegiatan'] : ''),
            ],
            'nilai'          => $nilai,
            'total_terbayar' => $totalTerbayar,
            'lunas'          => $nilai > 0 && $totalTerbayar >= $nilai,
            'kesanggupan'    => [
                'ada' => $kesanggupanAda,
                'url' => $kesanggupanAda ? ($f3->get('BASE') . '/po-kegiatan/' . (int) $po['id'] . '/berkas/kesanggupan') : null,
            ],
            'termin' => $termin,
            'rab_excel' => $this->infoRabExcel((int) $po['id']),
            'dokumen' => $this->dokumenOrder($orderId, $kesanggupanAda ? ($f3->get('BASE') . '/po-kegiatan/' . (int) $po['id'] . '/berkas/kesanggupan') : null),
        ]);
    }

    /** Rincian "RAB dari Excel" milik PO (berkas asli + hasil pengecekan saat dikirim). ['ada' => false] bila RAB diisi manual. */
    protected function infoRabExcel($poId) {
        $r = $this->safeExec("SELECT rab FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $poId]);
        $rab = (!empty($r) && !empty($r[0]['rab'])) ? json_decode((string) $r[0]['rab'], true) : null;
        $sm = (is_array($rab) && isset($rab['sumber']) && is_array($rab['sumber']) && ($rab['sumber']['tipe'] ?? '') === 'excel') ? $rab['sumber'] : null;
        if (!$sm) { return ['ada' => false]; }
        $path = \PoKegiatanController::rabExcelJalur($sm['token'] ?? '');
        if ($path === '' || !is_file($path)) { return ['ada' => false]; }
        $url = (string) $this->f3->get('BASE') . '/po-kegiatan/' . (int) $poId . '/rab-excel';
        return [
            'ada' => true, 'nama' => (string) ($sm['nama'] ?? 'RAB.xlsx'), 'waktu' => (string) ($sm['waktu'] ?? ''),
            'url' => $url, 'url_unduh' => $url . '?unduh=1',
            'total_pengeluaran' => (float) ($sm['total_pengeluaran'] ?? 0), 'total_penerimaan' => (float) ($sm['total_penerimaan'] ?? 0),
            'kategori' => isset($sm['kategori']) && is_array($sm['kategori']) ? $sm['kategori'] : [],
            'koreksi'  => isset($sm['koreksi']) && is_array($sm['koreksi']) ? $sm['koreksi'] : [],
            'masalah'  => isset($sm['masalah']) && is_array($sm['masalah']) ? $sm['masalah'] : [],
        ];
    }

    /** GET /po-kegiatan/@id/rab-excel[?unduh=1] -- pratinjau (per sheet, hanya-baca) atau unduh berkas Excel RAB asli. */
    public function rabExcel($f3, $params) {
        list($po, $sisi) = $this->aksesBaca((int) $params['id']);
        $info = $this->infoRabExcel((int) $po['id']);
        if (empty($info['ada'])) { $f3->error(404, 'PO ini tidak memakai file Excel untuk RAB.'); return; }
        $r = $this->safeExec("SELECT rab FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $po['id']]);
        $rab = json_decode((string) $r[0]['rab'], true);
        \RabExcel::sajikan(\PoKegiatanController::rabExcelJalur($rab['sumber']['token']), 'RAB ' . (string) $po['nomor_po'], $info['url_unduh'], $info['nama']);
    }

    /** GET /po-kegiatan/@id/po-ttd[?unduh=1] -- PO bertanda tangan untuk tab PO TTD di slide (hak akses sama dengan tab Berkas) */
    public function lihatPoTtd($f3, $params) {
        list($po, $sisi) = $this->aksesBaca((int) $params['id']);
        \PoSchema::kirimPoTtd($f3, $this->db, (int) $po['id']);
    }

    /** GET /po-kegiatan/@id/berkas/kesanggupan[?unduh=1] -- file Surat Kesanggupan Bayar order dari PO ini */
    public function berkasKesanggupan($f3, $params) {
        list($po, $sisi) = $this->aksesBaca((int) $params['id']);
        /* sql:berkas_file_kesanggupan */
        $r = $this->safeExec(
            "SELECT surat_kesanggupan_bayar FROM tb_surat_penawaran
             WHERE order_id = ? AND surat_kesanggupan_bayar IS NOT NULL AND surat_kesanggupan_bayar <> ''
             ORDER BY (status_respon_klien = 'deal') DESC, id DESC LIMIT 1",
            [1 => (int) $po['order_id']]
        );
        $this->kirimBerkas(!empty($r) ? $r[0]['surat_kesanggupan_bayar'] : null, 'Surat-Kesanggupan-Bayar-' . $po['nomor_po']);
    }

    /** GET /po-kegiatan/@id/berkas/bukti/@pid[?unduh=1] -- file bukti pembayaran satu termin (harus milik order PO ini) */
    public function berkasBukti($f3, $params) {
        list($po, $sisi) = $this->aksesBaca((int) $params['id']);
        /* sql:berkas_file_bukti */
        $r = $this->safeExec(
            "SELECT bukti_bayar, termin_ke FROM opti_pembayaran
             WHERE id = ? AND order_id = ? AND (is_kesanggupan_bayar = 0 OR is_kesanggupan_bayar IS NULL) LIMIT 1",
            [1 => (int) $params['pid'], 2 => (int) $po['order_id']]
        );
        $this->kirimBerkas(!empty($r) ? $r[0]['bukti_bayar'] : null, 'Bukti-Pembayaran-Termin-' . (!empty($r) ? (int) $r[0]['termin_ke'] : 0) . '-' . $po['nomor_po']);
    }

    /**
     * GET /po-kegiatan/notif -- ringkasan pesan belum dibaca (satu baris per PO), untuk dropdown lonceng.
     * Pesan otomatis sistem tidak ikut dihitung (perubahan status sudah terlihat di daftar).
     */
    public function notifJson($f3) {
        $this->requireAuth();

        $mode = $this->modeHalaman();
        if ($mode === null || $mode === 'pjt') {
            $this->keluarkanJson(['ok' => true, 'total' => 0, 'items' => []]);
        }

        $uid = (int) $this->getUserId();
        $sisiLawan = ($mode === 'katim') ? 'pembuat' : 'katim';

        $sql = "SELECT pc.po_id, p.nomor_po, pc.pengirim_id, pc.pesan, pc.created_at,
                       (SELECT COUNT(*) FROM po_chat x
                         WHERE x.po_id = pc.po_id AND x.sisi = ? AND x.sistem = 0 AND x.dibaca_at IS NULL) AS jumlah
                FROM po_chat pc
                JOIN po_kegiatan p ON p.id = pc.po_id
                JOIN order_layanan o ON o.id = p.order_id
                WHERE p.status = 'terkirim'
                  AND pc.sisi = ? AND pc.sistem = 0 AND pc.dibaca_at IS NULL
                  AND pc.id = (SELECT MAX(y.id) FROM po_chat y
                                WHERE y.po_id = pc.po_id AND y.sisi = ? AND y.sistem = 0 AND y.dibaca_at IS NULL)";
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
     * DIPANGGIL DARI CONTROLLER LAIN (static, tanpa instance)
     * ============================================================ */

    /**
     * Panggil SETELAH $po->save() di PoKegiatanController::store() dan update().
     * Saat PO pertama kali berstatus 'terkirim': catat dikirim_at, reset penanda review, beri catatan sistem, kabari Ketua Tim.
     * Aman dipanggil berulang (hanya bertindak sekali, selama dikirim_at masih kosong).
     */
    public static function tandaiDikirim($db, $poId, $userId) {
        try {
            $rows = $db->exec(
                "SELECT p.status, p.dikirim_at, p.nomor_po, o.jenis_layanan_opti
                   FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id WHERE p.id = ?",
                [1 => (int) $poId]
            );
            if (empty($rows) || $rows[0]['status'] !== 'terkirim' || !empty($rows[0]['dikirim_at'])) { return; }

            $diubah = $db->exec(
                "UPDATE po_kegiatan
                    SET dikirim_at = NOW(), status_review = 'menunggu_review', reviewed_at = NULL, reviewed_by = NULL
                  WHERE id = ? AND dikirim_at IS NULL",
                [1 => (int) $poId]
            );
            if ((int) $diubah === 0) { return; }

            self::catatPesanSistem($db, $poId, $userId, 'pembuat', 'PO dikirim untuk direview.');
            self::kabari($db, self::idKatim($db, $rows[0]['jenis_layanan_opti']), 'PO baru menunggu review', 'PO ' . $rows[0]['nomor_po'] . ' baru dikirim.');
        } catch (\Exception $e) {
            // pencatatan jejak tidak boleh menggagalkan penyimpanan PO
        }
    }

    /**
     * Apakah PO boleh diedit sekarang? Dipakai di PoKegiatanController::edit() dan update().
     * Draft: boleh. Terkirim: hanya saat Ketua Tim meminta revisi (perlu_revisi).
     * @return array [bool boleh, string pesan]
     */
    public static function bolehEdit($db, $poId) {
        try {
            $rows = $db->exec("SELECT status, status_review FROM po_kegiatan WHERE id = ?", [1 => (int) $poId]);
        } catch (\Exception $e) {
            return [true, ''];   // kolom/migration belum ada: jangan memblokir editing
        }
        if (empty($rows)) { return [false, 'PO tidak ditemukan.']; }
        if ($rows[0]['status'] !== 'terkirim') { return [true, '']; }

        switch ($rows[0]['status_review']) {
            case 'perlu_revisi':
                return [true, ''];
            case 'disetujui':
                return [false, 'PO sudah disetujui dan dikunci. Minta Ketua Tim membuka kembali.'];
            default:
                return [false, 'PO sedang direview Ketua Tim. PO baru bisa diedit setelah Ketua Tim meminta revisi.'];
        }
    }

    /**
     * Angka untuk badge menu "Daftar PO" di sidebar = jumlah baris BELUM DIBACA (sama dengan jumlah titik biru di daftar).
     * Ketua Tim / Superadmin: PO baru yang belum dibuka + revisi masuk yang belum dibuka.
     * Ketua Pelaksana      : permintaan revisi yang belum dibuka.
     * Pasang di Controller::render():
     *   $this->f3->set('jumlah_notif_daftar_po', \PoReviewController::hitungBelumDibaca($this->db, $role, $userId, $layanan));
     */
    public static function hitungBelumDibaca($db, $role, $userId, $divisi) {
        try {
            if ($role === 'tim_kerja') {
                /* sql:belum_dibaca_pembuat */
                $sql = "SELECT COUNT(*) AS c FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id
                         WHERE p.status = 'terkirim' AND p.status_review = 'perlu_revisi' AND p.minta_dibaca_at IS NULL
                           AND o.ketua_pelaksana_id = ?";
                $r = $db->exec($sql, [1 => (int) $userId]);
            } elseif ($role === 'ketua_tim' || $role === 'kepala_lab_lingkungan' || $role === 'superadmin') {
                /* sql:belum_dibaca_katim */
                $sql = "SELECT COUNT(*) AS c FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id
                         WHERE p.status = 'terkirim'
                           AND ((p.status_review = 'menunggu_review' AND p.reviewed_at IS NULL)
                             OR (p.status_review = 'direvisi' AND p.revisi_dibaca_at IS NULL))";
                $prm = [];
                if (($role === 'ketua_tim' || $role === 'kepala_lab_lingkungan') && in_array($divisi, self::DIVISI_VALID, true)) {
                    $sql .= " AND o.jenis_layanan_opti = ?";
                    $prm[1] = $divisi;
                }
                $r = $db->exec($sql, $prm);
            } else {
                return 0;
            }
            $n = isset($r[0]['c']) ? (int) $r[0]['c'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
        return $n + self::hitungPpkBelumDibaca($db, $role, $divisi, $userId);
    }

    /**
     * Tambahan angka badge untuk Ketua Tim / Superadmin: revisi PPK BLU yang belum dibuka (permintaan revisi baru, atau hasil revisi
     * yang dikirim Ketua Pelaksana). Revisi PPK BLU ikut tampil di Daftar PO Ketua Tim, jadi ikut dihitung di sini.
     * Dihitung terpisah supaya angka lama tetap benar walau kolom revisi PPK BLU belum ada (hasil 0).
     */
    public static function hitungPpkBelumDibaca($db, $role, $divisi, $userId = 0) {
        if ($role === 'tim_kerja') {
            // Ketua Pelaksana: revisi PPK BLU yang didisposisikan kepadanya dan belum dibuka
            try {
                $r = $db->exec(
                    "SELECT COUNT(*) AS c FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id
                      WHERE p.status IN ('terkirim', 'disetujui_mitra', 'disetujui') AND p.status_review = 'disetujui'
                        AND p.ppk_status = 'ketupel_kerja' AND p.ppk_dibaca_at IS NULL AND p.validasi_keuangan_at IS NULL
                        AND o.ketua_pelaksana_id = ?",
                    [1 => (int) $userId]
                );
                return isset($r[0]['c']) ? (int) $r[0]['c'] : 0;
            } catch (\Exception $e) {
                return 0;
            }
        }
        if ($role !== 'ketua_tim' && $role !== 'kepala_lab_lingkungan' && $role !== 'superadmin') { return 0; }
        try {
            $sql = "SELECT COUNT(*) AS c FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id
                     WHERE p.status IN ('terkirim', 'disetujui_mitra', 'disetujui') AND p.status_review = 'disetujui'
                       AND p.ppk_status IN ('minta', 'katim_review') AND p.ppk_dibaca_at IS NULL AND p.validasi_keuangan_at IS NULL";
            $prm = [];
            if (($role === 'ketua_tim' || $role === 'kepala_lab_lingkungan') && in_array($divisi, self::DIVISI_VALID, true)) {
                $sql .= " AND o.jenis_layanan_opti = ?";
                $prm[1] = $divisi;
            }
            $r = $db->exec($sql, $prm);
            return isset($r[0]['c']) ? (int) $r[0]['c'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** Nama lama (dulu menghitung PO yang menunggu tindakan). Kini sama dengan hitungBelumDibaca. */
    public static function hitungAksi($db, $role, $userId, $divisi) {
        return self::hitungBelumDibaca($db, $role, $userId, $divisi);
    }

    /** Dipertahankan agar PoKegiatanController lama tidak error. Status kini diubah lewat Kirim revisi; hook ini boleh dihapus. */
    public static function tandaiRevisiBilaPerlu($db, $poId, $userId) {
        return;
    }

    /** Catat pesan otomatis di thread PO. $sekaliSaja: lewati kalau sudah ada pesan sistem dari sisi yang sama yang belum dibaca. */
    public static function catatPesanSistem($db, $poId, $pengirimId, $sisi, $teks, $sekaliSaja = false) {
        try {
            if ($sekaliSaja) {
                $ada = $db->exec(
                    "SELECT id FROM po_chat WHERE po_id = ? AND sisi = ? AND sistem = 1 AND dibaca_at IS NULL LIMIT 1",
                    [1 => (int) $poId, 2 => $sisi]
                );
                if (!empty($ada)) { return; }
            }
            $db->exec(
                "INSERT INTO po_chat (po_id, pengirim_id, sisi, pesan, sistem, created_at) VALUES (?, ?, ?, ?, 1, NOW())",
                [1 => (int) $poId, 2 => (int) $pengirimId, 3 => $sisi, 4 => $teks]
            );
        } catch (\Throwable $e) {
            // catatan sistem bersifat pelengkap; gagalnya chat tidak boleh ikut menggagalkan Riwayat Aktivitas di bawah
            error_log('[catatPesanSistem] po_chat gagal (PO ' . (int) $poId . '): ' . $e->getMessage());
        }
        self::catatRiwayatOrder($db, $poId, $pengirimId, $teks);   // salin juga ke Riwayat Aktivitas detail order
    }

    /**
     * Salin kejadian alur PO ke Riwayat Aktivitas di detail order (opti_activity_log), supaya alur PO di Daftar Review PO
     * nyambung dengan Riwayat Aktivitas. Dipanggil otomatis dari catatPesanSistem() dan langsung dari verifikasi Humas.
     * Tidak pernah menggagalkan proses utama.
     */
    public static function catatRiwayatOrder($db, $poId, $userId, $teks, $aksi = '') {
        try {
            $teks = trim((string) $teks);
            if ($teks === '' || preg_match('/\bmembaca\b/u', $teks)) { return; }   // "membaca ..." = penanda dibaca, bukan kejadian alur
            $r = $db->exec("SELECT order_id, nomor_po FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $poId]);
            if (empty($r) || (int) $r[0]['order_id'] <= 0) { return; }
            $orderId = (int) $r[0]['order_id'];
            if ($aksi === '') { $aksi = self::aksiRiwayatDariTeks($teks); }
            $nomor = trim((string) $r[0]['nomor_po']);
            $desk = self::ringkasRiwayat($teks) . ($nomor !== '' ? ' (PO ' . $nomor . ')' : '');
            if (function_exists('mb_substr')) { $desk = mb_substr($desk, 0, 480, 'UTF-8'); }
            $uid = (int) $userId;
            // jangan ganda kalau kejadian yang sama tercatat dua kali dalam beberapa detik
            $ada = $db->exec(
                "SELECT id FROM opti_activity_log WHERE order_id = ? AND aksi = ? AND deskripsi = ? AND user_id = ? AND created_at >= (NOW() - INTERVAL 10 SECOND) LIMIT 1",
                [1 => $orderId, 2 => $aksi, 3 => $desk, 4 => $uid]
            );
            if (!empty($ada)) { return; }
            $nama = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : (isset($_SESSION['nama_user']) ? $_SESSION['nama_user'] : 'User');
            $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
            $db->exec(
                "INSERT INTO opti_activity_log (order_id, modul, aksi, deskripsi, user_id, user_nama, user_role, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [1 => $orderId, 2 => 'po_kegiatan', 3 => $aksi, 4 => $desk, 5 => $uid, 6 => (string) $nama, 7 => (string) $role, 8 => (string) $ip]
            );
        } catch (\Throwable $e) {
            // riwayat bersifat pelengkap; alasan gagal dicatat di log PHP supaya bisa ditelusuri
            error_log('[catatRiwayatOrder] gagal (PO ' . (int) $poId . '): ' . $e->getMessage());
        }
    }

    /**
     * Ringkas bunyi pesan alur PO untuk Riwayat Aktivitas: nama pelaku dibuang (sudah tampil di bawah kartu) dan kalimat dipadatkan.
     * Chat alur PO tetap memakai kalimat lengkap; ini hanya untuk Riwayat. Bunyi yang tidak dikenal hanya dibuang nama pelakunya.
     */
    public static function ringkasRiwayat($teks) {
        $t = trim((string) $teks);
        if (preg_match('/^Humas memverifikasi PO\./u', $t)) { return 'PO diverifikasi, diteruskan ke Tim Mitra'; }
        // buang nama pelaku di depan: "<nama> (<peran>) ..." atau "<nama> <kata kerja> ..."
        $awal = $t;
        $t = preg_replace('/^[^.()]+?\s\((?:Ketua Tim|Ketua Pelaksana|PPK BLU|Humas|Tim Mitra)\)\s+/u', '', $t);
        if ($t === $awal) {
            $t = preg_replace('/^(?:(?!\b(?:meminta|mengirim|menyetujui|membuka|mengedit|mengerjakan|mendisposisikan|memverifikasi)\b)[^.()])+?\s(?=(?:meminta|mengirim|menyetujui|membuka|mengedit|mengerjakan|mendisposisikan|memverifikasi)\s)/u', '', $t);
        }
        $peta = array(
            '/^meminta revisi \((\d+) catatan\)\. Menunggu keputusan Ketua Tim\.?$/u' => 'Minta revisi ($1 catatan), menunggu keputusan Ketua Tim',
            '/^meminta revisi \((\d+) catatan\)\.?$/u'                                => 'Minta revisi ($1 catatan)',
            '/^meminta perbaikan [^(]*\((\d+) catatan[^)]*\):\s*(.+?)\.?$/u'          => 'Minta perbaikan ($1 catatan): $2',
            '/^meminta perbaikan [^(]*\((\d+) catatan[^)]*\)\.?$/u'                   => 'Minta perbaikan ($1 catatan)',
            '/^mengirim revisi\. Menunggu review ulang\.?$/u'                         => 'Revisi dikirim, menunggu review ulang',
            '/^menyetujui PO\. PO terkunci dan diteruskan ke Humas.*$/u'              => 'PO disetujui, diteruskan ke Humas',
            '/^membuka kembali PO untuk direview\..*$/u'                              => 'PO dibuka kembali untuk review',
            '/^PO dikirim untuk direview\.?$/u'                                       => 'PO dikirim untuk review',
            '/^PO diverifikasi Tim Mitra.*$/u'                                        => 'PO diverifikasi Tim Mitra',
            '/^mengedit isi PO ini saat review\.?$/u'                                 => 'Isi PO diedit saat review',
            '/^mengerjakan revisi PPK BLU sendiri\.?$/u'                              => 'Revisi PPK BLU dikerjakan sendiri',
            '/^mendisposisikan revisi PPK BLU ke Ketua Pelaksana\.?$/u'               => 'Revisi PPK BLU didisposisikan ke Ketua Pelaksana',
            '/^mengirim revisi ke PPK BLU\.?$/u'                                      => 'Revisi dikirim ke PPK BLU',
            '/^menyetujui revisi dan mengirim ke PPK BLU\.?$/u'                       => 'Revisi disetujui, dikirim ke PPK BLU',
            '/^mengirim revisi PPK BLU ke Ketua Tim\.?$/u'                            => 'Revisi PPK BLU dikirim ke Ketua Tim',
            '/^memverifikasi PO\.?$/u'                                                => 'PO disetujui PPK BLU',
            '/^Perubahan PO disimpan \([^)]*\):\s*(.+?)\.?$/u'                        => 'Perubahan PO disimpan: $1',
        );
        foreach ($peta as $pola => $ganti) {
            if (preg_match($pola, $t)) { return preg_replace($pola, $ganti, $t); }
        }
        return $t !== '' ? mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($t, 1, null, 'UTF-8') : trim((string) $teks);
    }

    /** Ringkas deskripsi Riwayat yang sudah tersimpan (termasuk catatan lama): ringkas bunyinya, sufiks " (PO <nomor>)" dijaga. */
    public static function ringkasDeskripsiRiwayat($desk) {
        $desk = (string) $desk;
        $sufiks = '';
        if (preg_match('/^(.*?)(\s\(PO [^()]*\))$/su', $desk, $m)) { $desk = $m[1]; $sufiks = $m[2]; }
        return self::ringkasRiwayat($desk) . $sufiks;
    }

    /** Nama aksi Riwayat dari bunyi pesan sistem (dipakai ikon & warna di Riwayat Aktivitas detail order). */
    protected static function aksiRiwayatDariTeks($teks) {
        $t = mb_strtolower((string) $teks, 'UTF-8');
        $peta = array(
            'meminta revisi' => 'minta_revisi', 'meminta perbaikan' => 'minta_revisi',
            'menyetujui po' => 'review_setuju', 'menyetujui revisi' => 'review_setuju',
            'memverifikasi po' => 'verif_ppk', 'diverifikasi tim mitra' => 'verif_mitra',
            'dikirim untuk direview' => 'kirim_petunjuk', 'mengirim revisi' => 'kirim_revisi',
            'mendisposisikan' => 'alihkan_revisi', 'membuka kembali' => 'buka_kembali', 'mengerjakan revisi' => 'kerjakan_revisi',
        );
        foreach ($peta as $kata => $aksi) {
            if (strpos($t, $kata) !== false) { return $aksi; }
        }
        return 'alur_lain';
    }

    /** id_user semua Ketua Tim aktif untuk satu divisi (opti_user_map). */
    public static function idKatim($db, $divisi) {
        $hasil = [];
        try {
            $rows = $db->exec(
                "SELECT id_user FROM opti_user_map
                  WHERE role = 'ketua_tim' AND is_active = 1 AND status_tampil = 'show' AND jenis_layanan IN (?, 'semua')",
                [1 => (string) $divisi]
            );
            foreach ($rows as $r) { $hasil[] = (int) $r['id_user']; }
        } catch (\Exception $e) {
            // tanpa daftar Ketua Tim, notifikasi dilewati
        }
        return $hasil;
    }

    /**
     * Notifikasi lonceng (opti_notifikasi) ke pengguna tertentu -- muncul di ikon lonceng + toast "Pemberitahuan".
     * Pengguna yang sedang login (pelaku aksi) tidak dikirimi. Dilewati diam-diam kalau NotificationService belum ada.
     */
    public static function lonceng($db, array $userIds, $judul, $pesan, $link = '/po-kegiatan/daftar', $tipe = 'info', $icon = 'bi-clipboard-check', $orderId = 0, $poId = 0) {
        if (!class_exists('NotificationService')) { return; }
        $aku = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $nama = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'Sistem OPTI';
        $sudah = [];
        foreach ($userIds as $id) {
            $id = (int) $id;
            if ($id <= 0 || $id === $aku || isset($sudah[$id])) { continue; }
            $sudah[$id] = true;
            try {
                \NotificationService::send($db, [
                    'order_id'        => (int) $orderId,
                    'po_id'           => (int) $poId,
                    'target_user_id'  => $id,
                    'judul'           => $judul,
                    'pesan'           => $pesan,
                    'tipe'            => $tipe,
                    'icon'            => $icon,
                    'link_url'        => $link,
                    'created_by_name' => $nama,
                ]);
            } catch (\Throwable $e) {
                // notifikasi bersifat pelengkap
            }
        }
    }

    /**
     * Beri tahu pengguna: lonceng di aplikasi (selalu, kecuali $lonceng = false) + Web Push kalau PushNotifikasi terpasang.
     * $link = halaman tujuan saat notifikasi diklik.
     */
    public static function kabari($db, array $userIds, $judul, $pesan, $link = '/po-kegiatan/daftar', $lonceng = true) {
        if ($lonceng) { self::lonceng($db, $userIds, $judul, $pesan, $link); }
        if (!class_exists('PushNotifikasi')) { return; }
        $base = (string) \Base::instance()->get('BASE');
        $sudah = [];
        foreach ($userIds as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($sudah[$id])) { continue; }
            $sudah[$id] = true;
            try {
                \PushNotifikasi::kirim($db, $id, $judul, $pesan, $base . $link, 'po-review');
            } catch (\Throwable $e) {
                // notifikasi bersifat pelengkap
            }
        }
    }

    /* ============================================================
     * HELPER
     * ============================================================ */

    /** 'katim' | 'pembuat' | null, berdasarkan peran login. */
    protected function modeHalaman() {
        if ($this->isSuperadmin()) { return 'katim'; }
        $role = $this->getUserRole();
        if ($role === 'ketua_tim' || $role === 'kepala_lab_lingkungan') { return 'katim'; }
        if ($role === 'tim_kerja') { return 'pembuat'; }
        if ($this->isPjtLingkungan()) { return 'pjt'; }   // PJT Lingkungan: slide baca saja + ACC / masukan untuk PO yang melibatkannya
        return null;
    }

    /** Divisi Ketua Tim ('' = semua divisi). Superadmin selalu semua. */
    protected function divisiKatim() {
        if ($this->isSuperadmin()) { return ''; }
        $d = (string) ($this->getDivisiKetuaTim() ?: '');
        return in_array($d, self::DIVISI_VALID, true) ? $d : '';
    }

    /** Tambah filter cakupan data ke SQL (alias tabel: o = order_layanan). */
    protected function tambahScope($mode, $uid, &$sql, &$params, &$idx) {
        if ($this->lihatSaja) { return; }
        if ($mode === 'pjt') {
            $sql .= " AND o.jenis_layanan_opti = 'lingkungan'";
            return;
        }
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

    /** Ambil PO + order untuk pemeriksaan akses dan status. Null kalau tidak ada. */
    protected function ambilPo($poId) {
        /* sql:ambil_po */
        $sql = "SELECT p.id, p.nomor_po, p.status, p.status_review, p.reviewed_at, p.ronde_revisi,
                       p.minta_dibaca_at, p.revisi_dibaca_at,
                       o.id AS order_id, o.status AS order_status, o.jenis_layanan_opti, o.ketua_pelaksana_id
                FROM po_kegiatan p
                JOIN order_layanan o ON o.id = p.order_id
                WHERE p.id = ? LIMIT 1";
        $rows = $this->db->exec($sql, [1 => (int) $poId]);
        return !empty($rows) ? $rows[0] : null;
    }

    /** Sisi pengguna terhadap PO ini: 'katim' | 'pembuat' | null (tidak berhak). */
    protected function sisiPengguna(array $po) {
        $mode = $this->modeHalaman();
        if ($mode === 'pjt') { return ((string) $po['jenis_layanan_opti'] === 'lingkungan') ? 'pjt' : null; }
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

        try {
            $po = ($poId > 0) ? $this->ambilPo($poId) : null;
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca data PO. Pastikan semua file migration sudah dijalankan. Detail: ' . $e->getMessage()], 500);
        }
        if (!$po) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan.'], 404);
        }
        $sisi = $this->sisiPengguna($po);
        if ($sisi === null) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke PO ini.'], 403);
        }
        return [$po, $sisi];
    }

    /**
     * Akses BACA berkas (tab Dokumen: berkas, surat kesanggupan, bukti pembayaran).
     * Superadmin, Ketua Tim, dan Tim Kerja boleh membuka berkas PO divisi / layanan lain (baca saja), sama seperti membaca dokumen PO-nya lewat panel "Lihat".
     * Peran lain tetap mengikuti aturan akses() biasa.
     */
    protected function aksesBaca($poId) {
        $this->requireAuth();
        if ($this->modeHalaman() !== null) {
            try { $po = ($poId > 0) ? $this->ambilPo($poId) : null; }
            catch (\Exception $e) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca data PO.'], 500); }
            if (!$po) { $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan.'], 404); }
            $sisi = $this->sisiPengguna($po);
            return [$po, $sisi !== null ? $sisi : 'pembuat'];
        }
        return $this->akses($poId);
    }

    protected function wajibSisi($sisi, $harus, $pesan) {
        if ($sisi !== $harus) { $this->keluarkanJson(['ok' => false, 'pesan' => $pesan], 403); }
    }

    protected function wajibStatus(array $po, array $boleh, $pesan) {
        if ($po['status'] !== 'terkirim' || !in_array($po['status_review'], $boleh, true)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => $pesan], 422);
        }
    }

    /** Jumlah catatan per state: ['draf' =>, 'terbuka' =>, 'selesai' =>, 'total' =>]. */
    protected function hitungCatatan($poId) {
        /* sql:hitung_catatan */
        $sql = "SELECT SUM(state = 'draf') AS draf, SUM(state = 'terbuka') AS terbuka, SUM(state = 'selesai') AS selesai, COUNT(*) AS total
                FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review'" . $this->sqlSembunyiPjt();
        try {
            $r = $this->db->exec($sql, [1 => (int) $poId]);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca catatan. Sudah menjalankan migration_po_review_revisi.sql?'], 500);
        }
        $r = !empty($r) ? $r[0] : [];
        return [
            'draf'    => isset($r['draf']) ? (int) $r['draf'] : 0,
            'terbuka' => isset($r['terbuka']) ? (int) $r['terbuka'] : 0,
            'selesai' => isset($r['selesai']) ? (int) $r['selesai'] : 0,
            'total'   => isset($r['total']) ? (int) $r['total'] : 0,
        ];
    }

    /** Daftar PO (atau satu PO bila $poId > 0) sesuai cakupan pengguna, siap di-JSON-kan. */
    protected function daftarItems($mode, $uid, $poId) {
        $sisiLawan = ($mode === 'katim') ? 'pembuat' : 'katim';

        // Catatan yang dihitung di kolom progres: PO biasa = catatan review putaran ini; revisi PPK BLU (ppk_status terisi) = catatan PPK BLU putaran ini
        $rnKondisi = ($this->kolomAda('ppk_status') && $this->kolomAda('ronde_ppk'))
            ? "((p.ppk_status IS NULL AND x.tahap = 'review' AND x.ronde = p.ronde_revisi) OR (p.ppk_status IS NOT NULL AND x.tahap = 'ppk' AND x.ronde = p.ronde_ppk))"
            : "(x.tahap = 'review' AND x.ronde = p.ronde_revisi)";

        /* sql:daftar */
        $sql = "SELECT p.id, p.nomor_po, p.status AS po_status, p.status_review, p.ronde_revisi,
                       COALESCE(p.dikirim_at, p.updated_at, p.created_at) AS dikirim_at,
                       p.reviewed_at AS dibaca_at, p.minta_at, p.minta_dibaca_at, p.revisi_at, p.revisi_dibaca_at, p.disetujui_at, p.created_at AS dibuat_at,
                       " . $this->kolomPosisiSql() . "
                       COALESCE(NULLIF(p.judul_kegiatan, ''), o.judul_kegiatan) AS judul,
                       o.nomor_order, o.jenis_layanan_opti, o.ketua_pelaksana_id,
                       " . $this->kolomProposal() . " AS tgl_proposal,
                       c.nmcustomer AS nama_perusahaan, c.pt_cv,
                       (SELECT COUNT(*) FROM po_chat pc
                         WHERE pc.po_id = p.id AND pc.sisi = ? AND pc.sistem = 0 AND pc.dibaca_at IS NULL) AS chat_unread,
                       (SELECT COUNT(*) FROM po_revisi_catatan x
                         WHERE x.po_id = p.id AND " . $rnKondisi . " AND x.state <> 'draf') AS rn_total,
                       (SELECT COUNT(*) FROM po_revisi_catatan x
                         WHERE x.po_id = p.id AND " . $rnKondisi . " AND x.state = 'selesai') AS rn_selesai,
                       (SELECT COUNT(*) FROM tb_surat_penawaran spd WHERE spd.order_id = o.id AND spd.status_respon_klien = 'deal') AS sp_deal
                FROM po_kegiatan p
                JOIN order_layanan o ON o.id = p.order_id
                JOIN tb_customer c ON c.id_customer = o.id_customer
                WHERE (p.status = 'terkirim'" . ($poId > 0 ? " OR p.status = 'draft'" : '') . "
                       OR (p.status IN ('disetujui_mitra', 'disetujui') AND p.status_review = 'disetujui'))";   // draft hanya ikut bila satu PO diminta lewat id (panel Lihat PO)
        $params = [1 => $sisiLawan];
        $idx = 2;
        if ($poId > 0) {
            $sql .= " AND p.id = ?";
            $params[$idx++] = (int) $poId;
        }
        $this->tambahScope($mode, $uid, $sql, $params, $idx);
        $sql .= " ORDER BY COALESCE(p.dikirim_at, p.updated_at, p.created_at) DESC LIMIT " . (int) self::LIMIT_DAFTAR;

        $rows = $this->db->exec($sql, $params);

        $katim = $this->katimPerDivisi();
        $ids = array_values($katim);
        foreach ($rows as $r) { $ids[] = $r['ketua_pelaksana_id']; if (!empty($r['pjt_id'])) { $ids[] = $r['pjt_id']; } }
        $peta = $this->petaNamaUser($ids);

        $daftar = [];
        foreach ($rows as $r) {
            $idp = (int) $r['ketua_pelaksana_id'];
            $div = (string) $r['jenis_layanan_opti'];
            $idk = isset($katim[$div]) ? (int) $katim[$div] : 0;
            // Humas "sudah membuka" hanya berlaku kalau dibuka SETELAH PO disetujui (setujui ulang otomatis mereset)
            $humasBaca = isset($r['humas_baca_at']) ? $r['humas_baca_at'] : null;
            if ($humasBaca && !empty($r['disposisi_at']) && $humasBaca < $r['disposisi_at']) { $humasBaca = null; }
            $daftar[] = [
                'id'               => (int) $r['id'],
                'nomor'            => (string) $r['nomor_po'],
                'order'            => (string) $r['nomor_order'],
                'mitra'            => $this->formatMitra($r['nama_perusahaan'], $r['pt_cv']),
                'judul'            => (string) $r['judul'],
                'divisi'           => $div,
                'pembuat'          => isset($peta[$idp]) ? $peta[$idp] : 'Ketua Pelaksana',
                'katim'            => isset($peta[$idk]) ? $peta[$idk] : 'Ketua Tim',
                'status'           => ((string) $r['po_status'] === 'draft') ? 'draft' : (string) $r['status_review'],
                'ronde'            => (int) $r['ronde_revisi'],
                'dibuat_at'        => $r['dibuat_at'],
                'dikirim_at'       => $r['dikirim_at'],
                'dibaca_at'        => $r['dibaca_at'],
                'minta_at'         => $r['minta_at'],
                'minta_dibaca_at'  => $r['minta_dibaca_at'],
                'revisi_at'        => $r['revisi_at'],
                'revisi_dibaca_at' => $r['revisi_dibaca_at'],
                'disetujui_at'     => $r['disetujui_at'],
                'humas_baca_at'    => $humasBaca,
                'humas_ver_at'     => isset($r['humas_ver_at']) ? $r['humas_ver_at'] : null,
                'mitra_valid_at'   => isset($r['mitra_valid_at']) ? $r['mitra_valid_at'] : null,
                'ppk_valid_at'     => isset($r['ppk_valid_at']) ? $r['ppk_valid_at'] : null,
                // PO bertanda tangan + kontrak (kartu di tab Dokumen, tampil setelah PPK BLU menyetujui)
                'ttd_at'           => isset($r['po_ttd_at']) ? $r['po_ttd_at'] : null,
                'ttd_nama'         => (string) ($r['po_ttd_nama'] ?? ''),
                'ttd_oleh'         => (string) ($r['po_ttd_oleh_nama'] ?? ''),
                'kontrak_at'       => isset($r['kontrak_dibuat_at']) ? $r['kontrak_dibuat_at'] : null,
                'kontrak_nomor'    => (string) ($r['kontrak_nomor'] ?? ''),
                'kontrak_oleh'     => (string) ($r['kontrak_dibuat_oleh_nama'] ?? ''),
                'url_ttd'          => !empty($r['po_ttd_at']) ? (\Base::instance()->get('BASE') . '/po-kegiatan/' . (int) $r['id'] . '/po-ttd') : '',
                'kontrak_url'      => !empty($r['kontrak_dibuat_at']) ? $this->urlPksKartu($r['id']) : '',
                // catatan Humas ikut tampil setelah Humas memverifikasi PO
                'catatan_humas'    => (!empty($r['humas_ver_at']) && isset($r['catatan_humas'])) ? trim((string) $r['catatan_humas']) : '',
                // catatan Tim Mitra baru ditampilkan setelah Tim Mitra memvalidasi PO
                'catatan_mitra'    => (!empty($r['mitra_valid_at']) && isset($r['catatan_tim_mitra'])) ? trim((string) $r['catatan_tim_mitra']) : '',
                // catatan PPK BLU (non-revisi) ikut tampil setelah PPK BLU memverifikasi PO
                'catatan_ppk'      => (!empty($r['ppk_valid_at']) && isset($r['catatan_ppk'])) ? trim((string) $r['catatan_ppk']) : '',
                // [BARU] revisi PPK BLU (hanya dipakai untuk teks "Status PO saat ini")
                'ppk_status'       => !empty($r['ppk_status']) ? (string) $r['ppk_status'] : '',
                'ronde_ppk'        => isset($r['ronde_ppk']) ? (int) $r['ronde_ppk'] : 0,
                // kapan revisi PPK BLU berpindah ke tahap sekarang, dan kapan penerimanya membuka (dipakai badge dan penanda belum dibaca Ketua Tim)
                'ppk_status_at'    => isset($r['ppk_status_at']) ? $r['ppk_status_at'] : null,
                'ppk_dibaca_at'    => isset($r['ppk_dibaca_at']) ? $r['ppk_dibaca_at'] : null,
                // PJT Lingkungan yang dilibatkan Ketua Tim: status ('menunggu' = Setujui terkunci), id, dan nama
                'pjt_status'       => !empty($r['pjt_status']) ? (string) $r['pjt_status'] : '',
                'pjt_id'           => !empty($r['pjt_id']) ? (int) $r['pjt_id'] : 0,
                'pjt_nama'         => (!empty($r['pjt_id']) && isset($peta[(int) $r['pjt_id']])) ? $peta[(int) $r['pjt_id']] : '',
                'pjt_catatan'      => isset($r['pjt_catatan']) ? (string) $r['pjt_catatan'] : '',
                'tanpa_deal'       => (isset($r['sp_deal']) && (int) $r['sp_deal'] === 0),   // order belum punya Surat Penawaran DEAL
                // PO ini sedang menunggu tanggapan PJT yang sedang login (memunculkan tombol ACC / Kirim masukan di slide)
                'pjt_saya'         => (!empty($r['pjt_id']) && (int) $r['pjt_id'] === (int) $uid && (string) $r['pjt_status'] === 'menunggu' && in_array((string) $r['status_review'], ['menunggu_review', 'direvisi'], true)),
                'rn_total'         => (int) $r['rn_total'],
                'rn_selesai'       => (int) $r['rn_selesai'],
                'unread'           => (int) $r['chat_unread'],
                // ---- Map Kendali F.PJT-12-03/1 (kartu di tab Dokumen untuk Ketua Pelaksana; hanya tanggal) ----
                'disiapkan'        => $div !== '' ? 'Tim Kerja OPTI ' . ucfirst(strtolower($div)) : 'Tim Kerja OPTI',
                'map'              => [
                    'proposal' => self::tanggalMap((string) ($r['tgl_proposal'] ?? '')),
                    'humas'    => self::tanggalMap((string) $humasBaca),   // Humas tidak memverifikasi lagi: tanggal = Humas pertama kali membuka PO setelah disetujui Ketua Tim
                    'mitra'    => self::tanggalMap((string) ($r['mitra_valid_at'] ?? '')),
                    'keuangan' => self::tanggalMap((string) ($r['ppk_valid_at'] ?? '')),
                ],
            ];
        }
        return $daftar;
    }

    protected static function tanggalMap($v) {
        $v = trim((string) $v);
        return ($v !== '' && strpos($v, '0000') !== 0 && strtotime($v)) ? date('Y-m-d', strtotime($v)) : '';
    }

    /** Subquery tanggal proposal disetujui Ketua (proposal terbaru per order); NULL bila kolomnya belum ada. */
    protected function kolomProposal() {
        try {
            $r = $this->db->exec("SHOW COLUMNS FROM opti_proposal_riset LIKE 'disetujui_ketua_at'");
            if (!empty($r)) {
                return "(SELECT pr.disetujui_ketua_at FROM opti_proposal_riset pr WHERE pr.order_id = o.id ORDER BY pr.id DESC LIMIT 1)";
            }
        } catch (\Exception $e) {}
        return 'NULL';
    }

    /**
     * Potongan SELECT untuk "Status PO saat ini" (berakhir dengan koma). Kolom yang belum ada diganti NULL, jadi aman
     * dijalankan walau migration belum lengkap. Posisi PO tidak disimpan; dihitung di halaman dari kolom ini.
     * Urutan setelah Ketua Tim menyetujui: Humas -> Tim Mitra -> PPK BLU.
     *   humas_ver_at : verifikasi Humas (verifikasi_humas_at)   humas_baca_at : Humas membuka PO (humas_dibaca_at)
     *   mitra_valid_at : validasi Tim Mitra (tervalidasi_mitra_at)   ppk_valid_at : validasi PPK BLU (validasi_keuangan_at)
     */
    protected function kolomPosisiSql() {
        $ex = function ($kolom) { return $this->kolomAda($kolom) ? 'p.' . $kolom : 'NULL'; };
        return 'p.disposisi_humas_at AS disposisi_at, '
             . $ex('verifikasi_humas_at') . ' AS humas_ver_at, '
             . $ex('humas_dibaca_at') . ' AS humas_baca_at, '
             . $ex('tervalidasi_mitra_at') . ' AS mitra_valid_at, '
             . $ex('validasi_keuangan_at') . ' AS ppk_valid_at, '
             . $ex('po_ttd_at') . ' AS po_ttd_at, '
             . $ex('po_ttd_nama') . ' AS po_ttd_nama, '
             . $ex('po_ttd_oleh_nama') . ' AS po_ttd_oleh_nama, '
             . $ex('kontrak_dibuat_at') . ' AS kontrak_dibuat_at, '
             . $ex('kontrak_nomor') . ' AS kontrak_nomor, '
             . $ex('kontrak_dibuat_oleh_nama') . ' AS kontrak_dibuat_oleh_nama, '
             . $ex('catatan_tim_mitra') . ' AS catatan_tim_mitra, '
             . $ex('catatan_humas') . ' AS catatan_humas, '
             . $ex('catatan_ppk') . ' AS catatan_ppk, '
             . $ex('ppk_status') . ' AS ppk_status, '
             . $ex('ronde_ppk') . ' AS ronde_ppk, '
             . $ex('ppk_status_at') . ' AS ppk_status_at, '
             . $ex('ppk_dibaca_at') . ' AS ppk_dibaca_at, '
             . $ex('pjt_status') . ' AS pjt_status, '
             . $ex('pjt_id') . ' AS pjt_id, '
             . $ex('pjt_catatan') . ' AS pjt_catatan,';
    }

    /** Rincian satu PO + catatan, dalam satu paket JSON. Draf catatan hanya untuk sisi Ketua Tim. */
    protected function paketDetail($poId, $sisi) {
        $mode = ($sisi === 'katim') ? 'katim' : (($sisi === 'pjt') ? 'pjt' : 'pembuat');
        try {
            $items = $this->daftarItems($mode, (int) $this->getUserId(), (int) $poId);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat PO.'], 500);
        }
        if (empty($items)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan atau belum dikirim.'], 404);
        }

        /* sql:daftar_catatan_katim */
        $selDp = $this->adaDariPjt() ? ', dari_pjt' : ', 0 AS dari_pjt';
        $sql = "SELECT id, bagian, teks, state, ronde, dibuat_oleh, created_at" . $selDp . " FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review'" . $this->sqlSembunyiPjt() . " ORDER BY id ASC";
        /* sql:daftar_catatan_pembuat */
        $sqlPembuat = "SELECT id, bagian, teks, state, ronde, dibuat_oleh, created_at" . $selDp . " FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review' AND state <> 'draf' ORDER BY id ASC";
        $sqlPjt = "SELECT id, bagian, teks, state, ronde, dibuat_oleh, created_at" . $selDp . " FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review' AND (state <> 'draf'" . ($this->adaDariPjt() ? " OR (dari_pjt = 1 AND dibuat_oleh = " . (int) $this->getUserId() . ")" : '') . ") ORDER BY id ASC";

        try {
            $rows = $this->db->exec(($sisi === 'katim') ? $sql : (($sisi === 'pjt') ? $sqlPjt : $sqlPembuat), [1 => (int) $poId]);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat catatan. Sudah menjalankan migration_po_review_revisi.sql?'], 500);
        }

        $ids = [];
        foreach ($rows as $r) { $ids[] = $r['dibuat_oleh']; }
        $peta = $this->petaNamaUser($ids);

        $catatan = [];
        foreach ($rows as $r) {
            $idu = (int) $r['dibuat_oleh'];
            $catatan[] = [
                'id'     => (int) $r['id'],
                'bagian' => (string) $r['bagian'],
                'teks'   => (string) $r['teks'],
                'state'  => (string) $r['state'],
                'ronde'  => (int) $r['ronde'],
                'oleh'   => isset($peta[$idu]) ? $peta[$idu] : (!empty($r['dari_pjt']) ? 'PJT Lingkungan' : 'Ketua Tim'),
                'dari_pjt' => !empty($r['dari_pjt']) ? 1 : 0,
                'waktu'  => (string) $r['created_at'],
                'tahap'  => 'review',
            ];
        }
        // Catatan revisi PPK BLU (tahap 'ppk', tanpa draf): hanya dipakai panel mode "lihat" untuk PO yang sedang direvisi PPK BLU
        if (!empty($items[0]['ppk_status'])) {
            try {
                $rowsPpk = $this->db->exec("SELECT id, bagian, teks, state, ronde, dibuat_oleh, created_at FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'ppk' AND state <> 'draf' ORDER BY id ASC", [1 => (int) $poId]);
                $idsPpk = [];
                foreach ($rowsPpk as $r) { $idsPpk[] = $r['dibuat_oleh']; }
                $petaPpk = $this->petaNamaUser($idsPpk);
                foreach ($rowsPpk as $r) {
                    $idu = (int) $r['dibuat_oleh'];
                    $catatan[] = [
                        'id'     => (int) $r['id'],
                        'bagian' => (string) $r['bagian'],
                        'teks'   => (string) $r['teks'],
                        'state'  => (string) $r['state'],
                        'ronde'  => (int) $r['ronde'],
                        'oleh'   => isset($petaPpk[$idu]) ? $petaPpk[$idu] : 'PPK BLU',
                        'waktu'  => (string) $r['created_at'],
                        'tahap'  => 'ppk',
                    ];
                }
            } catch (\Exception $e) { /* catatan PPK BLU tidak wajib; panel tetap jalan */ }
        }
        return ['ok' => true, 'sisi' => $sisi, 'po' => $items[0], 'catatan' => $catatan, 'pjt_opsi' => ($sisi === 'katim' && $this->poLingkungan((int) $poId)) ? $this->opsiPjt() : []];
    }

    /** id_user Ketua Tim pertama per divisi: ['selulosa' => id, 'lingkungan' => id] (0 = tidak ada). */
    protected function katimPerDivisi() {
        $peta = ['selulosa' => 0, 'lingkungan' => 0];
        try {
            $rows = $this->db->exec("SELECT id_user, jenis_layanan AS jenis_layanan_opti FROM opti_user_map WHERE role = 'ketua_tim' AND is_active = 1 AND status_tampil = 'show' ORDER BY id ASC");
            foreach ($rows as $r) {
                $d = (string) $r['jenis_layanan_opti'];
                if (isset($peta[$d]) && $peta[$d] === 0) { $peta[$d] = (int) $r['id_user']; }
            }
        } catch (\Exception $e) {
            // nama Ketua Tim tampil generik
        }
        return $peta;
    }

    /** Nama pegawai (untuk teks pesan sistem). */
    protected function namaPengguna($uid, $cadangan) {
        $peta = $this->petaNamaUser([$uid]);
        return isset($peta[(int) $uid]) ? $peta[(int) $uid] : $cadangan;
    }

    /** Apakah kolom ada di po_kegiatan (dicache per proses). */
    protected function kolomAda($nama) {
        if (!isset(self::$kolomCache[$nama])) {
            try {
                $r = $this->db->exec(
                    "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'po_kegiatan' AND COLUMN_NAME = ?",
                    [1 => (string) $nama]
                );
                self::$kolomCache[$nama] = !empty($r) && (int) $r[0]['c'] > 0;
            } catch (\Exception $e) {
                self::$kolomCache[$nama] = false;
            }
        }
        return self::$kolomCache[$nama];
    }

    protected function bentukPesan(array $r, array $peta, $uid) {
        $idp = (int) $r['pengirim_id'];
        return [
            'id'     => (int) $r['id'],
            'sisi'   => $r['sisi'],
            'nama'   => isset($peta[$idp]) ? $peta[$idp] : '-',
            'pesan'  => (string) $r['pesan'],
            'sistem' => !empty($r['sistem']),
            'waktu'  => (string) $r['created_at'],
            'saya'   => ($idp === (int) $uid),
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
            // nama tampil generik kalau database Sekretariat tidak terjangkau
        }
        return $peta;
    }

    /** "PT PT Kertas X" + pt_cv "PT" -> "PT Kertas X" (awalan berulang dibuang, badan usaha dipasang sekali). */
    protected function formatMitra($nama, $ptCv) {
        $bersih = trim((string) preg_replace('/^((PT|CV|UD)\.?\s+)+/i', '', (string) $nama));
        $badan  = trim((string) $ptCv);
        return ($badan !== '') ? $badan . ' ' . $bersih : $bersih;
    }

    /** Query yang gagal (tabel/kolom belum ada) dianggap tidak ada datanya, bukan error 500. */
    protected function safeExec($sql, $params = []) {
        try { return $this->db->exec($sql, $params); } catch (\Exception $e) { return []; }
    }

    /**
     * Kirim berkas hasil unggahan ke browser. $relPath = isi kolom di database (path relatif / nama file).
     * Tampil di tempat (inline) secara bawaan; ?unduh=1 memaksa unduh dengan nama $namaUnduh.
     * Daftar lokasi yang dicoba sama dengan BerkasController dan PembayaranOptiController.
     */
    protected function kirimBerkas($relPath, $namaUnduh) {
        $relPath = ltrim((string) $relPath, "/\\");
        if ($relPath === '') { $this->f3->error(404, 'Berkas tidak tersedia.'); return; }
        $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $docRoot = rtrim(str_replace('\\', '/', (string) $this->f3->get('ROOT')), '/\\');
        $nama = basename($relPath);

        $kandidat = [
            $appRoot . '/' . $relPath,
            $appRoot . '/public/' . $relPath,
            $appRoot . '/storage/' . $relPath,
            $appRoot . '/storage/kesanggupan_bayar/' . $nama,
            $appRoot . '/storage/pembayaran/' . $nama,
            $appRoot . '/uploads/' . $nama,
            $appRoot . '/uploads/penawaran/' . $nama,
            $appRoot . '/uploads/bukti_bayar/' . $nama,
            $docRoot . '/' . $relPath,
            $docRoot . '/storage/pembayaran/' . $nama,
            $docRoot . '/uploads/bukti_bayar/' . $nama,
            $relPath,
        ];
        $path = null;
        foreach ($kandidat as $k) {
            if ($k !== '' && is_file($k)) { $path = $k; break; }
        }
        if (!$path) { $this->f3->error(404, 'Berkas tidak ditemukan di server.'); return; }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
                 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!isset($mime[$ext])) { $this->f3->error(404, 'Jenis berkas tidak didukung.'); return; }

        $unduh = (string) $this->f3->get('GET.unduh') === '1';
        $bersih = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $namaUnduh), '-') . '.' . $ext;
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: ' . $mime[$ext]);
        header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . $bersih . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}