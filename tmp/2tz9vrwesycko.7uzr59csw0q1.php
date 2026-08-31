<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/surat-penawaran" class="text-decoration-none">Surat Penawaran</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= ($sp['id'] ? 'Edit Penawaran #' . $sp['nomor_surat'] : 'Buat Penawaran Baru') ?></li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2 font-display">
            <i class="bi bi-file-earmark-text text-primary"></i> Formulir Surat Penawaran Layanan OPTI
        </h4>
        <p class="text-muted small mb-0">Lengkapi formulir penawaran resmi di bawah. Dokumen akan tersinkronisasi secara langsung (*real-time*) pada lembar pratinjau di sebelah kanan.</p>
    </div>
    <a href="<?= ($BASE) ?>/surat-penawaran" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
    </a>
</div>

<style>
    .panel-container {
        display: grid;
        grid-template-columns: 1.15fr 1fr;
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 1199.98px) {
        .panel-container {
            grid-template-columns: 1fr;
        }
    }
    .panel-form {
        background: #ffffff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
    }
    .panel-head {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--color-border-subtle);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .panel-body {
        padding: 1.25rem;
    }
    .form-group-fieldset {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        background-color: #f8fafc;
    }
    .form-group-fieldset legend {
        font-size: 0.825rem;
        font-weight: 700;
        color: var(--color-primary);
        padding: 0 8px;
        float: none;
        width: auto;
        margin-bottom: 0;
    }
    .opt-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 12px;
    }
    .opt-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.825rem;
        font-weight: 500;
        color: #334155;
        padding: 8px 10px;
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-sm);
        background: #ffffff;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .opt-label:hover {
        background: #f1f5f9;
        border-color: var(--color-primary);
    }
    .opt-label input[type="radio"] {
        margin: 0;
        accent-color: var(--color-primary);
    }
    .sub-field {
        display: none;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed #cbd5e1;
    }
    .sub-field.show {
        display: block;
    }

    /* Choice Tile for OPTI Division */
    .opti-choice-tile {
        border: 2px solid #cbd5e1;
        border-radius: var(--radius-md);
        padding: 12px 14px;
        background: #ffffff;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .opti-choice-tile:hover {
        border-color: var(--color-primary);
        transform: translateY(-1px);
    }
    .opti-choice-tile.selected-selulosa {
        border-color: var(--color-primary) !important;
        background: #fdf2f8 !important;
    }
    .opti-choice-tile.selected-lingkungan {
        border-color: #059669 !important;
        background: #f0fdf4 !important;
    }
    
    /* Document Preview Styles */
    .doc-preview-panel {
        background: #334155;
        border-radius: var(--radius-lg);
        padding: 20px;
        position: sticky;
        top: 85px;
    }
    .doc-page {
        background: #ffffff;
        border-radius: 4px;
        padding: 35px 35px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        font-family: 'Times New Roman', Times, serif;
        font-size: 0.85rem;
        color: #111;
        line-height: 1.45;
    }
    .doc-header-kop {
        text-align: center;
        border-bottom: 2px solid #000;
        padding-bottom: 6px;
        margin-bottom: 2px;
    }
    .doc-header-subkop {
        border-bottom: 1px solid #000;
        margin-bottom: 15px;
    }
</style>

<div class="panel-container">

    <!-- Form Section -->
    <div class="panel-form">
        <div class="panel-head">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Isian Surat Penawaran Resmi</h6>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="statusBadge"><?= (strtoupper($sp['status_respon_klien'] ?: ($sp['status'] ?: 'DRAFT'))) ?></span>
        </div>
        <div class="panel-body">
            <form id="spForm" action="<?= ($sp['id'] ? $BASE.'/surat-penawaran/'.$sp['id'].'/update' : $BASE.'/surat-penawaran/simpan') ?>" method="POST">
                <input type="hidden" name="action" id="formAction" value="simpan">
                <input type="hidden" name="jenis_layanan" id="inputJenisLayanan" value="<?= ($sp['jenis_layanan'] ?: 'selulosa') ?>">

                <!-- 1. Identitas Klien -->
                <fieldset class="form-group-fieldset">
                    <legend>1. Identitas Klien / Pelanggan</legend>
                    
                    <div class="mb-2">
                        <label class="form-label" for="f_nama">Nama Kontak (PIC) <span class="text-danger">*</span></label>
                        <input type="text" id="f_nama" name="nama" class="form-control form-control-sm" placeholder="Nama PIC klien..." value="<?= ($sp['nama']) ?>" required>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label" for="f_perusahaan">Nama Perusahaan / Instansi <span class="text-danger">*</span></label>
                        <input type="text" id="f_perusahaan" name="perusahaan" class="form-control form-control-sm fw-semibold" placeholder="Nama perusahaan..." value="<?= ($sp['perusahaan']) ?>" required>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label" for="f_alamat">Alamat Lengkap</label>
                        <textarea id="f_alamat" name="alamat" class="form-control form-control-sm" rows="2" placeholder="Alamat pabrik / kantor klien..." required><?= ($sp['alamat']) ?></textarea>
                    </div>
                </fieldset>

                <!-- 2. Divisi Layanan OPTI (Fokus Dual Workflow) -->
                <fieldset class="form-group-fieldset">
                    <legend>2. Divisi Layanan OPTI Yang Ditawarkan</legend>
                    
                    <div class="row g-2 mb-2">
                        <div class="col-sm-6">
                            <div class="opti-choice-tile <?= ((!$sp['jenis_layanan'] || $sp['jenis_layanan'] == 'selulosa') ? 'selected-selulosa' : '') ?>" id="tileSelulosa" onclick="pilihDivisiPenawaran('selulosa')">
                                <input type="radio" name="opt_divisi" id="radioSelulosa" value="selulosa" <?= ((!$sp['jenis_layanan'] || $sp['jenis_layanan'] == 'selulosa') ? 'checked' : '') ?> class="d-none">
                                <i class="bi bi-diagram-3-fill text-primary fs-4"></i>
                                <div>
                                    <strong class="d-block text-dark small">OPTI Selulosa</strong>
                                    <small class="text-muted" style="font-size: 0.725rem;">Riset & Skenario Rancop</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="opti-choice-tile <?= (($sp['jenis_layanan'] == 'lingkungan') ? 'selected-lingkungan' : '') ?>" id="tileLingkungan" onclick="pilihDivisiPenawaran('lingkungan')">
                                <input type="radio" name="opt_divisi" id="radioLingkungan" value="lingkungan" <?= (($sp['jenis_layanan'] == 'lingkungan') ? 'checked' : '') ?> class="d-none">
                                <i class="bi bi-water text-success fs-4"></i>
                                <div>
                                    <strong class="d-block text-dark small">OPTI Lingkungan</strong>
                                    <small class="text-muted" style="font-size: 0.725rem;">Pengujian Mutu Lab & LPV</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- 3. Rincian Penawaran & Nominal Biaya -->
                <fieldset class="form-group-fieldset">
                    <legend>3. Rincian Penawaran & Nominal Biaya</legend>
                    
                    <div class="row g-2 mb-2">
                        <div class="col-md-7">
                            <label class="form-label" for="f_nomor">Nomor Surat Penawaran</label>
                            <input type="text" id="f_nomor" name="nomor_surat" class="form-control form-control-sm font-monospace fw-bold" placeholder="01/SP/BBSPJIS/VIII/2026" value="<?= ($sp['nomor_surat']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="f_tanggal">Tanggal Surat</label>
                            <input type="date" id="f_tanggal" name="tanggal_surat" class="form-control form-control-sm" value="<?= ($sp['tanggal_surat'] ?: date('Y-m-d')) ?>">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="f_perihal">Perihal Penawaran</label>
                        <input type="text" id="f_perihal" name="perihal" class="form-control form-control-sm" placeholder="Penawaran Layanan Jasa OPTI..." value="<?= ($sp['perihal'] ?: 'Penawaran Kerjasama Layanan Optimalisasi Teknologi Industri (OPTI)') ?>" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="f_nominal">
                            <i class="bi bi-cash-coin text-success me-1"></i> Total Nilai Penawaran (Rp) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light fw-bold">Rp</span>
                            <input type="number" id="f_nominal" name="nominal_penawaran" class="form-control form-control-sm text-end fw-bold" placeholder="0" value="<?= ($sp['nominal_penawaran'] ?: 0) ?>" required min="0" step="1000">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="f_penjelasan">Penjelasan / Rincian Tambahan</label>
                        <textarea id="f_penjelasan" name="penjelasan" class="form-control form-control-sm" rows="3" placeholder="Catatan ruang lingkup, durasi pengerjaan, atau ketentuan termin pembayaran..."><?= ($sp['penjelasan']) ?></textarea>
                    </div>
                </fieldset>

                <!-- 4. Saluran Permintaan & Status Respon -->
                <fieldset class="form-group-fieldset">
                    <legend>4. Saluran Permintaan & Status Klien</legend>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Saluran Permintaan:</label>
                        <div class="opt-grid">
                            <label class="opt-label">
                                <input type="radio" name="permintaan_melalui" value="email" <?= (($sp['permintaan_melalui'] == 'email' || !$sp['permintaan_melalui']) ? 'checked' : '') ?>>
                                <span>E-mail Resmi</span>
                            </label>
                            <label class="opt-label">
                                <input type="radio" name="permintaan_melalui" value="surat" <?= ($sp['permintaan_melalui'] == 'surat' ? 'checked' : '') ?>>
                                <span>Surat Fisik</span>
                            </label>
                            <label class="opt-label">
                                <input type="radio" name="permintaan_melalui" value="telepon" <?= ($sp['permintaan_melalui'] == 'telepon' ? 'checked' : '') ?>>
                                <span>Telepon / WA</span>
                            </label>
                            <label class="opt-label">
                                <input type="radio" name="permintaan_melalui" value="datang_langsung" <?= ($sp['permintaan_melalui'] == 'datang_langsung' ? 'checked' : '') ?>>
                                <span>Datang Langsung</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-dark">Status Respon Klien:</label>
                        <select name="status_respon_klien" id="f_status" class="form-select form-select-sm">
                            <option value="draft" <?= ((!$sp['status_respon_klien'] || $sp['status_respon_klien'] == 'draft') ? 'selected' : '') ?>>Draft (Penyusunan Internal)</option>
                            <option value="terkirim" <?= ($sp['status_respon_klien'] == 'terkirim' ? 'selected' : '') ?>>Terkirim ke Klien (Menunggu Respon)</option>
                            <option value="nego" <?= ($sp['status_respon_klien'] == 'nego' ? 'selected' : '') ?>>Negosiasi Tarif / Lingkup</option>
                            <option value="deal" <?= ($sp['status_respon_klien'] == 'deal' ? 'selected' : '') ?>>Disetujui Klien (DEAL)</option>
                            <option value="batal" <?= ($sp['status_respon_klien'] == 'batal' ? 'selected' : '') ?>>Batal / Ditolak Klien</option>
                        </select>
                    </div>
                </fieldset>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= ($BASE) ?>/surat-penawaran" class="btn btn-outline-secondary btn-sm px-3">Batal</a>
                    <button type="button" class="btn btn-outline-primary btn-sm px-3" id="btnSimpan">
                        <i class="bi bi-save me-1"></i> Simpan Draft
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" id="btnKirim">
                        <i class="bi bi-send-fill me-1"></i> Terbitkan Penawaran
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Live Document Preview Section -->
    <div class="doc-preview-panel">
        <div class="d-flex justify-content-between align-items-center text-white px-0 pb-3 mb-2 border-bottom border-secondary">
            <h6 class="m-0 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-text text-danger"></i> Live Preview Penawaran Resmi
            </h6>
            <span class="badge bg-success small"><i class="bi bi-broadcast me-1"></i> Real-time Sync</span>
        </div>
        
        <div class="doc-page" id="docPage">
            <!-- Kop Surat BBSPJIS -->
            <div class="doc-header-kop">
                <div style="font-size: 0.725rem; font-weight: bold; letter-spacing: 0.5px;">KEMENTERIAN PERINDUSTRIAN REPUBLIK INDONESIA</div>
                <div style="font-size: 0.725rem; font-weight: bold; letter-spacing: 0.5px;">BADAN STANDARDISASI DAN KEBIJAKAN JASA INDUSTRI</div>
                <div style="font-size: 0.85rem; font-weight: bold; margin: 2px 0;">BALAI BESAR STANDARDISASI DAN PELAYANAN JASA INDUSTRI SELULOSA</div>
                <div style="font-size: 0.675rem; color: #444;">Jl. Raya Dayeuhkolot No. 132 Bandung 40258 Telp. (022) 5202871 Fax. (022) 5202872</div>
            </div>
            <div class="doc-header-subkop"></div>

            <!-- Metadata Surat -->
            <div class="d-flex justify-content-between align-items-start mb-3" style="font-size: 0.825rem;">
                <div>
                    <div>Nomor &nbsp; : <strong id="prevNomor">-</strong></div>
                    <div>Lampiran: 1 (satu) berkas rincian biaya</div>
                    <div>Hal &nbsp; &nbsp; &nbsp; &nbsp;: <strong id="prevPerihal">-</strong></div>
                </div>
                <div id="prevTanggal">
                    Bandung, -
                </div>
            </div>

            <!-- Tujuan Surat -->
            <div class="mb-3" style="font-size: 0.825rem;">
                <div>Kepada Yth.</div>
                <strong id="prevNama">-</strong><br>
                <strong id="prevPerusahaan">-</strong><br>
                <span id="prevAlamat" style="color: #444;">-</span>
            </div>

            <!-- Isi Surat -->
            <div style="text-align: justify; font-size: 0.825rem; margin-bottom: 15px;">
                <p style="margin-bottom: 6px;">Dengan hormat,</p>
                <p style="margin-bottom: 8px;">Menindaklanjuti permohonan kerjasama pelaksanaan Layanan Optimalisasi Teknologi Industri (OPTI), bersama ini kami sampaikan penawaran biaya pelaksanaan pada divisi layanan:</p>
                
                <div class="p-2 border rounded my-2 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <span class="small text-muted d-block">Divisi Layanan:</span>
                        <strong id="prevDivisi">OPTI Selulosa (Riset & Skenario Rancop)</strong>
                    </div>
                    <div class="text-end">
                        <span class="small text-muted d-block">Total Nilai Penawaran (PNBP):</span>
                        <h6 class="fw-bold text-dark m-0" id="prevNominal">Rp 0</h6>
                    </div>
                </div>

                <div id="prevPenjelasanBox" style="margin-top: 10px; display: none;">
                    <strong>Catatan Ruang Lingkup:</strong>
                    <p id="prevPenjelasan" style="margin-top: 2px; color: #333; white-space: pre-line;">-</p>
                </div>

                <p style="margin-top: 10px; margin-bottom: 0;">Demikian surat penawaran ini kami sampaikan. Atas perhatian dan kerjasamanya, kami ucapkan terima kasih.</p>
            </div>

            <!-- Tanda Tangan BBSPJIS -->
            <div style="text-align: right; font-size: 0.8rem; margin-top: 25px;">
                <div>Kepala Balai Besar,</div>
                <div style="font-size: 0.75rem; color: #555;">u.b. Ketua Tim Kerja Kemitraan & Standardisasi</div>
                <div style="height: 45px; margin: 6px 0; display: flex; align-items: center; justify-content: flex-end;">
                    <span style="border: 1px dashed #999; padding: 3px 8px; font-size: 0.7rem; color: #888;">[ STEMPEL & TTD RESMI ]</span>
                </div>
                <strong><u>Tim Kemitraan BBSPJIS</u></strong><br>
                <span style="font-size: 0.725rem; color: #555;">NIP. 19850715 201012 1 002</span>
            </div>
        </div>
    </div>

</div>

<script>
function pilihDivisiPenawaran(tipe) {
    document.getElementById('inputJenisLayanan').value = tipe;
    var tileSel = document.getElementById('tileSelulosa');
    var tileLing = document.getElementById('tileLingkungan');
    var radioSel = document.getElementById('radioSelulosa');
    var radioLing = document.getElementById('radioLingkungan');

    if (tipe === 'selulosa') {
        if (tileSel) tileSel.classList.add('selected-selulosa');
        if (tileLing) tileLing.classList.remove('selected-lingkungan');
        if (radioSel) radioSel.checked = true;
    } else {
        if (tileLing) tileLing.classList.add('selected-lingkungan');
        if (tileSel) tileSel.classList.remove('selected-selulosa');
        if (radioLing) radioLing.checked = true;
    }
    syncLivePreview();
}

function syncLivePreview() {
    var nama = document.getElementById('f_nama').value || 'Nama PIC';
    var perusahaan = document.getElementById('f_perusahaan').value || 'Nama Perusahaan';
    var alamat = document.getElementById('f_alamat').value || 'Alamat Klien';
    var nomor = document.getElementById('f_nomor').value || '01/SP/BBSPJIS/VIII/2026';
    var perihal = document.getElementById('f_perihal').value || 'Penawaran Layanan Jasa OPTI';
    var nominalVal = parseFloat(document.getElementById('f_nominal').value) || 0;
    var tglVal = document.getElementById('f_tanggal').value;
    var penjelasan = document.getElementById('f_penjelasan').value;
    var jenis = document.getElementById('inputJenisLayanan').value;

    document.getElementById('prevNama').textContent = nama;
    document.getElementById('prevPerusahaan').textContent = perusahaan;
    document.getElementById('prevAlamat').textContent = alamat;
    document.getElementById('prevNomor').textContent = nomor;
    document.getElementById('prevPerihal').textContent = perihal;
    document.getElementById('prevNominal').textContent = 'Rp ' + nominalVal.toLocaleString('id-ID');

    if (tglVal) {
        var d = new Date(tglVal);
        var months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        document.getElementById('prevTanggal').textContent = 'Bandung, ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    if (jenis === 'selulosa') {
        document.getElementById('prevDivisi').textContent = 'OPTI Selulosa (Riset & Skenario Rancop)';
    } else {
        document.getElementById('prevDivisi').textContent = 'OPTI Lingkungan (Pengujian Mutu Lab & LPV)';
    }

    var penizedBox = document.getElementById('prevPenjelasanBox');
    var penText = document.getElementById('prevPenjelasan');
    if (penjelasan && penjelasan.trim() !== '') {
        penizedBox.style.display = 'block';
        penText.textContent = penjelasan;
    } else {
        penizedBox.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Event listeners for real-time live preview
    var inputs = ['f_nama', 'f_perusahaan', 'f_alamat', 'f_nomor', 'f_perihal', 'f_nominal', 'f_tanggal', 'f_penjelasan'];
    inputs.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', syncLivePreview);
            el.addEventListener('change', syncLivePreview);
        }
    });

    syncLivePreview();

    // Submit actions
    var form = document.getElementById('spForm');
    var btnSimpan = document.getElementById('btnSimpan');
    var btnKirim = document.getElementById('btnKirim');

    if (btnSimpan) {
        btnSimpan.addEventListener('click', function() {
            document.getElementById('formAction').value = 'simpan';
            form.submit();
        });
    }
    if (btnKirim) {
        btnKirim.addEventListener('click', function() {
            document.getElementById('formAction').value = 'kirim';
            form.submit();
        });
    }
});
</script>
