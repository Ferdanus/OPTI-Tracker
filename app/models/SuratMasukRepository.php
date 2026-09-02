<?php

/**
 * Repository SuratMasukRepository
 * Mengelola interaksi dengan tabel eksternal surat_masuk (database sekretariat)
 * dan sinkronisasi ke tabel internal order_layanan (database Mini OPTI)
 */
class SuratMasukRepository {

    protected $dbMain;
    protected $dbSekretariat;
    protected $tableSekretariat;

    public function __construct(\DB\SQL $dbMain, ?\DB\SQL $dbSekretariat = null) {
        $this->dbMain = $dbMain;
        $this->dbSekretariat = $dbSekretariat;
        $f3 = \Base::instance();
        $this->tableSekretariat = $f3->get('db_sekretariat_table') ?: 'surat_masuk';
    }

    /**
     * Cek apakah koneksi database eksternal aktif
     */
    public function isConnected(): bool {
        return $this->dbSekretariat !== null;
    }

    /**
     * Ambil daftar surat masuk OPTI yang belum diklaim
     * Filter: permohonan = 'yes' AND layanan = 'opti' AND status_ambil = 0
     * Urutan: tanggal_surat ASC, id ASC (FIFO)
     */
    public function getDaftarSuratOpti(?string $filterTahun = null): array {
        if (!$this->isConnected()) {
            return array();
        }

        $table = $this->tableSekretariat;
        $sql = "SELECT * FROM `{$table}` 
                WHERE `layanan` = 'opti' 
                  AND (`status_ambil` = 'belum' OR `status_ambil` = 0 OR `status_ambil` = '' OR `status_ambil` IS NULL)
                  AND (`status_tolak` = 0 OR `status_tolak` IS NULL)";
        
        $params = array();
        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql .= " AND YEAR(`tanggal_surat`) = ?";
            $params[1] = (int)$filterTahun;
        }

        $sql .= " ORDER BY `tanggal_surat` ASC, `id` ASC";

        return $this->dbSekretariat->exec($sql, $params);
    }

    /**
     * Ambil daftar permintaan masuk (yang sudah diklaim oleh tim mitra tapi belum diproses lebih lanjut)
     */
    public function getDaftarPermintaanMasuk(?int $userId = null, ?string $filterTahun = null): array {
        $sql = "SELECT o.*, 
                       c.nmcustomer, 
                       c.pt_cv, 
                       c.alamatcustomer, 
                       COALESCE(NULLIF(c.contactperson_opti, ''), NULLIF(c.contactperson, ''), c.nama_pribadi, '-') AS pic,
                       COALESCE(NULLIF(c.nohpcontactperson_opti, ''), NULLIF(c.nohpcontactperson, ''), c.notelpcustomer, '-') AS telepon,
                       COALESCE(NULLIF(c.emailcustomer, ''), c.emailcustomer_sertifikasi, '-') AS email,
                       u.nama_user as nama_pengklaim
                FROM `order_layanan` o
                LEFT JOIN `tb_customer` c ON o.id_customer = c.id_customer
                LEFT JOIN `tb_arsipuser` u ON o.diklaim_oleh = u.id_user
                WHERE o.status = 'permintaan_masuk'";

        $params = array();
        $idx = 1;
        if ($userId !== null) {
            $sql .= " AND o.diklaim_oleh = ?";
            $params[$idx++] = $userId;
        }

        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql .= " AND YEAR(COALESCE(o.tanggal_klaim, o.tanggal_masuk, o.created_at)) = ?";
            $params[$idx++] = (int)$filterTahun;
        }

        $sql .= " ORDER BY o.tanggal_klaim DESC, o.id DESC";

        return $this->dbMain->exec($sql, $params);
    }

    /**
     * Ambil daftar riwayat surat yang sudah selesai diklaim dan telah maju ke tahapan lanjutan
     */
    public function getDaftarRiwayatSurat(?string $filterTahun = null): array {
        $sql = "SELECT o.*, 
                       c.nmcustomer, 
                       c.pt_cv, 
                       c.alamatcustomer, 
                       COALESCE(NULLIF(c.contactperson_opti, ''), NULLIF(c.contactperson, ''), c.nama_pribadi, '-') AS pic,
                       COALESCE(NULLIF(c.nohpcontactperson_opti, ''), NULLIF(c.nohpcontactperson, ''), c.notelpcustomer, '-') AS telepon,
                       COALESCE(NULLIF(c.emailcustomer, ''), c.emailcustomer_sertifikasi, '-') AS email,
                       u.nama_user as nama_pengklaim,
                       p.nomor_po,
                       p.id as po_id
                FROM `order_layanan` o
                LEFT JOIN `tb_customer` c ON o.id_customer = c.id_customer
                LEFT JOIN `tb_arsipuser` u ON o.diklaim_oleh = u.id_user
                LEFT JOIN `po` p ON o.id = p.order_id
                WHERE o.id_surat_masuk IS NOT NULL AND o.status != 'permintaan_masuk' AND o.status != 'ditolak'";

        $params = array();
        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql .= " AND YEAR(COALESCE(o.tanggal_klaim, o.tanggal_masuk, o.created_at)) = ?";
            $params[1] = (int)$filterTahun;
        }

        $sql .= " ORDER BY o.tanggal_klaim DESC, o.id DESC";

        return $this->dbMain->exec($sql, $params);
    }

    /**
     * Proses klaim surat masuk menjadi order_layanan (dengan Transaction & Row Locking)
     */
    public function klaimSurat(int $suratId, int $userId): int {
        if (!$this->isConnected()) {
            throw new \Exception("Koneksi database sekretariat tidak tersedia.");
        }

        $table = $this->tableSekretariat;
        $waktuSekarang = date('Y-m-d H:i:s');

        // 1. Mulai Transaksi di kedua DB
        $this->dbSekretariat->begin();
        $this->dbMain->begin();

        try {
            // 2. Row Locking pada DB Eksternal (SELECT ... FOR UPDATE)
            $rows = $this->dbSekretariat->exec(
                "SELECT * FROM `{$table}` WHERE `id` = ? AND `status_ambil` = 0 FOR UPDATE",
                array(1 => $suratId)
            );

            if (empty($rows)) {
                $this->dbSekretariat->rollback();
                $this->dbMain->rollback();
                throw new \Exception("Surat sudah diklaim oleh pengguna lain atau tidak ditemukan.");
            }

            $surat = $rows[0];

            // 3. Update status_ambil pada DB Eksternal (Write-back)
            $updateSql = "UPDATE `{$table}` SET `status_ambil` = 1";
            $updateParams = array();
            $paramIndex = 1;

            // Deteksi kolom dinamis
            $checkCols = $this->dbSekretariat->exec("SHOW COLUMNS FROM `{$table}` LIKE 'diambil_oleh'");
            if (!empty($checkCols)) {
                $updateSql .= ", `diambil_oleh` = ?, `tanggal_ambil` = ?";
                $updateParams[$paramIndex++] = $userId;
                $updateParams[$paramIndex++] = $waktuSekarang;
            }
            $updateSql .= " WHERE `id` = ?";
            $updateParams[$paramIndex] = $suratId;

            $this->dbSekretariat->exec($updateSql, $updateParams);

            // 4. Pencocokan Customer ke tb_customer (DB Utama) dengan Smart Matching
            $namaPengirim = trim($surat['pengirim'] ?? ($surat['nama_pengirim'] ?? ''));
            $ptCv         = trim($surat['pt_cv'] ?? '');
            $alamat       = trim($surat['alamat_pengirim'] ?? '');
            $pic          = trim($surat['pic_pengirim'] ?? ($surat['contact_person'] ?? ''));
            $telepon      = trim($surat['no_telp_pengirim'] ?? ($surat['telepon'] ?? ''));
            $email        = trim($surat['email_pengirim'] ?? ($surat['email'] ?? ''));

            // Deteksi otomatis awalan PT / CV / UD
            if (empty($ptCv)) {
                if (preg_match('/^(PT\.?|PT|PERSEROAN TERBATAS)\s+/i', $namaPengirim)) {
                    $ptCv = 'PT';
                    $cleanName = preg_replace('/^(PT\.?|PT|PERSEROAN TERBATAS)\s+/i', '', $namaPengirim);
                } elseif (preg_match('/^(CV\.?|CV|COMMANDITAIRE VENNOOTSCHAP)\s+/i', $namaPengirim)) {
                    $ptCv = 'CV';
                    $cleanName = preg_replace('/^(CV\.?|CV|COMMANDITAIRE VENNOOTSCHAP)\s+/i', '', $namaPengirim);
                } elseif (preg_match('/^(UD\.?|UD)\s+/i', $namaPengirim)) {
                    $ptCv = 'UD';
                    $cleanName = preg_replace('/^(UD\.?|UD)\s+/i', '', $namaPengirim);
                } else {
                    $ptCv = 'PT';
                    $cleanName = $namaPengirim;
                }
            } else {
                $cleanName = preg_replace('/^(PT\.?|PT|CV\.?|CV|UD\.?|UD)\s+/i', '', $namaPengirim);
            }
            $cleanName = trim($cleanName);

            // Cari customer di tb_customer
            $custRows = $this->dbMain->exec(
                "SELECT id_customer, pt_cv, alamatcustomer, contactperson, contactperson_opti, notelpcustomer, nohpcontactperson_opti, emailcustomer 
                 FROM `tb_customer` 
                 WHERE LOWER(TRIM(nmcustomer)) = LOWER(?) 
                    OR LOWER(TRIM(nmcustomer)) = LOWER(?)
                    OR (LOWER(nmcustomer) LIKE LOWER(?) AND LENGTH(?) >= 4)
                 LIMIT 1",
                array(
                    1 => $namaPengirim, 
                    2 => $cleanName,
                    3 => '%' . $cleanName . '%',
                    4 => $cleanName
                )
            );

            if (!empty($custRows)) {
                $idCustomer = (int)$custRows[0]['id_customer'];
                $existing = $custRows[0];
                
                // Update data customer dan sinkronkan PIC kontak OPTI terbaru dari surat
                $updateCustSql = "UPDATE `tb_customer` SET `id_layanan_optimalisasi` = 1";
                $updateCustParams = [];
                $pIdx = 1;
                
                if (!empty($ptCv)) {
                    $updateCustSql .= ", `pt_cv` = ?";
                    $updateCustParams[$pIdx++] = $ptCv;
                }
                if (!empty($alamat)) {
                    $updateCustSql .= ", `alamatcustomer` = ?";
                    $updateCustParams[$pIdx++] = $alamat;
                }
                if (!empty($pic)) {
                    $updateCustSql .= ", `contactperson_opti` = ?, `contactperson` = ?";
                    $updateCustParams[$pIdx++] = $pic;
                    $updateCustParams[$pIdx++] = $pic;
                }
                if (!empty($telepon)) {
                    $updateCustSql .= ", `nohpcontactperson_opti` = ?, `notelpcustomer` = ?";
                    $updateCustParams[$pIdx++] = $telepon;
                    $updateCustParams[$pIdx++] = $telepon;
                }
                if (!empty($email)) {
                    $updateCustSql .= ", `emailcustomer` = ?";
                    $updateCustParams[$pIdx++] = $email;
                }
                $updateCustSql .= " WHERE `id_customer` = ?";
                $updateCustParams[$pIdx] = $idCustomer;
                
                $this->dbMain->exec($updateCustSql, $updateCustParams);
            } else {
                // Buat customer baru otomatis dengan data lengkap
                $this->dbMain->exec(
                    "INSERT INTO `tb_customer` (
                        `nmcustomer`, `pt_cv`, `alamatcustomer`, 
                        `contactperson`, `contactperson_opti`, 
                        `notelpcustomer`, `nohpcontactperson_opti`, 
                        `emailcustomer`, `id_layanan_optimalisasi`, `showhide`, `tglinput`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'show', ?)",
                    array(
                        1  => $cleanName ?: ($namaPengirim ?: 'Instansi / Perusahaan Pemohon'),
                        2  => $ptCv ?: 'PT',
                        3  => $alamat ?: '',
                        4  => $pic ?: '',
                        5  => $pic ?: '',
                        6  => $telepon ?: '',
                        7  => $telepon ?: '',
                        8  => $email ?: '',
                        9  => $waktuSekarang
                    )
                );
                $idCustomer = (int)($this->dbMain->exec("SELECT LAST_INSERT_ID() as id")[0]['id'] ?? 0);
            }

            // 5. Generate Nomor Order Baru
            $modelOrder = new \OrderLayanan($this->dbMain);
            $nomorOrder = $modelOrder->generateNomorOrder();

            // 6. Jenis layanan OPTI belum ditentukan saat surat pertama kali diklaim (menunggu disposisi tim mitra)
            $jenisLayanan = 'belum_ditentukan';

            // 7. Insert ke order_layanan internal dengan status permintaan_masuk
            $this->dbMain->exec(
                "INSERT INTO `order_layanan` (
                    `id_customer`, `id_surat_masuk`, `nomor_order`, `tanggal_masuk`, 
                    `judul_kegiatan`, `deskripsi`, `spm_layanan`, `jenis_layanan_opti`, 
                    `lokasi_pelaksanaan`, `jumlah_pekerjaan`, `estimasi_biaya`, 
                    `status`, `diklaim_oleh`, `tanggal_klaim`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'internal', '1 paket kegiatan', 0.00,
                    'permintaan_masuk', ?, ?, ?
                )",
                array(
                    1  => $idCustomer,
                    2  => $suratId,
                    3  => $nomorOrder,
                    4  => $surat['tanggal_surat'] ?: date('Y-m-d'),
                    5  => $perihal ?: 'Permohonan Layanan OPTI',
                    6  => '',
                    7  => 'Lainnya',
                    8  => $jenisLayanan,
                    9  => $userId,
                    10 => $waktuSekarang,
                    11 => $waktuSekarang
                )
            );

            $orderId = (int)($this->dbMain->exec("SELECT LAST_INSERT_ID() as id")[0]['id'] ?? 0);

            // 8. Commit kedua transaksi
            $this->dbSekretariat->commit();
            $this->dbMain->commit();

            return $orderId;

        } catch (\Exception $e) {
            $this->dbSekretariat->rollback();
            $this->dbMain->rollback();
            throw $e;
        }
    }

    /**
     * Batalkan klaim surat masuk (dengan State Guard)
     * Hanya diizinkan jika status order masih 'permintaan_masuk'
     */
    public function batalkanKlaim(int $orderId, int $userId): bool {
        if (!$this->isConnected()) {
            throw new \Exception("Koneksi database sekretariat tidak tersedia.");
        }

        $table = $this->tableSekretariat;

        $this->dbSekretariat->begin();
        $this->dbMain->begin();

        try {
            // 1. Cek State Guard pada order_layanan internal
            $orders = $this->dbMain->exec(
                "SELECT * FROM `order_layanan` WHERE `id` = ? FOR UPDATE",
                array(1 => $orderId)
            );

            if (empty($orders)) {
                throw new \Exception("Data order tidak ditemukan.");
            }

            $order = $orders[0];

            if ($order['status'] !== 'permintaan_masuk') {
                throw new \Exception("Klaim surat tidak dapat dibatalkan karena order sudah diproses ke tahap lanjutan (Status: " . htmlspecialchars($order['status']) . ").");
            }

            $idSuratMasuk = (int)($order['id_surat_masuk'] ?? 0);

            // 2. Kembalikan status_ambil di database eksternal
            if ($idSuratMasuk > 0) {
                $updateSql = "UPDATE `{$table}` SET `status_ambil` = 0";
                $checkCols = $this->dbSekretariat->exec("SHOW COLUMNS FROM `{$table}` LIKE 'diambil_oleh'");
                if (!empty($checkCols)) {
                    $updateSql .= ", `diambil_oleh` = NULL, `tanggal_ambil` = NULL";
                }
                $updateSql .= " WHERE `id` = ?";

                $this->dbSekretariat->exec($updateSql, array(1 => $idSuratMasuk));
            }

            // 3. Hapus order draft 'permintaan_masuk' di Mini OPTI
            $this->dbMain->exec("DELETE FROM `order_layanan` WHERE `id` = ?", array(1 => $orderId));

            // 4. Commit kedua transaksi
            $this->dbSekretariat->commit();
            $this->dbMain->commit();

            return true;

        } catch (\Exception $e) {
            $this->dbSekretariat->rollback();
            $this->dbMain->rollback();
            throw $e;
        }
    }

    /**
     * Ambil data 1 surat berdasarkan ID
     */
    public function getSuratById(int $id): ?array {
        if ($this->dbSekretariat) {
            $res = $this->dbSekretariat->exec("SELECT * FROM `surat_masuk` WHERE id = ?", [$id]);
            if (!empty($res)) return $res[0];
        }
        $res = $this->dbMain->exec("SELECT * FROM `surat_masuk` WHERE id = ?", [$id]);
        return !empty($res) ? $res[0] : null;
    }

    /**
     * Ambil daftar surat masuk yang ditolak beserta alasan penolakannya
     */
    public function getDaftarSuratDitolak(?string $filterTahun = null): array {
        if (!$this->isConnected()) {
            return array();
        }

        $table = $this->tableSekretariat;
        $sql = "SELECT s.*, 
                       s.alasan_tolak, 
                       s.tanggal_tolak, 
                       s.ditolak_oleh
                FROM `{$table}` s
                WHERE s.status_tolak = 1";
        
        $params = array();
        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql .= " AND YEAR(COALESCE(s.tanggal_tolak, s.tanggal_surat)) = ?";
            $params[1] = (int)$filterTahun;
        }

        $sql .= " ORDER BY s.tanggal_tolak DESC, s.id DESC";

        $rows = $this->dbSekretariat->exec($sql, $params);
        if (empty($rows)) {
            return array();
        }

        // Ambil pemetaan nama penolak dari database utama
        $users = $this->dbMain->exec("SELECT id_user, nama_user FROM `tb_arsipuser`");
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u['id_user']] = $u['nama_user'];
        }

        foreach ($rows as &$r) {
            $uid = (int)($r['ditolak_oleh'] ?? 0);
            $r['nama_penolak'] = $userMap[$uid] ?? 'Tim Mitra';
        }

        return $rows;
    }

    /**
     * Menolak permohonan surat masuk dan menyimpan alasan penolakan
     */
    public function tolakSurat(int $suratId, int $userId, string $alasan): bool {
        $waktuSekarang = date('Y-m-d H:i:s');
        $alasan = trim($alasan);

        // 1. Update di database sekretariat
        if ($this->isConnected()) {
            $table = $this->tableSekretariat;
            $this->dbSekretariat->exec(
                "UPDATE `{$table}` 
                 SET `status_tolak` = 1, `alasan_tolak` = ?, `ditolak_oleh` = ?, `tanggal_tolak` = ?
                 WHERE `id` = ?",
                array(1 => $alasan, 2 => $userId, 3 => $waktuSekarang, 4 => $suratId)
            );
        }

        // 2. Update di database lokal jika ada tabel surat_masuk
        try {
            $this->dbMain->exec(
                "UPDATE `surat_masuk` 
                 SET `status_tolak` = 1, `alasan_tolak` = ?, `ditolak_oleh` = ?, `tanggal_tolak` = ?
                 WHERE `id` = ?",
                array(1 => $alasan, 2 => $userId, 3 => $waktuSekarang, 4 => $suratId)
            );
        } catch (\Exception $e) {}

        // 3. Update juga jika ada order_layanan yang terikat dengan surat ini
        try {
            $this->dbMain->exec(
                "UPDATE `order_layanan` 
                 SET `status` = 'ditolak', `alasan_tolak` = ?, `ditolak_oleh` = ?, `tanggal_tolak` = ?
                 WHERE `id_surat_masuk` = ?",
                array(1 => $alasan, 2 => $userId, 3 => $waktuSekarang, 4 => $suratId)
            );
        } catch (\Exception $e) {}

        return true;
    }

    /**
     * Menolak order yang sudah diklaim
     */
    public function tolakOrder(int $orderId, int $userId, string $alasan): bool {
        $waktuSekarang = date('Y-m-d H:i:s');
        $alasan = trim($alasan);

        $order = $this->dbMain->exec("SELECT id_surat_masuk FROM `order_layanan` WHERE id = ?", array(1 => $orderId));
        $idSuratMasuk = !empty($order) ? (int)$order[0]['id_surat_masuk'] : 0;

        $this->dbMain->exec(
            "UPDATE `order_layanan` 
             SET `status` = 'ditolak', `alasan_tolak` = ?, `ditolak_oleh` = ?, `tanggal_tolak` = ?
             WHERE `id` = ?",
            array(1 => $alasan, 2 => $userId, 3 => $waktuSekarang, 4 => $orderId)
        );

        if ($idSuratMasuk > 0) {
            $this->tolakSurat($idSuratMasuk, $userId, $alasan);
        }

        return true;
    }
}