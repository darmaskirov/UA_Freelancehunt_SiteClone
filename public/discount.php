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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>优惠活动 - pc.dfbiu.com (clone)</title>
<style>
  :root{
    --bg:#e9f3fe;           /* світло-блакитний фон контенту */
    --text:#1f2533;
    --muted:#9aa4b2;
    --brand:#0bb2ff;        /* блакитний градієнт у шапках/кнопках */
    --brand2:#2cc1ff;
    --card:#ffffff;
    --line:#eef2f6;
    --footer:#22272e;
    --footerText:#c7d0db;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,"PingFang SC","Hiragino Sans GB","Microsoft YaHei","Noto Sans CJK SC",sans-serif;
    color:var(--text);
    background:#fff;
  }

  /* ===== Top utility bar (icons + profile) ===== */
  .topbar{
    display:flex; align-items:center; justify-content:flex-end;
    gap:16px;
    padding:12px 20px;
    border-bottom:1px solid var(--line);
  }
  .topbar .utl{
    display:flex; align-items:center; gap:14px;
    color:#6b7280;
  }
  .icon-btn{
    width:28px; height:28px; border-radius:8px; background:#f3f5f8;
    display:grid; place-items:center; cursor:pointer;
  }
  .icon-btn svg{width:16px;height:16px;opacity:.8}
  .profile{
    display:flex; align-items:center; gap:10px;
    padding-left:10px; border-left:1px solid var(--line);
  }
  .avatar{
    width:32px;height:32px;border-radius:50%;
    background:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="%23ffb36b"/><stop offset="1" stop-color="%23ff7a59"/></linearGradient></defs><circle cx="40" cy="40" r="40" fill="url(%23g)"/></svg>') center/cover no-repeat;
  }
  .balance{font-weight:600}
  .lang{
    padding:6px 10px; border:1px solid var(--line); border-radius:999px; color:#6b7280; cursor:pointer;
    background:#fff;
  }

  /* ===== Main navigation ===== */
  .nav{
    display:flex; align-items:center; gap:28px;
    padding:14px 24px;
  }
  .nav a{
    text-decoration:none; color:#273244; font-weight:600;
  }
  .nav a:hover{color:#0ea5e9}
  .nav .home-ico{
    width:20px;height:20px; display:inline-block; vertical-align:-4px;
    margin-right:6px; opacity:.8;
    background:conic-gradient(from 180deg at 50% 50%, #34d399, #06b6d4, #3b82f6, #34d399);
    border-radius:4px;
  }

  /* ===== Page header (tabs row that appears in screenshot) ===== */
  .page-tabs{
    display:flex; gap:18px; align-items:center;
    padding:8px 24px 14px;
    color:#6b7280;
  }
  .page-tabs .tab{display:flex; align-items:center; gap:6px; cursor:pointer}
  .page-tabs .tab:hover{color:#0ea5e9}
  .page-tabs .tab svg{width:16px;height:16px}

  /* ===== Content area ===== */
  .content-wrap{
    background:var(--bg);
    min-height:520px;
    padding:36px 0 64px;
  }
  .container{max-width:1200px;margin:0 auto;padding:0 20px}

  /* Left card with categories */
  .left-card{
    width:240px; background:var(--card); border-radius:18px; padding:14px 0;
    box-shadow:0 6px 20px rgba(20,80,140,.08);
    position:relative;
  }
  .left-card .title{
    position:absolute; top:-18px; left:16px; right:16px;
    height:44px; border-radius:999px;
    background:linear-gradient(90deg,var(--brand),var(--brand2));
    display:grid; place-items:center; color:#fff; font-weight:700; letter-spacing:.5px;
    box-shadow:0 6px 18px rgba(11,178,255,.35);
  }
  .left-card ul{list-style:none; padding:52px 10px 10px; margin:0}
  .left-card li{
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; margin:6px 6px; border-radius:12px;
    color:#cbd5e1; /* як на скріні — сірі неактивні пункти */
    user-select:none;
  }
  .left-card li .dot{
    width:16px;height:16px;border-radius:4px;background:#eef2f7;display:inline-block;
    position:relative;
  }
  .left-card li .dot::after{
    content:""; position:absolute; inset:5px; border-radius:2px; background:#dfe7f1;
  }

  /* Empty state (box illustration) */
  .empty{
    flex:1;
    display:grid; place-items:center;
  }
  .stage{
    width:360px; max-width:60vw; text-align:center; color:#8e9bb0;
  }
  .stage svg{width:100%; height:auto; display:block; margin:0 auto 8px}
  .stage .caption{font-size:13px}

  /* Layout: sidebar + empty */
  .grid{
    display:grid; grid-template-columns:240px 1fr; gap:38px;
  }
  @media (max-width: 900px){
    .grid{grid-template-columns:1fr}
    .left-card{order:2}
  }

  /* ===== Footer ===== */
  .footer{
    background:var(--footer); color:var(--footerText);
    padding:26px 0 36px; margin-top:0;
  }
  .logos{
    display:flex; flex-wrap:wrap; gap:16px; align-items:center; justify-content:center;
    padding:18px 0 14px; border-top:1px solid rgba(255,255,255,.06); border-bottom:1px solid rgba(255,255,255,.06);
  }
  .logo{
    width:86px; height:28px; border-radius:6px;
    background:linear-gradient(180deg,#3a4250,#2a3038);
    display:grid; place-items:center; font-size:10px; color:#aeb7c3;
  }
  .foot-links{
    text-align:center; margin:14px 0 8px; font-size:13px;
  }
  .foot-links a{color:#aeb7c3; text-decoration:none; margin:0 8px}
  .foot-copy{ text-align:center; font-size:12px; opacity:.8 }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar6.php'; ?>
  <!-- Content -->
  <div class="content-wrap">
    <div class="container">
      <div class="grid">
        <!-- Left filter card -->
        <aside class="left-card">
          <div class="title">优惠活动</div>
          <ul>
            <li><span class="dot"></span> 限时活动</li>
            <li><span class="dot"></span> 新人首存</li>
            <li><span class="dot"></span> 日常活动</li>
            <li><span class="dot"></span> 高额返水</li>
            <li><span class="dot"></span> VIP特权</li>
          </ul>
        </aside>

        <!-- Empty state -->
        <section class="empty">
          <div class="stage">
            <!-- просте SVG «коробка» як на скріні -->
            <svg viewBox="0 0 320 220" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0" stop-color="#e6eef9"/>
                  <stop offset="1" stop-color="#cfe0f4"/>
                </linearGradient>
                <linearGradient id="g2" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0" stop-color="#d9e8fb"/>
                  <stop offset="1" stop-color="#bdd3ee"/>
                </linearGradient>
              </defs>
              <ellipse cx="160" cy="188" rx="92" ry="14" fill="#dfe9f7"/>
              <g transform="translate(40,12)">
                <path d="M160 58 208 78 196 138 148 118Z" fill="url(#g1)"/>
                <path d="M160 58 112 78 124 138 172 118Z" fill="url(#g2)"/>
                <path d="M112 78 160 98 208 78 160 58Z" fill="#eef4fd"/>
                <path d="M124 138 160 154 196 138 160 122Z" fill="#e7effa"/>
                <path d="M160 98v56" stroke="#d1def2" stroke-width="2"/>
              </g>
            </svg>
            <div class="caption">暂无数据</div>
          </div>
        </section>
      </div>
    </div>
  </div>


<script>
  // (опційно) активний таб/ховер — тільки декоративно
  document.querySelectorAll('.page-tabs .tab').forEach(t=>{
    t.addEventListener('click', ()=> alert('该页面暂无数据（克隆版演示）'));
  });
</script>
</body>
</html>
