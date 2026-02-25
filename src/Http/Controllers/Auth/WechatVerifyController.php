<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;
use App\Support\Response;
use App\Support\Db;

class WechatLoginController {
  public function handle(): int {
    Response::text('Not implemented', 501);
    return 1;
  }
}
