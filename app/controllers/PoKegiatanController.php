<?php

/**
 * PoKegiatanController - alur: Order dengan status_keuangan lunas/terbayar sebagian
 * -> Tim Kerja buat Petunjuk Operasional (PO).
 *
 * [PENYESUAIAN SKEMA order_layanan]
 * Tabel `order_layanan` TIDAK punya kolom nama_mitra/alamat_mitra (itu ada di
 * `tb_customer`, di-JOIN lewat id_customer) dan TIDAK punya kolom tanggal_bayar
 * atau nilai_transportasi terpisah. Berikut pemetaannya:
 *   - nama_mitra      -> tb_customer.nmcustomer
 *   - alamat_mitra    -> tb_customer.alamatcustomer
 *   - tanggal_permintaan -> order_layanan.tanggal_masuk
 *   - tanggal_bayar   -> [ASUMSI] belum ada kolom pastinya di order_layanan,
 *                        sementara saya pakai order_layanan.created_at sebagai
 *                        pengganti. Kalau kamu punya tabel pembayaran (mis.
 *                        opti_pembayaran) dengan kolom tanggal_bayar per order,
 *                        kasih tau strukturnya biar saya ganti query getOrderDenganMitra()
 *                        supaya ambil dari situ.
 *   - nilai_kontrak   -> order_layanan.estimasi_biaya (dianggap sudah termasuk
 *                        semua komponen; tidak ada pemisahan "transportasi" di skema ini)
 *   - nilai_transportasi -> [ASUMSI] di-set 0 karena tidak ada kolom terpisah
 *
 * Filter index(): HANYA order dengan status_keuangan IN ('terbayar_sebagian','lunas').
 */
class PoKegiatanController extends Controller {

    /** GET /po-kegiatan */
    public function index($f3) {
        $this->requireAuth();

        $sql = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.tanggal_masuk,
                       o.estimasi_biaya, o.status_keuangan, o.jenis_layanan_opti,
                       o.created_at AS tanggal_bayar,
                       c.nmcustomer AS nama_mitra, c.pt_cv, c.alamatcustomer AS alamat_mitra,
                       p.id AS po_id, p.nomor_po, p.status AS po_status
                FROM order_layanan o
                INNER JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN po_kegiatan p ON p.order_id = o.id
                WHERE o.status_keuangan IN ('terbayar_sebagian', 'lunas')
                ORDER BY o.created_at DESC, o.id DESC";
        $daftar = $this->safeQuery($sql);

        $f3->set('daftar_order', $daftar);
        $f3->set('total_order', count($daftar));
        $f3->set('total_po_dibuat', count(array_filter($daftar, function ($r) { return !empty($r['po_id']); })));
        $f3->set('total_belum_po', count(array_filter($daftar, function ($r) { return empty($r['po_id']); })));

        $this->render('katim_kerja/po-kegiatan/index.html', 'Petunjuk Operasional (PO)', 'po_kegiatan');
    }

    /** GET /po-kegiatan/buat?order_id=@id */
    public function create($f3) {
        $this->requireAuth();

        $orderId = (int) ($f3->get('GET.order_id') ?? 0);
        $orderData = $this->getOrderDenganMitra($orderId);

        if (!$orderData) {
            $this->setFlashError('Order lunas/terbayar sebagian tidak ditemukan.');
            $f3->reroute('/po-kegiatan');
            return;
        }

        // Kalau sudah pernah dibuatkan PO, arahkan ke edit alih-alih bikin baru
        $existing = new PoKegiatan($this->db);
        $existing->load(['order_id = ?', $orderId]);
        if (!$existing->dry()) {
            $f3->reroute('/po-kegiatan/' . $existing->id . '/edit');
            return;
        }

        $f3->set('order', $orderData);
        $f3->set('po', null);
        $f3->set('nomor_po_saran', $this->generateNomorPo());

        // Default Dasar & Tujuan otomatis dari data order (tetap bisa diedit user)
        // $f3->set('dasar_default',
        //     "a. Permintaan dari {$orderData['nama_mitra']} pada " . date('d F Y', strtotime($orderData['tanggal_permintaan'])) . "\n" .
        //     "b. Bukti bayar dari {$orderData['nama_mitra']} pada " . date('d F Y', strtotime($orderData['tanggal_bayar']))
        // );
        // $f3->set('tujuan_default',
        //     "Membantu menjaga ketelitian dan kebenaran penunjukan peralatan/pengujian milik {$orderData['nama_mitra']}.\n" .
        //     "Alamat: {$orderData['alamat_mitra']}"
        // );
        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));
        // $f3->set('alamat_default', $orderData['alamat_mitra'] ?? '');
        $f3->set('po_json', 'null');

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

        $f3->set('order', $orderData ?: null);
        $poData = $po->cast();
$f3->set('po', $poData);
$f3->set('po_json', json_encode($poData, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));

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

    /**
     * GET /po-kegiatan/@id/preview
     * Dipanggil via fetch() dari index buat isi modal preview (bukan halaman penuh).
     */
 /** GET /po-kegiatan/@id/preview */
public function previewFragment($f3, $params) {
    $po = new PoKegiatan($this->db);
    $po->load(['id = ?', (int) $params['id']]);

    if ($po->dry()) {
        http_response_code(404);
        echo '<div class="text-center text-danger py-5">PO tidak ditemukan.</div>';
        return;
    }

    $orderData = $this->getOrderDenganMitra((int) $po->order_id);
    $poData = $po->cast();

    // Ruang Lingkup: {deskripsi, items} -- kompatibel juga kalau data lama masih array polos
    $rlDecoded = json_decode($poData['ruang_lingkup'] ?? '{}', true) ?: [];
    if (isset($rlDecoded[0])) { $rlDecoded = ['deskripsi' => '', 'items' => $rlDecoded]; } // data lama
    $poData['ruang_lingkup_deskripsi'] = $rlDecoded['deskripsi'] ?? '';
    $poData['ruang_lingkup_items']     = $rlDecoded['items'] ?? [];

    // Tim Pelaksana
    $poData['tim_pelaksana'] = json_decode($poData['tim_pelaksana'] ?? '[]', true) ?: [];

    // RAB
    $rab = json_decode($poData['rab'] ?? '{}', true) ?: [];
    $poData['rab_hitung'] = $this->hitungRab($rab);

    // Jadwal: kolom otomatis dari rentang tanggal + data centang tersimpan
    $kolomInfo = $this->generateJadwalKolom($poData['jadwal_mulai'] ?? null, $poData['jadwal_selesai'] ?? null);
    $poData['jadwal_kolom']        = $kolomInfo['kolom'];
    $poData['jadwal_bulan_header'] = $this->groupBulanHeader($kolomInfo['kolom']);
    $poData['jadwal_items']        = json_decode($poData['jadwal'] ?? '[]', true) ?: [];
    $abDecoded = json_decode($poData['alat_bahan'] ?? '{}', true) ?: [];
$poData['alat_items']  = $abDecoded['alat'] ?? [];
$poData['bahan_items'] = $abDecoded['bahan'] ?? [];
$adaAlatBahan = !empty($poData['alat_items']) || !empty($poData['bahan_items']);

    // Nomor urut section dinamis -- geser kalau Alamat kosong/terisi
    $nomor = 3; // 1. Dasar, 2. Tujuan, 3. Ruang Lingkup (selalu tetap)
    $poData['nomor_alamat'] = null;
    if (!empty($poData['alamat'])) {
        $nomor++;
        $poData['nomor_alamat'] = $nomor;
    }
    $poData['nomor_alat_bahan'] = null;
if ($adaAlatBahan) {
    $nomor++;
    $poData['nomor_alat_bahan'] = $nomor;
}
    $nomor++;
    $poData['nomor_tim'] = $nomor;
    $nomor++;
    $poData['nomor_rab'] = $nomor;
    $nomor++;
    $poData['nomor_jadwal'] = $nomor;

    $f3->set('order', $orderData ?: null);
    $f3->set('po', $poData);

    echo \Template::instance()->render('katim_kerja/po-kegiatan/preview_fragment.html');
}

    /**
     * Ambil satu order_layanan lengkap dengan data mitra (JOIN tb_customer).
     * Dipakai di create()/edit()/previewFragment() -- gantinya OrderLayanan::cast()
     * polos, karena nama_mitra/alamat_mitra bukan kolom langsung di order_layanan.
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

    /** Hitung ulang semua subtotal RAB di server (jangan cuma percaya angka dari JS) */
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
                : ($totalPengeluaran > 0 ? round($kat['subtotal'] / $totalPengeluaran * 100, 1) . '%' : '0%');
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
    $liburNasional = $this->getHariLibur();
    $bulanNama = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    $kolom = [];

    if ($selisihHari <= 31) {
        // MODE HARIAN: cuma hari kerja (Sabtu/Minggu/libur nasional dilewati)
        $mode = 'harian';
        $cursor = clone $mulai;
        while ($cursor <= $selesai) {
            $hariKe = (int) $cursor->format('N'); // 1=Senin ... 7=Minggu
            $tglStr = $cursor->format('Y-m-d');
            if ($hariKe < 6 && !in_array($tglStr, $liburNasional, true)) {
                $kolom[] = [
                    'key'         => $tglStr,
                    'label'       => $cursor->format('j'),
                    'bulan_label' => $bulanNama[(int) $cursor->format('n')] . ' ' . $cursor->format('Y'),
                ];
            }
            $cursor->modify('+1 day');
        }
    } else {
        // MODE MINGGUAN: Minggu ke-1, ke-2, dst
        $mode = 'mingguan';
        $cursor = clone $mulai;
        $mingguKe = 1;
        while ($cursor <= $selesai) {
            $kolom[] = [
                'key'         => 'w' . $mingguKe,
                'label'       => 'Minggu ke-' . $mingguKe,
                'bulan_label' => $bulanNama[(int) $cursor->format('n')] . ' ' . $cursor->format('Y'),
            ];
            $cursor->modify('+7 day');
            $mingguKe++;
        }
    }

    return ['mode' => $mode, 'kolom' => $kolom];
}

/** Gabungin kolom yang bulan_label-nya sama berurutan, buat colspan header */
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

    protected function bind(PoKegiatan $po, $f3, $isUpdate) {
        $post = $f3->get('POST');

        if (!$isUpdate) {
            $po->order_id = (int) ($post['order_id'] ?? 0);
        }

        $po->nomor_po       = trim((string) ($post['nomor_po'] ?? ''));
        $po->judul_kegiatan = trim((string) ($post['judul_kegiatan'] ?? ''));
        $po->dasar          = trim((string) ($post['dasar'] ?? ''));
        $po->tujuan         = trim((string) ($post['tujuan'] ?? ''));

        // Ruang lingkup: narasi (opsional) + tabel rincian alat/kegiatan (opsional)
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
        $po->alamat = trim((string) ($post['alamat'] ?? ''));
        // Alat (list teks biasa) & Bahan Kimia (tabel qty+satuan) -- keduanya opsional
$alatList = [];
foreach (($post['alat_nama'] ?? []) as $nama) {
    if (trim($nama) !== '') $alatList[] = trim($nama);
}

$bahanList = [];
foreach (($post['bahan_nama'] ?? []) as $i => $nama) {
    if (trim($nama) === '') continue;
    $bahanList[] = [
        'nama'   => trim($nama),
        'jumlah' => trim((string) ($post['bahan_jumlah'][$i] ?? '')),
        'satuan' => trim((string) ($post['bahan_satuan'][$i] ?? '')),
    ];
}

$po->alat_bahan = json_encode([
    'alat'  => $alatList,
    'bahan' => $bahanList,
], JSON_UNESCAPED_UNICODE);

        // Tim pelaksana: jumlah orang bebas/dinamis
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

        // RAB
        // Penerimaan: daftar item dinamis (bisa 1, bisa banyak)
$penerimaanItems = [];
foreach (($post['pen_nama'] ?? []) as $i => $nama) {
    if (trim($nama) === '') continue;
    $penerimaanItems[] = [
        'nama'    => trim($nama),
        'nominal' => (float) ($post['pen_nominal'][$i] ?? 0),
    ];
}

// Pengeluaran: daftar KATEGORI dinamis, tiap kategori punya daftar ITEM dinamis
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

        // Jadwal: matrix kegiatan x minggu (checkbox jadwal_minggu[index][] = nomor minggu)
        $po->jadwal_mulai   = $post['jadwal_mulai'] ?: null;
        $po->jadwal_selesai = $post['jadwal_selesai'] ?: null;

        $jadwalKegiatan = [];
        $jadwalNama = $post['jadwal_nama'] ?? [];
        foreach ($jadwalNama as $i => $nama) {
            if (trim($nama) === '') continue;
            $jadwalKegiatan[] = [
                'nama'  => trim($nama),
                'tanda' => $post['jadwal_tanda'][$i] ?? [],
            ];
        }
        $po->jadwal = json_encode($jadwalKegiatan, JSON_UNESCAPED_UNICODE);
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
}