<?php
declare(strict_types=1);

namespace App\Http\Middleware;

class EnsureHttps {
  public function handle(): void {
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
      $host = $_SERVER['HTTP_HOST'];
      $uri = $_SERVER['REQUEST_URI'];
      header('Location: https://' . $host . $uri, true, 301);
      exit;
    }
  }
}
