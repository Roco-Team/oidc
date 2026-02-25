<?php
declare(strict_types=1);

namespace App\Repository;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ClientEntity;
use App\Support\Db;
use PDO;

class ClientRepository implements ClientRepositoryInterface {
  public function getClientEntity($clientIdentifier): ?ClientEntityInterface {
    $stmt = Db::pdo()->prepare('SELECT * FROM oauth2_clients WHERE client_id=?');
    $stmt->execute([$clientIdentifier]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $client = new ClientEntity();
    $client->setIdentifier($row['client_id']);
    $client->setName('Discourse');
    $client->setRedirectUri(json_decode($row['redirect_uris'], true));
    return $client;
  }

  public function validateClient($clientIdentifier, $clientSecret, $grantType): bool {
    $stmt = Db::pdo()->prepare('SELECT client_secret FROM oauth2_clients WHERE client_id=?');
    $stmt->execute([$clientIdentifier]);
    $row = $stmt->fetch();
    return $row && hash_equals($row['client_secret'], $clientSecret);
  }
}
