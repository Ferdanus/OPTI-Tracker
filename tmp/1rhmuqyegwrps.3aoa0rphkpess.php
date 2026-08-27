<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-geo-alt text-primary me-1"></i> Lembaga Pengujian Eksternal</h2>
        <p class="text-muted small mb-0">Daftar lembaga / tempat rujukan pengujian eksternal beserta alamatnya.</p>
    </div>
    <a href="<?= ($BASE) ?>/pengujian-eksternal/tambah" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Tambah Lembaga
    </a>
</div>

<!-- Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Total Lembaga</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= ($total_lembaga ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: rgba(136,19,55,.08); color: var(--color-primary);">
                <i class="bi bi-building"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
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
    <div class="col-md-4">
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
        <form action="<?= ($BASE) ?>/pengujian-eksternal" method="GET" class="row g-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama lembaga / alamat..." value="<?= ($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
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

<!-- Tabel Data -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table text-primary me-2"></i>Daftar Lembaga</h6>
        <span class="text-muted small">Total: <?= ($total_lembaga ?: 0) ?> lembaga</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:40px;">No</th>
                    <th>Nama Lembaga / Tempat</th>
                    <th>Alamat</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width:150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($daftar_lembaga && count($daftar_lembaga) > 0): ?>
                    <?php foreach (($daftar_lembaga?:[]) as $i=>$l): ?>
                        <tr>
                            <td class="text-muted"><?= ($i + 1) ?></td>
                            <td class="fw-semibold text-dark">
                                <i class="bi bi-building text-muted me-1"></i> <?= ($l['nama_lembaga'])."
" ?>
                            </td>
                            <td class="text-muted small"><?= ($l['alamat'] ?: '-') ?></td>
                            <td class="text-center">
                                <?php if ($l['status'] == 'aktif'): ?>
                                    <span class="badge badge-pill-success"><i class="bi bi-eye"></i> Aktif</span>
                                <?php endif; ?>
                                <?php if ($l['status'] != 'aktif'): ?>
                                    <span class="badge badge-pill-secondary"><i class="bi bi-eye-slash"></i> Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="<?= ($BASE) ?>/pengujian-eksternal/<?= ($l['id']) ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="<?= ($BASE) ?>/pengujian-eksternal/<?= ($l['id']) ?>/toggle-status" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                        <?php if ($l['status'] == 'aktif'): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sembunyikan">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($l['status'] != 'aktif'): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Tampilkan kembali">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <form action="<?= ($BASE) ?>/pengujian-eksternal/<?= ($l['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus lembaga ini secara permanen?');">
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
                <?php if (!$daftar_lembaga || count($daftar_lembaga) == 0): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Belum ada data lembaga pengujian eksternal.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
