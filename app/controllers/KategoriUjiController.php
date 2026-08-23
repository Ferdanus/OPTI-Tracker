<?php

class KategoriUjiController
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

    /** GET /kategori-uji */
    public function index($f3)
    {
        $search = trim((string) $f3->get('GET.q'));
        $status = (string) $f3->get('GET.status');

        $sql    = 'SELECT * FROM kategori_pengujian WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql      .= ' AND nama_kategori LIKE ?';
            $params[]  = '%' . $search . '%';
        }
        if ($status !== '') {
            $sql      .= ' AND status = ?';
            $params[]  = $status;
        }
        $sql .= ' ORDER BY nama_kategori ASC';

        $rows = $this->db->exec($sql, $params);

        // Hitung jumlah metode per kategori (untuk badge "x metode")
        $daftar = [];
        foreach ($rows as $row) {
            $jumlah = $this->db->exec(
                'SELECT COUNT(*) AS total FROM metode_uji WHERE kategori_id = ?',
                [$row['id']]
            );
            $row['jumlah_metode'] = $jumlah[0]['total'] ?? 0;
            $daftar[] = $row;
        }

        $f3->set('daftar_kategori', $daftar);
        $f3->set('total_kategori', count($daftar));
        $f3->set('search', $search);
        $f3->set('filter_status', $status);

        $f3->set('page_title', 'Kategori Pengujian');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'admin-order/kategori-pengujian/index.html');
        echo \Template::instance()->render('layout.html');
    }

    /** GET /kategori-uji/tambah */
    public function create($f3)
    {
        $f3->set('kategori', null);
        $f3->set('page_title', 'Tambah Kategori Pengujian');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'admin-order/kategori-pengujian/form.html');
        echo \Template::instance()->render('layout.html');
    }

    /** GET /kategori-uji/@id/edit */
    public function edit($f3, $params)
    {
        $kategori = new KategoriUji($this->db);
        $kategori->load(['id = ?', $params['id']]);

        if ($kategori->dry()) {
            $f3->error(404, 'Kategori pengujian tidak ditemukan.');
            return;
        }

        $f3->set('kategori', $kategori->cast());
        $f3->set('page_title', 'Edit Kategori Pengujian');
        $f3->set('active_menu', 'config');
        $f3->set('content', 'admin-order/kategori-pengujian/form.html');
        echo \Template::instance()->render('layout.html');
    }

    /** POST /kategori-uji/simpan */
    public function store($f3)
    {
        $kategori = new KategoriUji($this->db);
        $this->bind($kategori, $f3);
        $kategori->save();

        $f3->set('SESSION.flash_success', 'Kategori pengujian berhasil ditambahkan.');
        $f3->reroute('/kategori-uji');
    }

    /** POST /kategori-uji/@id/update */
    public function update($f3, $params)
    {
        $kategori = new KategoriUji($this->db);
        $kategori->load(['id = ?', $params['id']]);

        if ($kategori->dry()) {
            $f3->error(404, 'Kategori pengujian tidak ditemukan.');
            return;
        }

        $this->bind($kategori, $f3);
        $kategori->save();

        $f3->set('SESSION.flash_success', 'Kategori pengujian berhasil diperbarui.');
        $f3->reroute('/kategori-uji');
    }

    /** POST /kategori-uji/@id/toggle-status  (tombol show/hide) */
    public function toggleStatus($f3, $params)
    {
        $kategori = new KategoriUji($this->db);
        $kategori->load(['id = ?', $params['id']]);

        if (!$kategori->dry()) {
            $kategori->status = $kategori->status === 'aktif' ? 'nonaktif' : 'aktif';
            $kategori->save();
            $f3->set('SESSION.flash_success', 'Status kategori berhasil diubah.');
        }

        $f3->reroute('/kategori-uji');
    }

    /** POST /kategori-uji/@id/hapus */
    public function delete($f3, $params)
    {
        $jumlah = $this->db->exec(
            'SELECT COUNT(*) AS total FROM metode_uji WHERE kategori_id = ?',
            [$params['id']]
        );

        if (($jumlah[0]['total'] ?? 0) > 0) {
            $f3->set('SESSION.flash_error', 'Kategori tidak bisa dihapus karena masih memiliki metode uji di dalamnya. Sembunyikan (nonaktifkan) saja jika tidak ingin ditampilkan.');
            $f3->reroute('/kategori-uji');
            return;
        }

        $kategori = new KategoriUji($this->db);
        $kategori->load(['id = ?', $params['id']]);

        if (!$kategori->dry()) {
            $kategori->erase();
            $f3->set('SESSION.flash_success', 'Kategori pengujian berhasil dihapus.');
        }

        $f3->reroute('/kategori-uji');
    }

    /** Helper: isi field dari POST ke Mapper */
    protected function bind(KategoriUji $kategori, $f3): void
    {
        $kategori->nama_kategori = trim((string) $f3->get('POST.nama_kategori'));
        $kategori->deskripsi     = trim((string) $f3->get('POST.deskripsi'));
        $kategori->status        = $f3->get('POST.status') === 'aktif' ? 'aktif' : 'nonaktif';
    }
}