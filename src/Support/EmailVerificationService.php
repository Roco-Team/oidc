<?php
declare(strict_types=1);

namespace App\Support;

use App\Support\Db;
use App\Http\Controllers\SendEmailController;

class EmailVerificationService {

    public static function createAndSend(int $uid): string {
        if ($uid <= 0) {
            throw new \Exception('Invalid user ID');
        }
        
        $stmt = Db::pdo()->prepare("SELECT email, username FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Generate tokens
        $code = (string)random_int(100000, 999999);
        $requestToken = bin2hex(random_bytes(32)); 
        
        // Insert verification record
        $stmt = Db::pdo()->prepare("INSERT INTO email_verifications (user_id, email, code, request_token, created_at, expire_at) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 3 MINUTE))");
        $stmt->execute([$uid, $user['email'], $code, $requestToken]);
        
        // Send email
        $subject = "Verify your email address - " . ($_ENV['SITE_NAME'] ?? 'OIDC');
        $body = "<p>Hi " . htmlspecialchars($user['username']) . ",</p>" .
                "<p>Your verification code is: <strong>" . $code . "</strong></p>" .
                "<p>This code will expire in 3 minutes.</p>";
                
        SendEmailController::send($user['email'], $subject, $body);

        return $requestToken;
    }
}
