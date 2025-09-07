<?php
// /public/includes/navbar.php
// Stable navbar: sessions + mysqli + balance + AJAX login/logout

require_once $_SERVER['DOCUMENT_ROOT'].'/app/boot_session.php';

/* 1) DB connect: очікуємо $conn (mysqli) з config.php; інакше спробуємо локалку */
if (!isset($conn) || !($conn instanceof mysqli)) {
  $cfg = __DIR__ . '/../../config/config.php';
  if (is_file($cfg)) require_once $cfg;                // має створити $conn (mysqli)
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  $conn = @new mysqli('srv1969.hstgr.io', 'u140095755_darmas', '@Corp9898', 'u140095755_questhub');
  if ($conn->connect_errno) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'msg'=>'DB connection ($conn) is not available.']);
    } else {
      echo '<!-- DB connection ($conn) is not available. -->';
    }
    exit;
  }
}
$conn->set_charset('utf8mb4');

/* 2) helpers */
function is_ajax(): bool {
  return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest';
}
function format_money($amount): string {
  return number_format((float)$amount, 4, '.', ''); // стиль $0.0000
}
// function current_user(mysqli $conn): ?array {
//   if (empty($_SESSION['uid'])) return null;
//   $uid = (int)$_SESSION['uid'];
//   $sql = "
//     SELECT 
//       u.id, u.username, u.email, u.role, u.status,
//       COALESCE(NULLIF(b.currency,''), u.currency) AS currency,
//       IFNULL(b.amount, 0.00) AS amount
//     FROM users u
//     LEFT JOIN balances b ON b.user_id = u.id
//     WHERE u.id = ?
//     LIMIT 1
//   ";
//   $stmt = $conn->prepare($sql);
//   $stmt->bind_param('i', $uid);
//   $stmt->execute();
//   $res = $stmt->get_result();
//   $row = $res->fetch_assoc() ?: null;
//   $stmt->close();
//   return $row;
// }
function find_user_by_login(mysqli $conn, string $login): ?array {
  $sql = "SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ss', $login, $login);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc() ?: null;
  $stmt->close();
  return $row;
}
function password_matches(?string $stored, string $input): bool {
  if (!$stored) return false;
  if (str_starts_with($stored, '$2y$')) return password_verify($input, $stored); // bcrypt
  return hash_equals($stored, $input); // сумісність із plaintext
}

/* 3) AJAX login/logout (JSON) */
if ($_SERVER['REQUEST_METHOD']==='POST' && is_ajax()) {
  header('Content-Type: application/json');

  // logout
  if (isset($_POST['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    echo json_encode(['ok'=>true,'msg'=>'Logged out']);
    exit;
  }

  // login
  $login = trim($_POST['login'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  if ($login==='' || $pass==='') {
    echo json_encode(['ok'=>false,'msg'=>'Вкажіть логін і пароль']); exit;
  }
  $u = find_user_by_login($conn, $login);
  if (!$u || !password_matches($u['password_hash'], $pass)) {
    echo json_encode(['ok'=>false,'msg'=>'Невірний логін або пароль']); exit;
  }
  $_SESSION['uid'] = (int)$u['id'];
  $me = current_user($conn);
  echo json_encode([
    'ok'=>true,'msg'=>'Успішний вхід',
    'user'=>[
      'id'=>(int)$me['id'],'username'=>$me['username'],'email'=>$me['email'],
      'currency'=>$me['currency'],'balance'=>format_money($me['amount'])
    ]
  ]);
  exit;
}

/* 4) HTML-вставка для include */
$me = current_user($conn);
?>
        <style>
:root{
  --bg:#e6f1fb;
  --text:#2a3b4f;
  --blue:#2e90ff;
  --border:#dbe8f7;
  --hover:#f3f7ff;
  --shadow:0 12px 28px rgba(32,74,128,.10);
  --header-h:60px;
}

*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,system-ui,"Noto Sans SC",sans-serif;background:#f5f8fd;color:var(--text)}

/* HEADER */
.header{position:sticky;top:0;z-index:1000;background:var(--bg);border-bottom:1px solid #fff}
.wrap{width:min(1240px,96vw);margin:0 auto}
.header-inner{height:var(--header-h);display:flex;align-items:center;gap:16px;position:relative}
.logo img{width:38px;height:24px;object-fit:contain}

/* NAV (left) */
.nav{
  display:flex;align-items:center;list-style:none;
  gap:24px;flex:1 1 auto;min-width:0; /* не даємо переламуватись */
  font-weight:700;color:#334a63
}
.nav > li{position:relative;flex:0 0 auto}
.nav a{padding:10px 2px;text-decoration:none;color:inherit;font-size:15px;transition:.15s;display:inline-block;white-space:nowrap}
.nav a:hover{color:#0a58ff}
.nav a.active{color:#0a58ff;position:relative}
.nav a.active::after{content:"";position:absolute;left:0;right:0;bottom:-6px;height:3px;background:var(--blue);border-radius:3px}

/* THUMB SCROLLER dropdown (compact) */
.has-dropdown{position:relative}
.dropdown-scroll{
  position:absolute;top:100%;left:0;margin-top:8px;
  background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);
  padding:20px 40px;width:1000px;max-width:calc(100vw - 24px);
  opacity:0;transform:translateY(10px);pointer-events:none;transition:all .25s ease;z-index:110
}
.has-dropdown:hover .dropdown-scroll{opacity:1;transform:translateY(0);pointer-events:auto}
.scroll-container{display:flex;gap:20px;overflow:hidden}
.scroll-btn{position:absolute;top:50%;transform:translateY(-50%);width:28px;height:28px;border:none;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.2);cursor:pointer}
.scroll-prev{left:6px}.scroll-next{right:6px}
.card{flex:0 0 auto;width:180px;height:240px;background:#f1f6ff;border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);display:flex;flex-direction:column;align-items:center;justify-content:space-between;padding:12px}
.card img{max-width:100%;height:120px;object-fit:contain}
.card .title{font-weight:700;margin:8px 0}
.card .play{background:var(--blue);border:none;color:#fff;font-weight:700;padding:6px 14px;border-radius:20px;cursor:pointer}
.card .play:hover{background:#1c6fe0}

/* FULL-WIDTH MEGA DROPDOWN */
.mega{position:static} /* li, для якого треба фулвітх */
.dropdown-full{
  position:absolute;
  left:50%;
  top:100%;
  transform:translate(-50%,10px);
  width:100vw;               /* на всю ширину екрана */
  background:#fff;
  border-top:1px solid var(--border);
  box-shadow:var(--shadow);
  padding:40px 0;            /* внутрішній відступ без зсуву контейнера */
  opacity:0; pointer-events:none; transition:all .25s ease; z-index:105;
}
.has-dropdown.mega:hover .dropdown-full{
  opacity:1; transform:translate(-50%,0); pointer-events:auto;
}
/* внутрішній контейнер для вирівнювання по wrap */
.dropdown-full .wrap-in{
  width:min(1240px,96vw); margin:0 auto; padding:0 20px;
}
.grid-menu{
  display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:30px;
}
.grid-item{
  background:#f8fbff;border:1px solid var(--border);border-radius:12px;
  box-shadow:var(--shadow);padding:16px;text-align:center;transition:transform .2s;
}
.grid-item:hover{transform:translateY(-4px)}
.grid-item img{width:100%;max-width:120px;height:80px;object-fit:contain;margin:0 auto 12px}
.grid-item .title{font-weight:700;color:#334a63;font-size:14px}

/* RIGHT SIDE (account / inputs / icons) */
.right{
  display:flex;align-items:center;gap:12px;margin-left:auto; /* прижимає вправо */
}
.nav-icons{display:flex;gap:12px;align-items:center;margin-right:12px}
.nav-icons .icon svg{width:32px;height:32px;color:#333;cursor:pointer;transition:color .3s}
.nav-icons .icon svg:hover{color:#007bff}

.account{position:relative}
.user-chip{
  display:flex;align-items:center;gap:10px;background:#fff;padding:6px 10px;border-radius:999px;
  border:1px solid var(--border);box-shadow:var(--shadow);font-weight:700;cursor:pointer;white-space:nowrap
}
.avatar{width:28px;height:28px;border-radius:50%;background:#d9e7ff url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=100&auto=format&fit=crop') center/cover}
.user-balance{color:#0d6efd;font-weight:800}

/* ACC MENU — ховер по всій ширині елемента */
.acc-menu{
  position:absolute;right:0;top:calc(100% + 8px);min-width:220px;background:#fff;
  border:1px solid var(--border);border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.12);
  padding:6px 0;opacity:0;transform:translateY(10px);pointer-events:none;transition:all .25s ease;z-index:120
}
.acc-menu.open{opacity:1;transform:translateY(0);pointer-events:auto}
.acc-item{
  display:block;              /* важливо для 100% ширини */
  width:100%;                 /* ховер на всю ширину */
  padding:10px 14px;
  font-size:14px;color:#334a63;text-decoration:none;cursor:pointer;transition:.2s
}
.acc-item:hover{background:var(--hover);color:#0a58ff}
.acc-item.logout{border-top:1px solid #e9eef5;margin-top:4px;padding:12px 14px;font-weight:700;color:#cf3e3e}
.acc-item.logout:hover{background:#fff2f2;color:#a82121}

/* Гостьова форма (короткі інпути) */
.guest-auth{display:flex;align-items:center;gap:8px;flex-wrap:nowrap}
.guest-auth input{
  height:32px;width:60px; /* короткі */
  border:1px solid var(--border);border-radius:20px;padding:0 10px;font-size:14px;outline:none;
  background:#f5f8fd;color:#2a3b4f
}
.guest-auth input:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 4px rgba(46,144,255,.4)}
.guest-auth .btn{
  padding:0 14px;height:32px;line-height:32px;border-radius:20px;border:1px solid var(--border);
  text-decoration:none;color:#2a3b4f;font-size:14px;background:#fff;white-space:nowrap
}
.guest-auth .btn.login{background:var(--blue);color:#fff;border-color:var(--blue)}
.guest-auth .btn.login:hover{filter:brightness(1.05)}

/* ВАРІАНТ №2 — форма з більшими «пігулками» (якщо потрібно) */
#navLoginForm.nav-login-form{display:flex;align-items:center;gap:10px;flex-wrap:nowrap}
#navLoginForm .nav-input.short{
  height:36px;min-width:160px;padding:0 12px;border:1px solid var(--border);border-radius:999px;background:#fff;
  color:var(--text);font-size:14px;line-height:36px;outline:none;box-shadow:0 2px 8px rgba(32,74,128,.08);
  transition:border-color .2s, box-shadow .2s, background .2s
}
#navLoginForm .nav-input.short::placeholder{color:#96a3b6}
#navLoginForm .nav-input.short:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(46,144,255,.18);background:#fff}
#navLoginForm .nav-btn{
  height:36px;padding:0 16px;border:0;border-radius:999px;background:var(--blue);color:#fff;font-weight:600;font-size:14px;
  cursor:pointer;white-space:nowrap;box-shadow:0 6px 16px rgba(46,144,255,.25);transition:transform .06s ease, box-shadow .2s, background .2s
}
#navLoginForm .nav-btn:hover{background:#1f7cf0;box-shadow:0 8px 20px rgba(31,124,240,.28)}
#navLoginForm .nav-btn:active{transform:translateY(1px)}

/* ICON TABS (як на оригіналі) */
.nav-icons-5{display:flex;align-items:center;gap:18px;margin-right:12px}
.nav-icon-item{display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;color:#6b7688;line-height:1;position:relative}
.nav-icon-item .icon-30{width:20px;height:20px}
.nav-icon-item span{margin-top:6px;font-size:14px}
.nav-icon-item::after{content:"";position:absolute;left:50%;transform:translateX(-50%);bottom:-8px;width:28px;height:3px;border-radius:3px;background:transparent}
.nav-icon-item:hover,.nav-icon-item.is-active{color:#14b8a6}
.nav-icon-item.is-active::after{background:#14b8a6}

/* ДРІБНІ ФІКСИ */
.header,.topbar,.site-header{will-change:transform}
@media (max-width: 900px){
  .nav{gap:14px}
  #navLoginForm .nav-input.short{min-width:120px}
  .right{gap:6px}
}

/* Щоб елементи меню у будь-яких випадаючих списках мали повний ховер */
.menu, .dropdown, .dropdown-full, .acc-menu { --item-padding: 10px 14px; }
.menu a, .dropdown a, .dropdown-full a, .acc-menu a,
.menu .item, .dropdown .item, .dropdown-full .item, .acc-menu .item{
  display:block; width:100%; padding:var(--item-padding);
}
/* селект мови як чіп */
.lang-select{
  height:32px; padding:0 30px 0 12px;
  border:1px solid var(--border);
  border-radius:9999px;
  background:#f3f7ff;
  font-size:13px; color:var(--text);
  appearance:none; -webkit-appearance:none; -moz-appearance:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 20 20' fill='%237e8aa6'><path d='M5 7l5 5 5-5'/></svg>");
  background-repeat:no-repeat;
  background-position:right 10px center;
}
.lang-select:focus{
  background:#fff; border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(46,144,255,.15);
}

    </style>
<?php if (!$me): ?>
  <div class="wrap header-inner">
    <div><img src="broken-logo.png" alt="logo"/></div>

      <ul class="nav">
            <li class="has-dropdown"><a href="/" data-code="home">首页</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">CQ9</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">DBDZ</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">FG</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">PP</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">TP</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div></li>

      <li class="has-dropdown">
        <a href="/category/live" data-code="video">视讯</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">BBDZ</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">JOKER</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">KA</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">MG</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">PG</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div>
      </li>


      <li class="has-dropdown">
        <a href="/category/game" data-code="slots">电子</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">PT</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">JDB</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">FC</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">MW</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">JILI</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div>
      </li>

      <li class="has-dropdown">
        <a href="/category/fishing" data-code="fish">捕鱼</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">DGDZ</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">HW</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">GM</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">GD</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">AGDZ</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div>
      </li>

      <li class="has-dropdown">
        <a href="/category/lottery" data-code="lottery">彩票</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">DBBY</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">BBBY</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">BGBY</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">CR</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">BBTY</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div>
      </li>

      <li class="has-dropdown">
        <a href="/category/sport" data-code="sports">体育</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">PB</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">HG</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">SS</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">IBC</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">FB</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div>
      </li>

      <li class="has-dropdown">
        <a href="/category/poker" data-code="board">棋牌</a>
        <div class="dropdown-scroll">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card"><img src="img/ag.png"/><div class="title">MP</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ebet.png"/><div class="title">BGQP</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">NW</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">LEG</div><button class="play">进入游戏</button></div>
            <div class="card"><img src="img/ag.png"/><div class="title">KX</div><button class="play">进入游戏</button></div>
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div>
      </li>

      <li class="has-dropdown">
        <a href="/category/esports" data-code="esports">电竞</a>
        <div class="dropdown-scroll">
          <div class="scroll-container">
            <div class="card">
              <img src="img/dg.png"/>
              <div class="title">IA</div>
              <button class="play">进入游戏</button>
            </div>
                        <div class="card">
              <img src="img/dg.png"/>
              <div class="title">DBDJ</div>
              <button class="play">进入游戏</button>
            </div>
                        <div class="card">
              <img src="img/dg.png"/>
              <div class="title">TFG</div>
              <button class="play">进入游戏</button>
            </div>
          </div>
        </div>
      </li>
  <a href="/cooperate" class="nav-icon-item" title="合营">
    <!-- 合营 -->
    <svg class="icon-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
      <path fill="currentColor" d="M224 704h576V318.336L552.512 115.84a64 64 0 0 0-81.024 0L224 318.336zm0 64v128h576V768zM593.024 66.304l259.2 212.096A32 32 0 0 1 864 303.168V928a32 32 0 0 1-32 32H192a32 32 0 0 1-32-32V303.168a32 32 0 0 1 11.712-24.768l259.2-212.096a128 128 0 0 1 162.112 0"></path>
      <path fill="currentColor" d="M512 448a64 64 0 1 0 0-128 64 64 0 0 0 0 128m0 64a128 128 0 1 1 0-256 128 128 0 0 1 0 256"></path>
    </svg>
    <span>合营</span>
  </a>

  <a href="/app" class="nav-icon-item" title="APP">
    <!-- APP -->
    <svg class="icon-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
      <path fill="currentColor" d="M256 128a64 64 0 0 0-64 64v640a64 64 0 0 0 64 64h512a64 64 0 0 0 64-64V192a64 64 0 0 0-64-64zm0-64h512a128 128 0 0 1 128 128v640a128 128 0 0 1-128 128H256a128 128 0 0 1-128-128V192A128 128 0 0 1 256 64m128 128h256a32 32 0 1 1 0 64H384a32 32 0 0 1 0-64m128 640a64 64 0 1 1 0-128 64 64 0 0 1 0 128"/>
    </svg>
    <span>APP</span>
  </a>



<div class="right account" id="account">
  <form id="navLoginForm" class="nav-login-form" method="post" action="/public/includes/navbar.php">
    <input class="nav-input short" type="text" name="login" placeholder="账号/邮箱" autocomplete="username" required>
    <input class="nav-input short" type="password" name="password" placeholder="密码" autocomplete="current-password" required>
    <button class="nav-btn primary" type="submit">登录</button>
    <a class="nav-btn ghost" href="/dfbiu/register">注册</a>
    <select class="lang-select" id="navLang" name="lang">
      <option value="zh-CN" selected>zh-CN</option>
      <option value="zh-TW">zh-TW</option>
      <option value="en">en</option>
      <option value="uk">uk</option>
      <option value="ru">ru</option>
    </select>
  </form>
</div>
  </div>
  <script>
  (function(){
    const form = document.getElementById('navLoginForm');
    if (!form) return;
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(form);
      try{
        const res = await fetch(form.action, {
          method:'POST', body:fd, credentials:'same-origin',
          headers:{'X-Requested-With':'XMLHttpRequest'}
        });
        const data = await res.json().catch(()=>({ok:false,msg:'Bad JSON'}));
        if (data.ok) location.reload(); else alert(data.msg || 'Login failed');
      }catch(err){ console.error(err); alert('Network error'); }
    });
  })();
  </script>
<?php else: ?>
  <div class="wrap header-inner">
    <div><img src="broken-logo.png" alt="logo"/></div>

      <ul class="nav">
      <li class="has-dropdown"><a href="/" data-code="home">首页</a>
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
        <a href="/category/live" data-code="video">视讯</a>
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
        <a href="/category/game" data-code="slots">电子</a>
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
        <a href="/category/fishing" data-code="fish">捕鱼</a>
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
        <a href="/category/lottery" data-code="lottery">彩票</a>
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
        <a href="/category/sport" data-code="sports">体育</a>
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
        <a href="/category/poker" data-code="board">棋牌</a>
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
        <a href="/category/esports" data-code="esports">电竞</a>
        <div class="dropdown-scroll">
          <div class="scroll-container">
            <div class="card">
              <img src="img/dg.png"/>
              <div class="title">IA</div>
              <button class="play">进入游戏</button>
            </div>
                        <div class="card">
              <img src="img/dg.png"/>
              <div class="title">DBDJ</div>
              <button class="play">进入游戏</button>
            </div>
                        <div class="card">
              <img src="img/dg.png"/>
              <div class="title">TFG</div>
              <button class="play">进入游戏</button>
            </div>
          </div>
        </div>
      </li>

  <a href="/cooperate" class="nav-icon-item" title="合营">
    <!-- 合营 -->
    <svg class="icon-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
      <path fill="currentColor" d="M224 704h576V318.336L552.512 115.84a64 64 0 0 0-81.024 0L224 318.336zm0 64v128h576V768zM593.024 66.304l259.2 212.096A32 32 0 0 1 864 303.168V928a32 32 0 0 1-32 32H192a32 32 0 0 1-32-32V303.168a32 32 0 0 1 11.712-24.768l259.2-212.096a128 128 0 0 1 162.112 0"></path>
      <path fill="currentColor" d="M512 448a64 64 0 1 0 0-128 64 64 0 0 0 0 128m0 64a128 128 0 1 1 0-256 128 128 0 0 1 0 256"></path>
    </svg>
    <span>合营</span>
  </a>

  <a href="/app-download" class="nav-icon-item" title="APP">
    <!-- APP -->
    <svg class="icon-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
      <path fill="currentColor" d="M256 128a64 64 0 0 0-64 64v640a64 64 0 0 0 64 64h512a64 64 0 0 0 64-64V192a64 64 0 0 0-64-64zm0-64h512a128 128 0 0 1 128 128v640a128 128 0 0 1-128 128H256a128 128 0 0 1-128-128V192A128 128 0 0 1 256 64m128 128h256a32 32 0 1 1 0 64H384a32 32 0 0 1 0-64m128 640a64 64 0 1 1 0-128 64 64 0 0 1 0 128"/>
    </svg>
    <span>APP</span>
  </a>


  <div class="right account" id="account">
    <button class="user-chip" id="userChip" type="button" aria-haspopup="true" aria-expanded="false">
      <div class="avatar" aria-hidden="true"></div>
      <?php $currency = $wallet['currency'] ?? 'USDT';
      $amount   = (float)($wallet['amount'] ?? 0); ?>
      <span class="user-name"><?= htmlspecialchars($me['username']) ?></span>
      <span class="user-balance"><?= htmlspecialchars($me['currency']) ?> <?= htmlspecialchars(format_money($me['amount'])) ?></span>
    </button>
    <div class="acc-menu" id="accMenu" hidden>
      <div class="acc-item"><b></b>&nbsp;<?= htmlspecialchars($me['currency']) ?> <?= htmlspecialchars(format_money($me['amount'])) ?></div>
      <a class="acc-item" href="/membership/user-info">用户信息</a>
      <a class="acc-item" href="/membership/privileges">VIP特权</a>
      <a class="acc-item" href="/membership/card-holder">我的卡包</a>
      <button class="acc-item logout" id="accLogout" type="button">退出</button>
    </div>

          <select class="lang-select" id="navLang" name="lang">
      <option value="zh-CN" selected>zh-CN</option>
      <option value="zh-TW">zh-TW</option>
      <option value="en">en</option>
      <option value="uk">uk</option>
      <option value="ru">ru</option>
    </select>
  </div>
  </div>
  <script>
      const chip=document.getElementById('userChip');
  const menu=document.getElementById('accMenu');
  chip?.addEventListener('click',e=>{e.stopPropagation();menu.classList.toggle('open')});
  document.addEventListener('click',()=>menu.classList.remove('open'));
  document.querySelectorAll('.has-dropdown').forEach(dd=>{
    const prev=dd.querySelector('.scroll-prev');
    const next=dd.querySelector('.scroll-next');
    const cont=dd.querySelector('.scroll-container');
    prev?.addEventListener('click',()=>cont.scrollBy({left:-150,behavior:'smooth'}));
    next?.addEventListener('click',()=>cont.scrollBy({left:150,behavior:'smooth'}));
  });
    </script>
  <script>
  (function(){
    const chip = document.getElementById('userChip');
    const menu = document.getElementById('accMenu');
    const logoutBtn = document.getElementById('accLogout');

    function openMenu(){ menu.removeAttribute('hidden'); chip.setAttribute('aria-expanded','true'); }
    function closeMenu(){ menu.setAttribute('hidden',''); chip.setAttribute('aria-expanded','false'); }
    chip?.addEventListener('click', ()=> menu.hasAttribute('hidden') ? openMenu() : closeMenu());
    document.addEventListener('click', (e)=>{ if(!menu || !chip) return; if(!menu.contains(e.target) && !chip.contains(e.target)) closeMenu(); });
    logoutBtn?.addEventListener('click', async ()=>{
      try{
        const fd = new FormData(); fd.append('logout','1');
        const res = await fetch('/public/includes/navbar.php', {
          method:'POST', body:fd, credentials:'same-origin',
          headers:{'X-Requested-With':'XMLHttpRequest'}
        });
        const data = await res.json().catch(()=>({ok:false,msg:'Bad JSON'}));
        if (data.ok) location.reload(); else alert(data.msg || 'Logout failed');
      }catch(err){ console.error(err); alert('Network error'); }
    });
  })();
  </script>
<?php endif; ?>
