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
        return null;
    }

    /** Sementara hanya Superadmin. */
    protected function izinAkses() {
        return $this->isSuperadmin();
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
        $this->kendalaSkema();

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
        $kolomTanggal = 'COALESCE(p.tanggal_po, DATE(p.created_at))';
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
            $where .= " AND (p.nomor_po LIKE ? OR p.judul_kegiatan LIKE ? OR a.nomor_order LIKE ? OR a.no_id_order LIKE ? OR c.nmcustomer LIKE ?)";
            for ($k = 0; $k < 5; $k++) { $params[] = $like; }
        }
        if (!$tampilkanSemua) {
            $where .= " AND {$kolomTanggal} IS NOT NULL AND MONTH({$kolomTanggal}) = ? AND YEAR({$kolomTanggal}) = ?";
            $params[] = $bulan;
            $params[] = $tahun;
        }

        $dbName = $this->namaDbSertifikasi();
        $from = "FROM po_kegiatan p
                 LEFT JOIN `{$dbName}`.`tb_arsip_sertifikasi` a ON p.layanan = '" . \PoSchema::SERTIFIKASI . "' AND a.id_arsip_sertifikasi = p.order_id
                 LEFT JOIN tb_customer c ON c.id_customer = a.id_customer";
        // Cadangan bila database SISERTIFIKASI tidak terbaca: daftar tetap tampil, tanpa No. Order & Mitra
        $fromTanpaOrder = "FROM po_kegiatan p
                 LEFT JOIN (SELECT NULL AS id_customer, NULL AS nomor_order, NULL AS no_id_order) a ON 1 = 0
                 LEFT JOIN tb_customer c ON c.id_customer = a.id_customer";

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
                    "SELECT p.id, p.layanan, p.nomor_po, p.tanggal_po, p.judul_kegiatan, p.status, p.order_id, p.created_at, p.updated_at,
                            a.nomor_order, a.no_id_order, c.nmcustomer, c.pt_cv
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

        $daftar = [];
        $no = ($halaman - 1) * $perHalaman;
        foreach ($rows as $r) {
            $slug = (string) $r['layanan'];
            $order = trim((string) ($r['nomor_order'] ?? ''));
            if ($order === '') { $order = trim((string) ($r['no_id_order'] ?? '')); }
            $tgl = (string) ($r['tanggal_po'] ?? '');
            $daftar[] = [
                'no_urut'       => ++$no,
                'po_id'         => (int) $r['id'],
                'nomor_po'      => (string) ($r['nomor_po'] ?? ''),
                'tanggal_po'    => ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) ? date('d/m/Y', strtotime($tgl)) : '-',
                'judul'         => (string) ($r['judul_kegiatan'] ?? ''),
                'status'        => (string) $r['status'],
                'layanan'       => $slug,
                'layanan_label' => $semuaLayanan[$slug] ?? ucfirst(str_replace('_', ' ', $slug)),
                'nomor_order'   => $order,
                'mitra'         => ($r['nmcustomer'] ?? '') !== '' || ($r['pt_cv'] ?? '') !== ''
                                    ? \Customer::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nmcustomer'] ?? '') : '',
                // baru layanan sertifikasi yang punya form simpan/edit; layanan lain menyusul
                'url_edit'      => $slug === \PoSchema::SERTIFIKASI ? $f3->get('BASE') . '/petunjuk-operasional/sertifikasi/' . (int) $r['id'] . '/edit' : '',
                'url_lihat'     => $slug === \PoSchema::SERTIFIKASI ? $f3->get('BASE') . '/petunjuk-operasional/sertifikasi/' . (int) $r['id'] . '/lihat' : '',
            ];
        }

        // ---- jumlah per tab (tidak ikut filter bulan/pencarian, tetap ikut filter layanan) ----
        $tabCounts = ['draft' => 0, 'terkirim' => 0];
        try {
            $rc = $this->db->exec(
                "SELECT SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) AS draft,
                        SUM(CASE WHEN p.status = 'terkirim' THEN 1 ELSE 0 END) AS terkirim
                 FROM po_kegiatan p WHERE {$dasar}",
                $this->indeks($dasarParams)
            );
            $tabCounts['draft'] = (int) ($rc[0]['draft'] ?? 0);
            $tabCounts['terkirim'] = (int) ($rc[0]['terkirim'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[PO Index] gagal menghitung tab: ' . $e->getMessage());
        }

        // ---- notif: order sertifikasi yang belum dibuatkan PO -> masuk ke Pusat Pemberitahuan (lonceng + bubble) ----
        // dijalankan sebelum render() supaya langsung muncul di bubble/lonceng halaman ini juga
        $this->sinkronNotifOrderBelumPo();

        $daftarLayananView = [];
        foreach ($semuaLayanan as $slug => $label) {
            $daftarLayananView[] = ['slug' => $slug, 'label' => $label];
        }
        $tahunIni = (int) date('Y');
        $daftarTahun = range($tahunIni + 1, min(2024, $tahun));

        $f3->set('daftar_po', $daftar);
        $f3->set('tab_aktif', $tab);
        $f3->set('tab_counts', $tabCounts);
        $f3->set('layanan', $layanan);
        $f3->set('layanan_list', $daftarLayananView);
        $f3->set('bisa_pilih_layanan', $terkunci === null);
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
