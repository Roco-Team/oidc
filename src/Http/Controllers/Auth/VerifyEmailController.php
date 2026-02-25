<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;
use App\Support\Response;
use App\Support\Db;
use App\Support\EmailVerificationService;

class VerifyEmailController {
  public function handle(): int {
    $requestToken = $_GET['request'] ?? '';
    
    if (empty($requestToken)) {
        // Try to find an unverified request for the logged-in user
        if (isset($_SESSION['uid'])) {
            $stmt = Db::pdo()->prepare("SELECT request_token FROM email_verifications WHERE user_id=? AND verified=0 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$_SESSION['uid']]);
            $lastReq = $stmt->fetch();
            
            if ($lastReq && !empty($lastReq['request_token'])) {
                Response::redirect('/verify/email?request=' . $lastReq['request_token']);
                return 1;
            }
        }
        
        Response::text('Invalid request', 400);
        return 0;
    }

    // Request verification token
    $stmt = Db::pdo()->prepare("SELECT * FROM email_verifications WHERE request_token=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$requestToken]);
    $verification = $stmt->fetch();

    if (!$verification) {
        Response::text('Request not found', 404);
        return 0;
    }

    $uid = $verification['user_id'];
    $createdAt = strtotime($verification['created_at']);
    $skipemailauth = $_ENV['SKIP_EMAIL_AUTH'] ?? 0;
    
    if ($skipemailauth === '1') {
        Db::pdo()->prepare('UPDATE email_verifications SET verified=1 WHERE id=?')->execute([$verification['id']]);
        Db::pdo()->prepare('UPDATE users SET email_verified=1 WHERE id=?')->execute([$uid]);
        Response::redirect('/login/password');
        return 0;
    }
    
    // Check if expired 
    if (time() - $createdAt > 180) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             Response::text($strings['verificationcodeexpired'], 400);
             return 0;
        }
        // If GET, show the expired error with resend button
        $expired = true;
    } else {
        $expired = false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      if (isset($_GET['resend']) && $_GET['resend'] === '1') {
          // Each send action must be at least 60s apart to prevent abuse
          if (time() - $createdAt < 60) {
              Response::redirect('/verify/email?request=' . $requestToken . '&error=' . urlencode('请稍后再试'));
              return 1;
          }
          // generate a new request token & verification code
          $this->resend($uid);
          return 1;
      }
      ui($requestToken, $expired);
      return 1;
    }
    
    if ($expired) {
        Response::text($strings['verificationcodeexpired'], 400);
        return 0;
    }

    $code = trim($_POST['code'] ?? '');
    
    // code is valid
    if ($code !== $verification['code'] || $verification['verified']) {
         Response::text('Invalid code', 400);
         return 0;
    }

    Db::pdo()->prepare('UPDATE email_verifications SET verified=1 WHERE id=?')->execute([$verification['id']]);
    Db::pdo()->prepare('UPDATE users SET email_verified=1 WHERE id=?')->execute([$uid]);
    Response::redirect('/login/password');
    return 0;
  }

  private function resend(int $uid): void {
      if ($uid <= 0) {
          Response::text('Invalid user ID', 400);
          return;
      }
      
      $stmt = Db::pdo()->prepare("SELECT email, username FROM users WHERE id=?");
      $stmt->execute([$uid]);
      $user = $stmt->fetch();
      
      if (!$user) {
          Response::text('User not found', 404);
          return;
      }
      
      try {
          $requestToken = EmailVerificationService::createAndSend($uid);
          Response::redirect('/verify/email?request=' . $requestToken . '&sent=1');
      } catch (\Exception $e) {
          Response::text('Failed to send verification email: ' . $e->getMessage(), 500);
      }
  }
}

function ui($requestToken, $expired) {
    require_once __DIR__ . "/../../../../includes/header.php";
    ?>
    
<div class="page page-center">
    <div class="container-tight py-4">
      <div class="card card-md">
        <div class="card-body">
          <h2 class="h2 text-center mb-4"><?php echo $strings['verifyemail'];?></h2>
          
          <?php if (isset($_GET['sent'])) { ?>
            <div class="alert alert-success"><?php echo $strings['verificationcodesent'];?></div>
          <?php } ?>
          <?php if ($expired) { ?>
            <div class="alert alert-warning"><?php echo $strings['verificationcodeexpired'];?></div>
          <?php } ?>
          <?php if (isset($_GET['error'])) { ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']) ?></div>
          <?php } ?>

          <form method="POST">
            <div class="mb-3">
              <label class="form-label required"><?php echo $strings['verificationcode'];?></label>
              <input name="code" class="form-control" placeholder="" autocomplete="off" <?php if ($expired) echo 'disabled'; ?>>
            </div>
            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100" <?php if ($expired) echo 'disabled'; ?>><?php echo $strings['submit']?></button>
              <a href="/verify/email?request=<?php echo htmlspecialchars($requestToken) ?>&resend=1" class="btn btn-link w-100 mt-2"><?php echo $strings['requestverificationcode'];?></a>
            </div>
          </form>
        </div>
      </div>
      <div class="text-center text-muted mt-3">
        <?php 
        if (!isset($_SESSION['uid']))
        echo $strings['haveacc']. '<a href="/login/password">'. $strings['login'];?></a>
      </div>
    </div>
  </div>
    
    <?php
    require_once __DIR__ . "/../../../../includes/footer.php";
}
