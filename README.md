# Mini OPTI Tracker

Aplikasi berbasis **PHP (Fat-Free Framework / F3)** dan **MySQL** untuk mendemonstrasikan konsep:
1. **CRUD & Relasi Data**: Klien, Order Layanan, dan PO.
2. **State Machine Linear**: Alur status PO (`proposal` -> `kontrak` -> `po_terbit` -> `distribusi` -> `selesai`).
3. **Audit Trail**: Pencatatan histori status di `po_log_status`.
4. **Auto Document Numbering**: Penomoran otomatis format `{urut}/PO/OPTI/{bulan_romawi}/{tahun}`.

## Panduan Instalasi & Menjalankan

1. **Jalankan XAMPP** (Apache & MySQL).
2. **Import Database**:
   - Buka phpMyAdmin (`http://localhost/phpmyadmin`).
   - Buat database `mini_opti_tracker` atau import file `schema.sql`.
3. **Akses Aplikasi**:
   - Buka browser ke: `http://localhost/Mini%20OPTI%20Tracker/`
