<?php

/**
 * Controller untuk mengelola Dokumen PO, State Machine Status, Map Kendali, dan Histori Log
 */
class PoController extends Controller {

    /**
     * Menampilkan daftar semua PO dengan filter (Bulan, Tahun, Status, Jenis Layanan, Search)
     * Route: GET /po
     */
    public function index($f3) {
        $filterBulan  = $f3->get('GET.bulan') ?? '';
        $filterTahun  = $f3->get('GET.tahun') ?? '';
        $filterStatus = $f3->get('GET.status') ?? '';
        $filterJenisLayanan = $f3->get('GET.jenis_layanan') ?? '';
        $search       = $f3->get('GET.q') ?? '';

        $poModel = new Po($this->db);
        $daftarPo = $poModel->allWithRelasi($filterBulan, $filterTahun, $filterStatus, $filterJenisLayanan, $search);

        // Siapkan opsi dropdown filter
        $listBulan = array(
            1 => 'Januari (I)', 2 => 'Februari (II)', 3 => 'Maret (III)',
            4 => 'April (IV)', 5 => 'Mei (V)', 6 => 'Juni (VI)',
            7 => 'Juli (VII)', 8 => 'Agustus (VIII)', 9 => 'September (IX)',
            10 => 'Oktober (X)', 11 => 'November (XI)', 12 => 'Desember (XII)'
        );

        $listStatus = array(
            'belum_upload'   => '1. Belum Upload Dokumen sudah Diterima',
            'sudah_upload'   => '2. Sudah Upload Dokumen belum Diterima',
            'on_proses'      => '3. On Proses',
            'kembali_selesai'=> '4. PO sudah kembali-selesai'
        );

        $f3->set('daftar_po', $daftarPo);
        $f3->set('list_bulan', $listBulan);
        $f3->set('list_status', $listStatus);
        $f3->set('filter_bulan', $filterBulan);
        $f3->set('filter_tahun', $filterTahun);
        $f3->set('filter_status', $filterStatus);
        $f3->set('filter_jenis_layanan', $filterJenisLayanan);
        $f3->set('search_q', $search);

        $this->render('po/index.html', 'Daftar PO & Dashboard - OPTI Tracker', 'po');
    }

    /**
     * Menampilkan detail PO, progress bar alur status, Map Kendali, dan riwayat audit trail log
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

        $logModel = new PoLogStatus($this->db);
        $daftarLog = $logModel->getByPoId($id);

        $nextStatus = Po::getNextStatus($po['status']);
        $nextStatusLabel = $nextStatus ? Po::getStatusLabel($nextStatus) : null;

        $f3->set('po', $po);
        $f3->set('daftar_log', $daftarLog);
        $f3->set('urutan_status', Po::$URUTAN_STATUS);
        $f3->set('next_status', $nextStatus);
        $f3->set('next_status_label', $nextStatusLabel);

        $this->render('po/detail.html', 'Detail Dokumen PO ' . $po['nomor_po'] . ' - OPTI Tracker', 'po');
    }

    /**
     * Memajukan status PO ke 1 tahap berikutnya
     * Route: POST /po/@id/lanjut-status
     */
    public function lanjutStatus($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $catatan          = trim($post['catatan'] ?? '');
        $biaya            = $post['biaya'] !== '' ? (float)$post['biaya'] : null;
        $timKerja         = trim($post['tim_kerja'] ?? '');
        $tanggalKeluar    = !empty($post['tanggal_keluar']) ? $post['tanggal_keluar'] : null;
        $tanggalKembali   = !empty($post['tanggal_kembali']) ? $post['tanggal_kembali'] : null;
        $targetMulai      = !empty($post['target_mulai']) ? $post['target_mulai'] : null;
        $targetSelesai    = !empty($post['target_selesai']) ? $post['target_selesai'] : null;
        $realisasiSelesai = !empty($post['realisasi_selesai']) ? $post['realisasi_selesai'] : null;

        try {
            $poModel = new Po($this->db);
            $statusBaru = $poModel->majuStatus($id, $catatan, array(
                'biaya'             => $biaya,
                'tim_kerja'         => $timKerja,
                'tanggal_keluar'    => $tanggalKeluar,
                'tanggal_kembali'   => $tanggalKembali,
                'target_mulai'      => $targetMulai,
                'target_selesai'    => $targetSelesai,
                'realisasi_selesai' => $realisasiSelesai
            ));

            $labelBaru = Po::getStatusLabel($statusBaru);
            $this->setFlashSuccess("Status PO berhasil dimajukan ke tahap: <strong>{$labelBaru}</strong>.");
            $f3->reroute("/po/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memajukan status PO: ' . $e->getMessage());
            $f3->reroute("/po/{$id}");
        }
    }

    /**
     * Verifikasi & Validasi Map Kendali PO oleh Pejabat
     * Route: POST /po/@id/approve-map/@stage
     */
    public function approveMap($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        $stage = trim($params['stage'] ?? '');

        try {
            $poModel = new Po($this->db);
            $poModel->approveMapKendali($id, $stage);

            $this->setFlashSuccess("Persetujuan Map Kendali tahap <strong>" . strtoupper($stage) . "</strong> berhasil disimpan.");
            $f3->reroute("/po/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memproses persetujuan Map Kendali: ' . $e->getMessage());
            $f3->reroute("/po/{$id}");
        }
    }

    /**
     * Memperbarui detail kemajuan pelaksanaan SOP (Draft laporan, notulen, final, BAST)
     * Route: POST /po/@id/update-pelaksanaan
     */
    public function updatePelaksanaan($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        // Proteksi token keamanan CSRF
        if (($post['csrf_token'] ?? '') !== $f3->get('SESSION.csrf_token')) {
            $this->setFlashError('Token keamanan tidak valid.');
            $f3->reroute("/po/{$id}");
            return;
        }

        // Cek otorisasi
        $role = $f3->get('SESSION.role');
        if ($role !== 'ketua_tim' && $role !== 'tim_kerja' && $role !== 'superadmin') {
            $this->setFlashError('Akses Ditolak: Anda tidak memiliki wewenang untuk memperbarui data pelaksanaan.');
            $f3->reroute("/po/{$id}");
            return;
        }

        // TODO-KONFIRMASI: Konfirmasi apakah tahapan "Pelaksanaan & BAST" ini disetujui untuk masuk dalam cakupan prioritas sistem.
        try {
            $poModel = new Po($this->db);
            $po = $poModel->getById($id);
            if (!$po) {
                throw new \Exception("PO #{$id} tidak ditemukan.");
            }

            $po->laporan_perkembangan = trim($post['laporan_perkembangan'] ?? '');
            $po->tgl_laporan_perkembangan = !empty($post['tgl_laporan_perkembangan']) ? $post['tgl_laporan_perkembangan'] : null;
            $po->notulen_masukan = trim($post['notulen_masukan'] ?? '');
            $po->tgl_notulen_masukan = !empty($post['tgl_notulen_masukan']) ? $post['tgl_notulen_masukan'] : null;
            $po->laporan_kegiatan_final = trim($post['laporan_kegiatan_final'] ?? '');
            $po->tgl_laporan_kegiatan_final = !empty($post['tgl_laporan_kegiatan_final']) ? $post['tgl_laporan_kegiatan_final'] : null;
            $po->bast_dokumen = trim($post['bast_dokumen'] ?? '');
            $po->tgl_bast = !empty($post['tgl_bast']) ? $post['tgl_bast'] : null;
            
            $po->save();

            // Catat audit log
            $logModel = new PoLogStatus($this->db);
            $logModel->catat(
                $id,
                $po->status,
                $po->status,
                'Memperbarui data kemajuan pelaksanaan SOP (Draft laporan, notulen, final, atau BAST).'
            );

            $this->setFlashSuccess('Data kemajuan pelaksanaan SOP berhasil disimpan.');
            $f3->reroute("/po/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui data pelaksanaan: ' . $e->getMessage());
            $f3->reroute("/po/{$id}");
        }
    }

    /**
     * Ekspor Data Rekap PO ke Excel/CSV
     * Route: GET /po/ekspor
     */
    public function ekspor($f3) {
        $filterBulan  = $f3->get('GET.bulan') ?? '';
        $filterTahun  = $f3->get('GET.tahun') ?? '';
        $filterStatus = $f3->get('GET.status') ?? '';
        $filterJenisLayanan = $f3->get('GET.jenis_layanan') ?? '';
        $search       = $f3->get('GET.q') ?? '';

        $poModel = new Po($this->db);
        $daftarPo = $poModel->allWithRelasi($filterBulan, $filterTahun, $filterStatus, $filterJenisLayanan, $search);

        // Header CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Rekap_PO_OPTI_' . date('Ymd_His') . '.csv');

        $output = fopen('php://output', 'w');
        
        // BOM untuk kompatibilitas MS Excel UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header Kolom rekap
        fputcsv($output, array(
            'No',
            'Tanggal Masuk',
            'Nomor PO',
            'Judul Kegiatan',
            'Pengguna Layanan Jasa (Klien)',
            'Tim',
            'Jumlah Pekerjaan/Alat',
            'Biaya',
            'Status Pembayaran',
            'Revisi',
            'Target Pelaksanaan',
            'Realisasi',
            'Tgl Kembali PO/Selesai'
        ), ';');

        $no = 1;
        foreach ($daftarPo as $po) {
            // Tentukan status pembayaran (jika PO selesai, diasumsikan pembayaran tuntas)
            $statusBayar = ($po['status'] === 'kembali_selesai') ? 'Lunas' : 'Proses';

            // Target Pelaksanaan Format
            $target = '-';
            if ($po['target_mulai'] && $po['target_selesai']) {
                $target = date('d/m/Y', strtotime($po['target_mulai'])) . ' - ' . date('d/m/Y', strtotime($po['target_selesai']));
            }

            fputcsv($output, array(
                $no++,
                $po['tanggal_masuk'] ? date('d/m/Y', strtotime($po['tanggal_masuk'])) : '-',
                $po['nomor_po'],
                $po['judul_kegiatan'],
                $po['nama_perusahaan'],
                $po['tim_kerja'] ?: '-',
                $po['jumlah_pekerjaan'] ?: '-',
                number_format($po['biaya'], 0, ',', '.'),
                $statusBayar,
                $po['tanggal_kembali'] ? date('d/m/Y', strtotime($po['tanggal_kembali'])) : '-',
                $target,
                $po['realisasi_selesai'] ? date('d/m/Y', strtotime($po['realisasi_selesai'])) : '-',
                $po['tanggal_kembali'] ? date('d/m/Y', strtotime($po['tanggal_kembali'])) : ($po['realisasi_selesai'] ? date('d/m/Y', strtotime($po['realisasi_selesai'])) : '-')
            ), ';');
        }

        fclose($output);
        exit;
    }
}
