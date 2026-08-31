<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= ($BASE) ?>/surat-penawaran" class="btn-back"><i class="bi bi-arrow-left"></i> Kembali</a>
  <div class="flex-grow-1">
      <h2 class="h4 fw-bold mb-1 text-dark">Buat Surat Permintaan Pelayanan Jasa</h2>
      <p class="text-muted small mb-0">Isi form di kiri, dokumen di kanan otomatis terisi mengikuti formulir fisik F.PJT-08-01/02.</p>
  </div>
  <span id="statusPill" class="badge badge-pill-secondary"><i class="bi bi-pencil-square"></i> Draft</span>
</div>

<form id="formPenawaran" method="POST" action="<?= ($BASE) ?>/surat-penawaran/simpan">
  <div class="row g-4">
      <div class="col-lg-6">
          <div class="card mb-4">
              <div class="card-header"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-person-badge text-primary me-2"></i>Identitas Klien / Pelanggan</h6></div>
              <div class="card-body p-3 p-md-4">
                  <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" id="inputNama" name="nama" class="form-control" placeholder="Nama lengkap klien" value="Calvin Leonardo"></div>
                  <div class="mb-3"><label class="form-label">Perusahaan</label><input type="text" id="inputPerusahaan" name="perusahaan" class="form-control" placeholder="Nama perusahaan" value="PT. Setia Kawan Makmur Sejahtera"></div>
                  <div class="mb-1"><label class="form-label">Alamat</label><input type="text" id="inputAlamat" name="alamat" class="form-control" placeholder="Alamat perusahaan / klien" value="Kudus"></div>
              </div>
          </div>

          <div class="card mb-4">
              <div class="card-header"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-telephone text-primary me-2"></i>Permintaan Melalui</h6></div>
              <div class="card-body p-3 p-md-4">
                  <div class="row g-2">
                      <div class="col-6">
                          <div class="form-check mb-2"><input class="form-check-input" type="radio" name="permintaan_melalui" id="pmTelepon" value="telepon"><label class="form-check-label small" for="pmTelepon">Telepon</label></div>
                          <div class="form-check mb-2"><input class="form-check-input" type="radio" name="permintaan_melalui" id="pmFax" value="fax"><label class="form-check-label small" for="pmFax">Fax</label></div>
                          <div class="form-check mb-2"><input class="form-check-input" type="radio" name="permintaan_melalui" id="pmSurat" value="surat"><label class="form-check-label small" for="pmSurat">Surat</label></div>
                      </div>
                      <div class="col-6">
                          <div class="form-check mb-2"><input class="form-check-input" type="radio" name="permintaan_melalui" id="pmEmail" value="email"><label class="form-check-label small" for="pmEmail">E-mail</label></div>
                          <div class="form-check mb-2"><input class="form-check-input" type="radio" name="permintaan_melalui" id="pmDatang" value="datang_langsung"><label class="form-check-label small" for="pmDatang">Datang Langsung</label></div>
                          <div class="form-check mb-2"><input class="form-check-input" type="radio" name="permintaan_melalui" id="pmPegawai" value="pegawai_bbspjis"><label class="form-check-label small" for="pmPegawai">Pegawai BBSPJIS</label></div>
                      </div>
                  </div>
                  <div id="pegawaiWrap" class="mt-2 p-3 rounded-3" style="background-color: var(--color-bg); border: 1px dashed var(--color-border); display: none;">
                      <label class="form-label small mb-1">Nama Pegawai</label>
                      <select id="inputPegawai" name="pegawai_id" class="form-select form-select-sm">
                        <option value="">-- Pilih Pegawai --</option>
                        <?php foreach (($daftar_pegawai?:[]) as $pegawai): ?>
                          <option value="<?= ($pegawai['id_user']) ?>"><?= ($pegawai['nama_user']) ?></option>
                        <?php endforeach; ?>
                      </select>
                  </div>
                  
              </div>
          </div>
      </div>

      <div class="col-lg-6">
          <div class="card mb-4" style="position: sticky; top: 20px;">
              <div class="card-header d-flex justify-content-between align-items-center">
                  <h6 class="m-0 fw-bold text-dark"><i class="bi bi-file-earmark-richtext text-primary me-2"></i>Preview Dokumen</h6>
                  <span class="d-flex align-items-center gap-1" style="font-size: .7rem; font-weight: 700; color: #065f46;"><span class="live-dot"></span> live sync</span>
              </div>
              <div class="card-body p-3 p-md-4">
                  <div class="doc-page">
                      <div class="d-flex justify-content-between align-items-start doc-letterhead">
                          <div class="d-flex align-items-center gap-2"><div class="doc-logo-badge">B</div><div class="fw-bold" style="letter-spacing:.5px;">BBSPJIS</div></div>
                          <div class="text-muted" style="font-size:10.5px;">F.PJT-08-01/02</div>
                      </div>
                      <div class="doc-title">FORMULIR SURAT PERMINTAAN<br>PELAYANAN JASA</div>
                      <div class="doc-row"><div class="doc-k">Nama</div><div class="doc-v" id="d_nama">&mdash;</div></div>
                      <div class="doc-row"><div class="doc-k">Perusahaan</div><div class="doc-v" id="d_perusahaan">&mdash;</div></div>
                      <div class="doc-row"><div class="doc-k">Alamat</div><div class="doc-v" id="d_alamat">&mdash;</div></div>
                      <div class="doc-section-title">Permintaan melalui (beri tanda &#9745;):</div>
                      <div class="doc-opt-grid" id="d_grup_permintaan"></div>
                      <div class="doc-note" id="d_pegawai_line"></div>
                      <div class="doc-section-title">Mengajukan permintaan penawaran pelayanan jasa BBSPJIS di bidang (beri tanda &#9745;):</div>
                      <div class="doc-opt-grid" id="d_grup_bidang"></div>
                      <div class="doc-note" id="d_lainnya_line"></div>
                      <div class="doc-note fw-semibold" id="d_divisi_line" style="color: var(--color-primary);"></div>
                      <div class="doc-section-title">Dengan penjelasan sebagai berikut:</div>
                      <div class="doc-v doc-explain-box" id="d_penjelasan">&mdash;</div>
                      <div class="doc-sign">
                          <div id="d_tanggal">Bandung, ...</div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <div class="row g-4">
      <div class="col-lg-6">
          <div class="card mb-4">
              <div class="card-header"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-clipboard2-check text-primary me-2"></i>Mengajukan Permintaan Penawaran di Bidang</h6></div>
              <div class="card-body p-3 p-md-4">
                <div class="row g-2">

                  <div class="col-6">
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                                 
                                 disabled>
                          <label class="form-check-label small" for="bdRiset">
                              Riset
                          </label>
                      </div>
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                            
                                 disabled>
                          <label class="form-check-label small" for="bdStandar">
                              Standardisasi
                          </label>
                      </div>
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                              
                                 disabled>
                          <label class="form-check-label small" for="bdPengujian">
                              Pengujian
                          </label>
                      </div>
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                                
                                 disabled>
                          <label class="form-check-label small" for="bdSertifikasi">
                              Sertifikasi
                          </label>
                      </div>
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                              
                                 disabled>
                          <label class="form-check-label small" for="bdKalibrasi">
                              Kalibrasi
                          </label>
                      </div>
              
                  </div>
              
                  <div class="col-6">
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                              
                                 disabled>
                          <label class="form-check-label small" for="bdKonsul">
                              Konsultansi
                          </label>
                      </div>
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                                
                                 disabled>
                          <label class="form-check-label small" for="bdPelatihan">
                              Pelatihan Teknis
                          </label>
                      </div>
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                              
                                 disabled>
                          <label class="form-check-label small" for="bdRekayasa">
                              Perekayasaan
                          </label>
                      </div>
              
                      <!-- INI SATU-SATUNYA YANG TERPILIH -->
                      <div class="form-check mb-2"> 
                          <input class="form-check-input"
                                 type="checkbox"
                                 value="opti"
                                 id="bdOpti"
                                 name="bidang"
                                 checked
                                 disabled>
                          <label class="form-check-label small" for="bdOpti">
                              OPTI
                          </label>
                      </div>
              
                      <div class="form-check mb-2">
                          <input class="form-check-input"
                                 type="checkbox"
                            
                                 disabled>
                          <label class="form-check-label small" for="bdLainnya">
                              Lainnya
                          </label>
                      </div>
              
                  </div>
              
              </div>
                  <div id="lainnyaWrap" class="mt-2 p-3 rounded-3" style="background-color: var(--color-bg); border: 1px dashed var(--color-border); display: none;">
                      <label class="form-label small mb-1">Sebutkan bidang lainnya</label>
                      <input type="text" id="inputBidangLainnya" class="form-control form-control-sm" placeholder="Sebutkan...">
                  </div>
                  <hr class="my-3">
                  <label class="form-label">Kirim ke Divisi OPTI <span class="text-danger">*</span></label>
                  <div class="d-flex gap-3">
                      <div class="form-check"><input class="form-check-input" type="radio" name="jenis_layanan" id="jlSelulosa" value="selulosa" checked><label class="form-check-label small" for="jlSelulosa">OPTI Selulosa</label></div>
                      <div class="form-check"><input class="form-check-input" type="radio" name="jenis_layanan" id="jlLingkungan" value="lingkungan"><label class="form-check-label small" for="jlLingkungan">OPTI Lingkungan</label></div>
                  </div>
              </div>
          </div>
      </div>

      <div class="col-lg-6">
          <div class="card mb-4">
              <div class="card-header"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-card-text text-primary me-2"></i>Penjelasan</h6></div>
              <div class="card-body p-3 p-md-4">
                  <textarea id="inputPenjelasan" name="penjelasan" class="form-control" rows="6" placeholder="Dengan penjelasan sebagai berikut..."></textarea>
              </div>
          </div>
          <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-outline-secondary px-4" id="btnSimpan">
              <i class="bi bi-save me-1"></i> Simpan sebagai Draft
          </button>
          
          <button type="submit" class="btn btn-primary px-4" id="btnKirim">
              <i class="bi bi-send me-1"></i> Simpan & Kirim
          </button>
              </div>
      </div>
  </div>
</form>

<style>
  .live-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; animation: pulseDot 1.4s infinite; }
  @keyframes pulseDot { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }

  .doc-page {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    padding: 24px 26px;
    font-family: "Times New Roman", Times, serif;
    font-size: 12.5px;
    color: #000;
    max-height: 620px;
    overflow-y: auto;
}
  .doc-letterhead {  border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
  .doc-logo-badge {
      width: 26px; height: 26px; border-radius: 50%;
      background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
      color: #fff; display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700;
  }
  .doc-title { text-align: center; font-weight: 700; font-size: 14px; line-height: 1.4; margin-bottom: 14px; }
  .doc-row { display: flex; margin-bottom: 5px; }
  .doc-k { width: 100px; flex: none; color: #475569; }
  .doc-v { flex: 1; font-weight: 600; border-bottom: 1px dotted var(--color-border); min-height: 16px; }
  .doc-explain-box { border-bottom: none; min-height: 40px; white-space: pre-wrap; }
  .doc-section-title { margin: 14px 0 6px; font-weight: 700; font-size: 12px; }
  .doc-opt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 16px; font-size: 12px; }
  .doc-opt { display: flex; align-items: center; gap: 6px; }
  .doc-box {
      width: 12px; height: 12px; border: 1px solid var(--color-primary-dark);
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 9px; flex: none; border-radius: 2px;
  }
  .doc-box.on { background: #000; border-color: var(--color-primary); color: #fff; }
  .doc-note { font-size: 11.5px; color: var(--color-text-muted); margin-top: 4px; min-height: 14px; }
  .doc-sign { margin-top: 26px; text-align: right; font-size: 12px; }

  .form-check-input:checked { background-color: var(--color-primary); border-color: var(--color-primary); }
  .form-check-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(136, 19, 55, 0.12); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  $('#inputPegawai').select2({
    placeholder: '-- Pilih Pegawai --',
    allowClear: true,
    width: '100%'
});
$('#inputPegawai').on('change', function () {
    updatePreview();
});
  var permintaanLabels = {
      telepon: 'Telepon', fax: 'Fax', surat: 'Surat', email: 'E-mail',
      datang_langsung: 'Datang Langsung', pegawai_bbspjis: 'Pegawai BBSPJIS'
  };
  var permintaanOrder = ['telepon', 'fax', 'surat', 'email', 'datang_langsung', 'pegawai_bbspjis'];

  var bidangLabels = {
    riset: 'Riset',
    standardisasi: 'Standardisasi',
    pengujian: 'Pengujian',
    sertifikasi: 'Sertifikasi',
    kalibrasi: 'Kalibrasi',
    konsultansi: 'Konsultansi',
    pelatihan_teknis: 'Pelatihan Teknis',
    perekayasaan: 'Perekayasaan',
    opti: 'OPTI',
    lainnya: 'Lainnya'
};
var bidangOrder = [
    'riset',
    'standardisasi',
    'pengujian',
    'sertifikasi',
    'kalibrasi',
    'konsultansi',
    'pelatihan_teknis',
    'perekayasaan',
    'opti',
    'lainnya'
];
  function renderRadioGroup(target, order, labels, activeValue) {
      target.innerHTML = order.map(function (key) {
          var on = key === activeValue;
          return '<div class="doc-opt"><span class="doc-box' + (on ? ' on' : '') + '">' + (on ? '&#10003;' : '') + '</span>' + labels[key] + '</div>';
      }).join('');
  }

  function renderCheckGroup(target, order, labels, activeValues) {
      target.innerHTML = order.map(function (key) {
          var on = activeValues.indexOf(key) !== -1;
          return '<div class="doc-opt"><span class="doc-box' + (on ? ' on' : '') + '">' + (on ? '&#10003;' : '') + '</span>' + labels[key] + '</div>';
      }).join('');
  }

  function updatePreview() {
      document.getElementById('d_nama').textContent = document.getElementById('inputNama').value || '\u2014';
      document.getElementById('d_perusahaan').textContent = document.getElementById('inputPerusahaan').value || '\u2014';
      document.getElementById('d_alamat').textContent = document.getElementById('inputAlamat').value || '\u2014';

      var permintaanEl = document.querySelector('input[name="permintaan_melalui"]:checked');
      var permintaan = permintaanEl ? permintaanEl.value : '';
      renderRadioGroup(document.getElementById('d_grup_permintaan'), permintaanOrder, permintaanLabels, permintaan);

      var pegawaiWrap = document.getElementById('pegawaiWrap');
      var pegawaiSelect = document.getElementById('inputPegawai');
      if (permintaan === 'pegawai_bbspjis') {
          pegawaiWrap.style.display = 'block';
          var opt = pegawaiSelect.options[pegawaiSelect.selectedIndex];
          document.getElementById('d_pegawai_line').textContent = 'Pegawai BBSPJIS: ' + (opt && opt.value ? opt.text : '-');
      } else {
          pegawaiWrap.style.display = 'none';
          document.getElementById('d_pegawai_line').textContent = '';
      }

      var bidangChecked = [];
      document.querySelectorAll('input[name="bidang_layanan[]"]:checked').forEach(function (cb) {
          bidangChecked.push(cb.value);
      });
      if (bidangChecked.indexOf('opti') === -1) {bidangChecked.push('opti');}
      renderCheckGroup(document.getElementById('d_grup_bidang'), bidangOrder, bidangLabels, bidangChecked);

      var lainnyaChecked = document.getElementById('bdLainnya').checked;
      var lainnyaWrap = document.getElementById('lainnyaWrap');
      var lainnyaText = document.getElementById('inputBidangLainnya').value;
      lainnyaWrap.style.display = lainnyaChecked ? 'block' : 'none';
      document.getElementById('d_lainnya_line').textContent = lainnyaChecked ? ('Lainnya: ' + (lainnyaText || '-')) : '';

      // var jenisEl = document.querySelector('input[name="jenis_layanan"]:checked');
      // var jenis = jenisEl ? jenisEl.value : 'selulosa';
      // document.getElementById('d_divisi_line').textContent = 'Dikirim ke: OPTI ' + (jenis === 'lingkungan' ? 'Lingkungan' : 'Selulosa');

      var penjelasan = document.getElementById('inputPenjelasan').value;
      document.getElementById('d_penjelasan').textContent = penjelasan || '\u2014';
  }

  document.getElementById('formPenawaran').addEventListener('input', updatePreview);
  document.getElementById('formPenawaran').addEventListener('change', updatePreview);

  var today = new Date();
  document.getElementById('d_tanggal').textContent = 'Bandung, ' + today.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

  document.getElementById('btnSimpan').addEventListener('click', function () {
      document.getElementById('inputAksi').value = 'simpan';
  });
  document.getElementById('btnKirim').addEventListener('click', function () {
      document.getElementById('inputAksi').value = 'kirim';
  });

  updatePreview();
});
</script>
