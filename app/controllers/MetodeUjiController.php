<?php


class MetodeUjiController
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

    /** GET /metode-uji  -- accordion per kategori, dengan search & filter */
    public function index($f3)
    {
        $search         = trim((string) $f3->get('GET.q'));
        $filterKategori = (string) $f3->get('GET.kategori');
        $filterStatus   = (string) $f3->get('GET.status');

        $kategoriModel = new KategoriUji($this->db);
        $semuaKategori = $kategoriModel->find(null, ['order' => 'nama_kategori ASC']);

        $daftarKategori = [];
        $totalMetode    = 0;
        $totalAktif     = 0;
        $totalNonaktif  = 0;

        foreach ($semuaKategori as $kat) {
            if ($filterKategori !== '' && (string) $filterKategori !== (string) $kat['id']) {
                continue;
            }

            $sql    = 'SELECT * FROM metode_uji WHERE kategori_id = ?';
            $params = [$kat['id']];

            if ($search !== '') {
                $sql      .= ' AND (nama_metode LIKE ? OR deskripsi_kegunaan LIKE ?)';
                $params[]  = '%' . $search . '%';
                $params[]  = '%' . $search . '%';
            }
            if ($filterStatus !== '') {
                $sql      .= ' AND status = ?';
                $params[]  = $filterStatus;
            }
            $sql .= ' ORDER BY nama_metode ASC';

            $metodeRows = $this->db->exec($sql, $params);

            foreach ($metodeRows as $m) {
                $totalMetode++;
                $m['status'] === 'aktif' ? $totalAktif++ : $totalNonaktif++;
            }

            // Saat sedang mencari/filter, sembunyikan kategori yang tidak ada hasilnya
            if (($search !== '' || $filterStatus !== '') && empty($metodeRows)) {
                continue;
            }

            $kat['metode']    = $metodeRows;
            $daftarKategori[] = $kat;
        }

        $f3->set('daftar_kategori', $daftarKategori);
        $f3->set('semua_kategori', $semuaKategori);
        $f3->set('total_kategori', count($semuaKategori));
        $f3->set('total_metode', $totalMetode);
        $f3->set('total_aktif', $totalAktif);
        $f3->set('total_nonaktif', $totalNonaktif);
        $f3->set('search', $search);
        $f3->set('filter_kategori', $filterKategori);
        $f3->set('filter_status', $filterStatus);

        $f3->set('page_title', 'Metode & Harga Uji');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'admin-order/metode_uji/index.html');
        echo \Template::instance()->render('layout.html');
    }

    /** GET /metode-uji/tambah  (support ?kategori=ID untuk preselect) */
    public function create($f3)
    {
        $kategoriModel = new KategoriUji($this->db);

        $f3->set('semua_kategori', $kategoriModel->find(['status = ?', 'aktif'], ['order' => 'nama_kategori ASC']));
        $f3->set('kategori_terpilih', $f3->get('GET.kategori'));
        $f3->set('metode', null);

        $f3->set('page_title', 'Tambah Metode & Harga Uji');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'admin-order/metode_uji/form.html');
        echo \Template::instance()->render('layout.html');
    }

    /** GET /metode-uji/@id/edit */
    public function edit($f3, $params)
    {
        $metode = new MetodeUji($this->db);
        $metode->load(['id = ?', $params['id']]);

        if ($metode->dry()) {
            $f3->error(404, 'Metode uji tidak ditemukan.');
            return;
        }

        $kategoriModel = new KategoriUji($this->db);
        $f3->set('semua_kategori', $kategoriModel->find(null, ['order' => 'nama_kategori ASC']));
        $f3->set('metode', $metode->cast());

        $f3->set('page_title', 'Edit Metode & Harga Uji');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'admin-order/metode_uji/form.html');
        echo \Template::instance()->render('layout.html');
    }

    /** POST /metode-uji/simpan */
    public function store($f3)
    {
        $metode = new MetodeUji($this->db);
        $this->bind($metode, $f3);
        $metode->save();

        $f3->set('SESSION.flash_success', 'Metode & harga uji berhasil ditambahkan.');
        $f3->reroute('/metode-uji');
    }

    /** POST /metode-uji/@id/update */
    public function update($f3, $params)
    {
        $metode = new MetodeUji($this->db);
        $metode->load(['id = ?', $params['id']]);

        if ($metode->dry()) {
            $f3->error(404, 'Metode uji tidak ditemukan.');
            return;
        }

        $this->bind($metode, $f3);
        $metode->save();

        $f3->set('SESSION.flash_success', 'Metode & harga uji berhasil diperbarui.');
        $f3->reroute('/metode-uji');
    }

    /** POST /metode-uji/@id/toggle-status  (tombol show/hide) */
    public function toggleStatus($f3, $params)
    {
        $metode = new MetodeUji($this->db);
        $metode->load(['id = ?', $params['id']]);

        if (!$metode->dry()) {
            $metode->status = $metode->status === 'aktif' ? 'nonaktif' : 'aktif';
            $metode->save();
            $f3->set('SESSION.flash_success', 'Status metode uji berhasil diubah.');
        }

        $f3->reroute('/metode-uji');
    }

    /** POST /metode-uji/@id/hapus */
    public function delete($f3, $params)
    {
        $metode = new MetodeUji($this->db);
        $metode->load(['id = ?', $params['id']]);

        if (!$metode->dry()) {
            $metode->erase();
            $f3->set('SESSION.flash_success', 'Metode uji berhasil dihapus.');
        }

        $f3->reroute('/metode-uji');
    }

    /** Helper: isi field dari POST ke Mapper */
    protected function bind(MetodeUji $metode, $f3): void
    {
        $metode->kategori_id        = (int) $f3->get('POST.kategori_id');
        $metode->nama_metode        = trim((string) $f3->get('POST.nama_metode'));
        $metode->deskripsi_kegunaan = trim((string) $f3->get('POST.deskripsi_kegunaan'));
        $metode->peralatan          = trim((string) $f3->get('POST.peralatan'));
        $metode->durasi_nilai       = max(1, (int) $f3->get('POST.durasi_nilai'));
        $metode->durasi_satuan      = $f3->get('POST.durasi_satuan') ?: 'Bulan';
        $metode->butuh_eksternal    = $f3->get('POST.butuh_eksternal') ? 1 : 0;
        $metode->harga              = (float) $f3->get('POST.harga');
        $metode->jumlah_sampel      = max(1, (int) $f3->get('POST.jumlah_sampel'));
        $metode->status             = $f3->get('POST.status') === 'aktif' ? 'aktif' : 'nonaktif';
    }
}