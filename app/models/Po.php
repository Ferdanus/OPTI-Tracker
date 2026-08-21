<?php

/**
 * Model Po (Petunjuk Operasional)
 * Mengelola dokumen kerja PO, auto-numbering aman, Map Kendali berjenjang, jadwal tim, dan evaluasi hasil kerja
 * 
 * TODO: Konfirmasi ke user apakah default auto-isi realisasi tanggal selesai dari upload dokumen atau tetap manual.
 * TODO: Konfirmasi ke user apakah penandatanganan PKS terjadi di dalam tahap penyusunan PO atau di awal pelaksanaan.
 * TODO: Konfirmasi apakah approver PO sama untuk Selulosa dan Lingkungan atau berbeda.
 */
class Po extends \DB\SQL\Mapper {

    // Urutan status linear state machine sesuai alur SOP & rekap PO
    public static $URUTAN_STATUS = array(
        'belum_upload'   => 'Menunggu Upload Dokumen PO',
        'sudah_upload'   => 'Dokumen Terunggah (Menunggu Verifikasi)',
        'on_proses'      => 'Sedang Dikerjakan (On Proses)',
        'kembali_selesai'=> 'Pekerjaan Selesai (Laporan Terbit)'
    );

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'po');
    }

    /**
     * Konversi angka bulan (1-12) ke angka Romawi (I-XII)
     */
    public static function bulanKeRomawi(int $bulan): string {
        $romawi = array(
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        );
        return $romawi[$bulan] ?? 'I';
    }

    /**
     * Auto-generate nomor PO dengan format resmi:
     * {urut}/PO/BBSPJIS/{bulan_romawi}/{tahun}
     */
    public function generateNomorPo(): string {
        $bulanSekarang = (int)date('n');
        $tahunSekarang = (int)date('Y');
        $bulanRomawi   = self::bulanKeRomawi($bulanSekarang);
        $suffix        = "/PO/BBSPJIS/{$bulanRomawi}/{$tahunSekarang}";

        $rows = $this->db->exec(
            "SELECT nomor_po FROM po WHERE nomor_po LIKE ?",
            array(1 => '%' . $suffix)
        );

        $maxUrut = 0;
        foreach ($rows as $r) {
            $parts = explode('/', $r['nomor_po']);
            $num = (int)($parts[0] ?? 0);
            if ($num > $maxUrut) {
                $maxUrut = $num;
            }
        }

        $nextUrut = $maxUrut + 1;
        $nomorPo = sprintf('%02d', $nextUrut) . $suffix;

        while (($this->db->exec("SELECT COUNT(*) AS c FROM po WHERE nomor_po = ?", array(1 => $nomorPo))[0]['c'] ?? 0) > 0) {
            $nextUrut++;
            $nomorPo = sprintf('%02d', $nextUrut) . $suffix;
        }

        return $nomorPo;
    }

    /**
     * Menghitung status keterlambatan (Overdue Detection)
     */
    public static function hitungOverdue(?string $targetSelesai, ?string $realisasiSelesai, string $status, ?string $spmLayanan = null): array {
        if (empty($targetSelesai)) {
            return array(
                'is_overdue'   => false,
                'days'         => 0,
                'label'        => 'Target Belum Diset',
                'badge_class'  => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-20'
            );
        }

        $targetTime = strtotime($targetSelesai);
        $todayTime  = strtotime(date('Y-m-d'));

        if ($status === 'kembali_selesai') {
            if (!empty($realisasiSelesai)) {
                $realisasiTime = strtotime($realisasiSelesai);
                $diffDays = (int)floor(($realisasiTime - $targetTime) / 86400);

                if ($diffDays > 0) {
                    return array(
                        'is_overdue'  => true,
                        'days'        => $diffDays,
                        'label'       => "Selesai (Telat {$diffDays} hari)",
                        'badge_class' => 'bg-warning bg-opacity-10 text-dark border border-warning'
                    );
                } else {
                    return array(
                        'is_overdue'  => false,
                        'days'        => 0,
                        'label'       => 'Tepat Waktu',
                        'badge_class' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-20'
                    );
                }
            } else {
                return array(
                    'is_overdue'  => false,
                    'days'        => 0,
                    'label'       => 'Selesai',
                    'badge_class' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-20'
                );
            }
        } else {
            $diffDays = (int)floor(($todayTime - $targetTime) / 86400);

            if ($diffDays > 0) {
                return array(
                    'is_overdue'  => true,
                    'days'        => $diffDays,
                    'label'       => "Terlambat {$diffDays} hari",
                    'badge_class' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'
                );
            } else {
                $sisaHari = abs($diffDays);
                return array(
                    'is_overdue'  => false,
                    'days'        => $sisaHari,
                    'label'       => "Sisa {$sisaHari} hari",
                    'badge_class' => 'bg-light text-muted border'
                );
            }
        }
    }

    /**
     * Membuat PO baru otomatis saat Order Layanan disetujui
     */
    public function buatDariOrder(int $orderId, string $nomorPoManual = '', float $biaya = 0.0, array $dataTambahan = array()): int {
        $nomorPo = trim($nomorPoManual);
        if (empty($nomorPo)) {
            $nomorPo = $this->generateNomorPo();
        }

        $targetMulai   = $dataTambahan['target_mulai'] ?? date('Y-m-d');
        $targetSelesai = $dataTambahan['target_selesai'] ?? date('Y-m-d', strtotime('+1 month'));

        $this->db->exec(
            "INSERT INTO po (order_id, nomor_po, biaya, status, tanggal_keluar, target_mulai, target_selesai, created_at)
             VALUES (?, ?, ?, 'belum_upload', NOW(), ?, ?, NOW())",
            array(
                1 => $orderId,
                2 => $nomorPo,
                3 => $biaya,
                4 => $targetMulai,
                5 => $targetSelesai
            )
        );

        $poId = (int)$this->db->lastInsertId();

        // Jika ada nilai biaya awal, buatkan 1 item rincian anggaran awal
        if ($biaya > 0) {
            $rabModel = new PoRincianAnggaran($this->db);
            $rabModel->tambahItem($poId, array(
                'kategori'  => 'Jasa Layanan/Pengujian',
                'deskripsi' => 'Alokasi awal biaya pengujian & analisis lab',
                'nominal'   => $biaya
            ));
        }

        // Catat ke log audit trail
        $logModel = new PoLogStatus($this->db);
        $logModel->catat(
            $poId,
            null,
            'belum_upload',
            'PO otomatis diterbitkan dari Order Layanan yang telah disetujui.'
        );

        return $poId;
    }

    /**
     * Ambil PO berdasarkan ID
     */
    public function getById(int $id) {
        $this->load(array('id = ?', $id));
        return $this->dry() ? null : $this;
    }

    /**
     * Ambil detail PO lengkap dengan data Order Layanan, Customer, dan Kontrak PKS
     */
    public function getDetail(int $id): ?array {
        $hasil = $this->db->exec(
            "SELECT p.*, 
                    o.nomor_order, o.judul_kegiatan, o.deskripsi AS deskripsi_order, o.tanggal_masuk, o.status AS status_order, 
                    o.jenis_layanan_opti, o.spm_layanan, o.lokasi_pelaksanaan, o.lab_internal, o.lokasi_lapangan,
                    o.tipe_data_sampel, o.jenis_sampel, o.volume_berat, o.karakteristik_serat, o.karakteristik_kimia,
                    o.jumlah_pekerjaan, o.estimasi_biaya,
                    c.id_customer, c.nmcustomer AS nama_perusahaan, c.pt_cv, c.contactperson AS pic, c.notelpcustomer AS telepon, c.emailcustomer AS email, c.alamatcustomer AS alamat,
                    c.contactperson_opti, c.nohpcontactperson_opti,
                    kp.id AS kontrak_id, kp.nomor_pks_klien, kp.nomor_pks_bbspjis, kp.status_ttd AS kontrak_status, kp.nilai_kontrak, kp.nomor_va,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id AND status_verifikasi = 'terverifikasi'), 0) AS total_terbayar
             FROM po p
             JOIN order_layanan o ON p.order_id = o.id
             JOIN tb_customer c ON o.id_customer = c.id_customer
             LEFT JOIN kontrak_pks kp ON p.id = kp.po_id
             WHERE p.id = ?",
            array(1 => $id)
        );

        return $hasil[0] ?? null;
    }

    /**
     * Ambil daftar semua PO dengan filter lengkap
     */
    public function allWithRelasi(string $filterBulan = '', string $filterTahun = '', string $filterStatus = '', string $filterJenisLayanan = '', string $search = '', string $filterOverdue = ''): array {
        $sql = "SELECT p.*, 
                       o.nomor_order, o.judul_kegiatan, o.jenis_layanan_opti, o.spm_layanan, o.lokasi_pelaksanaan, o.lab_internal, o.jenis_sampel, o.tanggal_masuk,
                       c.nmcustomer AS nama_perusahaan, c.pt_cv, c.contactperson AS pic,
                       kp.id AS kontrak_id, kp.nomor_pks_bbspjis, kp.status_ttd AS kontrak_status,
                       COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id AND status_verifikasi = 'terverifikasi'), 0) AS total_terbayar
                FROM po p
                JOIN order_layanan o ON p.order_id = o.id
                JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN kontrak_pks kp ON p.id = kp.po_id
                WHERE 1=1";

        $params = array();
        $idx = 1;

        if (!empty($filterBulan)) {
            $sql .= " AND MONTH(p.created_at) = ?";
            $params[$idx++] = (int)$filterBulan;
        }

        if (!empty($filterTahun)) {
            $sql .= " AND YEAR(p.created_at) = ?";
            $params[$idx++] = (int)$filterTahun;
        }

        if (!empty($filterStatus)) {
            $sql .= " AND p.status = ?";
            $params[$idx++] = $filterStatus;
        }

        if (!empty($filterJenisLayanan)) {
            $sql .= " AND o.jenis_layanan_opti = ?";
            $params[$idx++] = $filterJenisLayanan;
        }

        if (!empty($search)) {
            $sql .= " AND (p.nomor_po LIKE ? OR o.judul_kegiatan LIKE ? OR c.nmcustomer LIKE ? OR p.tim_kerja LIKE ? OR o.nomor_order LIKE ?)";
            $wildcard = "%{$search}%";
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
        }

        $sql .= " ORDER BY p.id DESC";

        $daftar = $this->db->exec($sql, $params);

        foreach ($daftar as &$item) {
            $item['overdue_info'] = self::hitungOverdue(
                $item['target_selesai'],
                $item['realisasi_selesai'],
                $item['status'],
                $item['spm_layanan']
            );
        }

        if ($filterOverdue === 'telat') {
            $daftar = array_filter($daftar, function($item) {
                return !empty($item['overdue_info']['is_overdue']);
            });
        }

        return $daftar;
    }

    /**
     * Update data PO secara langsung
     */
    public function updateData(int $id, array $data): bool {
        $po = $this->getById($id);
        if (!$po) {
            throw new \Exception("PO #{$id} tidak ditemukan.");
        }

        $statusLama = $po->status;
        $statusBaru = $data['status'] ?? $statusLama;

        $fields = array(
            'tim_kerja'                  => trim($data['tim_kerja'] ?? $po->tim_kerja),
            'status'                     => $statusBaru,
            'tanggal_keluar'             => !empty($data['tanggal_keluar']) ? $data['tanggal_keluar'] : $po->tanggal_keluar,
            'tanggal_kembali'            => !empty($data['tanggal_kembali']) ? $data['tanggal_kembali'] : $po->tanggal_kembali,
            'target_mulai'               => !empty($data['target_mulai']) ? $data['target_mulai'] : $po->target_mulai,
            'target_selesai'             => !empty($data['target_selesai']) ? $data['target_selesai'] : $po->target_selesai,
            'realisasi_selesai'          => !empty($data['realisasi_selesai']) ? $data['realisasi_selesai'] : $po->realisasi_selesai,
            'auto_realisasi_dari_upload' => isset($data['auto_realisasi_dari_upload']) ? (int)$data['auto_realisasi_dari_upload'] : (int)$po->auto_realisasi_dari_upload,
            'evaluasi_status'            => $data['evaluasi_status'] ?? $po->evaluasi_status,
            'notulen_evaluasi'           => trim($data['notulen_evaluasi'] ?? $po->notulen_evaluasi),
            'tgl_evaluasi'               => !empty($data['tgl_evaluasi']) ? $data['tgl_evaluasi'] : $po->tgl_evaluasi
        );

        $setParts = array();
        $params = array();
        $idx = 1;
        foreach ($fields as $key => $val) {
            $setParts[] = "{$key} = ?";
            $params[$idx++] = $val;
        }
        $params[$idx] = $id;

        $this->db->exec("UPDATE po SET " . implode(', ', $setParts) . " WHERE id = ?", $params);

        // Jika status berubah atau ada catatan, catat ke log
        $catatan = trim($data['catatan'] ?? '');
        if ($statusLama !== $statusBaru || !empty($catatan)) {
            $logModel = new PoLogStatus($this->db);
            $logModel->catat(
                $id,
                $statusLama,
                $statusBaru,
                $catatan ?: 'Pembaruan data dan status dokumen PO.'
            );
        }

        return true;
    }

    /**
     * Update tahap approval Map Kendali berjenjang
     */
    public function updateMapKendali(int $id, string $stage): bool {
        $allowedStages = array(
            'app_proposal', 'app_proposal_val',
            'app_kontrak', 'app_kontrak_val',
            'app_po_adm', 'app_po_mitra', 'app_po_ppk', 'app_po_kabag',
            'app_dist_tu', 'app_dist_kepeg', 'app_dist_keu'
        );

        if (!in_array($stage, $allowedStages)) {
            throw new \Exception("Tahapan approval '{$stage}' tidak valid.");
        }

        $dateCol = $stage . '_date';
        $this->db->exec(
            "UPDATE po SET {$stage} = 1, {$dateCol} = NOW() WHERE id = ?",
            array(1 => $id)
        );

        $logModel = new PoLogStatus($this->db);
        $logModel->catat($id, null, 'map_kendali', "Tahapan Map Kendali '{$stage}' telah diverifikasi/disetujui.");

        return true;
    }

    /**
     * Hapus PO beserta seluruh data relasi (RAB, Jadwal, Pembayaran, Log, Kontrak)
     */
    public function hapus(int $id): bool {
        $this->db->exec("DELETE FROM opti_po_sop_progress WHERE po_id = ?", array(1 => $id));
        $this->db->exec("DELETE FROM po_rincian_anggaran WHERE po_id = ?", array(1 => $id));
        $this->db->exec("DELETE FROM opti_po_jadwal_kerja WHERE po_id = ?", array(1 => $id));
        $this->db->exec("DELETE FROM po_log_status WHERE po_id = ?", array(1 => $id));
        $this->db->exec("DELETE FROM kontrak_pks WHERE po_id = ?", array(1 => $id));
        $this->db->exec("DELETE FROM po WHERE id = ?", array(1 => $id));
        return true;
    }
}
