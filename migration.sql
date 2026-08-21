-- ====================================================================
-- SKRIP INISIALISASI & MIGRASI DATABASE OPTI TRACKER BBSPJI SELULOSA
-- ====================================================================
CREATE DATABASE IF NOT EXISTS mini_opti_tracker;
USE mini_opti_tracker;

-- Matikan foreign key check sementara untuk reset aman
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS opti_user_alert_config;
DROP TABLE IF EXISTS opti_po_sop_progress;
DROP TABLE IF EXISTS opti_field_config;
DROP TABLE IF EXISTS opti_po_jadwal_kerja;
DROP TABLE IF EXISTS po_rincian_biaya;
DROP TABLE IF EXISTS po_rincian_anggaran;
DROP TABLE IF EXISTS opti_pembayaran;
DROP TABLE IF EXISTS kontrak_pks;
DROP TABLE IF EXISTS po_log_status;
DROP TABLE IF EXISTS po;
DROP TABLE IF EXISTS order_layanan;
DROP TABLE IF EXISTS opti_user_map;
DROP TABLE IF EXISTS tb_customer;
DROP TABLE IF EXISTS tb_arsipuser;
DROP TABLE IF EXISTS klien;
DROP TABLE IF EXISTS user;
DROP TABLE IF EXISTS role;

SET FOREIGN_KEY_CHECKS = 1;

-- ====================================================================
-- 1. TABEL MASTER PUSAT BALAI: tb_arsipuser (Single Sign-On Style)
-- CATATAN: Menggunakan struktur asli dari server pusat balai (Host: 202.150.151.244)
-- JANGAN ALTER struktur tabel produksi ini!
-- ====================================================================
CREATE TABLE `tb_arsipuser` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `id_struktural` int(11) DEFAULT NULL,
  `nama_user` varchar(255) DEFAULT NULL,
  `dualogin` enum('Y','N') DEFAULT 'N',
  `no_hp` varchar(15) DEFAULT NULL,
  `bidang` varchar(20) DEFAULT NULL,
  `nama_avatar` varchar(30) DEFAULT NULL,
  `show_customers` enum('Y','N') DEFAULT 'Y',
  `kd_contoh_authen` varchar(20) DEFAULT NULL,
  `id_struktural_tim` int(11) NOT NULL DEFAULT '0',
  `id_struktural_tim_staf` bigint(20) DEFAULT NULL,
  `id_sicaper` varchar(60) DEFAULT NULL,
  `id_sicaper_katim` int(11) DEFAULT NULL,
  `id_sibangmin` varchar(60) DEFAULT NULL,
  `si_kalibrasi` varchar(60) DEFAULT NULL,
  `id_inspeksi` varchar(60) DEFAULT NULL,
  `id_goonline` varchar(255) DEFAULT NULL,
  `id_keuangan_bios` varchar(255) DEFAULT NULL,
  `id_arsip` varchar(60) DEFAULT NULL,
  `id_udara` varchar(60) DEFAULT NULL,
  `id_lph` varchar(30) DEFAULT NULL,
  `si_sertifikasi` varchar(30) DEFAULT NULL,
  `si_perjakin` varchar(30) DEFAULT NULL,
  `si_selport` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id_user`)
) ENGINE=MyISAM AUTO_INCREMENT=168168169 DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;

-- Seed Users Pusat BBSPJI Selulosa (Termasuk User Real Balai + Akun Demo Alias)
INSERT INTO `tb_arsipuser` VALUES 
(1,'empunyadmin','398660959c7017252ce1952de852bb4d',1,200,'HK Administrator','N','628156006227','all','PHOTO_20141219_174547.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin','penerimaan_order','admin',NULL,'admin','admin',NULL,'admin','penerimaan_order','superadmin','admin'),
(3,'rinaPIs2r','7d4871dbbe5e0a859d5fd38098a582f5',1,41,'Rina Masriani','N','6281320635632','srs','rina.jpg','N',NULL,170,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(4,'ningrum','1a3b01e75a6877968164fa4f3f93d88a',1,160,'Nurhadiningrum Yuniastuti','N','6285659220865','tu','ningrum.jpg','Y',NULL,1,1,NULL,NULL,NULL,'keuangan','keuangan','651830','penerimaan',NULL,NULL,NULL,'keuangan',NULL,NULL),
(12,'yogiGu0zM','398660959c7017252ce1952de852bb4d',1,15,'Yogi Afiyan','N','6281261540423','paskal','yogi.jpg','Y',NULL,1,1,NULL,NULL,NULL,NULL,'tim_inspeksi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(36,'faridhKIMqp','14f7a701e8b5fa15871d25c614d1b122',1,152,'Faridh Hendriana','N','6282121200985','tu','faridh.jpg','N',NULL,50,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(58,'hendrJoc2q','4846ef474cc7f39013f0e129984e88f3',1,70,'Hendro Risdianto','N','628112205515','SRS','hendro.jpg','N',NULL,90,NULL,'ketua_tim',NULL,NULL,NULL,'tim_inspeksi',NULL,NULL,NULL,NULL,NULL,'auditor',NULL,NULL),
(61,'andritr3','23630c4c716e1e5b1982c3e075a19b0e',1,41,'Andri Taufick Rizaluddin','N','628179296562','SRS','andri.jpg','N',NULL,190,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(73,'jokopJI0zo','98f369ea1c54fcf4662d876db3c1764d',1,151,'Joko Pratomo','N','6281214519188','paskal','jokop.jpeg','Y',NULL,20,NULL,'kabag_tu',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(149,'doroadmind','398660959c7017252ce1952de852bb4d',1,200,'FA Administrator','N','6281283391300','all','fandyachmad.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin','admin','admin','746919','admin','admin',NULL,'admin','keuangan','pic',NULL),
(150,'doroadmint','398660959c7017252ce1952de852bb4d',1,200,'Ade K. Hidayat','N','6285221220100','all','papaanr.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin','admin','admin','801896','admin','admin',NULL,NULL,'tim_mitra',NULL,NULL),
(175,'kabbs','5e5bcc2c921acb32f667f20337246072',1,3,'Dodiet Prasetyo','N','62817217216',NULL,'doditisback.png','Y',NULL,2000,1,'pimpinan',NULL,NULL,'penerimaan_order','show',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(9001,'andri_selulosa','$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K',1,41,'Pak Andri Taufick','N','628179296562','srs','andri.jpg','Y',NULL,190,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(9002,'rina_lingkungan','$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K',1,41,'Bu Rina Masriani','N','6281320635632','srs','rina.jpg','Y',NULL,170,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(9003,'admin_order','$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K',1,152,'Petugas Order Layanan','N','6282121200985','tu','faridh.jpg','Y',NULL,50,NULL,'admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(9004,'pejabat_balai','$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K',1,3,'Kepala Balai / PPK BLU','N','62817217216',NULL,'doditisback.png','Y',NULL,2000,1,'pimpinan',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(9005,'admin_kontrak','$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K',1,200,'Staf Adm Kerjasama PKS','N','6285221220100','all','papaanr.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(9006,'fajriasa','$2y$10$OnurP/9DXA/MahHk.lmit..Gpn7uuBmzOs56ls5PlC9YNNkHmM80.','1',200,'Fajriasa Administrator','N','628156006227','all','PHOTO_20141219_174547.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin','admin','admin',NULL,'admin','admin',NULL,'admin','penerimaan_order','superadmin','admin');

-- ====================================================================
-- 2. TABEL PENGHUBUNG ROLE OPTI: opti_user_map
-- TODO: Konfirmasi ke admin/DBA arsipuser apakah boleh menambah kolom di tabel pusat atau tetap menggunakan tabel penghubung ini
-- ====================================================================
CREATE TABLE `opti_user_map` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_user` INT NOT NULL UNIQUE,
    `jenis_layanan_opti` ENUM('selulosa', 'lingkungan', 'semua') DEFAULT 'semua',
    `role_opti` ENUM('admin_order', 'ketua_tim', 'pejabat', 'tim_kerja', 'admin_kontrak', 'superadmin') NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Mapping Hak Akses OPTI
INSERT INTO `opti_user_map` (`id_user`, `jenis_layanan_opti`, `role_opti`, `is_active`) VALUES
(1, 'semua', 'superadmin', 1),
(3, 'lingkungan', 'ketua_tim', 1),
(4, 'semua', 'pejabat', 1),
(12, 'selulosa', 'tim_kerja', 1),
(36, 'semua', 'admin_order', 1),
(58, 'lingkungan', 'tim_kerja', 1),
(61, 'selulosa', 'ketua_tim', 1),
(73, 'semua', 'pejabat', 1),
(149, 'semua', 'superadmin', 1),
(150, 'semua', 'admin_kontrak', 1),
(175, 'semua', 'pejabat', 1),
(9001, 'selulosa', 'ketua_tim', 1),
(9002, 'lingkungan', 'ketua_tim', 1),
(9003, 'semua', 'admin_order', 1),
(9004, 'semua', 'pejabat', 1),
(9005, 'semua', 'admin_kontrak', 1),
(9006, 'semua', 'superadmin', 1);

-- ====================================================================
-- 3. TABEL MASTER CUSTOMER PUSAT: tb_customer
-- CATATAN: Menggunakan struktur asli dari server pusat balai (Host: 202.150.151.244)
-- ====================================================================
CREATE TABLE `tb_customer` (
  `id_customer` int(11) NOT NULL AUTO_INCREMENT,
  `kodex_perusahaan` smallint(6) DEFAULT NULL,
  `nmcustomer` varchar(255) DEFAULT NULL,
  `pt_cv` enum('PT','CV') DEFAULT NULL,
  `alamatcustomer` varchar(255) DEFAULT NULL,
  `alamatcustomer_baru` varchar(255) DEFAULT '',
  `kode_provinsi` int(11) DEFAULT NULL,
  `kode_kabupaten` int(11) DEFAULT NULL,
  `kode_negara` int(11) DEFAULT NULL,
  `emailcustomer` varchar(45) DEFAULT NULL,
  `notelpcustomer` varchar(45) DEFAULT NULL,
  `nofaxcustomer` varchar(45) DEFAULT NULL,
  `contactperson` varchar(45) DEFAULT NULL,
  `nohpcontactperson` varchar(17) DEFAULT '',
  `id_propinsi` int(11) DEFAULT NULL,
  `kode_pos` varchar(5) DEFAULT NULL,
  `id_kabupaten` int(11) DEFAULT NULL,
  `id_negara` int(11) DEFAULT '0',
  `nipinput` varchar(45) DEFAULT NULL,
  `tglinput` datetime DEFAULT NULL,
  `id_jenis_industri` tinyint(3) DEFAULT '0',
  `id_jenis_perusahaan` int(11) DEFAULT NULL,
  `tglupdate` datetime DEFAULT NULL,
  `thn_register` year(4) DEFAULT NULL,
  `showhide` enum('show','hide') NOT NULL DEFAULT 'show',
  `showhide_sekertaris` enum('show','hide') NOT NULL DEFAULT 'show',
  `lokasi_pabrik` varchar(250) DEFAULT '',
  `updater` varchar(9) DEFAULT '',
  `id_layanan` tinyint(3) NOT NULL DEFAULT '1',
  `id_layanan_inspeksi` tinyint(3) DEFAULT '0',
  `id_layanan_uji_profisiensi` tinyint(3) DEFAULT '0',
  `id_layanan_kalibrasi` tinyint(3) DEFAULT '0',
  `id_layanan_sertifikasi` tinyint(3) DEFAULT '0',
  `id_layanan_optimalisasi` tinyint(3) DEFAULT '0',
  `id_layanan_standardisasi` tinyint(3) DEFAULT '0',
  `id_layanan_konsultansi` tinyint(3) DEFAULT '0',
  `id_layanan_pendampingan` tinyint(3) DEFAULT '0',
  `nama_pribadi` varchar(255) DEFAULT NULL,
  `contactperson_kalibrasi` varchar(45) DEFAULT NULL,
  `nohpcontactperson_kalibrasi` varchar(17) DEFAULT '',
  `contactperson_sertifikasi` varchar(45) DEFAULT NULL,
  `nohpcontactperson_sertifikasi` varchar(17) DEFAULT '',
  `emailcustomer_sertifikasi` varchar(60) DEFAULT NULL,
  `contactperson_pengujian` varchar(45) DEFAULT NULL,
  `nohpcontactperson_pengujian` varchar(17) DEFAULT '',
  `contactperson_profiensi` varchar(45) DEFAULT NULL,
  `nohpcontactperson_profiensi` varchar(17) DEFAULT '',
  `contactperson_opti` varchar(45) DEFAULT NULL,
  `nohpcontactperson_opti` varchar(17) DEFAULT '',
  `contactperson_konsultansi` varchar(45) DEFAULT NULL,
  `nohpcontactperson_konsultansi` varchar(17) DEFAULT '',
  `contactperson_pendampinganteknis` varchar(45) DEFAULT NULL,
  `nohpcontactperson_pendampinganteknis` varchar(17) DEFAULT '',
  `id_user_sertifikasi_penerima_order` int(11) DEFAULT '0',
  `id_user_sertifikasi_penerima_order_ih` int(11) DEFAULT NULL,
  `no_id_sertifikasi_lspro` int(11) DEFAULT '0',
  `no_id_sertifikasi_lse` int(11) DEFAULT '0',
  `no_id_sertifikasi_lve` int(11) DEFAULT '0',
  `no_id_sertifikasi_lsih` int(11) DEFAULT '0',
  `no_id_sertifikasi_verifikasi_tkdn` int(11) DEFAULT NULL,
  `kodex_perusahaan_hide` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_customer`)
) ENGINE=MyISAM AUTO_INCREMENT=2890 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

-- Seed Data Customer Balai (Host: 202.150.151.244)
INSERT INTO `tb_customer` VALUES 
(1,NULL,'--Pilih--',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'',NULL,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(2,NULL,'Fajar Surya Wisesa','PT','Jl Raya Cibitung - Bekasi',NULL,NULL,NULL,NULL,'','08','','Grace ','',0,NULL,0,0,'090021240','2011-01-04 00:00:00',1,4,'2024-02-16 13:58:13',NULL,'hide','show','','',1,0,0,0,0,1,0,0,0,NULL,NULL,'',NULL,'','email@gmail.com',NULL,'',NULL,'','Bpk. Grace','081234567890',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(4,NULL,'Rayi Raka Metal Industri','PT','Kawasan Kota Bukit Indah Blok A 11 No. 28',NULL,NULL,NULL,NULL,'','(026) 491-0208','','Bpk. Sardiman D.','',12,NULL,171,0,'090021240','2011-01-04 00:00:00',1,4,NULL,NULL,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(6,NULL,'Pabrik Kertas Padalarang','PT','Jl. Cihaliwung No. 181 Padalarang Kab Bandung Barat',NULL,NULL,NULL,NULL,'wahyu.ptkp@gmail.com ','(022) 680-9315','(022) 680-9284','Bpk. Wahyu Widayanto','',12,NULL,161,62,'090022291','2017-02-24 09:17:21',1,4,NULL,0000,'hide','show','','',1,0,0,0,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Bpk. Wahyu','08122334455',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(10,13,'Pura Nusapersada','PT',' Jl. AKBP.R. Agil Kusumadya KM.2',NULL,33,3319,62,'','(029) 143-9636','','Jumadi','6285647347133',14,NULL,0,0,'090021240','2011-08-03 00:00:00',1,2,'2026-07-15 09:33:04',NULL,'show','show','','',1,0,0,0,1,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Jumadi','6285647347133',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(16,20,'Polyfin Canggih','PT','Jl. Raya Rancaekek Km. 19 No. 28, Desa Cipacing, Kab Sumedang, Bandung 45363',NULL,32,3211,62,'lany.suryati@polyfincanggih.com','(022) 779-8888','(022) 779-8885','Dedi Karso','628112207003',12,NULL,175,62,'090022291','2017-02-14 14:04:03',1,4,NULL,0000,'show','show','','',1,0,0,0,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Dedi Karso','628112207003',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(42,50,'Aspex Kumbong','PT','Wisma Korindo, Jl. MT Haryono Kav 62, Jakarta 12780',NULL,31,3174,62,'-','','(021) 823-0682','Bpk. Sabar Sriyanto','6287808876622',12,NULL,163,62,'090022291','2017-02-24 08:43:55',1,4,'2024-09-20 10:36:09',0000,'show','show','','',1,1,0,1,1,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Bpk. Sabar Sriyanto','6287808876622',NULL,'',NULL,'',129,NULL,72,0,0,0,NULL,NULL),
(54,64,'Pabrik Kertas Tjiwi Kimia Tbk.','PT','Jl. Raya Surabaya - Mojokerto Km. 44, Sidoarjo, Jawa Timur','Jl. Raya Surabaya - Mojokerto Km. 44',35,3515,62,'','(032) 136-1552','(032) 136-1615','Citra Mulya','6288801503960',15,NULL,250,0,'090021240','2011-01-20 00:00:00',1,2,'2026-01-12 08:34:38',NULL,'show','show','','',1,0,0,0,1,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Citra Mulya','6288801503960',NULL,'',NULL,'',126,NULL,6,0,0,0,NULL,NULL),
(76,275,'Sopanusa Tissue & Packaging Sarana Sukses','PT','Ds. Manduromangggunggajah',NULL,35,3516,62,'','(031) 371-5828','(031) 376-5081','Ibu Ciendrawati','',16,NULL,239,0,'090021240','2011-03-04 00:00:00',1,3,NULL,NULL,'show','show','','',1,0,0,1,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Ibu Ciendrawati','081233445566',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(82,NULL,'ITB',NULL,'Jl. Ganesha No. 10, Bandung',NULL,NULL,NULL,NULL,'','','','Hary Pratama Suhendri','',12,NULL,161,0,'090021240','2011-04-19 00:00:00',2,23,NULL,NULL,'hide','show','','',1,0,0,1,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Hary Pratama','08122334455',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(140,175,'Papertech Indonesia','PT','Jl. Raya Cipeundeuy Km. 1 Kec. Cipendeuy Kab. Subang',NULL,32,3213,62,'','(026) 071-0645','(026) 071-0644','Bapak Ketut','',12,NULL,173,0,'090021240','2011-06-21 00:00:00',1,2,'2024-07-16 06:55:01',NULL,'show','show','','',1,1,0,1,1,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Bpk. Ketut','081333444555',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(145,219,'Surya Pamenang','PT','Jl. Raya Kediri Kertosono Km. 7, Kec. Gampengrejo, Kabupaten Kediri',NULL,35,3506,62,'','(035) 468-1360','(035) 468-1591','Roy Hari A','',16,NULL,229,0,'090021240','2011-07-20 00:00:00',1,2,'2025-09-09 09:52:13',NULL,'show','show','','',1,0,0,0,0,1,1,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Roy Hari A','081234567899',NULL,'',NULL,'',126,NULL,56,0,0,0,NULL,NULL),
(146,NULL,'BALITTAS (Badan Penelitian Tanaman Tembakau dan Serat)',NULL,'Jl. Raya Karangploso Km. 4, PO Box 199',NULL,NULL,NULL,NULL,'','0341-491447','0341-485121','Bpk. Untung Setyo Budi','',3,NULL,210,0,'090021240','2011-06-27 00:00:00',0,9,NULL,NULL,'hide','show','','',1,0,0,0,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Bpk. Untung','0341-491447',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL);

-- ====================================================================
-- 4. TABEL ORDER LAYANAN OPTI: order_layanan
-- ====================================================================
CREATE TABLE order_layanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_customer INT NOT NULL,
    nomor_order VARCHAR(50) UNIQUE NOT NULL,
    tanggal_masuk DATE NOT NULL,
    judul_kegiatan VARCHAR(200) NOT NULL,
    deskripsi TEXT NULL,
    
    -- Jenis Layanan SPM Baku
    spm_layanan VARCHAR(150) NOT NULL DEFAULT 'Lainnya',
    jenis_layanan_opti ENUM('selulosa', 'lingkungan') NOT NULL,
    
    -- Lokasi Pelaksanaan: 6 Lab Internal BBSPJIS atau Lapangan
    lokasi_pelaksanaan ENUM('internal', 'lapangan') NOT NULL DEFAULT 'internal',
    lab_internal VARCHAR(100) NULL, -- 'Pemasakan & Pemutihan', 'Stock Preparation', 'Derivat Selulosa', 'Mikrobiologi', 'Biodegradasi & Toksikologi', 'Pengolahan Lingkungan'
    lokasi_lapangan TEXT NULL,
    
    -- Spesifikasi Teknis Sampel / Material
    tipe_data_sampel VARCHAR(100) NULL,
    jenis_sampel VARCHAR(150) NULL,
    volume_berat VARCHAR(100) NULL,
    karakteristik_serat TEXT NULL,
    karakteristik_kimia TEXT NULL,
    
    jumlah_pekerjaan VARCHAR(100) NOT NULL,
    estimasi_biaya DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('baru', 'disetujui', 'ditolak') DEFAULT 'baru',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_customer (id_customer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Order Layanan
INSERT INTO order_layanan (id, id_customer, nomor_order, tanggal_masuk, judul_kegiatan, deskripsi, spm_layanan, jenis_layanan_opti, lokasi_pelaksanaan, lab_internal, lokasi_lapangan, jenis_sampel, volume_berat, karakteristik_serat, karakteristik_kimia, jumlah_pekerjaan, estimasi_biaya, status) VALUES
(1, 1, 'ORD-202608-001', '2026-08-14', 'Pengujian Pembuatan & Pemutihan Pulp Kayu Akasia', 'Optimasi rasio alkali aktif dan sulfiditas untuk peningkatan indeks retak dan kecerahan (brightness).', 'Pembuatan pulp', 'selulosa', 'internal', 'Pemasakan & Pemutihan', NULL, 'Chips Kayu Akasia (Acacia mangium)', '25 kg', 'Panjang serat rata-rata 1.1 mm, dinding serat medium', 'Kandungan Lignin 28.4%, Holoselulosa 68.2%, Kadar Air 9.5%', '5 batch pemasakan & analisis kualitas', 25000000.00, 'disetujui'),
(2, 2, 'ORD-202608-002', '2026-08-16', 'Karakterisasi Percobaan Derivat Selulosa (CMC)', 'Sintesis Carboxymethyl Cellulose dari selulosa limbah pertanian untuk aditif industri pangan.', 'Percobaan derivat selulosa', 'selulosa', 'internal', 'Derivat Selulosa', NULL, 'Selulosa Ampas Tebu (Bagasse)', '10 kg', 'Derajat kristalinitas serat 62%', 'Kadar Alpha Selulosa >85%, Viskositas 1500 cPs, Derajat Substitusi 0.85', '3 formulasi sintesis & uji FTIR', 18000000.00, 'baru'),
(3, 4, 'ORD-202608-003', '2026-08-17', 'Pengujian Emisi Cerobong & Efisiensi IPAL Pabrik', 'Sampling gas buang cerobong boiler batubara dan evaluasi unit pengolahan limbah cair industri pulp.', 'Percobaan pengolahan air limbah', 'lingkungan', 'lapangan', NULL, 'Kawasan Pabrik PT Kimia Industri Hijau Karawang', 'Emisi Flue Gas & Air Limbah Outlet', '4 titik sampling gas & 20 liter limbah', 'Partikulat debu cerobong <50 mg/Nm3', 'BOD 250 mg/L, COD 600 mg/L, pH 6.8, Suhu Cerobong 175 C', '4 titik cerobong & 6 parameter air limbah', 35000000.00, 'disetujui');

-- ====================================================================
-- 5. TABEL PO (PETUNJUK OPERASIONAL & MAP KENDALI)
-- ====================================================================
CREATE TABLE po (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    nomor_po VARCHAR(50) UNIQUE NOT NULL, -- format: {urut}/PO/BBSPJIS/{bulan_romawi}/{tahun}
    biaya DECIMAL(15,2) DEFAULT 0.00,     -- SUM otomatis dari po_rincian_anggaran
    tim_kerja VARCHAR(150) NULL,
    status ENUM('belum_upload', 'sudah_upload', 'on_proses', 'kembali_selesai') DEFAULT 'belum_upload',
    
    tanggal_keluar DATE NULL,
    tanggal_kembali DATE NULL,             -- Tanggal pengembalian dokumen / revisi
    target_mulai DATE NULL,
    target_selesai DATE NULL,              -- FIXED: Batas waktu target selesai
    realisasi_selesai DATE NULL,           -- Tanggal selesai aktual (manual/auto)
    auto_realisasi_dari_upload TINYINT(1) DEFAULT 0, -- TODO: Konfirmasi ke user apakah default auto-isi dari upload atau manual
    
    -- Map Kendali Verifikasi & Validasi Berjenjang (SOP BBSPJIS)
    app_proposal TINYINT(1) DEFAULT 0,
    app_proposal_date DATETIME NULL,
    app_proposal_val TINYINT(1) DEFAULT 0,
    app_proposal_val_date DATETIME NULL,
    
    app_kontrak TINYINT(1) DEFAULT 0,
    app_kontrak_date DATETIME NULL,
    app_kontrak_val TINYINT(1) DEFAULT 0,
    app_kontrak_val_date DATETIME NULL,
    
    app_po_adm TINYINT(1) DEFAULT 0,      -- Adm KS & Humas
    app_po_adm_date DATETIME NULL,
    app_po_mitra TINYINT(1) DEFAULT 0,    -- Tim Mitra Industri
    app_po_mitra_date DATETIME NULL,
    app_po_ppk TINYINT(1) DEFAULT 0,      -- PPK BLU
    app_po_ppk_date DATETIME NULL,
    app_po_kabag TINYINT(1) DEFAULT 0,    -- Ka.Bag Tata Usaha
    app_po_kabag_date DATETIME NULL,
    
    app_dist_tu TINYINT(1) DEFAULT 0,     -- Bag. TU
    app_dist_tu_date DATETIME NULL,
    app_dist_kepeg TINYINT(1) DEFAULT 0,  -- Tim Kepeg/Organisasi/Tata Laksana
    app_dist_kepeg_date DATETIME NULL,
    app_dist_keu TINYINT(1) DEFAULT 0,    -- Tim Keuangan & Penatausahaan BMN
    app_dist_keu_date DATETIME NULL,
    
    -- Evaluasi & Laporan Akhir (Feedback Loop SOP)
    evaluasi_status ENUM('pending', 'disetujui', 'perlu_revisi') DEFAULT 'pending',
    notulen_evaluasi TEXT NULL,
    tgl_evaluasi DATE NULL,
    laporan_akhir TEXT NULL,
    bast_dokumen VARCHAR(255) NULL,
    tgl_bast DATE NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES order_layanan(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed PO
INSERT INTO po (id, order_id, nomor_po, biaya, tim_kerja, status, tanggal_keluar, target_mulai, target_selesai, app_proposal, app_proposal_date, app_po_adm, app_po_adm_date) VALUES
(1, 1, '01/PO/BBSPJIS/VIII/2026', 25000000.00, 'Tim Analis Pulp - Pak Andri T.', 'on_proses', '2026-08-15', '2026-08-15', '2026-11-15', 1, '2026-08-14 14:00:00', 1, '2026-08-15 09:30:00'),
(2, 3, '02/PO/BBSPJIS/VIII/2026', 35000000.00, 'Tim Sampling Lingkungan - Bu Rina M.', 'sudah_upload', '2026-08-18', '2026-08-18', '2026-10-18', 1, '2026-08-17 11:00:00', 0, NULL);

-- ====================================================================
-- 6. TABEL RINCIAN ANGGARAN BIAYA PO (RAB BREAKDOWN)
-- TODO: Struktur kategori rincian anggaran masih contoh baku - konfirmasi kategori resmi ke user
-- ====================================================================
CREATE TABLE po_rincian_anggaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    kategori VARCHAR(100) NOT NULL, -- 'Bahan/Material', 'Jasa Layanan/Pengujian', 'Transport/Sampling', 'Operasional/Lain-lain'
    deskripsi VARCHAR(255) NOT NULL,
    nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed RAB PO 1
INSERT INTO po_rincian_anggaran (po_id, kategori, deskripsi, nominal) VALUES
(1, 'Bahan/Material', 'Bahan Kimia NaOH, Na2S Standar Uji Kraft Cooking & H2O2 Bleaching', 6500000.00),
(1, 'Jasa Layanan/Pengujian', 'Pengujian Kekuatan Tarik, Sobek, Retak & Derajat Kecerahan ISO', 14500000.00),
(1, 'Transport/Sampling', 'Transportasi Penjemputan Sampel Industri', 2000000.00),
(1, 'Operasional/Lain-lain', 'Persiapan Digester Lab & Pelaporan Teknis', 2000000.00);

-- Seed RAB PO 2
INSERT INTO po_rincian_anggaran (po_id, kategori, deskripsi, nominal) VALUES
(2, 'Bahan/Material', 'Larutan Penjerap Gas (Impinger Solution) & Tabung Sampling', 7000000.00),
(2, 'Jasa Layanan/Pengujian', 'Jasa Analisis Flue Gas Analyzer & Parameter Air Limbah (BOD/COD)', 20000000.00),
(2, 'Transport/Sampling', 'Akomodasi & Transportasi Lapangan 4 Orang Tim Analis ke Karawang', 5000000.00),
(2, 'Operasional/Lain-lain', 'Sewa Genset Portable & Kalibrasi Peralatan Lapangan', 3000000.00);

-- ====================================================================
-- 7. TABEL PEMBAYARAN MULTI-TERMIN (One-to-Many terhadap Order/PO)
-- ====================================================================
CREATE TABLE opti_pembayaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    po_id INT NULL,
    termin_ke INT NOT NULL DEFAULT 1,
    tanggal_bayar DATE NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    keterangan VARCHAR(255) NULL,
    bukti_bayar VARCHAR(255) NULL,
    status_verifikasi ENUM('menunggu', 'terverifikasi', 'ditolak') DEFAULT 'terverifikasi',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES order_layanan(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Pembayaran (Termin 1 untuk Order 1)
INSERT INTO opti_pembayaran (order_id, po_id, termin_ke, tanggal_bayar, jumlah, keterangan, status_verifikasi) VALUES
(1, 1, 1, '2026-08-15', 10000000.00, 'Pembayaran Uang Muka (DP 40%) via VA Bank Mandiri', 'terverifikasi'),
(3, 2, 1, '2026-08-18', 15000000.00, 'Pembayaran Tahap 1 Persiapan Sampling Lapangan', 'terverifikasi');

-- ====================================================================
-- 8. TABEL JADWAL KERJA TIM PELAKSANA (Timeline Sederhana)
-- ====================================================================
CREATE TABLE opti_po_jadwal_kerja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    personil_anggota VARCHAR(100) NOT NULL,
    tahap_kegiatan VARCHAR(200) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status_pekerjaan ENUM('rencana', 'berjalan', 'selesai') DEFAULT 'rencana',
    keterangan TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Jadwal Kerja PO 1
INSERT INTO opti_po_jadwal_kerja (po_id, personil_anggota, tahap_kegiatan, tanggal_mulai, tanggal_selesai, status_pekerjaan, keterangan) VALUES
(1, 'Pak Andri T. & Tim Pemasakan', 'Preparasi Bahan Baku & Pemasakan Pulp Digester', '2026-08-16', '2026-09-05', 'selesai', 'Batch 1-3 telah selesai dipasak.'),
(1, 'Bu Siti (Analis Kimia)', 'Proses Pemutihan (Bleaching Sequence D-E-D)', '2026-09-06', '2026-10-10', 'berjalan', 'Sedang tahap ekstraksi alkali.'),
(1, 'Tim Penguji Fisik Kertas', 'Pembuatan Lembaran Kertas Uji & Pengujian Fisik/Optik', '2026-10-11', '2026-11-05', 'rencana', 'Menunggu sampel lembaran kering.'),
(1, 'Pak Andri T.', 'Penyusunan Draf Laporan Teknis & Evaluasi', '2026-11-06', '2026-11-15', 'rencana', 'Evaluasi akhir bersama mitra.');

-- ====================================================================
-- 9. TABEL KONTRAK KERJASAMA (PKS)
-- TODO: Konfirmasi posisi kontrak dalam alur: apakah PKS sebelum PO, sesudah PO, atau paralel.
-- ====================================================================
CREATE TABLE kontrak_pks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL UNIQUE,
    nomor_pks_klien VARCHAR(100) NOT NULL,
    nomor_pks_bbspjis VARCHAR(100) NOT NULL,
    nama_penandatangan_klien VARCHAR(100) NOT NULL,
    jabatan_penandatangan_klien VARCHAR(100) NOT NULL,
    nama_penandatangan_bbspjis VARCHAR(100) NOT NULL,
    jabatan_penandatangan_bbspjis VARCHAR(100) NOT NULL,
    ruang_lingkup TEXT NOT NULL,
    target_mulai DATE NOT NULL,
    target_selesai DATE NOT NULL,
    nilai_kontrak DECIMAL(15,2) DEFAULT 0.00,
    ketentuan_pembayaran TEXT NULL,
    nomor_va VARCHAR(50) NULL,
    tanggal_ttd DATE NULL,
    status_ttd ENUM('belum', 'sudah') DEFAULT 'belum',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Kontrak PKS untuk PO 1
INSERT INTO kontrak_pks (po_id, nomor_pks_klien, nomor_pks_bbspjis, nama_penandatangan_klien, jabatan_penandatangan_klien, nama_penandatangan_bbspjis, jabatan_penandatangan_bbspjis, ruang_lingkup, target_mulai, target_selesai, nilai_kontrak, ketentuan_pembayaran, nomor_va, tanggal_ttd, status_ttd) VALUES
(1, '089/DIR-SMS/PKS/VIII/2026', '102/PKS/BBSPJIS/VIII/2026', 'Ir. Hendra Gunawan', 'Direktur Utama', 'Andri T.', 'Kepala Balai Besar', 'Penyelenggaraan Jasa Optimalisasi Pemanfaatan Teknologi Industri Pembuatan & Pemutihan Pulp Kayu Akasia', '2026-08-15', '2026-11-15', 25000000.00, 'Pembayaran termin: DP 40% di awal, 60% setelah BAST diterbitkan.', '896081232370878', '2026-08-15', 'sudah');

-- ====================================================================
-- 10. TABEL AUDIT TRAIL LOG STATUS PO
-- ====================================================================
CREATE TABLE po_log_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    status_lama VARCHAR(50) NULL,
    status_baru VARCHAR(50) NOT NULL,
    catatan VARCHAR(255) NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Log
INSERT INTO po_log_status (po_id, status_lama, status_baru, catatan, tanggal) VALUES
(1, NULL, 'belum_upload', 'PO diterbitkan otomatis dari Order Layanan ORD-202608-001.', '2026-08-14 14:00:00'),
(1, 'belum_upload', 'on_proses', 'Proposal dan dokumen PO diverifikasi, pelaksanaan dimulai.', '2026-08-15 09:30:00');

-- ====================================================================
-- 11. TABEL KONFIGURASI DINAMIS SHOW/HIDE FIELD & PRIVASI MASKING
-- ====================================================================
CREATE TABLE opti_field_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jenis_layanan_opti ENUM('selulosa', 'lingkungan', 'global') NOT NULL DEFAULT 'global',
    entity VARCHAR(50) NOT NULL, -- 'order', 'po', 'sampel', 'klien'
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(150) NOT NULL,
    is_visible TINYINT(1) DEFAULT 1,
    is_required TINYINT(1) DEFAULT 0,
    default_value VARCHAR(255) NULL,
    mask_for_privacy TINYINT(1) DEFAULT 0, -- Sembunyikan/samarkan nama klien di dashboard/laporan umum
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Konfigurasi Field Awal
INSERT INTO opti_field_config (jenis_layanan_opti, entity, field_name, field_label, is_visible, is_required, default_value, mask_for_privacy) VALUES
-- Field Spesifikasi Sampel Selulosa
('selulosa', 'sampel', 'karakteristik_serat', 'Karakteristik Serat & Morfologi', 1, 0, NULL, 0),
('selulosa', 'sampel', 'karakteristik_kimia', 'Karakteristik Kimia (Lignin, Alfa-Selulosa)', 1, 0, NULL, 0),
('selulosa', 'sampel', 'tipe_data_sampel', 'Tipe Data / Standar Uji (TAPPI/SNI/ISO)', 1, 0, 'Standar SNI / ISO', 0),

-- Field Spesifikasi Sampel Lingkungan
('lingkungan', 'sampel', 'karakteristik_serat', 'Karakteristik Partikulat / Biologi', 0, 0, NULL, 0),
('lingkungan', 'sampel', 'karakteristik_kimia', 'Parameter Baku Mutu (BOD/COD/NOx/SOx)', 1, 1, NULL, 0),
('lingkungan', 'sampel', 'tipe_data_sampel', 'Metode Akreditasi KAN', 1, 0, 'Metode Standar KAN', 0),

-- Global Masking & Privacy
('global', 'klien', 'mask_client_name', 'Samarkan Nama Klien di Dashboard Publik', 0, 0, NULL, 1);

-- ====================================================================
-- 12. TABEL PENGATURAN ALERT PRIBADI PER USER
-- ====================================================================
CREATE TABLE opti_user_alert_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    alert_key VARCHAR(50) NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    threshold_days INT DEFAULT 3,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_alert_user (id_user),
    UNIQUE KEY (id_user, alert_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Alert Default untuk User Admin & Ketua Tim
INSERT INTO opti_user_alert_config (id_user, alert_key, is_enabled, threshold_days) VALUES
(1, 'alert_pembayaran_pending', 1, 7),
(2, 'alert_po_deadline', 1, 5),
(3, 'alert_po_deadline', 1, 5),
(4, 'alert_approval_needed', 1, 2);

-- ====================================================================
-- 13. TABEL SOP JASA PELAYANAN OPTI LINGKUNGAN (19 TAHAPAN RESMI)
-- ====================================================================
CREATE TABLE opti_po_sop_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    tahap_no INT NOT NULL,
    fase ENUM('persiapan', 'pelaksanaan', 'pengesahan', 'bast') NOT NULL DEFAULT 'persiapan',
    nama_aktivitas VARCHAR(255) NOT NULL,
    pelaksana_kode VARCHAR(50) NOT NULL,
    pelaksana_label VARCHAR(100) NOT NULL,
    mutu_kelengkapan VARCHAR(255) NOT NULL,
    mutu_waktu VARCHAR(100) NOT NULL,
    mutu_output VARCHAR(255) NOT NULL,
    keterangan VARCHAR(255) NULL,
    is_decision TINYINT(1) DEFAULT 0,
    status ENUM('menunggu', 'berjalan', 'selesai', 'revisi', 'dilewati') DEFAULT 'menunggu',
    catatan TEXT NULL,
    verified_by VARCHAR(100) NULL,
    verified_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_po_tahap (po_id, tahap_no),
    INDEX idx_po_fase (po_id, fase),
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

