<?php
/**
 * MasterJabatanPelaksanaController -- Data Master "Jabatan Pelaksana PO" (halaman & file terpisah dari Tim Pelaksana).
 * Superadmin dan Ketua Tim Layanan (const KETUA_TIM_KELOLA; set false kalau Ketua Tim cukup melihat saja -> ditolak).
 * Jabatan ini satu daftar bersama untuk semua layanan. Jabatan yang aktif jadi pilihan di form PO.
 * Memakai ulang helper akses/skema/data milik MasterTimPelaksanaController (class induk).
 * Method tambah/ubah/hapus/aktif di sini sengaja menimpa milik class induk (yang untuk anggota tim).
 *
 *   GET  /master-jabatan-pelaksana
 *   POST /master-jabatan-pelaksana/tambah          items=[{nama}]       (bisa banyak sekaligus)
 *   POST /master-jabatan-pelaksana/ubah            items=[{id,nama}]    (bisa banyak sekaligus)
 *        Ganti nama ikut mengubah teks jabatan di Tim Pelaksana PO yang BELUM SELESAI (status bukan 'disetujui').
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

        $rows = [];
        $aktif = 0;
        $no = 0;
        foreach ($ok ? $this->dataJabatan() : [] as $j) {
            $no++;
            if ($j['aktif']) { $aktif++; }
            $rows[] = [
                'id'    => $j['id'],
                'no'    => $no,
                'nama'  => $j['nama'],
                'cari'  => $this->kecil($j['nama']),
                'aktif' => $j['aktif'],
            ];
        }

        // tabel di view dirender dengan <repeat> dari mtp_jab_rows; mtp_jab_json hanya bahan form Tambah/Ubah
        $f3->set('mtp_jab_rows', $rows);
        $f3->set('mtp_n_semua',  count($rows));
        $f3->set('mtp_n_aktif',  $aktif);
        $f3->set('mtp_n_non',    count($rows) - $aktif);
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
        $items = json_decode((string) $f3->get('POST.items'), true);
        if (!is_array($items) || !count($items)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Isi minimal satu jabatan.'], 422); }

        $ada = [];
        foreach ($this->dataJabatan() as $j) { $ada[$this->kecil($j['nama'])] = 1; }
        $baru = [];
        foreach ($items as $it) {
            $nama = $this->namaBersih(isset($it['nama']) ? $it['nama'] : '');
            $k = $this->kecil($nama);
            if (isset($ada[$k]) || isset($baru[$k])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Nama jabatan sudah ada / dobel: "' . $nama . '".'], 422); }
            $baru[$k] = $nama;
        }
        try {
            $this->db->begin();
            foreach ($baru as $nama) {
                $this->db->exec("INSERT INTO po_jabatan_pelaksana (nama, aktif) VALUES (?, 1)", [1 => $nama]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            try { $this->db->rollback(); } catch (\Exception $e2) {}
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
        $this->keluarkanState(count($baru) . ' jabatan ditambahkan.');
    }

    /** POST /master-jabatan-pelaksana/ubah -- ganti nama beberapa jabatan sekaligus. */
    public function ubah($f3) {
        $this->izinkanJabatan(true);
        $items = json_decode((string) $f3->get('POST.items'), true);
        if (!is_array($items) || !count($items)) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Tidak ada data yang diubah.'], 422); }

        $semua = [];
        foreach ($this->dataJabatan() as $j) { $semua[$j['id']] = $j['nama']; }
        $ubah = [];
        foreach ($items as $it) {
            $id = (int) (isset($it['id']) ? $it['id'] : 0);
            if (!isset($semua[$id])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Ada jabatan yang tidak ditemukan.'], 404); }
            $ubah[$id] = $this->namaBersih(isset($it['nama']) ? $it['nama'] : '');
        }
        // nama akhir seluruh jabatan tidak boleh kembar (tanpa membedakan huruf besar/kecil)
        $akhir = $semua;
        foreach ($ubah as $id => $nama) { $akhir[$id] = $nama; }
        $lihat = [];
        foreach ($akhir as $nama) {
            $k = $this->kecil($nama);
            if (isset($lihat[$k])) { $this->keluarkanJson(['ok' => false, 'pesan' => 'Nama jabatan sudah ada / dobel: "' . $nama . '".'], 422); }
            $lihat[$k] = 1;
        }
        // pasangan nama lama -> nama baru, hanya yang benar-benar berubah (huruf besar/kecil dianggap beda)
        $peta = [];
        foreach ($ubah as $id => $nama) {
            if ($semua[$id] !== $nama) { $peta[$this->kecil($semua[$id])] = $nama; }
        }
        $poTerubah = 0;
        try {
            $this->db->begin();
            foreach ($ubah as $id => $nama) {
                $this->db->exec("UPDATE po_jabatan_pelaksana SET nama = ? WHERE id = ?", [1 => $nama, 2 => $id]);
            }
            if (count($peta)) { $poTerubah = $this->terapkanKePoBelumSelesai($peta); }
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
     * $peta = [nama_lama_huruf_kecil => nama_baru]. Dicocokkan tanpa beda huruf besar/kecil & spasi pinggir,
     * dan diterapkan sekaligus per baris (jadi tukar nama A<->B juga aman).
     * Dipanggil di dalam transaksi pemanggil. Return jumlah PO yang berubah.
     */
    private function terapkanKePoBelumSelesai(array $peta) {
        $rows = $this->db->exec(
            "SELECT id, tim_pelaksana FROM po_kegiatan
              WHERE (status IS NULL OR status <> 'disetujui')
                AND tim_pelaksana IS NOT NULL AND tim_pelaksana <> '' AND tim_pelaksana <> '[]'");
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

        foreach ($ids as $id) {
            $this->db->exec("DELETE FROM po_jabatan_pelaksana WHERE id = ?", [1 => $id]);
        }
        $this->keluarkanState(count($ids) . ' jabatan dihapus.');
    }

    /** POST /master-jabatan-pelaksana/@id/aktif */
    public function aktif($f3, $params) {
        $this->izinkanJabatan(true);
        $a = ((int) $f3->get('POST.aktif')) ? 1 : 0;
        $id = (int) $params['id'];
        $this->db->exec("UPDATE po_jabatan_pelaksana SET aktif = ? WHERE id = ?", [1 => $a, 2 => $id]);
        $this->keluarkanState($a ? 'Jabatan diaktifkan.' : 'Jabatan dinonaktifkan (tidak muncul di pilihan baru).');
    }
}
