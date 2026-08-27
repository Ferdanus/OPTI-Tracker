<?php

class PengujianEksternalController
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

    /** GET /lembaga-eksternal */
    public function index($f3)
    {
        $search       = trim((string) $f3->get('GET.q'));
        $filterStatus = (string) $f3->get('GET.status');

        $sql    = 'SELECT * FROM penguji_eksternal WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql      .= ' AND (nama_lembaga LIKE ? OR alamat LIKE ?)';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
        }
        if ($filterStatus !== '') {
            $sql      .= ' AND status = ?';
            $params[]  = $filterStatus;
        }
        $sql .= ' ORDER BY nama_lembaga ASC';

        $daftar = $this->safeQuery($sql, $params);

        $totalLembaga  = count($daftar);
        $totalAktif    = count(array_filter($daftar, function ($r) { return $r['status'] === 'aktif'; }));
        $totalNonaktif = $totalLembaga - $totalAktif;

        $f3->set('daftar_lembaga', $daftar);
        $f3->set('total_lembaga', $totalLembaga);
        $f3->set('total_aktif', $totalAktif);
        $f3->set('total_nonaktif', $totalNonaktif);
        $f3->set('search', $search);
        $f3->set('filter_status', $filterStatus);

        $f3->set('page_title', 'Lembaga Pengujian Eksternal');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'admin-order/penguji-eksternal/index.html');
        echo \Template::instance()->render('layout.html');
    }

    /** GET /lembaga-eksternal/tambah */
    public function create($f3)
    {
        $f3->set('lembaga', null);
        $f3->set('page_title', 'Tambah Lembaga Pengujian Eksternal');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'admin-order/penguji-eksternal/form.html');
        echo \Template::instance()->render('layout.html');
    }

    /** GET /lembaga-eksternal/@id/edit */
    public function edit($f3, $params)
    {
        $lembaga = new PengujianEksternal($this->db);
        $lembaga->load(['id = ?', $params['id']]);

        if ($lembaga->dry()) {
            $f3->error(404, 'Lembaga tidak ditemukan.');
            return;
        }

        $f3->set('lembaga', $lembaga->cast());
        $f3->set('page_title', 'Edit Lembaga Pengujian Eksternal');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'lembaga-eksternal/form.htm');
        echo \Template::instance()->render('layout.htm');
    }

    /** POST /lembaga-eksternal/simpan */
    public function store($f3)
    {
        $lembaga = new PengujianEksternal($this->db);
        $this->bind($lembaga, $f3);
        $lembaga->save();

        $f3->set('SESSION.flash_success', 'Lembaga pengujian eksternal berhasil ditambahkan.');
        $f3->reroute('/pengujian-eksternal');
    }

    /** POST /lembaga-eksternal/@id/update */
    public function update($f3, $params)
    {
        $lembaga = new PengujianEksternal($this->db);
        $lembaga->load(['id = ?', $params['id']]);

        if ($lembaga->dry()) {
            $f3->error(404, 'Lembaga tidak ditemukan.');
            return;
        }

        $this->bind($lembaga, $f3);
        $lembaga->save();

        $f3->set('SESSION.flash_success', 'Data lembaga berhasil diperbarui.');
        $f3->reroute('/pengujian-eksternal');
    }

    /** POST /lembaga-eksternal/@id/toggle-status  (tombol show/hide) */
    public function toggleStatus($f3, $params)
    {
        $lembaga = new PengujianEksternal($this->db);
        $lembaga->load(['id = ?', $params['id']]);

        if (!$lembaga->dry()) {
            $lembaga->status = $lembaga->status === 'aktif' ? 'nonaktif' : 'aktif';
            $lembaga->save();
            $f3->set('SESSION.flash_success', 'Status lembaga berhasil diubah.');
        }

        $f3->reroute('/pengujian-eksternal');
    }

    /** POST /lembaga-eksternal/@id/hapus */
    public function delete($f3, $params)
    {
        $lembaga = new PengujianEksternal($this->db);
        $lembaga->load(['id = ?', $params['id']]);

        if (!$lembaga->dry()) {
            $lembaga->erase();
            $f3->set('SESSION.flash_success', 'Lembaga berhasil dihapus.');
        }

        $f3->reroute('/pengujian-eksternal');
    }

    /** Helper: jalankan query SELECT, kembalikan array kosong kalau gagal */
    protected function safeQuery($sql, $params = [])
    {
        try {
            return $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }

    /** Helper: isi field dari POST ke Mapper */
    protected function bind(PengujianEksternal $lembaga, $f3)
    {
        $lembaga->nama_lembaga = trim((string) $f3->get('POST.nama_lembaga'));
        $lembaga->alamat       = trim((string) $f3->get('POST.alamat'));
        $lembaga->status       = $f3->get('POST.status') === 'aktif' ? 'aktif' : 'nonaktif';
    }
}