<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order" class="text-decoration-none">Order Layanan</a></li>
                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="text-decoration-none">#<?= ($order['nomor_order']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tinjauan Kelayakan ISO</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                <i class="bi bi-clipboard-check text-primary"></i>
                Formulir Tinjauan Kelayakan Permintaan (Kartu Kendali ISO)
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Kembali ke Detail Order
            </a>
        </div>
    </div>

    <!-- Alert / Flash Message -->
    <?php if ($SESSION['flash_error']): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= ($SESSION['flash_error'])."
" ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Kolom Ringkasan Order & Surat Asal -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i> Data Permintaan Klien
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="text-muted small d-block">Nomor Order / Permintaan:</span>
                        <strong class="text-primary font-monospace"><?= ($order['nomor_order']) ?></strong>
                        <span class="badge <?= ($order['jenis_layanan_opti'] == 'selulosa' ? 'bg-primary' : 'bg-success') ?> ms-1 text-uppercase">
                            <?= ($order['jenis_layanan_opti'])."
" ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small d-block">Nama Klien / Perusahaan:</span>
                        <div class="fw-bold text-dark"><?= ($order['nama_perusahaan']) ?></div>
                        <small class="text-muted"><?= ($order['pic']) ?> (<?= ($order['telepon']) ?>)</small>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small d-block">Perihal / Judul Kegiatan:</span>
                        <div class="fw-semibold text-dark"><?= ($order['judul_kegiatan']) ?></div>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small d-block">Jenis & Karakteristik Sampel:</span>
                        <div class="small text-dark"><?= ($order['jenis_sampel'] ?: '-') ?> (<?= ($order['volume_berat'] ?: '1 paket') ?>)</div>
                    </div>

                    <?php if ($order['deskripsi']): ?>
                        <div class="mb-0">
                            <span class="text-muted small d-block">Deskripsi / Ruang Lingkup:</span>
                            <div class="small bg-light p-2 rounded text-secondary"><?= ($order['deskripsi']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Form Kartu Kendali ISO -->
        <div class="col-lg-8">
            <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/tinjauan" method="POST">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-card-checklist text-primary"></i> Lembar Verifikasi 4 Parameter Kelayakan
                        </h6>
                        <span class="badge bg-light text-secondary border">Standar ISO 9001 / BBSPJIS</span>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-4">
                            Silakan periksa ketersediaan sumber daya, peralatan instrumen, bahan kimia reagen, dan metode uji sebelum menyetujui pelaksanaan layanan.
                        </p>

                        <!-- Parameter 1: SDM -->
                        <div class="border rounded p-3 mb-3 bg-light-subtle">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-2">
                                <label class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-people-fill text-primary"></i> 1. Ketersediaan Sumber Daya Manusia (SDM / Analis)
                                </label>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" name="sdm_tersedia" id="sdm_tersedia" value="1" <?= (!$tinjauan || $tinjauan['sdm_tersedia'] ? 'checked' : '') ?>>
                                    <label class="form-check-label small fw-semibold" for="sdm_tersedia">Tersedia / Siap</label>
                                </div>
                            </div>
                            <input type="text" name="sdm_catatan" class="form-control form-control-sm bg-white" placeholder="Catatan kesiapan personil / spesialis teknis (opsional)" value="<?= ($tinjauan['sdm_catatan'] ?? '') ?>">
                        </div>

                        <!-- Parameter 2: Peralatan Lab -->
                        <div class="border rounded p-3 mb-3 bg-light-subtle">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-2">
                                <label class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-gear-wide-connected text-primary"></i> 2. Ketersediaan Peralatan & Instrumen Laboratorium
                                </label>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" name="peralatan_tersedia" id="peralatan_tersedia" value="1" <?= (!$tinjauan || $tinjauan['peralatan_tersedia'] ? 'checked' : '') ?>>
                                    <label class="form-check-label small fw-semibold" for="peralatan_tersedia">Tersedia & Siap Operasi</label>
                                </div>
                            </div>
                            <input type="text" name="peralatan_catatan" class="form-control form-control-sm bg-white" placeholder="Catatan kesiapan mesin/alat lab atau kebutuhan kalibrasi (opsional)" value="<?= ($tinjauan['peralatan_catatan'] ?? '') ?>">
                        </div>

                        <!-- Parameter 3: Bahan Kimia & Reagen -->
                        <div class="border rounded p-3 mb-3 bg-light-subtle">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-2">
                                <label class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-eyedropper text-primary"></i> 3. Ketersediaan Bahan Kimia, Reagen & Pendukung
                                </label>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" name="bahan_tersedia" id="bahan_tersedia" value="1" <?= (!$tinjauan || $tinjauan['bahan_tersedia'] ? 'checked' : '') ?>>
                                    <label class="form-check-label small fw-semibold" for="bahan_tersedia">Tersedia & Cukup</label>
                                </div>
                            </div>
                            <input type="text" name="bahan_catatan" class="form-control form-control-sm bg-white" placeholder="Catatan stok reagen/kebutuhan pengadaan bahan kimia (opsional)" value="<?= ($tinjauan['bahan_catatan'] ?? '') ?>">
                        </div>

                        <!-- Parameter 4: Kesiapan Metode -->
                        <div class="border rounded p-3 mb-4 bg-light-subtle">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-2">
                                <label class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-journal-bookmark-fill text-primary"></i> 4. Kesiapan Metode Uji / Prosedur Teknis
                                </label>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" name="metode_tersedia" id="metode_tersedia" value="1" <?= (!$tinjauan || $tinjauan['metode_tersedia'] ? 'checked' : '') ?>>
                                    <label class="form-check-label small fw-semibold" for="metode_tersedia">Metode Tersedia & Tervalidasi</label>
                                </div>
                            </div>
                            <input type="text" name="metode_catatan" class="form-control form-control-sm bg-white" placeholder="Catatan metode standar (SNI/ASTM/ISO/In-House) (opsional)" value="<?= ($tinjauan['metode_catatan'] ?? '') ?>">
                        </div>

                        <!-- Bagian Keputusan Akhir -->
                        <div class="card border-primary-subtle bg-primary-subtle p-3 mb-4">
                            <h6 class="fw-bold text-primary-emphasis mb-2 d-flex align-items-center gap-2">
                                <i class="bi bi-check2-circle"></i> Hasil Keputusan Kelayakan
                            </h6>
                            <div class="d-flex flex-column flex-sm-row gap-4 my-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="keputusan" id="kep_layak" value="dapat_dilaksanakan" <?= (!$tinjauan || $tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'checked' : '') ?> onchange="toggleAlasanPenolakan()">
                                    <label class="form-check-label fw-bold text-success" for="kep_layak">
                                        <i class="bi bi-check-circle-fill me-1"></i> DAPAT DILAKSANAKAN
                                    </label>
                                    <div class="text-muted small">Permohonan disetujui untuk dibuatkan proposal/kalkulasi biaya.</div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="keputusan" id="kep_tidak_layak" value="tidak_dapat_dilaksanakan" <?= ($tinjauan && $tinjauan['keputusan'] == 'tidak_dapat_dilaksanakan' ? 'checked' : '') ?> onchange="toggleAlasanPenolakan()">
                                    <label class="form-check-label fw-bold text-danger" for="kep_tidak_layak">
                                        <i class="bi bi-x-circle-fill me-1"></i> TIDAK DAPAT DILAKSANAKAN
                                    </label>
                                    <div class="text-muted small">Order ditolak dan Tim Mitra menginfokan alasan ke klien.</div>
                                </div>
                            </div>

                            <div id="boxPicProposal" class="mt-3 pt-3 border-top" style="display: <?= (!$tinjauan || $tinjauan['keputusan'] == 'dapat_dilaksanakan' ? 'block' : 'none') ?>;">
                                <label class="form-label small fw-bold text-dark d-flex align-items-center gap-1">
                                    <i class="bi bi-person-badge text-primary"></i> Tunjuk PIC Penyusun Proposal / Rancop: <span class="text-danger">*</span>
                                </label>
                                <select name="pic_proposal_id" class="form-select form-select-sm" id="selectPicProposal">
                                    <option value="">-- Pilih Personil Peneliti / Analis Pelaksana --</option>
                                    <?php foreach (($daftar_pic ?: []?:[]) as $p): ?>
                                        <option value="<?= ($p['id_user']) ?>" <?= (($order['pic_proposal_id'] == $p['id_user']) ? 'selected' : '') ?>>
                                            <?= ($p['nama_user']) ?> <?= ($p['spesialisasi'] ? ' - Spesialisasi: '.$p['spesialisasi'] : '')."
" ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-info-circle text-primary me-1"></i> Personil yang ditunjuk akan bertugas menyusun skenario teknis, biaya, dan mengunggah dokumen proposal ke sistem.
                                </small>
                            </div>

                            <div id="boxAlasanPenolakan" class="mt-3 pt-3 border-top" style="display: <?= ($tinjauan && $tinjauan['keputusan'] == 'tidak_dapat_dilaksanakan' ? 'block' : 'none') ?>;">
                                <label class="form-label small fw-bold text-danger">Alasan Penolakan / Ketidaksanggupan Teknis:</label>
                                <textarea name="alasan_penolakan" class="form-control form-control-sm" rows="3" placeholder="Sebutkan kendala teknis (misal: alat sedang maintenance, reagen impor belum tiba, kapasitas lab penuh)..."><?= ($tinjauan['alasan_penolakan'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- Tombol Aksi -->
                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="btn btn-outline-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2 fw-bold">
                                <i class="bi bi-save"></i> Simpan Evaluasi &amp; Tugaskan PIC
                            </button>
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
    if (boxTolak) boxTolak.style.display = radioTidak.checked ? 'block' : 'none';
    if (boxPic) boxPic.style.display = radioLayak.checked ? 'block' : 'none';
}
</script>