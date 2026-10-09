<?php
/**
 * PushController
 *
 * Endpoint pendukung fitur Web Push Notification (desktop) -- Ketua Pelaksana dapat
 * notifikasi "ketok pintu" dari browser biarpun lagi gak stand by / sudah logout dari
 * tab website-nya, khusus buat kasus revisi PO dari Keuangan lewat chat (lihat
 * PoReviewController::chatKirim() & bukaKembali()).
 *
 * TIDAK lewat email atau WhatsApp -- murni Web Push (RFC 8030) + VAPID (RFC 8292),
 * payload kosong, isi notifikasi ditarik browser sendiri dari /notifikasi/unread
 * lewat Service Worker (lihat serviceWorker() di bawah).
 *
 * Kompatibel PHP 7.2.
 */
class PushController extends Controller {

    /** POST /push/subscribe -- simpan PushSubscription baru dari browser user yang lagi login. */
    public function subscribe($f3) {
        $this->requireAuth();
        $uid = (int) $this->getUserId();

        $raw = trim((string) $f3->get('POST.subscription'));
        $sub = json_decode($raw, true);
        if (!is_array($sub) || empty($sub['endpoint'])) {
            $this->keluarkanJson(['ok' => false, 'pesan' => 'Data subscription tidak valid.'], 422);
            return;
        }

        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $ok = \PushService::simpanSubscription($this->db, $uid, $sub, $ua);

        $this->keluarkanJson(['ok' => (bool) $ok]);
    }

    /** POST /push/unsubscribe -- hapus satu subscription (user cabut izin notifikasi di browsernya). */
    public function unsubscribe($f3) {
        $this->requireAuth();
        $endpoint = trim((string) $f3->get('POST.endpoint'));
        if ($endpoint !== '') {
            \PushService::hapusSubscription($this->db, $endpoint);
        }
        $this->keluarkanJson(['ok' => true]);
    }

    /**
     * GET /service-worker -- Service Worker. Disajikan lewat route (bukan file statis)
     * supaya bisa nyisipin BASE path F3 (perlu buat folder "Mini OPTI Tracker" yang
     * namanya ada spasinya) ke dalam URL fetch ke /notifikasi/unread.
     *
     * [PENTING] Path SENGAJA tanpa ekstensi ".js" (bukan "/sw.js") -- PHP built-in dev
     * server (php -S) nganggep path berakhiran ".js" sebagai file statis dan langsung
     * 404 duluan TANPA pernah masuk ke router F3 kalau file fisiknya gak ada di disk.
     * Browser tetap mau daftar Service Worker dari path tanpa ekstensi, asal
     * Content-Type response-nya "application/javascript" (sudah diatur di bawah).
     */
    public function serviceWorker($f3) {
        $base = (string) $f3->get('BASE');
        $body = self::isiServiceWorker($base);

        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache');
        // [PENTING] PHP built-in dev server gak ngirim Content-Length otomatis buat output
        // yang di-echo (cuma ngandelin "Connection: close" buat nandain akhir body). Chrome
        // butuh Content-Length (atau chunked encoding) pas fetch Service Worker script --
        // tanpa ini, registrasi gagal dengan error generik "unknown error fetching the
        // script" walau isi responsenya sebenarnya lengkap dan valid.
        header('Content-Length: ' . strlen($body));

        echo $body;
        exit;
    }

    protected static function isiServiceWorker($base) {
        $baseJs = json_encode($base, JSON_UNESCAPED_SLASHES);
        return <<<JS
// Service Worker -- OPTI Tracker Web Push.
// Push yang diterima SENGAJA kosong (gak ada data nempel) -- cuma dipakai buat
// "ketok pintu" biar Service Worker ini narik notifikasi terbaru dari endpoint
// yang udah ada (/notifikasi/unread), lalu nampilin satu notifikasi desktop.
var OPTI_BASE = {$baseJs};

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    event.waitUntil(
        fetch(OPTI_BASE + '/notifikasi/unread', { credentials: 'include', cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var item = (data && data.items && data.items.length) ? data.items[0] : null;
                var judul = item ? item.judul : 'Pemberitahuan OPTI Tracker';
                var pesan = item ? item.pesan : 'Ada pembaruan baru menunggu Anda.';
                var tujuan = (item && item.link_url) ? item.link_url : (OPTI_BASE + '/po-kegiatan/daftar');
                return self.registration.showNotification(judul, {
                    body: pesan,
                    tag: 'opti-revisi-po',
                    renotify: true,
                    data: { tujuan: tujuan }
                });
            })
            .catch(function () {
                return self.registration.showNotification('Pemberitahuan OPTI Tracker', {
                    body: 'Ada pembaruan baru menunggu Anda.',
                    tag: 'opti-revisi-po'
                });
            })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var tujuan = (event.notification.data && event.notification.data.tujuan)
        ? event.notification.data.tujuan
        : OPTI_BASE + '/po-kegiatan/daftar';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (var i = 0; i < list.length; i++) {
                if ('focus' in list[i]) { list[i].navigate(tujuan); return list[i].focus(); }
            }
            if (clients.openWindow) { return clients.openWindow(tujuan); }
        })
    );
});
JS;
    }

    protected function keluarkanJson($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
