<?php
/**
 * Template Word Surat Penawaran Resmi BBSPJIS
 * Sesuai Spesifikasi Layout Standar:
 * - Kertas A4 Portrait, Margin Kiri 3.0cm / Kanan 2.0cm, Margin Atas 0.9cm / Bawah 1.5cm
 * - Area tulis 16.0 cm
 * - Font isi Arial 11 pt, spasi tunggal, Justify
 * - Kop surat Tahoma (Baris 1: 12pt, Baris 2: 13-14pt tebal, Baris 3-5: 7pt)
 * - Header otomatis nomor halaman -n- pada halaman lanjutan
 * - Tidak ada tebal di isi (tebal hanya di baris 2 kop), kata asing & judul miring
 */

if (!function_exists('wordItalicizeForeignWords')) {
    function wordItalicizeForeignWords($str) {
        $words = [
            'email', 'e-mail', 'virtual account', 'invoice', 'WhatsApp', 
            'hunting', 'fax', 'handsheet', 'beating', 'stock', 'brightness'
        ];
        foreach ($words as $w) {
            $str = preg_replace('/\b(' . preg_quote($w, '/') . ')\b/i', '<em>$1</em>', $str);
        }
        return $str;
    }
}
?>
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($noSurat) ?></title>
<!--[if gte mso 9]>
<xml>
<w:WordDocument>
<w:View>Print</w:View>
<w:Zoom>100</w:Zoom>
<w:DoNotOptimizeForBrowser/>
</w:WordDocument>
</xml>
<![endif]-->
<style>
@page Section1 {
    size: 21.0cm 29.7cm;
    margin: 0.9cm 2.0cm 1.5cm 3.0cm;
    mso-header-margin: 0.8cm;
    mso-footer-margin: 0.8cm;
    mso-title-page: yes;
    mso-first-header: h1;
    mso-header: h1;
    mso-paper-source: 0;
}
div.Section1 { 
    page: Section1; 
    font-family: Arial, sans-serif; 
    font-size: 11pt; 
    line-height: normal; 
    color: #000000; 
}

@page Section2 {
    size: 21.0cm 29.7cm;
    margin: 0.9cm 2.0cm 1.5cm 3.0cm;
    mso-header-margin: 0.8cm;
    mso-footer-margin: 0.8cm;
    mso-paper-source: 0;
}
div.Section2 { 
    page: Section2; 
    font-family: Arial, sans-serif; 
    font-size: 11pt; 
    line-height: normal; 
    color: #000000; 
}

@page Section3 {
    size: 21.0cm 29.7cm;
    margin: 1.0cm 1.0cm 1.0cm 1.0cm;
    mso-header-margin: 0cm;
    mso-footer-margin: 0cm;
    mso-paper-source: 0;
}
div.Section3 { 
    page: Section3; 
    font-family: Arial, sans-serif; 
    font-size: 11pt; 
    line-height: normal; 
    color: #000000; 
}

table.MsoNormalTable {
    mso-style-name: "Table Normal";
    mso-tstyle-rowband-size: 0;
    mso-tstyle-colband-size: 0;
    mso-padding-alt: 0cm 0cm 0cm 0cm;
    mso-para-margin: 0cm;
    mso-para-margin-bottom: .0001pt;
    line-height: normal;
}
p.MsoNormal, div.MsoNormal {
    margin: 0;
    margin-bottom: .0001pt;
    font-size: 11.0pt;
    font-family: Arial, sans-serif;
    line-height: normal;
}
p.MsoHeader, div.MsoHeader, span.MsoHeader {
    margin: 0;
    text-align: center;
    font-family: Arial, sans-serif;
    font-size: 11pt;
    line-height: normal;
    color: #000000;
}
body { 
    font-family: Arial, sans-serif; 
    font-size: 11pt; 
    line-height: normal; 
    color: #000000; 
    margin: 0; 
    padding: 0; 
}
table { 
    border-collapse: collapse; 
    mso-table-lspace: 0pt; 
    mso-table-rspace: 0pt; 
}
p { 
    margin: 0; 
    margin-bottom: .0001pt;
    font-size: 11pt; 
    line-height: normal; 
}
</style>
</head>
<body>

<div class="Section1">

    <!-- Header Kosong untuk Section 1 (Halaman Pertama Tanpa Nomor Halaman) -->
    <div style='mso-element:header' id=h1>
        <p class="MsoHeader" style="margin: 0; line-height: 0pt; font-size: 1pt;">&nbsp;</p>
    </div>

    <!-- ======================================================== -->
    <!-- 2. HEADER KOP SURAT (Halaman Pertama)                    -->
    <!-- Font Tahoma, Semua Rata Tengah, Garis Tebal Selebar Tulis-->
    <!-- Lebar Penuh 16.0 cm (width="100%")                       -->
    <!-- ======================================================== -->
    <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; mso-table-layout-alt: fixed; table-layout: fixed; margin: 0; padding: 0;">
        <tr>
            <?php if (!empty($logoBase64)): ?>
            <td width="27%" style="width: 4.33cm; width: 27.0%; text-align: left; vertical-align: middle; padding: 0 0.2cm 6pt 0; border: none; border-bottom: 2.25pt solid #000000; mso-border-bottom-alt: solid black 2.25pt;">
                <img src="data:image/png;base64,<?= $logoBase64 ?>" width="155" height="49" style="width: 4.11cm; height: 1.29cm; display: block;" alt="Logo Kemenperin">
            </td>
            <?php endif; ?>
            <td width="<?= !empty($logoBase64) ? '73%' : '100%' ?>" style="width: <?= !empty($logoBase64) ? '11.67cm' : '16.0cm' ?>; width: <?= !empty($logoBase64) ? '73.0%' : '100.0%' ?>; text-align: center; vertical-align: middle; padding: 0 0 6pt 0; border: none; border-bottom: 2.25pt solid #000000; mso-border-bottom-alt: solid black 2.25pt; font-family: Tahoma, sans-serif;">
                <p class="MsoNormal" style="font-family: Tahoma, sans-serif; font-size: 12pt; font-weight: normal; margin: 0; line-height: 1.15; text-align: center;">BADAN STANDARDISASI DAN KEBIJAKAN JASA INDUSTRI</p>
                <p class="MsoNormal" style="font-family: Tahoma, sans-serif; font-size: 14pt; font-weight: bold; margin: 0; line-height: 1.15; text-align: center;">BALAI BESAR STANDARISASI DAN<br>PELAYANAN JASA INDUSTRI SELULOSA</p>
                <p class="MsoNormal" style="font-family: Tahoma, sans-serif; font-size: 7pt; font-weight: normal; margin: 2pt 0 0 0; line-height: 1.15; text-align: center;">Jl. Raya Dayeuhkolot No. 132 Bandung 40258</p>
                <p class="MsoNormal" style="font-family: Tahoma, sans-serif; font-size: 7pt; font-weight: normal; margin: 0; line-height: 1.15; text-align: center;">Telp. (022) 5202980 (<i>Hunting</i>) &amp; 5202871 <i>Fax</i>. (022) 5202871 <i>E-mail</i> : <?= ($order['jenis_layanan_opti'] ?? '') === 'lingkungan' ? 'bbpk@bbpk.go.id' : 'bbs@kemenperin.go.id' ?></p>
            </td>
        </tr>
    </table>

    <!-- Spasi Enter 1x Sebelum Nomor dan Tanggal -->
    <p class="MsoNormal" style="margin-top: 10pt; margin-bottom: 0;">&nbsp;</p>

    <!-- ======================================================== -->
    <!-- 3.a. BLOK IDENTITAS SURAT                                 -->
    <!-- Tabel 4 Kolom Tanpa Border, Lebar Total 16.0 cm (100%)    -->
    <!-- Kolom: 2.00cm | 0.75cm | 7.50cm | 5.74cm                  -->
    <!-- ======================================================== -->
    <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; mso-table-layout-alt: fixed; table-layout: fixed; margin: 0; padding: 0;">
        <tr>
            <td width="12.5%" style="width: 2.00cm; width: 12.5%; vertical-align: top; padding: 0; border: none; text-align: left;"><p class="MsoNormal">Nomor</p></td>
            <td width="4.7%" style="width: 0.75cm; width: 4.7%; vertical-align: top; padding: 0; border: none; text-align: center;"><p class="MsoNormal">:</p></td>
            <td width="46.9%" style="width: 7.50cm; width: 46.9%; vertical-align: top; padding: 0; border: none; text-align: left;"><p class="MsoNormal"><?= htmlspecialchars($noSurat) ?></p></td>
            <td width="35.9%" style="width: 5.74cm; width: 35.9%; vertical-align: top; padding: 0; border: none; text-align: right;"><p class="MsoNormal" style="text-align: right;">Bandung, <?= htmlspecialchars($tglFormatted) ?></p></td>
        </tr>
        <tr>
            <td style="width: 2.00cm; width: 12.5%; vertical-align: top; padding: 0; border: none; text-align: left;"><p class="MsoNormal">Lampiran</p></td>
            <td style="width: 0.75cm; width: 4.7%; vertical-align: top; padding: 0; border: none; text-align: center;"><p class="MsoNormal">:</p></td>
            <td colspan="2" style="width: 13.24cm; width: 82.8%; vertical-align: top; padding: 0; border: none; text-align: left;"><p class="MsoNormal"><?= htmlspecialchars($lampiranTeks) ?></p></td>
        </tr>
        <tr>
            <td style="width: 2.00cm; width: 12.5%; vertical-align: top; padding: 0; border: none; text-align: left;"><p class="MsoNormal">Hal</p></td>
            <td style="width: 0.75cm; width: 4.7%; vertical-align: top; padding: 0; border: none; text-align: center;"><p class="MsoNormal">:</p></td>
            <td colspan="2" style="width: 13.24cm; width: 82.8%; vertical-align: top; padding: 0; border: none; text-align: left;"><p class="MsoNormal"><?= htmlspecialchars($halSurat) ?></p></td>
        </tr>
    </table>

    <!-- ======================================================== -->
    <!-- 3.b. BLOK TUJUAN                                          -->
    <!-- Tiga Paragraf Rata Kiri Tanpa Jarak Menempel di Bawah Hal -->
    <!-- ======================================================== -->
    <?php
    $tujuanLines = [];
    $tujuanLines[] = 'Yth. ' . $ythTitle;
    if (!empty($alamat)) {
        $aLines = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $alamat))));
        foreach ($aLines as $al) {
            $tujuanLines[] = $al;
        }
    } else {
        $tujuanLines[] = 'di Tempat';
    }
    if (!empty($picSurat) && $picSurat !== '-' && stripos($ythTitle, $picSurat) === false) {
        $tujuanLines[] = 'u.p. ' . $picSurat;
    }
    ?>
    <div style="margin-top: 0; font-family: Arial, sans-serif; font-size: 11pt; line-height: normal; text-align: left;">
        <?php foreach ($tujuanLines as $tl): ?>
            <p class="MsoNormal" style="text-align: left;"><?= htmlspecialchars($tl) ?></p>
        <?php endforeach; ?>
    </div>

    <!-- ======================================================== -->
    <!-- 3.c. PARAGRAF PEMBUKA                                     -->
    <!-- Align Left, Indentasi 1.27 cm, Berjarak 12 pt dari Blok B  -->
    <!-- Judul Kegiatan & Kata Asing Dicetak Miring, Tanpa Tebal   -->
    <!-- ======================================================== -->
    <p class="MsoNormal" style="margin-top: 12pt; margin-bottom: 0; text-align: left; text-indent: 1.27cm;">
        Menanggapi permintaan Saudara <?= wordItalicizeForeignWords(htmlspecialchars(strip_tags($mediaPhrase))) ?> perihal "<em><?= htmlspecialchars(strip_tags($judulKegiatan)) ?></em>", dengan ini kami informasikan rincian kegiatan dan penawaran biaya layanan sebagai berikut:
    </p>

    <!-- ======================================================== -->
    <!-- 3.d. DAFTAR BERTINGKAT                                    -->
    <!-- Level 1: 1., Rata Kiri Margin (Hanging 0.5 cm)            -->
    <!-- Level 2: 1), Menjorok ke Dalam (Indent 1.0 cm, Hanging 0.5 cm)-->
    <!-- Menggunakan Borderless Table untuk Presisi Mutlak di Word -->
    <!-- ======================================================== -->
    <?php
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawNaskah));
    $level1Count = 0;
    $level2Count = 0;
    $inLevel2 = false;

    foreach ($lines as $line):
        $lineTrim = trim($line);
        if ($lineTrim === '') continue;

        if (preg_match('/^(\d+\)|[a-z]\)|\-|\*|\•)\s*(.*)/i', $lineTrim, $mSub)):
            $inLevel2 = true;
            $level2Count++;
            $text = wordItalicizeForeignWords(htmlspecialchars($mSub[2]));
        ?>
            <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; margin-top: 1pt; margin-bottom: 0;">
                <tr>
                    <td width="20" style="width: 0.5cm; vertical-align: top; padding: 0; border: none;">&nbsp;</td>
                    <td width="20" style="width: 0.5cm; vertical-align: top; padding: 0; border: none;"><p class="MsoNormal"><?= $level2Count ?>)</p></td>
                    <td style="vertical-align: top; padding: 0; border: none;"><p class="MsoNormal" style="text-align: left;"><?= $text ?></p></td>
                </tr>
            </table>
        <?php elseif (preg_match('/^(\d+)\.\s*(.*)/', $lineTrim, $mPoint)):
            $level1Count++;
            $inLevel2 = false;
            $level2Count = 0;
            $text = wordItalicizeForeignWords(htmlspecialchars($mPoint[2]));
        ?>
            <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; margin-top: 1pt; margin-bottom: 0;">
                <tr>
                    <td width="20" style="width: 0.5cm; vertical-align: top; padding: 0; border: none;"><p class="MsoNormal"><?= $level1Count ?>.</p></td>
                    <td style="vertical-align: top; padding: 0; border: none;"><p class="MsoNormal" style="text-align: left;"><?= $text ?></p></td>
                </tr>
            </table>
        <?php else:
            $text = wordItalicizeForeignWords(htmlspecialchars($lineTrim));
            if ($inLevel2): ?>
                <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; margin-top: 1pt; margin-bottom: 0;">
                    <tr>
                        <td width="40" style="width: 1.0cm; vertical-align: top; padding: 0; border: none;">&nbsp;</td>
                        <td style="vertical-align: top; padding: 0; border: none;"><p class="MsoNormal" style="text-align: left;"><?= $text ?></p></td>
                    </tr>
                </table>
            <?php else: ?>
                <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; margin-top: 1pt; margin-bottom: 0;">
                    <tr>
                        <td width="20" style="width: 0.5cm; vertical-align: top; padding: 0; border: none;">&nbsp;</td>
                        <td style="vertical-align: top; padding: 0; border: none;"><p class="MsoNormal" style="text-align: left;"><?= $text ?></p></td>
                    </tr>
                </table>
            <?php endif;
        endif;
    endforeach; ?>

    <!-- ======================================================== -->
    <!-- 3.e. PARAGRAF PENGANTAR DAFTAR KEDUA                      -->
    <!-- Align Left, Indentasi 1.27 cm, Spasi Sebelum 6 pt         -->
    <!-- ======================================================== -->
    <p class="MsoNormal" style="margin-top: 6pt; margin-bottom: 0; text-align: left; text-indent: 1.27cm;">
        Disamping itu perlu kami sampaikan pula ketentuan sebagai berikut :
    </p>

    <!-- ======================================================== -->
    <!-- 3.f. DAFTAR KEDUA                                         -->
    <!-- Rata Kiri Tanpa Hanging, Wrap ke Margin Kiri Sesuai Gambar -->
    <!-- ======================================================== -->
    <?php
    $ketentuan = [
        'Persetujuan terhadap biaya pengujian mohon disampaikan secara tertulis melalui email.',
        'Pengujian dilaksanakan setelah pembayaran biaya dilakukan dan sampel kami terima.',
        'Pembayaran biaya pengujian dilakukan dengan transfer melalui <i>virtual account bank</i>.',
        'Untuk informasi lebih lanjut dapat menghubungi Bagian Layanan nomor: 0813 8686 6808/ 0856 5908 8926.',
        'Dilarang memberi gratifikasi dalam bentuk apapun atas layanan jasa yang kami berikan, jika terdapat pemberian dan penerimaan gratifikasi mohon dapat dilaporkan ke : http://bbs.kemenperin.go.id/kontak-kami/pengaduan-gratifikasi.'
    ];
    ?>
    <?php foreach ($ketentuan as $kIdx => $kText): ?>
        <p class="MsoNormal" style="margin-top: 0; margin-bottom: 0; text-align: left; text-indent: 0; line-height: 1.15;"><?= ($kIdx + 1) ?>. <?= $kText ?></p>
    <?php endforeach; ?>

    <!-- ======================================================== -->
    <!-- 3.g. PARAGRAF PENUTUP                                     -->
    <!-- Align Left, Indentasi 1.27 cm, Spasi Sebelum 6 pt         -->
    <!-- ======================================================== -->
    <p class="MsoNormal" style="margin-top: 6pt; margin-bottom: 0; text-align: left; text-indent: 1.27cm; line-height: 1.15;">
        Kami menunggu konfirmasi lebih lanjut. Atas perhatian dan kerja sama yang baik, kami sampaikan terima kasih.
    </p>

    <!-- ======================================================== -->
    <!-- 3.h. BLOK TANDA TANGAN (Halaman Surat)                    -->
    <!-- Kolom Kanan Rata Tengah, Space 3 Enter, Space Before & After -->
    <!-- ======================================================== -->
    <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; mso-table-layout-alt: fixed; table-layout: fixed; margin-top: 12pt; margin-bottom: 18pt;">
        <tr>
            <td width="60%" style="width: 60.0%; width: 9.6cm; border: none; padding: 0;">&nbsp;</td>
            <td width="40%" style="width: 40.0%; width: 6.4cm; border: none; padding: 0; text-align: center; vertical-align: top;">
                <p class="MsoNormal" style="text-align: center; margin-top: 12.0pt; margin-bottom: 6.0pt; mso-para-margin-top: 12.0pt; mso-para-margin-bottom: 6.0pt; line-height: 1.15;"><?= htmlspecialchars($jabatanPejabat) ?>,</p>
                <p class="MsoNormal" style="text-align: center; margin: 0; line-height: 1.15;">&nbsp;</p>
                <p class="MsoNormal" style="text-align: center; margin: 0; line-height: 1.15;">&nbsp;</p>
                <p class="MsoNormal" style="text-align: center; margin: 0; line-height: 1.15;">&nbsp;</p>
                <p class="MsoNormal" style="text-align: center; margin: 0; line-height: 1.15;">&nbsp;</p>
                <p class="MsoNormal" style="text-align: center; margin-top: 6.0pt; margin-bottom: 12.0pt; mso-para-margin-top: 6.0pt; mso-para-margin-bottom: 12.0pt; line-height: 1.15;"><?= htmlspecialchars($pejabatNama) ?></p>
            </td>
        </tr>
    </table>

    <br clear="all" style="page-break-before: always; mso-break-type: section-break;" />
</div><!-- end Section1 -->

<div class="Section2">

    <!-- Penomoran Halaman 2: -2- Rata Tengah Arial 11 pt (Stay di Halaman 2 Sesuai Format Asli) -->
    <p class="MsoNormal" style="text-align: center; margin-top: 0; margin-bottom: 12pt;">-2-</p>

    <!-- ======================================================== -->
    <!-- 4.a. BLOK REFERENSI (Halaman Lampiran)                    -->
    <!-- Tabel 4 Kolom: 55% / 12% / 4% / 29% Menempel ke Kanan     -->
    <!-- Pola: Label, Titik Dua, Nilai                             -->
    <!-- ======================================================== -->
    <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; mso-table-layout-alt: fixed; table-layout: fixed; margin-bottom: 0;">
        <tr>
            <td width="55%" style="width: 55%; border: none; padding: 0;">&nbsp;</td>
            <td width="12%" style="width: 12%; border: none; padding: 0; vertical-align: top; text-align: left;"><p class="MsoNormal">Lampiran</p></td>
            <td width="4%" style="width: 4%; border: none; padding: 0; vertical-align: top; text-align: center;"><p class="MsoNormal">:</p></td>
            <td width="29%" style="width: 29%; border: none; padding: 0; vertical-align: top; text-align: left;"><p class="MsoNormal">I Surat</p></td>
        </tr>
        <tr>
            <td style="border: none; padding: 0;">&nbsp;</td>
            <td style="border: none; padding: 0; vertical-align: top; text-align: left;"><p class="MsoNormal">Nomor</p></td>
            <td style="border: none; padding: 0; vertical-align: top; text-align: center;"><p class="MsoNormal">:</p></td>
            <td style="border: none; padding: 0; vertical-align: top; text-align: left;"><p class="MsoNormal"><?= htmlspecialchars($noSurat) ?></p></td>
        </tr>
        <tr>
            <td style="border: none; padding: 0;">&nbsp;</td>
            <td style="border: none; padding: 0; vertical-align: top; text-align: left;"><p class="MsoNormal">Tanggal</p></td>
            <td style="border: none; padding: 0; vertical-align: top; text-align: center;"><p class="MsoNormal">:</p></td>
            <td style="border: none; padding: 0; vertical-align: top; text-align: left;"><p class="MsoNormal"><?= htmlspecialchars($tglFormatted) ?></p></td>
        </tr>
    </table>

    <!-- ======================================================== -->
    <!-- 4.b. JUDUL                                                -->
    <!-- Rata Tengah, Arial 11 pt, TIDAK TEBAL, Jarak 1-2 Baris     -->
    <!-- ======================================================== -->
    <p class="MsoNormal" style="text-align: center; font-weight: normal; margin-top: 18pt; margin-bottom: 18pt;">
        Lembar Persetujuan Surat Penawaran
    </p>

    <!-- ======================================================== -->
    <!-- 4.c. PARAGRAF PERNYATAAN                                  -->
    <!-- Align Left, Tanpa Indentasi Baris Pertama                 -->
    <!-- ======================================================== -->
    <p class="MsoNormal" style="text-align: left; text-indent: 0; margin-bottom: 0;">
        Dengan ini menyatakan bahwa telah membaca memahami dan menyetujui Surat Penawaran BBSPJIS.
    </p>

    <!-- ======================================================== -->
    <!-- 4.d. PARAGRAF PENGANTAR DAFTAR                            -->
    <!-- Rata Kiri                                                 -->
    <!-- ======================================================== -->
    <p class="MsoNormal" style="text-align: left; margin-top: 0; margin-bottom: 0;">
        Selanjutnya kami menyatakan:
    </p>

    <!-- ======================================================== -->
    <!-- 4.e. DAFTAR BERNOMOR                                      -->
    <!-- Berformat 1., Indentasi 0.5 cm, Hanging 0.5 cm, Align Left-->
    <!-- ======================================================== -->
    <?php
    $pernyataan = [
        'Menyetujui ruang lingkup pekerjaan biaya layanan serta syarat dan ketentuan yang tercantum dalam surat penawaran.',
        'Bersedia memenuhi kewajiban pembayaran sesuai ketentuan yang berlaku setelah diterbitkan tagihan (<em>invoice</em>) oleh BBSPJIS.',
        'Persetujuan ini menjadi dasar bagi BBSPJIS untuk memproses permohonan layanan sesuai dengan ketentuan yang berlaku.',
        'Apabila terdapat perubahan ruang lingkup pekerjaan setelah persetujuan ini diberikan maka akan dilakukan penyesuaian melalui kesepakatan kedua belah pihak.'
    ];
    ?>
    <?php foreach ($pernyataan as $pIdx => $pText): ?>
        <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; margin-top: 1pt; margin-bottom: 0;">
            <tr>
                <td width="20" style="width: 0.5cm; vertical-align: top; padding: 0; border: none;"><p class="MsoNormal"><?= ($pIdx + 1) ?>.</p></td>
                <td style="vertical-align: top; padding: 0; border: none;"><p class="MsoNormal" style="text-align: left;"><?= $pText ?></p></td>
            </tr>
        </table>
    <?php endforeach; ?>

    <!-- ======================================================== -->
    <!-- 4.f. PARAGRAF PENUTUP                                     -->
    <!-- Align Left, Tanpa Indentasi Baris Pertama, Spasi 6 pt     -->
    <!-- ======================================================== -->
    <p class="MsoNormal" style="margin-top: 6pt; margin-bottom: 6pt; text-align: left; text-indent: 0;">
        Demikian Lembar Persetujuan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.
    </p>

    <!-- ======================================================== -->
    <!-- 4.g. TABEL ISIAN KONFIRMASI KLIEN                         -->
    <!-- 2 Kolom (38% & 62%), Border Tipis Penuh di Semua Sisi     -->
    <!-- 7 Baris, Baris 2 Merge, Baris 7 Tinggi 3 cm Area TTD & Cap-->
    <!-- Padding Kiri 0.2 cm                                       -->
    <!-- ======================================================== -->
    <table class="MsoNormalTable" border="1" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; mso-table-layout-alt: fixed; table-layout: fixed; border: 0.5pt solid #000000; margin-bottom: 0;">
        <tr style="height: 0.5cm;">
            <td width="38%" style="width: 38%; border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">Tempat, Tanggal</p></td>
            <td width="62%" style="width: 62%; border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">&nbsp;</p></td>
        </tr>
        <tr style="height: 0.5cm;">
            <td colspan="2" style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">Menyetujui,</p></td>
        </tr>
        <tr style="height: 0.5cm;">
            <td style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">Nama Perusahaan/Instansi</p></td>
            <td style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">&nbsp;</p></td>
        </tr>
        <tr style="height: 0.5cm;">
            <td style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">Nama Pemohon</p></td>
            <td style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">&nbsp;</p></td>
        </tr>
        <tr style="height: 0.5cm;">
            <td style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">Jabatan</p></td>
            <td style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">&nbsp;</p></td>
        </tr>
        <tr style="height: 0.5cm;">
            <td style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">Nomor Telepon (<i>WhatsApp</i>)</p></td>
            <td style="border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: middle;"><p class="MsoNormal">&nbsp;</p></td>
        </tr>
        <tr style="height: 3.0cm;">
            <td style="height: 3.0cm; border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: top;"><p class="MsoNormal">Tanda Tangan dan Cap</p></td>
            <td style="height: 3.0cm; border: 0.5pt solid #000000; padding: 2pt 0.2cm; vertical-align: top;"><p class="MsoNormal">&nbsp;</p></td>
        </tr>
    </table>

    <!-- ======================================================== -->
    <!-- 4.h. CATATAN                                              -->
    <!-- Align Left, Tepat di Bawah Tabel Tanpa Jarak              -->
    <!-- ======================================================== -->
    <p class="MsoNormal" style="margin-top: 0; margin-bottom: 0; text-align: left;">
        Catatan: Mohon mengembalikan lembar persetujuan ini kepada BBSPJIS melalui <i>email</i> atau media komunikasi yang telah ditentukan sebagai dasar proses penerbitan <i>invoice</i> dan pelaksanaan layanan.
    </p>

    <!-- ======================================================== -->
    <!-- 4.i. BLOK TANDA TANGAN (Halaman Lampiran)                 -->
    <!-- Sama Persis dengan Blok H di Halaman Surat                -->
    <!-- ======================================================== -->
    <table class="MsoNormalTable" border="0" cellspacing="0" cellpadding="0" width="100%" style="width: 100.0%; border-collapse: collapse; mso-table-layout-alt: fixed; table-layout: fixed; margin-top: 12pt; margin-bottom: 18pt;">
        <tr>
            <td width="60%" style="width: 60.0%; width: 9.6cm; border: none; padding: 0;">&nbsp;</td>
            <td width="40%" style="width: 40.0%; width: 6.4cm; border: none; padding: 0; text-align: center; vertical-align: top;">
                <p class="MsoNormal" style="text-align: center; margin-top: 12.0pt; margin-bottom: 6.0pt; mso-para-margin-top: 12.0pt; mso-para-margin-bottom: 6.0pt; line-height: 1.15;"><?= htmlspecialchars($jabatanPejabat) ?>,</p>
                <p class="MsoNormal" style="text-align: center; margin: 0; line-height: 1.15;">&nbsp;</p>
                <p class="MsoNormal" style="text-align: center; margin: 0; line-height: 1.15;">&nbsp;</p>
                <p class="MsoNormal" style="text-align: center; margin: 0; line-height: 1.15;">&nbsp;</p>
                <p class="MsoNormal" style="text-align: center; margin: 0; line-height: 1.15;">&nbsp;</p>
                <p class="MsoNormal" style="text-align: center; margin-top: 6.0pt; margin-bottom: 12.0pt; mso-para-margin-top: 6.0pt; mso-para-margin-bottom: 12.0pt; line-height: 1.15;"><?= htmlspecialchars($pejabatNama) ?></p>
            </td>
        </tr>
    </table>

</div><!-- end Section2 (Nomor -2- stay di Halaman 2) -->

<!-- ======================================================== -->
<!-- BERKAS LAMPIRAN DOKUMEN PENDUKUNG (Halaman 3+)           -->
<!-- Murni Menyesuaikan Dokumen Asli yang Diunggah (Tanpa Header Tambahan) -->
<!-- ======================================================== -->
<?php if (!empty($lampiranPdfPages)): ?>
    <br clear="all" style="page-break-before: always; mso-break-type: section-break;" />
    <div class="Section3">
        <?php foreach ($lampiranPdfPages as $idx => $pageBase64): 
            $rawImg = base64_decode($pageBase64);
            $info = @getimagesizefromstring($rawImg);
            $imgW = ($info && $info[0] > 0) ? $info[0] : 1240;
            $imgH = ($info && $info[1] > 0) ? $info[1] : 1754;

            // Safe printable area in Section 3 (1.0cm margins on 21.0 x 29.7 cm A4)
            $maxW = 680;  // 18.0 cm in 96 DPI
            $maxH = 960;  // 25.4 cm in 96 DPI
            $scale = min($maxW / $imgW, $maxH / $imgH, 1.0);
            $drawW = round($imgW * $scale);
            $drawH = round($imgH * $scale);
            $cmW = round($drawW / 96 * 2.54, 2);
            $cmH = round($drawH / 96 * 2.54, 2);
        ?>
            <?php if ($idx > 0): ?>
            <br clear="all" style="page-break-before: always; mso-break-type: page-break;" />
            <?php endif; ?>
            <div style="text-align: center; margin: 0; padding: 0;">
                <img width="<?= $drawW ?>" height="<?= $drawH ?>" src="data:image/jpeg;base64,<?= $pageBase64 ?>" style="width: <?= $cmW ?>cm; height: <?= $cmH ?>cm; max-width: 100%; display: block; margin: 0 auto;">
            </div>
        <?php endforeach; ?>
    </div>
<?php elseif (!empty($lampiranImageBase64)): 
    $rawImg = base64_decode($lampiranImageBase64);
    $info = @getimagesizefromstring($rawImg);
    $imgW = ($info && $info[0] > 0) ? $info[0] : 1240;
    $imgH = ($info && $info[1] > 0) ? $info[1] : 1754;

    $maxW = 680;
    $maxH = 960;
    $scale = min($maxW / $imgW, $maxH / $imgH, 1.0);
    $drawW = round($imgW * $scale);
    $drawH = round($imgH * $scale);
    $cmW = round($drawW / 96 * 2.54, 2);
    $cmH = round($drawH / 96 * 2.54, 2);
?>
    <br clear="all" style="page-break-before: always; mso-break-type: section-break;" />
    <div class="Section3">
        <div style="text-align: center; margin: 0; padding: 0;">
            <img width="<?= $drawW ?>" height="<?= $drawH ?>" src="data:image/<?= htmlspecialchars($lampiranImageExt ?? 'jpeg') ?>;base64,<?= $lampiranImageBase64 ?>" style="width: <?= $cmW ?>cm; height: <?= $cmH ?>cm; max-width: 100%; display: block; margin: 0 auto;">
        </div>
    </div>
<?php elseif (!empty($lampiranDocName)): ?>
    <br clear="all" style="page-break-before: always; mso-break-type: section-break;" />
    <div class="Section3">
        <div style="text-align: center; font-family: Arial, sans-serif; font-size: 11pt; margin-top: 24pt;">
            <p style="margin-bottom: 6pt;">Berkas Lampiran Dokumen: <?= htmlspecialchars($lampiranDocName) ?></p>
            <p style="font-size: 10pt; color: #555555;">(Tersedia dan terlampir pada sistem OPTI Tracker BBSPJIS)</p>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
