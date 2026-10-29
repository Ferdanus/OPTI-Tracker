<?php
namespace petunjuk_operasional;

/**
 * PoPengujianController -- Petunjuk Operasional (PO) jenis PENGUJIAN.
 *
 * Sengaja DIPISAH dari modul PO OPTI (PoKegiatanController dkk): beda folder
 * (app/controllers/petunjuk_operasional/), beda namespace, beda folder view
 * (app/views/petunjuk_operasional/po_pengujian/), beda URL (/petunjuk-operasional/...).
 *
 * ALUR:
 *   0. Daftar PO -> "Tambah PO Baru" (layanan Pengujian) / kartu Pengujian di /po-kegiatan/pilih -> modal "Pilih Order
 *      Pengujian" (dengan kolom cari) -> /petunjuk-operasional/pengujian/create?order=<tb_arsipsurat.id_arsip>.
 *      Daftar order: GET /petunjuk-operasional/pengujian/order?q=... (JSON). Order yang sudah dibuatkan PO tidak muncul lagi.
 *   1. /petunjuk-operasional/pengujian/create?order=ID[,ID,...] -> isi form PO (live preview dokumen di sebelah kanan).
 *      Satu PO boleh memuat BANYAK order (modal pilih order: centang beberapa). Daftar order disimpan di po_kegiatan.daftar_order
 *      (snapshot JSON) + tabel po_pengujian_order (satu order hanya boleh ada di satu PO); po_kegiatan.id_arsip_surat = order pertama.
 *      Tanpa ?order= -> dialihkan ke Daftar PO dan modal pilih order terbuka otomatis.
 *   2. Tombol di bawah form (susunan sama dengan form PO OPTI): "Simpan sebagai Draft" -> status 'draft',
 *      "Kirim" -> status 'terkirim' (Kirim memeriksa kelengkapan isian). Form dikirim sebagai POST biasa (bukan AJAX),
 *      lalu pindah ke Daftar PO (PoIndexController, /petunjuk-operasional) dengan pesan sukses. Bila server menolak isian,
 *      form ditampilkan lagi dengan isian yang sama + pesan kesalahan (tidak ada yang hilang).
 *   3. Simpan -> tabel po_kegiatan dengan layanan = 'pengujian'. Pemetaan form <-> kolom ada di PoPengujian
 *      (app/models/PoPengujian.php); pembacaan field form + dokumen (preview & PDF) ada di PoPengujianDokumen.
 *      Nomor PO = "<urut>/PO/BBSPJIS/SVPK/<ROMAWI>/<TAHUN>", OTOMATIS dari po_nomor_urut saat PO DIKIRIM
 *      (satu deret per tahun bersama PO OPTI & Sertifikasi; bulan/tahun = tanggal kirim WIB), tidak diketik dan tidak berubah setelah terbit.
 *      Draft belum bernomor; Tanggal PO (tercetak) = tanggal kirim, tanggal pembuatan = created_at.
 *   4. Ikon mata di Daftar PO -> /pengujian/@id/lihat (preview dokumen), tombol Edit di sana (hanya untuk draft) ->
 *      /pengujian/@id/edit.
 *   5. Preview langsung & unduh PDF: POST /pengujian/preview-live (balasan: fragmen HTML dokumen) dan
 *      POST /pengujian/cetak-live (PDF Dompdf), keduanya dibuat dari field form yang sedang diisi (tanpa simpan).
 *      GET /pengujian/jadwal-kolom: kolom tanggal jadwal (harian / mingguan).
 *
 * Akses: Superadmin dan Tim Kerja Pengujian (role tim_kerja_pengujian).
 * Alur REVIEW PO Pengujian belum ada (halaman review masih penanda) -- tahap berikutnya.
 *
 * Kompatibel PHP 7.2.
 */
class PoPengujianController extends \Controller {

    /** Superadmin dan Tim Kerja Pengujian. */
    protected function izinPengujian() {
        return $this->isSuperadmin() || $this->getUserRole() === 'tim_kerja_pengujian';
    }

    /** Untuk halaman: bila tidak berhak, alihkan ke dashboard. Return true bila boleh lanjut. */
    protected function wajibIzinHalaman($f3) {
        $this->requireAuth();
        if (!$this->izinPengujian()) {
            $this->setFlashError('PO Pengujian hanya bisa diakses Superadmin dan Tim Kerja Pengujian.');
            $f3->reroute('/dashboard');
            return false;
        }
        return true;
    }

    /** Mode balasan JSON: dipakai dialog "Generate Nomor PO" (permintaan AJAX ke simpan/update; redirect diubah jadi JSON). */
    protected $jsonMode = false;
    protected $berhasilAjax = false;
    protected $nomorAjax = '';

    protected function permintaanJson() {
        $xr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $ac = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        return $xr === 'xmlhttprequest' && stripos($ac, 'application/json') !== false;
    }

    /**
     * Untuk permintaan AJAX: setiap $f3->reroute() (sukses maupun gagal) dibalas JSON {ok, message, nomor, redirect}
     * alih-alih redirect. Pesan flash dikonsumsi di sini supaya tidak muncul dua kali di halaman berikutnya.
     */
    protected function siapkanBalasanJson($f3) {
        if (!$this->permintaanJson()) { return; }
        $this->jsonMode = true;
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
        $self = $this;
        $f3->set('ONREROUTE', function ($url) use ($self, $f3) {
            $err = (string) ($_SESSION['flash_error'] ?? '');
            $ok = (string) ($_SESSION['flash_success'] ?? '');
            unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
            if (is_string($url) && $url !== '' && $url[0] === '/') { $url = $f3->get('BASE') . $url; }
            $berhasil = $self->berhasilAjax;
            $self->json([
                'ok'       => $berhasil,
                'message'  => $berhasil ? $ok : ($err !== '' ? $err : 'Sesi berakhir atau akses ditolak. Muat ulang halaman lalu coba lagi.'),
                'nomor'    => $berhasil ? (string) $self->nomorAjax : '',
                'redirect' => (string) $url,
            ]);
            return true;
        });
    }

    protected function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function jsonHive($data) {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /** Pastikan skema po_kegiatan siap untuk Pengujian; return pesan kendala ('' = aman). */
    protected function kendalaSkema() {
        \PoPengujian::pastikan($this->db);
        return \PoPengujian::kendala();
    }

    protected function statusDariAksi($f3) {
        return ((string) $f3->get('POST.aksi')) === 'kirim' ? 'terkirim' : 'draft';
    }

    protected function adaDompdf() {
        return class_exists('\\Dompdf\\Dompdf');
    }

    /** Hari libur nasional (tabel yang sama dengan jadwal PO OPTI). */
    protected function daftarLibur() {
        try {
            $rows = $this->db->exec('SELECT tanggal_libur FROM tb_tanggal_libur');
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $t = strtotime((string) ($r['tanggal_libur'] ?? ''));
            if ($t) { $out[] = date('Y-m-d', $t); }
        }
        return $out;
    }

    /**
     * Tanggal PO yang sudah tercatat (Y-m-d) atau null. Tanggal PO dibuat sekali, saat PO pertama kali disimpan
     * (draft ataupun langsung dikirim), lalu tetap walaupun PO diedit / dikirim belakangan.
     */
    protected function tanggalPoTersimpan($po) {
        $tgl = $po ? (string) ($po['tanggal_po'] ?? '') : '';
        return ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) ? date('Y-m-d', strtotime($tgl)) : null;
    }

    /**
     * Path (tanpa BASE) tujuan setelah simpan draft / kirim. Superadmin: Daftar PO gabungan (/po-kegiatan).
     * Role Tim Kerja Pengujian: tetap ke daftar Pengujian, karena /po-kegiatan tidak memuat PO layanan untuk mereka.
     */
    protected function pathSetelahSimpan($tab = 'draft') {
        return $this->isSuperadmin() ? '/po-kegiatan?tab=' . $tab : $this->pathIndex($tab);
    }

    /** Path (tanpa BASE) Daftar PO Pengujian pada tab tertentu, untuk $f3->reroute(). */
    protected function pathIndex($tab = 'draft') {
        return '/petunjuk-operasional?layanan=pengujian&tab=' . $tab;
    }

    /**
     * Variabel view yang sama untuk create / edit / lihat.
     * $pesan (opsional) = ['judul' => ..., 'teks' => ..., 'daftar' => [...]]: ditampilkan sebagai popup saat halaman terbuka
     * (dipakai ketika server menolak isian dan form ditampilkan ulang).
     */
    protected function setVarView($f3, $poInfo, $lihat, $order = null, $pesan = null) {
        $f3->set('po_json', $this->jsonHive($poInfo));
        // order_json = DAFTAR order asal PO (larik, bisa lebih dari satu)
        $f3->set('order_json', $this->jsonHive($order === null ? [] : (isset($order['id']) ? [$order] : array_values($order))));
        $f3->set('master_pelaksana_json', $this->jsonHive($this->namaPegawaiTimPengujian()));
        $f3->set('jabatan_pelaksana_json', $this->jsonHive($this->jabatanPelaksanaAktif()));
        $f3->set('mode_lihat', $lihat ? 'true' : 'false');
        $f3->set('tanggal_default', date('Y-m-d'));
        $f3->set('pesan_server', $this->jsonHive($pesan));
        $f3->set('csrf_po', (string) ($f3->get('csrf_token') ?? ''));
    }

    /**
     * Pilihan nama di Tim Pelaksana form PO: Data Master Tim Pelaksana PO layanan Pengujian (yang aktif).
     * Master masih kosong -> cadangan: semua pegawai, supaya form tetap bisa diisi.
     */
    protected function namaPegawaiTimPengujian() {
        $out = [];
        if (class_exists('MasterTimPelaksanaController')) {
            foreach (\MasterTimPelaksanaController::ambilAktif($this->db, 'pengujian') as $r) { $out[] = (string) $r['nama']; }
        }
        if (!$out) {
            try {
                foreach ($this->db->exec('SELECT nama_user FROM tb_arsipuser ORDER BY nama_user ASC') as $r) {
                    $n = trim((string) $r['nama_user']);
                    if ($n !== '') { $out[] = $n; }
                }
            } catch (\Throwable $e) { error_log('[PO Pengujian] gagal baca pegawai: ' . $e->getMessage()); }
        }
        return array_values(array_unique($out));
    }

    /** Saran jabatan di Tim Pelaksana: Data Master Jabatan Pelaksana PO layanan Pengujian (yang aktif). */
    protected function jabatanPelaksanaAktif() {
        return class_exists('MasterTimPelaksanaController') ? \MasterTimPelaksanaController::ambilJabatanAktif($this->db, 'pengujian') : [];
    }

    /** Batas baris daftar order di modal (sisanya dicari lewat kolom cari). */
    const BATAS_ORDER = 100;

    /**
     * GET /petunjuk-operasional/pengujian/order[?jenis=air|kertas|udara|limbah_padat|amdk|bkb][&q=kata]
     * JSON daftar order (arsip surat di sil2020 yang tercatat di tb_penerimaan_contoh) yang belum dibuatkan PO Pengujian
     * -- untuk modal pilih order. ?ringkas=1 -> daftar jenis + jumlah order (langkah pilih jenis).
     */
    public function daftarOrder($f3) {
        $this->requireAuth();
        if (!$this->izinPengujian()) {
            return $this->json(['ok' => false, 'message' => 'PO Pengujian hanya bisa diakses Superadmin dan Tim Kerja Pengujian.'], 403);
        }
        $kendala = $this->kendalaSkema();
        if ($kendala !== '') {
            return $this->json(['ok' => false, 'message' => $kendala], 500);
        }
        $q = trim((string) $f3->get('GET.q'));
        $q = function_exists('mb_substr') ? mb_substr($q, 0, 100) : substr($q, 0, 100);
        try {
            $poIni = (int) ($f3->get('GET.po') ?? 0); // PO yang sedang diedit: order miliknya ikut tampil
            if ((string) $f3->get('GET.ringkas') !== '') {
                return $this->json(['ok' => true, 'jenis' => \PoPengujian::ringkasJenis($this->db, $poIni)]);
            }
            $jenis = trim((string) $f3->get('GET.jenis'));
            $rows = \PoPengujian::queryOrder($this->db, 0, $q, true, self::BATAS_ORDER, true, null, $poIni, $jenis);
            $data = array_map(['PoPengujian', 'petakanOrder'], $rows);
            return $this->json(['ok' => true, 'data' => $data, 'batas' => self::BATAS_ORDER, 'terpotong' => count($rows) >= self::BATAS_ORDER]);
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal baca daftar order: ' . $e->getMessage());
            return $this->json(['ok' => false, 'message' => 'Gagal membaca data order dari tb_arsipsurat dan tb_penerimaan_contoh (database sil2020). Pastikan tabelnya ada dan user MySQL punya akses baca.'], 500);
        }
    }

    /** GET /petunjuk-operasional/pengujian/create?order=ID */
    public function create($f3) {
        if (!$this->wajibIzinHalaman($f3)) { return; }
        $this->kendalaSkema();

        $ids = \PoPengujian::idDariCsv($f3->get('GET.order'));
        if (!$ids) {
            // PO Pengujian selalu dibuat dari order: kembali ke Daftar PO dan buka modal pilih order
            $f3->reroute('/petunjuk-operasional?layanan=pengujian&buat=1');
            return;
        }

        try {
            list($orders, $err, $sudah) = $this->muatOrder($ids, 0);
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal baca order: ' . $e->getMessage());
            $orders = []; $err = 'Gagal membaca order.'; $sudah = 0;
        }
        if ($err !== null) {
            if ($sudah > 0 && count($ids) === 1) {
                // order ini sudah punya PO -> langsung ke halaman edit PO tersebut
                $f3->reroute('/petunjuk-operasional/pengujian/' . $sudah . '/edit');
                return;
            }
            $this->setFlashError($err . ' Pilih ulang order Pengujian.');
            $f3->reroute('/petunjuk-operasional?layanan=pengujian&buat=1');
            return;
        }

        $this->setVarView($f3, null, false, $orders);

        $this->render(
            'petunjuk_operasional/po_pengujian/create.html',
            'Buat PO Pengujian',
            'po_pengujian'
        );
    }

    /**
     * Baca & periksa order-order (urutan sesuai $ids). $poIni = PO yang sedang diedit (order miliknya dianggap bebas).
     * Return [orders, pesanError|null, idPoPemakai]. Kegagalan karena order sudah dipakai PO lain: idPoPemakai > 0.
     */
    protected function muatOrder(array $ids, $poIni = 0) {
        $rows = \PoPengujian::queryOrder($this->db, 0, '', false, max(1, count($ids)), true, $ids);
        $by = [];
        foreach ($rows as $r) { $o = \PoPengujian::petakanOrder($r); $by[$o['id']] = $o; }
        // PO yang sedang diedit: order miliknya tetap sah walau (misalnya PO lama) tidak memenuhi syarat daftar order yang baru
        if ($poIni > 0) {
            $hilang = [];
            foreach ($ids as $id) { if (!isset($by[$id]) && \PoPengujian::poDariOrder($this->db, $id) === (int) $poIni) { $hilang[] = $id; } }
            if ($hilang) {
                foreach (\PoPengujian::queryOrder($this->db, 0, '', false, count($hilang), false, $hilang) as $r) { $o = \PoPengujian::petakanOrder($r); $by[$o['id']] = $o; }
            }
        }
        $out = [];
        foreach ($ids as $id) {
            if (!isset($by[$id])) { return [[], 'Ada order yang tidak ditemukan atau bukan order Pengujian.', 0]; }
            $out[] = $by[$id];
        }
        foreach ($out as $o) {
            $po = \PoPengujian::poDariOrder($this->db, $o['id']);
            if ($po > 0 && $po !== (int) $poIni) {
                $nm = $o['mitra'] !== '' ? $o['mitra'] : ($o['nomor_surat'] !== '' ? $o['nomor_surat'] : ('#' . $o['id']));
                return [$out, 'Order "' . $nm . '" sudah dibuatkan PO Pengujian.', $po];
            }
        }
        return [$out, null, 0];
    }

    /** Order -> 4 kunci snapshot yang disimpan di po_kegiatan.daftar_order. */
    protected function itemSnapshot(array $orders) {
        $out = [];
        foreach ($orders as $o) {
            $out[] = ['id' => (int) $o['id'], 'mitra' => (string) $o['mitra'], 'nomor_surat' => (string) $o['nomor_surat'], 'tanggal_surat' => (string) $o['tanggal_surat'], 'alamat' => (string) ($o['alamat'] ?? '')];
        }
        return $out;
    }

    /** Id order milik PO yang sudah tersimpan (snapshot; cadangan: id_arsip_surat PO lama). */
    protected function idOrderPo(array $po) {
        $v = !empty($po['daftar_order']) ? json_decode((string) $po['daftar_order'], true) : null;
        $ids = [];
        if (is_array($v) && !empty($v['items']) && is_array($v['items'])) {
            foreach ($v['items'] as $it) { if (!empty($it['id'])) { $ids[] = (int) $it['id']; } }
        }
        if (!$ids && (int) ($po['id_arsip_surat'] ?? 0) > 0) { $ids[] = (int) $po['id_arsip_surat']; }
        return $ids;
    }

    /** Order asal PO tersimpan untuk banner form: dibaca ulang dari database (nama mitra terkini); cadangan snapshot. */
    protected function orderPoTersimpan(array $po) {
        $ids = $this->idOrderPo($po);
        if (!$ids) { return []; }
        $by = [];
        try {
            foreach (\PoPengujian::queryOrder($this->db, 0, '', false, max(1, count($ids)), false, $ids) as $r) {
                $o = \PoPengujian::petakanOrder($r); $by[$o['id']] = $o;
            }
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal baca order PO: ' . $e->getMessage());
        }
        $snap = [];
        $v = !empty($po['daftar_order']) ? json_decode((string) $po['daftar_order'], true) : null;
        if (is_array($v) && !empty($v['items'])) { foreach ($v['items'] as $it) { if (!empty($it['id'])) { $snap[(int) $it['id']] = $it; } } }
        $out = [];
        foreach ($ids as $id) {
            if (isset($by[$id])) { $out[] = $by[$id]; }
            elseif (isset($snap[$id])) { $out[] = ['id' => $id, 'nomor_surat' => (string) ($snap[$id]['nomor_surat'] ?? ''), 'perihal' => '', 'mitra' => (string) ($snap[$id]['mitra'] ?? ''), 'alamat' => '', 'tanggal_surat' => (string) ($snap[$id]['tanggal_surat'] ?? '')]; }
        }
        return $out;
    }

    /** GET /petunjuk-operasional/pengujian/@id/edit */
    public function edit($f3, $params) {
        return $this->tampilkan($f3, $params, false);
    }

    /** GET /petunjuk-operasional/pengujian/@id/lihat -- hanya preview dokumen PO (ikon mata di Daftar PO). */
    public function lihat($f3, $params) {
        return $this->tampilkan($f3, $params, true);
    }

    protected function tampilkan($f3, $params, $lihat) {
        if (!$this->wajibIzinHalaman($f3)) { return; }

        $po = null;
        if ($this->kendalaSkema() === '') {
            try {
                $po = \PoPengujian::ambil($this->db, (int) $params['id']);
            } catch (\Throwable $e) {
                error_log('[PO Pengujian] gagal baca PO: ' . $e->getMessage());
            }
        }
        if (!$po) {
            $this->setFlashError('PO Pengujian tidak ditemukan.');
            $f3->reroute('/petunjuk-operasional');
            return;
        }

        if (!$lihat && !in_array((string) ($po['status'] ?? ''), ['', 'draft'], true)) {
            // PO yang sudah dikirim hanya bisa dilihat (edit lagi hanya lewat permintaan revisi)
            $this->setFlashWarning('PO yang sudah terkirim tidak bisa diedit lagi.');
            $f3->reroute('/petunjuk-operasional/pengujian/' . (int) $po['id'] . '/lihat');
            return;
        }

        $poInfo = [
            'id'         => (int) $po['id'],
            'nomor'      => (string) ($po['nomor_po'] ?? ''),
            'tanggal_po' => (string) $this->tanggalPoTersimpan($po),
            'dibuat'     => !empty($po['created_at']) && strtotime((string) $po['created_at']) ? date('d/m/Y', strtotime((string) $po['created_at'])) : '',
            'judul'      => (string) ($po['judul_kegiatan'] ?? ''),
            'status'     => (string) ($po['status'] ?? ''),
            'data'       => \PoPengujian::formDariBaris($po),
        ];
        // order asal PO (PO Pengujian lama yang dibuat sebelum ada pilih order tidak punya id_arsip_surat -> tanpa banner)
        $order = $this->orderPoTersimpan($po);
        $this->setVarView($f3, $poInfo, $lihat, $order);

        $this->render(
            'petunjuk_operasional/po_pengujian/create.html',
            $lihat ? 'Preview PO Pengujian' : 'Edit PO Pengujian',
            'po_pengujian'
        );
    }

    /* ------------------------------------------------------------------ */
    /* Validasi                                                           */
    /* ------------------------------------------------------------------ */

    /** Daftar kekurangan isian (diisi bila aksi=kirim dan ada yang belum lengkap). */
    protected $errKirim = [];

    protected static function adaIsi($v) {
        return !is_array($v) && trim((string) $v) !== '';
    }

    protected static function angka($v) {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * Aturan Kirim (dicerminkan dengan validasi wajib di form). Draft tidak diperiksa. Nomor PO tidak diperiksa (otomatis).
     * @return string[] pesan kekurangan; kosong = lengkap
     */
    protected function kekuranganKirim(array $post, array $d) {
        $m = [];
        $isi = function ($v) { return self::adaIsi($v); };
        $rich = function ($v) { return \PoPengujian::teksPolos(is_array($v) ? '' : $v) !== ''; };

        if (!$isi($d['jenis'] ?? '')) { $m[] = 'Jenis pengujian belum diisi'; }
        if (!$isi($d['perusahaan'] ?? '')) { $m[] = 'Nama perusahaan / mitra belum diisi'; }
        if (!$isi($d['lokasi'] ?? '')) { $m[] = 'Lokasi belum diisi'; }
        $banyakOrder = isset($d['orders']) && is_array($d['orders']) && count($d['orders']) > 1;
        if (!$banyakOrder && !$isi($d['noPenawaran'] ?? '')) { $m[] = 'No. penawaran biaya pengujian belum diisi'; }

        foreach (['dasar' => 'Dasar', 'tujuan' => 'Tujuan Kegiatan', 'ruang' => 'Ruang Lingkup'] as $k => $lbl) {
            if (!$rich($d[$k] ?? '')) { $m[] = $lbl . ' belum diisi'; }
        }

        $tim = (isset($d['tim']) && is_array($d['tim'])) ? array_values($d['tim']) : [];
        if (!$tim) { $m[] = 'Tim Pelaksana belum ada personil'; }
        foreach (['nama' => 'nama personil', 'tugas' => 'tugas / tanggung jawab'] as $k => $lbl) {
            $bad = [];
            foreach ($tim as $i => $t) { if (!$isi(is_array($t) ? ($t[$k] ?? '') : '')) { $bad[] = $i + 1; } }
            if ($bad) { $m[] = 'Tim Pelaksana: ' . $lbl . ' belum diisi (baris ' . implode(', ', $bad) . ')'; }
        }

        $rab = (isset($d['rab']) && is_array($d['rab'])) ? $d['rab'] : [];
        $terima = self::angka($rab['pengujian'] ?? 0) + self::angka($rab['jasa'] ?? 0) + self::angka($rab['transport'] ?? 0);
        if ($terima <= 0) { $m[] = 'RAB: nilai penerimaan belum diisi'; }
        $induk = (isset($rab['induk']) && is_array($rab['induk'])) ? array_values($rab['induk']) : [];
        if (!$induk) { $m[] = 'RAB: rencana pengeluaran belum ada kategori'; }
        foreach ($induk as $i => $x) {
            if (!$isi(is_array($x) ? ($x['nama'] ?? '') : '')) { $m[] = 'RAB: nama kategori pengeluaran ke-' . ($i + 1) . ' belum diisi'; }
            if (is_array($x) && ($x['tipe'] ?? '') === 'perjalanan' && empty($x['trip'])) { $m[] = 'RAB: kategori perjalanan pengambilan contoh ke-' . ($i + 1) . ' belum punya perjalanan'; }
        }

        $est = \PoPengujian::estimasiNormal($d['estimasi'] ?? []);
        if ($est['template'] === 'w') {
            $adaKeg = false;
            foreach ($est['w']['rows'] as $rw) { if ($isi($rw['kegiatan'])) { $adaKeg = true; break; } }
            if (!$adaKeg) { $m[] = 'Estimasi pengambilan contoh: belum ada kegiatan yang diisi'; }
        } elseif (\PoPengujian::teksPolos($est['ketHtml']) === '') {
            $m[] = 'Estimasi pengambilan contoh: keterangan (nama personel) belum diisi';
        }

        $jd = (isset($d['jadwal']) && is_array($d['jadwal'])) ? $d['jadwal'] : [];
        if (!$isi($jd['mulai'] ?? '') || !$isi($jd['selesai'] ?? '')) { $m[] = 'Jadwal: tanggal mulai / selesai belum diisi'; }
        $rows = (isset($jd['rows']) && is_array($jd['rows'])) ? $jd['rows'] : [];
        if (!$rows) { $m[] = 'Jadwal belum punya kegiatan'; }
        else {
            $tanpaNama = false; $tanpaCentang = false;
            foreach ($rows as $r) {
                $r = is_array($r) ? $r : [];
                if (!$isi($r['nama'] ?? '')) { $tanpaNama = true; }
                $tanda = (isset($r['tanda']) && is_array($r['tanda'])) ? $r['tanda'] : [];
                if (!array_filter($tanda)) { $tanpaCentang = true; }
            }
            if ($tanpaNama) { $m[] = 'Jadwal: nama kegiatan belum diisi'; }
            if ($tanpaCentang) { $m[] = 'Jadwal: ada kegiatan yang belum dicentang tanggalnya'; }
        }

        // jadwal pengambilan contoh per perusahaan (opsional): bila dipilih tampil, tanggal & perusahaan tiap regu wajib terisi
        $smp = (isset($d['sampling']) && is_array($d['sampling'])) ? $d['sampling'] : [];
        if ($banyakOrder && ($smp['mode'] ?? 'off') === 'tabel') {
            $hari = (isset($smp['hari']) && is_array($smp['hari'])) ? $smp['hari'] : [];
            if (!$hari) { $m[] = 'Jadwal pengambilan contoh: belum ada tanggal (atau pilih "Tidak ditampilkan")'; }
            $tanpaTgl = false; $tanpaOrder = false;
            foreach ($hari as $h) {
                if (!$isi($h['tgl'] ?? '')) { $tanpaTgl = true; }
                foreach ((isset($h['regu']) && is_array($h['regu'])) ? $h['regu'] : [] as $r) {
                    if (empty($r['orders'])) { $tanpaOrder = true; }
                }
            }
            if ($tanpaTgl) { $m[] = 'Jadwal pengambilan contoh: ada tanggal yang belum diisi'; }
            if ($tanpaOrder) { $m[] = 'Jadwal pengambilan contoh: ada regu yang belum memilih perusahaan'; }
        }

        foreach (\PoTtd::kekurangan(\PoTtd::dariLama(isset($d['ttd']) ? $d['ttd'] : [])) as $k) { $m[] = $k; }
        return $m;
    }

    /**
     * Baca & periksa field form. Return [pesanError|null, in|null, S].
     *   S  = data PO dari field form (selalu ada; dipakai menampilkan ulang form bila ditolak)
     *   in = ['tanggal_po', 'judul', 'kolom' => nilai kolom po_kegiatan]
     * Daftar kekurangan untuk aksi Kirim ada di $this->errKirim.
     * $tglTetap = tanggal PO yang sudah tercatat (dibuat saat PO pertama kali disimpan, tidak berubah lagi). Kosong = PO
     * baru: hari ini (WIB) menjadi tanggal PO. Tanggal PO TIDAK diisi dari form.
     */
    protected function bacaInput($f3, $tglTetap = null, array $orders = null) {
        $post = $f3->get('POST');
        $post = is_array($post) ? $post : [];
        $post['tanggal_po'] = ($tglTetap !== null && $tglTetap !== '') ? (string) $tglTetap : \PoSchema::hariIniWib();
        $S = \PoPengujianDokumen::dariPost($post);
        if ($orders !== null) { $S['orders'] = $orders; } // daftar order yang SUDAH diperiksa di database (bukan kiriman form)

        $this->errKirim = [];
        if (((string) ($post['aksi'] ?? '')) === 'kirim') {
            $kurang = $this->kekuranganKirim($post, $S);
            if ($kurang) {
                $this->errKirim = $kurang;
                return ['Data PO belum lengkap, lengkapi dulu isian yang wajib.', null, $S];
            }
        }

        $tgl = (string) $S['tanggal'];
        if ($tgl === '') {
            return ['Tanggal PO tidak valid.', null, $S];
        }
        $judul = (string) $S['judul'];
        if ($judul === '') {
            return ['Judul kegiatan wajib diisi.', null, $S];
        }
        $judul = function_exists('mb_substr') ? mb_substr($judul, 0, 255) : substr($judul, 0, 255);
        $S['judul'] = $judul;

        return [null, [
            'tanggal_po' => $tgl,
            'judul'      => $judul,
            'kolom'      => \PoPengujian::kolomDariForm($S),
        ], $S];
    }

    /**
     * Server menolak isian: tampilkan form lagi dengan isian yang sama + popup pesan (tidak ada isian yang hilang).
     * $po = baris PO yang sedang diedit (null = PO baru).
     */
    protected function tampilUlang($f3, array $S, $po, $order, $err) {
        if ($this->jsonMode) {
            // permintaan AJAX (dialog Generate Nomor PO): balas JSON, bukan halaman form
            $this->json([
                'ok'      => false,
                'judul'   => $this->errKirim ? 'Ada Isian yang Belum Lengkap' : 'PO Belum Bisa Disimpan',
                'message' => is_array($err) ? implode(' ', $err) : (string) $err,
                'errors'  => array_values($this->errKirim),
            ]);
            return;
        }
        $poInfo = null;
        if ($po) {
            $poInfo = [
                'id'         => (int) $po['id'],
                'nomor'      => (string) ($po['nomor_po'] ?? ''),
                'tanggal_po' => (string) ($S['tanggal'] ?? ''),
                'dibuat'     => !empty($po['created_at']) && strtotime((string) $po['created_at']) ? date('d/m/Y', strtotime((string) $po['created_at'])) : '',
                'judul'      => (string) ($S['judul'] ?? ''),
                'status'     => (string) ($po['status'] ?? ''),
                'data'       => $S,
            ];
        } else {
            $poInfo = ['id' => 0, 'nomor' => '', 'tanggal_po' => (string) ($S['tanggal'] ?? ''), 'judul' => (string) ($S['judul'] ?? ''), 'status' => '', 'data' => $S];
        }
        $pesan = ['judul' => $this->errKirim ? 'Ada Isian yang Belum Lengkap' : 'PO Belum Bisa Disimpan', 'teks' => $err, 'daftar' => array_values($this->errKirim)];
        $this->setVarView($f3, $poInfo, false, $order, $pesan);
        $this->render(
            'petunjuk_operasional/po_pengujian/create.html',
            $po ? 'Edit PO Pengujian' : 'Buat PO Pengujian',
            'po_pengujian'
        );
    }

    /* ------------------------------------------------------------------ */
    /* Simpan                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * POST /petunjuk-operasional/pengujian/simpan  (form biasa; sukses -> Daftar PO)
     * Nomor PO diterbitkan dari po_nomor_urut dan baris po_kegiatan dibuat dalam satu transaksi
     * (gagal simpan -> nomor tidak terbuang).
     */
    public function simpan($f3) {
        $this->siapkanBalasanJson($f3);
        if (!$this->wajibIzinHalaman($f3)) { return; }
        $kendala = $this->kendalaSkema();
        if ($kendala !== '') {
            $this->setFlashError($kendala);
            $f3->reroute($this->pathIndex());
            return;
        }

        // PO Pengujian wajib berasal dari order (dipilih di modal; boleh banyak): diperiksa ulang di server
        $ids = \PoPengujian::idDariCsv($f3->get('POST.order_ids'));
        if (!$ids) { $ids = \PoPengujian::idDariCsv($f3->get('POST.order_id')); }
        if (!$ids) {
            $this->setFlashError('Order belum dipilih. Pilih order Pengujian dari Daftar PO.');
            $f3->reroute('/petunjuk-operasional?layanan=pengujian&buat=1');
            return;
        }
        try {
            list($order, $errOrder, $sudah) = $this->muatOrder($ids, 0);
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal memeriksa order: ' . $e->getMessage());
            $this->setFlashError('Gagal memeriksa order: ' . $e->getMessage());
            $f3->reroute($this->pathIndex());
            return;
        }
        if ($errOrder !== null) {
            $this->setFlashError($errOrder . ($sudah > 0 ? ' Silakan edit PO yang ada, atau keluarkan order itu dari daftar.' : ' Pilih ulang order dari Daftar PO.'));
            $f3->reroute($sudah > 0 && count($ids) === 1 ? '/petunjuk-operasional/pengujian/' . $sudah . '/edit' : '/petunjuk-operasional?layanan=pengujian&buat=1');
            return;
        }

        try {
            list($err, $in, $S) = $this->bacaInput($f3, null, $order);
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal membaca input: ' . $e->getMessage());
            $this->setFlashError('Gagal memeriksa data PO: ' . $e->getMessage());
            $f3->reroute($this->pathIndex());
            return;
        }
        if ($err !== null) {
            $this->tampilUlang($f3, $S, null, $order, $err);
            return;
        }
        $status = $this->statusDariAksi($f3);

        try {
            \PoSchema::pastikanTabelNomor($this->db); // DDL harus di luar transaksi
            $this->db->begin();
            try {
                // [BARU] nomor diterbitkan hanya saat PO DIKIRIM (bulan/tahun = tanggal kirim, WIB); draft belum bernomor
                $nomor = ($status === 'terkirim')
                    ? \PoPengujian::nomorBaru($this->db, \PoSchema::hariIniWib())
                    : ['id' => null, 'detail_nomor' => null];
                $now = date('Y-m-d H:i:s');
                $poId = \PoSchema::sisipPo($this->db, array_merge($in['kolom'], [
                    'order_id'       => null, // sengaja kosong: order Pengujian disimpan di id_arsip_surat (bukan id order_layanan OPTI)
                    'id_arsip_surat' => $ids[0],
                    'layanan'        => \PoPengujian::LAYANAN,
                    'nomor_po'       => $nomor['detail_nomor'],
                    'id_nomor_urut'  => $nomor['id'],
                    'tanggal_po'     => $in['tanggal_po'],
                    'judul_kegiatan' => $in['judul'],
                    'status'         => $status,
                    'created_by'     => (int) $this->getUserId(),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]));
                if ($poId <= 0) {
                    throw new \Exception('PO gagal dibuat.');
                }
                \PoPengujian::tulisOrderPo($this->db, $poId, $ids); // gagal bila ada order yang keburu dipakai PO lain -> semua dibatalkan
                $this->db->commit();
            } catch (\Throwable $e) {
                try { $this->db->rollback(); } catch (\Throwable $eRb) {}
                throw $e;
            }
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal menyimpan PO: ' . $e->getMessage());
            $this->errKirim = [];
            $this->tampilUlang($f3, $S, null, $order, 'Gagal menyimpan PO: ' . $e->getMessage());
            return;
        }

        $kirim = ($status === 'terkirim');
        $this->setFlashSuccess(($kirim ? 'PO berhasil dibuat dan dikirim.' : 'PO berhasil disimpan sebagai draft.') . ($nomor['detail_nomor'] !== null ? ' Nomor PO: ' . $nomor['detail_nomor'] : ''));
        $this->berhasilAjax = true;
        $this->nomorAjax = (string) $nomor['detail_nomor'];
        $f3->reroute($this->pathSetelahSimpan($kirim ? 'terkirim' : 'draft'));
    }

    /**
     * POST /petunjuk-operasional/pengujian/@id/update  (form biasa; sukses -> Daftar PO)
     * Nomor PO TIDAK berubah saat diedit (sama seperti PO OPTI dan Sertifikasi).
     */
    public function update($f3, $params) {
        $this->siapkanBalasanJson($f3);
        if (!$this->wajibIzinHalaman($f3)) { return; }
        $kendala = $this->kendalaSkema();
        if ($kendala !== '') {
            $this->setFlashError($kendala);
            $f3->reroute($this->pathIndex());
            return;
        }

        try {
            $po = \PoPengujian::ambil($this->db, (int) $params['id']);
        } catch (\Throwable $e) {
            $po = null;
        }
        if (!$po) {
            $this->setFlashError('PO Pengujian tidak ditemukan.');
            $f3->reroute('/petunjuk-operasional');
            return;
        }
        $statusLama = (string) ($po['status'] ?? 'draft');
        if (!in_array($statusLama, ['', 'draft'], true)) {
            // PO yang sudah dikirim terkunci; perubahan hanya lewat permintaan revisi (Review)
            $this->setFlashError($statusLama === 'terkirim'
                ? 'PO ini sudah terkirim dan tidak bisa diedit lagi. Perubahan hanya bisa lewat permintaan revisi.'
                : 'PO ini sudah berstatus "' . $statusLama . '" dan tidak bisa diubah lagi.');
            $f3->reroute($this->pathIndex());
            return;
        }
        $status = $this->statusDariAksi($f3);

        // order dalam PO: daftar dari form (boleh diubah selama PO masih draft / terkirim); kosong -> pakai yang sudah tersimpan
        $ids = \PoPengujian::idDariCsv($f3->get('POST.order_ids'));
        if (!$ids) { $ids = $this->idOrderPo($po); }
        $order = [];
        if ($ids) {
            try {
                list($order, $errOrder, $sudah) = $this->muatOrder($ids, (int) $po['id']);
            } catch (\Throwable $e) {
                error_log('[PO Pengujian] gagal memeriksa order: ' . $e->getMessage());
                $this->setFlashError('Gagal memeriksa order: ' . $e->getMessage());
                $f3->reroute($this->pathIndex());
                return;
            }
            if ($errOrder !== null) {
                $this->setFlashError($errOrder . ' Perubahan belum disimpan.');
                $f3->reroute('/petunjuk-operasional/pengujian/' . (int) $po['id'] . '/edit');
                return;
            }
        } else {
            $order = $this->orderPoTersimpan($po);
        }

        try {
            // tanggal PO dibuat sekali saat PO pertama kali disimpan (draft / kirim) dan TETAP sesudahnya; nomor tetap setelah terbit
            $tglTetap = $this->tanggalPoTersimpan($po);
            list($err, $in, $S) = $this->bacaInput($f3, $tglTetap, $order ?: null);
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal membaca input: ' . $e->getMessage());
            $this->setFlashError('Gagal memeriksa data PO: ' . $e->getMessage());
            $f3->reroute($this->pathIndex());
            return;
        }
        if ($err !== null) {
            $this->tampilUlang($f3, $S, $po, $order, $err);
            return;
        }

        $nomorBaruTerbit = '';
        try {
            $ubah = array_merge($in['kolom'], [
                'tanggal_po'     => $in['tanggal_po'],
                'judul_kegiatan' => $in['judul'],
                'status'         => $status,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            if ($ids) { $ubah['id_arsip_surat'] = $ids[0]; }
            if (trim((string) ($po['nomor_po'] ?? '')) === '' && $status === 'terkirim') {
                // [BARU] draft dikirim untuk pertama kali -> nomor diterbitkan sekarang (bulan/tahun = hari kirim, WIB);
                // nomor + update PO satu transaksi supaya gagal simpan tidak membuang nomor
                \PoSchema::pastikanTabelNomor($this->db); // DDL harus di luar transaksi
                $this->db->begin();
                try {
                    $nomor = \PoPengujian::nomorBaru($this->db, \PoSchema::hariIniWib()); // bulan/tahun nomor = hari PO dikirim
                    $ubah['nomor_po']      = $nomor['detail_nomor'];
                    $ubah['id_nomor_urut'] = $nomor['id'];
                    \PoSchema::ubahPo($this->db, (int) $po['id'], $ubah);
                    if ($ids) { \PoPengujian::tulisOrderPo($this->db, (int) $po['id'], $ids); }
                    $this->db->commit();
                } catch (\Throwable $e) {
                    try { $this->db->rollback(); } catch (\Throwable $eRb) {}
                    throw $e;
                }
                $nomorBaruTerbit = $nomor['detail_nomor'];
            } else {
                $this->db->begin();
                try {
                    \PoSchema::ubahPo($this->db, (int) $po['id'], $ubah);
                    if ($ids) { \PoPengujian::tulisOrderPo($this->db, (int) $po['id'], $ids); }
                    $this->db->commit();
                } catch (\Throwable $e) {
                    try { $this->db->rollback(); } catch (\Throwable $eRb) {}
                    throw $e;
                }
            }
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal memperbarui PO: ' . $e->getMessage());
            $this->errKirim = [];
            $this->tampilUlang($f3, $S, $po, $order, 'Gagal memperbarui PO: ' . $e->getMessage());
            return;
        }

        $kirim = ($status === 'terkirim');
        $this->setFlashSuccess(($kirim ? 'PO berhasil diperbarui dan dikirim.' : 'PO berhasil diperbarui sebagai draft.') . ($nomorBaruTerbit !== '' ? ' Nomor PO: ' . $nomorBaruTerbit : ''));
        $this->berhasilAjax = true;
        $this->nomorAjax = (string) $nomorBaruTerbit;
        $f3->reroute($this->pathSetelahSimpan($kirim ? 'terkirim' : 'draft'));
    }

    /* ------------------------------------------------------------------ */
    /* Preview langsung, PDF, kolom jadwal                                */
    /* ------------------------------------------------------------------ */

    /**
     * Tanggal dokumen untuk pratinjau/PDF: PO yang sudah bernomor memakai tanggalnya sendiri; selain itu hari ini (WIB).
     * Dimasukkan ke POST['tanggal_po'] sebelum dariPost() (form tidak lagi punya kolom tanggal).
     */
    protected function postDenganTanggal($f3) {
        $post = $f3->get('POST');
        $post = is_array($post) ? $post : [];
        $post['tanggal_po'] = \PoSchema::hariIniWib();
        $poId = (int) ($post['po_id'] ?? 0);
        if ($poId > 0) {
            try {
                $po = \PoPengujian::ambil($this->db, $poId);
                $tgl = $this->tanggalPoTersimpan($po);
                if ($tgl !== null) {
                    $post['tanggal_po'] = $tgl;
                }
            } catch (\Throwable $e) {
            }
        }
        return $post;
    }

    /** Nomor PO tersimpan milik po_id (kosong untuk PO baru / po_id tidak dikenal). */
    protected function nomorTersimpan($f3) {
        $poId = (int) ($f3->get('POST.po_id') ?? 0);
        if ($poId <= 0) { return ''; }
        try {
            $po = \PoPengujian::ambil($this->db, $poId);
        } catch (\Throwable $e) {
            return '';
        }
        return $po ? (string) ($po['nomor_po'] ?? '') : '';
    }

    /** Baris PO Pengujian tersimpan untuk pratinjau/cetak dari Daftar PO; null bila tidak ada / skema belum siap. */
    protected function poUntukPratinjau($id) {
        if ($this->kendalaSkema() !== '') { return null; }
        try {
            return \PoPengujian::ambil($this->db, (int) $id);
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal baca PO untuk pratinjau: ' . $e->getMessage());
            return null;
        }
    }

    /** GET /petunjuk-operasional/pengujian/@id/preview -- fragmen HTML dokumen PO tersimpan, untuk modal pratinjau di Daftar PO. */
    public function previewTersimpan($f3, $params) {
        $this->requireAuth();
        if (!$this->izinPengujian()) {
            http_response_code(403);
            echo '<div class="text-center text-danger py-5">Anda tidak berhak melihat PO Pengujian.</div>';
            return;
        }
        $po = $this->poUntukPratinjau($params['id']);
        if (!$po) {
            http_response_code(404);
            echo '<div class="text-center text-danger py-5">PO tidak ditemukan.</div>';
            return;
        }
        try {
            $S = \PoPengujianDokumen::dariBaris($po);
            echo \PoPengujianDokumen::fragmen($S, ['nomor' => (string) ($po['nomor_po'] ?? ''), 'libur' => $this->daftarLibur()]);
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal menyiapkan pratinjau tersimpan: ' . $e->getMessage());
            http_response_code(500);
            echo '<div class="text-center text-danger py-5">Gagal memuat data PO.</div>';
        }
    }

    /** GET /petunjuk-operasional/pengujian/@id/cetak -- PDF PO tersimpan (tombol Download PDF di modal pratinjau). */
    public function cetakTersimpan($f3, $params) {
        $this->requireAuth();
        if (!$this->izinPengujian()) {
            http_response_code(403);
            echo 'Anda tidak berhak mencetak PO Pengujian.';
            return;
        }
        if (!$this->adaDompdf()) {
            echo 'Gagal mencetak PDF: library Dompdf belum terpasang.';
            return;
        }
        $po = $this->poUntukPratinjau($params['id']);
        if (!$po) {
            http_response_code(404);
            echo 'PO tidak ditemukan.';
            return;
        }
        try {
            $nomor = (string) ($po['nomor_po'] ?? '');
            $S = \PoPengujianDokumen::dariBaris($po);
            $html = \PoPengujianDokumen::htmlPdf($S, ['nomor' => $nomor, 'libur' => $this->daftarLibur()]);

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Times-Roman');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $namaFile = ($nomor !== '' ? 'PO-' : 'Draft-PO-') . preg_replace('/[^A-Za-z0-9_-]/', '_', $nomor !== '' ? $nomor : (string) (int) $po['id']) . '.pdf';
            $dompdf->stream($namaFile, ['Attachment' => false]);
            exit;
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal mencetak PDF tersimpan: ' . $e->getMessage());
            echo 'Gagal mencetak PDF: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    /** POST /petunjuk-operasional/pengujian/preview-live -- fragmen HTML dokumen dari isi form saat ini (tanpa simpan). */
    public function previewLive($f3) {
        $this->requireAuth();
        if (!$this->izinPengujian()) {
            http_response_code(403);
            echo '<div class="text-white text-center py-4">Anda tidak berhak melihat PO Pengujian.</div>';
            return;
        }
        try {
            $S = \PoPengujianDokumen::dariPost($this->postDenganTanggal($f3));
            echo \PoPengujianDokumen::fragmen($S, ['nomor' => $this->nomorTersimpan($f3), 'libur' => $this->daftarLibur()]);
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal menyiapkan pratinjau: ' . $e->getMessage());
            http_response_code(500);
            echo '<div class="text-white text-center py-4"><p class="text-danger fw-bold mb-1">Gagal Menyiapkan Pratinjau</p><small class="text-white-50">' . htmlspecialchars($e->getMessage()) . '</small></div>';
        }
    }

    /** POST /petunjuk-operasional/pengujian/cetak-live -- PDF dari isi form saat ini (tanpa simpan), dibuka di tab baru. */
    public function cetakLive($f3) {
        $this->requireAuth();
        if (!$this->izinPengujian()) {
            http_response_code(403);
            echo 'Anda tidak berhak mencetak PO Pengujian.';
            return;
        }
        if (!$this->adaDompdf()) {
            echo 'Gagal mencetak PDF: library Dompdf belum terpasang.';
            return;
        }
        try {
            $S = \PoPengujianDokumen::dariPost($this->postDenganTanggal($f3));
            $nomor = $this->nomorTersimpan($f3);
            $html = \PoPengujianDokumen::htmlPdf($S, ['nomor' => $nomor, 'libur' => $this->daftarLibur()]);

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Times-Roman');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $namaFile = ($nomor !== '' ? 'PO-' : 'Preview-PO-') . preg_replace('/[^A-Za-z0-9_-]/', '_', $nomor !== '' ? $nomor : 'Draft') . '.pdf';
            $dompdf->stream($namaFile, ['Attachment' => false]);
            exit;
        } catch (\Throwable $e) {
            error_log('[PO Pengujian] gagal mencetak PDF: ' . $e->getMessage());
            echo 'Gagal mencetak PDF: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    /** GET /petunjuk-operasional/pengujian/jadwal-kolom?mulai=YYYY-MM-DD&selesai=YYYY-MM-DD (JSON, sama dengan jadwal PO OPTI) */
    public function jadwalKolom($f3) {
        $this->requireAuth();
        if (!$this->izinPengujian()) {
            return $this->json(['mode' => null, 'kolom' => [], 'bulan_header' => []], 403);
        }
        $hasil = \PoPengujianDokumen::kolomJadwal((string) ($f3->get('GET.mulai') ?? ''), (string) ($f3->get('GET.selesai') ?? ''), $this->daftarLibur());
        return $this->json($hasil);
    }

    /**
     * GET /petunjuk-operasional/pengujian/review
     * Review PO Pengujian. Sementara halaman penanda (alur review menyusul). Akses: Superadmin dan Tim Kerja Pengujian.
     */
    public function review($f3) {
        $this->requireAuth();
        $role = $this->getUserRole();
        if ($role !== 'superadmin' && $role !== 'tim_kerja_pengujian') {
            $f3->error(403, 'Halaman ini hanya untuk Tim Kerja Pengujian.');
            return;
        }

        $this->render(
            'petunjuk_operasional/po_pengujian/review.html',
            'Review PO Pengujian',
            'review_po_pengujian'
        );
    }
}
