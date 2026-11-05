-- migration_pks_kontrak.sql
-- Tabel modul Kontrak (PKS) untuk PksKontrakController:
--   1. pks_nomor_urut : master nomor PKS otomatis "B/<urut>/BBSPJIS/HK/<romawi>/<tahun>" (pola sama dengan po_nomor_urut)
--   2. pks_kontrak    : satu PO satu kontrak (po_id unik); kolom id_nomor_urut menunjuk baris pks_nomor_urut-nya
-- Aman dijalankan ulang: tabel dibuat hanya kalau belum ada, kolom id_nomor_urut ditambahkan hanya kalau belum ada.
-- Cara pakai: phpMyAdmin, tab SQL (tidak harus pilih database dulu), tempel isi file ini, lalu Kirim.

USE `silopti2026`;

-- ---------------------------------------------------------------------------
-- 1) Master nomor urut PKS (satu baris per nomor yang diterbitkan; urut berlanjut sepanjang TAHUN, ganti tahun -> 1 lagi)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pks_nomor_urut` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_urut`   INT UNSIGNED NOT NULL,
  `bulan`        TINYINT UNSIGNED NOT NULL,
  `tahun`        SMALLINT UNSIGNED NOT NULL,
  `detail_nomor` VARCHAR(60) NOT NULL,
  `updated_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pks_tahun_urut` (`tahun`, `nomor_urut`),
  UNIQUE KEY `uniq_pks_detail_nomor` (`detail_nomor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) Tabel kontrak
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pks_kontrak` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_id`               INT UNSIGNED NOT NULL,
  `status`              ENUM('draft','selesai') NOT NULL DEFAULT 'draft',
  `nomor_pks`           VARCHAR(120) NOT NULL DEFAULT '',
  `id_nomor_urut`       INT UNSIGNED NULL,
  `nomor_urut`          VARCHAR(30)  NOT NULL DEFAULT '',
  `nomor_pihak_pertama` VARCHAR(120) NOT NULL DEFAULT '',
  `tanggal_perjanjian`  DATE NULL,
  `perusahaan`          VARCHAR(255) NOT NULL DEFAULT '',
  `alamat_perusahaan`   VARCHAR(1000) NOT NULL DEFAULT '',
  `ttd_nama`            VARCHAR(150) NOT NULL DEFAULT '',
  `ttd_jabatan`         VARCHAR(150) NOT NULL DEFAULT '',
  `judul`               VARCHAR(1000) NOT NULL DEFAULT '',
  `ruang_lingkup`       TEXT NULL,
  `tgl_mulai`           DATE NULL,
  `tgl_selesai`         DATE NULL,
  `biaya`               DECIMAL(18,2) NOT NULL DEFAULT 0,
  `sampel`              TINYINT(1) NOT NULL DEFAULT 0,
  `up_nama`             VARCHAR(150) NOT NULL DEFAULT '',
  `up_jabatan`          VARCHAR(150) NOT NULL DEFAULT '',
  `alamat_notif`        VARCHAR(1000) NOT NULL DEFAULT '',
  `telepon`             VARCHAR(60)  NOT NULL DEFAULT '',
  `whatsapp`            VARCHAR(60)  NOT NULL DEFAULT '',
  `email`               VARCHAR(150) NOT NULL DEFAULT '',
  `kepala_nama`         VARCHAR(150) NOT NULL DEFAULT '',
  `kepala_jabatan`      VARCHAR(150) NOT NULL DEFAULT '',
  `kepala_alamat`       VARCHAR(1000) NOT NULL DEFAULT '',
  `ked_up_nama`         VARCHAR(150) NOT NULL DEFAULT '',
  `ked_up_jabatan`      VARCHAR(150) NOT NULL DEFAULT '',
  `ked_telepon`         VARCHAR(60)  NOT NULL DEFAULT '',
  `ked_wa`              VARCHAR(60)  NOT NULL DEFAULT '',
  `ked_email`           VARCHAR(150) NOT NULL DEFAULT '',
  `logo_file`           VARCHAR(255) NULL,
  `logo_mime`           VARCHAR(100) NULL,
  `created_by`          INT UNSIGNED NULL,
  `updated_by`          INT UNSIGNED NULL,
  `selesai_at`          DATETIME NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pks_po` (`po_id`),
  KEY `idx_pks_status` (`status`),
  KEY `idx_pks_nomor` (`nomor_pks`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kalau tabel pks_kontrak SUDAH ada dari migrasi sebelumnya (tanpa kolom id_nomor_urut), tambahkan kolomnya.
SET @ada_kolom := (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = 'silopti2026' AND TABLE_NAME = 'pks_kontrak' AND COLUMN_NAME = 'id_nomor_urut');
SET @sql_tambah := IF(@ada_kolom = 0,
                      'ALTER TABLE `pks_kontrak` ADD COLUMN `id_nomor_urut` INT UNSIGNED NULL AFTER `nomor_pks`',
                      'SELECT ''kolom id_nomor_urut sudah ada'' AS info');
PREPARE stmt_tambah FROM @sql_tambah;
EXECUTE stmt_tambah;
DEALLOCATE PREPARE stmt_tambah;

-- ---------------------------------------------------------------------------
-- OPSIONAL: lanjutkan dari nomor PKS yang sudah dipakai manual tahun ini.
-- Contoh: nomor terakhir yang sudah terbit B/40/BBSPJIS/HK/X/2026, supaya nomor berikutnya otomatis 41.
-- Hapus tanda "-- " di depan baris INSERT di bawah, sesuaikan angkanya, lalu jalankan SEKALI saja.
-- ---------------------------------------------------------------------------
-- INSERT INTO `pks_nomor_urut` (`nomor_urut`, `bulan`, `tahun`, `detail_nomor`, `updated_at`)
-- VALUES (40, 10, 2026, 'B/40/BBSPJIS/HK/X/2026', NOW());

-- Cek hasilnya: harus muncul dua baris (pks_kontrak dan pks_nomor_urut).
SHOW TABLES LIKE 'pks_%';
