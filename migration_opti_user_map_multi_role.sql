-- ============================================================================
-- Migrasi tabel opti_user_map ke skema multi-layanan / multi-role
-- Kode aplikasi terbaru memakai kolom: role, jenis_layanan, akses_opti,
-- akses_po, status_tampil. Skema lama hanya punya: role_opti, jenis_layanan_opti.
-- Kolom lama TIDAK dihapus (hanya dibuat boleh kosong), jadi data lama aman.
-- Jalankan sekali di phpMyAdmin (database OPTI Tracker / sekretariat).
-- Kalau ada pesan "Duplicate column name", berarti kolom itu sudah ada: lewati.
-- ============================================================================

ALTER TABLE `opti_user_map`
  ADD COLUMN `role` VARCHAR(50) NULL AFTER `id_user`,
  ADD COLUMN `jenis_layanan` VARCHAR(30) NOT NULL DEFAULT 'semua' AFTER `role`,
  ADD COLUMN `akses_opti` TINYINT(1) NOT NULL DEFAULT 1 AFTER `jenis_layanan`,
  ADD COLUMN `akses_po` TINYINT(1) NOT NULL DEFAULT 1 AFTER `akses_opti`,
  ADD COLUMN `status_tampil` ENUM('show','hide') NOT NULL DEFAULT 'show' AFTER `is_active`;

-- Salin data lama ke kolom baru
UPDATE `opti_user_map`
   SET `role` = `role_opti`,
       `jenis_layanan` = COALESCE(`jenis_layanan_opti`, 'semua')
 WHERE `role` IS NULL;

-- Kolom lama jadi boleh kosong (supaya INSERT baru yang hanya isi kolom baru tidak error)
ALTER TABLE `opti_user_map`
  MODIFY `role_opti` VARCHAR(50) NULL,
  MODIFY `jenis_layanan_opti` VARCHAR(30) NULL DEFAULT 'semua';

-- Satu user boleh punya beberapa baris (multi-layanan / multi-role): buang UNIQUE id_user
ALTER TABLE `opti_user_map` DROP INDEX `id_user`, ADD INDEX `idx_id_user` (`id_user`);
