<?php

class SuratPenawaranMitraController extends Controller {

    /** GET /surat-penawaran-mitra */
    public function index($f3) {
        $this->requireAuth();

        $search       = trim((string) ($f3->get('GET.q') ?? ''));
        $filterStatus = (string) ($f3->get('GET.status') ?? '');

        $sql    = 'SELECT * FROM surat_penawaran_mitra WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql      .= ' AND (nomor_surat LIKE ? OR nama_mitra LIKE ? OR perusahaan LIKE ?)';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
        }
        if ($filterStatus !== '') {
            $sql      .= ' AND status = ?';
            $params[]  = $filterStatus;
        }
        $sql .= ' ORDER BY tanggal_surat DESC, id DESC';

        $daftar = $this->safeQuery($sql, $params);

        $f3->set('daftar_surat', $daftar);
        $f3->set('total_surat', count($daftar));
        $f3->set('total_draft', count(array_filter($daftar, function ($r) { return $r['status'] === 'draft'; })));
        $f3->set('total_final', count(array_filter($daftar, function ($r) { return $r['status'] === 'final'; })));
        $f3->set('search', $search);
        $f3->set('filter_status', $filterStatus);

        $this->render('tim_mitra/surat penawaran/index.html', 'Surat Penawaran Mitra', 'surat-penawaran-mitra');
    }

    /** GET /surat-penawaran-mitra/tambah */
    public function create($f3) {
        $this->requireAuth();

        $f3->set('surat', null);

        $this->render('tim_mitra/surat penawaran/form.html', 'Tambah Surat Penawaran Mitra', 'penawaran_mitra');
    }

    /** GET /surat-penawaran-mitra/@id/edit */
    public function edit($f3, $params) {
        $this->requireAuth();

        $id = (int) ($params['id'] ?? 0);
        $surat = new SuratPenawaranMitra($this->db);
        $surat->load(['id = ?', $id]);

        if ($surat->dry()) {
            $this->setFlashError("Surat Penawaran Mitra #{$id} tidak ditemukan.");
            $f3->reroute('/surat-penawaran-mitra');
            return;
        }

        $f3->set('surat', $surat->cast());

        $this->render('tim_mitra/surat penawaran/form.html', 'Edit Surat Penawaran Mitra', 'penawaran_mitra');
    }

    /** POST /surat-penawaran-mitra/simpan */
    public function store($f3) {
        $this->requireAuth();

        try {
            $surat = new SuratPenawaranMitra($this->db);
            $this->bind($surat, $f3);
            $surat->created_at = date('Y-m-d H:i:s');
            $surat->save();

            $this->setFlashSuccess($f3->get('POST.aksi') === 'draft'
                ? 'Surat penawaran disimpan sebagai draft.'
                : 'Surat penawaran berhasil disimpan.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan surat penawaran: ' . $e->getMessage());
        }

        $f3->reroute('/surat-penawaran-mitra');
    }

    /** POST /surat-penawaran-mitra/@id/update */
    public function update($f3, $params) {
        $this->requireAuth();

        $id = (int) ($params['id'] ?? 0);
        $surat = new SuratPenawaranMitra($this->db);
        $surat->load(['id = ?', $id]);

        if ($surat->dry()) {
            $this->setFlashError("Surat Penawaran Mitra #{$id} tidak ditemukan.");
            $f3->reroute('/surat-penawaran-mitra');
            return;
        }

        try {
            $this->bind($surat, $f3);
            $surat->save();

            $this->setFlashSuccess($f3->get('POST.aksi') === 'draft'
                ? 'Perubahan disimpan sebagai draft.'
                : 'Surat penawaran berhasil diperbarui.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui surat penawaran: ' . $e->getMessage());
        }

        $f3->reroute('/surat-penawaran-mitra');
    }

    /** POST /surat-penawaran-mitra/@id/hapus */
    public function delete($f3, $params) {
        $this->requireAuth();

        $id = (int) ($params['id'] ?? 0);
        $surat = new SuratPenawaranMitra($this->db);
        $surat->load(['id = ?', $id]);

        if (!$surat->dry()) {
            $surat->erase();
            $this->setFlashSuccess("Surat Penawaran Mitra #{$id} berhasil dihapus.");
        }

        $f3->reroute('/surat-penawaran-mitra');
    }

    protected function safeQuery($sql, $params = []) {
        try {
            return $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function bind(SuratPenawaranMitra $surat, $f3) {
        $surat->nomor_surat       = trim((string) $f3->get('POST.nomor_surat'));
        $surat->nama_mitra        = trim((string) $f3->get('POST.nama_mitra'));
        $surat->perusahaan        = trim((string) $f3->get('POST.perusahaan'));
        $surat->alamat            = trim((string) $f3->get('POST.alamat'));
        $surat->perihal           = trim((string) $f3->get('POST.perihal'));
        $surat->jenis_layanan     = $f3->get('POST.jenis_layanan') === 'lingkungan' ? 'lingkungan' : 'selulosa';
        $surat->nominal_penawaran = (float) ($f3->get('POST.nominal_real') ?: 0);
        $surat->tanggal_surat     = $f3->get('POST.tanggal_surat') ?: date('Y-m-d');
        $surat->keterangan        = trim((string) $f3->get('POST.keterangan'));
        $surat->status            = $f3->get('POST.aksi') === 'draft' ? 'draft' : 'final';
    }
}