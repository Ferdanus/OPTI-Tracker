<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order" class="text-decoration-none">Order Layanan</a></li>
                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="text-decoration-none">#<?= ($order['nomor_order']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rancangan Percobaan (Rancop) Selulosa</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2 font-display">
                <i class="bi bi-diagram-3-fill text-primary"></i>
                Rancangan Percobaan (Rancop) & Anggaran Riset Selulosa
            </h4>
            <p class="text-muted small m-0 mt-1">Susun skenario tahapan eksperimen, RAB dinamis, log negosiasi, dan status kesepakatan riset kustom.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-arrow-left"></i> Kembali ke Detail Order
            </a>
        </div>
    </div>

    <?php if (!$can_edit): ?>
        <div class="alert alert-warning border-0 d-flex align-items-center gap-2 mb-4 py-2 px-3 small rounded-3">
            <i class="bi bi-lock-fill text-warning fs-5 flex-shrink-0"></i>
            <div>
                <strong>Mode Lihat Saja (Read-Only)</strong>: Penyusunan Rancangan Percobaan (Rancop) & Anggaran Riset merupakan wewenang <strong>PIC Proposal</strong> yang ditunjuk.
            </div>
        </div>
    <?php endif; ?>

    <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/rancop-selulosa" method="POST" id="formRancop">
        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

        <div class="row g-4">
            <!-- Kolom Kiri: Profil Permohonan & Status Kesepakatan (4 Kolom) -->
            <div class="col-lg-4">
                
                <!-- Card Status Kesepakatan (Deal Switcher) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-handshake-fill text-primary"></i> Status Kesepakatan Riset
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <label class="form-label small fw-bold text-dark mb-2">Status Progres Rancop:</label>
                        <select name="status_rancop" id="statusRancopSelect" class="form-select form-select-sm mb-3 fw-semibold" onchange="updateStatusBadge(this.value)">
                            <option value="draft" <?= ($order['status_rancop'] == 'draft' ? 'selected' : '') ?>>Draft (Penyusunan Awal)</option>
                            <option value="diskusi" <?= ($order['status_rancop'] == 'diskusi' ? 'selected' : '') ?>>Diskusi / Negosiasi Klien</option>
                            <option value="deal" <?= ($order['status_rancop'] == 'deal' ? 'selected' : '') ?>>Deal / Disetujui (Siap Penawaran)</option>
                            <option value="batal" <?= ($order['status_rancop'] == 'batal' ? 'selected' : '') ?>>Batal / Tidak Berlanjut</option>
                        </select>

                        <div id="statusInfoBox" class="p-3 rounded-2 small mb-3 bg-light border d-flex align-items-start gap-2">
                            <span id="statusDotIndicator" class="rounded-circle mt-1 flex-shrink-0" style="width: 10px; height: 10px; display: inline-block;"></span>
                            <div>
                                <strong class="d-block text-dark mb-0" id="statusHeading">Draft</strong>
                                <span id="statusInfoText" class="text-muted">Tahap awal penyusunan rancangan percobaan oleh tim teknis.</span>
                            </div>
                        </div>

                        <!-- Ringkasan Kalkulasi Real-time -->
                        <div class="p-3 rounded-3 border" style="background: #fdf2f8; border-color: #fbcfe8 !important;">
                            <span class="text-muted small d-block mb-1">Total Estimasi Anggaran Deal:</span>
                            <h3 class="fw-bold mb-0 font-monospace" style="color: var(--color-primary);" id="displayTotalAnggaran">
                                Rp <?= (number_format($order['estimasi_biaya'] ?: 0, 0, ',', '.'))."
" ?>
                            </h3>
                            <input type="hidden" name="estimasi_total_biaya" id="inputTotalAnggaran" value="<?= ($order['estimasi_biaya'] ?: 0) ?>">
                            <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">*Hanya menjumlahkan tahapan yang diaktifkan (ON).</small>
                        </div>
                    </div>
                </div>

                <!-- Card Ringkasan Permohonan & Klien -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-primary"></i> Data Permohonan & Klien
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-2">
                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Nomor Order:</span>
                            <strong class="text-primary font-monospace"><?= ($order['nomor_order']) ?></strong>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Instansi / Perusahaan:</span>
                            <span class="fw-bold text-dark"><?= ($order['nama_perusahaan']) ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Judul Kegiatan Permohonan:</span>
                            <span class="text-dark small fw-semibold"><?= ($order['judul_kegiatan']) ?></span>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Jenis Sampel / Bahan Baku:</span>
                            <span class="badge bg-light text-dark border"><?= ($order['jenis_sampel'] ?: 'Bahan Selulosa / Pulp / Kertas') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Card Log Diskusi / Catatan Meeting Klien (Zoom / Telepon) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-chat-left-text-fill text-primary"></i> Log Diskusi & Catatan Klien
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <label class="form-label small fw-bold text-dark mb-1">Hasil Zoom / Diskusi Teknis:</label>
                        <textarea name="log_diskusi_klien" class="form-control form-control-sm" rows="5" placeholder="Contoh:&#10;- 28 Agu: Meeting Zoom dengan PIC. Klien sepakat 2 batch pemasakan.&#10;- Dosis H2O2 ditetapkan 2%.&#10;- Uji SEM eksternal ditunda ke tahap 2."><?= ($order['log_diskusi_klien']) ?></textarea>
                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Catat perubahan parameter, kesepakatan dosis, atau hasil negosiasi harga.</small>
                    </div>
                </div>

            </div>

            <!-- Kolom Kanan: Penyusunan Tahapan Eksperimen & RAB Dinamis (8 Kolom) -->
            <div class="col-lg-8">
                
                <!-- Card Informasi Tim Peneliti (Hierarki PI) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-people-fill text-primary"></i> Tim Peneliti & Spesialisasi Teknis
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border">Divisi OPTI Selulosa</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label small fw-bold text-dark">
                                    Koordinator / Peneliti Utama (PI Utama): <span class="text-danger">*</span>
                                </label>
                                <select name="pic_penyusun_id" class="form-select form-select-sm" required onchange="autoFillSpesialisasi(this)">
                                    <option value="">Pilih Peneliti Utama / Koordinator</option>
                                    <?php foreach (($daftar_pic?:[]) as $p): ?>
                                        <option value="<?= ($p['id_user']) ?>" data-spesialisasi="<?= ($p['spesialisasi'] ?? '') ?>" <?= (($proposal && $proposal['pic_penyusun_id'] == $p['id_user']) || ($order['pic_proposal_id'] == $p['id_user']) ? 'selected' : '') ?>>
                                            <?= ($p['nama_user']) ?> (<?= ($p['spesialisasi'] ?: $p['role_opti']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-dark">Bidang Lab / Spesialisasi:</label>
                                <input type="text" name="spesialisasi" id="spesialisasiInput" class="form-control form-control-sm bg-light" placeholder="Misal: Pemasakan Pulp & Derivat" value="<?= ($proposal['spesialisasi'] ?? '') ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-dark">Judul Lengkap Proposal / Riset:</label>
                                <input type="text" name="judul_proposal" class="form-control form-control-sm" placeholder="Judul lengkap proposal teknis riset..." value="<?= ($proposal['judul_proposal'] ?: $order['judul_kegiatan']) ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Multi-Tahap Eksperimen (Rancop Builder) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-layers-fill text-primary"></i> Tahapan Eksperimen & Komponen Biaya (Rancop)
                            </h6>
                            <small class="text-muted">Susun tahap pengujian. Centang ON/OFF jika klien ingin menyesuaikan tahapan sesuai anggaran.</small>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm px-3 fw-semibold shadow-sm d-flex align-items-center gap-1" onclick="tambahTahapRiset()">
                            <i class="bi bi-plus-circle-fill"></i> Tambah Tahap Uji
                        </button>
                    </div>
                    
                    <div class="card-body p-3" id="containerTahapanRiset">
                        <!-- Container untuk kartu-kartu tahapan riset dinamis -->
                    </div>

                    <div class="card-footer bg-light p-3 border-top d-flex justify-content-between align-items-center">
                        <span class="small text-muted">
                            <i class="bi bi-calculator me-1"></i> Subtotal seluruh tahapan aktif dihitung otomatis.
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="tambahTahapRiset()">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Skenario Lain
                        </button>
                    </div>
                </div>

                <!-- Card Tombol Aksi Submit -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center">
                        <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="btn btn-outline-secondary btn-sm px-3">
                            <i class="bi bi-x-circle me-1"></i> Batal & Kembali
                        </a>
                        <?php if ($can_edit): ?>
                            <div class="d-flex gap-2">
                                <button type="submit" name="action_btn" value="save_draft" class="btn btn-outline-primary btn-sm px-4 fw-semibold">
                                    <i class="bi bi-save me-1"></i> Simpan Draf Rancop
                                </button>
                                <button type="submit" name="action_btn" value="save_deal" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" onclick="return confirmDeal();">
                                    <i class="bi bi-check-circle-fill me-1"></i> Simpan & Kunci Kesepakatan (Deal)
                                </button>
                            </div>
                        <?php endif; ?>
                        <?php if (!$can_edit): ?>
                            <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" disabled>
                                <i class="bi bi-lock-fill me-1"></i> Terkunci (Wewenang PIC Proposal)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<!-- Initial Data JSON untuk Tahapan Riset -->
<script>
var initialStages = <?= ($this->raw(!empty($order['tahapan_riset_json']) ? $order['tahapan_riset_json'] : '[]')) ?>;

// Default fallback jika belum ada data tahapan
if (!initialStages || initialStages.length === 0) {
    initialStages = [
        {
            nama: "Tahap 1: Uji Pendahuluan & Karakterisasi Lab Awal",
            keterangan: "Preparasi bahan baku, analisis komponen kimia & penentuan baseline.",
            biaya: <?= ($order['estimasi_biaya'] > 0 ? (int)($order['estimasi_biaya'] * 0.4) : 5000000) ?>,
            is_active: true
        },
        {
            nama: "Tahap 2: Optimasi Proses Pemasakan & Pemutihan",
            keterangan: "Eksperimen variasi dosis kimia, suhu, dan yield serat selulosa.",
            biaya: <?= ($order['estimasi_biaya'] > 0 ? (int)($order['estimasi_biaya'] * 0.6) : 7500000) ?>,
            is_active: true
        }
    ];
}

var stageIndex = 0;

function renderStages() {
    var container = document.getElementById('containerTahapanRiset');
    container.innerHTML = '';

    initialStages.forEach(function(stg, idx) {
        addStageCardToDOM(stg.nama, stg.keterangan, stg.biaya, stg.is_active);
    });

    hitungTotalAnggaran();
}

function addStageCardToDOM(nama, keterangan, biaya, isActive) {
    var container = document.getElementById('containerTahapanRiset');
    var i = stageIndex++;

    var activeChecked = (isActive !== false) ? 'checked' : '';
    var cardOpacity = (isActive !== false) ? '' : 'opacity-75 bg-light';

    var cardHtml = `
    <div class="card border rounded-3 mb-3 shadow-none stage-card ${cardOpacity}" id="stageCard_${i}" style="border-color: #e2e8f0;">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary text-white stage-number-badge">Tahap</span>
                <span class="fw-bold text-dark small stage-title-display">${nama || 'Tahap Eksperimen'}</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="form-check form-switch m-0 d-flex align-items-center gap-1">
                    <input class="form-check-input stage-toggle" type="checkbox" name="tahapan[${i}][is_active]" id="toggleStage_${i}" value="1" ${activeChecked} onchange="toggleStageActive(${i})">
                    <label class="form-check-label small fw-semibold text-muted" for="toggleStage_${i}">Aktif (Deal)</label>
                </div>
                <button type="button" class="btn btn-outline-danger btn-xs py-0 px-2" onclick="hapusTahapRiset(${i})" title="Hapus Tahapan">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold text-dark mb-1">Nama Skenario / Tahap:</label>
                    <input type="text" name="tahapan[${i}][nama]" class="form-control form-control-sm stage-nama-input" value="${escapeHtml(nama)}" placeholder="Misal: Optimasi Pemasakan Pulp" required oninput="updateStageTitle(${i})">
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold text-dark mb-1">Estimasi Biaya Tahap Ini (Rp):</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light">Rp</span>
                        <input type="number" name="tahapan[${i}][biaya]" class="form-control form-control-sm text-end fw-bold stage-biaya-input" value="${biaya}" min="0" step="1000" oninput="hitungTotalAnggaran()">
                    </div>
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label small text-muted mb-1">Rincian Ruang Lingkup & Metodologi Lab:</label>
                    <textarea name="tahapan[${i}][keterangan]" class="form-control form-control-sm" rows="2" placeholder="Rincian uji komponen kimia, dosis reagen, jumlah sampel, alat lab yang digunakan...">${escapeHtml(keterangan)}</textarea>
                </div>
            </div>
        </div>
    </div>
    `;

    container.insertAdjacentHTML('beforeend', cardHtml);
    renumberStages();
}

function tambahTahapRiset() {
    var count = document.querySelectorAll('.stage-card').length + 1;
    addStageCardToDOM("Tahap " + count + ": Eksperimen Lanjutan", "Uji coba lanjutan sesuai kesepakatan dengan klien.", 5000000, true);
    hitungTotalAnggaran();
}

function hapusTahapRiset(idx) {
    var card = document.getElementById('stageCard_' + idx);
    if (card) {
        card.remove();
        renumberStages();
        hitungTotalAnggaran();
    }
}

function toggleStageActive(idx) {
    var toggle = document.getElementById('toggleStage_' + idx);
    var card = document.getElementById('stageCard_' + idx);
    if (toggle && card) {
        if (toggle.checked) {
            card.classList.remove('opacity-75', 'bg-light');
        } else {
            card.classList.add('opacity-75', 'bg-light');
        }
    }
    hitungTotalAnggaran();
}

function updateStageTitle(idx) {
    var card = document.getElementById('stageCard_' + idx);
    if (card) {
        var input = card.querySelector('.stage-nama-input');
        var display = card.querySelector('.stage-title-display');
        if (input && display) {
            display.textContent = input.value || 'Tahap Eksperimen';
        }
    }
}

function renumberStages() {
    var cards = document.querySelectorAll('.stage-card');
    cards.forEach(function(card, idx) {
        var badge = card.querySelector('.stage-number-badge');
        if (badge) {
            badge.textContent = "Tahap " + (idx + 1);
        }
    });
}

function hitungTotalAnggaran() {
    var total = 0;
    var cards = document.querySelectorAll('.stage-card');
    
    cards.forEach(function(card) {
        var toggle = card.querySelector('.stage-toggle');
        var biayaInput = card.querySelector('.stage-biaya-input');
        if (toggle && toggle.checked && biayaInput) {
            var val = parseFloat(biayaInput.value) || 0;
            total += val;
        }
    });

    document.getElementById('inputTotalAnggaran').value = total;
    document.getElementById('displayTotalAnggaran').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

function updateStatusBadge(val) {
    var infoText = document.getElementById('statusInfoText');
    var heading = document.getElementById('statusHeading');
    var dot = document.getElementById('statusDotIndicator');
    
    if (dot) {
        dot.className = 'rounded-circle mt-1 flex-shrink-0';
    }

    if (val === 'draft') {
        if (heading) heading.textContent = 'Draft';
        if (dot) dot.classList.add('bg-warning');
        if (infoText) infoText.textContent = 'Tahap awal penyusunan rancangan percobaan oleh tim teknis.';
    } else if (val === 'diskusi') {
        if (heading) heading.textContent = 'Sedang Diskusi / Negosiasi';
        if (dot) dot.classList.add('bg-primary');
        if (infoText) infoText.textContent = 'Sedang dalam sesi diskusi / negosiasi teknis (Zoom/telepon) bersama klien.';
    } else if (val === 'deal') {
        if (heading) heading.textContent = 'Deal / Disetujui';
        if (dot) dot.classList.add('bg-success');
        if (infoText) infoText.textContent = 'Kesepakatan tercapai! Anggaran disetujui dan siap diterbitkan Surat Penawaran Resmi.';
    } else if (val === 'batal') {
        if (heading) heading.textContent = 'Batal / Tidak Berlanjut';
        if (dot) dot.classList.add('bg-danger');
        if (infoText) infoText.textContent = 'Klien batal melanjutkan permohonan riset (diarsipkan rapi).';
    }
}

function confirmDeal() {
    var select = document.getElementById('statusRancopSelect');
    if (select) {
        select.value = 'deal';
        updateStatusBadge('deal');
    }
    return true;
}

function autoFillSpesialisasi(selectEl) {
    var opt = selectEl.options[selectEl.selectedIndex];
    var spec = opt.getAttribute('data-spesialisasi') || '';
    var inputSpec = document.getElementById('spesialisasiInput');
    if (inputSpec && spec) {
        inputSpec.value = spec;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', function() {
    renderStages();
    updateStatusBadge(document.getElementById('statusRancopSelect').value);
});
</script>
