<?php
declare(strict_types=1);

namespace App\OAuth;

class IdTokenService {

    public static function sign(array $claims): string {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => self::kid()
        ];
    
        $segments = [];
        // 1. Encode Header
        $segments[] = self::base64UrlEncode(json_encode($header));
    
        // 2. Clains included iss (URL), must use JSON_UNESCAPED_SLASHES to avoid escaping slashes in iss
        $segments[] = self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));
    
        $signingInput = implode('.', $segments);
    
        $privateKey = file_get_contents(__DIR__ . '/../../keys/private.pem');
        $key = openssl_pkey_get_private($privateKey);
    
        if (!$key) {
            throw new \Exception("Invalid private key");
        }
    
        $signature = '';
        openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);
    
        $segments[] = self::base64UrlEncode($signature);
    
        return implode('.', $segments);
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function kid(): string {
        $pem = file_get_contents(__DIR__ . '/../../keys/public.key');
        $pub = openssl_pkey_get_public($pem);
        $details = openssl_pkey_get_details($pub);
        return substr(sha1($details['rsa']['n']), 0, 16);
    }
}
