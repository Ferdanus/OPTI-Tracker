<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark">
            <i class="bi bi-wallet2 text-primary me-2"></i>Rekapitulasi Keuangan & Pembayaran
        </h2>
        <p class="text-muted small mb-0">Pencatatan pembayaran multi-termin (DP, cicilan, pelunasan) layanan jasa OPTI BBSPJIS.</p>
    </div>
    <a href="<?= ($BASE) ?>/pembayaran/tambah" class="btn btn-primary shadow-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-cash-stack"></i> Catat Pembayaran Baru
    </a>
</div>

<!-- ======================================================== -->
<!-- 3 KARTU METRIK KEUANGAN & PROGRESS REALISASI -->
<!-- ======================================================== -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card bg-white p-3 p-md-4 rounded-3 border shadow-sm h-100 d-flex justify-content-between align-items-start">
            <div class="w-100 pe-2">
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Tagihan PO</span>
                <h3 class="fw-bold text-dark mb-1 mt-1">Rp <?= (number_format($total_tagihan, 0, ',', '.')) ?></h3>
                <div class="small text-muted d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                    <i class="bi bi-file-earmark-check text-primary"></i>
                    <span><?= (count($rekap_po ?: [])) ?> Dokumen Petunjuk Operasional Resmi</span>
                </div>
            </div>
            <div class="metric-icon-box bg-primary bg-opacity-10 text-primary p-3 rounded-3 flex-shrink-0">
                <i class="bi bi-receipt fs-4"></i>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="metric-card bg-white p-3 p-md-4 rounded-3 border shadow-sm h-100 d-flex justify-content-between align-items-start">
            <div class="w-100 pe-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Realisasi Kas Masuk</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold" style="font-size: 0.7rem;">
                        <?= ($persen_realisasi) ?>% Terbayar
                    </span>
                </div>
                <h3 class="fw-bold text-success mb-1 mt-1">Rp <?= (number_format($total_terbayar, 0, ',', '.')) ?></h3>
                
                <div class="progress my-2 bg-light" style="height: 6px;">
                    <div class="progress-bar bg-success rounded" role="progressbar" style="width: <?= ($persen_realisasi) ?>%;" aria-valuenow="<?= ($persen_realisasi) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="small text-muted d-flex justify-content-between" style="font-size: 0.75rem;">
                    <span><i class="bi bi-check2-circle text-primary me-1"></i><?= ($count_lunas) ?> PO Lunas</span>
                    <span><i class="bi bi-clock-history text-primary me-1"></i><?= ($count_sebagian) ?> PO Cicilan/DP</span>
                </div>
            </div>
            <div class="metric-icon-box bg-primary bg-opacity-10 text-primary p-3 rounded-3 flex-shrink-0 ms-2">
                <i class="bi bi-cash-coin fs-4"></i>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="metric-card bg-white p-3 p-md-4 rounded-3 border shadow-sm h-100 d-flex justify-content-between align-items-start">
            <div class="w-100 pe-2">
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Sisa Piutang</span>
                <h3 class="fw-bold <?= ($sisa_piutang > 0 ? 'text-danger' : 'text-success') ?> mb-1 mt-1">
                    Rp <?= (number_format($sisa_piutang, 0, ',', '.'))."
" ?>
                </h3>
                <div class="small text-muted d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                    <?php if ($sisa_piutang > 0): ?>
                        
                            <i class="bi bi-exclamation-circle text-primary"></i>
                            <span class="text-secondary fw-semibold"><?= ($count_sebagian + $count_belum) ?> PO</span> masih memiliki sisa tagihan
                        
                        <?php else: ?>
                            <i class="bi bi-check-all text-primary"></i>
                            <span class="text-secondary fw-semibold">Semua tagihan lunas</span>
                        
                    <?php endif; ?>
                </div>
            </div>
            <div class="metric-icon-box bg-primary bg-opacity-10 text-primary p-3 rounded-3 flex-shrink-0">
                <i class="bi bi-wallet2 fs-4"></i>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- 1. TABEL STATUS PELUNASAN PER PETUNJUK OPERASIONAL (PO) -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark">
            <i class="bi bi-pie-chart text-primary me-2"></i>Status Pelunasan Tagihan per Petunjuk Operasional (PO)
        </h6>
        <span class="badge bg-light text-muted border"><?= (count($rekap_po ?: [])) ?> Dokumen PO</span>
    </div>
    <div class="card-body p-0">
        <?php if (count($rekap_po ?: []) > 0): ?>
            
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-center" style="width: 45px;">No</th>
                                <th style="width: 170px;">Dokumen PO & Divisi</th>
                                <th>Mitra Industri & Judul Kegiatan</th>
                                <th class="text-end" style="width: 140px;">Total Nilai PO</th>
                                <th style="width: 190px;">Dana Masuk & Termin</th>
                                <th class="text-end" style="width: 140px;">Sisa Piutang</th>
                                <th class="text-center" style="width: 130px;">Status Bayar</th>
                                <th class="text-center" style="width: 130px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ctr=0; foreach (($rekap_po?:[]) as $rp): $ctr++; ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= ($ctr) ?></td>
                                    <td>
                                        <div class="fw-bold text-primary small mb-1">
                                            <a href="<?= ($BASE) ?>/po/<?= ($rp['po_id']) ?>" class="text-decoration-none text-primary">
                                                <?= ($rp['nomor_po'])."
" ?>
                                            </a>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            <?php if ($rp['jenis_layanan_opti'] == 'selulosa'): ?>
                                                <span class="badge badge-pill-danger" style="font-size: 0.68rem;">Selulosa</span>
                                            <?php endif; ?>
                                            <?php if ($rp['jenis_layanan_opti'] == 'lingkungan'): ?>
                                                <span class="badge badge-pill-success" style="font-size: 0.68rem;">Lingkungan</span>
                                            <?php endif; ?>
                                            <span class="text-muted" style="font-size: 0.72rem;"><?= ($rp['nomor_order']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark mb-1" title="<?= ($rp['judul_kegiatan']) ?>">
                                            <?= ($rp['judul_kegiatan'])."
" ?>
                                        </div>
                                        <div class="small text-muted d-flex align-items-center gap-1">
                                            <i class="bi bi-building text-secondary"></i>
                                            <?php if ($mask_client_name): ?>
                                                
                                                    <?php $words = explode(' ', $rp['nama_perusahaan']);
                                                        $masked = array_map(function($w) {
                                                            return mb_strlen($w) > 1 ? mb_substr($w, 0, 1) . '***' : $w;
                                                        }, $words);
                                                        $namaTampil = implode(' ', $masked); ?>
                                                    <span><?= ($namaTampil) ?></span>
                                                
                                                <?php else: ?>
                                                    <span class="text-secondary fw-medium"><?= ($rp['nama_perusahaan']) ?> (<?= ($rp['pt_cv']) ?>)</span>
                                                
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold text-dark small">
                                        Rp <?= (number_format($rp['biaya'], 0, ',', '.'))."
" ?>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold text-success small">Rp <?= (number_format($rp['total_dibayar'], 0, ',', '.')) ?></span>
                                            <span class="text-muted" style="font-size: 0.72rem;"><?= ($rp['persen_lunas']) ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar <?= ($rp['persen_lunas'] >= 100 ? 'bg-success' : 'bg-warning') ?>" role="progressbar" style="width: <?= ($rp['persen_lunas']) ?>%;"></div>
                                        </div>
                                        <div class="text-muted mt-1" style="font-size: 0.7rem;">
                                            <i class="bi bi-layers text-muted me-1"></i><?= ($rp['jml_termin']) ?> kali pembayaran
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold <?= ($rp['sisa_piutang'] > 0 ? 'text-danger' : 'text-success') ?> small">
                                        Rp <?= (number_format($rp['sisa_piutang'], 0, ',', '.'))."
" ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rp['status_lunas'] == 'lunas'): ?>
                                            <span class="badge badge-pill-success">
                                                <i class="bi bi-check-circle-fill me-1"></i>Lunas 100%
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($rp['status_lunas'] == 'sebagian'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                <i class="bi bi-hourglass-split me-1"></i>Sebagian (DP)
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($rp['status_lunas'] == 'belum'): ?>
                                            <span class="badge badge-pill-danger">
                                                <i class="bi bi-x-circle me-1"></i>Belum Bayar
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <?php if ($rp['sisa_piutang'] > 0): ?>
                                                <a href="<?= ($BASE) ?>/pembayaran/tambah?order_id=<?= ($rp['order_id']) ?>" class="btn btn-sm btn-success py-1 px-2 d-inline-flex align-items-center gap-1" title="Catat Cicilan / Pelunasan">
                                                    <i class="bi bi-plus-circle"></i> <span>Bayar</span>
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= ($BASE) ?>/po/<?= ($rp['po_id']) ?>" class="btn btn-sm btn-outline-primary py-1 px-2 d-inline-flex align-items-center gap-1" title="Lihat Lembar PO">
                                                <i class="bi bi-file-earmark-text"></i> <span>PO</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php else: ?>
                <div class="text-center py-4 text-muted small">
                    Belum ada data dokumen Petunjuk Operasional (PO) yang terdaftar.
                </div>
            
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- 2. TABEL BUKU KAS & HISTORI TRANSAKSI PEMBAYARAN -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
            <h6 class="m-0 fw-bold text-dark">
                <i class="bi bi-receipt text-primary me-2"></i>Histori Buku Kas & Transaksi Pembayaran
            </h6>
            <span class="badge bg-light text-muted border"><?= (count($daftar_pembayaran ?: [])) ?> Transaksi Tercatat</span>
        </div>

        <!-- Filter & Search Bar Transaksi -->
        <form method="GET" action="<?= ($BASE) ?>/pembayaran" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="q" placeholder="Cari nomor PO, order, mitra, keterangan..." value="<?= ($search_q) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select form-select-sm" name="jenis_layanan">
                    <option value="">Semua Divisi Layanan</option>
                    <option value="selulosa" <?= ($filter_jenis_layanan == 'selulosa' ? 'selected' : '') ?>>OPTI Selulosa</option>
                    <option value="lingkungan" <?= ($filter_jenis_layanan == 'lingkungan' ? 'selected' : '') ?>>OPTI Lingkungan</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="<?= ($BASE) ?>/pembayaran" class="btn btn-sm btn-outline-secondary px-2" title="Reset Filter">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (count($daftar_pembayaran ?: []) > 0): ?>
            
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-center" style="width: 45px;">No</th>
                                <th style="width: 120px;">Tanggal Bayar</th>
                                <th style="width: 170px;">Dokumen PO & Order</th>
                                <th>Mitra Industri & Kegiatan</th>
                                <th class="text-center" style="width: 130px;">Tahap Termin</th>
                                <th class="text-end" style="width: 160px;">Nominal Transaksi</th>
                                <th>Keterangan / Catatan Transaksi</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ctr=0; foreach (($daftar_pembayaran?:[]) as $bayar): $ctr++; ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= ($ctr) ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark small"><?= (date('d/m/Y', strtotime($bayar['tanggal_bayar']))) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            <i class="bi bi-clock me-1"></i>Kas Masuk
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($bayar['nomor_po']): ?>
                                            
                                                <div class="fw-bold text-primary small mb-1">
                                                    <a href="<?= ($BASE) ?>/po/<?= ($bayar['po_id_real'] ?: $bayar['po_id']) ?>" class="text-decoration-none text-primary">
                                                        <?= ($bayar['nomor_po'])."
" ?>
                                                    </a>
                                                </div>
                                            
                                            <?php else: ?>
                                                <div class="fw-bold text-muted small mb-1">-</div>
                                            
                                        <?php endif; ?>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            <?= ($bayar['nomor_order'])."
" ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark small mb-1">
                                            <?= ($bayar['judul_kegiatan'])."
" ?>
                                        </div>
                                        <div class="small text-muted d-flex align-items-center gap-1">
                                            <i class="bi bi-building text-secondary"></i>
                                            <?php if ($mask_client_name): ?>
                                                
                                                    <?php $words = explode(' ', $bayar['nama_perusahaan']);
                                                        $masked = array_map(function($w) {
                                                            return mb_strlen($w) > 1 ? mb_substr($w, 0, 1) . '***' : $w;
                                                        }, $words);
                                                        $namaTampil = implode(' ', $masked); ?>
                                                    <span><?= ($namaTampil) ?></span>
                                                
                                                <?php else: ?>
                                                    <span class="text-secondary fw-medium"><?= ($bayar['nama_perusahaan']) ?> (<?= ($bayar['pt_cv']) ?>)</span>
                                                
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-pill-primary px-2 py-1">
                                            Termin Ke-<?= ($bayar['termin_ke'])."
" ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-success small">
                                            Rp <?= (number_format($bayar['jumlah'], 0, ',', '.'))."
" ?>
                                        </div>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size: 0.68rem;">
                                            <i class="bi bi-check-circle-fill me-1"></i>Terverifikasi
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small text-dark mb-0">
                                            <?= ($bayar['keterangan'] ?: 'Pembayaran layanan jasa OPTI')."
" ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <?php if ($bayar['po_id_real'] || $bayar['po_id']): ?>
                                                <a href="<?= ($BASE) ?>/po/<?= ($bayar['po_id_real'] ?: $bayar['po_id']) ?>" class="btn btn-sm btn-light border py-1 px-2 text-primary" title="Buka Dokumen PO">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </a>
                                            <?php endif; ?>
                                            <form action="<?= ($BASE) ?>/pembayaran/<?= ($bayar['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus pencatatan transaksi pembayaran ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                <button type="submit" class="btn btn-sm btn-light border py-1 px-2 text-danger" title="Hapus Transaksi">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-wallet2 text-muted display-4 d-block mb-3"></i>
                    <h5 class="fw-bold text-dark">Belum ada transaksi pembayaran yang cocok</h5>
                    <p class="text-muted small mb-3">Klik tombol di bawah untuk mencatat transaksi pembayaran baru.</p>
                    <a href="<?= ($BASE) ?>/pembayaran/tambah" class="btn btn-sm btn-primary">
                        <i class="bi bi-cash-stack me-1"></i> Catat Pembayaran
                    </a>
                </div>
            
        <?php endif; ?>
    </div>
</div>
