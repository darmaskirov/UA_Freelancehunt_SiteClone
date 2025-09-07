<?php
// app/admin/users.php — single-file admin (list + modals + API) на $conn
// if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'].'/dfbiu/app/admin/boot_session.php';
require_once __DIR__ . '/../../config/config.php'; // має створювати $conn (PDO)

// ---------- 1) Перевірка адміна ----------
$me = null;
if (!empty($_SESSION['user_id'])) {
  $st = $conn->prepare("SELECT id, username, role FROM users WHERE id=? LIMIT 1");
  $st->execute([$_SESSION['user_id']]);
  $me = $st->fetch();
}
if (!$me || $me['role'] !== 'admin') {
  if (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['ok'=>false,'err'=>'forbidden']); exit;
  }
  http_response_code(403);
  echo "Forbidden"; exit;
}

// ---------- 2) CSRF ----------
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

// ---------- 3) API (AJAX POST) у ТОМУ Ж ФАЙЛІ ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  try {
    // CSRF
    $token = $_POST['csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
      http_response_code(403);
      echo json_encode(['ok'=>false, 'err'=>'bad_csrf']); exit;
    }

    $op = $_POST['op'] ?? '';

    // адмін-лог
    $adminLog = function(string $action, array $details=[]) use ($conn, $me) {
      $st = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?,?,?)");
      $st->execute([$me['id'], $action, json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    };

    if ($op === 'user_update') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $username = trim($_POST['username'] ?? '');
      $email    = trim($_POST['email'] ?? '');
      $role     = $_POST['role'] ?? 'user';
      $status   = $_POST['status'] ?? 'active';
      $currency = $_POST['currency'] ?? 'USD';
      if ($uid<=0 || $username==='' || $email==='') throw new RuntimeException("bad_input");

      $st = $conn->prepare("UPDATE users SET username=?, email=?, role=?, status=?, currency=? WHERE id=?");
      $st->execute([$username,$email,$role,$status,$currency,$uid]);

      $adminLog('user_update', compact('uid','role','status','currency'));
      echo json_encode(['ok'=>true]); exit;
    }

    if ($op === 'user_pass_reset') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $new = $_POST['new_password'] ?? '';
      if ($uid<=0 || strlen($new)<6) throw new RuntimeException("bad_input");
      $hash = password_hash($new, PASSWORD_BCRYPT);
      $conn->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$uid]);
      $adminLog('user_pass_reset', ['user_id'=>$uid]);
      echo json_encode(['ok'=>true]); exit;
    }

    if ($op === 'balance') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $type = $_POST['type'] ?? 'deposit'; // deposit|withdraw|bonus
      $amount = (float)($_POST['amount'] ?? 0);
      $currency = $_POST['currency'] ?? 'USD';
      if ($uid<=0 || $amount<=0) throw new RuntimeException("bad_input");

      $conn->beginTransaction();
      try {
        $st = $conn->prepare("SELECT amount,currency FROM balances WHERE user_id=? LIMIT 1");
        $st->execute([$uid]);
        $row = $st->fetch();

        if (!$row) {
          $conn->prepare("INSERT INTO balances (user_id,currency,amount) VALUES (?,?,0.00)")
               ->execute([$uid,$currency]);
          $amountOld = 0.00;
        } else {
          $amountOld = (float)$row['amount'];
          if ($row['currency'] !== $currency) {
            $conn->prepare("UPDATE balances SET currency=? WHERE user_id=?")->execute([$currency,$uid]);
          }
        }

        $delta = ($type==='withdraw') ? -$amount : +$amount;
        $amountNew = round($amountOld + $delta, 2);
        if ($amountNew < 0) throw new RuntimeException("insufficient_funds");

        $conn->prepare("UPDATE balances SET amount=? WHERE user_id=?")->execute([$amountNew,$uid]);
        $conn->prepare("INSERT INTO transactions (user_id,type,amount,status) VALUES (?,?,?,'completed')")
             ->execute([$uid,$type,abs($amount)]);

        $adminLog("balance_$type", ['user_id'=>$uid,'delta'=>$delta,'currency'=>$currency,'newAmount'=>$amountNew]);

        $conn->commit();
      } catch (Throwable $e) { $conn->rollBack(); throw $e; }

      echo json_encode(['ok'=>true]); exit;
    }

    if ($op === 'toggle_active') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $to  = $_POST['to_status'] ?? 'inactive'; // active|inactive|banned|pending
      if ($uid<=0) throw new RuntimeException("bad_input");
      $conn->prepare("UPDATE users SET status=? WHERE id=?")->execute([$to,$uid]);
      $adminLog('user_status', ['user_id'=>$uid,'to'=>$to]);
      echo json_encode(['ok'=>true]); exit;
    }

    if ($op === 'user_delete') {
      $uid = (int)($_POST['user_id'] ?? 0);
      if ($uid<=0) throw new RuntimeException("bad_input");

      $conn->beginTransaction();
      try {
        $conn->prepare("DELETE FROM balances WHERE user_id=?")->execute([$uid]);
        $conn->prepare("DELETE FROM transactions WHERE user_id=?")->execute([$uid]);
        $conn->prepare("DELETE FROM profiles WHERE user_id=?")->execute([$uid]);
        $conn->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $adminLog('user_delete', ['user_id'=>$uid]);
        $conn->commit();
      } catch (Throwable $e) { $conn->rollBack(); throw $e; }

      echo json_encode(['ok'=>true]); exit;
    }

    throw new RuntimeException("unknown_op");
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'err'=>$e->getMessage()]);
  }
  exit;
}

// ---------- 4) GET (рендер сторінки) ----------
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
  $st = $conn->prepare("SELECT id,username,email,role,status,currency,created_at
                        FROM users
                        WHERE username LIKE ? OR email LIKE ?
                        ORDER BY id DESC LIMIT 200");
  $st->execute(["%$q%","%$q%"]);
} else {
  $st = $conn->query("SELECT id,username,email,role,status,currency,created_at
                      FROM users ORDER BY id DESC LIMIT 200");
}
$users = $st->fetchAll();
?>
<!doctype html>
<meta charset="utf-8">
<title>Адмінка — Користувачі</title>
<style>
:root{
  --bg:#0b1220; --panel:#121a2a; --text:#e9eef7; --muted:#93a0b5;
  --acc:#2e90ff; --ok:#37c871; --warn:#ffb020; --danger:#ff6b6b;
  --line:#1e2a44;
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,"Noto Sans SC",sans-serif;background:var(--bg);color:var(--text)}
.topbar{display:flex;gap:12px;align-items:center;padding:16px 20px;background:var(--panel);position:sticky;top:0;z-index:10;border-bottom:1px solid var(--line)}
.topbar h1{font-size:18px;margin:0}
.topbar .who{margin-left:auto;color:var(--muted)}
.wrap{width:min(1200px,96vw);margin:20px auto}
.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:16px}

.search{display:flex;gap:8px;margin-bottom:12px}
.search input{flex:1;padding:10px;border-radius:10px;border:1px solid var(--line);background:#0e1627;color:var(--text)}
button{padding:10px 12px;border-radius:10px;border:0;background:var(--acc);color:#fff;cursor:pointer}
a.btn-secondary, .btn-secondary{display:inline-grid;place-items:center;padding:10px 12px;border-radius:10px;background:#0e1627;border:1px solid var(--line);color:#cfe1ff;text-decoration:none}

.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid var(--line);text-align:left;font-size:14px;vertical-align:middle}
.muted{color:var(--muted)}
.pill{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid var(--line);background:#0e1627}
.pill.ok{border-color:#1c4e39}
.pill.warn{border-color:#5b471d}
.actions{display:flex;gap:8px;flex-wrap:wrap}
button.link{background:#0e1627;color:#cfe1ff;border:1px solid var(--line)}
button.danger{background:var(--danger)}
button.warn{background:var(--warn)}

input,select{background:#0e1627;border:1px solid var(--line);color:var(--text);padding:10px;border-radius:10px;width:100%}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* Modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:grid;place-items:center;padding:20px}
.modal[hidden]{display:none}
.modal__card{width:min(640px,96vw);background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:16px;box-shadow:0 20px 50px rgba(0,0,0,.4)}
.modal__actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}
</style>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="wrap">
  <form class="search" method="get">
    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="пошук username / email">
    <button type="submit">Шукати</button>
  </form>

  <div class="card">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th><th>Username</th><th>Email</th>
          <th>Роль</th><th>Статус</th><th>Валюта</th><th>Створено</th><th>Дії</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($users as $u): ?>
          <tr data-user='<?=htmlspecialchars(json_encode($u,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'>
            <td><?=$u['id']?></td>
            <td><?=htmlspecialchars($u['username'])?></td>
            <td><?=htmlspecialchars($u['email'])?></td>
            <td><span class="pill"><?=$u['role']?></span></td>
            <td><span class="pill <?=$u['status']==='active'?'ok':'warn'?>"><?=$u['status']?></span></td>
            <td><?=$u['currency']?></td>
            <td class="muted"><?=$u['created_at']?></td>
            <td class="actions">
              <button class="link" data-open="edit">Змінити</button>
              <button class="link" data-open="password">Пароль</button>
              <button class="link" data-open="balance">Баланс</button>
              <?php if ($u['status']==='active'): ?>
                <button class="link" data-open="deact">Деактивувати</button>
              <?php else: ?>
                <button class="link" data-open="act">Активувати</button>
              <?php endif; ?>
              <button class="danger" data-open="delete">Видалити</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- Модалі (все тут) -->
<div id="modals-root" data-csrf="<?=$csrf?>">
  <!-- Редагування -->
  <div class="modal" id="modal-edit" hidden>
    <div class="modal__card">
      <h3>Змінити дані користувача</h3>
      <form id="form-edit">
        <input type="hidden" name="op" value="user_update">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="user_id">
        <div class="grid-2">
          <label>Username<br><input name="username" required></label>
          <label>Email<br><input type="email" name="email" required></label>
          <label>Роль<br>
            <select name="role"><option>user</option><option>admin</option></select>
          </label>
          <label>Статус<br>
            <select name="status">
              <option>active</option><option>inactive</option><option>banned</option><option>pending</option>
            </select>
          </label>
          <label>Валюта<br>
            <select name="currency">
              <option>USD</option><option>PLN</option><option>UAH</option><option>CNY</option><option>HKD</option>
              <option>JPY</option><option>KRW</option><option>VND</option><option>THB</option><option>BRL</option>
            </select>
          </label>
        </div>
        <div class="modal__actions">
          <button type="button" class="btn-secondary" data-close>Скасувати</button>
          <button>Зберегти</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Пароль -->
  <div class="modal" id="modal-password" hidden>
    <div class="modal__card">
      <h3>Змінити пароль</h3>
      <form id="form-password">
        <input type="hidden" name="op" value="user_pass_reset">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="user_id">
        <label>Новий пароль (мін. 6 символів)<br>
          <input type="password" name="new_password" minlength="6" required>
        </label>
        <div class="modal__actions">
          <button type="button" class="btn-secondary" data-close>Скасувати</button>
          <button>Зберегти</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Баланс -->
  <div class="modal" id="modal-balance" hidden>
    <div class="modal__card">
      <h3>Операція з балансом</h3>
      <form id="form-balance">
        <input type="hidden" name="op" value="balance">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="user_id">
        <div class="grid-2">
          <label>Операція<br>
            <select name="type">
              <option value="deposit">Поповнення</option>
              <option value="withdraw">Списання</option>
              <option value="bonus">Бонус</option>
            </select>
          </label>
          <label>Сума<br><input type="number" step="0.01" min="0.01" name="amount" required></label>
          <label>Валюта<br>
            <select name="currency">
              <option>USD</option><option>PLN</option><option>UAH</option><option>CNY</option><option>HKD</option>
              <option>JPY</option><option>KRW</option><option>VND</option><option>THB</option><option>BRL</option>
            </select>
          </label>
        </div>
        <div class="modal__actions">
          <button type="button" class="btn-secondary" data-close>Скасувати</button>
          <button>Застосувати</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Актив/Деактив -->
  <div class="modal" id="modal-deact" hidden>
    <div class="modal__card">
      <h3 id="deact-title">Деактивувати користувача?</h3>
      <form id="form-deact">
        <input type="hidden" name="op" value="toggle_active">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="user_id">
        <input type="hidden" name="to_status" value="inactive">
        <p class="muted">Статус буде змінено.</p>
        <div class="modal__actions">
          <button type="button" class="btn-secondary" data-close>Скасувати</button>
          <button class="warn">Підтвердити</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Видалення -->
  <div class="modal" id="modal-delete" hidden>
    <div class="modal__card">
      <h3>Видалити користувача?</h3>
      <form id="form-delete">
        <input type="hidden" name="op" value="user_delete">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="user_id">
        <p class="muted">Операція незворотна. Рекомендується деактивація/бан.</p>
        <div class="modal__actions">
          <button type="button" class="btn-secondary" data-close>Скасувати</button>
          <button class="danger">Видалити</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>(function(){
  const root = document.getElementById('modals-root');
  const csrf = root?.dataset?.csrf || '';
  const open = id => document.getElementById('modal-'+id).hidden = false;
  const closeAll = () => document.querySelectorAll('.modal').forEach(m=>m.hidden=true);

  // Делегування кліків
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('button,[data-open],[data-close]');
    if (!btn) return;

    if (btn.hasAttribute('data-close')) { closeAll(); return; }

    const which = btn.dataset.open;
    if (!which) return;

    const tr = btn.closest('tr');
    const user = tr ? JSON.parse(tr.dataset.user) : null;

    if (which==='edit') {
      const f = document.getElementById('form-edit');
      f.user_id.value = user.id;
      f.username.value = user.username;
      f.email.value = user.email;
      f.role.value = user.role;
      f.status.value = user.status;
      f.currency.value = user.currency;
      open('edit');
    }

    if (which==='password') {
      const f = document.getElementById('form-password');
      f.user_id.value = user.id;
      f.new_password.value = '';
      open('password');
    }

    if (which==='balance') {
      const f = document.getElementById('form-balance');
      f.user_id.value = user.id;
      f.amount.value = '';
      f.type.value = 'deposit';
      f.currency.value = user.currency || 'USD';
      open('balance');
    }

    if (which==='deact' || which==='act') {
      const f = document.getElementById('form-deact');
      const to = which==='act' ? 'active' : 'inactive';
      f.user_id.value = user.id;
      f.to_status.value = to;
      document.getElementById('deact-title').textContent =
        (to==='active' ? 'Активувати користувача?' : 'Деактивувати користувача?');
      open('deact');
    }

    if (which==='delete') {
      const f = document.getElementById('form-delete');
      f.user_id.value = user.id;
      open('delete');
    }
  });

  async function postForm(form) {
    const fd = new FormData(form);
    const res = await fetch(location.href, { method: 'POST', body: fd, headers: { 'Accept':'application/json' } });
    const data = await res.json().catch(()=>({ok:false, err:'bad_json'}));
    if (!res.ok || !data.ok) throw new Error(data.err || 'request_failed');
    return data;
  }

  const bind = (id)=> {
    const form = document.getElementById(id);
    form?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      try {
        const btn = form.querySelector('button[type="submit"],button:not([type])');
        btn && (btn.disabled = true);
        await postForm(form);
        closeAll();
        location.reload();
      } catch(err) {
        alert('Помилка: ' + err.message);
      } finally {
        const btn = form.querySelector('button[type="submit"],button:not([type])');
        btn && (btn.disabled = false);
      }
    });
  };

  bind('form-edit');
  bind('form-password');
  bind('form-balance');
  bind('form-deact');
  bind('form-delete');

  // Закриття по бекдропу/ESC
  document.querySelectorAll('.modal').forEach(m=>{
    m.addEventListener('click', (e)=>{ if (e.target===m) closeAll(); });
  });
  document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeAll(); });
})();</script>
