<?php

/**
 * Dashboard OPTI - Ringkasan status order & keuangan per divisi (Selulosa & Lingkungan)
 * Terintegrasi penuh dengan Daftar Order, Disposisi Masuk, dan Modul Keuangan Balai BBSPJIS.
 */
class DashboardOptiController extends Controller {

    /**
     * Route: GET /dashboard
     */
    public function index($f3) {
        $this->requireAuth();

        $selulosa   = $this->hitungStatistikDivisi('selulosa');
        $lingkungan = $this->hitungStatistikDivisi('lingkungan');

        // Hitung permohonan yang belum didisposisi ke divisi (masih belum_ditentukan)
        $belumDitentukan = $this->safeCount(
            "SELECT COUNT(DISTINCT o.id) AS total 
             FROM order_layanan o 
             WHERE o.jenis_layanan_opti NOT IN ('selulosa', 'lingkungan') 
               AND o.status != 'ditolak'",
            []
        );

        $f3->set('stat_selulosa', $selulosa);
        $f3->set('stat_lingkungan', $lingkungan);
        $f3->set('stat_total', [
            'aktif'            => $selulosa['aktif'] + $lingkungan['aktif'] + $belumDitentukan,
            'diterima'         => $selulosa['diterima'] + $lingkungan['diterima'],
            'pending'          => $selulosa['pending'] + $lingkungan['pending'] + $belumDitentukan,
            'ditolak'          => $selulosa['ditolak'] + $lingkungan['ditolak'],
            'berjalan'         => $selulosa['berjalan'] + $lingkungan['berjalan'],
            'selesai'          => $selulosa['selesai'] + $lingkungan['selesai'],
            'uang_diterima'    => $selulosa['uang_diterima'] + $lingkungan['uang_diterima'],
            'belum_ditentukan' => $belumDitentukan,
        ]);

        // Muat 5 order layanan aktif terbaru untuk transparansi tracker di dashboard
        $orderModel = new OrderLayanan($this->db);
        $semuaAktif = $orderModel->allWithRelasi('', '', '', '', 'aktif');
        $daftarOrderTerbaru = array_slice($semuaAktif, 0, 5);
        $f3->set('daftar_order_terbaru', $daftarOrderTerbaru);

        $this->render('dashboard/index.html', 'Dashboard Optimalisasi Pemanfaatan Teknologi Industri BBSPJIS', 'dashboard');
    }

    /**
     * Hitung metrik operasional terpadu untuk satu divisi
     */
    protected function hitungStatistikDivisi($divisi) {
        return [
            'aktif'         => $this->countAktif($divisi),
            'diterima'      => $this->countDiterima($divisi),
            'pending'       => $this->countPending($divisi),
            'ditolak'       => $this->countDitolak($divisi),
            'berjalan'      => $this->countBerjalan($divisi),
            'selesai'       => $this->countSelesai($divisi),
            'uang_diterima' => $this->sumUangDiterima($divisi),
        ];
    }

    /** Order Aktif (Sinkron dengan Tab Order Aktif di /order) */
    protected function countAktif($divisi) {
        $sql = "SELECT COUNT(DISTINCT o.id) AS total
                FROM order_layanan o
                WHERE o.jenis_layanan_opti = ?
                  AND o.status != 'ditolak'
                  AND (o.status_tinjauan != 'tidak_layak' OR o.status_tinjauan IS NULL)";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /** Pending: Menunggu Kaji Kelayakan Ka. Tim atau Menunggu Approval Proposal (Sinkron dengan /disposisi-masuk) */
    protected function countPending($divisi) {
        $sql = "SELECT COUNT(DISTINCT o.id) AS total
                FROM order_layanan o
                WHERE o.jenis_layanan_opti = ?
                  AND o.status != 'ditolak'
                  AND (
                      (o.id NOT IN (SELECT order_id FROM opti_tinjauan_kelayakan WHERE keputusan IN ('dapat_dilaksanakan', 'tidak_dapat_dilaksanakan')))
                      OR o.status_proposal_biaya = 'menunggu_approval'
                  )";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /** Diterima: Ka. Tim memutuskan "dapat_dilaksanakan" dan order aktif */
    protected function countDiterima($divisi) {
        $sql = "SELECT COUNT(DISTINCT o.id) AS total
                FROM order_layanan o
                INNER JOIN opti_tinjauan_kelayakan t ON t.order_id = o.id
                WHERE o.jenis_layanan_opti = ?
                  AND t.keputusan = 'dapat_dilaksanakan'
                  AND o.status != 'ditolak'";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /** Ditolak: Keputusan tinjauan tidak layak atau order berstatus ditolak (Sinkron dengan Tab Order Ditolak di /order) */
    protected function countDitolak($divisi) {
        $sql = "SELECT COUNT(DISTINCT o.id) AS total
                FROM order_layanan o
                LEFT JOIN opti_tinjauan_kelayakan t ON t.order_id = o.id
                WHERE o.jenis_layanan_opti = ?
                  AND (t.keputusan = 'tidak_dapat_dilaksanakan' OR o.status = 'ditolak' OR o.status_tinjauan = 'tidak_layak')";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /** Sedang Berjalan: Sudah disetujui kelayakannya, belum selesai/ditolak */
    protected function countBerjalan($divisi) {
        $sql = "SELECT COUNT(DISTINCT o.id) AS total
                FROM order_layanan o
                INNER JOIN opti_tinjauan_kelayakan t ON t.order_id = o.id
                WHERE o.jenis_layanan_opti = ?
                  AND t.keputusan = 'dapat_dilaksanakan'
                  AND o.status NOT IN ('selesai', 'ditolak')
                  AND (o.status_pelaksanaan IS NULL OR o.status_pelaksanaan != 'laporan_selesai')";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /** Selesai: Pekerjaan lab tuntas dan/atau BAST telah diselesaikan */
    protected function countSelesai($divisi) {
        $sql = "SELECT COUNT(DISTINCT o.id) AS total
                FROM order_layanan o
                LEFT JOIN opti_bast b ON o.id = b.order_id
                WHERE o.jenis_layanan_opti = ?
                  AND (o.status = 'selesai' OR o.status_pelaksanaan = 'laporan_selesai' OR b.status_bast = 'selesai')";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /**
     * Total uang yang sudah diverifikasi masuk ke kas negara/balai per divisi.
     * Menggunakan tabel opti_pembayaran (kolom jumlah & status_verifikasi = 'terverifikasi').
     */
    protected function sumUangDiterima($divisi) {
        $sql = "SELECT COALESCE(SUM(pay.jumlah), 0) AS total
                FROM opti_pembayaran pay
                INNER JOIN order_layanan o ON o.id = pay.order_id
                WHERE o.jenis_layanan_opti = ?
                  AND pay.status_verifikasi = 'terverifikasi'";
        try {
            $rows = $this->db->exec($sql, [1 => $divisi]);
            return (float) ($rows[0]['total'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function safeCount($sql, $params) {
        try {
            $rows = $this->db->exec($sql, $params);
            return (int) ($rows[0]['total'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
}