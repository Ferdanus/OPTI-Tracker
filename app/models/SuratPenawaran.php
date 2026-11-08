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
        self::ensureColumns($db);
        parent::__construct($db, 'tb_surat_penawaran');
    }

    /**
     * Pastikan kolom-kolom baru tersedia di tb_surat_penawaran
     */
    public static function ensureColumns(\DB\SQL $db): void
    {
        static $checked = false;
        if ($checked) return;
        try {
            $cols = [
                'ruang_lingkup'      => 'TEXT NULL',
                'jadwal_pelaksanaan' => 'TEXT NULL',
                'catatan_sampel'     => 'TEXT NULL',
                'jabatan_pejabat'    => 'VARCHAR(100) NULL DEFAULT "Kepala"',
                'pejabat_nama'       => 'VARCHAR(150) NULL DEFAULT "Dodiet Prasetyo"',
                'hal'                => 'VARCHAR(150) NULL DEFAULT "Biaya OPTI"',
                'lampiran_teks'      => 'VARCHAR(100) NULL DEFAULT "1 (satu) lembar"',
                'dibuat_oleh'            => 'INT NULL',
                'file_lampiran_user'     => 'VARCHAR(255) NULL',
                'file_penawaran_ttd'     => 'VARCHAR(255) NULL',
                'file_bukti_persetujuan' => 'VARCHAR(255) NULL'
            ];
            foreach ($cols as $col => $def) {
                try {
                    $db->exec("ALTER TABLE tb_surat_penawaran ADD COLUMN {$col} {$def}");
                } catch (\Exception $e) {
                    // Column already exists, ignore
                }
            }
            $checked = true;
        } catch (\Exception $e) {}
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
     * Generate nomor surat penawaran resmi format balai: B/{urut}/BBSPJIS/MS/{bulan_romawi}/{tahun}
     * Otomatis sinkron dan mengambil nomor urut berikutnya dari tabel surat keluar (tb_surat_keluar) & tb_surat_penawaran
     */
    public function generateNomorSurat(string $tanggal = ''): string
    {
        $time = !empty($tanggal) && strtotime($tanggal) ? strtotime($tanggal) : time();

        $bulanAngka  = (int) date('n', $time);
        $tahun       = date('Y', $time);
        $bulanRomawi = self::bulanKeRomawi($bulanAngka);

        $maxUrut = 0;
        $patternBulanTahun = "%/{$bulanRomawi}/{$tahun}%";
        $firstDay = "{$tahun}-" . str_pad((string)$bulanAngka, 2, '0', STR_PAD_LEFT) . "-01";
        $lastDay  = date('Y-m-t', $time);

        // 1. Cek dari tb_surat_keluar (database saat ini dan database sekretariat jika terpisah)
        $f3 = \Base::instance();
        $targetDbs = [$this->db];
        if ($f3->exists('DB_SEKRETARIAT')) {
            $dbSekretariat = $f3->get('DB_SEKRETARIAT');
            if ($dbSekretariat && $dbSekretariat !== $this->db) {
                $targetDbs[] = $dbSekretariat;
            }
        }

        $allNumbers = [];
        foreach ($targetDbs as $tDb) {
            try {
                $checkTbl = $tDb->exec("SHOW TABLES LIKE 'tb_surat_keluar'");
                if (!empty($checkTbl)) {
                    $rows = $tDb->exec(
                        "SELECT no_surat FROM tb_surat_keluar WHERE no_surat LIKE ? OR (tanggal_surat >= ? AND tanggal_surat <= ?)",
                        [1 => $patternBulanTahun, 2 => $firstDay, 3 => $lastDay]
                    );
                    if (!empty($rows)) {
                        foreach ($rows as $r) {
                            if (!empty($r['no_surat'])) {
                                $allNumbers[] = trim($r['no_surat']);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {}
        }

        // 2. Cek dari tb_surat_penawaran
        try {
            $rowsSp = $this->db->exec(
                "SELECT nomor_surat FROM tb_surat_penawaran WHERE nomor_surat LIKE ? OR (tanggal_surat >= ? AND tanggal_surat <= ?)",
                [1 => $patternBulanTahun, 2 => $firstDay, 3 => $lastDay]
            );
            if (!empty($rowsSp)) {
                foreach ($rowsSp as $r) {
                    if (!empty($r['nomor_surat'])) {
                        $allNumbers[] = trim($r['nomor_surat']);
                    }
                }
            }
        } catch (\Exception $e) {}

        // Ambil nilai urutan tertinggi pada bulan dan tahun terkait
        foreach (array_unique($allNumbers) as $no) {
            if (preg_match('/^(?:B\/)?(\d+)/i', trim($no), $m)) {
                $val = (int)$m[1];
                if ($val > $maxUrut) {
                    $maxUrut = $val;
                }
            }
        }

        $nextUrut = $maxUrut + 1;
        $nomorUrutPadded = str_pad((string)$nextUrut, 2, '0', STR_PAD_LEFT);
        return "B/{$nomorUrutPadded}/BBSPJIS/MS/{$bulanRomawi}/{$tahun}";
    }

    /**
     * Ambil data surat penawaran berdasarkan ID Order
     */
    public function getByOrderId(int $orderId): ?array
    {
        $res = $this->db->exec(
            "SELECT sp.*,
                    COALESCE(
                        IF(o.jenis_layanan_opti = 'selulosa' AND pr.file_proposal IS NOT NULL AND pr.file_proposal <> '', pr.file_proposal, NULLIF(sp.file_lampiran_user, '')),
                        NULLIF(sp.file_lampiran_user, '')
                    ) AS file_lampiran_user,
                    c.nmcustomer, c.pt_cv, c.alamatcustomer, c.notelpcustomer, c.emailcustomer,
                    u.nama_user AS pembuat_nama
             FROM tb_surat_penawaran sp
             LEFT JOIN order_layanan o ON sp.order_id = o.id
             LEFT JOIN opti_proposal_riset pr ON sp.order_id = pr.order_id
             LEFT JOIN tb_customer c ON sp.customer_id = c.id_customer
             LEFT JOIN tb_arsipuser u ON sp.dibuat_oleh = u.id_user
             WHERE sp.order_id = ?
               AND ((sp.nomor_surat IS NOT NULL AND sp.nomor_surat != '') OR sp.nominal_penawaran > 0 OR sp.status_respon_klien != 'draft')
             ORDER BY (sp.status_respon_klien = 'deal') DESC, sp.id DESC LIMIT 1",
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
            "SELECT sp.*,
                    COALESCE(
                        IF(o.jenis_layanan_opti = 'selulosa' AND pr.file_proposal IS NOT NULL AND pr.file_proposal <> '', pr.file_proposal, NULLIF(sp.file_lampiran_user, '')),
                        NULLIF(sp.file_lampiran_user, '')
                    ) AS file_lampiran_user,
                    c.nmcustomer, c.pt_cv, c.alamatcustomer, c.notelpcustomer, c.emailcustomer,
                    u.nama_user AS pembuat_nama
             FROM tb_surat_penawaran sp
             LEFT JOIN order_layanan o ON sp.order_id = o.id
             LEFT JOIN opti_proposal_riset pr ON sp.order_id = pr.order_id
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
            "SELECT sp.*,
                    COALESCE(
                        IF(o.jenis_layanan_opti = 'selulosa' AND pr.file_proposal IS NOT NULL AND pr.file_proposal <> '', pr.file_proposal, NULLIF(sp.file_lampiran_user, '')),
                        NULLIF(sp.file_lampiran_user, '')
                    ) AS file_lampiran_user,
                    c.nmcustomer, c.pt_cv, c.alamatcustomer, c.notelpcustomer, c.emailcustomer,
                    u.nama_user AS pembuat_nama
             FROM tb_surat_penawaran sp
             LEFT JOIN order_layanan o ON sp.order_id = o.id
             LEFT JOIN opti_proposal_riset pr ON sp.order_id = pr.order_id
             LEFT JOIN tb_customer c ON sp.customer_id = c.id_customer
             LEFT JOIN tb_arsipuser u ON sp.dibuat_oleh = u.id_user
             WHERE sp.order_id = ?
               AND ((sp.nomor_surat IS NOT NULL AND sp.nomor_surat != '') OR sp.nominal_penawaran > 0 OR sp.status_respon_klien != 'draft')
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
        if (preg_match('/^\d+\/BBSPJIS\/MS\//i', $nomorSurat)) {
            $nomorSurat = 'B/' . $nomorSurat;
        }
        $isLingkungan = ($order['jenis_layanan_opti'] ?? '') === 'lingkungan';
        $perihal      = isset($data['perihal']) ? trim($data['perihal']) : ($isLingkungan ? '' : 'Penawaran Layanan Jasa OPTI - ' . $order['judul_kegiatan']);
        $nominal      = isset($data['nominal_penawaran']) ? (float)$data['nominal_penawaran'] : (float)$order['estimasi_biaya'];
        $namaPic      = !empty($data['nama']) ? trim($data['nama']) : ($order['pic'] ?? '');
        $perusahaan   = !empty($data['perusahaan']) ? trim($data['perusahaan']) : ($order['nama_perusahaan'] ?? '');
        $perusahaan   = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*){2,}/i', '$1 ', $perusahaan);
        $alamat       = !empty($data['alamat']) ? trim($data['alamat']) : ($order['alamatcustomer'] ?? '');
        $permintaanMelalui = !empty($data['permintaan_melalui']) ? trim($data['permintaan_melalui']) : (!empty($order['media_permohonan']) ? $order['media_permohonan'] : 'email');
        $penjelasan   = !empty($data['penjelasan']) ? trim($data['penjelasan']) : ($order['deskripsi'] ?? '');
        $fileLampiran = !empty($data['file_lampiran']) ? trim($data['file_lampiran']) : '';
        $fileLampiranUser = !empty($data['file_lampiran_user']) ? trim($data['file_lampiran_user']) : '';
        $statusRespon = in_array($data['status_respon_klien'] ?? '', ['draft', 'terkirim', 'nego', 'deal', 'batal']) ? $data['status_respon_klien'] : 'draft';
        $catatanNego  = trim($data['catatan_nego'] ?? '');

        $ruangLingkup      = isset($data['ruang_lingkup']) ? trim($data['ruang_lingkup']) : null;
        $jadwalPelaksanaan = isset($data['jadwal_pelaksanaan']) ? trim($data['jadwal_pelaksanaan']) : null;
        $catatanSampel     = isset($data['catatan_sampel']) ? trim($data['catatan_sampel']) : null;
        $jabatanPejabat    = !empty($data['jabatan_pejabat']) ? trim($data['jabatan_pejabat']) : ($isLingkungan ? 'Kepala Bagian Tata Usaha' : 'Kepala');
        $pejabatNama       = !empty($data['pejabat_nama']) ? trim($data['pejabat_nama']) : (!empty($data['pembuat_nama']) && $data['pembuat_nama'] !== 'Tim Mitra BBSPJIS' ? trim($data['pembuat_nama']) : ($isLingkungan ? 'Joko Pratomo' : 'Dodiet Prasetyo'));
        $defaultHalMdl     = $isLingkungan ? 'Biaya Pengujian Lingkungan' : 'Biaya OPTI';
        $hal               = (!empty($data['hal']) && trim($data['hal']) !== '') ? trim($data['hal']) : $defaultHalMdl;
        if (!empty($order['judul_kegiatan']) && ($hal === trim($order['judul_kegiatan']) || stripos($hal, 'Penawaran Layanan Jasa OPTI') !== false)) {
            $hal           = $defaultHalMdl;
        }
        $lampiranTeks      = isset($data['lampiran_teks']) ? trim($data['lampiran_teks']) : ($isLingkungan ? '' : '1 (satu) lembar');

        // Jika ada proposal selulosa dan ruang_lingkup kosong, coba ambil dari proposal
        if (empty($ruangLingkup) && $order['jenis_layanan_opti'] === 'selulosa') {
            $prop = $orderModel->getProposalRiset($orderId);
            if (!empty($prop['ruang_lingkup'])) {
                $ruangLingkup = $prop['ruang_lingkup'];
            }
        }

        // Jika ada proposal selulosa, ambil file lampirannya (halaman 3+) dari proposal riset Tahap 4
        if ($order['jenis_layanan_opti'] === 'selulosa') {
            if (!isset($prop)) {
                $prop = $orderModel->getProposalRiset($orderId);
            }
            if (!empty($prop['file_proposal']) && empty($fileLampiranUser)) {
                $fileLampiranUser = $prop['file_proposal'];
            }
        }

        $isRevisiBaru = !empty($data['is_revisi_baru']) || (($data['mode'] ?? '') === 'baru');
        $penawaranIdInput = (int)($data['penawaran_id'] ?? 0);
        $existing = $this->getByOrderId($orderId);

        if (empty($fileLampiranUser) && !empty($existing['file_lampiran_user'])) {
            $fileLampiranUser = $existing['file_lampiran_user'];
        }
        if (empty($fileLampiranUser) && ($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
            if (!isset($prop)) {
                $prop = $orderModel->getProposalRiset($orderId);
            }
            if (!empty($prop['file_proposal'])) {
                $fileLampiranUser = $prop['file_proposal'];
            }
        }

        // Deteksi apakah penawaran yang ada sebelumnya berstatus ditolak / batal
        $isExistingDitolak = $existing && in_array(strtolower($existing['status_respon_klien'] ?? ''), ['batal', 'tolak', 'ditolak']);

        // KASUS UPDATE (Bukan revisi baru):
        // 1. Jika revisi baru atau penawaran lama sudah ditolak, jangan update dokumen lama melainkan terbitkan baru
        // 2. Jika tidak ada penanda revisi baru dan bukan SP yang ditolak, lakukan update
        if ($isRevisiBaru || $isExistingDitolak) {
            $isUpdateExisting = false;
        } else {
            $isUpdateExisting = !empty($existing);
        }

        // PROTEKSI PERMANEN: Jika order sudah DEAL dan berlanjut ke pembayaran/pelaksanaan (Tahap 6/7/8)
        $orderHasDeal = (($order['status_penawaran'] ?? '') === 'deal') || (!empty($existing) && ($existing['status_respon_klien'] ?? '') === 'deal');
        if ($orderHasDeal) {
            $isLunas = ($order['status_keuangan'] ?? '') === 'lunas';
            $hasBayar = !empty($this->db->exec("SELECT id FROM opti_pembayaran WHERE order_id = ? LIMIT 1", [1 => $orderId]));
            $hasPo = !empty($this->db->exec("SELECT id FROM po_kegiatan WHERE order_id = ? LIMIT 1", [1 => $orderId]));
            $isPelaksanaan = in_array($order['status_pelaksanaan'] ?? '', ['sedang_berjalan', 'evaluasi_laporan', 'revisi_laporan', 'laporan_selesai']);
            if ($isLunas || $hasBayar || $hasPo || $isPelaksanaan || (($order['status_penawaran'] ?? '') === 'deal')) {
                // Seluruh penyimpanan dari formulir ini mengupdate dokumen DEAL yang ada, tanpa membatalkan DEAL
                $isUpdateExisting = true;
                $isRevisiBaru = false;
                $statusRespon = 'deal';
            }
        }

        if ($isUpdateExisting) {
            $targetId = $penawaranIdInput > 0 ? $penawaranIdInput : (int)$existing['id'];
            if (empty($fileLampiran) && !empty($existing['file_lampiran'])) {
                $fileLampiran = $existing['file_lampiran'];
            }
            // PROTEKSI STATE GUARD:
            // Jika dokumen yang di-update sebelumnya sudah DEAL atau order sudah DEAL, cegah penurunan status
            if ((($existing['status_respon_klien'] ?? '') === 'deal' || ($order['status_penawaran'] ?? '') === 'deal') && in_array($statusRespon, ['draft', 'nego'])) {
                $statusRespon = 'deal';
            }

            // Prioritaskan nomor surat dari input form (editable manual), atau fallback ke nomor existing
            $nomorSuratFinal = !empty($nomorSurat) ? $nomorSurat : (!empty($existing['nomor_surat']) ? $existing['nomor_surat'] : $nomorSurat);
            if (preg_match('/^\d+\/BBSPJIS\/MS\//i', $nomorSuratFinal)) {
                $nomorSuratFinal = 'B/' . $nomorSuratFinal;
            }

            $this->db->exec(
                "UPDATE tb_surat_penawaran SET 
                    nomor_surat = ?, perihal = ?, tanggal_surat = ?, jenis_layanan = ?, 
                    nama = ?, perusahaan = ?, alamat = ?, nominal_penawaran = ?, 
                    permintaan_melalui = ?, penjelasan = ?, file_lampiran = ?, file_lampiran_user = ?,
                    ruang_lingkup = ?, jadwal_pelaksanaan = ?, catatan_sampel = ?, 
                    jabatan_pejabat = ?, pejabat_nama = ?, hal = ?, lampiran_teks = ?,
                    status_respon_klien = ?, catatan_nego = ?, updated_at = NOW()
                 WHERE id = ?",
                array(
                    1 => $nomorSuratFinal,
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
                    12 => $fileLampiranUser ?: null,
                    13 => $ruangLingkup,
                    14 => $jadwalPelaksanaan,
                    15 => $catatanSampel,
                    16 => $jabatanPejabat,
                    17 => $pejabatNama,
                    18 => $hal,
                    19 => $lampiranTeks,
                    20 => $statusRespon,
                    21 => $catatanNego,
                    22 => $targetId
                )
            );
            $penawaranId = $targetId;
            $nomorSurat = $nomorSuratFinal;

            // Jika status berubah menjadi DEAL, pastikan seluruh penawaran revisi sebelumnya dibatalkan
            if ($statusRespon === 'deal') {
                $this->db->exec(
                    "UPDATE tb_surat_penawaran 
                     SET status_respon_klien = 'batal', updated_at = NOW() 
                     WHERE order_id = ? AND id != ?",
                    array(1 => $orderId, 2 => $targetId)
                );
            }
        } else {
            // KASUS INSERT: Terbitkan Dokumen Penawaran Baru (Revisi Negosiasi)
            $this->db->exec(
                "INSERT INTO tb_surat_penawaran 
                (customer_id, order_id, nomor_surat, perihal, nominal_penawaran, tanggal_surat, jenis_layanan, nama, perusahaan, alamat, permintaan_melalui, penjelasan, file_lampiran, file_lampiran_user, ruang_lingkup, jadwal_pelaksanaan, catatan_sampel, jabatan_pejabat, pejabat_nama, hal, lampiran_teks, status, status_respon_klien, catatan_nego, dibuat_oleh, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif', ?, ?, ?, NOW())",
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
                    14 => $fileLampiranUser ?: null,
                    15 => $ruangLingkup,
                    16 => $jadwalPelaksanaan,
                    17 => $catatanSampel,
                    18 => $jabatanPejabat,
                    19 => $pejabatNama,
                    20 => $hal,
                    21 => $lampiranTeks,
                    22 => $statusRespon,
                    23 => $catatanNego,
                    24 => $userId
                )
            );
            $penawaranId = (int)$this->db->exec("SELECT LAST_INSERT_ID() AS id")[0]['id'];

            // Otomatis ubah status penawaran lama menjadi 'batal' (Kecuali yang sudah DEAL, atau jika dokumen baru masih berupa draf)
            if ($statusRespon !== 'draft') {
                $this->db->exec(
                    "UPDATE tb_surat_penawaran 
                     SET status_respon_klien = 'batal', updated_at = NOW() 
                     WHERE order_id = ? AND id != ? AND status_respon_klien != 'deal'",
                    array(1 => $orderId, 2 => $penawaranId)
                );
            }
        }

        // Sinkronisasi status, ID surat penawaran, nominal biaya, durasi (SPM), dan status proposal ke order_layanan
        // PROTEKSI: Jangan perbarui status_penawaran di order_layanan menjadi 'draft' jika sebelumnya sudah DEAL
        $statusPenawaranOrder = $statusRespon;
        if (in_array($statusRespon, ['draft', 'nego']) && in_array($order['status_penawaran'] ?? '', ['deal'])) {
            $statusPenawaranOrder = 'deal';
        }

        $spmLayanan = trim($data['spm_layanan'] ?? '');
        $durasiNilai = (int)($data['durasi_hari'] ?? ($data['durasi_nilai'] ?? 0));
        $durasiSatuan = trim((string)($data['durasi_satuan'] ?? ''));
        if ($durasiNilai > 0 && empty($spmLayanan)) {
            $spmLayanan = "{$durasiNilai} " . (!empty($durasiSatuan) ? $durasiSatuan : (($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'Bulan' : 'Hari Kerja'));
        }

        $updateOrderSql = "UPDATE order_layanan SET status_penawaran = ?, surat_penawaran_id = ?, estimasi_biaya = ?, status_proposal_biaya = IF(status_proposal_biaya = 'disetujui', 'disetujui', 'siap_penawaran'), media_permohonan = ?";
        $orderParams = [1 => $statusPenawaranOrder, 2 => $penawaranId, 3 => $nominal, 4 => $permintaanMelalui];
        $paramIdx = 5;
        if (!empty($spmLayanan)) {
            $updateOrderSql .= ", spm_layanan = ?";
            $orderParams[$paramIdx++] = $spmLayanan;
            if ($durasiNilai > 0) {
                $satuanFinal = !empty($durasiSatuan) ? $durasiSatuan : (($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'Bulan' : 'Hari Kerja');
                $updateOrderSql .= ", spm_durasi_nilai = ?, spm_durasi_satuan = ?";
                $orderParams[$paramIdx++] = $durasiNilai;
                $orderParams[$paramIdx++] = $satuanFinal;
            }
        }
        $updateOrderSql .= " WHERE id = ?";
        $orderParams[$paramIdx] = $orderId;
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
            
            $updateSql = "UPDATE order_layanan SET 
                status_penawaran = ?, 
                status_rancop = ?,
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

    /**
     * Pastikan struktur kolom klaim surat keluar dan penawaran lengkap
     */
    public function pastikanKolomKlaimSuratKeluar(): void
    {
        try {
            // Kolom di sil2020.tb_surat_keluar
            $colsSk = $this->db->exec("SHOW COLUMNS FROM sil2020.tb_surat_keluar");
            $namesSk = array_column($colsSk, 'Field');
            $neededSk = [
                'dipilih_opti'   => "ENUM('Y','N') NOT NULL DEFAULT 'N'",
                'tgl_klaim'      => "DATETIME NULL",
                'id_user_klaim'  => "INT(11) NULL",
                'id_order_klaim' => "INT(11) NULL"
            ];
            foreach ($neededSk as $col => $def) {
                if (!in_array($col, $namesSk)) {
                    $this->db->exec("ALTER TABLE sil2020.tb_surat_keluar ADD COLUMN {$col} {$def}");
                }
            }

            // Kolom di silopti2026.tb_surat_penawaran
            $colsSp = $this->db->exec("SHOW COLUMNS FROM silopti2026.tb_surat_penawaran");
            $namesSp = array_column($colsSp, 'Field');
            if (!in_array('id_surat_keluar', $namesSp)) {
                $this->db->exec("ALTER TABLE silopti2026.tb_surat_penawaran ADD COLUMN id_surat_keluar INT(11) NULL AFTER file_penawaran_ttd");
            }
        } catch (\Exception $e) {
            error_log("pastikanKolomKlaimSuratKeluar error: " . $e->getMessage());
        }
    }

    /**
     * Ambil daftar surat penawaran dari arsip surat keluar yang belum diklaim
     * WHERE surat_penawaran = 'Y' AND dipilih_opti = 'N'
     */
    public function getDaftarSuratKeluarTersedia(?int $customerId = null, ?string $q = null): array
    {
        $this->pastikanKolomKlaimSuratKeluar();

        $sql = "SELECT sk.*,
                       c.nmcustomer,
                       c.pt_cv
                FROM sil2020.tb_surat_keluar sk
                LEFT JOIN sil2020.tb_customer c ON sk.id_customer = c.id_customer
                WHERE sk.surat_penawaran = 'Y'
                  AND (sk.dipilih_opti = 'N' OR sk.dipilih_sis_opti = 'N' OR sk.dipilih_opti IS NULL)";

        $params = [];
        $pIdx = 1;
        if (!empty($q)) {
            $sql .= " AND (sk.no_surat LIKE ? OR sk.perihal_surat LIKE ? OR c.nmcustomer LIKE ? OR sk.keterangan LIKE ?)";
            $pattern = '%' . trim($q) . '%';
            $params[$pIdx++] = $pattern;
            $params[$pIdx++] = $pattern;
            $params[$pIdx++] = $pattern;
            $params[$pIdx++] = $pattern;
        }

        if ($customerId > 0) {
            $sql .= " ORDER BY (sk.id_customer = " . (int)$customerId . ") DESC, sk.tanggal_surat DESC, sk.id_surat_keluar DESC";
        } else {
            $sql .= " ORDER BY sk.tanggal_surat DESC, sk.id_surat_keluar DESC";
        }

        $rows = $this->db->exec($sql, $params);
        $f3 = \Base::instance();
        $base = $f3->get('BASE') ?: '';

        foreach ($rows as &$r) {
            $r['nama_customer_lengkap'] = trim(($r['pt_cv'] ? $r['pt_cv'] . ' ' : '') . ($r['nmcustomer'] ?: ''));
            if (empty($r['nama_customer_lengkap'])) {
                $r['nama_customer_lengkap'] = !empty($r['jabatan_surat']) ? $r['jabatan_surat'] : ('Customer #' . $r['id_customer']);
            }
            $r['tanggal_surat_formatted'] = !empty($r['tanggal_surat']) ? date('d M Y', strtotime($r['tanggal_surat'])) : '-';
            $r['is_match_customer'] = ($customerId > 0 && (int)$r['id_customer'] === $customerId);
            $r['preview_url'] = $base . '/arsip-surat-keluar/' . $r['id_surat_keluar'] . '/preview';
        }
        unset($r);

        return $rows;
    }

    /**
     * Klaim Surat Penawaran Bertanda Tangan dari tb_surat_keluar
     */
    public function klaimSuratKeluar(int $spId, int $idSuratKeluar, int $userId): array
    {
        $this->pastikanKolomKlaimSuratKeluar();

        $sp = $this->getById($spId);
        if (!$sp) {
            return ['success' => false, 'message' => 'Data surat penawaran tidak ditemukan.'];
        }

        $skRows = $this->db->exec(
            "SELECT * FROM sil2020.tb_surat_keluar WHERE id_surat_keluar = ? LIMIT 1",
            [1 => $idSuratKeluar]
        );
        if (empty($skRows)) {
            return ['success' => false, 'message' => 'Surat keluar tidak ditemukan di arsip sekretariat.'];
        }

        $sk = $skRows[0];
        if (($sk['surat_penawaran'] ?? 'N') !== 'Y') {
            return ['success' => false, 'message' => 'Surat keluar yang dipilih bukan kategori Surat Penawaran.'];
        }

        // Cek apakah sudah diklaim order lain
        $claimedOrder = (int)($sk['id_order_klaim'] ?? 0);
        $isClaimedOther = ($claimedOrder > 0 && $claimedOrder !== (int)$sp['order_id'] && 
                          (($sk['dipilih_opti'] ?? 'N') === 'Y' || ($sk['dipilih_sis_opti'] ?? 'N') === 'Y'));
        if ($isClaimedOther) {
            return ['success' => false, 'message' => 'Surat keluar ini sudah diklaim oleh order lain.'];
        }

        // Jika sebelumnya order ini sudah mengklaim surat keluar lain, kembalikan statusnya
        $oldSkId = (int)($sp['id_surat_keluar'] ?? 0);
        if ($oldSkId > 0 && $oldSkId !== $idSuratKeluar) {
            $this->db->exec(
                "UPDATE sil2020.tb_surat_keluar SET 
                    dipilih_opti = 'N', 
                    dipilih_sis_opti = 'N', 
                    tgl_klaim = NULL, 
                    id_user_klaim = NULL, 
                    id_order_klaim = NULL,
                    id_surat_keluar_dipilih = NULL
                 WHERE id_surat_keluar = ?",
                [1 => $oldSkId]
            );
        }

        // Siapkan berkas fisik ke storage/kesanggupan_bayar agar dapat diunduh/dipratinjau
        $dirStorage = dirname(__DIR__, 2) . '/storage/kesanggupan_bayar';
        if (!is_dir($dirStorage)) {
            @mkdir($dirStorage, 0755, true);
        }

        $berkasAsli = trim($sk['nama_berkas_surat_keluar'] ?? '');
        $namaFileDisimpan = $berkasAsli;
        
        $root = dirname(__DIR__, 2);
        $kandidatPath = [
            $root . '/' . ltrim($berkasAsli, '/\\'),
            $root . '/public/' . ltrim($berkasAsli, '/\\'),
            $dirStorage . '/' . basename($berkasAsli),
            $root . '/uploads/penawaran/' . basename($berkasAsli),
        ];
        $pathDitemukan = null;
        if (!empty($berkasAsli)) {
            foreach ($kandidatPath as $kp) {
                if (is_file($kp)) {
                    $pathDitemukan = $kp;
                    break;
                }
            }
        }

        if ($pathDitemukan && is_file($pathDitemukan)) {
            $ext = strtolower(pathinfo($pathDitemukan, PATHINFO_EXTENSION)) ?: 'pdf';
            $namaFileSalinan = 'sp_ttd_' . $spId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dirStorage . '/' . $namaFileSalinan;
            if (@copy($pathDitemukan, $dest)) {
                $namaFileDisimpan = $namaFileSalinan;
            }
        } elseif (empty($berkasAsli)) {
            $namaFileDisimpan = 'sk_penawaran_ttd_' . $idSuratKeluar . '.pdf';
        }

        // 1. Update status klaim di sil2020.tb_surat_keluar
        $this->db->exec(
            "UPDATE sil2020.tb_surat_keluar SET 
                dipilih_opti = 'Y',
                dipilih_sis_opti = 'Y',
                tgl_klaim = NOW(),
                id_user_klaim = ?,
                id_order_klaim = ?,
                id_surat_keluar_dipilih = ?
             WHERE id_surat_keluar = ?",
            [
                1 => $userId,
                2 => (int)$sp['order_id'],
                3 => (int)$sp['order_id'],
                4 => $idSuratKeluar
            ]
        );

        // 2. Update di silopti2026.tb_surat_penawaran
        $this->db->exec(
            "UPDATE silopti2026.tb_surat_penawaran SET 
                file_penawaran_ttd = ?,
                id_surat_keluar = ?,
                nomor_surat = COALESCE(NULLIF(?, ''), nomor_surat)
             WHERE id = ?",
            [
                1 => $namaFileDisimpan,
                2 => $idSuratKeluar,
                3 => trim($sk['no_surat'] ?? ''),
                4 => $spId
            ]
        );

        return [
            'success'         => true,
            'message'         => 'Surat Penawaran bertanda tangan berhasil diklaim dari arsip surat keluar.',
            'no_surat'        => $sk['no_surat'],
            'id_surat_keluar' => $idSuratKeluar,
            'file_name'       => $namaFileDisimpan,
            'preview_url'     => \Base::instance()->get('BASE') . '/surat-penawaran/' . $spId . '/dokumen-deal/penawaran_ttd/preview'
        ];
    }

    /**
     * Batalkan klaim Surat Penawaran dari tb_surat_keluar
     */
    public function batalKlaimSuratKeluar(int $spId, int $userId): array
    {
        $sp = $this->getById($spId);
        if (!$sp) {
            return ['success' => false, 'message' => 'Data surat penawaran tidak ditemukan.'];
        }

        $idSuratKeluar = (int)($sp['id_surat_keluar'] ?? 0);
        if ($idSuratKeluar > 0) {
            $this->db->exec(
                "UPDATE sil2020.tb_surat_keluar SET 
                    dipilih_opti = 'N', 
                    dipilih_sis_opti = 'N', 
                    tgl_klaim = NULL, 
                    id_user_klaim = NULL, 
                    id_order_klaim = NULL,
                    id_surat_keluar_dipilih = NULL
                 WHERE id_surat_keluar = ?",
                [1 => $idSuratKeluar]
            );
        }
        if (!empty($sp['order_id'])) {
            $this->db->exec(
                "UPDATE sil2020.tb_surat_keluar SET 
                    dipilih_opti = 'N', 
                    dipilih_sis_opti = 'N', 
                    tgl_klaim = NULL, 
                    id_user_klaim = NULL, 
                    id_order_klaim = NULL,
                    id_surat_keluar_dipilih = NULL
                 WHERE id_order_klaim = ?",
                [1 => (int)$sp['order_id']]
            );
        }

        $this->db->exec(
            "UPDATE silopti2026.tb_surat_penawaran SET 
                file_penawaran_ttd = NULL,
                id_surat_keluar = NULL
             WHERE id = ?",
            [1 => $spId]
        );

        return [
            'success' => true,
            'message' => 'Klaim surat penawaran berhasil dibatalkan.'
        ];
    }
}