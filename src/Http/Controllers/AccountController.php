<?php
declare(strict_types=1);

namespace App\Http\Controllers;
use App\Support\Response;
use App\Support\Db;
use App\Http\Middleware\EnsureLoggedIn;
use App\Support\EmailVerificationService;
use App\Support\PasskeyService;
use App\Support\WechatVerifier;

class AccountController {
  public function handle(): int {
      
      (new EnsureLoggedIn())->handle();
      $uid = $_SESSION['uid'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      ui();
      return 0;
    }

    // Fetch user info for POST handling
    $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    
    require_once __DIR__ . '/../../../includes/lang.php'; 

    // Handle WeChat 2FA Toggle & Emergency Freeze
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'emergency_block') {
             // Emergency block
             // 1. ban = 10
             Db::pdo()->prepare("UPDATE users SET ban=10 WHERE id=?")->execute([$uid]);
             
             // 2. Revoke all sessions
             Db::pdo()->prepare("UPDATE user_sessions SET revoked=1 WHERE user_id=?")->execute([$uid]);
             
             // 3. Revole all OAuth2 tokens
             Db::pdo()->prepare("UPDATE oauth2_access_tokens SET revoked=1 WHERE user_id=?")->execute([$uid]);
             Db::pdo()->prepare("UPDATE oauth2_refresh_tokens SET revoked=1 WHERE access_token IN (SELECT token FROM oauth2_access_tokens WHERE user_id=?)")->execute([$uid]);
             
             // 4. Audit
             (new uti)->user_audit("emergency_block", null, null);
             
             // 5. Logout
             session_unset();
             session_destroy();
             Response::redirect('/login/password?blocked=1');
             return 0;
        } elseif ($_POST['action'] === 'enable_wechat_2fa') {
            if (!empty($user['wechat_openid'])) {
                Db::pdo()->prepare("UPDATE users SET wechat_login_2fa=1 WHERE id=?")->execute([$uid]);
                (new uti)->user_audit("wechat_login_2fa", '0', '1');
                Response::redirect('/account');
                return 0;
            }
        } elseif ($_POST['action'] === 'disable_wechat_2fa') {
            $code = $_POST['wechatcode'] ?? '';
            $openid = WechatVerifier::verify($code);
            if ($openid && $openid === $user['wechat_openid']) {
                Db::pdo()->prepare("UPDATE users SET wechat_login_2fa=0 WHERE id=?")->execute([$uid]);
                (new uti)->user_audit("wechat_login_2fa", '1', '0');
                Response::redirect('/account');
                return 0;
            } else {
                 Response::text($strings['cantturnoff2fa'], 400);
                 return 0;
            }
        }
    }

    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($phone !== '' && $phone != $user['phone_e164']) {
        Db::pdo()->prepare('UPDATE users SET phone_e164=? WHERE id=?')->execute([$phone, $uid]);
        (new uti)->user_audit("phonechange");
    }
    if ($password !== '') {
        Db::pdo()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_ARGON2ID), $uid]);
        (new uti)->user_audit("passwordchange");
    }

    // Re-authenticate if email changed 
    if ($email !== '') {
        $oldemail = $user['email'];
        if ($oldemail !== $email) {
            $stmt = Db::pdo()->prepare('UPDATE users SET email=? WHERE id=?');
            $stmt->execute([$email, $uid]);
            
            Db::pdo()->prepare('UPDATE users SET email_verified=0 WHERE id=?')->execute([$uid]);
            
            (new uti)->user_audit("emailchange", $oldemail, $email);

            // Send verification email
            try {
                $requestToken = EmailVerificationService::createAndSend($uid);
                Response::redirect('/verify/email?request=' . $requestToken);
                return 0;
            } catch (\Exception $e) {
                Response::text('Failed to send verification email: ' . $e->getMessage(), 500);
                return 0;
            }
        }
    }
    




    Response::text($strings['changed']);
    return 0;
  }

    // --- Passkey Management ---

    public function passkeyRegisterOptions() {
        (new EnsureLoggedIn())->handle();
        $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$_SESSION['uid']]);
        $user = $stmt->fetch();
        Response::json(PasskeyService::getRegisterOptions($user));
    }

    public function passkeyRegisterVerify() {
        (new EnsureLoggedIn())->handle();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$_SESSION['uid']]);
            $user = $stmt->fetch();
            
            PasskeyService::register($user, $data);
            (new uti)->user_audit("passkey_add");
            Response::json(['success' => true]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    public function passkeyDelete() {
        (new EnsureLoggedIn())->handle();
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
             Response::redirect('/account');
             return;
        }
        
        if ($id === 'ALL') {
            $stmt = Db::pdo()->prepare("DELETE FROM webauthn_credentials WHERE user_id=?");
            $stmt->execute([ $_SESSION['uid']]);
            
            (new uti)->user_audit("passkey_delete_all");
        } else {
        
        // Verify ownership
        $stmt = Db::pdo()->prepare("DELETE FROM webauthn_credentials WHERE id=? AND user_id=?");
        $stmt->execute([$id, $_SESSION['uid']]);
        
        (new uti)->user_audit("passkey_delete", $id);
        }
        Response::redirect('/account');
    }
}


function ui() {
    $uid = $_SESSION['uid'];
    $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();

    $stmt = Db::pdo()->prepare("SELECT * FROM user_audit_logs WHERE user_id=? ORDER BY id DESC");
    $stmt->execute([$uid]);
    $useraudit = $stmt->fetchAll();
    
    // Fetch passkeys
    $stmt = Db::pdo()->prepare("SELECT * FROM webauthn_credentials WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $passkeys = $stmt->fetchAll();
    
    require_once __DIR__ . "/../../../includes/header.php"; ?>
    
    <div class="page">
    

<br>

    <!-- Main -->
    <div class="page-wrapper">
      <div class="container-xl">
        <div class="row row-cards">
          <!-- User Info -->
          <div class="col-md-4">
            <div class="card">
              <div class="card-body text-center">
                <span class="avatar avatar-xl mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-circle"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" /></svg></span>
                <h3 class="card-title mb-1"><?php echo htmlspecialchars($u['username'] ?? 'user_id', ENT_QUOTES)?></h3>
                <p class="text-muted"><?php echo htmlspecialchars($u['email'] ?? 'user_email', ENT_QUOTES)?></p>
                <div class="btn-list">
                  <form method="POST" onsubmit="return confirm('Are you sure? This will immediately freeze your account, revoke all sessions, and log you out.');">
                    <input type="hidden" name="action" value="emergency_block">
                    <button type="submit" class="btn btn-danger"><?php echo $strings["emergencyblock"];?></button>
                  </form>
                </div>
              </div>
            </div>
            <br>
          <!-- Audit Log -->
            <div class="card mb-3">
              <div class="card-header">
                <h3 class="card-title"><?php 
                    $loglimit = $_ENV['USER_LOG_DISPLAY_LIMIT'] ?? 1;
                    echo $strings['notifications'];
                ?></h3>
              </div>
              <div class="list-group list-group-flush">
                  
            <?php 
                $logcount = 0;
                foreach ($useraudit as $audit) { 
                    if ($logcount >= $loglimit) break;
                    $logcount = $logcount + 1;
            ?>
                
                <div class="list-group-item">
                  <div class="row align-items-center">
                    <div class="col-auto">
                        <?php 
                            if ($audit['action'] === 'login' || $audit['action'] === 'login_passkey') {
                                echo '<span class="status-dot status-dot-animated bg-green"></span>';
                            } else if ($audit['action'] === 'wechat_login_2fa') {
                                if ($audit['old_value'] === '1' && $audit['new_value'] === '0') {
                                    echo '<span class="status-dot status-dot-animated bg-red"></span>';
                                } else echo '<span class="status-dot status-dot-animated bg-indigo"></span>';
                            } else if ($audit['action'] === 'emailchange' || $audit['action'] === 'phonechange' || $audit['action'] === 'passkey_add') {
                                echo '<span class="status-dot status-dot-animated bg-yellow"></span>';
                            } else if ($audit['action'] === 'passwordchange' || $audit['action'] === 'passkey_delete_all' || $audit['action'] === 'passkey_delete') {
                                echo '<span class="status-dot status-dot-animated bg-red"></span>';
                            } else if ($audit['action'] === 'emergency_block') {
                                echo '<span class="status-dot status-dot-animated bg-black"></span>';
                            }
                        ?>
                      
                    </div>
                    <div class="col text-truncate">
                      <a href="#" class="text-body d-block">
                          <?php 
                            if ($audit['action'] === 'login') {
                                echo $strings['loginsuccessful'];
                            } else if ($audit['action'] === 'wechat_login_2fa') {
                                if ($audit['old_value'] === '0' && $audit['new_value'] === '1') {
                                    echo $strings['enabledwechat2fa'];
                                } else if ($audit['old_value'] === '1' && $audit['new_value'] === '0') {
                                    echo $strings['disabledwechat2fa'];
                                } else {
                                    echo 'wechat_login_2fa_changed';
                                }
                            } else if ($audit['action'] === 'emailchange') {
                                echo $strings['emailchanged']."<br>".$strings['old'].": ".$audit['old_value']."<br>".$strings['new'].": ".$audit['new_value'];
                            } else if ($audit['action'] === 'phonechange') {
                                echo $strings['phonenumberchanged'];
                            } else if ($audit['action'] === 'passwordchange') {
                                echo $strings['passwordchanged'];
                            } else if ($audit['action'] === 'emergency_block') {
                                echo $strings['emergencyblock'];
                            } else if ($audit['action'] === 'login_passkey') {
                                echo $strings['loginwithpasskey'];
                            } else if ($audit['action'] === 'passkey_add') {
                                echo $strings['passkeyadded'];
                            } else if ($audit['action'] === 'passkey_delete_all') {
                                echo $strings['allpasskeyrevoked'];
                            }
                            else echo $audit['action'];
                        ?>
                      </a>
                      <div class="text-muted text-truncate mt-n1">
                            <?php echo $audit['created_at']?><br>
                            IP&nbsp;>
                            <?php echo $audit['ip_address']?></div>
                    </div>
                  </div>
                </div>
                
                
            <?php } ?>
                
                
              </div>
            </div>
          </div>

          <!-- Settings -->
          <div class="col-md-8">
            <!-- Acc. Settings -->
            <div class="card mb-3">
              <div class="card-header">
                <h3 class="card-title"><?php echo $strings['accountsettings']?></h3>
              </div>
              <div class="card-body">
                  

                  
                  <label class="form-label required"><?php echo $strings['username']?></label>
                  <div class=" mb-3">
                  <div class="input-group">
                    <span class="input-group-text"> @ </span>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($u['username'] ?? 'user_id', ENT_QUOTES)?>"
                      autocomplete="off" disabled/>
                  </div>
                  <small class="form-hint"><?php echo $strings["contactadmintoedit"]?></small>
                  </div>
                  
                <form  method='POST'>
                  <div class="mb-3">
                    <label class="form-label required"><?php echo $strings['email']?></label>
                    <input name='email' class="form-control" value="<?php echo htmlspecialchars($u['email'] ?? 'user_email', ENT_QUOTES)?>" required>
                    <?php
                        if ($u['email_verified'] === 0) {
                            echo $strings['youremailisnotverified'];
                            echo ' -> ';
                            echo "<a href='https://oidc.moec.dev/verify/email'>".$strings['clicktoverify']."</a>";
                        }
                    ?>
                  </div>
                  <div class="mb-3">
                    <label class="form-label"><?php echo $strings['phonenumber']?></label>
                    <input name='phone' class="form-control" value="<?php echo htmlspecialchars($u['phone_e164'] ?? '', ENT_QUOTES)?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label"><?php echo $strings['password']?></label>
                    <input name='password' type='password' class="form-control" value="" placeholder="<?php echo $strings['notchanged']?>">
                  </div>
                  <button type="submit" class="btn btn-primary"><?php echo $strings['submit']?></button>
                </form>
                
              </div>
            </div>

            

            <!-- Security Settings -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><?php echo $strings['secset']?></h3>
              </div>
              <div class="card-body">
                
                <?php if (empty($u['wechat_openid'])) { ?>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $strings['wechat2fa'];?></label>
                        <a href="/account/bind/wechat" class="btn btn-outline-primary"><?php echo $strings['linkwechat'];?></a>
                    </div>
                <?php } else { ?>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $strings['wechat2fa'];?></label>
                        <?php if ($u['wechat_login_2fa']) { ?>
                            <form method="POST" style="display: flex; gap: 10px;">
                                <input type="hidden" name="action" value="disable_wechat_2fa">
                                <div class="input-group">
                                    <span class="input-group-text bg-success text-white"><?php echo $strings['enabled'];?></span>
                                    <input type="text" name="wechatcode" class="form-control" placeholder="<?php echo $strings['enterverificationcodetodisable'];?>" required autocomplete="off">
                                    <button class="btn btn-danger" type="submit"><?php echo $strings['disable'];?></button>
                                </div>
                            </form>
                        <?php } else { ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="enable_wechat_2fa">
                                <div class="input-group">
                                    <button class="btn btn-success" type="submit"><?php echo $strings['enable'];?></button>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                <?php } ?>

                <!-- Passkeys -->
                <div class="mb-3">
                    <label class="form-label"><?php echo $strings['passkeys'] ?? 'Passkeys'; ?></label>
                    <div class="list-group mb-2">
                        <?php foreach ($passkeys as $pk) { ?>
                        <div class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col text-truncate">
                                    Passkey (<?php echo htmlspecialchars(substr($pk['credential_id'], 0, 8), ENT_QUOTES); ?>...)
                                    <div class="text-muted small">Added: <?php echo $pk['created_at']; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php } ?>
                        <?php if (empty($passkeys)) { ?>
                            <div class="text-muted small mb-2">No passkeys registered.</div>
                        <?php } ?>
                    </div>
                    <button id="register-passkey-btn" class="btn btn-outline-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-fingerprint" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                          <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                          <path d="M18.9 7a8 8 0 0 1 1.1 5v1a6 6 0 0 0 .8 3" />
                          <path d="M8 11a4 4 0 0 1 8 0v1a10 10 0 0 0 2 6" />
                          <path d="M12 11v2a14 14 0 0 0 2.5 8" />
                          <path d="M8 15a18 18 0 0 0 1.8 6" />
                          <path d="M4.9 19a22 22 0 0 1 -.9 -7v-1a8 8 0 0 1 12 -6.95" />
                        </svg>
                        <?php echo $strings['addpasskey'];?>
                    </button>
                    <?php if (!empty($passkeys)) { ?>
                        <form method="POST" action="/account/passkey/delete" onsubmit="return confirm('Are you sure?');">
                            <br>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars('ALL', ENT_QUOTES); ?>">
                            <button type="submit" class="btn btn-outline-danger"><?php echo $strings['revokeallpasskey'];?></button>
                        </form>
                    <?php } ?>
                </div>
                
                <script src="/assets/js/passkey.js"></script>
                <script>
                    document.getElementById('register-passkey-btn').addEventListener('click', function(e) {
                        e.preventDefault();
                        Passkey.register();
                    });
                </script>

                <!--<label class="form-check form-switch mt-3">-->
                <!--  <input class="form-check-input" type="checkbox">-->
                <!--  <span class="form-check-label"><?php //echo $strings['emailloginlog'];?></span>-->
                <!--</label>-->
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
    
    <?php
    require_once __DIR__ . "/../../../includes/footer.php";
}