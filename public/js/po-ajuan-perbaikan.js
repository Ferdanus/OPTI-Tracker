/* ============================================================================
   Ajuan perbaikan PO ke PPK BLU (setelah PO disetujui PPK BLU)
   Dipakai di Daftar PO (/po-kegiatan) dan Daftar Order (/order).

   Cara pasang di halaman: letakkan penanda kosong di sel Aksi baris yang punya PO:
       <span data-ajuan-po="ID_PO_KEGIATAN" data-ajuan-dari="index|order" hidden></span>
   Skrip ini menanyakan ke server (GET /po-ajuan/status) PO mana yang boleh diajukan perbaikannya oleh pengguna ini,
   lalu menyisipkan tombol tepat sesudah penanda. Pengguna yang tidak berhak tidak melihat apa-apa.
   Satu dialog, satu alur: tombol di dua halaman memanggil window.ajukanPerbaikanPo() yang sama.
   ============================================================================ */
(function () {
    var skrip = document.currentScript;
    var BASE = (skrip && skrip.getAttribute('data-base')) || '';

    function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }

    function pasangGaya() {
        if (document.getElementById('pjxCss')) { return; }
        var st = document.createElement('style');
        st.id = 'pjxCss';
        st.textContent =
            '.pjx-dlg{position:fixed;inset:0;z-index:2100;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.55);opacity:0;transition:opacity .15s}' +
            '.pjx-dlg[hidden]{display:none}.pjx-dlg.show{opacity:1}' +
            '.pjx-box{background:#fff;border-radius:16px;width:100%;max-width:470px;max-height:92vh;overflow:auto;padding:24px 24px 18px;box-shadow:0 24px 60px rgba(15,23,42,.3);transform:translateY(8px) scale(.98);transition:transform .15s}' +
            '.pjx-dlg.show .pjx-box{transform:none}' +
            '.pjx-ic{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;font-size:24px;background:#f1f5f9;color:#1e293b}' +
            '.pjx-box h5{font-size:1.05rem;font-weight:800;margin:0 0 4px;text-align:center}' +
            '.pjx-sub{color:#64748b;font-size:.83rem;text-align:center;margin:0 0 14px}' +
            '.pjx-info{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:.78rem;color:#475569;margin-bottom:12px}' +
            '.pjx-info div{display:flex;gap:7px;margin:2px 0}.pjx-info i{color:#334155;margin-top:2px}' +
            '.pjx-box label.pjx-l{display:block;font-weight:700;font-size:.8rem;color:#1e293b;margin:0 0 4px}' +
            '.pjx-box label.pjx-l small{font-weight:500;color:#64748b}' +
            '.pjx-box textarea{width:100%;min-height:92px;border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 11px;font-size:.85rem;resize:vertical;outline:none}' +
            '.pjx-box textarea:focus{border-color:#334155}' +
            '.pjx-dz{border:1.8px dashed #cbd5e1;border-radius:10px;padding:12px;text-align:center;font-size:.8rem;color:#64748b;cursor:pointer;background:#f8fafc;transition:.15s}' +
            '.pjx-dz:hover,.pjx-dz.hv{border-color:#334155;background:#fff;color:#334155}.pjx-dz b{color:#1e293b}.pjx-dz small{display:block;color:#94a3b8;font-size:.72rem}' +
            '.pjx-fl{display:flex;flex-direction:column;gap:6px;margin-top:8px}.pjx-fl:empty{display:none}' +
            '.pjx-fi{display:flex;align-items:center;gap:9px;border:1px solid #e2e8f0;border-radius:9px;padding:6px 10px;font-size:.78rem}' +
            '.pjx-fi .ex{background:#1e293b;color:#fff;border-radius:6px;font-size:.6rem;font-weight:700;padding:3px 6px;text-transform:uppercase}' +
            '.pjx-fi .n{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pjx-fi .s{color:#94a3b8}' +
            '.pjx-fi button{border:0;background:none;color:#64748b;font-size:1.1rem;line-height:1;cursor:pointer;padding:0 2px}' +
            '.pjx-cnt{text-align:right;color:#94a3b8;font-size:.72rem;margin-top:2px}' +
            '.pjx-err{color:#b91c1c;font-size:.8rem;margin-top:8px}' +
            '.pjx-btns{display:flex;gap:10px;margin-top:14px}' +
            '.pjx-btns button{flex:1;padding:10px;border-radius:9px;font-weight:700;font-size:.85rem;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;color:#334155}' +
            '.pjx-btns button.ya{background:#334155;border-color:#334155;color:#fff}.pjx-btns button:disabled{opacity:.55;cursor:not-allowed}';
        document.head.appendChild(st);
    }

    var dlg = null, sedang = false, berkas = [];
    var EKS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'], MAKS_FILE = 3, MAKS_UKURAN = 8 * 1024 * 1024;
    function ukuranTeks(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function gambarBerkas() {
        dlg.querySelector('#pjxFl').innerHTML = berkas.map(function (f, i) {
            return '<div class="pjx-fi"><span class="ex">' + esc((f.name.split('.').pop() || '').slice(0, 4)) + '</span><span class="n">' + esc(f.name) + '</span><span class="s">' + ukuranTeks(f.size) + '</span><button type="button" data-hapus="' + i + '" aria-label="Hapus berkas">&times;</button></div>';
        }).join('');
    }
    function tambahBerkas(daftar) {
        var pesan = '';
        Array.prototype.forEach.call(daftar, function (f) {
            var ext = (f.name.split('.').pop() || '').toLowerCase();
            if (berkas.length >= MAKS_FILE) { pesan = 'Lampiran maksimal ' + MAKS_FILE + ' file.'; return; }
            if (EKS.indexOf(ext) < 0) { pesan = 'Jenis file tidak didukung. Gunakan PDF, foto (JPG/PNG), Word, atau Excel.'; return; }
            if (f.size > MAKS_UKURAN) { pesan = 'Ada file lebih dari 8 MB, tidak ditambahkan.'; return; }
            berkas.push(f);
        });
        galat(pesan); gambarBerkas();
    }

    function buatDialog() {
        if (dlg) { return dlg; }
        pasangGaya();
        dlg = document.createElement('div');
        dlg.className = 'pjx-dlg'; dlg.hidden = true;
        dlg.innerHTML =
            '<div class="pjx-box" role="dialog" aria-modal="true" aria-labelledby="pjxJudul">' +
            '<div class="pjx-ic"><i class="bi bi-pencil-square"></i></div>' +
            '<h5 id="pjxJudul">Ajukan perbaikan PO ke PPK BLU</h5>' +
            '<p class="pjx-sub" id="pjxSub"></p>' +
            '<div class="pjx-info">' +
                '<div><i class="bi bi-shield-check"></i><span>PO versi lama tetap berlaku sampai PPK BLU menyetujui.</span></div>' +
                '<div><i class="bi bi-pencil"></i><span>Anda mengubah PO di form yang sama dengan saat membuat PO. Bagian yang diubah tercatat dan terlihat oleh PPK BLU.</span></div>' +
                '<div><i class="bi bi-people"></i><span>Ketua Tim dan Ketua Pelaksana hanya diberi tahu. Keputusan langsung di PPK BLU.</span></div>' +
            '</div>' +
            '<label class="pjx-l" for="pjxAlasan">Alasan perbaikan <small>(wajib)</small></label>' +
            '<textarea id="pjxAlasan" maxlength="1000" placeholder="Mis. klien menambah satu parameter uji, jadwal bergeser, atau ada koreksi RAB."></textarea>' +
            '<div class="pjx-cnt"><span id="pjxCnt">0</span>/1000</div>' +
            '<label class="pjx-l" style="margin-top:8px">Lampiran pendukung <small>(opsional, sampai 3 file, 8 MB per file; mis. surat atau email permintaan klien)</small></label>' +
            '<div class="pjx-dz" id="pjxDz" role="button" tabindex="0"><b>Klik untuk memilih file</b> atau seret ke sini<small>PDF, foto, Word, Excel</small></div>' +
            '<input type="file" id="pjxLamp" multiple hidden accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">' +
            '<div class="pjx-fl" id="pjxFl"></div>' +
            '<div class="pjx-err" id="pjxErr" hidden></div>' +
            '<div class="pjx-btns"><button type="button" id="pjxNo">Batal</button><button type="button" class="ya" id="pjxYa">Lanjut ubah PO</button></div>' +
            '</div>';
        document.body.appendChild(dlg);
        dlg.addEventListener('click', function (e) { if (e.target === dlg) { tutup(); } });
        dlg.querySelector('#pjxNo').onclick = tutup;
        dlg.querySelector('#pjxAlasan').addEventListener('input', function () { dlg.querySelector('#pjxCnt').textContent = this.value.length; });
        var dz = dlg.querySelector('#pjxDz'), fi = dlg.querySelector('#pjxLamp');
        dz.addEventListener('click', function () { fi.click(); });
        dz.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fi.click(); } });
        fi.addEventListener('change', function () { tambahBerkas(fi.files); fi.value = ''; });
        ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('hv'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('hv'); }); });
        dz.addEventListener('drop', function (e) { tambahBerkas(e.dataTransfer.files); });
        dlg.querySelector('#pjxFl').addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-hapus]') : null;
            if (b) { berkas.splice(parseInt(b.getAttribute('data-hapus'), 10), 1); galat(''); gambarBerkas(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dlg && !dlg.hidden && !sedang) { tutup(); } });
        return dlg;
    }

    function galat(m) { var e = dlg.querySelector('#pjxErr'); e.textContent = m || ''; e.hidden = !m; }
    function tutup() {
        if (!dlg || sedang) { return; }
        dlg.classList.remove('show');
        setTimeout(function () { dlg.hidden = true; }, 160);
    }

    function buka(poId, nomor, dari) {
        buatDialog();
        dlg.querySelector('#pjxSub').textContent = 'PO ' + (nomor || '') + ' sudah disetujui PPK BLU. Jelaskan apa yang perlu diperbaiki.';
        dlg.querySelector('#pjxAlasan').value = '';
        dlg.querySelector('#pjxLamp').value = ''; berkas = []; gambarBerkas();
        dlg.querySelector('#pjxCnt').textContent = '0';
        galat('');
        var ya = dlg.querySelector('#pjxYa');
        ya.disabled = false; ya.textContent = 'Lanjut ubah PO';
        ya.onclick = function () {
            var alasan = dlg.querySelector('#pjxAlasan').value.trim();
            if (alasan.length < 10) { galat('Alasan wajib diisi (minimal 10 karakter) supaya PPK BLU paham apa yang berubah.'); dlg.querySelector('#pjxAlasan').focus(); return; }
            var fd = new FormData();
            fd.append('csrf_token', csrf()); fd.append('alasan', alasan); fd.append('dari', dari || 'index');
            berkas.forEach(function (f) { fd.append('lampiran[]', f); });
            sedang = true; ya.disabled = true; ya.textContent = 'Memproses...'; galat('');
            fetch(BASE + '/po-ajuan/' + poId + '/mulai', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.text().then(function (t) { var j; try { j = JSON.parse(t); } catch (e) { throw new Error('Server membalas ' + r.status + '.'); } if (!r.ok || !j.ok) { throw new Error(j.pesan || 'Permintaan gagal.'); } return j; }); })
                .then(function (j) { window.location.href = j.edit_url; })
                .catch(function (e) { sedang = false; ya.disabled = false; ya.textContent = 'Lanjut ubah PO'; galat(e.message); });
        };
        dlg.hidden = false;
        requestAnimationFrame(function () { dlg.classList.add('show'); });
        setTimeout(function () { var a = dlg.querySelector('#pjxAlasan'); if (a) { a.focus(); } }, 80);
    }
    window.ajukanPerbaikanPo = buka;

    /* ---------- penyisipan tombol ---------- */
    function sisipkan(el, info, dari) {
        if (el.getAttribute('data-ajuan-ok') === '1') { return; }
        el.setAttribute('data-ajuan-ok', '1');
        var node = null;
        if (info.bisa) {
            node = document.createElement('button');
            node.type = 'button';
            node.className = 'btn btn-sm btn-outline-secondary py-1 px-2 btn-ajuan-po';
            node.title = 'Ajukan perbaikan PO ke PPK BLU';
            node.setAttribute('aria-label', 'Ajukan perbaikan PO');
            node.innerHTML = '<i class="bi bi-pencil-square"></i>';
            if (dari === 'index') {
                /* Daftar PO: tombol geser seperti Lihat/Detail (kata muncul saat disorot). */
                node.className += ' btn-lihat-geser';
                node.style.setProperty('--w', '3.6em');
                node.innerHTML += '<span class="aksi-lbl">Ajukan</span>';
            }
            node.setAttribute('data-po', el.getAttribute('data-ajuan-po'));
            node.setAttribute('data-nomor', info.nomor || '');
            node.setAttribute('data-dari', dari || 'index');
        } else if (info.aktif) {
            node = document.createElement('span');
            node.className = 'btn btn-sm btn-outline-warning py-1 px-2 btn-ajuan-po';
            node.title = info.ket || 'Ajuan perbaikan sedang berjalan';
            node.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            if (dari === 'index') {
                node.className += ' btn-lihat-geser';
                node.style.setProperty('--w', '4.8em');
                node.style.cursor = 'default';
                node.innerHTML += '<span class="aksi-lbl">Diajukan</span>';
            } else {
                node.style.pointerEvents = 'none';
            }
        }
        if (node) { el.parentNode.insertBefore(node, el.nextSibling); }
    }

    /* Status yang sudah diterima disimpan sebentar, supaya tabel yang diganti ulang (pindah tab, filter, halaman) langsung dapat tombolnya lagi tanpa menunggu server. */
    var simpan = {}, UMUR = 45000;

    function pasang() {
        var els = Array.prototype.slice.call(document.querySelectorAll('[data-ajuan-po]:not([data-ajuan-ok])'));
        if (!els.length) { return; }
        var sekarang = Date.now(), perlu = {};
        els.forEach(function (el) {
            var id = parseInt(el.getAttribute('data-ajuan-po'), 10);
            if (!(id > 0)) { return; }
            var c = simpan[id];
            if (c && sekarang - c.t < UMUR) {
                if (c.info) { sisipkan(el, c.info, el.getAttribute('data-ajuan-dari') || 'index'); }
                else { el.setAttribute('data-ajuan-ok', '1'); }
            } else { perlu[id] = true; }
        });
        var daftar = Object.keys(perlu);
        if (!daftar.length) { return; }
        for (var i = 0; i < daftar.length; i += 150) {
            (function (potong) {
                fetch(BASE + '/po-ajuan/status?ids=' + potong.join(','), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j || !j.ok || !j.data) { return; }
                        var t = Date.now();
                        potong.forEach(function (id) { simpan[id] = { t: t, info: j.data[id] || null }; });
                        /* baca ulang penanda: tabel bisa saja sudah diganti selama menunggu server */
                        Array.prototype.forEach.call(document.querySelectorAll('[data-ajuan-po]:not([data-ajuan-ok])'), function (el) {
                            var id = el.getAttribute('data-ajuan-po');
                            if (!perlu[id]) { return; }
                            var info = j.data[id];
                            if (info) { sisipkan(el, info, el.getAttribute('data-ajuan-dari') || 'index'); }
                            else { el.setAttribute('data-ajuan-ok', '1'); }
                        });
                    })
                    .catch(function () { /* tombol pelengkap: kalau gagal, halaman tetap normal */ });
            })(daftar.slice(i, i + 150));
        }
    }
    window.pasangAjuanPo = pasang;

    /* Daftar PO mengganti isi tabelnya lewat AJAX (tab, filter, pencarian, halaman). Penanda baru yang muncul dipasangi tombol lagi. */
    function awasi() {
        if (!window.MutationObserver) { return; }
        var timer = null;
        new MutationObserver(function (daftar) {
            var ada = false;
            for (var i = 0; i < daftar.length && !ada; i++) {
                var n = daftar[i].addedNodes;
                for (var k = 0; k < n.length; k++) {
                    var x = n[k];
                    if (x.nodeType === 1 && ((x.hasAttribute && x.hasAttribute('data-ajuan-po')) || (x.querySelector && x.querySelector('[data-ajuan-po]')))) { ada = true; break; }
                }
            }
            if (!ada) { return; }
            clearTimeout(timer);
            timer = setTimeout(pasang, 30);
        }).observe(document.body, { childList: true, subtree: true });
    }

    /* Satu pendengar klik untuk semua tombol, jadi tombol yang tersalin lewat cache tab pun tetap berfungsi. */
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('button.btn-ajuan-po[data-po]') : null;
        if (!b) { return; }
        e.preventDefault(); e.stopPropagation();
        buka(b.getAttribute('data-po'), b.getAttribute('data-nomor'), b.getAttribute('data-dari'));
    });

    function mulai() { pasang(); awasi(); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', mulai); } else { mulai(); }
})();
