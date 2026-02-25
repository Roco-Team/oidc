<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;
use App\Support\Response;
use App\Support\Db;
use App\Support\EmailVerificationService;
use App\Http\Controllers\uti;

class RegisterController {
  public function handle(): int {
      if (isset($_SESSION['uid'])) return Response::redirect('/account');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      ui();
      return 1;
    }
    
    $ip = (new uti())->getip();
    if (!(new uti())->ratelimit("register_ip:$ip", 3, 3600)) {
        Response::text("Too many registration attempts. Please try again later.", 429);
        return 0;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($username === '' || $password === '' || $email === '') {
        Response::text('Missing fields', 400);
        return 0;
    }
    
    $isNameLegal = (new uti())->isUsernameLegal($username);
    if (!$isNameLegal) {
        Response::redirect("/register?invalidusername", 400);
        return 0;
    }

    $stmt = Db::pdo()->prepare('INSERT INTO users (username,email,password_hash,phone_e164,wechat_openid) VALUES (?,?,?,?,?)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_ARGON2ID), $phone ?: null, $_SESSION['pending_wechat_openid'] ?? null]);

    $userId = (int)Db::pdo()->lastInsertId();

    try {
        $requestToken = EmailVerificationService::createAndSend($userId);
        Response::redirect('/verify/email?request=' . $requestToken);
    } catch (\Exception $e) {
        Response::text('Failed to send verification email: ' . $e->getMessage(), 500);
    }

    return 0;
  }
}

function ui() {
    
    require_once __DIR__ . "/../../../../includes/header.php";
    ?>
    
<div class="page page-center">
    <div class="container-tight py-4">
        <?php if (isset($_GET['invalidusername'])) { ?>
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
              <?php echo $strings['invalidusername']?>
            </div>
          </div>
        </div>
        <?php } ?>
      <div class="card card-md">
        <div class="card-body">
          <h2 class="h2 text-center mb-4"><?php echo $strings['register'];?></h2>
          <form method="POST">
            <div class="mb-3">
              <label class="form-label required"><?php $minusernamelen = $_ENV['USERNAME_MIN_LENGTH'] ?? 5; $maxusernamelen = $_ENV['USERNAME_MAX_LENGTH'] ?? 100;echo $strings['username'].' ('.$strings['lengthmustbe'].': '.$minusernamelen.' ~ '.$maxusernamelen.')';?></label>
              <input name="username" class="form-control" placeholder="" autocomplete="off" required>
            </div>
            <div class="mb-2">
              <label class="form-label required"><?php echo $strings['password'];?></label>
              <input type="password" name="password" class="form-control" placeholder="" autocomplete="off" required>
            </div>
            <div class="mb-2">
              <label class="form-label required"><?php echo $strings['email'];?></label>
              <input type="email" name="email" class="form-control" placeholder="" autocomplete="off" required>
            </div>
            <div class="mb-2">
              <label class="form-label "><?php echo $strings['phonenumber'];?></label>
              <input name="phone" class="form-control" placeholder="<?php echo $strings['optional']?>" autocomplete="off">
            </div>
            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100"><?php echo $strings['register'];?></button>
            </div>
          </form>
        </div>
      </div>
      <div class="text-center text-muted mt-3">
        <?php echo $strings['haveacc'];?> <a href="/login/password?portal"><?php echo $strings['login'];?></a>
      </div>
    </div>
  </div>
    
    <?php
    require_once __DIR__ . "/../../../../includes/footer.php";
}
