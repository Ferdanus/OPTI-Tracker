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
                        'pksTanggal' => 'Tanggal PKS', 'keduaNama' => 'Nama Pihak Kedua', 'keduaJabatan' => 'Jabatan Pihak Kedua',
                        'mengetahuiNama' => 'Pejabat yang mengetahui', 'mengetahuiJabatan' => 'Jabatan pejabat yang mengetahui');
    /** Isian yang dikunci dari PKS kalau PKS-nya ada */
    const DARI_PKS = array('perusahaan', 'alamat', 'ttdNama', 'ttdJabatan', 'judul', 'pksNomor', 'pksTanggal');

    /**
     * Menampilkan form pembuatan / edit BAST (form + pratinjau dokumen live)
     * Route: GET /order/@id/bast/buat
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

        $bastModel = new OptiBast($this->db);
        $bast = $bastModel->getByOrderId($orderId);

        if (empty($bast) && !$this->poTtdSudahDiunggah($orderId)) {
            $this->setFlashError('BAST belum bisa dibuat: upload dulu PO yang sudah ditandatangani, lalu tunggu Humas membuat PKS (Tahap 9).');
            $f3->reroute("/order/{$orderId}");
            return;
        }

        $pks = $this->ambilPks($orderId);
        $pejabat = $this->daftarPejabat();
        $kepala = $this->pilihKepala($pejabat);

        $v = array(
            'nomorUrut' => '', 'tanggal' => date('Y-m-d'),
            'perusahaan' => '', 'alamat' => '', 'ttdNama' => '', 'ttdJabatan' => '', 'judul' => '', 'pksNomor' => '', 'pksTanggal' => '',
            'keduaNama' => '', 'keduaJabatan' => '', 'keduaNip' => '',
            'mengetahuiNama' => $kepala['nama'], 'mengetahuiJabatan' => $kepala['jabatan'],
        );
        if ($pks) {
            $v['perusahaan'] = $this->namaPerusahaan($pks, $order);
            $v['alamat']     = $this->teksPolos($pks['alamat_perusahaan']);
            $v['ttdNama']    = (string) $pks['ttd_nama'];
            $v['ttdJabatan'] = (string) $pks['ttd_jabatan'];
            $v['judul']      = $this->teksPolos($pks['judul']);
            $v['pksNomor']   = (string) $pks['nomor_pks'];
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
            $status = ($bast['status_bast'] === 'selesai') ? 'selesai' : 'draft';
            $v['nomorUrut'] = (string) ($bast['nomor_urut'] ?? '');
            if (!empty($bast['tanggal_bast'])) { $v['tanggal'] = substr((string) $bast['tanggal_bast'], 0, 10); }
            if ($status === 'selesai' && !empty($bast['id_nomor_urut'])) { $nomorTerbit = (string) $bast['nomor_bast']; }
            $peta = array('perusahaan' => 'pihak_pertama_perusahaan', 'alamat' => 'pihak_pertama_alamat', 'ttdNama' => 'pihak_pertama_nama', 'ttdJabatan' => 'pihak_pertama_jabatan',
                          'judul' => 'judul_pekerjaan', 'pksNomor' => 'pks_nomor', 'keduaNama' => 'pihak_kedua_nama', 'keduaJabatan' => 'pihak_kedua_jabatan',
                          'keduaNip' => 'pihak_kedua_nip', 'mengetahuiNama' => 'mengetahui_nama', 'mengetahuiJabatan' => 'mengetahui_jabatan');
            foreach ($peta as $id => $kol) {
                if ($pks && in_array($id, self::DARI_PKS, true)) { continue; }   // PKS jadi sumber kebenaran
                if (isset($bast[$kol]) && trim((string) $bast[$kol]) !== '') { $v[$id] = (string) $bast[$kol]; }
            }
            if (!$pks && !empty($bast['pks_tanggal'])) { $v['pksTanggal'] = substr((string) $bast['pks_tanggal'], 0, 10); }
            if (!empty($bast['file_dokumen_bast'])) { $fileUrl = $f3->get('BASE') . '/' . $bast['file_dokumen_bast']; }
        }

        $data = array(
            'order' => array('id' => $orderId, 'nomor' => (string) ($order['nomor_order'] ?? $orderId), 'perusahaan' => (string) ($order['nama_perusahaan'] ?? '')),
            'status' => $status,
            'nomorTerbit' => $nomorTerbit,
            'pksAda' => (bool) $pks,
            'vals' => $v,
            'pejabat' => $pejabat,
            'fileUrl' => $fileUrl,
        );
        $f3->set('bast_json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
        $f3->set('order', $order);

        // Audit Tahap 9: BAST dibuka/dikelola Tim Mitra (First Time Only)
        $sessionUser = $f3->get('SESSION.user') ?? [];
        $userId = (int)($sessionUser['id'] ?? ($this->getUserId() ?? 0));
        $userNama = $sessionUser['nama'] ?? ($sessionUser['nama_lengkap'] ?? 'Tim Mitra');
        if ($userId > 0 && $orderId > 0) {
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

        $this->render('bast/form.html', "Berita Acara Serah Terima - Order #{$order['nomor_order']}", 'order');
    }

    /** POST /order/@id/bast/draft */
    public function simpanBast($f3, $params) {
        $this->prosesSimpan($f3, (int)($params['id'] ?? 0), false);
    }

    /** POST /order/@id/bast/selesai */
    public function selesaiBast($f3, $params) {
        $this->prosesSimpan($f3, (int)($params['id'] ?? 0), true);
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
            'kedua_nip_jabatan'  => ($nip !== '' ? 'NIP. ' . $nip . ($jab !== '' ? ' / ' : '') : '') . $jab,
            'judul'              => (string) $b['judul_pekerjaan'],
            'nomor_pks'          => (string) $b['pks_nomor'],
            'tanggal_pks'        => $tsPks ? ((int) date('j', $tsPks) . ' ' . OptiBast::BULAN[(int) date('n', $tsPks) - 1] . ' ' . date('Y', $tsPks)) : '',
            'mengetahui_jabatan' => (string) $b['mengetahui_jabatan'],
            'mengetahui_nama'    => (string) $b['mengetahui_nama'],
        );
    }

    /**
     * Simpan BAST (draft atau selesai). Selesai: nomor didaftarkan (bast_nomor_urut), BAST berstatus selesai,
     * dan order otomatis ditutup (Order Selesai) lewat OptiBast::tutupOrder.
     */
    protected function prosesSimpan($f3, $orderId, $final) {
        $this->izinkanJson('order:edit');
        \OptiBast::pastikanSkema($this->db);

        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);
        if (!$order) { $this->json(array('ok' => false, 'pesan' => "Order #{$orderId} tidak ditemukan."), 404); }

        $bastModel = new OptiBast($this->db);
        $lama = $bastModel->getByOrderId($orderId);
        if (empty($lama) && !$this->poTtdSudahDiunggah($orderId)) {
            $this->json(array('ok' => false, 'pesan' => 'BAST belum bisa dibuat: upload dulu PO bertanda tangan, lalu tunggu Humas membuat PKS.'), 409);
        }
        if ($lama && $lama['status_bast'] === 'selesai') { $final = true; }   // BAST yang sudah selesai tidak kembali jadi draft

        $pks = $this->ambilPks($orderId);
        $v = $this->bacaInput($f3);
        if ($pks) {   // data dari PKS dikunci, tidak bisa diubah lewat form
            $v['perusahaan'] = $this->namaPerusahaan($pks, $order);
            $v['alamat']     = $this->teksPolos($pks['alamat_perusahaan']);
            $v['ttdNama']    = (string) $pks['ttd_nama'];
            $v['ttdJabatan'] = (string) $pks['ttd_jabatan'];
            $v['judul']      = $this->teksPolos($pks['judul']);
            $v['pksNomor']   = (string) $pks['nomor_pks'];
            $v['pksTanggal'] = $pks['tanggal_perjanjian'] ? substr((string) $pks['tanggal_perjanjian'], 0, 10) : '';
        }
        if ($v['tanggal'] === '') { $v['tanggal'] = date('Y-m-d'); }

        if ($final) {
            $kosong = array(); $label = array();
            foreach (self::WAJIB as $id => $lbl) {
                if (trim((string) $v[$id]) === '') { $kosong[] = $id; $label[] = $lbl; }
            }
            if ($kosong) {
                $this->json(array('ok' => false, 'kosong' => $kosong, 'pesan' => 'Masih ada isian wajib yang kosong: ' . implode(', ', array_slice($label, 0, 4)) . (count($label) > 4 ? ', dan lainnya' : '') . '.'), 422);
            }
        }

        // ---- berkas BAST bertanda tangan (opsional) ----
        $fileDokumen = $lama ? ($lama['file_dokumen_bast'] ?: null) : null;
        $fileBaru = null;
        if (!empty($_FILES['file_bast']['name']) && (int) $_FILES['file_bast']['error'] === UPLOAD_ERR_OK) {
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
                'file_dokumen_bast' => $fileDokumen, 'status_bast' => $final ? 'selesai' : 'draft',
            ), $lama ? (int) $lama['id'] : null);
        } catch (\Throwable $e) {
            if ($fileBaru) { @unlink($fileBaru); }
            if ($idNomorBaru) { $bastModel->lepasNomor($idNomorBaru); }
            elseif ($nomorLamaRow) { $bastModel->pulihkanNomor($nomorLamaRow); }
            $this->json(array('ok' => false, 'pesan' => 'Gagal menyimpan BAST. Detail: ' . $e->getMessage()), 500);
        }

        $pertamaKali = !($lama && $lama['status_bast'] === 'selesai');
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
            }
        }

        $this->json(array(
            'ok' => true, 'status' => $final ? 'selesai' : 'draft', 'nomor_bast' => $final ? $nomorBast : '',
            'nomor_terbit' => (bool) $final,
            'fileUrl' => $fileDokumen ? ($f3->get('BASE') . '/' . $fileDokumen) : '',
            'redirect' => $f3->get('BASE') . '/order/' . $orderId,
        ));
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

        try {
            $bastModel = new OptiBast($this->db);
            $bast = $bastModel->getByOrderId($orderId);
            $orderModel = new OrderLayanan($this->db);
            $order = $orderModel->getDetail($orderId);

            if (!$bast && !$this->poTtdSudahDiunggah($orderId)) {
                throw new \Exception('Order belum bisa ditutup: upload dulu PO yang sudah ditandatangani, lalu tunggu Humas membuat kontrak (Tahap 8).');
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
            return true;
        }
        if (empty($r)) { return true; }
        return !empty($r[0]['po_ttd_file']) && !empty($r[0]['kontrak_dibuat_at']);
    }
}
