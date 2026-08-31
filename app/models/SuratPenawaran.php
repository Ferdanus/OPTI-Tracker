<?php

/**
 * Model SuratPenawaran (tb_surat_penawaran)
 * Mengelola penerbitan Surat Penawaran Resmi oleh Tim Mitra, 
 * sinkronisasi biaya dari Proposal/Kalkulasi Uji, dan pencatatan respon negosiasi klien.
 */
class SuratPenawaran extends \DB\SQL\Mapper
{
    public function __construct(\DB\SQL $db)
    {
        parent::__construct($db, 'tb_surat_penawaran');
    }

    /**
     * Konversi angka bulan ke format Romawi
     */
    public static function bulanKeRomawi(int $bulan): string
    {
        $romawi = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $romawi[$bulan] ?? 'I';
    }

    /**
     * Generate nomor surat penawaran resmi format balai: {urut}/SP/BBSPJIS/{bulan_romawi}/{tahun}
     */
    public function generateNomorSurat(string $tanggal = ''): string
    {
        $time = !empty($tanggal) ? strtotime($tanggal) : time();
        if (!$time) $time = time();

        $bulanAngka  = (int) date('n', $time);
        $tahun       = date('Y', $time);
        $bulanRomawi = self::bulanKeRomawi($bulanAngka);

        // Ambil nomor urut tertinggi pada bulan dan tahun yang bersangkutan
        $pattern = "%/SP/BBSPJIS/{$bulanRomawi}/{$tahun}";
        $res = $this->db->exec(
            "SELECT nomor_surat FROM tb_surat_penawaran WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1",
            array(1 => $pattern)
        );

        $urut = 1;
        if (!empty($res) && !empty($res[0]['nomor_surat'])) {
            $parts = explode('/', $res[0]['nomor_surat']);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $urut = (int) $parts[0] + 1;
            }
        }

        $nomorUrutPadded = str_pad((string)$urut, 2, '0', STR_PAD_LEFT);
        return "{$nomorUrutPadded}/SP/BBSPJIS/{$bulanRomawi}/{$tahun}";
    }

    /**
     * Ambil data surat penawaran berdasarkan ID Order
     */
    public function getByOrderId(int $orderId): ?array
    {
        $res = $this->db->exec(
            "SELECT sp.*, c.nmcustomer, c.pt_cv, c.alamatcustomer, c.notelpcustomer, c.emailcustomer,
                    u.nama_user AS pembuat_nama
             FROM tb_surat_penawaran sp
             LEFT JOIN tb_customer c ON sp.customer_id = c.id_customer
             LEFT JOIN tb_arsipuser u ON sp.dibuat_oleh = u.id_user
             WHERE sp.order_id = ?
             ORDER BY sp.id DESC LIMIT 1",
            array(1 => $orderId)
        );
        return !empty($res) ? $res[0] : null;
    }

    /**
     * Buat atau perbarui Surat Penawaran langsung dari data Order
     */
    public function buatDariOrder(int $orderId, int $userId, array $data): array
    {
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);

        if (!$order) {
            throw new \Exception("Order Layanan #{$orderId} tidak ditemukan.");
        }

        $tanggalSurat = !empty($data['tanggal_surat']) ? $data['tanggal_surat'] : date('Y-m-d');
        $nomorSurat   = !empty($data['nomor_surat']) ? trim($data['nomor_surat']) : $this->generateNomorSurat($tanggalSurat);
        $perihal      = !empty($data['perihal']) ? trim($data['perihal']) : 'Penawaran Layanan Jasa OPTI - ' . $order['judul_kegiatan'];
        $nominal      = isset($data['nominal_penawaran']) ? (float)$data['nominal_penawaran'] : (float)$order['estimasi_biaya'];
        $namaPic      = !empty($data['nama']) ? trim($data['nama']) : ($order['pic'] ?? '');
        $perusahaan   = !empty($data['perusahaan']) ? trim($data['perusahaan']) : ($order['nama_perusahaan'] ?? '');
        $alamat       = !empty($data['alamat']) ? trim($data['alamat']) : ($order['alamatcustomer'] ?? '');
        $permintaanMelalui = !empty($data['permintaan_melalui']) ? trim($data['permintaan_melalui']) : 'email';
        $penjelasan   = !empty($data['penjelasan']) ? trim($data['penjelasan']) : ($order['deskripsi'] ?? '');
        $fileLampiran = !empty($data['file_lampiran']) ? trim($data['file_lampiran']) : '';
        $statusRespon = in_array($data['status_respon_klien'] ?? '', ['draft', 'terkirim', 'nego', 'deal', 'batal']) ? $data['status_respon_klien'] : 'draft';
        $catatanNego  = trim($data['catatan_nego'] ?? '');

        // Jika ada proposal selulosa, ambil file lampirannya jika belum ada upload baru
        if (empty($fileLampiran) && $order['jenis_layanan_opti'] === 'selulosa') {
            $prop = $orderModel->getProposalRiset($orderId);
            if (!empty($prop['file_proposal'])) {
                $fileLampiran = $prop['file_proposal'];
            }
        }

        $existing = $this->getByOrderId($orderId);

        if ($existing) {
            if (empty($fileLampiran) && !empty($existing['file_lampiran'])) {
                $fileLampiran = $existing['file_lampiran'];
            }
            $this->db->exec(
                "UPDATE tb_surat_penawaran SET 
                    nomor_surat = ?, perihal = ?, tanggal_surat = ?, jenis_layanan = ?, 
                    nama = ?, perusahaan = ?, alamat = ?, nominal_penawaran = ?, 
                    permintaan_melalui = ?, penjelasan = ?, file_lampiran = ?, 
                    status_respon_klien = ?, catatan_nego = ?, updated_at = NOW()
                 WHERE id = ?",
                array(
                    1 => $nomorSurat,
                    2 => $perihal,
                    3 => $tanggalSurat,
                    4 => 'opti_' . $order['jenis_layanan_opti'],
                    5 => $namaPic,
                    6 => $perusahaan,
                    7 => $alamat,
                    8 => $nominal,
                    9 => $permintaanMelalui,
                    10 => $penjelasan,
                    11 => $fileLampiran,
                    12 => $statusRespon,
                    13 => $catatanNego,
                    14 => $existing['id']
                )
            );
            $penawaranId = $existing['id'];
        } else {
            $this->db->exec(
                "INSERT INTO tb_surat_penawaran 
                (customer_id, order_id, nomor_surat, perihal, nominal_penawaran, tanggal_surat, jenis_layanan, nama, perusahaan, alamat, permintaan_melalui, penjelasan, file_lampiran, status, status_respon_klien, catatan_nego, dibuat_oleh, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif', ?, ?, ?, NOW())",
                array(
                    1 => $order['id_customer'],
                    2 => $orderId,
                    3 => $nomorSurat,
                    4 => $perihal,
                    5 => $nominal,
                    6 => $tanggalSurat,
                    7 => 'opti_' . $order['jenis_layanan_opti'],
                    8 => $namaPic,
                    9 => $perusahaan,
                    10 => $alamat,
                    11 => $permintaanMelalui,
                    12 => $penjelasan,
                    13 => $fileLampiran,
                    14 => $statusRespon,
                    15 => $catatanNego,
                    16 => $userId
                )
            );
            $penawaranId = (int)$this->db->exec("SELECT LAST_INSERT_ID() AS id")[0]['id'];
        }

        // Sinkronisasi status dan ID surat penawaran ke order_layanan
        $this->db->exec(
            "UPDATE order_layanan SET status_penawaran = ?, surat_penawaran_id = ?, estimasi_biaya = ? WHERE id = ?",
            array(
                1 => $statusRespon,
                2 => $penawaranId,
                3 => $nominal,
                4 => $orderId
            )
        );

        return [
            'penawaran_id' => $penawaranId,
            'nomor_surat'  => $nomorSurat,
            'nominal'      => $nominal,
            'status'       => $statusRespon
        ];
    }

    /**
     * Memperbarui respon / negosiasi klien terhadap Surat Penawaran
     */
    public function updateResponKlien(int $penawaranId, string $statusRespon, string $catatanNego = '', float $nominalBaru = 0.0): array
    {
        $this->load(['id = ?', $penawaranId]);
        if ($this->dry()) {
            throw new \Exception("Surat Penawaran #{$penawaranId} tidak ditemukan.");
        }

        $this->status_respon_klien = $statusRespon;
        if (!empty($catatanNego)) {
            $this->catatan_nego = $catatanNego;
        }

        if ($nominalBaru > 0) {
            $this->nominal_penawaran = $nominalBaru;
        }

        if ($statusRespon === 'deal') {
            $this->disetujui_klien_at = date('Y-m-d H:i:s');
        }

        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();

        // Sinkronisasi ke order jika terhubung
        if (!empty($this->order_id)) {
            $this->db->exec(
                "UPDATE order_layanan SET 
                    status_penawaran = ?, 
                    estimasi_biaya = " . ($nominalBaru > 0 ? (float)$nominalBaru : "estimasi_biaya") . " 
                 WHERE id = ?",
                array(
                    1 => $statusRespon,
                    2 => $this->order_id
                )
            );
        }

        return [
            'penawaran_id' => $this->id,
            'order_id'     => $this->order_id,
            'status'       => $this->status_respon_klien,
            'nominal'      => $this->nominal_penawaran
        ];
    }
}