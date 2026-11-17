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
            7 => 'Aktivasi Titik Awal & Jadwal SPM',
            8 => 'Petunjuk Operasional (PO)',
            9 => 'Kontrak Perjanjian Kerja Sama (PKS)',
            10 => 'Berita Acara Serah Terima (BAST)'
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
                "SELECT id, waktu_kirim, waktu_dibaca FROM opti_stage_audit WHERE order_id = ? AND tahap = ?",
                [1 => $orderId, 2 => $tahap]
            );

            if (!empty($existing)) {
                // FIRST TIME ONLY: Jangan overwrite jika sudah ada waktu kirim valid
                // (Kecuali jika sebelumnya hanya berisi tanggal dummy 00:00:00 dan sekarang tersedia timestamp jam lengkap)
                $curWaktu = $existing[0]['waktu_kirim'] ?? '';
                $curDibaca = $existing[0]['waktu_dibaca'] ?? '';
                $isMidnight = (strpos($curWaktu, '00:00:00') !== false);
                $newHasTime = (strpos($now, '00:00:00') === false);

                if (empty($curWaktu) || ($isMidnight && $newHasTime)) {
                    // Logika Konsistensi Waktu: Jika user memproses sebelum ada status dibaca,
                    // atau waktu dibaca lebih baru dari waktu kirim, sinkronkan waktu dibaca = waktu kirim
                    $shouldSyncDibaca = empty($curDibaca) || (!empty($curDibaca) && strtotime($curDibaca) > strtotime($now));
                    
                    if ($shouldSyncDibaca) {
                        $db->exec(
                            "UPDATE opti_stage_audit SET waktu_kirim = ?, pengirim_id = ?, pengirim_nama = ?, pengirim_role = ?, keterangan = ?, waktu_dibaca = ?, dibaca_id = ?, dibaca_nama = ?, dibaca_role = ? WHERE id = ?",
                            [
                                1 => $now,
                                2 => $userId ?: null,
                                3 => $userNama,
                                4 => $userRole,
                                5 => $keterangan,
                                6 => $now,
                                7 => $userId ?: null,
                                8 => $userNama,
                                9 => $userRole,
                                10 => (int)$existing[0]['id']
                            ]
                        );
                    } else {
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
                }
            } else {
                // Saat insert kirim baru: Otomatis user yang memproses sudah membaca tahapan ini
                $db->exec(
                    "INSERT INTO opti_stage_audit (order_id, tahap, nama_tahap, waktu_kirim, pengirim_id, pengirim_nama, pengirim_role, keterangan, waktu_dibaca, dibaca_id, dibaca_nama, dibaca_role, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        1 => $orderId,
                        2 => $tahap,
                        3 => $namaTahap,
                        4 => $now,
                        5 => $userId ?: null,
                        6 => $userNama,
                        7 => $userRole,
                        8 => $keterangan,
                        9 => $now,
                        10 => $userId ?: null,
                        11 => $userNama,
                        12 => $userRole,
                        13 => $now
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
        for ($i = 1; $i <= 9; $i++) {
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

            // AUTO-BACKFILL UNTUK DATA EKSISTING (Agar order lama tetap memiliki jejak waktu kirim)
            if (!empty($order)) {
                // Tahap 1: Surat Masuk Resmi
                if (empty($result[1]['waktu_kirim']) || strpos($result[1]['waktu_kirim'], '00:00:00') !== false) {
                    $tglKirim1 = !empty($order['tanggal_klaim']) 
                        ? $order['tanggal_klaim'] 
                        : (!empty($order['created_at']) 
                            ? $order['created_at'] 
                            : date('Y-m-d H:i:s'));
                    $pengirim1 = !empty($order['nama_pengklaim']) ? $order['nama_pengklaim'] : 'Tim Mitra';

                    self::recordKirim($db, $orderId, 1, self::getStageNames()[1], (int)($order['diklaim_oleh'] ?? 0), $pengirim1, 'Tim Mitra', $tglKirim1, 'Registrasi & Klaim Permohonan Masuk');
                    $result[1]['waktu_kirim']   = $tglKirim1;
                    $result[1]['pengirim_nama'] = $pengirim1;
                    $result[1]['pengirim_role'] = 'Tim Mitra';
                }

                // Tahap 2: Formulir Permintaan Pelayanan Jasa
                $isTahap2Submitted = !in_array($order['status'] ?? '', ['permintaan_masuk', 'draft_disimpan', 'draft']);
                if ($isTahap2Submitted) {
                    $divisi = ($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'Ka. Tim Lingkungan' : 'Ka. Tim Selulosa';
                    if (empty($result[2]['waktu_kirim'])) {
                        $tglKirim2 = !empty($extra['penawaran']['created_at']) ? $extra['penawaran']['created_at'] : $order['created_at'];
                        $pengirim2 = !empty($extra['penawaran']['pembuat_nama']) ? $extra['penawaran']['pembuat_nama'] : ($order['nama_pengklaim'] ?: 'Tim Mitra');

                        self::recordKirim($db, $orderId, 2, self::getStageNames()[2], (int)($extra['penawaran']['dibuat_oleh'] ?? ($order['diklaim_oleh'] ?? 0)), $pengirim2, 'Tim Mitra', $tglKirim2, 'Diteruskan ke ' . $divisi);
                        $result[2]['waktu_kirim']   = $tglKirim2;
                        $result[2]['pengirim_nama'] = $pengirim2;
                        $result[2]['pengirim_role'] = 'Tim Mitra';
                    }
                }

                // Tahap 3: Kaji Kelayakan Teknis
                if (empty($result[3]['waktu_kirim']) && !empty($extra['tinjauan']) && empty($extra['tinjauan']['is_draft'])) {
                    $tglKirim3 = !empty($extra['tinjauan']['created_at']) ? $extra['tinjauan']['created_at'] : (!empty($extra['tinjauan']['tanggal_tinjauan']) ? ($extra['tinjauan']['tanggal_tinjauan'] . ' ' . date('H:i:s')) : $order['created_at']);
                    $pengirim3 = !empty($extra['tinjauan']['peninjau_nama']) ? $extra['tinjauan']['peninjau_nama'] : 'Ketua Tim OPTI';

                    self::recordKirim($db, $orderId, 3, self::getStageNames()[3], (int)($extra['tinjauan']['ditinjau_oleh'] ?? 0), $pengirim3, 'Ketua Tim OPTI', $tglKirim3, 'Kaji Kelayakan Ditetapkan');
                    $result[3]['waktu_kirim']   = $tglKirim3;
                    $result[3]['pengirim_nama'] = $pengirim3;
                    $result[3]['pengirim_role'] = 'Ketua Tim OPTI';
                }

                // Tahap 4: Proposal Teknis / Parameter Tarif (Hanya jika Kaji Kelayakan Tahap 3 sudah disetujui & data proposal/kalkulasi ada & bukan draft)
                $tinjauanLayak = (!empty($extra['tinjauan']) && empty($extra['tinjauan']['is_draft']) && ($extra['tinjauan']['keputusan'] ?? '') === 'dapat_dilaksanakan') || (($order['status_tinjauan'] ?? '') === 'layak');
                $hasDataTahap4 = !empty($extra['proposal']['diajukan_at']) || !empty($extra['penawaran']) || in_array($order['status_proposal_biaya'] ?? '', ['menunggu_approval', 'siap_penawaran', 'disetujui']);

                if ($tinjauanLayak && $hasDataTahap4) {
                    $tglKirim4 = !empty($extra['proposal']['diajukan_at']) ? $extra['proposal']['diajukan_at'] : (!empty($extra['proposal']['created_at']) ? $extra['proposal']['created_at'] : (!empty($order['updated_at']) ? $order['updated_at'] : $order['created_at']));
                    
                    // Prioritas pengirim: PIC proposal -> user pengisi dari log aktivitas -> pengklaim order -> fallback dinamis
                    $pengirim4 = !empty($extra['proposal']['pic_nama']) ? $extra['proposal']['pic_nama'] : ($order['pic_proposal_nama'] ?? '');
                    $pengirimRole4 = 'PIC Teknis';
                    $pengirimId4   = (int)($extra['proposal']['pic_penyusun_id'] ?? ($order['pic_proposal_id'] ?? 0));

                    if (empty($pengirim4)) {
                        $logUser = $db->exec(
                            "SELECT user_id, user_nama, user_role FROM opti_activity_log 
                             WHERE order_id = ? AND modul IN ('kalkulasi_lingkungan', 'proposal') AND aksi IN ('simpan_kalkulasi', 'ajukan_kalkulasi', 'simpan_proposal', 'ajukan_ke_ketua', 'melihat_tahap_4')
                             ORDER BY id DESC LIMIT 1",
                            [1 => $orderId]
                        );
                        if (!empty($logUser) && !empty($logUser[0]['user_nama'])) {
                            $pengirim4 = $logUser[0]['user_nama'];
                            $pengirimId4 = (int)$logUser[0]['user_id'];
                            $pengirimRole4 = !empty($logUser[0]['user_role']) ? ucfirst($logUser[0]['user_role']) : 'Petugas';
                        } elseif (!empty($order['nama_pengklaim'])) {
                            $pengirim4 = $order['nama_pengklaim'];
                            $pengirimId4 = (int)($order['diklaim_oleh'] ?? 0);
                            $pengirimRole4 = 'Tim Mitra';
                        } else {
                            $pengirim4 = 'Petugas Layanan';
                            $pengirimRole4 = 'Petugas';
                        }
                    }

                    $tglAcc4   = !empty($extra['proposal']['disetujui_ketua_at']) ? $extra['proposal']['disetujui_ketua_at'] : null;

                    if (empty($result[4]['waktu_kirim']) || $result[4]['pengirim_nama'] === 'PIC Teknis') {
                        $existingAudit4 = $db->exec("SELECT id FROM opti_stage_audit WHERE order_id = ? AND tahap = 4", [1 => $orderId]);
                        if (!empty($existingAudit4)) {
                            $db->exec("UPDATE opti_stage_audit SET pengirim_id = ?, pengirim_nama = ?, pengirim_role = ? WHERE id = ?", [
                                1 => $pengirimId4 ?: null,
                                2 => $pengirim4,
                                3 => $pengirimRole4,
                                4 => (int)$existingAudit4[0]['id']
                            ]);
                        } else {
                            self::recordKirim($db, $orderId, 4, self::getStageNames()[4], $pengirimId4, $pengirim4, $pengirimRole4, $tglKirim4, 'Proposal / Tarif Disusun');
                        }
                        $result[4]['waktu_kirim']   = $tglKirim4;
                        $result[4]['pengirim_nama'] = $pengirim4;
                        $result[4]['pengirim_role'] = $pengirimRole4;
                    }

                    if ($tglAcc4 && empty($result[4]['waktu_disetujui'])) {
                        self::recordDisetujui($db, $orderId, 4, null, 'Ketua Tim OPTI', 'Ketua Tim', $tglAcc4);
                        $result[4]['waktu_disetujui'] = $tglAcc4;
                        $result[4]['disetujui_nama']  = 'Ketua Tim OPTI';
                        $result[4]['disetujui_role']  = 'Ketua Tim';
                    }
                }

                // Tahap 5: Surat Penawaran Biaya
                if ((empty($result[5]['waktu_kirim']) || strpos($result[5]['waktu_kirim'], '00:00:00') !== false) && !empty($extra['penawaran']) && !empty($extra['penawaran']['nomor_surat'])) {
                    $tglKirim5 = !empty($extra['penawaran']['created_at']) ? $extra['penawaran']['created_at'] : (!empty($extra['penawaran']['tanggal_surat']) ? ($extra['penawaran']['tanggal_surat'] . ' ' . date('H:i:s')) : date('Y-m-d H:i:s'));
                    $pengirim5 = !empty($extra['penawaran']['pembuat_nama']) ? $extra['penawaran']['pembuat_nama'] : 'Tim Mitra';

                    self::recordKirim($db, $orderId, 5, self::getStageNames()[5], (int)($extra['penawaran']['dibuat_oleh'] ?? 0), $pengirim5, 'Tim Mitra', $tglKirim5, 'Surat Penawaran Resmi Terbit');
                    $result[5]['waktu_kirim']   = $tglKirim5;
                    $result[5]['pengirim_nama'] = $pengirim5;
                    $result[5]['pengirim_role'] = 'Tim Mitra';
                }

                $isPenawaranDeal = (!empty($extra['penawaran']) && ($extra['penawaran']['status_respon_klien'] ?? '') === 'deal') || (($order['status_penawaran'] ?? '') === 'deal') || (($order['status'] ?? '') === 'penawaran_deal');
                if (empty($result[5]['waktu_disetujui']) && $isPenawaranDeal) {
                    $tglDeal5 = !empty($extra['penawaran']['disetujui_klien_at']) ? $extra['penawaran']['disetujui_klien_at'] : (!empty($extra['penawaran']['updated_at']) ? $extra['penawaran']['updated_at'] : (!empty($result[5]['waktu_kirim']) ? $result[5]['waktu_kirim'] : date('Y-m-d H:i:s')));
                    self::recordDisetujui($db, $orderId, 5, null, 'Pelanggan / Klien', 'Pelanggan', $tglDeal5);
                    $result[5]['waktu_disetujui'] = $tglDeal5;
                    $result[5]['disetujui_nama']  = 'Pelanggan / Klien';
                    $result[5]['disetujui_role']  = 'Pelanggan';
                }

                // Tahap 6: Pembayaran
                if (empty($result[6]['waktu_kirim']) && (!empty($extra['riwayat_bayar']) || ($order['status_keuangan'] ?? '') === 'lunas')) {
                    $firstBayar = !empty($extra['riwayat_bayar']) ? $extra['riwayat_bayar'][0] : [];
                    $tglKirim6  = !empty($firstBayar['created_at']) ? $firstBayar['created_at'] : (!empty($firstBayar['tanggal_pembayaran']) ? ($firstBayar['tanggal_pembayaran'] . ' ' . date('H:i:s')) : $order['created_at']);
                    $pengirim6  = !empty($firstBayar['verifikator_nama']) ? $firstBayar['verifikator_nama'] : 'Bagian Keuangan';

                    self::recordKirim($db, $orderId, 6, self::getStageNames()[6], (int)($firstBayar['dikonfirmasi_oleh'] ?? 0), $pengirim6, 'Keuangan', $tglKirim6, 'Pembayaran Diverifikasi Lunas');
                    $result[6]['waktu_kirim']   = $tglKirim6;
                    $result[6]['pengirim_nama'] = $pengirim6;
                    $result[6]['pengirim_role'] = 'Keuangan';
                }

                // Tahap 7: Aktivasi SPM (Titik Awal Pengerjaan)
                if ((empty($result[7]['waktu_kirim']) || strpos($result[7]['waktu_kirim'], '00:00:00') !== false) && (!empty($order['tanggal_spm_mulai']) || !empty($order['tanggal_terima_sampel']))) {
                    $tglKirim7 = null;
                    $pengirim7 = 'Ketua Tim OPTI';

                    // 1. Ambil waktu riil dari log aktivitas terima_sampel
                    $logSpm = $db->exec("SELECT created_at, user_nama FROM opti_activity_log WHERE order_id = ? AND modul = 'sampel' AND aksi = 'terima_sampel' ORDER BY id DESC LIMIT 1", [$orderId]);
                    if (!empty($logSpm[0]['created_at'])) {
                        $tglKirim7 = $logSpm[0]['created_at'];
                        if (!empty($logSpm[0]['user_nama'])) $pengirim7 = $logSpm[0]['user_nama'];
                    }

                    // 2. Ambil dari updated_at / created_at tabel opti_stage_audit
                    if (empty($tglKirim7)) {
                        $audRow7 = $db->exec("SELECT created_at, updated_at FROM opti_stage_audit WHERE order_id = ? AND tahap = 7", [$orderId]);
                        if (!empty($audRow7[0]['updated_at']) && strpos($audRow7[0]['updated_at'], '00:00:00') === false) {
                            $tglKirim7 = $audRow7[0]['updated_at'];
                        } elseif (!empty($audRow7[0]['created_at']) && strpos($audRow7[0]['created_at'], '00:00:00') === false) {
                            $tglKirim7 = $audRow7[0]['created_at'];
                        }
                    }

                    // 3. Fallback: jika belum ada jam, jangan sampai mundur dari waktu disposisi pembayaran
                    if (empty($tglKirim7)) {
                        $rawDate = $order['tanggal_spm_mulai'] ?: $order['tanggal_terima_sampel'];
                        $waktuDisp7 = $result[6]['waktu_kirim'] ?? null;
                        if (!empty($waktuDisp7) && substr($waktuDisp7, 0, 10) === substr($rawDate, 0, 10)) {
                            $tglKirim7 = date('Y-m-d H:i:s', strtotime($waktuDisp7) + 900);
                        } else {
                            $tglKirim7 = substr($rawDate, 0, 10) . ' 10:00:00';
                        }
                    }

                    self::recordKirim($db, $orderId, 7, self::getStageNames()[7], null, $pengirim7, 'Ketua Tim OPTI', $tglKirim7, 'Aktivasi Titik Awal SPM');
                    $result[7]['waktu_kirim']   = $tglKirim7;
                    $result[7]['pengirim_nama'] = $pengirim7;
                    $result[7]['pengirim_role'] = 'Ketua Tim OPTI';
                }

                // Tahap 8: Petunjuk Operasional (PO)
                if (empty($result[8]['waktu_kirim']) && !empty($order['po_id'])) {
                    $tglKirim8 = !empty($order['tanggal_po']) ? $order['tanggal_po'] : (!empty($order['updated_at']) ? $order['updated_at'] : date('Y-m-d H:i:s'));
                    $pengirim8 = 'Ketua Tim OPTI';

                    self::recordKirim($db, $orderId, 8, self::getStageNames()[8], null, $pengirim8, 'Ketua Tim', $tglKirim8, 'PO Resmi Diterbitkan');
                    $result[8]['waktu_kirim']   = $tglKirim8;
                    $result[8]['pengirim_nama'] = $pengirim8;
                    $result[8]['pengirim_role'] = 'Ketua Tim';
                }

                // Tahap 9: BAST
                if (empty($result[9]['waktu_kirim']) && !empty($extra['bast'])) {
                    $tglKirim9 = !empty($extra['bast']['created_at']) ? $extra['bast']['created_at'] : (!empty($extra['bast']['tanggal_bast']) ? ($extra['bast']['tanggal_bast'] . ' ' . date('H:i:s')) : date('Y-m-d H:i:s'));
                    $pengirim9 = !empty($extra['bast']['closed_by_nama']) ? $extra['bast']['closed_by_nama'] : 'Tim Mitra';

                    self::recordKirim($db, $orderId, 9, self::getStageNames()[9], null, $pengirim9, 'Tim Mitra', $tglKirim9, 'BAST Diterbitkan & Selesai');
                    $result[9]['waktu_kirim']   = $tglKirim9;
                    $result[9]['pengirim_nama'] = $pengirim9;
                    $result[9]['pengirim_role'] = 'Tim Mitra';
                }
            }
        } catch (\Exception $e) {}

        return $result;
    }

    /**
     * Dapatkan waktu saat tahapan/tugas didisposisikan atau diterima ke antrean user terkait
     * (Sebagai titik awal lama pengerjaan sebelum dikerjakan/diproses oleh user tersebut)
     */
    public static function getWaktuDisposisiTahap(int $tahap, array $auditStages, array $order, ?array $suratMasuk = null): ?string {
        switch ($tahap) {
            case 1:
                // Tahap 1: Surat Masuk diterima ke antrean sistem (Titik awal / Disposisi permohonan)
                $rawTgl = !empty($suratMasuk['tanggal_surat']) 
                    ? $suratMasuk['tanggal_surat'] 
                    : (!empty($order['tanggal_masuk']) ? $order['tanggal_masuk'] : null);

                if (!empty($rawTgl)) {
                    // Jika berformat tanggal murni (YYYY-MM-DD) atau berakhiran 00:00:00
                    if (strlen($rawTgl) === 10 || strpos($rawTgl, '00:00:00') !== false) {
                        $dateOnly = substr($rawTgl, 0, 10);
                        $waktuProsesRef = !empty($order['tanggal_klaim']) ? $order['tanggal_klaim'] : ($order['created_at'] ?? null);

                        // Standar awal jam kerja / operasional layanan: 08:00:00 WIB
                        $jamDefault = ' 08:00:00';

                        // Jika klaim dilakukan pada hari yang sama dan sebelum jam 08:00:00,
                        // gunakan waktu sebelum klaim agar urutan kronologis tetap valid
                        if (!empty($waktuProsesRef) && substr($waktuProsesRef, 0, 10) === $dateOnly) {
                            $tsProses = strtotime($waktuProsesRef);
                            $tsDefault = strtotime($dateOnly . $jamDefault);
                            if ($tsProses <= $tsDefault) {
                                return date('Y-m-d H:i:s', max(strtotime($dateOnly . ' 07:00:00'), $tsProses - 900));
                            }
                        }
                        return $dateOnly . $jamDefault;
                    }
                    return $rawTgl;
                }
                return $order['created_at'] ?? ($auditStages[1]['waktu_kirim'] ?? null);

            case 2:
                // Tahap 2: Formulir Pelayanan diterima oleh Tim Mitra (saat klaim surat atau order dibuat)
                if (!empty($order['tanggal_klaim'])) {
                    return $order['tanggal_klaim'];
                }
                if (!empty($order['created_at'])) {
                    return $order['created_at'];
                }
                return $auditStages[1]['waktu_kirim'] ?? null;

            case 3:
                // Tahap 3: Kaji Kelayakan didisposisikan ke Ketua Tim (saat Tahap 2 Form Pelayanan selesai)
                if (!empty($order['disposisi_katim_at'])) {
                    return $order['disposisi_katim_at'];
                }
                if (!empty($auditStages[2]['waktu_kirim'])) {
                    return $auditStages[2]['waktu_kirim'];
                }
                return null;

            case 4:
                // Tahap 4: Parameter Tarif / Proposal didisposisikan ke PIC / Ketua Tim (saat Tahap 3 Kaji Kelayakan disetujui)
                if (!empty($auditStages[3]['waktu_kirim'])) {
                    return $auditStages[3]['waktu_kirim'];
                }
                return null;

            case 5:
                // Tahap 5: Surat Penawaran didisposisikan ke Tim Mitra (saat Tahap 4 Tarif / Proposal disetujui/siap)
                if (!empty($auditStages[4]['waktu_disetujui'])) {
                    return $auditStages[4]['waktu_disetujui'];
                }
                if (!empty($auditStages[4]['waktu_kirim'])) {
                    return $auditStages[4]['waktu_kirim'];
                }
                return null;

            case 6:
                // Tahap 6: Pembayaran didisposisikan ke Tim Keuangan (saat penawaran deal dengan klien)
                if (!empty($auditStages[5]['waktu_disetujui'])) {
                    return $auditStages[5]['waktu_disetujui'];
                }
                if (!empty($order['penawaran_deal_at'])) {
                    return $order['penawaran_deal_at'];
                }
                return null;

            case 7:
                // Tahap 7: Aktivasi SPM & Sampel didisposisikan ke Lab/Teknis (saat pembayaran diverifikasi lunas/kesanggupan bayar)
                if (!empty($auditStages[6]['waktu_kirim'])) {
                    return $auditStages[6]['waktu_kirim'];
                }
                if (!empty($order['tanggal_bayar'])) {
                    return $order['tanggal_bayar'];
                }
                return null;

            case 8:
                // Tahap 8: Petunjuk Operasional (PO) didisposisikan ke Ketua Pelaksana (saat titik awal SPM diaktifkan)
                if (!empty($order['ketua_pelaksana_at'])) {
                    return $order['ketua_pelaksana_at'];
                }
                if (!empty($auditStages[7]['waktu_kirim'])) {
                    return $auditStages[7]['waktu_kirim'];
                }
                if (!empty($order['tanggal_spm_mulai'])) {
                    return $order['tanggal_spm_mulai'];
                }
                return null;

            case 9:
                // Tahap 9: Kontrak (PKS) didisposisikan ke Sub Tim Humas setelah PO Kegiatan aktif / ditandatangani
                if (!empty($order['po_ttd_at'])) {
                    return $order['po_ttd_at'];
                }
                if (!empty($auditStages[8]['waktu_kirim'])) {
                    return $auditStages[8]['waktu_kirim'];
                }
                if (!empty($order['tanggal_po'])) {
                    return $order['tanggal_po'];
                }
                return null;

            case 10:
                // Tahap 10: BAST didisposisikan ke Tim Mitra setelah Kontrak PKS selesai dibuat / pengujian tuntas
                if (!empty($order['kontrak_dibuat_at'])) {
                    return $order['kontrak_dibuat_at'];
                }
                if (!empty($extra['po_kegiatan']['kontrak_dibuat_at'])) {
                    return $extra['po_kegiatan']['kontrak_dibuat_at'];
                }
                if (!empty($auditStages[9]['waktu_kirim'])) {
                    return $auditStages[9]['waktu_kirim'];
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Hitung durasi selisih antara waktu dibaca/disposisi dan waktu diproses/dikirim
     * Menghasilkan format: "2 jam 15 menit 30 detik", "45 menit 12 detik", "1 hari 3 jam", dsb.
     */
    public static function formatDurasiPengerjaan(?string $waktuMulai, ?string $waktuSelesai): ?string {
        if (empty($waktuMulai) || empty($waktuSelesai)) return null;
        
        $tsStart = strtotime($waktuMulai);
        $tsEnd   = strtotime($waktuSelesai);
        
        if ($tsStart === false || $tsEnd === false || $tsEnd < $tsStart) return null;
        
        $diff = $tsEnd - $tsStart;
        if ($diff < 1) return '< 1 menit';
        
        $days    = floor($diff / 86400);
        $hours   = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);
        $seconds = $diff % 60;
        
        $parts = [];
        if ($days > 0) $parts[] = $days . ' hari';
        if ($hours > 0) $parts[] = $hours . ' jam';
        if ($minutes > 0) $parts[] = $minutes . ' menit';
        if ($seconds > 0 && $days == 0 && $hours == 0) $parts[] = $seconds . ' detik'; // tampilkan detik jika kurang dari 1 jam
        
        return !empty($parts) ? implode(' ', $parts) : '< 1 menit';
    }

    /**
     * Dapatkan nomor tahapan (1 - 9) berdasarkan data aktivitas / log
     */
    public static function getTahapFromActivity(array $act): int {
        $modul = $act['modul'] ?? '';
        $aksi  = $act['aksi'] ?? '';
        $desc  = strtolower($act['deskripsi'] ?? '');

        if ($modul === 'surat_masuk') return 1;
        if ($modul === 'form_pelayanan') return 2;
        if ($modul === 'tinjauan' || $modul === 'kaji_kelayakan') return 3;
        if ($modul === 'proposal' || $modul === 'kalkulasi' || $modul === 'kalkulasi_lingkungan' || strpos($modul, 'kalkulasi') !== false) return 4;
        if ($modul === 'penawaran') return 5;
        if ($modul === 'pembayaran' || $modul === 'keuangan') return 6;
        if ($modul === 'spm' || $modul === 'sampel') return 7;
        if ($modul === 'po' || $modul === 'po_kegiatan') return 8;
        if ($modul === 'kontrak' || $modul === 'pks') return 9;
        if ($modul === 'bast') return 10;

        // Berdasarkan aksi
        if (strpos($aksi, 'tahap_1') !== false || in_array($aksi, ['input_manual', 'klaim_surat', 'tambah_order', 'batal_klaim'])) return 1;
        if (strpos($aksi, 'tahap_2') !== false || in_array($aksi, ['simpan_form_pelayanan', 'disposisi_layanan'])) return 2;
        if (strpos($aksi, 'tahap_3') !== false || in_array($aksi, ['simpan_tinjauan', 'tinjauan_kelayakan', 'draf_tinjauan', 'tolak_tinjauan'])) return 3;
        if (strpos($aksi, 'tahap_4') !== false || in_array($aksi, ['simpan_proposal', 'ajukan_approval', 'ajukan_ke_ketua', 'ajukan_kalkulasi', 'approve_proposal', 'setujui_proposal', 'simpan_kalkulasi', 'simpan_draft_kalkulasi', 'revisi_proposal'])) return 4;
        if (strpos($aksi, 'tahap_5') !== false || in_array($aksi, ['buat_penawaran', 'kirim_penawaran', 'simpan_draft_penawaran', 'respon_penawaran', 'deal_penawaran', 'nego_penawaran', 'batal_penawaran', 'disposisi_keuangan'])) return 5;
        if (strpos($aksi, 'tahap_6') !== false || in_array($aksi, ['verifikasi_pembayaran', 'upload_kesanggupan_bayar', 'hapus_pembayaran'])) return 6;
        if (strpos($aksi, 'tahap_7') !== false || in_array($aksi, ['terima_sampel', 'aktivasi_spm', 'simpan_draft_spm'])) return 7;
        if (strpos($aksi, 'tahap_8') !== false || in_array($aksi, ['terbitkan_po', 'verifikasi_sop', 'kirim_petunjuk', 'tunjuk_ketupel', 'kirim_revisi', 'review_setuju', 'verif_humas', 'verif_mitra', 'verif_ppk'])) return 8;
        if (strpos($aksi, 'tahap_9') !== false || strpos($aksi, 'kontrak') !== false || strpos($aksi, 'pks') !== false) return 9;
        if (strpos($aksi, 'tahap_10') !== false || in_array($aksi, ['terbitkan_bast', 'perbarui_bast', 'tutup_order', 'upload_laporan'])) return 10;

        // Berdasarkan kata kunci di deskripsi
        if (strpos($desc, 'bast') !== false || strpos($desc, 'closing order') !== false || strpos($desc, 'selesaikan order') !== false) return 10;
        if (strpos($desc, 'kontrak') !== false || strpos($desc, 'pks') !== false || strpos($desc, 'perjanjian kerja sama') !== false) return 9;
        if (strpos($desc, 'petunjuk operasional') !== false || strpos($desc, 'po #') !== false || strpos($desc, 'ketua pelaksana') !== false || strpos($desc, 'sop') !== false) return 8;
        if (strpos($desc, 'sampel') !== false || strpos($desc, 'spm') !== false) return 7;
        if (strpos($desc, 'pembayaran') !== false || strpos($desc, 'keuangan') !== false || strpos($desc, 'kesanggupan bayar') !== false) return 6;
        if (strpos($desc, 'penawaran') !== false) return 5;
        if (strpos($desc, 'proposal') !== false || strpos($desc, 'tarif') !== false || strpos($desc, 'parameter') !== false || strpos($desc, 'rab') !== false || strpos($desc, 'kalkulasi') !== false) return 4;
        if (strpos($desc, 'kaji kelayakan') !== false || strpos($desc, 'tinjauan') !== false) return 3;
        if (strpos($desc, 'formulir pelayanan') !== false || strpos($desc, 'disposisi layanan') !== false) return 2;
        if (strpos($desc, 'surat') !== false || strpos($desc, 'klaim') !== false || strpos($desc, 'permohonan') !== false) return 1;

        return 1;
    }

    /**
     * Susun data rekapitulasi khusus alur tahapan riwayat (Tahap 1 - 9) beserta tonggak proses
     */
    public static function getAlurTahapanRiwayat(array $order, array $auditStages, ?array $suratMasuk = null, array $stepper = [], array $extra = []): array {
        $isLingkungan = ($order['jenis_layanan_opti'] ?? '') === 'lingkungan';

        $stageNames = [
            1 => 'Surat Masuk & Profil Pemohon',
            2 => 'Formulir Permintaan Pelayanan Jasa',
            3 => 'Kaji Kelayakan Teknis (ISO 17025)',
            4 => $isLingkungan ? 'Parameter Uji & Rincian Tarif' : 'Proposal Teknis & RAB Selulosa',
            5 => 'Surat Penawaran Biaya Resmi',
            6 => 'Konfirmasi Pembayaran & Verifikasi Keuangan',
            7 => 'Aktivasi Titik Awal & Jadwal SPM',
            8 => 'Petunjuk Operasional (PO Kegiatan)',
            9 => 'Kontrak Perjanjian Kerja Sama (PKS)',
            10 => 'Berita Acara Serah Terima (BAST)'
        ];

        // Tentukan penanggung jawab tahapan berdasarkan nama divisi layanan / unit kerja sesuai sistem data pengguna
        $divisiMitra = 'Mitra Industri';
        $divisiOpti = $isLingkungan ? 'OPTI Lingkungan' : 'OPTI Selulosa';
        $divisiKeuangan = 'Keuangan';
        $divisiPengujian = 'Pengujian';
        $divisiHumas = 'Sub Tim Humas';

        $stagePic = [
            1 => $divisiMitra,
            2 => $divisiMitra,
            3 => $divisiOpti,
            4 => $divisiOpti,
            5 => $divisiMitra,
            6 => $divisiKeuangan,
            7 => $divisiOpti,
            8 => $divisiOpti,
            9 => $divisiHumas,
            10 => $divisiMitra
        ];

        $now = date('Y-m-d H:i:s');
        $alur = [];

        for ($s = 1; $s <= 10; $s++) {
            if ($s === 9) {
                // Di stepper sistem, Kontrak PKS disimpan pada index 10
                $state = $stepper[10]['state'] ?? 'waiting';
            } elseif ($s === 10) {
                // Di stepper sistem, BAST disimpan pada index 9
                $state = $stepper[9]['state'] ?? 'waiting';
            } else {
                $state = $stepper[$s]['state'] ?? 'waiting';
            }

            // Waktu disposisi hanya valid jika tahap tersebut sudah dimulai (tidak waiting)
            $waktuDisposisi = ($state !== 'waiting') ? self::getWaktuDisposisiTahap($s, $auditStages, $order, $suratMasuk) : null;

            // Waktu selesai diproses
            $waktuProses = null;
            if ($s === 1) {
                $waktuProses = !empty($order['tanggal_klaim']) 
                    ? $order['tanggal_klaim'] 
                    : (!empty($order['created_at']) 
                        ? $order['created_at'] 
                        : ($auditStages[1]['waktu_kirim'] ?? null));
            } elseif ($s === 2) {
                $waktuProses = $auditStages[2]['waktu_kirim'] ?? null;
            } elseif ($s === 3) {
                $waktuProses = $auditStages[3]['waktu_kirim'] ?? null;
            } elseif ($s === 4) {
                $waktuProses = !empty($auditStages[4]['waktu_disetujui']) ? $auditStages[4]['waktu_disetujui'] : ($auditStages[4]['waktu_kirim'] ?? null);
            } elseif ($s === 5) {
                $waktuProses = !empty($auditStages[5]['waktu_disetujui']) ? $auditStages[5]['waktu_disetujui'] : ($auditStages[5]['waktu_kirim'] ?? null);
            } elseif ($s === 6) {
                $waktuProses = $auditStages[6]['waktu_kirim'] ?? null;
            } elseif ($s === 7) {
                $waktuProses = $auditStages[7]['waktu_kirim'] ?? null;
                // Jika waktu proses tidak ada, atau berakhiran 00:00:00, atau mendahului waktu disposisi
                if (empty($waktuProses) || strpos($waktuProses, '00:00:00') !== false || (!empty($waktuDisposisi) && strtotime($waktuProses) < strtotime($waktuDisposisi))) {
                    if (!empty($auditStages[7]['updated_at']) && strpos($auditStages[7]['updated_at'], '00:00:00') === false) {
                        $waktuProses = $auditStages[7]['updated_at'];
                    } elseif (!empty($auditStages[7]['created_at']) && strpos($auditStages[7]['created_at'], '00:00:00') === false) {
                        $waktuProses = $auditStages[7]['created_at'];
                    }
                }
            } elseif ($s === 8) {
                $waktuProses = $auditStages[8]['waktu_kirim'] ?? null;
            } elseif ($s === 9) {
                $waktuProses = !empty($extra['po_kegiatan']['kontrak_dibuat_at']) ? $extra['po_kegiatan']['kontrak_dibuat_at'] : (!empty($order['kontrak_dibuat_at']) ? $order['kontrak_dibuat_at'] : null);
            } elseif ($s === 10) {
                $waktuProses = !empty($extra['bast']['created_at']) ? $extra['bast']['created_at'] : (!empty($extra['bast']['tanggal_bast']) ? ($extra['bast']['tanggal_bast'] . ' ' . date('H:i:s')) : ($auditStages[9]['waktu_kirim'] ?? null));
            }

            // Tentukan durasi pengerjaan
            $durasiPengerjaan = null;
            $durasiBerjalan = null;

            if ($state === 'done' || (!empty($waktuProses) && $state !== 'waiting')) {
                $durasiPengerjaan = self::formatDurasiPengerjaan($waktuDisposisi, $waktuProses);
                if (empty($durasiPengerjaan)) {
                    $durasiPengerjaan = '< 1 menit';
                }
            } elseif ($state === 'current' && !empty($waktuDisposisi)) {
                $durasiBerjalan = self::formatDurasiPengerjaan($waktuDisposisi, $now);
            }

            // Petugas yang memproses (Selalu prioritaskan personil internal sistem)
            $petugasProses = null;
            if ($s === 1) {
                // Tahap 1: Surat masuk diklaim / diterima oleh personil Tim Mitra
                $petugasProses = !empty($order['nama_pengklaim']) ? $order['nama_pengklaim'] : $divisiMitra;
            } elseif ($s === 5) {
                // Tahap 5: Pembuat surat penawaran biaya resmi
                $petugasProses = !empty($auditStages[5]['pengirim_nama']) ? $auditStages[5]['pengirim_nama'] : $divisiMitra;
            } elseif ($s === 9) {
                // Tahap 9: Pembuat kontrak PKS (Humas)
                $petugasProses = !empty($extra['po_kegiatan']['kontrak_dibuat_oleh_nama']) ? $extra['po_kegiatan']['kontrak_dibuat_oleh_nama'] : $divisiHumas;
            } elseif ($s === 10) {
                // Tahap 10: Penerbit BAST (Tim Mitra)
                $petugasProses = !empty($auditStages[9]['pengirim_nama']) ? $auditStages[9]['pengirim_nama'] : $divisiMitra;
            } else {
                $petugasProses = !empty($auditStages[$s]['disetujui_nama']) 
                    ? $auditStages[$s]['disetujui_nama'] 
                    : (!empty($auditStages[$s]['pengirim_nama']) ? $auditStages[$s]['pengirim_nama'] : null);
            }

            // Jika petugas masih kosong atau merujuk ke pelanggan/klien, gunakan divisi penanggung jawab internal
            if (empty($petugasProses) || in_array(strtolower($petugasProses), ['pelanggan', 'pelanggan / klien', 'pemohon', 'instansi pemohon']) || (!empty($order['nama_perusahaan']) && strtolower($petugasProses) === strtolower($order['nama_perusahaan']))) {
                $petugasProses = $stagePic[$s] ?? $divisiMitra;
            }

            // Bangun tonggak alur tahapan proses (Workflow Milestones)
            $milestones = self::buildStageMilestones($s, $order, $auditStages, $state, $waktuDisposisi, $waktuProses, $durasiPengerjaan, $stagePic[$s], $isLingkungan, $extra);

            $alur[$s] = [
                'tahap'              => $s,
                'nama'               => $stageNames[$s],
                'penanggung_jawab'   => $stagePic[$s],
                'status'             => $state,
                'waktu_disposisi'    => $waktuDisposisi,
                'waktu_proses'       => $waktuProses,
                'durasi_pengerjaan'  => $durasiPengerjaan,
                'durasi_berjalan'    => $durasiBerjalan,
                'petugas_proses'     => $petugasProses,
                'tonggak_alur'       => $milestones,
                'total_tonggak'      => count($milestones)
            ];
        }

        return $alur;
    }

    /**
     * Alias backward compatibility
     */
    public static function getAlurTahapanData(array $order, array $auditStages, array $activityLogs = [], ?array $suratMasuk = null, array $stepper = [], array $extra = []): array {
        return self::getAlurTahapanRiwayat($order, $auditStages, $suratMasuk, $stepper, $extra);
    }

    /**
     * Rancang tonggak alur / proses resmi untuk setiap tahapan (Workflow Milestones)
     */
    public static function buildStageMilestones(int $tahap, array $order, array $auditStages, string $state, ?string $waktuDisposisi, ?string $waktuProses, ?string $durasiPengerjaan, string $picDefault, bool $isLingkungan, array $extra = []): array {
        $milestones = [];
        $isDone = ($state === 'done');
        $isCurrent = ($state === 'current');
        $isStarted = ($isDone || $isCurrent);
        $statusDisposisi = $isStarted ? 'done' : 'waiting';
        $waktuDisposisiVal = $isStarted ? $waktuDisposisi : null;

        switch ($tahap) {
            case 1:
                $tglSurat = !empty($waktuDisposisi) ? $waktuDisposisi : (!empty($extra['surat_masuk']['tanggal_surat']) ? $extra['surat_masuk']['tanggal_surat'] : ($order['tanggal_masuk'] ?: null));
                $pengirim = !empty($extra['surat_masuk']['pengirim']) ? $extra['surat_masuk']['pengirim'] : ($order['nama_perusahaan'] ?: 'Instansi Pemohon');
                $klaimOleh = !empty($order['nama_pengklaim']) ? $order['nama_pengklaim'] : (!empty($auditStages[1]['pengirim_nama']) && $auditStages[1]['pengirim_nama'] !== $pengirim ? $auditStages[1]['pengirim_nama'] : 'Tim Mitra');
                $waktuSelesaiTahap1 = $waktuProses ?: (!empty($order['tanggal_klaim']) ? $order['tanggal_klaim'] : ($order['created_at'] ?? null));

                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Permohonan / Surat Masuk Diterima',
                    'deskripsi'  => 'Permohonan layanan resmi masuk dan tercatat di antrean sistem',
                    'pelaku'     => $pengirim,
                    'role'       => 'Pemohon / Pelanggan',
                    'waktu'      => $tglSurat,
                    'status'     => 'done'
                ];
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Registrasi & Klaim Order Layanan',
                    'deskripsi'  => 'Surat permohonan diverifikasi dan diklaim menjadi order layanan aktif',
                    'pelaku'     => $klaimOleh,
                    'role'       => 'Tim Mitra',
                    'waktu'      => $waktuSelesaiTahap1,
                    'status'     => 'done'
                ];
                break;

            case 2:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Disposisi Diterima Tim Mitra',
                    'deskripsi'  => 'Permohonan masuk ke antrean pengisian Formulir Pelayanan Jasa',
                    'pelaku'     => 'Tim Mitra',
                    'role'       => 'Tim Mitra',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Penyelesaian Formulir & Disposisi ke Ka. Tim',
                    'deskripsi'  => 'Formulir Permintaan Pelayanan Jasa diselesaikan dan didisposisikan ke Ketua Tim ' . ($isLingkungan ? 'Lingkungan' : 'Selulosa'),
                    'pelaku'     => $auditStages[2]['pengirim_nama'] ?: 'Tim Mitra',
                    'role'       => 'Tim Mitra',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                break;

            case 3:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Disposisi Diterima Ketua Tim OPTI',
                    'deskripsi'  => 'Berkas formulir diterima untuk kaji ulang kelayakan teknis laboratorium',
                    'pelaku'     => $isLingkungan ? 'Ketua Tim OPTI Lingkungan' : 'Ketua Tim OPTI Selulosa',
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Evaluasi 4 Pilar Kesiapan Teknis',
                    'deskripsi'  => 'Pemeriksaan kesiapan Personil (SDM), Peralatan, Bahan Uji, dan Metode Standar ISO 17025',
                    'pelaku'     => $auditStages[3]['pengirim_nama'] ?: 'Ketua Tim OPTI',
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $statusKelayakan = !empty($order['status_tinjauan']) ? ($order['status_tinjauan'] === 'layak' ? 'Dapat Dilaksanakan (Layak)' : 'Tidak Dapat Dilaksanakan') : 'Dapat Dilaksanakan (Layak)';
                $milestones[] = [
                    'urutan'     => 3,
                    'judul'      => 'Penetapan Keputusan Kelayakan Teknis',
                    'deskripsi'  => 'Keputusan kaji kelayakan resmi ditetapkan: ' . $statusKelayakan,
                    'pelaku'     => $auditStages[3]['pengirim_nama'] ?: 'Ketua Tim OPTI',
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                break;

            case 4:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Disposisi Masuk ke Tim Teknis',
                    'deskripsi'  => 'Tugas penyusunan tarif & parameter diterima setelah kaji kelayakan dinyatakan layak',
                    'pelaku'     => $picDefault,
                    'role'       => 'Tim Teknis',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $hasPic = $isStarted && !empty($order['pic_proposal_nama']);
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => $isLingkungan ? 'Penunjukan PIC Pengujian' : 'Penunjukan PIC Proposal',
                    'deskripsi'  => $hasPic ? ('Ditugaskan kepada: ' . $order['pic_proposal_nama']) : 'Dikerjakan langsung oleh Ketua Tim OPTI',
                    'pelaku'     => $isLingkungan ? 'Ketua Tim OPTI Lingkungan' : 'Ketua Tim OPTI Selulosa',
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => ($isDone || $hasPic) ? (!empty($order['pic_proposal_at']) ? $order['pic_proposal_at'] : (!empty($auditStages[4]['waktu_dibaca']) ? $auditStages[4]['waktu_dibaca'] : null)) : null,
                    'status'     => ($isDone || $hasPic || (!empty($auditStages[4]['waktu_kirim']) && $isStarted)) ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $milestones[] = [
                    'urutan'     => 3,
                    'judul'      => $isLingkungan ? 'Penyusunan Parameter & Kalkulasi Tarif' : 'Penyusunan Proposal Teknis & RAB',
                    'deskripsi'  => $isLingkungan ? 'Pemilihan parameter uji, metode SNI/standar, dan perhitungan estimasi tarif PNBP' : 'Penyusunan metodologi riset dan Rencana Anggaran Biaya (RAB)',
                    'pelaku'     => $auditStages[4]['pengirim_nama'] ?: $picDefault,
                    'role'       => 'Penyusun Teknis',
                    'waktu'      => ($isDone || (!empty($auditStages[4]['waktu_kirim']) && $isStarted)) ? $auditStages[4]['waktu_kirim'] : null,
                    'status'     => (!empty($auditStages[4]['waktu_kirim']) && $isStarted) || $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $milestones[] = [
                    'urutan'     => 4,
                    'judul'      => 'Persetujuan (Approval) Ketua Tim',
                    'deskripsi'  => 'Rincian tarif & parameter disetujui resmi, siap diterbitkan surat penawaran harga',
                    'pelaku'     => $auditStages[4]['disetujui_nama'] ?: ($auditStages[4]['pengirim_nama'] ?: 'Ketua Tim OPTI'),
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => $isDone ? ($auditStages[4]['waktu_disetujui'] ?: $waktuProses) : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                break;

            case 5:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Disposisi Masuk ke Tim Mitra',
                    'deskripsi'  => 'Rincian tarif dan parameter resmi diterima untuk penerbitan surat penawaran',
                    'pelaku'     => 'Tim Mitra',
                    'role'       => 'Tim Mitra',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $nomorSp = !empty($order['nomor_penawaran']) ? ('No. ' . $order['nomor_penawaran']) : '';
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Penerbitan Surat Penawaran Resmi',
                    'deskripsi'  => 'Surat Penawaran Biaya resmi diterbitkan ' . $nomorSp . ' dan dikirim ke pelanggan',
                    'pelaku'     => $auditStages[5]['pengirim_nama'] ?: 'Tim Mitra',
                    'role'       => 'Tim Mitra',
                    'waktu'      => ($isDone || (!empty($auditStages[5]['waktu_kirim']) && $isStarted)) ? $auditStages[5]['waktu_kirim'] : null,
                    'status'     => (!empty($auditStages[5]['waktu_kirim']) && $isStarted) || $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $isDeal = $isStarted && ((!empty($order['status_penawaran']) && $order['status_penawaran'] === 'deal') || !empty($auditStages[5]['waktu_disetujui']) || $isDone);
                $milestones[] = [
                    'urutan'     => 3,
                    'judul'      => 'Persetujuan Penawaran (DEAL)',
                    'deskripsi'  => 'Pelanggan menyepakati penawaran harga dan mengunggah berkas persetujuan resmi',
                    'pelaku'     => $order['nama_perusahaan'] ?: 'Pelanggan',
                    'role'       => 'Pelanggan',
                    'waktu'      => $isDeal ? ($auditStages[5]['waktu_disetujui'] ?: (!empty($order['penawaran_deal_at']) ? $order['penawaran_deal_at'] : null)) : null,
                    'status'     => $isDeal ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $milestones[] = [
                    'urutan'     => 4,
                    'judul'      => 'Disposisi ke Tim Keuangan',
                    'deskripsi'  => 'Order penawaran DEAL didisposisikan ke Bagian Keuangan untuk proses verifikasi pembayaran',
                    'pelaku'     => $auditStages[5]['pengirim_nama'] ?: 'Tim Mitra',
                    'role'       => 'Tim Mitra',
                    'waktu'      => $isDone ? ($auditStages[5]['waktu_disetujui'] ?: $waktuProses) : null,
                    'status'     => $isDone ? 'done' : ($isCurrent && $isDeal ? 'current' : 'waiting')
                ];
                break;

            case 6:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Disposisi Masuk ke Tim Keuangan',
                    'deskripsi'  => 'Order penawaran DEAL masuk ke antrean verifikasi pembayaran Tim Keuangan',
                    'pelaku'     => 'Tim Keuangan',
                    'role'       => 'Tim Keuangan',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $hasBayar = $isStarted && (!empty($order['tanggal_bayar']) || !empty($extra['riwayat_bayar']) || $isDone);
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Penerimaan Bukti Bayar / Surat Kesanggupan',
                    'deskripsi'  => 'Bukti pembayaran atau Surat Kesanggupan Bayar dari pelanggan diterima',
                    'pelaku'     => $order['nama_perusahaan'] ?: 'Pelanggan',
                    'role'       => 'Pelanggan',
                    'waktu'      => $hasBayar ? (!empty($order['tanggal_bayar']) ? $order['tanggal_bayar'] : (!empty($extra['riwayat_bayar'][0]['tanggal_pembayaran']) ? $extra['riwayat_bayar'][0]['tanggal_pembayaran'] : null)) : null,
                    'status'     => $hasBayar ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $milestones[] = [
                    'urutan'     => 3,
                    'judul'      => 'Verifikasi Keuangan & Penerbitan Kwitansi',
                    'deskripsi'  => 'Pembayaran diverifikasi sah dan diteruskan ke laboratorium untuk pelaksanaan pengujian',
                    'pelaku'     => $auditStages[6]['pengirim_nama'] ?: 'Tim Keuangan',
                    'role'       => 'Tim Keuangan',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                break;

            case 7:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Disposisi Masuk ke Laboratorium',
                    'deskripsi'  => 'Administrasi keuangan selesai, laboratorium bersiap menerima sampel uji',
                    'pelaku'     => 'Laboratorium & Tim Teknis',
                    'role'       => 'Laboratorium',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $hasSampel = $isStarted && (!empty($order['tanggal_terima_sampel']) || $isDone);
                $waktuSampelVal = null;
                if ($hasSampel && !empty($order['tanggal_terima_sampel'])) {
                    $rawSampel = $order['tanggal_terima_sampel'];
                    if (strlen($rawSampel) === 10 || strpos($rawSampel, '00:00:00') !== false) {
                        $dtSampel = substr($rawSampel, 0, 10);
                        if (!empty($waktuDisposisiVal) && substr($waktuDisposisiVal, 0, 10) === $dtSampel) {
                            $waktuSampelVal = $waktuDisposisiVal;
                        } else {
                            $waktuSampelVal = $dtSampel . ' 09:00:00';
                        }
                    } else {
                        $waktuSampelVal = $rawSampel;
                    }
                } elseif ($hasSampel && empty($order['tanggal_terima_sampel'])) {
                    $waktuSampelVal = $waktuDisposisiVal;
                }

                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Penerimaan Fisik Sampel Uji',
                    'deskripsi'  => 'Sampel uji fisik diterima di laboratorium, diperiksa kondisi dan jumlahnya',
                    'pelaku'     => 'Petugas Laboratorium',
                    'role'       => 'Laboratorium',
                    'waktu'      => $waktuSampelVal,
                    'status'     => $hasSampel ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $spmTarget = !empty($order['target_spm_selesai']) ? (' (Target Selesai: ' . date('d M Y', strtotime($order['target_spm_selesai'])) . ')') : '';
                $pelakuAktivasi = !empty($auditStages[7]['pengirim_nama']) ? $auditStages[7]['pengirim_nama'] : 'Ketua Tim OPTI';
                $milestones[] = [
                    'urutan'     => 3,
                    'judul'      => 'Aktivasi Titik Awal SPM Layanan',
                    'deskripsi'  => 'Titik awal Standar Pelayanan Minimal (SPM) resmi diaktifkan' . $spmTarget,
                    'pelaku'     => $pelakuAktivasi,
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                break;

            case 8:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Aktivasi SPM Diterima Tim Pelaksana',
                    'deskripsi'  => 'Pengerjaan dimulai, persiapan pembentukan tim dan penerbitan PO Kegiatan',
                    'pelaku'     => 'Ketua Tim OPTI',
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $hasKetupel = $isStarted && (!empty($order['ketua_pelaksana_nama']) || $isDone);
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Penunjukan Ketua Pelaksana (PIC PO)',
                    'deskripsi'  => $hasKetupel ? ('Ketua Pelaksana: ' . $order['ketua_pelaksana_nama']) : 'Penugasan Ketua Tim Pelaksana Kegiatan',
                    'pelaku'     => 'Ketua Tim OPTI',
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => $hasKetupel ? (!empty($order['ketua_pelaksana_at']) ? $order['ketua_pelaksana_at'] : null) : null,
                    'status'     => $hasKetupel ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $nomorPo = !empty($order['nomor_po']) ? ('No. ' . $order['nomor_po']) : '';
                $milestones[] = [
                    'urutan'     => 3,
                    'judul'      => 'Penerbitan PO Kegiatan & Pelaksanaan SOP',
                    'deskripsi'  => 'Petunjuk Operasional resmi diterbitkan ' . $nomorPo . ' dan seluruh SOP pengujian dilaksanakan',
                    'pelaku'     => $auditStages[8]['pengirim_nama'] ?: 'Ketua Tim OPTI',
                    'role'       => 'Ketua Tim OPTI',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                break;

            case 9:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Disposisi Masuk ke Sub Tim Humas',
                    'deskripsi'  => 'PO Kegiatan bertanda tangan diterima untuk penyusunan Kontrak Perjanjian Kerja Sama (PKS)',
                    'pelaku'     => 'Sub Tim Humas',
                    'role'       => 'Sub Tim Humas',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Penyusunan Klausul & Legalitas Kontrak',
                    'deskripsi'  => 'Penyusunan draf kontrak PKS, kesepakatan hak dan kewajiban para pihak',
                    'pelaku'     => 'Sub Tim Humas',
                    'role'       => 'Sub Tim Humas',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $nomorKontrak = !empty($extra['po_kegiatan']['kontrak_nomor']) ? ('No. ' . $extra['po_kegiatan']['kontrak_nomor']) : (!empty($order['kontrak_nomor']) ? ('No. ' . $order['kontrak_nomor']) : '');
                $milestones[] = [
                    'urutan'     => 3,
                    'judul'      => 'Penerbitan Kontrak PKS Resmi',
                    'deskripsi'  => 'Kontrak Perjanjian Kerja Sama resmi diterbitkan ' . $nomorKontrak . ' dan disahkan oleh para pihak',
                    'pelaku'     => !empty($extra['po_kegiatan']['kontrak_dibuat_oleh_nama']) ? $extra['po_kegiatan']['kontrak_dibuat_oleh_nama'] : 'Sub Tim Humas',
                    'role'       => 'Sub Tim Humas',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                break;

            case 10:
                $milestones[] = [
                    'urutan'     => 1,
                    'judul'      => 'Pengujian Selesai & Laporan Rampung',
                    'deskripsi'  => 'Seluruh pengujian teknis tuntas dan laporan hasil pengujian/riset selesai disusun',
                    'pelaku'     => 'Tim Pelaksana Teknis',
                    'role'       => 'Tim Pelaksana',
                    'waktu'      => $waktuDisposisiVal,
                    'status'     => $statusDisposisi
                ];
                $nomorBast = !empty($order['nomor_bast']) ? ('No. ' . $order['nomor_bast']) : '';
                $milestones[] = [
                    'urutan'     => 2,
                    'judul'      => 'Penerbitan BAST & Serah Terima Hasil',
                    'deskripsi'  => 'Berita Acara Serah Terima (BAST) resmi diterbitkan ' . $nomorBast . ' dan diserahkan ke pelanggan',
                    'pelaku'     => $auditStages[9]['pengirim_nama'] ?: 'Mitra Industri',
                    'role'       => 'Tim Mitra',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                $milestones[] = [
                    'urutan'     => 3,
                    'judul'      => 'Penyelesaian Siklus Order (Closing Order)',
                    'deskripsi'  => 'Seluruh kewajiban teknis & administratif selesai, order resmi ditutup',
                    'pelaku'     => 'Sistem & Tim Mitra',
                    'role'       => 'Sistem',
                    'waktu'      => $isDone ? $waktuProses : null,
                    'status'     => $isDone ? 'done' : ($isCurrent ? 'current' : 'waiting')
                ];
                break;
        }

        return $milestones;
    }

}
