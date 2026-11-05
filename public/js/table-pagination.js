/**
 * Universal Table Pagination (SILOPTI)
 * Otomatis membatasi tampilan semua tabel data menjadi maksimal 10 data per halaman
 * Dilengkapi navigasi halaman dinamis DI ATAS DAN DI BAWAH TABEL,
 * info rentang data, dan sinkronisasi otomatis dengan Tab Bootstrap & Live Search.
 */
(function () {
    'use strict';

    var DEFAULT_PAGE_SIZE = 10;

    /**
     * Cek apakah tabel harus dikecualikan dari pagination otomatis
     */
    function isExcludedTable(table) {
        if (!table || table.nodeType !== 1) return true;
        if (table.getAttribute('data-no-paginate') === 'true') return true;
        if (table.classList.contains('no-pagination') || table.classList.contains('no-paginate')) return true;
        if (table.classList.contains('table-kalkulasi') || table.classList.contains('table-borderless')) return true;
        if (table.classList.contains('mk-table') || table.classList.contains('dpk-table') || table.classList.contains('info')) return true;

        // Jangan paginasi tabel formulir dokumen pratinjau cetak
        if (table.closest('.po-doc-sheet') || table.closest('.dpk-sheet') || table.closest('.kontrak-doc')) return true;

        // Pengecualian tabel ringkasan / widget dashboard
        if (table.classList.contains('dash-table') || table.closest('.dash-card')) return true;

        // Harus punya thead dan tbody
        var tbody = table.querySelector('tbody');
        var thead = table.querySelector('thead');
        if (!tbody || !thead) return true;

        // Pengecualian tabel form input dinamis spesifik
        if (table.id === 'tableUjiLingkungan' || table.classList.contains('table-form-kalkulasi')) return true;

        return false;
    }

    /**
     * Ambil baris-baris data dari tbody (mengabaikan baris kosong / pesan kosong)
     */
    function getDataRows(tbody) {
        if (!tbody) return [];
        var allRows = Array.from(tbody.querySelectorAll(':scope > tr'));
        return allRows.filter(function (tr) {
            if (tr.classList.contains('no-search-result-row')) return false;
            if (tr.classList.contains('table-footer-row')) return false;
            if (tr.classList.contains('table-no-data')) return false;
            if (tr.classList.contains('empty-row')) return false;

            // Jika ada tr dengan colspan >= 3 (biasanya placeholder "Tidak ada data")
            var firstTd = tr.querySelector('td');
            if (firstTd && firstTd.getAttribute('colspan')) {
                var cs = parseInt(firstTd.getAttribute('colspan'), 10) || 1;
                if (cs >= 3) return false;
            }
            return true;
        });
    }

    /**
     * Cek apakah baris disembunyikan oleh live search / filter eksternal
     */
    function isRowHiddenByExternalFilter(tr) {
        if (tr.classList.contains('search-hidden')) return true;
        if (tr.dataset.searchMatch === '0' || tr.dataset.filtered === 'false') return true;
        // Jika baris display: none tetapi BUKAN disembunyikan oleh pagination
        if (tr.style.display === 'none' && !tr.classList.contains('table-pg-hidden')) {
            return true;
        }
        return false;
    }

    /**
     * Cek apakah kolom pertama tabel adalah kolom Nomor Urut (No)
     */
    function checkHasNoColumn(table) {
        var firstTh = table.querySelector('thead tr th:first-child');
        if (!firstTh) return false;
        var text = firstTh.textContent.trim().toLowerCase();
        return (text === 'no' || text === 'no.' || text === '#' || text === 'nomor');
    }

    /**
     * Update teks pada sel nomor urut baris
     */
    function updateNoCellText(cell, num) {
        if (!cell) return;
        var numSpan = cell.querySelector('.row-number, .nomor-urut');
        if (numSpan) {
            numSpan.textContent = num;
            return;
        }

        // Jika hanya teks biasa di dalam cell
        if (cell.children.length === 0) {
            cell.textContent = num;
        } else {
            // Jika ada icon/sub-elemen, ganti text node pertama
            var firstChild = cell.firstChild;
            if (firstChild && firstChild.nodeType === 3) {
                firstChild.nodeValue = num + ' ';
            } else {
                cell.textContent = num;
            }
        }
    }

    /**
     * Mendapatkan elemen top bar pagination (di atas tabel)
     */
    function getTopWrapper(table) {
        var parent = table.closest('.table-responsive') || table;
        var prev = parent.previousElementSibling;
        if (prev && prev.classList.contains('table-pagination-top')) {
            return prev;
        }
        return null;
    }

    /**
     * Mendapatkan elemen bottom bar pagination (di bawah tabel)
     */
    function getBottomWrapper(table) {
        var parent = table.closest('.table-responsive') || table;
        var next = parent.nextElementSibling;
        if (next && next.classList.contains('table-pagination-bottom')) {
            return next;
        }
        return null;
    }

    /**
     * Hapus wrapper pagination bila data kosong
     */
    function removePaginationWrappers(table) {
        var top = getTopWrapper(table);
        if (top && top.parentNode) top.parentNode.removeChild(top);
        var bottom = getBottomWrapper(table);
        if (bottom && bottom.parentNode) bottom.parentNode.removeChild(bottom);
    }

    /**
     * Buat HTML tombol-tombol pagination
     */
    function buildPaginationNavHtml(totalPages, currentPage) {
        if (totalPages <= 1) return '';

        var html = '<nav class="table-pagination-nav ms-auto" aria-label="Navigasi Halaman Tabel">';
        html += '<ul class="pagination pagination-sm mb-0 align-items-center gap-1">';

        // Tombol Sebelumnya
        var prevDisabled = (currentPage <= 1) ? ' disabled' : '';
        html += '<li class="page-item' + prevDisabled + '">';
        html += '<a class="page-link table-pg-btn shadow-none" href="javascript:void(0)" data-page="' + (currentPage - 1) + '" title="Halaman Sebelumnya">&laquo;</a>';
        html += '</li>';

        // Sliding Window nomor halaman
        var pages = [];
        for (var p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || (p >= currentPage - 2 && p <= currentPage + 2)) {
                pages.push(p);
            } else if (pages[pages.length - 1] !== '...') {
                pages.push('...');
            }
        }

        pages.forEach(function (p) {
            if (p === '...') {
                html += '<li class="page-item disabled"><span class="page-link border-0 text-muted shadow-none bg-transparent">&hellip;</span></li>';
            } else if (p === currentPage) {
                html += '<li class="page-item active" aria-current="page"><span class="page-link table-pg-btn shadow-none fw-bold">' + p + '</span></li>';
            } else {
                html += '<li class="page-item"><a class="page-link table-pg-btn shadow-none" href="javascript:void(0)" data-page="' + p + '">' + p + '</a></li>';
            }
        });

        // Tombol Berikutnya
        var nextDisabled = (currentPage >= totalPages) ? ' disabled' : '';
        html += '<li class="page-item' + nextDisabled + '">';
        html += '<a class="page-link table-pg-btn shadow-none" href="javascript:void(0)" data-page="' + (currentPage + 1) + '" title="Halaman Berikutnya">&raquo;</a>';
        html += '</li>';

        html += '</ul></nav>';
        return html;
    }

    /**
     * Render antarmuka UI pagination ganda (ATAS dan BAWAH tabel)
     */
    function renderPaginationUI(table, totalItems, totalPages, currentPage, pageSize, startIdx, endIdx) {
        var parent = table.closest('.table-responsive') || table;
        var topWrapper = getTopWrapper(table);
        var bottomWrapper = getBottomWrapper(table);

        // Jika data kosong, sembunyikan atau hapus kedua wrapper
        if (totalItems === 0) {
            if (topWrapper) topWrapper.style.display = 'none';
            if (bottomWrapper) bottomWrapper.style.display = 'none';
            return;
        }

        // 1. Inisialisasi Top Wrapper jika belum ada
        if (!topWrapper) {
            topWrapper = document.createElement('div');
            topWrapper.className = 'table-pagination-wrapper table-pagination-top px-3 py-2 border-bottom d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 bg-white';
            parent.parentNode.insertBefore(topWrapper, parent);
        }

        // 2. Inisialisasi Bottom Wrapper jika belum ada
        if (!bottomWrapper) {
            bottomWrapper = document.createElement('div');
            bottomWrapper.className = 'table-pagination-wrapper table-pagination-bottom card-footer bg-white border-top px-3 py-2.5 d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 shadow-xs';
            parent.parentNode.insertBefore(bottomWrapper, parent.nextSibling);
        }

        var displayStart = Math.min(startIdx + 1, totalItems);
        var displayEnd = Math.min(endIdx, totalItems);

        var infoHtml = '';
        if (totalPages <= 1) {
            infoHtml = '<i class="bi bi-layers-half text-secondary opacity-75 me-1.5"></i>' +
                       '<span>Menampilkan&nbsp;<strong class="text-dark fw-bold">' + totalItems + '</strong>&nbsp;data</span>';
            // Jika data <= 10, sembunyikan top bar agar ringkas, dan tampilkan info saja di bottom bar
            topWrapper.style.display = 'none';
            bottomWrapper.style.display = 'flex';
        } else {
            infoHtml = '<i class="bi bi-layers-half text-secondary opacity-75 me-1.5"></i>' +
                       '<span>Menampilkan&nbsp;<strong class="text-dark fw-bold">' + displayStart + ' &ndash; ' + displayEnd + '</strong>&nbsp;dari&nbsp;<strong class="text-dark fw-bold">' + totalItems + '</strong>&nbsp;data</span>';
            topWrapper.style.display = 'flex';
            bottomWrapper.style.display = 'flex';
        }

        var navHtml = buildPaginationNavHtml(totalPages, currentPage);

        // Isi konten kedua bar dengan gap yang jelas antar-elemen teks
        topWrapper.innerHTML = '<div class="table-pagination-info small text-muted d-flex align-items-center flex-wrap gap-1">' + infoHtml + '</div>' + navHtml;
        bottomWrapper.innerHTML = '<div class="table-pagination-info small text-muted d-flex align-items-center flex-wrap gap-1">' + infoHtml + '</div>' + navHtml;

        // Pasang event listener untuk semua tombol (baik di bar atas maupun bar bawah)
        [topWrapper, bottomWrapper].forEach(function (wrap) {
            var buttons = wrap.querySelectorAll('a.table-pg-btn');
            buttons.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var targetPage = parseInt(this.getAttribute('data-page'), 10);
                    if (targetPage >= 1 && targetPage <= totalPages && targetPage !== currentPage) {
                        table.setAttribute('data-current-page', targetPage);
                        setupPagination(table);

                        // Jika diklik dari navigasi BAWAH, scroll halus kembali ke atas tabel
                        if (wrap.classList.contains('table-pagination-bottom')) {
                            var rect = parent.getBoundingClientRect();
                            if (rect.top < 70) {
                                parent.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }
                    }
                });
            });
        });
    }

    /**
     * Setup atau perbarui pagination pada satu tabel
     */
    function setupPagination(table) {
        if (isExcludedTable(table)) return;

        var tbody = table.querySelector('tbody');
        if (!tbody) return;

        var pageSize = parseInt(table.getAttribute('data-page-size'), 10) || DEFAULT_PAGE_SIZE;
        var currentPage = parseInt(table.getAttribute('data-current-page'), 10) || 1;

        var rows = getDataRows(tbody);
        if (rows.length === 0) {
            removePaginationWrappers(table);
            return;
        }

        // Ambil baris yang tidak disembunyikan oleh filter eksternal
        var visibleRows = rows.filter(function (tr) {
            return !isRowHiddenByExternalFilter(tr);
        });

        var totalItems = visibleRows.length;
        var totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

        if (currentPage > totalPages) {
            currentPage = totalPages;
            table.setAttribute('data-current-page', currentPage);
        } else if (currentPage < 1) {
            currentPage = 1;
            table.setAttribute('data-current-page', 1);
        }

        var startIndex = (currentPage - 1) * pageSize;
        var endIndex = startIndex + pageSize;
        var hasNoCol = checkHasNoColumn(table);

        visibleRows.forEach(function (tr, idx) {
            if (idx >= startIndex && idx < endIndex) {
                tr.classList.remove('table-pg-hidden');
                tr.style.display = '';

                // Sinkronkan nomor urut kolom pertama jika ada
                if (hasNoCol) {
                    var noCell = tr.querySelector('td:first-child');
                    if (noCell && !noCell.hasAttribute('data-no-static')) {
                        var nomorUrut = idx + 1;
                        if (!noCell.hasAttribute('data-original-no')) {
                            noCell.setAttribute('data-original-no', noCell.textContent.trim());
                        }
                        updateNoCellText(noCell, nomorUrut);
                    }
                }
            } else {
                tr.classList.add('table-pg-hidden');
                tr.style.display = 'none';
            }
        });

        // Tampilkan navigasi pagination ganda (atas dan bawah)
        renderPaginationUI(table, totalItems, totalPages, currentPage, pageSize, startIndex, endIndex);
    }

    /**
     * Inisialisasi semua tabel di dalam dokumen atau container tertentu
     */
    function initAll(container) {
        var root = container || document;
        var tables = root.querySelectorAll('table.table, .table-responsive > table');
        tables.forEach(function (table) {
            setupPagination(table);
        });
    }

    // Expose global API
    window.UniversalTablePagination = {
        init: initAll,
        setup: setupPagination,
        refresh: function (tableOrSelector) {
            if (!tableOrSelector) {
                initAll();
                return;
            }
            var el = (typeof tableOrSelector === 'string') ? document.querySelector(tableOrSelector) : tableOrSelector;
            if (el) {
                setupPagination(el);
            }
        }
    };

    // Jalankan otomatis saat DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll();
        });
    } else {
        initAll();
    }

    // Refresh saat tab bootstrap berpindah
    document.addEventListener('shown.bs.tab', function (e) {
        var targetSelector = e.target.getAttribute('data-bs-target') || e.target.getAttribute('href');
        if (targetSelector) {
            var targetPane = document.querySelector(targetSelector);
            if (targetPane) {
                initAll(targetPane);
            }
        }
    });

    // Refresh saat modal bootstrap dibuka
    document.addEventListener('shown.bs.modal', function (e) {
        var modal = e.target;
        if (modal) {
            initAll(modal);
        }
    });

})();
