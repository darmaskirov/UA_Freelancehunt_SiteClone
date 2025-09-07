<?php
// /dfbiu/logout.php
declare(strict_types=1);

/* Уніфікована сесія для всього /dfbiu */
$cookiePath = '/dfbiu';
session_name('dfbiu_sess');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => $cookiePath,
  'domain'   => '',
  'secure'   => false,
  'httponly' => true,
  'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) session_start();

/* Чистимо сесію + куку */
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  // важливо: той самий path, що вище
  setcookie(session_name(), '', time() - 42000, $cookiePath, $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

/* Визначаємо: AJAX чи ні */
$isAjax = false;
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest') {
  $isAjax = true;
}
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if (stripos($accept, 'application/json') !== false) {
  $isAjax = true;
}

/* Відповідь */
if ($isAjax || $_SERVER['REQUEST_METHOD']==='POST') {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(200);
  echo json_encode(['ok' => true]);
  exit;
}

header('Location: /dfbiu/');
exit;
