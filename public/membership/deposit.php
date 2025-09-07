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
<?php
// /dfbiu/public/wallet/deposit.php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/boot_session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>充值 - 钱包</title>

<style>
/* ====== ВСЕ ПІД .dp-page ЩОБ НЕ ЛАМАВ navbar6 ====== */
.dp-page{
  --dp-bg:#e6f1fb; --dp-card:#fff; --dp-ink:#2a3b4f; --dp-muted:#8aa0b9;
  --dp-line:#e7eef8; --dp-brand:#2e90ff; --dp-hover:#f3f7ff;
  --dp-shadow:0 12px 28px rgba(32,74,128,.10);
  font-family:Inter,system-ui,"Noto Sans SC",sans-serif;
  color:var(--dp-ink);
  background:var(--dp-bg);
}
.dp-page *{box-sizing:border-box}

/* layout */
.dp-wrap{width:min(1240px,96vw);margin:24px auto;display:grid;grid-template-columns:260px 1fr;gap:20px}

/* side */
.dp-side{background:#f1f6ff;border:1px solid var(--dp-line);border-radius:12px;box-shadow:var(--dp-shadow)}
.dp-prof{background:#2e90ff;border-radius:12px 12px 0 0;color:#fff;padding:24px 16px;display:flex;flex-direction:column;align-items:center}
.dp-ava{width:120px;height:120px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:700;margin-bottom:10px}
.dp-name{font-weight:600}
.dp-date{opacity:.9;font-size:12px;margin-top:6px}

.dp-qa{display:flex;gap:18px;justify-content:center;padding:14px 10px;background:#f7fbff}
.dp-qa .dp-qa-i{width:60px;text-align:center;text-decoration:none;color:var(--dp-ink)}
.dp-qa img{width:48px;height:48px;display:block;margin:0 auto 6px;border-radius:14px}
.dp-qa .dp-qa-t{font-size:12px;color:var(--dp-muted)}

.dp-nav{padding:10px 14px 16px}
.dp-nav .dp-grp{padding:8px 0;border-top:1px solid var(--dp-line)}
.dp-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;margin:4px 0;border-radius:8px;color:var(--dp-ink);text-decoration:none;font-size:14px}
.dp-nav a:hover{background:var(--dp-hover)}
.dp-nav a.dp-active{background:#e8f3ff;color:#1677ff;font-weight:600}

/* main */
.dp-card{background:var(--dp-card);border:1px solid var(--dp-line);border-radius:12px;box-shadow:var(--dp-shadow);padding:22px}
.dp-title{font-size:20px;font-weight:700;margin:2px 0 18px}

.dp-paytype{display:flex;gap:12px;margin-bottom:18px}
.dp-tab{border:1px solid var(--dp-line);background:#f9fbff;border-radius:10px;padding:10px 14px;font-size:13px;color:#9bb0c9;cursor:pointer}
.dp-tab.dp-on{color:#2b6cb0;border-color:#d6e6fb;background:#eef6ff}

.dp-channel{min-height:110px;border:1px dashed #dbe8f7;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#9bb0c9;margin-bottom:20px}
.dp-channel .dp-empty{display:flex;flex-direction:column;align-items:center;gap:6px}
.dp-channel .dp-cloud{width:28px;height:20px;border-radius:4px;background:#e8f3ff}

.dp-amount .dp-lbl{margin:2px 0 10px;font-weight:600}
.dp-preset{display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.dp-btn{min-width:78px;height:38px;padding:0 14px;border:1px solid #e6edf8;background:#fff;border-radius:10px;font-size:14px;color:#9bb0c9;cursor:pointer}
.dp-btn.dp-on{background:var(--dp-brand);color:#fff;border-color:var(--dp-brand);box-shadow:0 6px 14px rgba(46,144,255,.25)}

.dp-input input{width:min(380px,90%);height:40px;border:1px solid #e6edf8;border-radius:8px;padding:0 12px;font-size:14px;background:#fff}
.dp-submit{margin-top:16px;width:min(380px,90%);height:40px;border:none;border-radius:8px;background:var(--dp-brand);color:#fff;font-weight:600;cursor:pointer}
.dp-submit:hover{filter:brightness(1.02)}

@media (max-width: 920px){
  .dp-wrap{grid-template-columns:1fr}
  .dp-qa{justify-content:flex-start;padding-left:18px}
}
</style>
</head>
<body>

<?php require_once $_SERVER['DOCUMENT_ROOT'].'/public/includes/navbar6.php'; ?>

<div class="dp-page">
  <div class="dp-wrap">

    <!-- LEFT -->
    <aside class="dp-side">
      <div class="dp-prof">
        <div class="dp-ava">f</div>
        <div class="dp-name">test228</div>
        <div class="dp-date">2025-09-01</div>
        
      </div>


      <div class="dp-qa">
        <a class="dp-qa-i" href="/membership/deposit">
          <img src="/public/assets/img/deposit-DWbaeqin.png" alt="充值"><span class="dp-qa-t">充值</span>
        </a>
        <a class="dp-qa-i" href="/membership/transfer">
          <img src="/public/assets/img/transfer-Dp9ZMyl.png" alt="转换"><span class="dp-qa-t">转换</span>
        </a>
        <a class="dp-qa-i" href="/membership/withdraw">
          <img src="/public/assets/img/withdraw-pAIYbTu2.png" alt="提现"><span class="dp-qa-t">提现</span>
        </a>
      </div>

      

      <nav class="dp-nav">
        <div class="dp-grp">
          <a class="dp-active" href="/membership/deposit">充值</a>
          <a href="/membership/transfer">转换</a>
          <a href="/membership/withdraw">提现</a>
        </div>
        <div class="dp-grp">
          <a href="/membership/user-info">用户信息</a>
          <a href="/dfbiu/public/membership/vip.php">VIP特权</a>
        </div>
        <div class="dp-grp">
          <a href="/membership/deposit">我的卡包</a>
        </div>
      </nav>
    </aside>

    <!-- RIGHT -->
    <main class="dp-main">
      <div class="dp-card">
        <div class="dp-title">充值</div>

        <div class="dp-paytype">
          <button type="button" class="dp-tab dp-on">卡密支付</button>
          <button type="button" class="dp-tab">虚拟币支付</button>
          <button type="button" class="dp-tab">支付宝支付</button>
        </div>

        <div class="dp-channel">
          <div class="dp-empty">
            <div class="dp-cloud"></div>
            <div>暂无渠道</div>
          </div>
        </div>

        <form class="dp-amount" method="post" action="/membership/deposit">
          <div class="dp-lbl">支付金额</div>
          <div class="dp-preset" id="dp_preset">
            <button class="dp-btn dp-on" type="button" data-val="100">100</button>
            <button class="dp-btn" type="button" data-val="300">300</button>
            <button class="dp-btn" type="button" data-val="500">500</button>
            <button class="dp-btn" type="button" data-val="800">800</button>
            <button class="dp-btn" type="button" data-val="1000">1000</button>
            <button class="dp-btn" type="button" data-val="1200">1200</button>
          </div>

          <div class="dp-input">
            <input id="dp_amount" name="amount" type="number" inputmode="numeric" placeholder="输入金额" value="100" min="1" step="1" required>
          </div>

          <button class="dp-submit" type="submit">确定提交</button>
        </form>
      </div>
    </main>

  </div>
</div>

<script>
// Ізольований JS (уникати глобальних id/класів)
const dpPreset = document.getElementById('dp_preset');
const dpAmount = document.getElementById('dp_amount');

dpPreset.addEventListener('click', (e) => {
  const btn = e.target.closest('.dp-btn');
  if (!btn) return;
  dpPreset.querySelectorAll('.dp-btn').forEach(b=>b.classList.remove('dp-on'));
  btn.classList.add('dp-on');
  dpAmount.value = btn.dataset.val || '';
});

dpAmount.addEventListener('input', () => {
  const v = dpAmount.value.trim();
  dpPreset.querySelectorAll('.dp-btn').forEach(b=>{
    b.classList.toggle('dp-on', b.dataset.val === v);
  });
});
</script>
</body>
</html>
