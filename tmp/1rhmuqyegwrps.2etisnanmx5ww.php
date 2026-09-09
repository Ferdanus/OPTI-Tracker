<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= ($BASE) ?>/pembayaran" class="btn-back"><i class="bi bi-arrow-left"></i> Kembali</a>
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark">Catat Pembayaran</h2>
        <p class="text-muted small mb-0">Input penerimaan pembayaran termin / pelunasan dari mitra industri.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-receipt text-primary me-2"></i>Formulir Bukti Bayar</h6></div>
            <div class="card-body p-4">
                <form action="<?= ($BASE) ?>/pembayaran/simpan" method="POST" enctype="multipart/form-data" id="formPembayaran">
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

                    <div class="mb-1">
                        <label class="form-label">Pilih Order Layanan <span class="text-danger">*</span></label>
                        <select name="order_id" id="selectOrder" class="form-select searchable-select" placeholder="Ketik nomor order / nama mitra..." required>
                            <option value="">Pilih Order Layanan</option>
                            <?php foreach (($daftar_order?:[]) as $ord): ?>
                                <option value="<?= ($ord['id']) ?>"
                                        data-sisa="<?= ($ord['sisa_tagihan']) ?>"
                                        data-termin-berikutnya="<?= ($ord['termin_berikutnya']) ?>"
                                        <?= ($selected_order_id == $ord['id'] ? 'selected' : '') ?>>
                                    <?= ($ord['nomor_order']) ?> &mdash; <?= ($ord['nama_perusahaan'])."
" ?>
                                    (Sisa: Rp <?= (number_format($ord['sisa_tagihan'], 0, ',', '.')) ?>, Termin ke-<?= ($ord['termin_berikutnya']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-text mb-3" id="infoOrderTerpilih">Pilih order dulu buat lihat sisa tagihan &amp; saran termin.</div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tahap Termin Ke- <span class="text-danger">*</span></label>
                            <select name="termin_ke" id="selectTermin" class="form-select" required>
                                <option value="1">Termin 1 (Uang Muka / DP)</option>
                                <option value="2">Termin 2</option>
                                <option value="3">Termin 3 (Pelunasan)</option>
                                <option value="4">Termin 4</option>
                            </select>
                            <div class="form-text">Otomatis kesaran, tetap bisa diganti manual.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Bayar <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_bayar" class="form-control" value="<?= (date('Y-m-d')) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Jumlah Nominal (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="jumlah" id="inputJumlah" class="form-control" placeholder="0" required min="1000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Metode Pembayaran</label>
                            <select name="metode_pembayaran" class="form-select">
                                <option value="transfer_bank">Transfer Bank</option>
                                <option value="tunai">Tunai</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor Transaksi / NTPN (opsional)</label>
                        <input type="text" name="nomor_transaksi_ntpn" class="form-control" placeholder="Contoh: NTPN 1234567890ABCDEF">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload Bukti Pembayaran</label>
                        <input type="file" name="bukti_bayar" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">PDF, JPG, atau PNG. Maksimal 5MB.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= ($BASE) ?>/pembayaran" class="btn btn-outline-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var selectOrder = document.getElementById('selectOrder');
    var selectTermin = document.getElementById('selectTermin');
    var infoOrder = document.getElementById('infoOrderTerpilih');

    function syncDariOrderTerpilih() {
        var opt = selectOrder.options[selectOrder.selectedIndex];
        if (!opt || !opt.value) {
            infoOrder.textContent = 'Pilih order dulu buat lihat sisa tagihan & saran termin.';
            return;
        }
        var sisa = parseFloat(opt.dataset.sisa || '0');
        var terminSaran = parseInt(opt.dataset.terminBerikutnya || '1', 10);
        infoOrder.innerHTML = 'Sisa tagihan order ini: <strong>Rp ' + sisa.toLocaleString('id-ID') + '</strong> &mdash; saran termin ke-' + terminSaran + '.';
        if (selectTermin.querySelector('option[value="' + terminSaran + '"]')) {
            selectTermin.value = String(terminSaran);
        }
    }

    selectOrder.addEventListener('change', syncDariOrderTerpilih);
    if (selectOrder.value) syncDariOrderTerpilih();
});
</script>
