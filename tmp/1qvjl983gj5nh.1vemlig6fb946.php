<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-text text-primary me-1"></i> Surat Penawaran Pelayanan Jasa</h2>
        <p class="text-muted small mb-0">Daftar surat penawaran layanan OPTI (Selulosa & Lingkungan) yang diajukan kepada mitra/customer.</p>
    </div>
    <a href="<?= ($BASE) ?>/surat-penawaran/tambah" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Tambah Surat Penawaran
    </a>
</div>

<!-- Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Total Surat</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= ($total_surat ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: rgba(136,19,55,.08); color: var(--color-primary);">
                <i class="bi bi-file-earmark-text"></i>
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
        <form action="<?= ($BASE) ?>/surat-penawaran" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nomor surat / perihal / nama customer..." value="<?= ($search) ?>">
                </div>
            </div>
            <div class="col-md-3">

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

<!-- Tabel Data -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table text-primary me-2"></i>Daftar Surat Penawaran</h6>
        <span class="text-muted small">Total: <?= ($total_surat ?: 0) ?> surat</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>No. Surat</th>
                    <th>Customer / Mitra</th>
                    <th>Tanggal</th>
                    <th>Perihal</th>
    
                    
                    <th class="text-center" style="width:150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($daftar_penawaran && count($daftar_penawaran) > 0): ?>
                    <?php foreach (($daftar_penawaran?:[]) as $sp): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= ($sp['nomor_surat']) ?></td>
                            <td><?= ($sp['nmcustomer']) ?></td>
                            <td><?= (date('d M Y', strtotime($sp['tanggal_surat']))) ?></td>
                            <td class="text-muted small"><?= ($sp['perihal']) ?></td>
                            <!-- <td class="text-center">
                                <?php if ($sp['status'] == 'aktif'): ?>
                                    <span class="badge badge-pill-success"><i class="bi bi-eye"></i> Aktif</span>
                                <?php endif; ?>
                                <?php if ($sp['status'] != 'aktif'): ?>
                                    <span class="badge badge-pill-secondary"><i class="bi bi-eye-slash"></i> Nonaktif</span>
                                <?php endif; ?>
                            </td> -->
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="<?= ($BASE) ?>/surat-penawaran/<?= ($sp['id']) ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= ($BASE) ?>/surat-penawaran/<?= ($sp['id']) ?>/edit" class="btn btn-sm btn-outline-success" title="Edit">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <!-- <form action="<?= ($BASE) ?>/surat-penawaran/<?= ($sp['id']) ?>/toggle-status" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                        <?php if ($sp['status'] == 'aktif'): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sembunyikan">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($sp['status'] != 'aktif'): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Tampilkan kembali">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <form action="<?= ($BASE) ?>/surat-penawaran/<?= ($sp['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus surat penawaran ini secara permanen?');">
                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form> -->
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!$daftar_penawaran || count($daftar_penawaran) == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Belum ada data surat penawaran.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
