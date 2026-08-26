<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark">Monitoring Petunjuk Operasional (PO)</h2>
        <p class="text-muted small mb-0">Pelacakan administrasi, evaluasi pelaksanaan, dan pengawasan batas waktu proyek OPTI BBSPJIS.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= ($BASE) ?>/po/ekspor" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel"></i> Ekspor Rekap Excel
        </a>
        <a href="<?= ($BASE) ?>/order/tambah" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Order Baru
        </a>
    </div>
</div>

<!-- ======================================================== -->
<!-- 4 KARTU METRIK KPI RINGKASAN -->
<!-- ======================================================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div>
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">PO Aktif Berjalan</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= ($po_berjalan_count) ?></h3>
                <span class="small text-muted" style="font-size: 0.75rem;">Dokumen dalam proses</span>
            </div>
            <div class="metric-icon-box" style="background-color: rgba(136, 19, 55, 0.1); color: #881337;">
                <i class="bi bi-speedometer2"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div>
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Order Masuk Baru</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= ($order_baru_count) ?></h3>
                <span class="small text-muted" style="font-size: 0.75rem;">Menunggu approval</span>
            </div>
            <div class="metric-icon-box" style="background-color: #fef3c7; color: #d97706;">
                <i class="bi bi-inbox-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div>
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">OPTI Selulosa</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= ($selulosa_count) ?></h3>
                <span class="small text-muted" style="font-size: 0.75rem;">Katim: <?= ($katim_selulosa_nama) ?></span>
            </div>
            <div class="metric-icon-box" style="background-color: #ffe4e6; color: #e11d48;">
                <i class="bi bi-tree-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="metric-card">
            <div>
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">OPTI Lingkungan</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= ($lingkungan_count) ?></h3>
                <span class="small text-muted" style="font-size: 0.75rem;">Katim: <?= ($katim_lingkungan_nama) ?></span>
            </div>
            <div class="metric-icon-box" style="background-color: #ccfbf1; color: #0d9488;">
                <i class="bi bi-water"></i>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- FILTER MULTI-DIMENSI MONITORING -->
<!-- ======================================================== -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="<?= ($BASE) ?>/po" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="q" placeholder="Cari nomor PO, mitra, judul..." value="<?= ($search_q) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="jenis_layanan">
                    <option value="">Semua Divisi</option>
                    <option value="selulosa" <?= ($filter_jenis_layanan == 'selulosa' ? 'selected' : '') ?>>Selulosa</option>
                    <option value="lingkungan" <?= ($filter_jenis_layanan == 'lingkungan' ? 'selected' : '') ?>>Lingkungan</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="status">
                    <option value="">Semua Status PO</option>
                    <?php foreach (($list_status?:[]) as $statKey=>$statLabel): ?>
                        <option value="<?= ($statKey) ?>" <?= ($filter_status == $statKey ? 'selected' : '') ?>>
                            <?= ($statLabel)."
" ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="bulan">
                    <option value="">Semua Bulan</option>
                    <?php foreach (($list_bulan?:[]) as $bKey=>$bVal): ?>
                        <option value="<?= ($bKey) ?>" <?= ($filter_bulan == $bKey ? 'selected' : '') ?>>
                            <?= ($bVal)."
" ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <select class="form-select form-select-sm" name="overdue">
                    <option value="">Semua Waktu</option>
                    <option value="telat" <?= ($filter_overdue == 'telat' ? 'selected' : '') ?>>Hanya PO Terlambat</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary px-3 flex-shrink-0">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="<?= ($BASE) ?>/po" class="btn btn-sm btn-outline-secondary px-2 flex-shrink-0" title="Reset Filter">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- TABEL DAFTAR REKAP PO (FULL DISPLAY, TIDAK PERLU SCROLL) -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-ruled text-primary"></i> Daftar Dokumen Petunjuk Operasional
        </h6>
        <span class="badge bg-light text-muted border"><?= (is_array($daftar_po) ? count($daftar_po) : 0) ?> Dokumen Terdaftar</span>
    </div>
    <div class="card-body p-0">
        <?php if ($daftar_po && count($daftar_po) > 0): ?>
            
                <div class="table-responsive" style="overflow-x: hidden;">
                    <table class="table table-hover align-middle w-100 mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 3%; min-width: 35px;">No</th>
                                <th style="width: 17%;">Dokumen PO & Tanggal</th>
                                <th style="width: 32%;">Proyek & Mitra Industri</th>
                                <th style="width: 13%;">Divisi & Tim</th>
                                <th class="text-end" style="width: 15%;">Nilai & Pembayaran</th>
                                <th style="width: 13%;">Status & Waktu</th>
                                <th class="text-center" style="width: 7%; min-width: 65px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ctr=0; foreach (($daftar_po?:[]) as $p): $ctr++; ?>
                                <tr>
                                    <!-- 1. No -->
                                    <td class="text-center text-muted small fw-semibold"><?= ($ctr) ?></td>

                                    <!-- 2. Dokumen PO & Tanggal -->
                                    <td>
                                        <a href="<?= ($BASE) ?>/po/<?= ($p['id']) ?>" class="fw-bold text-primary text-decoration-none d-block mb-1 text-truncate" title="<?= ($p['nomor_po']) ?>">
                                            <?= ($p['nomor_po'])."
" ?>
                                        </a>
                                        <div class="text-muted small d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-calendar3 text-secondary"></i>
                                            <span>Masuk: <?= ($p['tanggal_masuk'] ? date('d/m/Y', strtotime($p['tanggal_masuk'])) : '-') ?></span>
                                        </div>
                                    </td>

                                    <!-- 3. Proyek & Mitra Industri -->
                                    <td>
                                        <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= ($p['judul_kegiatan']) ?>">
                                            <?= ($p['judul_kegiatan'])."
" ?>
                                        </div>
                                        <div class="small text-muted d-flex align-items-center gap-1 text-truncate" style="font-size: 0.775rem;">
                                            <i class="bi bi-building text-secondary"></i>
                                            <?php if ($mask_client_name): ?>
                                                
                                                    <?php $words = explode(' ', $p['nama_perusahaan']);
                                                        $masked = array_map(function($w) {
                                                            return mb_strlen($w) > 1 ? mb_substr($w, 0, 1) . '***' : $w;
                                                        }, $words);
                                                        $namaTampil = implode(' ', $masked); ?>
                                                    <span title="Nama disamarkan untuk privasi" class="fw-semibold"><?= ($namaTampil) ?> (<?= ($p['pt_cv']) ?>)</span>
                                                
                                                <?php else: ?>
                                                    <span class="fw-semibold"><?= ($p['nama_perusahaan']) ?> (<?= ($p['pt_cv']) ?>)</span>
                                                
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 4. Divisi & Tim Pelaksana -->
                                    <td>
                                        <div class="mb-1">
                                            <?php if ($p['jenis_layanan_opti'] == 'selulosa'): ?>
                                                <span class="badge badge-pill-danger">Selulosa</span>
                                            <?php endif; ?>
                                            <?php if ($p['jenis_layanan_opti'] == 'lingkungan'): ?>
                                                <span class="badge badge-pill-success">Lingkungan</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small text-truncate" style="font-size: 0.75rem;" title="<?= ($p['tim_kerja']) ?>">
                                            <i class="bi bi-person me-1"></i><?= ($p['tim_kerja'] ?: 'Tim Balai')."
" ?>
                                        </div>
                                    </td>

                                    <!-- 5. Nilai & Pembayaran -->
                                    <td class="text-end">
                                        <div class="fw-bold text-dark small mb-1">
                                            Rp <?= (number_format($p['biaya'], 0, ',', '.'))."
" ?>
                                        </div>
                                        <div>
                                            <?php if ((float)$p['total_terbayar'] >= (float)$p['biaya'] && (float)$p['biaya'] > 0): ?>
                                                <span class="badge badge-pill-success" style="font-size: 0.675rem;">
                                                    <i class="bi bi-check2"></i> Lunas
                                                </span>
                                            <?php endif; ?>
                                            <?php if ((float)$p['total_terbayar'] < (float)$p['biaya']): ?>
                                                <span class="badge badge-pill-warning" style="font-size: 0.675rem;" title="Terbayar: Rp <?= (number_format($p['total_terbayar'], 0, ',', '.')) ?>">
                                                    Bayar: Rp <?= (number_format($p['total_terbayar'], 0, ',', '.'))."
" ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 6. Status & Batas Waktu -->
                                    <td>
                                        <div class="mb-2">
                                            <?php if ($p['status'] == 'belum_upload'): ?>
                                                <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" style="font-size: 0.72rem;">
                                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem; opacity: 0.6;"></i>Menunggu Upload
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($p['status'] == 'sudah_upload'): ?>
                                                <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.72rem; background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                                                    <i class="bi bi-file-earmark-check me-1"></i>Dokumen Terunggah
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($p['status'] == 'on_proses'): ?>
                                                <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.72rem; background-color: rgba(136, 19, 55, 0.08); color: #881337; border: 1px solid rgba(136, 19, 55, 0.2);">
                                                    <i class="bi bi-gear-fill me-1" style="font-size: 0.6rem;"></i>Sedang Dikerjakan
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($p['status'] == 'kembali_selesai'): ?>
                                                <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.72rem; background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Selesai (BAST)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small d-flex flex-column gap-1" style="font-size: 0.72rem; line-height: 1.25;">
                                            <div class="text-muted d-flex align-items-center gap-1">
                                                <i class="bi bi-calendar3" style="font-size: 0.7rem;"></i>
                                                <span>Target: <strong class="text-dark"><?= ($p['target_selesai'] ? date('d/m/Y', strtotime($p['target_selesai'])) : '-') ?></strong></span>
                                            </div>
                                            <?php if ($p['status'] != 'kembali_selesai'): ?>
                                                <?php if ($p['overdue_info']['is_overdue']): ?>
                                                    
                                                        <span class="text-danger fw-bold d-flex align-items-center gap-1">
                                                            <i class="bi bi-exclamation-triangle-fill"></i> Telat <?= ($p['overdue_info']['days']) ?> hari
                                                        </span>
                                                    
                                                    <?php else: ?>
                                                        <span class="text-secondary d-flex align-items-center gap-1">
                                                            <i class="bi bi-clock"></i> Sisa <strong class="text-dark"><?= ($p['overdue_info']['days']) ?> hari</strong>
                                                        </span>
                                                    
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($p['status'] == 'kembali_selesai'): ?>
                                                <span class="text-success fw-medium d-flex align-items-center gap-1">
                                                    <i class="bi bi-check2-all text-success"></i> Tuntas
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 7. Tombol Aksi -->
                                    <td class="text-center">
                                        <a href="<?= ($BASE) ?>/po/<?= ($p['id']) ?>" class="btn btn-sm btn-primary py-1 px-2" style="font-size: 0.775rem;" title="Buka Detail PO & Map Kendali">
                                            <i class="bi bi-eye"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-journal-x text-muted display-4 d-block mb-3"></i>
                    <h5 class="fw-bold text-dark">Tidak ada data dokumen PO</h5>
                    <p class="text-muted small mb-3">Sesuaikan filter pencarian atau setujui Order Layanan yang masuk.</p>
                    <a href="<?= ($BASE) ?>/order" class="btn btn-sm btn-primary">
                        <i class="bi bi-inbox me-1"></i> Buka Order Masuk
                    </a>
                </div>
            
        <?php endif; ?>
    </div>
</div>
