<!-- ======================= VIEW 3: FORM TAMBAH METODE ======================= -->
<div class="demo-view" id="view-form">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= ($BASE) ?>/kategori-uji" class="btn-back"><i class="bi bi-arrow-left"></i> Kembali</a>
        <div>
            <h2 class="h4 fw-bold mb-1 text-dark">Tambah Metode & Harga Uji</h2>
            <p class="text-muted small mb-0">Detail standar/metode pengujian &mdash; deskripsi, peralatan, durasi pelaksanaan, dan tarif per sampel.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-clipboard2-data text-primary me-2"></i>Kategori & Standar Metode</h6></div>
                <div class="card-body p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label">Kategori Pengujian <span class="text-danger">*</span></label>
                        <select class="form-select"><option>Biodegradasi</option><option>Toksikologi</option><option>Ketahanan Jamur</option></select>
                        <div class="form-text">Kategori belum ada? <a href="#">Tambah Kategori Baru</a></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Metode / Standar Acuan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" value="SNI 14593">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi & Kegunaan Uji</label>
                        <textarea class="form-control" rows="3">Kemampuan biodegradasi ultimate aerobik untuk senyawa organik pada media cair.</textarea>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Peralatan Utama</label>
                        <input type="text" class="form-control" value="Shaker Inkubator">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Durasi Pelaksanaan</h6></div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Lama Pelaksanaan <span class="text-danger">*</span></label><input type="number" class="form-control" value="3"></div>
                        <div class="col-6"><label class="form-label">Satuan Waktu <span class="text-danger">*</span></label><select class="form-select"><option>Bulan</option></select></div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" checked id="ce">
                        <label class="form-check-label small" for="ce">Membutuhkan pengujian pihak eksternal (di luar Balai)</label>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-cash-coin text-primary me-2"></i>Tarif Pengujian</h6></div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3 mb-3">
                        <div class="col-6"><label class="form-label">Harga (Rp) <span class="text-danger">*</span></label><input type="number" class="form-control" value="142500000"></div>
                        <div class="col-6"><label class="form-label">Per Jumlah Sampel <span class="text-danger">*</span></label><input type="number" class="form-control" value="4"></div>
                    </div>
                    <div class="form-text mb-3">Contoh: <strong>Rp142.500.000 / 4 sampel</strong> &rarr; Harga = 142500000, Jumlah Sampel = 4.</div>
                    <hr class="my-3">
                    <label class="form-label d-block mb-2">Status Tampilan pada Pilihan Order</label>
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background:var(--color-bg); border:1px solid var(--color-border);">
                        <div class="d-flex align-items-center gap-2 fw-semibold small text-dark"><i class="bi bi-eye text-success fs-5"></i><span>Aktif &ndash; Tampil di pilihan order</span></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" checked style="width:2.75rem; height:1.5rem;"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="#" class="btn btn-outline-secondary px-4">Batal</a>
                <button class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Metode Uji</button>
            </div>
        </div>
    </div>
</div>