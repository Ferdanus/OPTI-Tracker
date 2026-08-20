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
            "SELECT * FROM opti_pembayaran WHERE order_id = ? ORDER BY termin_ke ASC, tanggal_bayar ASC",
            array(1 => $orderId)
        );
    }

    /**
     * Ambil seluruh riwayat pembayaran untuk sebuah PO
     */
    public function getByPoId(int $poId): array {
        return $this->db->exec(
            "SELECT p.*, o.nomor_order 
             FROM opti_pembayaran p
             JOIN order_layanan o ON p.order_id = o.id
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
        $terminKe     = (int)($data['termin_ke'] ?? 1);
        $tanggalBayar = $data['tanggal_bayar'] ?? date('Y-m-d');
        $jumlah       = (float)($data['jumlah'] ?? 0);
        $keterangan   = trim($data['keterangan'] ?? '');
        $buktiBayar   = $data['bukti_bayar'] ?? null;
        $status       = $data['status_verifikasi'] ?? 'terverifikasi';

        if ($jumlah <= 0) {
            throw new \Exception("Nominal pembayaran harus lebih dari 0.");
        }

        $this->db->exec(
            "INSERT INTO opti_pembayaran (order_id, po_id, termin_ke, tanggal_bayar, jumlah, keterangan, bukti_bayar, status_verifikasi, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            array(
                1 => $orderId,
                2 => $poId,
                3 => $terminKe,
                4 => $tanggalBayar,
                5 => $jumlah,
                6 => $keterangan,
                7 => $buktiBayar,
                8 => $status
            )
        );

        return (int)$this->db->lastInsertId();
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
     * Hapus transaksi pembayaran
     */
    public function hapus(int $id): bool {
        $this->db->exec("DELETE FROM opti_pembayaran WHERE id = ?", array(1 => $id));
        return true;
    }
}
