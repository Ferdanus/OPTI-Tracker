<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-2">
        <h4 class="fw-bold text-dark m-0 font-display">Kaji Ulang Kelayakan &amp; Penunjukan PIC</h4>
        <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="btn btn-light btn-sm text-secondary px-3 py-1.5 fw-medium border">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail Order
        </a>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Informasi Permintaan Masuk (5 Kolom) -->
        <div class="col-lg-5">
            <div class="card border rounded-3 bg-white shadow-xs">
                <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom">
                    <h6 class="m-0 fw-bold text-dark font-display">Data Permintaan Pelanggan</h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="small text-secondary" style="font-size: 0.84rem; line-height: 1.6;">
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 125px; flex-shrink: 0;">No. Order</span>
                            <span class="text-muted me-2">:</span>
                            <strong class="text-dark font-monospace"><?= ($order['nomor_order']) ?></strong>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 125px; flex-shrink: 0;">Bidang Layanan</span>
                            <span class="text-muted me-2">:</span>
                            <span class="badge <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary text-white' : 'bg-success text-white') ?> text-uppercase fw-bold px-2 py-0.5" style="font-size: 0.72rem;">
                                OPTI <?= ($order['jenis_layanan_opti'])."
" ?>
                            </span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 125px; flex-shrink: 0;">Nama Perusahaan</span>
                            <span class="text-muted me-2">:</span>
                            <strong class="text-dark"><?= ($order['nama_perusahaan']) ?> (<?= ($order['pt_cv']) ?>)</strong>
                        </div>
                        <?php if ($order['pic'] && $order['pic'] != '-'): ?>
                            <div class="d-flex mb-2">
                                <span class="text-muted" style="width: 125px; flex-shrink: 0;">PIC Klien</span>
                                <span class="text-muted me-2">:</span>
                                <span class="text-dark"><?= ($order['pic']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['telepon'] && $order['telepon'] != '-'): ?>
                            <div class="d-flex mb-2">
                                <span class="text-muted" style="width: 125px; flex-shrink: 0;">No. HP / Telp</span>
                                <span class="text-muted me-2">:</span>
                                <span class="text-dark"><?= ($order['telepon']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex mb-2">
                            <span class="text-muted" style="width: 125px; flex-shrink: 0;">Perihal</span>
                            <span class="text-muted me-2">:</span>
                            <span class="text-dark fw-semibold"><?= ($order['judul_kegiatan']) ?></span>
                        </div>
                        <div class="d-flex">
                            <span class="text-muted" style="width: 125px; flex-shrink: 0;">Kebutuhan</span>
                            <span class="text-muted me-2">:</span>
                            <span class="text-dark"><?= ($order['deskripsi'] ?: ($order['penjelasan'] ?: 'Kebutuhan pelayanan jasa teknis.')) ?></span>
                        </div>
                    </div>

                    <?php if ($surat_masuk && $surat_masuk['file_path']): ?>
                        <div class="pt-3 mt-3 border-top">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 fw-semibold d-flex align-items-center justify-content-center gap-1.5 shadow-xs" data-bs-toggle="modal" data-bs-target="#modalPreviewSuratMasukTinjauan">
                                <i class="bi bi-file-earmark-text-fill text-danger fs-6"></i> Pratinjau Surat Permohonan Klien
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Form Evaluasi Ringkas Ka. Tim (7 Kolom) -->
        <div class="col-lg-7">
            <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan" method="POST">
                <div class="card border rounded-3 bg-white shadow-xs">
                    <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark font-display">Evaluasi Kelayakan &amp; Penugasan PIC</h6>
                        <?php if (!$can_edit): ?>
                            <span class="badge bg-light text-secondary border"><i class="bi bi-lock-fill me-1"></i> Mode Lihat Saja</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        
                        <?php if (!$can_edit): ?>
                            <div class="alert alert-warning border-0 d-flex align-items-center gap-2 mb-3 py-2 px-3 small rounded-3">
                                <i class="bi bi-lock-fill text-warning fs-5 flex-shrink-0"></i>
                                <div>
                                    <strong>Mode Lihat Saja (Read-Only)</strong>: Kaji ulang kelayakan teknis dan penunjukan PIC merupakan wewenang <strong>Ketua Tim OPTI</strong>. Anda dapat melihat data tetapi tidak dapat mengubah keputusan.
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 1. Kesiapan Sumber Daya (SDM, Alat, Bahan, Metode) -->
                        <!-- 1. Kesiapan Sumber Daya (SDM, Alat, Bahan, Metode) -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-2">Kesiapan Sumber Daya &amp; Teknis:</label>
                            
                            <div class="row g-2 mb-2">
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-light d-flex align-items-center">
                                        <input class="form-check-input mt-0 me-2" type="checkbox" name="sdm_tersedia" id="sdm_tersedia" value="1" <?= (!$tinjauan || $tinjauan['sdm_tersedia'] ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?> onchange="checkReadinessAndLock()">
                                        <label class="form-check-label small fw-semibold text-dark cursor-pointer mb-0" for="sdm_tersedia">
                                            Personil / SDM Analis Siap
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-light d-flex align-items-center">
                                        <input class="form-check-input mt-0 me-2" type="checkbox" name="peralatan_tersedia" id="peralatan_tersedia" value="1" <?= (!$tinjauan || $tinjauan['peralatan_tersedia'] ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?> onchange="checkReadinessAndLock()">
                                        <label class="form-check-label small fw-semibold text-dark cursor-pointer mb-0" for="peralatan_tersedia">
                                            Peralatan &amp; Alat Uji Siap
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-light d-flex align-items-center">
                                        <input class="form-check-input mt-0 me-2" type="checkbox" name="bahan_tersedia" id="bahan_tersedia" value="1" <?= (!$tinjauan || $tinjauan['bahan_tersedia'] ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?> onchange="checkReadinessAndLock()">
                                        <label class="form-check-label small fw-semibold text-dark cursor-pointer mb-0" for="bahan_tersedia">
                                            Bahan Kimia &amp; Reagen Siap
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-light d-flex align-items-center">
                                        <input class="form-check-input mt-0 me-2" type="checkbox" name="metode_tersedia" id="metode_tersedia" value="1" <?= (!$tinjauan || $tinjauan['metode_tersedia'] ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?> onchange="checkReadinessAndLock()">
                                        <label class="form-check-label small fw-semibold text-dark cursor-pointer mb-0" for="metode_tersedia">
                                            Metode Uji Tervalidasi
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Keputusan Kelayakan -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-2">Keputusan Kelayakan Teknis:</label>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <input type="radio" class="btn-check" name="keputusan" id="kep_layak" value="dapat_dilaksanakan" <?= (!$tinjauan || $tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?> onchange="toggleAlasanPenolakan()">
                                    <label class="choice-radio-card card-layak w-100 rounded-3 text-start p-3 d-flex flex-row align-items-center" id="label_card_layak" for="kep_layak" style="cursor: <?= ($can_edit ? 'pointer' : 'default') ?>; min-height: 72px;">
                                        <div class="card-icon-wrap me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                            <i class="bi bi-check-circle-fill fs-4" style="line-height: 1;"></i>
                                        </div>
                                        <div class="card-text-wrap flex-grow-1">
                                            <div class="card-title fw-bold" style="font-size: 0.92rem; line-height: 1.25;">Dapat Dilaksanakan</div>
                                            <div class="card-subtitle small mt-0.5" style="font-size: 0.74rem; line-height: 1.25;">Sanggup dikerjakan</div>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-sm-6">
                                    <input type="radio" class="btn-check" name="keputusan" id="kep_tidak_layak" value="tidak_dapat_dilaksanakan" <?= ($tinjauan && $tinjauan['keputusan'] == 'tidak_dapat_dilaksanakan' ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?> onchange="toggleAlasanPenolakan()">
                                    <label class="choice-radio-card card-tidak-layak w-100 rounded-3 text-start p-3 d-flex flex-row align-items-center" id="label_card_tidak_layak" for="kep_tidak_layak" style="cursor: <?= ($can_edit ? 'pointer' : 'default') ?>; min-height: 72px;">
                                        <div class="card-icon-wrap me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                            <i class="bi bi-x-circle-fill fs-4" style="line-height: 1;"></i>
                                        </div>
                                        <div class="card-text-wrap flex-grow-1">
                                            <div class="card-title fw-bold" style="font-size: 0.92rem; line-height: 1.25;">Tidak Dapat Dilaksanakan</div>
                                            <div class="card-subtitle small mt-0.5" style="font-size: 0.74rem; line-height: 1.25;">Ditolak / tidak sanggup</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div id="noticeUnready" class="small text-danger mt-2" style="display: none;">
                                <i class="bi bi-info-circle me-1"></i> Semua 4 parameter kesiapan (SDM, Alat, Bahan, Metode) harus terisi / siap untuk dapat memilih status <strong>Dapat Dilaksanakan</strong>.
                            </div>
                        </div>

                        <!-- 3. Tunjuk PIC Proposal (Muncul jika Dapat Dilaksanakan) -->
                        <div id="boxPicProposal" class="mb-3" style="display: <?= (!$tinjauan || $tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'block' : 'none') ?>;">
                            <label class="form-label small fw-bold text-dark mb-1">
                                Tunjuk PIC Penyusun Proposal: <span class="text-danger">*</span>
                            </label>
                            <select name="pic_proposal_id" class="form-select form-select-sm" id="selectPicProposal" <?= (!$can_edit ? 'disabled' : '') ?> required>
                                <option value="">-- Pilih PIC Peneliti Pelaksana --</option>
                                <?php foreach (($daftar_pic ?: []?:[]) as $p): ?>
                                    <option value="<?= ($p['id_user']) ?>" <?= (($order['pic_proposal_id'] == $p['id_user']) ? 'selected' : '') ?>>
                                        <?= ($p['nama_user']) ?> <?= ($p['spesialisasi'] ? ' ('.$p['spesialisasi'].')' : '')."
" ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 4. Catatan / Arahan Ka. Tim (Opsional) -->
                        <div id="boxCatatan" class="mb-4" style="display: <?= (!$tinjauan || $tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'block' : 'none') ?>;">
                            <label class="form-label small fw-bold text-dark mb-1">
                                Catatan / Arahan Ka. Tim (Opsional):
                            </label>
                            <input type="text" name="sdm_catatan" class="form-control form-control-sm" placeholder="Contoh: Prioritaskan pengujian sampel sesuai SNI..." value="<?= ($tinjauan['sdm_catatan'] ?? '') ?>" <?= (!$can_edit ? 'disabled' : '') ?>>
                        </div>

                        <!-- 5. Alasan Penolakan (Muncul jika Tidak Dapat Dilaksanakan) -->
                        <div id="boxAlasanPenolakan" class="mb-4" style="display: <?= ($tinjauan && $tinjauan['keputusan'] == 'tidak_dapat_dilaksanakan' ? 'block' : 'none') ?>;">
                            <label class="form-label small fw-bold text-danger mb-1">Alasan Penolakan / Kendala Teknis:</label>
                            <textarea name="alasan_penolakan" class="form-control form-control-sm" rows="3" placeholder="Sebutkan kendala teknis penolakan (misal: alat sedang kalibrasi, reagen habis)..." <?= (!$can_edit ? 'disabled' : '') ?>><?= ($tinjauan['alasan_penolakan'] ?? '') ?></textarea>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="btn btn-light btn-sm text-secondary px-3 py-1.5 border">
                                Kembali
                            </a>
                            <?php if ($can_edit): ?>
                                <button type="submit" class="btn btn-primary btn-sm px-4 py-1.5 fw-semibold shadow-xs">
                                    Simpan &amp; Tugaskan PIC
                                </button>
                            <?php endif; ?>
                            <?php if (!$can_edit): ?>
                                <button type="button" class="btn btn-secondary btn-sm px-4 py-1.5 fw-semibold shadow-xs" disabled>
                                    Terkunci (Wewenang Ka. Tim)
                                </button>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAlasanPenolakan() {
    var radioTidak = document.getElementById('kep_tidak_layak');
    var radioLayak = document.getElementById('kep_layak');
    var boxTolak = document.getElementById('boxAlasanPenolakan');
    var boxPic = document.getElementById('boxPicProposal');
    var boxCatatan = document.getElementById('boxCatatan');
    var selectPic = document.getElementById('selectPicProposal');

    if (radioTidak && radioTidak.checked) {
        if (boxTolak) boxTolak.style.display = 'block';
        if (boxPic) boxPic.style.display = 'none';
        if (boxCatatan) boxCatatan.style.display = 'none';
        if (selectPic) selectPic.removeAttribute('required');
    } else {
        if (boxTolak) boxTolak.style.display = 'none';
        if (boxPic) boxPic.style.display = 'block';
        if (boxCatatan) boxCatatan.style.display = 'block';
        if (selectPic) selectPic.setAttribute('required', 'required');
    }
}

function checkReadinessAndLock() {
    var sdm = document.getElementById('sdm_tersedia');
    var alat = document.getElementById('peralatan_tersedia');
    var bahan = document.getElementById('bahan_tersedia');
    var metode = document.getElementById('metode_tersedia');

    var isAllReady = (sdm && sdm.checked) && 
                     (alat && alat.checked) && 
                     (bahan && bahan.checked) && 
                     (metode && metode.checked);

    var radioLayak = document.getElementById('kep_layak');
    var radioTidak = document.getElementById('kep_tidak_layak');
    var labelLayak = document.getElementById('label_card_layak');
    var notice = document.getElementById('noticeUnready');

    if (!isAllReady) {
        if (radioLayak) {
            radioLayak.disabled = true;
            if (radioLayak.checked) {
                radioLayak.checked = false;
                if (radioTidak) {
                    radioTidak.checked = true;
                }
            }
        }
        if (labelLayak) {
            labelLayak.style.opacity = '0.45';
            labelLayak.style.pointerEvents = 'none';
            labelLayak.style.cursor = 'not-allowed';
            labelLayak.setAttribute('title', 'Terkunci: Semua 4 parameter kesiapan harus siap');
        }
        if (notice) notice.style.display = 'block';
    } else {
        <?php if ($can_edit): ?>
        if (radioLayak) radioLayak.disabled = false;
        if (labelLayak) {
            labelLayak.style.opacity = '1';
            labelLayak.style.pointerEvents = 'auto';
            labelLayak.style.cursor = 'pointer';
            labelLayak.removeAttribute('title');
        }
        <?php endif; ?>
        if (notice) notice.style.display = 'none';
    }

    toggleAlasanPenolakan();
}

document.addEventListener('DOMContentLoaded', function() {
    checkReadinessAndLock();
});
</script>

<style>
.choice-radio-card {
    background-color: #ffffff;
    border: 1.5px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    display: flex;
    align-items: center;
    padding: 12px 16px;
    gap: 12px;
}
.choice-radio-card .card-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    color: #94a3b8;
    transition: all 0.2s ease;
}
.choice-radio-card .card-icon-wrap i {
    font-size: 1.25rem;
    line-height: 1;
    display: block;
}
.choice-radio-card .card-text-wrap {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.choice-radio-card .card-title {
    color: #1e293b;
    font-weight: 700;
    font-size: 0.88rem;
    line-height: 1.25;
}
.choice-radio-card .card-subtitle {
    color: #64748b;
    font-size: 0.72rem;
    line-height: 1.25;
    margin-top: 2px;
}

/* Active State: Dapat Dilaksanakan */
#kep_layak:checked + .card-layak {
    background-color: #f0fdf4 !important;
    border-color: #22c55e !important;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15) !important;
}
#kep_layak:checked + .card-layak .card-icon-wrap {
    color: #16a34a !important;
}
#kep_layak:checked + .card-layak .card-title {
    color: #15803d !important;
}
#kep_layak:checked + .card-layak .card-subtitle {
    color: #166534 !important;
}

/* Active State: Tidak Dapat Dilaksanakan */
#kep_tidak_layak:checked + .card-tidak-layak {
    background-color: #fef2f2 !important;
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
}
#kep_tidak_layak:checked + .card-tidak-layak .card-icon-wrap {
    color: #dc2626 !important;
}
#kep_tidak_layak:checked + .card-tidak-layak .card-title {
    color: #b91c1c !important;
}
#kep_tidak_layak:checked + .card-tidak-layak .card-subtitle {
    color: #991b1b !important;
}
</style>

<!-- MODAL IN-APP PRATINJAU SURAT PERMOHONAN KLIEN -->
<?php if ($surat_masuk): ?>
<div class="modal fade" id="modalPreviewSuratMasukTinjauan" tabindex="-1" aria-labelledby="modalPreviewSuratMasukTinjauanLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            
            <div class="modal-header border-bottom py-2.5 px-4 bg-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle p-2 bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-file-earmark-text-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark m-0 font-display" id="modalPreviewSuratMasukTinjauanLabel">
                            Berkas Surat Permohonan: <?= ($surat_masuk['nomor_surat'])."
" ?>
                        </h6>
                        <small class="text-muted"><?= ($order['nama_perusahaan']) ?> &bull; Tanggal Surat: <?= (date('d M Y', strtotime($surat_masuk['tanggal_surat']))) ?></small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
            </div>

            <div class="modal-body p-4 bg-light" style="min-height: 70vh;">
                <div class="official-sheet mx-auto bg-white p-4 p-md-5 rounded-3 shadow-sm border" style="max-width: 820px; font-family: 'Times New Roman', Times, serif; color: #111; line-height: 1.55;">
                    
                    <!-- KOP SURAT PENGIRIM -->
                    <div class="text-center pb-2">
                        <h4 class="fw-bold mb-0 text-uppercase letter-spacing-1" style="font-size: 1.3rem;"><?= (($surat_masuk['pt_cv'] ? $surat_masuk['pt_cv'] . ' ' : '') . $surat_masuk['pengirim']) ?></h4>
                        <div class="fw-bold small text-muted text-uppercase my-1" style="font-size: 0.75rem; letter-spacing: 1px;">PRODUSEN &amp; JASA INDUSTRI TEKNOLOGI</div>
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
                        <p>Sehubungan dengan rencana pengujian mutu dan optimalisasi proses teknologi industri, bersama ini kami mengajukan permohonan kerjasama pelaksanaan Layanan Optimalisasi Teknologi Industri (OPTI) dengan rincian sebagai berikut:</p>
                        
                        <strong class="d-block mb-1">1. Data Pemohon / Instansi:</strong>
                        <table class="table table-borderless table-sm ms-2 mb-3" style="font-size: 0.925rem; width: 98%;">
                            <tr>
                                <td style="width: 150px; padding: 3px 0;">a. Nama Perusahaan</td>
                                <td style="width: 15px; padding: 3px 0;">:</td>
                                <td style="padding: 3px 0;"><strong><?= (($surat_masuk['pt_cv'] ? $surat_masuk['pt_cv'] . ' ' : '') . $surat_masuk['pengirim']) ?></strong></td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0;">b. Alamat</td>
                                <td style="padding: 3px 0;">:</td>
                                <td style="padding: 3px 0;"><?= ($surat_masuk['alamat_pengirim'] ?: 'Kawasan Industri Terpadu, Indonesia') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0;">c. Narahubung / PIC</td>
                                <td style="padding: 3px 0;">:</td>
                                <td style="padding: 3px 0;"><?= ($surat_masuk['pic_pengirim'] ?: ($order['pic'] ?: 'Penanggung Jawab Teknis')) ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0;">d. Telepon &amp; Email</td>
                                <td style="padding: 3px 0;">:</td>
                                <td style="padding: 3px 0;"><?= ($surat_masuk['no_telp_pengirim'] ?: ($order['telepon'] ?: '-')) ?> &bull; <?= ($surat_masuk['email_pengirim'] ?: ($order['email'] ?: '-')) ?></td>
                            </tr>
                        </table>

                        <strong class="d-block mb-1">2. Rincian Kebutuhan Layanan OPTI:</strong>
                        <table class="table table-borderless table-sm ms-2 mb-3" style="font-size: 0.925rem; width: 98%;">
                            <tr>
                                <td style="width: 150px; padding: 3px 0;">a. Judul Kegiatan</td>
                                <td style="width: 15px; padding: 3px 0;">:</td>
                                <td style="padding: 3px 0;"><strong><?= ($order['judul_kegiatan']) ?></strong></td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0;">b. Layanan Dimohon</td>
                                <td style="padding: 3px 0;">:</td>
                                <td style="padding: 3px 0;">Layanan Optimalisasi Teknologi Industri (OPTI) - Divisi <?= (ucfirst($order['jenis_layanan_opti'])) ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0;">c. Ruang Lingkup</td>
                                <td style="padding: 3px 0;">:</td>
                                <td style="padding: 3px 0;"><?= ($order['deskripsi'] ?: ($order['penjelasan'] ?: 'Kajian Teknis, Karakterisasi Laboratorium & Penerbitan Sertifikat / Laporan Hasil Pengujian Resmi Balai')) ?></td>
                            </tr>
                        </table>

                        <p>Demikian surat permohonan ini kami sampaikan. Kami berharap dapat segera menerima Tinjauan Kelayakan Permintaan serta Surat Penawaran Biaya resmi dari BBSPJIS. Atas perhatian dan kerjasama Bapak/Ibu, kami ucapkan terima kasih.</p>
                    </div>

                    <!-- TANDA TANGAN & STEMPEL -->
                    <div class="row mt-5">
                        <div class="col-6"></div>
                        <div class="col-6 text-center" style="font-size: 0.95rem;">
                            <div>Hormat kami,</div>
                            <strong class="d-block mb-2"><?= (($surat_masuk['pt_cv'] ? $surat_masuk['pt_cv'] . ' ' : '') . $surat_masuk['pengirim']) ?></strong>
                            <div class="border d-inline-block px-3 py-1 my-2 text-muted small" style="border-style: dashed !important; font-size: 0.75rem;">
                                [ TTD &amp; STEMPEL RESMI ]
                            </div>
                            <div class="mt-3">
                                <u class="fw-bold d-block"><?= ($surat_masuk['pic_pengirim'] ?: ($order['pic'] ?: 'Pimpinan Perusahaan')) ?></u>
                                <span class="small text-muted">Direktur</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer border-top py-2 px-4 bg-white d-flex justify-content-between align-items-center">
                <span class="small text-muted font-monospace">
                    <i class="bi bi-shield-check text-success me-1"></i> Berkas Terverifikasi Sistem Informasi Layanan BBSPJIS
                </span>
                <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold rounded-pill" data-bs-dismiss="modal">
                    Tutup Pratinjau
                </button>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>