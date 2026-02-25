<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;
use App\Support\Response;
use App\Support\Db;
use OTPHP\TOTP; 
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Controllers\uti;

class AdminLoginController {
    public function handle() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            ui();
            return 1;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $totpCode = trim($_POST['totp'] ?? '');

        $stmt = Db::pdo()->prepare('SELECT * FROM admin_users WHERE username=?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            return Response::text('用户名或密码错误', 401);
        }

        $totp = TOTP::create($admin['totp_secret']);
        if (!$totp->verify($totpCode)) {
            return Response::text('2FA 验证失败', 401);
        }

        $token = (new uti)->CookieGen();
        $stmt = Db::pdo()->prepare("
          INSERT INTO admin_sessions (admin_id, session_token, expires_at)
          VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ");
        $stmt->execute([$admin['id'], $token]);
        
        $_SESSION['admin_cookie'] = $token;
        $_SESSION['admin_id'] = $admin['id'];

        (new uti)->admin_audit('login', null, null);
        
        Response::redirect('/admin');
    }
}

function ui() {
    require_once __DIR__ . "/../../../../includes/adminheader.php";
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
      <div class="card card-md">
        <div class="card-body">
          <h2 class="h2 text-center mb-4"><?php echo $strings['adminportal'];?></h2>
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
              <label class="form-label required"><?php echo $strings['2fa'];?></label>
              <input name="totp" class="form-control" placeholder="" autocomplete="off" required>
            </div>
            <input type="hidden" name="csrf_token" value="<?php echo CsrfMiddleware::generateToken() ?>">
            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100"><?php echo $strings['login'];?></button>
            </div>
          </form>
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