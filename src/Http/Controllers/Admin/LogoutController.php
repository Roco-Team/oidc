<?php
namespace App\Http\Controllers\Admin;
use App\Support\Response;
use App\Support\Db;


class LogoutController {
    public function handle(): void {
        // Self-logout
        
        $id = (int)$_SESSION['admin_id'];
        
        // revoke sessions
        $stmt = Db::pdo()->prepare("UPDATE admin_sessions SET revoked=1 WHERE admin_id=?");
        $stmt->execute([$id]);
        
        session_unset();
        session_destroy();
        Response::redirect('/admin/login');
    }
}
