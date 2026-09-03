<div class="container-fluid py-3">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 pb-2 border-bottom">
        <h4 class="fw-bold text-dark m-0 font-display">Permintaan Masuk &amp; Disposisi</h4>

        <?php if ($is_superadmin): ?>
            <div class="d-inline-flex align-items-center p-1 rounded-pill border shadow-2xs" style="background-color: #f1f5f9;">
                <a href="<?= ($BASE) ?>/disposisi-masuk" class="text-decoration-none px-3 py-1 rounded-pill small fw-semibold transition-all <?= (empty($filter_divisi) ? 'bg-white text-dark shadow-xs' : 'text-secondary') ?>">
                    <i class="bi bi-grid-fill me-1 opacity-50"></i> Semua
                </a>
                <a href="<?= ($BASE) ?>/disposisi-masuk?divisi=selulosa" class="text-decoration-none px-3 py-1 rounded-pill small fw-semibold transition-all <?= ($filter_divisi == 'selulosa' ? 'bg-white text-primary shadow-xs' : 'text-secondary') ?>">
                    <i class="bi bi-file-earmark-medical me-1"></i> Selulosa
                </a>
                <a href="<?= ($BASE) ?>/disposisi-masuk?divisi=lingkungan" class="text-decoration-none px-3 py-1 rounded-pill small fw-semibold transition-all <?= ($filter_divisi == 'lingkungan' ? 'bg-white text-success shadow-xs' : 'text-secondary') ?>">
                    <i class="bi bi-water me-1"></i> Lingkungan
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ======================================================== -->
    <!-- SEKSI 1: PERMINTAAN BARU (PERLU KAJI ULANG & TUNJUK PIC) -->
    <!-- ======================================================== -->
    <div class="card border rounded-3 mb-4 bg-white shadow-xs">
        <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="fw-bold text-dark m-0">1. Permintaan Masuk Baru</h6>
            <span class="badge <?= (count($perlu_kaji_ulang) > 0 ? 'bg-warning text-dark' : 'bg-light text-secondary border') ?> px-2.5 py-1 fw-semibold">
                <?= (count($perlu_kaji_ulang)) ?> Permintaan
            </span>
        </div>

        <div class="card-body p-3 p-md-4 bg-light-subtle">
            <?php if (empty($perlu_kaji_ulang)): ?>
                <div class="text-center py-4 bg-white rounded-3 border text-secondary small">
                    <i class="bi bi-check2-circle text-success fs-3 d-block mb-1"></i>
                    Tidak ada permintaan baru yang menunggu kaji ulang.
                </div>
            <?php endif; ?>

            <?php if (!empty($perlu_kaji_ulang)): ?>
                <div class="custom-scroll-container" style="max-height: 580px; overflow-y: auto; padding-right: 4px;">
                    <div class="row g-3">
                        <?php foreach (($perlu_kaji_ulang?:[]) as $item): ?>
                            <div class="col-lg-6">
                                <div class="card h-100 border rounded-3 p-3 bg-white shadow-2xs d-flex flex-column justify-content-between">
                                    
                                    <div>
                                        <!-- Header: Tag Divisi, Nomor Order, Tanggal -->
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= ($item['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-success-subtle text-success border border-success-subtle') ?> text-uppercase fw-semibold px-2 py-1" style="font-size: 0.68rem;">
                                                    OPTI <?= ($item['jenis_layanan_opti'])."
" ?>
                                                </span>
                                                <span class="text-secondary font-monospace small ms-1" style="font-size: 0.78rem;">
                                                    #<?= ($item['nomor_order'])."
" ?>
                                                </span>
                                            </div>
                                            <span class="text-muted small" style="font-size: 0.75rem;">
                                                <?= (date('d M Y', strtotime($item['tanggal_masuk'])))."
" ?>
                                            </span>
                                        </div>

                                        <!-- Judul Kegiatan -->
                                        <h6 class="fw-bold text-dark mb-2.5 font-display" style="font-size: 0.95rem; line-height: 1.35;">
                                            <?= ($item['judul_kegiatan'])."
" ?>
                                        </h6>

                                        <!-- Data Informasi Multi-Baris Rapi -->
                                        <div class="small text-secondary mb-3" style="font-size: 0.82rem; line-height: 1.5;">
                                            <div class="d-flex mb-1">
                                                <span class="text-muted" style="width: 120px; flex-shrink: 0;">Nama Perusahaan</span>
                                                <span class="text-muted me-2">:</span>
                                                <strong class="text-dark"><?= ($item['nama_perusahaan']) ?> (<?= ($item['pt_cv']) ?>)</strong>
                                            </div>
                                            <div class="d-flex mb-1">
                                                <span class="text-muted" style="width: 120px; flex-shrink: 0;">PIC</span>
                                                <span class="text-muted me-2">:</span>
                                                <span class="text-dark"><?= ($item['pic']) ?></span>
                                            </div>
                                            <div class="d-flex mb-1">
                                                <span class="text-muted" style="width: 120px; flex-shrink: 0;">No. HP</span>
                                                <span class="text-muted me-2">:</span>
                                                <span class="text-dark"><?= ($item['telepon']) ?></span>
                                            </div>
                                            <div class="d-flex">
                                                <span class="text-muted" style="width: 120px; flex-shrink: 0;">Kebutuhan</span>
                                                <span class="text-muted me-2">:</span>
                                                <span class="text-dark"><?= ($item['deskripsi'] ?: ($item['penjelasan'] ?: 'Permohonan pelayanan jasa teknis.')) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <a href="<?= ($BASE) ?>/order/<?= ($item['id']) ?>" class="btn btn-light btn-sm text-secondary px-3 py-1 fw-medium">
                                            Detail
                                        </a>
                                        <a href="<?= ($BASE) ?>/order/<?= ($item['id']) ?>/tinjauan" class="btn btn-primary btn-sm px-3 py-1 fw-semibold">
                                            <i class="bi bi-clipboard-check me-1"></i> Kaji Ulang &amp; Tunjuk PIC
                                        </a>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- SEKSI 2: PROPOSAL MENUNGGU APPROVAL KETUA TIM            -->
    <!-- ======================================================== -->
    <div class="card border rounded-3 mb-4 bg-white shadow-xs">
        <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="fw-bold text-dark m-0">2. Proposal Menunggu Approval</h6>
            <span class="badge <?= (count($perlu_approval) > 0 ? 'bg-primary text-white' : 'bg-light text-secondary border') ?> px-2.5 py-1 fw-semibold">
                <?= (count($perlu_approval)) ?> Proposal
            </span>
        </div>

        <div class="card-body p-3 p-md-4 bg-light-subtle">
            <?php if (empty($perlu_approval)): ?>
                <div class="text-center py-4 bg-white rounded-3 border text-secondary small">
                    <i class="bi bi-inbox text-muted fs-3 opacity-50 d-block mb-1"></i>
                    Tidak ada proposal yang menunggu persetujuan.
                </div>
            <?php endif; ?>

            <?php if (!empty($perlu_approval)): ?>
                <div class="custom-scroll-container" style="max-height: 580px; overflow-y: auto; padding-right: 4px;">
                    <div class="row g-3">
                        <?php foreach (($perlu_approval?:[]) as $p): ?>
                            <div class="col-lg-6">
                                <div class="card h-100 border rounded-3 p-3 bg-white shadow-2xs d-flex flex-column justify-content-between">
                                    
                                    <div>
                                        <!-- Header -->
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= ($p['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-success-subtle text-success border border-success-subtle') ?> text-uppercase fw-semibold px-2 py-1" style="font-size: 0.68rem;">
                                                    OPTI <?= ($p['jenis_layanan_opti'])."
" ?>
                                                </span>
                                                <span class="text-secondary font-monospace small ms-1" style="font-size: 0.78rem;">
                                                    #<?= ($p['nomor_order'])."
" ?>
                                                </span>
                                            </div>
                                            <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size: 0.7rem;">
                                                Menunggu Approval
                                            </span>
                                        </div>

                                        <!-- Judul Kegiatan -->
                                        <h6 class="fw-bold text-dark mb-2.5 font-display" style="font-size: 0.95rem; line-height: 1.35;">
                                            <?= ($p['judul_kegiatan'])."
" ?>
                                        </h6>

                                        <!-- Data Informasi Multi-Baris Rapi -->
                                        <div class="small text-secondary mb-3" style="font-size: 0.82rem; line-height: 1.5;">
                                            <div class="d-flex mb-1">
                                                <span class="text-muted" style="width: 120px; flex-shrink: 0;">Nama Perusahaan</span>
                                                <span class="text-muted me-2">:</span>
                                                <strong class="text-dark"><?= ($p['nama_perusahaan']) ?> (<?= ($p['pt_cv']) ?>)</strong>
                                            </div>
                                            <div class="d-flex mb-1">
                                                <span class="text-muted" style="width: 120px; flex-shrink: 0;">PIC Proposal</span>
                                                <span class="text-muted me-2">:</span>
                                                <span class="text-dark"><?= ($p['pic_nama'] ?: '-') ?></span>
                                            </div>
                                            <div class="d-flex">
                                                <span class="text-muted" style="width: 120px; flex-shrink: 0;">Estimasi Biaya</span>
                                                <span class="text-muted me-2">:</span>
                                                <strong class="text-primary font-monospace">Rp <?= (number_format($p['estimasi_biaya'] ?: ($p['estimasi_total_biaya'] ?: 0), 0, ',', '.')) ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <?php if ($p['file_proposal']): ?>
                                            <a href="<?= ($BASE) ?>/<?= ($p['file_proposal']) ?>" target="_blank" class="btn btn-outline-danger btn-sm py-1 px-2.5 fw-medium" style="font-size: 0.78rem;">
                                                <i class="bi bi-file-earmark-pdf me-1"></i> File
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$p['file_proposal']): ?>
                                            <span class="text-muted small" style="font-size: 0.75rem;">Tanpa Berkas</span>
                                        <?php endif; ?>

                                        <a href="<?= ($BASE) ?>/order/<?= ($p['id']) ?>" class="btn btn-success btn-sm px-3 py-1 fw-semibold">
                                            <i class="bi bi-shield-check me-1"></i> Review &amp; Approval
                                        </a>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- SEKSI 3: DAFTAR PERMOHONAN SEDANG BERJALAN              -->
    <!-- ======================================================== -->
    <div class="card border rounded-3 bg-white shadow-xs overflow-hidden">
        <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="fw-bold text-dark m-0">3. Permohonan Sedang Berjalan</h6>
            <a href="<?= ($BASE) ?>/order" class="btn btn-light btn-sm text-secondary px-3 py-1 fw-medium border">
                Semua Order <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 ps-md-4" style="width: 140px;">No. Order</th>
                        <th>Pelanggan</th>
                        <th>Kegiatan</th>
                        <th>PIC Proposal</th>
                        <th>Status</th>
                        <th class="text-end pe-3 pe-md-4" style="width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sedang_berjalan)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Belum ada order aktif yang sedang berjalan.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach (($sedang_berjalan?:[]) as $row): ?>
                        <tr>
                            <td class="ps-3 ps-md-4">
                                <span class="badge bg-light text-dark border font-monospace"><?= ($row['nomor_order']) ?></span>
                                <span class="badge <?= ($row['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success') ?> text-uppercase d-block mt-1" style="font-size: 0.65rem;">
                                    <?= ($row['jenis_layanan_opti'])."
" ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-dark"><?= ($row['nama_perusahaan']) ?></strong>
                                <small class="text-muted d-block">(<?= ($row['pt_cv']) ?>)</small>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold text-truncate" style="max-width: 280px;" title="<?= ($row['judul_kegiatan']) ?>">
                                    <?= ($row['judul_kegiatan'])."
" ?>
                                </div>
                                <small class="text-muted">Masuk: <?= (date('d M Y', strtotime($row['tanggal_masuk']))) ?></small>
                            </td>
                            <td>
                                <span class="text-dark"><?= ($row['pic_nama'] ?: '-') ?></span>
                            </td>
                            <td>
                                <?php if ($row['status_proposal_biaya'] == 'siap_penawaran'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-medium">
                                        <i class="bi bi-check-circle-fill me-1"></i> Proposal Disetujui
                                    </span>
                                <?php endif; ?>
                                <?php if ($row['status_proposal_biaya'] == 'draft' || !$row['status_proposal_biaya']): ?>
                                    <span class="badge bg-light text-secondary border fw-medium">
                                        <i class="bi bi-pencil-square me-1"></i> PIC Menyusun Proposal
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3 pe-md-4">
                                <a href="<?= ($BASE) ?>/order/<?= ($row['id']) ?>" class="btn btn-light btn-sm text-secondary px-2.5 py-1 border">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
/* Custom slim scrollbar */
.custom-scroll-container::-webkit-scrollbar {
    width: 5px;
}
.custom-scroll-container::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scroll-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-scroll-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
