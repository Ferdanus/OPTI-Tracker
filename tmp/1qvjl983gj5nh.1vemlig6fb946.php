<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-text text-primary me-2"></i>Surat Penawaran Resmi</h2>
        <p class="text-muted small mb-0">Daftar Surat Penawaran Resmi Layanan OPTI BBSPJIS.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalPilihOrder">
        <i class="bi bi-plus-lg"></i> Tambah Surat Penawaran
    </button>
</div>

<!-- Ringkasan Status Alur Pelayanan -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Total Penawaran</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= ($total_surat ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: rgba(136,19,55,.08); color: var(--color-primary);">
                <i class="bi bi-file-earmark-text"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Dalam Negosiasi</div>
                <div class="h4 fw-bold mb-0 text-warning"><?= ($total_nego ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: #fffbeb; color: #b45309;">
                <i class="bi bi-chat-dots"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div>
                <div class="text-muted small fw-semibold mb-1">Disepakati &bull; DEAL</div>
                <div class="h4 fw-bold mb-0 text-success"><?= ($total_deal ?: 0) ?></div>
            </div>
            <div class="metric-icon-box" style="background: #ecfdf5; color: #065f46;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Pencarian & Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="<?= ($BASE) ?>/surat-penawaran" method="GET" class="row g-2 align-items-center">
            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nomor surat / perihal / nama instansi customer..." value="<?= ($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="draft" <?= ($filter_status == 'draft' ? 'selected' : '') ?>>Draft</option>
                    <option value="nego" <?= ($filter_status == 'nego' ? 'selected' : '') ?>>Negosiasi</option>
                    <option value="deal" <?= ($filter_status == 'deal' ? 'selected' : '') ?>>DEAL (Disepakati)</option>
                    <option value="batal" <?= ($filter_status == 'batal' ? 'selected' : '') ?>>Ditolak / Batal</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <?php if ($search || $filter_status): ?>
                    <a href="<?= ($BASE) ?>/surat-penawaran" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center px-2.5" title="Reset filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Data -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table text-primary me-2"></i>Daftar Surat Penawaran Resmi</h6>
            <?php if ($tampilkan_semua): ?>
                <span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size: 0.72rem;">Semua Riwayat Revisi</span>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if (!$tampilkan_semua): ?>
                <a href="<?= ($BASE) ?>/surat-penawaran?all=1" class="text-decoration-none small text-muted" title="Tampilkan seluruh versi riwayat revisi">
                    <i class="bi bi-clock-history me-1"></i>Lihat Semua Riwayat
                </a>
            <?php endif; ?>
            <?php if ($tampilkan_semua): ?>
                <a href="<?= ($BASE) ?>/surat-penawaran" class="text-decoration-none small text-primary fw-semibold" title="Hanya tampilkan dokumen penawaran aktif">
                    <i class="bi bi-check2-circle me-1"></i>Hanya Dokumen Aktif
                </a>
            <?php endif; ?>
            <span class="text-muted small">Total: <strong><?= ($total_surat ?: 0) ?></strong> dokumen</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>No. Surat</th>
                    <th>Customer / Mitra</th>
                    <th>Tanggal</th>
                    <th>Perihal &amp; Layanan</th>
                    <th class="text-center">Status Alur</th>
                    <th class="text-center text-nowrap" style="width: 165px; min-width: 165px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($daftar_penawaran && count($daftar_penawaran) > 0): ?>
                    <?php foreach (($daftar_penawaran?:[]) as $sp): ?>
                        <tr>
                            <td>
                                <div class="fw-bold font-monospace text-dark"><?= ($sp['nomor_surat']) ?></div>
                            </td>
                            <td>
                                <strong class="text-dark"><?= ($sp['display_perusahaan'] ?: $sp['nmcustomer']) ?></strong>
                                <?php if (!empty($sp['sub_info'])): ?>
                                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                                        <?php if (strpos($sp['sub_info'], 'PIC:') === 0): ?>
                                            <i class="bi bi-person me-1"></i>
                                        <?php endif; ?>
                                        <?= ($sp['sub_info'])."
" ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap small text-muted">
                                <?= (date('d M Y', strtotime($sp['tanggal_surat'])))."
" ?>
                            </td>
                            <td>
                                <div class="text-dark small fw-medium text-truncate" style="max-width: 280px;" title="<?= ($sp['perihal']) ?>">
                                    <?= ($sp['perihal'])."
" ?>
                                </div>
                                <?php if ($sp['jenis_layanan'] && !in_array($sp['jenis_layanan'], ['belum_ditentukan', 'opti_belum_ditentukan'])): ?>
                                    <span class="badge <?= (strpos($sp['jenis_layanan'], 'selulosa') !== false ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success') ?> text-uppercase mt-1" style="font-size: 0.65rem;">
                                        <?= (str_replace('_', ' ', $sp['jenis_layanan']))."
" ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($sp['status_respon_klien'] == 'deal' || $sp['status'] == 'disetujui'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i> DEAL / Sepakat
                                    </span>
                                <?php endif; ?>
                                <?php if ($sp['status_respon_klien'] == 'nego'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                        <i class="bi bi-chat-dots me-1"></i> Negosiasi
                                    </span>
                                <?php endif; ?>
                                <?php if (($sp['status'] == 'terkirim' || $sp['status_respon_klien'] == 'terkirim' || $sp['status_respon_klien'] == 'menunggu') && $sp['status_respon_klien'] != 'deal' && $sp['status_respon_klien'] != 'nego' && $sp['status'] != 'disetujui'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        <i class="bi bi-send-check me-1"></i> Terkirim
                                    </span>
                                <?php endif; ?>
                                <?php if ($sp['status_respon_klien'] == 'tolak' || $sp['status_respon_klien'] == 'batal'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        <i class="bi bi-x-circle-fill me-1"></i> Ditolak
                                    </span>
                                <?php endif; ?>
                                <?php if (($sp['status'] == 'draft' || $sp['status_respon_klien'] == 'draft' || !$sp['status'] || $sp['status'] == 'nonaktif') && $sp['status_respon_klien'] != 'deal' && $sp['status_respon_klien'] != 'nego' && $sp['status'] != 'terkirim' && $sp['status_respon_klien'] != 'terkirim' && $sp['status_respon_klien'] != 'tolak' && $sp['status_respon_klien'] != 'batal' && $sp['status'] != 'disetujui'): ?>
                                    <span class="badge bg-light text-secondary border px-2 py-1">
                                        <i class="bi bi-pencil-square me-1"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <?php if ($sp['order_id']): ?>
                                        <a href="<?= ($BASE) ?>/order/<?= ($sp['order_id']) ?>/penawaran/cetak?id=<?= ($sp['id']) ?>&t=<?= (time()) ?>" target="_blank" class="btn btn-outline-danger btn-sm py-1 px-2 d-inline-flex align-items-center gap-1 fw-semibold" title="Cetak Surat Penawaran PDF">
                                            <i class="bi bi-file-earmark-pdf"></i> PDF
                                        </a>
                                        <a href="<?= ($BASE) ?>/order/<?= ($sp['order_id']) ?>" class="btn btn-light border btn-sm py-1 px-2 text-primary" title="Buka Detail Order">
                                            <i class="bi bi-folder2-open"></i>
                                        </a>
                                        <a href="<?= ($BASE) ?>/order/<?= ($sp['order_id']) ?>/penawaran/buat" class="btn btn-light border btn-sm py-1 px-2 text-secondary" title="Edit / Revisi Surat Penawaran">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$sp['order_id']): ?>
                                        <a href="<?= ($BASE) ?>/surat-penawaran/<?= ($sp['id']) ?>/edit" class="btn btn-light border btn-sm py-1 px-2 text-secondary" title="Edit Surat Penawaran">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-light border btn-sm py-1 px-2 text-danger" data-bs-toggle="modal" data-bs-target="#modalHapusSP<?= ($sp['id']) ?>" title="Hapus Surat Penawaran">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>

                                <!-- Modal Konfirmasi Hapus Surat Penawaran -->
                                <div class="modal fade" id="modalHapusSP<?= ($sp['id']) ?>" tabindex="-1" aria-labelledby="modalHapusSPLabel<?= ($sp['id']) ?>" aria-hidden="true" style="white-space: normal;">
                                    <div class="modal-dialog modal-dialog-centered" style="white-space: normal;">
                                        <div class="modal-content bg-white border-0 shadow rounded-3 text-start text-wrap" style="white-space: normal;">
                                            <div class="modal-header border-bottom py-3 px-4 bg-white">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background-color: #ffe4e6; color: #dc2626;">
                                                        <i class="bi bi-trash3-fill fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalHapusSPLabel<?= ($sp['id']) ?>">Konfirmasi Hapus Surat Penawaran</h6>
                                                        <small class="text-muted" style="font-size: 0.75rem;">Tindakan ini tidak dapat dibatalkan</small>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                            </div>

                                            <form action="<?= ($BASE) ?>/surat-penawaran/<?= ($sp['id']) ?>/hapus" method="POST">
                                                 <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

                                                <div class="modal-body p-4" style="white-space: normal;">
                                                    <p class="text-dark small mb-3">
                                                        Apakah Anda yakin ingin menghapus dokumen surat penawaran berikut?
                                                    </p>

                                                    <!-- Box Rincian Surat Penawaran -->
                                                    <div class="border rounded-2 p-3 bg-light mb-3" style="white-space: normal;">
                                                        <div class="mb-2">
                                                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Nomor Surat:</span>
                                                            <strong class="font-monospace fs-6 text-primary"><?= ($sp['nomor_surat']) ?></strong>
                                                        </div>
                                                        <div class="mb-2">
                                                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Nama Instansi / Perusahaan:</span>
                                                            <span class="fw-semibold text-dark text-break"><?= ($sp['perusahaan']) ?></span>
                                                        </div>
                                                        <div class="mb-0">
                                                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Perihal:</span>
                                                            <span class="text-secondary small text-break"><?= ($sp['perihal']) ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="p-3 mb-0 rounded-2 small d-flex align-items-start gap-2" style="font-size: 0.8rem; background-color: #fff1f2; color: #881337; border: 1px solid #fecdd3; white-space: normal;">
                                                        <i class="bi bi-exclamation-triangle-fill fs-6 flex-shrink-0 mt-0.5" style="color: #881337;"></i>
                                                        <div style="white-space: normal; line-height: 1.45; word-break: break-word;">Dokumen surat penawaran ini akan dihapus dan status order terkait akan kembali ke tahap sebelum penawaran terbit.</div>
                                                    </div>
                                                </div>

                                                <div class="modal-footer border-top py-3 px-4 bg-light d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold" data-bs-dismiss="modal">
                                                        Batal
                                                    </button>
                                                    <button type="submit" class="btn btn-danger btn-sm px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-trash3-fill"></i> Ya, Hapus Penawaran
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!$daftar_penawaran || count($daftar_penawaran) == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted opacity-50"></i>
                            Belum ada data surat penawaran resmi.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Pilih Order Layanan untuk Penerbitan Surat Penawaran -->
<div class="modal fade" id="modalPilihOrder" tabindex="-1" aria-labelledby="modalPilihOrderLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white border-0 shadow rounded-3">
            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: #eff6ff; color: var(--color-primary);">
                        <i class="bi bi-file-earmark-plus-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalPilihOrderLabel">Pilih Order Layanan untuk Diterbitkan Penawaran</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Surat Penawaran Resmi diterbitkan berdasarkan Order Layanan yang telah siap</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-4">
                <?php if ($daftar_order_siap && count($daftar_order_siap) > 0): ?>
                    <p class="small text-muted mb-3">
                        Pilih order di bawah ini yang telah disetujui kaji kelayakannya dan memiliki estimasi biaya/proposal teknis:
                    </p>
                    <div class="table-responsive border rounded-2">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>No. Order</th>
                                    <th>Perusahaan / Mitra</th>
                                    <th>Layanan</th>
                                    <th class="text-end">Estimasi Biaya</th>
                                    <th class="text-center" style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($daftar_order_siap?:[]) as $ord): ?>
                                    <tr>
                                        <td>
                                            <strong class="font-monospace text-primary">#<?= ($ord['nomor_order']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark"><?= ($ord['nama_perusahaan'] ?: ($ord['nmcustomer'] ?: '-')) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?= ($ord['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success') ?> text-uppercase" style="font-size: 0.65rem;">
                                                OPTI <?= ($ord['jenis_layanan_opti'])."
" ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold font-monospace text-dark">
                                            Rp <?= (number_format($ord['estimasi_biaya'] ?: 0, 0, ',', '.'))."
" ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= ($BASE) ?>/order/<?= ($ord['id']) ?>/penawaran/buat" class="btn btn-primary btn-sm py-1.5 px-3 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.78rem;">
                                                <i class="bi bi-file-earmark-plus"></i> Terbitkan
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!$daftar_order_siap || count($daftar_order_siap) == 0): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-2 text-muted opacity-50 d-block mb-2"></i>
                        <h6 class="fw-bold text-dark mb-1">Belum Ada Order yang Menunggu Penawaran</h6>
                        <p class="text-muted small mb-3">
                            Semua order aktif saat ini sudah diterbitkan penawarannya, atau masih dalam tahap kaji kelayakan teknis oleh Ketua Tim OPTI.
                        </p>
                        <a href="<?= ($BASE) ?>/order" class="btn btn-outline-primary btn-sm px-3 fw-semibold">
                            <i class="bi bi-list-ul me-1"></i> Buka Daftar Order Layanan
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer border-top py-2 px-4 bg-light d-flex justify-content-between align-items-center">
                <a href="<?= ($BASE) ?>/order" class="btn btn-link btn-sm text-decoration-none text-muted p-0">
                    <i class="bi bi-folder2-open me-1"></i> Lihat Semua Order
                </a>
                <button type="button" class="btn btn-secondary btn-sm px-3 py-1.5 fw-semibold" data-bs-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($GET['buka_modal'] == '1'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modalEl = document.getElementById('modalPilihOrder');
            if (modalEl) {
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    </script>
<?php endif; ?>
