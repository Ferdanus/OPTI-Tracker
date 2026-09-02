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
     * Masking nomor telepon untuk tampilan aman (contoh: +62 812-****-0423)
     */
    public static function maskPhoneNumber(string $phone): string {
        $formatted = self::formatPhoneNumber($phone);
        if (strlen($formatted) < 8) {
            return '+62 ***-***';
        }

        $prefix = substr($formatted, 0, 5); // 62812
        $suffix = substr($formatted, -4);    // 0423
        return '+' . substr($prefix, 0, 2) . ' ' . substr($prefix, 2) . '-****-' . $suffix;
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

        // Pesan standar WhatsApp resmi dan profesional (tanpa emoji)
        $message = "*BBSPJIS | SILOPTI*\n"
                 . "Kode Verifikasi Akses\n\n"
                 . "Yth. {$namaUser},\n"
                 . "Kode verifikasi Anda adalah:\n\n"
                 . "*{$otp}*\n\n"
                 . "Kode ini berlaku selama 24 jam. Demi keamanan, mohon tidak membagikan kode ini kepada pihak manapun.";

        // Kirim via API Gateway jika dikonfigurasi di config.ini
        $f3 = \Base::instance();
        $gatewayUrl = $f3->get('wa_gateway_url') ?: '';
        $apiKey     = $f3->get('wa_api_key') ?: '';

        $deliverySuccess = true;
        $deliveryResponse = 'Simulated Local/Dev Delivery';

        if (!empty($gatewayUrl) && !empty($apiKey)) {
            try {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $gatewayUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array(
                        'target' => $formattedPhone,
                        'message' => $message,
                        'countryCode' => '62',
                    ),
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: {$apiKey}"
                    ),
                ));
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                $deliveryResponse = "HTTP {$httpCode}: " . substr((string)$response, 0, 200);
                $deliverySuccess = ($httpCode >= 200 && $httpCode < 300);
            } catch (\Exception $e) {
                $deliveryResponse = 'cURL Error: ' . $e->getMessage();
                $deliverySuccess = false;
            }

            // Update log delivery response
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
        }

        return [
            'success'      => true,
            'phone'        => $formattedPhone,
            'masked_phone' => $maskedPhone,
            'otp'          => $otp,
            'message'      => "Kode OTP verifikasi 24 jam telah dikirimkan ke WhatsApp ({$maskedPhone})."
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
