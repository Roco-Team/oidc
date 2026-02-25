<?php
namespace App\Http\Controllers;
use App\Support\Response;

class LogoutController {
    public function handle(): void {
        
        Response::redirect('/account/logout_all');
        exit();
        
        $stmt = Db::pdo()->prepare("UPDATE user_sessions SET revoked=1 WHERE session_token=?");
        $stmt->execute([$_SESSION['cookie']]);

        // Destory session
        session_unset();
        session_destroy();
        Response::redirect('/login/password');
    }
}
