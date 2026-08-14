<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Daftar Petunjuk Operasional (PO)</h3>
        <p class="text-muted mb-0">Pantau progres dokumen kerja PO dan alur status pekerjaan.</p>
    </div>
</div>

<!-- Card Filter Pencarian -->
<div class="card mb-4 shadow-sm">
    <div class="card-body bg-light rounded">
        <form method="GET" action="<?= ($BASE) ?>/po" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="bulan" class="form-label small fw-bold text-secondary">Filter Bulan</label>
                <select name="bulan" id="bulan" class="form-select form-select-sm">
                    <option value="">-- Semua Bulan --</option>
                    <?php foreach (($list_bulan?:[]) as $k=>$v): ?>
                        <option value="<?= ($k) ?>" <?= ($filter_bulan == $k ? 'selected' : '') ?>><?= ($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="tahun" class="form-label small fw-bold text-secondary">Filter Tahun</label>
                <input type="number" name="tahun" id="tahun" class="form-control form-control-sm" placeholder="Contoh: 2026" value="<?= ($filter_tahun) ?>">
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label small fw-bold text-secondary">Filter Status</label>
                <select name="status" id="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <?php foreach (($list_status?:[]) as $sk=>$sv): ?>
                        <option value="<?= ($sk) ?>" <?= ($filter_status == $sk ? 'selected' : '') ?>><?= ($sv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-filter me-1"></i> Terapkan Filter
                </button>
                <a href="<?= ($BASE) ?>/po" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar PO -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (count($daftar_po) > 0): ?>
            
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>Nomor PO</th>
                                <th>Klien</th>
                                <th>Judul Kegiatan</th>
                                <th>Status PO</th>
                                <th>Tgl Dibuat</th>
                                <th class="text-center" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ctr=0; foreach (($daftar_po?:[]) as $item): $ctr++; ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= ($ctr) ?></td>
                                    <td>
                                        <a href="<?= ($BASE) ?>/po/<?= ($item['id']) ?>" class="fw-bold text-decoration-none">
                                            <?= ($item['nomor_po'])."
" ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-dark"><?= ($item['nama_perusahaan']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-secondary small"><?= ($item['judul_kegiatan']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-status-<?= ($item['status']) ?> px-2 py-1">
                                            <?= ($list_status[$item['status']])."
" ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= (date('d/m/Y', strtotime($item['created_at'])))."
" ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= ($BASE) ?>/po/<?= ($item['id']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-x text-muted display-4 d-block mb-3"></i>
                    <h5 class="text-muted">Tidak ada dokumen PO yang sesuai</h5>
                    <p class="text-secondary small mb-0">Dokumen PO otomatis muncul ketika Order Layanan di-approve.</p>
                </div>
            
        <?php endif; ?>
    </div>
</div>
