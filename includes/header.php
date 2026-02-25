<?php 
use App\Support\Db;
require_once __DIR__ . '/lang.php'; 
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$sitename = $_ENV["SITE_NAME"] ?? 'AuthorizeCenter';
$sitetitle = $sitename;
$intheaccsettingspage = 0;
if (str_starts_with($path, '/account')) {
    $intheaccsettingspage = 1;
    $sitetitle = $strings['accountsettings'] . ' - ' . $sitetitle;
}
if (str_starts_with($path, '/login')) $sitetitle = $strings['login'] . ' - ' . $sitetitle;
if (str_starts_with($path, '/register')) $sitetitle = $strings['register'] . ' - ' . $sitetitle;

if (isset($_SESSION['uid'])) {
    $uid = (int)$_SESSION['uid'];
    $u = Db::pdo()->query("SELECT username,email,phone_e164,wechat_openid,wechat_login_2fa,email_verified FROM users WHERE id=$uid")->fetch();
}

$JSDURL = $_ENV['JSD_URL'] ?? 'https://cdn.jsdelivr.net';
?>
<html>
<head>
    <title><?php echo $sitetitle?></title>

    <link rel="stylesheet"
      href="<?php echo $JSDURL;?>/npm/@tabler/core@1.4.0/dist/css/tabler.min.css" />
    <script
      src="<?php echo $JSDURL;?>/npm/@tabler/core@1.4.0/dist/js/tabler.min.js">
    </script>
    
</head>
    
<body>
<header class="navbar navbar-expand-md d-print-none">
  <div class="container-xl">
    <button
      class="navbar-toggler"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#navbar-menu"
      aria-controls="navbar-menu"
      aria-expanded="false"
      aria-label="Toggle navigation"
    >
      <span class="navbar-toggler-icon"></span>
    </button>
	<!-- BEGIN NAVBAR LOGO --><a href="/account" aria-label="Tabler" class="navbar-brand navbar-brand-autodark me-3">
	    <?php
	    if (isset($_ENV['SITE_LOGO'])) {
	    ?>
	    <img src="<?php echo $_ENV['SITE_LOGO']?>" width="156px"/>
	    <?php
	    } else echo $sitename;
	    ?>
	</a><!-- END NAVBAR LOGO -->
	<div>
		<span class="badge">v0.1β</span>
	</div>&nbsp;&nbsp;&nbsp;
    <ul class="navbar-nav">
      <li class="nav-item <?php if ($intheaccsettingspage) echo "active";?>">
        <a class="nav-link" href="/account">
          <span class="nav-link-icon">
            <!-- Download SVG icon from http://tabler.io/icons/icon/home -->
	<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-lock-cog"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 21h-5a2 2 0 0 1 -2 -2v-6a2 2 0 0 1 2 -2h10c.564 0 1.074 .234 1.437 .61" /><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M8 11v-4a4 4 0 1 1 8 0v4" /><path d="M19.001 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M19.001 15.5v1.5" /><path d="M19.001 21v1.5" /><path d="M22.032 17.25l-1.299 .75" /><path d="M17.27 20l-1.3 .75" /><path d="M15.97 17.25l1.3 .75" /><path d="M20.733 20l1.3 .75" /></svg>
          </span>
          <span class="nav-link-title"> <?php echo $strings['accountsettings']?> </span>
        </a>
      </li>
      
      <li class="nav-item">
        <a class="nav-link" href="https://github.com/Roco-Team/oidc" target="_blank">
          <span class="nav-link-icon"
            ><!-- Download SVG icon from http://tabler.io/icons/icon/checkbox -->
	<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-github"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 19c-4.3 1.4 -4.3 -2.5 -6 -3m12 5v-3.5c0 -1 .1 -1.4 -.5 -2c2.8 -.3 5.5 -1.4 5.5 -6a4.6 4.6 0 0 0 -1.3 -3.2a4.2 4.2 0 0 0 -.1 -3.2s-1.1 -.3 -3.5 1.3a12.3 12.3 0 0 0 -6.2 0c-2.4 -1.6 -3.5 -1.3 -3.5 -1.3a4.2 4.2 0 0 0 -.1 3.2a4.6 4.6 0 0 0 -1.3 3.2c0 4.6 2.7 5.7 5.5 6c-.6 .6 -.6 1.2 -.5 2v3.5" /></svg>
          </span>
          <span class="nav-link-title"> Roco-Team/oidc </span>
        </a>
      </li>
    </ul>
<?php if (isset($_SESSION['uid'])) { ?>    
    <div class="navbar-nav flex-row order-md-last ms-auto">
      <div class="nav-item dropdown">
        <a
          href="#"
          class="nav-link d-flex lh-1 text-reset"
          data-bs-toggle="dropdown"
          aria-label="Open user menu"
        >
          <span
            class="avatar avatar-sm"
          ><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-circle"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" /></svg></span>
          <div class="d-none d-xl-block ps-2">
            <div><?php echo $u['username'] ?? 'user_id'?></div>
          </div>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
          <a href="/account/logout" class="dropdown-item"><?php echo $strings['logout'];?></a>
          <div class="dropdown-divider"></div>
          <a href="/account/logout_all" class="dropdown-item" style="color:red"><?php echo $strings['logout_all'];?></a>
        </div>
      </div>
    </div>
<?php } else { ?>
    <div class="navbar-nav flex-row order-md-last ms-auto">
      <div class="nav-item">
        <a href="/login/password?portal" class="btn btn-primary"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-login-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2" /><path d="M3 12h13l-3 -3" /><path d="M13 15l3 -3" /></svg>&nbsp;<?php echo $strings['login']?></a>
        
      </div>
    </div>
<?php } ?>
  </div>
</header>