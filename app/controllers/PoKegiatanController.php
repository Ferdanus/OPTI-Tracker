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
 * - Alur "Buat PO" untuk OPTI: tombol "Buat PO" di baris order langsung ke
 *   /po-kegiatan/buat?order_id=... (form PO), tombol "Tambah PO" di atas ke
 *   /po-kegiatan/pilih?jenis=opti (langsung pilih order, tanpa pilih jenis).
 *   Halaman /po-kegiatan/pilih tanpa ?jenis=opti tetap menampilkan pilihan
 *   semua jenis PO.
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
/**
 * [BARU] Divisi yang "dikunci" buat role non-superadmin -- tim_kerja &
 * ketua_tim gak boleh ganti-ganti divisi sendiri lewat filter, otomatis
 * kepake sesuai divisi mereka masing-masing. Superadmin gak kena ini
 * (bebas pilih lewat ?divisi=). Return '' kalau superadmin, atau kalau
 * divisi user gak kedetect (misal Ketua Tim OPTI umum yang gak terikat
 * satu divisi tertentu).
 */
protected function divisiPenggunaSaatIni() {
    $role = $this->getUserRole();
    if ($role === 'tim_kerja') {
        return $this->getDivisiTimKerja();
    }
    if ($role === 'ketua_tim') {
        $lay = strtolower((string) $this->getUserLayanan());
        return in_array($lay, ['lingkungan', 'selulosa'], true) ? $lay : '';
    }
    return '';
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
    // Satu item = satu poin berpenanda (a. / 1. / - / •). Baris tanpa penanda yang menyusul sebuah poin
    // dianggap lanjutan poin itu (digabung dengan baris baru, isinya tidak diubah) supaya tampil rapi
    // dengan indentasi menggantung. Baris tanpa penanda sebelum poin pertama tetap berdiri sendiri.
    $lines = [];
    $akhir = -1; // indeks poin berpenanda terakhir (-1 = belum ada)
    foreach (explode("\n", (string) $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^(\d+\.|[a-zA-Z]\.|[-•])\s*(.*)$/', $line, $m)) {
            $lines[] = ['marker' => $m[1], 'teks' => $m[2]];
            $akhir = count($lines) - 1;
        } elseif ($akhir >= 0) {
            $lines[$akhir]['teks'] .= "\n" . $line;
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

/**
 * Ambil daftar Tim Pelaksana aktif dari Data Master (per divisi: lingkungan/selulosa)
 * buat ngisi dropdown "Nama" di bagian Tim Pelaksana form PO -- begitu nama dipilih,
 * Tugas/Tanggung Jawab otomatis keisi dari data master ini (lihat form.html).
 * Return [] kalau divisi-nya gak dikenali atau controller master-nya gak ada.
 */
protected function ambilMasterPelaksanaUntukPo($divisi) {
    $d = strtolower(trim((string) $divisi));
    if (!in_array($d, ['lingkungan', 'selulosa'], true) || !class_exists('MasterTimPelaksanaController')) {
        return [];
    }
    return \MasterTimPelaksanaController::ambilAktif($this->db, $d);
}

/** Nama jabatan aktif dari Master Jabatan Pelaksana (pilihan kolom jabatan di Tim Pelaksana form PO). */
protected function ambilJabatanPelaksanaUntukPo() {
    return class_exists('MasterTimPelaksanaController') ? \MasterTimPelaksanaController::ambilJabatanAktif($this->db) : [];
}

protected function getHariLiburSet() {
    $rows = $this->safeQuery('SELECT tanggal_libur FROM tb_tanggal_libur');
    return array_map(function ($r) { return date('Y-m-d', strtotime($r['tanggal_libur'])); }, $rows);
}
/**
 * [BARU] Nomor + tanggal Surat Persetujuan Biaya, diambil dari tb_surat_penawaran
 * berdasarkan order_id (surat penawaran terakhir untuk order itu, belum perlu 'deal').
 * Return null kalau order belum punya surat penawaran -> form jatuh ke input manual.
 */
protected function suratDasarDariOrder($orderId) {
    $orderId = (int) $orderId;
    if ($orderId <= 0) { return null; }
    $r = $this->safeQuery(
        "SELECT nomor_surat, tanggal_surat FROM tb_surat_penawaran
          WHERE order_id = ? AND nomor_surat IS NOT NULL AND nomor_surat <> ''
          ORDER BY id DESC LIMIT 1",
        [1 => $orderId]
    );
    if (empty($r[0])) { return null; }
    $nomor = trim((string) $r[0]['nomor_surat']);
    $out = [
        'nomor'        => $nomor,
        'tanggal'      => substr((string) $r[0]['tanggal_surat'], 0, 10),
        'nomor_urut'   => '',
        'bulan_romawi' => '',
        'tahun'        => '',
    ];
    // pecah best-effort kalau formatnya B/1574/BBSPJIS/MS/X/2026 (spasi diabaikan)
    if (preg_match('#^B\s*/\s*(\d+)\s*/\s*BBSPJIS\s*/\s*MS\s*/\s*([IVX]+)\s*/\s*(\d{4})$#i', $nomor, $m)) {
        $out['nomor_urut'] = $m[1]; $out['bulan_romawi'] = strtoupper($m[2]); $out['tahun'] = $m[3];
    }
    return $out;
}
/**
 * [BARU] Jadwal PO otomatis: mulai = tanggal fisik sampel masuk, selesai = tanggal_deadline_spm.
 * Return null kalau salah satu belum terisi -> form jatuh ke input manual.
 */
protected function jadwalOtomatisDariOrder($orderData) {
    if (!$orderData) { return null; }
    // GANTI nama kolom di sini kalau di tabel kamu beda
    $mulai   = substr((string) ($orderData['tanggal_terima_sampel'] ?? ''), 0, 10);
    $selesai = substr((string) ($orderData['tanggal_deadline_spm'] ?? ''), 0, 10);
    $kosong  = ['', '0000-00-00'];
    if (in_array($mulai, $kosong, true) || in_array($selesai, $kosong, true)) { return null; }
    if ($selesai < $mulai) { return null; }
    return ['mulai' => $mulai, 'selesai' => $selesai];
}


/**
 * Mode balasan JSON untuk dialog "Generate Nomor PO" (permintaan AJAX ke simpan/update): setiap $f3->reroute()
 * dibalas JSON {ok, message, nomor, redirect} alih-alih redirect. Pesan flash dikonsumsi supaya tidak muncul dua kali.
 * ("Simpan dan kirim" revisi punya jalur JSON sendiri lewat kirim_setelah=1 -- tidak ikut mode ini.)
 */
protected $jsonMode = false;
protected $berhasilAjax = false;
protected $nomorAjax = '';

protected function siapkanBalasanJson($f3) {
    $xr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $ac = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if ($xr !== 'xmlhttprequest' || stripos($ac, 'application/json') === false) { return; }
    $this->jsonMode = true;
    unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
    $self = $this;
    $f3->set('ONREROUTE', function ($url) use ($self, $f3) {
        $err = (string) ($_SESSION['flash_error'] ?? '');
        $ok = (string) ($_SESSION['flash_success'] ?? '');
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
        if (is_string($url) && $url !== '' && $url[0] === '/') { $url = $f3->get('BASE') . $url; }
        $berhasil = $self->berhasilAjax;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'       => $berhasil,
            'message'  => $berhasil ? $ok : ($err !== '' ? $err : 'Sesi berakhir atau akses ditolak. Muat ulang halaman lalu coba lagi.'),
            'nomor'    => $berhasil ? (string) $self->nomorAjax : '',
            'redirect' => (string) $url,
        ], JSON_UNESCAPED_UNICODE);
        return true;
    });
}

/** Tambah ?tab= (draft / terkirim) ke alamat Daftar PO supaya PO yang baru disimpan langsung terlihat. */
protected function denganTab($url, $tab) {
    return $url . (strpos($url, '?') !== false ? '&' : '?') . 'tab=' . $tab;
}

/** id baris po_nomor_urut yang baru diterbitkan oleh bind() -- dilepas lagi kalau penyimpanan PO gagal (nomor tidak boleh bolong). */
protected $nomorBaruId = 0;

protected function lepasNomorBaru() {
    if ($this->nomorBaruId > 0) {
        try { $this->db->exec('DELETE FROM po_nomor_urut WHERE id = ?', [1 => $this->nomorBaruId]); } catch (\Throwable $e) {}
        $this->nomorBaruId = 0;
    }
}

protected function bind(PoKegiatan $po, $f3, $isUpdate, $isLivePreview = false) {

    $post = $f3->get('POST');

    if (!$isUpdate) {
        $po->order_id = (int) ($post['order_id'] ?? 0);
    }

    // [BARU] Penandatangan (Menyetujui / Pelaksana Kegiatan / Mengetahui) dipilih dari master penandatangan, sama
    // dengan Surat Penawaran. Yang disimpan salinan nama, NIP, dan jabatannya (PoTtd), supaya PO yang sudah terkirim
    // tidak ikut berubah kalau pejabat di master berganti. PO tidak boleh dikirim sebelum ketiganya dipilih.
    $ttd = \PoTtd::dariPost(is_array($post) ? $post : [], \PoTtd::KOTA_BAWAAN);
    if (!$isLivePreview && ($post['aksi'] ?? '') === 'kirim') {
        $kurangTtd = \PoTtd::kekurangan($ttd);
        if ($kurangTtd) {
            throw new \Exception(implode('; ', $kurangTtd));
        }
    }
    $po->ttd_json = json_encode($ttd, JSON_UNESCAPED_UNICODE);

    if (!$isLivePreview) {
        $this->ensureSchemaNomorPo();

        // [BARU] Nomor PO TIDAK lagi dibuat saat draft pertama disimpan, tapi baru saat PO DIKIRIM
        // (aksi = kirim). Bulan-romawi & tahun di nomor, juga Tanggal PO yang tercetak di dokumen,
        // mengikuti TANGGAL KIRIM (WIB). Tanggal pembuatan = created_at (draft pertama disimpan).
        // Nomor diambil dari tabel master po_nomor_urut (satu deret per tahun, bareng Sertifikasi &
        // Pengujian); po_kegiatan menyimpan id_nomor_urut + salinan nomor_po. PO yang sudah punya nomor
        // (dikirim sebelumnya, atau draft lama) TIDAK diubah lagi: nomor & tanggalnya dipertahankan,
        // termasuk saat direvisi dan dikirim ulang.
        $hari = \PoSchema::hariIniWib();
        if (empty($po->nomor_po)) {
            if (($post['aksi'] ?? '') === 'kirim') {
                $nomorBaru         = $this->generateNomorPoBaru($hari);
                $po->id_nomor_urut = $nomorBaru['id'];
                $po->nomor_po      = $nomorBaru['detail_nomor'];
                $this->nomorBaruId = (int) $nomorBaru['id'];
            }
            $po->tanggal_po = $hari;   // draft: sementara (tanggal resmi = tanggal kirim)
        } elseif (empty($po->tanggal_po) || strpos((string) $po->tanggal_po, '0000') === 0) {
            $po->tanggal_po = $hari;
        }
    } else {
        // pratinjau live: tanpa menyimpan & tanpa memakai nomor. PO yang sudah bernomor memakai nomor/tanggalnya.
        if (empty($po->nomor_po) || empty($po->tanggal_po) || strpos((string) $po->tanggal_po, '0000') === 0) {
            $po->tanggal_po = \PoSchema::hariIniWib();
        }
    }

    $po->judul_kegiatan = trim((string) ($post['judul_kegiatan'] ?? ''));

    // [BARU] Judul Kegiatan diedit dari form PO -> sinkronkan juga ke
    // order_layanan.judul_kegiatan biar PO dan Order gak beda-beda lagi.
    if (!$isLivePreview && (int) $po->order_id > 0 && $po->judul_kegiatan !== '') {
        $this->db->exec(
            'UPDATE order_layanan SET judul_kegiatan = ? WHERE id = ?',
            [1 => $po->judul_kegiatan, 2 => (int) $po->order_id]
        );
    }

    // [BARU] Nomor & tanggal surat persetujuan biaya TIDAK dipercaya dari form: kalau order
    // punya surat penawaran, nilainya ditimpa dari tb_surat_penawaran (anti utak-atik inspect element).
    $suratDasar = $this->suratDasarDariOrder((int) $po->order_id);
    if ($suratDasar) {
        $post['dasar_nomor_urut']   = $suratDasar['nomor_urut'];
        $post['dasar_bulan_romawi'] = $suratDasar['bulan_romawi'];
        $post['dasar_tahun']        = $suratDasar['tahun'];
        $post['dasar_tanggal']      = $suratDasar['tanggal'];
    }

    $po->dasar          = trim((string) ($post['dasar'] ?? ''));
    $po->dasar_struktur = json_encode([
        'nomor_urut'   => trim((string) ($post['dasar_nomor_urut'] ?? '')),
        'bulan_romawi' => trim((string) ($post['dasar_bulan_romawi'] ?? '')),
        'tahun'        => trim((string) ($post['dasar_tahun'] ?? '')),
        'tanggal'      => trim((string) ($post['dasar_tanggal'] ?? '')),
        'tentang'      => trim((string) ($post['dasar_tentang'] ?? '')),
        'nomor_surat'  => $suratDasar ? $suratDasar['nomor'] : '',
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

    $jadwalAuto = $this->jadwalOtomatisDariOrder($this->getOrderDenganMitra((int) $po->order_id));
    $po->jadwal_mulai   = $jadwalAuto ? $jadwalAuto['mulai']   : ($post['jadwal_mulai'] ?: null);
    $po->jadwal_selesai = $jadwalAuto ? $jadwalAuto['selesai'] : ($post['jadwal_selesai'] ?: null);

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

// Pratinjau live / cetak live mengirim jadwal sebagai satu string JSON (jadwal_groups), bukan sebagai field tahap_nama[] dst.
// Tanpa pembacaan ini tabel jadwal di pratinjau kosong (cuma judul "JADWAL" yang muncul).
if (empty($groups) && isset($post['jadwal_groups']) && is_string($post['jadwal_groups']) && $post['jadwal_groups'] !== '') {
    $jg = json_decode($post['jadwal_groups'], true);
    if (is_array($jg)) {
        foreach ($jg as $g) {
            if (!is_array($g)) continue;
            $kegiatanList = [];
            foreach (($g['kegiatan'] ?? []) as $keg) {
                if (!is_array($keg) || trim((string) ($keg['nama'] ?? '')) === '') continue;
                $kegiatanList[] = [
                    'nama'  => trim((string) $keg['nama']),
                    'tanda' => array_map('strval', is_array($keg['tanda'] ?? null) ? $keg['tanda'] : []),
                ];
            }
            $groups[] = ['nama_tahap' => trim((string) ($g['nama_tahap'] ?? '')), 'kegiatan' => $kegiatanList];
        }
    }
}

$po->jadwal = json_encode([
'groups'     => $groups,
'keterangan' => trim((string) ($post['jadwal_keterangan'] ?? '')),
], JSON_UNESCAPED_UNICODE);

    // [FIX] PO yang udah lewat tahap validasi Tim Mitra (status 'disetujui_mitra')
    // atau udah final disetujui Keuangan (status 'disetujui') -- statusnya JANGAN
    // direset balik ke 'terkirim'/'draft' cuma gara-gara pembuat edit & kirim ulang
    // pas lagi revisi. Status cuma boleh maju lewat PoValidasiMitraController::validasi()
    // / PoReviewController::setujui(), dan cuma boleh mundur lewat PoReviewController::
    // bukaKembali(). Edit biasa (revisi) gak boleh ngubah tahapnya.
    $statusSaatIni = $isUpdate ? (string) ($po->status ?? '') : '';
    // [BARU] Saat revisi (review biasa / PPK BLU) PO yang sudah 'terkirim' tetap 'terkirim'.
    $sedangDirevisi = $isUpdate && class_exists('PoRevisiPpkController')
        && \PoRevisiPpkController::sedangDirevisi($this->db, (int) $po->id);
    if (!$sedangDirevisi && !in_array($statusSaatIni, ['disetujui_mitra', 'disetujui'], true)) {
        $po->status = ($post['aksi'] ?? '') === 'kirim' ? 'terkirim' : 'draft';
    }
}

    /**
     * GET /po-kegiatan -- Daftar PO dengan 4 tab (PO Masuk / Terkirim /
     * Disetujui Mitra / Disetujui Keuangan Final), filter bulan+tahun,
     * divisi dikunci buat non-superadmin, dan paginasi 10 baris/halaman.
     *
     * [BARU] Rework total sesuai permintaan: tab, filter periode, kunci
     * divisi per-role, toggle "tampilkan semua", dan paginasi.
     */
public function index($f3) {
    $this->requireAuth();
    $role = $this->getUserRole();
    $isSuperadmin = $role === 'superadmin';

    // --- Tab aktif ---
    $tabsValid = ['masuk', 'draft', 'terkirim', 'disetujui_mitra', 'disetujui'];
    $tab = strtolower(trim((string) $f3->get('GET.tab')));
    if (!in_array($tab, $tabsValid, true)) { $tab = 'masuk'; }

    // --- Divisi: superadmin bebas pilih lewat ?divisi=, role lain (tim_kerja
    // / ketua_tim) dikunci ke divisi mereka sendiri -- parameter GET divisi
    // dari non-superadmin SENGAJA diabaikan biar gak bisa ngintip data
    // divisi lain cuma dengan ganti URL manual. ---
    $divisiTerkunci = $this->divisiPenggunaSaatIni();
    $divisi = $isSuperadmin ? $this->divisiDariRequest($f3) : $divisiTerkunci;
    $namaDivisiTampilan = $divisi === 'lingkungan' ? 'Lingkungan' : ($divisi === 'selulosa' ? 'Selulosa' : 'Semua Divisi');

    // [BARU] Superadmin: Daftar PO gabungan. "Semua Divisi" memuat PO OPTI + PO layanan lain (Sertifikasi, Pengujian, dst.);
    // memilih satu layanan non-OPTI hanya menampilkan PO layanan itu (PO OPTI disembunyikan).
    $labelLayananLain = [
        'sertifikasi' => 'Sertifikasi', 'pengujian' => 'Pengujian', 'kalibrasi' => 'Kalibrasi', 'pendampingan_teknis' => 'Pendampingan Teknis',
        'konsultansi' => 'Konsultansi', 'inspeksi' => 'Inspeksi', 'uji_profesiensi' => 'Uji Profesiensi',
    ];
    $divRaw = strtolower(trim((string) $f3->get('GET.divisi')));
    $layananFilter = ($isSuperadmin && isset($labelLayananLain[$divRaw])) ? $divRaw : '';
    $gabung = $isSuperadmin && !$divisi;
    if ($layananFilter !== '') {
        $namaDivisiTampilan = $labelLayananLain[$layananFilter];
        if (trim((string) $f3->get('GET.tab')) === '') { $tab = 'draft'; }   // "PO Masuk" hanya untuk order OPTI
    }

    // --- Filter bulan/tahun & toggle "Tampilkan Semua Data" ---
    // Default kalau togglenya belum pernah disentuh user (parameter
    // "semua" belum ada di URL): tab "PO Masuk" -> tampilkan semua (gak
    // ada histori bulanan yang jelas buat order yang belum/baru mau
    // dibikinin PO); tab lain (Terkirim/Disetujui Mitra/Disetujui
    // Keuangan) -> default kefilter bulan berjalan. Begitu user pencet
    // togglenya sendiri, pilihan itu yang dipakai & ikut kebawa pas
    // pindah tab/halaman (lewat link yang nyertain parameter semua=).
    $semuaGet = $f3->get('GET.semua');
    $semuaEksplisit = !($semuaGet === null || $semuaGet === '');
    // Perlu/tidaknya filter bulan per tab (dipakai juga untuk angka di tiap tab, supaya angka tab = isi tab kalau diklik)
    $semuaUntukTab = function ($t) use ($semuaEksplisit, $semuaGet) {
        return $semuaEksplisit ? ($semuaGet === '1') : in_array($t, ['masuk', 'draft'], true);
    };
    $tampilkanSemua = $semuaUntukTab($tab);

    $bulanGet = (int) $f3->get('GET.bulan');
    $tahunGet = (int) $f3->get('GET.tahun');
    $bulan = ($bulanGet >= 1 && $bulanGet <= 12) ? $bulanGet : (int) date('n');
    $tahun = ($tahunGet >= 2000 && $tahunGet <= 2100) ? $tahunGet : (int) date('Y');

    $terapkanFilterBulan = !$tampilkanSemua;

    // Kolom tanggal acuan filter bulan, beda-beda per tab sesuai histori
    // status yang beneran tercatat di po_kegiatan/order_layanan.
    $kolomTanggalPerTab = [
        'masuk'           => 'o.ketua_pelaksana_at',
        'draft'           => 'COALESCE(p.updated_at, p.created_at)',
        'terkirim'        => 'p.updated_at',
        'disetujui_mitra' => 'p.tervalidasi_mitra_at',
        'disetujui'       => 'p.validasi_keuangan_at',
    ];
    $kolomTanggal = $kolomTanggalPerTab[$tab];

    // Status efektif PO: verifikasi PPK BLU hanya mengisi validasi_keuangan_at (kolom status tidak berubah),
    // jadi PO yang sudah diverifikasi PPK BLU dihitung 'disetujui' (Final) walau status mentahnya masih terkirim / disetujui_mitra.
    $stEf = "(CASE WHEN p.id IS NULL THEN NULL WHEN p.validasi_keuangan_at IS NOT NULL OR p.status = 'disetujui' THEN 'disetujui' ELSE p.status END)";
    $kondisiTabPerTab = [
        'masuk'           => "p.id IS NULL",
        'draft'           => "{$stEf} = 'draft'",
        'terkirim'        => "{$stEf} = 'terkirim'",
        'disetujui_mitra' => "{$stEf} = 'disetujui_mitra'",
        'disetujui'       => "{$stEf} = 'disetujui'",
    ];

    // Judul kegiatan di daftar ini: PO Selulosa mengambil judul proposal (opti_proposal_riset.judul_proposal, proposal terbaru order itu);
    // kalau proposalnya belum ada / judulnya kosong, dipakai judul order. PO Lingkungan tetap memakai judul order.
    $sqlJudul = "CASE WHEN o.jenis_layanan_opti = 'selulosa'
                      THEN COALESCE(NULLIF(TRIM((SELECT pr_j.judul_proposal FROM opti_proposal_riset pr_j WHERE pr_j.order_id = o.id ORDER BY pr_j.id DESC LIMIT 1)), ''), o.judul_kegiatan)
                      ELSE o.judul_kegiatan END";

    $where = ($layananFilter !== '' ? "1 = 0 AND " : "") . "o.ketua_pelaksana_id IS NOT NULL AND " . $kondisiTabPerTab[$tab];
    $params = [];
    $idx = 1;
    if ($divisi) {
        $where .= " AND o.jenis_layanan_opti = ?";
        $params[$idx++] = $divisi;
    }
    // Pencarian bebas (realtime dari kolom Cari)
    $q = trim((string) $f3->get('GET.q'));
    if (function_exists('mb_substr')) { $q = mb_substr($q, 0, 100); } else { $q = substr($q, 0, 100); }
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $where .= " AND (o.nomor_order LIKE ? OR p.nomor_po LIKE ? OR c.nmcustomer LIKE ? OR {$sqlJudul} LIKE ?)";
        for ($k = 0; $k < 4; $k++) { $params[$idx++] = $like; }
    }
    if ($terapkanFilterBulan) {
        $where .= " AND {$kolomTanggal} IS NOT NULL AND MONTH({$kolomTanggal}) = ? AND YEAR({$kolomTanggal}) = ?";
        $params[$idx++] = $bulan;
        $params[$idx++] = $tahun;
    }

    // --- Total baris buat paginasi (10 data/halaman) ---
    $resCount = $this->safeQuery(
        "SELECT COUNT(*) AS c
         FROM order_layanan o
         JOIN tb_customer c ON o.id_customer = c.id_customer
         LEFT JOIN po_kegiatan p ON p.order_id = o.id" . \PoSchema::bukanSertifikasi('p') . "
         WHERE {$where}",
        $params
    );
    $totalBaris = (int) ($resCount[0]['c'] ?? 0);

    // [BARU] Gabungan: ambil juga PO layanan lain. Dari tiap sumber diambil N baris teratas (N = halaman x 10), lalu digabung & diurutkan.
    $perHalaman = 5;   // jumlah baris per halaman (dikunci 5; sisanya lewat pagination)
    $lain = null;
    if ($gabung) {
        $halamanDiminta = max(1, (int) $f3->get('GET.page'));
        $semuaPerTab = [];
        foreach (['draft', 'terkirim', 'disetujui_mitra', 'disetujui'] as $kT0) { $semuaPerTab[$kT0] = $semuaUntukTab($kT0); }
        $lain = (new \petunjuk_operasional\PoIndexController())->ambilGabungan($tab, $layananFilter, $q, $tampilkanSemua, $bulan, $tahun, min(500, $halamanDiminta * $perHalaman), $semuaPerTab);
        $totalBaris += (int) $lain['total'];
    }

    $totalHalaman = max(1, (int) ceil($totalBaris / $perHalaman));
    $halamanSaatIni = max(1, (int) $f3->get('GET.page'));
    if ($halamanSaatIni > $totalHalaman) { $halamanSaatIni = $totalHalaman; }
    $offset = ($halamanSaatIni - 1) * $perHalaman;

    $sql = "SELECT p.id AS po_id, p.nomor_po, {$stEf} AS po_status, p.created_at AS po_created_at,
                   o.id AS order_id, o.nomor_order, {$sqlJudul} AS judul_kegiatan, o.jenis_layanan_opti, o.status_tinjauan,
                   o.ketua_pelaksana_id, o.ketua_pelaksana_at, o.id_surat_masuk, o.status_keuangan,
                   p.status_review, u_pic.nama_user AS nama_pembuat,
                   c.nmcustomer AS nama_mitra, c.pt_cv,
                   sp.id AS sp_id, sp.surat_kesanggupan_bayar, sp.nominal_penawaran, o.estimasi_biaya,
                   (SELECT id FROM opti_proposal_riset pr WHERE pr.order_id = o.id ORDER BY pr.id DESC LIMIT 1) AS proposal_id,
                   COALESCE((SELECT MAX(pb.is_kesanggupan_bayar) FROM opti_pembayaran pb WHERE pb.order_id = o.id), 0) AS punya_kesanggupan_bayar,
                   (SELECT MAX(COALESCE(pb.created_at, pb.tanggal_bayar)) FROM opti_pembayaran pb WHERE pb.order_id = o.id AND pb.is_kesanggupan_bayar = 1) AS tanggal_surat_kesanggupan,
                   (SELECT MAX(pb.tanggal_bayar) FROM opti_pembayaran pb WHERE pb.order_id = o.id AND pb.status_verifikasi = 'terverifikasi' AND (pb.is_kesanggupan_bayar = 0 OR pb.is_kesanggupan_bayar IS NULL)) AS tanggal_bayar_terakhir,
                   CAST(COALESCE({$kolomTanggal}, p.created_at, o.ketua_pelaksana_at) AS DATETIME) AS sort_ts
            FROM order_layanan o
            JOIN tb_customer c ON o.id_customer = c.id_customer
            LEFT JOIN po_kegiatan p ON p.order_id = o.id" . \PoSchema::bukanSertifikasi('p') . "
            LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
            LEFT JOIN tb_arsipuser u_pic ON u_pic.id_user = o.ketua_pelaksana_id
            WHERE {$where}
            ORDER BY COALESCE({$kolomTanggal}, p.created_at, o.ketua_pelaksana_at) DESC
            LIMIT " . ($gabung ? (int) min(500, $halamanSaatIni * $perHalaman) : (int) $perHalaman) . " OFFSET " . ($gabung ? 0 : (int) $offset);

$daftarPo = $this->safeQuery($sql, $params);

    // [dipertahankan dari versi lama] Riwayat Bukti Pembayaran per-termin buat
    // modal "Lihat File" -- diambil cuma buat baris yang lagi tampil di
    // halaman ini (maks 10), dikelompokin per order_id di PHP (bukan JOIN
    // langsung, soalnya 1 order bisa punya banyak termin). Baris
    // is_kesanggupan_bayar=1 (termin_ke=0, jumlah=0, tanpa bukti) sengaja
    // DIKELUARIN dari sini -- itu bukan termin pembayaran beneran, cuma
    // penanda "pakai Surat Kesanggupan Bayar", jadi ditampilin terpisah di
    // bawah.
    $orderIdUnik = array_values(array_unique(array_map(function ($r) {
        return (int) $r['order_id'];
    }, $daftarPo)));

    $pembayaranByOrder = [];
    if (!empty($orderIdUnik)) {
        $placeholder = implode(',', array_fill(0, count($orderIdUnik), '?'));
        $rowsBayar = $this->safeQuery(
            "SELECT id, order_id, termin_ke, tanggal_bayar, jumlah, keterangan, bukti_bayar, status_verifikasi
             FROM opti_pembayaran
             WHERE order_id IN ($placeholder) AND (is_kesanggupan_bayar = 0 OR is_kesanggupan_bayar IS NULL)
             ORDER BY termin_ke ASC",
            $orderIdUnik
        );
        foreach ($rowsBayar as $rb) {
            $pembayaranByOrder[(int) $rb['order_id']][] = $rb;
        }
    }
    foreach ($daftarPo as $i => &$p) {
        $p['no_urut'] = $offset + $i + 1;
        $p['daftar_pembayaran'] = $pembayaranByOrder[(int) $p['order_id']] ?? [];

        // Data modal "Berkas" -- disamakan dengan "Lihat file" di Daftar Review PO / Validasi PO Mitra:
        // nilai kontrak (penawaran deal, fallback estimasi biaya), total terbayar (hanya yang terverifikasi),
        // Surat Kesanggupan Bayar, dan bukti pembayaran per termin lengkap dengan statusnya.
        $nilai = !empty($p['nominal_penawaran']) ? (float) $p['nominal_penawaran'] : (float) ($p['estimasi_biaya'] ?? 0);
        $terbayar = 0;
        foreach ($p['daftar_pembayaran'] as &$bayar) {
            if (($bayar['status_verifikasi'] ?? '') === 'terverifikasi') { $terbayar += (float) $bayar['jumlah']; }
            $bayar['jumlah_fmt']   = $this->formatRupiahBerkas($bayar['jumlah']);
            $bayar['tanggal_fmt']  = $this->formatTanggalBerkas($bayar['tanggal_bayar']);
            $bayar['bukti_url']    = !empty($bayar['bukti_bayar']) ? ($f3->get('BASE') . '/pembayaran/bukti/' . $this->buatTokenBukti((int) $bayar['id'])) : '';
            $st = $bayar['status_verifikasi'] ?? '';
            $bayar['status_label'] = ($st === 'terverifikasi') ? 'Terverifikasi' : (($st === 'ditolak') ? 'Ditolak' : 'Menunggu');
            $bayar['status_kelas'] = ($st === 'terverifikasi') ? 'emerald' : (($st === 'ditolak') ? 'rose' : 'amber');
        }
        unset($bayar);
        $p['nilai_fmt']        = $this->formatRupiahBerkas($nilai);
        $p['terbayar_fmt']     = $this->formatRupiahBerkas($terbayar);
        $p['kontrak_lunas']    = ($nilai > 0 && $terbayar >= $nilai);
        $p['kesanggupan_url']  = !empty($p['surat_kesanggupan_bayar']) ? ($f3->get('BASE') . '/penawaran/surat-kesanggupan/' . (int) $p['order_id']) : '';
    }
    unset($p);

    // [dipertahankan dari versi lama] Label+tanggal yang ditampilin di modal
    // "Berkas Terkait": kalo order-nya udah beneran lunas (status_keuangan=
    // 'lunas' & ada tanggal pembayaran riil yang terverifikasi) -> "Tanggal
    // Bayar" pake tanggal pembayaran terakhir; kalo belum lunas tapi pakai
    // Surat Kesanggupan Bayar -> "Tanggal Surat Kesanggupan" pake tanggal
    // baris opti_pembayaran (is_kesanggupan_bayar=1) itu dicatat, alias
    // tanggal surat itu diunggah.
    foreach ($daftarPo as &$p) {
        $sudahLunas = ($p['status_keuangan'] ?? '') === 'lunas' && !empty($p['tanggal_bayar_terakhir']);
        $p['sudah_lunas'] = $sudahLunas;
        $p['label_tanggal_pembayaran'] = null;
        $p['tanggal_pembayaran_ditampilkan'] = null;

        if ($sudahLunas) {
            $p['label_tanggal_pembayaran'] = 'Tanggal Bayar';
            $p['tanggal_pembayaran_ditampilkan'] = $p['tanggal_bayar_terakhir'];
        } elseif (!empty($p['punya_kesanggupan_bayar']) && !empty($p['tanggal_surat_kesanggupan'])) {
            $p['label_tanggal_pembayaran'] = 'Tanggal Surat Kesanggupan';
            $p['tanggal_pembayaran_ditampilkan'] = $p['tanggal_surat_kesanggupan'];
        }
    }
    unset($p);

    // [BARU] Gabungan: urutkan PO OPTI + PO layanan lain berdasarkan tanggal terbaru, lalu ambil baris untuk halaman ini
    foreach ($daftarPo as &$pSumber) { $pSumber['sumber'] = 'opti'; }
    unset($pSumber);
    if ($gabung && $lain !== null) {
        // baris PO layanan dilengkapi kunci yang dipakai tabel OPTI (template menghentikan halaman bila ada kunci yang hilang)
        $kunciKosong = ['po_id' => 0, 'po_status' => '', 'po_created_at' => '', 'order_id' => 0, 'nama_mitra' => '', 'pt_cv' => '', 'nama_pembuat' => '', 'jenis_layanan_opti' => '',
                        'judul_kegiatan' => '', 'status_review' => '', 'nilai_fmt' => '', 'terbayar_fmt' => '', 'kontrak_lunas' => false, 'kesanggupan_url' => '', 'daftar_pembayaran' => []];
        foreach ($lain['rows'] as $iL => $rL) {
            $lain['rows'][$iL] = array_merge($kunciKosong, $rL, ['sumber' => 'layanan', 'po_status' => (string) $rL['status'], 'judul_kegiatan' => (string) $rL['judul'], 'nama_mitra' => (string) $rL['mitra']]);
        }
        $gabungan = [];
        foreach (array_merge($daftarPo, $lain['rows']) as $iG => $rG) { $rG['_i'] = $iG; $gabungan[] = $rG; }
        usort($gabungan, function ($a, $b) {
            $c = strcmp((string) (isset($b['sort_ts']) ? $b['sort_ts'] : ''), (string) (isset($a['sort_ts']) ? $a['sort_ts'] : ''));
            return $c !== 0 ? $c : ($a['_i'] - $b['_i']);
        });
        $daftarPo = array_slice($gabungan, $offset, $perHalaman);
        foreach ($daftarPo as $iG => &$rG) { $rG['no_urut'] = $offset + $iG + 1; }
        unset($rG);
    }

    // --- Badge jumlah per tab: ikut filter yang sedang aktif (divisi, pencarian, bulan/tahun) ---
    // Tiap tab dihitung dengan aturan yang sama seperti isi tabnya, jadi angkanya = jumlah baris kalau tab itu diklik.
    $selTab = [];
    foreach ($tabsValid as $kT) {
        $cond = $kondisiTabPerTab[$kT];
        if (!$semuaUntukTab($kT)) {
            $kol = $kolomTanggalPerTab[$kT];
            $cond .= " AND {$kol} IS NOT NULL AND MONTH({$kol}) = " . (int) $bulan . " AND YEAR({$kol}) = " . (int) $tahun;
        }
        $selTab[] = "SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END) AS {$kT}";
    }
    $whereTab = ($layananFilter !== '' ? "1 = 0 AND " : "") . "o.ketua_pelaksana_id IS NOT NULL";
    $paramsTab = [];
    $iT = 1;
    if ($divisi) { $whereTab .= " AND o.jenis_layanan_opti = ?"; $paramsTab[$iT++] = $divisi; }
    if ($q !== '') {
        $whereTab .= " AND (o.nomor_order LIKE ? OR p.nomor_po LIKE ? OR c.nmcustomer LIKE ? OR {$sqlJudul} LIKE ?)";
        for ($k = 0; $k < 4; $k++) { $paramsTab[$iT++] = $like; }
    }
    $resTab = $this->safeQuery(
        "SELECT " . implode(', ', $selTab) . "
         FROM order_layanan o
         JOIN tb_customer c ON o.id_customer = c.id_customer
         LEFT JOIN po_kegiatan p ON p.order_id = o.id" . \PoSchema::bukanSertifikasi('p') . "
         WHERE {$whereTab}",
        $paramsTab
    );
    $tabStat = $resTab[0] ?? [];
    $tabCounts = [];
    foreach ($tabsValid as $kT) { $tabCounts[$kT] = (int) ($tabStat[$kT] ?? 0); }

    // Kartu ringkasan di atas daftar: total keseluruhan per status (ikut filter divisi saja, tidak kena pencarian / bulan)
    $selGlobal = [];
    foreach ($tabsValid as $kT) { $selGlobal[] = "SUM(CASE WHEN {$kondisiTabPerTab[$kT]} THEN 1 ELSE 0 END) AS {$kT}"; }
    $sqlTab = "SELECT " . implode(', ', $selGlobal) . ", COUNT(*) AS total
                FROM order_layanan o
                LEFT JOIN po_kegiatan p ON p.order_id = o.id" . \PoSchema::bukanSertifikasi('p') . "
                WHERE o.ketua_pelaksana_id IS NOT NULL" . ($divisi ? " AND o.jenis_layanan_opti = ?" : "") . ($layananFilter !== '' ? " AND 1 = 0" : "");
    $resGlobal = $this->safeQuery($sqlTab, $divisi ? [1 => $divisi] : []);
    $gStat = $resGlobal[0] ?? [];
    $tabGlobal = [];
    foreach ($tabsValid as $kT) { $tabGlobal[$kT] = (int) ($gStat[$kT] ?? 0); }

    $totalPoSemua = (int) ($gStat['total'] ?? 0);
    if ($lain !== null) {
        foreach (['draft', 'terkirim', 'disetujui_mitra', 'disetujui'] as $kTab) {
            $tabCounts[$kTab] += (int) $lain['counts'][$kTab];
            $tabGlobal[$kTab] += (int) $lain['counts_semua'][$kTab];
        }
        $totalPoSemua += array_sum($lain['counts_semua']);
    }

    $tahunSekarang = (int) date('Y');

    $f3->set('daftar_po', $daftarPo);
    $f3->set('tab_aktif', $tab);
    $f3->set('q', $q);
    $f3->set('q_url', rawurlencode($q));
    $f3->set('tab_counts', $tabCounts);
    $f3->set('divisi', $layananFilter !== '' ? $layananFilter : $divisi);
    $f3->set('peringatan_index', $lain !== null ? $lain['peringatan'] : '');
    $f3->set('bisa_pilih_divisi', $isSuperadmin);
    $f3->set('nama_divisi_tampilan', $namaDivisiTampilan);
    $f3->set('bulan_filter', $bulan);
    $f3->set('tahun_filter', $tahun);
    $f3->set('daftar_tahun', range($tahunSekarang + 1, $tahunSekarang - 4));
    $f3->set('tampilkan_semua', $tampilkanSemua);
    $f3->set('semua_param', $semuaEksplisit ? '&semua=' . ($semuaGet === '1' ? 1 : 0) : '');
    $f3->set('bulan_default', (int) date('n'));
    $f3->set('tahun_default', (int) date('Y'));
    $f3->set('halaman_saat_ini', $halamanSaatIni);
    $f3->set('total_halaman', $totalHalaman);
    // Daftar nomor halaman ala DataTables: 1 ... (sekitar halaman aktif) ... terakhir. n = 0 berarti titik-titik.
    $daftarHalaman = [];
    $tampilHalaman = [];
    for ($h = 1; $h <= $totalHalaman; $h++) {
        if ($h === 1 || $h === $totalHalaman || abs($h - $halamanSaatIni) <= 1) { $tampilHalaman[] = $h; }
    }
    $sebelum = 0;
    foreach ($tampilHalaman as $h) {
        if ($sebelum && $h - $sebelum > 1) { $daftarHalaman[] = ['n' => 0, 'aktif' => false]; }
        $daftarHalaman[] = ['n' => $h, 'aktif' => ($h === $halamanSaatIni)];
        $sebelum = $h;
    }
    $f3->set('daftar_halaman', $daftarHalaman);
    $f3->set('baris_awal', $totalBaris > 0 ? $offset + 1 : 0);
    $f3->set('baris_akhir', $totalBaris > 0 ? $offset + count($daftarPo) : 0);
    $f3->set('total_baris', $totalBaris);
    $f3->set('total_po', $totalPoSemua);
    // Angka 4 kartu ringkasan di atas daftar (ikut filter divisi, gak kena filter bulan)
    $f3->set('total_perlu_tindakan', $tabGlobal['masuk'] + $tabGlobal['draft']);
    $f3->set('total_berjalan',       $tabGlobal['terkirim'] + $tabGlobal['disetujui_mitra']);
    $f3->set('total_disetujui',      $tabGlobal['disetujui']);
    $f3->set('bisa_tambah_po', $role !== 'ketua_tim');
    $f3->set('tombol_tambah_atas', !in_array($divisi, ['selulosa', 'lingkungan'], true));

    $this->render('katim_kerja/po-kegiatan/index.html', 'Petunjuk Operasional (PO)', ($divisi && !$isSuperadmin) ? 'po_' . $divisi : 'po_kegiatan');
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

        // Role layanan (mis. tim_kerja_pengujian) hanya boleh memakai jenis PO layanannya; kartu lain ditampilkan redup & tidak bisa diklik
        $jenisTerkunci = $this->getUserRole() === 'tim_kerja_pengujian' ? 'pengujian' : ($this->isRoleSertifikasi() ? 'sertifikasi' : null);
        foreach ($jenisPoList as $k => $j) {
            $jenisPoList[$k]['terkunci'] = ($jenisTerkunci !== null && $j['slug'] !== $jenisTerkunci) ? 1 : 0;
        }

        $divisi = $this->divisiDariRequest($f3);

$sqlOrder = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.tanggal_masuk, o.jenis_layanan_opti,
                    c.nmcustomer AS nama_mitra, c.alamatcustomer AS alamat_mitra, c.pt_cv
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer

             WHERE o.ketua_pelaksana_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM po_kegiatan p WHERE p.order_id = o.id" . \PoSchema::bukanSertifikasi('p') . ")
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

// Tombol "Kembali": kalau halaman ini dibuka dari daftar Petunjuk Operasional (?asal=petunjuk), balik ke sana
// (lengkap dengan filter layanannya), bukan ke daftar PO OPTI.
$kembaliUrl = '/po-kegiatan';
if ((string) $f3->get('GET.asal') === 'petunjuk') {
    $kembaliUrl = '/petunjuk-operasional';
    $layAsal = preg_replace('/[^a-z_]/', '', strtolower((string) $f3->get('GET.layanan')));
    if ($layAsal !== '') { $kembaliUrl .= '?layanan=' . $layAsal; }
}
$f3->set('kembali_url', $kembaliUrl);

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
            $f3->reroute('/po-kegiatan/pilih?jenis=opti');
            return;
        }

        if (($orderData['status_tinjauan'] ?? '') !== 'layak') {
            $this->setFlashError('Order ini belum dinyatakan layak (Tinjauan Kelayakan Teknis belum selesai).');
            $f3->reroute('/po-kegiatan/pilih?jenis=opti');
            return;
        }

        // Kalau sudah pernah dibuatkan PO, arahkan ke edit alih-alih bikin baru
        $existing = new PoKegiatan($this->db);
        $existing->load(['order_id = ?' . \PoSchema::bukanSertifikasi(''), $orderId]);
        if (!$existing->dry()) {
            $f3->reroute('/po-kegiatan/' . $existing->id . '/edit' . ((string) $f3->get('GET.dari') === 'order' ? '?embed=1&dari=order' : ''));
            return;
        }
        // PO baru baru boleh disusun kalau pembayaran sudah lunas dan SPM sudah aktif
        $syaratPo = $this->syaratPoTerpenuhi($orderId);
        if (!$syaratPo['ok']) {
            $this->setFlashError(!$syaratPo['lunas'] && !$syaratPo['spm']
                ? 'Penerbitan PO belum bisa dibuka: pembayaran belum lunas dan SPM belum diaktifkan.'
                : (!$syaratPo['lunas'] ? 'Penerbitan PO belum bisa dibuka: pembayaran order ini belum lunas.' : 'Penerbitan PO belum bisa dibuka: SPM (Tahap 7) belum diaktifkan.'));
            $f3->reroute('/order/' . $orderId);
            return;
        }
        $f3->set('dari', ((string) $f3->get('GET.dari') === 'order') ? 'order' : '');   // dari Tahap 8 detail order (form tertanam di iframe)
        $tahunSekarang = (int) date('Y');
    $daftarTahun = [];
    for ($i = 0; $i < 5; $i++) { $daftarTahun[] = $tahunSekarang - $i; }
    $f3->set('daftar_tahun', $daftarTahun);

    $infoPembayaran = $this->getInfoPembayaranOtomatis($orderId, $orderData['status_keuangan'] ?? '');
$f3->set('info_pembayaran_json', json_encode($infoPembayaran, JSON_UNESCAPED_UNICODE));

        $suratDasar = $this->suratDasarDariOrder($orderId);
        $jadwalAuto = $this->jadwalOtomatisDariOrder($orderData);
$f3->set('jadwal_auto', $jadwalAuto);
$f3->set('jadwal_auto_json', json_encode($jadwalAuto, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('surat_dasar', $suratDasar);
        $f3->set('surat_dasar_json', json_encode($suratDasar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));

        $f3->set('order', $orderData);
        $f3->set('po', null);
        $f3->set('po_json', 'null');
        // PO Selulosa memakai proposal (bukan surat masuk) sebagai acuan judul; "Lihat Surat Masuk" hanya untuk PO Lingkungan
        $f3->set('surat_masuk_terkait', (($orderData['jenis_layanan_opti'] ?? '') === 'selulosa') ? null : $this->getSuratMasukTerkait($orderData));
        $proposalTerkait = $this->getProposalTerkait($orderData);
        $f3->set('proposal_terkait', $proposalTerkait);
        $f3->set('judul_form_awal', $this->judulAwalForm('', $orderData, $proposalTerkait));
        $this->setPenandaJudul($proposalTerkait, $f3->get('surat_masuk_terkait'));
        // PO Selulosa: Penjelasan Ruang Lingkup terisi dari proposal (PO Lingkungan: kosong, tidak ada penanda)
        $f3->set('ruang_lingkup_proposal', is_array($proposalTerkait) ? (string) $proposalTerkait['ruang_lingkup'] : '');
        $f3->set('nomor_po_saran', $this->generateNomorPo());
        $f3->set('tahun_po_wajib', $this->tahunPoWajib(''));
        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));
        $f3->set('daftar_master_pelaksana_json', json_encode(
            $this->ambilMasterPelaksanaUntukPo($orderData['jenis_layanan_opti'] ?? ''),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        ));
        $f3->set('daftar_jabatan_pelaksana_json', json_encode(
            $this->ambilJabatanPelaksanaUntukPo(),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        ));

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

        // [BARU] Siapa boleh edit PO ini sekarang + konteks revisi ('' | 'review' | 'ppk')
        $konteksRevisi = '';
        if (class_exists('PoRevisiPpkController')) {
            list($bolehEdit, $pesanEdit, $konteksRevisi) = \PoRevisiPpkController::izinEdit(
                $this->db, (int) $po->id, (string) $this->getUserRole(), $this->getUserRole() === 'superadmin', (int) $this->getUserId()
            );
            if (!$bolehEdit) {
                $this->setFlashError($pesanEdit);
                $f3->reroute('/po-kegiatan/daftar');   // revisi PPK BLU kini dikelola dari Daftar Review PO
                return;
            }
        }
        $f3->set('revisi_konteks', $konteksRevisi);

        $orderData = $this->getOrderDenganMitra((int) $po->order_id);
        $poData = $po->cast();

        // [BARU] Nomor PO yang ditampilin di view ngambil langsung dari
        // po_nomor_urut.detail_nomor (sumber aslinya), bukan dari po_kegiatan.nomor_po
        // -- biar selalu sinkron sama tabel master, walaupun nomor_po cuma salinan.
        if (!empty($poData['id_nomor_urut'])) {
            $rowNomor = $this->safeQuery(
                'SELECT detail_nomor FROM po_nomor_urut WHERE id = ?',
                [1 => (int) $poData['id_nomor_urut']]
            );
            if (!empty($rowNomor[0]['detail_nomor'])) {
                $poData['nomor_po'] = $rowNomor[0]['detail_nomor'];
            }
        }

        $tahunSekarang = (int) date('Y');
    $daftarTahun = [];
    for ($i = 0; $i < 5; $i++) { $daftarTahun[] = $tahunSekarang - $i; }
    $f3->set('daftar_tahun', $daftarTahun);

    $infoPembayaran = $this->getInfoPembayaranOtomatis((int) $po->order_id, $orderData['status_keuangan'] ?? '');
    $f3->set('info_pembayaran_json', json_encode($infoPembayaran, JSON_UNESCAPED_UNICODE));

        $suratDasar = $this->suratDasarDariOrder((int) $po->order_id);
        $jadwalAuto = $this->jadwalOtomatisDariOrder($orderData);
$f3->set('jadwal_auto', $jadwalAuto);
$f3->set('jadwal_auto_json', json_encode($jadwalAuto, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('surat_dasar', $suratDasar);
        $f3->set('surat_dasar_json', json_encode($suratDasar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));

        $f3->set('order', $orderData ?: null);
        $f3->set('po', $poData);
        $f3->set('po_json', json_encode($poData, JSON_UNESCAPED_UNICODE));
        $f3->set('tahun_po_wajib', $this->tahunPoWajib((string) ($poData['nomor_po'] ?? '')));
        $f3->set('surat_masuk_terkait', ($orderData && ($orderData['jenis_layanan_opti'] ?? '') !== 'selulosa') ? $this->getSuratMasukTerkait($orderData) : null);
        $proposalTerkait = $orderData ? $this->getProposalTerkait($orderData) : null;
        $f3->set('proposal_terkait', $proposalTerkait);
        $f3->set('judul_form_awal', $this->judulAwalForm((string) ($poData['judul_kegiatan'] ?? ''), $orderData ?: [], $proposalTerkait));
        $this->setPenandaJudul($proposalTerkait, $f3->get('surat_masuk_terkait'));
        $f3->set('ruang_lingkup_proposal', is_array($proposalTerkait) ? (string) $proposalTerkait['ruang_lingkup'] : '');
        $f3->set('daftar_pegawai', $this->safeQuery('SELECT id_user, nama_user FROM tb_arsipuser ORDER BY nama_user ASC'));
        $f3->set('daftar_master_pelaksana_json', json_encode(
            $this->ambilMasterPelaksanaUntukPo($orderData['jenis_layanan_opti'] ?? ''),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        ));
        $f3->set('daftar_jabatan_pelaksana_json', json_encode(
            $this->ambilJabatanPelaksanaUntukPo(),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        ));

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

        // [FIX] Dibawa dari query string (?dari=daftar) pas link edit-nya datang dari
        // halaman "Daftar Review PO"/"Diskusi PO" -- disimpan di hidden field form biar
        // kebawa lagi pas submit, jadi update() tau harus reroute balik ke mana.
        $dariValid = ['daftar', 'ppk', 'order'];
        $dari = (string) $f3->get('GET.dari');
        $f3->set('dari', in_array($dari, $dariValid, true) ? $dari : '');

        $this->render('katim_kerja/po-kegiatan/form.html', 'Edit Petunjuk Operasional', 'po_kegiatan');
    }

    /**
     * Tujuan sesudah simpan / kirim saat form PO tertanam di Tahap 8 detail order (hidden field dari = order):
     * PO terkirim -> detail order (halaman induk), draft -> tetap di form tertanam, belum tersimpan -> form baru tertanam.
     */
    /**
     * Pindah ke tujuan setelah simpan dari Tahap 8 detail order. Form ini berjalan di dalam iframe; kalau tujuannya halaman detail order
     * (/order/ID), halaman itu harus dimuat di jendela utama -- bukan di dalam iframe -- jadi balasannya skrip kecil yang memindahkan jendela utama.
     */
    protected function keTujuanDariOrder($f3, $url) {
        if (strpos((string) $url, '/order/') === 0) {
            $u = (string) $f3->get('BASE') . $url;
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Memuat...</title></head><body>'
                . '<script>try{window.top.location.replace(' . json_encode($u) . ');}catch(e){window.location.replace(' . json_encode($u) . ');}</script>'
                . '<noscript><a href="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '">Lanjut</a></noscript></body></html>';
            exit;
        }
        $f3->reroute($url);
    }

    protected function tujuanDariOrder($orderId, $poId, $status) {
        if ($poId > 0 && (string) $status !== '' && (string) $status !== 'draft') { return '/order/' . (int) $orderId; }
        if ($poId > 0) { return '/po-kegiatan/' . (int) $poId . '/edit?embed=1&dari=order'; }
        return '/po-kegiatan/buat?order_id=' . (int) $orderId . '&embed=1&dari=order';
    }

    /** POST /po-kegiatan/simpan */
    public function store($f3) {
        $this->siapkanBalasanJson($f3);
        $this->requireAuth();
        $tabTujuan = '';
        $idBaru = 0;
        $statusBaru = '';

        try {
            $po = new PoKegiatan($this->db);
            $this->nomorBaruId = 0;
            $this->bind($po, $f3, false);
            $po->created_at = date('Y-m-d H:i:s');
            try {
                $po->save();
            } catch (\Throwable $eSimpan) {
                $this->lepasNomorBaru();   // gagal simpan -> nomor dikembalikan, urutan tidak bolong
                throw $eSimpan;
            }

            // [BARU] Kolom layanan diisi otomatis dari divisi order: 'opti-lingkungan' / 'opti-selulosa'.
            \PoSchema::tandaiLayananOpti($this->db, (int) $po->id, (int) $po->order_id);

            \PoReviewController::tandaiDikirim($this->db, (int) $po->id, (int) $this->getUserId());

            // Notifikasi ke Tim Mitra TIDAK dikirim di sini lagi: PO baru masuk ke Mitra setelah
            // Ketua Tim menyetujui dan Humas memverifikasi (lihat PoHumasController::verifikasi()).

            $this->setFlashSuccess($f3->get('POST.aksi') === 'kirim'
                ? 'PO berhasil dibuat dan dikirim.'
                : 'PO berhasil disimpan sebagai draft.');
            $this->berhasilAjax = true;
            $this->nomorAjax = (string) $po->nomor_po;
            $tabTujuan = ((string) $po->status === 'terkirim') ? 'terkirim' : 'draft';
            $idBaru = (int) $po->id;
            $statusBaru = (string) $po->status;
        } catch (\Exception $e) {
            $this->lepasNomorBaru();
            // [DEBUG-SEMENTARA] Nyatet detail lengkap pas gagal simpan PO -- POST
            // data + pesan error + trace -- ke file log biar gampang dilacak akar
            // masalahnya. Aman dihapus abis kelar debug (lihat storage/debug_po.log).
            try {
                $dir = $f3->get('ROOT') . '/storage';
                if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                $isi = "===== " . date('Y-m-d H:i:s') . " =====\n"
                     . "PESAN: " . $e->getMessage() . "\n"
                     . "POST : " . json_encode($f3->get('POST'), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
                     . "TRACE: " . $e->getTraceAsString() . "\n\n";
                @file_put_contents($dir . '/debug_po.log', $isi, FILE_APPEND);
            } catch (\Exception $e2) {}

            $this->setFlashError('Gagal menyimpan PO: ' . $e->getMessage());
        }

        if ((string) $f3->get('POST.dari') === 'order') {
            $this->keTujuanDariOrder($f3, $this->tujuanDariOrder((int) $f3->get('POST.order_id'), $idBaru, $statusBaru));
            return;
        }
        $tujuan = $this->urlDaftarPoDariOrder((int) $f3->get('POST.order_id'));
        $f3->reroute($tabTujuan !== '' ? $this->denganTab($tujuan, $tabTujuan) : $tujuan);
    }

    /** POST /po-kegiatan/@id/update */
        /** POST /po-kegiatan/@id/update */
        public function update($f3, $params) {
            $this->requireAuth();

            // [BARU] "Simpan dan kirim" dari form revisi: dikirim lewat fetch (X-Requested-With) + kirim_setelah=1.
            // Balasannya JSON; pengiriman (kirim-revisi / kirim-ppk / kirim-katim) dipanggil browser sesudah simpan berhasil.
            $ajaxKirim = ((string) $f3->get('POST.kirim_setelah') === '1')
                && strtolower((string) (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '')) === 'xmlhttprequest';
            $berhasilSimpan = false;
            $galatSimpan = '';
            if (!$ajaxKirim) { $this->siapkanBalasanJson($f3); }
    
            $po = new PoKegiatan($this->db);
            $po->load(['id = ?', (int) $params['id']]);
    
            if ($po->dry()) {
                if ($ajaxKirim) { $this->jsonSimpan(false, 'PO tidak ditemukan.', 404); }
                $this->setFlashError('PO tidak ditemukan.');
                $f3->reroute('/po-kegiatan');
                return;
            }
    
            $statusSebelum = (string) ($po->status ?? '');
    
            // [BARU] PO yang sedang direview Ketua Tim atau sudah disetujui tidak boleh diedit sembarangan
            // [UBAH] bolehEdit() diganti izinEdit(): sama aturannya, ditambah revisi PPK BLU.
            $konteksRevisi = '';
            if (class_exists('PoRevisiPpkController')) {
                list($boleh, $pesan, $konteksRevisi) = \PoRevisiPpkController::izinEdit(
                    $this->db, (int) $po->id, (string) $this->getUserRole(), $this->getUserRole() === 'superadmin', (int) $this->getUserId()
                );
            } else {
                list($boleh, $pesan) = \PoReviewController::bolehEdit($this->db, (int) $po->id);
            }
            if (!$boleh) {
                if ($ajaxKirim) { $this->jsonSimpan(false, $pesan, 403); }
                $this->setFlashError($pesan);
                $f3->reroute('/po-kegiatan/daftar');
                return;
            }
            if ($ajaxKirim && !in_array($konteksRevisi, ['review', 'ppk', 'katim'], true)) {
                $this->jsonSimpan(false, 'PO ini tidak sedang dalam revisi, tidak ada yang bisa dikirim.', 422);
            }
            $sudahTerkirim = ($statusSebelum === 'terkirim');

            // [BARU] Potret isi PO sebelum diubah (jejak perubahan, hanya revisi PPK BLU)
            $potretSebelum = [];
            if ($konteksRevisi === 'ppk') {
                try { $potretSebelum = $this->siapkanDataPo($po, $this->getOrderDenganMitra((int) $po->order_id)); } catch (\Throwable $eSnap) { $potretSebelum = []; }
            }
    
            try {
                $this->nomorBaruId = 0;
                $this->bind($po, $f3, true);
                if ($sudahTerkirim) { $po->status = 'terkirim'; }   // "Simpan draft" saat revisi tidak boleh melempar PO jadi draft
                $po->updated_at = date('Y-m-d H:i:s');
                try {
                    $po->save();
                } catch (\Throwable $eSimpan) {
                    $this->lepasNomorBaru();   // gagal simpan -> nomor dikembalikan
                    throw $eSimpan;
                }

                // [BARU] Catat bagian PO yang berubah selama revisi PPK BLU
                if ($konteksRevisi === 'ppk' && !empty($potretSebelum)) {
                    try {
                        $potretSesudah = $this->siapkanDataPo($po, $this->getOrderDenganMitra((int) $po->order_id));
                        \PoRevisiPpkController::catatPerubahan($this->db, (int) $po->id, (int) $this->getUserId(), $potretSebelum, $potretSesudah);
                    } catch (\Throwable $ePerubahan) {
                        // jejak perubahan pelengkap
                    }
                }

                // [BARU] Draf yang baru dikirim: catat dikirim_at (sekali), pesan sistem, notifikasi ke Ketua Tim.
                // Aman dipanggil berulang, hanya bertindak kalau status 'terkirim' dan dikirim_at masih kosong.
                \PoReviewController::tandaiDikirim($this->db, (int) $po->id, (int) $this->getUserId());
    
                // [FIX] Dipanggil SETELAH save() berhasil. Sekarang hanya shim yang aman (tidak melakukan apa-apa).
                if (class_exists('PoReviewController')) {
                    try {
                        \PoReviewController::tandaiRevisiBilaPerlu($this->db, (int) $po->id, (int) $this->getUserId());
                    } catch (\Exception $eRevisi) {
                        // catatan revisi bersifat pelengkap -- jangan gagalkan penyimpanan PO
                    }
                }
    
                $berhasilSimpan = true;
                $this->berhasilAjax = true;
                $this->nomorAjax = (string) $po->nomor_po;
                if ($ajaxKirim) {
                    // flash tidak dipasang: halaman tujuan sesudah kirim tidak perlu pesan "tandai catatan"
                    // Ketua Tim "Simpan dan setujui" dari Tahap 8: jejak edit tetap dicatat (persetujuan dipanggil browser sesudah ini)
                    if ($konteksRevisi === 'katim') { $this->catatEditKatim($po); }
                } elseif ($konteksRevisi === 'katim') {
                    // [BARU] Ketua Tim mengedit sendiri saat review: status review tetap, pembuat diberi tahu lewat tab Alur + notifikasi
                    $this->catatEditKatim($po);
                    $this->setFlashSuccess('Perubahan PO disimpan. PO tetap menunggu review Anda.');
                } elseif ($konteksRevisi !== '') {
                    $this->setFlashSuccess('Perubahan PO disimpan. Tandai catatan yang sudah diperbaiki, lalu kirim revisi.');
                } else {
                $this->setFlashSuccess($f3->get('POST.aksi') === 'kirim'
                    ? 'PO berhasil diperbarui dan dikirim.'
                    : 'PO berhasil diperbarui sebagai draft.');
                }
            } catch (\Exception $e) {
                $this->lepasNomorBaru();
                $galatSimpan = 'Gagal memperbarui PO: ' . $e->getMessage();
                if (!$ajaxKirim) { $this->setFlashError($galatSimpan); }
            }

            if ($ajaxKirim) {
                $this->jsonSimpan($berhasilSimpan, $berhasilSimpan ? 'Perubahan PO disimpan.' : ($galatSimpan !== '' ? $galatSimpan : 'Gagal memperbarui PO.'), $berhasilSimpan ? 200 : 500);
            }
    
            // [FIX] Kalau edit ini datang dari halaman "Daftar Review PO"/"Diskusi PO"
            // (hidden field 'dari' = 'daftar'), reroute balik ke situ -- bukan ke daftar PO
            // per-divisi yang biasa.
            if ((string) $f3->get('POST.dari') === 'order') {
                $this->keTujuanDariOrder($f3, $this->tujuanDariOrder((int) $po->order_id, (int) $po->id, $berhasilSimpan ? (string) $po->status : 'draft'));
                return;
            }
            if ((string) $f3->get('POST.dari') === 'daftar') {
                $f3->reroute('/po-kegiatan/daftar');
                return;
            }
            // [BARU] Edit dari revisi PPK BLU -> balik ke panel revisinya; menutup panel kembali ke Daftar Review PO
            if ((string) $f3->get('POST.dari') === 'ppk') {
                $f3->reroute('/po-revisi-ppk?buka=' . (int) $po->id . '&dari=daftar');
                return;
            }
            // [BARU] Edit PO yang sedang direview/direvisi tetapi dibuka dari halaman lain (mis. daftar PO per order):
            // balik ke Daftar Review PO / panel revisi PPK BLU, bukan ke halaman daftar PO biasa tempat tombol Buat PO.
            if ($konteksRevisi === 'ppk') {
                $f3->reroute('/po-revisi-ppk?buka=' . (int) $po->id . '&dari=daftar');
                return;
            }
            if ($konteksRevisi === 'review' || $konteksRevisi === 'katim') {
                $f3->reroute('/po-kegiatan/daftar');
                return;
            }
    
            $tujuanDaftar = $this->urlDaftarPoDariOrder((int) $f3->get('POST.order_id'));
            if ($berhasilSimpan) { $tujuanDaftar = $this->denganTab($tujuanDaftar, ((string) $po->status === 'terkirim') ? 'terkirim' : 'draft'); }
            $f3->reroute($tujuanDaftar);
        }

    /** Balasan JSON untuk "Simpan dan kirim" (lihat update()). Mengakhiri request. */
    protected function jsonSimpan($ok, $pesan, $status = 200) {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => (bool) $ok, 'pesan' => (string) $pesan], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Jejak di tab Alur + notifikasi ke Ketua Pelaksana pembuat saat Ketua Tim mengedit PO yang sedang direview. */
    protected function catatEditKatim($po) {
        try {
            $uid = (int) $this->getUserId();
            $nama = 'Ketua Tim';
            try {   // tb_arsipuser ada di database Sekretariat
                $r = $this->dbSekretariat->exec('SELECT nama_user FROM tb_arsipuser WHERE id_user = ? LIMIT 1', [1 => $uid]);
                if (!empty($r[0]['nama_user'])) { $nama = trim((string) $r[0]['nama_user']); }
            } catch (\Throwable $eNama) { /* nama generik */ }
            $o = $this->safeQuery('SELECT ketua_pelaksana_id FROM order_layanan WHERE id = ? LIMIT 1', [1 => (int) $po->order_id]);
            $pembuatId = !empty($o[0]['ketua_pelaksana_id']) ? (int) $o[0]['ketua_pelaksana_id'] : 0;
            \PoReviewController::catatPesanSistem($this->db, (int) $po->id, $uid, 'katim', $nama . ' (Ketua Tim) mengedit isi PO ini saat review.');
            if ($pembuatId > 0) {
                \PoReviewController::kabari($this->db, [$pembuatId], 'PO diedit Ketua Tim', 'PO ' . $po->nomor_po . ' diperbarui langsung oleh Ketua Tim saat review.');
            }
        } catch (\Throwable $e) {
            // jejak bersifat pelengkap, jangan gagalkan penyimpanan
        }
    }

    protected function formatRupiahBerkas($n) {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }

    protected function formatTanggalBerkas($s) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $s, $m)) { return '-'; }
        $bln = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
        return (int) $m[3] . ' ' . $bln[(int) $m[2] - 1] . ' ' . $m[1];
    }

    /** Sama persis dengan PembayaranOptiController::buatTokenBukti() -- kunci BUKTI_KEY yang sama. */
    protected function buatTokenBukti($id) {
        $secret = (string) $this->f3->get('BUKTI_KEY');
        if ($secret === '') { return (string) (int) $id; }
        $key = hash('sha256', $secret, true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt((string) (int) $id, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return rtrim(strtr(base64_encode($iv . $enc), '+/', '-_'), '=');
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

    /** GET /po-kegiatan/@id/cetak -- PDF PO ditampilkan di browser (pratinjau). */
    public function cetak($f3, $params) {
        $this->kirimPdfPo($f3, (int) $params['id'], false);
    }

    /**
     * GET /po-kegiatan/@id/unduh -- PDF PO yang sama, tapi langsung diunduh (Content-Disposition: attachment).
     * Route sengaja bukan ".../cetak": link yang mengandung "/cetak" dicegat layout dan dibuka di modal preview, bukan diunduh.
     */
    public function unduh($f3, $params) {
        $this->kirimPdfPo($f3, (int) $params['id'], true);
    }

    /**
     * PO dianggap FINAL bila sudah diverifikasi PPK BLU (validasi_keuangan_at terisi) atau status efektifnya 'disetujui'
     * (aturan yang sama dengan status efektif di daftar PO). Selain itu PDF diberi watermark DRAFT.
     */
    protected function poSudahFinal($poId) {
        try {
            $r = $this->db->exec("SELECT * FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $poId]);
        } catch (\Throwable $e) {
            return false;
        }
        if (empty($r)) { return false; }
        $r = $r[0];
        $v = trim((string) (isset($r['validasi_keuangan_at']) ? $r['validasi_keuangan_at'] : ''));
        if ($v !== '' && strpos($v, '0000-00-00') !== 0) { return true; }
        return isset($r['status']) && (string) $r['status'] === 'disetujui';
    }

    /**
     * Watermark "DRAFT" besar miring di tengah setiap halaman + keterangan kecil di bawah.
     * Dipasang SETELAH $dompdf->render() lewat page_script, jadi tidak mengubah tata letak dokumen.
     */
    protected function pasangWatermarkDraft($dompdf) {
        $canvas = $dompdf->getCanvas();
        $ratio = (float) $dompdf->getOptions()->getFontHeightRatio();
        if ($ratio <= 0) { $ratio = 1.0; }
        $canvas->page_script(function ($no, $total, $c, $fm) use ($ratio) {
            $tebal = $fm->getFont('Helvetica', 'bold');
            $biasa = $fm->getFont('Helvetica', 'normal');
            $w = $c->get_width();
            $h = $c->get_height();
            $teks = 'DRAFT';
            $ukuran = 120;
            $lebar = $c->get_text_width($teks, $tebal, $ukuran);
            // Canvas::text() menaruh pangkal garis dasar di (x, h - y - tinggiFontMentah) dan memutar searah jarum jam untuk sudut positif,
            // jadi sudut -45 = naik ke kanan. Titik awal dihitung supaya teks berpusat di tengah halaman.
            $tinggiMentah = $c->get_font_height($tebal, $ukuran) / $ratio;
            $rad = deg2rad(45);
            $tinggiHuruf = $ukuran * 0.72;
            $sx = $w / 2 - ($lebar / 2) * cos($rad) + ($tinggiHuruf / 2) * sin($rad);
            $sy = $h / 2 - ($lebar / 2) * sin($rad) - ($tinggiHuruf / 2) * cos($rad);
            $c->set_opacity(0.16);
            $c->text($sx, $h - $sy - $tinggiMentah, $teks, $tebal, $ukuran, [0.39, 0.45, 0.55], 0.0, 0.0, -45);
            $c->set_opacity(1.0);
            $ket = 'DOKUMEN DRAFT - belum disetujui PPK BLU';
            $c->text(($w - $c->get_text_width($ket, $biasa, 7.5)) / 2, $h - 30, $ket, $biasa, 7.5, [0.45, 0.5, 0.58]);
        });
    }

    protected function kirimPdfPo($f3, $poId, $unduh) {
        $po = new PoKegiatan($this->db);
        $po->load(['id = ?', (int) $poId]);

        if ($po->dry()) {
            if ($unduh) {
                http_response_code(404);
                echo 'PO tidak ditemukan.';
                exit;
            }
            $this->setFlashError('PO tidak ditemukan.');
            $f3->reroute('/po-kegiatan');
            return;
        }

        $orderData = $this->getOrderDenganMitra((int) $po->order_id);
        $poData = $this->siapkanDataPo($po, $orderData);
        // Rapikan baris kosong di awal/akhir tiap poin (<br>, <p><br></p>, &nbsp;) supaya jarak di PDF sama seperti preview
        foreach (['dasar_lines', 'tujuan_lines', 'ruang_lingkup_deskripsi_lines', 'alat_bahan_lines', 'metoda_lines'] as $kunciLines) {
            if (empty($poData[$kunciLines]) || !is_array($poData[$kunciLines])) { continue; }
            foreach ($poData[$kunciLines] as $i => $baris) {
                $t = (string) ($baris['teks'] ?? '');
                $kosong = '(?:\s|&nbsp;|\x{00A0}|<br\s*\/?>|<p[^>]*>\s*(?:&nbsp;|<br\s*\/?>|\s)*<\/p>)+';
                $t = preg_replace('/^' . $kosong . '/iu', '', $t);
                $t = preg_replace('/' . $kosong . '$/iu', '', $t);
                $poData[$kunciLines][$i]['teks'] = $t;
            }
        }

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

        $final = $this->poSudahFinal((int) $po->id);
        if (!$final) { $this->pasangWatermarkDraft($dompdf); }

        $namaFile = 'PO-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $poData['nomor_po'] ?? $po->id) . ($final ? '' : '-DRAFT') . '.pdf';

        $dompdf->stream($namaFile, ['Attachment' => (bool) $unduh]);
        exit;
    }

    /** POST /po-kegiatan/preview-live -- render live preview fragment dari data form saat ini (tanpa save DB) */
    public function previewLive($f3) {
        $this->requireAuth();

        try {
            $po = new PoKegiatan($this->db);
            $orderId = (int) ($f3->get('POST.order_id') ?? 0);
            $poId = (int) ($f3->get('POST.po_id') ?? 0);
            if ($poId > 0) {
                $po->load(['id = ?', $poId]);
            }
            
            $this->bind($po, $f3, $poId > 0, true);
            
            $orderData = $this->getOrderDenganMitra($orderId);
            $poData = $this->siapkanDataPo($po, $orderData);

            $f3->set('order', $orderData ?: null);
            $f3->set('po', $poData);

            echo \Template::instance()->render('katim_kerja/po-kegiatan/preview_fragment.html');
        } catch (\Throwable $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo '<div class="text-white text-center py-4"><p class="text-danger fw-bold mb-1">Gagal Menyiapkan Pratinjau</p><small class="text-white-50">' . htmlspecialchars($e->getMessage()) . '</small></div>';
        }
    }

    /** POST /po-kegiatan/cetak-live -- render live PDF stream dari data form saat ini */
    public function cetakLive($f3) {
        $this->requireAuth();

        try {
            $po = new PoKegiatan($this->db);
            $orderId = (int) ($f3->get('POST.order_id') ?? 0);
            $poId = (int) ($f3->get('POST.po_id') ?? 0);
            if ($poId > 0) {
                $po->load(['id = ?', $poId]);
            }
            
            $this->bind($po, $f3, $poId > 0, true);
            
            $orderData = $this->getOrderDenganMitra($orderId);
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

            $namaFile = 'Preview-PO-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $poData['nomor_po'] ?: 'Draft') . '.pdf';
            $dompdf->stream($namaFile, ['Attachment' => false]);
            exit;
        } catch (\Throwable $e) {
            echo 'Gagal mencetak PDF: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

protected function siapkanDataPo($po, $orderData) {
    $poData = $po->cast();

    // [BARU] PO yang belum dikirim belum punya nomor: dokumen pratinjau/PDF memakai nomor sementara
    // dan tanggal hari ini (WIB). Nomor & tanggal resmi baru ada saat PO dikirim.
    $poData['belum_bernomor'] = empty($poData['nomor_po']);
    if ($poData['belum_bernomor']) {
        $hariIni = \PoSchema::hariIniWib();
        $poData['tanggal_po'] = $hariIni;
        $poData['nomor_po'] = 'DRAFT/PO/BBSPJIS/' . \PoSchema::angkaRomawi((int) substr($hariIni, 5, 2)) . '/' . substr($hariIni, 0, 4);
    }

    $rlDecoded = json_decode($poData['ruang_lingkup'] ?? '{}', true) ?: [];
    if (isset($rlDecoded[0])) { $rlDecoded = ['deskripsi' => '', 'items' => $rlDecoded]; }
    $poData['ruang_lingkup_deskripsi'] = $rlDecoded['deskripsi'] ?? '';
    $poData['ruang_lingkup_deskripsi_lines'] = $this->parsePerPointLines($poData['ruang_lingkup_deskripsi']);
    $poData['ruang_lingkup_items'] = $rlDecoded['items'] ?? [];

    $poData['dasar_lines']  = $this->parsePerPointLines($poData['dasar'] ?? '');
    $poData['tujuan_lines'] = $this->parsePerPointLines($poData['tujuan'] ?? '');
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

    // [BARU] Blok tanda tangan dokumen (3 penandatangan dari master). PO lama yang belum punya data -> kolom kosong
    // ( ..... ) sampai penandatangannya dipilih di form.
    $ttdTersimpan = json_decode((string) ($poData['ttd_json'] ?? ''), true);
    $poData['ttd'] = \PoTtd::bersihkan(is_array($ttdTersimpan) ? $ttdTersimpan : [], \PoTtd::KOTA_BAWAAN);
    $poData['ttd_blok'] = \PoTtd::htmlBlok($poData['ttd'], \PoTtd::bulanTahun((string) ($poData['tanggal_po'] ?? '')), ['ketuaBawaan' => 'Ketua Tim Kerja']);

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
    $poData['jadwal_chunks']       = $this->chunkJadwalKolom($kolomInfo['kolom']);

    $jadwalDecoded = json_decode($poData['jadwal'] ?? '{}', true) ?: [];
    $poData['jadwal_groups']           = $jadwalDecoded['groups'] ?? [];
    $poData['jadwal_keterangan_lines'] = $this->parsePerPointLines($jadwalDecoded['keterangan'] ?? '');
    $divisi = $orderData['jenis_layanan_opti'] ?? '';
    $nomor = 3;

    $poData['nomor_alat_bahan'] = null;
    $poData['nomor_metoda'] = null;
    if ($divisi === 'lingkungan') {
        $nomor++; $poData['nomor_alat_bahan'] = $nomor;
        $nomor++; $poData['nomor_metoda'] = $nomor;
    } elseif ($divisi === 'selulosa') {
        $nomor++; $poData['nomor_alat_bahan'] = $nomor;
    }

    // Alamat tampil SETELAH Alat dan Bahan / Metoda di dokumen (pratinjau dan PDF), jadi nomornya dihitung setelahnya
    $poData['nomor_alamat'] = null;
    if (!empty($poData['alamat'])) { $nomor++; $poData['nomor_alamat'] = $nomor; }

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

    /** GET /po-kegiatan/jadwal-kolom?mulai=YYYY-MM-DD&selesai=YYYY-MM-DD&mode=mingguan */
    public function jadwalKolom($f3) {
        $mulai = (string) ($f3->get('GET.mulai') ?? '');
        $selesai = (string) ($f3->get('GET.selesai') ?? '');
        $modeReq = (string) ($f3->get('GET.mode') ?? '');

        $hasil = $this->generateJadwalKolom($mulai, $selesai, $modeReq);
        $hasil['bulan_header'] = $this->groupBulanHeader($hasil['kolom']);
        $hasil['chunks']       = $this->chunkJadwalKolom($hasil['kolom']);

        header('Content-Type: application/json');
        echo json_encode($hasil);
    }

    /**
     * Membagi kolom jadwal menjadi beberapa chunk (maksimal 16 kolom per chunk)
     * agar tabel tidak memanjang ke samping (mentok kanan) pada durasi berbulan-bulan/bertahun-tahun.
     * Pembagian dihitung utuh per bulan sehingga minggu dalam satu bulan tidak terpisah.
     */
    protected function chunkJadwalKolom($kolom, $maxColsPerChunk = 16) {
        if (empty($kolom)) return [];

        if (count($kolom) <= $maxColsPerChunk) {
            $bh = $this->groupBulanHeader($kolom);
            $firstMonth = $bh[0]['label'] ?? '';
            $lastMonth  = $bh[count($bh) - 1]['label'] ?? '';
            $label = ($firstMonth === $lastMonth) ? $firstMonth : $firstMonth . ' – ' . $lastMonth;
            return [[
                'label'        => $label,
                'kolom'        => $kolom,
                'bulan_header' => $bh,
            ]];
        }

        // Kelompokkan kolom per bulan
        $monthGroups = [];
        foreach ($kolom as $k) {
            $m = $k['bulan_label'];
            if (!isset($monthGroups[$m])) {
                $monthGroups[$m] = [];
            }
            $monthGroups[$m][] = $k;
        }

        $chunks = [];
        $currentChunkCols = [];

        foreach ($monthGroups as $mCols) {
            if (!empty($currentChunkCols) && (count($currentChunkCols) + count($mCols)) > $maxColsPerChunk) {
                $bh = $this->groupBulanHeader($currentChunkCols);
                $firstMonth = $bh[0]['label'] ?? '';
                $lastMonth  = $bh[count($bh) - 1]['label'] ?? '';
                $label = ($firstMonth === $lastMonth) ? $firstMonth : $firstMonth . ' – ' . $lastMonth;
                $chunks[] = [
                    'label'        => $label,
                    'kolom'        => $currentChunkCols,
                    'bulan_header' => $bh,
                ];
                $currentChunkCols = [];
            }
            foreach ($mCols as $c) {
                $currentChunkCols[] = $c;
            }
        }

        if (!empty($currentChunkCols)) {
            $bh = $this->groupBulanHeader($currentChunkCols);
            $firstMonth = $bh[0]['label'] ?? '';
            $lastMonth  = $bh[count($bh) - 1]['label'] ?? '';
            $label = ($firstMonth === $lastMonth) ? $firstMonth : $firstMonth . ' – ' . $lastMonth;
            $chunks[] = [
                'label'        => $label,
                'kolom'        => $currentChunkCols,
                'bulan_header' => $bh,
            ];
        }

        return $chunks;
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

    /**
     * PO Selulosa: proposal (opti_proposal_riset, yang terbaru) milik order ini -- judulnya jadi acuan judul PO dan filenya
     * dibuka lewat tombol "Lihat Proposal" (menggantikan "Lihat Surat Masuk"). PO Lingkungan: null (tetap pakai surat masuk).
     */
    protected function getProposalTerkait($orderData) {
        if (($orderData['jenis_layanan_opti'] ?? '') !== 'selulosa') { return null; }
        $orderId = (int) ($orderData['id'] ?? 0);
        if ($orderId <= 0) { return null; }
        $r = $this->safeQuery(
            "SELECT id, judul_proposal, file_proposal FROM opti_proposal_riset WHERE order_id = ? ORDER BY id DESC LIMIT 1",
            [1 => $orderId]
        );
        if (empty($r)) { return null; }
        $pr = $r[0];
        $base = (string) $this->f3->get('BASE');
        $adaFile = !empty($pr['file_proposal']);
        // ruang lingkup dibaca terpisah: kalau kolomnya belum ada di database, cuma ruang lingkupnya yang kosong (judul dan file tetap jalan)
        $rl = $this->safeQuery("SELECT ruang_lingkup FROM opti_proposal_riset WHERE id = ? LIMIT 1", [1 => (int) $pr['id']]);
        $ruangLingkup = !empty($rl) ? trim((string) ($rl[0]['ruang_lingkup'] ?? '')) : '';
        return [
            'id'       => (int) $pr['id'],
            'judul'    => trim((string) $pr['judul_proposal']),
            'ruang_lingkup' => $ruangLingkup,
            'ada_file' => $adaFile,
            // sama dengan tab Berkas: file proposal kalau sudah diunggah, kalau belum halaman proposal order itu
            'url'      => $adaFile ? ($base . '/proposal/' . (int) $pr['id'] . '/file') : ($base . '/order/' . $orderId . '/proposal'),
        ];
    }

    /** Pembanding untuk penanda "Sesuai / Sudah Diubah dari ..." di samping Judul Kegiatan: judul proposal (Selulosa) atau perihal surat masuk. */
    protected function setPenandaJudul($proposalTerkait, $suratMasukTerkait) {
        if (is_array($proposalTerkait)) {
            $this->f3->set('judul_asli_form', (string) $proposalTerkait['judul']);
            $this->f3->set('sumber_judul_form', 'Proposal');
        } else {
            $this->f3->set('judul_asli_form', is_array($suratMasukTerkait) ? (string) ($suratMasukTerkait['perihal'] ?? '') : '');
            $this->f3->set('sumber_judul_form', 'Surat Masuk');
        }
    }

    /** Isi awal kolom Judul Kegiatan di form PO: judul PO yang tersimpan; kalau belum ada, judul proposal (Selulosa); kalau tidak ada juga, judul order. */
    protected function judulAwalForm($judulPo, $orderData, $proposalTerkait) {
        if (trim((string) $judulPo) !== '') { return (string) $judulPo; }
        if (is_array($proposalTerkait) && $proposalTerkait['judul'] !== '') { return $proposalTerkait['judul']; }
        return (string) ($orderData['judul_kegiatan'] ?? '');
    }

    /** [PERLU DIVERIFIKASI ULANG tiap tahun sesuai SKB 3 Menteri] */
    protected function getHariLibur() {
        return [
            '2026-01-01', '2026-02-17', '2026-03-19', '2026-03-20', '2026-03-21',
            '2026-04-03', '2026-05-01', '2026-05-14', '2026-05-27', '2026-05-31',
            '2026-06-01', '2026-06-17', '2026-08-17', '2026-08-26', '2026-12-25',
        ];
    }

    protected function generateJadwalKolom($mulaiStr, $selesaiStr, $modeReq = '') {
        if (empty($mulaiStr) || empty($selesaiStr)) return ['mode' => null, 'kolom' => []];
    
        $mulai = new \DateTime($mulaiStr);
        $selesai = new \DateTime($selesaiStr);
        if ($selesai < $mulai) return ['mode' => null, 'kolom' => []];
    
        $selisihHari = $mulai->diff($selesai)->days + 1;
        $liburSet = $this->getHariLiburSet();
        $bulanNama = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    
        $kolom = [];
    
        // Jika mode explicitly requested as 'mingguan' atau 'bulanan', atau durasi > 31 hari
        if (($modeReq === 'mingguan' || $modeReq === 'bulanan') || (empty($modeReq) && $selisihHari > 31)) {
            $mode = 'bulanan';
            $startYear  = (int) $mulai->format('Y');
            $startMonth = (int) $mulai->format('n');
            $endYear    = (int) $selesai->format('Y');
            $endMonth   = (int) $selesai->format('n');

            $currYear  = $startYear;
            $currMonth = $startMonth;

            while ($currYear < $endYear || ($currYear === $endYear && $currMonth <= $endMonth)) {
                $bLabel = $bulanNama[$currMonth] . ' ' . $currYear;
                for ($w = 1; $w <= 4; $w++) {
                    $key = $currYear . '-' . str_pad($currMonth, 2, '0', STR_PAD_LEFT) . '-w' . $w;
                    $kolom[] = [
                        'key'         => $key,
                        'label'       => (string) $w,
                        'bulan_label' => $bLabel,
                        'libur'       => false,
                    ];
                }
                $currMonth++;
                if ($currMonth > 12) {
                    $currMonth = 1;
                    $currYear++;
                }
            }
        } else {
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

            // [BARU] Item "Disediakan PT X" (RAB Lingkungan) -- biaya ditanggung partner: Rp 0, tanpa rincian kali/jumlah/hari/tarif
            $ptItem = trim((string) ($item['disediakan_pt'] ?? ''));
            if ($ptItem !== '' && ($item['tipe_item'] ?? 'biasa') === 'biasa') {
                $baris[] = [
                    'tipe' => 'item', 'depth' => $depth, 'nomor' => '', 'nama' => $jenis, 'rincian' => '',
                    'kali_fmt' => '', 'dim2_fmt' => '', 'dim2_label' => '', 'hari_fmt' => '',
                    'satuan_biaya' => 0, 'biaya' => 0, 'disediakan_pt' => $ptItem,
                ];
                continue;
            }

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

    /**
     * [BARU] Pastiin kolom po_kegiatan.tanggal_po & id_nomor_urut, plus tabel
     * master po_nomor_urut, udah ada di DB -- dibuat/di-migrasi otomatis kalau
     * belum ada/masih skema lama (pola self-healing yang sama kaya
     * ensureSchema() di PembayaranOptiController), jadi gak perlu migrasi
     * manual di server produksi.
     */
    protected function ensureSchemaNomorPo(): void {
        static $done = false;
        if ($done) return;
        try {
            $colsTgl = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'tanggal_po'");
            if (empty($colsTgl)) {
                $this->db->exec("ALTER TABLE po_kegiatan ADD COLUMN tanggal_po DATE NULL AFTER nomor_po");
            }
            $colsIdNomor = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'id_nomor_urut'");
            if (empty($colsIdNomor)) {
                $this->db->exec("ALTER TABLE po_kegiatan ADD COLUMN id_nomor_urut INT UNSIGNED NULL AFTER nomor_po");
            }

            // [FIX] dasar_struktur ternyata BUKAN kolom asli di po_kegiatan (cuma
            // properti yang di-set langsung di bind() tanpa pernah dibikinin
            // kolomnya). Akibatnya F3 Mapper nganggep ini "adhoc field" & nyimpen
            // nilainya (JSON) sebagai ekspresi SQL MENTAH (gak di-escape/bind),
            // yang bikin query SELECT reload setelah INSERT jadi rusak & error
            // "...}) AS" pas bikin PO baru. Fix-nya: bikinin kolom asli biar F3
            // nganggep ini kolom biasa (ke-bind aman kaya kolom lain).
            // [BARU] nomor_po harus boleh NULL: nomor baru dibuat saat PO dikirim, draft belum bernomor.
            \PoSchema::nomorPoBolehKosong($this->db);

            $colsDasarStruktur = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'dasar_struktur'");
            if (empty($colsDasarStruktur)) {
                $this->db->exec("ALTER TABLE po_kegiatan ADD COLUMN dasar_struktur TEXT NULL AFTER dasar");
            }

            $tabelAda = $this->db->exec("SHOW TABLES LIKE 'po_nomor_urut'");
            if (!empty($tabelAda)) {
                $colsDetail = $this->db->exec("SHOW COLUMNS FROM po_nomor_urut LIKE 'detail_nomor'");
                if (empty($colsDetail)) {
                    // [MIGRASI] Skema lama po_nomor_urut cuma 1 baris counter per
                    // bulan+tahun (tanpa detail_nomor) -- strukturnya beda total
                    // sama skema baru (1 baris per nomor yang DITERBITKAN). Fitur
                    // ini baru aja dipasang & belum sempet kepake buat PO beneran,
                    // jadi aman di-drop & dibikin ulang pakai skema baru.
                    $this->db->exec("DROP TABLE po_nomor_urut");
                }
            }

            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS po_nomor_urut (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nomor_urut INT UNSIGNED NOT NULL,
                    bulan TINYINT UNSIGNED NOT NULL,
                    tahun SMALLINT UNSIGNED NOT NULL,
                    detail_nomor VARCHAR(60) NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uniq_bulan_tahun_urut (bulan, tahun, nomor_urut),
                    UNIQUE KEY uniq_detail_nomor (detail_nomor)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $done = true;
        } catch (\Exception $e) {}
    }

    /** [BARU] 1-12 -> angka romawi (I..XII), buat format Nomor PO. */
    protected function angkaRomawi($bulan) {
        $peta = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
                 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
        return $peta[(int) $bulan] ?? 'I';
    }

    /**
     * [BARU] Tahun yang diwajibkan untuk Tanggal Surat PO: PO baru = tahun berjalan; PO yang sudah punya
     * nomor (lagi diedit) = tahun di nomornya (mis. "12/PO/BBSPJIS/X/2026" -> 2026), biar draft lama
     * tetap bisa diedit di tahun berikutnya tanpa menabrak nomornya sendiri.
     */
    protected function tahunPoWajib($nomorPo = '') {
        if (preg_match('/\/(\d{4})\s*$/', (string) $nomorPo, $m)) { return (int) $m[1]; }
        return (int) date('Y');
    }

    /**
     * [BARU] Nomor urut tertinggi yang KEBACA dari nomor_po di po_kegiatan untuk satu TAHUN (format
     * "<urut>/PO/BBSPJIS/<romawi>/<tahun>"), termasuk nomor lama yang diisi manual sebelum tabel master
     * po_nomor_urut ada -- dipakai bareng MAX() di tabel master biar nomor baru gak bentrok.
     */
    protected function cariUrutanTertinggiTahunIni($tahun) {
        $rows = $this->safeQuery(
            "SELECT nomor_po FROM po_kegiatan WHERE nomor_po LIKE ? AND nomor_po NOT LIKE '%/SVPK/%'", // deret nomor PO Pengujian (.../SVPK/...) terpisah
            [1 => '%/' . (int) $tahun]
        );
        $max = 0;
        foreach ($rows as $r) {
            if (preg_match('/^\s*(\d+)\s*\//', (string) ($r['nomor_po'] ?? ''), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return $max;
    }

    /**
     * [BARU] Generate Nomor PO beneran -- nomor urut BERLANJUT sepanjang TAHUN (tidak reset tiap bulan)
     * dan kembali ke 1 begitu ganti tahun. Format: "<urut>/PO/BBSPJIS/<bulan romawi>/<tahun>", bulan &
     * tahun diambil dari Tanggal Surat PO. Tabel master po_nomor_urut nyimpen SATU BARIS per nomor yang
     * pernah diterbitkan. Nomor urut baru = MAX(nomor_urut) tahun itu (dari tabel master DAN dari
     * nomor_po lama) + 1. Proses ambil-nomor dikunci per tahun (GET_LOCK) biar dua orang yang simpan
     * barengan gak dapat nomor yang sama; UNIQUE KEY detail_nomor tetap jadi pengaman terakhir.
     * Dipanggil SEKALI per PO, pas pertama kali disimpan (lihat bind()).
     *
     * @return array{id:int, detail_nomor:string}
     */
    protected function generateNomorPoBaru($tanggalPo) {
        $this->ensureSchemaNomorPo();

        $ts    = strtotime((string) $tanggalPo) ?: time();
        $bulan = (int) date('n', $ts);
        $tahun = (int) date('Y', $ts);

        $namaKunci = 'po_nomor_urut_' . $tahun;
        $kunciDapat = false;
        try {
            $r = $this->db->exec('SELECT GET_LOCK(?, 5) AS k', [1 => $namaKunci]);
            $kunciDapat = !empty($r) && (int) ($r[0]['k'] ?? 0) === 1;
        } catch (\Exception $e) {}

        try {
            for ($percobaan = 0; $percobaan < 5; $percobaan++) {
                $row = $this->safeQuery(
                    'SELECT COALESCE(MAX(nomor_urut), 0) AS maxu FROM po_nomor_urut WHERE tahun = ?',
                    [1 => $tahun]
                );
                $maxTercatat = (int) ($row[0]['maxu'] ?? 0);
                $maxLegacy   = $this->cariUrutanTertinggiTahunIni($tahun);
                $urutanBaru  = max($maxTercatat, $maxLegacy) + 1 + $percobaan;
                $detailNomor = $urutanBaru . '/PO/BBSPJIS/' . $this->angkaRomawi($bulan) . '/' . $tahun;

                try {
                    $this->db->exec(
                        'INSERT INTO po_nomor_urut (nomor_urut, bulan, tahun, detail_nomor, updated_at) VALUES (?, ?, ?, ?, NOW())',
                        [1 => $urutanBaru, 2 => $bulan, 3 => $tahun, 4 => $detailNomor]
                    );
                    $idBaru = (int) ($this->db->exec('SELECT LAST_INSERT_ID() AS id')[0]['id'] ?? 0);
                    return ['id' => $idBaru, 'detail_nomor' => $detailNomor];
                } catch (\Exception $e) {
                    // Bentrok UNIQUE KEY -- coba lagi dari angka berikutnya (maksimal 5x).
                    continue;
                }
            }
        } finally {
            if ($kunciDapat) {
                try { $this->db->exec('SELECT RELEASE_LOCK(?)', [1 => $namaKunci]); } catch (\Exception $e) {}
            }
        }

        throw new \Exception('Gagal membuat Nomor PO baru, silakan coba simpan ulang.');
    }

    protected function safeQuery($sql, $params = []) {
        try {
            return $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }
}
