<?php

/**
 * Dashboard OPTI - ringkasan status order & keuangan per divisi (Selulosa & Lingkungan).
 * Ditampilkan SAMA untuk semua user, terlepas dari divisi mana yang sedang mereka akses.
 *
 * CATATAN PENTING - MOHON DIVERIFIKASI:
 * Query di bawah ini disusun berdasarkan pola yang terlihat di OrderController
 * (disposisiMasuk, tinjauan, dsb). Beberapa bagian bersifat ASUMSI karena saya
 * tidak punya akses ke skema tabel pembayaran & status final order kamu:
 *   - Nama tabel/kolom pembayaran: `opti_pembayaran` (order_id, jumlah_bayar, status)
 *   - Status "selesai" pada order_layanan.status untuk order yang sudah tuntas
 * Sesuaikan bagian yang saya tandai [VERIFIKASI] di bawah dengan skema asli kamu.
 */
class DashboardOptiController extends Controller {

    /**
     * Route: GET /dashboard-opti
     * (atau mount di route mana pun yang kamu mau - isinya sama untuk semua user/divisi)
     */
    public function index($f3) {
        $this->requireAuth();

        $selulosa   = $this->hitungStatistikDivisi('selulosa');
        $lingkungan = $this->hitungStatistikDivisi('lingkungan');

        $f3->set('stat_selulosa', $selulosa);
        $f3->set('stat_lingkungan', $lingkungan);
        $f3->set('stat_total', [
            'diterima'      => $selulosa['diterima'] + $lingkungan['diterima'],
            'pending'       => $selulosa['pending'] + $lingkungan['pending'],
            'ditolak'       => $selulosa['ditolak'] + $lingkungan['ditolak'],
            'berjalan'      => $selulosa['berjalan'] + $lingkungan['berjalan'],
            'uang_diterima' => $selulosa['uang_diterima'] + $lingkungan['uang_diterima'],
        ]);

        $this->render('dashboard/index.html', 'Dashboard OPTI', 'dashboard');
    }

    /**
     * Hitung 5 metrik untuk satu divisi (selulosa / lingkungan).
     */
    protected function hitungStatistikDivisi($divisi) {
        return [
            'diterima'      => $this->countDiterima($divisi),
            'pending'       => $this->countPending($divisi),
            'ditolak'       => $this->countDitolak($divisi),
            'berjalan'      => $this->countBerjalan($divisi),
            'uang_diterima' => $this->sumUangDiterima($divisi),
        ];
    }

    /** Pending: masuk baru, belum ditinjau Ka. Tim sama sekali */
    protected function countPending($divisi) {
        $sql = "SELECT COUNT(*) AS total
                FROM order_layanan o
                WHERE o.jenis_layanan_opti = ?
                  AND o.status = 'baru'
                  AND o.id NOT IN (SELECT order_id FROM opti_tinjauan_kelayakan)";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /** Diterima: Ka. Tim memutuskan "dapat_dilaksanakan" (terlepas dari tahap selanjutnya) */
    protected function countDiterima($divisi) {
        $sql = "SELECT COUNT(*) AS total
                FROM order_layanan o
                INNER JOIN opti_tinjauan_kelayakan t ON t.order_id = o.id
                WHERE o.jenis_layanan_opti = ?
                  AND t.keputusan = 'dapat_dilaksanakan'";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /** Ditolak: keputusan tinjauan "tidak_dapat_dilaksanakan" ATAU order eksplisit ditolak */
    protected function countDitolak($divisi) {
        $sql = "SELECT COUNT(*) AS total
                FROM order_layanan o
                LEFT JOIN opti_tinjauan_kelayakan t ON t.order_id = o.id
                WHERE o.jenis_layanan_opti = ?
                  AND (t.keputusan = 'tidak_dapat_dilaksanakan' OR o.status = 'ditolak')";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /**
     * Sedang Berjalan: sudah diterima, belum ditandai selesai/ditolak.
     * [VERIFIKASI] Saya asumsikan order yang sudah tuntas ditandai o.status = 'selesai'.
     * Kalau penanda "selesai" kamu ada di tabel lain (mis. opti_bast), ganti kondisinya.
     */
    protected function countBerjalan($divisi) {
        $sql = "SELECT COUNT(*) AS total
                FROM order_layanan o
                INNER JOIN opti_tinjauan_kelayakan t ON t.order_id = o.id
                WHERE o.jenis_layanan_opti = ?
                  AND t.keputusan = 'dapat_dilaksanakan'
                  AND o.status NOT IN ('selesai', 'ditolak')";
        return $this->safeCount($sql, [1 => $divisi]);
    }

    /**
     * Total uang yang sudah diterima (pembayaran lunas) per divisi.
     * [VERIFIKASI] Asumsi nama tabel `opti_pembayaran` dengan kolom
     * order_id, jumlah_bayar, status ('lunas' menandakan sudah diterima).
     * Sesuaikan dengan model OptiPembayaran kamu yang sebenarnya.
     */
    protected function sumUangDiterima($divisi) {
        $sql = "SELECT COALESCE(SUM(pay.jumlah_bayar), 0) AS total
                FROM opti_pembayaran pay
                INNER JOIN order_layanan o ON o.id = pay.order_id
                WHERE o.jenis_layanan_opti = ?
                  AND pay.status = 'lunas'";
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