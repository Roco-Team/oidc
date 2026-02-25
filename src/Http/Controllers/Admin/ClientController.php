<?php
namespace App\Http\Controllers\Admin;

use App\Support\Response;
use App\Support\Db;
use App\Http\Controllers\uti;

class ClientController {

  // List all clients
  public function handle(): void {
    $stmt = Db::pdo()->query("SELECT * FROM oauth2_clients ORDER BY id DESC");
    $clients = $stmt->fetchAll();
    ui($clients);
  }

  // Create new client
  public function create(): void {
    $redirectUrisRaw = trim($_POST['redirect_uris'] ?? '');
    $scopesRaw = trim($_POST['scopes'] ?? 'openid email profile');
    $pkceRequired = 0;//isset($_POST['pkce_required']) ? 1 : 0;
    $wechatRequired = isset($_POST['wechat_required']) ? 1 : 0;

    if (!$redirectUrisRaw) {
      Response::text('Missing fields', 400);
      exit();
    }

    // redirect_uris → JSON array
    $redirectUrisArray = array_filter(array_map('trim', explode("\n", $redirectUrisRaw)));
    $redirectUrisJson = json_encode($redirectUrisArray, JSON_UNESCAPED_SLASHES);

    // scopes → JSON array
    $scopesArray = array_filter(array_map('trim', preg_split('/[\s,]+/', $scopesRaw)));
    $scopesJson = json_encode($scopesArray, JSON_UNESCAPED_SLASHES);

    $clientId = bin2hex(random_bytes(16));
    $clientSecret = bin2hex(random_bytes(32));

    $stmt = Db::pdo()->prepare("
      INSERT INTO oauth2_clients (client_id, client_secret, redirect_uris, scopes, pkce_required, wechat_required)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$clientId, $clientSecret, $redirectUrisJson, $scopesJson, $pkceRequired, $wechatRequired]);

    // get numeric id
    $insertId = Db::pdo()->lastInsertId();

    // AUDIT: create_client
    (new uti)->admin_audit('create_client', null, (int)$insertId);

    Response::redirect('/admin/clients');
  }



  // Revoke client
  public function revoke(): void {
    $id = (int)$_POST['id'];

    // revoke client
    $stmt = Db::pdo()->prepare("UPDATE oauth2_clients SET revoked=1 WHERE id=?");
    $stmt->execute([$id]);

    // revoke all tokens from this client
    $stmt = Db::pdo()->prepare("
      UPDATE oauth2_access_tokens 
      SET revoked=1 
      WHERE client_id=(SELECT client_id FROM oauth2_clients WHERE id=?)
    ");
    $stmt->execute([$id]);

    // AUDIT: revoke_client
    (new uti)->admin_audit('revoke_client', null, $id);

    Response::redirect('/admin/clients');
  }
  public function rotate(): void {
        $id = (int)$_POST['id'];

        // get client
        $stmt = Db::pdo()->prepare("SELECT client_id FROM oauth2_clients WHERE id=? AND revoked=0");
        $stmt->execute([$id]);
        $client = $stmt->fetch();

        if (!$client) {
            Response::text('Client not found or revoked', 400);
            exit();
        }

        // gen new secret
        $newSecret = bin2hex(random_bytes(32));

        // rotate client_secret
        $stmt = Db::pdo()->prepare("UPDATE oauth2_clients SET client_secret=? WHERE id=?");
        $stmt->execute([$newSecret, $id]);

        // revoke token
        $stmt = Db::pdo()->prepare("
            UPDATE oauth2_access_tokens 
            SET revoked=1 
            WHERE client_id=?
        ");
        $stmt->execute([$client['client_id']]);

        // audit
        (new uti)->admin_audit('rotate_clisec', null, $id);

        Response::redirect('/admin/clients');
    }

}



function ui(array $clients) {
    require __DIR__ . '/../../../../includes/adminheader.php';
    ?>

<div class="page-body">
  <div class="container-xl">

    <!-- Title -->
    <div class="page-header d-print-none">
      <div class="row align-items-center">
        <div class="col">
          <h2 class="page-title">OIDC Clients <?php echo $strings['mgmt']?></h2>
          <div class="text-muted mt-1"></div>
        </div>
      </div>
    </div>

    <!-- Create Client -->
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title"><?php echo $strings['createnewclient']?></h3>
      </div>
      <div class="card-body">
        <form method="POST" action="/admin/clients/create">

          <div class="mb-3">
            <label class="form-label required">Redirect URIs (<?php echo $strings['oneperline']?>)</label>
            <textarea name="redirect_uris" class="form-control" rows="3" required></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label required">Scopes</label>
            <input type="text" name="scopes" class="form-control" value="openid email profile">
          </div>

          <!--<div class="mb-3">-->
          <!--  <label class="form-check">-->
          <!--    <input class="form-check-input" type="checkbox" name="pkce_required" checked disabled>-->
          <!--    <span class="form-check-label">Require PKCE</span>-->
          <!--  </label>-->
          <!--</div>-->
          
          <div class="mb-3">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="wechat_required" >
              <span class="form-check-label">Require WeChat</span>
            </label>
          </div>

          <button class="btn btn-primary"><?php echo $strings['create']?></button>
        </form>
      </div>
    </div>

    <!-- Client List -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?php echo $strings['registeredclients']?></h3>
      </div>

      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Client ID</th>
              <th>Client Secret</th>
              <th><?php echo $strings['mustbindwechat']?></th>
              <th><?php echo $strings['more']?></th>
              <th><?php echo $strings['status']?></th>
              <th><?php echo $strings['actions']?></th>
            </tr>
          </thead>
          <tbody>

          <?php foreach ($clients as $c): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['id']) ?></td>
              <td><code><?php echo htmlspecialchars($c['client_id']) ?></code></td>
              <td> <code><?php echo htmlspecialchars($c['client_secret']) ?></code></td>
              <td><?php echo $c['wechat_required'] ? 'Yes' : 'No' ?></td>
              <td>
                  <div class="dropdown">
                  <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown"><?php echo $strings['reveal']?></a>
                  <div class="dropdown-menu dropdown-menu-card" style="max-width: 16rem">
                    <div class="card d-flex flex-column">
                      <div class="card-body d-flex flex-column">
                        <h3 class="card-title">
                            Redirect URIs
                          <pre><?php echo htmlspecialchars($c['redirect_uris']) ?></pre>
                            Scopes
                          <pre><?php echo htmlspecialchars($c['scopes']) ?></pre>
                        </h3>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
              
              <td>
                <?php if ($c['revoked']): ?>
                  <span class="status status-red">
                          <span class="status-dot status-dot-animated"></span>
                          <?php echo $strings['revoked']?>
                  </span>
                <?php else: ?>
                  <span class="status status-green">
                          <span class="status-dot status-dot-animated"></span>
                          <?php echo $strings['active']?>
                  </span>
                <?php endif; ?>
              </td>

              <td class="text-end">

                <?php if (!$c['revoked']): ?>

                  <!-- Rotate Secret -->
                  <form method="POST" action="/admin/clients/rotate" style="display:inline-block">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-warning btn-sm"
                      onclick="return confirm('Rotate secret for this client?')">
                      <?php echo $strings['rotate']?>
                    </button>
                  </form>

                  <!-- Revoke Client -->
                  <form method="POST" action="/admin/clients/revoke" style="display:inline-block">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-danger btn-sm"
                      onclick="return confirm('Revoke this client? Tokens will be invalidated.')">
                      <?php echo $strings['revoke']?>
                    </button>
                  </form>

                <?php else: ?>
                  <span class="text-muted"><?php echo $strings['noactions']?></span>
                <?php endif; ?>

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
    require __DIR__ . '/../../../../includes/footer.php';
}