<?php

/**
 * Controller untuk mengelola Master Customer / Klien (tb_customer)
 * 
 * TODO: Konfirmasi ke admin database bahwa hak akses tb_customer adalah read-only dari sisi OPTI
 * kecuali menambah customer baru yang menggunakan layanan OPTI.
 */
class CustomerController extends Controller {

    /**
     * Menampilkan daftar semua customer
     * Route: GET /customer atau GET /klien
     */
    public function index($f3) {
        $customerModel = new Customer($this->db);
        $daftarCustomer = $customerModel->all();

        $f3->set('daftar_klien', $daftarCustomer);
        $f3->set('daftar_customer', $daftarCustomer);
        $this->render('klien/index.html', 'Daftar Mitra / Customer', 'klien');
    }

    /**
     * Menampilkan form input customer baru
     * Route: GET /customer/tambah atau GET /klien/tambah
     */
    public function tambah($f3) {
        $f3->set('customer', null);
        $f3->set('klien', null);
        $this->render('klien/form.html', 'Tambah Customer Baru', 'klien');
    }

    /**
     * Memproses penyimpanan customer baru
     * Route: POST /customer/simpan atau POST /klien/simpan
     */
    public function simpan($f3) {
        $post = $f3->get('POST');

        $namaPerusahaan = trim($post['nmcustomer'] ?? '');
        $ptCv           = trim($post['pt_cv'] ?? 'PT');
        $pic            = trim($post['contactperson_opti'] ?? $post['contactperson'] ?? '');
        $telepon        = trim($post['nohpcontactperson_opti'] ?? $post['notelpcustomer'] ?? '');
        $email          = trim($post['emailcustomer'] ?? '');
        $alamat         = trim($post['alamatcustomer'] ?? '');

        if (empty($namaPerusahaan)) {
            $this->setFlashError('Nama perusahaan / instansi customer wajib diisi!');
            $f3->reroute('/klien/tambah');
            return;
        }

        try {
            $customerModel = new Customer($this->db);
            $customerModel->simpanBaru(array(
                'nmcustomer'             => $namaPerusahaan,
                'pt_cv'                  => $ptCv,
                'contactperson'          => $pic,
                'contactperson_opti'     => $pic,
                'notelpcustomer'         => $telepon,
                'nohpcontactperson_opti' => $telepon,
                'emailcustomer'          => $email,
                'alamatcustomer'         => $alamat
            ));

            $this->setFlashSuccess("Customer <strong>{$namaPerusahaan}</strong> berhasil ditambahkan.");
            $f3->reroute('/klien');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menyimpan customer: ' . $e->getMessage());
            $f3->reroute('/klien/tambah');
        }
    }

    /**
     * Menampilkan form edit customer
     * Route: GET /customer/@id/edit atau GET /klien/@id/edit
     */
    public function edit($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        $customerModel = new Customer($this->db);
        $customer = $customerModel->getById($id);

        if (!$customer) {
            $this->setFlashError("Customer #{$id} tidak ditemukan.");
            $f3->reroute('/klien');
            return;
        }

        $f3->set('customer', $customer->cast());
        $f3->set('klien', $customer->cast());
        $this->render('klien/form.html', 'Edit Data Customer', 'klien');
    }

    /**
     * Memproses update data customer
     * Route: POST /customer/@id/update atau POST /klien/@id/update
     */
    public function update($f3, $params) {
        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $namaPerusahaan = trim($post['nmcustomer'] ?? ($post['nama_perusahaan'] ?? ''));
        $ptCv           = trim($post['pt_cv'] ?? 'PT');
        $pic            = trim($post['contactperson'] ?? ($post['pic'] ?? ''));
        $alamat         = trim($post['alamatcustomer'] ?? ($post['alamat'] ?? ''));
        $telepon        = trim($post['notelpcustomer'] ?? ($post['telepon'] ?? ''));
        $email          = trim($post['emailcustomer'] ?? ($post['email'] ?? ''));

        if (empty($namaPerusahaan)) {
            $this->setFlashError('Nama perusahaan/mitra wajib diisi.');
            $f3->reroute("/klien/{$id}/edit");
            return;
        }

        try {
            $customerModel = new Customer($this->db);
            $customerModel->updateData($id, array(
                'nmcustomer'             => $namaPerusahaan,
                'pt_cv'                  => $ptCv,
                'contactperson'          => $pic,
                'alamatcustomer'         => $alamat,
                'notelpcustomer'         => $telepon,
                'emailcustomer'          => $email,
                'contactperson_opti'     => $pic,
                'nohpcontactperson_opti' => $telepon
            ));

            $this->setFlashSuccess("Data customer '{$namaPerusahaan}' berhasil diperbarui.");
            $f3->reroute('/klien');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui customer: ' . $e->getMessage());
            $f3->reroute("/klien/{$id}/edit");
        }
    }

    /**
     * Menghapus data customer
     * Route: POST /customer/@id/hapus atau POST /klien/@id/hapus
     */
    public function hapus($f3, $params) {
        $id = (int)($params['id'] ?? 0);

        try {
            $customerModel = new Customer($this->db);
            $customerModel->hapus($id);

            $this->setFlashSuccess("Data customer #{$id} berhasil dihapus.");
            $f3->reroute('/klien');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal menghapus customer: ' . $e->getMessage());
            $f3->reroute('/klien');
        }
    }
}
