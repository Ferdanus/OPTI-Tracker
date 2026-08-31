<?php

/**
 * Model OptiBast
 * Mengelola Berita Acara Serah Terima (BAST) dan Penutupan & Pengarsipan Order Layanan OPTI
 */
class OptiBast extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_bast');
    }

    /**
     * Generate nomor BAST resmi balai format: {urut}/BAST/BBSPJIS/{bulan_romawi}/{tahun}
     */
    public function generateNomorBast(string $tanggal = ''): string
    {
        $time = !empty($tanggal) ? strtotime($tanggal) : time();
        if (!$time) $time = time();

        $bulanAngka  = (int) date('n', $time);
        $tahun       = date('Y', $time);
        $bulanRomawi = SuratPenawaran::bulanKeRomawi($bulanAngka);

        $pattern = "%/BAST/BBSPJIS/{$bulanRomawi}/{$tahun}";
        $res = $this->db->exec(
            "SELECT nomor_bast FROM opti_bast WHERE nomor_bast LIKE ? ORDER BY id DESC LIMIT 1",
            array(1 => $pattern)
        );

        $urut = 1;
        if (!empty($res) && !empty($res[0]['nomor_bast'])) {
            $parts = explode('/', $res[0]['nomor_bast']);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $urut = (int) $parts[0] + 1;
            }
        }

        $nomorUrutPadded = str_pad((string)$urut, 2, '0', STR_PAD_LEFT);
        return "{$nomorUrutPadded}/BAST/BBSPJIS/{$bulanRomawi}/{$tahun}";
    }

    /**
     * Simpan data BAST baru
     */
    public function simpanBaru(array $data): int {
        $this->reset();
        $this->order_id              = (int)$data['order_id'];
        $this->po_id                 = !empty($data['po_id']) ? (int)$data['po_id'] : null;
        $this->nomor_bast            = !empty($data['nomor_bast']) ? trim($data['nomor_bast']) : $this->generateNomorBast($data['tanggal_bast'] ?? '');
        $this->tanggal_bast         = $data['tanggal_bast'] ?? date('Y-m-d');
        $this->pihak_pertama_nama    = trim($data['pihak_pertama_nama'] ?? 'Kepala BBSPJIS');
        $this->pihak_pertama_jabatan = trim($data['pihak_pertama_jabatan'] ?? 'Kepala Balai Besar');
        $this->pihak_kedua_nama      = trim($data['pihak_kedua_nama'] ?? '');
        $this->pihak_kedua_jabatan   = trim($data['pihak_kedua_jabatan'] ?? '');
        $this->judul_pekerjaan       = trim($data['judul_pekerjaan'] ?? '');
        $this->ringkasan_serah_terima= trim($data['ringkasan_serah_terima'] ?? '');
        $this->file_dokumen_bast     = !empty($data['file_dokumen_bast']) ? $data['file_dokumen_bast'] : null;
        $this->status_bast           = $data['status_bast'] ?? 'draft';
        $this->status_penutupan      = $data['status_penutupan'] ?? 'aktif';
        $this->created_at            = date('Y-m-d H:i:s');
        $this->save();

        $bastId = (int)$this->id;

        // Sinkronkan data BAST ke PO jika terhubung
        if (!empty($this->po_id)) {
            $this->db->exec(
                "UPDATE po SET bast_dokumen = ?, tgl_bast = ? WHERE id = ?",
                array(1 => $this->file_dokumen_bast, 2 => $this->tanggal_bast, 3 => $this->po_id)
            );
        }

        return $bastId;
    }

    /**
     * Update data BAST
     */
    public function updateData(int $id, array $data): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("BAST #{$id} tidak ditemukan.");
        }

        $this->nomor_bast            = !empty($data['nomor_bast']) ? trim($data['nomor_bast']) : $this->nomor_bast;
        $this->tanggal_bast         = $data['tanggal_bast'] ?? $this->tanggal_bast;
        $this->pihak_pertama_nama    = trim($data['pihak_pertama_nama'] ?? $this->pihak_pertama_nama);
        $this->pihak_pertama_jabatan = trim($data['pihak_pertama_jabatan'] ?? $this->pihak_pertama_jabatan);
        $this->pihak_kedua_nama      = trim($data['pihak_kedua_nama'] ?? $this->pihak_kedua_nama);
        $this->pihak_kedua_jabatan   = trim($data['pihak_kedua_jabatan'] ?? $this->pihak_kedua_jabatan);
        $this->judul_pekerjaan       = trim($data['judul_pekerjaan'] ?? $this->judul_pekerjaan);
        $this->ringkasan_serah_terima= trim($data['ringkasan_serah_terima'] ?? $this->ringkasan_serah_terima);
        if (!empty($data['file_dokumen_bast'])) {
            $this->file_dokumen_bast = $data['file_dokumen_bast'];
        }
        $this->status_bast           = $data['status_bast'] ?? $this->status_bast;
        $this->status_penutupan      = $data['status_penutupan'] ?? $this->status_penutupan;
        $this->updated_at            = date('Y-m-d H:i:s');
        $this->save();

        if (!empty($this->po_id)) {
            $this->db->exec(
                "UPDATE po SET bast_dokumen = ?, tgl_bast = ? WHERE id = ?",
                array(1 => $this->file_dokumen_bast, 2 => $this->tanggal_bast, 3 => $this->po_id)
            );
        }

        return true;
    }

    /**
     * Ambil data BAST berdasarkan Order ID
     */
    public function getByOrderId(int $orderId): ?array {
        $res = $this->db->exec(
            "SELECT b.*, u.nama_user AS closed_by_nama 
             FROM opti_bast b 
             LEFT JOIN tb_arsipuser u ON b.closed_by = u.id_user 
             WHERE b.order_id = ? ORDER BY b.id DESC LIMIT 1",
            array(1 => $orderId)
        );
        return $res[0] ?? null;
    }

    /**
     * Ambil data BAST berdasarkan PO ID
     */
    public function getByPoId(int $poId): ?array {
        $res = $this->db->exec(
            "SELECT b.*, u.nama_user AS closed_by_nama 
             FROM opti_bast b 
             LEFT JOIN tb_arsipuser u ON b.closed_by = u.id_user 
             WHERE b.po_id = ? ORDER BY b.id DESC LIMIT 1",
            array(1 => $poId)
        );
        return $res[0] ?? null;
    }

    /**
     * Memproses Penutupan & Pengarsipan Order (Closing Order)
     */
    public function tutupOrder(int $bastId, int $orderId, string $catatan, int $userId): bool {
        $this->load(array('id = ?', $bastId));
        if ($this->dry()) {
            throw new \Exception("BAST #{$bastId} tidak ditemukan.");
        }

        $now = date('Y-m-d H:i:s');
        $this->status_bast      = 'selesai';
        $this->status_penutupan = 'selesai_diarsipkan';
        $this->catatan_penutupan= trim($catatan);
        $this->closed_by        = $userId;
        $this->closed_at        = $now;
        $this->updated_at       = $now;
        $this->save();

        // Update status order_layanan menjadi 'selesai'
        $this->db->exec(
            "UPDATE order_layanan SET status = 'selesai', status_pelaksanaan = 'laporan_selesai' WHERE id = ?",
            array(1 => $orderId)
        );

        // Jika ada PO, pastikan PO juga selesai
        if (!empty($this->po_id)) {
            $this->db->exec(
                "UPDATE po SET status = 'kembali_selesai', realisasi_selesai = COALESCE(realisasi_selesai, CURDATE()) WHERE id = ?",
                array(1 => $this->po_id)
            );

            $logModel = new PoLogStatus($this->db);
            $logModel->catat(
                $this->po_id,
                'kembali_selesai',
                'kembali_selesai',
                "Order & PO resmi DITUTUP & DIARSIPKAN. BAST No: {$this->nomor_bast}. Catatan: {$catatan}"
            );
        }

        return true;
    }

    /**
     * Hapus data BAST
     */
    public function hapus(int $id): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("BAST #{$id} tidak ditemukan.");
        }
        $this->erase();
        return true;
    }
}