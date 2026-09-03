<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= ($order['id'] ? $BASE.'/order/'.$order['id'] : $BASE.'/surat-penawaran') ?>" class="text-decoration-none text-muted"><?= ($order['id'] ? 'Order #' . $order['nomor_order'] : 'Surat Penawaran') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Formulir Permintaan Pelayanan Jasa</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0 text-dark">Formulir Permintaan Pelayanan Jasa</h4>
    </div>
    <div>
        <a href="<?= ($order['id'] ? $BASE.'/order/'.$order['id'] : $BASE.'/surat-penawaran') ?>" class="btn btn-outline-secondary btn-sm">
            Kembali
        </a>
    </div>
</div>

<style>
    .form-preview-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: stretch;
    }
    @media (max-width: 1199.98px) {
        .form-preview-grid {
            grid-template-columns: 1fr;
        }
    }
    .form-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-bottom: 12px;
    }
    .form-card-header {
        padding: 8px 12px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 0.82rem;
        color: #1e293b;
    }
    .form-card-body {
        padding: 12px;
    }

    /* Live Preview Document Styles */
    .preview-container {
        background: #475569;
        border-radius: 6px;
        padding: 12px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .preview-header {
        color: #f8fafc;
        font-size: 0.78rem;
        font-weight: 600;
        padding-bottom: 6px;
        margin-bottom: 8px;
        border-bottom: 1px solid #64748b;
    }
    .doc-sheet {
        background: #ffffff;
        border-radius: 4px;
        padding: 18px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-family: "Times New Roman", Times, serif;
        font-size: 11px;
        color: #000000;
        line-height: 1.35;
        flex: 1;
        overflow-y: auto;
    }
    .doc-head {
        border-bottom: 1.5px solid #000;
        padding-bottom: 4px;
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .doc-main-title {
        text-align: center;
        font-weight: bold;
        font-size: 12px;
        line-height: 1.25;
        margin-bottom: 8px;
    }
    .doc-field-row {
        display: flex;
        margin-bottom: 3px;
    }
    .doc-field-label {
        width: 75px;
        flex: none;
        color: #333;
    }
    .doc-field-colon {
        width: 10px;
        flex: none;
    }
    .doc-field-value {
        flex: 1;
        font-weight: 600;
        border-bottom: 1px dotted #999;
        min-height: 14px;
    }
    .doc-subtitle {
        margin: 6px 0 3px;
        font-weight: bold;
        font-size: 10.5px;
    }
    .doc-grid-2col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2px 6px;
        font-size: 10.5px;
    }
    .doc-option-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .doc-checkbox-box {
        width: 10px;
        height: 10px;
        border: 1px solid #000;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 8px;
        font-weight: bold;
        flex: none;
        border-radius: 1px;
        background: #fff;
    }
    .doc-checkbox-box.checked {
        background: #000;
        color: #fff;
    }
    .doc-text-box {
        border: 1px dashed #999;
        padding: 4px 6px;
        border-radius: 2px;
        min-height: 28px;
        font-size: 10px;
        white-space: pre-wrap;
        margin-top: 2px;
        background: #fafafa;
    }
    .doc-signature {
        margin-top: 10px;
        text-align: right;
        font-size: 10.5px;
    }
</style>

<form id="formPenawaran" method="POST" action="<?= ($order['id'] ? $BASE.'/order/'.$order['id'].'/form-pelayanan' : ($sp['id'] ? $BASE.'/surat-penawaran/'.$sp['id'].'/update' : $BASE.'/surat-penawaran/simpan')) ?>">
    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
    <input type="hidden" name="order_id" value="<?= ($order['id'] ?: ($sp['order_id'] ?: '')) ?>">
    <input type="hidden" name="aksi" id="inputAksi" value="simpan">
    <input type="hidden" name="bidang_layanan[]" value="opti">

    <?php if (!$can_edit): ?>
        <div class="alert alert-warning border-0 d-flex align-items-center mb-3 py-2 px-3 small rounded-3">
            <div>
                <strong>Mode Lihat Saja (Read-Only)</strong>: Pengisian dan pengiriman Formulir Permintaan Pelayanan Jasa merupakan wewenang <strong>Tim Mitra</strong>.
            </div>
        </div>
    <?php endif; ?>

    <div class="form-preview-grid">

        <!-- ============================================== -->
        <!-- KOLOM KIRI: FORMULIR ISIAN LENGKAP             -->
        <!-- ============================================== -->
        <div>

            <!-- 1. Identitas Klien -->
            <div class="form-card">
                <div class="form-card-header">1. Identitas Klien / Pelanggan</div>
                <div class="form-card-body">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold text-dark mb-1" for="inputNama">Nama <span class="text-danger">*</span></label>
                            <input type="text" id="inputNama" name="nama" class="form-control form-control-sm" placeholder="Nama lengkap" value="<?= (($order['pic'] && $order['pic'] != '-' && $order['pic'] != '—') ? $order['pic'] : (($sp['nama'] && $sp['nama'] != '-' && $sp['nama'] != '—') ? $sp['nama'] : '')) ?>" <?= (!$can_edit ? 'disabled' : '') ?> required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold text-dark mb-1" for="inputPerusahaan">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" id="inputPerusahaan" name="perusahaan" class="form-control form-control-sm" placeholder="Nama instansi / PT" value="<?= (($order['nama_perusahaan'] && $order['nama_perusahaan'] != '-' && $order['nama_perusahaan'] != '—') ? $order['nama_perusahaan'] : (($sp['perusahaan'] && $sp['perusahaan'] != '-' && $sp['perusahaan'] != '—') ? $sp['perusahaan'] : '')) ?>" <?= (!$can_edit ? 'disabled' : '') ?> required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark mb-1" for="inputAlamat">Alamat</label>
                            <input type="text" id="inputAlamat" name="alamat" class="form-control form-control-sm" placeholder="Alamat perusahaan / klien" value="<?= (($order['alamat'] && $order['alamat'] != '-' && $order['alamat'] != '—') ? $order['alamat'] : (($sp['alamat'] && $sp['alamat'] != '-' && $sp['alamat'] != '—') ? $sp['alamat'] : '')) ?>" <?= (!$can_edit ? 'disabled' : '') ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Permintaan Melalui & Disposisi Divisi -->
            <div class="form-card">
                <div class="form-card-header">2. Saluran Masuk &amp; Divisi OPTI</div>
                <div class="form-card-body">
                    <label class="form-label small fw-semibold text-dark mb-1">Permintaan Melalui:</label>
                    <div class="row g-1 mb-2">
                        <div class="col-4">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="permintaan_melalui" id="pmTelepon" value="telepon" <?= ($sp['permintaan_melalui'] == 'telepon' ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?>>
                                <label class="form-check-label small" for="pmTelepon">Telepon</label>
                            </div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="permintaan_melalui" id="pmFax" value="fax" <?= ($sp['permintaan_melalui'] == 'fax' ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?>>
                                <label class="form-check-label small" for="pmFax">Fax</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="permintaan_melalui" id="pmSurat" value="surat" <?= (($sp['permintaan_melalui'] == 'surat' || (!$sp['permintaan_melalui'] && $order['id'])) ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?>>
                                <label class="form-check-label small" for="pmSurat">Surat</label>
                            </div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="permintaan_melalui" id="pmEmail" value="email" <?= (($sp['permintaan_melalui'] == 'email' || (!$sp['permintaan_melalui'] && !$order['id'])) ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?>>
                                <label class="form-check-label small" for="pmEmail">E-mail</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="permintaan_melalui" id="pmDatang" value="datang_langsung" <?= ($sp['permintaan_melalui'] == 'datang_langsung' ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?>>
                                <label class="form-check-label small" for="pmDatang">Datang Langsung</label>
                            </div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="permintaan_melalui" id="pmPegawai" value="pegawai_bbspjis" <?= ($sp['permintaan_melalui'] == 'pegawai_bbspjis' ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?>>
                                <label class="form-check-label small" for="pmPegawai">Pegawai BBSPJIS</label>
                            </div>
                        </div>
                    </div>

                    <div id="pegawaiWrap" class="p-2 rounded bg-light border mb-2" style="display: none;">
                        <label class="form-label small mb-1" for="inputPegawai">Pilih Pegawai BBSPJIS:</label>
                        <select id="inputPegawai" name="pegawai_id" class="form-select form-select-sm" <?= (!$can_edit ? 'disabled' : '') ?>>
                            <option value="">-- Pilih Pegawai --</option>
                            <?php foreach (($daftar_pegawai?:[]) as $pegawai): ?>
                                <option value="<?= ($pegawai['id_user']) ?>" <?= ($sp['pegawai_id'] == $pegawai['id_user'] ? 'selected' : '') ?>><?= ($pegawai['nama_user']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="my-2">

                    <label class="form-label small fw-semibold text-dark mb-1">Kirim ke Divisi OPTI:</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis_layanan" id="jlSelulosa" value="selulosa" <?= ($order['jenis_layanan_opti'] == 'lingkungan' ? '' : 'checked') ?> <?= (!$can_edit ? 'disabled' : '') ?>>
                            <label class="form-check-label small fw-semibold text-dark" for="jlSelulosa">OPTI Selulosa</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis_layanan" id="jlLingkungan" value="lingkungan" <?= ($order['jenis_layanan_opti'] == 'lingkungan' ? 'checked' : '') ?> <?= (!$can_edit ? 'disabled' : '') ?>>
                            <label class="form-check-label small fw-semibold text-dark" for="jlLingkungan">OPTI Lingkungan</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Penjelasan -->
            <div class="form-card">
                <div class="form-card-header">3. Penjelasan</div>
                <div class="form-card-body">
                    <textarea id="inputPenjelasan" name="penjelasan" class="form-control form-control-sm" rows="3" placeholder="" <?= (!$can_edit ? 'disabled' : '') ?>><?= (($order['deskripsi'] && $order['deskripsi'] != '-' && !strpos($order['deskripsi'], 'Klaim Surat Masuk') && $order['deskripsi'] != 'Dengan penjelasan sebagai berikut...') ? $order['deskripsi'] : (($sp['penjelasan'] && $sp['penjelasan'] != '-' && !strpos($sp['penjelasan'], 'Klaim Surat Masuk') && $sp['penjelasan'] != 'Dengan penjelasan sebagai berikut...') ? $sp['penjelasan'] : '')) ?></textarea>
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="<?= ($order['id'] ? $BASE.'/order/'.$order['id'] : $BASE.'/surat-penawaran') ?>" class="btn btn-outline-secondary btn-sm px-3">
                    Kembali
                </a>
                <?php if ($can_edit): ?>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-outline-secondary btn-sm px-3" id="btnSimpan">
                            Simpan sebagai Draft
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnKirim">
                            Simpan &amp; Kirim
                        </button>
                    </div>
                <?php endif; ?>
                <?php if (!$can_edit): ?>
                    <button type="button" class="btn btn-secondary btn-sm px-4" disabled>
                        <i class="bi bi-lock-fill me-1"></i> Terkunci (Wewenang Tim Mitra)
                    </button>
                <?php endif; ?>
            </div>

        </div>

        <!-- ============================================== -->
        <!-- KOLOM KANAN: DOKUMEN PREVIEW LENGKAP           -->
        <!-- ============================================== -->
        <div>
            <div class="preview-container">
                <div class="preview-header">
                    <span>Pratinjau Dokumen F.PJT-08-01/02</span>
                </div>

                <div class="doc-sheet" id="docSheet">
                    <!-- Letterhead Kop -->
                    <div class="doc-head">
                        <div>
                            <div style="font-size: 8px; font-weight: bold; letter-spacing: 0.3px;">BALAI BESAR STANDARDISASI DAN PELAYANAN JASA INDUSTRI SELULOSA</div>
                            <div style="font-size: 7.5px; color: #444;">Jl. Raya Dayeuhkolot No. 132 Bandung 40258</div>
                        </div>
                        <div style="font-size: 8px; font-weight: bold; font-family: monospace;">
                            F.PJT-08-01/02
                        </div>
                    </div>

                    <!-- Judul Dokumen -->
                    <div class="doc-main-title">
                        FORMULIR SURAT PERMINTAAN<br>PELAYANAN JASA
                    </div>

                    <!-- Row Identitas -->
                    <div class="doc-field-row">
                        <div class="doc-field-label">Nama</div>
                        <div class="doc-field-colon">:</div>
                        <div class="doc-field-value" id="d_nama"></div>
                    </div>
                    <div class="doc-field-row">
                        <div class="doc-field-label">Perusahaan</div>
                        <div class="doc-field-colon">:</div>
                        <div class="doc-field-value" id="d_perusahaan"></div>
                    </div>
                    <div class="doc-field-row">
                        <div class="doc-field-label">Alamat</div>
                        <div class="doc-field-colon">:</div>
                        <div class="doc-field-value" id="d_alamat"></div>
                    </div>

                    <!-- Permintaan Melalui -->
                    <div class="doc-subtitle">Permintaan melalui (beri tanda &#9745;):</div>
                    <div class="doc-grid-2col" id="d_grup_permintaan"></div>
                    <div style="font-size: 9px; font-weight: bold; color: #1e293b; margin-top: 2px;" id="d_pegawai_line"></div>

                    <!-- Bidang Layanan -->
                    <div class="doc-subtitle">Bidang pelayanan jasa (beri tanda &#9745;):</div>
                    <div class="doc-grid-2col" id="d_grup_bidang"></div>

                    <!-- Divisi Pelaksana -->
                    <div style="font-size: 9.5px; font-weight: bold; padding: 3px 5px; background: #f1f5f9; border-radius: 2px; margin: 4px 0;" id="d_divisi_line">
                        Dikirim ke: OPTI Selulosa
                    </div>

                    <!-- Penjelasan -->
                    <div class="doc-subtitle">Penjelasan:</div>
                    <div class="doc-text-box" id="d_penjelasan"></div>

                    <!-- Tanda Tangan & Tanggal -->
                    <div class="doc-signature">
                        <div id="d_tanggal">Bandung, ...</div>
                        <div style="margin-top: 15px;">
                            <span id="d_sign_nama">( ........................................ )</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var permintaanLabels = {
        telepon: 'Telepon', fax: 'Fax', surat: 'Surat', email: 'E-mail',
        datang_langsung: 'Datang Langsung', pegawai_bbspjis: 'Pegawai BBSPJIS'
    };
    var permintaanOrder = ['telepon', 'fax', 'surat', 'email', 'datang_langsung', 'pegawai_bbspjis'];

    var bidangLabels = {
        riset: 'Riset',
        standardisasi: 'Standardisasi',
        pengujian: 'Pengujian',
        sertifikasi: 'Sertifikasi',
        kalibrasi: 'Kalibrasi',
        konsultansi: 'Konsultansi',
        pelatihan_teknis: 'Pelatihan Teknis',
        perekayasaan: 'Perekayasaan',
        opti: 'OPTI',
        lainnya: 'Lainnya'
    };
    var bidangOrder = [
        'riset',
        'standardisasi',
        'pengujian',
        'sertifikasi',
        'kalibrasi',
        'konsultansi',
        'pelatihan_teknis',
        'perekayasaan',
        'opti',
        'lainnya'
    ];

    function renderRadioGroup(target, order, labels, activeValue) {
        if (!target) return;
        target.innerHTML = order.map(function (key) {
            var on = key === activeValue;
            return '<div class="doc-option-item"><span class="doc-checkbox-box' + (on ? ' checked' : '') + '">' + (on ? '&#10003;' : '') + '</span>' + labels[key] + '</div>';
        }).join('');
    }

    function renderCheckGroup(target, order, labels, activeValues) {
        if (!target) return;
        target.innerHTML = order.map(function (key) {
            var on = activeValues.indexOf(key) !== -1;
            return '<div class="doc-option-item"><span class="doc-checkbox-box' + (on ? ' checked' : '') + '">' + (on ? '&#10003;' : '') + '</span>' + labels[key] + '</div>';
        }).join('');
    }

    function updatePreview() {
        var elNama = document.getElementById('inputNama');
        var elPerusahaan = document.getElementById('inputPerusahaan');
        var elAlamat = document.getElementById('inputAlamat');
        var elPenjelasan = document.getElementById('inputPenjelasan');

        var valNama = (elNama && elNama.value.trim() && elNama.value.trim() !== '-' && elNama.value.trim() !== '—') ? elNama.value.trim() : '';
        var valPerusahaan = (elPerusahaan && elPerusahaan.value.trim() && elPerusahaan.value.trim() !== '-' && elPerusahaan.value.trim() !== '—') ? elPerusahaan.value.trim() : '';
        var valAlamat = (elAlamat && elAlamat.value.trim() && elAlamat.value.trim() !== '-' && elAlamat.value.trim() !== '—') ? elAlamat.value.trim() : '';
        var valPenjelasan = (elPenjelasan && elPenjelasan.value.trim() && elPenjelasan.value.trim() !== '-' && !elPenjelasan.value.trim().startsWith('Klaim Surat Masuk') && elPenjelasan.value.trim() !== 'Dengan penjelasan sebagai berikut...') ? elPenjelasan.value.trim() : '';

        if (document.getElementById('d_nama')) document.getElementById('d_nama').textContent = valNama;
        if (document.getElementById('d_perusahaan')) document.getElementById('d_perusahaan').textContent = valPerusahaan;
        if (document.getElementById('d_alamat')) document.getElementById('d_alamat').textContent = valAlamat;
        if (document.getElementById('d_sign_nama')) document.getElementById('d_sign_nama').textContent = valNama ? '(' + valNama + ')' : '( ........................................ )';

        var permintaanEl = document.querySelector('input[name="permintaan_melalui"]:checked');
        var permintaan = permintaanEl ? permintaanEl.value : 'email';
        renderRadioGroup(document.getElementById('d_grup_permintaan'), permintaanOrder, permintaanLabels, permintaan);

        var pegawaiWrap = document.getElementById('pegawaiWrap');
        var pegawaiSelect = document.getElementById('inputPegawai');
        if (permintaan === 'pegawai_bbspjis') {
            if (pegawaiWrap) pegawaiWrap.style.display = 'block';
            var opt = (pegawaiSelect && pegawaiSelect.selectedIndex >= 0) ? pegawaiSelect.options[pegawaiSelect.selectedIndex] : null;
            if (document.getElementById('d_pegawai_line')) {
                document.getElementById('d_pegawai_line').textContent = 'Pegawai BBSPJIS: ' + (opt && opt.value ? opt.text : '-');
            }
        } else {
            if (pegawaiWrap) pegawaiWrap.style.display = 'none';
            if (document.getElementById('d_pegawai_line')) {
                document.getElementById('d_pegawai_line').textContent = '';
            }
        }

        renderCheckGroup(document.getElementById('d_grup_bidang'), bidangOrder, bidangLabels, ['opti']);

        var jenisEl = document.querySelector('input[name="jenis_layanan"]:checked');
        var jenis = jenisEl ? jenisEl.value : 'selulosa';
        if (document.getElementById('d_divisi_line')) {
            document.getElementById('d_divisi_line').textContent = 'Dikirim ke: OPTI ' + (jenis === 'lingkungan' ? 'Lingkungan' : 'Selulosa');
        }

        if (document.getElementById('d_penjelasan')) document.getElementById('d_penjelasan').textContent = valPenjelasan;
    }

    window.updatePreview = updatePreview;

    var form = document.getElementById('formPenawaran');
    if (form) {
        form.addEventListener('input', updatePreview);
        form.addEventListener('change', updatePreview);
    }

    var elPegawai = document.getElementById('inputPegawai');
    if (elPegawai) {
        elPegawai.addEventListener('change', updatePreview);
    }

    var today = new Date();
    var dTanggal = document.getElementById('d_tanggal');
    if (dTanggal) {
        dTanggal.textContent = 'Bandung, ' + today.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    var btnSimpan = document.getElementById('btnSimpan');
    var btnKirim = document.getElementById('btnKirim');
    if (btnSimpan) {
        btnSimpan.addEventListener('click', function () {
            document.getElementById('inputAksi').value = 'simpan';
        });
    }
    if (btnKirim) {
        btnKirim.addEventListener('click', function () {
            document.getElementById('inputAksi').value = 'kirim';
        });
    }

    updatePreview();
});
</script>