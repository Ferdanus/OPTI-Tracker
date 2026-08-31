<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-folder-plus text-primary me-1"></i> Form Proses Klaim Surat</h2>
        <p class="text-muted small mb-0">Memproses surat masuk resmi menjadi data <strong>Order Layanan OPTI</strong>.</p>
    </div>
    <a href="<?= ($BASE) ?>/surat-masuk-opti" class="btn btn-outline-secondary">
        <i class="bi bg-arrow-left"></i> Kembali ke Daftar
    </a>
</div>

<div class="row g-4">
    <!-- Left Column: Letter Details -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-file-earmark-text text-primary me-2"></i>Rincian Surat Masuk</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Nomor Surat</label>
                    <div class="fw-bold text-dark"><?= ($sm['nomor_surat']) ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Tanggal Surat</label>
                    <div class="fw-bold text-dark"><i class="bi bi-calendar3 me-1"></i><?= (date('d M Y', strtotime($sm['tanggal_surat']))) ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Pengirim / Perusahaan</label>
                    <div class="fw-bold text-dark"><?= ($sm['nama_pengirim']) ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Perihal / Isi</label>
                    <div class="text-dark"><?= ($sm['perihal']) ?></div>
                </div>

                <?php if ($sm['file_path']): ?>
                    <div class="mb-0">
                        <label class="form-label text-muted small mb-1">Berkas Lampiran</label>
                        <div>
                            <a href="<?= ($BASE) ?>/<?= ($sm['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-danger px-3 py-1.5 d-inline-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-pdf"></i> Lihat Berkas PDF/Scan
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Process/Claim Form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-gear-fill text-primary me-2"></i>Pengaturan Klaim Order OPTI</h6>
            </div>
            <div class="card-body">
                <form action="<?= ($BASE) ?>/surat-masuk-opti/<?= ($sm['id']) ?>/proses" method="POST" id="prosesForm">
                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

                    <!-- PILIHAN TIPE KLIEN -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark d-block">Pendaftaran Customer / Mitra</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="klien_type" id="klien_type_exist" value="exist" checked>
                            <label class="form-check-label" for="klien_type_exist">Mitra Sudah Terdaftar (Database)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="klien_type" id="klien_type_new" value="new">
                            <label class="form-check-label" for="klien_type_new">Tambah Mitra Baru</label>
                        </div>
                    </div>

                    <!-- CONTAINER: KLIEN TERDAFTAR -->
                    <div id="container_klien_exist" class="mb-4">
                        <label class="form-label fw-semibold small text-muted" for="f_id_customer">Pilih Customer Terdaftar</label>
                        <select id="f_id_customer" name="id_customer" class="form-control">
                            <option value="">-- Cari Nama Perusahaan --</option>
                            <?php foreach (($customer_list?:[]) as $c): ?>
                                <option value="<?= ($c['id_customer']) ?>"><?= ($c['pt_cv']) ?>. <?= ($c['nmcustomer']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- CONTAINER: KLIEN BARU -->
                    <div id="container_klien_new" class="mb-4 d-none">
                        <div class="bg-light border rounded-3 p-3">
                            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-plus-circle me-1"></i> Form Customer Baru</h6>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-md-9">
                                    <label class="form-label fw-semibold small text-muted mb-1" for="new_nmcustomer">Nama Perusahaan / Industri</label>
                                    <input type="text" class="form-control form-control-sm" id="new_nmcustomer" name="nmcustomer" placeholder="Contoh: Selulosa Paperindo">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-muted mb-1" for="new_pt_cv">Bentuk Badan</label>
                                    <select class="form-select form-select-sm" id="new_pt_cv" name="pt_cv">
                                        <option value="PT">PT</option>
                                        <option value="CV">CV</option>
                                        <option value="Firma">Firma</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-muted mb-1" for="new_alamat">Alamat Lengkap</label>
                                <textarea class="form-control form-control-sm" id="new_alamat" name="alamatcustomer" rows="2" placeholder="Jl. Raya Utama No..."></textarea>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-muted mb-1" for="new_pic">Nama PIC Kontak</label>
                                    <input type="text" class="form-control form-control-sm" id="new_pic" name="contactperson" placeholder="Nama PIC">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-muted mb-1" for="new_hp">No. HP / WA Kontak</label>
                                    <input type="text" class="form-control form-control-sm" id="new_hp" name="nohpcontactperson_opti" placeholder="Contoh: 0812...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PENGATURAN LAYANAN -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted" for="jenis_layanan_opti">Jenis Layanan OPTI</label>
                            <select class="form-select" id="jenis_layanan_opti" name="jenis_layanan_opti" required>
                                <option value="selulosa" selected>OPTI Selulosa &amp; Pulp</option>
                                <option value="lingkungan">OPTI Lingkungan Industri</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted" for="keterangan">Keterangan / Catatan Internal</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="1" placeholder="Catatan opsional..."></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= ($BASE) ?>/surat-masuk-opti" class="btn btn-outline-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i> Proses Klaim &amp; Terbitkan Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi Tom Select untuk pencarian customer
        let clientSelect = new TomSelect('#f_id_customer', {
            create: false,
            sortField: {
                field: "text",
                direction: "asc"
            }
        });

        // Toggle form klien exist / new
        const optExist = document.getElementById('klien_type_exist');
        const optNew = document.getElementById('klien_type_new');
        const divExist = document.getElementById('container_klien_exist');
        const divNew = document.getElementById('container_klien_new');

        const inputNewName = document.getElementById('new_nmcustomer');
        const inputNewAlamat = document.getElementById('new_alamat');

        function toggleKlienForm() {
            if (optExist.checked) {
                divExist.classList.remove('d-none');
                divNew.classList.add('d-none');
                
                inputNewName.removeAttribute('required');
                inputNewAlamat.removeAttribute('required');
            } else {
                divExist.classList.add('d-none');
                divNew.classList.remove('d-none');
                
                inputNewName.setAttribute('required', '');
                inputNewAlamat.setAttribute('required', '');
            }
        }

        optExist.addEventListener('change', toggleKlienForm);
        optNew.addEventListener('change', toggleKlienForm);
    });
</script>
