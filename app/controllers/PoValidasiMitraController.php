<?php
/**
 * PoValidasiMitraController
 *
 * PO masuk ke sini SETELAH diverifikasi Humas. Urutan alurnya: Ketua Pelaksana mengirim PO ->
 * Ketua Tim menyetujui (disposisi ke Humas) -> Humas memverifikasi -> BARU masuk ke Tim Mitra.
 * PO yang belum diverifikasi Humas (verifikasi_humas_at kosong) tidak tampil di sini dan tidak bisa
 * divalidasi lewat API. Tim Mitra mengecek bukti pembayaran order terkait (surat kesanggupan
 * bayar dan/atau bukti transfer per termin), menulis catatan, lalu memvalidasi.
 *
 * Tombol Validasi hanya aktif kalau order-nya SUDAH LUNAS atau PUNYA surat kesanggupan bayar.
 *
 * Tampilan disamakan dengan Daftar PO Humas / Daftar Review PO: baris tebal bertitik biru = belum dibaca Tim Mitra,
 * panel samping dengan stepper alur (Ketua Tim -> Humas -> Tim Mitra -> PPK BLU) dan tab PO | Berkas | Alur.
 * Penanda baca disimpan di po_kegiatan.mitra_dibaca_at (kolom dibuat otomatis, self-healing).
 *
 * Halaman ini punya 2 tab:
 *   - "Perlu Divalidasi"  -> PO status = 'terkirim' yang SUDAH diverifikasi Humas dan belum divalidasi Tim Mitra.
 *   - "Sudah Disetujui"   -> PO yang sudah divalidasi Tim Mitra (status 'disetujui_mitra', atau 'disetujui' bila PPK BLU
 *                            juga sudah memvalidasi, supaya Tim Mitra bisa memantau lanjutannya). Dikelompokkan per
 *                            bulan+tahun dari tervalidasi_mitra_at (default bulan berjalan,
 *                            bulan lain bisa dibuka lewat filter bulan/tahun atau search).
 *
 * [PENTING] Validasi di sini HANYA mengubah status PO (terkirim -> disetujui_mitra) +
 * tervalidasi_mitra_at/tervalidasi_mitra_by. Field status_review & disposisi_humas_at
 * SENGAJA tidak disentuh: keduanya sudah diisi saat Ketua Tim menyetujui PO.
 * Notifikasi lonceng "PO siap divalidasi" ke Tim Mitra dikirim oleh PoHumasController::verifikasi().
 *
 *   GET  /po-kegiatan/validasi-mitra           index: 2 tab (perlu divalidasi / sudah disetujui)
 *   GET  /po-kegiatan/@id/validasi-detail      JSON: info pembayaran order + dokumen + catatan
 *   POST /po-kegiatan/@id/baca-mitra           catat waktu PO pertama kali dibuka Tim Mitra
 *   POST /po-kegiatan/@id/catatan              simpan catatan saja (tanpa validasi)
 *   POST /po-kegiatan/@id/validasi             validasi -> status jadi disetujui_mitra
 *
 * Akses: Tim Mitra (tim_mitra_industri / admin_order / tim_mitra / ketua_tim_mitra) + Superadmin.
 * Kompatibel PHP 7.2.
 */
class PoValidasiMitraController extends Controller {

    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /po-kegiatan/validasi-mitra */
    public function index($f3) {
        $this->izinkan();
        $this->ensureSchemaStatusMitra();

        $errorMessage = null;

        $this->pastikanKolomBaca();

        // Tab 1: "Perlu Divalidasi" -- PO yang sudah diverifikasi Humas tapi belum divalidasi Tim Mitra.
        $errPerlu = null;
        $daftar = $this->ambilDaftar(
            "p.status = 'terkirim'" . \PoSchema::bukanSertifikasi('p') . $this->syaratHumas('p'),
            $this->kolomHumas('p') . " DESC, p.created_at DESC",
            $errPerlu
        );
        if ($errPerlu) { $errorMessage = 'Gagal memuat daftar: ' . $errPerlu; }

        // Tab 2: "Sudah Disetujui" -- PO yang udah divalidasi Tim Mitra (termasuk yang sudah lanjut divalidasi PPK BLU).
        // Dikelompokkan per bulan+tahun dari tervalidasi_mitra_at, biar frontend bisa
        // nampilin cuma bulan berjalan secara default & browsing bulan lain lewat
        // filter bulan/tahun atau pencarian.
        $errSetuju = null;
        $daftarDisetujui = $this->ambilDaftar(
            "p.status IN ('disetujui_mitra','disetujui') AND p.tervalidasi_mitra_at IS NOT NULL" . \PoSchema::bukanSertifikasi('p'),
            "p.tervalidasi_mitra_at DESC",
            $errSetuju
        );

        $f3->set('daftar_po_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('daftar_disetujui_json', json_encode($daftarDisetujui, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('bulan_ini', (int) date('n'));
        $f3->set('tahun_ini', (int) date('Y'));
        $f3->set('error_message', $errorMessage);
        $this->render('katim_kerja/po-kegiatan/validasi_mitra.html', 'Validasi Mitra', 'po_validasi_mitra');
    }

    /**
     * Ambil daftar PO untuk satu tab + info pembayaran order + bahan tab Alur. Hasil sudah dalam bentuk array siap di-JSON.
     * Kolom yang mungkin belum ada di database lama dipilih lewat kolom() supaya query tidak gagal.
     */
    protected function ambilDaftar($where, $orderBy, &$galat) {
        $galat = null;
        try {
            $rows = $this->db->exec(
                "SELECT p.id, p.nomor_po, p.status, p.created_at, p.updated_at, p.catatan_tim_mitra, p.tervalidasi_mitra_at,
                        o.id AS order_id, o.nomor_order, o.judul_kegiatan, o.jenis_layanan_opti, o.estimasi_biaya, o.ketua_pelaksana_id,
                        c.nmcustomer, c.pt_cv,
                        sp.nominal_penawaran, sp.surat_kesanggupan_bayar,
                        " . $this->kolom('p', 'verifikasi_humas_at') . " AS verifikasi_humas_at,
                        " . $this->kolom('p', 'humas_dibaca_at') . " AS humas_dibaca_at,
                        " . $this->kolom('p', 'mitra_dibaca_at') . " AS mitra_dibaca_at,
                        " . $this->kolom('p', 'validasi_keuangan_at') . " AS validasi_keuangan_at,
                        " . $this->kolom('p', 'dikirim_at') . " AS kirim_at,
                        " . $this->kolom('p', 'reviewed_at') . " AS katim_baca_at,
                        " . $this->kolom('p', 'disetujui_at') . " AS disetujui_at,
                        COALESCE((SELECT SUM(pb.jumlah) FROM opti_pembayaran pb
                                   WHERE pb.order_id = o.id AND pb.status_verifikasi = 'terverifikasi'), 0) AS total_terbayar,
                        (SELECT MAX(pb.tanggal_bayar) FROM opti_pembayaran pb
                          WHERE pb.order_id = o.id AND pb.status_verifikasi = 'terverifikasi') AS tanggal_bayar_terakhir
                 FROM po_kegiatan p
                 JOIN order_layanan o ON o.id = p.order_id
                 JOIN tb_customer c ON c.id_customer = o.id_customer
                 LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                 WHERE " . $where . "
                 ORDER BY " . $orderBy
            );
        } catch (\Exception $e) {
            $galat = $e->getMessage();
            return [];
        }

        $katim = $this->katimPerDivisi();
        $ids = array_values($katim);
        foreach ($rows as $r) { $ids[] = $r['ketua_pelaksana_id']; }
        $peta = $this->petaNamaUser($ids);

        $hasil = [];
        foreach ($rows as $r) {
            $div = (string) ($r['jenis_layanan_opti'] ?? '');
            $idp = (int) ($r['ketua_pelaksana_id'] ?? 0);
            $idk = isset($katim[$div]) ? (int) $katim[$div] : 0;
            $r['nama_pembuat'] = isset($peta[$idp]) ? $peta[$idp] : '';
            $r['nama_katim']   = isset($peta[$idk]) ? $peta[$idk] : '';
            $hasil[] = $this->petakan($r);
        }
        return $hasil;
    }

    /** Baris DB -> item JSON (daftar + bahan panel samping / tab Alur). */
    protected function petakan(array $r) {
        $nilai = !empty($r['nominal_penawaran']) ? (float) $r['nominal_penawaran'] : (float) $r['estimasi_biaya'];
        $terbayar = (float) $r['total_terbayar'];
        $lunas = $nilai > 0 && $terbayar >= $nilai;
        $punyaKesanggupan = !empty($r['surat_kesanggupan_bayar']);
        $verif = self::tanggalJam((string) ($r['verifikasi_humas_at'] ?? ''));
        $tglValidasi = self::tanggalJam((string) ($r['tervalidasi_mitra_at'] ?? ''));
        $ts = $tglValidasi !== '' ? strtotime($tglValidasi) : false;
        $divalidasi = ((string) $r['status']) !== 'terkirim' && $tglValidasi !== '';
        return [
            'id'                 => (int) $r['id'],
            'nomor'              => (string) $r['nomor_po'],
            'order'              => (string) $r['nomor_order'],
            'mitra'              => $this->formatMitra($r['nmcustomer'], $r['pt_cv']),
            'judul'              => (string) $r['judul_kegiatan'],
            'divisi'             => (string) $r['jenis_layanan_opti'],
            'nilai'              => $nilai,
            'terbayar'           => $terbayar,
            'lunas'              => $lunas,
            'punya_kesanggupan'  => $punyaKesanggupan,
            'bisa_validasi'      => $lunas || $punyaKesanggupan,
            'tanggal_bayar'      => $r['tanggal_bayar_terakhir'],
            // waktu PO masuk ke Tim Mitra = saat Humas memverifikasi (cadangan: waktu terakhir PO diubah)
            'dikirim'            => $verif !== '' ? $verif : self::tanggalJam((string) ($r['updated_at'] ?: $r['created_at'])),
            // dibaca hanya dihitung kalau terjadi SETELAH PO masuk ke Tim Mitra
            'dibaca_at'          => $this->bacaSetelahMasuk($r),
            'divalidasi'         => $divalidasi,
            'tgl_validasi'       => $tglValidasi,
            'tanggal'            => $tglValidasi !== '' ? substr($tglValidasi, 0, 10) : null,
            'bulan'              => $ts ? (int) date('n', $ts) : null,
            'tahun'              => $ts ? (int) date('Y', $ts) : null,
            'catatan'            => (string) ($r['catatan_tim_mitra'] ?? ''),
            // ---- bahan tab Alur dan header panel ----
            'pembuat'            => (string) ($r['nama_pembuat'] ?? ''),
            'katim'              => (string) ($r['nama_katim'] ?? ''),
            'dibuat_at'          => self::tanggalJam((string) ($r['created_at'] ?? '')),
            'kirim_at'           => self::tanggalJam((string) ($r['kirim_at'] ?? '')),
            'katim_baca_at'      => self::tanggalJam((string) ($r['katim_baca_at'] ?? '')),
            'disetujui_at'       => self::tanggalJam((string) ($r['disetujui_at'] ?? '')),
            'humas_baca_at'      => self::tanggalJam((string) ($r['humas_dibaca_at'] ?? '')),
            'humas_valid_at'     => $verif,
            'mitra_valid_at'     => $tglValidasi,
            'ppk_valid_at'       => self::tanggalJam((string) ($r['validasi_keuangan_at'] ?? '')),
        ];
    }

    /* ============================================================
     * API
     * ============================================================ */

    /** POST /po-kegiatan/@id/baca-mitra -- Tim Mitra membuka PO: catat waktu baca (sekali saja; dihitung ulang kalau PO masuk lagi). */
    public function baca($f3, $params) {
        list($po, $order) = $this->akses((int) $params['id']);
        if (!$this->pastikanKolomBaca()) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom penanda baca belum tersedia di database.'], 500);
        }
        $id = (int) $po['id'];
        $r = [];
        try {
            $this->db->exec(
                "UPDATE po_kegiatan SET mitra_dibaca_at = NOW()
                 WHERE id = ? AND status = 'terkirim' AND verifikasi_humas_at IS NOT NULL
                   AND (mitra_dibaca_at IS NULL OR mitra_dibaca_at < verifikasi_humas_at)",
                [1 => $id]
            );
            $r = $this->db->exec("SELECT mitra_dibaca_at, verifikasi_humas_at FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => $id]);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mencatat waktu baca.'], 500);
        }
        $this->keluarkanJson(['ok' => true, 'waktu' => !empty($r[0]) ? $this->bacaSetelahMasuk($r[0]) : null]);
    }

    /** GET /po-kegiatan/@id/validasi-detail */
    public function detailJson($f3, $params) {
        list($po, $order) = $this->akses((int) $params['id']);
        $this->wajibVerifikasiHumas($po);

        $sp = $this->safeExec(
            "SELECT surat_kesanggupan_bayar, nominal_penawaran FROM tb_surat_penawaran
             WHERE order_id = ? AND status_respon_klien = 'deal' ORDER BY id DESC LIMIT 1",
            [1 => (int) $order['id']]
        );
        $sp = !empty($sp) ? $sp[0] : [];

        $nilai = !empty($sp['nominal_penawaran']) ? (float) $sp['nominal_penawaran'] : (float) $order['estimasi_biaya'];

        $bayarRows = $this->safeExec(
            "SELECT id, termin_ke, jumlah, tanggal_bayar, status_verifikasi, bukti_bayar
             FROM opti_pembayaran WHERE order_id = ? ORDER BY termin_ke ASC",
            [1 => (int) $order['id']]
        );
        $totalTerbayar = 0;
        $termin = [];
        foreach ($bayarRows as $b) {
            if ($b['status_verifikasi'] === 'terverifikasi') { $totalTerbayar += (float) $b['jumlah']; }
            $termin[] = [
                'termin_ke'         => (int) $b['termin_ke'],
                'jumlah'            => (float) $b['jumlah'],
                'tanggal_bayar'     => $b['tanggal_bayar'],
                'status_verifikasi' => $b['status_verifikasi'],
                'url'               => !empty($b['bukti_bayar']) ? ($f3->get('BASE') . '/pembayaran/bukti/' . $this->buatTokenBukti((int) $b['id'])) : null,
            ];
        }

        $kesanggupanAda = !empty($sp['surat_kesanggupan_bayar']);

        // Riwayat untuk tab Alur: catatan otomatis sistem pada thread PO (disetujui, minta revisi, buka kembali, dst.)
        $riwayat = [];
        foreach ($this->safeExec("SELECT pesan, created_at FROM po_chat WHERE po_id = ? AND sistem = 1 ORDER BY id ASC", [1 => (int) $po['id']]) as $m) {
            $riwayat[] = ['pesan' => (string) $m['pesan'], 'waktu' => (string) $m['created_at']];
        }

        $this->keluarkanJson([
            'ok' => true,
            'riwayat' => $riwayat,
            'po' => [
                'id' => (int) $po['id'],
                'nomor' => (string) $po['nomor_po'],
                'catatan' => (string) ($po['catatan_tim_mitra'] ?? ''),
                'sudah_disetujui' => $po['status'] !== 'terkirim',
            ],
            'order' => ['nomor' => (string) $order['nomor_order'], 'judul' => (string) $order['judul_kegiatan']],
            'nilai' => $nilai,
            'total_terbayar' => $totalTerbayar,
            'lunas' => $nilai > 0 && $totalTerbayar >= $nilai,
            'kesanggupan' => [
                'ada' => $kesanggupanAda,
                'url' => $kesanggupanAda ? ($f3->get('BASE') . '/penawaran/surat-kesanggupan/' . (int) $order['id']) : null,
            ],
            'termin' => $termin,
            'bisa_validasi' => ($nilai > 0 && $totalTerbayar >= $nilai) || $kesanggupanAda,
        ]);
    }

    /** POST /po-kegiatan/@id/catatan -- simpan catatan tanpa validasi */
    public function simpanCatatan($f3, $params) {
        list($po, $order) = $this->akses((int) $params['id']);
        $this->wajibVerifikasiHumas($po);

        $catatan = trim((string) $f3->get('POST.catatan'));
        try {
            $this->db->exec("UPDATE po_kegiatan SET catatan_tim_mitra = ? WHERE id = ?", [1 => $catatan, 2 => (int) $po['id']]);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan catatan.'], 500);
        }
        $this->keluarkanJson(['ok' => true]);
    }

    /** POST /po-kegiatan/@id/validasi */
    public function validasi($f3, $params) {
        list($po, $order) = $this->akses((int) $params['id']);
        $uid = (int) $this->getUserId();

        if ($po['status'] === 'disetujui_mitra' || $po['status'] === 'disetujui') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini sudah divalidasi sebelumnya.'], 422);
        }
        if ($po['status'] !== 'terkirim') {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini belum dikirim, jadi belum bisa divalidasi.'], 422);
        }
        $this->wajibVerifikasiHumas($po);

        // cek ulang di server -- jangan percaya tombol frontend doang
        $sp = $this->safeExec(
            "SELECT surat_kesanggupan_bayar, nominal_penawaran FROM tb_surat_penawaran
             WHERE order_id = ? AND status_respon_klien = 'deal' ORDER BY id DESC LIMIT 1",
            [1 => (int) $order['id']]
        );
        $sp = !empty($sp) ? $sp[0] : [];
        $nilai = !empty($sp['nominal_penawaran']) ? (float) $sp['nominal_penawaran'] : (float) $order['estimasi_biaya'];
        $totalTerbayar = (float) $this->safeExecScalar(
            "SELECT COALESCE(SUM(jumlah),0) FROM opti_pembayaran WHERE order_id = ? AND status_verifikasi = 'terverifikasi'",
            [1 => (int) $order['id']]
        );
        $lunas = $nilai > 0 && $totalTerbayar >= $nilai;
        $kesanggupanAda = !empty($sp['surat_kesanggupan_bayar']);

        if (!$lunas && !$kesanggupanAda) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Belum bisa divalidasi: order ini belum lunas dan tidak ada surat kesanggupan bayar.'], 422);
        }

        $catatan = trim((string) $f3->get('POST.catatan'));

        // [PENTING] status -> 'disetujui_mitra' (bukan status_review); status_review & disposisi_humas_at
        // tidak disentuh (sudah diisi saat Ketua Tim menyetujui PO).
        try {
            $this->db->exec(
                "UPDATE po_kegiatan
                    SET status = 'disetujui_mitra',
                        tervalidasi_mitra_at = NOW(), tervalidasi_mitra_by = ?,
                        catatan_tim_mitra = ?,
                        reviewed_at = COALESCE(reviewed_at, NOW()), reviewed_by = COALESCE(reviewed_by, ?)
                  WHERE id = ? AND status = 'terkirim' AND verifikasi_humas_at IS NOT NULL",
                [1 => $uid, 2 => $catatan, 3 => $uid, 4 => (int) $po['id']]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan validasi: ' . $e->getMessage()], 500);
        }

        // catatan sistem di thread PO (kalau tabel po_chat ada) -- biar pembuat PO (tim_kerja) kebagian notifikasi
        if (class_exists('PoReviewController')) {
            try {
                \PoReviewController::catatPesanSistem($this->db, (int) $po['id'], $uid, 'katim', 'PO divalidasi Tim Mitra (pembayaran OK).');
            } catch (\Exception $e) {}
        }

        // [BARU] Notifikasi lonceng ke Keuangan + Superadmin -- PO ini sekarang nongol
        // di halaman "Daftar Review PO" mereka, menunggu persetujuan final.
        try {
            \NotificationService::send($this->db, [
                'order_id'        => $order['id'],
                'po_id'           => $po['id'],
                'target_role'     => 'keuangan',
                'target_layanan'  => 'semua',
                'judul'           => 'PO Siap Direview Keuangan',
                'pesan'           => "PO #{$po['nomor_po']} untuk Order #{$order['nomor_order']} ({$order['judul_kegiatan']}) sudah divalidasi Tim Mitra, siap direview Keuangan.",
                'tipe'            => 'info',
                'icon'            => 'bi-clipboard-check',
                'link_url'        => '/po-kegiatan/daftar',
                'created_by'      => $uid,
                'created_by_name' => $_SESSION['nama_lengkap'] ?? 'Tim Mitra',
            ]);
        } catch (\Exception $eNotif) {}

        $this->keluarkanJson(['ok' => true]);
    }

    /* ============================================================
     * HELPER
     * ============================================================ */

    /**
     * [FIX] Kolom po_kegiatan.status di beberapa server masih ENUM('draft','kirim')
     * (skema lama) padahal kode di sini pakai nilai 'terkirim'/'disetujui_mitra'.
     * Lebar-in jadi VARCHAR biar nilai baru 'disetujui_mitra' gak ditolak / ke-coerce
     * jadi string kosong oleh MySQL. Pola sama kayak ensureSchemaNomorPo() di
     * PoKegiatanController, jadi gak perlu migrasi manual di server produksi.
     */
    protected function ensureSchemaStatusMitra(): void {
        static $done = false;
        if ($done) return;
        try {
            $cols = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'status'");
            if (!empty($cols)) {
                $type = strtolower((string) $cols[0]['Type']);
                $perluWiden = strpos($type, 'enum') !== false && strpos($type, 'disetujui_mitra') === false;
                if ($perluWiden) {
                    $this->db->exec("ALTER TABLE po_kegiatan MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'draft'");
                }
            }
        } catch (\Exception $e) {
            // biarin -- kalau gagal alter (misal gak ada izin DDL), query tetap
            // dicoba jalan apa adanya, cuma gak self-healing.
        }
        $done = true;
    }

    protected function ambilPo($poId) {
        if ($poId <= 0) { return null; }
        $rows = $this->safeExec(
            "SELECT p.id, p.nomor_po, p.status, p.catatan_tim_mitra, p.order_id
             FROM po_kegiatan p WHERE p.id = ?" . \PoSchema::bukanSertifikasi('p') . " LIMIT 1",
            [1 => $poId]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    protected function ambilOrder($orderId) {
        $rows = $this->safeExec(
            "SELECT id, nomor_order, judul_kegiatan, estimasi_biaya, jenis_layanan_opti FROM order_layanan WHERE id = ? LIMIT 1",
            [1 => (int) $orderId]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /** Wajib login + role Tim Mitra/Superadmin + PO & order valid. Return [$po, $order] atau langsung jawab JSON error. */
    protected function akses($poId) {
        $this->izinkan(true);
        $this->ensureSchemaStatusMitra();
        $po = $this->ambilPo($poId);
        if (!$po) { $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan.'], 404); }
        $order = $this->ambilOrder($po['order_id']);
        if (!$order) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Order terkait tidak ditemukan.'], 404); }
        return [$po, $order];
    }

    protected function izinkan($json = false) {
        $this->requireAuth();
        if ($this->isSuperadmin() || $this->isTimMitra()) { return; }
        if ($json) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke halaman ini.'], 403); }
        $this->f3->error(403, 'Halaman ini hanya untuk Tim Mitra dan Superadmin.');
        exit;
    }

    /** true bila kolom po_kegiatan.verifikasi_humas_at ada (dibuat PoHumasController). */
    protected function kolomHumasAda() {
        static $ada = null;
        if ($ada === null) {
            try {
                $r = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'verifikasi_humas_at'");
                $ada = !empty($r);
            } catch (\Exception $e) {
                $ada = false;
            }
        }
        return $ada;
    }

    /** Ekspresi SELECT waktu verifikasi Humas, atau NULL bila kolomnya belum ada. */
    protected function kolomHumas($alias = 'p') {
        return $this->kolomHumasAda() ? $alias . '.verifikasi_humas_at' : 'NULL';
    }

    /** Syarat SQL: hanya PO yang sudah diverifikasi Humas. Kolom belum ada = belum ada PO yang diverifikasi. */
    protected function syaratHumas($alias = 'p') {
        return $this->kolomHumasAda() ? ' AND ' . $alias . '.verifikasi_humas_at IS NOT NULL' : ' AND 1 = 0';
    }

    /**
     * PO yang masih menunggu Tim Mitra (status 'terkirim') wajib sudah diverifikasi Humas.
     * PO yang sudah divalidasi (disetujui_mitra) tetap boleh dibuka dari tab "Sudah Disetujui".
     */
    protected function wajibVerifikasiHumas(array $po) {
        if ((string) $po['status'] !== 'terkirim') { return; }
        $r = $this->safeExec("SELECT verifikasi_humas_at FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $po['id']]);
        if (empty($r) || empty($r[0]['verifikasi_humas_at'])) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini belum diverifikasi Humas, jadi belum bisa diproses Tim Mitra.'], 422);
        }
    }

    /** Daftar kolom po_kegiatan (satu kali SHOW COLUMNS per request). */
    protected function kolomTersedia($nama) {
        static $ada = null;
        if ($ada === null) {
            $ada = [];
            try {
                foreach ($this->db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) { $ada[strtolower($c['Field'])] = true; }
            } catch (\Exception $e) {}
        }
        return isset($ada[strtolower($nama)]);
    }

    /** Ekspresi SELECT untuk kolom po_kegiatan yang mungkin belum ada di database lama (NULL kalau belum ada). */
    protected function kolom($alias, $nama) {
        return $this->kolomTersedia($nama) ? $alias . '.' . $nama : 'NULL';
    }

    /**
     * Pastikan kolom penanda baca ada (self-healing, pola sama dengan PoHumasController::pastikanKolom()).
     * Return false bila gagal dibuat (mis. izin ALTER kurang) -- halaman tetap jalan, hanya penanda baca yang mati.
     */
    protected function pastikanKolomBaca() {
        static $hasil = null;
        if ($hasil !== null) { return $hasil; }
        try {
            $ada = false;
            foreach ($this->db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) {
                if (strtolower($c['Field']) === 'mitra_dibaca_at') { $ada = true; }
            }
            if (!$ada) { $this->db->exec('ALTER TABLE po_kegiatan ADD COLUMN `mitra_dibaca_at` DATETIME NULL'); }
            $hasil = true;
        } catch (\Exception $e) {
            error_log('[PO Mitra] gagal menyiapkan kolom mitra_dibaca_at: ' . $e->getMessage());
            $hasil = false;
        }
        return $hasil;
    }

    /** Waktu baca Tim Mitra, atau null kalau belum dibaca / bacanya terjadi sebelum PO diverifikasi (masuk) ulang. */
    protected function bacaSetelahMasuk(array $r) {
        $baca = self::tanggalJam((string) ($r['mitra_dibaca_at'] ?? ''));
        if ($baca === '') { return null; }
        $masuk = self::tanggalJam((string) ($r['verifikasi_humas_at'] ?? ''));
        if ($masuk !== '' && strtotime($baca) < strtotime($masuk)) { return null; }
        return $baca;
    }

    /** Nilai DATETIME -> 'Y-m-d H:i:s', atau '' bila kosong/tidak valid. */
    protected static function tanggalJam($v) {
        $v = trim((string) $v);
        return ($v !== '' && strpos($v, '0000') !== 0 && strtotime($v)) ? date('Y-m-d H:i:s', strtotime($v)) : '';
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
        } catch (\Throwable $e) {
            // nama dikosongkan kalau database Sekretariat tidak terjangkau
        }
        return $peta;
    }

    /** Ketua Tim aktif per divisi (opti_user_map). */
    protected function katimPerDivisi() {
        $peta = ['selulosa' => 0, 'lingkungan' => 0];
        try {
            $rows = $this->db->exec("SELECT id_user, jenis_layanan_opti FROM opti_user_map WHERE role_opti = 'ketua_tim' AND is_active = 1 ORDER BY id ASC");
            foreach ($rows as $r) {
                $d = (string) $r['jenis_layanan_opti'];
                if (isset($peta[$d]) && $peta[$d] === 0) { $peta[$d] = (int) $r['id_user']; }
            }
        } catch (\Throwable $e) {
            // nama Ketua Tim kosong
        }
        return $peta;
    }

    protected function safeExec($sql, $params = []) {
        try { return $this->db->exec($sql, $params); } catch (\Exception $e) { return []; }
    }

    protected function safeExecScalar($sql, $params = []) {
        $r = $this->safeExec($sql, $params);
        if (empty($r)) { return 0; }
        $row = $r[0];
        return reset($row);
    }

    /** Sama persis dengan PembayaranOptiController::buatTokenBukti() -- kunci BUKTI_KEY yang sama. */
    protected function buatTokenBukti($id) {
        $secret = (string) $this->f3->get('BUKTI_KEY');
        if ($secret === '') { return (string) (int) $id; }
        $key = hash('sha256', $secret, true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt((string) (int) $id, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return rtrim(strtr(base64_encode($iv . $enc), '+/', '-_'), '=');
    }

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
