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
    public function allWithRelasi(string $filterJenis = '', string $filterStatus = '', string $search = '', string $filterTahun = '', string $filterTab = 'aktif'): array {
        $sql = "SELECT o.*, 
                       c.nmcustomer AS nama_perusahaan, c.pt_cv, 
                       COALESCE(NULLIF(c.contactperson_opti, ''), NULLIF(c.contactperson, ''), c.nama_pribadi, '-') AS pic,
                       COALESCE(NULLIF(c.nohpcontactperson_opti, ''), NULLIF(c.nohpcontactperson, ''), c.notelpcustomer, '-') AS telepon,
                       COALESCE(NULLIF(c.emailcustomer, ''), c.emailcustomer_sertifikasi, '-') AS email,
                       COALESCE(NULLIF(c.alamatcustomer_baru, ''), c.alamatcustomer, '-') AS alamat,
                       p.id AS po_id, p.nomor_po, p.status AS status_po, p.biaya AS biaya_po,
                       sp.status_respon_klien, sp.nomor_surat AS nomor_penawaran,
                       pr.durasi_kegiatan AS proposal_durasi, pr.status_proposal,
                       COALESCE(u_tolak.nama_user, u_tinjau.nama_user, '-') AS nama_penolak,
                       COALESCE(NULLIF(o.alasan_tolak, ''), NULLIF(tk.alasan_penolakan, ''), '-') AS alasan_tolak,
                       COALESCE(o.tanggal_tolak, tk.tanggal_tinjauan) AS tanggal_tolak,
                       COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id), 0) AS total_terbayar
                FROM order_layanan o
                JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN po p ON o.id = p.order_id
                LEFT JOIN tb_surat_penawaran sp ON o.id = sp.order_id
                LEFT JOIN opti_proposal_riset pr ON o.id = pr.order_id
                LEFT JOIN tb_arsipuser u_tolak ON o.ditolak_oleh = u_tolak.id_user
                LEFT JOIN opti_tinjauan_kelayakan tk ON o.id = tk.order_id AND tk.keputusan = 'tidak_dapat_dilaksanakan'
                LEFT JOIN tb_arsipuser u_tinjau ON tk.ditinjau_oleh = u_tinjau.id_user
                WHERE 1=1";
        
        $params = array();
        $idx = 1;

        // Filter tab: aktif (hanya yang sedang berlangsung / acc) vs ditolak (arsip penampungan ditolak)
        if ($filterTab === 'aktif') {
            $sql .= " AND o.status != 'ditolak' AND (o.status_tinjauan != 'tidak_layak' OR o.status_tinjauan IS NULL)";
        } elseif ($filterTab === 'ditolak') {
            $sql .= " AND (o.status = 'ditolak' OR o.status_tinjauan = 'tidak_layak')";
        }

        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql .= " AND YEAR(COALESCE(o.tanggal_masuk, o.tanggal_klaim, o.created_at)) = ?";
            $params[$idx++] = (int)$filterTahun;
        }

        if (!empty($filterJenis)) {
            $sql .= " AND o.jenis_layanan_opti = ?";
            $params[$idx++] = $filterJenis;
        }

        if (!empty($filterStatus)) {
            if ($filterStatus === 'draft_disimpan' || $filterStatus === 'draft') {
                $sql .= " AND (o.status IN ('draft', 'draft_disimpan') OR o.status_proposal_biaya = 'draft_disimpan' OR pr.status_proposal = 'draft_disimpan')";
            } else {
                $sql .= " AND o.status = ?";
                $params[$idx++] = $filterStatus;
            }
        }

        if (!empty($search)) {
            $sql .= " AND (o.nomor_order LIKE ? OR o.judul_kegiatan LIKE ? OR c.nmcustomer LIKE ? OR o.jenis_sampel LIKE ?)";
            $wildcard = "%{$search}%";
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
        }

        if ($filterTab === 'ditolak') {
            $sql .= " ORDER BY COALESCE(o.tanggal_tolak, o.id) DESC";
        } else {
            $sql .= " ORDER BY o.id DESC";
        }

        $rows = $this->db->exec($sql, $params);
        foreach ($rows as &$r) {
            $st = self::resolveStageStatus($r);
            $r['stage_label'] = $st['label'];
            $r['stage_class'] = $st['class'];
            $r['stage_icon']  = $st['icon'];

            // Resolusi nama & badge tahap saat order ditolak
            if (($r['status_tinjauan'] ?? '') === 'tidak_layak') {
                $r['tahap_tolak'] = 'Kaji Kelayakan ISO';
                $r['tahap_tolak_class'] = 'bg-danger-subtle text-danger border border-danger-subtle';
                $r['tahap_tolak_icon'] = 'bi-clipboard-x';
            } elseif (!empty($r['status_respon_klien']) && in_array($r['status_respon_klien'], ['batal', 'ditolak'])) {
                $r['tahap_tolak'] = 'Penawaran Harga';
                $r['tahap_tolak_class'] = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                $r['tahap_tolak_icon'] = 'bi-file-earmark-x';
            } elseif (($r['status'] ?? '') === 'permintaan_masuk') {
                $r['tahap_tolak'] = 'Disposisi Surat Masuk';
                $r['tahap_tolak_class'] = 'bg-secondary-subtle text-secondary border';
                $r['tahap_tolak_icon'] = 'bi-inbox';
            } else {
                $r['tahap_tolak'] = 'Verifikasi Order';
                $r['tahap_tolak_class'] = 'bg-danger-subtle text-danger border border-danger-subtle';
                $r['tahap_tolak_icon'] = 'bi-x-circle';
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * Helper penentu status alur tunggal, simpel, dan konsisten
     */
    public static function resolveStageStatus(array $o): array {
        // 1. Selesai
        if (($o['status'] ?? '') === 'selesai' || ($o['status_pelaksanaan'] ?? '') === 'laporan_selesai') {
            return [
                'label' => 'Selesai (LHU)',
                'class' => 'bg-success text-white border border-success',
                'icon'  => 'bi-check2-all'
            ];
        }
        // 2. Ditolak
        if (($o['status'] ?? '') === 'ditolak' || ($o['status_tinjauan'] ?? '') === 'tidak_layak') {
            return [
                'label' => 'Ditolak',
                'class' => 'bg-danger text-white border border-danger',
                'icon'  => 'bi-x-circle'
            ];
        }
        // 3. Pelaksanaan Lab (PO)
        if (!empty($o['po_id']) || ($o['status'] ?? '') === 'disetujui' || in_array($o['status_pelaksanaan'] ?? '', ['sedang_berjalan', 'evaluasi_laporan', 'revisi_laporan'])) {
            return [
                'label' => 'Pengerjaan Lab (PO)',
                'class' => 'bg-primary text-white border border-primary',
                'icon'  => 'bi-gear-wide-connected'
            ];
        }
        // 4. Penawaran DEAL
        if (($o['status'] ?? '') === 'penawaran_deal' || ($o['status_respon_klien'] ?? '') === 'deal' || ($o['status_penawaran'] ?? '') === 'deal') {
            return [
                'label' => 'Penawaran DEAL',
                'class' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                'icon'  => 'bi-hand-thumbs-up-fill'
            ];
        }

        // Cek apakah proposal teknis/biaya sudah disetujui oleh Ka Tim
        $proposalApproved = (in_array($o['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) || in_array($o['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan']));
        
        // Cek apakah surat penawaran harga resmi telah terbit
        $hasOfferLetter = (!empty($o['surat_penawaran_id']) || in_array($o['status_penawaran'] ?? '', ['terkirim', 'nego', 'draft']));

        // 5. Penawaran Terbit (Hanya setelah proposal disetujui dan surat penawaran resmi diterbitkan)
        if ($hasOfferLetter && $proposalApproved) {
            return [
                'label' => 'Penawaran Harga',
                'class' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                'icon'  => 'bi-file-earmark-text-fill'
            ];
        }

        // 6. Proposal Disetujui (Siap Kirim Penawaran)
        if ($proposalApproved) {
            return [
                'label' => 'Proposal Disetujui',
                'class' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                'icon'  => 'bi-check-circle-fill'
            ];
        }

        // 7. Penyusunan Proposal / Dokumen (Setelah Kaji Ulang Layak / PIC ditunjuk)
        if (($o['status_tinjauan'] ?? '') === 'layak' || !empty($o['pic_proposal_id'])) {
            if (($o['status_proposal'] ?? '') === 'draft_disimpan' || ($o['status_proposal_biaya'] ?? '') === 'draft_disimpan') {
                return [
                    'label' => 'Draft Disimpan',
                    'class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                    'icon'  => 'bi-bookmark-check-fill'
                ];
            }
            if (($o['status_proposal_biaya'] ?? '') === 'menunggu_approval' || ($o['status_proposal'] ?? '') === 'diajukan') {
                return [
                    'label' => 'Menunggu Review Ka. Tim',
                    'class' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                    'icon'  => 'bi-hourglass-split'
                ];
            }
            return [
                'label' => 'Penyusunan Proposal',
                'class' => 'bg-primary-subtle text-primary border border-primary-subtle',
                'icon'  => 'bi-pencil-square'
            ];
        }

        // 8. Draft Disimpan (Order Masuk / Form Pelayanan Jasa)
        if (($o['status'] ?? '') === 'draft_disimpan' || ($o['status'] ?? '') === 'draft') {
            return [
                'label' => 'Draft Disimpan',
                'class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'icon'  => 'bi-bookmark-check-fill'
            ];
        }

        // 9. Permintaan Masuk (Belum disposisi divisi / belum diverifikasi awal)
        if (($o['status'] ?? '') === 'permintaan_masuk' || ($o['jenis_layanan_opti'] ?? '') === 'belum_ditentukan') {
            return [
                'label' => 'Permintaan Masuk',
                'class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                'icon'  => 'bi-inbox'
            ];
        }

        // 10. Kaji Ulang (Ka. Tim)
        if (($o['status_tinjauan'] ?? '') === 'belum_ditinjau' || empty($o['status_tinjauan'])) {
            return [
                'label' => 'Kaji Ulang (Ka. Tim)',
                'class' => 'bg-warning-subtle text-dark border border-warning',
                'icon'  => 'bi-clipboard-check'
            ];
        }

        // 11. Default
        return [
            'label' => 'Order Baru',
            'class' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
            'icon'  => 'bi-file-earmark'
        ];
    }

    /**
     * Ambil detail satu order layanan lengkap
     */
    public function getDetail(int $id): ?array {
        $res = $this->db->exec(
            "SELECT o.*, 
                    c.nmcustomer AS nama_perusahaan, c.pt_cv, 
                    COALESCE(NULLIF(c.contactperson_opti, ''), NULLIF(c.contactperson, ''), c.nama_pribadi, '-') AS pic,
                    COALESCE(NULLIF(c.nohpcontactperson_opti, ''), NULLIF(c.nohpcontactperson, ''), c.notelpcustomer, '-') AS telepon,
                    COALESCE(NULLIF(c.emailcustomer, ''), c.emailcustomer_sertifikasi, '-') AS email,
                    COALESCE(NULLIF(c.alamatcustomer_baru, ''), c.alamatcustomer, '-') AS alamat,
                    p.id AS po_id, p.nomor_po, p.status AS status_po, p.biaya AS biaya_po, p.target_selesai AS target_po,
                    pr.durasi_kegiatan AS proposal_durasi, pr.status_proposal,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id), 0) AS total_terbayar,
                    u_pic.nama_user AS pic_proposal_nama,
                    u_klaim.nama_user AS nama_pengklaim
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer
             LEFT JOIN po p ON o.id = p.order_id
             LEFT JOIN tb_arsipuser u_pic ON o.pic_proposal_id = u_pic.id_user
             LEFT JOIN tb_arsipuser u_klaim ON o.diklaim_oleh = u_klaim.id_user
             LEFT JOIN opti_proposal_riset pr ON o.id = pr.order_id
             WHERE o.id = ?",
            array(1 => $id)
        );
        if (empty($res)) {
            return null;
        }
        $detail = $res[0];
        $st = self::resolveStageStatus($detail);
        $detail['stage_label'] = $st['label'];
        $detail['stage_class'] = $st['class'];
        $detail['stage_icon']  = $st['icon'];

        return $detail;
    }

    /**
     * Cari order by ID
     */
    public function getById(int $id) {
        $this->load(array('id = ?', $id));
        return $this->dry() ? null : $this;
    }

    public function getDetailSurat(int $id)
{
    $result = $this->db->exec(
        "SELECT 
            o.*,
            c.contactperson AS nama_pelanggan,
            c.nmcustomer AS nama_perusahaan,
            c.alamatcustomer AS alamat_customer,

            t.sdm_tersedia,
            t.sdm_catatan,
            t.peralatan_tersedia,
            t.peralatan_catatan,
            t.bahan_tersedia,
            t.bahan_catatan,
            t.metode_tersedia,
            t.metode_catatan,
            t.keputusan,
            t.alasan_penolakan,

            sp.permintaan_melalui,
            sp.pegawai_id,
            sp.penjelasan AS sp_penjelasan,

            p.nama_user AS nama_pegawai


        FROM order_layanan o
        LEFT JOIN tb_customer c 
            ON c.id_customer = o.id_customer
        LEFT JOIN opti_tinjauan_kelayakan t
            ON t.order_id = o.id
        LEFT JOIN tb_surat_penawaran sp
            ON sp.order_id = o.id
        LEFT JOIN tb_arsipuser p
            ON p.id_user = sp.pegawai_id
        WHERE o.id = ?",
        [$id]
    );

    return $result[0] ?? null;
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
        $this->status               = in_array($data['status'] ?? '', array('permintaan_masuk', 'baru', 'draft', 'draft_disimpan', 'disetujui', 'ditolak')) ? $data['status'] : 'baru';
        $this->id_surat_masuk       = !empty($data['id_surat_masuk']) ? (int)$data['id_surat_masuk'] : null;
        $this->diklaim_oleh         = !empty($data['diklaim_oleh']) ? (int)$data['diklaim_oleh'] : null;
        $this->tanggal_klaim        = !empty($data['tanggal_klaim']) ? $data['tanggal_klaim'] : null;
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
        if (isset($data['tanggal_masuk'])) {
            $this->tanggal_masuk = $data['tanggal_masuk'];
        }
        if (isset($data['judul_kegiatan'])) {
            $this->judul_kegiatan = trim($data['judul_kegiatan']);
        }
        if (isset($data['deskripsi'])) {
            $this->deskripsi = trim($data['deskripsi']);
        }
        if (isset($data['spm_layanan'])) {
            $this->spm_layanan = trim($data['spm_layanan']);
        }
        if (isset($data['jenis_layanan_opti']) && in_array($data['jenis_layanan_opti'], array('selulosa', 'lingkungan'))) {
            $this->jenis_layanan_opti = $data['jenis_layanan_opti'];
        }
        if (isset($data['lokasi_pelaksanaan']) && in_array($data['lokasi_pelaksanaan'], array('internal', 'lapangan'))) {
            $this->lokasi_pelaksanaan = $data['lokasi_pelaksanaan'];
        }
        if (isset($data['lab_internal'])) {
            $this->lab_internal = ($this->lokasi_pelaksanaan === 'internal') ? trim($data['lab_internal']) : null;
        }
        if (isset($data['lokasi_lapangan'])) {
            $this->lokasi_lapangan = ($this->lokasi_pelaksanaan === 'lapangan') ? trim($data['lokasi_lapangan']) : null;
        }
        
        // Spesifikasi Teknis Sampel
        if (isset($data['tipe_data_sampel'])) $this->tipe_data_sampel = trim($data['tipe_data_sampel']);
        if (isset($data['jenis_sampel'])) $this->jenis_sampel = trim($data['jenis_sampel']);
        if (isset($data['volume_berat'])) $this->volume_berat = trim($data['volume_berat']);
        if (isset($data['karakteristik_serat'])) $this->karakteristik_serat = trim($data['karakteristik_serat']);
        if (isset($data['karakteristik_kimia'])) $this->karakteristik_kimia = trim($data['karakteristik_kimia']);
        
        if (isset($data['jumlah_pekerjaan'])) $this->jumlah_pekerjaan = trim($data['jumlah_pekerjaan']);
        if (isset($data['estimasi_biaya'])) $this->estimasi_biaya = (float)$data['estimasi_biaya'];
        if (isset($data['status'])) $this->status = $data['status'];
        if (isset($data['pic_proposal_id'])) $this->pic_proposal_id = !empty($data['pic_proposal_id']) ? (int)$data['pic_proposal_id'] : null;

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
        $this->status_tinjauan = 'tidak_layak';
        $this->save();
        return true;
    }

    /**
     * Ambil data tinjauan kelayakan ISO untuk satu order
     */
    public function getTinjauanKelayakan(int $orderId): ?array {
        $res = $this->db->exec(
            "SELECT t.*, u.nama_user AS peninjau_nama 
             FROM opti_tinjauan_kelayakan t 
             LEFT JOIN tb_arsipuser u ON t.ditinjau_oleh = u.id_user 
             WHERE t.order_id = ? 
             ORDER BY t.id DESC LIMIT 1",
            array(1 => $orderId)
        );
        return !empty($res) ? $res[0] : null;
    }

    /**
     * Simpan / Perbarui Tinjauan Kelayakan Permintaan (Kartu Kendali ISO)
     */
    public function simpanTinjauanKelayakan(int $orderId, array $data, int $userId): array {
        $this->load(array('id = ?', $orderId));
        if ($this->dry()) {
            throw new \Exception("Order Layanan #{$orderId} tidak ditemukan.");
        }

        $sdmTersedia   = !empty($data['sdm_tersedia']) ? 1 : 0;
        $sdmCatatan    = trim($data['sdm_catatan'] ?? '');
        $alatTersedia  = !empty($data['peralatan_tersedia']) ? 1 : 0;
        $alatCatatan   = trim($data['peralatan_catatan'] ?? '');
        $bahanTersedia = !empty($data['bahan_tersedia']) ? 1 : 0;
        $bahanCatatan  = trim($data['bahan_catatan'] ?? '');
        $metodeTersedia= !empty($data['metode_tersedia']) ? 1 : 0;
        $metodeCatatan = trim($data['metode_catatan'] ?? '');

        $keputusan = ($data['keputusan'] ?? '') === 'tidak_dapat_dilaksanakan' ? 'tidak_dapat_dilaksanakan' : 'dapat_dilaksanakan';
        $alasanPenolakan = trim($data['alasan_penolakan'] ?? '');

        // Validasi: Jika 4 parameter tidak siap, tidak boleh 'dapat_dilaksanakan'
        if ($keputusan === 'dapat_dilaksanakan' && (!$sdmTersedia || !$alatTersedia || !$bahanTersedia || !$metodeTersedia)) {
            throw new \Exception("Semua 4 parameter kesiapan (SDM, Alat, Bahan, dan Metode Uji) harus terpenuhi untuk menyetujui status 'Dapat Dilaksanakan'.");
        }

        // Hapus tinjauan lama jika ada untuk order ini
        $this->db->exec("DELETE FROM opti_tinjauan_kelayakan WHERE order_id = ?", array(1 => $orderId));

        // Insert tinjauan baru
        $this->db->exec(
            "INSERT INTO opti_tinjauan_kelayakan 
            (order_id, sdm_tersedia, sdm_catatan, peralatan_tersedia, peralatan_catatan, bahan_tersedia, bahan_catatan, metode_tersedia, metode_catatan, keputusan, alasan_penolakan, ditinjau_oleh, tanggal_tinjauan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            array(
                1 => $orderId,
                2 => $sdmTersedia,
                3 => $sdmCatatan,
                4 => $alatTersedia,
                5 => $alatCatatan,
                6 => $bahanTersedia,
                7 => $bahanCatatan,
                8 => $metodeTersedia,
                9 => $metodeCatatan,
                10 => $keputusan,
                11 => $alasanPenolakan,
                12 => $userId
            )
        );

        // Update status order & Penunjukan PIC Proposal
        if ($keputusan === 'dapat_dilaksanakan') {
            $this->status_tinjauan = 'layak';
            $this->status = 'baru';
            
            $picProposalId = !empty($data['pic_proposal_id']) ? (int)$data['pic_proposal_id'] : null;
            if ($picProposalId > 0) {
                $this->pic_proposal_id = $picProposalId;
                
                // Pastikan ada draft proposal riset untuk PIC ini jika belum ada
                $chkProp = $this->db->exec("SELECT id FROM opti_proposal_riset WHERE order_id = ?", array(1 => $orderId));
                if (empty($chkProp)) {
                    $this->db->exec(
                        "INSERT INTO opti_proposal_riset (order_id, pic_penyusun_id, status_proposal, created_at) VALUES (?, ?, 'draft', NOW())",
                        array(1 => $orderId, 2 => $picProposalId)
                    );
                } else {
                    $this->db->exec(
                        "UPDATE opti_proposal_riset SET pic_penyusun_id = ? WHERE order_id = ?",
                        array(1 => $picProposalId, 2 => $orderId)
                    );
                }
            }
        } else {
            $this->status_tinjauan = 'tidak_layak';
            $this->status = 'ditolak';
            $this->alasan_tolak = $alasanPenolakan;
            $this->ditolak_oleh = $userId;
            $this->tanggal_tolak = date('Y-m-d H:i:s');
            $this->status_tolak = 1;
        }
        $this->save();

        return array(
            'order_id'  => $orderId,
            'keputusan' => $keputusan,
            'status'    => $this->status
        );
    }

    /**
     * Ambil data Proposal Riset (Selulosa)
     */
    public function getProposalRiset(int $orderId): ?array {
        $res = $this->db->exec(
            "SELECT p.*, u.nama_user AS pic_nama 
             FROM opti_proposal_riset p 
             LEFT JOIN tb_arsipuser u ON p.pic_penyusun_id = u.id_user 
             WHERE p.order_id = ? 
             ORDER BY p.id DESC LIMIT 1",
            array(1 => $orderId)
        );
        return !empty($res) ? $res[0] : null;
    }

    /**
     * Simpan / Perbarui Proposal Riset (Divisi Selulosa)
     */
    public function simpanProposalSelulosa(int $orderId, array $data, int $userId): array {
        $this->load(array('id = ?', $orderId));
        if ($this->dry()) {
            throw new \Exception("Order Layanan #{$orderId} tidak ditemukan.");
        }

        $picId       = !empty($data['pic_penyusun_id']) ? (int)$data['pic_penyusun_id'] : null;
        $spesialisasi= trim($data['spesialisasi'] ?? '');
        $judul       = trim($data['judul_proposal'] ?? $this->judul_kegiatan);
        $ruangLingkup= trim($data['ruang_lingkup'] ?? '');
        $durasi      = trim($data['durasi_kegiatan'] ?? '3 bulan');
        $biaya       = (float)($data['estimasi_total_biaya'] ?? 0.0);
        $filePath    = trim($data['file_proposal'] ?? '');
        $statusProp  = in_array($data['status_proposal'] ?? '', array('draft', 'diajukan', 'disetujui_ketua', 'disetujui_pimpinan')) ? $data['status_proposal'] : 'draft';
        $catatanRev  = trim($data['catatan_revisi'] ?? '');

        // Cek apakah sudah ada record proposal
        $existing = $this->getProposalRiset($orderId);
        if ($existing) {
            if (empty($filePath) && !empty($existing['file_proposal'])) {
                $filePath = $existing['file_proposal'];
            }
            $this->db->exec(
                "UPDATE opti_proposal_riset SET 
                    pic_penyusun_id = ?, spesialisasi = ?, judul_proposal = ?, ruang_lingkup = ?, 
                    durasi_kegiatan = ?, estimasi_total_biaya = ?, file_proposal = ?, 
                    status_proposal = ?, catatan_revisi = ?,
                    disetujui_pimpinan_at = " . ($statusProp === 'disetujui_pimpinan' ? "NOW()" : "disetujui_pimpinan_at") . " 
                 WHERE id = ?",
                array(
                    1 => $picId,
                    2 => $spesialisasi,
                    3 => $judul,
                    4 => $ruangLingkup,
                    5 => $durasi,
                    6 => $biaya,
                    7 => $filePath,
                    8 => $statusProp,
                    9 => $catatanRev,
                    10 => $existing['id']
                )
            );
        } else {
            $this->db->exec(
                "INSERT INTO opti_proposal_riset 
                (order_id, pic_penyusun_id, spesialisasi, judul_proposal, ruang_lingkup, durasi_kegiatan, estimasi_total_biaya, file_proposal, status_proposal, catatan_revisi, disetujui_pimpinan_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, " . ($statusProp === 'disetujui_pimpinan' ? "NOW()" : "NULL") . ")",
                array(
                    1 => $orderId,
                    2 => $picId,
                    3 => $spesialisasi,
                    4 => $judul,
                    5 => $ruangLingkup,
                    6 => $durasi,
                    7 => $biaya,
                    8 => $filePath,
                    9 => $statusProp,
                    10 => $catatanRev
                )
            );
        }

        // Sinkronisasi ke tabel order_layanan
        $this->estimasi_biaya = $biaya;
        $this->pic_proposal_id = $picId;
        
        if (isset($data['status_rancop'])) {
            $this->status_rancop = in_array($data['status_rancop'], ['draft', 'diskusi', 'deal', 'batal']) ? $data['status_rancop'] : 'draft';
        }
        if (isset($data['log_diskusi_klien'])) {
            $this->log_diskusi_klien = $data['log_diskusi_klien'];
        }
        if (isset($data['tahapan_riset_json'])) {
            $this->tahapan_riset_json = is_string($data['tahapan_riset_json']) ? $data['tahapan_riset_json'] : json_encode($data['tahapan_riset_json']);
        }

        if ($this->status_rancop === 'deal' || $statusProp === 'disetujui_pimpinan' || $statusProp === 'disetujui_ketua') {
            $this->status_proposal_biaya = 'siap_penawaran';
            $this->status = 'baru'; // Maju dari permintaan_masuk ke baru / siap penawaran
        } elseif ($statusProp === 'diajukan') {
            $this->status_proposal_biaya = 'menunggu_approval';
        } else {
            $this->status_proposal_biaya = 'draft';
        }
        $this->save();

        return array(
            'order_id'        => $orderId,
            'estimasi_biaya'  => $biaya,
            'status_proposal' => $statusProp,
            'status_rancop'   => $this->status_rancop
        );
    }

    /**
     * Ambil rincian kalkulasi uji laboratorium lingkungan
     */
    public function getKalkulasiLingkungan(int $orderId): array {
        return $this->db->exec(
            "SELECT k.*, m.nama_metode AS master_metode_nama, m.durasi_nilai AS master_durasi_nilai 
             FROM opti_kalkulasi_uji_lingkungan k 
             LEFT JOIN metode_uji m ON k.metode_uji_id = m.id 
             WHERE k.order_id = ? 
             ORDER BY k.id ASC",
            array(1 => $orderId)
        );
    }

    /**
     * Simpan Kalkulasi Pengujian Multi-Metode (Divisi Lingkungan)
     */
    public function simpanKalkulasiLingkungan(int $orderId, array $items, float $diskon, ?string $tglSampel, int $userId): array {
        $this->load(array('id = ?', $orderId));
        if ($this->dry()) {
            throw new \Exception("Order Layanan #{$orderId} tidak ditemukan.");
        }

        // Hapus item kalkulasi lama
        $this->db->exec("DELETE FROM opti_kalkulasi_uji_lingkungan WHERE order_id = ?", array(1 => $orderId));

        $totalBruto = 0.0;
        $maxDurasiBulan = 1;

        foreach ($items as $item) {
            $namaUji    = trim($item['nama_pengujian'] ?? '');
            if (empty($namaUji)) continue;

            $subLayanan = in_array($item['sub_layanan'] ?? '', array('uji_laboratorium', 'lpv', 'kajian_lab', 'konsultansi')) ? $item['sub_layanan'] : 'uji_laboratorium';
            $metodeId   = !empty($item['metode_uji_id']) ? (int)$item['metode_uji_id'] : null;
            $standar    = trim($item['standar_rujukan'] ?? '');
            $tarif      = (float)($item['tarif_per_sampel'] ?? 0.0);
            $jumlah     = max(1, (int)($item['jumlah_sampel'] ?? 1));
            $totalItem  = $tarif * $jumlah;
            $durasiBulan= max(1, (int)($item['durasi_bulan'] ?? 1));
            $isSub      = !empty($item['is_subkontrak']) ? 1 : 0;
            $labEks     = !empty($item['lab_eksternal_id']) ? (int)$item['lab_eksternal_id'] : null;

            $totalBruto += $totalItem;
            if ($durasiBulan > $maxDurasiBulan) {
                $maxDurasiBulan = $durasiBulan;
            }

            $this->db->exec(
                "INSERT INTO opti_kalkulasi_uji_lingkungan 
                (order_id, sub_layanan, metode_uji_id, nama_pengujian, standar_rujukan, tarif_per_sampel, jumlah_sampel, total_biaya_item, durasi_bulan, is_subkontrak, lab_eksternal_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array(
                    1 => $orderId,
                    2 => $subLayanan,
                    3 => $metodeId,
                    4 => $namaUji,
                    5 => $standar,
                    6 => $tarif,
                    7 => $jumlah,
                    8 => $totalItem,
                    9 => $durasiBulan,
                    10 => $isSub,
                    11 => $labEks
                )
            );
        }

        $totalNetto = max(0.0, $totalBruto - $diskon);

        // Update ke order_layanan
        $this->estimasi_biaya = $totalNetto;
        $this->diskon_penawaran = $diskon;
        if (!empty($tglSampel)) {
            $this->tanggal_terima_sampel = $tglSampel;
        }
        $this->status_proposal_biaya = 'siap_penawaran';
        $this->save();

        return array(
            'order_id'         => $orderId,
            'total_bruto'      => $totalBruto,
            'diskon'           => $diskon,
            'total_netto'      => $totalNetto,
            'max_durasi_bulan' => $maxDurasiBulan
        );
    }

    /**
     * Ambil daftar personil spesialis untuk penunjukan PIC Proposal / Proyek
     */
    public static function getPICSpesialisasiList(\DB\SQL $db, ?string $divisi = null): array {
        $sql = "SELECT DISTINCT u.id_user, u.login, u.nama_user, 
                       'tim_kerja' AS role_opti, 
                       CASE 
                           WHEN u.si_opti LIKE '%lingkungan%' THEN 'lingkungan'
                           WHEN u.si_opti LIKE '%selulosa%' THEN 'selulosa'
                           WHEN m.jenis_layanan_opti IS NOT NULL THEN m.jenis_layanan_opti
                           ELSE 'semua'
                       END AS spesialisasi 
                FROM tb_arsipuser u 
                LEFT JOIN opti_user_map m ON u.id_user = m.id_user 
                WHERE (u.si_opti LIKE 'tim_kerja%' OR m.role_opti = 'tim_kerja')
                  AND (u.status = 1 OR u.status = '1' OR u.status = 'aktif')";
        
        $params = array();
        if (!empty($divisi) && in_array($divisi, array('selulosa', 'lingkungan'))) {
            $sql .= " AND (u.si_opti LIKE ? OR m.jenis_layanan_opti = ? OR m.jenis_layanan_opti = 'semua' OR u.si_opti = 'tim_kerja') ";
            $params[1] = "%{$divisi}%";
            $params[2] = $divisi;
        }

        $sql .= " ORDER BY u.nama_user ASC";
        return $db->exec($sql, $params);
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
        // Hapus tinjauan & proposal
        $this->db->exec("DELETE FROM opti_tinjauan_kelayakan WHERE order_id = ?", array(1 => $id));
        $this->db->exec("DELETE FROM opti_proposal_riset WHERE order_id = ?", array(1 => $id));
        $this->db->exec("DELETE FROM opti_kalkulasi_uji_lingkungan WHERE order_id = ?", array(1 => $id));

        $this->erase();
        return true;
    }
}

