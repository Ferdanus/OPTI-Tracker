<?php

/**
 * Model PoJadwalKerja
 * Mengelola jadwal kegiatan dan penugasan personil tim kerja (kalender/timeline kerja)
 */
class PoJadwalKerja extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_po_jadwal_kerja');
    }

    /**
     * Ambil seluruh jadwal kerja untuk sebuah PO
     */
    public function getByPoId(int $poId): array {
        return $this->db->exec(
            "SELECT * FROM opti_po_jadwal_kerja WHERE po_id = ? ORDER BY tanggal_mulai ASC, id ASC",
            array(1 => $poId)
        );
    }

    /**
     * Tambah item jadwal kerja baru
     */
    public function tambahJadwal(int $poId, array $data): int {
        $personil       = trim($data['personil_anggota'] ?? '');
        $tahapKegiatan  = trim($data['tahap_kegiatan'] ?? '');
        $tanggalMulai   = $data['tanggal_mulai'] ?? date('Y-m-d');
        $tanggalSelesai = $data['tanggal_selesai'] ?? date('Y-m-d');
        $status         = in_array($data['status_pekerjaan'] ?? '', array('rencana', 'berjalan', 'selesai')) ? $data['status_pekerjaan'] : 'rencana';
        $keterangan     = trim($data['keterangan'] ?? '');

        if (empty($personil) || empty($tahapKegiatan)) {
            throw new \Exception("Nama personil dan uraian tahap kegiatan wajib diisi.");
        }

        $this->db->exec(
            "INSERT INTO opti_po_jadwal_kerja (po_id, personil_anggota, tahap_kegiatan, tanggal_mulai, tanggal_selesai, status_pekerjaan, keterangan, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            array(
                1 => $poId,
                2 => $personil,
                3 => $tahapKegiatan,
                4 => $tanggalMulai,
                5 => $tanggalSelesai,
                6 => $status,
                7 => $keterangan
            )
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update status pekerjaan jadwal
     */
    public function updateStatus(int $id, string $status): bool {
        $this->db->exec(
            "UPDATE opti_po_jadwal_kerja SET status_pekerjaan = ? WHERE id = ?",
            array(1 => $status, 2 => $id)
        );
        return true;
    }

    /**
     * Hapus jadwal kegiatan
     */
    public function hapus(int $id): bool {
        $this->db->exec("DELETE FROM opti_po_jadwal_kerja WHERE id = ?", array(1 => $id));
        return true;
    }
}
