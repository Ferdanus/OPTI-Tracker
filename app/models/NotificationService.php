<?php

class NotificationService
{
    /**
     * Kirim notifikasi baru
     *
     * @param \DB\SQL $db
     * @param array $data [order_id, po_id, target_role, target_user_id, target_layanan, judul, pesan, tipe, icon, link_url, created_by, created_by_name]
     * @return int ID notifikasi yang dibuat
     */
    public static function send(\DB\SQL $db, array $data): int
    {
        $orderId       = !empty($data['order_id']) ? (int)$data['order_id'] : null;
        $poId          = !empty($data['po_id']) ? (int)$data['po_id'] : null;
        $targetRole    = $data['target_role'] ?? 'all';
        $targetUserId  = !empty($data['target_user_id']) ? (int)$data['target_user_id'] : null;
        $targetLayanan = $data['target_layanan'] ?? 'semua';
        $judul         = $data['judul'] ?? 'Pemberitahuan Sistem';
        $pesan         = $data['pesan'] ?? '';
        $tipe          = $data['tipe'] ?? 'info';
        $icon          = $data['icon'] ?? 'bi-bell-fill';
        $linkUrl       = $data['link_url'] ?? '/order';
        $createdBy     = !empty($data['created_by']) ? (int)$data['created_by'] : ($_SESSION['user_id'] ?? null);
        $createdByName = $data['created_by_name'] ?? ($_SESSION['nama_lengkap'] ?? 'Sistem OPTI');

        $sql = "INSERT INTO `opti_notifikasi` 
                (`order_id`, `po_id`, `target_role`, `target_user_id`, `target_layanan`, `judul`, `pesan`, `tipe`, `icon`, `link_url`, `created_by`, `created_by_name`, `created_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $db->exec($sql, [
            $orderId,
            $poId,
            $targetRole,
            $targetUserId,
            $targetLayanan,
            $judul,
            $pesan,
            $tipe,
            $icon,
            $linkUrl,
            $createdBy,
            $createdByName
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Ambil daftar notifikasi untuk user yang sedang login
     */
    public static function getUserNotifications(\DB\SQL $db, int $userId, string $role, string $layanan = 'semua', int $limit = 15): array
    {
        $where = [];
        $params = [];

        if ($role === 'superadmin') {
            // Superadmin melihat semua notifikasi alur
            $where[] = "1=1";
        } else {
            // Role matching / targeted user
            $roleCond = "(target_role = 'all' OR target_role = ?)";
            $params[] = $role;

            if ($userId > 0) {
                $roleCond .= " OR target_user_id = ?";
                $params[] = $userId;
            }

            // Layanan matching
            if ($role === 'ketua_tim' && in_array($layanan, ['selulosa', 'lingkungan'])) {
                $layananCond = "(target_layanan = 'semua' OR target_layanan = ? OR target_layanan = 'belum_ditentukan' OR target_layanan IS NULL OR target_layanan = '')";
                $params[] = $layanan;
                $where[] = "({$roleCond}) AND {$layananCond}";
            } else {
                $where[] = "({$roleCond})";
            }
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM `opti_notifikasi` WHERE {$whereClause} ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit;

        $rows = $db->exec($sql, $params);
        foreach ($rows as &$r) {
            $r['time_ago'] = self::timeAgo($r['created_at']);
        }
        unset($r);

        return $rows;
    }

    /**
     * Hitung jumlah notifikasi belum dibaca (unread count)
     */
    public static function getUnreadCount(\DB\SQL $db, int $userId, string $role, string $layanan = 'semua'): int
    {
        $where = ["is_read = 0"];
        $params = [];

        if ($role === 'superadmin') {
            // Superadmin
        } else {
            $roleCond = "(target_role = 'all' OR target_role = ?)";
            $params[] = $role;

            if ($userId > 0) {
                $roleCond .= " OR target_user_id = ?";
                $params[] = $userId;
            }

            if ($role === 'ketua_tim' && in_array($layanan, ['selulosa', 'lingkungan'])) {
                $layananCond = "(target_layanan = 'semua' OR target_layanan = ? OR target_layanan = 'belum_ditentukan' OR target_layanan IS NULL OR target_layanan = '')";
                $params[] = $layanan;
                $where[] = "({$roleCond}) AND {$layananCond}";
            } else {
                $where[] = "({$roleCond})";
            }
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as c FROM `opti_notifikasi` WHERE {$whereClause}";
        $res = $db->exec($sql, $params);

        return (int)($res[0]['c'] ?? 0);
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca
     */
    public static function markAsRead(\DB\SQL $db, int $notifId): bool
    {
        $db->exec("UPDATE `opti_notifikasi` SET is_read = 1 WHERE id = ?", [$notifId]);
        return true;
    }

    /**
     * Tandai semua notifikasi untuk peran saat ini sebagai sudah dibaca
     */
    public static function markAllAsRead(\DB\SQL $db, int $userId, string $role, string $layanan = 'semua'): bool
    {
        if ($role === 'superadmin') {
            $db->exec("UPDATE `opti_notifikasi` SET is_read = 1 WHERE is_read = 0");
        } else {
            $where = ["is_read = 0"];
            $params = [];

            $roleCond = "(target_role = 'all' OR target_role = ?)";
            $params[] = $role;

            if ($userId > 0) {
                $roleCond .= " OR target_user_id = ?";
                $params[] = $userId;
            }

            if ($role === 'ketua_tim' && in_array($layanan, ['selulosa', 'lingkungan'])) {
                $layananCond = "(target_layanan = 'semua' OR target_layanan = ?)";
                $params[] = $layanan;
                $where[] = "({$roleCond}) AND {$layananCond}";
            } else {
                $where[] = "({$roleCond})";
            }

            $whereClause = implode(' AND ', $where);
            $db->exec("UPDATE `opti_notifikasi` SET is_read = 1 WHERE {$whereClause}", $params);
        }
        return true;
    }

    /**
     * Format waktu relatif Bahasa Indonesia
     */
    public static function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'Baru saja';
        }
        $minutes = round($diff / 60);
        if ($minutes < 60) {
            return $minutes . ' menit lalu';
        }
        $hours = round($diff / 3600);
        if ($hours < 24) {
            return $hours . ' jam lalu';
        }
        $days = round($diff / 86400);
        if ($days < 7) {
            return $days . ' hari lalu';
        }
        return date('d M Y, H:i', $time);
    }
}