<?php
namespace App\Http\Controllers\Oidc;

use App\Support\Response;

class DiscoveryController {
    public function handle(): void {
        $issuer = $_ENV['OIDC_ISSUER'];

        Response::json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'userinfo_endpoint' => $issuer . '/userinfo',
            'jwks_uri' => $issuer . '/jwks.json',
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'email', 'profile'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'claims_supported' => [
                'sub', 'email', 'email_verified', 'preferred_username', 'name', 'phone_number'
            ]
        ]);
    }
}
