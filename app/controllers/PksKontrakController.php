<?php
/**
 * PksKontrakController
 *
 * Kontrak (PKS) yang disusun dari PO yang sudah disetujui dan didisposisikan ke Humas.
 *   /kontrak                  index: daftar PO disposisi (semua divisi) (tab Perlu dibuat / Draft / Selesai) + Unduh Word (tab Selesai)
 *   /kontrak/@id/form         form + pratinjau live (@id = id PO)
 *   POST /kontrak/@id/simpan  simpan draft            (multipart: field form + logo + hapus_logo)
 *   POST /kontrak/@id/selesai validasi lengkap lalu tandai selesai
 *   /kontrak/@id/unduh        unduh .docx dari template (hanya kontrak selesai)
 *   /kontrak/@id/logo         logo klien (butuh login)
 *
 * Sementara hanya Superadmin. Kalau role Humas sudah ada, tambahkan ke ROLE_BOLEH.
 * Kompatibel PHP 7.2. Butuh: migration_pks_kontrak.sql, PksDocx.php, storage/templates/pks_selulosa_template.docx
 */
class PksKontrakController extends Controller {

    const ROLE_BOLEH = ['superadmin'];    // tambahkan 'humas' nanti
    /** Template Word per divisi (di storage/templates/). Lingkungan sementara memakai template yang sama; ganti nama file kalau sudah ada. */
    const TEMPLATE = [
        'selulosa'   => 'pks_selulosa_template.docx',
        'lingkungan' => 'pks_selulosa_template.docx',
    ];
    const TEMPLATE_DEFAULT = 'pks_selulosa_template.docx';
    const LOGO_MAKS  = 2097152;           // 2 MB

    /** Data BBSPJIS (Pihak Kedua): nilai awal; disimpan per kontrak dan boleh diubah di form. */
    const TETAP = [
        'kepalaNama'    => 'Dodiet Prasetyo',
        'kepalaJabatan' => 'Kepala',
        'kepalaAlamat'  => 'Jl. Raya Dayeuhkolot No.132, Dayeuhkolot, Kec. Dayeuhkolot, Kabupaten Bandung, Jawa Barat 40258',
        'kedUpNama'     => 'Yani Kurniawati, S.S',
        'kedUpJabatan'  => 'Tim Administrasi Kerja Sama dan Humas',
        'kedTelepon'    => '+6222 520 2980 Ext. 116',
        'kedWa'         => '081232370878',
        'kedEmail'      => 'bbs@kemenperin.go.id',
    ];

    /**
     * Definisi isian: id (sama dengan form) => kolom DB, tipe, batas panjang, wajib, label.
     * tipe: teks | area | tgl | uang | bool
     */
    const FIELDS = [
        'nomorUrut'         => ['nomor_urut',          'teks', 30,   true,  'Nomor urut PKS'],
        'nomorPihakPertama' => ['nomor_pihak_pertama', 'teks', 120,  false, 'Nomor perjanjian versi klien'],
        'tanggal'           => ['tanggal_perjanjian',  'tgl',  0,    true,  'Tanggal perjanjian'],
        'perusahaan'        => ['perusahaan',          'teks', 255,  true,  'Nama perusahaan'],
        'alamatPerusahaan'  => ['alamat_perusahaan',   'area', 1000, true,  'Alamat perusahaan'],
        'ttdNama'           => ['ttd_nama',            'teks', 150,  true,  'Nama penandatangan'],
        'ttdJabatan'        => ['ttd_jabatan',         'teks', 150,  true,  'Jabatan penandatangan'],
        'judul'             => ['judul',               'area', 1000, true,  'Judul pekerjaan'],
        'ruangLingkup'      => ['ruang_lingkup',       'area', 6000, true,  'Ruang lingkup'],
        'mulai'             => ['tgl_mulai',           'tgl',  0,    true,  'Tanggal mulai'],
        'selesai'           => ['tgl_selesai',         'tgl',  0,    true,  'Tanggal selesai'],
        'biaya'             => ['biaya',               'uang', 0,    true,  'Nilai kontrak'],
        'sampel'            => ['sampel',              'bool', 0,    false, 'Klien menyiapkan sampel'],
        'upNama'            => ['up_nama',             'teks', 150,  true,  'UP (nama kontak)'],
        'upJabatan'         => ['up_jabatan',          'teks', 150,  false, 'Jabatan kontak'],
        'alamatNotif'       => ['alamat_notif',        'area', 1000, true,  'Alamat pemberitahuan'],
        'telepon'           => ['telepon',             'teks', 60,   true,  'Telepon'],
        'whatsapp'          => ['whatsapp',            'teks', 60,   false, 'Whats App'],
        'email'             => ['email',               'teks', 150,  true,  'Email'],
        'kepalaNama'        => ['kepala_nama',         'teks', 150,  true,  'Nama Kepala BBSPJIS'],
        'kepalaJabatan'     => ['kepala_jabatan',      'teks', 150,  true,  'Jabatan Kepala BBSPJIS'],
        'kepalaAlamat'      => ['kepala_alamat',       'area', 1000, true,  'Alamat BBSPJIS'],
        'kedUpNama'         => ['ked_up_nama',         'teks', 150,  true,  'UP Pihak Kedua'],
        'kedUpJabatan'      => ['ked_up_jabatan',      'teks', 150,  false, 'Jabatan UP Pihak Kedua'],
        'kedTelepon'        => ['ked_telepon',         'teks', 60,   true,  'Telepon Pihak Kedua'],
        'kedWa'             => ['ked_wa',              'teks', 60,   false, 'Whats App Pihak Kedua'],
        'kedEmail'          => ['ked_email',           'teks', 150,  true,  'Email Pihak Kedua'],
    ];

    const HARI   = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    const BULAN  = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const ROMAWI = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /* ============================================================
     * HALAMAN
     * ============================================================ */

    /** GET /kontrak */
    public function index($f3) {
        $this->izinkan();

        $errorMessage = null;
        $rows = [];
        $kontrak = [];
        try {
            $rows = $this->db->exec($this->sqlPoDisposisi() . " ORDER BY COALESCE(p.disposisi_humas_at, p.disetujui_at, p.updated_at) DESC");
            foreach ($this->db->exec("SELECT * FROM pks_kontrak") as $k) {
                $kontrak[(int) $k['po_id']] = $k;
            }
        } catch (\Exception $e) {
            $errorMessage = 'Gagal memuat daftar kontrak: ' . $e->getMessage() . ' (sudah menjalankan migration_pks_kontrak.sql?)';
        }

        $daftar = [];
        foreach ($rows as $r) {
            $id = (int) $r['po_id'];
            $k = isset($kontrak[$id]) ? $kontrak[$id] : null;
            $status = $k ? $k['status'] : 'baru';

            $terisi = 0;
            $total = 0;
            if ($k) {
                $vals = $this->dariBaris($k);
                foreach (self::FIELDS as $fid => $def) {
                    if ($def[3]) {
                        $total++;
                        if (!$this->kosong($vals[$fid], $def[1])) { $terisi++; }
                    }
                }
            }

            $daftar[] = [
                'id'        => $id,
                'nomor'     => (string) $r['nomor_po'],
                'divisi'    => ucfirst((string) $r['jenis_layanan_opti']),
                'mitra'     => $this->formatMitra($r['nmcustomer'], $r['pt_cv']),
                'judul'     => $this->pilihJudul($r),
                'biaya'     => $this->pilihBiaya($r),
                'disposisi' => substr((string) $r['tgl_disposisi'], 0, 10),
                'status'    => $status,
                'nomor_pks' => $k ? (string) $k['nomor_pks'] : '',
                'terisi'    => $terisi,
                'total'     => $total,
            ];
        }

        $tab = (string) $f3->get('GET.tab');
        $pesan = (string) $f3->get('GET.pesan');
        $f3->set('daftar_kontrak_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('tab_awal_json', json_encode(in_array($tab, ['baru', 'draft', 'selesai'], true) ? $tab : 'baru'));
        $f3->set('pesan_awal_json', json_encode($pesan === 'selesai' ? 'Kontrak ditandai selesai. Unduh Word tersedia di tab ini.' : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('error_message', $errorMessage);

        $this->render('kontrak/index.html', 'Kontrak (PKS)', 'kontrak');
    }

    /** GET /kontrak/@id/form */
    public function form($f3, $params) {
        $this->izinkan();

        $poId = (int) $params['id'];
        $po = $this->ambilPo($poId);
        if (!$po) {
            $f3->error(404, 'PO tidak ditemukan, atau belum disetujui dan didisposisikan ke Humas.');
            return;
        }

        $db = $this->susunDefault($po);
        $k = $this->ambilKontrak($poId);
        $vals = $k ? $this->dariBaris($k) : $this->nilaiAwal($db);

        $data = [
            'po' => [
                'id'        => $poId,
                'nomor'     => (string) $po['nomor_po'],
                'divisi'    => ucfirst((string) $po['jenis_layanan_opti']),
                'disposisi' => substr((string) $po['tgl_disposisi'], 0, 10),
                'mitra'     => $db['perusahaan'],
            ],
            'db'      => $db,
            'vals'    => $vals,
            'status'  => $k ? $k['status'] : 'baru',
            'logoUrl' => ($k && !empty($k['logo_file'])) ? $f3->get('BASE') . '/kontrak/' . $poId . '/logo?v=' . (int) strtotime((string) $k['updated_at']) : '',
        ];

        $f3->set('kontrak_json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $this->render('kontrak/form.html', 'Kontrak untuk ' . $po['nomor_po'], 'kontrak');
    }

    /* ============================================================
     * API
     * ============================================================ */

    /** POST /kontrak/@id/simpan */
    public function simpan($f3, $params) {
        $this->prosesSimpan($f3, (int) $params['id'], false);
    }

    /** POST /kontrak/@id/selesai */
    public function selesai($f3, $params) {
        $this->prosesSimpan($f3, (int) $params['id'], true);
    }

    protected function prosesSimpan($f3, $poId, $final) {
        $this->izinkan(true);

        $po = $this->ambilPo($poId);
        if (!$po) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'PO tidak ditemukan atau belum didisposisikan ke Humas.'], 404);
        }

        $vals = $this->bacaInput($f3);
        $lama = $this->ambilKontrak($poId);

        // ---- validasi (khusus Selesai) ----
        if ($final) {
            $kosong = [];
            foreach (self::FIELDS as $fid => $def) {
                if ($def[3] && $this->kosong($vals[$fid], $def[1])) { $kosong[] = $fid; }
            }
            if ($kosong) {
                $label = [];
                foreach ($kosong as $fid) { $label[] = self::FIELDS[$fid][4]; }
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Masih ada isian wajib yang kosong: ' . implode(', ', array_slice($label, 0, 4)) . (count($label) > 4 ? ', dan lainnya' : '') . '.', 'kosong' => $kosong], 422);
            }
            if ($vals['selesai'] < $vals['mulai']) {
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Tanggal selesai harus sama atau setelah tanggal mulai.', 'kosong' => ['selesai']], 422);
            }
            $bentrok = $this->db->exec(
                "SELECT po_id FROM pks_kontrak WHERE nomor_pks = ? AND status = 'selesai' AND po_id <> ? LIMIT 1",
                [1 => $this->nomorPks($vals), 2 => $poId]
            );
            if (!empty($bentrok)) {
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Nomor PKS ' . $this->nomorPks($vals) . ' sudah dipakai kontrak lain. Ganti nomor urutnya.', 'kosong' => ['nomorUrut']], 422);
            }
        }

        // ---- logo ----
        $logoFile = $lama ? $lama['logo_file'] : null;
        $logoMime = $lama ? $lama['logo_mime'] : null;
        $hapusLama = null;

        $adaBerkas = isset($_FILES['logo']) && is_array($_FILES['logo']) && (int) $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($adaBerkas) {
            $baru = $this->simpanLogo($_FILES['logo']);
            if ($logoFile) { $hapusLama = $logoFile; }
            $logoFile = $baru['file'];
            $logoMime = $baru['mime'];
        } elseif ((string) $f3->get('POST.hapus_logo') === '1' && $logoFile) {
            $hapusLama = $logoFile;
            $logoFile = null;
            $logoMime = null;
        }

        // ---- simpan ----
        $uid = (int) $this->getUserId();
        $status = $final ? 'selesai' : 'draft';
        $sekarang = date('Y-m-d H:i:s');
        $nomorPks = $this->nomorPks($vals);

        $kolom = [];
        $nilai = [];
        foreach (self::FIELDS as $fid => $def) {
            $kolom[] = $def[0];
            $nilai[] = $this->keNilaiDb($vals[$fid], $def[1]);
        }
        $kolom = array_merge($kolom, ['status', 'nomor_pks', 'logo_file', 'logo_mime', 'updated_by', 'selesai_at']);
        $nilai = array_merge($nilai, [$status, $nomorPks, $logoFile, $logoMime, $uid, $final ? $sekarang : null]);

        try {
            if ($lama) {
                $set = [];
                $p = [];
                $i = 1;
                foreach ($kolom as $idx => $nama) {
                    $set[] = '`' . $nama . '` = ?';
                    $p[$i++] = $nilai[$idx];
                }
                $p[$i++] = $poId;
                $this->db->exec("UPDATE pks_kontrak SET " . implode(', ', $set) . " WHERE po_id = ?", $p);
            } else {
                $kolom[] = 'po_id';       $nilai[] = $poId;
                $kolom[] = 'created_by';  $nilai[] = $uid;
                $ph = [];
                $p = [];
                $i = 1;
                foreach ($kolom as $idx => $nama) {
                    $ph[] = '?';
                    $p[$i++] = $nilai[$idx];
                }
                $this->db->exec("INSERT INTO pks_kontrak (`" . implode('`, `', $kolom) . "`) VALUES (" . implode(', ', $ph) . ")", $p);
            }
        } catch (\Exception $e) {
            if ($adaBerkas) { $this->hapusBerkasLogo($logoFile); }   // batalkan unggahan baru
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan kontrak. Detail: ' . $e->getMessage()], 500);
        }

        if ($hapusLama) { $this->hapusBerkasLogo($hapusLama); }

        $this->keluarkanJson([
            'ok'        => true,
            'status'    => $status,
            'nomor_pks' => $nomorPks,
            'logoUrl'   => $logoFile ? $f3->get('BASE') . '/kontrak/' . $poId . '/logo?v=' . time() : '',
        ]);
    }

    /** GET /kontrak/@id/unduh */
    public function unduh($f3, $params) {
        $this->izinkan();

        $poId = (int) $params['id'];
        $k = $this->ambilKontrak($poId);
        if (!$k || $k['status'] !== 'selesai') {
            $f3->error(404, 'Kontrak belum berstatus selesai, jadi belum bisa diunduh.');
            return;
        }

        $vals = $this->dariBaris($k);
        $logoPath = null;
        if (!empty($k['logo_file'])) {
            $p = $this->dirLogo() . '/' . basename((string) $k['logo_file']);
            if (is_file($p)) { $logoPath = $p; }
        }

        try {
            $isi = PksDocx::buat($this->pathTemplate($this->divisiPo($poId)), $this->susunDataDocx($vals), $logoPath);
        } catch (\Exception $e) {
            $f3->error(500, 'Gagal membuat dokumen Word: ' . $e->getMessage());
            return;
        }

        $nama = 'PKS_' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $k['nomor_pks']), '-')
              . '_' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $this->hapusAwalan($vals['perusahaan'])), '-') . '.docx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $nama . '"');
        header('Content-Length: ' . strlen($isi));
        header('Cache-Control: private, no-store, max-age=0');
        echo $isi;
        exit;
    }

    /** GET /kontrak/@id/logo */
    public function logo($f3, $params) {
        $this->izinkan();

        $k = $this->ambilKontrak((int) $params['id']);
        if (!$k || empty($k['logo_file'])) {
            $f3->error(404);
            return;
        }
        $path = $this->dirLogo() . '/' . basename((string) $k['logo_file']);
        if (!is_file($path)) {
            $f3->error(404);
            return;
        }
        header('Content-Type: ' . ($k['logo_mime'] ?: 'image/png'));
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    /* ============================================================
     * DATA PO -> NILAI AWAL
     * ============================================================ */

    /** SELECT dasar: PO Selulosa yang sudah disetujui + didisposisikan ke Humas. */
    protected function sqlPoDisposisi() {
        return "SELECT p.id AS po_id, p.nomor_po, p.status_review,
                       COALESCE(p.disposisi_humas_at, p.disetujui_at, p.updated_at) AS tgl_disposisi,
                       p.judul_kegiatan AS po_judul,
                       p.ruang_lingkup, p.jadwal_mulai, p.jadwal_selesai,
                       o.id AS order_id, o.id_customer, o.jenis_layanan_opti, o.judul_kegiatan AS order_judul, o.estimasi_biaya,
                       c.nmcustomer, c.pt_cv,
                       (SELECT sp.nominal_penawaran FROM tb_surat_penawaran sp
                         WHERE sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                         ORDER BY sp.id DESC LIMIT 1) AS nominal_penawaran
                FROM po_kegiatan p
                JOIN order_layanan o ON o.id = p.order_id
                JOIN tb_customer c ON c.id_customer = o.id_customer
                WHERE (p.status_review = 'disetujui' OR p.disposisi_humas_at IS NOT NULL)";
    }

    protected function ambilPo($poId) {
        if ($poId <= 0) { return null; }
        try {
            $rows = $this->db->exec($this->sqlPoDisposisi() . " AND p.id = ? LIMIT 1", [1 => $poId]);
        } catch (\Exception $e) {
            return null;
        }
        return !empty($rows) ? $rows[0] : null;
    }

    protected function ambilKontrak($poId) {
        try {
            $rows = $this->db->exec("SELECT * FROM pks_kontrak WHERE po_id = ? LIMIT 1", [1 => (int) $poId]);
        } catch (\Exception $e) {
            return null;
        }
        return !empty($rows) ? $rows[0] : null;
    }

    protected function pilihJudul(array $r) {
        $j = trim((string) $r['po_judul']);
        return $j !== '' ? $j : trim((string) $r['order_judul']);
    }

    protected function pilihBiaya(array $r) {
        $b = !empty($r['nominal_penawaran']) ? (float) $r['nominal_penawaran'] : (float) $r['estimasi_biaya'];
        return (int) round($b);
    }

    /** Nilai yang diambil dari PO/database (isian "biru" di form). */
    protected function susunDefault(array $po) {
        $cust = [];
        try {
            $rows = $this->db->exec(
                "SELECT nmcustomer, pt_cv, alamatcustomer, contactperson, contactperson_opti, nama_pribadi,
                        notelpcustomer, nohpcontactperson, nohpcontactperson_opti, emailcustomer, emailcustomer_sertifikasi
                 FROM tb_customer WHERE id_customer = ? LIMIT 1",
                [1 => (int) $po['id_customer']]
            );
            if (!empty($rows)) { $cust = $rows[0]; }
        } catch (\Exception $e) {
            // dibiarkan kosong: form tetap terbuka, isian diisi manual
        }

        $alamat = trim((string) (isset($cust['alamatcustomer']) ? $cust['alamatcustomer'] : ''));

        return self::TETAP + [
            'perusahaan'       => $this->formatMitra(isset($cust['nmcustomer']) ? $cust['nmcustomer'] : $po['nmcustomer'], isset($cust['pt_cv']) ? $cust['pt_cv'] : $po['pt_cv']),
            'alamatPerusahaan' => $alamat,
            'alamatNotif'      => $alamat,
            'judul'            => $this->pilihJudul($po),
            'ruangLingkup'     => $this->ekstrakRuangLingkup($po['ruang_lingkup']),
            'mulai'            => $this->tanggalValid(substr((string) $po['jadwal_mulai'], 0, 10)),
            'selesai'          => $this->tanggalValid(substr((string) $po['jadwal_selesai'], 0, 10)),
            'biaya'            => $this->pilihBiaya($po),
            'upNama'           => $this->pertama($cust, ['contactperson_opti', 'contactperson', 'nama_pribadi']),
            'telepon'          => $this->pertama($cust, ['nohpcontactperson_opti', 'nohpcontactperson', 'notelpcustomer']),
            'email'            => $this->pertama($cust, ['emailcustomer', 'emailcustomer_sertifikasi']),
        ];
    }

    /** Isian manual dimulai kosong; sisanya salinan nilai dari PO. */
    protected function nilaiAwal(array $db) {
        return $db + [
            'nomorUrut' => '', 'nomorPihakPertama' => '', 'tanggal' => date('Y-m-d'),
            'ttdNama' => '', 'ttdJabatan' => '', 'sampel' => false, 'upJabatan' => '', 'whatsapp' => '',
        ];
    }

    protected function pertama(array $baris, array $kunci) {
        foreach ($kunci as $k) {
            if (isset($baris[$k]) && trim((string) $baris[$k]) !== '' && trim((string) $baris[$k]) !== '-') {
                return trim((string) $baris[$k]);
            }
        }
        return '';
    }

    /** po_kegiatan.ruang_lingkup: JSON {deskripsi, items} (atau teks biasa) -> satu poin per baris, tanpa penanda 1./a./- */
    protected function ekstrakRuangLingkup($mentah) {
        $rl = json_decode((string) $mentah, true);
        if (!is_array($rl)) {
            $rl = ['deskripsi' => (string) $mentah, 'items' => []];
        }
        if (isset($rl[0])) {
            $rl = ['deskripsi' => '', 'items' => $rl];
        }

        $baris = [];
        $deskripsi = isset($rl['deskripsi']) ? (string) $rl['deskripsi'] : '';
        foreach (preg_split('/\R/u', $deskripsi) as $b) {
            $b = trim((string) preg_replace('/^(\d+\.|[a-zA-Z]\.|[-\x{2022}])\s*/u', '', trim($b)));
            if ($b !== '') { $baris[] = $b; }
        }
        $items = isset($rl['items']) ? (array) $rl['items'] : [];
        foreach ($items as $it) {
            if (is_array($it)) {
                $t = '';
                foreach (['nama', 'uraian', 'teks', 'kegiatan'] as $kunci) {
                    if (isset($it[$kunci]) && trim((string) $it[$kunci]) !== '') { $t = trim((string) $it[$kunci]); break; }
                }
            } else {
                $t = trim((string) $it);
            }
            if ($t !== '') { $baris[] = $t; }
        }
        return implode("\n", $baris);
    }

    /* ============================================================
     * INPUT / DB
     * ============================================================ */

    /** Baca dan bersihkan isian dari POST. Kunci yang tidak dikirim memakai nilai kosong (atau data tetap BBSPJIS). */
    protected function bacaInput($f3) {
        $hasil = [];
        foreach (self::FIELDS as $fid => $def) {
            $mentah = $f3->get('POST.' . $fid);
            if ($mentah === null && isset(self::TETAP[$fid])) { $mentah = self::TETAP[$fid]; }
            $hasil[$fid] = $this->bersihkan($mentah, $def[1], $def[2]);
        }
        return $hasil;
    }

    protected function bersihkan($v, $tipe, $maks) {
        if (is_array($v)) { $v = ''; }
        switch ($tipe) {
            case 'bool':
                return in_array((string) $v, ['1', 'true', 'on', 'ya'], true);
            case 'uang':
                $n = (float) preg_replace('/[^0-9]/', '', (string) $v);
                return (int) min($n, 999999999999);
            case 'tgl':
                return $this->tanggalValid(trim((string) $v));
            case 'area':
                $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v);
                $s = str_replace(["\r\n", "\r"], "\n", (string) $s);
                $s = implode("\n", array_map('trim', explode("\n", (string) $s)));
                return mb_substr(trim((string) $s), 0, $maks, 'UTF-8');
            default:
                $s = preg_replace('/[\x00-\x1F]/u', ' ', (string) $v);
                $s = trim((string) preg_replace('/\s+/u', ' ', (string) $s));
                return mb_substr($s, 0, $maks, 'UTF-8');
        }
    }

    protected function tanggalValid($s) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $s, $m)) { return ''; }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? (string) $s : '';
    }

    protected function keNilaiDb($v, $tipe) {
        switch ($tipe) {
            case 'bool': return $v ? 1 : 0;
            case 'uang': return (int) $v;
            case 'tgl':  return $v === '' ? null : $v;
            default:     return (string) $v;
        }
    }

    /** Baris DB -> nilai berkunci sama dengan form. */
    protected function dariBaris(array $k) {
        $vals = [];
        foreach (self::FIELDS as $fid => $def) {
            $v = isset($k[$def[0]]) ? $k[$def[0]] : null;
            switch ($def[1]) {
                case 'bool': $vals[$fid] = !empty($v); break;
                case 'uang': $vals[$fid] = (int) round((float) $v); break;
                case 'tgl':  $vals[$fid] = $v ? substr((string) $v, 0, 10) : ''; break;
                default:     $vals[$fid] = (string) $v;
            }
        }
        return $vals;
    }

    protected function kosong($v, $tipe) {
        if ($tipe === 'uang') { return !((int) $v > 0); }
        if ($tipe === 'bool') { return false; }
        return trim((string) $v) === '';
    }

    /* ============================================================
     * LOGO
     * ============================================================ */

    protected function dirLogo() {
        return rtrim((string) $this->f3->get('ROOT'), '/\\') . '/storage/pks_logo';
    }

    protected function pathTemplate($divisi = '') {
        $d = strtolower(trim((string) $divisi));
        $berkas = isset(self::TEMPLATE[$d]) ? self::TEMPLATE[$d] : self::TEMPLATE_DEFAULT;
        return rtrim((string) $this->f3->get('ROOT'), '/\\') . '/storage/templates/' . $berkas;
    }

    /** Divisi order milik sebuah PO (untuk memilih template). */
    protected function divisiPo($poId) {
        try {
            $r = $this->db->exec("SELECT o.jenis_layanan_opti FROM po_kegiatan p JOIN order_layanan o ON o.id = p.order_id WHERE p.id = ? LIMIT 1", [1 => (int) $poId]);
            return !empty($r) ? (string) $r[0]['jenis_layanan_opti'] : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /** Validasi + simpan logo. Hanya PNG/JPG (Word tidak menampilkan WEBP/SVG dengan andal). */
    protected function simpanLogo(array $f) {
        if ((int) $f['error'] !== UPLOAD_ERR_OK) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Logo gagal diunggah (kode ' . (int) $f['error'] . '). Coba file lain.'], 422);
        }
        if ($f['size'] > self::LOGO_MAKS) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Ukuran logo maksimal 2 MB.'], 422);
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Berkas logo tidak valid.'], 422);
        }
        $info = @getimagesize($f['tmp_name']);
        if (!$info || ($info[2] !== IMAGETYPE_PNG && $info[2] !== IMAGETYPE_JPEG) || $info[0] < 1 || $info[1] < 1) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Format logo harus PNG atau JPG.'], 422);
        }

        $dir = $this->dirLogo();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Folder penyimpanan logo tidak bisa dibuat: ' . $dir], 500);
        }

        $ext = ($info[2] === IMAGETYPE_PNG) ? 'png' : 'jpg';
        $nama = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nama)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Logo gagal disimpan di server.'], 500);
        }
        return ['file' => $nama, 'mime' => ($ext === 'png') ? 'image/png' : 'image/jpeg'];
    }

    protected function hapusBerkasLogo($nama) {
        if (!$nama) { return; }
        $p = $this->dirLogo() . '/' . basename((string) $nama);
        if (is_file($p)) { @unlink($p); }
    }

    /* ============================================================
     * PENYUSUNAN DATA DOCX
     * ============================================================ */

    protected function infoTanggal($s) {
        $s = $this->tanggalValid((string) $s);
        if ($s === '') { return null; }
        return \DateTime::createFromFormat('!Y-m-d', $s);
    }

    protected function nomorPks(array $v) {
        $d = $this->infoTanggal($v['tanggal']);
        $urut = trim((string) $v['nomorUrut']);
        return 'B/' . ($urut !== '' ? $urut : '.....') . '/BBSPJIS/HK/' . ($d ? self::ROMAWI[(int) $d->format('n')] : 'Bulan') . '/' . ($d ? $d->format('Y') : 'Tahun');
    }

    /** Nilai form -> penanda template Word (lihat PksDocx). */
    protected function susunDataDocx(array $v) {
        $d = $this->infoTanggal($v['tanggal']);
        $m = $this->infoTanggal($v['mulai']);
        $s = $this->infoTanggal($v['selesai']);
        $n = ($m && $s && $s >= $m) ? ((int) $m->diff($s)->days + 1) : 0;

        $lingkup = [];
        foreach (preg_split('/\R/u', (string) $v['ruangLingkup']) as $b) {
            $b = trim($b);
            if ($b !== '') { $lingkup[] = $b; }
        }
        $atauStrip = function ($x) { $x = trim((string) $x); return $x !== '' ? $x : '-'; };

        return [
            'perusahaan'          => $v['perusahaan'],
            'judul'               => $v['judul'],
            'nomor_pihak_pertama' => $atauStrip($v['nomorPihakPertama']),
            'nomor_pks'           => $this->nomorPks($v),
            'hari'                => $d ? self::HARI[(int) $d->format('N') - 1] : '',
            'tanggal_kata'        => $d ? self::terbilang((int) $d->format('j')) : '',
            'bulan'               => $d ? self::BULAN[(int) $d->format('n') - 1] : '',
            'tahun_kata'          => $d ? self::terbilang((int) $d->format('Y')) : '',
            'tanggal_angka'       => $d ? $d->format('d-m-Y') : '',
            'ttd_nama'            => $v['ttdNama'],
            'ttd_jabatan'         => $v['ttdJabatan'],
            'alamat_perusahaan'   => $v['alamatPerusahaan'],
            'kepala_nama'         => $v['kepalaNama'],
            'kepala_jabatan'      => $v['kepalaJabatan'],
            'kepala_alamat'       => $v['kepalaAlamat'],
            'ruang_lingkup'       => $lingkup,
            'durasi'              => $n ? $n . ' (' . self::terbilang($n) . ') hari' : '',
            'mulai_tgl'           => $m ? $m->format('j') : '',
            'mulai_bulan'         => $m ? self::BULAN[(int) $m->format('n') - 1] : '',
            'mulai_tahun'         => $m ? $m->format('Y') : '',
            'selesai_tgl'         => $s ? $s->format('j') : '',
            'selesai_bulan'       => $s ? self::BULAN[(int) $s->format('n') - 1] : '',
            'selesai_tahun'       => $s ? $s->format('Y') : '',
            'biaya'               => number_format((float) $v['biaya'], 2, ',', '.'),
            'biaya_terbilang'     => self::terbilang((int) $v['biaya']),
            'sampel'              => (bool) $v['sampel'],
            'up_nama'             => $v['upNama'],
            'up_jabatan'          => trim((string) $v['upJabatan']),
            'alamat_notif'        => $v['alamatNotif'],
            'telepon'             => $v['telepon'],
            'whatsapp'            => $atauStrip($v['whatsapp']),
            'email'               => $v['email'],
            'ked_up_nama'         => $v['kedUpNama'],
            'ked_up_jabatan'      => $v['kedUpJabatan'],
            'ked_telepon'         => $v['kedTelepon'],
            'ked_wa'              => $v['kedWa'],
            'ked_email'           => $v['kedEmail'],
        ];
    }

    protected static function terbilang($n) {
        $n = (int) floor($n);
        return $n === 0 ? 'nol' : self::tb($n);
    }

    protected static function tb($x) {
        static $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($x < 12)   { return $satuan[$x]; }
        if ($x < 20)   { return self::tb($x - 10) . ' belas'; }
        if ($x < 100)  { return self::tb((int) floor($x / 10)) . ' puluh' . ($x % 10 ? ' ' . self::tb($x % 10) : ''); }
        if ($x < 200)  { return 'seratus' . ($x - 100 ? ' ' . self::tb($x - 100) : ''); }
        if ($x < 1000) { return self::tb((int) floor($x / 100)) . ' ratus' . ($x % 100 ? ' ' . self::tb($x % 100) : ''); }
        if ($x < 2000) { return 'seribu' . ($x - 1000 ? ' ' . self::tb($x - 1000) : ''); }
        if ($x < 1000000)       { return self::tb((int) floor($x / 1000)) . ' ribu' . ($x % 1000 ? ' ' . self::tb($x % 1000) : ''); }
        if ($x < 1000000000)    { return self::tb((int) floor($x / 1000000)) . ' juta' . ($x % 1000000 ? ' ' . self::tb($x % 1000000) : ''); }
        if ($x < 1000000000000) { return self::tb((int) floor($x / 1000000000)) . ' miliar' . ($x % 1000000000 ? ' ' . self::tb($x % 1000000000) : ''); }
        return self::tb((int) floor($x / 1000000000000)) . ' triliun' . ($x % 1000000000000 ? ' ' . self::tb($x % 1000000000000) : '');
    }

    /* ============================================================
     * HELPER UMUM
     * ============================================================ */

    /** Hanya peran yang diizinkan. $json = true untuk endpoint API. */
    protected function izinkan($json = false) {
        $this->requireAuth();
        $role = (string) $this->getUserRole();
        if ($this->isSuperadmin() || in_array($role, self::ROLE_BOLEH, true)) {
            return;
        }
        if ($json) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke modul kontrak.'], 403);
        }
        $this->f3->error(403, 'Modul kontrak hanya untuk Humas (sementara: Superadmin).');
        exit;
    }

    /** "PT PT Kertas X" + pt_cv "PT" -> "PT Kertas X". */
    protected function formatMitra($nama, $ptCv) {
        $bersih = trim((string) preg_replace('/^((PT|CV|UD)\.?\s+)+/i', '', (string) $nama));
        $badan  = trim((string) $ptCv);
        return ($badan !== '') ? $badan . ' ' . $bersih : $bersih;
    }

    protected function hapusAwalan($nama) {
        return trim((string) preg_replace('/^((PT|CV|UD)\.?\s+)+/i', '', (string) $nama));
    }

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}