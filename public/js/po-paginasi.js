/* ============================================================================
   PoPaginasi: pemisah halaman A4 pada pratinjau dokumen PO (halaman terpisah, seperti PDF)
   Dipakai di form PO, slide review (Daftar Review / Validasi PPK BLU / Revisi PPK BLU),
   Validasi Tim Mitra, dan Humas.

   Dokumen tetap satu .po-doc-sheet. Di titik potong halaman disisipkan pita .po-pgbreak
   (sisa halaman + celah + margin atas halaman berikutnya). Blok yang tidak muat pindah utuh,
   tabel dipecah per baris (header diulang; minimal 2 baris di tiap sisi, sama dengan aturan PDF), judul bagian ikut isi di bawahnya, blok dengan
   break-inside:avoid (mis. tanda tangan) tidak dipecah. Elemen opsi.sembunyikan (mis. kolom
   catatan review) tidak dihitung, jadi nomor halaman tetap sama dengan PDF.
   ============================================================================ */
(function () {
    var MM = 96 / 25.4, PAGE_H = 297 * MM, MARGIN = 20 * MM, GAP = 28, STRIDE = PAGE_H + GAP;

    function warnaLatar(sheet, opsi) {
        if (opsi && opsi.latar) { return opsi.latar; }
        var el = sheet.parentElement;
        while (el) {
            var c = '';
            try { c = getComputedStyle(el).backgroundColor; } catch (e) {}
            if (c && c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent' && c !== 'rgb(255, 255, 255)') {
                var m = c.match(/\d+(\.\d+)?/g);
                // latar gelap (mis. pratinjau form) dipakai apa adanya; latar terang diganti abu-abu supaya celah antar halaman terlihat
                if (m && m.length >= 3 && (0.299 * m[0] + 0.587 * m[1] + 0.114 * m[2]) < 140) { return c; }
                break;
            }
            el = el.parentElement;
        }
        return '#cbd5e1';
    }

    function jalankan(sheet, opsi) {
        opsi = opsi || {};
        if (!sheet || sheet.offsetHeight === 0) { return 0; }

        if (opsi.pulih) {   // dijalankan ulang pada isi yang sama (form PO)
            if (typeof sheet.__asli === 'string') { sheet.innerHTML = sheet.__asli; } else { sheet.__asli = sheet.innerHTML; }
        }
        Array.prototype.slice.call(sheet.querySelectorAll('.po-pgnum')).forEach(function (n) { n.parentNode.removeChild(n); });

        var bgView = warnaLatar(sheet, opsi);
        var skala = (sheet.getBoundingClientRect().width / sheet.offsetWidth) || 1;
        var halaman = 0, labels = [];

        // sembunyikan sementara elemen yang tidak ikut dokumen (mis. kolom catatan)
        var disembunyikan = [];
        if (opsi.sembunyikan) {
            Array.prototype.forEach.call(sheet.querySelectorAll(opsi.sembunyikan), function (e) {
                disembunyikan.push([e, e.style.display]);
                e.style.display = 'none';
            });
        }

        function atas(el) { return (el.getBoundingClientRect().top - sheet.getBoundingClientRect().top) / skala; }
        function batas() { return halaman * STRIDE + PAGE_H - MARGIN; }
        function awalHal() { return halaman * STRIDE + MARGIN; }

        function sisip(sebelum, parent) {
            var b = document.createElement('div');
            b.className = 'po-pgbreak';
            b.setAttribute('aria-hidden', 'true');
            b.style.cssText = 'height:0;position:relative;clear:both;box-sizing:border-box;pointer-events:none;user-select:none;';
            parent.insertBefore(b, sebelum);
            var y = atas(b);
            halaman = Math.max(halaman, Math.floor((y - 1) / STRIDE));
            var mt = 0;
            if (sebelum) { mt = parseFloat(getComputedStyle(sebelum).marginTop) || 0; }
            var a = Math.max(0, halaman * STRIDE + PAGE_H - y);
            var tinggi = Math.max(a + GAP + 2, (halaman + 1) * STRIDE + MARGIN - y - mt);
            var dx = (b.getBoundingClientRect().left - sheet.getBoundingClientRect().left) / skala;
            b.style.marginLeft = (-dx) + 'px';
            b.style.width = sheet.offsetWidth + 'px';
            b.style.height = tinggi + 'px';
            b.style.background = 'linear-gradient(to bottom,#fff 0,#fff ' + a + 'px,' + bgView + ' ' + a + 'px,' + bgView + ' ' + (a + GAP) + 'px,#fff ' + (a + GAP) + 'px,#fff 100%)';
            var lb = document.createElement('div');
            lb.className = 'po-pgnum';
            lb.style.cssText = 'position:absolute;left:0;right:0;text-align:center;font:9pt "Times New Roman",Times,serif;color:#475569;top:' + Math.max(0, a - MARGIN + 18) + 'px;';
            b.appendChild(lb);
            labels.push(lb);
            halaman++;
            return b;
        }

        function judul(el) {
            return !!(el.classList && (el.classList.contains('po-section-title') || el.classList.contains('rvw-sec-h') ||
                (el.classList.contains('po-body') && /font-weight:\s*700/.test(el.getAttribute('style') || ''))));
        }
        function bisaTurun(el) {
            if (el.tagName === 'TABLE') { return false; }
            var cs0 = getComputedStyle(el);
            if (cs0.breakInside === 'avoid' || cs0.pageBreakInside === 'avoid' || /break-inside:\s*avoid/.test(el.getAttribute('style') || '')) { return false; }
            var d = cs0.display;
            if (d !== 'block' && d !== 'list-item') { return false; }
            for (var i = 0; i < el.children.length; i++) {
                var dc = getComputedStyle(el.children[i]).display;
                if (dc === 'block' || dc === 'list-item' || dc === 'table') { return true; }
            }
            return false;
        }
        // tinggi minimum yang harus muat bersama judul di atasnya
        function ukuranAwal(nx) {
            var t = nx.tagName === 'TABLE' ? nx : ((nx.firstElementChild && nx.firstElementChild.tagName === 'TABLE') ? nx.firstElementChild : null);
            if (t && t.rows.length) {
                var nBody = t.rows.length - (t.tHead ? t.tHead.rows.length : 0);
                if (!t.classList.contains('rab-table') && nBody < 4) { return nx.offsetHeight; }   // tabel sependek ini tidak dipecah: judul ikut seluruh tabel
                var tb = Math.min(t.rows.length - 1, (t.tHead ? t.tHead.rows.length : 0) + 1);
                var r = t.rows[tb];
                return (atas(r) + r.offsetHeight) - atas(nx);
            }
            if (bisaTurun(nx) && nx.firstElementChild) {
                var f = nx.firstElementChild;
                return Math.min(nx.offsetHeight, (atas(f) + f.offsetHeight) - atas(nx));
            }
            return nx.offsetHeight;
        }
        function amanIdx(rows, idx) {   // jangan memutus rowspan
            var ulang = true;
            while (ulang && idx > 0) {
                ulang = false;
                for (var r = 0; r < idx; r++) {
                    for (var c = 0; c < rows[r].cells.length; c++) {
                        if (rows[r].cells[c].rowSpan > 1 && r + rows[r].cells[c].rowSpan > idx) { idx = r; ulang = true; break; }
                    }
                    if (ulang) { break; }
                }
            }
            return idx;
        }
        function pecahTabel(tbl, parent) {
            for (var guard = 0; guard < 40; guard++) {
                var head = tbl.tHead ? tbl.tHead.rows.length : 0;
                var rows = Array.prototype.slice.call(tbl.rows, head);
                var idx = -1;
                for (var j = 0; j < rows.length; j++) {
                    if (atas(rows[j]) + rows[j].offsetHeight > batas() + 0.5) { idx = j; break; }
                }
                if (idx < 0) { return; }
                // Aturan PDF: judul kolom + minimal 2 baris pertama dan minimal 2 baris terakhir tidak dipisah (tabel RAB: 1 baris).
                var minAwal = tbl.classList.contains('rab-table') ? 1 : 2, minAkhir = minAwal;
                idx = amanIdx(rows, idx);
                if (idx > rows.length - minAkhir) { idx = amanIdx(rows, Math.max(0, rows.length - minAkhir)); }
                if (idx < minAwal) {
                    if (atas(tbl) > awalHal() + 8) { sisip(tbl, parent); continue; }   // seluruh tabel (dan judulnya) pindah ke halaman berikut
                    idx = Math.max(1, Math.min(minAwal, rows.length - 1));              // sudah di awal halaman: paksa seperlunya
                    if (rows.length < 2) { return; }
                }
                var baru = tbl.cloneNode(false);
                var cg = tbl.querySelector('colgroup');
                if (cg) { baru.appendChild(cg.cloneNode(true)); }
                if (tbl.tHead) { baru.appendChild(tbl.tHead.cloneNode(true)); }
                var tb = document.createElement('tbody');
                rows.slice(idx).forEach(function (r) { tb.appendChild(r); });
                baru.appendChild(tb);
                parent.insertBefore(baru, tbl.nextSibling);
                tbl.style.marginBottom = '0';
                sisip(baru, parent);
                tbl = baru;
            }
        }
        function susun(parent) {
            var anak = Array.prototype.slice.call(parent.children);
            for (var i = 0; i < anak.length; i++) {
                var el = anak[i];
                if (el.classList.contains('po-pgbreak') || el.classList.contains('po-pgnum') || el.classList.contains('po-badge-status')) { continue; }
                var cs = getComputedStyle(el);
                if (cs.display === 'none' || cs.position === 'absolute' || cs.position === 'fixed') { continue; }
                var y = atas(el), tinggi = el.offsetHeight;

                if (judul(el)) {      // judul ikut blok di bawahnya
                    var nx = null;
                    for (var q = i + 1; q < anak.length; q++) {
                        if (getComputedStyle(anak[q]).display !== 'none') { nx = anak[q]; break; }
                    }
                    if (nx) {
                        var perlu = (atas(nx) - y) + ukuranAwal(nx);
                        if (y + perlu > batas() + 0.5 && y > awalHal() + 8) { sisip(el, parent); }
                        continue;
                    }
                }
                if (y + tinggi <= batas() + 0.5) { continue; }
                if (y >= batas() - 4) { if (y > awalHal() + 8) { sisip(el, parent); } continue; }
                if (el.tagName === 'TABLE') { pecahTabel(el, parent); continue; }
                if (bisaTurun(el) && tinggi > 60) { susun(el); continue; }
                if (tinggi <= PAGE_H - 2 * MARGIN && y > awalHal() + 8) { sisip(el, parent); }
            }
        }

        var posLama = sheet.style.position;
        sheet.style.position = 'relative';
        sheet.style.minHeight = '';
        try { susun(sheet); } finally {
            disembunyikan.forEach(function (p) { p[0].style.display = p[1]; });
        }

        var n = halaman + 1;
        sheet.style.minHeight = (n * PAGE_H + (n - 1) * GAP) + 'px';
        labels.forEach(function (lb, k) { lb.textContent = 'Halaman ' + (k + 1) + ' dari ' + n; });
        var akhir = document.createElement('div');
        akhir.className = 'po-pgnum';
        akhir.textContent = 'Halaman ' + n + ' dari ' + n;
        akhir.style.cssText = 'position:absolute;left:0;right:0;bottom:' + (MARGIN - 18 - 16) + 'px;text-align:center;font:9pt "Times New Roman",Times,serif;color:#475569;pointer-events:none;';
        sheet.appendChild(akhir);
        sheet.__paginasiJumlah = n;
        return n;
    }

    // Jalankan sekarang; kalau dokumen sedang tersembunyi (ukuran 0), tunggu sampai tampil.
    function pasang(sheet, opsi, sesudah) {
        if (!sheet) { return; }
        var n = 0;
        try { n = jalankan(sheet, opsi); } catch (e) { if (window.console) { console.error('Paginasi PO gagal:', e); } return; }
        if (n) { if (sesudah) { sesudah(n); } return; }
        if (!window.ResizeObserver) { return; }
        var ro = new ResizeObserver(function () {
            if (!sheet.isConnected) { ro.disconnect(); return; }
            if (sheet.offsetHeight > 0) {
                ro.disconnect();
                try { var m = jalankan(sheet, opsi); if (m && sesudah) { sesudah(m); } } catch (e) { if (window.console) { console.error('Paginasi PO gagal:', e); } }
            }
        });
        ro.observe(sheet);
    }

    window.PoPaginasi = { jalankan: jalankan, pasang: pasang };
})();
