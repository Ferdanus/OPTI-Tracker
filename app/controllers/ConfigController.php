<?php

/**
 * Controller untuk mengelola Konfigurasi Dinamis Show/Hide Field per Tim,
 * Penetapan Pejabat/Ketua Tim OPTI, dan Privasi Data Masking
 * Dilengkapi Guard Permission (Ketua Tim, Admin & Superadmin)
 */
class ConfigController extends Controller {

    /**
     * Halaman pengaturan konfigurasi field dinamis & privasi
     * Route: GET /config atau GET /pengaturan
     */
    public function index($f3) {
        $this->requirePermission('config:team', '/po');

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $allConfigs = $fieldConfigModel->getAll() ?: array();

        $maskEnabled = $fieldConfigModel->isMaskClientNameEnabled();

        // Ambil data personil balai dan ketua tim aktif secara dinamis dari database master
        $userModel = new ArsipUser($this->db);
        $allInternalUsers = $userModel->getAllInternalUsers();
        $katimSelulosa = $userModel->getKetuaTim('selulosa');
        $katimLingkungan = $userModel->getKetuaTim('lingkungan');

        $f3->set('all_configs', $allConfigs);
        $f3->set('field_configs', $allConfigs);
        $f3->set('mask_enabled', $maskEnabled);
        $f3->set('mask_client_name', $maskEnabled);

        $f3->set('internal_users', $allInternalUsers);
        $f3->set('katim_selulosa', $katimSelulosa);
        $f3->set('katim_lingkungan', $katimLingkungan);

        $this->render('config/index.html', 'Pengaturan Konfigurasi Dinamis & Privasi - OPTI Tracker', 'config');
    }

    /**
     * Penetapan / Pergantian Pejabat Ketua Tim OPTI secara dinamis
     * Route: POST /config/set-ketua-tim
     */
    public function setKetuaTim($f3) {
        $this->requirePermission('config:team', '/config');

        $post = $f3->get('POST');
        $idSelulosa   = (int)($post['id_user_selulosa'] ?? 0);
        $idLingkungan = (int)($post['id_user_lingkungan'] ?? 0);

        if ($idSelulosa <= 0 || $idLingkungan <= 0) {
            $this->setFlashError('Pilih personil yang valid untuk kedua divisi layanan OPTI.');
            $f3->reroute('/config');
            return;
        }

        try {
            $userModel = new ArsipUser($this->db);
            $userModel->setKetuaTim('selulosa', $idSelulosa);
            $userModel->setKetuaTim('lingkungan', $idLingkungan);

            $this->setFlashSuccess('Pejabat Ketua Tim OPTI Selulosa dan Lingkungan berhasil diperbarui secara dinamis!');
            $f3->reroute('/config');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui penetapan Ketua Tim: ' . $e->getMessage());
            $f3->reroute('/config');
        }
    }

    /**
     * Update status visibility & requirement untuk sebuah field
     * Route: POST /config/field/@id/update
     */
    public function updateField($f3, $params) {
        $this->requirePermission('config:team', '/config');

        $id = (int)($params['id'] ?? 0);
        $post = $f3->get('POST');

        $isVisible    = !empty($post['is_visible']);
        $isRequired   = !empty($post['is_required']);
        $defaultValue = trim($post['default_value'] ?? '') ?: null;

        try {
            $fieldConfigModel = new OptiFieldConfig($this->db);
            $fieldConfigModel->updateFieldConfig($id, $isVisible, $isRequired, $defaultValue);

            $this->setFlashSuccess('Pengaturan konfigurasi field berhasil diperbarui.');
            $f3->reroute('/config');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui konfigurasi field: ' . $e->getMessage());
            $f3->reroute('/config');
        }
    }

    /**
     * Toggle penyamaran nama klien (Privacy Masking)
     * Route: POST /config/toggle-masking
     */
    public function toggleMasking($f3) {
        $this->requirePermission('config:team', '/config');

        $fieldConfigModel = new OptiFieldConfig($this->db);
        $currentMask = $fieldConfigModel->isMaskClientNameEnabled();
        $newMask = !$currentMask;

        try {
            $fieldConfigModel->toggleMaskClientName($newMask);
            $_SESSION['mask_client_name'] = $newMask;
            $f3->set('SESSION.mask_client_name', $newMask);

            $statusText = $newMask ? 'diaktifkan (Nama klien disamarkan)' : 'dinonaktifkan (Nama klien ditampilkan penuh)';
            $this->setFlashSuccess("Pengaturan privasi data berhasil diperbarui: Penyamaran nama klien {$statusText}.");
            $f3->reroute('/config');
        } catch (\Exception $e) {
            $this->setFlashError('Gagal memperbarui pengaturan privasi: ' . $e->getMessage());
            $f3->reroute('/config');
        }
    }
}
