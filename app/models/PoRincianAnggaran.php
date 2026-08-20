<?php

/**
 * Model PoRincianAnggaran
 * Mengelola rincian breakdown penggunaan anggaran per PO (Bahan/Material, Jasa Layanan, dll.)
 * 
 * TODO: Struktur kategori rincian anggaran PO (material, jasa layanan, transport, operasional)
 * masih contoh baku - konfirmasi daftar kategori resmi ke pengguna asli.
 */
class PoRincianAnggaran extends \DB\SQL\Mapper {

    public static $KATEGORI_LIST = array(
        'Bahan/Material'         => 'Bahan Kimia / Reagen / Material Uji',
        'Jasa Layanan/Pengujian' => 'Jasa Analisis & Pengujian Laboratorium',
        'Transport/Sampling'     => 'Biaya Transportasi, Akomodasi & Sampling Lapangan',
        'Operasional/Lain-lain'  => 'Biaya Operasional, Penyiapan Alat & Lain-lain',
        'Lainnya'                => 'Kategori Belanja Lainnya'
    );

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'po_rincian_anggaran');
    }

    /**
     * Ambil seluruh rincian anggaran untuk sebuah PO
     */
    public function getByPoId(int $poId): array {
        return $this->db->exec(
            "SELECT * FROM po_rincian_anggaran WHERE po_id = ? ORDER BY id ASC",
            array(1 => $poId)
        );
    }

    /**
     * Tambah item rincian anggaran baru
     */
    public function tambahItem(int $poId, array $data): int {
        $kategori  = trim($data['kategori'] ?? 'Jasa Layanan/Pengujian');
        $deskripsi = trim($data['deskripsi'] ?? '');
        $nominal   = (float)($data['nominal'] ?? 0);

        if ($nominal <= 0) {
            throw new \Exception("Nominal rincian anggaran harus lebih dari 0.");
        }

        $this->db->exec(
            "INSERT INTO po_rincian_anggaran (po_id, kategori, deskripsi, nominal, created_at) VALUES (?, ?, ?, ?, NOW())",
            array(
                1 => $poId,
                2 => $kategori,
                3 => $deskripsi,
                4 => $nominal
            )
        );

        $insertedId = (int)$this->db->lastInsertId();

        // Sinkronkan total biaya PO
        $this->sinkronTotalBiayaPo($poId);

        return $insertedId;
    }

    /**
     * Hapus item rincian anggaran
     */
    public function hapusItem(int $id, ?int $poId = null): bool {
        if (!$poId) {
            $item = $this->db->exec("SELECT po_id FROM po_rincian_anggaran WHERE id = ?", array(1 => $id));
            if (!empty($item)) {
                $poId = (int)$item[0]['po_id'];
            }
        }

        $this->db->exec("DELETE FROM po_rincian_anggaran WHERE id = ?", array(1 => $id));

        if ($poId) {
            $this->sinkronTotalBiayaPo($poId);
        }

        return true;
    }

    /**
     * Hitung total akumulasi rincian biaya untuk sebuah PO
     */
    public function hitungTotalBiaya(int $poId): float {
        $res = $this->db->exec(
            "SELECT COALESCE(SUM(nominal), 0) AS total FROM po_rincian_anggaran WHERE po_id = ?",
            array(1 => $poId)
        );
        return (float)($res[0]['total'] ?? 0);
    }

    /**
     * Sinkronisasikan kolom 'biaya' di tabel PO dengan akumulasi rincian RAB
     */
    public function sinkronTotalBiayaPo(int $poId): void {
        $total = $this->hitungTotalBiaya($poId);
        if ($total > 0) {
            $this->db->exec(
                "UPDATE po SET biaya = ? WHERE id = ?",
                array(1 => $total, 2 => $poId)
            );
        }
    }
}
