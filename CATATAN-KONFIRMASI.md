# Catatan Konfirmasi Mentor & Analisis GAP Sistem (SILOPTI)

Dokumen ini memuat temuan ketidaksesuaian (*GAP Analysis*) antara asumsi awal sistem dengan dokumen operasional riil (SOP 19 Langkah, Map Kendali, Data Riil List PO, dan studi kasus riil seperti PT Darusyifa Hikmah Tirta). Poin-poin ini menjadi agenda klarifikasi dengan Mentor/Analis Balai.

---

### 1. Urutan & Sifat PKS vs Map Kendali (PKS Bersifat Opsional)
*   **Temuan / GAP**: 
    *   Sistem sebelumnya mengasumsikan PKS sebagai tahapan wajib (Tahap 3) sebelum Map Kendali/Pelaksanaan.
    *   **Fakta Riil**: Pada kasus riil (contoh: PT Darusyifa Hikmah Tirta), order berjalan langsung dari Surat Penawaran Biaya/RAB $\rightarrow$ PO $\rightarrow$ Pelaksanaan $\rightarrow$ Selesai **tanpa ada PKS**. PKS hanya dibuat untuk kerja sama bernilai/ruang lingkup khusus.
    *   Map Kendali bukan tahapan terpisah setelah PKS, melainkan satu lembar kendali fisik berisikan 4 tahap (Proposal, Kontrak, PO, Distribusi) yang menyertai berkas PO.
*   **Pertanyaan untuk Mentor**: Apakah alur sistem harus memperlakukan Kontrak PKS murni sebagai fitur opsional (hanya diinput jika ada PKS), dan PO bisa langsung dikerjakan tanpa harus menunggu nomor PKS terbit?
*   **Implementasi Sementara di Sistem**: Modul Kontrak PKS dibuat independen dan tidak mengunci (*non-blocking*) proses pengerjaan PO. PO yang belum memiliki PKS tetap dapat diproses hingga selesai.

---

### 2. Taksonomi Status: 2 Flag Independen vs State Machine Linear
*   **Temuan / GAP**:
    *   Data riil List PO menggunakan 4 kategori status:
        1. *Belum Upload Dokumen sudah Diterima*
        2. *Sudah Upload Dokumen belum Diterima*
        3. *On Proses*
        4. *PO sdh kembali/sls*
    *   Kategori 1 dan 2 bukan urutan waktu berjenjang (*linear*), melainkan kombinasi dari **2 kondisi fisik yang independen**:
        *   Status Upload Dokumen Softcopy: [Sudah / Belum]
        *   Status Penerimaan Berkas Fisik: [Sudah / Belum]
*   **Pertanyaan untuk Mentor**: Apakah status di sistem sebaiknya dimodelkan sebagai 2 flag checklist terpisah (`is_uploaded` dan `is_received_physical`), atau tetap menggunakan 4 pilihan dropdown status sesuai tabel rekapitulasi Excel saat ini?
*   **Implementasi Sementara di Sistem**: Sistem mempertahankan 4 status dengan label persis seperti Excel data riil, namun tidak memaksa validasi prasyarat upload yang kaku agar operasional tidak terhambat.

---

### 3. Granularitas Peran (*Roles*) Penandatangan Map Kendali & Tahap Pasif
*   **Temuan / GAP**:
    *   Di form asli, penandatangan Map Kendali bukan hanya satu "Pejabat", melainkan 7-8 pihak spesifik: *Adm KS & Humas, Tim Mitra Industri, PPK BLU, Ka. Bag TU, Kepala Balai, Bag. TU (Arsip), Tim Kepegawaian, Tim Keuangan*.
    *   Pada kasus riil (Darusyifa), **hanya Tahap 3 (Petunjuk Operasional) yang ditandatangani**, sedangkan Tahap 1, 2, dan 4 kosong. Ini membuktikan tidak semua tahap Map Kendali wajib diisi untuk setiap PO.
*   **Pertanyaan untuk Mentor**: 
    1. Apakah sistem perlu membuat login akun terpisah untuk ke-8 pihak tersebut, atau cukup diberikan wewenang approval berdasarkan grup role yang ada?
    2. Apakah Map Kendali diizinkan hanya diisi sebagian (tahap 3 saja untuk order reguler non-PKS)?
*   **Implementasi Sementara di Sistem**: Struktur tabel `role` di database dibuat fleksibel (bukan hardcoded enum). TTD Map Kendali dibuat mandiri per baris sehingga pengguna bebas menandatangani baris mana saja yang relevan tanpa saling mengunci (*optional filling*).

---

### 4. Pelaku Pembuat Dokumen BAST (Tim Mitra Industri vs Ketua Tim)
*   **Temuan / GAP**:
    *   SOP resmi Langkah 16 menyatakan bahwa yang membuat dan menyusun draf BAST adalah **Tim Mitra Industri (Tim Kerja)**, kemudian diserahkan kepada Ketua Tim OPTI dan pelanggan.
*   **Pertanyaan untuk Mentor**: Apakah input file/nomor BAST di sistem dapat dilakukan langsung oleh Tim Kerja (teknis penguji) atau harus divalidasi oleh Ketua Tim OPTI?
*   **Implementasi Sementara di Sistem**: Form pengisian BAST dan Laporan Akhir dibuka untuk hak akses `tim_kerja`, `ketua_tim`, dan `superadmin`.

---

### 5. *Conditional Skip* pada Tahapan Pelaksanaan (Laporan Perkembangan)
*   **Temuan / GAP**:
    *   SOP Langkah 6 menyatakan: *"Apabila di kontrak tidak dipersyaratkan membuat laporan perkembangan, maka tahapan dilanjutkan ke tahap 13 (Laporan Kegiatan Final)."*
*   **Pertanyaan untuk Mentor**: Untuk pengujian rutin/singkat yang tidak memerlukan Laporan Perkembangan antara, apakah sistem cukup mengizinkan field laporan perkembangan dikosongkan langsung ke laporan final?
*   **Implementasi Sementara di Sistem**: Seluruh field pada Card Pelaksanaan SOP (*Draft Laporan Perkembangan*, *Notulen Masukan*, *Laporan Akhir*) bersifat opsional (*nullable*), sehingga pekerjaan dengan siklus singkat dapat langsung mengisi Laporan Final tanpa error.

---

### 6. Standar Penomoran PO (Banyaknya Variasi Format Historis)
*   **Temuan / GAP**:
    *   Data historis Balai menunjukkan pola nomor PO yang beragam (`01/BBSPJIS/PO/I/2026`, `B/82/PO/BBSPJIS/IV/2026`, `246/PO/BBSPJIS/VIII/2026`).
    *   Pola penomoran belum terpusat dalam satu aturan algoritma lokal OPTI.
*   **Pertanyaan untuk Mentor**: Bagaimana format baku nomor PO yang disepakati untuk sistem ke depan? Apakah nomor PO akan di-generate oleh sistem sentral Balai lalu diinput ke OPTI Tracker, atau OPTI Tracker yang akan menjadi acuan format baru?
*   **Implementasi Sementara di Sistem**: Saat Pejabat menyetujui order, sistem memunculkan prompt dialog agar nomor PO dapat **diinput manual** sesuai nomor fisik yang diterbitkan Balai, dengan auto-generate hanya sebagai usulan opsional.
