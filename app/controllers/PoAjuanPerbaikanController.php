<?php
/**
 * PoAjuanPerbaikanController
 *
 * Ajuan perbaikan PO ke PPK BLU SETELAH PO diverifikasi PPK BLU (PO Aktif).
 * Pengaju: Ketua Tim (divisi PO ini) ATAU Ketua Pelaksana pembuat PO. Siapa pun boleh, tidak harus lewat satu sama lain.
 *
 * Alur (memakai ulang alur revisi PPK BLU di PoRevisiPpkController, tidak membuat alur baru):
 *   1. Pengaju menekan "Ajukan perbaikan", mengisi alasan (wajib) dan lampiran pendukung (opsional)      -> mulai()
 *      - potret isi PO disimpan (untuk dikembalikan bila ajuan ditolak / ditarik)
 *      - po_kegiatan.ajuan_id terisi; ppk_status = katim_kerja (pengaju Ketua Tim) / ketupel_kerja (pengaju Ketua Pelaksana)
 *      - PO tetap "Aktif": validasi_keuangan_at TIDAK diubah, PO TTD lama tetap berlaku sampai ajuan disetujui
 *   2. Pengaju mengubah PO lewat form PO yang sama (konteks 'ppk'), lalu "Kirim ke PPK BLU"                -> kirim()
 *      Pihak lain (Ketua Tim / Ketua Pelaksana) hanya diberi tahu.
 *   3. PPK BLU memutuskan, langsung tanpa lewat Ketua Tim / Ketua Pelaksana:
 *      - Setujui     -> PoRevisiPpkController::validasi() memanggil setujuiAjuan(); PO TTD baru wajib diunggah ulang
 *      - Minta revisi (alur lama) -> ppk_status = 'minta', Ketua Tim memutuskan siapa yang memperbaiki, dst sampai PPK BLU puas
 *      - Tolak       -> tolak(): isi PO dikembalikan ke potret awal
 *   4. Pengaju boleh menarik ajuan kapan saja sebelum diputuskan                                           -> tarik()
 *
 * Skema dibuat otomatis sekali (pastikanSkema), file SQL-nya: migration_po_ajuan_perbaikan.sql.
 * Kompatibel PHP 7.2.
 */
class PoAjuanPerbaikanController extends PoRevisiPpkController {

    /** Kolom isi PO yang diubah form (bind()) dan karenanya dipotret / dikembalikan. Nomor PO, status, dan jejak alur tidak ikut. */
    const KOLOM_ISI = [
        'alamat', 'alat_bahan', 'bahan_kimia', 'dasar', 'dasar_struktur', 'jadwal', 'jadwal_mulai', 'jadwal_selesai',
        'judul_kegiatan', 'kebutuhan_tambahan', 'metoda', 'pic_po_id', 'rab', 'ruang_lingkup', 'tanggal_po',
        'tim_pelaksana', 'ttd_json', 'tujuan',
    ];
    const LAMPIRAN_EKSTENSI = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    const LAMPIRAN_MAKS     = 8388608;   // 8 MB per file
    const LAMPIRAN_BANYAK   = 3;         // jumlah file maksimal per ajuan
    const ALASAN_MIN        = 10;
    const ALASAN_MAKS_AJUAN = 1000;

    /* ============================================================
     * SKEMA
     * ============================================================ */

    /** Buat tabel dan kolom yang dibutuhkan (sekali per sesi). false kalau gagal; pemanggil tetap jalan. */
    public static function pastikanSkema($db) {
        static $sudah = false;
        if ($sudah) { return true; }
        if (!empty($_SESSION['_skema_po_ajuan_v2'])) { $sudah = true; return true; }
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS po_ajuan_perbaikan (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    po_id INT NOT NULL,
                    order_id INT NULL,
                    oleh_id INT NOT NULL,
                    peran VARCHAR(10) NOT NULL DEFAULT 'katim',
                    alasan TEXT NOT NULL,
                    lampiran_nama VARCHAR(255) NULL,
                    lampiran_file VARCHAR(120) NULL,
                    status VARCHAR(12) NOT NULL DEFAULT 'berjalan',
                    ronde_awal INT NOT NULL DEFAULT 0,
                    snapshot LONGTEXT NULL,
                    putusan_catatan TEXT NULL,
                    diputus_oleh INT NULL,
                    diputus_at DATETIME NULL,
                    ttd_lama_file VARCHAR(120) NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_po (po_id, status)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            // lampiran ke-2 dan ke-3 (lampiran pertama tetap di kolom lampiran_nama / lampiran_file supaya tampilan lama tetap jalan)
            $db->exec(
                "CREATE TABLE IF NOT EXISTS po_ajuan_lampiran (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    ajuan_id INT UNSIGNED NOT NULL,
                    nama VARCHAR(255) NOT NULL,
                    file VARCHAR(120) NOT NULL,
                    urut TINYINT NOT NULL DEFAULT 2,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_ajuan (ajuan_id)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $ada = function ($kolom) use ($db) {
                $r = $db->exec("SHOW COLUMNS FROM `po_kegiatan` LIKE '" . $kolom . "'");
                return !empty($r);
            };
            if (!$ada('ajuan_id')) { $db->exec("ALTER TABLE `po_kegiatan` ADD COLUMN `ajuan_id` INT NULL DEFAULT NULL"); }
            $_SESSION['_skema_po_ajuan_v2'] = 1;
            $sudah = true;
            return true;
        } catch (\Throwable $e) {
            error_log('[PO ajuan] skema gagal dibuat: ' . $e->getMessage());
            return false;
        }
    }

    protected function wajibSkema() {
        if (!self::pastikanSkema($this->db)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Tabel ajuan perbaikan belum bisa dibuat di database. Jalankan migration_po_ajuan_perbaikan.sql.'], 500);
        }
    }

    /* ============================================================
     * STATUS (untuk tombol di Daftar PO dan Daftar Order)
     * ============================================================ */

    /** GET /po-ajuan/status?ids=1,2,3 -- siapa boleh mengajukan, dan ajuan yang sedang berjalan */
    public function status($f3) {
        $this->requireAuth();
        $this->wajibSkema();
        $ids = [];
        foreach (explode(',', (string) $f3->get('GET.ids')) as $x) { $x = (int) $x; if ($x > 0) { $ids[$x] = $x; } }
        $ids = array_slice(array_values($ids), 0, 300);
        if (empty($ids)) { $this->keluarkanJson(['ok' => true, 'data' => new \stdClass()]); }
        $mode = $this->modeRevisi();
        if ($mode === null) { $this->keluarkanJson(['ok' => true, 'data' => new \stdClass()]); }
        try {
            $rows = $this->db->exec(
                "SELECT p.id, p.nomor_po, p.status, p.status_review, p.ppk_status, p.ajuan_id, p.validasi_keuangan_at,
                        o.id AS order_id, o.status AS order_status, o.jenis_layanan_opti, o.ketua_pelaksana_id
                   FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id
                  WHERE p.id IN (" . implode(',', array_map('intval', $ids)) . ")"
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membaca status PO.'], 500);
        }
        $hasil = [];
        foreach ($rows as $r) {
            if ($this->sisiPada($r) === null) { continue; }
            if (empty($r['validasi_keuangan_at']) || (string) $r['status_review'] !== 'disetujui') { continue; }
            $terkunci = strtolower(trim((string) $r['order_status'])) === 'selesai';
            $adaBast = empty($r['ajuan_id']) && $this->adaBast((int) $r['order_id']);
            if (!empty($r['ajuan_id'])) {
                $ps = (string) $r['ppk_status'];
                $ket = 'Perbaikan menunggu keputusan PPK BLU';
                if ($ps === 'katim_kerja') { $ket = 'Perbaikan sedang dikerjakan Ketua Tim'; }
                elseif ($ps === 'ketupel_kerja') { $ket = 'Perbaikan sedang dikerjakan Ketua Pelaksana'; }
                elseif ($ps === 'minta') { $ket = 'PPK BLU meminta revisi atas ajuan perbaikan'; }
                elseif ($ps === 'katim_review') { $ket = 'Hasil perbaikan sedang direview Ketua Tim'; }
                $hasil[(int) $r['id']] = ['bisa' => false, 'aktif' => true, 'ket' => $ket, 'nomor' => (string) $r['nomor_po']];
            } elseif ($r['ppk_status'] === null || $r['ppk_status'] === '') {
                $hasil[(int) $r['id']] = ['bisa' => !$terkunci && !$adaBast, 'aktif' => false, 'ket' => $terkunci ? 'Order sudah selesai dan dikunci.' : ($adaBast ? 'BAST sudah dibuat.' : ''), 'nomor' => (string) $r['nomor_po']];
            }
        }
        $this->keluarkanJson(['ok' => true, 'data' => $hasil ?: new \stdClass()]);
    }

    /** BAST sudah dibuat: PO bertanda tangan tidak bisa diganti lagi, jadi PO juga tidak bisa diajukan perbaikan. */
    protected function adaBast($orderId) {
        try {
            $b = (new \OptiBast($this->db))->getByOrderId((int) $orderId);
            return !empty($b);
        } catch (\Throwable $e) { return false; }
    }

    /** 'katim' | 'pembuat' | null untuk baris po (p.* + o.jenis_layanan_opti, o.ketua_pelaksana_id). Superadmin = 'katim'. */
    protected function sisiPada(array $r) {
        $mode = $this->modeRevisi();
        if ($this->isSuperadmin()) { return 'katim'; }
        if ($mode === 'katim') {
            $div = $this->divisiKatim();
            return ($div === '' || $div === (string) $r['jenis_layanan_opti']) ? 'katim' : null;
        }
        if ($mode === 'pembuat') {
            $uid = (int) $this->getUserId();
            return ($uid > 0 && $uid === (int) $r['ketua_pelaksana_id']) ? 'pembuat' : null;
        }
        return null;
    }

    /* ============================================================
     * AKSI PENGAJU
     * ============================================================ */

    /** POST /po-ajuan/@id/mulai -- field: alasan (wajib), lampiran[] (opsional, sampai 3 berkas), dari (index|order|daftar) */
    public function mulai($f3, $params) {
        $this->wajibSkema();
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        if (strtolower(trim((string) ($po['order_status'] ?? ''))) === 'selesai' && !$this->canEditSubmittedData(true, (int) ($po['order_id'] ?? 0))) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Order sudah selesai dan dikunci.'], 422);
        }
        if ($this->adaBast((int) $po['order_id'])) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'BAST untuk order ini sudah dibuat, jadi PO tidak bisa diajukan perbaikan lagi.'], 422);
        }
        if ($this->stateDari($po) !== 'selesai' || !empty($po['ajuan_id'])) {
            $this->keluarkanJson(['ok' => false, 'pesan' => !empty($po['ajuan_id']) ? 'PO ini sudah punya ajuan perbaikan yang sedang berjalan.' : 'Ajuan perbaikan hanya bisa dibuat setelah PO diverifikasi PPK BLU dan sedang tidak dalam revisi.'], 422);
        }
        $alasan = trim((string) $f3->get('POST.alasan'));
        if (mb_strlen($alasan) < self::ALASAN_MIN) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Alasan perbaikan wajib diisi (minimal ' . self::ALASAN_MIN . ' karakter) supaya PPK BLU paham apa yang berubah.'], 422);
        }
        $alasan = mb_substr($alasan, 0, self::ALASAN_MAKS_AJUAN);

        $lamp = $this->simpanLampiranBanyak();   // [[nama tampil, nama file tersimpan], ...]
        $lampNama = !empty($lamp) ? $lamp[0][0] : null;
        $lampFile = !empty($lamp) ? $lamp[0][1] : null;

        try {
            $rows = $this->db->exec("SELECT * FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $po['id']]);
        } catch (\Exception $e) { $rows = []; }
        if (empty($rows)) {
            $this->hapusSemuaLampiran($lamp);
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan.'], 404);
        }
        $row = $rows[0];
        $isi = [];
        foreach (self::KOLOM_ISI as $k) { if (array_key_exists($k, $row)) { $isi[$k] = $row[$k]; } }
        $snap = json_encode(['isi' => $isi], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($snap === false) {
            $this->hapusSemuaLampiran($lamp);
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membuat salinan PO sebelum diubah.'], 500);
        }

        $ronde = (int) $row['ronde_ppk'];
        $statusBaru = ($sisi === 'pembuat') ? 'ketupel_kerja' : 'katim_kerja';
        $ajuanId = 0;
        try {
            $this->db->begin();
            $this->db->exec(
                "INSERT INTO po_ajuan_perbaikan (po_id, order_id, oleh_id, peran, alasan, lampiran_nama, lampiran_file, status, ronde_awal, snapshot, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'berjalan', ?, ?, NOW())",
                [1 => (int) $po['id'], 2 => (int) $po['order_id'], 3 => $uid, 4 => $sisi, 5 => $alasan, 6 => $lampNama, 7 => $lampFile, 8 => $ronde, 9 => $snap]
            );
            $ajuanId = (int) $this->db->lastInsertId();
            foreach (array_slice($lamp, 1) as $i => $x) {
                $this->db->exec("INSERT INTO po_ajuan_lampiran (ajuan_id, nama, file, urut, created_at) VALUES (?, ?, ?, ?, NOW())", [1 => $ajuanId, 2 => $x[0], 3 => $x[1], 4 => $i + 2]);
            }
            $diubah = $this->db->exec(
                "UPDATE po_kegiatan
                    SET ajuan_id = ?, ppk_status = ?, ronde_ppk = ronde_ppk + 1, ppk_status_at = NOW(), ppk_dibaca_at = NULL, ppk_alasan = NULL
                  WHERE id = ? AND status_review = 'disetujui' AND ppk_status IS NULL AND ajuan_id IS NULL AND validasi_keuangan_at IS NOT NULL",
                [1 => $ajuanId, 2 => $statusBaru, 3 => (int) $po['id']]
            );
            if ((int) $diubah === 0) {
                $this->db->rollback();
                $this->hapusSemuaLampiran($lamp);
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            $this->hapusSemuaLampiran($lamp);
            error_log('[PO ajuan] mulai gagal: ' . $e->getMessage());
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal membuat ajuan perbaikan.'], 500);
        }

        // Berkas Excel RAB disimpan di server dan ditimpa saat pengaju menyimpan perbaikan, jadi isi awalnya dicadangkan di sini (dipulihkan bila ajuan ditolak / ditarik)
        try {
            $cad = self::jalurCadanganRab(isset($row['rab']) ? $row['rab'] : '', $ajuanId);
            $utama = ($cad !== '') ? preg_replace('/\.ajuan\d+\.xlsx$/', '.xlsx', $cad) : '';
            if ($cad !== '' && is_file($utama) && !@copy($utama, $cad)) { error_log('[PO ajuan] cadangan Excel RAB gagal dibuat: ' . $cad); }
        } catch (\Throwable $eCad) { error_log('[PO ajuan] cadangan Excel RAB: ' . $eCad->getMessage()); }
        $this->simpanBaselinePpk((int) $po['id']);   // isi PO sebelum diperbaiki = pembanding untuk sorotan perubahan di review PPK BLU
        $peran = ($sisi === 'pembuat') ? 'Ketua Pelaksana' : 'Ketua Tim';
        $this->pesan($po, $uid, $sisi, $peran, 'mengajukan perbaikan PO ke PPK BLU (PO sudah disetujui). Alasan: ' . $alasan);
        $this->kabariKe($this->pihakLain($po, $sisi), 'Ajuan perbaikan PO dimulai', 'PO ' . $po['nomor_po'] . ': ' . $peran . ' mulai memperbaiki PO yang sudah disetujui PPK BLU. PO versi lama tetap berlaku sampai PPK BLU memutuskan.', '/po-kegiatan/daftar');

        $dari = (string) $f3->get('POST.dari');
        if (!in_array($dari, ['index', 'order', 'daftar'], true)) { $dari = 'index'; }
        $this->keluarkanJson(['ok' => true, 'ajuan_id' => $ajuanId, 'edit_url' => (string) $f3->get('BASE') . '/po-kegiatan/' . (int) $po['id'] . '/edit?dari=' . $dari]);
    }

    /** POST /po-ajuan/@id/kirim -- pengaju yang sedang mengerjakan mengirim hasil perbaikan langsung ke PPK BLU */
    public function kirim($f3, $params) {
        $this->wajibSkema();
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        if (empty($po['ajuan_id'])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini tidak punya ajuan perbaikan yang berjalan.'], 422); }
        $state = $this->stateDari($po);
        if ($state === 'katim_kerja') {
            $this->wajibSisi($sisi, 'katim', 'Perbaikan ini sedang dikerjakan Ketua Tim.');
        } elseif ($state === 'ketupel_kerja') {
            $this->wajibSisi($sisi, 'pembuat', 'Perbaikan ini sedang dikerjakan Ketua Pelaksana.');
        } else {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Ajuan ini tidak sedang Anda kerjakan.'], 422);
        }
        $this->wajibCatatanSelesai($po);

        $ajuan = $this->ambilAjuan((int) $po['ajuan_id']);
        $jmlUbah = 0;
        try {
            $r = $this->db->exec("SELECT COUNT(*) AS c FROM po_ppk_perubahan WHERE po_id = ? AND ronde_ppk > ?", [1 => (int) $po['id'], 2 => (int) ($ajuan ? $ajuan['ronde_awal'] : 0)]);
            $jmlUbah = isset($r[0]['c']) ? (int) $r[0]['c'] : 0;
        } catch (\Exception $e) {}

        $this->gantiStatus($po, [$state === 'katim_kerja' ? 'katim_kerja' : 'ketupel_kerja'], null);
        $peran = ($sisi === 'pembuat') ? 'Ketua Pelaksana' : 'Ketua Tim';
        $this->pesan($po, $uid, $sisi, $peran, 'mengirim perbaikan PO ke PPK BLU' . ($jmlUbah === 0 ? ' (tidak ada perubahan isi PO yang terdeteksi).' : '.'));
        $this->kabariKe(self::idPpk($this->db), 'Ajuan perbaikan PO masuk', 'PO ' . $po['nomor_po'] . ': ' . $peran . ' mengajukan perbaikan. Buka untuk menyetujui, meminta revisi, atau menolak.', '/po-ppk');
        $this->kabariKe($this->pihakLain($po, $sisi), 'Ajuan perbaikan PO dikirim', 'PO ' . $po['nomor_po'] . ': ' . $peran . ' mengirim ajuan perbaikan ke PPK BLU.', '/po-kegiatan/daftar');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], $sisi));
    }

    /** POST /po-ajuan/@id/tarik -- pengaju menarik ajuan; isi PO dikembalikan ke versi sebelum ajuan */
    public function tarik($f3, $params) {
        $this->wajibSkema();
        list($po, $sisi) = $this->aksesRevisi((int) $params['id']);
        $uid = (int) $this->getUserId();
        if (empty($po['ajuan_id'])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini tidak punya ajuan perbaikan yang berjalan.'], 422); }
        $ajuan = $this->ambilAjuan((int) $po['ajuan_id']);
        if (!$ajuan || $ajuan['status'] !== 'berjalan') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Ajuan ini sudah diputuskan.'], 409); }
        $this->kembalikan($po, $ajuan, 'ditarik', $uid, '');
        $peran = ($sisi === 'pembuat') ? 'Ketua Pelaksana' : 'Ketua Tim';
        $this->pesan($po, $uid, $sisi, $peran, 'menarik ajuan perbaikan PO. Isi PO dikembalikan seperti sebelum ajuan.');
        $this->kabariKe(array_merge(self::idPpk($this->db), $this->pihakLain($po, $sisi)), 'Ajuan perbaikan PO ditarik', 'PO ' . $po['nomor_po'] . ': ' . $peran . ' menarik ajuan perbaikan. PO kembali seperti semula.', '/po-kegiatan/daftar');
        $this->keluarkanJson(['ok' => true, 'ditarik' => true]);   // PO sudah keluar dari alur revisi, jadi tidak ada rincian untuk dimuat ulang
    }

    /* ============================================================
     * AKSI PPK BLU
     * ============================================================ */

    /** POST /po-ajuan/@id/tolak -- PPK BLU menolak ajuan (alasan wajib). Isi PO dikembalikan. */
    public function tolak($f3, $params) {
        $this->wajibSkema();
        $po = $this->aksesPpk((int) $params['id']);
        $uid = (int) $this->getUserId();
        if (empty($po['ajuan_id'])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini tidak punya ajuan perbaikan yang berjalan.'], 422); }
        $this->wajibState($po, ['ppk_cek'], 'Ajuan hanya bisa ditolak saat berada di PPK BLU.');
        $catatan = trim((string) $f3->get('POST.catatan'));
        if (mb_strlen($catatan) < 5) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Alasan penolakan wajib diisi supaya pengaju tahu apa yang harus dilakukan.'], 422); }
        $catatan = mb_substr($catatan, 0, 500);
        $ajuan = $this->ambilAjuan((int) $po['ajuan_id']);
        if (!$ajuan || $ajuan['status'] !== 'berjalan') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Ajuan ini sudah diputuskan.'], 409); }

        $this->kembalikan($po, $ajuan, 'ditolak', $uid, $catatan);
        $this->pesan($po, $uid, 'katim', 'PPK BLU', 'menolak ajuan perbaikan PO. Alasan: ' . $catatan . ' PO kembali ke isi semula.');
        $tujuan = array_merge(self::idKatim($this->db, $po['jenis_layanan_opti']), [(int) $po['ketua_pelaksana_id']]);
        $this->kabariKe($tujuan, 'Ajuan perbaikan PO ditolak', 'PO ' . $po['nomor_po'] . ': ' . $catatan . ' PO kembali ke isi sebelum ajuan dan tetap berlaku.', '/po-kegiatan/daftar');
        $this->keluarkanJson($this->paketDetail((int) $po['id'], 'ppk'));
    }

    /**
     * Dipanggil PoRevisiPpkController::validasi() saat PPK BLU menyetujui PO yang punya ajuan. Isi PO sudah berubah sejak pengaju menyimpan.
     * Menandai ajuan disetujui, melepas PO dari ajuan, dan mewajibkan PO bertanda tangan diunggah ulang.
     */
    public static function setujuiAjuan($db, $poId, $ajuanId, $uid, $catatan) {
        $file = '';
        try {
            $r = $db->exec("SELECT po_ttd_file FROM po_kegiatan WHERE id = ?", [1 => (int) $poId]);
            $file = !empty($r) ? trim((string) $r[0]['po_ttd_file']) : '';
        } catch (\Exception $e) {}
        $db->exec(
            "UPDATE po_ajuan_perbaikan SET status = 'disetujui', diputus_oleh = ?, diputus_at = NOW(), putusan_catatan = ?, ttd_lama_file = ? WHERE id = ? AND status = 'berjalan'",
            [1 => (int) $uid, 2 => ($catatan !== '' ? $catatan : null), 3 => ($file !== '' ? $file : null), 4 => (int) $ajuanId]
        );
        $db->exec("UPDATE po_kegiatan SET ajuan_id = NULL WHERE id = ? AND ajuan_id = ?", [1 => (int) $poId, 2 => (int) $ajuanId]);
        try {   // perbaikan disetujui: cadangan Excel RAB lama tidak diperlukan lagi
            $rr = $db->exec("SELECT rab FROM po_kegiatan WHERE id = ?", [1 => (int) $poId]);
            $cad = !empty($rr) ? self::jalurCadanganRab($rr[0]['rab'], (int) $ajuanId) : '';
            if ($cad !== '' && is_file($cad)) { @unlink($cad); }
        } catch (\Throwable $eCad) {}
        if ($file === '') { return false; }
        // PO bertanda tangan lama sudah tidak sesuai isi PO yang baru: dilepas dari PO (berkasnya tetap tersimpan, namanya dicatat di ajuan),
        // sehingga Tahap 8 kembali meminta Ketua Tim mengunggah PO bertanda tangan yang baru.
        try {
            $db->exec("UPDATE po_kegiatan SET po_ttd_file = NULL, po_ttd_nama = NULL, po_ttd_asal = NULL, po_ttd_at = NULL, po_ttd_oleh_id = NULL, po_ttd_oleh_nama = NULL WHERE id = ?", [1 => (int) $poId]);
        } catch (\Exception $e) {
            $db->exec("UPDATE po_kegiatan SET po_ttd_file = '', po_ttd_nama = '', po_ttd_asal = '', po_ttd_at = NULL WHERE id = ?", [1 => (int) $poId]);
        }
        return true;
    }

    /** Info ajuan untuk banner di halaman PPK BLU dan Ketua Tim / Ketua Pelaksana: map ajuan_id => array */
    public static function infoAjuan($db, array $ajuanIds) {
        $ajuanIds = array_values(array_unique(array_filter(array_map('intval', $ajuanIds))));
        if (empty($ajuanIds)) { return []; }
        $peta = [];
        try {
            $rows = $db->exec(
                "SELECT a.id, a.po_id, a.oleh_id, a.peran, a.alasan, a.lampiran_nama, a.lampiran_file, a.status, a.created_at
                   FROM po_ajuan_perbaikan a WHERE a.id IN (" . implode(',', $ajuanIds) . ")"
            );
        } catch (\Exception $e) { return []; }
        $base = (string) \Base::instance()->get('BASE');
        $extra = [];   // lampiran ke-2 dan ke-3 per ajuan
        try {
            foreach ($db->exec("SELECT id, ajuan_id, nama FROM po_ajuan_lampiran WHERE ajuan_id IN (" . implode(',', $ajuanIds) . ") ORDER BY urut, id") as $x) { $extra[(int) $x['ajuan_id']][] = $x; }
        } catch (\Exception $e) { /* tabel belum ada: hanya lampiran pertama */ }
        foreach ($rows as $r) {
            $daftar = [];
            if ($r['lampiran_file'] !== null && $r['lampiran_file'] !== '') { $daftar[] = ['nama' => (string) $r['lampiran_nama'], 'url' => $base . '/po-ajuan/' . (int) $r['id'] . '/lampiran']; }
            if (!empty($extra[(int) $r['id']])) { foreach ($extra[(int) $r['id']] as $x) { $daftar[] = ['nama' => (string) $x['nama'], 'url' => $base . '/po-ajuan/' . (int) $r['id'] . '/lampiran?f=' . (int) $x['id']]; } }
            $peta[(int) $r['id']] = [
                'id'        => (int) $r['id'],
                'peran'     => (string) $r['peran'],
                'peran_teks' => $r['peran'] === 'pembuat' ? 'Ketua Pelaksana' : 'Ketua Tim',
                'oleh_id'   => (int) $r['oleh_id'],
                'oleh'      => '',
                'alasan'    => (string) $r['alasan'],
                'lampiran'  => (string) $r['lampiran_nama'],
                'lampiran_url' => ($r['lampiran_file'] !== null && $r['lampiran_file'] !== '') ? ($base . '/po-ajuan/' . (int) $r['id'] . '/lampiran') : '',
                'lampiran_list' => $daftar,
                'status'    => (string) $r['status'],
                'sejak'     => (string) $r['created_at'],
            ];
        }
        return $peta;
    }

    /* ============================================================
     * LAMPIRAN
     * ============================================================ */

    /** GET /po-ajuan/@id/lampiran -- @id = id ajuan. Boleh: PPK BLU, Superadmin, Ketua Tim divisi PO, Ketua Pelaksana pembuat. */
    public function lampiran($f3, $params) {
        $this->requireAuth();
        $this->wajibSkema();
        $a = $this->ambilAjuan((int) $params['id']);
        $berkas = $a ? (string) $a['lampiran_file'] : '';
        $namaBerkas = $a ? (string) $a['lampiran_nama'] : '';
        $fid = (int) $f3->get('GET.f');
        if ($a && $fid > 0) {   // lampiran ke-2 / ke-3
            $berkas = ''; $namaBerkas = '';
            try {
                $x = $this->db->exec("SELECT nama, file FROM po_ajuan_lampiran WHERE id = ? AND ajuan_id = ? LIMIT 1", [1 => $fid, 2 => (int) $a['id']]);
                if (!empty($x)) { $berkas = (string) $x[0]['file']; $namaBerkas = (string) $x[0]['nama']; }
            } catch (\Exception $e) { $berkas = ''; }
        }
        if (!$a || $berkas === '') { $f3->error(404, 'Lampiran tidak ditemukan.'); return; }
        $boleh = $this->izinPpk();
        if (!$boleh) {
            try {
                $r = $this->db->exec(
                    "SELECT o.jenis_layanan_opti, o.ketua_pelaksana_id FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id WHERE p.id = ? LIMIT 1",
                    [1 => (int) $a['po_id']]
                );
                $boleh = !empty($r) && $this->sisiPada($r[0]) !== null;
            } catch (\Exception $e) { $boleh = false; }
        }
        if (!$boleh) { $f3->error(403, 'Anda tidak punya akses ke lampiran ini.'); return; }
        $path = $this->dirLampiran() . basename($berkas);
        if (!is_file($path)) { $f3->error(404, 'Berkas lampiran sudah tidak ada.'); return; }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $tipe = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                 'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $nama = preg_replace('/[\x00-\x1F"\\\\\/]+/', '-', (string) ($namaBerkas !== '' ? $namaBerkas : basename($path)));
        header('Content-Type: ' . (isset($tipe[$ext]) ? $tipe[$ext] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . $nama . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    protected function dirLampiran() {
        $dir = dirname(__DIR__, 2) . '/storage/po_ajuan/';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        return $dir;
    }

    /**
     * Simpan lampiran opsional dari $_FILES['lampiran'] (satu berkas, atau lampiran[] sampai LAMPIRAN_BANYAK berkas).
     * Return [[nama tampil, nama file tersimpan], ...]. Galat = JSON 422 dan berkas yang sudah tersimpan dibuang.
     */
    protected function simpanLampiranBanyak() {
        if (empty($_FILES['lampiran'])) { return []; }
        $f = $_FILES['lampiran'];
        $banyak = is_array($f['name']);
        $n = $banyak ? count($f['name']) : 1;
        $hasil = [];
        $gagal = function ($pesan, $kode = 422) use (&$hasil) {
            $this->hapusSemuaLampiran($hasil);
            $this->keluarkanJson(['ok' => false, 'pesan' => $pesan], $kode);
        };
        for ($i = 0; $i < $n; $i++) {
            $nama = $banyak ? $f['name'][$i] : $f['name'];
            $tmp  = $banyak ? $f['tmp_name'][$i] : $f['tmp_name'];
            $err  = (int) ($banyak ? $f['error'][$i] : $f['error']);
            $size = (int) ($banyak ? $f['size'][$i] : $f['size']);
            if ($err === UPLOAD_ERR_NO_FILE) { continue; }
            if (count($hasil) >= self::LAMPIRAN_BANYAK) { $gagal('Lampiran maksimal ' . self::LAMPIRAN_BANYAK . ' berkas.'); }
            if ($err !== UPLOAD_ERR_OK) { $gagal('Lampiran gagal diunggah (kode ' . $err . '). Coba file yang lebih kecil.'); }
            if ($size > self::LAMPIRAN_MAKS) { $gagal('Lampiran terlalu besar. Maksimal 8 MB per berkas.'); }
            $ext = strtolower(pathinfo((string) $nama, PATHINFO_EXTENSION));
            if (!in_array($ext, self::LAMPIRAN_EKSTENSI, true)) { $gagal('Jenis lampiran tidak didukung. Gunakan PDF, gambar (JPG/PNG), Word, atau Excel.'); }
            $simpan = bin2hex(random_bytes(12)) . '.' . $ext;
            if (!@move_uploaded_file($tmp, $this->dirLampiran() . $simpan)) { $gagal('Gagal menyimpan lampiran di server.', 500); }
            $tampil = mb_substr(preg_replace('/[\x00-\x1F\\\\\/:*?"<>|]+/u', '-', basename((string) $nama)), 0, 200);
            $hasil[] = [$tampil, $simpan];
        }
        return $hasil;
    }

    protected function hapusSemuaLampiran(array $daftar) {
        foreach ($daftar as $x) { $this->hapusLampiran($x[1]); }
    }

    protected function hapusLampiran($file) {
        if ($file !== null && $file !== '') { @unlink($this->dirLampiran() . basename($file)); }
    }

    /* ============================================================
     * HELPER
     * ============================================================ */

    protected function ambilAjuan($id) {
        try {
            $r = $this->db->exec("SELECT * FROM po_ajuan_perbaikan WHERE id = ? LIMIT 1", [1 => (int) $id]);
            return !empty($r) ? $r[0] : null;
        } catch (\Exception $e) { return null; }
    }

    /** id_user pihak lain yang cukup diberi tahu: pengaju Ketua Tim -> Ketua Pelaksana; pengaju Ketua Pelaksana -> Ketua Tim divisi. */
    protected function pihakLain(array $po, $sisi) {
        if ($sisi === 'pembuat') { return array_map('intval', self::idKatim($this->db, $po['jenis_layanan_opti'])); }
        return [(int) $po['ketua_pelaksana_id']];
    }

    /**
     * Akhiri ajuan tanpa persetujuan (ditarik / ditolak): isi PO dikembalikan ke potret awal, PO lepas dari alur revisi,
     * jejak perubahan dan catatan putaran ajuan dibersihkan, lampiran tetap disimpan sebagai arsip.
     */
    protected function kembalikan(array $po, array $ajuan, $hasil, $uid, $catatan) {
        $snap = json_decode((string) $ajuan['snapshot'], true);
        $isi = (is_array($snap) && isset($snap['isi']) && is_array($snap['isi'])) ? $snap['isi'] : null;
        if ($isi === null) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Salinan PO sebelum ajuan tidak ditemukan, jadi PO tidak bisa dikembalikan otomatis.'], 500); }
        $set = [];
        $prm = [];
        $i = 1;
        foreach (self::KOLOM_ISI as $k) {
            if (!array_key_exists($k, $isi)) { continue; }
            $set[] = '`' . $k . '` = ?';
            $prm[$i++] = $isi[$k];
        }
        $set[] = "ajuan_id = NULL";
        $set[] = "ppk_status = NULL";
        $set[] = "ppk_status_at = NOW()";
        $set[] = "ppk_dibaca_at = NULL";
        $set[] = "ronde_ppk = ?";
        $prm[$i++] = (int) $ajuan['ronde_awal'];
        $prm[$i++] = (int) $po['id'];
        $prm[$i++] = (int) $ajuan['id'];
        try {
            $this->db->begin();
            $diubah = $this->db->exec("UPDATE po_kegiatan SET " . implode(', ', $set) . " WHERE id = ? AND ajuan_id = ?", $prm);
            if ((int) $diubah === 0) {
                $this->db->rollback();
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Status PO sudah berubah. Muat ulang halaman.'], 409);
            }
            $this->db->exec("DELETE FROM po_ppk_perubahan WHERE po_id = ? AND ronde_ppk > ?", [1 => (int) $po['id'], 2 => (int) $ajuan['ronde_awal']]);
            $this->db->exec("DELETE FROM po_revisi_catatan WHERE po_id = ? AND tahap = 'ppk' AND ronde > ?", [1 => (int) $po['id'], 2 => (int) $ajuan['ronde_awal']]);
            try {   // balasan dari catatan yang baru dihapus ikut dibersihkan (tabel dibuat otomatis saat balasan pertama, jadi bisa belum ada)
                $this->db->exec("DELETE b FROM po_catatan_balasan b LEFT JOIN po_revisi_catatan c ON c.id = b.catatan_id WHERE b.po_id = ? AND c.id IS NULL", [1 => (int) $po['id']]);
            } catch (\Exception $eB) { /* tabel belum ada: tidak ada yang perlu dibersihkan */ }
            $this->db->exec(
                "UPDATE po_ajuan_perbaikan SET status = ?, diputus_oleh = ?, diputus_at = NOW(), putusan_catatan = ? WHERE id = ?",
                [1 => $hasil, 2 => (int) $uid, 3 => ($catatan !== '' ? $catatan : null), 4 => (int) $ajuan['id']]
            );
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            error_log('[PO ajuan] kembalikan gagal: ' . $e->getMessage());
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mengembalikan PO ke isi sebelum ajuan.'], 500);
        }
        $this->pulihkanRabExcel($isi, $ajuan);   // tabel RAB (berkas Excel) ikut kembali ke isi sebelum ajuan
    }

    /** Berkas cadangan Excel RAB untuk satu ajuan (<token>.ajuan<id>.xlsx). '' bila RAB PO tidak bersumber Excel. */
    public static function jalurCadanganRab($rabJson, $ajuanId) {
        $rab = json_decode((string) $rabJson, true);
        $tok = (is_array($rab) && isset($rab['sumber']) && is_array($rab['sumber']) && (($rab['sumber']['tipe'] ?? '') === 'excel')) ? (string) ($rab['sumber']['token'] ?? '') : '';
        $j = \PoKegiatanController::rabExcelJalur($tok);
        return $j === '' ? '' : preg_replace('/\.xlsx$/', '.ajuan' . (int) $ajuanId . '.xlsx', $j);
    }

    /**
     * Kembalikan berkas Excel RAB ke isi sebelum ajuan. Sumber: cadangan saat ajuan dimulai; untuk ajuan yang dimulai sebelum cadangan dibuat,
     * berkas "<token>.asli.xlsx" dipakai HANYA bila dibuat sesudah ajuan dimulai (berarti itu isi sebelum disimpan pertama kali).
     */
    protected function pulihkanRabExcel(array $isi, array $ajuan) {
        try {
            $cad = self::jalurCadanganRab(isset($isi['rab']) ? $isi['rab'] : '', (int) $ajuan['id']);
            if ($cad === '') { return; }
            $utama = preg_replace('/\.ajuan\d+\.xlsx$/', '.xlsx', $cad);
            $sumber = '';
            if (is_file($cad)) { $sumber = $cad; }
            else {
                $asli = preg_replace('/\.xlsx$/', '.asli.xlsx', $utama);
                $mulai = strtotime((string) (isset($ajuan['created_at']) ? $ajuan['created_at'] : ''));
                if (is_file($asli) && $mulai && @filemtime($asli) >= $mulai - 60) { $sumber = $asli; }
            }
            if ($sumber === '') { error_log('[PO ajuan] Excel RAB tidak bisa dipulihkan (cadangan tidak ada): ' . $utama); return; }
            $tmp = $utama . '.tmp';
            if (@copy($sumber, $tmp) && @rename($tmp, $utama)) { if ($sumber === $cad) { @unlink($cad); } }
            else { @unlink($tmp); error_log('[PO ajuan] Excel RAB gagal dipulihkan: ' . $utama); }
        } catch (\Throwable $e) { error_log('[PO ajuan] pulihkan Excel RAB: ' . $e->getMessage()); }
    }
}
