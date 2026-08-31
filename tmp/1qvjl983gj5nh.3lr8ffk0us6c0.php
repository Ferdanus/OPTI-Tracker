<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-envelope text-primary me-1"></i> Registrasi Surat Masuk Balai</h2>
        <p class="text-muted small mb-0">Halaman khusus Sekretaris untuk mencatat surat permintaan dari mitra sebelum diproses oleh tim teknis.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: List of logged letters -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-list-task text-primary me-2"></i>Riwayat Registrasi Surat Anda</h6>
                <span class="badge bg-light text-muted border"><?= (count($daftar_surat)) ?> Surat</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($daftar_surat) > 0): ?>
                    
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No</th>
                                        <th>No. Surat / Tanggal</th>
                                        <th>Pengirim / Perihal</th>
                                        <th>Layanan</th>
                                        <th class="text-center">Lampiran</th>
                                        <th class="text-center">Status Ambil</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $ctr=0; foreach (($daftar_surat?:[]) as $sm): $ctr++; ?>
                                        <tr>
                                            <td class="text-center text-muted small"><?= ($ctr) ?></td>
                                            <td>
                                                <div class="fw-bold text-dark mb-0"><?= ($sm['nomor_surat']) ?></div>
                                                <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i><?= (date('d M Y', strtotime($sm['tanggal_surat']))) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark mb-0"><?= ($sm['nama_pengirim']) ?></div>
                                                <div class="text-muted small text-truncate" style="max-width: 260px;" title="<?= ($sm['perihal']) ?>"><?= ($sm['perihal']) ?></div>
                                            </td>
                                            <td>
                                                <?php if ($sm['layanan'] == 'opti'): ?>
                                                    <span class="badge bg-danger text-white">OPTI</span>
                                                    <?php else: ?><span class="badge bg-secondary text-white"><?= (strtoupper($sm['layanan'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($sm['file_path']): ?>
                                                    
                                                        <a href="<?= ($BASE) ?>/<?= ($sm['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-danger px-2" title="Buka PDF/Scan">
                                                            <i class="bi bi-file-earmark-pdf"></i>
                                                        </a>
                                                    
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($sm['status_ambil'] == 'sudah'): ?>
                                                    
                                                        <span class="badge bg-success text-white" title="Sudah diproses menjadi Order"><i class="bi bi-check-circle me-1"></i>Sudah</span>
                                                    
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark" title="Menunggu diklaim oleh Admin Order"><i class="bi bi-clock me-1"></i>Belum</span>
                                                    
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-envelope-open fs-2 d-block mb-2"></i>
                            Belum ada surat masuk yang terdaftar hari ini.
                        </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Log form -->
    <div class="col-lg-4">
        <?php echo $this->render('surat-masuk/form.html',NULL,get_defined_vars(),0); ?>
    </div>
</div>
