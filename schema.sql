-- Skrip Inisialisasi Database Mini OPTI Tracker
CREATE DATABASE IF NOT EXISTS mini_opti_tracker;
USE mini_opti_tracker;

-- 1. Tabel Klien (Master Data Mitra/Perusahaan)
CREATE TABLE IF NOT EXISTS klien (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_perusahaan VARCHAR(150) NOT NULL,
    pic VARCHAR(100),
    alamat TEXT,
    telepon VARCHAR(30),
    email VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Order Layanan (Permintaan Layanan dari Klien)
CREATE TABLE IF NOT EXISTS order_layanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    klien_id INT NOT NULL,
    judul_kegiatan VARCHAR(200) NOT NULL,
    deskripsi TEXT,
    tanggal_masuk DATE NOT NULL,
    status ENUM('baru','disetujui','ditolak') DEFAULT 'baru',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klien_id) REFERENCES klien(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel PO (Petunjuk Operasional / Dokumen Kerja Hasil Approval)
CREATE TABLE IF NOT EXISTS po (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    nomor_po VARCHAR(50) UNIQUE NOT NULL,   -- format: {urut}/PO/LATIHAN/{bulan_romawi}/{tahun}
    biaya DECIMAL(15,2) DEFAULT 0,
    status ENUM('proposal','kontrak','po_terbit','distribusi','selesai') DEFAULT 'proposal',
    tanggal_target DATE,
    tanggal_realisasi DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES order_layanan(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Log Status PO (Audit Trail Perubahan Status PO)
CREATE TABLE IF NOT EXISTS po_log_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    status_lama VARCHAR(30),
    status_baru VARCHAR(30) NOT NULL,
    catatan VARCHAR(255),
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES po(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
