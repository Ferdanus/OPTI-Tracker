<?php
class KetuaTimController extends Controller {

    /** GET /ketua-tim/siap-po */
    public function index($f3) {
        $this->requireAuth();
        $this->requirePermission('order:tinjau');

        $userRole     = $this->getUserRole();
        $isSuperadmin = $this->isSuperadmin();
        $divisiKatim  = $_SESSION['jenis_layanan_opti'] ?? '';

        $filterDivisi = null;
        if ($userRole === 'ketua_tim' && !$isSuperadmin && in_array($divisiKatim, ['selulosa', 'lingkungan'], true)) {
            $filterDivisi = $divisiKatim;
        }

        $sql = "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya,
                       o.jenis_layanan_opti, o.disposisi_katim_at,
                       o.ketua_pelaksana_id, o.ketua_pelaksana_at,
                       c.nmcustomer AS nama_perusahaan, c.pt_cv,
                       sp.nominal_penawaran,
                       COALESCE((SELECT SUM(p.jumlah) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi'), 0) AS total_terbayar,
                       (SELECT MAX(p.tanggal_bayar) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi') AS tanggal_lunas,
                       (SELECT id FROM po_kegiatan WHERE order_id = o.id LIMIT 1) AS po_id
                FROM order_layanan o
                JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
                WHERE o.disposisi_katim_at IS NOT NULL
                  AND o.id IN (SELECT DISTINCT order_id FROM opti_pembayaran)";
        $params = [];
        if ($filterDivisi) {
            $sql .= " AND o.jenis_layanan_opti = ?";
            $params[] = $filterDivisi;
        }
        $sql .= " ORDER BY o.disposisi_katim_at DESC";

        try {
            $rows = $this->db->exec($sql, $params);
        } catch (\Exception $e) {
            $rows = [];
        }

        $daftarBelum = [];
        $daftarSudah = [];

        foreach ($rows as $o) {
            $biayaAcuan = !empty($o['nominal_penawaran']) ? (float) $o['nominal_penawaran'] : (float) $o['estimasi_biaya'];
            $terbayar   = (float) $o['total_terbayar'];
            if ($biayaAcuan <= 0 || $terbayar < $biayaAcuan) continue; // cuma yang beneran lunas

            $o['biaya_acuan'] = $biayaAcuan;

            if (!empty($o['ketua_pelaksana_id'])) {
                $t = strtotime($o['ketua_pelaksana_at']);
                $o['tahun_tunjuk'] = $t ? (int) date('Y', $t) : null;
                $o['bulan_tunjuk'] = $t ? (int) date('n', $t) : null;
                $o['sudah_ada_po'] = !empty($o['po_id']);
                $daftarSudah[] = $o;
            } else {
                $daftarBelum[] = $o;
            }
        }

        // [BARU] Daftar calon Ketua Pelaksana -- dari tb_arsipuser di db sil2020 (dbSekretariat)
        try {
            $daftarPelaksana = $this->dbSekretariat->exec("SELECT id_user, nama_user FROM tb_arsipuser WHERE si_opti LIKE '%tim_kerja%' ORDER BY nama_user ASC");
        } catch (\Exception $e) {
            $daftarPelaksana = [];
        }

        $tahunSekarang = (int) date('Y');
        $daftarTahun = [];
        for ($i = 0; $i < 4; $i++) { $daftarTahun[] = $tahunSekarang - $i; }

        $f3->set('daftar_belum_json', json_encode($daftarBelum, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_sudah_json', json_encode($daftarSudah, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_pelaksana_json', json_encode($daftarPelaksana, JSON_UNESCAPED_UNICODE));
        $f3->set('daftar_tahun', $daftarTahun);
        $f3->set('bulan_sekarang', (int) date('n'));
        $f3->set('tahun_sekarang', $tahunSekarang);

        $this->render('ketua-tim/index.html', 'Order Siap Ditindaklanjuti', 'ketua-tim-po');
    }

    /** POST /ketua-tim/@id/pilih-pelaksana */
    public function simpanPelaksana($f3, $params) {
        $this->requireAuth();
        $this->requirePermission('order:tinjau');

        $orderId     = (int) ($params['id'] ?? 0);
        $pelaksanaId = (int) ($f3->get('POST.ketua_pelaksana_id') ?? 0);

        if ($orderId <= 0 || $pelaksanaId <= 0) {
            $this->setFlashError('Order atau Ketua Pelaksana tidak valid.');
            $f3->reroute('/ketua-tim/siap-po');
            return;
        }

        try {
            $this->db->exec(
                "UPDATE order_layanan SET ketua_pelaksana_id = ?, ketua_pelaksana_at = NOW() WHERE id = ?",
                [1 => $pelaksanaId, 2 => $orderId]
            );
            $this->setFlashSuccess('Ketua Pelaksana berhasil ditunjuk.');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan: ' . $e->getMessage());
        }

        $f3->reroute('/ketua-tim/siap-po');
    }
}