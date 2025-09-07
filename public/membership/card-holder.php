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
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>我的卡包 - 会员中心</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#eaf3fc;          /* світлий фон сторінки */
    --panel:#ffffff;       /* білі панелі */
    --line:#e6edf5;
    --text:#1f2a37;
    --muted:#6b7a8c;
    --primary:#2ea1ff;     /* синій бренд */
    --primary-600:#1988ff;
    --primary-soft:#e6f3ff;
    --chip:#f3f6fb;
    --success:#0aa779;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    font-family:"Inter","Noto Sans SC",system-ui,-apple-system,Segoe UI,Roboto,Arial,"PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif;
    color:var(--text);
    background:var(--bg);
  }

  /* 顶部栏 */
  .topbar{
    position:sticky; top:0; z-index:50;
    background:#fff;
    border-bottom:1px solid var(--line);
  }
  .topbar-inner{
    max-width:1200px; margin:0 auto;
    display:flex; align-items:center; gap:18px;
    padding:10px 16px;
  }
  .logo{ display:flex; align-items:center; gap:10px; font-weight:700; }
  .logo img{ width:28px; height:28px; border-radius:6px; background:#ddd; object-fit:cover }
  .mainnav{ display:flex; gap:18px; margin-left:8px; }
  .mainnav a{
    text-decoration:none; color:#2b3b4f; font-weight:600; font-size:14px;
    padding:8px 4px; border-radius:6px;
  }
  .mainnav a:hover{ color:var(--primary) }

  .top-spacer{ flex:1 }

  .quick-icons{ display:flex; gap:12px; align-items:center; }
  .quick-icons .qi{
    display:flex; align-items:center; gap:6px;
    font-size:13px; color:#334155; text-decoration:none;
    padding:6px 8px; border-radius:8px;
  }
  .quick-icons .qi:hover{ background:var(--chip) }

  .userbox{ display:flex; align-items:center; gap:12px; }
  .balance{ font-weight:700; font-size:14px; color:#111827 }
  .avatar{
    display:flex; align-items:center; gap:8px; cursor:pointer; position:relative;
    padding:4px 6px; border-radius:10px;
  }
  .avatar:hover{ background:var(--chip) }
  .avatar img{ width:28px; height:28px; border-radius:999px; object-fit:cover; background:#ddd }
  .avatar .name{ font-weight:600; font-size:14px }
  .lang{
    position:relative;
  }
  .lang select{
    border:1px solid var(--line); background:#fff; border-radius:10px;
    padding:6px 28px 6px 10px; font-size:13px; color:#374151;
    appearance:none; -moz-appearance:none; -webkit-appearance:none;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="%236b7280"><path d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.25 8.29a.75.75 0 01-.02-1.08z"/></svg>');
    background-repeat:no-repeat; background-position:right 8px center;
  }

  /* 页面布局 */
  .wrap{ max-width:1200px; margin:0 auto; padding:18px 16px 40px; }
  .page-title{ display:none } /* на оригіналі заголовка зліва нема – панель одразу */

  .grid{
    display:grid;
    grid-template-columns: 260px 1fr;
    gap:20px;
  }

  /* 左列 */
  .profile-card{
    background:var(--panel); border-radius:16px; padding:16px;
    box-shadow:0 1px 0 rgba(15,23,42,.03);
  }
  .blue-card{
    background:linear-gradient(180deg, var(--primary) 0%, var(--primary-600) 100%);
    color:#fff; height:230px; border-radius:16px;
    display:flex; align-items:center; justify-content:center; flex-direction:column;
    gap:10px; margin-bottom:16px;
  }
  .blue-card .ph-avatar{
    width:92px; height:92px; border-radius:12px; background:rgba(255,255,255,.2);
    display:grid; place-items:center; font-size:28px; font-weight:700;
  }
  .blue-card .uname{ font-weight:700 }

  .side-menu{ display:flex; flex-direction:column; gap:14px; }
  .side-group{ padding:12px 0; border-top:1px dashed var(--line); }
  .side-group:first-child{ border-top:none }
  .side-item{
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:12px; cursor:pointer;
    color:#1f2a37; text-decoration:none; font-weight:600; font-size:14px;
  }
  .side-item .ico{
    width:28px; height:28px; border-radius:8px; background:var(--chip);
    display:grid; place-items:center; font-size:14px;
  }
  .side-item:hover{ background:var(--chip) }
  .side-item.active{
    background:var(--primary-soft); color:var(--primary-600);
    outline:1px solid #cfe6ff;
  }

  /* 右列 */
  .panel{
    background:var(--panel); border-radius:16px; padding:22px;
    box-shadow:0 1px 0 rgba(15,23,42,.03);
    min-height:520px;
  }
  .panel h2{ margin:0 0 14px; font-size:20px }
  .tips{ color:var(--muted); font-size:13px; line-height:1.7; }
  .tips li{ margin:6px 0 }
  .btn{
    display:inline-block; padding:10px 14px; border-radius:10px; font-weight:700;
    text-decoration:none; border:1px solid transparent; cursor:pointer;
  }
  .btn-primary{ background:var(--primary); color:#fff; }
  .btn-primary:hover{ background:var(--primary-600) }

  /* 响应式 */
  @media (max-width:980px){
    .grid{ grid-template-columns:1fr; }
    .quick-icons{ display:none }
  }
</style>
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/public/includes/navbar6.php'; ?>
<!-- 页面主体 -->
<main class="wrap">
  <div class="grid">
    <!-- 左列 -->
    <aside>
      <div class="profile-card">
        <div class="blue-card">
          <div class="ph-avatar">🟦</div>
          <div class="uname">test228</div>
        </div>

        <nav class="side-menu">
          <div class="side-group">
            <a class="side-item" href="#">
              <div class="ico">￥</div><span>充值</span>
            </a>
            <a class="side-item" href="#">
              <div class="ico">⇄</div><span>转换</span>
            </a>
            <a class="side-item" href="#">
              <div class="ico">⤓</div><span>提现</span>
            </a>
          </div>

          <div class="side-group">
            <a class="side-item" href="#">
              <div class="ico">🧾</div><span>用户信息</span>
            </a>
            <a class="side-item" href="#">
              <div class="ico">👑</div><span>VIP特权</span>
            </a>
          </div>

          <div class="side-group">
            <a class="side-item active" href="#">
              <div class="ico">💳</div><span>我的卡包</span>
            </a>
          </div>
        </nav>
      </div>
    </aside>

    <!-- 右列 -->
    <section>
      <div class="panel">
        <h2>我的卡包</h2>
        <ul class="tips">
          <li>绑定银行卡必须填写真实姓名；</li>
          <li>一旦绑定后无法修改真实姓名；</li>
          <li>如需更改真实姓名请联系在线客服处理。</li>
        </ul>

        <div style="margin-top:14px;">
          <button class="btn btn-primary">去认证真实姓名</button>
        </div>
      </div>
    </section>
  </div>
</main>

<script>
  // 示例：активний пункт у лівому меню (можеш підсвічувати за URL)
  document.querySelectorAll('.side-item').forEach(a=>{
    a.addEventListener('click', e=>{
      document.querySelectorAll('.side-item').forEach(i=>i.classList.remove('active'));
      a.classList.add('active');
    });
  });

  // заглушка меню профілю
  const avatar = document.getElementById('avatarMenu');
  avatar.addEventListener('click', ()=>alert('账户菜单（示例）\n— 个人中心\n— 安全设置\n— 退出登录'));
</script>
</body>
</html>
