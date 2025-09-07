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
<title>会员权益 — pc.df clone</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#e6f1fb; --panel:#fff; --muted:#90a0b4; --text:#2b3b4f;
  --blue:#2f8eff; --blue2:#55bbff; --border:#e5edf7;
  --radius-lg:18px; --radius-md:14px; --radius-sm:10px;
  --shadow:0 15px 30px rgba(32,74,128,.12);
}
*{box-sizing:border-box}
body{
  margin:0; color:var(--text);
  font-family:"Inter","Noto Sans SC",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
  background:radial-gradient(1200px 260px at 50% -120px, rgba(71,165,255,.20), transparent), var(--bg);
}
.wrap{width:min(1220px,96vw); margin:0 auto}

/* top bar (коротко, як на макеті) */
.top{height:64px; display:flex; align-items:center; gap:24px;}
.logo{width:36px; height:22px; border-radius:6px; background:linear-gradient(180deg,#66c2ff,#2e90ff); box-shadow:0 6px 14px rgba(46,144,255,.28)}
.nav{display:flex; gap:22px; font-weight:700}
.nav a{color:#3a5068; text-decoration:none}
.sp{flex:1}
.icons{display:flex; gap:10px}
.icon{width:26px;height:26px;display:grid;place-items:center;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow)}
.user-chip{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--border);border-radius:999px;padding:6px 12px;box-shadow:var(--shadow);font-weight:700}
.avatar{width:28px;height:28px;border-radius:50%;background:url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=100&auto=format&fit=crop') center/cover}
.lang{margin-left:10px; background:#fff; border:1px solid var(--border); border-radius:999px; padding:6px 10px; box-shadow:var(--shadow);}

/* layout */
.grid{display:grid; grid-template-columns:280px 1fr; gap:22px}
.left .card{background:linear-gradient(180deg,#1ea0ff,#1d79ff); color:#fff; border-radius:var(--radius-lg); box-shadow:var(--shadow); padding:18px; display:flex; flex-direction:column; align-items:center; gap:12px}
.avatar-big{width:120px;height:120px;border-radius:14px;background:rgba(255,255,255,.16)}
.nick{font-weight:700}
.left .menu{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;margin-top:12px}
.mi{display:flex;align-items:center;gap:10px;padding:14px 16px;border-top:1px solid var(--border);color:#2a3a4d}
.mi:first-child{border-top:0}
.mi .dot{width:12px;height:12px;border-radius:4px;background:linear-gradient(180deg,var(--blue2),var(--blue))}
.left .hint{padding:12px 16px; color:var(--muted); font-size:13px}

/* right panel */
.panel{background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); padding:18px}
.panel h2{margin:6px 0 12px}
.panel .sub{color:var(--muted); margin-top:-4px}

.header-row{display:flex; align-items:center; justify-content:space-between}
.btn-mini{height:32px;border:0;padding:0 12px;border-radius:10px;background:linear-gradient(180deg,var(--blue2),var(--blue));color:#fff;font-weight:700;box-shadow:0 10px 18px rgba(47,142,255,.35);cursor:pointer}

/* vip progress */
.vip-track{margin-top:12px; background:#f4f8ff; border:1px solid var(--border); border-radius:14px; padding:14px 12px}
.steps{display:flex; align-items:center; gap:14px; margin:6px 0 10px}
.steps .lab{font-weight:700; color:#889ab0}
.dot{width:10px;height:10px;border-radius:50%;background:#cddbed}
.dot.active{background:linear-gradient(180deg,var(--blue2),var(--blue)); box-shadow:0 6px 10px rgba(47,142,255,.35)}
.bar{height:6px; background:#e6effa; border-radius:999px; overflow:hidden}
.bar i{display:block; height:100%; width:10%; background:linear-gradient(90deg,var(--blue2),var(--blue))}

/* benefits cards (малий інфоблок) */
.benefits{display:grid; grid-template-columns:repeat(10,1fr); gap:10px; margin-top:12px}
.benefit{background:#fff; border:1px solid var(--border); border-radius:12px; padding:10px; text-align:center}
.benefit b{display:block; font-size:12px; color:#6d7f92}
.benefit span{display:block; color:#2a3b4f; font-weight:700}

/* table */
.table-card{margin-top:16px; border:1px solid var(--border); border-radius:14px; overflow:hidden}
.table-head{background:#f7fbff; padding:10px 12px; font-weight:700}
.table{width:100%; border-collapse:collapse; background:#fff}
.table th, .table td{border-top:1px solid var(--border); padding:10px 12px; text-align:center; font-size:14px}
.table th{color:#6e8198; background:#fff}
.table tr:nth-child(even) td{background:#fbfdff}

/* bottom button */
.center{display:flex; justify-content:center; margin:16px 0 6px}
.btn{height:40px; padding:0 18px; border-radius:999px; border:0; background:linear-gradient(180deg,var(--blue2),var(--blue)); color:#fff; font-weight:700; box-shadow:0 12px 22px rgba(47,142,255,.32); cursor:pointer}

@media (max-width:1060px){ .grid{grid-template-columns:1fr} }

/* стилі під скрін — компактний ряд з підписами */
.quick-actions{
  display:flex; gap:16px;
  align-items:flex-start;
  margin:14px 0 6px;
}
.quick-actions .qa{
  width:72px; text-align:center;
  text-decoration:none; color:var(--text);
}
.quick-actions img{
  width:56px; height:56px;
  display:block; margin:0 auto 6px;
  border-radius:14px; /* якщо PNG без фону — дає мʼякі кути */
}
.quick-actions .label{
  font-size:12px; line-height:1; color:#7e8aa6;
}
@media (max-width: 640px){
  .quick-actions{ gap:12px }
  .quick-actions .qa{ width:64px }
  .quick-actions img{ width:48px; height:48px }
}

</style>
</head>
<body>
  
<?php include __DIR__ . '/../includes/navbar6.php'; ?>
<link rel="stylesheet" href="/public/includes/navbar.css">
<div class="wrap" style="margin-bottom:30px">
  <div class="grid">
    <!-- LEFT -->
    <aside class="left">
      <div class="card">
        <div class="avatar-big"></div>
        <div class="nick">test228</div>
      </div>
      
            <div class="quick-actions">
        <a class="qa" href="/membership/deposit">
          <img src="/public/assets/img/deposit-DWbaeqin.png" alt="充值">
          <span class="label">充值</span>
        </a>

        <a class="qa" href="/membership/transfer">
          <img src="/public/assets/img/transfer-Dp9ZMyl.png" alt="转换">
          <span class="label">转换</span>
        </a>

        <a class="qa" href="/membership/withdraw">
          <img src="/public/assets/img/withdraw-pAIYbTu2.png" alt="提现">
          <span class="label">提现</span>
        </a>
      </div>
      <div class="menu">
        <div class="mi"><span class="dot"></span> 充值</div>
        <div class="mi"><span class="dot"></span> 转换</div>
        <div class="mi"><span class="dot"></span> 提现</div>
        <div class="mi"><span class="dot"></span> 用户信息</div>
        <div class="mi"><span class="dot"></span> VIP特权</div>
        <div class="mi"><span class="dot"></span> 我的卡包</div>
        <div class="hint">— 左е меню як на твоєму скріні</div>
      </div>
    </aside>

    <!-- RIGHT -->
    <main class="panel">
      <div class="header-row">
        <h2>会员权益</h2>
        <button class="btn-mini">查看VIP详情</button>
      </div>

      <!-- vip progress -->
      <div class="vip-track">
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div style="display:flex;align-items:center;gap:10px">
            <img src="" alt="" style="width:44px;height:44px;border-radius:50%;background:linear-gradient(180deg,#ffd9a8,#ffa04d)">
            <div>
              <div style="font-weight:700">当前等级：VIP0</div>
              <div class="sub">成长值：<b>0</b></div>
            </div>
          </div>
        </div>

        <div class="steps" style="margin-top:10px">
          <span class="lab">VIP0</span>
          <span class="dot active"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span>
          <span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span>
          <span class="lab">VIP10</span>
        </div>
        <div class="bar"><i style="width:10%"></i></div>

        <div class="sub" style="margin-top:10px">距离下一个等级：VIP1</div>
      </div>

      <!-- benefits small grid -->
      <section style="margin-top:12px">
        <div class="sub" style="margin:6px 0 8px">会员权益</div>
        <div class="benefits">
          <div class="benefit"><b>升级奖励</b><span>0.00</span></div>
          <div class="benefit"><b>升级流水</b><span>100.00</span></div>
          <div class="benefit"><b>每日提款次数</b><span>5</span></div>
          <div class="benefit"><b>单日提款总额</b><span>100000.00</span></div>
          <div class="benefit"><b>单笔最低提款</b><span>10.00</span></div>
          <div class="benefit"><b>单笔最高提款</b><span>999999.00</span></div>
          <div class="benefit"><b>升级赠送</b><span>0.00</span></div>
          <div class="benefit"><b>生日赠送</b><span>0.00</span></div>
          <div class="benefit"><b>返水金额</b><span>0.00</span></div>
          <div class="benefit"><b>提款手续费</b><span>10.00</span></div>
        </div>
      </section>

      <!-- table -->
      <div class="table-card">
        <div class="table-head">VIP等级比例</div>
        <table class="table">
          <thead>
            <tr>
              <th>VIP等级</th>
              <th>视讯返水</th>
              <th>电子返水</th>
              <th>彩票返水</th>
              <th>体育返水</th>
              <th>电竞返水</th>
              <th>棋牌返水</th>
              <th>捕鱼返水</th>
            </tr>
          </thead>
          <tbody>
            <!-- рядки як на скріні (приклад) -->
            <tr><td>VIP0</td><td>0.20</td><td>0.20</td><td>0.20</td><td>0.20</td><td>0.20</td><td>0.20</td><td>0.20</td></tr>
            <tr><td>VIP1</td><td>0.50</td><td>0.50</td><td>0.50</td><td>0.50</td><td>0.50</td><td>0.50</td><td>0.50</td></tr>
            <tr><td>VIP2</td><td>0.60</td><td>0.60</td><td>0.60</td><td>0.60</td><td>0.60</td><td>0.60</td><td>0.60</td></tr>
            <tr><td>VIP3</td><td>0.70</td><td>0.70</td><td>0.70</td><td>0.70</td><td>0.70</td><td>0.70</td><td>0.70</td></tr>
            <tr><td>VIP4</td><td>0.80</td><td>0.80</td><td>0.80</td><td>0.80</td><td>0.80</td><td>0.80</td><td>0.80</td></tr>
            <tr><td>VIP5</td><td>0.90</td><td>0.90</td><td>0.90</td><td>0.90</td><td>0.90</td><td>0.90</td><td>0.90</td></tr>
            <tr><td>VIP6</td><td>1.00</td><td>1.00</td><td>1.00</td><td>1.00</td><td>1.00</td><td>1.00</td><td>1.00</td></tr>
            <tr><td>VIP7</td><td>1.10</td><td>1.10</td><td>1.10</td><td>1.10</td><td>1.10</td><td>1.10</td><td>1.10</td></tr>
            <tr><td>VIP8</td><td>1.20</td><td>1.20</td><td>1.20</td><td>1.20</td><td>1.20</td><td>1.20</td><td>1.20</td></tr>
            <tr><td>VIP9</td><td>1.30</td><td>1.30</td><td>1.30</td><td>1.30</td><td>1.30</td><td>1.30</td><td>1.30</td></tr>
            <tr><td>VIP10</td><td>1.40</td><td>1.40</td><td>1.40</td><td>1.40</td><td>1.40</td><td>1.40</td><td>1.40</td></tr>
          </tbody>
        </table>
      </div>

      <div class="center"><button class="btn">查看VIP详情</button></div>
    </main>
  </div>
</div>

</body>
</html>
