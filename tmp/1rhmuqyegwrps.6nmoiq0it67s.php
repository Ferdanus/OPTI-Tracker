<!-- Custom Style khusus untuk Responsivitas Tabel Daftar Klien di Mobile -->
<style>
    @media (max-width: 767.98px) {
        /* Menonaktifkan format table bawaan agar bisa dialihkan menjadi block card */
        .table-responsive {
            border: none !important;
            overflow-x: visible !important;
        }
        table, thead, tbody, th, td, tr {
            display: block !important;
            width: 100% !important;
        }
        thead {
            display: none !important; /* Sembunyikan header tabel horizontal */
        }
        tr {
            background-color: #ffffff !important;
            border: 1px solid var(--color-border) !important;
            border-radius: 12px !important;
            padding: 16px !important;
            margin-bottom: 16px !important;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.04) !important;
        }
        td {
            text-align: left !important;
            padding: 10px 0 !important;
            border: none !important;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.06) !important;
            position: relative;
            padding-left: 45% !important; /* Menyediakan ruang untuk label kolom di kiri */
            font-size: 0.875rem !important;
            max-width: none !important; /* Menghapus batasan lebar maksimal desktop di layar mobile */
        }
        td:last-child {
            border-bottom: none !important;
            padding-bottom: 0 !important;
        }
        
        /* Memunculkan Label Kolom di Sebelah Kiri Nilai */
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
        
        /* Kustomisasi Khusus untuk Kolom Nomor Urut sebagai Header Kartu */
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
            content: "Mitra / Klien";
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-text-dark);
            margin-left: 8px;
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

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4" data-aos="fade-up">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-building me-2 text-primary"></i>Daftar Klien</h3>
        <p class="text-muted mb-0">Kelola data mitra/perusahaan pengguna layanan OPTI.</p>
    </div>
    <?php if ($SESSION['role'] == 'admin_order' || $SESSION['role'] == 'superadmin'): ?>
        <div class="align-self-end align-self-sm-center">
            <a href="<?= ($BASE) ?>/klien/tambah" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Tambah Klien
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm" data-aos="fade-up">
    <div class="card-body p-0">
        <?php if (count($daftar_klien) > 0): ?>
            
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;" class="text-center">No</th>
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
                                    <td class="text-center text-muted small" data-label="No"><?= ($ctr) ?></td>
                                    <td data-label="Nama Perusahaan">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-building text-primary me-2 fs-5"></i>
                                            <span class="fw-semibold text-dark"><?= ($klien['nama_perusahaan']) ?></span>
                                        </div>
                                    </td>
                                    <td data-label="PIC">
                                        <?php if ($klien['pic']): ?>
                                            
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-person-circle text-primary me-2"></i>
                                                    <span class="fw-semibold text-dark"><?= ($klien['pic']) ?></span>
                                                </div>
                                            
                                            <?php else: ?><span class="text-muted small"><em>Belum ditentukan</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Kontak">
                                        <div class="d-flex flex-column gap-1 small text-secondary">
                                            <?php if ($klien['telepon']): ?>
                                                <div><i class="bi bi-telephone text-primary me-2"></i><?= ($klien['telepon']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($klien['email']): ?>
                                                <div>
                                                    <i class="bi bi-envelope text-primary me-2"></i>
                                                    <a href="mailto:<?= ($klien['email']) ?>" class="text-decoration-none text-secondary"><?= ($klien['email']) ?></a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!$klien['telepon'] && !$klien['email']): ?>
                                                <span class="text-muted small"><em>Tidak ada kontak</em></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="small text-muted" style="max-width: 250px;" data-label="Alamat">
                                        <?php if ($klien['alamat']): ?>
                                            
                                                <span class="text-secondary"><i class="bi bi-geo-alt text-primary me-1"></i><?= ($klien['alamat']) ?></span>
                                            
                                            <?php else: ?><span class="text-muted small"><em>Belum diisi</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted" data-label="Tgl Dibuat">
                                        <span><i class="bi bi-calendar3 text-primary me-2"></i><?= (date('d/m/Y', strtotime($klien['created_at']))) ?></span>
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
