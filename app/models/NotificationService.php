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
            $conditions[] = "target_user_id = ?";
            $params[] = $userId;
        }

        // 2. Role-based matching (only when target_user_id IS NULL or 0)
        $roleMatch = [];
        if ($role === 'ketua_tim_mitra') {
            $roleMatch[] = "target_role IN ('ketua_tim_mitra', 'tim_mitra_industri', 'admin_order', 'tim_mitra', 'all')";
        } elseif ($role === 'tim_mitra_industri' || $role === 'admin_order' || $role === 'tim_mitra') {
            // Staf tim mitra does NOT get ketua_tim_mitra notifications
            $roleMatch[] = "target_role IN ('tim_mitra_industri', 'admin_order', 'tim_mitra', 'all')";
        } elseif ($role === 'ketua_tim_keuangan') {
            $roleMatch[] = "target_role IN ('ketua_tim_keuangan', 'keuangan', 'all')";
        } elseif ($role === 'keuangan') {
            // Staf keuangan does NOT get ketua_tim_keuangan notifications
            $roleMatch[] = "target_role IN ('keuangan', 'all')";
        } elseif ($role === 'ketua_tim') {
            $roleMatch[] = "target_role IN ('ketua_tim', 'all')";
        } elseif ($role === 'tim_kerja') {
            $roleMatch[] = "target_role IN ('tim_kerja', 'all')";
        } else {
            $roleMatch[] = "(target_role = ? OR target_role = 'all')";
            $params[] = $role;
        }

        $roleSql = implode(' OR ', $roleMatch);

        // Filter layanan for ketua_tim & tim_kerja when matching role-based
        if (in_array($role, ['ketua_tim', 'tim_kerja']) && in_array($layanan, ['selulosa', 'lingkungan'])) {
            $roleSql = "({$roleSql}) AND (target_layanan = 'semua' OR target_layanan = ? OR target_layanan = 'belum_ditentukan' OR target_layanan IS NULL OR target_layanan = '')";
            $params[] = $layanan;
        }

        $conditions[] = "((target_user_id IS NULL OR target_user_id = 0) AND ({$roleSql}))";

        $fullCondition = "(" . implode(' OR ', $conditions) . ")";
        return [$fullCondition, $params];
    }

    /**
     * Ambil daftar notifikasi untuk user yang sedang login
     */
    public static function getUserNotifications(\DB\SQL $db, int $userId, string $role, string $layanan = 'semua', int $limit = 15, string $search = ''): array
    {
        list($whereClause, $params) = self::buildTargetCondition($userId, $role, $layanan);
        
        $searchSql = '';
        if (!empty(trim($search))) {
            $searchSql = " AND (judul LIKE ? OR pesan LIKE ? OR created_by_name LIKE ?)";
            $keyword = '%' . trim($search) . '%';
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $sql = "SELECT * FROM `opti_notifikasi` WHERE {$whereClause}{$searchSql} ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit;

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
        list($whereClause, $params) = self::buildTargetCondition($userId, $role, $layanan);
        $sql = "SELECT COUNT(*) as c FROM `opti_notifikasi` WHERE is_read = 0 AND {$whereClause}";
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
        list($whereClause, $params) = self::buildTargetCondition($userId, $role, $layanan);
        $sql = "UPDATE `opti_notifikasi` SET is_read = 1 WHERE is_read = 0 AND {$whereClause}";
        $db->exec($sql, $params);
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