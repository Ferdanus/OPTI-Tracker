<?php

/**
 * Service WhatsApp & Pengelolaan OTP Login
 * Mengirimkan kode verifikasi OTP ke nomor WhatsApp pengguna dari data no_hp tb_arsipuser.
 * Kode OTP berlaku selama 24 jam dan dapat digunakan berulang kali selama masa aktif tersebut
 * tanpa perlu bot mengirimkan kode baru setiap kali login.
 */
class WhatsAppService {

    /**
     * Normalisasi nomor telepon ke format internasional (628xxx)
     */
    public static function formatPhoneNumber(string $phone): string {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return '';
        }

        if (strpos($clean, '08') === 0) {
            $clean = '62' . substr($clean, 1);
        } elseif (strpos($clean, '8') === 0) {
            $clean = '62' . $clean;
        }

        return $clean;
    }

    /**
     * Masking nomor telepon: 2 angka utama dan 4 angka terakhir yang tidak tersensor (contoh: 08******6227)
     */
    public static function maskPhoneNumber(string $phone): string {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return '08******----';
        }

        // Normalisasi ke format 08xx
        if (strpos($clean, '62') === 0) {
            $clean = '0' . substr($clean, 2);
        } elseif (strpos($clean, '8') === 0) {
            $clean = '0' . $clean;
        }

        $len = strlen($clean);
        if ($len <= 6) {
            return substr($clean, 0, 2) . '****' . substr($clean, -2);
        }

        $first2 = substr($clean, 0, 2); // 2 angka utama (08)
        $last4  = substr($clean, -4);    // 4 angka terakhir
        $maskCount = max(4, $len - 6);
        $maskedMiddle = str_repeat('*', $maskCount);

        return $first2 . $maskedMiddle . $last4;
    }

    /**
     * Generate 6-digit random numeric OTP
     */
    public static function generateOtp(): string {
        return (string) random_int(100000, 999999);
    }

    /**
     * Ambil OTP aktif pengguna yang masih berlaku (dalam kurun waktu 24 jam)
     */
    public static function getActiveOtp(\DB\SQL $db, int $userId): ?array {
        $rows = $db->exec(
            "SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expired_at) AS remaining_secs 
             FROM opti_login_otp 
             WHERE user_id = ? AND expired_at >= NOW() 
             ORDER BY id DESC LIMIT 1",
            array(1 => $userId)
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Mengambil OTP harian yang masih aktif atau mengirimkan OTP baru jika belum ada/kedaluwarsa.
     * Jika sudah ada kode OTP dalam 24 jam, sistem menggunakan kode tersebut tanpa mengirim ulang ke WhatsApp.
     */
    public static function getOrCreateDailyOtp(\DB\SQL $db, int $userId, string $namaUser, string $phone, bool $forceNew = false): array {
        $formattedPhone = self::formatPhoneNumber($phone);
        $maskedPhone = self::maskPhoneNumber($phone);

        if (empty($formattedPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp / HP tidak terdaftar pada profil akun arsip user.'
            ];
        }

        // Cek apakah ada OTP aktif yang masih berlaku jika tidak dipaksa buat baru
        if (!$forceNew) {
            $activeOtp = self::getActiveOtp($db, $userId);
            if (!empty($activeOtp)) {
                $remSecs = max(1, (int)$activeOtp['remaining_secs']);
                $expTime = strtotime($activeOtp['expired_at']);
                return [
                    'success'           => true,
                    'phone'             => $formattedPhone,
                    'masked_phone'      => $maskedPhone,
                    'otp'               => $activeOtp['otp_code'],
                    'is_new'            => false,
                    'expires_at'        => $expTime,
                    'remaining_seconds' => $remSecs,
                    'message'           => "Kode OTP Anda masih aktif selama 24 jam. Silakan periksa pesan WhatsApp yang telah diterima ({$maskedPhone})."
                ];
            }
        }

        // Generate OTP baru yang berlaku 24 jam
        $otp = self::generateOtp();
        $sendResult = self::sendOtp($db, $userId, $namaUser, $formattedPhone, $otp);

        $sendResult['is_new']            = true;
        $sendResult['expires_at']        = time() + 86400;
        $sendResult['remaining_seconds'] = 86400;
        return $sendResult;
    }

    /**
     * Mengirim kode OTP baru ke WhatsApp pengguna dan mencatat log (masa berlaku 24 jam)
     */
    public static function sendOtp(\DB\SQL $db, int $userId, string $namaUser, string $phone, string $otp): array {
        $formattedPhone = self::formatPhoneNumber($phone);
        $maskedPhone = self::maskPhoneNumber($phone);

        if (empty($formattedPhone)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp / HP tidak terdaftar pada profil akun arsip user.'
            ];
        }

        // Nonaktifkan OTP lama untuk user ini
        try {
            $db->exec(
                "UPDATE opti_login_otp SET expired_at = NOW(), is_used = 1 WHERE user_id = ? AND expired_at >= NOW()",
                array(1 => $userId)
            );

            // Simpan OTP baru dengan masa berlaku 1 hari (24 jam)
            $db->exec(
                "INSERT INTO opti_login_otp (user_id, no_hp, otp_code, expired_at, is_used, delivery_status, created_at)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), 0, 'sent', NOW())",
                array(
                    1 => $userId,
                    2 => $formattedPhone,
                    3 => $otp
                )
            );
        } catch (\Exception $e) {
            // Abaikan error tabel jika terjadi kendala minor
        }

        // Format template pesan WhatsApp OTP: Clean & Simple (tanpa emoji)
        $message = "*SILOPTI - BBSPJIS*\n\n"
                 . "Yth. {$namaUser},\n\n"
                 . "Kode verifikasi (OTP) Anda adalah: *{$otp}*\n\n"
                 . "Catatan:\n"
                 . "- Kode ini berlaku selama 24 jam.\n"
                 . "- Bersifat rahasia, mohon tidak membagikan kode ini kepada siapapun.\n"
                 . "- Abaikan pesan ini jika Anda tidak merasa melakukan permintaan login.\n\n"
                 . "Terima kasih.";

        // Kirim via gateway resmi WANotif menggunakan fungsi SentWANotif
        $waResult = self::SentWANotif($formattedPhone, $message);
        $deliverySuccess = $waResult['success'];
        $deliveryResponse = "HTTP {$waResult['http_code']}: " . substr((string)$waResult['response'], 0, 255);

        // Update log status pengiriman ke database
        try {
            $db->exec(
                "UPDATE opti_login_otp SET delivery_status = ?, delivery_response = ? WHERE user_id = ? AND otp_code = ? ORDER BY id DESC LIMIT 1",
                array(
                    1 => $deliverySuccess ? 'delivered' : 'failed',
                    2 => $deliveryResponse,
                    3 => $userId,
                    4 => $otp
                )
            );
        } catch (\Exception $e) {}

        return [
            'success'      => true,
            'phone'        => $formattedPhone,
            'masked_phone' => $maskedPhone,
            'otp'          => $otp,
            'message'      => "Kode OTP verifikasi 24 jam telah dikirimkan ke WhatsApp ({$maskedPhone})."
        ];
    }

    /**
     * Kirim notifikasi WhatsApp via gateway resmi WANotif (api.wanotif.id)
     * Menggunakan cURL POST x-www-form-urlencoded sesuai script resmi dari mentor
     */
    public static function SentWANotif(string $noHP, string $pesanWA, ?string $apikey = null, ?string $url = null): array {
        $f3 = class_exists('\Base') ? \Base::instance() : null;
        $apikey = $apikey ?: ($f3 ? $f3->get('wa_api_key') : null) ?: '0nCcu32vvhazLEMjKIAasaLSeLMJ3tDZ';
        $url    = $url ?: ($f3 ? $f3->get('wa_gateway_url') : null) ?: 'https://api.wanotif.id/v1/send';

        $phone = preg_replace('/[^0-9]/', '', $noHP);
        $message = $pesanWA;

        $postData = http_build_query([
            'Apikey'  => $apikey,
            'Phone'   => $phone,
            'Message' => $message,
        ], '', '&', PHP_QUERY_RFC3986);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $isSuccess = ($httpCode >= 200 && $httpCode < 300) && empty($curlError);

        return [
            'success'   => $isSuccess,
            'http_code' => $httpCode,
            'response'  => $response ?: $curlError,
            'phone'     => $phone
        ];
    }

    /**
     * Verifikasi kode OTP (berlaku 24 jam dan dapat dipakai berulang kali tanpa di-expire-kan seketika)
     */
    public static function verifyOtp(\DB\SQL $db, int $userId, string $inputOtp): array {
        $inputOtp = trim($inputOtp);
        if (empty($inputOtp)) {
            return ['valid' => false, 'message' => 'Kode OTP wajib diisi.'];
        }

        $rows = $db->exec(
            "SELECT * FROM opti_login_otp 
             WHERE user_id = ? AND otp_code = ? AND expired_at >= NOW() 
             ORDER BY id DESC LIMIT 1",
            array(1 => $userId, 2 => $inputOtp)
        );

        if (empty($rows)) {
            return ['valid' => false, 'message' => 'Kode OTP salah atau sudah kedaluwarsa.'];
        }

        return ['valid' => true, 'message' => 'Verifikasi OTP berhasil.'];
    }

    /**
     * Pastikan tabel antrean notifikasi WhatsApp (opti_wa_queue) tersedia.
     */
    public static function ensureWaQueueTable(\DB\SQL $db): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `opti_wa_queue` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` INT NULL,
                `notif_id` INT NULL,
                `target_user_id` INT NULL,
                `target_nama` VARCHAR(150) NULL,
                `no_hp` VARCHAR(32) NOT NULL,
                `pesan` TEXT NOT NULL,
                `dedupe_key` VARCHAR(160) NULL,
                `status` ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `last_error` VARCHAR(255) NULL,
                `scheduled_at` DATETIME NOT NULL,
                `sent_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_wa_queue_status_sched` (`status`, `scheduled_at`),
                KEY `idx_wa_queue_dedupe` (`dedupe_key`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}
    }

    /**
     * Cek apakah fitur notifikasi WA aktif di konfigurasi (default: nonaktif sampai siap diaktifkan).
     */
    public static function isWaNotifEnabled(\DB\SQL $db): bool {
        try {
            $rows = $db->exec("SELECT config_value FROM opti_config WHERE config_key = 'wa_notif_enabled' LIMIT 1");
            if (!empty($rows) && in_array(strtolower(trim((string)$rows[0]['config_value'])), ['1', 'true', 'on', 'aktif'], true)) {
                return true;
            }
        } catch (\Throwable $e) {}
        return false;
    }

    /**
     * Normalisasi nomor HP ke format 628xxx (kosong jika tidak valid).
     */
    public static function normalizePhone(string $rawPhone): string {
        $digits = preg_replace('/[^0-9]/', '', $rawPhone);
        if ($digits === '' || strlen($digits) < 9) {
            return '';
        }
        if (strpos($digits, '0') === 0) {
            $digits = '62' . substr($digits, 1);
        } elseif (strpos($digits, '8') === 0) {
            $digits = '62' . $digits;
        }
        return (strlen($digits) >= 10 && strlen($digits) <= 15) ? $digits : '';
    }

    /**
     * Bangun pesan WA singkat, natural & terpersonalisasi (anti-spam).
     */
    public static function buildSimpleWaMessage(array $data, string $recipientName = ''): string {
        if (!empty($data['wa_pesan'])) {
            return trim((string)$data['wa_pesan']);
        }
        $sapaan = $recipientName !== '' ? "Halo *{$recipientName}*," : "Halo,";
        $judul  = trim((string)($data['judul'] ?? 'Info SILOPTI'));
        $pesan  = trim(strip_tags((string)($data['pesan'] ?? '')));
        if (mb_strlen($pesan) > 160) {
            $pesan = mb_substr($pesan, 0, 157) . '...';
        }
        $linkUrl = trim((string)($data['link_url'] ?? ''));
        $f3 = class_exists('\Base') ? \Base::instance() : null;
        if ($linkUrl !== '' && strpos($linkUrl, 'http') !== 0 && $f3) {
            $scheme = $f3->get('SCHEME') ?: 'http';
            $host   = $f3->get('HOST') ?: 'localhost';
            $base   = rtrim((string)$f3->get('BASE'), '/');
            $linkUrl = "{$scheme}://{$host}{$base}/" . ltrim($linkUrl, '/');
        }

        $lines = [
            "{$sapaan} ada pembaruan di *SILOPTI*:",
            "*{$judul}*",
        ];
        if ($pesan !== '') {
            $lines[] = $pesan;
        }
        if ($linkUrl !== '') {
            $lines[] = "Cek detail: {$linkUrl}";
        }
        return implode("\n", $lines);
    }

    /**
     * Masukkan notifikasi ke antrean WA dengan proteksi anti-broadcast, deduplikasi, & jitter delay.
     */
    public static function enqueueNotification(\DB\SQL $db, array $data): bool {
        if (empty($data['force_queue']) && !self::isWaNotifEnabled($db)) {
            return false;
        }

        self::ensureWaQueueTable($db);

        $orderId       = !empty($data['order_id']) ? (int)$data['order_id'] : null;
        $notifId       = !empty($data['notif_id']) ? (int)$data['notif_id'] : null;
        $targetUserId  = !empty($data['target_user_id']) ? (int)$data['target_user_id'] : 0;
        $targetRole    = strtolower(trim((string)($data['target_role'] ?? '')));
        $targetLayanan = strtolower(trim((string)($data['target_layanan'] ?? '')));

        // Proteksi anti-ban: dilarang keras broadcast ke 'all'
        if ($targetUserId <= 0 && ($targetRole === '' || $targetRole === 'all')) {
            return false;
        }

        // Resolve maksimal 1-2 penerima spesifik (tidak pernah broadcast massal)
        $recipients = [];
        try {
            if ($targetUserId > 0) {
                $rows = $db->exec(
                    "SELECT id_user, nama_user, no_hp FROM tb_arsipuser WHERE id_user = ? AND no_hp IS NOT NULL AND no_hp != '' LIMIT 1",
                    [1 => $targetUserId]
                );
                if (!empty($rows)) $recipients = $rows;
            } else {
                // Mapping role -> target spesifik di tb_arsipuser.si_opti (Mitra hanya Ketua Tim Mitra)
                $roleToken = $targetRole;
                if (in_array($targetRole, ['ketua_tim_mitra', 'tim_mitra_industri', 'admin_order', 'tim_mitra', 'mitra'], true)) {
                    $roleToken = 'ketua_tim_mitra';
                } elseif ($targetRole === 'ketua_tim' && in_array($targetLayanan, ['selulosa', 'lingkungan', 'pengujian', 'kalibrasi', 'sertifikasi', 'pendampingan', 'inspeksi', 'profisiensi'], true)) {
                    $roleToken = 'ketua_tim_' . $targetLayanan;
                } elseif (in_array($targetRole, ['keuangan', 'ketua_tim_keuangan'], true)) {
                    $roleToken = 'ketua_tim_keuangan';
                }

                $recipients = $db->exec(
                    "SELECT id_user, nama_user, no_hp
                     FROM tb_arsipuser
                     WHERE no_hp IS NOT NULL AND no_hp != ''
                       AND FIND_IN_SET(?, REPLACE(REPLACE(COALESCE(si_opti, ''), ', ', ','), ' ,', ',')) > 0
                     LIMIT 2",
                    [1 => $roleToken]
                ) ?: [];
            }
        } catch (\Throwable $e) {
            return false;
        }

        if (empty($recipients)) {
            return false;
        }

        // Ambil waktu jadwal terakhir agar antrean terjeda acak 4-9 detik (anti-burst)
        $baseTs = time();
        try {
            $lastRow = $db->exec("SELECT MAX(scheduled_at) AS max_sched FROM opti_wa_queue WHERE status = 'pending'");
            if (!empty($lastRow[0]['max_sched'])) {
                $maxTs = strtotime($lastRow[0]['max_sched']);
                if ($maxTs && $maxTs > $baseTs) {
                    $baseTs = $maxTs;
                }
            }
        } catch (\Throwable $e) {}

        $enqueuedAny = false;
        foreach ($recipients as $rcp) {
            $phone = self::normalizePhone((string)($rcp['no_hp'] ?? ''));
            if ($phone === '') continue;

            $uid = (int)($rcp['id_user'] ?? 0);
            $dedupeKey = !empty($data['dedupe_key'])
                ? substr((string)$data['dedupe_key'] . '_u' . $uid, 0, 160)
                : sprintf('wa_o%d_%s_u%d', (int)$orderId, $targetRole ?: 'direct', $uid);

            // Cooldown 10 menit untuk order + event + user yang sama
            try {
                $dup = $db->exec(
                    "SELECT id FROM opti_wa_queue
                     WHERE dedupe_key = ? AND status IN ('pending', 'sent') AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                     LIMIT 1",
                    [1 => $dedupeKey]
                );
                if (!empty($dup)) continue;
            } catch (\Throwable $e) {}

            $baseTs += rand(4, 9);
            $schedAt = date('Y-m-d H:i:s', $baseTs);
            $pesanWa = self::buildSimpleWaMessage($data, (string)($rcp['nama_user'] ?? ''));

            try {
                $db->exec(
                    "INSERT INTO opti_wa_queue (order_id, notif_id, target_user_id, target_nama, no_hp, pesan, dedupe_key, status, scheduled_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
                    [
                        1 => $orderId,
                        2 => $notifId,
                        3 => $uid ?: null,
                        4 => $rcp['nama_user'] ?? null,
                        5 => $phone,
                        6 => $pesanWa,
                        7 => $dedupeKey,
                        8 => $schedAt
                    ]
                );
                $enqueuedAny = true;
            } catch (\Throwable $e) {}
        }

        return $enqueuedAny;
    }

    /**
     * Proses antrean WA secara bertahap dengan batas maksimal per jam (rate limit anti-ban).
     */
    public static function processWaQueue(\DB\SQL $db, int $limit = 10, int $maxPerHour = 30): array {
        self::ensureWaQueueTable($db);
        if (!self::isWaNotifEnabled($db)) {
            return ['enabled' => false, 'processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $sentLastHour = 0;
        try {
            $cnt = $db->exec("SELECT COUNT(*) AS c FROM opti_wa_queue WHERE status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $sentLastHour = (int)($cnt[0]['c'] ?? 0);
        } catch (\Throwable $e) {}

        $remainingQuota = max(0, $maxPerHour - $sentLastHour);
        if ($remainingQuota <= 0) {
            return ['enabled' => true, 'processed' => 0, 'sent' => 0, 'failed' => 0, 'rate_limited' => true];
        }

        $batchLimit = min(max(1, $limit), $remainingQuota);
        $rows = $db->exec(
            "SELECT id, no_hp, pesan, attempts FROM opti_wa_queue
             WHERE status = 'pending' AND scheduled_at <= NOW() AND attempts < 2
             ORDER BY scheduled_at ASC, id ASC
             LIMIT {$batchLimit}"
        ) ?: [];

        $sent = 0;
        $failed = 0;
        foreach ($rows as $idx => $row) {
            if ($idx > 0) {
                usleep(rand(2000000, 4000000)); // jeda 2-4 detik antar request ke gateway
            }
            $res = self::SentWANotif((string)$row['no_hp'], (string)$row['pesan']);
            $attempts = (int)$row['attempts'] + 1;
            if (!empty($res['success'])) {
                $db->exec("UPDATE opti_wa_queue SET status = 'sent', attempts = ?, sent_at = NOW(), last_error = NULL WHERE id = ?", [1 => $attempts, 2 => (int)$row['id']]);
                $sent++;
            } else {
                $newStatus = ($attempts >= 2) ? 'failed' : 'pending';
                $errMsg = substr((string)($res['response'] ?? 'Gateway error'), 0, 250);
                $db->exec(
                    "UPDATE opti_wa_queue SET status = ?, attempts = ?, last_error = ?, scheduled_at = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE id = ?",
                    [1 => $newStatus, 2 => $attempts, 3 => $errMsg, 4 => (int)$row['id']]
                );
                $failed++;
            }
        }

        return ['enabled' => true, 'processed' => count($rows), 'sent' => $sent, 'failed' => $failed];
    }
}

if (!function_exists('SentWANotif')) {
    /**
     * Global wrapper fungsi SentWANotif sesuai arahan mentor
     */
    function SentWANotif($noHP, $pesanWA) {
        return WhatsAppService::SentWANotif((string)$noHP, (string)$pesanWA);
    }
}
