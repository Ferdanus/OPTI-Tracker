<?php

/**
 * Model OptiInvoice (opti_invoice)
 * Mengelola penerbitan Invoice / Surat Tagihan Pembayaran Layanan OPTI oleh Bagian Keuangan.
 */
class OptiInvoice extends \DB\SQL\Mapper
{
    public function __construct(\DB\SQL $db)
    {
        parent::__construct($db, 'opti_invoice');
    }

    /**
     * Generate nomor invoice resmi balai format: {urut}/INV/BBSPJIS/{bulan_romawi}/{tahun}
     */
    public function generateNomorInvoice(string $tanggal = ''): string
    {
        $time = !empty($tanggal) ? strtotime($tanggal) : time();
        if (!$time) $time = time();

        $bulanAngka  = (int) date('n', $time);
        $tahun       = date('Y', $time);
        $bulanRomawi = SuratPenawaran::bulanKeRomawi($bulanAngka);

        $pattern = "%/INV/BBSPJIS/{$bulanRomawi}/{$tahun}";
        $res = $this->db->exec(
            "SELECT nomor_invoice FROM opti_invoice WHERE nomor_invoice LIKE ? ORDER BY id DESC LIMIT 1",
            array(1 => $pattern)
        );

        $urut = 1;
        if (!empty($res) && !empty($res[0]['nomor_invoice'])) {
            $parts = explode('/', $res[0]['nomor_invoice']);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $urut = (int) $parts[0] + 1;
            }
        }

        $nomorUrutPadded = str_pad((string)$urut, 2, '0', STR_PAD_LEFT);
        return "{$nomorUrutPadded}/INV/BBSPJIS/{$bulanRomawi}/{$tahun}";
    }

    /**
     * Ambil daftar invoice untuk sebuah Order
     */
    public function getByOrderId(int $orderId): array
    {
        return $this->db->exec(
            "SELECT inv.*, u.nama_user AS pembuat_nama,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE invoice_id = inv.id AND status_verifikasi = 'terverifikasi'), 0) AS total_terbayar_invoice
             FROM opti_invoice inv
             LEFT JOIN tb_arsipuser u ON inv.dibuat_oleh = u.id_user
             WHERE inv.order_id = ?
             ORDER BY inv.id ASC",
            array(1 => $orderId)
        );
    }

    /**
     * Buat invoice tagihan baru untuk order
     */
    public function buatInvoiceBaru(int $orderId, int $userId, array $data): array
    {
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);

        if (!$order) {
            throw new \Exception("Order Layanan #{$orderId} tidak ditemukan.");
        }

        $tanggalInv = !empty($data['tanggal_invoice']) ? $data['tanggal_invoice'] : date('Y-m-d');
        $jatuhTempo = !empty($data['jatuh_tempo']) ? $data['jatuh_tempo'] : date('Y-m-d', strtotime('+14 days'));
        $nomorInv   = !empty($data['nomor_invoice']) ? trim($data['nomor_invoice']) : $this->generateNomorInvoice($tanggalInv);
        $nominal    = (float)($data['nominal_tagihan'] ?? 0);
        $terminKet  = trim($data['keterangan_termin'] ?? 'Tagihan Layanan OPTI');
        $fileInv    = $data['file_invoice'] ?? null;
        $poId       = !empty($order['po_id']) ? (int)$order['po_id'] : null;

        if ($nominal <= 0) {
            throw new \Exception("Nominal tagihan invoice harus lebih dari 0.");
        }

        $this->db->exec(
            "INSERT INTO opti_invoice 
            (order_id, po_id, nomor_invoice, tanggal_invoice, jatuh_tempo, nominal_tagihan, keterangan_termin, status_pembayaran, file_invoice, dibuat_oleh, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'belum_bayar', ?, ?, NOW())",
            array(
                1 => $orderId,
                2 => $poId,
                3 => $nomorInv,
                4 => $tanggalInv,
                5 => $jatuhTempo,
                6 => $nominal,
                7 => $terminKet,
                8 => $fileInv,
                9 => $userId
            )
        );

        $invoiceId = (int)$this->db->exec("SELECT LAST_INSERT_ID() AS id")[0]['id'];

        // Update status keuangan di order
        $this->db->exec(
            "UPDATE order_layanan SET status_keuangan = 'menunggu_pembayaran' WHERE id = ? AND status_keuangan = 'belum_ditagih'",
            array(1 => $orderId)
        );

        return [
            'invoice_id'    => $invoiceId,
            'nomor_invoice' => $nomorInv,
            'nominal'       => $nominal
        ];
    }

    /**
     * Hitung ulang status pembayaran tiap invoice untuk order
     */
    public function sinkronkanStatusInvoice(int $orderId): void
    {
        $invoices = $this->getByOrderId($orderId);
        foreach ($invoices as $inv) {
            $tagihan = (float)$inv['nominal_tagihan'];
            $bayar   = (float)$inv['total_terbayar_invoice'];

            $status = 'belum_bayar';
            if ($bayar >= $tagihan && $tagihan > 0) {
                $status = 'lunas';
            } elseif ($bayar > 0) {
                $status = 'sebagian';
            }

            $this->db->exec(
                "UPDATE opti_invoice SET status_pembayaran = ? WHERE id = ?",
                array(1 => $status, 2 => $inv['id'])
            );
        }
    }
}