<?php
namespace petunjuk_operasional;

/**
 * PoSertifikasiController -- Petunjuk Operasional (PO) jenis SERTIFIKASI.
 *
 * Sengaja DIPISAH dari modul PO OPTI (PoKegiatanController dkk): beda folder
 * (app/controllers/petunjuk_operasional/), beda namespace, beda folder view
 * (app/views/petunjuk_operasional/po_sertifikasi/), beda URL (/petunjuk-operasional/...).
 *
 * ALUR:
 *   1. /po-kegiatan/pilih -> pilih "Sertifikasi" -> modal daftar order dari database SISERTIFIKASI
 *      (sisertifikasi2026.tb_arsip_sertifikasi).
 *   2. /petunjuk-operasional/sertifikasi/create?order=<id_arsip_sertifikasi> -> isi form PO.
 *   3. Simpan -> tabel po_kegiatan (silopti2026):
 *        - order_id = id_arsip_sertifikasi
 *        - layanan  = 'sertifikasi' (otomatis; PO OPTI diisi 'opti-lingkungan'/'opti-selulosa')
 *        - nomor_po / id_nomor_urut = dari tabel po_nomor_urut, algoritma & format SAMA dengan PO OPTI
 *          ("N/PO/BBSPJIS/ROMAWI/TAHUN"), diterbitkan SEKALI saat PO DIKIRIM (bulan/tahun = tanggal kirim WIB; draft belum bernomor)
 *        - isi form dipecah ke kolom-kolom po_kegiatan seperti PO OPTI (dasar, tujuan, ruang_lingkup, alamat,
 *          tim_pelaksana, rab, jadwal) + kolom khusus sertifikasi (jenis, nama_perusahaan, no_penawaran,
 *          tanggal_penawaran, alamat_bagian, alamat_tampil); isian kosong disimpan NULL (lihat PoSchema::kolomDariForm)
 *      Satu order sertifikasi = satu PO; membuka create lagi untuk order yang sama diarahkan ke edit.
 *   4. Tombol di bawah form (sama seperti PO OPTI): "Simpan sebagai Draft" -> status 'draft',
 *      "Kirim" -> status 'terkirim'. Keduanya menampilkan popup (SweetAlert) lalu pindah ke daftar PO. Daftar PO-nya ada di PoIndexController (/petunjuk-operasional).
 *      Order yang sudah dibuatkan PO tidak muncul lagi di modal pilih order.
 *
 * Syarat order yang boleh dibuatkan PO Sertifikasi:
 *   1. status_penerimaan_order = 'Order'  (bukan 'Pendaftaran')
 *   2. total_nilai_kontrak = total_bayar_kontrak  (kontrak sudah terbayar penuh)
 *   3. total_nilai_kontrak > 0  (pengaman: 0 = 0 bukan berarti sudah lunas)
 *
 * Kompatibel PHP 7.2.
 */
class PoSertifikasiController extends \Controller {

    const DB_SERTIFIKASI_DEFAULT = 'sisertifikasi2026';

    /** Nama database SISERTIFIKASI. Bisa diganti lewat config.ini: db_sertifikasi_name=... */
    protected function namaDbSertifikasi() {
        $n = (string) \Base::instance()->get('db_sertifikasi_name');
        return preg_match('/^[A-Za-z0-9_]+$/', $n) ? $n : self::DB_SERTIFIKASI_DEFAULT;
    }

    /** Pastikan database SISERTIFIKASI dan tb_arsip_sertifikasi tersedia di server MySQL */
    public function ensureDatabaseSertifikasi(): void {
        static $checked = false;
        if ($checked) return;
        try {
            $dbName = $this->namaDbSertifikasi();
            $this->db->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->db->exec("CREATE TABLE IF NOT EXISTS `{$dbName}`.`tb_arsip_sertifikasi` (
                `id_arsip_sertifikasi` INT(11) NOT NULL AUTO_INCREMENT,
                `no_id_order` VARCHAR(50) DEFAULT NULL,
                `nomor_order` VARCHAR(100) DEFAULT NULL,
                `tanggal_order` DATE DEFAULT NULL,
                `status_order` VARCHAR(50) DEFAULT 'Diproses',
                `status_penerimaan_order` VARCHAR(50) DEFAULT 'Order',
                `id_customer` INT(11) DEFAULT NULL,
                `no_pks` VARCHAR(100) DEFAULT NULL,
                `keterangan` TEXT DEFAULT NULL,
                `total_nilai_kontrak` DECIMAL(15,2) NOT NULL DEFAULT '0.00',
                `total_bayar_kontrak` DECIMAL(15,2) NOT NULL DEFAULT '0.00',
                `tanggal_update` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id_arsip_sertifikasi`),
                KEY `idx_customer` (`id_customer`),
                KEY `idx_status_order` (`status_penerimaan_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $checked = true;
        } catch (\Throwable $e) {}
    }

    /**
     * Ambil order sertifikasi yang memenuhi syarat. $id > 0 -> cek satu order saja
     * (dipakai untuk validasi di create()/simpan()). $kecualiSudahPo=true -> sembunyikan order yang sudah punya PO (daftar di modal). $hanyaLayak=false -> tanpa syarat
     * Order+lunas (dipakai saat mengedit PO yang sudah tersimpan). tb_customer otomatis diarahkan ke sil2020
     * oleh OptiDatabase; tabel sertifikasi dibaca lintas-database (server MySQL yang sama).
     */
    protected function queryOrderLayak($id = 0, $hanyaLayak = true, $kecualiSudahPo = false) {
        $this->ensureDatabaseSertifikasi();
        $dbName = $this->namaDbSertifikasi();
        $sql = "SELECT a.id_arsip_sertifikasi, a.no_id_order, a.nomor_order, a.tanggal_order, a.status_order,
                       a.no_pks, a.keterangan, a.total_nilai_kontrak, a.total_bayar_kontrak,
                       " . $this->kolomTanggalUpdate($dbName) . " AS tanggal_update,
                       c.nmcustomer, c.pt_cv, c.alamatcustomer
                FROM `{$dbName}`.`tb_arsip_sertifikasi` a
                LEFT JOIN tb_customer c ON c.id_customer = a.id_customer
                WHERE 1 = 1";
        if ($hanyaLayak) {
            $sql .= " AND a.status_penerimaan_order = 'Order'
                  AND a.total_nilai_kontrak = a.total_bayar_kontrak
                  AND a.total_nilai_kontrak > 0";
        }
        if ($kecualiSudahPo && $this->kolomKlaimAda($dbName)) {
            // penanda: order yang sudah diklaim PO (status_klaim_po = 'Y', terisi otomatis saat PO disimpan) tidak ditawarkan lagi
            $sql .= " AND COALESCE(a.status_klaim_po, 'N') <> 'Y'";
        }
        if ($kecualiSudahPo && \PoSchema::siap()) {
            // pengaman: order yang sudah dibuatkan PO Sertifikasi (po_kegiatan.order_id = id_arsip_sertifikasi) tidak ditawarkan lagi
            $sql .= " AND NOT EXISTS (SELECT 1 FROM po_kegiatan pk WHERE pk.order_id = a.id_arsip_sertifikasi AND pk.layanan = '" . \PoSchema::SERTIFIKASI . "')";
        }
        $args = [];
        if ($id > 0) {
            $sql .= " AND a.id_arsip_sertifikasi = ?";
            $args[1] = (int) $id;
        }
        $sql .= " ORDER BY a.tanggal_order DESC, a.id_arsip_sertifikasi DESC";
        if ($id > 0) {
            $sql .= " LIMIT 1";
        }
        return $this->db->exec($sql, $args);
    }

    /** a.tanggal_update bila kolomnya ada di tb_arsip_sertifikasi, kalau tidak NULL (supaya query tidak error). */
    protected function kolomTanggalUpdate($dbName) {
        static $ada = null;
        if ($ada === null) {
            try {
                $r = $this->db->exec("SHOW COLUMNS FROM `{$dbName}`.`tb_arsip_sertifikasi` LIKE 'tanggal_update'");
                $ada = !empty($r);
            } catch (\Throwable $e) {
                $ada = false;
            }
        }
        return $ada ? 'a.tanggal_update' : 'NULL';
    }

    /**
     * Kolom penanda tb_arsip_sertifikasi.status_klaim_po ('N' = belum diklaim PO, 'Y' = sudah punya PO, termasuk draft).
     * Dianggap ada hanya bila bertipe teks (ENUM / CHAR / VARCHAR), supaya nilai 'Y' / 'N' aman dibandingkan;
     * kalau kolom belum ada, aplikasi jalan seperti sebelumnya (cukup cek po_kegiatan).
     */
    protected function kolomKlaimAda($dbName) {
        static $ada = null;
        if ($ada === null) {
            try {
                $r = $this->db->exec("SHOW COLUMNS FROM `{$dbName}`.`tb_arsip_sertifikasi` LIKE 'status_klaim_po'");
                $ada = !empty($r) && preg_match('/^(enum|char|varchar)/i', (string) ($r[0]['Type'] ?? $r[0]['type'] ?? '')) === 1;
            } catch (\Throwable $e) {
                $ada = false;
            }
        }
        return $ada;
    }

    /**
     * Set penanda klaim order ('Y' saat PO disimpan, 'N' bila dilepas). Gagal menandai tidak membatalkan simpan PO:
     * filter modal tetap aman karena po_kegiatan ikut dicek.
     */
    protected function tandaiKlaim($orderId, $nilai = 'Y') {
        try {
            $dbName = $this->namaDbSertifikasi();
            if (!$this->kolomKlaimAda($dbName)) { return false; }
            $this->db->exec(
                "UPDATE `{$dbName}`.`tb_arsip_sertifikasi` SET status_klaim_po = ? WHERE id_arsip_sertifikasi = ?",
                [1 => ($nilai === 'Y' ? 'Y' : 'N'), 2 => (int) $orderId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal mengisi status_klaim_po order #' . (int) $orderId . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Tanggal valid (Y-m-d) dari nilai DATE/DATETIME, atau ''. */
    protected static function tanggalValid($v) {
        $v = (string) $v;
        return ($v !== '' && strpos($v, '0000') !== 0 && strtotime($v)) ? date('Y-m-d', strtotime($v)) : '';
    }

    /** Lunas bila total nilai kontrak = total bayar kontrak (dan > 0). */
    protected static function orderLunas(array $r) {
        $nilai = (float) ($r['total_nilai_kontrak'] ?? 0);
        return $nilai > 0 && abs($nilai - (float) ($r['total_bayar_kontrak'] ?? 0)) < 0.005;
    }

    /**
     * Kalimat poin terakhir Dasar. Lunas -> "... telah lunas pada tanggal <tanggal_update>"; sama dengan yang tampil di form.
     * $r = baris order mentah (null = order tidak terbaca -> kalimat netral).
     */
    protected function teksPembayaran($r, $perusahaan) {
        $nm = trim((string) $perusahaan);
        $dasar = 'Pembayaran biaya oleh ' . ($nm === '' ? '...' : $nm);
        if (!is_array($r)) {
            return $dasar;
        }
        if (!self::orderLunas($r)) {
            return $dasar . ' belum lunas';
        }
        $tgl = \PoSchema::tanggalIndo(self::tanggalValid($r['tanggal_update'] ?? ''));
        return $dasar . ' telah lunas' . ($tgl !== '' ? ' pada tanggal ' . $tgl : '');
    }

    /** Kolom sebuah tabel (nama huruf kecil => field/type/key); [] bila tabel tidak ada. */
    protected function kolomTabel($dbName, $tabel) {
        static $cache = [];
        $k = $dbName . '.' . $tabel;
        if (!isset($cache[$k])) {
            $out = [];
            try {
                foreach ((array) $this->db->exec("SHOW COLUMNS FROM `{$dbName}`.`{$tabel}`") as $c) {
                    $out[strtolower((string) $c['Field'])] = ['field' => (string) $c['Field'], 'type' => strtolower((string) $c['Type']), 'key' => (string) $c['Key']];
                }
            } catch (\Throwable $e) {
                $out = [];
            }
            $cache[$k] = $out;
        }
        return $cache[$k];
    }

    /**
     * Teks (nama) dari tabel master untuk satu id. Kolom id = kunci utama master (atau kolom bernama sama dengan
     * kolom FK di order, atau 'id'); kolom teks dipilih otomatis (nama/jenis/kegiatan/... lalu kolom teks pertama).
     * Gagal/tidak ketemu -> '' (tampilan jatuh ke id).
     */
    protected function labelMaster($dbName, $tabel, $kolomFk, $id, $kolomUtama = '', $kolomId = '') {
        $kol = $this->kolomTabel($dbName, $tabel);
        if (!$kol || $id === '' || $id === null) {
            return '';
        }
        $pk = '';
        if ($kolomId !== '' && isset($kol[strtolower($kolomId)])) { $pk = $kol[strtolower($kolomId)]['field']; }   // kolom id yang ditentukan (mis. id_jenis_ws)
        if ($pk === '') {
            foreach ($kol as $c) {
                if ($c['key'] === 'PRI') { $pk = $c['field']; break; }
            }
        }
        if ($pk === '') {
            $pk = isset($kol[strtolower($kolomFk)]) ? $kol[strtolower($kolomFk)]['field'] : (isset($kol['id']) ? $kol['id']['field'] : '');
        }
        if ($pk === '') {   // terakhir: kolom pertama tabel master (umumnya id)
            $pertama = reset($kol);
            $pk = $pertama ? $pertama['field'] : '';
        }
        if ($pk === '') {
            return '';
        }
        $teks = [];
        foreach ($kol as $c) {
            $nm = strtolower($c['field']);
            if ($c['field'] === $pk || $nm === 'id' || strpos($nm, 'id_') === 0) { continue; }
            if (preg_match('/^(var)?char|text/', $c['type']) && !preg_match('/(created|updated|dibuat|diubah|status|aktif|password)/i', $c['field'])) {
                $teks[] = $c['field'];
            }
        }
        $adaKolomUtama = ($kolomUtama !== '' && isset($kol[strtolower($kolomUtama)]));   // kolom yang diminta boleh bertipe enum (mis. nama_ws = enum Wajib/Sukarela)
        if (!$teks && !$adaKolomUtama) {
            return '';
        }
        $pilih = $teks ? $teks[0] : '';
        foreach ($teks as $t) {
            if (preg_match('/^(nama|nm|jenis|kegiatan|ws|label|uraian|judul)/i', $t)) { $pilih = $t; break; }
        }
        if ($adaKolomUtama) { $pilih = $kol[strtolower($kolomUtama)]['field']; }   // kolom yang diminta (mis. nama_ws) diutamakan
        try {
            $r = $this->db->exec("SELECT `{$pilih}` AS teks FROM `{$dbName}`.`{$tabel}` WHERE `{$pk}` = ? LIMIT 1", [1 => $id]);
            return trim((string) ($r[0]['teks'] ?? ''));
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal baca ' . $tabel . ': ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Cari nama_ws bila tabel master tidak terbaca di database sertifikasi: cari di database MANA PUN di server yang sama
     * (tabel mst_jenis_ws_sertifikasi lebih dulu, lalu tabel apa pun yang punya kolom nama_ws). $dbName diprioritaskan.
     * $r = baris order (kunci huruf kecil). '' bila tidak ketemu.
     */
    protected function cariNamaWs($dbName, array $r) {
        $id = null;
        foreach ($r as $nmKol => $v) {
            if (strpos($nmKol, 'id_') === 0 && preg_match('/(^|_)ws(_|$)/', $nmKol) && $v !== null && $v !== '' && (string) $v !== '0') {
                $id = $v;
                if ($nmKol === 'id_jenis_ws') { break; }
            }
        }
        if ($id === null) { return ''; }
        try {
            $tab = $this->db->exec(
                "SELECT TABLE_SCHEMA AS s, TABLE_NAME AS t FROM information_schema.COLUMNS
                  WHERE COLUMN_NAME = 'nama_ws' AND TABLE_SCHEMA NOT IN ('mysql', 'information_schema', 'performance_schema', 'sys')
                  ORDER BY (TABLE_SCHEMA = ?) DESC, (TABLE_NAME = 'mst_jenis_ws_sertifikasi') DESC, TABLE_SCHEMA, TABLE_NAME LIMIT 8",
                [1 => $dbName]
            );
            foreach ((array) $tab as $row) {
                if ((string) $row['t'] === 'tb_arsip_sertifikasi') { continue; }
                $nama = $this->labelMaster((string) $row['s'], (string) $row['t'], 'id_jenis_ws', $id, 'nama_ws', 'id_jenis_ws');
                if ($nama !== '') { return $nama; }
            }
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal mencari nama_ws: ' . $e->getMessage());
        }
        return '';
    }

    /**
     * Informasi tambahan order untuk kartu "Informasi Order": tanggal pendaftaran + jenis sertifikasi,
     * kegiatan, jenis WS (nama dari tabel master). Kolom yang belum ada di tb_arsip_sertifikasi dilewati
     * supaya halaman tidak error. Hasil digabung ke array order dari petakanOrder().
     */
    protected function lengkapiInfoOrder(array $order) {
        $order += ['tanggal_pendaftaran' => '', 'jenis_sertifikasi' => '', 'kegiatan' => '', 'jenis_ws' => ''];
        if (empty($order['id'])) {
            return $order;
        }
        try {
            $this->ensureDatabaseSertifikasi();
            $dbName = $this->namaDbSertifikasi();
            $kol = $this->kolomTabel($dbName, 'tb_arsip_sertifikasi');
            $ambil = [];
            $kolomWs = [];
            foreach ($kol as $nmKol => $infoKol) {   // kolom lain yang berhubungan dengan WS (id_ws, nama_ws, dst.)
                if ($nmKol !== 'id_jenis_ws' && preg_match('/(^|_)ws(_|$)/', $nmKol)) { $kolomWs[] = $nmKol; }
            }
            foreach (array_merge(['tanggal_pendaftaran', 'id_jenis_sertifikasi', 'id_kegiatan', 'id_jenis_ws'], $kolomWs) as $c) {
                if (isset($kol[$c])) { $ambil[] = '`' . $kol[$c]['field'] . '`'; }
            }
            if (!$ambil) {
                return $order;
            }
            $r = $this->db->exec("SELECT " . implode(', ', $ambil) . " FROM `{$dbName}`.`tb_arsip_sertifikasi` WHERE id_arsip_sertifikasi = ? LIMIT 1", [1 => (int) $order['id']]);
            $r = array_change_key_case($r[0] ?? [], CASE_LOWER);
            $tgl = self::tanggalValid($r['tanggal_pendaftaran'] ?? '');
            $order['tanggal_pendaftaran'] = $tgl !== '' ? date('d/m/Y', strtotime($tgl)) : '';
            $map = [
                'jenis_sertifikasi' => ['id_jenis_sertifikasi', 'mst_jenis_sertifikasi'],
                'kegiatan'          => ['id_kegiatan', 'mst_kegiatan_sertifikasi'],
                'jenis_ws'          => ['id_jenis_ws', 'mst_jenis_ws_sertifikasi'],
            ];
            foreach ($map as $key => $m) {
                $id = $r[$m[0]] ?? null;
                if ($id === null || $id === '' || (string) $id === '0') { continue; }
                $nama = $this->labelMaster($dbName, $m[1], $m[0], $id, $key === 'jenis_ws' ? 'nama_ws' : '', $key === 'jenis_ws' ? 'id_jenis_ws' : '');
                $order[$key] = $nama !== '' ? $nama : ('#' . $id);
            }
            // Jenis WS: tampilkan nama_ws. Kolom nama_ws bisa langsung ada di tb_arsip_sertifikasi, atau di tabel master
            // (nama tabel/kolom id dilacak otomatis kalau berbeda dari dugaan).
            $namaWs = trim((string) ($r['nama_ws'] ?? ''));
            if ($namaWs === '') { $namaWs = $this->cariNamaWs($dbName, $r); }
            if ($namaWs !== '') { $order['jenis_ws'] = $namaWs; }
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal baca info order: ' . $e->getMessage());
        }
        return $order;
    }

    protected function petakanOrder(array $r) {
        $nomor = trim((string) ($r['nomor_order'] ?? ''));
        if ($nomor === '') {
            $nomor = trim((string) ($r['no_id_order'] ?? ''));
        }
        $tgl = (string) ($r['tanggal_order'] ?? '');
        $tglFmt = '';
        if ($tgl !== '' && $tgl !== '0000-00-00' && strtotime($tgl)) {
            $tglFmt = date('d/m/Y', strtotime($tgl));
        }
        return [
            'id'            => (int) $r['id_arsip_sertifikasi'],
            'nomor_order'   => $nomor,
            'no_id_order'   => trim((string) ($r['no_id_order'] ?? '')),
            'no_pks'        => trim((string) ($r['no_pks'] ?? '')),
            'mitra'         => \Customer::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nmcustomer'] ?? ''),
            'alamat'        => trim((string) ($r['alamatcustomer'] ?? '')),
            'tanggal_order' => $tglFmt,
            'status_order'  => (string) ($r['status_order'] ?? ''),
            'nilai_kontrak' => (int) ($r['total_nilai_kontrak'] ?? 0),
            'keterangan'    => trim((string) ($r['keterangan'] ?? '')),
            'lunas'         => self::orderLunas($r),
            'tanggal_bayar' => self::tanggalValid($r['tanggal_update'] ?? ''),
        ];
    }

    /**
     * GET /petunjuk-operasional/sertifikasi/order
     * JSON daftar order sertifikasi yang layak dibuatkan PO (untuk modal di /po-kegiatan/pilih).
     */
    public function daftarOrder($f3) {
        $this->requireAuth();
        if ($this->terlarangRoleLayananLain() || $this->terlarangMenulis()) {
            return $this->json(['ok' => false, 'message' => 'PO Sertifikasi tidak tersedia untuk role Anda.'], 403);
        }
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->kendalaSkema(); // pastikan kolom layanan ada supaya filter 'sudah punya PO' aktif
            $rows = $this->queryOrderLayak(0, true, true);
            $data = array_map([$this, 'petakanOrder'], $rows);
            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal baca daftar order: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Gagal membaca data order dari database ' . $this->namaDbSertifikasi() . '. Pastikan database tersebut ada dan user MySQL punya akses baca.'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Hari libur nasional (tabel yang sama dengan jadwal PO OPTI dan PO Pengujian). */
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
     * GET /petunjuk-operasional/sertifikasi/jadwal-kolom?mulai=YYYY-MM-DD&selesai=YYYY-MM-DD (JSON)
     * Kolom jadwal otomatis, sama dengan PO Pengujian dan PO OPTI: <= 31 hari harian (Sabtu/Minggu dan libur nasional
     * terkunci), selebihnya mingguan (4 minggu per bulan). Dipakai juga saat PO dilihat (ikon mata), jadi tidak
     * dibatasi untuk Ketua Tim.
     */
    public function jadwalKolom($f3) {
        $this->requireAuth();
        if ($this->terlarangRoleLayananLain()) {
            return $this->json(['mode' => null, 'kolom' => [], 'bulan_header' => []], 403);
        }
        $hasil = \PoPengujianDokumen::kolomJadwal((string) ($f3->get('GET.mulai') ?? ''), (string) ($f3->get('GET.selesai') ?? ''), $this->daftarLibur());
        return $this->json($hasil);
    }

    /** Role layanan lain (tim_kerja_pengujian) tidak boleh memakai PO Sertifikasi. */
    protected function terlarangRoleLayananLain() {
        return $this->getUserRole() === 'tim_kerja_pengujian';
    }

    /** Ketua Tim Sertifikasi hanya meninjau PO (lihat/review); membuat & mengedit PO tugas Tim Kerja. */
    protected function terlarangMenulis() {
        return $this->isKetuaSertifikasi();
    }

    protected function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function jsonHive($data) {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /** Pastikan skema po_kegiatan siap; return pesan kendala ('' = aman). */
    protected function kendalaSkema() {
        \PoSchema::pastikan($this->db);
        \PoSchema::pastikanTanggalDikirim($this->db);
        return \PoSchema::kendalaSertifikasi();
    }

    /** 'kirim' -> 'terkirim'; selain itu -> 'draft' (sama seperti PO OPTI). */
    protected function statusDariAksi($f3) {
        return ((string) $f3->get('POST.aksi')) === 'kirim' ? 'terkirim' : 'draft';
    }

    /**
     * Tujuan setelah simpan draft / kirim. Superadmin: Daftar PO gabungan (/po-kegiatan), tempat semua PO layanan tampil.
     * Role layanan (Tim Kerja / Ketua Tim Sertifikasi): tetap ke daftar layanannya, karena /po-kegiatan tidak memuat PO layanan untuk mereka.
     */
    protected function urlIndex($tab = 'draft') {
        $base = \Base::instance()->get('BASE');
        if ($this->isSuperadmin()) { return $base . '/po-kegiatan?tab=' . $tab; }
        return $base . '/petunjuk-operasional?layanan=sertifikasi&tab=' . $tab;
    }

    protected function urlEdit($poId) {
        return \Base::instance()->get('BASE') . '/petunjuk-operasional/sertifikasi/' . (int) $poId . '/edit';
    }

    /**
     * GET /petunjuk-operasional/sertifikasi/create[?order=@id_arsip_sertifikasi]
     * Tanpa ?order= -> form kosong (mode lihat tampilan; belum bisa disimpan). Dengan ?order= -> order
     * divalidasi ulang di server (syarat sama seperti daftar) lalu data mitra mengisi form.
     * Kalau order itu SUDAH punya PO Sertifikasi -> diarahkan ke halaman edit PO tersebut.
     */
    public function create($f3) {
        $this->requireAuth();
        if ($this->terlarangRoleLayananLain() || $this->terlarangMenulis()) {
            $this->setFlashError('PO Sertifikasi tidak tersedia untuk role Anda.');
            $f3->reroute('/petunjuk-operasional');
            return;
        }

        $orderId = (int) ($f3->get('GET.order') ?? 0);
        $order = null;
        if ($orderId > 0) {
            try {
                $rows = $this->queryOrderLayak($orderId);
            } catch (\Throwable $e) {
                error_log('[PO Sertifikasi] gagal baca order: ' . $e->getMessage());
                $rows = [];
            }
            if (empty($rows[0])) {
                $this->setFlashError('Order sertifikasi tidak ditemukan atau belum memenuhi syarat (harus berstatus Order dan kontrak sudah terbayar penuh).');
                $f3->reroute('/petunjuk-operasional?layanan=sertifikasi');
                return;
            }
            $order = $this->lengkapiInfoOrder($this->petakanOrder($rows[0]));

            $this->kendalaSkema();
            if (\PoSchema::siap()) {
                try {
                    $ada = \PoSchema::cariPoSertifikasi($this->db, $orderId);
                    if ($ada) {
                        $f3->reroute('/petunjuk-operasional/sertifikasi/' . (int) $ada['id'] . '/edit');
                        return;
                    }
                } catch (\Throwable $e) {
                    error_log('[PO Sertifikasi] gagal cek PO yang sudah ada: ' . $e->getMessage());
                }
            }
        }

        $f3->set('order_json', $this->jsonHive($order));
        $f3->set('po_json', 'null');
        $f3->set('mode_lihat', 'false');
        $f3->set('tanggal_default', date('Y-m-d'));
        $f3->set('csrf_po', (string) ($f3->get('csrf_token') ?? ''));

        $this->render(
            'petunjuk_operasional/po_sertifikasi/create.html',
            'Buat PO Sertifikasi',
            'po_sertifikasi'
        );
    }

    /** GET /petunjuk-operasional/sertifikasi/@id/edit */
    public function edit($f3, $params) {
        return $this->tampilkan($f3, $params, false);
    }

    /** GET /petunjuk-operasional/sertifikasi/@id/lihat -- hanya melihat preview dokumen PO (tanpa form), dipakai ikon mata di daftar PO. */
    public function lihat($f3, $params) {
        return $this->tampilkan($f3, $params, true);
    }

    protected function tampilkan($f3, $params, $lihat) {
        $this->requireAuth();
        if ($this->terlarangRoleLayananLain()) {
            $this->setFlashError('PO Sertifikasi tidak tersedia untuk role Anda.');
            $f3->reroute('/petunjuk-operasional');
            return;
        }
        if (!$lihat && $this->terlarangMenulis()) {
            // Ketua Tim hanya melihat dokumen, tidak mengedit
            $f3->reroute('/petunjuk-operasional/sertifikasi/' . (int) $params['id'] . '/lihat');
            return;
        }

        $this->kendalaSkema();
        $po = null;
        if (\PoSchema::siap()) {
            try {
                $po = \PoSchema::ambilPoSertifikasi($this->db, (int) $params['id']);
            } catch (\Throwable $e) {
                error_log('[PO Sertifikasi] gagal baca PO: ' . $e->getMessage());
            }
        }
        if (!$po) {
            $this->setFlashError('PO Sertifikasi tidak ditemukan.');
            $f3->reroute('/petunjuk-operasional?layanan=sertifikasi');
            return;
        }
        if (!$lihat && !in_array((string) ($po['status'] ?? ''), ['', 'draft'], true)) {
            // PO yang sudah dikirim hanya bisa dilihat (edit lagi hanya lewat permintaan revisi)
            $this->setFlashWarning('PO yang sudah terkirim tidak bisa diedit lagi.');
            $f3->reroute('/petunjuk-operasional/sertifikasi/' . (int) $po['id'] . '/lihat');
            return;
        }

        $order = null;
        try {
            $rows = $this->queryOrderLayak((int) $po['order_id'], false);
            if (!empty($rows[0])) {
                $order = $this->lengkapiInfoOrder($this->petakanOrder($rows[0]));
            }
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal baca order PO: ' . $e->getMessage());
        }
        if ($order === null) {
            // order di SISERTIFIKASI sudah tidak terbaca -> tetap bisa dibuka dari data yang tersimpan
            $order = ['id' => (int) $po['order_id'], 'nomor_order' => '', 'no_pks' => '', 'mitra' => '', 'alamat' => (string) ($po['alamat'] ?? ''),
                      'no_id_order' => '', 'tanggal_order' => '', 'status_order' => '', 'nilai_kontrak' => 0, 'keterangan' => '', 'lunas' => null, 'tanggal_bayar' => '',
                      'tanggal_pendaftaran' => '', 'jenis_sertifikasi' => '', 'kegiatan' => '', 'jenis_ws' => ''];
        }

        $data = \PoSchema::formDariBaris($po); // isi form dari kolom-kolom po_kegiatan
        $tgl = (string) ($po['tanggal_po'] ?? '');
        $poInfo = [
            'id'         => (int) $po['id'],
            'nomor'      => (string) ($po['nomor_po'] ?? ''),
            'tanggal_po' => ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) ? date('Y-m-d', strtotime($tgl)) : '',
            'dikirim'    => $this->tglDikirimTeks($po),
            'dibuat'     => !empty($po['created_at']) && strtotime((string) $po['created_at']) ? date('d/m/Y', strtotime((string) $po['created_at'])) : '',
            'judul'      => (string) ($po['judul_kegiatan'] ?? ''),
            'status'     => (string) ($po['status'] ?? ''),
            'data'       => $data,
        ];

        $f3->set('order_json', $this->jsonHive($order));
        $f3->set('po_json', $this->jsonHive($poInfo));
        $f3->set('mode_lihat', $lihat ? 'true' : 'false');
        $f3->set('tanggal_default', date('Y-m-d'));
        $f3->set('csrf_po', (string) ($f3->get('csrf_token') ?? ''));

        $this->render(
            'petunjuk_operasional/po_sertifikasi/create.html',
            $lihat ? 'Preview PO Sertifikasi' : 'Edit PO Sertifikasi',
            'po_sertifikasi'
        );
    }

    /** Daftar kekurangan isian (diisi bila aksi=kirim dan ada yang belum lengkap). */
    protected $errKirim = [];

    protected static function adaIsi($v) {
        return !is_array($v) && trim((string) $v) !== '';
    }

    /** Teks polos dari isian rich text (tag, &nbsp;, spasi ganda dibuang). */
    protected static function teksRte($html) {
        $t = preg_replace('/<[^>]*>/', ' ', (string) $html);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace("\xC2\xA0", ' ', $t);
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    protected static function angka($v) {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    protected static function hurufUrut($i) {
        $s = '';
        $i = (int) $i;
        do { $s = chr(65 + ($i % 26)) . $s; $i = intdiv($i, 26) - 1; } while ($i >= 0);
        return $s;
    }

    /**
     * Aturan Kirim (dicerminkan dengan validasiKirim() di form): semua isian wajib,
     * KECUALI alamat (alamat perusahaan & bagian Alamat). Draft tidak diperiksa.
     * @return string[] pesan kekurangan; kosong = lengkap
     */
    protected function kekuranganKirim(array $post, array $d) {
        $m = [];
        $isi = function ($v) { return self::adaIsi($v); };

        if (!$isi($post['judul_kegiatan'] ?? '')) { $m[] = 'Judul Kegiatan belum diisi'; }
        if (!$isi($post['jenis'] ?? '')) { $m[] = 'Jenis belum diisi'; }

        // Dasar: a. surat penawaran (nomor + tanggal; romawi & tahun otomatis dari tanggal), b dst. poin penjelasan
        $dp = (isset($d['dasarPenawaran']) && is_array($d['dasarPenawaran'])) ? $d['dasarPenawaran'] : [];
        if (!$isi($dp['nomor'] ?? '')) { $m[] = 'Poin a: nomor surat penawaran belum diisi'; }
        if (!$isi($dp['tanggal'] ?? '')) { $m[] = 'Poin a: tanggal surat penawaran belum diisi'; }
        $poin = (isset($d['dasarPoin']) && is_array($d['dasarPoin'])) ? array_values($d['dasarPoin']) : [];
        if (!$poin) { $m[] = 'Dasar: belum ada poin penjelasan (b, c, ...)'; }
        foreach ($poin as $i => $p) {
            $lbl = 'Dasar: poin ' . \PoSchema::hurufPoin($i + 1);
            $t = self::teksRte(is_array($p) ? ($p['teks'] ?? '') : '');
            if ($t === '') { $m[] = $lbl . ' belum diisi'; }
            elseif (preg_match('/\.{3}|\x{2026}/u', $t)) { $m[] = $lbl . ' masih ada titik-titik (...) yang belum diganti'; }
        }

        foreach (['tujuan' => 'Tujuan', 'ruang' => 'Ruang Lingkup'] as $k => $lbl) {
            $t = self::teksRte($d[$k] ?? '');
            if ($t === '') { $m[] = $lbl . ' belum diisi'; }
            elseif (preg_match('/\.{3}|\x{2026}/u', $t)) { $m[] = $lbl . ' masih ada titik-titik (...) yang belum diganti'; }
        }

        $rt = (isset($d['ruangTbl']) && is_array($d['ruangTbl'])) ? $d['ruangTbl'] : [];
        if (!empty($rt['aktif'])) {
            $kol = (isset($rt['kolom']) && is_array($rt['kolom'])) ? $rt['kolom'] : [];
            $rows = (isset($rt['rows']) && is_array($rt['rows'])) ? $rt['rows'] : [];
            if (!$kol) { $m[] = 'Tabel Ruang Lingkup belum punya kolom (atau matikan centang tabelnya)'; }
            else {
                foreach ($kol as $k) { if (!$isi($k['nama'] ?? '')) { $m[] = 'Tabel Ruang Lingkup: nama kolom belum diisi (atau matikan centang tabelnya)'; break; } }
            }
            if (!$rows) { $m[] = 'Tabel Ruang Lingkup belum punya baris (atau matikan centang tabelnya)'; }
            else {
                foreach ($rows as $r) {
                    $c = (isset($r['c']) && is_array($r['c'])) ? $r['c'] : [];
                    $ada = false;
                    foreach ($c as $v) { if ($isi($v)) { $ada = true; break; } }
                    if (!$ada) { $m[] = 'Tabel Ruang Lingkup: ada baris yang masih kosong (isi, hapus, atau matikan centang tabelnya)'; break; }
                }
            }
        }

        $tim = (isset($d['tim']) && is_array($d['tim'])) ? array_values($d['tim']) : [];
        if (!$tim) { $m[] = 'Tim Pelaksana belum ada anggota'; }
        foreach (['nama' => 'nama petugas', 'tugas' => 'tugas / tanggung jawab', 'uraian' => 'uraian tugas'] as $k => $lbl) {
            $bad = [];
            foreach ($tim as $i => $t) { if (!$isi(is_array($t) ? ($t[$k] ?? '') : '')) { $bad[] = $i + 1; } }
            if ($bad) { $m[] = 'Tim Pelaksana: ' . $lbl . ' belum diisi (baris ' . implode(', ', $bad) . ')'; }
        }

        $rab = (isset($d['rab']) && is_array($d['rab'])) ? $d['rab'] : [];
        $terima = (isset($rab['terima']) && is_array($rab['terima'])) ? array_values($rab['terima']) : [];
        $induk = (isset($rab['induk']) && is_array($rab['induk'])) ? array_values($rab['induk']) : [];
        if (!$terima) { $m[] = 'RAB: penerimaan belum ada'; }
        else {
            $tot = 0.0; $bad = [];
            foreach ($terima as $i => $t) {
                $t = is_array($t) ? $t : [];
                $tot += self::angka($t['nilai'] ?? 0);
                if (!$isi($t['nama'] ?? '')) { $bad[] = $i + 1; }
            }
            if ($bad) { $m[] = 'RAB: keterangan penerimaan belum diisi (baris ' . implode(', ', $bad) . ')'; }
            if ($tot <= 0) { $m[] = 'RAB: nilai penerimaan belum diisi'; }
        }
        $tot_terima_rab = 0.0;
        foreach ($terima as $t) { $tot_terima_rab += self::angka(is_array($t) ? ($t['nilai'] ?? 0) : 0); }
        if (!$induk) { $m[] = 'RAB: rencana pengeluaran belum ada kategori'; }
        // jumlah rincian satu kategori (sama dengan hitungan di form)
        // semua baris rincian satu kategori: kelompok utama (rincian) + kelompok berjudul lain (grup[].rincian)
        $semuaRincian = function ($x) {
            $rr = (isset($x['rincian']) && is_array($x['rincian'])) ? array_values($x['rincian']) : [];
            if (isset($x['grup']) && is_array($x['grup'])) {
                foreach ($x['grup'] as $g) {
                    if (is_array($g) && isset($g['rincian']) && is_array($g['rincian'])) { $rr = array_merge($rr, array_values($g['rincian'])); }
                }
            }
            return $rr;
        };
        $jmlRincian = function ($x) use ($semuaRincian) {
            $rr = $semuaRincian($x);
            $jml = 0.0;
            foreach ($rr as $b) {
                $b = is_array($b) ? $b : [];
                $tarif = self::angka($b['tarif'] ?? 0);
                if ($tarif > 0) {
                    $q = self::angka($b['qty'] ?? 0); $hr = self::angka($b['hari'] ?? 0);
                    $jml += round(($q ?: 1) * ($hr ?: 1) * $tarif);
                } else { $jml += round(self::angka($b['jumlah'] ?? 0)); }
            }
            return $jml;
        };
        // nilai tiap kategori: Nominal (atau total rincian), lalu Persentase dari sisa setelah Nominal, lalu Sisa Penerimaan
        $nilaiInduk = []; $totalNominal = 0.0;
        foreach ($induk as $i => $x) {
            $x = is_array($x) ? $x : [];
            $nilaiInduk[$i] = 0.0;
            if ((string) ($x['tipe'] ?? 'harga') === 'harga') {
                $nilaiInduk[$i] = !empty($x['rinci']) ? $jmlRincian($x) : round(self::angka($x['nilai'] ?? 0));
                $totalNominal += $nilaiInduk[$i];
            }
        }
        $tetapInduk = $totalNominal;
        foreach ($induk as $i => $x) {
            $x = is_array($x) ? $x : [];
            if ((string) ($x['tipe'] ?? 'harga') === 'persen') {
                $nilaiInduk[$i] = round(($tot_terima_rab - $totalNominal) * self::angka($x['persen'] ?? 0) / 100);
                $tetapInduk += $nilaiInduk[$i];
            }
        }
        foreach ($induk as $i => $x) {
            $x = is_array($x) ? $x : [];
            if ((string) ($x['tipe'] ?? 'harga') === 'sisa') { $nilaiInduk[$i] = $tot_terima_rab - $tetapInduk; }
        }
        if ($tot_terima_rab > 0 && $tetapInduk > $tot_terima_rab + 0.5) {
            $m[] = 'RAB: total kategori Nominal dan Persentase melebihi penerimaan sebesar Rp ' . number_format($tetapInduk - $tot_terima_rab, 0, ',', '.');
        }
        foreach ($induk as $i => $x) {
            $x = is_array($x) ? $x : [];
            $h = self::hurufUrut($i);
            $nm = $isi($x['nama'] ?? '') ? '"' . trim((string) $x['nama']) . '"' : 'kategori ' . $h;
            if (!$isi($x['nama'] ?? '')) { $m[] = 'RAB: nama kategori ' . $h . ' belum diisi'; }
            $tipe = (string) ($x['tipe'] ?? 'harga');
            if ($tipe === 'harga') {
                if (!empty($x['rinci'])) {
                    $rr = $semuaRincian($x);
                    if (!$rr) { $m[] = 'RAB: rincian ' . $nm . ' belum ada baris'; continue; }
                    $jml = 0.0; $tanpaNama = 0;
                    foreach ($rr as $b) {
                        $b = is_array($b) ? $b : [];
                        if (!$isi($b['nama'] ?? '')) { $tanpaNama++; }
                        $tarif = self::angka($b['tarif'] ?? 0);
                        if ($tarif > 0) {
                            $q = self::angka($b['qty'] ?? 0); $hr = self::angka($b['hari'] ?? 0);
                            $jml += round(($q ?: 1) * ($hr ?: 1) * $tarif);
                        } else { $jml += round(self::angka($b['jumlah'] ?? 0)); }
                    }
                    if ($tanpaNama) { $m[] = 'RAB: uraian rincian ' . $nm . ' belum diisi'; }
                    elseif ($jml <= 0) { $m[] = 'RAB: nilai rincian ' . $nm . ' belum diisi'; }
                } elseif (round(self::angka($x['nilai'] ?? 0)) <= 0) {
                    $m[] = 'RAB: nilai ' . $nm . ' belum diisi';
                }
            } elseif ($tipe === 'persen' && self::angka($x['persen'] ?? 0) <= 0) {
                $m[] = 'RAB: persen ' . $nm . ' belum diisi';
            }
            // rincian pada Persentase / Sisa Penerimaan: harus ada isinya dan jumlahnya sama dengan nilai kategori
            if ($tipe !== 'harga' && !empty($x['rinci'])) {
                $rr = $semuaRincian($x);
                if (!$rr) { $m[] = 'RAB: rincian ' . $nm . ' belum ada baris'; continue; }
                $tanpaNama = 0;
                foreach ($rr as $b) { if (!$isi(is_array($b) ? ($b['nama'] ?? '') : '')) { $tanpaNama++; } }
                if ($tanpaNama) { $m[] = 'RAB: uraian rincian ' . $nm . ' belum diisi'; }
                else {
                    $selisih = round($nilaiInduk[$i]) - round($jmlRincian($x));
                    if ($selisih != 0) { $m[] = 'RAB: jumlah rincian ' . $nm . ' ' . ($selisih > 0 ? 'kurang' : 'lebih') . ' Rp ' . number_format(abs($selisih), 0, ',', '.') . ' dari nilai kategori'; }
                }
            }
        }

        $jd = (isset($d['jadwal']) && is_array($d['jadwal'])) ? $d['jadwal'] : [];
        $modeJd = (string) ($jd['mode'] ?? 'teks');
        if ($modeJd === 'tanggal') {
            $tglOk = function ($v) { $v = (string) $v; return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false; };
            $mulai = (string) ($jd['mulai'] ?? ''); $selesai = (string) ($jd['selesai'] ?? '');
            if (!$tglOk($mulai)) { $m[] = 'Jadwal: tanggal mulai belum diisi'; }
            if (!$tglOk($selesai)) { $m[] = 'Jadwal: tanggal selesai belum diisi'; }
            elseif ($tglOk($mulai) && strtotime($selesai) < strtotime($mulai)) { $m[] = 'Jadwal: tanggal selesai lebih awal dari tanggal mulai'; }
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
                if ($tanpaCentang) { $m[] = 'Jadwal: ada kegiatan yang belum dicentang jadwalnya'; }
            }
        } elseif ($modeJd === 'gantt') {
            $bulan = (isset($jd['bulan']) && is_array($jd['bulan'])) ? $jd['bulan'] : [];
            $rows = (isset($jd['rows']) && is_array($jd['rows'])) ? $jd['rows'] : [];
            if (!$bulan) { $m[] = 'Jadwal belum punya bulan'; }
            else { foreach ($bulan as $b) { if (!$isi(is_array($b) ? ($b['nama'] ?? '') : '')) { $m[] = 'Jadwal: nama bulan belum diisi'; break; } } }
            if (!$rows) { $m[] = 'Jadwal belum punya kegiatan'; }
            else {
                $tanpaNama = false; $tanpaCentang = false;
                foreach ($rows as $r) {
                    $r = is_array($r) ? $r : [];
                    if (!$isi($r['nama'] ?? '')) { $tanpaNama = true; }
                    $on = (isset($r['on']) && is_array($r['on'])) ? $r['on'] : [];
                    if (!array_filter($on)) { $tanpaCentang = true; }
                }
                if ($tanpaNama) { $m[] = 'Jadwal: nama kegiatan belum diisi'; }
                if ($tanpaCentang) { $m[] = 'Jadwal: ada kegiatan yang belum dicentang minggunya'; }
            }
        } elseif (self::teksRte($d['jadwalTeks'] ?? '') === '') {
            $m[] = 'Jadwal belum diisi';
        }

        foreach (\PoTtd::kekurangan(\PoTtd::bersihkan(isset($d['ttd']) ? $d['ttd'] : [])) as $pesan) {
            $m[] = $pesan;
        }
        return $m;
    }

    /** Tanggal PO dikirim (d/m/Y): kolom tanggal_dikirim; PO terkirim versi lama (belum ada kolom/isinya) memakai tanggal PO-nya. */
    protected function tglDikirimTeks($po) {
        if (trim((string) ($po['nomor_po'] ?? '')) === '') { return ''; }
        $t = (string) ($po['tanggal_dikirim'] ?? '');
        if ($t === '' || strpos($t, '0000') === 0 || !strtotime($t)) { $t = (string) ($po['tanggal_po'] ?? ''); }
        return ($t !== '' && strpos($t, '0000') !== 0 && strtotime($t)) ? date('d/m/Y', strtotime($t)) : '';
    }

    /** Balasan 422 untuk input yang ditolak; sertakan daftar kekurangan bila dari validasi Kirim. */
    protected function tolakInput($err) {
        $out = ['ok' => false, 'message' => $err];
        if ($this->errKirim) { $out['errors'] = $this->errKirim; }
        return $this->json($out, 422);
    }

    /**
     * Validasi & rapikan input form. Return [pesanError|null, data].
     * Form mengirim seluruh isian sebagai satu JSON di field 'isi_form' (hanya untuk perjalanan data);
     * di sini dipecah ke kolom-kolom po_kegiatan lewat PoSchema::kolomDariForm(). Tidak ada kolom JSON gabungan.
     */
    protected function bacaInput($f3, $orderRaw = null, $tglTetap = null) {
        $post = $f3->get('POST');

        $this->errKirim = [];
        if (((string) ($post['aksi'] ?? '')) === 'kirim') {
            $dd = json_decode((string) ($post['isi_form'] ?? ''), true);
            $kurang = $this->kekuranganKirim($post, is_array($dd) ? $dd : []);
            if ($kurang) {
                $this->errKirim = $kurang;
                return ['Data PO belum lengkap, semua isian wajib diisi kecuali alamat.', null];
            }
        }

        // Tanggal PO tidak diisi dari form (sama seperti PO Pengujian): dibuat sekali saat PO pertama kali disimpan (draft / kirim),
        // yaitu hari ini (WIB), lalu TETAP walau PO diedit / dikirim belakangan ($tglTetap = tanggal PO yang sudah tercatat).
        // Tanggal PO dikirim TIDAK dari form: dicatat otomatis (tanggal_dikirim) saat PO dikirim, dan jadi patokan bulan/tahun Nomor PO.
        $tgl = ($tglTetap !== null && $tglTetap !== '') ? (string) $tglTetap : \PoSchema::hariIniWib();
        $judul = trim((string) ($post['judul_kegiatan'] ?? ''));
        if ($judul === '') {
            return ['Judul Kegiatan wajib diisi.', null];
        }
        if (function_exists('mb_substr')) { $judul = mb_substr($judul, 0, 255); } else { $judul = substr($judul, 0, 255); }
        $alamat = trim((string) ($post['alamat'] ?? ''));

        $raw = (string) ($post['isi_form'] ?? '');
        if ($raw === '' || strlen($raw) > 3000000) {
            return ['Data PO kosong atau terlalu besar.', null];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['Data PO tidak valid.', null];
        }
        // nilai kolom biasa dan isi form dijaga selalu sama
        $decoded['tanggal'] = $tgl;
        $decoded['judul']   = $judul;
        $decoded['jenis']   = trim((string) ($post['jenis'] ?? ''));
        $decoded['alamat']  = $alamat;
        // Nilai Kontrak = total_nilai_kontrak order, baris pertama penerimaan; tidak bisa diubah dari form
        if (is_array($orderRaw) && (float) ($orderRaw['total_nilai_kontrak'] ?? 0) > 0) {
            $terima = (isset($decoded['rab']['terima']) && is_array($decoded['rab']['terima'])) ? array_values($decoded['rab']['terima']) : [];
            $kunci = ['nama' => 'Nilai Kontrak', 'nilai' => (float) $orderRaw['total_nilai_kontrak']];
            if ($terima && is_array($terima[0]) && preg_match('/^nilai kontrak$/i', trim((string) ($terima[0]['nama'] ?? '')))) {
                $terima[0] = $kunci;
            } else {
                array_unshift($terima, $kunci);
            }
            if (!isset($decoded['rab']) || !is_array($decoded['rab'])) { $decoded['rab'] = []; }
            $decoded['rab']['terima'] = $terima;
        }

        return [null, [
            'tanggal_po' => $tgl,
            'judul'      => $judul,
            'alamat'     => $alamat === '' ? null : $alamat,
            // dasar(+dasar_struktur), tujuan, ruang_lingkup, tim_pelaksana, rab, jadwal, jenis, ...; poin pembayaran ikut ditulis di kolom `dasar`
            'kolom'      => \PoSchema::kolomDariForm($decoded, $this->teksPembayaran($orderRaw, $decoded['perusahaan'] ?? '')),
        ]];
    }


    /**
     * POST /petunjuk-operasional/sertifikasi/simpan  (AJAX, balasan JSON)
     * Membuat PO baru: nomor PO diterbitkan dari po_nomor_urut + baris po_kegiatan dibuat dalam
     * satu transaksi (gagal simpan -> nomor tidak terbuang).
     */
    public function simpan($f3) {
        $this->requireAuth();
        if ($this->terlarangRoleLayananLain() || $this->terlarangMenulis()) {
            return $this->json(['ok' => false, 'message' => 'PO Sertifikasi tidak tersedia untuk role Anda.'], 403);
        }

        $kendala = $this->kendalaSkema();
        if ($kendala !== '') {
            return $this->json(['ok' => false, 'message' => $kendala], 500);
        }

        $orderId = (int) ($f3->get('POST.order_id') ?? 0);
        if ($orderId <= 0) {
            return $this->json(['ok' => false, 'message' => 'PO harus dibuat dari order. Pilih order dulu lewat Petunjuk Operasional > Sertifikasi.'], 422);
        }
        try {
            $rows = $this->queryOrderLayak($orderId);
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal validasi order saat simpan: ' . $e->getMessage());
            return $this->json(['ok' => false, 'message' => 'Gagal membaca order dari database SISERTIFIKASI.'], 500);
        }
        if (empty($rows[0])) {
            return $this->json(['ok' => false, 'message' => 'Order sertifikasi tidak ditemukan atau belum memenuhi syarat.'], 422);
        }

        list($err, $in) = $this->bacaInput($f3, $rows[0]);
        if ($err !== null) {
            return $this->tolakInput($err);
        }
        $status = $this->statusDariAksi($f3);

        try {
            $ada = \PoSchema::cariPoSertifikasi($this->db, $orderId);
            if ($ada) {
                return $this->json(['ok' => false, 'message' => 'Order ini sudah punya PO.', 'redirect' => $this->urlEdit($ada['id'])], 409);
            }

            \PoSchema::pastikanTabelNomor($this->db); // DDL harus di luar transaksi
            $this->db->begin();
            try {
                // [BARU] nomor baru diterbitkan hanya saat PO DIKIRIM (bulan/tahun = tanggal kirim, WIB); draft belum bernomor
                $nomor = ($status === 'terkirim')
                    ? \PoSchema::nomorBaru($this->db, \PoSchema::hariIniWib(), false)
                    : ['id' => null, 'detail_nomor' => null];
                $now = date('Y-m-d H:i:s');
                $poId = \PoSchema::sisipPo($this->db, array_merge($in['kolom'], [
                    'order_id'       => $orderId,
                    'layanan'        => \PoSchema::SERTIFIKASI,
                    'nomor_po'       => $nomor['detail_nomor'],
                    'id_nomor_urut'  => $nomor['id'],
                    'tanggal_po'     => $in['tanggal_po'],
                    'tanggal_dikirim' => ($status === 'terkirim') ? \PoSchema::sekarangWib() : null,
                    'judul_kegiatan' => $in['judul'],
                    'alamat'         => $in['alamat'],
                    'status'         => $status,
                    'created_by'     => (int) $this->getUserId(),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]));
                if ($poId <= 0) {
                    throw new \Exception('PO gagal dibuat.');
                }
                $this->tandaiKlaim($orderId, 'Y'); // order ini sekarang diklaim PO (draft pun dihitung)
                $this->db->commit();
            } catch (\Throwable $e) {
                try { $this->db->rollback(); } catch (\Throwable $eRb) {}
                throw $e;
            }
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal menyimpan PO: ' . $e->getMessage());
            return $this->json(['ok' => false, 'message' => 'Gagal menyimpan PO: ' . $e->getMessage()], 500);
        }

        $kirim = ($status === 'terkirim');
        $pesan = $kirim ? 'PO berhasil dibuat dan dikirim.' : 'PO berhasil disimpan sebagai draft.';
        // popup berhasil + pindah ke daftar PO ditangani di halaman form (tab sesuai status)
        return $this->json([
            'ok'             => true,
            'message'        => $pesan,
            'id'             => $poId,
            'nomor'          => (string) $nomor['detail_nomor'],
            'nomor_baru'     => $nomor['detail_nomor'] !== null,
            'tanggal_po'     => $in['tanggal_po'],
            'dikirim'        => $nomor['detail_nomor'] !== null ? date('d/m/Y') : '',
            'dibuat'         => date('d/m/Y'),
            'status'         => $status,
            'redirect'       => $this->urlEdit($poId),
            'redirect_index' => $this->urlIndex($kirim ? 'terkirim' : 'draft'),
        ]);
    }

    /**
     * POST /petunjuk-operasional/sertifikasi/@id/update  (AJAX, balasan JSON)
     * Nomor PO TIDAK berubah saat diedit (sama seperti PO OPTI).
     */
    public function update($f3, $params) {
        $this->requireAuth();
        if ($this->terlarangRoleLayananLain() || $this->terlarangMenulis()) {
            return $this->json(['ok' => false, 'message' => 'PO Sertifikasi tidak tersedia untuk role Anda.'], 403);
        }

        $kendala = $this->kendalaSkema();
        if ($kendala !== '') {
            return $this->json(['ok' => false, 'message' => $kendala], 500);
        }

        try {
            $po = \PoSchema::ambilPoSertifikasi($this->db, (int) $params['id']);
        } catch (\Throwable $e) {
            $po = null;
        }
        if (!$po) {
            return $this->json(['ok' => false, 'message' => 'PO Sertifikasi tidak ditemukan.'], 404);
        }
        $statusLama = (string) ($po['status'] ?? 'draft');
        if (!in_array($statusLama, ['', 'draft'], true)) {
            // PO yang sudah dikirim terkunci; perubahan hanya lewat permintaan revisi (Review)
            return $this->json(['ok' => false, 'message' => $statusLama === 'terkirim'
                ? 'PO ini sudah terkirim dan tidak bisa diedit lagi. Perubahan hanya bisa lewat permintaan revisi.'
                : 'PO ini sudah berstatus "' . $statusLama . '" dan tidak bisa diubah lagi.'], 409);
        }
        $status = $this->statusDariAksi($f3);

        $orderRaw = null;
        try {
            $ro = $this->queryOrderLayak((int) $po['order_id'], false);
            $orderRaw = !empty($ro[0]) ? $ro[0] : null;
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal baca order saat update: ' . $e->getMessage());
        }

        // PO yang sudah bernomor: nomornya TETAP; Tanggal PO (tanggal dibuat) juga TETAP (dibuat saat PO pertama kali disimpan), tanggal dikirimnya tidak berubah
        $sudahNomor = trim((string) ($po['nomor_po'] ?? '')) !== '';
        $tglLama = (string) ($po['tanggal_po'] ?? '');
        $tglTetap = ($tglLama !== '' && strpos($tglLama, '0000') !== 0 && strtotime($tglLama)) ? date('Y-m-d', strtotime($tglLama)) : null;
        list($err, $in) = $this->bacaInput($f3, $orderRaw, $tglTetap);
        if ($err !== null) {
            return $this->tolakInput($err);
        }

        $nomorBaru = null;
        try {
            $ubah = array_merge($in['kolom'], [
                'tanggal_po'     => $in['tanggal_po'],
                'judul_kegiatan' => $in['judul'],
                'alamat'         => $in['alamat'],
                'status'         => $status,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            if (!$sudahNomor && $status === 'terkirim') {
                // [BARU] draft dikirim untuk pertama kali -> nomor diterbitkan sekarang (bulan/tahun = hari kirim, WIB);
                // nomor + update PO satu transaksi supaya gagal simpan tidak membuang nomor
                \PoSchema::pastikanTabelNomor($this->db); // DDL harus di luar transaksi
                $this->db->begin();
                try {
                    $nomorBaru = \PoSchema::nomorBaru($this->db, \PoSchema::hariIniWib(), false);
                    $ubah['tanggal_dikirim'] = \PoSchema::sekarangWib();
                    $ubah['nomor_po']      = $nomorBaru['detail_nomor'];
                    $ubah['id_nomor_urut'] = $nomorBaru['id'];
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
            error_log('[PO Sertifikasi] gagal memperbarui PO: ' . $e->getMessage());
            return $this->json(['ok' => false, 'message' => 'Gagal memperbarui PO: ' . $e->getMessage()], 500);
        }
        $this->tandaiKlaim((int) $po['order_id'], 'Y'); // PO lama (dibuat sebelum kolom penanda ada) ikut terisi saat disimpan ulang
        $nomorAkhir = $nomorBaru ? $nomorBaru['detail_nomor'] : (string) ($po['nomor_po'] ?? '');

        $kirim = ($status === 'terkirim');
        $pesan = $kirim ? 'PO berhasil diperbarui dan dikirim.' : 'PO berhasil diperbarui sebagai draft.';
        return $this->json([
            'ok'             => true,
            'message'        => $pesan,
            'id'             => (int) $po['id'],
            'nomor'          => $nomorAkhir,
            'nomor_baru'     => $nomorBaru !== null,
            'tanggal_po'     => $in['tanggal_po'],
            'dikirim'        => $nomorBaru !== null ? date('d/m/Y') : $this->tglDikirimTeks($po),
            'status'         => $status,
            'redirect_index' => $this->urlIndex($kirim ? 'terkirim' : 'draft'),
        ]);
    }

    /**
     * GET /petunjuk-operasional/sertifikasi/review
     * Review PO Sertifikasi. Sementara halaman penanda (alur review menyusul), sama seperti Review PO Pengujian.
     * Akses: Superadmin, Tim Kerja Sertifikasi, dan Ketua Tim Sertifikasi.
     */
    public function review($f3) {
        $this->requireAuth();
        if (!($this->isSuperadmin() || $this->isRoleSertifikasi())) {
            $f3->error(403, 'Halaman ini hanya untuk Tim Kerja dan Ketua Tim Sertifikasi.');
            return;
        }

        $this->render(
            'petunjuk_operasional/po_sertifikasi/review.html',
            'Review PO Sertifikasi',
            'review_po_sertifikasi'
        );
    }
}
