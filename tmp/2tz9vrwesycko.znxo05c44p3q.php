<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-text text-primary me-2"></i>Surat Pelayanan Resmi</h2>
        <p class="text-muted small mb-0">Daftar Formulir Permintaan Pelayanan Jasa &amp; Surat Penawaran Resmi Layanan OPTI BBSPJIS.</p>
    </div>
    <a href="<?= ($BASE) ?>/surat-penawaran/tambah" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-1.5 fw-semibold">
        <i class="bi bi-plus-lg"></i> Tambah Formulir Pelayanan
    </a>
</div>

<!-- Ringkasan Status Alur Pelayanan -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Total Surat Pelayanan</div>
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
                <div class="text-muted small fw-semibold mb-1">Terkirim &bull; Menunggu Respon</div>
                <div class="h4 fw-bold mb-0 text-primary"><?= ($total_terkirim ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: #eff6ff; color: #1d4ed8;">
                <i class="bi bi-send-check"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Disepakati Klien &bull; DEAL</div>
                <div class="h4 fw-bold mb-0 text-success"><?= ($total_deal ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: #ecfdf5; color: #065f46;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Pencarian & Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="<?= ($BASE) ?>/surat-penawaran" method="GET" class="row g-2 align-items-center">
            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nomor surat / perihal / nama instansi customer..." value="<?= ($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status Layanan</option>
                    <option value="draft" <?= ($filter_status == 'draft' ? 'selected' : '') ?>>Draft Formulir</option>
                    <option value="terkirim" <?= ($filter_status == 'terkirim' ? 'selected' : '') ?>>Terkirim ke Pelanggan</option>
                    <option value="deal" <?= ($filter_status == 'deal' ? 'selected' : '') ?>>Disepakati (DEAL)</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel me-1"></i> Filter</button>
                <?php if ($search || $filter_status): ?>
                    <a href="<?= ($BASE) ?>/surat-penawaran" class="btn btn-light border text-muted" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Data -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table text-primary me-2"></i>Daftar Surat Pelayanan Jasa</h6>
        <span class="text-muted small">Total: <strong><?= ($total_surat ?: 0) ?></strong> dokumen</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>No. Surat</th>
                    <th>Customer / Mitra</th>
                    <th>Tanggal</th>
                    <th>Perihal &amp; Layanan</th>
                    <th class="text-center">Status Alur</th>
                    <th class="text-center" style="width:130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($daftar_penawaran && count($daftar_penawaran) > 0): ?>
                    <?php foreach (($daftar_penawaran?:[]) as $sp): ?>
                        <tr>
                            <td>
                                <div class="fw-bold font-monospace text-dark"><?= ($sp['nomor_surat']) ?></div>
                            </td>
                            <td>
                                <strong class="text-dark"><?= ($sp['nmcustomer']) ?></strong>
                                <?php if ($sp['perusahaan'] && $sp['perusahaan'] != $sp['nmcustomer']): ?>
                                    <small class="text-muted d-block" style="font-size: 0.75rem;"><?= ($sp['perusahaan']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap small text-muted">
                                <?= (date('d M Y', strtotime($sp['tanggal_surat'])))."
" ?>
                            </td>
                            <td>
                                <div class="text-dark small fw-medium text-truncate" style="max-width: 280px;" title="<?= ($sp['perihal']) ?>">
                                    <?= ($sp['perihal'])."
" ?>
                                </div>
                                <span class="badge <?= ($sp['jenis_layanan'] == 'selulosa' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success') ?> text-uppercase mt-1" style="font-size: 0.65rem;">
                                    OPTI <?= ($sp['jenis_layanan'] ?: 'selulosa')."
" ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($sp['status_respon_klien'] == 'deal' || $sp['status'] == 'disetujui'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i> DEAL / Sepakat
                                    </span>
                                <?php endif; ?>
                                <?php if (($sp['status'] == 'terkirim' || $sp['status_respon_klien'] == 'terkirim' || $sp['status_respon_klien'] == 'menunggu') && $sp['status_respon_klien'] != 'deal' && $sp['status'] != 'disetujui'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        <i class="bi bi-send-check me-1"></i> Terkirim
                                    </span>
                                <?php endif; ?>
                                <?php if ($sp['status_respon_klien'] == 'tolak'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        <i class="bi bi-x-circle-fill me-1"></i> Ditolak
                                    </span>
                                <?php endif; ?>
                                <?php if (($sp['status'] == 'draft' || $sp['status_respon_klien'] == 'draft' || !$sp['status'] || $sp['status'] == 'nonaktif') && $sp['status_respon_klien'] != 'deal' && $sp['status'] != 'terkirim' && $sp['status_respon_klien'] != 'terkirim' && $sp['status_respon_klien'] != 'tolak' && $sp['status'] != 'disetujui'): ?>
                                    <span class="badge bg-light text-secondary border px-2 py-1">
                                        <i class="bi bi-pencil-square me-1"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <?php if ($sp['order_id']): ?>
                                        <a href="<?= ($BASE) ?>/order/<?= ($sp['order_id']) ?>" class="btn btn-sm btn-light border py-1 px-2 text-primary" title="Buka Detail Order">
                                            <i class="bi bi-folder2-open"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= ($BASE) ?>/surat-penawaran/<?= ($sp['id']) ?>/edit" class="btn btn-sm btn-light border py-1 px-2 text-secondary" title="Edit Surat Pelayanan">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($sp['order_id']): ?>
                                        <a href="<?= ($BASE) ?>/order/<?= ($sp['order_id']) ?>/penawaran/cetak" target="_blank" class="btn btn-sm btn-light border py-1 px-2 text-danger" title="Cetak Surat Penawaran PDF">
                                            <i class="bi bi-file-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!$daftar_penawaran || count($daftar_penawaran) == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted opacity-50"></i>
                            Belum ada data surat pelayanan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
