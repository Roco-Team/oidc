<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;
use App\Support\Response;
use App\Support\Db;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Controllers\uti;
use App\Support\WechatVerifier;

class PasswordLoginController {
  public function handle(): void {
    if (isset($_SESSION['uid'])) {
        $hasOidcParams = isset($_GET['client_id']) || isset($_GET['response_type']);
        $target = $hasOidcParams ? '/authorize' : '/account';
        $qs = isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        Response::redirect($target . $qs);
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      ui();
      return;
    }
    $ip = (new uti())->getip();
    if (!(new uti())->ratelimit("login_ip:$ip", 5, 60)) {
        Response::text("Too many login attempts. Please try again later.", 429);
        return;
    }
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $stmt = Db::pdo()->prepare('SELECT * FROM users WHERE username=?'); 
    $stmt->execute([$u]); 
    $user = $stmt->fetch();

    if (!$user || !password_verify($p, $user['password_hash'])) {
        // preserve query_string when redirecting back to login page on login failure
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        if (strpos($qs, 'invalidcred=1') === false) {
            $qs .= ($qs ? '&' : '') . 'invalidcred=1';
        }
        Response::redirect('/login/password' . ($qs ? '?' . $qs : ''));
        return;
    }
    
    if ($user['ban'] > 0) {
        Response::text("Account suspended", 403);
        return;
    }
    
    if ($user['wechat_login_2fa']) {
        $wx2facode = $_POST['wechatcode'] ?? '';
        
        if (empty($wx2facode)) {
             $qs = $_SERVER['QUERY_STRING'] ?? '';
             if (strpos($qs, 'wechat2farequired=1') === false) {
                 $qs .= ($qs ? '&' : '') . 'wechat2farequired=1';
             }
             Response::redirect('/login/password' . ($qs ? '?' . $qs : ''));
             return;
        }

        // Communicate with your own WeChat Code Verifier
        // URL is configurable in the .env
        $openid = WechatVerifier::verify($wx2facode);
        
        if ($openid === null || $openid !== $user['wechat_openid']) {
             $qs = $_SERVER['QUERY_STRING'] ?? '';
             if (strpos($qs, 'wechat2fafailed=1') === false) {
                 $qs .= ($qs ? '&' : '') . 'wechat2fafailed=1';
             }
             Response::redirect('/login/password' . ($qs ? '?' . $qs : ''));
             return;
        }
    }
    
    if (!CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF validation failed');
    }

    session_regenerate_id(true);
    $token = (new uti())->CookieGen();
    $stmt = Db::pdo()->prepare("
      INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
      VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
    ");
    $stmt->execute([$user['id'], $token, $ip, $_SERVER['HTTP_USER_AGENT']]);
    
    $_SESSION['cookie'] = $token;
    $_SESSION['uid'] = $user['id'];
    
    (new uti)->user_audit("login", null, null);
    
    $hasOidcParams = isset($_GET['client_id']) || isset($_GET['response_type']);
    $target = $hasOidcParams ? '/authorize' : '/account';
    $qs = isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect($target . $qs);
  }
}

function ui() {
    require_once __DIR__ . "/../../../../includes/header.php";
    ?>
    
<div class="page page-center">
    <div class="container-tight py-4">
        <?php if (isset($_GET['invalidcred'])) { ?>
        <div class="alert alert-danger" role="alert">
          <div class="alert-icon">
            <!-- Download SVG icon from http://tabler.io/icons/icon/alert-circle -->
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"
              class="icon alert-icon icon-2">
              <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
              <path d="M12 8v4" />
              <path d="M12 16h.01" />
            </svg>
          </div>
          <div>
            <h4 class="alert-heading"><?php echo $strings['warning']?></h4>
            <div class="alert-description">
              <?php echo $strings['invalidcredential']?>
            </div>
          </div>
        </div>
        <?php } ?>
        <?php if (isset($_GET['wechat2farequired'])) { ?>
        <div class="alert alert-warning" role="alert">
          <div class="alert-icon">
            <!-- Download SVG icon from http://tabler.io/icons/icon/alert-circle -->
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
      stroke-linecap="round" stroke-linejoin="round"
      class="icon alert-icon icon-2">
      <path d="M12 9v4" />
      <path
        d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" />
      <path d="M12 16h.01" />
    </svg>
          </div>
          <div>
            <h4 class="alert-heading"><?php echo $strings['warning']?></h4>
            <div class="alert-description">
              <?php echo $strings['wechat2farequired']?>
            </div>
          </div>
        </div>
        <?php } ?>
        <?php if (isset($_GET['wechat2fafailed'])) { ?>
        <div class="alert alert-danger" role="alert">
          <div class="alert-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"
              class="icon alert-icon icon-2">
              <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
              <path d="M12 8v4" />
              <path d="M12 16h.01" />
            </svg>
          </div>
          <div>
            <h4 class="alert-heading"><?php echo $strings['warning']?></h4>
            <div class="alert-description">
              <?php echo $strings['wechat2fafailed'] ?? 'WeChat 2FA failed'?>
            </div>
          </div>
        </div>
        <?php } ?>
      <div class="card card-md">
        <div class="card-body">
          <h2 class="h2 text-center mb-4"><?php echo $strings['login'];?></h2>
          <form method="POST">
            <div class="mb-3">
              <label class="form-label required"><?php echo $strings['username'];?></label>
              <input name="username" class="form-control" placeholder="" autocomplete="off" required>
            </div>
            <div class="mb-3">
              <label class="form-label required"><?php echo $strings['password'];?></label>
              <input type="password" name="password" class="form-control" placeholder="" autocomplete="off" required>
            </div>
            <div class="mb-2">
              <label class="form-label"><?php echo $strings['wechatcode'];?></label>
              <input type="password" name="wechatcode" class="form-control" placeholder="<?php echo $strings['optional']?>" autocomplete="off">
              <div class="form-hint"><?php echo $strings['learnmore']?></div>
            </div>
            <input type="hidden" name="csrf_token" value="<?php echo CsrfMiddleware::generateToken() ?>">
            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100"><?php echo $strings['login'];?></button>
            </div>
          </form>
          <div class="hr-text">or</div>
          <div class="card-body">
              <a href="/login/passkey" class="btn btn-outline-secondary w-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-fingerprint" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                   <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                   <path d="M18.9 7a8 8 0 0 1 1.1 5v1a6 6 0 0 0 .8 3"></path>
                   <path d="M8 11a4 4 0 0 1 8 0v1a10 10 0 0 0 2 6"></path>
                   <path d="M12 11v2a14 14 0 0 0 2.5 8"></path>
                   <path d="M8 15a18 18 0 0 0 1.8 6"></path>
                   <path d="M4.9 19a22 22 0 0 1 -.9 -7v-1a8 8 0 0 1 12 -6.95"></path>
                </svg>
                Login with Passkey
              </a>
          </div>
        </div>
      </div>
      <div class="text-center text-muted mt-3">
        <?php echo $strings['noaccount']?> <a href="/register"><?php echo $strings['register'];?></a>
      </div>
    </div>
  </div>
    
    <?php
    require_once __DIR__ . "/../../../../includes/footer.php";
}