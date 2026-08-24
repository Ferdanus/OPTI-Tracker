<?php
    /**
     * Dashboard & Daftar semua PO dengan multi-filter
     * Route: GET /po
     */
class DashboardController extends Controller
{
    public function index($f3)
    {
        // Ambil role dari session
        $role = $f3->get('SESSION.role');

        // Pastikan hanya admin_order yang bisa masuk
        if ($role !== 'admin_order') {
            $f3->reroute('/login');
            return;
        }

        // Tampilkan dashboard Admin Order
        $this->render(
            'admin-order/dashboard.html',
            'Dashboard Admin Order - OPTI Tracker BBSPJIS',
            'dashboard'
        );
    }
}