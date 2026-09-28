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
                $hasil .= str_replace($penanda, self::esc($item), $p);
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
        if ($rasio >= $boxW / $boxH) {
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

    /** Escape XML; baris baru di dalam nilai menjadi <w:br/>. */
    protected static function esc($nilai) {
        $s = htmlspecialchars((string) $nilai, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return str_replace(["\r\n", "\r", "\n"], '</w:t><w:br/><w:t xml:space="preserve">', $s);
    }
}