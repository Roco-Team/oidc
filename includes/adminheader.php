<?php 
use App\Support\Db;
require_once __DIR__ . '/lang.php'; 
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$sitename = $_ENV["SITE_NAME"] ?? 'AuthorizeCenter';
$sitetitle = $strings['adminportal'] . ' - ' . $sitename;
if (isset($_SESSION['admin_id'])) {
    $uid = (int)$_SESSION['admin_id'];
    $u = Db::pdo()->query("SELECT username FROM admin_users WHERE id=$uid")->fetch();
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
<?php if (isset($_SESSION['admin_id'])) { ?>    
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
          ><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-skyscraper"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0" /><path d="M5 21v-14l8 -4v18" /><path d="M19 21v-10l-6 -4" /><path d="M9 9l0 .01" /><path d="M9 12l0 .01" /><path d="M9 15l0 .01" /><path d="M9 18l0 .01" /></svg></span>
          <div class="d-none d-xl-block ps-2">
            <div><?php echo $u['username'] ?? 'admin_id'?></div>
          </div>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
          <a href="/admin/logout" class="dropdown-item"><?php echo $strings['logout'];?></a>
        </div>
      </div>
    </div>
<?php } else { ?>
    <div class="navbar-nav flex-row order-md-last ms-auto">
      
    </div>
<?php } ?>
  </div>
</header>
<?php if (isset($_SESSION['admin_id'])) { ?>    
<header class="navbar-expand-md">
<div class="collapse navbar-collapse" id="navbar-menu">
  <div class="navbar">
    <div class="container-xl">
      <div class="row flex-column flex-md-row flex-fill align-items-center">
        <div class="col">
          <!-- BEGIN NAVBAR MENU -->
          <ul class="navbar-nav">
            <li class="nav-item <?php if ($path == '/admin') { ?>active<?php } ?>">
              <a class="nav-link" href="/admin">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-layout-dashboard"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1" /><path d="M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1" /><path d="M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1" /><path d="M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1" /></svg></span>
                <span class="nav-link-title"> <?php echo $strings['audit']?> </span>
              </a>
            </li>
            <li class="nav-item <?php if ($path == '/admin/users') { ?>active<?php } ?>">
              <a class="nav-link" href="/admin/users">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-cog"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h2.5" /><path d="M19.001 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M19.001 15.5v1.5" /><path d="M19.001 21v1.5" /><path d="M22.032 17.25l-1.299 .75" /><path d="M17.27 20l-1.3 .75" /><path d="M15.97 17.25l1.3 .75" /><path d="M20.733 20l1.3 .75" /></svg></span>
                <span class="nav-link-title"> <?php echo $strings['usercontrol']?> </span>
              </a>
            </li>
            <li class="nav-item <?php if ($path == '/admin/clients') { ?>active<?php } ?>">
              <a class="nav-link" href="/admin/clients">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-desktop-cog"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 16h-8a1 1 0 0 1 -1 -1v-10a1 1 0 0 1 1 -1h16a1 1 0 0 1 1 1v7" /><path d="M7 20h5" /><path d="M9 16v4" /><path d="M19.001 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M19.001 15.5v1.5" /><path d="M19.001 21v1.5" /><path d="M22.032 17.25l-1.299 .75" /><path d="M17.27 20l-1.3 .75" /><path d="M15.97 17.25l1.3 .75" /><path d="M20.733 20l1.3 .75" /></svg></span>
                <span class="nav-link-title"> <?php echo $strings['clientcontrol']?> </span>
              </a>
            </li>
            <li class="nav-item <?php if ($path == '/admin/system') { ?>active<?php } ?>">
              <a class="nav-link" href="/admin/system">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shield-cog"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 21a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3a12 12 0 0 0 8.5 3c.568 1.933 .635 3.957 .223 5.89" /><path d="M19.001 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M19.001 15.5v1.5" /><path d="M19.001 21v1.5" /><path d="M22.032 17.25l-1.299 .75" /><path d="M17.27 20l-1.3 .75" /><path d="M15.97 17.25l1.3 .75" /><path d="M20.733 20l1.3 .75" /></svg></span>
                <span class="nav-link-title"> <?php echo $strings['systemutil']?> </span>
              </a>
            </li>
          </ul>
          <!-- END NAVBAR MENU -->
        </div>
        
      </div>
    </div>
  </div>
</div>
</header>
<?php } ?>