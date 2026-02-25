<?php
namespace App\Http\Controllers\Admin;
use App\Support\Response;


class SystemController {
    public function handle(): void {
        ui();
    }
}


function ui() {
    require_once __DIR__ . '/../../../../includes/adminheader.php';
    echo "<h2>".$strings['systemutil']."</h2>
              <p>Issuer: {$_ENV['OIDC_ISSUER']}</p>
              <p>Time: ".date('c')."</p>
              <ul>
                <li><a href='/init?token=supersecret'>初始化数据库 Reset DB</a></li>
                <li><a href='/admin/logout_all'>下线所有用户 Logout All Users</a></li>
                <li><a href='/ut/generator'>生成工具 Generator</a></li>
              </ul>
              ";
}