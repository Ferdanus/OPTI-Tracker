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
        } catch (\Exception $e) {
            $errorMessage = 'Gagal memuat daftar PO: ' . $e->getMessage() . ' (sudah menjalankan ketiga file migration?)';
        }

        $f3->set('daftar_po_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('mode', $mode);
        $f3->set('error_message', $errorMessage);

        $this->render('katim_kerja/po-kegiatan/daftar.html', 'Daftar PO', 'po_daftar');
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
        list($po, $sisi) = $this->akses((int) $params['id']);
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
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
                "UPDATE po_revisi_catatan SET state = 'terbuka', ronde = ? WHERE po_id = ? AND tahap = 'review' AND state IN ('draf', 'terbuka')",
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
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengirim permintaan revisi.'], 500);
        }

        $nama = $this->namaPengguna($uid, 'Ketua Tim');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' meminta revisi (' . $jumlah . ' catatan).');
        self::kabari($this->db, [(int) $po['ketua_pelaksana_id']], 'Permintaan revisi PO', 'PO ' . $po['nomor_po'] . ': ' . $jumlah . ' catatan revisi dari Ketua Tim.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-kegiatan/@id/kirim-revisi -- Ketua Pelaksana mengirim revisi (semua catatan putaran ini sudah ditandai) */
    public function kirimRevisi($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
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
        self::kabari($this->db, self::idKatim($this->db, $po['jenis_layanan_opti']), 'Revisi PO masuk', 'PO ' . $po['nomor_po'] . ' sudah direvisi dan menunggu review ulang.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-kegiatan/@id/setujui -- Ketua Tim menyetujui PO. PO dikunci dan diteruskan ke Tim Mitra dan Humas. */
    public function setujui($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat menyetujui PO.');
        $this->wajibStatus($po, ['menunggu_review', 'direvisi'], 'PO hanya bisa disetujui saat menunggu review.');

        $n = $this->hitungCatatan((int) $po['id']);
        if ($n['draf'] > 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Ada ' . $n['draf'] . ' catatan draf yang belum dikirim. Kirim lewat Minta revisi atau hapus dulu.'], 422);
        }
        if ($n['terbuka'] > 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Masih ada ' . $n['terbuka'] . ' catatan yang belum diperbaiki.'], 422);
        }

        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan
                    SET status_review = 'disetujui', disetujui_at = NOW(), disetujui_by = ?, disposisi_humas_at = NOW(),
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

        $nama = $this->namaPengguna($uid, 'Ketua Tim');
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' menyetujui PO. PO terkunci dan diteruskan ke Humas untuk diverifikasi.');
        self::kabari($this->db, [(int) $po['ketua_pelaksana_id']], 'PO disetujui', 'PO ' . $po['nomor_po'] . ' disetujui Ketua Tim.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-kegiatan/@id/buka-kembali -- Ketua Tim membatalkan persetujuan; PO kembali ke antrean review. */
    public function bukaKembali($f3, $params) {
        list($po, $sisi) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat membuka kembali PO.');
        $this->wajibStatus($po, ['disetujui'], 'PO ini belum berstatus disetujui.');

        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan
                    SET status_review = 'menunggu_review', disetujui_at = NULL, disetujui_by = NULL, disposisi_humas_at = NULL,
                        reviewed_at = NOW(), reviewed_by = ?
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
        self::catatPesanSistem($this->db, $po['id'], $uid, 'katim', $nama . ' membuka kembali PO untuk direview. Persetujuan dan validasi dibatalkan.');
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
        self::kabari($this->db, $tujuan, 'Pesan baru di PO ' . $po['nomor_po'], $nama . ': ' . mb_substr($teks, 0, 90, 'UTF-8'));

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

    /**
     * GET /po-kegiatan/notif -- ringkasan pesan belum dibaca (satu baris per PO), untuk dropdown lonceng.
     * Pesan otomatis sistem tidak ikut dihitung (perubahan status sudah terlihat di daftar).
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
            } elseif ($role === 'ketua_tim' || $role === 'superadmin') {
                /* sql:belum_dibaca_katim */
                $sql = "SELECT COUNT(*) AS c FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id
                         WHERE p.status = 'terkirim'
                           AND ((p.status_review = 'menunggu_review' AND p.reviewed_at IS NULL)
                             OR (p.status_review = 'direvisi' AND p.revisi_dibaca_at IS NULL))";
                $prm = [];
                if ($role === 'ketua_tim' && in_array($divisi, self::DIVISI_VALID, true)) {
                    $sql .= " AND o.jenis_layanan_opti = ?";
                    $prm[1] = $divisi;
                }
                $r = $db->exec($sql, $prm);
            } else {
                return 0;
            }
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
        } catch (\Exception $e) {
            // catatan sistem bersifat pelengkap
        }
    }

    /** id_user semua Ketua Tim aktif untuk satu divisi (opti_user_map). */
    public static function idKatim($db, $divisi) {
        $hasil = [];
        try {
            $rows = $db->exec(
                "SELECT id_user FROM opti_user_map
                  WHERE role_opti = 'ketua_tim' AND is_active = 1 AND jenis_layanan_opti IN (?, 'semua')",
                [1 => (string) $divisi]
            );
            foreach ($rows as $r) { $hasil[] = (int) $r['id_user']; }
        } catch (\Exception $e) {
            // tanpa daftar Ketua Tim, notifikasi dilewati
        }
        return $hasil;
    }

    /** Kirim Web Push ke beberapa pengguna. Dilewati diam-diam kalau PushNotifikasi belum terpasang. */
    public static function kabari($db, array $userIds, $judul, $pesan) {
        if (!class_exists('PushNotifikasi')) { return; }
        $base = (string) \Base::instance()->get('BASE');
        $sudah = [];
        foreach ($userIds as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($sudah[$id])) { continue; }
            $sudah[$id] = true;
            try {
                \PushNotifikasi::kirim($db, $id, $judul, $pesan, $base . '/po-kegiatan/daftar', 'po-review');
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

    /** Ambil PO + order untuk pemeriksaan akses dan status. Null kalau tidak ada. */
    protected function ambilPo($poId) {
        /* sql:ambil_po */
        $sql = "SELECT p.id, p.nomor_po, p.status, p.status_review, p.reviewed_at, p.ronde_revisi,
                       p.minta_dibaca_at, p.revisi_dibaca_at,
                       o.id AS order_id, o.jenis_layanan_opti, o.ketua_pelaksana_id
                FROM po_kegiatan p
                JOIN order_layanan o ON o.id = p.order_id
                WHERE p.id = ? LIMIT 1";
        $rows = $this->db->exec($sql, [1 => (int) $poId]);
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
                FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review'";
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

        /* sql:daftar */
        $sql = "SELECT p.id, p.nomor_po, p.status_review, p.ronde_revisi,
                       COALESCE(p.dikirim_at, p.updated_at, p.created_at) AS dikirim_at,
                       p.reviewed_at AS dibaca_at, p.minta_at, p.minta_dibaca_at, p.revisi_at, p.revisi_dibaca_at, p.disetujui_at, p.created_at AS dibuat_at,
                       " . $this->kolomPosisiSql() . "
                       COALESCE(NULLIF(p.judul_kegiatan, ''), o.judul_kegiatan) AS judul,
                       o.nomor_order, o.jenis_layanan_opti, o.ketua_pelaksana_id,
                       c.nmcustomer AS nama_perusahaan, c.pt_cv,
                       (SELECT COUNT(*) FROM po_chat pc
                         WHERE pc.po_id = p.id AND pc.sisi = ? AND pc.sistem = 0 AND pc.dibaca_at IS NULL) AS chat_unread,
                       (SELECT COUNT(*) FROM po_revisi_catatan x
                         WHERE x.po_id = p.id AND x.tahap = 'review' AND x.ronde = p.ronde_revisi AND x.state <> 'draf') AS rn_total,
                       (SELECT COUNT(*) FROM po_revisi_catatan x
                         WHERE x.po_id = p.id AND x.tahap = 'review' AND x.ronde = p.ronde_revisi AND x.state = 'selesai') AS rn_selesai
                FROM po_kegiatan p
                JOIN order_layanan o ON o.id = p.order_id
                JOIN tb_customer c ON c.id_customer = o.id_customer
                WHERE (p.status = 'terkirim'
                       OR (p.status IN ('disetujui_mitra', 'disetujui') AND p.status_review = 'disetujui'))";
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
        foreach ($rows as $r) { $ids[] = $r['ketua_pelaksana_id']; }
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
                'status'           => (string) $r['status_review'],
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
                // catatan Tim Mitra baru ditampilkan setelah Tim Mitra memvalidasi PO
                'catatan_mitra'    => (!empty($r['mitra_valid_at']) && isset($r['catatan_tim_mitra'])) ? trim((string) $r['catatan_tim_mitra']) : '',
                // [BARU] revisi PPK BLU (hanya dipakai untuk teks "Status PO saat ini")
                'ppk_status'       => !empty($r['ppk_status']) ? (string) $r['ppk_status'] : '',
                'ronde_ppk'        => isset($r['ronde_ppk']) ? (int) $r['ronde_ppk'] : 0,
                'rn_total'         => (int) $r['rn_total'],
                'rn_selesai'       => (int) $r['rn_selesai'],
                'unread'           => (int) $r['chat_unread'],
            ];
        }
        return $daftar;
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
             . $ex('catatan_tim_mitra') . ' AS catatan_tim_mitra, '
             . $ex('ppk_status') . ' AS ppk_status, '
             . $ex('ronde_ppk') . ' AS ronde_ppk,';
    }

    /** Rincian satu PO + catatan, dalam satu paket JSON. Draf catatan hanya untuk sisi Ketua Tim. */
    protected function paketDetail($poId, $sisi) {
        $mode = ($sisi === 'katim') ? 'katim' : 'pembuat';
        try {
            $items = $this->daftarItems($mode, (int) $this->getUserId(), (int) $poId);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat PO.'], 500);
        }
        if (empty($items)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan atau belum dikirim.'], 404);
        }

        /* sql:daftar_catatan_katim */
        $sql = "SELECT id, bagian, teks, state, ronde, dibuat_oleh, created_at FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review' ORDER BY id ASC";
        /* sql:daftar_catatan_pembuat */
        $sqlPembuat = "SELECT id, bagian, teks, state, ronde, dibuat_oleh, created_at FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'review' AND state <> 'draf' ORDER BY id ASC";

        try {
            $rows = $this->db->exec(($sisi === 'katim') ? $sql : $sqlPembuat, [1 => (int) $poId]);
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
                'oleh'   => isset($peta[$idu]) ? $peta[$idu] : 'Ketua Tim',
                'waktu'  => (string) $r['created_at'],
            ];
        }
        return ['ok' => true, 'sisi' => $sisi, 'po' => $items[0], 'catatan' => $catatan];
    }

    /** id_user Ketua Tim pertama per divisi: ['selulosa' => id, 'lingkungan' => id] (0 = tidak ada). */
    protected function katimPerDivisi() {
        $peta = ['selulosa' => 0, 'lingkungan' => 0];
        try {
            $rows = $this->db->exec("SELECT id_user, jenis_layanan_opti FROM opti_user_map WHERE role_opti = 'ketua_tim' AND is_active = 1 ORDER BY id ASC");
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

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}