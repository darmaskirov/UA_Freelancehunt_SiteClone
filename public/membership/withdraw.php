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
// /dfbiu/public/wallet/withdraw.php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/boot_session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>提现 - 钱包</title>
<style>
/* —— усе під .wd-page, щоб не ламати navbar6 —— */
.wd-page{
  --wd-bg:#e6f1fb; --wd-card:#fff; --wd-ink:#2a3b4f; --wd-muted:#8aa0b9;
  --wd-line:#e7eef8; --wd-brand:#2e90ff; --wd-hover:#f3f7ff;
  --wd-shadow:0 12px 28px rgba(32,74,128,.10);
  font-family:Inter,system-ui,"Noto Sans SC",sans-serif;
  color:var(--wd-ink); background:var(--wd-bg);
}
.wd-page *{box-sizing:border-box}

.wd-wrap{width:min(1240px,96vw);margin:24px auto;display:grid;grid-template-columns:260px 1fr;gap:20px}

/* SIDE */
.wd-side{background:#f1f6ff;border:1px solid var(--wd-line);border-radius:12px;box-shadow:var(--wd-shadow)}
.wd-prof{background:#2e90ff;border-radius:12px 12px 0 0;color:#fff;padding:24px 16px;display:flex;flex-direction:column;align-items:center}
.wd-ava{width:120px;height:120px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:700;margin-bottom:10px}
.wd-name{font-weight:600}.wd-date{opacity:.9;font-size:12px;margin-top:6px}

.wd-qa{display:flex;gap:18px;justify-content:center;padding:14px 10px;background:#f7fbff}
.wd-qa .wd-qa-i{width:60px;text-align:center;text-decoration:none;color:var(--wd-ink)}
.wd-qa img{width:48px;height:48px;display:block;margin:0 auto 6px;border-radius:14px}
.wd-qa .wd-qa-t{font-size:12px;color:var(--wd-muted)}

.wd-nav{padding:10px 14px 16px}
.wd-nav .wd-grp{padding:8px 0;border-top:1px solid var(--wd-line)}
.wd-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;margin:4px 0;border-radius:8px;color:var(--wd-ink);text-decoration:none;font-size:14px}
.wd-nav a:hover{background:var(--wd-hover)}
.wd-nav a.wd-active{background:#e8f3ff;color:#1677ff;font-weight:600}

/* MAIN */
.wd-card{background:var(--wd-card);border:1px solid var(--wd-line);border-radius:12px;box-shadow:var(--wd-shadow);padding:22px}
.wd-title{font-size:20px;font-weight:700;margin:2px 0 18px}

/* tabs */
.wd-tabs{display:flex;gap:12px;margin-bottom:18px}
.wd-tab{border:1px solid var(--wd-line);background:#f9fbff;border-radius:10px;padding:10px 14px;font-size:13px;color:#9bb0c9;cursor:pointer}
.wd-tab.wd-on{color:#2b6cb0;border-color:#d6e6fb;background:#eef6ff}

/* channel */
.wd-channel{min-height:110px;border:1px dashed #dbe8f7;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#9bb0c9;margin-bottom:20px}
.wd-channel .wd-empty{display:flex;flex-direction:column;align-items:center;gap:6px}
.wd-channel .wd-cloud{width:28px;height:20px;border-radius:4px;background:#e8f3ff}

/* form */
.wd-form .wd-label{margin:2px 0 10px;font-weight:600}
.wd-grid{display:grid;grid-template-columns:repeat(2,minmax(260px,380px));gap:14px 24px;margin-bottom:14px}
.wd-input{height:40px;border:1px solid #e6edf8;border-radius:8px;padding:0 12px;background:#fff;font-size:14px;width:100%}
.wd-preset{display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.wd-btn{min-width:78px;height:38px;padding:0 14px;border:1px solid #e6edf8;background:#fff;border-radius:10px;font-size:14px;color:#9bb0c9;cursor:pointer}
.wd-btn.wd-on{background:var(--wd-brand);color:#fff;border-color:var(--wd-brand);box-shadow:0 6px 14px rgba(46,144,255,.25)}
.wd-submit{margin-top:6px;width:min(380px,90%);height:40px;border:none;border-radius:8px;background:var(--wd-brand);color:#fff;font-weight:600;cursor:pointer}
.wd-submit:hover{filter:brightness(1.02)}

@media (max-width:920px){
  .wd-wrap{grid-template-columns:1fr}
  .wd-qa{justify-content:flex-start;padding-left:18px}
  .wd-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<?php require_once $_SERVER['DOCUMENT_ROOT'].'/public/includes/navbar6.php'; ?>

<div class="wd-page">
  <div class="wd-wrap">

    <!-- LEFT -->
    <aside class="wd-side">
      <div class="wd-prof">
        <div class="wd-ava">f</div>
       <div class="pf-username"><?=htmlspecialchars($user['username'] ?? '')?></div>
          <div class="pf-reg"><?=htmlspecialchars($user['reg_date'] ?? '')?></div>
      </div>

      <div class="wd-qa">
        <a class="wd-qa-i" href="/membership/deposit">
          <img src="/public/assets/img/deposit-DWbaeqin.png" alt="充值"><span class="wd-qa-t">充值</span>
        </a>
        <a class="wd-qa-i" href="/membership/transfer">
          <img src="/public/assets/img/transfer-Dp9ZMyl.png" alt="转换"><span class="wd-qa-t">转换</span>
        </a>
        <a class="wd-qa-i" href="/membership/withdraw">
          <img src="/public/assets/img/withdraw-pAIYbTu2.png" alt="提现"><span class="wd-qa-t">提现</span>
        </a>
      </div>

      <nav class="wd-nav">
        <div class="wd-grp">
          <a href="/membership/deposit">充值</a>
          <a href="/membership/transfer">转换</a>
          <a class="wd-active" href="/membership/withdraw">提现</a>
        </div>
        <div class="wd-grp">
          <a href="/membership/transfer">用户信息</a>
          <a href="/membership/transfer">VIP特权</a>
        </div>
        <div class="wd-grp">
          <a href="/membership/transfer">我的卡包</a>
        </div>
      </nav>
    </aside>

    <!-- RIGHT -->
    <main class="wd-main">
      <section class="wd-card">
        <div class="wd-title">提现</div>

        <!-- tabs -->
        <div class="wd-tabs" id="wd_tabs">
          <button type="button" class="wd-tab wd-on" data-mode="bank">银行卡提现</button>
          <button type="button" class="wd-tab" data-mode="usdt">虚拟币提现</button>
          <button type="button" class="wd-tab" data-mode="alipay">支付宝提现</button>
        </div>

        <!-- channel placeholder -->
        <div class="wd-channel">
          <div class="wd-empty">
            <div class="wd-cloud"></div>
            <div>暂无渠道</div>
          </div>
        </div>

        <!-- form -->
        <form class="wd-form" method="post" action="/dfbiu/public/wallet/withdraw_submit.php" id="wd_form">
          <div class="wd-label">提现信息</div>
          <div class="wd-grid">
            <input class="wd-input" id="wd_realname" name="realname" type="text" placeholder="持卡人姓名 / 真实姓名" autocomplete="name">
            <input class="wd-input" id="wd_account" name="account" type="text" placeholder="收款账号（银行卡号）" autocomplete="off">
            <input class="wd-input" id="wd_bank" name="bank" type="text" placeholder="开户行（仅银行卡）" autocomplete="organization">
            <input class="wd-input" id="wd_remark" name="remark" type="text" placeholder="备注（可选）">
          </div>

          <div class="wd-label">提现金额</div>
          <div class="wd-preset" id="wd_preset">
            <button class="wd-btn wd-on" type="button" data-val="100">100</button>
            <button class="wd-btn" type="button" data-val="300">300</button>
            <button class="wd-btn" type="button" data-val="500">500</button>
            <button class="wd-btn" type="button" data-val="800">800</button>
            <button class="wd-btn" type="button" data-val="1000">1000</button>
            <button class="wd-btn" type="button" data-val="1200">1200</button>
          </div>

          <input class="wd-input" style="width:min(380px,90%);margin-bottom:8px" id="wd_amount" name="amount" type="number" inputmode="numeric" placeholder="输入金额" value="100" min="1" step="1" required>

          <button class="wd-submit" type="submit">确定提交</button>
        </form>
      </section>
    </main>

  </div>
</div>

<script>
// ізольований JS (wd-*)
const tabs = document.getElementById('wd_tabs');
const account = document.getElementById('wd_account');
const bank = document.getElementById('wd_bank');

tabs.addEventListener('click', e=>{
  const t = e.target.closest('.wd-tab'); if(!t) return;
  tabs.querySelectorAll('.wd-tab').forEach(x=>x.classList.remove('wd-on'));
  t.classList.add('wd-on');
  const mode = t.dataset.mode;
  // міняємо підказки під тип виводу
  if (mode==='bank'){
    account.placeholder = '收款账号（银行卡号）';
    bank.disabled = false; bank.placeholder = '开户行（仅银行卡）';
  }else if(mode==='usdt'){
    account.placeholder = '收款地址（USDT-TRC20/ERC20）';
    bank.disabled = true; bank.placeholder = '虚拟币无需填写开户行';
  }else{
    account.placeholder = '收款账号（支付宝）';
    bank.disabled = true; bank.placeholder = '支付宝无需填写开户行';
  }
});

const preset = document.getElementById('wd_preset');
const amount = document.getElementById('wd_amount');

preset.addEventListener('click', (e) => {
  const btn = e.target.closest('.wd-btn'); if (!btn) return;
  preset.querySelectorAll('.wd-btn').forEach(b=>b.classList.remove('wd-on'));
  btn.classList.add('wd-on');
  amount.value = btn.dataset.val || '';
});

amount.addEventListener('input', () => {
  const v = amount.value.trim();
  preset.querySelectorAll('.wd-btn').forEach(b=>{
    b.classList.toggle('wd-on', b.dataset.val === v);
  });
});
</script>
</body>
</html>
