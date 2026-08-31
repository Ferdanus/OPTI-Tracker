<?php

class NotificationController extends Controller
{
    /**
     * GET /notifikasi/unread
     * Mengembalikan data JSON notifikasi belum dibaca untuk polling / dynamic bubble UI
     */
    public function getUnread($f3)
    {
        $userId  = (int)($this->getUserId() ?? 0);
        $role    = $this->getUserRole();
        $layanan = $this->getUserLayanan();

        $unreadCount = NotificationService::getUnreadCount($this->db, $userId, $role, $layanan);
        $items = NotificationService::getUserNotifications($this->db, $userId, $role, $layanan, 10);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'count'   => $unreadCount,
            'items'   => $items
        ]);
        exit;
    }

    /**
     * POST /notifikasi/mark-read/@id
     * Menandai 1 notifikasi telah dibaca
     */
    public function markRead($f3, $params)
    {
        $notifId = (int)($params['id'] ?? 0);
        if ($notifId > 0) {
            NotificationService::markAsRead($this->db, $notifId);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * POST /notifikasi/mark-all-read
     * Menandai seluruh notifikasi user saat ini telah dibaca
     */
    public function markAllRead($f3)
    {
        $userId  = (int)($this->getUserId() ?? 0);
        $role    = $this->getUserRole();
        $layanan = $this->getUserLayanan();

        NotificationService::markAllAsRead($this->db, $userId, $role, $layanan);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * GET /notifikasi
     * Halaman lengkap daftar riwayat notifikasi
     */
    public function index($f3)
    {
        $this->requireAuth();
        $userId  = (int)($this->getUserId() ?? 0);
        $role    = $this->getUserRole();
        $layanan = $this->getUserLayanan();

        $allNotif = NotificationService::getUserNotifications($this->db, $userId, $role, $layanan, 50);
        $f3->set('daftar_notifikasi_semua', $allNotif);

        $this->render('notifikasi/index.html', 'Pusat Pemberitahuan & Notifikasi', 'dashboard');
    }
}