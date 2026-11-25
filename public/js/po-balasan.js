/* Balasan pada catatan revisi PO (PPK BLU <-> Ketua Tim <-> Ketua Pelaksana).
 *
 * Dipakai oleh slide Katim/Ketupel (revisi_ppk.html), slide PPK BLU (validasi_ppk.html) dan panel catatan di form Edit PO (form.html).
 * Halaman pemakai tetap merender daftar catatannya sendiri; modul ini hanya menyediakan HTML utas balasan + kotak "Balas" dan
 * menangani kliknya. Halaman memasang sekali:
 *
 *   PoBalasan.init({
 *     kirim:    function (idCatatan, teks) { return Promise<{balasan: [...]}> },   // POST ke server
 *     terapkan: function (idCatatan, balasan) { ...simpan ke data halaman... },
 *     render:   function () { ...render ulang daftar catatan... }
 *   });
 *
 * lalu memanggil PoBalasan.html(catatan, bolehBalas, ctx) di dalam kartu catatan. "ctx" membedakan dua tempat yang menampilkan
 * catatan yang sama pada satu halaman (mis. panel atas dan kotak di bawah judul kartu), supaya kotak ketiknya tidak bentrok.
 */
(function () {
    if (window.PoBalasan) { return; }
    var cfg = null, buka = {}, draf = {}, kirim = {};
    var MAKS = 500;

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    function fmt(t) {
        var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(t || '');
        if (!m) { return ''; }
        return (+m[3]) + ' ' + BULAN[+m[2] - 1] + ' ' + m[1] + (m[4] ? ' ' + m[4] + ':' + m[5] : '');
    }
    function ini(n) { return String(n || '?').split(' ').map(function (x) { return x.charAt(0); }).slice(0, 2).join('').toUpperCase(); }
    function kunci(ctx, id) { return (ctx || '') + '|' + id; }

    function css() {
        if (document.getElementById('pbCss')) { return; }
        var s = document.createElement('style'); s.id = 'pbCss';
        s.textContent =
            '.pb-wrap{flex:0 0 100%;width:100%;margin-top:8px;font-family:var(--font-body,system-ui,sans-serif);font-size:13px;text-align:left}' +
            '.pb-th{margin:0 0 0 4px;padding-left:12px;border-left:2px solid #cbd5e1;display:flex;flex-direction:column;gap:8px}' +
            '.pb-rp{display:flex;gap:8px;align-items:flex-start}' +
            '.pb-av{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:.58rem;font-weight:700;color:#fff;background:#1e293b;flex:none;margin-top:2px}' +
            '.pb-av.ppk{background:#475569}' +
            '.pb-rb{flex:1;min-width:0;background:#f1f5f9;border-radius:4px 12px 12px 12px;padding:6px 11px}' +
            '.pb-rt{font-size:.72rem;display:flex;gap:6px;flex-wrap:wrap;align-items:baseline}.pb-rt b{font-weight:700;color:#0f172a}.pb-rt span{color:#94a3b8}' +
            '.pb-tx{font-size:.82rem;margin-top:1px;white-space:pre-wrap;overflow-wrap:anywhere;color:#0f172a}' +
            '.pb-acts{margin-top:8px;display:flex;gap:6px}' +
            '.pb-btn{border:1.5px solid #cbd5e1;background:#fff;color:#334155;border-radius:8px;padding:4px 11px;font:inherit;font-size:.75rem;font-weight:700;cursor:pointer;line-height:1.4}' +
            '.pb-btn:hover{border-color:#1e293b;color:#1e293b}.pb-btn:disabled{opacity:.55;cursor:default}' +
            '.pb-btn.pri{background:#1e293b;border-color:#1e293b;color:#fff}.pb-btn.pri:hover{background:#0f172a;color:#fff}' +
            '.pb-cmp{margin-top:8px;border:1.5px solid #1e293b;border-radius:9px;padding:8px;background:#fff}' +
            '.pb-cmp textarea{width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:7px 9px;font:inherit;font-size:.82rem;resize:vertical;min-height:58px;background:#fff;color:#0f172a}' +
            '.pb-cmp textarea:focus{outline:none;border-color:#1e293b;box-shadow:0 0 0 3px rgba(30,41,59,.12)}' +
            '.pb-cmp textarea.err{border-color:#dc2626}' +
            '.pb-err{color:#dc2626;font-size:.72rem;margin-top:4px}' +
            '.pb-row{display:flex;gap:6px;justify-content:flex-end;margin-top:7px;align-items:center}.pb-row .pb-hint{margin-right:auto;font-size:.68rem;color:#94a3b8}';
        document.head.appendChild(s);
    }

    function item(r) {
        return '<div class="pb-rp"><span class="pb-av ' + esc(r.sisi) + '">' + esc(ini(r.oleh)) + '</span><div class="pb-rb"><div class="pb-rt"><b>' + esc(r.oleh) + '</b><span>' + esc(r.peran) + ' &middot; ' + esc(fmt(r.waktu)) + '</span></div><div class="pb-tx">' + esc(r.teks) + '</div></div></div>';
    }
    function kotak(id, ctx) {
        var k = kunci(ctx, id);
        return '<div class="pb-cmp"><textarea id="pbT' + esc(k) + '" data-pb-t="' + esc(k) + '" maxlength="' + MAKS + '" placeholder="Tulis balasan atau penjelasan Anda" aria-label="Balasan catatan">' + esc(draf[k] || '') + '</textarea>' +
            '<div class="pb-err" id="pbE' + esc(k) + '" hidden></div>' +
            '<div class="pb-row"><span class="pb-hint">Ctrl + Enter untuk mengirim</span><button type="button" class="pb-btn" data-pb-batal="' + id + '" data-pb-ctx="' + esc(ctx || '') + '">Batal</button>' +
            '<button type="button" class="pb-btn pri" data-pb-kirim="' + id + '" data-pb-ctx="' + esc(ctx || '') + '">Kirim balasan</button></div></div>';
    }

    /** HTML utas + tombol/kotak balas untuk satu catatan. Kosong kalau tidak ada balasan dan tidak boleh membalas. */
    function html(n, boleh, ctx) {
        css();
        var b = n.balasan || [], h = '', k = kunci(ctx, n.id);
        if (b.length) { h += '<div class="pb-th">' + b.map(item).join('') + '</div>'; }
        if (boleh) {
            if (buka[k]) { h += kotak(n.id, ctx); }
            else { h += '<div class="pb-acts"><button type="button" class="pb-btn" data-pb-buka="' + n.id + '" data-pb-ctx="' + esc(ctx || '') + '"><i class="bi bi-reply"></i> ' + (b.length ? 'Balas lagi' : 'Balas') + '</button></div>'; }
        }
        return h ? '<div class="pb-wrap">' + h + '</div>' : '';
    }

    function galat(k, m) {
        var e = document.getElementById('pbE' + k), t = document.getElementById('pbT' + k);
        if (e) { e.textContent = m; e.hidden = !m; }
        if (t) { t.classList.toggle('err', !!m); }
    }
    function fokus(k) { setTimeout(function () { var t = document.getElementById('pbT' + k); if (t) { t.focus(); try { t.scrollIntoView({ block: 'nearest' }); } catch (e) {} } }, 0); }

    function kirimBalasan(id, ctx) {
        var k = kunci(ctx, id);
        if (!cfg || kirim[k]) { return; }
        var t = document.getElementById('pbT' + k), v = t ? t.value.trim() : '';
        if (!v) { galat(k, 'Balasan tidak boleh kosong.'); if (t) { t.focus(); } return; }
        kirim[k] = true; galat(k, '');
        var btn = document.querySelector('[data-pb-kirim="' + id + '"][data-pb-ctx="' + (ctx || '') + '"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Mengirim...'; }
        Promise.resolve(cfg.kirim(id, v)).then(function (j) {
            kirim[k] = false;
            Object.keys(buka).forEach(function (x) { if (x.split('|')[1] === String(id)) { delete buka[x]; delete draf[x]; } });
            cfg.terapkan(id, (j && j.balasan) || []);
            cfg.render();
        }).catch(function (e) {
            kirim[k] = false;
            if (btn) { btn.disabled = false; btn.textContent = 'Kirim balasan'; }
            galat(k, (e && e.message) || 'Balasan gagal dikirim.');
        });
    }

    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-pb-buka],[data-pb-batal],[data-pb-kirim]') : null;
        if (!el || !cfg) { return; }
        var ctx = el.getAttribute('data-pb-ctx') || '', id, k;
        if (el.hasAttribute('data-pb-buka')) { id = el.getAttribute('data-pb-buka'); k = kunci(ctx, id); buka[k] = true; cfg.render(); fokus(k); }
        else if (el.hasAttribute('data-pb-batal')) { id = el.getAttribute('data-pb-batal'); k = kunci(ctx, id); delete buka[k]; delete draf[k]; cfg.render(); }
        else { kirimBalasan(parseInt(el.getAttribute('data-pb-kirim'), 10), ctx); }
    });
    document.addEventListener('input', function (e) {
        var t = e.target; if (!t || !t.getAttribute || !t.hasAttribute('data-pb-t')) { return; }
        var k = t.getAttribute('data-pb-t'); draf[k] = t.value;
        if (t.classList.contains('err') && t.value.trim()) { galat(k, ''); }
    });
    document.addEventListener('keydown', function (e) {
        var t = e.target; if (!t || !t.getAttribute || !t.hasAttribute('data-pb-t')) { return; }
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault(); var k = t.getAttribute('data-pb-t'), p = k.split('|');
            kirimBalasan(parseInt(p[1], 10), p[0]);
        }
    });

    window.PoBalasan = { init: function (c) { cfg = c; css(); }, html: html };
})();
