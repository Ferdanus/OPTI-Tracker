<?php

/**
 * Controller untuk mengelola Data Pembayaran Multi-Termin (One-to-Many terhadap Order/PO)
 * Dilengkapi Guard Permission (Input/Edit: Admin Order & Superadmin; View: Internal Balai)
 */
class PembayaranController extends Controller {

    /**
     * Menampilkan daftar riwayat pembayaran dan status pelunasan
     * Route: GET /pembayaran
     */
    public function index($f3) {
        $this->requirePermission('pembayaran:view', '/po');

        $search = trim($f3->get('GET.q') ?? '');
        $filterJenis = trim($f3->get('GET.jenis_layanan') ?? '');

        $sql = "SELECT p.*, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, o.jenis_layanan_opti,
                       c.nmcustomer AS nama_perusahaan, c.pt_cv,
                       po.id AS po_id_real, po.nomor_po, po.biaya AS biaya_po
                FROM opti_pembayaran p
                JOIN order_layanan o ON p.order_id = o.id
                JOIN tb_customer c ON o.id_customer = c.id_customer
                LEFT JOIN po ON p.po_id = po.id OR (p.po_id IS NULL AND o.id = po.order_id)
                WHERE 1=1";
        
        $params = array();
        $idx = 1;

        if (!empty($filterJenis)) {
            $sql .= " AND o.jenis_layanan_opti = ?";
            $params[$idx++] = $filterJenis;
        }

        if (!empty($search)) {
            $sql .= " AND (o.nomor_order LIKE ? OR po.nomor_po LIKE ? OR c.nmcustomer LIKE ? OR p.keterangan LIKE ?)";
            $wildcard = "%{$search}%";
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
            $params[$idx++] = $wildcard;
        }

        $sql .= " ORDER BY p.tanggal_bayar DESC, p.id DESC";
        $daftarPembayaran = $this->db->exec($sql, $params);

        // Rekapitulasi Status Pembayaran Per PO Resmi
        $rekapPoRaw = $this->db->exec(
            "SELECT p.id AS po_id, p.nomor_po, p.biaya, p.tim_kerja, p.status AS status_po,
                    o.id AS order_id, o.nomor_order, o.judul_kegiatan, o.jenis_layanan_opti,
                    c.nmcustomer AS nama_perusahaan, c.pt_cv,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE (po_id = p.id OR (po_id IS NULL AND order_id = o.id)) AND status_verifikasi = 'terverifikasi'), 0) AS total_dibayar,
                    (SELECT COUNT(id) FROM opti_pembayaran WHERE (po_id = p.id OR (po_id IS NULL AND order_id = o.id))) AS jml_termin
             FROM po p
             JOIN order_layanan o ON p.order_id = o.id
             JOIN tb_customer c ON o.id_customer = c.id_customer
             ORDER BY p.id ASC"
        );

        $rekapPo = array();
        $countLunas = 0;
        $countSebagian = 0;
        $countBelum = 0;

        foreach ($rekapPoRaw as $item) {
            $biaya = (float)$item['biaya'];
            $dibayar = (float)$item['total_dibayar'];
            $sisa = max(0, $biaya - $dibayar);
            $persen = $biaya > 0 ? min(100, round(($dibayar / $biaya) * 100, 1)) : 0;

            if ($dibayar >= $biaya && $biaya > 0) {
                $statusLunas = 'lunas';
                $countLunas++;
            } elseif ($dibayar > 0) {
                $statusLunas = 'sebagian';
                $countSebagian++;
            } else {
                $statusLunas = 'belum';
                $countBelum++;
            }

            $item['sisa_piutang'] = $sisa;
            $item['persen_lunas'] = $persen;
            $item['status_lunas'] = $statusLunas;
            $rekapPo[] = $item;
        }

        // Total Rekapitulasi Finansial
        $totalTerbayar = (float)($this->db->exec("SELECT SUM(jumlah) AS t FROM opti_pembayaran WHERE status_verifikasi = 'terverifikasi'")[0]['t'] ?? 0);
        $totalTagihan  = (float)($this->db->exec("SELECT SUM(biaya) AS t FROM po")[0]['t'] ?? 0);
        if ($totalTagihan == 0) {
            $totalTagihan = (float)($this->db->exec("SELECT SUM(estimasi_biaya) AS t FROM order_layanan")[0]['t'] ?? 0);
        }
        $sisaPiutang = max(0, $totalTagihan - $totalTerbayar);
        $persenRealisasi = $totalTagihan > 0 ? round(($totalTerbayar / $totalTagihan) * 100, 1) : 0;

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();

        $f3->set('daftar_pembayaran', $daftarPembayaran);
        $f3->set('rekap_po', $rekapPo);
        $f3->set('total_terbayar', $totalTerbayar);
        $f3->set('total_tagihan', $totalTagihan);
        $f3->set('sisa_piutang', $sisaPiutang);
        $f3->set('persen_realisasi', $persenRealisasi);
        $f3->set('count_lunas', $countLunas);
        $f3->set('count_sebagian', $countSebagian);
        $f3->set('count_belum', $countBelum);
        $f3->set('search_q', $search);
        $f3->set('filter_jenis_layanan', $filterJenis);
        $f3->set('mask_client_name', $maskEnabled);

        $this->render('pembayaran/index.html', 'Rekapitulasi Keuangan & Pembayaran - OPTI Tracker', 'pembayaran');
    }

    /**
     * Menampilkan form input pembayaran baru untuk suatu order
     * Route: GET /pembayaran/tambah
     */
    public function tambah($f3) {
        $this->requirePermission('pembayaran:create', '/pembayaran');

        $orderId = (int)($f3->get('GET.order_id') ?? 0);
        $orderModel = new OrderLayanan($this->db);
        
        $daftarOrder = $this->db->exec(
            "SELECT o.id, o.nomor_order, o.judul_kegiatan, o.estimasi_biaya, 
                    c.nmcustomer AS nama_perusahaan,
                    p.id AS po_id, p.nomor_po, p.biaya AS biaya_po,
                    COALESCE((SELECT SUM(jumlah) FROM opti_pembayaran WHERE order_id = o.id AND status_verifikasi = 'terverifikasi'), 0) AS terbayar
             FROM order_layanan o
             JOIN tb_customer c ON o.id_customer = c.id_customer
             LEFT JOIN po p ON o.id = p.order_id
             ORDER BY o.id DESC"
        );

        $selectedOrder = null;
        if ($orderId > 0) {
            $selectedOrder = $orderModel->getDetail($orderId);
        }

        $f3->set('daftar_order', $daftarOrder);
        $f3->set('selected_order', $selectedOrder);
        $f3->set('order_id', $orderId);

        $this->render('pembayaran/form.html', 'Input Pembayaran Termin - OPTI Tracker', 'pembayaran');
    }

    /**
     * Memproses penyimpanan data pembayaran
     * Route: POST /pembayaran/simpan
     */
    public function simpan($f3) {
        $this->requirePermission('pembayaran:create', '/pembayaran');

        $post = $f3->get('POST');

        $orderId      = (int)($post['order_id'] ?? 0);
        $poId         = !empty($post['po_id']) ? (int)$post['po_id'] : null;
        $terminKe     = (int)($post['termin_ke'] ?? 1);
        $tanggalBayar = $post['tanggal_bayar'] ?? date('Y-m-d');
        $jumlah       = (float)($post['jumlah'] ?? 0);
        $keterangan   = trim($post['keterangan'] ?? '');

        if ($orderId <= 0 || $jumlah <= 0) {
            $this->setFlashError('Pilih order layanan dan masukkan nominal pembayaran valid.');
            $f3->reroute('/pembayaran/tambah');
            return;
        }

        try {
            $pembayaranModel = new OptiPembayaran($this->db);
            $pembayaranModel->tambahPembayaran(array(
                'order_id'          => $orderId,
                'po_id'             => $poId,
                'termin_ke'         => $terminKe,
                'tanggal_bayar'     => $tanggalBayar,
                'jumlah'            => $jumlah,
                'keterangan'        => $keterangan,
                'status_verifikasi' => 'terverifikasi'
            ));

            $this->setFlashSuccess('Transaksi pembayaran berhasil dicatat.');
            
            if ($poId) {
                $f3->reroute("/po/{$poId}");
            } else {
                $f3->reroute('/pembayaran');
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal mencatat pembayaran: ' . $e->getMessage());
            $f3->reroute('/pembayaran/tambah');
        }
    }

    /**
     * Menghapus transaksi pembayaran
     * Route: POST /pembayaran/@id/hapus
     */
    public function hapus($f3, $params) {
        $this->requirePermission('pembayaran:edit', '/pembayaran');

        $id = (int)($params['id'] ?? 0);
        $redirectPoId = (int)($f3->get('POST.redirect_po_id') ?? 0);

        try {
            $pembayaranModel = new OptiPembayaran($this->db);
            $pembayaranModel->hapus($id);

            $this->setFlashSuccess('Transaksi pembayaran berhasil dihapus.');
            if ($redirectPoId > 0) {
                $f3->reroute("/po/{$redirectPoId}");
            } else {
                $f3->reroute('/pembayaran');
            }
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menghapus pembayaran: ' . $e->getMessage());
            $f3->reroute('/pembayaran');
        }
    }
}
