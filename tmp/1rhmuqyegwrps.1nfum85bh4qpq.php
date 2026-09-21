<style>
.filter-tab-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.filter-tab-pill:hover {
    color: #0f172a;
    background-color: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.filter-tab-pill .count-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}

/* Active State: Semua */
.filter-tab-pill.active-all {
    color: #ffffff !important;
    background-color: var(--color-primary, #881337) !important;
    border-color: var(--color-primary, #881337) !important;
    box-shadow: 0 2px 6px rgba(136, 19, 55, 0.25) !important;
}
.filter-tab-pill.active-all .count-chip {
    background-color: rgba(255, 255, 255, 0.25) !important;
    border-color: transparent !important;
    color: #ffffff !important;
}

/* Active State: Draf PIC */
.filter-tab-pill.active-draft {
    color: #78350f !important;
    background-color: #fef3c7 !important;
    border-color: #fcd34d !important;
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.15) !important;
}
.filter-tab-pill.active-draft .count-chip {
    background-color: #d97706 !important;
    border-color: #b45309 !important;
    color: #ffffff !important;
}

/* Active State: Menunggu Review */
.filter-tab-pill.active-review {
    color: #0369a1 !important;
    background-color: #e0f2fe !important;
    border-color: #7dd3fc !important;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.15) !important;
}
.filter-tab-pill.active-review .count-chip {
    background-color: #0284c7 !important;
    border-color: #0369a1 !important;
    color: #ffffff !important;
}

/* Active State: Disetujui */
.filter-tab-pill.active-approved {
    color: #14532d !important;
    background-color: #dcfce7 !important;
    border-color: #86efac !important;
    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.15) !important;
}
.filter-tab-pill.active-approved .count-chip {
    background-color: #16a34a !important;
    border-color: #15803d !important;
    color: #ffffff !important;
}

/* Active State: Perlu Revisi */
.filter-tab-pill.active-revisi {
    color: #881337 !important;
    background-color: #ffe4e6 !important;
    border-color: #fca5a5 !important;
    box-shadow: 0 2px 6px rgba(225, 29, 72, 0.15) !important;
}
.filter-tab-pill.active-revisi .count-chip {
    background-color: #e11d48 !important;
    border-color: #be123c !important;
    color: #ffffff !important;
}
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="h4 fw-bold mb-0 text-dark font-display">Pusat Tugas Proposal Teknis</h2>
            <?php if ($is_tim_kerja): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 0.72rem;">
                    <i class="bi bi-person-check-fill me-1"></i> Penugasan Akun Anda
                </span>
            <?php endif; ?>
        </div>
        <p class="text-muted small mb-0">Daftar penugasan proposal teknis dan rancangan anggaran biaya (RAB) layanan OPTI.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$is_tim_kerja): ?>
            <a href="<?= ($BASE) ?>/disposisi-masuk" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold">
                <i class="bi bi-inbox me-1"></i> Permintaan Masuk
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- 4 KARTU METRIK KPI RINGKASAN -->
<!-- ======================================================== -->
<div class="row g-3 mb-4">
    <!-- Draf PIC -->
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div>
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Draf PIC</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= ($stat_draft) ?></h3>
                <span class="small text-muted" style="font-size: 0.75rem;">Perlu disusun / diunggah</span>
            </div>
            <div class="metric-icon-box" style="background-color: #fef3c7; color: #d97706;">
                <i class="bi bi-pencil-square"></i>
            </div>
        </div>
    </div>

    <!-- Menunggu Review Ka. Tim -->
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div>
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Menunggu Ka. Tim</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= ($stat_diajukan) ?></h3>
                <span class="small text-muted" style="font-size: 0.75rem;">Diajukan untuk review</span>
            </div>
            <div class="metric-icon-box" style="background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>

    <!-- Disetujui Ka. Tim -->
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div>
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Disetujui Ka. Tim</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= ($stat_disetujui) ?></h3>
                <span class="small text-muted" style="font-size: 0.75rem;">Siap Surat Penawaran</span>
            </div>
            <div class="metric-icon-box" style="background-color: #dcfce7; color: #16a34a;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
        </div>
    </div>

    <!-- Perlu Revisi -->
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div>
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Perlu Revisi</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= ($stat_ditolak) ?></h3>
                <span class="small text-muted" style="font-size: 0.75rem;">Catatan koreksi Ka. Tim</span>
            </div>
            <div class="metric-icon-box" style="background-color: #fee2e2; color: #dc2626;">
                <i class="bi bi-exclamation-diamond-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- TOOLBAR FILTER STATUS & PENCARIAN -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-stretch align-items-lg-center gap-3">
            <!-- Filter Tabs Container dengan spacing lega (gap-2) -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Semua -->
                <a href="<?= ($BASE) ?>/proposal?status=semua<?= ($filter_q ? '&q='.$filter_q : '') ?>" 
                   class="filter-tab-pill <?= ($filter_status == 'semua' ? 'active-all' : '') ?>">
                    <i class="bi bi-grid-fill"></i>
                    <span>Semua</span>
                    <span class="count-chip"><?= ($total_proposal) ?></span>
                </a>

                <!-- Draft Disimpan -->
                <a href="<?= ($BASE) ?>/proposal?status=draft<?= ($filter_q ? '&q='.$filter_q : '') ?>" 
                   class="filter-tab-pill <?= ($filter_status == 'draft' || $filter_status == 'draft_disimpan' ? 'active-draft' : '') ?>">
                    <i class="bi bi-bookmark-check"></i>
                    <span>Draft Disimpan</span>
                    <span class="count-chip"><?= ($stat_draft) ?></span>
                </a>

                <!-- Menunggu Review -->
                <a href="<?= ($BASE) ?>/proposal?status=diajukan<?= ($filter_q ? '&q='.$filter_q : '') ?>" 
                   class="filter-tab-pill <?= ($filter_status == 'diajukan' ? 'active-review' : '') ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Menunggu Review</span>
                    <span class="count-chip"><?= ($stat_diajukan) ?></span>
                </a>

                <!-- Disetujui -->
                <a href="<?= ($BASE) ?>/proposal?status=disetujui<?= ($filter_q ? '&q='.$filter_q : '') ?>" 
                   class="filter-tab-pill <?= ($filter_status == 'disetujui' ? 'active-approved' : '') ?>">
                    <i class="bi bi-check2-circle"></i>
                    <span>Disetujui</span>
                    <span class="count-chip"><?= ($stat_disetujui) ?></span>
                </a>

                <!-- Perlu Revisi -->
                <a href="<?= ($BASE) ?>/proposal?status=ditolak<?= ($filter_q ? '&q='.$filter_q : '') ?>" 
                   class="filter-tab-pill <?= ($filter_status == 'ditolak' ? 'active-revisi' : '') ?>">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Perlu Revisi</span>
                    <span class="count-chip"><?= ($stat_ditolak) ?></span>
                </a>
            </div>

            <!-- Search Box Form -->
            <form action="<?= ($BASE) ?>/proposal" method="GET" class="d-flex align-items-center gap-2" style="min-width: 250px;">
                <input type="hidden" name="status" value="<?= ($filter_status) ?>">
                <div class="input-group input-group-sm w-100">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Cari nomor order, klien..." value="<?= ($filter_q) ?>">
                    <?php if ($filter_q): ?>
                        <a href="<?= ($BASE) ?>/proposal?status=<?= ($filter_status) ?>" class="btn btn-sm btn-light border border-start-0 text-muted" title="Hapus Filter">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- TABEL DATA TUGAS PROPOSAL -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="bg-light text-secondary" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;">
                <tr>
                    <th class="py-3 px-4" style="width: 140px;">No. Order</th>
                    <th class="py-3 px-3">Instansi Pelanggan</th>
                    <th class="py-3 px-3">Judul Kegiatan / Proposal</th>
                    <?php if (!$is_tim_kerja): ?>
                        <th class="py-3 px-3">PIC Peneliti</th>
                    <?php endif; ?>
                    <th class="py-3 px-3">Estimasi Biaya</th>
                    <th class="py-3 px-3 text-center">Berkas</th>
                    <th class="py-3 px-3 text-center">Status Proposal</th>
                    <th class="py-3 px-4 text-end" style="width: 150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($list_proposal) > 0): ?>
                    
                        <?php foreach (($list_proposal?:[]) as $row): ?>
                            <tr>
                                <!-- No. Order -->
                                <td class="py-3 px-4">
                                    <a href="<?= ($BASE) ?>/order/<?= ($row['id']) ?>/proposal" class="fw-bold font-monospace text-primary text-decoration-none d-block">
                                        <?= ($row['nomor_order'])."
" ?>
                                    </a>
                                    <small class="text-muted" style="font-size: 0.72rem;"><?= (date('d M Y', strtotime($row['tanggal_masuk']))) ?></small>
                                </td>

                                <!-- Pelanggan & Divisi -->
                                <td class="py-3 px-3">
                                    <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= ($row['nama_perusahaan']) ?></div>
                                    <span class="badge <?= ($row['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success') ?> px-2 py-0.5 rounded-pill" style="font-size: 0.68rem;">
                                        OPTI <?= (ucfirst($row['jenis_layanan_opti']))."
" ?>
                                    </span>
                                </td>

                                <!-- Judul Kegiatan / Proposal -->
                                <td class="py-3 px-3">
                                    <div class="text-dark fw-medium" style="font-size: 0.84rem; max-width: 280px;">
                                        <?= ($row['judul_proposal'] ?: $row['judul_kegiatan'])."
" ?>
                                    </div>
                                    <small class="text-muted fst-italic" style="font-size: 0.72rem;">Durasi: <?= ($row['durasi_kegiatan'] ?: '30 Hari Kerja') ?></small>
                                </td>

                                <!-- PIC (jika Ka Tim / Admin) -->
                                <?php if (!$is_tim_kerja): ?>
                                    <td class="py-3 px-3">
                                        <span class="text-dark small fw-semibold"><?= ($row['pic_nama'] ?: 'Belum Ditunjuk') ?></span>
                                    </td>
                                <?php endif; ?>

                                <!-- Estimasi Biaya (RAB) -->
                                <td class="py-3 px-3 font-monospace fw-bold text-dark" style="font-size: 0.84rem;">
                                    Rp <?= (number_format($row['estimasi_total_biaya'] ?: 0, 0, ',', '.'))."
" ?>
                                </td>

                                <!-- Berkas Upload -->
                                <td class="py-3 px-3 text-center">
                                    <?php if ($row['file_proposal']): ?>
                                        
                                            <a href="<?= ($BASE) ?>/<?= ($row['file_proposal']) ?>" target="_blank" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-pill shadow-xs" title="Unduh Berkas Proposal">
                                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                            </a>
                                        
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">-</span>
                                        
                                    <?php endif; ?>
                                </td>

                                <!-- Status Proposal -->
                                <td class="py-3 px-3 text-center">
                                    <?php if ($row['status_proposal'] == 'disetujui_ketua'): ?>
                                        <span class="badge badge-pill-success">
                                            <i class="bi bi-check-circle-fill me-1"></i> Disetujui Ka. Tim
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($row['status_proposal'] == 'diajukan'): ?>
                                        <span class="badge badge-pill-info">
                                            <i class="bi bi-clock-history me-1"></i> Menunggu Review
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($row['status_proposal'] == 'ditolak'): ?>
                                        <span class="badge badge-pill-danger">
                                            <i class="bi bi-exclamation-diamond-fill me-1"></i> Perlu Revisi
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($row['status_proposal'] == 'draft_disimpan'): ?>
                                        <span class="badge badge-pill-warning">
                                            <i class="bi bi-bookmark-check-fill me-1"></i> Draft Disimpan
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$row['status_proposal'] || $row['status_proposal'] == 'draft'): ?>
                                        <span class="badge badge-pill-secondary">
                                            <i class="bi bi-pencil-square me-1"></i> Draf PIC
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3 px-4 text-end">
                                    <a href="<?= ($BASE) ?>/order/<?= ($row['id']) ?>/proposal" class="btn btn-primary btn-sm px-3 py-1 rounded-pill fw-semibold text-white shadow-xs d-inline-flex align-items-center gap-1" style="font-size: 0.76rem;">
                                        <i class="bi <?= ($is_tim_kerja ? 'bi-pencil-fill' : 'bi-journal-check') ?>"></i>
                                        <?= ($is_tim_kerja ? 'Kerjakan' : 'Kelola')."
" ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= ($is_tim_kerja ? '7' : '8') ?>" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="bi bi-folder2-open fs-1 text-secondary opacity-50 mb-2 d-block"></i>
                                    <h6 class="fw-bold text-dark mb-1 font-display">Belum Ada Tugas Proposal</h6>
                                    <p class="small text-secondary mb-0">
                                        <?php if ($is_tim_kerja): ?>
                                            Anda belum memiliki tugas penyusunan proposal aktif yang didelegasikan oleh Ketua Tim OPTI.
                                            <?php else: ?>Tidak ada order yang sedang dalam tahap penyusunan proposal teknis.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>