<?php
declare(strict_types=1);

namespace App\Repository;

use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Entities\AuthCodeEntity;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use App\Support\Db;

class AuthCodeRepository implements AuthCodeRepositoryInterface {
  public function getNewAuthCode(): AuthCodeEntityInterface { return new AuthCodeEntity(); }
  public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void {
    $stmt = Db::pdo()->prepare('INSERT INTO oauth2_auth_codes (code,user_id,client_id,scopes,expires_at,revoked,nonce)
      VALUES (?,?,?,?,?,0,?)');
    $stmt->execute([
      $authCodeEntity->getIdentifier(),
      $authCodeEntity->getUserIdentifier(),
      $authCodeEntity->getClient()->getIdentifier(),
      json_encode(array_map(fn($s)=>$s->getIdentifier(), $authCodeEntity->getScopes())),
      $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
      $_SESSION['oidc_nonce'] ?? null
    ]);
  }
  public function revokeAuthCode($codeId): void {
    Db::pdo()->prepare('UPDATE oauth2_auth_codes SET revoked=1 WHERE code=?')->execute([$codeId]);
  }
  public function isAuthCodeRevoked($codeId): bool {
    $r = Db::pdo()->query("SELECT revoked FROM oauth2_auth_codes WHERE code=" . Db::pdo()->quote($codeId))->fetch();
    return !$r || (bool)$r['revoked'];
  }
}
