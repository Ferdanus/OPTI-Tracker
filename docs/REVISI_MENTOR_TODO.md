# Rundown To-Do Revisi Hasil Diskusi dengan Mentor

> Sumber: transkrip diskusi review sistem SILOPTI (alur Surat Masuk sampai PO).
> Legenda prioritas: **P0** = wajib beres sebelum training/uji coba user, **P1** = penting, **P2** = perbaikan lanjutan.
> Status: `[ ]` belum, `[x]` sudah, `[~]` sebagian/perlu verifikasi ulang.
> Item bertanda **(?)** isi transkripnya kurang jelas, ada di bagian "Perlu Klarifikasi" paling bawah.

---

## 0. Arahan Umum Mentor

- [ ] **P0** Tujuan training: alur disposisi dan data harus **sampai ke tiap user** (surat masuk → mitra → ketua tim → PIC → ketua tim → mitra → keuangan → ketua tim → PIC PO → PO). Salah input nominal atau teks kecil boleh dirapikan setelah training.
- [ ] **P0** Saat training, developer **langsung memperbaiki di tempat** kalau ada tugas yang tidak sampai ke user, bukan hanya mencatat.
- [ ] **P1** Developer wajib paham proses bisnis, bukan hanya mengandalkan AI. Pelajari dan jelaskan sendiri kode yang dipakai.
- [ ] **P1** Timeline: target total ± 3 bulan (bisa jadi 4). Minggu depan review lagi, cakupan sampai **PO**. Berikutnya **Kontrak** dan **BAST**.
- [ ] **P1** Sambil lanjut, perbaiki semua temuan di bawah sekaligus.

---

## 1. Infrastruktur dan Deployment

- [x] **P0** **Rename folder** `Mini OPTI Tracker` menjadi `silopti` (tanpa spasi dan tanpa `%20`).
  - [x] Hapus hardcode `BASE = '/Mini%20OPTI%20Tracker'` di `index.php` (sekarang selalu redirect ke "tracker").
  - [x] Cek semua link, aset, dan redirect setelah rename.
  - [x] Tes akses lewat IP laptop (contoh `http://192.168.34.203/silopti/...` / `http://[IP_LAPTOP]/silopti/...`) dari komputer lain.
- [ ] **P0** **Gabungkan kode** semua developer (Fajri, Eka, dan lainnya) jadi satu, lalu tes jalan.
  - [ ] Eka dan Fajri sepakat push **jam 12.00**. Merge, cek konflik, tes ulang.
- [ ] **P1** **Uji di PHP 8.4**: copy versi terbaru, jalankan, catat error atau deprecated yang muncul.
  - Kalau tidak jalan, perbaikannya diharapkan hanya sedikit.
- [ ] **P1** Siapkan environment demo di satu laptop (server uji coba). Hosting/domain nanti menyusul.
- [ ] **P2** Siapkan data demo yang bersih (user, order contoh) untuk training.

## 2. Login dan Session (Integrasi SIPISA)

- [ ] **P0** Login dilakukan di **SIPISA**. Dari SIPISA ada link ke SILOPTI, dan **session user terbawa otomatis** (contoh login sebagai Bu Rina di SIPISA, buka SILOPTI, otomatis sebagai Bu Rina).
- [ ] **P0** **Hapus dropdown "ganti user"** (itu hanya untuk uji coba).
- [ ] **P1** Uji alur login terintegrasi. Mentor menyebut ini langkah mundur sedikit, tapi perlu.
- [ ] **P1** Daftar Pengguna: tab **Pengguna OPTI** dan **Pengguna PO** (sudah ada, cek ulang). Peran yang dipakai di demo:
  - Tim Mitra: Pak Andar.
  - Ketua Tim Selulosa: Bu Rina.
  - PIC Proposal: Fajri/Aji.
  - Tim Keuangan: Bu Ati/Wati (cek).
  - Pembuat PO Selulosa: Yogi, Aji Bakwan (cek).

## 3. Tahap 1: Surat Masuk

- [x] **P0** Urutan daftar surat masuk **descending**: yang terbaru di atas (berdasarkan tanggal atau ID arsip terbesar).
- [ ] **P0** Tombol **Lihat Surat** harus menampilkan **file surat yang diunggah sekretaris** (tabel arsip). Saat ini yang tampil template buatan sistem/blank.
- [ ] **P1** Setelah surat **diterima**, surat hilang dari daftar surat masuk dan masuk ke **Daftar Order** (alur sudah benar, cek ulang).
- [ ] **P1** Penanganan surat dengan instansi tidak terdaftar di data customer **(?)**.

## 4. Tahap 2: Form Pelayanan (Tim Mitra)

- [ ] **P1** Pemilihan layanan (OPTI Selulosa/Lingkungan) lalu **Kirim ke Ketua Tim**.
- [ ] **P1** Tinjauan (4 pilar) **opsional**: diisi sekarang oleh Tim Mitra, atau diserahkan ke Ketua Tim.
- [ ] **P1** Penjelasan/ruang lingkup tetap ada.
- [ ] **P1** Penamaan: gunakan **"Tim OPTI Selulosa/Lingkungan"**, bukan "bidang".

## 5. Notifikasi (banyak revisi)

- [x] **P0** **Gabungkan dua notifikasi ganda** ("Permintaan Layanan Baru" dan "Permintaan Masuk Baru") menjadi **satu**.
- [x] **P0** Judul baru: **"Permintaan Permohonan Baru"** (bukan "layanan baru" atau "order baru").
  - Alasan: statusnya masih permohonan, jadi order baru terbentuk setelah deal.
- [x] **P0** Isi notifikasi:
  - [x] Tambahkan **nomor request** (REQ-...).
  - [x] Hapus kata "telah masuk".
  - [x] Hapus "Mohon kaji kelayakan teknis ISO 17025".
  - [x] Tambah baris **Tindakan**:
    - Tinjauan belum diisi: *"Evaluasi kelayakan teknis dan tentukan PIC"*.
    - Tinjauan sudah diisi Tim Mitra: *"Tentukan PIC"*.
- [x] **P0** Notifikasi penugasan PIC Proposal:
  - Format: *"Ditugaskan sebagai PIC untuk order [nomor request]. Mohon susun dan unggah dokumen proposal teknis."*
  - Jangan tampilkan nama PT dalam kurung. Pastikan **nomor request terisi** (sekarang tampil `Order #` kosong).
- [x] **P0** Notifikasi **tidak boleh muncul untuk draf** (contoh Simpan Draf surat penawaran). Notifikasi hanya muncul saat benar-benar dikirim/diterbitkan.
- [x] **P0** Notifikasi pembayaran: tulis **"dikonfirmasi oleh Bagian Keuangan"**, tanpa nama orang.
- [x] **P1** Bug lonceng: setelah dibuka, notifikasi hilang dari dropdown tapi **tetap ada di riwayat** ("Lihat Seluruh Riwayat"). Cek kenapa sebagian user melihat kosong.
- [x] **P1** Tanggal **baca** per user tidak boleh hilang walau tugas dialihkan.
- [x] **P1** Tim Mitra tetap menerima notifikasi tiap tahap (untuk memantau posisi order).
- [x] **P1** Tambahkan notifikasi yang belum ada ("nanti tambahin notifnya"), termasuk notifikasi dari tahap PO. Cek semua tahap, pastikan ada notifikasi ke user berikutnya.

## 6. Tahap 3: Kaji Kelayakan (Ketua Tim)

- [ ] **P0** Bug form kaji kelayakan: harus diklik/dipilih "layak" dulu, dan layout panel tidak benar. Perbaiki alurnya.
- [ ] **P0** Panel kiri: **nama perusahaan diambil dari surat masuk** (yang dipilih di Tahap 1), bukan dari master customer.
- [ ] **P1** Ganti istilah "bidang" menjadi **"Tim"** (contoh Tim OPTI Selulosa).
- [ ] **P1** Pilih **PIC Proposal** di tahap ini (pilihan hanya anggota tim layanan tersebut).
- [ ] **P1** **Stepper**: tahap yang sudah selesai diberi **warna hijau** (sekarang serba hitam putih), supaya jelas mana yang sudah terlewati.

## 7. Tahap 4: Proposal

- [ ] **P0** Tambah input baru **"Judul Kegiatan"** (berbeda dengan judul permohonan dari surat).
  - Diisi oleh Tim Mitra, PIC, atau Ketua Tim.
  - **Setelah deal**, judul yang tampil di Daftar Order, Tahap 6 dan seterusnya adalah **Judul Kegiatan + nama perusahaan**, bukan judul permohonan.
  - Di Surat Penawaran (belum deal) tetap pakai judul permohonan dari surat klien.
  - Contoh: permohonan "Optimalisasi Formulasi…" lalu kegiatan "Optimalisasi Formulasi Deinking".
- [ ] **P1** Proposal dikerjakan **di luar sistem**, lalu di-upload dalam format **DOC/DOCX** (supaya Bu Rina bisa edit sendiri).
  - [ ] Preview di sistem (auto-convert ke PDF).
  - [ ] Tombol **Download** dan tombol **Ganti** (upload ulang versi revisi).
- [ ] **P1** Catatan 4 pilar dari tinjauan: hanya diisi kalau ada kendala (opsional). Perjelas apakah tampil di surat penawaran **(?)**.
- [ ] **P1** Ketua Tim: **Setujui** atau **Minta Revisi**.
  - Revisi: kembali ke PIC Proposal (status draf/revisi).
  - Setuju: langsung lanjut ke Tim Mitra.
- [ ] **P1** Ubah teks setelah disetujui: bukan "tahap penerbitan surat penawaran resmi", tapi **"Tahap selanjutnya: pembuatan surat penawaran yang akan dikirim ke Mitra"**.
- [ ] **P1** Daftar Order: garis/penanda **biru** untuk tugas milik user yang login (fitur tambahan, dipertahankan).
- [ ] **P2** Halaman rekap proposal (tugas proposal) per layanan, tampilkan status:
  - draf (PIC belum kirim), menunggu Ketua Tim, disetujui.
  - Hilangkan yang membingungkan/duplikat, dan arahkan ke detail order **(?)**.

## 8. Tahap 5: Surat Penawaran (Tim Mitra)

**Tampilan dan form**
- [ ] **P0** Form penawaran langsung tampil di bawah tab proposal sehingga membingungkan. Letakkan di tahap/menu yang jelas, dengan penanda yang tepat.
- [ ] **P1** **Preview surat** menghalangi input. Buat mode transparan, bisa ditutup, atau tampil di panel samping yang tidak menutupi form.
- [ ] **P1** **Nomor surat**: format `B/…/BBSPJIS/MS/XI/2026`.
  - Nomor urut **dikosongkan (titik-titik)** dan diisi **manual** oleh Tim Mitra (nomor dari sekretaris).
  - Bulan dan tahun otomatis. Tetap ada awalan **B**.
  - Alasan: nomor ini nomor surat keluar seluruh layanan jasa, bukan hanya OPTI.
- [ ] **P1** Rincian poin-poin: "Sisipkan Draft Standar" hanya template, tetap bisa diedit manual.
- [ ] **P0** **Simpan Draft salah fungsi**: sekarang langsung menerbitkan dan mengirim notifikasi. Harus hanya **menyimpan draft**, tidak diterbitkan, tidak kirim notifikasi.
- [ ] **P1** **Status respon klien** awalnya **Negosiasi**.
- [ ] **P1** **Lampiran surat penawaran**: tambahkan input lampiran **di dalam form penawaran** (lampiran proposal/RAB), tersimpan bersama surat. Tidak perlu input terpisah yang tersembunyi.

**Negosiasi dan deal**
- [ ] **P0** Buat **surat penawaran baru** (nomor baru) ⇒ surat sebelumnya otomatis berstatus **Batal**, yang terbaru jadi aktif. Riwayat dan catatan alasan tetap terlihat.
- [ ] **P1** **Harga awal** (dari proposal) tetap/tidak berubah. **Harga sekarang** (hasil nego) diisi manual di form.
- [ ] **P0** Kalau klien **deal tanpa surat baru**: gunakan **Koreksi Formulir**, ubah status jadi **Deal**. Nomor surat tetap (contoh B03), tidak buat nomor baru.
- [ ] **P1** Setelah deal, sembunyikan tombol **Ganti Surat**.

**Berkas surat**
- [ ] **P0** **Surat penawaran bertanda tangan** diambil dari **Surat Keluar sekretaris** (klaim surat), bukan upload manual.
  - [ ] Hapus opsi upload manual untuk surat bertanda tangan.
  - [ ] Dropdown surat difilter berdasarkan **nama perusahaan** terkait.
  - [ ] Setelah diklaim, tombol hanya **Lihat Dokumen**. Kalau salah pilih, gunakan **Batal Klaim** lalu klaim ulang.
- [ ] **P1** Hilangkan **dokumen ganda** (muncul dua kali di daftar berkas: nomor 1 dan 2).
- [ ] **P1** Lampiran **persetujuan klien (halaman 2, tabel pelanggan)**:
  - Tim Mitra download, print, kirim ke pelanggan, lalu pelanggan mengisi tabel dan mengirim balik.
  - Tim Mitra upload ke sistem dalam **PDF saja** (jangan terima PNG/JPG/DOC, alasan keamanan).
- [ ] **P1** **Surat Kesanggupan Bayar**: opsional.
  - Ada surat kesanggupan ⇒ Keuangan boleh **skip** pembayaran dulu.
  - Tidak ada ⇒ order menunggu pembayaran.
- [ ] **P1** Download surat penawaran dalam **Word** (unduh Word) tetap tersedia.

## 9. Tahap 6: Pembayaran (Tim Keuangan)

- [ ] **P0** Tambah field **Tanggal Pengesahan** per pembayaran/termin.
  - **Opsional**. Kalau kosong, otomatis sama dengan tanggal bayar.
  - Dipakai untuk pelaporan data bulanan ke pusat. Termin 1 dan 2 punya tanggal pengesahan masing-masing.
  - Tampilkan di rincian pembayaran.
- [ ] **P0** Upload **bukti pembayaran / invoice**: tetap **wajib**. Perlu konfirmasi lagi apakah bukti selalu ada **(?)**.
- [ ] **P0** **Bug logika termin**: setelah pembayaran lunas, form masih bisa tambah. Sisa tagihan dan status sebagian/lunas harus benar. Tes skenario:
  - Bayar sebagian lalu tambah termin lalu lunas.
  - Nominal melebihi sisa ditolak.
  - Order lunas tidak bisa ditambah pembayaran lagi.
- [ ] **P0** **Tombol "Detail Pembayaran"** hanya untuk **Tim Keuangan**.
  - Hilangkan/blok untuk Ketua Tim, Tim Mitra, dan user lain (sekarang error untuk Bu Rina).
  - Menu "Pembayaran/Pembiayaan" di sidebar hanya muncul untuk Keuangan. Cek di **semua role**.
- [ ] **P1** User lain tetap bisa **melihat bukti pembayaran** di Tahap 6 detail order (read-only).
- [ ] **P1** Simpan Draf kesanggupan bayar tidak boleh melompat ke Tahap 7 (sudah diperbaiki sebelumnya, `[~]` verifikasi ulang).
- [ ] **P1** Daftar Order setelah deal: kolom **estimasi biaya** dan **terbayar** konsisten, judul pakai Judul Kegiatan.

## 10. Tahap 7: Aktivasi SPM

- [ ] **P0** Setelah pembayaran terkonfirmasi, tugas masuk ke **Ketua Tim** (notifikasi harus tampil).
- [ ] **P0** Ketua Tim **pilih PIC PO** (Ketua Pelaksana). Bisa **diganti** lewat sistem (jangan lewat database).
  - Saat diganti: notifikasi baru ke PIC baru.
  - **Tanggal baca PIC lama tidak hilang** (jejak audit tetap).
- [ ] **P1** **Trigger SPM**: pilihan titik awal (Selulosa: sampel diterima. Lingkungan: sampel/kick-off/tanda tangan PKS). Ubah label supaya jelas: pisahkan **Trigger SPM** dan **Tanggal Mulai SPM**.
- [ ] **P1** Aktivasi SPM bisa dilakukan oleh **PIC** atau **Ketua Tim**.
- [ ] **P1** Catatan trigger SPM **(?)**.
- [ ] **P1** Durasi mengikuti SPM order (sudah diperbaiki di sesi sebelumnya, `[~]` verifikasi ulang selulosa 23 hari kerja dan lingkungan 9 bulan).

## 11. Tahap 8: PO dan Review PO

- [ ] **P0** **Nomor PO berurutan satu sequence untuk semua layanan** (OPTI dan layanan lain). Jangan sampai nomor OPTI berdiri sendiri (1, 2, 3 hanya OPTI).
- [ ] **P0** **Bug Review PO**: halaman review tidak bisa dibuka (ada tanda bulat merah di menu).
  - Perbaikan dari Eka sudah ada, tinggal **merge dan push** (jam 12.00).
- [ ] **P1** **Tanda tangan di PO**: bagian atas **PPK**, bagian bawah **Ketua Tim**, diambil dari **tabel struktural** (belum tersambung, masih contoh/dummy).
  - Pilihan bisa manual, dengan default dari struktural.
- [ ] **P1** Notifikasi di tiap langkah PO (pembuat PO → Ketua Tim → PPK/keuangan → kembali).
- [ ] **P1** **Daftar PO** dibuat dinamis dan sama untuk layanan lain.
- [ ] **P1** Berkas Layanan (menu gabungan di sidebar): rekap **proposal, penawaran, pembayaran, kontrak** per order, **read-only**, supaya sidebar tidak penuh. Cek apakah user non-keuangan tidak bisa mengedit pembayaran dari sini.

## 12. Setelah PO (menyusul)

- [ ] **P1** **Kontrak/PKS**: dibuat oleh Bu Yani dari data yang sudah final (tidak bolak-balik seperti PO). Sudah ada tampilan, perlu integrasi alur dari PO.
- [ ] **P1** **BAST**: sama, alur lebih sederhana.
- [ ] **P2** Fokus dulu ke PO sampai tuntas sebelum Kontrak dan BAST.

## 13. Integrasi Sistem Lain

- [ ] **P1** Integrasi dengan **SIPISA/CPISA**: surat masuk memang berasal dari surat sekretaris, login lewat SIPISA (lihat bagian 2).
- [ ] **P2** Kalau memakai database/tabel sekretaris, pastikan tidak ada penulisan ke tabel induk (hanya baca).

---

## 14. Perlu Klarifikasi dengan Mentor

Bagian-bagian transkrip yang artinya belum pasti. Jangan dikerjakan sebelum jelas:

1. **Instansi tidak terdaftar** di Tahap 1: siapa yang menambah data (Ketua Tim/Admin) dan di mana.
2. **Catatan 4 pilar**: tampil di surat penawaran atau hanya internal (versi Fajri: hanya untuk Tim Mitra bila ada kendala).
3. **Halaman rekap proposal**: dipertahankan, diganti "Berkas Layanan", atau digabung ke Daftar Order.
4. **Bukti pembayaran wajib**: ternyata pelanggan sering transfer langsung (virtual account) tanpa mengirim bukti. Perlu konfirmasi ke Bu Ati/Keuangan apakah tetap wajib atau opsional (invoice yang wajib).
5. **Catatan trigger SPM**: wajib atau opsional.
6. **Perubahan PIC PO**: boleh diganti kapan saja, atau hanya sebelum PIC mulai mengerjakan PO.

---

## 15. Catatan Non-Teknis

- Jaringan Wi-Fi ruang kerja sering lemot: disampaikan ke **Pak Fandi**.
- Ruangan kerja sementara dipindah karena acara tanggal 5. Koordinasi ruangan dengan Pak/Bu setempat.
- Pelatihan user: undang semua user terlibat, lokasi ruang Pinus (lantai atas), 1 hari (± 09.00–12.00), developer siaga memperbaiki di tempat.

---

## 16. Urutan Pengerjaan yang Disarankan

1. **Hari ini:** merge kode (Eka + Fajri), rename folder `silopti`, perbaiki BASE path, tes lewat IP laptop.
2. **Alur disposisi (P0):** notifikasi, surat masuk (urutan dan lihat surat), kaji kelayakan, Judul Kegiatan, Simpan Draft penawaran, deal/batal otomatis.
3. **Keuangan (P0):** tanggal pengesahan, bug termin, hak akses Detail Pembayaran.
4. **Tahap 7–8:** ganti PIC PO, nomor PO satu sequence, Review PO, tanda tangan dari struktural.
5. **Login SIPISA** dan hilangkan dropdown ganti user.
6. **Uji PHP 8.4**, lalu uji alur penuh dengan 2–3 orang sebelum training.
7. **Minggu depan:** review sampai PO. Lanjut Kontrak lalu BAST.
