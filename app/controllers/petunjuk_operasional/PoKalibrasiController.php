<?php
namespace petunjuk_operasional;

/**
 * PoKalibrasiController -- Petunjuk Operasional (PO) jenis KALIBRASI.
 *
 * Sengaja DIPISAH dari modul PO OPTI (PoKegiatanController dkk): beda folder
 * (app/controllers/petunjuk_operasional/), beda namespace, beda folder view
 * (app/views/petunjuk_operasional/po_kalibrasi/), beda URL (/petunjuk-operasional/...).
 *
 * ALUR:
 *   0. Daftar PO -> "Tambah PO" -> kartu Kalibrasi di /po-kegiatan/pilih -> modal "Pilih Order Kalibrasi"
 *      (daftar order di kiri, rincian order terpilih di kanan) -> tombol "Buat PO dari order ini" ->
 *      /petunjuk-operasional/kalibrasi/create?order=<tb_arsip_kalibrasi.id_arsip_kalibrasi>.
 *      Daftar order: GET /petunjuk-operasional/kalibrasi/order?q=... (JSON). Satu order = satu PO (tidak ada pilih banyak).
 *   1. /petunjuk-operasional/kalibrasi/create?order=ID -> form PO; mitra, daftar alat, tim pelaksana (teknisi),
 *      RAB dan jadwal terisi awal dari order (semuanya masih bisa diubah).
 *      Tanpa ?order= / order tidak ditemukan -> dialihkan ke Daftar PO dan modal pilih order terbuka.
 *
 * Sumber data & aturan baca skema: lihat model PoKalibrasi (app/models/PoKalibrasi.php).
 * Akses: Superadmin (role khusus Kalibrasi belum ada).
 *
 *   2. Simpan: tombol "Simpan sebagai Draft" -> status 'draft' (belum bernomor); "Kirim" -> status 'terkirim' dan Nomor PO
 *      diterbitkan SEKALI (dialog "Generate Nomor PO") dari tabel po_nomor_urut -- satu deret per tahun bareng PO OPTI,
 *      Sertifikasi, dst., format "N/PO/BBSPJIS/ROMAWI/TAHUN", sehingga nomor tidak pernah dobel/bentrok.
 *      Datanya disimpan di tabel po_kegiatan (sama seperti PO OPTI & Sertifikasi) dengan layanan = 'kalibrasi',
 *      order_id = id_arsip_kalibrasi (kolom id_arsip_kalibrasi juga diisi). Isi form lengkap disimpan di kolom isi_form (JSON);
 *      kolom lain (judul_kegiatan, nama_perusahaan, alamat, no_penawaran, tanggal_penawaran, dasar, tujuan, ruang_lingkup,
 *      tim_pelaksana, rab, jadwal, ttd_json) ikut diisi. Satu order = satu PO; create untuk order yang sudah punya PO -> ke edit.
 *      PO terkirim hanya bisa dilihat (belum ada alur review Ketua Tim Kalibrasi).
 *
 * TAHAP SEKARANG: simpan draft / kirim + nomor otomatis jalan; alur review/validasi PO Kalibrasi belum ada.
 *
 * Kompatibel PHP 7.2.
 */
class PoKalibrasiController extends \Controller {

    /** Batas baris daftar order di modal (sisanya dicari lewat kolom cari). */
    const BATAS_ORDER = 300;

    /** Superadmin saja untuk sementara. */
    protected function izinKalibrasi() {
        return $this->isSuperadmin();
    }

    protected function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    protected function jsonHive($data) {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * GET /petunjuk-operasional/kalibrasi/order[?q=kata]
     * JSON daftar order kalibrasi (tb_arsip_kalibrasi + alat per order) untuk modal pilih order.
     */
    public function daftarOrder($f3) {
        $this->requireAuth();
        if (!$this->izinKalibrasi()) {
            return $this->json(['ok' => false, 'message' => 'PO Kalibrasi sementara hanya bisa diakses Superadmin.'], 403);
        }
        $kendala = \PoKalibrasi::kendala($this->db);
        if ($kendala !== '') {
            return $this->json(['ok' => false, 'message' => $kendala], 500);
        }
        if ((string) $f3->get('GET.debug') === '1') {
            // pemeriksaan skema (Superadmin saja): tabel alat/tarif yang terdeteksi + contoh baris
            try { return $this->json(['ok' => true, 'debug' => \PoKalibrasi::diagnosa($this->db)]); }
            catch (\Throwable $e) { return $this->json(['ok' => false, 'message' => $e->getMessage()], 500); }
        }
        $q = trim((string) $f3->get('GET.q'));
        $q = function_exists('mb_substr') ? mb_substr($q, 0, 100) : substr($q, 0, 100);
        try {
            $rows = \PoKalibrasi::daftar($this->db, 0, $q, self::BATAS_ORDER, (int) ($f3->get('GET.po') ?? 0));
            return $this->json([
                'ok'        => true,
                'data'      => $rows,
                'batas'     => self::BATAS_ORDER,
                'terpotong' => count($rows) >= self::BATAS_ORDER,
                'catatan'   => \PoKalibrasi::catatan(),
            ]);
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] gagal baca daftar order: ' . $e->getMessage());
            return $this->json(['ok' => false, 'message' => 'Gagal membaca data order kalibrasi (tb_arsip_kalibrasi). Pastikan tabelnya ada dan user MySQL punya akses baca.'], 500);
        }
    }

    /** GET /petunjuk-operasional/kalibrasi/create?order=ID */
    public function create($f3) {
        $this->requireAuth();
        if (!$this->izinKalibrasi()) {
            $this->setFlashError('PO Kalibrasi sementara hanya bisa diakses Superadmin.');
            $f3->reroute('/dashboard');
            return;
        }

        $idOrder = (int) $f3->get('GET.order');
        if ($idOrder <= 0) {
            // PO Kalibrasi selalu dibuat dari order: ke Daftar PO dan buka modal pilih order
            $f3->reroute('/petunjuk-operasional?layanan=kalibrasi&buat=1');
            return;
        }

        $order = null;
        try {
            $order = \PoKalibrasi::ambil($this->db, $idOrder);
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] gagal baca order: ' . $e->getMessage());
        }
        if ($order === null) {
            $this->setFlashError('Order kalibrasi tidak ditemukan atau sudah tidak tersedia. Pilih ulang order.');
            $f3->reroute('/petunjuk-operasional?layanan=kalibrasi&buat=1');
            return;
        }

        // satu order = satu PO: order yang sudah punya PO langsung dibuka di halaman edit PO-nya
        $this->kendalaSkema();
        try {
            $ada = $this->cariPoOrder($idOrder);
            if ($ada) {
                $f3->reroute('/petunjuk-operasional/kalibrasi/' . (int) $ada['id'] . '/edit');
                return;
            }
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] gagal cek PO yang sudah ada: ' . $e->getMessage());
        }

        $f3->set('order_json', $this->jsonHive($order));
        $f3->set('klaim_json', $this->jsonHive($this->klaimUntukView($idOrder)));
        $f3->set('po_json', 'null');
        $f3->set('mode_lihat', 'false');
        $f3->set('tanggal_default', date('Y-m-d'));
        $f3->set('csrf_po', (string) ($f3->get('csrf_token') ?? ''));

        $this->render(
            'petunjuk_operasional/po_kalibrasi/create.html',
            'Buat PO Kalibrasi',
            'po_kalibrasi'
        );
    }

    /* ================================================================
     * SIMPAN KE DATABASE (po_kegiatan, layanan = 'kalibrasi')
     * ================================================================ */

    protected static $kolomKal = array(
        'isi_form'           => 'LONGTEXT NULL',
        'id_arsip_kalibrasi' => 'INT NULL',
        'order_nomor'        => 'VARCHAR(150) NULL',
    );
    protected static $kolomKalSiap = false;

    /** Kolom khusus Kalibrasi di po_kegiatan (dibuat sekali bila belum ada; tidak menyentuh PO layanan lain). */
    protected function pastikanKolomKal() {
        if (self::$kolomKalSiap) { return ''; }
        self::$kolomKalSiap = true;
        try {
            $ada = array();
            foreach ($this->db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) { $ada[] = $c['Field']; }
            $kurang = array();
            foreach (self::$kolomKal as $nama => $def) {
                if (in_array($nama, $ada, true)) { continue; }
                try { $this->db->exec('ALTER TABLE po_kegiatan ADD COLUMN `' . $nama . '` ' . $def); }
                catch (\Throwable $e) { error_log('[PO Kalibrasi] ' . $e->getMessage()); $kurang[] = 'ADD COLUMN `' . $nama . '` ' . $def; }
            }
            if ($kurang) {
                return 'Kolom po_kegiatan untuk PO Kalibrasi belum berhasil dibuat otomatis. Cek hak ALTER TABLE user MySQL, atau jalankan manual: ALTER TABLE po_kegiatan ' . implode(', ', $kurang) . ';';
            }
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] ' . $e->getMessage());
            return 'Gagal memeriksa kolom po_kegiatan.';
        }
        return '';
    }

    /** Pastikan skema po_kegiatan siap; return pesan kendala ('' = aman). */
    protected function kendalaSkema() {
        \PoSchema::pastikan($this->db);
        \PoSchema::pastikanTanggalDikirim($this->db);
        $k = \PoSchema::kendalaSertifikasi();
        if ($k !== '') { return $k; }
        return $this->pastikanKolomKal();
    }

    protected function cariPoOrder($orderId) {
        $r = $this->db->exec(
            'SELECT * FROM po_kegiatan WHERE order_id = ? AND layanan = ? ORDER BY id DESC LIMIT 1',
            array(1 => (int) $orderId, 2 => \PoKalibrasi::LAYANAN)
        );
        return empty($r) ? null : $r[0];
    }

    protected function ambilPo($id) {
        $r = $this->db->exec(
            'SELECT * FROM po_kegiatan WHERE id = ? AND layanan = ? LIMIT 1',
            array(1 => (int) $id, 2 => \PoKalibrasi::LAYANAN)
        );
        return empty($r) ? null : $r[0];
    }

    protected function urlIndex($tab = 'draft') {
        $base = \Base::instance()->get('BASE');
        if ($this->isSuperadmin()) { return $base . '/po-kegiatan?tab=' . $tab; }
        return $base . '/petunjuk-operasional?layanan=kalibrasi&tab=' . $tab;
    }

    protected function urlEdit($poId) {
        return \Base::instance()->get('BASE') . '/petunjuk-operasional/kalibrasi/' . (int) $poId . '/edit';
    }

    protected function statusDariAksi($f3) {
        return ((string) $f3->get('POST.aksi')) === 'kirim' ? 'terkirim' : 'draft';
    }

    protected function tglDikirimTeks($po) {
        if (trim((string) ($po['nomor_po'] ?? '')) === '') { return ''; }
        $t = (string) ($po['tanggal_dikirim'] ?? '');
        if ($t === '' || strpos($t, '0000') === 0 || !strtotime($t)) { $t = (string) ($po['tanggal_po'] ?? ''); }
        return ($t !== '' && strpos($t, '0000') !== 0 && strtotime($t)) ? date('d/m/Y', strtotime($t)) : '';
    }

    /** Daftar kekurangan isian (diisi bila aksi=kirim dan ada yang belum lengkap). */
    protected $errKirim = array();
    /** id order yang sedang disimpan; dipakai kekuranganKirim() untuk memastikan surat penawaran sudah diklaim */
    protected $orderCek = 0;

    protected static function adaIsi($v) {
        return !is_array($v) && trim((string) $v) !== '';
    }

    protected static function teksRte($html) {
        $t = preg_replace('/<[^>]*>/', ' ', (string) $html);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace("\xC2\xA0", ' ', $t);
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    /**
     * Aturan Kirim (dicerminkan validasiKirim() di form): judul, mitra, surat penawaran, dasar/tujuan/ruang lingkup,
     * alat, nama tim, dan penandatangan wajib; ALAMAT boleh kosong. Draft tidak diperiksa.
     * @return string[]
     */
    protected function kekuranganKirim(array $d) {
        $m = array();
        $isi = function ($v) { return PoKalibrasiController::adaIsi($v); };
        if (!$isi($d['judul'] ?? '')) { $m[] = 'Judul Kegiatan belum diisi'; }
        if (!$isi($d['perusahaan'] ?? '')) { $m[] = 'Nama perusahaan / mitra belum diisi'; }
        if (!$isi($d['noPenawaran'] ?? '')) { $m[] = 'Nomor surat penawaran belum diisi'; }
        if (!$isi($d['tglPenawaran'] ?? '')) { $m[] = 'Tanggal surat penawaran belum diisi'; }
        if ($this->orderCek > 0) {
            $klaimAda = false;
            try { $klaimAda = ($this->suratKlaimOrder($this->orderCek) !== null); } catch (\Throwable $e) { error_log('[PO Kalibrasi] cek klaim surat: ' . $e->getMessage()); }
            if (!$klaimAda) { $m[] = 'Surat penawaran belum diambil dari arsip surat keluar (klik "Ambil dari Arsip")'; }
        }
        if (self::teksRte($d['dasar'] ?? '') === '') { $m[] = 'Dasar belum diisi'; }
        if (self::teksRte($d['tujuan'] ?? '') === '') { $m[] = 'Tujuan belum diisi'; }
        if (self::teksRte($d['ruang'] ?? '') === '') { $m[] = 'Ruang lingkup belum diisi'; }
        $alat = (isset($d['alat']) && is_array($d['alat'])) ? array_values($d['alat']) : array();
        if (!$alat) { $m[] = 'Daftar alat belum diisi'; }
        foreach ($alat as $a) {
            if (!is_array($a) || !$isi($a['nama'] ?? '')) { $m[] = 'Daftar alat: ada nama alat yang belum diisi'; break; }
        }
        $tim = (isset($d['tim']) && is_array($d['tim'])) ? array_values($d['tim']) : array();
        if (!$tim) { $m[] = 'Tim pelaksana belum diisi'; }
        foreach ($tim as $t) {
            if (!is_array($t) || !$isi($t['nama'] ?? '')) { $m[] = 'Tim pelaksana: ada nama anggota yang belum diisi'; break; }
        }
        foreach (\PoTtd::kekurangan(\PoTtd::bersihkan(isset($d['ttd']) ? $d['ttd'] : array())) as $pesan) {
            $m[] = $pesan;
        }
        return $m;
    }

    protected function tolakInput($err) {
        $out = array('ok' => false, 'message' => $err);
        if ($this->errKirim) { $out['errors'] = $this->errKirim; }
        return $this->json($out, 422);
    }

    protected static function teksOrNull($v, $maks = 0) {
        if (is_array($v) || $v === null) { return null; }
        $t = trim((string) $v);
        if ($t === '') { return null; }
        if ($maks > 0) { $t = function_exists('mb_substr') ? mb_substr($t, 0, $maks) : substr($t, 0, $maks); }
        return $t;
    }

    protected static function tglOrNull($v) {
        $t = trim((string) $v);
        $dt = \DateTime::createFromFormat('Y-m-d', $t);
        return ($dt && $dt->format('Y-m-d') === $t) ? $t : null;
    }

    protected static function jsonKol($v) {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * Validasi & rapikan input. Form mengirim seluruh isian sebagai satu JSON di 'isi_form'; di sini disimpan utuh
     * (kolom isi_form) dan dipecah ke kolom-kolom po_kegiatan. Return [pesanError|null, data].
     */
    protected function bacaInput($f3, $tglTetap = null) {
        $post = $f3->get('POST');
        $raw = (string) ($post['isi_form'] ?? '');
        if ($raw === '' || strlen($raw) > 3000000) { return array('Data PO kosong atau terlalu besar.', null); }
        $d = json_decode($raw, true);
        if (!is_array($d)) { return array('Data PO tidak valid.', null); }

        $this->errKirim = array();
        $judul = trim((string) ($post['judul_kegiatan'] ?? ''));
        $d['judul'] = $judul;
        if (((string) ($post['aksi'] ?? '')) === 'kirim') {
            $kurang = $this->kekuranganKirim($d);
            if ($kurang) {
                $this->errKirim = $kurang;
                return array('Data PO belum lengkap, semua isian wajib diisi kecuali alamat.', null);
            }
        }
        if ($judul === '') { return array('Judul Kegiatan wajib diisi.', null); }
        $judul = function_exists('mb_substr') ? mb_substr($judul, 0, 255) : substr($judul, 0, 255);

        // Tanggal PO = hari PO pertama kali disimpan (WIB), lalu TETAP; bulan/tahun Nomor PO = hari PO dikirim
        $tgl = ($tglTetap !== null && $tglTetap !== '') ? (string) $tglTetap : \PoSchema::hariIniWib();
        $d['tanggal'] = substr($tgl, 0, 7);
        $d['urut'] = '';

        $alamat = self::teksRte(isset($d['alamat']) ? $d['alamat'] : '') === '' ? null : trim((string) $d['alamat']);
        $kolom = array(
            'isi_form'          => self::jsonKol($d),
            'nama_perusahaan'   => self::teksOrNull($d['perusahaan'] ?? '', 255),
            'no_penawaran'      => self::teksOrNull($d['noPenawaran'] ?? '', 150),
            'tanggal_penawaran' => self::tglOrNull($d['tglPenawaran'] ?? ''),
            'dasar'             => self::teksOrNull($d['dasar'] ?? ''),
            'tujuan'            => self::teksOrNull($d['tujuan'] ?? ''),
            'ruang_lingkup'     => self::jsonKol(array('teks' => isset($d['ruang']) ? $d['ruang'] : '', 'alat' => isset($d['alat']) ? $d['alat'] : array())),
            'tim_pelaksana'     => self::jsonKol(isset($d['tim']) ? $d['tim'] : array()),
            'rab'               => self::jsonKol(isset($d['rab']) ? $d['rab'] : array()),
            'jadwal'            => self::jsonKol(isset($d['jadwal']) ? $d['jadwal'] : array()),
            'ttd_json'          => self::jsonKol(\PoTtd::bersihkan(isset($d['ttd']) ? $d['ttd'] : array())),
        );
        return array(null, array('tanggal_po' => $tgl, 'judul' => $judul, 'alamat' => $alamat, 'kolom' => $kolom));
    }

    protected function jenisOrder(array $order) {
        return (isset($order['tipe']) && $order['tipe'] === 'insitu') ? 'Insitu' : 'Inlab';
    }

    /**
     * POST /petunjuk-operasional/kalibrasi/simpan  (AJAX, balasan JSON)
     * Nomor PO diterbitkan hanya saat Kirim, bersama pembuatan baris po_kegiatan dalam satu transaksi.
     */
    public function simpan($f3) {
        $this->requireAuth();
        if (!$this->izinKalibrasi()) {
            return $this->json(array('ok' => false, 'message' => 'PO Kalibrasi sementara hanya bisa diakses Superadmin.'), 403);
        }
        $kendala = $this->kendalaSkema();
        if ($kendala !== '') { return $this->json(array('ok' => false, 'message' => $kendala), 500); }

        $orderId = (int) ($f3->get('POST.order_id') ?? 0);
        if ($orderId <= 0) {
            return $this->json(array('ok' => false, 'message' => 'PO harus dibuat dari order. Pilih order dulu lewat Petunjuk Operasional > Kalibrasi.'), 422);
        }
        try { $order = \PoKalibrasi::ambil($this->db, $orderId); }
        catch (\Throwable $e) { error_log('[PO Kalibrasi] gagal validasi order saat simpan: ' . $e->getMessage()); $order = null; }
        if ($order === null) {
            return $this->json(array('ok' => false, 'message' => 'Order kalibrasi tidak ditemukan atau sudah tidak tersedia.'), 422);
        }

        $this->orderCek = $orderId;
        list($err, $in) = $this->bacaInput($f3);
        if ($err !== null) { return $this->tolakInput($err); }
        $status = $this->statusDariAksi($f3);

        try {
            $ada = $this->cariPoOrder($orderId);
            if ($ada) {
                return $this->json(array('ok' => false, 'message' => 'Order ini sudah punya PO.', 'redirect' => $this->urlEdit($ada['id'])), 409);
            }
            \PoSchema::pastikanTabelNomor($this->db); // DDL harus di luar transaksi
            $this->db->begin();
            try {
                $nomor = ($status === 'terkirim')
                    ? \PoSchema::nomorBaru($this->db, \PoSchema::hariIniWib(), false)
                    : array('id' => null, 'detail_nomor' => null);
                $now = date('Y-m-d H:i:s');
                $poId = \PoSchema::sisipPo($this->db, array_merge($in['kolom'], array(
                    'order_id'           => $orderId,
                    'id_arsip_kalibrasi' => $orderId,
                    'order_nomor'        => self::teksOrNull(isset($order['nomor']) ? $order['nomor'] : '', 150),
                    'layanan'            => \PoKalibrasi::LAYANAN,
                    'jenis'              => $this->jenisOrder($order),
                    'nomor_po'           => $nomor['detail_nomor'],
                    'id_nomor_urut'      => $nomor['id'],
                    'tanggal_po'         => $in['tanggal_po'],
                    'tanggal_dikirim'    => ($status === 'terkirim') ? \PoSchema::sekarangWib() : null,
                    'judul_kegiatan'     => $in['judul'],
                    'alamat'             => $in['alamat'],
                    'status'             => $status,
                    'created_by'         => (int) $this->getUserId(),
                    'created_at'         => $now,
                    'updated_at'         => $now,
                )));
                if ($poId <= 0) { throw new \Exception('PO gagal dibuat.'); }
                $this->db->commit();
            } catch (\Throwable $e) {
                try { $this->db->rollback(); } catch (\Throwable $eRb) {}
                throw $e;
            }
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] gagal menyimpan PO: ' . $e->getMessage());
            return $this->json(array('ok' => false, 'message' => 'Gagal menyimpan PO: ' . $e->getMessage()), 500);
        }

        $kirim = ($status === 'terkirim');
        return $this->json(array(
            'ok'             => true,
            'message'        => $kirim ? 'PO berhasil dibuat dan dikirim.' : 'PO berhasil disimpan sebagai draft.',
            'id'             => $poId,
            'nomor'          => (string) $nomor['detail_nomor'],
            'nomor_baru'     => $nomor['detail_nomor'] !== null,
            'tanggal_po'     => $in['tanggal_po'],
            'dikirim'        => $nomor['detail_nomor'] !== null ? date('d/m/Y') : '',
            'dibuat'         => date('d/m/Y'),
            'status'         => $status,
            'redirect'       => $this->urlEdit($poId),
            'redirect_index' => $this->urlIndex($kirim ? 'terkirim' : 'draft'),
        ));
    }

    /**
     * POST /petunjuk-operasional/kalibrasi/@id/update  (AJAX, balasan JSON)
     * Nomor PO yang sudah terbit TIDAK berubah; draft yang dikirim pertama kali mendapat nomor sekarang.
     */
    public function update($f3, $params) {
        $this->requireAuth();
        if (!$this->izinKalibrasi()) {
            return $this->json(array('ok' => false, 'message' => 'PO Kalibrasi sementara hanya bisa diakses Superadmin.'), 403);
        }
        $kendala = $this->kendalaSkema();
        if ($kendala !== '') { return $this->json(array('ok' => false, 'message' => $kendala), 500); }

        try { $po = $this->ambilPo((int) $params['id']); } catch (\Throwable $e) { $po = null; }
        if (!$po) { return $this->json(array('ok' => false, 'message' => 'PO Kalibrasi tidak ditemukan.'), 404); }
        $statusLama = (string) ($po['status'] ?? 'draft');
        if (!in_array($statusLama, array('', 'draft'), true)) {
            return $this->json(array('ok' => false, 'message' => $statusLama === 'terkirim'
                ? 'PO ini sudah terkirim dan tidak bisa diedit lagi.'
                : 'PO ini sudah berstatus "' . $statusLama . '" dan tidak bisa diubah lagi.'), 409);
        }
        $status = $this->statusDariAksi($f3);

        $tglLama = (string) ($po['tanggal_po'] ?? '');
        $tglTetap = ($tglLama !== '' && strpos($tglLama, '0000') !== 0 && strtotime($tglLama)) ? date('Y-m-d', strtotime($tglLama)) : null;
        $this->orderCek = (int) ($po['order_id'] ?? 0);
        list($err, $in) = $this->bacaInput($f3, $tglTetap);
        if ($err !== null) { return $this->tolakInput($err); }

        $sudahNomor = trim((string) ($po['nomor_po'] ?? '')) !== '';
        $nomorBaru = null;
        try {
            $ubah = array_merge($in['kolom'], array(
                'tanggal_po'     => $in['tanggal_po'],
                'judul_kegiatan' => $in['judul'],
                'alamat'         => $in['alamat'],
                'status'         => $status,
                'updated_at'     => date('Y-m-d H:i:s'),
            ));
            if (!$sudahNomor && $status === 'terkirim') {
                \PoSchema::pastikanTabelNomor($this->db); // DDL harus di luar transaksi
                $this->db->begin();
                try {
                    $nomorBaru = \PoSchema::nomorBaru($this->db, \PoSchema::hariIniWib(), false);
                    $ubah['tanggal_dikirim'] = \PoSchema::sekarangWib();
                    $ubah['nomor_po']        = $nomorBaru['detail_nomor'];
                    $ubah['id_nomor_urut']   = $nomorBaru['id'];
                    \PoSchema::ubahPo($this->db, (int) $po['id'], $ubah);
                    $this->db->commit();
                } catch (\Throwable $e) {
                    try { $this->db->rollback(); } catch (\Throwable $eRb) {}
                    throw $e;
                }
            } else {
                \PoSchema::ubahPo($this->db, (int) $po['id'], $ubah);
            }
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] gagal memperbarui PO: ' . $e->getMessage());
            return $this->json(array('ok' => false, 'message' => 'Gagal memperbarui PO: ' . $e->getMessage()), 500);
        }
        $nomorAkhir = $nomorBaru ? $nomorBaru['detail_nomor'] : (string) ($po['nomor_po'] ?? '');
        $kirim = ($status === 'terkirim');
        return $this->json(array(
            'ok'             => true,
            'message'        => $kirim ? 'PO berhasil diperbarui dan dikirim.' : 'PO berhasil diperbarui sebagai draft.',
            'id'             => (int) $po['id'],
            'nomor'          => $nomorAkhir,
            'nomor_baru'     => $nomorBaru !== null,
            'tanggal_po'     => $in['tanggal_po'],
            'dikirim'        => $nomorBaru !== null ? date('d/m/Y') : $this->tglDikirimTeks($po),
            'status'         => $status,
            'redirect_index' => $this->urlIndex($kirim ? 'terkirim' : 'draft'),
        ));
    }

    /** GET /petunjuk-operasional/kalibrasi/@id/edit */
    /* ================================================================
     * KLAIM SURAT PENAWARAN dari ARSIP SURAT KELUAR sekretariat (sil2020.tb_surat_keluar)
     * Sama konsepnya dengan klaim di OPTI, tapi memakai penanda SENDIRI untuk Kalibrasi supaya tidak mengganggu klaim OPTI:
     *   dipilih_kalibrasi ('N' default -> 'Y' saat diklaim), tgl_klaim_kalibrasi, id_user_klaim_kalibrasi, id_order_klaim_kalibrasi.
     * Yang tampil di daftar: surat_penawaran='Y', dipilih_kalibrasi='N', dan belum dipilih untuk layanan lain
     * (dipilih_opti / dipilih_sis_opti / dipilih_sis_pengujian / dipilih_sis_sertifikasi bukan 'Y').
     * Satu order = satu surat; klaim ulang (Ganti) otomatis melepas klaim lama order itu.
     * Input surat manual (ketik + upload) SENGAJA belum ada.
     * ================================================================ */

    protected static $kolomKlaimSiap = false;
    protected static $kolomKlaim = array(
        'dipilih_kalibrasi'        => "ENUM('Y','N') NOT NULL DEFAULT 'N'",
        'tgl_klaim_kalibrasi'      => 'DATETIME NULL',
        'id_user_klaim_kalibrasi'  => 'INT(11) NULL',
        'id_order_klaim_kalibrasi' => 'INT(11) NULL',
    );

    /** Pastikan kolom klaim Kalibrasi ada di sil2020.tb_surat_keluar; return pesan kendala ('' = aman). */
    protected function pastikanKolomKlaim() {
        if (self::$kolomKlaimSiap) { return ''; }
        try {
            // kolom klaim OPTI (dipilih_opti dkk) dipakai sebagai filter; ikut dipastikan ada
            $sp = new \SuratPenawaran($this->db);
            $sp->pastikanKolomKlaimSuratKeluar();
            $ada = array();
            foreach ($this->db->exec('SHOW COLUMNS FROM sil2020.tb_surat_keluar') as $c) { $ada[] = $c['Field']; }
            $kurang = array();
            foreach (self::$kolomKlaim as $nama => $def) {
                if (in_array($nama, $ada, true)) { continue; }
                try { $this->db->exec('ALTER TABLE sil2020.tb_surat_keluar ADD COLUMN `' . $nama . '` ' . $def); }
                catch (\Throwable $e) { error_log('[PO Kalibrasi] ' . $e->getMessage()); $kurang[] = 'ADD COLUMN `' . $nama . '` ' . $def; }
            }
            if ($kurang) {
                return 'Kolom klaim Kalibrasi di tb_surat_keluar belum berhasil dibuat otomatis. Cek hak ALTER TABLE user MySQL, atau jalankan manual: ALTER TABLE sil2020.tb_surat_keluar ' . implode(', ', $kurang) . ';';
            }
            self::$kolomKlaimSiap = true;
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] ' . $e->getMessage());
            return 'Gagal memeriksa kolom tb_surat_keluar.';
        }
        return '';
    }

    /** Satu baris tb_surat_keluar (+ nama customer) -> bentuk untuk JSON. */
    protected function petakanSurat(array $r, $idCustomerOrder = 0, $namaMitra = '') {
        $base = \Base::instance()->get('BASE') ?: '';
        $nama = '';
        try { $nama = \Customer::formatNamaPerusahaan(isset($r['pt_cv']) ? $r['pt_cv'] : '', isset($r['nmcustomer']) ? $r['nmcustomer'] : ''); }
        catch (\Throwable $e) { $nama = trim((isset($r['nmcustomer']) ? $r['nmcustomer'] : '')); }
        if ($nama === '') { $nama = !empty($r['jabatan_surat']) ? (string) $r['jabatan_surat'] : ('Customer #' . (int) $r['id_customer']); }
        $tgl = (string) ($r['tanggal_surat'] ?? '');
        $tglOk = ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) ? date('Y-m-d', strtotime($tgl)) : '';
        $cocok = ($idCustomerOrder > 0 && (int) $r['id_customer'] === (int) $idCustomerOrder)
            || ($namaMitra !== '' && $nama !== '' && strtolower(trim($namaMitra)) === strtolower(trim($nama)));
        return array(
            'id'          => (int) $r['id_surat_keluar'],
            'no_surat'    => (string) ($r['no_surat'] ?? ''),
            'tanggal'     => $tglOk,
            'tanggal_fmt' => $tglOk !== '' ? date('d M Y', strtotime($tglOk)) : '-',
            'perihal'     => (string) ($r['perihal_surat'] ?? ''),
            'keterangan'  => (string) ($r['keterangan'] ?? ''),
            'jabatan'     => (string) ($r['jabatan_surat'] ?? ''),
            'customer'    => $nama,
            'cocok'       => $cocok,
            'berkas'      => basename((string) ($r['nama_berkas_surat_keluar'] ?? '')),
            'preview_url' => $base . '/arsip-surat-keluar/' . (int) $r['id_surat_keluar'] . '/preview',
        );
    }

    /** Surat yang sedang diklaim order ini (null = belum ada / kolom belum ada). */
    protected function suratKlaimOrder($orderId) {
        $r = $this->db->exec(
            "SELECT sk.*, c.nmcustomer, c.pt_cv FROM sil2020.tb_surat_keluar sk
             LEFT JOIN sil2020.tb_customer c ON c.id_customer = sk.id_customer
             WHERE sk.dipilih_kalibrasi = 'Y' AND sk.id_order_klaim_kalibrasi = ? LIMIT 1",
            array(1 => (int) $orderId)
        );
        return empty($r) ? null : $this->petakanSurat($r[0]);
    }

    /** Untuk form: klaim aktif order (null kalau belum ada atau kolom belum dibuat). */
    protected function klaimUntukView($orderId) {
        try { return $this->suratKlaimOrder($orderId); }
        catch (\Throwable $e) { return null; }
    }

    /** PO order ini masih boleh diubah klaimnya? (belum ada PO, atau PO masih draft) */
    protected function poMasihDraft($orderId) {
        try {
            $po = $this->cariPoOrder($orderId);
            return !$po || in_array((string) ($po['status'] ?? ''), array('', 'draft'), true);
        } catch (\Throwable $e) { return true; }
    }

    protected function cekAkses() {
        $this->requireAuth();
        if (!$this->izinKalibrasi()) {
            $this->json(array('ok' => false, 'message' => 'PO Kalibrasi sementara hanya bisa diakses Superadmin.'), 403);
            return false;
        }
        return true;
    }

    /**
     * GET /petunjuk-operasional/kalibrasi/surat-keluar?order=ID[&q=kata]
     * Daftar surat penawaran di arsip sekretariat yang masih bebas (dipilih_kalibrasi = 'N').
     */
    public function daftarSurat($f3) {
        if (!$this->cekAkses()) { return; }
        $kendala = $this->pastikanKolomKlaim();
        if ($kendala !== '') { return $this->json(array('ok' => false, 'message' => $kendala), 500); }

        $orderId = (int) $f3->get('GET.order');
        $q = trim((string) $f3->get('GET.q'));
        $idCust = 0; $namaMitra = '';
        if ($orderId > 0) {
            try { $o = \PoKalibrasi::ambil($this->db, $orderId); if ($o) { $idCust = (int) ($o['id_customer'] ?? 0); $namaMitra = (string) ($o['mitra'] ?? ''); } }
            catch (\Throwable $e) { error_log('[PO Kalibrasi] daftarSurat order: ' . $e->getMessage()); }
        }

        $sql = "SELECT sk.*, c.nmcustomer, c.pt_cv
                FROM sil2020.tb_surat_keluar sk
                LEFT JOIN sil2020.tb_customer c ON c.id_customer = sk.id_customer
                WHERE sk.surat_penawaran = 'Y'
                  AND sk.dipilih_kalibrasi = 'N'
                  AND COALESCE(sk.dipilih_opti, 'N') <> 'Y'
                  AND COALESCE(sk.dipilih_sis_opti, 'N') <> 'Y'
                  AND COALESCE(sk.dipilih_sis_pengujian, 'N') <> 'Y'
                  AND COALESCE(sk.dipilih_sis_sertifikasi, 'N') <> 'Y'";
        $args = array(); $i = 1;
        if ($q !== '') {
            $sql .= ' AND (sk.no_surat LIKE ? OR sk.perihal_surat LIKE ? OR c.nmcustomer LIKE ? OR sk.keterangan LIKE ?)';
            $like = '%' . $q . '%';
            for ($k = 0; $k < 4; $k++) { $args[$i++] = $like; }
        }
        $sql .= ' ORDER BY ' . ($idCust > 0 ? '(sk.id_customer = ' . $idCust . ') DESC, ' : '') . 'sk.tanggal_surat DESC, sk.id_surat_keluar DESC LIMIT 300';

        try { $rows = $this->db->exec($sql, $args); }
        catch (\Throwable $e) {
            error_log('[PO Kalibrasi] daftarSurat: ' . $e->getMessage());
            return $this->json(array('ok' => false, 'message' => 'Gagal membaca arsip surat keluar.'), 500);
        }
        $out = array();
        foreach ($rows as $r) { $out[] = $this->petakanSurat($r, $idCust, $namaMitra); }
        // yang cocok dengan mitra order di atas (urutan sama dipertahankan)
        usort($out, function ($a, $b) { return ((int) $b['cocok']) - ((int) $a['cocok']); });
        return $this->json(array('ok' => true, 'total' => count($out), 'data' => $out));
    }

    /**
     * POST /petunjuk-operasional/kalibrasi/surat-keluar/klaim  (order_id, id_surat_keluar)
     * Klaim surat untuk order. Klaim lama order yang sama otomatis dilepas (kembali 'N').
     */
    public function klaimSurat($f3) {
        if (!$this->cekAkses()) { return; }
        $kendala = $this->pastikanKolomKlaim();
        if ($kendala !== '') { return $this->json(array('ok' => false, 'message' => $kendala), 500); }

        $orderId = (int) $f3->get('POST.order_id');
        $idSk = (int) $f3->get('POST.id_surat_keluar');
        if ($orderId <= 0 || $idSk <= 0) { return $this->json(array('ok' => false, 'message' => 'Order atau surat belum dipilih.'), 422); }
        try { $order = \PoKalibrasi::ambil($this->db, $orderId); } catch (\Throwable $e) { $order = null; }
        if ($order === null) { return $this->json(array('ok' => false, 'message' => 'Order kalibrasi tidak ditemukan.'), 422); }
        if (!$this->poMasihDraft($orderId)) {
            return $this->json(array('ok' => false, 'message' => 'PO order ini sudah terkirim, surat penawarannya tidak bisa diganti lagi.'), 409);
        }
        $userId = (int) ($this->getUserId() ?? 0);

        try {
            $this->db->begin();
            $r = $this->db->exec('SELECT * FROM sil2020.tb_surat_keluar WHERE id_surat_keluar = ? LIMIT 1 FOR UPDATE', array(1 => $idSk));
            if (empty($r)) { $this->db->rollback(); return $this->json(array('ok' => false, 'message' => 'Surat tidak ditemukan di arsip surat keluar.'), 404); }
            $sk = $r[0];
            if (($sk['surat_penawaran'] ?? 'N') !== 'Y') { $this->db->rollback(); return $this->json(array('ok' => false, 'message' => 'Surat yang dipilih bukan kategori Surat Penawaran.'), 422); }
            $dipakaiLain = (($sk['dipilih_kalibrasi'] ?? 'N') === 'Y' && (int) ($sk['id_order_klaim_kalibrasi'] ?? 0) !== $orderId)
                || ($sk['dipilih_opti'] ?? 'N') === 'Y' || ($sk['dipilih_sis_opti'] ?? 'N') === 'Y'
                || ($sk['dipilih_sis_pengujian'] ?? 'N') === 'Y' || ($sk['dipilih_sis_sertifikasi'] ?? 'N') === 'Y';
            if ($dipakaiLain) { $this->db->rollback(); return $this->json(array('ok' => false, 'message' => 'Surat ini sudah diklaim / dipilih untuk layanan atau order lain. Muat ulang daftar.'), 409); }

            // lepas klaim lama order ini (kalau Ganti)
            $this->db->exec(
                "UPDATE sil2020.tb_surat_keluar SET dipilih_kalibrasi = 'N', tgl_klaim_kalibrasi = NULL, id_user_klaim_kalibrasi = NULL, id_order_klaim_kalibrasi = NULL
                 WHERE id_order_klaim_kalibrasi = ? AND id_surat_keluar <> ?",
                array(1 => $orderId, 2 => $idSk)
            );
            $this->db->exec(
                "UPDATE sil2020.tb_surat_keluar SET dipilih_kalibrasi = 'Y', tgl_klaim_kalibrasi = NOW(), id_user_klaim_kalibrasi = ?, id_order_klaim_kalibrasi = ?
                 WHERE id_surat_keluar = ?",
                array(1 => $userId > 0 ? $userId : null, 2 => $orderId, 3 => $idSk)
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            try { $this->db->rollback(); } catch (\Throwable $e2) {}
            error_log('[PO Kalibrasi] klaimSurat: ' . $e->getMessage());
            return $this->json(array('ok' => false, 'message' => 'Gagal mengklaim surat. Coba lagi.'), 500);
        }

        $surat = null;
        try { $surat = $this->suratKlaimOrder($orderId); } catch (\Throwable $e) {}
        return $this->json(array('ok' => true, 'message' => 'Surat penawaran berhasil diklaim.', 'surat' => $surat));
    }

    /** POST /petunjuk-operasional/kalibrasi/surat-keluar/batal  (order_id) -- surat kembali ke daftar (dipilih_kalibrasi = 'N'). */
    public function batalKlaimSurat($f3) {
        if (!$this->cekAkses()) { return; }
        $kendala = $this->pastikanKolomKlaim();
        if ($kendala !== '') { return $this->json(array('ok' => false, 'message' => $kendala), 500); }
        $orderId = (int) $f3->get('POST.order_id');
        if ($orderId <= 0) { return $this->json(array('ok' => false, 'message' => 'Order tidak valid.'), 422); }
        if (!$this->poMasihDraft($orderId)) {
            return $this->json(array('ok' => false, 'message' => 'PO order ini sudah terkirim, klaim surat tidak bisa dibatalkan.'), 409);
        }
        try {
            $this->db->exec(
                "UPDATE sil2020.tb_surat_keluar SET dipilih_kalibrasi = 'N', tgl_klaim_kalibrasi = NULL, id_user_klaim_kalibrasi = NULL, id_order_klaim_kalibrasi = NULL
                 WHERE id_order_klaim_kalibrasi = ? AND dipilih_kalibrasi = 'Y'",
                array(1 => $orderId)
            );
        } catch (\Throwable $e) {
            error_log('[PO Kalibrasi] batalKlaimSurat: ' . $e->getMessage());
            return $this->json(array('ok' => false, 'message' => 'Gagal membatalkan klaim. Coba lagi.'), 500);
        }
        return $this->json(array('ok' => true, 'message' => 'Klaim dibatalkan, surat kembali ke daftar arsip.'));
    }

    public function edit($f3, $params) { return $this->tampilkan($f3, $params, false); }

    /** GET /petunjuk-operasional/kalibrasi/@id/lihat -- hanya pratinjau dokumen PO (tanpa form). */
    public function lihat($f3, $params) { return $this->tampilkan($f3, $params, true); }

    protected function tampilkan($f3, $params, $lihat) {
        $this->requireAuth();
        if (!$this->izinKalibrasi()) {
            $this->setFlashError('PO Kalibrasi sementara hanya bisa diakses Superadmin.');
            $f3->reroute('/dashboard');
            return;
        }
        $this->kendalaSkema();
        $po = null;
        try { $po = $this->ambilPo((int) $params['id']); }
        catch (\Throwable $e) { error_log('[PO Kalibrasi] gagal baca PO: ' . $e->getMessage()); }
        if (!$po) {
            $this->setFlashError('PO Kalibrasi tidak ditemukan.');
            $f3->reroute('/petunjuk-operasional?layanan=kalibrasi');
            return;
        }
        if (!$lihat && !in_array((string) ($po['status'] ?? ''), array('', 'draft'), true)) {
            $this->setFlashWarning('PO yang sudah terkirim tidak bisa diedit lagi.');
            $f3->reroute('/petunjuk-operasional/kalibrasi/' . (int) $po['id'] . '/lihat');
            return;
        }

        $order = null;
        try { $order = \PoKalibrasi::ambil($this->db, (int) $po['order_id']); }
        catch (\Throwable $e) { error_log('[PO Kalibrasi] gagal baca order PO: ' . $e->getMessage()); }
        if ($order === null) {
            // order sudah tidak terbaca -> PO tetap bisa dibuka dari data yang tersimpan
            $order = array('id' => (int) $po['order_id'], 'nomor' => (string) ($po['order_nomor'] ?? ''), 'mitra' => (string) ($po['nama_perusahaan'] ?? ''),
                           'alamat' => '', 'uraian' => '', 'tipe' => ((string) ($po['jenis'] ?? '') === 'Insitu') ? 'insitu' : 'lab', 'tempat' => '',
                           'tgl_order' => '', 'berangkat' => '', 'pulang' => '', 'target' => '', 'jml_teknisi' => 0, 'teknisi' => array(),
                           'biaya' => array(), 'alat' => array());
        }

        $data = json_decode((string) ($po['isi_form'] ?? ''), true);
        $tgl = (string) ($po['tanggal_po'] ?? '');
        $poInfo = array(
            'id'         => (int) $po['id'],
            'nomor'      => (string) ($po['nomor_po'] ?? ''),
            'tanggal_po' => ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) ? date('Y-m-d', strtotime($tgl)) : '',
            'dikirim'    => $this->tglDikirimTeks($po),
            'dibuat'     => !empty($po['created_at']) && strtotime((string) $po['created_at']) ? date('d/m/Y', strtotime((string) $po['created_at'])) : '',
            'judul'      => (string) ($po['judul_kegiatan'] ?? ''),
            'status'     => (string) ($po['status'] ?? ''),
            'data'       => is_array($data) ? $data : null,
        );

        $f3->set('order_json', $this->jsonHive($order));
        $f3->set('klaim_json', $this->jsonHive($this->klaimUntukView((int) $po['order_id'])));
        $f3->set('po_json', $this->jsonHive($poInfo));
        $f3->set('mode_lihat', $lihat ? 'true' : 'false');
        $f3->set('tanggal_default', date('Y-m-d'));
        $f3->set('csrf_po', (string) ($f3->get('csrf_token') ?? ''));

        $this->render(
            'petunjuk_operasional/po_kalibrasi/create.html',
            $lihat ? 'Preview PO Kalibrasi' : 'Edit PO Kalibrasi',
            'po_kalibrasi'
        );
    }
}
