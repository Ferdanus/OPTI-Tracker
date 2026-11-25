<?php

/**
 * RabExcel -- baca file Excel (.xlsx) RAB milik mitra/staff lalu petakan ke struktur RAB form PO.
 *
 * Tanpa library tambahan: .xlsx = ZIP berisi XML, dibaca pakai ZipArchive + SimpleXML.
 * Format .xls lama tidak didukung (minta simpan ulang sebagai .xlsx).
 *
 * Alur:
 *   RabExcel::bacaBuku($path)        -> ['sheets' => [['nama'=>, 'baris'=>[no_baris => [indeks_kolom => nilai]]]]]
 *   RabExcel::analisis($buku)        -> hasil bacaan per sheet + kategori RAB siap dimasukkan ke form
 *   RabExcel::htmlPratinjau($buku)   -> halaman HTML pratinjau semua sheet (hanya-baca)
 */
class RabExcel {

    const MAKS_UKURAN = 5242880;      // 5 MB
    const MAKS_BARIS  = 600;          // baris per sheet yang dibaca
    const MAKS_KOLOM  = 30;

    /* ============================================================ */
    /*  1. PEMBACA XLSX                                              */
    /* ============================================================ */

    /** @throws \RuntimeException pesan siap tampil ke pengguna */
    public static function bacaBuku($path) {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Server belum mengaktifkan ekstensi zip PHP, jadi file Excel belum bisa dibaca.');
        }
        if (!is_file($path)) { throw new \RuntimeException('File tidak ditemukan.'); }
        if (filesize($path) > self::MAKS_UKURAN) { throw new \RuntimeException('Ukuran file terlalu besar (maksimal 5 MB).'); }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('File tidak bisa dibuka. Pastikan formatnya .xlsx (bukan .xls lama atau file rusak).');
        }
        try {
            $wb = self::ambilXml($zip, 'xl/workbook.xml');
            if (!$wb) { throw new \RuntimeException('Isi file tidak dikenali sebagai Excel .xlsx.'); }

            $rels = [];
            $relXml = self::ambilXml($zip, 'xl/_rels/workbook.xml.rels');
            if ($relXml) {
                foreach ($relXml->Relationship as $r) {
                    $rels[(string) $r['Id']] = (string) $r['Target'];
                }
            }

            $shared = self::bacaSharedStrings($zip);
            $stil = self::bacaStil($zip);

            $sheets = [];
            $nsMain = $wb->getNamespaces(true);
            $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
            $daftar = isset($wb->sheets) ? $wb->sheets->sheet : [];
            $urut = 0;
            foreach ($daftar as $sh) {
                $urut++;
                $nama = (string) $sh['name'];
                $state = (string) $sh['state'];
                if ($state === 'hidden' || $state === 'veryHidden') { continue; }
                $rid = (string) $sh->attributes($nsR)['id'];
                $target = isset($rels[$rid]) ? $rels[$rid] : ('worksheets/sheet' . $urut . '.xml');
                $target = ltrim($target, '/');
                $file = (strpos($target, 'xl/') === 0) ? $target : 'xl/' . $target;
                $xml = self::ambilXml($zip, $file);
                if (!$xml) { continue; }
                $rumus = []; $galat = []; $fmt = []; $gaya = []; $lebar = []; $merges = [];
                $baris = self::bacaSheet($xml, $shared, $rumus, $galat, $fmt, $stil, $gaya, $lebar, $merges);
                $sheets[] = ['nama' => $nama, 'baris' => $baris, 'rumus' => $rumus, 'galat' => $galat, 'fmt' => $fmt, 'gaya' => $gaya, 'lebar' => $lebar, 'merges' => $merges];
            }
            if (!$sheets) { throw new \RuntimeException('Tidak ada sheet yang bisa dibaca di file ini.'); }
            return ['sheets' => $sheets];
        } finally {
            $zip->close();
        }
    }

    protected static function ambilXml(\ZipArchive $zip, $nama) {
        $idx = $zip->locateName($nama, \ZipArchive::FL_NOCASE);
        if ($idx === false) { return null; }
        $st = $zip->statIndex($idx);
        if ($st && $st['size'] > 30 * 1024 * 1024) { throw new \RuntimeException('Isi file terlalu besar untuk dibaca.'); }
        $isi = $zip->getFromIndex($idx);
        if ($isi === false || $isi === '') { return null; }
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($isi, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $xml ?: null;
    }

    protected static function bacaSharedStrings(\ZipArchive $zip) {
        $hasil = [];
        $xml = self::ambilXml($zip, 'xl/sharedStrings.xml');
        if (!$xml) { return $hasil; }
        foreach ($xml->si as $si) {
            $t = '';
            if (isset($si->t)) { $t .= (string) $si->t; }
            if (isset($si->r)) { foreach ($si->r as $r) { $t .= (string) $r->t; } }
            $hasil[] = $t;
        }
        return $hasil;
    }

    protected static function indeksKolom($ref) {
        if (!preg_match('/^([A-Z]+)(\d+)$/i', (string) $ref, $m)) { return [null, null]; }
        $huruf = strtoupper($m[1]); $n = 0;
        for ($i = 0; $i < strlen($huruf); $i++) { $n = $n * 26 + (ord($huruf[$i]) - 64); }
        return [(int) $m[2], $n - 1];
    }

    /**
     * Baca satu sheet. $baris = nilai (hasil hitung terakhir yang tersimpan di file); $rumus[no][kolom] = "=RUMUS"; $galat[no][kolom] = "#DIV/0!".
     * Rumus bersama (shared formula) digeser ke sel turunannya.
     */
    protected static function bacaSheet($xml, array $shared, &$rumus = null, &$galat = null, &$fmt = null, array $stil = [], &$gaya = null, &$lebar = null, &$merges = null) {
        $baris = []; $rumus = []; $galat = []; $fmt = []; $gaya = []; $lebar = []; $merges = []; $sharedF = [];
        // sel yang digabung (merge), mis. "B1:D1"
        if (isset($xml->mergeCells)) {
            foreach ($xml->mergeCells->mergeCell as $mc) {
                $ref = strtoupper((string) $mc['ref']);
                if (count($merges) >= self::MAKS_MERGE || !preg_match('/^[A-Z]{1,3}\d{1,5}:[A-Z]{1,3}\d{1,5}$/', $ref)) { continue; }
                $merges[] = $ref;
            }
        }
        // lebar kolom (piksel perkiraan: 1 karakter = 7 px + 5 px ruang)
        if (isset($xml->cols)) {
            foreach ($xml->cols->col as $cl) {
                $mn = (int) $cl['min']; $mx = (int) $cl['max']; $w = (float) $cl['width'];
                if ($mn < 1 || $w <= 0 || (string) $cl['hidden'] === '1' || (string) $cl['hidden'] === 'true') { continue; }
                $px = (int) round($w * 7 + 5);
                for ($kc = $mn; $kc <= min($mx, self::MAKS_KOLOM); $kc++) { $lebar[$kc - 1] = $px; }
            }
        }
        if (!isset($xml->sheetData)) { return $baris; }
        foreach ($xml->sheetData->row as $row) {
            $noBaris = (int) $row['r'];
            if ($noBaris <= 0 || $noBaris > self::MAKS_BARIS) { if ($noBaris > self::MAKS_BARIS) { break; } continue; }
            $isi = [];
            foreach ($row->c as $c) {
                list($r2, $kol) = self::indeksKolom($c['r']);
                if ($kol === null || $kol >= self::MAKS_KOLOM) { continue; }
                $t = (string) $c['t'];
                $si = (string) $c['s'];
                if ($si !== '') {
                    if (isset($stil['fmt'][(int) $si])) { $fmt[$noBaris][$kol] = $stil['fmt'][(int) $si]; }
                    if (isset($stil['gaya'][(int) $si])) { $gaya[$noBaris][$kol] = $stil['gaya'][(int) $si]; }
                }
                // ---- rumus
                $f = null;
                if (isset($c->f)) {
                    $ft = (string) $c->f; $ftipe = (string) $c->f['t'];
                    if ($ftipe === 'shared') {
                        $si = (string) $c->f['si'];
                        if ($ft !== '') { $sharedF[$si] = ['f' => $ft, 'r' => $noBaris, 'c' => $kol]; $f = '=' . $ft; }
                        elseif (isset($sharedF[$si])) { $f = '=' . self::geserRumus($sharedF[$si]['f'], $noBaris - $sharedF[$si]['r'], $kol - $sharedF[$si]['c']); }
                    } elseif ($ftipe !== 'array' && $ftipe !== 'dataTable' && $ft !== '') { $f = '=' . $ft; }
                }
                if ($f !== null && strlen($f) <= 1000 && !preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $f)) { $rumus[$noBaris][$kol] = $f; }
                // ---- nilai
                $v = null;
                if ($t === 's') {
                    $i = (int) $c->v; $v = isset($shared[$i]) ? $shared[$i] : '';
                } elseif ($t === 'inlineStr') {
                    $v = '';
                    if (isset($c->is->t)) { $v .= (string) $c->is->t; }
                    if (isset($c->is->r)) { foreach ($c->is->r as $r) { $v .= (string) $r->t; } }
                } elseif ($t === 'str') {
                    $v = (string) $c->v;
                } elseif ($t === 'b') {
                    $v = ((string) $c->v === '1') ? 'TRUE' : 'FALSE';
                } elseif ($t === 'e') {
                    $e = trim((string) $c->v); if (preg_match('/^#[A-Z0\/!?]{2,8}$/', $e)) { $galat[$noBaris][$kol] = $e; }
                    $v = null;
                } else {
                    $mentah = (string) $c->v;
                    $v = ($mentah === '') ? null : (is_numeric($mentah) ? (float) $mentah : $mentah);
                }
                if ($v === null || $v === '') { continue; }
                if (is_string($v)) {
                    // spasi di depan teks (cara umum membuat "menjorok" di Excel) dicatat sebagai level indent, lalu teksnya dirapikan seperti biasa
                    $spasiAwal = preg_match('/^[ \t\x{00A0}]+/u', $v, $mj) ? mb_strlen($mj[0], 'UTF-8') : 0;
                    $v = trim(preg_replace('/ ?\n ?/', "\n", preg_replace('/[^\S\n]+/u', ' ', str_replace(["\r\n", "\r"], "\n", $v)))); if ($v === '') { continue; }
                    if ($spasiAwal > 0) {
                        $lv = min(15, max(1, (int) ceil($spasiAwal / 3)));
                        $g0 = isset($gaya[$noBaris][$kol]) ? $gaya[$noBaris][$kol] : [];
                        if (empty($g0['i']) || $g0['i'] < $lv) { $g0['i'] = $lv; }
                        $gaya[$noBaris][$kol] = $g0;
                    }
                }
                $isi[$kol] = $v;
            }
            if ($isi) { ksort($isi); $baris[$noBaris] = $isi; }
        }
        return $baris;
    }

    /**
     * Gaya tiap xf: ['fmt' => [xf => kode], 'gaya' => [xf => ['b'=>1,'a'=>'l|c|r','w'=>1,'i'=>jorok]]].
     * Kode format: gen[:n] sep[:n] rp[:n] pct[:n] txt (n = jumlah desimal); format lain (tanggal, dsb.) tidak dicatat.
     */
    protected static function bacaStil(\ZipArchive $zip) {
        $out = ['fmt' => [], 'gaya' => []];
        $xml = self::ambilXml($zip, 'xl/styles.xml');
        if (!$xml) { return $out; }
        $kode = [];
        if (isset($xml->numFmts)) { foreach ($xml->numFmts->numFmt as $nf) { $kode[(int) $nf['numFmtId']] = (string) $nf['formatCode']; } }
        $tebal = [];
        if (isset($xml->fonts)) {
            $fi = 0;
            foreach ($xml->fonts->font as $ft) {
                $b = false;
                if (isset($ft->b)) { $v = strtolower((string) $ft->b['val']); $b = !($v === '0' || $v === 'false'); }
                $tebal[$fi++] = $b;
            }
        }
        if (!isset($xml->cellXfs)) { return $out; }
        $i = 0;
        foreach ($xml->cellXfs->xf as $xf) {
            $id = (int) $xf['numFmtId'];
            $f = self::kodeDariFormat($id, isset($kode[$id]) ? $kode[$id] : '');
            if ($f !== '') { $out['fmt'][$i] = $f; }
            $g = [];
            if (!empty($tebal[(int) $xf['fontId']])) { $g['b'] = 1; }
            if (isset($xf->alignment)) {
                $h = strtolower((string) $xf->alignment['horizontal']);
                if ($h === 'left') { $g['a'] = 'l'; } elseif ($h === 'center' || $h === 'centercontinuous') { $g['a'] = 'c'; } elseif ($h === 'right') { $g['a'] = 'r'; }
                $w = strtolower((string) $xf->alignment['wrapText']);
                if ($w === '1' || $w === 'true') { $g['w'] = 1; }
                $ind = (int) $xf->alignment['indent'];   // menjorok (Increase Indent) di Excel
                if ($ind > 0) { $g['i'] = min(15, $ind); }
            }
            if ($g) { $out['gaya'][$i] = $g; }
            $i++;
        }
        return $out;
    }

    /** Terjemahkan numFmt Excel ke kode format sederhana ('' bila tidak dikenali / Umum). */
    protected static function kodeDariFormat($id, $k) {
        if ($id === 0) { return ''; }
        if ($id === 9) { return 'pct:0'; }
        if ($id === 10) { return 'pct:2'; }
        if ($id === 1) { return 'gen:0'; }
        if ($id === 2) { return 'gen:2'; }
        if ($id === 3 || $id === 37 || $id === 38 || $id === 41) { return 'sep:0'; }
        if ($id === 4 || $id === 39 || $id === 40 || $id === 43) { return 'sep:2'; }
        if ($id === 49) { return 'txt'; }
        if ($k === '') { return ''; }
        $bagian = preg_split('/(?<!\\\\);/', $k)[0];
        $desimal = function ($t) { return preg_match('/\.([0#?]+)/', $t, $m) ? min(6, strlen($m[1])) : 0; };
        if (strpos($bagian, '%') !== false) { return 'pct:' . $desimal(substr($bagian, 0, strpos($bagian, '%'))); }
        if (stripos($bagian, 'Rp') !== false) { $d = $desimal($bagian); return $d > 0 ? 'rp:' . $d : 'rp'; }
        $bersih = preg_replace('/\[[^\]]*\]|"[^"]*"|\\\\.|_.|\*./', '', $bagian);
        $bersih = trim($bersih);
        if ($bersih !== '' && preg_match('/^[#0?,.\s]+$/', $bersih) && preg_match('/[0#]/', $bersih)) {
            $d = $desimal($bersih);
            return (strpos($bersih, ',') !== false ? 'sep:' : 'gen:') . $d;
        }
        return '';
    }

    /** Kode format sederhana -> id numFmt (kode khusus dikumpulkan di $kustom: formatCode => id). */
    protected static function idNumFmt($fm, array &$kustom) {
        if ($fm === '' || $fm === 'gen') { return 0; }
        if ($fm === 'txt') { return 49; }
        if (!preg_match('/^(gen|sep|rp|pct)(?::(\d))?$/', $fm, $m)) { return 0; }
        $k = $m[1]; $d = isset($m[2]) ? (int) $m[2] : null;
        if ($k === 'gen') { if ($d === null) { return 0; } if ($d === 0) { return 1; } if ($d === 2) { return 2; } $kode = '0.' . str_repeat('0', $d); }
        elseif ($k === 'sep') { $d = $d === null ? 0 : $d; if ($d === 0) { return 3; } if ($d === 2) { return 4; } $kode = '#,##0.' . str_repeat('0', $d); }
        elseif ($k === 'pct') { if ($d === null || $d === 2) { return 10; } if ($d === 0) { return 9; } $kode = '0.' . str_repeat('0', $d) . '%'; }
        else { $d = $d === null ? 0 : $d; $z = $d > 0 ? '.' . str_repeat('0', $d) : ''; $kode = '"Rp"\ #,##0' . $z . ';\-"Rp"\ #,##0' . $z . ';"Rp"\ \-'; }
        if (!isset($kustom[$kode])) { $kustom[$kode] = 164 + count($kustom); }
        return $kustom[$kode];
    }

    /** Geser alamat sel relatif di rumus bersama ($dr baris, $dc kolom). Teks dalam tanda kutip ganda tidak diubah. */
    protected static function geserRumus($f, $dr, $dc) {
        $bagian = preg_split('/("(?:[^"]|"")*")/', $f, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($bagian as $i => $p) {
            if ($i % 2 === 1) { continue; }
            $bagian[$i] = preg_replace_callback('/(?<![A-Za-z0-9_.])(\$?)([A-Z]{1,3})(\$?)(\d+)(?![A-Za-z0-9_(])/', function ($m) use ($dr, $dc) {
                $kol = 0; for ($i = 0; $i < strlen($m[2]); $i++) { $kol = $kol * 26 + (ord($m[2][$i]) - 64); }
                $kolBaru = ($m[1] === '$') ? $kol : $kol + $dc;
                $barisBaru = ($m[3] === '$') ? (int) $m[4] : (int) $m[4] + $dr;
                if ($kolBaru < 1 || $barisBaru < 1) { return '#REF!'; }
                return $m[1] . self::hurufKolom($kolBaru - 1) . $m[3] . $barisBaru;
            }, $p);
        }
        return implode('', $bagian);
    }

    /* ============================================================ */
    /*  2. ANALISIS: kenali kolom, kategori, dan baris RAB           */
    /* ============================================================ */

    protected static function norm($s) {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    protected static function peranKolom($teks) {
        $n = self::norm($teks);
        if ($n === '') { return null; }
        if (preg_match('/^(uraian|kategori|kategori biaya|uraian (kegiatan|biaya|pekerjaan|belanja)|kegiatan|item|nama (item|barang|kegiatan)|keterangan|deskripsi|rincian|jenis (biaya|pengeluaran)|komponen( biaya)?|pos (biaya|pengeluaran)|nama kegiatan)$/', $n)) { return 'nama'; }
        if (preg_match('/^(no|nomor|no urut)$/', $n)) { return 'no'; }
        if (preg_match('/^(harga( satuan| per satuan)?|tarif( satuan)?|biaya satuan|satuan biaya|harga satuan rp|satuan harga|nilai satuan)$/', $n) || preg_match('/harga satuan|tarif/', $n)) { return 'harga'; }
        if (preg_match('/^(total|sub ?total|jumlah (harga|biaya|total|rp)|total (harga|biaya)|nilai|anggaran|biaya)$/', $n)) { return 'total'; }
        if (preg_match('/^(volume|vol|qty|kuantitas|banyaknya|jumlah|jml)$/', $n)) { return 'volume'; }
        if (preg_match('/^(satuan|sat|unit|uom)$/', $n)) { return 'satuan'; }
        if (preg_match('/^(kali|frekuensi|freq)$/', $n)) { return 'kali'; }
        if (preg_match('/^(orang|org|jumlah orang|kamar|peserta|pax|sampel|jumlah sampel)$/', $n)) { return 'dim2'; }
        if (preg_match('/^(hari|durasi|lama|hr|jumlah hari)$/', $n)) { return 'hari'; }
        return null;
    }

    /** Cari baris judul kolom. Mengembalikan [noBaris, [peran => indeksKolom]] atau null. */
    protected static function cariHeader(array $baris) {
        $kandidat = null;
        $dicek = 0;
        foreach ($baris as $no => $cells) {
            if (++$dicek > 30) { break; }
            $peran = [];
            foreach ($cells as $kol => $v) {
                if (!is_string($v)) { continue; }
                $p = self::peranKolom($v);
                if ($p && !isset($peran[$p])) { $peran[$p] = $kol; }
            }
            // "Jumlah" muncul dua kali (volume dan total)? yang pertama volume, yang kedua total
            $jumlahKol = [];
            foreach ($cells as $kol => $v) { if (is_string($v) && self::norm($v) === 'jumlah') { $jumlahKol[] = $kol; } }
            if (count($jumlahKol) >= 2) { $peran['volume'] = $jumlahKol[0]; $peran['total'] = $jumlahKol[count($jumlahKol) - 1]; }
            elseif (count($jumlahKol) === 1 && isset($peran['harga']) && !isset($peran['total'])) {
                // satu "Jumlah" setelah kolom harga = total; sebelumnya = volume
                if ($jumlahKol[0] > $peran['harga']) { $peran['total'] = $jumlahKol[0]; if (isset($peran['volume']) && $peran['volume'] === $jumlahKol[0]) { unset($peran['volume']); } }
            }
            // "Biaya" dan "Total Biaya" berdampingan: Biaya = per item, Total Biaya = subtotal kegiatan
            $kolBiaya = null; $kolTotalBiaya = null;
            foreach ($cells as $kol => $v) {
                if (!is_string($v)) { continue; }
                $nv = self::norm($v);
                if ($nv === 'biaya' && $kolBiaya === null) { $kolBiaya = $kol; }
                if ($nv === 'total biaya' && $kolTotalBiaya === null) { $kolTotalBiaya = $kol; }
            }
            if ($kolBiaya !== null && $kolTotalBiaya !== null) { $peran['total'] = $kolBiaya; $peran['subtotal'] = $kolTotalBiaya; }
            if (isset($peran['nama']) && (isset($peran['harga']) || isset($peran['total'])) && count($peran) >= 2) {
                if (!$kandidat || count($peran) > count($kandidat[1])) { $kandidat = [$no, $peran]; }
                if (count($peran) >= 4) { return [$no, $peran]; }
            }
        }
        return $kandidat;
    }

    protected static function angka($v) {
        if (is_float($v) || is_int($v)) { return (float) $v; }
        if (!is_string($v)) { return null; }
        $s = trim(preg_replace('/^(rp\.?|idr)\s*/i', '', trim($v)));
        if ($s === '' || !preg_match('/^-?[\d.,\s]+$/', $s)) { return null; }
        $s = str_replace(' ', '', $s);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
        elseif (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) { $s = str_replace(',', '', $s); }
        else { $s = str_replace(',', '.', $s); }
        return is_numeric($s) ? (float) $s : null;
    }

    protected static function eHari($label) { return (bool) preg_match('/^(hari|hr|day|d)$/i', trim((string) $label)); }

    /**
     * Pecah teks volume seperti "4 org x 3 hr" / "2 x 5 hari" / "12 sampel" menjadi bagian-bagian.
     * @return array[] daftar ['n' => angka, 'label' => teks] atau null kalau tidak terbaca
     */
    protected static function pecahVolume($teks) {
        $teks = trim((string) $teks);
        if ($teks === '') { return null; }
        $bagian = preg_split('/\s*[x×*]\s*/iu', $teks);
        $hasil = [];
        foreach ($bagian as $b) {
            if (!preg_match('/^(\d+(?:[.,]\d+)?)\s*([\p{L}\/.]*)$/u', trim($b), $m)) { return null; }
            $hasil[] = ['n' => (float) str_replace(',', '.', $m[1]), 'label' => $m[2]];
        }
        return $hasil ?: null;
    }

    /** Terjemahkan volume + satuan ke kolom item RAB form: kali, dim2 (+label), hari. */
    protected static function petakanVolume($vol, $satuan) {
        $o = ['kali' => 1.0, 'dim2' => null, 'dim2_label' => '', 'hari' => null];
        $satuan = trim((string) $satuan);
        if (is_float($vol) || is_int($vol)) {
            $v = (float) $vol;
            if ($satuan === '' || preg_match('/^(kali|x|paket|ls|lumpsum)$/i', $satuan)) { $o['kali'] = $v; if ($satuan !== '' && strcasecmp($satuan, 'kali') !== 0 && strcasecmp($satuan, 'x') !== 0) { $o['dim2_label'] = ''; } }
            elseif (self::eHari($satuan)) { $o['kali'] = 1.0; $o['hari'] = $v; }
            else { $o['kali'] = 1.0; $o['dim2'] = $v; $o['dim2_label'] = $satuan; }
            return $o;
        }
        $p = self::pecahVolume($vol);
        if (!$p) { return null; }
        $n = count($p);
        if ($n === 1) { return self::petakanVolume($p[0]['n'], $p[0]['label'] !== '' ? $p[0]['label'] : $satuan); }
        if ($n === 2) {
            $a = $p[0]; $b = $p[1];
            if (self::eHari($b['label']) || $b['label'] === '') { $o['dim2'] = $a['n']; $o['dim2_label'] = $a['label'] !== '' ? $a['label'] : $satuan; $o['hari'] = $b['n']; }
            else { $o['kali'] = $a['n']; $o['dim2'] = $b['n']; $o['dim2_label'] = $b['label']; }
            return $o;
        }
        if ($n === 3) {
            $o['kali'] = $p[0]['n']; $o['dim2'] = $p[1]['n']; $o['dim2_label'] = $p[1]['label']; $o['hari'] = $p[2]['n'];
            return $o;
        }
        return null;
    }

    protected static function hitungItem(array $it) {
        return (float) $it['kali'] * ($it['dim2'] ?: 1) * ($it['hari'] ?: 1) * (float) $it['satuan'];
    }

    /**
     * Baca satu sheet RAB. Mendukung dua bentuk:
     *  - tabel datar: Uraian | Volume | Satuan | Harga | Jumlah (judul bagian = Kegiatan baru)
     *  - format cetakan PO: No | Kegiatan | Rincian (angka+satuan berpasangan: 1 kali 3 orang 1 hari) | Satuan Biaya | Biaya | Total Biaya,
     *    dengan tingkat Kegiatan > Lokasi/Kelompok > Item, blok "Nilai Kontrak" + persen kategori di atas tabel.
     */
    protected static function bacaSheetRab($namaSheet, array $baris, $noHeader, array $peran, array &$masalah, array &$koreksi, array &$peranBaris = []) {
        $kolNama = $peran['nama'];
        $kolNo   = isset($peran['no']) ? $peran['no'] : null;
        $kolSub  = isset($peran['subtotal']) ? $peran['subtotal'] : null;
        $adaHarga = isset($peran['harga']);
        $adaKolomQty = isset($peran['volume']) || isset($peran['kali']) || isset($peran['dim2']) || isset($peran['hari']);
        $pairDari = null; $pairSampai = null;
        if (!$adaKolomQty && $adaHarga && $peran['harga'] > $kolNama + 1) { $pairDari = $kolNama + 1; $pairSampai = $peran['harga'] - 1; }

        $bersih = function ($t) { $t = preg_replace('/^\d+[.)]\s*/u', '', trim((string) $t)); return trim(rtrim(trim($t), ':')); };
        $adaNomor = function ($v) { return $v !== null && trim((string) $v) !== ''; };

        // ---- blok ringkasan di atas judul tabel: Nilai Kontrak + kategori dengan persen
        $nilaiKontrak = null; $ringkas = [];
        foreach ($baris as $no => $cells) {
            if ($no >= $noHeader) { break; }
            $teks = null; $angka = []; $persen = null;
            foreach ($cells as $v) {
                if (is_string($v)) {
                    if (preg_match('/^(-?[\d.,]+)\s*%$/', $v, $pm)) { $persen = self::angka($pm[1]); continue; }
                    if ($teks === null && self::angka($v) === null) { $teks = $v; }
                } elseif (is_float($v) || is_int($v)) {
                    if ($v > 0 && $v < 1 && $persen === null) { $persen = round($v * 100, 2); } else { $angka[] = (float) $v; }
                }
            }
            if ($teks === null) { continue; }
            if (preg_match('/nilai\s*kontrak/i', $teks)) { if ($angka) { $nilaiKontrak = max($angka); } $peranBaris[$no] = 'ringkas'; continue; }
            if (preg_match('/^\d+[.)]\s*(.+)$/u', $teks, $m) && ($persen !== null || $angka)) {
                $ringkas[] = ['nama' => $bersih($teks), 'persen' => $persen, 'nilai' => $angka ? max($angka) : null, 'dipakai' => false]; $peranBaris[$no] = 'ringkas';
            }
        }
        $cocokRingkas = function ($teks) use (&$ringkas, $bersih) {
            $n = self::norm($bersih($teks)); if ($n === '') { return null; }
            foreach ($ringkas as $i => $r) {
                $rn = self::norm($r['nama']);
                if ($rn !== '' && ($rn === $n || strpos($rn, $n) !== false || strpos($n, $rn) !== false)) { return $i; }
            }
            return null;
        };

        // ---- baca nilai satu baris
        $bacaBaris = function ($cells) use ($peran, $kolNama, $kolNo, $kolSub, $adaHarga, $pairDari, $pairSampai) {
            $r = ['nm' => isset($cells[$kolNama]) ? (is_string($cells[$kolNama]) ? $cells[$kolNama] : (string) $cells[$kolNama]) : null];
            $r['no'] = ($kolNo !== null && isset($cells[$kolNo])) ? $cells[$kolNo] : null;
            $r['tot'] = (isset($peran['total']) && isset($cells[$peran['total']])) ? self::angka($cells[$peran['total']]) : null;
            $r['sub'] = ($kolSub !== null && isset($cells[$kolSub])) ? self::angka($cells[$kolSub]) : null;
            $r['harga'] = ($adaHarga && isset($cells[$peran['harga']])) ? self::angka($cells[$peran['harga']]) : null;
            $r['vol'] = isset($peran['volume']) && isset($cells[$peran['volume']]) ? $cells[$peran['volume']] : null;
            $r['sat'] = isset($peran['satuan']) && isset($cells[$peran['satuan']]) ? $cells[$peran['satuan']] : '';
            $r['kali'] = isset($peran['kali']) && isset($cells[$peran['kali']]) ? self::angka($cells[$peran['kali']]) : null;
            $r['dim2'] = isset($peran['dim2']) && isset($cells[$peran['dim2']]) ? self::angka($cells[$peran['dim2']]) : null;
            $r['hari'] = isset($peran['hari']) && isset($cells[$peran['hari']]) ? self::angka($cells[$peran['hari']]) : null;
            // baris judul yang teksnya ada di kolom lain (mis. sel digabung A:K "Biaya Operasional"): satu-satunya teks di baris tanpa angka
            if ($r['nm'] === null || trim((string) $r['nm']) === '') {
                $teks = []; $adaAngka = false;
                foreach ($cells as $v) { if (is_string($v) && self::angka($v) === null) { $teks[] = $v; } else { $adaAngka = true; } }
                if (count($teks) === 1 && !$adaAngka) { $r['nm'] = $teks[0]; $r['no'] = null; }
            }
            $r['pairs'] = [];
            if ($pairDari !== null) {
                $num = null;
                for ($c = $pairDari; $c <= $pairSampai; $c++) {
                    if (!isset($cells[$c])) { continue; }
                    $v = $cells[$c]; $a = self::angka($v);
                    if ($a !== null) { if ($num !== null) { $r['pairs'][] = [$num, '']; } $num = $a; }
                    elseif (is_string($v) && $num !== null) { $r['pairs'][] = [$num, trim($v)]; $num = null; }
                }
                if ($num !== null) { $r['pairs'][] = [$num, '']; }
            }
            return $r;
        };
        $eItem = function ($r) use ($kolSub) {
            if ($r['harga'] !== null || $r['pairs'] || $r['vol'] !== null || $r['kali'] !== null || $r['dim2'] !== null || $r['hari'] !== null) { return true; }
            return $r['tot'] !== null;   // baris judul hanya membawa Total Biaya (kolom subtotal), bukan Biaya
        };

        // ---- prapindai: apakah ada tingkat bertingkat (item tanpa nomor + kegiatan bernomor)?
        $itemTanpaNo = 0; $bernomor = 0;
        foreach ($baris as $no => $cells) {
            if ($no <= $noHeader) { continue; }
            $r = $bacaBaris($cells);
            if ($r['nm'] === null || preg_match('/^(grand\s*total|t\s*o\s*t\s*a\s*l|jumlah|sub\s*total)\b/iu', $r['nm'])) { continue; }
            if ($kolNo !== null && $adaNomor($r['no'])) { $bernomor++; }
            elseif ($eItem($r)) { $itemTanpaNo++; }
        }
        $hier = ($kolNo !== null && $itemTanpaNo > 0 && $bernomor > 0);

        // ---- baca baris demi baris
        $kats = []; $ki = -1; $gi = -1; $ji = -1;      // kategori, kegiatan, grup (indeks aktif)
        $akhir = null;                                  // [ki, gi, ji|-1, ii] item terakhir, untuk lanjutan nama
        $totalExcelSheet = null; $jumlahBaris = 0; $masalahSheet = 0;
        $kategoriBaru = function ($nama, $rIdx = null) use (&$kats, &$ki, &$gi, &$ji, &$ringkas) {
            $k = ['sheet' => null, 'nama' => $nama, 'kegiatan' => [], 'total' => 0.0, 'total_excel' => null, 'rekap' => null, 'cocok' => null, 'persen' => '', 'tanpa_rincian' => false];
            if ($rIdx !== null) {
                $k['nama'] = $ringkas[$rIdx]['nama']; $k['rekap'] = $ringkas[$rIdx]['nilai'];
                $k['persen'] = $ringkas[$rIdx]['persen'] !== null ? rtrim(rtrim(number_format($ringkas[$rIdx]['persen'], 2, '.', ''), '0'), '.') : '';
                $ringkas[$rIdx]['dipakai'] = true;
            }
            $kats[] = $k; $ki = count($kats) - 1; $gi = -1; $ji = -1;
        };
        $kegiatanBaru = function ($nama, $sub = null) use (&$kats, &$ki, &$gi, &$ji, &$kategoriBaru, $namaSheet) {
            if ($ki < 0) { $kategoriBaru($namaSheet); }
            $kats[$ki]['kegiatan'][] = ['nama' => $nama, 'items' => [], 'grup' => [], 'sub_excel' => $sub];
            $gi = count($kats[$ki]['kegiatan']) - 1; $ji = -1;
        };

        foreach ($baris as $no => $cells) {
            if ($no <= $noHeader) { continue; }
            $r = $bacaBaris($cells);
            if ($r['nm'] === null) { continue; }
            $nm = trim(preg_replace('/\s+/u', ' ', $r['nm']));
            if ($nm === '') { continue; }

            // baris Total / Jumlah
            if (preg_match('/^(grand\s*total|t\s*o\s*t\s*a\s*l|jumlah|sub\s*total)\b/iu', $nm)) {
                if ($r['tot'] !== null) { $totalExcelSheet = $r['tot']; }
                elseif ($r['sub'] !== null) { $totalExcelSheet = $r['sub']; }
                $peranBaris[$no] = 'total';
                continue;
            }

            $bernomorIni = ($kolNo !== null && $adaNomor($r['no']));

            if (!$eItem($r)) {
                // 1) judul kategori (cocok dengan blok ringkasan)
                $rIdx = (!$bernomorIni && !preg_match('/^\d+[.)]\s/', $nm)) ? $cocokRingkas($nm) : null;
                if ($rIdx !== null && !$ringkas[$rIdx]['dipakai']) { $kategoriBaru($nm, $rIdx); $peranBaris[$no] = 'kat'; continue; }
                // 2) lanjutan nama baris sebelumnya (mis. judul panjang yang dipotong ke baris berikutnya)
                if ($hier && !$bernomorIni && !preg_match('/^\d+[.)]\s/', $nm) && $akhir !== null && $akhir['bernomor'] && $r['sub'] === null) {
                    $kats[$akhir['ki']]['kegiatan'][$akhir['gi']]['nama'] .= ' ' . $nm;
                    $kats[$akhir['ki']]['kegiatan'][$akhir['gi']]['items'][$akhir['ii']]['jenis'] .= ' ' . $nm;
                    $peranBaris[$no] = 'lanjut';
                    continue;
                }
                if ($hier) {
                    if ($bernomorIni) { $kegiatanBaru($bersih($nm), $r['sub']); $peranBaris[$no] = 'keg'; }
                    else {   // judul tanpa nomor = Lokasi/Kelompok di bawah kegiatan
                        if ($gi < 0 || $ki < 0) { $kegiatanBaru($bersih($nm)); $peranBaris[$no] = 'keg'; }
                        else { $peranBaris[$no] = 'grup'; $kats[$ki]['kegiatan'][$gi]['grup'][] = ['nama' => $bersih($nm), 'items' => [], 'children' => []]; $ji = count($kats[$ki]['kegiatan'][$gi]['grup']) - 1; }
                    }
                } else {
                    $kegiatanBaru($nm, $r['sub']);
                    $peranBaris[$no] = 'keg';
                }
                continue;
            }

            // ---- baris item
            $item = ['baris' => $no, 'jenis' => $nm, 'kali' => 1.0, 'dim2' => null, 'dim2_label' => '', 'hari' => null, 'satuan' => 0.0, 'tipe_item' => 'biasa'];
            $harga = $r['harga']; $tot = $r['tot']; $catatan = [];
            if ($r['pairs']) {
                $kaliSet = false;
                foreach ($r['pairs'] as $pr) {
                    list($n, $lb) = $pr; $l = mb_strtolower(trim($lb), 'UTF-8');
                    if ($l === 'kali' || $l === 'x') { $item['kali'] = $n; $kaliSet = true; }
                    elseif ($l === '' && !$kaliSet) { $item['kali'] = $n; $kaliSet = true; }
                    elseif (self::eHari($l)) { $item['hari'] = $n; }
                    elseif ($item['dim2'] === null) { $item['dim2'] = $n; $item['dim2_label'] = $lb; }
                    else { $catatan[] = 'angka tambahan "' . self::fmtAngka($n) . ' ' . $lb . '" tidak dipakai'; }
                }
            } elseif ($r['kali'] !== null || $r['dim2'] !== null || $r['hari'] !== null) {
                $item['kali'] = $r['kali'] !== null ? $r['kali'] : 1.0;
                $item['dim2'] = $r['dim2']; if ($r['dim2'] !== null) { $item['dim2_label'] = is_string($r['sat']) ? $r['sat'] : ''; }
                $item['hari'] = $r['hari'];
            } elseif ($r['vol'] !== null) {
                $pm = self::petakanVolume(is_string($r['vol']) ? $r['vol'] : (float) $r['vol'], is_string($r['sat']) ? $r['sat'] : '');
                if ($pm) { $item = array_merge($item, $pm); } else { $item['_volume_gagal'] = (string) $r['vol']; }
            }
            $item['satuan'] = $harga !== null ? $harga : 0.0;

            if (isset($item['_volume_gagal'])) {
                if ($harga && $tot) {
                    $item['kali'] = round($tot / $harga, 2); $item['dim2'] = null; $item['hari'] = null;
                    $catatan[] = 'volume "' . $item['_volume_gagal'] . '" tidak terbaca, dihitung dari total ÷ harga = ' . self::fmtAngka($item['kali']);
                } else {
                    $masalah[] = ['sheet' => $namaSheet, 'baris' => $no, 'tingkat' => 'bad', 'pesan' => 'Volume "' . $item['_volume_gagal'] . '" tidak terbaca.']; $masalahSheet++;
                }
                unset($item['_volume_gagal']);
            }
            if ($harga === null) {
                $qty = self::hitungItem(array_merge($item, ['satuan' => 1.0]));
                if ($tot !== null && $qty > 0) { $item['satuan'] = round($tot / $qty, 2); $catatan[] = 'harga satuan tidak ada, dihitung dari total ÷ volume'; }
                else { $masalah[] = ['sheet' => $namaSheet, 'baris' => $no, 'tingkat' => 'bad', 'pesan' => 'Harga satuan tidak ditemukan.']; $masalahSheet++; }
            }
            $hit = self::hitungItem($item);
            if ($tot !== null && abs($hit - $tot) > max(1, 0.005 * abs($tot))) {
                $catatan[] = 'total di Excel ' . self::rp($tot) . ' beda dengan hitungan ' . self::rp($hit);
                $masalah[] = ['sheet' => $namaSheet, 'baris' => $no, 'tingkat' => 'warn', 'pesan' => 'Total di Excel (' . self::rp($tot) . ') beda dengan hitungan volume × harga (' . self::rp($hit) . ').']; $masalahSheet++;
            }
            foreach ($catatan as $c) { if (strpos($c, 'beda dengan') === false) { $koreksi[] = ['sheet' => $namaSheet, 'baris' => $no, 'catatan' => $c]; } }
            $item['total_hitung'] = $hit; $item['total_excel'] = $tot; $item['catatan'] = $catatan;

            // letakkan item
            if ($hier && $bernomorIni) {
                $kegiatanBaru($nm, null); $item['jenis'] = $nm;
                $kats[$ki]['kegiatan'][$gi]['items'][] = $item;
                $akhir = ['ki' => $ki, 'gi' => $gi, 'ii' => count($kats[$ki]['kegiatan'][$gi]['items']) - 1, 'bernomor' => true];
            } else {
                if ($gi < 0 || $ki < 0) { $kegiatanBaru(''); }
                if ($ji >= 0) { $kats[$ki]['kegiatan'][$gi]['grup'][$ji]['items'][] = $item; $ii = count($kats[$ki]['kegiatan'][$gi]['grup'][$ji]['items']) - 1; }
                else { $kats[$ki]['kegiatan'][$gi]['items'][] = $item; $ii = count($kats[$ki]['kegiatan'][$gi]['items']) - 1; }
                $akhir = ['ki' => $ki, 'gi' => $gi, 'ii' => $ii, 'bernomor' => false];
            }
            $jumlahBaris++; $peranBaris[$no] = 'item';
        }

        // ---- rapikan: buang kegiatan / grup kosong, isi nama kosong, hitung total, cek subtotal kegiatan
        $hasil = [];
        foreach ($kats as $k) {
            $keg = [];
            foreach ($k['kegiatan'] as $kg) {
                $grup = [];
                foreach ($kg['grup'] as $g) { if ($g['items']) { $grup[] = $g; } }
                $kg['grup'] = $grup;
                if (!$kg['items'] && !$kg['grup']) { continue; }
                if ($kg['nama'] === '') { $kg['nama'] = $k['nama']; }
                $hit = 0.0; $barisPertama = null;
                foreach ($kg['items'] as $it) { $hit += $it['total_hitung']; if ($barisPertama === null) { $barisPertama = $it['baris']; } }
                foreach ($kg['grup'] as $g) { foreach ($g['items'] as $it) { $hit += $it['total_hitung']; if ($barisPertama === null) { $barisPertama = $it['baris']; } } }
                $kg['total_hitung'] = $hit;
                if ($kg['sub_excel'] !== null && abs($hit - $kg['sub_excel']) > max(1, 0.005 * abs($kg['sub_excel']))) {
                    $masalah[] = ['sheet' => $namaSheet, 'baris' => $barisPertama, 'tingkat' => 'warn', 'pesan' => 'Total Biaya kegiatan "' . $kg['nama'] . '" di Excel (' . self::rp($kg['sub_excel']) . ') beda dengan jumlah itemnya (' . self::rp($hit) . ').']; $masalahSheet++;
                }
                $k['total'] += $hit;
                $keg[] = $kg;
            }
            if (!$keg) { continue; }
            $k['kegiatan'] = $keg; $k['sheet'] = $namaSheet;
            $hasil[] = $k;
        }
        if (count($hasil) === 1 && $totalExcelSheet !== null) { $hasil[0]['total_excel'] = $totalExcelSheet; }

        // kategori di blok ringkasan yang tidak punya rincian -> kategori "tanpa rincian item" (nilai = persen x Nilai Kontrak)
        foreach ($ringkas as $r) {
            if ($r['dipakai'] || ($r['persen'] === null && $r['nilai'] === null)) { continue; }
            // cocokkan dengan kategori berisi item yang namanya mirip (judul kategori tidak ada di tabel)
            $sudah = false;
            foreach ($hasil as $i => $k) {
                $a = self::norm($k['nama']); $b = self::norm($r['nama']);
                if ($a !== '' && ($a === $b || strpos($a, $b) !== false || strpos($b, $a) !== false)) { $sudah = true; break; }
            }
            if ($sudah) { continue; }
            $hasil[] = ['sheet' => $namaSheet, 'nama' => $r['nama'], 'kegiatan' => [], 'total' => (float) ($r['nilai'] !== null ? $r['nilai'] : 0), 'total_excel' => null,
                        'rekap' => $r['nilai'], 'cocok' => true, 'persen' => $r['persen'] !== null ? rtrim(rtrim(number_format($r['persen'], 2, '.', ''), '0'), '.') : '', 'tanpa_rincian' => true];
        }
        return ['kategori' => $hasil, 'nilai_kontrak' => $nilaiKontrak, 'ringkas' => $ringkas, 'jumlah' => $jumlahBaris, 'masalah_sheet' => $masalahSheet];
    }

    /**
     * Analisis seluruh buku. Keluaran:
     * [
     *   'sheets'   => [['nama','jenis'(rab|rekap|lewati),'alasan','kategori_nama','jumlah_item','total','baris_masalah'], ...],
     *   'kategori' => [['sheet','nama','kegiatan'=>[...format form...],'total','total_excel','rekap','cocok'(true|false|null)], ...],
     *   'rekap'    => [['nama'=>,'nilai'=>], ...],
     *   'masalah'  => [['sheet','baris','pesan'], ...],
     *   'koreksi'  => [['sheet','baris','catatan'], ...],
     *   'total'    => float, 'total_rekap' => float|null, 'cocok_rekap' => bool|null
     * ]
     */
    public static function analisis(array $buku) {
        // analisis tetap bekerja pada teks satu baris (sel multi-baris diratakan)
        foreach ($buku['sheets'] as $si => $shx) { foreach ($shx['baris'] as $no => $cellsx) { foreach ($cellsx as $kx => $vx) { if (is_string($vx) && strpos($vx, "\n") !== false) { $buku['sheets'][$si]['baris'][$no][$kx] = trim(preg_replace('/\s+/u', ' ', $vx)); } } } }
        $sheetsOut = []; $kategori = []; $rekapRows = []; $masalah = []; $koreksi = []; $nilaiKontrakExcel = null;

        // ---- sheet pendukung: dirujuk lewat rumus dari sheet RAB lain (mis. Rincian Kebutuhan / Deskripsi yang diambil berdasarkan Kode).
        // Nilainya sudah terbawa ke sheet RAB, jadi tidak dihitung lagi (kalau tidak, total jadi dobel).
        $pendukung = [];
        foreach ($buku['sheets'] as $sa) {
            if (preg_match('/rekap|ringkasan|summary|resume|rangkuman/i', $sa['nama']) || empty($sa['rumus']) || !self::cariHeader($sa['baris'])) { continue; }
            $dirujuk = [];
            foreach ($sa['rumus'] as $rowF) {
                foreach ($rowF as $f) {
                    if (preg_match_all("/(?:'((?:[^']|'')+)'|([A-Za-z0-9_.]+))!/u", $f, $mm, PREG_SET_ORDER)) {
                        foreach ($mm as $x) { $dirujuk[mb_strtolower(str_replace("''", "'", $x[1] !== '' ? $x[1] : $x[2]), 'UTF-8')] = true; }
                    }
                }
            }
            foreach ($buku['sheets'] as $sb) {
                $kb = mb_strtolower($sb['nama'], 'UTF-8');
                if ($sb['nama'] !== $sa['nama'] && isset($dirujuk[$kb])) { $pendukung[$kb][] = $sa['nama']; }
            }
        }
        // saling merujuk = ambigu, jangan dilewati
        foreach ($pendukung as $kb => $oleh) {
            foreach ($buku['sheets'] as $sb) {
                if (mb_strtolower($sb['nama'], 'UTF-8') !== $kb) { continue; }
                foreach ($oleh as $namaA) { if (isset($pendukung[mb_strtolower($namaA, 'UTF-8')]) && in_array($sb['nama'], $pendukung[mb_strtolower($namaA, 'UTF-8')], true)) { unset($pendukung[$kb]); break 2; } }
            }
        }

        foreach ($buku['sheets'] as $sh) {
            $nama = $sh['nama']; $baris = $sh['baris'];
            $info = ['nama' => $nama, 'jenis' => 'lewati', 'alasan' => '', 'kategori_nama' => '', 'jumlah_item' => 0, 'total' => 0.0, 'baris_masalah' => 0, 'peran' => []];
            if (!$baris) { $info['alasan'] = 'Sheet kosong.'; $sheetsOut[] = $info; continue; }

            $kn = mb_strtolower($nama, 'UTF-8');
            if (isset($pendukung[$kn])) {
                $info['alasan'] = 'Data pendukung: dirujuk lewat rumus oleh sheet ' . implode(', ', $pendukung[$kn]) . ', jadi tidak dihitung lagi.';
                $sheetsOut[] = $info; continue;
            }
            $hd = self::cariHeader($baris);
            if (!$hd) { $info['alasan'] = 'Tidak ada tabel biaya (judul kolom Uraian / Volume / Harga tidak ditemukan).'; $sheetsOut[] = $info; continue; }
            list($noHeader, $peran) = $hd;
            $pb = [(int) $noHeader => 'hdr'];

            $adaHarga = isset($peran['harga']);
            $adaVolume = isset($peran['volume']) || isset($peran['kali']) || isset($peran['dim2']) || isset($peran['hari']);
            $namaSheetRekap = (bool) preg_match('/rekap|ringkasan|summary|resume|rangkuman/i', $nama);

            // ---- sheet rekap: hanya nama + total, tanpa harga satuan / volume
            if ($namaSheetRekap || (!$adaHarga && !$adaVolume && isset($peran['total']))) {
                $info['jenis'] = 'rekap';
                foreach ($baris as $no => $cells) {
                    if ($no <= $noHeader) { continue; }
                    $n = isset($cells[$peran['nama']]) ? $cells[$peran['nama']] : null;
                    $nilai = isset($peran['total']) && isset($cells[$peran['total']]) ? self::angka($cells[$peran['total']]) : null;
                    if (!is_string($n) || $nilai === null || $nilai <= 0) { continue; }
                    if (preg_match('/^(grand ?total|total|jumlah|sub ?total)/i', $n)) { $info['total'] = $nilai; continue; }
                    $rekapRows[] = ['nama' => $n, 'nilai' => $nilai]; $pb[(int) $no] = 'rekap';
                    $info['jumlah_item']++;
                }
                $info['alasan'] = 'Dipakai sebagai pembanding total tiap kategori.'; $info['peran'] = $pb;
                $sheetsOut[] = $info; continue;
            }

            // ---- sheet RAB
            $br = self::bacaSheetRab($nama, $baris, $noHeader, $peran, $masalah, $koreksi, $pb);
            if ($br['jumlah'] === 0 && !$br['kategori']) { $info['alasan'] = 'Tidak ada baris biaya yang terbaca.'; $sheetsOut[] = $info; continue; }
            $info['peran'] = $pb;
            // sel yang rumusnya error (#DIV/0!, #VALUE!, ...): tandai barisnya, gantikan catatan lain di baris itu
            if (!empty($sh['galat'])) {
                foreach ($sh['galat'] as $noG => $kolG) {
                    if ($noG <= $noHeader) { continue; }
                    $masalah = array_values(array_filter($masalah, function ($m) use ($nama, $noG) { return !($m['sheet'] === $nama && (int) $m['baris'] === (int) $noG); }));
                    foreach ($kolG as $kk => $ee) { $masalah[] = ['sheet' => $nama, 'baris' => (int) $noG, 'tingkat' => 'bad', 'pesan' => 'Sel ' . self::hurufKolom($kk) . $noG . ' berisi error rumus (' . $ee . ').']; $br['masalah_sheet']++; break; }
                }
            }
            if ($nilaiKontrakExcel === null && $br['nilai_kontrak'] !== null) { $nilaiKontrakExcel = $br['nilai_kontrak']; }
            $namaKat = []; $totalSheet = 0.0;
            foreach ($br['kategori'] as $k) { $kategori[] = $k; $namaKat[] = $k['nama']; $totalSheet += $k['total']; }
            $info['jenis'] = 'rab'; $info['jumlah_item'] = $br['jumlah']; $info['total'] = $totalSheet; $info['baris_masalah'] = count(array_filter($masalah, function ($m) use ($nama) { return $m['sheet'] === $nama; }));
            $info['kategori_nama'] = implode(' + ', $namaKat);
            $sheetsOut[] = $info;
        }

        // ---- cocokkan nama kategori dan total dengan sheet rekap
        $totalRekap = null;
        foreach ($kategori as $i => &$k) {
            $kn = self::norm($k['sheet']);
            if ($k['rekap'] !== null) { $rekapRows = $rekapRows; }
            foreach (($k['rekap'] !== null ? [] : $rekapRows) as $r) {
                $rn = self::norm($r['nama']);
                if ($rn === '' || $kn === '') { continue; }
                if ($rn === $kn || strpos($rn, $kn) !== false || strpos($kn, $rn) !== false) {
                    $k['nama'] = $r['nama']; $k['rekap'] = $r['nilai'];
                    $k['cocok'] = abs($k['total'] - $r['nilai']) <= max(1, 0.001 * $r['nilai']);
                    break;
                }
            }
            if ($k['rekap'] !== null && $k['cocok'] === null) { $k['cocok'] = abs($k['total'] - $k['rekap']) <= max(1, 0.001 * $k['rekap']); }
            // rekap tidak ada tapi baris "Jumlah" di sheet ada -> bandingkan dengan itu
            if ($k['rekap'] === null && $k['total_excel'] !== null) {
                $k['cocok'] = abs($k['total'] - $k['total_excel']) <= max(1, 0.001 * $k['total_excel']);
            }
            // kegiatan tanpa judul bagian -> pakai nama kategori
            foreach ($k['kegiatan'] as &$kg) { if ($kg['nama'] === '') { $kg['nama'] = $k['nama']; } }
            unset($kg);
        }
        unset($k);
        if ($rekapRows) { $totalRekap = 0.0; foreach ($rekapRows as $r) { $totalRekap += $r['nilai']; } }

        $total = 0.0; foreach ($kategori as $k) { $total += $k['total']; }
        return [
            'sheets' => $sheetsOut, 'kategori' => $kategori, 'rekap' => $rekapRows,
            'masalah' => $masalah, 'koreksi' => $koreksi,
            'total' => $total, 'total_rekap' => $totalRekap, 'nilai_kontrak' => $nilaiKontrakExcel,
            'cocok_rekap' => $totalRekap === null ? null : (abs($total - $totalRekap) <= max(1, 0.001 * $totalRekap)),
        ];
    }

    /**
     * Tabel RAB untuk dokumen PO (pratinjau & PDF) dari berkas Excel: tampilannya mengikuti sheet apa adanya
     * (kolom & urutan baris sama), hanya dirapikan: baris/kolom kosong dibuang, judul kolom digabung, Rp & % dari format sel.
     * Nilai diambil dari berkas di server (hasil hitung terakhir), jadi selalu ikut perubahan yang disimpan dari form.
     * Mengembalikan HTML (gaya ditulis inline agar sama di layar dan Dompdf), atau '' bila gagal.
     */
    public static function htmlDokumen($path, array $namaSheet, $namaBerkas = '') {
        try {
            $buku = self::bacaBuku($path);
            $grid = self::gridDariBuku($buku);
        } catch (\Throwable $e) { return ''; }
        $pilih = [];
        foreach ($grid['sheets'] as $sh) { if (!$namaSheet || in_array($sh['nama'], $namaSheet, true)) { $pilih[] = $sh; } }
        if (!$pilih && $grid['sheets']) { $pilih[] = $grid['sheets'][0]; }
        $h = '';
        foreach ($pilih as $sh) {
            $t = self::htmlSheetDokumen($sh);
            if ($t === '') { continue; }
            if (count($pilih) > 1) { $h .= '<div style="font-weight:bold;font-size:10pt;margin:10px 0 3px;">' . htmlspecialchars($sh['nama'], ENT_QUOTES, 'UTF-8') . '</div>'; }
            $h .= $t;
        }
        if ($h === '') { return ''; }
        return $h;
    }

    protected static function nilaiSelDokumen($c) {
        if (is_array($c)) { return isset($c['e']) ? (string) $c['e'] : (array_key_exists('v', $c) ? $c['v'] : null); }
        return $c;
    }

    protected static function teksAngkaDokumen($v, $fmt) {
        $k = ''; $d = null;
        if (preg_match('/^([a-z]+)(?::(\d))?$/', (string) $fmt, $m)) { $k = $m[1]; $d = isset($m[2]) ? (int) $m[2] : null; }
        $v = (float) $v;
        if ($k === 'rp') { $dr = $d === null ? 0 : $d; return (abs($v) < pow(10, -$dr) / 2) ? 'Rp -' : 'Rp ' . number_format($v, $dr, ',', '.'); }
        if ($k === 'pct') { return $d === null ? rtrim(rtrim(number_format($v * 100, 2, ',', '.'), '0'), ',') . '%' : number_format($v * 100, $d, ',', '.') . '%'; }
        if ($k === 'sep') { return number_format($v, $d === null ? 0 : $d, ',', '.'); }
        if ($k === 'gen' && $d !== null) { return number_format($v, $d, ',', ''); }
        if ($k === 'txt') { return $v == floor($v) ? (string) (int) round($v) : rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.'); }
        if ($v == floor($v)) { return (string) (int) round($v); }
        return rtrim(rtrim(number_format($v, 4, ',', ''), '0'), ',');
    }

    protected static function htmlSheetDokumen(array $sh) {
        $huruf = function ($i) { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - $m - 1, 26); } return $s; };
        $fmtSel = isset($sh['fmt']) ? (array) $sh['fmt'] : [];
        $gayaSel = isset($sh['gaya']) ? (array) $sh['gaya'] : [];
        $lebarSel = isset($sh['lebar']) ? (array) $sh['lebar'] : [];
        $adaGaya = false;   // true bila file punya gaya tebal / rata / bungkus (indent saja tidak dihitung, supaya tebal otomatis tetap jalan)
        foreach ($gayaSel as $gSel) { $gSel = (array) $gSel; if (isset($gSel['b']) || isset($gSel['a']) || isset($gSel['w'])) { $adaGaya = true; break; } }   // gaya dikunci per alamat sel (mis. K17 => ['b'=>1])
        // 1. baris yang ada isinya
        $baris = []; $adaKol = []; $noBaris = [];
        foreach ($sh['baris'] as $br) {
            $no = (int) $br[0]; $sel = [];
            $kosong = true;
            foreach ($br[1] as $ci => $c) {
                $v = self::nilaiSelDokumen($c);
                if ($v === null || $v === '') { $sel[$ci] = null; continue; }
                $kosong = false; $adaKol[$ci] = true;
                $adr = $huruf($ci) . $no; $gy = isset($gayaSel[$adr]) ? (array) $gayaSel[$adr] : [];
                if (is_numeric($v) && !is_string($v)) { $sel[$ci] = ['n', (float) $v, self::teksAngkaDokumen($v, isset($fmtSel[$adr]) ? $fmtSel[$adr] : ''), $gy, $adr]; }
                else { $sel[$ci] = ['t', (string) $v, (string) $v, $gy, $adr]; }
            }
            if (!$kosong) { $baris[] = $sel; $noBaris[] = $no; }
        }
        if (!$baris) { return ''; }
        $kol = array_keys($adaKol); sort($kol);
        $nk = count($kol);
        // 2. jenis tiap baris + bobot lebar kolom
        $info = []; $bobot = array_fill(0, $nk, 3);
        foreach ($baris as $bi => $sel) {
            $c = []; $nNum = 0; $nTxt = 0; $pertama = null;
            foreach ($kol as $k => $ci) { $c[$k] = isset($sel[$ci]) ? $sel[$ci] : null; if ($c[$k]) { if ($c[$k][0] === 'n') { $nNum++; } else { $nTxt++; } if ($pertama === null) { $pertama = $k; } } }
            $jenis = 'biasa';
            if ($nNum === 0 && $nTxt >= 2) { $jenis = 'judul'; }
            elseif ($nNum === 0 && $nTxt === 1) { $jenis = ($pertama === 0) ? 'bagian' : 'sub'; }
            $info[$bi] = ['c' => $c, 'jenis' => $jenis, 'nNum' => $nNum, 'pertama' => $pertama];
            if ($jenis === 'biasa') {
                foreach ($c as $k => $x) { if ($x) { $bobot[$k] = max($bobot[$k], min(45, mb_strlen($x[2]) + 2)); } }
            }
        }
        foreach ($kol as $k => $ci) { if (isset($lebarSel[$huruf($ci)]) && (int) $lebarSel[$huruf($ci)] > 0) { $bobot[$k] = max(2, (int) $lebarSel[$huruf($ci)] / 7); } }
        // lebar dari Excel dipakai sebagai patokan, tapi tidak boleh lebih sempit dari isi angka / teks pendeknya (kalau tidak, "Rp 18.000.000" meluber ke kolom sebelah)
        foreach ($info as $rr) {
            foreach ($rr['c'] as $k => $x) {
                if (!$x) { continue; }
                $pj = mb_strlen($x[2]);
                if ($x[0] === 'n') { $bobot[$k] = max($bobot[$k], 1.6 * $pj + 2.5); }
                elseif ($rr['jenis'] === 'biasa' && $pj <= 14) { $bobot[$k] = max($bobot[$k], 1.7 * $pj + 3); }
                elseif ($rr['jenis'] === 'judul' && $pj <= 9) { $bobot[$k] = max($bobot[$k], 1.9 * $pj + 3); }
            }
        }
        // sel gabungan -> colspan / rowspan (hanya pada baris yang tampil)
        $sp = []; $tt = [];
        foreach (isset($sh['merges']) ? (array) $sh['merges'] : [] as $mg) {
            if (!preg_match('/^([A-Z]+\d+):([A-Z]+\d+)$/', (string) $mg, $mm)) { continue; }
            list($mr1, $mc1) = self::indeksKolom($mm[1]); list($mr2, $mc2) = self::indeksKolom($mm[2]);
            $bis = []; foreach ($noBaris as $bi => $nb) { if ($nb >= $mr1 && $nb <= $mr2) { $bis[] = $bi; } }
            $ks = []; foreach ($kol as $k => $ci) { if ($ci >= $mc1 && $ci <= $mc2) { $ks[] = $k; } }
            if (!$bis || !$ks) { continue; }
            if (count($bis) > 1) { foreach ($bis as $bi) { if (!in_array($info[$bi]['jenis'], ['biasa', 'judul'], true)) { $bis = [$bis[0]]; break; } } }
            if (count($bis) === 1 && count($ks) === 1) { continue; }
            $sp[$bis[0]][$ks[0]] = [count($ks), count($bis)];
            foreach ($bis as $bi) { foreach ($ks as $k) { if (!($bi === $bis[0] && $k === $ks[0])) { $tt[$bi][$k] = true; } } }
        }
        $jml = array_sum($bobot); $lebar = [];
        foreach ($bobot as $k => $b) { $lebar[$k] = round($b * 100 / $jml, 2); }
        // 3. HTML
        $tb = 'border:1px solid #444;padding:1px 3px;font-size:7.5pt;line-height:1.25;vertical-align:middle;';
        $o = '<table data-sh="' . htmlspecialchars((string) $sh['nama'], ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;width:100%;table-layout:fixed;margin:0;"><colgroup>';
        foreach ($lebar as $w) { $o .= '<col style="width:' . $w . '%;">'; }
        // baris judul di awal tabel (judul tabel + judul kolom) dimasukkan ke <thead>: pemecah halaman pratinjau dan PDF mengulangnya di tiap halaman
        // dan tidak meninggalkan judul sendirian di dasar halaman. Hanya bila ada baris judul kolom dan sesudahnya masih ada baris isi.
        $nHead = 0; $adaJudulKol = false; $adaIsi = false;
        foreach ($info as $rr0) {
            if ($rr0['jenis'] === 'biasa') { $adaIsi = true; break; }
            if ($rr0['jenis'] === 'judul') { $adaJudulKol = true; }
            $nHead++;
        }
        if (!$adaJudulKol || !$adaIsi || $nHead > 6) { $nHead = 0; }
        $o .= '</colgroup>' . ($nHead > 0 ? '<thead>' : '<tbody>') . '<tr>';
        foreach ($lebar as $w) { $o .= '<td style="width:' . $w . '%;padding:0;border:0;height:0;font-size:1px;line-height:1px;"></td>'; }
        $o .= '</tr>';
        $e = function ($t) { return nl2br(htmlspecialchars($t, ENT_QUOTES, 'UTF-8')); };
        $dA = function ($x) { return ($x && !empty($x[4])) ? ' data-a="' . $x[4] . '"' : ''; };   // alamat sel Excel (mis. K17): dipakai form untuk menyorot sel yang diubah
        foreach ($info as $bi => $r) {
            if ($nHead > 0 && $bi === $nHead) { $o .= '</thead><tbody>'; }
            $c = $r['c']; $jenis = $r['jenis'];
            $tebalSel = function ($x, $biasa) use ($adaGaya) { return $adaGaya ? !empty($x[3]['b']) : $biasa; };
            $ratakan = function ($x, $bawaan) { $a = isset($x[3]['a']) ? $x[3]['a'] : ''; $jorok = !empty($x[3]['i']) ? 'padding-left:' . (3 + 9 * (int) $x[3]['i']) . 'px;' : ''; if ($a === 'l') { return 'text-align:left;' . $jorok; } if ($a === 'c') { return 'text-align:center;' . $jorok; } if ($a === 'r') { return 'text-align:right;' . $jorok; } return $bawaan . $jorok; };
            if ($jenis === 'bagian') { $o .= '<tr><td' . $dA($c[0]) . ' colspan="' . $nk . '" style="' . $tb . $ratakan($c[0], 'text-align:center;') . ($tebalSel($c[0], true) ? 'font-weight:bold;' : '') . 'background:#f3f5f8;">' . $e($c[0][2]) . '</td></tr>'; continue; }
            if ($jenis === 'sub') { $p = $r['pertama']; $o .= '<tr>' . ($p > 0 ? '<td colspan="' . $p . '" style="' . $tb . '"></td>' : '') . '<td' . $dA($c[$p]) . ' colspan="' . ($nk - $p) . '" style="' . $tb . $ratakan($c[$p], '') . ($tebalSel($c[$p], false) ? 'font-weight:bold;' : '') . '">' . $e($c[$p][2]) . '</td></tr>'; continue; }
            $o .= '<tr>';
            if ($jenis === 'judul') {
                for ($k = 0; $k < $nk; $k++) {
                    if (!empty($tt[$bi][$k])) { continue; }
                    $mgk = isset($sp[$bi][$k]) ? $sp[$bi][$k] : null;
                    if ($c[$k] === null && !$mgk) { $o .= '<td style="' . $tb . 'background:#e4e8ee;"></td>'; continue; }
                    $span = 1; while ($k + $span < $nk && $c[$k + $span] === null && empty($tt[$bi][$k + $span])) { $span++; }
                    if ($mgk) { $span = $mgk[0]; }
                    $rsp = ($mgk && $mgk[1] > 1) ? ' rowspan="' . $mgk[1] . '"' : '';
                    $o .= '<td' . $dA($c[$k]) . ($span > 1 ? ' colspan="' . $span . '"' : '') . $rsp . ' style="' . $tb . ($c[$k] !== null ? $ratakan($c[$k], 'text-align:center;') . ($tebalSel($c[$k], true) ? 'font-weight:bold;' : '') : '') . 'background:#e4e8ee;">' . ($c[$k] !== null ? $e($c[$k][2]) : '') . '</td>';
                    $k += $span - 1;
                }
            } else {
                $tebal = false;
                if ($c[0] && $c[0][0] === 'n') { $tebal = true; }
                foreach ($c as $x) { if ($x && $x[0] === 't' && preg_match('/^t\s*o\s*t\s*a\s*l\b/iu', trim($x[1]))) { $tebal = true; } }
                for ($k = 0; $k < $nk; $k++) {
                    if (!empty($tt[$bi][$k])) { continue; }
                    $mgk = isset($sp[$bi][$k]) ? $sp[$bi][$k] : null;
                    $mgAttr = $mgk ? (($mgk[0] > 1 ? ' colspan="' . $mgk[0] . '"' : '') . ($mgk[1] > 1 ? ' rowspan="' . $mgk[1] . '"' : '')) : '';
                    $x = $c[$k]; $st = $tb . ($x !== null ? ($tebalSel($x, $tebal) ? 'font-weight:bold;' : '') : ($tebal && !$adaGaya ? 'font-weight:bold;' : ''));
                    if ($x === null) { $o .= '<td' . $mgAttr . ' style="' . $st . '"></td>'; continue; }
                    if ($x[0] === 'n') { $st .= $ratakan($x, ($k === 0) ? 'text-align:center;' : 'text-align:right;'); if ($k !== 0 || !empty($x[3]['a'])) { $st .= 'white-space:nowrap;'; } }
                    elseif ($r['nNum'] > 0 && mb_strlen($x[1]) <= 6) { $st .= $ratakan($x, 'text-align:center;'); }
                    else { $st .= $ratakan($x, ''); }
                    $o .= '<td' . $mgAttr . $dA($x) . ' style="' . $st . '">' . $e($x[2]) . '</td>';
                }
            }
            $o .= '</tr>';
        }
        return $o . '</tbody></table>';
    }

    /** Ringkasan satu kategori untuk tampilan tab (Ringkasan / Rincian) di form: kegiatan -> item (+ kelompok lokasi), tanpa isi sel mentah. */
    public static function ringkasKategori(array $k) {
        $item = function ($it) {
            return ['baris' => (int) $it['baris'], 'nama' => (string) $it['jenis'], 'kali' => $it['kali'], 'dim2' => $it['dim2'], 'dim2_label' => (string) $it['dim2_label'],
                    'hari' => $it['hari'], 'satuan' => (float) $it['satuan'], 'jumlah' => (float) (isset($it['total_hitung']) ? $it['total_hitung'] : 0)];
        };
        $jumlah = function (array $items) { $t = 0.0; foreach ($items as $i) { $t += $i['jumlah']; } return $t; };
        $keg = [];
        foreach ((isset($k['kegiatan']) ? $k['kegiatan'] : []) as $kg) {
            $its = array_map($item, isset($kg['items']) ? $kg['items'] : []);
            $grup = []; $tg = 0.0;
            foreach ((isset($kg['grup']) ? $kg['grup'] : []) as $g) {
                $gi = array_map($item, isset($g['items']) ? $g['items'] : []);
                $grup[] = ['nama' => (string) $g['nama'], 'total' => $jumlah($gi), 'items' => $gi]; $tg += $jumlah($gi);
            }
            $keg[] = ['nama' => (string) $kg['nama'], 'total' => $jumlah($its) + $tg, 'items' => $its, 'grup' => $grup];
        }
        return ['nama' => (string) $k['nama'], 'sheet' => $k['sheet'], 'total' => (float) $k['total'], 'persen' => (string) (isset($k['persen']) ? $k['persen'] : ''),
                'rekap' => $k['rekap'], 'total_excel' => $k['total_excel'], 'cocok' => $k['cocok'], 'tanpa_rincian' => !empty($k['tanpa_rincian']), 'kegiatan' => $keg];
    }

    /** Ubah kategori hasil analisis ke format kategori form (sama dengan yang disimpan di po_kegiatan.rab). */
    public static function keFormKategori(array $kat) {
        if (!empty($kat['tanpa_rincian'])) {
            return ['nama' => $kat['nama'], 'persen_manual' => (string) $kat['persen'], 'tanpa_rincian' => true];
        }
        $petaItem = function ($it) {
            return [
                'tipe_item' => 'biasa', 'disediakan_pt' => '',
                'jenis' => $it['jenis'], 'kali' => $it['kali'],
                'dim2' => $it['dim2'], 'dim2_label' => $it['dim2_label'],
                'hari' => $it['hari'], 'satuan' => $it['satuan'],
                'excel_baris' => $it['baris'], 'excel_catatan' => $it['catatan'],
            ];
        };
        $keg = [];
        foreach ($kat['kegiatan'] as $kg) {
            $grup = [];
            foreach ((isset($kg['grup']) ? $kg['grup'] : []) as $g) {
                $grup[] = ['nama' => $g['nama'], 'items' => array_map($petaItem, $g['items']), 'children' => []];
            }
            $keg[] = ['nama' => $kg['nama'], 'items' => array_map($petaItem, $kg['items']), 'grup' => $grup];
        }
        return ['nama' => $kat['nama'], 'persen_manual' => (string) (isset($kat['persen']) ? $kat['persen'] : ''), 'kegiatan' => $keg];
    }

    /* ============================================================ */
    /*  GRID: isi buku untuk ditampilkan / diedit di browser, dan    */
    /*  penulisan balik ke .xlsx                                     */
    /* ============================================================ */

    const MAKS_SHEET   = 20;
    const MAKS_MERGE   = 500;
    const MAKS_TEKS    = 2000;   // panjang teks satu sel
    const BARIS_CADANGAN = 5;   // baris kosong di bawah isi, supaya bisa menambah baris

    /**
     * Buku -> grid untuk browser: tiap sheet berisi baris 1..N berurutan (baris kosong ikut), tiap baris berisi sel berurutan kolom A..
     * ['sheets' => [['nama'=>, 'kol'=>int, 'baris'=>[[no, [sel,...]], ...]], ...]]
     */
    public static function gridDariBuku(array $buku) {
        $out = [];
        foreach ($buku['sheets'] as $sh) {
            $rumus = isset($sh['rumus']) ? $sh['rumus'] : []; $galat = isset($sh['galat']) ? $sh['galat'] : [];
            $maks = 0; $maxKol = 0;
            foreach ([$sh['baris'], $rumus, $galat] as $sumber) {
                foreach ($sumber as $no => $cells) { if ($cells) { $maks = max($maks, (int) $no); $maxKol = max($maxKol, max(array_keys($cells)) + 1); } }
            }
            $kol = max(8, min(self::MAKS_KOLOM, $maxKol + 1));
            $akhir = min(self::MAKS_BARIS, $maks + self::BARIS_CADANGAN);
            $baris = [];
            for ($no = 1; $no <= $akhir; $no++) {
                $row = [];
                $cells = isset($sh['baris'][$no]) ? $sh['baris'][$no] : [];
                for ($k = 0; $k < $kol; $k++) {
                    if (isset($rumus[$no][$k])) {
                        $c = ['f' => $rumus[$no][$k]];
                        if (isset($cells[$k])) { $c['v'] = $cells[$k]; }
                        if (isset($galat[$no][$k])) { $c['e'] = $galat[$no][$k]; }
                        $row[] = $c;
                    } elseif (isset($galat[$no][$k])) { $row[] = ['f' => '=', 'e' => $galat[$no][$k]]; }
                    else { $row[] = isset($cells[$k]) ? $cells[$k] : null; }
                }
                $baris[] = [$no, $row];
            }
            $fm = [];
            if (!empty($sh['fmt'])) { foreach ($sh['fmt'] as $no => $kolom) { foreach ($kolom as $k => $f) { if ($k < $kol && $no <= $akhir) { $fm[self::hurufKolom($k) . (int) $no] = $f; } } } }
            $gy = [];
            if (!empty($sh['gaya'])) { foreach ($sh['gaya'] as $no => $kolom) { foreach ($kolom as $k => $g) { if ($k < $kol && $no <= $akhir) { $gy[self::hurufKolom($k) . (int) $no] = $g; } } } }
            $lb = [];
            if (!empty($sh['lebar'])) { foreach ($sh['lebar'] as $k => $px) { if ($k < $kol) { $lb[self::hurufKolom($k)] = (int) $px; } } }
            $out[] = ['nama' => $sh['nama'], 'kol' => $kol, 'baris' => $baris, 'fmt' => (object) $fm, 'gaya' => (object) $gy, 'lebar' => (object) $lb, 'merges' => isset($sh['merges']) ? array_values($sh['merges']) : []];
        }
        return ['sheets' => $out];
    }

    /**
     * Grid hasil edit dari browser -> buku (format sama dengan bacaBuku, plus 'rumus' & 'galat'). Divalidasi ketat karena datang dari klien.
     * Sel boleh berupa angka / teks, atau {f: "=RUMUS", v: hasil, e: "#DIV/0!"}.
     * @throws \RuntimeException
     */
    public static function bukuDariGrid($grid) {
        if (!is_array($grid) || !isset($grid['sheets']) || !is_array($grid['sheets']) || !$grid['sheets']) { throw new \RuntimeException('Data tabel tidak valid.'); }
        if (count($grid['sheets']) > self::MAKS_SHEET) { throw new \RuntimeException('Jumlah sheet terlalu banyak.'); }
        $bersihTeks = function ($v, $baru = false) {
            $v = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $v);
            $v = $baru ? trim(preg_replace('/ ?\n ?/', "\n", preg_replace('/[^\S\n]+/u', ' ', str_replace(["\r\n", "\r"], "\n", $v)))) : trim(preg_replace('/\s+/u', ' ', $v));
            if (mb_strlen($v, 'UTF-8') > self::MAKS_TEKS) { $v = mb_substr($v, 0, self::MAKS_TEKS, 'UTF-8'); }
            return $v;
        };
        $sheets = [];
        foreach ($grid['sheets'] as $sh) {
            if (!is_array($sh) || !isset($sh['baris']) || !is_array($sh['baris'])) { throw new \RuntimeException('Data tabel tidak valid.'); }
            if (count($sh['baris']) > self::MAKS_BARIS) { throw new \RuntimeException('Jumlah baris melebihi batas (' . self::MAKS_BARIS . ').'); }
            $baris = []; $rumus = []; $galat = []; $fmt = []; $gaya = []; $lebar = [];
            $kolDari = function ($huruf) { $kk = 0; for ($q = 0; $q < strlen($huruf); $q++) { $kk = $kk * 26 + (ord($huruf[$q]) - 64); } return $kk - 1; };
            if (isset($sh['fmt']) && is_array($sh['fmt'])) {
                $n = 0;
                foreach ($sh['fmt'] as $ref => $f) {
                    if (++$n > 20000) { break; }
                    if (!is_string($ref) || !preg_match('/^([A-Z]{1,3})(\d{1,5})$/', $ref, $m) || !is_string($f) || !preg_match('/^(?:(?:gen|sep|rp|pct)(?::[0-6])?|txt)$/', $f)) { continue; }
                    $kk = $kolDari($m[1]); if ($kk >= self::MAKS_KOLOM || (int) $m[2] > self::MAKS_BARIS || (int) $m[2] < 1) { continue; }
                    $fmt[(int) $m[2]][$kk] = $f;
                }
            }
            if (isset($sh['gaya']) && is_array($sh['gaya'])) {
                $n = 0;
                foreach ($sh['gaya'] as $ref => $g) {
                    if (++$n > 20000) { break; }
                    if (!is_string($ref) || !is_array($g) || !preg_match('/^([A-Z]{1,3})(\d{1,5})$/', $ref, $m)) { continue; }
                    $kk = $kolDari($m[1]); if ($kk >= self::MAKS_KOLOM || (int) $m[2] > self::MAKS_BARIS || (int) $m[2] < 1) { continue; }
                    $o = [];
                    if (!empty($g['b'])) { $o['b'] = 1; }
                    if (isset($g['a']) && in_array($g['a'], ['l', 'c', 'r'], true)) { $o['a'] = $g['a']; }
                    if (!empty($g['w'])) { $o['w'] = 1; }
                    if (!empty($g['i']) && (int) $g['i'] > 0) { $o['i'] = min(15, (int) $g['i']); }
                    if ($o) { $gaya[(int) $m[2]][$kk] = $o; }
                }
            }
            $merges = [];
            if (isset($sh['merges']) && is_array($sh['merges'])) {
                foreach ($sh['merges'] as $mg) {
                    if (count($merges) >= self::MAKS_MERGE) { break; }
                    if (!is_string($mg) || !preg_match('/^([A-Z]{1,3})(\d{1,5}):([A-Z]{1,3})(\d{1,5})$/', $mg, $mm)) { continue; }
                    $c1 = $kolDari($mm[1]); $c2 = $kolDari($mm[3]); $r1 = (int) $mm[2]; $r2 = (int) $mm[4];
                    if ($c1 > $c2 || $r1 > $r2 || $r1 < 1 || $r2 > self::MAKS_BARIS || $c2 >= self::MAKS_KOLOM || ($c1 === $c2 && $r1 === $r2)) { continue; }
                    $merges[] = $mg;
                }
            }
            if (isset($sh['lebar']) && is_array($sh['lebar'])) {
                foreach ($sh['lebar'] as $huruf => $px) {
                    if (!is_string($huruf) || !preg_match('/^[A-Z]{1,3}$/', $huruf) || !is_numeric($px)) { continue; }
                    $kk = $kolDari($huruf); if ($kk >= self::MAKS_KOLOM) { continue; }
                    $lebar[$kk] = (int) max(24, min(800, round((float) $px)));
                }
            }
            foreach ($sh['baris'] as $row) {
                if (!is_array($row) || count($row) !== 2 || !is_array($row[1])) { continue; }
                $no = (int) $row[0];
                if ($no < 1 || $no > self::MAKS_BARIS) { continue; }
                $isi = [];
                foreach (array_slice($row[1], 0, self::MAKS_KOLOM) as $k => $v) {
                    $k = (int) $k;
                    if (is_array($v)) {
                        $f = isset($v['f']) && is_string($v['f']) ? $bersihTeks($v['f']) : '';
                        if (strlen($f) > 1 && $f[0] === '=' && strlen($f) <= 1000) { $rumus[$no][$k] = $f; }
                        $e = isset($v['e']) && is_string($v['e']) ? $v['e'] : '';
                        if ($e !== '' && preg_match('/^#[A-Z0\/!?]{2,8}$/', $e)) { $galat[$no][$k] = $e; continue; }
                        $v = array_key_exists('v', $v) ? $v['v'] : null;
                        if ($v === null) { continue; }
                    }
                    if (is_bool($v)) { $v = $v ? 'TRUE' : 'FALSE'; }
                    if (is_int($v) || is_float($v)) { $v = (float) $v; if (!is_finite($v)) { continue; } }
                    elseif (is_string($v)) { $v = $bersihTeks($v, true); if ($v === '') { continue; } }
                    else { continue; }
                    $isi[$k] = $v;
                }
                if ($isi) { ksort($isi); $baris[$no] = $isi; }
            }
            ksort($baris);
            $nama = isset($sh['nama']) && is_string($sh['nama']) ? trim($sh['nama']) : 'Sheet';
            $sheets[] = ['nama' => ($nama === '' ? 'Sheet' : mb_substr($nama, 0, 31, 'UTF-8')), 'baris' => $baris, 'rumus' => $rumus, 'galat' => $galat, 'fmt' => $fmt, 'gaya' => $gaya, 'lebar' => $lebar, 'merges' => $merges];
        }
        return ['sheets' => $sheets];
    }

    protected static function hurufKolom($i) {
        $h = ''; $i = (int) $i;
        do { $h = chr(65 + ($i % 26)) . $h; $i = intdiv($i, 26) - 1; } while ($i >= 0);
        return $h;
    }

    /** Tulis buku ke berkas .xlsx sederhana (format angka, tebal, rata, bungkus teks, lebar kolom; tanpa warna/garis). @throws \RuntimeException */
    public static function tulisXlsx(array $buku, $path) {
        if (!class_exists('ZipArchive')) { throw new \RuntimeException('Ekstensi zip PHP belum aktif.'); }
        $x = function ($t) { return htmlspecialchars((string) $t, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { throw new \RuntimeException('Berkas Excel tidak bisa ditulis.'); }
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wbSheets = ''; $wbRels = ''; $dipakai = [];
        // daftar gaya (xf) dibuat sesuai kebutuhan: format angka + tebal + rata + bungkus teks
        $kustom = []; $xfIdx = []; $xfXml = ['<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'];
        $gayaSel = function ($fm, $g) use (&$kustom, &$xfIdx, &$xfXml) {
            $fm = ($fm === 'gen') ? '' : (string) $fm; $b = !empty($g['b']); $a = isset($g['a']) ? $g['a'] : ''; $w = !empty($g['w']); $ind = !empty($g['i']) ? min(15, (int) $g['i']) : 0;
            if ($fm === '' && !$b && $a === '' && !$w && $ind === 0) { return 0; }
            $key = $fm . '|' . (int) $b . '|' . $a . '|' . (int) $w . '|' . $ind;
            if (isset($xfIdx[$key])) { return $xfIdx[$key]; }
            $nid = self::idNumFmt($fm, $kustom);
            $peta = ['l' => 'left', 'c' => 'center', 'r' => 'right'];
            $hz = ($a !== '') ? $peta[$a] : ($ind > 0 ? 'left' : '');   // indent di Excel hanya berlaku bersama rata kiri/kanan
            $al = ($hz !== '' || $w) ? '<alignment' . ($hz !== '' ? ' horizontal="' . $hz . '"' : '') . ($ind > 0 ? ' indent="' . $ind . '"' : '') . ($w ? ' wrapText="1"' : '') . '/>' : '';
            $xfXml[] = '<xf numFmtId="' . $nid . '" fontId="' . ($b ? 1 : 0) . '" fillId="0" borderId="0" xfId="0"' . ($nid ? ' applyNumberFormat="1"' : '') . ($b ? ' applyFont="1"' : '') . ($al !== '' ? ' applyAlignment="1">' . $al . '</xf>' : '/>');
            return $xfIdx[$key] = count($xfXml) - 1;
        };
        foreach ($buku['sheets'] as $i => $sh) {
            $n = $i + 1;
            $nm = trim((string) preg_replace('/[\[\]:*?\/\\\\]+/u', ' ', $sh['nama'])); if ($nm === '') { $nm = 'Sheet' . $n; }
            $nm = mb_substr($nm, 0, 31, 'UTF-8'); $dasar = $nm; $c = 2;
            while (isset($dipakai[mb_strtolower($nm, 'UTF-8')])) { $nm = mb_substr($dasar, 0, 28, 'UTF-8') . ' ' . $c++; }
            $dipakai[mb_strtolower($nm, 'UTF-8')] = true;
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wbSheets .= '<sheet name="' . $x($nm) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $rumus = isset($sh['rumus']) ? $sh['rumus'] : []; $galat = isset($sh['galat']) ? $sh['galat'] : [];
            $semua = [];
            $fmtS = isset($sh['fmt']) ? $sh['fmt'] : []; $gayaS = isset($sh['gaya']) ? $sh['gaya'] : [];
            foreach ([$sh['baris'], $rumus, $galat, $fmtS, $gayaS] as $sumber) { foreach ($sumber as $no => $cells) { foreach ($cells as $k => $_) { $semua[(int) $no][(int) $k] = true; } } }
            ksort($semua);
            $data = '';
            foreach ($semua as $no => $kolom) {
                ksort($kolom);
                $data .= '<row r="' . (int) $no . '">';
                foreach ($kolom as $k => $_) {
                    $ref = self::hurufKolom($k) . (int) $no;
                    $fm = isset($fmtS[$no][$k]) ? $fmtS[$no][$k] : '';
                    $si = $gayaSel($fm, isset($gayaS[$no][$k]) ? $gayaS[$no][$k] : []);
                    $sa = $si ? ' s="' . $si . '"' : '';
                    $v = isset($sh['baris'][$no][$k]) ? $sh['baris'][$no][$k] : null;
                    $f = isset($rumus[$no][$k]) ? '<f>' . $x(substr($rumus[$no][$k], 1)) . '</f>' : '';
                    if (isset($galat[$no][$k])) { $data .= '<c r="' . $ref . '"' . $sa . ' t="e">' . $f . '<v>' . $x($galat[$no][$k]) . '</v></c>'; }
                    elseif (is_float($v) || is_int($v)) { $data .= '<c r="' . $ref . '"' . $sa . '>' . $f . '<v>' . rtrim(rtrim(sprintf('%.10F', $v), '0'), '.') . '</v></c>'; }
                    elseif ($v === null) { $data .= ($f !== '' ? '<c r="' . $ref . '"' . $sa . '>' . $f . '</c>' : ($sa !== '' ? '<c r="' . $ref . '"' . $sa . '/>' : '')); }
                    elseif ($f !== '') { $data .= '<c r="' . $ref . '"' . $sa . ' t="str">' . $f . '<v>' . $x($v) . '</v></c>'; }
                    else { $data .= '<c r="' . $ref . '"' . $sa . ' t="inlineStr"><is><t xml:space="preserve">' . $x($v) . '</t></is></c>'; }
                }
                $data .= '</row>';
            }
            $mergeXml = '';
            if (!empty($sh['merges'])) {
                foreach ($sh['merges'] as $mg) { $mergeXml .= '<mergeCell ref="' . $x($mg) . '"/>'; }
                $mergeXml = '<mergeCells count="' . count($sh['merges']) . '">' . $mergeXml . '</mergeCells>';
            }
            $cols = '';
            if (!empty($sh['lebar'])) {
                $lb = $sh['lebar']; ksort($lb);
                foreach ($lb as $k => $px) { $cols .= '<col min="' . ((int) $k + 1) . '" max="' . ((int) $k + 1) . '" width="' . round(max(0, ((float) $px - 5) / 7), 2) . '" customWidth="1"/>'; }
                if ($cols !== '') { $cols = '<cols>' . $cols . '</cols>'; }
            }
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $cols . '<sheetData>' . $data . '</sheetData>' . $mergeXml . '</worksheet>');
        }
        $zip->addFromString('[Content_Types].xml', $ct . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $nfXml = '';
        foreach ($kustom as $kode => $id) { $nfXml .= '<numFmt numFmtId="' . $id . '" formatCode="' . $x($kode) . '"/>'; }
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . ($nfXml !== '' ? '<numFmts count="' . count($kustom) . '">' . $nfXml . '</numFmts>' : '') . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="' . count($xfXml) . '">' . implode('', $xfXml) . '</cellXfs></styleSheet>');
        if (!$zip->close()) { throw new \RuntimeException('Berkas Excel tidak bisa ditulis.'); }
    }

    protected static function fmtAngka($n) { return rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); }
    protected static function rp($n) { return 'Rp ' . number_format((float) $n, 0, ',', '.'); }

    /** Kirim berkas ke browser: pratinjau HTML, atau file asli bila ?unduh=1. Mengakhiri request. */
    public static function sajikan($path, $judul, $urlUnduh = '', $namaUnduh = 'RAB.xlsx') {
        while (ob_get_level() > 0) { ob_end_clean(); }
        if ($path === '' || !is_file($path)) {
            http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Berkas tidak ditemukan di server.'; exit;
        }
        if (isset($_GET['unduh']) && $_GET['unduh'] === '1') {
            $bersih = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $namaUnduh), '-');
            if ($bersih === '') { $bersih = 'RAB.xlsx'; }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $bersih . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: private, max-age=300');
            readfile($path); exit;
        }
        try {
            $html = self::htmlPratinjau(self::bacaBuku($path), $judul, $urlUnduh);
        } catch (\Throwable $e) {
            http_response_code(422); header('Content-Type: text/plain; charset=utf-8'); echo 'Berkas tidak bisa dibaca untuk pratinjau. Silakan unduh file aslinya.'; exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'");
        echo $html; exit;
    }

    /* ============================================================ */
    /*  3. PRATINJAU HTML (hanya-baca)                               */
    /* ============================================================ */

    public static function htmlPratinjau(array $buku, $judul, $urlUnduh = '') {
        $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $peranSheet = []; $barisBermasalah = [];
        try {
            $an = self::analisis($buku);
            foreach ($an['sheets'] as $si) { $peranSheet[$si['nama']] = isset($si['peran']) ? $si['peran'] : []; }
            foreach ($an['masalah'] as $m) { $barisBermasalah[$m['sheet']][(int) $m['baris']] = $m['pesan']; }
        } catch (\Throwable $eAn) { /* pratinjau tetap tampil tanpa penanda */ }
        $labelPeran = ['item' => 'Dibaca sebagai item biaya', 'keg' => 'Dibaca sebagai kegiatan', 'grup' => 'Dibaca sebagai lokasi / kelompok', 'kat' => 'Dibaca sebagai judul kategori',
                       'lanjut' => 'Lanjutan nama baris di atasnya', 'total' => 'Baris total (dipakai untuk pengecekan)', 'hdr' => 'Judul kolom', 'ringkas' => 'Ringkasan di atas tabel (Nilai Kontrak / persen kategori)', 'rekap' => 'Baris rekap (pembanding total)'];
        $tab = ''; $isi = '';
        foreach ($buku['sheets'] as $i => $sh) {
            $tab .= '<button type="button" class="tb' . ($i === 0 ? ' on' : '') . '" data-i="' . $i . '" data-n="' . $e($sh['nama']) . '">' . $e($sh['nama']) . '</button>';
            $maxKol = 0; foreach ($sh['baris'] as $c) { $maxKol = max($maxKol, max(array_keys($c)) + 1); }
            $pr = isset($peranSheet[$sh['nama']]) ? $peranSheet[$sh['nama']] : [];
            $bm = isset($barisBermasalah[$sh['nama']]) ? $barisBermasalah[$sh['nama']] : [];
            $t = '<table><tbody>';
            foreach ($sh['baris'] as $no => $cells) {
                $kelas = isset($pr[(int) $no]) ? 'r-' . $pr[(int) $no] : '';
                $tip = isset($pr[(int) $no]) && isset($labelPeran[$pr[(int) $no]]) ? $labelPeran[$pr[(int) $no]] : '';
                if (isset($bm[(int) $no])) { $kelas .= ' r-bad'; $tip = 'Perlu dicek: ' . $bm[(int) $no]; }
                $t .= '<tr data-r="' . (int) $no . '"' . ($kelas !== '' ? ' class="' . $e(trim($kelas)) . '"' : '') . '><th' . ($tip !== '' ? ' title="' . $e($tip) . '"' : '') . '>' . (int) $no . '</th>';
                for ($k = 0; $k < $maxKol; $k++) {
                    $v = isset($cells[$k]) ? $cells[$k] : '';
                    if (is_float($v)) { $cls = ' class="n"'; $v = (abs($v - round($v)) < 0.0000001) ? number_format($v, 0, ',', '.') : rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ','); }
                    else { $cls = ''; }
                    $t .= '<td' . $cls . '>' . $e($v) . '</td>';
                }
                $t .= '</tr>';
            }
            $t .= '</tbody></table>';
            $isi .= '<div class="sh' . ($i === 0 ? ' on' : '') . '" data-i="' . $i . '">' . ($sh['baris'] ? $t : '<p class="muted">Sheet kosong.</p>') . '</div>';
        }
        return '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($judul) . '</title>'
            . '<style>body{margin:0;font:14px system-ui,Segoe UI,sans-serif;background:#f4f6f9;color:#1e293b}.top{display:flex;align-items:center;gap:12px;padding:12px 18px;background:#fff;border-bottom:1px solid #e2e8f0;flex-wrap:wrap}.top b{font-size:15px}.top span{color:#64748b;font-size:12.5px}.top a{margin-left:auto;text-decoration:none;border:1px solid #1e293b;color:#1e293b;border-radius:999px;padding:6px 14px;font-weight:600;font-size:13px}'
            . '.tabs{display:flex;gap:3px;padding:12px 18px 0;flex-wrap:wrap}.tb{border:1px solid #e2e8f0;border-bottom:0;background:#f8fafc;border-radius:8px 8px 0 0;padding:6px 14px;font:inherit;font-weight:600;color:#64748b;cursor:pointer}.tb.on{background:#fff;color:#1e293b;border-color:#cbd5e1}'
            . '.wrap{margin:0 18px 24px;background:#fff;border:1px solid #cbd5e1;border-radius:0 8px 8px 8px;overflow:auto;max-height:calc(100vh - 150px)}.sh{display:none}.sh.on{display:block}table{border-collapse:collapse;font-size:13px;min-width:100%}td,th{border:1px solid #e2e8f0;padding:5px 9px;white-space:nowrap}th{background:#f1f5f9;color:#64748b;font-weight:600;font-size:12px;text-align:center;position:sticky;left:0}td.n{text-align:right;font-variant-numeric:tabular-nums}.muted{color:#64748b;padding:14px}tr.r-item th{box-shadow:inset 3px 0 0 #16a34a}tr.r-keg th,tr.r-grup th,tr.r-kat th{box-shadow:inset 3px 0 0 #2563eb}tr.r-keg td,tr.r-kat td{font-weight:600}tr.r-total th,tr.r-ringkas th,tr.r-rekap th,tr.r-lanjut th,tr.r-hdr th{box-shadow:inset 3px 0 0 #cbd5e1}tr.r-hdr td{background:#f1f5f9;font-weight:700}tr.r-bad td{background:#fef2f2}tr.r-pilih td{background:#fef9c3!important}tr.r-bad th{box-shadow:inset 3px 0 0 #dc2626}.lg{display:flex;gap:14px;flex-wrap:wrap;padding:10px 18px 0;font-size:12px;color:#64748b}.lg i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:5px;vertical-align:-1px}</style></head><body>'
            . '<div class="top"><b>' . $e($judul) . '</b><span>Pratinjau hanya-baca. Warna di tepi baris = cara sistem membaca baris itu.</span>' . ($urlUnduh !== '' ? '<a href="' . $e($urlUnduh) . '">Unduh file asli</a>' : '') . '</div>'
            . '<div class="lg"><span><i style="background:#16a34a"></i>Item biaya</span><span><i style="background:#2563eb"></i>Kegiatan / lokasi / kategori</span><span><i style="background:#cbd5e1"></i>Dilewati / pembanding</span><span><i style="background:#dc2626"></i>Perlu dicek</span></div><div class="tabs">' . $tab . '</div><div class="wrap">' . $isi . '</div>'
            . '<script>document.querySelectorAll(".tb").forEach(function(b){b.onclick=function(){var i=b.dataset.i;document.querySelectorAll(".tb").forEach(function(x){x.classList.toggle("on",x===b)});document.querySelectorAll(".sh").forEach(function(x){x.classList.toggle("on",x.dataset.i===i)})}});window.addEventListener("message",function(ev){var d=ev.data;if(!d||d.type!=="rab-lompat")return;var tb=null;document.querySelectorAll(".tb").forEach(function(x){if(x.dataset.n===d.sheet)tb=x});if(!tb)return;tb.click();document.querySelectorAll("tr.r-pilih").forEach(function(x){x.classList.remove("r-pilih")});if(!d.baris)return;var sh=document.querySelector(".sh.on");var tr=null;if(sh)sh.querySelectorAll("tr").forEach(function(x){if(x.dataset.r==d.baris)tr=x});if(!tr)return;tr.classList.add("r-pilih");var w=document.querySelector(".wrap");w.scrollTop=Math.max(0,tr.offsetTop-90)})</script></body></html>';
    }
}
