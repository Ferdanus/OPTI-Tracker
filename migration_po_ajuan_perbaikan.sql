-- Ajuan perbaikan PO ke PPK BLU setelah PO disetujui.
-- Tidak wajib dijalankan: aplikasi membuat tabel dan kolom ini sendiri saat pertama dibuka (PoAjuanPerbaikanController::pastikanSkema).
-- File ini hanya untuk dokumentasi / dijalankan manual di server lain.
CREATE TABLE IF NOT EXISTS po_ajuan_perbaikan (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    po_id INT NOT NULL,
    order_id INT NULL,
    oleh_id INT NOT NULL,
    peran VARCHAR(10) NOT NULL DEFAULT 'katim',          -- katim | pembuat (Ketua Pelaksana)
    alasan TEXT NOT NULL,
    lampiran_nama VARCHAR(255) NULL,
    lampiran_file VARCHAR(120) NULL,                      -- storage/po_ajuan/
    status VARCHAR(12) NOT NULL DEFAULT 'berjalan',       -- berjalan | disetujui | ditolak | ditarik
    ronde_awal INT NOT NULL DEFAULT 0,
    snapshot LONGTEXT NULL,                               -- isi PO sebelum diubah (dikembalikan bila ditolak / ditarik)
    putusan_catatan TEXT NULL,
    diputus_oleh INT NULL,
    diputus_at DATETIME NULL,
    ttd_lama_file VARCHAR(120) NULL,                      -- PO bertanda tangan versi lama yang dilepas saat ajuan disetujui
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_po (po_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE po_kegiatan ADD COLUMN ajuan_id INT NULL DEFAULT NULL;
-- Lampiran ke-2 dan ke-3 (lampiran pertama tetap di po_ajuan_perbaikan.lampiran_nama / lampiran_file). Dibuat otomatis juga.
CREATE TABLE IF NOT EXISTS po_ajuan_lampiran (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ajuan_id INT UNSIGNED NOT NULL,
    nama VARCHAR(255) NOT NULL,
    file VARCHAR(120) NOT NULL,                           -- storage/po_ajuan/
    urut TINYINT NOT NULL DEFAULT 2,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ajuan (ajuan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
