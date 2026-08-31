<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Registrasi Surat Masuk</h6>
    </div>
    <div class="card-body">
        <form action="<?= ($BASE) ?>/surat-masuk" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold small text-muted" for="nomor_surat">Nomor Surat</label>
                <input type="text" class="form-control" id="nomor_surat" name="nomor_surat" placeholder="Contoh: 012/EXT/VIII/2026" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small text-muted" for="tanggal_surat">Tanggal Surat</label>
                <input type="date" class="form-control" id="tanggal_surat" name="tanggal_surat" value="<?= (date('Y-m-d')) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small text-muted" for="nama_pengirim">Nama Pengirim / Perusahaan</label>
                <input type="text" class="form-control" id="nama_pengirim" name="nama_pengirim" placeholder="Contoh: PT Semen Indonesia" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small text-muted" for="perihal">Perihal / Keterangan Singkat</label>
                <input type="text" class="form-control" id="perihal" name="perihal" placeholder="Contoh: Permintaan Uji Biodegradabilitas Kemasan" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small text-muted" for="layanan">Layanan Tujuan</label>
                <select class="form-select" id="layanan" name="layanan" required>
                    <option value="opti" selected>Layanan Optimalisasi Industri (OPTI)</option>
                    <option value="pengujian">Layanan Pengujian Standar</option>
                    <option value="sertifikasi">Layanan Sertifikasi (LSP/LSPro)</option>
                    <option value="kalibrasi">Layanan Kalibrasi Alat</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold small text-muted" for="file_upload">Upload Berkas PDF / Scan Surat</label>
                <input type="file" class="form-control" id="file_upload" name="file_upload" accept=".pdf,image/*">
                <div class="form-text small">Hanya file PDF atau Gambar (maksimal 5MB)</div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-save me-1"></i> Catat Surat Masuk
            </button>
        </form>
    </div>
</div>
