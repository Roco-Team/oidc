<?php
declare(strict_types=1);

namespace App\Support;

class Response {
  public static function json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
  }
  public static function text(string $text, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
  }
  public static function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
  }
}
