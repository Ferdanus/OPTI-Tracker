<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-inbox me-2 text-primary"></i>Daftar Order Layanan</h3>
        <p class="text-muted mb-0">Kelola permintaan layanan masuk dari klien dan lakukan persetujuan (approval) untuk menerbitkan PO.</p>
    </div>
    <div>
        <a href="<?= ($BASE) ?>/order/tambah" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Tambah Order Layanan
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (count($daftar_order) > 0): ?>
            
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>Tanggal</th>
                                <th>Klien</th>
                                <th>Judul Kegiatan</th>
                                <th>Status Order</th>
                                <th>Dokumen PO Terkait</th>
                                <th class="text-center" style="width: 200px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ctr=0; foreach (($daftar_order?:[]) as $order): $ctr++; ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= ($ctr) ?></td>
                                    <td class="small">
                                        <i class="bi bi-calendar-event me-1 text-muted"></i>
                                        <?= (date('d/m/Y', strtotime($order['tanggal_masuk'])))."
" ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= ($order['nama_perusahaan']) ?></div>
                                        <?php if ($order['pic']): ?>
                                            <div class="small text-muted"><i class="bi bi-person me-1"></i><?= ($order['pic']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark"><?= ($order['judul_kegiatan']) ?></div>
                                        <?php if ($order['deskripsi']): ?>
                                            <div class="small text-muted text-truncate" style="max-width: 300px;"><?= ($order['deskripsi']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['status'] == 'baru'): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Baru</span>
                                        <?php endif; ?>
                                        <?php if ($order['status'] == 'disetujui'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Disetujui</span>
                                        <?php endif; ?>
                                        <?php if ($order['status'] == 'ditolak'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['po_id']): ?>
                                            
                                                <a href="<?= ($BASE) ?>/po/<?= ($order['po_id']) ?>" class="btn btn-sm btn-outline-primary py-0">
                                                    <i class="bi bi-file-earmark-text me-1"></i><?= ($order['nomor_po'])."
" ?>
                                                </a>
                                            
                                            <?php else: ?>
                                                <span class="text-muted small"><em>Belum ada PO</em></span>
                                            
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($order['status'] == 'baru'): ?>
                                            
                                                <div class="d-flex justify-content-center gap-1">
                                                    <!-- Form Approve -->
                                                    <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/approve" method="POST" onsubmit="return confirm('Setujui Order ini dan terbitkan dokumen PO otomatis?');">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Setujui dan Auto-Generate PO">
                                                            <i class="bi bi-check-lg me-1"></i>Approve
                                                        </button>
                                                    </form>
                                                    <!-- Form Tolak -->
                                                    <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tolak" method="POST" onsubmit="return confirm('Tolak Order ini?');">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Tolak Order">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border">Selesai Diproses</span>
                                            
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted display-4 d-block mb-3"></i>
                    <h5 class="text-muted">Belum ada Order Layanan</h5>
                    <p class="text-secondary small mb-3">Tambahkan order layanan masuk untuk memulai alur pekerjaan.</p>
                    <a href="<?= ($BASE) ?>/order/tambah" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-lg me-1"></i>Buat Order Layanan
                    </a>
                </div>
            
        <?php endif; ?>
    </div>
</div>
