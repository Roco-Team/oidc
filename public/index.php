<?php
declare(strict_types=1);
ob_start(); 

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap.php';

if (isset($_ENV['SITE_LOGO'])) {
    $parts = parse_url($_ENV['SITE_LOGO']);
    $logoroot = $parts['scheme'] . '://' . $parts['host'];
} else $logoroot = NULL;

$JSDURL = $_ENV['JSD_URL'] ?? 'https://cdn.jsdelivr.net';

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' ".$JSDURL." https://static.cloudflareinsights.com; " .
    "style-src 'self' 'unsafe-inline' ".$JSDURL."; " .
    "img-src 'self' data: ".$logoroot."; " .
    "upgrade-insecure-requests;");
header_remove("X-Powered-By");

use App\Support\Response;
use App\Http\Middleware\EnsureHttps;

$middleware = [new EnsureHttps()];
foreach ($middleware as $m) { 
    $m->handle(); 
}

require __DIR__ . '/../config/routes.php';
