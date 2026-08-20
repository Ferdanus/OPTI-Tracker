<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = new \DB\SQL('mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=utf8mb4', 'root', '');

echo "--- Resetting clean initial demo data ---\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0");

$db->exec("TRUNCATE TABLE opti_pembayaran");
$db->exec("TRUNCATE TABLE opti_po_jadwal_kerja");
$db->exec("TRUNCATE TABLE po_rincian_anggaran");
$db->exec("TRUNCATE TABLE po_log_status");
$db->exec("TRUNCATE TABLE kontrak_pks");
$db->exec("TRUNCATE TABLE po");
$db->exec("TRUNCATE TABLE order_layanan");

// Clean test customers
$db->exec("DELETE FROM tb_customer WHERE id_customer > 2883");
$db->exec("ALTER TABLE tb_customer AUTO_INCREMENT = 2884");

// 1. Seed Order Layanan
$db->exec("
INSERT INTO order_layanan (id, id_customer, nomor_order, tanggal_masuk, judul_kegiatan, deskripsi, spm_layanan, jenis_layanan_opti, lokasi_pelaksanaan, lab_internal, lokasi_lapangan, tipe_data_sampel, jenis_sampel, volume_berat, karakteristik_serat, karakteristik_kimia, jumlah_pekerjaan, estimasi_biaya, status, created_at) VALUES
(1, 2, 'ORD-202608-001', '2026-08-14', 'Pengujian Pembuatan & Pemutihan Pulp Kayu Akasia', 'Optimasi rasio alkali aktif dan sulfiditas untuk peningkatan indeks retak dan kecerahan (brightness).', 'Pembuatan pulp', 'selulosa', 'internal', 'Pemasakan & Pemutihan', NULL, NULL, 'Chips Kayu Akasia (Acacia mangium)', '25 kg', 'Panjang serat rata-rata 1.1 mm, dinding serat medium', 'Kandungan Lignin 28.4%, Holoselulosa 68.2%, Kadar Air 9.5%', '5 batch pemasakan & analisis kualitas', 25000000.00, 'disetujui', '2026-08-14 09:00:00'),
(2, 2, 'ORD-202608-002', '2026-08-16', 'Karakterisasi Percobaan Derivat Selulosa (CMC)', 'Sintesis Carboxymethyl Cellulose dari selulosa limbah pertanian untuk aditif industri pangan.', 'Percobaan derivat selulosa', 'selulosa', 'internal', 'Derivat Selulosa', NULL, NULL, 'Selulosa Ampas Tebu (Bagasse)', '10 kg', 'Derajat kristalinitas serat 62%', 'Kadar Alpha Selulosa >85%, Viskositas 1500 cPs, Derajat Substitusi 0.85', '3 formulasi sintesis & uji FTIR', 18000000.00, 'baru', '2026-08-16 10:30:00'),
(3, 4, 'ORD-202608-003', '2026-08-17', 'Pengujian Emisi Cerobong & Efisiensi IPAL Pabrik', 'Sampling gas buang cerobong boiler batubara dan evaluasi unit pengolahan limbah cair industri pulp.', 'Percobaan pengolahan air limbah', 'lingkungan', 'lapangan', NULL, 'Kawasan Pabrik PT Kimia Industri Hijau Karawang', NULL, 'Emisi Flue Gas & Air Limbah Outlet', '4 titik sampling gas & 20 liter limbah', 'Partikulat debu cerobong <50 mg/Nm3', 'BOD 250 mg/L, COD 600 mg/L, pH 6.8, Suhu Cerobong 175 C', '4 titik cerobong & 6 parameter air limbah', 32000000.00, 'disetujui', '2026-08-17 11:00:00')
");

// 2. Seed PO
$db->exec("
INSERT INTO po (id, order_id, nomor_po, biaya, tim_kerja, status, tanggal_keluar, target_mulai, target_selesai, app_proposal, app_proposal_date, app_po_adm, app_po_adm_date, created_at) VALUES
(1, 1, '01/PO/BBSPJIS/VIII/2026', 25000000.00, 'Tim Analis Pulp - Andri Taufick', 'on_proses', '2026-08-15', '2026-08-15', '2026-11-15', 1, '2026-08-14 14:00:00', 1, '2026-08-15 09:30:00', '2026-08-15 09:30:00'),
(2, 3, '02/PO/BBSPJIS/VIII/2026', 32000000.00, 'Tim Sampling Lingkungan - Rina Masriani', 'sudah_upload', '2026-08-18', '2026-08-18', '2026-10-18', 1, '2026-08-17 11:00:00', 0, NULL, '2026-08-18 11:00:00')
");

// 3. Seed RAB PO 1 & 2
$db->exec("
INSERT INTO po_rincian_anggaran (po_id, kategori, deskripsi, nominal) VALUES
(1, 'Bahan/Material', 'Bahan Kimia NaOH, Na2S Standar Uji Kraft Cooking & H2O2 Bleaching', 6500000.00),
(1, 'Jasa Layanan/Pengujian', 'Pengujian Kekuatan Tarik, Sobek, Retak & Derajat Kecerahan ISO', 14500000.00),
(1, 'Transport/Sampling', 'Transportasi Penjemputan Sampel Industri', 2000000.00),
(1, 'Operasional/Lain-lain', 'Persiapan Digester Lab & Pelaporan Teknis', 2000000.00),
(2, 'Bahan/Material', 'Larutan Penjerap Gas (Impinger Solution) & Tabung Sampling', 7000000.00),
(2, 'Jasa Layanan/Pengujian', 'Jasa Analisis Flue Gas Analyzer & Parameter Air Limbah (BOD/COD)', 20000000.00),
(2, 'Transport/Sampling', 'Akomodasi & Transportasi Lapangan 4 Orang Tim Analis ke Karawang', 5000000.00)
");

// 4. Seed Jadwal Kerja
$db->exec("
INSERT INTO opti_po_jadwal_kerja (po_id, personil_anggota, tahap_kegiatan, tanggal_mulai, tanggal_selesai, status_pekerjaan, keterangan) VALUES
(1, 'Andri Taufick', 'Persiapan bahan baku chips kayu & pengujian pendahuluan', '2026-08-15', '2026-08-25', 'selesai', 'Penyiapan laboratorium digester'),
(1, 'Dra. Sri Purwati', 'Pemasakan pulp skala lab digester & pemutihan ECF', '2026-08-26', '2026-09-20', 'berjalan', 'Optimasi kappa number'),
(1, 'Yogi Afiyan', 'Pengujian lembaran pulp & pengukuran derajat putih ISO', '2026-09-21', '2026-10-15', 'rencana', 'Pengujian fisik mekanik kertas'),
(2, 'Rina Masriani', 'Koordinasi lapangan & inspeksi titik cerobong', '2026-08-18', '2026-08-20', 'selesai', 'Verifikasi titik lubang sampling'),
(2, 'Tim Analis Lingkungan', 'Sampling isokinetik partikulat & gas buang boiler', '2026-08-21', '2026-08-25', 'berjalan', 'Pengukuran flue gas analyzer')
");

// 5. Seed Pembayaran
$db->exec("
INSERT INTO opti_pembayaran (order_id, po_id, termin_ke, tanggal_bayar, jumlah, keterangan, status_verifikasi) VALUES
(1, 1, 1, '2026-08-15', 10000000.00, 'Termin 1 (DP 40% Virtual Account Mandiri)', 'terverifikasi'),
(3, 2, 1, '2026-08-18', 32000000.00, 'Pembayaran Penuh 100% di Muka (Billing SIMPONI)', 'terverifikasi')
");

// 6. Seed Kontrak PKS
$db->exec("
INSERT INTO kontrak_pks (po_id, nomor_pks_klien, nomor_pks_bbspjis, nama_penandatangan_klien, jabatan_penandatangan_klien, nama_penandatangan_bbspjis, jabatan_penandatangan_bbspjis, ruang_lingkup, target_mulai, target_selesai, nilai_kontrak, ketentuan_pembayaran, nomor_va, tanggal_ttd, status_ttd) VALUES
(1, '089/DIR-SMS/PKS/VIII/2026', '102/PKS/BBSPJIS/VIII/2026', 'Ir. Hendra Gunawan', 'Direktur Utama', 'Andri Taufick Rizaluddin', 'Kepala Balai Besar', 'Penyelenggaraan Jasa Optimalisasi Pemanfaatan Teknologi Industri Pembuatan & Pemutihan Pulp Kayu Akasia', '2026-08-15', '2026-11-15', 25000000.00, 'Pembayaran termin: DP 40% di awal, 60% setelah BAST diterbitkan.', '896081232370878', '2026-08-15', 'sudah')
");

$db->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "Clean reset completed successfully!\n\n";

$pos = $db->exec('SELECT p.id, p.nomor_po, p.biaya, p.status, o.nomor_order, o.jenis_layanan_opti, o.judul_kegiatan, cust.nmcustomer, cust.pt_cv FROM po p JOIN order_layanan o ON p.order_id = o.id JOIN tb_customer cust ON o.id_customer = cust.id_customer ORDER BY p.id ASC');
echo "Official Active POs in Database:\n";
foreach ($pos as $p) {
    echo " -> PO ID: {$p['id']} | {$p['nomor_po']} | Order: {$p['nomor_order']} ({$p['jenis_layanan_opti']}) | Mitra: {$p['nmcustomer']} ({$p['pt_cv']}) | {$p['judul_kegiatan']}\n";
}
