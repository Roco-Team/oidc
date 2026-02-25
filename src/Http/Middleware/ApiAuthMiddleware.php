<?php
namespace App\Http\Middleware;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\Clock\SystemClock;

class ApiAuthMiddleware {
    private Configuration $jwt;
    private string $expectedIssuer;
    private string $expectedAudience;

    public function __construct() {
        // Load Issuer / Audience from .env
        $this->expectedIssuer  = getenv('OIDC_ISSUER') ?: 'https://default-issuer.example.com';
        $this->expectedAudience = getenv('OIDC_AUDIENCE') ?: 'your-resource-audience';

        // Init JWT（RS256）
        $this->jwt = Configuration::forAsymmetricSigner(
            new \Lcobucci\JWT\Signer\Rsa\Sha256(),
            \Lcobucci\JWT\Signer\Key\InMemory::file(__DIR__ . '/../../../keys/private.pem'), // Gen Token
            \Lcobucci\JWT\Signer\Key\InMemory::file(__DIR__ . '/../../../keys/public.key')   // Verify Token
        );
    }

    public function handle(): void {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            http_response_code(401);
            exit('Missing bearer token');
        }

        try {
            $token = $this->jwt->parser()->parse($m[1]);

            // Verify signature and expiration
            $constraints = [
                new SignedWith($this->jwt->signer(), $this->jwt->verificationKey()),
                new StrictValidAt(SystemClock::fromUTC()),
            ];
            if (!$this->jwt->validator()->validate($token, ...$constraints)) {
                throw new \RuntimeException('Invalid signature or expired token');
            }

            // Verify issuer
            $iss = $token->claims()->get('iss');
            if ($iss !== $this->expectedIssuer) {
                throw new \RuntimeException('Invalid issuer');
            }

            // Verify audience
            $aud = $token->claims()->get('aud');
            $audList = is_array($aud) ? $aud : [$aud];
            if (!in_array($this->expectedAudience, $audList, true)) {
                throw new \RuntimeException('Invalid audience');
            }

            // Verify scope
            $scope = $token->claims()->get('scope') ?? '';
            $scopes = explode(' ', $scope);
            if (!in_array('openid', $scopes, true)) {
                throw new \RuntimeException('Insufficient scope');
            }

            // Verified → return user info
            $_SERVER['oidc_sub'] = $token->claims()->get('sub');
            $_SERVER['oidc_client_id'] = $token->claims()->get('client_id') ?? null;

        } catch (\Throwable $e) {
            http_response_code(401);
            exit('Invalid token: ' . $e->getMessage());
        }
    }
}
