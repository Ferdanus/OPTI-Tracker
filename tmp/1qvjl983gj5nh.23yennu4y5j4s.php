<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= ($BASE) ?>/order" class="btn-back">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark">
            <?= ($order ? 'Edit Order Layanan #' . $order['nomor_order'] : 'Pendaftaran Order Layanan Baru')."
" ?>
        </h2>
        <p class="text-muted small mb-0">Input permohonan jasa teknologi industri terintegrasi Standar Pelayanan Minimum (SPM) dan Lab Balai.</p>
    </div>
</div>

<form action="<?= ($order ? $BASE . '/order/' . $order['id'] . '/update' : $BASE . '/order/simpan') ?>" method="POST">
    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

    <div class="row g-4">
        <!-- ======================================================== -->
        <!-- SEKSI KIRI: MITRA, LAYANAN & LOKASI -->
        <!-- ======================================================== -->
        <div class="col-lg-6">
            
            <!-- KARTU 1: DATA MITRA & PERMOHONAN -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-building text-primary me-2"></i>Informasi Mitra & Judul Proyek</h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label">Pilih Mitra Industri / Customer <span class="text-danger">*</span></label>
                        <select name="id_customer" class="form-select searchable-select" placeholder="Ketik untuk mencari nama perusahaan..." required>
                            <option value="">Pilih Customer Terdaftar</option>
                            <?php foreach (($daftar_customer ?: ($daftar_klien ?: [])?:[]) as $c): ?>
                                <option value="<?= ($c['id_customer']) ?>" <?= ($order && $order['id_customer'] == $c['id_customer'] ? 'selected' : '') ?>>
                                    <?= ($c['nmcustomer']) ?> (<?= ($c['pt_cv']) ?>) - PIC: <?= ($c['contactperson'] ?: ($c['contactperson_opti'] ?: '-'))."
" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Belum terdaftar? <a href="<?= ($BASE) ?>/klien/tambah" target="_blank">Tambah Customer Baru</a></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Judul Permohonan Kegiatan / Proyek <span class="text-danger">*</span></label>
                        <textarea name="judul_kegiatan" class="form-control" rows="2" placeholder="Contoh: Optimalisasi Proses Pembuatan Pulp Daun Nanas untuk Kemasan Ramah Lingkungan" required><?= ($order['judul_kegiatan']) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Divisi Layanan OPTI <span class="text-danger">*</span></label>
                            <select name="jenis_layanan_opti" class="form-select" required>
                                <option value="selulosa" <?= ($order && $order['jenis_layanan_opti'] == 'selulosa' ? 'selected' : '') ?>>OPTI Selulosa (Katim: <?= ($katim_selulosa_nama) ?>)</option>
                                <option value="lingkungan" <?= ($order && $order['jenis_layanan_opti'] == 'lingkungan' ? 'selected' : '') ?>>OPTI Lingkungan (Katim: <?= ($katim_lingkungan_nama) ?>)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Masuk Permohonan</label>
                            <input type="date" name="tanggal_masuk" class="form-control" value="<?= ($order ? $order['tanggal_masuk'] : date('Y-m-d')) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Standar Pelayanan Minimum (SPM) & Durasi Baku <span class="text-danger">*</span></label>
                        <select name="spm_layanan" class="form-select" required>
                            <?php foreach (($spm_list ?: []?:[]) as $spmKey=>$spmVal): ?>
                                <option value="<?= ($spmKey) ?>" <?= ($order && $order['spm_layanan'] == $spmKey ? 'selected' : '') ?>>
                                    <?= ($spmKey) ?> (Standar: <?= ($spmVal) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- KARTU 2: TEMPAT & LABORATORIUM PELAKSANAAN -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-geo-alt text-primary me-2"></i>Tempat & Laboratorium Pelaksanaan</h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label d-block">Tipe Lokasi Pelaksanaan</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="lokasi_pelaksanaan" id="locInternal" value="internal" <?= (!$order || $order['lokasi_pelaksanaan'] == 'internal' ? 'checked' : '') ?> onchange="toggleLoc(this.value)">
                            <label class="form-check-label" for="locInternal">Laboratorium Internal BBSPJIS</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="lokasi_pelaksanaan" id="locLapangan" value="lapangan" <?= ($order && $order['lokasi_pelaksanaan'] == 'lapangan' ? 'checked' : '') ?> onchange="toggleLoc(this.value)">
                            <label class="form-check-label" for="locLapangan">On-Site / Lapangan Mitra</label>
                        </div>
                    </div>

                    <div class="mb-3" id="labInternalGroup">
                        <label class="form-label">Pilih Laboratorium Resmi Balai</label>
                        <select name="lab_internal" class="form-select">
                            <?php foreach (($lab_internal_list ?: ($lab_list ?: [])?:[]) as $lKey=>$labName): ?>
                                <option value="<?= ($lKey) ?>" <?= ($order && $order['lab_internal'] == $lKey ? 'selected' : '') ?>>
                                    <?= ($labName)."
" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="lokasiLapanganGroup" style="display: none;">
                        <label class="form-label">Alamat / Lokasi Lapangan Pabrik</label>
                        <input type="text" name="lokasi_lapangan" class="form-control" placeholder="Contoh: Pabrik PT ABC, Karawang, Jawa Barat" value="<?= ($order['lokasi_lapangan']) ?>">
                    </div>
                </div>
            </div>

        </div>

        <!-- ======================================================== -->
        <!-- SEKSI KANAN: SPESIFIKASI SAMPEL & BIAYA -->
        <!-- ======================================================== -->
        <div class="col-lg-6">
            
            <!-- KARTU 3: SPESIFIKASI SAMPEL UJI -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-eyedropper text-primary me-2"></i>Spesifikasi Teknis Sampel & Parameter</h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Sampel / Bahan</label>
                            <input type="text" name="jenis_sampel" class="form-control" placeholder="Contoh: Serat Pelepah Pisang" value="<?= ($order['jenis_sampel']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Volume / Berat Sampel</label>
                            <input type="text" name="volume_berat" class="form-control" placeholder="Contoh: 50 Kg / 3 Jerigen" value="<?= ($order['volume_berat']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Standar Acuan Uji / Metode</label>
                        <input type="text" name="tipe_data_sampel" class="form-control" placeholder="Contoh: SNI ISO 5263 / TAPPI T205" value="<?= ($order['tipe_data_sampel']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Karakteristik Fisik / Morfologi Serat</label>
                        <textarea name="karakteristik_serat" class="form-control" rows="2" placeholder="Contoh: Panjang serat rata-rata, daya saring, freeness..."><?= ($order['karakteristik_serat']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Karakteristik Kimia / Kandungan</label>
                        <textarea name="karakteristik_kimia" class="form-control" rows="2" placeholder="Contoh: Kadar selulosa alfa, lignin, holoselulosa, abu..."><?= ($order['karakteristik_kimia']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah / Satuan Pekerjaan</label>
                        <input type="text" name="jumlah_pekerjaan" class="form-control" placeholder="Contoh: 1 Paket Pengujian" value="<?= ($order ? $order['jumlah_pekerjaan'] : '1 Paket') ?>">
                    </div>
                </div>
            </div>

            <!-- KARTU 4: BIAYA & CATATAN -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-cash-coin text-primary me-2"></i>Alokasi Biaya & Keterangan</h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label">Estimasi Total Biaya Layanan (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="biaya" class="form-control" placeholder="0" value="<?= ($order ? $order['biaya'] : 0) ?>" required min="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan Tambahan</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan khusus dari mitra atau instruksi pengerjaan..."><?= ($order['keterangan']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- TOMBOL AKSI SIMPAN -->
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= ($BASE) ?>/order" class="btn btn-outline-secondary px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i> <?= ($order ? 'Perbarui Order' : 'Simpan Order Layanan')."
" ?>
                </button>
            </div>

        </div>
    </div>
</form>

<script>
function toggleLoc(val) {
    if (val === 'lapangan') {
        document.getElementById('labInternalGroup').style.display = 'none';
        document.getElementById('lokasiLapanganGroup').style.display = 'block';
    } else {
        document.getElementById('labInternalGroup').style.display = 'block';
        document.getElementById('lokasiLapanganGroup').style.display = 'none';
    }
}
// Init state
toggleLoc("<?= ($order && $order['lokasi_pelaksanaan'] == 'lapangan' ? 'lapangan' : 'internal') ?>");
</script>
