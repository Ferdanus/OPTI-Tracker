<?php

/**
 * Model OrderLayanan
 * Mengelola permohonan layanan OPTI dari customer (tb_customer), standar SPM, 6 Lab balai, dan spesifikasi sampel
 */
class OrderLayanan extends \DB\SQL\Mapper {

    // Daftar Baku Standar Pelayanan Minimum (SPM) beserta durasi waktu maksimum standar
    public static $SPM_LIST = array(
        'Pembuatan pulp'                                 => '3 bulan',
        'Pemutihan pulp'                                 => '3 bulan',
        'Percobaan derivat selulosa'                     => '6 bulan',
        'Aplikasi aditif pulp'                           => '2 bulan',
        'Aplikasi aditif kertas'                         => '2 bulan',
        'Percobaan LD-50'                                => 'Sesuai lingkup kegiatan',
        'Percobaan pengolahan air limbah'                => 'Sesuai lingkup kegiatan',
        'Analisis kategori dampak lingkungan'            => 'Sesuai lingkup kegiatan',
        'Pengkajian Circular Economy & Life Cycle Analysis' => 'Sesuai lingkup kegiatan',
        'Lainnya'                                        => 'Sesuai lingkup kegiatan'
    );

    // 6 Laboratorium Resmi Pendukung OPTI di BBSPJIS
    public static $LAB_INTERNAL_LIST = array(
        'Pemasakan & Pemutihan'       => '1. Lab Pemasakan & Pemutihan Pulp',
        'Stock Preparation'           => '2. Lab Stock Preparation & Pengolahan Serat',
        'Derivat Selulosa'            => '3. Lab Derivat Selulosa & Biomaterial',
        'Mikrobiologi'                => '4. Lab Mikrobiologi Industri',
        'Biodegradasi & Toksikologi'  => '5. Lab Biodegradasi, Toksikologi & Ekotoksikologi',
        'Pengolahan Lingkungan'       => '6. Lab Pengolahan Air Limbah & Emisi Lingkungan'
    );

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'order_layanan');
    }

    /**
     * Hitung perkiraan target selesai berdasarkan tanggal masuk & SPM
     */
    public static function hitungTargetSelesaiSpm(string $tanggalMasuk, string $spmLayanan): ?string {
        $durasi = self::$SPM_LIST[$spmLayanan] ?? 'Sesuai lingkup kegiatan';
        $time = strtotime($tanggalMasuk);
        if (!$time) $time = time();

        if (strpos($durasi, '3 bulan') !== false) {
            return date('Y-m-d', strtotime('+3 months', $time));
        } elseif (strpos($durasi, '6 bulan') !== false) {
            return date('Y-m-d', strtotime('+6 months', $time));
        } elseif (strpos($durasi, '2 bulan') !== false) {
            return date('Y-m-d', strtotime('+2 months', $time));
        }
        // Default untuk yang sesuai lingkup kegiatan (misal 1 bulan)
        return date('Y-m-d', strtotime('+1 month', $time));
    }

    /**
     * Ambil seluruh order layanan lengkap dengan nama customer, nomor PO, dan ringkasan pembayaran
     */
    public function allWithRelasi(string $filterJenis = '', string $filterStatus = '', string $search = ''): array {
        $sql = "SELECT o.*, 
                       c.nmcustomer AS nama_perusahaan, c.pt_cv, c.contactperson AS pic, c.notelpcustomer AS telepon, c.emailcustomer AS email,
                       p.id AS po_id, p.nomor_po, p.status AS status_po, p.biaya AS biaya_po,
                       COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id), 0) AS total_terbayar
                FROM order_layanan o
                JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN po p ON o.id = p.order_id
                WHERE 1=1";
        
        $params = array();
        $idx = 1;

        if (!empty($filterJenis)) {
            $sql .= " AND o.jenis_layanan_opti = ?";
            $params[$idx++] = $filterJenis;
        }

        if (!empty($filterStatus)) {
            $sql .= " AND o.status = ?";
            $params[$idx++] = $filterStatus;
        }

        if (!empty($search)) {
            $sql .= " AND (o.nomor_order LIKE ? OR o.judul_kegiatan LIKE ? OR c.nmcustomer LIKE ? OR o.jenis_sampel LIKE ?)";
            $wildcard = "%{$search}%";
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
        }

        $sql .= " ORDER BY o.id DESC";

        return $this->db->exec($sql, $params);
    }

    /**
     * Ambil detail satu order layanan lengkap
     */
    public function getDetail(int $id): ?array {
        $res = $this->db->exec(
            "SELECT o.*, 
                    c.nmcustomer AS nama_perusahaan, c.pt_cv, c.contactperson AS pic, c.notelpcustomer AS telepon, c.emailcustomer AS email, c.alamatcustomer AS alamat,
                    p.id AS po_id, p.nomor_po, p.status AS status_po, p.biaya AS biaya_po, p.target_selesai AS target_po,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id), 0) AS total_terbayar
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer
             LEFT JOIN po p ON o.id = p.order_id
             WHERE o.id = ?",
            array(1 => $id)
        );
        return $res[0] ?? null;
    }

    /**
     * Cari order by ID
     */
    public function getById(int $id) {
        $this->load(array('id = ?', $id));
        return $this->dry() ? null : $this;
    }

    /**
     * Generate nomor order unik aman: ORD-YYYYMM-XXX
     */
    public function generateNomorOrder(): string {
        $prefix = 'ORD-' . date('Ym');
        $rows = $this->db->exec(
            "SELECT nomor_order FROM order_layanan WHERE nomor_order LIKE ?",
            array(1 => $prefix . '-%')
        );

        $maxSeq = 0;
        foreach ($rows as $r) {
            $parts = explode('-', $r['nomor_order']);
            $num = (int)($parts[2] ?? 0);
            if ($num > $maxSeq) {
                $maxSeq = $num;
            }
        }

        $nextSeq = $maxSeq + 1;
        $nomorOrder = $prefix . '-' . sprintf('%03d', $nextSeq);

        while (($this->db->exec("SELECT COUNT(*) as c FROM order_layanan WHERE nomor_order = ?", array(1 => $nomorOrder))[0]['c'] ?? 0) > 0) {
            $nextSeq++;
            $nomorOrder = $prefix . '-' . sprintf('%03d', $nextSeq);
        }

        return $nomorOrder;
    }

    /**
     * Simpan order layanan baru
     */
    public function simpanBaru(array $data): int {
        $this->reset();
        $this->id_customer          = (int)$data['id_customer'];
        $this->nomor_order          = trim($data['nomor_order'] ?? '') ?: $this->generateNomorOrder();
        $this->tanggal_masuk        = $data['tanggal_masuk'];
        $this->judul_kegiatan       = trim($data['judul_kegiatan']);
        $this->deskripsi            = trim($data['deskripsi'] ?? '');
        $this->spm_layanan          = trim($data['spm_layanan'] ?? 'Lainnya');
        $this->jenis_layanan_opti   = in_array($data['jenis_layanan_opti'] ?? '', array('selulosa', 'lingkungan')) ? $data['jenis_layanan_opti'] : 'selulosa';
        $this->lokasi_pelaksanaan   = in_array($data['lokasi_pelaksanaan'] ?? '', array('internal', 'lapangan')) ? $data['lokasi_pelaksanaan'] : 'internal';
        $this->lab_internal         = ($this->lokasi_pelaksanaan === 'internal') ? trim($data['lab_internal'] ?? '') : null;
        $this->lokasi_lapangan      = ($this->lokasi_pelaksanaan === 'lapangan') ? trim($data['lokasi_lapangan'] ?? '') : null;
        
        // Spesifikasi Teknis Sampel
        $this->tipe_data_sampel     = trim($data['tipe_data_sampel'] ?? '');
        $this->jenis_sampel         = trim($data['jenis_sampel'] ?? '');
        $this->volume_berat         = trim($data['volume_berat'] ?? '');
        $this->karakteristik_serat  = trim($data['karakteristik_serat'] ?? '');
        $this->karakteristik_kimia  = trim($data['karakteristik_kimia'] ?? '');
        
        $this->jumlah_pekerjaan     = trim($data['jumlah_pekerjaan'] ?? '1 paket kegiatan');
        $this->estimasi_biaya       = (float)($data['estimasi_biaya'] ?? 0);
        $this->status               = 'baru';
        $this->created_at           = date('Y-m-d H:i:s');
        $this->save();
        return (int)$this->id;
    }

    /**
     * Update data order layanan
     */
    public function updateData(int $id, array $data): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("Order Layanan #{$id} tidak ditemukan.");
        }

        if (!empty($data['id_customer'])) {
            $this->id_customer = (int)$data['id_customer'];
        }
        $this->tanggal_masuk        = $data['tanggal_masuk'];
        $this->judul_kegiatan       = trim($data['judul_kegiatan']);
        $this->deskripsi            = trim($data['deskripsi'] ?? '');
        $this->spm_layanan          = trim($data['spm_layanan'] ?? $this->spm_layanan);
        $this->jenis_layanan_opti   = in_array($data['jenis_layanan_opti'] ?? '', array('selulosa', 'lingkungan')) ? $data['jenis_layanan_opti'] : $this->jenis_layanan_opti;
        $this->lokasi_pelaksanaan   = in_array($data['lokasi_pelaksanaan'] ?? '', array('internal', 'lapangan')) ? $data['lokasi_pelaksanaan'] : $this->lokasi_pelaksanaan;
        $this->lab_internal         = ($this->lokasi_pelaksanaan === 'internal') ? trim($data['lab_internal'] ?? '') : null;
        $this->lokasi_lapangan      = ($this->lokasi_pelaksanaan === 'lapangan') ? trim($data['lokasi_lapangan'] ?? '') : null;
        
        // Spesifikasi Teknis Sampel
        $this->tipe_data_sampel     = trim($data['tipe_data_sampel'] ?? $this->tipe_data_sampel);
        $this->jenis_sampel         = trim($data['jenis_sampel'] ?? $this->jenis_sampel);
        $this->volume_berat         = trim($data['volume_berat'] ?? $this->volume_berat);
        $this->karakteristik_serat  = trim($data['karakteristik_serat'] ?? $this->karakteristik_serat);
        $this->karakteristik_kimia  = trim($data['karakteristik_kimia'] ?? $this->karakteristik_kimia);
        
        $this->jumlah_pekerjaan     = trim($data['jumlah_pekerjaan'] ?? $this->jumlah_pekerjaan);
        $this->estimasi_biaya       = (float)($data['estimasi_biaya'] ?? $this->estimasi_biaya);
        $this->save();
        return true;
    }

    /**
     * Menyetujui order layanan dan otomatis membuat record PO terkait
     */
    public function approve(int $id, string $nomorPoManual = '', float $biaya = 0.0): array {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("Order Layanan #{$id} tidak ditemukan.");
        }

        if ($this->status !== 'baru') {
            throw new \Exception("Order #{$id} sudah berstatus '{$this->status}' dan tidak dapat disetujui lagi.");
        }

        // 1. Update status order menjadi disetujui
        $this->status = 'disetujui';
        $this->save();

        // 2. Buat PO otomatis melalui model Po
        $poModel = new Po($this->db);
        $biayaAwal = $biaya > 0 ? $biaya : (float)$this->estimasi_biaya;
        $targetSpm = self::hitungTargetSelesaiSpm($this->tanggal_masuk, $this->spm_layanan);

        $poId = $poModel->buatDariOrder($this->id, $nomorPoManual, $biayaAwal, array(
            'target_mulai'   => date('Y-m-d'),
            'target_selesai' => $targetSpm
        ));

        return array(
            'order_id' => $this->id,
            'po_id'    => $poId,
            'nomor_po' => $poModel->nomor_po
        );
    }

    /**
     * Menolak order layanan
     */
    public function tolak(int $id): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("Order Layanan #{$id} tidak ditemukan.");
        }
        $this->status = 'ditolak';
        $this->save();
        return true;
    }

    /**
     * Hapus order beserta PO dan pembayarannya
     */
    public function hapus(int $id): bool {
        $this->load(array('id = ?', $id));
        if ($this->dry()) {
            throw new \Exception("Order Layanan #{$id} tidak ditemukan.");
        }

        // Hapus PO terkait jika ada
        $po = $this->db->exec("SELECT id FROM po WHERE order_id = ?", array(1 => $id));
        if (!empty($po)) {
            $poModel = new Po($this->db);
            $poModel->hapus((int)$po[0]['id']);
        }

        // Hapus pembayaran terkait
        $this->db->exec("DELETE FROM opti_pembayaran WHERE order_id = ?", array(1 => $id));

        $this->erase();
        return true;
    }
}
