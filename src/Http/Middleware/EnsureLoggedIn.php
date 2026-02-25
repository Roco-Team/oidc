<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Db;
use App\Support\Response;

class EnsureLoggedIn {
  public function handle(): void {
    if (empty($_SESSION['cookie'])) {
      Response::redirect('/login/password' . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
      exit;
    }

    $stmt = Db::pdo()->prepare("
      SELECT * FROM user_sessions 
      WHERE session_token=? AND revoked=0
    ");
    $stmt->execute([$_SESSION['cookie']]);
    $row = $stmt->fetch();

    if (!$row || strtotime($row['expires_at']) < time()) { // using invalid session (Expired or Never-Existed)
      session_unset();
      session_destroy();
      Response::redirect('/login/password?invalidcred');
      exit;
    }

    if ($row['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) { // Session valid, but User-Agent mismatch. REVOKE
          $stmt = Db::pdo()->prepare("UPDATE user_sessions SET revoked=1 WHERE id=?");
          $stmt->execute([$row['id']]);
          session_unset();
          session_destroy();
          Response::redirect('/login/password?invalidcred');
          exit;
    }

    // Check if user is banned
    $stmt = Db::pdo()->prepare("SELECT ban FROM users WHERE id=?");
    $stmt->execute([$row['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['ban'] > 0) {
        // Revoke session if ban > 0 (could be blocked (10) or banned (1))
        $stmt = Db::pdo()->prepare("UPDATE user_sessions SET revoked=1 WHERE id=?");
        $stmt->execute([$row['id']]);
        session_unset();
        session_destroy();
        Response::text('Your account has been suspended.', 403);
        exit;
    }

    $_SESSION['uid'] = $row['user_id'];
    
    // Expand session & update expires_at
     $stmt = Db::pdo()->prepare("UPDATE user_sessions SET expires_at=DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id=?");
     $stmt->execute([$row['id']]);
  }
}


