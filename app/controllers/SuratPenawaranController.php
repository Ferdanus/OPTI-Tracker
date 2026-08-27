<?php

class SuratPenawaranController
{
    /** @var \Base */
    protected $f3;

    /** @var \DB\SQL */
    protected $db;

    public function __construct()
    {
        $this->f3 = \Base::instance();
        $this->db = $this->f3->get('DB');
    }

    /**
     * GET /surat-penawaran
     * CATATAN: sesuaikan nama tabel/kolom `customer` (nmcustomer) kalau beda di database kamu.
     */
    public function index($f3)
    {
        $search        = trim((string) $f3->get('GET.q'));
        $filterLayanan = (string) $f3->get('GET.jenis_layanan');
        $filterStatus  = (string) $f3->get('GET.status');

        $sql = "SELECT sp.*, c.nmcustomer
        FROM surat_penawaran sp
        INNER JOIN tb_customer c ON c.id_customer = sp.customer_id
        WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql      .= ' AND (sp.nomor_surat LIKE ? OR sp.perihal LIKE ? OR c.nmcustomer LIKE ?)';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
        }
        if ($filterLayanan !== '') {
            $sql      .= ' AND sp.jenis_layanan = ?';
            $params[]  = $filterLayanan;
        }
        if ($filterStatus !== '') {
            $sql      .= ' AND sp.status = ?';
            $params[]  = $filterStatus;
        }
        $sql .= ' ORDER BY sp.tanggal_surat DESC, sp.id DESC';

        $daftar = $this->db->exec($sql, $params);

        $totalSurat    = count($daftar);
        $totalAktif    = count(array_filter($daftar, function ($r) { return $r['status'] === 'aktif'; }));
        $totalNonaktif = $totalSurat - $totalAktif;

        $f3->set('daftar_penawaran', $daftar);
        $f3->set('total_surat', $totalSurat);
        $f3->set('total_aktif', $totalAktif);
        $f3->set('total_nonaktif', $totalNonaktif);
        $f3->set('search', $search);
        $f3->set('filter_layanan', $filterLayanan);
        $f3->set('filter_status', $filterStatus);

        $f3->set('page_title', 'Surat Penawaran');
        $f3->set('active_menu', 'penawaran');
        $f3->set('content', 'tim_mitra/surat Pelayanan/index.html');
        echo \Template::instance()->render('layout.html');
    }
    public function edit($f3, $params)
    {
        $id = (int) ($params['id'] ?? 0);

        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $suratPenawaran = new DB\SQL\Mapper($db, 'surat_penawaran');

        if ($id > 0) { 
            $sp->load(['id = ?', $id]); 
    
            // Ambil data surat_penawaran berdasarkan surat_id
            if (!$sp->dry() && !empty($sp->surat_id)) {
                $suratPenawaran->load([
                    'id = ?', 
                    $sp->surat_id
                ]);
            }
        } 

        // kalau record baru (belum ada di DB), set default sesuai bisnis rule:
        // "Pegawai BBSPJIS" otomatis tercentang karena form ini diisi oleh staf
        if ($sp->dry()) {
            $sp->permintaan_melalui = 'pegawai_bbspjis';
            $sp->status = 'draft';
        }

        // daftar pegawai untuk dropdown (dari tb_arsipuser)
        $arsipUser = new DB\SQL\Mapper($db, 'tb_arsipuser');

$daftarPegawai = $arsipUser->find(
    null,
    ['order' => 'nama_user ASC']
);

        $f3->set('sp', $sp);
        $f3->set('daftar_pegawai', $daftarPegawai);
        $f3->set('opsi_permintaan', [
            'telepon'          => 'Telepon',
            'fax'              => 'Fax',
            'surat'            => 'Surat',
            'email'            => 'E-mail',
            'datang_langsung'  => 'Datang langsung',
            'pegawai_bbspjis'  => 'Pegawai BBSPJIS',
        ]);
        $f3->set('opsi_kirim_ke', [
            'selulosa'    => 'Selulosa',
            'lingkungan'  => 'Lingkungan',
        ]);
        
        $f3->set('page_title', 'Surat Penawaran');
        $f3->set('active_menu', 'penawaran');
        $f3->set('content', 'tim_mitra/surat Pelayanan/form.html');
        echo \Template::instance()->render('layout.html');
    }
    public function show($f3, $params)
    {
        $id = (int) ($params['id'] ?? 0);
        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');
        $sp->load(['id = ?', $id]);

        $f3->set('sp', $sp);
        echo \Template::instance()->render('surat_penawaran/show.htm');
    }
    public function store($f3, $params)
    {
        $id     = (int) ($params['id'] ?? 0);
        $action = $f3->get('POST.action'); // 'simpan' | 'kirim'

        $db = $f3->get('DB');
        $sp = new DB\SQL\Mapper($db, 'tb_surat_penawaran');

        if ($id > 0) {
            $sp->load(['id = ?', $id]);
        }

        $sp->nama               = $f3->get('POST.nama');
        $sp->perusahaan         = $f3->get('POST.perusahaan');
        $sp->alamat             = $f3->get('POST.alamat');
        $sp->permintaan_melalui = $f3->get('POST.permintaan_melalui');

        // hanya isi pegawai_id & kirim_ke kalau opsinya "pegawai_bbspjis"
        if ($sp->permintaan_melalui === 'pegawai_bbspjis') {
            $sp->pegawai_id = $f3->get('POST.pegawai_id') ?: null;
        
        } else {
            $sp->pegawai_id = null;
        }
        $sp->jenis_layanan   = $f3->get('POST.jenis_layanan') ?: null;

        $sp->penjelasan     = $f3->get('POST.penjelasan');

        $isNew = $sp->dry();

        if ($action === 'kirim') {
            $sp->status     = 'terkirim';
            $sp->updated_at = date('Y-m-d H:i:s'); // jam kirim
            if ($isNew) {
                $sp->created_at = date('Y-m-d H:i:s');
            }
        } else { // simpan / draft
            $sp->status = $sp->status ?: 'draft';
            if ($isNew) {
                $sp->created_at = date('Y-m-d H:i:s');
            }
            // updated_at sengaja TIDAK diisi saat simpan, sesuai aturan yang kamu minta
        }

        $sp->save();

        $f3->reroute('/surat-penawaran');
    }


}
