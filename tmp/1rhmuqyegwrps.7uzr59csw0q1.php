<style>
    :root{
      --navy:#1b2a4a;
      --navy-light:#2c4270;
      --paper:#ffffff;
      --bg:#eef1f6;
      --line:#c7ccd6;
      --accent:#c8102e;
      --muted:#6b7280;
      --ok:#1c7a4d;
    }
    *{box-sizing:border-box;}
    body{
      margin:0;
      font-family:'Segoe UI',Tahoma,Arial,sans-serif;
      background:var(--bg);
      color:#1b2434;
    }
    header.topbar{
      background:var(--navy);
      color:#fff;
      padding:14px 24px;
      display:flex;
      align-items:center;
      justify-content:space-between;
    }
    header.topbar h1{font-size:16px;margin:0;font-weight:600;letter-spacing:.3px;}
    header.topbar span{font-size:12px;color:#c7d0e8;}
  
    .layout{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:20px;
      padding:20px;
      max-width:1400px;
      margin:0 auto;
    }
    @media (max-width:960px){ .layout{grid-template-columns:1fr;} }
  
    .panel{
      background:var(--paper);
      border-radius:10px;
      box-shadow:0 1px 3px rgba(20,30,60,.08), 0 8px 24px -12px rgba(20,30,60,.15);
      overflow:hidden;
    }
    .panel-head{
      padding:14px 20px;
      border-bottom:1px solid var(--line);
      display:flex;
      align-items:center;
      justify-content:space-between;
    }
    .panel-head h2{font-size:13px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:0;font-weight:700;}
    .panel-body{padding:20px;}
  
    fieldset{
      border:1px solid var(--line);
      border-radius:8px;
      padding:14px 16px 16px;
      margin:0 0 16px;
    }
    legend{font-size:12px;font-weight:700;color:var(--navy);padding:0 6px;}
  
    label.field{display:block;font-size:12.5px;font-weight:600;color:#374151;margin:10px 0 4px;}
    input[type=text], select, textarea{
      width:100%;
      padding:8px 10px;
      border:1px solid var(--line);
      border-radius:6px;
      font-size:13.5px;
      font-family:inherit;
    }
    textarea{resize:vertical;min-height:60px;}
  
    .opt-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;}
    .opt{
      display:flex;align-items:center;gap:8px;
      padding:6px 8px;border-radius:6px;cursor:pointer;
      font-size:13px;
    }
    .opt:hover{background:#f3f5fa;}
    .opt input{
      appearance:none;-webkit-appearance:none;
      width:16px;height:16px;border:1.5px solid #94a3b8;border-radius:3px;
      display:grid;place-content:center;cursor:pointer;flex:none;
    }
    .opt input::before{
      content:"";width:9px;height:9px;transform:scale(0);border-radius:1px;
      box-shadow:inset 1em 1em var(--navy);
      clip-path:polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
    }
    .doc-explain {
    display: flex !important;
    align-items: baseline !important;
    gap: 5px;
    margin: 0 !important;
    padding: 0 !important;
}

.doc-explain .doc-section-title {
    margin: 0 !important;
    padding: 0 !important;
    white-space: nowrap;
}

.doc-explain #d_penjelasan {
    position: static !important;
    display: inline !important;
    margin: 0 !important;
    padding: 0 !important;
    top: auto !important;
    left: auto !important;
    right: auto !important;
    bottom: auto !important;
    transform: none !important;
    text-align: left !important;
}
    .opt input:checked::before{transform:scale(1);}
    .opt.disabled{opacity:.45;pointer-events:none;}
  
    .sub-field{
      margin-top:8px;padding:10px 12px;background:#f8f9fc;border:1px dashed var(--line);
      border-radius:6px;display:none;
    }
    .sub-field.show{display:block;}
  
    .btn-row{display:flex;gap:10px;margin-top:18px;}
    button{
      border:none;border-radius:7px;padding:10px 18px;font-size:13.5px;font-weight:600;cursor:pointer;
    }
    .btn-save{background:#e7eaf3;color:var(--navy);}
    .btn-save:hover{background:#dbe0ee;}
    .btn-send{background:var(--navy);color:#fff;}
    .btn-send:hover{background:var(--navy-light);}
    .status-pill{font-size:11px;padding:3px 9px;border-radius:99px;background:#fde8ea;color:var(--accent);font-weight:700;}
    .status-pill.sent{background:#e4f5ec;color:var(--ok);}
  
    /* ---- dokumen preview meniru file Word ---- */
    .doc-page{
      background:#fff;
      width:100%;
      aspect-ratio:1/1.35;
      max-height:760px;
      margin:0 auto;
      padding:30px 34px;
      border:1px solid var(--line);
      font-family:'Cambria','Georgia',serif;
      font-size:12.5px;
      color:#1b2a4a;
      overflow-y:auto;
    }
    .doc-letterhead{display:flex;align-items:flex-start;justify-content:space-between;border-bottom:2px solid var(--navy);padding-bottom:8px;margin-bottom:10px;}
    .doc-logo{display:flex;align-items:center;gap:8px;font-weight:700;font-size:14px;letter-spacing:.5px;}
    .doc-logo .badge{width:26px;height:26px;border-radius:50%;background:var(--navy);color:#fff;display:grid;place-content:center;font-size:11px;}
    .doc-code{font-size:10.5px;color:var(--muted);}
    .doc-title{text-align:center;font-weight:700;font-size:14.5px;line-height:1.4;margin-bottom:14px;}
    .doc-row{display:flex;margin-bottom:4px;}
    .doc-row .k{width:120px;flex:none;color:#374151;}
    .doc-row .v{flex:1;font-weight:600;border-bottom:1px dotted #c7ccd6;min-height:16px;}
    .doc-section-title{margin:14px 0 6px;font-weight:700;font-size:12px;}
    .doc-opt-grid{display:grid;grid-template-columns:1fr 1fr;gap:2px 16px;font-size:12px;}
    .doc-opt{display:flex;align-items:center;gap:6px;}
    .doc-box{width:11px;height:11px;border:1px solid #1b2a4a;display:inline-flex;align-items:center;justify-content:center;font-size:9px;flex:none;}
    .doc-box.on{background:var(--navy);color:#fff;}
    .doc-note{font-size:11.5px;color:var(--muted);margin-top:4px;}
    .doc-explain{margin-top:16px;font-size:12px;}
    .doc-sign{margin-top:26px;text-align:right;font-size:12px;}
    .doc-sign .place{margin-bottom:38px;}
    .doc-empty{color:#b6bcc8;font-style:italic;}
  
    .live-badge{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--ok);font-weight:700;}
    .live-dot{width:7px;height:7px;border-radius:50%;background:var(--ok);animation:pulse 1.4s infinite;}
    @keyframes pulse{0%,100%{opacity:1;}50%{opacity:.3;}}
  </style>
<div class="layout">

  <!-- ================= FORM ================= -->
  <div class="panel">
    <div class="panel-head">
      <h2>Form Isian</h2>
      <span class="status-pill" id="statusPill">Draft</span>
    </div>
    <div class="panel-body">
      <form id="spForm">

        <fieldset>
          <legend>Identitas Klien / Pelanggan</legend>
          <label class="field">Nama</label>
          <input type="text" id="f_nama" placeholder="Nama lengkap klien">
          <label class="field">Perusahaan</label>
          <input type="text" id="f_perusahaan" placeholder="Nama perusahaan">
          <label class="field">Alamat</label>
          <input type="text" id="f_alamat" placeholder="Alamat perusahaan / klien">
        </fieldset>

        <fieldset>
          <legend>Permintaan Melalui</legend>
          <div class="opt-grid" id="grup1">
            <label class="opt"><input type="radio" name="permintaan" value="telepon">Telepon</label>
            <label class="opt"><input type="radio" name="permintaan" value="email">E-mail</label>
            <label class="opt"><input type="radio" name="permintaan" value="fax">Fax</label>
            <label class="opt"><input type="radio" name="permintaan" value="datang_langsung">Datang langsung</label>
            <label class="opt"><input type="radio" name="permintaan" value="surat">Surat</label>
            <label class="opt"><input type="radio" name="permintaan" value="pegawai_bbspjis" checked>Pegawai BBSPJIS</label>
          </div>
          <div class="sub-field show" id="subPegawai">
            <label class="field" style="margin-top:0;">Nama pegawai (dari tb_arsipuser)</label>
            <select id="f_pegawai">
              <option value="">-- pilih pegawai --</option>
              <option>Ahmad Fauzi</option>
              <option>Rina Kusuma</option>
              <option>Bayu Pratama</option>
              <option>Siti Nur Aini</option>
            </select>
            <label class="field">Kirim ke</label>
            <select id="f_kirimke">
              <option value="">-- pilih tujuan --</option>
              <option value="selulosa">Selulosa</option>
              <option value="lingkungan">Lingkungan</option>
            </select>
          </div>
        </fieldset>

        <fieldset>
          <legend>Mengajukan Permintaan Penawaran Pelayanan Jasa BBSPJIS di Bidang</legend>
          <div class="opt-grid" id="grup2">
            <label class="opt"><input type="radio" name="bidang" value="riset">Riset</label>
            <label class="opt"><input type="radio" name="bidang" value="konsultansi">Konsultansi</label>
            <label class="opt"><input type="radio" name="bidang" value="standardisasi">Standardisasi</label>
            <label class="opt"><input type="radio" name="bidang" value="pelatihan_teknis">Pelatihan Teknis</label>
            <label class="opt"><input type="radio" name="bidang" value="pengujian">Pengujian</label>
            <label class="opt"><input type="radio" name="bidang" value="perekayasaan">Perekayasaan</label>
            <label class="opt"><input type="radio" name="bidang" value="sertifikasi">Sertifikasi</label>
            <label class="opt"><input type="radio" name="bidang" value="lainnya">Lainnya</label>
            <label class="opt"><input type="radio" name="bidang" value="kalibrasi">Kalibrasi</label>
          </div>
          <div class="sub-field" id="subLainnya">
            <label class="field" style="margin-top:0;">Sebutkan bidang lainnya</label>
            <input type="text" id="f_bidang_lainnya" placeholder="Sebutkan...">
          </div>
        </fieldset>

        <fieldset>
          <legend>Penjelasan</legend>
          <textarea id="f_penjelasan" placeholder="Dengan penjelasan sebagai berikut..."></textarea>
        </fieldset>

        <fieldset>
          <legend>Mengajukan Permintaan Penawaran Pelayanan Jasa BBSPJIS di Bidang</legend>
          <div class="opt-grid">
            <label class="opt"><input type="radio" name="bidang" value="lingkungan">Riset</label>
            <label class="opt"><input type="radio" name="bidang" value="opti">Konsultansi</label>
          </div>
        </fieldset>
        

        <div class="btn-row">
          <button type="button" class="btn-save" id="btnSimpan">Simpan</button>
          <button type="button" class="btn-send" id="btnKirim">Kirim</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ================= PREVIEW ================= -->
  <div class="panel">
    <div class="panel-head">
      <h2>Preview Dokumen</h2>
      <div class="live-badge"><span class="live-dot"></span> live sync</div>
    </div>
    <div class="panel-body">
      <div class="doc-page" id="docPage">
        <div class="doc-letterhead">
          <div class="doc-logo"><div class="badge">B</div>BBSPJIS</div>
          <div class="doc-code">F.PJT-08-01/02</div>
        </div>
        <div class="doc-title">FORMULIR SURAT PERMINTAAN<br>PELAYANAN JASA</div>

        <div class="doc-row"><div class="k">Nama</div><div class="v" id="d_nama"></div></div>
        <div class="doc-row"><div class="k">Perusahaan</div><div class="v" id="d_perusahaan"></div></div>
        <div class="doc-row"><div class="k">Alamat</div><div class="v" id="d_alamat"></div></div>

        <div class="doc-section-title">Permintaan melalui (beri tanda ☑):</div>
        <div class="doc-opt-grid" id="d_grup1"></div>
        <div class="doc-note" id="d_pegawai_line"></div>
        <div class="doc-note" id="d_kirimke_line"></div>

        <div class="doc-section-title">Mengajukan permintaan penawaran pelayanan jasa BBSPJIS di bidang (beri tanda ☑):</div>
        <div class="doc-opt-grid" id="d_grup2"></div>
        <div class="doc-note" id="d_lainnya_line"></div>

        <div class="doc-explain">
            <span class="doc-section-title">
                Dengan penjelasan sebagai berikut:
            </span>
        
            <span id="d_penjelasan">
                judshuduuuueghghghshshhsmdkmks
            </span>
        </div>

        <div class="doc-sign">
          <div class="place" id="d_tanggal">Bandung, ...</div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
const opt1Labels = {telepon:'Telepon', email:'E - mail', fax:'Fax', datang_langsung:'Datang langsung', surat:'Surat', pegawai_bbspjis:'Pegawai BBSPJIS (sebutkan)'};
const opt1Order = ['telepon','email','fax','datang_langsung','surat','pegawai_bbspjis'];
const opt2Labels = {riset:'Riset',konsultansi:'Konsultansi',standardisasi:'Standardisasi',pelatihan_teknis:'Pelatihan Teknis',pengujian:'Pengujian',perekayasaan:'Perekayasaan',sertifikasi:'Sertifikasi',lainnya:'Lainnya (sebutkan)',kalibrasi:'Kalibrasi'};
const opt2Order = ['riset','konsultansi','standardisasi','pelatihan_teknis','pengujian','perekayasaan','sertifikasi','lainnya','kalibrasi'];

function renderOptGroup(target, order, labels, selected){
  target.innerHTML = order.map(key=>{
    const on = key===selected;
    return `<div class="doc-opt"><span class="doc-box ${on?'on':''}">${on?'✓':''}</span>${labels[key]}</div>`;
  }).join('');
}

function sync(){
  const nama = document.getElementById('f_nama').value.trim();
  const perusahaan = document.getElementById('f_perusahaan').value.trim();
  const alamat = document.getElementById('f_alamat').value.trim();
  const penjelasan = document.getElementById('f_penjelasan').value.trim();
  const permintaan = document.querySelector('input[name=permintaan]:checked')?.value;
  const bidang = document.querySelector('input[name=bidang]:checked')?.value;
  const pegawai = document.getElementById('f_pegawai').value;
  const kirimke = document.getElementById('f_kirimke').value;
  const bidangLainnya = document.getElementById('f_bidang_lainnya').value.trim();

  document.getElementById('d_nama').textContent = nama || '';
  document.getElementById('d_perusahaan').textContent = perusahaan || '';
  document.getElementById('d_alamat').textContent = alamat || '';

  renderOptGroup(document.getElementById('d_grup1'), opt1Order, opt1Labels, permintaan);
  renderOptGroup(document.getElementById('d_grup2'), opt2Order, opt2Labels, bidang);

  document.getElementById('d_pegawai_line').textContent =
    (permintaan==='pegawai_bbspjis') ? ('Pegawai BBSPJIS: ' + (pegawai || '-')) : '';
  document.getElementById('d_kirimke_line').textContent =
    (permintaan==='pegawai_bbspjis' && kirimke) ? ('Kirim ke: ' + (kirimke==='selulosa'?'Selulosa':'Lingkungan')) : '';
  document.getElementById('d_lainnya_line').textContent =
    (bidang==='lainnya' && bidangLainnya) ? ('Lainnya: ' + bidangLainnya) : '';

  const dp = document.getElementById('d_penjelasan');
  dp.textContent = penjelasan || '-';
  dp.classList.toggle('doc-empty', !penjelasan);

  // toggle sub-field pegawai
  document.getElementById('subPegawai').classList.toggle('show', permintaan==='pegawai_bbspjis');
  document.getElementById('subLainnya').classList.toggle('show', bidang==='lainnya');
}

document.getElementById('spForm').addEventListener('input', sync);
document.getElementById('spForm').addEventListener('change', sync);

const today = new Date();
document.getElementById('d_tanggal').textContent =
  'Bandung, ' + today.toLocaleDateString('id-ID', {day:'numeric', month:'long', year:'numeric'});

document.getElementById('btnSimpan').addEventListener('click', ()=>{
  const pill = document.getElementById('statusPill');
  pill.textContent = 'Draft (created_at diisi)';
  pill.classList.remove('sent');
  // di implementasi asli: fetch POST action=simpan -> hanya set created_at
});
document.getElementById('btnKirim').addEventListener('click', ()=>{
  const pill = document.getElementById('statusPill');
  pill.textContent = 'Terkirim (updated_at diisi)';
  pill.classList.add('sent');
  // di implementasi asli: fetch POST action=kirim -> set updated_at = jam kirim
});

sync();
</script>
</body>
</html>
