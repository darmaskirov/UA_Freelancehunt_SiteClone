<?php
// navbar.php — рендер навбару + AJAX-логін (PDO, sessions)
if (session_status() === PHP_SESSION_NONE) session_start();

/** DB */
$host = "127.0.0.1";
$db   = "dfbiu_clone";
$user = "root";
$pass = "";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB connection error.";
  exit;
}

/** current_user */
function current_user(PDO $pdo): ?array {
  if (empty($_SESSION['user_id'])) return null;
  $id = (int)$_SESSION['user_id'];
  $stmt = $pdo->prepare("SELECT id, username, email, balance, is_admin FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$id]);
  $u = $stmt->fetch();
  return $u ?: null;
}

/** AJAX-логін: якщо прийшов POST з login/password — обробляємо та виходимо JSON'ом */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login    = trim($_POST['login'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if ($login === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'Вкажіть логін і пароль']);
    exit;
  }

  // 1) шукаємо користувача
  $stmt = $pdo->prepare("
    SELECT id, username, email, password AS password_hash, balance, is_admin
    FROM users
    WHERE username = ? OR email = ?
    LIMIT 1
  ");
  $stmt->execute([$login, $login]);
  $user = $stmt->fetch();

  if (!$user) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'Невірний логін або пароль']);
    exit;
  }

  // 2) перевірка пароля (hash або «старий» plain)
  $passOk = password_verify($password, $user['password_hash']) || $password === $user['password_hash'];
  if (!$passOk) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'Невірний логін або пароль']);
    exit;
  }

  // 3) успіх — ставимо сесію (мінімально необхідне)
  $_SESSION['user_id']  = (int)$user['id'];
  $_SESSION['username'] = (string)$user['username'];

  echo json_encode(['ok'=>true]);
  exit;
}

// ---- Далі — HTML навбару (для include) ----
$u = current_user($pdo);
?>
<link rel="stylesheet" href="navbar.css?v=2">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/png" href="/dfbiu/public/assets/img/vue.png" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vite App</title>
</head>
<header class="header" id="navbar">


    <!-- <svg class='icon-small' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M224 704h576V318.336L552.512 115.84a64 64 0 0 0-81.024 0L224 318.336zm0 64v128h576V768zM593.024 66.304l259.2 212.096A32 32 0 0 1 864 303.168V928a32 32 0 0 1-32 32H192a32 32 0 0 1-32-32V303.168a32 32 0 0 1 11.712-24.768l259.2-212.096a128 128 0 0 1 162.112 0"></path><path fill="currentColor" d="M512 448a64 64 0 1 0 0-128 64 64 0 0 0 0 128m0 64a128 128 0 1 1 0-256 128 128 0 0 1 0 256"></path></svg>
    <svg class='icon-small' xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 1024 1024"><path fill="currentColor" d="M918.4 201.6c-6.4-6.4-12.8-9.6-22.4-9.6H768V96c0-9.6-3.2-16-9.6-22.4C752 67.2 745.6 64 736 64H288c-9.6 0-16 3.2-22.4 9.6C259.2 80 256 86.4 256 96v96H128c-9.6 0-16 3.2-22.4 9.6-6.4 6.4-9.6 16-9.6 22.4 3.2 108.8 25.6 185.6 64 224 34.4 34.4 77.56 55.65 127.65 61.99 10.91 20.44 24.78 39.25 41.95 56.41 40.86 40.86 91 65.47 150.4 71.9V768h-96c-9.6 0-16 3.2-22.4 9.6-6.4 6.4-9.6 12.8-9.6 22.4s3.2 16 9.6 22.4c6.4 6.4 12.8 9.6 22.4 9.6h256c9.6 0 16-3.2 22.4-9.6 6.4-6.4 9.6-12.8 9.6-22.4s-3.2-16-9.6-22.4c-6.4-6.4-12.8-9.6-22.4-9.6h-96V637.26c59.4-7.71 109.54-30.01 150.4-70.86 17.2-17.2 31.51-36.06 42.81-56.55 48.93-6.51 90.02-27.7 126.79-61.85 38.4-38.4 60.8-112 64-224 0-6.4-3.2-16-9.6-22.4zM256 438.4c-19.2-6.4-35.2-19.2-51.2-35.2-22.4-22.4-35.2-70.4-41.6-147.2H256zm390.4 80C608 553.6 566.4 576 512 576s-99.2-19.2-134.4-57.6C342.4 480 320 438.4 320 384V128h384v256c0 54.4-19.2 99.2-57.6 134.4m172.8-115.2c-16 16-32 25.6-51.2 35.2V256h92.8c-6.4 76.8-19.2 124.8-41.6 147.2zM768 896H256c-9.6 0-16 3.2-22.4 9.6-6.4 6.4-9.6 12.8-9.6 22.4s3.2 16 9.6 22.4c6.4 6.4 12.8 9.6 22.4 9.6h512c9.6 0 16-3.2 22.4-9.6 6.4-6.4 9.6-12.8 9.6-22.4s-3.2-16-9.6-22.4c-6.4-6.4-12.8-9.6-22.4-9.6"></path></svg>
    <svg class='icon-small' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M256 128a64 64 0 0 0-64 64v640a64 64 0 0 0 64 64h512a64 64 0 0 0 64-64V192a64 64 0 0 0-64-64zm0-64h512a128 128 0 0 1 128 128v640a128 128 0 0 1-128 128H256a128 128 0 0 1-128-128V192A128 128 0 0 1 256 64m128 128h256a32 32 0 1 1 0 64H384a32 32 0 0 1 0-64m128 640a64 64 0 1 1 0-128 64 64 0 0 1 0 128"></path></svg>
    <svg class='icon-small' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M256 128a64 64 0 0 0-64 64v640a64 64 0 0 0 64 64h512a64 64 0 0 0 64-64V192a64 64 0 0 0-64-64zm0-64h512a128 128 0 0 1 128 128v640a128 128 0 0 1-128 128H256a128 128 0 0 1-128-128V192A128 128 0 0 1 256 64m128 128h256a32 32 0 1 1 0 64H384a32 32 0 0 1 0-64m128 640a64 64 0 1 1 0-128 64 64 0 0 1 0 128"></path></svg>
    <svg class='icon-small' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M256 128a64 64 0 0 0-64 64v640a64 64 0 0 0 64 64h512a64 64 0 0 0 64-64V192a64 64 0 0 0-64-64zm0-64h512a128 128 0 0 1 128 128v640a128 128 0 0 1-128 128H256a128 128 0 0 1-128-128V192A128 128 0 0 1 256 64m128 128h256a32 32 0 1 1 0 64H384a32 32 0 0 1 0-64m128 640a64 64 0 1 1 0-128 64 64 0 0 1 0 128"></path></svg>
    <style>
        svg.icon-small {
            width: 20px;
            height: 20px;
            }
    </style> -->






  </div>

  <div class="lang"><span id="langVal">zh-CN</span></div>
</div>

<style>

</style>

  </div>
</header>
<script>
script>
    (function initNavbar(){
      const root = document.getElementById('navbar');

      // ЛОГІН: сабміт + fetch -> підмінити navbar HTML
      const form = root.querySelector('#navLoginForm');
      if (form) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const fd = new FormData(form);
          try {
            const r = await fetch('/public/includes/auth/login.php', {
              method: 'POST',
              body: fd,
              credentials: 'same-origin',
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await r.json().catch(() => ({ ok:false, msg:'Помилка' }));
            if (!data.ok) {
              alert(data.msg || 'Невірний логін або пароль');
              return;
            }
            await refreshNavbar();
          } catch (err) {
            console.error(err);
            alert('Мережева помилка');
          }
        });
      }

      async function refreshNavbar() {
        const r = await fetch('/dfbiu/public/includes/navbar.php', {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const html = await r.text();
        // підміняємо увесь <nav id="navbar">...</nav>
        root.outerHTML = html;
        // перевішуємо обробники на новий елемент
        requestAnimationFrame(initNavbar);
      }
    })();
</script>

<script src='navbar.js'></script>