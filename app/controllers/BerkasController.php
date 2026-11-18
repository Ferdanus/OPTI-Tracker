<?php
/**
 * BerkasController
 *
 * Kumpulan berkas terkait 1 order: Surat Masuk (asli), Surat Penawaran, Proposal (Selulosa saja),
 * Surat Kesanggupan Bayar, PO Kegiatan Internal, Bukti Pembayaran (per termin), dan BAST.
 *
 *   GET /order/@id/berkas            -> JSON daftar kategori + status ada/tidak + metadata + URL file
 *   GET /order/@id/surat-masuk-pdf   -> stream file surat masuk dari loket balai
 *   GET /penawaran/@id/lampiran      -> stream file lampiran surat penawaran (id = tb_surat_penawaran.id atau order_id)
 *   GET /proposal/@id/file           -> stream file proposal riset (id = opti_proposal_riset.id atau order_id)
 *   GET /penawaran/surat-kesanggupan/@id -> stream surat kesanggupan bayar (id = order_id)
 *   GET /pembayaran/bukti/@id        -> stream bukti bayar (PembayaranOptiController::unduhBukti)
 */
class BerkasController extends Controller {

    /** GET /order/@id/berkas */
    public function index($f3, $params) {
        $this->requireAuth();

        $orderId = (int) $params['id'];
        if ($orderId <= 0) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Order tidak valid.'], 404);
        }

        $hasKolomSampel = !empty($this->safeExec("SHOW COLUMNS FROM order_layanan LIKE 'file_bukti_sampel'"));
        $hasKolomBuktiPermohonan = !empty($this->safeExec("SHOW COLUMNS FROM order_layanan LIKE 'file_bukti_permohonan'"));
        $order = $this->safeExec(
            "SELECT o.id, o.nomor_order, o.nomor_permintaan, o.judul_kegiatan, o.jenis_layanan_opti, o.id_surat_masuk, 
                    o.status, o.status_proposal_biaya, o.status_penawaran, o.status_keuangan"
                    . ($hasKolomBuktiPermohonan ? ", o.file_bukti_permohonan" : "")
                    . ($hasKolomSampel ? ", o.file_bukti_sampel, o.tanggal_spm_mulai" : "") . ", c.nmcustomer, c.pt_cv
             FROM order_layanan o
             LEFT JOIN tb_customer c ON o.id_customer = c.id_customer
             WHERE o.id = ? LIMIT 1",
            [1 => $orderId]
        );
        if (empty($order)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Order tidak ditemukan.'], 404);
        }
        $order = $order[0];
        $divisi = strtolower((string) ($order['jenis_layanan_opti'] ?? ''));
        $namaPerusahaan = \Customer::formatNamaPerusahaan($order['pt_cv'] ?? '', $order['nmcustomer'] ?? '');

        $items = [];
        // 1. Surat Masuk Permohonan (Awal mula masuknya berkas permohonan)
        $items[] = $this->itemSuratMasuk($order);

        // 1b. Bukti Permohonan Langsung (Jika diunggah manual pada Tahap 2)
        if (!empty($order['file_bukti_permohonan'])) {
            $items[] = [
                'kategori'   => 'bukti_permohonan',
                'label'      => 'Berkas Bukti Permohonan Langsung',
                'sub'        => 'Dokumen bukti permohonan layanan yang diunggah manual oleh Tim Mitra',
                'keterangan' => 'Berkas permohonan langsung dari mitra yang diunggah saat input order.',
                'ikon'       => 'bi-file-earmark-arrow-up-fill',
                'warna'      => 'slate',
                'file_name'  => basename((string) $order['file_bukti_permohonan']),
                'tipe'       => 'Lampiran Permohonan',
                'ada'        => true,
                'url'        => $this->f3->get('BASE') . '/' . ltrim((string) $order['file_bukti_permohonan'], '/\\')
            ];
        }

        // 2. Proposal Riset / Teknis (Jika ada proposal atau divisi selulosa)
        if ($divisi === 'selulosa' || $this->adaProposal($orderId)) {
            $items[] = $this->itemProposal($orderId, $order);
        }

        // 3. Surat Penawaran Harga (SPH) & Lampiran/Dokumen Deal
        $items[] = $this->itemPenawaran($orderId);
        foreach ($this->itemsDokumenPenawaranTambahan($orderId) as $extraSpItem) {
            $items[] = $extraSpItem;
        }

        // 4. Surat Kesanggupan Bayar (SKB)
        $items[] = $this->itemSuratKesanggupan($orderId);

        // 5. Bukti Pembayaran / Transfer (Keuangan & PNBP)
        $items[] = $this->itemPembayaran($orderId);

        // 5b. Gambar Bukti Fisik Sampel (Opsional saat Aktivasi SPM)
        if (!empty($order['file_bukti_sampel'])) {
            $items[] = [
                'kategori'   => 'bukti_sampel',
                'label'      => 'Gambar Bukti Fisik Sampel',
                'sub'        => 'Dokumentasi foto penerimaan sampel fisik pada tahap aktivasi SPM',
                'keterangan' => 'Foto bukti fisik sampel yang diunggah oleh petugas saat aktivasi titik awal SPM.',
                'ikon'       => 'bi-camera-fill',
                'warna'      => 'slate',
                'nomor'      => '-',
                'tanggal'    => !empty($order['tanggal_spm_mulai']) ? date('d M Y', strtotime($order['tanggal_spm_mulai'])) : null,
                'file_name'  => basename((string) $order['file_bukti_sampel']),
                'tipe'       => 'Foto / Gambar',
                'ada'        => true,
                'url'        => $this->f3->get('BASE') . '/' . ltrim((string) $order['file_bukti_sampel'], '/')
            ];
        }

        // 6. PO Kegiatan Internal & PO Bertanda Tangan + Kontrak PKS
        $items[] = $this->itemPoKegiatan($orderId);
        foreach ($this->itemsPoTtdDanKontrak($orderId) as $extraPoItem) {
            $items[] = $extraPoItem;
        }

        // 7. Berita Acara Serah Terima (BAST & Laporan Akhir)
        $items[] = $this->itemBast($orderId);

        $faseMap = [
            'surat_masuk'        => 'Registrasi & Permohonan Mitra',
            'bukti_permohonan'   => 'Bukti Permohonan Langsung',
            'proposal'           => 'Kajian Teknis & Metodologi Riset',
            'penawaran'          => 'Penawaran Tarif Resmi (SPH)',
            'lampiran_penawaran' => 'Lampiran Tambahan Penawaran',
            'penawaran_ttd'      => 'Surat Penawaran Bertanda Tangan',
            'bukti_persetujuan'  => 'Bukti Persetujuan / PO Klien',
            'kesanggupan_bayar'  => 'Kesepakatan & Komitmen Bayar (SKB)',
            'pembayaran'         => 'Verifikasi Keuangan & Setoran PNBP',
            'bukti_sampel'       => 'Bukti Fisik Sampel (Aktivasi SPM)',
            'po_kegiatan'        => 'Pelaksanaan Kerja Teknis (PO Balai)',
            'po_ttd'             => 'PO Kegiatan Bertanda Tangan',
            'pks_kontrak'        => 'Kontrak / Perjanjian Kerja Sama (PKS)',
            'bast'               => 'Serah Terima Resmi & Penutupan (BAST)'
        ];

        $step = 1;
        $totalSteps = count($items);
        foreach ($items as &$it) {
            $it['step_no'] = $step;
            $it['total_steps'] = $totalSteps;
            $it['tahap_badge'] = 'Tahap ' . $step;
            $it['fase_nama'] = $faseMap[$it['kategori']] ?? ('Tahap ' . $step);
            $step++;
        }
        unset($it);

        // Hitung total berkas yang sudah ada/diunggah
        $totalAda = 0;
        foreach ($items as $it) {
            if ($it['kategori'] === 'pembayaran') {
                $totalAda += count($it['items'] ?? []);
            } elseif (!empty($it['ada'])) {
                $totalAda++;
            }
        }

        $this->keluarkanJson([
            'ok'               => true,
            'order_id'         => $orderId,
            'nomor_order'      => (string) ($order['nomor_order'] ?: $order['nomor_permintaan']),
            'nomor_permintaan' => (string) ($order['nomor_permintaan'] ?? ''),
            'nama_perusahaan'  => $namaPerusahaan ?: 'Mitra Industri',
            'judul_kegiatan'   => (string) ($order['judul_kegiatan'] ?? ''),
            'divisi'           => $divisi,
            'total_tersedia'   => $totalAda,
            'items'            => $items,
        ]);
    }

    /** GET /order/@id/surat-masuk-pdf -- stream PDF Surat Masuk untuk order bersangkutan */
    public function streamSuratMasuk($f3, $params) {
        $this->requireAuth();
        $orderId = (int) $params['id'];
        $order = $this->safeExec("SELECT id_surat_masuk FROM order_layanan WHERE id = ? LIMIT 1", [1 => $orderId]);
        if (empty($order) || empty($order[0]['id_surat_masuk'])) {
            $f3->error(404, 'Surat masuk permohonan tidak terhubung dengan order ini.');
            return;
        }
        $suratId = (int) $order[0]['id_surat_masuk'];
        $ctrl = new \SuratMasukController();
        $ctrl->previewPdf($f3, ['id' => $suratId]);
    }

    /** GET /penawaran/@id/lampiran -- id = tb_surat_penawaran.id atau order_id */
    public function lampiranPenawaran($f3, $params) {
        $this->requireAuth();
        $id = (int) $params['id'];
        // Coba cari by id surat penawaran
        $r = $this->safeExec("SELECT file_lampiran FROM tb_surat_penawaran WHERE id = ? LIMIT 1", [1 => $id]);
        if (empty($r) || empty($r[0]['file_lampiran'])) {
            // Fallback: coba cari by order_id
            $r = $this->safeExec("SELECT file_lampiran FROM tb_surat_penawaran WHERE order_id = ? ORDER BY id DESC LIMIT 1", [1 => $id]);
        }
        $this->streamBerkasKolom(!empty($r) ? $r[0]['file_lampiran'] : null);
    }

    /** GET /proposal/@id/file -- id = opti_proposal_riset.id atau order_id */
    public function fileProposal($f3, $params) {
        $this->requireAuth();
        $id = (int) $params['id'];
        $r = $this->safeExec("SELECT file_proposal FROM opti_proposal_riset WHERE id = ? LIMIT 1", [1 => $id]);
        if (empty($r) || empty($r[0]['file_proposal'])) {
            $r = $this->safeExec("SELECT file_proposal FROM opti_proposal_riset WHERE order_id = ? ORDER BY id DESC LIMIT 1", [1 => $id]);
        }
        $this->streamBerkasKolom(!empty($r) ? $r[0]['file_proposal'] : null);
    }

    /** GET /penawaran/surat-kesanggupan/@id -- id = order_layanan.id atau tb_surat_penawaran.id */
    public function suratKesanggupan($f3, $params) {
        $this->requireAuth();
        $orderId = (int) $params['id'];
        $r = $this->safeExec(
            "SELECT surat_kesanggupan_bayar FROM tb_surat_penawaran
             WHERE (order_id = ? OR id = ?) AND surat_kesanggupan_bayar IS NOT NULL AND surat_kesanggupan_bayar <> ''
             ORDER BY id DESC LIMIT 1",
            [1 => $orderId, 2 => $orderId]
        );
        $this->streamBerkasKolom(!empty($r) ? $r[0]['surat_kesanggupan_bayar'] : null);
    }

    /* ============================================================
     * Penyusun Tiap Kategori Dokumen / Media
     * ============================================================ */

    protected function itemSuratMasuk(array $order) {
        $ada = false;
        $url = null;
        $nomor = '-';
        $tanggal = null;
        $perihal = null;
        $pengirim = null;
        $fileName = null;

        if (!empty($order['id_surat_masuk'])) {
            try {
                $s = $this->dbSekretariat->exec(
                    "SELECT a.id_arsip, a.nomor_surat, a.tanggal_surat, a.perihal, a.nama_berkas,
                            COALESCE(c.nmcustomer, 'Mitra Pemohon') AS pengirim, c.pt_cv
                     FROM tb_arsipsurat a
                     LEFT JOIN tb_customer c ON a.id_customer = c.id_customer
                     WHERE a.id_arsip = ? LIMIT 1",
                    [1 => (int) $order['id_surat_masuk']]
                );
                if (!empty($s)) {
                    $row = $s[0];
                    $nomor = $row['nomor_surat'] ?: '-';
                    $tanggal = $row['tanggal_surat'];
                    $perihal = $row['perihal'];
                    $pengirim = \Customer::formatNamaPerusahaan($row['pt_cv'] ?? '', $row['pengirim'] ?? '');
                    $fileName = !empty($row['nama_berkas']) ? basename($row['nama_berkas']) : null;
                    $ada = true;
                    // Gunakan streaming PDF surat masuk (resmi loket sekretariat)
                    $url = $this->f3->get('BASE') . '/surat-masuk/' . (int) $row['id_arsip'] . '/pdf';
                }
            } catch (\Exception $e) {
                error_log('[BerkasController::itemSuratMasuk] ' . $e->getMessage());
            }
        }

        $keterangan = $ada
            ? "Surat permohonan resmi dari mitra pemohon telah terdaftar di loket sekretariat."
            : "Order diproses secara mandiri atau belum ditautkan dengan registrasi surat masuk loket balai.";

        return [
            'kategori'   => 'surat_masuk',
            'label'      => 'Surat Masuk Permohonan',
            'sub'        => 'Dokumen permohonan kerjasama layanan dari mitra industri',
            'keterangan' => $keterangan,
            'ikon'       => 'bi-envelope-paper-fill',
            'warna'      => 'amber',
            'nomor'      => $nomor,
            'tanggal'    => $tanggal,
            'perihal'    => $perihal,
            'pengirim'   => $pengirim,
            'file_name'  => $fileName,
            'tipe'       => 'PDF Resmi',
            'ada'        => $ada,
            'url'        => $url
        ];
    }

    protected function itemPenawaran($orderId) {
        $ada = false;
        $url = null;
        $nomor = '-';
        $tanggal = null;
        $nominal = 0;
        $statusRespon = null;
        $perihal = null;
        $fileName = null;

        $r = $this->safeExec(
            "SELECT id, nomor_surat, tanggal_surat, perihal, nominal_penawaran, status_respon_klien, file_lampiran
             FROM tb_surat_penawaran
             WHERE order_id = ?
             ORDER BY (status_respon_klien = 'deal') DESC, id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (!empty($r)) {
            $sp = $r[0];
            $nomor = $sp['nomor_surat'] ?: '-';
            $tanggal = $sp['tanggal_surat'];
            $perihal = $sp['perihal'];
            $nominal = (float)($sp['nominal_penawaran'] ?? 0);
            $statusRespon = strtolower((string)($sp['status_respon_klien'] ?? 'draft'));

            if (!empty($sp['file_lampiran'])) {
                $ada = true;
                $fileName = basename($sp['file_lampiran']);
                $url = $this->f3->get('BASE') . '/penawaran/' . (int) $sp['id'] . '/lampiran';
            } elseif (!empty($sp['nomor_surat'])) {
                $ada = true;
                $url = $this->f3->get('BASE') . '/surat-penawaran/' . (int) $sp['id'] . '/pdf';
            }
        }

        $labelRespon = 'Draft';
        if ($statusRespon === 'deal') {
            $labelRespon = 'Disepakati (DEAL)';
            $keterangan = "Surat penawaran harga telah disepakati oleh mitra (Status: DEAL).";
        } elseif ($statusRespon === 'terkirim') {
            $labelRespon = 'Terkirim / Negosiasi';
            $keterangan = "Surat penawaran harga telah dikirimkan ke mitra dan menunggu konfirmasi persetujuan.";
        } elseif ($ada) {
            $labelRespon = 'Draft Penawaran';
            $keterangan = "Surat penawaran telah dirancang dan tersimpan dalam sistem.";
        } else {
            $labelRespon = 'Belum Diterbitkan';
            $keterangan = "Surat Penawaran Harga (SPH) belum diterbitkan oleh Tim Pemasaran.";
        }

        return [
            'kategori'      => 'penawaran',
            'label'         => 'Surat Penawaran Harga (SPH)',
            'sub'           => 'Dokumen resmi penawaran tarif PNBP dan ruang lingkup pekerjaan',
            'keterangan'    => $keterangan,
            'ikon'          => 'bi-file-earmark-text-fill',
            'warna'         => 'primary',
            'nomor'         => $nomor,
            'tanggal'       => $tanggal,
            'nominal'       => $nominal,
            'perihal'       => $perihal,
            'status_respon' => $statusRespon,
            'status_label'  => $labelRespon,
            'file_name'     => $fileName,
            'tipe'          => 'PDF Resmi',
            'ada'           => $ada,
            'url'           => $url
        ];
    }

    protected function itemSuratKesanggupan($orderId) {
        $ada = false;
        $url = null;
        $fileName = null;

        $r = $this->safeExec(
            "SELECT id, surat_kesanggupan_bayar FROM tb_surat_penawaran
             WHERE order_id = ? AND surat_kesanggupan_bayar IS NOT NULL AND surat_kesanggupan_bayar <> ''
             ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (!empty($r)) {
            $ada = true;
            $fileName = basename($r[0]['surat_kesanggupan_bayar']);
            $url = $this->f3->get('BASE') . '/penawaran/surat-kesanggupan/' . $orderId;
        }

        $keterangan = $ada
            ? "Surat pernyataan kesanggupan pembayaran dari mitra industri telah diunggah."
            : "Mitra memilih skema pelunasan langsung/termin reguler tanpa penjaminan Surat Kesanggupan Bayar.";

        return [
            'kategori'   => 'kesanggupan_bayar',
            'label'      => 'Surat Kesanggupan Bayar (SKB)',
            'sub'        => 'Surat komitmen penjaminan pembayaran dari mitra sebelum pekerjaan dimulai',
            'keterangan' => $keterangan,
            'ikon'       => 'bi-file-earmark-check-fill',
            'warna'      => 'indigo',
            'file_name'  => $fileName,
            'tipe'       => 'PDF / Scan Dokumen',
            'ada'        => $ada,
            'url'        => $url
        ];
    }

    protected function itemsDokumenPenawaranTambahan($orderId): array {
        $hasil = [];
        $r = $this->safeExec(
            "SELECT id, nomor_surat, file_lampiran_user, file_penawaran_ttd, file_bukti_persetujuan
             FROM tb_surat_penawaran
             WHERE order_id = ?
             ORDER BY (status_respon_klien = 'deal') DESC, id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (empty($r)) { return $hasil; }
        $sp = $r[0];
        $spId = (int) $sp['id'];

        if (!empty($sp['file_lampiran_user'])) {
            $hasil[] = [
                'kategori'   => 'lampiran_penawaran',
                'label'      => 'Lampiran Tambahan Penawaran (Hal. 3+)',
                'sub'        => 'Berkas lampiran tambahan yang digabungkan ke Surat Penawaran Resmi',
                'keterangan' => 'Lampiran tambahan yang diunggah Tim Mitra pada formulir Surat Penawaran.',
                'ikon'       => 'bi-paperclip',
                'warna'      => 'slate',
                'nomor'      => $sp['nomor_surat'] ?: '-',
                'file_name'  => basename((string) $sp['file_lampiran_user']),
                'ada'        => true,
                'url'        => $this->f3->get('BASE') . '/' . ltrim((string) $sp['file_lampiran_user'], '/\\')
            ];
        }
        if (!empty($sp['file_penawaran_ttd'])) {
            $hasil[] = [
                'kategori'   => 'penawaran_ttd',
                'label'      => 'Surat Penawaran Bertanda Tangan',
                'sub'        => 'Arsip Surat Penawaran Resmi yang telah ditandatangani (Tahap 5 Deal)',
                'keterangan' => 'Surat Penawaran Bertanda Tangan telah ditautkan dari arsip surat keluar.',
                'ikon'       => 'bi-file-earmark-check-fill',
                'warna'      => 'slate',
                'nomor'      => $sp['nomor_surat'] ?: '-',
                'file_name'  => basename((string) $sp['file_penawaran_ttd']),
                'ada'        => true,
                'url'        => $this->f3->get('BASE') . '/surat-penawaran/' . $spId . '/dokumen-deal/penawaran_ttd/preview'
            ];
        }
        if (!empty($sp['file_bukti_persetujuan'])) {
            $hasil[] = [
                'kategori'   => 'bukti_persetujuan',
                'label'      => 'Bukti Persetujuan / PO Klien',
                'sub'        => 'Dokumen bukti persetujuan resmi atau Purchase Order dari pihak mitra',
                'keterangan' => 'Bukti persetujuan klien untuk penawaran DEAL telah diunggah.',
                'ikon'       => 'bi-file-earmark-ruled-fill',
                'warna'      => 'slate',
                'nomor'      => $sp['nomor_surat'] ?: '-',
                'file_name'  => basename((string) $sp['file_bukti_persetujuan']),
                'ada'        => true,
                'url'        => $this->f3->get('BASE') . '/surat-penawaran/' . $spId . '/dokumen-deal/bukti_persetujuan/preview'
            ];
        }
        return $hasil;
    }

    protected function itemsPoTtdDanKontrak($orderId): array {
        $hasil = [];
        $hasTtd = !empty($this->safeExec("SHOW COLUMNS FROM po_kegiatan LIKE 'po_ttd_file'"));
        $r = $this->safeExec(
            "SELECT id, nomor_po" . ($hasTtd ? ", po_ttd_file" : "") . " FROM po_kegiatan WHERE order_id = ? ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (empty($r)) { return $hasil; }
        $po = $r[0];
        $poId = (int) $po['id'];

        if (!empty($po['po_ttd_file'])) {
            $hasil[] = [
                'kategori'   => 'po_ttd',
                'label'      => 'PO Kegiatan Bertanda Tangan',
                'sub'        => 'Dokumen PO Kegiatan yang sudah ditandatangani Ketua Tim (Tahap 8)',
                'keterangan' => 'PO bertanda tangan telah diunggah dan diteruskan ke proses Kontrak/PKS.',
                'ikon'       => 'bi-pen-fill',
                'warna'      => 'slate',
                'nomor'      => $po['nomor_po'] ?: '-',
                'file_name'  => basename((string) $po['po_ttd_file']),
                'ada'        => true,
                'url'        => $this->f3->get('BASE') . '/order/' . $orderId . '/po-ttd'
            ];
        }

        $k = $this->safeExec(
            "SELECT id, nomor_pks, status, tanggal_perjanjian FROM pks_kontrak WHERE po_id = ? AND status = 'selesai' LIMIT 1",
            [1 => $poId]
        );
        if (!empty($k)) {
            $pks = $k[0];
            $hasil[] = [
                'kategori'   => 'pks_kontrak',
                'label'      => 'Perjanjian Kerja Sama (PKS)',
                'sub'        => 'Dokumen Kontrak / PKS resmi yang diterbitkan oleh Humas (Tahap 9)',
                'keterangan' => 'Dokumen Kontrak / PKS telah selesai dibuat dan siap dicetak/diunduh.',
                'ikon'       => 'bi-file-earmark-lock2-fill',
                'warna'      => 'slate',
                'nomor'      => $pks['nomor_pks'] ?: '-',
                'tanggal'    => $pks['tanggal_perjanjian'] ?? null,
                'ada'        => true,
                'url'        => $this->f3->get('BASE') . '/pks/' . $poId . '/pdf'
            ];
        }

        return $hasil;
    }

    protected function itemPoKegiatan($orderId) {
        $ada = false;
        $url = null;
        $nomor = '-';
        $tanggal = null;
        $statusPo = null;

        $r = $this->safeExec(
            "SELECT id, nomor_po, tanggal_po, status, status_review FROM po_kegiatan
             WHERE order_id = ? ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (!empty($r)) {
            $po = $r[0];
            $nomor = $po['nomor_po'] ?: '-';
            $tanggal = $po['tanggal_po'];
            $statusPo = $po['status'] ?: 'aktif';
            $ada = true;
            $url = $this->f3->get('BASE') . '/po-kegiatan/' . (int)$po['id'] . '/cetak';
        }

        $keterangan = $ada
            ? "PO Kegiatan Internal resmi telah diterbitkan oleh Ketua Tim untuk instruksi kerja tim pelaksana."
            : "PO Kegiatan Internal belum diterbitkan oleh Ketua Tim Layanan (menunggu tahapan kesepakatan/keuangan).";

        return [
            'kategori'   => 'po_kegiatan',
            'label'      => 'PO Kegiatan Internal (Lembar Kerja)',
            'sub'        => 'Surat perintah dan disposisi penugasan internal kepada Tim Teknis & Laboratorium',
            'keterangan' => $keterangan,
            'ikon'       => 'bi-briefcase-fill',
            'warna'      => 'purple',
            'nomor'      => $nomor,
            'tanggal'    => $tanggal,
            'status_po'  => $statusPo,
            'tipe'       => 'PDF Lembar Kerja',
            'ada'        => $ada,
            'url'        => $url
        ];
    }

    protected function adaProposal($orderId): bool {
        $r = $this->safeExec("SELECT id FROM opti_proposal_riset WHERE order_id = ? LIMIT 1", [1 => $orderId]);
        return !empty($r);
    }

    protected function itemProposal($orderId, array $order = []) {
        $ada = false;
        $url = null;
        $judul = null;
        $statusProposal = null;
        $fileName = null;

        $r = $this->safeExec(
            "SELECT id, file_proposal, judul_proposal, status_proposal, disetujui_ketua_at, created_at, updated_at 
             FROM opti_proposal_riset
             WHERE order_id = ? ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (!empty($r)) {
            $pr = $r[0];
            $judul = $pr['judul_proposal'] ?? null;
            $statusProposal = strtolower(trim((string)($pr['status_proposal'] ?? 'draft')));
            $statusPropBiaya = strtolower(trim((string)($order['status_proposal_biaya'] ?? '')));
            $statusOrd = strtolower(trim((string)($order['status'] ?? '')));
            if (!empty($pr['disetujui_ketua_at'])
                || in_array($statusPropBiaya, ['siap_penawaran', 'disetujui'], true)
                || in_array($statusOrd, ['siap_penawaran', 'penawaran', 'negosiasi', 'deal', 'po_terbit', 'selesai'], true)
                || ($order['status_penawaran'] ?? '') === 'deal') {
                $statusProposal = 'disetujui_ketua';
            } elseif ($statusPropBiaya === 'menunggu_approval' && in_array($statusProposal, ['', 'draft', 'draft_disimpan'], true)) {
                $statusProposal = 'diajukan';
            }

            if (!empty($pr['file_proposal'])) {
                $ada = true;
                $fileName = basename($pr['file_proposal']);
                $url = $this->f3->get('BASE') . '/order/' . (int) $orderId . '/proposal/file';
            } else {
                $ada = true;
                $url = $this->f3->get('BASE') . '/order/' . $orderId . '/proposal';
            }
        }

        $statusLabelMap = [
            'disetujui_ketua'    => 'Disetujui Ka. Tim',
            'disetujui'          => 'Disetujui Ka. Tim',
            'disetujui_pimpinan' => 'Disetujui',
            'diajukan'           => 'Menunggu Review Ka. Tim',
            'menunggu_approval'  => 'Menunggu Review Ka. Tim',
            'perlu_revisi'       => 'Perlu Revisi',
            'revisi'             => 'Perlu Revisi',
            'draft_disimpan'     => 'Draft Disimpan',
            'draft'              => 'Draft'
        ];
        $statusTeks = $statusLabelMap[$statusProposal] ?? ucwords(str_replace('_', ' ', (string)$statusProposal));

        $keterangan = $ada
            ? "Proposal kajian teknis & metodologi riset telah disusun ({$statusTeks})."
            : "Proposal riset/kajian teknis belum disusun oleh PIC Teknis.";

        return [
            'kategori'        => 'proposal',
            'label'           => 'Proposal Riset / Teknis',
            'sub'             => 'Dokumen kajian metodologi, parameter analisis, dan perencanaan teknis',
            'keterangan'      => $keterangan,
            'ikon'            => 'bi-journal-text',
            'warna'           => 'slate',
            'judul'           => $judul,
            'status_proposal' => $statusTeks,
            'file_name'       => $fileName,
            'tipe'            => 'PDF / Word Dokumen',
            'ada'             => $ada,
            'url'             => $url
        ];
    }

    /** Bukti pembayaran: bisa lebih dari satu termin */
    protected function itemPembayaran($orderId) {
        $daftar = [];
        $rows = $this->safeExec(
            "SELECT id, termin_ke, jumlah, tanggal_bayar, status_verifikasi, metode_pembayaran, bukti_bayar, nomor_transaksi_ntpn
             FROM opti_pembayaran
             WHERE order_id = ? AND bukti_bayar IS NOT NULL AND bukti_bayar <> '' ORDER BY termin_ke ASC",
            [1 => $orderId]
        );
        foreach ($rows as $r) {
            $daftar[] = [
                'id'                => (int) $r['id'],
                'termin_ke'         => (int) $r['termin_ke'],
                'jumlah'            => (float) $r['jumlah'],
                'jumlah_format'     => 'Rp ' . number_format((float)$r['jumlah'], 0, ',', '.'),
                'tanggal_bayar'     => $r['tanggal_bayar'],
                'metode'            => ucwords(str_replace('_', ' ', (string)($r['metode_pembayaran'] ?: 'transfer_bank'))),
                'ntpn'              => $r['nomor_transaksi_ntpn'] ?: '-',
                'status_verifikasi' => $r['status_verifikasi'] ?: 'terverifikasi',
                'file_name'         => basename($r['bukti_bayar']),
                'url'               => $this->f3->get('BASE') . '/pembayaran/bukti/' . (int)$r['id'],
            ];
        }

        $keterangan = !empty($daftar)
            ? "Ditemukan " . count($daftar) . " bukti transaksi pembayaran yang telah diunggah dan diverifikasi."
            : "Belum ada bukti transaksi / pembayaran yang diverifikasi bagian keuangan.";

        return [
            'kategori'   => 'pembayaran',
            'label'      => 'Bukti Pembayaran / Transfer',
            'sub'        => 'Struk transfer / bukti transaksi perbankan atau NTPN per termin',
            'keterangan' => $keterangan,
            'ikon'       => 'bi-receipt-cutoff',
            'warna'      => 'success',
            'ada'        => !empty($daftar),
            'items'      => $daftar
        ];
    }

    protected function itemBast($orderId) {
        $ada = false;
        $url = null;
        $nomor = '-';
        $tanggal = null;
        $statusBast = null;
        $fileName = null;

        $r = $this->safeExec(
            "SELECT id, nomor_bast, tanggal_bast, status_bast, file_dokumen_bast FROM opti_bast
             WHERE order_id = ? ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (!empty($r)) {
            $bast = $r[0];
            $nomor = $bast['nomor_bast'] ?: '-';
            $tanggal = $bast['tanggal_bast'];
            $statusBast = $bast['status_bast'] ?: 'draf';
            $ada = true;
            if (!empty($bast['file_dokumen_bast'])) {
                $fileName = basename($bast['file_dokumen_bast']);
                $url = $this->f3->get('BASE') . '/' . ltrim($bast['file_dokumen_bast'], '/\\');
            } else {
                $url = $this->f3->get('BASE') . '/order/' . $orderId . '/bast/buat';
            }
        }

        $keterangan = $ada
            ? "Berita Acara Serah Terima (BAST) telah diterbitkan sebagai tanda selesainya seluruh layanan."
            : "Layanan masih berjalan. BAST akan diterbitkan saat penyerahan hasil laporan akhir.";

        return [
            'kategori'    => 'bast',
            'label'       => 'Berita Acara Serah Terima (BAST)',
            'sub'         => 'Dokumen resmi penutupan order dan serah terima hasil pekerjaan ke mitra',
            'keterangan'  => $keterangan,
            'ikon'        => 'bi-patch-check-fill',
            'warna'       => 'teal',
            'nomor'       => $nomor,
            'tanggal'     => $tanggal,
            'status_bast' => $statusBast,
            'file_name'   => $fileName,
            'tipe'        => 'PDF Resmi',
            'ada'         => $ada,
            'url'         => $url
        ];
    }

    /* ============================================================
     * Helper & Stream Resolver
     * ============================================================ */

    protected function safeExec($sql, $params = []) {
        try {
            return $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }

    /** Stream file dari kolom DB (path relatif). Memeriksa semua kemungkinan lokasi di server. */
    protected function streamBerkasKolom($relPath) {
        if (!$relPath) {
            $this->f3->error(404, 'Berkas tidak tersedia.');
            return;
        }
        $relPath = ltrim((string) $relPath, "/\\");
        $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
        $docRoot = rtrim(str_replace('\\', '/', (string) $this->f3->get('ROOT')), '/\\');
        $baseName = basename($relPath);

        $kandidat = [
            $appRoot . '/' . $relPath,
            $appRoot . '/public/' . $relPath,
            $appRoot . '/storage/' . $relPath,
            $appRoot . '/storage/kesanggupan_bayar/' . $baseName,
            $appRoot . '/storage/pembayaran/' . $baseName,
            $appRoot . '/uploads/' . $baseName,
            $appRoot . '/uploads/penawaran/' . $baseName,
            $appRoot . '/uploads/surat_masuk/' . $baseName,
            $appRoot . '/uploads/proposals/' . $baseName,
            $appRoot . '/uploads/proposal/' . $baseName,
            $appRoot . '/public/uploads/proposals/' . $baseName,
            $appRoot . '/uploads/bukti_bayar/' . $baseName,
            $appRoot . '/uploads/bast/' . $baseName,
            $docRoot . '/' . $relPath,
            $docRoot . '/silopti/' . $relPath,
            $docRoot . '/silopti/storage/kesanggupan_bayar/' . $baseName,
            $docRoot . '/silopti/uploads/penawaran/' . $baseName,
            $docRoot . '/Mini OPTI Tracker/' . $relPath,
            $docRoot . '/Mini OPTI Tracker/storage/kesanggupan_bayar/' . $baseName,
            $docRoot . '/Mini OPTI Tracker/uploads/penawaran/' . $baseName,
            $relPath
        ];

        $path = null;
        foreach ($kandidat as $k) {
            if (!empty($k) && is_file($k)) {
                $path = $k;
                break;
            }
        }

        if (!$path) {
            $this->f3->error(404, 'Berkas tidak ditemukan di server. Path yang dicoba: ' . implode(', ', $kandidat));
            return;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf', 
            'jpg'  => 'image/jpeg', 
            'jpeg' => 'image/jpeg', 
            'png'  => 'image/png',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        header('Content-Type: ' . (isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
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