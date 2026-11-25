-- migration_pks_nomor_per_tahun.sql
-- Nomor urut PKS mulai lagi dari 1 tiap tahun (seperti Nomor PO): satu angka urut hanya boleh dipakai SEKALI per tahun,
-- bulan tidak dihitung. File ini mengganti indeks unik tabel pks_nomor_urut dari (tahun, bulan, urut) menjadi (tahun, urut).
-- Cara pakai: phpMyAdmin, tab SQL, tempel isi file ini, lalu Kirim. Aman dijalankan ulang.

USE `silopti2026`;

-- ---------------------------------------------------------------------------
-- 3) Indeks unik nomor: (tahun, urut). Nomor urut PKS mulai lagi dari 1 tiap tahun (seperti Nomor PO), jadi urut yang sama
--    hanya boleh kalau tahunnya beda; bulan tidak dihitung. Menggantikan indeks (tahun, bulan, urut) dari versi sebelumnya.
--    Aman dijalankan ulang. Kalau ADA nomor lama yang urut + tahunnya kembar (beda bulan), indeks baru TIDAK dibuat dan
--    daftar yang kembar ditampilkan di bawah: ubah dulu nomor urut salah satunya, lalu jalankan file ini lagi.
-- ---------------------------------------------------------------------------
SET @ada_bulan := (SELECT COUNT(*) FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = 'silopti2026' AND TABLE_NAME = 'pks_nomor_urut' AND INDEX_NAME = 'uniq_pks_urut_bulan_tahun');
SET @ada_baru := (SELECT COUNT(*) FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = 'silopti2026' AND TABLE_NAME = 'pks_nomor_urut' AND INDEX_NAME = 'uniq_pks_tahun_urut');
SET @kembar := (SELECT COUNT(*) FROM (SELECT 1 FROM `pks_nomor_urut` GROUP BY `tahun`, `nomor_urut` HAVING COUNT(*) > 1) x);

-- Nomor yang kembar (kalau ada)
SELECT n.`id`, n.`detail_nomor`, n.`nomor_urut`, n.`bulan`, n.`tahun`
  FROM `pks_nomor_urut` n
  JOIN (SELECT `tahun`, `nomor_urut` FROM `pks_nomor_urut` GROUP BY `tahun`, `nomor_urut` HAVING COUNT(*) > 1) d
    ON d.`tahun` = n.`tahun` AND d.`nomor_urut` = n.`nomor_urut`
 ORDER BY n.`tahun`, n.`nomor_urut`, n.`bulan`;

-- Tambah indeks baru dulu (hanya kalau belum ada dan tidak ada yang kembar)
SET @sql_baru := IF(@ada_baru = 0 AND @kembar = 0,
                    'ALTER TABLE `pks_nomor_urut` ADD UNIQUE KEY `uniq_pks_tahun_urut` (`tahun`, `nomor_urut`)',
                    IF(@ada_baru > 0, 'SELECT ''indeks (tahun, urut) sudah ada'' AS info',
                       'SELECT ''DILEWATI: ada nomor kembar (lihat daftar di atas), ubah urutnya lalu jalankan lagi'' AS info'));
PREPARE stmt_baru FROM @sql_baru;
EXECUTE stmt_baru;
DEALLOCATE PREPARE stmt_baru;

-- Baru buang indeks (tahun, bulan, urut) yang lama, hanya kalau indeks baru sudah terpasang
SET @ada_baru2 := (SELECT COUNT(*) FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = 'silopti2026' AND TABLE_NAME = 'pks_nomor_urut' AND INDEX_NAME = 'uniq_pks_tahun_urut');
SET @sql_lama := IF(@ada_bulan > 0 AND @ada_baru2 > 0,
                    'ALTER TABLE `pks_nomor_urut` DROP INDEX `uniq_pks_urut_bulan_tahun`',
                    'SELECT ''indeks lama tidak perlu dibuang'' AS info');
PREPARE stmt_lama FROM @sql_lama;
EXECUTE stmt_lama;
DEALLOCATE PREPARE stmt_lama;

SHOW INDEX FROM `pks_nomor_urut`;
