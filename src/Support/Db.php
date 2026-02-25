<?php
declare(strict_types=1);

namespace App\Support;
use PDO;

class Db {
  private static PDO $pdo;

  public static function init(array $cfg): void {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
      $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']);
    self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
  }
  public static function pdo(): PDO { return self::$pdo; }
}
