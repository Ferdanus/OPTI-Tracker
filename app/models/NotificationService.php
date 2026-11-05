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
     * Build WHERE clause condition and parameters for target user / role matching
     */
    protected static function buildTargetCondition(int $userId, string $role, string $layanan = 'semua'): array
    {
        if ($role === 'superadmin') {
            return ['1=1', []];
        }

        $params = [];
        $conditions = [];

        // 1. Direct user target (e.g. PIC Proposal, specific assignee)
        if ($userId > 0) {
            $conditions[] = "n.target_user_id = ?";
            $params[] = $userId;
        }

        // 2. Role-based matching (only when target_user_id IS NULL or 0)
        $roleMatch = [];
        $isMitra = in_array($role, ['ketua_tim_mitra', 'tim_mitra_industri', 'admin_order', 'tim_mitra', 'mitra']) || ($role === 'ketua_tim' && $layanan === 'mitra');
        $isKeuangan = in_array($role, ['ketua_tim_keuangan', 'keuangan']) || ($role === 'ketua_tim' && $layanan === 'keuangan');

        if ($isMitra) {
            $roleMatch[] = "n.target_role IN ('ketua_tim_mitra', 'tim_mitra_industri', 'admin_order', 'tim_mitra', 'mitra', 'all')";
        } elseif ($isKeuangan) {
            $roleMatch[] = "n.target_role IN ('ketua_tim_keuangan', 'keuangan', 'all')";
        } elseif ($role === 'ketua_tim') {
            $roleMatch[] = "n.target_role IN ('ketua_tim', 'all')";
        } elseif (in_array($role, ['tim_kerja', 'tim_kerja_selulosa', 'tim_kerja_lingkungan', 'tim_kerja_pengujian', 'tim_kerja_sertifikasi'])) {
            $roleMatch[] = "n.target_role IN ('tim_kerja', 'all')";
        } else {
            $roleMatch[] = "(n.target_role = ? OR n.target_role = 'all')";
            $params[] = $role;
        }

        $roleSql = implode(' OR ', $roleMatch);

        // Filter layanan for ketua_tim when matching role-based
        if (in_array($role, ['ketua_tim']) && in_array($layanan, ['selulosa', 'lingkungan'])) {
            $roleSql = "({$roleSql}) AND (n.target_layanan = 'semua' OR n.target_layanan = ? OR n.target_layanan = 'belum_ditentukan' OR n.target_layanan IS NULL OR n.target_layanan = '')";
            $params[] = $layanan;
        }

        $conditions[] = "((n.target_user_id IS NULL OR n.target_user_id = 0) AND ({$roleSql}))";

        $fullCondition = "(" . implode(' OR ', $conditions) . ")";
        return [$fullCondition, $params];
    }

    /**
     * Ambil daftar notifikasi untuk user yang sedang login
     */
    public static function getUserNotifications(\DB\SQL $db, int $userId, string $role, string $layanan = 'semua', int $limit = 15, string $search = '', bool $unreadOnly = false): array
    {
        list($whereClause, $targetParams) = self::buildTargetCondition($userId, $role, $layanan);

        $params = [$userId];
        $params = array_merge($params, $targetParams);

        $unreadSql = '';
        if ($unreadOnly) {
            $unreadSql = " AND (nr.id IS NULL AND (n.target_user_id != {$userId} OR n.target_user_id IS NULL OR n.is_read = 0))";
        }

        $searchSql = '';
        if (!empty(trim($search))) {
            $searchSql = " AND (n.judul LIKE ? OR n.pesan LIKE ? OR n.created_by_name LIKE ?)";
            $keyword = '%' . trim($search) . '%';
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $sql = "SELECT n.*, 
                       COALESCE(nr.read_at, n.read_at) AS user_read_at,
                       CASE 
                           WHEN nr.id IS NOT NULL THEN 1 
                           WHEN n.target_user_id = {$userId} AND n.is_read = 1 THEN 1 
                           ELSE 0 
                       END AS is_read
                FROM `opti_notifikasi` n
                LEFT JOIN `opti_notifikasi_user_read` nr ON nr.notifikasi_id = n.id AND nr.user_id = ?
                WHERE {$whereClause}{$unreadSql}{$searchSql} 
                ORDER BY n.created_at DESC, n.id DESC 
                LIMIT " . (int)$limit;

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
        list($whereClause, $targetParams) = self::buildTargetCondition($userId, $role, $layanan);
        $params = [$userId];
        $params = array_merge($params, $targetParams);

        $sql = "SELECT COUNT(*) as c 
                FROM `opti_notifikasi` n
                LEFT JOIN `opti_notifikasi_user_read` nr ON nr.notifikasi_id = n.id AND nr.user_id = ?
                WHERE {$whereClause} 
                  AND (nr.id IS NULL AND (n.target_user_id != {$userId} OR n.target_user_id IS NULL OR n.is_read = 0))";

        $res = $db->exec($sql, $params);
        return (int)($res[0]['c'] ?? 0);
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca per user
     */
    public static function markAsRead(\DB\SQL $db, int $notifId, int $userId = 0): bool
    {
        if ($userId > 0) {
            $db->exec(
                "INSERT INTO `opti_notifikasi_user_read` (`notifikasi_id`, `user_id`, `read_at`)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE read_at = read_at",
                [$notifId, $userId]
            );
        }
        $db->exec("UPDATE `opti_notifikasi` SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id = ?", [$notifId]);
        return true;
    }

    /**
     * Tandai semua notifikasi untuk peran saat ini sebagai sudah dibaca
     */
    public static function markAllAsRead(\DB\SQL $db, int $userId, string $role, string $layanan = 'semua'): bool
    {
        $notifs = self::getUserNotifications($db, $userId, $role, $layanan, 500, '', true);
        foreach ($notifs as $n) {
            self::markAsRead($db, (int)$n['id'], $userId);
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
