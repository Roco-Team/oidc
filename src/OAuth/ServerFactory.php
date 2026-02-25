<?php
declare(strict_types=1);

namespace App\OAuth;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\CryptKey;
use DateInterval;
use App\Repository\ClientRepository;
use App\Repository\ScopeRepository;
use App\Repository\AccessTokenRepository; ##
use App\Repository\AuthCodeRepository;
use App\Repository\RefreshTokenRepository;

class ServerFactory {
  public static function make(): AuthorizationServer {
    $server = new AuthorizationServer(
      new ClientRepository(),
      new AccessTokenRepository(),
      new ScopeRepository(),
      new CryptKey(__DIR__ . '/../../keys/private.pem', null, false),
      base64_decode(substr($_ENV['OAUTH2_ENC_KEY'], 7))
    );

    $authCodeGrant = new AuthCodeGrant(
      new AuthCodeRepository(),
      new RefreshTokenRepository(),
      new DateInterval('PT10M')
    );
    $authCodeGrant->setRefreshTokenTTL(new DateInterval('P30D'));

    $server->enableGrantType($authCodeGrant, new DateInterval('PT1H'));

    $refreshGrant = new RefreshTokenGrant(new RefreshTokenRepository());
    $refreshGrant->setRefreshTokenTTL(new DateInterval('P30D'));

    $server->enableGrantType($refreshGrant, new DateInterval('PT1H'));

    return $server;
  }
}
