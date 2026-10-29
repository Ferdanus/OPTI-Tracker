<?php

/**
 * Model OrderLayanan
 * Mengelola permohonan layanan OPTI dari customer (tb_customer), standar SPM, 6 Lab balai, dan spesifikasi sampel
 */
class OrderLayanan extends \DB\SQL\Mapper {
    /** true = daftar hanya memuat order yang PO-nya sudah disetujui Ketua Tim dan dikirim ke Humas (dipakai untuk role Humas). */
    protected $hanyaPoHumas = false;
    public function batasiUntukHumas($ya = true) { $this->hanyaPoHumas = (bool) $ya; return $this; }


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
        if (date_default_timezone_get() !== 'Asia/Jakarta') {
            date_default_timezone_set('Asia/Jakarta');
        }
        self::ensureColumns($db);
        parent::__construct($db, 'order_layanan');
    }

    /**
     * Pastikan kolom nomor_permintaan tersedia di tabel order_layanan
     */
    public static function ensureColumns(\DB\SQL $db): void {
        static $checked = false;
        if ($checked) return;
        try {
            $cols = [
                'nomor_permintaan' => 'VARCHAR(50) NULL AFTER id',
'is_unlocked_edit' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'unlocked_by' => 'INT(11) NULL',
                'unlocked_at' => 'DATETIME NULL',
                'unlocked_until' => 'DATETIME NULL',
                'unlocked_days' => 'INT(11) NULL DEFAULT 3',
                'unlocked_reason' => 'VARCHAR(255) NULL',
                // durasi SPM (dipakai simpan proposal, penawaran, dan perhitungan jadwal); dibuat otomatis kalau belum ada di database
                'spm_durasi_nilai' => 'INT(11) NULL',
                'spm_durasi_satuan' => "VARCHAR(50) NULL DEFAULT 'Hari Kerja'"
            ];
            foreach ($cols as $col => $def) {
                try {
                    $db->exec("ALTER TABLE order_layanan ADD COLUMN {$col} {$def}");
                } catch (\Exception $e) {}
            }
            $checked = true;
        } catch (\Exception $e) {}
    }

    private static $cachedHolidays = null;

    /**
     * Ambil daftar tanggal merah / hari libur resmi (di-cache per request)
     */
    public function getDaftarLibur(): array {
        if (self::$cachedHolidays !== null) {
            return self::$cachedHolidays;
        }

        $holidays = [];
        try {
            $rowsTgl = $this->db->exec("SELECT tanggal_libur FROM tb_tanggal_libur");
            if (!empty($rowsTgl)) {
                foreach ($rowsTgl as $r) {
                    if (!empty($r['tanggal_libur'])) {
                        $tgl = date('Y-m-d', strtotime($r['tanggal_libur']));
                        $holidays[$tgl] = 'Hari Libur Resmi / Cuti Bersama';
                    }
                }
            }
        } catch (\Exception $e) {
            try {
                $rowsTgl = $this->db->exec("SELECT tanggal_libur FROM sil2020.tb_tanggal_libur");
                if (!empty($rowsTgl)) {
                    foreach ($rowsTgl as $r) {
                        if (!empty($r['tanggal_libur'])) {
                            $tgl = date('Y-m-d', strtotime($r['tanggal_libur']));
                            $holidays[$tgl] = 'Hari Libur Resmi / Cuti Bersama';
                        }
                    }
                }
            } catch (\Exception $e2) {}
        }

        try {
            $rowsHari = $this->db->exec("SELECT tanggal, keterangan FROM tb_hari_libur");
            if (!empty($rowsHari)) {
                foreach ($rowsHari as $r) {
                    if (!empty($r['tanggal'])) {
                        $tgl = date('Y-m-d', strtotime($r['tanggal']));
                        $holidays[$tgl] = !empty($r['keterangan']) ? $r['keterangan'] : ($holidays[$tgl] ?? 'Hari Libur Nasional');
                    }
                }
            }
        } catch (\Exception $e) {
            try {
                $rowsHari = $this->db->exec("SELECT tanggal, keterangan FROM sil2020.tb_hari_libur");
                if (!empty($rowsHari)) {
                    foreach ($rowsHari as $r) {
                        if (!empty($r['tanggal'])) {
                            $tgl = date('Y-m-d', strtotime($r['tanggal']));
                            $holidays[$tgl] = !empty($r['keterangan']) ? $r['keterangan'] : ($holidays[$tgl] ?? 'Hari Libur Nasional');
                        }
                    }
                }
            } catch (\Exception $e2) {}
        }

        self::$cachedHolidays = $holidays;
        return self::$cachedHolidays;
    }

    /**
     * Hitung sisa hari kerja menuju tanggal deadline (mengecualikan Sabtu, Minggu, dan tanggal merah)
     */
    public function hitungSisaHariKerja(string $tanggalDeadline, ?string $tanggalHariIni = null): int {
        $todayStr = !empty($tanggalHariIni) ? date('Y-m-d', strtotime($tanggalHariIni)) : date('Y-m-d');
        $deadlineStr = date('Y-m-d', strtotime($tanggalDeadline));

        $tToday = strtotime($todayStr);
        $tDeadline = strtotime($deadlineStr);

        if (!$tDeadline || $tToday === $tDeadline) {
            return 0;
        }

        $holidays = $this->getDaftarLibur();

        if ($tToday < $tDeadline) {
            $curr = strtotime('+1 day', $tToday);
            $sisa = 0;
            while ($curr <= $tDeadline) {
                $dStr = date('Y-m-d', $curr);
                $dow = (int)date('N', $curr);
                if ($dow < 6 && !isset($holidays[$dStr])) {
                    $sisa++;
                }
                $curr = strtotime('+1 day', $curr);
            }
            return $sisa;
        } else {
            $curr = strtotime('+1 day', $tDeadline);
            $lewat = 0;
            while ($curr <= $tToday) {
                $dStr = date('Y-m-d', $curr);
                $dow = (int)date('N', $curr);
                if ($dow < 6 && !isset($holidays[$dStr])) {
                    $lewat++;
                }
                $curr = strtotime('+1 day', $curr);
            }
            return -$lewat;
        }
    }

    /**
     * Resolusi durasi pengerjaan dan perhitungan mundur (countdown) sisa hari kerja terhadap deadline
     */
    public function resolveDurasiInfo(array $r): array {
        $durasiDitentukan = (
            in_array($r['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan']) ||
            in_array($r['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) ||
            !empty($r['tanggal_deadline_spm']) ||
            !empty($r['tanggal_spm_mulai']) ||
            !empty($r['tanggal_terima_sampel'])
        );

        $durasiTotalNilai = 30;
        $durasiSatuan = 'Hari Kerja';

        if (!empty($r['spm_durasi_nilai']) && (!empty($r['tanggal_spm_mulai']) || !empty($r['tanggal_terima_sampel']))) {
            $durasiTotalNilai = (int)$r['spm_durasi_nilai'];
            $durasiSatuan = !empty($r['spm_durasi_satuan']) ? $r['spm_durasi_satuan'] : 'Hari Kerja';
        } elseif (!empty($r['proposal_durasi']) && preg_match('/(\d+)/', $r['proposal_durasi'], $mProp)) {
            $durasiTotalNilai = (int)$mProp[1];
            if (stripos($r['proposal_durasi'], 'kalender') !== false) {
                $durasiSatuan = 'Hari Kalender';
            } elseif (stripos($r['proposal_durasi'], 'bulan') !== false) {
                $durasiSatuan = 'Bulan';
            } elseif (stripos($r['proposal_durasi'], 'minggu') !== false) {
                $durasiSatuan = 'Minggu';
            } else {
                $durasiSatuan = 'Hari Kerja';
            }
        } elseif (!empty($r['spm_layanan']) && strtolower(trim($r['spm_layanan'])) !== 'lainnya' && preg_match('/(\d+)/', $r['spm_layanan'], $mSpm)) {
            $durasiTotalNilai = (int)$mSpm[1];
            $durasiSatuan = !empty($r['spm_durasi_satuan']) ? $r['spm_durasi_satuan'] : 'Hari Kerja';
        } elseif (!empty($r['spm_durasi_nilai']) && (int)$r['spm_durasi_nilai'] > 0) {
            $durasiTotalNilai = (int)$r['spm_durasi_nilai'];
            $durasiSatuan = !empty($r['spm_durasi_satuan']) ? $r['spm_durasi_satuan'] : 'Hari Kerja';
        } elseif (!empty($r['durasi_hari_kerja']) && (int)$r['durasi_hari_kerja'] > 0) {
            $durasiTotalNilai = (int)$r['durasi_hari_kerja'];
            $durasiSatuan = 'Hari Kerja';
        }

        $durasiAwalLabel = "{$durasiTotalNilai} {$durasiSatuan}";

        $isOrderSelesai = (
            (!empty($r['bast_id']) && in_array($r['status_bast'] ?? '', ['disetujui', 'selesai', 'terbit'])) ||
            ($r['status'] ?? '') === 'selesai' ||
            ($r['status_pelaksanaan'] ?? '') === 'laporan_selesai'
        );

        $deadlineEfektif = null;
        if (!empty($r['tanggal_deadline_spm'])) {
            $deadlineEfektif = date('Y-m-d', strtotime($r['tanggal_deadline_spm']));
        } elseif (!empty($r['po_target_selesai']) || !empty($r['target_po'])) {
            $deadlineEfektif = date('Y-m-d', strtotime($r['po_target_selesai'] ?? $r['target_po']));
        } elseif ($durasiDitentukan) {
            $candidates = [
                $r['tanggal_spm_mulai'] ?? null,
                $r['tanggal_terima_sampel'] ?? null,
                $r['po_target_mulai'] ?? null,
                $r['tanggal_bayar_terakhir'] ?? null,
                (($r['status_respon_klien'] ?? '') === 'deal') ? ($r['penawaran_updated_at'] ?? ($r['penawaran_tanggal_surat'] ?? null)) : null,
                $r['proposal_disetujui_at'] ?? null,
            ];
            $tglAcuan = null;
            foreach ($candidates as $cand) {
                if (!empty($cand)) {
                    $tglAcuan = $cand;
                    break;
                }
            }

            if (!empty($tglAcuan)) {
                $tglMulaiYmd = date('Y-m-d', strtotime($tglAcuan));
                $calc = $this->hitungDeadlineFleksibel($tglMulaiYmd, $durasiTotalNilai, $durasiSatuan);
                $deadlineEfektif = $calc['tanggal_deadline_spm'];
            }
        }

        $sisaHari = null;
        $durasiTampil = $durasiAwalLabel;
        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
        $tooltip = "Waktu pengerjaan disetujui: {$durasiAwalLabel}";

        if ($isOrderSelesai) {
            $durasiTampil = $durasiAwalLabel;
            $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
            $tooltip = "Order telah selesai (Target durasi: {$durasiAwalLabel})";
        } elseif ($durasiDitentukan && !empty($deadlineEfektif)) {
            if (stripos($durasiSatuan, 'kerja') !== false) {
                $sisaHari = $this->hitungSisaHariKerja($deadlineEfektif);
                if ($sisaHari > $durasiTotalNilai) {
                    $sisaHari = $durasiTotalNilai;
                }
                $satuanPendek = 'Hari Kerja';
            } else {
                $sisaHari = (int)ceil((strtotime($deadlineEfektif) - strtotime(date('Y-m-d'))) / 86400);
                $satuanPendek = 'Hari Kalender';
            }

            $tglDeadlineFmt = date('d/m/Y', strtotime($deadlineEfektif));
            if ($sisaHari > 0) {
                $durasiTampil = "{$sisaHari} {$satuanPendek}";
                if ($sisaHari <= 5) {
                    $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                } else {
                    $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                }
                $tooltip = "Sisa {$sisaHari} dari {$durasiAwalLabel} • Target Selesai: {$tglDeadlineFmt}";
            } elseif ($sisaHari === 0) {
                $durasiTampil = "0 {$satuanPendek} (Hari Ini)";
                $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                $tooltip = "Batas akhir pengerjaan hari ini ({$tglDeadlineFmt}) • Durasi awal: {$durasiAwalLabel}";
            } else {
                $lewat = abs($sisaHari);
                $durasiTampil = "Lewat {$lewat} {$satuanPendek}";
                $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                $tooltip = "Melewati batas waktu {$lewat} {$satuanPendek} (Target: {$tglDeadlineFmt})";
            }
        }

        return [
            'durasi_ditentukan'        => $durasiDitentukan,
            'durasi_awal_label'        => $durasiAwalLabel,
            'durasi_total_nilai'       => $durasiTotalNilai,
            'durasi_satuan_efektif'    => $durasiSatuan,
            'tanggal_deadline_efektif' => $deadlineEfektif,
            'sisa_hari_kerja'          => $sisaHari,
            'durasi_tampil'            => $durasiTampil,
            'durasi_badge_class'       => $badgeClass,
            'durasi_tooltip'           => $tooltip,
            'is_order_selesai'         => $isOrderSelesai,
        ];
    }

    /**
     * Hitung deadline hari kerja berbasis tb_hari_libur (H+1 exclude weekend & holidays)
     */
    public function hitungDeadlineHariKerja(string $tanggalSampel, int $durasiHariKerja = 30): array {
        $time = strtotime($tanggalSampel);
        if (!$time) {
            $time = time();
            $tanggalSampel = date('Y-m-d', $time);
        }

        if ($durasiHariKerja <= 0) {
            $durasiHariKerja = 30;
        }

        $holidays = $this->getDaftarLibur();

        // Perhitungan H+1 hari kerja (Pak Hendy: Hari pertama pengerjaan adalah hari kerja berikutnya)
        $curr = strtotime('+1 day', $time);
        $hariTerhitung = 0;
        $liburDilewati = [];
        $akhirPekanDilewati = 0;

        while ($hariTerhitung < $durasiHariKerja) {
            $dateStr = date('Y-m-d', $curr);
            $dayOfWeek = (int)date('N', $curr); // 1 = Senin, ..., 6 = Sabtu, 7 = Minggu

            if ($dayOfWeek >= 6) {
                $akhirPekanDilewati++;
            } elseif (isset($holidays[$dateStr])) {
                $liburDilewati[] = [
                    'tanggal' => $dateStr,
                    'keterangan' => $holidays[$dateStr]
                ];
            } else {
                $hariTerhitung++;
            }

            if ($hariTerhitung < $durasiHariKerja) {
                $curr = strtotime('+1 day', $curr);
            }
        }

        return [
            'tanggal_mulai'       => $tanggalSampel,
            'tanggal_sampel'      => $tanggalSampel,
            'durasi_hari_kerja'   => $durasiHariKerja,
            'durasi_nilai'        => $durasiHariKerja,
            'durasi_satuan'       => 'Hari Kerja',
            'label_durasi'        => "{$durasiHariKerja} Hari Kerja",
            'tanggal_deadline_spm'=> date('Y-m-d', $curr),
            'libur_dilewati'      => $liburDilewati,
            'akhir_pekan_hari'    => $akhirPekanDilewati
        ];
    }

    /**
     * Hitung deadline SPM fleksibel (Hari Kerja, Hari Kalender, Minggu, Bulan)
     */
    public function hitungDeadlineFleksibel(string $tanggalMulai, int $durasiNilai = 30, string $durasiSatuan = 'Hari Kerja'): array {
        $time = strtotime($tanggalMulai);
        if (!$time) {
            $time = time();
            $tanggalMulai = date('Y-m-d', $time);
        }

        if ($durasiNilai <= 0) {
            $durasiNilai = 1;
        }

        $satuanClean = strtolower(trim($durasiSatuan));
        $liburDilewati = [];
        $akhirPekanDilewati = 0;
        $deadlineStr = '';

        if (strpos($satuanClean, 'kerja') !== false) {
            // Hari Kerja: H+1 exclude weekend & holidays
            $res = $this->hitungDeadlineHariKerja($tanggalMulai, $durasiNilai);
            $deadlineStr = $res['tanggal_deadline_spm'];
            $liburDilewati = $res['libur_dilewati'];
            $akhirPekanDilewati = $res['akhir_pekan_hari'];
        } elseif (strpos($satuanClean, 'bulan') !== false) {
            $deadlineStr = date('Y-m-d', strtotime("+{$durasiNilai} months", $time));
        } elseif (strpos($satuanClean, 'minggu') !== false) {
            $deadlineStr = date('Y-m-d', strtotime("+{$durasiNilai} weeks", $time));
        } else {
            // Hari Kalender / Hari Biasa
            $deadlineStr = date('Y-m-d', strtotime("+{$durasiNilai} days", $time));
        }

        return [
            'tanggal_mulai'       => $tanggalMulai,
            'tanggal_sampel'      => $tanggalMulai,
            'durasi_nilai'        => $durasiNilai,
            'durasi_satuan'       => $durasiSatuan,
            'durasi_hari_kerja'   => (strpos($satuanClean, 'kerja') !== false) ? $durasiNilai : null,
            'label_durasi'        => "{$durasiNilai} {$durasiSatuan}",
            'tanggal_deadline_spm'=> $deadlineStr,
            'libur_dilewati'      => $liburDilewati,
            'akhir_pekan_hari'    => $akhirPekanDilewati
        ];
    }

    /**
     * Catat titik awal pengerjaan / aktivasi SPM dengan pilihan trigger (Sampel Diterima, Kick-Off, TTD PKS)
     */
    public function simpanAktivasiSpm(int $orderId, string $tanggalMulai, int $durasiNilai = 30, string $durasiSatuan = 'Hari Kerja', string $triggerTipe = 'sampel_diterima', ?string $catatan = null): array {
        $this->load(['id = ?', $orderId]);
        if ($this->dry()) {
            throw new \Exception("Order #{$orderId} tidak ditemukan.");
        }

        $kalkulasi = $this->hitungDeadlineFleksibel($tanggalMulai, $durasiNilai, $durasiSatuan);
        $deadline = $kalkulasi['tanggal_deadline_spm'];

        $this->tanggal_terima_sampel = $tanggalMulai; // backward compatibility
        $this->tanggal_spm_mulai     = $tanggalMulai;
        $this->spm_trigger_tipe      = $triggerTipe;
        $this->tanggal_deadline_spm  = $deadline;
        $this->durasi_hari_kerja     = (strpos(strtolower($durasiSatuan), 'kerja') !== false) ? $durasiNilai : null;
        $this->spm_durasi_nilai      = $durasiNilai;
        $this->spm_durasi_satuan     = $durasiSatuan;
        $this->spm_layanan           = "{$durasiNilai} {$durasiSatuan}";
        $this->catatan_trigger_spm   = !empty($catatan) ? trim($catatan) : null;
        $this->save();

        // Jika PO sudah ada, sinkronkan juga target_mulai dan target_selesai di tabel po
        if (!empty($this->po_id)) {
            $this->db->exec(
                "UPDATE po SET target_mulai = ?, target_selesai = ? WHERE id = ?",
                [1 => $tanggalMulai, 2 => $deadline, 3 => $this->po_id]
            );
        }

        return $kalkulasi;
    }

    /**
     * Catat penerimaan fisik sampel laboratorium dan hitung otomatis deadline SPM (Legacy wrapper)
     */
    public function simpanPenerimaanSampel(int $orderId, string $tanggalSampel, int $durasiHariKerja = 30): array {
        return $this->simpanAktivasiSpm($orderId, $tanggalSampel, $durasiHariKerja, 'Hari Kerja', 'sampel_diterima');
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
    public function allWithRelasi(string $filterJenis = '', string $filterStatus = '', string $search = '', string $filterTahun = '', string $filterTab = 'aktif', string $filterBulan = ''): array {
        // Kolom po_kegiatan yang dipakai untuk posisi PO + status dibaca di daftar. Hanya kolom yang ada di database yang dipilih
        // (kolom jejak baca ditambahkan lewat migrasi/penyesuaian skema, jadi bisa saja belum ada).
        static $pkAda = null;
        if ($pkAda === null) {
            $pkAda = array();
            try {
                foreach ($this->db->exec('SHOW COLUMNS FROM po_kegiatan') as $kc) { $pkAda[$kc['Field']] = true; }
            } catch (\Exception $e) { $pkAda = array(); }
        }
        $pkKolom = '';
        foreach (array('status', 'status_review', 'ppk_status', 'disetujui_at', 'verifikasi_humas_at', 'tervalidasi_mitra_at', 'validasi_keuangan_at',
                       'reviewed_at', 'minta_dibaca_at', 'revisi_dibaca_at', 'humas_dibaca_at', 'disposisi_humas_at', 'ppk_dibaca_at', 'mitra_dibaca_at',
                       'ronde_ppk', 'ppk_status_at') as $kcol) {
            if (isset($pkAda[$kcol])) { $pkKolom .= ' pk.' . $kcol . ' AS pk_' . $kcol . ','; }
        }
        // Nomor putaran perbaikan dari Ketua Tim (catatan asal 'katim') pada ronde PPK BLU yang berjalan; kolomnya baru ada setelah fitur dipakai
        static $rcAda = null;
        if ($rcAda === null) {
            $rcAda = false;
            try {
                $rcKol = array();
                foreach ($this->db->exec('SHOW COLUMNS FROM po_revisi_catatan') as $kc) { $rcKol[$kc['Field']] = true; }
                $rcAda = isset($rcKol['asal']) && isset($rcKol['putaran']);
            } catch (\Exception $e) { $rcAda = false; }
        }
        if ($rcAda && isset($pkAda['ronde_ppk'])) {
            $pkKolom .= " (SELECT COALESCE(MAX(rc.putaran), 0) FROM po_revisi_catatan rc WHERE rc.po_id = pk.id AND rc.tahap = 'ppk' AND rc.asal = 'katim' AND rc.ronde = pk.ronde_ppk AND rc.state <> 'draf') AS pk_putaran_katim,";
        }
        $sql = "SELECT o.*, 
                       c.nmcustomer AS nama_perusahaan, c.pt_cv, 
                       COALESCE(NULLIF(c.contactperson_opti, ''), NULLIF(c.contactperson, ''), c.nama_pribadi, '-') AS pic,
                       COALESCE(NULLIF(c.nohpcontactperson_opti, ''), NULLIF(c.nohpcontactperson, ''), c.notelpcustomer, '-') AS telepon,
                       COALESCE(NULLIF(c.emailcustomer, ''), c.emailcustomer_sertifikasi, '-') AS email,
                       COALESCE(NULLIF(c.alamatcustomer_baru, ''), c.alamatcustomer, '-') AS alamat,
                       p.id AS po_id, p.nomor_po, p.status AS status_po, p.biaya AS biaya_po,
                       p.target_mulai AS po_target_mulai, p.target_selesai AS po_target_selesai,
                       pk.id AS pk_id," . $pkKolom . "
                       sp.id AS surat_penawaran_id, sp.status_respon_klien, sp.nomor_surat AS nomor_penawaran,
                       sp.tanggal_surat AS penawaran_tanggal_surat, sp.updated_at AS penawaran_updated_at,
                       pr.durasi_kegiatan AS proposal_durasi, pr.status_proposal,
                       pr.file_proposal, pr.estimasi_total_biaya, pr.disetujui_ketua_at AS proposal_disetujui_at,
                       b.id AS bast_id, b.status_bast,
                       COALESCE(u_tolak.nama_user, u_tinjau.nama_user, '-') AS nama_penolak,
                       COALESCE(NULLIF(o.alasan_tolak, ''), NULLIF(tk.alasan_penolakan, ''), '-') AS alasan_tolak,
                       COALESCE(o.tanggal_tolak, tk.tanggal_tinjauan) AS tanggal_tolak,
                       COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id), 0) AS total_terbayar,
                       (SELECT MAX(tanggal_bayar) FROM opti_pembayaran WHERE order_id = o.id) AS tanggal_bayar_terakhir,
                       u_pic.nama_user AS pic_proposal_nama,
                       u_pelaksana.nama_user AS ketua_pelaksana_nama,
                       u_unlock.nama_user AS unlocked_by_name
                FROM order_layanan o
                JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN po p ON o.id = p.order_id
                LEFT JOIN po_kegiatan pk ON pk.id = (SELECT id FROM po_kegiatan WHERE order_id = o.id" . (class_exists('PoSchema') ? \PoSchema::bukanSertifikasi('') : '') . " ORDER BY id ASC LIMIT 1)
                LEFT JOIN tb_surat_penawaran sp ON sp.id = (SELECT id FROM tb_surat_penawaran WHERE order_id = o.id ORDER BY (status_respon_klien = 'deal') DESC, id DESC LIMIT 1)
                LEFT JOIN opti_proposal_riset pr ON pr.id = (SELECT id FROM opti_proposal_riset WHERE order_id = o.id ORDER BY id DESC LIMIT 1)
                LEFT JOIN opti_bast b ON b.id = (SELECT id FROM opti_bast WHERE order_id = o.id ORDER BY id DESC LIMIT 1)
                LEFT JOIN tb_arsipuser u_tolak ON o.ditolak_oleh = u_tolak.id_user
                LEFT JOIN opti_tinjauan_kelayakan tk ON o.id = tk.order_id AND tk.keputusan = 'tidak_dapat_dilaksanakan'
                LEFT JOIN tb_arsipuser u_tinjau ON tk.ditinjau_oleh = u_tinjau.id_user
                LEFT JOIN tb_arsipuser u_pic ON o.pic_proposal_id = u_pic.id_user
                LEFT JOIN tb_arsipuser u_pelaksana ON o.ketua_pelaksana_id = u_pelaksana.id_user
                LEFT JOIN tb_arsipuser u_unlock ON o.unlocked_by = u_unlock.id_user
                WHERE 1=1";
        
        $params = array();
        $idx = 1;

        if ($this->hanyaPoHumas) {
            $sql .= " AND EXISTS (SELECT 1 FROM po_kegiatan ph WHERE ph.order_id = o.id AND ph.status IN ('terkirim','disetujui_mitra','disetujui') AND ph.status_review = 'disetujui'"
                  . (class_exists('PoSchema') ? \PoSchema::bukanSertifikasi('ph') : '') . ")";
        }

        // Filter tab: masuk (tahap sebelum deal), berlangsung (sudah terjadi deal & proses pengerjaan), selesai (tuntas BAST / laporan selesai), ditolak, atau aktif
        if ($filterTab === 'masuk') {
            $sql .= " AND o.status != 'ditolak' AND (o.status_tinjauan != 'tidak_layak' OR o.status_tinjauan IS NULL)
                      AND (sp.status_respon_klien NOT IN ('ditolak', 'batal') OR sp.status_respon_klien IS NULL)
                      AND (sp.status_respon_klien IS NULL OR sp.status_respon_klien != 'deal')
                      AND (o.status_penawaran IS NULL OR o.status_penawaran != 'deal')
                      AND p.id IS NULL
                      AND b.id IS NULL
                      AND o.status NOT IN ('penawaran_deal', 'disetujui', 'po_dibuat', 'proses', 'selesai', 'lapor_selesai')
                      AND (o.status_pelaksanaan IS NULL OR o.status_pelaksanaan != 'laporan_selesai')";
        } elseif ($filterTab === 'berlangsung') {
            $sql .= " AND o.status != 'ditolak' AND (o.status_tinjauan != 'tidak_layak' OR o.status_tinjauan IS NULL)
                      AND (sp.status_respon_klien NOT IN ('ditolak', 'batal') OR sp.status_respon_klien IS NULL)
                      AND (
                          (sp.status_respon_klien = 'deal') 
                          OR (o.status_penawaran = 'deal') 
                          OR (p.id IS NOT NULL) 
                          OR (o.status IN ('penawaran_deal', 'disetujui', 'po_dibuat', 'proses', 'lapor_selesai'))
                          OR (b.id IS NOT NULL AND b.status_bast NOT IN ('disetujui', 'selesai', 'terbit'))
                      )
                      AND (o.status != 'selesai' AND (o.status_pelaksanaan IS NULL OR o.status_pelaksanaan != 'laporan_selesai'))
                      AND (b.id IS NULL OR b.status_bast NOT IN ('disetujui', 'selesai', 'terbit'))";
        } elseif ($filterTab === 'selesai') {
            $sql .= " AND o.status != 'ditolak' AND (o.status_tinjauan != 'tidak_layak' OR o.status_tinjauan IS NULL)
                      AND (sp.status_respon_klien NOT IN ('ditolak', 'batal') OR sp.status_respon_klien IS NULL)
                      AND (
                          o.status = 'selesai' 
                          OR o.status_pelaksanaan = 'laporan_selesai' 
                          OR (b.id IS NOT NULL AND b.status_bast IN ('disetujui', 'selesai', 'terbit'))
                      )";
        } elseif ($filterTab === 'ditolak') {
            $sql .= " AND (o.status = 'ditolak' OR o.status_tinjauan = 'tidak_layak' OR sp.status_respon_klien IN ('ditolak', 'batal'))";
        } elseif ($filterTab === 'aktif') {
            $sql .= " AND o.status != 'ditolak' AND (o.status_tinjauan != 'tidak_layak' OR o.status_tinjauan IS NULL)
                      AND (sp.status_respon_klien NOT IN ('ditolak', 'batal') OR sp.status_respon_klien IS NULL)";
        }

        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql .= " AND YEAR(COALESCE(o.tanggal_masuk, o.tanggal_klaim, o.created_at)) = ?";
            $params[$idx++] = (int)$filterTahun;
        }

        if (!empty($filterBulan) && $filterBulan !== 'all') {
            $sql .= " AND MONTH(COALESCE(o.tanggal_masuk, o.tanggal_klaim, o.created_at)) = ?";
            $params[$idx++] = (int)$filterBulan;
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
            $sql .= " AND (o.nomor_permintaan LIKE ? OR o.nomor_order LIKE ? OR o.judul_kegiatan LIKE ? OR c.nmcustomer LIKE ? OR o.jenis_sampel LIKE ?)";
            $wildcard = "%{$search}%";
            $params[$idx++] = $wildcard;
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
            $r['nmcustomer_murni'] = Customer::cleanNamaPerusahaan($r['nmcustomer'] ?? ($r['nama_perusahaan'] ?? ''), $r['pt_cv'] ?? '');
            $r['nama_perusahaan'] = Customer::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nama_perusahaan'] ?? '');
            $st = self::resolveStageStatus($r);
            $r['stage_label']        = $st['label'];
            $r['stage_class']        = $st['class'];
            $r['stage_icon']         = $st['icon'];
            $r['stage_user']         = $st['user'] ?? '';
            $r['keterangan_aksi']    = $st['keterangan_aksi'] ?? $st['label'];
            $r['keterangan_sub']     = $st['keterangan_sub'] ?? '';
            $r['keterangan_detail']  = $st['keterangan_detail'] ?? '';
            $r['keterangan_icon']    = $st['keterangan_icon'] ?? 'bi-info-circle text-secondary';
            $r['aksi_badge']         = $st['aksi_badge'] ?? 'bg-secondary-subtle text-secondary border';
            $r['keterangan_pelaksana'] = $st['keterangan_pelaksana'] ?? '';   // baris "Pelaksana: ..." di atas posisi PO (Tahap 8)
            $r['po_pemegang']          = $st['po_pemegang'] ?? '';            // siapa yang memegang bola di alur PO: pic / katim / humas / mitra / ppk
            $r['keterangan_baca']      = $st['keterangan_baca'] ?? '';        // mis. "belum dibaca Ketua Tim" / "dibaca Ketua Tim 12 Okt 14:20"
            $r['po_baca_status']       = $st['po_baca_status'] ?? '';         // 'belum' | 'sudah' | ''

            // Evaluasi status unlock per-order dan countdown auto-lock
            $r['is_unlocked_edit'] = ((int)($r['is_unlocked_edit'] ?? 0) === 1);
            $r['unlocked_remaining_seconds'] = 0;
            $r['unlocked_countdown_label'] = '';
            $r['unlocked_until_formatted'] = '';
            $r['unlocked_is_expired'] = false;

            if ($r['is_unlocked_edit'] && !empty($r['unlocked_until'])) {
                $now = time();
                $until = strtotime($r['unlocked_until']);
                if ($until <= $now) {
                    $r['is_unlocked_edit'] = false;
                    $r['unlocked_is_expired'] = true;
                    try {
                        $this->db->exec(
                            "UPDATE order_layanan SET is_unlocked_edit = 0, unlocked_reason = CONCAT(COALESCE(unlocked_reason, ''), ' [Otomatis terkunci karena batas waktu habis]') WHERE id = ?",
                            [1 => (int)$r['id']]
                        );
                    } catch (\Exception $eEx) {}
                } else {
                    $sisaDetik = $until - $now;
                    $r['unlocked_remaining_seconds'] = $sisaDetik;
                    $sisaJam = floor($sisaDetik / 3600);
                    $sisaMenit = floor(($sisaDetik % 3600) / 60);
                    $r['unlocked_countdown_label'] = "{$sisaJam}j {$sisaMenit}m";
                    $r['unlocked_until_formatted'] = date('d/m/Y H:i', $until);
                }
            }

            // Status Pembayaran Rinci
            $biayaOrder = (float)($r['estimasi_biaya'] ?: ($r['biaya_po'] ?: 0));
            $terbayar   = (float)($r['total_terbayar'] ?? 0);
            if ($biayaOrder > 0 && $terbayar >= $biayaOrder) {
                $r['status_bayar_label'] = 'Lunas';
                $r['status_bayar_class'] = 'bg-success-subtle text-success border border-success-subtle';
                $r['status_bayar_icon']  = 'bi-check2-circle';
            } elseif ($terbayar > 0) {
                $r['status_bayar_label'] = 'Sebagian';
                $r['status_bayar_class'] = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                $r['status_bayar_icon']  = 'bi-hourglass-split';
            } elseif ($biayaOrder > 0) {
                $r['status_bayar_label'] = 'Belum Bayar';
                $r['status_bayar_class'] = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                $r['status_bayar_icon']  = 'bi-clock';
            } else {
                $r['status_bayar_label'] = 'Menunggu Biaya';
                $r['status_bayar_class'] = 'bg-light text-muted border';
                $r['status_bayar_icon']  = 'bi-dash-circle';
            }

            $durInfo = $this->resolveDurasiInfo($r);
            foreach ($durInfo as $kDur => $vDur) {
                $r[$kDur] = $vDur;
            }

            // Resolusi nama & badge tahap saat order ditolak
            if (($r['status_tinjauan'] ?? '') === 'tidak_layak') {
                $r['tahap_tolak'] = 'Kaji Kelayakan ISO';
                $r['tahap_tolak_class'] = 'status-pill status-pill-rose';
                $r['tahap_tolak_icon'] = 'bi-clipboard-x';
            } elseif (!empty($r['status_respon_klien']) && in_array($r['status_respon_klien'], ['batal', 'ditolak'])) {
                $r['tahap_tolak'] = 'Penawaran Harga';
                $r['tahap_tolak_class'] = 'status-pill status-pill-amber';
                $r['tahap_tolak_icon'] = 'bi-file-earmark-x';
            } elseif (($r['status'] ?? '') === 'permintaan_masuk') {
                $r['tahap_tolak'] = 'Disposisi Masuk';
                $r['tahap_tolak_class'] = 'status-pill status-pill-slate';
                $r['tahap_tolak_icon'] = 'bi-inbox';
            } else {
                $r['tahap_tolak'] = 'Verifikasi Order';
                $r['tahap_tolak_class'] = 'status-pill status-pill-rose';
                $r['tahap_tolak_icon'] = 'bi-x-circle';
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * Posisi PO Kegiatan di rantai Disusun -> Review Ketua Tim -> Verifikasi Humas -> Tim Mitra -> PPK BLU -> PO Aktif.
     * Hanya membaca kolom pk_* hasil join di daftar order; kalau join tidak ada (mis. dipanggil dari detail) mengembalikan null.
     * Aturan sama dengan OrderController::alurPoTahap8.
     */
    protected static function posisiPoKegiatan(array $o): ?array {
        if (!array_key_exists('pk_id', $o)) {
            return null;
        }
        $bln = array('', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
        $tgl = function ($v) use ($bln) {
            $t = $v ? strtotime((string) $v) : false;
            return $t ? ((int) date('j', $t) . ' ' . $bln[(int) date('n', $t)] . ' ' . date('Y', $t)) : '';
        };
        $jam = function ($v) use ($bln) {
            $t = strtotime((string) $v);
            return (int) date('j', $t) . ' ' . $bln[(int) date('n', $t)] . ' ' . date('H:i', $t);
        };
        // jejak baca penerima: null = tidak dilacak (kolom belum ada); $min = kolom waktu masuk (baca sebelum itu tidak dihitung)
        $baca = function ($kolom, $penerima, $min = '') use ($o, $jam) {
            if (!array_key_exists('pk_' . $kolom, $o)) { return null; }
            $t = trim((string) $o['pk_' . $kolom]);
            if ($t !== '' && (strpos($t, '0000') === 0 || !strtotime($t))) { $t = ''; }
            if ($t !== '' && $min !== '' && array_key_exists('pk_' . $min, $o)) {
                $mn = trim((string) $o['pk_' . $min]);
                if ($mn !== '' && strtotime($mn) && strtotime($t) < strtotime($mn)) { $t = ''; }
            }
            return array('belum' => ($t === ''), 'teks' => ($t === '' ? 'belum dibaca ' . $penerima : 'dibaca ' . $penerima . ' ' . $jam($t)));
        };
        $r = function ($teks, $catatan, $pemegang, $revisi = false, $bc = null) {
            return array('teks' => $teks, 'catatan' => $catatan, 'pemegang' => $pemegang, 'revisi' => $revisi,
                'baca_teks' => $bc ? $bc['teks'] : '', 'baca_status' => $bc ? ($bc['belum'] ? 'belum' : 'sudah') : '');
        };
        if (empty($o['pk_id'])) {
            return $r('Penyusunan PO', 'PO belum dibuat', 'pic');
        }
        $sent = in_array((string) ($o['pk_status'] ?? ''), array('terkirim', 'disetujui_mitra', 'disetujui'), true);
        if (!$sent) {
            return $r('Penyusunan PO', 'PO belum dikirim', 'pic');
        }
        $sr = (string) ($o['pk_status_review'] ?? '');
        if ($sr === 'disetujui') {
            $putKatim = (int) ($o['pk_putaran_katim'] ?? 0);
            switch ((string) ($o['pk_ppk_status'] ?? '')) {
                case 'minta':          return $r('PPK Meminta Revisi', 'Menunggu keputusan Ketua Tim', 'katim', true, $baca('ppk_dibaca_at', 'Ketua Tim'));
                case 'katim_kerja':    return $r('Revisi PPK Dikerjakan Ketua Tim', 'Ketua Tim sedang memperbaiki PO', 'katim', true);
                case 'ketupel_kerja':
                    if ($putKatim > 0) { return $r('Perbaikan Ulang Dikerjakan Pelaksana', 'Catatan dari Ketua Tim · perbaikan ke-' . $putKatim, 'pic', true, $baca('ppk_dibaca_at', 'Ketua Pelaksana')); }
                    return $r('Revisi PPK Dikerjakan Pelaksana', 'Didisposisikan ke Ketua Pelaksana', 'pic', true, $baca('ppk_dibaca_at', 'Ketua Pelaksana'));
                case 'katim_review':
                    if ($putKatim > 0) { return $r('Review Hasil Perbaikan Ketua Tim', 'Perbaikan ke-' . $putKatim . ' · menunggu review Ketua Tim', 'katim', true, $baca('ppk_dibaca_at', 'Ketua Tim')); }
                    return $r('Review Hasil Revisi PPK', 'Menunggu review Ketua Tim', 'katim', true, $baca('ppk_dibaca_at', 'Ketua Tim'));
            }
            if (!empty($o['pk_validasi_keuangan_at'])) {
                return $r('PO Aktif', 'Pengerjaan lab dimulai', '');
            }
            if (!empty($o['pk_tervalidasi_mitra_at'])) {
                // Meja PPK BLU bisa dikunjungi berulang (PPK minta revisi -> Ketua Tim/Pelaksana memperbaiki -> kembali ke PPK BLU).
                // Tiap kali PO kembali, penanda baca di-NULL-kan dan waktu masuknya (ppk_status_at) jadi acuan "belum/sudah dibaca" ronde itu.
                $ronde = (int) ($o['pk_ronde_ppk'] ?? 0);
                if ($ronde > 0) {
                    $masuk = $jam($o['pk_ppk_status_at'] ?? '');
                    $catatan = 'Ketua Tim mengirim hasil revisi (ronde ' . $ronde . ')' . (!empty($o['pk_ppk_status_at']) ? ' ' . $masuk : '');
                    return $r('Revisi Kembali ke PPK BLU', $catatan, 'ppk', false, $baca('ppk_dibaca_at', 'PPK BLU', 'ppk_status_at'));
                }
                return $r('Menunggu Verifikasi PPK BLU', 'Tim Mitra sudah memverifikasi', 'ppk', false, $baca('ppk_dibaca_at', 'PPK BLU', 'tervalidasi_mitra_at'));
            }
            if (!empty($o['pk_verifikasi_humas_at'])) {
                return $r('Menunggu Verifikasi Tim Mitra', 'Humas sudah memverifikasi', 'mitra', false, $baca('mitra_dibaca_at', 'Tim Mitra', 'verifikasi_humas_at'));
            }
            $tg = $tgl($o['pk_disetujui_at'] ?? null);
            return $r('Menunggu Verifikasi Humas', 'Disetujui Ketua Tim' . ($tg !== '' ? ' ' . $tg : ''), 'humas', false, $baca('humas_dibaca_at', 'Humas', 'disposisi_humas_at'));
        }
        if ($sr === 'perlu_revisi') {
            return $r('Perlu Revisi PO', 'Ketua Tim memberi catatan revisi', 'pic', true, $baca('minta_dibaca_at', 'Ketua Pelaksana'));
        }
        if ($sr === 'direvisi') {
            return $r('Revisi Menunggu Review', 'Revisi sudah dikirim ulang', 'katim', true, $baca('revisi_dibaca_at', 'Ketua Tim'));
        }
        return $r('Menunggu Review Ketua Tim', 'PO sudah dikirim', 'katim', false, $baca('reviewed_at', 'Ketua Tim'));
    }

    /**
     * Helper penentu status alur tunggal, simpel, dan konsisten
     */
    public static function resolveStageStatus(array $o): array {
        $isSelulosa = (($o['jenis_layanan_opti'] ?? '') === 'selulosa');
        $isLingkungan = (($o['jenis_layanan_opti'] ?? '') === 'lingkungan');
        $katimLabel = $isSelulosa ? 'Ketua Tim Selulosa' : ($isLingkungan ? 'Ketua Tim Lingkungan' : 'Ketua Tim OPTI');
        $timKerjaLabel = $isSelulosa ? 'Tim Kerja Selulosa' : ($isLingkungan ? 'Tim Kerja Lingkungan' : 'Tim Kerja OPTI');

        // 1. Order Ditolak
        if (($o['status'] ?? '') === 'ditolak' || ($o['status_tinjauan'] ?? '') === 'tidak_layak' || in_array($o['status_respon_klien'] ?? '', ['batal', 'ditolak'])) {
            return [
                'label' => 'Order Ditolak',
                'class' => 'status-pill status-pill-rose',
                'icon'  => 'bi-x-circle-fill',
                'user'  => 'Tim Mitra & Pelanggan',
                'keterangan_aksi'   => 'Permohonan Ditolak',
                'keterangan_sub'    => 'Tidak Dapat Dilanjutkan',
                'keterangan_detail' => !empty($o['alasan_tolak']) && $o['alasan_tolak'] !== '-' ? $o['alasan_tolak'] : 'Permohonan order tidak memenuhi kriteria',
                'keterangan_icon'   => 'bi-x-circle text-danger',
                'aksi_badge' => 'bg-danger-subtle text-danger border border-danger-subtle'
            ];
        }

        // 2. Step 8: Selesai (BAST)
        $isBastDone = (!empty($o['bast_id']) && in_array($o['status_bast'] ?? '', ['disetujui', 'selesai', 'terbit']))
            || ($o['status'] ?? '') === 'selesai'
            || ($o['status_pelaksanaan'] ?? '') === 'laporan_selesai';
        if ($isBastDone) {
            return [
                'label' => 'Selesai (BAST)',
                'class' => 'status-pill status-pill-emerald',
                'icon'  => 'bi-check2-all',
                'user'  => 'Tim Mitra & Pelanggan',
                'keterangan_aksi'   => 'Pekerjaan Selesai',
                'keterangan_sub'    => 'BAST & Laporan Resmi Terbit',
                'keterangan_detail' => 'Seluruh tahapan order telah tuntas dilaksanakan',
                'keterangan_icon'   => 'bi-check-circle-fill text-success',
                'aksi_badge' => 'bg-success-subtle text-success border border-success-subtle'
            ];
        }

        // 3. Step 7: Pengujian Lab (PO Aktif)
        if (!empty($o['po_id']) || ($o['status'] ?? '') === 'disetujui' || in_array($o['status_pelaksanaan'] ?? '', ['sedang_berjalan', 'evaluasi_laporan', 'revisi_laporan'])) {
            return [
                'label' => 'Pengujian Lab',
                'class' => 'status-pill status-pill-indigo',
                'icon'  => 'bi-flask-fill',
                'user'  => $timKerjaLabel,
                'keterangan_aksi'   => 'Pengujian di Laboratorium',
                'keterangan_sub'    => 'Pelaksana: ' . $timKerjaLabel,
                'keterangan_detail' => 'Analisis & pengujian sampel sedang berjalan',
                'keterangan_icon'   => 'bi-flask text-primary',
                'aksi_badge' => 'bg-primary-subtle text-primary border border-primary-subtle'
            ];
        }

        // Cek Pembayaran Lunas & Penawaran DEAL
        $isDeal = (($o['status'] ?? '') === 'penawaran_deal' || ($o['status_respon_klien'] ?? '') === 'deal' || ($o['status_penawaran'] ?? '') === 'deal');
        $isLunas = (($o['status_keuangan'] ?? '') === 'lunas');   // bayar sebagian (terbayar_sebagian) belum dianggap lunas

        // Jika sudah DEAL & LUNAS, namun PO belum dibuat -> Alur masuk ke Step 7 (Sampel & PO)
        if ($isDeal && $isLunas) {
            if (empty($o['tanggal_terima_sampel']) && empty($o['tanggal_spm_mulai'])) {
                return [
                    'label' => 'Menunggu Sampel',
                    'class' => 'status-pill status-pill-amber',
                    'icon'  => 'bi-box-seam-fill',
                    'user'  => 'Tim Mitra & Pelanggan',
                    'keterangan_aksi'   => 'Menunggu Kiriman Sampel',
                    'keterangan_sub'    => 'Dari: ' . (!empty($o['nama_perusahaan']) ? $o['nama_perusahaan'] : 'Pelanggan'),
                    'keterangan_detail' => 'Sampel uji belum sampai di laboratorium',
                    'keterangan_icon'   => 'bi-box-seam text-warning',
                    'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                ];
            } elseif (empty($o['ketua_pelaksana_id'])) {
                return [
                    'label' => 'Penunjukan Pelaksana',
                    'class' => 'status-pill status-pill-rose',
                    'icon'  => 'bi-person-plus-fill',
                    'user'  => $katimLabel,
                    'keterangan_aksi'   => 'Penunjukan Ketua Pelaksana',
                    'keterangan_sub'    => 'Pelaksana: ' . $katimLabel,
                    'keterangan_detail' => 'Ketua Tim belum menunjuk pelaksana teknis',
                    'keterangan_icon'   => 'bi-person-exclamation text-danger',
                    'aksi_badge' => 'bg-danger-subtle text-danger border border-danger-subtle'
                ];
            } else {
                $namaPelaksana = !empty($o['ketua_pelaksana_nama']) ? $o['ketua_pelaksana_nama'] : 'Ketua Pelaksana';
                // Daftar order: posisi PO di rantai review & verifikasi (dibaca dari po_kegiatan, sama dengan Tahap 8 di detail order)
                $posisi = self::posisiPoKegiatan($o);
                if ($posisi !== null) {
                    return [
                        'label' => 'Penerbitan PO',
                        'class' => $posisi['revisi'] ? 'status-pill status-pill-rose' : 'status-pill status-pill-blue',
                        'icon'  => 'bi-patch-check-fill',
                        'user'  => $namaPelaksana,
                        'keterangan_pelaksana' => 'Pelaksana: ' . $namaPelaksana,
                        'keterangan_aksi'   => $posisi['teks'],
                        'keterangan_sub'    => $posisi['teks'],
                        'keterangan_detail' => $posisi['catatan'],
                        'keterangan_icon'   => 'bi-person-check text-primary',
                        'aksi_badge' => $posisi['revisi'] ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle',
                        'po_pemegang' => $posisi['pemegang'],
                        'keterangan_baca' => $posisi['baca_teks'],
                        'po_baca_status'  => $posisi['baca_status']
                    ];
                }
                return [
                    'label' => 'Penerbitan PO',
                    'class' => 'status-pill status-pill-blue',
                    'icon'  => 'bi-patch-check-fill',
                    'user'  => $namaPelaksana,
                    'keterangan_aksi'   => 'Pembuatan Dokumen PO',
                    'keterangan_sub'    => 'Pelaksana: ' . $namaPelaksana,
                    'keterangan_detail' => 'Menunggu penyusunan & penerbitan PO teknis',
                    'keterangan_icon'   => 'bi-person-check text-primary',
                    'aksi_badge' => 'bg-primary-subtle text-primary border border-primary-subtle'
                ];
            }
        }

        // Sudah DEAL & baru bayar sebagian: SPM boleh diaktifkan lebih dulu, tapi PO menunggu pelunasan
        $isSebagian = $isDeal && !$isLunas && (($o['status_keuangan'] ?? '') === 'terbayar_sebagian' || (float)($o['total_terbayar'] ?? 0) > 0);
        if ($isSebagian) {
            if (empty($o['tanggal_terima_sampel']) && empty($o['tanggal_spm_mulai'])) {
                return [
                    'label' => 'Menunggu Sampel',
                    'class' => 'status-pill status-pill-amber',
                    'icon'  => 'bi-box-seam-fill',
                    'user'  => 'Tim Mitra & Pelanggan',
                    'keterangan_aksi'   => 'Menunggu Kiriman Sampel',
                    'keterangan_sub'    => 'Dari: ' . (!empty($o['nama_perusahaan']) ? $o['nama_perusahaan'] : 'Pelanggan'),
                    'keterangan_detail' => 'Pembayaran baru sebagian; SPM sudah bisa diaktifkan',
                    'keterangan_icon'   => 'bi-box-seam text-warning',
                    'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                ];
            }
            return [
                'label' => 'Menunggu Pelunasan',
                'class' => 'status-pill status-pill-amber',
                'icon'  => 'bi-credit-card-2-front-fill',
                'user'  => 'Tim Keuangan',
                'keterangan_aksi'   => 'Menunggu Pelunasan',
                'keterangan_sub'    => 'Klien: ' . (!empty($o['nama_perusahaan']) ? $o['nama_perusahaan'] : 'Mitra'),
                'keterangan_detail' => 'SPM aktif; PO terbuka setelah pembayaran lunas',
                'keterangan_icon'   => 'bi-credit-card text-warning',
                'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
            ];
        }

        // 4. Step 6: Menunggu Pembayaran (Sudah DEAL namun belum lunas)
        if ($isDeal) {
            return [
                'label' => 'Menunggu Bayar',
                'class' => 'status-pill status-pill-amber',
                'icon'  => 'bi-credit-card-2-front-fill',
                'user'  => 'Tim Keuangan',
                'keterangan_aksi'   => 'Menunggu Pembayaran',
                'keterangan_sub'    => 'Klien: ' . (!empty($o['nama_perusahaan']) ? $o['nama_perusahaan'] : 'Mitra'),
                'keterangan_detail' => 'Pelanggan belum membayar tagihan layanan',
                'keterangan_icon'   => 'bi-credit-card text-warning',
                'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
            ];
        }

        // 5. Step 5: Penawaran Terbit (Surat Penawaran resmi sudah dibuat namun belum DEAL)
        $hasOfferLetter = (!empty($o['surat_penawaran_id']) || !empty($o['nomor_penawaran']) || in_array($o['status_penawaran'] ?? '', ['terkirim', 'nego', 'draft']));
        if ($hasOfferLetter) {
            return [
                'label' => 'Penawaran Terbit',
                'class' => 'status-pill status-pill-sky',
                'icon'  => 'bi-file-earmark-text-fill',
                'user'  => 'Tim Mitra Industri',
                'keterangan_aksi'   => 'Menunggu Respon Penawaran',
                'keterangan_sub'    => 'Klien: ' . (!empty($o['nama_perusahaan']) ? $o['nama_perusahaan'] : 'Pelanggan'),
                'keterangan_detail' => 'Surat penawaran harga sudah dikirim ke pelanggan',
                'keterangan_icon'   => 'bi-send-check text-info',
                'aksi_badge' => 'bg-info-subtle text-info-emphasis border border-info-subtle'
            ];
        }

        // Step 4 & 3: Cek jenis layanan dan kaji kelayakan
        $isLayak = (($o['status_tinjauan'] ?? '') === 'layak');

        // Jika Kaji Kelayakan Layak -> Masuk Step 4
        if ($isLayak) {
            if ($isLingkungan) {
                $hasBiaya = ((float)($o['estimasi_biaya'] ?? 0) > 0) || in_array($o['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']);
                if ($hasBiaya) {
                    return [
                        'label' => 'Siap Penawaran',
                        'class' => 'status-pill status-pill-sky',
                        'icon'  => 'bi-check-circle-fill',
                        'user'  => 'Tim Mitra Industri',
                        'keterangan_aksi'   => 'Siap Dibuat Surat Penawaran',
                        'keterangan_sub'    => 'Pelaksana: Tim Mitra Industri',
                        'keterangan_detail' => 'Rincian biaya selesai, siap diterbitkan surat',
                        'keterangan_icon'   => 'bi-file-earmark-check text-primary',
                        'aksi_badge' => 'bg-primary-subtle text-primary border border-primary-subtle'
                    ];
                }
                if (($o['status_proposal_biaya'] ?? '') === 'draft_disimpan') {
                    return [
                        'label' => 'Draft Disimpan',
                        'class' => 'status-pill status-pill-amber',
                        'icon'  => 'bi-bookmark-check-fill',
                        'user'  => 'Tim Kerja Lingkungan',
                        'keterangan_aksi'   => 'Penyusunan Biaya Uji',
                        'keterangan_sub'    => 'Pelaksana: Tim Kerja Lingkungan',
                        'keterangan_detail' => 'Draf tarif disimpan, belum diajukan penawaran',
                        'keterangan_icon'   => 'bi-pencil-square text-warning',
                        'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                    ];
                }
                return [
                    'label' => 'Tarif & Parameter',
                    'class' => 'status-pill status-pill-blue',
                    'icon'  => 'bi-calculator-fill',
                    'user'  => 'Tim Kerja Lingkungan',
                    'keterangan_aksi'   => 'Perhitungan Tarif Uji',
                    'keterangan_sub'    => 'Pelaksana: Tim Kerja Lingkungan',
                    'keterangan_detail' => 'Sedang menentukan parameter & tarif lab',
                    'keterangan_icon'   => 'bi-calculator text-primary',
                    'aksi_badge' => 'bg-primary-subtle text-primary border border-primary-subtle'
                ];
            } elseif ($isSelulosa) {
                $proposalComplete = !empty($o['file_proposal']) && (float)($o['estimasi_total_biaya'] ?? 0) > 0;
                $proposalApproved = in_array($o['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) || in_array($o['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan']);
                if ($proposalApproved) {
                    return [
                        'label' => 'Proposal Disetujui',
                        'class' => 'status-pill status-pill-emerald',
                        'icon'  => 'bi-check-circle-fill',
                        'user'  => 'Tim Mitra Industri',
                        'keterangan_aksi'   => 'Siap Dibuat Surat Penawaran',
                        'keterangan_sub'    => 'Proposal Disetujui Ketua Tim',
                        'keterangan_detail' => 'Menunggu Tim Mitra menerbitkan surat penawaran',
                        'keterangan_icon'   => 'bi-check-circle-fill text-success',
                        'aksi_badge' => 'bg-primary-subtle text-primary border border-primary-subtle'
                    ];
                }
                $rawPicNama = !empty($o['pic_proposal_nama']) ? trim($o['pic_proposal_nama']) : '';
                $rawPicNama = preg_replace('/^pic\s*:\s*/i', '', $rawPicNama);
                $picPelaksana = !empty($rawPicNama) ? $rawPicNama : 'Tim Kerja Selulosa';
                if (($o['status_proposal'] ?? '') === 'draft_disimpan' || ($o['status_proposal_biaya'] ?? '') === 'draft_disimpan') {
                    return [
                        'label' => 'Draft Disimpan',
                        'class' => 'status-pill status-pill-amber',
                        'icon'  => 'bi-bookmark-check-fill',
                        'user'  => 'Tim Kerja Selulosa',
                        'keterangan_aksi'   => 'Draf Proposal Belum Diajukan',
                        'keterangan_sub'    => 'Pelaksana: ' . $picPelaksana,
                        'keterangan_detail' => 'Masih tersimpan sebagai draf di sistem',
                        'keterangan_icon'   => 'bi-pencil-square text-warning',
                        'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                    ];
                }
                if (($o['status_proposal_biaya'] ?? '') === 'menunggu_approval' || ($o['status_proposal'] ?? '') === 'diajukan' || $proposalComplete) {
                    return [
                        'label' => 'Review Proposal',
                        'class' => 'status-pill status-pill-sky',
                        'icon'  => 'bi-hourglass-split',
                        'user'  => 'Ketua Tim Selulosa',
                        'keterangan_aksi'   => 'Pemeriksaan Proposal',
                        'keterangan_sub'    => 'Pelaksana: Ketua Tim Selulosa',
                        'keterangan_detail' => 'Proposal sudah diajukan, menunggu persetujuan Katim',
                        'keterangan_icon'   => 'bi-hourglass-split text-info',
                        'aksi_badge' => 'bg-info-subtle text-info-emphasis border border-info-subtle'
                    ];
                }
                return [
                    'label' => 'Proposal Teknis',
                    'class' => 'status-pill status-pill-blue',
                    'icon'  => 'bi-file-earmark-arrow-up-fill',
                    'user'  => 'Tim Kerja Selulosa',
                    'keterangan_aksi'   => 'Penyusunan Proposal Teknis',
                    'keterangan_sub'    => 'Pelaksana: ' . $picPelaksana,
                    'keterangan_detail' => 'Sedang menyusun proposal & estimasi biaya',
                    'keterangan_icon'   => 'bi-pencil-square text-primary',
                    'aksi_badge' => 'bg-primary-subtle text-primary border border-primary-subtle'
                ];
            } else {
                return [
                    'label' => 'Disposisi Masuk',
                    'class' => 'status-pill status-pill-slate',
                    'icon'  => 'bi-inbox-fill',
                    'user'  => $katimLabel,
                    'keterangan_aksi'   => 'Permohonan Baru Masuk',
                    'keterangan_sub'    => 'Perlu Disposisi Layanan',
                    'keterangan_detail' => 'Perlu ditentukan jenis divisi & Ketua Tim',
                    'keterangan_icon'   => 'bi-inbox text-secondary',
                    'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                ];
            }
        }

        // Step 3: Menunggu Kaji Kelayakan (Setelah Form Pelayanan Jasa terisi)
        if (($o['status'] ?? '') !== 'permintaan_masuk' && !empty($o['jenis_layanan_opti']) && $o['jenis_layanan_opti'] !== 'belum_ditentukan') {
            if (empty($o['status_tinjauan']) || in_array($o['status_tinjauan'] ?? '', ['belum_ditinjau', 'menunggu', 'draft'])) {
                return [
                    'label' => 'Kaji Kelayakan',
                    'class' => 'status-pill status-pill-amber',
                    'icon'  => 'bi-clipboard-check-fill',
                    'user'  => $katimLabel,
                    'keterangan_aksi'   => 'Menunggu Kaji Kelayakan',
                    'keterangan_sub'    => 'Pelaksana: ' . $katimLabel,
                    'keterangan_detail' => 'Perlu dicek kelayakan personel & peralatan lab',
                    'keterangan_icon'   => 'bi-clipboard-check text-warning',
                    'aksi_badge' => 'bg-danger-subtle text-danger border border-danger-subtle'
                ];
            }
        }

        // Step 2: Form Pelayanan Jasa / Disposisi
        if (($o['status'] ?? '') === 'draft_disimpan' || ($o['status'] ?? '') === 'draft') {
            return [
                'label' => 'Draft Disimpan',
                'class' => 'status-pill status-pill-amber',
                'icon'  => 'bi-bookmark-check-fill',
                'user'  => 'Tim Mitra Industri',
                'keterangan_aksi'   => 'Draf Formulir Pelayanan',
                'keterangan_sub'    => 'Pelaksana: Tim Mitra Industri',
                'keterangan_detail' => 'Masih diisi oleh staf mitra, belum diajukan',
                'keterangan_icon'   => 'bi-file-earmark-text text-warning',
                'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
            ];
        }

        if (($o['status'] ?? '') === 'permintaan_masuk' || empty($o['jenis_layanan_opti']) || ($o['jenis_layanan_opti'] ?? '') === 'belum_ditentukan') {
            return [
                'label' => 'Disposisi Masuk',
                'class' => 'status-pill status-pill-slate',
                'icon'  => 'bi-inbox-fill',
                'user'  => 'Tim Mitra Industri',
                'keterangan_aksi'   => 'Permohonan Baru Masuk',
                'keterangan_sub'    => 'Perlu Disposisi Layanan',
                'keterangan_detail' => 'Perlu ditentukan divisi & Ketua Tim',
                'keterangan_icon'   => 'bi-inbox text-secondary',
                'aksi_badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
            ];
        }

        // Fallback
        return [
            'label' => 'Order Baru',
            'class' => 'status-pill status-pill-sky',
            'icon'  => 'bi-file-earmark',
            'user'  => 'Tim Mitra Industri',
            'keterangan_aksi'   => 'Permohonan Baru Diterima',
            'keterangan_sub'    => 'Registrasi Berkas Masuk',
            'keterangan_detail' => 'Menunggu verifikasi permohonan jasa',
            'keterangan_icon'   => 'bi-info-circle text-info',
            'aksi_badge' => 'bg-info-subtle text-info-emphasis border border-info-subtle'
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
                    p.id AS po_id, p.nomor_po, p.status AS status_po, p.biaya AS biaya_po,
                    p.target_mulai AS po_target_mulai, p.target_selesai AS target_po, p.target_selesai AS po_target_selesai,
                    sp.id AS surat_penawaran_id, sp.status_respon_klien, sp.nomor_surat AS nomor_penawaran,
                    sp.tanggal_surat AS penawaran_tanggal_surat, sp.updated_at AS penawaran_updated_at,
                    pr.durasi_kegiatan AS proposal_durasi, pr.status_proposal,
                    pr.file_proposal, pr.estimasi_total_biaya, pr.disetujui_ketua_at AS proposal_disetujui_at,
                    b.id AS bast_id, b.status_bast,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id), 0) AS total_terbayar,
                    (SELECT MAX(tanggal_bayar) FROM opti_pembayaran WHERE order_id = o.id) AS tanggal_bayar_terakhir,
                    u_pic.nama_user AS pic_proposal_nama,
                    u_pelaksana.nama_user AS ketua_pelaksana_nama,
                    u_klaim.nama_user AS nama_pengklaim,
                    u_unlock.nama_user AS unlocked_by_name,
                    a.nomor_surat AS nomor_surat_masuk,
                    a.tanggal_surat AS tanggal_surat_masuk,
                    a.perihal AS perihal_surat_masuk,
                    a.nama_berkas AS file_surat_masuk,
                    a.kontak_person AS pic_surat_masuk,
                    a.hp_kontakperson AS hp_pic_surat_masuk,
                    a.email_kontakperson AS email_pic_surat_masuk
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer
             LEFT JOIN tb_arsipsurat a ON o.id_surat_masuk = a.id_arsip
             LEFT JOIN po p ON o.id = p.order_id
             LEFT JOIN tb_surat_penawaran sp ON sp.id = (SELECT id FROM tb_surat_penawaran WHERE order_id = o.id ORDER BY (status_respon_klien = 'deal') DESC, id DESC LIMIT 1)
             LEFT JOIN tb_arsipuser u_pic ON o.pic_proposal_id = u_pic.id_user
             LEFT JOIN tb_arsipuser u_pelaksana ON o.ketua_pelaksana_id = u_pelaksana.id_user
             LEFT JOIN tb_arsipuser u_klaim ON o.diklaim_oleh = u_klaim.id_user
              LEFT JOIN tb_arsipuser u_unlock ON o.unlocked_by = u_unlock.id_user
             LEFT JOIN opti_proposal_riset pr ON o.id = pr.order_id
             LEFT JOIN opti_bast b ON b.id = (SELECT id FROM opti_bast WHERE order_id = o.id ORDER BY id DESC LIMIT 1)
             WHERE o.id = ?",
            array(1 => $id)
        );
        if (empty($res)) {
            return null;
        }
        $detail = $res[0];
        $detail['nmcustomer_murni'] = Customer::cleanNamaPerusahaan($detail['nmcustomer'] ?? ($detail['nama_perusahaan'] ?? ''), $detail['pt_cv'] ?? '');
        $detail['nama_perusahaan'] = Customer::formatNamaPerusahaan($detail['pt_cv'] ?? '', $detail['nama_perusahaan'] ?? '');
        // Evaluasi status unlock per-order dan countdown auto-lock
        $detail['is_unlocked_edit'] = ((int)($detail['is_unlocked_edit'] ?? 0) === 1);
        $detail['unlocked_remaining_seconds'] = 0;
        $detail['unlocked_countdown_label'] = '';
        $detail['unlocked_until_formatted'] = '';
        $detail['unlocked_is_expired'] = false;

        if ($detail['is_unlocked_edit'] && !empty($detail['unlocked_until'])) {
            $now = time();
            $until = strtotime($detail['unlocked_until']);
            if ($until <= $now) {
                $detail['is_unlocked_edit'] = false;
                $detail['unlocked_is_expired'] = true;
                try {
                    $this->db->exec(
                        "UPDATE order_layanan SET is_unlocked_edit = 0, unlocked_reason = CONCAT(COALESCE(unlocked_reason, ''), ' [Otomatis terkunci karena batas waktu habis]') WHERE id = ?",
                        [1 => $id]
                    );
                } catch (\Exception $eEx) {}
            } else {
                $sisaDetik = $until - $now;
                $detail['unlocked_remaining_seconds'] = $sisaDetik;
                $sisaJam = floor($sisaDetik / 3600);
                $sisaMenit = floor(($sisaDetik % 3600) / 60);
                $detail['unlocked_countdown_label'] = "{$sisaJam}j {$sisaMenit}m";
                $detail['unlocked_until_formatted'] = date('d/m/Y H:i', $until);
            }
        }

        $st = self::resolveStageStatus($detail);
        $detail['stage_label'] = $st['label'];
        $detail['stage_class'] = $st['class'];
        $detail['stage_icon']  = $st['icon'];
        $detail['stage_user']  = $st['user'] ?? '';

        $durInfo = $this->resolveDurasiInfo($detail);
        foreach ($durInfo as $kDur => $vDur) {
            $detail[$kDur] = $vDur;
        }

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
     * Generate nomor permintaan unik format: REQ-YYYYMM-XXXX (4-digit counter akumulatif per tahun)
     */
    public function generateNomorPermintaan(string $tanggal = ''): string {
        $time = !empty($tanggal) ? strtotime($tanggal) : time();
        if (!$time) $time = time();
        $year = date('Y', $time);
        $prefix = 'REQ-' . date('Ym', $time);
        
        $rows = $this->db->exec(
            "SELECT nomor_permintaan FROM order_layanan WHERE nomor_permintaan LIKE ?",
            array(1 => 'REQ-' . $year . '%')
        );

        $maxSeq = 0;
        foreach ($rows as $r) {
            $parts = explode('-', $r['nomor_permintaan']);
            $num = (int)($parts[2] ?? 0);
            if ($num > $maxSeq) {
                $maxSeq = $num;
            }
        }

        $nextSeq = $maxSeq + 1;
        $nomorReq = $prefix . '-' . sprintf('%04d', $nextSeq);

        while (($this->db->exec("SELECT COUNT(*) as c FROM order_layanan WHERE nomor_permintaan = ?", array(1 => $nomorReq))[0]['c'] ?? 0) > 0) {
            $nextSeq++;
            $nomorReq = $prefix . '-' . sprintf('%04d', $nextSeq);
        }

        return $nomorReq;
    }

    /**
     * Simpan permohonan manual dari channel non-surat (WhatsApp, Telepon, Datang Langsung, Website)
     */
    public function simpanPermohonanManual(array $data): int {
        $idCustomer = (int)($data['id_customer'] ?? 0);
        if ($idCustomer <= 0) {
            throw new \Exception("Pelanggan resmi dari master data tb_customer harus dipilih.");
        }

        $custRows = $this->db->exec(
            "SELECT id_customer, nmcustomer, pt_cv, kodex_perusahaan FROM tb_customer WHERE id_customer = ?",
            [1 => $idCustomer]
        );
        if (empty($custRows)) {
            throw new \Exception("Data pelanggan tidak ditemukan dalam database.");
        }

        $tanggalMasuk = !empty($data['tanggal_masuk']) ? date('Y-m-d', strtotime($data['tanggal_masuk'])) : date('Y-m-d');
        $nomorPermintaan = $this->generateNomorPermintaan($tanggalMasuk);

        $mediaPermohonan = trim($data['media_permohonan'] ?? 'whatsapp');
        $validMedia = ['whatsapp', 'telepon', 'datang_langsung', 'website'];
        if (!in_array($mediaPermohonan, $validMedia)) {
            $mediaPermohonan = 'whatsapp';
        }

        $jenisLayanan = trim($data['jenis_layanan_opti'] ?? 'belum_ditentukan');
        if (!in_array($jenisLayanan, ['selulosa', 'lingkungan', 'belum_ditentukan'])) {
            $jenisLayanan = 'belum_ditentukan';
        }

        $judulKegiatan = trim($data['judul_kegiatan'] ?? '');
        if (empty($judulKegiatan)) {
            $judulKegiatan = 'Permohonan Layanan via ' . ucfirst(str_replace('_', ' ', $mediaPermohonan));
        }

        $deskripsi = trim($data['deskripsi'] ?? '');
        $fileBukti = !empty($data['file_bukti_permohonan']) ? trim($data['file_bukti_permohonan']) : null;
        $userId = !empty($data['diklaim_oleh']) ? (int)$data['diklaim_oleh'] : null;
        $waktuSekarang = date('Y-m-d H:i:s');

        $this->db->exec(
            "INSERT INTO order_layanan (
                id_customer, id_surat_masuk, nomor_permintaan, nomor_order, tanggal_masuk,
                judul_kegiatan, deskripsi, spm_layanan, jenis_layanan_opti,
                lokasi_pelaksanaan, jumlah_pekerjaan, estimasi_biaya,
                status, diklaim_oleh, tanggal_klaim, media_permohonan, file_bukti_permohonan, created_at
            ) VALUES (
                ?, NULL, ?, NULL, ?,
                ?, ?, 'Lainnya', ?,
                'internal', '1 paket kegiatan', 0.00,
                'permintaan_masuk', ?, ?, ?, ?, ?
            )",
            [
                1  => $idCustomer,
                2  => $nomorPermintaan,
                3  => $tanggalMasuk,
                4  => $judulKegiatan,
                5  => $deskripsi,
                6  => $jenisLayanan,
                7  => $userId,
                8  => $waktuSekarang,
                9  => $mediaPermohonan,
                10 => $fileBukti,
                11 => $waktuSekarang
            ]
        );

        $orderId = (int)($this->db->exec("SELECT LAST_INSERT_ID() as id")[0]['id'] ?? 0);
        return $orderId;
    }

    /**
     * Generate nomor order unik aman: ORD-YYYYMM-XXX
     * HANYA diterbitkan ketika customer telah melakukan pembayaran / konfirmasi kesanggupan bayar
     */
    public function generateNomorOrder(string $tanggal = ''): string {
        $time = !empty($tanggal) ? strtotime($tanggal) : time();
        if (!$time) $time = time();
        $prefix = 'ORD-' . date('Ym', $time);
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
     * Terbitkan nomor order resmi jika order sudah melakukan pembayaran
     */
    public function terbitkanNomorOrder(int $orderId): ?string {
        $this->load(['id = ?', $orderId]);
        if ($this->dry()) return null;
        if (empty($this->nomor_order)) {
            $this->nomor_order = $this->generateNomorOrder();
            $this->save();
        }
        return $this->nomor_order;
    }

    /**
     * Simpan order layanan baru
     */
    public function simpanBaru(array $data): int {
        $this->reset();
        $this->id_customer          = (int)$data['id_customer'];
        $this->nomor_permintaan     = !empty($data['nomor_permintaan']) ? trim($data['nomor_permintaan']) : $this->generateNomorPermintaan($data['tanggal_masuk'] ?? '');
        $this->nomor_order          = !empty($data['nomor_order']) ? trim($data['nomor_order']) : null;
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
        $targetMulai = !empty($this->tanggal_terima_sampel) ? $this->tanggal_terima_sampel : date('Y-m-d');
        $targetSpm = !empty($this->tanggal_deadline_spm) ? $this->tanggal_deadline_spm : self::hitungTargetSelesaiSpm($this->tanggal_masuk, $this->spm_layanan);

        $poId = $poModel->buatDariOrder($this->id, $nomorPoManual, $biayaAwal, array(
            'target_mulai'   => $targetMulai,
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
    public function simpanTinjauanKelayakan(int $orderId, array $data, int $userId, bool $isDraft = false): array {
        $this->load(array('id = ?', $orderId));
        if ($this->dry()) {
            throw new \Exception("Order Layanan #{$orderId} tidak ditemukan.");
        }

        $sdmTersedia   = !empty($data['sdm_tersedia']) ? 1 : 0;
        $sdmCatatan    = trim($data['sdm_catatan'] ?? '');
        $alatTersedia  = (!empty($data['peralatan_tersedia']) || !empty($data['alat_tersedia'])) ? 1 : 0;
        $alatCatatan   = trim($data['peralatan_catatan'] ?? '');
        $bahanTersedia = (!empty($data['bahan_tersedia']) || !empty($data['bahan_kimia_tersedia'])) ? 1 : 0;
        $bahanCatatan  = trim($data['bahan_catatan'] ?? '');
        $metodeTersedia= (!empty($data['metode_tersedia']) || !empty($data['metode_uji_tersedia'])) ? 1 : 0;
        $metodeCatatan = trim($data['metode_catatan'] ?? '');

        $keputusan = ($data['keputusan'] ?? '') === 'tidak_dapat_dilaksanakan' ? 'tidak_dapat_dilaksanakan' : 'dapat_dilaksanakan';
        $alasanPenolakan = trim($data['alasan_penolakan'] ?? '');

        // Hapus tinjauan lama jika ada untuk order ini
        $this->db->exec("DELETE FROM opti_tinjauan_kelayakan WHERE order_id = ?", array(1 => $orderId));

        // Insert tinjauan baru
        $this->db->exec(
            "INSERT INTO opti_tinjauan_kelayakan 
            (order_id, sdm_tersedia, sdm_catatan, peralatan_tersedia, peralatan_catatan, bahan_tersedia, bahan_catatan, metode_tersedia, metode_catatan, keputusan, is_draft, alasan_penolakan, ditinjau_oleh, tanggal_tinjauan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
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
                11 => $isDraft ? 1 : 0,
                12 => $alasanPenolakan,
                13 => $userId
            )
        );

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

        // Update status order jika BUKAN draft (resmi ditetapkan)
        if (!$isDraft) {
            if ($keputusan === 'dapat_dilaksanakan') {
                $this->status_tinjauan = 'layak';
                $this->status = 'baru';
                // Nomor Permintaan dipastikan terisi, nomor order HANYA dibuat setelah pembayaran
                if (empty($this->nomor_permintaan)) {
                    $this->nomor_permintaan = $this->generateNomorPermintaan($this->tanggal_masuk ?? '');
                }
            } else {
                $this->status_tinjauan = 'tidak_layak';
                $this->status = 'ditolak';
                $this->alasan_tolak = $alasanPenolakan;
                $this->ditolak_oleh = $userId;
                $this->tanggal_tolak = date('Y-m-d H:i:s');
                $this->status_tolak = 1;
            }
        }
        $this->save();

        return array(
            'order_id'  => $orderId,
            'keputusan' => $keputusan,
            'is_draft'  => $isDraft,
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
            "SELECT k.*, m.nama_metode AS master_metode_nama, 
                    COALESCE(k.durasi_nilai, m.durasi_nilai) AS durasi_nilai, 
                    COALESCE(k.durasi_satuan, m.durasi_satuan, 'Hari') AS durasi_satuan,
                    m.durasi_nilai AS master_durasi_nilai, 
                    m.durasi_satuan AS master_durasi_satuan 
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
    public function simpanKalkulasiLingkungan(int $orderId, array $items, float $diskon, ?string $tglSampel, int $userId, ?string $spmLayanan = null, ?int $spmDurasiNilai = null, ?string $spmDurasiSatuan = null, ?string $spmTriggerTipe = null): array {
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
            $durasiNilai= (isset($item['durasi_nilai']) && $item['durasi_nilai'] !== '' && $item['durasi_nilai'] !== null) ? (int)$item['durasi_nilai'] : null;
            $durasiSatuan = !empty($item['durasi_satuan']) ? trim($item['durasi_satuan']) : null;
            $durasiBulan= max(1, (int)($item['durasi_bulan'] ?? 1));
            $isSub      = !empty($item['is_subkontrak']) ? 1 : 0;
            $labEks     = !empty($item['lab_eksternal_id']) ? (int)$item['lab_eksternal_id'] : null;

            $totalBruto += $totalItem;
            if ($durasiBulan > $maxDurasiBulan) {
                $maxDurasiBulan = $durasiBulan;
            }

            $this->db->exec(
                "INSERT INTO opti_kalkulasi_uji_lingkungan 
                (order_id, sub_layanan, metode_uji_id, nama_pengujian, standar_rujukan, tarif_per_sampel, jumlah_sampel, total_biaya_item, durasi_nilai, durasi_satuan, durasi_bulan, is_subkontrak, lab_eksternal_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array(
                    1 => $orderId,
                    2 => $subLayanan,
                    3 => $metodeId,
                    4 => $namaUji,
                    5 => $standar,
                    6 => $tarif,
                    7 => $jumlah,
                    8 => $totalItem,
                    9 => $durasiNilai,
                    10 => $durasiSatuan,
                    11 => $durasiBulan,
                    12 => $isSub,
                    13 => $labEks
                )
            );
        }

        $totalNetto = max(0.0, $totalBruto - $diskon);

        // Update ke order_layanan
        $this->estimasi_biaya = $totalNetto;
        $this->diskon_penawaran = $diskon;
        if (!empty($tglSampel)) {
            $this->tanggal_terima_sampel = $tglSampel;
            $this->tanggal_spm_mulai     = $tglSampel;
        }
        if (!empty($spmLayanan)) {
            $this->spm_layanan = $spmLayanan;
        }
        if ($spmDurasiNilai !== null && $spmDurasiNilai > 0) {
            $this->spm_durasi_nilai = $spmDurasiNilai;
            if (empty($spmLayanan)) {
                $this->spm_layanan = "{$spmDurasiNilai} " . ($spmDurasiSatuan ?: 'Hari Kerja');
            }
        }
        if (!empty($spmDurasiSatuan)) {
            $this->spm_durasi_satuan = $spmDurasiSatuan;
        }
        if (!empty($spmTriggerTipe)) {
            $this->spm_trigger_tipe = $spmTriggerTipe;
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
     * Ambil daftar Ketua Tim dan penanggung jawab teknis untuk penunjukan pelaksana order
     */
    public static function getPICSpesialisasiList(\DB\SQL $db, ?string $divisi = null): array {
        $priorityUser = 0;
        if ($divisi === 'lingkungan') {
            $priorityUser = 61;
        } elseif ($divisi === 'selulosa') {
            $priorityUser = 3;
        }

        $sql = "SELECT DISTINCT u.id_user, u.login, u.nama_user, u.si_opti,
                       (CASE WHEN u.id_user = {$priorityUser} THEN 1 ELSE 0 END) AS is_priority,
                       (CASE WHEN u.si_opti LIKE '%ketua_tim%' THEN 1 ELSE 0 END) AS is_katim,
                       CASE 
                           WHEN u.si_opti LIKE '%ketua_tim%' THEN 'Ketua Tim'
                           ELSE 'Koordinator Pelaksana'
                       END AS role_label,
                       CASE 
                           WHEN u.si_opti LIKE '%lingkungan%' THEN 'lingkungan'
                           WHEN u.si_opti LIKE '%selulosa%' THEN 'selulosa'
                           WHEN m.jenis_layanan_opti IS NOT NULL THEN m.jenis_layanan_opti
                           ELSE 'semua'
                       END AS spesialisasi 
                FROM tb_arsipuser u 
                LEFT JOIN opti_user_map m ON u.id_user = m.id_user 
                WHERE (u.si_opti IN ('ketua_tim_selulosa', 'ketua_tim_lingkungan', 'katim_selulosa', 'katim_lingkungan') OR u.si_opti LIKE 'tim_kerja%' OR (m.role_opti IN ('ketua_tim', 'tim_kerja') AND m.jenis_layanan_opti IN ('selulosa', 'lingkungan', 'semua')))
                  AND (u.status = 1 OR u.status = '1' OR u.status = 'aktif')";
        
        $params = array();
        if (!empty($divisi) && in_array($divisi, array('selulosa', 'lingkungan'))) {
            $sql .= " AND (u.si_opti LIKE ? OR m.jenis_layanan_opti = ? OR m.jenis_layanan_opti = 'semua' OR u.si_opti IN ('ketua_tim_selulosa', 'ketua_tim_lingkungan')) ";
            $params[1] = "%{$divisi}%";
            $params[2] = $divisi;
        }

        if ($priorityUser > 0) {
            $sql .= " ORDER BY is_priority DESC, is_katim DESC, u.nama_user ASC";
        } else {
            $sql .= " ORDER BY is_katim DESC, u.nama_user ASC";
        }

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

    /**
     * Cek apakah order layanan sedang dalam status unlock per-order oleh Super Admin
     * Otomatis mengunci kembali jika durasi waktu habis (Auto-Lock Expired)
     */
    public function isOrderUnlocked(int $orderId): bool {
        if ($orderId <= 0) return false;
        $row = $this->db->exec("SELECT is_unlocked_edit, unlocked_until, unlocked_reason, nomor_order FROM order_layanan WHERE id = ?", [1 => $orderId]);
        if (!empty($row)) {
            $isUnlocked = ((int)$row[0]['is_unlocked_edit'] === 1);
            if ($isUnlocked && !empty($row[0]['unlocked_until'])) {
                $tz = new \DateTimeZone('Asia/Jakarta');
                $now = (new \DateTime('now', $tz))->getTimestamp();
                $until = (new \DateTime($row[0]['unlocked_until'], $tz))->getTimestamp();
                if ($until <= $now) {
                    // Durasi izin edit telah kedaluwarsa -> otomatis kunci kembali
                    $this->db->exec(
                        "UPDATE order_layanan SET is_unlocked_edit = 0, unlocked_reason = CONCAT(COALESCE(unlocked_reason, ''), ' [Otomatis terkunci karena batas waktu habis]') WHERE id = ?",
                        [1 => $orderId]
                    );
                    return false;
                }
            }
            return $isUnlocked;
        }
        return false;
    }

    /**
     * Buka / Kunci izin perubahan data (Unlock Per-Order) khusus Super Admin dengan auto-kunci countdown
     */
    public function toggleUnlockEdit(int $orderId, int $userId, ?string $reason = null, int $durationDays = 3): array {
        $this->load(['id = ?', $orderId]);
        if ($this->dry()) {
            throw new \Exception("Order #{$orderId} tidak ditemukan.");
        }

        $currentStatus = (int)($this->is_unlocked_edit ?? 0);
        $newStatus = ($currentStatus === 1) ? 0 : 1;
        $tz = new \DateTimeZone('Asia/Jakarta');

        $this->is_unlocked_edit = $newStatus;
        if ($newStatus === 1) {
            $durationDays = max(1, $durationDays > 0 ? $durationDays : 3);
            $dtNow = new \DateTime('now', $tz);
            $this->unlocked_by = $userId;
            $this->unlocked_at = $dtNow->format('Y-m-d H:i:s');
            $this->unlocked_days = $durationDays;

            $dtUntil = clone $dtNow;
            $dtUntil->modify("+{$durationDays} days");
            $this->unlocked_until = $dtUntil->format('Y-m-d H:i:s');
            $this->unlocked_reason = !empty($reason) ? trim($reason) : 'Izin perubahan data diberikan oleh Super Admin';
        } else {
            // Ketika dikunci kembali
            $this->unlocked_by = null;
            $this->unlocked_at = null;
            $this->unlocked_until = null;
            $this->unlocked_days = null;
            $this->unlocked_reason = !empty($reason) ? trim($reason) : 'Akses edit ditutup kembali oleh Super Admin';
        }
        $this->save();

        return [
            'id' => $orderId,
            'is_unlocked_edit' => $newStatus,
            'unlocked_reason' => $this->unlocked_reason,
            'unlocked_until' => $this->unlocked_until,
            'unlocked_days' => $this->unlocked_days,
            'action_label' => ($newStatus === 1) ? 'dibuka' : 'dikunci'
        ];
    }
}
