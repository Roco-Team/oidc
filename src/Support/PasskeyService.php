<?php
declare(strict_types=1);

namespace App\Support;

use App\Support\Db;

class PasskeyService {
    
    public static function getRegisterOptions(array $user, string $tableName = 'webauthn_credentials', string $userIdColumn = 'user_id'): array {
        $challenge = random_bytes(32);
        $_SESSION['webauthn_challenge'] = bin2hex($challenge);
        
        return [
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => $_ENV['SITE_NAME'] ?? 'OIDC Service',
                'id' => parse_url($_ENV['SITE_URL'] ?? 'http://localhost', PHP_URL_HOST)
            ],
            'user' => [
                'id' => base64_encode((string)$user['id']),
                'name' => $user['email'] ?? $user['username'], // Fallback for admins who might not have email
                'displayName' => $user['username']
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7], // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => self::getUserCredentials((int)$user['id'], $tableName, $userIdColumn)
        ];
    }

    public static function register(array $user, array $data, string $tableName = 'webauthn_credentials', string $userIdColumn = 'user_id'): void {
        $clientDataJSON = self::base64url_decode($data['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);
        
        $stored = hex2bin($_SESSION['webauthn_challenge'] ?? '');
        $received = self::base64url_decode($clientData['challenge']);
        if (empty($stored) || !hash_equals($stored, $received)) {
             throw new \Exception('Invalid challenge');
        }

        if ($clientData['type'] !== 'webauthn.create') {
            throw new \Exception('Invalid type');
        }
        
        $attestationObject = self::base64url_decode($data['response']['attestationObject']);
        $authData = self::extractAuthData($attestationObject);
        
        if (strlen($authData) < 37) throw new \Exception('Invalid authData length');
        
        $flags = ord($authData[32]);
        if (!($flags & 0x40)) { // AT (Attested Credential Data) must be set
            throw new \Exception('Attested Credential Data not present');
        }
        
        // Parse Attested Credential Data
        // 32 (RP ID Hash) + 1 (Flags) + 4 (SignCount) = 37 bytes header
        $offset = 37;
        
        // AAGUID (16 bytes)
        $aaguid = substr($authData, $offset, 16);
        $offset += 16;
        
        // Credential ID Length (2 bytes)
        $idLen = (ord($authData[$offset]) << 8) | ord($authData[$offset+1]);
        $offset += 2;
        
        // Credential ID
        $credentialId = substr($authData, $offset, $idLen);
        $offset += $idLen;
        
        // The rest is COSE Key (CBOR)
        $coseKeyCbor = substr($authData, $offset);
        $pem = self::parseCoseKeyToPem($coseKeyCbor);
        
        if (!$pem) {
            throw new \Exception('Failed to parse Public Key');
        }

        $stmt = Db::pdo()->prepare("INSERT INTO $tableName ($userIdColumn, credential_id, public_key, sign_count, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->execute([$user['id'], $data['id'], $pem]);
    }

    public static function getLoginOptions(): array {
        $challenge = random_bytes(32);
        $_SESSION['webauthn_challenge'] = bin2hex($challenge);
        
        return [
            'challenge' => base64_encode($challenge),
            'rpId' => parse_url($_ENV['SITE_URL'] ?? 'http://localhost', PHP_URL_HOST),
            'timeout' => 60000,
            'userVerification' => 'preferred'
        ];
    }

    public static function login(array $data, string $tableName = 'webauthn_credentials', string $userIdColumn = 'user_id') {
        $credentialId = $data['id'];
        $clientDataJSON = self::base64url_decode($data['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);
        
        $stored = hex2bin($_SESSION['webauthn_challenge'] ?? '');
        $received = self::base64url_decode($clientData['challenge']);
        if (empty($stored) || !hash_equals($stored, $received)) {
             throw new \Exception('Invalid challenge');
        }

        if ($clientData['type'] !== 'webauthn.get') {
            throw new \Exception('Invalid type');
        }

        $stmt = Db::pdo()->prepare("SELECT * FROM $tableName WHERE credential_id=?");
        $stmt->execute([$credentialId]);
        $cred = $stmt->fetch();
        
        if (!$cred) {
            throw new \Exception('Credential not found');
        }
        
        $authenticatorData = self::base64url_decode($data['response']['authenticatorData']);
        $signature = self::base64url_decode($data['response']['signature']);
        $clientDataHash = hash('sha256', $clientDataJSON, true);
        $signedData = $authenticatorData . $clientDataHash;
        
        $publicKey = $cred['public_key'];
        
        $verified = openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        
        if ($verified === 1) {
            return (int)$cred[$userIdColumn];
        }
        
        throw new \Exception('Invalid signature');
    }

    private static function getUserCredentials(int $userId, string $tableName = 'webauthn_credentials', string $userIdColumn = 'user_id'): array {
        $stmt = Db::pdo()->prepare("SELECT credential_id FROM $tableName WHERE $userIdColumn=?");
        $stmt->execute([$userId]);
        $creds = $stmt->fetchAll();
        
        return array_map(function($c) {
            return [
                'type' => 'public-key',
                'id' => $c['credential_id']
            ];
        }, $creds);
    }
    
    private static function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    // --- CBOR / COSE Parsing Helpers ---

    private static function extractAuthData($cbor) {
        // Find "authData" key in CBOR map
        // "authData" is encoded as text string (Major type 3) length 8
        // 0x68 (011_01000) followed by "authData"
        $needle = "\x68authData";
        $pos = strpos($cbor, $needle);
        if ($pos === false) throw new \Exception('authData not found in CBOR');
        
        $offset = $pos + 9; // Skip key
        
        // Read value type
        $type = ord($cbor[$offset]);
        $len = 0;
        $headerLen = 0;
        
        if ($type == 0x58) { // Byte string, 1 byte length
            $len = ord($cbor[$offset+1]);
            $headerLen = 2;
        } elseif ($type == 0x59) { // Byte string, 2 byte length
            $len = (ord($cbor[$offset+1]) << 8) | ord($cbor[$offset+2]);
            $headerLen = 3;
        } else {
            // Assume it's a byte string (Major type 2: 010_xxxxx)
            // If length < 24, it's encoded in the byte itself
            if (($type & 0xE0) == 0x40) {
                 $len = $type & 0x1F;
                 $headerLen = 1;
                 if ($len >= 24) throw new \Exception('Unsupported authData length encoding');
            } else {
                 throw new \Exception('Unexpected authData type: ' . dechex($type));
            }
        }
        
        return substr($cbor, $offset + $headerLen, $len);
    }

    private static function parseCoseKeyToPem($cbor) {
        // Helper to find value by integer key in CBOR Map
        $kty = self::findCborIntKey($cbor, 1);
        
        if ($kty === 2) { // EC
            $crv = self::findCborIntKey($cbor, -1);
            if ($crv !== 1) throw new \Exception('Unsupported curve');
            
            $x = self::findCborIntKeyBytes($cbor, -2);
            $y = self::findCborIntKeyBytes($cbor, -3);
            
            if (!$x || !$y) throw new \Exception('Invalid EC key');
            
            // Convert to PEM
             $oid = pack('H*', '3059301306072a8648ce3d020106082a8648ce3d03010703420004');
             $der = $oid . $x . $y;
             return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----";
        } elseif ($kty === 3) { // RSA
            $n = self::findCborIntKeyBytes($cbor, -1);
            $e = self::findCborIntKeyBytes($cbor, -2);
            
            if (!$n || !$e) throw new \Exception('Invalid RSA key');
            
            // Build PEM
            $modulus = $n;
            if (ord($modulus[0]) > 127) $modulus = chr(0) . $modulus;
            $exponent = $e;
            
            $rsaKeySeq = self::asn1_sequence(self::asn1_integer($modulus) . self::asn1_integer($exponent));
            $bitString = self::asn1_bit_string($rsaKeySeq);
            $algId = pack('H*', '300d06092a864886f70d0101010500');
            $pubKeySeq = self::asn1_sequence($algId . $bitString);
            
            return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($pubKeySeq), 64, "\n") . "-----END PUBLIC KEY-----";
        }
        
        throw new \Exception('Unsupported key type: ' . $kty);
    }
    
    private static function findCborIntKey($cbor, $key) {
        $pos = 0;
        // Read Map header
        $byte = ord($cbor[$pos++]);
        if (($byte & 0xE0) != 0xA0) return null; // Not a map
        
        $count = $byte & 0x1F;
        if ($count == 0x18) $count = ord($cbor[$pos++]);
        elseif ($count == 0x19) $count = (ord($cbor[$pos++]) << 8) | ord($cbor[$pos++]);
        
        for ($i = 0; $i < $count; $i++) {
            // Read Key
            $k = self::readCborInt($cbor, $pos);
            // Read Value
            if ($k === $key) {
                return self::readCborInt($cbor, $pos);
            }
            // Skip Value
            self::skipCborValue($cbor, $pos);
        }
        return null;
    }

    private static function findCborIntKeyBytes($cbor, $key) {
        $pos = 0;
        $byte = ord($cbor[$pos++]);
        if (($byte & 0xE0) != 0xA0) return null;
        
        $count = $byte & 0x1F;
        if ($count == 0x18) $count = ord($cbor[$pos++]);
        elseif ($count == 0x19) $count = (ord($cbor[$pos++]) << 8) | ord($cbor[$pos++]);
        
        for ($i = 0; $i < $count; $i++) {
            $k = self::readCborInt($cbor, $pos);
            if ($k === $key) {
                return self::readCborBytes($cbor, $pos);
            }
            self::skipCborValue($cbor, $pos);
        }
        return null;
    }
    
    private static function readCborInt($cbor, &$pos) {
        $byte = ord($cbor[$pos++]);
        $type = $byte & 0xE0;
        $val = $byte & 0x1F;
        
        if ($val == 0x18) $val = ord($cbor[$pos++]);
        elseif ($val == 0x19) { $val = (ord($cbor[$pos++]) << 8) | ord($cbor[$pos++]); }
        
        if ($type == 0x00) { // Unsigned
            return $val;
        } elseif ($type == 0x20) { // Negative
            return -1 - $val;
        }
        return null;
    }
    
    private static function readCborBytes($cbor, &$pos) {
        $byte = ord($cbor[$pos++]);
        if (($byte & 0xE0) != 0x40 && ($byte & 0xE0) != 0x60) return null; // Not bytes/string
        
        $len = $byte & 0x1F;
        if ($len == 0x18) {
            $len = ord($cbor[$pos++]);
        } elseif ($len == 0x19) {
            $len = (ord($cbor[$pos++]) << 8) | ord($cbor[$pos++]);
        }
        
        $res = substr($cbor, $pos, $len);
        $pos += $len;
        return $res;
    }
    
    private static function skipCborValue($cbor, &$pos) {
        $byte = ord($cbor[$pos]);
        $type = $byte & 0xE0;
        $info = $byte & 0x1F;
        $pos++;
        
        $len = 0;
        
        // Handle Length
        if ($info < 24) {
            $len = $info;
        } elseif ($info == 0x18) {
            $len = ord($cbor[$pos++]);
        } elseif ($info == 0x19) {
            $len = (ord($cbor[$pos++]) << 8) | ord($cbor[$pos++]);
        }
        
        if ($type == 0x00 || $type == 0x20) { // Int
            // Already read value (length is the value)
        } elseif ($type == 0x40 || $type == 0x60) { // Bytes/String
             $pos += $len;
        } elseif ($type == 0xA0) { // Map
            for ($i = 0; $i < $len; $i++) {
                self::skipCborValue($cbor, $pos); // Key
                self::skipCborValue($cbor, $pos); // Value
            }
        } elseif ($type == 0x80) { // Array
            for ($i = 0; $i < $len; $i++) {
                self::skipCborValue($cbor, $pos);
            }
        }
    }

    private static function asn1_sequence($content) {
        return chr(0x30) . self::asn1_length(strlen($content)) . $content;
    }
    
    private static function asn1_integer($content) {
        return chr(0x02) . self::asn1_length(strlen($content)) . $content;
    }
    
    private static function asn1_bit_string($content) {
        return chr(0x03) . self::asn1_length(strlen($content) + 1) . chr(0) . $content;
    }
    
    private static function asn1_length($len) {
        if ($len < 128) return chr($len);
        $lenBytes = '';
        while ($len > 0) {
            $lenBytes = chr($len & 0xff) . $lenBytes;
            $len >>= 8;
        }
        return chr(0x80 | strlen($lenBytes)) . $lenBytes;
    }
}
