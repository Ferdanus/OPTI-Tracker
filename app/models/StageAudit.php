<?php

/**
 * Model StageAudit
 * Mengelola audit waktu dan petugas untuk setiap tahapan siklus Order Layanan (Tahap 1 - 8)
 * Aturan: FIRST TIME ONLY (Waktu pertama kali dikirim dan pertama kali dibaca tidak berubah walau data diedit ulang)
 */
class StageAudit {

    public static function getStageNames(): array {
        return [
            1 => 'Surat Masuk & Profil Pelanggan',
            2 => 'Formulir Permintaan Pelayanan Jasa (F.PJT-08-01/02)',
            3 => 'Kaji Kelayakan Teknis Layanan (ISO 17025)',
            4 => 'Proposal Teknis / Parameter Tarif',
            5 => 'Surat Penawaran Biaya Resmi',
            6 => 'Konfirmasi Pembayaran & Verifikasi Keuangan',
            7 => 'Penerimaan Fisik Sampel & SPM (PO)',
            8 => 'Berita Acara Serah Terima (BAST)'
        ];
    }

    /**
     * Pastikan tabel opti_stage_audit tersedia di database
     */
    public static function ensureTable(\DB\SQL $db): void {
        static $checked = false;
        if ($checked) return;
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS opti_stage_audit (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                tahap TINYINT NOT NULL,
                nama_tahap VARCHAR(100) NOT NULL,
                waktu_kirim DATETIME NULL,
                pengirim_id INT NULL,
                pengirim_nama VARCHAR(150) NULL,
                pengirim_role VARCHAR(50) NULL,
                waktu_dibaca DATETIME NULL,
                dibaca_id INT NULL,
                dibaca_nama VARCHAR(150) NULL,
                dibaca_role VARCHAR(50) NULL,
                waktu_disetujui DATETIME NULL,
                disetujui_id INT NULL,
                disetujui_nama VARCHAR(150) NULL,
                disetujui_role VARCHAR(50) NULL,
                keterangan VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_order_tahap (order_id, tahap),
                KEY idx_order_id (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Pastikan kolom waktu_disetujui ada jika tabel dibuat dari versi sebelumnya
            $cols = $db->exec("SHOW COLUMNS FROM opti_stage_audit LIKE 'waktu_disetujui'");
            if (empty($cols)) {
                $db->exec("ALTER TABLE opti_stage_audit 
                    ADD COLUMN waktu_disetujui DATETIME NULL AFTER dibaca_role,
                    ADD COLUMN disetujui_id INT NULL AFTER waktu_disetujui,
                    ADD COLUMN disetujui_nama VARCHAR(150) NULL AFTER disetujui_id,
                    ADD COLUMN disetujui_role VARCHAR(50) NULL AFTER disetujui_nama");
            }
            $checked = true;
        } catch (\Exception $e) {}
    }

    /**
     * Catat Waktu dan Pengirim saat tahapan diproses / dikirim
     * Prinsip: FIRST TIME ONLY. Jika waktu_kirim sudah ada, tidak akan ditimpa saat user edit data.
     */
    public static function recordKirim(\DB\SQL $db, int $orderId, int $tahap, string $namaTahap = '', ?int $userId = null, string $userNama = '', string $userRole = '', ?string $waktuKirim = null, string $keterangan = ''): bool {
        if ($orderId <= 0 || $tahap <= 0) return false;
        self::ensureTable($db);

        $stageNames = self::getStageNames();
        if (empty($namaTahap)) {
            $namaTahap = $stageNames[$tahap] ?? ('Tahap ' . $tahap);
        }

        $now = $waktuKirim ?: date('Y-m-d H:i:s');

        try {
            $existing = $db->exec(
                "SELECT id, waktu_kirim FROM opti_stage_audit WHERE order_id = ? AND tahap = ?",
                [1 => $orderId, 2 => $tahap]
            );

            if (!empty($existing)) {
                // FIRST TIME ONLY: Jangan overwrite jika sudah ada waktu kirim
                if (empty($existing[0]['waktu_kirim'])) {
                    $db->exec(
                        "UPDATE opti_stage_audit SET waktu_kirim = ?, pengirim_id = ?, pengirim_nama = ?, pengirim_role = ?, keterangan = ? WHERE id = ?",
                        [
                            1 => $now,
                            2 => $userId ?: null,
                            3 => $userNama,
                            4 => $userRole,
                            5 => $keterangan,
                            6 => (int)$existing[0]['id']
                        ]
                    );
                }
            } else {
                $db->exec(
                    "INSERT INTO opti_stage_audit (order_id, tahap, nama_tahap, waktu_kirim, pengirim_id, pengirim_nama, pengirim_role, keterangan, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        1 => $orderId,
                        2 => $tahap,
                        3 => $namaTahap,
                        4 => $now,
                        5 => $userId ?: null,
                        6 => $userNama,
                        7 => $userRole,
                        8 => $keterangan,
                        9 => $now
                    ]
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Catat Waktu dan Petugas saat tahapan dibuka / dibaca pertama kali
     * Prinsip: FIRST TIME ONLY. Jika waktu_dibaca sudah ada, tidak akan ditimpa.
     */
    public static function recordDibaca(\DB\SQL $db, int $orderId, int $tahap, ?int $userId = null, string $userNama = '', string $userRole = '', ?string $waktuDibaca = null): bool {
        if ($orderId <= 0 || $tahap <= 0) return false;
        self::ensureTable($db);

        $now = $waktuDibaca ?: date('Y-m-d H:i:s');

        try {
            $existing = $db->exec(
                "SELECT id, waktu_dibaca FROM opti_stage_audit WHERE order_id = ? AND tahap = ?",
                [1 => $orderId, 2 => $tahap]
            );

            if (!empty($existing)) {
                // FIRST TIME ONLY: Jangan overwrite jika sudah ada waktu dibaca
                if (empty($existing[0]['waktu_dibaca'])) {
                    $db->exec(
                        "UPDATE opti_stage_audit SET waktu_dibaca = ?, dibaca_id = ?, dibaca_nama = ?, dibaca_role = ? WHERE id = ?",
                        [
                            1 => $now,
                            2 => $userId ?: null,
                            3 => $userNama,
                            4 => $userRole,
                            5 => (int)$existing[0]['id']
                        ]
                    );
                }
            } else {
                $stageNames = self::getStageNames();
                $namaTahap = $stageNames[$tahap] ?? ('Tahap ' . $tahap);
                $db->exec(
                    "INSERT INTO opti_stage_audit (order_id, tahap, nama_tahap, waktu_dibaca, dibaca_id, dibaca_nama, dibaca_role, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        1 => $orderId,
                        2 => $tahap,
                        3 => $namaTahap,
                        4 => $now,
                        5 => $userId ?: null,
                        6 => $userNama,
                        7 => $userRole,
                        8 => $now
                    ]
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Catat Waktu dan Petugas saat tahapan disetujui / diperiksa (terutama Tahap 4 oleh Ketua Tim)
     * Prinsip: FIRST TIME ONLY.
     */
    public static function recordDisetujui(\DB\SQL $db, int $orderId, int $tahap, ?int $userId = null, string $userNama = '', string $userRole = '', ?string $waktuDisetujui = null): bool {
        if ($orderId <= 0 || $tahap <= 0) return false;
        self::ensureTable($db);

        $now = $waktuDisetujui ?: date('Y-m-d H:i:s');

        try {
            $existing = $db->exec(
                "SELECT id, waktu_disetujui FROM opti_stage_audit WHERE order_id = ? AND tahap = ?",
                [1 => $orderId, 2 => $tahap]
            );

            if (!empty($existing)) {
                if (empty($existing[0]['waktu_disetujui'])) {
                    $db->exec(
                        "UPDATE opti_stage_audit SET waktu_disetujui = ?, disetujui_id = ?, disetujui_nama = ?, disetujui_role = ? WHERE id = ?",
                        [
                            1 => $now,
                            2 => $userId ?: null,
                            3 => $userNama,
                            4 => $userRole,
                            5 => (int)$existing[0]['id']
                        ]
                    );
                }
            } else {
                $stageNames = self::getStageNames();
                $namaTahap = $stageNames[$tahap] ?? ('Tahap ' . $tahap);
                $db->exec(
                    "INSERT INTO opti_stage_audit (order_id, tahap, nama_tahap, waktu_disetujui, disetujui_id, disetujui_nama, disetujui_role, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        1 => $orderId,
                        2 => $tahap,
                        3 => $namaTahap,
                        4 => $now,
                        5 => $userId ?: null,
                        6 => $userNama,
                        7 => $userRole,
                        8 => $now
                    ]
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ambil seluruh data audit waktu per tahapan untuk order tertentu
     * Dilengkapi auto-backfill cerdas untuk order lama/data eksisting yang belum masuk tabel audit
     */
    public static function getAuditByOrder(\DB\SQL $db, int $orderId, array $order = [], array $extra = []): array {
        $result = [];
        for ($i = 1; $i <= 8; $i++) {
            $result[$i] = [
                'tahap'          => $i,
                'nama_tahap'     => self::getStageNames()[$i] ?? ('Tahap ' . $i),
                'waktu_kirim'    => null,
                'pengirim_nama'  => null,
                'pengirim_role'  => null,
                'waktu_dibaca'   => null,
                'dibaca_nama'    => null,
                'dibaca_role'    => null,
                'waktu_disetujui'=> null,
                'disetujui_nama' => null,
                'disetujui_role' => null,
                'keterangan'     => null
            ];
        }

        if ($orderId <= 0) return $result;
        self::ensureTable($db);

        try {
            $rows = $db->exec(
                "SELECT * FROM opti_stage_audit WHERE order_id = ? ORDER BY tahap ASC",
                [1 => $orderId]
            );

            foreach ($rows as $row) {
                $t = (int)$row['tahap'];
                if (isset($result[$t])) {
                    $result[$t] = array_merge($result[$t], $row);
                }
            }

            // AUTO-BACKFILL UNTUK DATA EKSISTING (Agar order lama tetap memiliki jejak audit lengkap)
            if (!empty($order)) {
                // Tahap 1: Surat Masuk Resmi
                if (empty($result[1]['waktu_kirim'])) {
                    $tglKirim1 = !empty($extra['surat_masuk']['tanggal_surat']) ? $extra['surat_masuk']['tanggal_surat'] : ($order['tanggal_masuk'] ?: $order['created_at']);
                    $pengirim1 = !empty($extra['surat_masuk']['pengirim']) ? $extra['surat_masuk']['pengirim'] : ($order['nama_perusahaan'] ?: 'Instansi Pemohon');
                    $tglBaca1  = !empty($order['tanggal_klaim']) ? $order['tanggal_klaim'] : $order['created_at'];
                    $pembaca1  = !empty($order['nama_pengklaim']) ? $order['nama_pengklaim'] : 'Tim Mitra';

                    self::recordKirim($db, $orderId, 1, self::getStageNames()[1], null, $pengirim1, 'Pemohon / Pelanggan', $tglKirim1, 'Penerimaan Permohonan Resmi');
                    self::recordDibaca($db, $orderId, 1, (int)($order['diklaim_oleh'] ?? 0), $pembaca1, 'Tim Mitra', $tglBaca1);
                    $result[1]['waktu_kirim']   = $tglKirim1;
                    $result[1]['pengirim_nama'] = $pengirim1;
                    $result[1]['pengirim_role'] = 'Pemohon / Pelanggan';
                    $result[1]['waktu_dibaca']  = $tglBaca1;
                    $result[1]['dibaca_nama']   = $pembaca1;
                    $result[1]['dibaca_role']   = 'Tim Mitra';
                }

                // Tahap 2: Formulir Permintaan Pelayanan Jasa
                if (empty($result[2]['waktu_kirim']) && ($order['status'] ?? '') !== 'permintaan_masuk') {
                    $tglKirim2 = !empty($extra['penawaran']['created_at']) ? $extra['penawaran']['created_at'] : $order['created_at'];
                    $pengirim2 = !empty($extra['penawaran']['pembuat_nama']) ? $extra['penawaran']['pembuat_nama'] : ($order['nama_pengklaim'] ?: 'Tim Mitra');
                    $divisi    = ($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'Lingkungan' : 'Selulosa';

                    self::recordKirim($db, $orderId, 2, self::getStageNames()[2], (int)($extra['penawaran']['dibuat_oleh'] ?? ($order['diklaim_oleh'] ?? 0)), $pengirim2, 'Tim Mitra', $tglKirim2, 'Diteruskan ke Ka. Tim ' . $divisi);
                    $result[2]['waktu_kirim']   = $tglKirim2;
                    $result[2]['pengirim_nama'] = $pengirim2;
                    $result[2]['pengirim_role'] = 'Tim Mitra';
                }

                // Tahap 3: Kaji Kelayakan Teknis
                if (empty($result[3]['waktu_kirim']) && !empty($extra['tinjauan'])) {
                    $tglKirim3 = !empty($extra['tinjauan']['tanggal_tinjauan']) ? $extra['tinjauan']['tanggal_tinjauan'] : $extra['tinjauan']['created_at'];
                    $pengirim3 = !empty($extra['tinjauan']['peninjau_nama']) ? $extra['tinjauan']['peninjau_nama'] : 'Ketua Tim OPTI';

                    self::recordKirim($db, $orderId, 3, self::getStageNames()[3], (int)($extra['tinjauan']['ditinjau_oleh'] ?? 0), $pengirim3, 'Ketua Tim OPTI', $tglKirim3, 'Kaji Kelayakan Ditetapkan');
                    $result[3]['waktu_kirim']   = $tglKirim3;
                    $result[3]['pengirim_nama'] = $pengirim3;
                    $result[3]['pengirim_role'] = 'Ketua Tim OPTI';
                }

                // Tahap 4: Proposal Teknis / Parameter Tarif
                if (!empty($extra['proposal']) || !empty($order['estimasi_biaya']) || in_array($order['status_proposal_biaya'] ?? '', ['menunggu_approval', 'siap_penawaran', 'disetujui'])) {
                    $tglKirim4 = !empty($extra['proposal']['diajukan_at']) ? $extra['proposal']['diajukan_at'] : (!empty($extra['proposal']['created_at']) ? $extra['proposal']['created_at'] : (!empty($order['updated_at']) ? $order['updated_at'] : $order['created_at']));
                    $pengirim4 = !empty($extra['proposal']['pic_nama']) ? $extra['proposal']['pic_nama'] : ($order['pic_proposal_nama'] ?: 'PIC Teknis');
                    $tglAcc4   = !empty($extra['proposal']['disetujui_ketua_at']) ? $extra['proposal']['disetujui_ketua_at'] : null;

                    if (empty($result[4]['waktu_dibaca'])) {
                        self::recordDibaca($db, $orderId, 4, (int)($extra['proposal']['pic_penyusun_id'] ?? ($order['pic_proposal_id'] ?? 0)), $pengirim4, 'PIC Teknis', $tglKirim4);
                        $result[4]['waktu_dibaca'] = $tglKirim4;
                        $result[4]['dibaca_nama']  = $pengirim4;
                        $result[4]['dibaca_role']  = 'PIC Teknis';
                    }

                    if (empty($result[4]['waktu_kirim'])) {
                        self::recordKirim($db, $orderId, 4, self::getStageNames()[4], (int)($extra['proposal']['pic_penyusun_id'] ?? ($order['pic_proposal_id'] ?? 0)), $pengirim4, 'PIC Teknis', $tglKirim4, 'Proposal / Tarif Disusun');
                        $result[4]['waktu_kirim']   = $tglKirim4;
                        $result[4]['pengirim_nama'] = $pengirim4;
                        $result[4]['pengirim_role'] = 'PIC Teknis';
                    }

                    if ($tglAcc4 && empty($result[4]['waktu_disetujui'])) {
                        self::recordDisetujui($db, $orderId, 4, null, 'Ketua Tim OPTI', 'Ketua Tim', $tglAcc4);
                        $result[4]['waktu_disetujui'] = $tglAcc4;
                        $result[4]['disetujui_nama']  = 'Ketua Tim OPTI';
                        $result[4]['disetujui_role']  = 'Ketua Tim';
                    }
                }

                // Tahap 5: Surat Penawaran Biaya
                if (empty($result[5]['waktu_kirim']) && !empty($extra['penawaran']) && !empty($extra['penawaran']['nomor_surat'])) {
                    $tglKirim5 = !empty($extra['penawaran']['tanggal_surat']) ? $extra['penawaran']['tanggal_surat'] : $extra['penawaran']['created_at'];
                    $pengirim5 = !empty($extra['penawaran']['pembuat_nama']) ? $extra['penawaran']['pembuat_nama'] : 'Tim Mitra';
                    $tglDeal5  = !empty($extra['penawaran']['disetujui_klien_at']) ? $extra['penawaran']['disetujui_klien_at'] : null;

                    self::recordKirim($db, $orderId, 5, self::getStageNames()[5], (int)($extra['penawaran']['dibuat_oleh'] ?? 0), $pengirim5, 'Tim Mitra', $tglKirim5, 'Surat Penawaran Resmi Terbit');
                    if ($tglDeal5) {
                        self::recordDibaca($db, $orderId, 5, null, $order['nama_perusahaan'] ?: 'Pelanggan', 'Pelanggan', $tglDeal5);
                        $result[5]['waktu_dibaca'] = $tglDeal5;
                        $result[5]['dibaca_nama']  = $order['nama_perusahaan'] ?: 'Pelanggan';
                        $result[5]['dibaca_role']  = 'Pelanggan (Respon Deal)';
                    }
                    $result[5]['waktu_kirim']   = $tglKirim5;
                    $result[5]['pengirim_nama'] = $pengirim5;
                    $result[5]['pengirim_role'] = 'Tim Mitra';
                }

                // Tahap 6: Pembayaran
                if (empty($result[6]['waktu_kirim']) && (!empty($extra['riwayat_bayar']) || ($order['status_keuangan'] ?? '') === 'lunas')) {
                    $firstBayar = !empty($extra['riwayat_bayar']) ? $extra['riwayat_bayar'][0] : [];
                    $tglKirim6  = !empty($firstBayar['tanggal_pembayaran']) ? $firstBayar['tanggal_pembayaran'] : ($firstBayar['created_at'] ?? $order['created_at']);
                    $pengirim6  = !empty($firstBayar['verifikator_nama']) ? $firstBayar['verifikator_nama'] : 'Bagian Keuangan';

                    self::recordKirim($db, $orderId, 6, self::getStageNames()[6], (int)($firstBayar['dikonfirmasi_oleh'] ?? 0), $pengirim6, 'Keuangan', $tglKirim6, 'Pembayaran Diverifikasi Lunas');
                    $result[6]['waktu_kirim']   = $tglKirim6;
                    $result[6]['pengirim_nama'] = $pengirim6;
                    $result[6]['pengirim_role'] = 'Keuangan';
                }

                // Tahap 7: Penerimaan Sampel & PO
                if (empty($result[7]['waktu_kirim']) && !empty($order['tanggal_terima_sampel'])) {
                    $tglKirim7 = $order['tanggal_terima_sampel'];
                    $pengirim7 = 'Petugas Penerima Sampel';

                    self::recordKirim($db, $orderId, 7, self::getStageNames()[7], null, $pengirim7, 'Laboratorium', $tglKirim7, 'Fisik Sampel Diterima');
                    $result[7]['waktu_kirim']   = $tglKirim7;
                    $result[7]['pengirim_nama'] = $pengirim7;
                    $result[7]['pengirim_role'] = 'Laboratorium';
                }

                // Tahap 8: BAST
                if (empty($result[8]['waktu_kirim']) && !empty($extra['bast'])) {
                    $tglKirim8 = !empty($extra['bast']['tanggal_bast']) ? $extra['bast']['tanggal_bast'] : $extra['bast']['created_at'];
                    $pengirim8 = !empty($extra['bast']['closed_by_nama']) ? $extra['bast']['closed_by_nama'] : 'Tim Mitra';

                    self::recordKirim($db, $orderId, 8, self::getStageNames()[8], null, $pengirim8, 'Tim Mitra', $tglKirim8, 'BAST Diterbitkan & Selesai');
                    $result[8]['waktu_kirim']   = $tglKirim8;
                    $result[8]['pengirim_nama'] = $pengirim8;
                    $result[8]['pengirim_role'] = 'Tim Mitra';
                }
            }
        } catch (\Exception $e) {}

        return $result;
    }
}
