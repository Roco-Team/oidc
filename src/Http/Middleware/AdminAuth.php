<?php
declare(strict_types=1);

namespace App\Http\Middleware;
use App\Support\Response;
use App\Support\Db;

class AdminAuth {
  public function handle(): void {
    if (empty($_SESSION['admin_cookie'])) {
      Response::redirect('/admin/login');
      exit;
    }

    $stmt = Db::pdo()->prepare("
      SELECT * FROM admin_sessions 
      WHERE session_token=? AND revoked=0
    ");
    $stmt->execute([$_SESSION['admin_cookie']]);
    $row = $stmt->fetch();

    if (!$row || strtotime($row['expires_at']) < time()) {
      session_unset();
      session_destroy();
      Response::redirect('/admin/login');
      exit;
    }
    $_SESSION['admin_id'] = $row['admin_id'];
  }
}

