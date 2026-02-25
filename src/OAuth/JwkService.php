<?php
declare(strict_types=1);

namespace App\OAuth;

class JwkService {
    public static function publicJwks(): array {
        // Read PKCS#8 pubkey
        $pem = file_get_contents(__DIR__ . '/../../keys/public.key');
        $pub = openssl_pkey_get_public($pem);
        $details = openssl_pkey_get_details($pub);

        // RSA modulus (n) 和 exponent (e)
        $n = self::base64UrlEncode($details['rsa']['n']);
        $e = self::base64UrlEncode($details['rsa']['e']);

        $kid = substr(sha1($details['rsa']['n']), 0, 16);

        return [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => $kid,
                'use' => 'sig',
                'alg' => 'RS256',
                'n'   => $n,
                'e'   => $e
            ]]
        ];
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
