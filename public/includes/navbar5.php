<?php
// /public/includes/navbar.php
// GET  -> HTML навбар (гость/юзер)
// POST -> JSON: логін (username/email + пароль)
//
// PHP 8+, MySQLi. Сесії включено.
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

/* ========= НАЛАШТУВАННЯ ========= */
const DEBUG_MODE = true; // у продакшні вимкни
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'dfbiu_clone';

// перелік можливих колонок з хешем пароля — візьмемо першу непорожню
$PASSWORD_COLUMNS = ['password_hash', 'password', 'pass'];

// КУДИ шле форма із навбару:
$SELF_URL = '/dfbiu/public/includes/navbar.php';
/* ================================= */

/* ---- БД ---- */
function db(): mysqli {
  static $conn = null;
  global $DB_HOST,$DB_USER,$DB_PASS,$DB_NAME;
  if ($conn instanceof mysqli) return $conn;
  $conn = @new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
  if ($conn->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'DB connection failed','debug'=>$conn->connect_error]);
    exit;
  }
  $conn->set_charset('utf8mb4');
  return $conn;
}

/* ---- МОДЕЛЬ ---- */
function find_user_by_login(mysqli $conn, string $login): ?array {
  $sql = "SELECT * FROM users WHERE username=? OR email=? LIMIT 1";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return null;
  $stmt->bind_param('ss', $login, $login);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return $row ?: null;
}

function extract_password_hash(array $user): array {
  // повертає [hash, column] або ['', null], якщо нічого
  global $PASSWORD_COLUMNS;
  foreach ($PASSWORD_COLUMNS as $col) {
    if (array_key_exists($col, $user) && !empty($user[$col])) {
      return [(string)$user[$col], $col];
    }
  }
  return ['', null];
}

/* ---- СЕСІЯ ---- */
function current_user(): ?array { return $_SESSION['user'] ?? null; }

function set_session_user(array $user): void {
  $_SESSION['user'] = [
    'id'       => (int)($user['id'] ?? 0),
    'username' => (string)($user['username'] ?? ''),
    'email'    => (string)($user['email'] ?? ''),
    'balance'  => (float)($user['balance'] ?? 0),
    'is_admin' => (int)($user['is_admin'] ?? 0),
  ];
}

/* ---- AJAX ЛОГІН (POST) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');

  // приймаємо або form-data, або JSON
  $login = trim($_POST['login'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');

  if ($login === '' || $pass === '') {
    // спробуємо JSON
    $raw = file_get_contents('php://input');
    if ($raw) {
      $json = json_decode($raw, true);
      if (is_array($json)) {
        $login = trim((string)($json['login'] ?? $login));
        $pass  = (string)($json['password'] ?? $pass);
      }
    }
  }

  if ($login === '' || $pass === '') {
    http_response_code(400);
    echo json_encode([
      'ok'=>false,
      'msg'=>'Вкажіть логін і пароль',
      'debug'=> DEBUG_MODE ? ['got'=>['login'=>$login!=='' , 'password'=>$pass!=='']] : null
    ]);
    exit;
  }

  $conn = db();
  $user = find_user_by_login($conn, $login);
  if (!$user) {
    http_response_code(401);
    echo json_encode([
      'ok'=>false,
      'msg'=>'Невірний логін або пароль',
      'debug'=> DEBUG_MODE ? ['reason'=>'user-not-found','login'=>$login] : null
    ]);
    exit;
  }

  [$hash, $col] = extract_password_hash($user);
  if ($hash === '' || !password_verify($pass, $hash)) {
    http_response_code(401);
    echo json_encode([
      'ok'=>false,
      'msg'=>'Невірний логін або пароль',
      'debug'=> DEBUG_MODE ? [
        'reason'=>'password-mismatch',
        'hash_col'=>$col,
        'hash_len'=>strlen($hash),
        'hash_prefix'=>substr($hash,0,7)
      ] : null
    ]);
    exit;
  }

  set_session_user($user);

  echo json_encode([
    'ok'=>true,
    'user'=>[
      'id'=>(int)$user['id'],
      'username'=>$user['username'],
      'email'=>$user['email'],
      'balance'=>(float)($user['balance'] ?? 0),
      'is_admin'=>(int)($user['is_admin'] ?? 0),
    ],
    'debug'=> DEBUG_MODE ? ['db'=>$DB_NAME, 'hash_col'=>$col] : null
  ]);
  exit;
}

/* ---- ІНАКШЕ: GET -> HTML НАВБАР ---- */
$u = current_user();
?>
<!-- ===== NAVBAR (HTML + CSS + JS) ===== -->
<style>
  .header{position:sticky;top:0;z-index:1000;background:#e6f1fb;border-bottom:1px solid #fff}
  .wrap{width:min(1240px,96vw);margin:0 auto}
  .header-inner{height:60px;display:flex;align-items:center;gap:16px}
  .logo img{width:38px;height:24px;object-fit:contain}
  .nav{display:flex;gap:24px;align-items:center;list-style:none;margin:0;padding:0}
  .nav a{display:block;padding:8px 10px;border-radius:10px;text-decoration:none;color:#2a3b4f}
  .nav a:hover{background:#f3f7ff}
  .spacer{flex:1}
  .right{margin-left:auto;display:flex;align-items:center;gap:12px}

  .login-form{display:flex;gap:8px;align-items:center}
  .login-form input{height:36px;padding:0 10px;border:1px solid #dbe8f7;border-radius:10px;min-width:160px;background:#fff}
  .login-form button{height:36px;padding:0 14px;border:0;border-radius:12px;background:#2e90ff;color:#fff;cursor:pointer}
  .login-form button:hover{filter:brightness(1.05)}

  .acc-wrap{position:relative}
  .user-chip{display:flex;gap:8px;align-items:center;height:38px;background:#fff;border:1px solid #dbe8f7;border-radius:999px;padding:0 12px;cursor:pointer}
  .avatar{width:22px;height:22px;border-radius:50%;background:linear-gradient(180deg,#eaeaff,#d0d8ff)}
  .user-name{font-weight:600}
  .user-balance{font-weight:600;opacity:.8}
  .acc-menu{position:absolute;right:0;top:52px;width:280px;background:#fff;border:1px solid #e6edf8;border-radius:16px;box-shadow:0 12px 28px rgba(32,74,128,.10);display:none;overflow:hidden}
  .acc-menu.open{display:block}
  .acc-item{display:block;padding:12px 14px;text-decoration:none;color:#2a3b4f}
  .acc-item:hover{background:#f3f7ff;width:100%}

  .acc-menu{
  position:absolute;right:0;top:52px;width:280px;background:#fff;
  border:1px solid #e6edf8;border-radius:16px;box-shadow:0 12px 28px rgba(32,74,128,.10);
  display:none;overflow:hidden
}
.acc-menu.open{display:block}
.acc-item{display:block;padding:12px 14px;text-decoration:none;color:#2a3b4f}
.acc-item:hover,.acc-item:focus{background:#f3f7ff;outline:none}
.acc-sep{border:none;border-top:1px solid #eef3fb;margin:0}
.acc-item.danger{color:#b42318}
.acc-item.danger:hover{background:#fff1f0}

</style>

<header class="header">
  <div class="wrap">
    <div class="header-inner">
      <a class="logo" href="/dfbiu/"><img src="/dfbiu/public/assets/logo.svg" alt="logo"></a>

<ul class="nav">
      <li class="has-dropdown"><a href="/dfbiu/" data-code="home">首页</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">AG视讯</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">EBET</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">AG视讯</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">AG视讯</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">AG视讯</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div></li>

      <li class="has-dropdown">
        <a href="/dfbiu/category/live" data-code="video">视讯</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">AG视讯</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">EBET</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">AG视讯</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">AG视讯</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">AG视讯</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div>
      </li>


      <li class="has-dropdown">
        <a href="/dfbiu/category/game" data-code="slots">电子</a>
        <div class="dropdown-scroll"><div class="scroll-container"><div class="card"><img src="img/dg.png"/><div class="title">MG</div><button class="play">进入游戏</button></div></div></div>
      </li>

      <li class="has-dropdown">
        <a href="/dfbiu/category/fishing" data-code="fish">捕鱼</a>
        <div class="dropdown-scroll"><div class="scroll-container"><div class="card"><img src="img/dg.png"/><div class="title">FG</div><button class="play">进入游戏</button></div></div></div>
      </li>

      <li class="has-dropdown">
        <a href="/dfbiu/category/lottery" data-code="lottery">彩票</a>
        <div class="dropdown-scroll"><div class="scroll-container"><div class="card"><img src="img/dg.png"/><div class="title">PK彩票</div><button class="play">进入游戏</button></div></div></div>
      </li>

      <li class="has-dropdown">
        <a href="/dfbiu/category/sport" data-code="sports">体育</a>
        <div class="dropdown-scroll"><div class="scroll-container"><div class="card"><img src="img/dg.png"/><div class="title">IM体育</div><button class="play">进入游戏</button></div></div></div>
      </li>

      <li class="has-dropdown">
        <a href="/dfbiu/category/poker" data-code="board">棋牌</a>
        <div class="dropdown-scroll"><div class="scroll-container"><div class="card"><img src="img/dg.png"/><div class="title">KY棋牌</div><button class="play">进入游戏</button></div></div></div>
      </li>

      <li class="has-dropdown">
        <a href="/dfbiu/category/esports" data-code="esports">电竞</a>
        <div class="dropdown-scroll"><div class="scroll-container"><div class="card"><img src="img/dg.png"/><div class="title">AVIA</div><button class="play">进入游戏</button></div></div></div>
      </li>
    </ul>


      <div class="spacer"></div>

      <div class="right">
        <?php if (!$u): ?>
          <!-- Гість: логін форма -->
          <form id="navLoginForm" class="login-form" method="post" action="<?=htmlspecialchars($SELF_URL)?>">
            <input type="text"     name="login"    placeholder="Логін або Email" autocomplete="username" required>
            <input type="password" name="password" placeholder="Пароль"          autocomplete="current-password" required>
            <button type="submit">Увійти</button>
          </form>
        <?php else: ?>
          <!-- Авторизований: дропдаун -->
        <div class="acc-wrap">
          <button class="user-chip" id="userChip" aria-haspopup="menu" aria-expanded="false">
            <div class="avatar"></div>
            <span class="user-name"><?=htmlspecialchars($u['username'])?></span>
            <span class="user-balance">$<?=number_format((float)$u['balance'], 4)?></span>
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" style="opacity:.6;margin-left:2px">
              <path fill="currentColor" d="M7 10l5 5 5-5z"/>
            </svg>
          </button>

          <div class="acc-menu" id="accMenu" role="menu">
            <a class="acc-item" href="/dfbiu/membership/user-info"  role="menuitem">用户信息</a>
            <a class="acc-item" href="/dfbiu/membership/privileges" role="menuitem">VIP特权</a>
            <hr class="acc-sep">
            <a class="acc-item danger" href="/dfbiu/public/includes/logout.php" id="logoutBtn" role="menuitem">Вийти</a>

          </div>

          
        </div>

        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<script>
(function initNavbar(){
  const form = document.getElementById('navLoginForm');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const url = form.getAttribute('action') || '<?=htmlspecialchars($SELF_URL)?>';
      try {
        const res = await fetch(url, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch (err) {
          console.error('[navbar] not JSON, raw:', text);
          alert('Помилка: сервер повернув не-JSON'); return;
        }
        if (!res.ok || !data.ok) {
          console.warn('[navbar] login fail:', data);
          alert(data?.msg || ('Помилка '+res.status));
          return;
        }
        // успіх -> перезавантажити, щоб побачити авторизований стан
        location.reload();
      } catch (err) {
        console.error('[navbar] network error:', err);
        alert('Мережева помилка');
      }
    });
  }

  const chip = document.getElementById('userChip');
  const menu = document.getElementById('accMenu');
  if (chip && menu) {
    chip.addEventListener('click', () => menu.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target) && !chip.contains(e.target)) menu.classList.remove('open');
    });
  }

    const chip = document.getElementById('userChip');
  const menu = document.getElementById('accMenu');

  function openMenu(){
    menu.classList.add('open');
    chip.setAttribute('aria-expanded','true');
    // фокус на перший пункт
    const first = menu.querySelector('.acc-item');
    if (first) first.focus();
  }
  function closeMenu(){
    menu.classList.remove('open');
    chip.setAttribute('aria-expanded','false');
  }
  function toggleMenu(){
    if (menu.classList.contains('open')) closeMenu(); else openMenu();
  }

  if (chip && menu){
    chip.addEventListener('click', (e)=>{ e.stopPropagation(); toggleMenu(); });
    document.addEventListener('click', (e)=>{
      if (!menu.contains(e.target) && !chip.contains(e.target)) closeMenu();
    });
    document.addEventListener('keydown', (e)=>{
      if (e.key === 'Escape') closeMenu();
    });
    // клавіатура: закрити при Tab виході з меню
    menu.addEventListener('keydown', (e)=>{
      if (e.key === 'Tab') {
        // якщо фокус іде поза меню — закриваємо
        setTimeout(()=>{
          if (!menu.contains(document.activeElement)) closeMenu();
        },0);
      }
    });
  }

    const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      try {
        const res = await fetch(logoutBtn.href, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.ok) {
          location.reload(); // після логауту оновлюємо навбар
        } else {
          alert('Помилка при виході');
        }
      } catch (err) {
        console.error('Logout error:', err);
        alert('Мережева помилка при виході');
      }
    });
  }
})();
</script>
