<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Response;
use App\Support\Db;
use App\Http\Middleware\EnsureLoggedIn;
use App\Http\Middleware\CsrfMiddleware;
use App\Support\WechatVerifier;

class WechatBindController {
    public function handle(): void {
        (new EnsureLoggedIn())->handle();
        $uid = $_SESSION['uid'];
        
        // Fetch current user
        $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
             // If already bound, redirect to account
             if (!empty($user['wechat_openid'])) {
                 Response::redirect('/account');
                 return;
             }
             
             require_once __DIR__ . "/../../../includes/header.php";
             ?>
             <div class="page page-center">
               <div class="container-tight py-4">
                 <div class="card card-md">
                   <div class="card-body">
                     <h2 class="h2 text-center mb-4">绑定微信账号</h2>
                     <?php if (isset($_GET['error'])) { ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']) ?></div>
                     <?php } ?>
                     <form method="POST">
                       <div class="mb-3">
                         <label class="form-label">微信验证码</label>
                         <input type="text" name="wechatcode" class="form-control" placeholder="请输入微信验证码" required autocomplete="off">
                         <div class="form-hint">请关注我们的微信公众号，发送“login”获取。</div>
                       </div>
                       <input type="hidden" name="csrf_token" value="<?php echo CsrfMiddleware::generateToken() ?>">
                       <div class="form-footer">
                         <button type="submit" class="btn btn-primary w-100">绑定并开启两步验证</button>
                       </div>
                     </form>
                   </div>
                 </div>
               </div>
             </div>
             <?php
             require_once __DIR__ . "/../../../includes/footer.php";
             return;
        }
        
        // Handle POST
        if (!CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit('CSRF validation failed');
        }
        
        $code = $_POST['wechatcode'] ?? '';
        $openid = WechatVerifier::verify($code);
        
        if ($openid === null) {
            Response::redirect('/account/bind/wechat?error=' . urlencode('验证码无效或过期'));
            return;
        }
        
        // Update user
        $stmt = Db::pdo()->prepare("UPDATE users SET wechat_openid=?, wechat_login_2fa=1 WHERE id=?");
        $stmt->execute([$openid, $uid]);
        
        Response::redirect('/account');
    }
}
