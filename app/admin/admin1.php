<?php
/******************************
 * dfbiu admin panel (single file)
 * Requirements: PHP 8+, PDO MySQL
 ******************************/

session_start();

/** CONFIG: Підключення до БД */
$DB_HOST = '127.0.0.1';
$DB_NAME = 'dfbiu_clone';
$DB_USER = 'root';
$DB_PASS = ''; // постав свій пароль

/** Під’єднання PDO */
try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB connection error.";
  exit;
}

/** --- Простенька авторизація адміна ---
 * Очікуємо, що в сесії лежить user_id після твого логіну.
 * Перевіряємо роль у БД.
 */
function current_admin(PDO $pdo): ?array {
  if (empty($_SESSION['user_id'])) return null;
  $st = $pdo->prepare("SELECT id, username, role FROM users WHERE id=? LIMIT 1");
  $st->execute([$_SESSION['user_id']]);
  $u = $st->fetch();
  if (!$u || $u['role'] !== 'admin') return null;
  return $u;
}
$admin = current_admin($pdo);
// На час тесту можеш розкоментувати це, щоб увійти під конкретним id:
// $_SESSION['user_id'] = 1; $admin = current_admin($pdo);

/** CSRF */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function require_csrf() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok) { http_response_code(403); echo "Bad CSRF."; exit; }
  }
}

/** Утиліти */
function flash($msg, $type='success') {
  $_SESSION['flash'][] = ['t'=>$type, 'm'=>$msg];
}
function get_flash(): array {
  $msgs = $_SESSION['flash'] ?? [];
  unset($_SESSION['flash']);
  return $msgs;
}
function admin_log(PDO $pdo, $admin_id, $action, $details=null) {
  $st = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?,?,?)");
  $st->execute([$admin_id, $action, $details]);
}

/** Баланс: апсерт та транзакція (поважає унікальний user_id у balances) */
function upsert_balance(PDO $pdo, int $user_id, string $currency, float $delta, string $txType, int $admin_id): void {
  // забираємо поточний баланс
  $pdo->beginTransaction();
  try {
    // якщо запису немає — створимо з 0
    $st = $pdo->prepare("SELECT id, amount, currency FROM balances WHERE user_id=? LIMIT 1");
    $st->execute([$user_id]);
    $row = $st->fetch();

    if (!$row) {
      $st = $pdo->prepare("INSERT INTO balances (user_id, currency, amount) VALUES (?,?,0.00)");
      $st->execute([$user_id, $currency]);
      $balance = 0.00;
    } else {
      // Якщо валюта відрізняється — оновлюємо на нову (один запис на користувача)
      if ($row['currency'] !== $currency) {
        $st = $pdo->prepare("UPDATE balances SET currency=? WHERE user_id=?");
        $st->execute([$currency, $user_id]);
      }
      $balance = (float)$row['amount'];
    }

    $newAmount = round($balance + $delta, 2);
    if ($newAmount < 0) throw new RuntimeException("Insufficient funds for withdraw.");

    $st = $pdo->prepare("UPDATE balances SET amount=? WHERE user_id=?");
    $st->execute([$newAmount, $user_id]);

    // транзакція
    $st = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status) VALUES (?,?,?, 'completed')");
    $st->execute([$user_id, $txType, abs($delta)]);

    // лог
    admin_log($pdo, $admin_id, "balance_$txType", json_encode([
      'user_id'=>$user_id, 'delta'=>$delta, 'currency'=>$currency, 'newAmount'=>$newAmount
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/** Дії (mini router) */
$action = $_GET['action'] ?? 'dashboard';

if (!$admin) {
  // Проста заглушка, якщо не адмін/не увійшов
  echo "<!doctype html><meta charset='utf-8'><style>body{font-family:system-ui;padding:24px}</style>";
  echo "<h1>Адмінка</h1><p>Увійди як <b>admin</b>. Знайдений user_id у сесії повинен мати role=admin.</p>";
  echo "<p>Підтвердження структури БД дивись у дампі (таблиці users/balances/transactions/admin_logs/sessions/profiles). </p>";
  exit;
}

/** POST-хендлери */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();

  try {
    if ($action === 'user_create') {
      $username = trim($_POST['username'] ?? '');
      $email    = trim($_POST['email'] ?? '');
      $password = $_POST['password'] ?? '';
      $currency = $_POST['currency'] ?? 'USD';
      $role     = $_POST['role'] ?? 'user';
      $status   = $_POST['status'] ?? 'active';

      if ($username === '' || $email === '' || $password === '') throw new RuntimeException("Поля не можуть бути порожні.");
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $st = $pdo->prepare("INSERT INTO users (username, email, currency, password_hash, role, status) VALUES (?,?,?,?,?,?)");
      $st->execute([$username, $email, $currency, $hash, $role, $status]);

      $uid = (int)$pdo->lastInsertId();
      // створимо пустий профіль (опційно)
      $pdo->prepare("INSERT INTO profiles (user_id) VALUES (?)")->execute([$uid]);

      admin_log($pdo, $admin['id'], 'user_create', json_encode(['user_id'=>$uid,'username'=>$username]));
      flash("Користувача створено (#$uid).");
      header("Location: admin.php?action=users&created=$uid"); exit;
    }

    if ($action === 'user_update') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $role = $_POST['role'] ?? 'user';
      $status = $_POST['status'] ?? 'active';
      $currency = $_POST['currency'] ?? 'USD';

      $st = $pdo->prepare("UPDATE users SET role=?, status=?, currency=? WHERE id=?");
      $st->execute([$role, $status, $currency, $uid]);

      admin_log($pdo, $admin['id'], 'user_update', json_encode(['user_id'=>$uid, 'role'=>$role, 'status'=>$status, 'currency'=>$currency]));
      flash("Користувача #$uid оновлено.");
      header("Location: admin.php?action=user_edit&id=$uid"); exit;
    }

    if ($action === 'user_pass_reset') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $newpass = $_POST['new_password'] ?? '';
      if (strlen($newpass) < 6) throw new RuntimeException("Пароль замалий.");
      $hash = password_hash($newpass, PASSWORD_BCRYPT);
      $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);

      admin_log($pdo, $admin['id'], 'user_pass_reset', json_encode(['user_id'=>$uid]));
      flash("Пароль користувачу #$uid змінено.");
      header("Location: admin.php?action=user_edit&id=$uid"); exit;
    }

    if ($action === 'balance') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $op  = $_POST['op'] ?? 'deposit'; // deposit|withdraw|bonus
      $amt = (float)($_POST['amount'] ?? 0);
      $currency = $_POST['currency'] ?? 'USD';
      if ($amt <= 0) throw new RuntimeException("Сума має бути > 0.");
      $deltaByOp = [
        'deposit' => +$amt,
        'bonus'   => +$amt,
        'withdraw'=> -$amt,
      ];
      if (!isset($deltaByOp[$op])) throw new RuntimeException("Невідома операція.");
      upsert_balance($pdo, $uid, $currency, $deltaByOp[$op], $op, $admin['id']);
      flash("Операцію виконано: $op $amt $currency для користувача #$uid.");
      header("Location: admin.php?action=user_edit&id=$uid"); exit;
    }

  } catch (Throwable $e) {
    flash("Помилка: ".$e->getMessage(), 'error');
    header("Location: admin.php?action=".urlencode($action)); exit;
  }
}

/** Дані для відображення */
function get_user(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $u = $st->fetch();
  if (!$u) return null;

  $b = $pdo->prepare("SELECT amount, currency FROM balances WHERE user_id=? LIMIT 1");
  $b->execute([$id]);
  $bal = $b->fetch() ?: ['amount'=>0.00,'currency'=>$u['currency']];

  return ['user'=>$u, 'balance'=>$bal];
}

function list_users(PDO $pdo, string $q='', int $limit=50): array {
  if ($q !== '') {
    $st = $pdo->prepare("SELECT id, username, email, role, status, currency, created_at FROM users
                         WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT ?");
    $st->execute(["%$q%","%$q%",$limit]);
  } else {
    $st = $pdo->prepare("SELECT id, username, email, role, status, currency, created_at FROM users ORDER BY id DESC LIMIT ?");
    $st->execute([$limit]);
  }
  return $st->fetchAll();
}

function list_user_tx(PDO $pdo, int $uid, int $limit=100): array {
  $st = $pdo->prepare("SELECT id, type, amount, status, created_at FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT ?");
  $st->execute([$uid, $limit]);
  return $st->fetchAll();
}

function list_admin_logs(PDO $pdo, int $limit=100): array {
  $st = $pdo->prepare("SELECT a.id, a.action, a.details, a.created_at, u.username AS admin_name
                       FROM admin_logs a JOIN users u ON u.id=a.admin_id
                       ORDER BY a.id DESC LIMIT ?");
  $st->execute([$limit]);
  return $st->fetchAll();
}

$flash = get_flash();

$currencies = ['CNY','HKD','USD','JPY','KRW','VND','THB','BRL','PLN','UAH']; // з дампу
$roles = ['user','admin'];
$statuses = ['active','banned','pending'];



// Логування відвідувача (гостя)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';


// Вигляд
?>
<!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Адмінка dfbiu</title>


<style>
  :root{ --bg:#0b1220; --panel:#121a2a; --text:#e9eef7; --muted:#93a0b5; --acc:#2e90ff; --ok:#37c871; --err:#ff6b6b; }
  *{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui;background:var(--bg);color:var(--text)}
  header{display:flex;align-items:center;gap:12px;padding:16px 20px;background:var(--panel);position:sticky;top:0;z-index:10}
  header h1{font-size:18px;margin:0}
  header .who{margin-left:auto;color:var(--muted)}
  .wrap{width:min(1200px,96vw);margin:20px auto}
  nav{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
  nav a{padding:10px 12px;border-radius:10px;background:#0f1728;color:#cfe1ff;text-decoration:none;border:1px solid #1e2a44}
  nav a.active{outline:2px solid var(--acc)}
  .card{background:var(--panel);border:1px solid #1e2a44;border-radius:14px;padding:16px}
  .row{display:grid;grid-template-columns: 1fr 1fr; gap:16px}
  .row-3{display:grid;grid-template-columns: 1fr 1fr 1fr; gap:16px}
  .table{width:100%;border-collapse:collapse}
  .table th,.table td{padding:10px;border-bottom:1px solid #1e2a44;text-align:left;font-size:14px}
  .muted{color:var(--muted);font-size:13px}
  form.inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  input,select{background:#0e1627;border:1px solid #1e2a44;color:#e9eef7;padding:10px;border-radius:10px}
  button{background:var(--acc);border:0;color:white;padding:10px 14px;border-radius:10px;cursor:pointer}
  .btn-secondary{background:#0e1627;color:#cfe1ff;border:1px solid #1e2a44}
  .flash{padding:10px 12px;border-radius:10px;margin:8px 0}
  .flash.success{background:#0e2120;color:#baf5e2;border:1px solid #235f5a}
  .flash.error{background:#2a1111;color:#ffd6d6;border:1px solid #5f2323}
  .grid-2{display:grid;grid-template-columns:1fr 1fr; gap:16px}
  .right{justify-self:end}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #1e2a44;background:#0e1627}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="wrap">
  <nav>
    <?php
      $tabs = [
        'dashboard'=>'Дашборд',
        'users'=>'Користувачі',
        'user_create'=>'Додати акаунт',
        'logs'=>'Журнал дій',
      ];
      foreach ($tabs as $k=>$label) {
        $cls = $action===$k ? 'active' : '';
        echo "<a class='$cls' href='admin.php?action=$k'>$label</a>";
      }
    ?>
  </nav>

  <?php foreach ($flash as $f): ?>
    <div class="flash <?=$f['t']?>"><?=$f['m']?></div>
  <?php endforeach; ?>

  <?php if ($action==='dashboard'): ?>
    <div class="row">
      <div class="card">
        <h3>Швидкі дії</h3>
        <p class="muted">Створення акаунта, пошук користувача, перегляд останніх транзакцій.</p>
        <form class="inline" method="get" action="admin.php">
          <input type="hidden" name="action" value="users">
          <input name="q" placeholder="пошук: username або email" />
          <button>Шукати</button>
        </form>
      </div>
      <div class="card">
        <h3>Довідка щодо схеми</h3>
        <p class="muted">Один запис у <code>balances</code> на користувача (у т.ч. валюта в полі запису). Операції: deposit / withdraw / bonus. Всі операції фіксуються в <code>transactions</code> та <code>admin_logs</code>.</p>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($action==='users'):
    $q = trim($_GET['q'] ?? '');
    $items = list_users($pdo, $q);
  ?>
    <div class="card">
      <div class="grid-2">
        <h3>Користувачі</h3>
        <form class="inline right" method="get">
          <input type="hidden" name="action" value="users">
          <input name="q" placeholder="пошук..." value="<?=htmlspecialchars($q)?>" />
          <button class="btn-secondary">Пошук</button>
        </form>
      </div>
      <table class="table">
        <thead><tr>
          <th>ID</th><th>Username</th><th>Email</th><th>Роль</th><th>Статус</th><th>Валюта</th><th>Створено</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($items as $u): ?>
            <tr>
              <td><?=$u['id']?></td>
              <td><?=htmlspecialchars($u['username'])?></td>
              <td><?=htmlspecialchars($u['email'])?></td>
              <td><span class="pill"><?=$u['role']?></span></td>
              <td><span class="pill"><?=$u['status']?></span></td>
              <td><?=$u['currency']?></td>
              <td class="muted"><?=$u['created_at']?></td>
              <td><a class="pill" href="admin.php?action=user_edit&id=<?=$u['id']?>">Відкрити</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($action==='user_create'): ?>
    <div class="card">
      <h3>Створити акаунт</h3>
      <form method="post" class="grid-2" action="admin.php?action=user_create">
        <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
        <div>
          <label>Username<br><input name="username" required></label>
        </div>
        <div>
          <label>Email<br><input type="email" name="email" required></label>
        </div>
        <div>
          <label>Пароль<br><input type="password" name="password" required></label>
        </div>
        <div>
          <label>Валюта<br>
            <select name="currency"><?php foreach($currencies as $c) echo "<option>$c</option>"; ?></select>
          </label>
        </div>
        <div>
          <label>Роль<br>
            <select name="role"><?php foreach($roles as $r) echo "<option>$r</option>"; ?></select>
          </label>
        </div>
        <div>
          <label>Статус<br>
            <select name="status"><?php foreach($statuses as $s) echo "<option>$s</option>"; ?></select>
          </label>
        </div>
        <div></div>
        <div class="right"><button>Створити</button></div>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($action==='user_edit'):
    $uid = (int)($_GET['id'] ?? 0);
    $data = $uid ? get_user($pdo, $uid) : null;
    if (!$data): ?>
      <div class="card"><b>Користувача не знайдено.</b></div>
    <?php else:
      $u = $data['user']; $bal=$data['balance'];
      $tx = list_user_tx($pdo, $u['id']);
  ?>
    <div class="row">
      <div class="card">
        <h3>Користувач #<?=$u['id']?> — <?=htmlspecialchars($u['username'])?></h3>
        <p class="muted"><?=htmlspecialchars($u['email'])?> • створено: <?=$u['created_at']?></p>
        <form method="post" action="admin.php?action=user_update" class="row">
          <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
          <input type="hidden" name="user_id" value="<?=$u['id']?>">
          <div>
            <label>Роль<br>
              <select name="role"><?php foreach($roles as $r){ $sel=$r===$u['role']?'selected':''; echo "<option $sel>$r</option>"; } ?></select>
            </label>
          </div>
          <div>
            <label>Статус<br>
              <select name="status"><?php foreach($statuses as $s){ $sel=$s===$u['status']?'selected':''; echo "<option $sel>$s</option>"; } ?></select>
            </label>
          </div>
          <div>
            <label>Базова валюта<br>
              <select name="currency"><?php foreach($currencies as $c){ $sel=$c===$u['currency']?'selected':''; echo "<option $sel>$c</option>"; } ?></select>
            </label>
          </div>
          <div class="right" style="align-self:end"><button class="btn-secondary">Зберегти</button></div>
        </form>

        <hr style="border-color:#1e2a44;margin:16px 0">

        <form method="post" action="admin.php?action=user_pass_reset" class="inline">
          <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
          <input type="hidden" name="user_id" value="<?=$u['id']?>">
          <input type="password" name="new_password" placeholder="Новий пароль (мін. 6)" required>
          <button class="btn-secondary">Змінити пароль</button>
        </form>
      </div>

      <div class="card">
        <h3>Баланс</h3>
        <p class="muted">Поточний: <b><?=number_format($bal['amount'],2)?></b> <span class="pill"><?=$bal['currency']?></span></p>
        <form method="post" action="admin.php?action=balance" class="row">
          <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
          <input type="hidden" name="user_id" value="<?=$u['id']?>">
          <div>
            <label>Операція<br>
              <select name="op">
                <option value="deposit">Поповнення</option>
                <option value="withdraw">Списання</option>
                <option value="bonus">Бонус</option>
              </select>
            </label>
          </div>
          <div>
            <label>Сума<br><input type="number" step="0.01" min="0.01" name="amount" required></label>
          </div>
          <div>
            <label>Валюта балансу<br>
              <select name="currency"><?php foreach($currencies as $c){ $sel=$c===$bal['currency']?'selected':''; echo "<option $sel>$c</option>"; } ?></select>
            </label>
          </div>
          <div class="right" style="align-self:end"><button>Застосувати</button></div>
        </form>

        <hr style="border-color:#1e2a44;margin:16px 0">

        <h4>Останні транзакції</h4>
        <table class="table">
          <thead><tr><th>ID</th><th>Тип</th><th>Сума</th><th>Статус</th><th>Коли</th></tr></thead>
          <tbody>
          <?php foreach ($tx as $t): ?>
            <tr>
              <td><?=$t['id']?></td>
              <td><?=$t['type']?></td>
              <td><?=number_format($t['amount'],2)?></td>
              <td><span class="pill"><?=$t['status']?></span></td>
              <td class="muted"><?=$t['created_at']?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; endif; ?>

  <?php if ($action==='logs'):
    $logs = list_admin_logs($pdo);
  ?>
    <div class="card">
      <h3>Журнал дій адміна</h3>
      <table class="table">
        <thead><tr><th>ID</th><th>Хто</th><th>Дія</th><th>Деталі</th><th>Коли</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $g): ?>
          <tr>
            <td><?=$g['id']?></td>
            <td><?=htmlspecialchars($g['admin_name'])?></td>
            <td><?=$g['action']?></td>
            <td><span class="muted"><?=htmlspecialchars($g['details'])?></span></td>
            <td class="muted"><?=$g['created_at']?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="muted" style="margin-top:24px">Побудовано під твою БД (users, balances, transactions, admin_logs, profiles). Дамп: див. завантажений файл. </p>
</div>
</body>
</html>
