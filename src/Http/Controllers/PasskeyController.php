<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Response;
use App\Support\Db;
use App\Support\PasskeyService;
use App\Http\Middleware\EnsureLoggedIn;
use App\Http\Controllers\uti;
use App\Http\Middleware\CsrfMiddleware;

class PasskeyController {
    
    // --- Registration Endpoints ---

    public function registerOptions() {
        (new EnsureLoggedIn())->handle();
        $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$_SESSION['uid']]);
        $user = $stmt->fetch();
        Response::json(PasskeyService::getRegisterOptions($user));
    }

    public function registerVerify() {
        (new EnsureLoggedIn())->handle();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$_SESSION['uid']]);
            $user = $stmt->fetch();
            
            PasskeyService::register($user, $data);
            Response::json(['success' => true]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // --- Login Endpoints ---

    public function loginOptions() {
        Response::json(PasskeyService::getLoginOptions());
    }

    public function loginVerify() {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $userId = PasskeyService::login($data);
            
            // Login successful
            session_regenerate_id(true);
            
            // Fetch user
            $user = Db::pdo()->prepare("SELECT * FROM users WHERE id=?")->execute([$userId])->fetch();
            
            // Check ban status
            if ($user['ban'] == 1) {
                Response::json(['success' => false, 'message' => 'Account suspended'], 403);
                return;
            }

            $ip = (new uti())->getip();
            $token = (new uti())->CookieGen();
            
            $stmt = Db::pdo()->prepare("
              INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
              VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");
            $stmt->execute([$user['id'], $token, $ip, $_SERVER['HTTP_USER_AGENT']]);
            
            $_SESSION['cookie'] = $token;
            $_SESSION['uid'] = $user['id'];
            
            (new uti)->user_audit("login_passkey", null, null);
            
            Response::json(['success' => true, 'redirect' => '/account']); 
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
