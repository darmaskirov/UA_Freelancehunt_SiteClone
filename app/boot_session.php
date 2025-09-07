<?php
declare(strict_types=1);

/** ПІДКЛЮЧАЄМО КОНФІГ БД */
$ROOT = dirname(__DIR__); // .../dfbiu
require_once $ROOT . '/config/db.php';   // <-- тут лежать DB_HOST/DB_NAME/DB_USER/DB_PASS

// Якщо з якихось причин константи не задані — підкидаємо дефолти (щоб не падало)
if (!defined('DB_HOST')) define('DB_HOST', 'srv1969.hstgr.io');
if (!defined('DB_NAME')) define('DB_NAME', 'u140095755_questhub');
if (!defined('DB_USER')) define('DB_USER', 'u140095755_darmas');
if (!defined('DB_PASS')) define('DB_PASS', '@Corp9898');

/** СЕСІЯ */
$cookiePath = '/';  // підпапка проекту
session_name('DFBIUSESSID');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => $cookiePath,
  'secure' => false,
  'httponly' => true,
  'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) session_start();

/** DB (PDO) — єдина реалізація у проекті */
if (!function_exists('db')) {
  function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO(
      'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
      DB_USER, DB_PASS,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
    return $pdo;
  }
}

/** Поточний користувач */
if (!function_exists('current_user')) {
  function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $pdo = db();
    $stmt = $pdo->prepare("
      SELECT u.id, u.username, u.email, u.role, u.status, u.currency AS user_currency,
             b.amount AS balance_amount, b.currency AS balance_currency,
             p.full_name, p.phone, p.country, p.avatar_url
      FROM users u
      LEFT JOIN balances b ON b.user_id = u.id
      LEFT JOIN profiles p ON p.user_id = u.id
      WHERE u.id = ?
      LIMIT 1
    ");
    $stmt->execute([$_SESSION['uid']]);
    $row = $stmt->fetch();
    if (!$row) return null;
    if (!$row['balance_currency']) $row['balance_currency'] = $row['user_currency'] ?: 'USD';
    if ($row['balance_amount'] === null) $row['balance_amount'] = 0;
    return $row;
  }
}

/** Редірект */
if (!function_exists('redirect')) {
  function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
  }
}
