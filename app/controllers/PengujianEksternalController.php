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

    /** GET /pengujian-eksternal */
    public function index($f3)
    {
        $search       = trim((string) $f3->get('GET.q'));
        $filterMetode = (string) $f3->get('GET.metode');
        $filterStatus = (string) $f3->get('GET.status');

        $sql = 'SELECT pe.*, m.nama_metode, k.nama_kategori
                FROM pengujian_eksternal pe
                INNER JOIN metode_uji m ON m.id = pe.metode_id
                INNER JOIN kategori_pengujian k ON k.id = m.kategori_id
                WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql      .= ' AND (pe.nama_lembaga LIKE ? OR m.nama_metode LIKE ?)';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
        }
        if ($filterMetode !== '') {
            $sql      .= ' AND pe.metode_id = ?';
            $params[]  = $filterMetode;
        }
        if ($filterStatus !== '') {
            $sql      .= ' AND pe.status = ?';
            $params[]  = $filterStatus;
        }
        $sql .= ' ORDER BY pe.nama_lembaga ASC';

        $daftar = $this->db->exec($sql, $params);

        // Ringkasan
        $totalMitra    = count($daftar);
        $totalAktif    = count(array_filter($daftar, function ($r) { return $r['status'] === 'aktif'; }));
        $totalNonaktif = $totalMitra - $totalAktif;

        // Metode yang sudah ditandai "butuh eksternal" (di menu Metode & Harga Uji)
        // tapi BELUM punya mitra terdaftar sama sekali -> perlu ditindaklanjuti
        $metodeBelumAdaMitra = $this->db->exec(
            "SELECT m.id, m.nama_metode, k.nama_kategori
             FROM metode_uji m
             INNER JOIN kategori_pengujian k ON k.id = m.kategori_id
             WHERE m.butuh_eksternal = 1
               AND m.status = 'aktif'
               AND NOT EXISTS (
                   SELECT 1 FROM pengujian_eksternal pe WHERE pe.metode_id = m.id
               )
             ORDER BY m.nama_metode ASC"
        );

        // Dropdown filter: semua metode yang pernah butuh eksternal
        $semuaMetodeEksternal = $this->db->exec(
            "SELECT m.id, m.nama_metode, k.nama_kategori
             FROM metode_uji m
             INNER JOIN kategori_pengujian k ON k.id = m.kategori_id
             WHERE m.butuh_eksternal = 1
             ORDER BY m.nama_metode ASC"
        );

        $f3->set('daftar_eksternal', $daftar);
        $f3->set('total_mitra', $totalMitra);
        $f3->set('total_aktif', $totalAktif);
        $f3->set('total_nonaktif', $totalNonaktif);
        $f3->set('metode_belum_ada_mitra', $metodeBelumAdaMitra);
        $f3->set('semua_metode_eksternal', $semuaMetodeEksternal);
        $f3->set('search', $search);
        $f3->set('filter_metode', $filterMetode);
        $f3->set('filter_status', $filterStatus);

        $f3->set('page_title', 'Data Pengujian Eksternal');
        $f3->set('active_menu', 'pengujian-eksternal');
        $f3->set('content', 'admin-order/penguji-eksternal/index.html');
        echo \Template::instance()->render('layout.html');
    }

    /** GET /pengujian-eksternal/tambah  (support ?metode=ID untuk preselect) */
    public function create($f3)
    {
        $f3->set('semua_metode_eksternal', $this->db->exec(
            "SELECT m.id, m.nama_metode, k.nama_kategori
             FROM metode_uji m
             INNER JOIN kategori_pengujian k ON k.id = m.kategori_id
             WHERE m.butuh_eksternal = 1 AND m.status = 'aktif'
             ORDER BY m.nama_metode ASC"
        ));
        $f3->set('metode_terpilih', $f3->get('GET.metode'));
        $f3->set('eksternal', null);

        $f3->set('page_title', 'Tambah Data Pengujian Eksternal');
        $f3->set('active_menu', 'pengujian-eksternal');
        $f3->set('content', 'admin-order/penguji-eksternal/form.html');
        echo \Template::instance()->render('layout.html');
    }

    /** GET /pengujian-eksternal/@id/edit */
    public function edit($f3, $params)
    {
        $eksternal = new PengujianEksternal($this->db);
        $eksternal->load(['id = ?', $params['id']]);

        if ($eksternal->dry()) {
            $f3->error(404, 'Data pengujian eksternal tidak ditemukan.');
            return;
        }

        $f3->set('semua_metode_eksternal', $this->db->exec(
            "SELECT m.id, m.nama_metode, k.nama_kategori
             FROM metode_uji m
             INNER JOIN kategori_pengujian k ON k.id = m.kategori_id
             WHERE m.butuh_eksternal = 1
             ORDER BY m.nama_metode ASC"
        ));
        $f3->set('eksternal', $eksternal->cast());

        $f3->set('page_title', 'Edit Data Pengujian Eksternal');
        $f3->set('active_menu', 'pengujian-eksternal');
        $f3->set('content', 'pengujian-eksternal/form.htm');
        echo \Template::instance()->render('layout.htm');
    }

    /** POST /pengujian-eksternal/simpan */
    public function store($f3)
    {
        $eksternal = new PengujianEksternal($this->db);
        $this->bind($eksternal, $f3);
        $eksternal->save();

        $f3->set('SESSION.flash_success', 'Data pengujian eksternal berhasil ditambahkan.');
        $f3->reroute('/pengujian-eksternal');
    }

    /** POST /pengujian-eksternal/@id/update */
    public function update($f3, $params)
    {
        $eksternal = new PengujianEksternal($this->db);
        $eksternal->load(['id = ?', $params['id']]);

        if ($eksternal->dry()) {
            $f3->error(404, 'Data pengujian eksternal tidak ditemukan.');
            return;
        }

        $this->bind($eksternal, $f3);
        $eksternal->save();

        $f3->set('SESSION.flash_success', 'Data pengujian eksternal berhasil diperbarui.');
        $f3->reroute('/pengujian-eksternal');
    }

    /** POST /pengujian-eksternal/@id/toggle-status  (tombol show/hide) */
    public function toggleStatus($f3, $params)
    {
        $eksternal = new PengujianEksternal($this->db);
        $eksternal->load(['id = ?', $params['id']]);

        if (!$eksternal->dry()) {
            $eksternal->status = $eksternal->status === 'aktif' ? 'nonaktif' : 'aktif';
            $eksternal->save();
            $f3->set('SESSION.flash_success', 'Status mitra eksternal berhasil diubah.');
        }

        $f3->reroute('/pengujian-eksternal');
    }

    /** POST /pengujian-eksternal/@id/hapus */
    public function delete($f3, $params)
    {
        $eksternal = new PengujianEksternal($this->db);
        $eksternal->load(['id = ?', $params['id']]);

        if (!$eksternal->dry()) {
            $eksternal->erase();
            $f3->set('SESSION.flash_success', 'Data pengujian eksternal berhasil dihapus.');
        }

        $f3->reroute('/pengujian-eksternal');
    }

    /** Helper: isi field dari POST ke Mapper */
    protected function bind(PengujianEksternal $eksternal, $f3)
    {
        $eksternal->metode_id       = (int) $f3->get('POST.metode_id');
        $eksternal->nama_lembaga    = trim((string) $f3->get('POST.nama_lembaga'));
        $eksternal->alamat          = trim((string) $f3->get('POST.alamat'));
        $eksternal->pic_nama        = trim((string) $f3->get('POST.pic_nama'));
        $eksternal->pic_kontak      = trim((string) $f3->get('POST.pic_kontak'));
        $eksternal->no_akreditasi   = trim((string) $f3->get('POST.no_akreditasi'));
        $eksternal->estimasi_biaya  = (float) $f3->get('POST.estimasi_biaya');
        $eksternal->estimasi_nilai  = (int) $f3->get('POST.estimasi_nilai');
        $eksternal->estimasi_satuan = $f3->get('POST.estimasi_satuan') ?: 'Bulan';
        $eksternal->keterangan      = trim((string) $f3->get('POST.keterangan'));
        $eksternal->status          = $f3->get('POST.status') === 'aktif' ? 'aktif' : 'nonaktif';
    }
}