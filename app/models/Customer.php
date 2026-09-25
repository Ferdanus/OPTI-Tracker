<?php

/**
 * Model Customer
 * Berinteraksi dengan tabel master customer pusat balai 'tb_customer'
 * 
 * TODO: Konfirmasi ke admin database bahwa hak akses tb_customer adalah read-only
 * dari sisi OPTI kecuali saat menambah customer baru yang memesan layanan OPTI.
 * TODO: Konfirmasi apakah database OPTI akan satu server MySQL dengan database utama balai (202.150.151.244).
 */
class Customer extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'tb_customer');
    }

    /**
     * Ambil seluruh customer
     */
    public function all() {
        return $this->db->exec("SELECT * FROM tb_customer WHERE showhide = 'show' OR id_layanan_optimalisasi = 1 ORDER BY id_customer DESC");
    }

    /**
     * Ambil daftar customer yang terdaftar di layanan OPTI (flag id_layanan_optimalisasi = 1)
     */
    public function allOpti() {
        return $this->db->exec("SELECT * FROM tb_customer WHERE id_layanan_optimalisasi = 1 ORDER BY id_customer DESC");
    }

    /**
     * Format nama perusahaan agar tidak terjadi duplikasi bentuk badan (misal: "PT PT...")
     */
    public static function formatNamaPerusahaan(?string $ptCv, ?string $nama): string {
        $nama = trim($nama ?? '');
        $ptCv = trim($ptCv ?? '');
        if (empty($nama)) {
            return $ptCv;
        }
        // Bersihkan jika ada penulisan badan usaha berulang (misal "PT PT Tanjung...", "PT. PT. Sinar...", "PT PT PT...")
        $nama = preg_replace('/^((PT|CV|UD|BUMN|PERUM|PERSERO|Yayasan|Koperasi)\.?\s*)+/i', '$2 ', $nama);
        $nama = trim($nama);
        if (empty($ptCv)) {
            return $nama;
        }
        // Jika nama sudah diawali bentuk badan hukum yang sama/sejenis, jangan prepend lagi
        if (preg_match('/^(PT\.?|CV\.?|UD\.?|BUMN|PERUM|PERSERO|Yayasan|Koperasi)\b/i', $nama)) {
            return $nama;
        }
        return $ptCv . ' ' . $nama;
    }

    /**
     * Ambil daftar customer resmi OPTI (Pelanggan yang memiliki kodex_perusahaan dan id_layanan_optimalisasi = 1)
     * Digunakan untuk dropdown seleksi tanpa input manual bagi Tim Mitra & Sekretariat
     */
    public function getRegisteredPelangganOpti(): array {
        $rows = $this->db->exec(
            "SELECT id_customer, kodex_perusahaan, nmcustomer, pt_cv, 
                    alamatcustomer, alamatcustomer_baru, lokasi_pabrik, kode_pos,
                    contactperson, contactperson_opti, nama_pribadi,
                    notelpcustomer, nohpcontactperson, nohpcontactperson_opti, nofaxcustomer,
                    emailcustomer, emailcustomer_sertifikasi
             FROM tb_customer
             WHERE kodex_perusahaan IS NOT NULL 
               AND kodex_perusahaan != ''
               AND id_layanan_optimalisasi = 1
             ORDER BY nmcustomer ASC"
        );
        foreach ($rows as &$r) {
            $r['nama_perusahaan_bersih'] = self::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nmcustomer'] ?? '');

            // PIC bersih
            $pic = trim($r['contactperson'] ?? '');
            if (empty($pic) || $pic === '-') {
                $pic = trim($r['contactperson_opti'] ?? '');
            }
            if (empty($pic) || $pic === '-') {
                $pic = trim($r['nama_pribadi'] ?? '');
            }
            $r['pic_bersih'] = !empty($pic) ? $pic : '-';

            // No HP / WA
            $hp = trim($r['nohpcontactperson'] ?? '');
            if (empty($hp) || $hp === '-') {
                $hp = trim($r['nohpcontactperson_opti'] ?? '');
            }
            $r['hp_bersih'] = !empty($hp) ? $hp : '-';

            // Telepon Kantor
            $telpKantor = trim($r['notelpcustomer'] ?? '');
            if (empty($telpKantor) || $telpKantor === '-') {
                $telpKantor = trim($r['nofaxcustomer'] ?? '');
            }
            $r['telp_kantor_bersih'] = !empty($telpKantor) ? $telpKantor : '-';

            // Telepon Gabungan (HP / Kantor)
            if ($r['hp_bersih'] !== '-' && $r['telp_kantor_bersih'] !== '-' && $r['hp_bersih'] !== $r['telp_kantor_bersih']) {
                $r['telp_bersih'] = $r['hp_bersih'] . ' / ' . $r['telp_kantor_bersih'];
            } elseif ($r['hp_bersih'] !== '-') {
                $r['telp_bersih'] = $r['hp_bersih'];
            } elseif ($r['telp_kantor_bersih'] !== '-') {
                $r['telp_bersih'] = $r['telp_kantor_bersih'];
            } else {
                $r['telp_bersih'] = '-';
            }

            // Email bersih
            $email = trim($r['emailcustomer'] ?? '');
            if (empty($email) || $email === '-') {
                $email = trim($r['emailcustomer_sertifikasi'] ?? '');
            }
            $r['email_bersih'] = !empty($email) ? $email : '-';

            // Alamat bersih
            $alamat = trim($r['alamatcustomer'] ?? '');
            if (empty($alamat) || $alamat === '-') {
                $alamat = trim($r['alamatcustomer_baru'] ?? '');
            }
            $r['alamat_bersih'] = !empty($alamat) ? $alamat : '-';
        }
        unset($r);
        return $rows;
    }

    /**
     * Cari customer berdasarkan id_customer
     */
    public function getById(int $idCustomer) {
        $this->load(array('id_customer = ?', $idCustomer));
        return $this->dry() ? null : $this;
    }

    /**
     * Simpan customer baru dengan mengaktifkan flag id_layanan_optimalisasi = 1
     */
    public function simpanBaru(array $data): int {
        $this->reset();
        $this->nmcustomer                 = trim($data['nmcustomer'] ?? ($data['nama_perusahaan'] ?? ''));
        $this->pt_cv                      = trim($data['pt_cv'] ?? 'PT');
        $this->alamatcustomer             = trim($data['alamatcustomer'] ?? ($data['alamat'] ?? ''));
        $this->emailcustomer              = trim($data['emailcustomer'] ?? ($data['email'] ?? ''));
        $this->notelpcustomer             = trim($data['notelpcustomer'] ?? ($data['telepon'] ?? ''));
        $this->contactperson              = trim($data['contactperson'] ?? ($data['pic'] ?? ''));
        $this->id_layanan                 = 1;
        $this->id_layanan_optimalisasi    = 1;
        $this->contactperson_opti         = trim($data['contactperson_opti'] ?? ($data['pic'] ?? ''));
        $this->nohpcontactperson_opti     = trim($data['nohpcontactperson_opti'] ?? ($data['telepon'] ?? ''));
        $this->showhide                   = 'show';
        $this->showhide_sekertaris        = 'show';
        $this->tglinput                   = date('Y-m-d H:i:s');
        $this->tglupdate                  = date('Y-m-d H:i:s');
        $this->save();
        return (int)$this->id_customer;
    }

    /**
     * Update data customer
     */
    public function updateData(int $idCustomer, array $data): bool {
        $this->load(array('id_customer = ?', $idCustomer));
        if ($this->dry()) {
            throw new \Exception("Customer dengan ID #{$idCustomer} tidak ditemukan.");
        }

        $this->nmcustomer                 = trim($data['nmcustomer'] ?? ($data['nama_perusahaan'] ?? $this->nmcustomer));
        $this->pt_cv                      = trim($data['pt_cv'] ?? $this->pt_cv);
        $this->alamatcustomer             = trim($data['alamatcustomer'] ?? ($data['alamat'] ?? $this->alamatcustomer));
        $this->emailcustomer              = trim($data['emailcustomer'] ?? ($data['email'] ?? $this->emailcustomer));
        $this->notelpcustomer             = trim($data['notelpcustomer'] ?? ($data['telepon'] ?? $this->notelpcustomer));
        $this->contactperson              = trim($data['contactperson'] ?? ($data['pic'] ?? $this->contactperson));
        $this->id_layanan_optimalisasi    = 1;
        $this->contactperson_opti         = trim($data['contactperson_opti'] ?? ($data['pic'] ?? $this->contactperson_opti));
        $this->nohpcontactperson_opti     = trim($data['nohpcontactperson_opti'] ?? ($data['telepon'] ?? $this->nohpcontactperson_opti));
        $this->tglupdate                  = date('Y-m-d H:i:s');
        $this->save();
        return true;
    }

    /**
     * Ambil seluruh customer dengan hitungan relasi surat masuk (tb_arsipsurat) & order layanan (order_layanan)
     */
    public function allWithCounts(?string $search = null): array {
        $params = [];
        $search = trim($search ?? '');
        if (!empty($search)) {
            $where = "WHERE (LOWER(c.nmcustomer) LIKE LOWER(?) OR LOWER(c.contactperson) LIKE LOWER(?) OR LOWER(c.emailcustomer) LIKE LOWER(?) OR c.notelpcustomer LIKE ?)";
            $params = [1 => "%{$search}%", 2 => "%{$search}%", 3 => "%{$search}%", 4 => "%{$search}%"];
        } else {
            $where = "WHERE (c.id_layanan_optimalisasi = 1 
                        OR c.id_customer IN (SELECT DISTINCT id_customer FROM order_layanan WHERE id_customer IS NOT NULL)
                        OR c.id_customer IN (SELECT DISTINCT id_customer FROM tb_arsipsurat WHERE id_customer IS NOT NULL))";
        }

        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM tb_arsipsurat a WHERE a.id_customer = c.id_customer) AS total_surat,
                       (SELECT COUNT(*) FROM order_layanan o WHERE o.id_customer = c.id_customer) AS total_order,
                       COALESCE((SELECT SUM(o.estimasi_biaya) FROM order_layanan o WHERE o.id_customer = c.id_customer), 0) AS total_nilai_order
                FROM tb_customer c
                {$where}
                ORDER BY total_order DESC, total_surat DESC, c.nmcustomer ASC";

        $rows = $this->db->exec($sql, $params);
        foreach ($rows as &$r) {
            $r['nama_perusahaan_bersih'] = self::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nmcustomer'] ?? '');
        }
        unset($r);
        return $rows;
    }

    /**
     * Ambil detail lengkap customer beserta seluruh relasi surat masuk dan order layanan
     */
    public function getDetailWithRelations(int $idCustomer): ?array {
        $custRows = $this->db->exec("SELECT * FROM tb_customer WHERE id_customer = ?", [1 => $idCustomer]);
        if (empty($custRows)) {
            return null;
        }

        $customer = $custRows[0];
        $customer['nama_perusahaan_bersih'] = self::formatNamaPerusahaan($customer['pt_cv'] ?? '', $customer['nmcustomer'] ?? '');

        // Ambil riwayat surat masuk dari tb_arsipsurat
        $suratMasuk = $this->db->exec(
            "SELECT a.*,
                    o.id AS order_id,
                    o.nomor_order,
                    o.status AS order_status,
                    o.jenis_layanan_opti,
                    o.tanggal_masuk AS tanggal_order
             FROM tb_arsipsurat a
             LEFT JOIN order_layanan o ON a.id_arsip = o.id_surat_masuk
             WHERE a.id_customer = ?
             ORDER BY a.tanggal_surat DESC, a.id_arsip DESC",
            [1 => $idCustomer]
        );

        // Ambil riwayat order layanan dari order_layanan
        $orders = $this->db->exec(
            "SELECT o.*,
                    p.nomor_po,
                    p.biaya AS biaya_po,
                    a.nomor_surat AS nomor_surat_masuk,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id), 0) AS total_terbayar
             FROM order_layanan o
             LEFT JOIN po p ON o.id = p.order_id
             LEFT JOIN tb_arsipsurat a ON o.id_surat_masuk = a.id_arsip
             WHERE o.id_customer = ?
             ORDER BY o.id DESC",
            [1 => $idCustomer]
        );

        // Statistik agregat
        $stats = [
            'total_surat'    => count($suratMasuk),
            'total_order'    => count($orders),
            'order_selesai'  => count(array_filter($orders, function($o) { return in_array($o['status'], ['selesai', 'lapor_selesai']); })),
            'order_proses'   => count(array_filter($orders, function($o) { return !in_array($o['status'], ['selesai', 'lapor_selesai', 'ditolak']); })),
            'total_biaya'    => array_sum(array_column($orders, 'estimasi_biaya')),
            'total_terbayar' => array_sum(array_column($orders, 'total_terbayar'))
        ];

        return [
            'customer'    => $customer,
            'surat_masuk' => $suratMasuk,
            'orders'      => $orders,
            'stats'       => $stats
        ];
    }

    /**
     * Hapus customer jika tidak ada relasi order aktif maupun surat masuk yang terikat
     */
    public function hapus(int $idCustomer): bool {
        $orders = $this->db->exec("SELECT COUNT(*) AS total FROM order_layanan WHERE id_customer = ?", array(1 => $idCustomer));
        $totalOrders = (int)($orders[0]['total'] ?? 0);
        if ($totalOrders > 0) {
            throw new \Exception("Customer tidak dapat dihapus karena memiliki {$totalOrders} order layanan aktif.");
        }

        $surat = $this->db->exec("SELECT COUNT(*) AS total FROM tb_arsipsurat WHERE id_customer = ?", array(1 => $idCustomer));
        $totalSurat = (int)($surat[0]['total'] ?? 0);
        if ($totalSurat > 0) {
            throw new \Exception("Customer tidak dapat dihapus karena terhubung dengan {$totalSurat} berkas arsip surat permohonan.");
        }

        $this->load(array('id_customer = ?', $idCustomer));
        if (!$this->dry()) {
            $this->erase();
        }
        return true;
    }
}
