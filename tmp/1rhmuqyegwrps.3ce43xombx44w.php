<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-clipboard2-data text-primary me-1"></i> Metode & Harga Uji Laboratorium</h2>
        <p class="text-muted small mb-0">Daftar standar/metode pengujian beserta deskripsi, peralatan, durasi, dan tarif &mdash; dikelompokkan per kategori pengujian.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= ($BASE) ?>/kategori-uji" class="btn btn-outline-primary">
            <i class="bi bi-collection"></i> Kelola Kategori
        </a>
        <a href="<?= ($BASE) ?>/metode-uji/tambah" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Metode Uji
        </a>
    </div>
</div>

<!-- Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Kategori</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= ($total_kategori ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: rgba(136,19,55,.08); color: var(--color-primary);">
                <i class="bi bi-collection"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Total Metode Uji</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= ($total_metode ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: #f0f9ff; color: #075985;">
                <i class="bi bi-list-ul"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Aktif &bull; Tampil</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= ($total_aktif ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: #ecfdf5; color: #065f46;">
                <i class="bi bi-eye"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Nonaktif &bull; Tersembunyi</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= ($total_nonaktif ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: #f1f5f9; color: #475569;">
                <i class="bi bi-eye-slash"></i>
            </div>
        </div>
    </div>
</div>

<!-- Pencarian & Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="<?= ($BASE) ?>/metode-uji" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama metode / standar acuan..." value="<?= ($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="kategori" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php foreach (($semua_kategori ?: []?:[]) as $kOpt): ?>
                        <option value="<?= ($kOpt['id']) ?>" <?= ($filter_kategori == $kOpt['id'] ? 'selected' : '') ?>><?= ($kOpt['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="aktif" <?= ($filter_status == 'aktif' ? 'selected' : '') ?>>Aktif (Tampil)</option>
                    <option value="nonaktif" <?= ($filter_status == 'nonaktif' ? 'selected' : '') ?>>Nonaktif (Disembunyikan)</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Accordion per Kategori -->
<?php if ($daftar_kategori && count($daftar_kategori) > 0): ?>
    <div class="accordion" id="accordionMetode">
        <?php foreach (($daftar_kategori?:[]) as $ik=>$kat): ?>
            <div class="accordion-item border-0 shadow-sm rounded-3 mb-3 overflow-hidden">
                <h2 class="accordion-header" id="heading<?= ($ik) ?>">
                    <button class="accordion-button <?= ($ik > 0 ? 'collapsed' : '') ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= ($ik) ?>" aria-expanded="<?= ($ik == 0 ? 'true' : 'false') ?>" aria-controls="collapse<?= ($ik) ?>">
                        <div class="d-flex align-items-center justify-content-between w-100 me-3 flex-wrap gap-2">
                            <div>
                                <span class="fw-bold text-dark"><?= ($kat['nama_kategori']) ?></span>
                                <?php if ($kat['status'] != 'aktif'): ?>
                                    <span class="badge badge-pill-secondary ms-2"><i class="bi bi-eye-slash"></i> Kategori Nonaktif</span>
                                <?php endif; ?>
                                <?php if ($kat['deskripsi']): ?>
                                    <div class="text-muted small fw-normal mt-1" style="max-width: 520px;"><?= ($kat['deskripsi']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="badge badge-pill-primary"><?= (count($kat['metode'] ?: [])) ?> metode</span>
                        </div>
                    </button>
                </h2>
                <div id="collapse<?= ($ik) ?>" class="accordion-collapse collapse <?= ($ik == 0 ? 'show' : '') ?>" aria-labelledby="heading<?= ($ik) ?>" data-bs-parent="#accordionMetode">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="min-width:170px;">Standar / Metode</th>
                                        <th>Deskripsi & Peralatan</th>
                                        <th style="width:110px;">Durasi</th>
                                        <th class="text-end" style="width:190px;">Harga</th>
                                        <th class="text-center" style="width:90px;">Status</th>
                                        <th class="text-center" style="width:150px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($kat['metode'] && count($kat['metode']) > 0): ?>
                                        <?php foreach (($kat['metode']?:[]) as $m): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?= ($m['nama_metode']) ?></div>
                                                    <?php if ($m['butuh_eksternal']): ?>
                                                        <span class="badge badge-pill-warning mt-1"><i class="bi bi-box-arrow-up-right"></i> Perlu Uji Eksternal</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="text-muted small" style="max-width: 380px;"><?= ($m['deskripsi_kegunaan']) ?></div>
                                                    <?php if ($m['peralatan']): ?>
                                                        <div class="text-muted" style="font-size: 0.72rem;"><i class="bi bi-tools me-1"></i>Peralatan: <?= ($m['peralatan']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= ($m['durasi_nilai']) ?> <?= ($m['durasi_satuan']) ?></td>
                                                <td class="text-end fw-semibold">
                                                    Rp <?= (number_format($m['harga'], 0, ',', '.'))."
" ?>
                                                    <div class="text-muted fw-normal" style="font-size: 0.72rem;">/ <?= ($m['jumlah_sampel']) ?> sampel</div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($m['status'] == 'aktif'): ?>
                                                        <span class="badge badge-pill-success"><i class="bi bi-eye"></i> Aktif</span>
                                                    <?php endif; ?>
                                                    <?php if ($m['status'] != 'aktif'): ?>
                                                        <span class="badge badge-pill-secondary"><i class="bi bi-eye-slash"></i> Nonaktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <a href="<?= ($BASE) ?>/metode-uji/<?= ($m['id']) ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form action="<?= ($BASE) ?>/metode-uji/<?= ($m['id']) ?>/toggle-status" method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                            <?php if ($m['status'] == 'aktif'): ?>
                                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sembunyikan dari pilihan order">
                                                                    <i class="bi bi-eye-slash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($m['status'] != 'aktif'): ?>
                                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Tampilkan kembali">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>
                                                        <form action="<?= ($BASE) ?>/metode-uji/<?= ($m['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus metode uji ini secara permanen?');">
                                                            <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!$kat['metode'] || count($kat['metode']) == 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                Belum ada metode uji pada kategori ini.
                                                <a href="<?= ($BASE) ?>/metode-uji/tambah?kategori=<?= ($kat['id']) ?>">Tambahkan sekarang</a>.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$daftar_kategori || count($daftar_kategori) == 0): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            Belum ada kategori maupun metode pengujian.<br>
            Mulai dengan <a href="<?= ($BASE) ?>/kategori-uji/tambah">membuat kategori pengujian</a> terlebih dahulu.
        </div>
    </div>
<?php endif; ?>
