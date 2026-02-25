<?php
declare(strict_types=1);

namespace App\Http\Controllers\Oidc;

use App\Support\Response;
use App\Http\Middleware\EnsureLoggedIn;
use App\Support\Db;

class AuthorizeController {
  public function handle(): void {
    (new EnsureLoggedIn())->handle();

    // Treat portal login specially
    if (isset($_GET['portal']) || isset($_GET['invalidcred'])) {
        Response::redirect('/account');
        exit;
    }

    // // PKCE 必须存在
    // if (empty($_GET['code_challenge'])) {
    //     http_response_code(400);
    //     PKCE_required();
    //     exit;
    // }

    $clientId = $_GET['client_id'] ?? null;
    $redirectUri = $_GET['redirect_uri'] ?? null;
    $state = $_GET['state'] ?? null;
    $nonce = $_GET['nonce'] ?? null;

    // verify client_id / redirect_uri
    if (!$clientId || !$redirectUri || !$this->isValidClient($clientId, $redirectUri)) {
        Response::text('Invalid client or redirect_uri', 400);
        exit();
    }
    
    // 强制绑定微信
    if ($this->isWeChatRequired($clientId) === 1) {
        $stmt = Db::pdo()->prepare("
            SELECT wechat_openid 
            FROM users 
            WHERE id=?
        ");
        $stmt->execute([$_SESSION['uid']]);
        $user = $stmt->fetch();
        if (!isset($user['wechat_openid'])) {
            require_once __DIR__ . '/../../../../includes/lang.php';
            Response::text($strings['wechatbindenforced'], 401);
            exit();
        }
    }

    // Generate auth code
    $code = bin2hex(random_bytes(32));

    $stmt = Db::pdo()->prepare("
        INSERT INTO oauth2_auth_codes 
        (code, user_id, client_id, redirect_uri, code_challenge, nonce, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
    ");
    $stmt->execute([
        $code,
        $_SESSION['uid'],
        $clientId,
        $redirectUri,
        $_GET['code_challenge'] ?? 'null',
        $nonce
    ]);

    // OIDC redirect
    $url = $redirectUri . '?code=' . urlencode($code);
    if ($state) {
        $url .= '&state=' . urlencode($state);
    }

    Response::redirect($url);
  }

  private function isValidClient(string $clientId, string $redirectUri): bool {
    $stmt = Db::pdo()->prepare("
        SELECT redirect_uris, revoked 
        FROM oauth2_clients 
        WHERE client_id=?
    ");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();

    if (!$client || (int)$client['revoked'] === 1) {
        return false;
    }

    // JSON decode
    $uris = json_decode($client['redirect_uris'], true);
    if (!is_array($uris)) {
        return false;
    }

    $redirectUri = rtrim($redirectUri, '/');

    foreach ($uris as $uri) {
        if (rtrim($uri, '/') === $redirectUri) {
            return true;
        }
    }

    return false;
  }
  
  private function isWeChatRequired($clientId) {
      $stmt = Db::pdo()->prepare("
        SELECT wechat_required 
        FROM oauth2_clients 
        WHERE client_id=?
    ");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();
    
    return $client['wechat_required'];
  }
}
