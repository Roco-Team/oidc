<?php
declare(strict_types=1);

namespace App\Http\Controllers\Oidc;

use App\Support\Response;
use App\Support\Db;
use App\OAuth\IdTokenService;

class TokenController {
    public function handle(): void {

        // Must be authorization_code
        if (($_POST['grant_type'] ?? '') !== 'authorization_code') {
            Response::json(['error' => 'unsupported_grant_type'], 400);
            return;
        }

        $code = $_POST['code'] ?? null;
        $redirectUri = $_POST['redirect_uri'] ?? null;
        $codeVerifier = $_POST['code_verifier'] ?? null;

        if (!$code || !$redirectUri) {
            Response::json(['error' => 'invalid_request'], 400);
            return;
        }

        // Get auth code
        $stmt = Db::pdo()->prepare("
            SELECT * FROM oauth2_auth_codes
            WHERE code=? AND revoked=0 AND expires_at > NOW()
        ");
        $stmt->execute([$code]);
        $auth = $stmt->fetch();

        if (!$auth) {
            Response::json(['error' => 'invalid_grant'], 400);
            return;
        }

        // verify redirect_uri
        if (rtrim($auth['redirect_uri'], '/') !== rtrim($redirectUri, '/')) {
            Response::json(['error' => 'invalid_grant'], 400);
            return;
        }

        // verify PKCE (if code_challenge involved)
        if ($auth['code_challenge'] && $auth['code_challenge'] !== 'null') {
            if (!$codeVerifier) {
                Response::json(['error' => 'invalid_grant', 'error_description' => 'code_verifier required'], 400);
                return;
            }
            $expected = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            if ($expected !== $auth['code_challenge']) {
                Response::json(['error' => 'invalid_grant', 'error_description' => 'code_verifier invalid'], 400);
                return;
            }
        }

        // Generate access_token
        $token = bin2hex(random_bytes(32));

        $stmt = Db::pdo()->prepare("
            INSERT INTO oauth2_access_tokens (token, user_id, client_id, expires_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ");
        $stmt->execute([$token, $auth['user_id'], $auth['client_id']]);

        // revoke auth code 
        Db::pdo()->prepare("UPDATE oauth2_auth_codes SET revoked=1 WHERE id=?")
                 ->execute([$auth['id']]);

        // Generate ID Token
        $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$auth['user_id']]);
        $u = $stmt->fetch();

        $idToken = IdTokenService::sign([
            'iss' => $_ENV['OIDC_ISSUER'],
            'aud' => $auth['client_id'],
            'iat' => time(),
            'exp' => time() + 3600,
            'sub' => (string)$u['id'],
            'email' => $u['email'],
            'email_verified' => (bool)$u['email_verified'],
            'preferred_username' => $u['username'],
            'name' => $u['username'],
            'nonce' => $auth['nonce'] ?? null,
            'auth_time' => $_SESSION['auth_time'] ?? time(),
        ]);
        
        
        
        Response::json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'id_token' => $idToken
        ]);
    }
}
