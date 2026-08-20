<?php

/**
 * Model OptiUserAlertConfig
 * Mengelola pengaturan alert dan notifikasi pribadi per user
 */
class OptiUserAlertConfig extends \DB\SQL\Mapper {

    public static $ALERT_TYPES = array(
        'alert_po_deadline'        => 'Notifikasi PO Mendekati Batas Waktu (Deadline)',
        'alert_pembayaran_pending' => 'Notifikasi Order dengan Pembayaran / Tagihan Belum Lunas',
        'alert_approval_needed'    => 'Notifikasi Dokumen PO Membutuhkan Approval / Validasi'
    );

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'opti_user_alert_config');
    }

    /**
     * Ambil pengaturan alert untuk user tertentu
     */
    public function getByUserId(int $idUser): array {
        $rows = $this->db->exec(
            "SELECT * FROM opti_user_alert_config WHERE id_user = ?",
            array(1 => $idUser)
        );

        $alerts = array();
        foreach ($rows as $r) {
            $alerts[$r['alert_key']] = array(
                'is_enabled'     => (bool)$r['is_enabled'],
                'threshold_days' => (int)$r['threshold_days']
            );
        }

        // Lengkapi dengan default jika ada yang belum diset
        foreach (self::$ALERT_TYPES as $key => $label) {
            if (!isset($alerts[$key])) {
                $alerts[$key] = array('is_enabled' => false, 'threshold_days' => 3);
            }
        }

        return $alerts;
    }

    /**
     * Simpan pengaturan alert user
     */
    public function saveUserAlert(int $idUser, string $alertKey, bool $isEnabled, int $thresholdDays = 3): bool {
        $this->db->exec(
            "INSERT INTO opti_user_alert_config (id_user, alert_key, is_enabled, threshold_days, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), threshold_days = VALUES(threshold_days), updated_at = NOW()",
            array(1 => $idUser, 2 => $alertKey, 3 => $isEnabled ? 1 : 0, 4 => $thresholdDays)
        );
        return true;
    }
}
