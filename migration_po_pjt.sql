-- Melibatkan PJT Lingkungan di review PO (dipilih Ketua Tim). Jalankan sekali di database silopti2026 (dan di server).
-- Cek dulu: SHOW COLUMNS FROM po_kegiatan LIKE 'pjt_%';  (kalau sudah ada, lewati)
ALTER TABLE po_kegiatan
  ADD COLUMN pjt_status VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN pjt_id INT NULL DEFAULT NULL,
  ADD COLUMN pjt_at DATETIME NULL DEFAULT NULL;

-- Tahap 2: masukan dari PJT dan waktu PJT menanggapi
ALTER TABLE po_kegiatan
  ADD COLUMN pjt_catatan TEXT NULL DEFAULT NULL,
  ADD COLUMN pjt_selesai_at DATETIME NULL DEFAULT NULL;

-- Tahap 3: catatan per bagian dari PJT (ditandai dari_pjt = 1, draf sampai PJT menekan Kirim masukan)
ALTER TABLE po_revisi_catatan
  ADD COLUMN dari_pjt TINYINT(1) NOT NULL DEFAULT 0;

-- Tahap 4: salinan dokumen PO terakhir yang ditinjau tiap pihak (katim / pembuat / pjt), dasar tanda merah perubahan
CREATE TABLE IF NOT EXISTS po_baseline (
  po_id INT NOT NULL,
  sisi VARCHAR(10) NOT NULL,
  isi MEDIUMTEXT NOT NULL,
  diperbarui_at DATETIME NOT NULL,
  PRIMARY KEY (po_id, sisi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
