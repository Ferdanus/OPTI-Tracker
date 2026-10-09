<?php
// Script debug SEMENTARA -- aman dihapus kapan aja, gak kepake sama aplikasi.
// Tujuannya cuma buat ngintip struktur asli tabel po_kegiatan & po_nomor_urut
// langsung dari database, biar ketauan kenapa simpen PO baru gagal.

header('Content-Type: text/plain; charset=utf-8');

// [FIX] parse_ini_file() bawaan PHP gagal buat baris db_dns (isinya ada titik
// koma & banyak tanda "=" -- dianggap komentar/salah sintaks sama parser INI
// standar). F3 sendiri punya parser config-nya sendiri yang lebih longgar,
// jadi di sini kita parse manual aja per baris "key=value".
$dsn = $user = $pass = '';
foreach (file(__DIR__ . '/config.ini') as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === ';' || $line[0] === '[') continue;
    $pos = strpos($line, '=');
    if ($pos === false) continue;
    $key = trim(substr($line, 0, $pos));
    $val = trim(substr($line, $pos + 1));
    if ($key === 'db_dns')  $dsn  = $val;
    if ($key === 'db_user') $user = $val;
    if ($key === 'db_pass') $pass = $val;
}

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (\Exception $e) {
    echo "GAGAL KONEK DB: " . $e->getMessage() . "\n";
    exit;
}

function dump($pdo, $title, $sql) {
    echo "=== $title ===\n";
    try {
        $stmt = $pdo->query($sql);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach ($row as $k => $v) {
                echo "[$k]\n$v\n";
            }
            echo "---\n";
        }
    } catch (\Exception $e) {
        echo "(error: " . $e->getMessage() . ")\n";
    }
    echo "\n";
}

dump($pdo, 'SHOW CREATE TABLE po_kegiatan', 'SHOW CREATE TABLE po_kegiatan');
dump($pdo, 'SHOW CREATE TABLE po_nomor_urut', 'SHOW CREATE TABLE po_nomor_urut');
dump($pdo, 'SHOW TRIGGERS (po_kegiatan)', "SHOW TRIGGERS WHERE `Table` = 'po_kegiatan'");
dump($pdo, 'MySQL version', 'SELECT VERSION() AS v');

echo "=== Isi po_nomor_urut (kalau ada) ===\n";
dump($pdo, 'Rows', 'SELECT * FROM po_nomor_urut ORDER BY id DESC LIMIT 10');

echo "SELESAI. Boleh di-copy-paste semua hasil di atas ke chat, terus file ini aman dihapus.\n";
