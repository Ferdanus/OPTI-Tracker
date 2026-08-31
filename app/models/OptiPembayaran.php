<?php

/**
 * Model OptiPembayaran
 * Mengelola transaksi pembayaran multi-termin (cicilan / DP / pelunasan) per order layanan OPTI
 */
class OptiPembayaran extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_pembayaran');
    }

    /**
     * Ambil seluruh riwayat pembayaran untuk sebuah Order
     */
    public function getByOrderId(int $orderId): array {
        return $this->db->exec(
            "SELECT p.*, inv.nomor_invoice, u.nama_user AS verifikator_nama
             FROM opti_pembayaran p
             LEFT JOIN opti_invoice inv ON p.invoice_id = inv.id
             LEFT JOIN tb_arsipuser u ON p.verifikator_id = u.id_user
             WHERE p.order_id = ? 
             ORDER BY p.termin_ke ASC, p.tanggal_bayar ASC",
            array(1 => $orderId)
        );
    }

    /**
     * Ambil seluruh riwayat pembayaran untuk sebuah PO
     */
    public function getByPoId(int $poId): array {
        return $this->db->exec(
            "SELECT p.*, o.nomor_order, inv.nomor_invoice
             FROM opti_pembayaran p
             JOIN order_layanan o ON p.order_id = o.id
             LEFT JOIN opti_invoice inv ON p.invoice_id = inv.id
             WHERE p.po_id = ? OR p.order_id = (SELECT order_id FROM po WHERE id = ?)
             ORDER BY p.termin_ke ASC, p.tanggal_bayar ASC",
            array(1 => $poId, 2 => $poId)
        );
    }

    /**
     * Tambah data pembayaran termin baru
     */
    public function tambahPembayaran(array $data): int {
        $orderId      = (int)$data['order_id'];
        $poId         = !empty($data['po_id']) ? (int)$data['po_id'] : null;
        $invoiceId    = !empty($data['invoice_id']) ? (int)$data['invoice_id'] : null;
        $terminKe     = (int)($data['termin_ke'] ?? 1);
        $tanggalBayar = $data['tanggal_bayar'] ?? date('Y-m-d');
        $jumlah       = (float)($data['jumlah'] ?? 0);
        $metode       = $data['metode_pembayaran'] ?? 'transfer_bank';
        $ntpn         = trim($data['nomor_transaksi_ntpn'] ?? '');
        $keterangan   = trim($data['keterangan'] ?? '');
        $buktiBayar   = $data['bukti_bayar'] ?? null;
        $status       = $data['status_verifikasi'] ?? 'terverifikasi';
        $verifikator  = !empty($data['verifikator_id']) ? (int)$data['verifikator_id'] : null;

        if ($jumlah <= 0) {
            throw new \Exception("Nominal pembayaran harus lebih dari 0.");
        }

        $this->db->exec(
            "INSERT INTO opti_pembayaran 
            (order_id, po_id, invoice_id, termin_ke, tanggal_bayar, jumlah, metode_pembayaran, nomor_transaksi_ntpn, keterangan, bukti_bayar, status_verifikasi, verifikator_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            array(
                1 => $orderId,
                2 => $poId,
                3 => $invoiceId,
                4 => $terminKe,
                5 => $tanggalBayar,
                6 => $jumlah,
                7 => $metode,
                8 => $ntpn,
                9 => $keterangan,
                10 => $buktiBayar,
                11 => $status,
                12 => $verifikator
            )
        );

        $pembayaranId = (int)$this->db->exec("SELECT LAST_INSERT_ID() AS id")[0]['id'];

        // Sinkronisasi status invoice & keuangan order
        $this->sinkronkanKeuanganOrder($orderId);

        return $pembayaranId;
    }

    /**
     * Hitung total terbayar untuk sebuah order
     */
    public function hitungTotalTerbayar(int $orderId): float {
        $res = $this->db->exec(
            "SELECT COALESCE(SUM(jumlah), 0) AS total FROM opti_pembayaran WHERE order_id = ? AND status_verifikasi = 'terverifikasi'",
            array(1 => $orderId)
        );
        return (float)($res[0]['total'] ?? 0);
    }

    /**
     * Hitung rekapitulasi keuangan lengkap per order
     */
    public function getRekapKeuanganOrder(int $orderId): array {
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);
        $totalBiaya = (float)($order['estimasi_biaya'] ?? 0);

        $totalTerbayar = $this->hitungTotalTerbayar($orderId);
        $sisaPiutang = max(0, $totalBiaya - $totalTerbayar);
        $persenLunas = $totalBiaya > 0 ? min(100, round(($totalTerbayar / $totalBiaya) * 100, 1)) : 0;

        $invoices = (new OptiInvoice($this->db))->getByOrderId($orderId);
        $totalTertagih = 0;
        foreach ($invoices as $inv) {
            $totalTertagih += (float)$inv['nominal_tagihan'];
        }

        $statusKeuangan = 'belum_ditagih';
        if ($totalTerbayar >= $totalBiaya && $totalBiaya > 0) {
            $statusKeuangan = 'lunas';
        } elseif ($totalTerbayar > 0) {
            $statusKeuangan = 'terbayar_sebagian';
        } elseif ($totalTertagih > 0) {
            $statusKeuangan = 'menunggu_pembayaran';
        }

        return [
            'total_biaya'          => $totalBiaya,
            'total_tertagih'       => $totalTertagih,
            'total_terbayar'       => $totalTerbayar,
            'sisa_piutang'         => $sisaPiutang,
            'persentase_lunas'     => $persenLunas,
            'status_keuangan'      => $statusKeuangan,
            'jumlah_invoice'       => count($invoices)
        ];
    }

    /**
     * Sinkronkan status keuangan order dan invoice
     */
    public function sinkronkanKeuanganOrder(int $orderId): void
    {
        $rekap = $this->getRekapKeuanganOrder($orderId);

        $this->db->exec(
            "UPDATE order_layanan SET status_keuangan = ? WHERE id = ?",
            array(1 => $rekap['status_keuangan'], 2 => $orderId)
        );

        (new OptiInvoice($this->db))->sinkronkanStatusInvoice($orderId);
    }

    /**
     * Hapus transaksi pembayaran
     */
    public function hapus(int $id): bool {
        $pembayaran = $this->db->exec("SELECT order_id FROM opti_pembayaran WHERE id = ?", array(1 => $id));
        $this->db->exec("DELETE FROM opti_pembayaran WHERE id = ?", array(1 => $id));

        if (!empty($pembayaran) && !empty($pembayaran[0]['order_id'])) {
            $this->sinkronkanKeuanganOrder((int)$pembayaran[0]['order_id']);
        }
        return true;
    }
}