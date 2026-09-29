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
protected function divisiDariRequest($f3) {
    $d = strtolower(trim((string) $f3->get('GET.divisi')));
    return in_array($d, ['lingkungan', 'selulosa'], true) ? $d : '';
}
protected function urlDaftarPoDariOrder($orderId) {
    $r = $this->safeQuery('SELECT jenis_layanan_opti FROM order_layanan WHERE id = ?', [1 => (int) $orderId]);
    $d = strtolower((string) ($r[0]['jenis_layanan_opti'] ?? ''));
    return '/po-kegiatan' . (in_array($d, ['lingkungan', 'selulosa'], true) ? '?divisi=' . $d : '');
}
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
protected function bind(PoKegiatan $po, $f3, $isUpdate) {
    $post = $f3->get('POST');

    if (!$isUpdate) {
        $po->order_id = (int) ($post['order_id'] ?? 0);
    }

    $po->nomor_po       = trim((string) ($post['nomor_po'] ?? ''));
    $po->judul_kegiatan = trim((string) ($post['judul_kegiatan'] ?? ''));

    // [BARU] Judul Kegiatan diedit dari form PO -> sinkronkan juga ke
    // order_layanan.judul_kegiatan biar PO dan Order gak beda-beda lagi.
    if ((int) $po->order_id > 0 && $po->judul_kegiatan !== '') {
        $this->db->exec(
            'UPDATE order_layanan SET judul_kegiatan = ? WHERE id = ?',
            [1 => $po->judul_kegiatan, 2 => (int) $po->order_id]
        );
    }

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

        // [BARU] Kategori "tanpa rincian" (mis. Biaya Penunjang Pelayanan Klien): nilainya
        // dihitung langsung dari persen x Nilai Kontrak di hitungRab(), gak perlu isi
        // Kegiatan/Lokasi/Item ataupun items flat sama sekali.
        if (!empty($post['kat_tanpa_rincian'][$catIdx])) {
            $kategoriList[] = [
                'nama'          => trim($namaKategori),
                'persen_manual' => trim((string) ($post['kat_persen_manual'][$catIdx] ?? '')),
                'tanpa_rincian' => true,
            ];
            continue;
        }

        // [BARU] RAB lingkungan: struktur berjenjang Kegiatan -> Lokasi/Kelompok -> Item Biaya,
        // dikirim JS sebagai satu blok JSON per kategori (kat_kegiatan_json[idx]).
        $kegiatanJsonRaw = $post['kat_kegiatan_json'][$catIdx] ?? '';
        $kegiatanDecoded = $kegiatanJsonRaw !== '' ? json_decode($kegiatanJsonRaw, true) : null;

        if (is_array($kegiatanDecoded) && !empty($kegiatanDecoded)) {
            $kategoriList[] = [
                'nama'          => trim($namaKategori),
                'persen_manual' => trim((string) ($post['kat_persen_manual'][$catIdx] ?? '')),
                'kegiatan'      => $kegiatanDecoded,
            ];
            continue;
        }

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

    /** GET /po-kegiatan -- cuma nampilin PO yang SUDAH dibuat */
    /** GET /po-kegiatan -- PO yang sudah dibuat + order yang udah ditunjuk tapi PO-nya belum dibuat */
public function index($f3) {
    $this->requireAuth();
    $divisi = $this->divisiDariRequest($f3);
    $params = $divisi ? [1 => $divisi] : [];

    // [FIX] kondisi divisi sebelumnya dihitung ($where) tapi kelupaan gak
    // ditempelin ke $sql, jadi klik "PO Lingkungan"/"PO Selulosa" di sidebar
    // sama sekali gak nyaring data -- sekarang beneran ditempelin ke WHERE-nya.
    $sql = "SELECT p.id AS po_id, p.nomor_po, p.status AS po_status, p.created_at AS po_created_at,
                   o.id AS order_id, o.nomor_order, o.judul_kegiatan, o.jenis_layanan_opti, o.status_tinjauan,
                   o.ketua_pelaksana_id, o.ketua_pelaksana_at,
                   c.nmcustomer AS nama_mitra, c.pt_cv
            FROM order_layanan o
            JOIN tb_customer c ON o.id_customer = c.id_customer
            LEFT JOIN po_kegiatan p ON p.order_id = o.id
            WHERE o.ketua_pelaksana_id IS NOT NULL"
            . ($divisi ? " AND o.jenis_layanan_opti = ?" : "") . "
            ORDER BY COALESCE(p.created_at, o.ketua_pelaksana_at) DESC";

$daftarPo = $this->safeQuery($sql, $params);


    $totalMenunggu = count(array_filter($daftarPo, function ($r) { return empty($r['po_id']); }));
    $totalDraft    = count(array_filter($daftarPo, function ($r) { return $r['po_status'] === 'draft'; }));
    $totalTerkirim = count(array_filter($daftarPo, function ($r) { return $r['po_status'] === 'terkirim'; }));

    $f3->set('daftar_po', $daftarPo);
    $f3->set('divisi', $divisi);
    $f3->set('total_po', count($daftarPo));
    $f3->set('total_menunggu', $totalMenunggu);
    $f3->set('total_draft', $totalDraft);
    $f3->set('total_terkirim', $totalTerkirim);

    $this->render('katim_kerja/po-kegiatan/index.html', 'Petunjuk Operasional (PO)', $divisi ? 'po_' . $divisi : 'po_kegiatan');
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

        $divisi = $this->divisiDariRequest($f3);

$sqlOrder = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.tanggal_masuk, o.jenis_layanan_opti,
                    c.nmcustomer AS nama_mitra, c.alamatcustomer AS alamat_mitra, c.pt_cv
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer

             WHERE o.ketua_pelaksana_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM po_kegiatan p WHERE p.order_id = o.id)
               AND o.status NOT IN ('batal', 'ditolak', 'selesai')";
$paramsOrder = [];
$i = 1;


if ($divisi) {
    $sqlOrder .= " AND o.jenis_layanan_opti = ?";
    $paramsOrder[$i++] = $divisi;
}
if ($this->getUserRole() === 'tim_kerja') {
    $sqlOrder .= " AND o.ketua_pelaksana_id = ?";
    $paramsOrder[$i++] = (int) $this->getUserId();
}
$sqlOrder .= " ORDER BY o.id DESC";

$daftarOrder = $this->safeQuery($sqlOrder, $paramsOrder);

$f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));
$f3->set('nomor_po_saran', $this->generateNomorPo());
$f3->set('jenis_po_list', $jenisPoList);
$f3->set('daftar_order', $daftarOrder);
$f3->set('divisi', $divisi);

$this->render('katim_kerja/po-kegiatan/pilih_jenis.html', 'Buat Petunjuk Operasional', $divisi ? 'po_' . $divisi : 'po_kegiatan');
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
        $f3->set('surat_masuk_terkait', $this->getSuratMasukTerkait($orderData));
        $f3->set('nomor_po_saran', $this->generateNomorPo());
        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));

        $nomor = $this->hitungNomorSection($orderData['jenis_layanan_opti'] ?? '');
        $f3->set('nomor_alat_bahan', $nomor['alat_bahan']);
        $f3->set('nomor_metoda', $nomor['metoda']);
        $f3->set('nomor_tim', $nomor['tim']);
        $f3->set('nomor_rab', $nomor['rab']);
        $f3->set('nomor_jadwal', $nomor['jadwal']);

        // Audit Tahap 7: PO dibuka/dikelola
        $sessionUser = $f3->get('SESSION.user') ?? [];
        $userId = (int)($sessionUser['id'] ?? ($this->getUserId() ?? 0));
        $userNama = $sessionUser['nama'] ?? ($sessionUser['nama_lengkap'] ?? 'Laboratorium / Tim Pelaksana');
        if ($userId > 0 && $orderId > 0) {
            \StageAudit::recordDibaca($this->db, $orderId, 7, $userId, $userNama, 'Laboratorium / Tim Pelaksana');
        }

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
        $f3->set('surat_masuk_terkait', $orderData ? $this->getSuratMasukTerkait($orderData) : null);
        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));

        $nomor = $this->hitungNomorSection($orderData['jenis_layanan_opti'] ?? '');
        $f3->set('nomor_alat_bahan', $nomor['alat_bahan']);
        $f3->set('nomor_metoda', $nomor['metoda']);
        $f3->set('nomor_tim', $nomor['tim']);
        $f3->set('nomor_rab', $nomor['rab']);
        $f3->set('nomor_jadwal', $nomor['jadwal']);

        // Audit Tahap 7: PO dibuka/diedit
        $sessionUser = $f3->get('SESSION.user') ?? [];
        $userId = (int)($sessionUser['id'] ?? ($this->getUserId() ?? 0));
        $userNama = $sessionUser['nama'] ?? ($sessionUser['nama_lengkap'] ?? 'Laboratorium / Tim Pelaksana');
        $poOrderId = (int)($po->order_id ?? 0);
        if ($userId > 0 && $poOrderId > 0) {
            \StageAudit::recordDibaca($this->db, $poOrderId, 7, $userId, $userNama, 'Laboratorium / Tim Pelaksana');
        }

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

        $f3->reroute($this->urlDaftarPoDariOrder((int) $f3->get('POST.order_id')));
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
            $po->updated_at = date('Y-m-d H:i:s');
            $po->save();

            $this->setFlashSuccess($f3->get('POST.aksi') === 'kirim'
                ? 'PO berhasil diperbarui dan dikirim.'
                : 'PO berhasil diperbarui sebagai draft.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui PO: ' . $e->getMessage());
        }

        $f3->reroute($this->urlDaftarPoDariOrder((int) $f3->get('POST.order_id')));
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
    
        // Audit Tahap 7: PO dilihat di modal preview
        $sessionUser = $f3->get('SESSION.user') ?? [];
        $userId = (int)($sessionUser['id'] ?? ($this->getUserId() ?? 0));
        $userNama = $sessionUser['nama'] ?? ($sessionUser['nama_lengkap'] ?? 'Laboratorium / Tim Pelaksana');
        $poOrderId = (int)($po->order_id ?? 0);
        if ($userId > 0 && $poOrderId > 0) {
            \StageAudit::recordDibaca($this->db, $poOrderId, 7, $userId, $userNama, 'Laboratorium / Tim Pelaksana');
        }

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

    $dompdf->stream($namaFile, ['Attachment' => false]);
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

    // [BARU] Peta nama-kategori -> hasil hitung "Rincian Kebutuhan Lain" (bagian 2. Alat dan
    // Bahan), dipakai item RAB tipe "kebutuhan" (selulosa) yang nyambung ke sini biar biayanya
    // gak usah diketik ulang manual -- cukup pilih kategorinya di form.
    $kebutuhanMap = [];
    foreach ($kebutuhanHitung as $kat) {
        if (($kat['nama'] ?? '') !== '') { $kebutuhanMap[$kat['nama']] = $kat; }
    }

    $rab = json_decode($poData['rab'] ?? '{}', true) ?: [];
    $poData['rab_hitung'] = $this->hitungRab($rab, $kebutuhanMap);

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
        if (!empty($rows[0])) {
            $rows[0]['nama_mitra'] = \Customer::formatNamaPerusahaan($rows[0]['pt_cv'] ?? '', $rows[0]['nama_mitra'] ?? '');
            return $rows[0];
        }
        return null;
    }

    /**
     * [BARU] Ambil data surat masuk yang jadi asal Order ini (kalau ada),
     * buat tombol "Lihat Surat Masuk" + penanda judul di form PO.
     */
    protected function getSuratMasukTerkait($orderData) {
        $idSurat = (int) ($orderData['id_surat_masuk'] ?? 0);
        if ($idSurat <= 0) return null;

        $repo = new \SuratMasukRepository($this->db, $this->dbSekretariat);
        return $repo->getSuratById($idSurat);
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

    protected function hitungRab($rab, $kebutuhanMap = []) {
        $penerimaanItems = $rab['penerimaan']['items'] ?? [];
        $totalPenerimaan = array_sum(array_column($penerimaanItems, 'nominal'));

        $kategoriList = $rab['pengeluaran']['kategori'] ?? [];
        $kategoriHasil = [];
        $totalPengeluaran = 0;

        foreach ($kategoriList as $kat) {
            // [BARU] Kategori "tanpa rincian" (mis. Biaya Penunjang Pelayanan Klien): nilainya
            // langsung persen x Nilai Kontrak (Total Penerimaan), tanpa itemisasi Kegiatan/
            // Lokasi/Item sama sekali -- 'baris' & 'items' sengaja gak diisi biar templatenya
            // gak nampilin rincian apa-apa di bawah baris kategori ini.
            if (!empty($kat['tanpa_rincian'])) {
                $persenManual = trim((string) ($kat['persen_manual'] ?? ''));
                $persenNum = (float) str_replace(',', '.', str_replace('%', '', $persenManual));
                $subtotal = $totalPenerimaan > 0 ? round(($persenNum / 100) * $totalPenerimaan) : 0;
                $kategoriHasil[] = [
                    'nama'          => $kat['nama'] ?? '',
                    'persen_manual' => $persenManual,
                    'tanpa_rincian' => true,
                    'subtotal'      => $subtotal,
                ];
                $totalPengeluaran += $subtotal;
                continue;
            }

            // [BARU] Kategori dengan struktur RAB berjenjang (lingkungan): Kegiatan -> Lokasi/
            // Kelompok -> Item Biaya. Diratakan (flatten) jadi daftar baris ber-depth biar
            // gampang dirender di template tanpa perlu <repeat> rekursif.
            if (!empty($kat['kegiatan'])) {
                $hasilKeg = $this->hitungKegiatanRab($kat['kegiatan'], $kebutuhanMap);
                $kategoriHasil[] = [
                    'nama'          => $kat['nama'] ?? '',
                    'persen_manual' => $kat['persen_manual'] ?? '',
                    'baris'         => $hasilKeg['baris'],
                    'subtotal'      => $hasilKeg['subtotal'],
                    // [BARU] Struktur khusus tampilan preview/cetak RAB selulosa (kegiatan diberi
                    // nomor urut, item tunggal digabung ke baris nomornya, lembur dikelompokkan).
                    'kegiatan_selulosa' => $this->susunKegiatanSelulosa($kat['kegiatan'], $kebutuhanMap),
                ];
                $totalPengeluaran += $hasilKeg['subtotal'];
                continue;
            }

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

        // [BARU] Label huruf besar (A./B./dst) per kategori pengeluaran, dipakai di tampilan
        // RAB selulosa yang formatnya ngikutin RAB fisik (bukan "1. Nama Kategori" kaya lingkungan).
        $hurufKategori = range('A', 'Z');
        foreach ($kategoriHasil as $idxKat => &$kat) {
            $kat['persen_tampil'] = ($kat['persen_manual'] !== '')
                ? $kat['persen_manual']
                : ($totalPenerimaan > 0 ? round($kat['subtotal'] / $totalPenerimaan * 100, 1) . '%' : '0%');
            $kat['label_huruf'] = $hurufKategori[$idxKat] ?? (string) ($idxKat + 1);
        }
        unset($kat);

        // [BARU] Format RAB baru (lingkungan): Nilai Kontrak dipecah ke kategori berpersen,
        // salah satunya (atau lebih) punya rincian Kegiatan->Lokasi->Item. Dipakai buat milih
        // tampilan mana yang dirender di preview/cetak (RAB baru vs A.Penerimaan/B.Pengeluaran lama).
        $isRabBaru = false;
        foreach ($kategoriHasil as $kat) {
            if (!empty($kat['baris']) || !empty($kat['tanpa_rincian'])) { $isRabBaru = true; break; }
        }

        return [
            'penerimaan_items'  => $penerimaanItems,
            'total_penerimaan'  => $totalPenerimaan,
            'kategori'          => $kategoriHasil,
            'total_pengeluaran' => $totalPengeluaran,
            'selisih'           => $totalPenerimaan - $totalPengeluaran,
            'is_rab_baru'       => $isRabBaru,
        ];
    }

    /**
     * [BARU] Ratakan struktur RAB berjenjang (lingkungan) -- Kegiatan -> Lokasi/Kelompok
     * (bisa nested) -> Item Biaya (Lumpsum/Transport/Hotel/dll) -- jadi daftar baris flat
     * bertanda depth & nomor, biar template gak perlu <repeat> rekursif.
     * @return array{baris: array, subtotal: float}
     */
    protected function hitungKegiatanRab($kegiatanList, $kebutuhanMap = []) {
        $baris = [];
        $subtotalTotal = 0;
        $noKeg = 0;

        foreach ($kegiatanList as $keg) {
            $namaKeg = trim($keg['nama'] ?? '');
            if ($namaKeg === '') continue;
            $noKeg++;

            $subtotalKeg = 0;
            $barisKeg = [];
            $this->flattenRabItems($keg['items'] ?? [], 1, $barisKeg, $subtotalKeg, $kebutuhanMap);
            $this->flattenRabGrup($keg['grup'] ?? [], 0, $barisKeg, $subtotalKeg, $kebutuhanMap);

            $baris[] = ['tipe' => 'kegiatan', 'depth' => 0, 'nomor' => $noKeg . '.', 'nama' => $namaKeg, 'biaya' => $subtotalKeg];
            foreach ($barisKeg as $b) { $baris[] = $b; }
            $subtotalTotal += $subtotalKeg;
        }

        return ['baris' => $baris, 'subtotal' => $subtotalTotal];
    }

    /** Grup/Lokasi/Kelompok, bisa bersarang (children) tak terbatas. $depth: 0 = anak langsung Kegiatan. */
    protected function flattenRabGrup($grupList, $depth, &$baris, &$subtotalAcc, $kebutuhanMap = []) {
        $alpha  = range('a', 'z');
        $romawi = ['i','ii','iii','iv','v','vi','vii','viii','ix','x','xi','xii','xiii','xiv','xv'];

        $no = 0;
        foreach ($grupList as $grup) {
            $namaGrup = trim($grup['nama'] ?? '');
            $ptNama   = trim((string) ($grup['disediakan_pt'] ?? ''));
            $no++;

            if ($depth === 0)      { $label = $no . '.'; }
            elseif ($depth === 1)  { $label = ($alpha[$no - 1] ?? $no) . '.'; }
            else                   { $label = ($romawi[$no - 1] ?? $no) . '.'; }

            // [BARU] Lokasi/Kelompok "Disediakan PT X" -- biaya ditanggung partner, jadi Rp 0
            // dan gak usah masuk itemisasi (items/children-nya diabaikan sama sekali).
            if ($ptNama !== '') {
                $baris[] = [
                    'tipe' => 'grup', 'depth' => $depth, 'nomor' => $label, 'nama' => $namaGrup,
                    'biaya' => 0, 'disediakan_pt' => $ptNama,
                ];
                continue;
            }

            $subtotalGrup = 0;
            $barisAnak = [];
            $this->flattenRabItems($grup['items'] ?? [], $depth + 1, $barisAnak, $subtotalGrup, $kebutuhanMap);
            $this->flattenRabGrup($grup['children'] ?? [], $depth + 1, $barisAnak, $subtotalGrup, $kebutuhanMap);

            $baris[] = ['tipe' => 'grup', 'depth' => $depth, 'nomor' => $label, 'nama' => $namaGrup, 'biaya' => $subtotalGrup];
            foreach ($barisAnak as $b) { $baris[] = $b; }
            $subtotalAcc += $subtotalGrup;
        }
    }

    /** Baris biaya (Lumpsum/Transport/Hotel/dll): Kali x (Orang/Kamar) x Hari x Tarif Satuan. */
    protected function flattenRabItems($items, $depth, &$baris, &$subtotalAcc, $kebutuhanMap = []) {
        foreach ($items as $item) {
            // [BARU] Item yang nyambung ke kategori "Rincian Kebutuhan Lain" (bagian 2. Alat
            // dan Bahan) -- biayanya diambil dari subtotal kategori itu, bukan diketik manual,
            // biar jelas asalnya darimana & gak dobel-input (dipakai khusus selulosa). Nama di
            // RAB-nya juga langsung ngikut nama kategorinya, gak ada input "Jenis" terpisah lagi.
            if (($item['tipe_item'] ?? '') === 'kebutuhan') {
                $namaKategori = trim((string) ($item['keb_kategori'] ?? ''));
                if ($namaKategori === '') continue;

                $kebKat  = $kebutuhanMap[$namaKategori] ?? null;
                $biaya   = (float) ($kebKat['subtotal'] ?? 0);
                $barangList = array_map(function ($it) {
                    $jumlah = trim($this->formatAngkaRab((float) ($it['jumlah'] ?? 0)));
                    $satuan = trim((string) ($it['satuan'] ?? ''));
                    return trim($it['nama'] ?? '') . ' (' . $jumlah . ($satuan !== '' ? ' ' . $satuan : '') . ')';
                }, $kebKat['items'] ?? []);
                $rincian = $barangList
                    ? ('Rincian Kebutuhan Lain -- ' . implode(', ', $barangList))
                    : 'Rincian Kebutuhan Lain';

                $baris[] = [
                    'tipe'         => 'item',
                    'depth'        => $depth,
                    'nomor'        => '',
                    'nama'         => $namaKategori,
                    'rincian'      => $rincian,
                    'kali_fmt'     => '',
                    'dim2_fmt'     => '',
                    'dim2_label'   => '',
                    'hari_fmt'     => '',
                    'satuan_biaya' => 0,
                    'biaya'        => $biaya,
                ];
                $subtotalAcc += $biaya;
                continue;
            }

            $jenis = trim($item['jenis'] ?? '');
            if ($jenis === '') continue;

            // [BARU] Item "Lembur" -- rumusnya beda dari item biasa (Kali x Jumlah x Hari x
            // Satuan): Biaya Lembur = Hari x Jam x Tarif per-OJ x %Lembur, ditambah Uang Makan
            // = Hari x Rp/hari. Dipakai buat kasus kayak "RAB KS Chitose" yang gak ngikutin
            // pola qty x harga biasa.
            if (($item['tipe_item'] ?? '') === 'lembur') {
                $hari      = (float) ($item['hari'] ?? 0);
                $jam       = (float) ($item['jam'] ?? 0);
                $oj        = (float) ($item['oj'] ?? 0);
                $persen    = (float) ($item['persen'] ?? 200);
                $uangMakan = (float) ($item['uang_makan'] ?? 0);

                $biayaLembur = $hari * $jam * $oj * ($persen / 100);
                $biayaMakan  = $hari * $uangMakan;
                $biaya       = $biayaLembur + $biayaMakan;

                $rincian = $this->formatAngkaRab($hari) . ' hari x ' . $this->formatAngkaRab($jam) . ' jam x Rp'
                    . number_format($oj, 0, ',', '.') . '/OJ x ' . $this->formatAngkaRab($persen) . '%'
                    . ($uangMakan > 0 ? ' + Rp' . number_format($uangMakan, 0, ',', '.') . '/hari uang makan' : '');

                $baris[] = [
                    'tipe'         => 'item',
                    'depth'        => $depth,
                    'nomor'        => '',
                    'nama'         => $jenis,
                    'rincian'      => $rincian,
                    'kali_fmt'     => '',
                    'dim2_fmt'     => '',
                    'dim2_label'   => '',
                    'hari_fmt'     => $this->formatAngkaRab($hari),
                    'satuan_biaya' => $oj,
                    'biaya'        => $biaya,
                ];
                $subtotalAcc += $biaya;
                continue;
            }

            $kali   = (float) ($item['kali'] ?? 0);
            $dim2   = (($item['dim2'] ?? null) !== null && $item['dim2'] !== '') ? (float) $item['dim2'] : null;
            $hari   = (($item['hari'] ?? null) !== null && $item['hari'] !== '') ? (float) $item['hari'] : null;
            $satuan = (float) ($item['satuan'] ?? 0);
            $biaya  = $kali * ($dim2 ?: 1) * ($hari ?: 1) * $satuan;

            $rincian = [$this->formatAngkaRab($kali) . ' kali'];
            if ($dim2 !== null) { $rincian[] = $this->formatAngkaRab($dim2) . ' ' . trim((string) ($item['dim2_label'] ?? '')); }
            if ($hari !== null) { $rincian[] = $this->formatAngkaRab($hari) . ' hari'; }

            // [BARU] Kolom terpisah (Kali | Jumlah | Hari) biar tabel cetak/preview-nya
            // persis kaya format RAB asli (tiap angka + satuannya kolom sendiri-sendiri),
            // bukan digabung jadi satu teks "rincian".
            $baris[] = [
                'tipe'         => 'item',
                'depth'        => $depth,
                'nomor'        => '',
                'nama'         => $jenis,
                'rincian'      => trim(implode(', ', $rincian)),
                'kali_fmt'     => $this->formatAngkaRab($kali),
                'dim2_fmt'     => $dim2 !== null ? $this->formatAngkaRab($dim2) : '',
                'dim2_label'   => trim((string) ($item['dim2_label'] ?? '')),
                'hari_fmt'     => $hari !== null ? $this->formatAngkaRab($hari) : '',
                'satuan_biaya' => $satuan,
                'biaya'        => $biaya,
            ];
            $subtotalAcc += $biaya;
        }
    }

    /**
     * [BARU] Susun ulang data Kegiatan->Grup->Item RAB selulosa jadi struktur siap-tampil yang
     * ngikutin format RAB fisik (mis. "RAB KS Chitose"): kategori dikasih label huruf besar
     * (A./B./dst -- lihat 'label_huruf' di hitungRab), dan tiap Kegiatan diberi nomor urut
     * (1./2./dst) TANPA baris "Kegiatan" terpisah kaya di preview lingkungan. Kalau Kegiatan
     * cuma punya 1 anak (1 item biasa/kebutuhan, gak ada Lokasi/Grup), rincian qty/satuannya
     * digabung nempel di baris nomor yang sama (kaya "2. Konsumsi rapat  50 pax  Rp60.000").
     * Kalau lebih dari 1 anak, tiap anak ditampilin baris tersendiri di bawahnya pakai tanda "-".
     * Item "Lembur" yang beruntun dikumpulin jadi satu blok mini-tabel Hari|Jam|OJ|%|Uang Makan,
     * persis kaya contoh RAB fisik yang nunjukin beberapa baris lembur di bawah satu judul "Lembur".
     */
    protected function susunKegiatanSelulosa($kegiatanList, $kebutuhanMap = []) {
        $hasil = [];
        $no = 0;

        foreach ($kegiatanList as $keg) {
            $namaKeg = trim($keg['nama'] ?? '');
            if ($namaKeg === '') continue;
            $no++;

            $anak = [];
            $subtotalKeg = 0;
            $this->kumpulkanAnakSelulosa($keg['items'] ?? [], $anak, $subtotalKeg, $kebutuhanMap);
            $this->kumpulkanGrupSelulosa($keg['grup'] ?? [], $anak, $subtotalKeg, $kebutuhanMap);

            if (count($anak) === 1 && in_array($anak[0]['tipe'], ['item', 'kebutuhan'], true)) {
                $hasil[] = [
                    'nomor'        => $no . '.',
                    'nama'         => $namaKeg,
                    'biaya'        => $subtotalKeg,
                    'gabung'       => true,
                    'rincian'      => $anak[0]['rincian'],
                    'satuan_biaya' => $anak[0]['satuan_biaya'],
                ];
            } else {
                $hasil[] = [
                    'nomor'  => $no . '.',
                    'nama'   => $namaKeg,
                    'biaya'  => $subtotalKeg,
                    'gabung' => false,
                    'anak'   => $anak,
                ];
            }
        }

        return $hasil;
    }

    /** Kumpulin item (biasa/lembur/kebutuhan) jadi baris "anak" tampilan selulosa. */
    protected function kumpulkanAnakSelulosa($items, &$anak, &$subtotalAcc, $kebutuhanMap) {
        foreach ($items as $item) {
            if (($item['tipe_item'] ?? '') === 'kebutuhan') {
                $namaKategori = trim((string) ($item['keb_kategori'] ?? ''));
                if ($namaKategori === '') continue;

                $kebKat = $kebutuhanMap[$namaKategori] ?? null;
                $biaya  = (float) ($kebKat['subtotal'] ?? 0);
                $barangList = array_map(function ($it) {
                    $jumlah = trim($this->formatAngkaRab((float) ($it['jumlah'] ?? 0)));
                    $satuan = trim((string) ($it['satuan'] ?? ''));
                    return trim($it['nama'] ?? '') . ' (' . $jumlah . ($satuan !== '' ? ' ' . $satuan : '') . ')';
                }, $kebKat['items'] ?? []);

                $anak[] = [
                    'tipe'         => 'kebutuhan',
                    'nama'         => $namaKategori,
                    'rincian'      => $barangList ? implode(', ', $barangList) : '',
                    'satuan_biaya' => 0,
                    'biaya'        => $biaya,
                ];
                $subtotalAcc += $biaya;
                continue;
            }

            if (($item['tipe_item'] ?? '') === 'lembur') {
                $hari      = (float) ($item['hari'] ?? 0);
                $jam       = (float) ($item['jam'] ?? 0);
                $oj        = (float) ($item['oj'] ?? 0);
                $persen    = (float) ($item['persen'] ?? 200);
                $uangMakan = (float) ($item['uang_makan'] ?? 0);
                $biaya     = ($hari * $jam * $oj * ($persen / 100)) + ($hari * $uangMakan);
                $jenis     = trim($item['jenis'] ?? '');

                $baris = [
                    'nama' => $jenis, 'hari' => $hari, 'jam' => $jam, 'oj' => $oj,
                    'persen' => $persen, 'uang_makan' => $uangMakan, 'biaya' => $biaya,
                ];

                $lastIdx = count($anak) - 1;
                if ($lastIdx >= 0 && $anak[$lastIdx]['tipe'] === 'lembur_group') {
                    $anak[$lastIdx]['items'][] = $baris;
                    $anak[$lastIdx]['subtotal'] += $biaya;
                } else {
                    $anak[] = ['tipe' => 'lembur_group', 'nama' => 'Lembur', 'subtotal' => $biaya, 'items' => [$baris]];
                }
                $subtotalAcc += $biaya;
                continue;
            }

            $jenis = trim($item['jenis'] ?? '');
            if ($jenis === '') continue;

            $kali   = (float) ($item['kali'] ?? 0);
            $dim2   = (($item['dim2'] ?? null) !== null && $item['dim2'] !== '') ? (float) $item['dim2'] : null;
            $hari   = (($item['hari'] ?? null) !== null && $item['hari'] !== '') ? (float) $item['hari'] : null;
            $satuan = (float) ($item['satuan'] ?? 0);
            $biaya  = $kali * ($dim2 ?: 1) * ($hari ?: 1) * $satuan;

            $rincianParts = [$this->formatAngkaRab($kali) . ' kali'];
            if ($dim2 !== null) { $rincianParts[] = $this->formatAngkaRab($dim2) . ' ' . trim((string) ($item['dim2_label'] ?? '')); }
            if ($hari !== null) { $rincianParts[] = $this->formatAngkaRab($hari) . ' hari'; }

            $anak[] = [
                'tipe'         => 'item',
                'nama'         => $jenis,
                'rincian'      => trim(implode(', ', $rincianParts)),
                'satuan_biaya' => $satuan,
                'biaya'        => $biaya,
            ];
            $subtotalAcc += $biaya;
        }
    }

    /** Kumpulin Lokasi/Kelompok (bisa nested) jadi baris "anak" tampilan selulosa. */
    protected function kumpulkanGrupSelulosa($grupList, &$anak, &$subtotalAcc, $kebutuhanMap) {
        foreach ($grupList as $grup) {
            $namaGrup = trim($grup['nama'] ?? '');
            $ptNama   = trim((string) ($grup['disediakan_pt'] ?? ''));

            if ($ptNama !== '') {
                $anak[] = ['tipe' => 'grup', 'nama' => $namaGrup, 'biaya' => 0, 'disediakan_pt' => $ptNama, 'anak' => []];
                continue;
            }

            $anakGrup = [];
            $subtotalGrup = 0;
            $this->kumpulkanAnakSelulosa($grup['items'] ?? [], $anakGrup, $subtotalGrup, $kebutuhanMap);
            $this->kumpulkanGrupSelulosa($grup['children'] ?? [], $anakGrup, $subtotalGrup, $kebutuhanMap);

            $anak[] = ['tipe' => 'grup', 'nama' => $namaGrup, 'biaya' => $subtotalGrup, 'anak' => $anakGrup];
            $subtotalAcc += $subtotalGrup;
        }
    }

    protected function formatAngkaRab($n) {
        if ((float) $n == (int) $n) return (string) (int) $n;
        return rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
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
}