<?php
// profile_redesign.php — scoped, conflict-free profile UI
// Drop-in page: keeps CSS scoped under .pf-page to avoid conflicts with navbar/site.
// Assumes you already authenticated the user and have a $user array.
// Safe fallbacks are provided so the page renders even without PHP data.
// Підключаємо твій бут — шлях НЕ чіпаю
require_once __DIR__ . '/../../app/boot_session.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('BASE_URL')) define('BASE_URL', '/');

if (!function_exists('redirect')) {
  function redirect(string $path): never {
    if (str_starts_with($path, 'http')) header('Location: '.$path);
    else header('Location: '.rtrim(BASE_URL,'/').'/'.ltrim($path,'/'));
    exit;
  }
}

if (!function_exists('db')) {
  function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $host = defined('DB_HOST') ? DB_HOST : 'srv1969.hstgr.io';
    $name = defined('DB_NAME') ? DB_NAME : 'u140095755_questhub';
    $user = defined('DB_USER') ? DB_USER : 'u140095755_darmas';
    $pass = defined('DB_PASS') ? DB_PASS : '@Corp9898';
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
  }
}

if (empty($_SESSION['uid'])) {
  $back = $_SERVER['REQUEST_URI'] ?? (BASE_URL.'/');
  redirect(BASE_URL.'/public/login.php?next='.rawurlencode($back));
}

$pdo = db();

/** helpers */
function table_columns(PDO $pdo, string $table): array {
  $cols = [];
  foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) $cols[] = $r['Field'];
  return $cols;
}
function hascol(array $cols, string $name): bool { return in_array($name, $cols, true); }

$cols = table_columns($pdo, 'users');

/** читаємо поточного користувача */
$select = ['id','username'];
foreach (['email','mail'] as $f) if (hascol($cols,$f)) { $select[] = "`$f` AS email"; break; }
foreach (['realname','fullname','name'] as $f) if (hascol($cols,$f)) { $select[] = "`$f` AS realname"; break; }
foreach (['gender','sex'] as $f) if (hascol($cols,$f)) { $select[] = "`$f` AS gender"; break; }
foreach (['phone','mobile','tel'] as $f) if (hascol($cols,$f)) { $select[] = "`$f` AS phone"; break; }
foreach (['birthday','dob'] as $f) if (hascol($cols,$f)) { $select[] = "`$f` AS birthday"; break; }
foreach (['qq'] as $f) if (hascol($cols,$f)) { $select[] = "`$f` AS qq"; break; }
foreach (['telegram','tg'] as $f) if (hascol($cols,$f)) { $select[] = "`$f` AS telegram"; break; }
foreach (['currency','user_currency','curr'] as $f) if (hascol($cols,$f)) { $select[] = "`$f` AS currency"; break; }
foreach (['balance','user_balance','wallet','money','amount','credits','coins','cash','score'] as $f)
  if (hascol($cols,$f)) { $select[] = "`$f` AS balance"; break; }
$hasPassword = hascol($cols, 'password');

$sql = "SELECT ".implode(', ',$select)." FROM `users` WHERE id=? LIMIT 1";
$st  = $pdo->prepare($sql);
$st->execute([$_SESSION['uid']]);
$me = $st->fetch();
if (!$me) redirect(BASE_URL.'/public/login.php');

/** обробка форм */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  // БАЗОВІ ДАНІ
  if (isset($_POST['action']) && $_POST['action']==='save_basic') {
    $sets = []; $vals = [];

    // ОНОВЛЮЄМО ЛИШЕ ТІ ПОЛЯ, ЯКІ ПРИЙШЛИ В POST + існують у БД
    if (isset($_POST['realname']) && (hascol($cols,'realname') || hascol($cols,'fullname') || hascol($cols,'name'))) {
      $field = hascol($cols,'realname') ? 'realname' : (hascol($cols,'fullname')?'fullname':'name');
      $sets[] = "`$field` = ?"; $vals[] = trim($_POST['realname']);
    }
    if (isset($_POST['gender']) && (hascol($cols,'gender') || hascol($cols,'sex'))) {
      $field = hascol($cols,'gender') ? 'gender' : 'sex';
      $g = $_POST['gender'];
      $g = in_array($g, ['male','female','other',''], true) ? $g : '';
      $sets[] = "`$field` = ?"; $vals[] = $g;
    }
    if (isset($_POST['phone']) && (hascol($cols,'phone') || hascol($cols,'mobile') || hascol($cols,'tel'))) {
      $field = hascol($cols,'phone') ? 'phone' : (hascol($cols,'mobile')?'mobile':'tel');
      $sets[] = "`$field` = ?"; $vals[] = trim($_POST['phone']);
    }
    if (isset($_POST['birthday']) && (hascol($cols,'birthday') || hascol($cols,'dob'))) {
      $field = hascol($cols,'birthday') ? 'birthday' : 'dob';
      $sets[] = "`$field` = ?"; $vals[] = trim($_POST['birthday']);
    }
    if (isset($_POST['qq']) && hascol($cols,'qq')) {
      $sets[]="`qq`=?"; $vals[] = trim($_POST['qq']);
    }
    if (isset($_POST['telegram']) && (hascol($cols,'telegram') || hascol($cols,'tg'))) {
      $field = hascol($cols,'telegram') ? 'telegram' : 'tg';
      $sets[]="`$field`=?"; $vals[] = trim($_POST['telegram']);
    }
    if (isset($_POST['email']) && (hascol($cols,'email') || hascol($cols,'mail'))) {
      $field = hascol($cols,'email') ? 'email' : 'mail';
      $sets[]="`$field`=?"; $vals[] = trim($_POST['email']);
    }

    if ($sets) {
      $vals[] = $_SESSION['uid'];
      $upd = $pdo->prepare("UPDATE `users` SET ".implode(', ',$sets)." WHERE id=? LIMIT 1");
      $upd->execute($vals);
    }
    redirect($_SERVER['REQUEST_URI']); // PRG
  }

  // ЗМІНА ПАРОЛЯ
  if ($hasPassword && isset($_POST['action']) && $_POST['action']==='change_password') {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    if ($new !== '' && strlen($new) >= 6) {
      $ps = $pdo->prepare("SELECT `password` FROM `users` WHERE id=?");
      $ps->execute([$_SESSION['uid']]);
      $ph = (string)($ps->fetch()['password'] ?? '');
      $ok = $ph ? password_verify($old, $ph) : ($old === $ph);
      if ($ok) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd  = $pdo->prepare("UPDATE `users` SET `password`=? WHERE id=? LIMIT 1");
        $upd->execute([$hash, $_SESSION['uid']]);
      }
    }
    redirect($_SERVER['REQUEST_URI']);
  }
}

/** нормалізація для відображення */
$u = [
  'username' => $me['username'] ?? '',
  'email'    => $me['email']    ?? '',
  'realname' => $me['realname'] ?? '',
  'gender'   => $me['gender']   ?? '',
  'phone'    => $me['phone']    ?? '',
  'birthday' => $me['birthday'] ?? '',
  'qq'       => $me['qq']       ?? '',
  'telegram' => $me['telegram'] ?? '',
  'currency' => strtoupper(trim((string)($me['currency'] ?? 'USD'))),
  'balance'  => (float)($me['balance'] ?? 0),
];

function money_symbol(string $code): string {
  static $m=['USD'=>'$','EUR'=>'€','PLN'=>'zł','UAH'=>'₴','GBP'=>'£','CNY'=>'¥','JPY'=>'¥'];
  return $m[strtoupper($code)] ?? '';
}
function fmt_money(float $amt, string $code): string {
  $sym = money_symbol($code);
  $num = number_format($amt, 2, '.', ' ');
  return $sym ? $sym.$num : $num.' '.$code;
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

$logout_url = BASE_URL.'/public/logout.php?next='.rawurlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL.'/'));
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>用户信息</title>
  <style>
  /* ===== Profile page (scoped) ===== */
  .pf-page{--bg:#eaf4ff;--card:#ffffff;--muted:#7e8aa6;--line:#e7eef8;--brand:#2e90ff;--ink:#1b2430;--chip:#eef6ff;--shadow:0 12px 28px rgba(32,74,128,.10);--sidebar:#f5faff}
  .pf-page{background:var(--bg);color:var(--ink);font-family:Inter,system-ui,"Noto Sans SC",sans-serif;min-height:100vh}
  .pf-wrap{width:min(1200px,96vw);margin:24px auto;display:grid;grid-template-columns:280px 1fr;gap:24px}
  .pf-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}
  .pf-blue{background:linear-gradient(180deg,#0e8fff 0%,#2ea4ff 100%);color:#fff}
  .pf-h{padding:20px 20px 16px}
  .pf-body{padding:16px 20px 20px}
  .pf-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.35);color:#fff;padding:6px 10px;border-radius:10px;font-size:12px}
  .pf-username{font-weight:600;font-size:16px;letter-spacing:.2px}
  .pf-reg{opacity:.9;font-size:12px;margin-top:6px}
  .pf-kpis{display:flex;gap:12px;margin-top:12px}
  .pf-kpis .pf-chip{backdrop-filter:saturate(120%) blur(2px)}
  /* left sidebar menu */
  .pf-side{position:sticky;top:16px;height:fit-content}
  .pf-menu{display:flex;flex-direction:column}
  .pf-menu .pf-tab{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;color:#2a3b4f;text-decoration:none;border:1px solid transparent}
  .pf-menu .pf-tab:hover{background:#f7fbff;border-color:var(--line)}
  .pf-menu h5{font-weight:600;color:#506380;margin:10px 14px}
  .pf-menu .pf-tab.active{background:#f5faff;border-color:var(--line)}
  .pf-ico{width:18px;height:18px;color:#579dff;display:inline-flex}
  .pf-sep{height:1px;background:var(--line);margin:10px 0}
  /* main content */
  .pf-main .pf-h{display:flex;align-items:center;justify-content:space-between}
  .pf-title{font-size:18px;font-weight:700;color:#1a2b44}
  .pf-sub{font-size:13px;color:#6b7a96;margin-top:2px}
  /* form */
  .pf-form{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:14px}
  .pf-field{display:flex;flex-direction:column;gap:6px}
  .pf-label{font-size:12px;color:#6b7a96}
  .pf-input, .pf-select, .pf-date{height:38px;border:1px solid var(--line);border-radius:10px;background:#fff;padding:0 12px;outline:0;font-size:14px;transition:border-color .15s, box-shadow .15s;width:100%}
  .pf-input:focus, .pf-select:focus, .pf-date:focus{border-color:#cfe3ff;box-shadow:0 0 0 3px rgba(46,144,255,.15)}
  .pf-help{font-size:12px;color:#6b7a96;display:flex;align-items:center;gap:6px}
  .pf-help::before{content:"i";display:inline-grid;place-items:center;width:16px;height:16px;border-radius:50%;border:1px solid #a9c8ff;color:#579dff;font-weight:700;font-size:12px}
  .pf-actions{margin-top:8px}
  .pf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:38px;padding:0 18px;border-radius:12px;border:1px solid transparent;background:var(--brand);color:#fff;font-weight:600;cursor:pointer}
  .pf-btn:hover{filter:brightness(1.02)}
  .pf-btn:active{transform:translateY(1px)}
  /* security rows */
  .pf-sec{margin-top:16px;display:flex;flex-direction:column;gap:12px}
  .pf-row{display:flex;align-items:center;justify-content:space-between;border:1px solid var(--line);background:#fff;border-radius:12px;padding:12px 14px}
  .pf-row .l{display:flex;align-items:center;gap:10px}
  .pf-row .r{display:flex;align-items:center;gap:10px}
  .pf-tag{font-size:12px;background:var(--chip);border:1px solid #dbe8f7;color:#3972c1;padding:4px 8px;border-radius:999px}
  .pf-btn-lite{height:32px;padding:0 14px;border-radius:10px;background:#2e90ff;color:#fff;border:0;font-weight:600;cursor:pointer}
  .pf-btn-lite.alt{background:#3c95ff}
  /* responsive */
  @media (max-width: 980px){
    .pf-wrap{grid-template-columns:1fr}
    .pf-side{position:static}
    .pf-form{grid-template-columns:1fr}
  }
  
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
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/public/includes/navbar6.php'; ?>
<body class="pf-page">
  <main class="pf-wrap">

    <!-- Left column -->
    <aside class="pf-side">
      <div class="pf-card pf-blue">
        <div class="pf-h">
          <div class="pf-username"><?=htmlspecialchars($user['username'] ?? '')?></div>
          <div class="pf-reg"><?=htmlspecialchars($user['reg_date'] ?? '')?></div>
          <div class="pf-kpis">
            <span class="pf-chip">
              <!-- wallet icon -->
              <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 12V7a2 2 0 0 0-2-2H6a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3h12a2 2 0 0 0 2-2v-5z"/>
                <path d="M18 12h2"/>
              </svg>
              充值
            </span>
            <span class="pf-chip">
              <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M7 7h10v10H7z"/><path d="M3 7h4M17 7h4M3 17h4M17 17h4"/>
              </svg>
              转帐
            </span>
            <span class="pf-chip">
              <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 8v8M8 12h8"/><circle cx="12" cy="12" r="10"/>
              </svg>
              提现
            </span>
          </div>
        </div>
      </div>
      
            <!-- QUICK ACTIONS (充值 / 转换 / 提现) -->
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


      <div class="pf-card pf-body pf-menu" style="margin-top:12px">
        <a class="pf-tab active" href="#">
          <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          用户信息
        </a>
        <a class="pf-tab" href="#vip">
          <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 7h18l-3 10H6L3 7z"/><path d="M7 7l5 6 5-6"/>
          </svg>
          VIP特权
        </a>
        <a class="pf-tab" href="#cards">
          <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="5" width="20" height="14" rx="2"/>
            <path d="M2 10h20"/>
          </svg>
          我的卡包
        </a>
      </div>
    </aside>

    <!-- Right column -->
    <section class="pf-main">
      <div class="pf-card">
        <div class="pf-h">
          <div>
            <div class="pf-title">用户信息</div>
            <div class="pf-sub">基本信息</div>
          </div>
        </div>
        <div class="pf-body">
          <form class="pf-form" action="" method="post">
            <div class="pf-field">
              <label class="pf-label">姓名</label>
              <input class="pf-input" name="realname" placeholder="请输入真实姓名" value="<?=htmlspecialchars($user['realname'] ?? '')?>">
            </div>

            <div class="pf-field">
              <label class="pf-label">性别</label>
              <select class="pf-select" name="gender">
                <option value="" <?=empty($user['gender'])?'selected':''?>>请选择</option>
                <option value="male" <?=($user['gender']??'')==='male'?'selected':''?>>男</option>
                <option value="female" <?=($user['gender']??'')==='female'?'selected':''?>>女</option>
              </select>
            </div>

            <div class="pf-field">
              <label class="pf-label">手机号</label>
              <input class="pf-input" name="phone" placeholder="请输入手机号码" value="<?=htmlspecialchars($user['phone'] ?? '')?>">
            </div>
            
            

            <div class="pf-field">
              <label class="pf-label">QQ</label>
              <input class="pf-input" name="qq" placeholder="请输入QQ" value="<?=htmlspecialchars($user['qq'] ?? '')?>">
            </div>

            <div class="pf-field">
              <label class="pf-label">telegram</label>
              <input class="pf-input" name="telegram" placeholder="请输入Telegram" value="<?=htmlspecialchars($user['telegram'] ?? '')?>">
            </div>

            <div class="pf-field" style="grid-column:1 / -1">
              <label class="pf-label">电子邮件</label>
              <input class="pf-input" name="email" placeholder="请输入电子邮箱" value="<?=htmlspecialchars($user['email'] ?? '')?>">
            </div>
          </form>

          <div class="pf-actions">
            <button class="pf-btn" type="submit">提交</button>
          </div>

          <div style="margin-top:10px" class="pf-help">其他用户将不可见您的基本信息</div>

          <div class="pf-sec">
            <div class="pf-row">
              <div class="l">
                <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 5h18v14H3z"/><path d="M7 10h10M7 14h10"/>
                </svg>
                <div>手机号</div>
              </div>
              <div class="r">
                <?php if (!empty($user['phone_verified'])): ?>
                  <span class="pf-tag">已验证</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="pf-row">
              <div class="l">
                <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16v16H4z"/><path d="M4 9h16"/>
                </svg>
                <div>电子邮件</div>
              </div>
              <div class="r">
                <button class="pf-btn-lite">绑定</button>
              </div>
            </div>

            <div class="pf-row">
              <div class="l">
                <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 11v6"/><path d="M9 11V9a3 3 0 1 1 6 0v2"/><rect x="5" y="11" width="14" height="9" rx="2"/>
                </svg>
                <div>密码</div>
              </div>
              <div class="r">
                <button class="pf-btn-lite alt">修改</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>
  <script>
document.addEventListener('DOMContentLoaded', function(){
  const input = document.querySelector('input[name="birthday"]');
  const out = document.getElementById('birthdayCn');
  if (!input || !out) return;

  function upd(){
    const v = input.value;
    if (!v){ out.textContent = ''; return; }
    const d = new Date(v);
    out.textContent = isNaN(d)
      ? v
      : d.toLocaleDateString('zh-CN', { year:'numeric', month:'long', day:'numeric' });
  }
  input.addEventListener('input',  upd);
  input.addEventListener('change', upd);
});
</script>
</body>
</html>