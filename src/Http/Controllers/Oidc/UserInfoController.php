<?php
declare(strict_types=1);

namespace App\Http\Controllers\Oidc;

use App\Support\Response;
use App\Support\Db;

class UserInfoController {
  public function handle(): void {

    // 1. Read Authorization header
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($auth, 'Bearer ')) {
        http_response_code(401);
        exit('Missing bearer token');
    }

    $token = substr($auth, 7);

    // 2. Check access_token
    $stmt = Db::pdo()->prepare("
      SELECT user_id, client_id, revoked, expires_at
      FROM oauth2_access_tokens
      WHERE token=?
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    // 3. Token invalided
    if (!$row || (int)$row['revoked'] === 1 || strtotime($row['expires_at']) < time()) {
        http_response_code(401);
        exit('Invalid or expired access token');
    }

    // 4. Portal token is not allowed in /userinfo
    if ($row['client_id'] === 'portal') {
        http_response_code(401);
        exit('Portal tokens cannot access /userinfo');
    }

    // 5. check user
    $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([(int)$row['user_id']]);
    $u = $stmt->fetch();

    if (!$u) {
        http_response_code(401);
        exit('User not found');
    }

    // 6. Return OIDC Standard Claims
    Response::json([
      'sub' => (string)$u['id'],
      'email' => $u['email'],
      'email_verified' => (bool)$u['email_verified'],
      'preferred_username' => $u['username'],
      'name' => $u['username'],
    ]);
  }
}
