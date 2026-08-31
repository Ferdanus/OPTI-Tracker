<div class="container-fluid px-0">
    <style>
        /* Modern BBSPJIS System Design for Order Detail */
        .order-hero-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .stepper-progress-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            padding: 1.25rem 0.5rem 0.5rem 0.5rem;
        }
        .stepper-progress-container::before {
            content: '';
            position: absolute;
            top: 32px;
            left: 40px;
            right: 40px;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
        }
        .step-checkpoint {
            position: relative;
            z-index: 2;
            background: #ffffff;
            padding: 0 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .step-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 700;
            transition: all 0.2s ease;
        }
        .step-circle.done {
            background: #059669;
            color: #ffffff;
            box-shadow: 0 0 0 4px #ecfdf5;
        }
        .step-circle.current {
            background: var(--color-primary, #881337);
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(136, 19, 55, 0.15);
        }
        .step-circle.waiting {
            background: #f8fafc;
            color: #94a3b8;
            border: 2px solid #cbd5e1;
        }
        .step-text {
            font-size: 0.775rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
        }
        .step-text.current { color: var(--color-primary, #881337); font-weight: 700; }
        .step-text.done { color: #059669; }

        /* Choice Cards for Disposisi */
        .choice-card-opti {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #ffffff;
            height: 100%;
        }
        .choice-card-opti:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.06);
        }
        .choice-card-opti.active-selulosa {
            border-color: var(--color-primary, #881337) !important;
            background: #fdf2f8 !important;
            box-shadow: 0 0 0 3px rgba(136, 19, 55, 0.12) !important;
        }
        .choice-card-opti.active-lingkungan {
            border-color: #059669 !important;
            background: #f0fdf4 !important;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12) !important;
        }
        .choice-card-locked {
            opacity: 0.55;
            background: #f8fafc !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed !important;
            user-select: none;
            box-shadow: none !important;
        }

        .icon-tile {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }

        /* Bubble Scope Tags */
        .bubble-scope-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }
        .bubble-scope-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.725rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all 0.15s ease;
        }
        .bubble-tag-blue {
            background-color: #fdf2f8;
            color: var(--color-primary, #881337);
            border: 1px solid #fbcfe8;
        }
        .bubble-tag-blue:hover {
            background-color: #fce7f3;
        }
        .bubble-tag-green {
            background-color: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .bubble-tag-green:hover {
            background-color: #dcfce7;
        }

        .system-section-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            overflow: hidden;
        }
        .system-section-header {
            background: #fafafa;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>

    <!-- Header & Info Bar -->
    <div class="order-hero-card">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order" class="text-decoration-none text-muted"><i class="bi bi-folder2-open me-1"></i>Order Layanan</a></li>
                        <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">#<?= ($order['nomor_order']) ?></li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h4 class="fw-bold text-dark m-0 font-display"><?= ($order['judul_kegiatan']) ?></h4>
                    <?php if ($order['jenis_layanan_opti'] == 'selulosa'): ?>
                        <span class="badge bg-primary text-uppercase">
                            <i class="bi bi-file-earmark-text me-1"></i> OPTI Selulosa
                        </span>
                    <?php endif; ?>
                    <?php if ($order['jenis_layanan_opti'] == 'lingkungan'): ?>
                        <span class="badge bg-success text-uppercase">
                            <i class="bi bi-tree me-1"></i> OPTI Lingkungan
                        </span>
                    <?php endif; ?>
                    <?php if (!$order['jenis_layanan_opti'] || $order['jenis_layanan_opti'] == 'belum_ditentukan'): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                            <i class="bi bi-hourglass-split me-1"></i> Layanan Belum Ditentukan
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'permintaan_masuk'): ?>
                        <span class="badge bg-warning text-dark border border-warning">
                            <i class="bi bi-inbox-fill me-1"></i> Menunggu Disposisi
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'baru'): ?>
                        <span class="badge bg-info text-dark">
                            <i class="bi bi-file-earmark-plus me-1"></i> Order Aktif
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'disetujui'): ?>
                        <span class="badge bg-primary">
                            <i class="bi bi-gear-wide-connected me-1"></i> PO Diterbitkan
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'selesai'): ?>
                        <span class="badge bg-success">
                            <i class="bi bi-patch-check-fill me-1"></i> Selesai
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'ditolak'): ?>
                        <span class="badge bg-danger">
                            <i class="bi bi-x-circle-fill me-1"></i> Ditolak
                        </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-3 text-muted small mt-1">
                    <div>No. Order: <strong class="text-primary font-monospace"><?= ($order['nomor_order']) ?></strong></div>
                    <div>&bull;</div>
                    <div>Tgl Masuk: <i class="bi bi-calendar3 me-1"></i><?= (date('d M Y', strtotime($order['tanggal_masuk']))) ?></div>
                    <div>&bull;</div>
                    <div>Standar SPM: <span class="text-dark fw-semibold"><?= ($order['spm_layanan']) ?></span></div>
                </div>
            </div>

            <!-- Action Buttons Sesuai Tema Sistem -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= ($BASE) ?>/order" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                </a>

                <?php if ($order['status'] == 'baru' || $order['status'] == 'permintaan_masuk'): ?>
                    <?php if ($order['jenis_layanan_opti'] == 'selulosa'): ?>
                        <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/rancop-selulosa" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm">
                            <i class="bi bi-diagram-3-fill me-1"></i> <?= ($order['status_rancop'] == 'deal' ? 'Kelola Rancop (Deal)' : 'Rancangan Percobaan (Rancop)')."
" ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($order['jenis_layanan_opti'] == 'lingkungan'): ?>
                        <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/biaya-lingkungan" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
                            <i class="bi bi-calculator me-1"></i> <?= (!empty($kalkulasi_lingkungan) ? 'Kalkulasi Uji Lab' : 'Hitung Biaya Pengujian')."
" ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Dropdown Menu Aksi Sederhana -->
                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle px-3 fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical me-1"></i> Aksi Cepat
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 small">
                        <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.68rem;">Tahapan Saat Ini</h6></li>
                        <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan"><i class="bi bi-clipboard-check text-primary"></i> Kaji Ulang Kelayakan ISO</a></li>
                        <?php if ($order['jenis_layanan_opti'] == 'selulosa'): ?>
                            <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/rancop-selulosa"><i class="bi bi-diagram-3-fill text-primary"></i> Skenario Rancop & Riset</a></li>
                        <?php endif; ?>
                        <?php if ($order['jenis_layanan_opti'] == 'lingkungan'): ?>
                            <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/biaya-lingkungan"><i class="bi bi-calculator text-success"></i> Hitung Tarif Uji SNI</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/buat"><i class="bi bi-file-earmark-text text-primary"></i> <?= ($penawaran ? 'Edit Surat Penawaran' : 'Buat Surat Penawaran') ?></a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 6-Tahap Progress Stepper Terintegrasi -->
        <div class="mt-4 pt-3 border-top">
            <div class="stepper-progress-container">
                <!-- Step 1: Surat Masuk -->
                <div class="step-checkpoint">
                    <div class="step-circle done"><i class="bi bi-check-lg"></i></div>
                    <span class="step-text done">1. Surat Masuk</span>
                </div>
                <!-- Step 2: Disposisi -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($order['status'] == 'permintaan_masuk' ? 'current' : 'done') ?>">
                        <?php if ($order['status'] == 'permintaan_masuk'): ?><i class="bi bi-send-fill"></i><?php endif; ?>
                        <?php if ($order['status'] != 'permintaan_masuk'): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                    </div>
                    <span class="step-text <?= ($order['status'] == 'permintaan_masuk' ? 'current' : 'done') ?>">2. Disposisi</span>
                </div>
                <!-- Step 3: Kaji Ulang & Biaya / Rancop -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($order['status'] == 'baru' && !$penawaran ? 'current' : ($order['estimasi_biaya'] > 0 ? 'done' : 'waiting')) ?>">
                        <?php if ($order['estimasi_biaya'] > 0): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                        <?php if (!($order['estimasi_biaya'] > 0)): ?>3<?php endif; ?>
                    </div>
                    <span class="step-text <?= ($order['status'] == 'baru' && !$penawaran ? 'current' : ($order['estimasi_biaya'] > 0 ? 'done' : '')) ?>">
                        <?= ($order['jenis_layanan_opti'] == 'selulosa' ? '3. Rancop & Riset' : '3. Kaji Ulang Tarif')."
" ?>
                    </span>
                </div>
                <!-- Step 4: Penawaran Resmi -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($penawaran ? ($penawaran['status_respon_klien'] == 'deal' ? 'done' : 'current') : 'waiting') ?>">
                        <?php if ($penawaran && $penawaran['status_respon_klien'] == 'deal'): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                        <?php if ($penawaran && $penawaran['status_respon_klien'] != 'deal'): ?><i class="bi bi-send-fill"></i><?php endif; ?>
                        <?php if (!$penawaran): ?>4<?php endif; ?>
                    </div>
                    <span class="step-text <?= ($penawaran ? ($penawaran['status_respon_klien'] == 'deal' ? 'done' : 'current') : '') ?>">4. Penawaran</span>
                </div>
                <!-- Step 5: Petunjuk Operasional (PO) -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($order['po_id'] ? 'done' : ($penawaran && $penawaran['status_respon_klien'] == 'deal' ? 'current' : 'waiting')) ?>">
                        <?php if ($order['po_id']): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                        <?php if (!$order['po_id']): ?>5<?php endif; ?>
                    </div>
                    <span class="step-text <?= ($order['po_id'] ? 'done' : ($penawaran && $penawaran['status_respon_klien'] == 'deal' ? 'current' : '')) ?>">5. Petunjuk Operasional</span>
                </div>
                <!-- Step 6: BAST & Penyerahan -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($bast && $bast['status'] == 'diserahkan' ? 'done' : ($order['po_id'] ? 'current' : 'waiting')) ?>">
                        <?php if ($bast && $bast['status'] == 'diserahkan'): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                        <?php if (!($bast && $bast['status'] == 'diserahkan')): ?>6<?php endif; ?>
                    </div>
                    <span class="step-text <?= ($bast && $bast['status'] == 'diserahkan' ? 'done' : '') ?>">6. BAST &amp; Laporan</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert / Notifikasi -->
    <?php if ($SESSION['flash_success']): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= ($this->raw($SESSION['flash_success']))."
" ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($SESSION['flash_warning']): ?>
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i> <?= ($this->raw($SESSION['flash_warning']))."
" ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($SESSION['flash_error']): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= ($this->raw($SESSION['flash_error']))."
" ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Layout 2 Kolom -->
    <div class="row g-4">
        <!-- Kolom Kiri: Dokumen Surat & Data Pelanggan (4 Kolom) -->
        <div class="col-lg-4">

            <!-- Card Asal Surat Permohonan & Klien -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-envelope-paper-fill text-primary"></i> Asal Surat Permohonan
                    </h6>
                    <button type="button" class="btn btn-outline-primary btn-xs py-0 px-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalEditKlien">
                        <i class="bi bi-pencil me-1"></i> Edit Data
                    </button>
                </div>
                <div class="p-3">
                    <?php if ($surat_masuk): ?>
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Arsip Surat Masuk
                                </span>
                                <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= (date('d M Y', strtotime($surat_masuk['tanggal_surat']))) ?></small>
                            </div>
                            <div class="mb-1 small">
                                <span class="text-muted d-block" style="font-size:0.75rem;">Nomor Surat Resmi:</span>
                                <strong class="text-dark font-monospace"><?= ($surat_masuk['nomor_surat']) ?></strong>
                            </div>
                            <div class="mb-2 small">
                                <span class="text-muted d-block" style="font-size:0.75rem;">Perihal:</span>
                                <span class="text-secondary fw-semibold"><?= ($surat_masuk['perihal']) ?></span>
                            </div>
                            <?php if ($surat_masuk['file_path']): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary w-100 py-1 fw-semibold d-flex align-items-center justify-content-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPreviewSuratMasuk">
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i> Pratinjau Berkas Surat PDF
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <h6 class="fw-bold text-dark mb-1"><?= ($order['nama_perusahaan']) ?></h6>
                    <span class="badge bg-light text-secondary border mb-3"><?= ($order['pt_cv'] ?: 'Perusahaan') ?></span>

                    <div class="mb-2 small">
                        <span class="text-muted d-block" style="font-size:0.75rem;">Nama Kontak / PIC:</span>
                        <strong class="text-dark"><?= ($order['pic'] ?: '-') ?></strong>
                    </div>
                    <div class="mb-2 small">
                        <span class="text-muted d-block" style="font-size:0.75rem;">Telepon / WhatsApp:</span>
                        <span class="text-dark"><?= ($order['telepon'] ?: '-') ?></span>
                    </div>
                    <div class="mb-2 small">
                        <span class="text-muted d-block" style="font-size:0.75rem;">Email:</span>
                        <span class="text-dark"><?= ($order['email'] ?: '-') ?></span>
                    </div>
                    <div class="mb-0 small">
                        <span class="text-muted d-block" style="font-size:0.75rem;">Alamat:</span>
                        <span class="text-secondary"><?= ($order['alamat'] ?: '-') ?></span>
                    </div>
                </div>
            </div>

            <!-- Card Spesifikasi Sampel -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam-fill text-primary"></i> Data Sampel & Lokasi Lab
                    </h6>
                </div>
                <div class="p-3">
                    <div class="mb-2 small">
                        <span class="text-muted d-block" style="font-size:0.75rem;">Jenis Sampel / Bahan:</span>
                        <strong class="text-dark"><?= ($order['jenis_sampel'] ?: 'Belum diisi') ?></strong>
                    </div>
                    <div class="mb-2 small">
                        <span class="text-muted d-block" style="font-size:0.75rem;">Jumlah / Volume:</span>
                        <span class="text-dark"><?= ($order['volume_berat'] ?: '1 paket kegiatan') ?></span>
                    </div>
                    <div class="mb-0 small">
                        <span class="text-muted d-block" style="font-size:0.75rem;">Lokasi Pelaksanaan:</span>
                        <span class="badge <?= ($order['lokasi_pelaksanaan'] == 'internal' ? 'bg-info-subtle text-info-emphasis' : 'bg-warning-subtle text-warning-emphasis') ?> text-capitalize mb-1">
                            <?= ($order['lokasi_pelaksanaan'])."
" ?>
                        </span>
                        <small class="text-muted d-block"><?= ($order['lab_internal'] ?: $order['lokasi_lapangan'] ?: 'Laboratorium Pengujian BBSPJIS') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Rangkaian Form Disposisi & SOP (8 Kolom) -->
        <div class="col-lg-8">

            <!-- ======================================================== -->
            <!-- FORM DISPOSISI UNIT OPTI (HERO SECTION)                  -->
            <!-- ======================================================== -->
            <div class="system-section-card" id="cardDisposisi">
                <div class="system-section-header bg-white">
                    <div>
                        <h6 class="fw-bold text-dark m-0 d-flex align-items-center gap-2 font-display">
                            <i class="bi bi-send-check-fill text-warning fs-5"></i> Disposisi & Penentuan Divisi Layanan OPTI
                        </h6>
                        <small class="text-muted">Tentukan unit laboratorium pelaksana untuk menindaklanjuti permohonan ini.</small>
                    </div>
                    <?php if ($order['status'] == 'permintaan_masuk'): ?>
                        <span class="badge bg-warning text-dark fw-bold">
                            <i class="bi bi-hourglass-split me-1"></i> Perlu Disposisi
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'permintaan_masuk'): ?>
                        <span class="badge bg-success text-white fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Sudah Didisposisikan
                        </span>
                    <?php endif; ?>
                </div>

                <div class="p-4">
                    <?php if ($order['status'] == 'permintaan_masuk'): ?>
                        <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/disposisi" method="POST">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <label class="form-label fw-bold text-dark small m-0">
                                        Pilih Divisi Laboratorium Pelaksana <span class="text-danger">*</span>
                                    </label>
                                    <span class="text-muted small">
                                        <i class="bi bi-info-circle me-1 text-primary"></i> Klik salah satu kartu untuk menentukan divisi tujuan
                                    </span>
                                </div>

                                <div class="row g-3">
                                    <!-- Hidden input that carries selected value -->
                                    <input type="hidden" name="jenis_layanan_opti" id="inputJenisLayananOpti" value="<?= ($order['jenis_layanan_opti'] ?: 'selulosa') ?>">

                                    <!-- Option OPTI Selulosa -->
                                    <div class="col-md-6">
                                        <div class="choice-card-opti <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'active-selulosa' : '') ?>" id="cardSelulosa" onclick="selectDivisiOpti('selulosa')">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="icon-tile bg-primary-subtle text-primary">
                                                    <i class="bi bi-file-earmark-text-fill"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <h6 class="fw-bold text-primary m-0 font-display">OPTI Selulosa</h6>
                                                        <input class="form-check-input" type="radio" name="radio_opti" id="radioSelulosa" value="selulosa" <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'checked' : '') ?> onchange="selectDivisiOpti('selulosa')">
                                                    </div>
                                                    <div class="small text-muted mb-2">
                                                        Uji Pulp, Kertas & Biomaterial
                                                    </div>
                                                    <div class="bubble-scope-group">
                                                        <span class="bubble-scope-tag bubble-tag-blue"><i class="bi bi-droplet-half"></i> Pemasakan Pulp</span>
                                                        <span class="bubble-scope-tag bubble-tag-blue"><i class="bi bi-stars"></i> Pemutihan</span>
                                                        <span class="bubble-scope-tag bubble-tag-blue"><i class="bi bi-layers-fill"></i> Derivat Selulosa</span>
                                                        <span class="bubble-scope-tag bubble-tag-blue"><i class="bi bi-file-earmark"></i> Aditif Kertas</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Option OPTI Lingkungan -->
                                    <div class="col-md-6">
                                        <div class="choice-card-opti <?= ($order['jenis_layanan_opti'] == 'lingkungan' ? 'active-lingkungan' : '') ?>" id="cardLingkungan" onclick="selectDivisiOpti('lingkungan')">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="icon-tile bg-success-subtle text-success">
                                                    <i class="bi bi-tree-fill"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <h6 class="fw-bold text-success m-0 font-display">OPTI Lingkungan</h6>
                                                        <input class="form-check-input" type="radio" name="radio_opti" id="radioLingkungan" value="lingkungan" <?= ($order['jenis_layanan_opti'] == 'lingkungan' ? 'checked' : '') ?> onchange="selectDivisiOpti('lingkungan')">
                                                    </div>
                                                    <div class="small text-muted mb-2">
                                                        Uji Lingkungan, Toksikologi & Validasi
                                                    </div>
                                                    <div class="bubble-scope-group">
                                                        <span class="bubble-scope-tag bubble-tag-green"><i class="bi bi-recycle"></i> Biodegradasi</span>
                                                        <span class="bubble-scope-tag bubble-tag-green"><i class="bi bi-virus"></i> Toksikologi</span>
                                                        <span class="bubble-scope-tag bubble-tag-green"><i class="bi bi-patch-check"></i> LPV</span>
                                                        <span class="bubble-scope-tag bubble-tag-green"><i class="bi bi-water"></i> Kajian Limbah</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark small mb-1">
                                    Catatan / Instruksi Disposisi (Opsional)
                                </label>
                                <textarea class="form-control form-control-sm" name="catatan_disposisi" rows="3" placeholder="Tuliskan catatan arahan pengujian atau parameter yang diminta pelanggan..."></textarea>
                            </div>

                            <div class="d-flex justify-content-end pt-3 border-top">
                                <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill"></i> Simpan & Teruskan Disposisi
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($order['status'] != 'permintaan_masuk'): ?>
                        <div class="p-3 rounded border <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle border-primary-subtle' : 'bg-success-subtle border-success-subtle') ?>">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-tile bg-white shadow-sm <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'text-primary' : 'text-success') ?>">
                                        <i class="bi <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bi-file-earmark-text-fill' : 'bi-tree-fill') ?>"></i>
                                    </div>
                                    <div>
                                        <span class="badge <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary' : 'bg-success') ?> text-uppercase mb-1">
                                            OPTI <?= ($order['jenis_layanan_opti'])."
" ?>
                                        </span>
                                        <h6 class="fw-bold text-dark m-0">
                                            Didisposisikan ke Unit Layanan <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'OPTI Selulosa' : 'OPTI Lingkungan')."
" ?>
                                        </h6>
                                        <small class="text-secondary">Permohonan siap untuk kaji ulang kelayakan teknis dan penetapan tarif.</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-white btn-sm border fw-semibold shadow-sm text-secondary" data-bs-toggle="modal" data-bs-target="#modalDisposisi">
                                    <i class="bi bi-pencil-square me-1"></i> Ubah Disposisi
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 1. Kaji Ulang Kelayakan ISO                              -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-clipboard-check-fill text-primary"></i> 1. Kaji Ulang Kelayakan Teknis (ISO 17025)
                    </h6>
                    <?php if ($tinjauan): ?>
                        <span class="badge <?= ($tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'bg-success' : 'bg-danger') ?>">
                            <?= ($tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'Dapat Dilaksanakan' : 'Tidak Dapat Dilaksanakan')."
" ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!$tinjauan): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                            <i class="bi bi-hourglass me-1"></i> Belum Ditinjau
                        </span>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <?php if ($tinjauan): ?>
                        <div class="row g-3 small">
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light">
                                    <strong class="<?= ($tinjauan['sdm_tersedia'] ? 'text-success' : 'text-danger') ?>">
                                        <i class="bi <?= ($tinjauan['sdm_tersedia'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill') ?> me-1"></i> Personil / SDM Analis
                                    </strong>
                                    <span class="text-muted d-block mt-1"><?= ($tinjauan['sdm_catatan'] ?: 'Tersedia') ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light">
                                    <strong class="<?= ($tinjauan['peralatan_tersedia'] ? 'text-success' : 'text-danger') ?>">
                                        <i class="bi <?= ($tinjauan['peralatan_tersedia'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill') ?> me-1"></i> Peralatan & Instrumen
                                    </strong>
                                    <span class="text-muted d-block mt-1"><?= ($tinjauan['peralatan_catatan'] ?: 'Siap digunakan') ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light">
                                    <strong class="<?= ($tinjauan['bahan_tersedia'] ? 'text-success' : 'text-danger') ?>">
                                        <i class="bi <?= ($tinjauan['bahan_tersedia'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill') ?> me-1"></i> Bahan Kimia & Reagen
                                    </strong>
                                    <span class="text-muted d-block mt-1"><?= ($tinjauan['bahan_catatan'] ?: 'Tersedia') ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light">
                                    <strong class="<?= ($tinjauan['metode_tersedia'] ? 'text-success' : 'text-danger') ?>">
                                        <i class="bi <?= ($tinjauan['metode_tersedia'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill') ?> me-1"></i> Kesiapan Metode Uji
                                    </strong>
                                    <span class="text-muted d-block mt-1"><?= ($tinjauan['metode_catatan'] ?: 'Tervalidasi') ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top small text-muted">
                            <div>Ditinjau oleh: <strong><?= ($tinjauan['peninjau_nama']) ?></strong> &bull; <?= (date('d M Y H:i', strtotime($tinjauan['tanggal_tinjauan']))) ?></div>
                            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan" class="btn btn-outline-primary btn-sm fw-semibold">
                                <i class="bi bi-pencil me-1"></i> Edit Kaji Ulang
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (!$tinjauan): ?>
                        <div class="text-center py-3">
                            <p class="text-muted small mb-3">Kaji ulang kelayakan fasilitas, instrumen, dan reagen belum dilakukan.</p>
                            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan" class="btn btn-primary btn-sm px-3 fw-semibold">
                                <i class="bi bi-check2-circle me-1"></i> Lakukan Kaji Ulang Sekarang
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 2. Anggaran & Biaya Layanan / Rancop                     -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bi-diagram-3-fill text-primary' : 'bi-cash-coin text-success') ?> fs-5"></i> 
                        <?= ($order['jenis_layanan_opti'] == 'selulosa' ? '2. Rancangan Percobaan (Rancop) & Anggaran Riset' : '2. Penentuan Biaya & Draf Proposal')."
" ?>
                    </h6>
                    <?php if ($order['jenis_layanan_opti'] == 'selulosa'): ?>
                        <?php if ($order['status_rancop'] == 'deal'): ?>
                            <span class="badge bg-success text-white"><i class="bi bi-check-circle-fill me-1"></i> Deal Disepakati</span>
                        <?php endif; ?>
                        <?php if ($order['status_rancop'] == 'diskusi'): ?>
                            <span class="badge bg-primary-subtle text-primary border"><i class="bi bi-chat-dots-fill me-1"></i> Sedang Diskusi / Nego</span>
                        <?php endif; ?>
                        <?php if ($order['status_rancop'] == 'batal'): ?>
                            <span class="badge bg-danger text-white"><i class="bi bi-x-circle-fill me-1"></i> Batal / Tidak Lanjut</span>
                        <?php endif; ?>
                        <?php if (!$order['status_rancop'] || $order['status_rancop'] == 'draft'): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Draf Rancop</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($order['jenis_layanan_opti'] != 'selulosa'): ?>
                        <span class="badge bg-light text-secondary border">Dasar Surat Penawaran</span>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <!-- Kasus Selulosa: Rancop & Multi-Tahap Riset -->
                    <?php if ($order['jenis_layanan_opti'] == 'selulosa'): ?>
                        <?php if ($order['estimasi_biaya'] > 0 || $proposal): ?>
                            <div class="row g-3 mb-3 small">
                                <div class="col-md-6">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Peneliti Utama (PI Utama):</span>
                                    <strong class="text-dark"><?= ($proposal['pic_nama'] ?: ($order['pic_proposal_nama'] ?: 'Tim Peneliti Selulosa')) ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Perkiraan Durasi Kegiatan:</span>
                                    <strong class="text-dark"><?= ($proposal['durasi_kegiatan'] ?: '3 bulan') ?></strong>
                                </div>
                                <?php if ($order['log_diskusi_klien']): ?>
                                    <div class="col-12">
                                        <span class="text-muted d-block" style="font-size: 0.75rem;">Catatan Hasil Diskusi / Meeting Klien:</span>
                                        <div class="p-2 bg-light rounded text-secondary border small font-monospace" style="white-space: pre-line;"><?= ($order['log_diskusi_klien']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center p-3 bg-primary-subtle rounded border border-primary-subtle mb-3">
                                <div>
                                    <span class="text-muted small d-block">Total Anggaran Riset Deal:</span>
                                    <h4 class="fw-bold text-primary m-0 font-display">Rp <?= (number_format($order['estimasi_biaya'] ?: ($proposal['estimasi_total_biaya'] ?: 0), 0, ',', '.')) ?></h4>
                                </div>
                                <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/rancop-selulosa" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm">
                                    <i class="bi bi-pencil-square me-1"></i> Kelola Skenario Rancop
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if (!($order['estimasi_biaya'] > 0) && !$proposal): ?>
                            <div class="text-center py-3">
                                <i class="bi bi-diagram-3 text-muted fs-2 d-block mb-2 opacity-50"></i>
                                <h6 class="fw-bold text-dark mb-1">Rancangan Percobaan (Rancop) Belum Disusun</h6>
                                <p class="text-muted small mb-3">Susun skenario tahapan pengujian awal (pre-trial), optimasi proses, dan perkiraan RAB riset.</p>
                                <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/rancop-selulosa" class="btn btn-primary btn-sm px-4 fw-semibold shadow-sm">
                                    <i class="bi bi-plus-circle me-1"></i> Buat Rancangan Percobaan (Rancop)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Kasus Lingkungan -->
                    <?php if ($order['jenis_layanan_opti'] == 'lingkungan'): ?>
                        <?php if (!empty($kalkulasi_lingkungan)): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead class="bg-light text-secondary small text-uppercase">
                                        <tr>
                                            <th>Sub Layanan</th>
                                            <th>Metode Pengujian</th>
                                            <th class="text-end">Tarif Satuan</th>
                                            <th class="text-center">Jumlah</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php foreach (($kalkulasi_lingkungan?:[]) as $item): ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark border"><?= (str_replace('_', ' ', $item['sub_layanan'])) ?></span></td>
                                                <td>
                                                    <strong><?= ($item['nama_pengujian']) ?></strong>
                                                    <?php if ($item['standar_rujukan']): ?>
                                                        <small class="text-muted d-block">Standar: <?= ($item['standar_rujukan']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">Rp <?= (number_format($item['tarif_per_sampel'], 0, ',', '.')) ?></td>
                                                <td class="text-center fw-bold"><?= ($item['jumlah_sampel']) ?></td>
                                                <td class="text-end fw-bold">Rp <?= (number_format($item['total_biaya_item'], 0, ',', '.')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center p-3 bg-success-subtle rounded border border-success-subtle mb-3">
                                <div>
                                    <?php if ($order['diskon_penawaran'] > 0): ?>
                                        <span class="text-danger small d-block">Diskon: - Rp <?= (number_format($order['diskon_penawaran'], 0, ',', '.')) ?></span>
                                    <?php endif; ?>
                                    <span class="text-muted small d-block">Total Netto Penawaran:</span>
                                    <h4 class="fw-bold text-success m-0 font-display">Rp <?= (number_format($order['estimasi_biaya'], 0, ',', '.')) ?></h4>
                                </div>
                                <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/biaya-lingkungan" class="btn btn-outline-success btn-sm fw-semibold">
                                    <i class="bi bi-pencil me-1"></i> Edit Rincian Uji
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if (empty($kalkulasi_lingkungan)): ?>
                            <div class="text-center py-3">
                                <p class="text-muted small mb-3">Parameter pengujian laboratorium dan kalkulasi tarif belum dihitung.</p>
                                <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/biaya-lingkungan" class="btn btn-success btn-sm px-3 fw-semibold">
                                    <i class="bi bi-calculator me-1"></i> Hitung Biaya Pengujian Sekarang
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 3. Surat Penawaran Resmi (Tema Merah Maroon BBSPJIS)      -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-send-fill text-primary"></i> 3. Surat Penawaran Resmi ke Klien
                    </h6>
                    <?php if ($penawaran): ?>
                        <span class="badge <?= ($penawaran['status_respon_klien'] == 'deal' ? 'bg-success' : ($penawaran['status_respon_klien'] == 'terkirim' ? 'bg-primary' : 'bg-warning text-dark')) ?>">
                            <?= (strtoupper($penawaran['status_respon_klien']))."
" ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!$penawaran): ?>
                        <span class="badge bg-light text-secondary border">Belum Dibuat</span>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <?php if ($penawaran): ?>
                        <div class="row g-3 mb-3 small">
                            <div class="col-md-6">
                                <span class="text-muted d-block">Nomor Surat Penawaran:</span>
                                <strong class="text-primary font-monospace"><?= ($penawaran['nomor_surat']) ?></strong>
                                <small class="text-muted d-block">Tanggal: <?= (date('d M Y', strtotime($penawaran['tanggal_surat']))) ?></small>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">Total Nilai Penawaran:</span>
                                <h4 class="fw-bold text-dark m-0 font-display">Rp <?= (number_format($penawaran['nominal_penawaran'], 0, ',', '.')) ?></h4>
                            </div>
                        </div>

                        <!-- Update Respon Klien -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/status" method="POST" class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-dark">Status Respon Klien:</label>
                                    <select name="status_respon_klien" class="form-select form-select-sm">
                                        <option value="draft" <?= ($penawaran['status_respon_klien'] == 'draft' ? 'selected' : '') ?>>Draft Internal</option>
                                        <option value="terkirim" <?= ($penawaran['status_respon_klien'] == 'terkirim' ? 'selected' : '') ?>>Terkirim ke Klien</option>
                                        <option value="nego" <?= ($penawaran['status_respon_klien'] == 'nego' ? 'selected' : '') ?>>Negosiasi Tarif</option>
                                        <option value="deal" <?= ($penawaran['status_respon_klien'] == 'deal' ? 'selected' : '') ?>>DEAL (Disepakati)</option>
                                        <option value="batal" <?= ($penawaran['status_respon_klien'] == 'batal' ? 'selected' : '') ?>>Batal / Ditolak Klien</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-dark">Catatan Negosiasi:</label>
                                    <input type="text" name="catatan_nego" class="form-control form-control-sm" placeholder="Catatan hasil diskusi..." value="<?= ($penawaran['catatan_nego'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-dark btn-sm w-100 fw-semibold">
                                        <i class="bi bi-arrow-repeat me-1"></i> Update Status
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/cetak" target="_blank" class="btn btn-outline-primary btn-sm fw-semibold">
                                <i class="bi bi-printer me-1"></i> Preview PDF Penawaran
                            </a>
                            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/buat" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-pencil me-1"></i> Edit Penawaran
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (!$penawaran): ?>
                        <div class="text-center py-3">
                            <p class="text-muted small mb-3">Surat penawaran harga resmi belum diterbitkan.</p>
                            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/buat" class="btn btn-primary btn-sm px-3 fw-semibold">
                                <i class="bi bi-send-plus me-1"></i> Buat Surat Penawaran Sekarang
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAHAP 5: PETUNJUK OPERASIONAL (PO)                       -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-speedometer2 text-warning"></i> 5. Petunjuk Operasional (PO) &amp; Pelaksanaan Teknis
                    </h6>
                    <?php if ($order['po_id']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">PO Aktif: <?= ($order['nomor_po']) ?></span>
                    <?php endif; ?>
                    <?php if (!$order['po_id']): ?>
                        <span class="badge bg-light text-secondary border">Belum Diterbitkan</span>
                    <?php endif; ?>
                </div>
                <div class="system-section-body">
                    <?php if ($order['po_id']): ?>
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-7">
                                    <div class="fw-bold text-dark mb-1">
                                        <i class="bi bi-file-earmark-check text-primary me-1"></i> Nomor PO: <span class="font-monospace text-primary"><?= ($order['nomor_po']) ?></span>
                                    </div>
                                    <div class="small text-secondary mb-1">
                                        Biaya Operasional: <strong>Rp <?= (number_format($order['biaya_po'] ?: ($order['estimasi_biaya'] ?: 0), 0, ',', '.')) ?></strong>
                                    </div>
                                    <div class="small text-muted">
                                        Status Pelaksanaan: <span class="badge bg-primary-subtle text-primary">Dalam Pengerjaan Lab</span>
                                    </div>
                                </div>
                                <div class="col-md-5 text-md-end">
                                    <a href="<?= ($BASE) ?>/po/<?= ($order['po_id']) ?>" class="btn btn-dark btn-sm px-3 fw-semibold shadow-sm">
                                        <i class="bi bi-speedometer2 me-1"></i> Buka Dashboard Monitoring PO
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!$order['po_id']): ?>
                        <?php if ($penawaran && $penawaran['status_respon_klien'] == 'deal'): ?>
                            <div class="alert alert-success d-flex align-items-center justify-content-between p-3 border-0 shadow-sm mb-3">
                                <div>
                                    <div class="fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i> Penawaran Telah Disepakati (DEAL)</div>
                                    <div class="small text-dark">Petunjuk Operasional (PO) siap diterbitkan untuk penugasan tim teknis di laboratorium.</div>
                                </div>
                                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#modalApprove">
                                    <i class="bi bi-patch-check-fill me-1"></i> Terbitkan PO Sekarang
                                </button>
                            </div>
                        <?php endif; ?>
                        <?php if (!$penawaran || $penawaran['status_respon_klien'] != 'deal'): ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-hourglass-split fs-4 d-block mb-1 text-secondary opacity-50"></i>
                                <p class="small mb-0">Petunjuk Operasional (PO) dapat diterbitkan setelah Surat Penawaran berstatus <strong>DEAL</strong> (disepakati pelanggan).</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAHAP 6: KONTRAK / PERJANJIAN KERJA SAMA (PKS)           -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-ruled text-primary"></i> 6. Dokumen Kontrak / Perjanjian Kerja Sama (PKS)
                    </h6>
                    <span class="badge bg-light text-secondary border">Fleksibel / Sesuai Kebutuhan</span>
                </div>
                <div class="system-section-body">
                    <?php if ($kontrakPks): ?>
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="small text-muted">Nomor Kontrak / PKS:</div>
                                    <div class="fw-bold text-dark"><?= ($kontrakPks['nomor_kontrak']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-muted">Nilai Kontrak:</div>
                                    <div class="fw-bold text-success">Rp <?= (number_format($kontrakPks['nilai_kontrak'] ?: 0, 0, ',', '.')) ?></div>
                                </div>
                            </div>
                        </div>
                        <a href="<?= ($BASE) ?>/kontrak/<?= ($kontrakPks['id']) ?>" class="btn btn-outline-primary btn-sm fw-semibold">
                            <i class="bi bi-journal-text me-1"></i> Lihat Rincian Kontrak PKS
                        </a>
                    <?php endif; ?>
                    <?php if (!$kontrakPks): ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                            <div>
                                <p class="small text-muted mb-0">Dokumen Kontrak / PKS disusun apabila proyek riset memerlukan perjanjian legal formal berjangka panjang.</p>
                            </div>
                            <a href="<?= ($BASE) ?>/kontrak/tambah?order_id=<?= ($order['id']) ?>" class="btn btn-outline-secondary btn-sm px-3 fw-semibold text-nowrap">
                                <i class="bi bi-plus-circle me-1"></i> Input Dokumen Kontrak PKS
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAHAP 7: KEUANGAN, BILLING SIMPONI & PEMBAYARAN PNBP    -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-cash-stack text-success"></i> 7. Penagihan Billing SIMPONI &amp; Pembayaran PNBP
                    </h6>
                    <?php if (($rekapKeuangan['total_bayar'] ?: 0) >= ($order['estimasi_biaya'] ?: 0) && ($order['estimasi_biaya'] ?: 0) > 0): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Lunas</span>
                    <?php endif; ?>
                    <?php if (($rekapKeuangan['total_bayar'] ?: 0) < ($order['estimasi_biaya'] ?: 0)): ?>
                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle">Belum Lunas</span>
                    <?php endif; ?>
                </div>
                <div class="system-section-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded border">
                                <div class="small text-muted">Total Nilai Tagihan:</div>
                                <div class="fw-bold text-dark fs-6">Rp <?= (number_format($order['estimasi_biaya'] ?: ($order['biaya_po'] ?: 0), 0, ',', '.')) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded border">
                                <div class="small text-muted">Total Terbayar:</div>
                                <div class="fw-bold text-success fs-6">Rp <?= (number_format($rekapKeuangan['total_bayar'] ?: 0, 0, ',', '.')) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded border">
                                <div class="small text-muted">Sisa Tagihan:</div>
                                <div class="fw-bold text-danger fs-6">
                                    Rp <?= (number_format(max(0, ($order['estimasi_biaya'] ?: ($order['biaya_po'] ?: 0)) - ($rekapKeuangan['total_bayar'] ?: 0)), 0, ',', '.'))."
" ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <div class="small text-muted">
                            Pencatatan setoran PNBP terintegrasi dengan kode billing SIMPONI Kementerian Keuangan &amp; Bank Persepsi.
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= ($BASE) ?>/pembayaran/tambah?order_id=<?= ($order['id']) ?>" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
                                <i class="bi bi-cash-coin me-1"></i> Catat Pembayaran Setoran
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAHAP 8: BERITA ACARA SERAH TERIMA (BAST) & LAPORAN      -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-journal-check text-primary"></i> 8. Berita Acara Serah Terima (BAST) &amp; Laporan Akhir
                    </h6>
                    <?php if ($bast): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">BAST Terbit</span>
                    <?php endif; ?>
                    <?php if (!$bast): ?>
                        <span class="badge bg-light text-secondary border">Tahap Penutupan</span>
                    <?php endif; ?>
                </div>
                <div class="system-section-body">
                    <?php if ($bast): ?>
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="small text-muted">Nomor BAST:</div>
                                    <div class="fw-bold text-dark"><?= ($bast['nomor_bast']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-muted">Tanggal Serah Terima:</div>
                                    <div class="fw-bold text-dark"><?= (date('d M Y', strtotime($bast['tanggal_bast']))) ?></div>
                                </div>
                            </div>
                        </div>
                        <a href="<?= ($BASE) ?>/bast/<?= ($bast['id']) ?>/pdf" target="_blank" class="btn btn-outline-primary btn-sm fw-semibold">
                            <i class="bi bi-printer me-1"></i> Cetak Dokumen BAST
                        </a>
                    <?php endif; ?>
                    <?php if (!$bast): ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                            <div>
                                <p class="small text-muted mb-0">BAST diterbitkan setelah pengujian selesai untuk serah terima Laporan Hasil Pengujian (LHP) / Sertifikat resmi kepada pelanggan.</p>
                            </div>
                            <a href="<?= ($BASE) ?>/bast/tambah?order_id=<?= ($order['id']) ?>" class="btn btn-outline-primary btn-sm px-3 fw-semibold text-nowrap">
                                <i class="bi bi-file-earmark-check me-1"></i> Terbitkan BAST
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL DIALOG                                             -->
<!-- ======================================================== -->

<!-- Modal Disposisi (Popup untuk Edit Disposisi) -->
<div class="modal fade" id="modalDisposisi" tabindex="-1" aria-labelledby="modalDisposisiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/disposisi" method="POST">
                <div class="modal-header border-bottom py-3">
                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalDisposisiLabel">
                        <i class="bi bi-send-check text-warning fs-5"></i> Ubah Disposisi Permohonan
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Pilih Divisi OPTI Pelaksana <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2">
                            <div class="form-check p-3 border rounded flex-fill bg-light">
                                <input class="form-check-input" type="radio" name="jenis_layanan_opti" id="modalOptiSelulosa" value="selulosa" <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'checked' : '') ?>>
                                <label class="form-check-label fw-semibold text-dark small ms-1" for="modalOptiSelulosa">
                                    <i class="bi bi-file-earmark-text text-primary me-1"></i> OPTI Selulosa
                                    <small class="d-block text-muted mt-1">Fokus: Pulp, Kertas & Biomaterial</small>
                                </label>
                            </div>
                            <div class="form-check p-3 border rounded flex-fill bg-light">
                                <input class="form-check-input" type="radio" name="jenis_layanan_opti" id="modalOptiLingkungan" value="lingkungan" <?= ($order['jenis_layanan_opti'] == 'lingkungan' ? 'checked' : '') ?>>
                                <label class="form-check-label fw-semibold text-dark small ms-1" for="modalOptiLingkungan">
                                    <i class="bi bi-tree text-success me-1"></i> OPTI Lingkungan
                                    <small class="d-block text-muted mt-1">Fokus: Biodegradasi, Toksikologi & LPV</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Catatan / Arahan Disposisi</label>
                        <textarea class="form-control form-control-sm" name="catatan_disposisi" rows="3" placeholder="Tambahkan instruksi arahan teknis..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i> Simpan Perubahan Disposisi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Data Klien -->
<div class="modal fade" id="modalEditKlien" tabindex="-1" aria-labelledby="modalEditKlienLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/klien/update" method="POST">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-bottom">
                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalEditKlienLabel">
                        <i class="bi bi-pencil-square text-primary"></i> Perbarui Data Pelanggan
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Bentuk Usaha:</label>
                            <select name="pt_cv" class="form-select form-select-sm">
                                <option value="PT" <?= ($order['pt_cv'] == 'PT' ? 'selected' : '') ?>>PT</option>
                                <option value="CV" <?= ($order['pt_cv'] == 'CV' ? 'selected' : '') ?>>CV</option>
                                <option value="UD" <?= ($order['pt_cv'] == 'UD' ? 'selected' : '') ?>>UD</option>
                                <option value="Yayasan" <?= ($order['pt_cv'] == 'Yayasan' ? 'selected' : '') ?>>Yayasan</option>
                                <option value="Universitas" <?= ($order['pt_cv'] == 'Universitas' ? 'selected' : '') ?>>Universitas / Instansi</option>
                                <option value="Lainnya" <?= ($order['pt_cv'] == 'Lainnya' ? 'selected' : '') ?>>Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small fw-bold">Nama Perusahaan / Instansi <span class="text-danger">*</span></label>
                            <input type="text" name="nmcustomer" class="form-control form-control-sm fw-semibold" value="<?= ($order['nama_perusahaan']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Person In Charge (PIC):</label>
                            <input type="text" name="pic" class="form-control form-control-sm" value="<?= ($order['pic'] ?: '') ?>" placeholder="Nama PIC Pemohon">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kontak Telepon / WhatsApp:</label>
                            <input type="text" name="telepon" class="form-control form-control-sm" value="<?= ($order['telepon'] ?: '') ?>" placeholder="Contoh: 0812-3456-7890">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Resmi:</label>
                        <input type="email" name="email" class="form-control form-control-sm" value="<?= ($order['email'] ?: '') ?>" placeholder="email@perusahaan.com">
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold">Alamat Lengkap:</label>
                        <textarea name="alamat" class="form-control form-control-sm" rows="2" placeholder="Jalan, Kawasan Industri, Kota, Provinsi"><?= ($order['alamat'] ?: '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Approve Order / Terbitkan PO -->
<div class="modal fade" id="modalApprove" tabindex="-1" aria-labelledby="modalApproveLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/approve" method="POST">
                <div class="modal-header bg-warning-subtle border-0 py-3">
                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalApproveLabel">
                        <i class="bi bi-patch-check-fill text-warning fs-5"></i> Setujui & Terbitkan Petunjuk Operasional (PO)
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-secondary small mb-3">
                        Dengan menyetujui order ini, Petunjuk Operasional (PO) akan resmi diterbitkan di laboratorium pengujian dan masuk ke dalam Dashboard PO.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nomor PO (Opsional / Otomatis):</label>
                        <input type="text" name="nomor_po" class="form-control form-control-sm" placeholder="Biarkan kosong untuk nomor otomatis">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-dark">Biaya Operasional (Rp):</label>
                        <input type="number" name="biaya" class="form-control form-control-sm fw-bold" value="<?= ($order['estimasi_biaya'] ?: 0) ?>">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm px-4 fw-bold text-dark shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi & Terbitkan PO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Pratinjau Surat Masuk Resmi -->
<?php if ($surat_masuk && $surat_masuk['file_path']): ?>
    <div class="modal fade" id="modalPreviewSuratMasuk" tabindex="-1" aria-labelledby="modalPreviewSuratMasukLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content bg-white border-0 shadow-lg rounded-3">
                
                <div class="modal-header border-bottom py-2 px-4 bg-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text-fill text-danger fs-4"></i>
                        <div>
                            <h6 class="modal-title fw-bold text-dark m-0" id="modalPreviewSuratMasukLabel">
                                Berkas Surat Masuk: <?= ($surat_masuk['nomor_surat'])."
" ?>
                            </h6>
                            <small class="text-muted"><?= ($order['nama_perusahaan']) ?> &bull; Tanggal Surat: <?= (date('d M Y', strtotime($surat_masuk['tanggal_surat']))) ?></small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= ($BASE) ?>/surat-masuk/<?= ($surat_masuk['id']) ?>/pdf" target="_blank" class="btn btn-outline-secondary btn-sm px-2 py-1 fw-semibold">
                            <i class="bi bi-download me-1"></i> Unduh PDF
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                </div>

                <div class="modal-body p-4 bg-light" style="min-height: 75vh;">
                    <div class="official-sheet mx-auto bg-white p-4 p-md-5 rounded shadow-sm border" style="max-width: 820px; font-family: 'Times New Roman', Times, serif; color: #111; line-height: 1.55;">
                        
                        <!-- KOP SURAT PENGIRIM -->
                        <div class="text-center pb-2">
                            <h4 class="fw-bold mb-0 text-uppercase letter-spacing-1" style="font-size: 1.3rem;"><?= (($surat_masuk['pt_cv'] ? $surat_masuk['pt_cv'] . ' ' : '') . $surat_masuk['pengirim']) ?></h4>
                            <div class="fw-bold small text-muted text-uppercase my-1" style="font-size: 0.75rem; letter-spacing: 1px;">PRODUSEN & JASA INDUSTRI TEKNOLOGI</div>
                            <div class="small text-secondary" style="font-size: 0.825rem;"><?= ($surat_masuk['alamat_pengirim'] ?: 'Kawasan Industri Terpadu, Indonesia') ?> | Telp: <?= ($surat_masuk['no_telp_pengirim'] ?: '-') ?></div>
                        </div>
                        
                        <!-- GARIS KOP GANDA -->
                        <div style="border-bottom: 2.5px solid #000; margin-bottom: 2px;"></div>
                        <div style="border-bottom: 1px solid #000; margin-bottom: 24px;"></div>

                        <!-- METADATA SURAT -->
                        <div class="d-flex justify-content-between align-items-start mb-4" style="font-size: 0.95rem;">
                            <div>
                                <table class="table table-borderless table-sm p-0 m-0" style="width: auto;">
                                    <tr>
                                        <td style="width: 80px; padding: 2px 0;">Nomor</td>
                                        <td style="width: 15px; padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;"><strong><?= ($surat_masuk['nomor_surat']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 2px 0;">Lampiran</td>
                                        <td style="padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;">1 (satu) berkas spesifikasi teknis</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 2px 0;">Hal</td>
                                        <td style="padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;"><strong><?= ($surat_masuk['perihal']) ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="text-end fw-normal" style="white-space: nowrap;">
                                Bandung, <?= (date('d F Y', strtotime($surat_masuk['tanggal_surat'])))."
" ?>
                            </div>
                        </div>

                        <!-- TUJUAN -->
                        <div class="mb-4" style="font-size: 0.95rem;">
                            <div>Kepada Yth.</div>
                            <strong>Kepala Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa (BBSPJIS)</strong><br>
                            Kementerian Perindustrian Republik Indonesia<br>
                            Jl. Raya Dayeuhkolot No. 132, Bandung, Jawa Barat 40258
                        </div>

                        <!-- ISI SURAT -->
                        <div class="mb-4" style="font-size: 0.95rem; text-align: justify;">
                            <p>Dengan hormat,</p>
                            <p>Sehubungan dengan rencana pengujian mutu dan optimalisasi proses industri, bersama ini kami mengajukan permohonan kerjasama pelaksanaan Layanan Optimalisasi Teknologi Industri (OPTI) dengan rincian sebagai berikut:</p>
                            
                            <strong class="d-block mb-1">1. Data Pemohon / Instansi:</strong>
                            <table class="table table-borderless table-sm ms-2 mb-3" style="font-size: 0.925rem; width: 98%;">
                                <tr>
                                    <td style="width: 150px; padding: 3px 0;">a. Nama Perusahaan</td>
                                    <td style="width: 15px; padding: 3px 0;">:</td>
                                    <td style="padding: 3px 0;"><strong><?= ($order['nama_perusahaan']) ?></strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 3px 0;">b. Alamat</td>
                                    <td style="padding: 3px 0;">:</td>
                                    <td style="padding: 3px 0;"><?= ($surat_masuk['alamat_pengirim'] ?: $order['alamat'] ?: '-') ?></td>
                                </tr>
                                <tr>
                                    <td style="padding: 3px 0;">c. Narahubung / PIC</td>
                                    <td style="padding: 3px 0;">:</td>
                                    <td style="padding: 3px 0;"><?= ($surat_masuk['pic_pengirim'] ?: $order['pic'] ?: 'Penanggung Jawab Teknis') ?></td>
                                </tr>
                                <tr>
                                    <td style="padding: 3px 0;">d. Telepon & Email</td>
                                    <td style="padding: 3px 0;">:</td>
                                    <td style="padding: 3px 0;"><?= ($order['telepon'] ?: '-') ?> / <?= ($order['email'] ?: '-') ?></td>
                                </tr>
                            </table>

                            <strong class="d-block mb-1">2. Rincian Kebutuhan Layanan OPTI:</strong>
                            <table class="table table-borderless table-sm ms-2 mb-3" style="font-size: 0.925rem; width: 98%;">
                                <tr>
                                    <td style="width: 150px; padding: 3px 0;">a. Judul Kegiatan</td>
                                    <td style="width: 15px; padding: 3px 0;">:</td>
                                    <td style="padding: 3px 0;"><strong><?= ($surat_masuk['perihal']) ?></strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 3px 0;">b. Layanan Dimohon</td>
                                    <td style="padding: 3px 0;">:</td>
                                    <td style="padding: 3px 0;">Layanan Optimalisasi Teknologi Industri (OPTI)</td>
                                </tr>
                                <tr>
                                    <td style="padding: 3px 0;">c. Ruang Lingkup</td>
                                    <td style="padding: 3px 0;">:</td>
                                    <td style="padding: 3px 0;">Kajian Teknis, Karakterisasi Laboratorium & Penerbitan Sertifikat / Laporan Hasil Pengujian Resmi Balai</td>
                                </tr>
                            </table>

                            <p>Demikian surat permohonan ini kami sampaikan. Kami berharap dapat segera menerima Tinjauan Kelayakan Permintaan serta Surat Penawaran Biaya resmi dari BBSPJIS. Atas perhatian dan kerjasama Bapak/Ibu, kami ucapkan terima kasih.</p>
                        </div>

                        <!-- TANDA TANGAN & STEMPEL -->
                        <div class="row mt-5">
                            <div class="col-6"></div>
                            <div class="col-6 text-center" style="font-size: 0.95rem;">
                                <div>Hormat kami,</div>
                                <strong class="d-block mb-2"><?= ($order['nama_perusahaan']) ?></strong>
                                <div class="border d-inline-block px-3 py-1 my-2 text-muted small" style="border-style: dashed !important; font-size: 0.75rem;">
                                    [ TTD & STEMPEL RESMI ]
                                </div>
                                <div class="mt-3">
                                    <u class="fw-bold d-block"><?= ($surat_masuk['pic_pengirim'] ?: $order['pic'] ?: 'Pimpinan Perusahaan') ?></u>
                                    <span class="small text-muted">Direktur</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-top py-2 px-4 bg-white d-flex justify-content-between align-items-center">
                    <span class="small text-muted font-monospace">
                        <i class="bi bi-shield-check text-success me-1"></i> Berkas Terverifikasi Arsip Sekretariat BBSPJIS
                    </span>
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">
                        Tutup Pratinjau
                    </button>
                </div>

            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function selectDivisiOpti(tipe) {
    var inputHidden = document.getElementById('inputJenisLayananOpti');
    var radioSel = document.getElementById('radioSelulosa');
    var radioLing = document.getElementById('radioLingkungan');
    var cardSel = document.getElementById('cardSelulosa');
    var cardLing = document.getElementById('cardLingkungan');

    if (inputHidden) inputHidden.value = tipe;

    if (tipe === 'selulosa') {
        if (radioSel) radioSel.checked = true;
        if (cardSel) cardSel.classList.add('active-selulosa');
        if (cardLing) cardLing.classList.remove('active-lingkungan');
    } else if (tipe === 'lingkungan') {
        if (radioLing) radioLing.checked = true;
        if (cardLing) cardLing.classList.add('active-lingkungan');
        if (cardSel) cardSel.classList.remove('active-selulosa');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    selectDivisiOpti('<?= ($order['jenis_layanan_opti'] ?: "selulosa") ?>');
});
</script>