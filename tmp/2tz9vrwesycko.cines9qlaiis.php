<style>
    /* ======================================================== */
    /* CUSTOM THEME STYLES - BBSPJIS MAROON & PASTEL ACCENTS */
    /* ======================================================== */
    .po-stepper-container {
        position: relative;
        padding: 0.5rem 0;
    }
    .po-stepper {
        display: flex;
        justify-content: space-between;
        position: relative;
    }
    .po-stepper::before {
        content: '';
        position: absolute;
        top: 19px;
        left: 5%;
        right: 5%;
        height: 2px;
        background: #e2e8f0;
        z-index: 1;
    }
    .po-step-item {
        position: relative;
        z-index: 2;
        text-align: center;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .po-step-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        background: #ffffff;
        border: 2px solid #cbd5e1;
        color: #64748b;
        transition: all 0.2s ease;
        margin-bottom: 0.45rem;
    }
    .po-step-item.completed .po-step-circle {
        background: var(--color-primary);
        border-color: var(--color-primary);
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(136, 19, 55, 0.2);
    }
    .po-step-item.active .po-step-circle {
        background: #ffffff;
        border-color: var(--color-primary);
        color: var(--color-primary);
        box-shadow: 0 0 0 4px rgba(136, 19, 55, 0.15);
        font-weight: 800;
    }
    .po-step-item.pending .po-step-circle {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #94a3b8;
    }
    .po-step-label {
        font-size: 0.775rem;
        font-weight: 600;
        color: #475569;
        line-height: 1.2;
    }
    .po-step-item.active .po-step-label {
        color: var(--color-primary);
        font-weight: 700;
    }
    .po-step-item.completed .po-step-label {
        color: #0f172a;
    }
    
    /* Nav Pills Tab SOP Maroon Theme */
    .sop-nav-pills .nav-link {
        color: #475569;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.15s ease;
        padding: 6px 12px;
    }
    .sop-nav-pills .nav-link:hover {
        color: var(--color-primary);
        border-color: rgba(136, 19, 55, 0.3);
        background: #fff1f2;
    }
    .sop-nav-pills .nav-link.active {
        background-color: var(--color-primary) !important;
        color: #ffffff !important;
        border-color: var(--color-primary) !important;
        box-shadow: 0 2px 8px rgba(136, 19, 55, 0.25);
    }
    .sop-nav-pills .nav-link.active .badge {
        background-color: rgba(255, 255, 255, 0.25) !important;
        color: #ffffff !important;
    }
</style>

<!-- ======================================================== -->
<!-- HEADER HALAMAN & QUICK ACTION BUTTONS -->
<!-- ======================================================== -->
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= ($BASE) ?>/po" class="btn-back">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h3 class="h4 fw-bold mb-0 text-dark"><?= ($po['nomor_po']) ?></h3>
                <?php if ($po['jenis_layanan_opti'] == 'selulosa'): ?>
                    <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.75rem; background-color: #ffe4e6; color: #be123c; border: 1px solid #fecdd3;">
                        <i class="bi bi-tree me-1"></i>OPTI Selulosa
                    </span>
                <?php endif; ?>
                <?php if ($po['jenis_layanan_opti'] == 'lingkungan'): ?>
                    <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.75rem; background-color: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4;">
                        <i class="bi bi-water me-1"></i>OPTI Lingkungan
                    </span>
                <?php endif; ?>
                <span class="badge px-2 py-1 fw-medium" style="font-size: 0.75rem; background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                    <i class="bi bi-clock me-1"></i><?= ($overdue_info['label'])."
" ?>
                </span>
            </div>
            <p class="text-muted small mb-0"><?= ($po['judul_kegiatan']) ?></p>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="#progres-section" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-diagram-3-fill me-1"></i> Progres Teknis
        </a>
        <a href="#rab-section" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-calculator me-1"></i> RAB (Rp <?= (number_format($total_rab ?: $po['biaya'], 0, ',', '.')) ?>)
        </a>
        <a href="#jadwal-section" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-calendar-range me-1"></i> Jadwal Tim
        </a>
        <a href="#pembayaran-section" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-credit-card me-1"></i> Pembayaran
        </a>
    </div>
</div>

<!-- ======================================================== -->
<!-- ALERT KETERLAMBATAN WAKTU JIKA OVERDUE -->
<!-- ======================================================== -->
<?php if ($overdue_info['is_overdue']): ?>
    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-3 mb-4 rounded-3 p-3">
        <i class="bi bi-exclamation-triangle-fill fs-3 text-danger flex-shrink-0"></i>
        <div class="small">
            <strong>Peringatan Keterlambatan:</strong> Dokumen PO ini telah melewati batas waktu target selesai (<strong><?= (date('d/m/Y', strtotime($po['target_selesai']))) ?></strong>) sebanyak <strong><?= ($overdue_info['days']) ?> hari</strong>. Harap koordinasikan dengan tim analis untuk segera menyelesaikan tahapan uji atau menerbitkan laporan akhir.
        </div>
    </div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- WORKFLOW LIFECYCLE STEPPER (TEMA RESMI BBSPJIS) -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <span class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                <i class="bi bi-diagram-2 text-primary me-1"></i> Alur Tahapan Proyek Jasa OPTI (SOP BBSPJIS)
            </span>
            <span class="badge px-3 py-2 fw-semibold" style="font-size: 0.78rem; background-color: rgba(136, 19, 55, 0.08); color: #881337; border: 1px solid rgba(136, 19, 55, 0.2);">
                Status PO: <?= ($urutan_status[$po['status']] ?? $po['status'])."
" ?>
            </span>
        </div>

        <div class="po-stepper-container">
            <div class="po-stepper">
                <!-- 1. Order Masuk -->
                <div class="po-step-item completed">
                    <div class="po-step-circle">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <span class="po-step-label">1. Order Masuk</span>
                </div>

                <!-- 2. Proposal -->
                <div class="po-step-item <?= ($po['app_proposal'] || $po['status'] == 'on_proses' || $po['status'] == 'kembali_selesai' ? 'completed' : ($po['status'] == 'sudah_upload' ? 'active' : 'pending')) ?>">
                    <div class="po-step-circle">
                        <?php if ($po['app_proposal'] || $po['status'] == 'on_proses' || $po['status'] == 'kembali_selesai'): ?>
                            <i class="bi bi-check-lg"></i>
                            <?php else: ?>2
                        <?php endif; ?>
                    </div>
                    <span class="po-step-label">2. Proposal</span>
                </div>

                <!-- 3. PO & Kendali -->
                <div class="po-step-item <?= ($po['app_po_adm'] || $po['status'] == 'on_proses' || $po['status'] == 'kembali_selesai' ? 'completed' : ($po['status'] == 'sudah_upload' ? 'active' : 'pending')) ?>">
                    <div class="po-step-circle">
                        <?php if ($po['app_po_adm'] || $po['status'] == 'on_proses' || $po['status'] == 'kembali_selesai'): ?>
                            <i class="bi bi-check-lg"></i>
                            <?php else: ?>3
                        <?php endif; ?>
                    </div>
                    <span class="po-step-label">3. PO & Kendali</span>
                </div>

                <!-- 4. Pelaksanaan -->
                <div class="po-step-item <?= ($po['status'] == 'kembali_selesai' ? 'completed' : ($po['status'] == 'on_proses' ? 'active' : 'pending')) ?>">
                    <div class="po-step-circle">
                        <?php if ($po['status'] == 'kembali_selesai'): ?>
                            <i class="bi bi-check-lg"></i>
                            <?php else: ?><i class="bi bi-gear-fill" style="font-size: 0.8rem;"></i>
                        <?php endif; ?>
                    </div>
                    <span class="po-step-label">4. Pelaksanaan</span>
                </div>

                <!-- 5. Evaluasi Klien -->
                <div class="po-step-item <?= ($po['evaluasi_status'] == 'disetujui' || $po['status'] == 'kembali_selesai' ? 'completed' : ($po['evaluasi_status'] == 'perlu_revisi' ? 'active' : 'pending')) ?>">
                    <div class="po-step-circle">
                        <?php if ($po['evaluasi_status'] == 'disetujui' || $po['status'] == 'kembali_selesai'): ?>
                            <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <?php if ($po['evaluasi_status'] == 'perlu_revisi'): ?>
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                    <?php else: ?>5
                                <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                    <span class="po-step-label">5. Evaluasi Klien</span>
                </div>

                <!-- 6. Selesai / BAST -->
                <div class="po-step-item <?= ($po['status'] == 'kembali_selesai' ? 'completed' : 'pending') ?>">
                    <div class="po-step-circle">
                        <?php if ($po['status'] == 'kembali_selesai'): ?>
                            <i class="bi bi-award-fill"></i>
                            <?php else: ?>6
                        <?php endif; ?>
                    </div>
                    <span class="po-step-label">6. Selesai / BAST</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ======================================================== -->
    <!-- KOLOM KIRI: INFO ORDER, SPESIFIKASI SAMPEL, EVALUASI, LOG -->
    <!-- ======================================================== -->
    <div class="col-lg-6">
        
        <!-- KARTU 1: INFORMASI PERMOHONAN & CUSTOMER -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Informasi Permohonan & Mitra</h6>
                <a href="<?= ($BASE) ?>/order/<?= ($po['order_id']) ?>/edit" class="btn btn-sm btn-outline-secondary py-1 px-2">
                    <i class="bi bi-pencil"></i> Edit Order
                </a>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 small">
                    <div class="col-sm-4 text-muted">Mitra / Customer:</div>
                    <div class="col-sm-8 fw-bold text-dark">
                        <?php if ($mask_client_name): ?>
                            
                                <?php $words = explode(' ', $po['nama_perusahaan']);
                                    $masked = array_map(function($w) {
                                        return mb_strlen($w) > 1 ? mb_substr($w, 0, 1) . '***' : $w;
                                    }, $words);
                                    $namaTampil = implode(' ', $masked); ?>
                                <span title="Nama disamarkan untuk privasi"><?= ($namaTampil) ?></span>
                            
                            <?php else: ?>
                                <?= ($po['nama_perusahaan']) ?> (<?= ($po['pt_cv']) ?>)
                            
                        <?php endif; ?>
                    </div>

                    <div class="col-sm-4 text-muted">Kontak PIC:</div>
                    <div class="col-sm-8 text-dark"><?= ($po['pic'] ?: '-') ?> &bull; <?= ($po['telepon'] ?: '-') ?></div>

                    <div class="col-sm-4 text-muted">Nomor Order:</div>
                    <div class="col-sm-8"><code class="text-primary fw-bold"><?= ($po['nomor_order']) ?></code></div>

                    <div class="col-sm-4 text-muted">Tanggal Masuk:</div>
                    <div class="col-sm-8 text-dark"><?= ($po['tanggal_masuk'] ? date('d F Y', strtotime($po['tanggal_masuk'])) : '-') ?></div>

                    <div class="col-sm-4 text-muted">Standar SPM:</div>
                    <div class="col-sm-8"><span class="badge bg-light text-secondary border px-2 py-1"><?= ($po['spm_layanan']) ?></span></div>

                    <div class="col-sm-4 text-muted">Lokasi Pelaksanaan:</div>
                    <div class="col-sm-8">
                        <?php if ($po['lokasi_pelaksanaan'] == 'internal'): ?>
                            <span class="badge px-2 py-1 fw-semibold" style="background-color: rgba(136, 19, 55, 0.08); color: #881337; border: 1px solid rgba(136, 19, 55, 0.2);">
                                <i class="bi bi-building me-1"></i><?= ($po['lab_internal'] ?: 'Laboratorium BBSPJIS')."
" ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($po['lokasi_pelaksanaan'] == 'lapangan'): ?>
                            <span class="badge px-2 py-1 fw-semibold" style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                                <i class="bi bi-geo-alt me-1"></i>Lapangan: <?= ($po['lokasi_lapangan'])."
" ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="col-sm-4 text-muted">Tim Kerja:</div>
                    <div class="col-sm-8 fw-semibold text-dark"><?= ($po['tim_kerja'] ?: 'Belum Ditetapkan') ?></div>
                </div>
            </div>
        </div>

        <!-- KARTU 2: SPESIFIKASI TEKNIS SAMPEL -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-eyedropper text-primary me-2"></i>Spesifikasi Teknis Sampel Uji</h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 small">
                    <div class="col-sm-4 text-muted">Jenis Bahan:</div>
                    <div class="col-sm-8 fw-bold text-dark"><?= ($po['jenis_sampel'] ?: '-') ?></div>

                    <div class="col-sm-4 text-muted">Volume / Berat:</div>
                    <div class="col-sm-8 text-dark"><?= ($po['volume_berat'] ?: '-') ?></div>

                    <div class="col-sm-4 text-muted">Standar Uji:</div>
                    <div class="col-sm-8 text-dark"><?= ($po['tipe_data_sampel'] ?: '-') ?></div>

                    <?php if ($po['karakteristik_serat']): ?>
                        <div class="col-sm-4 text-muted">Morfologi Serat:</div>
                        <div class="col-sm-8 text-secondary"><?= ($po['karakteristik_serat']) ?></div>
                    <?php endif; ?>

                    <?php if ($po['karakteristik_kimia']): ?>
                        <div class="col-sm-4 text-muted">Karakteristik Kimia:</div>
                        <div class="col-sm-8 text-secondary"><?= ($po['karakteristik_kimia']) ?></div>
                    <?php endif; ?>

                    <div class="col-sm-4 text-muted">Jumlah Pekerjaan:</div>
                    <div class="col-sm-8 fw-semibold text-dark"><?= ($po['jumlah_pekerjaan']) ?></div>
                </div>
            </div>
        </div>

        <!-- KARTU 3: LAPORAN HASIL & EVALUASI FEEDBACK LOOP -->
        <div class="card border-0 shadow-sm mb-4" id="evaluasi-section">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-file-earmark-text text-primary me-2"></i>Laporan Hasil & Evaluasi Mitra</h6>
                <?php if ($po['evaluasi_status'] == 'disetujui'): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Disetujui (Selesai)</span>
                <?php endif; ?>
                <?php if ($po['evaluasi_status'] == 'perlu_revisi'): ?>
                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Perlu Revisi</span>
                <?php endif; ?>
                <?php if ($po['evaluasi_status'] == 'pending' || empty($po['evaluasi_status'])): ?>
                    <span class="badge bg-secondary">Menunggu Evaluasi</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-3 p-md-4">
                <!-- Sub-Form A: Unggah Berkas Draf & Final Laporan -->
                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/laporan/upload" method="POST" enctype="multipart/form-data" class="mb-4 pb-3 border-bottom">
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1">Nomor Sertifikat / Laporan Resmi Balai:</label>
                        <input type="text" name="nomor_laporan_hasil" class="form-control form-control-sm font-monospace" placeholder="Contoh: 088/LHP/BBSPJIS/VIII/2026" value="<?= ($po['nomor_laporan_hasil']) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Ringkasan / Kesimpulan Hasil:</label>
                        <textarea name="laporan_akhir" class="form-control form-control-sm" rows="2" placeholder="Uraian ringkas parameter hasil pengujian atau riset..."><?= ($po['laporan_akhir']) ?></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">File Draf Laporan:</label>
                            <input type="file" name="file_draf" class="form-control form-control-sm">
                            <?php if ($po['file_draf_laporan']): ?>
                                <div class="mt-1 small">
                                    <a href="<?= ($BASE) ?>/<?= ($po['file_draf_laporan']) ?>" target="_blank" class="text-primary text-decoration-none">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Lihat Draf
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">File Laporan Final (Bertandatangan):</label>
                            <input type="file" name="file_final" class="form-control form-control-sm">
                            <?php if ($po['file_laporan_final']): ?>
                                <div class="mt-1 small">
                                    <a href="<?= ($BASE) ?>/<?= ($po['file_laporan_final']) ?>" target="_blank" class="text-success fw-bold text-decoration-none">
                                        <i class="bi bi-file-earmark-check me-1"></i>Lihat Final
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Simpan Dokumen Laporan
                        </button>
                    </div>
                </form>

                <!-- Sub-Form B: Feedback Loop Evaluasi -->
                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/evaluasi" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-2">Evaluasi Hasil Kerja Bersama Mitra (Feedback Loop)</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="evaluasi_status" id="evaluasi_setuju" value="disetujui" autocomplete="off" <?= ($po['evaluasi_status'] == 'disetujui' || empty($po['evaluasi_status']) || $po['evaluasi_status'] == 'pending' ? 'checked' : '') ?>>
                                <label class="btn btn-outline-success w-100 py-2 d-flex flex-column align-items-center justify-content-center gap-1 shadow-none" for="evaluasi_setuju" style="cursor: pointer;">
                                    <span class="fw-bold fs-6"><i class="bi bi-check-circle me-1"></i>Disetujui</span>
                                    <span style="font-size: 0.7rem; opacity: 0.85;">Pekerjaan Tuntas & Siap BAST</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="evaluasi_status" id="evaluasi_revisi" value="perlu_revisi" autocomplete="off" <?= ($po['evaluasi_status'] == 'perlu_revisi' ? 'checked' : '') ?>>
                                <label class="btn btn-outline-danger w-100 py-2 d-flex flex-column align-items-center justify-content-center gap-1 shadow-none" for="evaluasi_revisi" style="cursor: pointer;">
                                    <span class="fw-bold fs-6"><i class="bi bi-arrow-counterclockwise me-1"></i>Perlu Revisi</span>
                                    <span style="font-size: 0.7rem; opacity: 0.85;">Kembali ke On Proses</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Notulen / Catatan Masukan Customer:</label>
                        <textarea name="notulen_evaluasi" class="form-control form-control-sm" rows="2" placeholder="Catatan hasil diskusi teknis, revisi parameter, atau konfirmasi kepuasan..."><?= ($po['notulen_evaluasi']) ?></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <input type="date" name="tgl_evaluasi" class="form-control form-control-sm w-50" value="<?= ($po['tgl_evaluasi'] ?: date('Y-m-d')) ?>">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-save me-1"></i> Simpan Evaluasi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- KARTU 4: AUDIT TRAIL LOG STATUS -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Histori Perubahan Dokumen</h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <?php if (count($daftar_log) > 0): ?>
                    <div class="small">
                        <?php foreach (($daftar_log?:[]) as $l): ?>
                            <div class="mb-2 pb-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-primary"><?= ($l['status_baru']) ?></span>
                                    <span class="text-muted" style="font-size: 0.725rem;"><?= (date('d/m/Y H:i', strtotime($l['tanggal']))) ?></span>
                                </div>
                                <div class="text-secondary" style="font-size: 0.8rem;"><?= ($l['catatan']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ======================================================== -->
    <!-- KOLOM KANAN: ALUR SOP 19 TAHAP, RAB, JADWAL TIM, PEMBAYARAN -->
    <!-- ======================================================== -->
    <div class="col-lg-6">
        
        <!-- KARTU 5: PELAKSANAAN TEKNIS & MILESTONE PROGRES KEGIATAN -->
        <div class="card border-0 shadow-sm mb-4" id="progres-section">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div>
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-diagram-3-fill text-primary"></i> Progres Pelaksanaan Teknis Kegiatan (PO)
                        </h6>
                        <span class="text-muted" style="font-size: 0.75rem;">Tahapan operasional pengujian/riset, validasi teknis, dan pemenuhan target SPM</span>
                    </div>
                    <div class="text-end">
                        <span class="badge px-3 py-2 fw-bold" style="font-size: 0.75rem; background-color: rgba(136, 19, 55, 0.08); color: #881337; border: 1px solid rgba(136, 19, 55, 0.2);">
                            <?= ($sop_statistik['persen']) ?>% Selesai
                        </span>
                    </div>
                </div>

                <!-- Visual Progress Bar -->
                <div class="progress" style="height: 6px; background-color: #f1f5f9; border-radius: 10px;">
                    <div class="progress-bar" role="progressbar" style="width: <?= ($sop_statistik['persen']) ?>%; background-color: var(--color-primary); border-radius: 10px;" aria-valuenow="<?= ($sop_statistik['persen']) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>

            <div class="card-body p-3 p-md-4">
                
                <!-- 4 FASE UTAMA PELAKSANAAN TEKNIS -->
                <div class="d-flex flex-column gap-3">
                    
                    <!-- FASE 1: Persiapan & Penugasan Tim -->
                    <?php $fase1_done = ($sop_statistik['tahap_terverifikasi_max'] >= 5); ?>
                    <div class="p-3 rounded-3 border <?= ($fase1_done ? 'bg-success-subtle border-success-subtle' : 'bg-white') ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= ($fase1_done ? 'bg-success text-white' : 'bg-primary text-white') ?> px-2 py-1" style="font-size: 0.725rem;">
                                    Fase 1
                                </span>
                                <strong class="text-dark small">Persiapan, Penugasan Tim &amp; Rencana Kerja</strong>
                            </div>
                            <span class="badge <?= ($fase1_done ? 'bg-success text-white' : 'bg-secondary') ?>" style="font-size: 0.7rem;">
                                <?= ($fase1_done ? 'Selesai' : 'Sedang Berjalan')."
" ?>
                            </span>
                        </div>
                        <div class="small text-muted mb-2">
                            Penetapan personil analis/peneliti, koordinasi ruang lingkup pengujian/riset, dan kesiapan administrasi.
                        </div>
                        <?php if (!$fase1_done): ?>
                            <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/5/verifikasi" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                <button type="submit" class="btn btn-sm btn-primary py-1 px-3 fw-semibold" style="font-size: 0.75rem;">
                                    <i class="bi bi-check2-circle me-1"></i> Tandai Fase Persiapan Selesai
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- FASE 2: Pelaksanaan Pengujian Lab / Riset Lapangan -->
                    <?php $fase2_done = ($sop_statistik['tahap_terverifikasi_max'] >= 12); ?>
                    <div class="p-3 rounded-3 border <?= ($fase2_done ? 'bg-success-subtle border-success-subtle' : ($fase1_done ? 'bg-white' : 'bg-light text-muted')) ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= ($fase2_done ? 'bg-success text-white' : ($fase1_done ? 'bg-primary text-white' : 'bg-secondary')) ?> px-2 py-1" style="font-size: 0.725rem;">
                                    Fase 2
                                </span>
                                <strong class="text-dark small">Pelaksanaan Pengujian Laboratorium / Riset Lapangan</strong>
                            </div>
                            <span class="badge <?= ($fase2_done ? 'bg-success text-white' : ($fase1_done ? 'bg-primary text-white' : 'bg-secondary')) ?>" style="font-size: 0.7rem;">
                                <?= ($fase2_done ? 'Selesai' : ($fase1_done ? 'Sedang Berjalan' : 'Menunggu'))."
" ?>
                            </span>
                        </div>
                        <div class="small text-muted mb-2">
                            Eksekusi pengujian parameter standar (SNI/ASTM) atau tahapan eksperimen formulasi riset selulosa di lab/pilot.
                        </div>
                        <?php if ($fase1_done && !$fase2_done): ?>
                            <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/12/verifikasi" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                <button type="submit" class="btn btn-sm btn-primary py-1 px-3 fw-semibold" style="font-size: 0.75rem;">
                                    <i class="bi bi-check2-circle me-1"></i> Tandai Pengujian Lab / Riset Selesai
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- FASE 3: Pengolahan Data & Draf Laporan (LHP / Laporan Riset) -->
                    <?php $fase3_done = ($sop_statistik['tahap_terverifikasi_max'] >= 14); ?>
                    <div class="p-3 rounded-3 border <?= ($fase3_done ? 'bg-success-subtle border-success-subtle' : ($fase2_done ? 'bg-white' : 'bg-light text-muted')) ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= ($fase3_done ? 'bg-success text-white' : ($fase2_done ? 'bg-primary text-white' : 'bg-secondary')) ?> px-2 py-1" style="font-size: 0.725rem;">
                                    Fase 3
                                </span>
                                <strong class="text-dark small">Pengolahan Data &amp; Penyusunan Draf LHP / Laporan Riset</strong>
                            </div>
                            <span class="badge <?= ($fase3_done ? 'bg-success text-white' : ($fase2_done ? 'bg-primary text-white' : 'bg-secondary')) ?>" style="font-size: 0.7rem;">
                                <?= ($fase3_done ? 'Selesai' : ($fase2_done ? 'Sedang Berjalan' : 'Menunggu'))."
" ?>
                            </span>
                        </div>
                        <div class="small text-muted mb-2">
                            Analisis data pengujian, komparasi baku mutu SPM, dan penyusunan draf Laporan Hasil Pengujian (LHP).
                        </div>
                        <?php if ($fase2_done && !$fase3_done): ?>
                            <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/14/verifikasi" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                <button type="submit" class="btn btn-sm btn-primary py-1 px-3 fw-semibold" style="font-size: 0.75rem;">
                                    <i class="bi bi-check2-circle me-1"></i> Tandai Draf Laporan Selesai Disusun
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- FASE 4: Validasi Manajer Teknis & Serah Terima Hasil (BAST) -->
                    <?php $fase4_done = ($sop_statistik['tahap_terverifikasi_max'] >= 19 || $po['status'] == 'kembali_selesai'); ?>
                    <div class="p-3 rounded-3 border <?= ($fase4_done ? 'bg-success-subtle border-success-subtle' : ($fase3_done ? 'bg-white' : 'bg-light text-muted')) ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= ($fase4_done ? 'bg-success text-white' : ($fase3_done ? 'bg-primary text-white' : 'bg-secondary')) ?> px-2 py-1" style="font-size: 0.725rem;">
                                    Fase 4
                                </span>
                                <strong class="text-dark small">Pengesahan Resmi &amp; Serah Terima Hasil (BAST)</strong>
                            </div>
                            <span class="badge <?= ($fase4_done ? 'bg-success text-white' : ($fase3_done ? 'bg-primary text-white' : 'bg-secondary')) ?>" style="font-size: 0.7rem;">
                                <?= ($fase4_done ? 'Selesai' : ($fase3_done ? 'Sedang Berjalan' : 'Menunggu'))."
" ?>
                            </span>
                        </div>
                        <div class="small text-muted mb-2">
                            Pengesahan sertifikat LHP bertandatangan digital dan penerbitan Berita Acara Serah Terima (BAST).
                        </div>
                        <?php if ($fase3_done && !$fase4_done): ?>
                            <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/19/verifikasi" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                <button type="submit" class="btn btn-sm btn-success py-1 px-3 fw-bold shadow-sm" style="font-size: 0.75rem;">
                                    <i class="bi bi-patch-check-fill me-1"></i> Sahkan &amp; Selesaikan Kegiatan PO
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </div>

        <!-- KARTU 6: RINCIAN ANGGARAN BIAYA (RAB BREAKDOWN) -->
        <div class="card border-0 shadow-sm mb-4" id="rab-section">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-calculator text-primary me-2"></i>Rincian Anggaran Biaya (RAB)</h6>
                    <span class="text-muted" style="font-size: 0.75rem;">Akumulasi otomatis ke Nilai PO</span>
                </div>
                <span class="badge px-3 py-2 fw-bold fs-6 text-nowrap" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                    Rp <?= (number_format($total_rab ?: $po['biaya'], 0, ',', '.'))."
" ?>
                </span>
            </div>
            <div class="card-body p-3 p-md-4">
                <?php if (count($daftar_rab) > 0): ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 135px; white-space: nowrap;">Kategori</th>
                                    <th>Uraian Kebutuhan</th>
                                    <th class="text-end text-nowrap" style="width: 140px; white-space: nowrap;">Nominal (Rp)</th>
                                    <th style="width: 30px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($daftar_rab?:[]) as $rab): ?>
                                    <tr>
                                        <td class="text-nowrap"><span class="badge bg-light text-secondary border" style="font-size: 0.7rem;"><?= ($rab['kategori']) ?></span></td>
                                        <td class="small text-dark"><?= ($rab['deskripsi']) ?></td>
                                        <td class="text-end fw-bold small text-nowrap" style="white-space: nowrap;">Rp <?= (number_format($rab['nominal'], 0, ',', '.')) ?></td>
                                        <td class="text-center">
                                            <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/rab/<?= ($rab['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus item anggaran ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Form Tambah Item RAB -->
                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/rab/tambah" method="POST" class="p-3 bg-light rounded-3">
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <select name="kategori" class="form-select form-select-sm" required>
                                <?php foreach (($kategori_rab_list?:[]) as $kKey=>$kLabel): ?>
                                    <option value="<?= ($kKey) ?>"><?= ($kKey) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" name="nominal" class="form-control form-control-sm" placeholder="Nominal (Rp)" required min="1">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="bi bi-plus-lg"></i> Tambah
                            </button>
                        </div>
                    </div>
                    <div>
                        <input type="text" name="deskripsi" class="form-control form-control-sm" placeholder="Uraian kebutuhan/material..." required>
                    </div>
                </form>
            </div>
        </div>

        <!-- KARTU 7: JADWAL KERJA TIM PELAKSANA (TIMELINE) -->
        <div class="card border-0 shadow-sm mb-4" id="jadwal-section">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-calendar3 text-primary me-2"></i>Jadwal Kerja Tim Pelaksana</h6>
                    <span class="text-muted" style="font-size: 0.75rem;">Timeline dan milestone kegiatan tim balai</span>
                </div>
                <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.75rem; background-color: rgba(136, 19, 55, 0.08); color: #881337; border: 1px solid rgba(136, 19, 55, 0.2);">
                    <?= (count($daftar_jadwal)) ?> Milestone
                </span>
            </div>
            <div class="card-body p-3 p-md-4">
                <?php if (count($daftar_jadwal) > 0): ?>
                    <div class="list-group list-group-flush mb-3">
                        <?php foreach (($daftar_jadwal?:[]) as $j): ?>
                            <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="fw-semibold text-dark small"><?= ($j['tahap_kegiatan']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-person me-1"></i><?= ($j['personil_anggota']) ?> &bull; 
                                        <i class="bi bi-clock me-1"></i><?= (date('d/m/y', strtotime($j['tanggal_mulai']))) ?> s/d <?= (date('d/m/y', strtotime($j['tanggal_selesai'])))."
" ?>
                                    </div>
                                    <?php if ($j['keterangan']): ?>
                                        <div class="text-secondary mt-1" style="font-size: 0.75rem;"><?= ($j['keterangan']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/jadwal/<?= ($j['id']) ?>/status" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                        <select name="status" class="form-select form-select-sm py-1 pe-4 text-dark fw-medium border shadow-none" style="font-size: 0.75rem; width: 110px; cursor: pointer;" onchange="this.form.submit()">
                                            <option value="rencana" <?= ($j['status_pekerjaan'] == 'rencana' ? 'selected' : '') ?>>Rencana</option>
                                            <option value="berjalan" <?= ($j['status_pekerjaan'] == 'berjalan' ? 'selected' : '') ?>>Berjalan</option>
                                            <option value="selesai" <?= ($j['status_pekerjaan'] == 'selesai' ? 'selected' : '') ?>>Selesai</option>
                                        </select>
                                    </form>
                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/jadwal/<?= ($j['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger p-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Hapus Jadwal">
                                            <i class="bi bi-trash" style="font-size: 0.85rem;"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Form Tambah Jadwal -->
                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/jadwal/tambah" method="POST" class="p-3 bg-light rounded-3">
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <input type="text" name="personil_anggota" class="form-control form-control-sm" placeholder="Nama personil / tim..." required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="tahap_kegiatan" class="form-control form-control-sm" placeholder="Uraian tahap kegiatan..." required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-0" style="font-size: 0.7rem;">Mulai:</label>
                            <input type="date" name="tanggal_mulai" class="form-control form-control-sm" value="<?= (date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted mb-0" style="font-size: 0.7rem;">Selesai:</label>
                            <input type="date" name="tanggal_selesai" class="form-control form-control-sm" value="<?= (date('Y-m-d', strtotime('+7 days'))) ?>" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <input type="text" name="keterangan" class="form-control form-control-sm" placeholder="Catatan opsional...">
                        <button type="submit" class="btn btn-sm btn-primary px-3 text-nowrap d-flex align-items-center gap-1">
                            <i class="bi bi-plus-lg"></i> <span>Tambah</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- KARTU 8: RIWAYAT PEMBAYARAN MULTI-TERMIN -->
        <div class="card border-0 shadow-sm mb-4" id="pembayaran-section">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-credit-card text-primary me-2"></i>Status Pembayaran Layanan</h6>
                    <span class="text-muted" style="font-size: 0.75rem;">Multi-termin pembayaran jasa</span>
                </div>
                <div>
                    <?php if ($total_terbayar >= $po['biaya'] && $po['biaya'] > 0): ?>
                        <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.72rem; background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">Lunas</span>
                        <?php else: ?><span class="badge px-2 py-1 fw-semibold" style="font-size: 0.72rem; background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;">Belum Lunas</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-2 mb-3 small">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded text-center">
                            <div class="text-muted" style="font-size: 0.7rem;">Total Terbayar</div>
                            <div class="fw-bold text-success" style="white-space: nowrap;">Rp <?= (number_format($total_terbayar, 0, ',', '.')) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-light rounded text-center">
                            <div class="text-muted" style="font-size: 0.7rem;">Sisa Tagihan</div>
                            <div class="fw-bold text-danger" style="white-space: nowrap;">Rp <?= (number_format($sisa_tagihan, 0, ',', '.')) ?></div>
                        </div>
                    </div>
                </div>

                <?php if (count($daftar_pembayaran) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead>
                                <tr>
                                    <th>Termin</th>
                                    <th>Tanggal</th>
                                    <th class="text-end text-nowrap" style="white-space: nowrap;">Nominal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($daftar_pembayaran?:[]) as $byr): ?>
                                    <tr>
                                        <td>Termin <?= ($byr['termin_ke']) ?></td>
                                        <td><?= (date('d/m/Y', strtotime($byr['tanggal_bayar']))) ?></td>
                                        <td class="text-end fw-bold text-nowrap" style="white-space: nowrap;">Rp <?= (number_format($byr['jumlah'], 0, ',', '.')) ?></td>
                                        <td>
                                            <?php if ($byr['status_verifikasi'] == 'terverifikasi'): ?>
                                                <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.68rem; background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">Terverifikasi</span>
                                            <?php endif; ?>
                                            <?php if ($byr['status_verifikasi'] == 'menunggu'): ?>
                                                <span class="badge px-2 py-1 fw-semibold" style="font-size: 0.68rem; background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;">Menunggu</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>