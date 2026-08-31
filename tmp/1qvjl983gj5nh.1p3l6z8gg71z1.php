<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/surat-masuk" class="text-decoration-none text-muted"><i class="bi bi-inbox me-1"></i>Kotak Surat Masuk</a></li>
                <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="text-decoration-none text-muted">Order #<?= ($order['nomor_order']) ?></a></li>
                <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">Formulir Permintaan Pelayanan Jasa</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2 font-display">
            <i class="bi bi-file-earmark-medical-fill text-primary"></i> Formulir Permintaan Pelayanan Jasa OPTI
        </h4>
        <p class="text-muted small mb-0">Lengkapi data permohonan layanan jasa dari surat masuk. Dokumen formulir tersinkronisasi secara langsung (<em>real-time live preview</em>) pada lembar dokumen di sebelah kanan.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= ($BASE) ?>/surat-masuk" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Kembali ke Kotak Surat
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
<?php if ($SESSION['flash_success']): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= ($this->raw($SESSION['flash_success']))."
" ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

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
        overflow: hidden;
    }
    .panel-head {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--color-border-subtle);
        background: #f8fafc;
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
        color: var(--color-primary, #881337);
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
        border-color: var(--color-primary, #881337) !important;
        background: #fdf2f8 !important;
        box-shadow: 0 0 0 3px rgba(136, 19, 55, 0.12) !important;
    }
    .opti-choice-tile.selected-lingkungan {
        border-color: #059669 !important;
        background: #f0fdf4 !important;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12) !important;
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
        padding: 30px 32px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        font-family: 'Times New Roman', Times, serif;
        font-size: 0.825rem;
        color: #111;
        line-height: 1.4;
    }
    .doc-header-kop {
        text-align: center;
        border-bottom: 2px solid #000;
        padding-bottom: 6px;
        margin-bottom: 2px;
    }
    .doc-header-subkop {
        border-bottom: 1px solid #000;
        margin-bottom: 12px;
    }
    .doc-title-box {
        text-align: center;
        margin-bottom: 14px;
    }
    .doc-title-box h6 {
        font-family: 'Times New Roman', Times, serif;
        font-size: 0.95rem;
        font-weight: bold;
        text-decoration: underline;
        margin-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .doc-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .doc-table td {
        padding: 4px 6px;
        vertical-align: top;
        font-size: 0.8rem;
    }
    .doc-table td.label-col {
        width: 32%;
        color: #222;
    }
    .doc-table td.colon-col {
        width: 3%;
        text-align: center;
    }
    .doc-table td.val-col {
        width: 65%;
        font-weight: 600;
        color: #000;
    }
    .doc-section-head {
        background: #e2e8f0;
        font-weight: bold;
        font-size: 0.78rem;
        padding: 4px 8px;
        margin: 10px 0 6px 0;
        border-left: 3px solid var(--color-primary, #881337);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
</style>

<div class="panel-container">

    <!-- KOLOM KIRI: FORM ISIAN PELAYANAN -->
    <div class="panel-form">
        <div class="panel-head">
            <h6 class="m-0 fw-bold text-dark font-display">
                <i class="bi bi-pencil-square text-primary me-2"></i>Isian Permintaan Pelayanan Jasa
            </h6>
            <span class="badge bg-primary text-white">Tim Kemitraan</span>
        </div>
        <div class="panel-body">
            <form id="pelayananForm" action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/form-pelayanan" method="POST">
                <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                <input type="hidden" name="action_btn" id="formActionBtn" value="kirim_katim">
                <input type="hidden" name="jenis_layanan_opti" id="inputJenisLayanan" value="<?= ($order['jenis_layanan_opti'] == 'lingkungan' ? 'lingkungan' : 'selulosa') ?>">

                <!-- 1. Data Surat Masuk & Identitas Pemohon -->
                <fieldset class="form-group-fieldset">
                    <legend>1. Data Surat Masuk &amp; Identitas Pemohon</legend>
                    
                    <div class="row g-2 mb-2">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-dark" for="f_nomor_surat">Nomor Surat Masuk</label>
                            <input type="text" id="f_nomor_surat" class="form-control form-control-sm font-monospace bg-light" value="<?= ($surat_masuk['nomor_surat'] ?: '-') ?>" readonly>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark" for="f_tanggal_surat">Tanggal Surat</label>
                            <input type="date" id="f_tanggal_surat" class="form-control form-control-sm bg-light" value="<?= ($surat_masuk['tanggal_surat'] ? date('Y-m-d', strtotime($surat_masuk['tanggal_surat'])) : date('Y-m-d')) ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark" for="f_perusahaan">Nama Perusahaan / Instansi Pemohon <span class="text-danger">*</span></label>
                        <input type="text" id="f_perusahaan" name="perusahaan" class="form-control form-control-sm fw-semibold" placeholder="Nama instansi / PT / CV..." value="<?= ($order['nama_perusahaan']) ?>" required>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark" for="f_pic">Nama Kontak PIC Pelanggan</label>
                            <input type="text" id="f_pic" name="pic" class="form-control form-control-sm" placeholder="Nama PIC..." value="<?= ($order['pic']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark" for="f_telepon">Telepon / WhatsApp</label>
                            <input type="text" id="f_telepon" name="telepon" class="form-control form-control-sm" placeholder="No Telepon / WA..." value="<?= ($order['telepon']) ?>">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-dark" for="f_alamat">Alamat Instansi / Pabrik</label>
                        <textarea id="f_alamat" name="alamat" class="form-control form-control-sm" rows="2" placeholder="Alamat lengkap pabrik / kantor..."><?= ($order['alamat']) ?></textarea>
                    </div>
                </fieldset>

                <!-- 2. Pilihan Divisi Layanan OPTI (Fokus Dual Workflow) -->
                <fieldset class="form-group-fieldset">
                    <legend>2. Pilihan Divisi Pelaksana OPTI</legend>
                    
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <div class="opti-choice-tile <?= ($order['jenis_layanan_opti'] != 'lingkungan' ? 'selected-selulosa' : '') ?>" id="tileSelulosa" onclick="pilihDivisi('selulosa')">
                                <input type="radio" name="opt_divisi" id="radioSelulosa" value="selulosa" <?= ($order['jenis_layanan_opti'] != 'lingkungan' ? 'checked' : '') ?> class="d-none">
                                <i class="bi bi-file-earmark-medical-fill text-primary fs-3"></i>
                                <div>
                                    <strong class="d-block text-primary small font-display">OPTI Selulosa</strong>
                                    <small class="text-muted" style="font-size: 0.725rem;">Ka. Tim: <?= ($katim_selulosa_nama ?: 'Bu Rina Masriani') ?></small>
                                    <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.65rem;">Riset &amp; Rancop</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="opti-choice-tile <?= ($order['jenis_layanan_opti'] == 'lingkungan' ? 'selected-lingkungan' : '') ?>" id="tileLingkungan" onclick="pilihDivisi('lingkungan')">
                                <input type="radio" name="opt_divisi" id="radioLingkungan" value="lingkungan" <?= ($order['jenis_layanan_opti'] == 'lingkungan' ? 'checked' : '') ?> class="d-none">
                                <i class="bi bi-water text-success fs-3"></i>
                                <div>
                                    <strong class="d-block text-success small font-display">OPTI Lingkungan</strong>
                                    <small class="text-muted" style="font-size: 0.725rem;">Ka. Tim: <?= ($katim_lingkungan_nama ?: 'Pak Andri Taufick') ?></small>
                                    <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.65rem;">Uji Lab SNI &amp; LPV</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- 3. Rincian Permohonan Pelayanan Jasa -->
                <fieldset class="form-group-fieldset">
                    <legend>3. Rincian Permohonan Pelayanan Jasa</legend>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark" for="f_judul">Judul Permohonan Kegiatan / Pengujian <span class="text-danger">*</span></label>
                        <input type="text" id="f_judul" name="judul_kegiatan" class="form-control form-control-sm fw-semibold" placeholder="Contoh: Pengujian Karakteristik & Baku Mutu Air Limbah Industri" value="<?= ($order['judul_kegiatan']) ?>" required>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark" for="f_bidang">Bidang / Jenis Pelayanan <span class="text-danger">*</span></label>
                            <select id="f_bidang" name="bidang_pelayanan" class="form-select form-select-sm">
                                <option value="pengujian">Pengujian Laboratorium</option>
                                <option value="riset">Riset &amp; Pengembangan Teknologi</option>
                                <option value="standardisasi">Standardisasi &amp; Kalibrasi</option>
                                <option value="konsultansi">Konsultansi &amp; Bimbingan Teknis</option>
                                <option value="perekayasaan">Perekayasaan Proses Industri</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark" for="f_spm">Standar Pelayanan Minimum (SPM) <span class="text-danger">*</span></label>
                            <select id="f_spm" name="spm_layanan" class="form-select form-select-sm" required>
                                <?php foreach (($spm_list ?: []?:[]) as $spmKey=>$spmVal): ?>
                                    <option value="<?= ($spmKey) ?>" <?= ($order['spm_layanan'] == $spmKey ? 'selected' : '') ?>>
                                        <?= ($spmKey) ?> (<?= ($spmVal) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark" for="f_jenis_sampel">Jenis Sampel / Bahan Awal</label>
                            <input type="text" id="f_jenis_sampel" name="jenis_sampel" class="form-control form-control-sm" placeholder="Contoh: Air Limbah Outlet / Sampel Kayu Pulp" value="<?= ($order['jenis_sampel']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark" for="f_volume">Jumlah / Volume Sampel</label>
                            <input type="text" id="f_volume" name="volume_berat" class="form-control form-control-sm" placeholder="Contoh: 5 Liter / 10 Kg / 3 Titik Sampling" value="<?= ($order['volume_berat'] ?: '1 paket kegiatan') ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark" for="f_lokasi">Tipe Lokasi Pelaksanaan</label>
                            <select id="f_lokasi" name="lokasi_pelaksanaan" class="form-select form-select-sm">
                                <option value="internal" <?= ($order['lokasi_pelaksanaan'] != 'lapangan' ? 'selected' : '') ?>>Laboratorium Internal BBSPJIS</option>
                                <option value="lapangan" <?= ($order['lokasi_pelaksanaan'] == 'lapangan' ? 'selected' : '') ?>>On-Site / Pabrik Lapangan Mitra</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark" for="f_lab">Laboratorium Resmi Balai</label>
                            <select id="f_lab" name="lab_internal" class="form-select form-select-sm">
                                <?php foreach (($lab_internal_list ?: []?:[]) as $labKey=>$labName): ?>
                                    <option value="<?= ($labKey) ?>" <?= ($order['lab_internal'] == $labKey ? 'selected' : '') ?>><?= ($labName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-dark" for="f_catatan">Deskripsi Singkat / Catatan Kebutuhan Klien</label>
                        <textarea id="f_catatan" name="deskripsi" class="form-control form-control-sm" rows="3" placeholder="Tuliskan catatan teknis atau ruang lingkup khusus yang diminta oleh pelanggan..."><?= ($order['deskripsi']) ?></textarea>
                    </div>
                </fieldset>

                <!-- Tombol Aksi -->
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <a href="<?= ($BASE) ?>/surat-masuk" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-x-circle me-1"></i> Batal
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm px-3" id="btnSimpanDraf">
                            <i class="bi bi-save me-1"></i> Simpan Draf
                        </button>
                        <button type="button" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" id="btnKirimKatim">
                            <i class="bi bi-send-check-fill me-1"></i> Simpan &amp; Kirim ke Ketua Tim OPTI
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- KOLOM KANAN: LIVE DOCUMENT / PDF PREVIEW -->
    <div class="doc-preview-panel">
        <div class="d-flex justify-content-between align-items-center text-white px-0 pb-3 mb-2 border-bottom border-secondary">
            <h6 class="m-0 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-medical text-danger"></i> Live Preview Formulir Pelayanan
            </h6>
            <span class="badge bg-success small"><i class="bi bi-broadcast me-1"></i> Real-time Sync</span>
        </div>
        
        <div class="doc-page" id="docPage">
            <!-- Kop Surat Resmi BBSPJIS -->
            <div class="doc-header-kop">
                <div style="font-size: 0.68rem; font-weight: bold; letter-spacing: 0.5px;">KEMENTERIAN PERINDUSTRIAN REPUBLIK INDONESIA</div>
                <div style="font-size: 0.68rem; font-weight: bold; letter-spacing: 0.5px;">BADAN STANDARDISASI DAN KEBIJAKAN JASA INDUSTRI</div>
                <div style="font-size: 0.8rem; font-weight: bold; margin: 2px 0;">BALAI BESAR STANDARDISASI DAN PELAYANAN JASA INDUSTRI SELULOSA</div>
                <div style="font-size: 0.625rem; color: #444;">Jl. Raya Dayeuhkolot No. 132 Bandung 40258 Telp. (022) 5202871 Fax. (022) 5202872</div>
            </div>
            <div class="doc-header-subkop"></div>

            <!-- Judul Dokumen -->
            <div class="doc-title-box">
                <h6>FORMULIR PERMINTAAN PELAYANAN JASA INDUSTRI (OPTI)</h6>
                <span style="font-size: 0.725rem; color: #555;">No. Registrasi: <strong id="prevNoOrder">#<?= ($order['nomor_order']) ?></strong></span>
            </div>

            <!-- I. Data Pemohon -->
            <div class="doc-section-head">I. IDENTITAS PEMOHON / PELANGGAN</div>
            <table class="doc-table">
                <tr>
                    <td class="label-col">Nama Perusahaan / Instansi</td>
                    <td class="colon-col">:</td>
                    <td class="val-col" id="prevPerusahaan">-</td>
                </tr>
                <tr>
                    <td class="label-col">Penanggung Jawab (PIC)</td>
                    <td class="colon-col">:</td>
                    <td class="val-col" id="prevPic">-</td>
                </tr>
                <tr>
                    <td class="label-col">Kontak Telepon / WA</td>
                    <td class="colon-col">:</td>
                    <td class="val-col" id="prevTelepon">-</td>
                </tr>
                <tr>
                    <td class="label-col">Alamat Lengkap</td>
                    <td class="colon-col">:</td>
                    <td class="val-col" id="prevAlamat" style="font-weight: normal; color: #333;">-</td>
                </tr>
                <tr>
                    <td class="label-col">Dasar Surat Permohonan</td>
                    <td class="colon-col">:</td>
                    <td class="val-col">
                        <span id="prevNomorSurat"><?= ($surat_masuk['nomor_surat'] ?: '-') ?></span> 
                        (<span id="prevTglSurat"><?= ($surat_masuk['tanggal_surat'] ? date('d M Y', strtotime($surat_masuk['tanggal_surat'])) : '-') ?></span>)
                    </td>
                </tr>
            </table>

            <!-- II. Rincian Pelayanan -->
            <div class="doc-section-head">II. RINCIAN PERMINTAAN LAYANAN JASA</div>
            <table class="doc-table">
                <tr>
                    <td class="label-col">Divisi Laboratorium OPTI</td>
                    <td class="colon-col">:</td>
                    <td class="val-col" id="prevDivisi">OPTI Selulosa (Riset &amp; Rancop)</td>
                </tr>
                <tr>
                    <td class="label-col">Judul Permohonan Kegiatan</td>
                    <td class="colon-col">:</td>
                    <td class="val-col" id="prevJudul">-</td>
                </tr>
                <tr>
                    <td class="label-col">Bidang / Jenis Pelayanan</td>
                    <td class="colon-col">:</td>
                    <td class="val-col" id="prevBidang">Pengujian Laboratorium</td>
                </tr>
                <tr>
                    <td class="label-col">Standar Pelayanan Minimum</td>
                    <td class="colon-col">:</td>
                    <td class="val-col" id="prevSpm">-</td>
                </tr>
                <tr>
                    <td class="label-col">Jenis &amp; Volume Sampel</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><span id="prevJenisSampel">-</span> / <span id="prevVolume">-</span></td>
                </tr>
                <tr>
                    <td class="label-col">Lokasi &amp; Laboratorium</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><span id="prevLokasi">Laboratorium Internal BBSPJIS</span> (<span id="prevLab">-</span>)</td>
                </tr>
            </table>

            <!-- III. Catatan Teknis -->
            <div class="doc-section-head">III. CATATAN &amp; SPESIFIKASI KHUSUS</div>
            <div style="font-size: 0.78rem; padding: 4px 6px; min-height: 36px; background: #fdfdfd; border: 1px dashed #ccc; border-radius: 3px; color: #333; white-space: pre-line;" id="prevCatatan">
                Tidak ada catatan khusus.
            </div>

            <!-- IV. Lembar Pengesahan -->
            <div style="margin-top: 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.75rem;">
                <div style="text-align: center; border: 1px solid #ddd; padding: 8px; border-radius: 4px; background: #fafafa;">
                    <div style="font-size: 0.7rem; color: #555;">Petugas Penerima Permintaan</div>
                    <strong style="display: block; margin-top: 2px;">Tim Kemitraan BBSPJIS</strong>
                    <div style="height: 38px; display: flex; align-items: center; justify-content: center;">
                        <span style="font-size: 0.65rem; color: #888; border: 1px dashed #bbb; padding: 2px 6px;">[ TERVERIFIKASI SISTEM ]</span>
                    </div>
                    <small class="text-muted">Tanggal: <?= (date('d M Y')) ?></small>
                </div>
                <div style="text-align: center; border: 1px solid #ddd; padding: 8px; border-radius: 4px; background: #fafafa;">
                    <div style="font-size: 0.7rem; color: #555;">Ketua Tim OPTI Terkait</div>
                    <strong style="display: block; margin-top: 2px;" id="prevKatimNama"><?= ($katim_selulosa_nama ?: 'Bu Rina Masriani') ?></strong>
                    <div style="height: 38px; display: flex; align-items: center; justify-content: center;">
                        <span style="font-size: 0.65rem; color: #888; border: 1px dashed #bbb; padding: 2px 6px;">[ MENUNGGU KAJI ULANG ]</span>
                    </div>
                    <small class="text-muted">Status: Kaji Ulang ISO 17025</small>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
function pilihDivisi(divisi) {
    document.getElementById('inputJenisLayanan').value = divisi;
    
    var tileSel = document.getElementById('tileSelulosa');
    var tileLing = document.getElementById('tileLingkungan');
    var radSel = document.getElementById('radioSelulosa');
    var radLing = document.getElementById('radioLingkungan');
    
    if (divisi === 'selulosa') {
        if (tileSel) tileSel.classList.add('selected-selulosa');
        if (tileLing) tileLing.classList.remove('selected-lingkungan');
        if (radSel) radSel.checked = true;
    } else {
        if (tileLing) tileLing.classList.add('selected-lingkungan');
        if (tileSel) tileSel.classList.remove('selected-selulosa');
        if (radLing) radLing.checked = true;
    }
    syncLivePreview();
}

function syncLivePreview() {
    var perusahaan = document.getElementById('f_perusahaan') ? document.getElementById('f_perusahaan').value : '';
    var pic = document.getElementById('f_pic') ? document.getElementById('f_pic').value : '';
    var telepon = document.getElementById('f_telepon') ? document.getElementById('f_telepon').value : '';
    var alamat = document.getElementById('f_alamat') ? document.getElementById('f_alamat').value : '';
    var judul = document.getElementById('f_judul') ? document.getElementById('f_judul').value : '';
    var jenisSampel = document.getElementById('f_jenis_sampel') ? document.getElementById('f_jenis_sampel').value : '';
    var volume = document.getElementById('f_volume') ? document.getElementById('f_volume').value : '';
    var catatan = document.getElementById('f_catatan') ? document.getElementById('f_catatan').value : '';
    
    var bidangSelect = document.getElementById('f_bidang');
    var bidangText = bidangSelect ? bidangSelect.options[bidangSelect.selectedIndex].text : 'Pengujian Laboratorium';
    
    var spmSelect = document.getElementById('f_spm');
    var spmText = spmSelect ? spmSelect.options[spmSelect.selectedIndex].text : '-';

    var lokasiSelect = document.getElementById('f_lokasi');
    var lokasiText = lokasiSelect ? lokasiSelect.options[lokasiSelect.selectedIndex].text : 'Laboratorium Internal BBSPJIS';

    var labSelect = document.getElementById('f_lab');
    var labText = labSelect ? labSelect.options[labSelect.selectedIndex].text : '-';

    var jenisLayanan = document.getElementById('inputJenisLayanan').value;

    document.getElementById('prevPerusahaan').textContent = perusahaan || 'PT. Nama Perusahaan Mitra';
    document.getElementById('prevPic').textContent = pic || '-';
    document.getElementById('prevTelepon').textContent = telepon || '-';
    document.getElementById('prevAlamat').textContent = alamat || '-';
    document.getElementById('prevJudul').textContent = judul || 'Pengujian & Pelayanan Jasa OPTI';
    document.getElementById('prevBidang').textContent = bidangText;
    document.getElementById('prevSpm').textContent = spmText;
    document.getElementById('prevJenisSampel').textContent = jenisSampel || 'Sampel Uji';
    document.getElementById('prevVolume').textContent = volume || '1 paket';
    document.getElementById('prevLokasi').textContent = lokasiText;
    document.getElementById('prevLab').textContent = labText;

    if (catatan && catatan.trim() !== '') {
        document.getElementById('prevCatatan').textContent = catatan;
    } else {
        document.getElementById('prevCatatan').textContent = 'Tidak ada catatan khusus.';
    }

    if (jenisLayanan === 'selulosa') {
        document.getElementById('prevDivisi').textContent = 'OPTI Selulosa (Riset & Skenario Rancop)';
        document.getElementById('prevKatimNama').textContent = '<?= ($katim_selulosa_nama ?: "Bu Rina Masriani") ?>';
    } else {
        document.getElementById('prevDivisi').textContent = 'OPTI Lingkungan (Pengujian Mutu Lab & LPV)';
        document.getElementById('prevKatimNama').textContent = '<?= ($katim_lingkungan_nama ?: "Pak Andri Taufick") ?>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var inputIds = ['f_perusahaan', 'f_pic', 'f_telepon', 'f_alamat', 'f_judul', 'f_bidang', 'f_spm', 'f_jenis_sampel', 'f_volume', 'f_lokasi', 'f_lab', 'f_catatan'];
    inputIds.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', syncLivePreview);
            el.addEventListener('change', syncLivePreview);
        }
    });

    syncLivePreview();

    var form = document.getElementById('pelayananForm');
    var btnDraf = document.getElementById('btnSimpanDraf');
    var btnKirim = document.getElementById('btnKirimKatim');

    if (btnDraf) {
        btnDraf.addEventListener('click', function() {
            document.getElementById('formActionBtn').value = 'save_draft';
            form.submit();
        });
    }
    if (btnKirim) {
        btnKirim.addEventListener('click', function() {
            document.getElementById('formActionBtn').value = 'kirim_katim';
            form.submit();
        });
    }
});
</script>