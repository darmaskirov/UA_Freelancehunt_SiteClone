<?php
// /public/login.php
require_once __DIR__ . '/../app/auth.php';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = trim($_POST['login'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($login === '' || $password === '') {
    if ($isAjax) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => false, 'msg' => 'Вкажіть логін і пароль']);
      exit;
    }
    $error = 'Вкажіть логін і пароль';
  } else {
    $user = find_user_by_login($conn, $login);
    if ($user && password_verify($password, $user['password_hash'])) {
      login_user($user);

      if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
      }
      // redirect звичайним способом
      $redirect = $_GET['redirect'] ?? '/dfbiu/';
      header('Location: ' . $redirect);
      exit;
    } else {
      if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Невірний логін або пароль']);
        exit;
      }
      $error = 'Невірний логін або пароль';
    }
  }
}

?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <title>Вхід</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:Inter,system-ui,sans-serif;background:#0b1220;color:#eaf0ff;margin:0;display:grid;place-items:center;min-height:100vh}
    .card{width:min(420px,92vw);background:#0e1726;border:1px solid #1a2842;border-radius:14px;box-shadow:0 12px 28px rgba(8,15,30,.35);padding:22px}
    h1{margin:.25rem 0 1rem;font-size:20px}
    .row{display:grid;gap:8px;margin-bottom:12px}
    label{font-size:12px;color:#97a4c0}
    input{height:40px;padding:0 12px;border:1px solid #223150;background:#0b1324;color:#eaf0ff;border-radius:10px;outline:none}
    button{height:42px;border:1px solid #2e90ff;background:#2e90ff;color:#fff;border-radius:12px;font-weight:600;cursor:pointer}
    .error{background:#391818;border:1px solid #5d2626;color:#ffbdbd;padding:10px;border-radius:10px;margin-bottom:12px}
    .hint{font-size:12px;color:#8fa1c4;margin-top:8px}
  </style>
  
</head>
<body>
  <div class="card">
    <h1>Вхід</h1>
    <?php if (!empty($error)): ?>
      <div class="error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <form method="post" action="/public/login.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>">
      <div class="row">
        <label for="login">Логін або Email</label>
        <input id="login" name="login" type="text" autocomplete="username" required>
      </div>
      <div class="row">
        <label for="password">Пароль</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button type="submit">Увійти</button>
    </form>
    <div class="hint">Після успіху вас перекине назад на попередню сторінку (або на головну).</div>
  </div>
</body>
</html>
