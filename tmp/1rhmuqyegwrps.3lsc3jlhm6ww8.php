<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-collection text-primary me-1"></i> Kategori Pengujian</h2>
            <p class="text-muted small mb-0">Kelompok besar jenis uji laboratorium. Tambahkan kategori baru sebelum mengisi metode & harganya.</p>
        </div>
        <a href="<?= ($BASE) ?>/kategori-uji/tambah" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Kategori</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="<?= ($BASE) ?>/kategori-uji" method="GET" class="row g-2 align-items-center">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama kategori pengujian..." value="<?= ($search) ?>">
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
    
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table text-primary me-2"></i>Daftar Kategori Pengujian</h6>
            <span class="text-muted small">Total: <?= ($total_kategori ?: 0) ?> kategori</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>Nama Kategori Pengujian</th>
                        <th>Deskripsi Singkat</th>
                        <th class="text-center">Jumlah Metode</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width:150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daftar_kategori && count($daftar_kategori) > 0): ?>
                        <?php foreach (($daftar_kategori?:[]) as $i=>$k): ?>
                            <tr>
                                <td class="text-muted"><?= ($i + 1) ?></td>
                                <td class="fw-semibold text-dark"><?= ($k['nama_kategori']) ?></td>
                                <td class="text-muted small"><?= ($k['deskripsi'] ?: '-') ?></td>
                                <td class="text-center">
                                    <a href="<?= ($BASE) ?>/metode-uji?kategori=<?= ($k['id']) ?>" class="badge badge-pill-primary text-decoration-none">
                                        <?= ($k['jumlah_metode'] ?: 0) ?> metode
                                    </a>
                                </td>
                                <td class="text-center">
                                    <?php if ($k['status'] == 'aktif'): ?>
                                        <span class="badge badge-pill-success"><i class="bi bi-eye"></i> Aktif</span>
                                    <?php endif; ?>
                                    <?php if ($k['status'] != 'aktif'): ?>
                                        <span class="badge badge-pill-secondary"><i class="bi bi-eye-slash"></i> Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="<?= ($BASE) ?>/kategori-uji/<?= ($k['id']) ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit Kategori">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="<?= ($BASE) ?>/kategori-uji/<?= ($k['id']) ?>/toggle-status" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                            <?php if ($k['status'] == 'aktif'): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sembunyikan kategori (metode di dalamnya ikut tersembunyi)">
                                                    <i class="bi bi-eye-slash"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($k['status'] != 'aktif'): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Tampilkan kembali">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        <form action="<?= ($BASE) ?>/kategori-uji/<?= ($k['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini? Kategori hanya bisa dihapus jika belum memiliki metode uji.');">
                                            <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Kategori">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!$daftar_kategori || count($daftar_kategori) == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Belum ada kategori pengujian. Klik <strong>"Tambah Kategori"</strong> untuk memulai.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>