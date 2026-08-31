<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= ($BASE) ?>/penguji-eksternal" class="btn-back">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark">
            <?= ($lembaga ? 'Edit Lembaga Pengujian Eksternal' : 'Tambah Lembaga Pengujian Eksternal')."
" ?>
        </h2>
        <p class="text-muted small mb-0">Data lembaga / tempat rujukan pengujian eksternal.</p>
    </div>
</div>

<form action="<?= ($lembaga ? $BASE . '/pengujian-eksternal/' . $lembaga['id'] . '/update' : $BASE . '/pengujian-eksternal/simpan') ?>" method="POST">
    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

    <div class="row g-4">
        <!-- ======================================================== -->
        <!-- KOLOM KIRI: INFORMASI LEMBAGA -->
        <!-- ======================================================== -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-building text-primary me-2"></i>Informasi Lembaga</h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label">Nama Lembaga / Tempat <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lembaga" class="form-control" placeholder="Contoh: Sucofindo Cabang Bandung" value="<?= ($lembaga['nama_lembaga']) ?>" required>
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="4" placeholder="Contoh: Jl. Cisitu Lama No.4, Bandung, Jawa Barat"><?= ($lembaga['alamat']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- KOLOM KANAN: STATUS TAMPILAN -->
        <!-- ======================================================== -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-toggle-on text-primary me-2"></i>Status Tampilan</h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background-color: var(--color-bg); border: 1px solid var(--color-border);">
                        <div id="statusAktifLabel" class="d-flex align-items-center gap-2 fw-semibold small text-dark">
                            <i class="bi bi-eye text-success fs-5"></i>
                            <span>Aktif &ndash; Tampil di daftar</span>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="status" id="statusAktif" value="aktif" style="width: 2.75rem; height: 1.5rem;" <?= (!$lembaga || $lembaga['status'] == 'aktif' ? 'checked' : '') ?>>
                        </div>
                    </div>
                    <div class="form-text">Nonaktifkan untuk menyembunyikan lembaga ini dari daftar tanpa menghapus datanya.</div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= ($BASE) ?>/penguji-eksternal" class="btn btn-outline-secondary px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i> <?= ($lembaga ? 'Perbarui Data' : 'Simpan Lembaga')."
" ?>
                </button>
            </div>
        </div>
    </div>
</form>

<style>
    .form-switch .form-check-input:checked { background-color: var(--color-primary); border-color: var(--color-primary); }
    .form-switch .form-check-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(136, 19, 55, 0.12); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var statusSwitch = document.getElementById('statusAktif');
    var statusLabel = document.getElementById('statusAktifLabel');
    function renderStatusLabel() {
        statusLabel.innerHTML = statusSwitch.checked
            ? '<i class="bi bi-eye text-success fs-5"></i><span>Aktif &ndash; Tampil di daftar</span>'
            : '<i class="bi bi-eye-slash text-muted fs-5"></i><span>Nonaktif &ndash; Disembunyikan dari daftar</span>';
    }
    if (statusSwitch) {
        statusSwitch.addEventListener('change', renderStatusLabel);
        renderStatusLabel();
    }
});
</script>
