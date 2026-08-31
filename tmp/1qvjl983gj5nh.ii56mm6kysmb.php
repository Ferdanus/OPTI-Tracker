<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-envelope-open text-primary me-1"></i> Klaim Surat Masuk OPTI</h2>
        <p class="text-muted small mb-0">Daftar surat permintaan resmi dari mitra industri (Layanan OPTI) yang belum diklaim menjadi Order Layanan.</p>
    </div>
</div>

<!-- SEARCH & FILTER -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="<?= ($BASE) ?>/surat-masuk-opti" class="row g-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="q" placeholder="Cari nomor surat, pengirim, atau perihal..." value="<?= ($search) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Cari Surat</button>
                <a href="<?= ($BASE) ?>/surat-masuk-opti" class="btn btn-outline-secondary px-2" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- DATA TABLE -->
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-envelope text-primary me-2"></i>Daftar Surat Masuk Belum Diklaim (Layanan: OPTI)</h6>
        <span class="badge bg-danger text-white"><?= (count($daftar_surat)) ?> Surat</span>
    </div>
    <div class="card-body p-0">
        <?php if (count($daftar_surat) > 0): ?>
            
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th>No. Surat / Tanggal</th>
                                <th>Nama Pengirim / Mitra</th>
                                <th>Perihal Surat</th>
                                <th class="text-center" style="width: 100px;">Lampiran</th>
                                <th class="text-center" style="width: 150px;">Aksi</th>
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
                                    <td class="fw-bold text-dark"><?= ($sm['nama_pengirim']) ?></td>
                                    <td class="text-muted small"><?= ($sm['perihal']) ?></td>
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
                                        <a href="<?= ($BASE) ?>/surat-masuk-opti/<?= ($sm['id']) ?>/proses" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-folder-plus"></i> Klaim &amp; Proses
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-mailbox fs-2 d-block mb-2 text-success"></i>
                    Semua surat masuk OPTI telah selesai diproses / diklaim!
                </div>
            
        <?php endif; ?>
    </div>
</div>
