<?php
/**
 * BastDocx
 *
 * Mengisi template BAST (.docx berplaceholder {{kunci}}) lalu mengembalikan isi file .docx.
 * Template: storage/templates/bast_template.docx (boleh diedit di Word; jangan mengubah teks {{...}}).
 * Penanda: {{kunci}} diganti nilai teks (baris baru di nilai jadi baris baru di Word).
 *
 * Butuh ekstensi PHP zip (ZipArchive). Kompatibel PHP 7.2.
 */
class BastDocx {

    /**
     * @param string $templatePath path template .docx
     * @param array  $data         kunci => teks
     * @return string isi file .docx (biner)
     */
    public static function buat($templatePath, array $data) {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Ekstensi PHP zip belum aktif. Aktifkan extension=zip di php.ini lalu restart server.');
        }
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template BAST tidak ditemukan: ' . $templatePath);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bast');
        if (!copy($templatePath, $tmp)) {
            throw new \RuntimeException('Gagal menyalin template BAST.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Template BAST bukan file .docx yang valid.');
        }
        try {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                throw new \RuntimeException('word/document.xml tidak ditemukan di template.');
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

    /** Teks -> isi run XML aman; baris baru jadi <w:br/>. */
    protected static function esc($nilai) {
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $nilai);
        $t = str_replace(["\r\n", "\r"], "\n", $t);
        $baris = explode("\n", $t);
        $keluar = [];
        foreach ($baris as $b) {
            $keluar[] = htmlspecialchars($b, ENT_XML1 | ENT_NOQUOTES, 'UTF-8');
        }
        return implode('</w:t><w:br/><w:t xml:space="preserve">', $keluar);
    }
}
