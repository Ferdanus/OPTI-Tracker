<?php

/**
 * Model OptiFieldConfig
 * Mengelola konfigurasi dinamis show/hide field per tim dan privasi masking nama
 */
class OptiFieldConfig extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_field_config');
    }

    /**
     * Ambil seluruh konfigurasi field
     */
    public function getAll(): array {
        return $this->db->exec("SELECT * FROM opti_field_config ORDER BY jenis_layanan_opti ASC, entity ASC, id ASC");
    }

    /**
     * Ambil konfigurasi field aktif untuk tim tertentu ('selulosa' / 'lingkungan')
     */
    public function getConfigForTim(string $jenisLayananOpti): array {
        $rows = $this->db->exec(
            "SELECT * FROM opti_field_config WHERE jenis_layanan_opti IN (?, 'global')",
            array(1 => $jenisLayananOpti)
        );

        $config = array();
        foreach ($rows as $r) {
            $config[$r['field_name']] = array(
                'is_visible'       => (bool)$r['is_visible'],
                'is_required'      => (bool)$r['is_required'],
                'default_value'    => $r['default_value'],
                'field_label'      => $r['field_label'],
                'mask_for_privacy' => (bool)$r['mask_for_privacy']
            );
        }
        return $config;
    }

    /**
     * Update status visibility & requirement field
     */
    public function updateFieldConfig(int $id, bool $isVisible, bool $isRequired, ?string $defaultValue = null): bool {
        $this->db->exec(
            "UPDATE opti_field_config SET is_visible = ?, is_required = ?, default_value = ?, updated_at = NOW() WHERE id = ?",
            array(1 => $isVisible ? 1 : 0, 2 => $isRequired ? 1 : 0, 3 => $defaultValue, 4 => $id)
        );
        return true;
    }

    /**
     * Cek apakah fitur penyamaran nama klien aktif secara global
     */
    public function isMaskClientNameEnabled(): bool {
        $res = $this->db->exec("SELECT is_visible FROM opti_field_config WHERE field_name = 'mask_client_name' LIMIT 1");
        return !empty($res[0]['is_visible']);
    }

    /**
     * Toggle status penyamaran nama klien
     */
    public function toggleMaskClientName(bool $enabled): bool {
        $this->db->exec(
            "UPDATE opti_field_config SET is_visible = ?, updated_at = NOW() WHERE field_name = 'mask_client_name'",
            array(1 => $enabled ? 1 : 0)
        );
        return true;
    }
}
