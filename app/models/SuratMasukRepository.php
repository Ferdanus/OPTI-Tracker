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
    protected $dbMainName = 'silopti2026';
    protected $dbSekretariatName = 'sil2020';

    public function __construct(\DB\SQL $dbMain, ?\DB\SQL $dbSekretariat = null) {
        $this->dbMain = $dbMain;
        $this->dbSekretariat = $dbSekretariat ?: $dbMain;
        $f3 = \Base::instance();
        $this->tableSekretariat = $f3->get('db_sekretariat_table') ?: 'tb_arsipsurat';

        try {
            $r1 = $this->dbMain->exec("SELECT DATABASE() as d");
            if (!empty($r1[0]['d'])) $this->dbMainName = $r1[0]['d'];
        } catch (\Exception $e) {}

        try {
            if ($this->dbSekretariat) {
                $r2 = $this->dbSekretariat->exec("SELECT DATABASE() as d");
                if (!empty($r2[0]['d'])) $this->dbSekretariatName = $r2[0]['d'];
            }
        } catch (\Exception $e) {}
    }

    /**
     * Cek apakah koneksi database eksternal aktif
     */
    public function isConnected(): bool {
        return $this->dbSekretariat !== null;
    }

    private function beginTransaction(): void {
        $this->dbSekretariat->begin();
        if ($this->dbMain !== $this->dbSekretariat) {
            $this->dbMain->begin();
        }
    }

    private function commitTransaction(): void {
        $this->dbSekretariat->commit();
        if ($this->dbMain !== $this->dbSekretariat) {
            $this->dbMain->commit();
        }
    }

    private function rollbackTransaction(): void {
        try {
            $this->dbSekretariat->rollback();
        } catch (\Exception $e) {}
        if ($this->dbMain !== $this->dbSekretariat) {
            try {
                $this->dbMain->rollback();
            } catch (\Exception $e) {}
        }
    }

    /**
     * Cek apakah menggunakan tabel tb_arsipsurat (standar SIL)
     */
    public function isArsipSurat(): bool {
        return strpos($this->tableSekretariat, 'tb_arsipsurat') !== false;
    }

    /**
     * Ambil daftar surat masuk OPTI yang belum diklaim
     * Urutan: tanggal_surat ASC, id ASC (FIFO)
     */
    public function getDaftarSuratOpti(?string $filterTahun = null): array {
        if (!$this->isConnected()) {
            return array();
        }

        if ($this->isArsipSurat()) {
            $sql = "SELECT a.*, 
                           a.id_arsip AS id,
                           COALESCE(c.nmcustomer, 'Instansi / Perusahaan') AS pengirim,
                           c.pt_cv,
                           c.kodex_perusahaan,
                           c.id_customer,
                           COALESCE(c.alamatcustomer, '-') AS alamat_pengirim,
                           COALESCE(NULLIF(a.kontak_person, ''), NULLIF(c.contactperson_opti, ''), NULLIF(c.contactperson, ''), '-') AS pic_pengirim,
                           COALESCE(NULLIF(a.hp_kontakperson, ''), NULLIF(c.nohpcontactperson_opti, ''), NULLIF(c.notelpcustomer, ''), '-') AS no_telp_pengirim,
                           COALESCE(NULLIF(a.email_kontakperson, ''), NULLIF(c.emailcustomer, ''), '-') AS email_pengirim,
                           a.nama_berkas AS file_path,
                           a.nama_layanan,
                           CASE 
                               WHEN a.nama_layanan = 'OPTI_lingkungan' THEN 'OPTI Lingkungan'
                               WHEN a.nama_layanan = 'OPTI_Selulosa' THEN 'OPTI Selulosa'
                               ELSE 'Belum Ditentukan'
                           END AS nama_layanan_label
                    FROM `{$this->tableSekretariat}` a
                    INNER JOIN `{$this->dbSekretariatName}`.tb_customer c ON a.id_customer = c.id_customer
                    WHERE a.surat_permohonan = 'Y'
                      AND (a.id_pemasaran_order IS NULL OR a.id_pemasaran_order = 0)
                      AND (a.status_disposisi_surat IS NULL OR a.status_disposisi_surat != 'ditolak')
                      AND c.kodex_perusahaan IS NOT NULL
                      AND c.kodex_perusahaan != ''
                      AND c.id_layanan_optimalisasi = 1
                      AND a.id_arsip NOT IN (SELECT id_surat_masuk FROM `{$this->dbMainName}`.order_layanan WHERE id_surat_masuk IS NOT NULL AND status != 'ditolak')";

            $params = array();
            if (!empty($filterTahun) && $filterTahun !== 'all') {
                $sql .= " AND YEAR(a.tanggal_surat) = ?";
                $params[1] = (int)$filterTahun;
            }

            $sql .= " ORDER BY a.tanggal_surat ASC, a.id_arsip ASC";

            $rows = $this->dbSekretariat->exec($sql, $params);
            foreach ($rows as &$r) {
                $r['nama_pengirim_bersih'] = \Customer::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['pengirim'] ?? '');
            }
            unset($r);
            return $rows;
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
     * Hitung jumlah surat masuk OPTI yang belum diklaim secara cepat (COUNT query)
     */
    public function countSuratOptiBelumKlaim(?string $filterTahun = null): int {
        if (!$this->isConnected()) {
            return 0;
        }

        if ($this->isArsipSurat()) {
            $sql = "SELECT COUNT(*) as c FROM `{$this->tableSekretariat}` a
                    INNER JOIN `{$this->dbSekretariatName}`.tb_customer c ON a.id_customer = c.id_customer
                    WHERE a.surat_permohonan = 'Y'
                      AND (a.id_pemasaran_order IS NULL OR a.id_pemasaran_order = 0)
                      AND (a.status_disposisi_surat IS NULL OR a.status_disposisi_surat != 'ditolak')
                      AND c.kodex_perusahaan IS NOT NULL
                      AND c.kodex_perusahaan != ''
                      AND c.id_layanan_optimalisasi = 1
                      AND a.id_arsip NOT IN (SELECT id_surat_masuk FROM `{$this->dbMainName}`.order_layanan WHERE id_surat_masuk IS NOT NULL AND status != 'ditolak')";

            $params = array();
            if (!empty($filterTahun) && $filterTahun !== 'all') {
                $sql .= " AND YEAR(a.tanggal_surat) = ?";
                $params[1] = (int)$filterTahun;
            }

            $res = $this->dbSekretariat->exec($sql, $params);
            return (int)($res[0]['c'] ?? 0);
        }

        $table = $this->tableSekretariat;
        $sql = "SELECT COUNT(*) as c FROM `{$table}` 
                WHERE `layanan` = 'opti' 
                  AND (`status_ambil` = 'belum' OR `status_ambil` = 0 OR `status_ambil` = '' OR `status_ambil` IS NULL)
                  AND (`status_tolak` = 0 OR `status_tolak` IS NULL)";
        
        $params = array();
        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql .= " AND YEAR(`tanggal_surat`) = ?";
            $params[1] = (int)$filterTahun;
        }

        $res = $this->dbSekretariat->exec($sql, $params);
        return (int)($res[0]['c'] ?? 0);
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

        $rows = $this->dbMain->exec($sql, $params);
        foreach ($rows as &$r) {
            $r['nama_pengirim_bersih'] = \Customer::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nmcustomer'] ?? '');
        }
        unset($r);
        return $rows;
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

        $rows = $this->dbMain->exec($sql, $params);
        foreach ($rows as &$r) {
            $r['nama_pengirim_bersih'] = \Customer::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nmcustomer'] ?? '');
        }
        unset($r);
        return $rows;
    }

    /**
     * Proses klaim surat masuk menjadi order_layanan (dengan Transaction & Row Locking)
     * Tim Mitra memilih instansi pelanggan terdaftar dari tb_customer
     */
    public function klaimSurat(int $suratId, int $userId, ?int $selectedCustomerId = null, ?string $pilihanLayanan = null): int {
        if (!$this->isConnected()) {
            throw new \Exception("Koneksi database sekretariat tidak tersedia.");
        }

        $table = $this->tableSekretariat;
        $waktuSekarang = date('Y-m-d H:i:s');

        // 1. Mulai Transaksi
        $this->beginTransaction();

        try {
            if ($this->isArsipSurat()) {
                $rows = $this->dbSekretariat->exec(
                    "SELECT a.*, a.id_arsip as id FROM `{$this->tableSekretariat}` a 
                     WHERE a.id_arsip = ? AND (a.id_pemasaran_order IS NULL OR a.id_pemasaran_order = 0) FOR UPDATE",
                    [1 => $suratId]
                );

                if (empty($rows)) {
                    throw new \Exception("Surat sudah diklaim oleh pengguna lain atau tidak ditemukan.");
                }

                $surat = $rows[0];

                // Validasi dan penentuan Customer resmi dari tb_customer
                $idCustomer = $selectedCustomerId ? (int)$selectedCustomerId : (int)($surat['id_customer'] ?? 0);
                if ($idCustomer <= 0) {
                    throw new \Exception("Pilih instansi pelanggan terdaftar dari master tb_customer terlebih dahulu.");
                }

                // Periksa customer di tb_customer: harus terdaftar, memiliki kodex_perusahaan dan id_layanan_optimalisasi = 1
                $custRows = $this->dbMain->exec(
                    "SELECT id_customer, kodex_perusahaan, nmcustomer, pt_cv, alamatcustomer, contactperson, contactperson_opti, notelpcustomer, nohpcontactperson_opti, emailcustomer, id_layanan_optimalisasi
                     FROM `tb_customer` 
                     WHERE `id_customer` = ?",
                    [1 => $idCustomer]
                );

                if (empty($custRows)) {
                    throw new \Exception("Instansi pelanggan #{$idCustomer} tidak ditemukan dalam database master tb_customer.");
                }

                $customer = $custRows[0];
                if (empty($customer['kodex_perusahaan'])) {
                    throw new \Exception("Perusahaan {$customer['nmcustomer']} belum memiliki Kode Pelanggan (kodex_perusahaan). Hanya surat dari pelanggan resmi yang dapat diterima.");
                }

                if ((int)$customer['id_layanan_optimalisasi'] !== 1) {
                    throw new \Exception("Perusahaan {$customer['nmcustomer']} belum terdaftar untuk Layanan Optimalisasi Teknologi Industri (OPTI).");
                }

                // Generate Nomor Order Baru
                $modelOrder = new \OrderLayanan($this->dbMain);
                $nomorOrder = $modelOrder->generateNomorOrder();

                // Tentukan jenis layanan: saat surat pertama kali diklaim menjadi order_layanan, belum ditentukan (menunggu disposisi / form pelayanan tim mitra)
                $jenisLayanan = (!empty($pilihanLayanan) && in_array($pilihanLayanan, ['selulosa', 'lingkungan'])) ? $pilihanLayanan : 'belum_ditentukan';

                // Insert ke order_layanan internal dengan status permintaan_masuk
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
                        5  => $surat['perihal'] ?: 'Permohonan Layanan OPTI',
                        6  => '',
                        7  => 'Lainnya',
                        8  => $jenisLayanan,
                        9  => $userId,
                        10 => $waktuSekarang,
                        11 => $waktuSekarang
                    )
                );

                $orderId = (int)($this->dbMain->exec("SELECT LAST_INSERT_ID() as id")[0]['id'] ?? 0);

                // Update tb_arsipsurat di dbSekretariat: simpan id_customer yang dipilih dan kaitkan orderId
                $this->dbSekretariat->exec(
                    "UPDATE `{$this->tableSekretariat}` 
                     SET `id_customer` = ?,
                         `id_pemasaran_order` = ?, 
                         `status_disposisi_surat` = 'diklaim', 
                         `sie_kerjasama_tanggal_baca` = ?, 
                         `sie_kerjasama_pc_tanggal_kirim` = ?,
                         `progres` = 'Permintaan'
                     WHERE `id_arsip` = ?",
                    [1 => $idCustomer, 2 => $orderId, 3 => $waktuSekarang, 4 => $waktuSekarang, 5 => $suratId]
                );

                $this->commitTransaction();

                return $orderId;
            }

            // 2. Row Locking pada DB Eksternal (SELECT ... FOR UPDATE)
            $rows = $this->dbSekretariat->exec(
                "SELECT * FROM `{$table}` WHERE `id` = ? AND `status_ambil` = 0 FOR UPDATE",
                array(1 => $suratId)
            );

            if (empty($rows)) {
                $this->rollbackTransaction();
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

            // 8. Commit transaksi
            $this->commitTransaction();

            return $orderId;

        } catch (\Exception $e) {
            $this->rollbackTransaction();
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

        $this->beginTransaction();

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

            if ($this->isArsipSurat()) {
                if ($idSuratMasuk > 0) {
                    $this->dbSekretariat->exec(
                        "UPDATE `{$this->tableSekretariat}` 
                         SET `id_pemasaran_order` = NULL, 
                             `status_disposisi_surat` = NULL,
                             `progres` = NULL
                         WHERE `id_arsip` = ?",
                        [1 => $idSuratMasuk]
                    );
                }
                $this->dbMain->exec("DELETE FROM `order_layanan` WHERE `id` = ?", [1 => $orderId]);
                $this->commitTransaction();
                return true;
            }

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

            // 4. Commit transaksi
            $this->commitTransaction();

            return true;

        } catch (\Exception $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Ambil data 1 surat berdasarkan ID
     */
    public function getSuratById(int $id): ?array {
        if ($this->isArsipSurat()) {
            $sql = "SELECT a.*, 
                           a.id_arsip AS id,
                           COALESCE(c.nmcustomer, 'Instansi / Perusahaan') AS pengirim,
                           c.pt_cv,
                           COALESCE(c.alamatcustomer, '-') AS alamat_pengirim,
                           COALESCE(NULLIF(a.kontak_person, ''), c.contactperson, '-') AS pic_pengirim,
                           COALESCE(NULLIF(a.hp_kontakperson, ''), c.notelpcustomer, '-') AS no_telp_pengirim,
                           COALESCE(NULLIF(a.email_kontakperson, ''), c.emailcustomer, '-') AS email_pengirim,
                           a.nama_berkas AS file_path,
                           a.nama_layanan,
                           CASE 
                               WHEN a.nama_layanan = 'OPTI_lingkungan' THEN 'OPTI Lingkungan'
                               WHEN a.nama_layanan = 'OPTI_Selulosa' THEN 'OPTI Selulosa'
                               ELSE 'OPTI'
                           END AS nama_layanan_label
                    FROM `{$this->tableSekretariat}` a
                    LEFT JOIN `{$this->dbSekretariatName}`.tb_customer c ON a.id_customer = c.id_customer
                    WHERE a.id_arsip = ?";
            if ($this->dbSekretariat) {
                $res = $this->dbSekretariat->exec($sql, [1 => $id]);
                if (!empty($res)) return $res[0];
            }
            $res = $this->dbMain->exec($sql, [1 => $id]);
            return !empty($res) ? $res[0] : null;
        }

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

        if ($this->isArsipSurat()) {
            $sql = "SELECT a.*, 
                           a.id_arsip AS id,
                           COALESCE(c.nmcustomer, 'Instansi / Perusahaan') AS pengirim,
                           c.pt_cv,
                           COALESCE(c.alamatcustomer, '-') AS alamat_pengirim,
                           COALESCE(NULLIF(a.kontak_person, ''), c.contactperson, '-') AS pic_pengirim,
                           a.keterangan_proses AS alasan_tolak,
                           a.tanggal_update AS tanggal_tolak,
                           'Tim Mitra' AS nama_penolak
                    FROM `{$this->tableSekretariat}` a
                    LEFT JOIN `{$this->dbSekretariatName}`.tb_customer c ON a.id_customer = c.id_customer
                    WHERE a.status_disposisi_surat = 'ditolak'";

            $params = [];
            if (!empty($filterTahun) && $filterTahun !== 'all') {
                $sql .= " AND YEAR(COALESCE(a.tanggal_update, a.tanggal_surat)) = ?";
                $params[1] = (int)$filterTahun;
            }
            $sql .= " ORDER BY a.tanggal_update DESC, a.id_arsip DESC";
            return $this->dbSekretariat->exec($sql, $params);
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

        if ($this->isArsipSurat()) {
            if ($this->isConnected()) {
                $this->dbSekretariat->exec(
                    "UPDATE `{$this->tableSekretariat}` 
                     SET `status_disposisi_surat` = 'ditolak', `keterangan_proses` = ?, `tanggal_update` = ?
                     WHERE `id_arsip` = ?",
                    [1 => $alasan, 2 => $waktuSekarang, 3 => $suratId]
                );
            }
            try {
                $this->dbMain->exec(
                    "UPDATE `order_layanan` 
                     SET `status` = 'ditolak', `alasan_tolak` = ?, `ditolak_oleh` = ?, `tanggal_tolak` = ?
                     WHERE `id_surat_masuk` = ?",
                    [1 => $alasan, 2 => $userId, 3 => $waktuSekarang, 4 => $suratId]
                );
            } catch (\Exception $e) {}
            return true;
        }

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