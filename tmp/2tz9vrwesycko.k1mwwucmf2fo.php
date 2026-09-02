<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1 text-dark font-display"><i class="bi bi-bell-fill text-primary me-2"></i>Pusat Pemberitahuan &amp; Pesan Masuk</h2>
        <p class="text-muted small mb-0">Riwayat notifikasi alur kerja, penugasan kaji kelayakan, dan pembaruan dokumen OPTI Tracker.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1.5 rounded-pill px-3 shadow-xs" onclick="window.markAllNotifRead()">
            <i class="bi bi-check2-all"></i> Tandai Semua Sudah Dibaca
        </button>
    </div>
</div>

<?php $totalSemua = count($daftar_notifikasi_semua ?: []);
    $totalUnread = 0;
    $totalRead = 0;
    if (!empty($daftar_notifikasi_semua)) {
        foreach ($daftar_notifikasi_semua as $notifItem) {
            if (empty($notifItem['is_read'])) {
                $totalUnread++;
            } else {
                $totalRead++;
            }
        }
    } ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center border-bottom gap-2">
        <div class="d-flex align-items-center gap-3">
            <h6 class="m-0 fw-bold text-dark font-display">Daftar Pemberitahuan</h6>
            <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 font-monospace" style="font-size: 0.72rem;" id="totalNotifLabel"><?= ($totalSemua) ?> Total Pesan</span>
        </div>
        <div class="btn-group btn-group-sm p-0.5 bg-light rounded-pill border filter-btn-group" role="group">
            <button type="button" class="btn btn-white rounded-pill px-3 fw-semibold active shadow-xs" id="btnFilterSemua" onclick="filterNotif('all', this)" style="font-size: 0.75rem;">
                Semua <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1" style="font-size: 0.65rem;"><?= ($totalSemua) ?></span>
            </button>
            <button type="button" class="btn btn-transparent text-secondary rounded-pill px-3 fw-semibold" id="btnFilterUnread" onclick="filterNotif('unread', this)" style="font-size: 0.75rem;">
                Belum Dibaca <span class="badge bg-danger text-white rounded-pill ms-1" style="font-size: 0.65rem;" id="unreadTabCount"><?= ($totalUnread) ?></span>
            </button>
            <button type="button" class="btn btn-transparent text-secondary rounded-pill px-3 fw-semibold" id="btnFilterRead" onclick="filterNotif('read', this)" style="font-size: 0.75rem;">
                Riwayat <span class="badge bg-light text-muted border rounded-pill ms-1" style="font-size: 0.65rem;"><?= ($totalRead) ?></span>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($totalSemua > 0): ?>
            
                <div class="list-group list-group-flush" id="fullNotifList">
                    <?php foreach (($daftar_notifikasi_semua?:[]) as $item): ?>
                        <div class="list-group-item p-3.5 notif-row-item <?= ($item['is_read'] ? 'bg-white is-read' : 'bg-light bg-opacity-40 is-unread') ?> d-flex align-items-start gap-3 border-bottom transition-all" data-is-read="<?= ($item['is_read'] ? '1' : '0') ?>">
                            <div class="rounded-circle p-2.5 d-flex align-items-center justify-content-center flex-shrink-0 <?= ($item['tipe'] == 'success' ? 'bg-success-subtle text-success' : ($item['tipe'] == 'warning' ? 'bg-warning-subtle text-warning' : ($item['tipe'] == 'primary' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info'))) ?>" style="width: 44px; height: 44px;">
                                <i class="bi <?= ($item['icon'] ?: 'bi-bell-fill') ?> fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-bold text-dark mb-0 font-display <?= ($item['is_read'] ? '' : 'text-primary') ?>" style="font-size: 0.9rem;"><?= ($item['judul']) ?></h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <small class="text-muted" style="font-size: 0.72rem;"><i class="bi bi-clock me-1"></i><?= ($item['time_ago']) ?></small>
                                        <?php if (!$item['is_read']): ?>
                                            <span class="badge bg-danger rounded-pill px-2 py-0.5 notif-baru-badge" style="font-size: 0.65rem;">Baru</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="text-secondary small mb-2.5" style="font-size: 0.8rem; line-height: 1.4;"><?= ($item['pesan']) ?></p>
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="<?= ($BASE) ?><?= ($item['link_url']) ?>" onclick="window.markNotifRead(<?= ($item['id']) ?>, '<?= ($BASE) ?><?= ($item['link_url']) ?>')" class="btn btn-sm btn-primary py-1 px-3 rounded-pill text-white text-decoration-none fw-semibold shadow-xs d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;">
                                            Buka Dokumen <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                    <span class="text-muted small" style="font-size: 0.72rem;">Oleh: <strong><?= ($item['created_by_name'] ?: 'Sistem OPTI') ?></strong></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Dynamic Empty Box for Filter -->
                <div class="text-center py-5 text-muted" id="notifEmptyFilter" style="display: none;">
                    <i class="bi bi-inbox fs-1 text-muted opacity-40 d-block mb-2"></i>
                    <h6 class="fw-bold text-dark" id="notifEmptyFilterTitle">Tidak Ada Pesan</h6>
                    <p class="small text-muted mb-0" id="notifEmptyFilterDesc">Tidak ditemukan pesan pada kategori filter yang dipilih.</p>
                </div>
            
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash fs-1 text-muted opacity-40 d-block mb-2"></i>
                    <h6 class="fw-bold text-dark">Belum Ada Pemberitahuan</h6>
                    <p class="small text-muted mb-0">Pemberitahuan alur kerja akan muncul otomatis ketika terdapat tugas atau berkas baru.</p>
                </div>
            
        <?php endif; ?>
    </div>
</div>

<script>
    function filterNotif(type, btn) {
        // Toggle Button Active State
        document.querySelectorAll('.filter-btn-group .btn').forEach(function(b) {
            b.classList.remove('btn-white', 'active', 'shadow-xs');
            b.classList.add('btn-transparent', 'text-secondary');
        });
        btn.classList.remove('btn-transparent', 'text-secondary');
        btn.classList.add('btn-white', 'active', 'shadow-xs');

        // Filter Rows
        var rows = document.querySelectorAll('.notif-row-item');
        var visibleCount = 0;

        rows.forEach(function(r) {
            var isRead = r.getAttribute('data-is-read') === '1';
            var shouldShow = false;

            if (type === 'all') {
                shouldShow = true;
            } else if (type === 'unread') {
                shouldShow = !isRead;
            } else if (type === 'read') {
                shouldShow = isRead;
            }

            if (shouldShow) {
                r.style.setProperty('display', 'flex', 'important');
                visibleCount++;
            } else {
                r.style.setProperty('display', 'none', 'important');
            }
        });

        // Handle Empty State
        var emptyBox = document.getElementById('notifEmptyFilter');
        var emptyTitle = document.getElementById('notifEmptyFilterTitle');
        var emptyDesc = document.getElementById('notifEmptyFilterDesc');

        if (emptyBox) {
            if (visibleCount === 0 && rows.length > 0) {
                emptyBox.style.display = 'block';
                if (type === 'unread') {
                    emptyTitle.textContent = 'Semua Pesan Sudah Dibaca';
                    emptyDesc.textContent = 'Tidak ada pemberitahuan baru yang belum dibaca saat ini.';
                } else if (type === 'read') {
                    emptyTitle.textContent = 'Belum Ada Riwayat Pesan';
                    emptyDesc.textContent = 'Pesan yang sudah Anda baca akan muncul di tab riwayat ini.';
                } else {
                    emptyTitle.textContent = 'Tidak Ada Pesan';
                    emptyDesc.textContent = 'Tidak ditemukan notifikasi.';
                }
            } else {
                emptyBox.style.display = 'none';
            }
        }
    }
</script>