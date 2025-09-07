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
// /dfbiu/public/wallet/transfer.
require_once $_SERVER['DOCUMENT_ROOT'].'/app/boot_session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>转换 - 钱包</title>

<style>
/* ===== Все стилі ізольовані під .tf-page ===== */
.tf-page{
  --tf-bg:#e6f1fb; --tf-card:#fff; --tf-ink:#2a3b4f; --tf-muted:#8aa0b9;
  --tf-line:#e7eef8; --tf-brand:#2e90ff; --tf-hover:#f3f7ff;
  --tf-shadow:0 12px 28px rgba(32,74,128,.10);
  font-family:Inter,system-ui,"Noto Sans SC",sans-serif;
  color:var(--tf-ink); background:var(--tf-bg);
}
.tf-page *{box-sizing:border-box}

/* layout */
.tf-wrap{width:min(1240px,96vw);margin:24px auto;display:grid;grid-template-columns:260px 1fr;gap:20px}

/* side */
.tf-side{background:#f1f6ff;border:1px solid var(--tf-line);border-radius:12px;box-shadow:var(--tf-shadow)}
.tf-prof{background:#2e90ff;border-radius:12px 12px 0 0;color:#fff;padding:24px 16px;display:flex;flex-direction:column;align-items:center}
.tf-ava{width:120px;height:120px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:700;margin-bottom:10px}
.tf-name{font-weight:600}
.tf-date{opacity:.9;font-size:12px;margin-top:6px}

.tf-qa{display:flex;gap:18px;justify-content:center;padding:14px 10px;background:#f7fbff}
.tf-qa .tf-qa-i{width:60px;text-align:center;text-decoration:none;color:var(--tf-ink)}
.tf-qa img{width:48px;height:48px;display:block;margin:0 auto 6px;border-radius:14px}
.tf-qa .tf-qa-t{font-size:12px;color:var(--tf-muted)}

.tf-nav{padding:10px 14px 16px}
.tf-nav .tf-grp{padding:8px 0;border-top:1px solid var(--tf-line)}
.tf-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;margin:4px 0;border-radius:8px;color:var(--tf-ink);text-decoration:none;font-size:14px}
.tf-nav a:hover{background:var(--tf-hover)}
.tf-nav a.tf-active{background:#e8f3ff;color:#1677ff;font-weight:600}

/* main */
.tf-card{background:var(--tf-card);border:1px solid var(--tf-line);border-radius:12px;box-shadow:var(--tf-shadow)}
.tf-head{padding:18px 22px;border-bottom:1px solid var(--tf-line);font-size:20px;font-weight:700}
.tf-body{padding:18px 22px}

/* balances row */
.tf-balances{display:flex;gap:20px;align-items:center;margin-bottom:14px}
.tf-bx{flex:1;background:#fff;border:1px solid var(--tf-line);border-radius:12px;padding:16px}
.tf-bx-title{color:var(--tf-muted);font-size:12px;display:flex;align-items:center;gap:6px}
.tf-bx-amt{font-weight:800;font-size:18px;margin-top:6px}
.tf-switch{margin-left:auto;display:flex;align-items:center;gap:8px;color:#666}
.tf-switch input{appearance:none;width:44px;height:24px;border-radius:999px;background:#e6eef9;position:relative;outline:none;cursor:pointer;border:1px solid var(--tf-line)}
.tf-switch input:checked{background:#a7d3ff}
.tf-switch input::after{content:"";position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.2s}
.tf-switch input:checked::after{left:23px}

/* transfer form */
.tf-row{display:flex;align-items:center;gap:14px;margin:16px 0}
.tf-select,.tf-input{height:40px;border:1px solid var(--tf-line);border-radius:8px;padding:0 12px;background:#fff}
.tf-select{min-width:180px}
.tf-input{min-width:220px}
.tf-arrow{font-size:22px;color:#3aa3ff;font-weight:800}
.tf-btn{border:1px solid var(--tf-line);background:#fff;border-radius:8px;height:40px;padding:0 18px;cursor:pointer}
.tf-btn:hover{border-color:var(--tf-brand);color:var(--tf-brand)}
.tf-btn-primary{background:var(--tf-brand);border-color:var(--tf-brand);color:#fff}
.tf-btn-primary:hover{filter:brightness(1.02)}

/* actions */
.tf-actions{display:flex;gap:10px;margin:6px 0 14px}

/* providers */
.tf-providers{display:grid;grid-template-columns:repeat(6,1fr);gap:12px}
.tf-p{background:#fff;border:1px solid var(--tf-line);border-radius:10px;padding:14px;text-align:center;color:#666}
.tf-p:hover{border-color:#cfe5ff}
.tf-p small{display:block;margin-top:6px;color:#999;font-size:12px}

/* responsive */
@media (max-width:1100px){ .tf-providers{grid-template-columns:repeat(4,1fr)} }
@media (max-width:920px){
  .tf-wrap{grid-template-columns:1fr}
  .tf-qa{justify-content:flex-start;padding-left:18px}
}
</style>
</head>
<body>

<?php require_once $_SERVER['DOCUMENT_ROOT'].'/public/includes/navbar6.php'; ?>

<div class="tf-page">
  <div class="tf-wrap">

    <!-- LEFT -->
    <aside class="tf-side">
      <div class="tf-prof">
        <div class="tf-ava">f</div>
       <div class="pf-username"><?=htmlspecialchars($user['username'] ?? '')?></div>
          <div class="pf-reg"><?=htmlspecialchars($user['reg_date'] ?? '')?></div>
      </div>

      <div class="tf-qa">
        <a class="tf-qa-i" href="/membership/deposit">
          <img src="/public/assets/img/deposit-DWbaeqin.png" alt="充值"><span class="tf-qa-t">充值</span>
        </a>
        <a class="tf-qa-i" href="/membership/transfer">
          <img src="/public/assets/img/transfer-Dp9ZMyl.png" alt="转换"><span class="tf-qa-t">转换</span>
        </a>
        <a class="tf-qa-i" href="/membership/withdraw">
          <img src="/public/assets/img/withdraw-pAIYbTu2.png" alt="提现"><span class="tf-qa-t">提现</span>
        </a>
      </div>

      <nav class="tf-nav">
        <div class="tf-grp">
          <a href="/membership/deposit">充值</a>
          <a class="tf-active" href="/membership/transfer">转换</a>
          <a href="/membership/withdraw">提现</a>
        </div>
        <div class="tf-grp">
          <a href="/membership/user-info">用户信息</a>
          <a href="/membership/withdraw">VIP特权</a>
        </div>
        <div class="tf-grp">
          <a href="/membership/withdraw">我的卡包</a>
        </div>
      </nav>
    </aside>

    <!-- RIGHT -->
    <main class="tf-main">
      <section class="tf-card">
        <div class="tf-head">转换</div>
        <div class="tf-body">

          <!-- balances -->
          <div class="tf-balances">
            <div class="tf-bx">
              <div class="tf-bx-title"><span>账户余额</span><span class="muted">⟳</span></div>
              <div class="tf-bx-amt">$0.0000</div>
            </div>
            <div class="tf-bx">
              <div class="tf-bx-title"><span>游戏余额</span><span class="muted">⟳</span></div>
              <div class="tf-bx-amt">$0.0000</div>
            </div>
            <label class="tf-switch"><input id="tf_manual" type="checkbox"><span>手动转换</span></label>
          </div>

          <!-- form -->
          <div class="tf-row">
            <select class="tf-select" id="tf_from">
              <option value="center">中心钱包</option>
              <option value="platform">平台钱包</option>
            </select>

            <div class="tf-arrow">➜</div>

            <select class="tf-select" id="tf_to">
              <option value="db">DB真人</option>
              <option value="mg">MG电子</option>
              <option value="pp">PP电子</option>
            </select>

            <input class="tf-input" id="tf_amount" type="number" inputmode="numeric" placeholder="请输入金额" min="1" step="1">
            <button class="tf-btn tf-btn-primary" id="tf_submit" type="button">确认转换</button>
          </div>

          <!-- quick actions -->
          <div class="tf-actions">
            <button class="tf-btn tf-btn-primary" type="button" id="tf_refresh">一键刷新</button>
            <button class="tf-btn tf-btn-primary" type="button" id="tf_recycle">一键回收</button>
          </div>

          <!-- providers -->
          <div class="tf-providers">
            <div class="tf-p">DB真人<small>0</small></div>
            <div class="tf-p">EVO 真人<small>0</small></div>
            <div class="tf-p">AG视讯<small>0</small></div>
            <div class="tf-p">BB真人<small>0</small></div>
            <div class="tf-p">BG真人<small>0</small></div>
            <div class="tf-p">VR竞赛<small>0</small></div>
            <div class="tf-p">SBO体育<small>0</small></div>
            <div class="tf-p">POLY保利体育<small>0</small></div>
            <div class="tf-p">DS88电子<small>0</small></div>
            <div class="tf-p">VG棋牌<small>0</small></div>
            <div class="tf-p">PP电子<small>0</small></div>
            <div class="tf-p">MG电子<small>0</small></div>
          </div>

        </div>
      </section>
    </main>

  </div>
</div>

<script>
// Легкий, ізольований JS під tf-*
document.getElementById('tf_submit').addEventListener('click', () => {
  const from = document.getElementById('tf_from').value;
  const to   = document.getElementById('tf_to').value;
  const amt  = document.getElementById('tf_amount').value.trim();
  if (!amt || +amt <= 0) { alert('请输入正确的金额'); return; }
  // TODO: заміни на реальний POST до бекенду
  alert(`转换成功\nFrom: ${from} ➜ To: ${to}\nAmount: ${amt}`);
});

document.getElementById('tf_refresh').addEventListener('click', () => {
  // TODO: AJAX refresh balances
  alert('已刷新余额');
});
document.getElementById('tf_recycle').addEventListener('click', () => {
  // TODO: AJAX recycle balances
  alert('一键回收完成');
});
</script>
</body>
</html>
