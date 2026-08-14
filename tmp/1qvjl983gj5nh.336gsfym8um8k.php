<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-3">
            <a href="<?= ($BASE) ?>/klien" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <h4 class="fw-bold mb-0"><i class="bi bi-building-add me-2 text-primary"></i>Tambah Klien Baru</h4>
        </div>

        <div class="card shadow-sm">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Form Data Klien / Perusahaan</h6>
            </div>
            <div class="card-body p-4">
                <form action="<?= ($BASE) ?>/klien/simpan" method="POST">
                    <div class="mb-3">
                        <label for="nama_perusahaan" class="form-label fw-semibold">Nama Perusahaan / Mitra <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_perusahaan" name="nama_perusahaan" required placeholder="Contoh: PT Selulosa Makmur Sejahtera">
                        <div class="form-text">Nama resmi perusahaan atau institusi klien.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="pic" class="form-label fw-semibold">Nama PIC (Person in Charge)</label>
                            <input type="text" class="form-control" id="pic" name="pic" placeholder="Contoh: Budi Santoso">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telepon" class="form-label fw-semibold">Nomor Telepon / WhatsApp</label>
                            <input type="text" class="form-control" id="telepon" name="telepon" placeholder="Contoh: 081234567890">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Alamat Email</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Contoh: kontak@perusahaan.com">
                    </div>

                    <div class="mb-4">
                        <label for="alamat" class="form-label fw-semibold">Alamat Lengkap</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3" placeholder="Alamat kantor atau pabrik..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= ($BASE) ?>/klien" class="btn btn-light border">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Data Klien
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
