/* ===== Lembar kerja mini untuk RAB: rumus hidup, banyak sheet, edit sel ala Excel (tanpa pustaka luar) ===== */
var XL = (function () {
  var XL = {};
  XL.colNum = function (s) { var n = 0; for (var i = 0; i < s.length; i++) n = n * 26 + s.charCodeAt(i) - 64; return n; };
  XL.numCol = function (n) { var s = ''; while (n > 0) { var m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; };
  XL.addr = function (r, c) { return XL.numCol(c) + r; };
  XL.parseAddr = function (a) { var m = /^\$?([A-Z]+)\$?(\d+)$/.exec(a); return m ? { c: XL.colNum(m[1]), r: +m[2] } : null; };
  function esc(x) { return String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  XL.esc = esc;
  function rp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }
  XL.rp = rp;

  /* ---------- tokenizer + parser rumus ---------- */
  var TOK = /\s*(?:(\d+(?:\.\d+)?)|("(?:[^"]|"")*")|([A-Z][A-Z0-9\.]*)(?=\()|((?:'[^']+'|[A-Za-z_][A-Za-z0-9_]*)!)?(\$?[A-Z]{1,3}\$?\d+)(?::(\$?[A-Z]{1,3}\$?\d+))?|(<>|<=|>=|[-+*\/^&=<>(),%]))/y;
  function tokenize(f) {
    var t = [], m; TOK.lastIndex = 0; f = f.replace(/\s+$/, '');
    while (TOK.lastIndex < f.length) {
      var at = TOK.lastIndex; m = TOK.exec(f);
      if (!m) throw '#NAME?';
      if (m[1] !== undefined) t.push({ k: 'num', v: parseFloat(m[1]) });
      else if (m[2] !== undefined) t.push({ k: 'str', v: m[2].slice(1, -1).replace(/""/g, '"') });
      else if (m[3] !== undefined) t.push({ k: 'fn', v: m[3] });
      else if (m[5] !== undefined) t.push({ k: 'ref', sheet: m[4] ? m[4].slice(0, -1).replace(/^'|'$/g, '') : null, a: m[5].replace(/\$/g, ''), b: m[6] ? m[6].replace(/\$/g, '') : null });
      else t.push({ k: 'op', v: m[7] });
    }
    return t;
  }
  var cache = {};
  function compile(f) {
    if (cache[f]) return cache[f];
    if (f.indexOf('#REF!') >= 0) throw '#REF!';
    var t = tokenize(f.slice(1)), i = 0;
    function peek() { return t[i]; }
    function isOp(v) { var x = t[i]; return x && x.k === 'op' && x.v === v; }
    function cmp() { var l = cat(); while (t[i] && t[i].k === 'op' && /^(=|<>|<=|>=|<|>)$/.test(t[i].v)) { var o = t[i++].v; l = { k: 'bin', o: o, l: l, r: cat() }; } return l; }
    function cat() { var l = add(); while (isOp('&')) { i++; l = { k: 'bin', o: '&', l: l, r: add() }; } return l; }
    function add() { var l = mul(); while (isOp('+') || isOp('-')) { var o = t[i++].v; l = { k: 'bin', o: o, l: l, r: mul() }; } return l; }
    function mul() { var l = pow(); while (isOp('*') || isOp('/')) { var o = t[i++].v; l = { k: 'bin', o: o, l: l, r: pow() }; } return l; }
    function pow() { var l = un(); while (isOp('^')) { i++; l = { k: 'bin', o: '^', l: l, r: un() }; } return l; }
    function un() { if (isOp('-')) { i++; return { k: 'neg', e: un() }; } if (isOp('+')) { i++; return un(); } return post(); }
    function post() { var e = prim(); while (isOp('%')) { i++; e = { k: 'pct', e: e }; } return e; }
    function prim() {
      var x = t[i++]; if (!x) throw '#ERR!';
      if (x.k === 'num' || x.k === 'str' || x.k === 'ref') return x;
      if (x.k === 'fn') {
        if (!isOp('(')) throw '#NAME?'; i++; var args = [];
        if (!isOp(')')) { for (;;) { args.push(cmp()); if (isOp(',')) { i++; continue; } break; } }
        if (!isOp(')')) throw '#ERR!'; i++; return { k: 'fn', n: x.v, args: args };
      }
      if (x.k === 'op' && x.v === '(') { var e = cmp(); if (!isOp(')')) throw '#ERR!'; i++; return e; }
      throw '#ERR!';
    }
    var ast = cmp(); if (i < t.length) throw '#ERR!';
    return (cache[f] = ast);
  }

  /* ---------- workbook ---------- */
  XL.Workbook = function (def) {
    this.def = JSON.parse(JSON.stringify(def)); this.order = []; this.sheets = {}; this.memo = null;
    var self = this;
    this.def.forEach(function (s) {
      self.order.push(s.name);
      var raw = {};
      s.rows.forEach(function (row, ri) { row.forEach(function (v, ci) { if (v !== null && v !== '' && v !== undefined) raw[XL.addr(ri + 1, ci + 1)] = v; }); });
      var nc = s.ncols || Math.max.apply(null, s.rows.map(function (r) { return r.length; }).concat([6]));
      self.sheets[s.name] = { name: s.name, raw: raw, cache: s.cache || {}, nrows: Math.max(s.nrows || 0, s.rows.length + 2), ncols: nc, fmt: s.fmt || {}, cellFmt: s.cellFmt || {}, gaya: s.gaya || {}, widths: s.widths || {}, wc: s.wc || {}, merges: (s.merges || []).map(function (m) { return { r1: m.r1, c1: m.c1, r2: m.r2, c2: m.c2 }; }), original: JSON.parse(JSON.stringify(raw)) };
    });
  };
  var P = XL.Workbook.prototype;
  P.reset = function () { var self = this; this.order.forEach(function (n) { self.sheets[n].raw = JSON.parse(JSON.stringify(self.sheets[n].original)); }); this.memo = null; };
  P.resolve = function (n) { if (this.sheets[n]) return n; var l = String(n).toLowerCase(); for (var i = 0; i < this.order.length; i++) if (this.order[i].toLowerCase() === l) return this.order[i]; return n; };
  P.getRaw = function (sh, a) { var s = this.sheets[sh]; return s && s.raw[a] !== undefined ? s.raw[a] : null; };
  P.setRaw = function (sh, a, v) { var s = this.sheets[sh]; if (!s) return; delete s.cache[a]; if (v === null || v === '') delete s.raw[a]; else s.raw[a] = v; this.memo = null; };
  P.isFormula = function (sh, a) { var r = this.getRaw(sh, a); return typeof r === 'string' && r.charAt(0) === '='; };
  P.fmtOf = function (sh, a) { var s = this.sheets[sh]; if (s.cellFmt[a]) return s.cellFmt[a]; var p = XL.parseAddr(a); return s.fmt[XL.numCol(p.c)] || ''; };
  P.value = function (sh, a, stack) {
    sh = this.resolve(sh);
    if (!this.sheets[sh]) throw '#REF!';
    if (!this.memo) this.memo = {};
    var key = sh + '!' + a, m = this.memo[key];
    if (m !== undefined) { if (m && m.e) throw m.e; if (m && m.busy) throw '#CIRC!'; return m.v; }
    var raw = this.getRaw(sh, a);
    if (typeof raw !== 'string' || raw.charAt(0) !== '=') { this.memo[key] = { v: raw }; return raw; }
    this.memo[key] = { busy: true };
    try { var v = this.evalNode(compile(raw), sh); if (Array.isArray(v)) v = v.length ? v[0] : null; this.memo[key] = { v: v }; return v; }
    catch (e) {
      var er = typeof e === 'string' ? e : '#ERR!';
      // fungsi yang belum dikenal mesin ini: pakai hasil terakhir yang tersimpan di file Excel
      var cs = this.sheets[sh].cache; if (er === '#NAME?' && cs && cs[a] !== undefined && cs[a] !== null && !(typeof cs[a] === 'string' && cs[a].charAt(0) === '#')) { this.memo[key] = { v: cs[a], dariFile: true }; return cs[a]; }
      this.memo[key] = { e: er }; throw er;
    }
  };
  P.safe = function (sh, a) { try { return { v: this.value(sh, a) }; } catch (e) { return { e: String(e) }; } };
  // perbandingan ala Excel: sel kosong sama dengan "" dan 0, teks tidak peka huruf besar/kecil
  function cmp(o, l, r) {
    if (l === null || l === undefined) l = (typeof r === 'number') ? 0 : (typeof r === 'boolean' ? false : '');
    if (r === null || r === undefined) r = (typeof l === 'number') ? 0 : (typeof l === 'boolean' ? false : '');
    if (typeof l === 'string' && typeof r === 'string') { l = l.toLowerCase(); r = r.toLowerCase(); }
    else if (typeof l !== typeof r) { // angka < teks < logika, seperti Excel
      var rank = function (x) { return typeof x === 'number' ? 0 : (typeof x === 'string' ? 1 : 2); };
      if (o === '=') return false; if (o === '<>') return true; l = rank(l); r = rank(r);
    }
    switch (o) { case '=': return l === r; case '<>': return l !== r; case '<': return l < r; case '>': return l > r; case '<=': return l <= r; default: return l >= r; }
  }
  function num(v) { if (v === null || v === undefined || v === '') return 0; if (typeof v === 'number') return v; if (typeof v === 'string' && v.trim() !== '' && !isNaN(Number(v))) return Number(v); throw '#VALUE!'; }
  P.rangeCells = function (n, ctx) {
    var sh = this.resolve(n.sheet || ctx), A = XL.parseAddr(n.a), B = n.b ? XL.parseAddr(n.b) : A, out = [];
    for (var r = Math.min(A.r, B.r); r <= Math.max(A.r, B.r); r++) for (var c = Math.min(A.c, B.c); c <= Math.max(A.c, B.c); c++) out.push({ sh: sh, a: XL.addr(r, c) });
    return out;
  };
  P.evalNode = function (n, ctx) {
    var self = this;
    switch (n.k) {
      case 'num': case 'str': return n.v;
      case 'ref':
        if (!n.b) return this.value(n.sheet || ctx, n.a);
        return this.rangeCells(n, ctx).map(function (c) { return self.value(c.sh, c.a); });
      case 'neg': return -num(this.evalNode(n.e, ctx));
      case 'pct': return num(this.evalNode(n.e, ctx)) / 100;
      case 'bin': {
        var l = this.evalNode(n.l, ctx), r = this.evalNode(n.r, ctx);
        if (Array.isArray(l) || Array.isArray(r)) throw '#VALUE!';
        switch (n.o) {
          case '+': return num(l) + num(r); case '-': return num(l) - num(r); case '*': return num(l) * num(r);
          case '/': { var d = num(r); if (d === 0) throw '#DIV/0!'; return num(l) / d; }
          case '^': return Math.pow(num(l), num(r));
          case '&': return String(l == null ? '' : l) + String(r == null ? '' : r);
          case '=': case '<>': case '<': case '>': case '<=': case '>=': return cmp(n.o, l, r);
        }
      }
      case 'fn': {
        var f = n.n;
        if (f === 'IF') { var c = this.evalNode(n.args[0], ctx); var ok = (typeof c === 'number') ? c !== 0 : !!c; return n.args.length > (ok ? 1 : 2) ? this.evalNode(n.args[ok ? 1 : 2], ctx) : false; }
        if (f === 'IFERROR') { try { var iv = this.evalNode(n.args[0], ctx); if (Array.isArray(iv)) iv = iv.length ? iv[0] : null; return iv; } catch (e0) { return n.args.length > 1 ? this.evalNode(n.args[1], ctx) : ''; } }
        var vals = [];
        n.args.forEach(function (a) { var v = self.evalNode(a, ctx); if (Array.isArray(v)) v.forEach(function (x) { vals.push({ v: x, rng: true }); }); else vals.push({ v: v, rng: false }); });
        var nums = vals.filter(function (x) { return typeof x.v === 'number' || (!x.rng && x.v !== null && x.v !== '' && !isNaN(Number(x.v))); }).map(function (x) { return Number(x.v); });
        switch (f) {
          case 'SUM': return nums.reduce(function (a, b) { return a + b; }, 0);
          case 'AVERAGE': if (!nums.length) throw '#DIV/0!'; return nums.reduce(function (a, b) { return a + b; }, 0) / nums.length;
          case 'MIN': return nums.length ? Math.min.apply(null, nums) : 0;
          case 'MAX': return nums.length ? Math.max.apply(null, nums) : 0;
          case 'COUNT': return nums.length;
          case 'ABS': return Math.abs(num(vals[0].v));
          case 'ROUND': { var d2 = vals.length > 1 ? num(vals[1].v) : 0, k = Math.pow(10, d2); return Math.round(num(vals[0].v) * k) / k; }
          case 'ROUNDUP': case 'ROUNDDOWN': { var d3 = vals.length > 1 ? num(vals[1].v) : 0, k3 = Math.pow(10, d3), x3 = num(vals[0].v) * k3; return (f === 'ROUNDUP' ? (x3 < 0 ? Math.floor(x3) : Math.ceil(x3)) : Math.trunc(x3)) / k3; }
          case 'AND': { var ok1 = true; vals.forEach(function (x) { if (x.v === null || x.v === '') return; if (!(typeof x.v === 'number' ? x.v !== 0 : !!x.v)) ok1 = false; }); return ok1; }
          case 'OR': { var ok2 = false; vals.forEach(function (x) { if (x.v === null || x.v === '') return; if (typeof x.v === 'number' ? x.v !== 0 : !!x.v) ok2 = true; }); return ok2; }
          case 'NOT': return !(typeof vals[0].v === 'number' ? vals[0].v !== 0 : !!vals[0].v);
          case 'ISBLANK': return vals[0].v === null || vals[0].v === undefined;
          case 'ISNUMBER': return typeof vals[0].v === 'number';
          case 'INT': return Math.floor(num(vals[0].v));
          case 'SQRT': { var q = num(vals[0].v); if (q < 0) throw '#NUM!'; return Math.sqrt(q); }
          case 'PRODUCT': return nums.length ? nums.reduce(function (a, b) { return a * b; }, 1) : 0;
          case 'MOD': { var dv = num(vals[1].v); if (dv === 0) throw '#DIV/0!'; var m = num(vals[0].v) % dv; return (m !== 0 && (m < 0) !== (dv < 0)) ? m + dv : m; }
          default: throw '#NAME?';
        }
      }
    }
    throw '#ERR!';
  };
  // sel yang dirujuk rumus (untuk disorot)
  P.refsOf = function (sh, a) {
    var raw = this.getRaw(sh, a); if (typeof raw !== 'string' || raw.charAt(0) !== '=') return [];
    var out = [], self = this;
    (function walk(n) {
      if (!n) return;
      if (n.k === 'ref') { self.rangeCells(n, sh).forEach(function (c) { out.push(c); }); }
      if (n.e) walk(n.e); if (n.l) walk(n.l); if (n.r) walk(n.r); if (n.args) n.args.forEach(walk);
    })((function () { try { return compile(raw); } catch (e) { return null; } })());
    return out;
  };
  P.snapshot = function () {
    var snap = {}, self = this; this.memo = null;
    this.order.forEach(function (sn) { var s = self.sheets[sn]; for (var a in s.raw) { if (typeof s.raw[a] === 'string' && s.raw[a].charAt(0) === '=') { var r = self.safe(sn, a); snap[sn + '!' + a] = r.e ? r.e : r.v; } } });
    return snap;
  };


  /* ---------- keadaan (untuk urungkan/ulangi) ---------- */
  P.state = function () {
    var o = {}, self = this;
    this.order.forEach(function (n) { var s = self.sheets[n]; o[n] = { raw: s.raw, cache: s.cache, cellFmt: s.cellFmt, gaya: s.gaya, widths: s.widths, wc: s.wc, merges: s.merges, nrows: s.nrows, ncols: s.ncols }; });
    return JSON.stringify(o);
  };
  P.restore = function (str) {
    var o = JSON.parse(str), self = this;
    this.order.forEach(function (n) { var d = o[n]; if (!d) return; var s = self.sheets[n]; s.raw = d.raw; s.cache = d.cache; s.cellFmt = d.cellFmt; s.gaya = d.gaya; s.widths = d.widths; s.wc = d.wc; s.merges = d.merges || []; s.nrows = d.nrows; s.ncols = d.ncols; });
    this.memo = null;
  };

  /* ---------- sisip / hapus baris & kolom (rumus ikut bergeser, seperti Excel) ---------- */
  var REFSRC = '("(?:[^"]|"")*")|(^|[^A-Za-z0-9_.])((?:\'[^\']+\'|[A-Za-z_][A-Za-z0-9_]*)!)?(\\$?)([A-Z]{1,3})(\\$?)(\\d+)(?::(\\$?)([A-Z]{1,3})(\\$?)(\\d+))?(?![A-Za-z0-9_(])';
  function refRe() { return new RegExp(REFSRC, 'g'); }
  // ax: 'r' | 'c'; op: 'ins' | 'del'; at: posisi pertama; n: jumlah
  function mapPoint(v, op, at, n) { if (op === 'ins') return v >= at ? v + n : v; if (v >= at && v < at + n) return -1; return v >= at + n ? v - n : v; }
  function mapSpan(a, b, op, at, n) { // -> [a2, b2] atau null bila seluruh rentang terhapus
    if (a > b) { var t = a; a = b; b = t; }
    if (op === 'ins') return [a >= at ? a + n : a, b >= at ? b + n : b];
    if (a >= at && b < at + n) return null;
    var a2 = a < at ? a : (a < at + n ? at : a - n), b2 = b < at ? b : (b < at + n ? at - 1 : b - n);
    return [a2, b2];
  }
  function mapFormula(wb, f, owner, target, ax, op, at, n) {
    return f.replace(refRe(), function (m, q, pre, sh, d1, c1, d2, r1, d3, c2, d4, r2) {
      if (q) return q;
      var shn = sh ? wb.resolve(sh.slice(0, -1).replace(/^'|'$/g, '')) : owner;
      if (shn !== target) return m;
      var pfx = pre + (sh || '');
      var cA = XL.colNum(c1), rA = +r1, hasB = c2 !== undefined, cB = hasB ? XL.colNum(c2) : cA, rB = hasB ? +r2 : rA;
      if (ax === 'r') {
        if (!hasB) { var np = mapPoint(rA, op, at, n); if (np < 0) return pre + '#REF!'; return pfx + d1 + c1 + d2 + np; }
        var sp = mapSpan(rA, rB, op, at, n); if (!sp) return pre + '#REF!';
        return pfx + d1 + c1 + d2 + sp[0] + ':' + d3 + c2 + d4 + sp[1];
      }
      if (!hasB) { var nc = mapPoint(cA, op, at, n); if (nc < 0) return pre + '#REF!'; return pfx + d1 + XL.numCol(nc) + d2 + rA; }
      var sc = mapSpan(cA, cB, op, at, n); if (!sc) return pre + '#REF!';
      return pfx + d1 + XL.numCol(sc[0]) + d2 + rA + ':' + d3 + XL.numCol(sc[1]) + d4 + rB;
    });
  }
  // geser alamat relatif (isi ke bawah, tempel): $ menahan baris/kolom
  XL.geserRumus = function (f, dr, dc) {
    return f.replace(refRe(), function (m, q, pre, sh, d1, c1, d2, r1, d3, c2, d4, r2) {
      if (q) return q;
      function sat(dk, col, dr2, row) {
        var cc = dk ? XL.colNum(col) : XL.colNum(col) + dc, rr = dr2 ? +row : +row + dr;
        if (cc < 1 || rr < 1) return null; return (dk || '') + XL.numCol(cc) + (dr2 || '') + rr;
      }
      var a = sat(d1, c1, d2, r1); if (a === null) return pre + '#REF!';
      if (c2 === undefined) return pre + (sh || '') + a;
      var b = sat(d3, c2, d4, r2); if (b === null) return pre + '#REF!';
      return pre + (sh || '') + a + ':' + b;
    });
  };
  P.mergeAt = function (sh, r, c) { var ms = this.sheets[sh].merges; for (var i = 0; i < ms.length; i++) { var m = ms[i]; if (r >= m.r1 && r <= m.r2 && c >= m.c1 && c <= m.c2) return m; } return null; };
  P.structural = function (sh, ax, op, at, n) {
    var s = this.sheets[sh], self = this;
    function remap(obj) {
      var out = {};
      for (var a in obj) { var p = XL.parseAddr(a); if (!p) continue; var r = p.r, c = p.c; if (ax === 'r') r = mapPoint(r, op, at, n); else c = mapPoint(c, op, at, n); if (r < 0 || c < 0) continue; out[XL.addr(r, c)] = obj[a]; }
      return out;
    }
    s.raw = remap(s.raw); s.cache = remap(s.cache); s.cellFmt = remap(s.cellFmt); s.gaya = remap(s.gaya);
    var mg = [];
    s.merges.forEach(function (m) {
      var sp = ax === 'r' ? mapSpan(m.r1, m.r2, op, at, n) : mapSpan(m.c1, m.c2, op, at, n); if (!sp) return;
      var o = { r1: m.r1, c1: m.c1, r2: m.r2, c2: m.c2 };
      if (ax === 'r') { o.r1 = sp[0]; o.r2 = sp[1]; } else { o.c1 = sp[0]; o.c2 = sp[1]; }
      if (o.r1 === o.r2 && o.c1 === o.c2) return; mg.push(o);
    });
    s.merges = mg;
    if (ax === 'r') s.nrows = Math.max(1, op === 'ins' ? s.nrows + n : s.nrows - n);
    else {
      var w2 = {}, wc2 = {};
      for (var c = 1; c <= s.ncols; c++) { var L = XL.numCol(c), nc = mapPoint(c, op, at, n); if (nc < 1) continue; if (s.widths[L] !== undefined) w2[XL.numCol(nc)] = s.widths[L]; if (s.wc[L]) wc2[XL.numCol(nc)] = 1; }
      s.widths = w2; s.wc = wc2; s.ncols = Math.max(1, op === 'ins' ? s.ncols + n : s.ncols - n);
    }
    this.order.forEach(function (name) {
      var raw = self.sheets[name].raw;
      for (var a in raw) { if (typeof raw[a] === 'string' && raw[a].charAt(0) === '=') raw[a] = mapFormula(self, raw[a], name, sh, ax, op, at, n); }
    });
    this.memo = null;
  };


  /* ---------- tampilan nilai & input ---------- */
  // kode format sel: '' (Umum) | gen[:n] | sep[:n] | rp[:n] | pct[:n] | txt   (n = jumlah desimal 0-6)
  var KODE = /^(?:(?:gen|sep|rp|pct)(?::[0-6])?|txt)$/;
  XL.kodeSah = function (f) { return KODE.test(f); };
  function pc(f) { var m = /^([a-z]+)(?::(\d))?$/.exec(f || ''); return m ? { k: m[1], d: m[2] === undefined ? null : +m[2] } : { k: '', d: null }; }
  XL.pc = pc;
  function desimalAlami(v, persen) { // jumlah desimal yang tampil bila format tidak menentukan
    if (typeof v !== 'number' || !isFinite(v)) return 0;
    var x = persen ? Math.round(v * 1e8) / 1e6 : Math.round(v * 1e6) / 1e6, s = String(x), i = s.indexOf('.');
    return i < 0 ? 0 : Math.min(persen ? 2 : 6, s.length - i - 1);
  }
  XL.desimalAlami = desimalAlami;
  XL.fmtVal = function (v, f) {
    if (v === null || v === undefined || v === '') return '';
    if (typeof v === 'number') {
      var p = pc(f);
      var nf = function (x, d, g) { return x.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d, useGrouping: g }); };
      if (p.k === 'rp') { var dr = p.d || 0; return Math.abs(v) < Math.pow(10, -dr) / 2 ? 'Rp -' : 'Rp ' + nf(v, dr, true); }
      if (p.k === 'pct') return p.d === null ? (Math.round(v * 10000) / 100).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%' : nf(v * 100, p.d, true) + '%';
      if (p.k === 'sep') return nf(v, p.d || 0, true);
      if (p.k === 'gen' && p.d !== null) return nf(v, p.d, false);
      if (p.k === 'txt') return String(v);
      return v.toLocaleString('id-ID', { maximumFractionDigits: Math.abs(v - Math.round(v)) < 1e-9 ? 0 : 6 });
    }
    if (typeof v === 'boolean') return v ? 'TRUE' : 'FALSE';
    return String(v);
  };
  XL.parseInput = function (t, f) {
    t = String(t).replace(/ /g, ' ').trim();
    if (t === '') return null;
    if (pc(f).k === 'txt') return t;
    if (t.charAt(0) === '=') return t.replace(/('[^']*'|"[^"]*")|([^'"]+)/g, function (m, q, o) { return q ? q : o.toUpperCase(); });
    var t2 = t.replace(/^Rp\.?\s*/i, '');
    var pct = /^-?[\d.,]+%$/.test(t2);
    var body = pct ? t2.slice(0, -1) : t2;
    var n = null;
    if (/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(body)) n = Number(body.replace(/\./g, '').replace(',', '.'));
    else if (/^-?\d+(,\d+)?$/.test(body)) n = Number(body.replace(',', '.'));
    else if (/^-?\d+\.\d+$/.test(body)) n = Number(body);
    if (n !== null) return pct ? n / 100 : n;
    return t;
  };
  XL.rawText = function (raw, f) { // teks di formula bar
    if (raw === null || raw === undefined) return '';
    if (typeof raw === 'number') { if (pc(f).k === 'pct') return String(Math.round(raw * 10000) / 100).replace('.', ',') + '%'; return String(raw).replace('.', ','); }
    return String(raw);
  };

  /* ---------- pertukaran dengan server ---------- */
  // payload server: {sheets:[{nama, kol, baris:[[no,[sel...]],...], fmt:{A1:'rp'}, gaya:{A1:{b:1,a:'c',w:1}}, lebar:{A:120}}]}; sel = angka | teks | null | {f:"=RUMUS", v:hasil terakhir, e:"#GALAT"}
  function parseMerge(t) {
    var m = /^([A-Z]+\d+):([A-Z]+\d+)$/.exec(String(t)); if (!m) return null;
    var a = XL.parseAddr(m[1]), b = XL.parseAddr(m[2]); if (!a || !b) return null;
    return { r1: Math.min(a.r, b.r), c1: Math.min(a.c, b.c), r2: Math.max(a.r, b.r), c2: Math.max(a.c, b.c) };
  }
  XL.fromGrid = function (grid) {
    var def = grid.sheets.map(function (sh) {
      var rows = [], cache = {}, w = [], maxR = 0;
      sh.baris.forEach(function (br) {
        var no = br[0], cells = br[1], out = [];
        cells.forEach(function (v, ci) {
          if (v !== null && typeof v === 'object') {
            var a = XL.addr(no, ci + 1);
            if (v.f && v.f.length > 1) { out.push(v.f); if (v.e) cache[a] = v.e; else if (v.v !== undefined && v.v !== null) cache[a] = v.v; }
            else out.push(v.e || null);
            v = out[out.length - 1];
          } else out.push(v);
          var len = v === null || v === undefined ? 0 : String(typeof v === 'number' ? v.toLocaleString('id-ID') : (String(v).charAt(0) === '=' ? (cells[ci] && cells[ci].v !== undefined ? cells[ci].v : '1234567') : v)).length;
          if (len && sh.fmt && !Array.isArray(sh.fmt) && /^rp/.test(sh.fmt[XL.addr(no, ci + 1)] || '')) len += 4;
          if (len > (w[ci] || 0)) w[ci] = len;
        });
        while (rows.length < no - 1) rows.push([]);
        rows[no - 1] = out; if (no > maxR) maxR = no;
      });
      var widths = {}, wc = {}, lebar = sh.lebar && !Array.isArray(sh.lebar) ? sh.lebar : {};
      for (var c = 0; c < sh.kol; c++) {
        var L = XL.numCol(c + 1);
        if (lebar[L]) { widths[L] = Math.max(24, Math.min(800, +lebar[L])); wc[L] = 1; }
        else widths[L] = Math.max(56, Math.min(300, Math.round((w[c] || 6) * 7.2 + 18)));
      }
      return { name: sh.nama, rows: rows, ncols: sh.kol, nrows: maxR, widths: widths, wc: wc, cache: cache, cellFmt: sh.fmt && !Array.isArray(sh.fmt) ? sh.fmt : {}, gaya: sh.gaya && !Array.isArray(sh.gaya) ? sh.gaya : {}, merges: (sh.merges || []).map(parseMerge).filter(Boolean) };
    });
    return new XL.Workbook(def);
  };
  // isi workbook -> payload untuk dikirim ke server (hanya baris yang berisi)
  XL.toGrid = function (wb, names) {
    wb.memo = null;
    return { sheets: (names || wb.order).map(function (n) {
      var s = wb.sheets[n], baris = [];
      for (var r = 1; r <= s.nrows; r++) {
        var row = [], ada = false;
        for (var c = 1; c <= s.ncols; c++) {
          var a = XL.addr(r, c), raw = wb.getRaw(n, a), cell = raw;
          if (typeof raw === 'string' && raw.charAt(0) === '=') {
            var res = wb.safe(n, a); cell = { f: raw };
            if (res.e) cell.e = res.e; else if (res.v !== null && res.v !== undefined) cell.v = typeof res.v === 'boolean' ? (res.v ? 'TRUE' : 'FALSE') : (typeof res.v === 'number' && !isFinite(res.v) ? null : res.v);
          }
          if (cell !== null && cell !== undefined) ada = true; row.push(cell === undefined ? null : cell);
        }
        if (ada) baris.push([r, row]);
      }
      var lebar = {}; for (var L in s.wc) { if (s.wc[L] && s.widths[L]) lebar[L] = Math.round(s.widths[L]); }
      return { nama: n, kol: s.ncols, baris: baris, fmt: s.cellFmt || {}, gaya: s.gaya || {}, lebar: lebar, merges: s.merges.map(function (m) { return XL.addr(m.r1, m.c1) + ':' + XL.addr(m.r2, m.c2); }) };
    }) };
  };
  // apakah hasil hitung mesin ini berbeda dari angka yang tersimpan di file? (rumus tanpa nilai tersimpan, dsb.)
  XL.beda = function (wb) {
    wb.memo = null; var n = 0;
    wb.order.forEach(function (sn) {
      var s = wb.sheets[sn];
      for (var a in s.raw) {
        if (typeof s.raw[a] !== 'string' || s.raw[a].charAt(0) !== '=') continue;
        var r = wb.safe(sn, a), c = s.cache[a];
        if (r.e) { if (c !== r.e) n++; continue; }
        if (typeof r.v === 'number') { if (typeof c !== 'number' || Math.abs(c - r.v) > 0.005) n++; }
        else if ((r.v === null || r.v === '') !== (c === undefined || c === null || c === '')) n++;
      }
    });
    return n;
  };

  /* ---------- pita menu (ribbon) ---------- */
  var MAXR = 600, MAXC = 30;
  function ic(path, extra) { return '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' + path + (extra || '') + '</svg>'; }
  function bg(act, title, inner, cls) { return '<button type="button" class="xl-b' + (cls ? ' ' + cls : '') + '" data-act="' + act + '" title="' + title + '">' + inner + '</button>'; }
  function grp(rows, cap, style) { return '<div class="xl-g"' + (style ? ' style="' + style + '"' : '') + '><div class="xl-rows">' + rows + '</div><div class="xl-cap">' + cap + '</div></div>'; }
  function bigIcon(inner) { return '<svg viewBox="0 0 26 26" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.4">' + inner + '</svg>'; }
  function ribbonHtml(title) {
    var tgrid = '<rect x="3" y="4" width="14" height="14" rx="1"/><path d="M3 9h14M3 14h14M10 4v14"/>';
    var h = '<div class="xl-title"><span class="xl-qa">' +
      bg('undo', 'Urungkan (Ctrl+Z)', ic('<path d="M5 3 2 6l3 3"/><path d="M2 6h7a4 4 0 0 1 0 8H6"/>')) +
      bg('redo', 'Ulangi (Ctrl+Y)', ic('<path d="m11 3 3 3-3 3"/><path d="M14 6H7a4 4 0 0 0 0 8h3"/>')) +
      '</span><span class="xl-fn">' + esc(title || 'Tabel Excel') + '</span><span style="width:60px"></span></div>' +
      '<div class="xl-rt"><button type="button" class="on" data-rt="home">Beranda</button><button type="button" data-rt="view">Tampilan</button></div><div class="xl-rib">';
    h += '<div class="xl-pane on" data-pane="home">';
    h += grp('<div class="xl-row">' + bg('bold', 'Tebal (Ctrl+B)', '<b style="font-family:serif;font-size:15px">B</b>') + '</div>', 'Huruf');
    h += grp('<div class="xl-row">' + bg('al-l', 'Rata kiri', ic('<path d="M2 3h12M2 6.5h8M2 10h12M2 13.5h8"/>')) + bg('al-c', 'Rata tengah', ic('<path d="M2 3h12M4 6.5h8M2 10h12M4 13.5h8"/>')) + bg('al-r', 'Rata kanan', ic('<path d="M2 3h12M6 6.5h8M2 10h12M6 13.5h8"/>')) + '</div>' +
      '<div class="xl-row">' + bg('wrap', 'Bungkus teks', ic('<path d="M2 3h12M2 7h9a2.5 2.5 0 0 1 0 5H8"/><path d="m9.5 10.5-2 1.5 2 1.5"/><path d="M2 12h3"/>') + '<span>Bungkus teks</span>') +
      '<span class="xl-dd"><button type="button" class="xl-b" data-menu="1" title="Gabung sel">' + ic('<rect x="2" y="4" width="12" height="8" rx="1"/><path d="M5 8h6M5 8l1.5-1.5M5 8l1.5 1.5M11 8 9.5 6.5M11 8 9.5 9.5"/>') + '<span>Gabung <i class="xl-car">&#9660;</i></span></button><div class="xl-menu"><div data-act="merge-c">Gabung &amp; tengahkan</div><div data-act="merge">Gabung sel</div><div data-act="unmerge">Pisahkan sel</div></div></span></div>', 'Perataan');
    h += grp('<div class="xl-row">' + bg('list-n', 'Daftar bernomor di dalam sel: 1. 2. 3. (klik lagi untuk melepas)', '1.') + bg('list-a', 'Daftar abjad di dalam sel: a. b. c.', 'a.') + bg('list-b', 'Daftar poin di dalam sel', '&bull;') + '</div><div class="xl-row" style="font-size:11px;color:#605e5c;max-width:104px;line-height:1.15">Alt+Enter = baris baru di sel</div>', 'Paragraf');
    h += grp('<div class="xl-row"><select class="xl-sel" data-sel="fmt" title="Format angka"><option value="">Format angka</option><option value="gen">Umum</option><option value="sep">Angka (1.000)</option><option value="rp">Rp (Rupiah)</option><option value="pct">Persen (%)</option><option value="txt">Teks</option></select></div>' +
      '<div class="xl-row">' + bg('f-rp', 'Format Rupiah', 'Rp') + bg('f-pct', 'Format persen', '%') + bg('f-sep', 'Pemisah ribuan', '000') + bg('dec+', 'Tambah desimal', ',0<span style="font-size:10px">&rarr;</span>') + bg('dec-', 'Kurangi desimal', '&larr;<span style="font-size:10px">,0</span>') + '</div>', 'Angka');
    h += '<div class="xl-g"><div class="xl-row" style="align-items:flex-start">' +
      '<span class="xl-dd"><button type="button" class="xl-b xl-big" data-menu="1">' + bigIcon(tgrid + '<path d="M20 17v7M16.5 20.5h7" stroke="#217346" stroke-width="2"/>') + '<span>Sisipkan <i class="xl-car">&#9660;</i></span></button><div class="xl-menu"><div data-act="ins-ra">Sisipkan baris di atas</div><div data-act="ins-rb">Sisipkan baris di bawah</div><div data-act="ins-c">Sisipkan kolom</div></div></span>' +
      '<span class="xl-dd"><button type="button" class="xl-b xl-big" data-menu="1">' + bigIcon(tgrid + '<path d="M16.5 20.5h7" stroke="#c42b1c" stroke-width="2"/>') + '<span>Hapus <i class="xl-car">&#9660;</i></span></button><div class="xl-menu"><div data-act="del-r">Hapus baris terpilih</div><div data-act="del-c">Hapus kolom terpilih</div></div></span>' +
      '</div><div class="xl-cap">Sel</div></div>';
    h += '<div class="xl-g"><div class="xl-row" style="align-items:flex-start">' +
      '<span class="xl-dd"><button type="button" class="xl-b xl-big" data-menu="1">' + bigIcon('<rect x="5" y="3" width="14" height="19" rx="1"/><path d="M5 9h14M5 15h14"/><path d="M12 11v9m-3-3 3 3 3-3" stroke="#217346" stroke-width="2"/>') + '<span>Isi <i class="xl-car">&#9660;</i></span></button><div class="xl-menu"><div data-act="fill-d">Ke bawah (Ctrl+D)</div><div class="xl-sepr"></div><div data-act="seq-n">Nomor: 1, 2, 3</div><div data-act="seq-n.">Nomor titik: 1., 2., 3.</div><div data-act="seq-a">Abjad: a, b, c</div><div data-act="seq-A">Abjad besar: A, B, C</div><div data-act="seq-I">Romawi: I, II, III</div><div data-act="seq-m">Bertingkat: 1.1, 1.2, 1.3</div><div class="xl-sepr"></div><div data-act="renum">Nomori ulang (lewati sel kosong)</div></div></span>' +
      '<div class="xl-rows" style="padding-left:6px"><div class="xl-row"><input class="xl-fq" placeholder="Cari… (Ctrl+F)" spellcheck="false" autocomplete="off">' + bg('find-n', 'Cari berikutnya', ic('<path d="M8 3v10m-4-4 4 4 4-4"/>')) + '</div><div class="xl-row xl-hits">&nbsp;</div></div>' +
      '</div><div class="xl-cap">Pengeditan</div></div>';
    h += '</div>';
    h += '<div class="xl-pane" data-pane="view">';
    h += grp('<div class="xl-row"><span>Zoom:</span><select class="xl-sel" data-sel="zoom"><option value="75">75%</option><option value="100" selected>100%</option><option value="125">125%</option></select></div>', 'Zoom');
    h += '<div class="xl-g"><div class="xl-row" style="align-items:flex-start">' +
      bg('freeze', 'Bekukan baris judul', bigIcon('<rect x="3" y="4" width="20" height="18" rx="1"/><path d="M3 10h20M10 4v18"/><path d="M3 10h20" stroke="#217346" stroke-width="2.4"/>') + '<span>Bekukan judul</span>', 'xl-big') +
      bg('compact', 'Baris lebih rapat', bigIcon('<rect x="3" y="4" width="20" height="18" rx="1"/><path d="M3 8.5h20M3 13h20M3 17.5h20"/>') + '<span>Tampilan ringkas</span>', 'xl-big') +
      bg('full', 'Layar penuh (Esc untuk keluar)', bigIcon('<path d="M4 10V4h6M22 10V4h-6M4 16v6h6M22 16v6h-6"/>') + '<span>Layar penuh</span>', 'xl-big') +
      '</div><div class="xl-cap">Jendela</div></div>';
    h += '<div class="xl-g" style="border:0"><div class="xl-rows"><div style="color:#605e5c;max-width:260px">Lebar kolom: tarik garis di antara judul kolom (A, B, C&hellip;) seperti di Excel.</div></div></div>';
    h += '</div></div>';
    return h;
  }
  function statHtml() {
    return '<div class="xl-stat"><span class="xl-st1">Siap</span><span class="xl-st2"></span><span class="xl-stsp"></span><span class="xl-st3"></span><span class="xl-zm"><button type="button" data-act="z-">&minus;</button><input type="range" class="xl-zr" min="50" max="150" step="5" value="100"><button type="button" data-act="z+">+</button><span class="xl-zt">100%</span></span></div>';
  }

  /* ---------- tampilan grid ---------- */
  XL.Grid = function (o) {
    var self = this; this.o = o; this.wb = o.wb; this.sheet = o.sheet || this.wb.order[0]; this.sel = { r: 1, c: 1 }; this.rng = { r1: 1, c1: 1, r2: 1, c2: 1 }; this.anc = { r: 1, c: 1 }; this.far = { r: 1, c: 1 };
    this.editing = false; this.showF = false; this.flashSet = {}; this.marks = {}; this.hist = []; this.fut = []; this.zoom = 1; this.compact = false; this.freeze = 0; this.clip = null; this.findQ = ''; this.findI = -1; this.edited = {};
    this.tb = !!o.toolbar && !o.readonly;
    var root = o.mount; root.innerHTML = ''; root.className = (root.className || '').replace(/\bxl-fs\b/g, '').trim();
    root.classList.add('xlg'); this.root = root;
    root.innerHTML = (this.tb ? ribbonHtml(o.title) : '') +
      '<div class="xl-bar"><b class="xl-ref">A1</b><span class="xl-fxl">fx</span><input class="xl-fxi" spellcheck="false" autocomplete="off"><button type="button" class="xl-xr" hidden></button></div>' +
      '<div class="xl-scroll" tabindex="0"><table class="xl-t"></table><textarea class="xl-edit" rows="1" wrap="off" spellcheck="false" autocomplete="off" hidden></textarea></div>' +
      (o.noTabs ? '' : '<div class="xl-tabs"></div>') + (this.tb ? statHtml() : '');
    var q = function (s) { return root.querySelector(s); };
    this.el = { ref: q('.xl-ref'), fxi: q('.xl-fxi'), xr: q('.xl-xr'), scroll: q('.xl-scroll'), table: q('.xl-t'), edit: q('.xl-edit'), tabs: q('.xl-tabs'), fq: q('.xl-fq'), hits: q('.xl-hits'), st1: q('.xl-st1'), st2: q('.xl-st2'), st3: q('.xl-st3'), zr: q('.xl-zr'), zt: q('.xl-zt') };
    if (o.maxHeight) this.el.scroll.style.maxHeight = o.maxHeight + 'px';
    var sc = this.el.scroll;
    sc.addEventListener('mousedown', function (e) { XL._act = self; self.mdown(e); });
    sc.addEventListener('dblclick', function (e) { if (e.target.closest('td[data-a]')) self.startEdit(null); });
    sc.addEventListener('keydown', function (e) { self.key(e); });
    sc.addEventListener('copy', function (e) { if (e.target === self.el.edit) return; e.preventDefault(); self.copy(e, false); });
    sc.addEventListener('cut', function (e) { if (e.target === self.el.edit) return; e.preventDefault(); self.copy(e, true); });
    sc.addEventListener('paste', function (e) { if (e.target === self.el.edit) return; e.preventDefault(); var t = (e.clipboardData || window.clipboardData).getData('text'); if (t) self.paste(t); });
    this.el.edit.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (e.altKey) { var ed = self.el.edit; ed.setRangeText('\n', ed.selectionStart, ed.selectionEnd, 'end'); self.fitEdit(); self.el.fxi.value = ed.value.replace(/\n/g, ' '); }
        else { self.commit(); self.move(1, 0); }
      }
      else if (e.key === 'Tab') { e.preventDefault(); self.commit(); self.move(0, e.shiftKey ? -1 : 1); }
      else if (e.key === 'Escape') { e.preventDefault(); self.cancel(); }
      e.stopPropagation();
    });
    this.el.edit.addEventListener('input', function () { self.fitEdit(); self.el.fxi.value = self.el.edit.value.replace(/\n/g, ' '); });
    this.el.edit.addEventListener('blur', function () { if (self.editing) self.commit(); });
    this.el.fxi.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); var a0 = self.curAddr(), cur = XL.rawText(self.wb.getRaw(self.sheet, a0), self.wb.fmtOf(self.sheet, a0)).replace(/\n/g, ' '); if (self.el.fxi.value !== cur) self.setFromText(self.el.fxi.value); self.move(1, 0); self.el.scroll.focus(); }
      else if (e.key === 'Escape') { self.refreshBar(); self.el.scroll.focus(); }
    });
    if (this.tb) {
      var rib = root.querySelector('.xl-rib'), top = root.querySelector('.xl-title');
      var keepFocus = function (e) { if (!e.target.closest('input,select')) e.preventDefault(); };
      rib.addEventListener('mousedown', keepFocus); top.addEventListener('mousedown', keepFocus); root.querySelector('.xl-stat').addEventListener('mousedown', keepFocus);
      root.addEventListener('click', function (e) {
        var rt = e.target.closest('[data-rt]');
        if (rt) { root.querySelectorAll('[data-rt]').forEach(function (b) { b.classList.toggle('on', b === rt); }); root.querySelectorAll('.xl-pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane === rt.dataset.rt); }); return; }
        var mb = e.target.closest('[data-menu]');
        if (mb) { var mn = mb.parentNode.querySelector('.xl-menu'), was = mn.classList.contains('open'); XL.closeMenus(); if (!was) mn.classList.add('open'); e.stopPropagation(); return; }
        var a = e.target.closest('[data-act]');
        if (a && !a.disabled) { XL.closeMenus(); self.act(a.dataset.act); }
      });
      root.querySelectorAll('select[data-sel]').forEach(function (sl) {
        sl.addEventListener('change', function () {
          var v = sl.value;
          if (sl.dataset.sel === 'fmt') { sl.value = ''; if (v) self.setFmt(v); self.el.scroll.focus(); }
          else if (sl.dataset.sel === 'zoom') { self.setZoom(parseInt(v, 10)); self.el.scroll.focus(); }
        });
      });
      this.el.zr.addEventListener('input', function () { self.setZoom(parseInt(self.el.zr.value, 10)); });
      this.el.fq.addEventListener('input', function () { self.findQ = self.el.fq.value.trim().toLowerCase(); self.findI = -1; self.paintHits(); });
      this.el.fq.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); self.findNext(); } else if (e.key === 'Escape') { self.el.scroll.focus(); } e.stopPropagation(); });
    }
    XL.initGlobal();
    this.render();
  };
  // pendengar global (sekali saja): seret pilihan, ubah lebar kolom, tutup menu
  XL.closeMenus = function () { document.querySelectorAll('.xl-menu.open').forEach(function (m) { m.classList.remove('open'); }); };
  XL.initGlobal = function () {
    if (XL._gi) return; XL._gi = true;
    document.addEventListener('click', function () { XL.closeMenus(); });
    document.addEventListener('mousemove', function (e) {
      var g = XL._act; if (!g) return;
      if (g.rz) { g.rzMove(e); return; }
      if (!g.drag) return;
      var t = e.target.closest ? e.target.closest('td[data-a]') : null;
      if (t) { var p = XL.parseAddr(t.dataset.a); g.extend(p.r, p.c, true); }
    });
    document.addEventListener('mouseup', function () { var g = XL._act; if (!g) return; g.drag = false; if (g.rz) g.rzEnd(); });
  };
  var G = XL.Grid.prototype;
  G.cur = function () { return this.wb.sheets[this.sheet]; };
  G.curAddr = function () { return XL.addr(this.sel.r, this.sel.c); };
  G.R = function () { var g = this.rng; return { r1: Math.min(g.r1, g.r2), r2: Math.max(g.r1, g.r2), c1: Math.min(g.c1, g.c2), c2: Math.max(g.c1, g.c2) }; };
  G.rowH = function () { return Math.round((this.compact ? 18 : 24) * this.zoom); };
  G.colW = function (c) { return Math.round((this.cur().widths[XL.numCol(c)] || 90) * this.zoom); };
  G.note = function (msg) { var self = this; if (!this.el.st3) return; this.el.st3.textContent = msg; clearTimeout(this._nt); this._nt = setTimeout(function () { self.el.st3.textContent = ''; }, 3500); };
  G.setSheet = function (name) { if (this.editing) this.commit(); this.sheet = name; this.sel = { r: 1, c: 1 }; this.setRange(1, 1, 1, 1); this.findI = -1; this.render(); if (this.o.onSheet) this.o.onSheet(name); };
  G.clampSel = function () {
    var s = this.cur(), cl = function (v, m) { return Math.max(1, Math.min(m, v)); };
    this.sel = { r: cl(this.sel.r, s.nrows), c: cl(this.sel.c, s.ncols) };
    this.setRange(this.sel.r, this.sel.c, cl(this.far.r, s.nrows), cl(this.far.c, s.ncols));
  };
  // pilihan rentang: sel gabungan selalu ikut terpilih utuh (seperti Excel)
  G.setRange = function (ar, ac, fr, fc) {
    var s = this.cur(), cl = function (v, m) { return Math.max(1, Math.min(m, v)); };
    ar = cl(ar, s.nrows); ac = cl(ac, s.ncols); fr = cl(fr, s.nrows); fc = cl(fc, s.ncols);
    this.anc = { r: ar, c: ac }; this.far = { r: fr, c: fc };
    var g = { r1: Math.min(ar, fr), c1: Math.min(ac, fc), r2: Math.max(ar, fr), c2: Math.max(ac, fc) }, ch = true;
    while (ch) { ch = false; s.merges.forEach(function (m) { if (m.r1 <= g.r2 && m.r2 >= g.r1 && m.c1 <= g.c2 && m.c2 >= g.c1) { if (m.r1 < g.r1) { g.r1 = m.r1; ch = true; } if (m.r2 > g.r2) { g.r2 = m.r2; ch = true; } if (m.c1 < g.c1) { g.c1 = m.c1; ch = true; } if (m.c2 > g.c2) { g.c2 = m.c2; ch = true; } } }); }
    this.rng = g;
  };
  G.select = function (r, c, noScroll) {
    var s = this.cur(); r = Math.max(1, Math.min(s.nrows, r)); c = Math.max(1, Math.min(s.ncols, c));
    var mg = this.wb.mergeAt(this.sheet, r, c); if (mg) { r = mg.r1; c = mg.c1; }
    this.sel = { r: r, c: c }; this.setRange(r, c, r, c); this.paintSel(); this.refreshBar();
    if (!noScroll) this.reveal(r, c);
    if (this.o.onSelect) this.o.onSelect(this.sheet, r, c);
  };
  G.extend = function (r, c, noScroll) {
    var s = this.cur(); r = Math.max(1, Math.min(s.nrows, r)); c = Math.max(1, Math.min(s.ncols, c));
    this.setRange(this.sel.r, this.sel.c, r, c); this.paintSel(); this.refreshBar(); if (!noScroll) this.reveal(r, c);
  };
  G.cellEl = function (r, c) { var tb = this.el.table.tBodies[0]; var tr = tb && tb.rows[r - 1]; return tr ? tr.cells[c] : null; };
  G.reveal = function (r, c) { // geser tampilan agar sel terlihat
    var td = this.cellEl(r, c); if (!td) return; var sc = this.el.scroll;
    var thH = this.el.table.tHead ? this.el.table.tHead.offsetHeight : 0, rw = Math.round(42 * this.zoom), fzH = 0;
    if (this.freeze) { for (var i = 1; i <= this.freeze; i++) { var fr = this.el.table.tBodies[0].rows[i - 1]; if (fr) fzH += fr.offsetHeight; } if (r <= this.freeze) fzH = 0; }
    var top = td.offsetTop, bot = top + td.offsetHeight, lf = td.offsetLeft, rt = lf + td.offsetWidth;
    if (top < sc.scrollTop + thH + fzH) sc.scrollTop = Math.max(0, top - thH - fzH); else if (bot > sc.scrollTop + sc.clientHeight) sc.scrollTop = bot - sc.clientHeight;
    if (lf < sc.scrollLeft + rw) sc.scrollLeft = Math.max(0, lf - rw); else if (rt > sc.scrollLeft + sc.clientWidth) sc.scrollLeft = rt - sc.clientWidth;
  };
  G.move = function (dr, dc) { // melompati sel gabungan seperti Excel
    var m0 = this.wb.mergeAt(this.sheet, this.sel.r, this.sel.c);
    var nr = dr > 0 ? (m0 ? m0.r2 : this.sel.r) + dr : this.sel.r + dr, nc = dc > 0 ? (m0 ? m0.c2 : this.sel.c) + dc : this.sel.c + dc;
    this.select(nr, nc); this.el.scroll.focus();
  };
  G.refreshBar = function () {
    var a = this.curAddr(), R = this.R();
    this.el.ref.textContent = (R.r1 === R.r2 && R.c1 === R.c2) ? a : XL.addr(R.r1, R.c1) + ':' + XL.addr(R.r2, R.c2);
    if (!this.editing) this.el.fxi.value = XL.rawText(this.wb.getRaw(this.sheet, a), this.wb.fmtOf(this.sheet, a)).replace(/\n/g, ' ');
  };

  /* ---- mouse ---- */
  G.mdown = function (e) {
    if (e.button !== 0) return;
    var s = this.cur(), rz = e.target.closest('.xl-rz');
    if (rz) { if (this.editing) this.commit(); var L = XL.numCol(+rz.dataset.c); this.rz = { c: +rz.dataset.c, x: e.clientX, w: s.widths[L] || 90, st: this.wb.state(), moved: false }; e.preventDefault(); return; }
    var td = e.target.closest('td[data-a]'), th = e.target.closest('th[data-r]'), hc = e.target.closest('th[data-c]'), corner = e.target.closest('th.xl-corner');
    if (td) {
      if (this.editing) this.commit();
      var p = XL.parseAddr(td.dataset.a);
      if (e.shiftKey) { this.extend(p.r, p.c, true); this.el.scroll.focus(); e.preventDefault(); } else { this.select(p.r, p.c, true); this.drag = true; }
    } else if (th) { if (this.editing) this.commit(); var r = +th.dataset.r; this.select(r, 1, true); this.setRange(r, 1, r, s.ncols); this.paintSel(); this.refreshBar(); }
    else if (hc) { if (this.editing) this.commit(); var c = +hc.dataset.c; this.select(1, c, true); this.setRange(1, c, s.nrows, c); this.paintSel(); this.refreshBar(); }
    else if (corner) { if (this.editing) this.commit(); this.select(1, 1, true); this.setRange(1, 1, s.nrows, s.ncols); this.paintSel(); this.refreshBar(); }
  };
  G.rzMove = function (e) {
    var z = this.rz, w = Math.max(24, Math.min(800, Math.round(z.w + (e.clientX - z.x) / this.zoom))), s = this.cur(), L = XL.numCol(z.c);
    if (w === s.widths[L]) return; z.moved = true; s.widths[L] = w; s.wc[L] = 1;
    var cols = this.el.table.querySelectorAll('col'); if (cols[z.c]) cols[z.c].style.width = Math.round(w * this.zoom) + 'px';
    var tw = Math.round(42 * this.zoom); for (var c = 1; c <= s.ncols; c++) tw += this.colW(c); this.el.table.style.width = tw + 'px';
  };
  G.rzEnd = function () {
    var z = this.rz; this.rz = null; if (!z || !z.moved) return;
    this.hist.push(z.st); if (this.hist.length > 60) this.hist.shift(); this.fut = [];
    this.render(); if (this.o.onChange) this.o.onChange({ changed: {}, edited: null });
  };

  /* ---- papan ketik ---- */
  G.key = function (e) {
    if (this.editing) return;
    var k = e.key, ctrl = e.ctrlKey || e.metaKey, kl = k.length === 1 ? k.toLowerCase() : k;
    if (ctrl) {
      if (kl === 'z') { e.preventDefault(); this.undo(); }
      else if (kl === 'y') { e.preventDefault(); this.redo(); }
      else if (kl === 'b' && this.tb) { e.preventDefault(); this.act('bold'); }
      else if (kl === 'd' && !this.o.readonly) { e.preventDefault(); this.fillDown(); }
      else if (kl === 'f' && this.tb) { e.preventDefault(); this.el.fq.focus(); this.el.fq.select(); }
      else if (kl === 'a') { e.preventDefault(); var s = this.cur(); this.select(1, 1, true); this.setRange(1, 1, s.nrows, s.ncols); this.paintSel(); this.refreshBar(); }
      return;
    }
    var mv = function (self, dr, dc) { e.preventDefault(); if (e.shiftKey) self.extend(self.far.r + dr, self.far.c + dc); else self.move(dr, dc); };
    if (k === 'ArrowDown') mv(this, 1, 0); else if (k === 'ArrowUp') mv(this, -1, 0);
    else if (k === 'ArrowLeft') mv(this, 0, -1); else if (k === 'ArrowRight') mv(this, 0, 1);
    else if (k === 'Tab') { e.preventDefault(); this.move(0, e.shiftKey ? -1 : 1); }
    else if (k === 'Enter' || k === 'F2') { e.preventDefault(); this.startEdit(null); }
    else if (k === 'Delete' || k === 'Backspace') { e.preventDefault(); this.clearRange(); }
    else if (k === 'Escape' && this.root.classList.contains('xl-fs')) { e.preventDefault(); this.act('full'); }
    else if (k.length === 1 && !e.altKey) { e.preventDefault(); this.startEdit(k); }
  };
  G.startEdit = function (first) {
    var td = this.cellEl(this.sel.r, this.sel.c); if (!td) return;
    if (this.o.readonly) return;
    var ed = this.el.edit, a = this.curAddr();
    ed.style.left = (td.offsetLeft + this.el.table.offsetLeft) + 'px'; ed.style.top = (td.offsetTop + this.el.table.offsetTop) + 'px';
    ed.style.width = Math.max(td.offsetWidth, 120) + 'px'; this.editH = td.offsetHeight; ed.style.fontSize = Math.round(12.5 * this.zoom * 10) / 10 + 'px';
    ed.value = first !== null ? first : XL.rawText(this.wb.getRaw(this.sheet, a), this.wb.fmtOf(this.sheet, a));
    ed.hidden = false; this.editing = true; this.fitEdit(); ed.focus();
    ed.setSelectionRange(ed.value.length, ed.value.length);
    this.el.fxi.value = ed.value.replace(/\n/g, ' ');
    if (this.el.st1) this.el.st1.textContent = 'Ubah';
  };
  G.fitEdit = function () { // tinggi kotak edit mengikuti jumlah baris (Alt+Enter)
    var ed = this.el.edit, n = ed.value.split('\n').length, fs = parseFloat(ed.style.fontSize) || 12.5;
    if (n === 1) { ed.style.lineHeight = Math.max(12, this.editH - 4) + 'px'; ed.style.height = this.editH + 'px'; }
    else { var lh = Math.round(fs * 1.3); ed.style.lineHeight = lh + 'px'; ed.style.height = Math.max(this.editH, n * lh + 6) + 'px'; }
  };
  G.commit = function () {
    if (!this.editing) return; this.editing = false; var v = this.el.edit.value; this.el.edit.hidden = true;
    if (this.el.st1) this.el.st1.textContent = 'Siap';
    this.setFromText(v);
  };
  G.cancel = function () { this.editing = false; this.el.edit.hidden = true; if (this.el.st1) this.el.st1.textContent = 'Siap'; this.refreshBar(); this.el.scroll.focus(); };

  /* ---- perubahan isi (semua lewat mut agar bisa diurungkan) ---- */
  G.mut = function (fn, key, quiet) {
    if (this.o.readonly) return false;
    var before = quiet ? null : this.wb.snapshot(), st = this.wb.state();
    if (fn.call(this) === false) return false;
    this.hist.push(st); if (this.hist.length > 60) this.hist.shift(); this.fut = [];
    this.afterChange(before, key || null); return true;
  };
  G.undo = function () {
    if (this.editing) this.cancel(); if (!this.hist.length) return;
    this.fut.push(this.wb.state()); this.wb.restore(this.hist.pop()); this.clampSel(); this.afterChange(null, null);
  };
  G.redo = function () {
    if (this.editing) this.cancel(); if (!this.fut.length) return;
    this.hist.push(this.wb.state()); this.wb.restore(this.fut.pop()); this.clampSel(); this.afterChange(null, null);
  };
  G.setFromText = function (t) {
    var a = this.curAddr(), f = this.wb.fmtOf(this.sheet, a), oldRaw = this.wb.getRaw(this.sheet, a), nv = XL.parseInput(t, f);
    if (nv === oldRaw) { this.refreshBar(); return; }
    var self = this, sh = this.sheet;
    this.mut(function () {
      self.edited[sh + '!' + a] = true; self.wb.setRaw(sh, a, nv);
      if (typeof nv === 'string' && nv.indexOf('\n') >= 0) { var s = self.cur(), g = {}, o = s.gaya[a]; if (o) for (var k in o) g[k] = o[k]; g.w = 1; s.gaya[a] = g; }
    }, sh + '!' + a);
  };
  G.setCell = function (sheet, a, t) { // dipakai tombol "coba otomatis"
    var self = this, f = this.wb.fmtOf(sheet, a);
    this.mut(function () { self.edited[sheet + '!' + a] = true; self.wb.setRaw(sheet, a, XL.parseInput(t, f)); }, sheet + '!' + a);
  };
  G.afterChange = function (before, editedKey) {
    var ch = {}; this.flashSet = {};
    if (before) { var after = this.wb.snapshot(); for (var k in after) { if (before[k] !== after[k]) ch[k] = true; } for (var k2 in before) { if (!(k2 in after)) ch[k2] = true; } }
    this.flashSet = ch; this.render();
    if (this.o.onChange) this.o.onChange({ changed: ch, edited: editedKey });
    var self = this; clearTimeout(this._ft); this._ft = setTimeout(function () { self.flashSet = {}; self.el.table.querySelectorAll('.xl-flash').forEach(function (n) { n.classList.remove('xl-flash'); }); }, 1600);
  };
  G.reset = function () { this.wb.reset(); this.edited = {}; this.flashSet = {}; this.hist = []; this.fut = []; this.render(); if (this.o.onChange) this.o.onChange({ changed: {}, edited: null, reset: true }); };
  G.eachCell = function (fn) { var R = this.R(); for (var r = R.r1; r <= R.r2; r++) for (var c = R.c1; c <= R.c2; c++) fn(XL.addr(r, c), r, c); };
  G.clearRange = function () { var self = this, sh = this.sheet; this.mut(function () { self.eachCell(function (a) { self.edited[sh + '!' + a] = true; self.wb.setRaw(sh, a, null); }); }); };

  /* ---- gaya sel ---- */
  G.gayaAktif = function () { return this.cur().gaya[this.curAddr()] || {}; };
  G.setGaya = function (prop, val) { // val kosong -> hapus
    var s = this.cur();
    this.mut(function () {
      this.eachCell(function (a) {
        var g = {}, o = s.gaya[a]; if (o) for (var k in o) g[k] = o[k];
        if (val === '' || val === 0 || val === false || val === null) delete g[prop]; else g[prop] = val;
        if (Object.keys(g).length) s.gaya[a] = g; else delete s.gaya[a];
      });
    }, null, true);
  };
  G.setFmt = function (code) {
    var s = this.cur(), wb = this.wb, sh = this.sheet;
    this.mut(function () {
      this.eachCell(function (a) {
        var raw = wb.getRaw(sh, a);
        if (code === 'txt' && typeof raw === 'number') wb.setRaw(sh, a, String(raw));
        if (code === 'gen' || code === '') delete s.cellFmt[a]; else s.cellFmt[a] = code;
      });
      wb.memo = null;
    }, null, true);
  };
  G.toggleKind = function (kind) {
    var cur = pc(this.wb.fmtOf(this.sheet, this.curAddr())).k; this.setFmt(cur === kind ? 'gen' : kind);
  };
  G.decimals = function (dir) {
    var s = this.cur(), wb = this.wb, sh = this.sheet;
    this.mut(function () {
      this.eachCell(function (a) {
        var res = wb.safe(sh, a); if (typeof res.v !== 'number') return;
        var p = pc(wb.fmtOf(sh, a)); if (p.k === 'txt') return;
        var kind = (p.k === '' || (p.k === 'gen' && p.d === null)) ? 'sep' : p.k, d = p.d !== null ? p.d : (kind === 'sep' || kind === 'rp' ? 0 : desimalAlami(res.v, kind === 'pct'));
        d = Math.max(0, Math.min(6, d + dir)); s.cellFmt[a] = kind + ':' + d;
      });
    }, null, true);
  };

  /* ---- sisip / hapus ---- */
  G.struct = function (ax, op, at, n) {
    var s = this.cur(), sh = this.sheet, self = this;
    if (op === 'ins' && ax === 'r' && s.nrows + n > MAXR) { this.note('Maksimal ' + MAXR + ' baris.'); return; }
    if (op === 'ins' && ax === 'c' && s.ncols + n > MAXC) { this.note('Maksimal ' + MAXC + ' kolom.'); return; }
    if (op === 'del' && ((ax === 'r' && n >= s.nrows) || (ax === 'c' && n >= s.ncols))) { this.note('Tidak bisa menghapus semuanya.'); return; }
    this.mut(function () { self.wb.structural(sh, ax, op, at, n); self.edited = {}; self.clampSel(); }, null, true);
    var s2 = this.cur();
    if (op === 'ins') { if (ax === 'r') { this.sel = { r: at, c: this.sel.c }; this.setRange(at, this.sel.c, at + n - 1, this.sel.c); } else { this.sel = { r: this.sel.r, c: at }; this.setRange(this.sel.r, at, this.sel.r, at + n - 1); } }
    else { this.select(Math.min(this.sel.r, s2.nrows), Math.min(this.sel.c, s2.ncols), true); }
    this.paintSel(); this.refreshBar();
  };

  /* ---- isi ke bawah & nomor ---- */
  G.fillDown = function () {
    var R = this.R(), s = this.cur(), wb = this.wb, sh = this.sheet, self = this;
    var r1 = R.r1, r2 = R.r2;
    if (r1 === r2) { if (r1 < 2) return; r1 = r1 - 1; }
    this.mut(function () {
      for (var c = R.c1; c <= R.c2; c++) {
        var src = XL.addr(r1, c), raw = wb.getRaw(sh, src), g = s.gaya[src], f = s.cellFmt[src];
        for (var r = r1 + 1; r <= r2; r++) {
          var a = XL.addr(r, c), v = raw;
          if (typeof raw === 'string' && raw.charAt(0) === '=') v = XL.geserRumus(raw, r - r1, 0);
          wb.setRaw(sh, a, v); self.edited[sh + '!' + a] = true;
          if (g) s.gaya[a] = JSON.parse(JSON.stringify(g)); else delete s.gaya[a];
          if (f) s.cellFmt[a] = f; else delete s.cellFmt[a];
        }
      }
    });
  };
  /* ---- penomoran kolom: 1,2,3 / 1. / a,b,c / A,B,C / I,II,III / 1.1 ---- */
  function huruf(n, besar) { var t = ''; while (n > 0) { var m = (n - 1) % 26; t = String.fromCharCode((besar ? 65 : 97) + m) + t; n = Math.floor((n - 1) / 26); } return t; }
  function romawi(n, besar) {
    var p = [[1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'], [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']], t = '';
    if (n > 3999) return String(n); p.forEach(function (x) { while (n >= x[0]) { t += x[1]; n -= x[0]; } }); return besar ? t : t.toLowerCase();
  }
  function labelNo(style, n, pre) {
    switch (style) {
      case 'n': return n; case 'n.': return n + '.'; case 'a': return huruf(n, false); case 'A': return huruf(n, true);
      case 'I': return romawi(n, true); case 'i': return romawi(n, false); default: return pre + '.' + n;
    }
  }
  function mirip(style, v) { // apakah v tampak seperti nomor bergaya `style`
    if (style === 'n') return typeof v === 'number' && v === Math.floor(v);
    if (typeof v !== 'string') return false;
    if (style === 'n.') return /^\d+\.$/.test(v); if (style === 'a') return /^[a-z]{1,2}$/.test(v); if (style === 'A') return /^[A-Z]{1,2}$/.test(v);
    if (style === 'I') return /^[IVXLCDM]+$/.test(v); if (style === 'i') return /^[ivxlcdm]+$/.test(v); return /^\d+\.\d+$/.test(v);
  }
  function gayaDari(v) {
    if (typeof v === 'number') return 'n'; if (typeof v !== 'string') return null;
    if (/^\d+\.\d+$/.test(v)) return 'm'; if (/^\d+\.$/.test(v)) return 'n.'; if (/^[IVXLCDM]+$/.test(v)) return 'I'; if (/^[ivxlcdm]+$/.test(v) && v !== 'c' && v !== 'd' && v !== 'l' && v !== 'm') return 'i';
    if (/^[a-z]{1,2}$/.test(v)) return 'a'; if (/^[A-Z]{1,2}$/.test(v)) return 'A'; return null;
  }
  G.numbering = function (style, renum) {
    var R = this.R(), s = this.cur(), wb = this.wb, sh = this.sheet, self = this, c = R.c1, r1 = R.r1, r2 = R.r2;
    if (renum) {
      if (r1 === r2) r2 = s.nrows; // satu sel dipilih: nomori ulang ke bawah
      var first = null; for (var r0 = r1; r0 <= r2 && !first; r0++) { var g0 = gayaDari(wb.getRaw(sh, XL.addr(r0, c))); if (g0) first = g0; }
      if (!first) { this.note('Pilih sel yang berisi nomor dulu.'); return; }
      style = first;
    }
    this.mut(function () {
      var pre = 1;
      if (style === 'm') {
        for (var r = r1 - 1; r >= 1; r--) {
          var x = wb.getRaw(sh, XL.addr(r, c));
          if (typeof x === 'number' && x === Math.floor(x)) { pre = x; break; }
          if (typeof x === 'string' && /^\d+\.\d+$/.test(x)) { pre = parseInt(x, 10); break; }
        }
        if (renum) { var f0 = wb.getRaw(sh, XL.addr(r1, c)); if (typeof f0 === 'string' && /^\d+\.\d+$/.test(f0)) pre = parseInt(f0, 10); }
      }
      var n = 0;
      for (var rr = r1; rr <= r2; rr++) {
        var a = XL.addr(rr, c), cur = wb.getRaw(sh, a);
        if (renum && !mirip(style, cur)) continue;
        n++; self.edited[sh + '!' + a] = true; var v = labelNo(style, n, pre);
        wb.setRaw(sh, a, v);
        if (typeof v === 'string') s.cellFmt[a] = 'txt'; else if (pc(s.cellFmt[a]).k === 'txt') delete s.cellFmt[a];
      }
    });
  };

  /* ---- daftar bernomor / poin di dalam satu sel (beberapa baris) ---- */
  G.listFmt = function (kind) {
    var wb = this.wb, sh = this.sheet, s = this.cur(), self = this, ada = false;
    var RE = /^\s*(?:\d+[.)]|[a-zA-Z][.)]|[•\-–])\s+/, cocok = { n: /^\d+\.\s/, a: /^[a-z]\.\s/, b: /^•\s/ }[kind];
    var ok = this.mut(function () {
      this.eachCell(function (a) {
        var raw = wb.getRaw(sh, a); if (typeof raw !== 'string' || raw.charAt(0) === '=') return; ada = true;
        var lines = raw.split('\n'), isi = lines.filter(function (l) { return l.trim() !== ''; });
        var semua = isi.length && isi.every(function (l) { return cocok.test(l); }), i = 0, out;
        if (semua) out = lines.map(function (l) { return l.replace(RE, ''); });
        else out = lines.map(function (l) { if (l.trim() === '') return l; var t = l.replace(RE, ''); i++; return (kind === 'n' ? i + '. ' : kind === 'a' ? huruf(i, false) + '. ' : '• ') + t; });
        self.edited[sh + '!' + a] = true; wb.setRaw(sh, a, out.join('\n'));
        var g = {}, o = s.gaya[a]; if (o) for (var k in o) g[k] = o[k]; g.w = 1; s.gaya[a] = g;
      });
      if (!ada) return false;
    });
    if (ok === false && !ada) this.note('Pilih sel yang berisi teks dulu.');
  };

  /* ---- gabung sel ---- */
  G.doMerge = function (center) {
    var R = this.R(), s = this.cur(), wb = this.wb, sh = this.sheet, self = this, hapus = 0;
    if (R.r1 === R.r2 && R.c1 === R.c2) { this.note('Pilih lebih dari satu sel dulu.'); return; }
    this.mut(function () {
      s.merges = s.merges.filter(function (m) { return !(m.r1 <= R.r2 && m.r2 >= R.r1 && m.c1 <= R.c2 && m.c2 >= R.c1); });
      for (var r = R.r1; r <= R.r2; r++) for (var c = R.c1; c <= R.c2; c++) {
        if (r === R.r1 && c === R.c1) continue; var a = XL.addr(r, c);
        if (wb.getRaw(sh, a) !== null) { hapus++; wb.setRaw(sh, a, null); }
      }
      s.merges.push({ r1: R.r1, c1: R.c1, r2: R.r2, c2: R.c2 });
      if (center) { var an = XL.addr(R.r1, R.c1), g = {}, o = s.gaya[an]; if (o) for (var k in o) g[k] = o[k]; g.a = 'c'; s.gaya[an] = g; }
    }, null, true);
    this.sel = { r: R.r1, c: R.c1 }; this.setRange(R.r1, R.c1, R.r2, R.c2); this.paintSel(); this.refreshBar();
    if (hapus) this.note('Hanya isi sel kiri-atas yang dipertahankan (Ctrl+Z untuk membatalkan).');
  };
  G.unMerge = function () {
    var R = this.R(), s = this.cur(), ada = false;
    s.merges.forEach(function (m) { if (m.r1 <= R.r2 && m.r2 >= R.r1 && m.c1 <= R.c2 && m.c2 >= R.c1) ada = true; });
    if (!ada) { this.note('Tidak ada sel gabungan di pilihan ini.'); return; }
    this.mut(function () { s.merges = s.merges.filter(function (m) { return !(m.r1 <= R.r2 && m.r2 >= R.r1 && m.c1 <= R.c2 && m.c2 >= R.c1); }); }, null, true);
    this.setRange(R.r1, R.c1, R.r2, R.c2); this.paintSel();
  };

  /* ---- salin / tempel ---- */
  G.copy = function (e, cut) {
    var R = this.R(), wb = this.wb, sh = this.sheet, s = this.cur(), lines = [], cells = [];
    for (var r = R.r1; r <= R.r2; r++) {
      var row = [], crow = [];
      for (var c = R.c1; c <= R.c2; c++) {
        var a = XL.addr(r, c), res = wb.safe(sh, a), v = res.e ? res.e : res.v;
        row.push(v === null || v === undefined ? '' : (typeof v === 'number' ? String(v).replace('.', ',') : (typeof v === 'boolean' ? (v ? 'TRUE' : 'FALSE') : String(v).replace(/[\t\r\n]+/g, ' '))));
        crow.push({ raw: wb.getRaw(sh, a), f: s.cellFmt[a] || '', g: s.gaya[a] ? JSON.parse(JSON.stringify(s.gaya[a])) : null });
      }
      lines.push(row.join('\t')); cells.push(crow);
    }
    var tsv = lines.join('\n');
    if (e && e.clipboardData) e.clipboardData.setData('text/plain', tsv);
    this.clip = { tsv: tsv, cells: cells, r1: R.r1, c1: R.c1 };
    if (cut) this.clearRange();
  };
  G.paste = function (text) {
    if (this.o.readonly) return;
    var norm = text.replace(/\r\n?/g, '\n').replace(/\n$/, ''), rows = norm.split('\n').map(function (l) { return l.split('\t'); });
    var R = this.R(), s = this.cur(), wb = this.wb, sh = this.sheet, self = this, intern = this.clip && this.clip.tsv === norm, clip = this.clip, maxr = R.r1, maxc = R.c1;
    this.mut(function () {
      rows.forEach(function (rw, i) {
        rw.forEach(function (v, j) {
          var r = R.r1 + i, c = R.c1 + j; if (r > MAXR || c > MAXC) return;
          var a = XL.addr(r, c); self.edited[sh + '!' + a] = true;
          if (r > maxr) maxr = r; if (c > maxc) maxc = c;
          if (intern && clip.cells[i] && clip.cells[i][j]) {
            var src = clip.cells[i][j], raw = src.raw;
            if (typeof raw === 'string' && raw.charAt(0) === '=') raw = XL.geserRumus(raw, r - (clip.r1 + i), c - (clip.c1 + j));
            wb.setRaw(sh, a, raw);
            if (src.f) s.cellFmt[a] = src.f; else delete s.cellFmt[a];
            if (src.g) s.gaya[a] = JSON.parse(JSON.stringify(src.g)); else delete s.gaya[a];
          } else wb.setRaw(sh, a, XL.parseInput(v, wb.fmtOf(sh, a)));
        });
      });
      if (maxr > s.nrows) s.nrows = maxr; if (maxc > s.ncols) s.ncols = maxc;
    });
    this.setRange(R.r1, R.c1, maxr, maxc); this.paintSel(); this.refreshBar();
  };

  /* ---- cari ---- */
  G.findAll = function () {
    var q = this.findQ, out = []; if (!q) return out;
    var s = this.cur(), wb = this.wb, sh = this.sheet;
    for (var r = 1; r <= s.nrows; r++) for (var c = 1; c <= s.ncols; c++) {
      var a = XL.addr(r, c); if (wb.getRaw(sh, a) === null) continue;
      var res = wb.safe(sh, a); if (res.e) continue;
      var t = XL.fmtVal(res.v, wb.fmtOf(sh, a)); if (t && t.toLowerCase().indexOf(q) >= 0) out.push([r, c]);
    }
    return out;
  };
  G.paintHits = function () {
    var t = this.el.table, hits = this.findAll(), self = this;
    t.querySelectorAll('td.xl-hit').forEach(function (n) { n.classList.remove('xl-hit'); });
    hits.forEach(function (h) { var td = self.cellEl(h[0], h[1]); if (td) td.classList.add('xl-hit'); });
    if (this.el.hits) this.el.hits.innerHTML = this.findQ ? hits.length + ' hasil ditemukan' : '&nbsp;';
    return hits;
  };
  G.findNext = function () {
    var hits = this.paintHits(); if (!hits.length) return;
    this.findI = (this.findI + 1) % hits.length; this.select(hits[this.findI][0], hits[this.findI][1]);
  };

  /* ---- perintah pita ---- */
  G.setZoom = function (p) {
    p = Math.max(50, Math.min(150, Math.round(p))); this.zoom = p / 100;
    if (this.el.zr) this.el.zr.value = p; if (this.el.zt) this.el.zt.textContent = p + '%';
    var zs = this.root.querySelector('select[data-sel="zoom"]'); if (zs) zs.value = [75, 100, 125].indexOf(p) >= 0 ? String(p) : '100';
    if (this.editing) this.cancel(); this.render();
  };
  G.act = function (name) {
    if (this.editing) this.commit();
    var g = this.gayaAktif();
    switch (name) {
      case 'undo': this.undo(); break;
      case 'redo': this.redo(); break;
      case 'bold': this.setGaya('b', g.b ? 0 : 1); break;
      case 'al-l': this.setGaya('a', g.a === 'l' ? '' : 'l'); break;
      case 'al-c': this.setGaya('a', g.a === 'c' ? '' : 'c'); break;
      case 'al-r': this.setGaya('a', g.a === 'r' ? '' : 'r'); break;
      case 'wrap': this.setGaya('w', g.w ? 0 : 1); break;
      case 'f-rp': this.toggleKind('rp'); break;
      case 'f-pct': this.toggleKind('pct'); break;
      case 'f-sep': this.toggleKind('sep'); break;
      case 'dec+': this.decimals(1); break;
      case 'dec-': this.decimals(-1); break;
      case 'ins-ra': { var R = this.R(); this.struct('r', 'ins', R.r1, R.r2 - R.r1 + 1); break; }
      case 'ins-rb': { var R2 = this.R(); this.struct('r', 'ins', R2.r2 + 1, R2.r2 - R2.r1 + 1); break; }
      case 'ins-c': { var R3 = this.R(); this.struct('c', 'ins', R3.c1, R3.c2 - R3.c1 + 1); break; }
      case 'del-r': { var R4 = this.R(); this.struct('r', 'del', R4.r1, R4.r2 - R4.r1 + 1); break; }
      case 'del-c': { var R5 = this.R(); this.struct('c', 'del', R5.c1, R5.c2 - R5.c1 + 1); break; }
      case 'fill-d': this.fillDown(); break;
      case 'renum': this.numbering('n', true); break;
      case 'merge-c': this.doMerge(true); break;
      case 'merge': this.doMerge(false); break;
      case 'unmerge': this.unMerge(); break;
      case 'list-n': this.listFmt('n'); break;
      case 'list-a': this.listFmt('a'); break;
      case 'list-b': this.listFmt('b'); break;
      case 'find-n': this.findNext(); break;
      default: if (name.indexOf('seq-') === 0) this.numbering(name.slice(4)); break;
      case 'z-': this.setZoom(Math.round(this.zoom * 100) - 10); break;
      case 'z+': this.setZoom(Math.round(this.zoom * 100) + 10); break;
      case 'compact': this.compact = !this.compact; this.render(); break;
      case 'freeze': this.toggleFreeze(); break;
      case 'full': { var on = this.root.classList.toggle('xl-fs'); document.documentElement.classList.toggle('xl-fs-lock', on); this.render(); break; }
    }
    if (!this.editing) this.el.scroll.focus({ preventScroll: true });
  };
  G.toggleFreeze = function () {
    if (this.freeze) { this.freeze = 0; this.render(); return; }
    var m = this.marks[this.sheet] || {}, n = 0;
    for (var r in m) { if (m[r] === 'hdr') { n = +r; break; } }
    if (!n) n = this.sel.r > 1 ? this.sel.r - 1 : 1;
    this.freeze = n; this.render(); this.el.scroll.scrollTop = 0;
  };

  /* ---- tampilan ---- */
  G.setMarks = function (m) { this.marks = m || {}; this.paintMarks(); };
  G.paintMarks = function () {
    var m = this.marks[this.sheet] || {}, bad = (this.badRows || {})[this.sheet] || {};
    var rows = this.el.table.tBodies[0] ? this.el.table.tBodies[0].rows : [];
    for (var i = 0; i < rows.length; i++) { var tr = rows[i], r = +tr.dataset.r, c = m[r] ? 'm-' + m[r] : ''; if (bad[r]) c += ' m-' + bad[r]; if (r <= this.freeze) c += ' xl-fzr'; tr.className = c.trim(); }
  };
  G.setBad = function (b) { this.badRows = b; this.paintMarks(); };
  G.toggleFormulas = function (on) { this.showF = !!on; this.render(); };
  G.paintSel = function () {
    var t = this.el.table, tb = t.tBodies[0], R = this.R(), self = this;
    t.querySelectorAll('.xl-sel,.xl-rg,.xl-prec').forEach(function (n) { n.classList.remove('xl-sel', 'xl-rg', 'xl-prec'); });
    t.querySelectorAll('th.xl-act').forEach(function (n) { n.classList.remove('xl-act'); });
    if (tb) {
      for (var r = R.r1; r <= R.r2; r++) {
        var tr = tb.rows[r - 1]; if (!tr) continue; tr.cells[0].classList.add('xl-act');
        if (R.r1 !== R.r2 || R.c1 !== R.c2) for (var c = R.c1; c <= R.c2; c++) { var td = tr.cells[c]; if (td) td.classList.add('xl-rg'); }
      }
      var ac = this.cellEl(this.sel.r, this.sel.c); if (ac) { ac.classList.add('xl-sel'); ac.classList.remove('xl-rg'); }
    }
    var hs = t.tHead ? t.tHead.rows[0].cells : [];
    for (var c2 = R.c1; c2 <= R.c2; c2++) if (hs[c2]) hs[c2].classList.add('xl-act');
    this.wb.refsOf(this.sheet, this.curAddr()).forEach(function (c) { if (c.sh !== self.sheet) return; var n = t.querySelector('td[data-a="' + c.a + '"]'); if (n) n.classList.add('xl-prec'); });
    var ref = this.wb.refsOf(this.sheet, this.curAddr()).filter(function (c) { return c.sh !== self.sheet; });
    var xr = this.el.xr;
    if (xr) { if (ref.length) { var first = ref[0], uniq = {}; ref.forEach(function (c) { uniq[c.sh + '!' + c.a] = 1; }); var ks = Object.keys(uniq); xr.hidden = false; xr.textContent = 'Ambil dari sheet ' + first.sh + ' (' + ks.length + ' sel) →'; xr.onclick = function () { self.setSheet(first.sh); var p = XL.parseAddr(first.a); self.select(p.r, p.c); }; } else { xr.hidden = true; } }
    if (this.o.onRefs) this.o.onRefs(ref);
    this.syncTb();
  };
  G.syncTb = function () {
    if (!this.tb) return;
    var root = this.root, g = this.gayaAktif(), f = pc(this.wb.fmtOf(this.sheet, this.curAddr())).k;
    var on = function (act, v) { var b = root.querySelector('[data-act="' + act + '"]'); if (b) b.classList.toggle('on', !!v); };
    on('bold', g.b); on('wrap', g.w); on('al-l', g.a === 'l'); on('al-c', g.a === 'c'); on('al-r', g.a === 'r'); on('f-rp', f === 'rp'); on('f-pct', f === 'pct'); on('f-sep', f === 'sep'); on('freeze', this.freeze); var mgb = root.querySelector('[data-menu][title="Gabung sel"]'); if (mgb) mgb.classList.toggle('on', !!this.wb.mergeAt(this.sheet, this.sel.r, this.sel.c)); on('compact', this.compact); on('full', root.classList.contains('xl-fs'));
    var u = root.querySelector('[data-act="undo"]'), rd = root.querySelector('[data-act="redo"]'); if (u) u.disabled = !this.hist.length; if (rd) rd.disabled = !this.fut.length;
    // ringkasan sel terpilih
    var R = this.R(), tot = 0, n = 0;
    if (this.el.st2) {
      if (R.r1 !== R.r2 || R.c1 !== R.c2) for (var r = R.r1; r <= R.r2; r++) for (var c = R.c1; c <= R.c2; c++) { var res = this.wb.safe(this.sheet, XL.addr(r, c)); if (typeof res.v === 'number') { tot += res.v; n++; } }
      var fm = function (x) { return x.toLocaleString('id-ID', { maximumFractionDigits: 2 }); };
      this.el.st2.innerHTML = n > 1 ? 'Rata-rata: <b>' + fm(tot / n) + '</b> &nbsp; Hitung: <b>' + n + '</b> &nbsp; Jumlah: <b>' + fm(tot) + '</b>' : '';
    }
  };
  G.render = function () {
    var s = this.cur(), wb = this.wb, Z = this.zoom, sh = this.sheet, rowW = Math.round(42 * Z), tw = rowW, h = '<colgroup><col style="width:' + rowW + 'px">';
    for (var c = 1; c <= s.ncols; c++) { var cw = this.colW(c); tw += cw; h += '<col style="width:' + cw + 'px">'; }
    var T = this.el.table, minW = this.o.fill ? Math.max(tw, this.el.scroll.clientWidth - 2) : tw;
    if (this.o.extraCol) { h += '<col style="width:300px">'; tw += 300; minW = this.o.fill ? Math.max(tw, this.el.scroll.clientWidth - 2) : tw; }
    T.style.width = minW + 'px'; T.style.setProperty('--rh', this.rowH() + 'px'); T.style.fontSize = (Math.round(12.5 * Z * 10) / 10) + 'px';
    h += '</colgroup><thead><tr><th class="xl-corner"></th>';
    for (var c2 = 1; c2 <= s.ncols; c2++) h += '<th data-c="' + c2 + '">' + XL.numCol(c2) + '<span class="xl-rz" data-c="' + c2 + '"></span></th>';
    if (this.o.extraCol) h += '<th class="xl-notehd">Catatan sistem</th>';
    h += '</tr></thead><tbody>';
    var AL = { l: 'left', c: 'center', r: 'right' }, cov = {}, anc = {};
    s.merges.forEach(function (m) { anc[m.r1 + ',' + m.c1] = m; for (var rr = m.r1; rr <= m.r2; rr++) for (var cc = m.c1; cc <= m.c2; cc++) if (rr !== m.r1 || cc !== m.c1) cov[rr + ',' + cc] = true; });
    for (var r = 1; r <= s.nrows; r++) {
      h += '<tr data-r="' + r + '"><th data-r="' + r + '">' + r + '</th>';
      for (var c3 = 1; c3 <= s.ncols; c3++) {
        if (cov[r + ',' + c3]) { h += '<td class="xl-hid"></td>'; continue; }
        var a = XL.addr(r, c3), res = wb.safe(sh, a), raw = wb.getRaw(sh, a), f = wb.fmtOf(sh, a), g = s.gaya[a] || null, cls = [], text, st = '', inner, mgA = anc[r + ',' + c3] || null;
        if (res.e) { text = res.e; cls.push('xl-err'); }
        else if (this.showF && wb.isFormula(sh, a)) { text = raw; cls.push('xl-ftxt'); }
        else { text = XL.fmtVal(res.v, f); if (typeof res.v === 'number' && pc(f).k !== 'txt') cls.push('n'); }
        if (wb.isFormula(sh, a)) cls.push('xl-f');
        if (this.edited[sh + '!' + a]) cls.push('xl-edt');
        if (this.flashSet[sh + '!' + a]) cls.push('xl-flash');
        if (g) { if (g.b) st += 'font-weight:700;'; if (g.a && AL[g.a]) st += 'text-align:' + AL[g.a] + ';'; if (g.i > 0) st += 'padding-left:' + (4 + 9 * g.i) + 'px;'; if (g.w) cls.push('xl-wr'); }
        inner = esc(text);
        // teks panjang meluber ke sel kosong di kanannya, seperti Excel
        if (text !== '' && typeof res.v === 'string' && !res.e && !mgA && !(g && (g.w || (g.a && g.a !== 'l'))) && !this.showF) {
          var av = this.colW(c3) - 14, j = c3 + 1, ext = false;
          while (j <= s.ncols && wb.getRaw(sh, XL.addr(r, j)) === null) { av += this.colW(j); j++; ext = true; }
          if (ext) { inner = '<div class="xl-sx" style="width:' + av + 'px">' + inner + '</div>'; cls.push('xl-sp'); }
        }
        if (mgA) { cls.push('xl-mg'); }
        h += '<td data-a="' + a + '"' + (mgA ? ' colspan="' + (mgA.c2 - mgA.c1 + 1) + '" rowspan="' + (mgA.r2 - mgA.r1 + 1) + '"' : '') + (cls.length ? ' class="' + cls.join(' ') + '"' : '') + (st ? ' style="' + st + '"' : '') + '>' + inner + '</td>';
      }
      if (this.o.extraCol) h += '<td class="xl-note">' + this.o.extraCol(sh, r) + '</td>';
      h += '</tr>';
    }
    h += '</tbody>'; T.innerHTML = h;
    this.paintMarks(); this.paintSel(); this.refreshBar(); this.renderTabs(); this.applyFreeze();
    if (this.findQ) this.paintHits();
  };
  G.applyFreeze = function () {
    var T = this.el.table, rows = T.tBodies[0].rows, top = T.tHead ? T.tHead.offsetHeight : 0;
    if (!this.freeze) return;
    for (var i = 0; i < this.freeze && i < rows.length; i++) {
      var tr = rows[i];
      for (var j = 0; j < tr.cells.length; j++) { var cell = tr.cells[j]; cell.style.top = top + 'px'; if (j > 0) cell.classList.add('xl-fz'); else cell.style.zIndex = 5; }
      top += tr.offsetHeight;
    }
  };
  G.renderTabs = function () {
    if (!this.el.tabs) return; var self = this, h = '';
    var list = this.o.tabList ? this.o.tabList() : this.wb.order;
    list.forEach(function (n) { var badge = self.o.tabBadge ? self.o.tabBadge(n) : ''; h += '<button type="button" data-sh="' + esc(n) + '" class="' + (n === self.sheet ? 'on' : '') + '">' + esc(n) + (badge || '') + '</button>'; });
    this.el.tabs.innerHTML = h;
    this.el.tabs.querySelectorAll('button').forEach(function (b) { b.onclick = function () { self.setSheet(b.dataset.sh); }; });
  };
  G.highlightRow = function (sheet, r) { if (sheet !== this.sheet) this.setSheet(sheet); this.select(r, 2); };

  /* ---------- tombol ringkas + laci catatan ---------- */
  // Catatan: {t:'bad'|'warn'|'ok', sh:'nama sheet', r:no baris (boleh kosong), h:'HTML aman', w:'label kecil'}
  XL.Laci = function (o) {
    var self = this; this.o = o; this.open = false; this.N = { perlu: [], oke: [] };
    this.pill = o.pill; this.mount = o.mount;
    this.drawer = document.createElement('div'); this.drawer.className = 'xg-drawer'; this.drawer.hidden = true; this.mount.appendChild(this.drawer);
    this.pill.addEventListener('click', function () { self.open = !self.open; self.paint(); });
  };
  XL.Laci.prototype.set = function (N) { this.N = N || { perlu: [], oke: [] }; this.paint(); };
  XL.Laci.prototype.close = function () { this.open = false; this.paint(); };
  XL.Laci.prototype.paint = function () {
    var self = this, N = this.N, bad = N.perlu.some(function (x) { return x.t === 'bad'; });
    this.pill.className = 'xg-pill' + (N.perlu.length ? (bad ? ' bad' : '') : ' ok');
    this.pill.textContent = N.perlu.length ? (bad ? '● ' : '⚠ ') + N.perlu.length + ' perlu dicek' : '✓ Semua sesuai';
    this.pill.setAttribute('aria-expanded', this.open ? 'true' : 'false');
    this.drawer.hidden = !this.open; if (!this.open) return;
    function item(n) { return '<div class="xg-notice ' + n.t + (n.sh ? ' go' : '') + '"' + (n.sh ? ' data-sh="' + esc(n.sh) + '" data-r="' + (n.r || '') + '"' : '') + '><div class="w">' + esc(n.w || '') + '</div>' + n.h + '</div>'; }
    this.drawer.innerHTML = '<div class="dh"><b>Catatan sistem</b><button type="button" class="x" title="Tutup">&times;</button></div><div class="db">' +
      (N.perlu.length ? '<div class="sm">' + (this.o.canJump === false ? 'Catatan yang perlu dicek.' : 'Klik catatan untuk pindah ke barisnya.') + '</div>' + N.perlu.map(item).join('') : '<div class="sm">Tidak ada yang perlu dicek.</div>') +
      (N.oke.length ? '<div class="sm" style="margin-top:10px">Sudah sesuai / dibaca otomatis</div>' + N.oke.map(item).join('') : '') + '</div>';
    this.drawer.querySelector('.x').onclick = function () { self.close(); };
    this.drawer.querySelectorAll('.xg-notice[data-sh]').forEach(function (e) { e.onclick = function () { if (self.o.onJump) self.o.onJump(e.dataset.sh, e.dataset.r ? +e.dataset.r : null); }; });
  };

  /* ---------- gaya (disisipkan sekali) ---------- */
  var CSS = '' +
  '.xlg,.xlg *,.xlg *::before,.xlg *::after{box-sizing:border-box}' +
  '.xlg button,.xlg select,.xlg input{margin:0;letter-spacing:normal;text-transform:none}' +
  '.xlg{border:1px solid #c6c6c6;border-radius:6px;overflow:hidden;background:#fff;color:#1f2328;display:flex;flex-direction:column;font-family:"Segoe UI",system-ui,Arial,sans-serif;position:relative;text-align:left}' +
  '.xlg[hidden]{display:none}.xlg.xl-fs{position:fixed;inset:0;z-index:2000;border-radius:0;border:0}' +
  '.xlg.xl-fs .xl-scroll{max-height:none!important;flex:1;min-height:0}' +
  /* judul + tab pita */
  '.xl-title{background:#217346;color:#fff;display:flex;align-items:center;gap:6px;padding:3px 8px;font-size:12px}' +
  '.xl-fn{flex:1;text-align:center;opacity:.95;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.xl-qa{display:flex;gap:2px}' +
  '.xl-title .xl-b{color:#fff;min-width:26px}.xl-title .xl-b:hover:not([disabled]){background:rgba(255,255,255,.22);border-color:transparent}.xl-title .xl-b.on{background:rgba(255,255,255,.3);border-color:transparent}' +
  '.xl-rt{background:#217346;display:flex;gap:2px;padding:0 8px}' +
  '.xl-rt button{background:transparent;border:0;color:#fff;padding:4px 16px;font:inherit;font-size:12.5px;cursor:pointer;border-radius:4px 4px 0 0;margin:0}.xl-rt button.on{background:#f3f3f3;color:#217346;font-weight:600}' +
  /* pita */
  '.xl-rib{background:#f3f3f3;border-bottom:1px solid #d6d6d6;display:flex;min-height:84px;position:relative;z-index:10}' +
  '.xl-pane{display:none;width:100%;flex-wrap:wrap}.xl-pane.on{display:flex}' +
  '.xl-g{display:flex;flex-direction:column;justify-content:space-between;padding:5px 8px 2px;border-right:1px solid #d6d6d6;flex:0 0 auto;font-size:12.5px}' +
  '.xl-row{display:flex;gap:2px;align-items:center;flex-wrap:nowrap}.xl-rows{display:flex;flex-direction:column;gap:3px;justify-content:center;flex:1}' +
  '.xl-cap{font-size:11px;color:#605e5c;text-align:center;padding-top:2px}' +
  '.xl-b{border:1px solid transparent;background:transparent;color:#1f2328;border-radius:3px;min-width:26px;height:26px;padding:0 5px;font:inherit;font-size:12.5px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;white-space:nowrap;margin:0}' +
  '.xl-b:hover:not([disabled]){background:#e1e1e1;border-color:#d0d0d0}.xl-b.on{background:#c9e2d3;border-color:#217346}.xl-b[disabled]{opacity:.4;cursor:default}' +
  '.xl-b svg{flex:0 0 auto}.xl-big{flex-direction:column;height:62px;min-width:52px;gap:2px;font-size:12px;padding:3px 5px}.xl-car{font-size:9px;font-style:normal}' +
  '.xl-sel{border:1px solid #d0d0d0;background:#fff;color:#1f2328;height:24px;border-radius:3px;font:inherit;font-size:12.5px;padding:0 4px;margin:0}' +
  '.xl-dd{position:relative;display:inline-flex}.xl-menu{position:absolute;top:100%;left:0;background:#fff;border:1px solid #d0d0d0;box-shadow:0 4px 14px rgba(0,0,0,.2);z-index:60;min-width:190px;padding:3px 0;display:none}.xl-menu.open{display:block}' +
  '.xl-menu div{padding:5px 14px;cursor:pointer;white-space:nowrap;font-size:12.5px}.xl-menu div:hover{background:#e8e8e8}' +
  '.xl-fq{height:24px;width:120px;border:1px solid #d0d0d0;border-radius:3px;background:#fff;color:#1f2328;padding:0 6px;font:inherit;font-size:12.5px;margin:0}.xl-hits{font-size:12px;color:#605e5c}' +
  /* kotak nama + rumus */
  '.xl-bar{display:flex;align-items:center;background:#fff;border-bottom:1px solid #d4d4d4;gap:0}' +
  '.xl-ref{min-width:84px;padding:3px 8px;border-right:1px solid #d4d4d4;font-weight:500;font-size:12.5px;color:#1f2328;background:#fff;text-align:left}' +
  '.xl-fxl{padding:0 10px;color:#605e5c;font-style:italic;font-family:Georgia,serif;border-right:1px solid #d4d4d4;align-self:stretch;display:flex;align-items:center;font-size:13px;font-weight:400}' +
  '.xl-fxi{flex:1;min-width:0;border:0;padding:3px 8px;font:inherit;font-size:12.5px;background:#fff;color:#1f2328;min-height:24px}.xl-fxi:focus{outline:0;box-shadow:inset 0 0 0 1px #217346}' +
  '.xl-xr{margin:0 6px;border:1px solid #c4b5fd;background:#f5f3ff;color:#6d28d9;border-radius:999px;font:inherit;font-size:.72rem;font-weight:700;padding:2px 10px;cursor:pointer;white-space:nowrap}' +
  '.xl-xr[hidden]{display:none}' +
  /* tabel */
  '.xl-scroll{overflow:auto;max-height:460px;position:relative;outline:none;background:#fff;flex:0 1 auto}' +
  'table.xl-t{border-collapse:separate;border-spacing:0;table-layout:fixed;margin:0;background:#fff;font-family:Calibri,Carlito,"Segoe UI",Arial,sans-serif;--rh:24px}' +
  'table.xl-t th,table.xl-t td{border-right:1px solid #dadada;border-bottom:1px solid #dadada;padding:0 4px;white-space:nowrap;height:var(--rh);overflow:hidden;text-overflow:ellipsis;line-height:calc(var(--rh) - 2px);position:relative;user-select:none;-webkit-user-select:none}' +
  'table.xl-t thead th{position:sticky;top:0;z-index:3;background:#e6e6e6;color:#605e5c;font-weight:400;text-align:center;border-right:1px solid #bdbdbd;border-bottom:1px solid #bdbdbd;font-size:.92em}' +
  'table.xl-t tbody th{position:sticky;left:0;z-index:2;background:#e6e6e6;color:#605e5c;font-weight:400;text-align:center;cursor:pointer;border-right:1px solid #bdbdbd;font-size:.92em}' +
  'table.xl-t thead th.xl-corner{left:0;z-index:4}' +
  'table.xl-t thead th.xl-act{background:#d2d2d2;color:#217346;font-weight:700;border-bottom:2px solid #217346}' +
  'table.xl-t tbody th.xl-act{background:#d2d2d2;color:#217346;font-weight:700;border-right:2px solid #217346}' +
  '.xl-rz{position:absolute;right:-3px;top:0;bottom:0;width:7px;cursor:col-resize;z-index:6}' +
  'table.xl-t td{cursor:cell;text-align:left}table.xl-t td.n{text-align:right;font-variant-numeric:tabular-nums}' +
  'table.xl-t td.xl-hid{display:none}table.xl-t td.xl-mg{vertical-align:middle}' +
  'table.xl-t td.xl-wr{white-space:pre-line;height:auto;line-height:1.2;padding-top:2px;padding-bottom:2px;vertical-align:bottom;text-overflow:clip}' +
  'table.xl-t td.xl-sp{overflow:visible;z-index:1}.xl-sx{overflow:hidden;white-space:nowrap;text-overflow:clip;pointer-events:none}' +
  'table.xl-t td.xl-err{color:#dc2626;font-weight:700;text-align:center}' +
  'table.xl-t td.xl-ftxt{color:#7c3aed;font-family:ui-monospace,Consolas,monospace;font-size:.9em}' +
  'table.xl-t td.xl-edt{background-color:#fefce8}' +
  'table.xl-t td.xl-rg{background-image:linear-gradient(rgba(33,115,70,.14),rgba(33,115,70,.14))}' +
  'table.xl-t td.xl-hit{background-color:#fff2a8}' +
  'table.xl-t td.xl-prec{outline:2px dashed #7c3aed;outline-offset:-2px;background-color:#f5f3ff}' +
  'table.xl-t td.xl-sel{outline:2px solid #217346;outline-offset:-2px;z-index:2;background-image:none}' +
  'table.xl-t td.xl-flash{animation:xlf 1.5s ease-out}@keyframes xlf{0%{background-color:#86efac}100%{background-color:transparent}}' +
  'table.xl-t td.xl-fz{position:sticky;z-index:2;background-color:#fff}' +
  'table.xl-t tr.xl-fzr:last-child td{border-bottom:2px solid #217346}' +
  'table.xl-t tr.m-item th{box-shadow:inset 3px 0 0 #16a34a}' +
  'table.xl-t tr.m-keg th,table.xl-t tr.m-kat th,table.xl-t tr.m-grup th{box-shadow:inset 3px 0 0 #2563eb}table.xl-t tr.m-keg td,table.xl-t tr.m-kat td{font-weight:700}' +
  'table.xl-t tr.m-total th,table.xl-t tr.m-ringkas th,table.xl-t tr.m-rekap th,table.xl-t tr.m-lanjut th,table.xl-t tr.m-hdr th{box-shadow:inset 3px 0 0 #cbd5e1}' +
  'table.xl-t tr.m-hdr td{background-color:#f1f5f9;font-weight:700}table.xl-t tr.m-total td{font-weight:700;background-color:#fafafa}' +
  'table.xl-t tr.m-bad td{background-color:#fef2f2}table.xl-t tr.m-bad th{box-shadow:inset 3px 0 0 #dc2626}' +
  'table.xl-t tr.m-warn td{background-color:#fffbeb}table.xl-t tr.m-warn th{box-shadow:inset 3px 0 0 #d97706}' +
  'table.xl-t tr.m-pilih td{background-color:#fef9c3}' +
  '.xl-sepr{height:1px;background:#d6d6d6;margin:3px 0;padding:0!important;cursor:default!important}.xl-sepr:hover{background:#d6d6d6!important}' +
  '.xl-edit{resize:none;overflow:hidden;white-space:pre;position:absolute;z-index:8;font-family:Calibri,Carlito,"Segoe UI",Arial,sans-serif;padding:0 3px;border:2px solid #217346;border-radius:0;outline:none;background:#fff;color:#1f2328;margin:0;box-sizing:border-box}' +
  '.xl-edit[hidden]{display:none}' +
  /* lembar & status */
  '.xl-tabs{display:flex;align-items:flex-end;gap:0;padding:0 8px;background:#f3f3f3;border-top:1px solid #d6d6d6;overflow-x:auto;min-height:28px}' +
  '.xl-tabs button{padding:3px 18px;font:inherit;font-size:12px;font-weight:400;color:#1f2328;border:1px solid #d6d6d6;border-top:0;border-bottom:2px solid transparent;border-radius:0;cursor:pointer;background:transparent;white-space:nowrap;margin:0}' +
  '.xl-tabs button:hover{background:#e6e6e6}.xl-tabs button.on{background:#fff;color:#217346;font-weight:600;border-bottom-color:#217346}' +
  '.xl-tabs .tb{display:inline-block;width:8px;height:8px;border-radius:50%;margin-left:6px}' +
  '.xl-stat{display:flex;align-items:center;gap:18px;background:#f3f3f3;border-top:1px solid #d6d6d6;padding:3px 12px;font-size:12px;color:#1f2328;flex-wrap:wrap}' +
  '.xl-stsp{flex:1}.xl-st3{color:#605e5c}.xl-zm{display:flex;align-items:center;gap:6px}.xl-zm input{width:110px;accent-color:#217346;margin:0}.xl-zt{width:36px}' +
  '.xl-zm button{border:0;background:transparent;font-size:16px;line-height:1;cursor:pointer;color:#1f2328;width:20px;padding:0}' +
  '.xg-wrap{position:relative}' +
  '.xg-pill{display:inline-flex;align-items:center;gap:7px;border:1px solid #fde68a;background:#fffbeb;color:#b45309;font-weight:700;font-size:.78rem;border-radius:999px;padding:4px 12px;cursor:pointer;font-family:inherit}' +
  '.xg-pill.ok{border-color:#bbf7d0;background:#f0fdf4;color:#15803d}.xg-pill.bad{border-color:#fecaca;background:#fef2f2;color:#dc2626}' +
  '.xg-drawer{position:absolute;top:0;right:0;bottom:0;width:360px;max-width:92%;background:#fff;border-left:1px solid #cbd5e1;box-shadow:-10px 0 24px rgba(15,23,42,.14);z-index:20;display:flex;flex-direction:column;border-radius:0 10px 10px 0}' +
  '.xg-drawer[hidden]{display:none}' +
  '.xg-drawer .dh{display:flex;align-items:center;padding:10px 14px;border-bottom:1px solid #e2e8f0;gap:8px;font-size:.85rem}.xg-drawer .dh b{flex:1}' +
  '.xg-drawer .db{overflow:auto;padding:10px 14px;flex:1}.xg-drawer .sm{font-size:.72rem;color:#64748b;margin-bottom:6px}' +
  '.xg-drawer .x{border:0;background:transparent;font-size:1.3rem;line-height:1;cursor:pointer;color:#64748b}' +
  '.xg-notice{border:1px solid #e2e8f0;border-left-width:4px;border-radius:8px;padding:8px 10px;margin-bottom:8px;font-size:.78rem;line-height:1.45;background:#fff}' +
  '.xg-notice.go{cursor:pointer}.xg-notice.go:hover{background:#fefce8}.xg-notice.warn{border-left-color:#d97706}.xg-notice.bad{border-left-color:#dc2626}.xg-notice.ok{border-left-color:#16a34a}' +
  '.xg-notice .w{font-size:.68rem;font-weight:700;color:#64748b;margin-bottom:2px}' +
  '.xg-picks{display:flex;gap:8px;flex-wrap:wrap}' +
  '.xg-pick{display:inline-flex;align-items:center;gap:8px;border:1.5px solid #e2e8f0;border-radius:10px;padding:7px 14px;background:#fff;font-weight:600;font-size:.82rem;cursor:pointer;user-select:none;margin:0}' +
  '.xg-pick.on{border-color:#2563eb;background:#eff6ff}.xg-pick input{width:16px;height:16px;margin:0}' +
  'html.xl-fs-lock,html.xl-fs-lock body{overflow:hidden}';
  (function () { var old = document.getElementById('xl-gaya'); if (old) old.parentNode.removeChild(old); var st = document.createElement('style'); st.id = 'xl-gaya'; st.textContent = CSS; (document.head || document.documentElement).appendChild(st); })();

  return XL;
})();
