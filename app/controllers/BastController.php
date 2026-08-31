<?php

/**
 * Controller untuk mengelola Berita Acara Serah Terima (BAST) & Penutupan Order
 */
class BastController extends Controller {

    /**
     * Menampilkan form pembuatan / edit BAST
     * Route: GET /order/@id/bast/buat
     */
    public function formBast($f3, $params) {
        $this->requirePermission('order:view', '/order');

        $orderId = (int)($params['id'] ?? 0);
        $orderModel = new OrderLayanan($this->db);
        $order = $orderModel->getDetail($orderId);

        if (!$order) {
            $this->setFlashError("Order Layanan #{$orderId} tidak ditemukan.");
            $f3->reroute('/order');
            return;
        }

        $bastModel = new OptiBast($this->db);
        $bast = $bastModel->getByOrderId($orderId);

        $poDetail = null;
        if (!empty($order['po_id'])) {
            $poModel = new Po($this->db);
            $poDetail = $poModel->getDetail((int)$order['po_id']);
        }

        $nomorBastOtomatis = $bastModel->generateNomorBast();

        $f3->set('order', $order);
        $f3->set('po', $poDetail);
        $f3->set('bast', $bast);
        $f3->set('nomor_bast_otomatis', $nomorBastOtomatis);

        $this->render('bast/form.html', "Berita Acara Serah Terima - Order #{$order['nomor_order']}", 'order');
    }

    /**
     * Memproses penyimpanan BAST (Draft / Final)
     * Route: POST /order/@id/bast/simpan
     */
    public function simpanBast($f3, $params) {
        $this->requirePermission('order:edit', '/order');

        $orderId = (int)($params['id'] ?? 0);
        $post    = $f3->get('POST');

        try {
            $orderModel = new OrderLayanan($this->db);
            $order = $orderModel->getDetail($orderId);
            if (!$order) {
                throw new \Exception("Order Layanan #{$orderId} tidak ditemukan.");
            }

            $bastModel = new OptiBast($this->db);
            $existing = $bastModel->getByOrderId($orderId);

            $fileDokumen = $existing ? $existing['file_dokumen_bast'] : null;

            // Upload Dokumen BAST
            if (!empty($_FILES['file_bast']['name']) && $_FILES['file_bast']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/bast/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $ext = strtolower(pathinfo($_FILES['file_bast']['name'], PATHINFO_EXTENSION));
                $namaFile = 'bast_ord_' . $orderId . '_' . time() . '.' . $ext;
                $targetFile = $uploadDir . $namaFile;
                if (move_uploaded_file($_FILES['file_bast']['tmp_name'], $targetFile)) {
                    $fileDokumen = $targetFile;
                }
            }

            $data = array(
                'order_id'              => $orderId,
                'po_id'                 => !empty($order['po_id']) ? (int)$order['po_id'] : null,
                'nomor_bast'            => trim($post['nomor_bast'] ?? ''),
                'tanggal_bast'         => $post['tanggal_bast'] ?? date('Y-m-d'),
                'pihak_pertama_nama'    => trim($post['pihak_pertama_nama'] ?? 'Kepala BBSPJIS'),
                'pihak_pertama_jabatan' => trim($post['pihak_pertama_jabatan'] ?? 'Kepala Balai Besar'),
                'pihak_kedua_nama'      => trim($post['pihak_kedua_nama'] ?? ($order['pic'] ?: 'Pimpinan Mitra')),
                'pihak_kedua_jabatan'   => trim($post['pihak_kedua_jabatan'] ?? 'Pimpinan Perusahaan'),
                'judul_pekerjaan'       => trim($post['judul_pekerjaan'] ?? $order['judul_kegiatan']),
                'ringkasan_serah_terima'=> trim($post['ringkasan_serah_terima'] ?? ''),
                'file_dokumen_bast'     => $fileDokumen,
                'status_bast'           => $post['status_bast'] ?? 'draft'
            );

            if ($existing) {
                $bastModel->updateData((int)$existing['id'], $data);
                $this->setFlashSuccess("Dokumen BAST #{$order['nomor_order']} berhasil diperbarui.");
            } else {
                $bastModel->simpanBaru($data);
                $this->setFlashSuccess("Dokumen BAST resmi berhasil diterbitkan untuk Order #{$order['nomor_order']}.");
            }

            $f3->reroute("/order/{$orderId}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan BAST: ' . $e->getMessage());
            $f3->reroute("/order/{$orderId}/bast/buat");
        }
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

            if (!$bast) {
                // Buat BAST otomatis jika belum ada sebelum closing
                $orderModel = new OrderLayanan($this->db);
                $order = $orderModel->getDetail($orderId);
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

            $this->setFlashSuccess("Order Layanan #{$orderId} berhasil DITUTUP & DIARSIPKAN (Selesai). Seluruh siklus layanan tuntas!");
            $f3->reroute("/order/{$orderId}");
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menutup order: ' . $e->getMessage());
            $f3->reroute("/order/{$orderId}");
        }
    }
}