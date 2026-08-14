<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-building me-2 text-primary"></i>Daftar Klien</h3>
        <p class="text-muted mb-0">Kelola data mitra/perusahaan pengguna layanan OPTI.</p>
    </div>
    <div>
        <a href="<?= ($BASE) ?>/klien/tambah" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Tambah Klien
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (count($daftar_klien) > 0): ?>
            
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>Nama Perusahaan</th>
                                <th>PIC</th>
                                <th>Kontak</th>
                                <th>Alamat</th>
                                <th>Tgl Dibuat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ctr=0; foreach (($daftar_klien?:[]) as $klien): $ctr++; ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= ($ctr) ?></td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= ($klien['nama_perusahaan']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($klien['pic']): ?>
                                            <i class="bi bi-person me-1 text-muted"></i><?= ($klien['pic']) ?>
                                            <?php else: ?><span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?php if ($klien['telepon']): ?>
                                                <div><i class="bi bi-telephone me-1 text-muted"></i><?= ($klien['telepon']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($klien['email']): ?>
                                                <div><i class="bi bi-envelope me-1 text-muted"></i><?= ($klien['email']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!$klien['telepon'] && !$klien['email']): ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="small text-muted" style="max-width: 250px;">
                                        <?= ($klien['alamat'] ?: '-')."
" ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= (date('d/m/Y', strtotime($klien['created_at'])))."
" ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-building text-muted display-4 d-block mb-3"></i>
                    <h5 class="text-muted">Belum ada data klien</h5>
                    <p class="text-secondary small mb-3">Silakan tambahkan data klien/mitra pertama Anda.</p>
                    <a href="<?= ($BASE) ?>/klien/tambah" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Klien Sekarang
                    </a>
                </div>
            
        <?php endif; ?>
    </div>
</div>
