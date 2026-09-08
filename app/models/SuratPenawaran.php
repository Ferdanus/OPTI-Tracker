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
     * Ambil data surat penawaran berdasarkan ID Primary Key
     */
    public function getById(int $id): ?array
    {
        $res = $this->db->exec(
            "SELECT sp.*, c.nmcustomer, c.pt_cv, c.alamatcustomer, c.notelpcustomer, c.emailcustomer,
                    u.nama_user AS pembuat_nama
             FROM tb_surat_penawaran sp
             LEFT JOIN tb_customer c ON sp.customer_id = c.id_customer
             LEFT JOIN tb_arsipuser u ON sp.dibuat_oleh = u.id_user
             WHERE sp.id = ?
             LIMIT 1",
            array(1 => $id)
        );
        return !empty($res) ? $res[0] : null;
    }

    /**
     * Ambil seluruh riwayat surat penawaran (audit trail penawaran/revisi) untuk sebuah order
     */
    public function getAllByOrderId(int $orderId): array
    {
        return $this->db->exec(
            "SELECT sp.*, c.nmcustomer, c.pt_cv, c.alamatcustomer, c.notelpcustomer, c.emailcustomer,
                    u.nama_user AS pembuat_nama
             FROM tb_surat_penawaran sp
             LEFT JOIN tb_customer c ON sp.customer_id = c.id_customer
             LEFT JOIN tb_arsipuser u ON sp.dibuat_oleh = u.id_user
             WHERE sp.order_id = ?
             ORDER BY sp.id ASC",
            array(1 => $orderId)
        );
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
        $perusahaan   = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*){2,}/i', '$1 ', $perusahaan);
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

        $isRevisiBaru = !empty($data['is_revisi_baru']) || (($data['mode'] ?? '') === 'baru');
        $penawaranIdInput = (int)($data['penawaran_id'] ?? 0);
        $existing = $this->getByOrderId($orderId);

        if (!$isRevisiBaru && ($penawaranIdInput > 0 || $existing)) {
            $targetId = $penawaranIdInput > 0 ? $penawaranIdInput : (int)$existing['id'];
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
                    14 => $targetId
                )
            );
            $penawaranId = $targetId;
        } else {
            // INSERT sebagai riwayat penawaran baru (audit trail revisi)
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

        // Sinkronisasi status, ID surat penawaran, nominal biaya, durasi (SPM), dan status proposal ke order_layanan
        $spmLayanan = trim($data['spm_layanan'] ?? '');
        $updateOrderSql = "UPDATE order_layanan SET status_penawaran = ?, surat_penawaran_id = ?, estimasi_biaya = ?, status_proposal_biaya = IF(status_proposal_biaya = 'disetujui', 'disetujui', 'siap_penawaran')";
        $orderParams = [1 => $statusRespon, 2 => $penawaranId, 3 => $nominal];
        if (!empty($spmLayanan)) {
            $updateOrderSql .= ", spm_layanan = ?";
            $orderParams[4] = $spmLayanan;
            $orderParams[5] = $orderId;
            $updateOrderSql .= " WHERE id = ?";
        } else {
            $orderParams[4] = $orderId;
            $updateOrderSql .= " WHERE id = ?";
        }
        $this->db->exec($updateOrderSql, $orderParams);

        // Sinkronisasi ke tb_surat_keluar
        $idSuratMasuk = (int)($order['id_surat_masuk'] ?? 0);
        $this->syncKeSuratKeluar([
            'nomor_surat'        => $nomorSurat,
            'tanggal_surat'      => $tanggalSurat,
            'perihal'            => $perihal,
            'customer_id'        => $order['id_customer'] ?? 0,
            'id_arsip'           => $idSuratMasuk,
            'file_lampiran'      => $fileLampiran,
            'nominal_penawaran'  => $nominal
        ]);

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
    public function updateResponKlien(int $penawaranId, string $statusRespon, string $catatanNego = '', float $nominalBaru = 0.0, string $spmLayananBaru = ''): array
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
            $statusRancop = ($statusRespon === 'deal') ? 'deal' : (($statusRespon === 'batal') ? 'batal' : 'diskusi');
            $statusKeuanganUpdate = ($statusRespon === 'deal') ? ", status_keuangan = IF(status_keuangan = 'lunas', 'lunas', 'menunggu_pembayaran')" : "";
            
            $updateSql = "UPDATE order_layanan SET 
                status_penawaran = ?, 
                status_rancop = ?" . $statusKeuanganUpdate . ",
                estimasi_biaya = " . ($nominalBaru > 0 ? (float)$nominalBaru : "estimasi_biaya");

            $params = [
                1 => $statusRespon,
                2 => $statusRancop
            ];

            if (!empty($spmLayananBaru)) {
                $updateSql .= ", spm_layanan = ?";
                $params[3] = $spmLayananBaru;
                $params[4] = $this->order_id;
                $updateSql .= " WHERE id = ?";
            } else {
                $params[3] = $this->order_id;
                $updateSql .= " WHERE id = ?";
            }
            $this->db->exec($updateSql, $params);

            // Sinkronisasi pembaruan ke tb_surat_keluar
            $orderRow = $this->db->exec("SELECT id_surat_masuk, id_customer FROM order_layanan WHERE id = ?", [1 => $this->order_id]);
            $idSuratMasuk = !empty($orderRow) ? (int)($orderRow[0]['id_surat_masuk'] ?? 0) : 0;
            $this->syncKeSuratKeluar([
                'nomor_surat'        => $this->nomor_surat,
                'tanggal_surat'      => $this->tanggal_surat,
                'perihal'            => $this->perihal,
                'customer_id'        => $this->customer_id,
                'id_arsip'           => $idSuratMasuk,
                'file_lampiran'      => $this->file_lampiran,
                'nominal_penawaran'  => $this->nominal_penawaran,
                'keterangan'         => "Respon: " . strtoupper($statusRespon) . ($catatanNego ? " - Catatan: {$catatanNego}" : "")
            ]);
        }

        return [
            'penawaran_id' => $this->id,
            'order_id'     => $this->order_id,
            'status'       => $this->status_respon_klien,
            'nominal'      => $this->nominal_penawaran,
            'spm_layanan'  => $spmLayananBaru
        ];
    }

    /**
     * Sinkronisasi Surat Penawaran ke tabel tb_surat_keluar (Sekretariat / SIL)
     */
    public function syncKeSuratKeluar(array $penawaranData, ?\DB\SQL $dbSekretariat = null): void
    {
        $f3 = \Base::instance();
        if (!$dbSekretariat && $f3->exists('DB_SEKRETARIAT')) {
            $dbSekretariat = $f3->get('DB_SEKRETARIAT');
        }

        $noSurat      = trim($penawaranData['nomor_surat'] ?? '');
        $tanggalSurat = !empty($penawaranData['tanggal_surat']) ? $penawaranData['tanggal_surat'] : date('Y-m-d');
        $perihal      = trim($penawaranData['perihal'] ?? '');
        $idCustomer   = (int)($penawaranData['customer_id'] ?? 0);
        $idArsip      = (int)($penawaranData['id_arsip'] ?? 0);
        $berkas       = trim($penawaranData['file_lampiran'] ?? '');
        $keterangan   = trim($penawaranData['keterangan'] ?? '');
        if (empty($keterangan) && isset($penawaranData['nominal_penawaran'])) {
            $keterangan = 'Surat Penawaran OPTI - Nilai: Rp ' . number_format((float)$penawaranData['nominal_penawaran'], 0, ',', '.');
        }
        $jabatan      = 'Ketua Tim Kerja OPTI';

        $targets = [$this->db];
        if ($dbSekretariat && $dbSekretariat !== $this->db) {
            $targets[] = $dbSekretariat;
        }

        foreach ($targets as $targetDb) {
            try {
                $checkTbl = $targetDb->exec("SHOW TABLES LIKE 'tb_surat_keluar'");
                if (empty($checkTbl)) continue;

                $existing = [];
                if (!empty($noSurat)) {
                    $existing = $targetDb->exec(
                        "SELECT id_surat_keluar FROM `tb_surat_keluar` WHERE no_surat = ? AND surat_penawaran = 'Y' AND dipilih_sis_opti = 'Y' LIMIT 1",
                        [1 => $noSurat]
                    );
                }
                if (empty($existing) && $idArsip > 0) {
                    $existing = $targetDb->exec(
                        "SELECT id_surat_keluar FROM `tb_surat_keluar` WHERE id_arsip = ? AND surat_penawaran = 'Y' AND dipilih_sis_opti = 'Y' LIMIT 1",
                        [1 => $idArsip]
                    );
                }

                if (!empty($existing)) {
                    $idSk = (int)$existing[0]['id_surat_keluar'];
                    $targetDb->exec(
                        "UPDATE `tb_surat_keluar` SET 
                            no_surat = ?, tanggal_surat = ?, perihal_surat = ?, jabatan_surat = ?,
                            id_customer = ?, keterangan = ?, nama_berkas_surat_keluar = ?,
                            id_arsip = ?, surat_penawaran = 'Y', biaya_opti = 'Y', dipilih_sis_opti = 'Y'
                         WHERE id_surat_keluar = ?",
                        [
                            1 => $noSurat,
                            2 => $tanggalSurat,
                            3 => $perihal,
                            4 => $jabatan,
                            5 => $idCustomer,
                            6 => $keterangan,
                            7 => $berkas,
                            8 => $idArsip,
                            9 => $idSk
                        ]
                    );
                } else {
                    $targetDb->exec(
                        "INSERT INTO `tb_surat_keluar` (
                            no_surat, tanggal_surat, perihal_surat, jabatan_surat,
                            id_customer, keterangan, nama_berkas_surat_keluar, tanggal_simpan,
                            id_arsip, surat_penawaran, biaya_opti, dipilih_sis_opti
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?, ?, NOW(),
                            ?, 'Y', 'Y', 'Y'
                        )",
                        [
                            1 => $noSurat,
                            2 => $tanggalSurat,
                            3 => $perihal,
                            4 => $jabatan,
                            5 => $idCustomer,
                            6 => $keterangan,
                            7 => $berkas,
                            8 => $idArsip
                        ]
                    );
                }
            } catch (\Exception $e) {
                error_log("Gagal sinkronisasi tb_surat_keluar: " . $e->getMessage());
            }
        }
    }

    /**
     * Hapus sinkronisasi di tb_surat_keluar
     */
    public function hapusDariSuratKeluar(string $noSurat, int $idArsip = 0, ?\DB\SQL $dbSekretariat = null): void
    {
        $f3 = \Base::instance();
        if (!$dbSekretariat && $f3->exists('DB_SEKRETARIAT')) {
            $dbSekretariat = $f3->get('DB_SEKRETARIAT');
        }

        $targets = [$this->db];
        if ($dbSekretariat && $dbSekretariat !== $this->db) {
            $targets[] = $dbSekretariat;
        }

        foreach ($targets as $targetDb) {
            try {
                if (!empty($noSurat)) {
                    $targetDb->exec(
                        "DELETE FROM `tb_surat_keluar` WHERE no_surat = ? AND surat_penawaran = 'Y' AND dipilih_sis_opti = 'Y'",
                        [1 => $noSurat]
                    );
                }
                if ($idArsip > 0) {
                    $targetDb->exec(
                        "DELETE FROM `tb_surat_keluar` WHERE id_arsip = ? AND surat_penawaran = 'Y' AND dipilih_sis_opti = 'Y'",
                        [1 => $idArsip]
                    );
                }
            } catch (\Exception $e) {}
        }
    }
}