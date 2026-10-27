<?php
/**
 * PoHumasController -- Daftar PO untuk Humas.
 *
 * Humas HANYA melihat & memverifikasi PO (bahan acuan pembuatan Kontrak). PO yang sudah dikirim
 * (status terkirim / disetujui_mitra / disetujui) otomatis masuk ke halaman ini, barengan dengan alur
 * Tim Mitra -- verifikasi Humas TIDAK mengubah status PO dan tidak mempengaruhi alur Tim Mitra / Keuangan.
 *
 * Halaman punya 3 tab (data dikirim ke view sebagai JSON; filter bulan/tahun/baca dikerjakan di sisi browser):
 *   - "Daftar PO Baru"      -> PO terkirim yang belum diverifikasi Humas (verifikasi_humas_at IS NULL). Filter: baca, bulan, tahun.
 *   - "Sudah Diverifikasi"  -> PO yang sudah diverifikasi (default bulan berjalan, filter bulan/tahun).
 *   - "PO Selesai"          -> PO yang sudah divalidasi PPK BLU (validasi_keuangan_at terisi; default bulan berjalan). Filter: baca, bulan, tahun.
 *
 * Tiap PO punya "Map Kendali" (form F.PJT-12-03/1) yang tanggalnya otomatis dari database:
 * Nomor & judul kegiatan di Map Kendali diambil dari PO; "Disiapkan Oleh" = Tim Kerja OPTI Lingkungan/Selulosa sesuai layanan.
 *   - PROPOSAL                 : opti_proposal_riset.disetujui_ketua_at (proposal terbaru order itu; jam tidak ditampilkan)
 *   - PETUNJUK OPERASIONAL
 *       Adm KS & Humas         : po_kegiatan.verifikasi_humas_at (tanggal PO diverifikasi Humas; kosong sebelum diverifikasi)
 *       Tim Mitra Industri     : po_kegiatan.tervalidasi_mitra_at
 *       PPK BLU                : po_kegiatan.validasi_keuangan_at
 *       Ka. BBSPJIS            : kosong
 *   - KONTRAK & DISTRIBUSI     : kosong
 *
 * Kolom baru di po_kegiatan (dibuat otomatis kalau belum ada, self-healing): verifikasi_humas_at,
 * verifikasi_humas_by, catatan_humas, humas_dibaca_at (penanda "sudah dibaca Humas": baris tebal + titik biru). Hanya PO OPTI (PO Sertifikasi dst. tidak ikut).
 *
 *   GET  /po-humas                      daftar (2 tab)
 *   POST /po-humas/@id/baca             catat waktu PO pertama kali dibuka Humas
 *   POST /po-humas/@id/verifikasi       verifikasi PO (+ catatan opsional)
 *   GET  /po-humas/@id/berkas           JSON panel samping: nilai kontrak, surat kesanggupan bayar, bukti pembayaran per termin, riwayat sistem (tab Alur)
 *
 * AKSES: Superadmin dan role 'humas' (Sub Tim Humas) -- lihat izinAkses() dan menu sidebar (layout.html).
 * Kompatibel PHP 7.2.
 */
class PoHumasController extends Controller {

    /** Status PO yang sudah dikirim ke Mitra (dan ke Humas). */
    const STATUS_TERKIRIM = "('terkirim','disetujui_mitra','disetujui')";

    /** Superadmin dan role 'humas' (Sub Tim Humas). */
    protected function izinAkses() {
        return $this->isSuperadmin() || $this->getUserRole() === 'humas';
    }

    protected function izinkan($json = false) {
        $this->requireAuth();
        if ($this->izinAkses()) { return; }
        if ($json) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke halaman ini.'], 403); }
        $this->setFlashError('Halaman Daftar PO Humas hanya bisa diakses Sub Tim Humas dan Superadmin.');
        $this->f3->reroute('/dashboard');
        exit;
    }

    
    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /po-humas */
    public function index($f3) {
        $this->izinkan();
        $skemaOk = $this->pastikanKolom();

        $baru = [];
        $selesai = [];
        $errorMessage = $skemaOk ? null : 'Kolom verifikasi Humas belum bisa dibuat otomatis di tabel po_kegiatan (izin ALTER database kurang). Jalankan: ALTER TABLE po_kegiatan ADD COLUMN verifikasi_humas_at DATETIME NULL, ADD COLUMN verifikasi_humas_by INT UNSIGNED NULL, ADD COLUMN catatan_humas TEXT NULL;';

        if ($skemaOk) {
            try {
                $rows = $this->db->exec(
                    "SELECT p.id, p.order_id, p.nomor_po, p.status, p.created_at, p.updated_at,
                            p.tervalidasi_mitra_at, p.validasi_keuangan_at, 
                            " . $this->kolomPpk() . ",
                            " . $this->kolomBacaHilir() . "
                            p.verifikasi_humas_at, p.catatan_humas, " . $this->kolomCatatanMitra() . " AS catatan_tim_mitra,
                            p.humas_dibaca_at, p.disposisi_humas_at,
                            p.created_at AS dibuat_at, p.dikirim_at AS kirim_at, p.reviewed_at AS katim_baca_at, p.disetujui_at,
                            o.ketua_pelaksana_id, o.nomor_order, " . $this->kolomJudul() . " AS judul_po, o.jenis_layanan_opti,
                            c.nmcustomer, c.pt_cv,
                            " . $this->kolomProposal() . " AS tgl_proposal
                     FROM po_kegiatan p
                     JOIN order_layanan o ON o.id = p.order_id
                     JOIN tb_customer c ON c.id_customer = o.id_customer
                     WHERE p.status IN " . self::STATUS_TERKIRIM . " AND p.status_review = 'disetujui'" . \PoSchema::bukanSertifikasi('p') . "
                     ORDER BY p.updated_at DESC, p.id DESC"
                );
            } catch (\Exception $e) {
                $rows = [];
                $errorMessage = 'Gagal memuat daftar: ' . $e->getMessage();
            }

            $katim = $this->katimPerDivisi();
            $ids = array_values($katim);
            foreach ($rows as $r) { $ids[] = $r['ketua_pelaksana_id']; }
            $peta = $this->petaNamaUser($ids);

            foreach ($rows as $r) {
                $div = (string) ($r['jenis_layanan_opti'] ?? '');
                $idp = (int) ($r['ketua_pelaksana_id'] ?? 0);
                $idk = isset($katim[$div]) ? (int) $katim[$div] : 0;
                $r['nama_pembuat'] = isset($peta[$idp]) ? $peta[$idp] : '';
                $r['nama_katim']   = isset($peta[$idk]) ? $peta[$idk] : '';
                $item = $this->petakan($r);
                if ($item['diverifikasi']) { $selesai[] = $item; } else { $baru[] = $item; }
            }
            // yang sudah diverifikasi: terbaru di atas berdasarkan waktu verifikasi
            usort($selesai, function ($a, $b) { return strcmp((string) $b['tgl_verifikasi'], (string) $a['tgl_verifikasi']); });
        }

        $f3->set('daftar_baru_json', json_encode($baru, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('daftar_selesai_json', json_encode($selesai, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('bulan_ini', (int) date('n'));
        $f3->set('tahun_ini', (int) date('Y'));
        $f3->set('error_message', $errorMessage);
        $this->render('humas/daftar_po.html', 'Daftar PO Humas', 'po_humas');
    }

    /** POST /po-humas/@id/verifikasi */
    public function verifikasi($f3, $params) {
        $this->izinkan(true);
        if (!$this->pastikanKolom()) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom verifikasi Humas belum tersedia di database.'], 500);
        }
        $id = (int) $params['id'];
        $rows = $this->safeExec(
            "SELECT p.id, p.status, p.verifikasi_humas_at, p.nomor_po, p.order_id FROM po_kegiatan p
             WHERE p.id = ? AND p.status IN " . self::STATUS_TERKIRIM . \PoSchema::bukanSertifikasi('p') . " LIMIT 1",
            [1 => $id]
        );
        if (empty($rows)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan atau belum dikirim.'], 404);
        }
        if (!empty($rows[0]['verifikasi_humas_at'])) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO ini sudah diverifikasi sebelumnya.'], 422);
        }

        $catatan = trim((string) $f3->get('POST.catatan'));
        $uid = (int) $this->getUserId();
        try {
            $this->db->exec(
                "UPDATE po_kegiatan SET verifikasi_humas_at = NOW(), verifikasi_humas_by = ?, catatan_humas = ?,
                        humas_dibaca_at = COALESCE(humas_dibaca_at, NOW())
                 WHERE id = ? AND verifikasi_humas_at IS NULL",
                [1 => $uid, 2 => ($catatan === '' ? null : $catatan), 3 => $id]
            );
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan verifikasi: ' . $e->getMessage()], 500);
        }
        \PoReviewController::catatRiwayatOrder($this->db, $id, $uid, 'Humas memverifikasi PO. Diteruskan ke Tim Mitra.', 'verif_humas');   // Riwayat Aktivitas detail order
        $this->kabariTimMitra($rows[0]);
        $this->keluarkanJson(['ok' => true, 'waktu' => date('Y-m-d H:i:s'), 'tanggal' => date('Y-m-d')]);
    }

    /**
     * Setelah Humas memverifikasi, PO giliran Tim Mitra: kirim notifikasi lonceng ke Tim Mitra.
     * (Dulu dikirim saat PO baru dikirim; sekarang urutannya Ketua Tim -> Humas -> Tim Mitra -> PPK BLU.)
     */
    protected function kabariTimMitra(array $po) {
        try {
            $o = $this->safeExec("SELECT nomor_order, judul_kegiatan FROM order_layanan WHERE id = ? LIMIT 1", [1 => (int) $po['order_id']]);
            $o = !empty($o) ? $o[0] : [];
            \NotificationService::send($this->db, [
                'order_id'        => (int) $po['order_id'],
                'po_id'           => (int) $po['id'],
                'target_role'     => 'ketua_tim_mitra',
                'target_layanan'  => 'semua',
                'judul'           => 'PO Menunggu Verifikasi',
                'pesan'           => 'PO #' . $po['nomor_po'] . ' untuk Order #' . (isset($o['nomor_order']) ? $o['nomor_order'] : '-')
                                   . ' (' . (isset($o['judul_kegiatan']) ? $o['judul_kegiatan'] : '') . ') sudah diverifikasi Humas, menunggu verifikasi Tim Mitra.',

                'tipe'            => 'primary',
                'icon'            => 'bi-clipboard-check',
                'link_url'        => '/po-kegiatan/validasi-mitra',
                'created_by'      => $this->getUserId(),
                'created_by_name' => isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'Humas',
            ]);
        } catch (\Throwable $e) {
            // notifikasi bersifat pelengkap -- jangan gagalkan verifikasi
        }
    }

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

    /** GET /po-humas/@id/berkas -- isi sama dengan "Lihat file" di Daftar Review PO / Validasi PO Mitra. */
    public function berkas($f3, $params) {
        $this->izinkan(true);
        $id = (int) $params['id'];
        $po = $this->safeExec(
            "SELECT p.id, p.nomor_po, p.order_id FROM po_kegiatan p
             WHERE p.id = ? AND p.status IN " . self::STATUS_TERKIRIM . \PoSchema::bukanSertifikasi('p') . " LIMIT 1",
            [1 => $id]
        );
        if (empty($po)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan atau belum dikirim.'], 404);
        }
        $po = $po[0];
        $orderId = (int) $po['order_id'];

        $o = $this->safeExec("SELECT nomor_order, judul_kegiatan, estimasi_biaya FROM order_layanan WHERE id = ? LIMIT 1", [1 => $orderId]);
        $o = !empty($o) ? $o[0] : [];
        $sp = $this->safeExec(
            "SELECT surat_kesanggupan_bayar, nominal_penawaran FROM tb_surat_penawaran
             WHERE order_id = ? AND status_respon_klien = 'deal' ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        $sp = !empty($sp) ? $sp[0] : [];
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
                // lewat endpoint halaman ini (hak akses Humas), BUKAN /pembayaran/bukti yang khusus Keuangan dan Superadmin
                'url'               => !empty($b['bukti_bayar']) ? ($f3->get('BASE') . '/po-humas/' . $id . '/berkas/bukti/' . (int) $b['id']) : null,
            ];
        }
        $kesanggupanAda = !empty($sp['surat_kesanggupan_bayar']);

        // Riwayat untuk tab Alur: catatan otomatis sistem pada thread PO (disetujui, minta revisi, buka kembali, dst.)
        $riwayat = [];
        foreach ($this->safeExec("SELECT pesan, created_at FROM po_chat WHERE po_id = ? AND sistem = 1 ORDER BY id ASC", [1 => (int) $po['id']]) as $m) {
            $riwayat[] = ['pesan' => (string) $m['pesan'], 'waktu' => (string) $m['created_at']];
        }

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
                'url' => $kesanggupanAda ? ($f3->get('BASE') . '/penawaran/surat-kesanggupan/' . $orderId) : null,
            ],
            'termin'  => $termin,
            'dokumen' => $this->dokumenOrder($orderId, $kesanggupanAda ? ($f3->get('BASE') . '/penawaran/surat-kesanggupan/' . $orderId) : null),
            'riwayat' => $riwayat,
        ]);
    }

    /** GET /po-humas/@id/berkas/bukti/@pid[?unduh=1] -- file bukti pembayaran satu termin (harus milik order PO ini). Hak akses: Humas dan Superadmin. */
    public function berkasBukti($f3, $params) {
        $this->izinkan(true);
        $po = $this->safeExec(
            "SELECT p.id, p.nomor_po, p.order_id FROM po_kegiatan p
             WHERE p.id = ? AND p.status IN " . self::STATUS_TERKIRIM . \PoSchema::bukanSertifikasi('p') . " LIMIT 1",
            [1 => (int) $params['id']]
        );
        if (empty($po)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan atau belum dikirim.'], 404);
        }
        $po = $po[0];
        $r = $this->safeExec(
            "SELECT bukti_bayar, termin_ke FROM opti_pembayaran
             WHERE id = ? AND order_id = ? AND (is_kesanggupan_bayar = 0 OR is_kesanggupan_bayar IS NULL) LIMIT 1",
            [1 => (int) $params['pid'], 2 => (int) $po['order_id']]
        );
        $this->kirimBerkas(!empty($r) ? $r[0]['bukti_bayar'] : null, 'Bukti-Pembayaran-Termin-' . (!empty($r) ? (int) $r[0]['termin_ke'] : 0) . '-' . $po['nomor_po']);
    }

    /** Kirim berkas bukti bayar ke browser (sama dengan PoReviewController::kirimBerkas). */
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
            $appRoot . '/storage/pembayaran/' . $nama,
            $appRoot . '/uploads/' . $nama,
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
        $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
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

    /* ============================================================
     * HELPER
     * ============================================================ */

    /** Sama persis dengan PembayaranOptiController::buatTokenBukti() -- kunci BUKTI_KEY yang sama. */
    protected function buatTokenBukti($id) {
        $secret = (string) $this->f3->get('BUKTI_KEY');
        if ($secret === '') { return (string) (int) $id; }
        $key = hash('sha256', $secret, true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt((string) (int) $id, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return rtrim(strtr(base64_encode($iv . $enc), '+/', '-_'), '=');
    }

    /** Baris DB -> item JSON (termasuk seluruh tanggal Map Kendali). */
    protected function petakan(array $r) {
        $divisi = strtolower((string) ($r['jenis_layanan_opti'] ?? ''));
        $verif = (string) ($r['verifikasi_humas_at'] ?? '');
        $tglVerif = self::tanggalJam($verif);   // lengkap dengan jam (halaman menampilkan "Diverifikasi 2 Okt 10:45")
        $ts = $tglVerif !== '' ? strtotime($tglVerif) : false;
        return [
            'id'             => (int) $r['id'],
            'order_id'       => (int) ($r['order_id'] ?? 0),
            'nomor'          => (string) $r['nomor_po'],
            'order'          => (string) $r['nomor_order'],
            'mitra'          => $this->formatMitra($r['nmcustomer'], $r['pt_cv']),
            'judul'          => (string) $r['judul_po'],
            'divisi'         => $divisi,
            'disiapkan'      => $divisi !== '' ? 'Tim Kerja OPTI ' . ucfirst($divisi) : 'Tim Kerja OPTI',
            // waktu PO masuk ke Humas = saat disetujui Ketua Tim (disposisi_humas_at); cadangan: waktu terakhir PO diubah
            'dikirim'        => self::tanggalJam((string) (($r['disposisi_humas_at'] ?? '') ?: ($r['updated_at'] ?: $r['created_at']))),
            // dibaca hanya dihitung kalau terjadi SETELAH PO masuk (disetujui) ke Humas. Kalau PO dibuka kembali lalu
            // disetujui ulang, disposisi_humas_at jadi lebih baru -> otomatis "belum dibaca" lagi, tanpa menyentuh alur review.
            'dibaca_at'      => $this->bacaSetelahMasuk($r),
            'diverifikasi'   => $verif !== '',
            'tgl_verifikasi' => $tglVerif,
            'bulan'          => $ts ? (int) date('n', $ts) : null,
            'tahun'          => $ts ? (int) date('Y', $ts) : null,
            'catatan'        => (string) ($r['catatan_humas'] ?? ''),
            // ---- bahan tab Alur dan header panel ----
            'pembuat'        => (string) ($r['nama_pembuat'] ?? ''),
            'katim'          => (string) ($r['nama_katim'] ?? ''),
            'dibuat_at'      => self::tanggalJam((string) ($r['dibuat_at'] ?? '')),
            'kirim_at'       => self::tanggalJam((string) ($r['kirim_at'] ?? '')),
            'katim_baca_at'  => self::tanggalJam((string) ($r['katim_baca_at'] ?? '')),
            'disetujui_at'   => self::tanggalJam((string) ($r['disetujui_at'] ?? '')),
            'mitra_valid_at' => self::tanggalJam((string) ($r['tervalidasi_mitra_at'] ?? '')),
            'ppk_valid_at'   => self::tanggalJam((string) ($r['validasi_keuangan_at'] ?? '')),
            // revisi PPK BLU: hanya untuk teks "Status PO saat ini" (Humas tidak terlibat di tahap ini)
            'ppk_status'     => (string) ($r['ppk_status'] ?? ''),
            'ronde_ppk'      => (int) ($r['ronde_ppk'] ?? 0),
            // sudah dibuka pemegang berikutnya? Tim Mitra: hanya dihitung bila dibuka SETELAH Humas memverifikasi. PPK BLU: penanda di-reset tiap perpindahan tahap.
            'mitra_baca_at'  => (trim((string) ($r['mitra_dibaca_at'] ?? '')) !== '' && $verif !== '' && strcmp((string) $r['mitra_dibaca_at'], $verif) >= 0) ? self::tanggalJam((string) $r['mitra_dibaca_at']) : '',
            'ppk_baca_at'    => trim((string) ($r['tervalidasi_mitra_at'] ?? '')) !== '' ? self::tanggalJam((string) ($r['ppk_dibaca_at'] ?? '')) : '',
            // catatan Tim Mitra baru tampil setelah Tim Mitra benar-benar memvalidasi PO
            'catatan_mitra'  => trim((string) ($r['tervalidasi_mitra_at'] ?? '')) !== '' ? trim((string) ($r['catatan_tim_mitra'] ?? '')) : '',
            // ---- Map Kendali (hanya tanggal, jam tidak dipakai) ----
            'map' => [
                'proposal' => self::tanggal((string) ($r['tgl_proposal'] ?? '')),
                'humas'    => self::tanggal((string) ($r['verifikasi_humas_at'] ?? '')),
                'mitra'    => self::tanggal((string) ($r['tervalidasi_mitra_at'] ?? '')),
                'keuangan' => self::tanggal((string) ($r['validasi_keuangan_at'] ?? '')),
            ],
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

    /** Nilai DATE/DATETIME -> 'Y-m-d' (jam dibuang), atau '' bila kosong/tidak valid. */
    protected static function tanggal($v) {
        $v = trim((string) $v);
        return ($v !== '' && strpos($v, '0000') !== 0 && strtotime($v)) ? date('Y-m-d', strtotime($v)) : '';
    }

    /** Waktu baca Humas, atau null kalau belum dibaca / bacanya terjadi sebelum PO disetujui ulang. */
    protected function bacaSetelahMasuk(array $r) {
        $baca = self::tanggalJam((string) ($r['humas_dibaca_at'] ?? ''));
        if ($baca === '') { return null; }
        $masuk = self::tanggalJam((string) ($r['disposisi_humas_at'] ?? ''));
        if ($masuk !== '' && strtotime($baca) < strtotime($masuk)) { return null; }
        return $baca;
    }

    /** Nilai DATETIME -> 'Y-m-d H:i:s', atau '' bila kosong/tidak valid. */
    protected static function tanggalJam($v) {
        $v = trim((string) $v);
        return ($v !== '' && strpos($v, '0000') !== 0 && strtotime($v)) ? date('Y-m-d H:i:s', strtotime($v)) : '';
    }

    /** Judul kegiatan diambil dari PO (po_kegiatan.judul_kegiatan); kalau kosong/kolom tidak ada, pakai judul order. */
    protected function kolomJudul() {
        $ada = false;
        try {
            $r = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'judul_kegiatan'");
            $ada = !empty($r);
        } catch (\Exception $e) {}
        return $ada ? "COALESCE(NULLIF(TRIM(p.judul_kegiatan), ''), o.judul_kegiatan)" : 'o.judul_kegiatan';
    }

    /** Kolom catatan Tim Mitra; NULL bila kolomnya belum ada (kolom dibuat otomatis oleh halaman Validasi Mitra). */
    protected function kolomCatatanMitra() {
        try {
            $r = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'catatan_tim_mitra'");
            if (!empty($r)) { return 'p.catatan_tim_mitra'; }
        } catch (\Exception $e) {}
        return 'NULL';
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
     * Pastikan kolom yang dibutuhkan ada di po_kegiatan (self-healing, pola sama dengan PoReviewController).
     * Return false bila ada kolom yang gagal dibuat (mis. izin ALTER kurang).
     */
    protected function pastikanKolom() {
        static $hasil = null;
        if ($hasil !== null) { return $hasil; }
        $perlu = [
            'validasi_keuangan_at' => 'DATETIME NULL',
            'verifikasi_humas_at'  => 'DATETIME NULL',
            'verifikasi_humas_by'  => 'INT UNSIGNED NULL',
            'catatan_humas'        => 'TEXT NULL',
            'humas_dibaca_at'      => 'DATETIME NULL',
        ];
        try {
            $ada = [];
            foreach ($this->db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) { $ada[strtolower($c['Field'])] = true; }
            $tambah = [];
            foreach ($perlu as $nama => $tipe) {
                if (!isset($ada[$nama])) { $tambah[] = "ADD COLUMN `{$nama}` {$tipe}"; }
            }
            if ($tambah) { $this->db->exec('ALTER TABLE po_kegiatan ' . implode(', ', $tambah)); }
            $hasil = true;
        } catch (\Exception $e) {
            error_log('[PO Humas] gagal menyiapkan kolom po_kegiatan: ' . $e->getMessage());
            $hasil = false;
        }
        return $hasil;
    }
        /** Potongan SELECT revisi PPK BLU. Kolom dari migration_po_revisi_ppk.sql; kalau belum ada diganti nilai kosong. */
        protected function kolomPpk() {
            $ada = $this->safeExec("SHOW COLUMNS FROM po_kegiatan LIKE 'ppk_status'");
            return !empty($ada) ? 'p.ppk_status, p.ronde_ppk' : "'' AS ppk_status, 0 AS ronde_ppk";
        }

    /** Penanda "sudah dibuka" Tim Mitra dan PPK BLU (untuk kolom Posisi PO di tab Sedang Berjalan). Kolom yang belum ada diganti NULL. */
    protected function kolomBacaHilir() {
        $s = '';
        foreach (['mitra_dibaca_at', 'ppk_dibaca_at'] as $k) {
            $ada = $this->safeExec("SHOW COLUMNS FROM po_kegiatan LIKE '" . $k . "'");
            $s .= (!empty($ada) ? 'p.' . $k : 'NULL') . ' AS ' . $k . ', ';
        }
        return $s;
    }

    protected function safeExec($sql, $params = []) {
        try { return $this->db->exec($sql, $params); } catch (\Exception $e) { return []; }
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

    /** POST /po-humas/@id/baca -- Humas membuka PO: catat waktu baca (sekali saja, tidak ditimpa). */
    public function baca($f3, $params) {
        $this->izinkan(true);
        if (!$this->pastikanKolom()) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Kolom penanda baca belum tersedia di database.'], 500);
        }
        $id = (int) $params['id'];
        try {
            $this->db->exec(
                "UPDATE po_kegiatan SET humas_dibaca_at = NOW()
                 WHERE id = ? AND status_review = 'disetujui'
                   AND (humas_dibaca_at IS NULL OR (disposisi_humas_at IS NOT NULL AND humas_dibaca_at < disposisi_humas_at))",
                [1 => $id]
            );
            $r = $this->db->exec("SELECT humas_dibaca_at, disposisi_humas_at FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => $id]);
        } catch (\Exception $e) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal mencatat waktu baca.'], 500);
        }
        $this->keluarkanJson(['ok' => true, 'waktu' => !empty($r[0]) ? $this->bacaSetelahMasuk($r[0]) : null]);
    }
}