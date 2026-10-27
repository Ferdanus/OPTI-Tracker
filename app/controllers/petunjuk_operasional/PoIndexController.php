<?php
namespace petunjuk_operasional;

/**
 * PoIndexController -- halaman DAFTAR Petunjuk Operasional (PO) untuk layanan SELAIN OPTI.
 *
 * Sengaja terpisah dari daftar PO OPTI (/po-kegiatan -> PoKegiatanController):
 *   - URL     : GET /petunjuk-operasional[?layanan=sertifikasi&tab=draft|terkirim]
 *   - View    : app/views/petunjuk_operasional/index.html
 *   - Data    : po_kegiatan dengan kolom layanan BUKAN 'opti-*' (sekarang baru 'sertifikasi')
 *   - Tab     : Draft & Terkirim saja (alur validasi Tim Mitra/Keuangan belum ada untuk layanan ini)
 *   - Filter  : Layanan, Bulan, Tahun, "Tampilkan Semua Data", pencarian realtime (pola sama dengan PO OPTI)
 *   - Notif   : banner di atas daftar bila masih ada order sertifikasi (status Order & lunas) yang
 *               belum dibuatkan PO.
 *
 * AKSES: sementara HANYA Superadmin (filter layanan bebas dipilih). Penambahan role layanan sedang
 * disiapkan (COMING SOON): role Sertifikasi nantinya otomatis terkunci ke layanan 'sertifikasi' dan
 * tidak bisa mengubah filter -- tempatnya sudah disiapkan di layananTerkunci() & izinAkses().
 *
 * Mewarisi PoSertifikasiController hanya untuk memakai query order sertifikasi yang sama
 * (queryOrderLayak) -- supaya hitungan notif persis sama dengan isi modal pilih order.
 *
 * Kompatibel PHP 7.2.
 */
class PoIndexController extends PoSertifikasiController {

    /** slug => label. Slug sama dengan yang dipakai kartu jenis PO di /po-kegiatan/pilih. */
    protected function daftarLayanan() {
        return [
            'sertifikasi'         => 'Sertifikasi',
            'pengujian'           => 'Pengujian',
            'kalibrasi'           => 'Kalibrasi',
            'pendampingan_teknis' => 'Pendampingan Teknis',
            'konsultansi'         => 'Konsultansi',
            'inspeksi'            => 'Inspeksi',
            'uji_profesiensi'     => 'Uji Profesiensi',
        ];
    }

    /**
     * Layanan yang dikunci untuk pengguna saat ini (null = bebas memilih).
     * COMING SOON: begitu role layanan ditambahkan, role Sertifikasi mengembalikan 'sertifikasi' di sini
     * dan izinAkses() meloloskannya -- filter di halaman otomatis terkunci & tidak bisa diubah.
     */
    protected function layananTerkunci() {
        // Tim Kerja Pengujian otomatis terkunci ke layanan 'pengujian' (filter layanan tidak bisa diubah)
        if ($this->getUserRole() === 'tim_kerja_pengujian') {
            return 'pengujian';
        }
        // Tim Kerja & Ketua Tim Sertifikasi otomatis terkunci ke layanan 'sertifikasi'
        if ($this->isRoleSertifikasi()) {
            return 'sertifikasi';
        }
        return null;
    }

    /** Superadmin (semua layanan), Tim Kerja Pengujian (Pengujian), Tim Kerja & Ketua Tim Sertifikasi (Sertifikasi). */
    protected function izinAkses() {
        return $this->isSuperadmin() || $this->getUserRole() === 'tim_kerja_pengujian' || $this->isRoleSertifikasi();
    }

    protected function intBetween($v, $min, $max, $default) {
        $n = (int) $v;
        return ($n >= $min && $n <= $max) ? $n : $default;
    }

    public function index($f3) {
        $this->requireAuth();
        if (!$this->izinAkses()) {
            $this->setFlashError('Halaman Daftar PO layanan ini sementara hanya bisa diakses Superadmin (role layanan akan segera hadir).');
            $f3->reroute('/dashboard');
            return;
        }
        // Satu halaman Daftar PO untuk semua layanan: dialihkan ke /po-kegiatan (filter Divisi = layanan), jadi tampilannya sama dengan PO OPTI.
        // Tautan notifikasi "buat PO" (?buat=1) langsung membuka pemilihan order di halaman Tambah PO.
        $layananAlih = strtolower(trim((string) $f3->get('GET.layanan')));
        $terkunciAlih = $this->layananTerkunci();
        if ($terkunciAlih !== null) { $layananAlih = $terkunciAlih; }
        if (!isset($this->daftarLayanan()[$layananAlih])) { $layananAlih = ''; }
        if ((string) $f3->get('GET.buat') === '1') {
            $f3->reroute('/po-kegiatan/pilih?jenis=' . ($layananAlih !== '' ? $layananAlih : 'sertifikasi'));
            return;
        }
        $tabAlih = strtolower(trim((string) $f3->get('GET.tab')));
        $paramAlih = [];
        if ($layananAlih !== '') { $paramAlih[] = 'divisi=' . rawurlencode($layananAlih); }
        if ($tabAlih === 'draft') { $paramAlih[] = 'tab=draft'; } elseif ($tabAlih === 'terkirim') { $paramAlih[] = 'tab=berjalan'; }
        foreach (['q', 'bulan', 'tahun', 'semua'] as $kAlih) {
            $vAlih = (string) $f3->get('GET.' . $kAlih);
            if ($vAlih !== '') { $paramAlih[] = $kAlih . '=' . rawurlencode($vAlih); }
        }
        $f3->reroute('/po-kegiatan' . ($paramAlih ? '?' . implode('&', $paramAlih) : ''));
        return;
        $this->kendalaSkema();
        // kolom id_arsip_surat (order PO Pengujian) dipakai query daftar di bawah -- pastikan sudah ada
        try { \PoPengujian::pastikan($this->db); } catch (\Throwable $e) { error_log('[PO Index] ' . $e->getMessage()); }
        // kolom tanggal_dikirim (waktu PO dikirim, otomatis); dipakai tampilan "Dikirim pada" & filter bulan PO terkirim
        $adaTglKirim = false;
        try { $adaTglKirim = \PoSchema::pastikanTanggalDikirim($this->db); } catch (\Throwable $e) { error_log('[PO Index] ' . $e->getMessage()); }

        // kolom daftar_order (PO Pengujian dengan banyak order); dipakai hanya bila ada
        $adaDaftarOrder = false;
        try {
            $kol = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'daftar_order'");
            $adaDaftarOrder = !empty($kol);
        } catch (\Throwable $e) { error_log('[PO Index] ' . $e->getMessage()); }

        $semuaLayanan = $this->daftarLayanan();
        $terkunci = $this->layananTerkunci();
        $layanan = $terkunci !== null ? $terkunci : strtolower(trim((string) $f3->get('GET.layanan')));
        if ($layanan !== '' && !isset($semuaLayanan[$layanan])) {
            $layanan = '';
        }

        $tab = strtolower(trim((string) $f3->get('GET.tab')));
        if (!in_array($tab, ['draft', 'terkirim'], true)) {
            $tab = 'draft';
        }

        // Sama dengan PO OPTI: tab Draft -> default tampilkan semua; tab Terkirim -> default bulan berjalan
        $semuaGet = $f3->get('GET.semua');
        $tampilkanSemua = ($semuaGet === null || $semuaGet === '') ? ($tab === 'draft') : ($semuaGet === '1');
        $bulan = $this->intBetween($f3->get('GET.bulan'), 1, 12, (int) date('n'));
        $tahun = $this->intBetween($f3->get('GET.tahun'), 2000, 2100, (int) date('Y'));

        $q = trim((string) $f3->get('GET.q'));
        $q = function_exists('mb_substr') ? mb_substr($q, 0, 100) : substr($q, 0, 100);

        // ---- WHERE dasar: PO non-OPTI ----
        // PO yang belum dikirim belum bernomor: tanggalnya = tanggal pembuatan; PO bernomor: tanggal PO (= tanggal kirim)
        $kolomTanggal = $adaTglKirim
            ? "CASE WHEN p.nomor_po IS NULL OR p.nomor_po = '' THEN DATE(p.created_at) ELSE COALESCE(DATE(p.tanggal_dikirim), p.tanggal_po, DATE(p.created_at)) END"
            : "CASE WHEN p.nomor_po IS NULL OR p.nomor_po = '' THEN DATE(p.created_at) ELSE COALESCE(p.tanggal_po, DATE(p.created_at)) END";
        $dasar = "p.layanan IS NOT NULL AND p.layanan NOT LIKE 'opti-%'";
        $dasarParams = [];
        if ($layanan !== '') {
            $dasar .= " AND p.layanan = ?";
            $dasarParams[] = $layanan;
        }

        $where = $dasar . " AND p.status = ?";
        $params = array_merge($dasarParams, [$tab]);
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where .= " AND (p.nomor_po LIKE ? OR p.judul_kegiatan LIKE ? OR a.nomor_order LIKE ? OR a.no_id_order LIKE ? OR c.nmcustomer LIKE ? OR p.nama_perusahaan LIKE ? OR s.nomor_surat LIKE ?" . ($adaDaftarOrder ? " OR p.daftar_order LIKE ?" : '') . ")";
            for ($k = 0; $k < ($adaDaftarOrder ? 8 : 7); $k++) { $params[] = $like; }
        }
        if (!$tampilkanSemua) {
            $where .= " AND {$kolomTanggal} IS NOT NULL AND MONTH({$kolomTanggal}) = ? AND YEAR({$kolomTanggal}) = ?";
            $params[] = $bulan;
            $params[] = $tahun;
        }

        $this->ensureDatabaseSertifikasi();
        $dbName = $this->namaDbSertifikasi();
        $from = "FROM po_kegiatan p
                 LEFT JOIN `{$dbName}`.`tb_arsip_sertifikasi` a ON p.layanan = '" . \PoSchema::SERTIFIKASI . "' AND a.id_arsip_sertifikasi = p.order_id
                 LEFT JOIN tb_arsipsurat s ON p.layanan = '" . \PoPengujian::LAYANAN . "' AND s.id_arsip = p.id_arsip_surat
                 LEFT JOIN tb_customer c ON c.id_customer = COALESCE(a.id_customer, s.id_customer)";
        // Cadangan bila database SISERTIFIKASI tidak terbaca: daftar tetap tampil, tanpa No. Order & Mitra
        $fromTanpaOrder = "FROM po_kegiatan p
                 LEFT JOIN (SELECT NULL AS id_customer, NULL AS nomor_order, NULL AS no_id_order) a ON 1 = 0
                 LEFT JOIN tb_arsipsurat s ON p.layanan = '" . \PoPengujian::LAYANAN . "' AND s.id_arsip = p.id_arsip_surat
                 LEFT JOIN tb_customer c ON c.id_customer = COALESCE(a.id_customer, s.id_customer)";

        $perHalaman = 10;
        $total = 0;
        $rows = [];
        $halaman = max(1, (int) $f3->get('GET.page'));
        $peringatan = '';
        foreach ([$from, $fromTanpaOrder] as $idx => $fromSql) {
            try {
                $res = $this->db->exec("SELECT COUNT(*) AS c {$fromSql} WHERE {$where}", $this->indeks($params));
                $total = (int) ($res[0]['c'] ?? 0);
                $totalHalaman = max(1, (int) ceil($total / $perHalaman));
                if ($halaman > $totalHalaman) { $halaman = $totalHalaman; }
                $offset = ($halaman - 1) * $perHalaman;
                $rows = $this->db->exec(
                    "SELECT p.id, p.layanan, p.nomor_po, p.tanggal_po, " . ($adaTglKirim ? 'p.tanggal_dikirim, ' : '') . "p.judul_kegiatan, p.status, p.order_id, p.created_at, p.updated_at, p.nama_perusahaan, p.jenis," . ($adaDaftarOrder ? ' p.daftar_order,' : '') . "
                            a.nomor_order, a.no_id_order, s.nomor_surat, c.nmcustomer, c.pt_cv
                     {$fromSql}
                     WHERE {$where}
                     ORDER BY COALESCE(p.updated_at, p.created_at) DESC, p.id DESC
                     LIMIT {$perHalaman} OFFSET {$offset}",
                    $this->indeks($params)
                );
                if ($idx === 1) {
                    $peringatan = 'Database ' . $dbName . ' tidak terbaca, kolom No. Order & Mitra dikosongkan.';
                }
                break;
            } catch (\Throwable $e) {
                error_log('[PO Index] gagal membaca daftar PO: ' . $e->getMessage());
                $total = 0; $rows = [];
            }
        }
        $totalHalaman = max(1, (int) ceil($total / $perHalaman));

        $daftar = $this->petakanBarisDaftar($rows, ($halaman - 1) * $perHalaman, $semuaLayanan, $f3);

        // ---- jumlah per tab (tidak ikut filter bulan/pencarian, tetap ikut filter layanan) ----
        $tabCounts = ['draft' => 0, 'terkirim' => 0];
        $totalPo = 0; $totalDisetujui = 0; // untuk 4 kartu ringkasan di atas daftar
        try {
            $rc = $this->db->exec(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) AS draft,
                        SUM(CASE WHEN p.status = 'terkirim' THEN 1 ELSE 0 END) AS terkirim,
                        SUM(CASE WHEN p.status IN ('disetujui', 'disetujui_mitra') THEN 1 ELSE 0 END) AS disetujui
                 FROM po_kegiatan p WHERE {$dasar}",
                $this->indeks($dasarParams)
            );
            $tabCounts['draft'] = (int) ($rc[0]['draft'] ?? 0);
            $tabCounts['terkirim'] = (int) ($rc[0]['terkirim'] ?? 0);
            $totalPo = (int) ($rc[0]['total'] ?? 0);
            $totalDisetujui = (int) ($rc[0]['disetujui'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[PO Index] gagal menghitung tab: ' . $e->getMessage());
        }

        // ---- notif: order sertifikasi yang belum dibuatkan PO -> masuk ke Pusat Pemberitahuan (lonceng + bubble) ----
        // dijalankan sebelum render() supaya langsung muncul di bubble/lonceng halaman ini juga
        if (!$this->terlarangRoleLayananLain()) {
            $this->sinkronNotifOrderBelumPo(); // notifikasi order Sertifikasi bukan urusan role layanan lain
        }

        $daftarLayananView = [];
        foreach ($semuaLayanan as $slug => $label) {
            $daftarLayananView[] = ['slug' => $slug, 'label' => $label];
        }
        $tahunIni = (int) date('Y');
        $daftarTahun = range($tahunIni + 1, min(2024, $tahun));

        $f3->set('daftar_po', $daftar);
        $f3->set('tab_aktif', $tab);
        $f3->set('tab_counts', $tabCounts);
        $f3->set('total_po', $totalPo);
        $f3->set('total_perlu_tindakan', $tabCounts['draft']);
        $f3->set('total_berjalan', $tabCounts['terkirim']);
        $f3->set('total_disetujui', $totalDisetujui);
        $f3->set('layanan', $layanan);
        $f3->set('layanan_list', $daftarLayananView);
        $f3->set('bisa_pilih_layanan', $terkunci === null);
        $f3->set('bisa_buat_po', !$this->isKetuaSertifikasi()); // Ketua Tim Sertifikasi meninjau saja: tidak membuat/mengedit PO
        $f3->set('nama_layanan_tampilan', $layanan === '' ? 'Semua Layanan' : $semuaLayanan[$layanan]);
        $f3->set('bulan_filter', $bulan);
        $f3->set('tahun_filter', $tahun);
        $f3->set('daftar_tahun', $daftarTahun);
        $f3->set('tampilkan_semua', $tampilkanSemua);
        $f3->set('q', $q);
        $f3->set('q_url', rawurlencode($q));
        $f3->set('total_baris', $total);
        $f3->set('total_halaman', $totalHalaman);
        $f3->set('halaman_saat_ini', $halaman);
        $f3->set('peringatan_index', $peringatan);

        $this->render('petunjuk_operasional/index.html', 'Daftar PO Layanan', 'po_layanan');
    }

    /** Susun baris hasil query daftar PO layanan menjadi baris tampilan (dipakai index() dan ambilGabungan()). */
    protected function petakanBarisDaftar(array $rows, $noAwal, array $semuaLayanan, $f3) {
        $daftar = [];
        $no = (int) $noAwal;
        foreach ($rows as $r) {
            $slug = (string) $r['layanan'];
            $order = trim((string) ($r['nomor_order'] ?? ''));
            if ($order === '') { $order = trim((string) ($r['no_id_order'] ?? '')); }
            if ($order === '') { $order = trim((string) ($r['nomor_surat'] ?? '')); } // Pengujian: nomor surat permintaan (order dari tb_arsipsurat)
            // PO Pengujian untuk banyak order: kolom No. Order = jumlah order, Mitra = teks yang diisi di form PO
            $banyakOrder = 0;
            if (!empty($r['daftar_order'])) {
                $dOrder = json_decode((string) $r['daftar_order'], true);
                if (is_array($dOrder) && !empty($dOrder['items']) && is_array($dOrder['items']) && count($dOrder['items']) > 1) { $banyakOrder = count($dOrder['items']); }
            }
            if ($banyakOrder > 1) { $order = $banyakOrder . ' order'; }
            $sudahBernomor = trim((string) ($r['nomor_po'] ?? '')) !== '';
            $sah = function ($v) { $v = (string) $v; return ($v !== '' && strpos($v, '0000') !== 0 && strtotime($v)) ? strtotime($v) : null; };
            $tKirim = $sudahBernomor ? $sah($r['tanggal_dikirim'] ?? '') : null;
            $labelTgl = '-'; $dibuatBeda = '';
            if ($tKirim) {
                // PO yang tanggal dikirimnya tercatat otomatis: tanggal_po = tanggal PO dibuat (diisi user di form).
                // Tampil "Dikirim pada ..."; bila bulan/tahun tanggal PO berbeda, tampil juga "Dibuat ..."
                $labelTgl = 'Dikirim pada ' . date('d/m/Y', $tKirim);
                $tPo = $sah($r['tanggal_po'] ?? '');
                if ($tPo && date('Y-m', $tPo) !== date('Y-m', $tKirim)) { $dibuatBeda = 'Dibuat ' . date('d/m/Y', $tPo); }
            } elseif ($sudahBernomor) {
                // PO terkirim versi lama / layanan lain: tanggal_po = tanggal kirim; bila draft-nya dibuat di bulan berbeda, tampil tanggal dibuatnya
                $tPo = $sah($r['tanggal_po'] ?? '');
                if ($tPo) {
                    $labelTgl = 'Dikirim pada ' . date('d/m/Y', $tPo);
                    $tCreated = $sah($r['created_at'] ?? '');
                    if ($tCreated && date('Y-m', $tCreated) !== date('Y-m', $tPo)) { $dibuatBeda = 'Dibuat ' . date('d/m/Y', $tCreated); }
                }
            } else {
                // draft: PO Sertifikasi memakai Tanggal PO yang diisi user; lainnya tanggal pembuatan
                $tD = ($slug === \PoSchema::SERTIFIKASI) ? $sah($r['tanggal_po'] ?? '') : null;
                $tD = $tD ?: $sah($r['created_at'] ?? '');
                if ($tD) { $labelTgl = 'Dibuat ' . date('d/m/Y', $tD); }
            }
            $daftar[] = [
                'no_urut'       => ++$no,
                'po_id'         => (int) $r['id'],
                'nomor_po'      => (string) ($r['nomor_po'] ?? ''),
                'tanggal_po'    => $labelTgl,
                'dibuat_beda'   => $dibuatBeda,
                'judul'         => (string) ($r['judul_kegiatan'] ?? ''),
                'status'        => (string) $r['status'],
                'layanan'       => $slug,
                'jenis'         => trim((string) ($r['jenis'] ?? '')),
                'layanan_label' => $semuaLayanan[$slug] ?? ucfirst(str_replace('_', ' ', $slug)),
                'nomor_order'   => $order,
                // nilai kontrak & total terbayar (hanya order Sertifikasi yang punya angkanya; lainnya null)
                'nilai_kontrak' => (isset($r['total_nilai_kontrak']) && $r['total_nilai_kontrak'] !== null && $r['total_nilai_kontrak'] !== '') ? (float) $r['total_nilai_kontrak'] : null,
                'terbayar'      => (isset($r['total_bayar_kontrak']) && $r['total_bayar_kontrak'] !== null && $r['total_bayar_kontrak'] !== '') ? (float) $r['total_bayar_kontrak'] : null,
                // mitra dari order (tb_customer); PO tanpa order (Pengujian lama): nama perusahaan yang diisi di form PO
                'mitra'         => ($banyakOrder > 1 && trim((string) ($r['nama_perusahaan'] ?? '')) !== '') ? trim((string) $r['nama_perusahaan'])
                                    : (($r['nmcustomer'] ?? '') !== '' || ($r['pt_cv'] ?? '') !== ''
                                    ? \Customer::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nmcustomer'] ?? '') : trim((string) ($r['nama_perusahaan'] ?? ''))),
                // baru layanan sertifikasi & pengujian yang punya form simpan/edit; layanan lain menyusul
                'url_edit'      => (!$this->isKetuaSertifikasi() && in_array($slug, [\PoSchema::SERTIFIKASI, \PoPengujian::LAYANAN], true)) ? $f3->get('BASE') . '/petunjuk-operasional/' . $slug . '/' . (int) $r['id'] . '/edit' : '',
                'url_lihat'     => in_array($slug, [\PoSchema::SERTIFIKASI, \PoPengujian::LAYANAN], true) ? $f3->get('BASE') . '/petunjuk-operasional/' . $slug . '/' . (int) $r['id'] . '/lihat' : '',
            ];
        }
        return $daftar;
    }

    /**
     * [BARU] Data PO layanan non-OPTI untuk Daftar PO gabungan Superadmin (/po-kegiatan: "Semua Divisi" atau satu layanan).
     * Kondisi dan filternya sama dengan index(). $limit = jumlah baris teratas yang diambil (digabung dengan PO OPTI oleh pemanggil).
     * Return ['total' => int, 'rows' => [], 'counts' => [draft, terkirim, disetujui_mitra, disetujui] (ikut filter pencarian & bulan),
     *         'counts_semua' => (sama, tanpa pencarian & bulan), 'peringatan' => string].
     * $semuaPerTab = [tab => bool]: apakah filter bulan dimatikan untuk tab itu (dipakai menghitung angka tiap tab); kosong = ikut $tampilkanSemua.
     * Status efektif: PO yang sudah diverifikasi PPK BLU (validasi_keuangan_at terisi) dihitung 'disetujui'.
     */
    public function ambilGabungan($tab, $layanan, $q, $tampilkanSemua, $bulan, $tahun, $limit, $semuaPerTab = null) {
        $nol = ['draft' => 0, 'terkirim' => 0, 'disetujui_mitra' => 0, 'disetujui' => 0];
        $hasil = ['total' => 0, 'rows' => [], 'counts' => $nol, 'counts_semua' => $nol, 'peringatan' => ''];
        $semuaPerTab = is_array($semuaPerTab) ? $semuaPerTab : [];
        $f3 = \Base::instance();
        try { $this->kendalaSkema(); } catch (\Throwable $e) { error_log('[PO Gabungan] ' . $e->getMessage()); }
        try { \PoPengujian::pastikan($this->db); } catch (\Throwable $e) { error_log('[PO Gabungan] ' . $e->getMessage()); }
        $adaTglKirim = false;
        try { $adaTglKirim = \PoSchema::pastikanTanggalDikirim($this->db); } catch (\Throwable $e) { error_log('[PO Gabungan] ' . $e->getMessage()); }
        $adaDaftarOrder = false;
        try { $kol = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'daftar_order'"); $adaDaftarOrder = !empty($kol); } catch (\Throwable $e) { error_log('[PO Gabungan] ' . $e->getMessage()); }

        $semuaLayanan = $this->daftarLayanan();
        if ($layanan !== '' && !isset($semuaLayanan[$layanan])) { $layanan = ''; }

        $kolomTanggal = $adaTglKirim
            ? "CASE WHEN p.nomor_po IS NULL OR p.nomor_po = '' THEN DATE(p.created_at) ELSE COALESCE(DATE(p.tanggal_dikirim), p.tanggal_po, DATE(p.created_at)) END"
            : "CASE WHEN p.nomor_po IS NULL OR p.nomor_po = '' THEN DATE(p.created_at) ELSE COALESCE(p.tanggal_po, DATE(p.created_at)) END";
        $dasar = "p.layanan IS NOT NULL AND p.layanan NOT LIKE 'opti-%'";
        $dasarParams = [];
        if ($layanan !== '') { $dasar .= " AND p.layanan = ?"; $dasarParams[] = $layanan; }

        $stEf = "(CASE WHEN p.validasi_keuangan_at IS NOT NULL OR p.status = 'disetujui' THEN 'disetujui' ELSE p.status END)";
        $daftarStatus = ['draft', 'terkirim', 'disetujui_mitra', 'disetujui'];

        // jumlah per status untuk kartu ringkasan (tidak ikut filter bulan/pencarian, tetap ikut filter layanan)
        try {
            $sel = [];
            foreach ($daftarStatus as $k) { $sel[] = "SUM(CASE WHEN {$stEf} = '{$k}' THEN 1 ELSE 0 END) AS {$k}"; }
            $rc = $this->db->exec("SELECT " . implode(', ', $sel) . " FROM po_kegiatan p WHERE {$dasar}", $this->indeks($dasarParams));
            foreach ($daftarStatus as $k) { $hasil['counts_semua'][$k] = (int) (isset($rc[0][$k]) ? $rc[0][$k] : 0); }
        } catch (\Throwable $e) {
            error_log('[PO Gabungan] gagal menghitung status: ' . $e->getMessage());
        }
        $hasil['counts'] = $hasil['counts_semua'];   // cadangan bila hitungan berfilter di bawah gagal

        // pencarian (berlaku untuk semua tab)
        $whereQ = $dasar;
        $paramsQ = $dasarParams;
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $whereQ .= " AND (p.nomor_po LIKE ? OR p.judul_kegiatan LIKE ? OR a.nomor_order LIKE ? OR a.no_id_order LIKE ? OR c.nmcustomer LIKE ? OR p.nama_perusahaan LIKE ? OR s.nomor_surat LIKE ?" . ($adaDaftarOrder ? " OR p.daftar_order LIKE ?" : "") . ")";
            for ($k = 0; $k < ($adaDaftarOrder ? 8 : 7); $k++) { $paramsQ[] = $like; }
        }
        // angka tiap tab: pencarian + bulan/tahun (bila filter bulan berlaku untuk tab itu)
        $selF = [];
        foreach ($daftarStatus as $k) {
            $cond = "{$stEf} = '{$k}'";
            $semuaK = array_key_exists($k, $semuaPerTab) ? (bool) $semuaPerTab[$k] : (bool) $tampilkanSemua;
            if (!$semuaK) { $cond .= " AND {$kolomTanggal} IS NOT NULL AND MONTH({$kolomTanggal}) = " . (int) $bulan . " AND YEAR({$kolomTanggal}) = " . (int) $tahun; }
            $selF[] = "SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END) AS {$k}";
        }

        $where = $whereQ . " AND {$stEf} = ?";
        $params = array_merge($paramsQ, [$tab]);
        if (!$tampilkanSemua) {
            $where .= " AND {$kolomTanggal} IS NOT NULL AND MONTH({$kolomTanggal}) = ? AND YEAR({$kolomTanggal}) = ?";
            $params[] = (int) $bulan;
            $params[] = (int) $tahun;
        }
        $tabValid = in_array($tab, $daftarStatus, true);   // tab "PO Masuk" hanya untuk order OPTI

        $this->ensureDatabaseSertifikasi();
        $dbName = $this->namaDbSertifikasi();
        $from = "FROM po_kegiatan p
                 LEFT JOIN `{$dbName}`.`tb_arsip_sertifikasi` a ON p.layanan = '" . \PoSchema::SERTIFIKASI . "' AND a.id_arsip_sertifikasi = p.order_id
                 LEFT JOIN tb_arsipsurat s ON p.layanan = '" . \PoPengujian::LAYANAN . "' AND s.id_arsip = p.id_arsip_surat
                 LEFT JOIN tb_customer c ON c.id_customer = COALESCE(a.id_customer, s.id_customer)";
        $fromTanpaOrder = "FROM po_kegiatan p
                 LEFT JOIN (SELECT NULL AS id_customer, NULL AS nomor_order, NULL AS no_id_order, NULL AS total_nilai_kontrak, NULL AS total_bayar_kontrak) a ON 1 = 0
                 LEFT JOIN tb_arsipsurat s ON p.layanan = '" . \PoPengujian::LAYANAN . "' AND s.id_arsip = p.id_arsip_surat
                 LEFT JOIN tb_customer c ON c.id_customer = COALESCE(a.id_customer, s.id_customer)";

        foreach ([$from, $fromTanpaOrder] as $idx => $fromSql) {
            try {
                $rf = $this->db->exec("SELECT " . implode(', ', $selF) . " {$fromSql} WHERE {$whereQ}", $this->indeks($paramsQ));
                foreach ($daftarStatus as $k) { $hasil['counts'][$k] = (int) (isset($rf[0][$k]) ? $rf[0][$k] : 0); }
                if ($tabValid) {
                    $res = $this->db->exec("SELECT COUNT(*) AS c {$fromSql} WHERE {$where}", $this->indeks($params));
                    $total = (int) (isset($res[0]['c']) ? $res[0]['c'] : 0);
                    $rows = $this->db->exec(
                        "SELECT p.id, p.layanan, p.nomor_po, p.tanggal_po, " . ($adaTglKirim ? 'p.tanggal_dikirim, ' : '') . "p.judul_kegiatan, p.status, p.order_id, p.created_at, p.updated_at, p.nama_perusahaan, p.jenis," . ($adaDaftarOrder ? ' p.daftar_order,' : '') . "
                                a.nomor_order, a.no_id_order, a.total_nilai_kontrak, a.total_bayar_kontrak, s.nomor_surat, c.nmcustomer, c.pt_cv,
                                {$stEf} AS status_efektif,
                                COALESCE(p.updated_at, p.created_at) AS sort_ts
                         {$fromSql}
                         WHERE {$where}
                         ORDER BY COALESCE(p.updated_at, p.created_at) DESC, p.id DESC
                         LIMIT " . max(1, (int) $limit),
                        $this->indeks($params)
                    );
                    $hasil['total'] = $total;
                    $peta = $this->petakanBarisDaftar($rows, 0, $semuaLayanan, $f3);
                    foreach ($peta as $i => $b) {
                        $b['sort_ts'] = (string) $rows[$i]['sort_ts'];
                        $b['status'] = (string) $rows[$i]['status_efektif'];
                        $hasil['rows'][] = $b;
                    }
                }
                if ($idx === 1) { $hasil['peringatan'] = 'Database ' . $dbName . ' tidak terbaca, kolom No. Order & Mitra PO Sertifikasi dikosongkan.'; }
                break;
            } catch (\Throwable $e) {
                error_log('[PO Gabungan] gagal membaca daftar PO layanan: ' . $e->getMessage());
                $hasil['total'] = 0; $hasil['rows'] = [];
            }
        }
        return $hasil;
    }

    /** Tautan notifikasi "order belum dibuatkan PO" (sekaligus penanda baris notifikasinya di opti_notifikasi). */
    const NOTIF_LINK = '/petunjuk-operasional?layanan=sertifikasi&buat=1';

    /**
     * Sinkronkan notifikasi "N order sertifikasi sudah masuk (kontrak terbayar penuh) dan belum dibuatkan PO"
     * ke opti_notifikasi (muncul sebagai bubble + lonceng, sama seperti pemberitahuan lain).
     * Satu baris notifikasi saja (dikenali dari NOTIF_LINK + target_role superadmin):
     *   - belum ada & N > 0       -> dibuat
     *   - N bertambah             -> pesan diperbarui, jadi belum dibaca & naik ke atas lagi
     *   - N berkurang             -> pesan diperbarui saja (status baca tetap)
     *   - N = 0                   -> ditandai sudah dibaca
     * Aman dipanggil dari mana saja (tidak melempar error). Dipanggil di Daftar PO dan tiap render halaman Superadmin.
     * Akses PO layanan sementara hanya Superadmin; saat role Sertifikasi hadir, tinggal tambah target_role-nya di sini.
     */
    public function sinkronNotifOrderBelumPo() {
        $_SESSION['po_notif_sinkron_at'] = time();
        try {
            $this->kendalaSkema(); // pastikan po_kegiatan siap, supaya order yang sudah punya PO tidak ikut terhitung
            $jumlah = count($this->queryOrderLayak(0, true, true));
            $ada = $this->db->exec(
                "SELECT id, pesan, is_read FROM `opti_notifikasi` WHERE link_url = ? AND target_role = 'superadmin' ORDER BY id DESC LIMIT 1",
                [1 => self::NOTIF_LINK]
            );
            $pesan = $jumlah . ' order sertifikasi sudah masuk (kontrak terbayar penuh) dan belum dibuatkan PO.';
            if (empty($ada)) {
                if ($jumlah > 0) {
                    \NotificationService::send($this->db, [
                        'target_role'    => 'superadmin',
                        'target_layanan' => 'semua',
                        'judul'          => 'Order Sertifikasi Belum Dibuatkan PO',
                        'pesan'          => $pesan,
                        'tipe'           => 'warning',
                        'icon'           => 'bi-file-earmark-plus',
                        'link_url'       => self::NOTIF_LINK,
                        'created_by_name'=> 'Sistem OPTI',
                    ]);
                }
                return;
            }
            $baris = $ada[0];
            $lama = preg_match('/^(\d+)\s/', (string) $baris['pesan'], $m) ? (int) $m[1] : 0;
            if ($jumlah === 0) {
                // semua order sudah punya PO: tandai dibaca & reset hitungan (supaya order berikutnya memunculkan lagi)
                if ((int) $baris['is_read'] === 0 || $lama !== 0) {
                    $this->db->exec("UPDATE `opti_notifikasi` SET pesan = ?, is_read = 1 WHERE id = ?", [1 => $pesan, 2 => (int) $baris['id']]);
                }
            } elseif ($jumlah > $lama) {
                $this->db->exec(
                    "UPDATE `opti_notifikasi` SET pesan = ?, is_read = 0, created_at = NOW() WHERE id = ?",
                    [1 => $pesan, 2 => (int) $baris['id']]
                );
            } elseif ($jumlah < $lama) {
                $this->db->exec("UPDATE `opti_notifikasi` SET pesan = ? WHERE id = ?", [1 => $pesan, 2 => (int) $baris['id']]);
            }
        } catch (\Throwable $e) {
            error_log('[PO Index] gagal sinkron notifikasi order belum PO: ' . $e->getMessage());
        }
    }

    /** [a, b, c] -> [1 => a, 2 => b, 3 => c] (format parameter F3 SQL). */
    protected function indeks(array $arr) {
        $out = [];
        $i = 1;
        foreach ($arr as $v) { $out[$i++] = $v; }
        return $out;
    }
}
