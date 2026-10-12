<?php
/**
 * PoRevisiPpkController
 *
 * Tahap PPK BLU pada alur PO, termasuk revisi dari PPK BLU.
 *
 * Urutan lengkap: Ketua Pelaksana buat PO -> Ketua Tim review/setujui (PoReviewController) -> Humas verifikasi
 *   -> Tim Mitra validasi -> PPK BLU (controller ini).
 *
 * PPK BLU mengecek PO dan RAB, lalu:
 *   - Validasi                                          -> selesai, PO terkunci
 *   - Minta revisi (catatan per bagian, tahap = 'ppk')  -> ke Ketua Tim
 * Ketua Tim memutuskan:
 *   - Kerjakan sendiri  -> mengedit PO, menandai catatan, mengirim LANGSUNG ke PPK BLU
 *   - Disposisi         -> Ketua Pelaksana mengedit PO, menandai catatan, mengirim ke Ketua Tim;
 *                          Ketua Tim menyetujui lalu mengirim ke PPK BLU, atau meminta perbaikan lagi (wajib alasan)
 * Berulang sampai PPK BLU memvalidasi. Tidak ada batas putaran (ronde_ppk dihitung).
 * Humas dan Tim Mitra tidak ikut di tahap ini. Mereka hanya melihat "Status PO saat ini".
 *
 * Status disimpan di po_kegiatan.ppk_status (NULL = di meja PPK BLU). status_review tetap 'disetujui'.
 *   state turunan: ppk_cek | ppk_minta | katim_kerja | ketupel_kerja | katim_review | selesai
 *
 * Halaman dan API (satu view: katim_kerja/po-kegiatan/revisi_ppk.html, mode 'katim' | 'pembuat' | 'ppk'):
 *   Ketua Tim / Ketua Pelaksana  GET /po-revisi-ppk ...      PPK BLU  GET /po-ppk ...   (lihat routes_po_revisi_ppk.php)
 *
 * Hook yang dipanggil controller lain (static):
 *   izinEdit()         PoKegiatanController::edit() dan update()  -> siapa boleh mengedit PO pada status sekarang
 *   sedangDirevisi()   PoKegiatanController::bind()               -> status PO jangan direset ke draft saat revisi
 *   catatPerubahan()   PoKegiatanController::update()             -> jejak "Perubahan sejak revisi diminta"
 *   hitungAksi()       Controller::render()                       -> angka badge menu
 *
 * Kompatibel PHP 7.2. Butuh: migration_po_revisi_ppk.sql (dan migration PoReviewController yang sudah ada).
 */
class PoRevisiPpkController extends Controller {

    /** Role yang dianggap PPK BLU (selain Superadmin). SESUAIKAN dengan role di sistem. */
    const ROLE_PPK = ['ppk_blu', 'ppk', 'pejabat'];

    const DIVISI_VALID  = ['selulosa', 'lingkungan'];
    const CATATAN_MAKS  = 500;
    const CATATAN_BATAS = 100;
    const ALASAN_MAKS   = 300;
    const LIMIT_DAFTAR  = 500;

    protected static $kolomCache = [];

    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /po-revisi-ppk -- Ketua Tim (dan Superadmin) + Ketua Pelaksana */
    public function indexRevisi($f3) {
        $this->requireAuth();
        $mode = $this->modeRevisi();
        if ($mode === null) {
            $f3->error(403, 'Halaman ini hanya untuk Ketua Tim dan Ketua Pelaksana.');
            return;
        }
        $this->tampilkan($f3, $mode, 'Revisi PPK BLU', 'po_revisi_ppk');
    }

    /** GET /po-ppk -- PPK BLU (dan Superadmin) */
    public function indexPpk($f3) {
        $this->requireAuth();
        if (!$this->izinPpk()) {
            $f3->error(403, 'Halaman ini hanya untuk PPK BLU.');
            return;
        }
        $this->tampilkan($f3, 'ppk', 'Validasi PPK BLU', 'po_ppk');
    }

    protected function tampilkan($f3, $mode, $judul, $menu) {
        $errorMessage = null;
        $daftar = [];
        try {
            $daftar = $this->daftarItems($mode, (int) $this->getUserId(), 0);
        } catch (\Exception $e) {
            $errorMessage = 'Gagal memuat daftar PO: ' . $e->getMessage() . ' (sudah menjalankan migration_po_revisi_ppk.sql?)';
        }
        $f3->set('daftar_po_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('mode', $mode);
        $f3->set('prefix_api', $mode === 'ppk' ? '/po-ppk' : '/po-revisi-ppk');
        $f3->set('error_message', $errorMessage);
        $this->render('katim_kerja/po-kegiatan/revisi_ppk.html', $judul, $menu);
    }

    /** GET /po-revisi-ppk/data */
    public function dataRevisi($f3) {
        $this->requireAuth();
        $mode = $this->modeRevisi();
        if ($mode === null) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Tidak ada akses.'], 403); }
        $this->keluarkanDaftar($mode);
    }

    /** GET /po-ppk/data */
    public function dataPpk($f3) {
        $this->requireAuth();
        if (!$this->izinPpk()) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Tidak ada akses.'], 403); }
        $this->keluarkanDaftar('ppk');
    }

    protected function keluarkanDaftar($mode) {
        try {
            $daftar = $this->daftarItems($mode, (int) $this->getUserId(), 0);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat daftar PO.'], 500);
        }
        $this->keluarkanJson(['ok' => true, 'daftar' => $daftar]);
    }

    /** GET /po-revisi-ppk/@id/detail */
    public function detailRevisi($f3, $params) {
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        if (!$this->isSuperadmin()) { $this->tandaiDibaca($po, $sisi); }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** GET /po-ppk/@id/detail */
    public function detailPpk($f3, $params) {
        $po = $this->aksesPpk((int) $params['id']);
        if (!$this->isSuperadmin()) { $this->tandaiDibaca($po, 'ppk'); }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'ppk'));
    }

    /* ============================================================
     * AKSI KETUA TIM
     * ============================================================ */

    /** POST /po-revisi-ppk/@id/kerjakan -- Ketua Tim mengerjakan sendiri */
    public function kerjakan($f3, $params) {
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat memutuskan.');
        $this->wajibState($po, ['ppk_minta'], 'PO ini tidak sedang menunggu keputusan Ketua Tim.');

        $this->gantiStatus($po, ['minta'], 'katim_kerja');
        $this->pesan($po, $uid, 'katim', 'Ketua Tim', 'mengerjakan revisi PPK BLU sendiri.');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-revisi-ppk/@id/disposisi -- Ketua Tim mendisposisikan ke Ketua Pelaksana (juga membatalkan "kerjakan sendiri") */
    public function disposisi($f3, $params) {
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat mendisposisikan.');
        $this->wajibState($po, ['ppk_minta', 'katim_kerja'], 'PO ini tidak bisa didisposisikan pada status sekarang.');

        $this->gantiStatus($po, ['minta', 'katim_kerja'], 'ketupel_kerja');
        $this->pesan($po, $uid, 'katim', 'Ketua Tim', 'mendisposisikan revisi PPK BLU ke Ketua Pelaksana.');
        $this->kabariKe([(int) $po['ketua_pelaksana_id']], 'Revisi PPK BLU untuk Anda', 'PO ' . $po['nomor_po'] . ' didisposisikan Ketua Tim untuk direvisi sesuai catatan PPK BLU.', '/po-revisi-ppk');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-revisi-ppk/@id/kirim-ppk -- Ketua Tim (kerja sendiri) mengirim langsung ke PPK BLU */
    public function kirimPpk($f3, $params) {
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat mengirim ke PPK BLU.');
        $this->wajibState($po, ['katim_kerja'], 'PO ini tidak sedang Anda kerjakan.');
        $this->wajibCatatanSelesai($po);

        $this->gantiStatus($po, ['katim_kerja'], null);
        $this->pesan($po, $uid, 'katim', 'Ketua Tim', 'mengirim revisi ke PPK BLU.');
        $this->kabariKe(self::idPpk($this->db), 'Revisi PO masuk', 'PO ' . $po['nomor_po'] . ' sudah direvisi dan menunggu validasi ulang.', '/po-ppk');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-revisi-ppk/@id/setujui-kirim -- Ketua Tim menyetujui hasil revisi Ketua Pelaksana dan mengirim ke PPK BLU */
    public function setujuKirim($f3, $params) {
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat menyetujui revisi.');
        $this->wajibState($po, ['katim_review'], 'PO ini tidak sedang menunggu review Ketua Tim.');

        $this->gantiStatus($po, ['katim_review'], null);
        $this->pesan($po, $uid, 'katim', 'Ketua Tim', 'menyetujui revisi dan mengirim ke PPK BLU.');
        $this->kabariKe(self::idPpk($this->db), 'Revisi PO masuk', 'PO ' . $po['nomor_po'] . ' sudah direvisi dan menunggu validasi ulang.', '/po-ppk');
        $this->kabariKe([(int) $po['ketua_pelaksana_id']], 'Revisi disetujui', 'PO ' . $po['nomor_po'] . ': revisi disetujui Ketua Tim dan dikirim ke PPK BLU.', '/po-kegiatan/daftar');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-revisi-ppk/@id/minta-lagi -- Ketua Tim mengembalikan ke Ketua Pelaksana. Field: alasan (wajib) */
    public function mintaLagi($f3, $params) {
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'katim', 'Hanya Ketua Tim yang dapat meminta perbaikan lagi.');
        $this->wajibState($po, ['katim_review'], 'PO ini tidak sedang menunggu review Ketua Tim.');

        $alasan = trim((string) $f3->get('POST.alasan'));
        if ($alasan === '') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Alasan wajib diisi.'], 422);
        }
        if (mb_strlen($alasan, 'UTF-8') > self::ALASAN_MAKS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Alasan terlalu panjang (maksimal ' . self::ALASAN_MAKS . ' karakter).'], 422);
        }

        try {
            $this->db->begin();
            // catatan putaran ini dibuka lagi supaya Ketua Pelaksana menandai ulang
            $this->db->exec(
                "UPDATE po_revisi_catatan SET state = 'terbuka', selesai_oleh = NULL, selesai_at = NULL
                  WHERE po_id = ? AND tahap = 'ppk' AND ronde = ? AND state = 'selesai'",
                [1 => (int) $po['id'], 2 => (int) $po['ronde_ppk']]
            );
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET ppk_status = 'ketupel_kerja', ppk_status_at = NOW(), ppk_alasan = ?, ppk_dibaca_at = NULL
                  WHERE id = ? AND status_review = 'disetujui' AND ppk_status = 'katim_review'",
                [1 => $alasan, 2 => (int) $po['id']]
            );
            if ((int) $diubah === 0) {
                $this->db->rollback();
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengembalikan PO.'], 500);
        }

        $this->pesan($po, $uid, 'katim', 'Ketua Tim', 'meminta perbaikan lagi: ' . $alasan);
        $this->kabariKe([(int) $po['ketua_pelaksana_id']], 'Perbaikan lagi diminta', 'PO ' . $po['nomor_po'] . ': ' . mb_substr($alasan, 0, 90, 'UTF-8'), '/po-revisi-ppk');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /* ============================================================
     * AKSI KETUA PELAKSANA
     * ============================================================ */

    /** POST /po-revisi-ppk/@id/kirim-katim -- Ketua Pelaksana mengirim hasil revisi ke Ketua Tim */
    public function kirimKatim($f3, $params) {
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibSisi($sisi, 'pembuat', 'Hanya Ketua Pelaksana yang dapat mengirim revisi ke Ketua Tim.');
        $this->wajibState($po, ['ketupel_kerja'], 'PO ini tidak sedang didisposisikan kepada Anda.');
        $this->wajibCatatanSelesai($po);

        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET ppk_status = 'katim_review', ppk_status_at = NOW(), ppk_alasan = NULL, ppk_dibaca_at = NULL
                  WHERE id = ? AND status_review = 'disetujui' AND ppk_status = 'ketupel_kerja'",
                [1 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengirim revisi.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
        }

        $this->pesan($po, $uid, 'pembuat', 'Ketua Pelaksana', 'mengirim revisi PPK BLU ke Ketua Tim.');
        $this->kabariKe(self::idKatim($this->db, $po['jenis_layanan_opti']), 'Hasil revisi PPK BLU masuk', 'PO ' . $po['nomor_po'] . ' sudah direvisi Ketua Pelaksana, menunggu review Anda.', '/po-revisi-ppk');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /**
     * POST /po-revisi-ppk/@id/catatan/@nid -- tandai catatan PPK BLU. Field: aksi = selesai | batalselesai
     * Ketua Tim saat katim_kerja, atau Ketua Pelaksana saat ketupel_kerja; hanya catatan putaran yang berjalan.
     */
    public function catatanAksi($f3, $params) {
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $nid  = (int) $params['nid'];
        $aksi = trim((string) $f3->get('POST.aksi'));

        $state = $this->stateDari($po);
        $boleh = ($sisi === 'katim' && $state === 'katim_kerja') || ($sisi === 'pembuat' && $state === 'ketupel_kerja');
        if (!$boleh) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan hanya bisa ditandai oleh yang sedang mengerjakan revisi.'], 403);
        }

        try {
            $rows = $this->db->exec(
                "SELECT id, state, ronde FROM po_revisi_catatan WHERE id = ? AND po_id = ? AND tahap = 'ppk' LIMIT 1",
                [1 => $nid, 2 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca catatan.'], 500);
        }
        if (empty($rows)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan tidak ditemukan.'], 404); }
        $c = $rows[0];
        if ((int) $c['ronde'] !== (int) $po['ronde_ppk']) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini bukan dari putaran revisi yang berjalan.'], 422);
        }

        $uid = (int) $this->getUserId();
        if ($aksi === 'selesai') {
            if ($c['state'] !== 'terbuka') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini tidak sedang terbuka.'], 422); }
            $sql = "UPDATE po_revisi_catatan SET state = 'selesai', selesai_oleh = ?, selesai_at = NOW() WHERE id = ? AND po_id = ? AND state = 'terbuka'";
            $prm = [1 => $uid, 2 => $nid, 3 => (int) $po['id']];
        } elseif ($aksi === 'batalselesai') {
            if ($c['state'] !== 'selesai') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan ini belum ditandai diperbaiki.'], 422); }
            $sql = "UPDATE po_revisi_catatan SET state = 'terbuka', selesai_oleh = NULL, selesai_at = NULL WHERE id = ? AND po_id = ? AND state = 'selesai'";
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

    /** GET /po-revisi-ppk/@id/catatan-form -- catatan yang harus dikerjakan, untuk panel di form Edit PO */
    public function catatanForm($f3, $params) {
        $this->requireAuth();
        $poId = (int) $params['id'];
        $uid = (int) $this->getUserId();
        list($boleh, $pesan, $konteks) = self::izinEdit($this->db, $poId, (string) $this->getUserRole(), $this->isSuperadmin(), $uid);
        if (!$boleh) { $this->keluarkanJson(['ok' => false, 'pesan' => $pesan], 403); }
        if ($konteks === '') { $this->keluarkanJson(['ok' => true, 'konteks' => '', 'catatan' => []]); }

        try {
            if ($konteks === 'ppk') {
                $rows = $this->db->exec(
                    "SELECT c.id, c.bagian, c.teks, c.state, c.ronde, c.created_at FROM po_revisi_catatan c
                       JOIN po_kegiatan p ON p.id = c.po_id
                      WHERE c.po_id = ? AND c.tahap = 'ppk' AND c.state <> 'draf' AND c.ronde = p.ronde_ppk ORDER BY c.id ASC",
                    [1 => $poId]
                );
            } else {
                $rows = $this->db->exec(
                    "SELECT c.id, c.bagian, c.teks, c.state, c.ronde, c.created_at FROM po_revisi_catatan c
                       JOIN po_kegiatan p ON p.id = c.po_id
                      WHERE c.po_id = ? AND c.tahap = 'review' AND c.state <> 'draf' AND c.ronde = p.ronde_revisi ORDER BY c.id ASC",
                    [1 => $poId]
                );
            }
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat catatan.'], 500);
        }

        $catatan = [];
        foreach ($rows as $r) {
            $catatan[] = ['id' => (int) $r['id'], 'bagian' => (string) $r['bagian'], 'teks' => (string) $r['teks'], 'state' => (string) $r['state']];
        }
        $this->keluarkanJson(['ok' => true, 'konteks' => $konteks, 'catatan' => $catatan]);
    }

    /* ============================================================
     * AKSI PPK BLU
     * ============================================================ */

    /** POST /po-ppk/@id/catatan -- PPK BLU menambah catatan (draf). Field: bagian, teks */
    public function catatanTambah($f3, $params) {
        $po = $this->aksesPpk((int) $params['id']);
        $this->wajibState($po, ['ppk_cek'], 'Catatan hanya bisa ditambah saat PO berada di PPK BLU.');

        $bagian = trim((string) $f3->get('POST.bagian'));
        $teks   = trim((string) $f3->get('POST.teks'));
        if (!preg_match('/^[a-z0-9_-]{1,40}$/', $bagian)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Bagian PO tidak valid.'], 422);
        }
        if ($teks === '') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan tidak boleh kosong.'], 422); }
        if (mb_strlen($teks, 'UTF-8') > self::CATATAN_MAKS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Catatan terlalu panjang (maksimal ' . self::CATATAN_MAKS . ' karakter).'], 422);
        }
        $n = $this->hitungCatatan((int) $po['id'], 'ppk');
        if ($n['draf'] + $n['terbuka'] + $n['selesai'] >= self::CATATAN_BATAS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Jumlah catatan pada PO ini sudah mencapai batas ' . self::CATATAN_BATAS . '.'], 422);
        }

        try {
            $this->db->exec(
                "INSERT INTO po_revisi_catatan (po_id, tahap, bagian, teks, state, ronde, dibuat_oleh, created_at)
                 VALUES (?, 'ppk', ?, ?, 'draf', 0, ?, NOW())",
                [1 => (int) $po['id'], 2 => $bagian, 3 => $teks, 4 => (int) $this->getUserId()]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan catatan. Sudah menjalankan migration_po_revisi_ppk.sql?'], 500);
        }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'ppk'));
    }

    /** POST /po-ppk/@id/catatan/@nid -- hapus draf catatan. Field: aksi = hapus */
    public function catatanHapus($f3, $params) {
        $po = $this->aksesPpk((int) $params['id']);
        $nid = (int) $params['nid'];
        if (trim((string) $f3->get('POST.aksi')) !== 'hapus') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Aksi tidak dikenal.'], 422);
        }
        try {
            $diubah = $this->db->exec(
                "DELETE FROM po_revisi_catatan WHERE id = ? AND po_id = ? AND tahap = 'ppk' AND state = 'draf'",
                [1 => $nid, 2 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menghapus catatan.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Hanya catatan draf yang bisa dihapus.'], 422);
        }
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'ppk'));
    }

    /** POST /po-ppk/@id/minta-revisi -- kirim semua draf ke Ketua Tim */
    public function mintaRevisi($f3, $params) {
        $po = $this->aksesPpk((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibState($po, ['ppk_cek'], 'Revisi hanya bisa diminta saat PO berada di PPK BLU.');

        $n = $this->hitungCatatan((int) $po['id'], 'ppk');
        if ($n['draf'] < 1) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Belum ada catatan untuk dikirim. Tambahkan catatan dulu.'], 422);
        }

        $ronde = (int) $po['ronde_ppk'] + 1;
        try {
            $this->db->begin();
            $this->db->exec(
                "UPDATE po_revisi_catatan SET state = 'terbuka', ronde = ? WHERE po_id = ? AND tahap = 'ppk' AND state = 'draf'",
                [1 => $ronde, 2 => (int) $po['id']]
            );
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan
                    SET ppk_status = 'minta', ronde_ppk = ?, ppk_minta_at = NOW(), ppk_status_at = NOW(), ppk_alasan = NULL, ppk_dibaca_at = NULL
                  WHERE id = ? AND status_review = 'disetujui' AND ppk_status IS NULL AND validasi_keuangan_at IS NULL",
                [1 => $ronde, 2 => (int) $po['id']]
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

        $this->pesan($po, $uid, 'katim', 'PPK BLU', 'meminta revisi (' . $n['draf'] . ' catatan). Menunggu keputusan Ketua Tim.');
        $this->kabariKe(self::idKatim($this->db, $po['jenis_layanan_opti']), 'PPK BLU meminta revisi', 'PO ' . $po['nomor_po'] . ': ' . $n['draf'] . ' catatan dari PPK BLU. Tentukan siapa yang mengerjakan.', '/po-revisi-ppk');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'ppk'));
    }

    /** POST /po-ppk/@id/validasi -- PPK BLU memvalidasi. PO selesai dan terkunci. */
    public function validasi($f3, $params) {
        $po = $this->aksesPpk((int) $params['id']);
        $uid = (int) $this->getUserId();
        $this->wajibState($po, ['ppk_cek'], 'PO hanya bisa divalidasi saat berada di PPK BLU.');

        $n = $this->hitungCatatan((int) $po['id'], 'ppk');
        if ($n['draf'] > 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Ada ' . $n['draf'] . ' catatan draf yang belum dikirim. Kirim lewat Minta revisi atau hapus dulu.'], 422);
        }

        $set = 'validasi_keuangan_at = NOW()';
        $prm = [];
        $i = 1;
        if ($this->kolomAda('validasi_keuangan_by')) { $set .= ', validasi_keuangan_by = ?'; $prm[$i++] = $uid; }
        $prm[$i++] = (int) $po['id'];
        try {
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET " . $set . "
                  WHERE id = ? AND status_review = 'disetujui' AND ppk_status IS NULL AND validasi_keuangan_at IS NULL",
                $prm
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan validasi.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
        }

        $this->pesan($po, $uid, 'katim', 'PPK BLU', 'memvalidasi PO.');
        $tujuan = self::idKatim($this->db, $po['jenis_layanan_opti']);
        $tujuan[] = (int) $po['ketua_pelaksana_id'];
        $this->kabariKe($tujuan, 'PO divalidasi PPK BLU', 'PO ' . $po['nomor_po'] . ' sudah divalidasi PPK BLU.', '/po-kegiatan/daftar');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'ppk'));
    }

    /* ============================================================
     * HOOK UNTUK CONTROLLER LAIN (static)
     * ============================================================ */

    /**
     * Siapa boleh mengedit PO sekarang? Dipakai di PoKegiatanController::edit() dan update().
     * @return array [bool boleh, string pesan, string konteks]  konteks: '' (draf) | 'review' | 'ppk'
     */
    public static function izinEdit($db, $poId, $role, $superadmin, $userId) {
        $r = null;
        try {
            $rows = $db->exec(
                "SELECT p.status, p.status_review, p.ppk_status, o.ketua_pelaksana_id
                   FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id WHERE p.id = ?",
                [1 => (int) $poId]
            );
            $r = !empty($rows) ? $rows[0] : null;
        } catch (\Exception $e) {
            try {   // kolom ppk_status belum ada (migration belum jalan)
                $rows = $db->exec(
                    "SELECT p.status, p.status_review, NULL AS ppk_status, o.ketua_pelaksana_id
                       FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id WHERE p.id = ?",
                    [1 => (int) $poId]
                );
                $r = !empty($rows) ? $rows[0] : null;
            } catch (\Exception $e2) {
                return [true, '', ''];   // jangan memblokir editing kalau tabel tidak terbaca
            }
        }
        if (!$r) { return [false, 'PO tidak ditemukan.', '']; }
        if (!in_array($r['status'], ['terkirim', 'disetujui_mitra', 'disetujui'], true)) { return [true, '', '']; }   // draf biasa; PO yang sudah divalidasi Tim Mitra statusnya 'disetujui_mitra'

        $pembuat = ((int) $userId > 0 && (int) $userId === (int) $r['ketua_pelaksana_id']);
        $sr = (string) $r['status_review'];
        $ps = (string) $r['ppk_status'];

        if ($sr === 'perlu_revisi') {
            return [true, '', 'review'];   // sama seperti PoReviewController::bolehEdit(): aturan lama tidak diubah
        }
        if ($sr === 'disetujui') {
            if ($ps === 'katim_kerja') {
                if ($superadmin || $role === 'ketua_tim') { return [true, '', 'ppk']; }
                return [false, 'Revisi PPK BLU sedang dikerjakan Ketua Tim.', ''];
            }
            if ($ps === 'ketupel_kerja') {
                if ($superadmin || $pembuat) { return [true, '', 'ppk']; }
                return [false, 'Revisi PPK BLU sedang dikerjakan Ketua Pelaksana pembuat PO.', ''];
            }
            return [false, 'PO sudah disetujui dan dikunci. Perubahan hanya bisa lewat revisi dari PPK BLU, atau minta Ketua Tim membuka kembali.', ''];
        }
        return [false, 'PO sedang direview Ketua Tim. PO baru bisa diedit setelah Ketua Tim meminta revisi.', ''];
    }

    /** True kalau PO sedang direvisi (review biasa atau PPK BLU). Dipakai bind(): status PO jangan balik ke draft. */
    public static function sedangDirevisi($db, $poId) {
        try {
            $rows = $db->exec("SELECT status_review, ppk_status FROM po_kegiatan WHERE id = ?", [1 => (int) $poId]);
        } catch (\Exception $e) {
            try {
                $rows = $db->exec("SELECT status_review, NULL AS ppk_status FROM po_kegiatan WHERE id = ?", [1 => (int) $poId]);
            } catch (\Exception $e2) {
                return false;
            }
        }
        if (empty($rows)) { return false; }
        return $rows[0]['status_review'] === 'perlu_revisi' || in_array((string) $rows[0]['ppk_status'], ['katim_kerja', 'ketupel_kerja'], true);
    }

    /**
     * Catat perubahan isi PO selama revisi PPK BLU. Panggil di PoKegiatanController::update() setelah save().
     * $sebelum / $sesudah = hasil siapkanDataPo() sebelum bind dan sesudah save.
     * Tidak melakukan apa-apa kalau PO tidak sedang dikerjakan untuk revisi PPK BLU.
     */
    public static function catatPerubahan($db, $poId, $userId, array $sebelum, array $sesudah) {
        try {
            $rows = $db->exec("SELECT ppk_status, ronde_ppk FROM po_kegiatan WHERE id = ?", [1 => (int) $poId]);
            if (empty($rows) || !in_array((string) $rows[0]['ppk_status'], ['katim_kerja', 'ketupel_kerja'], true)) { return; }
            $ronde = (int) $rows[0]['ronde_ppk'];
            $oleh  = $rows[0]['ppk_status'] === 'katim_kerja' ? 'Ketua Tim' : 'Ketua Pelaksana';

            $norm = function ($v) { return is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : trim((string) $v); };
            $beda = function (array $kunci) use ($sebelum, $sesudah, $norm) {
                foreach ($kunci as $k) {
                    $a = isset($sebelum[$k]) ? $norm($sebelum[$k]) : '';
                    $b = isset($sesudah[$k]) ? $norm($sesudah[$k]) : '';
                    if ($a !== $b) { return true; }
                }
                return false;
            };
            $rp = function ($n) { return 'Rp ' . number_format((float) $n, 0, ',', '.'); };

            $baris = [];   // [bagian, ringkasan]
            $label = ['dasar' => 'Dasar', 'tujuan' => 'Tujuan', 'lingkup' => 'Ruang Lingkup', 'alat' => 'Alat dan Bahan', 'metoda' => 'Metoda', 'alamat' => 'Alamat', 'tim' => 'Tim Pelaksana'];
            $peta  = ['dasar' => ['dasar'], 'tujuan' => ['tujuan'], 'lingkup' => ['ruang_lingkup'],
                      'alat' => ['alat_bahan', 'bahan_kimia', 'kebutuhan_tambahan'], 'metoda' => ['metoda'], 'alamat' => ['alamat'], 'tim' => ['tim_pelaksana']];
            foreach ($peta as $bagian => $kunci) {
                if ($beda($kunci)) { $baris[] = [$bagian, $label[$bagian] . ' diubah']; }
            }

            // Jadwal
            if ($beda(['jadwal_mulai', 'jadwal_selesai', 'jadwal'])) {
                $f = function ($d) { return (isset($d['jadwal_mulai']) && $d['jadwal_mulai'] ? $d['jadwal_mulai'] : '-') . ' s/d ' . (isset($d['jadwal_selesai']) && $d['jadwal_selesai'] ? $d['jadwal_selesai'] : '-'); };
                $baris[] = ['jadwal', $beda(['jadwal_mulai', 'jadwal_selesai']) ? 'Jadwal diubah: ' . $f($sebelum) . ' menjadi ' . $f($sesudah) : 'Jadwal (rincian kegiatan) diubah'];
            }

            // RAB: bandingkan angka hasil hitung per kategori
            if ($beda(['rab'])) {
                $ra = isset($sebelum['rab_hitung']) && is_array($sebelum['rab_hitung']) ? $sebelum['rab_hitung'] : [];
                $rb = isset($sesudah['rab_hitung']) && is_array($sesudah['rab_hitung']) ? $sesudah['rab_hitung'] : [];
                $n0 = count($baris);
                $ta = isset($ra['total_penerimaan']) ? (float) $ra['total_penerimaan'] : 0;
                $tb = isset($rb['total_penerimaan']) ? (float) $rb['total_penerimaan'] : 0;
                if (round($ta) !== round($tb)) { $baris[] = ['rab', 'Nilai kontrak (penerimaan) ' . $rp($ta) . ' menjadi ' . $rp($tb)]; }
                $ka = []; $kb = [];
                foreach ((isset($ra['kategori']) ? $ra['kategori'] : []) as $k) { $ka[(string) ($k['nama'] ?? '')] = (float) ($k['subtotal'] ?? 0); }
                foreach ((isset($rb['kategori']) ? $rb['kategori'] : []) as $k) { $kb[(string) ($k['nama'] ?? '')] = (float) ($k['subtotal'] ?? 0); }
                foreach ($kb as $nama => $sub) {
                    if (!isset($ka[$nama])) { $baris[] = ['rab', 'Kategori biaya ditambah: ' . $nama . ' ' . $rp($sub)]; }
                    elseif (round($ka[$nama]) !== round($sub)) { $baris[] = ['rab', 'Biaya ' . $nama . ' ' . $rp($ka[$nama]) . ' menjadi ' . $rp($sub)]; }
                }
                foreach ($ka as $nama => $sub) {
                    if (!isset($kb[$nama])) { $baris[] = ['rab', 'Kategori biaya dihapus: ' . $nama . ' ' . $rp($sub)]; }
                }
                $pa = isset($ra['total_pengeluaran']) ? (float) $ra['total_pengeluaran'] : 0;
                $pb = isset($rb['total_pengeluaran']) ? (float) $rb['total_pengeluaran'] : 0;
                if (round($pa) !== round($pb)) { $baris[] = ['rab', 'Total biaya ' . $rp($pa) . ' menjadi ' . $rp($pb)]; }
                if (count($baris) === $n0) { $baris[] = ['rab', 'RAB diubah (rincian item)']; }
            }

            if (empty($baris)) { return; }
            $baris = array_slice($baris, 0, 25);
            $bagianUnik = [];
            foreach ($baris as $b) {
                $db->exec(
                    "INSERT INTO po_ppk_perubahan (po_id, ronde_ppk, bagian, ringkasan, oleh, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                    [1 => (int) $poId, 2 => $ronde, 3 => $b[0], 4 => mb_substr($b[1], 0, 500, 'UTF-8'), 5 => (int) $userId]
                );
                $bagianUnik[$b[0]] = true;
            }
            $teks = 'Perubahan PO disimpan (' . $oleh . '): ' . implode(', ', array_map(function ($k) use ($label) { return $k === 'rab' ? 'RAB' : (isset($label[$k]) ? $label[$k] : ucfirst($k)); }, array_keys($bagianUnik))) . '.';
            \PoReviewController::catatPesanSistem($db, (int) $poId, (int) $userId, $oleh === 'Ketua Tim' ? 'katim' : 'pembuat', $teks);
        } catch (\Throwable $e) {
            // jejak perubahan bersifat pelengkap, jangan gagalkan penyimpanan PO
        }
    }

    /**
     * Angka badge menu.
     *   PPK BLU    : PO di meja PPK BLU (validasi pertama maupun ulang)
     *   Ketua Tim  : menunggu keputusan + sedang dikerjakan sendiri + menunggu review hasil revisi
     *   Ketua Pelaksana: revisi PPK BLU yang didisposisikan kepadanya
     * Pasang di Controller::render():
     *   $this->f3->set('jumlah_notif_revisi_ppk', \PoRevisiPpkController::hitungAksi($this->db, $role, $userId, $layanan));
     */
    public static function hitungAksi($db, $role, $userId, $divisi) {
        try {
            if ($role === 'tim_kerja') {
                $r = $db->exec(
                    "SELECT COUNT(*) AS c FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id
                      WHERE p.status IN ('terkirim', 'disetujui_mitra', 'disetujui') AND p.status_review = 'disetujui' AND p.ppk_status = 'ketupel_kerja' AND o.ketua_pelaksana_id = ?",
                    [1 => (int) $userId]
                );
            } elseif ($role === 'ketua_tim' || $role === 'superadmin') {
                $sql = "SELECT COUNT(*) AS c FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id
                         WHERE p.status IN ('terkirim', 'disetujui_mitra', 'disetujui') AND p.status_review = 'disetujui' AND p.ppk_status IN ('minta', 'katim_kerja', 'katim_review')";
                $prm = [];
                if ($role === 'ketua_tim' && in_array($divisi, self::DIVISI_VALID, true)) { $sql .= " AND o.jenis_layanan_opti = ?"; $prm[1] = $divisi; }
                $r = $db->exec($sql, $prm);
            } elseif (in_array($role, self::ROLE_PPK, true)) {
                $sql = "SELECT COUNT(*) AS c FROM po_kegiatan p
                         WHERE p.status IN ('terkirim', 'disetujui_mitra', 'disetujui') AND p.status_review = 'disetujui' AND p.ppk_status IS NULL AND p.validasi_keuangan_at IS NULL";
                foreach (self::kolomSyarat($db) as $k) { $sql .= " AND p." . $k . " IS NOT NULL"; }
                $r = $db->exec($sql);
            } else {
                return 0;
            }
            return isset($r[0]['c']) ? (int) $r[0]['c'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** id_user PPK BLU aktif (opti_user_map). Kosong = notifikasi dilewati. */
    public static function idPpk($db) {
        $hasil = [];
        try {
            $rows = $db->exec("SELECT id_user FROM opti_user_map WHERE role_opti IN ('ppk_blu', 'ppk', 'pejabat') AND is_active = 1");
            foreach ($rows as $r) { $hasil[] = (int) $r['id_user']; }
        } catch (\Exception $e) {
            // tanpa daftar PPK BLU, notifikasi dilewati
        }
        return $hasil;
    }

    /** id_user Ketua Tim aktif per divisi (sama dengan PoReviewController::idKatim). */
    public static function idKatim($db, $divisi) {
        return \PoReviewController::idKatim($db, $divisi);
    }

    /**
     * Kolom po_kegiatan yang WAJIB terisi sebelum PO sampai di meja PPK BLU: verifikasi Humas dan validasi Tim Mitra.
     * Hanya kolom yang ada di database yang dipakai (nama kolom Tim Mitra: tervalidasi_mitra_at, atau tervalidasi_keuangan_at pada skema lama).
     */
    public static function kolomSyarat($db) {
        $hasil = [];
        if (self::adaKolom($db, 'verifikasi_humas_at')) { $hasil[] = 'verifikasi_humas_at'; }
        if (self::adaKolom($db, 'tervalidasi_mitra_at')) { $hasil[] = 'tervalidasi_mitra_at'; }
        elseif (self::adaKolom($db, 'tervalidasi_keuangan_at')) { $hasil[] = 'tervalidasi_keuangan_at'; }
        return $hasil;
    }

    /* ============================================================
     * HELPER: AKSES DAN STATUS
     * ============================================================ */

    /** 'katim' | 'pembuat' | null */
    protected function modeRevisi() {
        if ($this->isSuperadmin()) { return 'katim'; }
        $role = $this->getUserRole();
        if ($role === 'ketua_tim') { return 'katim'; }
        if ($role === 'tim_kerja') { return 'pembuat'; }
        return null;
    }

    protected function izinPpk() {
        return $this->isSuperadmin() || in_array($this->getUserRole(), self::ROLE_PPK, true);
    }

    protected function divisiKatim() {
        if ($this->isSuperadmin()) { return ''; }
        $d = isset($_SESSION['jenis_layanan_opti']) ? (string) $_SESSION['jenis_layanan_opti'] : '';
        return in_array($d, self::DIVISI_VALID, true) ? $d : '';
    }

    protected function ambilPo($poId) {
        $sql = "SELECT p.id, p.nomor_po, p.status, p.status_review, p.ppk_status, p.ronde_ppk, p.validasi_keuangan_at,
                       o.id AS order_id, o.jenis_layanan_opti, o.ketua_pelaksana_id
                FROM po_kegiatan p
                JOIN order_layanan o ON o.id = p.order_id
                WHERE p.id = ? LIMIT 1";
        $rows = $this->db->exec($sql, [1 => (int) $poId]);
        return !empty($rows) ? $rows[0] : null;
    }

    /** Wajib login + sisi Ketua Tim / Ketua Pelaksana pada PO ini. Return [$po, $sisi] atau JSON error. */
    protected function aksesRevisi($poId) {
        $this->requireAuth();
        $po = $this->muatPo($poId);
        $mode = $this->modeRevisi();
        $sisi = null;
        if ($mode === 'katim') {
            $div = $this->divisiKatim();
            $sisi = ($div === '' || $div === $po['jenis_layanan_opti']) ? 'katim' : null;
        } elseif ($mode === 'pembuat') {
            $uid = (int) $this->getUserId();
            $sisi = ($uid > 0 && $uid === (int) $po['ketua_pelaksana_id']) ? 'pembuat' : null;
        }
        if ($sisi === null) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke PO ini.'], 403); }
        return [$po, $sisi];
    }

    /** Wajib login + PPK BLU. Return $po atau JSON error. */
    protected function aksesPpk($poId) {
        $this->requireAuth();
        if (!$this->izinPpk()) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke halaman ini.'], 403); }
        return $this->muatPo($poId);
    }

    protected function muatPo($poId) {
        try {
            $po = ($poId > 0) ? $this->ambilPo($poId) : null;
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca data PO. Sudah menjalankan migration_po_revisi_ppk.sql? Detail: ' . $e->getMessage()], 500);
        }
        if (!$po) { $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan.'], 404); }
        if (!in_array($po['status'], ['terkirim', 'disetujui_mitra', 'disetujui'], true) || $po['status_review'] !== 'disetujui') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini belum disetujui Ketua Tim.'], 422);
        }
        return $po;
    }

    /** Siapa penerima kiriman pada state ini ('ppk' | 'katim' | 'pembuat'), null kalau tidak ada yang perlu "membaca". */
    protected static function penerimaDari($state) {
        $peta = ['ppk_cek' => 'ppk', 'ppk_minta' => 'katim', 'ketupel_kerja' => 'pembuat', 'katim_review' => 'katim'];
        return isset($peta[$state]) ? $peta[$state] : null;
    }

    /**
     * Catat "sudah dibaca" ketika penerima kiriman yang sedang berlaku membuka rincian PO (sekali per kiriman).
     * Penanda di-NULL-kan setiap PO berpindah tangan (gantiStatus / mintaRevisi). Superadmin tidak menandai.
     */
    protected function tandaiDibaca(array $po, $sisi) {
        try {
            $state = $this->stateDari($po);
            if (self::penerimaDari($state) !== $sisi) { return; }
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan SET ppk_dibaca_at = NOW()
                  WHERE id = ? AND ppk_dibaca_at IS NULL AND validasi_keuangan_at IS NULL AND ppk_status <=> ?",
                [1 => (int) $po['id'], 2 => ($po['ppk_status'] !== null && $po['ppk_status'] !== '') ? $po['ppk_status'] : null]
            );
            if ((int) $diubah > 0) {
                $peran = ['ppk' => 'PPK BLU', 'katim' => 'Ketua Tim', 'pembuat' => 'Ketua Pelaksana'][$sisi];
                $apa = ['ppk_cek' => ((int) $po['ronde_ppk'] > 0 ? 'revisi dari Ketua Tim' : 'PO untuk divalidasi'), 'ppk_minta' => 'permintaan revisi PPK BLU',
                        'ketupel_kerja' => 'disposisi revisi dari Ketua Tim', 'katim_review' => 'hasil revisi dari Ketua Pelaksana'][$state];
                $this->pesan($po, (int) $this->getUserId(), $sisi === 'pembuat' ? 'pembuat' : 'katim', $peran, 'membaca ' . $apa . '.');
            }
        } catch (\Throwable $e) {
            // penanda baca bersifat pelengkap (mis. kolom ppk_dibaca_at belum dimigrasi)
        }
    }

    /** Status turunan satu PO: ppk_cek | ppk_minta | katim_kerja | ketupel_kerja | katim_review | selesai */
    protected function stateDari(array $r) {
        if (!empty($r['validasi_keuangan_at'])) { return 'selesai'; }
        switch ((string) $r['ppk_status']) {
            case 'minta':         return 'ppk_minta';
            case 'katim_kerja':   return 'katim_kerja';
            case 'ketupel_kerja': return 'ketupel_kerja';
            case 'katim_review':  return 'katim_review';
        }
        return 'ppk_cek';
    }

    protected function wajibSisi($sisi, $harus, $pesan) {
        if ($sisi !== $harus) { $this->keluarkanJson(['ok' => false, 'pesan' => $pesan], 403); }
    }

    protected function wajibState(array $po, array $boleh, $pesan) {
        if (!in_array($this->stateDari($po), $boleh, true)) { $this->keluarkanJson(['ok' => false, 'pesan' => $pesan], 422); }
    }

    protected function wajibCatatanSelesai(array $po) {
        $n = $this->hitungCatatan((int) $po['id'], 'ppk', (int) $po['ronde_ppk']);
        if ($n['terbuka'] > 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Masih ada ' . $n['terbuka'] . ' catatan yang belum ditandai diperbaiki.'], 422);
        }
    }

    /**
     * Ubah ppk_status dengan syarat status asal (optimistic lock). $ke null = kembali ke meja PPK BLU.
     * Menjawab 409 kalau status sudah berubah.
     */
    protected function gantiStatus(array $po, array $dari, $ke) {
        $in = "'" . implode("','", array_map(function ($s) { return preg_replace('/[^a-z_]/', '', $s); }, $dari)) . "'";
        try {
            if ($ke === null) {
                $diubah = $this->db->exec(
                    "UPDATE po_kegiatan SET ppk_status = NULL, ppk_status_at = NOW(), ppk_alasan = NULL, ppk_dibaca_at = NULL
                      WHERE id = ? AND status_review = 'disetujui' AND ppk_status IN (" . $in . ")",
                    [1 => (int) $po['id']]
                );
            } else {
                $diubah = $this->db->exec(
                    "UPDATE po_kegiatan SET ppk_status = ?, ppk_status_at = NOW(), ppk_dibaca_at = NULL
                      WHERE id = ? AND status_review = 'disetujui' AND ppk_status IN (" . $in . ")",
                    [1 => $ke, 2 => (int) $po['id']]
                );
            }
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengubah status PO.'], 500);
        }
        if ((int) $diubah === 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
        }
    }

    /** Catat pesan sistem. Format: "<nama> (<peran>) <aksi>". Halaman memakai "(peran)" untuk menentukan pelaku di Alur. */
    protected function pesan(array $po, $uid, $sisi, $peran, $aksi) {
        $nama = $this->namaPengguna($uid, $peran);
        \PoReviewController::catatPesanSistem($this->db, (int) $po['id'], (int) $uid, $sisi, $nama . ' (' . $peran . ') ' . $aksi);
    }

    protected function kabariKe(array $userIds, $judul, $pesan, $urlRelatif) {
        if (!class_exists('PushNotifikasi')) { return; }
        $base = (string) \Base::instance()->get('BASE');
        $sudah = [];
        foreach ($userIds as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($sudah[$id])) { continue; }
            $sudah[$id] = true;
            try {
                \PushNotifikasi::kirim($this->db, $id, $judul, $pesan, $base . $urlRelatif, 'po-review');
            } catch (\Throwable $e) {
                // notifikasi bersifat pelengkap
            }
        }
    }

    /* ============================================================
     * HELPER: DATA
     * ============================================================ */

    /** Jumlah catatan per state untuk satu tahap (ronde opsional). */
    protected function hitungCatatan($poId, $tahap, $ronde = null) {
        $sql = "SELECT SUM(state = 'draf') AS draf, SUM(state = 'terbuka') AS terbuka, SUM(state = 'selesai') AS selesai
                FROM po_revisi_catatan WHERE po_id = ? AND tahap = ?";
        $prm = [1 => (int) $poId, 2 => $tahap];
        if ($ronde !== null) { $sql .= " AND ronde = ?"; $prm[3] = (int) $ronde; }
        try {
            $r = $this->db->exec($sql, $prm);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca catatan. Sudah menjalankan migration_po_revisi_ppk.sql?'], 500);
        }
        $r = !empty($r) ? $r[0] : [];
        return [
            'draf'    => isset($r['draf']) ? (int) $r['draf'] : 0,
            'terbuka' => isset($r['terbuka']) ? (int) $r['terbuka'] : 0,
            'selesai' => isset($r['selesai']) ? (int) $r['selesai'] : 0,
        ];
    }

    /**
     * Daftar PO sesuai mode. katim/pembuat: hanya PO yang sedang/pernah masuk revisi PPK BLU dan belum divalidasi.
     * ppk: semua PO yang sudah sampai di PPK BLU (Humas dan Tim Mitra selesai).
     */
    protected function daftarItems($mode, $uid, $poId) {
        $ex = function ($kolom) { return $this->kolomAda($kolom) ? 'p.' . $kolom : 'NULL'; };
        $mitraKol = $this->kolomAda('tervalidasi_mitra_at') ? 'tervalidasi_mitra_at' : ($this->kolomAda('tervalidasi_keuangan_at') ? 'tervalidasi_keuangan_at' : '');

        $sql = "SELECT p.id, p.nomor_po, p.ppk_status, p.ronde_ppk, p.ppk_dibaca_at, p.ppk_minta_at, p.ppk_status_at, p.ppk_alasan, p.validasi_keuangan_at,
                       p.created_at AS dibuat_at, COALESCE(p.dikirim_at, p.updated_at, p.created_at) AS dikirim_at, p.disetujui_at,
                       " . $ex('verifikasi_humas_at') . " AS humas_ver_at,
                       " . ($mitraKol !== '' ? 'p.' . $mitraKol : 'NULL') . " AS mitra_valid_at,
                       COALESCE(NULLIF(p.judul_kegiatan, ''), o.judul_kegiatan) AS judul,
                       o.nomor_order, o.jenis_layanan_opti, o.ketua_pelaksana_id,
                       c.nmcustomer AS nama_perusahaan, c.pt_cv,
                       (SELECT COUNT(*) FROM po_revisi_catatan x
                         WHERE x.po_id = p.id AND x.tahap = 'ppk' AND x.ronde = p.ronde_ppk AND x.state <> 'draf') AS rn_total,
                       (SELECT COUNT(*) FROM po_revisi_catatan x
                         WHERE x.po_id = p.id AND x.tahap = 'ppk' AND x.ronde = p.ronde_ppk AND x.state = 'selesai') AS rn_selesai,
                       (SELECT COUNT(*) FROM po_ppk_perubahan g WHERE g.po_id = p.id AND g.ronde_ppk = p.ronde_ppk) AS jml_ubah,
                       (SELECT COUNT(*) FROM po_ppk_perubahan g WHERE g.po_id = p.id AND g.ronde_ppk = p.ronde_ppk AND (p.ppk_status_at IS NULL OR g.created_at >= p.ppk_status_at)) AS jml_ubah_baru
                FROM po_kegiatan p
                JOIN order_layanan o ON o.id = p.order_id
                JOIN tb_customer c ON c.id_customer = o.id_customer
                WHERE p.status IN ('terkirim', 'disetujui_mitra', 'disetujui') AND p.status_review = 'disetujui'";
        $params = [];
        $idx = 1;
        if ($poId > 0) { $sql .= " AND p.id = ?"; $params[$idx++] = (int) $poId; }

        if ($mode === 'ppk') {
            foreach (self::kolomSyarat($this->db) as $k) { $sql .= " AND p." . $k . " IS NOT NULL"; }
        } else {
            $sql .= " AND (p.ppk_status IS NOT NULL OR (p.ronde_ppk > 0 AND p.validasi_keuangan_at IS NULL))";
            if ($mode === 'katim') {
                $div = $this->divisiKatim();
                if ($div !== '') { $sql .= " AND o.jenis_layanan_opti = ?"; $params[$idx++] = $div; }
            } else {
                $sql .= " AND o.ketua_pelaksana_id = ?"; $params[$idx++] = (int) $uid;
            }
        }
        $sql .= " ORDER BY COALESCE(p.ppk_status_at, p.disetujui_at, p.created_at) DESC LIMIT " . (int) self::LIMIT_DAFTAR;

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
            $daftar[] = [
                'id'          => (int) $r['id'],
                'nomor'       => (string) $r['nomor_po'],
                'order'       => (string) $r['nomor_order'],
                'mitra'       => $this->formatMitra($r['nama_perusahaan'], $r['pt_cv']),
                'judul'       => (string) $r['judul'],
                'divisi'      => $div,
                'pembuat'     => isset($peta[$idp]) ? $peta[$idp] : 'Ketua Pelaksana',
                'katim'       => isset($peta[$idk]) ? $peta[$idk] : 'Ketua Tim',
                'state'       => $this->stateDari($r),
                'penerima'    => self::penerimaDari($this->stateDari($r)),
                'dibaca_at'   => $r['ppk_dibaca_at'],
                'ronde'       => (int) $r['ronde_ppk'],
                'alasan'      => (string) $r['ppk_alasan'],
                'status_at'   => $r['ppk_status_at'],
                'minta_at'    => $r['ppk_minta_at'],
                'dibuat_at'   => $r['dibuat_at'],
                'dikirim_at'  => $r['dikirim_at'],
                'disetujui_at' => $r['disetujui_at'],
                'humas_ver_at' => $r['humas_ver_at'],
                'mitra_valid_at' => $r['mitra_valid_at'],
                'ppk_valid_at' => $r['validasi_keuangan_at'],
                'rn_total'    => (int) $r['rn_total'],
                'rn_selesai'  => (int) $r['rn_selesai'],
                'jml_ubah'    => (int) $r['jml_ubah'],
                'jml_ubah_baru' => (int) $r['jml_ubah_baru'],   // perubahan PO sejak PO terakhir dikirim/didisposisikan ke penerima
            ];
        }
        return $daftar;
    }

    /** Rincian satu PO: item daftar + catatan tahap PPK + perubahan putaran ini + riwayat sistem. Draf hanya untuk PPK BLU. */
    protected function paketDetail($poId, $sisi) {
        $mode = ($sisi === 'ppk') ? 'ppk' : $sisi;
        try {
            $items = $this->daftarItems($mode, (int) $this->getUserId(), (int) $poId);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat PO.'], 500);
        }
        if (empty($items)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan atau tidak berada di tahap ini.'], 404);
        }

        $where = ($sisi === 'ppk') ? "" : " AND state <> 'draf'";
        try {
            $rows = $this->db->exec(
                "SELECT id, bagian, teks, state, ronde, dibuat_oleh, created_at FROM po_revisi_catatan
                  WHERE po_id = ? AND tahap = 'ppk'" . $where . " ORDER BY id ASC",
                [1 => (int) $poId]
            );
            $chg = $this->db->exec(
                "SELECT g.bagian, g.ringkasan, g.oleh, g.created_at FROM po_ppk_perubahan g
                   JOIN po_kegiatan p ON p.id = g.po_id
                  WHERE g.po_id = ? AND g.ronde_ppk = p.ronde_ppk ORDER BY g.id ASC",
                [1 => (int) $poId]
            );
            $riw = $this->db->exec(
                "SELECT pesan, created_at FROM po_chat WHERE po_id = ? AND sistem = 1 ORDER BY id ASC",
                [1 => (int) $poId]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal memuat catatan. Sudah menjalankan migration_po_revisi_ppk.sql?'], 500);
        }

        $ids = [];
        foreach ($rows as $r) { $ids[] = $r['dibuat_oleh']; }
        foreach ($chg as $r) { $ids[] = $r['oleh']; }
        $peta = $this->petaNamaUser($ids);

        $catatan = [];
        foreach ($rows as $r) {
            $idu = (int) $r['dibuat_oleh'];
            $catatan[] = ['id' => (int) $r['id'], 'bagian' => (string) $r['bagian'], 'teks' => (string) $r['teks'], 'state' => (string) $r['state'],
                          'ronde' => (int) $r['ronde'], 'oleh' => isset($peta[$idu]) ? $peta[$idu] : 'PPK BLU', 'waktu' => (string) $r['created_at']];
        }
        $perubahan = [];
        foreach ($chg as $r) {
            $idu = (int) $r['oleh'];
            $perubahan[] = ['bagian' => (string) $r['bagian'], 'ringkasan' => (string) $r['ringkasan'], 'oleh' => isset($peta[$idu]) ? $peta[$idu] : '-', 'waktu' => (string) $r['created_at']];
        }
        $riwayat = [];
        foreach ($riw as $r) { $riwayat[] = ['pesan' => (string) $r['pesan'], 'waktu' => (string) $r['created_at']]; }

        return ['ok' => true, 'sisi' => $sisi, 'po' => $items[0], 'catatan' => $catatan, 'perubahan' => $perubahan, 'riwayat' => $riwayat];
    }

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

    protected function namaPengguna($uid, $cadangan) {
        $peta = $this->petaNamaUser([$uid]);
        return isset($peta[(int) $uid]) ? $peta[(int) $uid] : $cadangan;
    }

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

    protected function formatMitra($nama, $ptCv) {
        $bersih = trim((string) preg_replace('/^((PT|CV|UD)\.?\s+)+/i', '', (string) $nama));
        $badan  = trim((string) $ptCv);
        return ($badan !== '') ? $badan . ' ' . $bersih : $bersih;
    }

    protected function kolomAda($nama) {
        return self::adaKolom($this->db, $nama);
    }

    /** Apakah kolom ada di po_kegiatan (dicache per proses). */
    protected static function adaKolom($db, $nama) {
        if (!isset(self::$kolomCache[$nama])) {
            try {
                $r = $db->exec(
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

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}