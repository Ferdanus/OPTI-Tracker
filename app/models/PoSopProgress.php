<?php

/**
 * Model PoSopProgress
 * Mengelola 19 Aktivitas/Tahapan Alur SOP Jasa Pelayanan OPTI Lingkungan
 * Berdasarkan Diagram Standar Operasional Prosedur (SOP) BBSPJIS
 */
class PoSopProgress extends \DB\SQL\Mapper {

    public static $STANDARD_STEPS_LINGKUNGAN = array(
        1 => array(
            'tahap_no'          => 1,
            'fase'              => 'persiapan',
            'nama_aktivitas'    => 'Menginformasikan dokumen Persetujuan Penawaran kepada Ketua Tim',
            'pelaksana_kode'    => 'tim_mitra',
            'pelaksana_label'   => 'Ka.Tim Mitra Industri',
            'mutu_kelengkapan'  => 'Penawaran dan kontrak kerjasama',
            'mutu_waktu'        => '30 Menit',
            'mutu_output'       => 'Penawaran dan kontrak kerjasama',
            'keterangan'        => 'Pemberitahuan dokumen penawaran dan kontrak kepada Ketua Tim OPTI',
            'is_decision'       => 0
        ),
        2 => array(
            'tahap_no'          => 2,
            'fase'              => 'persiapan',
            'nama_aktivitas'    => 'Membentuk tim kerja',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'Penawaran dan kontrak kerjasama',
            'mutu_waktu'        => '2,5 Jam',
            'mutu_output'       => 'Draft Tim Kerja',
            'keterangan'        => 'Penetapan susunan personil analis & tim pelaksana',
            'is_decision'       => 0
        ),
        3 => array(
            'tahap_no'          => 3,
            'fase'              => 'persiapan',
            'nama_aktivitas'    => 'Menyusun rencana kegiatan pelayanan jasa',
            'pelaksana_kode'    => 'tim_kerja',
            'pelaksana_label'   => 'Tim Kerja Pelaksana',
            'mutu_kelengkapan'  => 'Draft Tim Kerja',
            'mutu_waktu'        => '1 Hari',
            'mutu_output'       => 'Draft Rencana Kegiatan',
            'keterangan'        => 'Penyusunan jadwal kerja, metode sampling, dan alokasi alat',
            'is_decision'       => 0
        ),
        4 => array(
            'tahap_no'          => 4,
            'fase'              => 'persiapan',
            'nama_aktivitas'    => 'Menyampaikan usulan rencana kegiatan pelayanan jasa',
            'pelaksana_kode'    => 'tim_kerja',
            'pelaksana_label'   => 'Tim Kerja Pelaksana',
            'mutu_kelengkapan'  => 'Draft Rencana Kegiatan',
            'mutu_waktu'        => '30 Menit',
            'mutu_output'       => 'Draft Rencana Kegiatan Disampaikan',
            'keterangan'        => 'Penyerahan usulan rencana ke Ketua Tim OPTI',
            'is_decision'       => 0
        ),
        5 => array(
            'tahap_no'          => 5,
            'fase'              => 'persiapan',
            'nama_aktivitas'    => 'Memeriksa dan menandatangani usulan rencana kegiatan pelayanan jasa',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'Draft Rencana Kegiatan',
            'mutu_waktu'        => '2 Hari',
            'mutu_output'       => 'Rencana Kegiatan Disetujui',
            'keterangan'        => 'Pemeriksaan rencana kerja. Jika perlu revisi, kembali ke Tahap 3',
            'is_decision'       => 1
        ),
        6 => array(
            'tahap_no'          => 6,
            'fase'              => 'pelaksanaan',
            'nama_aktivitas'    => 'Melaksanakan kegiatan pelayanan jasa, menyampaikan draft laporan perkembangan / laporan akhir kepada Ketua Tim OPTI',
            'pelaksana_kode'    => 'tim_kerja',
            'pelaksana_label'   => 'Tim Kerja Pelaksana',
            'mutu_kelengkapan'  => 'Rencana Kegiatan',
            'mutu_waktu'        => 'Sesuai Kontrak / SPK',
            'mutu_output'       => 'Draft Laporan',
            'keterangan'        => 'Pelaksanaan sampling/analisis lapangan & penyusunan draft laporan',
            'is_decision'       => 0
        ),
        7 => array(
            'tahap_no'          => 7,
            'fase'              => 'pelaksanaan',
            'nama_aktivitas'    => 'Menyampaikan laporan perkembangan kepada pelanggan',
            'pelaksana_kode'    => 'tim_kerja',
            'pelaksana_label'   => 'Tim OPTI / Tim Kerja',
            'mutu_kelengkapan'  => 'Draft Laporan Perkembangan',
            'mutu_waktu'        => 'Sesuai Kebutuhan',
            'mutu_output'       => 'Draft Laporan Perkembangan',
            'keterangan'        => 'Dapat dilewati jika kontrak tidak mempersyaratkan laporan perkembangan',
            'is_decision'       => 0
        ),
        8 => array(
            'tahap_no'          => 8,
            'fase'              => 'pelaksanaan',
            'nama_aktivitas'    => 'Memeriksa draft laporan perkembangan',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'Draft Laporan Perkembangan',
            'mutu_waktu'        => 'Sesuai Kebutuhan',
            'mutu_output'       => 'Laporan Perkembangan Tervalidasi',
            'keterangan'        => 'Pemeriksaan draft perkembangan. Jika revisi, kembali ke Tahap 6',
            'is_decision'       => 1
        ),
        9 => array(
            'tahap_no'          => 9,
            'fase'              => 'pelaksanaan',
            'nama_aktivitas'    => 'Menyampaikan laporan perkembangan kepada pelanggan',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'Laporan Perkembangan',
            'mutu_waktu'        => 'Sesuai Kebutuhan',
            'mutu_output'       => 'Laporan Perkembangan Terkirim',
            'keterangan'        => 'Penyampaian laporan perkembangan ke pihak industri',
            'is_decision'       => 0
        ),
        10 => array(
            'tahap_no'          => 10,
            'fase'              => 'pelaksanaan',
            'nama_aktivitas'    => 'Menerima laporan perkembangan dan memberikan masukan',
            'pelaksana_kode'    => 'pelanggan',
            'pelaksana_label'   => 'Pelanggan / Industri',
            'mutu_kelengkapan'  => 'Laporan Perkembangan',
            'mutu_waktu'        => 'Sesuai Jadwal Rapat',
            'mutu_output'       => 'Notulen Masukan',
            'keterangan'        => 'Review dan masukan/evaluasi dari pelanggan',
            'is_decision'       => 0
        ),
        11 => array(
            'tahap_no'          => 11,
            'fase'              => 'pelaksanaan',
            'nama_aktivitas'    => 'Menerima hasil masukan dari pelanggan',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'Notulen',
            'mutu_waktu'        => 'Sesuai Jadwal',
            'mutu_output'       => 'Notulen Diterima',
            'keterangan'        => 'Penerimaan notulen untuk diteruskan ke tim pelaksana',
            'is_decision'       => 0
        ),
        12 => array(
            'tahap_no'          => 12,
            'fase'              => 'pelaksanaan',
            'nama_aktivitas'    => 'Menindaklanjuti masukan dari pelanggan, melanjutkan kegiatan, menyusun draft laporan kegiatan',
            'pelaksana_kode'    => 'tim_kerja',
            'pelaksana_label'   => 'Tim Kerja Pelaksana',
            'mutu_kelengkapan'  => 'Notulen',
            'mutu_waktu'        => 'Sesuai SPK',
            'mutu_output'       => 'Draft Laporan Kegiatan Final',
            'keterangan'        => 'Penyempurnaan pengujian dan finalisasi draft laporan kegiatan',
            'is_decision'       => 0
        ),
        13 => array(
            'tahap_no'          => 13,
            'fase'              => 'pengesahan',
            'nama_aktivitas'    => 'Memeriksa draft laporan kegiatan',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'Draft Laporan Kegiatan',
            'mutu_waktu'        => '3 Hari',
            'mutu_output'       => 'Draft Laporan Kegiatan Tervalidasi',
            'keterangan'        => 'Pemeriksaan laporan. Jika perlu perbaikan, kembali ke Tahap 12',
            'is_decision'       => 1
        ),
        14 => array(
            'tahap_no'          => 14,
            'fase'              => 'pengesahan',
            'nama_aktivitas'    => 'Memeriksa dan menandatangani draft laporan kegiatan',
            'pelaksana_kode'    => 'kepala',
            'pelaksana_label'   => 'Kepala Balai Besar',
            'mutu_kelengkapan'  => 'Draft Laporan Kegiatan',
            'mutu_waktu'        => '2 Hari',
            'mutu_output'       => 'Laporan Kegiatan Sah (Ditandatangani)',
            'keterangan'        => 'Pemeriksaan & tanda tangan Kepala Balai. Jika ada catatan, kembali ke Tahap 13',
            'is_decision'       => 1
        ),
        15 => array(
            'tahap_no'          => 15,
            'fase'              => 'bast',
            'nama_aktivitas'    => 'Menerima laporan akhir dan meminta tim mitra industri untuk membuat BAST',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'Laporan Kegiatan, BAST',
            'mutu_waktu'        => '30 Menit',
            'mutu_output'       => 'Permintaan Penerbitan BAST',
            'keterangan'        => 'Instruksi pembuatan BAST ke Ka.Tim Mitra Industri',
            'is_decision'       => 0
        ),
        16 => array(
            'tahap_no'          => 16,
            'fase'              => 'bast',
            'nama_aktivitas'    => 'Membuat BAST dan menyerahkan kepada Ketua Tim OPTI',
            'pelaksana_kode'    => 'tim_mitra',
            'pelaksana_label'   => 'Ka.Tim Mitra Industri',
            'mutu_kelengkapan'  => 'Laporan Kegiatan, BAST',
            'mutu_waktu'        => '30 Menit',
            'mutu_output'       => 'Dokumen BAST Terbit',
            'keterangan'        => 'Penyusunan dokumen Berita Acara Serah Terima (BAST)',
            'is_decision'       => 0
        ),
        17 => array(
            'tahap_no'          => 17,
            'fase'              => 'bast',
            'nama_aktivitas'    => 'Menerima BAST dan menyerahkan kepada pelanggan',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'Laporan Kegiatan, BAST',
            'mutu_waktu'        => '3 Jam',
            'mutu_output'       => 'Dokumen BAST & Laporan Diserahkan',
            'keterangan'        => 'Penyerahan berkas BAST & laporan kegiatan ke pelanggan',
            'is_decision'       => 0
        ),
        18 => array(
            'tahap_no'          => 18,
            'fase'              => 'bast',
            'nama_aktivitas'    => 'Menerima laporan kegiatan dan BAST',
            'pelaksana_kode'    => 'pelanggan',
            'pelaksana_label'   => 'Pelanggan / Industri',
            'mutu_kelengkapan'  => 'Laporan Kegiatan, BAST',
            'mutu_waktu'        => '2 Hari',
            'mutu_output'       => 'BAST yang sudah ditandatangani',
            'keterangan'        => 'Pemeriksaan & tanda tangan BAST oleh pelanggan. Jika revisi, kembali ke Tahap 17',
            'is_decision'       => 1
        ),
        19 => array(
            'tahap_no'          => 19,
            'fase'              => 'bast',
            'nama_aktivitas'    => 'Menerima BAST yang sudah ditandatangani pelanggan dan menyimpan dokumentasi kegiatan',
            'pelaksana_kode'    => 'ketua_tim',
            'pelaksana_label'   => 'Ketua Tim OPTI',
            'mutu_kelengkapan'  => 'BAST yang sudah ditandatangani',
            'mutu_waktu'        => '30 Menit',
            'mutu_output'       => 'Dokumentasi Kegiatan Selesai',
            'keterangan'        => 'Pengarsipan BAST dan penutupan pekerjaan pelayanan OPTI (Total Mutu Baku: 10 Hari di luar waktu SPK)',
            'is_decision'       => 0
        )
    );

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_po_sop_progress');
    }

    /**
     * Inisialisasi 19 Tahapan SOP untuk sebuah PO jika belum ada
     */
    public function initForPo(int $poId, string $statusPo = 'belum_upload'): void {
        $count = (int)($this->db->exec("SELECT COUNT(*) AS c FROM opti_po_sop_progress WHERE po_id = ?", array(1 => $poId))[0]['c'] ?? 0);
        if ($count > 0) {
            return;
        }

        foreach (self::$STANDARD_STEPS_LINGKUNGAN as $no => $step) {
            $status = 'menunggu';
            $verifiedBy = null;
            $verifiedAt = null;

            // Jika status PO sudah on_proses atau lebih, tandai tahap 1-2 sebagai selesai awal
            if ($statusPo === 'on_proses' && $no <= 2) {
                $status = 'selesai';
                $verifiedBy = 'Sistem / Inisialisasi';
                $verifiedAt = date('Y-m-d H:i:s');
            } elseif ($statusPo === 'kembali_selesai') {
                $status = 'selesai';
                $verifiedBy = 'Sistem / Selesai';
                $verifiedAt = date('Y-m-d H:i:s');
            }

            $this->db->exec(
                "INSERT INTO opti_po_sop_progress 
                 (po_id, tahap_no, fase, nama_aktivitas, pelaksana_kode, pelaksana_label, mutu_kelengkapan, mutu_waktu, mutu_output, keterangan, is_decision, status, verified_by, verified_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array(
                    1  => $poId,
                    2  => $step['tahap_no'],
                    3  => $step['fase'],
                    4  => $step['nama_aktivitas'],
                    5  => $step['pelaksana_kode'],
                    6  => $step['pelaksana_label'],
                    7  => $step['mutu_kelengkapan'],
                    8  => $step['mutu_waktu'],
                    9  => $step['mutu_output'],
                    10 => $step['keterangan'],
                    11 => $step['is_decision'],
                    12 => $status,
                    13 => $verifiedBy,
                    14 => $verifiedAt
                )
            );
        }
    }

    /**
     * Ambil seluruh tahapan SOP untuk sebuah PO
     */
    public function getByPoId(int $poId): array {
        return $this->db->exec(
            "SELECT * FROM opti_po_sop_progress WHERE po_id = ? ORDER BY tahap_no ASC",
            array(1 => $poId)
        );
    }

    /**
     * Verifikasi sebuah tahapan SOP
     */
    public function verifikasiTahap(int $poId, int $tahapNo, string $verifiedBy, ?string $catatan = null): bool {
        $this->db->exec(
            "UPDATE opti_po_sop_progress 
             SET status = 'selesai', verified_by = ?, verified_at = NOW(), catatan = ?
             WHERE po_id = ? AND tahap_no = ?",
            array(
                1 => $verifiedBy,
                2 => $catatan,
                3 => $poId,
                4 => $tahapNo
            )
        );

        // Jika ada tahap berikutnya yang masih menunggu, ubah ke berjalan
        $nextTahap = $tahapNo + 1;
        $this->db->exec(
            "UPDATE opti_po_sop_progress 
             SET status = 'berjalan'
             WHERE po_id = ? AND tahap_no = ? AND status = 'menunggu'",
            array(1 => $poId, 2 => $nextTahap)
        );

        // Catat ke Log Status PO
        $logModel = new PoLogStatus($this->db);
        $namaTahap = self::$STANDARD_STEPS_LINGKUNGAN[$tahapNo]['nama_aktivitas'] ?? "Tahap {$tahapNo}";
        $logModel->catat($poId, null, 'sop_verifikasi', "Tahap {$tahapNo} ({$namaTahap}) diverifikasi oleh {$verifiedBy}.");

        return true;
    }

    /**
     * Tandai tahap SOP perlu revisi dan kembalikan ke tahap target
     */
    public function revisiTahap(int $poId, int $tahapNo, int $targetTahapKembali, string $requestedBy, string $catatanRevisi): bool {
        // Update tahap ini menjadi revisi
        $this->db->exec(
            "UPDATE opti_po_sop_progress 
             SET status = 'revisi', verified_by = ?, verified_at = NOW(), catatan = ?
             WHERE po_id = ? AND tahap_no = ?",
            array(
                1 => $requestedBy,
                2 => "Revisi: " . $catatanRevisi,
                3 => $poId,
                4 => $tahapNo
            )
        );

        // Kembalikan tahap target menjadi berjalan
        $this->db->exec(
            "UPDATE opti_po_sop_progress 
             SET status = 'berjalan'
             WHERE po_id = ? AND tahap_no = ?",
            array(1 => $poId, 2 => $targetTahapKembali)
        );

        // Catat ke Log Status PO
        $logModel = new PoLogStatus($this->db);
        $logModel->catat(
            $poId, 
            null, 
            'sop_revisi', 
            "Tahap {$tahapNo} memerlukan revisi dari {$requestedBy}: {$catatanRevisi}. Alur dikembalikan ke Tahap {$targetTahapKembali}."
        );

        return true;
    }

    /**
     * Lewati tahap laporan perkembangan (Tahap 7 s.d. 12) jika tidak dipersyaratkan di kontrak
     */
    public function skipPerkembangan(int $poId, string $actorName): bool {
        $this->db->exec(
            "UPDATE opti_po_sop_progress 
             SET status = 'dilewati', verified_by = ?, verified_at = NOW(), catatan = 'Dilewati: Kontrak tidak mempersyaratkan laporan perkembangan'
             WHERE po_id = ? AND tahap_no BETWEEN 7 AND 12",
            array(1 => $actorName, 2 => $poId)
        );

        // Aktifkan tahap 13 langsung
        $this->db->exec(
            "UPDATE opti_po_sop_progress 
             SET status = 'berjalan'
             WHERE po_id = ? AND tahap_no = 13",
            array(1 => $poId)
        );

        // Catat ke Log Status PO
        $logModel = new PoLogStatus($this->db);
        $logModel->catat(
            $poId, 
            null, 
            'sop_skip', 
            "Tahap 7 s.d. 12 (Laporan Perkembangan) dilewati oleh {$actorName}. Alur langsung ke Tahap 13 (Laporan Kegiatan)."
        );

        return true;
    }

    /**
     * Hitung statistik kemajuan SOP
     */
    public function getStatistik(int $poId): array {
        $rows = $this->getByPoId($poId);
        $total = count($rows);
        if ($total === 0) {
            return array(
                'total' => 0, 'selesai' => 0, 'berjalan' => 0, 'menunggu' => 0, 'revisi' => 0, 'dilewati' => 0,
                'persen' => 0, 'current_tahap' => 1
            );
        }

        $selesai = 0;
        $dilewati = 0;
        $revisi = 0;
        $berjalan = 0;
        $menunggu = 0;
        $currentTahap = 1;

        foreach ($rows as $r) {
            if ($r['status'] === 'selesai') {
                $selesai++;
            } elseif ($r['status'] === 'dilewati') {
                $dilewati++;
            } elseif ($r['status'] === 'revisi') {
                $revisi++;
                $currentTahap = (int)$r['tahap_no'];
            } elseif ($r['status'] === 'berjalan') {
                $berjalan++;
                $currentTahap = (int)$r['tahap_no'];
            } else {
                $menunggu++;
            }
        }

        if ($berjalan === 0 && $revisi === 0 && $selesai < $total) {
            // Temukan tahap pertama yang menunggu
            foreach ($rows as $r) {
                if ($r['status'] === 'menunggu') {
                    $currentTahap = (int)$r['tahap_no'];
                    break;
                }
            }
        } elseif ($selesai + $dilewati === $total) {
            $currentTahap = 19;
        }

        $persen = round((($selesai + $dilewati) / $total) * 100);

        return array(
            'total'         => $total,
            'selesai'       => $selesai,
            'dilewati'      => $dilewati,
            'revisi'        => $revisi,
            'berjalan'      => $berjalan,
            'menunggu'      => $menunggu,
            'persen'        => $persen,
            'current_tahap' => $currentTahap
        );
    }
}