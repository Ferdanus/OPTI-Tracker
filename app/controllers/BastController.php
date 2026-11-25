<?php

/**
 * Controller untuk mengelola Berita Acara Serah Terima (BAST) & Penutupan Order
 *
 * Form BAST (form + pratinjau dokumen) memakai data PKS yang sudah selesai. Nomor BAST "B/<urut>/BBSPJIS/HK/<romawi>/<tahun>": urut manual,
 * unik per urut + bulan + tahun (tabel bast_nomor_urut, terpisah dari nomor PKS). BAST diselesaikan = order otomatis selesai.
 */
class BastController extends Controller {

    const HARI  = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    const BATAS = array('nomorUrut' => 9, 'perusahaan' => 255, 'alamat' => 1000, 'ttdNama' => 150, 'ttdJabatan' => 200, 'judul' => 1000,
                        'pksNomor' => 120, 'keduaNama' => 150, 'keduaJabatan' => 200, 'keduaNip' => 60, 'mengetahuiNama' => 150, 'mengetahuiJabatan' => 150);
    /** Isian wajib saat Selesai: id => label */
    const WAJIB = array('nomorUrut' => 'Nomor urut BAST', 'tanggal' => 'Tanggal BAST', 'perusahaan' => 'Perusahaan', 'alamat' => 'Alamat perusahaan',
                        'ttdNama' => 'Nama Pihak Pertama', 'ttdJabatan' => 'Jabatan Pihak Pertama', 'judul' => 'Judul pekerjaan', 'pksNomor' => 'Nomor PKS',
                        'pksTanggal' => 'Tanggal PKS', 'keduaNama' => 'Nama Pihak Kedua', 'keduaJabatan' => 'Jabatan Pihak Kedua', 'keduaNip' => 'NIP Pihak Kedua',
                        'mengetahuiNama' => 'Pejabat yang mengetahui', 'mengetahuiJabatan' => 'Jabatan pejabat yang mengetahui');
    /** Isian wajib saat Ketua Tim mengirim ke Humas (nomor, tanggal, dan pejabat yang mengetahui diisi Humas belakangan) */
    const WAJIB_KIRIM = array('perusahaan' => 'Perusahaan', 'alamat' => 'Alamat perusahaan', 'ttdNama' => 'Nama Pihak Pertama', 'ttdJabatan' => 'Jabatan Pihak Pertama',
                              'judul' => 'Judul pekerjaan', 'pksNomor' => 'Nomor PKS', 'pksTanggal' => 'Tanggal PKS', 'keduaNama' => 'Nama Pihak Kedua', 'keduaJabatan' => 'Jabatan Pihak Kedua', 'keduaNip' => 'NIP Pihak Kedua');
    /** Isian yang dikunci dari PKS kalau PKS-nya ada */
    const DARI_PKS = array('perusahaan', 'alamat', 'ttdNama', 'ttdJabatan', 'judul', 'pksNomor', 'pksNomorP1', 'pksTanggal');

    /**
     * Form BAST (form + pratinjau dokumen live), isinya mengikuti peran:
     *  - Ketua Tim layanan order ini: lengkapi jabatan + NIP Pihak Kedua, lalu "Kirim ke Humas".
     *  - Humas / Admin PKS: isi nomor urut dan pilih pejabat yang mengetahui, lalu "Selesai" (tanggal BAST = hari nomor diberikan).
     *  - Superadmin: semua isian. Yang lain (termasuk Tim Mitra): hanya melihat.
     * Draf BAST dibuat otomatis oleh sistem (OptiBast::siapkanDraf) begitu PO bertanda tangan + PKS + Laporan Akhir selesai.
     * Route: GET /order/@id/bast  dan  /order/@id/bast/buat
     */
    public function formBast($f3, $params) {
        $this->requirePermission('order:view', '/order');

        $orderId = (int)($params['id'] ?? 0);
        \OptiBast::pastikanSkema($this->db);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$orderId} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $sessionUser = $f3->get('SESSION.user') ?? [];
        $userId = (int)($sessionUser['id'] ?? ($this->getUserId() ?? 0));
        $userNama = $sessionUser['nama'] ?? ($sessionUser['nama_lengkap'] ?? ($_SESSION['nama_lengkap'] ?? 'Sistem'));
        \OptiBast::siapkanDraf($this->db, $orderId, $userId, (string) $userNama);   // order lama / hook yang terlewat

        $bastModel = new OptiBast($this->db);
        $bast = $bastModel->getByOrderId($orderId);
        $mode = $this->modeBast($order);
        $urlKembali = ($mode === 'humas') ? '/humas/bast' : '/order/' . $orderId;

        if (empty($bast)) {
            if ($mode !== 'penuh' || (!$this->poTtdSudahDiunggah($orderId) && !$this->canEditSubmittedData(true, $orderId))) {
                $this->setFlashError('BAST belum tersedia. BAST dibuat otomatis setelah PO bertanda tangan diunggah, PKS dibuat Humas, dan Laporan Akhir (Tahap 10) diselesaikan.');
                $f3->reroute($urlKembali);
                return;
            }
        }
        $st = strtolower(trim((string) ($bast['status_bast'] ?? '')));
        if ($mode === 'humas' && $st === \OptiBast::ST_DRAF) {
            $this->setFlashError('BAST ini belum dikirim Ketua Tim. Humas baru bisa memberi nomor setelah Ketua Tim mengirimnya.');
            $f3->reroute('/humas/bast');
            return;
        }
        if ($mode === 'humas' && $st === \OptiBast::ST_KIRIM && empty($bast['dibuka_humas_at'])) {
            try { $bastModel->tandaiDibuka((int) $bast['id']); $bast['dibuka_humas_at'] = date('Y-m-d H:i:s'); } catch (\Throwable $e) {}
        }

        $terkunci = ($st === \OptiBast::ST_SELESAI) && !$this->canEditSubmittedData(true, $orderId);
        $boleh = $terkunci ? array() : $this->bolehEdit($mode, $st, $bast);
        $aksi  = $terkunci ? array('draft' => false, 'kirim' => false, 'selesai' => false) : $this->aksiBoleh($mode, $st);

        $pks = $this->ambilPks($orderId);
        $pejabat = $this->daftarPejabat();
        $kepala = $this->pilihKepala($pejabat);

        $v = array(
            'nomorUrut' => '', 'tanggal' => date('Y-m-d'),
            'perusahaan' => '', 'alamat' => '', 'ttdNama' => '', 'ttdJabatan' => '', 'judul' => '', 'pksNomor' => '', 'pksNomorP1' => '', 'pksTanggal' => '',
            'keduaNama' => '', 'keduaJabatan' => '', 'keduaNip' => '',
            'mengetahuiNama' => '', 'mengetahuiJabatan' => '',
        );
        if (empty($bast) && $mode === 'penuh') { $v['mengetahuiNama'] = $kepala['nama']; $v['mengetahuiJabatan'] = $kepala['jabatan']; }   // BAST manual (Superadmin): usulan awal Kepala Balai
        if ($pks) {
            $v['perusahaan'] = $this->namaPerusahaan($pks, $order);
            $v['alamat']     = $this->teksPolos($pks['alamat_perusahaan']);
            $v['ttdNama']    = (string) $pks['ttd_nama'];
            $v['ttdJabatan'] = (string) $pks['ttd_jabatan'];
            $v['judul']      = $this->teksPolos($pks['judul']);
            $v['pksNomor']   = (string) $pks['nomor_pks'];
            $v['pksNomorP1'] = $this->nomorPihakPertama($pks);
            $v['pksTanggal'] = $pks['tanggal_perjanjian'] ? substr((string) $pks['tanggal_perjanjian'], 0, 10) : '';
        } else {
            $v['perusahaan'] = trim((string) ($order['nama_perusahaan'] ?? ''));
            $v['alamat']     = trim((string) ($order['alamat'] ?? '')) === '-' ? '' : trim((string) ($order['alamat'] ?? ''));
            $v['judul']      = trim((string) ($order['judul_kegiatan'] ?? ''));
        }

        $status = 'baru';
        $nomorTerbit = '';
        $fileUrl = '';
        if (!empty($bast)) {
            $status = ($st === \OptiBast::ST_SELESAI) ? 'selesai' : ($st === \OptiBast::ST_KIRIM ? 'dikirim' : 'draft');
            $dariBast = $this->nilaiDariBast($bast);
            foreach ($dariBast as $id => $nilai) {
                if ($pks && in_array($id, self::DARI_PKS, true)) { continue; }   // PKS jadi sumber kebenaran
                if (trim((string) $nilai) !== '') { $v[$id] = (string) $nilai; }
            }
            if ($mode === 'humas' && $status !== 'selesai') { $v['tanggal'] = date('Y-m-d'); }   // tanggal BAST = hari Humas memberi nomor
            elseif ($status !== 'selesai' && $mode !== 'penuh') { $v['tanggal'] = ''; }          // belum bernomor: tanggal baru ada saat Humas memberi nomor
            if ($status === 'selesai' && !empty($bast['id_nomor_urut'])) { $nomorTerbit = (string) $bast['nomor_bast']; }
            if (!empty($bast['file_dokumen_bast'])) { $fileUrl = $f3->get('BASE') . '/' . $bast['file_dokumen_bast']; }
        }

        $info = array('dikirimAt' => '', 'dikirimOleh' => '', 'dibukaHumas' => '');
        if (!empty($bast['dikirim_at'])) {
            $info['dikirimAt'] = date('d-m-Y H:i', strtotime((string) $bast['dikirim_at']));
            if (!empty($bast['dikirim_oleh'])) {
                try {
                    $u = $this->db->exec('SELECT nama_user FROM tb_arsipuser WHERE id_user = ? LIMIT 1', array(1 => (int) $bast['dikirim_oleh']));
                    $info['dikirimOleh'] = !empty($u) ? (string) $u[0]['nama_user'] : '';
                } catch (\Throwable $e) {}
            }
        }
        if (!empty($bast['dibuka_humas_at'])) { $info['dibukaHumas'] = date('d-m-Y H:i', strtotime((string) $bast['dibuka_humas_at'])); }

        $data = array(
            'order' => array('id' => $orderId, 'nomor' => (string) ($order['nomor_order'] ?? $orderId), 'perusahaan' => (string) ($order['nama_perusahaan'] ?? '')),
            'status' => $status,
            'mode' => $mode,
            'boleh' => array_values($boleh),
            'aksi' => $aksi,
            'terkunci' => $terkunci,
            'info' => $info,
            'kembaliUrl' => $f3->get('BASE') . $urlKembali,
            'nomorTerbit' => $nomorTerbit,
            'pksAda' => (bool) $pks,
            'vals' => $v,
            'pejabat' => $pejabat,
            'fileUrl' => $fileUrl,
        );
        $f3->set('bast_json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('order', $order);
        $f3->set('bast_kembali', $f3->get('BASE') . $urlKembali);

        // Audit Tahap 9: BAST dibuka Tim Mitra / Superadmin (First Time Only)
        if ($userId > 0 && $orderId > 0 && ($this->isTimMitra() || $this->isSuperadmin())) {
            \StageAudit::recordDibaca($this->db, $orderId, 9, $userId, $userNama, 'Tim Mitra');
            try {
                $hasLogged = $this->db->exec(
                    "SELECT id FROM opti_activity_log WHERE order_id = ? AND user_id = ? AND (aksi = 'melihat_tahap_9' OR aksi = 'melihat_tahap_bast') LIMIT 1",
                    [1 => $orderId, 2 => $userId]
                );
                if (empty($hasLogged)) {
                    $this->logActivity($orderId, 'bast', 'melihat_tahap_bast', 'Melihat dokumen BAST');
                }
            } catch (\Exception $e) {}
        }

        // ?fragment=1 : hanya isi form (tanpa layout), dipasang langsung di kartu Tahap 11 Detail Order (bukan di dalam iframe)
        if ((string) $f3->get('GET.fragment') === '1') {
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: private, no-store');
            echo \Template::instance()->render('bast/form.html');
            return;
        }

        $this->render('bast/form.html', "Berita Acara Serah Terima - Order #{$order['nomor_order']}", 'order');
    }

    /* ---------- peran dan hak edit ---------- */

    /** penuh = Superadmin; humas = Humas / Admin PKS; katim = Ketua Tim layanan order ini; lihat = selain itu (termasuk Tim Mitra). */
    protected function modeBast(array $order) {
        if ($this->isSuperadmin()) { return 'penuh'; }
        $role = (string) $this->getUserRole();
        if ($role === 'humas' || $role === 'admin_kontrak') { return 'humas'; }
        if ($this->isKetuaTim() && $this->isKetuaTimForOrder($order)) { return 'katim'; }
        return 'lihat';
    }

    /** Isian yang boleh diubah mode ini pada status BAST ini ('berkas' = unggah BAST bertanda tangan). Selain itu terkunci. */
    protected function bolehEdit($mode, $st, $bast) {
        if ($mode === 'penuh') {
            return array('nomorUrut', 'tanggal', 'perusahaan', 'alamat', 'ttdNama', 'ttdJabatan', 'judul', 'pksNomor', 'pksTanggal', 'keduaNama', 'keduaJabatan', 'keduaNip', 'mengetahuiNama', 'mengetahuiJabatan', 'berkas');
        }
        if ($mode === 'katim' && $st === \OptiBast::ST_DRAF) {
            $b = array('keduaJabatan', 'keduaNip');
            if (!$bast || trim((string) ($bast['pihak_kedua_nama'] ?? '')) === '') { $b[] = 'keduaNama'; }   // belum ada Ketua Tim terdaftar: diketik sendiri
            return $b;
        }
        if ($mode === 'humas' && ($st === \OptiBast::ST_KIRIM || $st === \OptiBast::ST_SELESAI)) {
            return array('nomorUrut', 'mengetahuiNama', 'mengetahuiJabatan', 'berkas');
        }
        return array();
    }

    /** Tombol aksi yang berlaku untuk mode + status ini. */
    protected function aksiBoleh($mode, $st) {
        $a = array('draft' => false, 'kirim' => false, 'selesai' => false);
        if ($mode === 'penuh') { $a['draft'] = true; $a['selesai'] = true; }
        elseif ($mode === 'katim' && $st === \OptiBast::ST_DRAF) { $a['draft'] = true; $a['kirim'] = true; }
        elseif ($mode === 'humas' && ($st === \OptiBast::ST_KIRIM || $st === \OptiBast::ST_SELESAI)) { $a['draft'] = true; $a['selesai'] = true; }
        return $a;
    }

    /** Baris opti_bast -> nilai isian form (kunci sama dengan input form). */
    protected function nilaiDariBast(array $b) {
        $peta = array('nomorUrut' => 'nomor_urut', 'perusahaan' => 'pihak_pertama_perusahaan', 'alamat' => 'pihak_pertama_alamat', 'ttdNama' => 'pihak_pertama_nama',
                      'ttdJabatan' => 'pihak_pertama_jabatan', 'judul' => 'judul_pekerjaan', 'pksNomor' => 'pks_nomor', 'keduaNama' => 'pihak_kedua_nama',
                      'keduaJabatan' => 'pihak_kedua_jabatan', 'keduaNip' => 'pihak_kedua_nip', 'mengetahuiNama' => 'mengetahui_nama', 'mengetahuiJabatan' => 'mengetahui_jabatan');
        $h = array();
        foreach ($peta as $id => $kol) { $h[$id] = isset($b[$kol]) ? (string) $b[$kol] : ''; }
        $h['tanggal']    = !empty($b['tanggal_bast']) ? substr((string) $b['tanggal_bast'], 0, 10) : '';
        $h['pksTanggal'] = !empty($b['pks_tanggal']) ? substr((string) $b['pks_tanggal'], 0, 10) : '';
        return $h;
    }

    /** POST /order/@id/bast/draft */
    public function simpanBast($f3, $params) {
        $this->prosesSimpan($f3, (int)($params['id'] ?? 0), 'draft');
    }

    /** POST /order/@id/bast/kirim : Ketua Tim mengirim BAST ke Humas (menunggu nomor). */
    public function kirimBast($f3, $params) {
        $this->prosesSimpan($f3, (int)($params['id'] ?? 0), 'kirim');
    }

    /** POST /order/@id/bast/selesai : Humas memberi nomor (BAST selesai). */
    public function selesaiBast($f3, $params) {
        $this->prosesSimpan($f3, (int)($params['id'] ?? 0), 'selesai');
    }

    /** GET /order/@id/bast/cek-nomor?urut=12&tanggal=2026-10-21 : bentrok kalau urut + bulan + tahun sama dengan BAST lain. */
    public function cekNomor($f3, $params) {
        $this->izinkanJson('order:view');
        $orderId = (int)($params['id'] ?? 0);
        \OptiBast::pastikanSkema($this->db);
        $bastModel = new OptiBast($this->db);
        $bast = $bastModel->getByOrderId($orderId);
        $tgl = $this->tanggalValid((string) $f3->get('GET.tanggal'));
        $ts = strtotime($tgl !== '' ? $tgl : date('Y-m-d'));
        $bulan = (int) date('n', $ts);
        $tahun = (int) date('Y', $ts);
        $urut = $this->urutBersih((string) $f3->get('GET.urut'));
        if ($urut === '') { $this->json(array('ok' => true, 'bentrok' => false, 'nomor' => '')); }
        try {
            $b = $bastModel->cariBentrok((int) $urut, $bulan, $tahun, ($bast && !empty($bast['id_nomor_urut'])) ? (int) $bast['id_nomor_urut'] : null);
        } catch (\Throwable $e) {
            $this->json(array('ok' => false, 'pesan' => 'Gagal memeriksa nomor: ' . $e->getMessage()), 500);
        }
        $this->json(array('ok' => true, 'bentrok' => (bool) $b, 'nomor' => OptiBast::detailNomor($urut, $bulan, $tahun),
                          'pesan' => $b ? OptiBast::pesanBentrok($b, $urut, $bulan, $tahun) : ''));
    }

    /** GET /order/@id/bast/unduh : BAST sebagai file Word dari template (storage/templates/bast_template.docx). */
    public function unduh($f3, $params) {
        $this->requirePermission('order:view', '/order');
        $orderId = (int)($params['id'] ?? 0);
        \OptiBast::pastikanSkema($this->db);
        $bast = (new OptiBast($this->db))->getByOrderId($orderId);
        if (!$bast || $bast['status_bast'] !== 'selesai') {
            $f3->error(404, 'BAST belum berstatus selesai, jadi belum bisa diunduh.');
            return;
        }
        $path = dirname(__DIR__, 2) . '/storage/templates/bast_template.docx';
        try {
            $isi = \BastDocx::buat($path, $this->dataDocx($bast));
        } catch (\Throwable $e) {
            $f3->error(500, 'Gagal membuat dokumen Word BAST: ' . $e->getMessage());
            return;
        }
        $per = trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $bast['pihak_pertama_perusahaan']), '-');
        $nama = 'BAST_' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $bast['nomor_bast']), '-') . ($per !== '' ? '_' . $per : '') . '.docx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $nama . '"');
        header('Content-Length: ' . strlen($isi));
        header('Cache-Control: private, no-store, max-age=0');
        echo $isi;
        exit;
    }

    /** Baris BAST -> penanda template Word. */
    protected function dataDocx(array $b) {
        $ts = strtotime((string) $b['tanggal_bast']) ?: time();
        $tsPks = !empty($b['pks_tanggal']) ? strtotime((string) $b['pks_tanggal']) : false;
        $nip = trim((string) $b['pihak_kedua_nip']);
        $jab = trim((string) $b['pihak_kedua_jabatan']);
        // Nomor PKS di dokumen: nomor perjanjian versi klien (Pihak Pertama, kalau diisi di PKS); nomor kita (Pihak Kedua). Kosong -> tidak ditampilkan.
        $nomorPks = (string) $b['pks_nomor'];
        if (!empty($b['order_id'])) {
            $pksDoc = $this->ambilPks((int) $b['order_id']);
            $p1 = $pksDoc ? $this->nomorPihakPertama($pksDoc) : '';
            if ($p1 !== '') { $nomorPks = $p1 . '; ' . $nomorPks; }
        }
        return array(
            'nomor_bast'         => (string) $b['nomor_bast'],
            'hari'               => self::HARI[(int) date('N', $ts) - 1],
            'tanggal_kata'       => self::terbilang((int) date('j', $ts)),
            'bulan'              => OptiBast::BULAN[(int) date('n', $ts) - 1],
            'tahun_kata'         => self::terbilang((int) date('Y', $ts)),
            'ttd_nama'           => (string) $b['pihak_pertama_nama'],
            'ttd_jabatan'        => (string) $b['pihak_pertama_jabatan'],
            'perusahaan'         => (string) $b['pihak_pertama_perusahaan'],
            'alamat_perusahaan'  => (string) $b['pihak_pertama_alamat'],
            'kedua_nama'         => (string) $b['pihak_kedua_nama'],
            'kedua_nip_jabatan'  => ($nip !== '' ? 'NIP. ' . $nip . ($jab !== '' ? ' & ' : '') : '') . $jab,
            'judul'              => (string) $b['judul_pekerjaan'],
            'nomor_pks'          => $nomorPks,
            'tanggal_pks'        => $tsPks ? ((int) date('j', $tsPks) . ' ' . OptiBast::BULAN[(int) date('n', $tsPks) - 1] . ' ' . date('Y', $tsPks)) : '',
            'mengetahui_jabatan' => (string) $b['mengetahui_jabatan'],
            'mengetahui_nama'    => (string) $b['mengetahui_nama'],
        );
    }

    /**
     * Simpan BAST. $aksi: 'draft' (simpan), 'kirim' (Ketua Tim -> Humas), 'selesai' (Humas memberi nomor).
     * Selesai: nomor didaftarkan (bast_nomor_urut), BAST berstatus selesai, dan order otomatis ditutup (Order Selesai) lewat OptiBast::tutupOrder
     * (kelanjutan setelah Humas memberi nomor belum diputuskan, jadi perilaku lama dipertahankan).
     * Isian yang boleh diubah dibatasi menurut peran (bolehEdit); isian lain selalu diambil dari data tersimpan, bukan dari kiriman form.
     */
    protected function prosesSimpan($f3, $orderId, $aksi) {
        $this->izinkanJson('order:view');
        \OptiBast::pastikanSkema($this->db);

        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);
        if (!$order) { $this->json(array('ok' => false, 'pesan' => "Order #{$orderId} tidak ditemukan."), 404); }

        $final = ($aksi === 'selesai');
        $kirim = ($aksi === 'kirim');
        $bastModel = new OptiBast($this->db);
        $lama = $bastModel->getByOrderId($orderId);
        $mode = $this->modeBast($order);
        $statusLama = $lama ? strtolower(trim((string) ($lama['status_bast'] ?? ''))) : '';

        if (!$lama) {
            if ($mode !== 'penuh') { $this->json(array('ok' => false, 'pesan' => 'BAST belum tersedia. BAST dibuat otomatis setelah PO bertanda tangan, PKS, dan Laporan Akhir selesai.'), 409); }
            if (!$this->poTtdSudahDiunggah($orderId) && !$this->canEditSubmittedData(true, $orderId)) {
                $this->json(array('ok' => false, 'pesan' => 'BAST belum bisa dibuat: upload dulu PO bertanda tangan, lalu tunggu Humas membuat PKS.'), 409);
            }
        }
        if ($statusLama === \OptiBast::ST_SELESAI && !$this->canEditSubmittedData(true, $orderId)) {
            $this->json(array('ok' => false, 'pesan' => 'Dokumen BAST sudah selesai dan dikunci. Hubungi Superadmin untuk membuka izin edit.'), 423);
        }
        $aksiOk = $this->aksiBoleh($mode, $statusLama);
        if (empty($aksiOk[$aksi])) {
            if ($mode === 'lihat') { $pesan = 'Anda tidak punya akses untuk mengubah BAST ini.'; }
            elseif ($mode === 'katim' && $statusLama !== \OptiBast::ST_DRAF) { $pesan = 'BAST ini sudah dikirim ke Humas dan tidak bisa diubah lagi oleh Ketua Tim.'; }
            elseif ($mode === 'humas') { $pesan = 'BAST ini belum dikirim Ketua Tim, jadi Humas belum bisa memberi nomor.'; }
            else { $pesan = 'Tindakan ini tidak tersedia untuk peran Anda pada BAST ini.'; }
            $this->json(array('ok' => false, 'pesan' => $pesan), 403);
        }
        if ($statusLama === \OptiBast::ST_SELESAI) { $final = true; $kirim = false; }   // BAST yang sudah selesai tidak kembali jadi draft

        // ---- isian: hanya yang boleh diubah peran ini yang diambil dari form ----
        $boleh = $this->bolehEdit($mode, $statusLama, $lama);
        $post = $this->bacaInput($f3);
        if ($lama) {
            $v = $this->nilaiDariBast($lama);
            foreach ($boleh as $id) { if (array_key_exists($id, $post)) { $v[$id] = $post[$id]; } }
        } else {
            $v = $post;
        }
        $pks = $this->ambilPks($orderId);
        if ($pks) {   // data dari PKS dikunci, tidak bisa diubah lewat form
            $v['perusahaan'] = $this->namaPerusahaan($pks, $order);
            $v['alamat']     = $this->teksPolos($pks['alamat_perusahaan']);
            $v['ttdNama']    = (string) $pks['ttd_nama'];
            $v['ttdJabatan'] = (string) $pks['ttd_jabatan'];
            $v['judul']      = $this->teksPolos($pks['judul']);
            $v['pksNomor']   = (string) $pks['nomor_pks'];
            $v['pksNomorP1'] = $this->nomorPihakPertama($pks);
            $v['pksTanggal'] = $pks['tanggal_perjanjian'] ? substr((string) $pks['tanggal_perjanjian'], 0, 10) : '';
        }
        if ($mode === 'humas') {   // tanggal BAST = hari Humas memberi nomor (BAST yang sudah selesai tetap pada tanggalnya)
            $v['tanggal'] = ($statusLama === \OptiBast::ST_SELESAI && $this->tanggalValid((string) $v['tanggal']) !== '') ? $v['tanggal'] : date('Y-m-d');
        }
        if ($v['tanggal'] === '') { $v['tanggal'] = date('Y-m-d'); }

        if ($final || $kirim) {
            $kosong = array(); $label = array();
            foreach (($final ? self::WAJIB : self::WAJIB_KIRIM) as $id => $lbl) {
                if ($id === 'keduaNip' && !in_array('keduaNip', $boleh, true)) { continue; }   // NIP diisi Ketua Tim; peran lain (mis. Humas pada BAST lama tanpa NIP) tidak bisa mengisinya, jadi tidak ditahan di sini
                if (trim((string) $v[$id]) === '') { $kosong[] = $id; $label[] = $lbl; }
            }
            if ($kosong) {
                $this->json(array('ok' => false, 'kosong' => $kosong, 'pesan' => 'Masih ada isian wajib yang kosong: ' . implode(', ', array_slice($label, 0, 4)) . (count($label) > 4 ? ', dan lainnya' : '') . '.'), 422);
            }
        }

        // ---- berkas BAST bertanda tangan (opsional, Humas / Superadmin) ----
        $fileDokumen = $lama ? ($lama['file_dokumen_bast'] ?: null) : null;
        $fileBaru = null;
        if (in_array('berkas', $boleh, true) && !empty($_FILES['file_bast']['name']) && (int) $_FILES['file_bast']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['file_bast']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'), true) || (int) $_FILES['file_bast']['size'] > 10 * 1024 * 1024) {
                $this->json(array('ok' => false, 'pesan' => 'Berkas BAST harus PDF, DOC, DOCX, JPG, atau PNG dan maksimal 10 MB.'), 422);
            }
            $dir = 'uploads/bast/';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $target = $dir . 'bast_ord_' . $orderId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['file_bast']['tmp_name'], $target)) { $fileBaru = $target; $fileDokumen = $target; }
        }

        // ---- nomor ----
        $idNomorLama = ($lama && !empty($lama['id_nomor_urut'])) ? (int) $lama['id_nomor_urut'] : null;
        $idNomorBaru = 0; $nomorLamaRow = null; $idNomor = $idNomorLama;
        if ($final) {
            try {
                $t = $bastModel->daftarkanNomor((int) $v['nomorUrut'], $v['tanggal'], $idNomorLama);
            } catch (\Exception $e) {
                if ($fileBaru) { @unlink($fileBaru); }
                $kode = (int) $e->getCode();
                $this->json(array('ok' => false, 'pesan' => $e->getMessage(), 'kosong' => $kode === 409 ? array('nomorUrut') : array()), $kode === 409 ? 409 : 500);
            }
            $idNomorBaru  = $t['baru'] ? $t['id'] : 0;
            $nomorLamaRow = $t['lama'];
            $idNomor      = $t['id'];
            $nomorBast    = $t['detail_nomor'];
        } else {
            $nomorBast = ($lama && strpos((string) $lama['nomor_bast'], 'DRAFT-') === 0) ? (string) $lama['nomor_bast'] : ('DRAFT-' . $orderId);
        }

        $statusBaru = $final ? \OptiBast::ST_SELESAI : ($kirim ? \OptiBast::ST_KIRIM : ($statusLama === \OptiBast::ST_KIRIM ? \OptiBast::ST_KIRIM : \OptiBast::ST_DRAF));
        $uid = (int) ($this->getUserId() ?? 0);
        try {
            $bastId = $bastModel->simpanFormulir(array(
                'order_id' => $orderId, 'po_id' => !empty($order['po_id']) ? (int) $order['po_id'] : null,
                'nomor_bast' => $nomorBast, 'nomor_urut' => $v['nomorUrut'], 'id_nomor_urut' => $idNomor,
                'tanggal_bast' => $v['tanggal'],
                'pihak_pertama_nama' => $v['ttdNama'], 'pihak_pertama_jabatan' => $v['ttdJabatan'],
                'pihak_pertama_perusahaan' => $v['perusahaan'], 'pihak_pertama_alamat' => $v['alamat'],
                'pihak_kedua_nama' => $v['keduaNama'], 'pihak_kedua_jabatan' => $v['keduaJabatan'], 'pihak_kedua_nip' => $v['keduaNip'],
                'judul_pekerjaan' => $v['judul'], 'mengetahui_nama' => $v['mengetahuiNama'], 'mengetahui_jabatan' => $v['mengetahuiJabatan'],
                'pks_nomor' => $v['pksNomor'], 'pks_tanggal' => $v['pksTanggal'] !== '' ? $v['pksTanggal'] : null,
                'file_dokumen_bast' => $fileDokumen, 'status_bast' => $statusBaru,
            ), $lama ? (int) $lama['id'] : null);
        } catch (\Throwable $e) {
            if ($fileBaru) { @unlink($fileBaru); }
            if ($idNomorBaru) { $bastModel->lepasNomor($idNomorBaru); }
            elseif ($nomorLamaRow) { $bastModel->pulihkanNomor($nomorLamaRow); }
            $this->json(array('ok' => false, 'pesan' => 'Gagal menyimpan BAST. Detail: ' . $e->getMessage()), 500);
        }

        $unama = (string) ($_SESSION['nama_lengkap'] ?? 'Pengguna');
        if ($kirim) {
            try { $bastModel->tandaiDikirim($bastId, $uid); } catch (\Throwable $e) { error_log('[BAST] tandaiDikirim: ' . $e->getMessage()); }
            try { $this->logActivity($orderId, 'bast', 'kirim_bast_humas', 'Kirim BAST ke Humas (menunggu nomor)'); } catch (\Throwable $e) {}
            $this->kabariHumasBast($order, $unama);
        }

        $pertamaKali = !($lama && $statusLama === \OptiBast::ST_SELESAI);
        if ($final) {
            try {
                $this->logActivity($orderId, 'bast', 'terbitkan_bast', ($pertamaKali ? 'Terbitkan' : 'Perbarui') . " BAST No. {$nomorBast} (Selesai)");
            } catch (\Throwable $e) {}
            // Selesai BAST = order selesai (hanya sekali; BAST yang sudah menutup order tidak menutup ulang)
            $sudahTutup = ($lama && ($lama['status_penutupan'] ?? '') === 'selesai_diarsipkan');
            if (!$sudahTutup) {
                try {
                    $bastModel->tutupOrder($bastId, $orderId, 'Order ditutup otomatis saat BAST diselesaikan.', $uid);
                    $this->logActivity($orderId, 'bast', 'tutup_order', 'Selesaikan order (Closing Order) - BAST diselesaikan');
                } catch (\Throwable $e) {
                    $this->json(array('ok' => false, 'pesan' => 'BAST tersimpan, tetapi order gagal ditutup: ' . $e->getMessage()), 500);
                }
            }
            if ($pertamaKali) {
                $this->kabari($order, 'BAST Resmi Diterbitkan', "Dokumen BAST No. {$nomorBast} untuk Order #{$order['nomor_order']} {$order['nama_perusahaan']} telah diterbitkan.", 'bi-file-earmark-check-fill');
                $this->kabari($order, 'Order Selesai & Ditutup (Closing)', "Order #{$order['nomor_order']} {$order['nama_perusahaan']} berhasil ditutup dan diarsipkan secara resmi. Seluruh siklus layanan tuntas.", 'bi-check-all');
                // Konfirmasi juga ke Ketua Pelaksana PO
                $ketupelId = (int) ($order['ketua_pelaksana_id'] ?? 0);
                if ($ketupelId > 0 && $ketupelId !== (int) $this->getUserId()) {
                    try {
                        \NotificationService::send($this->db, array(
                            'order_id' => $orderId, 'target_user_id' => $ketupelId, 'target_role' => 'tim_kerja',
                            'target_layanan' => $order['jenis_layanan_opti'] ?? 'semua', 'judul' => 'BAST Resmi Diterbitkan',
                            'pesan' => "Dokumen BAST No. {$nomorBast} untuk Order #{$order['nomor_order']} {$order['nama_perusahaan']} telah berhasil diterbitkan.",
                            'tipe' => 'success', 'icon' => 'bi-file-earmark-check-fill', 'link_url' => "/order/{$orderId}",
                            'created_by' => (int) $this->getUserId(), 'created_by_name' => $unama,
                        ));
                    } catch (\Throwable $e) {}
                }
            }
        }

        $statusKeluar = $final ? 'selesai' : ($statusBaru === \OptiBast::ST_KIRIM ? 'dikirim' : 'draft');
        $this->json(array(
            'ok' => true, 'status' => $statusKeluar, 'nomor_bast' => $final ? $nomorBast : '',
            'nomor_terbit' => (bool) $final,
            'fileUrl' => $fileDokumen ? ($f3->get('BASE') . '/' . $fileDokumen) : '',
            'redirect' => $f3->get('BASE') . ($mode === 'humas' ? '/humas/bast?tab=selesai' : '/order/' . $orderId),
        ));
    }

    /** Beri tahu Humas (lonceng) bahwa Ketua Tim mengirim BAST. Gagal kirim tidak menggagalkan penyimpanan. */
    protected function kabariHumasBast($order, $pengirim) {
        $orderId = (int) $order['id'];
        try {
            \NotificationService::send($this->db, array(
                'order_id' => $orderId, 'target_role' => 'humas', 'target_layanan' => 'semua',
                'judul' => 'BAST Menunggu Nomor',
                'pesan' => 'Ketua Tim ' . $pengirim . ' mengirim BAST Order #' . (string) ($order['nomor_order'] ?? $orderId) . ' ' . (string) ($order['nama_perusahaan'] ?? '') . '. Isi nomor urut dan pilih pejabat yang mengetahui.',
                'tipe' => 'primary', 'icon' => 'bi-file-earmark-text', 'link_url' => '/humas/bast',
                'created_by' => (int) ($this->getUserId() ?? 0), 'created_by_name' => $pengirim,
            ));
        } catch (\Throwable $e) {
            error_log('[BAST] notifikasi Humas gagal: ' . $e->getMessage());
        }
    }

    /**
     * Halaman BAST di menu Humas: daftar BAST yang sudah dikirim Ketua Tim (Perlu Nomor) dan yang sudah selesai.
     * Route: GET /humas/bast
     */
    public function halamanHumas($f3) {
        $this->requireAuth();
        $role = (string) $this->getUserRole();
        if (!$this->isSuperadmin() && $role !== 'humas' && $role !== 'admin_kontrak') {
            $f3->error(403, 'Halaman BAST hanya untuk Humas, Admin PKS, dan Superadmin.');
            exit;
        }
        \OptiBast::pastikanSkema($this->db);
        $base = (string) $f3->get('BASE');
        // Data Daftar Order untuk kolom tabel (nomor, tanggal order, saluran, durasi, estimasi biaya): sumbernya sama dengan halaman PKS
        $rowsPerlu = \OptiBast::daftarHumas($this->db, \OptiBast::ST_KIRIM);
        $rowsSelesai = \OptiBast::daftarHumas($this->db, \OptiBast::ST_SELESAI);
        $rowsDraf = \OptiBast::daftarHumas($this->db, \OptiBast::ST_DRAF);   // BAST yang masih disusun Ketua Tim (belum dikirim)
        $infoOrder = array();
        try {
            $ids = array();
            foreach (array_merge($rowsPerlu, $rowsSelesai, $rowsDraf) as $r) { if (!empty($r['order_id'])) { $ids[] = (int) $r['order_id']; } }
            if ($ids) {
                $om = new \OrderLayanan($this->db);
                foreach ($om->allWithRelasi('', '', '', 'all', 'semua', 'all', array_values(array_unique($ids))) as $ro) { $infoOrder[(int) $ro['id']] = $ro; }
            }
        } catch (\Throwable $e) { $infoOrder = array(); }
        $petaMedia = array(
            'whatsapp' => array('WhatsApp', 'bi-chat-dots'), 'telepon' => array('Telepon', 'bi-telephone'), 'email' => array('E-mail', 'bi-envelope-at'),
            'fax' => array('Fax', 'bi-printer'), 'datang_langsung' => array('Datang Langsung', 'bi-building'), 'website' => array('Website', 'bi-laptop'),
        );
        $petakan = function ($rows) use ($base, $infoOrder, $petaMedia) {
            $h = array();
            foreach ($rows as $r) {
                $oid = (int) $r['order_id'];
                $ro = isset($infoOrder[$oid]) ? $infoOrder[$oid] : null;
                $pm = $petaMedia[$ro ? (string) ($ro['media_permohonan'] ?? '') : ''] ?? array('Surat', 'bi-envelope-paper');
                $acuan = $ro ? (float) (($ro['estimasi_biaya'] ?? 0) ?: ($ro['biaya_po'] ?? 0)) : 0.0;
                $bayar = $ro ? (float) ($ro['total_terbayar'] ?? 0) : 0.0;
                $lunas = $ro ? !empty($ro['bayar_lunas']) : false;
                $h[] = array(
                    'layanan_kode' => (string) $r['jenis_layanan_opti'],
                    'tanggal_order' => ($ro && !empty($ro['tanggal_masuk'])) ? date('d/m/Y', strtotime($ro['tanggal_masuk'])) : '',
                    'nomor_permintaan' => $ro ? (string) ($ro['nomor_permintaan'] ?? '') : '',
                    'media_label' => $pm[0], 'media_icon' => $pm[1],
                    'punya_info_order' => $ro ? true : false,
                    'durasi_ditentukan' => $ro ? !empty($ro['durasi_ditentukan']) : false,
                    'durasi_tampil' => $ro ? (string) ($ro['durasi_tampil'] ?? '') : '',
                    'durasi_badge_class' => $ro ? (string) ($ro['durasi_badge_class'] ?? '') : '',
                    'durasi_tooltip' => $ro ? (string) ($ro['durasi_tooltip'] ?? '') : '',
                    'eb_acuan' => number_format($acuan, 0, ',', '.'),
                    'eb_terbayar' => $bayar > 0 ? number_format($bayar, 0, ',', '.') : '',
                    'eb_lunas' => $lunas,
                    'eb_sisa' => (!$lunas && $bayar > 0 && $ro) ? number_format((float) ($ro['sisa_tagihan_order'] ?? 0), 0, ',', '.') : '',
                    // Keterangan yang sama persis dengan kolom Keterangan di Daftar Order (dihitung OrderLayanan::resolveStageStatus)
                    'ket_pelaksana' => $ro ? (string) ($ro['keterangan_pelaksana'] ?? '') : '',
                    'ket_aksi' => $ro ? (string) (($ro['keterangan_sub'] ?? '') !== '' ? $ro['keterangan_sub'] : ($ro['keterangan_aksi'] ?? '')) : '',
                    'ket_detail' => $ro ? (string) ($ro['keterangan_detail'] ?? '') : '',
                    'ket_baca' => $ro ? (string) ($ro['keterangan_baca'] ?? '') : '',
                    'ket_baca_status' => $ro ? (string) ($ro['po_baca_status'] ?? '') : '',
                    'selesai_at' => !empty($r['updated_at']) ? (string) $r['updated_at'] : '',
                    'id' => (int) $r['id'], 'order_id' => $oid, 'nomor_order' => (string) $r['nomor_order'],
                    'perusahaan' => (string) $r['pihak_pertama_perusahaan'], 'judul' => (string) $r['judul_pekerjaan'],
                    'layanan' => ucwords(str_replace('_', ' ', (string) $r['jenis_layanan_opti'])),
                    'katim' => (string) $r['pihak_kedua_nama'], 'pengirim' => (string) ($r['pengirim'] ?? ''),
                    'pks_nomor' => (string) $r['pks_nomor'],
                    'dikirim_at' => !empty($r['dikirim_at']) ? (string) $r['dikirim_at'] : '',
                    'dibuka_at' => !empty($r['dibuka_humas_at']) ? (string) $r['dibuka_humas_at'] : '',
                    'nomor_bast' => (string) $r['nomor_bast'],
                    'tanggal_bast' => !empty($r['tanggal_bast']) ? substr((string) $r['tanggal_bast'], 0, 10) : '',
                    'url_isi' => $base . '/order/' . $oid . '/bast/buat',
                    'url_unduh' => $base . '/order/' . $oid . '/bast/unduh',
                    'url_order' => $base . '/order/' . $oid,
                );
            }
            return $h;
        };
        $data = array('perlu' => $petakan($rowsPerlu), 'draft' => $petakan($rowsDraf), 'selesai' => $petakan($rowsSelesai));
        $tab = (string) $f3->get('GET.tab');
        $f3->set('bast_humas_json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('bast_slide_json', '{}');   // slide BAST dipakai untuk banyak order: datanya diambil per order saat tombol Lihat BAST diklik
        $f3->set('bast_humas_tab', json_encode(in_array($tab, array('semua', 'draft', 'selesai'), true) ? $tab : 'perlu'));
        $this->render('humas/bast.html', 'BAST', 'bast_humas');
    }

    /** Notifikasi ke Tim Mitra dan Ketua Tim layanan order ini. Gagal kirim tidak menggagalkan penyimpanan. */
    protected function kabari($order, $judul, $pesan, $ikon) {
        $orderId = (int) $order['id'];
        foreach (array(array('tim_mitra', 'semua'), array('ketua_tim', $order['jenis_layanan_opti'] ?? 'semua')) as $t) {
            try {
                \NotificationService::send($this->db, array(
                    'order_id' => $orderId, 'target_role' => $t[0], 'target_layanan' => $t[1], 'judul' => $judul, 'pesan' => $pesan,
                    'tipe' => 'success', 'icon' => $ikon, 'link_url' => "/order/{$orderId}",
                    'created_by' => (int) $this->getUserId(), 'created_by_name' => $_SESSION['nama_lengkap'] ?? 'Tim Mitra',
                ));
            } catch (\Throwable $e) {}
        }
    }

    /* ---------- input & data PKS ---------- */

    protected function bacaInput($f3) {
        $h = array();
        foreach (array('perusahaan', 'ttdNama', 'ttdJabatan', 'pksNomor', 'keduaNama', 'keduaJabatan', 'keduaNip', 'mengetahuiNama', 'mengetahuiJabatan') as $id) {
            $h[$id] = $this->bersihTeks($f3->get('POST.' . $id), self::BATAS[$id], false);
        }
        foreach (array('alamat', 'judul') as $id) {
            $h[$id] = $this->bersihTeks($f3->get('POST.' . $id), self::BATAS[$id], true);
        }
        $h['nomorUrut']  = $this->urutBersih((string) $f3->get('POST.nomorUrut'));
        $h['tanggal']    = $this->tanggalValid((string) $f3->get('POST.tanggal'));
        $h['pksTanggal'] = $this->tanggalValid((string) $f3->get('POST.pksTanggal'));
        return $h;
    }

    protected function bersihTeks($v, $maks, $baris) {
        if (is_array($v) || $v === null) { return ''; }
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v);
        if ($baris) {
            $s = str_replace(array("\r\n", "\r"), "\n", $s);
            $s = implode("\n", array_map('trim', explode("\n", $s)));
            $s = trim($s);
        } else {
            $s = trim(preg_replace('/\s+/u', ' ', $s));
        }
        return mb_substr($s, 0, $maks, 'UTF-8');
    }

    /** Urut dari isian: angka saja, tanpa nol di depan, maksimal 9 digit ('' kalau kosong atau 0). */
    protected function urutBersih($v) {
        return substr(ltrim(preg_replace('/\D+/', '', (string) $v), '0'), 0, 9);
    }

    protected function tanggalValid($s) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $s, $m)) { return ''; }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? (string) $s : '';
    }

    /** PKS selesai milik order ini (via PO kegiatan), atau null (order lama tanpa PKS: isian diketik manual). */
    protected function ambilPks($orderId) {
        try {
            \PoSchema::pastikan($this->db);
            $r = $this->db->exec(
                "SELECT k.* FROM pks_kontrak k JOIN po_kegiatan p ON p.id = k.po_id WHERE p.order_id = ?" . \PoSchema::bukanSertifikasi('p') . " AND k.status = 'selesai' ORDER BY k.id DESC LIMIT 1",
                array(1 => (int) $orderId)
            );
            return !empty($r) ? $r[0] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Nomor perjanjian versi klien (Pihak Pertama) dari PKS; kosong / strip dianggap tidak diisi. */
    protected function nomorPihakPertama($pks) {
        $n = trim((string) (is_array($pks) && isset($pks['nomor_pihak_pertama']) ? $pks['nomor_pihak_pertama'] : ''));
        return ($n === '-' ) ? '' : $n;
    }

    protected function namaPerusahaan($pks, $order) {
        $n = trim((string) $pks['perusahaan']);
        return $n !== '' ? $n : trim((string) ($order['nama_perusahaan'] ?? ''));
    }

    /** Teks berformat PKS (b/i/u) -> teks polos, satu poin per baris. */
    protected function teksPolos($v) {
        try {
            $baris = array();
            foreach (\PksDocx::barisDariTeks((string) $v) as $b) {
                $t = \PksDocx::teksBaris($b);
                if (trim($t) !== '') { $baris[] = $t; }
            }
            return implode("\n", $baris);
        } catch (\Throwable $e) {
            return trim(strip_tags((string) $v));
        }
    }

    /** Pejabat dari master penandatangan (database sekretariat), hanya yang showhide = 'show'. NIP ikut kalau kolomnya ada. */
    protected function daftarPejabat() {
        try {
            $sumber = $this->dbSekretariat ?: $this->db;
            $rows = $sumber->exec("SELECT * FROM mst_penandatangan_lhu WHERE showhide = 'show' ORDER BY id_penandatangan DESC");
        } catch (\Throwable $e) {
            return array();
        }
        $hasil = array(); $lihat = array();
        foreach ($rows as $r) {
            $nama = trim(preg_replace('/\s+/', ' ', strip_tags((string) $r['nama'])));
            $jab = preg_replace('~<br\s*/?>~i', ' ', str_ireplace('&nbsp;', ' ', (string) $r['jabatan']));
            $jab = trim(preg_replace('/\s+/', ' ', strip_tags($jab)), " \t\n\r\0\x0B,");
            $jab = trim(preg_replace('/^a\.?\s?n\.?\s+/i', '', $jab));
            if ($nama === '' || $jab === '') { continue; }
            $kunci = strtolower($nama . '|' . $jab);
            if (isset($lihat[$kunci])) { continue; }
            $lihat[$kunci] = true;
            $nip = '';
            foreach (array('nip', 'NIP', 'nip_penandatangan') as $kn) {
                if (isset($r[$kn]) && trim((string) $r[$kn]) !== '') { $nip = trim(preg_replace('/\s+/', ' ', strip_tags((string) $r[$kn]))); break; }
            }
            $hasil[] = array('id' => (int) $r['id_penandatangan'], 'nama' => $nama, 'jabatan' => $jab, 'nip' => $nip);
        }
        return $hasil;
    }

    protected function pilihKepala(array $pejabat) {
        foreach ($pejabat as $p) {
            if (preg_match('/^(?:p\.?l\.?[ht]\.?\s*)?kepala(?:\s+balai.*)?$/i', $p['jabatan'])) {
                return array('nama' => $p['nama'], 'jabatan' => $p['jabatan']);
            }
        }
        return array('nama' => '', 'jabatan' => '');
    }

    protected function izinkanJson($izin) {
        $this->requireAuth();
        if (!$this->hasPermission($izin)) {
            $this->json(array('ok' => false, 'pesan' => 'Anda tidak punya akses untuk tindakan ini.'), 403);
        }
    }

    protected function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected static function terbilang($n) {
        $n = (int) floor($n);
        return $n === 0 ? 'nol' : self::tb($n);
    }

    protected static function tb($x) {
        static $satuan = array('', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas');
        if ($x < 12)   { return $satuan[$x]; }
        if ($x < 20)   { return self::tb($x - 10) . ' belas'; }
        if ($x < 100)  { return self::tb((int) floor($x / 10)) . ' puluh' . ($x % 10 ? ' ' . self::tb($x % 10) : ''); }
        if ($x < 200)  { return 'seratus' . ($x - 100 ? ' ' . self::tb($x - 100) : ''); }
        if ($x < 1000) { return self::tb((int) floor($x / 100)) . ' ratus' . ($x % 100 ? ' ' . self::tb($x % 100) : ''); }
        if ($x < 2000) { return 'seribu' . ($x - 1000 ? ' ' . self::tb($x - 1000) : ''); }
        return self::tb((int) floor($x / 1000)) . ' ribu' . ($x % 1000 ? ' ' . self::tb($x % 1000) : '');
    }

    /**
     * Memproses Penutupan & Pengarsipan Order (Closing Order)
     * Route: POST /order/@id/bast/tutup
     */
    public function tutupOrder($f3, $params) {
        $this->requirePermission('order:edit', '/order');

        $orderId = (int)($params['id'] ?? 0);
        $post    = $f3->get('POST');
        $catatan = trim($post['catatan_penutupan'] ?? 'Seluruh rangkaian pekerjaan jasa layanan telah selesai, BAST ditandatangani dan diarsipkan.');
        $userId  = (int)($this->getUserId() ?? 1);

        if (!$this->isTimMitra() && !$this->isAdminOrder() && !$this->isSuperadmin()) {
            $this->setFlashError('Akses ditolak: penutupan order hanya dapat dilakukan oleh Tim Mitra Industri.');
            $f3->reroute('/order/' . $orderId);
            return;
        }

        try {
            $bastModel = new OptiBast($this->db);
            $bast = $bastModel->getByOrderId($orderId);
            $orderModel = new OrderLayanan($this->db);
            $order = $orderModel->getDetail($orderId);
            $statusOrder = strtolower(trim((string)($order['status'] ?? '')));
            $statusBast  = strtolower(trim((string)($bast['status_bast'] ?? '')));
            if (($statusOrder === 'selesai' || $statusBast === 'selesai') && !$this->canEditSubmittedData(true, $orderId)) {
                throw new \Exception('Order / BAST sudah selesai dan dikunci. Hubungi Superadmin untuk membuka izin edit.');
            }

            if (!$this->poTtdSudahDiunggah($orderId) && !$this->canEditSubmittedData(true, $orderId)) {
                throw new \Exception('Order belum bisa ditutup: upload dulu PO yang sudah ditandatangani, lalu tunggu Humas membuat kontrak (Tahap 8).');
            }
            if ($bast && $statusBast !== 'selesai' && !$this->isSuperadmin()) {
                throw new \Exception('Order belum bisa ditutup: BAST belum diberi nomor oleh Humas.');
            }

            if (!$bast) {
                // Buat BAST otomatis jika belum ada sebelum closing
                $bastId = $bastModel->simpanBaru(array(
                    'order_id'              => $orderId,
                    'po_id'                 => !empty($order['po_id']) ? (int)$order['po_id'] : null,
                    'nomor_bast'            => $bastModel->generateNomorBast(),
                    'tanggal_bast'         => date('Y-m-d'),
                    'pihak_pertama_nama'    => 'Kepala BBSPJIS',
                    'pihak_pertama_jabatan' => 'Kepala Balai Besar',
                    'pihak_kedua_nama'      => $order['pic'] ?: 'Pimpinan Mitra',
                    'pihak_kedua_jabatan'   => 'Pimpinan Perusahaan',
                    'judul_pekerjaan'       => $order['judul_kegiatan'],
                    'ringkasan_serah_terima'=> 'Serah terima hasil pekerjaan jasa layanan balai.',
                    'status_bast'           => 'selesai'
                ));
            } else {
                $bastId = (int)$bast['id'];
            }

            $bastModel->tutupOrder($bastId, $orderId, $catatan, $userId);

            $this->logActivity($orderId, 'bast', 'tutup_order', "Selesaikan order (Closing Order)" . (!empty($catatan) ? " - {$catatan}" : ""));

            // Kirim notifikasi Closing ke Tim Mitra & Ka Tim OPTI
            try {
                $nomorOrderStr = $order['nomor_order'] ?? $orderId;
                $perusahaanStr = $order['nama_perusahaan'] ?? 'Klien';
                \NotificationService::send($this->db, [
                    'order_id'        => $orderId,
                    'target_role'     => 'tim_mitra',
                    'target_layanan'  => 'semua',
                    'judul'           => 'Order Selesai & Ditutup (Closing)',
                    'pesan'           => "Order #{$nomorOrderStr} {$perusahaanStr} berhasil ditutup dan diarsipkan secara resmi. Seluruh siklus layanan tuntas.",
                    'tipe'            => 'success',
                    'icon'            => 'bi-check-all',
                    'link_url'        => "/order/{$orderId}",
                    'created_by'      => $userId,
                    'created_by_name' => $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                ]);

                \NotificationService::send($this->db, [
                    'order_id'        => $orderId,
                    'target_role'     => 'ketua_tim',
                    'target_layanan'  => $order['jenis_layanan_opti'] ?? 'semua',
                    'judul'           => 'Order Selesai & Ditutup (Closing)',
                    'pesan'           => "Order #{$nomorOrderStr} {$perusahaanStr} telah ditutup dan diarsipkan secara resmi.",
                    'tipe'            => 'success',
                    'icon'            => 'bi-check-all',
                    'link_url'        => "/order/{$orderId}",
                    'created_by'      => $userId,
                    'created_by_name' => $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                ]);

                // Kirim notifikasi penutupan order juga ke Ketua Pelaksana PO
                $ketupelId = (int)($order['ketua_pelaksana_id'] ?? 0);
                if ($ketupelId > 0 && $ketupelId !== (int)$userId) {
                    \NotificationService::send($this->db, [
                        'order_id'        => $orderId,
                        'target_user_id'  => $ketupelId,
                        'target_role'     => 'tim_kerja',
                        'target_layanan'  => $order['jenis_layanan_opti'] ?? 'semua',
                        'judul'           => 'Order Selesai & Ditutup (Closing)',
                        'pesan'           => "Order #{$nomorOrderStr} {$perusahaanStr} telah selesai secara keseluruhan, ditutup dan diarsipkan secara resmi. Terima kasih atas kinerjanya!",
                        'tipe'            => 'success',
                        'icon'            => 'bi-check-all',
                        'link_url'        => "/order/{$orderId}",
                        'created_by'      => $userId,
                        'created_by_name' => $_SESSION['nama_lengkap'] ?? 'Tim Mitra'
                    ]);
                }
            } catch (\Exception $eNotif) {}

            $this->setFlashSuccess("Order Layanan #{$orderId} berhasil DITUTUP & DIARSIPKAN (Selesai). Seluruh siklus layanan tuntas!");
            $f3->reroute("/order/{$orderId}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menutup order: ' . $e->getMessage());
            $f3->reroute("/order/{$orderId}");
        }
    }

    /**
     * Syarat BAST: PO kegiatan order ini sudah punya file PO bertanda tangan (di-upload Ketua Tim di Tahap 8)
     * DAN Humas sudah menandai kontraknya dibuat (kontrak_dibuat_at). Order yang memakai PO versi lama (tanpa baris po_kegiatan) tidak dikunci.
     */
    protected function poTtdSudahDiunggah($orderId) {
        try {
            \PoSchema::pastikan($this->db);
            $r = $this->db->exec(
                'SELECT id, po_ttd_file, kontrak_dibuat_at FROM po_kegiatan WHERE order_id = ?' . \PoSchema::bukanSertifikasi('') . ' ORDER BY id DESC LIMIT 1',
                array(1 => (int) $orderId)
            );
        } catch (\Throwable $e) {
            return false;
        }
        if (empty($r)) {
            try {
                $ord = $this->db->exec('SELECT po_id FROM order_layanan WHERE id = ? LIMIT 1', array(1 => (int) $orderId));
                return !empty($ord[0]['po_id']);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return !empty($r[0]['po_ttd_file']) && !empty($r[0]['kontrak_dibuat_at']) && $this->laporanAkhirSelesai($orderId);
    }

    /**
     * LAPORAN AKHIR (ditambahkan Kaa): syarat BAST berikutnya, tahap Laporan Akhir (di antara PKS dan BAST) sudah diselesaikan
     * (file laporan boleh kosong). Order lama (sudah berstatus selesai / pelaksanaan selesai) tidak dikunci.
     */
    protected function laporanAkhirSelesai($orderId) {
        try {
            \OrderLayanan::ensureColumns($this->db);
            $r = $this->db->exec('SELECT status, status_pelaksanaan, laporan_akhir_selesai_at FROM order_layanan WHERE id = ? LIMIT 1', array(1 => (int) $orderId));
        } catch (\Throwable $e) {
            return false;
        }
        if (empty($r)) { return false; }
        return !empty($r[0]['laporan_akhir_selesai_at']) || ($r[0]['status'] ?? '') === 'selesai' || ($r[0]['status_pelaksanaan'] ?? '') === 'laporan_selesai';
    }
}
