<?php
declare(strict_types=1);

namespace App\Repository;

use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntity;
use App\Support\Db;

class AccessTokenRepository implements AccessTokenRepositoryInterface {
  public function getNewToken($clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntityInterface {
    $token = new AccessTokenEntity();
    $token->setClient($clientEntity);
    foreach ($scopes as $scope) $token->addScope($scope);
    $token->setUserIdentifier($userIdentifier);
    return $token;
  }
  public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void {
    $stmt = Db::pdo()->prepare('INSERT INTO oauth2_access_tokens (token,user_id,client_id,scopes,expires_at,revoked)
      VALUES (?,?,?,?,?,0)');
    $stmt->execute([
      $accessTokenEntity->getIdentifier(),
      $accessTokenEntity->getUserIdentifier(),
      $accessTokenEntity->getClient()->getIdentifier(),
      json_encode(array_map(fn($s)=>$s->getIdentifier(), $accessTokenEntity->getScopes())),
      $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s')
    ]);
  }
  public function revokeAccessToken($tokenId): void {
    Db::pdo()->prepare('UPDATE oauth2_access_tokens SET revoked=1 WHERE token=?')->execute([$tokenId]);
  }
  public function isAccessTokenRevoked($tokenId): bool {
    $r = Db::pdo()->query("SELECT revoked FROM oauth2_access_tokens WHERE token=" . Db::pdo()->quote($tokenId))->fetch();
    return !$r || (bool)$r['revoked'];
  }
}
