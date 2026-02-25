<?php
namespace App\Http\Controllers;
use App\Support\Response;
use App\Support\Db;

class LogoutAllController {
    public function handle(): void {
        if (!isset($_SESSION['uid'])) {
            Response::redirect('/login/password');
            exit;
        }

        $uid = (int)$_SESSION['uid'];

        // revoke access tokens
        $stmt = Db::pdo()->prepare("UPDATE oauth2_access_tokens SET revoked=1 WHERE user_id=?");
        $stmt->execute([$uid]);

        // revoke refresh tokens
        $stmt = Db::pdo()->prepare("
            UPDATE oauth2_refresh_tokens 
            SET revoked=1 
            WHERE access_token IN (
                SELECT token FROM oauth2_access_tokens WHERE user_id=?
            )
        ");
        $stmt->execute([$uid]);

        // revoke authorization codes
        $stmt = Db::pdo()->prepare("UPDATE oauth2_auth_codes SET revoked=1 WHERE user_id=?");
        $stmt->execute([$uid]);

        // revoke user sessions
        $stmt = Db::pdo()->prepare("UPDATE user_sessions SET revoked=1 WHERE user_id=?");
        $stmt->execute([$uid]);

        // clear PHP session
        session_unset();
        session_destroy();

        Response::redirect('/login/password?portal');
    }
}
