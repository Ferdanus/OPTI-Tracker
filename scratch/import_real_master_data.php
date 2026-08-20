<?php

require_once __DIR__ . '/../vendor/autoload.php';

$db = new \DB\SQL(
    'mysql:host=127.0.0.1;port=3306;dbname=mini_opti_tracker;charset=latin1',
    'root',
    '',
    array(
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    )
);

echo "Executing import of real production data from balai server (202.150.151.244)...\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0;");

// 1. Structure and Data for tb_customer
$db->exec("DROP TABLE IF EXISTS `tb_customer`");
$db->exec("CREATE TABLE `tb_customer` (
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
) ENGINE=MyISAM AUTO_INCREMENT=2890 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");

$custInsert = "INSERT INTO `tb_customer` VALUES 
(1,NULL,'--Pilih--',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'',NULL,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(2,NULL,'Fajar Surya Wisesa','PT','Jl Raya Cibitung - Bekasi',NULL,NULL,NULL,NULL,'','08','','Grace ','',0,NULL,0,0,'090021240','2011-01-04 00:00:00',1,4,'2024-02-16 13:58:13',NULL,'hide','show','','',1,0,0,0,0,1,0,0,0,NULL,NULL,'',NULL,'','email@gmail.com',NULL,'',NULL,'','Bpk. Grace','081234567890',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(4,NULL,'Rayi Raka Metal Industri','PT','Kawasan Kota Bukit Indah Blok A 11 No. 28',NULL,NULL,NULL,NULL,'','(026) 491-0208','','Bpk. Sardiman D.','',12,NULL,171,0,'090021240','2011-01-04 00:00:00',1,4,NULL,NULL,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(6,NULL,'Pabrik Kertas Padalarang','PT','Jl. Cihaliwung No. 181 Padalarang Kab Bandung Barat',NULL,NULL,NULL,NULL,'wahyu.ptkp@gmail.com ','(022) 680-9315','(022) 680-9284','Bpk. Wahyu Widayanto','',12,NULL,161,62,'090022291','2017-02-24 09:17:21',1,4,NULL,0000,'hide','show','','',1,0,0,0,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Bpk. Wahyu','08122334455',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(7,NULL,'PRIMKOKAS',NULL,'JL. K. H. Yasin Beji, Krakatau Junction',NULL,NULL,NULL,NULL,'','0254-392784,372289','0254-398884','Bpk. Arif Sugiyono (Manager)','',13,NULL,267,0,'090021240','2011-09-16 00:00:00',0,4,NULL,NULL,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(8,NULL,'Ahmad Solikin - Univ. Diponegoro, Jurusan Ilmu Kelautan',NULL,'Jl. Nakula Raya No. 80',NULL,NULL,NULL,NULL,'','(085) 648-9200','','Ahmad Solikin','',14,NULL,205,0,'090021240','2011-01-06 00:00:00',0,10,NULL,NULL,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(9,NULL,'Rizky Danu Prakoso, Jur. Kelautan, Univ. Diponogoro',NULL,'Jl. Nakula Raya No. 80L',NULL,NULL,NULL,NULL,'','(085) 648-9200','','Rizky Danu Prakoso','',14,NULL,205,0,'090021240','2011-01-06 00:00:00',0,10,NULL,NULL,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(10,13,'Pura Nusapersada','PT',' Jl. AKBP.R. Agil Kusumadya KM.2',NULL,33,3319,62,'','(029) 143-9636','','Jumadi','6285647347133',14,NULL,0,0,'090021240','2011-08-03 00:00:00',1,2,'2026-07-15 09:33:04',NULL,'show','show','','',1,0,0,0,1,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Jumadi','6285647347133',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(13,NULL,'Sinar Domas Textile ','PT','Jl. Raya laswi No. 51, Toblong, Majalaya',NULL,NULL,NULL,NULL,'-','(081) 222-8559','(022) 595-1880','Jajang','6289697285340',12,NULL,160,62,'090022291','2017-02-14 16:07:36',1,4,NULL,0000,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(16,20,'Polyfin Canggih','PT','Jl. Raya Rancaekek Km. 19 No. 28, Desa Cipacing, Kab Sumedang, Bandung 45363',NULL,32,3211,62,'lany.suryati@polyfincanggih.com','(022) 779-8888','(022) 779-8885','Dedi Karso','628112207003',12,NULL,175,62,'090022291','2017-02-14 14:04:03',1,4,NULL,0000,'show','show','','',1,0,0,0,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Dedi Karso','628112207003',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(17,9,'Natatex Prima','PT','Jl. Raya Rancaekek Km. 26.5, Rancaekek Kab Sumedang 40394',NULL,32,3211,62,'natatex@global.net/dedenachmad77@yahoo.com','(022) 779-8440','(022) 779-8445','Asep Rohmat','6281394194953',12,NULL,175,62,'090022291','2017-02-14 13:55:35',1,4,NULL,0000,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(18,27,'Wiska','PT','Jl. Raya Rancaekek Km. 20,9, Rancaekek',NULL,32,3211,62,'-','(022) 779-8155','(022) 891-0566','Okay','6281320578307',12,NULL,160,62,'090022291','2017-02-14 13:43:47',1,4,NULL,0000,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(19,NULL,'INSAN SANDANG INTERNUSA','PT','Jl. Raya Rancaekek Km. 22,5',NULL,NULL,NULL,NULL,'','','','Bpk. Sutedjo','',12,NULL,168,0,'090021240','2011-11-01 00:00:00',1,5,NULL,NULL,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(20,NULL,'KAHATEX SOLOKAN JERUK','PT','Jl. Raya Rancaekek, Majalaya No. 389, Bandung 40382',NULL,NULL,NULL,NULL,'-','(022) 779-8060','(022) 779-8063','Vega','6283113377398',12,NULL,160,62,'090022291','2017-02-24 09:13:28',1,4,NULL,0000,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(21,NULL,'PT. KEWALRAM INDONESIA',NULL,'Jl. Raya Rancaekek Km. 25, Rancaekek',NULL,NULL,NULL,NULL,'embroidery@kewalram.co.id','022 7794312/7791705','022 7794311/7797142','Bpk. Kabul Sungkawa','',12,NULL,160,62,'090022291','2017-02-24 09:07:16',0,4,NULL,0000,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(22,NULL,'ARTHA TRIMUSTIKA','PT','Jl. Raya Bandung - Garut Km. 28',NULL,NULL,NULL,NULL,'','','','Ibu Fanta / Bpk. Komar','',12,NULL,161,0,'090021240','2011-11-01 00:00:00',1,5,NULL,NULL,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(23,NULL,'Prima Makmur Rotokemindo','PT','JL. Raya Curug KM. 1,1 Desa Kadu Jaya, Curug 15810',NULL,NULL,NULL,NULL,'edwin@primamakmur.com','','(021) 596-1321','Edwin Aldrin','',11,NULL,152,1,'999999999','2014-12-21 19:09:26',1,3,NULL,2009,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(24,NULL,'BINTANG MITRA TEXINDO','PT','Jl. Raya Banjaran Km. 14,9',NULL,NULL,NULL,NULL,'','','','Bpk. Mulfi Syafrudin','',12,NULL,161,0,'090021240','2011-05-25 00:00:00',1,5,NULL,NULL,'hide','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(25,14,'Safilindo Permata','PT','Jl. Waas No. 39 Banjaran, Kab Bandung',NULL,32,3204,62,'022 5940123','(022) 594-0123','(022) 594-0039','Ruliyana','6283844387651',12,NULL,161,62,'090022291','2017-02-14 14:46:01',1,4,'2026-02-20 11:33:41',0000,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(26,42,'Ferinatex Jaya','PT','Jl. Cihaneut No. 16, Majalaya, Kab Bandung',NULL,32,3204,62,'wiwinwidansya@gmail.com','(022) 595-1244','(022) 595-1248','Iwan','081320688815',12,NULL,161,62,'090022291','2017-02-24 09:00:20',1,4,'2026-03-25 08:18:21',0000,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(27,15,'Tridayamas Sinar Pusaka','PT','Jl. Mekar Sari I RT 002/20, Baleendah, Kab Bandung',NULL,32,3204,62,'-','(022) 594-2773','(022) 594-2768','Yusril','6281321088408',12,NULL,160,62,'090022291','2017-02-21 11:04:03',1,4,'2024-11-06 12:36:19',0000,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(41,35,'Hakatex','PT','Jl. Moch. Toha Km. 5,6 Bandung 40255','Jl. Moch. Toha Km. 5,6',32,3273,62,'vian@hakatex.com','(022) 520-3787','(022) 522-9678','Bpk. Vian','628998241109',12,'40255',160,62,'090022291','2017-02-24 09:03:31',1,4,'2025-03-06 15:26:15',0000,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(42,50,'Aspex Kumbong','PT','Wisma Korindo, Jl. MT Haryono Kav 62, Jakarta 12780',NULL,31,3174,62,'-','','(021) 823-0682','Bpk. Sabar Sriyanto','6287808876622',12,NULL,163,62,'090022291','2017-02-24 08:43:55',1,4,'2024-09-20 10:36:09',0000,'show','show','','',1,1,0,1,1,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Bpk. Sabar Sriyanto','6287808876622',NULL,'',NULL,'',129,NULL,72,0,0,0,NULL,NULL),
(43,29,'Cipta Paperia','PT','Jl. Raya Serang Km. 76, Kragilan Serang',NULL,36,3604,62,'shanty@ciptapaperia.com','(021) 653-0678','(021) 653-0085','Bpk. Ahmad Satibi','6281382803146',0,NULL,0,62,'090022291','2017-02-24 08:48:50',1,4,NULL,0000,'show','show','','',1,0,0,0,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(54,64,'Pabrik Kertas Tjiwi Kimia Tbk.','PT','Jl. Raya Surabaya - Mojokerto Km. 44, Sidoarjo, Jawa Timur','Jl. Raya Surabaya - Mojokerto Km. 44',35,3515,62,'','(032) 136-1552','(032) 136-1615','Citra Mulya','6288801503960',15,NULL,250,0,'090021240','2011-01-20 00:00:00',1,2,'2026-01-12 08:34:38',NULL,'show','show','','',1,0,0,0,1,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Citra Mulya','6288801503960',NULL,'',NULL,'',126,NULL,6,0,0,0,NULL,NULL),
(72,72,'Pabrik Kertas Noree Indonesia','PT','Jl. Raya Babelan No.Km 7,8 , RW.8, Kebalen, Kec. Babelan, Kabupaten Bekasi, Jawa Barat 17121',NULL,32,3216,62,'-','(021) 892-1244','','Maslia','',12,NULL,173,62,'090022291','2017-02-24 09:20:08',1,4,'2024-05-20 02:09:36',0000,'show','show','','',1,1,0,1,0,0,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'',NULL,'',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(76,275,'Sopanusa Tissue & Packaging Sarana Sukses','PT','Ds. Manduromangggunggajah',NULL,35,3516,62,'','(031) 371-5828','(031) 376-5081','Ibu Ciendrawati','',16,NULL,239,0,'090021240','2011-03-04 00:00:00',1,3,NULL,NULL,'show','show','','',1,0,0,1,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Ibu Ciendrawati','081233445566',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(82,NULL,'ITB',NULL,'Jl. Ganesha No. 10, Bandung',NULL,NULL,NULL,NULL,'','','','Hary Pratama Suhendri','',12,NULL,161,0,'090021240','2011-04-19 00:00:00',2,23,NULL,NULL,'hide','show','','',1,0,0,1,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Hary Pratama','08122334455',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(140,175,'Papertech Indonesia','PT','Jl. Raya Cipeundeuy Km. 1 Kec. Cipendeuy Kab. Subang',NULL,32,3213,62,'','(026) 071-0645','(026) 071-0644','Bapak Ketut','',12,NULL,173,0,'090021240','2011-06-21 00:00:00',1,2,'2024-07-16 06:55:01',NULL,'show','show','','',1,1,0,1,1,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Bpk. Ketut','081333444555',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL),
(145,219,'Surya Pamenang','PT','Jl. Raya Kediri Kertosono Km. 7, Kec. Gampengrejo, Kabupaten Kediri',NULL,35,3506,62,'','(035) 468-1360','(035) 468-1591','Roy Hari A','',16,NULL,229,0,'090021240','2011-07-20 00:00:00',1,2,'2025-09-09 09:52:13',NULL,'show','show','','',1,0,0,0,0,1,1,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Roy Hari A','081234567899',NULL,'',NULL,'',126,NULL,56,0,0,0,NULL,NULL),
(146,NULL,'BALITTAS (Badan Penelitian Tanaman Tembakau dan Serat)',NULL,'Jl. Raya Karangploso Km. 4, PO Box 199',NULL,NULL,NULL,NULL,'','0341-491447','0341-485121','Bpk. Untung Setyo Budi','',3,NULL,210,0,'090021240','2011-06-27 00:00:00',0,9,NULL,NULL,'hide','show','','',1,0,0,0,0,1,0,0,0,NULL,NULL,'',NULL,'',NULL,NULL,'',NULL,'','Bpk. Untung','0341-491447',NULL,'',NULL,'',0,NULL,0,0,0,0,NULL,NULL)";
$db->exec($custInsert);
echo "tb_customer successfully populated.\n";

// 2. Structure and Data for tb_arsipuser
$db->exec("DROP TABLE IF EXISTS `tb_arsipuser`");
$db->exec("CREATE TABLE `tb_arsipuser` (
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
) ENGINE=MyISAM AUTO_INCREMENT=168168169 DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT");

$userInsert = "INSERT INTO `tb_arsipuser` VALUES 
(1,'empunyadmin','398660959c7017252ce1952de852bb4d',1,200,'HK Administrator','N','628156006227','all','PHOTO_20141219_174547.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin','penerimaan_order','admin',NULL,'admin','admin',NULL,'admin','penerimaan_order','superadmin','admin'),
(2,'lov3sonyb4nget','77222594003e4421f19cb27f908b86e3',1,32,'Sonny Kurnia Wirawan','N','6285659088926','pjt','sonnykurniawirawan.jpg','Y',NULL,1,1,NULL,NULL,NULL,NULL,NULL,NULL,'penerimaan',NULL,NULL,NULL,NULL,NULL,NULL),
(3,'rinaPIs2r','7d4871dbbe5e0a859d5fd38098a582f5',1,41,'Rina Masriani','N','6281320635632','srs','rina.jpg','N',NULL,170,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(4,'ningrum','1a3b01e75a6877968164fa4f3f93d88a',1,160,'Nurhadiningrum Yuniastuti','N','6285659220865','tu','ningrum.jpg','Y',NULL,1,1,NULL,NULL,NULL,'keuangan','keuangan','651830','penerimaan',NULL,NULL,NULL,'keuangan',NULL,NULL),
(5,'sekrePIs1rq','127847a499e113936df986ba878f6247',1,2,'Rima Yunia','N','6282216363779',NULL,'rima.jpeg','N',NULL,2000,NULL,'user_atk',103,NULL,NULL,NULL,'839990',NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(7,'nikibola07','b2e34f845d894a17284677e7a81b58cc',1,13,'Niki Gumelar','Y','6281901105898','paskal','niki.jpg','Y',NULL,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(8,'koryJY0qt','a3f4c5ffb955192a5ceb735ecb8d59ff',1,23,'Kory Pranita Andriyati','N','628122092397','paskal','qory.jpg','N',NULL,102,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'auditor',NULL,NULL),
(9,'srihani16','c4185ce42c877d83a9a9dfc7c6d99210',1,14,'Sri Hani','N','6287741608011','paskal','srihani2.jpg','N',NULL,1,1,'user_lab',26,NULL,NULL,NULL,'556013',NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(10,'nenalia090','ab674c50ac60d677ff2ce8afad72309c',1,13,'Nena Andrina Restu','N','6281220844202','paskal','nena.jpg','Y',NULL,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(12,'yogiGu0zM','398660959c7017252ce1952de852bb4d',1,15,'Yogi Afiyan','N','6281261540423','paskal','yogi.jpg','Y',NULL,1,1,NULL,NULL,NULL,NULL,'tim_inspeksi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(23,'darmawanKoMqo','f142364d1d2aba2804a2b4c1a5e9d393',1,20,'Darmawan','N','628122189667','paskal','darmawan.jpeg','N',NULL,100,NULL,'ketua_tim',NULL,NULL,'teknisi',NULL,NULL,NULL,NULL,NULL,NULL,'ketua_tim','pic',NULL),
(26,'yoveniN40uq','caf0e10f70c50eac6a0fa7e3f2534d16',1,12,'Yoveni Yanimar Fitri','N','628126725987','srs','yoveni.jpg','Y','1#1#1#1#1#1',140,NULL,'ketua_tim',NULL,NULL,NULL,'tim_inspeksi','862552',NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(27,'kris','17b074513312cffde900f0a53b2a4e6a',1,182,'Kristaufan Joko Pramono','N','628157179119','srs','kristaufan.jpg','N',NULL,91,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(29,'hendyksw','398660959c7017252ce1952de852bb4d',1,37,'Hendy Kuswaendi','N','628156006227','pjt','hendy.jpg','N',NULL,260,NULL,'ketua_tim',NULL,'bmn',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ketua_tim','pic',NULL),
(36,'faridhKIMqp','14f7a701e8b5fa15871d25c614d1b122',1,152,'Faridh Hendriana','N','6282121200985','tu','faridh.jpg','N',NULL,50,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(37,'atinL5Yx7','e9d5893a720e63f73e00dfe48c4513b8',1,160,'Ati Nurhayati','N','6281321180510','tu','ati.jpg','Y',NULL,30,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,'admin',NULL,NULL,NULL,NULL,'pic',NULL),
(58,'hendrJoc2q','4846ef474cc7f39013f0e129984e88f3',1,70,'Hendro Risdianto','N','628112205515','SRS','hendro.jpg','N',NULL,90,NULL,'ketua_tim',NULL,NULL,NULL,'tim_inspeksi',NULL,NULL,NULL,NULL,NULL,'auditor',NULL,NULL),
(59,'teddyOoc8q','40ae93308f2bc17adf4a76b17de88fce',1,42,'Teddy Kardiansyah','N','6281320370070','SRS','teddy.jpg','N',NULL,180,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(61,'andritr3','23630c4c716e1e5b1982c3e075a19b0e',1,41,'Andri Taufick Rizaluddin','N','628179296562','SRS','andri.jpg','N',NULL,190,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(70,'kabb','7fc3f9d8c9b7965424aa3dc06ab4185d1',0,3,'Hendra Yetty','N','628158043538',NULL,'hendrayetti.jpg','Y',NULL,2000,NULL,'pimpinan',NULL,NULL,'penerimaan_order','show',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(73,'jokopJI0zo','98f369ea1c54fcf4662d876db3c1764d',1,151,'Joko Pratomo','N','6281214519188','paskal','jokop.jpeg','Y',NULL,20,NULL,'kabag_tu',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(102,'devi102','c1ea5709632dadfce77dcc90c6864245',1,161,'Devi Mei Hana Nurfiyah','N','6281331107836',NULL,'devi2.jpg','N',NULL,31,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'admin',NULL,NULL,NULL,NULL,'pic',NULL),
(103,'dion','c807012874884044379626720d38dcd3',1,161,'Dion Pratama','N','6282120652195',NULL,'dion2.jpg','N',NULL,60,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(149,'doroadmind','398660959c7017252ce1952de852bb4d',1,200,'FA Administrator','N','6281283391300','all','fandyachmad.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin','admin','admin','746919','admin','admin',NULL,'admin','keuangan','pic',NULL),
(150,'doroadmint','398660959c7017252ce1952de852bb4d',1,200,'Ade K. Hidayat','N','6285221220100','all','papaanr.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin','admin','admin','801896','admin','admin',NULL,NULL,'tim_mitra',NULL,NULL),
(175,'kabbs','5e5bcc2c921acb32f667f20337246072',1,3,'Dodiet Prasetyo','N','62817217216',NULL,'doditisback.png','Y',NULL,2000,1,'pimpinan',NULL,NULL,'penerimaan_order','show',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(9001,'andri_selulosa','" . password_hash('password123', PASSWORD_BCRYPT) . "',1,41,'Pak Andri Taufick (Demo)','N','628179296562','srs','andri.jpg','Y',NULL,190,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(9002,'rina_lingkungan','" . password_hash('password123', PASSWORD_BCRYPT) . "',1,41,'Bu Rina Masriani (Demo)','N','6281320635632','srs','rina.jpg','Y',NULL,170,NULL,'ketua_tim',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(9003,'admin_order','" . password_hash('password123', PASSWORD_BCRYPT) . "',1,152,'Petugas Order Layanan (Demo)','N','6282121200985','tu','faridh.jpg','Y',NULL,50,NULL,'admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pic',NULL),
(9004,'pejabat_balai','" . password_hash('password123', PASSWORD_BCRYPT) . "',1,3,'Kepala Balai / PPK BLU (Demo)','N','62817217216',NULL,'doditisback.png','Y',NULL,2000,1,'pimpinan',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(9005,'admin_kontrak','" . password_hash('password123', PASSWORD_BCRYPT) . "',1,200,'Staf Adm Kerjasama PKS (Demo)','N','6285221220100','all','papaanr.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(9006,'fajriasa','" . password_hash('fajri123', PASSWORD_BCRYPT) . "',1,200,'Fajriasa Administrator (Demo)','N','628156006227','all','PHOTO_20141219_174547.jpg','Y',NULL,2000,NULL,'admin',NULL,'admin','admin','admin',NULL,'admin','admin',NULL,'admin','penerimaan_order','superadmin','admin')";

$db->exec($userInsert);
echo "tb_arsipuser successfully populated with production records + demo aliases.\n";

// 3. Populate opti_user_map mapping table
$db->exec("DELETE FROM `opti_user_map`");
$mapInsert = "INSERT INTO `opti_user_map` (`id_user`, `jenis_layanan_opti`, `role_opti`, `is_active`) VALUES
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
(9006, 'semua', 'superadmin', 1)";

$db->exec($mapInsert);
echo "opti_user_map successfully populated.\n";

echo "ALL REAL BALAI MASTER RECORDS IMPORTED AND LINKED SUCCESSFULLY!\n";
