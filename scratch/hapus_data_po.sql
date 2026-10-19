-- =====================================================================
-- HAPUS DATA PO (mulai dari awal)  --  database: silopti2026
--
-- Yang DIHAPUS : semua PO beserta turunannya (catatan revisi, chat, perubahan PPK,
--                tautan PO-order pengujian, penghitung nomor PO, jejak audit tahap 7).
-- Yang AMAN    : tb_arsipsurat (ada di database sil2020, script ini tidak menyentuhnya),
--                order, penawaran, pembayaran, customer, user, master Tim Pelaksana,
--                master Jabatan Pelaksana, penandatangan, push_subscriptions.
--
-- CARA PAKAI
--   1. BACKUP dulu, lewat CMD / terminal Laragon (bukan di SQL):
--        mysqldump -u root -p silopti2026 > C:\backup_silopti2026_sebelum_hapus_po.sql
--   2. Buka phpMyAdmin / HeidiSQL, pilih database silopti2026.
--   3. Jalankan BAGIAN A dulu, lihat jumlah barisnya.
--   4. Kalau sudah yakin, jalankan BAGIAN B.
--   5. Jalankan BAGIAN C untuk memastikan semuanya 0.
--
-- PENTING: penghapusan tidak bisa dibatalkan. Tanpa backup, data tidak bisa kembali.
-- =====================================================================

USE silopti2026;

-- ---------------------------------------------------------------------
-- BAGIAN A : lihat isi sekarang (tidak mengubah apa pun)
-- ---------------------------------------------------------------------
SELECT 'po_kegiatan'          AS tabel, COUNT(*) AS jumlah FROM po_kegiatan
UNION ALL SELECT 'po_revisi_catatan',    COUNT(*) FROM po_revisi_catatan
UNION ALL SELECT 'po_chat',              COUNT(*) FROM po_chat
UNION ALL SELECT 'po_ppk_perubahan',     COUNT(*) FROM po_ppk_perubahan
UNION ALL SELECT 'po_pengujian_order',   COUNT(*) FROM po_pengujian_order
UNION ALL SELECT 'po_nomor_urut',        COUNT(*) FROM po_nomor_urut
UNION ALL SELECT 'opti_stage_audit tahap 7 (PO)', COUNT(*) FROM opti_stage_audit WHERE tahap = 7;


-- ---------------------------------------------------------------------
-- BAGIAN B : hapus
-- (TRUNCATE sekaligus mengembalikan nomor ID ke 1)
-- Kalau muncul error "Table ... doesn't exist", tabel itu memang belum pernah dibuat;
-- lewati barisnya dan lanjutkan ke baris berikutnya.
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE po_revisi_catatan;     -- catatan revisi Ketua Tim / PPK BLU
TRUNCATE TABLE po_chat;               -- chat + riwayat sistem (isi tab Alur)
TRUNCATE TABLE po_ppk_perubahan;      -- jejak perubahan saat revisi PPK BLU
TRUNCATE TABLE po_pengujian_order;    -- tautan PO pengujian <-> order (arsip surat jadi bebas dipilih lagi)
TRUNCATE TABLE po_nomor_urut;         -- penghitung nomor PO: nomor PO mulai lagi dari 1
TRUNCATE TABLE po_kegiatan;           -- PO-nya sendiri

-- jejak audit tahap 7 (Penerimaan Fisik Sampel & SPM / PO) milik order-order lama
DELETE FROM opti_stage_audit WHERE tahap = 7;

SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------------------
-- BAGIAN C : cek hasil (semua harus 0)
-- ---------------------------------------------------------------------
SELECT 'po_kegiatan'          AS tabel, COUNT(*) AS jumlah FROM po_kegiatan
UNION ALL SELECT 'po_revisi_catatan',    COUNT(*) FROM po_revisi_catatan
UNION ALL SELECT 'po_chat',              COUNT(*) FROM po_chat
UNION ALL SELECT 'po_ppk_perubahan',     COUNT(*) FROM po_ppk_perubahan
UNION ALL SELECT 'po_pengujian_order',   COUNT(*) FROM po_pengujian_order
UNION ALL SELECT 'po_nomor_urut',        COUNT(*) FROM po_nomor_urut
UNION ALL SELECT 'opti_stage_audit tahap 7 (PO)', COUNT(*) FROM opti_stage_audit WHERE tahap = 7;


-- ---------------------------------------------------------------------
-- OPSIONAL : modul PO lama (tabel `po`, sebelum po_kegiatan).
-- Hapus tanda komentar HANYA kalau data PO lama itu juga mau dibuang.
-- Catatan: kontrak_pks (kontrak PKS) menempel ke tabel `po` lama, jadi ikut terhapus.
-- ---------------------------------------------------------------------
-- SET FOREIGN_KEY_CHECKS = 0;
-- TRUNCATE TABLE po_rincian_anggaran;
-- TRUNCATE TABLE po_log_status;
-- TRUNCATE TABLE opti_po_jadwal_kerja;
-- TRUNCATE TABLE opti_po_sop_progress;
-- TRUNCATE TABLE kontrak_pks;
-- TRUNCATE TABLE `po`;
-- SET FOREIGN_KEY_CHECKS = 1;
