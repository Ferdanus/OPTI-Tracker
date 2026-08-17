<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
        <a href="<?= ($BASE) ?>/po" class="btn-back">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <div>
            <h4 class="fw-bold mb-0 text-dark">
                <i class="bi bi-file-earmark-text text-primary me-2"></i><?= ($po['nomor_po'])."
" ?>
            </h4>
            <span class="text-muted small">Diterbitkan otomatis pada <?= (date('d F Y, H:i', strtotime($po['created_at']))) ?> WIB</span>
        </div>
    </div>
    <div>
        <?php if ($po['status'] == 'belum_upload'): ?>
            <span class="badge bg-secondary fs-6 px-3 py-2 text-uppercase rounded-pill" style="font-weight: 600;">Belum Upload</span>
        <?php endif; ?>
        <?php if ($po['status'] == 'sudah_upload'): ?>
            <span class="badge bg-warning text-dark fs-6 px-3 py-2 text-uppercase rounded-pill" style="font-weight: 600;">Sudah Upload</span>
        <?php endif; ?>
        <?php if ($po['status'] == 'on_proses'): ?>
            <span class="badge bg-primary fs-6 px-3 py-2 text-uppercase rounded-pill" style="font-weight: 600;">On Proses</span>
        <?php endif; ?>
        <?php if ($po['status'] == 'kembali_selesai'): ?>
            <span class="badge bg-success fs-6 px-3 py-2 text-uppercase rounded-pill" style="font-weight: 600;">Selesai</span>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- 1. VISUAL PROGRESS TIMELINE (LIFECYCLE) -->
<!-- ========================================== -->
<div class="card mb-4 shadow-sm" data-aos="fade-up">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Visual Progress Timeline Layanan Jasa OPTI</h6>
    </div>
    <div class="card-body bg-light bg-opacity-30">
        <div class="row text-center g-2 align-items-center">
            <!-- Tahap 1: Order Masuk -->
            <div class="col-6 col-md">
                <div class="p-3 border rounded bg-success text-white fw-bold">
                    <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                    <div style="font-size: 0.65rem; opacity: 0.85;">TAHAP 1</div>
                    <div class="small">Order Masuk</div>
                </div>
            </div>
            <div class="col-auto text-success d-none d-md-block"><i class="bi bi-check-lg fs-5"></i></div>
            
            <!-- Tahap 2: Penawaran Disetujui -->
            <div class="col-6 col-md">
                <div class="p-3 border rounded bg-success text-white fw-bold">
                    <i class="bi bi-check-circle fs-4 d-block mb-1"></i>
                    <div style="font-size: 0.65rem; opacity: 0.85;">TAHAP 2</div>
                    <div class="small">Penawaran Disetujui</div>
                </div>
            </div>
            <div class="col-auto text-success d-none d-md-block"><i class="bi bi-check-lg fs-5"></i></div>
            
            <!-- Tahap 3: Map Kendali PO -->
            <?php $mapApproved = ($po['app_proposal'] && $po['app_kontrak'] && $po['app_po_adm'] && $po['app_po_mitra'] && $po['app_po_ppk'] && $po['app_po_kabag'] && $po['app_dist_tu'] && $po['app_dist_kepeg'] && $po['app_dist_keu']); ?>
            <div class="col-6 col-md">
                <div class="p-3 border rounded <?= ($mapApproved ? 'bg-success text-white' : 'bg-white text-dark border-primary') ?> fw-bold">
                    <i class="bi bi-file-earmark-check fs-4 d-block mb-1 text-primary <?= ($mapApproved ? 'text-white' : '') ?>"></i>
                    <div style="font-size: 0.65rem; opacity: 0.85; color: var(--color-muted) <?= ($mapApproved ? '; color:white' : '') ?>">TAHAP 3</div>
                    <div class="small">Map Kendali</div>
                </div>
            </div>
            <div class="col-auto text-muted d-none d-md-block"><i class="bi bi-chevron-right"></i></div>
            
            <!-- Tahap 4: PKS Disepakati (Kontrak) -->
            <div class="col-6 col-md">
                <div class="p-3 border rounded <?= ($po['kontrak_id'] ? 'bg-success text-white' : 'bg-white text-muted') ?> fw-bold">
                    <i class="bi bi-journal-text fs-4 d-block mb-1"></i>
                    <div style="font-size: 0.65rem; opacity: 0.85;">TAHAP 4</div>
                    <div class="small">PKS Disepakati</div>
                </div>
            </div>
            <div class="col-auto text-muted d-none d-md-block"><i class="bi bi-chevron-right"></i></div>
            
            <!-- Tahap 5: Pelaksanaan Pekerjaan -->
            <div class="col-6 col-md">
                <div class="p-3 border rounded <?= (in_array($po['status'], ['on_proses','kembali_selesai']) ? 'bg-success text-white' : 'bg-white text-muted') ?> fw-bold">
                    <i class="bi bi-hammer fs-4 d-block mb-1"></i>
                    <div style="font-size: 0.65rem; opacity: 0.85;">TAHAP 5</div>
                    <div class="small">Pelaksanaan</div>
                </div>
            </div>
            <div class="col-auto text-muted d-none d-md-block"><i class="bi bi-chevron-right"></i></div>
            
            <!-- Tahap 6: BAST & Selesai -->
            <div class="col-6 col-md">
                <div class="p-3 border rounded <?= ($po['status'] == 'kembali_selesai' ? 'bg-success text-white' : 'bg-white text-muted') ?> fw-bold">
                    <i class="bi bi-archive fs-4 d-block mb-1"></i>
                    <div style="font-size: 0.65rem; opacity: 0.85;">TAHAP 6</div>
                    <div class="small">BAST & Selesai</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- ========================================== -->
    <!-- KOLOM KIRI: INFO PO, CLIENT, & MAP KENDALI -->
    <!-- ========================================== -->
    <div class="col-lg-7" data-aos="fade-up">
        <!-- Card Detail PO & Order -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Detail Pekerjaan & PO</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-muted small">No Order</span>
                        <div class="fw-bold text-dark"><?= ($po['nomor_order']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small">Jenis Layanan</span>
                        <div>
                            <?php if ($po['jenis_layanan'] == 'selulosa'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20"><i class="bi bi-tag-fill me-1"></i>OPTI Selulosa dan Derivat</span>
                            <?php endif; ?>
                            <?php if ($po['jenis_layanan'] == 'lingkungan'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-tag-fill me-1"></i>OPTI Lingkungan</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <span class="text-muted small">Judul Kegiatan</span>
                        <div class="fw-bold text-dark fs-5"><?= ($po['judul_kegiatan']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small">Biaya PO</span>
                        <div class="fw-bold text-success">Rp <?= (number_format($po['biaya'], 0, ',', '.')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small">Jumlah Pekerjaan / Alat</span>
                        <div class="fw-bold text-dark"><?= ($po['jumlah_pekerjaan']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small">Tim Kerja OPTI</span>
                        <div class="fw-bold text-dark"><?= ($po['tim_kerja'] ?: 'Belum dibentuk') ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small">Target Pelaksanaan</span>
                        <div class="fw-bold text-dark">
                            <?= ($po['target_mulai'] ? date('d/m/Y', strtotime($po['target_mulai'])) : '-') ?> s/d <?= ($po['target_selesai'] ? date('d/m/Y', strtotime($po['target_selesai'])) : '-')."
" ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small">Tanggal Keluar PO</span>
                        <div class="fw-bold text-dark"><?= ($po['tanggal_keluar'] ? date('d/m/Y', strtotime($po['tanggal_keluar'])) : '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small">Tanggal Kembali (Revisi)</span>
                        <div class="fw-bold text-dark"><?= ($po['tanggal_kembali'] ? date('d/m/Y', strtotime($po['tanggal_kembali'])) : '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small">Realisasi Selesai</span>
                        <div class="fw-bold text-success"><?= ($po['realisasi_selesai'] ? date('d/m/Y', strtotime($po['realisasi_selesai'])) : '-') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Map Kendali Verifikasi & Validasi -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-shield-check me-2 text-primary"></i>Map Kendali - Verifikasi & Validasi PO</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light text-uppercase" style="font-size: 0.75rem;">
                            <tr>
                                <th style="width: 50px;" class="text-center">No</th>
                                <th>Tahap Verifikasi</th>
                                <th>Distribusi / Pihak Berwenang</th>
                                <th>Status Tanda Tangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Tahap 1: Proposal -->
                            <tr>
                                <td class="text-center" rowspan="2">1</td>
                                <td rowspan="2"><strong>1. Proposal</strong></td>
                                <td>Verifikasi: Adm KS & Humas / Staf</td>
                                <td>
                                    <?php if ($po['app_proposal']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Terverifikasi (<?= (date('d/m/Y H:i', strtotime($po['app_proposal_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/proposal" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Validasi: Kepala Balai / Pimpinan</td>
                                <td>
                                    <?php if ($po['app_proposal_val']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Tervalidasi (<?= (date('d/m/Y H:i', strtotime($po['app_proposal_val_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/proposal_val" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Tahap 2: Kontrak -->
                            <tr>
                                <td class="text-center" rowspan="2">2</td>
                                <td rowspan="2"><strong>2. Kontrak</strong></td>
                                <td>Verifikasi: Admin Kontrak</td>
                                <td>
                                    <?php if ($po['app_kontrak']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Terverifikasi (<?= (date('d/m/Y H:i', strtotime($po['app_kontrak_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/kontrak" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Validasi: Pimpinan / PPK BLU</td>
                                <td>
                                    <?php if ($po['app_kontrak_val']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Tervalidasi (<?= (date('d/m/Y H:i', strtotime($po['app_kontrak_val_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/kontrak_val" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Tahap 3: Petunjuk Operasional (Didistribusikan ke 4 Pihak) -->
                            <tr>
                                <td class="text-center" rowspan="4">3</td>
                                <td rowspan="4"><strong>3. Petunjuk Operasional</strong><br><span class="text-muted small">Distribusi Berkas PO</span></td>
                                <td>Adm KS & Humas</td>
                                <td>
                                    <?php if ($po['app_po_adm']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Disetujui (<?= (date('d/m/Y H:i', strtotime($po['app_po_adm_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/po_adm" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Tim Mitra Industri</td>
                                <td>
                                    <?php if ($po['app_po_mitra']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Disetujui (<?= (date('d/m/Y H:i', strtotime($po['app_po_mitra_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/po_mitra" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>PPK BLU</td>
                                <td>
                                    <?php if ($po['app_po_ppk']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Disetujui (<?= (date('d/m/Y H:i', strtotime($po['app_po_ppk_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/po_ppk" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Ka.Bag Tata Usaha</td>
                                <td>
                                    <?php if ($po['app_po_kabag']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Disetujui (<?= (date('d/m/Y H:i', strtotime($po['app_po_kabag_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/po_kabag" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Tahap 4: Distribusi PO (Didistribusikan ke 3 Pihak) -->
                            <tr>
                                <td class="text-center" rowspan="3">4</td>
                                <td rowspan="3"><strong>4. Distribusi</strong><br><span class="text-muted small">Penerima Arsip</span></td>
                                <td>Bagian TU (Arsip)</td>
                                <td>
                                    <?php if ($po['app_dist_tu']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Diterima (<?= (date('d/m/Y H:i', strtotime($po['app_dist_tu_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/dist_tu" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Tim Kepegawaian & Tata Laksana</td>
                                <td>
                                    <?php if ($po['app_dist_kepeg']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Diterima (<?= (date('d/m/Y H:i', strtotime($po['app_dist_kepeg_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/dist_kepeg" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Tim Keuangan & BMN</td>
                                <td>
                                    <?php if ($po['app_dist_keu']): ?>
                                        
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20"><i class="bi bi-check-circle-fill me-1"></i>Diterima (<?= (date('d/m/Y H:i', strtotime($po['app_dist_keu_date']))) ?>)</span>
                                        
                                        <?php else: ?>
                                            <?php if ($SESSION['role'] == 'pejabat' || $SESSION['role'] == 'superadmin'): ?>
                                                
                                                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/approve-map/dist_keu" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-xs btn-primary py-0.5 px-2" style="font-size: 0.725rem;"><i class="bi bi-pen me-1"></i>Tanda Tangan</button>
                                                    </form>
                                                
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-hourglass-split me-1"></i>Belum TTD</span>
                                                
                                            <?php endif; ?>
                                        
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Card Pelaksanaan & Laporan (SOP OPTI) -->
        <div class="card mb-4 shadow-sm border-start border-4 border-success">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-file-earmark-medical me-2 text-success"></i>Pelaksanaan & Laporan (SOP OPTI)</h6>
                <?php if ($SESSION['role'] == 'ketua_tim' || $SESSION['role'] == 'tim_kerja' || $SESSION['role'] == 'superadmin'): ?>
                    <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#formPelaksanaan" aria-expanded="false" aria-controls="formPelaksanaan">
                        <i class="bi bi-pencil-square me-1"></i>Edit Data SOP
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <!-- Tampilan Data Saat Ini -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="small text-secondary fw-semibold">Draft Laporan Perkembangan:</div>
                        <div class="text-dark fw-bold"><?= ($po['laporan_perkembangan'] ?: 'Belum diisi') ?></div>
                        <?php if ($po['tgl_laporan_perkembangan']): ?>
                            <div class="small text-muted">Tanggal: <?= (date('d/m/Y', strtotime($po['tgl_laporan_perkembangan']))) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-secondary fw-semibold">Notulen Masukan Pelanggan:</div>
                        <div class="text-dark fw-bold"><?= ($po['notulen_masukan'] ?: 'Belum ada masukan') ?></div>
                        <?php if ($po['tgl_notulen_masukan']): ?>
                            <div class="small text-muted">Tanggal: <?= (date('d/m/Y', strtotime($po['tgl_notulen_masukan']))) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 border-top pt-2">
                        <div class="small text-secondary fw-semibold">Laporan Kegiatan Final:</div>
                        <div class="text-dark fw-bold"><?= ($po['laporan_kegiatan_final'] ?: 'Belum diisi') ?></div>
                        <?php if ($po['tgl_laporan_kegiatan_final']): ?>
                            <div class="small text-muted">Tanggal: <?= (date('d/m/Y', strtotime($po['tgl_laporan_kegiatan_final']))) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 border-top pt-2">
                        <div class="small text-secondary fw-semibold">Dokumen BAST:</div>
                        <div class="text-dark fw-bold"><?= ($po['bast_dokumen'] ?: 'Belum diupload') ?></div>
                        <?php if ($po['tgl_bast']): ?>
                            <div class="small text-muted">Tanggal: <?= (date('d/m/Y', strtotime($po['tgl_bast']))) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Form Collapse untuk Edit Data (Hanya untuk Ketua Tim / Tim Kerja / Superadmin) -->
                <div class="collapse mt-3 border-top pt-3" id="formPelaksanaan">
                    <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/update-pelaksanaan" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                        
                        <div class="mb-3">
                            <label for="laporan_perkembangan" class="form-label small fw-bold text-secondary">Draft Laporan Perkembangan (Keterangan/Link)</label>
                            <input type="text" class="form-control form-control-sm" id="laporan_perkembangan" name="laporan_perkembangan" value="<?= ($po['laporan_perkembangan']) ?>" placeholder="Contoh: Draft Laporan v1.pdf / Google Drive Link">
                        </div>
                        <div class="mb-3">
                            <label for="tgl_laporan_perkembangan" class="form-label small fw-bold text-secondary">Tanggal Laporan Perkembangan</label>
                            <input type="date" class="form-control form-control-sm" id="tgl_laporan_perkembangan" name="tgl_laporan_perkembangan" value="<?= ($po['tgl_laporan_perkembangan']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="notulen_masukan" class="form-label small fw-bold text-secondary">Notulen Masukan Pelanggan (Feedback Loop)</label>
                            <textarea class="form-control form-control-sm" id="notulen_masukan" name="notulen_masukan" rows="2" placeholder="Catat saran atau feedback notulen pertemuan dengan pelanggan..."><?= ($po['notulen_masukan']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="tgl_notulen_masukan" class="form-label small fw-bold text-secondary">Tanggal Masukan/Notulen</label>
                            <input type="date" class="form-control form-control-sm" id="tgl_notulen_masukan" name="tgl_notulen_masukan" value="<?= ($po['tgl_notulen_masukan']) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="laporan_kegiatan_final" class="form-label small fw-bold text-secondary">Laporan Kegiatan Final (Keterangan/Link)</label>
                            <input type="text" class="form-control form-control-sm" id="laporan_kegiatan_final" name="laporan_kegiatan_final" value="<?= ($po['laporan_kegiatan_final']) ?>" placeholder="Contoh: Laporan Akhir Final.pdf">
                        </div>
                        <div class="mb-3">
                            <label for="tgl_laporan_kegiatan_final" class="form-label small fw-bold text-secondary">Tanggal Laporan Final</label>
                            <input type="date" class="form-control form-control-sm" id="tgl_laporan_kegiatan_final" name="tgl_laporan_kegiatan_final" value="<?= ($po['tgl_laporan_kegiatan_final']) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="bast_dokumen" class="form-label small fw-bold text-secondary">Dokumen BAST (Keterangan/Link)</label>
                            <input type="text" class="form-control form-control-sm" id="bast_dokumen" name="bast_dokumen" value="<?= ($po['bast_dokumen']) ?>" placeholder="Contoh: BAST_PT_SMS_Signed.pdf">
                        </div>
                        <div class="mb-3">
                            <label for="tgl_bast" class="form-label small fw-bold text-secondary">Tanggal BAST</label>
                            <input type="date" class="form-control form-control-sm" id="tgl_bast" name="tgl_bast" value="<?= ($po['tgl_bast']) ?>">
                        </div>

                        <button type="submit" class="btn btn-sm btn-success w-100 py-2">
                            <i class="bi bi-save me-1"></i> Simpan Data Pelaksanaan SOP
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Card Data Klien -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-building me-2 text-primary"></i>Informasi Klien / Pemohon</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th style="width: 180px;" class="text-secondary fw-semibold">Nama Perusahaan:</th>
                        <td class="fw-bold text-dark"><?= ($po['nama_perusahaan']) ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-semibold">PIC:</th>
                        <td class="text-secondary"><?= ($po['pic'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-semibold">Telepon:</th>
                        <td class="text-secondary"><?= ($po['telepon'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-semibold">Email:</th>
                        <td class="text-secondary"><?= ($po['email'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary fw-semibold">Alamat:</th>
                        <td class="small text-secondary"><?= ($po['alamat'] ?: '-') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- KOLOM KANAN: AKSI STATUS & AUDIT LOG -->
    <!-- ========================================== -->
    <div class="col-lg-5" data-aos="fade-up">
        <!-- Card Aksi Transisi Status PO (Hanya untuk Ketua Tim OPTI) -->
        <div class="card mb-4 shadow-sm border-primary" style="border-color: var(--color-primary) !important;">
            <div class="card-header bg-primary text-white py-3">
                <h6 class="m-0 fw-bold"><i class="bi bi-arrow-right-circle me-2"></i>Kontrol Status Pekerjaan PO</h6>
            </div>
            <div class="card-body">
                <?php if ($SESSION['role'] == 'ketua_tim' || $SESSION['role'] == 'superadmin'): ?>
                    
                        <?php if ($next_status): ?>
                            
                                <p class="small text-muted mb-3">
                                    Status saat ini: <strong><?= ($po['status']) ?></strong>. Silakan perbarui target dan teruskan status ke tahap berikutnya:
                                </p>
                                <form action="<?= ($BASE) ?>/po/<?= ($po['id']) ?>/lanjut-status" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memajukan status PO ini ke tahap <?= ($next_status_label) ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-secondary">Tahap Selanjutnya:</label>
                                        <div class="form-control bg-light fw-bold text-primary border-primary-subtle" style="color: var(--color-primary) !important;">
                                            <i class="bi bi-arrow-right me-1"></i><?= ($next_status_label)."
" ?>
                                        </div>
                                    </div>

                                    <!-- input dinamis jika statusnya lanjut ke 'sudah_upload' -->
                                    <?php if ($next_status == 'sudah_upload'): ?>
                                        <div class="mb-3">
                                            <label for="tim_kerja" class="form-label small fw-bold text-secondary">Tugaskan Tim Kerja / PIC OPTI <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="tim_kerja" name="tim_kerja" placeholder="Nama tim atau personil penguji" required value="<?= ($po['tim_kerja']) ?>">
                                        </div>
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <label for="target_mulai" class="form-label small fw-bold text-secondary">Target Mulai <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="target_mulai" name="target_mulai" required value="<?= ($po['target_mulai']) ?>">
                                            </div>
                                            <div class="col-6 mb-3">
                                                <label for="target_selesai" class="form-label small fw-bold text-secondary">Target Selesai <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="target_selesai" name="target_selesai" required value="<?= ($po['target_selesai']) ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="tanggal_keluar" class="form-label small fw-bold text-secondary">Tanggal Keluar PO <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control form-control-sm" id="tanggal_keluar" name="tanggal_keluar" required value="<?= ($po['tanggal_keluar'] ?: date('Y-m-d')) ?>">
                                        </div>
                                    <?php endif; ?>

                                    <!-- input dinamis jika statusnya lanjut ke 'kembali_selesai' -->
                                    <?php if ($next_status == 'kembali_selesai'): ?>
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <label for="tanggal_kembali" class="form-label small fw-bold text-secondary">Tanggal Kembali PO (Revisi)</label>
                                                <input type="date" class="form-control form-control-sm" id="tanggal_kembali" name="tanggal_kembali" value="<?= ($po['tanggal_kembali']) ?>">
                                            </div>
                                            <div class="col-6 mb-3">
                                                <label for="realisasi_selesai" class="form-label small fw-bold text-secondary">Realisasi Selesai (BAST) <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="realisasi_selesai" name="realisasi_selesai" required value="<?= ($po['realisasi_selesai'] ?: date('Y-m-d')) ?>">
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="catatan" class="form-label small fw-bold text-secondary">Catatan Perubahan Status <span class="text-danger">*</span></label>
                                        <textarea class="form-control form-control-sm" id="catatan" name="catatan" rows="2" placeholder="Catatan kemajuan pekerjaan atau kendala..." required></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 py-2">
                                        <i class="bi bi-check-circle me-1"></i> Perbarui & Majukan Status
                                    </button>
                                </form>
                            
                            <?php else: ?>
                                <div class="alert alert-success mb-0 text-center" role="alert" style="background-color: rgba(22, 101, 52, 0.08); border-color: rgba(22, 101, 52, 0.2); color: #166534;">
                                    <i class="bi bi-patch-check-fill display-6 d-block mb-2 text-success"></i>
                                    <h6 class="fw-bold mb-1">Pekerjaan Selesai</h6>
                                    <p class="small mb-0">Dokumen PO ini telah selesai dan diarsipkan di BAST.</p>
                                </div>
                            
                        <?php endif; ?>
                    
                    <?php else: ?>
                        <div class="alert alert-info mb-0 small border-0" role="alert">
                            <i class="bi bi-info-circle me-2 text-primary fs-5"></i>
                            Pembaruan tahapan status pengerjaan PO (Belum Upload -> Sudah Upload -> On Proses -> Selesai) hanya dapat dilakukan oleh akun dengan otoritas <strong>Ketua Tim OPTI</strong>.
                        </div>
                    
                <?php endif; ?>
            </div>
        </div>

        <!-- Card Riwayat Audit Trail Log -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Audit Trail Log</h6>
            </div>
            <div class="card-body p-0">
                <?php if (count($daftar_log) > 0): ?>
                    
                        <div class="list-group list-group-flush small">
                            <?php foreach (($daftar_log?:[]) as $log): ?>
                                <div class="list-group-item p-3 border-bottom border-light">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <?php if ($log['status_lama']): ?>
                                                
                                                    <span class="badge bg-secondary rounded-pill text-uppercase" style="font-size: 0.6rem;"><?= ($log['status_lama']) ?></span>
                                                    <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                                
                                            <?php endif; ?>
                                            <span class="badge badge-status-<?= ($log['status_baru']) ?> rounded-pill text-uppercase" style="font-size: 0.6rem;"><?= ($log['status_baru']) ?></span>
                                        </div>
                                        <span class="text-muted" style="font-size: 0.7rem;">
                                            <i class="bi bi-clock me-1"></i><?= (date('d/m/Y H:i', strtotime($log['tanggal'])))."
" ?>
                                        </span>
                                    </div>
                                    <div class="text-secondary mt-1 lh-base">
                                        <?= ($log['catatan'] ?: 'Perubahan status tercatat.')."
" ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    
                    <?php else: ?>
                        <div class="p-3 text-center text-muted small">Belum ada riwayat status.</div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
