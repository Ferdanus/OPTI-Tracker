<?php

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PoKegiatanController (REVISI BESAR)
 *
 * - PO SEKARANG TIDAK TERGANTUNG status_keuangan sama sekali. Order boleh
 *   dibikinin PO kalau status_tinjauan = 'layak' dan belum pernah punya PO.
 * - index() cuma nampilin PO yang SUDAH dibuat (bukan lagi daftar order
 *   yang "perlu dibuatkan PO").
 * - Alur "Tambah PO" sekarang: /po-kegiatan/pilih (pilih jenis PO -> pilih
 *   order -> form PO langsung muncul di halaman yang sama, submit ke
 *   /po-kegiatan/simpan). Route /po-kegiatan/buat masih ada buat kompatibel
 *   ke belakang tapi UI utama sudah gak ngarah ke situ lagi.
 */
class PoKegiatanController extends Controller {

    /**
 * Bikin poin-poin otomatis buat Dasar (b, c, dst) dari riwayat opti_pembayaran.
 * Kosong kalau belum ada pembayaran tercatat sama sekali.
 */
/**
 * Info pembayaran buat Dasar poin b.
 * Lunas -> "dibayarkan pada tanggal [tanggal pembayaran terakhir]"
 * Belum lunas (termin/kosong) -> "-"
 */
protected function getInfoPembayaranOtomatis($orderId, $statusKeuangan) {
    if ($statusKeuangan !== 'lunas') {
        return '-';
    }

    $bulanIndo = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    $riwayat = $this->safeQuery(
        "SELECT tanggal_bayar FROM opti_pembayaran WHERE order_id = ? AND status_verifikasi = 'terverifikasi' ORDER BY tanggal_bayar DESC LIMIT 1",
        [$orderId]
    );
    if (empty($riwayat)) return '-';

    $t = strtotime($riwayat[0]['tanggal_bayar']);
    $tanggalFormat = (int) date('d', $t) . ' ' . $bulanIndo[(int) date('n', $t)] . ' ' . date('Y', $t);

    return 'dibayarkan pada tanggal ' . $tanggalFormat;
}

protected function parsePerPointLines($text) {
    $lines = [];
    foreach (explode("\n", (string) $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^(\d+\.|[a-zA-Z]\.|[-•])\s*(.*)$/', $line, $m)) {
            $lines[] = ['marker' => $m[1], 'teks' => $m[2]];
        } else {
            $lines[] = ['marker' => '', 'teks' => $line];
        }
    }
    return $lines;
}

protected function hitungNomorSection($divisi) {
    if ($divisi === 'lingkungan') {
        return ['alat_bahan' => 4, 'metoda' => 5, 'tim' => 6, 'rab' => 7, 'jadwal' => 8];
    }
    if ($divisi === 'selulosa') {
        return ['alat_bahan' => 4, 'metoda' => null, 'tim' => 5, 'rab' => 6, 'jadwal' => 7];
    }
    return ['alat_bahan' => null, 'metoda' => null, 'tim' => 4, 'rab' => 5, 'jadwal' => 6];
}

protected function getHariLiburSet() {
    $rows = $this->safeQuery('SELECT tanggal_libur FROM tb_tanggal_libur');
    return array_map(function ($r) { return date('Y-m-d', strtotime($r['tanggal_libur'])); }, $rows);
}

    /** GET /po-kegiatan -- cuma nampilin PO yang SUDAH dibuat */
    public function index($f3) {
        $this->requireAuth();

        $sql = "SELECT p.id AS po_id, p.nomor_po, p.status AS po_status, p.created_at AS po_created_at,
                       o.id AS order_id, o.nomor_order, o.judul_kegiatan, o.jenis_layanan_opti, o.status_tinjauan,
                       c.nmcustomer AS nama_mitra, c.pt_cv
                FROM po_kegiatan p
                INNER JOIN order_layanan o ON o.id = p.order_id
                INNER JOIN tb_customer c ON o.id_customer = c.id_customer
                ORDER BY p.created_at DESC";

        $daftarPo = $this->safeQuery($sql);

        $f3->set('daftar_po', $daftarPo);
        $f3->set('total_po', count($daftarPo));
        $f3->set('total_draft', count(array_filter($daftarPo, function ($r) { return $r['po_status'] === 'draft'; })));
        $f3->set('total_terkirim', count(array_filter($daftarPo, function ($r) { return $r['po_status'] === 'terkirim'; })));

        $this->render('katim_kerja/po-kegiatan/index.html', 'Petunjuk Operasional (PO)', 'po_kegiatan');
    }

    /**
     * GET /po-kegiatan/pilih
     * Halaman pemilihan jenis PO + pilih order + form PO (semua di satu
     * halaman, tanpa reload). Untuk jenis "Optimalisasi Pemanfaatan
     * Teknologi Industri" (OPTI). Jenis lain belum terintegrasi di sini.
     */
    public function pilihJenis($f3) {
        $this->requireAuth();

        $jenisPoList = [
            ['slug' => 'pengujian',           'label' => 'Pengujian',                                   'icon' => 'bi-clipboard2-data'],
            ['slug' => 'kalibrasi',           'label' => 'Kalibrasi',                                   'icon' => 'bi-speedometer2'],
            ['slug' => 'sertifikasi',         'label' => 'Sertifikasi',                                 'icon' => 'bi-patch-check'],
            ['slug' => 'pendampingan_teknis', 'label' => 'Pendampingan Teknis',                         'icon' => 'bi-people'],
            ['slug' => 'konsultansi',         'label' => 'Konsultansi',                                 'icon' => 'bi-chat-square-text'],
            ['slug' => 'opti',                'label' => 'Optimalisasi Pemanfaatan Teknologi Industri', 'icon' => 'bi-gear-wide-connected'],
            ['slug' => 'inspeksi',            'label' => 'Inspeksi',                                    'icon' => 'bi-search'],
            ['slug' => 'uji_profesiensi',     'label' => 'Uji Profesiensi',                             'icon' => 'bi-award'],
        ];

        // Order yang boleh dibikinin PO: status_tinjauan = 'layak' DAN belum pernah punya PO
        $daftarOrder = $this->safeQuery(
            "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.tanggal_masuk, o.jenis_layanan_opti,
                    c.nmcustomer AS nama_mitra, c.alamatcustomer AS alamat_mitra, c.pt_cv
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer
             WHERE o.status_tinjauan = 'layak'
               AND o.id NOT IN (SELECT order_id FROM po_kegiatan)
             ORDER BY o.id DESC"
        );

        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));
        $f3->set('nomor_po_saran', $this->generateNomorPo());
        $f3->set('jenis_po_list', $jenisPoList);
        $f3->set('daftar_order', $daftarOrder);

        $this->render('katim_kerja/po-kegiatan/pilih_jenis.html', 'Buat Petunjuk Operasional', 'po_kegiatan');
    }

    /**
     * GET /po-kegiatan/buat?order_id=@id
     * [Dipertahankan buat kompatibel ke belakang -- UI utama sekarang pakai
     * pilihJenis() di atas, bukan halaman ini lagi.]
     */
    public function create($f3) {
        $this->requireAuth();

        $orderId = (int) ($f3->get('GET.order_id') ?? 0);
        $orderData = $this->getOrderDenganMitra($orderId);

        if (!$orderData) {
            $this->setFlashError('Order tidak ditemukan.');
            $f3->reroute('/po-kegiatan/pilih');
            return;
        }

        if (($orderData['status_tinjauan'] ?? '') !== 'layak') {
            $this->setFlashError('Order ini belum dinyatakan layak (Tinjauan Kelayakan Teknis belum selesai).');
            $f3->reroute('/po-kegiatan/pilih');
            return;
        }

        // Kalau sudah pernah dibuatkan PO, arahkan ke edit alih-alih bikin baru
        $existing = new PoKegiatan($this->db);
        $existing->load(['order_id = ?', $orderId]);
        if (!$existing->dry()) {
            $f3->reroute('/po-kegiatan/' . $existing->id . '/edit');
            return;
        }
        $tahunSekarang = (int) date('Y');
    $daftarTahun = [];
    for ($i = 0; $i < 5; $i++) { $daftarTahun[] = $tahunSekarang - $i; }
    $f3->set('daftar_tahun', $daftarTahun);

    $infoPembayaran = $this->getInfoPembayaranOtomatis($orderId, $orderData['status_keuangan'] ?? '');
$f3->set('info_pembayaran_json', json_encode($infoPembayaran, JSON_UNESCAPED_UNICODE));

        $f3->set('order', $orderData);
        $f3->set('po', null);
        $f3->set('po_json', 'null');
        $f3->set('nomor_po_saran', $this->generateNomorPo());
        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));

        $nomor = $this->hitungNomorSection($orderData['jenis_layanan_opti'] ?? '');
        $f3->set('nomor_alat_bahan', $nomor['alat_bahan']);
        $f3->set('nomor_metoda', $nomor['metoda']);
        $f3->set('nomor_tim', $nomor['tim']);
        $f3->set('nomor_rab', $nomor['rab']);
        $f3->set('nomor_jadwal', $nomor['jadwal']);

        $this->render('katim_kerja/po-kegiatan/form.html', 'Buat Petunjuk Operasional', 'po_kegiatan');
    }

    /** GET /po-kegiatan/@id/edit */
    public function edit($f3, $params) {
        $this->requireAuth();

        $po = new PoKegiatan($this->db);
        $po->load(['id = ?', (int) $params['id']]);

        if ($po->dry()) {
            $this->setFlashError('PO tidak ditemukan.');
            $f3->reroute('/po-kegiatan');
            return;
        }

        $orderData = $this->getOrderDenganMitra((int) $po->order_id);
        $poData = $po->cast();

        $tahunSekarang = (int) date('Y');
    $daftarTahun = [];
    for ($i = 0; $i < 5; $i++) { $daftarTahun[] = $tahunSekarang - $i; }
    $f3->set('daftar_tahun', $daftarTahun);

    $infoPembayaran = $this->getInfoPembayaranOtomatis((int) $po->order_id, $orderData['status_keuangan'] ?? '');
    $f3->set('info_pembayaran_json', json_encode($infoPembayaran, JSON_UNESCAPED_UNICODE));

        $f3->set('order', $orderData ?: null);
        $f3->set('po', $poData);
        $f3->set('po_json', json_encode($poData, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));

        $nomor = $this->hitungNomorSection($orderData['jenis_layanan_opti'] ?? '');
        $f3->set('nomor_alat_bahan', $nomor['alat_bahan']);
        $f3->set('nomor_metoda', $nomor['metoda']);
        $f3->set('nomor_tim', $nomor['tim']);
        $f3->set('nomor_rab', $nomor['rab']);
        $f3->set('nomor_jadwal', $nomor['jadwal']);

        $this->render('katim_kerja/po-kegiatan/form.html', 'Edit Petunjuk Operasional', 'po_kegiatan');
    }

    /** POST /po-kegiatan/simpan */
    public function store($f3) {
        $this->requireAuth();

        try {
            $po = new PoKegiatan($this->db);
            $this->bind($po, $f3, false);
            $po->created_at = date('Y-m-d H:i:s');
            $po->save();

            $this->setFlashSuccess($f3->get('POST.aksi') === 'kirim'
                ? 'PO berhasil dibuat dan dikirim.'
                : 'PO berhasil disimpan sebagai draft.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan PO: ' . $e->getMessage());
        }

        $f3->reroute('/po-kegiatan');
    }

    /** POST /po-kegiatan/@id/update */
    public function update($f3, $params) {
        $this->requireAuth();

        $po = new PoKegiatan($this->db);
        $po->load(['id = ?', (int) $params['id']]);

        if ($po->dry()) {
            $this->setFlashError('PO tidak ditemukan.');
            $f3->reroute('/po-kegiatan');
            return;
        }

        try {
            $this->bind($po, $f3, true);
            $po->save();

            $this->setFlashSuccess($f3->get('POST.aksi') === 'kirim'
                ? 'PO berhasil dikirim.'
                : 'Perubahan PO disimpan sebagai draft.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui PO: ' . $e->getMessage());
        }

        $f3->reroute('/po-kegiatan');
    }

    /** GET /po-kegiatan/@id/preview -- fragment AJAX buat modal preview di index */
    public function previewFragment($f3, $params) {
        $po = new PoKegiatan($this->db);
        $po->load(['id = ?', (int) $params['id']]);
    
        if ($po->dry()) {
            http_response_code(404);
            echo '<div class="text-center text-danger py-5">PO tidak ditemukan.</div>';
            return;
        }
    
        $orderData = $this->getOrderDenganMitra((int) $po->order_id);
        $poData = $this->siapkanDataPo($po, $orderData);
    
        $f3->set('order', $orderData ?: null);
        $f3->set('po', $poData);
    
        echo \Template::instance()->render('katim_kerja/po-kegiatan/preview_fragment.html');
    }

    public function cetak($f3, $params) {
        $po = new PoKegiatan($this->db);
        $po->load(['id = ?', (int) $params['id']]);
    
        if ($po->dry()) {
            $this->setFlashError('PO tidak ditemukan.');
            $f3->reroute('/po-kegiatan');
            return;
        }
    
        $orderData = $this->getOrderDenganMitra((int) $po->order_id);
        $poData = $this->siapkanDataPo($po, $orderData);
    
        $f3->set('order', $orderData ?: null);
        $f3->set('po', $poData);
        $html = \Template::instance()->render('katim_kerja/po-kegiatan/cetak_pdf.html');
    
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'Times-Roman');
        $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $namaFile = 'PO-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $poData['nomor_po'] ?? $po->id) . '.pdf';

    $dompdf->stream($namaFile, ['Attachment' => true]);
    exit;
}

protected function siapkanDataPo($po, $orderData) {
    $poData = $po->cast();

    $rlDecoded = json_decode($poData['ruang_lingkup'] ?? '{}', true) ?: [];
    if (isset($rlDecoded[0])) { $rlDecoded = ['deskripsi' => '', 'items' => $rlDecoded]; }
    $poData['ruang_lingkup_deskripsi'] = $rlDecoded['deskripsi'] ?? '';
    $poData['ruang_lingkup_deskripsi_lines'] = $this->parsePerPointLines($poData['ruang_lingkup_deskripsi']);
    $poData['ruang_lingkup_items'] = $rlDecoded['items'] ?? [];

    $poData['alat_bahan_lines'] = $this->parsePerPointLines($poData['alat_bahan'] ?? '');
    $poData['metoda_lines']     = $this->parsePerPointLines($poData['metoda'] ?? '');
    $poData['bahan_kimia_items'] = json_decode($poData['bahan_kimia'] ?? '[]', true) ?: [];

    $kebutuhanRaw = json_decode($poData['kebutuhan_tambahan'] ?? '[]', true) ?: [];
    $kebutuhanHitung = [];
    foreach ($kebutuhanRaw as $kat) {
        $subtotal = 0; $items = [];
        foreach (($kat['items'] ?? []) as $it) {
            $jumlah = (float) ($it['jumlah'] ?? 0);
            $harga  = (float) ($it['harga'] ?? 0);
            $total  = $jumlah * $harga;
            $subtotal += $total;
            $items[] = array_merge($it, ['total' => $total]);
        }
        $kebutuhanHitung[] = ['nama' => $kat['nama'] ?? '', 'items' => $items, 'subtotal' => $subtotal];
    }

    $poData['tim_pelaksana'] = json_decode($poData['tim_pelaksana'] ?? '[]', true) ?: [];

    $rab = json_decode($poData['rab'] ?? '{}', true) ?: [];
    $poData['rab_hitung'] = $this->hitungRab($rab);

    $kolomInfo = $this->generateJadwalKolom($poData['jadwal_mulai'] ?? null, $poData['jadwal_selesai'] ?? null);
    $poData['jadwal_kolom']        = $kolomInfo['kolom'];
    $poData['jadwal_bulan_header'] = $this->groupBulanHeader($kolomInfo['kolom']);

    $jadwalDecoded = json_decode($poData['jadwal'] ?? '{}', true) ?: [];
    $poData['jadwal_groups']           = $jadwalDecoded['groups'] ?? [];
    $poData['jadwal_keterangan_lines'] = $this->parsePerPointLines($jadwalDecoded['keterangan'] ?? '');
    $divisi = $orderData['jenis_layanan_opti'] ?? '';
    $nomor = 3;

    $poData['nomor_alamat'] = null;
    if (!empty($poData['alamat'])) { $nomor++; $poData['nomor_alamat'] = $nomor; }

    $poData['nomor_alat_bahan'] = null;
    $poData['nomor_metoda'] = null;
    if ($divisi === 'lingkungan') {
        $nomor++; $poData['nomor_alat_bahan'] = $nomor;
        $nomor++; $poData['nomor_metoda'] = $nomor;
    } elseif ($divisi === 'selulosa') {
        $nomor++; $poData['nomor_alat_bahan'] = $nomor;
    }

    $nomor++; $poData['nomor_tim']    = $nomor;
    $nomor++; $poData['nomor_rab']    = $nomor;
    $nomor++; $poData['nomor_jadwal'] = $nomor;
    if ($divisi === 'selulosa') {
        $sub = 1;
        $poData['sub_nomor_alat'] = $sub; $sub++;
        $poData['sub_nomor_bahan_kimia'] = $sub;
        $poData['sub_nomor_metode'] = null;
        if (!empty($poData['metoda'])) { $sub++; $poData['sub_nomor_metode'] = $sub; }
        foreach ($kebutuhanHitung as &$kat) { $sub++; $kat['sub_nomor'] = $sub; }
        unset($kat);
    }
    $poData['kebutuhan_tambahan_hitung'] = $kebutuhanHitung;

    return $poData;
}

    /** GET /po-kegiatan/jadwal-kolom?mulai=YYYY-MM-DD&selesai=YYYY-MM-DD */
    public function jadwalKolom($f3) {
        $mulai = (string) ($f3->get('GET.mulai') ?? '');
        $selesai = (string) ($f3->get('GET.selesai') ?? '');

        $hasil = $this->generateJadwalKolom($mulai, $selesai);
        $hasil['bulan_header'] = $this->groupBulanHeader($hasil['kolom']);

        header('Content-Type: application/json');
        echo json_encode($hasil);
    }

    /**
     * Ambil satu order_layanan lengkap dengan data mitra (JOIN tb_customer).
     * o.* otomatis kebawa status_tinjauan, jadi gak perlu tambah kolom lagi.
     */
    protected function getOrderDenganMitra($orderId) {
        if ($orderId <= 0) return null;

        $sql = "SELECT o.*,
                       c.nmcustomer AS nama_mitra, c.pt_cv, c.alamatcustomer AS alamat_mitra,
                       o.tanggal_masuk AS tanggal_permintaan,
                       o.created_at AS tanggal_bayar,
                       o.estimasi_biaya AS nilai_kontrak,
                       0 AS nilai_transportasi
                FROM order_layanan o
                INNER JOIN tb_customer c ON o.id_customer = c.id_customer
                WHERE o.id = ?
                LIMIT 1";

        $rows = $this->safeQuery($sql, [1 => $orderId]);
        return $rows[0] ?? null;
    }

    /** [PERLU DIVERIFIKASI ULANG tiap tahun sesuai SKB 3 Menteri] */
    protected function getHariLibur() {
        return [
            '2026-01-01', '2026-02-17', '2026-03-19', '2026-03-20', '2026-03-21',
            '2026-04-03', '2026-05-01', '2026-05-14', '2026-05-27', '2026-05-31',
            '2026-06-01', '2026-06-17', '2026-08-17', '2026-08-26', '2026-12-25',
        ];
    }

    protected function generateJadwalKolom($mulaiStr, $selesaiStr) {
        if (empty($mulaiStr) || empty($selesaiStr)) return ['mode' => null, 'kolom' => []];
    
        $mulai = new \DateTime($mulaiStr);
        $selesai = new \DateTime($selesaiStr);
        if ($selesai < $mulai) return ['mode' => null, 'kolom' => []];
    
        $selisihHari = $mulai->diff($selesai)->days + 1;
        $liburSet = $this->getHariLiburSet();
        $bulanNama = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    
        $kolom = [];
    
        if ($selisihHari <= 31) {
            $mode = 'harian';
            $cursor = clone $mulai;
            while ($cursor <= $selesai) {
                $hariKe = (int) $cursor->format('N');
                $tglStr = $cursor->format('Y-m-d');
                $isLibur = ($hariKe >= 6) || in_array($tglStr, $liburSet, true);
                $kolom[] = [
                    'key'         => $tglStr,
                    'label'       => $cursor->format('j'),
                    'bulan_label' => $bulanNama[(int) $cursor->format('n')] . ' ' . $cursor->format('Y'),
                    'libur'       => $isLibur,
                ];
                $cursor->modify('+1 day');
            }
        } else {
            $mode = 'bulanan';
            $cursor = clone $mulai;
            while ($cursor <= $selesai) {
                $tahun = (int) $cursor->format('Y');
                $bulan = (int) $cursor->format('n');
                $mingguKe = (int) floor(((int) $cursor->format('j') - 1) / 7) + 1;
                $key = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-w' . $mingguKe;
    
                $sudahAda = false;
                foreach ($kolom as $k) { if ($k['key'] === $key) { $sudahAda = true; break; } }
                if (!$sudahAda) {
                    $kolom[] = ['key' => $key, 'label' => (string) $mingguKe, 'bulan_label' => $bulanNama[$bulan] . ' ' . $tahun, 'libur' => false];
                }
                $cursor->modify('+1 day');
            }
        }
    
        return ['mode' => $mode, 'kolom' => $kolom];
    }

    protected function groupBulanHeader($kolom) {
        $groups = [];
        foreach ($kolom as $k) {
            $lastIdx = count($groups) - 1;
            if ($lastIdx >= 0 && $groups[$lastIdx]['label'] === $k['bulan_label']) {
                $groups[$lastIdx]['span']++;
            } else {
                $groups[] = ['label' => $k['bulan_label'], 'span' => 1];
            }
        }
        return $groups;
    }

    protected function hitungRab($rab) {
        $penerimaanItems = $rab['penerimaan']['items'] ?? [];
        $totalPenerimaan = array_sum(array_column($penerimaanItems, 'nominal'));

        $kategoriList = $rab['pengeluaran']['kategori'] ?? [];
        $kategoriHasil = [];
        $totalPengeluaran = 0;

        foreach ($kategoriList as $kat) {
            $subtotal = 0;
            $itemHasil = [];
            foreach (($kat['items'] ?? []) as $item) {
                $qtyA   = (float) ($item['qty_a'] ?? 1) ?: 1;
                $qtyB   = (float) ($item['qty_b'] ?? 1) ?: 1;
                $tarif  = (float) ($item['tarif'] ?? 0);
                $nominal = $qtyA * $qtyB * $tarif;
                $subtotal += $nominal;
                $itemHasil[] = array_merge($item, ['nominal' => $nominal]);
            }
            $kategoriHasil[] = [
                'nama'          => $kat['nama'] ?? '',
                'persen_manual' => $kat['persen_manual'] ?? '',
                'items'         => $itemHasil,
                'subtotal'      => $subtotal,
            ];
            $totalPengeluaran += $subtotal;
        }

        foreach ($kategoriHasil as &$kat) {
            $kat['persen_tampil'] = ($kat['persen_manual'] !== '')
                ? $kat['persen_manual']
                : ($totalPenerimaan > 0 ? round($kat['subtotal'] / $totalPenerimaan * 100, 1) . '%' : '0%');
        }
        unset($kat);

        return [
            'penerimaan_items'  => $penerimaanItems,
            'total_penerimaan'  => $totalPenerimaan,
            'kategori'          => $kategoriHasil,
            'total_pengeluaran' => $totalPengeluaran,
            'selisih'           => $totalPenerimaan - $totalPengeluaran,
        ];
    }

    protected function generateNomorPo() {
        return rand(100, 999) . '/PO/BBSPJIS/' . date('m') . '/' . date('Y');
    }

    protected function safeQuery($sql, $params = []) {
        try {
            return $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function bind(PoKegiatan $po, $f3, $isUpdate) {
        $post = $f3->get('POST');

        if (!$isUpdate) {
            $po->order_id = (int) ($post['order_id'] ?? 0);
        }

        $po->nomor_po       = trim((string) ($post['nomor_po'] ?? ''));
        $po->judul_kegiatan = trim((string) ($post['judul_kegiatan'] ?? ''));
        $po->dasar          = trim((string) ($post['dasar'] ?? ''));
        $po->dasar_struktur = json_encode([
            'nomor_urut'   => trim((string) ($post['dasar_nomor_urut'] ?? '')),
            'bulan_romawi' => trim((string) ($post['dasar_bulan_romawi'] ?? '')),
            'tahun'        => trim((string) ($post['dasar_tahun'] ?? '')),
            'tanggal'      => trim((string) ($post['dasar_tanggal'] ?? '')),
            'tentang'      => trim((string) ($post['dasar_tentang'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);
        $po->tujuan         = trim((string) ($post['tujuan'] ?? ''));

        $ruangLingkupItems = [];
        $rlNama = $post['rl_nama'] ?? [];
        foreach ($rlNama as $i => $nama) {
            if (trim($nama) === '') continue;
            $ruangLingkupItems[] = [
                'nama_alat'   => trim($nama),
                'jumlah'      => (int) ($post['rl_jumlah'][$i] ?? 0),
                'waktu_menit' => (int) ($post['rl_waktu_menit'][$i] ?? 0),
                'total_jam'   => (float) ($post['rl_total_jam'][$i] ?? 0),
                'keterangan'  => trim((string) ($post['rl_keterangan'][$i] ?? '')),
            ];
        }
        $po->ruang_lingkup = json_encode([
            'deskripsi' => trim((string) ($post['ruang_lingkup_deskripsi'] ?? '')),
            'items'     => $ruangLingkupItems,
        ], JSON_UNESCAPED_UNICODE);

        $po->alat_bahan = trim((string) ($post['alat_bahan'] ?? ''));
        $po->metoda     = trim((string) ($post['metoda'] ?? ''));
        // 4.2 Bahan dan Bahan Kimia (khusus Selulosa) -- Nama/Jumlah/Satuan, tanpa harga
        $bahanKimiaList = [];
        $bkNama = $post['bahan_kimia_nama'] ?? [];
        foreach ($bkNama as $i => $nama) {
            if (trim($nama) === '') continue;
            $bahanKimiaList[] = [
                'nama'   => trim($nama),
                'jumlah' => trim((string) ($post['bahan_kimia_jumlah'][$i] ?? '')),
                'satuan' => trim((string) ($post['bahan_kimia_satuan'][$i] ?? '')),
            ];
        }
        $po->bahan_kimia = json_encode($bahanKimiaList, JSON_UNESCAPED_UNICODE);

        // Rincian Kebutuhan Lain -- opsional & dinamis (bisa banyak kategori: ATK, Computer Supply, dll)
        $kebutuhanList = [];
        $kebNama = $post['keb_nama'] ?? [];
        foreach ($kebNama as $katIdx => $namaKategori) {
            if (trim($namaKategori) === '') continue;
            $items = [];
            $itemNamaList = $post['keb_item_nama'][$katIdx] ?? [];
            foreach ($itemNamaList as $i => $namaItem) {
                if (trim($namaItem) === '') continue;
                $items[] = [
                    'nama'   => trim($namaItem),
                    'jumlah' => (float) ($post['keb_item_jumlah'][$katIdx][$i] ?? 0),
                    'satuan' => trim((string) ($post['keb_item_satuan'][$katIdx][$i] ?? '')),
                    'harga'  => (float) ($post['keb_item_harga'][$katIdx][$i] ?? 0),
                ];
            }
            if (empty($items)) continue;
            $kebutuhanList[] = ['nama' => trim($namaKategori), 'items' => $items];
        }
        $po->kebutuhan_tambahan = json_encode($kebutuhanList, JSON_UNESCAPED_UNICODE);

        $po->alamat = trim((string) ($post['alamat'] ?? ''));
        $timPelaksana = [];
        $tpNama = $post['tp_nama'] ?? [];
        foreach ($tpNama as $i => $nama) {
            if (trim($nama) === '') continue;
            $timPelaksana[] = [
                'nama'         => trim($nama),
                'tugas'        => trim((string) ($post['tp_tugas'][$i] ?? '')),
                'uraian_tugas' => trim((string) ($post['tp_uraian'][$i] ?? '')),
            ];
        }
        $po->tim_pelaksana = json_encode($timPelaksana, JSON_UNESCAPED_UNICODE);

        $penerimaanItems = [];
        foreach (($post['pen_nama'] ?? []) as $i => $nama) {
            if (trim($nama) === '') continue;
            $penerimaanItems[] = ['nama' => trim($nama), 'nominal' => (float) ($post['pen_nominal'][$i] ?? 0)];
        }

        $kategoriList = [];
        $katNama = $post['kat_nama'] ?? [];
        foreach ($katNama as $catIdx => $namaKategori) {
            if (trim($namaKategori) === '') continue;
            $items = [];
            $itemNamaList = $post['kat_item_nama'][$catIdx] ?? [];
            foreach ($itemNamaList as $i => $namaItem) {
                if (trim($namaItem) === '') continue;
                $items[] = [
                    'nama'    => trim($namaItem),
                    'qty_a'   => (float) ($post['kat_item_qtya'][$catIdx][$i] ?? 1) ?: 1,
                    'label_a' => trim((string) ($post['kat_item_labela'][$catIdx][$i] ?? '')),
                    'qty_b'   => (float) ($post['kat_item_qtyb'][$catIdx][$i] ?? 1) ?: 1,
                    'label_b' => trim((string) ($post['kat_item_labelb'][$catIdx][$i] ?? '')),
                    'tarif'   => (float) ($post['kat_item_tarif'][$catIdx][$i] ?? 0),
                ];
            }
            $kategoriList[] = [
                'nama'          => trim($namaKategori),
                'persen_manual' => trim((string) ($post['kat_persen_manual'][$catIdx] ?? '')),
                'items'         => $items,
            ];
        }
        $po->rab = json_encode([
            'penerimaan'  => ['items' => $penerimaanItems],
            'pengeluaran' => ['kategori' => $kategoriList],
        ], JSON_UNESCAPED_UNICODE);

        $po->jadwal_mulai   = $post['jadwal_mulai'] ?: null;
$po->jadwal_selesai = $post['jadwal_selesai'] ?: null;

$tahapNamaList    = $post['tahap_nama'] ?? [];
$kegiatanNamaAll  = $post['jadwal_kegiatan_nama'] ?? [];
$kegiatanTandaAll = $post['jadwal_kegiatan_tanda'] ?? [];

$groups = [];
foreach ($tahapNamaList as $gIdx => $namaTahap) {
    $kegiatanList = [];
    $namaList = $kegiatanNamaAll[$gIdx] ?? [];
    foreach ($namaList as $kIdx => $namaKeg) {
        if (trim($namaKeg) === '') continue;
        $kegiatanList[] = [
            'nama'  => trim($namaKeg),
            'tanda' => array_map('strval', $kegiatanTandaAll[$gIdx][$kIdx] ?? []),
        ];
    }
    $groups[] = ['nama_tahap' => trim($namaTahap), 'kegiatan' => $kegiatanList];
}

$po->jadwal = json_encode([
    'groups'     => $groups,
    'keterangan' => trim((string) ($post['jadwal_keterangan'] ?? '')),
], JSON_UNESCAPED_UNICODE);

        $po->status = ($post['aksi'] ?? '') === 'kirim' ? 'terkirim' : 'draft';
    }
}