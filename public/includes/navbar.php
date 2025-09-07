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
function current_user(mysqli $conn): ?array {
  if (empty($_SESSION['uid'])) return null;
  $uid = (int)$_SESSION['uid'];
  $sql = "
    SELECT 
      u.id, u.username, u.email, u.role, u.status,
      COALESCE(NULLIF(b.currency,''), u.currency) AS currency,
      IFNULL(b.amount, 0.00) AS amount
    FROM users u
    LEFT JOIN balances b ON b.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc() ?: null;
  $stmt->close();
  return $row;
}
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
    echo json_encode(['ok'=>false,'msg'=>'账号长度不能小于 6 个字符']); exit;
  }
  $u = find_user_by_login($conn, $login);
  if (!$u || !password_matches($u['password_hash'], $pass)) {
    echo json_encode(['ok'=>false,'msg'=>'账号长度不能小于 6 个字符']); exit;
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

          
.nav-icons-5{
  display:flex;
  align-items:center;
  gap:18px;                 /* відстань між пунктами */
  margin-right:12px;        /* трохи перед аватаром */
}

.nav-icon-item{
  display:flex;
  flex-direction:column;    /* іконка зверху, текст під нею */
  align-items:center;
  justify-content:center;
  text-decoration:none;
  color:#6b7688;            /* як на оригіналі – сіро-блакитний */
  line-height:1;
  position:relative;
}

.nav-icon-item .icon-30{
  width:20px;
  height:20px;
}

.nav-icon-item span{
  margin-top:6px;
  font-size:14px;
}

.nav-icon-item::after{      /* підкреслення як індикатор активної */
  content:"";
  position:absolute;
  left:50%;
  transform:translateX(-50%);
  bottom:-8px;
  width:28px;
  height:3px;
  border-radius:3px;
  background:transparent;
}

.nav-icon-item:hover,
.nav-icon-item.is-active{
  color:#14b8a6;            /* бірюзовий акцент */
}

.nav-icon-item.is-active::after{
  background:#14b8a6;       /* показує підкреслення в активному */
}
.right { display: flex; align-items: center;  }

/* інпути у стилі як на скріні */
.right input {
  height: 32px;
  width: 60px; /* короткі */
  border: 1px solid #dbe8f7;
  border-radius: 20px;
  padding: 0 10px;
  font-size: 14px;
  outline: none;
  background: #f5f8fd;
  color: #2a3b4f;
}

.right input:focus {
  border-color: #2e90ff;
  background: #fff;
  box-shadow: 0 0 4px rgba(46,144,255,.4);
}

.guest-auth { display: flex; align-items: center; gap: 8px; }
.guest-auth .btn {
  padding: 0 14px;
  height: 32px;
  line-height: 32px;
  border-radius: 20px;
  border: 1px solid #dbe8f7;
  text-decoration: none;
  color: #2a3b4f;
  font-size: 14px;
  background: #fff;
}
.guest-auth .btn.login { background: #2e90ff; color: #fff; border-color:#2e90ff; }
.guest-auth .btn.login:hover { filter: brightness(1.05); }
/* ===== NAVBAR: гість (логін-форма) ===== */

/* контейнер справа */
.right.account {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
  height: var(--header-h, 60px);
  padding-right: 12px;
}

/* не даємо формі ламатись/переноситись */
#navLoginForm.nav-login-form {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: nowrap;
}

/* інпути */
#navLoginForm .nav-input.short {
  height: 36px;
  min-width: 60px;            /* щоб не стискалось до “чіпса” */
  padding: 0 12px;
  border: 1px solid var(--border, #dbe8f7);
  border-radius: 999px;        /* пігулка */
  background: #fff;
  color: var(--text, #2a3b4f);
  font-size: 14px;
  line-height: 36px;
  outline: none;
  box-shadow: 0 2px 8px rgba(32,74,128,.08);
  transition: border-color .2s, box-shadow .2s, background .2s;
}

#navLoginForm .nav-input.short::placeholder{
  color: #96a3b6;
}

#navLoginForm .nav-input.short:focus{
  border-color: var(--blue, #2e90ff);
  box-shadow: 0 0 0 3px rgba(46,144,255,.18);
  background: #fff;
}

/* кнопка Увійти */
#navLoginForm .nav-btn {
  height: 36px;
  padding: 0 16px;
  border: 0;
  border-radius: 999px;
  background: var(--blue, #2e90ff);
  color: #fff;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 6px 16px rgba(46,144,255,.25);
  transition: transform .06s ease, box-shadow .2s, background .2s;
}

#navLoginForm .nav-btn:hover {
  background: #1f7cf0;
  box-shadow: 0 8px 20px rgba(31,124,240,.28);
}

#navLoginForm .nav-btn:active {
  transform: translateY(1px);
}

/* щоб хедер не “стрибав”, коли з’являється тінь */
.header, .topbar, .site-header {
  will-change: transform;
}

/* мобільна адаптація */
@media (max-width: 900px){
  #navLoginForm .nav-input.short{
    min-width: 60px;
  }
  .right.account{ padding-right: 6px; gap: 6px; }
}
.right.account {
  display: flex;
  align-items: center;
  justify-content: flex-end;  /* прижимає весь блок вправо */
  gap: 10px;
  margin-left: auto;          /* важливо — штовхає вправо */
  height: var(--header-h, 60px);
  padding-right: 16px;        /* відступ від краю */
}

#navLoginForm.nav-login-form {
  display: flex;
  align-items: center;
  gap: 10px;
}
.header {
  display: flex;
  align-items: center;
  justify-content: space-between; /* ліве вліво, праве вправо */
  height: var(--header-h, 60px);
  padding: 0 16px;
}
/* === User Info inputs only === */
#userInfoForm{
  --h:44px;
  --r:10px;
  --line:#dbe8f7;
  --ink:#2a3b4f;
  --muted:#7e8aa6;
  --focus:#2e90ff;
}

/* сітка з оригіналу: 2 колонки */
#userInfoForm .form-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:20px 24px; /* row/col */
}

/* елемент форми */
#userInfoForm .form-item{
  display:flex;
  flex-direction:column;
  gap:8px;
}
#userInfoForm .form-item > label{
  font-size:14px;
  color:var(--muted);
  line-height:1;
}

/* уніфікація всіх інпутів/селектів */
#userInfoForm input[type="text"],
#userInfoForm input[type="tel"],
#userInfoForm input[type="email"],
#userInfoForm input[type="date"],
#userInfoForm input[type="number"],
#userInfoForm select,
#userInfoForm .el-input__inner{
  height:var(--h);
  border:1px solid var(--line);
  border-radius:var(--r);
  background:#fff;
  padding:0 14px;
  font:14px/1.2 Inter,system-ui,"Noto Sans SC",sans-serif;
  color:var(--ink);
  width:100%;
  outline:none;
  transition:border .15s, box-shadow .15s, background .15s;
  box-shadow:0 1px 0 rgba(17,24,39,.02) inset;
}

/* placeholder як на оригіналі */
#userInfoForm input::placeholder{ color:#a7b3c8; }

/* фокус */
#userInfoForm input:focus,
#userInfoForm select:focus,
#userInfoForm .el-input__inner:focus{
  border-color:var(--focus);
  box-shadow:0 0 0 3px rgba(46,144,255,.15);
}

/* select — стрілка та правий відступ */
#userInfoForm select{
  appearance:none;
  padding-right:36px;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2390a4c2'><path d='M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 011.08 1.04l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z'/></svg>");
  background-repeat:no-repeat;
  background-position:right 12px center;
  background-size:18px 18px;
}

/* date input — вирівнюємо іконку/поля */
#userInfoForm input[type="date"]{
  padding-right:12px;
}
#userInfoForm input[type="date"]::-webkit-calendar-picker-indicator{
  opacity:.8;
  margin-right:4px;
}

/* disabled (на майбутнє) */
#userInfoForm input[disabled],
#userInfoForm select[disabled]{
  background:#f7faff;
  color:#a7b3c8;
}

/* email – на весь ряд як у макеті */
#userInfoForm .form-item.full{
  grid-column:1 / -1;
}

/* мобільно — одна колонка */
@media (max-width:1024px){
  #userInfoForm .form-grid{ grid-template-columns:1fr; }
}

/* ===== compact auth block (тільки для цього фрагмента) ===== */
.right.account { margin-left:auto; }

.right.account .nav-login-form{
  display:flex;
  align-items:center;
  gap:8px;
  flex-wrap:nowrap;
}

/* короткі інпути */
.right.account .nav-input.short{
  width:60px;              /* звузив як на скріні */
  height:32px;
  padding:0 10px;
  border:1px solid var(--border, #dbe8f7);
  border-radius:16px;
  background:#fff;
  font-size:12px;
  line-height:32px;
  color:var(--text, #2a3b4f);
}
.right.account .nav-input.short::placeholder{ color:var(--muted, #7e8aa6); }
.right.account .nav-input.short:focus{
  border-color:var(--blue, #2e90ff);
  box-shadow:0 0 0 3px rgba(46,144,255,.15);
  outline:none;
}

/* кнопка входу */
.right.account .nav-btn{
  height:32px;
  padding:0 12px;
  border-radius:16px;
  border:1px solid var(--blue, #2e90ff);
  background:var(--blue, #2e90ff);
  color:#fff;
  font-size:12px;
  font-weight:600;
  line-height:32px;
  cursor:pointer;
}
.right.account .nav-btn:hover{ filter:brightness(.98); }
.right.account .nav-btn:active{ transform:translateY(1px); }

/* адаптив: ще компактніше на вужчих екранах */


/* контейнер */
.right.account { display:flex; align-items:center; }
.nav-login-form { display:flex; align-items:center; gap:8px; }

/* чіп-інпути як на pc.dfbiu */
.nav-input.short{
  width:60px; height:32px;
  padding:0 12px;
  border:1px solid var(--border);
  border-radius:9999px;
  background:#f3f7ff;            /* світло-сірий чіп */
  color:var(--text);
  font-size:13px; line-height:32px;
  outline:none;
  transition:border-color .2s, box-shadow .2s, background .2s;
}
.nav-input.short::placeholder{ color:#7e8aa6; }
.nav-input.short:focus{
  background:#fff;
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(46,144,255,.15);
}

/* кнопки */
.nav-btn{
  height:32px; padding:0 16px;
  border-radius:9999px;
  border:1px solid transparent;
  font-size:13px; font-weight:600;
  cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;
}
.nav-btn.primary{                /* 登录 — синя */
  background:var(--blue); color:#fff; border-color:#1f6fd9;
}
.nav-btn.ghost{                  /* 注册 — сіра */
  background:#f3f7ff; color:var(--text); border:1px solid var(--border);
}
.nav-btn:hover{ filter:brightness(1.03); }

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

/* щоб не ламати хедер на малих ширинах */
@media (max-width: 1100px){
  .nav-input.short{ width:60px; }
  .nav-login-form{ gap:6px; }
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
    </ul>

  <div class="nav-icons-5">
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
</div>



<div class="right account" id="account">
  <form id="navLoginForm" class="nav-login-form" method="post" action="/public/includes/navbar.php">
    <input class="nav-input short" type="text" name="login" placeholder="账号/邮箱" autocomplete="username" required>
    <input class="nav-input short" type="password" name="password" placeholder="密码" autocomplete="current-password" required>
    <button class="nav-btn primary" type="submit">登录</button>
    <a class="nav-btn ghost" href="/register">注册</a>
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
    </ul>

  <div class="nav-icons-5">

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

</div>

  <div class="right account" id="account">
    <button class="user-chip" id="userChip" type="button" aria-haspopup="true" aria-expanded="false">
      <div class="avatar" aria-hidden="true"></div>
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
  </div>
      <select class="lang-select" id="navLang" name="lang">
      <option value="zh-CN" selected>zh-CN</option>
      <option value="zh-TW">zh-TW</option>
      <option value="en">en</option>
      <option value="uk">uk</option>
      <option value="ru">ru</option>
    </select>
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
