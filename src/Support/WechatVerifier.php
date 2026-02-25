<?php
declare(strict_types=1);

namespace App\Support;

class WechatVerifier {
    /**
     * Verify WeChat code and return openid if successful
     * 
     * @param string $code
     * @return string|null The openid if verification succeeds, null otherwise
     */
    public static function verify(string $code): ?string {
        if (empty($code)) {
            return null;
        }

        $ch = curl_init();
        $apiUrl = $_ENV['WECHAT_API_URL'] ?? 'https://wx.example.cn/';
        $token = $_ENV['WECHAT_API_TOKEN'] ?? 'aiworisuex';
        $url = $apiUrl . "?code=" . urlencode($code) . "&token=" . urlencode($token);
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $apiRes = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
             return null;
        }

        $apiData = json_decode($apiRes ?: '', true);

        if (!$apiData || !isset($apiData['openid']) || empty($apiData['openid'])) {
             return null;
        }

        return $apiData['openid'];
    }
}
