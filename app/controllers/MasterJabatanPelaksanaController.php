<?php
/**
 * MasterJabatanPelaksanaController -- Data Master "Jabatan Pelaksana PO" (halaman & file terpisah dari Tim Pelaksana).
 * Superadmin dan Ketua Tim Layanan (const KETUA_TIM_KELOLA; set false kalau Ketua Tim cukup melihat saja -> ditolak).
 * Jabatan dicatat PER LAYANAN (divisi), sama seperti Tim Pelaksana, supaya tidak tercampur antar layanan.
 * Superadmin: semua layanan. Ketua Tim: otomatis terkunci ke layanannya sendiri.
 * Jabatan yang aktif jadi pilihan di bagian Tim Pelaksana form PO layanan yang sama.
 * Memakai ulang helper akses/skema/data milik MasterTimPelaksanaController (class induk).
 * Method tambah/ubah/hapus/aktif di sini sengaja menimpa milik class induk (yang untuk anggota tim).
 *
 *   GET  /master-jabatan-pelaksana
 *   POST /master-jabatan-pelaksana/tambah          layanan, items=[{nama}]  (bisa banyak sekaligus)
 *   POST /master-jabatan-pelaksana/ubah            items=[{id,nama}]    (bisa banyak sekaligus)
 *        Ganti nama ikut mengubah teks jabatan di Tim Pelaksana PO layanan yang sama yang BELUM SELESAI (status bukan 'disetujui').
 *        PO yang sudah selesai (status 'disetujui') tidak disentuh: isinya tetap seperti saat disimpan.
 *   POST /master-jabatan-pelaksana/hapus           ids=[..]
 *   POST /master-jabatan-pelaksana/@id/aktif       aktif=0|1
 *
 * Kompatibel PHP 7.2.
 */
class MasterJabatanPelaksanaController extends MasterTimPelaksanaController {

    /** true = Ketua Tim Layanan boleh membuka & mengelola halaman ini (daftar jabatan berlaku untuk semua layanan). */
    const KETUA_TIM_KELOLA = true;

    protected function izinkanJabatan($json = false) {
        $this->requireAuth();
        $ok = $this->isSuperadmin()
            || (self::KETUA_TIM_KELOLA && $this->getUserRole() === 'ketua_tim' && $this->layananTerkunci() !== null);
        if ($ok) { return; }
        if ($json) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Anda tidak punya akses untuk aksi ini.'], 403); }
        $this->setFlashError('Anda tidak punya akses ke halaman Master Jabatan Pelaksana PO.');
        $this->f3->reroute('/dashboard');
        exit;
    }

    /** GET /master-jabatan-pelaksana */
    public function index($f3) {
        $this->izinkanJabatan(false);
        $ok = $this->pastikanSkema();
        $lock = $this->layananTerkunci();

        $rows = [];
        $aktif = 0;
        $no = 0;
        foreach ($ok ? $this->dataJabatan() : [] as $j) {
            $no++;
            if ($j['aktif']) { $aktif++; }
            $rows[] = [
                'id'      => $j['id'],
                'no'      => $no,
                'nama'    => $j['nama'],
                'lay'     => $j['lay'],
                'layanan' => isset(self::$LAYANAN[$j['lay']]) ? self::$LAYANAN[$j['lay']] : $j['lay'],
                'cari'    => $this->kecil($j['nama']),
                'aktif'   => $j['aktif'],
            ];
        }

        $layOpts = [];
        foreach ($this->dataLayanan() as $l) { $layOpts[] = ['k' => $l[0], 'v' => $l[1]]; }
        $cfg = ['su' => $this->isSuperadmin(), 'lock' => (string) $lock];
        if ($this->isSuperadmin()) {
            $sub = 'Daftar jabatan yang bisa dipilih di bagian Tim Pelaksana pada form PO, dicatat per layanan supaya tidak tercampur. Hanya jabatan aktif yang muncul di pilihan.';
        } else {
            $nama = isset(self::$LAYANAN[$lock]) ? self::$LAYANAN[$lock] : '';
            $sub = 'Daftar jabatan layanan ' . $nama . ' yang bisa dipilih di bagian Tim Pelaksana pada form PO. Hanya jabatan aktif yang muncul di pilihan.';
        }

        // tabel di view dirender dengan <repeat> dari mtp_jab_rows; mtp_jab_json hanya bahan form Tambah/Ubah
        $f3->set('mtp_jab_rows', $rows);
        $f3->set('mtp_n_semua',  count($rows));
        $f3->set('mtp_n_aktif',  $aktif);
        $f3->set('mtp_n_non',    count($rows) - $aktif);
        $f3->set('mtp_lay_opts', $layOpts);
        $f3->set('mtp_lock',     (string) $lock);
        $f3->set('mtp_subjudul', $sub);
        $f3->set('mtp_layanan_json', $this->json($this->dataLayanan()));
        $f3->set('mtp_cfg_json', $this->json($cfg));
        $f3->set('mtp_jab_json', $this->json($ok ? $this->dataJabatan() : []));
        $f3->set('error_message', $ok ? null : 'Tabel master belum bisa dibuat otomatis (izin CREATE TABLE kurang). Jalankan migration_master_tim_pelaksana.sql.');
        $this->render('master-jabatan-pelaksana/index.html', 'Master Jabatan Pelaksana PO', 'master_jabatan_pelaksana');
    }

    /** Bersihkan nama: spasi rapi, wajib isi, maksimal 80 karakter. Gagal -> JSON 422. */
    private function namaBersih($nama) {
        $nama = trim(preg_replace('/\s+/', ' ', (string) $nama));
        if ($nama === '') { $this->keluarkanJson(['ok' => false, 'pesan' => 'Nama jabatan wajib diisi.'], 422); }
        if (mb_strlen($nama) > 80) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Nama jabatan maksimal 80 karakter.'], 422); }
        return $nama;
    }

    /** POST /master-jabatan-pelaksana/tambah */
    public function tambah($f3) {
        $this->izinkanJabatan(true);
        $this->pastikanSkema();
        $lock = $this->layananTerkunci();
        $lay = $lock ?: strtolower(trim((string) $f3->get('POST.layanan')));
        if (!isset(self::$LAYANAN[$lay])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Pilih layanan dulu.'], 422); }
        $items = json_decode((string) $f3->get('POST.items'), true);
        if (!is_array($items) || !count($items)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Isi minimal satu jabatan.'], 422); }

        $ada = [];
        foreach ($this->dataJabatan() as $j) {
            if ($j['lay'] === $lay) { $ada[$this->kecil($j['nama'])] = 1; }
        }
        $baru = [];
        foreach ($items as $it) {
            $nama = $this->namaBersih(isset($it['nama']) ? $it['nama'] : '');
            $k = $this->kecil($nama);
            if (isset($ada[$k]) || isset($baru[$k])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Nama jabatan sudah ada / dobel di ' . self::$LAYANAN[$lay] . ': "' . $nama . '".'], 422); }
            $baru[$k] = $nama;
        }
        try {
            $this->db->begin();
            foreach ($baru as $nama) {
                $this->db->exec("INSERT INTO po_jabatan_pelaksana (layanan, nama, aktif) VALUES (?, ?, 1)", [1 => $lay, 2 => $nama]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
        $this->keluarkanState(count($baru) . ' jabatan ditambahkan ke ' . self::$LAYANAN[$lay] . '.');
    }

    /** POST /master-jabatan-pelaksana/ubah -- ganti nama beberapa jabatan sekaligus. */
    public function ubah($f3) {
        $this->izinkanJabatan(true);
        $items = json_decode((string) $f3->get('POST.items'), true);
        if (!is_array($items) || !count($items)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Tidak ada data yang diubah.'], 422); }

        $semua = [];
        $layOf = [];
        foreach ($this->dataJabatan() as $j) { $semua[$j['id']] = $j['nama']; $layOf[$j['id']] = $j['lay']; }
        $ubah = [];
        foreach ($items as $it) {
            $id = (int) (isset($it['id']) ? $it['id'] : 0);
            if (!isset($semua[$id])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Ada jabatan yang tidak ditemukan.'], 404); }
            $ubah[$id] = $this->namaBersih(isset($it['nama']) ? $it['nama'] : '');
        }
        // nama akhir jabatan dalam satu layanan tidak boleh kembar (tanpa membedakan huruf besar/kecil); layanan lain tidak ikut dihitung
        $akhir = $semua;
        foreach ($ubah as $id => $nama) { $akhir[$id] = $nama; }
        $lihat = [];
        foreach ($akhir as $id => $nama) {
            $k = $layOf[$id] . '|' . $this->kecil($nama);
            if (isset($lihat[$k])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Nama jabatan sudah ada / dobel: "' . $nama . '".'], 422); }
            $lihat[$k] = 1;
        }
        // pasangan nama lama -> nama baru PER LAYANAN, hanya yang benar-benar berubah (huruf besar/kecil dianggap beda)
        $peta = [];
        foreach ($ubah as $id => $nama) {
            if ($semua[$id] !== $nama) { $peta[$layOf[$id]][$this->kecil($semua[$id])] = $nama; }
        }
        $poTerubah = 0;
        try {
            $this->db->begin();
            foreach ($ubah as $id => $nama) {
                $this->db->exec("UPDATE po_jabatan_pelaksana SET nama = ? WHERE id = ?", [1 => $nama, 2 => $id]);
            }
            foreach ($peta as $layanan => $petaLay) { $poTerubah += $this->terapkanKePoBelumSelesai($petaLay, $layanan); }
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
        $pesan = count($ubah) . ' jabatan diperbarui.';
        if (count($peta)) {
            $pesan .= $poTerubah > 0
                ? ' ' . $poTerubah . ' PO yang belum selesai ikut diperbarui; PO yang sudah selesai tidak berubah.'
                : ' Tidak ada PO yang belum selesai yang memakai jabatan ini; PO yang sudah selesai tidak berubah.';
        }
        $this->keluarkanState($pesan);
    }

    /**
     * Ganti teks jabatan (kolom "tugas" di JSON po_kegiatan.tim_pelaksana) pada PO yang BELUM selesai.
     * PO selesai = status 'disetujui' (sudah divalidasi Keuangan) -> dibiarkan apa adanya.
     * Hanya PO milik layanan jabatan itu (po_kegiatan.layanan: selulosa -> 'opti-selulosa', lingkungan -> 'opti-lingkungan',
     * lainnya sama dengan kunci layanan); jabatan layanan lain dengan nama sama tidak ikut berubah.
     * $peta = [nama_lama_huruf_kecil => nama_baru]. Dicocokkan tanpa beda huruf besar/kecil & spasi pinggir,
     * dan diterapkan sekaligus per baris (jadi tukar nama A<->B juga aman).
     * Dipanggil di dalam transaksi pemanggil. Return jumlah PO yang berubah.
     */
    private function terapkanKePoBelumSelesai(array $peta, $layanan) {
        $kunciPo = ['selulosa' => 'opti-selulosa', 'lingkungan' => 'opti-lingkungan'];
        $layPo = isset($kunciPo[$layanan]) ? $kunciPo[$layanan] : $layanan;
        $kolLay = $this->db->exec("SHOW COLUMNS FROM po_kegiatan LIKE 'layanan'");
        $sql = "SELECT id, tim_pelaksana FROM po_kegiatan
              WHERE (status IS NULL OR status <> 'disetujui')
                AND tim_pelaksana IS NOT NULL AND tim_pelaksana <> '' AND tim_pelaksana <> '[]'";
        $par = [];
        if (!empty($kolLay)) { $sql .= " AND layanan = ?"; $par[1] = $layPo; }
        $rows = $this->db->exec($sql, $par);
        $n = 0;
        foreach ($rows as $r) {
            $tim = json_decode((string) $r['tim_pelaksana'], true);
            if (!is_array($tim)) { continue; }
            $berubah = false;
            foreach ($tim as $i => $t) {
                if (!is_array($t) || !isset($t['tugas']) || !is_string($t['tugas'])) { continue; }
                $k = $this->kecil(trim(preg_replace('/\s+/', ' ', $t['tugas'])));
                if (isset($peta[$k])) { $tim[$i]['tugas'] = $peta[$k]; $berubah = true; }
            }
            if ($berubah) {
                $this->db->exec("UPDATE po_kegiatan SET tim_pelaksana = ? WHERE id = ?",
                    [1 => json_encode($tim, JSON_UNESCAPED_UNICODE), 2 => (int) $r['id']]);
                $n++;
            }
        }
        return $n;
    }

    /** POST /master-jabatan-pelaksana/hapus */
    public function hapus($f3) {
        $this->izinkanJabatan(true);
        $ids = json_decode((string) $f3->get('POST.ids'), true);
        if (!is_array($ids) || !count($ids)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Tidak ada data yang dipilih.'], 422); }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $milik = [];
        foreach ($this->dataJabatan() as $j) { $milik[$j['id']] = $j; }   // hanya jabatan layanan yang boleh dikelola user ini

        // jabatan yang masih aktif tidak boleh dihapus: nonaktifkan dulu
        foreach ($ids as $id) {
            if (isset($milik[$id]) && (int) $milik[$id]['aktif'] === 1) {
                $this->keluarkanJson(['ok' => false, 'pesan' => 'Jabatan "' . $milik[$id]['nama'] . '" masih aktif. Nonaktifkan dulu sebelum dihapus.'], 422);
            }
        }
        $n = 0;
        foreach ($ids as $id) {
            if (!isset($milik[$id])) { continue; }
            $this->db->exec("DELETE FROM po_jabatan_pelaksana WHERE id = ? AND aktif = 0", [1 => $id]);
            $n++;
        }
        $this->keluarkanState($n . ' jabatan dihapus. PO yang sudah dibuat tidak berubah.');
    }

    /** POST /master-jabatan-pelaksana/@id/aktif */
    public function aktif($f3, $params) {
        $this->izinkanJabatan(true);
        $a = ((int) $f3->get('POST.aktif')) ? 1 : 0;
        $id = (int) $params['id'];
        $milik = [];
        foreach ($this->dataJabatan() as $j) { $milik[$j['id']] = 1; }
        if (!isset($milik[$id])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Jabatan tidak ditemukan.'], 404); }
        $this->db->exec("UPDATE po_jabatan_pelaksana SET aktif = ? WHERE id = ?", [1 => $a, 2 => $id]);
        $this->keluarkanState($a ? 'Jabatan diaktifkan.' : 'Jabatan dinonaktifkan (tidak muncul di pilihan baru).');
    }
}
