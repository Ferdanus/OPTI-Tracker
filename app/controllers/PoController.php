<?php

/**
 * Controller untuk mengelola Dokumen PO, Map Kendali, RAB Breakdown, Jadwal Tim Kerja, Evaluasi, dan Ekspor Excel
 */
class PoController extends Controller {

    /**
     * Dashboard & Daftar semua PO dengan multi-filter
     * Route: GET /po
     */
    public function index($f3) {
        $filterBulan        = $f3->get('GET.bulan') ?? '';
        $filterTahun        = $f3->get('GET.tahun') ?? '';
        $filterStatus       = $f3->get('GET.status') ?? '';
        $filterJenisLayanan = $f3->get('GET.jenis_layanan') ?? '';
        $filterOverdue      = $f3->get('GET.overdue') ?? '';
        $search             = $f3->get('GET.q') ?? '';

        $poModel = new Po($this->db);
        $daftarPo = $poModel->allWithRelasi($filterBulan, $filterTahun, $filterStatus, $filterJenisLayanan, $search, $filterOverdue);

        $listBulan = array(
            1 => 'Januari (I)', 2 => 'Februari (II)', 3 => 'Maret (III)',
            4 => 'April (IV)', 5 => 'Mei (V)', 6 => 'Juni (VI)',
            7 => 'Juli (VII)', 8 => 'Agustus (VIII)', 9 => 'September (IX)',
            10 => 'Oktober (X)', 11 => 'November (XI)', 12 => 'Desember (XII)'
        );

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();

        // Dashboard metrics
        $selulosaCount = (int)($this->db->exec("SELECT COUNT(*) AS total FROM order_layanan WHERE jenis_layanan_opti = 'selulosa'")[0]['total'] ?? 0);
        $lingkunganCount = (int)($this->db->exec("SELECT COUNT(*) AS total FROM order_layanan WHERE jenis_layanan_opti = 'lingkungan'")[0]['total'] ?? 0);
        $orderBaruCount = (int)($this->db->exec("SELECT COUNT(*) AS total FROM order_layanan WHERE status = 'baru'")[0]['total'] ?? 0);
        $poBerjalanCount = (int)($this->db->exec("SELECT COUNT(*) AS total FROM po WHERE status != 'kembali_selesai'")[0]['total'] ?? 0);
        
        $f3->set('daftar_po', $daftarPo);
        $f3->set('list_bulan', $listBulan);
        $f3->set('list_status', Po::$URUTAN_STATUS);
        $f3->set('filter_bulan', $filterBulan);
        $f3->set('filter_tahun', $filterTahun);
        $f3->set('filter_status', $filterStatus);
        $f3->set('filter_jenis_layanan', $filterJenisLayanan);
        $f3->set('filter_overdue', $filterOverdue);
        $f3->set('search_q', $search);
        $f3->set('mask_client_name', $maskEnabled);

        $userModel = new ArsipUser($this->db);
        $katimSelulosa = $userModel->getKetuaTim('selulosa');
        $katimLingkungan = $userModel->getKetuaTim('lingkungan');

        $f3->set('selulosa_count', $selulosaCount);
        $f3->set('lingkungan_count', $lingkunganCount);
        $f3->set('order_baru_count', $orderBaruCount);
        $f3->set('po_berjalan_count', $poBerjalanCount);
        $f3->set('katim_selulosa_nama', $katimSelulosa['nama_user'] ?? 'Andri Taufick Rizaluddin');
        $f3->set('katim_lingkungan_nama', $katimLingkungan['nama_user'] ?? 'Rina Masriani');

        $this->render('po/index.html', 'Dashboard & Monitoring PO - OPTI Tracker BBSPJIS', 'po');
    }

    /**
     * Menampilkan detail PO lengkap: Info, SPM, Labs, Map Kendali, RAB, Jadwal Tim, Pembayaran, Evaluasi, dan Audit Log
     * Route: GET /po/@id
     */
    public function detail($f3, $params) {
        $id = (int)($params['id'] ?? 0);

        $poModel = new Po($this->db);
        $po = $poModel->getDetail($id);

        if (!$po) {
            $this->setFlashError("Dokumen PO #{$id} tidak ditemukan.");
            $f3->reroute('/po');
            return;
        }

        // 1. RAB Breakdown
        $rabModel = new PoRincianAnggaran($this->db);
        $daftarRab = $rabModel->getByPoId($id);
        $totalRab = $rabModel->hitungTotalBiaya($id);

        // 2. Jadwal Kerja Tim
        $jadwalModel = new PoJadwalKerja($this->db);
        $daftarJadwal = $jadwalModel->getByPoId($id);

        // 3. Riwayat Pembayaran Multi-Termin
        $pembayaranModel = new OptiPembayaran($this->db);
        $daftarPembayaran = $pembayaranModel->getByPoId($id);
        $totalTerbayar = $pembayaranModel->hitungTotalTerbayar((int)$po['order_id']);
        $sisaTagihan = max(0, (float)$po['biaya'] - $totalTerbayar);

        // 4. Riwayat Audit Log
        $logModel = new PoLogStatus($this->db);
        $daftarLog = $logModel->getByPoId($id);

        // 5. Kalkulasi Overdue
        $overdueInfo = Po::hitungOverdue(
            $po['target_selesai'],
            $po['realisasi_selesai'],
            $po['status'],
            $po['spm_layanan']
        );

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();

        // 6. Alur SOP Progress (19 Tahapan Resmi BBSPJIS)
        $sopModel = new PoSopProgress($this->db);
        $sopModel->initForPo($id, $po['status']);
        $daftarSop = $sopModel->getByPoId($id);
        $sopStatistik = $sopModel->getStatistik($id);

        $f3->set('po', $po);
        $f3->set('daftar_rab', $daftarRab);
        $f3->set('total_rab', $totalRab);
        $f3->set('kategori_rab_list', PoRincianAnggaran::$KATEGORI_LIST);
        $f3->set('daftar_jadwal', $daftarJadwal);
        $f3->set('daftar_pembayaran', $daftarPembayaran);
        $f3->set('total_terbayar', $totalTerbayar);
        $f3->set('sisa_tagihan', $sisaTagihan);
        $f3->set('daftar_log', $daftarLog);
        $f3->set('logs', $daftarLog);
        $f3->set('overdue_info', $overdueInfo);
        $f3->set('urutan_status', Po::$URUTAN_STATUS);
        $f3->set('mask_client_name', $maskEnabled);
        $f3->set('daftar_sop', $daftarSop);
        $f3->set('sop_statistik', $sopStatistik);

        $this->render('po/detail.html', "Detail PO {$po['nomor_po']} - OPTI Tracker", 'po');
    }

    /**
     * Tambah item rincian anggaran biaya (RAB)
     * Route: POST /po/@id/rab/tambah
     */
    public function tambahRab($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $kategori  = trim($post['kategori'] ?? '');
        $deskripsi = trim($post['deskripsi'] ?? '');
        $nominal   = (float)($post['nominal'] ?? 0);

        $this->requirePermission('po:rab', "/po/{$poId}");

        if (empty($kategori) || empty($deskripsi) || $nominal <= 0) {
            $this->setFlashError('Kategori, uraian pekerjaan, dan nominal biaya wajib diisi.');
            $f3->reroute("/po/{$poId}");
            return;
        }

        try {
            $rabModel = new PoRincianAnggaran($this->db);
            $rabModel->tambahItem($poId, array(
                'kategori'  => $kategori,
                'deskripsi' => $deskripsi,
                'nominal'   => $nominal
            ));

            $this->setFlashSuccess("Item rincian anggaran berhasil ditambahkan.");
            $f3->reroute("/po/{$poId}#rab-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menambahkan rincian RAB: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Hapus item rincian anggaran biaya (RAB)
     * Route: POST /po/@id/rab/@rab_id/hapus
     */
    public function hapusRab($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $rabId = (int)($params['rab_id'] ?? 0);

        $this->requirePermission('po:rab', "/po/{$poId}");

        try {
            $rabModel = new PoRincianAnggaran($this->db);
            $rabModel->hapusItem($rabId, $poId);

            $this->setFlashSuccess("Item rincian anggaran berhasil dihapus.");
            $f3->reroute("/po/{$poId}#rab-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menghapus item RAB: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Tambah jadwal kerja tim pelaksana
     * Route: POST /po/@id/jadwal/tambah
     */
    public function tambahJadwal($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $this->requirePermission('po:jadwal', "/po/{$poId}");

        try {
            $jadwalModel = new PoJadwalKerja($this->db);
            $jadwalModel->tambahJadwal($poId, array(
                'personil_anggota' => trim($post['personil_anggota'] ?? ''),
                'tahap_kegiatan'   => trim($post['tahap_kegiatan'] ?? ''),
                'tanggal_mulai'    => $post['tanggal_mulai'] ?? date('Y-m-d'),
                'tanggal_selesai'  => $post['tanggal_selesai'] ?? date('Y-m-d'),
                'status_pekerjaan' => $post['status_pekerjaan'] ?? 'rencana',
                'keterangan'       => trim($post['keterangan'] ?? '')
            ));

            $this->setFlashSuccess('Jadwal kegiatan tim berhasil ditambahkan.');
            $f3->reroute("/po/{$poId}#jadwal-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menambahkan jadwal: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Update status item jadwal kerja tim
     * Route: POST /po/@id/jadwal/@jadwal_id/status
     */
    public function updateJadwalStatus($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $jadwalId = (int)($params['jadwal_id'] ?? 0);
        $status = $f3->get('POST.status') ?? 'rencana';

        $this->requirePermission('po:jadwal', "/po/{$poId}");

        try {
            $jadwalModel = new PoJadwalKerja($this->db);
            $jadwalModel->updateStatus($jadwalId, $status);

            $this->setFlashSuccess('Status jadwal kegiatan berhasil diperbarui.');
            $f3->reroute("/po/{$poId}#jadwal-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui status jadwal: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Hapus jadwal kegiatan tim
     * Route: POST /po/@id/jadwal/@jadwal_id/hapus
     */
    public function hapusJadwal($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $jadwalId = (int)($params['jadwal_id'] ?? 0);

        $this->requirePermission('po:jadwal', "/po/{$poId}");

        try {
            $jadwalModel = new PoJadwalKerja($this->db);
            $jadwalModel->hapus($jadwalId);

            $this->setFlashSuccess('Jadwal kegiatan berhasil dihapus.');
            $f3->reroute("/po/{$poId}#jadwal-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menghapus jadwal: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Verifikasi & Validasi tahap Map Kendali berjenjang
     * Route: POST /po/@id/map/@stage
     * TODO: Konfirmasi ke user asli apakah approver juga boleh reject dengan catatan revisi (bukan cuma ya/tidak).
     */
    public function approveMap($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $stage = $params['stage'] ?? '';

        $this->requirePermission('po:approve', "/po/{$poId}");

        try {
            $poModel = new Po($this->db);
            $poModel->updateMapKendali($poId, $stage);

            $this->setFlashSuccess("Tahapan Map Kendali <strong>{$stage}</strong> berhasil diverifikasi & divalidasi.");
            $f3->reroute("/po/{$poId}#map-kendali-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal verifikasi Map Kendali: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Verifikasi tahapan SOP Lingkungan (19 Tahap Resmi BBSPJIS)
     * Route: POST /po/@id/sop/@tahap/verifikasi
     */
    public function verifikasiSopTahap($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $tahapNo = (int)($params['tahap'] ?? 0);
        $post = $f3->get('POST');

        $this->requirePermission('po:sop', "/po/{$poId}");

        try {
            $catatan = trim($post['catatan'] ?? '');
            $verifiedBy = $_SESSION['nama_user'] ?? 'Petugas Balai';

            $sopModel = new PoSopProgress($this->db);
            $sopModel->verifikasiTahap($poId, $tahapNo, $verifiedBy, $catatan);

            // Jika tahap 19 selesai, auto selesaikan status PO
            if ($tahapNo === 19) {
                $poModel = new Po($this->db);
                $poModel->updateData($poId, array(
                    'status'            => 'kembali_selesai',
                    'realisasi_selesai' => date('Y-m-d'),
                    'catatan'           => 'Seluruh 19 tahapan SOP OPTI Lingkungan telah selesai diverifikasi & BAST telah diarsipkan.'
                ));
            } elseif ($tahapNo >= 3) {
                // Pastikan status PO minimal on_proses
                $poModel = new Po($this->db);
                $poData = $poModel->getById($poId);
                if ($poData && in_array($poData->status, array('belum_upload', 'sudah_upload'))) {
                    $poModel->updateData($poId, array(
                        'status'          => 'on_proses',
                        'tanggal_keluar'  => !empty($poData->tanggal_keluar) ? $poData->tanggal_keluar : date('Y-m-d'),
                        'catatan'         => "Pelaksanaan SOP Tahap {$tahapNo} berjalan (Status: On Proses)."
                    ));
                }
            }

            $this->setFlashSuccess("Tahapan SOP <strong>#{$tahapNo}</strong> berhasil diverifikasi & dicatat.");
            $f3->reroute("/po/{$poId}#sop-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memverifikasi tahapan SOP: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Minta revisi tahapan SOP Lingkungan (Feedback Loop Alur)
     * Route: POST /po/@id/sop/@tahap/revisi
     */
    public function revisiSopTahap($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $tahapNo = (int)($params['tahap'] ?? 0);
        $post = $f3->get('POST');

        $this->requirePermission('po:sop', "/po/{$poId}");

        try {
            $catatanRevisi = trim($post['catatan_revisi'] ?? '');
            $targetTahapKembali = (int)($post['target_tahap'] ?? max(1, $tahapNo - 1));
            $requestedBy = $_SESSION['nama_user'] ?? 'Petugas Balai';

            if (empty($catatanRevisi)) {
                throw new \Exception("Catatan / notulen perbaikan revisi wajib diisi.");
            }

            $sopModel = new PoSopProgress($this->db);
            $sopModel->revisiTahap($poId, $tahapNo, $targetTahapKembali, $requestedBy, $catatanRevisi);

            // Kembalikan status PO ke on_proses jika sebelumnya selesai
            $poModel = new Po($this->db);
            $poModel->updateData($poId, array(
                'status'          => 'on_proses',
                'tanggal_kembali' => date('Y-m-d'),
                'catatan'         => "Tahap {$tahapNo} memerlukan revisi: {$catatanRevisi}. Alur kembali ke Tahap {$targetTahapKembali}."
            ));

            $this->setFlashSuccess("Catatan revisi Tahap <strong>#{$tahapNo}</strong> disimpan. Alur dikembalikan ke Tahap #{$targetTahapKembali}.");
            $f3->reroute("/po/{$poId}#sop-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memproses revisi SOP: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Lewati tahap laporan perkembangan (Tahap 7 - 12) jika tidak dipersyaratkan di kontrak SPK
     * Route: POST /po/@id/sop/skip-perkembangan
     */
    public function skipSopPerkembangan($f3, $params) {
        $poId = (int)($params['id'] ?? 0);

        $this->requirePermission('po:sop', "/po/{$poId}");

        try {
            $actorName = $_SESSION['nama_user'] ?? 'Ketua Tim OPTI';
            $sopModel = new PoSopProgress($this->db);
            $sopModel->skipPerkembangan($poId, $actorName);

            $this->setFlashSuccess("Tahapan Laporan Perkembangan (Tahap 7 s.d. 12) dilewati. Alur langsung ke Tahap #13 (Pemeriksaan Laporan Kegiatan).");
            $f3->reroute("/po/{$poId}#sop-section");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal melewati tahapan: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Proses evaluasi dengan customer (Feedback Loop SOP)
     * Route: POST /po/@id/evaluasi
     */
    public function updateEvaluasi($f3, $params) {
        $poId = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $this->requirePermission('po:evaluasi', "/po/{$poId}");

        $evaluasiStatus = $post['evaluasi_status'] ?? 'disetujui';
        $notulen        = trim($post['notulen_evaluasi'] ?? '');
        $tglEvaluasi    = $post['tgl_evaluasi'] ?? date('Y-m-d');

        try {
            $poModel = new Po($this->db);
            
            if ($evaluasiStatus === 'disetujui') {
                // Lanjut ke tahap laporan akhir & selesai
                $poModel->updateData($poId, array(
                    'evaluasi_status'   => 'disetujui',
                    'notulen_evaluasi'  => $notulen,
                    'tgl_evaluasi'      => $tglEvaluasi,
                    'status'            => 'kembali_selesai',
                    'realisasi_selesai' => date('Y-m-d'),
                    'catatan'           => 'Evaluasi disetujui customer. Laporan akhir disusun dan PO selesai.'
                ));
                $this->setFlashSuccess('Hasil evaluasi disetujui customer! Dokumen PO berhasil diselesaikan.');
            } else {
                // Tidak disetujui -> kembali ke tahap pelaksanaan (iterasi ulang/revisi)
                $poModel->updateData($poId, array(
                    'evaluasi_status'  => 'perlu_revisi',
                    'notulen_evaluasi' => $notulen,
                    'tgl_evaluasi'     => $tglEvaluasi,
                    'status'           => 'on_proses',
                    'tanggal_kembali'  => date('Y-m-d'),
                    'catatan'          => "Evaluasi belum disetujui (Revisi): {$notulen}. Dokumen kembali ke tahap pelaksanaan."
                ));
                $this->setFlashSuccess('Catatan revisi evaluasi disimpan. Status PO dikembalikan ke tahap Pelaksanaan (On Proses).');
            }

            $f3->reroute("/po/{$poId}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memproses evaluasi: ' . $e->getMessage());
            $f3->reroute("/po/{$poId}");
        }
    }

    /**
     * Memproses update data PO & Status
     * Route: POST /po/@id/update
     */
    public function update($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        try {
            $poModel = new Po($this->db);
            $poModel->updateData($id, $post);

            $this->setFlashSuccess("Data Dokumen PO #{$id} berhasil diperbarui.");
            $f3->reroute("/po/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui data PO: ' . $e->getMessage());
            $f3->reroute("/po/{$id}");
        }
    }

    /**
     * Menghapus dokumen PO
     * Route: POST /po/@id/hapus
     */
    public function hapus($f3, $params) {
        $id = (int)($params['id'] ?? 0);

        try {
            $poModel = new Po($this->db);
            $poModel->hapus($id);

            $this->setFlashSuccess("Dokumen PO #{$id} berhasil dihapus.");
            $f3->reroute('/po');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menghapus dokumen PO: ' . $e->getMessage());
            $f3->reroute('/po');
        }
    }

    /**
     * Ekspor Data Rekap PO ke Format Excel/CSV sesuai standar rekap BBSPJIS
     * Route: GET /po/ekspor
     */
    public function ekspor($f3) {
        $poModel = new Po($this->db);
        $daftarPo = $poModel->allWithRelasi();

        $filename = 'Rekap_PO_OPTI_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Tambahkan UTF-8 BOM untuk kompatibilitas Microsoft Excel
        fputs($output, "\xEF\xBB\xBF");

        // Header kolom sesuai format rekap PO existing resmi balai
        fputcsv($output, array(
            'No',
            'Tanggal Masuk',
            'Nomor PO',
            'Judul Kegiatan',
            'Pengguna Layanan Jasa',
            'Tim',
            'Jumlah Pekerjaan/Alat',
            'Biaya',
            'Status Pembayaran',
            'Revisi',
            'Target Pelaksanaan',
            'Realisasi',
            'Tgl Kembali PO/Selesai'
        ));

        $no = 1;
        foreach ($daftarPo as $item) {
            $statusBayar = ((float)$item['total_terbayar'] >= (float)$item['biaya'] && (float)$item['biaya'] > 0) ? 'Lunas' : 'Belum Lunas';
            $tglMasuk = !empty($item['tanggal_masuk']) ? date('d/m/Y', strtotime($item['tanggal_masuk'])) : '-';
            $targetPelaksanaan = !empty($item['target_selesai']) ? date('d/m/Y', strtotime($item['target_selesai'])) : '-';
            $realisasi = !empty($item['realisasi_selesai']) ? date('d/m/Y', strtotime($item['realisasi_selesai'])) : '-';
            $revisi = !empty($item['tanggal_kembali']) ? date('d/m/Y', strtotime($item['tanggal_kembali'])) : '-';
            $tglKembaliSelesai = ($item['status'] === 'kembali_selesai' && !empty($item['realisasi_selesai'])) 
                ? date('d/m/Y', strtotime($item['realisasi_selesai'])) 
                : (!empty($item['tanggal_kembali']) ? date('d/m/Y', strtotime($item['tanggal_kembali'])) : '-');

            fputcsv($output, array(
                $no++,
                $tglMasuk,
                $item['nomor_po'],
                $item['judul_kegiatan'],
                $item['nama_perusahaan'] . ' (' . ($item['pt_cv'] ?? 'PT') . ')',
                $item['tim_kerja'] ?: '-',
                $item['jumlah_pekerjaan'] ?: ($item['jenis_sampel'] ?: '1 Paket'),
                $item['biaya'],
                $statusBayar,
                $revisi,
                $targetPelaksanaan,
                $realisasi,
                $tglKembaliSelesai
            ));
        }

        fclose($output);
        exit;
    }
}
