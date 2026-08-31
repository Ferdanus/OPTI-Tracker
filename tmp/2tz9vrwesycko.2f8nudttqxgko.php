<style>
.proposal-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 2.25rem 1.5rem;
    text-align: center;
    background-color: #f8fafc;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    position: relative;
}
.proposal-dropzone:hover, .proposal-dropzone.dragover {
    border-color: var(--color-primary, #881337);
    background-color: #fff1f2;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(136, 19, 55, 0.08);
}
.proposal-dropzone .dropzone-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #ffffff;
    color: var(--color-primary, #881337);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 0.85rem;
    transition: transform 0.2s ease;
}
.proposal-dropzone:hover .dropzone-icon {
    transform: scale(1.08);
}
.preview-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.proposal-sheet {
    background-color: #ffffff;
    max-width: 800px;
    margin: 0 auto;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
</style>

<!-- PDF.js Library for Zero-IDM-Interception in-browser rendering -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<div class="container-fluid px-0">
    <!-- Header & Navigasi -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <h4 class="fw-bold text-dark m-0 font-display">Penyusunan &amp; Upload Dokumen Proposal</h4>
                <span class="badge <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-success-subtle text-success border border-success-subtle') ?> px-2.5 py-1 text-uppercase fw-bold" style="font-size: 0.72rem;">
                    OPTI <?= ($order['jenis_layanan_opti'])."
" ?>
                </span>
                
                <!-- Status Proposal Badge -->
                <?php if ($proposal && $proposal['status_proposal'] == 'disetujui_ketua'): ?>
                    <span class="badge badge-pill-success px-2.5 py-1">
                        <i class="bi bi-check-circle-fill me-1"></i> Disetujui Ka. Tim
                    </span>
                <?php endif; ?>
                <?php if ($proposal && $proposal['status_proposal'] == 'diajukan'): ?>
                    <span class="badge badge-pill-info px-2.5 py-1">
                        <i class="bi bi-clock-history me-1"></i> Menunggu Ka. Tim
                    </span>
                <?php endif; ?>
                <?php if ($proposal && $proposal['status_proposal'] == 'ditolak'): ?>
                    <span class="badge badge-pill-danger px-2.5 py-1">
                        <i class="bi bi-exclamation-diamond-fill me-1"></i> Perlu Revisi
                    </span>
                <?php endif; ?>
                <?php if (!$proposal || $proposal['status_proposal'] == 'draft'): ?>
                    <span class="badge badge-pill-warning px-2.5 py-1">
                        <i class="bi bi-pencil-square me-1"></i> Draf PIC
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-secondary small mb-0">
                Order <strong class="text-dark font-monospace">#<?= ($order['nomor_order']) ?></strong> &bull; <?= ($order['nama_perusahaan']) ?> (<?= ($order['pt_cv']) ?>) &bull; <span class="fst-italic text-dark"><?= ($order['judul_kegiatan']) ?></span>
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="<?= ($BASE) ?>/proposal" class="btn btn-light btn-sm text-secondary px-3 py-1.5 fw-medium border">
                <i class="bi bi-journal-code me-1"></i> Daftar Proposal
            </a>
            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="btn btn-light btn-sm text-secondary px-3 py-1.5 fw-medium border">
                <i class="bi bi-arrow-left me-1"></i> Detail Order
            </a>
        </div>
    </div>

    <!-- Alert Catatan Revisi jika ada -->
    <?php if ($proposal && $proposal['status_proposal'] == 'ditolak' && $proposal['catatan_revisi']): ?>
        <div class="alert alert-danger border-0 d-flex align-items-start gap-3 p-3 mb-4 rounded-3 shadow-xs">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5 flex-shrink-0 mt-0.5"></i>
            <div>
                <strong class="text-danger d-block mb-1 font-display">Catatan Revisi dari Ketua Tim OPTI:</strong>
                <p class="mb-0 small text-danger-emphasis"><?= ($proposal['catatan_revisi']) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Kolom Kiri: Informasi Permintaan & Surat Masuk (5 Kolom) -->
        <div class="col-lg-5">
            <!-- Card 1: Data Pelanggan & Penugasan PIC -->
            <div class="card border rounded-3 bg-white shadow-xs mb-4">
                <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom">
                    <h6 class="m-0 fw-bold text-dark font-display">Data Permintaan &amp; Penugasan PIC</h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="small text-secondary" style="font-size: 0.84rem; line-height: 1.6;">
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 130px; flex-shrink: 0;">No. Order</span>
                            <span class="text-muted me-2">:</span>
                            <strong class="text-dark font-monospace"><?= ($order['nomor_order']) ?></strong>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 130px; flex-shrink: 0;">Nama Perusahaan</span>
                            <span class="text-muted me-2">:</span>
                            <strong class="text-dark"><?= ($order['nama_perusahaan']) ?> (<?= ($order['pt_cv']) ?>)</strong>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 130px; flex-shrink: 0;">PIC Klien</span>
                            <span class="text-muted me-2">:</span>
                            <span class="text-dark"><?= ($order['nama_pic'] ?: '-') ?> <span class="text-muted small">(<?= ($order['kontak_pic'] ?: '-') ?>)</span></span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 130px; flex-shrink: 0;">PIC Peneliti</span>
                            <span class="text-muted me-2">:</span>
                            <div>
                                <strong class="text-primary font-display"><i class="bi bi-person-badge me-1"></i> <?= ($order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?: 'Tim Pelaksana')) ?></strong>
                                <small class="d-block text-secondary">Spesialisasi <?= (ucfirst($order['jenis_layanan_opti'])) ?></small>
                            </div>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 130px; flex-shrink: 0;">Standar SPM</span>
                            <span class="text-muted me-2">:</span>
                            <span class="badge bg-secondary-subtle text-secondary px-2 py-0.5 rounded-pill font-monospace fw-semibold"><?= ($order['spm_layanan'] ?: '7 Hari Kerja') ?></span>
                        </div>
                        <div class="d-flex">
                            <span class="text-muted" style="width: 130px; flex-shrink: 0;">Perihal Kegiatan</span>
                            <span class="text-muted me-2">:</span>
                            <span class="text-dark fw-medium"><?= ($order['judul_kegiatan']) ?></span>
                        </div>
                    </div>

                    <!-- Pratinjau Surat Permohonan jika ada -->
                    <?php if ($surat_masuk): ?>
                        <div class="pt-3 mt-3 border-top">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 fw-semibold d-flex align-items-center justify-content-center gap-1.5 shadow-xs" data-bs-toggle="modal" data-bs-target="#modalPreviewSuratMasukProposal">
                                <i class="bi bi-file-earmark-text-fill text-danger fs-6"></i> Pratinjau Surat Permohonan Klien
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 2: Hasil Kaji Ulang Kelayakan Teknis (ISO 17025) -->
            <?php if ($tinjauan): ?>
                <div class="card border rounded-3 bg-white shadow-xs mb-4">
                    <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom">
                        <h6 class="m-0 fw-bold text-dark font-display">Hasil Kaji Ulang Kelayakan (Ka. Tim)</h6>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <div class="mb-2">
                            <span class="badge bg-success text-white px-2.5 py-1 rounded-pill fw-semibold mb-2">
                                <i class="bi bi-check-circle-fill me-1"></i> Dapat Dilaksanakan
                            </span>
                        </div>
                        <div class="small text-secondary">
                            <strong class="text-dark d-block mb-1">Catatan Evaluasi:</strong>
                            <p class="mb-0 text-secondary fst-italic"><?= ($tinjauan['catatan_kelayakan'] ?: 'Kapasitas laboratorium, instrumentasi pengujian, dan ketersediaan personil analis memenuhi standar.') ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kolom Kanan: Formulir Penyusunan & Upload Dokumen Proposal (7 Kolom) -->
        <div class="col-lg-7">
            <div class="card border rounded-3 bg-white shadow-xs mb-4">
                <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark font-display">Formulir &amp; Dokumen Proposal Teknis</h6>
                    <?php if (!$can_edit): ?>
                        <span class="badge bg-light text-secondary border"><i class="bi bi-lock-fill me-1"></i> Mode Lihat Saja</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    
                    <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/proposal/simpan" method="POST" enctype="multipart/form-data" id="formProposal">
                        
                        <!-- 1. Judul Proposal -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-2" style="font-size: 0.86rem;">
                                Judul Proposal Teknis <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="judul_proposal" class="form-control py-2 px-3" 
                                   value="<?= ($proposal['judul_proposal'] ?: $order['judul_kegiatan']) ?>" 
                                   placeholder="Judul proposal..." required <?= (!$can_edit ? 'disabled' : '') ?>>
                        </div>

                        <!-- 2. Ruang Lingkup & Metodologi -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-2" style="font-size: 0.86rem;">
                                Ruang Lingkup &amp; Metodologi Pengujian / Riset
                            </label>
                            <textarea name="ruang_lingkup" class="form-control p-3" rows="5" 
                                      placeholder="Uraian parameter uji, metodologi standar SNI/ISO/TAPPI, tahapan sampling, dan alur eksperimen..." <?= (!$can_edit ? 'disabled' : '') ?>><?= ($proposal['ruang_lingkup']) ?></textarea>
                        </div>

                        <!-- 3. Durasi & Estimasi Biaya (RAB) -->
                        <div class="row g-3.5 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-2" style="font-size: 0.86rem;">
                                    Estimasi Durasi Pelaksanaan <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted px-3" style="font-size: 0.84rem;"><i class="bi bi-calendar-event"></i></span>
                                    <input type="number" min="1" max="365" name="durasi_hari" class="form-control fw-semibold font-monospace py-2" style="font-size: 0.84rem;" 
                                           value="<?= ($durasi_hari ?: '30') ?>" placeholder="30" required <?= (!$can_edit ? 'disabled' : '') ?>>
                                    <span class="input-group-text bg-light text-secondary fw-semibold px-3" style="font-size: 0.8rem;">Hari Kerja</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-2" style="font-size: 0.86rem;">
                                    Estimasi Total Biaya / RAB (Rp) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted fw-semibold px-3" style="font-size: 0.84rem;">Rp</span>
                                    <input type="number" step="any" name="estimasi_total_biaya" class="form-control fw-bold text-primary font-monospace py-2" style="font-size: 0.84rem;" 
                                           value="<?= ($proposal['estimasi_total_biaya'] ?: ($order['estimasi_biaya'] ?: 0)) ?>" 
                                           placeholder="0" required <?= (!$can_edit ? 'disabled' : '') ?>>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Area Upload & Pratinjau Berkas Dokumen Proposal -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-2 d-flex justify-content-between align-items-center" style="font-size: 0.86rem;">
                                <span>Dokumen Proposal Teknis <span class="text-danger">*</span></span>
                                <small class="text-muted fw-normal">Format: PDF, DOCX, XLSX (Maks. 20MB)</small>
                            </label>

                            <!-- Card Berkas / Dokumen Proposal Aktif -->
                            <?php if ($proposal): ?>
                                <div class="preview-card mb-3">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-3 bg-danger-subtle text-danger p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                <i class="bi bi-file-earmark-pdf-fill fs-4"></i>
                                            </div>
                                            <div>
                                                <strong class="text-dark d-block font-monospace" style="font-size: 0.88rem;">
                                                    <?= ($proposal['file_proposal'] ? basename($proposal['file_proposal']) : 'Proposal_Order_#' . $order['nomor_order'] . '.pdf')."
" ?>
                                                </strong>
                                                <div class="d-flex align-items-center gap-2 mt-0.5">
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill" style="font-size: 0.68rem;">Tersimpan di Sistem</span>
                                                    <span class="text-muted small" style="font-size: 0.72rem;"><?= ($proposal['durasi_kegiatan'] ?: '30 Hari Kerja') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <!-- Tombol Modal Pratinjau In-App Bebas IDM -->
                                            <button type="button" class="btn btn-outline-danger btn-sm px-3 py-1.5 fw-semibold shadow-xs" onclick="openProposalCanvasModal('<?= ($BASE) ?>/order/<?= ($order['id']) ?>/proposal/pdf')">
                                                <i class="bi bi-eye-fill me-1"></i> Pratinjau Dokumen
                                            </button>
                                            <!-- Tombol Download jika ada file fisik -->
                                            <?php if ($proposal['file_proposal']): ?>
                                                <a href="<?= ($BASE) ?>/<?= ($proposal['file_proposal']) ?>" target="_blank" class="btn btn-light border btn-sm px-3 py-1.5 fw-semibold text-secondary" download>
                                                    <i class="bi bi-download me-1"></i> Unduh
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Area Dropzone Besar & Interaktif -->
                            <?php if ($can_edit): ?>
                                <div class="proposal-dropzone" id="dropzoneContainer" onclick="document.getElementById('file_proposal').click()">
                                    <div class="dropzone-icon">
                                        <i class="bi bi-cloud-arrow-up-fill fs-2"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark font-display mb-1" style="font-size: 0.95rem;">
                                        Pilih Berkas atau Tarik File ke Sini
                                    </h6>
                                    <p class="text-muted small mb-2">Klik untuk memilih dokumen proposal dari perangkat Anda</p>
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <span class="badge bg-white text-secondary border px-2.5 py-1 rounded-pill" style="font-size: 0.72rem;">PDF</span>
                                        <span class="badge bg-white text-secondary border px-2.5 py-1 rounded-pill" style="font-size: 0.72rem;">DOC / DOCX</span>
                                        <span class="badge bg-white text-secondary border px-2.5 py-1 rounded-pill" style="font-size: 0.72rem;">XLS / XLSX</span>
                                    </div>

                                    <!-- Hidden Real File Input -->
                                    <input type="file" name="file_proposal" id="file_proposal" class="d-none" accept=".pdf,.doc,.docx,.xls,.xlsx" onchange="handleFileSelect(event)">
                                </div>

                                <!-- Live Selected File Preview Box (Muncul saat user memilih file baru) -->
                                <div id="selectedFilePreview" class="preview-card mt-3 d-none border-primary border-opacity-25" style="background-color: #faf5ff;">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-3">
                                            <div id="fileIconBox" class="rounded-3 bg-primary text-white p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                <i class="bi bi-file-earmark-check-fill fs-4"></i>
                                            </div>
                                            <div>
                                                <strong id="selectedFileName" class="text-dark d-block font-monospace" style="font-size: 0.88rem;">-</strong>
                                                <div class="d-flex align-items-center gap-2 mt-0.5">
                                                    <span id="selectedFileSize" class="text-muted small">0 KB</span>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 rounded-pill" style="font-size: 0.68rem;">Siap Diunggah</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" id="btnPreviewSelectedPdf" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold d-none" onclick="toggleLocalPdfPreview()">
                                                <i class="bi bi-eye me-1"></i> Pratinjau File
                                            </button>
                                            <button type="button" class="btn btn-light border btn-sm px-2.5 py-1.5 text-danger" onclick="cancelSelectedFile()" title="Batalkan Pilihan">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Inline PDF Canvas Viewer Container (Zero IDM Trigger) -->
                                    <div id="inlinePdfPreviewWrapper" class="mt-3 pt-3 border-top d-none">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="fw-bold text-dark font-monospace"><i class="bi bi-filetype-pdf text-danger me-1"></i> Pratinjau Tampilan Berkas PDF (Halaman 1):</small>
                                            <button type="button" class="btn btn-sm btn-link text-secondary p-0 text-decoration-none" onclick="toggleLocalPdfPreview()"><i class="bi bi-chevron-up"></i> Tutup</button>
                                        </div>
                                        <div class="p-3 bg-secondary bg-opacity-10 rounded-3 border text-center overflow-auto" style="max-height: 500px;">
                                            <canvas id="localPdfCanvas" class="shadow-sm border rounded bg-white mx-auto" style="max-width: 100%; height: auto;"></canvas>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 5. Tombol Aksi PIC -->
                        <?php if ($can_edit): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
                                <button type="submit" name="action_type" value="draft" class="btn btn-light border px-3.5 py-2 fw-semibold text-secondary">
                                    <i class="bi bi-save me-1"></i> Simpan Draf
                                </button>
                                <button type="submit" name="action_type" value="ajukan" class="btn btn-primary px-4 py-2 fw-semibold text-white shadow-sm">
                                    <i class="bi bi-send-fill me-1"></i> Ajukan ke Ketua Tim OPTI
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- 6. Panel Review & Persetujuan Ketua Tim OPTI -->
            <?php if ($can_review): ?>
                <div class="card border rounded-3 bg-white shadow-xs mb-4" style="border-left: 4px solid var(--color-primary) !important;">
                    <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark font-display d-flex align-items-center gap-2">
                            <i class="bi bi-patch-check-fill text-primary"></i>
                            Panel Review &amp; Persetujuan Ketua Tim OPTI
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-0.5 small fw-semibold">Wewenang Ka. Tim</span>
                    </div>
                    <div class="card-body p-4">
                        <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/proposal/review-katim" method="POST">
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-dark mb-2" style="font-size: 0.86rem;">Catatan Review / Catatan Revisi</label>
                                <textarea name="catatan_revisi" class="form-control p-3" rows="3" placeholder="Masukkan catatan persetujuan atau catatan revisi..."><?= ($proposal['catatan_revisi']) ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2.5">
                                <button type="submit" name="action_review" value="reject" class="btn btn-outline-danger px-3.5 py-2 fw-semibold rounded-3 d-inline-flex align-items-center gap-1.5 shadow-xs">
                                    <i class="bi bi-arrow-counterclockwise fs-6"></i>
                                    <span>Minta Revisi Proposal</span>
                                </button>
                                
                                <button type="submit" name="action_review" value="approve" class="btn btn-success px-4 py-2 fw-semibold text-white rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm">
                                    <i class="bi bi-check2-circle fs-6"></i>
                                    <span>Setujui Proposal (Approve)</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL PRATINJAU DOKUMEN PROPOSAL (DUAL TAB VIEW)          -->
<!-- ========================================================= -->
<?php if ($proposal || $order): ?>
    <div class="modal fade" id="modalPreviewDokumenProposal" tabindex="-1" aria-labelledby="modalPreviewDokumenProposalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 92vw;">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="height: 90vh;">
                
                <!-- Modal Header -->
                <div class="modal-header border-bottom py-2.5 px-4 bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-danger-subtle text-danger p-1.5 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark font-display mb-0" id="modalPreviewDokumenProposalLabel" style="font-size: 0.92rem;">Pratinjau Dokumen Proposal Teknis</h6>
                            <small class="text-secondary font-monospace" style="font-size: 0.72rem;">Order #<?= ($order['nomor_order']) ?> &bull; <?= ($order['nama_perusahaan']) ?></small>
                        </div>
                    </div>

                    <!-- Tab Buttons & Actions -->
                    <div class="d-flex align-items-center gap-2">
                        <ul class="nav nav-pills nav-pills-sm me-2" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active px-3 py-1 fw-semibold small" id="tab-doc-btn" data-bs-toggle="pill" data-bs-target="#tab-doc-content" type="button" role="tab">
                                    <i class="bi bi-file-earmark-richtext me-1"></i> Lembar Dokumen
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link px-3 py-1 fw-semibold small" id="tab-pdf-btn" data-bs-toggle="pill" data-bs-target="#tab-pdf-content" type="button" role="tab" onclick="triggerCanvasPdfRender()">
                                    <i class="bi bi-filetype-pdf me-1 text-danger"></i> Berkas PDF
                                </button>
                            </li>
                        </ul>

                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                
                <!-- Modal Body with Tabs -->
                <div class="modal-body p-0 bg-secondary bg-opacity-10 position-relative overflow-auto" style="min-height: 65vh;">
                    <div class="tab-content h-100">
                        
                        <!-- TAB 1: LEMBAR DOKUMEN RESMI BERSTRUKTUR (HTML INSTANT RENDER) -->
                        <div class="tab-pane fade show active p-4" id="tab-doc-content" role="tabpanel">
                            <div class="proposal-sheet p-4 p-md-5 rounded-3 bg-white">
                                
                                <!-- KOP SURAT BBSPJIS -->
                                <div class="text-center border-bottom pb-3 mb-4">
                                    <h6 class="fw-bold text-dark text-uppercase mb-0.5" style="font-size: 1.05rem; letter-spacing: 0.5px;">KEMENTERIAN PERINDUSTRIAN REPUBLIK INDONESIA</h6>
                                    <h6 class="fw-bold text-dark text-uppercase mb-1" style="font-size: 0.95rem;">BALAI BESAR STANDARDISASI DAN PELAYANAN JASA INDUSTRI SELULOSA</h6>
                                    <small class="text-muted d-block" style="font-size: 0.78rem;">
                                        Jl. Raya Dayeuhkolot No. 132, Bandung 40258 | Telp. (022) 5202871 | www.bbspjis.kemenperin.go.id
                                    </small>
                                </div>

                                <!-- JUDUL PROPOSAL -->
                                <div class="text-center mb-4">
                                    <h5 class="fw-bold text-primary font-display mb-1 text-uppercase" style="font-size: 1.1rem;">
                                        PROPOSAL TEKNIS &amp; RANCANGAN ANGGARAN BIAYA (RAB)
                                    </h5>
                                    <span class="badge bg-secondary-subtle text-secondary px-3 py-1 rounded-pill fw-semibold font-monospace" style="font-size: 0.75rem;">
                                        LAYANAN OPTI <?= (strtoupper($order['jenis_layanan_opti'])) ?> &bull; NO. #<?= ($order['nomor_order'])."
" ?>
                                    </span>
                                </div>

                                <!-- METADATA & DATA PEMOHON -->
                                <div class="border rounded-3 p-3.5 mb-3.5 bg-light bg-opacity-40">
                                    <h6 class="fw-bold text-dark font-display border-bottom pb-2 mb-2.5" style="font-size: 0.88rem;">1. Data Permintaan &amp; Pelaksana</h6>
                                    <div class="row g-2 small text-secondary" style="font-size: 0.84rem;">
                                        <div class="col-sm-6">
                                            <span class="text-muted d-block">Perusahaan Pemohon:</span>
                                            <strong class="text-dark font-display"><?= ($order['nama_perusahaan']) ?> (<?= ($order['pt_cv']) ?>)</strong>
                                        </div>
                                        <div class="col-sm-6">
                                            <span class="text-muted d-block">PIC Peneliti Penyusun:</span>
                                            <strong class="text-primary font-display"><i class="bi bi-person-badge me-1"></i> <?= ($order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?: 'Tim Pelaksana OPTI BBSPJIS')) ?></strong>
                                        </div>
                                        <div class="col-sm-12 mt-2">
                                            <span class="text-muted d-block">Judul Proposal Teknis:</span>
                                            <span class="text-dark fw-semibold"><?= ($proposal['judul_proposal'] ?: $order['judul_kegiatan']) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- RUANG LINGKUP & METODOLOGI -->
                                <div class="border rounded-3 p-3.5 mb-3.5 bg-light bg-opacity-40">
                                    <h6 class="fw-bold text-dark font-display border-bottom pb-2 mb-2.5" style="font-size: 0.88rem;">2. Ruang Lingkup &amp; Metodologi Pengujian / Riset</h6>
                                    <div class="text-secondary small" style="font-size: 0.84rem; line-height: 1.7; white-space: pre-line;">
                                        <?= ($proposal['ruang_lingkup'] ?: 'Pengujian parameter mutu, pengamatan karakteristik bahan baku, sampling lapangan, dan formulasi rekomendasi teknologi sesuai standar SNI/ISO/TAPPI terakreditasi ISO/IEC 17025 BBSPJIS.')."
" ?>
                                    </div>
                                </div>

                                <!-- ESTIMASI DURASI & ANGGARAN (RAB) -->
                                <div class="border rounded-3 p-3.5 mb-4 bg-light bg-opacity-40">
                                    <h6 class="fw-bold text-dark font-display border-bottom pb-2 mb-2.5" style="font-size: 0.88rem;">3. Rencana Durasi Pelaksanaan &amp; Estimasi Anggaran (RAB)</h6>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <div class="p-3 bg-white rounded-3 border">
                                                <small class="text-muted d-block mb-1">Estimasi Durasi:</small>
                                                <strong class="text-dark font-monospace fs-6"><?= ($proposal['durasi_kegiatan'] ?: '30 Hari Kerja') ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="p-3 bg-white rounded-3 border">
                                                <small class="text-muted d-block mb-1">Estimasi Total Biaya (RAB):</small>
                                                <strong class="text-primary font-monospace fs-5">
                                                    Rp <?= (number_format((float)($proposal['estimasi_total_biaya'] ?: ($order['estimasi_biaya'] ?: 0)), 0, ',', '.'))."
" ?>
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- KOLOM TANDA TANGAN -->
                                <div class="row pt-3 text-center small text-secondary">
                                    <div class="col-6">
                                        <div>Penyusun Proposal (PIC Peneliti),</div>
                                        <div style="height: 55px;"></div>
                                        <u class="fw-bold text-dark d-block"><?= ($order['pic_proposal_nama'] ?: ($proposal['pic_nama'] ?: 'PIC Peneliti BBSPJIS')) ?></u>
                                        <small class="text-muted">BBSPJIS Kemenperin RI</small>
                                    </div>
                                    <div class="col-6">
                                        <div>Mengetahui &amp; Menyetujui,</div>
                                        <div style="height: 55px;"></div>
                                        <u class="fw-bold text-dark d-block">Ketua Tim OPTI <?= (ucfirst($order['jenis_layanan_opti'])) ?></u>
                                        <small class="text-muted">BBSPJIS Kemenperin RI</small>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- TAB 2: PDF CANVAS VIEWER (PDF.JS ZERO IDM INTERCEPTION) -->
                        <div class="tab-pane fade p-3 text-center" id="tab-pdf-content" role="tabpanel">
                            <!-- Loading Spinner -->
                            <div id="canvasProposalSpinner" class="py-5 my-5">
                                <div class="spinner-border text-primary" role="status" style="width: 2.75rem; height: 2.75rem;"></div>
                                <p class="small text-muted mt-2 fw-medium">Memuat dan merender berkas proposal...</p>
                            </div>

                            <!-- Canvas Element -->
                            <canvas id="proposalPdfCanvas" class="shadow border rounded bg-white mx-auto" style="display: none; max-width: 100%; height: auto;"></canvas>
                        </div>

                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer border-top py-2 px-4 bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <!-- Page & Zoom Controls for Canvas Tab -->
                    <div id="canvasControlsGroup" class="d-flex align-items-center gap-2" style="visibility: hidden;">
                        <button type="button" class="btn btn-light btn-sm border px-2.5 py-1 text-secondary" id="btnProposalPrev" onclick="changeProposalPage(-1)">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span class="small text-dark fw-semibold font-monospace" style="font-size: 0.82rem;">
                            Hal. <span id="proposalCurrentPage">1</span> / <span id="proposalTotalPages">1</span>
                        </span>
                        <button type="button" class="btn btn-light btn-sm border px-2.5 py-1 text-secondary" id="btnProposalNext" onclick="changeProposalPage(1)">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        
                        <div class="vr mx-1.5 my-1"></div>
                        
                        <button type="button" class="btn btn-light btn-sm border px-2 py-1 text-secondary" onclick="zoomProposalPdf(-0.2)" title="Perkecil">
                            <i class="bi bi-zoom-out"></i>
                        </button>
                        <button type="button" class="btn btn-light btn-sm border px-2 py-1 text-secondary" onclick="zoomProposalPdf(0.2)" title="Perbesar">
                            <i class="bi bi-zoom-in"></i>
                        </button>
                    </div>

                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Tutup Pratinjau</button>
                </div>

            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- MODAL PRATINJAU LEMBAR SURAT MASUK RESMI -->
<!-- ========================================== -->
<?php if ($surat_masuk): ?>
    <div class="modal fade" id="modalPreviewSuratMasukProposal" tabindex="-1" aria-labelledby="modalPreviewSuratMasukProposalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border rounded-3 shadow-lg overflow-hidden">
                <div class="modal-header border-bottom py-2.5 px-4 bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary-subtle text-primary p-1.5 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                            <i class="bi bi-file-earmark-text-fill"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark font-display mb-0" id="modalPreviewSuratMasukProposalLabel" style="font-size: 0.92rem;">Lembar Surat Permohonan Klien</h6>
                            <small class="text-secondary font-monospace" style="font-size: 0.72rem;"><?= ($surat_masuk['nomor_surat']) ?> &bull; <?= ($surat_masuk['instansi_pengirim']) ?></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="border rounded-3 p-4 bg-white shadow-xs">
                        <div class="text-center border-bottom pb-3 mb-3">
                            <h6 class="fw-bold text-dark text-uppercase mb-1" style="font-size: 0.95rem;"><?= ($surat_masuk['instansi_pengirim']) ?></h6>
                            <small class="text-muted">Nomor: <?= ($surat_masuk['nomor_surat']) ?> &bull; Tanggal: <?= (date('d F Y', strtotime($surat_masuk['tanggal_surat']))) ?></small>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Perihal:</small>
                            <div class="fw-bold text-dark"><?= ($surat_masuk['perihal']) ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Isi / Ringkasan Permohonan:</small>
                            <div class="p-3 bg-light rounded-3 text-secondary" style="font-size: 0.84rem; line-height: 1.6;">
                                <?= ($surat_masuk['ringkasan'] ?: $surat_masuk['perihal'])."
" ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-4 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- JavaScript Drag-and-Drop, Live Preview & PDF.js Canvas Engine -->
<script>
if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

let selectedFileObj = null;
let activePdfUrl = '';
let currentPdfDoc = null;
let currentPdfPage = 1;
let currentPdfScale = 1.35;

// Drag and drop visual state
const dropzone = document.getElementById('dropzoneContainer');
if (dropzone) {
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            const input = document.getElementById('file_proposal');
            input.files = files;
            handleFileSelect({ target: input });
        }
    }, false);
}

function handleFileSelect(event) {
    const input = event.target;
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    selectedFileObj = file;
    const previewBox = document.getElementById('selectedFilePreview');
    const nameEl = document.getElementById('selectedFileName');
    const sizeEl = document.getElementById('selectedFileSize');
    const iconBox = document.getElementById('fileIconBox');
    const btnPreview = document.getElementById('btnPreviewSelectedPdf');
    const inlineWrapper = document.getElementById('inlinePdfPreviewWrapper');

    if (!previewBox) return;

    // Display filename & size
    nameEl.textContent = file.name;
    const sizeInKb = (file.size / 1024).toFixed(1);
    const sizeInMb = (file.size / (1024 * 1024)).toFixed(2);
    sizeEl.textContent = file.size > 1024 * 1024 ? `${sizeInMb} MB` : `${sizeInKb} KB`;

    const ext = file.name.split('.').pop().toLowerCase();
    if (ext === 'pdf') {
        iconBox.className = 'rounded-3 bg-danger text-white p-2.5 d-flex align-items-center justify-content-center';
        iconBox.innerHTML = '<i class="bi bi-filetype-pdf fs-4"></i>';
        btnPreview.classList.remove('d-none');
    } else if (ext === 'doc' || ext === 'docx') {
        iconBox.className = 'rounded-3 bg-primary text-white p-2.5 d-flex align-items-center justify-content-center';
        iconBox.innerHTML = '<i class="bi bi-filetype-docx fs-4"></i>';
        btnPreview.classList.add('d-none');
        if (inlineWrapper) inlineWrapper.classList.add('d-none');
    } else if (ext === 'xls' || ext === 'xlsx') {
        iconBox.className = 'rounded-3 bg-success text-white p-2.5 d-flex align-items-center justify-content-center';
        iconBox.innerHTML = '<i class="bi bi-filetype-xlsx fs-4"></i>';
        btnPreview.classList.add('d-none');
        if (inlineWrapper) inlineWrapper.classList.add('d-none');
    } else {
        iconBox.className = 'rounded-3 bg-secondary text-white p-2.5 d-flex align-items-center justify-content-center';
        iconBox.innerHTML = '<i class="bi bi-file-earmark-fill fs-4"></i>';
        btnPreview.classList.add('d-none');
        if (inlineWrapper) inlineWrapper.classList.add('d-none');
    }

    previewBox.classList.remove('d-none');
}

function toggleLocalPdfPreview() {
    const inlineWrapper = document.getElementById('inlinePdfPreviewWrapper');
    const canvas = document.getElementById('localPdfCanvas');
    if (!inlineWrapper || !canvas || !selectedFileObj) return;

    if (!inlineWrapper.classList.contains('d-none')) {
        inlineWrapper.classList.add('d-none');
        return;
    }

    inlineWrapper.classList.remove('d-none');

    // Render local PDF ArrayBuffer to canvas (zero download manager triggers)
    selectedFileObj.arrayBuffer().then(function(buffer) {
        return pdfjsLib.getDocument({ data: buffer }).promise;
    }).then(function(pdf) {
        return pdf.getPage(1);
    }).then(function(page) {
        const viewport = page.getViewport({ scale: 1.2 });
        const context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const renderContext = {
            canvasContext: context,
            viewport: viewport
        };
        return page.render(renderContext).promise;
    }).catch(function(err) {
        console.error('Error rendering local PDF:', err);
    });
}

function cancelSelectedFile() {
    const input = document.getElementById('file_proposal');
    const previewBox = document.getElementById('selectedFilePreview');
    const inlineWrapper = document.getElementById('inlinePdfPreviewWrapper');

    if (input) input.value = '';
    if (previewBox) previewBox.classList.add('d-none');
    if (inlineWrapper) inlineWrapper.classList.add('d-none');
    selectedFileObj = null;
}

// ========================================================
// PDF.JS CANVAS VIEWER UNTUK DOKUMEN PROPOSAL TERSIMPAN
// ========================================================
function openProposalCanvasModal(pdfUrl) {
    activePdfUrl = pdfUrl;
    currentPdfDoc = null; // Selalu reset agar memuat file terbaru yang diunggah
    const modalEl = document.getElementById('modalPreviewDokumenProposal');
    if (!modalEl) return;

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();

    // Langsung buka Tab Berkas PDF asli
    const pdfTabBtn = document.getElementById('tab-pdf-btn');
    if (pdfTabBtn) {
        const tabTrigger = new bootstrap.Tab(pdfTabBtn);
        tabTrigger.show();
    }
    const ctrlGroup = document.getElementById('canvasControlsGroup');
    if (ctrlGroup) ctrlGroup.style.visibility = 'visible';

    triggerCanvasPdfRender();
}

function triggerCanvasPdfRender() {
    const ctrlGroup = document.getElementById('canvasControlsGroup');
    if (ctrlGroup) ctrlGroup.style.visibility = 'visible';

    if (currentPdfDoc) return; // Sudah ter-render

    const spinner = document.getElementById('canvasProposalSpinner');
    const canvas = document.getElementById('proposalPdfCanvas');
    if (spinner) spinner.style.display = 'block';
    if (canvas) canvas.style.display = 'none';

    currentPdfPage = 1;
    currentPdfScale = 1.35;

    // Gunakan cache-buster agar selalu merender file fisik yang baru diunggah
    const fetchUrl = activePdfUrl + (activePdfUrl.indexOf('?') === -1 ? '?t=' : '&t=') + Date.now();

    fetch(fetchUrl)
        .then(function(res) {
            if (!res.ok) throw new Error('Gagal mengunduh stream berkas PDF');
            return res.arrayBuffer();
        })
        .then(function(buffer) {
            return pdfjsLib.getDocument({ data: buffer }).promise;
        })
        .then(function(pdf) {
            currentPdfDoc = pdf;
            document.getElementById('proposalTotalPages').textContent = pdf.numPages;
            renderProposalPage(currentPdfPage);
        })
        .catch(function(err) {
            console.error('Error loading PDF:', err);
            if (spinner) {
                spinner.innerHTML = '<div class="text-danger p-4"><i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>Gagal memuat berkas PDF.<br><small class="text-muted">Silakan gunakan tab "Lembar Dokumen" untuk melihat data resmi proposal.</small></div>';
            }
        });
}

function renderProposalPage(pageNum) {
    if (!currentPdfDoc) return;
    const canvas = document.getElementById('proposalPdfCanvas');
    const spinner = document.getElementById('canvasProposalSpinner');
    if (!canvas) return;

    currentPdfDoc.getPage(pageNum).then(function(page) {
        const viewport = page.getViewport({ scale: currentPdfScale });
        const context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const renderContext = {
            canvasContext: context,
            viewport: viewport
        };
        return page.render(renderContext).promise;
    }).then(function() {
        if (spinner) spinner.style.display = 'none';
        canvas.style.display = 'block';
        document.getElementById('proposalCurrentPage').textContent = pageNum;
    });
}

function changeProposalPage(delta) {
    if (!currentPdfDoc) return;
    const newPage = currentPdfPage + delta;
    if (newPage >= 1 && newPage <= currentPdfDoc.numPages) {
        currentPdfPage = newPage;
        renderProposalPage(currentPdfPage);
    }
}

function zoomProposalPdf(delta) {
    if (!currentPdfDoc) return;
    const newScale = currentPdfScale + delta;
    if (newScale >= 0.75 && newScale <= 3.0) {
        currentPdfScale = newScale;
        renderProposalPage(currentPdfPage);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const docTabBtn = document.getElementById('tab-doc-btn');
    const pdfTabBtn = document.getElementById('tab-pdf-btn');
    const ctrlGroup = document.getElementById('canvasControlsGroup');

    if (docTabBtn && ctrlGroup) {
        docTabBtn.addEventListener('shown.bs.tab', function() {
            ctrlGroup.style.visibility = 'hidden';
        });
    }
    if (pdfTabBtn && ctrlGroup) {
        pdfTabBtn.addEventListener('shown.bs.tab', function() {
            ctrlGroup.style.visibility = 'visible';
            triggerCanvasPdfRender();
        });
    }
});
</script>