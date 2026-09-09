<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark">
            <i class="bi bi-wallet2 text-primary me-2"></i>Rekapitulasi Keuangan & Pembayaran
        </h2>
        <p class="text-muted small mb-0">Order dengan status Menunggu Pembayaran / Terbayar Sebagian &mdash; pencatatan multi-termin.</p>
    </div>
    <a href="<?= ($BASE) ?>/pembayaran/tambah" class="btn btn-primary shadow-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-cash-stack"></i> Catat Pembayaran Baru
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card bg-white p-3 p-md-4 rounded-3 border shadow-sm h-100 d-flex justify-content-between align-items-start">
            <div class="w-100 pe-2">
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Tagihan</span>
                <h3 class="fw-bold text-dark mb-1 mt-1">Rp <?= (number_format($total_tagihan, 0, ',', '.')) ?></h3>
                <div class="small text-muted" style="font-size: 0.75rem;">
                    <i class="bi bi-file-earmark-check text-primary"></i> <?= ($total_order ?: 0) ?> Order Menunggu/Sebagian
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
                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold" style="font-size: 0.7rem;"><?= ($persen_realisasi) ?>%</span>
                </div>
                <h3 class="fw-bold text-success mb-1 mt-1">Rp <?= (number_format($total_terbayar, 0, ',', '.')) ?></h3>
                <div class="progress my-2 bg-light" style="height: 6px;">
                    <div class="progress-bar bg-success rounded" style="width: <?= ($persen_realisasi) ?>%;"></div>
                </div>
                <div class="small text-muted d-flex justify-content-between" style="font-size: 0.75rem;">
                    <span><i class="bi bi-check2-circle text-success me-1"></i><?= ($count_lunas) ?> Lunas</span>
                    <span><i class="bi bi-clock-history text-warning me-1"></i><?= ($count_sebagian) ?> Cicilan</span>
                    <span><i class="bi bi-x-circle text-danger me-1"></i><?= ($count_belum) ?> Belum</span>
                </div>
            </div>
            <div class="metric-icon-box bg-success bg-opacity-10 text-success p-3 rounded-3 flex-shrink-0 ms-2">
                <i class="bi bi-cash-coin fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card bg-white p-3 p-md-4 rounded-3 border shadow-sm h-100 d-flex justify-content-between align-items-start">
            <div class="w-100 pe-2">
                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Sisa Piutang</span>
                <h3 class="fw-bold <?= ($sisa_piutang > 0 ? 'text-danger' : 'text-success') ?> mb-1 mt-1">Rp <?= (number_format($sisa_piutang, 0, ',', '.')) ?></h3>
                <div class="small text-muted" style="font-size: 0.75rem;">
                    <?php if ($sisa_piutang > 0): ?><i class="bi bi-exclamation-circle text-danger"></i> Masih ada tagihan berjalan<?php endif; ?>
                    <?php if (!$sisa_piutang): ?><i class="bi bi-check-all text-success"></i> Semua tagihan lunas<?php endif; ?>
                </div>
            </div>
            <div class="metric-icon-box bg-danger bg-opacity-10 text-danger p-3 rounded-3 flex-shrink-0">
                <i class="bi bi-wallet2 fs-4"></i>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- TABEL UTAMA: 1 BARIS PER ORDER -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-pie-chart text-primary me-2"></i>Status Pembayaran per Order</h6>
        <span class="badge bg-light text-muted border"><?= ($total_order ?: 0) ?> Order</span>
    </div>
    <div class="card-body p-3 border-bottom">
        <form method="GET" action="<?= ($BASE) ?>/pembayaran" class="row g-2">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="q" placeholder="Cari nomor order / nama mitra..." value="<?= ($search_q) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="jenis_layanan">
                    <option value="">Semua Divisi</option>
                    <option value="selulosa" <?= ($filter_jenis_layanan == 'selulosa' ? 'selected' : '') ?>>OPTI Selulosa</option>
                    <option value="lingkungan" <?= ($filter_jenis_layanan == 'lingkungan' ? 'selected' : '') ?>>OPTI Lingkungan</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="status_pembayaran">
                    <option value="">Semua Status</option>
                    <option value="menunggu_pembayaran" <?= ($filter_status_pembayaran == 'menunggu_pembayaran' ? 'selected' : '') ?>>Menunggu</option>
                    <option value="terbayar_sebagian" <?= ($filter_status_pembayaran == 'terbayar_sebagian' ? 'selected' : '') ?>>Sebagian</option>
                    <option value="lunas" <?= ($filter_status_pembayaran == 'lunas' ? 'selected' : '') ?>>Lunas</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="tahun">
                    <option value="">Semua Tahun</option>
                    <?php foreach (($daftar_tahun ?: []?:[]) as $thn): ?>
                        <option value="<?= ($thn) ?>" <?= ($filter_tahun == $thn ? 'selected' : '') ?>><?= ($thn) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Terapkan Filter"><i class="bi bi-filter"></i></button>
            </div>
            <?php if ($search_q || $filter_jenis_layanan || $filter_status_pembayaran || $filter_tahun): ?>
                <div class="col-12">
                    <a href="<?= ($BASE) ?>/pembayaran" class="small text-muted"><i class="bi bi-arrow-clockwise me-1"></i>Reset semua filter</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if ($daftar_order && count($daftar_order) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>No. Order</th>
                            <th>Mitra & Kegiatan</th>
                            <th>Divisi</th>
                            <th class="text-end">Nilai Acuan</th>
                            <th style="width:190px;">Terbayar</th>
                            <th class="text-end">Sisa Tagihan</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($daftar_order?:[]) as $o): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?= ($o['nomor_order']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?= ($o['judul_kegiatan']) ?></div>
                                    <div class="text-muted small">
                                        <?php if ($mask_client_name): ?>
                                            <?php $words = explode(' ', $o['nama_perusahaan']);
                                                $masked = array_map(function($w) { return mb_strlen($w) > 1 ? mb_substr($w, 0, 1) . '***' : $w; }, $words); ?>
                                            <?= (implode(' ', $masked))."
" ?>
                                        <?php endif; ?>
                                        <?php if (!$mask_client_name): ?><?= ($o['nama_perusahaan']) ?> (<?= ($o['pt_cv']) ?>)<?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($o['jenis_layanan_opti'] == 'selulosa'): ?><span class="badge badge-pill-primary">Selulosa</span><?php endif; ?>
                                    <?php if ($o['jenis_layanan_opti'] == 'lingkungan'): ?><span class="badge badge-pill-info">Lingkungan</span><?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold">
                                    Rp <?= (number_format($o['biaya_acuan'], 0, ',', '.'))."
" ?>
                                    <?php if (!$o['nominal_penawaran']): ?><div class="text-muted" style="font-size:.68rem;">*estimasi</div><?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-semibold text-success">Rp <?= (number_format($o['total_terbayar'], 0, ',', '.')) ?></span>
                                        <span class="text-muted"><?= ($o['persen_bayar']) ?>%</span>
                                    </div>
                                    <div class="progress" style="height:5px;">
                                        <div class="progress-bar <?= ($o['persen_bayar'] >= 100 ? 'bg-success' : 'bg-warning') ?>" style="width:<?= ($o['persen_bayar']) ?>%;"></div>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:.7rem;"><i class="bi bi-layers me-1"></i><?= ($o['jumlah_termin']) ?> kali bayar</div>
                                </td>
                                <td class="text-end fw-bold <?= ($o['sisa_tagihan'] > 0 ? 'text-danger' : 'text-success') ?>">
                                    Rp <?= (number_format($o['sisa_tagihan'], 0, ',', '.'))."
" ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($o['status_tampil'] == 'lunas'): ?><span class="badge badge-pill-success"><i class="bi bi-check-circle-fill me-1"></i>Lunas</span><?php endif; ?>
                                    <?php if ($o['status_tampil'] == 'sebagian'): ?><span class="badge badge-pill-warning"><i class="bi bi-hourglass-split me-1"></i>Sebagian</span><?php endif; ?>
                                    <?php if ($o['status_tampil'] == 'belum'): ?><span class="badge badge-pill-danger"><i class="bi bi-x-circle me-1"></i>Belum</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Riwayat Pembayaran"
                                                onclick="bukaDetailPembayaran(<?= ($o['id']) ?>, '<?= ($o['nomor_order']) ?>', '<?= ($o['nama_perusahaan']) ?>')">
                                            <i class="bi bi-clock-history"></i>
                                        </button>
                                        <a href="<?= ($BASE) ?>/pembayaran/tambah?order_id=<?= ($o['id']) ?>" class="btn btn-sm btn-success py-1 px-2" title="Catat Pembayaran">
                                            <i class="bi bi-plus-circle"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if (!$daftar_order || count($daftar_order) == 0): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                Tidak ada order yang menunggu pembayaran / masih ada sisa termin.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- HISTORI SEMUA TRANSAKSI (mentah, per pembayaran) -->
<!-- ======================================================== -->
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-receipt text-primary me-2"></i>Histori Buku Kas & Transaksi Pembayaran</h6>
        <span class="badge bg-light text-muted border"><?= (count($daftar_pembayaran ?: [])) ?> Transaksi</span>
    </div>
    <div class="card-body p-0">
        <?php if ($daftar_pembayaran && count($daftar_pembayaran) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>No. Order</th>
                            <th>Mitra</th>
                            <th class="text-center">Termin</th>
                            <th class="text-end">Nominal</th>
                            <th>Keterangan</th>
                            <th class="text-center" style="width:90px;">Bukti</th>
                            <th class="text-center" style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($daftar_pembayaran?:[]) as $bayar): ?>
                            <tr>
                                <td class="small"><?= (date('d/m/Y', strtotime($bayar['tanggal_bayar']))) ?></td>
                                <td class="fw-semibold small"><?= ($bayar['nomor_order']) ?></td>
                                <td class="small text-muted"><?= ($bayar['nama_perusahaan']) ?></td>
                                <td class="text-center"><span class="badge badge-pill-primary">Ke-<?= ($bayar['termin_ke']) ?></span></td>
                                <td class="text-end fw-semibold text-success">Rp <?= (number_format($bayar['jumlah'], 0, ',', '.')) ?></td>
                                <td class="small text-muted"><?= ($bayar['keterangan'] ?: '-') ?></td>
                                <td class="text-center">
                                    <?php if ($bayar['bukti_bayar']): ?>
                                        <a href="<?= ($BASE) ?>/pembayaran/bukti/<?= ($bayar['id']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                                    <?php endif; ?>
                                    <?php if (!$bayar['bukti_bayar']): ?><span class="text-muted small">-</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <form action="<?= ($BASE) ?>/pembayaran/<?= ($bayar['id']) ?>/hapus" method="POST" onsubmit="return confirm('Hapus transaksi ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if (!$daftar_pembayaran || count($daftar_pembayaran) == 0): ?>
            <div class="text-center py-5 text-muted"><i class="bi bi-wallet2 fs-2 d-block mb-2"></i>Belum ada transaksi tercatat.</div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL RIWAYAT PER ORDER -->
<div class="modal fade" id="modalDetailPembayaran" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Pembayaran <span id="detailOrderLabel" class="text-muted fw-normal small d-block mt-1"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light"><tr><th>Tanggal</th><th class="text-center">Termin</th><th class="text-end">Nominal</th><th>Keterangan</th></tr></thead>
                    <tbody id="detailPembayaranBody"></tbody>
                </table>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="fw-bold">Total: <span id="detailPembayaranTotal" class="text-success">Rp 0</span></div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
var semuaPembayaran = <?= ($this->raw($daftar_pembayaran_json)) ?>;

function bukaDetailPembayaran(orderId, nomorOrder, namaMitra) {
    document.getElementById('detailOrderLabel').textContent = nomorOrder + ' — ' + namaMitra;
    var body = document.getElementById('detailPembayaranBody');
    body.innerHTML = '';

    var riwayat = semuaPembayaran.filter(function (p) { return parseInt(p.order_id, 10) === parseInt(orderId, 10); });
    var total = 0;

    if (riwayat.length === 0) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>';
    } else {
        riwayat.forEach(function (p) {
            total += parseFloat(p.jumlah) || 0;
            var tgl = new Date(p.tanggal_bayar);
            var tglLabel = ('0' + tgl.getDate()).slice(-2) + '/' + ('0' + (tgl.getMonth() + 1)).slice(-2) + '/' + tgl.getFullYear();
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + tglLabel + '</td><td class="text-center"><span class="badge badge-pill-primary">Ke-' + p.termin_ke + '</span></td>' +
                '<td class="text-end fw-semibold text-success">Rp ' + (parseFloat(p.jumlah) || 0).toLocaleString('id-ID') + '</td>' +
                '<td class="small text-muted">' + (p.keterangan || '-') + '</td>';
            body.appendChild(tr);
        });
    }
    document.getElementById('detailPembayaranTotal').textContent = 'Rp ' + total.toLocaleString('id-ID');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetailPembayaran')).show();
}
</script>