<?php
/** app/boot_session.php
 * ПІДКЛЮЧАЙ ПЕРШИМ у ВСІХ файлах: navbar.php, login.php, logout.php, profile.php, admin.php і т.д.
 * Дає: єдине ім’я сесії, єдиний cookie path, захист від фіксації, guard-и require_login/require_admin.
 */
declare(strict_types=1);

/* 0) Глушимо випадковий вивід до start() */
if (!headers_sent()) { @ob_start(); }

/* 1) Єдине ім’я сесії та cookie-параметри */
session_name('dfbiu_sess'); // ОДНЕ ім’я на весь проєкт

$secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$cookieParams = [
  'lifetime' => 0,
  // ОБОВ’ЯЗКОВО: один і той самий path, щоб сесія «бачилась» і в /public, і в /app, і в /admin
  'path'     => '/',
  'domain'   => '',          // залиш порожнім (локально на localhost/127.0.0.1/::1)
  'secure'   => $secure,     // true на проді з HTTPS
  'httponly' => true,
  'samesite' => 'Lax',       // або 'Strict' якщо не використовуєш кросс-сабміти
];
session_set_cookie_params($cookieParams);

/* 2) Стартуємо сесію один раз */
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/* 3) Проста anti-fixation зв’язка із UA/IP (м’яко, без 100% блокувань у локалці) */
if (!isset($_SESSION['_fingerprint'])) {
  $_SESSION['_fingerprint'] = [
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'na',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
  ];
} else {
  $uaOk = ($_SESSION['_fingerprint']['ua'] ?? '') === ($_SERVER['HTTP_USER_AGENT'] ?? '');
  if (!$uaOk) {
    // новий браузер → скинемо сесію
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
    @session_start();
  }
}

/* 4) Хелпери */
function session_user(): ?array {
  return $_SESSION['user'] ?? null; // ['id'=>..,'username'=>..,'role'=>'user|admin','status'=>'active'...]
}
function is_logged_in(): bool {
  return isset($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] > 0;
}
function is_admin(): bool {
  return (session_user()['role'] ?? 'user') === 'admin';
}
function require_login(): void {
  if (!is_logged_in()) {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      header('Content-Type: application/json', true, 401);
      echo json_encode(['ok'=>false,'err'=>'unauthorized']); exit;
    }
    header('Location: /admin/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
  }
}
function require_admin(): void {
  require_login();
  if (!is_admin()) {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      header('Content-Type: application/json', true, 403);
      echo json_encode(['ok'=>false,'err'=>'forbidden']); exit;
    }
    http_response_code(403);
    echo 'Forbidden'; exit;
  }
}

/* 5) Регенеруємо id після логіна (викликається із login.php):
 *   safe_regenerate() — окрема функція, щоб можна було юзати її де треба.
 */
function safe_regenerate(): void {
  if (session_status() === PHP_SESSION_ACTIVE) {
    @session_regenerate_id(true);
  }
}
