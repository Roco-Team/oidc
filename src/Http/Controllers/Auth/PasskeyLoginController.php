<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;
use App\Support\Response;
use App\Support\Db;
use App\Support\PasskeyService;
use App\Http\Controllers\uti;

class PasskeyLoginController {
    
    public function loginOptions() {
        Response::json(PasskeyService::getLoginOptions());
    }

    public function loginVerify() {
        $ip = (new uti())->getip();
        if (!(new uti())->ratelimit("passkey_login_ip:$ip", 10, 60)) {
            Response::json(['success' => false, 'message' => 'Too many attempts'], 429);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $userId = PasskeyService::login($data);
            
            // Login successful
            session_regenerate_id(true);
            
            // Fetch user
            $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            // Check ban status
            if ($user['ban'] > 0) {
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

  public function handle(): void {
    if (isset($_SESSION['uid'])) {
        Response::redirect('/account');
        return;
    }
    
    require_once __DIR__ . "/../../../../includes/header.php";
    ?>
    <div class="page page-center">
      <div class="container-tight py-4">
        <div class="card card-md">
          <div class="card-body text-center">
            <h2 class="h2 mb-4"><?php echo $strings['login_passkey'] ?? 'Sign in with Passkey'; ?></h2>
            <div class="mb-3">
               <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-fingerprint" width="48" height="48" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2c3e50" fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                  <path d="M18.9 7a8 8 0 0 1 1.1 5v1a6 6 0 0 0 .8 3" />
                  <path d="M8 11a4 4 0 0 1 8 0v1a10 10 0 0 0 2 6" />
                  <path d="M12 11v2a14 14 0 0 0 2.5 8" />
                  <path d="M8 15a18 18 0 0 0 1.8 6" />
                  <path d="M4.9 19a22 22 0 0 1 -.9 -7v-1a8 8 0 0 1 12 -6.95" />
                </svg>
            </div>
            <div class="mb-4">
               <button id="passkey-login-btn" class="btn btn-primary w-100">
                  <?php echo $strings['authenticate'] ?? 'Authenticate'; ?>
               </button>
            </div>
            <div id="message" class="text-danger"></div>
          </div>
        </div>
        <div class="text-center text-muted mt-3">
          <a href="/login/password"><?php echo $strings['login_password'] ?? 'Login with Password'; ?></a>
        </div>
      </div>
    </div>
    
    <script src="/assets/js/passkey.js"></script>
    <script>
        document.getElementById('passkey-login-btn').addEventListener('click', function() {
            Passkey.login();
        });
        
        // Auto-trigger if requested
        if (new URLSearchParams(window.location.search).has('autostart')) {
             Passkey.login();
        }
    </script>
    <?php
    require_once __DIR__ . "/../../../../includes/footer.php";
  }
}
