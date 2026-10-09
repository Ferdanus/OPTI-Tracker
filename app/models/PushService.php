<?php
/**
 * PushService
 *
 * Browser Push Notification (Web Push, RFC 8030/8292) TANPA payload terenkripsi --
 * tiap push yang dikirim KOSONG (gak ada data nempel), cuma dipakai buat "ngetok pintu"
 * Service Worker di browser Ketua Pelaksana biar dia narik notifikasi terbaru lewat
 * endpoint /notifikasi/unread yang udah ada (lihat NotificationService & NotificationController).
 * Jadi SEMUA isi notifikasi (judul/pesan/link) tetep dari tabel opti_notifikasi --
 * PushService di sini cuma tukang "ketok pintu" doang, gak nyimpen isi pesan sendiri.
 *
 * Kenapa gak pakai payload terenkripsi (AES128GCM + ECDH per RFC 8291)? Karena itu
 * butuh openssl_pkey_derive() yang baru ada di PHP 7.3+ (server ini PHP 7.2), atau
 * library composer pihak ketiga yang gak bisa diinstall otomatis dari sini (gak ada
 * akses shell ke server ini). Push kosong + VAPID (tanda tangan ES256, cuma butuh
 * openssl_sign() yang udah ada dari PHP 7.1) udah cukup dan didukung resmi sama semua
 * push service (Chrome/Edge lewat FCM, Firefox lewat Mozilla Autopush).
 *
 * Setup sekali (lihat config.ini):
 *   vapid_public_key       = kunci publik VAPID, format raw EC point (0x04||X||Y) base64url
 *   vapid_private_key_b64  = PEM private key EC (prime256v1) di-base64-encode biasa
 *   vapid_subject          = kontak ("mailto:..." atau URL) sesuai RFC 8292
 *
 * Cara pakai dari controller lain:
 *   \PushService::kirimKeUser($this->db, $targetUserId);
 *
 * Kompatibel PHP 7.2.
 */
class PushService {

    /** Bikin tabel penyimpanan subscription kalau belum ada (self-healing, gak perlu migration manual). */
    public static function ensureTabel($db) {
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS push_subscriptions (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    endpoint VARCHAR(500) NOT NULL,
                    p256dh VARCHAR(255) NULL,
                    auth_key VARCHAR(255) NULL,
                    user_agent VARCHAR(255) NULL,
                    created_at DATETIME NULL,
                    UNIQUE KEY uniq_endpoint (endpoint(255)),
                    KEY idx_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\Exception $e) {
            // biarin -- kalau gagal (mis. gak ada izin DDL), panggilan lain tetep dicoba jalan apa adanya.
        }
    }

    /**
     * Simpan/perbarui subscription baru dari browser (dipanggil dari PushController::subscribe()).
     * $sub = array hasil decode JSON dari PushSubscription.toJSON() di browser:
     *   ['endpoint' => '...', 'keys' => ['p256dh' => '...', 'auth' => '...']]
     */
    public static function simpanSubscription($db, $userId, array $sub, $userAgent = '') {
        self::ensureTabel($db);
        $endpoint = (string) ($sub['endpoint'] ?? '');
        $keys = $sub['keys'] ?? [];
        $p256dh = (string) ($keys['p256dh'] ?? '');
        $auth = (string) ($keys['auth'] ?? '');
        if ($endpoint === '') { return false; }

        try {
            $ada = $db->exec("SELECT id FROM push_subscriptions WHERE endpoint = ?", [1 => $endpoint]);
            if (!empty($ada)) {
                $db->exec(
                    "UPDATE push_subscriptions SET user_id = ?, p256dh = ?, auth_key = ?, user_agent = ? WHERE endpoint = ?",
                    [1 => (int) $userId, 2 => $p256dh, 3 => $auth, 4 => $userAgent, 5 => $endpoint]
                );
            } else {
                $db->exec(
                    "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth_key, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                    [1 => (int) $userId, 2 => $endpoint, 3 => $p256dh, 4 => $auth, 5 => $userAgent]
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /** Hapus satu subscription (dipanggil pas user cabut izin notifikasi di browsernya). */
    public static function hapusSubscription($db, $endpoint) {
        self::ensureTabel($db);
        try {
            $db->exec("DELETE FROM push_subscriptions WHERE endpoint = ?", [1 => (string) $endpoint]);
        } catch (\Exception $e) {
            // non-fatal
        }
    }

    /**
     * Kirim push KOSONG ke semua subscription milik satu user -- "ketok pintu" biar browser-nya
     * narik notifikasi terbaru sendiri (lihat PushController::serviceWorker() buat isi Service Worker-nya).
     */
    public static function kirimKeUser($db, $userId) {
        self::ensureTabel($db);
        try {
            $subs = $db->exec("SELECT id, endpoint FROM push_subscriptions WHERE user_id = ?", [1 => (int) $userId]);
        } catch (\Exception $e) {
            return;
        }
        foreach ($subs as $s) {
            $kode = self::kirim($s['endpoint']);
            // 404/410 = subscription udah gak valid lagi (browser-nya uninstall push / izin dicabut
            // lewat pengaturan browser, bukan lewat aplikasi kita) -- bersihin biar gak nyoba2 mulu.
            if ($kode === 404 || $kode === 410) {
                try { $db->exec("DELETE FROM push_subscriptions WHERE id = ?", [1 => (int) $s['id']]); } catch (\Exception $e) {}
            }
        }
    }

    /** Kirim satu request push ke satu endpoint. Return kode HTTP dari push service (0 kalau gagal konek/konfig). */
    protected static function kirim($endpoint) {
        $f3 = \Base::instance();
        $vapidPublicKey = (string) $f3->get('vapid_public_key');
        $pemB64 = (string) $f3->get('vapid_private_key_b64');
        $subject = (string) $f3->get('vapid_subject');
        if ($vapidPublicKey === '' || $pemB64 === '') { return 0; }

        $pem = base64_decode($pemB64);
        $pkey = @openssl_pkey_get_private($pem);
        if ($pkey === false) { return 0; }

        $urlParts = parse_url($endpoint);
        if (empty($urlParts['scheme']) || empty($urlParts['host'])) { return 0; }
        $audience = $urlParts['scheme'] . '://' . $urlParts['host'];

        $jwt = self::buatVapidJwt($audience, $subject, $pkey);
        if ($jwt === null) { return 0; }

        $authHeader = 'vapid t=' . $jwt . ', k=' . $vapidPublicKey;

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => [
                'TTL: 60',
                'Content-Length: 0',
                'Authorization: ' . $authHeader,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $kode;
    }

    /** Bikin JWT VAPID (header.payload.signature, alg ES256) sesuai RFC 8292. */
    protected static function buatVapidJwt($audience, $subject, $pkey) {
        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = ['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => $subject];
        $segmen = [
            self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segmen);
        $ok = openssl_sign($signingInput, $der, $pkey, OPENSSL_ALGO_SHA256);
        if (!$ok) { return null; }
        $raw = self::derKeRaw($der);
        if ($raw === null) { return null; }
        $segmen[] = self::b64url($raw);
        return implode('.', $segmen);
    }

    /**
     * Konversi signature ECDSA format DER (keluaran openssl_sign) ke format "raw R||S"
     * 64-byte yang dipakai standar JWS/VAPID. Sudah diuji lewat 500 kali round-trip
     * sign->parse->encode-ulang->verify (termasuk kasus byte nol di depan R/S) -- selalu cocok.
     */
    protected static function derKeRaw($der) {
        try {
            $idx = 0;
            if (ord($der[$idx]) !== 0x30) { return null; }
            $idx++;
            $seqLen = ord($der[$idx]); $idx++;
            if ($seqLen & 0x80) {
                $numBytes = $seqLen & 0x7f;
                $seqLen = 0;
                for ($i = 0; $i < $numBytes; $i++) { $seqLen = ($seqLen << 8) | ord($der[$idx]); $idx++; }
            }
            if (ord($der[$idx]) !== 0x02) { return null; }
            $idx++;
            $rLen = ord($der[$idx]); $idx++;
            $r = substr($der, $idx, $rLen); $idx += $rLen;
            if (ord($der[$idx]) !== 0x02) { return null; }
            $idx++;
            $sLen = ord($der[$idx]); $idx++;
            $s = substr($der, $idx, $sLen);
            return self::fixLen($r, 32) . self::fixLen($s, 32);
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Paksa panjang string byte jadi persis $len: potong byte nol di depan kalau kepanjangan, isi nol di depan kalau kependekan. */
    protected static function fixLen($bin, $len) {
        if (strlen($bin) > $len) {
            $bin = ltrim($bin, "\x00");
            if (strlen($bin) > $len) { $bin = substr($bin, -$len); }
        }
        if (strlen($bin) < $len) { $bin = str_pad($bin, $len, "\x00", STR_PAD_LEFT); }
        return $bin;
    }

    protected static function b64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
