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
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>真人视讯 — Category Live</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#eaf4ff; --text:#2b3b4f; --muted:#8096ad;
    --blue:#2f8eff; --blue2:#55bbff;
    --border:#e4eef7; --white:#fff;
    --shadow:0 14px 28px rgba(36,92,160,.15);
  }
  *{box-sizing:border-box}
  body{
    margin:0; color:var(--text);
    font-family:"Inter","Noto Sans SC",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
    background:radial-gradient(1200px 240px at 50% -120px, rgba(71,165,255,.20), transparent), var(--bg);
  }
  .wrap{width:min(1220px,96vw); margin:0 auto}

  /* HEADER */
  .top{height:70px; display:flex; align-items:center; gap:26px}
  .logo{width:38px;height:24px;border-radius:6px;background:linear-gradient(180deg,#66c2ff,#2e90ff);box-shadow:0 6px 16px rgba(46,144,255,.25)}
  .nav{display:flex;gap:22px;font-weight:700}
  .nav a{color:#354c66;text-decoration:none}
  .sp{flex:1}
  .right{display:flex;align-items:center;gap:10px}
  .pill{height:32px;padding:0 14px;border-radius:999px;border:1px solid #dbe9fb;
        background:linear-gradient(180deg,#f5f9ff,#eaf2ff); color:#5f7aa0; font-weight:700}
  .pill.primary{background:linear-gradient(180deg,#55bbff,#2f8eff);color:#fff;border-color:transparent;
                box-shadow:0 10px 18px rgba(47,142,255,.30)}
  .pill.ghost{color:#7a8ea6}
  .chip{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--border);border-radius:999px;padding:6px 12px;box-shadow:var(--shadow);font-weight:700}
  .avatar{width:28px;height:28px;border-radius:50%;background:#d2d7df}
  .lang{background:#fff;border:1px solid var(--border);border-radius:999px;padding:6px 10px;box-shadow:var(--shadow)}

  /* CONTENT LAYOUT */
  .content{display:grid; grid-template-columns:520px 1fr; gap:26px; align-items:start; margin:12px 0 24px}
  .promo-lg{height:520px; border-radius:16px; background:#9aa0b4; opacity:.85;
            border:1px solid #c7d2e1; box-shadow:0 12px 26px rgba(36,92,160,.12)}
  .right-col{display:flex; flex-direction:column; gap:18px}

  /* CATEGORY ICONS */
  .caticons{display:flex; align-items:center; gap:26px; justify-content:flex-start; padding-right:10px}
  .cat{display:flex; flex-direction:column; align-items:center; gap:6px}
  .cat .ico{width:54px; height:54px; border-radius:50%; background:#fff; border:1px solid #e4eef7;
            box-shadow:0 10px 18px rgba(36,92,160,.12)}
  .cat span{font-weight:700; color:#3a4f67}

  /* PROVIDERS GRID */
  .providers{display:grid; grid-template-columns:repeat(4,1fr); gap:18px; justify-items:start}
  .card{width:120px;height:120px;border-radius:14px;background:#fff;border:1px solid #e4eef7;
        box-shadow:0 14px 24px rgba(36,92,160,.12); display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;cursor:pointer}
  .thumb{width:60px;height:60px;border-radius:12px;background:#e9edf4}
  .title{font-size:12px; color:#6b7f95; font-weight:700}
  .card.active{outline:4px solid #9f8bf7; outline-offset:-4px;}

  /* CTA */
  .cta{display:flex; justify-content:center; margin-top:12px}
  .go{height:36px; padding:0 22px; border-radius:999px; border:0; color:#fff; font-weight:800;
      background:linear-gradient(180deg,#55bbff,#2f8eff); box-shadow:0 12px 22px rgba(47,142,255,.35)}

  /* FOOTER */
  .footer{background:#3b444a;color:#cbd4da;padding:28px 0 48px}
  .brands{display:flex;flex-wrap:wrap;gap:22px;justify-content:center}
  .brand{width:110px;height:26px;border-radius:6px;background:#58636c;opacity:.9}
  .copy{text-align:center;margin-top:16px;opacity:.9}
  .links{text-align:center;margin-top:10px;color:#aebdc9}

  @media (max-width: 1100px){ .content{grid-template-columns:1fr} .promo-lg{height:280px} }
  @media (max-width: 680px){ .providers{grid-template-columns:repeat(2,1fr)} }

  .guest-auth{display:flex;align-items:center;gap:8px;flex-wrap:nowrap}

</style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/navbar6.php'; ?>
<!-- <link rel="stylesheet" href="/public/includes/navbar.css"> -->

<main class="wrap">
  <section class="content">
    <!-- Left promo banner -->
              <img src='/public/assets/img/categ1ory-CMHPLGhY.png'>

    <!-- Right side: categories + providers -->
    <div class="right-col">
      <div class="caticons">
          <img src='/public/assets/img/category-CMHPLGhY.png'>
      </div>

      <section class="providers">
        <div class="card"><div class="thumb"></div><div class="title">DG真人</div></div>
        <div class="card"><div class="thumb"></div><div class="title">欧博视讯</div></div>
        <div class="card"><div class="thumb"></div><div class="title">AG真人</div></div>
        <div class="card"><div class="thumb"></div><div class="title">完美真人</div></div>

        <div class="card active"><div class="thumb"></div><div class="title">SEXY性感真人</div></div>
        <div class="card"><div class="thumb"></div><div class="title">BG真人</div></div>
        <div class="card"><div class="thumb"></div><div class="title">WE真人</div></div>
        <div class="card"><div class="thumb"></div><div class="title">EVO真人</div></div>

        <div class="card"><div class="thumb"></div><div class="title">BET真人</div></div>
        <div class="card"><div class="thumb"></div><div class="title">DG视讯</div></div>
        <div class="card"><div class="thumb"></div><div class="title">…</div></div>
        <div class="card"><div class="thumb"></div><div class="title">…</div></div>
      </section>

      <div class="cta"><button class="go">进入游戏</button></div>
    </div>
  </section>
</main>



<script>
  // JS: активація картки при кліку
  document.querySelectorAll('.card').forEach(card=>{
    card.addEventListener('click', ()=>{
      document.querySelectorAll('.card').forEach(c=>c.classList.remove('active'));
      card.classList.add('active');
    });
  });
</script>
</body>
</html>
