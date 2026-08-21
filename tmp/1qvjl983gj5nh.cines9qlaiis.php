<div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= ($BASE) ?>/po" class="btn-back">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h3 class="h4 fw-bold mb-0 text-dark"><?= ($po['nomor_po']) ?></h3>
                <?php if ($po['jenis_layanan_opti'] == 'selulosa'): ?>
                    <span class="badge badge-pill-danger">OPTI Selulosa</span>
                <?php endif; ?>
                <?php if ($po['jenis_layanan_opti'] == 'lingkungan'): ?>
                    <span class="badge badge-pill-success">OPTI Lingkungan</span>
                <?php endif; ?>
                <span class="badge <?= ($overdue_info['badge_class']) ?>"><?= ($overdue_info['label']) ?></span>
            </div>
            <p class="text-muted small mb-0"><?= ($po['judul_kegiatan']) ?></p>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="#map-kendali-section" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-check2-square"></i> Map Kendali
        </a>
        <a href="#rab-section" class="btn btn-outline-success btn-sm">
            <i class="bi bi-calculator"></i> RAB (Rp <?= (number_format($total_rab ?: $po['biaya'], 0, ',', '.')) ?>)
        </a>
        <a href="#jadwal-section" class="btn btn-outline-info btn-sm">
            <i class="bi bi-calendar-range"></i> Jadwal Tim
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
<!-- VISUAL WORKFLOW LIFECYCLE STEPPER (SOP BALAI) -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                <i class="bi bi-arrow-repeat me-1 text-primary"></i> Alur Tahapan Proyek Jasa OPTI (SOP BBSPJIS)
            </span>
            <span class="badge badge-pill-primary px-3 py-2 fw-semibold" style="font-size: 0.8rem;">Status PO: <?= ($urutan_status[$po['status']] ?? $po['status']) ?></span>
        </div>
        <div class="row g-2 text-center">
            <div class="col-6 col-md-2">
                <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success border border-success border-opacity-25 small fw-bold">
                    <i class="bi bi-check-circle-fill d-block fs-5 mb-1"></i>
                    1. Order Masuk
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded-3 <?= ($po['app_proposal'] ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-light text-muted border') ?> small fw-bold">
                    <i class="bi <?= ($po['app_proposal'] ? 'bi-check-circle-fill' : 'bi-circle') ?> d-block fs-5 mb-1"></i>
                    2. Proposal
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded-3 <?= ($po['app_po_adm'] ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-light text-muted border') ?> small fw-bold">
                    <i class="bi <?= ($po['app_po_adm'] ? 'bi-check-circle-fill' : 'bi-circle') ?> d-block fs-5 mb-1"></i>
                    3. PO & Kendali
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded-3 <?= ($po['status'] == 'on_proses' || $po['status'] == 'kembali_selesai' ? 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25' : 'bg-light text-muted border') ?> small fw-bold">
                    <i class="bi <?= ($po['status'] == 'on_proses' || $po['status'] == 'kembali_selesai' ? 'bi-gear-wide-connected' : 'bi-circle') ?> d-block fs-5 mb-1"></i>
                    4. Pelaksanaan
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded-3 <?= ($po['evaluasi_status'] == 'disetujui' ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : ($po['evaluasi_status'] == 'perlu_revisi' ? 'bg-warning bg-opacity-10 text-dark border border-warning' : 'bg-light text-muted border')) ?> small fw-bold">
                    <i class="bi <?= ($po['evaluasi_status'] == 'disetujui' ? 'bi-check-circle-fill' : ($po['evaluasi_status'] == 'perlu_revisi' ? 'bi-arrow-counterclockwise' : 'bi-circle')) ?> d-block fs-5 mb-1"></i>
                    5. Evaluasi Klien
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded-3 <?= ($po['status'] == 'kembali_selesai' ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-light text-muted border') ?> small fw-bold">
                    <i class="bi <?= ($po['status'] == 'kembali_selesai' ? 'bi-award-fill' : 'bi-circle') ?> d-block fs-5 mb-1"></i>
                    6. Selesai / BAST
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ======================================================== -->
    <!-- KOLOM KIRI: INFO ORDER, SPESIFIKASI SAMPEL, MAP KENDALI -->
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
                    <div class="col-sm-8"><span class="badge badge-pill-secondary"><?= ($po['spm_layanan']) ?></span></div>

                    <div class="col-sm-4 text-muted">Lokasi Pelaksanaan:</div>
                    <div class="col-sm-8">
                        <?php if ($po['lokasi_pelaksanaan'] == 'internal'): ?>
                            <span class="badge badge-pill-primary">
                                <i class="bi bi-building me-1"></i><?= ($po['lab_internal'] ?: 'Laboratorium BBSPJIS')."
" ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($po['lokasi_pelaksanaan'] == 'lapangan'): ?>
                            <span class="badge badge-pill-warning">
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

                <!-- KARTU 3: ALUR SOP JASA PELAYANAN OPTI LINGKUNGAN (19 TAHAPAN RESMI BBSPJIS) -->
        <div class="card border-0 shadow-sm mb-4" id="sop-section">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div>
                        <h6 class="m-0 fw-bold text-dark">
                            <i class="bi bi-diagram-3-fill text-primary me-2"></i>SOP Jasa Pelayanan OPTI Lingkungan
                        </h6>
                        <span class="text-muted" style="font-size: 0.75rem;">19 Aktivitas & Mutu Baku Alur Pelayanan Resmi BBSPJIS</span>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fw-bold" style="font-size: 0.75rem;">
                            <?= ($sop_statistik['persen']) ?>% Selesai (<?= ($sop_statistik['selesai']) ?>/<?= ($sop_statistik['total']) ?> Tahap)
                        </span>
                    </div>
                </div>

                <!-- Visual Progress Bar -->
                <div class="progress" style="height: 7px; background-color: #f1f5f9; border-radius: 10px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($sop_statistik['persen']) ?>%; border-radius: 10px;" aria-valuenow="<?= ($sop_statistik['persen']) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>

            <!-- Tab Navigasi 4 Fase SOP -->
            <div class="card-header bg-light p-2 border-bottom">
                <ul class="nav nav-pills nav-fill gap-1 small" id="sopFaseTab" role="tablist" style="font-size: 0.75rem;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-1 px-2 fw-semibold" id="fase-persiapan-tab" data-bs-toggle="pill" data-bs-target="#fase-persiapan" type="button" role="tab">
                            1. Persiapan <span class="badge bg-secondary bg-opacity-25 text-dark ms-1">1-5</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 px-2 fw-semibold" id="fase-pelaksanaan-tab" data-bs-toggle="pill" data-bs-target="#fase-pelaksanaan" type="button" role="tab">
                            2. Pelaksanaan <span class="badge bg-secondary bg-opacity-25 text-dark ms-1">6-12</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 px-2 fw-semibold" id="fase-pengesahan-tab" data-bs-toggle="pill" data-bs-target="#fase-pengesahan" type="button" role="tab">
                            3. Pengesahan <span class="badge bg-secondary bg-opacity-25 text-dark ms-1">13-14</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 px-2 fw-semibold" id="fase-bast-tab" data-bs-toggle="pill" data-bs-target="#fase-bast" type="button" role="tab">
                            4. BAST <span class="badge bg-secondary bg-opacity-25 text-dark ms-1">15-19</span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-3">
                <div class="tab-content" id="sopFaseTabContent">

                    <!-- ========================================== -->
                    <!-- FASE 1: PERSIAPAN & PERENCANAAN (1 - 5) -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade show active" id="fase-persiapan" role="tabpanel">
                        <div class="d-flex flex-column gap-3">
                            <?php foreach (($daftar_sop?:[]) as $step): ?>
                                <?php if ($step['fase'] == 'persiapan'): ?>
                                    <div class="p-3 rounded-3 border <?= ($step['status'] == 'selesai' ? 'bg-success bg-opacity-10 border-success-subtle' : ($step['status'] == 'berjalan' ? 'bg-primary bg-opacity-10 border-primary-subtle' : ($step['status'] == 'revisi' ? 'bg-danger bg-opacity-10 border-danger-subtle' : 'bg-light border-light-subtle'))) ?>">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= ($step['status'] == 'selesai' ? 'bg-success text-white' : ($step['status'] == 'berjalan' ? 'bg-primary text-white' : ($step['status'] == 'revisi' ? 'bg-danger text-white' : 'bg-secondary bg-opacity-25 text-secondary'))) ?> rounded-pill px-2 py-1" style="font-size: 0.725rem;">
                                                    #<?= ($step['tahap_no'])."
" ?>
                                                </span>
                                                <span class="fw-bold text-dark small"><?= ($step['nama_aktivitas']) ?></span>
                                            </div>
                                            <div>
                                                <?php if ($step['status'] == 'selesai'): ?>
                                                    <span class="badge bg-success text-white" style="font-size: 0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>Selesai</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'berjalan'): ?>
                                                    <span class="badge bg-primary text-white" style="font-size: 0.7rem;"><i class="bi bi-hourglass-split me-1"></i>Berjalan</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'revisi'): ?>
                                                    <span class="badge bg-danger text-white" style="font-size: 0.7rem;"><i class="bi bi-exclamation-circle-fill me-1"></i>Revisi</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'menunggu'): ?>
                                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Menunggu</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Detail Pelaksana & Mutu Baku -->
                                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2" style="font-size: 0.725rem;">
                                            <span class="badge bg-white text-secondary border px-2 py-1">
                                                <i class="bi bi-person-badge text-primary me-1"></i><?= ($step['pelaksana_label'])."
" ?>
                                            </span>
                                            <span class="text-muted"><i class="bi bi-clock me-1"></i>Waktu: <strong class="text-dark"><?= ($step['mutu_waktu']) ?></strong></span>
                                            <span class="text-muted">&bull;</span>
                                            <span class="text-muted"><i class="bi bi-file-earmark-check me-1"></i>Output: <span class="text-dark fw-medium"><?= ($step['mutu_output']) ?></span></span>
                                        </div>

                                        <!-- Log Verifikasi / Catatan -->
                                        <?php if ($step['verified_by']): ?>
                                            <div class="small text-muted mb-2 p-1 px-2 bg-white rounded border" style="font-size: 0.7rem;">
                                                <i class="bi bi-info-circle text-primary me-1"></i>
                                                Diverifikasi oleh <strong><?= ($step['verified_by']) ?></strong>
                                                <?php if ($step['verified_at']): ?>
                                                    &bull; <?= (date('d/m/Y H:i', strtotime($step['verified_at'])))."
" ?>
                                                <?php endif; ?>
                                                <?php if ($step['catatan']): ?>
                                                    <div class="text-secondary mt-1"><em>"<?= ($step['catatan']) ?>"</em></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Tombol Aksi Verifikasi & Revisi -->
                                        <div class="d-flex flex-wrap gap-2 align-items-center mt-2 pt-1 border-top border-secondary-subtle">
                                            <?php if ($step['status'] != 'selesai'): ?>
                                                <!-- Form Verifikasi -->
                                                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/<?= ($step['tahap_no']) ?>/verifikasi" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary py-1 px-2" style="font-size: 0.75rem;">
                                                        <i class="bi bi-check-lg me-1"></i> Verifikasi Selesai
                                                    </button>
                                                </form>

                                                <!-- Form Minta Revisi (Decision Step 5) -->
                                                <?php if ($step['is_decision'] == 1): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.75rem;" data-bs-toggle="collapse" data-bs-target="#revisi-form-<?= ($step['tahap_no']) ?>">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Perlu Revisi
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($step['status'] == 'selesai'): ?>
                                                <span class="text-success small fw-medium" style="font-size: 0.725rem;">
                                                    <i class="bi bi-check-all"></i> Tahapan tuntas & sah
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Collapse Form Catatan Revisi -->
                                        <?php if ($step['is_decision'] == 1): ?>
                                            <div class="collapse mt-2" id="revisi-form-<?= ($step['tahap_no']) ?>">
                                                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/<?= ($step['tahap_no']) ?>/revisi" method="POST" class="p-2 bg-white rounded border">
                                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                    <input type="hidden" name="target_tahap" value="3">
                                                    <label class="form-label small fw-semibold text-danger mb-1" style="font-size: 0.725rem;">Catatan Perbaikan (Alur Kembali ke Tahap #3):</label>
                                                    <div class="input-group input-group-sm mb-1">
                                                        <input type="text" name="catatan_revisi" class="form-control form-control-sm" placeholder="Tuliskan catatan perbaikan rencana kerja..." required>
                                                        <button type="submit" class="btn btn-danger btn-sm">Kirim Revisi</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- FASE 2: PELAKSANAAN & EVALUASI (6 - 12) -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="fase-pelaksanaan" role="tabpanel">
                        <!-- Fast-track Box: Lewati Laporan Perkembangan -->
                        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center p-2 mb-3 rounded-3" style="font-size: 0.75rem;">
                            <div>
                                <i class="bi bi-info-circle-fill text-info me-1"></i>
                                <strong>Ketentuan Kontrak:</strong> Apabila kontrak/SPK tidak mempersyaratkan Laporan Perkembangan, Tahap 7–12 dapat dilewati langsung ke Tahap 13.
                            </div>
                            <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/skip-perkembangan" method="POST" class="d-inline mt-1 mt-md-0" onsubmit="return confirm('Lewati tahap laporan perkembangan (7 s.d. 12) dan langsung ke Tahap 13?');">
                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-info py-0 px-2 fw-semibold" style="font-size: 0.725rem;">
                                    <i class="bi bi-fast-forward-fill me-1"></i> Lewati (Langsung ke #13)
                                </button>
                            </form>
                        </div>

                        <div class="d-flex flex-column gap-3">
                            <?php foreach (($daftar_sop?:[]) as $step): ?>
                                <?php if ($step['fase'] == 'pelaksanaan'): ?>
                                    <div class="p-3 rounded-3 border <?= ($step['status'] == 'selesai' ? 'bg-success bg-opacity-10 border-success-subtle' : ($step['status'] == 'berjalan' ? 'bg-primary bg-opacity-10 border-primary-subtle' : ($step['status'] == 'revisi' ? 'bg-danger bg-opacity-10 border-danger-subtle' : ($step['status'] == 'dilewati' ? 'bg-secondary bg-opacity-10 border-secondary-subtle' : 'bg-light border-light-subtle')))) ?>">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= ($step['status'] == 'selesai' ? 'bg-success text-white' : ($step['status'] == 'berjalan' ? 'bg-primary text-white' : ($step['status'] == 'revisi' ? 'bg-danger text-white' : ($step['status'] == 'dilewati' ? 'bg-secondary text-white' : 'bg-secondary bg-opacity-25 text-secondary')))) ?> rounded-pill px-2 py-1" style="font-size: 0.725rem;">
                                                    #<?= ($step['tahap_no'])."
" ?>
                                                </span>
                                                <span class="fw-bold text-dark small"><?= ($step['nama_aktivitas']) ?></span>
                                            </div>
                                            <div>
                                                <?php if ($step['status'] == 'selesai'): ?>
                                                    <span class="badge bg-success text-white" style="font-size: 0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>Selesai</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'berjalan'): ?>
                                                    <span class="badge bg-primary text-white" style="font-size: 0.7rem;"><i class="bi bi-hourglass-split me-1"></i>Berjalan</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'revisi'): ?>
                                                    <span class="badge bg-danger text-white" style="font-size: 0.7rem;"><i class="bi bi-exclamation-circle-fill me-1"></i>Revisi</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'dilewati'): ?>
                                                    <span class="badge bg-secondary text-white" style="font-size: 0.7rem;"><i class="bi bi-skip-forward-fill me-1"></i>Dilewati</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'menunggu'): ?>
                                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Menunggu</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Detail Pelaksana & Mutu Baku -->
                                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2" style="font-size: 0.725rem;">
                                            <span class="badge bg-white text-secondary border px-2 py-1">
                                                <i class="bi bi-person-badge text-primary me-1"></i><?= ($step['pelaksana_label'])."
" ?>
                                            </span>
                                            <span class="text-muted"><i class="bi bi-clock me-1"></i>Waktu: <strong class="text-dark"><?= ($step['mutu_waktu']) ?></strong></span>
                                            <span class="text-muted">&bull;</span>
                                            <span class="text-muted"><i class="bi bi-file-earmark-check me-1"></i>Output: <span class="text-dark fw-medium"><?= ($step['mutu_output']) ?></span></span>
                                        </div>

                                        <!-- Log Verifikasi / Catatan -->
                                        <?php if ($step['verified_by']): ?>
                                            <div class="small text-muted mb-2 p-1 px-2 bg-white rounded border" style="font-size: 0.7rem;">
                                                <i class="bi bi-info-circle text-primary me-1"></i>
                                                Diverifikasi oleh <strong><?= ($step['verified_by']) ?></strong>
                                                <?php if ($step['verified_at']): ?>
                                                    &bull; <?= (date('d/m/Y H:i', strtotime($step['verified_at'])))."
" ?>
                                                <?php endif; ?>
                                                <?php if ($step['catatan']): ?>
                                                    <div class="text-secondary mt-1"><em>"<?= ($step['catatan']) ?>"</em></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Tombol Aksi Verifikasi & Revisi -->
                                        <div class="d-flex flex-wrap gap-2 align-items-center mt-2 pt-1 border-top border-secondary-subtle">
                                            <?php if ($step['status'] != 'selesai' && $step['status'] != 'dilewati'): ?>
                                                <!-- Form Verifikasi -->
                                                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/<?= ($step['tahap_no']) ?>/verifikasi" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary py-1 px-2" style="font-size: 0.75rem;">
                                                        <i class="bi bi-check-lg me-1"></i> Verifikasi Selesai
                                                    </button>
                                                </form>

                                                <!-- Form Minta Revisi (Decision Step 8) -->
                                                <?php if ($step['is_decision'] == 1): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.75rem;" data-bs-toggle="collapse" data-bs-target="#revisi-form-<?= ($step['tahap_no']) ?>">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Perlu Revisi
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($step['status'] == 'selesai'): ?>
                                                <span class="text-success small fw-medium" style="font-size: 0.725rem;">
                                                    <i class="bi bi-check-all"></i> Tahapan tuntas
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($step['status'] == 'dilewati'): ?>
                                                <span class="text-muted small" style="font-size: 0.725rem;">
                                                    <i class="bi bi-skip-forward"></i> Tidak dipersyaratkan di kontrak
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Collapse Form Catatan Revisi (Tahap 8 -> kembali ke 6) -->
                                        <?php if ($step['is_decision'] == 1): ?>
                                            <div class="collapse mt-2" id="revisi-form-<?= ($step['tahap_no']) ?>">
                                                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/<?= ($step['tahap_no']) ?>/revisi" method="POST" class="p-2 bg-white rounded border">
                                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                    <input type="hidden" name="target_tahap" value="6">
                                                    <label class="form-label small fw-semibold text-danger mb-1" style="font-size: 0.725rem;">Catatan Perbaikan Draft Perkembangan (Kembali ke Tahap #6):</label>
                                                    <div class="input-group input-group-sm mb-1">
                                                        <input type="text" name="catatan_revisi" class="form-control form-control-sm" placeholder="Tuliskan catatan perbaikan..." required>
                                                        <button type="submit" class="btn btn-danger btn-sm">Kirim Revisi</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- FASE 3: PENGESAHAN LAPORAN AKHIR (13 - 14) -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="fase-pengesahan" role="tabpanel">
                        <div class="d-flex flex-column gap-3">
                            <?php foreach (($daftar_sop?:[]) as $step): ?>
                                <?php if ($step['fase'] == 'pengesahan'): ?>
                                    <div class="p-3 rounded-3 border <?= ($step['status'] == 'selesai' ? 'bg-success bg-opacity-10 border-success-subtle' : ($step['status'] == 'berjalan' ? 'bg-primary bg-opacity-10 border-primary-subtle' : ($step['status'] == 'revisi' ? 'bg-danger bg-opacity-10 border-danger-subtle' : 'bg-light border-light-subtle'))) ?>">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= ($step['status'] == 'selesai' ? 'bg-success text-white' : ($step['status'] == 'berjalan' ? 'bg-primary text-white' : ($step['status'] == 'revisi' ? 'bg-danger text-white' : 'bg-secondary bg-opacity-25 text-secondary'))) ?> rounded-pill px-2 py-1" style="font-size: 0.725rem;">
                                                    #<?= ($step['tahap_no'])."
" ?>
                                                </span>
                                                <span class="fw-bold text-dark small"><?= ($step['nama_aktivitas']) ?></span>
                                            </div>
                                            <div>
                                                <?php if ($step['status'] == 'selesai'): ?>
                                                    <span class="badge bg-success text-white" style="font-size: 0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>Selesai</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'berjalan'): ?>
                                                    <span class="badge bg-primary text-white" style="font-size: 0.7rem;"><i class="bi bi-hourglass-split me-1"></i>Berjalan</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'revisi'): ?>
                                                    <span class="badge bg-danger text-white" style="font-size: 0.7rem;"><i class="bi bi-exclamation-circle-fill me-1"></i>Revisi</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'menunggu'): ?>
                                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Menunggu</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Detail Pelaksana & Mutu Baku -->
                                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2" style="font-size: 0.725rem;">
                                            <span class="badge bg-white text-secondary border px-2 py-1">
                                                <i class="bi bi-person-badge text-primary me-1"></i><?= ($step['pelaksana_label'])."
" ?>
                                            </span>
                                            <span class="text-muted"><i class="bi bi-clock me-1"></i>Waktu: <strong class="text-dark"><?= ($step['mutu_waktu']) ?></strong></span>
                                            <span class="text-muted">&bull;</span>
                                            <span class="text-muted"><i class="bi bi-file-earmark-check me-1"></i>Output: <span class="text-dark fw-medium"><?= ($step['mutu_output']) ?></span></span>
                                        </div>

                                        <!-- Log Verifikasi / Catatan -->
                                        <?php if ($step['verified_by']): ?>
                                            <div class="small text-muted mb-2 p-1 px-2 bg-white rounded border" style="font-size: 0.7rem;">
                                                <i class="bi bi-info-circle text-primary me-1"></i>
                                                Diverifikasi oleh <strong><?= ($step['verified_by']) ?></strong>
                                                <?php if ($step['verified_at']): ?>
                                                    &bull; <?= (date('d/m/Y H:i', strtotime($step['verified_at'])))."
" ?>
                                                <?php endif; ?>
                                                <?php if ($step['catatan']): ?>
                                                    <div class="text-secondary mt-1"><em>"<?= ($step['catatan']) ?>"</em></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Tombol Aksi Verifikasi & Revisi -->
                                        <div class="d-flex flex-wrap gap-2 align-items-center mt-2 pt-1 border-top border-secondary-subtle">
                                            <?php if ($step['status'] != 'selesai'): ?>
                                                <!-- Form Verifikasi / Pengesahan -->
                                                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/<?= ($step['tahap_no']) ?>/verifikasi" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary py-1 px-2" style="font-size: 0.75rem;">
                                                        <i class="bi bi-pen-fill me-1"></i> <?= ($step['tahap_no'] == 14 ? 'Tandatangani & Sahkan' : 'Verifikasi Selesai')."
" ?>
                                                    </button>
                                                </form>

                                                <!-- Form Minta Revisi -->
                                                <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.75rem;" data-bs-toggle="collapse" data-bs-target="#revisi-form-<?= ($step['tahap_no']) ?>">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Perlu Revisi
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($step['status'] == 'selesai'): ?>
                                                <span class="text-success small fw-medium" style="font-size: 0.725rem;">
                                                    <i class="bi bi-check-all"></i> Laporan disahkan & ditandatangani
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Collapse Form Catatan Revisi (Tahap 13 -> ke 12, Tahap 14 -> ke 13) -->
                                        <div class="collapse mt-2" id="revisi-form-<?= ($step['tahap_no']) ?>">
                                            <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/<?= ($step['tahap_no']) ?>/revisi" method="POST" class="p-2 bg-white rounded border">
                                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                <input type="hidden" name="target_tahap" value="<?= ($step['tahap_no'] == 14 ? 13 : 12) ?>">
                                                <label class="form-label small fw-semibold text-danger mb-1" style="font-size: 0.725rem;">Catatan Perbaikan (Kembali ke Tahap #<?= ($step['tahap_no'] == 14 ? 13 : 12) ?>):</label>
                                                <div class="input-group input-group-sm mb-1">
                                                    <input type="text" name="catatan_revisi" class="form-control form-control-sm" placeholder="Tuliskan catatan perbaikan laporan..." required>
                                                    <button type="submit" class="btn btn-danger btn-sm">Kirim Revisi</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- FASE 4: BAST & PENGARSIPAN SELESAI (15 - 19) -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="fase-bast" role="tabpanel">
                        <div class="d-flex flex-column gap-3">
                            <?php foreach (($daftar_sop?:[]) as $step): ?>
                                <?php if ($step['fase'] == 'bast'): ?>
                                    <div class="p-3 rounded-3 border <?= ($step['status'] == 'selesai' ? 'bg-success bg-opacity-10 border-success-subtle' : ($step['status'] == 'berjalan' ? 'bg-primary bg-opacity-10 border-primary-subtle' : ($step['status'] == 'revisi' ? 'bg-danger bg-opacity-10 border-danger-subtle' : 'bg-light border-light-subtle'))) ?>">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= ($step['status'] == 'selesai' ? 'bg-success text-white' : ($step['status'] == 'berjalan' ? 'bg-primary text-white' : ($step['status'] == 'revisi' ? 'bg-danger text-white' : 'bg-secondary bg-opacity-25 text-secondary'))) ?> rounded-pill px-2 py-1" style="font-size: 0.725rem;">
                                                    #<?= ($step['tahap_no'])."
" ?>
                                                </span>
                                                <span class="fw-bold text-dark small"><?= ($step['nama_aktivitas']) ?></span>
                                            </div>
                                            <div>
                                                <?php if ($step['status'] == 'selesai'): ?>
                                                    <span class="badge bg-success text-white" style="font-size: 0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>Selesai</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'berjalan'): ?>
                                                    <span class="badge bg-primary text-white" style="font-size: 0.7rem;"><i class="bi bi-hourglass-split me-1"></i>Berjalan</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'revisi'): ?>
                                                    <span class="badge bg-danger text-white" style="font-size: 0.7rem;"><i class="bi bi-exclamation-circle-fill me-1"></i>Revisi</span>
                                                <?php endif; ?>
                                                <?php if ($step['status'] == 'menunggu'): ?>
                                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Menunggu</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Detail Pelaksana & Mutu Baku -->
                                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2" style="font-size: 0.725rem;">
                                            <span class="badge bg-white text-secondary border px-2 py-1">
                                                <i class="bi bi-person-badge text-primary me-1"></i><?= ($step['pelaksana_label'])."
" ?>
                                            </span>
                                            <span class="text-muted"><i class="bi bi-clock me-1"></i>Waktu: <strong class="text-dark"><?= ($step['mutu_waktu']) ?></strong></span>
                                            <span class="text-muted">&bull;</span>
                                            <span class="text-muted"><i class="bi bi-file-earmark-check me-1"></i>Output: <span class="text-dark fw-medium"><?= ($step['mutu_output']) ?></span></span>
                                        </div>

                                        <!-- Log Verifikasi / Catatan -->
                                        <?php if ($step['verified_by']): ?>
                                            <div class="small text-muted mb-2 p-1 px-2 bg-white rounded border" style="font-size: 0.7rem;">
                                                <i class="bi bi-info-circle text-primary me-1"></i>
                                                Diverifikasi oleh <strong><?= ($step['verified_by']) ?></strong>
                                                <?php if ($step['verified_at']): ?>
                                                    &bull; <?= (date('d/m/Y H:i', strtotime($step['verified_at'])))."
" ?>
                                                <?php endif; ?>
                                                <?php if ($step['catatan']): ?>
                                                    <div class="text-secondary mt-1"><em>"<?= ($step['catatan']) ?>"</em></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Tombol Aksi Verifikasi & Revisi -->
                                        <div class="d-flex flex-wrap gap-2 align-items-center mt-2 pt-1 border-top border-secondary-subtle">
                                            <?php if ($step['status'] != 'selesai'): ?>
                                                <!-- Form Verifikasi -->
                                                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/<?= ($step['tahap_no']) ?>/verifikasi" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary py-1 px-2" style="font-size: 0.75rem;">
                                                        <i class="bi bi-check-lg me-1"></i> <?= ($step['tahap_no'] == 19 ? 'Arsipkan & Selesaikan PO' : 'Verifikasi Selesai')."
" ?>
                                                    </button>
                                                </form>

                                                <!-- Form Minta Revisi (Step 18) -->
                                                <?php if ($step['is_decision'] == 1): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.75rem;" data-bs-toggle="collapse" data-bs-target="#revisi-form-<?= ($step['tahap_no']) ?>">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Perlu Revisi
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($step['status'] == 'selesai'): ?>
                                                <span class="text-success small fw-medium" style="font-size: 0.725rem;">
                                                    <i class="bi bi-check2-all"></i> <?= ($step['tahap_no'] == 19 ? 'Dokumentasi kegiatan telah diarsipkan (Selesai)' : 'Tahapan tuntas')."
" ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Collapse Form Catatan Revisi (Tahap 18 -> kembali ke 17) -->
                                        <?php if ($step['is_decision'] == 1): ?>
                                            <div class="collapse mt-2" id="revisi-form-<?= ($step['tahap_no']) ?>">
                                                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/sop/<?= ($step['tahap_no']) ?>/revisi" method="POST" class="p-2 bg-white rounded border">
                                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                    <input type="hidden" name="target_tahap" value="17">
                                                    <label class="form-label small fw-semibold text-danger mb-1" style="font-size: 0.725rem;">Catatan Revisi BAST (Kembali ke Tahap #17):</label>
                                                    <div class="input-group input-group-sm mb-1">
                                                        <input type="text" name="catatan_revisi" class="form-control form-control-sm" placeholder="Tuliskan catatan perbaikan BAST..." required>
                                                        <button type="submit" class="btn btn-danger btn-sm">Kirim Revisi</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        </div>

    </div>

    <!-- ======================================================== -->
    <!-- KOLOM KANAN: RAB, JADWAL TIM, PEMBAYARAN, EVALUASI -->
    <!-- ======================================================== -->
    <div class="col-lg-6">
        
        <!-- KARTU 4: RINCIAN ANGGARAN BIAYA (RAB BREAKDOWN) -->
        <div class="card border-0 shadow-sm mb-4" id="rab-section">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-calculator text-primary me-2"></i>Rincian Anggaran Biaya (RAB)</h6>
                    <span class="text-muted" style="font-size: 0.75rem;">Akumulasi otomatis ke Nilai PO</span>
                </div>
                <span class="badge badge-pill-success fs-6 text-nowrap">
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
                                        <td class="text-nowrap"><span class="badge badge-pill-secondary" style="font-size: 0.7rem;"><?= ($rab['kategori']) ?></span></td>
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

        <!-- KARTU 5: JADWAL KERJA TIM PELAKSANA (TIMELINE) -->
        <div class="card border-0 shadow-sm mb-4" id="jadwal-section">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-calendar-range text-primary me-2"></i>Jadwal Kerja Tim Pelaksana (Milestone)</h6>
                <span class="badge bg-light text-muted border"><?= (count($daftar_jadwal)) ?> Kegiatan</span>
            </div>
            <div class="card-body p-3 p-md-4">
                <?php if (count($daftar_jadwal) > 0): ?>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php foreach (($daftar_jadwal?:[]) as $j): ?>
                            <div class="p-3 rounded-3 border bg-white shadow-xs d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                                <div class="me-2">
                                    <div class="fw-bold text-dark small mb-1 d-flex align-items-center gap-2">
                                        <?php if ($j['status_pekerjaan'] == 'selesai'): ?>
                                            <i class="bi bi-check-circle-fill text-primary"></i>
                                        <?php endif; ?>
                                        <?php if ($j['status_pekerjaan'] == 'berjalan'): ?>
                                            <i class="bi bi-play-circle-fill text-primary"></i>
                                        <?php endif; ?>
                                        <?php if ($j['status_pekerjaan'] == 'rencana'): ?>
                                            <i class="bi bi-clock-fill text-secondary"></i>
                                        <?php endif; ?>
                                        <span><?= ($j['tahap_kegiatan']) ?></span>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <span class="fw-medium text-secondary"><i class="bi bi-person me-1"></i><?= ($j['personil_anggota']) ?></span>
                                        <span class="mx-1">&bull;</span>
                                        <span><i class="bi bi-calendar3 me-1"></i><?= (date('d/m/Y', strtotime($j['tanggal_mulai']))) ?> s.d. <?= (date('d/m/Y', strtotime($j['tanggal_selesai']))) ?></span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/jadwal/<?= ($j['id']) ?>/status" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                        <select name="status" class="form-select form-select-sm <?= ($j['status_pekerjaan'] == 'selesai' || $j['status_pekerjaan'] == 'berjalan' ? 'border-primary text-primary fw-semibold bg-primary bg-opacity-10' : 'border-secondary-subtle text-secondary bg-light') ?>" style="font-size: 0.75rem; min-width: 105px; height: 32px; padding: 0.25rem 1.75rem 0.25rem 0.6rem; border-radius: 6px;" onchange="this.form.submit()">
                                            <option value="rencana" <?= ($j['status_pekerjaan'] == 'rencana' ? 'selected' : '') ?>>Rencana</option>
                                            <option value="berjalan" <?= ($j['status_pekerjaan'] == 'berjalan' ? 'selected' : '') ?>>Berjalan</option>
                                            <option value="selesai" <?= ($j['status_pekerjaan'] == 'selesai' ? 'selected' : '') ?>>Selesai</option>
                                        </select>
                                    </form>
                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/jadwal/<?= ($j['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-light border text-danger p-1 rounded-2" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;" title="Hapus Jadwal"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Form Tambah Jadwal -->
                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/jadwal/tambah" method="POST" class="p-3 bg-light rounded-3 border">
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <input type="text" name="personil_anggota" class="form-control form-control-sm" placeholder="Personil / Penanggung Jawab" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="tahap_kegiatan" class="form-control form-control-sm" placeholder="Uraian Tahap Kegiatan" required>
                        </div>
                    </div>
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <input type="date" name="tanggal_mulai" class="form-control form-control-sm" value="<?= (date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <input type="date" name="tanggal_selesai" class="form-control form-control-sm" value="<?= (date('Y-m-d', strtotime('+1 week'))) ?>" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100 py-1 shadow-sm d-flex align-items-center justify-content-center gap-1" title="Tambah Jadwal">
                                <i class="bi bi-plus-lg"></i> <span>Tambah</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- KARTU 6: PEMBAYARAN MULTI-TERMIN -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-cash-stack text-primary me-2"></i>Pembayaran & Pelunasan Tagihan</h6>
                <a href="<?= ($BASE) ?>/pembayaran/tambah?order_id=<?= ($po['order_id']) ?>" class="btn btn-sm btn-primary py-1 px-2">
                    <i class="bi bi-plus-lg"></i> Catat Bayar
                </a>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4 p-2 border rounded bg-light">
                        <span class="text-muted d-block" style="font-size: 0.7rem;">Nilai PO</span>
                        <strong class="text-dark small">Rp <?= (number_format($po['biaya'], 0, ',', '.')) ?></strong>
                    </div>
                    <div class="col-4 p-2 border rounded bg-success bg-opacity-10 text-success">
                        <span class="d-block" style="font-size: 0.7rem;">Terbayar</span>
                        <strong class="small">Rp <?= (number_format($total_terbayar, 0, ',', '.')) ?></strong>
                    </div>
                    <div class="col-4 p-2 border rounded <?= ($sisa_tagihan > 0 ? 'bg-warning bg-opacity-10 text-dark' : 'bg-light') ?>">
                        <span class="text-muted d-block" style="font-size: 0.7rem;">Sisa Tagihan</span>
                        <strong class="<?= ($sisa_tagihan > 0 ? 'text-danger' : 'text-success') ?> small">
                            <?= ($sisa_tagihan > 0 ? 'Rp ' . number_format($sisa_tagihan, 0, ',', '.') : 'LUNAS')."
" ?>
                        </strong>
                    </div>
                </div>

                <?php if (count($daftar_pembayaran) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Termin</th>
                                    <th>Tanggal</th>
                                    <th class="text-end text-nowrap" style="white-space: nowrap;">Jumlah (Rp)</th>
                                    <th style="width: 25px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($daftar_pembayaran?:[]) as $bayar): ?>
                                    <tr>
                                        <td><span class="badge badge-pill-primary" style="font-size: 0.7rem;">Termin <?= ($bayar['termin_ke']) ?></span></td>
                                        <td class="small text-nowrap"><?= (date('d/m/Y', strtotime($bayar['tanggal_bayar']))) ?></td>
                                        <td class="text-end fw-bold small text-success text-nowrap" style="white-space: nowrap;">Rp <?= (number_format($bayar['jumlah'], 0, ',', '.')) ?></td>
                                        <td>
                                            <form action="<?= ($BASE) ?>/pembayaran/<?= ($bayar['id']) ?>/hapus" method="POST" class="d-inline" onsubmit="return confirm('Hapus transaksi pembayaran ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                <input type="hidden" name="redirect_po_id" value="<?= ($po['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KARTU 7: EVALUASI FEEDBACK LOOP CUSTOMER -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-chat-left-text text-primary me-2"></i>Evaluasi Hasil Kerja Bersama Mitra (Feedback Loop)</h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/evaluasi" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-2">Status Hasil Evaluasi Mitra</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="evaluasi_status" id="evaluasi_setuju" value="disetujui" autocomplete="off" <?= ($po['evaluasi_status'] == 'disetujui' || empty($po['evaluasi_status']) || $po['evaluasi_status'] == 'pending' ? 'checked' : '') ?>>
                                <label class="btn btn-outline-success w-100 py-2 d-flex flex-column align-items-center justify-content-center gap-1 shadow-none" for="evaluasi_setuju" style="cursor: pointer;">
                                    <span class="fw-bold fs-6"><i class="bi bi-check-circle me-1"></i>Disetujui</span>
                                    <span style="font-size: 0.7rem; opacity: 0.85;">Lanjut ke Laporan Akhir</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="evaluasi_status" id="evaluasi_revisi" value="perlu_revisi" autocomplete="off" <?= ($po['evaluasi_status'] == 'perlu_revisi' ? 'checked' : '') ?>>
                                <label class="btn btn-outline-danger w-100 py-2 d-flex flex-column align-items-center justify-content-center gap-1 shadow-none" for="evaluasi_revisi" style="cursor: pointer;">
                                    <span class="fw-bold fs-6"><i class="bi bi-arrow-counterclockwise me-1"></i>Tidak Disetujui</span>
                                    <span style="font-size: 0.7rem; opacity: 0.85;">Perlu Revisi Uji</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Notulen / Catatan Masukan Customer</label>
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

        <!-- KARTU 8: AUDIT TRAIL LOG STATUS -->
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
</div>
