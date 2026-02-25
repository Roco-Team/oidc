<?php
declare(strict_types=1);

namespace App\Repository;

use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntity;

class ScopeRepository implements ScopeRepositoryInterface {
  public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface {
    $scope = new ScopeEntity();
    $scope->setIdentifier($identifier);
    return $scope;
  }
  public function finalizeScopes(array $scopes, $grantType, $clientEntity, $userIdentifier = null): array {
    return $scopes; 
  }
}
