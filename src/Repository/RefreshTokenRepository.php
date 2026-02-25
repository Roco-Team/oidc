<?php
declare(strict_types=1);

namespace App\Repository;

use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntity;
use App\Support\Db;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface {
  public function getNewRefreshToken(): RefreshTokenEntityInterface { return new RefreshTokenEntity(); }
  public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void {
    $stmt = Db::pdo()->prepare('INSERT INTO oauth2_refresh_tokens (token,access_token,expires_at,revoked)
      VALUES (?,?,?,0)');
    $stmt->execute([
      $refreshTokenEntity->getIdentifier(),
      $refreshTokenEntity->getAccessToken()->getIdentifier(),
      $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s')
    ]);
  }
  public function revokeRefreshToken($tokenId): void {
    Db::pdo()->prepare('UPDATE oauth2_refresh_tokens SET revoked=1 WHERE token=?')->execute([$tokenId]);
  }
  public function isRefreshTokenRevoked($tokenId): bool {
    $r = Db::pdo()->query("SELECT revoked FROM oauth2_refresh_tokens WHERE token=" . Db::pdo()->quote($tokenId))->fetch();
    return !$r || (bool)$r['revoked'];
  }
}
