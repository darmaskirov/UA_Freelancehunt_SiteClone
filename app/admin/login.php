<?php
declare(strict_types=1);
require_once __DIR__ . '/boot_session.php';

/**
 * ПІДКЛЮЧЕННЯ ДО БД:
 * Варіант А) якщо у тебе є окремий config.php у ТІЙ ЖЕ папці — розкоментуй:
 *   require_once __DIR__ . '/config.php'; // повинен створити $conn = new PDO(...)
 *
 * Варіант Б) тимчасовий inline-конект нижче (заміни креденшали під себе)
 */
if (!isset($conn)) {
  $DB_HOST = 'srv1969.hstgr.io';
  $DB_NAME = 'u140095755_questhub';
  $DB_USER = 'u140095755_darmas';
  $DB_PASS = '@Corp9898';
  try {
    $conn = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    http_response_code(500);
    die('DB connection error.');
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  header('Content-Type: application/json; charset=utf-8');

  $login    = trim($_POST['login'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if ($login === '' || $password === '') {
    echo json_encode(['ok'=>false,'msg'=>'Вкажіть логін і пароль']); exit;
  }

  try {
    $st = $conn->prepare("
      SELECT id, username, email, role, status, password_hash
      FROM users
      WHERE username = ? OR email = ?
      LIMIT 1
    ");
    $st->execute([$login, $login]);
    $u = $st->fetch();

    if (!$u || !password_verify($password, $u['password_hash'])) {
      echo json_encode(['ok'=>false,'msg'=>'Невірний логін або пароль']); exit;
    }
    if (($u['status'] ?? 'active') !== 'active') {
      echo json_encode(['ok'=>false,'msg'=>'Аккаунт не активний']); exit;
    }

    $_SESSION['user'] = [
      'id'       => (int)$u['id'],
      'username' => $u['username'],
      'email'    => $u['email'],
      'role'     => $u['role'] ?: 'user',
      'status'   => $u['status'] ?: 'active',
      'login_at' => time(),
    ];
    safe_regenerate();

    $target = '/admin';
    
    
    // Если логин дергается через AJAX — вернём JSON с redirect (на будущее, вдруг используешь fetch)
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => true, 'redirect' => $target], JSON_UNESCAPED_UNICODE);
      exit;
    }
    
    // Обычный (не-AJAX) сценарий — редирект
    if (!headers_sent()) {
      header('Location: ' . $target, true, 302);
      exit;
    }
    
    // Фолбэк, если заголовки уже были отправлены (на всякий случай)
    echo '<meta http-equiv="refresh" content="0;url='.$target.'">';
    echo '<script>location.replace("'.$target.'")</script>';
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Server error','debug'=>null]); exit;
  }
}

/* GET → мінімальна форма (працює у цій самій папці) */
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <title>Логін</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, sans-serif; background:#f6f8fa; display:flex; min-height:100vh; align-items:center; justify-content:center; }
    form { background:#fff; padding:20px; border-radius:12px; width:320px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
    h1 { margin:0 0 10px; font-size:20px; }
    label { display:block; margin-top:10px; font-size:14px; }
    input { width:100%; margin-top:6px; padding:10px; border:1px solid #d0d7de; border-radius:8px; }
    button { margin-top:14px; width:100%; padding:10px; border:0; border-radius:8px; background:#2e90ff; color:#fff; font-weight:600; cursor:pointer; }
    button:hover { filter:brightness(.95); }
  </style>
</head>
<body>
  <form method="post">
    <h1>Вхід</h1>
    <label>Логін або email
      <input type="text" name="login" required>
    </label>
    <label>Пароль
      <input type="password" name="password" required>
    </label>
    <button type="submit">Увійти</button>
  </form>
</body>
</html>
