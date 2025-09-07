<?php
// /public/includes/navbar.php
// Stable navbar: sessions + mysqli + balance + AJAX login/logout

require_once $_SERVER['DOCUMENT_ROOT'].'/dfbiu/app/boot_session.php';

/* 1) DB connect: очікуємо $conn (mysqli) з config.php; інакше спробуємо локалку */
if (!isset($conn) || !($conn instanceof mysqli)) {
  $cfg = __DIR__ . '/../../config/config.php';
  if (is_file($cfg)) require_once $cfg;                // має створити $conn (mysqli)
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  $conn = @new mysqli('127.0.0.1', 'root', '', 'dfbiu_clone');
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
}

/* base */
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,system-ui,"Noto Sans SC",sans-serif;background:#f5f8fd;color:var(--text)}

/* header */
.header{position:sticky;top:0;z-index:1000;background:#eaf4ff;border-bottom:1px solid #fff}
.wrap{width:min(1240px,96vw);margin:0 auto}
.header-inner{height:60px;display:flex;align-items:center;gap:16px}
.logo img{width:38px;height:24px;object-fit:contain}

/* nav center */
.nav{flex:1;display:flex;justify-content:center;align-items:center;gap:26px;list-style:none;font-weight:700;color:#334a63}
.nav a{padding:10px 2px;text-decoration:none;color:inherit;font-size:15px;transition:.15s}
.nav a:hover{color:#0a58ff}
.nav a.active{color:#0a58ff;position:relative}
.nav a.active::after{content:"";position:absolute;left:0;right:0;bottom:-6px;height:3px;background:var(--blue);border-radius:3px}

/* right side: pill buttons (як на скріні) */
.right{display:flex;align-items:center;gap:10px}
.pill{
  display:inline-flex;align-items:center;gap:6px;
  height:34px;padding:0 12px;border-radius:999px;border:1px solid var(--border);
  background:#fff;box-shadow:0 6px 16px rgba(32,74,128,.10);font-weight:700;color:#5b6f86;
}
.pill.gray{background:#eef3fb;color:#5b6f86;border-color:#e6eefb}
.pill.blue{background:#1e8bff;color:#fff;border-color:#1e8bff}
.pill:hover{filter:brightness(1.03)}
.pill .icon{width:18px;height:18px;display:inline-block}

/* account chip + баланс */
.account{position:relative}
.user-chip{
  display:flex;align-items:center;gap:10px;background:#fff;padding:6px 10px;border-radius:999px;
  border:1px solid var(--border);box-shadow:var(--shadow);font-weight:700;cursor:pointer
}
.avatar{width:28px;height:28px;border-radius:50%;background:#d9e7ff url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=100&auto=format&fit=crop') center/cover}
.user-balance{color:#0d6efd;font-weight:800}

/* full-width dropdown (під весь екран) */
.has-dropdown{position:static}              /* важливо: щоб дроп абсолютно позиціонувався від вікна */
.dropdown-full{
  position:absolute;left:0;top:60px;       /* рівно під хедером */
  width:100vw;background:#fff;border-top:1px solid var(--border);
  box-shadow:var(--shadow);padding:36px 60px;
  opacity:0;transform:translateY(10px);pointer-events:none;transition:all .25s ease;z-index:100;
}
.has-dropdown:hover .dropdown-full{opacity:1;transform:translateY(0);pointer-events:auto}

/* grid of cards всередині дропу */
.grid-menu{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:28px}
.grid-item{
  background:#f8fbff;border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);
  padding:16px;text-align:center;transition:.2s
}
.grid-item:hover{transform:translateY(-4px)}
.grid-item img{width:100%;max-width:120px;height:80px;object-fit:contain;margin:0 auto 12px}
.grid-item .title{font-weight:700;color:#334a63;font-size:14px}

/* account dropdown */
.acc-menu{
  position:absolute;right:0;top:calc(100% + 8px);min-width:220px;background:#fff;border:1px solid var(--border);
  border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.12);padding:6px 0;opacity:0;transform:translateY(10px);
  pointer-events:none;transition:all .25s ease
}
.acc-menu.open{opacity:1;transform:translateY(0);pointer-events:auto}
.acc-item{display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:14px;color:#334a63;text-decoration:none;cursor:pointer}
.acc-item:hover{background:var(--hover);color:#0a58ff}
.acc-item.logout{border-top:1px solid #e9eef5;margin-top:4px;padding:12px 14px;font-weight:700;color:#cf3e3e}
.acc-item.logout:hover{background:#fff2f2;color:#a82121}

/* компактні інпути логіну в хедері */
.nav-login-form{display:flex;gap:8px;align-items:center}
.nav-input.short{width:140px;height:34px;padding:0 10px;border:1px solid #dbe8f7;border-radius:999px;outline:none;background:#fff}
.nav-input.short:focus{box-shadow:0 0 0 3px rgba(46,144,255,.15)}
.nav-btn{height:34px;padding:0 14px;border:1px solid #dbe8f7;border-radius:999px;background:#fff;cursor:pointer}
.nav-btn:hover{background:#f5f9ff}

/* іконки праворуч (за потреби) */
.nav-icons{display:flex;gap:12px;align-items:center;margin-right:8px}
.nav-icons .icon svg{width:22px;height:22px;color:#5b6f86;cursor:pointer;transition:color .2s}
.nav-icons .icon svg:hover{color:#1e8bff}

/* адаптив */
@media (max-width:1024px){
  .nav{gap:18px}
  .dropdown-full{padding:26px 24px}
}
@media (max-width:768px){
  .header-inner{gap:10px}
  .nav{display:none}                 /* мобільна логіка — приховаємо верхнє меню */
  .nav-input.short{width:120px}
}

  </style>
<?php if (!$me): ?>
  <div class="right account" id="account">
    <form id="navLoginForm" class="nav-login-form" method="post" action="/dfbiu/public/includes/navbar.php">
      <input class="nav-input short" type="text" name="login" placeholder="Логін або email" autocomplete="username" required>
      <input class="nav-input short" type="password" name="password" placeholder="Пароль" autocomplete="current-password" required>
      <button class="nav-btn" type="submit">Увійти</button>
    </form>
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
  <div class="right account" id="account">
    <button class="user-chip" id="userChip" type="button" aria-haspopup="true" aria-expanded="false">
      <div class="avatar" aria-hidden="true"></div>
      <span class="user-name"><?= htmlspecialchars($me['username']) ?></span>
      <span class="user-balance"><?= htmlspecialchars($me['currency']) ?> <?= htmlspecialchars(format_money($me['amount'])) ?></span>
    </button>
    <div class="acc-menu" id="accMenu" hidden>
      <div class="acc-item"><b>Баланс:</b>&nbsp;<?= htmlspecialchars($me['currency']) ?> <?= htmlspecialchars(format_money($me['amount'])) ?></div>
      <a class="acc-item" href="/dfbiu/membership/user-info">用户信息</a>
      <a class="acc-item" href="/dfbiu/membership/privileges">VIP特权</a>
      <a class="acc-item" href="/dfbiu/membership/card-holder">我的卡包</a>
      <button class="acc-item logout" id="accLogout" type="button">Вийти</button>
    </div>
  </div>
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
        const res = await fetch('/dfbiu/public/includes/navbar.php', {
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
