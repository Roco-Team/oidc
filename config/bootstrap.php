<?php
declare(strict_types=1);

use App\Support\Db;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$requestUri = $_SERVER['REQUEST_URI'] ?? '';

$publicPaths = ['/login', '/authorize', '/token', '/init', '/.well-known/openid-configuration', '/admin', '/assets', '/register', '/verify', '/account', '/jwks.json', '/ut/generator', '/reset', '/userinfo'];
$isPublic = false;
foreach ($publicPaths as $path) {
    if (strpos($requestUri, $path) === 0) {
        $isPublic = true;
        break;
    }
}

if (!$isPublic) {
    (new \App\Http\Middleware\ApiAuthMiddleware())->handle();
}


Db::init([
  'host' => $_ENV['DB_HOST'],
  'port' => (int)$_ENV['DB_PORT'],
  'database' => $_ENV['DB_DATABASE'],
  'username' => $_ENV['DB_USERNAME'],
  'password' => $_ENV['DB_PASSWORD'],
  'charset' => 'utf8mb4'
]);

date_default_timezone_set('UTC');
session_set_cookie_params([
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Strict'
]);
session_start();
