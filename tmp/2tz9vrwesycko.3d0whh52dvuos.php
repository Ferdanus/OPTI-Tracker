<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order" class="text-decoration-none">Order Layanan</a></li>
                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="text-decoration-none">#<?= ($order['nomor_order']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kalkulasi Pengujian Lingkungan</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                <i class="bi bi-calculator text-success"></i>
                Kalkulator Biaya Pengujian Multi-Metode (Divisi Lingkungan)
            </h4>
            <p class="text-muted small m-0 mt-1">Perhitungan biaya berbasis tarif baku metode (ASTM/SNI), jumlah sampel, diskon nominal (Rp), dan auto-deadline.</p>
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

    <form action="<?= ($BASE) ?>/order/<?= ($order['id']) ?>/biaya-lingkungan" method="POST" id="formKalkulasiLingkungan">
        <div class="row g-4">
            <!-- Kolom Pengaturan Tanggal Sampel & Ringkasan -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-calendar-event text-success"></i> Parameter Waktu & Sampel
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">
                                <i class="bi bi-box-seam text-primary me-1"></i> Tanggal Fisik Sampel Masuk Lab:
                            </label>
                            <input type="date" name="tanggal_terima_sampel" id="tanggal_terima_sampel" class="form-control form-control-sm" value="<?= ($order['tanggal_terima_sampel'] ?: date('Y-m-d')) ?>" onchange="recalculateTotals()">
                            <small class="text-muted">Target selesai (deadline) dihitung mulai dari tanggal sampel masuk.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">
                                <i class="bi bi-tag text-danger me-1"></i> Potongan / Diskon Nominal (Rp):
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-bold">Rp</span>
                                <input type="number" name="diskon_penawaran" id="diskon_penawaran" class="form-control form-control-sm text-end fw-bold" value="<?= ($order['diskon_penawaran'] ?: 0) ?>" min="0" step="10000" oninput="recalculateTotals()">
                            </div>
                            <small class="text-muted">Potongan harga hasil negosiasi (dalam bentuk nominal rupiah bulat).</small>
                        </div>

                        <!-- Card Total Ringkasan -->
                        <div class="card bg-success-subtle border-success-subtle p-3 mt-4">
                            <div class="d-flex justify-content-between mb-1 small text-secondary">
                                <span>Total Bruto:</span>
                                <strong id="lblTotalBruto">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 small text-danger">
                                <span>Potongan Diskon:</span>
                                <strong id="lblTotalDiskon">- Rp 0</strong>
                            </div>
                            <hr class="my-1 border-success-subtle">
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="fw-bold text-success-emphasis">Total Netto:</span>
                                <h5 class="m-0 fw-bold text-success" id="lblTotalNetto">Rp 0</h5>
                            </div>
                            <div class="mt-2 small text-muted">
                                <i class="bi bi-clock me-1"></i> Durasi Terlama: <strong class="text-dark" id="lblDurasiTerlama">1 Bulan</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Tabel Daftar Metode & Item Pengujian -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-list-check text-success"></i> Rincian Pengujian & Metode Laboratorium
                            </h6>
                            <small class="text-muted">Tambahkan satu atau beberapa metode pengujian untuk order ini.</small>
                        </div>
                        <button type="button" class="btn btn-success btn-sm d-flex align-items-center gap-1" onclick="addRowUji()">
                            <i class="bi bi-plus-circle"></i> Tambah Pengujian
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle m-0" id="tableUjiLingkungan">
                                <thead class="bg-light text-secondary small text-uppercase">
                                    <tr>
                                        <th style="width: 20%;">Sub Layanan</th>
                                        <th style="width: 30%;">Metode / Pengujian</th>
                                        <th style="width: 15%;">Tarif (Rp)</th>
                                        <th style="width: 10%;">Sampel</th>
                                        <th style="width: 15%;">Total Item</th>
                                        <th style="width: 10%; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyUji">
                                    <!-- Baris diisi oleh PHP atau JS -->
                                </tbody>
                            </table>
                        </div>

                        <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><i class="bi bi-info-circle me-1"></i> Anda bisa memilih metode baku ASTM/SNI atau mengetik pengujian kustom.</span>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="addRowUji()">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Baris
                            </button>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2 border-top">
                        <a href="<?= ($BASE) ?>/order/<?= ($order['id']) ?>" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-success d-flex align-items-center gap-2">
                            <i class="bi bi-save"></i> Simpan Kalkulasi Biaya
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Master Data Metode & Lab Eksternal untuk JavaScript -->
<script>
var MASTER_METODE = [
    <?php foreach (($daftar_metode?:[]) as $m): ?>
    {
        id: <?= ($m['id']) ?>,
        kategori_id: <?= ($m['kategori_id']) ?>,
        nama: "<?= (addslashes($m['nama_metode'])) ?>",
        standar: "<?= (addslashes($m['deskripsi_kegunaan'] ?? '')) ?>",
        tarif: <?= ((float)$m['harga']) ?>,
        durasi_hari: <?= ((int)$m['durasi_nilai'] * 30)."
" ?>
    },
    <?php endforeach; ?>
];

var INITIAL_ITEMS = [
    <?php foreach (($kalkulasi_items?:[]) as $it): ?>
    {
        sub_layanan: "<?= ($it['sub_layanan']) ?>",
        metode_uji_id: <?= ($it['metode_uji_id'] ?: 'null') ?>,
        nama_pengujian: "<?= (addslashes($it['nama_pengujian'])) ?>",
        standar_rujukan: "<?= (addslashes($it['standar_rujukan'])) ?>",
        tarif_per_sampel: <?= ((float)$it['tarif_per_sampel']) ?>,
        jumlah_sampel: <?= ((int)$it['jumlah_sampel']) ?>,
        durasi_bulan: <?= ((int)$it['durasi_bulan']) ?>,
        is_subkontrak: <?= ($it['is_subkontrak'] ? 'true' : 'false') ?>,
        lab_eksternal_id: <?= ($it['lab_eksternal_id'] ?: 'null')."
" ?>
    },
    <?php endforeach; ?>
];

var rowIndex = 0;

function addRowUji(data) {
    data = data || {
        sub_layanan: 'uji_laboratorium',
        metode_uji_id: null,
        nama_pengujian: '',
        standar_rujukan: '',
        tarif_per_sampel: 0,
        jumlah_sampel: 1,
        durasi_bulan: 1,
        is_subkontrak: false,
        lab_eksternal_id: null
    };

    var tbody = document.getElementById('tbodyUji');
    var tr = document.createElement('tr');
    tr.id = 'row_' + rowIndex;

    var idx = rowIndex;
    rowIndex++;

    var optMetodeHtml = '<option value="">-- Pilih Metode Standar (Otomatis Tarif) --</option>';
    MASTER_METODE.forEach(function(m) {
        var sel = (data.metode_uji_id == m.id) ? 'selected' : '';
        optMetodeHtml += '<option value="' + m.id + '" data-tarif="' + m.tarif + '" data-standar="' + m.standar + '" data-durasi="' + m.durasi_hari + '" ' + sel + '>' + m.nama + ' (' + m.standar + ')</option>';
    });

    tr.innerHTML = `
        <td>
            <select name="items[${idx}][sub_layanan]" class="form-select form-select-sm">
                <option value="uji_laboratorium" ${data.sub_layanan == 'uji_laboratorium' ? 'selected' : ''}>Uji Laboratorium</option>
                <option value="lpv" ${data.sub_layanan == 'lpv' ? 'selected' : ''}>Layanan LPV (Lapangan/Emisi)</option>
                <option value="kajian_lab" ${data.sub_layanan == 'kajian_lab' ? 'selected' : ''}>Kajian Laboratorium</option>
                <option value="konsultansi" ${data.sub_layanan == 'konsultansi' ? 'selected' : ''}>Konsultansi Lingkungan</option>
            </select>
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="items[${idx}][is_subkontrak]" id="sub_${idx}" value="1" ${data.is_subkontrak ? 'checked' : ''}>
                <label class="form-check-label small text-muted" for="sub_${idx}">Lab Eksternal</label>
            </div>
        </td>
        <td>
            <select class="form-select form-select-sm mb-1 select-metode" name="items[${idx}][metode_uji_id]" onchange="onSelectMetode(${idx}, this)">
                ${optMetodeHtml}
            </select>
            <input type="text" name="items[${idx}][nama_pengujian]" id="nama_${idx}" class="form-control form-control-sm mb-1" placeholder="Nama pengujian/parameter" value="${data.nama_pengujian}" required>
            <div class="row g-1">
                <div class="col-7">
                    <input type="text" name="items[${idx}][standar_rujukan]" id="standar_${idx}" class="form-control form-control-sm text-muted" placeholder="Standar (ASTM/SNI)" value="${data.standar_rujukan}">
                </div>
                <div class="col-5">
                    <div class="input-group input-group-sm">
                        <input type="number" name="items[${idx}][durasi_bulan]" id="durasi_${idx}" class="form-control form-control-sm text-center" value="${data.durasi_bulan}" min="1" max="24" oninput="recalculateTotals()">
                        <span class="input-group-text small">Bln</span>
                    </div>
                </div>
            </div>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text small">Rp</span>
                <input type="number" name="items[${idx}][tarif_per_sampel]" id="tarif_${idx}" class="form-control form-control-sm text-end fw-semibold input-tarif" value="${data.tarif_per_sampel}" min="0" step="1000" oninput="recalculateTotals()">
            </div>
        </td>
        <td>
            <input type="number" name="items[${idx}][jumlah_sampel]" id="sampel_${idx}" class="form-control form-control-sm text-center fw-bold input-sampel" value="${data.jumlah_sampel}" min="1" oninput="recalculateTotals()">
        </td>
        <td>
            <div class="fw-bold text-end text-dark small" id="total_item_${idx}">Rp 0</div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRowUji(${idx})" title="Hapus Pengujian">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    recalculateTotals();
}

function onSelectMetode(idx, selectEl) {
    var opt = selectEl.options[selectEl.selectedIndex];
    if (opt && opt.value) {
        var tarif = parseFloat(opt.getAttribute('data-tarif')) || 0;
        var standar = opt.getAttribute('data-standar') || '';
        var durasiHari = parseInt(opt.getAttribute('data-durasi')) || 30;
        var durasiBulan = Math.max(1, Math.round(durasiHari / 30));

        document.getElementById('nama_' + idx).value = opt.text.split(' (')[0];
        document.getElementById('standar_' + idx).value = standar;
        document.getElementById('tarif_' + idx).value = tarif;
        document.getElementById('durasi_' + idx).value = durasiBulan;
    }
    recalculateTotals();
}

function removeRowUji(idx) {
    var tr = document.getElementById('row_' + idx);
    if (tr) {
        tr.remove();
    }
    recalculateTotals();
}

function recalculateTotals() {
    var rows = document.querySelectorAll('#tbodyUji tr');
    var totalBruto = 0;
    var maxDurasi = 1;

    rows.forEach(function(tr) {
        var tarifEl = tr.querySelector('.input-tarif');
        var sampelEl = tr.querySelector('.input-sampel');
        var durasiEl = tr.querySelector('input[name*="[durasi_bulan]"]');
        var itemTotalEl = tr.querySelector('[id^="total_item_"]');

        var tarif = parseFloat(tarifEl ? tarifEl.value : 0) || 0;
        var sampel = parseInt(sampelEl ? sampelEl.value : 1) || 1;
        var durasi = parseInt(durasiEl ? durasiEl.value : 1) || 1;

        var subTotal = tarif * sampel;
        totalBruto += subTotal;

        if (durasi > maxDurasi) {
            maxDurasi = durasi;
        }

        if (itemTotalEl) {
            itemTotalEl.textContent = 'Rp ' + subTotal.toLocaleString('id-ID');
        }
    });

    var diskonEl = document.getElementById('diskon_penawaran');
    var diskon = parseFloat(diskonEl ? diskonEl.value : 0) || 0;
    var totalNetto = Math.max(0, totalBruto - diskon);

    document.getElementById('lblTotalBruto').textContent = 'Rp ' + totalBruto.toLocaleString('id-ID');
    document.getElementById('lblTotalDiskon').textContent = '- Rp ' + diskon.toLocaleString('id-ID');
    document.getElementById('lblTotalNetto').textContent = 'Rp ' + totalNetto.toLocaleString('id-ID');
    document.getElementById('lblDurasiTerlama').textContent = maxDurasi + ' Bulan';
}

document.addEventListener('DOMContentLoaded', function() {
    if (INITIAL_ITEMS && INITIAL_ITEMS.length > 0) {
        INITIAL_ITEMS.forEach(function(item) {
            addRowUji(item);
        });
    } else {
        addRowUji(); // Baris pertama default
    }
});
</script>