-- Perbaiki order yang sudah DEAL tapi BELUM didisposisikan ke Keuangan,
-- tapi terlanjur ditandai 'menunggu_pembayaran' oleh sistem lama.
-- Aman dijalankan berulang. Jalankan di database silopti2026.

-- 1) LIHAT DULU order mana yang akan dikembalikan (cek isinya, pastikan memang belum didisposisi)
SELECT o.id, o.nomor_order, o.status_keuangan
FROM order_layanan o
WHERE o.status_keuangan = 'menunggu_pembayaran'
  AND o.id NOT IN (SELECT order_id FROM opti_pembayaran WHERE order_id IS NOT NULL)
  AND o.id NOT IN (SELECT order_id FROM opti_activity_log WHERE aksi = 'Disposisi Keuangan' AND order_id IS NOT NULL);

-- 2) KEMBALIKAN ke 'belum_ditagih' (hilang dari antrean Keuangan sampai didisposisikan)
UPDATE order_layanan o
SET o.status_keuangan = 'belum_ditagih'
WHERE o.status_keuangan = 'menunggu_pembayaran'
  AND o.id NOT IN (SELECT order_id FROM opti_pembayaran WHERE order_id IS NOT NULL)
  AND o.id NOT IN (SELECT order_id FROM opti_activity_log WHERE aksi = 'Disposisi Keuangan' AND order_id IS NOT NULL);
