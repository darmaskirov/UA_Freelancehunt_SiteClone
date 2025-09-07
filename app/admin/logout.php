<?php
declare(strict_types=1);
require_once __DIR__ . '/boot_session.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
@session_destroy();

/* повернемо на головну (index.php у цій же папці) */
header('Location: ./login');
exit;
