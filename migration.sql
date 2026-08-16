-- Skrip Inisialisasi Database OPTI Tracker BBSPJI Selulosa
CREATE DATABASE IF NOT EXISTS mini_opti_tracker;
USE mini_opti_tracker;

-- Drop tables in reverse order of foreign keys
DROP TABLE IF EXISTS kontrak_pks;
DROP TABLE IF EXISTS po_log_status;
DROP TABLE IF EXISTS po;
DROP TABLE IF EXISTS order_layanan;
DROP TABLE IF EXISTS klien;
DROP TABLE IF EXISTS user;

-- 1. Tabel User (RBAC & Login Security)
CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin_order', 'ketua_tim', 'pejabat', 'tim_kerja', 'admin_kontrak', 'superadmin') NOT NULL DEFAULT 'tim_kerja',
    jenis_layanan ENUM('selulosa', 'lingkungan', 'all') DEFAULT 'all', -- Khusus ketua_tim atau all
    is_active TINYINT(1) DEFAULT 1,
    foto_profil VARCHAR(255) NULL,
    last_login DATETIME NULL,
    failed_login_count INT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Users (Password: password123, fajri123 untuk fajriasa)
INSERT INTO user (nama_lengkap, username, password_hash, role, jenis_layanan) VALUES
('Petugas Penerima Order', 'admin_order', '$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K', 'admin_order', 'all'),
('Pak Andri T. (OPTI Selulosa)', 'ketua_selulosa', '$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K', 'ketua_tim', 'selulosa'),
('Bu Rina M. (OPTI Lingkungan)', 'ketua_lingkungan', '$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K', 'ketua_tim', 'lingkungan'),
('Kepala Balai / Pejabat Penandatangan', 'pejabat_opti', '$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K', 'pejabat', 'all'),
('Tim Mitra Industri / Kerja', 'tim_kerja_opti', '$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K', 'tim_kerja', 'all'),
('Staf Admin Kontrak', 'admin_kontrak', '$2y$10$XpPJjUlOSY6vVOy.o5cPRe9CZGBZd7334LAKe2JdatoKtedissw0K', 'admin_kontrak', 'all'),
('Fajriasa Super Admin', 'fajriasa', '$2y$10$OnurP/9DXA/MahHk.lmit..Gpn7uuBmzOs56ls5PlC9YNNkHmM80.', 'superadmin', 'all');

-- 2. Tabel Klien (Master Data Mitra/Perusahaan)
CREATE TABLE klien (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_perusahaan VARCHAR(150) NOT NULL,
    pic VARCHAR(100),
    alamat TEXT,
    telepon VARCHAR(30),
    email VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Klien
INSERT INTO klien (nama_perusahaan, pic, alamat, telepon, email) VALUES
('PT Selulosa Makmur Sejahtera', 'Aji Ajian', 'Tasikmalaya', '081234567890', 'aji@selulosa.com'),
('PT Selulosa Bandung', 'Budi Budian', 'Jln Balongkanyun no 12', '081234567891', 'budi@selulosabdg.com');

-- 3. Tabel Order Layanan (Permintaan Layanan OPTI)
CREATE TABLE order_layanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    klien_id INT NOT NULL,
    nomor_order VARCHAR(50) UNIQUE NOT NULL,
    tanggal_masuk DATE NOT NULL,
    judul_kegiatan VARCHAR(200) NOT NULL,
    jenis_layanan ENUM('selulosa', 'lingkungan') NOT NULL,
    jumlah_pekerjaan VARCHAR(100) NOT NULL, -- Jumlah pekerjaan/alat
    estimasi_biaya DECIMAL(15,2) DEFAULT 0,
    deskripsi TEXT,
    status ENUM('baru', 'disetujui', 'ditolak') DEFAULT 'baru',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klien_id) REFERENCES klien(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Order Layanan
INSERT INTO order_layanan (klien_id, nomor_order, tanggal_masuk, judul_kegiatan, jenis_layanan, jumlah_pekerjaan, estimasi_biaya, deskripsi, status) VALUES
(1, 'ORD-001', '2026-08-14', 'Pengujian Mutu Kertas Pulp PT SMS', 'selulosa', '10 sampel pulp', 15000000.00, 'Pengujian kadar air, ketebalan, dan daya regang kertas pulp.', 'disetujui'),
(2, 'ORD-002', '2026-08-12', 'Analisis Emisi Cerobong Industri', 'lingkungan', '2 titik cerobong', 25000000.00, 'Pengukuran kadar NOx, SOx, dan partikulat pada gas buang.', 'baru');

-- 4. Tabel PO (Petunjuk Operasional & Map Kendali)
CREATE TABLE po (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    nomor_po VARCHAR(50) UNIQUE NOT NULL, -- format: {urut}/PO/BBSPJIS/{bulan_romawi}/{tahun}
    tim_kerja VARCHAR(150) NULL,
    status ENUM('belum_upload', 'sudah_upload', 'on_proses', 'kembali_selesai') DEFAULT 'belum_upload',
    tanggal_keluar DATE NULL,
    tanggal_kembali DATE NULL, -- Revisi/kembali
    target_mulai DATE NULL,
    target_selesai DATE NULL,
    realisasi_selesai DATE NULL,
    
    -- Map Kendali Verifikasi & Validasi Berjenjang
    app_proposal TINYINT(1) DEFAULT 0,
    app_proposal_date DATETIME NULL,
    app_kontrak TINYINT(1) DEFAULT 0,
    app_kontrak_date DATETIME NULL,
    
    app_po_adm TINYINT(1) DEFAULT 0,
    app_po_adm_date DATETIME NULL,
    app_po_mitra TINYINT(1) DEFAULT 0,
    app_po_mitra_date DATETIME NULL,
    app_po_ppk TINYINT(1) DEFAULT 0,
    app_po_ppk_date DATETIME NULL,
    app_po_kabag TINYINT(1) DEFAULT 0,
    app_po_kabag_date DATETIME NULL,
    
    app_dist_tu TINYINT(1) DEFAULT 0,
    app_dist_tu_date DATETIME NULL,
    app_dist_kepeg TINYINT(1) DEFAULT 0,
    app_dist_kepeg_date DATETIME NULL,
    app_dist_keu TINYINT(1) DEFAULT 0,
    app_dist_keu_date DATETIME NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES order_layanan(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed PO untuk Order 1 (yang berstatus disetujui)
INSERT INTO po (order_id, nomor_po, status, tim_kerja, target_mulai, target_selesai, app_proposal, app_proposal_date) VALUES
(1, '01/PO/BBSPJIS/VIII/2026', 'belum_upload', 'Tim Penguji Pulp - Pak Andri T.', '2026-08-20', '2026-08-30', 1, '2026-08-14 15:00:00');

-- 5. Tabel Log Status PO (Audit Trail)
CREATE TABLE po_log_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    status_lama VARCHAR(50),
    status_baru VARCHAR(50) NOT NULL,
    catatan VARCHAR(255),
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Log PO
INSERT INTO po_log_status (po_id, status_lama, status_baru, catatan, tanggal) VALUES
(1, NULL, 'belum_upload', 'PO otomatis dibuat dari Order Layanan yang telah disetujui.', '2026-08-14 15:00:00');

-- 6. Tabel Kontrak PKS (Perjanjian Kerja Sama)
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
    nilai_kontrak DECIMAL(15,2) DEFAULT 0,
    ketentuan_pembayaran TEXT NULL,
    tanggal_ttd DATE NULL,
    status_ttd ENUM('belum', 'proses', 'selesai') DEFAULT 'belum',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
