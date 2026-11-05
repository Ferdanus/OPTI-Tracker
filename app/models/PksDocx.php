<?php
/**
 * PksDocx
 *
 * Mengisi template PKS (.docx berplaceholder {{...}}) lalu mengembalikan isi file .docx.
 * Template: storage/templates/pks_selulosa_template.docx (bisa diedit di Word; jangan mengubah teks {{...}}).
 *
 * Jenis penanda di template:
 *   {{kunci}}               diganti nilai teks
 *   {{?kunci}}              di awal paragraf: paragraf HANYA dipertahankan kalau nilainya ada (bool true / teks tidak kosong)
 *   {{ruang_lingkup_item}}  satu paragraf berpenomoran, digandakan sesuai jumlah poin ruang lingkup
 *   {{logo_klien}}          diganti gambar logo klien (kotak yang sama dengan logo Kemenperin), atau dihapus
 *
 * Butuh ekstensi PHP zip (ZipArchive). Kompatibel PHP 7.2.
 */
class PksDocx {

    const REL_GAMBAR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
    const PARAGRAF   = '/<w:p[ >].*?<\/w:p>/s';   // "<w:p " atau "<w:p>" (bukan <w:pPr>)

    /**
     * @param string      $templatePath path template .docx
     * @param array       $data         nilai: kunci => teks; 'ruang_lingkup' => array teks; 'sampel' => bool
     * @param string|null $logoPath     path logo klien (PNG/JPG) atau null
     * @return string     isi file .docx (biner)
     */
    public static function buat($templatePath, array $data, $logoPath = null) {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Ekstensi PHP zip belum aktif. Aktifkan extension=zip di php.ini lalu restart server.');
        }
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template PKS tidak ditemukan: ' . $templatePath);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pks');
        if (!copy($templatePath, $tmp)) {
            throw new \RuntimeException('Gagal menyalin template PKS.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Template PKS bukan file .docx yang valid.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                throw new \RuntimeException('word/document.xml tidak ditemukan di template.');
            }

            $xml = self::terapkanKondisi($xml, $data);
            $xml = self::ulangParagraf($xml, '{{ruang_lingkup_item}}', isset($data['ruang_lingkup']) ? (array) $data['ruang_lingkup'] : []);
            $xml = self::pasangLogo($zip, $xml, $logoPath);
            foreach (['judul', 'alamat_perusahaan', 'alamat_notif', 'kepala_alamat'] as $kunciRich) {
                if (isset($data[$kunciRich])) {
                    $xml = self::gantiRich($xml, $kunciRich, self::barisDariTeks($data[$kunciRich]));
                }
            }

            foreach ($data as $kunci => $nilai) {
                if (is_scalar($nilai)) {
                    $xml = str_replace('{{' . $kunci . '}}', self::esc($nilai), $xml);
                }
            }

            if (preg_match('/\{\{[^}]+\}\}/', $xml, $sisa)) {
                throw new \RuntimeException('Penanda template belum terisi: ' . $sisa[0]);
            }

            $zip->addFromString('word/document.xml', $xml);
            $zip->close();
            $isi = file_get_contents($tmp);
        } catch (\Exception $e) {
            @$zip->close();
            @unlink($tmp);
            throw $e;
        }

        @unlink($tmp);
        return $isi;
    }

    /** Paragraf berpenanda {{?kunci}}: hapus paragraf kalau nilainya kosong, kalau ada buang penandanya saja. */
    protected static function terapkanKondisi($xml, array $data) {
        return preg_replace_callback(self::PARAGRAF, function ($m) use ($data) {
            $p = $m[0];
            if (!preg_match('/\{\{\?([a-z_]+)\}\}/', $p, $k)) {
                return $p;
            }
            $nilai = isset($data[$k[1]]) ? $data[$k[1]] : null;
            $ada = is_bool($nilai) ? $nilai : (trim((string) $nilai) !== '');
            return $ada ? str_replace($k[0], '', $p) : '';
        }, $xml);
    }

    /** Gandakan paragraf yang memuat $penanda, satu per item. Daftar kosong: penanda dibuang (satu baris kosong). */
    protected static function ulangParagraf($xml, $penanda, array $items) {
        return preg_replace_callback(self::PARAGRAF, function ($m) use ($penanda, $items) {
            $p = $m[0];
            if (strpos($p, $penanda) === false) {
                return $p;
            }
            // paraId/textId harus unik di dokumen; salinan tidak membawanya
            $p = preg_replace('/\s+w14:(?:paraId|textId)="[^"]*"/', '', $p);
            if (empty($items)) {
                return str_replace($penanda, '', $p);
            }
            $hasil = '';
            foreach ($items as $item) {
                if (is_array($item)) {
                    $hasil .= self::gantiRich($p, trim($penanda, '{}'), [$item]);   // satu poin = satu baris berformat
                } else {
                    $hasil .= str_replace($penanda, self::esc($item), $p);
                }
            }
            return $hasil;
        }, $xml);
    }

    /** Ganti penanda {{logo_klien}} dengan gambar mengambang berukuran sama dengan logo Kemenperin (anchor pertama di template). */
    protected static function pasangLogo(\ZipArchive $zip, $xml, $logoPath) {
        $penanda = '<w:r><w:t>{{logo_klien}}</w:t></w:r>';
        if (strpos($xml, $penanda) === false) {
            throw new \RuntimeException('Penanda {{logo_klien}} tidak ada di template.');
        }
        if (!$logoPath || !is_file($logoPath)) {
            return str_replace($penanda, '', $xml);
        }

        $info = @getimagesize($logoPath);
        if (!$info || ($info[2] !== IMAGETYPE_PNG && $info[2] !== IMAGETYPE_JPEG) || $info[0] < 1 || $info[1] < 1) {
            return str_replace($penanda, '', $xml);
        }
        $ext  = ($info[2] === IMAGETYPE_PNG) ? 'png' : 'jpeg';
        $nama = 'logo_klien.' . $ext;
        $rid  = 'rIdLogoKlien';

        // Kotak logo = ukuran dan posisi vertikal logo Kemenperin di template
        $boxW = 2376805; $boxH = 821690; $posY = -629285;
        if (preg_match('/<wp:anchor.*?<wp:positionV[^>]*><wp:posOffset>(-?\d+)<\/wp:posOffset>.*?<wp:extent cx="(\d+)" cy="(\d+)"/s', $xml, $e)) {
            $posY = (int) $e[1]; $boxW = (int) $e[2]; $boxH = (int) $e[3];
        }

        $rasio = $info[0] / $info[1];
        if (abs($rasio - $boxW / $boxH) < 0.02) {
            // Logo sudah berrasio kotak (hasil konversi PNG): pakai ukuran kotak persis, 6,6 x 2,28 cm.
            $cx = $boxW;
            $cy = $boxH;
        } elseif ($rasio >= $boxW / $boxH) {
            $cx = $boxW;
            $cy = (int) round($boxW / $rasio);
        } else {
            $cy = $boxH;
            $cx = (int) round($boxH * $rasio);
        }
        $offsetY = $posY + (int) round(($boxH - $cy) / 2);

        $gambar = '<w:r><w:drawing><wp:anchor distT="0" distB="0" distL="114300" distR="114300" simplePos="0" relativeHeight="251660289" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">'
            . '<wp:simplePos x="0" y="0"/><wp:positionH relativeFrom="column"><wp:posOffset>0</wp:posOffset></wp:positionH>'
            . '<wp:positionV relativeFrom="paragraph"><wp:posOffset>' . $offsetY . '</wp:posOffset></wp:positionV>'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:effectExtent l="0" t="0" r="0" b="0"/><wp:wrapNone/>'
            . '<wp:docPr id="9001" name="Logo Klien"/>'
            . '<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="9001" name="' . $nama . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rid . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:anchor></w:drawing></w:r>';

        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        $ct   = $zip->getFromName('[Content_Types].xml');
        if ($rels === false || $ct === false) {
            throw new \RuntimeException('Struktur .docx template tidak lengkap (rels atau content types).');
        }
        $rels = str_replace('</Relationships>', '<Relationship Id="' . $rid . '" Type="' . self::REL_GAMBAR . '" Target="media/' . $nama . '"/></Relationships>', $rels);
        if (stripos($ct, 'Extension="' . $ext . '"') === false) {
            $ct = preg_replace('/(<Types[^>]*>)/', '$1<Default Extension="' . $ext . '" ContentType="image/' . $ext . '"/>', $ct, 1);
        }

        $zip->addFromString('word/media/' . $nama, file_get_contents($logoPath));
        $zip->addFromString('word/_rels/document.xml.rels', $rels);
        $zip->addFromString('[Content_Types].xml', $ct);

        return str_replace($penanda, $gambar, $xml);
    }

    /* ============================================================
     * TEKS BERFORMAT (editor kotak teks: tebal, miring, garis bawah)
     * Nilai tersimpan = teks biasa, atau HTML sederhana (<div> per baris, <b> <i> <u>).
     * ============================================================ */

    /** Apakah nilai memuat markup sederhana dari editor. */
    public static function adaMarkup($v) {
        return (bool) preg_match('/<\/?(?:b|i|u|div|p|br|strong|em|span|font|ul|ol|li)\b/i', (string) $v);
    }

    /** Teks polos satu baris (daftar run). */
    public static function teksBaris(array $baris) {
        $t = '';
        foreach ($baris as $r) { $t .= $r['t']; }
        return trim($t);
    }

    /** Nilai (teks biasa atau HTML editor) -> daftar baris; tiap baris = daftar run ['t','b','i','u']. */
    public static function barisDariTeks($v) {
        $v = (string) $v;
        $lines = [];
        if (!self::adaMarkup($v)) {
            foreach (preg_split('/\R/u', $v) as $b) {
                $lines[] = [['t' => $b, 'b' => false, 'i' => false, 'u' => false]];
            }
            return self::rapikanBaris($lines);
        }
        if (!class_exists('DOMDocument')) {
            $v = str_ireplace(['</div>', '</p>', '<br>', '<br/>', '<br />'], "\n", $v);
            foreach (preg_split('/\R/u', html_entity_decode(strip_tags($v), ENT_QUOTES, 'UTF-8')) as $b) {
                $lines[] = [['t' => $b, 'b' => false, 'i' => false, 'u' => false]];
            }
            return self::rapikanBaris($lines);
        }

        $pakaiErr = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $v . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($pakaiErr);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) { return []; }

        $cur = null;
        $ambil = function () use (&$lines, &$cur) {
            if ($cur === null) { $lines[] = []; $cur = count($lines) - 1; }
            return $cur;
        };
        $walk = function ($node, $b, $i, $u) use (&$walk, &$lines, &$cur, $ambil) {
            foreach ($node->childNodes as $c) {
                if ($c->nodeType === XML_TEXT_NODE) {
                    $t = preg_replace('/[\r\n\t]+/', ' ', str_replace("\xC2\xA0", ' ', (string) $c->nodeValue));
                    if ($t === '') { continue; }
                    $k = $ambil();
                    $lines[$k][] = ['t' => $t, 'b' => $b, 'i' => $i, 'u' => $u];
                    continue;
                }
                if ($c->nodeType !== XML_ELEMENT_NODE) { continue; }
                $tag = strtolower($c->nodeName);
                if ($tag === 'script' || $tag === 'style') { continue; }
                if ($tag === 'br') { $ambil(); $cur = null; continue; }
                if (in_array($tag, ['div', 'p', 'li', 'ul', 'ol'], true)) {
                    $cur = null;
                    $walk($c, $b, $i, $u);
                    $cur = null;
                    continue;
                }
                $nb = $b; $ni = $i; $nu = $u;
                if ($tag === 'b' || $tag === 'strong') { $nb = true; }
                if ($tag === 'i' || $tag === 'em') { $ni = true; }
                if ($tag === 'u') { $nu = true; }
                if ($c instanceof \DOMElement && $c->hasAttribute('style')) {
                    $st = strtolower((string) $c->getAttribute('style'));
                    if (preg_match('/font-weight\s*:\s*(bold|[6-9]00)/', $st)) { $nb = true; }
                    if (preg_match('/font-style\s*:\s*italic/', $st)) { $ni = true; }
                    if (preg_match('/text-decoration[^;]*underline/', $st)) { $nu = true; }
                }
                $walk($c, $nb, $ni, $nu);
            }
        };
        $walk($body, false, false, false);
        return self::rapikanBaris($lines);
    }

    /** Gabungkan run bergaya sama, rapikan spasi tepi baris, buang baris kosong di ujung. */
    protected static function rapikanBaris(array $lines) {
        $hasil = [];
        foreach ($lines as $runs) {
            $g = [];
            foreach ($runs as $r) {
                if ($r['t'] === '') { continue; }
                $n = count($g);
                if ($n && $g[$n - 1]['b'] === $r['b'] && $g[$n - 1]['i'] === $r['i'] && $g[$n - 1]['u'] === $r['u']) {
                    $g[$n - 1]['t'] .= $r['t'];
                } else {
                    $g[] = $r;
                }
            }
            if ($g) {
                $g[0]['t'] = ltrim($g[0]['t']);
                $n = count($g);
                $g[$n - 1]['t'] = rtrim($g[$n - 1]['t']);
                $g = array_values(array_filter($g, function ($r) { return $r['t'] !== ''; }));
            }
            $hasil[] = $g;
        }
        while ($hasil && self::teksBaris($hasil[0]) === '') { array_shift($hasil); }
        while ($hasil && self::teksBaris($hasil[count($hasil) - 1]) === '') { array_pop($hasil); }
        return $hasil;
    }

    /** Bentuk simpan baku: teks biasa kalau tanpa format, kalau berformat HTML sederhana. '' kalau kosong. */
    public static function kanonik($v, $maks) {
        $baris = self::barisDariTeks($v);
        if (!$baris) { return ''; }
        $adaGaya = false;
        $plain = [];
        foreach ($baris as $b) {
            $plain[] = self::teksBaris($b);
            foreach ($b as $r) { if ($r['b'] || $r['i'] || $r['u']) { $adaGaya = true; } }
        }
        $teks = implode("\n", $plain);
        if (!$adaGaya && !self::adaMarkup($teks)) {
            return mb_substr($teks, 0, $maks, 'UTF-8');
        }
        $html = '';
        foreach ($baris as $b) {
            $isi = '';
            foreach ($b as $r) {
                $x = htmlspecialchars($r['t'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($r['u']) { $x = '<u>' . $x . '</u>'; }
                if ($r['i']) { $x = '<i>' . $x . '</i>'; }
                if ($r['b']) { $x = '<b>' . $x . '</b>'; }
                $isi .= $x;
            }
            $html .= '<div>' . ($isi !== '' ? $isi : '<br>') . '</div>';
        }
        if (mb_strlen($html, 'UTF-8') > $maks) {
            return mb_substr($teks, 0, $maks, 'UTF-8');   // terlalu panjang: format dibuang supaya muat di kolom
        }
        return $html;
    }

    /** Sisipkan elemen rPr (b/i/u) sesuai urutan skema OOXML. */
    protected static function rprGaya($rPr, $b, $i, $u) {
        if (!$b && !$i && !$u) { return $rPr; }
        if ($rPr === '') { $rPr = '<w:rPr></w:rPr>'; }
        $sisip = function ($rPr, $tag, array $setelah, $cek) {
            if (preg_match('#<' . $cek . '(?:\s[^>]*)?/?>#', $rPr)) { return $rPr; }
            foreach ($setelah as $pola) {
                if (preg_match_all('#' . $pola . '#', $rPr, $mm, PREG_OFFSET_CAPTURE) && $mm[0]) {
                    $akhir = end($mm[0]);
                    $pos = $akhir[1] + strlen($akhir[0]);
                    return substr($rPr, 0, $pos) . $tag . substr($rPr, $pos);
                }
            }
            return preg_replace('#<w:rPr>#', '<w:rPr>' . $tag, $rPr, 1);
        };
        if ($b) {
            $rPr = $sisip($rPr, '<w:b/>', ['<w:rFonts[^>]*/>', '<w:rStyle[^>]*/>'], 'w:b');
            $rPr = $sisip($rPr, '<w:bCs/>', ['<w:b/>'], 'w:bCs');
        }
        if ($i) {
            $rPr = $sisip($rPr, '<w:i/>', ['<w:bCs/>', '<w:b/>', '<w:rFonts[^>]*/>', '<w:rStyle[^>]*/>'], 'w:i');
            $rPr = $sisip($rPr, '<w:iCs/>', ['<w:i/>'], 'w:iCs');
        }
        if ($u && !preg_match('#<w:u(?:\s[^>]*)?/?>#', $rPr)) {
            if (preg_match('#<w:(?:effect|bdr|shd|fitText|vertAlign|rtl|cs|em|lang|eastAsianLayout|specVanish|oMath)\b|<w14:#', $rPr, $mm, PREG_OFFSET_CAPTURE)) {
                $pos = $mm[0][1];
                $rPr = substr($rPr, 0, $pos) . '<w:u w:val="single"/>' . substr($rPr, $pos);
            } else {
                $rPr = str_replace('</w:rPr>', '<w:u w:val="single"/></w:rPr>', $rPr);
            }
        }
        return $rPr;
    }

    protected static function escXml($t) {
        return htmlspecialchars((string) $t, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Daftar baris berformat -> deretan run Word (antar baris: <w:br/>). */
    protected static function runDariBaris($rPr, array $baris) {
        $out = '';
        foreach ($baris as $n => $b) {
            if ($n > 0) { $out .= '<w:r>' . $rPr . '<w:br/></w:r>'; }
            foreach ($b as $r) {
                $out .= '<w:r>' . self::rprGaya($rPr, $r['b'], $r['i'], $r['u']) . '<w:t xml:space="preserve">' . self::escXml($r['t']) . '</w:t></w:r>';
            }
        }
        return $out;
    }

    /** Ganti run yang memuat {{kunci}} dengan run berformat (teks di sekitar penanda tetap dipertahankan). */
    protected static function gantiRich($xml, $kunci, array $baris) {
        $penanda = '{{' . $kunci . '}}';
        if (strpos($xml, $penanda) === false) { return $xml; }
        return preg_replace_callback(
            '#<w:r(?:\s[^>]*)?>((?:<w:rPr>(?:(?!</w:rPr>).)*</w:rPr>)?)<w:t(?:\s[^>]*)?>([^<]*)</w:t></w:r>#s',
            function ($m) use ($penanda, $baris) {
                if (strpos($m[2], $penanda) === false) { return $m[0]; }
                $rPr = $m[1];
                $pos = strpos($m[2], $penanda);
                $pre = substr($m[2], 0, $pos);
                $post = substr($m[2], $pos + strlen($penanda));
                $out = '';
                if ($pre !== '') { $out .= '<w:r>' . $rPr . '<w:t xml:space="preserve">' . $pre . '</w:t></w:r>'; }
                $out .= self::runDariBaris($rPr, $baris);
                if ($post !== '') { $out .= '<w:r>' . $rPr . '<w:t xml:space="preserve">' . $post . '</w:t></w:r>'; }
                return $out;
            },
            $xml
        );
    }

    /** Escape XML; baris baru di dalam nilai menjadi <w:br/>. */
    protected static function esc($nilai) {
        $s = htmlspecialchars((string) $nilai, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return str_replace(["\r\n", "\r", "\n"], '</w:t><w:br/><w:t xml:space="preserve">', $s);
    }
}