<?php
namespace petunjuk_operasional;

/**
 * PoSertifikasiController -- Petunjuk Operasional (PO) jenis SERTIFIKASI.
 *
 * Sengaja DIPISAH dari modul PO OPTI (PoKegiatanController dkk): beda folder
 * (app/controllers/petunjuk_operasional/), beda namespace, beda folder view
 * (app/views/petunjuk_operasional/po_sertifikasi/), beda URL (/petunjuk-operasional/...).
 *
 * ALUR:
 *   1. /po-kegiatan/pilih -> pilih "Sertifikasi" -> modal daftar order dari database SISERTIFIKASI
 *      (sisertifikasi2026.tb_arsip_sertifikasi).
 *   2. /petunjuk-operasional/sertifikasi/create?order=<id_arsip_sertifikasi> -> isi form PO.
 *   3. Simpan -> tabel po_kegiatan (silopti2026):
 *        - order_id = id_arsip_sertifikasi
 *        - layanan  = 'sertifikasi' (otomatis; PO OPTI diisi 'opti-lingkungan'/'opti-selulosa')
 *        - nomor_po / id_nomor_urut = dari tabel po_nomor_urut, algoritma & format SAMA dengan PO OPTI
 *          ("N/PO/BBSPJIS/ROMAWI/TAHUN"), diterbitkan SEKALI saat PO pertama kali disimpan
 *        - isi form dipecah ke kolom-kolom po_kegiatan seperti PO OPTI (dasar, tujuan, ruang_lingkup, alamat,
 *          tim_pelaksana, rab, jadwal) + kolom khusus sertifikasi (jenis, nama_perusahaan, no_penawaran,
 *          tanggal_penawaran, alamat_bagian, alamat_tampil); isian kosong disimpan NULL (lihat PoSchema::kolomDariForm)
 *      Satu order sertifikasi = satu PO; membuka create lagi untuk order yang sama diarahkan ke edit.
 *   4. Tombol di bawah form (sama seperti PO OPTI): "Simpan sebagai Draft" -> status 'draft',
 *      "Kirim" -> status 'terkirim'. Keduanya menampilkan popup (SweetAlert) lalu pindah ke daftar PO. Daftar PO-nya ada di PoIndexController (/petunjuk-operasional).
 *      Order yang sudah dibuatkan PO tidak muncul lagi di modal pilih order.
 *
 * Syarat order yang boleh dibuatkan PO Sertifikasi:
 *   1. status_penerimaan_order = 'Order'  (bukan 'Pendaftaran')
 *   2. total_nilai_kontrak = total_bayar_kontrak  (kontrak sudah terbayar penuh)
 *   3. total_nilai_kontrak > 0  (pengaman: 0 = 0 bukan berarti sudah lunas)
 *
 * Kompatibel PHP 7.2.
 */
class PoSertifikasiController extends \Controller {

    const DB_SERTIFIKASI_DEFAULT = 'sisertifikasi2026';

    /** Nama database SISERTIFIKASI. Bisa diganti lewat config.ini: db_sertifikasi_name=... */
    protected function namaDbSertifikasi() {
        $n = (string) \Base::instance()->get('db_sertifikasi_name');
        return preg_match('/^[A-Za-z0-9_]+$/', $n) ? $n : self::DB_SERTIFIKASI_DEFAULT;
    }

    /**
     * Ambil order sertifikasi yang memenuhi syarat. $id > 0 -> cek satu order saja
     * (dipakai untuk validasi di create()/simpan()). $kecualiSudahPo=true -> sembunyikan order yang sudah punya PO (daftar di modal). $hanyaLayak=false -> tanpa syarat
     * Order+lunas (dipakai saat mengedit PO yang sudah tersimpan). tb_customer otomatis diarahkan ke sil2020
     * oleh OptiDatabase; tabel sertifikasi dibaca lintas-database (server MySQL yang sama).
     */
    protected function queryOrderLayak($id = 0, $hanyaLayak = true, $kecualiSudahPo = false) {
        $dbName = $this->namaDbSertifikasi();
        $sql = "SELECT a.id_arsip_sertifikasi, a.no_id_order, a.nomor_order, a.tanggal_order, a.status_order,
                       a.no_pks, a.keterangan, a.total_nilai_kontrak, a.total_bayar_kontrak,
                       " . $this->kolomTanggalUpdate($dbName) . " AS tanggal_update,
                       c.nmcustomer, c.pt_cv, c.alamatcustomer
                FROM `{$dbName}`.`tb_arsip_sertifikasi` a
                LEFT JOIN tb_customer c ON c.id_customer = a.id_customer
                WHERE 1 = 1";
        if ($hanyaLayak) {
            $sql .= " AND a.status_penerimaan_order = 'Order'
                  AND a.total_nilai_kontrak = a.total_bayar_kontrak
                  AND a.total_nilai_kontrak > 0";
        }
        if ($kecualiSudahPo && \PoSchema::siap()) {
            // order yang sudah dibuatkan PO Sertifikasi (po_kegiatan.order_id = id_arsip_sertifikasi) tidak ditawarkan lagi
            $sql .= " AND NOT EXISTS (SELECT 1 FROM po_kegiatan pk WHERE pk.order_id = a.id_arsip_sertifikasi AND pk.layanan = '" . \PoSchema::SERTIFIKASI . "')";
        }
        $args = [];
        if ($id > 0) {
            $sql .= " AND a.id_arsip_sertifikasi = ?";
            $args[1] = (int) $id;
        }
        $sql .= " ORDER BY a.tanggal_order DESC, a.id_arsip_sertifikasi DESC";
        if ($id > 0) {
            $sql .= " LIMIT 1";
        }
        return $this->db->exec($sql, $args);
    }

    /** a.tanggal_update bila kolomnya ada di tb_arsip_sertifikasi, kalau tidak NULL (supaya query tidak error). */
    protected function kolomTanggalUpdate($dbName) {
        static $ada = null;
        if ($ada === null) {
            try {
                $r = $this->db->exec("SHOW COLUMNS FROM `{$dbName}`.`tb_arsip_sertifikasi` LIKE 'tanggal_update'");
                $ada = !empty($r);
            } catch (\Throwable $e) {
                $ada = false;
            }
        }
        return $ada ? 'a.tanggal_update' : 'NULL';
    }

    /** Tanggal valid (Y-m-d) dari nilai DATE/DATETIME, atau ''. */
    protected static function tanggalValid($v) {
        $v = (string) $v;
        return ($v !== '' && strpos($v, '0000') !== 0 && strtotime($v)) ? date('Y-m-d', strtotime($v)) : '';
    }

    /** Lunas bila total nilai kontrak = total bayar kontrak (dan > 0). */
    protected static function orderLunas(array $r) {
        $nilai = (float) ($r['total_nilai_kontrak'] ?? 0);
        return $nilai > 0 && abs($nilai - (float) ($r['total_bayar_kontrak'] ?? 0)) < 0.005;
    }

    /**
     * Kalimat poin terakhir Dasar. Lunas -> "... telah lunas pada tanggal <tanggal_update>"; sama dengan yang tampil di form.
     * $r = baris order mentah (null = order tidak terbaca -> kalimat netral).
     */
    protected function teksPembayaran($r, $perusahaan) {
        $nm = trim((string) $perusahaan);
        $dasar = 'Pembayaran biaya oleh ' . ($nm === '' ? '...' : $nm);
        if (!is_array($r)) {
            return $dasar;
        }
        if (!self::orderLunas($r)) {
            return $dasar . ' belum lunas';
        }
        $tgl = \PoSchema::tanggalIndo(self::tanggalValid($r['tanggal_update'] ?? ''));
        return $dasar . ' telah lunas' . ($tgl !== '' ? ' pada tanggal ' . $tgl : '');
    }

    protected function petakanOrder(array $r) {
        $nomor = trim((string) ($r['nomor_order'] ?? ''));
        if ($nomor === '') {
            $nomor = trim((string) ($r['no_id_order'] ?? ''));
        }
        $tgl = (string) ($r['tanggal_order'] ?? '');
        $tglFmt = '';
        if ($tgl !== '' && $tgl !== '0000-00-00' && strtotime($tgl)) {
            $tglFmt = date('d/m/Y', strtotime($tgl));
        }
        return [
            'id'            => (int) $r['id_arsip_sertifikasi'],
            'nomor_order'   => $nomor,
            'no_pks'        => trim((string) ($r['no_pks'] ?? '')),
            'mitra'         => \Customer::formatNamaPerusahaan($r['pt_cv'] ?? '', $r['nmcustomer'] ?? ''),
            'alamat'        => trim((string) ($r['alamatcustomer'] ?? '')),
            'tanggal_order' => $tglFmt,
            'status_order'  => (string) ($r['status_order'] ?? ''),
            'nilai_kontrak' => (int) ($r['total_nilai_kontrak'] ?? 0),
            'keterangan'    => trim((string) ($r['keterangan'] ?? '')),
            'lunas'         => self::orderLunas($r),
            'tanggal_bayar' => self::tanggalValid($r['tanggal_update'] ?? ''),
        ];
    }

    /**
     * GET /petunjuk-operasional/sertifikasi/order
     * JSON daftar order sertifikasi yang layak dibuatkan PO (untuk modal di /po-kegiatan/pilih).
     */
    public function daftarOrder($f3) {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->kendalaSkema(); // pastikan kolom layanan ada supaya filter 'sudah punya PO' aktif
            $rows = $this->queryOrderLayak(0, true, true);
            $data = array_map([$this, 'petakanOrder'], $rows);
            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal baca daftar order: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Gagal membaca data order dari database ' . $this->namaDbSertifikasi() . '. Pastikan database tersebut ada dan user MySQL punya akses baca.'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    protected function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function jsonHive($data) {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /** Pastikan skema po_kegiatan siap; return pesan kendala ('' = aman). */
    protected function kendalaSkema() {
        \PoSchema::pastikan($this->db);
        return \PoSchema::kendalaSertifikasi();
    }

    /** 'kirim' -> 'terkirim'; selain itu -> 'draft' (sama seperti PO OPTI). */
    protected function statusDariAksi($f3) {
        return ((string) $f3->get('POST.aksi')) === 'kirim' ? 'terkirim' : 'draft';
    }

    protected function urlIndex($tab = 'draft') {
        return \Base::instance()->get('BASE') . '/petunjuk-operasional?layanan=sertifikasi&tab=' . $tab;
    }

    protected function urlEdit($poId) {
        return \Base::instance()->get('BASE') . '/petunjuk-operasional/sertifikasi/' . (int) $poId . '/edit';
    }

    /**
     * GET /petunjuk-operasional/sertifikasi/create[?order=@id_arsip_sertifikasi]
     * Tanpa ?order= -> form kosong (mode lihat tampilan; belum bisa disimpan). Dengan ?order= -> order
     * divalidasi ulang di server (syarat sama seperti daftar) lalu data mitra mengisi form.
     * Kalau order itu SUDAH punya PO Sertifikasi -> diarahkan ke halaman edit PO tersebut.
     */
    public function create($f3) {
        $this->requireAuth();

        $orderId = (int) ($f3->get('GET.order') ?? 0);
        $order = null;
        if ($orderId > 0) {
            try {
                $rows = $this->queryOrderLayak($orderId);
            } catch (\Throwable $e) {
                error_log('[PO Sertifikasi] gagal baca order: ' . $e->getMessage());
                $rows = [];
            }
            if (empty($rows[0])) {
                $this->setFlashError('Order sertifikasi tidak ditemukan atau belum memenuhi syarat (harus berstatus Order dan kontrak sudah terbayar penuh).');
                $f3->reroute('/po-kegiatan/pilih');
                return;
            }
            $order = $this->petakanOrder($rows[0]);

            $this->kendalaSkema();
            if (\PoSchema::siap()) {
                try {
                    $ada = \PoSchema::cariPoSertifikasi($this->db, $orderId);
                    if ($ada) {
                        $f3->reroute('/petunjuk-operasional/sertifikasi/' . (int) $ada['id'] . '/edit');
                        return;
                    }
                } catch (\Throwable $e) {
                    error_log('[PO Sertifikasi] gagal cek PO yang sudah ada: ' . $e->getMessage());
                }
            }
        }

        $f3->set('order_json', $this->jsonHive($order));
        $f3->set('po_json', 'null');
        $f3->set('mode_lihat', 'false');
        $f3->set('tanggal_default', date('Y-m-d'));
        $f3->set('csrf_po', (string) ($f3->get('csrf_token') ?? ''));

        $this->render(
            'petunjuk_operasional/po_sertifikasi/create.html',
            'Buat PO Sertifikasi',
            'po_sertifikasi'
        );
    }

    /** GET /petunjuk-operasional/sertifikasi/@id/edit */
    public function edit($f3, $params) {
        return $this->tampilkan($f3, $params, false);
    }

    /** GET /petunjuk-operasional/sertifikasi/@id/lihat -- hanya melihat preview dokumen PO (tanpa form), dipakai ikon mata di daftar PO. */
    public function lihat($f3, $params) {
        return $this->tampilkan($f3, $params, true);
    }

    protected function tampilkan($f3, $params, $lihat) {
        $this->requireAuth();

        $this->kendalaSkema();
        $po = null;
        if (\PoSchema::siap()) {
            try {
                $po = \PoSchema::ambilPoSertifikasi($this->db, (int) $params['id']);
            } catch (\Throwable $e) {
                error_log('[PO Sertifikasi] gagal baca PO: ' . $e->getMessage());
            }
        }
        if (!$po) {
            $this->setFlashError('PO Sertifikasi tidak ditemukan.');
            $f3->reroute('/po-kegiatan/pilih');
            return;
        }

        $order = null;
        try {
            $rows = $this->queryOrderLayak((int) $po['order_id'], false);
            if (!empty($rows[0])) {
                $order = $this->petakanOrder($rows[0]);
            }
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal baca order PO: ' . $e->getMessage());
        }
        if ($order === null) {
            // order di SISERTIFIKASI sudah tidak terbaca -> tetap bisa dibuka dari data yang tersimpan
            $order = ['id' => (int) $po['order_id'], 'nomor_order' => '', 'no_pks' => '', 'mitra' => '', 'alamat' => (string) ($po['alamat'] ?? ''),
                      'tanggal_order' => '', 'status_order' => '', 'nilai_kontrak' => 0, 'keterangan' => '', 'lunas' => null, 'tanggal_bayar' => ''];
        }

        $data = \PoSchema::formDariBaris($po); // isi form dari kolom-kolom po_kegiatan
        $tgl = (string) ($po['tanggal_po'] ?? '');
        $poInfo = [
            'id'         => (int) $po['id'],
            'nomor'      => (string) ($po['nomor_po'] ?? ''),
            'tanggal_po' => ($tgl !== '' && strpos($tgl, '0000') !== 0 && strtotime($tgl)) ? date('Y-m-d', strtotime($tgl)) : '',
            'judul'      => (string) ($po['judul_kegiatan'] ?? ''),
            'status'     => (string) ($po['status'] ?? ''),
            'data'       => $data,
        ];

        $f3->set('order_json', $this->jsonHive($order));
        $f3->set('po_json', $this->jsonHive($poInfo));
        $f3->set('mode_lihat', $lihat ? 'true' : 'false');
        $f3->set('tanggal_default', date('Y-m-d'));
        $f3->set('csrf_po', (string) ($f3->get('csrf_token') ?? ''));

        $this->render(
            'petunjuk_operasional/po_sertifikasi/create.html',
            $lihat ? 'Preview PO Sertifikasi' : 'Edit PO Sertifikasi',
            'po_sertifikasi'
        );
    }

    /** Daftar kekurangan isian (diisi bila aksi=kirim dan ada yang belum lengkap). */
    protected $errKirim = [];

    protected static function adaIsi($v) {
        return !is_array($v) && trim((string) $v) !== '';
    }

    /** Teks polos dari isian rich text (tag, &nbsp;, spasi ganda dibuang). */
    protected static function teksRte($html) {
        $t = preg_replace('/<[^>]*>/', ' ', (string) $html);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace("\xC2\xA0", ' ', $t);
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    protected static function angka($v) {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    protected static function hurufUrut($i) {
        $s = '';
        $i = (int) $i;
        do { $s = chr(65 + ($i % 26)) . $s; $i = intdiv($i, 26) - 1; } while ($i >= 0);
        return $s;
    }

    /**
     * Aturan Kirim (dicerminkan dengan validasiKirim() di form): semua isian wajib,
     * KECUALI alamat (alamat perusahaan & bagian Alamat). Draft tidak diperiksa.
     * @return string[] pesan kekurangan; kosong = lengkap
     */
    protected function kekuranganKirim(array $post, array $d) {
        $m = [];
        $isi = function ($v) { return self::adaIsi($v); };

        if (!$isi($post['tanggal_po'] ?? '')) { $m[] = 'Tanggal Surat PO belum diisi'; }
        if (!$isi($post['judul_kegiatan'] ?? '')) { $m[] = 'Judul Kegiatan belum diisi'; }
        if (!$isi($post['jenis'] ?? '')) { $m[] = 'Jenis belum diisi'; }

        // Dasar: a. surat penawaran (nomor + tanggal; romawi & tahun otomatis dari tanggal), b dst. poin penjelasan
        $dp = (isset($d['dasarPenawaran']) && is_array($d['dasarPenawaran'])) ? $d['dasarPenawaran'] : [];
        if (!$isi($dp['nomor'] ?? '')) { $m[] = 'Poin a: nomor surat penawaran belum diisi'; }
        if (!$isi($dp['tanggal'] ?? '')) { $m[] = 'Poin a: tanggal surat penawaran belum diisi'; }
        $poin = (isset($d['dasarPoin']) && is_array($d['dasarPoin'])) ? array_values($d['dasarPoin']) : [];
        if (!$poin) { $m[] = 'Dasar: belum ada poin penjelasan (b, c, ...)'; }
        foreach ($poin as $i => $p) {
            $lbl = 'Dasar: poin ' . \PoSchema::hurufPoin($i + 1);
            $t = self::teksRte(is_array($p) ? ($p['teks'] ?? '') : '');
            if ($t === '') { $m[] = $lbl . ' belum diisi'; }
            elseif (preg_match('/\.{3}|\x{2026}/u', $t)) { $m[] = $lbl . ' masih ada titik-titik (...) yang belum diganti'; }
        }

        foreach (['tujuan' => 'Tujuan', 'ruang' => 'Ruang Lingkup'] as $k => $lbl) {
            $t = self::teksRte($d[$k] ?? '');
            if ($t === '') { $m[] = $lbl . ' belum diisi'; }
            elseif (preg_match('/\.{3}|\x{2026}/u', $t)) { $m[] = $lbl . ' masih ada titik-titik (...) yang belum diganti'; }
        }

        $rt = (isset($d['ruangTbl']) && is_array($d['ruangTbl'])) ? $d['ruangTbl'] : [];
        if (!empty($rt['aktif'])) {
            $kol = (isset($rt['kolom']) && is_array($rt['kolom'])) ? $rt['kolom'] : [];
            $rows = (isset($rt['rows']) && is_array($rt['rows'])) ? $rt['rows'] : [];
            if (!$kol) { $m[] = 'Tabel Ruang Lingkup belum punya kolom (atau matikan centang tabelnya)'; }
            else {
                foreach ($kol as $k) { if (!$isi($k['nama'] ?? '')) { $m[] = 'Tabel Ruang Lingkup: nama kolom belum diisi (atau matikan centang tabelnya)'; break; } }
            }
            if (!$rows) { $m[] = 'Tabel Ruang Lingkup belum punya baris (atau matikan centang tabelnya)'; }
            else {
                foreach ($rows as $r) {
                    $c = (isset($r['c']) && is_array($r['c'])) ? $r['c'] : [];
                    $ada = false;
                    foreach ($c as $v) { if ($isi($v)) { $ada = true; break; } }
                    if (!$ada) { $m[] = 'Tabel Ruang Lingkup: ada baris yang masih kosong (isi, hapus, atau matikan centang tabelnya)'; break; }
                }
            }
        }

        $tim = (isset($d['tim']) && is_array($d['tim'])) ? array_values($d['tim']) : [];
        if (!$tim) { $m[] = 'Tim Pelaksana belum ada anggota'; }
        foreach (['nama' => 'nama petugas', 'tugas' => 'tugas / tanggung jawab', 'uraian' => 'uraian tugas'] as $k => $lbl) {
            $bad = [];
            foreach ($tim as $i => $t) { if (!$isi(is_array($t) ? ($t[$k] ?? '') : '')) { $bad[] = $i + 1; } }
            if ($bad) { $m[] = 'Tim Pelaksana: ' . $lbl . ' belum diisi (baris ' . implode(', ', $bad) . ')'; }
        }

        $rab = (isset($d['rab']) && is_array($d['rab'])) ? $d['rab'] : [];
        $terima = (isset($rab['terima']) && is_array($rab['terima'])) ? array_values($rab['terima']) : [];
        $induk = (isset($rab['induk']) && is_array($rab['induk'])) ? array_values($rab['induk']) : [];
        if (!$terima) { $m[] = 'RAB: penerimaan belum ada'; }
        else {
            $tot = 0.0; $bad = [];
            foreach ($terima as $i => $t) {
                $t = is_array($t) ? $t : [];
                $tot += self::angka($t['nilai'] ?? 0);
                if (!$isi($t['nama'] ?? '')) { $bad[] = $i + 1; }
            }
            if ($bad) { $m[] = 'RAB: keterangan penerimaan belum diisi (baris ' . implode(', ', $bad) . ')'; }
            if ($tot <= 0) { $m[] = 'RAB: nilai penerimaan belum diisi'; }
        }
        if (!$induk) { $m[] = 'RAB: rencana pengeluaran belum ada kategori'; }
        foreach ($induk as $i => $x) {
            $x = is_array($x) ? $x : [];
            $h = self::hurufUrut($i);
            $nm = $isi($x['nama'] ?? '') ? '"' . trim((string) $x['nama']) . '"' : 'kategori ' . $h;
            if (!$isi($x['nama'] ?? '')) { $m[] = 'RAB: nama kategori ' . $h . ' belum diisi'; }
            $tipe = (string) ($x['tipe'] ?? 'harga');
            if ($tipe === 'harga') {
                if (!empty($x['rinci'])) {
                    $rr = (isset($x['rincian']) && is_array($x['rincian'])) ? array_values($x['rincian']) : [];
                    if (!$rr) { $m[] = 'RAB: rincian ' . $nm . ' belum ada baris'; continue; }
                    $jml = 0.0; $tanpaNama = 0;
                    foreach ($rr as $b) {
                        $b = is_array($b) ? $b : [];
                        if (!$isi($b['nama'] ?? '')) { $tanpaNama++; }
                        $tarif = self::angka($b['tarif'] ?? 0);
                        if ($tarif > 0) {
                            $q = self::angka($b['qty'] ?? 0); $hr = self::angka($b['hari'] ?? 0);
                            $jml += round(($q ?: 1) * ($hr ?: 1) * $tarif);
                        } else { $jml += round(self::angka($b['jumlah'] ?? 0)); }
                    }
                    if ($tanpaNama) { $m[] = 'RAB: uraian rincian ' . $nm . ' belum diisi'; }
                    elseif ($jml <= 0) { $m[] = 'RAB: nilai rincian ' . $nm . ' belum diisi'; }
                } elseif (round(self::angka($x['nilai'] ?? 0)) <= 0) {
                    $m[] = 'RAB: nilai ' . $nm . ' belum diisi';
                }
            } elseif ($tipe === 'persen' && self::angka($x['persen'] ?? 0) <= 0) {
                $m[] = 'RAB: persen ' . $nm . ' belum diisi';
            }
        }

        $jd = (isset($d['jadwal']) && is_array($d['jadwal'])) ? $d['jadwal'] : [];
        if ((string) ($jd['mode'] ?? 'teks') === 'gantt') {
            $bulan = (isset($jd['bulan']) && is_array($jd['bulan'])) ? $jd['bulan'] : [];
            $rows = (isset($jd['rows']) && is_array($jd['rows'])) ? $jd['rows'] : [];
            if (!$bulan) { $m[] = 'Jadwal belum punya bulan'; }
            else { foreach ($bulan as $b) { if (!$isi(is_array($b) ? ($b['nama'] ?? '') : '')) { $m[] = 'Jadwal: nama bulan belum diisi'; break; } } }
            if (!$rows) { $m[] = 'Jadwal belum punya kegiatan'; }
            else {
                $tanpaNama = false; $tanpaCentang = false;
                foreach ($rows as $r) {
                    $r = is_array($r) ? $r : [];
                    if (!$isi($r['nama'] ?? '')) { $tanpaNama = true; }
                    $on = (isset($r['on']) && is_array($r['on'])) ? $r['on'] : [];
                    if (!array_filter($on)) { $tanpaCentang = true; }
                }
                if ($tanpaNama) { $m[] = 'Jadwal: nama kegiatan belum diisi'; }
                if ($tanpaCentang) { $m[] = 'Jadwal: ada kegiatan yang belum dicentang minggunya'; }
            }
        } elseif (self::teksRte($d['jadwalTeks'] ?? '') === '') {
            $m[] = 'Jadwal belum diisi';
        }
        return $m;
    }

    /** Balasan 422 untuk input yang ditolak; sertakan daftar kekurangan bila dari validasi Kirim. */
    protected function tolakInput($err) {
        $out = ['ok' => false, 'message' => $err];
        if ($this->errKirim) { $out['errors'] = $this->errKirim; }
        return $this->json($out, 422);
    }

    /**
     * Validasi & rapikan input form. Return [pesanError|null, data].
     * Form mengirim seluruh isian sebagai satu JSON di field 'isi_form' (hanya untuk perjalanan data);
     * di sini dipecah ke kolom-kolom po_kegiatan lewat PoSchema::kolomDariForm(). Tidak ada kolom JSON gabungan.
     */
    protected function bacaInput($f3, $orderRaw = null) {
        $post = $f3->get('POST');

        $this->errKirim = [];
        if (((string) ($post['aksi'] ?? '')) === 'kirim') {
            $dd = json_decode((string) ($post['isi_form'] ?? ''), true);
            $kurang = $this->kekuranganKirim($post, is_array($dd) ? $dd : []);
            if ($kurang) {
                $this->errKirim = $kurang;
                return ['Data PO belum lengkap, semua isian wajib diisi kecuali alamat.', null];
            }
        }

        $tgl = trim((string) ($post['tanggal_po'] ?? ''));
        $dt = \DateTime::createFromFormat('Y-m-d', $tgl);
        if (!$dt || $dt->format('Y-m-d') !== $tgl) {
            return ['Tanggal Surat PO belum diisi / formatnya tidak valid.', null];
        }
        $judul = trim((string) ($post['judul_kegiatan'] ?? ''));
        if ($judul === '') {
            return ['Judul Kegiatan wajib diisi.', null];
        }
        if (function_exists('mb_substr')) { $judul = mb_substr($judul, 0, 255); } else { $judul = substr($judul, 0, 255); }
        $alamat = trim((string) ($post['alamat'] ?? ''));

        $raw = (string) ($post['isi_form'] ?? '');
        if ($raw === '' || strlen($raw) > 3000000) {
            return ['Data PO kosong atau terlalu besar.', null];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['Data PO tidak valid.', null];
        }
        // nilai kolom biasa dan isi form dijaga selalu sama
        $decoded['tanggal'] = $tgl;
        $decoded['judul']   = $judul;
        $decoded['jenis']   = trim((string) ($post['jenis'] ?? ''));
        $decoded['alamat']  = $alamat;
        // Nilai Kontrak = total_nilai_kontrak order, baris pertama penerimaan; tidak bisa diubah dari form
        if (is_array($orderRaw) && (float) ($orderRaw['total_nilai_kontrak'] ?? 0) > 0) {
            $terima = (isset($decoded['rab']['terima']) && is_array($decoded['rab']['terima'])) ? array_values($decoded['rab']['terima']) : [];
            $kunci = ['nama' => 'Nilai Kontrak', 'nilai' => (float) $orderRaw['total_nilai_kontrak']];
            if ($terima && is_array($terima[0]) && preg_match('/^nilai kontrak$/i', trim((string) ($terima[0]['nama'] ?? '')))) {
                $terima[0] = $kunci;
            } else {
                array_unshift($terima, $kunci);
            }
            if (!isset($decoded['rab']) || !is_array($decoded['rab'])) { $decoded['rab'] = []; }
            $decoded['rab']['terima'] = $terima;
        }

        return [null, [
            'tanggal_po' => $tgl,
            'judul'      => $judul,
            'alamat'     => $alamat === '' ? null : $alamat,
            // dasar(+dasar_struktur), tujuan, ruang_lingkup, tim_pelaksana, rab, jadwal, jenis, ...; poin pembayaran ikut ditulis di kolom `dasar`
            'kolom'      => \PoSchema::kolomDariForm($decoded, $this->teksPembayaran($orderRaw, $decoded['perusahaan'] ?? '')),
        ]];
    }


    /**
     * POST /petunjuk-operasional/sertifikasi/simpan  (AJAX, balasan JSON)
     * Membuat PO baru: nomor PO diterbitkan dari po_nomor_urut + baris po_kegiatan dibuat dalam
     * satu transaksi (gagal simpan -> nomor tidak terbuang).
     */
    public function simpan($f3) {
        $this->requireAuth();

        $kendala = $this->kendalaSkema();
        if ($kendala !== '') {
            return $this->json(['ok' => false, 'message' => $kendala], 500);
        }

        $orderId = (int) ($f3->get('POST.order_id') ?? 0);
        if ($orderId <= 0) {
            return $this->json(['ok' => false, 'message' => 'PO harus dibuat dari order. Pilih order dulu lewat Petunjuk Operasional > Sertifikasi.'], 422);
        }
        try {
            $rows = $this->queryOrderLayak($orderId);
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal validasi order saat simpan: ' . $e->getMessage());
            return $this->json(['ok' => false, 'message' => 'Gagal membaca order dari database SISERTIFIKASI.'], 500);
        }
        if (empty($rows[0])) {
            return $this->json(['ok' => false, 'message' => 'Order sertifikasi tidak ditemukan atau belum memenuhi syarat.'], 422);
        }

        list($err, $in) = $this->bacaInput($f3, $rows[0]);
        if ($err !== null) {
            return $this->tolakInput($err);
        }
        $status = $this->statusDariAksi($f3);

        try {
            $ada = \PoSchema::cariPoSertifikasi($this->db, $orderId);
            if ($ada) {
                return $this->json(['ok' => false, 'message' => 'Order ini sudah punya PO.', 'redirect' => $this->urlEdit($ada['id'])], 409);
            }

            \PoSchema::pastikanTabelNomor($this->db); // DDL harus di luar transaksi
            $this->db->begin();
            try {
                $nomor = \PoSchema::nomorBaru($this->db, $in['tanggal_po'], false);
                $now = date('Y-m-d H:i:s');
                $poId = \PoSchema::sisipPo($this->db, array_merge($in['kolom'], [
                    'order_id'       => $orderId,
                    'layanan'        => \PoSchema::SERTIFIKASI,
                    'nomor_po'       => $nomor['detail_nomor'],
                    'id_nomor_urut'  => $nomor['id'],
                    'tanggal_po'     => $in['tanggal_po'],
                    'judul_kegiatan' => $in['judul'],
                    'alamat'         => $in['alamat'],
                    'status'         => $status,
                    'created_by'     => (int) $this->getUserId(),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]));
                if ($poId <= 0) {
                    throw new \Exception('PO gagal dibuat.');
                }
                $this->db->commit();
            } catch (\Throwable $e) {
                try { $this->db->rollback(); } catch (\Throwable $eRb) {}
                throw $e;
            }
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal menyimpan PO: ' . $e->getMessage());
            return $this->json(['ok' => false, 'message' => 'Gagal menyimpan PO: ' . $e->getMessage()], 500);
        }

        $kirim = ($status === 'terkirim');
        $pesan = $kirim ? 'PO berhasil dibuat dan dikirim.' : 'PO berhasil disimpan sebagai draft.';
        // popup berhasil + pindah ke daftar PO ditangani di halaman form (tab sesuai status)
        return $this->json([
            'ok'             => true,
            'message'        => $pesan,
            'id'             => $poId,
            'nomor'          => $nomor['detail_nomor'],
            'status'         => $status,
            'redirect'       => $this->urlEdit($poId),
            'redirect_index' => $this->urlIndex($kirim ? 'terkirim' : 'draft'),
        ]);
    }

    /**
     * POST /petunjuk-operasional/sertifikasi/@id/update  (AJAX, balasan JSON)
     * Nomor PO TIDAK berubah saat diedit (sama seperti PO OPTI).
     */
    public function update($f3, $params) {
        $this->requireAuth();

        $kendala = $this->kendalaSkema();
        if ($kendala !== '') {
            return $this->json(['ok' => false, 'message' => $kendala], 500);
        }

        try {
            $po = \PoSchema::ambilPoSertifikasi($this->db, (int) $params['id']);
        } catch (\Throwable $e) {
            $po = null;
        }
        if (!$po) {
            return $this->json(['ok' => false, 'message' => 'PO Sertifikasi tidak ditemukan.'], 404);
        }
        $statusLama = (string) ($po['status'] ?? 'draft');
        if (!in_array($statusLama, ['', 'draft', 'terkirim'], true)) {
            return $this->json(['ok' => false, 'message' => 'PO ini sudah berstatus "' . $statusLama . '" dan tidak bisa diubah lagi.'], 409);
        }
        $status = $this->statusDariAksi($f3);

        $orderRaw = null;
        try {
            $ro = $this->queryOrderLayak((int) $po['order_id'], false);
            $orderRaw = !empty($ro[0]) ? $ro[0] : null;
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal baca order saat update: ' . $e->getMessage());
        }

        list($err, $in) = $this->bacaInput($f3, $orderRaw);
        if ($err !== null) {
            return $this->tolakInput($err);
        }

        try {
            \PoSchema::ubahPo($this->db, (int) $po['id'], array_merge($in['kolom'], [
                'tanggal_po'     => $in['tanggal_po'],
                'judul_kegiatan' => $in['judul'],
                'alamat'         => $in['alamat'],
                'status'         => $status,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]));
        } catch (\Throwable $e) {
            error_log('[PO Sertifikasi] gagal memperbarui PO: ' . $e->getMessage());
            return $this->json(['ok' => false, 'message' => 'Gagal memperbarui PO: ' . $e->getMessage()], 500);
        }

        $kirim = ($status === 'terkirim');
        $pesan = $kirim ? 'PO berhasil diperbarui dan dikirim.' : 'PO berhasil diperbarui sebagai draft.';
        return $this->json([
            'ok'             => true,
            'message'        => $pesan,
            'id'             => (int) $po['id'],
            'nomor'          => (string) ($po['nomor_po'] ?? ''),
            'status'         => $status,
            'redirect_index' => $this->urlIndex($kirim ? 'terkirim' : 'draft'),
        ]);
    }
}
