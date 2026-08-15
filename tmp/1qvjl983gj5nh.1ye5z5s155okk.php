<div class="row justify-content-center" data-aos="fade-up">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-3">
            <a href="<?= ($BASE) ?>/order" class="btn-back me-3">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Tambah Order Layanan Baru</h4>
        </div>

        <div class="card shadow-sm">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Form Permintaan Layanan Klien</h6>
            </div>
            <div class="card-body p-4">
                <form action="<?= ($BASE) ?>/order/simpan" method="POST">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                    <div class="mb-3">
                        <label for="klien_id" class="form-label fw-semibold">Pilih Klien / Perusahaan <span class="text-danger">*</span></label>
                        <select class="form-select" id="klien_id" name="klien_id" required>
                            <option value="">-- Pilih Klien Pemohon --</option>
                            <?php foreach (($daftar_klien?:[]) as $klien): ?>
                                <option value="<?= ($klien['id']) ?>"><?= ($klien['nama_perusahaan']) ?><?= ($klien['pic'] ? ' (PIC: ' . $klien['pic'] . ')' : '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Data perusahaan yang mengajukan permohonan layanan.</div>
                    </div>

                    <div class="mb-3">
                        <label for="judul_kegiatan" class="form-label fw-semibold">Judul Kegiatan Layanan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul_kegiatan" name="judul_kegiatan" required placeholder="Contoh: Pengujian Karakteristik Fisik & Mekanik Pulp Selulosa">
                    </div>

                    <div class="mb-3">
                        <label for="tanggal_masuk" class="form-label fw-semibold">Tanggal Masuk Order <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk" required value="<?= (date('Y-m-d')) ?>">
                    </div>

                    <div class="mb-4">
                        <label for="deskripsi" class="form-label fw-semibold">Deskripsi / Ruang Lingkup Pekerjaan</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4" placeholder="Detail parameter uji, spesifikasi sampel, atau catatan khusus..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= ($BASE) ?>/order" class="btn btn-light border">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Order Layanan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
