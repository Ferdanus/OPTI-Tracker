<?php

require_once __DIR__ . '/../helpers/fpdf/fpdf.php';

if (!class_exists('SiloptiSuratPenawaranPdf')) {
    class SiloptiSuratPenawaranPdf extends \setasign\Fpdi\Fpdi {
        public $jenisLayanan = 'selulosa';
        public $isAttachmentPage = false;
        public $lampiranPath = '';
        public $lampiranMerged = true;
        public $useKop = true;

        public function Header() {
            // Header kosong sesuai spesifikasi: Kop surat di Halaman 1 adalah isi halaman, bukan header.
            // Halaman 2 mencetak nomor halaman "-2-" di body halaman.
            // Jika ada halaman lampiran gambar/dokumen (halaman 3+), cetak nomor halaman di header.
            if ($this->isAttachmentPage) {
                return;
            }
            if ($this->PageNo() >= 3) {
                $this->SetY(9);
                $this->SetFont('Arial', '', 11);
                $this->SetTextColor(0, 0, 0);
                $this->Cell(160, 4.46, '- ' . $this->PageNo() . ' -', 0, 1, 'C');
                $this->Ln(2);
            }
        }

        public function Footer() {
            // Footer kosong sesuai spesifikasi teknis
        }

        /**
         * Helper cetak paragraf dengan indentasi baris pertama (1.27 cm = 12.7 mm)
         * dan rata kiri (align left 'L') persis spesifikasi teknis
         */
        public function ParagraphWithIndent(string $text, float $indent = 12.7, float $lineH = 4.46, float $totalWidth = 160.0, float $leftMargin = 30.0)
        {
            $words = preg_split('/\s+/', trim($text));
            if (empty($words) || $words[0] === '') return;

            $isFirstLine = true;
            $currentLine = '';
            $maxW = $totalWidth - $indent;

            foreach ($words as $word) {
                $testLine = ($currentLine === '') ? $word : ($currentLine . ' ' . $word);
                if ($this->GetStringWidth($testLine) <= $maxW) {
                    $currentLine = $testLine;
                } else {
                    if ($isFirstLine) {
                        $this->SetXY($leftMargin + $indent, $this->GetY());
                        $this->Cell($maxW, $lineH, $currentLine, 0, 1, 'L');
                        $isFirstLine = false;
                        $maxW = $totalWidth;
                    } else {
                        $this->SetXY($leftMargin, $this->GetY());
                        $this->Cell($maxW, $lineH, $currentLine, 0, 1, 'L');
                    }
                    $currentLine = $word;
                }
            }
            if ($currentLine !== '') {
                if ($isFirstLine) {
                    $this->SetXY($leftMargin + $indent, $this->GetY());
                    $this->Cell($maxW, $lineH, $currentLine, 0, 1, 'L');
                } else {
                    $this->SetXY($leftMargin, $this->GetY());
                    $this->Cell($maxW, $lineH, $currentLine, 0, 1, 'L');
                }
            }
        }
    }
}

class SuratPenawaranController extends Controller
{


    /**
     * GET /surat-penawaran
     * CATATAN: sesuaikan nama tabel/kolom `customer` (nmcustomer) kalau beda di database kamu.
     */
    public function index($f3)
    {
        $currentYear   = date('Y');
        $filterTahun   = $f3->exists('GET.tahun') ? trim((string)$f3->get('GET.tahun')) : $currentYear;
        $filterBulan   = $f3->exists('GET.bulan') ? trim((string)$f3->get('GET.bulan')) : 'all';
        $daftarTahun   = range((int)$currentYear, (int)$currentYear - 4);
        $daftarBulan   = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember'
        ];

        $search        = trim((string) $f3->get('GET.q'));
        $filterLayanan = (string) $f3->get('GET.jenis_layanan');
        $activeTab     = $f3->exists('GET.tab') ? trim((string)$f3->get('GET.tab')) : 'aktif';
        if (!in_array($activeTab, ['aktif', 'nego', 'deal', 'batal', 'semua'])) {
            $activeTab = 'aktif';
        }

        $sql = "SELECT sp.*, COALESCE(c.nmcustomer, sp.perusahaan, '-') AS nmcustomer,
                       c.pt_cv,
                       o.nomor_permintaan,
                       o.nomor_order,
                       o.id AS order_id_real,
                       o.judul_kegiatan,
                       o.status AS order_status,
                       COALESCE(NULLIF(u.nama_user, ''), NULLIF(u_klaim.nama_user, ''), NULLIF(sp.pejabat_nama, ''), 'Tim Mitra') AS pembuat_nama,
                       sk.id_surat_keluar,
                       sk.nama_berkas_surat_keluar,
                       tk.sdm_tersedia,
                       tk.sdm_catatan,
                       tk.peralatan_tersedia,
                       tk.peralatan_catatan,
                       tk.bahan_tersedia,
                       tk.bahan_catatan,
                       tk.metode_tersedia,
                       tk.metode_catatan,
                       tk.keputusan AS tinjauan_keputusan,
                       (
                           SELECT sp_sub.nomor_surat 
                           FROM tb_surat_penawaran sp_sub 
                           WHERE sp_sub.order_id = sp.order_id AND sp_sub.id != sp.id AND sp_sub.status_respon_klien != 'batal'
                           ORDER BY sp_sub.id DESC LIMIT 1
                       ) AS nomor_surat_pengganti,
                       (
                           SELECT sp_sub.id 
                           FROM tb_surat_penawaran sp_sub 
                           WHERE sp_sub.order_id = sp.order_id AND sp_sub.id != sp.id AND sp_sub.status_respon_klien != 'batal'
                           ORDER BY sp_sub.id DESC LIMIT 1
                       ) AS id_surat_pengganti
                FROM tb_surat_penawaran sp
                LEFT JOIN tb_customer c ON c.id_customer = sp.customer_id
                LEFT JOIN order_layanan o ON o.id = sp.order_id
                LEFT JOIN tb_arsipuser u ON sp.dibuat_oleh = u.id_user
                LEFT JOIN tb_arsipuser u_klaim ON o.diklaim_oleh = u_klaim.id_user
                LEFT JOIN tb_surat_keluar sk ON (sk.no_surat = sp.nomor_surat AND sk.surat_penawaran = 'Y' AND sk.dipilih_sis_opti = 'Y')
                LEFT JOIN (
                    SELECT tk_sub.order_id, tk_sub.sdm_tersedia, tk_sub.sdm_catatan, tk_sub.peralatan_tersedia, tk_sub.peralatan_catatan,
                           tk_sub.bahan_tersedia, tk_sub.bahan_catatan, tk_sub.metode_tersedia, tk_sub.metode_catatan,
                           tk_sub.keputusan, tk_sub.alasan_penolakan, tk_sub.tanggal_tinjauan
                    FROM opti_tinjauan_kelayakan tk_sub
                    INNER JOIN (
                        SELECT MAX(id) AS max_id FROM opti_tinjauan_kelayakan GROUP BY order_id
                    ) tk_max ON tk_sub.id = tk_max.max_id
                ) tk ON tk.order_id = sp.order_id
                WHERE 1=1";
        $params = [];

        // Filter Tahun (sama seperti di daftar order)
        if (!empty($filterTahun) && $filterTahun !== 'all') {
            $sql      .= ' AND YEAR(COALESCE(sp.tanggal_surat, sp.created_at)) = ?';
            $params[]  = (int)$filterTahun;
        }

        // Filter Bulan
        if (!empty($filterBulan) && $filterBulan !== 'all') {
            $sql      .= ' AND MONTH(COALESCE(sp.tanggal_surat, sp.created_at)) = ?';
            $params[]  = (int)$filterBulan;
        }

        if ($search !== '') {
            $sql      .= ' AND (sp.nomor_surat LIKE ? OR sp.perihal LIKE ? OR c.nmcustomer LIKE ? OR sp.perusahaan LIKE ? OR o.nomor_order LIKE ? OR o.nomor_permintaan LIKE ?)';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
        }
        if ($filterLayanan !== '' && $filterLayanan !== 'all') {
            $sql      .= ' AND sp.jenis_layanan = ?';
            $params[]  = $filterLayanan;
        }
        $sql .= ' ORDER BY sp.tanggal_surat DESC, sp.id DESC';

        $allRows = $this->db->exec($sql, $params);

        // Normalisasi nama perusahaan agar tidak terjadi duplikasi "PT PT" dan teks bertumpuk
        foreach ($allRows as &$item) {
            $rawCust = trim($item['nmcustomer'] ?? '');
            $rawPer = trim($item['perusahaan'] ?? '');

            $cleanCust = \Customer::formatNamaPerusahaan($item['pt_cv'] ?? '', $rawCust);
            $cleanPer = \Customer::formatNamaPerusahaan('', $rawPer);

            $item['nmcustomer'] = $cleanCust;
            $item['perusahaan'] = $cleanPer;

            $coreCust = strtolower(\Customer::cleanNamaPerusahaan($cleanCust));
            $corePer = strtolower(\Customer::cleanNamaPerusahaan($cleanPer));

            if ($coreCust === $corePer || empty($cleanPer) || $cleanPer === '-') {
                $item['display_perusahaan'] = !empty($cleanCust) && $cleanCust !== '-' ? $cleanCust : $cleanPer;
                $item['sub_info'] = !empty($item['nama']) ? 'PIC: ' . $item['nama'] : '';
            } else {
                $item['display_perusahaan'] = $cleanCust;
                $item['sub_info'] = $cleanPer . (!empty($item['nama']) ? ' (PIC: ' . $item['nama'] . ')' : '');
            }

            // Deteksi catatan & kendala kaji kelayakan teknis (4 pilar)
            $catatanPilarList = [];
            if (!empty(trim($item['sdm_catatan'] ?? ''))) {
                $catatanPilarList[] = 'SDM: ' . trim($item['sdm_catatan']);
            }
            if (!empty(trim($item['peralatan_catatan'] ?? ''))) {
                $catatanPilarList[] = 'Alat: ' . trim($item['peralatan_catatan']);
            }
            if (!empty(trim($item['bahan_catatan'] ?? ''))) {
                $catatanPilarList[] = 'Bahan: ' . trim($item['bahan_catatan']);
            }
            if (!empty(trim($item['metode_catatan'] ?? ''))) {
                $catatanPilarList[] = 'Metode: ' . trim($item['metode_catatan']);
            }
            $item['catatan_pilar_list'] = $catatanPilarList;
            $item['has_catatan_tinjauan'] = !empty($catatanPilarList);
            $item['has_kendala_tinjauan'] = (isset($item['sdm_tersedia']) && empty($item['sdm_tersedia']))
                || (isset($item['peralatan_tersedia']) && empty($item['peralatan_tersedia']))
                || (isset($item['bahan_tersedia']) && empty($item['bahan_tersedia']))
                || (isset($item['metode_tersedia']) && empty($item['metode_tersedia']))
                || (($item['tinjauan_keputusan'] ?? '') === 'tidak_dapat_dilaksanakan');
        }
        unset($item);

        // Cari ID penawaran aktif / terbaru untuk setiap order
        $latestIdsPerOrder = [];
        foreach ($allRows as $r) {
            $ordKey = !empty($r['order_id']) ? (int)$r['order_id'] : 0;
            if ($ordKey > 0) {
                if ($r['status_respon_klien'] !== 'batal' && $r['status_respon_klien'] !== 'tolak' && $r['status'] !== 'batal' && $r['status'] !== 'nonaktif') {
                    if (!isset($latestIdsPerOrder[$ordKey]) || $r['id'] > $latestIdsPerOrder[$ordKey]) {
                        $latestIdsPerOrder[$ordKey] = (int)$r['id'];
                    }
                }
            }
        }

        $daftarAktif = [];
        $daftarNego = [];
        $daftarDeal = [];
        $daftarBatal = [];
        $daftarSemua = [];

        foreach ($allRows as $r) {
            $isBatal = ($r['status_respon_klien'] === 'batal' || $r['status_respon_klien'] === 'tolak' || $r['status'] === 'batal' || $r['status'] === 'nonaktif');
            $ordKey = !empty($r['order_id']) ? (int)$r['order_id'] : 0;
            $isOldReplaced = ($ordKey > 0 && isset($latestIdsPerOrder[$ordKey]) && (int)$r['id'] !== $latestIdsPerOrder[$ordKey]);
            $r['is_old_replaced'] = $isOldReplaced;

            if ($isBatal || $isOldReplaced) {
                $r['status_badge_type'] = 'batal';
                $daftarBatal[] = $r;
            } elseif ($r['status_respon_klien'] === 'deal' || $r['status'] === 'disetujui') {
                $r['status_badge_type'] = 'deal';
                $daftarDeal[] = $r;
            } elseif ($r['status_respon_klien'] === 'nego') {
                // Khusus surat yang sedang dalam tahap tawar-menawar/negosiasi
                $r['status_badge_type'] = 'nego';
                $daftarNego[] = $r;
            } else {
                // Penawaran aktif berjalan (Draft atau Terkirim / Menunggu Respon)
                $r['status_badge_type'] = $r['status_respon_klien'] ?: ($r['status'] ?: 'draft');
                $daftarAktif[] = $r;
            }
            $daftarSemua[] = $r;
        }

        $totalSurat    = count($daftarSemua);
        $totalAktif    = count($daftarAktif);
        $totalDeal     = count($daftarDeal);
        $totalNego     = count($daftarNego);
        $totalBatal    = count($daftarBatal);
        $totalTerkirim = count(array_filter($daftarAktif, function ($r) { return ($r['status'] === 'terkirim' || $r['status_respon_klien'] === 'terkirim' || $r['status_respon_klien'] === 'menunggu'); }));
        $totalDraft    = count(array_filter($daftarAktif, function ($r) { return ($r['status'] === 'draft' || $r['status_respon_klien'] === 'draft' || empty($r['status_respon_klien'])); }));

        // Ambil daftar order yang siap diterbitkan penawarannya (untuk modal pemilihan order)
        $sqlSiap = "SELECT o.id, o.nomor_permintaan, o.nomor_order, COALESCE(c.nmcustomer, '-') AS nama_perusahaan, o.jenis_layanan_opti, o.estimasi_biaya, 
                           o.status_proposal_biaya, o.status_penawaran, o.created_at, c.nmcustomer, c.pt_cv
                    FROM order_layanan o
                    LEFT JOIN tb_customer c ON c.id_customer = o.id_customer
                    WHERE (o.status_tinjauan = 'layak' OR o.status NOT IN ('permintaan_masuk', 'draft_disimpan'))
                      AND (
                          (o.jenis_layanan_opti = 'selulosa' AND o.status_proposal_biaya = 'siap_penawaran' AND o.id IN (SELECT order_id FROM opti_proposal_riset WHERE file_proposal IS NOT NULL AND file_proposal != '' AND estimasi_total_biaya > 0 AND status_proposal IN ('disetujui', 'disetujui_ketua', 'disetujui_pimpinan')))
                          OR
                          (o.jenis_layanan_opti = 'lingkungan' AND o.status_proposal_biaya = 'siap_penawaran')
                          OR
                          (o.jenis_layanan_opti NOT IN ('selulosa', 'lingkungan') AND (o.status_proposal_biaya = 'siap_penawaran' OR o.status_penawaran IN ('belum_ada', 'draft', '')))
                      )
                      AND (o.status_penawaran != 'deal' OR o.status_penawaran IS NULL)
                    ORDER BY o.id DESC LIMIT 20";
        $daftarOrderSiap = $this->db->exec($sqlSiap);
        foreach ($daftarOrderSiap as &$ord) {
            $ord['nama_perusahaan'] = \Customer::formatNamaPerusahaan($ord['pt_cv'] ?? '', $ord['nmcustomer'] ?? ($ord['nama_perusahaan'] ?? ''));
        }
        unset($ord);

        $f3->set('daftar_penawaran', $daftarAktif);
        $f3->set('daftar_aktif', $daftarAktif);
        $f3->set('daftar_nego', $daftarNego);
        $f3->set('daftar_deal', $daftarDeal);
        $f3->set('daftar_batal', $daftarBatal);
        $f3->set('daftar_semua', $daftarSemua);
        $f3->set('active_tab', $activeTab);

        $f3->set('daftar_order_siap', $daftarOrderSiap);
        $f3->set('total_surat', $totalSurat);
        $f3->set('total_aktif', $totalAktif);
        $f3->set('total_deal', $totalDeal);
        $f3->set('total_nego', $totalNego);
        $f3->set('total_batal', $totalBatal);
        $f3->set('total_terkirim', $totalTerkirim);
        $f3->set('total_draft', $totalDraft);
        $f3->set('search', $search);
        $f3->set('filter_layanan', $filterLayanan);
        $f3->set('filter_status', $filterStatus);
        $f3->set('filter_tahun', $filterTahun);
        $f3->set('daftar_tahun', $daftarTahun);
        $f3->set('filter_bulan', $filterBulan);
        $f3->set('daftar_bulan', $daftarBulan);
        $f3->set('tampilkan_semua', $tampilkanSemua);

        $this->render('tim_mitra/surat Pelayanan/index.html', 'Surat Penawaran', 'surat-penawaran');
    }
    public function edit($f3, $params)
    {
        $id = (int) ($params['id'] ?? 0);

        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');

        if ($id > 0) {
            $sp->load(['id = ?', $id]);
            // Jika surat penawaran terikat ke order, alihkan langsung ke form penawaran order modern
            if (!$sp->dry() && !empty($sp->order_id)) {
                $f3->reroute("/order/{$sp->order_id}/penawaran/buat");
                return;
            }
        }

        // kalau record baru (belum ada di DB), set default sesuai bisnis rule:
        // "Pegawai BBSPJIS" otomatis tercentang karena form ini diisi oleh staf
        if ($sp->dry()) {
            $sp->permintaan_melalui = 'pegawai_bbspjis';
            $sp->status = 'draft';
        }

        // daftar pegawai untuk dropdown (dari tb_arsipuser)
        $arsipUser = new DB\SQL\Mapper($db, 'tb_arsipuser');

$daftarPegawai = $arsipUser->find(
    null,
    ['order' => 'nama_user ASC']
);

        $isSubmitted = !$sp->dry() && !empty($sp->nomor_surat) && ($sp->status !== 'draft');
        $canEdit = ($this->hasPermission('penawaran:create') || $this->hasPermission('penawaran:edit') || $this->isSuperadmin() || $this->isAdminOrder()) && $this->canEditSubmittedData($isSubmitted);

        $f3->set('sp', $sp);
        $f3->set('can_edit', $canEdit);
        $f3->set('daftar_pegawai', $daftarPegawai);
        $f3->set('opsi_permintaan', [
            'telepon'          => 'Telepon',
            'fax'              => 'Fax',
            'surat'            => 'Surat',
            'email'            => 'E-mail',
            'whatsapp'         => 'WhatsApp',
            'website'          => 'Website',
            'datang_langsung'  => 'Datang langsung',
        ]);
        $f3->set('opsi_bidang', [
            'riset'            => 'Riset',
            'standardisasi'    => 'Standardisasi',
            'pengujian'        => 'Pengujian',
            'sertifikasi'      => 'Sertifikasi',
            'kalibrasi'        => 'Kalibrasi',
            'konsultansi'      => 'Konsultansi',
            'pelatihan_teknis' => 'Pelatihan Teknis',
            'perekayasaan'     => 'Perekayasaan',
            'lainnya'          => 'Lainnya',
        ]);
        $f3->set('opsi_kirim_ke', [
            'selulosa'    => 'Selulosa',
            'lingkungan'  => 'Lingkungan',
        ]);
        
        $this->render('tim_mitra/surat Pelayanan/form.html', 'Surat Penawaran', 'surat-penawaran');
    }
    public function show($f3, $params)
    {
        $id = (int) ($params['id'] ?? 0);
        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $sp->load(['id = ?', $id]);

        $f3->set('sp', $sp);
        echo \Template::instance()->render('surat_penawaran/show.htm');
    }
    public function store($f3, $params)
    {
        $id     = (int) ($params['id'] ?? 0);
        $action = $f3->get('POST.action'); // 'simpan' | 'kirim'

        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');

        if ($id > 0) {
            $sp->load(['id = ?', $id]);
        }

        $spModel = new SuratPenawaran($db);

        $sp->nama               = $f3->get('POST.nama');
        $sp->perusahaan         = $f3->get('POST.perusahaan');
        $sp->alamat             = $f3->get('POST.alamat');
        $sp->permintaan_melalui = $f3->get('POST.permintaan_melalui') ?: 'email';

        $jenisLayanan           = $f3->get('POST.jenis_layanan') ?: 'selulosa';
        $sp->jenis_layanan      = $jenisLayanan;
        $sp->bidang             = ($jenisLayanan === 'selulosa') ? 'riset' : 'pengujian';

        $tglSurat               = $f3->get('POST.tanggal_surat') ?: date('Y-m-d');
        $sp->tanggal_surat      = $tglSurat;

        $nomorSurat             = trim((string)$f3->get('POST.nomor_surat'));
        if (empty($nomorSurat)) {
            $nomorSurat = $spModel->generateNomorSurat($tglSurat);
        }
        $sp->nomor_surat        = $nomorSurat;

        $sp->perihal            = $f3->get('POST.perihal') ?: 'Penawaran Kerjasama Layanan Optimalisasi Teknologi Industri (OPTI)';
        $nominalRaw             = preg_replace('/[^0-9]/', '', (string)$f3->get('POST.nominal_penawaran'));
        $sp->nominal_penawaran  = (float)($nominalRaw ?: 0);
        $sp->penjelasan         = $f3->get('POST.penjelasan');
        $sp->status_respon_klien= $f3->get('POST.status_respon_klien') ?: 'draft';

        $isNew = $sp->dry();

        if ($action === 'kirim') {
            $sp->status = 'terkirim';
            $sp->status_respon_klien = 'terkirim';
            $sp->updated_at = date('Y-m-d H:i:s');
            if ($isNew) {
                $sp->created_at = date('Y-m-d H:i:s');
            }
            $f3->set('SESSION.flash_success', "Surat penawaran <strong>{$nomorSurat}</strong> berhasil diterbitkan dan dikirim ke klien.");
        } else {
            $sp->status = $sp->status ?: 'draft';
            if ($isNew) {
                $sp->created_at = date('Y-m-d H:i:s');
            }
            $f3->set('SESSION.flash_success', "Draf surat penawaran <strong>{$nomorSurat}</strong> berhasil disimpan.");
        }

        $orderId = (int)($f3->get('POST.order_id') ?: ($sp->order_id ?? 0));
        if ($orderId > 0) {
            $sp->order_id = $orderId;
        }

        $sp->save();

        // Sinkronisasi ke tb_surat_keluar
        $idSuratMasuk = 0;
        if ($orderId > 0) {
            $ordRow = $db->exec("SELECT id_surat_masuk, id_customer FROM order_layanan WHERE id = ?", [1 => $orderId]);
            if (!empty($ordRow)) {
                $idSuratMasuk = (int)($ordRow[0]['id_surat_masuk'] ?? 0);
                if (empty($sp->customer_id) && !empty($ordRow[0]['id_customer'])) {
                    $sp->customer_id = $ordRow[0]['id_customer'];
                }
            }
        }
        $spModel->syncKeSuratKeluar([
            'nomor_surat'        => $sp->nomor_surat,
            'tanggal_surat'      => $sp->tanggal_surat,
            'perihal'            => $sp->perihal,
            'customer_id'        => $sp->customer_id ?? 0,
            'id_arsip'           => $idSuratMasuk,
            'file_lampiran'      => $sp->file_lampiran ?? '',
            'nominal_penawaran'  => $sp->nominal_penawaran ?? 0
        ], $this->dbSekretariat);

        if ($orderId > 0) {
            $orderModel = new OrderLayanan($db);
            $order = $orderModel->getById($orderId);
            if ($order && !$order->dry()) {
                $order->jenis_layanan_opti = $jenisLayanan;
                if (!empty($sp->nama)) $order->pic = $sp->nama;
                if (!empty($sp->alamat)) $order->alamat = $sp->alamat;
                if (!empty($sp->perusahaan)) $order->nama_perusahaan = $sp->perusahaan;
                if (!empty($sp->penjelasan)) $order->deskripsi = $sp->penjelasan;
                if ($action === 'kirim') {
                    $order->status = 'baru';
                    $order->status_tinjauan = 'belum_ditinjau';
                }
                $order->save();
            }
            $f3->reroute("/order/{$orderId}");
            return;
        }

        $f3->reroute('/surat-penawaran');
    }

    public function tambah($f3)
    {
        $orderId = (int)($f3->get('GET.order_id') ?? 0);
        if ($orderId > 0) {
            $f3->reroute("/order/{$orderId}/penawaran/buat");
            return;
        }
        $f3->reroute('/surat-penawaran?buka_modal=1');
    }

    public function create($f3)
    {
        $this->tambah($f3);
    }

    public function simpan($f3, $params)
    {
        $this->store($f3, $params);
    }

    public function update($f3, $params)
    {
        $this->store($f3, $params);
    }

    public function toggleStatus($f3, $params)
    {
        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $sp->load(['id = ?', $params['id']]);

        if (!$sp->dry()) {
            $sp->status = $sp->status === 'aktif' ? 'nonaktif' : 'aktif';
            $sp->save();
            $f3->set('SESSION.flash_success', 'Status surat penawaran berhasil diubah.');
        }

        $f3->reroute('/surat-penawaran');
    }

    public function hapus($f3, $params)
    {
        $this->delete($f3, $params);
    }

    public function delete($f3, $params)
    {
        $this->requirePermission('order:edit', '/surat-penawaran');

        $spId = (int)($params['id'] ?? 0);
        $db = $this->db ?? $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $sp->load(['id = ?', $spId]);

        $orderId = 0;
        if (!$sp->dry()) {
            $orderId = (int)($sp['order_id'] ?? 0);
            $nomorSurat = $sp['nomor_surat'] ?? "ID #{$spId}";

            // Hapus surat penawaran
            $sp->erase();

            // Hapus dari tb_surat_keluar
            $idArsip = 0;
            if ($orderId > 0) {
                $orderRow = $db->exec("SELECT id_surat_masuk FROM order_layanan WHERE id = ?", [1 => $orderId]);
                $idArsip = !empty($orderRow) ? (int)($orderRow[0]['id_surat_masuk'] ?? 0) : 0;
            }
            $spModel = new SuratPenawaran($db);
            $spModel->hapusDariSuratKeluar($nomorSurat, $idArsip, $this->dbSekretariat);

            // Kembalikan status order jika terhubung
            if ($orderId > 0) {
                $orderRow = $db->exec("SELECT surat_penawaran_id FROM order_layanan WHERE id = ?", [1 => $orderId]);
                $currentSpId = !empty($orderRow) ? (int)($orderRow[0]['surat_penawaran_id'] ?? 0) : 0;
                if ($currentSpId === $spId) {
                    $prevSp = $db->exec("SELECT id, status_respon_klien, nominal_penawaran FROM tb_surat_penawaran WHERE order_id = ? AND id != ? ORDER BY id DESC LIMIT 1", [1 => $orderId, 2 => $spId]);
                    if (!empty($prevSp)) {
                        $db->exec("UPDATE order_layanan SET surat_penawaran_id = ?, status_penawaran = ?, estimasi_biaya = ? WHERE id = ?", [
                            1 => $prevSp[0]['id'],
                            2 => $prevSp[0]['status_respon_klien'],
                            3 => $prevSp[0]['nominal_penawaran'],
                            4 => $orderId
                        ]);
                    } else {
                        $db->exec(
                            "UPDATE order_layanan SET status_penawaran = 'belum_ada', surat_penawaran_id = NULL WHERE id = ?",
                            [$orderId]
                        );
                    }
                }
            }

            $this->logActivity($orderId, 'surat_penawaran', 'hapus', "Menghapus Surat Penawaran {$nomorSurat}");
            $this->setFlashSuccess("Surat Penawaran {$nomorSurat} berhasil dihapus.");
        } else {
            $this->setFlashError("Surat Penawaran tidak ditemukan.");
        }

        $redirect = $f3->get('GET.redirect') ?? $f3->get('POST.redirect') ?? '';
        if ($redirect === 'order' && $orderId > 0) {
            $f3->reroute("/order/{$orderId}");
            return;
        }

        $f3->reroute('/surat-penawaran');
    }

    /**
     * Menampilkan form pembuatan Surat Penawaran ter-prefill dari data Order
     * Route: GET /order/@id/penawaran/buat
     */
    public function tambahDariOrder($f3, $params)
    {
        $orderId = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$orderId} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        // 1. Validasi Prasyarat Tinjauan Permintaan / Kontrak (Tahap 2 - Khusus Selulosa)
        $tinjauan = $orderModel->getTinjauanKelayakan($orderId);
        $isSelulosa = (($order['jenis_layanan_opti'] ?? '') === 'selulosa');
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak') ||
            !$isSelulosa
        );
        if (!$tinjauanSelesai) {
            $this->setFlashError("Gagal: Tinjauan Permintaan / Kontrak (Tahap 2) belum selesai atau tidak memenuhi syarat.");
            $f3->reroute("/order/{$orderId}");
            return;
        }

        // 2. Validasi Prasyarat Dokumen Proposal (Khusus OPTI Selulosa)
        if (($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
            $proposalCheck = $orderModel->getProposalRiset($orderId);
            $proposalValid = !empty($proposalCheck) && 
                !empty($proposalCheck['file_proposal']) && 
                (float)($proposalCheck['estimasi_total_biaya'] ?? 0) > 0;
            $proposalApproved = $proposalValid && (
                in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) ||
                in_array($proposalCheck['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])
            );

            if (!$proposalValid || !$proposalApproved) {
                $this->setFlashError("Gagal: Untuk OPTI Selulosa, dokumen proposal teknis harus diisi, berkas dokumen proposal wajib diunggah, dan telah disetujui (ACC) oleh Ketua Tim OPTI sebelum dapat menerbitkan Surat Penawaran.");
                $f3->reroute("/order/{$orderId}");
                return;
            }
        }

        // Sinkronisasi data dari Surat Masuk jika order berasal dari klaim surat
        if (!empty($order['id_surat_masuk'])) {
            $suratMasukData = null;
            try {
                $repoSurat = new \SuratMasukRepository($this->db, $this->dbSekretariat);
                $suratMasukData = $repoSurat->getSuratById((int)$order['id_surat_masuk']);
            } catch (\Exception $e) {}

            if ($suratMasukData) {
                $picSurat = trim($suratMasukData['pic_pengirim'] ?? ($suratMasukData['nama_pengirim'] ?? ''));
                if (!empty($picSurat)) {
                    $order['pic'] = $picSurat;
                }
                $alamatSurat = trim($suratMasukData['alamat_pengirim'] ?? '');
                if (!empty($alamatSurat)) {
                    $order['alamat'] = $alamatSurat;
                }
                if (!empty($suratMasukData['pengirim'])) {
                    $rawPengirim = trim($suratMasukData['pengirim']);
                    $ptCv = trim($suratMasukData['pt_cv'] ?? ($order['pt_cv'] ?? ''));
                    $pengirimTanpaPT = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*)+/i', '', $rawPengirim);
                    $prefix = !empty($ptCv) ? strtoupper($ptCv) . ' ' : (preg_match('/^(PT|CV|UD|PD|Yayasan)\.?\s*/i', $rawPengirim, $mb) ? strtoupper($mb[1]) . ' ' : '');
                    $order['nama_perusahaan'] = trim($prefix . $pengirimTanpaPT);
                    $order['nmcustomer'] = $order['nama_perusahaan'];
                    $order['pt_cv'] = trim($ptCv ?: (preg_match('/^(PT|CV|UD|PD|Yayasan)/i', $prefix, $mb2) ? $mb2[1] : ''));
                }
            }
        }

        // Ambil alamat customer terlengkap dari database tb_customer
        $alamatCust = '';
        if (!empty($order['id_customer'])) {
            $custRow = $this->db->exec("SELECT nmcustomer, pt_cv, contactperson, contactperson_opti, nama_pribadi, alamatcustomer, alamatcustomer_baru, lokasi_pabrik, kode_pos FROM tb_customer WHERE id_customer = ?", [1 => (int)$order['id_customer']]);
            if (!empty($custRow)) {
                $cR = $custRow[0];
                $alamatCust = trim($cR['alamatcustomer_baru'] ?: ($cR['alamatcustomer'] ?: ($cR['lokasi_pabrik'] ?: '')));
                if (empty($order['pic']) || $order['pic'] === '-') {
                    $order['pic'] = trim($cR['contactperson_opti'] ?: ($cR['contactperson'] ?: ($cR['nama_pribadi'] ?: '-')));
                }
            }
        }
        if (empty($alamatCust) && !empty($order['alamat']) && $order['alamat'] !== '-') {
            $alamatCust = trim($order['alamat']);
        }
        $order['alamat_lengkap'] = $alamatCust;
        $order['alamatcustomer'] = $alamatCust;
        if (empty($order['alamat']) || $order['alamat'] === '-') {
            $order['alamat'] = $alamatCust;
        }

        $spModel = new SuratPenawaran($this->db);
        $allSp = $spModel->getAllByOrderId($orderId);
        $spIdReq = (int)($f3->get('GET.sp_id') ?? 0);
        $spExisting = null;
        if ($spIdReq > 0) {
            $spCandidate = $spModel->getById($spIdReq);
            if ($spCandidate && (int)($spCandidate['order_id'] ?? 0) === $orderId) {
                $spExisting = $spCandidate;
            }
        }
        if (!$spExisting) {
            $spExisting = $spModel->getByOrderId($orderId);
        }

        // Bersihkan nama perusahaan dari duplikasi "PT PT"
        if (!empty($spExisting['perusahaan'])) {
            $spExisting['perusahaan'] = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*){2,}/i', '$1 ', $spExisting['perusahaan']);
        }
        if (!empty($order['nama_perusahaan'])) {
            $order['nama_perusahaan'] = preg_replace('/^((?:PT|CV|UD|PD|Yayasan)\.?\s*){2,}/i', '$1 ', $order['nama_perusahaan']);
        }

        $mode = $f3->get('GET.mode') ?? '';
        // Hanya buat revisi baru jika ada permintaan eksplisit mode='baru' atau mode='revisi'
        $isRevisiBaru = !empty($spExisting) && ($mode === 'baru' || $mode === 'revisi');
        if (!empty($spExisting['nomor_surat']) && preg_match('/^\d+\/BBSPJIS\/MS\//i', $spExisting['nomor_surat'])) {
            $spExisting['nomor_surat'] = 'B/' . $spExisting['nomor_surat'];
        }
        $nomorSuratOtomatis = $isRevisiBaru ? $spModel->generateNomorSurat() : (!empty($spExisting['nomor_surat']) ? $spExisting['nomor_surat'] : $spModel->generateNomorSurat());
        if (preg_match('/^\d+\/BBSPJIS\/MS\//i', $nomorSuratOtomatis)) {
            $nomorSuratOtomatis = 'B/' . $nomorSuratOtomatis;
        }
        $revisiKe = count($allSp) + 1;

        $proposal = ($order['jenis_layanan_opti'] === 'selulosa') ? $orderModel->getProposalRiset($orderId) : null;
        $kalkulasi = ($order['jenis_layanan_opti'] === 'lingkungan') ? $orderModel->getKalkulasiLingkungan($orderId) : [];

        $durasiHari = 30;
        $spmVal = trim($order['spm_layanan'] ?? '');
        if (preg_match('/^(\d+)/', $spmVal, $m)) {
            $durasiHari = (int)$m[1];
        } elseif (strpos(strtolower($spmVal), 'bulan') !== false) {
            if (preg_match('/(\d+)\s*bulan/i', $spmVal, $mb)) {
                $durasiHari = (int)$mb[1] * 20;
            }
        } elseif (isset(\OrderLayanan::$SPM_LIST[$spmVal])) {
            $durasiStandar = \OrderLayanan::$SPM_LIST[$spmVal];
            if (preg_match('/(\d+)\s*bulan/i', $durasiStandar, $mb)) {
                $durasiHari = (int)$mb[1] * 20;
            }
        }
        $f3->set('durasi_hari', $durasiHari);

        $canEdit = ($this->hasPermission('penawaran:create') || $this->hasPermission('penawaran:edit') || $this->isSuperadmin() || $this->isAdminOrder());

        $defaultNominal = 0;
        if ($order['jenis_layanan_opti'] === 'lingkungan' && !empty($kalkulasi)) {
            $totalBruto = 0;
            foreach ($kalkulasi as $kIt) {
                $totalBruto += (float)$kIt['total_biaya_item'];
            }
            $diskonPenawaran = (float)($order['diskon_penawaran'] ?? 0);
            $defaultNominal = max(0.0, $totalBruto - $diskonPenawaran);
        }
        $f3->set('default_nominal', $defaultNominal);

        $defaultRuangLingkup = '';
        $f3->set('default_ruang_lingkup', $defaultRuangLingkup);

        if ($order['jenis_layanan_opti'] === 'lingkungan') {
            $f3->set('default_pejabat_nama', 'Joko Pratomo');
            $f3->set('default_jabatan_pejabat', 'Kepala Bagian Tata Usaha');
            $f3->set('default_hal', '');
            $f3->set('default_lampiran_teks', '1 (satu) lembar');
        } else {
            $f3->set('default_pejabat_nama', 'Dodiet Prasetyo');
            $f3->set('default_jabatan_pejabat', 'Kepala');
            $f3->set('default_hal', 'Biaya OPTI');
            $f3->set('default_lampiran_teks', '1 (satu) lembar');
        }

        list($penandatanganList, $jabatanList) = $this->getMasterPenandatanganData();
        $f3->set('daftar_penandatangan', $penandatanganList);
        $f3->set('daftar_jabatan', $jabatanList);

        $f3->set('order', $order);
        $f3->set('sp_existing', $spExisting);
        $lampiranUserExisting = !empty($spExisting['file_lampiran_user'])
            ? str_replace('\\', '/', (string)$spExisting['file_lampiran_user'])
            : str_replace('\\', '/', (string)($_SESSION['pending_lampiran_sp_' . $orderId] ?? ''));
        // Khusus OPTI Selulosa: lampiran hal. 3 langsung menyesuaikan dokumen yang diunggah PIC di Tahap 4
        if (($order['jenis_layanan_opti'] ?? '') === 'selulosa' && !empty($proposal['file_proposal'])) {
            if (empty($_SESSION['pending_lampiran_sp_' . $orderId])) {
                $lampiranUserExisting = str_replace('\\', '/', (string)$proposal['file_proposal']);
            }
        }
        $f3->set('lampiran_user_existing', $lampiranUserExisting);
        $f3->set('all_sp', $allSp);
        $f3->set('is_revisi_baru', $isRevisiBaru);
        $f3->set('revisi_ke', $revisiKe);
        $f3->set('proposal', $proposal);
        $f3->set('kalkulasi', $kalkulasi);
        $f3->set('tinjauan', $tinjauan);

        $hasCatatanKelayakan = false;
        $hasKendalaKelayakan = false;
        if (!empty($tinjauan)) {
            $hasCatatanKelayakan = !empty(trim($tinjauan['sdm_catatan'] ?? ''))
                || !empty(trim($tinjauan['peralatan_catatan'] ?? ''))
                || !empty(trim($tinjauan['bahan_catatan'] ?? ''))
                || !empty(trim($tinjauan['metode_catatan'] ?? ''))
                || !empty(trim($tinjauan['alasan_penolakan'] ?? ''));
            $hasKendalaKelayakan = (isset($tinjauan['sdm_tersedia']) && empty($tinjauan['sdm_tersedia']))
                || (isset($tinjauan['peralatan_tersedia']) && empty($tinjauan['peralatan_tersedia']))
                || (isset($tinjauan['bahan_tersedia']) && empty($tinjauan['bahan_tersedia']))
                || (isset($tinjauan['metode_tersedia']) && empty($tinjauan['metode_tersedia']))
                || (($tinjauan['keputusan'] ?? '') === 'tidak_dapat_dilaksanakan');
        }
        $f3->set('has_catatan_kelayakan', $hasCatatanKelayakan);
        $f3->set('has_kendala_kelayakan', $hasKendalaKelayakan);

        $pembuatNama = $_SESSION['user']['nama'] ?? ($_SESSION['nama_lengkap'] ?? ($spExisting['pembuat_nama'] ?? 'Tim Mitra BBSPJIS'));
        $f3->set('nomor_surat_otomatis', $nomorSuratOtomatis);
        $f3->set('pembuat_nama', $pembuatNama);
        $f3->set('can_edit', $canEdit);

        // State Guard: Cek apakah penawaran sudah DEAL dan order telah masuk ke pembayaran / pelaksanaan
        $isLockedDeal = false;
        $orderHasDeal = (($order['status_penawaran'] ?? '') === 'deal') || (!empty($spExisting) && ($spExisting['status_respon_klien'] ?? '') === 'deal');
        if ($orderHasDeal) {
            $isLunas = ($order['status_keuangan'] ?? '') === 'lunas';
            $hasBayar = !empty($this->db->exec("SELECT id FROM opti_pembayaran WHERE order_id = ? LIMIT 1", [1 => $orderId]));
            $hasPo = !empty($this->db->exec("SELECT id FROM po_kegiatan WHERE order_id = ? LIMIT 1", [1 => $orderId]));
            $isPelaksanaan = in_array($order['status_pelaksanaan'] ?? '', ['sedang_berjalan', 'evaluasi_laporan', 'revisi_laporan', 'laporan_selesai']);
            if ($isLunas || $hasBayar || $hasPo || $isPelaksanaan || ($order['status_penawaran'] ?? '') === 'deal') {
                $isLockedDeal = true;
                $isRevisiBaru = false;
            }
        }
        $f3->set('is_locked_deal', $isLockedDeal);
        $f3->set('is_revisi_baru', $isRevisiBaru);

        // Audit Tahap 5: Surat Penawaran resmi dibuka / dikelola Tim Mitra (First Time Only)
        $userId = (int)$this->getUserId();
        if ($userId > 0) {
            \StageAudit::recordDibaca($this->db, $orderId, 5, $userId, $pembuatNama, 'Tim Mitra');
            try {
                $hasLogged = $this->db->exec(
                    "SELECT id FROM opti_activity_log WHERE order_id = ? AND user_id = ? AND aksi = 'melihat_tahap_5' LIMIT 1",
                    [1 => $orderId, 2 => $userId]
                );
                if (empty($hasLogged)) {
                    $isDeal = in_array($order['status'] ?? '', ['penawaran_deal', 'pembayaran', 'proses_uji', 'selesai']) || ($order['status_penawaran'] ?? '') === 'deal';
                    $deskripsi = $isDeal ? 'Melihat penawaran DEAL' : 'Melihat surat penawaran';
                    $this->logActivity($orderId, 'penawaran', 'melihat_tahap_5', $deskripsi);
                }
            } catch (\Exception $e) {}
        }

        $this->render('tim_mitra/surat Pelayanan/form_order.html', "Terbitkan Surat Penawaran - Order #{$order['nomor_order']}", 'surat-penawaran');
    }

    /**
     * Menyimpan Surat Penawaran yang diterbitkan dari Order
     * Route: POST /order/@id/penawaran/simpan
     */
    public function simpanDariOrder($f3, $params)
    {
        $orderId = (int)($params['id'] ?? 0);

        if (!$this->hasPermission('penawaran:create') && !$this->isSuperadmin() && !$this->isAdminOrder()) {
            $this->setFlashError("Akses Ditolak: Penerbitan Surat Penawaran Resmi merupakan wewenang Tim Mitra.");
            $f3->reroute("/order/{$orderId}/penawaran/buat");
            return;
        }

        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);
        if (!$order) {
            $this->setFlashError("Order Layanan #{$orderId} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        // 1. Validasi Prasyarat Tinjauan Permintaan / Kontrak (Tahap 2 - Khusus Selulosa)
        $tinjauan = $orderModel->getTinjauanKelayakan($orderId);
        $isSelulosa = (($order['jenis_layanan_opti'] ?? '') === 'selulosa');
        $tinjauanSelesai = (
            (!empty($tinjauan) && ($tinjauan['keputusan'] ?? '') === 'dapat_dilaksanakan') ||
            (($order['status_tinjauan'] ?? '') === 'layak') ||
            !$isSelulosa
        );
        if (!$tinjauanSelesai) {
            $this->setFlashError("Gagal: Tinjauan Permintaan / Kontrak (Tahap 2) belum selesai atau tidak memenuhi syarat.");
            $f3->reroute("/order/{$orderId}");
            return;
        }

        // 2. Validasi Prasyarat Dokumen Proposal (Khusus OPTI Selulosa)
        if (($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
            $proposalCheck = $orderModel->getProposalRiset($orderId);
            $proposalValid = !empty($proposalCheck) && 
                !empty($proposalCheck['file_proposal']) && 
                (float)($proposalCheck['estimasi_total_biaya'] ?? 0) > 0;
            $proposalApproved = $proposalValid && (
                in_array($order['status_proposal_biaya'] ?? '', ['siap_penawaran', 'disetujui']) ||
                in_array($proposalCheck['status_proposal'] ?? '', ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])
            );

            if (!$proposalValid || !$proposalApproved) {
                $this->setFlashError("Gagal: Dokumen proposal OPTI Selulosa wajib diisi, berkas harus diunggah, dan disetujui Ketua Tim sebelum Surat Penawaran dapat disimpan.");
                $f3->reroute("/order/{$orderId}");
                return;
            }
        }

        $post = $f3->get('POST');
        $userId = $this->getUserId() ?? 1;

        $spModel = new SuratPenawaran($this->db);
        $spExisting = $spModel->getByOrderId($orderId);
        $isRevisiBaru = !empty($post['is_revisi_baru']);

        // Proteksi State Guard:
        // Jika dokumen ini sebelumnya sudah berstatus DEAL, atau order sudah lanjut ke pembayaran/pelaksanaan:
        // Jangan izinkan downgrade status menjadi 'draft' dan jangan izinkan membuat revisi baru yang merusak alur.
        $isLockedDeal = false;
        $orderHasDeal = (($order['status_penawaran'] ?? '') === 'deal') || (!empty($spExisting) && ($spExisting['status_respon_klien'] ?? '') === 'deal');
        if ($orderHasDeal) {
            $isLunas = ($order['status_keuangan'] ?? '') === 'lunas';
            $hasBayar = !empty($this->db->exec("SELECT id FROM opti_pembayaran WHERE order_id = ? LIMIT 1", [1 => $orderId]));
            $hasPo = !empty($this->db->exec("SELECT id FROM po_kegiatan WHERE order_id = ? LIMIT 1", [1 => $orderId]));
            $isPelaksanaan = in_array($order['status_pelaksanaan'] ?? '', ['sedang_berjalan', 'evaluasi_laporan', 'revisi_laporan', 'laporan_selesai']);
            if ($isLunas || $hasBayar || $hasPo || $isPelaksanaan || ($order['status_penawaran'] ?? '') === 'deal') {
                $isLockedDeal = true;
            }
        }

        if ($isLockedDeal) {
            $post['is_revisi_baru'] = 0;
            $post['mode'] = 'edit';
            $post['status_respon_klien'] = 'deal';
            $post['action_status'] = 'deal';
        } else {
            $actionStatus = $post['action_status'] ?? '';
            if ($actionStatus === 'draft') {
                $post['status_respon_klien'] = 'draft';
            } elseif ($actionStatus === 'nego' || $actionStatus === 'terkirim') {
                if (empty($post['status_respon_klien']) || $post['status_respon_klien'] === 'draft') {
                    $post['status_respon_klien'] = 'nego';
                }
            }
        }

        $durasiAngka = (int)($post['durasi_hari'] ?? 0);
        if ($durasiAngka > 0) {
            $post['spm_layanan'] = $durasiAngka . ' Hari Kerja';
        } elseif (empty($post['spm_layanan']) || $post['spm_layanan'] === 'Lainnya') {
            $post['spm_layanan'] = '30 Hari Kerja';
        }

        if (!empty($post['nomor_surat']) && preg_match('/^\d+\/BBSPJIS\/MS\//i', $post['nomor_surat'])) {
            $post['nomor_surat'] = 'B/' . $post['nomor_surat'];
        }

        try {
            $orderModel = new \OrderLayanan($this->db);
            $order = $orderModel->getDetail($orderId);

            // Opsional: simpan berkas lampiran yang diunggah user sebelum generate PDF gabungan
            if (!empty($_FILES['file_lampiran_user']) && $_FILES['file_lampiran_user']['error'] === UPLOAD_ERR_OK) {
                $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
                $origName = $_FILES['file_lampiran_user']['name'] ?? '';
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExt, true) && (int)$_FILES['file_lampiran_user']['size'] <= 10 * 1024 * 1024) {
                    $lampiranDir = 'uploads/penawaran/lampiran/';
                    if (!is_dir($lampiranDir)) {
                        @mkdir($lampiranDir, 0777, true);
                    }
                    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                    $safeBase = substr($safeBase ?: 'Lampiran', 0, 50);
                    $lampiranFileName = 'Lampiran_SP_' . $orderId . '_' . time() . '_' . $safeBase . '.' . $ext;
                    $lampiranDest = $lampiranDir . $lampiranFileName;
                    if (@move_uploaded_file($_FILES['file_lampiran_user']['tmp_name'], $lampiranDest)) {
                        $post['file_lampiran_user'] = $lampiranDest;
                        unset($_SESSION['pending_lampiran_sp_' . $orderId]);
                    }
                }
            } elseif (!empty($_SESSION['pending_lampiran_sp_' . $orderId])) {
                $post['file_lampiran_user'] = $_SESSION['pending_lampiran_sp_' . $orderId];
                unset($_SESSION['pending_lampiran_sp_' . $orderId]);
            }

            // Khusus OPTI Selulosa: jika belum ada upload berkas lampiran baru di sesi ini,
            // sesuaikan langsung dengan dokumen proposal yang diunggah PIC pada Tahap 4
            if (empty($post['file_lampiran_user']) && ($order['jenis_layanan_opti'] ?? '') === 'selulosa') {
                $prop = $orderModel->getProposalRiset($orderId);
                if (!empty($prop['file_proposal'])) {
                    $post['file_lampiran_user'] = str_replace('\\', '/', (string)$prop['file_proposal']);
                }
            }

            // Otomatis generate dan simpan berkas PDF resmi (termasuk lampiran hal 3+) ke folder uploads/penawaran
            $uploadDir = 'uploads/penawaran/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'Penawaran_' . $orderId . '_' . time() . '.pdf';
            $destPath = $uploadDir . $newFileName;

            $spDataForPdf = array_merge($post, [
                'pembuat_nama' => $_SESSION['user']['nama'] ?? ($_SESSION['nama_lengkap'] ?? 'Tim Mitra BBSPJIS')
            ]);
            $pdfObj = $this->generatePdfObject($order, $spDataForPdf);
            $pdfObj->Output('F', $destPath);
            $post['file_lampiran'] = $destPath;

            $spModel = new SuratPenawaran($this->db);
            $hasil = $spModel->buatDariOrder($orderId, $userId, $post);

            $isDraft = ($post['status_respon_klien'] === 'draft');

            // Catat audit pengiriman Tahap 5 (Surat Penawaran Resmi)
            if (!$isDraft) {
                \StageAudit::recordKirim(
                    $this->db,
                    $orderId,
                    5,
                    'Surat Penawaran Biaya Resmi',
                    $userId,
                    $_SESSION['nama_lengkap'] ?? ($_SESSION['user']['nama'] ?? 'Tim Mitra'),
                    'Tim Mitra',
                    date('Y-m-d H:i:s'),
                    'Surat Penawaran Resmi Diterbitkan (No: ' . $hasil['nomor_surat'] . ')'
                );

                try {
                    $orderModel = new \OrderLayanan($this->db);
                    $order = $orderModel->getDetail($orderId);
                    \NotificationService::send($this->db, [
                        'order_id'       => $orderId,
                        'target_role'    => 'ketua_tim',
                        'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                        'judul'          => 'Surat Penawaran Resmi Diterbitkan',
                        'pesan'          => "Surat Penawaran No. {$hasil['nomor_surat']} senilai Rp " . number_format($hasil['nominal'], 0, ',', '.') . " telah dikirimkan ke {$order['nama_perusahaan']}.",
                        'tipe'           => 'info',
                        'icon'           => 'bi-send-check-fill',
                        'link_url'       => "/order/{$orderId}",
                        'created_by'     => $userId,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                    ]);
                } catch (\Exception $eNotif) {}

                $isDeal = (($post['status_respon_klien'] ?? '') === 'deal');
                if ($isDeal) {
                    \StageAudit::recordDisetujui(
                        $this->db,
                        $orderId,
                        5,
                        $userId,
                        $_SESSION['nama_lengkap'] ?? ($_SESSION['user']['nama'] ?? 'Tim Mitra'),
                        'Tim Mitra',
                        date('Y-m-d H:i:s')
                    );
                    $this->logActivity($orderId, 'penawaran', 'deal_penawaran', "Persetujuan Penawaran (DEAL) No. {$hasil['nomor_surat']} (Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")");
                    if ($isLockedDeal) {
                        $this->setFlashSuccess(
                            "Perubahan redaksi Surat Penawaran Nomor <strong>{$hasil['nomor_surat']}</strong> berhasil disimpan. Status kesepakatan <strong>DEAL</strong> tetap dipertahankan karena alur pelayanan telah berlanjut."
                        );
                    } else {
                        $this->setFlashSuccess(
                            "Surat Penawaran Resmi berhasil diterbitkan dengan Nomor: <strong>{$hasil['nomor_surat']}</strong> (Status: DEAL / Disetujui, Nominal: Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")."
                        );
                    }
                } elseif (($post['status_respon_klien'] ?? '') === 'nego') {
                    $this->logActivity($orderId, 'penawaran', 'nego_penawaran', "Terbitkan penawaran No. {$hasil['nomor_surat']} - Negosiasi (Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")");
                    $this->setFlashSuccess(
                        "Surat Penawaran Resmi berhasil diterbitkan dengan Nomor: <strong>{$hasil['nomor_surat']}</strong> (Status: Negosiasi, Nominal: Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")."
                    );
                } else {
                    $this->logActivity($orderId, 'penawaran', 'buat_penawaran', "Terbitkan penawaran No. {$hasil['nomor_surat']} (Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")");
                    $this->setFlashSuccess(
                        "Surat Penawaran Resmi berhasil diterbitkan dengan Nomor: <strong>{$hasil['nomor_surat']}</strong> (Nominal: Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")."
                    );
                }
            } else {
                $this->logActivity($orderId, 'penawaran', 'simpan_draft_penawaran', "Draf penawaran No. {$hasil['nomor_surat']} (Rp " . number_format($hasil['nominal'], 0, ',', '.') . ")");

                $this->setFlashSuccess(
                    "Draf Surat Penawaran Nomor <strong>{$hasil['nomor_surat']}</strong> berhasil disimpan (Status: Draft Internal)."
                );
                $editUrl = "/order/{$orderId}/penawaran/buat" . (!empty($hasil['id']) ? "?mode=edit&sp_id={$hasil['id']}" : "");
                $f3->reroute($editUrl);
                return;
            }
            $f3->reroute("/order/{$orderId}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menerbitkan surat penawaran: ' . $e->getMessage());
            $f3->reroute("/order/{$orderId}/penawaran/buat");
        }
    }

    /**
     * Alias method untuk tambahDariOrder
     * Route: GET /order/@id/penawaran/buat
     */
    public function buatDariOrder($f3, $params)
    {
        return $this->tambahDariOrder($f3, $params);
    }

    /**
     * Memperbarui status respon klien langsung dari halaman detail order
     * Route: POST /order/@id/penawaran/status
     */
    public function updateStatusKlien($f3, $params)
    {
        $orderId = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');
        $statusRespon = $post['status_respon_klien'] ?? 'draft';
        $catatanNego  = trim($post['catatan_nego'] ?? '');

        $spModel = new SuratPenawaran($this->db);
        $sp = $spModel->getByOrderId($orderId);

        if (!$sp) {
            $this->setFlashError('Surat penawaran untuk order ini belum diterbitkan.');
            $f3->reroute("/order/{$orderId}");
            return;
        }

        try {
            $nominal = (float)($sp['nominal_penawaran'] ?? 0);
            $spModel->updateResponKlien((int)$sp['id'], $statusRespon, $catatanNego, $nominal);

            $orderModel = new \OrderLayanan($this->db);
            $order = $orderModel->getDetail($orderId);

            // Jika status respon adalah DEAL, kirim notifikasi ke Pejabat, Ka Tim, & Tim Keuangan
            if ($statusRespon === 'deal') {
                try {
                    $this->db->exec(
                        "UPDATE order_layanan SET status_penawaran = 'deal', status_keuangan = 'menunggu_pembayaran' WHERE id = ?",
                        [1 => $orderId]
                    );

                    // Notif ke Ketua Tim Keuangan
                    \NotificationService::send($this->db, [
                        'order_id'        => $orderId,
                        'target_role'     => 'ketua_tim_keuangan',
                        'target_layanan'  => 'semua',
                        'judul'           => 'Disposisi Pembayaran: Order #' . ($order['nomor_order'] ?? $orderId),
                        'pesan'           => "Penawaran {$order['nama_perusahaan']} telah disepakati (DEAL senilai Rp " . number_format($nominal, 0, ',', '.') . "). Siap untuk proses pembayaran.",
                        'tipe'            => 'success',
                        'icon'            => 'bi-wallet2',
                        'link_url'        => "/pembayaran/tambah?order_id={$orderId}",
                        'created_by'      => $this->getUserId() ?? 1,
                        'created_by_name' => $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                    ]);

                    // Notif ke Ka Tim OPTI
                    \NotificationService::send($this->db, [
                        'order_id'       => $orderId,
                        'target_role'    => 'ketua_tim',
                        'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua',
                        'judul'          => 'Penawaran Order DEAL',
                        'pesan'          => "Klien ({$order['nama_perusahaan']}) telah sepakat dengan penawaran Order #{$order['nomor_order']}. Silakan lakukan Disposisi ke Tim Keuangan.",
                        'tipe'           => 'success',
                        'icon'           => 'bi-check-circle-fill',
                        'link_url'       => "/order/{$orderId}",
                        'created_by'     => $this->getUserId() ?? 1,
                        'created_by_name'=> $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                    ]);
                } catch (\Exception $eNotif) {}

                $this->setFlashSuccess("Penawaran telah disetujui klien (<strong>DEAL</strong>). Notifikasi telah diteruskan ke <strong>Tim Keuangan</strong> untuk tahap pembayaran.");
            } else {
                $this->setFlashSuccess("Status respon penawaran berhasil diperbarui menjadi: <strong>" . strtoupper($statusRespon) . "</strong>.");
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui status penawaran: ' . $e->getMessage());
        }

        $f3->reroute("/order/{$orderId}");
    }

    /**
     * Memperbarui respon / negosiasi klien terhadap Surat Penawaran
     * Route: POST /surat-penawaran/@id/respon
     */
    public function updateRespon($f3, $params)
    {
        $penawaranId = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $statusRespon = $post['status_respon_klien'] ?? 'draft';
        $catatanNego  = trim($post['catatan_nego'] ?? '');
        $nominalRaw   = preg_replace('/[^0-9]/', '', (string)($post['nominal_penawaran'] ?? '0'));
        $nominalBaru  = (float)($nominalRaw ?: 0);
        $orderId      = (int)($post['order_id'] ?? 0);

        try {
            $spModel = new SuratPenawaran($this->db);
            $hasil = $spModel->updateResponKlien($penawaranId, $statusRespon, $catatanNego, $nominalBaru);

            if ($statusRespon === 'deal') {
                if ($orderId > 0) {
                    \StageAudit::recordDisetujui($this->db, $orderId, 5, $this->getUserId() ?? 1, 'Pelanggan / Klien', 'Pelanggan', date('Y-m-d H:i:s'));
                    $orderModel = new \OrderLayanan($this->db);
                    $order = $orderModel->getDetail($orderId);
                    if ($order) {
                        $this->db->exec(
                            "UPDATE order_layanan SET status_penawaran = 'deal', status_keuangan = 'menunggu_pembayaran' WHERE id = ?",
                            [1 => $orderId]
                        );

                        try {
                            \NotificationService::send($this->db, [
                                'order_id'        => $orderId,
                                'target_role'     => 'ketua_tim_keuangan',
                                'target_layanan'  => 'semua',
                                'judul'           => 'Disposisi Pembayaran: Order #' . ($order['nomor_order'] ?? $orderId),
                                'pesan'           => "Penawaran {$order['nama_perusahaan']} telah disepakati (DEAL senilai Rp " . number_format($nominalBaru, 0, ',', '.') . "). Siap untuk proses pembayaran.",
                                'tipe'            => 'success',
                                'icon'            => 'bi-wallet2',
                                'link_url'        => "/pembayaran/tambah?order_id={$orderId}",
                                'created_by'      => $this->getUserId() ?? 1,
                                'created_by_name' => $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                            ]);
                        } catch (\Exception $eNotif) {}
                    }
                }
                $this->setFlashSuccess("Penawaran disetujui (<strong>DEAL</strong>). Notifikasi telah dikirim ke <strong>Tim Keuangan</strong> untuk membuka tahap pembayaran.");
            } elseif ($statusRespon === 'nego') {
                $this->setFlashWarning("Catatan negosiasi harga klien telah disimpan. Nominal penawaran disesuaikan.");
            } else {
                $this->setFlashSuccess("Status respon klien berhasil diperbarui.");
            }

            if ($orderId > 0) {
                $aksiLog = ($statusRespon === 'deal') ? 'deal_penawaran' : (($statusRespon === 'nego') ? 'nego_penawaran' : (($statusRespon === 'batal') ? 'batal_penawaran' : 'respon_penawaran'));
                $nominalTampil = $nominalBaru > 0 ? $nominalBaru : ((float)($hasil['nominal'] ?? 0));
                $descRespon = ($statusRespon === 'deal')
                    ? "Persetujuan Penawaran (DEAL)" . ($nominalTampil > 0 ? " (Rp " . number_format($nominalTampil, 0, ',', '.') . ")" : "") . (!empty($catatanNego) ? " - Catatan: {$catatanNego}" : "")
                    : (($statusRespon === 'nego')
                        ? "Negosiasi Penawaran Klien" . ($nominalTampil > 0 ? " (Rp " . number_format($nominalTampil, 0, ',', '.') . ")" : "") . (!empty($catatanNego) ? " - Catatan: {$catatanNego}" : "")
                        : "Respon penawaran: " . strtoupper($statusRespon) . (!empty($catatanNego) ? " - Catatan: {$catatanNego}" : ""));
                $this->logActivity($orderId, 'penawaran', $aksiLog, $descRespon);
                $f3->reroute("/order/{$orderId}");
            } else {
                $f3->reroute('/surat-penawaran');
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui respon penawaran: ' . $e->getMessage());
            $f3->reroute($orderId > 0 ? "/order/{$orderId}" : '/surat-penawaran');
        }
    }

    /**
     * Disposisi Surat Penawaran DEAL ke Tim Keuangan
     * Route: POST /order/@id/penawaran/disposisi-keuangan & POST /surat-penawaran/@id/disposisi-keuangan
     */
    public function disposisiKeuangan($f3, $params)
    {
        $orderId = (int)($params['id'] ?? 0);
        $userId = (int)($this->getUserId() ?? 1);
        $userNama = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama_user'] ?? 'Tim Mitra');

        $orderModel = new \OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);
        if (!$order) {
            $this->setFlashError("Order Layanan #{$orderId} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $spModel = new \SuratPenawaran($this->db);
        $sp = $spModel->getByOrderId($orderId);
        if (!$sp) {
            $this->setFlashError("Surat penawaran untuk order ini belum diterbitkan.");
            $f3->reroute("/order/{$orderId}");
            return;
        }

        if (empty($sp['file_penawaran_ttd']) || empty($sp['file_bukti_persetujuan'])) {
            $this->setFlashError("Gagal mendisposisikan ke Tim Keuangan: Lengkapi Surat Penawaran bertandatangan dan Bukti Persetujuan terlebih dahulu.");
            $f3->reroute("/order/{$orderId}");
            return;
        }

        try {
            // Update status penawaran ke DEAL jika belum
            $nominal = (float)($sp['nominal_penawaran'] ?? ($order['estimasi_biaya'] ?? 0));
            $spModel->updateResponKlien((int)$sp['id'], 'deal', $sp['catatan_nego'] ?? '', $nominal);

            // Update status keuangan order ke 'menunggu_pembayaran'
            $this->db->exec(
                "UPDATE order_layanan SET status_keuangan = 'menunggu_pembayaran', status = 'penawaran' WHERE id = ?",
                [1 => $orderId]
            );

            // 1. Kirim Notifikasi ke Ketua Tim Keuangan
            \NotificationService::send($this->db, [
                'order_id'        => $orderId,
                'target_role'     => 'ketua_tim_keuangan',
                'target_layanan'  => 'semua',
                'judul'           => 'Disposisi Pembayaran: Order #' . $order['nomor_order'],
                'pesan'           => "Penawaran {$order['nama_perusahaan']} (No: {$sp['nomor_surat']}, Nilai: Rp " . number_format($nominal, 0, ',', '.') . ") telah DEAL dan didisposisikan ke Tim Keuangan untuk proses pencatatan pembayaran.",
                'tipe'            => 'success',
                'icon'            => 'bi-wallet2',
                'link_url'        => "/pembayaran/tambah?order_id={$orderId}",
                'created_by'      => $userId,
                'created_by_name' => $userNama
            ]);

            // 2. Kirim Notifikasi ke Ketua Tim OPTI
            \NotificationService::send($this->db, [
                'order_id'        => $orderId,
                'target_role'     => 'ketua_tim',
                'target_layanan'  => $order['jenis_layanan_opti'] ?? 'semua',
                'judul'           => 'Penawaran Order DEAL - Didisposisikan ke Keuangan',
                'pesan'           => "Penawaran {$order['nama_perusahaan']} telah disepakati (DEAL) dan didisposisikan ke Tim Keuangan.",
                'tipe'            => 'info',
                'icon'            => 'bi-send-check-fill',
                'link_url'        => "/order/{$orderId}",
                'created_by'      => $userId,
                'created_by_name' => $userNama
            ]);

            // Catat StageAudit Tahap 5
            \StageAudit::recordKirim(
                $this->db,
                $orderId,
                5,
                'Disposisi ke Tim Keuangan',
                $userId,
                $userNama,
                'Tim Mitra',
                date('Y-m-d H:i:s'),
                "Penawaran DEAL didisposisikan ke Tim Keuangan (Nominal: Rp " . number_format($nominal, 0, ',', '.') . ")"
            );

            $this->logActivity($orderId, 'Penawaran', 'Disposisi Keuangan', "Disposisi penawaran DEAL ke Tim Keuangan");

            $this->setFlashSuccess("Penawaran DEAL berhasil didisposisikan ke <strong>Tim Keuangan</strong>. Notifikasi telah dikirim dan order kini masuk ke antrean <strong>Pembayaran</strong>.");
        } catch (\Exception $e) {
            $this->setFlashError("Gagal mendisposisikan ke Tim Keuangan: " . $e->getMessage());
        }

        $redirect = $f3->get('POST.redirect') ?: 'order';
        if ($redirect === 'pembayaran') {
            $f3->reroute("/pembayaran/tambah?order_id={$orderId}");
        } elseif ($redirect === 'surat_penawaran') {
            $f3->reroute("/surat-penawaran");
        } else {
            $f3->reroute("/order/{$orderId}");
        }
    }

    /**
     * Mengambil daftar penandatangan dan daftar jabatan dari master mst_penandatangan_lhu database sil2020
     */
    public function getMasterPenandatanganData()
    {
        $penandatanganList = [];
        $jabatanList = [];
        try {
            $dbSekre = $this->dbSekretariat ?: $this->db;
            $rows = $dbSekre->exec("SELECT id_penandatangan, jabatan, nama, nip, showhide FROM mst_penandatangan_lhu ORDER BY FIELD(showhide, 'show', 'hide'), id_penandatangan DESC");
            $seenKey = [];
            $seenJab = [];
            foreach ($rows as $r) {
                $rawJab = str_ireplace(['<br>', '<br/>', '<br />', '&nbsp;'], ' ', $r['jabatan'] ?? '');
                $cleanJab = strip_tags($rawJab);
                $cleanJab = preg_replace('/\s+/', ' ', $cleanJab);
                $cleanJab = trim($cleanJab, " \t\n\r\0\x0B,");

                // Hapus "a.n. Kepala" jika diikuti jabatan definitif (misal: a.n Kepala Kepala Bagian... -> Kepala Bagian...)
                $cleanJab = preg_replace('/^a\.?n\.?\s+Kepala[,\s]+(Kepala|Ketua|Plt|Plh|Sekretaris|Bendahara)/i', '$1', $cleanJab);
                // Hapus prefix a.n. / an. / a.n / an
                $cleanJab = preg_replace('/^(a\.?n\.?|an\.)\s*/i', '', $cleanJab);
                // Hapus prefix Plh. / Plh / p.l.h. / p.l.h / plh
                $cleanJab = preg_replace('/^(p\.?l\.?h\.?|plh\.)\s*/i', '', $cleanJab);
                // Hapus prefix Plt. / Plt / p.l.t. / p.l.t / plt / a.n.t / ant
                $cleanJab = preg_replace('/^(p\.?l\.?t\.?|plt\.|a\.?n\.?t\.?|ant\.)\s*/i', '', $cleanJab);
                // Rapihkan spasi dan trailing koma
                $cleanJab = trim(preg_replace('/\s+/', ' ', $cleanJab), " \t\n\r\0\x0B,");

                $cleanNm = trim($r['nama'] ?? '');

                if (!empty($cleanJab) && !isset($seenJab[$cleanJab])) {
                    $seenJab[$cleanJab] = true;
                    $jabatanList[] = $cleanJab;
                }

                $key = strtolower($cleanNm . '|' . $cleanJab);
                if (!isset($seenKey[$key]) && !empty($cleanNm)) {
                    $seenKey[$key] = true;

                    // Klasifikasi kategori & ikon badge agar tampilan dropdown rapi dan terstruktur
                    $kategori = 'Pejabat & Unit Kerja';
                    $badgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                    $icon = 'bi-person-badge';

                    if (stripos($cleanJab, 'Ketua Tim') !== false) {
                        $kategori = 'Ketua Tim Kerja';
                        $badgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                        $icon = 'bi-people';
                    } elseif (stripos($cleanJab, 'Kepala') !== false || stripos($cleanJab, 'Plh.') !== false || stripos($cleanJab, 'a.n') !== false) {
                        if (stripos($cleanJab, 'Bidang') !== false || stripos($cleanJab, 'Seksi') !== false) {
                            $kategori = 'Pejabat Bidang & Teknis';
                            $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                            $icon = 'bi-briefcase';
                        } else {
                            $kategori = 'Pimpinan Balai & Manajemen';
                            $badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                            $icon = 'bi-award';
                        }
                    }

                    $penandatanganList[] = [
                        'id'         => $r['id_penandatangan'],
                        'nama'       => $cleanNm,
                        'jabatan'    => $cleanJab,
                        'kategori'   => $kategori,
                        'badgeClass' => $badgeClass,
                        'icon'       => $icon,
                        'nip'        => trim($r['nip'] ?? '')
                    ];
                }
            }
        } catch (\Exception $e) {
            $penandatanganList = [
                ['id' => 21, 'nama' => 'Dodiet Prasetyo', 'jabatan' => 'Kepala', 'kategori' => 'Pimpinan Balai & Manajemen', 'badgeClass' => 'bg-primary-subtle text-primary border border-primary-subtle', 'icon' => 'bi-award', 'nip' => ''],
                ['id' => 18, 'nama' => 'Joko Pratomo', 'jabatan' => 'Kepala Bagian Tata Usaha', 'kategori' => 'Pimpinan Balai & Manajemen', 'badgeClass' => 'bg-primary-subtle text-primary border border-primary-subtle', 'icon' => 'bi-award', 'nip' => ''],
                ['id' => 20, 'nama' => 'Andar Hermawan', 'jabatan' => 'Ketua Tim Pelaksanaan Mitra Industri', 'kategori' => 'Ketua Tim Kerja', 'badgeClass' => 'bg-info-subtle text-info-emphasis border border-info-subtle', 'icon' => 'bi-people', 'nip' => ''],
                ['id' => 8,  'nama' => 'Emma Safarina Ertaviani', 'jabatan' => 'Ketua Tim Layanan Mitra Industri', 'kategori' => 'Ketua Tim Kerja', 'badgeClass' => 'bg-info-subtle text-info-emphasis border border-info-subtle', 'icon' => 'bi-people', 'nip' => ''],
                ['id' => 10, 'nama' => 'Hendra Yetty', 'jabatan' => 'Kepala', 'kategori' => 'Pimpinan Balai & Manajemen', 'badgeClass' => 'bg-primary-subtle text-primary border border-primary-subtle', 'icon' => 'bi-award', 'nip' => '']
            ];
            $jabatanList = ['Kepala', 'Kepala Bagian Tata Usaha', 'Ketua Tim Pelaksanaan Mitra Industri', 'Ketua Tim Layanan Mitra Industri'];
        }

        return [$penandatanganList, $jabatanList];
    }

    /**
     * Cetak PDF Surat Penawaran Resmi BBSPJIS
     * Route: GET /order/@id/penawaran/cetak
     */
    public function cetakPdf($f3, $params)
    {
        $this->requireAuth();
        $targetId = (int)($params['id'] ?? 0);
        $spModel  = new SuratPenawaran($this->db);
        $orderModel = new OrderLayanan($this->db);

        $order = $orderModel->getDetail($targetId);
        $sp    = null;

        if ($order) {
            $spId = (int)($f3->get('GET.id') ?? 0);
            if ($spId > 0) {
                $sp = $spModel->getById($spId);
                if ($sp && (int)$sp['order_id'] !== $targetId) {
                    $sp = null;
                }
            }
            if (!$sp) {
                $sp = $spModel->getByOrderId($targetId);
            }
        } else {
            // Coba cari sebagai ID Surat Penawaran langsung
            $sp = $spModel->getById($targetId);
            if ($sp && !empty($sp['order_id'])) {
                $order = $orderModel->getDetail((int)$sp['order_id']);
            }
        }

        $orderIdActual = (int)($order['id'] ?? ($sp['order_id'] ?? $targetId));
        if (!$sp && $order) {
            $sp = [
                'id'                  => 0,
                'order_id'            => (int)$order['id'],
                'nomor_surat'         => $spModel->generateNomorSurat(($order['jenis_layanan_opti'] ?? 'selulosa') === 'selulosa' ? 'SL' : 'LK'),
                'perusahaan'          => $order['nama_perusahaan'] ?? 'Mitra Balai',
                'nama'                => $order['pic'] ?? '-',
                'alamat'              => $order['alamat'] ?? '-',
                'jenis_layanan'       => $order['jenis_layanan_opti'] ?? 'selulosa',
                'perihal'             => $order['judul_kegiatan'] ?? 'Penawaran Layanan Jasa OPTI',
                'nominal_penawaran'   => (float)($order['estimasi_biaya'] ?? 0),
                'tanggal_surat'       => date('Y-m-d'),
                'status_respon_klien' => 'draft',
                'file_lampiran_user'  => $_SESSION['pending_lampiran_sp_' . $orderIdActual] ?? ''
            ];
        }

        if ($sp && empty($sp['file_lampiran_user']) && !empty($_SESSION['pending_lampiran_sp_' . $orderIdActual])) {
            $sp['file_lampiran_user'] = $_SESSION['pending_lampiran_sp_' . $orderIdActual];
        }

        if ($sp && ($order['jenis_layanan_opti'] ?? '') === 'selulosa' && empty($_SESSION['pending_lampiran_sp_' . $orderIdActual])) {
            $propRows = $this->db->exec('SELECT file_proposal FROM opti_proposal_riset WHERE order_id = ? AND file_proposal IS NOT NULL AND file_proposal <> \'\' ORDER BY id DESC LIMIT 1', [1 => $orderIdActual]);
            if (!empty($propRows[0]['file_proposal'])) {
                $sp['file_lampiran_user'] = trim((string)$propRows[0]['file_proposal']);
            }
        }

        if (!$sp) {
            $this->setFlashError('Surat penawaran tidak ditemukan.');
            $f3->reroute('/surat-penawaran');
            return;
        }

        if (!$order) {
            // Fallback order info dari data surat penawaran
            $order = [
                'id'                 => (int)($sp['order_id'] ?? 0),
                'nomor_order'        => 'ORD-OFFLINE',
                'nama_perusahaan'    => $sp['perusahaan'] ?? 'Mitra Balai',
                'pt_cv'              => '',
                'pic'                => $sp['nama'] ?? '-',
                'telepon'            => '-',
                'email'              => '-',
                'alamat'             => $sp['alamat'] ?? '-',
                'jenis_layanan_opti' => $sp['jenis_layanan'] ?? 'selulosa',
                'judul_kegiatan'     => $sp['perihal'] ?? 'Penawaran Layanan Jasa OPTI',
                'estimasi_biaya'     => (float)($sp['nominal_penawaran'] ?? 0),
                'spm_layanan'        => '30 Hari Kerja'
            ];
        }

        $kopParam = $f3->get('GET.kop');
        $useKop = !isset($kopParam) || ($kopParam !== '0' && $kopParam !== 'tanpa_kop' && $kopParam !== 0 && $kopParam !== false);
        $isDownload = ($f3->get('GET.download') === '1' || $f3->get('GET.dl') === '1');

        $pdf = $this->generatePdfObject($order, $sp, $useKop);

        $safeNum = preg_replace('/[^A-Za-z0-9_\-]/', '_', $sp['nomor_surat'] ?: ($order['nomor_order'] ?? 'BBSPJIS'));
        $suffix = $useKop ? '' : '_Tanpa_Kop';
        $filename = 'Surat_Penawaran_' . $safeNum . $suffix . '.pdf';

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($isDownload ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        $pdf->Output($isDownload ? 'D' : 'I', $filename);
        exit;
    }

    /**
     * Unduh Dokumen Surat Penawaran Resmi BBSPJIS Format Word (.docx)
     * Route: GET|POST /order/@id/penawaran/word
     * Route: GET|POST /surat-penawaran/@id/word
     */
    public function unduhWord($f3, $params)
    {
        $this->requireAuth();
        $targetId = (int)($params['id'] ?? 0);
        $spModel  = new SuratPenawaran($this->db);
        $orderModel = new OrderLayanan($this->db);

        $order = $orderModel->getDetail($targetId);
        $sp    = null;

        if ($order) {
            $spId = (int)($f3->get('GET.id') ?? $f3->get('POST.id') ?? 0);
            if ($spId > 0) {
                $sp = $spModel->getById($spId);
                if ($sp && (int)$sp['order_id'] !== $targetId) {
                    $sp = null;
                }
            }
            if (!$sp) {
                $sp = $spModel->getByOrderId($targetId);
            }
        } else {
            $sp = $spModel->getById($targetId);
            if ($sp && !empty($sp['order_id'])) {
                $order = $orderModel->getDetail((int)$sp['order_id']);
            }
        }

        $orderIdActual = (int)($order['id'] ?? ($sp['order_id'] ?? $targetId));

        if (!$order && $sp) {
            $order = [
                'id'                 => (int)($sp['order_id'] ?? 0),
                'nomor_order'        => 'ORD-OFFLINE',
                'nama_perusahaan'    => $sp['perusahaan'] ?? 'Mitra Balai',
                'pt_cv'              => '',
                'pic'                => $sp['nama'] ?? '-',
                'telepon'            => '-',
                'email'              => '-',
                'alamat'             => $sp['alamat'] ?? '-',
                'jenis_layanan_opti' => $sp['jenis_layanan'] ?? 'selulosa',
                'judul_kegiatan'     => $sp['perihal'] ?? 'Penawaran Layanan Jasa OPTI',
                'estimasi_biaya'     => (float)($sp['nominal_penawaran'] ?? 0),
                'spm_layanan'        => '30 Hari Kerja'
            ];
        }

        if (!$sp && $order) {
            $sp = [
                'id'                  => 0,
                'order_id'            => (int)$order['id'],
                'nomor_surat'         => $spModel->generateNomorSurat(($order['jenis_layanan_opti'] ?? 'selulosa') === 'selulosa' ? 'SL' : 'LK'),
                'perusahaan'          => $order['nama_perusahaan'] ?? 'Mitra Balai',
                'nama'                => $order['pic'] ?? '-',
                'alamat'              => $order['alamat'] ?? '-',
                'jenis_layanan'       => $order['jenis_layanan_opti'] ?? 'selulosa',
                'perihal'             => $order['judul_kegiatan'] ?? 'Penawaran Layanan Jasa OPTI',
                'nominal_penawaran'   => (float)($order['estimasi_biaya'] ?? 0),
                'tanggal_surat'       => date('Y-m-d'),
                'status_respon_klien' => 'draft'
            ];
        }

        if (!$order && !$sp) {
            $this->setFlashError('Dokumen surat penawaran tidak ditemukan.');
            $f3->reroute('/surat-penawaran');
            return;
        }

        // Live draft inputs dari form jika tersedia (POST atau GET)
        $noSurat       = trim((string)($f3->get('POST.nomor_surat') ?? $f3->get('GET.nomor_surat') ?? ($sp['nomor_surat'] ?: $spModel->generateNomorSurat())));
        if (preg_match('/^\d+\/BBSPJIS\/MS\//i', $noSurat)) {
            $noSurat = 'B/' . $noSurat;
        }
        $tanggalSurat  = trim((string)($f3->get('POST.tanggal_surat') ?? $f3->get('GET.tanggal_surat') ?? ($sp['tanggal_surat'] ?: date('Y-m-d'))));
        $tglFormatted  = self::formatTanggalIndoSurat($tanggalSurat);
        $perusahaan    = trim((string)($f3->get('POST.perusahaan') ?? $f3->get('GET.perusahaan') ?? ($sp['perusahaan'] ?? ($order['nama_perusahaan'] ?? 'Mitra Balai'))));
        if (!empty($order['pt_cv']) && stripos($perusahaan, $order['pt_cv']) === false) {
            $perusahaan = $order['pt_cv'] . ' ' . $perusahaan;
        }
        $picSurat      = trim((string)($f3->get('POST.nama') ?? $f3->get('GET.nama') ?? ($sp['nama'] ?? ($order['pic'] ?? ''))));
        $alamat        = trim((string)($f3->get('POST.alamat') ?? $f3->get('GET.alamat') ?? ($sp['alamat'] ?? ($order['alamatcustomer'] ?? ($order['alamat'] ?? '')))));
        $halSurat      = trim((string)($f3->get('POST.hal') ?? $f3->get('GET.hal') ?? ($sp['hal'] ?? ($sp['perihal'] ?? 'Penawaran Layanan Jasa OPTI'))));
        $lampiranTeks  = trim((string)($f3->get('POST.lampiran_teks') ?? $f3->get('GET.lampiran_teks') ?? ($sp['lampiran_teks'] ?? '1 (satu) lembar')));
        if (empty($lampiranTeks)) $lampiranTeks = '1 (satu) lembar';

        $nominalRaw    = $f3->get('POST.nominal_penawaran') ?? $f3->get('GET.nominal_penawaran');
        $nominal       = ($nominalRaw !== null && $nominalRaw !== '') ? (float)str_replace(['.', ','], '', (string)$nominalRaw) : (float)($sp['nominal_penawaran'] ?? ($order['estimasi_biaya'] ?? 0));

        $durasiHari    = (int)($f3->get('POST.durasi_hari') ?? $f3->get('GET.durasi_hari') ?? ($sp['durasi_hari'] ?? 30));
        if ($durasiHari > 0) {
            $order['spm_layanan'] = "{$durasiHari} Hari Kerja";
        }

        $judulKegiatan = !empty($order['judul_kegiatan']) ? $order['judul_kegiatan'] : (!empty($sp['perihal']) ? $sp['perihal'] : 'Layanan Jasa Optimalisasi Teknologi Industri (OPTI)');
        $mediaRaw      = !empty($sp['permintaan_melalui']) ? $sp['permintaan_melalui'] : (!empty($order['permintaan_melalui']) ? $order['permintaan_melalui'] : 'email');
        $mediaPhrase   = self::formatMediaPermintaan($mediaRaw);
        $terbilangStr  = strtolower(self::terbilang($nominal));
        $ythTitle      = 'Pimpinan ' . $perusahaan;

        $jabatanPejabat = trim((string)($f3->get('POST.jabatan_pejabat') ?? $f3->get('GET.jabatan_pejabat') ?? ($sp['jabatan_pejabat'] ?? (($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'Kepala Bagian Tata Usaha' : 'Kepala'))));
        $pejabatNama    = trim((string)($f3->get('POST.pejabat_nama') ?? $f3->get('GET.pejabat_nama') ?? ($sp['pejabat_nama'] ?? (($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'Joko Pratomo' : 'Dodiet Prasetyo'))));

        $rawNaskah = $f3->get('POST.ruang_lingkup') ?? $f3->get('GET.ruang_lingkup') ?? ($sp['ruang_lingkup'] ?? null);
        if (empty($rawNaskah)) {
            $rawNaskah = "1. Biaya layanan adalah sebesar Rp " . number_format($nominal, 0, ',', '.') . " (" . $terbilangStr . " rupiah), dengan ruang lingkup pekerjaan sebagai berikut:\n" .
                "   1) Beating LBKP hingga 300 mL CSF\n" .
                "   2) Penyediaan stock sebanyak 2 variasi.\n" .
                "   3) Pembuatan handsheet dengan variasi penambahan aditif 2 variasi (sesuai b).\n" .
                "   4) Penampungan air dari pembuatan handsheet setiap variasi ditampung untuk dibawa ke " . $perusahaan . "\n" .
                "   5) Pengujian handsheet meliputi: massa handsheet, gramatur, uji brightness, % retensi.\n" .
                "   6) Evaluasi dan penyusunan laporan teknis.\n" .
                "2. Pelaksanaan kegiatan untuk ruang lingkup 1) sampai dengan 4) akan dilakukan pada tanggal " . $tglFormatted . ".\n" .
                "3. Total waktu pelaksanaan pekerjaan selama " . $durasiHari . " hari kerja.\n" .
                "4. Contoh aditif yang perlu disediakan sebanyak 50 ml.";
        }

        // Resolusi Berkas Lampiran jika ada
        $lampiranPdfPages = [];
        $lampiranImageBase64 = null;
        $lampiranImageExt = null;
        $lampiranDocName = null;
        $lampiranDocExt = null;

        $relLampiran = $sp['file_lampiran_user'] ?? ($_SESSION['pending_lampiran_sp_' . $orderIdActual] ?? '');
        if (empty($relLampiran) && ($order['jenis_layanan_opti'] ?? '') === 'selulosa' && $orderIdActual > 0) {
            $propRows = $this->db->exec('SELECT file_proposal FROM opti_proposal_riset WHERE order_id = ? AND file_proposal IS NOT NULL AND file_proposal <> \'\' ORDER BY id DESC LIMIT 1', [1 => $orderIdActual]);
            if (!empty($propRows[0]['file_proposal'])) {
                $relLampiran = trim((string)$propRows[0]['file_proposal']);
            }
        }
        if (!empty($relLampiran)) {
            $rootDir = dirname(__DIR__, 2);
            $fullLampiran = $rootDir . '/' . ltrim($relLampiran, '/\\');
            if (is_file($fullLampiran)) {
                $ext = strtolower(pathinfo($fullLampiran, PATHINFO_EXTENSION));

                $pdfToRender = null;
                if ($ext === 'pdf') {
                    $pdfToRender = $fullLampiran;
                } elseif (in_array($ext, ['doc', 'docx'], true)) {
                    $pdfToRender = $this->ensureConvertedPdf($fullLampiran);
                }

                if (!empty($pdfToRender) && is_file($pdfToRender)) {
                    // Render tiap halaman PDF menjadi gambar beresolusi tinggi agar muncul nyata di Word (Hal 3+)
                    $pythonExe = 'C:\\Users\\jrinz\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';
                    $scriptPath = __DIR__ . '/../helpers/render_pdf_pages.py';
                    if (is_file($pythonExe) && is_file($scriptPath)) {
                        $cmd = escapeshellarg($pythonExe) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($pdfToRender);
                        $jsonOutput = @shell_exec($cmd);
                        if ($jsonOutput) {
                            $res = json_decode($jsonOutput, true);
                            if (!empty($res['success']) && !empty($res['pages'])) {
                                $lampiranPdfPages = $res['pages'];
                            }
                        }
                    }
                    if (empty($lampiranPdfPages)) {
                        $lampiranDocName = basename($fullLampiran);
                        $lampiranDocExt = strtoupper($ext);
                    }
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    $lampiranImageBase64 = base64_encode(file_get_contents($fullLampiran));
                    $lampiranImageExt = ($ext === 'jpg' || $ext === 'jpeg') ? 'jpeg' : 'png';
                } else {
                    $lampiranDocName = basename($fullLampiran);
                    $lampiranDocExt = strtoupper($ext);
                }
            }
        }

        // Logo BBSPJIS Base64
        $logoPath = dirname(__DIR__, 2) . '/app/helpers/fpdf/kemenperin_logo.png';
        $logoBase64 = '';
        if (is_file($logoPath)) {
            $logoBase64 = base64_encode(file_get_contents($logoPath));
        }

        $safeNum = preg_replace('/[^A-Za-z0-9_\-]/', '_', $noSurat ?: ($order['nomor_order'] ?? 'BBSPJIS'));
        $filename = 'Surat_Penawaran_' . $safeNum . '.docx';

        // Tangkap isi HTML dari template penawaran
        ob_start();
        include dirname(__DIR__) . '/views/tim_mitra/surat Pelayanan/template_word_penawaran.php';
        $htmlContent = ob_get_clean();

        // Konversi ke format .docx asli via Microsoft Word COM PowerShell jika tersedia
        $helperScript = str_replace('/', '\\', realpath(dirname(__DIR__) . '/helpers/save_html_to_docx.ps1'));
        $tempDir = sys_get_temp_dir();
        $tempHtml = $tempDir . '\\sp_' . uniqid() . '.html';
        $tempDocx = $tempDir . '\\sp_' . uniqid() . '.docx';

        $converted = false;
        if ($helperScript && is_file($helperScript) && file_put_contents($tempHtml, $htmlContent) !== false) {
            $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($helperScript) .
                   ' -htmlPath ' . escapeshellarg($tempHtml) .
                   ' -docxPath ' . escapeshellarg($tempDocx);
            @exec($cmd, $out, $retCode);
            if ($retCode === 0 && is_file($tempDocx) && filesize($tempDocx) > 0) {
                $converted = true;
            }
        }

        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        if ($converted) {
            header('Content-Length: ' . filesize($tempDocx));
            readfile($tempDocx);
            @unlink($tempHtml);
            @unlink($tempDocx);
        } else {
            @unlink($tempHtml);
            @unlink($tempDocx);
            echo $htmlContent;
        }
        exit;
    }

    /**
     * Endpoint Data JSON Base64 untuk Pratinjau Surat Penawaran Bebas IDM (Zero Interception)
     * Route: GET /surat-penawaran/@id/raw-data
     * Route: GET /order/@id/penawaran/raw-data
     */
    public function penawaranRawData($f3, $params)
    {
        $this->requireAuth();
        $targetId = (int)($params['id'] ?? 0);
        $spModel  = new SuratPenawaran($this->db);
        $orderModel = new OrderLayanan($this->db);

        $order = $orderModel->getDetail($targetId);
        $sp    = null;

        if ($order) {
            $spId = (int)($f3->get('GET.id') ?? 0);
            if ($spId > 0) {
                $sp = $spModel->getById($spId);
                if ($sp && (int)$sp['order_id'] !== $targetId) {
                    $sp = null;
                }
            }
            if (!$sp) {
                $sp = $spModel->getByOrderId($targetId);
            }
        } else {
            $sp = $spModel->getById($targetId);
            if ($sp && !empty($sp['order_id'])) {
                $order = $orderModel->getDetail((int)$sp['order_id']);
            }
        }

        if (!$sp) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Surat penawaran tidak ditemukan.']);
            exit;
        }

        if (!$order) {
            $order = [
                'id'                 => (int)($sp['order_id'] ?? 0),
                'nomor_order'        => 'ORD-OFFLINE',
                'nama_perusahaan'    => $sp['perusahaan'] ?? 'Mitra Balai',
                'pt_cv'              => '',
                'pic'                => $sp['nama'] ?? '-',
                'telepon'            => '-',
                'email'              => '-',
                'alamat'             => $sp['alamat'] ?? '-',
                'jenis_layanan_opti' => $sp['jenis_layanan'] ?? 'selulosa',
                'judul_kegiatan'     => $sp['perihal'] ?? 'Penawaran Layanan Jasa OPTI',
                'estimasi_biaya'     => (float)($sp['nominal_penawaran'] ?? 0),
                'spm_layanan'        => '30 Hari Kerja'
            ];
        }

        $kopParam = $f3->get('GET.kop') ?? $f3->get('POST.kop');
        $useKop = !isset($kopParam) || ($kopParam !== '0' && $kopParam !== 'tanpa_kop' && $kopParam !== 0 && $kopParam !== false);

        try {
            $pdf = $this->generatePdfObject($order, $sp, $useKop);
            $pdfContent = $pdf->Output('S');
            $base64 = base64_encode($pdfContent);

            $suffix = $useKop ? '' : '_Tanpa_Kop';
            $safeFilename = 'Surat_Penawaran_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $sp['nomor_surat'] ?: 'BBSPJIS') . $suffix . '.pdf';

            $responsePayload = [
                'success'     => true,
                'is_pdf'      => true,
                'filename'    => $safeFilename,
                'nomor_surat' => $sp['nomor_surat'] ?? '',
                'perusahaan'  => $order['nama_perusahaan'] ?? ($sp['perusahaan'] ?? ''),
                'base64'      => $base64
            ];
            if (!$pdf->lampiranMerged && !empty($pdf->lampiranPath) && is_file($pdf->lampiranPath)) {
                $responsePayload['attachment_base64'] = base64_encode(file_get_contents($pdf->lampiranPath));
                $responsePayload['attachment_ext'] = strtolower(pathinfo($pdf->lampiranPath, PATHINFO_EXTENSION));
            }

            if (!headers_sent()) {
                header('Content-Type: application/json');
                header('Cache-Control: no-cache, no-store, must-revalidate');
            }
            echo json_encode($responsePayload);
            exit;
        } catch (\Exception $e) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Gagal memproses dokumen PDF: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Endpoint Live Preview PDF Surat Penawaran (Menerima input live dari form)
     * Route: GET|POST /order/@id/penawaran/preview-pdf
     */
    public function previewPdf($f3, $params)
    {
        $this->requireAuth();
        $orderId = (int)($params['id'] ?? 0);

        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);

        if (!$order) {
            $this->f3->error(404, 'Order tidak ditemukan');
            return;
        }

        $spModel = new SuratPenawaran($this->db);
        $existingSp = $spModel->getByOrderId($orderId) ?: [];

        // Ambil data live dari POST atau GET
        $nomorSurat   = trim((string)($f3->get('POST.nomor_surat') ?? $f3->get('GET.nomor_surat') ?? ($existingSp['nomor_surat'] ?? $spModel->generateNomorSurat())));
        if (preg_match('/^\d+\/BBSPJIS\/MS\//i', $nomorSurat)) {
            $nomorSurat = 'B/' . $nomorSurat;
        }
        $tanggalSurat = trim((string)($f3->get('POST.tanggal_surat') ?? $f3->get('GET.tanggal_surat') ?? ($existingSp['tanggal_surat'] ?? date('Y-m-d'))));
        $perusahaan   = trim((string)($f3->get('POST.perusahaan') ?? $f3->get('GET.perusahaan') ?? ($existingSp['perusahaan'] ?? $order['nama_perusahaan'])));
        $pic          = trim((string)($f3->get('POST.nama') ?? $f3->get('GET.nama') ?? ($existingSp['nama'] ?? $order['pic'])));
        $alamat       = trim((string)($f3->get('POST.alamat') ?? $f3->get('GET.alamat') ?? ($existingSp['alamat'] ?? ($order['alamatcustomer'] ?? $order['alamat']))));
        $perihal      = trim((string)($f3->get('POST.perihal') ?? $f3->get('GET.perihal') ?? ($existingSp['perihal'] ?? ('Penawaran Layanan Jasa OPTI - ' . $order['judul_kegiatan']))));
        
        $nominalRaw   = $f3->get('POST.nominal_penawaran') ?? $f3->get('GET.nominal_penawaran');
        $nominal      = ($nominalRaw !== null && $nominalRaw !== '') ? (float)str_replace(['.', ','], '', (string)$nominalRaw) : (float)($existingSp['nominal_penawaran'] ?? $order['estimasi_biaya']);

        $durasiHari   = (int)($f3->get('POST.durasi_hari') ?? $f3->get('GET.durasi_hari') ?? 30);
        $spmLayanan   = trim((string)($f3->get('POST.spm_layanan') ?? $f3->get('GET.spm_layanan') ?? ($durasiHari > 0 ? "{$durasiHari} Hari Kerja" : ($order['spm_layanan'] ?? '30 Hari Kerja'))));

        $spPreview = [
            'id'                 => (int)($existingSp['id'] ?? 0),
            'order_id'           => $orderId,
            'nomor_surat'        => $nomorSurat,
            'tanggal_surat'      => $tanggalSurat,
            'perusahaan'         => $perusahaan,
            'nama'               => $pic,
            'alamat'             => $alamat,
            'perihal'            => $perihal,
            'hal'                => trim((string)($f3->get('POST.hal') ?? $f3->get('GET.hal') ?? ($existingSp['hal'] ?? ''))),
            'lampiran_teks'      => trim((string)($f3->get('POST.lampiran_teks') ?? $f3->get('GET.lampiran_teks') ?? ($existingSp['lampiran_teks'] ?? ''))),
            'nominal_penawaran'  => $nominal,
            'durasi_hari'        => $durasiHari,
            'ruang_lingkup'      => $f3->get('POST.ruang_lingkup') ?? $f3->get('GET.ruang_lingkup') ?? ($existingSp['ruang_lingkup'] ?? null),
            'jadwal_pelaksanaan' => $f3->get('POST.jadwal_pelaksanaan') ?? $f3->get('GET.jadwal_pelaksanaan') ?? ($existingSp['jadwal_pelaksanaan'] ?? null),
            'catatan_sampel'     => $f3->get('POST.catatan_sampel') ?? $f3->get('GET.catatan_sampel') ?? ($existingSp['catatan_sampel'] ?? null),
            'jabatan_pejabat'    => $f3->get('POST.jabatan_pejabat') ?? $f3->get('GET.jabatan_pejabat') ?? ($existingSp['jabatan_pejabat'] ?? (($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'Kepala Bagian Tata Usaha' : 'Kepala')),
            'pejabat_nama'       => $f3->get('POST.pejabat_nama') ?? $f3->get('GET.pejabat_nama') ?? ($existingSp['pejabat_nama'] ?? (($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'Joko Pratomo' : 'Dodiet Prasetyo')),
            'pembuat_nama'       => $_SESSION['user']['nama'] ?? ($_SESSION['nama_lengkap'] ?? ($existingSp['pembuat_nama'] ?? 'Tim Mitra BBSPJIS')),
            'file_lampiran_user' => $existingSp['file_lampiran_user'] ?? ($_SESSION['pending_lampiran_sp_' . $orderId] ?? '')
        ];

        if (!empty($spmLayanan)) {
            $order['spm_layanan'] = $spmLayanan;
        }

        $kopParam = $f3->get('POST.kop') ?? $f3->get('GET.kop');
        $useKop = !isset($kopParam) || ($kopParam !== '0' && $kopParam !== 'tanpa_kop' && $kopParam !== 0 && $kopParam !== false);

        $pdf = $this->generatePdfObject($order, $spPreview, $useKop);

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="Preview_Penawaran_' . $orderId . '.pdf"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        $pdf->Output('I', 'Preview_Penawaran_' . $orderId . '.pdf');
        exit;
    }

    /**
     * Membangun objek PDF Surat Penawaran Resmi SILOPTI sesuai Tata Naskah Dinas BBSPJIS
     * Mendukung 2 format resmi BBSPJIS + penggabungan lampiran user mulai Halaman 3 dan seterusnya:
     * 1. Selulosa: Format 2-halaman (Halaman 1 Surat Penawaran Resmi + Halaman 2 Lembar Persetujuan) + Hal 3+ Lampiran
     * 2. Lingkungan: Format 2-halaman (Halaman 1 Surat Penawaran Resmi + Halaman 2 Lembar Persetujuan) + Hal 3+ Lampiran
     */
    public function generatePdfObject(array $order, array $sp, bool $useKop = true): SiloptiSuratPenawaranPdf
    {
        $jenis = strtolower(trim((string)($order['jenis_layanan_opti'] ?? 'selulosa')));
        if ($jenis === 'lingkungan' || strpos($jenis, 'lingkungan') !== false) {
            $pdf = $this->buildPdfLingkungan($order, $sp, $useKop);
        } else {
            $pdf = $this->buildPdfSelulosa($order, $sp, $useKop);
        }
        $this->appendLampiranUserToPdf($pdf, $order, $sp);
        return $pdf;
    }

    /**
     * Memastikan berkas dokumen Word (doc/docx) terkonversi ke PDF beresolusi tinggi
     * untuk disambungkan penuh ke Halaman 3+ Surat Penawaran atau dirender halamannya
     */
    private function ensureConvertedPdf(string $sourceFullPath): ?string
    {
        if (!is_file($sourceFullPath)) {
            return null;
        }
        $ext = strtolower(pathinfo($sourceFullPath, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            return $sourceFullPath;
        }
        if (!in_array($ext, ['doc', 'docx'], true)) {
            return null;
        }

        $rootDir = dirname(__DIR__, 2);
        $cacheDir = $rootDir . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $targetPdfName = 'word_attachment_' . md5($sourceFullPath . '_' . filemtime($sourceFullPath)) . '.pdf';
        $cachedPdfPath = $cacheDir . '/' . $targetPdfName;

        if (is_file($cachedPdfPath) && filesize($cachedPdfPath) > 0) {
            return $cachedPdfPath;
        }

        $psScript = $rootDir . '/app/helpers/convert_docx_to_pdf.ps1';
        if (is_file($psScript)) {
            $winDocx = str_replace('/', '\\', $sourceFullPath);
            $winPdf = str_replace('/', '\\', $cachedPdfPath);
            $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($psScript) . ' -docxPath ' . escapeshellarg($winDocx) . ' -pdfPath ' . escapeshellarg($winPdf);
            @exec($cmd, $out, $ret);
            if ($ret === 0 && is_file($cachedPdfPath) && filesize($cachedPdfPath) > 0) {
                return $cachedPdfPath;
            }
        }

        // Cek juga jika ada file PDF berdampingan dengan nama sama
        $siblingPdf = preg_replace('/\.(?:docx|doc)$/i', '.pdf', $sourceFullPath);
        if (is_file($siblingPdf) && filesize($siblingPdf) > 0) {
            return $siblingPdf;
        }

        return null;
    }

    /**
     * Menyambungkan berkas lampiran yang diunggah user ke PDF Surat Penawaran mulai Halaman 3 dan seterusnya
     */
    private function appendLampiranUserToPdf(SiloptiSuratPenawaranPdf $pdf, array $order, array $sp)
    {
        $orderId = (int)($order['id'] ?? ($sp['order_id'] ?? 0));
        $relPath = trim((string)($sp['file_lampiran_user'] ?? ''));

        if ($relPath === '' && $orderId > 0 && !empty($_SESSION['pending_lampiran_sp_' . $orderId])) {
            $relPath = trim((string)$_SESSION['pending_lampiran_sp_' . $orderId]);
        }

        if ($relPath === '') {
            $spId = (int)($sp['id'] ?? 0);
            if ($spId > 0) {
                $rows = $this->db->exec('SELECT file_lampiran_user FROM tb_surat_penawaran WHERE id = ? LIMIT 1', [1 => $spId]);
                if (!empty($rows[0]['file_lampiran_user'])) {
                    $relPath = trim((string)$rows[0]['file_lampiran_user']);
                }
            } elseif ($orderId > 0) {
                $rows = $this->db->exec('SELECT file_lampiran_user FROM tb_surat_penawaran WHERE order_id = ? AND file_lampiran_user IS NOT NULL AND file_lampiran_user <> \'\' ORDER BY id DESC LIMIT 1', [1 => $orderId]);
                if (!empty($rows[0]['file_lampiran_user'])) {
                    $relPath = trim((string)$rows[0]['file_lampiran_user']);
                }
            }
        }

        // Khusus OPTI Selulosa: lampiran Halaman 3+ langsung menyesuaikan dokumen yang diunggah PIC di Tahap 4
        if (($relPath === '' || ($order['jenis_layanan_opti'] ?? '') === 'selulosa') && $orderId > 0) {
            $propRows = $this->db->exec('SELECT file_proposal FROM opti_proposal_riset WHERE order_id = ? AND file_proposal IS NOT NULL AND file_proposal <> \'\' ORDER BY id DESC LIMIT 1', [1 => $orderId]);
            if (!empty($propRows[0]['file_proposal']) && empty($_SESSION['pending_lampiran_sp_' . $orderId])) {
                $relPath = trim((string)$propRows[0]['file_proposal']);
            }
        }

        if ($relPath === '') {
            return;
        }

        $rootDir = dirname(__DIR__, 2);
        $fullPath = $rootDir . '/' . ltrim($relPath, '/\\');
        if (!is_file($fullPath)) {
            return;
        }

        $pdf->lampiranPath = $fullPath;
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $pdf->isAttachmentPage = false; // Biarkan nomor halaman - 3 - tampil rapi di atas gambar lampiran
            $pdf->AddPage('P', 'A4');
            $imgInfo = @getimagesize($fullPath);
            if ($imgInfo && $imgInfo[0] > 0 && $imgInfo[1] > 0) {
                $maxW = 170;
                $maxH = 250;
                $ratio = min($maxW / $imgInfo[0], $maxH / $imgInfo[1]);
                $drawW = $imgInfo[0] * $ratio;
                $drawH = $imgInfo[1] * $ratio;
                $xPos = 20 + (($maxW - $drawW) / 2);
                $pdf->Image($fullPath, $xPos, 20, $drawW, $drawH);
                $pdf->lampiranMerged = true;
            }
            return;
        }

        if ($ext === 'pdf') {
            try {
                $pdf->isAttachmentPage = true;
                $pageCount = $pdf->setSourceFile($fullPath);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $tplId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($tplId);
                    $orientation = !empty($size['orientation']) ? $size['orientation'] : (($size['width'] > $size['height']) ? 'L' : 'P');
                    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                    $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
                }
                $pdf->isAttachmentPage = false;
                $pdf->lampiranMerged = true;
            } catch (\Throwable $e) {
                $pdf->isAttachmentPage = false;
                $pdf->lampiranMerged = false;
            }
            return;
        }

        if (in_array($ext, ['doc', 'docx'], true)) {
            $convertedPdf = $this->ensureConvertedPdf($fullPath);
            if (!empty($convertedPdf) && is_file($convertedPdf)) {
                try {
                    $pdf->isAttachmentPage = true;
                    $pageCount = $pdf->setSourceFile($convertedPdf);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $tplId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($tplId);
                        $orientation = !empty($size['orientation']) ? $size['orientation'] : (($size['width'] > $size['height']) ? 'L' : 'P');
                        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                        $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
                    }
                    $pdf->isAttachmentPage = false;
                    $pdf->lampiranMerged = true;
                    return;
                } catch (\Throwable $e) {
                    $pdf->isAttachmentPage = false;
                    $pdf->lampiranMerged = false;
                }
            }

            // Fallback jika konversi Word ke PDF belum berhasil: tampilkan halaman informasi berkas lampiran
            $pdf->isAttachmentPage = false;
            $pdf->AddPage('P', 'A4');
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'LAMPIRAN DOKUMEN PENDUKUNG / TEKNIS', 0, 1, 'C');
            $pdf->Ln(4);

            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(245, 247, 250);
            $pdf->Cell(0, 8, '  INFORMASI BERKAS LAMPIRAN', 1, 1, 'L', true);

            $pdf->SetFont('Arial', '', 9.5);
            $pdf->Cell(45, 7, '  Nama Dokumen', 'L', 0);
            $pdf->Cell(5, 7, ':', 0, 0);
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 7, basename($fullPath), 'R', 1);

            $pdf->SetFont('Arial', '', 9.5);
            $pdf->Cell(45, 7, '  Format Berkas', 'L', 0);
            $pdf->Cell(5, 7, ':', 0, 0);
            $pdf->Cell(0, 7, 'Microsoft Word Document (.' . strtoupper($ext) . ')', 'R', 1);

            $pdf->Cell(45, 7, '  Ukuran Berkas', 'L', 0);
            $pdf->Cell(5, 7, ':', 0, 0);
            $pdf->Cell(0, 7, round(filesize($fullPath) / 1024, 1) . ' KB', 'R', 1);

            $pdf->Cell(45, 8, '  Status Integrasi', 'LB', 0);
            $pdf->Cell(5, 8, ':', 'B', 0);
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 8, 'Terlampir resmi pada sistem OPTI Tracker BBSPJIS', 'RB', 1);

            $pdf->Ln(6);
            $pdf->SetFont('Arial', 'I', 8.5);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->MultiCell(0, 4.5, 'Dokumen asli Microsoft Word ini dilampirkan sebagai rincian teknis penawaran dan dapat diakses/diunduh secara utuh melalui aplikasi OPTI Tracker BBSPJIS.');
            $pdf->SetTextColor(0, 0, 0);

            $pdf->lampiranMerged = true;
            return;
        }
    }

    /**
     * Format 1: Surat Penawaran Layanan Jasa Selulosa (2 Halaman Presisi: Cover Penawaran + Lembar Persetujuan)
     */
    private function buildPdfSelulosa(array $order, array $sp, bool $useKop = true): SiloptiSuratPenawaranPdf
    {
        $pdf = new SiloptiSuratPenawaranPdf('P', 'mm', 'A4');
        $pdf->useKop = $useKop;
        $pdf->jenisLayanan = 'selulosa';
        $pdf->AliasNbPages();
        // Margin: Kiri 3.0 cm (30 mm), Atas 0.9 cm (9 mm), Kanan 2.0 cm (20 mm), Bawah 1.5 cm (15 mm)
        $pdf->SetMargins(30, 9, 20);
        $pdf->SetAutoPageBreak(false);

        // ==========================================
        // HALAMAN 1: SURAT PENAWARAN RESMI
        // ==========================================
        $pdf->AddPage();

        // 1. KOP SURAT (Isi Halaman, Bukan Header)
        if ($useKop) {
            $logo = __DIR__ . '/../helpers/fpdf/kemenperin_logo.png';
            if (file_exists($logo)) {
                // Logo Kemenperin 4,11 x 1,29 cm rata kiri di kolom 4,33 cm (X: 30 mm)
                $pdf->Image($logo, 30.0, 13.5, 41.1, 12.9);
            }

            // Kolom Kanan 11,67 cm (X: 73.3 mm sd 190.0 mm), Rata Tengah
            $pdf->SetXY(73.3, 9.0);
            $pdf->SetFont('Arial', '', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(116.7, 4.6, 'BADAN STANDARDISASI DAN KEBIJAKAN JASA INDUSTRI', 0, 1, 'C');

            $pdf->SetX(73.3);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(116.7, 5.2, 'BALAI BESAR STANDARISASI DAN', 0, 1, 'C');

            $pdf->SetX(73.3);
            $pdf->Cell(116.7, 5.2, 'PELAYANAN JASA INDUSTRI SELULOSA', 0, 1, 'C');

            $pdf->SetXY(73.3, $pdf->GetY() + 0.7); // Jarak atas 2 pt ≈ 0.7 mm
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(116.7, 3.2, 'Jl. Raya Dayeuhkolot No. 132 Bandung 40258', 0, 1, 'C');

            $pdf->SetX(73.3);
            $emailBbs = 'bbs@kemenperin.go.id';
            $pdf->Cell(116.7, 3.2, 'Telp. (022) 5202980 (Hunting) & 5202871 Fax. (022) 5202871 E-mail : ' . $emailBbs, 0, 1, 'C');

            // Garis Bawah Kop: 2,25 pt (≈ 0.8 mm) hitam selebar 16 cm (X: 30 mm ke 190 mm), jarak teks ±6 pt (≈ 2.1 mm)
            $yLine = $pdf->GetY() + 2.1;
            $pdf->SetLineWidth(0.8);
            $pdf->Line(30.0, $yLine, 190.0, $yLine);
            $pdf->SetLineWidth(0.2); // reset default

            // Jarak satu paragraf kosong: jarak atas 10 pt, total sekitar 0,8 cm (8 mm)
            $pdf->SetY($yLine + 8.0);
        } else {
            $pdf->SetY(41.0);
        }

        $tglFormatted = self::formatTanggalIndoSurat($sp['tanggal_surat'] ?? date('Y-m-d'));
        $noSurat = $sp['nomor_surat'] ?: ('B/01/BBSPJIS/MS/' . SuratPenawaran::bulanKeRomawi((int)date('n')) . '/' . date('Y'));
        if (preg_match('/^\d+\/BBSPJIS\/MS\//i', $noSurat)) {
            $noSurat = 'B/' . $noSurat;
        }
        $lampiranTeks = !empty($sp['lampiran_teks']) ? $sp['lampiran_teks'] : '1 (satu) lembar';
        $halSurat = !empty($sp['hal']) ? $sp['hal'] : (!empty($sp['perihal']) ? $sp['perihal'] : 'Biaya OPTI');

        // 2. BLOK NOMOR / LAMPIRAN / HAL (Tabel 4 Kolom: 2.0 cm | 0.75 cm | 7.5 cm | 5.74 cm)
        $pdf->SetFont('Arial', '', 11);
        $lineH = 4.46;

        $pdf->SetX(30.0);
        $pdf->Cell(20.0, $lineH, 'Nomor', 0, 0, 'L');
        $pdf->Cell(7.5, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(75.0, $lineH, $noSurat, 0, 0, 'L');
        $pdf->Cell(57.5, $lineH, 'Bandung, ' . $tglFormatted, 0, 1, 'R');

        $pdf->SetX(30.0);
        $pdf->Cell(20.0, $lineH, 'Lampiran', 0, 0, 'L');
        $pdf->Cell(7.5, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(132.5, $lineH, $lampiranTeks, 0, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(20.0, $lineH, 'Hal', 0, 0, 'L');
        $pdf->Cell(7.5, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(132.5, $lineH, $halSurat, 0, 1, 'L');

        // 3. ALAMAT TUJUAN (Menempel Langsung Tanpa Jarak di Bawah Baris Hal)
        $perusahaan = !empty($sp['perusahaan']) ? trim($sp['perusahaan']) : (!empty($order['nama_perusahaan']) ? trim($order['nama_perusahaan']) : 'Pimpinan Perusahaan/Instansi');
        if (!empty($order['pt_cv']) && stripos($perusahaan, $order['pt_cv']) === false && stripos($perusahaan, 'IPB') === false && stripos($perusahaan, 'Pusat') === false) {
            $perusahaan = $order['pt_cv'] . ' ' . $perusahaan;
        }
        $ythTitle = (stripos($perusahaan, 'Pimpinan') === false && stripos($perusahaan, 'Kepala') === false && stripos($perusahaan, 'Direktur') === false)
            ? 'Pimpinan ' . $perusahaan
            : $perusahaan;

        $alamat = !empty($sp['alamat']) ? trim($sp['alamat']) : (!empty($order['alamat_lengkap']) ? trim($order['alamat_lengkap']) : (!empty($order['alamatcustomer']) ? trim($order['alamatcustomer']) : (!empty($order['alamat']) && $order['alamat'] !== '-' ? trim($order['alamat']) : '')));
        $picSurat = !empty($sp['nama']) ? trim($sp['nama']) : (!empty($order['pic']) ? trim($order['pic']) : '');

        $pdf->SetX(30.0);
        $pdf->Cell(160.0, $lineH, 'Yth. ' . $ythTitle, 0, 1, 'L');
        if (!empty($alamat)) {
            $alamatLines = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $alamat))));
            foreach ($alamatLines as $alLine) {
                $pdf->SetX(30.0);
                $pdf->Cell(160.0, $lineH, $alLine, 0, 1, 'L');
            }
        } else {
            $pdf->SetX(30.0);
            $pdf->Cell(160.0, $lineH, 'di Tempat', 0, 1, 'L');
        }
        if (!empty($picSurat) && $picSurat !== '-' && stripos($ythTitle, $picSurat) === false) {
            $pdf->SetX(30.0);
            $pdf->Cell(160.0, $lineH, 'u.p. ' . $picSurat, 0, 1, 'L');
        }

        // 4. PARAGRAF PEMBUKA (Jarak atas 12 pt ≈ 4.23 mm, Indent 1.27 cm, Align Left)
        $judulKegiatan = !empty($order['judul_kegiatan']) ? $order['judul_kegiatan'] : (!empty($sp['perihal']) ? $sp['perihal'] : 'Layanan Jasa Optimalisasi Teknologi Industri (OPTI)');
        $mediaRaw = !empty($sp['permintaan_melalui']) ? $sp['permintaan_melalui'] : (!empty($order['permintaan_melalui']) ? $order['permintaan_melalui'] : 'email');
        $mediaPhrase = self::formatMediaPermintaan($mediaRaw);

        $pdf->SetY($pdf->GetY() + 4.23);
        $teksPembuka = 'Menanggapi permintaan Saudara ' . $mediaPhrase . ' perihal "' . $judulKegiatan . '", dengan ini kami informasikan rincian kegiatan dan penawaran biaya layanan sebagai berikut:';
        $pdf->ParagraphWithIndent($teksPembuka, 12.7, $lineH, 160.0, 30.0);

        // 5. SLOT RINCIAN KEGIATAN / BIAYA (Kolom kosong 0.5 cm, Kolom isi 15.5 cm Align Left)
        $rawNaskah = !empty($sp['ruang_lingkup']) ? trim($sp['ruang_lingkup']) : '';
        if (empty($rawNaskah)) {
            $nominal = (float)($sp['nominal_penawaran'] ?? ($order['estimasi_biaya'] ?? 0));
            $terbilangStr = strtolower(self::terbilang($nominal));
            $durasiAngka = (int)($sp['durasi_hari'] ?? (preg_match('/(\d+)/', (string)($order['spm_layanan'] ?? ''), $dm) ? (int)$dm[1] : 20));
            if ($durasiAngka <= 0) $durasiAngka = 20;

            $rawNaskah = "1. Biaya layanan adalah sebesar Rp " . number_format($nominal, 0, ',', '.') . " (" . $terbilangStr . " rupiah), dengan ruang lingkup pekerjaan sebagai berikut:\n" .
                "   1) Beating LBKP hingga 300 mL CSF\n" .
                "   2) Penyediaan stock sebanyak 2 variasi.\n" .
                "   3) Pembuatan handsheet dengan variasi penambahan aditif 2 variasi (sesuai b).\n" .
                "   4) Penampungan air dari pembuatan handsheet setiap variasi ditampung untuk dibawa ke " . $perusahaan . "\n" .
                "   5) Pengujian handsheet meliputi: massa handsheet, gramatur, uji brightness, % retensi.\n" .
                "   6) Evaluasi dan penyusunan laporan teknis.\n" .
                "2. Pelaksanaan kegiatan untuk ruang lingkup 1) sampai dengan 4) akan dilakukan pada tanggal " . $tglFormatted . ".\n" .
                "3. Total waktu pelaksanaan pekerjaan selama " . $durasiAngka . " hari kerja.\n" .
                "4. Contoh aditif yang perlu disediakan sebanyak 50 ml.";
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawNaskah));
        foreach ($lines as $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '') continue;

            if (preg_match('/^(\d+)\.\s*(.*)/', $lineTrim, $mPoint)) {
                $yCur = $pdf->GetY();
                $pdf->SetXY(30.0, $yCur);
                $pdf->Cell(5.0, $lineH, $mPoint[1] . '.', 0, 0, 'L');
                $pdf->SetLeftMargin(35.0);
                $pdf->SetXY(35.0, $yCur);
                $pdf->MultiCell(155.0, $lineH, $mPoint[2], 0, 'L');
                $pdf->SetLeftMargin(30.0);
            } elseif (preg_match('/^(\d+\)|[a-z]\)|\-|\*|\•)\s*(.*)/i', $lineTrim, $mSub)) {
                $bullet = $mSub[1];
                if (strlen($bullet) == 1 && ($bullet == '-' || $bullet == '*' || $bullet == '•')) {
                    $bullet = '-';
                }
                $yCur = $pdf->GetY();
                $pdf->SetXY(35.0, $yCur);
                $pdf->Cell(5.0, $lineH, $bullet, 0, 0, 'L');
                $pdf->SetLeftMargin(40.0);
                $pdf->SetXY(40.0, $yCur);
                $pdf->MultiCell(150.0, $lineH, $mSub[2], 0, 'L');
                $pdf->SetLeftMargin(30.0);
            } else {
                $pdf->SetLeftMargin(35.0);
                $pdf->SetX(35.0);
                $pdf->MultiCell(155.0, $lineH, $lineTrim, 0, 'L');
                $pdf->SetLeftMargin(30.0);
            }
        }

        // 6. KETENTUAN (Jarak atas 6 pt ≈ 2.12 mm, Indent 1.27 cm, Poin 1-5 tanpa indent gantung Align Left)
        $pdf->SetY($pdf->GetY() + 2.12);
        $pdf->ParagraphWithIndent('Disamping itu perlu kami sampaikan pula ketentuan sebagai berikut :', 12.7, $lineH, 160.0, 30.0);

        $ketentuan = [
            'Persetujuan terhadap biaya pengujian mohon disampaikan secara tertulis melalui email.',
            'Pengujian dilaksanakan setelah pembayaran biaya dilakukan dan sampel kami terima.',
            'Pembayaran biaya pengujian dilakukan dengan transfer melalui virtual account bank.',
            'Untuk informasi lebih lanjut dapat menghubungi Bagian Layanan nomor: 0813 8686 6808/ 0856 5908 8926.',
            'Dilarang memberi gratifikasi dalam bentuk apapun atas layanan jasa yang kami berikan, jika terdapat pemberian dan penerimaan gratifikasi mohon dapat dilaporkan ke : http://bbs.kemenperin.go.id/kontak-kami/pengaduan-gratifikasi.'
        ];
        foreach ($ketentuan as $idx => $k) {
            $pdf->SetX(30.0);
            $pdf->MultiCell(160.0, $lineH, ($idx + 1) . '. ' . $k, 0, 'L');
        }

        // 7. PARAGRAF PENUTUP (Jarak atas 6 pt ≈ 2.12 mm, Indent 1.27 cm, Align Left)
        $pdf->SetY($pdf->GetY() + 2.12);
        $pdf->ParagraphWithIndent('Kami menunggu konfirmasi lebih lanjut. Atas perhatian dan kerja sama yang baik, kami sampaikan terima kasih.', 12.7, $lineH, 160.0, 30.0);

        // 8. BLOK TANDA TANGAN HALAMAN 1 (Kolom Kanan 6.4 cm di X: 126 mm, Rata Tengah, 4 Enter Kosong)
        $signerRole = !empty($sp['jabatan_pejabat']) ? trim($sp['jabatan_pejabat']) : 'Kepala';
        $signerName = !empty($sp['pejabat_nama']) ? trim($sp['pejabat_nama']) : (!empty($sp['pembuat_nama']) && $sp['pembuat_nama'] !== 'Tim Mitra BBSPJIS' ? trim($sp['pembuat_nama']) : 'Dodiet Prasetyo');

        $pdf->SetY($pdf->GetY() + 4.23); // Jarak atas 12 pt
        $signerStartX = 126.0 + (64.0 - $pdf->GetStringWidth($signerName)) / 2;
        $pdf->SetX($signerStartX);
        $pdf->Cell(64.0, $lineH, rtrim($signerRole, ',') . ',', 0, 1, 'L');
        $pdf->SetY($pdf->GetY() + 2.12 + 17.8); // Jarak bawah 6 pt + 4 baris kosong (≈ 50 pt / 4 enter)
        $pdf->SetX(126.0);
        $pdf->Cell(64.0, $lineH, $signerName, 0, 1, 'C');

        // ==========================================
        // HALAMAN 2: LEMBAR PERSETUJUAN
        // ==========================================
        $pdf->AddPage();

        // 1. Nomor Halaman -2- Rata Tengah di Paling Atas
        $pdf->SetXY(30.0, 9.0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(160.0, $lineH, '-2-', 0, 1, 'C');
        $pdf->SetY($pdf->GetY() + 4.23); // Jarak bawah 12 pt

        // 2. Blok Lampiran / Nomor / Tanggal (8.80 cm kosong | 1.92 cm | 0.64 cm | 4.64 cm)
        $yRef = $pdf->GetY();
        $pdf->SetXY(118.0, $yRef);
        $pdf->Cell(19.2, $lineH, 'Lampiran', 0, 0, 'L');
        $pdf->Cell(6.4, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(46.4, $lineH, 'I Surat', 0, 1, 'L');

        $pdf->SetX(118.0);
        $pdf->Cell(19.2, $lineH, 'Nomor', 0, 0, 'L');
        $pdf->Cell(6.4, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(46.4, $lineH, $noSurat, 0, 1, 'L');

        $pdf->SetX(118.0);
        $pdf->Cell(19.2, $lineH, 'Tanggal', 0, 0, 'L');
        $pdf->Cell(6.4, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(46.4, $lineH, $tglFormatted, 0, 1, 'L');

        // 3. Judul Lembar Persetujuan Surat Penawaran (Rata tengah, regular, jarak atas & bawah 18 pt ≈ 6.35 mm)
        $pdf->SetY($pdf->GetY() + 6.35);
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetX(30.0);
        $pdf->Cell(160.0, $lineH, 'Lembar Persetujuan Surat Penawaran', 0, 1, 'C');
        $pdf->SetY($pdf->GetY() + 6.35);

        // 4. Paragraf Pernyataan (Align Left, Tanpa Indent)
        $pdf->SetX(30.0);
        $pdf->MultiCell(160.0, $lineH, 'Dengan ini menyatakan bahwa telah membaca memahami dan menyetujui Surat Penawaran BBSPJIS.', 0, 'L');
        $pdf->SetX(30.0);
        $pdf->Cell(160.0, $lineH, 'Selanjutnya kami menyatakan:', 0, 1, 'L');

        // 5. Empat Butir Pernyataan (Tabel 2 kolom: 0.5 cm nomor, 15.5 cm teks align left, tanpa jarak antar butir)
        $pernyataan = [
            'Menyetujui ruang lingkup pekerjaan biaya layanan serta syarat dan ketentuan yang tercantum dalam surat penawaran.',
            'Bersedia memenuhi kewajiban pembayaran sesuai ketentuan yang berlaku setelah diterbitkan tagihan (invoice) oleh BBSPJIS.',
            'Persetujuan ini menjadi dasar bagi BBSPJIS untuk memproses permohonan layanan sesuai dengan ketentuan yang berlaku.',
            'Apabila terdapat perubahan ruang lingkup pekerjaan setelah persetujuan ini diberikan maka akan dilakukan penyesuaian melalui kesepakatan kedua belah pihak.'
        ];
        foreach ($pernyataan as $idx => $pText) {
            $yP = $pdf->GetY();
            $pdf->SetXY(30.0, $yP);
            $pdf->Cell(5.0, $lineH, ($idx + 1) . '.', 0, 0, 'L');
            $pdf->SetLeftMargin(35.0);
            $pdf->SetXY(35.0, $yP);
            $pdf->MultiCell(155.0, $lineH, $pText, 0, 'L');
            $pdf->SetLeftMargin(30.0);
        }

        // 6. Demikian Lembar Persetujuan... (Align left, jarak atas dan bawah 6 pt ≈ 2.12 mm)
        $pdf->SetY($pdf->GetY() + 2.12);
        $pdf->SetX(30.0);
        $pdf->MultiCell(160.0, $lineH, 'Demikian Lembar Persetujuan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.', 0, 'L');
        $pdf->SetY($pdf->GetY() + 2.12);

        // 7. Tabel Isian Konfirmasi Klien (Lebar 16 cm: 6.08 cm | 9.92 cm, border 0.5 pt, baris ttd 3.0 cm)
        $pdf->SetLineWidth(0.18); // 0.5 pt
        $hRow = 5.2;

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Tempat, Tanggal', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(160.0, $hRow, ' Menyetujui,', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Nama Perusahaan/Instansi', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Nama Pemohon', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Jabatan', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Nomor Telepon (WhatsApp)', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $ySignRow = $pdf->GetY();
        $pdf->SetX(30.0);
        $pdf->Cell(60.8, 30.0, '', 1, 0, 'L');
        $pdf->Cell(99.2, 30.0, '', 1, 1, 'L');
        $pdf->SetXY(30.0, $ySignRow + 1.2);
        $pdf->Cell(60.8, $lineH, ' Tanda Tangan dan Cap', 0, 0, 'L');
        $pdf->SetY($ySignRow + 30.0);
        $pdf->SetLineWidth(0.2); // reset default

        // 8. Catatan Bawah (Menempel langsung tanpa jarak)
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->SetX(30.0);
        $pdf->MultiCell(160.0, 3.8, 'Catatan: Mohon mengembalikan lembar persetujuan ini kepada BBSPJIS melalui email atau media komunikasi yang telah ditentukan sebagai dasar proses penerbitan invoice dan pelaksanaan layanan.', 0, 'L');

        // 9. Blok Tanda Tangan Kepala Halaman 2 (Sama Persis dengan Halaman 1)
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetY($pdf->GetY() + 4.23); // Jarak atas 12 pt
        $signerStartX = 126.0 + (64.0 - $pdf->GetStringWidth($signerName)) / 2;
        $pdf->SetX($signerStartX);
        $pdf->Cell(64.0, $lineH, rtrim($signerRole, ',') . ',', 0, 1, 'L');
        $pdf->SetY($pdf->GetY() + 2.12 + 17.8); // Jarak bawah 6 pt + 4 baris kosong (≈ 50 pt / 4 enter)
        $pdf->SetX(126.0);
        $pdf->Cell(64.0, $lineH, $signerName, 0, 1, 'C');

        return $pdf;
    }

    /**
     * Format 2: Surat Penawaran Pengujian Lingkungan (Cover Penawaran + Lembar Persetujuan)
     */
    private function buildPdfLingkungan(array $order, array $sp, bool $useKop = true): SiloptiSuratPenawaranPdf
    {
        $orderModel = new OrderLayanan($this->db);
        $pdf = new SiloptiSuratPenawaranPdf('P', 'mm', 'A4');
        $pdf->useKop = $useKop;
        $pdf->jenisLayanan = 'lingkungan';
        $pdf->AliasNbPages();
        $pdf->SetMargins(30, 9, 20);
        $pdf->SetAutoPageBreak(false);

        // ==========================================
        // HALAMAN 1: SURAT PENGANTAR PENAWARAN RESMI
        // ==========================================
        $pdf->AddPage();

        // 1. KOP SURAT (Isi Halaman, Bukan Header)
        if ($useKop) {
            $logo = __DIR__ . '/../helpers/fpdf/kemenperin_logo.png';
            if (file_exists($logo)) {
                $pdf->Image($logo, 30.0, 13.5, 41.1, 12.9);
            }

            $pdf->SetXY(73.3, 9.0);
            $pdf->SetFont('Arial', '', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(116.7, 4.6, 'BADAN STANDARDISASI DAN KEBIJAKAN JASA INDUSTRI', 0, 1, 'C');

            $pdf->SetX(73.3);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(116.7, 5.2, 'BALAI BESAR STANDARISASI DAN', 0, 1, 'C');

            $pdf->SetX(73.3);
            $pdf->Cell(116.7, 5.2, 'PELAYANAN JASA INDUSTRI SELULOSA', 0, 1, 'C');

            $pdf->SetXY(73.3, $pdf->GetY() + 0.7);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(116.7, 3.2, 'Jl. Raya Dayeuhkolot No. 132 Bandung 40258', 0, 1, 'C');

            $pdf->SetX(73.3);
            $emailBbs = 'bbs@kemenperin.go.id';
            $pdf->Cell(116.7, 3.2, 'Telp. (022) 5202980 (Hunting) & 5202871 Fax. (022) 5202871 E-mail : ' . $emailBbs, 0, 1, 'C');

            $yLine = $pdf->GetY() + 2.1;
            $pdf->SetLineWidth(0.8);
            $pdf->Line(30.0, $yLine, 190.0, $yLine);
            $pdf->SetLineWidth(0.2);

            $pdf->SetY($yLine + 8.0);
        } else {
            $pdf->SetY(41.0);
        }

        $tglFormatted = self::formatTanggalIndoSurat($sp['tanggal_surat'] ?? date('Y-m-d'));
        $noSurat = $sp['nomor_surat'] ?: ('B/01/BBSPJIS/MS/' . SuratPenawaran::bulanKeRomawi((int)date('n')) . '/' . date('Y'));
        if (preg_match('/^\d+\/BBSPJIS\/MS\//i', $noSurat)) {
            $noSurat = 'B/' . $noSurat;
        }
        $perusahaan = !empty($sp['perusahaan']) ? trim($sp['perusahaan']) : (!empty($order['nama_perusahaan']) ? trim($order['nama_perusahaan']) : 'Pimpinan Perusahaan/Instansi');
        if (!empty($order['pt_cv']) && stripos($perusahaan, $order['pt_cv']) === false) {
            $perusahaan = $order['pt_cv'] . ' ' . $perusahaan;
        }
        $ythTitle = (stripos($perusahaan, 'Pimpinan') === false && stripos($perusahaan, 'Kepala') === false && stripos($perusahaan, 'Direktur') === false)
            ? 'Pimpinan ' . $perusahaan
            : $perusahaan;

        $alamat = !empty($sp['alamat']) ? trim($sp['alamat']) : (!empty($order['alamat_lengkap']) ? trim($order['alamat_lengkap']) : (!empty($order['alamatcustomer']) ? trim($order['alamatcustomer']) : (!empty($order['alamat']) && $order['alamat'] !== '-' ? trim($order['alamat']) : 'di Tempat')));
        $picSurat = !empty($sp['nama']) ? trim($sp['nama']) : (!empty($order['pic']) ? trim($order['pic']) : '');
        $lampTeks = !empty($sp['lampiran_teks']) && $sp['lampiran_teks'] !== '-' ? $sp['lampiran_teks'] : '1 (satu) lembar';
        $halLing = !empty($sp['perihal']) ? $sp['perihal'] : (!empty($sp['hal']) ? $sp['hal'] : 'Biaya Pengujian Lingkungan');

        $signerRoleLing = !empty($sp['jabatan_pejabat']) ? trim($sp['jabatan_pejabat']) : 'Kepala Bagian Tata Usaha';
        $signerNameLing = !empty($sp['pejabat_nama']) ? trim($sp['pejabat_nama']) : (!empty($sp['pembuat_nama']) && $sp['pembuat_nama'] !== 'Tim Mitra BBSPJIS' && $sp['pembuat_nama'] !== 'Tim Mitra Kerjasama BBSPJIS' ? trim($sp['pembuat_nama']) : 'Joko Pratomo');

        // 2. Blok Identitas
        $pdf->SetFont('Arial', '', 11);
        $lineH = 4.46;

        $pdf->SetX(30.0);
        $pdf->Cell(20.0, $lineH, 'Nomor', 0, 0, 'L');
        $pdf->Cell(7.5, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(75.0, $lineH, $noSurat, 0, 0, 'L');
        $pdf->Cell(57.5, $lineH, 'Bandung, ' . $tglFormatted, 0, 1, 'R');

        $pdf->SetX(30.0);
        $pdf->Cell(20.0, $lineH, 'Lampiran', 0, 0, 'L');
        $pdf->Cell(7.5, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(132.5, $lineH, $lampTeks, 0, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(20.0, $lineH, 'Hal', 0, 0, 'L');
        $pdf->Cell(7.5, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(132.5, $lineH, $halLing, 0, 1, 'L');

        // 3. Alamat Tujuan
        $pdf->SetX(30.0);
        $pdf->Cell(160.0, $lineH, 'Yth. ' . $ythTitle, 0, 1, 'L');
        $alamatLines = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $alamat))));
        foreach ($alamatLines as $alLine) {
            $pdf->SetX(30.0);
            $pdf->Cell(160.0, $lineH, $alLine, 0, 1, 'L');
        }
        if (!empty($picSurat) && $picSurat !== '-' && stripos($ythTitle, $picSurat) === false) {
            $pdf->SetX(30.0);
            $pdf->Cell(160.0, $lineH, 'u.p. ' . $picSurat, 0, 1, 'L');
        }

        // 4. Paragraf Pembuka
        $judulKegiatan = !empty($order['judul_kegiatan']) ? $order['judul_kegiatan'] : 'Pengujian Sampel Lingkungan BBSPJIS';
        $mediaRaw = !empty($sp['permintaan_melalui']) ? $sp['permintaan_melalui'] : (!empty($order['permintaan_melalui']) ? $order['permintaan_melalui'] : 'email');
        $mediaPhrase = self::formatMediaPermintaan($mediaRaw);

        $pdf->SetY($pdf->GetY() + 4.23);
        $teksPembuka = 'Menanggapi permintaan Saudara ' . $mediaPhrase . ' perihal "' . $judulKegiatan . '", dengan ini kami informasikan sebagai berikut:';
        $pdf->ParagraphWithIndent($teksPembuka, 12.7, $lineH, 160.0, 30.0);

        // 5. Poin Rincian Penawaran Lingkungan
        $rawNaskah = !empty($sp['ruang_lingkup']) ? trim($sp['ruang_lingkup']) : '';
        $nextPointNum = 1;

        if (!empty($rawNaskah)) {
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawNaskah));
            foreach ($lines as $line) {
                $lineTrim = trim($line);
                if ($lineTrim === '') continue;

                if (preg_match('/^(\d+)\.\s*(.*)/', $lineTrim, $mPoint)) {
                    $pNum = (int)$mPoint[1];
                    if ($pNum >= $nextPointNum) {
                        $nextPointNum = $pNum + 1;
                    }
                    $yCur = $pdf->GetY();
                    $pdf->SetXY(30.0, $yCur);
                    $pdf->Cell(5.0, $lineH, $mPoint[1] . '.', 0, 0, 'L');
                    $pdf->SetLeftMargin(35.0);
                    $pdf->SetXY(35.0, $yCur);
                    $pdf->MultiCell(155.0, $lineH, $mPoint[2], 0, 'L');
                    $pdf->SetLeftMargin(30.0);
                } elseif (preg_match('/^(\d+\)|[a-z]\)|\-|\*|\•)\s*(.*)/i', $lineTrim, $mSub)) {
                    $bullet = $mSub[1];
                    if (strlen($bullet) == 1 && ($bullet == '-' || $bullet == '*' || $bullet == '•')) {
                        $bullet = '-';
                    }
                    $yCur = $pdf->GetY();
                    $pdf->SetXY(35.0, $yCur);
                    $pdf->Cell(5.0, $lineH, $bullet, 0, 0, 'L');
                    $pdf->SetLeftMargin(40.0);
                    $pdf->SetXY(40.0, $yCur);
                    $pdf->MultiCell(150.0, $lineH, $mSub[2], 0, 'L');
                    $pdf->SetLeftMargin(30.0);
                } else {
                    $pdf->SetLeftMargin(35.0);
                    $pdf->SetX(35.0);
                    $pdf->MultiCell(155.0, $lineH, $lineTrim, 0, 'L');
                    $pdf->SetLeftMargin(30.0);
                }
            }
        }

        // Poin Ketentuan Standar Lingkungan
        if (stripos($rawNaskah, 'Virtual Account Mandiri') === false) {
            $yCur = $pdf->GetY();
            $pdf->SetXY(30.0, $yCur);
            $pdf->Cell(5.0, $lineH, $nextPointNum . '.', 0, 0, 'L');
            $pdf->SetLeftMargin(35.0);
            $pdf->SetXY(35.0, $yCur);
            $pdf->MultiCell(155.0, $lineH, 'Biaya dan rincian pekerjaan terlampir, dengan ketentuan sebagai berikut:', 0, 'L');
            $pdf->SetLeftMargin(30.0);

            $ketentuanLing = [
                'a' => 'Biaya pekerjaan ditagihkan dan dibayar melalui Virtual Account Mandiri.',
                'b' => 'Persetujuan terhadap biaya pekerjaan tersebut mohon disampaikan secara tertulis melalui fax atau e-mail.',
                'c' => 'Jadwal pelaksanaan pekerjaan akan disampaikan setelah pembayaran biaya pekerjaan dilakukan.',
                'd' => 'Dilarang memberi gratifikasi dalam bentuk apapun atas layanan jasa yang kami berikan, jika terdapat pemberian dan penerimaan gratifikasi mohon dapat dilaporkan ke : http://bbs.kemenperin.go.id/kontak-kami/pengaduan-gratifikasi.'
            ];
            foreach ($ketentuanLing as $subK => $subV) {
                $yCur = $pdf->GetY();
                $pdf->SetXY(35.0, $yCur);
                $pdf->Cell(5.0, $lineH, $subK . '.', 0, 0, 'L');
                $pdf->SetLeftMargin(40.0);
                $pdf->SetXY(40.0, $yCur);
                $pdf->MultiCell(150.0, $lineH, $subV, 0, 'L');
                $pdf->SetLeftMargin(30.0);
            }
        }

        // 6. Penutup
        $pdf->SetY($pdf->GetY() + 2.12);
        $pdf->ParagraphWithIndent('Kami menunggu konfirmasi lebih lanjut. Atas perhatian dan kerja sama yang baik, kami sampaikan terima kasih.', 12.7, $lineH, 160.0, 30.0);

        // 7. Tanda Tangan Cover
        $pdf->SetY($pdf->GetY() + 4.23);
        $signerStartXLing = 126.0 + (64.0 - $pdf->GetStringWidth($signerNameLing)) / 2;
        $pdf->SetX($signerStartXLing);
        $pdf->Cell(64.0, $lineH, rtrim($signerRoleLing, ',') . ',', 0, 1, 'L');
        $pdf->SetY($pdf->GetY() + 2.12 + 17.8); // 4 enter kosong
        $pdf->SetX(126.0);
        $pdf->Cell(64.0, $lineH, $signerNameLing, 0, 1, 'C');

        // ==========================================
        // HALAMAN 2: LEMBAR PERSETUJUAN
        // ==========================================
        $pdf->AddPage();

        // 1. Nomor Halaman -2- Rata Tengah
        $pdf->SetXY(30.0, 9.0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(160.0, $lineH, '-2-', 0, 1, 'C');
        $pdf->SetY($pdf->GetY() + 4.23);

        // 2. Blok Lampiran / Nomor / Tanggal
        $yRef = $pdf->GetY();
        $pdf->SetXY(118.0, $yRef);
        $pdf->Cell(19.2, $lineH, 'Lampiran', 0, 0, 'L');
        $pdf->Cell(6.4, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(46.4, $lineH, 'I Surat', 0, 1, 'L');

        $pdf->SetX(118.0);
        $pdf->Cell(19.2, $lineH, 'Nomor', 0, 0, 'L');
        $pdf->Cell(6.4, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(46.4, $lineH, $noSurat, 0, 1, 'L');

        $pdf->SetX(118.0);
        $pdf->Cell(19.2, $lineH, 'Tanggal', 0, 0, 'L');
        $pdf->Cell(6.4, $lineH, ':', 0, 0, 'C');
        $pdf->Cell(46.4, $lineH, $tglFormatted, 0, 1, 'L');

        // 3. Judul Lembar Persetujuan
        $pdf->SetY($pdf->GetY() + 6.35);
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetX(30.0);
        $pdf->Cell(160.0, $lineH, 'Lembar Persetujuan Surat Penawaran', 0, 1, 'C');
        $pdf->SetY($pdf->GetY() + 6.35);

        // 4. Paragraf Pernyataan
        $pdf->SetX(30.0);
        $pdf->MultiCell(160.0, $lineH, 'Dengan ini menyatakan bahwa telah membaca memahami dan menyetujui Surat Penawaran BBSPJIS.', 0, 'L');
        $pdf->SetX(30.0);
        $pdf->Cell(160.0, $lineH, 'Selanjutnya kami menyatakan:', 0, 1, 'L');

        // 5. Butir Pernyataan
        $pernyataan = [
            'Menyetujui ruang lingkup pekerjaan biaya layanan serta syarat dan ketentuan yang tercantum dalam surat penawaran.',
            'Bersedia memenuhi kewajiban pembayaran sesuai ketentuan yang berlaku setelah diterbitkan tagihan (invoice) oleh BBSPJIS.',
            'Persetujuan ini menjadi dasar bagi BBSPJIS untuk memproses permohonan layanan sesuai dengan ketentuan yang berlaku.',
            'Apabila terdapat perubahan ruang lingkup pekerjaan setelah persetujuan ini diberikan maka akan dilakukan penyesuaian melalui kesepakatan kedua belah pihak.'
        ];
        foreach ($pernyataan as $idx => $pText) {
            $yP = $pdf->GetY();
            $pdf->SetXY(30.0, $yP);
            $pdf->Cell(5.0, $lineH, ($idx + 1) . '.', 0, 0, 'L');
            $pdf->SetLeftMargin(35.0);
            $pdf->SetXY(35.0, $yP);
            $pdf->MultiCell(155.0, $lineH, $pText, 0, 'L');
            $pdf->SetLeftMargin(30.0);
        }

        // 6. Demikian
        $pdf->SetY($pdf->GetY() + 2.12);
        $pdf->SetX(30.0);
        $pdf->MultiCell(160.0, $lineH, 'Demikian Lembar Persetujuan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.', 0, 'L');
        $pdf->SetY($pdf->GetY() + 2.12);

        // 7. Tabel Isian Konfirmasi Klien
        $pdf->SetLineWidth(0.18);
        $hRow = 5.2;

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Tempat, Tanggal', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(160.0, $hRow, ' Menyetujui,', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Nama Perusahaan/Instansi', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Nama Pemohon', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Jabatan', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $pdf->SetX(30.0);
        $pdf->Cell(60.8, $hRow, ' Nomor Telepon (WhatsApp)', 1, 0, 'L');
        $pdf->Cell(99.2, $hRow, '', 1, 1, 'L');

        $ySignRow = $pdf->GetY();
        $pdf->SetX(30.0);
        $pdf->Cell(60.8, 30.0, '', 1, 0, 'L');
        $pdf->Cell(99.2, 30.0, '', 1, 1, 'L');
        $pdf->SetXY(30.0, $ySignRow + 1.2);
        $pdf->Cell(60.8, $lineH, ' Tanda Tangan dan Cap', 0, 0, 'L');
        $pdf->SetY($ySignRow + 30.0);
        $pdf->SetLineWidth(0.2);

        // 8. Catatan Bawah
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->SetX(30.0);
        $pdf->MultiCell(160.0, 3.8, 'Catatan: Mohon mengembalikan lembar persetujuan ini kepada BBSPJIS melalui email atau media komunikasi yang telah ditentukan sebagai dasar proses penerbitan invoice dan pelaksanaan layanan.', 0, 'L');

        // 9. Tanda Tangan Pejabat Lingkungan Halaman 2
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetY($pdf->GetY() + 4.23);
        $signerStartXLing = 126.0 + (64.0 - $pdf->GetStringWidth($signerNameLing)) / 2;
        $pdf->SetX($signerStartXLing);
        $pdf->Cell(64.0, $lineH, rtrim($signerRoleLing, ',') . ',', 0, 1, 'L');
        $pdf->SetY($pdf->GetY() + 2.12 + 17.8); // 4 enter kosong
        $pdf->SetX(126.0);
        $pdf->Cell(64.0, $lineH, $signerNameLing, 0, 1, 'C');

        return $pdf;
    }

    /**
     * Helper penyebutan media permintaan masuk pada naskah pembuka surat penawaran
     */
    public static function formatMediaPermintaan(?string $media): string
    {
        switch (strtolower(trim((string)$media))) {
            case 'surat':
                return 'melalui surat';
            case 'telepon':
                return 'melalui telepon';
            case 'whatsapp':
            case 'wa':
                return 'melalui WhatsApp';
            case 'website':
            case 'web':
                return 'melalui website resmi BBSPJIS';
            case 'fax':
                return 'melalui faksimile';
            case 'datang_langsung':
                return 'secara langsung';
            case 'pegawai_bbspjis':
                return 'melalui Petugas Kemitraan';
            case 'email':
            default:
                return 'melalui e-mail';
        }
    }

    /**
     * Helper format tanggal ke Bahasa Indonesia (Contoh: 7 September 2026)
     */
    private static function formatTanggalIndoSurat(?string $dateStr): string
    {
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $time = !empty($dateStr) ? strtotime($dateStr) : time();
        if (!$time) $time = time();
        $d = date('j', $time);
        $m = (int)date('n', $time);
        $y = date('Y', $time);
        return $d . ' ' . ($bulanIndo[$m] ?? date('F', $time)) . ' ' . $y;
    }

    /**
     * Helper konversi angka ke ejaan Bahasa Indonesia (Terbilang)
     */
    private static function terbilang(float $nilai): string
    {
        $nilai = abs($nilai);
        $huruf = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
        $temp = "";
        if ($nilai < 12) {
            $temp = " " . $huruf[(int)$nilai];
        } else if ($nilai < 20) {
            $temp = self::terbilang($nilai - 10) . " belas";
        } else if ($nilai < 100) {
            $temp = self::terbilang((int)($nilai / 10)) . " puluh " . self::terbilang($nilai % 10);
        } else if ($nilai < 200) {
            $temp = " seratus " . self::terbilang($nilai - 100);
        } else if ($nilai < 1000) {
            $temp = self::terbilang((int)($nilai / 100)) . " ratus " . self::terbilang($nilai % 100);
        } else if ($nilai < 2000) {
            $temp = " seribu " . self::terbilang($nilai - 1000);
        } else if ($nilai < 1000000) {
            $temp = self::terbilang((int)($nilai / 1000)) . " ribu " . self::terbilang($nilai % 1000);
        } else if ($nilai < 1000000000) {
            $temp = self::terbilang((int)($nilai / 1000000)) . " juta " . self::terbilang(fmod($nilai, 1000000));
        } else if ($nilai < 1000000000000) {
            $temp = self::terbilang((int)($nilai / 1000000000)) . " milyar " . self::terbilang(fmod($nilai, 1000000000));
        } else if ($nilai < 1000000000000000) {
            $temp = self::terbilang((int)($nilai / 1000000000000)) . " trilyun " . self::terbilang(fmod($nilai, 1000000000000));
        }
        return trim(preg_replace('/\s+/', ' ', $temp));
    }
    /**
     * Upload berkas kelengkapan Surat Penawaran DEAL:
     * 1. penawaran_ttd     -> file_penawaran_ttd (Wajib)
     * 2. bukti_persetujuan -> file_bukti_persetujuan (Wajib)
     * 3. surat_kesanggupan -> surat_kesanggupan_bayar (Opsional)
     * Route: POST /surat-penawaran/@id/dokumen-deal/@jenis/upload
     */
    public function uploadDokumenDeal($f3, $params)
    {
        $id = (int)($params['id'] ?? 0);
        $jenis = trim((string)($params['jenis'] ?? ($f3->get('POST.jenis_dokumen') ?? '')));
        $referer = $f3->get('HEADERS.Referer') ?: '/surat-penawaran';
        $isAjax = $f3->get('POST.ajax') == '1' || $f3->get('AJAX');

        $colMap = [
            'penawaran_ttd'     => 'file_penawaran_ttd',
            'bukti_persetujuan' => 'file_bukti_persetujuan',
            'surat_kesanggupan' => 'surat_kesanggupan_bayar',
        ];
        $prefixMap = [
            'penawaran_ttd'     => 'sp_ttd_',
            'bukti_persetujuan' => 'bukti_acc_',
            'surat_kesanggupan' => 'skb_',
        ];
        $labelMap = [
            'penawaran_ttd'     => 'Surat Penawaran yang Sudah Ditandatangani',
            'bukti_persetujuan' => 'Bukti Persetujuan Surat Penawaran',
            'surat_kesanggupan' => 'Surat Kesanggupan',
        ];

        if (!isset($colMap[$jenis])) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Jenis dokumen tidak valid.']);
                exit;
            }
            $this->setFlashError('Jenis dokumen tidak valid.');
            $f3->reroute($referer);
            return;
        }

        $col = $colMap[$jenis];
        $prefix = $prefixMap[$jenis];
        $label = $labelMap[$jenis];

        $gagal = function ($pesan) use ($f3, $referer, $isAjax) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $pesan]);
                exit;
            }
            $this->setFlashError($pesan);
            $f3->reroute($referer);
        };

        // Cari file dari berbagai kemungkinan nama input
        $file = $f3->get('FILES.dokumen_file') ?: ($f3->get('FILES.file') ?: ($f3->get('FILES.' . $col) ?: ($f3->get('FILES.' . $jenis) ?: $f3->get('FILES.surat_kesanggupan_bayar'))));
        if (empty($file) && !empty($_FILES)) {
            $file = reset($_FILES);
        }

        if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $gagal("Berkas {$label} wajib dipilih.");
            return;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $gagal('Gagal mengunggah file (kode error: ' . $file['error'] . ').');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (!in_array($ext, $allowedExt, true)) {
            $gagal("Dokumen {$label} harus berformat PDF, JPG, PNG, atau Word (.doc/.docx).");
            return;
        }

        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $gagal('Ukuran file maksimal 10MB.');
            return;
        }

        $mimeAsli = mime_content_type($file['tmp_name']);
        $mimeDiizinkan = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ];
        if (!in_array($mimeAsli, $mimeDiizinkan, true)) {
            $gagal('Isi file tidak valid atau tidak sesuai format yang diizinkan (PDF/Gambar/Word).');
            return;
        }

        $dir = dirname(__DIR__, 2) . '/storage/kesanggupan_bayar';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $namaFile = $prefix . $id . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $tujuan = $dir . '/' . $namaFile;

        if (!move_uploaded_file($file['tmp_name'], $tujuan)) {
            $gagal('Gagal menyimpan file ke server.');
            return;
        }

        // Hapus file lama jika ada
        $spLama = $this->db->exec("SELECT {$col} FROM tb_surat_penawaran WHERE id = ?", [1 => $id]);
        if (!empty($spLama[0][$col])) {
            $pathLama = $dir . '/' . basename($spLama[0][$col]);
            if (is_file($pathLama)) { @unlink($pathLama); }
        }

        $this->db->exec(
            "UPDATE tb_surat_penawaran SET {$col} = ? WHERE id = ?",
            [1 => $namaFile, 2 => $id]
        );

        $spRow = $this->db->exec('SELECT order_id FROM tb_surat_penawaran WHERE id = ?', [1 => $id]);
        $orderId = (int)($spRow[0]['order_id'] ?? 0);
        if ($orderId > 0) {
            $this->logActivity($orderId, 'penawaran', 'upload_' . $jenis, "Unggah {$label}");
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'     => true,
                'message'     => "{$label} berhasil diunggah.",
                'preview_url' => $f3->get('BASE') . '/surat-penawaran/' . $id . '/dokumen-deal/' . $jenis . '/preview',
                'file_name'   => $namaFile,
                'jenis'       => $jenis
            ]);
            exit;
        }

        $this->setFlashSuccess("{$label} berhasil diunggah.");
        $f3->reroute($referer);
    }

    /**
     * Preview berkas dokumen penawaran DEAL
     * Route: GET /surat-penawaran/@id/dokumen-deal/@jenis/preview
     */
    public function previewDokumenDeal($f3, $params)
    {
        $id = (int)($params['id'] ?? 0);
        $jenis = trim((string)($params['jenis'] ?? ''));

        $colMap = [
            'penawaran_ttd'     => 'file_penawaran_ttd',
            'bukti_persetujuan' => 'file_bukti_persetujuan',
            'surat_kesanggupan' => 'surat_kesanggupan_bayar',
        ];
        $labelMap = [
            'penawaran_ttd'     => 'Surat Penawaran Bertandatangan',
            'bukti_persetujuan' => 'Bukti Persetujuan Surat Penawaran',
            'surat_kesanggupan' => 'Surat Kesanggupan',
        ];

        if (!isset($colMap[$jenis])) {
            $f3->error(404, 'Jenis dokumen tidak valid.');
            return;
        }

        $col = $colMap[$jenis];
        $label = $labelMap[$jenis];

        $row = $this->db->exec("SELECT {$col} FROM tb_surat_penawaran WHERE id = ? OR order_id = ? ORDER BY id DESC LIMIT 1", [1 => $id, 2 => $id]);
        if (empty($row) || empty($row[0][$col])) {
            $f3->error(404, "{$label} tidak ditemukan.");
            return;
        }

        $namaFile = basename($row[0][$col]);
        $path = dirname(__DIR__, 2) . '/storage/kesanggupan_bayar/' . $namaFile;
        if (!is_file($path)) {
            $pathAlt = dirname(__DIR__, 2) . '/uploads/penawaran/' . $namaFile;
            if (is_file($pathAlt)) {
                $path = $pathAlt;
            }
        }

        if (!is_file($path)) {
            $f3->error(404, 'File tidak ditemukan di server.');
            return;
        }

        $ext = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . $namaFile . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** POST /surat-penawaran/@id/surat-kesanggupan/upload (Backward Compatibility) */
    public function uploadKesanggupanBayar($f3, $params)
    {
        $params['jenis'] = 'surat_kesanggupan';
        return $this->uploadDokumenDeal($f3, $params);
    }

    /** GET /surat-penawaran/@id/surat-kesanggupan/preview (Backward Compatibility) */
    public function previewKesanggupanBayar($f3, $params)
    {
        $params['jenis'] = 'surat_kesanggupan';
        return $this->previewDokumenDeal($f3, $params);
    }

    /**
     * Upload lampiran tambahan (opsional) oleh user pada Surat Penawaran
     * Route: POST /order/@id/penawaran/upload-lampiran ATAU POST /surat-penawaran/@id/upload-lampiran
     */
    public function uploadLampiranUser($f3, $params)
    {
        $targetId = (int)($params['id'] ?? 0);
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if (!$this->hasPermission('penawaran:create') && !$this->isSuperadmin() && !$this->isAdminOrder()) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Tim Mitra yang dapat mengunggah lampiran penawaran.']);
                exit;
            }
            $this->setFlashError('Akses ditolak.');
            $f3->reroute('/order/' . $targetId);
            return;
        }

        if (empty($_FILES['file_lampiran_user']) || $_FILES['file_lampiran_user']['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Silakan pilih berkas lampiran terlebih dahulu.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $this->setFlashError($msg);
            $f3->reroute('/order/' . $targetId);
            return;
        }

        $file = $_FILES['file_lampiran_user'];
        $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        $origName = $file['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            $msg = 'Format file tidak didukung. Gunakan PDF, Word, Excel, atau Gambar (JPG/PNG).';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $this->setFlashError($msg);
            $f3->reroute('/order/' . $targetId);
            return;
        }

        if ((int)$file['size'] > 10 * 1024 * 1024) {
            $msg = 'Ukuran file maksimal 10 MB.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $this->setFlashError($msg);
            $f3->reroute('/order/' . $targetId);
            return;
        }

        $lampiranDir = 'uploads/penawaran/lampiran/';
        if (!is_dir($lampiranDir)) {
            @mkdir($lampiranDir, 0777, true);
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $safeBase = substr($safeBase ?: 'Lampiran', 0, 50);
        $newFileName = 'Lampiran_SP_' . $targetId . '_' . time() . '_' . $safeBase . '.' . $ext;
        $destPath = $lampiranDir . $newFileName;

        if (!@move_uploaded_file($file['tmp_name'], $destPath)) {
            $msg = 'Gagal menyimpan berkas lampiran di server.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $this->setFlashError($msg);
            $f3->reroute('/order/' . $targetId);
            return;
        }

        $spModel = new SuratPenawaran($this->db);
        $spIdExplicit = (int)($f3->get('POST.sp_id') ?? $f3->get('GET.sp_id') ?? 0);
        $sp = null;
        if ($spIdExplicit > 0) {
            $sp = $spModel->getById($spIdExplicit);
        }
        if (!$sp) {
            $sp = $spModel->getByOrderId($targetId);
        }
        if (!$sp) {
            $sp = $spModel->getById($targetId);
        }

        if ($sp && !empty($sp['id'])) {
            $this->db->exec(
                "UPDATE tb_surat_penawaran SET file_lampiran_user = ?, updated_at = NOW() WHERE id = ?",
                [1 => $destPath, 2 => (int)$sp['id']]
            );
            $orderIdForSession = (int)($sp['order_id'] ?? $targetId);
            unset($_SESSION['pending_lampiran_sp_' . $orderIdForSession]);
        } else {
            $_SESSION['pending_lampiran_sp_' . $targetId] = $destPath;
        }

        $previewUrl = $f3->get('BASE') . '/order/' . ($sp['order_id'] ?? $targetId) . '/penawaran/lampiran-user' . (!empty($sp['id']) ? ('?id=' . $sp['id']) : '');

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'     => true,
                'message'     => 'Lampiran surat penawaran berhasil diunggah.',
                'file_name'   => $origName,
                'file_path'   => $destPath,
                'preview_url' => $previewUrl
            ]);
            exit;
        }

        $this->setFlashSuccess('Lampiran surat penawaran berhasil diunggah.');
        $f3->reroute('/order/' . ($sp['order_id'] ?? $targetId));
    }

    /**
     * Hapus berkas lampiran tambahan pada Surat Penawaran
     * Route: POST /order/@id/penawaran/hapus-lampiran ATAU POST /surat-penawaran/@id/hapus-lampiran
     */
    public function hapusLampiranUser($f3, $params)
    {
        $targetId = !empty($params['id']) ? (int)$params['id'] : 0;
        $isAjax = $f3->get('AJAX') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $spId = (int)($f3->get('POST.sp_id') ?: $f3->get('GET.sp_id') ?: 0);
        $orderId = $targetId;

        // Reset session pending lampiran
        unset($_SESSION['pending_lampiran_sp_' . $orderId]);
        if ($spId > 0) {
            unset($_SESSION['pending_lampiran_sp_' . $spId]);
            $this->db->exec("UPDATE tb_surat_penawaran SET file_lampiran_user = NULL, updated_at = NOW() WHERE id = ?", [1 => $spId]);
        } elseif ($orderId > 0) {
            $this->db->exec("UPDATE tb_surat_penawaran SET file_lampiran_user = NULL, updated_at = NOW() WHERE order_id = ?", [1 => $orderId]);
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Lampiran surat penawaran berhasil dilepas.']);
            exit;
        }

        $this->setFlashSuccess('Lampiran surat penawaran berhasil dilepas.');
        $f3->reroute('/order/' . $orderId);
    }

    /**
     * Pratinjau / unduh berkas lampiran user pada Surat Penawaran
     * Route: GET /order/@id/penawaran/lampiran-user ATAU GET /surat-penawaran/@id/lampiran-user
     */
    public function previewLampiranUser($f3, $params)
    {
        $targetId = (int)($params['id'] ?? 0);
        $spIdExplicit = (int)($f3->get('GET.id') ?? 0);
        $spModel = new SuratPenawaran($this->db);

        $sp = null;
        if ($spIdExplicit > 0) {
            $sp = $spModel->getById($spIdExplicit);
        }
        if (!$sp) {
            $sp = $spModel->getByOrderId($targetId);
        }
        if (!$sp) {
            $sp = $spModel->getById($targetId);
        }

        $relPath = '';
        if ($sp && !empty($sp['file_lampiran_user'])) {
            $relPath = $sp['file_lampiran_user'];
        } elseif (!empty($_SESSION['pending_lampiran_sp_' . $targetId])) {
            $relPath = $_SESSION['pending_lampiran_sp_' . $targetId];
        }

        if (empty($relPath)) {
            $f3->error(404, 'Berkas lampiran surat penawaran belum diunggah.');
            return;
        }

        $rootDir = dirname(__DIR__, 2);
        $fullPath = $rootDir . '/' . ltrim($relPath, '/\\');
        if (!is_file($fullPath)) {
            $f3->error(404, 'Berkas lampiran tidak ditemukan di server.');
            return;
        }

        $namaFile = basename($fullPath);
        $ext = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . $namaFile . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}
