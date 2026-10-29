<?php

/**
 * PoPengujian -- helper penyimpanan PO PENGUJIAN ke tabel po_kegiatan (layanan = 'pengujian').
 *
 * Dipisah dari PoSchema supaya PO Sertifikasi / PO OPTI tidak tersentuh. Memakai ulang kolom po_kegiatan yang sudah
 * ada (judul_kegiatan, dasar, tujuan, ruang_lingkup, tim_pelaksana, rab, jadwal, jenis, nama_perusahaan, no_penawaran,
 * tanggal_po, nomor_po, status, created_by, ...) dan menambah kolom yang baru dibutuhkan Pengujian
 * (lokasi, jenis_layanan, estimasi_contoh, tanda_tangan) secara otomatis (self-healing, pola yang sama dengan PoSchema).
 *
 * Catatan desain:
 *   - NOMOR PO Pengujian: "<urut>/PO/BBSPJIS/SVPK/<ROMAWI>/<TAHUN>", OTOMATIS dari tabel po_nomor_urut (nomorBaru()) --
 *     satu deret per tahun bersama PO OPTI & PO Sertifikasi (urut = angka tertinggi tahun itu + 1), terbit sekali saat PO
 *     pertama kali disimpan dan tidak berubah saat diedit. Penyisipan nomor + PO dalam satu transaksi (gagal simpan ->
 *     nomor tidak terbuang). id_nomor_urut menunjuk baris po_nomor_urut-nya.
 *   - ORDER: PO Pengujian dibuat dari order yang dipilih di modal (data: tb_arsipsurat di sil2020, sama seperti modal
 *     order Sertifikasi). Id-nya disimpan di kolom BARU po_kegiatan.id_arsip_surat. po_kegiatan.order_id sengaja tetap NULL:
 *     query review PO OPTI memakai JOIN order_layanan ON id = order_id, jadi PO Pengujian otomatis tidak ikut masuk alur
 *     review OPTI dan tidak bisa "nyasar" ke order_layanan yang kebetulan punya id sama.
 *   - Isian rich text (Dasar, Tujuan, Ruang Lingkup, uraian tugas) disimpan sebagai HTML yang SUDAH dibersihkan (whitelist tag).
 *
 * Kompatibel PHP 7.2.
 */
class PoPengujian
{
    const LAYANAN = 'pengujian';

    /** Kolom tambahan khusus Pengujian: nama => definisi (dibuat otomatis bila belum ada). */
    protected static $kolomBaru = array(
        'lokasi'          => 'VARCHAR(255) NULL',
        'jenis_layanan'   => 'VARCHAR(150) NULL',
        'estimasi_contoh' => 'LONGTEXT NULL',
        'tanda_tangan'    => 'LONGTEXT NULL',
        // order yang dipilih di modal "Pilih Order Pengujian" = tb_arsipsurat.id_arsip (database sil2020)
        'id_arsip_surat'  => 'INT NULL',
        // PO untuk BANYAK order: snapshot daftar order + pengaturan tampilan daftar mitra di dokumen (JSON)
        'daftar_order'    => 'LONGTEXT NULL',
        // jadwal pengambilan contoh per perusahaan (PO banyak order; opsional): JSON {mode, hari[{tgl, regu[{orders, ppc, supir}]}]}
        'jadwal_sampling' => 'LONGTEXT NULL',
    );

    protected static $sudahJalan = false;
    protected static $siap = false;
    protected static $kendala = '';

    /* ------------------------------------------------------------------ */
    /* Skema                                                              */
    /* ------------------------------------------------------------------ */

    /** Pastikan skema siap. Aman dipanggil di setiap request (1-2 query ringan bila sudah siap). */
    public static function pastikan($db)
    {
        if (self::$sudahJalan) {
            return self::$siap;
        }
        self::$sudahJalan = true;

        if (!\PoSchema::pastikan($db)) {
            self::$kendala = \PoSchema::kendalaSertifikasi();
            if (self::$kendala === '') {
                self::$kendala = 'Skema po_kegiatan belum siap (kolom layanan belum ada).';
            }
            return false;
        }
        $kendalaPoSchema = \PoSchema::kendalaSertifikasi();
        if ($kendalaPoSchema !== '' && strpos($kendalaPoSchema, 'foreign key') === false) {
            // kolom bersama (judul_kegiatan, dasar, dst.) ada yang belum terbentuk
            self::$kendala = $kendalaPoSchema;
            return false;
        }

        try {
            $cols = array();
            foreach ($db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) {
                $cols[$c['Field']] = $c;
            }
            foreach (self::$kolomBaru as $nama => $def) {
                if (!isset($cols[$nama])) {
                    try { $db->exec('ALTER TABLE po_kegiatan ADD COLUMN `' . $nama . '` ' . $def); }
                    catch (\Throwable $e) { error_log('[PoPengujian] ' . $e->getMessage()); }
                }
            }
            // PO Pengujian belum terhubung ke order -> order_id harus boleh NULL
            if (isset($cols['order_id']) && $cols['order_id']['Null'] === 'NO') {
                try {
                    $db->exec('ALTER TABLE po_kegiatan MODIFY `order_id` ' . $cols['order_id']['Type'] . ' NULL');
                } catch (\Throwable $e) {
                    error_log('[PoPengujian] gagal membuat order_id boleh NULL: ' . $e->getMessage());
                }
            }

            // PO untuk banyak order: tiap order (id_arsip) hanya boleh ada di satu PO Pengujian
            try {
                $db->exec('CREATE TABLE IF NOT EXISTS po_pengujian_order (
                    id_arsip INT NOT NULL, id_po INT NOT NULL, urut INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (id_arsip), KEY idx_po (id_po)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            } catch (\Throwable $e) {
                error_log('[PoPengujian] gagal membuat tabel po_pengujian_order: ' . $e->getMessage());
                self::$kendala = 'Tabel po_pengujian_order belum bisa dibuat otomatis (izin CREATE TABLE kurang).';
                return false;
            }

            // cek ulang hasilnya
            $ada = array();
            $orderNullable = false;
            foreach ($db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) {
                $ada[] = $c['Field'];
                if ($c['Field'] === 'order_id') {
                    $orderNullable = ($c['Null'] === 'YES');
                }
            }
            $kurang = array();
            foreach (array_keys(self::$kolomBaru) as $k) {
                if (!in_array($k, $ada, true)) {
                    $kurang[] = $k;
                }
            }
            if ($kurang) {
                $sql = array();
                foreach ($kurang as $k) { $sql[] = 'ADD COLUMN `' . $k . '` ' . self::$kolomBaru[$k]; }
                self::$kendala = 'Kolom po_kegiatan berikut belum berhasil dibuat otomatis: ' . implode(', ', $kurang)
                    . '. Cek hak ALTER TABLE user MySQL, atau jalankan manual: ALTER TABLE po_kegiatan ' . implode(', ', $sql) . ';';
                return false;
            }
            if (!$orderNullable) {
                self::$kendala = 'Kolom po_kegiatan.order_id belum boleh NULL (dibutuhkan PO Pengujian). Jalankan manual: '
                    . 'ALTER TABLE po_kegiatan MODIFY order_id ' . (isset($cols['order_id']) ? $cols['order_id']['Type'] : 'INT') . ' NULL;';
                return false;
            }
            self::$siap = true;
        } catch (\Throwable $e) {
            error_log('[PoPengujian] gagal memastikan skema: ' . $e->getMessage());
            self::$kendala = 'Gagal memeriksa skema po_kegiatan: ' . $e->getMessage();
            return false;
        }
        return true;
    }

    /** Pesan kendala skema ('' = aman). Panggil setelah pastikan(). */
    public static function kendala()
    {
        return self::$siap ? '' : self::$kendala;
    }

    /* ------------------------------------------------------------------ */
    /* Pembersih & util                                                   */
    /* ------------------------------------------------------------------ */

    protected static $tagBoleh = array(
        'b', 'strong', 'i', 'em', 'u', 'ol', 'ul', 'li', 'p', 'div', 'span', 'sub', 'sup',
        'table', 'thead', 'tbody', 'tr', 'td', 'th', 'colgroup', 'col',
    );

    /** Properti CSS yang boleh bertahan (editor memakainya untuk tabel, rata teks, warna sel). */
    protected static $cssBoleh = array(
        'text-align', 'font-weight', 'font-style', 'text-decoration', 'width', 'height', 'vertical-align', 'background-color', 'color',
        'border', 'border-top', 'border-bottom', 'border-left', 'border-right', 'border-collapse', 'border-style', 'border-color',
        'border-width', 'padding', 'margin-left', 'table-layout',
    );

    /**
     * Rich text -> HTML aman (whitelist). Tag: b/strong/i/em/u/ol/ul/li/p/div/span/sub/sup/br dan tabel (table, thead, tbody,
     * tr, td, th, colgroup, col). Atribut: type (ol/ul: 1,a,A,i,I), colspan/rowspan/width (tabel) dan style terbatas
     * ($cssBoleh, tanpa url()/expression). Cermin dari filter di form (JS).
     */
    public static function bersihHtml($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="__r">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('__r');
        if (!$root) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }
        return self::bersihNode($root);
    }

    protected static function bersihStyle($style)
    {
        $out = array();
        foreach (explode(';', (string) $style) as $d) {
            $p = explode(':', $d, 2);
            if (count($p) !== 2) { continue; }
            $prop = strtolower(trim($p[0]));
            $val = trim(str_replace(array('!important', '!IMPORTANT'), '', $p[1]));
            if ($val === '' || !in_array($prop, self::$cssBoleh, true)) { continue; }
            if (preg_match('/url\s*\(|expression|javascript|[<>"\\\\]/i', $val)) { continue; }
            $out[] = $prop . ':' . $val;
        }
        return implode(';', $out);
    }

    protected static function bersihNode(\DOMNode $n)
    {
        $out = '';
        foreach ($n->childNodes as $c) {
            if ($c->nodeType === XML_TEXT_NODE) {
                $out .= htmlspecialchars($c->nodeValue, ENT_QUOTES, 'UTF-8');
                continue;
            }
            if ($c->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $t = strtolower($c->nodeName);
            if ($t === 'br') {
                $out .= '<br>';
                continue;
            }
            if (in_array($t, array('script', 'style'), true)) {
                continue; // isi script/style dibuang sama sekali
            }
            $inner = self::bersihNode($c);
            if (!in_array($t, self::$tagBoleh, true)) {
                $out .= $inner;
                continue;
            }
            $at = '';
            if ($t === 'ol' || $t === 'ul') {
                $ty = $c->getAttribute('type');
                if ($ty !== '' && preg_match('/^[1aAiI]$/', $ty)) {
                    $at .= ' type="' . $ty . '"';
                }
            }
            if ($t === 'td' || $t === 'th') {
                foreach (array('colspan', 'rowspan') as $a) {
                    $v = $c->getAttribute($a);
                    if ($v !== '' && ctype_digit($v) && (int) $v >= 1 && (int) $v <= 50) { $at .= ' ' . $a . '="' . (int) $v . '"'; }
                }
            }
            if (in_array($t, array('td', 'th', 'col', 'table'), true)) {
                $w = $c->getAttribute('width');
                if ($w !== '' && preg_match('/^\d{1,4}%?$/', $w)) { $at .= ' width="' . $w . '"'; }
            }
            $st = self::bersihStyle($c->getAttribute('style'));
            if ($st !== '') {
                $at .= ' style="' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . '"';
            }
            $out .= '<' . $t . $at . '>' . $inner . '</' . $t . '>';
        }
        return $out;
    }

    /** Teks polos dari HTML (untuk cek "kosong"). */
    public static function teksPolos($html)
    {
        $t = preg_replace('/<[^>]*>/', ' ', (string) $html);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace("\xC2\xA0", ' ', $t);
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    protected static function teks($v, $maks = 0)
    {
        if (is_array($v) || $v === null) {
            return null;
        }
        $t = trim((string) $v);
        if ($t === '') {
            return null;
        }
        if ($maks > 0) {
            $t = function_exists('mb_substr') ? mb_substr($t, 0, $maks) : substr($t, 0, $maks);
        }
        return $t;
    }

    protected static function larik($v)
    {
        return is_array($v) ? $v : array();
    }

    protected static function json($v)
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    protected static function tanggalValid($v)
    {
        $t = trim((string) $v);
        $dt = \DateTime::createFromFormat('Y-m-d', $t);
        return ($dt && $dt->format('Y-m-d') === $t) ? $t : null;
    }

    /* ------------------------------------------------------------------ */
    /* Nomor PO                                                           */
    /* ------------------------------------------------------------------ */

    /** Nomor urut tertinggi tahun $tahun yang terbaca dari nomor_po semua PO (termasuk nomor lama yang diisi manual). */
    protected static function urutanLegacy($db, $tahun)
    {
        $max = 0;
        try {
            $rows = $db->exec("SELECT nomor_po FROM po_kegiatan WHERE nomor_po LIKE ?", array(1 => '%/' . (int) $tahun));
            foreach ($rows as $r) {
                if (preg_match('/^\s*(\d+)\s*\//', (string) $r['nomor_po'], $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
        } catch (\Throwable $e) {
        }
        return $max;
    }

    /**
     * Terbitkan Nomor PO Pengujian: "N/PO/BBSPJIS/SVPK/ROMAWI/TAHUN". Satu deret per TAHUN bersama PO OPTI dan PO
     * Sertifikasi (N = MAX(nomor_urut) tahun itu dari po_nomor_urut DAN dari nomor_po lama + 1; kembali ke 1 saat ganti
     * tahun), bulan & tahun dari Tanggal Surat PO. Pengambilan nomor dikunci per tahun (GET_LOCK, kunci yang sama dengan
     * PO OPTI/Sertifikasi); UNIQUE KEY po_nomor_urut tetap jadi pengaman terakhir (bentrok -> coba angka berikutnya, maks. 5x).
     * Panggil \PoSchema::pastikanTabelNomor() SEBELUM membuka transaksi (DDL meng-commit transaksi yang berjalan).
     *
     * @return array{id:int, detail_nomor:string, nomor_urut:int}
     */
    public static function nomorBaru($db, $tanggalPo)
    {
        $ts    = strtotime((string) $tanggalPo) ?: time();
        $bulan = (int) date('n', $ts);
        $tahun = (int) date('Y', $ts);

        $namaKunci  = 'po_nomor_urut_' . $tahun;
        $kunciDapat = false;
        try {
            $r = $db->exec('SELECT GET_LOCK(?, 5) AS k', array(1 => $namaKunci));
            $kunciDapat = !empty($r) && (int) (isset($r[0]['k']) ? $r[0]['k'] : 0) === 1;
        } catch (\Throwable $e) {
        }

        try {
            for ($percobaan = 0; $percobaan < 5; $percobaan++) {
                $row = $db->exec('SELECT COALESCE(MAX(nomor_urut), 0) AS maxu FROM po_nomor_urut WHERE tahun = ?', array(1 => $tahun));
                $maxTercatat = (int) (isset($row[0]['maxu']) ? $row[0]['maxu'] : 0);
                $urutBaru    = max($maxTercatat, self::urutanLegacy($db, $tahun)) + 1 + $percobaan;
                $detailNomor = $urutBaru . '/PO/BBSPJIS/SVPK/' . \PoSchema::angkaRomawi($bulan) . '/' . $tahun;
                try {
                    $db->exec(
                        'INSERT INTO po_nomor_urut (nomor_urut, bulan, tahun, detail_nomor, updated_at) VALUES (?, ?, ?, ?, NOW())',
                        array(1 => $urutBaru, 2 => $bulan, 3 => $tahun, 4 => $detailNomor)
                    );
                    $idRow = $db->exec('SELECT LAST_INSERT_ID() AS id');
                    return array('id' => (int) (isset($idRow[0]['id']) ? $idRow[0]['id'] : 0), 'detail_nomor' => $detailNomor, 'nomor_urut' => $urutBaru);
                } catch (\Throwable $e) {
                    continue; // bentrok UNIQUE -> coba nomor berikutnya
                }
            }
        } finally {
            if ($kunciDapat) {
                try { $db->exec('SELECT RELEASE_LOCK(?)', array(1 => $namaKunci)); } catch (\Throwable $e) {}
            }
        }
        throw new \Exception('Gagal membuat Nomor PO baru, silakan coba simpan ulang.');
    }

    /* ------------------------------------------------------------------ */
    /* Order (arsip surat permintaan)                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Jenis pengujian untuk pilihan di modal "Pilih order". Pengelompokan mengikuti tb_arsipsurat.layanan_Kd_contoh.
     * 'kode' = nilai layanan_Kd_contoh yang masuk jenis itu (boleh lebih dari satu). Mau menambah / memisah jenis
     * (mis. limbah cair dipisah dari Air): tambah satu baris di sini, tidak ada yang lain yang perlu diubah.
     * Order yang layanan_Kd_contoh-nya 0 / tidak ada di daftar ini tidak ditampilkan.
     */
    public static $JENIS = array(
        'air'          => array('nama' => 'Air',                  'ket' => 'Termasuk limbah cair',   'kode' => array(1)),
        'kertas'       => array('nama' => 'Kertas, karton, pulp', 'ket' => '',                       'kode' => array(2)),
        'udara'        => array('nama' => 'Udara',                'ket' => 'Emisi dan ambien',       'kode' => array(4)),
        'limbah_padat' => array('nama' => 'Limbah padat',         'ket' => '',                       'kode' => array(6)),
        'amdk'         => array('nama' => 'AMDK',                 'ket' => 'Air minum dalam kemasan', 'kode' => array(7)),
        'bkb'          => array('nama' => 'BKB',                  'ket' => '',                       'kode' => array(13)),
    );

    /** Semua kode layanan_Kd_contoh yang dikenal (gabungan semua jenis). */
    public static function semuaKodeJenis()
    {
        $out = array();
        foreach (self::$JENIS as $j) { foreach ($j['kode'] as $k) { $out[] = (int) $k; } }
        return $out;
    }

    /** layanan_Kd_contoh -> kunci jenis ('air', 'kertas', ...); '' kalau tidak dikenal. */
    public static function jenisDariKode($kode)
    {
        foreach (self::$JENIS as $key => $j) {
            if (in_array((int) $kode, $j['kode'], true)) { return $key; }
        }
        return '';
    }

    /**
     * Syarat surat yang dianggap ORDER PENGUJIAN:
     *   - surat tercatat di tb_penerimaan_contoh (kolom id_arsip) -- tidak lagi memakai id_layanan, dan
     *   - layanan_Kd_contoh termasuk salah satu jenis di $JENIS (yang tidak dikenal / 0 dilewati dulu).
     * IN (subquery) dipilih supaya MySQL membangun daftar id_arsip sekali, bukan memindai penerimaan contoh untuk tiap surat.
     */
    protected static function syaratOrder()
    {
        $kode = self::semuaKodeJenis();
        return " AND a.id_arsip IN (SELECT pc.id_arsip FROM tb_penerimaan_contoh pc)"
             . " AND a.layanan_Kd_contoh IN (" . implode(',', array_map('intval', $kode)) . ")";
    }

    /**
     * Daftar order untuk modal pilih order. Sumber: tb_arsipsurat (+ tb_customer) di sil2020 -- OptiDatabase otomatis
     * mengarahkannya ke sil2020. $id > 0 -> satu order saja. $kecualiSudahPo=true -> sembunyikan order yang sudah
     * punya PO Pengujian. $q = kata kunci (nomor surat / perihal / nama mitra). Maksimal $batas baris, terbaru dulu (urut id_arsip: surat penerimaan contoh sering tanpa tanggal surat, jadi tanggal tidak dipakai mengurutkan).
     * $hanyaPengujian=false -> tanpa syaratOrder() (dipakai membaca order asal PO yang sudah tersimpan, untuk banner).
     */
    public static function queryOrder($db, $id = 0, $q = '', $kecualiSudahPo = true, $batas = 100, $hanyaPengujian = true, $ids = null, $kecualiPo = 0, $jenis = '')
    {
        // $jenis = kunci jenis ('air', 'kertas', ...): hanya order jenis itu. Kunci tidak dikenal -> kosong.
        $jenis = trim((string) $jenis);
        if ($jenis !== '' && !isset(self::$JENIS[$jenis])) { return array(); }
        $sql = "SELECT a.id_arsip, a.nomor_surat, a.perihal, a.tanggal_surat, a.tanggal_terima, a.id_customer, a.layanan_Kd_contoh,
                       c.nmcustomer, c.pt_cv, c.alamatcustomer,
                       (SELECT pc.No_order FROM tb_penerimaan_contoh pc WHERE pc.id_arsip = a.id_arsip ORDER BY pc.id_pc DESC LIMIT 1) AS no_order_pc,
                       (SELECT pc.Ket_contoh FROM tb_penerimaan_contoh pc WHERE pc.id_arsip = a.id_arsip ORDER BY pc.id_pc DESC LIMIT 1) AS ket_contoh_pc,
                       (SELECT pc.Tanggal_order FROM tb_penerimaan_contoh pc WHERE pc.id_arsip = a.id_arsip ORDER BY pc.id_pc DESC LIMIT 1) AS tgl_order_pc
                FROM tb_arsipsurat a
                LEFT JOIN tb_customer c ON c.id_customer = a.id_customer
                WHERE 1 = 1";
        if ($hanyaPengujian) {
            $sql .= self::syaratOrder();
        }
        if ($jenis !== '') {
            $sql .= " AND a.layanan_Kd_contoh IN (" . implode(',', array_map('intval', self::$JENIS[$jenis]['kode'])) . ")";
        }
        $args = array();
        $i = 1;
        if ($id > 0) {
            $sql .= " AND a.id_arsip = ?";
            $args[$i++] = (int) $id;
        }
        if (is_array($ids)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if (!$ids) { return array(); }
            $sql .= " AND a.id_arsip IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
            foreach ($ids as $x) { $args[$i++] = $x; }
        }
        if ($kecualiSudahPo) {
            // $kecualiPo = PO yang sedang diedit: order miliknya sendiri tetap boleh muncul / dipilih
            $sql .= " AND NOT EXISTS (SELECT 1 FROM po_kegiatan pk WHERE pk.id_arsip_surat = a.id_arsip AND pk.layanan = ? AND pk.id <> ?)"
                  . " AND NOT EXISTS (SELECT 1 FROM po_pengujian_order x WHERE x.id_arsip = a.id_arsip AND x.id_po <> ?)";
            $args[$i++] = self::LAYANAN;
            $args[$i++] = (int) $kecualiPo;
            $args[$i++] = (int) $kecualiPo;
        }
        $q = trim((string) $q);
        if ($q !== '') {
            $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $q) . '%';
            $sql .= " AND (a.nomor_surat LIKE ? OR a.perihal LIKE ? OR c.nmcustomer LIKE ?)";
            $args[$i++] = $like;
            $args[$i++] = $like;
            $args[$i++] = $like;
        }
        $sql .= " ORDER BY a.id_arsip DESC LIMIT " . max(1, (int) $batas);
        return $db->exec($sql, $args);
    }

    /** Baris tb_arsipsurat -> larik untuk JSON modal / banner form. */
    public static function petakanOrder(array $r)
    {
        $tgl = isset($r['tanggal_surat']) ? (string) $r['tanggal_surat'] : '';
        $tglFmt = ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) ? date('d/m/Y', strtotime($tgl)) : '';
        return array(
            'id'            => (int) $r['id_arsip'],
            'nomor_surat'   => trim((string) (isset($r['nomor_surat']) ? $r['nomor_surat'] : '')),
            'perihal'       => trim((string) (isset($r['perihal']) ? $r['perihal'] : '')),
            'mitra'         => \Customer::formatNamaPerusahaan(isset($r['pt_cv']) ? $r['pt_cv'] : '', isset($r['nmcustomer']) ? $r['nmcustomer'] : ''),
            'alamat'        => trim((string) (isset($r['alamatcustomer']) ? $r['alamatcustomer'] : '')),
            'tanggal_surat' => $tglFmt,
            'tanggal_order' => (isset($r['tgl_order_pc']) && $r['tgl_order_pc'] !== '' && strpos((string) $r['tgl_order_pc'], '0000') !== 0 && strtotime($r['tgl_order_pc'])) ? date('d/m/Y', strtotime($r['tgl_order_pc'])) : '',
            'jenis'         => self::jenisDariKode(isset($r['layanan_Kd_contoh']) ? $r['layanan_Kd_contoh'] : 0),
            'no_order'      => trim((string) (isset($r['no_order_pc']) ? $r['no_order_pc'] : '')),
            'ket_contoh'    => trim((string) (isset($r['ket_contoh_pc']) ? $r['ket_contoh_pc'] : '')),
        );
    }

    /**
     * Ringkasan untuk langkah "pilih jenis" di modal: tiap jenis + jumlah order yang BELUM punya PO Pengujian
     * ($kecualiPo = PO yang sedang diedit: order miliknya tetap dihitung). Urutan mengikuti $JENIS.
     */
    public static function ringkasJenis($db, $kecualiPo = 0)
    {
        $sql = "SELECT a.layanan_Kd_contoh AS kode, COUNT(*) AS jml
                FROM tb_arsipsurat a
                WHERE 1 = 1" . self::syaratOrder()
             . " AND NOT EXISTS (SELECT 1 FROM po_kegiatan pk WHERE pk.id_arsip_surat = a.id_arsip AND pk.layanan = ? AND pk.id <> ?)"
             . " AND NOT EXISTS (SELECT 1 FROM po_pengujian_order x WHERE x.id_arsip = a.id_arsip AND x.id_po <> ?)"
             . " GROUP BY a.layanan_Kd_contoh";
        $rows = $db->exec($sql, array(1 => self::LAYANAN, 2 => (int) $kecualiPo, 3 => (int) $kecualiPo));
        $jml = array();
        foreach ($rows as $r) { $jml[(int) $r['kode']] = (int) $r['jml']; }
        $out = array();
        foreach (self::$JENIS as $key => $j) {
            $n = 0;
            foreach ($j['kode'] as $k) { if (isset($jml[(int) $k])) { $n += $jml[(int) $k]; } }
            $out[] = array('jenis' => $key, 'nama' => $j['nama'], 'ket' => $j['ket'], 'jumlah' => $n);
        }
        return $out;
    }

    /** Id PO Pengujian yang sudah memakai order (id_arsip) ini (0 = belum ada). */
    public static function poDariOrder($db, $idArsip)
    {
        $r = $db->exec(
            "SELECT id FROM po_kegiatan WHERE layanan = ? AND id_arsip_surat = ? LIMIT 1",
            array(1 => self::LAYANAN, 2 => (int) $idArsip)
        );
        if (!empty($r)) { return (int) $r[0]['id']; }
        $r = $db->exec("SELECT id_po FROM po_pengujian_order WHERE id_arsip = ? LIMIT 1", array(1 => (int) $idArsip));
        return empty($r) ? 0 : (int) $r[0]['id_po'];
    }

    /** "12,15,15,abc" -> [12, 15] (urutan dipertahankan, tanpa duplikat, maksimal $maks). */
    public static function idDariCsv($v, $maks = 300)
    {
        $out = array();
        foreach (explode(',', is_array($v) ? '' : (string) $v) as $x) {
            $n = (int) trim($x);
            if ($n > 0 && !in_array($n, $out, true)) { $out[] = $n; }
            if (count($out) >= $maks) { break; }
        }
        return $out;
    }

    /** Catat order milik sebuah PO (ganti seluruhnya). Panggil di dalam transaksi pemanggil; id_arsip yang sudah dipakai PO lain -> exception. */
    public static function tulisOrderPo($db, $idPo, array $ids)
    {
        $db->exec("DELETE FROM po_pengujian_order WHERE id_po = ?", array(1 => (int) $idPo));
        $urut = 0;
        foreach ($ids as $id) {
            $db->exec("INSERT INTO po_pengujian_order (id_arsip, id_po, urut) VALUES (?, ?, ?)", array(1 => (int) $id, 2 => (int) $idPo, 3 => ++$urut));
        }
    }

    /**
     * Jadwal pengambilan contoh per perusahaan (bagian opsional dokumen PO banyak order).
     * Bentuk: ['mode' => 'off'|'tabel', 'hari' => [['tgl' => 'Y-m-d', 'regu' => [['orders' => [id,...], 'ppc' => [nama,...], 'supir' => [nama,...]]]]]]
     * $idOrder (opsional) = id order yang sah di PO ini; id lain dibuang.
     */
    public static function samplingBersih($v, array $idOrder = null)
    {
        $v = is_array($v) ? $v : array();
        $out = array('mode' => (isset($v['mode']) && $v['mode'] === 'tabel') ? 'tabel' : 'off', 'hari' => array());
        $nama = function ($x) {
            $r = array();
            foreach (is_array($x) ? $x : array() as $n) {
                $n = is_array($n) ? '' : trim((string) $n);
                if ($n !== '' && !in_array($n, $r, true) && count($r) < 20) { $r[] = function_exists('mb_substr') ? mb_substr($n, 0, 120) : substr($n, 0, 120); }
            }
            return $r;
        };
        $nHari = 0;
        foreach (self::larik(isset($v['hari']) ? $v['hari'] : null) as $h) {
            if (++$nHari > 120) { break; }
            $h = self::larik($h);
            $tgl = isset($h['tgl']) && !is_array($h['tgl']) ? self::tanggalValid($h['tgl']) : '';
            $regu = array();
            foreach (self::larik(isset($h['regu']) ? $h['regu'] : null) as $r) {
                if (count($regu) >= 20) { break; }
                $r = self::larik($r);
                $ids = self::idDariCsv(implode(',', array_map('intval', self::larik(isset($r['orders']) ? $r['orders'] : null))));
                if ($idOrder !== null) { $ids = array_values(array_intersect($ids, $idOrder)); }
                $regu[] = array(
                    'orders' => $ids,
                    'ppc'    => $nama(isset($r['ppc']) ? $r['ppc'] : null),
                    'supir'  => $nama(isset($r['supir']) ? $r['supir'] : null),
                );
            }
            $out['hari'][] = array('tgl' => $tgl, 'regu' => $regu);
        }
        return $out;
    }

    /** Id order yang tercantum pada data form (S['orders']); dipakai membatasi jadwal pengambilan contoh. */
    public static function idOrderDariForm(array $d)
    {
        $ids = array();
        foreach (self::larik(isset($d['orders']) ? $d['orders'] : null) as $o) {
            if (is_array($o) && !empty($o['id'])) { $ids[] = (int) $o['id']; }
        }
        return $ids;
    }

    /** Pilihan jumlah kolom daftar mitra: 'auto' atau '1'..'4' (string). Selain itu dianggap 'auto'. */
    public static function pilihanKolomDaftar($v)
    {
        $v = is_array($v) ? '' : trim((string) $v);
        return in_array($v, array('1', '2', '3', '4'), true) ? $v : 'auto';
    }

    /** Jumlah kolom sebenarnya. Otomatis: sampai 15 mitra 2 kolom, lebih dari itu 3 kolom. */
    public static function kolomDaftar($pilihan, $jumlah)
    {
        $p = self::pilihanKolomDaftar($pilihan);
        if ($p !== 'auto') { return (int) $p; }
        return ((int) $jumlah) <= 15 ? 2 : 3;
    }

    /** Daftar order (snapshot) + pengaturan tampil di dokumen, dari data form -> larik yang disimpan di po_kegiatan.daftar_order. */
    public static function daftarDariForm(array $d, array $items = null)
    {
        if ($items === null) {
            $items = array();
            foreach (self::larik(isset($d['orders']) ? $d['orders'] : null) as $o) {
                $o = self::larik($o);
                $items[] = array(
                    'id'            => (int) (isset($o['id']) ? $o['id'] : 0),
                    'mitra'         => trim((string) (isset($o['mitra']) && !is_array($o['mitra']) ? $o['mitra'] : '')),
                    'nomor_surat'   => trim((string) (isset($o['nomor_surat']) && !is_array($o['nomor_surat']) ? $o['nomor_surat'] : '')),
                    'tanggal_surat' => trim((string) (isset($o['tanggal_surat']) && !is_array($o['tanggal_surat']) ? $o['tanggal_surat'] : '')),
                    'alamat'        => trim((string) (isset($o['alamat']) && !is_array($o['alamat']) ? $o['alamat'] : '')),
                );
            }
        }
        return array(
            'tampil' => !isset($d['daftarTampil']) || !empty($d['daftarTampil']) ? 1 : 0,
            'kolom'  => self::pilihanKolomDaftar(isset($d['daftarKolom']) ? $d['daftarKolom'] : 'auto'),
            'urut'   => (isset($d['daftarUrut']) && $d['daftarUrut'] === 'abc') ? 'abc' : 'sel',
            'items'  => array_values($items),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Isi form  <->  kolom po_kegiatan                                   */
    /* ------------------------------------------------------------------ */

    /** Keterangan estimasi (template P) untuk PO lama: daftar bernomor dari nama personel + catatan 2 dan 3. */
    public static function ketEstimasiLama(array $v)
    {
        $h = function ($x) { return htmlspecialchars(trim((string) (is_array($x) ? '' : $x)), ENT_QUOTES, 'UTF-8'); };
        $li = 'Personel:';
        $i = 0;
        foreach (self::larik(isset($v['personel']) ? $v['personel'] : null) as $p) {
            $p = self::larik($p);
            $i++;
            $li .= '<br>- P' . $i . ': ' . $h(isset($p['nama']) ? $p['nama'] : '');
        }
        $html = '<ol><li>' . $li . '</li>';
        foreach (array('cat2', 'cat3') as $k) {
            $x = $h(isset($v[$k]) ? $v[$k] : '');
            if ($x !== '') { $html .= '<li>' . $x . '</li>'; }
        }
        return $html . '</ol>';
    }

    /**
     * Estimasi kegiatan pengambilan contoh dalam bentuk baku. Dua template:
     *  p = menit per personel (kolom P1, P2, ...) + Keterangan bebas (HTML dari editor, satu kotak)
     *  w = satu kolom Waktu Pengerjaan (+ teks tampil opsional) dan Keterangan per baris, total jam otomatis
     * Data lama (tanpa template / ketHtml) dibaca sebagai template p; nama personel + catatan 2-3 dipindah ke ketHtml.
     */
    public static function estimasiNormal($v, $versi = null)
    {
        $v = self::larik($v);
        $tpl = (isset($v['template']) && $v['template'] === 'w') ? 'w' : 'p';
        $pers = array();
        foreach (self::larik(isset($v['personel']) ? $v['personel'] : null) as $p) {
            $p = self::larik($p);
            $pers[] = array('nama' => trim((string) (isset($p['nama']) && !is_array($p['nama']) ? $p['nama'] : '')));
        }
        $rows = array();
        foreach (self::larik(isset($v['rows']) ? $v['rows'] : null) as $r) {
            $r = self::larik($r);
            $menit = array();
            foreach (self::larik(isset($r['menit']) ? $r['menit'] : null) as $m) {
                $menit[] = is_numeric($m) ? max(0, (int) $m) : 0;
            }
            $rows[] = array(
                'kegiatan' => trim((string) (isset($r['kegiatan']) && !is_array($r['kegiatan']) ? $r['kegiatan'] : '')),
                'menit'    => $menit,
            );
        }
        if (array_key_exists('ketHtml', $v) && !is_array($v['ketHtml'])) {
            $ket = self::bersihHtml((string) $v['ketHtml']);
        } else {
            $ket = self::ketEstimasiLama($v);
        }
        $w = self::larik(isset($v['w']) ? $v['w'] : null);
        $wRows = array();
        foreach (self::larik(isset($w['rows']) ? $w['rows'] : null) as $r) {
            $r = self::larik($r);
            $wRows[] = array(
                'kegiatan' => trim((string) (isset($r['kegiatan']) && !is_array($r['kegiatan']) ? $r['kegiatan'] : '')),
                'menit'    => (isset($r['menit']) && is_numeric($r['menit'])) ? max(0, (int) $r['menit']) : 0,
                'tampil'   => trim((string) (isset($r['tampil']) && !is_array($r['tampil']) ? $r['tampil'] : '')),
                'ket'      => trim((string) (isset($r['ket']) && !is_array($r['ket']) ? $r['ket'] : '')),
            );
        }
        if ($versi === null) { $versi = (isset($v['versi']) && (int) $v['versi'] >= 2) ? 2 : 0; }
        return array(
            'template' => $tpl,
            'personel' => $pers ? $pers : array(array('nama' => '')),
            'rows'     => $rows,
            'ketHtml'  => $ket,
            'w'        => array('rows' => $wRows),
            'versi'    => $versi,
        );
    }

    /**
     * Isi form (array hasil JSON dari form) -> nilai kolom po_kegiatan. Hanya kolom ISI FORM;
     * nomor_po, tanggal_po, judul_kegiatan, status, dst. diurus pemanggil.
     */
    public static function kolomDariForm(array $d)
    {
        $g = function ($k) use ($d) { return isset($d[$k]) ? $d[$k] : null; };

        $tim = array();
        foreach (self::larik($g('tim')) as $t) {
            $t = self::larik($t);
            $tim[] = array(
                'nama'   => trim((string) (isset($t['nama']) && !is_array($t['nama']) ? $t['nama'] : '')),
                'tugas'  => trim((string) (isset($t['tugas']) && !is_array($t['tugas']) ? $t['tugas'] : '')),
                'uraian' => self::bersihHtml(isset($t['uraian']) && !is_array($t['uraian']) ? $t['uraian'] : ''),
            );
        }

        $estimasi = self::estimasiNormal($g('estimasi'), (isset($d['versiEditor']) && (int) $d['versiEditor'] >= 2) ? 2 : 0);

        $jd = self::larik($g('jadwal'));
        $jadwal = array(
            'mulai'   => self::tanggalValid(isset($jd['mulai']) ? $jd['mulai'] : '') ?: '',
            'selesai' => self::tanggalValid(isset($jd['selesai']) ? $jd['selesai'] : '') ?: '',
            'kolom'   => array(), // kolom tanggal dibuat ulang dari mulai/selesai setiap kali dibutuhkan
            'rows'    => array(),
        );
        foreach (self::larik(isset($jd['rows']) ? $jd['rows'] : null) as $r) {
            $r = self::larik($r);
            $tanda = array();
            foreach (self::larik(isset($r['tanda']) ? $r['tanda'] : null) as $k) {
                if (is_string($k) && $k !== '') { $tanda[] = $k; }
            }
            $jadwal['rows'][] = array(
                'nama'  => trim((string) (isset($r['nama']) && !is_array($r['nama']) ? $r['nama'] : '')),
                'tanda' => $tanda,
            );
        }

        return array(
            'jenis'           => self::teks($g('jenis'), 255),
            'nama_perusahaan' => self::teks($g('perusahaan'), 255),
            'lokasi'          => self::teks($g('lokasi'), 255),
            'jenis_layanan'   => self::teks($g('jenisLayanan'), 150),
            'no_penawaran'    => self::teks($g('noPenawaran'), 150),
            'dasar'           => self::bersihHtml($g('dasar')),
            'tujuan'          => self::bersihHtml($g('tujuan')),
            'ruang_lingkup'   => self::bersihHtml($g('ruang')),
            'tim_pelaksana'   => self::json($tim),
            'rab'             => self::json((object) self::larik($g('rab'))),
            'estimasi_contoh' => self::json($estimasi),
            'jadwal'          => self::json($jadwal),
            'tanda_tangan'    => self::json((object) \PoTtd::bersihkan(is_array($g('ttd')) ? $g('ttd') : array(), 'Bandung')),
            'daftar_order'    => self::json(self::daftarDariForm($d)),
            'jadwal_sampling' => self::json(self::samplingBersih(isset($d['sampling']) ? $d['sampling'] : null, self::idOrderDariForm($d))),
        );
    }

    /** Baris po_kegiatan -> isi form (kunci yang datanya tidak ada dihilangkan: form mengisi nilai awalnya). */
    public static function formDariBaris(array $r)
    {
        $S = array();
        $tgl = isset($r['tanggal_po']) ? (string) $r['tanggal_po'] : '';
        if ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) {
            $S['tanggal'] = date('Y-m-d', strtotime($tgl));
        }
        $teks = array('jenis' => 'jenis', 'perusahaan' => 'nama_perusahaan', 'lokasi' => 'lokasi', 'jenisLayanan' => 'jenis_layanan', 'noPenawaran' => 'no_penawaran');
        foreach ($teks as $kForm => $kol) {
            if (isset($r[$kol]) && $r[$kol] !== null) { $S[$kForm] = (string) $r[$kol]; }
        }
        if (isset($r['judul_kegiatan']) && $r['judul_kegiatan'] !== null) { $S['judul'] = (string) $r['judul_kegiatan']; }

        foreach (array('dasar' => 'dasar', 'tujuan' => 'tujuan', 'ruang' => 'ruang_lingkup') as $kForm => $kol) {
            if (isset($r[$kol]) && $r[$kol] !== null) { $S[$kForm] = (string) $r[$kol]; }
        }

        $json = function ($kol) use ($r) {
            if (!isset($r[$kol]) || $r[$kol] === null || $r[$kol] === '') { return null; }
            $v = json_decode((string) $r[$kol], true);
            return is_array($v) ? $v : null;
        };
        $v = $json('tim_pelaksana');
        if ($v !== null) { $S['tim'] = array_values($v); }
        $v = $json('rab');
        if ($v !== null) { $S['rab'] = $v; }
        $v = $json('estimasi_contoh');
        if ($v !== null) {
            $S['estimasi'] = self::estimasiNormal($v);
            $S['versiEditor'] = (isset($v['versi']) && (int) $v['versi'] >= 2) ? 2 : 0;
        }
        $v = $json('jadwal');
        if ($v !== null) { $S['jadwal'] = $v; }
        $v = $json('jadwal_sampling');
        if ($v !== null) { $S['sampling'] = self::samplingBersih($v); }
        $v = $json('daftar_order');
        if ($v !== null) {
            $S['orders'] = isset($v['items']) && is_array($v['items']) ? array_values($v['items']) : array();
            $S['daftarTampil'] = !isset($v['tampil']) || !empty($v['tampil']);
            $S['daftarKolom'] = self::pilihanKolomDaftar(isset($v['kolom']) ? $v['kolom'] : 'auto');
            $S['daftarUrut'] = (isset($v['urut']) && $v['urut'] === 'abc') ? 'abc' : 'sel';
        }
        $v = $json('tanda_tangan');
        if ($v !== null) { $S['ttd'] = \PoTtd::dariLama($v, array('ketuaJabatan' => 'Ketua Tim Pengujian dan Uji Profisiensi')); }
        return $S;
    }

    /* ------------------------------------------------------------------ */
    /* Baca                                                               */
    /* ------------------------------------------------------------------ */

    public static function ambil($db, $id)
    {
        $r = $db->exec(
            "SELECT * FROM po_kegiatan WHERE id = ? AND layanan = ? LIMIT 1",
            array(1 => (int) $id, 2 => self::LAYANAN)
        );
        return empty($r) ? null : $r[0];
    }
}
