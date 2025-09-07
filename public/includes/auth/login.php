<?php
// /public/includes/auth/login.php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
  exit;
}

require_once __DIR__ . '/../../../config/config.php'; // створює $conn (mysqli)

$login    = trim($_POST['login'] ?? '');<?php
// /public/includes/auth/login.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Method not allowed']); exit;
}

/* БД */
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'dfbiu_clone';
$PASSWORD_COLUMN = 'password_hash';

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'DB connection failed','debug'=>$conn->connect_error]); exit;
}
$conn->set_charset('utf8mb4');

$login = trim($_POST['login'] ?? '');
$pass  = (string)($_POST['password'] ?? '');
if ($login === '' || $pass === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Вкажіть логін і пароль']); exit;
}

$sql = "SELECT id, username, email, balance, is_admin, {$PASSWORD_COLUMN} AS pass FROM users
        WHERE username=? OR email=? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss',$login,$login);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user || !password_verify($pass, (string)$user['pass'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'Невірний логін або пароль']); exit;
}

$_SESSION['user'] = [
  'id'       => (int)$user['id'],
  'username' => $user['username'],
  'email'    => $user['email'],
  'balance'  => (float)$user['balance'],
  'is_admin' => (int)$user['is_admin'],
];

echo json_encode(['ok'=>true,'user'=>$_SESSION['user']]);

$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
  echo json_encode(['ok' => false, 'msg' => 'Вкажіть логін і пароль']);
  exit;
}

$stmt = $conn->prepare(
  "SELECT id, username, email, password AS password_hash, balance, is_admin 
   FROM users 
   WHERE username = ? OR email = ? 
   LIMIT 1"
);
$stmt->bind_param('ss', $login, $login);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
  echo json_encode(['ok' => false, 'msg' => 'Невірний логін або пароль']);
  exit;
}

/* Підтримуємо і захешовані паролі, і «старі» у відкритому вигляді */
$passOk = password_verify($password, $user['password_hash']) || $password === $user['password_hash'];
if (!$passOk) {
  echo json_encode(['ok' => false, 'msg' => 'Невірний логін або пароль']);
  exit;
}

/* Сетимо сесію мінімально необхідними даними */
$_SESSION['user'] = [
  'id'       => (int)$user['id'],
  'username' => $user['username'],
  'email'    => $user['email'],
  'balance'  => (float)$user['balance'],
  'is_admin' => (int)$user['is_admin'] ?? 0,
];

echo json_encode([
  'ok'   => true,
  'user' => [
    'id'       => (int)$user['id'],
    'username' => $user['username'],
    'email'    => $user['email'],
    'balance'  => (float)$user['balance'],
  ],
]);
