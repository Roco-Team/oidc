<?php
namespace App\Http\Controllers\Admin;
use App\Support\Response;
use App\Support\Db;
use App\Http\Controllers\uti;

class UserController {
    public function handle(): void {
        ui();
    }
    
    public function unfro() {
        if (!isset($_POST['id'])) {
            Response::text('unfro: no data', 401);
            exit();
        }
        $uid = (int)$_POST['id'];
        
        $user = Db::pdo()->query("SELECT ban FROM users")->fetch();
        if (!$user) {
            Response::text('unfro: no user', 401);
            exit();
        }
        
        $user = Db::pdo()->prepare("UPDATE users SET ban=? WHERE id=?");
        $user->execute([0, $uid]);
        
        (new uti)->admin_audit('unfro_user', $uid, null);

        Response::redirect('/admin/users');
    }
    
    public function ban() {
        if (!isset($_POST['id'])) {
            Response::text('ban: no data', 401);
            exit();
        }
        $uid = (int)$_POST['id'];
        
        $user = Db::pdo()->query("SELECT ban FROM users")->fetch();
        if (!$user) {
            Response::text('ban: no user', 401);
            exit();
        }
        
        $user = Db::pdo()->prepare("UPDATE users SET ban=? WHERE id=?");
        $user->execute([1, $uid]);
        
        (new uti)->admin_audit('ban_user', $uid, null);

        Response::redirect('/admin/users');
    }
    
    public function unban() {
        if (!isset($_POST['id'])) {
            Response::text('unban: no data', 401);
            exit();
        }
        $uid = (int)$_POST['id'];
        
        $user = Db::pdo()->query("SELECT ban FROM users")->fetch();
        if (!$user) {
            Response::text('unban: no user', 401);
            exit();
        }
        
        $user = Db::pdo()->prepare("UPDATE users SET ban=? WHERE id=?");
        $user->execute([0, $uid]);
        
        (new uti)->admin_audit('unban_user', $uid, null);

        Response::redirect('/admin/users');
    }

    public function passkeys() {
        if (!isset($_GET['id'])) {
            Response::redirect('/admin/users');
            return;
        }
        $uid = (int)$_GET['id'];
        ui_passkeys($uid);
    }
    
    public function deletePasskey() {
        if (!isset($_POST['id']) || !isset($_POST['user_id'])) {
            Response::text('deletePasskey: no data', 400);
            return;
        }
        $id = $_POST['id']; // credential ID (int)
        $uid = (int)$_POST['user_id'];
        
        $stmt = Db::pdo()->prepare("DELETE FROM webauthn_credentials WHERE id=? AND user_id=?");
        $stmt->execute([$id, $uid]);
        
        (new uti)->admin_audit('remove_user_passkey', $uid, $id);
        
        Response::redirect('/admin/users/passkeys?id=' . $uid);
    }
}

function ui_passkeys($uid) {
    require_once __DIR__ . '/../../../../includes/adminheader.php';
    $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "User not found";
        return;
    }
    
    $stmt = Db::pdo()->prepare("SELECT * FROM webauthn_credentials WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $passkeys = $stmt->fetchAll();
    ?>
    <div class="page-body">
      <div class="container-xl">
        <div class="page-header d-print-none">
          <div class="row align-items-center">
            <div class="col">
              <h2 class="page-title">Manage Passkeys for <?php echo htmlspecialchars($user['username']); ?></h2>
              <div class="text-muted mt-1"><a href="/admin/users">Back to Users</a></div>
            </div>
          </div>
        </div>
        
        <div class="card">
            <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Credential ID</th>
                  <th>Created At</th>
                  <th>Sign Count</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($passkeys as $pk): ?>
                <tr>
                    <td><?php echo $pk['id']; ?></td>
                    <td class="text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars(substr($pk['credential_id'], 0, 50)) . '...'; ?></td>
                    <td><?php echo $pk['created_at']; ?></td>
                    <td><?php echo $pk['sign_count']; ?></td>
                    <td>
                        <form method="POST" action="/admin/users/passkeys/delete" onsubmit="return confirm('Are you sure you want to delete this passkey?');" style="display:inline-block">
                            <input type="hidden" name="id" value="<?php echo $pk['id']; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($passkeys)): ?>
                <tr><td colspan="5" class="text-center">No passkeys found for this user.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            </div>
        </div>
      </div>
    </div>
    <?php
}

function ui() {
    require_once __DIR__ . '/../../../../includes/adminheader.php';
    $users = Db::pdo()->query("SELECT id,username,email,email_verified,wechat_openid,wechat_login_2fa,ban,created_at,phone_e164 FROM users")->fetchAll();
    ?>
    
<div class="page-body">
  <div class="container-xl">

    
    <div class="page-header d-print-none">
      <div class="row align-items-center">
        <div class="col">
          <h2 class="page-title"></h2>
          <div class="text-muted mt-1"></div>
        </div>
      </div>
    </div>

    <!-- User List -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?php echo $strings['usercontrol']?></h3>
      </div>

      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>username</th>
              <th>Email</th>
              <th>Info</th>
              <th><?php echo $strings['wechat2fa']?></th>
              <th><?php echo $strings['status']?></th>
              <th><?php echo $strings['createat']?></th>
              <th><?php echo $strings['actions']?></th>
            </tr>
          </thead>
          <tbody>

          <?php foreach ($users as $c): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['id']) ?></td>
              <td><code><?php echo htmlspecialchars($c['username']) ?></code></td>
              <td> <code><?php echo htmlspecialchars($c['email']) ?></code></td>
              <td>
                  <div class="dropdown">
                  <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown"><?php echo $strings['reveal']?></a>
                  <div class="dropdown-menu dropdown-menu-card" style="max-width: 16rem">
                    <div class="card d-flex flex-column">
                      <div class="card-body d-flex flex-column">
                        <h3 class="card-title">
                            <p>WeChat Openid</p>
                          <pre><?php echo htmlspecialchars($c['wechat_openid'] ?? 'NULL') ?></pre>
                            <p><?php echo $strings['phonenumber']?></p>
                          <pre><?php echo htmlspecialchars($c['phone_e164'] ?? 'NULL') ?></pre>
                        </h3>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
              <td><?php echo $c['wechat_login_2fa']?'Yes':'No' ?></td>
              
              <td>
                  <?php
                  
                  if ($c['ban'] === 0) {
                      ?>
                      <span class="status status-green">
                          <span class="status-dot status-dot-animated"></span>
                          <?php echo $strings['active']?>
                      </span>
                      <?php
                  }
                  
                  if ($c['ban'] === 1) {
                      ?>
                      <span class="status status-red">
                          <span class="status-dot status-dot-animated"></span>
                          <?php echo $strings['banned']?>
                      </span>
                      <?php
                  }
                  
                  if ($c['ban'] === 10) {
                      ?>
                      <span class="status status-indigo">
                          <span class="status-dot status-dot-animated"></span>
                          <?php echo $strings['frozen']?>
                      </span>
                      <?php
                  }
                  
                  ?>
              </td>

              <td><?php echo $c['created_at'] ?></td>

              <td class="text-end">

                  <a href="/admin/users/passkeys?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">Passkeys</a>
               
            <?php if ($c['ban'] === 0) { ?>
                 
                  <form method="POST" action="/admin/users/ban" style="display:inline-block">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-danger btn-sm"
                      onclick="return confirm('<?php echo $strings['sureaboutbanuser']?>')">
                      <?php echo $strings['ban']?>
                    </button>
                  </form>
            <?php } else if ($c['ban'] === 1) { ?>
                  
                  <form method="POST" action="/admin/users/unban" style="display:inline-block">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-dark btn-sm"
                      onclick="return confirm('<?php echo $strings['sureaboutunbanuser']?>')">
                      <?php echo $strings['unban']?>
                    </button>
                  </form>
            <?php } else if ($c['ban'] === 10) { ?>
            
                  <form method="POST" action="/admin/users/unfro" style="display:inline-block">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-primary btn-sm"
                      onclick="return confirm('<?php echo $strings['sureaboutunfrouser']?>')">
                      <?php echo $strings['unfro']?>
                    </button>
                  </form>
                  
                  <form method="POST" action="/admin/users/ban" style="display:inline-block">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-danger btn-sm"
                      onclick="return confirm('<?php echo $strings['sureaboutbanuser']?>')">
                      <?php echo $strings['ban']?>
                    </button>
                  </form>
            <?php } ?>

              </td>
            </tr>
          <?php endforeach; ?>

          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
    

    <?php
}