<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\Db;
use App\Support\Response;
use App\Support\PasskeyService;
use App\Http\Controllers\uti;

class AdminPasskeyController {
    
    // --- Login Endpoints ---

    public function loginOptions() {
        $ip = (new uti())->getip();
        if (!(new uti())->ratelimit("admin_passkey_options_ip:$ip", 20, 60)) {
            Response::json(['error' => 'Too many attempts'], 429);
            return;
        }

        try {
            $options = PasskeyService::getLoginOptions();
            Response::json($options);
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }

    public function loginVerify() {
        $ip = (new uti())->getip();
        if (!(new uti())->ratelimit("admin_passkey_login_ip:$ip", 10, 60)) {
            Response::json(['error' => 'Too many attempts'], 429);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new \Exception('Invalid JSON');
            
            // Verify passkey against admin_webauthn_credentials
            $adminId = PasskeyService::login($data, 'admin_webauthn_credentials', 'admin_id');
            
            // Login successful
            $token = (new uti)->CookieGen();
            $stmt = Db::pdo()->prepare("
                INSERT INTO admin_sessions (admin_id, session_token, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");
            $stmt->execute([$adminId, $token]);
            
            $_SESSION['admin_cookie'] = $token;
            $_SESSION['admin_id'] = $adminId;
            
            (new uti)->admin_audit('login_passkey', null, null);
            
            Response::json(['success' => true, 'redirect' => '/admin']);
            
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }

    // --- Management Endpoints (Require Admin Auth) ---

    public function registerOptions() {
        try {
            if (empty($_SESSION['admin_id'])) {
                throw new \Exception('Unauthorized');
            }
            
            $adminId = $_SESSION['admin_id'];
            $stmt = Db::pdo()->prepare("SELECT * FROM admin_users WHERE id=?");
            $stmt->execute([$adminId]);
            $user = $stmt->fetch();
            
            if (!$user) throw new \Exception('User not found');
            
            // Generate options for admin_webauthn_credentials
            $options = PasskeyService::getRegisterOptions($user, 'admin_webauthn_credentials', 'admin_id');
            Response::json($options);
            
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }

    public function registerVerify() {
        try {
            if (empty($_SESSION['admin_id'])) {
                throw new \Exception('Unauthorized');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new \Exception('Invalid JSON');
            
            $adminId = $_SESSION['admin_id'];
            $stmt = Db::pdo()->prepare("SELECT * FROM admin_users WHERE id=?");
            $stmt->execute([$adminId]);
            $user = $stmt->fetch();
            
            if (!$user) throw new \Exception('User not found');
            
            PasskeyService::register($user, $data, 'admin_webauthn_credentials', 'admin_id');
            
            (new uti)->admin_audit('add_passkey', null, null);
            
            Response::json(['success' => true]);
            
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }

    public function delete() {
        try {
            if (empty($_SESSION['admin_id'])) {
                throw new \Exception('Unauthorized');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id'])) throw new \Exception('Invalid Request');
            
            $credentialId = $data['id'];
            $adminId = $_SESSION['admin_id'];
            
            $stmt = Db::pdo()->prepare("DELETE FROM admin_webauthn_credentials WHERE credential_id=? AND admin_id=?");
            $stmt->execute([$credentialId, $adminId]);
            
            if ($stmt->rowCount() > 0) {
                (new uti)->admin_audit('remove_passkey', null, null);
                Response::json(['success' => true]);
            } else {
                throw new \Exception('Credential not found or not owned by you');
            }
            
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }
}
