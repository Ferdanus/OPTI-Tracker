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
}

if (!function_exists('SentWANotif')) {
    /**
     * Global wrapper fungsi SentWANotif sesuai arahan mentor
     */
    function SentWANotif($noHP, $pesanWA) {
        return WhatsAppService::SentWANotif((string)$noHP, (string)$pesanWA);
    }
}
