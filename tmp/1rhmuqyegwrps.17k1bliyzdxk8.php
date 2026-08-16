<!-- Hitung Jumlah PO per Status & Stats Layanan langsung di Template Layer -->
<?php $counts = ['belum_upload' => 0, 'sudah_upload' => 0, 'on_proses' => 0, 'kembali_selesai' => 0];
  $db = \Base::instance()->get('DB');
  if ($db) {
      $rows = $db->exec("SELECT status, COUNT(*) AS count FROM po GROUP BY status");
      foreach ($rows as $r) {
          $counts[$r['status']] = (int)$r['count'];
      }
      
      $selulosaCount = $db->exec("SELECT COUNT(*) AS total FROM order_layanan WHERE jenis_layanan = 'selulosa'")[0]['total'] ?? 0;
      $lingkunganCount = $db->exec("SELECT COUNT(*) AS total FROM order_layanan WHERE jenis_layanan = 'lingkungan'")[0]['total'] ?? 0;
      $orderBaruCount = $db->exec("SELECT COUNT(*) AS total FROM order_layanan WHERE status = 'baru'")[0]['total'] ?? 0;
      $poBerjalanCount = $db->exec("SELECT COUNT(*) AS total FROM po WHERE status != 'kembali_selesai'")[0]['total'] ?? 0;
      
      \Base::instance()->set('selulosa_count', $selulosaCount);
      \Base::instance()->set('lingkungan_count', $lingkunganCount);
      \Base::instance()->set('order_baru_count', $orderBaruCount);
      \Base::instance()->set('po_berjalan_count', $poBerjalanCount);
  }
  \Base::instance()->set('po_counts', $counts); ?>

<!-- Custom Style khusus untuk Responsivitas Tabel PO di Mobile -->
<style>
    @media (max-width: 767.98px) {
        .table-responsive {
            border: none !important;
            overflow-x: visible !important;
        }
        table, thead, tbody, th, td, tr {
            display: block !important;
            width: 100% !important;
        }
        thead {
            display: none !important;
        }
        tr {
            background-color: #ffffff !important;
            border: 1px solid var(--color-border) !important;
            border-radius: 12px !important;
            padding: 16px !important;
            margin-bottom: 16px !important;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02) !important;
        }
        td {
            text-align: left !important;
            padding: 10px 0 !important;
            border: none !important;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.06) !important;
            position: relative;
            padding-left: 45% !important;
            font-size: 0.875rem !important;
            max-width: none !important;
        }
        td:last-child {
            border-bottom: none !important;
            padding-bottom: 0 !important;
        }
        td::before {
            content: attr(data-label);
            position: absolute;
            left: 0;
            width: 40%;
            font-weight: 700;
            font-size: 0.725rem;
            text-transform: uppercase;
            color: var(--color-muted);
            letter-spacing: 0.5px;
            top: 50%;
            transform: translateY(-50%);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        td[data-label="No"] {
            padding-left: 0 !important;
            font-weight: 700;
            color: var(--color-primary) !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
            padding-bottom: 10px !important;
            margin-bottom: 6px !important;
            font-size: 1rem !important;
        }
        td[data-label="No"]::before {
            display: none !important;
        }
        td[data-label="No"]::after {
            content: "Petunjuk Operasional (PO)";
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-text-dark);
            margin-left: 8px;
        }
        td[data-label="Aksi"] {
            padding-top: 14px !important;
            border-bottom: none !important;
            padding-left: 0 !important;
        }
        td[data-label="Aksi"]::before {
            display: none !important;
        }
        td[data-label="Aksi"] a {
            width: 100% !important;
            display: block !important;
            text-align: center !important;
            padding: 10px !important;
        }
    }

    /* Desktop Table Styling Kustom Premium */
    @media (min-width: 768px) {
        .table {
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }
        .table thead th {
            background-color: #f8fafc !important;
            color: var(--color-text-dark) !important;
            font-weight: 600 !important;
            font-size: 0.8rem !important;
            letter-spacing: 0.8px !important;
            text-transform: uppercase !important;
            border-top: 1px solid var(--color-border) !important;
            border-bottom: 2px solid var(--color-primary) !important;
            padding: 16px 20px !important;
        }
        .table tbody td {
            padding: 16px 20px !important;
            border-bottom: 1px solid var(--color-border) !important;
            font-size: 0.9rem !important;
            color: var(--color-text) !important;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(164, 30, 34, 0.02) !important;
        }
    }
</style>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Petunjuk Operasional (PO)</h3>
        <p class="text-muted mb-0">Pantau progres dokumen kerja PO, alur status pekerjaan, dan verifikasi Map Kendali.</p>
    </div>
    <div class="align-self-end align-self-sm-center">
        <a href="<?= ($BASE) ?>/po/ekspor?bulan=<?= ($filter_bulan) ?>&tahun=<?= ($filter_tahun) ?>&status=<?= ($filter_status) ?>&jenis_layanan=<?= ($filter_jenis_layanan) ?>&q=<?= ($search_q) ?>" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Ekspor Rekap PO
        </a>
    </div>
</div>

<!-- Mini Dashboard Utama (OPTI Stats) -->
<div class="row g-3 mb-4" data-aos="fade-up">
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2 fs-3"><i class="bi bi-tag-fill"></i></div>
                <div>
                    <div class="text-muted small">OPTI Selulosa</div>
                    <h4 class="fw-bold mb-0"><?= ($selulosa_count) ?> <span class="text-muted fs-6 font-normal">order</span></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 fs-3"><i class="bi bi-tag-fill"></i></div>
                <div>
                    <div class="text-muted small">OPTI Lingkungan</div>
                    <h4 class="fw-bold mb-0"><?= ($lingkungan_count) ?> <span class="text-muted fs-6 font-normal">order</span></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2 fs-3"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="text-muted small">Order Baru</div>
                    <h4 class="fw-bold mb-0"><?= ($order_baru_count) ?> <span class="text-muted fs-6 font-normal">belum approve</span></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 fs-3"><i class="bi bi-arrow-repeat"></i></div>
                <div>
                    <div class="text-muted small">PO Sedang Berjalan</div>
                    <h4 class="fw-bold mb-0"><?= ($po_berjalan_count) ?> <span class="text-muted fs-6 font-normal">progres</span></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ringkasan Status PO (Dashboard Cards) -->
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3 mb-4" data-aos="fade-up">
    <!-- Card Belum Upload -->
    <div class="col">
        <div class="card h-100 shadow-sm border-start border-secondary border-4">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted" style="font-size: 0.65rem;">1. Belum Upload Dokumen</div>
                        <h3 class="fw-bold mb-0 mt-1 text-dark"><?= ($po_counts['belum_upload']) ?></h3>
                    </div>
                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-file-earmark-lock fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card Sudah Upload -->
    <div class="col">
        <div class="card h-100 shadow-sm border-start border-warning border-4" style="border-left-color: var(--color-accent) !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted" style="font-size: 0.65rem;">2. Sudah Upload Dokumen</div>
                        <h3 class="fw-bold mb-0 mt-1 text-dark"><?= ($po_counts['sudah_upload']) ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; color: var(--color-accent) !important;">
                        <i class="bi bi-file-earmark-arrow-up fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card On Proses -->
    <div class="col">
        <div class="card h-100 shadow-sm border-start border-primary border-4">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted" style="font-size: 0.65rem;">3. On Proses</div>
                        <h3 class="fw-bold mb-0 mt-1 text-dark"><?= ($po_counts['on_proses']) ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-gear-wide-connected fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card PO Kembali / Selesai -->
    <div class="col">
        <div class="card h-100 shadow-sm border-start border-success border-4" style="border-left-color: #166534 !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted" style="font-size: 0.65rem;">4. PO Kembali / Selesai</div>
                        <h3 class="fw-bold mb-0 mt-1 text-dark"><?= ($po_counts['kembali_selesai']) ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; color: #166534 !important;">
                        <i class="bi bi-check2-all fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Card Filter Pencarian -->
<div class="card mb-4 shadow-sm" data-aos="fade-up">
    <div class="card-body bg-light bg-opacity-50 rounded">
        <form method="GET" action="<?= ($BASE) ?>/po" class="row g-3 align-items-end">
            <div class="col-md-2 col-sm-6">
                <label for="bulan" class="form-label small fw-bold text-secondary">Filter Bulan</label>
                <select name="bulan" id="bulan" class="form-select form-select-sm">
                    <option value="">-- Semua Bulan --</option>
                    <?php foreach (($list_bulan?:[]) as $k=>$v): ?>
                        <option value="<?= ($k) ?>" <?= ($filter_bulan == $k ? 'selected' : '') ?>><?= ($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-6">
                <label for="tahun" class="form-label small fw-bold text-secondary">Filter Tahun</label>
                <input type="number" name="tahun" id="tahun" class="form-control form-control-sm" placeholder="Contoh: 2026" value="<?= ($filter_tahun) ?>">
            </div>

            <div class="col-md-2 col-sm-6">
                <label for="jenis_layanan" class="form-label small fw-bold text-secondary">Jenis Layanan</label>
                <select name="jenis_layanan" id="jenis_layanan" class="form-select form-select-sm">
                    <option value="">-- Semua Layanan --</option>
                    <option value="selulosa" <?= ($filter_jenis_layanan == 'selulosa' ? 'selected' : '') ?>>OPTI Selulosa</option>
                    <option value="lingkungan" <?= ($filter_jenis_layanan == 'lingkungan' ? 'selected' : '') ?>>OPTI Lingkungan</option>
                </select>
            </div>

            <div class="col-md-3 col-sm-6">
                <label for="status" class="form-label small fw-bold text-secondary">Filter Status PO</label>
                <select name="status" id="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <?php foreach (($list_status?:[]) as $sk=>$sv): ?>
                        <option value="<?= ($sk) ?>" <?= ($filter_status == $sk ? 'selected' : '') ?>><?= ($sv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="<?= ($BASE) ?>/po" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar PO -->
<div class="card shadow-sm" data-aos="fade-up">
    <div class="card-body p-0">
        <?php if (count($daftar_po) > 0): ?>
            
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;" class="text-center">No</th>
                                <th>Nomor PO</th>
                                <th>Klien & Layanan</th>
                                <th>Judul Kegiatan</th>
                                <th>Tim Kerja</th>
                                <th>Status PO</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ctr=0; foreach (($daftar_po?:[]) as $item): $ctr++; ?>
                                <tr>
                                    <td class="text-center text-muted small" data-label="No"><?= ($ctr) ?></td>
                                    <td data-label="Nomor PO">
                                        <a href="<?= ($BASE) ?>/po/<?= ($item['id']) ?>" class="fw-bold text-decoration-none text-primary">
                                            <?= ($item['nomor_po'])."
" ?>
                                        </a>
                                        <?php if ($item['kontrak_id']): ?>
                                            <div class="small text-success mt-1"><i class="bi bi-file-earmark-check-fill me-1"></i>PKS Terbit</div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Klien & Layanan">
                                        <span class="text-dark fw-bold"><?= ($item['nama_perusahaan']) ?></span>
                                        <div class="mt-1">
                                            <?php if ($item['jenis_layanan'] == 'selulosa'): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20" style="font-size: 0.7rem;"><i class="bi bi-tag-fill me-1"></i>OPTI Selulosa</span>
                                            <?php endif; ?>
                                            <?php if ($item['jenis_layanan'] == 'lingkungan'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20" style="font-size: 0.7rem;"><i class="bi bi-tag-fill me-1"></i>OPTI Lingkungan</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Judul Kegiatan">
                                        <span class="text-secondary small fw-semibold"><?= ($item['judul_kegiatan']) ?></span>
                                    </td>
                                    <td data-label="Tim Kerja">
                                        <span class="small text-muted"><?= ($item['tim_kerja'] ?: 'Belum dibentuk') ?></span>
                                    </td>
                                    <td data-label="Status PO">
                                        <?php if ($item['status'] == 'belum_upload'): ?>
                                            <span class="badge bg-secondary px-2.5 py-1.5 rounded-pill text-uppercase" style="font-size: 0.65rem; font-weight: 600;">Belum Upload</span>
                                        <?php endif; ?>
                                        <?php if ($item['status'] == 'sudah_upload'): ?>
                                            <span class="badge bg-warning text-dark px-2.5 py-1.5 rounded-pill text-uppercase" style="font-size: 0.65rem; font-weight: 600;">Sudah Upload</span>
                                        <?php endif; ?>
                                        <?php if ($item['status'] == 'on_proses'): ?>
                                            <span class="badge bg-primary px-2.5 py-1.5 rounded-pill text-uppercase" style="font-size: 0.65rem; font-weight: 600;">On Proses</span>
                                        <?php endif; ?>
                                        <?php if ($item['status'] == 'kembali_selesai'): ?>
                                            <span class="badge bg-success px-2.5 py-1.5 rounded-pill text-uppercase" style="font-size: 0.65rem; font-weight: 600;">Selesai</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" data-label="Aksi">
                                        <a href="<?= ($BASE) ?>/po/<?= ($item['id']) ?>" class="btn btn-sm btn-outline-primary" title="Lihat Detail & Map Kendali">
                                            <i class="bi bi-search me-1"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-x text-muted display-4 d-block mb-3"></i>
                    <h5 class="text-muted">Tidak ada dokumen PO yang sesuai</h5>
                    <p class="text-secondary small mb-0">Dokumen PO otomatis muncul ketika Order Layanan di-approve oleh Pejabat.</p>
                </div>
            
        <?php endif; ?>
    </div>
</div>
