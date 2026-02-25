<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Db;

class uti {
    public function CookieGen() {
        return bin2hex(random_bytes(32)); 
    }

    public function isUsernameLegal(string $username): bool {
        $min = isset($_ENV['USERNAME_MIN_LENGTH']) ? (int)$_ENV['USERNAME_MIN_LENGTH'] : 5;
        $max = isset($_ENV['USERNAME_MAX_LENGTH']) ? (int)$_ENV['USERNAME_MAX_LENGTH'] : 100;
        
        // Length check (multibyte safe, though regex limits to ascii)
        $len = strlen($username);
        if ($len < $min || $len > $max) {
            return false;
        }
        
        // Character check: letters, numbers, underscore, hyphen
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return false;
        }
        
        return true;
    }
    
    public function getip() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
    
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', $_SERVER[$key]);
                return trim($ipList[0]); // Return the first IP in the list
            }
        }
    
        return 'UNKNOWN';
    }
    
    public function admin_audit(string $action, ?int $targetUserId = null, ?int $targetClientId = null): void {
        $stmt = Db::pdo()->prepare("
            INSERT INTO admin_audit_logs (admin_id, action, target_user_id, target_client_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $action,
            $targetUserId,
            $targetClientId,
            uti::getip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    public function user_audit(string $action, string $oldvalue = null, string $newvalue = null): void {
        $stmt = Db::pdo()->prepare("
            INSERT INTO user_audit_logs (user_id, action, old_value, new_value, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['uid'],
            $action,
            $oldvalue,
            $newvalue,
            uti::getip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    public function ratelimit(string $key, int $limit, int $window): bool {
        $hash = hash('sha256', $key);
        $now = time();
        $pdo = Db::pdo();

        // Check existing
        $stmt = $pdo->prepare("SELECT counter, reset_at FROM rate_limits WHERE key_hash = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if ($row) {
            if ($now > $row['reset_at']) {
                // Expired, reset
                $stmt = $pdo->prepare("UPDATE rate_limits SET counter = 1, reset_at = ? WHERE key_hash = ?");
                $stmt->execute([$now + $window, $hash]);
                return true;
            } else {
                // Within window
                if ($row['counter'] >= $limit) {
                    return false;
                } else {
                    // Increment
                    $stmt = $pdo->prepare("UPDATE rate_limits SET counter = counter + 1 WHERE key_hash = ?");
                    $stmt->execute([$hash]);
                    return true;
                }
            }
        } else {
            // New entry
            $stmt = $pdo->prepare("INSERT INTO rate_limits (key_hash, counter, reset_at) VALUES (?, 1, ?)");
            $stmt->execute([$hash, $now + $window]);
            return true;
        }
    }
}


