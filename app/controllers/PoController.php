<?php

/**
 * Controller untuk mengelola Dokumen PO, State Machine Status, dan Histori Log
 */
class PoController extends Controller {

    /**
     * Menampilkan daftar semua PO dengan filter (Bulan, Tahun, Status)
     * Route: GET /po
     */
    public function index($f3) {
        $filterBulan  = $f3->get('GET.bulan') ?? '';
        $filterTahun  = $f3->get('GET.tahun') ?? '';
        $filterStatus = $f3->get('GET.status') ?? '';

        $poModel = new Po($this->db);
        $daftarPo = $poModel->allWithRelasi($filterBulan, $filterTahun, $filterStatus);

        // Siapkan opsi dropdown filter
        $listBulan = array(
            1 => 'Januari (I)', 2 => 'Februari (II)', 3 => 'Maret (III)',
            4 => 'April (IV)', 5 => 'Mei (V)', 6 => 'Juni (VI)',
            7 => 'Juli (VII)', 8 => 'Agustus (VIII)', 9 => 'September (IX)',
            10 => 'Oktober (X)', 11 => 'November (XI)', 12 => 'Desember (XII)'
        );

        $listStatus = array(
            'proposal'   => '1. Proposal',
            'kontrak'    => '2. Kontrak',
            'po_terbit'  => '3. PO Terbit',
            'distribusi' => '4. Distribusi',
            'selesai'    => '5. Selesai'
        );

        $f3->set('daftar_po', $daftarPo);
        $f3->set('list_bulan', $listBulan);
        $f3->set('list_status', $listStatus);
        $f3->set('filter_bulan', $filterBulan);
        $f3->set('filter_tahun', $filterTahun);
        $f3->set('filter_status', $filterStatus);

        $this->render('po/index.htm', 'Daftar PO & Dashboard - Mini OPTI Tracker', 'po');
    }

    /**
     * Menampilkan detail PO, progress bar alur status, dan riwayat audit trail log
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

        $this->render('po/detail.htm', 'Detail Dokumen PO ' . $po['nomor_po'] . ' - Mini OPTI Tracker', 'po');
    }

    /**
     * Memajukan status PO ke 1 tahap berikutnya
     * Route: POST /po/@id/lanjut-status
     */
    public function lanjutStatus($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $catatan          = trim($post['catatan'] ?? '');
        $biaya            = $post['biaya'] ?? null;
        $tanggalTarget    = $post['tanggal_target'] ?? null;
        $tanggalRealisasi = $post['tanggal_realisasi'] ?? null;

        try {
            $poModel = new Po($this->db);
            $statusBaru = $poModel->majuStatus($id, $catatan, array(
                'biaya'             => $biaya,
                'tanggal_target'    => $tanggalTarget,
                'tanggal_realisasi' => $tanggalRealisasi
            ));

            $labelBaru = Po::getStatusLabel($statusBaru);
            $this->setFlashSuccess("Status PO berhasil dimajukan ke tahap: <strong>{$labelBaru}</strong>.");
            $f3->reroute("/po/{$id}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memajukan status PO: ' . $e->getMessage());
            $f3->reroute("/po/{$id}");
        }
    }
}
