<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= ($BASE) ?>/po" class="btn-back">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark">Profil Pengguna</h2>
        <p class="text-muted small mb-0">Informasi akun Single Sign-On (SSO) internal Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Kartu Identitas Pengguna -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm text-center p-4">
            <div class="d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%); color: #ffffff; font-size: 2rem; font-weight: 800; box-shadow: 0 4px 12px rgba(136, 19, 55, 0.25);">
                <?php $initials = implode('', array_map(function($w) { return strtoupper($w[0] ?? ''); }, explode(' ', $profil['nama_user'] ?? 'U'))) ?>
                <?= (substr($initials, 0, 2))."
" ?>
            </div>

            <h5 class="fw-bold text-dark mb-1"><?= ($profil['nama_user']) ?></h5>
            <p class="text-muted small mb-2">@<?= ($profil['login']) ?></p>

            <div class="mb-3">
                <?php if ($profil['role'] == 'superadmin'): ?><span class="badge badge-pill-primary">Super Administrator</span><?php endif; ?>
                <?php if ($profil['role'] == 'admin_order'): ?><span class="badge badge-pill-info">Petugas Penerima Order</span><?php endif; ?>
                <?php if ($profil['role'] == 'ketua_tim'): ?><span class="badge badge-pill-success">Ketua Tim <?= ($profil['jenis_layanan_opti'] == 'selulosa' ? 'Selulosa' : 'Lingkungan') ?></span><?php endif; ?>
                <?php if ($profil['role'] == 'pejabat'): ?><span class="badge badge-pill-warning">Kepala Balai / PPK BLU</span><?php endif; ?>
                <?php if ($profil['role'] == 'tim_kerja'): ?><span class="badge badge-pill-secondary">Tim Analis Laboratorium</span><?php endif; ?>
                <?php if ($profil['role'] == 'admin_kontrak'): ?><span class="badge badge-pill-primary">Admin Kontrak Kerjasama</span><?php endif; ?>
            </div>

            <div class="border-top pt-3 text-start small">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">ID User Balai:</span>
                    <span class="fw-semibold">#<?= ($profil['id_user']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Bidang / Unit:</span>
                    <span class="fw-semibold"><?= ($profil['bidang'] ?: 'BBSPJI Selulosa') ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Status Akun:</span>
                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Aktif Terverifikasi</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Notifikasi Sistem:</span>
                    <span class="text-primary fw-bold"><i class="bi bi-bell-fill me-1"></i>Otomatis Aktif</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Form Profil Pengguna -->
    <div class="col-lg-8">
        <form action="<?= ($BASE) ?>/profil/simpan" method="POST">
            <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

            <!-- Kartu Informasi Kontak -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-person-gear text-primary me-2"></i>Informasi Akun & Data Kontak</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap Pejabat / Staf <span class="text-danger">*</span></label>
                        <input type="text" name="nama_user" class="form-control" value="<?= ($profil['nama_user']) ?>" required placeholder="Nama lengkap">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username SSO Balai (Read-Only)</label>
                            <input type="text" class="form-control bg-light text-muted" value="<?= ($profil['login']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nomor WhatsApp / HP</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                                <input type="text" name="no_hp" class="form-control" placeholder="081234567890" value="<?= ($profil['no_hp']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jenis Layanan OPTI</label>
                            <input type="text" class="form-control bg-light text-muted" value="<?= (strtoupper($profil['jenis_layanan_opti'] ?: 'SEMUA')) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hak Akses / Peran (Role)</label>
                            <input type="text" class="form-control bg-light text-muted" value="<?= (strtoupper($profil['role'] ?: 'INTERNAL')) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tombol Aksi Simpan -->
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= ($BASE) ?>/po" class="btn btn-outline-secondary px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan Profil
                </button>
            </div>
        </form>
    </div>
</div>
