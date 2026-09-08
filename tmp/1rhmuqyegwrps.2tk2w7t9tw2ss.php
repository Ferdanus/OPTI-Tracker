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
                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <h4 class="fw-bold text-dark m-0 font-display me-2 fs-4"><?= ($order['judul_kegiatan']) ?></h4>
                    <?php if ($order['jenis_layanan_opti'] == 'selulosa'): ?>
                        <span class="badge bg-primary text-white text-uppercase fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="font-size: 0.88rem; letter-spacing: 0.4px;">
                            OPTI SELULOSA
                        </span>
                    <?php endif; ?>
                    <?php if ($order['jenis_layanan_opti'] == 'lingkungan'): ?>
                        <span class="badge bg-success text-white text-uppercase fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="font-size: 0.88rem; letter-spacing: 0.4px;">
                            OPTI LINGKUNGAN
                        </span>
                    <?php endif; ?>
                    <?php if (!$order['jenis_layanan_opti'] || $order['jenis_layanan_opti'] == 'belum_ditentukan'): ?>
                        <span class="badge bg-secondary text-white text-uppercase fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="font-size: 0.88rem;">
                            BELUM DITENTUKAN
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'permintaan_masuk'): ?>
                        <span class="badge bg-warning text-dark fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="font-size: 0.88rem;">
                            Menunggu Disposisi
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'baru'): ?>
                        <span class="badge fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="font-size: 0.88rem; background-color: #0284c7 !important; color: #ffffff !important;">
                            Order Aktif
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'disetujui'): ?>
                        <span class="badge bg-primary text-white fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="font-size: 0.88rem;">
                            PO Diterbitkan
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'selesai'): ?>
                        <span class="badge bg-success text-white fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="font-size: 0.88rem;">
                            Selesai
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'ditolak'): ?>
                        <span class="badge bg-danger text-white fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="font-size: 0.88rem;">
                            Ditolak
                        </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-3 text-secondary small flex-wrap">
                    <span class="text-nowrap">No. Order: <strong class="text-dark font-monospace"><?= ($order['nomor_order']) ?></strong></span>
                    <span class="text-muted">&bull;</span>
                    <span class="text-nowrap">Tgl Masuk: <span class="text-dark"><?= (date('d M Y', strtotime($order['tanggal_masuk']))) ?></span></span>
                    <span class="text-muted">&bull;</span>
                    <span class="text-nowrap">
                        Waktu Pengerjaan: 
                        <?php if (($proposal && in_array($proposal['status_proposal'], ['disetujui', 'disetujui_ketua', 'disetujui_pimpinan'])) || in_array($order['status_proposal_biaya'], ['siap_penawaran', 'disetujui'])): ?>
                            
                                <span class="badge bg-success-subtle text-success fw-bold px-2 py-0.5 rounded-pill font-monospace"><i class="bi bi-clock-fill me-1"></i><?= ($proposal['durasi_kegiatan'] ?: ($order['proposal_durasi'] ?: '30 Hari Kerja')) ?></span>
                            
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary px-2 py-0.5 rounded-pill"><i class="bi bi-hourglass-split me-1"></i>Belum ditentukan</span>
                            
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Hanya 1 Tombol Kembali Saja di Pojok Kanan -->
            <div class="flex-shrink-0">
                <a href="<?= ($BASE) ?>/order" class="btn btn-light btn-sm text-secondary px-3 py-1.5 fw-medium border">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                </a>
            </div>
        </div>

        <!-- 6-Tahap Progress Stepper Terintegrasi Sesuai 5-Swimlane Diagram -->
        <div class="mt-4 pt-3 border-top">
            <div class="stepper-progress-container">
                <!-- Step 1: Surat Masuk (Sekretaris) -->
                <div class="step-checkpoint">
                    <div class="step-circle done"><i class="bi bi-check-lg"></i></div>
                    <span class="step-text done">1. Surat Masuk</span>
                </div>
                <!-- Step 2: Form Pelayanan (Tim Mitra) -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($order['status'] == 'permintaan_masuk' ? 'current' : 'done') ?>">
                        <?php if ($order['status'] == 'permintaan_masuk'): ?><i class="bi bi-ui-checks"></i><?php endif; ?>
                        <?php if ($order['status'] != 'permintaan_masuk'): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                    </div>
                    <span class="step-text <?= ($order['status'] == 'permintaan_masuk' ? 'current' : 'done') ?>">2. Form Pelayanan</span>
                </div>
                <!-- Step 3: Kaji Ulang & PIC (Ketua Tim) -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'done' : ($order['status'] != 'permintaan_masuk' && !$tinjauan ? 'current' : 'waiting')) ?>">
                        <?php if ($tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan'): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                        <?php if (!($tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan')): ?>3<?php endif; ?>
                    </div>
                    <span class="step-text <?= ($tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'done' : ($order['status'] != 'permintaan_masuk' && !$tinjauan ? 'current' : '')) ?>">
                        3. Kaji Ulang &amp; PIC
                    </span>
                </div>
                <!-- Step 4: Proposal Teknis (PIC & Ketua Tim) -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($order['status_proposal_biaya'] == 'siap_penawaran' ? 'done' : ($tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan' && $order['status_proposal_biaya'] != 'siap_penawaran' ? 'current' : 'waiting')) ?>">
                        <?php if ($order['status_proposal_biaya'] == 'siap_penawaran'): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                        <?php if ($order['status_proposal_biaya'] != 'siap_penawaran'): ?>4<?php endif; ?>
                    </div>
                    <span class="step-text <?= ($order['status_proposal_biaya'] == 'siap_penawaran' ? 'done' : ($tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'current' : '')) ?>">4. Proposal Teknis</span>
                </div>
                <!-- Step 5: Penawaran & Deal (Tim Mitra & Klien) -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= (($order['status_penawaran'] == 'deal' || ($penawaran && $penawaran['status_respon_klien'] == 'deal')) ? 'done' : ($order['status_proposal_biaya'] == 'siap_penawaran' ? 'current' : 'waiting')) ?>">
                        <?php if ($order['status_penawaran'] == 'deal' || ($penawaran && $penawaran['status_respon_klien'] == 'deal')): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                        <?php if (!($order['status_penawaran'] == 'deal' || ($penawaran && $penawaran['status_respon_klien'] == 'deal'))): ?>5<?php endif; ?>
                    </div>
                    <span class="step-text <?= (($order['status_penawaran'] == 'deal' || ($penawaran && $penawaran['status_respon_klien'] == 'deal')) ? 'done' : ($order['status_proposal_biaya'] == 'siap_penawaran' ? 'current' : '')) ?>">5. Penawaran &amp; Deal</span>
                </div>
                <!-- Step 6: PO & Pelaksanaan Lab -->
                <div class="step-checkpoint">
                    <div class="step-circle <?= ($order['po_id'] ? 'done' : (($order['status_penawaran'] == 'deal' || ($penawaran && $penawaran['status_respon_klien'] == 'deal')) ? 'current' : 'waiting')) ?>">
                        <?php if ($order['po_id']): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                        <?php if (!$order['po_id']): ?>6<?php endif; ?>
                    </div>
                    <span class="step-text <?= ($order['po_id'] ? 'done' : '') ?>">6. Pelaksanaan PO</span>
                </div>
            </div>
        </div>
    </div>

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

        </div>

        <!-- Kolom Kanan: Rangkaian Form Disposisi & SOP (8 Kolom) -->
        <div class="col-lg-8">

            <!-- ======================================================== -->
            <!-- 1. FORMULIR PERMINTAAN PELAYANAN JASA (TIM MITRA)        -->
            <!-- ======================================================== -->
            <div class="system-section-card" id="cardPelayanan">
                <div class="system-section-header bg-white">
                    <div>
                        <h6 class="fw-bold text-dark m-0 d-flex align-items-center gap-2 font-display">
                            <i class="bi bi-file-earmark-medical-fill text-primary fs-5"></i> 1. Formulir Permintaan Pelayanan Jasa (F.PJT-08-01/02)
                        </h6>
                        <small class="text-muted">Wewenang: Tim Mitra</small>
                    </div>
                    <?php if ($order['status'] == 'permintaan_masuk'): ?>
                        <span class="badge bg-warning text-dark fw-bold">
                            <i class="bi bi-hourglass-split me-1"></i> Perlu Dilengkapi
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'permintaan_masuk'): ?>
                        <span class="badge bg-success text-white fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Diteruskan ke Ka. Tim <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'Selulosa' : ($order['jenis_layanan_opti'] == 'lingkungan' ? 'Lingkungan' : 'OPTI'))."
" ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="p-4">
                    <?php if ($order['status'] == 'permintaan_masuk'): ?>
                        <div class="p-3 bg-warning-subtle border border-warning-subtle rounded-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <strong class="text-dark d-block mb-1">Surat Telah Diklaim oleh Tim Mitra</strong>
                                    <p class="small text-muted mb-0">Silakan lengkapi formulir spesifikasi layanan, pilih divisi OPTI pelaksana, dan kirimkan ke Ketua Tim.</p>
                                </div>
                                <?php if ($is_admin_order || $is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/form-pelayanan" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                                        <i class="bi bi-pencil-square me-1"></i> Isi Formulir Pelayanan Jasa
                                    </a>
                                <?php endif; ?>
                                <?php if (!$is_admin_order && !$is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/form-pelayanan" class="btn btn-outline-primary btn-sm px-3 fw-semibold shadow-xs">
                                        <i class="bi bi-eye me-1"></i> Lihat Formulir (Read-Only)
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['status'] != 'permintaan_masuk'): ?>
                        <div class="p-3 rounded border <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle border-primary-subtle' : 'bg-success-subtle border-success-subtle') ?> mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-tile bg-white shadow-sm <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'text-primary' : 'text-success') ?>">
                                        <i class="bi <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bi-file-earmark-medical-fill' : 'bi-water') ?>"></i>
                                    </div>
                                    <div>
                                        <span class="badge <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary' : 'bg-success') ?> text-uppercase mb-1">
                                            OPTI <?= ($order['jenis_layanan_opti'])."
" ?>
                                        </span>
                                        <h6 class="fw-bold text-dark m-0 font-display">
                                            Divisi Pelaksana: <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'OPTI Selulosa (Ka. Tim Selulosa)' : 'OPTI Lingkungan (Ka. Tim Lingkungan)')."
" ?>
                                        </h6>
                                        <small class="text-secondary">Standar Dokumen: <strong>F.PJT-08-01/02</strong></small>
                                    </div>
                                </div>
                                <?php if ($is_admin_order || $is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/form-pelayanan" class="btn btn-white btn-sm border fw-semibold shadow-sm text-secondary">
                                        <i class="bi bi-pencil me-1"></i> Ubah Formulir
                                    </a>
                                <?php endif; ?>
                                <?php if (!$is_admin_order && !$is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/form-pelayanan" class="btn btn-white btn-sm border fw-semibold shadow-sm text-secondary">
                                        <i class="bi bi-eye me-1"></i> Lihat Formulir
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Saluran Permintaan Masuk:</span>
                                    <strong class="text-dark text-capitalize"><?= ($penawaran['permintaan_melalui'] ? str_replace('_', ' ', $penawaran['permintaan_melalui']) : ($surat_masuk ? 'Surat Masuk Resmi' : 'E-mail / Surat')) ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Bidang Pelayanan Jasa:</span>
                                    <strong class="text-dark">Optimalisasi Pemanfaatan Teknologi Industri (OPTI)</strong>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-2 border rounded bg-light">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Penjelasan Kebutuhan Pelanggan:</span>
                                    <div class="text-dark mt-1"><?= (($order['deskripsi'] && $order['deskripsi'] != '-' && !strpos($order['deskripsi'], 'Klaim Surat Masuk') && $order['deskripsi'] != 'Dengan penjelasan sebagai berikut...') ? $order['deskripsi'] : (($penawaran['penjelasan'] && $penawaran['penjelasan'] != '-' && !strpos($penawaran['penjelasan'], 'Klaim Surat Masuk') && $penawaran['penjelasan'] != 'Dengan penjelasan sebagai berikut...') ? $penawaran['penjelasan'] : ($order['judul_kegiatan'] ?: 'Kebutuhan pelayanan jasa sesuai surat permohonan klien.'))) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 2. KAJI ULANG KELAYAKAN ISO & PENUGASAN PIC (KETUA TIM)  -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <div>
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2 font-display">
                            <i class="bi bi-clipboard-check-fill text-primary fs-5"></i> 2. Kaji Ulang Kelayakan Teknis dan Penunjukan PIC
                        </h6>
                        <small class="text-muted">Wewenang: Ketua Tim OPTI <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'Selulosa' : ($order['jenis_layanan_opti'] == 'lingkungan' ? 'Lingkungan' : '')) ?></small>
                    </div>
                    <?php if ($order['status'] == 'permintaan_masuk'): ?>
                        <span class="badge bg-light text-secondary border">
                            <i class="bi bi-lock-fill me-1"></i> Terkunci
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'permintaan_masuk'): ?>
                        <?php if ($tinjauan): ?>
                            <span class="badge <?= ($tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'bg-success' : 'bg-danger') ?>">
                                <?= ($tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'Dapat Dilaksanakan' : 'Tidak Dapat Dilaksanakan (Ditolak)')."
" ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!$tinjauan): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                <i class="bi bi-hourglass me-1"></i> Menunggu Kaji Ulang
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <?php if ($order['status'] == 'permintaan_masuk'): ?>
                        <div class="p-4 text-center text-muted py-5 bg-light rounded-3">
                            <i class="bi bi-lock-fill fs-2 text-secondary opacity-50 d-block mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">Tahap Ini Masih Terkunci</h6>
                            <p class="small text-secondary mb-0">Ketua Tim dapat melakukan kaji ulang kelayakan teknis dan menunjuk PIC setelah Tim Mitra melengkapi dan mengirimkan <strong>Formulir Permintaan Pelayanan Jasa (F.PJT-08-01/02)</strong>.</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'permintaan_masuk'): ?>
                        <?php if ($tinjauan): ?>
                            <div class="row g-3 small mb-3">
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-light">
                                        <strong class="<?= ($tinjauan['sdm_tersedia'] ? 'text-success' : 'text-danger') ?>">
                                            <i class="bi <?= ($tinjauan['sdm_tersedia'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill') ?> me-1"></i> Personil / SDM Analis
                                        </strong>
                                        <span class="text-muted d-block mt-1"><?= ($tinjauan['sdm_catatan'] ?: 'Tersedia & Siap') ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-light">
                                        <strong class="<?= ($tinjauan['peralatan_tersedia'] ? 'text-success' : 'text-danger') ?>">
                                            <i class="bi <?= ($tinjauan['peralatan_tersedia'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill') ?> me-1"></i> Peralatan &amp; Instrumen
                                        </strong>
                                        <span class="text-muted d-block mt-1"><?= ($tinjauan['peralatan_catatan'] ?: 'Siap digunakan') ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-light">
                                        <strong class="<?= ($tinjauan['bahan_tersedia'] ? 'text-success' : 'text-danger') ?>">
                                            <i class="bi <?= ($tinjauan['bahan_tersedia'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill') ?> me-1"></i> Bahan Kimia &amp; Reagen
                                        </strong>
                                        <span class="text-muted d-block mt-1"><?= ($tinjauan['bahan_catatan'] ?: 'Tersedia & Cukup') ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-light">
                                        <strong class="<?= ($tinjauan['metode_tersedia'] ? 'text-success' : 'text-danger') ?>">
                                            <i class="bi <?= ($tinjauan['metode_tersedia'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill') ?> me-1"></i> Kesiapan Metode Uji
                                        </strong>
                                        <span class="text-muted d-block mt-1"><?= ($tinjauan['metode_catatan'] ?: 'Tervalidasi SNI/ISO') ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Info Penunjukan PIC Proposal -->
                            <div class="p-3 bg-light rounded border mb-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <span class="text-muted d-block small">PIC Penyusun Proposal yang Ditugaskan:</span>
                                        <strong class="text-primary fs-6 font-display"><?= ($order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?: 'Aji Pisang')) ?></strong>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary border">Tim Kerja / Peneliti Pelaksana</span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top small text-muted">
                                <div>Ditinjau oleh: <strong><?= ($tinjauan['peninjau_nama']) ?></strong> &bull; <?= (date('d M Y H:i', strtotime($tinjauan['tanggal_tinjauan']))) ?></div>
                                <?php if ($is_ketua_tim || $is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan" class="btn btn-outline-primary btn-sm fw-semibold">
                                        <i class="bi bi-pencil me-1"></i> Edit Kaji Ulang &amp; PIC
                                    </a>
                                <?php endif; ?>
                                <?php if (!$is_ketua_tim && !$is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan" class="btn btn-outline-secondary btn-sm fw-semibold">
                                        <i class="bi bi-eye me-1"></i> Lihat Lembar Kaji Ulang
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$tinjauan): ?>
                            <div class="text-center py-3">
                                <i class="bi bi-clipboard-x text-muted fs-2 d-block mb-2 opacity-50"></i>
                                <h6 class="fw-bold text-dark mb-1">Kaji Ulang Kelayakan Teknis Belum Dilakukan</h6>
                                <p class="text-muted small mb-3">Ketua Tim OPTI perlu mengevaluasi ketersediaan personil, mesin uji, reagen, dan menunjuk PIC Proposal.</p>
                                <?php if ($is_ketua_tim || $is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                                        <i class="bi bi-check2-circle me-1"></i> Lakukan Kaji Ulang &amp; Tunjuk PIC
                                    </a>
                                <?php endif; ?>
                                <?php if (!$is_ketua_tim && !$is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                                        <i class="bi bi-eye me-1"></i> Buka Lembar Kaji Ulang (Read-Only)
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 3. RUANG KERJA PROPOSAL TEKNIS (PIC & KETUA TIM)         -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <div>
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2 font-display">
                            <i class="bi bi-file-earmark-ruled-fill text-primary fs-5"></i> 3. Ruang Kerja Proposal Teknis &amp; Biaya
                        </h6>
                        <small class="text-muted">Penyusunan: PIC Proposal &bull; Persetujuan: Ketua Tim OPTI</small>
                    </div>
                    <?php if ($order['status'] == 'permintaan_masuk' || !$tinjauan || $tinjauan['keputusan'] != 'dapat_dilaksanakan'): ?>
                        <span class="badge bg-light text-secondary border">
                            <i class="bi bi-lock-fill me-1"></i> Terkunci
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'permintaan_masuk' && $tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan'): ?>
                        <?php if ($order['status_proposal_biaya'] == 'siap_penawaran'): ?>
                            <span class="badge bg-success text-white"><i class="bi bi-check-circle-fill me-1"></i> Proposal Disetujui (Approved)</span>
                        <?php endif; ?>
                        <?php if ($order['status_proposal_biaya'] == 'menunggu_approval'): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Ketua Tim</span>
                        <?php endif; ?>
                        <?php if ($order['status_proposal_biaya'] == 'draft_disimpan'): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-bookmark-check-fill me-1"></i> Draft Disimpan</span>
                        <?php endif; ?>
                        <?php if ($order['status_proposal_biaya'] == 'draft' || !$order['status_proposal_biaya']): ?>
                            <span class="badge bg-light text-secondary border">Draf Penyusunan PIC</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <?php if ($order['status'] == 'permintaan_masuk' || !$tinjauan || $tinjauan['keputusan'] != 'dapat_dilaksanakan'): ?>
                        <div class="p-4 text-center text-muted py-5 bg-light rounded-3">
                            <i class="bi bi-lock-fill fs-2 text-secondary opacity-50 d-block mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">Tahap Ini Masih Terkunci</h6>
                            <p class="small text-secondary mb-0">Ruang kerja penyusunan proposal teknis dan estimasi biaya akan terbuka setelah <strong>Kaji Ulang Kelayakan Teknis</strong> disetujui dan PIC Proposal ditunjuk oleh Ketua Tim.</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'permintaan_masuk' && $tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan'): ?>
                        <!-- Status & Biaya Ringkas -->
                        <div class="d-flex justify-content-between align-items-center p-3 rounded border mb-3 <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle border-primary-subtle' : 'bg-success-subtle border-success-subtle') ?>">
                            <div>
                                <span class="text-muted small d-block">Estimasi Total Biaya / Anggaran:</span>
                                <h4 class="fw-bold m-0 font-display <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'text-primary' : 'text-success') ?>">
                                    Rp <?= (number_format($order['estimasi_biaya'] ?: ($proposal['estimasi_total_biaya'] ?: 0), 0, ',', '.'))."
" ?>
                                </h4>
                                <small class="text-secondary">PIC Penyusun: <strong><?= ($order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?: 'Aji Pisang')) ?></strong></small>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/proposal" class="btn btn-primary btn-sm fw-semibold shadow-sm">
                                    <i class="bi bi-file-earmark-arrow-up-fill me-1"></i> Ruang Proposal &amp; Upload Dokumen
                                </a>
                                <?php if ($order['jenis_layanan_opti'] == 'selulosa'): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/rancop-selulosa" class="btn btn-outline-primary btn-sm fw-semibold">
                                        <i class="bi bi-diagram-3 me-1"></i> Skenario &amp; Rancop
                                    </a>
                                <?php endif; ?>
                                <?php if ($order['jenis_layanan_opti'] == 'lingkungan'): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/biaya-lingkungan" class="btn btn-outline-success btn-sm fw-semibold">
                                        <i class="bi bi-calculator me-1"></i> Rincian Tarif SNI
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Dokumen Proposal File Upload & Download -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="text-dark small"><i class="bi bi-file-earmark-pdf text-danger me-1"></i> Dokumen Berkas Proposal:</strong>
                                <?php if ($proposal['file_proposal']): ?>
                                    <a href="<?= ($BASE) ?>/<?= ($proposal['file_proposal']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold">
                                        <i class="bi bi-download me-1"></i> Unduh Berkas Proposal
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if ($proposal['file_proposal']): ?>
                                <div class="text-muted small mb-2 font-monospace">
                                    File tersimpan: <?= (basename($proposal['file_proposal']))."
" ?>
                                </div>
                            <?php endif; ?>

                            <!-- Form Upload File Proposal (Hanya PIC Proposal / Tim Kerja / Superadmin) -->
                            <?php if ($user_id == $order['pic_proposal_id'] || $is_tim_kerja || $is_pejabat || $is_superadmin): ?>
                                <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/proposal/upload" method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                                    <input type="file" name="file_proposal" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                                    <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap fw-semibold">
                                        <i class="bi bi-cloud-arrow-up me-1"></i> Upload Dokumen
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($user_id != $order['pic_proposal_id'] && !$is_tim_kerja && !$is_pejabat && !$is_superadmin && !$proposal['file_proposal']): ?>
                                <div class="small text-muted fst-italic">Belum ada berkas proposal yang diupload oleh PIC.</div>
                            <?php endif; ?>
                        </div>

                        <!-- Catatan Revisi jika ada -->
                        <?php if ($proposal['catatan_revisi']): ?>
                            <div class="alert alert-warning border-0 p-2 mb-3 small">
                                <strong><i class="bi bi-chat-left-quote me-1"></i> Catatan Ketua Tim:</strong> <?= ($proposal['catatan_revisi'])."
" ?>
                            </div>
                        <?php endif; ?>

                        <!-- Aksi Berdasarkan Peran & Status -->
                        <!-- 1. Jika Status masih Draft / Draft Disimpan: Tombol Ajukan ke Ketua Tim -->
                        <?php if ($order['status_proposal_biaya'] == 'draft' || $order['status_proposal_biaya'] == 'draft_disimpan' || !$order['status_proposal_biaya']): ?>
                            <?php if ($user_id == $order['pic_proposal_id'] || $is_tim_kerja || $is_pejabat || $is_superadmin): ?>
                                <div class="d-flex justify-content-end pt-2 border-top">
                                    <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/proposal/kirim-katim" method="POST">
                                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm">
                                            <i class="bi bi-send-fill me-1"></i> Simpan &amp; Ajukan Proposal ke Ketua Tim
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <?php if ($user_id != $order['pic_proposal_id'] && !$is_tim_kerja && !$is_pejabat && !$is_superadmin): ?>
                                <div class="p-3 bg-light border rounded text-center small text-secondary">
                                    <i class="bi bi-lock-fill me-1"></i> Menunggu PIC Proposal (<strong><?= ($order['pic_proposal_nama'] ?: 'PIC') ?></strong>) menyelesaikan dan mengajukan proposal ke Ketua Tim.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- 2. Jika Status Menunggu Approval: Card Review untuk Ketua Tim -->
                        <?php if ($order['status_proposal_biaya'] == 'menunggu_approval'): ?>
                            <?php if ($is_ketua_tim || $is_superadmin): ?>
                                <div class="p-3 bg-warning-subtle border border-warning-subtle rounded-3">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-warning me-1"></i> Verifikasi &amp; Persetujuan Ketua Tim OPTI</h6>
                                    <p class="small text-muted mb-3">Ketua Tim memeriksa kesesuaian ruang lingkup, estimasi anggaran, dan kelayakan dokumen proposal yang diajukan PIC.</p>
                                    
                                    <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/proposal/review-katim" method="POST">
                                        <div class="mb-2">
                                            <input type="text" name="catatan_revisi" class="form-control form-control-sm" placeholder="Catatan persetujuan atau catatan perbaikan jika perlu revisi...">
                                        </div>
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="submit" name="action_review" value="reject" class="btn btn-outline-danger btn-sm fw-semibold">
                                                <i class="bi bi-x-circle me-1"></i> Kembalikan untuk Revisi
                                            </button>
                                            <button type="submit" name="action_review" value="approve" class="btn btn-success btn-sm fw-bold px-3">
                                                <i class="bi bi-check-circle-fill me-1"></i> Setujui Proposal (Approve)
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <?php if (!$is_ketua_tim && !$is_superadmin): ?>
                                <div class="p-3 bg-warning-subtle border border-warning-subtle rounded-3 text-center small text-secondary">
                                    <i class="bi bi-hourglass-split me-1 text-warning"></i> Proposal telah diajukan dan sedang dalam proses verifikasi / persetujuan oleh Ketua Tim OPTI.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- 3. Jika Status Sudah Approved -->
                        <?php if ($order['status_proposal_biaya'] == 'siap_penawaran'): ?>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top small text-success">
                                <div><i class="bi bi-check-circle-fill me-1"></i> Disetujui Ketua Tim &bull; Siap diterbitkan Surat Pelayanan Resmi</div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 4. SURAT PELAYANAN RESMI & KESEPAKATAN KLIEN (TIM MITRA) -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <div>
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2 font-display">
                            <i class="bi bi-send-fill text-primary fs-5"></i> 4. Surat Pelayanan Resmi &amp; Respon Pelanggan
                        </h6>
                        <small class="text-muted">Wewenang: Tim Mitra</small>
                    </div>
                    <?php if ($order['status'] == 'permintaan_masuk' || !$tinjauan || $tinjauan['keputusan'] != 'dapat_dilaksanakan' || $order['status_proposal_biaya'] != 'siap_penawaran'): ?>
                        <span class="badge bg-light text-secondary border">
                            <i class="bi bi-lock-fill me-1"></i> Terkunci
                        </span>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'permintaan_masuk' && $tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan' && $order['status_proposal_biaya'] == 'siap_penawaran'): ?>
                        <?php if ($penawaran): ?>
                            <span class="badge <?= ($penawaran['status_respon_klien'] == 'deal' ? 'bg-success' : ($penawaran['status_respon_klien'] == 'batal' ? 'bg-danger' : 'bg-primary')) ?>">
                                <?= (strtoupper($penawaran['status_respon_klien']))."
" ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!$penawaran): ?>
                            <span class="badge bg-warning text-dark">Siap Diterbitkan</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <?php if ($order['status'] == 'permintaan_masuk' || !$tinjauan || $tinjauan['keputusan'] != 'dapat_dilaksanakan' || $order['status_proposal_biaya'] != 'siap_penawaran'): ?>
                        <div class="p-4 text-center text-muted py-5 bg-light rounded-3">
                            <i class="bi bi-lock-fill fs-2 text-secondary opacity-50 d-block mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">Tahap Ini Masih Terkunci</h6>
                            <p class="small text-secondary mb-0">Surat Pelayanan Resmi dapat diterbitkan oleh Tim Mitra setelah <strong>Proposal Teknis &amp; Biaya</strong> telah diverifikasi dan disetujui (*Approved*) oleh Ketua Tim.</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'permintaan_masuk' && $tinjauan && $tinjauan['keputusan'] == 'dapat_dilaksanakan' && $order['status_proposal_biaya'] == 'siap_penawaran'): ?>
                        <?php if ($penawaran): ?>
                            <div class="row g-3 mb-3 small">
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Nomor Surat Pelayanan / Penawaran:</span>
                                    <strong class="text-primary font-monospace"><?= ($penawaran['nomor_surat']) ?></strong>
                                    <small class="text-muted d-block">Tanggal: <?= (date('d M Y', strtotime($penawaran['tanggal_surat']))) ?></small>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Total Nilai Pelayanan:</span>
                                    <h4 class="fw-bold text-dark m-0 font-display">Rp <?= (number_format($penawaran['nominal_penawaran'], 0, ',', '.')) ?></h4>
                                </div>
                            </div>

                            <!-- Catatan & Respon Pelanggan (Deal / Tolak) -->
                            <div class="p-3 bg-light rounded border mb-3">
                                <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-hand-thumbs-up-fill text-primary me-1"></i> Pencatatan Respon Pelanggan Terhadap Penawaran:</h6>
                                <?php if ($is_admin_order || $is_superadmin): ?>
                                    <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/respon-klien" method="POST">
                                        <div class="row g-2 align-items-center mb-2">
                                            <div class="col-md-8">
                                                <input type="text" name="catatan_klien" class="form-control form-control-sm" placeholder="Catatan kesepakatan klien / hasil meeting negosiasi..." value="<?= ($penawaran['catatan_nego'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 d-flex gap-2">
                                                <button type="submit" name="keputusan_klien" value="deal" class="btn btn-success btn-sm w-100 fw-bold">
                                                    <i class="bi bi-check-circle me-1"></i> Terima (Deal)
                                                </button>
                                                <button type="submit" name="keputusan_klien" value="batal" class="btn btn-outline-danger btn-sm w-100 fw-semibold">
                                                    <i class="bi bi-x-circle me-1"></i> Tolak
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                <?php endif; ?>
                                <?php if (!$is_admin_order && !$is_superadmin): ?>
                                    <div class="small text-secondary">
                                        Status saat ini: <strong class="text-dark"><?= (strtoupper($penawaran['status_respon_klien'])) ?></strong>
                                        <?php if ($penawaran['catatan_nego']): ?>
                                            &bull; Catatan: <em>&ldquo;<?= ($penawaran['catatan_nego']) ?>&rdquo;</em>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                                <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/cetak" target="_blank" class="btn btn-outline-primary btn-sm fw-semibold">
                                    <i class="bi bi-printer me-1"></i> Preview PDF Surat Pelayanan
                                </a>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($is_admin_order || $is_superadmin): ?>
                                        <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/buat" class="btn btn-primary btn-sm fw-semibold">
                                            <i class="bi bi-pencil me-1"></i> Edit Surat Pelayanan
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalHapusPenawaran<?= ($penawaran['id']) ?>" title="Hapus Surat Pelayanan">
                                            <i class="bi bi-trash3 me-1"></i> Hapus
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!$is_admin_order && !$is_superadmin): ?>
                                        <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/buat" class="btn btn-outline-secondary btn-sm fw-semibold">
                                            <i class="bi bi-eye me-1"></i> Lihat Data Surat Pelayanan
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Modal Konfirmasi Hapus Surat Pelayanan dari Halaman Order -->
                            <?php if ($is_admin_order || $is_superadmin): ?>
                                <div class="modal fade" id="modalHapusPenawaran<?= ($penawaran['id']) ?>" tabindex="-1" aria-labelledby="modalHapusPenawaranLabel<?= ($penawaran['id']) ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-white border-0 shadow rounded-3 text-start">
                                            <div class="modal-header border-bottom py-3 px-4 bg-white">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: #ffe4e6; color: var(--color-primary);">
                                                        <i class="bi bi-trash3-fill fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalHapusPenawaranLabel<?= ($penawaran['id']) ?>">Konfirmasi Hapus Surat Pelayanan</h6>
                                                        <small class="text-muted" style="font-size: 0.75rem;">Tindakan ini tidak dapat dibatalkan</small>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                            </div>

                                            <form action="<?= ($BASE) ?>/surat-penawaran/<?= ($penawaran['id']) ?>/hapus" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                                <input type="hidden" name="redirect" value="order">

                                                <div class="modal-body p-4">
                                                    <p class="text-dark small mb-3">
                                                        Apakah Anda yakin ingin menghapus data surat pelayanan untuk order ini?
                                                    </p>

                                                    <!-- Box Rincian Surat Pelayanan -->
                                                    <div class="border rounded-2 p-3 bg-light mb-3">
                                                        <div class="mb-2">
                                                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Nomor Surat Pelayanan:</span>
                                                            <strong class="font-monospace fs-6" style="color: var(--color-primary);"><?= ($penawaran['nomor_surat']) ?></strong>
                                                        </div>
                                                        <div class="mb-2">
                                                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Order Terkait:</span>
                                                            <span class="fw-semibold text-dark">#<?= ($order['nomor_order']) ?> - <?= ($order['pt_cv'] ? $order['pt_cv'] . ' ' : '') ?><?= ($order['nmcustomer'] ?: $order['nama_perusahaan']) ?></span>
                                                        </div>
                                                        <div class="mb-0">
                                                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Perihal:</span>
                                                            <span class="text-secondary small"><?= ($penawaran['perihal']) ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="p-2 mb-0 rounded-2 small d-flex align-items-center gap-2" style="font-size: 0.8rem; background-color: #fff1f2; color: #881337; border: 1px solid #fecdd3;">
                                                        <i class="bi bi-exclamation-triangle-fill fs-6 flex-shrink-0" style="color: #881337;"></i>
                                                        <span>Surat pelayanan akan dihapus dan status order akan kembali ke tahap sebelum penawaran terbit.</span>
                                                    </div>
                                                </div>

                                                <div class="modal-footer border-top py-3 px-4 bg-light d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold" data-bs-dismiss="modal">
                                                        Batal
                                                    </button>
                                                    <button type="submit" class="btn btn-primary btn-sm px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-1">
                                                        <i class="bi bi-trash3-fill"></i> Ya, Hapus Surat
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!$penawaran): ?>
                            <div class="text-center py-3">
                                <i class="bi bi-envelope-paper-heart text-success fs-2 d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">Proposal Telah Disetujui &bull; Siap Kirim Surat Pelayanan</h6>
                                <p class="text-muted small mb-3">Tim Mitra dapat menerbitkan Surat Pelayanan Resmi dan mengirimkannya kepada pihak pelanggan.</p>
                                <?php if ($is_admin_order || $is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/buat" class="btn btn-success btn-sm px-4 fw-bold shadow-sm">
                                        <i class="bi bi-file-earmark-plus me-1"></i> Buat Surat Pelayanan Resmi
                                    </a>
                                <?php endif; ?>
                                <?php if (!$is_admin_order && !$is_superadmin): ?>
                                    <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/penawaran/buat" class="btn btn-outline-success btn-sm px-3 fw-semibold shadow-xs">
                                        <i class="bi bi-eye me-1"></i> Lihat Format Surat Pelayanan (Read-Only)
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAHAP 5: PETUNJUK OPERASIONAL (PO)                       -->
            <!-- ======================================================== -->
            <div class="system-section-card">
                <div class="system-section-header">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2 font-display">
                        <i class="bi bi-speedometer2 text-primary fs-5"></i> 5. Petunjuk Operasional (PO) &amp; Pelaksanaan Teknis
                    </h6>
                    <?php if ($order['po_id']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">PO Aktif: <?= ($order['nomor_po']) ?></span>
                    <?php endif; ?>
                    <?php if (!$order['po_id']): ?>
                        <?php if ($penawaran && $penawaran['status_respon_klien'] == 'deal'): ?>
                            <span class="badge bg-warning text-dark">Siap Terbit PO</span>
                        <?php endif; ?>
                        <?php if (!$penawaran || $penawaran['status_respon_klien'] != 'deal'): ?>
                            <span class="badge bg-light text-secondary border">
                                <i class="bi bi-lock-fill me-1"></i> Terkunci
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="p-4">
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
                            <div class="p-4 text-center text-muted py-5 bg-light rounded-3">
                                <i class="bi bi-lock-fill fs-2 text-secondary opacity-50 d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">Tahap Ini Masih Terkunci</h6>
                                <p class="small text-secondary mb-0">Petunjuk Operasional (PO) dapat diterbitkan setelah Surat Penawaran Resmi berstatus <strong>DEAL</strong> (disepakati pelanggan).</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tahapan Lanjutan (Kontrak, Pembayaran, BAST) - Disembunyikan sementara sesuai arahan -->

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