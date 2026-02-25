<?php
declare(strict_types=1);

use App\Support\Response;
use App\Http\Controllers\Oidc\DiscoveryController;
use App\Http\Controllers\Oidc\JwksController;
use App\Http\Controllers\Oidc\AuthorizeController;
use App\Http\Controllers\Oidc\TokenController;
use App\Http\Controllers\Oidc\UserInfoController;
use App\Http\Controllers\Auth\PasswordLoginController;
use App\Http\Controllers\Auth\PasskeyLoginController;
use App\Http\Controllers\Auth\WechatLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\WechatBindController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\LogoutAllController;
use App\Http\Controllers\Generator;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = urldecode($path);
$path = trim($path);

if ($path !== '/' && substr($path, -1) === '/') {
    $path = substr($path, 0, -1);
}
$method = $_SERVER['REQUEST_METHOD'];

function route(string $pattern): bool {
  global $path;
  return $path === $pattern;
}

if (route('/')) {
    Response::redirect('/account');
}

// OIDC Endpoints
if (route('/.well-known/openid-configuration')) (new DiscoveryController())->handle();
elseif (route('/jwks.json')) (new JwksController())->handle();
elseif (route('/authorize')) (new AuthorizeController())->handle();
elseif (route('/token')) (new TokenController())->handle();
elseif (route('/userinfo')) (new UserInfoController())->handle();
elseif (route('/login/password')) (new PasswordLoginController())->handle();
elseif (route('/login/passkey')) (new PasskeyLoginController())->handle();
elseif (route('/login/passkey/options')) (new PasskeyLoginController())->loginOptions();
elseif (route('/login/passkey/verify')) (new PasskeyLoginController())->loginVerify();
elseif (route('/login/wechat')) (new WechatLoginController())->handle();
elseif (route('/register')) (new RegisterController())->handle();
elseif (route('/verify/email')) (new VerifyEmailController())->handle();
elseif (route('/account')) (new AccountController())->handle();
elseif (route('/account/bind/wechat')) (new WechatBindController())->handle();
elseif (route('/account/passkey/register/options')) (new AccountController())->passkeyRegisterOptions();
elseif (route('/account/passkey/register/verify')) (new AccountController())->passkeyRegisterVerify();
elseif (route('/account/passkey/delete')) (new AccountController())->passkeyDelete();

// DANGEROUS PART BEGIN
elseif ($path === '/reset') {

    exit(); // Leave this alone unless you want to rebuild your entire oidc database
    
    $token = $_GET['confirm'] ?? '';
    if ($token !== '1') {
        \App\Support\Response::text('Unauthorized', 401);
        exit;
    }

    require __DIR__ . '/../scripts/init.php';
}
// DANGEROUS PART END



// You can fit your own scripts like this here
// elseif ($path === '/hyw') {
//     require __DIR__ . '/../scripts/helloworld.php';
// }



// Admin Part
elseif ($path === '/admin/login') {
    (new \App\Http\Controllers\Admin\AdminLoginController())->handle();
}
elseif ($path === '/admin/login/passkey/options') {
    (new \App\Http\Controllers\Admin\AdminPasskeyController())->loginOptions();
}
elseif ($path === '/admin/login/passkey/verify') {
    (new \App\Http\Controllers\Admin\AdminPasskeyController())->loginVerify();
}

elseif (str_starts_with($path, '/admin')) {
    (new \App\Http\Middleware\AdminAuth())->handle();

    if ($path === '/admin') {
        (new \App\Http\Controllers\Admin\DashboardController())->handle();
    }
    elseif ($path === '/admin/passkey/register/options') {
        (new \App\Http\Controllers\Admin\AdminPasskeyController())->registerOptions();
    }
    elseif ($path === '/admin/passkey/register/verify') {
        (new \App\Http\Controllers\Admin\AdminPasskeyController())->registerVerify();
    }
    elseif ($path === '/admin/passkey/delete') {
        (new \App\Http\Controllers\Admin\AdminPasskeyController())->delete();
    }
    elseif ($path === '/admin/users') {
        (new \App\Http\Controllers\Admin\UserController())->handle();
    }
    elseif ($path === '/admin/users/ban') {
        (new \App\Http\Controllers\Admin\UserController())->ban();
    }
    elseif ($path === '/admin/users/unban') {
        (new \App\Http\Controllers\Admin\UserController())->unban();
    }
    elseif ($path === '/admin/users/unfro') {
        (new \App\Http\Controllers\Admin\UserController())->unfro();
    }
    elseif ($path === '/admin/users/passkeys') {
        (new \App\Http\Controllers\Admin\UserController())->passkeys();
    }
    elseif ($path === '/admin/users/passkeys/delete') {
        (new \App\Http\Controllers\Admin\UserController())->deletePasskey();
    }
    elseif ($path === '/admin/clients') {
        (new \App\Http\Controllers\Admin\ClientController())->handle();
    }
    elseif ($path === '/admin/clients/create') {
        (new \App\Http\Controllers\Admin\ClientController())->create();
    }
    elseif ($path === '/admin/clients/revoke') {
        (new \App\Http\Controllers\Admin\ClientController())->revoke();
    }
    elseif ($path === '/admin/clients/rotate') {
        (new \App\Http\Controllers\Admin\ClientController())->rotate();
    }
    elseif ($path === '/admin/system') {
        (new \App\Http\Controllers\Admin\SystemController())->handle();
    }
    elseif ($path === '/admin/logout') {
        (new \App\Http\Controllers\Admin\LogoutController())->handle();
    }
    elseif ($path === '/admin/logout_all') {
        (new \App\Http\Controllers\Admin\LogoutAllController())->handle();
    }
}

// User Center
elseif ($path === '/account/logout') {
    (new LogoutController())->handle();
}
elseif ($path === '/account/logout_all') {
    (new LogoutAllController())->handle();
}

// Utilities
elseif ($path === '/ut/generator') {
    (new Generator())->handle();
}

// Return 404
else Response::text('Not Found', 404);
