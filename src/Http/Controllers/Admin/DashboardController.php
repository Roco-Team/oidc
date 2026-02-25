<?php
namespace App\Http\Controllers\Admin;
use App\Support\Response;
use App\Support\Db;

class DashboardController {
    public function handle(): void {
        // Fetch user audit logs with username and email via JOIN
        // Limit to 50 for performance
        $stmt = Db::pdo()->query("
            SELECT ual.*, u.username, u.email 
            FROM user_audit_logs ual
            LEFT JOIN users u ON ual.user_id = u.id
            ORDER BY ual.id DESC
            LIMIT 50
        ");
        $useraudit = $stmt->fetchAll();

        // Fetch admin audit logs with username via JOIN
        $stmt = Db::pdo()->query("
            SELECT aal.*, au.username
            FROM admin_audit_logs aal
            LEFT JOIN admin_users au ON aal.admin_id = au.id
            ORDER BY aal.id DESC
            LIMIT 50
        ");
        $adminaudit = $stmt->fetchAll();
        
        $adminId = $_SESSION['admin_id'];
        $stmt = Db::pdo()->prepare("SELECT * FROM admin_webauthn_credentials WHERE admin_id=? ORDER BY created_at DESC");
        $stmt->execute([$adminId]);
        $passkeys = $stmt->fetchAll();
        
        ui($useraudit, $adminaudit, $passkeys);
    }
}

function ui($useraudit, $adminaudit, $passkeys) {
    require_once __DIR__ . '/../../../../includes/adminheader.php';
    ?>
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <h2 class="page-title">Audit Logs & Security</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="mb-3">
        <ul class="nav nav-pills" data-bs-toggle="tabs">
          <li class="nav-item">
            <a href="#tabs-admin" class="nav-link active" data-bs-toggle="tab"><?php echo $strings['adminactivity']?></a>
          </li>
          <li class="nav-item">
            <a href="#tabs-user" class="nav-link" data-bs-toggle="tab"><?php echo $strings['useractivity']?></a>
          </li>
          <li class="nav-item">
            <a href="#tabs-passkeys" class="nav-link" data-bs-toggle="tab">My Passkeys</a>
          </li>
        </ul>
      </div>

      <div class="tab-content">
        <!-- Admin Activity Tab -->
        <div class="tab-pane active show" id="tabs-admin">
          <div class="row row-cards">
            <?php foreach ($adminaudit as $audit) { 
                $admin = $audit['username'] ?? 'Unknown Admin';
                
                // Determine icon based on action
                $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9" /><line x1="12" y1="8" x2="12.01" y2="8" /><polyline points="11 12 12 12 12 16 13 16" /></svg>';
                if (strpos($audit['action'], 'login') !== false) {
                    $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M20 12h-13l3 -3m0 6l-3 -3" /></svg>';
                } elseif (strpos($audit['action'], 'create') !== false) {
                    $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>';
                } elseif (strpos($audit['action'], 'revoke') !== false || strpos($audit['action'], 'ban') !== false) {
                    $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9" /><line x1="5.7" y1="5.7" x2="18.3" y2="18.3" /></svg>';
                }
            ?>
            <div class="col-12">
              <div class="card card-sm border-start-3 border-start-<?php echo (strpos($audit['action'], 'ban') !== false || strpos($audit['action'], 'revoke') !== false) ? 'danger' : 'primary'; ?>">
                <div class="card-body">
                  <div class="row align-items-center">
                    <div class="col-auto">
                      <span class="avatar avatar-md rounded bg-transparent text-muted">
                        <?php echo $icon; ?>
                      </span>
                    </div>
                    <div class="col">
                      <div class="font-weight-medium"><?php echo htmlspecialchars($admin); ?></div>
                      <div class="text-secondary">
                        <?php
                        switch ($audit['action']) {
                            case 'create_client':
                                echo '<span class="status-dot status-dot-animated status-green"></span>&nbsp;';
                                echo $strings['create_client'];
                                if ($audit['target_client_id']) echo ' (Client ID: ' . $audit['target_client_id'] . ')';
                                break;
                            case "revoke_client":
                                echo '<span class="status-dot status-dot-animated status-red"></span>&nbsp;';
                                echo $strings['revoke_client'];
                                if ($audit['target_client_id']) echo ' (Client ID: ' . $audit['target_client_id'] . ')';
                                break;
                            case "rotate_clisec":
                                echo '<span class="status-dot status-dot-animated status-indigo"></span>&nbsp;';
                                echo $strings['rotate_clisec'];
                                if ($audit['target_client_id']) echo ' (Client ID: ' . $audit['target_client_id'] . ')';
                                break;
                            case "unfro_user":
                                echo '<span class="status-dot status-dot-animated status-indigo"></span>&nbsp;';
                                echo $strings['unfro_user'];
                                if ($audit['target_user_id']) echo ' (User ID: ' . $audit['target_user_id'] . ')';
                                break;
                            case "ban_user":
                                echo '<span class="status-dot status-dot-animated status-dark"></span>&nbsp;';
                                echo $strings['ban_user'];
                                if ($audit['target_user_id']) echo ' (User ID: ' . $audit['target_user_id'] . ')';
                                break;
                            case "unban_user":
                                echo '<span class="status-dot status-dot-animated status-red"></span>&nbsp;';
                                echo $strings['unban_user'];
                                if ($audit['target_user_id']) echo ' (User ID: ' . $audit['target_user_id'] . ')';
                                break;
                            case "remove_user_passkey":
                                echo '<span class="status-dot status-dot-animated status-red"></span>&nbsp;';
                                echo $strings['removeduserpasskey'];
                                if ($audit['target_user_id']) echo ' (User ID: ' . $audit['target_user_id'] . ')';
                                break;
                            case 'login':
                            case 'login_passkey':
                                echo '<span class="status-dot status-dot-animated status-green"></span>&nbsp;';
                                echo $strings['loginsuccessful'];
                                if ($audit['action'] === 'login_passkey') echo ' (Passkey)';
                                break;
                            default:
                                echo htmlspecialchars($audit['action']);
                                break;
                        }
                        ?>
                      </div>
                    </div>
                    <div class="col-auto ms-auto">
                      <div class="text-muted"><?php echo $audit['created_at']?></div>
                      <div class="mt-1 text-end">
                        <span class="badge bg-teal-lt">IP: <?php echo $audit['ip_address']?></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php } ?>
          </div>
        </div>

        <!-- User Activity Tab -->
        <div class="tab-pane" id="tabs-user">
          <div class="row row-cards">
            <?php foreach ($useraudit as $audit) { 
                $username = $audit['username'] ?? 'Unknown User';
                $email = $audit['email'] ?? '';
                
                // Determine icon and style based on action
                $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9" /><line x1="12" y1="8" x2="12.01" y2="8" /><polyline points="11 12 12 12 12 16 13 16" /></svg>';
                $statusColor = 'blue';
                $actionText = htmlspecialchars($audit['action']);
                
                switch ($audit['action']) {
                    case 'login':
                        $statusColor = 'green';
                        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M20 12h-13l3 -3m0 6l-3 -3" /></svg>';
                        $actionText = 'Login Successful';
                        break;
                    case 'passkey_add':
                        $statusColor = 'green';
                        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9" /><line x1="9" y1="12" x2="15" y2="12" /><line x1="12" y1="9" x2="12" y2="15" /></svg>';
                        $actionText = 'Passkey Added';
                        break;
                    case 'passkey_delete':
                        $statusColor = 'red';
                        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9" /><line x1="5.7" y1="5.7" x2="18.3" y2="18.3" /></svg>';
                        $actionText = 'Passkey Deleted';
                        break;
                    case 'emergency_block':
                        $statusColor = 'dark';
                        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="5" y="11" width="14" height="10" rx="2" /><circle cx="12" cy="16" r="1" /><path d="M8 11v-4a4 4 0 0 1 8 0v4" /></svg>';
                        $actionText = 'Emergency Blocked';
                        break;
                    case 'passwordchange':
                        $statusColor = 'yellow';
                        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="5" y="11" width="14" height="10" rx="2" /><circle cx="12" cy="16" r="1" /><path d="M8 11v-4a4 4 0 0 1 8 0v4" /></svg>';
                        $actionText = 'Password Changed';
                        break;
                    case 'emailchange':
                        $statusColor = 'yellow';
                        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="3" y="5" width="18" height="14" rx="2" /><polyline points="3 7 12 13 21 7" /></svg>';
                        $actionText = 'Email Changed';
                        break;
                    case 'phonechange':
                        $statusColor = 'yellow';
                        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2" /></svg>';
                        $actionText = 'Phone Changed';
                        break;
                    case 'wechat_login_2fa':
                        $statusColor = 'azure';
                        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 12c0 4.3 -3.6 8 -8 8c-4.4 0 -8 -3.7 -8 -8c0 -4.3 3.6 -8 8 -8c4.4 0 8 3.7 8 8z" /></svg>';
                        $actionText = 'WeChat 2FA Toggled';
                        break;
                }
            ?>
            <div class="col-md-6 col-xl-4">
              <div class="card">
                <div class="card-status-top bg-<?php echo $statusColor; ?>"></div>
                <div class="card-body">
                  <div class="d-flex align-items-center mb-3">
                    <span class="avatar avatar-sm rounded me-2 bg-<?php echo $statusColor; ?>-lt"><?php echo $icon; ?></span>
                    <div>
                      <div><strong><?php echo htmlspecialchars($username); ?></strong></div>
                      <div class="text-muted small">ID: #<?php echo $audit['user_id']; ?></div>
                    </div>
                    <div class="ms-auto">
                        <span class="badge bg-<?php echo $statusColor; ?>-lt"><?php echo $actionText; ?></span>
                    </div>
                  </div>
                  <div class="text-secondary mb-3">
                      <?php if ($audit['old_value'] || $audit['new_value']) { ?>
                          <div class="datagrid">
                              <div class="datagrid-item">
                                  <div class="datagrid-title">Old Value</div>
                                  <div class="datagrid-content"><?php echo htmlspecialchars($audit['old_value'] ?? '-'); ?></div>
                              </div>
                              <div class="datagrid-item">
                                  <div class="datagrid-title">New Value</div>
                                  <div class="datagrid-content"><?php echo htmlspecialchars($audit['new_value'] ?? '-'); ?></div>
                              </div>
                          </div>
                      <?php } else { ?>
                          <div class="text-muted fst-italic">No additional details.</div>
                      <?php } ?>
                  </div>
                  <div class="d-flex align-items-center mt-auto">
                    <div class="text-muted small">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-inline" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="4" y="5" width="16" height="16" rx="2" /><line x1="16" y1="3" x2="16" y2="7" /><line x1="8" y1="3" x2="8" y2="7" /><line x1="4" y1="11" x2="20" y2="11" /><line x1="11" y1="15" x2="12" y2="15" /><line x1="12" y1="15" x2="12" y2="18" /></svg>
                        <?php echo $audit['created_at']; ?>
                    </div>
                    <div class="ms-auto text-muted small">
                        IP: <?php echo $audit['ip_address']; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php } ?>
          </div>
        </div>

        <!-- Passkeys Tab -->
        <div class="tab-pane" id="tabs-passkeys">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">My Passkeys (Admin)</h3>
              <div class="card-actions">
                <button class="btn btn-primary" onclick="Passkey.register('/admin/passkey/register/options', '/admin/passkey/register/verify')">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                  Add Passkey
                </button>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table card-table table-vcenter text-nowrap datatable">
                <thead>
                  <tr>
                    <th>Credential ID (Partial)</th>
                    <th>Created At</th>
                    <th>Sign Count</th>
                    <th class="w-1">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($passkeys)) { ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted p-3">No passkeys found. Add one to enable passwordless login.</td>
                    </tr>
                  <?php } else { 
                      foreach ($passkeys as $pk) { ?>
                  <tr>
                    <td>
                        <span class="text-muted" title="<?php echo htmlspecialchars($pk['credential_id']); ?>">
                            <?php echo substr($pk['credential_id'], 0, 15) . '...'; ?>
                        </span>
                    </td>
                    <td><?php echo $pk['created_at']; ?></td>
                    <td><?php echo $pk['sign_count']; ?></td>
                    <td>
                      <button class="btn btn-danger btn-sm btn-icon" onclick="deletePasskey('<?php echo $pk['credential_id']; ?>')" title="Delete">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="4" y1="7" x2="20" y2="7" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                      </button>
                    </td>
                  </tr>
                  <?php } 
                  } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="/assets/js/passkey.js"></script>
<script>
function deletePasskey(id) {
    if (!confirm('Are you sure you want to delete this passkey?')) return;
    fetch('/admin/passkey/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    }).then(res => res.json()).then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert(res.error || 'Unknown error');
        }
    }).catch(err => {
        console.error(err);
        alert('Request failed');
    });
}
</script>
<?php
}
