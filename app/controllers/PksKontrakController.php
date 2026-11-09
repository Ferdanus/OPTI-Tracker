<?php
/**
 * PksKontrakController
 *
 * Kontrak (PKS) yang disusun dari PO yang sudah disetujui dan didisposisikan ke Humas.
 *   /pks                      index: daftar PO disposisi (semua divisi) (tab Perlu dibuat / Draft / Selesai) + Unduh Word (tab Selesai)
 *   /pks/@id/form            form + pratinjau live (@id = id PO)
 *   POST /pks/@id/simpan  simpan draft            (multipart: field form + logo + hapus_logo)
 *   POST /pks/@id/selesai validasi lengkap lalu tandai selesai
 *   /pks/@id/unduh           unduh .docx dari template (hanya kontrak selesai)
 *   /pks/@id/pdf             unduh PDF (Word -> PDF lewat LibreOffice bila terpasang; kalau tidak, 501 dan halaman memakai dialog cetak)
 *   /pks/@id/logo            logo klien (butuh login)
 *
 * Nomor PKS "B/<urut>/BBSPJIS/HK/<romawi>/<tahun>" OTOMATIS dari tabel pks_nomor_urut (nomorBaru()), sama pola dengan
 * nomor PO (po_nomor_urut): satu deret per TAHUN (reset ke 1 saat ganti tahun), terbit sekali saat kontrak pertama kali
 * diselesaikan dan tidak berubah lagi. Tanggal perjanjian juga otomatis (hari kontrak diselesaikan); bulan dan tahun nomor mengikuti tanggal itu.
 *
 * Akses: Superadmin, Humas, dan Admin PKS (lihat ROLE_BOLEH).
 * Kompatibel PHP 7.2. Butuh: migration_pks_kontrak.sql, PksDocx.php, storage/templates/pks_selulosa_template.docx
 */
class PksKontrakController extends Controller {

    const ROLE_BOLEH = ['superadmin', 'humas', 'admin_kontrak'];
    /** Template Word per divisi (di storage/templates/). Lingkungan sementara memakai template yang sama; ganti nama file kalau sudah ada. */
    const TEMPLATE = [
        'selulosa'   => 'pks_selulosa_template.docx',
        'lingkungan' => 'pks_selulosa_template.docx',
    ];
    const TEMPLATE_DEFAULT = 'pks_selulosa_template.docx';
    const LOGO_MAKS  = 2097152;           // 2 MB

    /** Data BBSPJIS (Pihak Kedua): nilai awal; disimpan per kontrak dan boleh diubah di form. */
    const TETAP = [
        // kepalaNama + kepalaJabatan TIDAK di sini: diambil dari master penandatangan (daftarPejabat()), dipilih di form.
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
        // Nomor urut dibuat otomatis oleh sistem saat kontrak diselesaikan (nomorBaru()), bukan diisi pengguna.
        'nomorUrut'         => ['nomor_urut',          'teks', 30,   false, 'Nomor urut PKS'],
        'nomorPihakPertama' => ['nomor_pihak_pertama', 'teks', 120,  false, 'Nomor perjanjian versi klien'],
        'tanggal'           => ['tanggal_perjanjian',  'tgl',  0,    true,  'Tanggal perjanjian'],
        'perusahaan'        => ['perusahaan',          'teks', 255,  true,  'Nama perusahaan'],
        'alamatPerusahaan'  => ['alamat_perusahaan',   'rich', 1000, true,  'Alamat perusahaan'],
        'ttdNama'           => ['ttd_nama',            'teks', 150,  true,  'Nama penandatangan'],
        'ttdJabatan'        => ['ttd_jabatan',         'teks', 150,  true,  'Jabatan penandatangan'],
        'judul'             => ['judul',               'rich', 1000, true,  'Judul pekerjaan'],
        'ruangLingkup'      => ['ruang_lingkup',       'rich', 6000, true,  'Ruang lingkup'],
        'mulai'             => ['tgl_mulai',           'tgl',  0,    true,  'Tanggal mulai'],
        'selesai'           => ['tgl_selesai',         'tgl',  0,    true,  'Tanggal selesai'],
        'biaya'             => ['biaya',               'uang', 0,    true,  'Nilai kontrak'],
        'sampel'            => ['sampel',              'bool', 0,    false, 'Klien menyiapkan sampel'],
        'upNama'            => ['up_nama',             'teks', 150,  true,  'UP (nama kontak)'],
        'upJabatan'         => ['up_jabatan',          'teks', 150,  false, 'Jabatan kontak'],
        'alamatNotif'       => ['alamat_notif',        'rich', 1000, true,  'Alamat pemberitahuan'],
        'telepon'           => ['telepon',             'teks', 60,   true,  'Telepon'],
        'whatsapp'          => ['whatsapp',            'teks', 60,   false, 'Whats App'],
        'email'             => ['email',               'teks', 150,  false, 'Email'],
        'kepalaNama'        => ['kepala_nama',         'teks', 150,  true,  'Nama Kepala BBSPJIS'],
        'kepalaJabatan'     => ['kepala_jabatan',      'teks', 150,  true,  'Jabatan Kepala BBSPJIS'],
        'kepalaAlamat'      => ['kepala_alamat',       'rich', 1000, true,  'Alamat BBSPJIS'],
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

    /** GET /pks */
    public function index($f3) {
        $this->izinkan();

        $errorMessage = null;
        $rows = [];
        $kontrak = [];
        try {
            $rows = $this->db->exec($this->sqlPoDisposisi() . " ORDER BY COALESCE(p.disposisi_humas_at, p.disetujui_at, p.updated_at) DESC");
            try { \PksMitraController::pastikanKolom($this->db); } catch (\Throwable $e) {}   // kolom PKS bertanda tangan (menu PKS Mitra)
            foreach ($this->db->exec("SELECT * FROM pks_kontrak") as $k) {
                $kontrak[(int) $k['po_id']] = $k;
            }
        } catch (\Exception $e) {
            $errorMessage = 'Gagal memuat daftar PKS: ' . $e->getMessage() . ' (sudah menjalankan migration_pks_kontrak.sql?)';
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

            $ekstra = ['selesai_at' => '', 'file_word' => '', 'logo_url' => '', 'logo_kb' => 0, 'pks_ttd_lacak' => false, 'pks_ttd_at' => '', 'url_pks_ttd' => ''];
            if ($k && $k['status'] === 'selesai') {
                // PKS bertanda tangan klien (di-upload Tim Mitra). Kolomnya dibuat otomatis oleh PksMitraController::pastikanKolom.
                if (array_key_exists('pks_ttd_file', $k)) {
                    $ekstra['pks_ttd_lacak'] = true;
                    if ((string) $k['pks_ttd_file'] !== '') {
                        $ekstra['pks_ttd_at'] = (string) $k['pks_ttd_at'];
                        $ekstra['url_pks_ttd'] = $f3->get('BASE') . '/pks-mitra/' . $id . '/ttd';
                    }
                }
                $ekstra['selesai_at'] = substr((string) $k['selesai_at'], 0, 10);
                $ekstra['file_word'] = $this->namaBerkas($k, $vals, 'docx');
                if (!empty($k['logo_file'])) {
                    $pl = $this->dirLogo() . '/' . basename((string) $k['logo_file']);
                    if (is_file($pl)) {
                        $ekstra['logo_url'] = $f3->get('BASE') . '/pks/' . $id . '/logo?v=' . (int) strtotime((string) $k['updated_at']);
                        $ekstra['logo_kb'] = max(1, (int) round(filesize($pl) / 1024));
                    }
                }
            }

            $daftar[] = [
                'id'        => $id,
                'nomor'     => (string) $r['nomor_po'],
                'divisi'    => ucwords(str_replace('_', ' ', (string) $r['jenis_layanan_opti'])),
                'layanan'   => (string) $r['jenis_layanan_opti'],
                'mitra'     => $this->formatMitra($r['nmcustomer'], $r['pt_cv']),
                'judul'     => $this->pilihJudul($r),
                'biaya'     => $this->pilihBiaya($r),
                'disposisi' => substr((string) $r['tgl_disposisi'], 0, 10),
                'status'    => $status,
                'nomor_pks' => $k ? (string) $k['nomor_pks'] : '',
                'terisi'    => $terisi,
                'total'     => $total,
                'baca_lacak' => $this->kolomBaca(),
                'baca_at'   => $this->bacaValid($r),
                'order_id'  => (int) ($r['order_id'] ?? 0),
                'url_detail' => !empty($r['order_id']) ? ($f3->get('BASE') . '/order/' . (int) $r['order_id']) : '',
                'url_po'    => !empty($r['po_ttd_file']) ? ($f3->get('BASE') . '/pks/' . $id . '/po-ttd') : '',
                'riwayat_po' => self::riwayatPo($r),
                'url_dok'   => $f3->get('BASE') . '/po-kegiatan/' . $id . '/cetak',
                'url_dok_unduh' => $f3->get('BASE') . '/po-kegiatan/' . $id . '/unduh',
                'info_ttd'  => self::infoTtd($r),
            ] + $ekstra;
        }

        $tab = (string) $f3->get('GET.tab');
        $pesan = (string) $f3->get('GET.pesan');
        $f3->set('daftar_kontrak_json', json_encode($daftar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('tab_awal_json', json_encode(in_array($tab, ['baru', 'draft', 'selesai'], true) ? $tab : 'baru'));
        $f3->set('pesan_awal_json', json_encode($pesan === 'selesai' ? 'PKS ditandai selesai. Klik Lihat untuk membuka, mengunduh, atau mencetaknya.' : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('error_message', $errorMessage);

        $this->render('kontrak/index.html', 'Perjanjian Kerja Sama (PKS)', 'kontrak');
    }

    /** GET /pks/@id/form */
    public function form($f3, $params) {
        $poId = (int) $params['id'];
        $this->izinkanBaca($poId, true);
        $po = $this->ambilPo($poId);
        if (!$po) {
            $f3->error(404, 'PO tidak ditemukan, atau belum disetujui dan didisposisikan ke Humas.');
            return;
        }

        // Humas membuka kontrak ini: catat "sudah dibuka" (miniatur halaman 1 di detail order tidak dihitung)
        if ((string) $f3->get('GET.lihat') !== '1') { $this->catatDibuka($poId); }

        $k = $this->ambilKontrak($poId);
        $pejabat = $this->daftarPejabat();
        // UP Pihak Kedua = pembuat kontrak (kontrak yang sudah ada: pembuat aslinya; kontrak baru: yang sedang login)
        $uidPembuat = ($k && !empty($k['created_by'])) ? (int) $k['created_by'] : (int) $this->getUserId();
        $db = $this->susunDefault($po, $pejabat, $uidPembuat);
        $vals = $k ? $this->dariBaris($k) : $this->nilaiAwal($db);
        $vals['tanggal'] = $this->tanggalOtomatis($k);   // otomatis: hari ini, kecuali sudah terkunci saat nomor terbit

        $data = [
            'po' => [
                'id'        => $poId,
                'order_id'  => (int) $po['order_id'],
                'nomor'     => (string) $po['nomor_po'],
                'divisi'    => ucfirst((string) $po['jenis_layanan_opti']),
                'disposisi' => substr((string) $po['tgl_disposisi'], 0, 10),
                'mitra'     => $db['perusahaan'],
            ],
            'db'      => $db,
            'pejabat' => $pejabat,
            'humas'   => $this->daftarHumas(),
            'vals'    => $vals,
            'status'  => $k ? $k['status'] : 'baru',
            'nomorPks' => $this->nomorTerbit($k),
            'logoUrl' => ($k && !empty($k['logo_file'])) ? $f3->get('BASE') . '/pks/' . $poId . '/logo?v=' . (int) strtotime((string) $k['updated_at']) : '',
        ];

        $f3->set('kontrak_json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $this->render('kontrak/form.html', 'PKS untuk ' . $po['nomor_po'], 'kontrak');
    }

    /* ============================================================
     * API
     * ============================================================ */

    /** POST /pks/@id/simpan */
    public function simpan($f3, $params) {
        $this->prosesSimpan($f3, (int) $params['id'], false);
    }

    /** POST /pks/@id/selesai */
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

        // Tanggal perjanjian OTOMATIS (bukan input): hari kontrak diselesaikan/disimpan; kalau nomor sudah terbit, tanggalnya ikut terkunci.
        $vals['tanggal'] = $this->tanggalOtomatis($lama);

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
        // ---- nomor PKS ----
        // Sudah pernah terbit -> tetap (tidak berubah walau tanggal diubah). Belum terbit -> terbit hanya saat Selesai.
        $nomorTerbit = $this->nomorTerbit($lama);
        $idNomorBaru = 0;
        $idNomorLama = ($lama && !empty($lama['id_nomor_urut'])) ? (int) $lama['id_nomor_urut'] : null;
        if ($nomorTerbit !== '') {
            $vals['nomorUrut'] = (string) $lama['nomor_urut'];
            $nomorPks = $nomorTerbit;
        } elseif ($final) {
            try {
                $t = $this->nomorBaru($vals['tanggal']);
            } catch (\Exception $e) {
                if ($adaBerkas) { $this->hapusBerkasLogo($logoFile); }
                $this->keluarkanJson(['ok' => false, 'pesan' => $e->getMessage()], 500);
            }
            $idNomorBaru = $t['id'];
            $idNomorLama = $t['id'];
            $vals['nomorUrut'] = (string) $t['nomor_urut'];
            $nomorPks = $t['detail_nomor'];
        } else {
            $vals['nomorUrut'] = '';
            $nomorPks = $this->nomorPks($vals);   // draft: bagian urut masih titik-titik
        }

        $kolom = [];
        $nilai = [];
        foreach (self::FIELDS as $fid => $def) {
            $kolom[] = $def[0];
            $nilai[] = $this->keNilaiDb($vals[$fid], $def[1]);
        }
        $kolom = array_merge($kolom, ['status', 'nomor_pks', 'id_nomor_urut', 'logo_file', 'logo_mime', 'updated_by', 'selesai_at']);
        $nilai = array_merge($nilai, [$status, $nomorPks, $idNomorLama, $logoFile, $logoMime, $uid, $final ? $sekarang : null]);

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
            if ($idNomorBaru) { $this->lepasNomor($idNomorBaru); }   // nomor yang baru terbit dilepas lagi (tidak boleh bolong)
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan PKS. Detail: ' . $e->getMessage()], 500);
        }

        if ($hapusLama) { $this->hapusBerkasLogo($hapusLama); }

        // Alur order (Riwayat Aktivitas di Detail Order): hanya saat kontrak diselesaikan, atau diselesaikan ulang setelah diperbarui.
        if ($final) {
            $this->catatAlur($po, ($lama && $lama['status'] === 'selesai') ? 'perbarui_kontrak' : 'selesai_kontrak', $nomorPks);
            $this->tandaiKontrakDiPo($po, $poId, $nomorPks);
        }

        $this->keluarkanJson([
            'ok'        => true,
            'status'    => $status,
            'nomor_pks' => $nomorPks,
            'nomor_terbit' => ($nomorTerbit !== '' || $idNomorBaru > 0),
            'logoUrl'   => $logoFile ? $f3->get('BASE') . '/pks/' . $poId . '/logo?v=' . time() : '',
        ]);
    }

    /** GET /pks/@id/unduh */
    public function unduh($f3, $params) {
        $poId = (int) $params['id'];
        $this->izinkanBaca($poId, false, true);
        $k = $this->ambilKontrak($poId);
        if (!$k || $k['status'] !== 'selesai') {
            $f3->error(404, 'PKS belum berstatus selesai, jadi belum bisa diunduh.');
            return;
        }

        $vals = $this->dariBaris($k);

        try {
            $isi = $this->isiDocx($poId, $k, $vals);
        } catch (\Exception $e) {
            $f3->error(500, 'Gagal membuat dokumen Word: ' . $e->getMessage());
            return;
        }

        $nama = $this->namaBerkas($k, $vals, 'docx');

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $nama . '"');
        header('Content-Length: ' . strlen($isi));
        header('Cache-Control: private, no-store, max-age=0');
        echo $isi;
        exit;
    }

    /** GET /pks/@id/pdf : Word -> PDF lewat LibreOffice (kalau terpasang). Kalau tidak ada, balas 501 supaya halaman memakai dialog cetak browser. */
    public function pdf($f3, $params) {
        $poId = (int) $params['id'];
        $this->izinkanBaca($poId, false, true);
        $k = $this->ambilKontrak($poId);
        if (!$k || $k['status'] !== 'selesai') {
            $f3->error(404, 'PKS belum berstatus selesai, jadi belum bisa diunduh.');
            return;
        }

        $soffice = $this->cariSoffice();
        if ($soffice === '') {
            $this->keluarkanJson(['ok' => false, 'kode' => 'tanpa_pdf', 'pesan' => 'LibreOffice belum terpasang di server ini.'], 501);
        }

        $vals = $this->dariBaris($k);
        try {
            $isi = $this->isiDocx($poId, $k, $vals);
        } catch (\Exception $e) {
            $f3->error(500, 'Gagal membuat dokumen: ' . $e->getMessage());
            return;
        }

        $tmp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'pks_' . bin2hex(random_bytes(6));
        if (!@mkdir($tmp, 0700, true)) {
            $f3->error(500, 'Folder sementara untuk PDF tidak bisa dibuat.');
            return;
        }
        $in = $tmp . DIRECTORY_SEPARATOR . 'pks.docx';
        file_put_contents($in, $isi);
        $profil = 'file:///' . ltrim(str_replace('\\', '/', $tmp), '/') . '/profil';

        $cmd = escapeshellarg($soffice) . ' --headless --norestore --nologo ' . escapeshellarg('-env:UserInstallation=' . $profil)
             . ' --convert-to pdf --outdir ' . escapeshellarg($tmp) . ' ' . escapeshellarg($in);
        $pipa = [];
        $proses = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipa);
        $ok = false;
        if (is_resource($proses)) {
            $batas = time() + 90;
            while (true) {
                $st = proc_get_status($proses);
                if (!$st['running']) { break; }
                if (time() > $batas) { proc_terminate($proses); break; }
                usleep(200000);
            }
            foreach ($pipa as $p) { if (is_resource($p)) { fclose($p); } }
            proc_close($proses);
            $ok = is_file($tmp . DIRECTORY_SEPARATOR . 'pks.pdf') && filesize($tmp . DIRECTORY_SEPARATOR . 'pks.pdf') > 0;
        }
        $pdf = $ok ? file_get_contents($tmp . DIRECTORY_SEPARATOR . 'pks.pdf') : '';
        $this->hapusFolder($tmp);

        if ($pdf === '' || $pdf === false) {
            $f3->error(500, 'Konversi ke PDF gagal. Cek pemasangan LibreOffice dan izin folder sementara.');
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $this->namaBerkas($k, $vals, 'pdf') . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store, max-age=0');
        echo $pdf;
        exit;
    }

    /** Lokasi soffice: SOFFICE_PATH (config.ini / env), lalu lokasi umum Windows/Linux. '' = tidak ada. */
    protected function cariSoffice() {
        $kandidat = [];
        $cfg = $this->f3->get('SOFFICE_PATH');
        if (is_string($cfg) && $cfg !== '') { $kandidat[] = $cfg; }
        $env = getenv('SOFFICE_PATH');
        if (is_string($env) && $env !== '') { $kandidat[] = $env; }
        foreach (['C:\\Program Files\\LibreOffice\\program\\soffice.exe', 'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                  '/usr/bin/soffice', '/usr/bin/libreoffice', '/usr/local/bin/soffice', '/opt/libreoffice/program/soffice',
                  '/Applications/LibreOffice.app/Contents/MacOS/soffice'] as $p) { $kandidat[] = $p; }
        foreach ($kandidat as $p) { if (is_file($p)) { return $p; } }
        return '';
    }

    protected function hapusFolder($dir) {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_dir($p)) { $this->hapusFolder($p); } else { @unlink($p); }
        }
        @rmdir($dir);
    }

    /** Isi .docx dari data kontrak (dipakai unduh Word dan PDF). Melempar \Exception kalau template/logo bermasalah. */
    protected function isiDocx($poId, array $k, array $vals) {
        $logoPath = null;
        if (!empty($k['logo_file'])) {
            $p = $this->dirLogo() . '/' . basename((string) $k['logo_file']);
            if (is_file($p)) { $logoPath = $p; }
        }
        return PksDocx::buat($this->pathTemplate($this->divisiPo($poId)), $this->susunDataDocx($vals, $this->nomorTerbit($k)), $logoPath);
    }

    /** Nama berkas unduhan: PKS_<nomor>_<perusahaan>.<ext>, atau <awal>_<perusahaan>.<ext> untuk berkas lain (mis. Logo). */
    protected function namaBerkas($k, array $vals, $ext, $awal = 'PKS') {
        $per = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $this->hapusAwalan(isset($vals['perusahaan']) ? $vals['perusahaan'] : '')), '-');
        if ($awal === 'PKS') {
            return 'PKS_' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $k['nomor_pks']), '-') . '_' . $per . '.' . $ext;
        }
        return $awal . '_' . $per . '.' . $ext;
    }

    /** Catat ke Riwayat Aktivitas order (alur di Detail Order). Gagal mencatat tidak boleh menggagalkan penyimpanan kontrak. */
    protected function catatAlur($po, $aksi, $nomorPks) {
        $orderId = (int) (isset($po['order_id']) ? $po['order_id'] : 0);
        if ($orderId <= 0) { return; }
        $teks = ($aksi === 'perbarui_kontrak' ? 'Memperbarui' : 'Menyelesaikan') . ' Perjanjian Kerja Sama (PKS) No. ' . $nomorPks;
        if (!empty($po['nomor_po'])) { $teks .= ' untuk PO ' . $po['nomor_po']; }
        try {
            $this->logActivity($orderId, 'kontrak_pks', $aksi, $teks);
        } catch (\Throwable $e) {
            // abaikan
        }
    }

    /**
     * Kontrak selesai -> tandai di PO (po_kegiatan.kontrak_dibuat_at) supaya Tahap 9 (BAST) di Detail Order terbuka.
     * Sama dengan tombol "Tandai kontrak dibuat" di Daftar PO Humas. Hanya menandai sekali; kontrak yang dibuka lagi hanya memperbarui nomornya.
     * Gagal menandai tidak boleh menggagalkan penyimpanan kontrak.
     */
    protected function tandaiKontrakDiPo($po, $poId, $nomorPks) {
        try {
            $r = $this->db->exec("SELECT kontrak_dibuat_at, kontrak_nomor FROM po_kegiatan WHERE id = ? LIMIT 1", [1 => (int) $poId]);
            if (empty($r)) { return; }
            $uid = (int) $this->getUserId();
            $unama = isset($_SESSION['nama_lengkap']) ? (string) $_SESSION['nama_lengkap'] : 'Humas';
            if (empty($r[0]['kontrak_dibuat_at'])) {
                $this->db->exec(
                    "UPDATE po_kegiatan SET kontrak_nomor = ?, kontrak_dibuat_at = NOW(), kontrak_dibuat_oleh_id = ?, kontrak_dibuat_oleh_nama = ? WHERE id = ? AND kontrak_dibuat_at IS NULL",
                    [1 => (string) $nomorPks, 2 => $uid, 3 => $unama, 4 => (int) $poId]
                );
                try {
                    \PoReviewController::catatRiwayatOrder($this->db, (int) $poId, $uid, 'Humas membuat PKS (' . $nomorPks . '). Tahap 9 (BAST) terbuka.', 'kontrak_humas');
                    $o = $this->db->exec("SELECT nomor_order, ketua_pelaksana_id, jenis_layanan_opti FROM order_layanan WHERE id = ? LIMIT 1", [1 => (int) $po['order_id']]);
                    $o = !empty($o) ? $o[0] : [];
                    $div = (string) (isset($o['jenis_layanan_opti']) ? $o['jenis_layanan_opti'] : '');
                    $katim = \ArsipUser::getKatimMap($this->db, ['selulosa', 'lingkungan']);
                    $penerima = [isset($katim[$div]) ? (int) $katim[$div] : 0, (int) (isset($o['ketua_pelaksana_id']) ? $o['ketua_pelaksana_id'] : 0)];
                    \PoReviewController::kabari(
                        $this->db, $penerima, 'PKS Sudah Dibuat',
                        'Humas sudah membuat PKS untuk PO ' . $po['nomor_po'] . ' (Order #' . (isset($o['nomor_order']) ? $o['nomor_order'] : '-') . '). Tahap 9 (BAST) sudah terbuka.',
                        '/order/' . (int) $po['order_id']
                    );
                } catch (\Throwable $e) {
                    // riwayat dan notifikasi bersifat pelengkap
                }
                // Tim Mitra: ada PKS baru untuk dicetak, diserahkan ke klien, lalu di-upload setelah ditandatangani (menu PKS Mitra)
                \PksMitraController::kabariMitraPksBaru($this->db, ['order_id' => (int) $po['order_id'], 'po_id' => (int) $poId, 'nomor_po' => isset($po['nomor_po']) ? $po['nomor_po'] : ''], $nomorPks, $unama);
            } elseif ((string) $r[0]['kontrak_nomor'] !== (string) $nomorPks) {
                $this->db->exec("UPDATE po_kegiatan SET kontrak_nomor = ? WHERE id = ?", [1 => (string) $nomorPks, 2 => (int) $poId]);
            }
        } catch (\Throwable $e) {
            // abaikan: kolom kontrak di PO belum ada, atau PO tidak ditemukan
        }
    }

    /** GET /pks/@id/logo */
    public function logo($f3, $params) {
        $this->izinkanBaca((int) $params['id'], false, (string) $f3->get('GET.unduh') === '1');   // tampil: semua user; ?unduh=1: hanya yang boleh mengunduh

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
        if ((string) $f3->get('GET.unduh') === '1') {
            $ext = (strpos((string) $k['logo_mime'], 'jpeg') !== false) ? 'jpg' : 'png';
            header('Content-Disposition: attachment; filename="' . $this->namaBerkas($k, $this->dariBaris($k), $ext, 'Logo') . '"');
        }
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
                       " . ($this->kolomBaca() ? "p.po_ttd_at, p.po_ttd_file, p.po_ttd_asal, p.po_ttd_oleh_nama, p.validasi_keuangan_at, p.kontrak_dibuat_at, p.kontrak_dibuat_oleh_nama, p.kontrak_dibaca_at, p.kontrak_dibaca_oleh_nama," : "") . "
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

    /**
     * Riwayat PO (slide "Lihat PO"): urut waktu naik. $r = baris po_kegiatan (kolom yang tidak ada dilewati).
     * @return array [['judul','waktu','oleh','ikon'], ...]
     */
    /** Keterangan singkat PO bertanda tangan untuk slide "Lihat PO": "Di-upload Ketua Tim, 8 Nov 2026 18:50 · Foto JPG diubah jadi PDF". '' bila belum di-upload. */
    public static function infoTtd(array $r) {
        $v = trim((string) ($r['po_ttd_at'] ?? ''));
        if (empty($r['po_ttd_file']) || $v === '' || strpos($v, '0000') === 0 || !strtotime($v)) { return ''; }
        $oleh = trim((string) ($r['po_ttd_oleh_nama'] ?? ''));
        $t = 'Di-upload ' . ($oleh !== '' ? $oleh . ', ' : '') . date('d M Y, H:i', strtotime($v));
        $asal = trim((string) ($r['po_ttd_asal'] ?? ''));
        if ($asal !== '') { $t .= ' · ' . $asal . (preg_match('/^\d+\s*foto/i', $asal) ? ' digabung jadi PDF' : ' diubah jadi PDF'); }
        return $t;
    }

    public static function riwayatPo(array $r) {
        $tgl = function ($v) {
            $v = trim((string) $v);
            if ($v === '' || strpos($v, '0000') === 0 || !strtotime($v)) { return array('', 0); }
            return array(date('d M Y, H:i', strtotime($v)), strtotime($v));
        };
        $rows = array();
        $tambah = function ($judul, $kolom, $oleh, $ikon) use (&$rows, $tgl, $r) {
            if (!isset($r[$kolom])) { return; }
            list($w, $ts) = $tgl($r[$kolom]);
            if ($w === '') { return; }
            $rows[] = array('ts' => $ts, 'judul' => $judul, 'waktu' => $w, 'oleh' => $oleh, 'ikon' => $ikon);
        };
        $tambah('PO disetujui PPK BLU', 'validasi_keuangan_at', '', 'bi-patch-check');
        $tambah('PO bertanda tangan di-upload', 'po_ttd_at', (string) ($r['po_ttd_oleh_nama'] ?? ''), 'bi-pen');
        // dibuka sebelum PO bertanda tangan di-upload tidak dihitung
        if (!empty($r['kontrak_dibaca_at']) && (empty($r['po_ttd_at']) || strtotime((string) $r['kontrak_dibaca_at']) >= strtotime((string) $r['po_ttd_at']))) {
            $tambah('Dibuka Humas', 'kontrak_dibaca_at', (string) ($r['kontrak_dibaca_oleh_nama'] ?? ''), 'bi-envelope-open');
        }
        $tambah('PKS dibuat', 'kontrak_dibuat_at', (string) ($r['kontrak_dibuat_oleh_nama'] ?? ''), 'bi-file-earmark-check');
        usort($rows, function ($a, $b) { return $a['ts'] <=> $b['ts']; });
        foreach ($rows as &$x) { unset($x['ts']); }
        unset($x);
        return $rows;
    }

    /** GET /pks/@id/po-ttd -- PO bertanda tangan untuk slide "Lihat PO" (?unduh=1 untuk mengunduh). Hak akses sama dengan modul Kontrak. */
    public function poTtd($f3, $params) {
        $this->izinkan();
        \PoSchema::kirimPoTtd($f3, $this->db, (int) $params['id']);
    }

    /**
     * Progres pengisian kontrak untuk satu PO (dipakai panel "Menunggu Humas membuat kontrak" di detail order).
     * Persen = kolom wajib yang sudah terisi / seluruh kolom wajib. Belum ada draft = 0%.
     * @return array ['persen'=>int, 'terisi'=>int, 'total'=>int, 'draft'=>bool]
     */
    public static function progresPengisian($db, $poId) {
        $total = 0; $terisi = 0; $draft = false;
        try {
            $rows = $db->exec("SELECT * FROM pks_kontrak WHERE po_id = ? LIMIT 1", [1 => (int) $poId]);
            $k = !empty($rows) ? $rows[0] : null;
            foreach (self::FIELDS as $fid => $def) {
                if (!$def[3]) { continue; }
                $total++;
                if (!$k) { continue; }
                $v = isset($k[$def[0]]) ? $k[$def[0]] : null;
                if ($def[1] === 'bool') { $isi = true; }
                elseif ($def[1] === 'uang') { $isi = ((int) round((float) $v) > 0); }
                else { $isi = (trim((string) $v) !== ''); }
                if ($isi) { $terisi++; }
            }
            $draft = ($k !== null);
        } catch (\Throwable $e) {
            // tabel belum ada / gagal: anggap belum mulai
        }
        return array('persen' => $total > 0 ? (int) round($terisi * 100 / $total) : 0, 'terisi' => $terisi, 'total' => $total, 'draft' => $draft);
    }

    /** true bila kolom penanda dibuka (po_kegiatan.kontrak_dibaca_at dst.) sudah ada. Dibuat otomatis lewat PoSchema. */
    protected function kolomBaca() {
        static $ada = null;
        if ($ada === null) {
            $ada = false;
            try { \PoSchema::pastikan($this->db); } catch (\Throwable $e) {}
            try {
                $k = [];
                foreach ($this->db->exec('SHOW COLUMNS FROM po_kegiatan') as $c) { $k[$c['Field']] = true; }
                $ada = isset($k['kontrak_dibaca_at']) && isset($k['kontrak_dibaca_oleh_nama']) && isset($k['po_ttd_at']);
            } catch (\Throwable $e) { $ada = false; }
        }
        return $ada;
    }

    /** Waktu Humas pertama kali membuka antrean kontrak ini. Dibuka sebelum PO bertanda tangan di-upload tidak dihitung. Kosong = belum dibuka. */
    protected function bacaValid(array $r) {
        $b = isset($r['kontrak_dibaca_at']) ? trim((string) $r['kontrak_dibaca_at']) : '';
        if ($b === '' || strpos($b, '0000') === 0 || !strtotime($b)) { return ''; }
        $t = isset($r['po_ttd_at']) ? trim((string) $r['po_ttd_at']) : '';
        if ($t !== '' && strtotime($t) && strtotime($b) < strtotime($t)) { return ''; }
        return date('Y-m-d H:i:s', strtotime($b));
    }

    /** Catat Humas membuka form kontrak (sekali; dibuka lagi setelah PO diunggah ulang dihitung baru). Gagal mencatat tidak boleh menggagalkan halaman. */
    protected function catatDibuka($poId) {
        if (!$this->kolomBaca()) { return; }
        try {
            $nama = isset($_SESSION['nama_lengkap']) ? (string) $_SESSION['nama_lengkap'] : 'Humas';
            $this->db->exec(
                "UPDATE po_kegiatan SET kontrak_dibaca_at = NOW(), kontrak_dibaca_oleh_nama = ?
                  WHERE id = ? AND (kontrak_dibaca_at IS NULL OR (po_ttd_at IS NOT NULL AND kontrak_dibaca_at < po_ttd_at))",
                [1 => $nama, 2 => (int) $poId]
            );
        } catch (\Throwable $e) {
            // abaikan
        }
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
    protected function susunDefault(array $po, array $pejabat = [], $uidPembuat = 0) {
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

        $hasil = self::TETAP + [
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

        // Kepala (Pihak Kedua): dari master penandatangan; kalau tidak ada yang cocok dibiarkan kosong (wajib dipilih di form)
        $kepala = $this->pilihKepala($pejabat);
        $hasil['kepalaNama']    = $kepala['nama'];
        $hasil['kepalaJabatan'] = $kepala['jabatan'];

        // UP Pihak Kedua: nama dan nomor HP pembuat kontrak (jabatan, telepon kantor, email tetap, bisa diubah di form)
        $pembuat = $this->dataPembuat((int) $uidPembuat);
        if ($pembuat['nama'] !== '') { $hasil['kedUpNama'] = $pembuat['nama']; }
        if ($pembuat['wa'] !== '')   { $hasil['kedWa'] = $pembuat['wa']; }

        return $hasil;
    }

    /**
     * Pejabat dari master penandatangan (mst_penandatangan_lhu di database sekretariat), HANYA yang showhide = 'show'.
     * Kosong kalau master tidak terbaca / tidak ada yang 'show' -> form meminta Kepala diisi manual.
     * @return array<int, array{id:int, nama:string, jabatan:string}>
     */
    protected function daftarPejabat() {
        try {
            $sumber = $this->dbSekretariat ?: $this->db;
            $rows = $sumber->exec("SELECT id_penandatangan, jabatan, nama FROM mst_penandatangan_lhu WHERE showhide = 'show' ORDER BY id_penandatangan DESC");
        } catch (\Throwable $e) {
            return [];
        }
        $hasil = [];
        $lihat = [];
        foreach ($rows as $r) {
            $nama = trim(preg_replace('/\s+/', ' ', strip_tags((string) $r['nama'])));
            $jab = preg_replace('~<br\s*/?>~i', ' ', str_ireplace('&nbsp;', ' ', (string) $r['jabatan']));
            $jab = trim(preg_replace('/\s+/', ' ', strip_tags($jab)), " \t\n\r\0\x0B,");
            $jab = trim(preg_replace('/^a\.?\s?n\.?\s+/i', '', $jab));   // buang "a.n."
            if ($nama === '' || $jab === '') { continue; }
            $kunci = strtolower($nama . '|' . $jab);
            if (isset($lihat[$kunci])) { continue; }
            $lihat[$kunci] = true;
            $hasil[] = ['id' => (int) $r['id_penandatangan'], 'nama' => $nama, 'jabatan' => $jab];
        }
        return $hasil;
    }

    /** Kepala balai dari daftar pejabat ("Kepala", boleh berawalan Plh./Plt.). Yang terbaru dipilih; tidak ada -> kosong. */
    protected function pilihKepala(array $pejabat) {
        foreach ($pejabat as $p) {
            if (preg_match('/^(?:p\.?l\.?[ht]\.?\s*)?kepala(?:\s+balai.*)?$/i', $p['jabatan'])) {
                return ['nama' => $p['nama'], 'jabatan' => $p['jabatan']];
            }
        }
        return ['nama' => '', 'jabatan' => ''];
    }

    /** Nama dan nomor HP (format 08xx) akun pembuat kontrak. */
    protected function dataPembuat($uid) {
        $hasil = ['nama' => '', 'wa' => ''];
        if ($uid > 0) {
            try {
                $sumber = $this->dbSekretariat ?: $this->db;
                $r = $sumber->exec('SELECT nama_user, no_hp FROM tb_arsipuser WHERE id_user = ? LIMIT 1', [1 => $uid]);
                if (!empty($r)) {
                    $hasil['nama'] = trim((string) $r[0]['nama_user']);
                    $hp = preg_replace('/\D+/', '', (string) $r[0]['no_hp']);
                    if ($hp !== '') {
                        if (substr($hp, 0, 2) === '62') { $hp = '0' . substr($hp, 2); }
                        elseif ($hp[0] === '8') { $hp = '0' . $hp; }
                        $hasil['wa'] = $hp;
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        if ($hasil['nama'] === '' && $uid === (int) $this->getUserId()) {
            $hasil['nama'] = trim((string) (isset($_SESSION['nama_user']) ? $_SESSION['nama_user'] : ''));
        }
        return $hasil;
    }

    /** Nomor HP -> format 08xx ('' kalau kosong). */
    protected function normalHp($hp) {
        $hp = preg_replace('/\D+/', '', (string) $hp);
        if ($hp === '') { return ''; }
        if (substr($hp, 0, 2) === '62') { return '0' . substr($hp, 2); }
        if ($hp[0] === '8') { return '0' . $hp; }
        return $hp;
    }

    /** Anggota Tim Humas (akun aktif berperan humas di tb_arsipuser / opti_user_map) untuk pilihan UP Pihak Kedua. */
    protected function daftarHumas() {
        $hasil = [];
        $sql = "SELECT DISTINCT u.id_user, u.nama_user, u.no_hp
                  FROM tb_arsipuser u
                  LEFT JOIN opti_user_map m ON m.id_user = u.id_user AND m.status_tampil = 'show' AND (m.is_active = 1 OR m.is_active = '1')
                 WHERE (u.status = 1 OR u.status = '1' OR u.status = 'aktif')
                   AND (u.si_opti = 'humas' OR m.role = 'humas')
                 ORDER BY u.nama_user ASC";
        try {
            $baris = $this->db->exec($sql);
        } catch (\Throwable $e) {
            try {
                $sumber = $this->dbSekretariat ?: $this->db;
                $baris = $sumber->exec("SELECT id_user, nama_user, no_hp FROM tb_arsipuser WHERE si_opti = 'humas' AND (status = 1 OR status = '1' OR status = 'aktif') ORDER BY nama_user ASC");
            } catch (\Throwable $e2) {
                return [];
            }
        }
        $sudah = [];
        foreach ((array) $baris as $r) {
            $nama = trim((string) $r['nama_user']);
            if ($nama === '' || isset($sudah[$nama])) { continue; }
            $sudah[$nama] = true;
            $hasil[] = ['id' => (int) $r['id_user'], 'nama' => $nama, 'wa' => $this->normalHp($r['no_hp'])];
        }
        return $hasil;
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
            $mentah = ($fid === 'nomorUrut') ? '' : $f3->get('POST.' . $fid);   // nomor urut ditetapkan server
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
            case 'rich':
                // teks berformat dari editor (teks biasa, atau HTML sederhana b/i/u per baris): dibersihkan ke bentuk baku
                $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v);
                return \PksDocx::kanonik((string) $s, $maks);
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
        return dirname(__DIR__, 2) . '/storage/pks_logo';
    }

    protected function pathTemplate($divisi = '') {
        $d = strtolower(trim((string) $divisi));
        $berkas = isset(self::TEMPLATE[$d]) ? self::TEMPLATE[$d] : self::TEMPLATE_DEFAULT;
        return dirname(__DIR__, 2) . '/storage/templates/' . $berkas;
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

    /** Validasi + simpan logo. Input PNG/JPG, disimpan selalu sebagai PNG berlatar putih. */
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

        // Selalu simpan sebagai PNG dengan latar putih (transparan diratakan), sama seperti di PDF.
        $nama = bin2hex(random_bytes(16)) . '.png';
        $tujuan = $dir . '/' . $nama;
        if ($this->ratakanKePng($f['tmp_name'], $info[2], $tujuan)) {
            return ['file' => $nama, 'mime' => 'image/png'];
        }

        // GD tidak tersedia / gagal: simpan apa adanya (browser sudah mengirim PNG hasil konversi).
        $ext = ($info[2] === IMAGETYPE_PNG) ? 'png' : 'jpg';
        $nama = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nama)) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Logo gagal disimpan di server.'], 500);
        }
        return ['file' => $nama, 'mime' => ($ext === 'png') ? 'image/png' : 'image/jpeg'];
    }

    /** Konversi gambar ke PNG dengan latar putih memakai GD. false kalau GD tidak ada atau gagal. */
    protected function ratakanKePng($src, $tipe, $tujuan) {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) { return false; }
        $im = ($tipe === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) ? @imagecreatefrompng($src)
            : (($tipe === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) ? @imagecreatefromjpeg($src) : false);
        if (!$im) { return false; }
        $w = imagesx($im); $h = imagesy($im);
        // Kanvas = rasio kotak logo di template Word (6,6 x 2,28 cm); logo rata kiri, tengah vertikal, proporsi tetap.
        $rasio = 2376805 / 821690;
        $cw = $w; $ch = (int) round($w / $rasio);
        if ($ch < $h) { $ch = $h; $cw = (int) round($h * $rasio); }
        $kanvas = imagecreatetruecolor($cw, $ch);
        imagefill($kanvas, 0, 0, imagecolorallocate($kanvas, 255, 255, 255));
        imagealphablending($kanvas, true);
        imagecopy($kanvas, $im, 0, (int) round(($ch - $h) / 2), 0, 0, $w, $h);
        $ok = @imagepng($kanvas, $tujuan, 6);
        imagedestroy($im); imagedestroy($kanvas);
        return $ok && is_file($tujuan) && filesize($tujuan) > 0;
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

    /** Nomor PKS lengkap yang SUDAH terbit untuk kontrak ini ('' kalau belum / baru draft tanpa nomor). */
    protected function nomorTerbit($k) {
        if (!$k || trim((string) $k['nomor_urut']) === '') { return ''; }
        return (string) $k['nomor_pks'];
    }

    /** Tanggal perjanjian otomatis: tanggal tersimpan kalau nomor sudah terbit (terkunci), kalau belum = hari ini. */
    protected function tanggalOtomatis($k) {
        if ($this->nomorTerbit($k) !== '' && !empty($k['tanggal_perjanjian'])) { return substr((string) $k['tanggal_perjanjian'], 0, 10); }
        return date('Y-m-d');
    }

    /** Lepas nomor yang baru diterbitkan kalau penyimpanan kontrak gagal. */
    protected function lepasNomor($id) {
        try { $this->db->exec('DELETE FROM pks_nomor_urut WHERE id = ?', [1 => (int) $id]); } catch (\Throwable $e) {}
    }

    /**
     * Terbitkan Nomor PKS baru: "B/<urut>/BBSPJIS/HK/<ROMAWI>/<TAHUN>". Urut BERLANJUT sepanjang tahun dan kembali ke 1
     * saat ganti tahun (N = MAX(nomor_urut) tahun itu + 1); bulan dan tahun dari Tanggal perjanjian. Pola sama dengan
     * PoSchema::nomorBaru(): dikunci per tahun (GET_LOCK), UNIQUE KEY jadi pengaman terakhir (bentrok -> coba angka
     * berikutnya, maksimal 5x).
     *
     * @return array{id:int, detail_nomor:string, nomor_urut:int}
     */
    protected function nomorBaru($tanggal) {
        $ts    = strtotime((string) $tanggal) ?: time();
        $bulan = (int) date('n', $ts);
        $tahun = (int) date('Y', $ts);

        $namaKunci  = 'pks_nomor_urut_' . $tahun;
        $kunciDapat = false;
        try {
            $r = $this->db->exec('SELECT GET_LOCK(?, 5) AS k', [1 => $namaKunci]);
            $kunciDapat = !empty($r) && (int) (isset($r[0]['k']) ? $r[0]['k'] : 0) === 1;
        } catch (\Throwable $e) {
        }

        try {
            for ($percobaan = 0; $percobaan < 5; $percobaan++) {
                try {
                    $row = $this->db->exec('SELECT COALESCE(MAX(nomor_urut), 0) AS maxu FROM pks_nomor_urut WHERE tahun = ?', [1 => $tahun]);
                } catch (\Throwable $e) {
                    throw new \Exception('Tabel pks_nomor_urut belum ada. Jalankan migration_pks_kontrak.sql dulu di phpMyAdmin.');
                }
                $urutanBaru  = (int) (isset($row[0]['maxu']) ? $row[0]['maxu'] : 0) + 1 + $percobaan;
                $detailNomor = 'B/' . $urutanBaru . '/BBSPJIS/HK/' . self::ROMAWI[$bulan] . '/' . $tahun;
                try {
                    $this->db->exec(
                        'INSERT INTO pks_nomor_urut (nomor_urut, bulan, tahun, detail_nomor, updated_at) VALUES (?, ?, ?, ?, NOW())',
                        [1 => $urutanBaru, 2 => $bulan, 3 => $tahun, 4 => $detailNomor]
                    );
                    $idRow = $this->db->exec('SELECT LAST_INSERT_ID() AS id');
                    return [
                        'id'           => (int) (isset($idRow[0]['id']) ? $idRow[0]['id'] : 0),
                        'detail_nomor' => $detailNomor,
                        'nomor_urut'   => $urutanBaru,
                    ];
                } catch (\Throwable $e) {
                    continue;   // bentrok UNIQUE -> coba nomor berikutnya
                }
            }
        } finally {
            if ($kunciDapat) {
                try { $this->db->exec('SELECT RELEASE_LOCK(?)', [1 => $namaKunci]); } catch (\Throwable $e) {}
            }
        }
        throw new \Exception('Gagal membuat nomor PKS baru, silakan coba lagi.');
    }

    protected function nomorPks(array $v) {
        $d = $this->infoTanggal($v['tanggal']);
        $urut = trim((string) $v['nomorUrut']);
        return 'B/' . ($urut !== '' ? $urut : '.....') . '/BBSPJIS/HK/' . ($d ? self::ROMAWI[(int) $d->format('n')] : 'Bulan') . '/' . ($d ? $d->format('Y') : 'Tahun');
    }

    /** Nilai form -> penanda template Word (lihat PksDocx). */
    protected function susunDataDocx(array $v, $nomorTetap = '') {
        $d = $this->infoTanggal($v['tanggal']);
        $m = $this->infoTanggal($v['mulai']);
        $s = $this->infoTanggal($v['selesai']);
        $n = ($m && $s && $s >= $m) ? ((int) $m->diff($s)->days + 1) : 0;

        $lingkup = [];   // satu poin = satu baris berformat (daftar run)
        foreach (\PksDocx::barisDariTeks($v['ruangLingkup']) as $b) {
            if (\PksDocx::teksBaris($b) !== '') { $lingkup[] = $b; }
        }
        $atauStrip = function ($x) { $x = trim((string) $x); return $x !== '' ? $x : '-'; };

        return [
            'perusahaan'          => $v['perusahaan'],
            'judul'               => $v['judul'],
            'nomor_pihak_pertama' => $atauStrip($v['nomorPihakPertama']),
            'nomor_pks'           => ($nomorTetap !== '' ? $nomorTetap : $this->nomorPks($v)),
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
            'email'               => $atauStrip($v['email']),
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
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses ke modul PKS.'], 403);
        }
        $this->f3->error(403, 'Modul PKS hanya untuk Humas, Admin PKS, dan Superadmin.');
        exit;
    }

    /**
     * Akses BACA PKS yang sudah SELESAI dibuat Humas.
     *  - Melihat (halaman form hanya dengan ?lihat=1, logo untuk tampilan): semua user yang sudah login.
     *  - Mengunduh / mencetak ($unduh = true: Word, PDF, unduh logo): Humas, Admin PKS, Superadmin, dan Tim Mitra.
     *    Ketua Tim belum diputuskan, jadi sementara hanya boleh melihat.
     * Menyimpan / menyelesaikan PKS tetap hanya untuk Humas, Admin PKS, dan Superadmin (lihat izinkan()).
     * $halamanForm = true untuk GET /pks/@id/form.
     */
    protected function izinkanBaca($poId, $halamanForm = false, $unduh = false) {
        $this->requireAuth();
        if ($this->isSuperadmin() || in_array((string) $this->getUserRole(), self::ROLE_BOLEH, true)) { return; }   // Humas / Admin PKS: akses penuh seperti sebelumnya
        $k = $this->ambilKontrak((int) $poId);
        $selesai = ($k && $k['status'] === 'selesai');
        if ($unduh) {
            if ($selesai && $this->isTimMitra()) { return; }
            $this->f3->error(403, 'Mengunduh PKS hanya untuk Tim Mitra, Humas, dan Superadmin. Anda masih bisa melihatnya lewat tombol Lihat PKS.');
            exit;
        }
        if ($selesai && (!$halamanForm || (string) $this->f3->get('GET.lihat') === '1')) { return; }
        $this->f3->error(403, 'PKS ini belum selesai dibuat Humas, atau hanya bisa dibuka lewat tombol Lihat PKS.');
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