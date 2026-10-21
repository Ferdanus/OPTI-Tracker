-- Sinkron penanda tb_arsip_sertifikasi.status_klaim_po dengan po_kegiatan (PO Sertifikasi).
-- Jalankan di phpMyAdmin. Ganti `silopti2026` bila nama database utama kamu beda.

-- 1) Order yang SUDAH punya PO (draft / terkirim / dst) -> 'Y'
UPDATE sisertifikasi2026.tb_arsip_sertifikasi a
SET a.status_klaim_po = 'Y'
WHERE EXISTS (
    SELECT 1 FROM silopti2026.po_kegiatan pk
    WHERE pk.order_id = a.id_arsip_sertifikasi AND pk.layanan = 'sertifikasi'
);

-- 2) Order bertanda 'Y' tapi PO-nya tidak ada lagi (mis. PO dihapus manual) -> kembali 'N'
UPDATE sisertifikasi2026.tb_arsip_sertifikasi a
SET a.status_klaim_po = 'N'
WHERE a.status_klaim_po = 'Y'
  AND NOT EXISTS (
    SELECT 1 FROM silopti2026.po_kegiatan pk
    WHERE pk.order_id = a.id_arsip_sertifikasi AND pk.layanan = 'sertifikasi'
);
