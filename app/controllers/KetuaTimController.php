<?php
class KetuaTimController extends Controller {

    public function beforeRoute($f3) {
        parent::beforeRoute($f3);
        $this->requireAuth();
        $role = $this->getUserRole();
        if ($role !== 'superadmin' && $role !== 'ketua_tim') {
            $this->setFlashError('Akses Ditolak: Halaman Daftar Order Lunas hanya dapat diakses oleh Ketua Tim OPTI dan Superadmin.');
            $f3->reroute('/dashboard');
            return;
        }
    }

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
               o.status_keuangan, o.tanggal_terima_sampel,
               o.ketua_pelaksana_id, o.ketua_pelaksana_at, o.pic_proposal_id,
                   c.nmcustomer AS nama_perusahaan, c.pt_cv,
                   sp.id AS sp_id, sp.nominal_penawaran, sp.surat_kesanggupan_bayar,
                   COALESCE((SELECT SUM(p.jumlah) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi'), 0) AS total_terbayar,
                   COALESCE((SELECT MAX(p.is_kesanggupan_bayar) FROM opti_pembayaran p WHERE p.order_id = o.id), 0) AS punya_kesanggupan_bayar,
                   (SELECT MAX(p.tanggal_bayar) FROM opti_pembayaran p WHERE p.order_id = o.id AND p.status_verifikasi = 'terverifikasi') AS tanggal_lunas,
                   (SELECT id FROM po_kegiatan WHERE order_id = o.id LIMIT 1) AS po_id,
                   (SELECT status FROM po_kegiatan WHERE order_id = o.id LIMIT 1) AS po_status
            FROM order_layanan o
            JOIN tb_customer c ON o.id_customer = c.id_customer
            LEFT JOIN tb_surat_penawaran sp ON sp.order_id = o.id AND sp.status_respon_klien = 'deal'
            WHERE o.disposisi_katim_at IS NOT NULL
              AND o.status_keuangan = 'lunas'
              AND o.tanggal_terima_sampel IS NOT NULL";

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
        // [FIX] sekarang wajib dua syarat: status_keuangan = 'lunas' DAN tanggal_terima_sampel
// sudah terisi. Order Kesanggupan Bayar yang belum ada tanggal terima sampelnya
// TIDAK boleh masuk sini walau status_keuangan-nya sudah lunas.
        $o['nama_perusahaan'] = \Customer::formatNamaPerusahaan($o['pt_cv'] ?? '', $o['nama_perusahaan'] ?? '');
        $o['biaya_acuan'] = !empty($o['nominal_penawaran']) ? (float) $o['nominal_penawaran'] : (float) $o['estimasi_biaya'];

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

    try {
        // [SEMENTARA] cuma tim_kerja_selulosa dulu. Lingkungan gak lewat alur "pilih pelaksana" ini,
// nanti pakai mekanisme disposisi langsung yang beda -- nyusul kalau udah dibahas.
$daftarPelaksana = $this->dbSekretariat->exec("SELECT id_user, nama_user FROM tb_arsipuser WHERE si_opti = 'tim_kerja_selulosa' ORDER BY nama_user ASC");
        // $daftarPelaksana = $this->dbSekretariat->exec("SELECT id_user, nama_user FROM tb_arsipuser WHERE si_opti LIKE '%tim_kerja%' ORDER BY nama_user ASC");
    } catch (\Exception $e) {
        $daftarPelaksana = [];
    }

    // [BARU] Nama Ketua Pelaksana yang udah ditunjuk -- dicari by ID (bukan cuma
    // dari $daftarPelaksana di atas) soalnya orangnya bisa aja udah gak lagi
    // ke-filter 'tim_kerja' pas dicek sekarang, padahal dulu pernah ditunjuk.
    // [BARU] Nama Ketua Pelaksana (tab Sudah) DAN nama PIC Proposal buat order Lingkungan
// (tab Belum) -- digabung jadi satu query biar gak query dua kali ke tb_arsipuser.
$idNamaUnik = [];
foreach ($daftarSudah as $o) {
    if (!empty($o['ketua_pelaksana_id'])) { $idNamaUnik[(int) $o['ketua_pelaksana_id']] = true; }
}
foreach ($daftarBelum as $o) {
    if (($o['jenis_layanan_opti'] ?? '') === 'lingkungan' && !empty($o['pic_proposal_id'])) {
        $idNamaUnik[(int) $o['pic_proposal_id']] = true;
    }
}

$mapNamaPelaksana = [];
if (!empty($idNamaUnik)) {
    $placeholder = implode(',', array_fill(0, count($idNamaUnik), '?'));
    try {
        $rowsNama = $this->dbSekretariat->exec(
            "SELECT id_user, nama_user FROM tb_arsipuser WHERE id_user IN ($placeholder)",
            array_values(array_keys($idNamaUnik))
        );
        foreach ($rowsNama as $rn) {
            $mapNamaPelaksana[(int) $rn['id_user']] = $rn['nama_user'];
        }
    } catch (\Exception $e) {
        // biarin kosong, tampilkan '-' di view
    }
}
foreach ($daftarSudah as &$o) {
    $o['nama_pelaksana'] = $mapNamaPelaksana[(int) $o['ketua_pelaksana_id']] ?? '-';
}
unset($o);
foreach ($daftarBelum as &$o) {
    if (($o['jenis_layanan_opti'] ?? '') === 'lingkungan') {
        $o['nama_pic_proposal'] = !empty($o['pic_proposal_id']) ? ($mapNamaPelaksana[(int) $o['pic_proposal_id']] ?? '-') : null;
    }
}
unset($o);

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