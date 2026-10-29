-- Jabatan Pelaksana PO per layanan (divisi).
-- Aplikasi menjalankan ini otomatis saat halaman Master dibuka. Pakai file ini hanya kalau user MySQL tidak punya hak ALTER.
ALTER TABLE po_jabatan_pelaksana ADD COLUMN layanan VARCHAR(30) NOT NULL DEFAULT '' AFTER id;
ALTER TABLE po_jabatan_pelaksana DROP INDEX uq_jabatan_nama, ADD UNIQUE KEY uq_jabatan_layanan_nama (layanan, nama);

-- salin jabatan lama (layanan kosong) ke semua layanan, lalu hapus baris lamanya
INSERT IGNORE INTO po_jabatan_pelaksana (layanan, nama, aktif)
SELECT l.k, j.nama, j.aktif
  FROM po_jabatan_pelaksana j
  JOIN (SELECT 'selulosa' AS k UNION SELECT 'lingkungan' UNION SELECT 'pengujian' UNION SELECT 'kalibrasi'
        UNION SELECT 'sertifikasi' UNION SELECT 'pendampingan' UNION SELECT 'inspeksi' UNION SELECT 'profisiensi') l
 WHERE j.layanan = '';
DELETE FROM po_jabatan_pelaksana WHERE layanan = '';
