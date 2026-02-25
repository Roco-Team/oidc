<?php
declare(strict_types=1);

namespace App\Http\Controllers\Oidc;

use App\Support\Response;
use App\OAuth\JwkService;

class JwksController {
    public function handle(): void {
        header('Content-Type: application/json');
        echo json_encode(JwkService::publicJwks(), JSON_UNESCAPED_SLASHES);
    }
}
