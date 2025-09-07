<?php
/***************************************************
 * admin.php — Users Admin (JOIN balances + UPSERT)
 * Працює поряд із boot_session.php у тій самій папці.
 ***************************************************/
declare(strict_types=1);

require_once __DIR__ . '/boot_session.php';
require_admin(); // лише адмін

/* ===== DB CONNECT (inline, якщо $conn не визначено) =====
 * Якщо маєш свій config.php з $conn (PDO) — підключи його і прибери блок нижче.
 */
if (!isset($conn)) {
  $DB_HOST = 'srv1969.hstgr.io';
  $DB_NAME = 'u140095755_questhub';
  $DB_USER = 'u140095755_darmas';
  $DB_PASS = '@Corp9898';
  try {
    $conn = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    http_response_code(500);
    die('DB connection error.');
  }
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function check_csrf(): void {
  $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf']);
  if (!$ok) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Bad CSRF']); exit; }
}

/* ===== Helpers: schema detection ===== */
function users_schema(PDO $conn): array {
  static $cached = null;
  if ($cached) return $cached;
  $cols = [];
  foreach ($conn->query("SHOW COLUMNS FROM users") as $r) {
    $cols[strtolower($r['Field'])] = true;
  }
  $cached = [
    'id'            => isset($cols['id']),
    'username'      => isset($cols['username']),
    'email'         => isset($cols['email']),
    'role'          => isset($cols['role']),
    'status'        => isset($cols['status']),
    'created_at'    => isset($cols['created_at']),
    'password_hash' => isset($cols['password_hash']),
    'password'      => isset($cols['password']),
  ];
  return $cached;
}
$SC = users_schema($conn);

function db_has_table(PDO $conn, string $name): bool {
  try {
    $st = $conn->query("SHOW TABLES LIKE " . $conn->quote($name));
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}
$HAS_BAL = db_has_table($conn, 'balances'); // balances(user_id, currency, amount) + UNIQUE(user_id)

/* ===== SQL builders (users + balances) ===== */
function select_list_columns(array $SC, bool $HAS_BAL): string {
  $cols = [];
  $cols[] = "u.id";
  $cols[] = "u.username";
  $cols[] = $SC['email']      ? "u.email"      : "'' AS email";
  $cols[] = $SC['role']       ? "u.role"       : "'' AS role";
  $cols[] = $SC['status']     ? "u.status"     : "'' AS status";
  if ($HAS_BAL) {
    $cols[] = "b.amount   AS balance";
    $cols[] = "b.currency AS currency";
  } else {
    $cols[] = "NULL AS balance";
    $cols[] = "''  AS currency";
  }
  $cols[] = $SC['created_at'] ? "u.created_at" : "NULL AS created_at";
  return implode(", ", $cols);
}
function from_users_with_bal(bool $HAS_BAL): string {
  return $HAS_BAL
    ? " FROM users u LEFT JOIN balances b ON b.user_id = u.id "
    : " FROM users u ";
}
function where_search(array $SC): string {
  return $SC['email'] ? "(u.username LIKE :u OR u.email LIKE :e)" : "(u.username LIKE :u)";
}
function apply_password_hash(array $SC, ?string $password): array {
  if ($password === null || $password === '') return [null, null];
  $hash = password_hash($password, PASSWORD_DEFAULT);
  if ($SC['password_hash']) return ['password_hash', $hash];
  if ($SC['password'])      return ['password', $hash];
  return [null, null];
}

/* ===== AJAX API ===== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action && in_array($method, ['GET','POST'], true)) {
  header('Content-Type: application/json; charset=utf-8');

  try {
    /* ---- LIST ---- */
    if ($action === 'list' && $method === 'GET') {
      $q    = trim($_GET['q'] ?? '');
      $page = max(1, (int)($_GET['page'] ?? 1));
      $size = min(100, max(1, (int)($_GET['size'] ?? 20)));
      $off  = ($page - 1) * $size;

      $select = select_list_columns($SC, $HAS_BAL);
      $from   = from_users_with_bal($HAS_BAL);

      if ($q !== '') {
        $like  = "%{$q}%";
        $where = where_search($SC);
        $sql = "SELECT SQL_CALC_FOUND_ROWS {$select} {$from} WHERE {$where}
                ORDER BY u.id DESC LIMIT {$size} OFFSET {$off}";
        $st = $conn->prepare($sql);
        if ($SC['email']) { $st->execute([':u'=>$like, ':e'=>$like]); } else { $st->execute([':u'=>$like]); }
      } else {
        $sql = "SELECT SQL_CALC_FOUND_ROWS {$select} {$from}
                ORDER BY u.id DESC LIMIT {$size} OFFSET {$off}";
        $st = $conn->query($sql);
      }

      $rows  = $st->fetchAll();
      $total = (int)$conn->query("SELECT FOUND_ROWS()")->fetchColumn();
      echo json_encode(['ok'=>true,'items'=>$rows,'total'=>$total,'page'=>$page,'size'=>$size]); exit;
    }

    /* ---- GET ONE ---- */
    if ($action === 'get' && $method === 'GET') {
      $id = (int)($_GET['id'] ?? 0);
      $select = select_list_columns($SC, $HAS_BAL);
      $from   = from_users_with_bal($HAS_BAL);
      $st = $conn->prepare("SELECT {$select} {$from} WHERE u.id=? LIMIT 1");
      $st->execute([$id]);
      $u = $st->fetch();
      if (!$u) { echo json_encode(['ok'=>false,'msg'=>'Not found']); exit; }
      echo json_encode(['ok'=>true,'item'=>$u]); exit;
    }

    /* ---- SAVE (create/update) ---- */
    if ($action === 'save' && $method === 'POST') {
      check_csrf();

      $id       = (int)($_POST['id'] ?? 0);
      $username = trim($_POST['username'] ?? '');
      $email    = trim($_POST['email'] ?? '');
      $role     = ($_POST['role'] ?? 'user');
      $status   = ($_POST['status'] ?? 'active');
      $password = (string)($_POST['password'] ?? '');

      $balanceRaw  = trim((string)($_POST['balance'] ?? ''));
      $currencyRaw = strtoupper(trim((string)($_POST['currency'] ?? '')));

      if ($username === '') { echo json_encode(['ok'=>false,'msg'=>'username обовʼязковий']); exit; }
      if ($SC['email'] && $email === '') { echo json_encode(['ok'=>false,'msg'=>'email обовʼязковий']); exit; }
      if (!$SC['role'])   $role   = 'user';
      if (!$SC['status']) $status = 'active';

      // balance
      $balance = null;
      if ($balanceRaw !== '') {
        $balanceRaw = str_replace(',', '.', $balanceRaw);
        if (!is_numeric($balanceRaw)) { echo json_encode(['ok'=>false,'msg'=>'Баланс має бути числом']); exit; }
        $balance = number_format((float)$balanceRaw, 2, '.', '');
      }
      // currency (список під свою БД — розширюй за потреби)
      $currency = null;
      if ($currencyRaw !== '') {
        $allowed = ['CNY','HKD','USD','EUR','KRW','JPY','BRL','IDR','MYR','THB','VND','INR','PHP','GBP','TRY','AED','TWD','AUD','RUB','PLN','UAH'];
        if (!in_array($currencyRaw, $allowed, true)) {
          echo json_encode(['ok'=>false,'msg'=>'Невідома валюта']); exit;
        }
        $currency = $currencyRaw;
      }

      // password
      [$passCol, $passVal] = apply_password_hash($SC, $password);

      if ($id > 0) {
        // UPDATE users
        if ($id === (int)session_user()['id'] && $SC['status'] && $status === 'blocked') {
          echo json_encode(['ok'=>false,'msg'=>'Неможливо заблокувати власний аккаунт']); exit;
        }
        $sets = ["u.username = :u"];
        $params = [':u'=>$username, ':id'=>$id];
        if ($SC['email'])  { $sets[]="u.email = :e";  $params[':e']=$email; }
        if ($SC['role'])   { $sets[]="u.role  = :r";  $params[':r']=($role==='admin'?'admin':'user'); }
        if ($SC['status']) { $sets[]="u.status= :s";  $params[':s']=($status==='blocked'?'blocked':'active'); }
        if ($passCol && $passVal) { $sets[]="u.{$passCol} = :p"; $params[':p']=$passVal; }

        $sql = "UPDATE users u SET ".implode(', ',$sets)." WHERE u.id = :id";
        $st  = $conn->prepare($sql);
        $st->execute($params);

        // UPSERT balances (якщо змінювали баланс/валюту або заповнили хоч щось)
        if ($HAS_BAL && ($balanceRaw !== '' || $currencyRaw !== '')) {
          $bal = ($balance === null && $balanceRaw === '') ? 0 : (float)$balance;
          $cur = ($currency === null && $currencyRaw === '') ? 'USD' : $currency;
          $stb = $conn->prepare("
            INSERT INTO balances (user_id, currency, amount)
            VALUES (:uid, :cur, :amt)
            ON DUPLICATE KEY UPDATE currency = VALUES(currency), amount = VALUES(amount)
          ");
          $stb->execute([':uid'=>$id, ':cur'=>$cur, ':amt'=>$bal]);
        }

        echo json_encode(['ok'=>true,'msg'=>'Оновлено']); exit;

      } else {
        // CREATE user
        if (!$passCol || $password === '') {
          echo json_encode(['ok'=>false,'msg'=>'Пароль обовʼязковий для нового користувача']); exit;
        }
        $cols = ['username']; $vals=[':u']; $params=[':u'=>$username];
        if ($SC['email'])  { $cols[]='email';  $vals[]=':e'; $params[':e']=$email; }
        if ($SC['role'])   { $cols[]='role';   $vals[]=':r'; $params[':r']=($role==='admin'?'admin':'user'); }
        if ($SC['status']) { $cols[]='status'; $vals[]=':s'; $params[':s']=($status==='blocked'?'blocked':'active'); }
        $cols[]=$passCol;  $vals[]=':p';       $params[':p']=$passVal;

        $sql = "INSERT INTO users (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
        $st  = $conn->prepare($sql);
        $st->execute($params);

        $newId = (int)$conn->lastInsertId();

        // стартовий баланс у balances
        if ($HAS_BAL) {
          $bal = ($balance === null ? 0 : (float)$balance);
          $cur = ($currency === null ? 'USD' : $currency);
          $stb = $conn->prepare("
            INSERT INTO balances (user_id, currency, amount)
            VALUES (:uid, :cur, :amt)
            ON DUPLICATE KEY UPDATE currency = VALUES(currency), amount = VALUES(amount)
          ");
          $stb->execute([':uid'=>$newId, ':cur'=>$cur, ':amt'=>$bal]);
        }

        echo json_encode(['ok'=>true,'msg'=>'Створено','id'=>$newId]); exit;
      }
    }

    /* ---- DELETE ---- */
    if ($action === 'delete' && $method === 'POST') {
      check_csrf();
      $id = (int)($_POST['id'] ?? 0);
      if ($id === 0) { echo json_encode(['ok'=>false,'msg'=>'id відсутній']); exit; }
      if ($id === (int)session_user()['id']) {
        echo json_encode(['ok'=>false,'msg'=>'Неможливо видалити власний аккаунт']); exit;
      }
      // видаляємо користувача; а баланс — або через ON DELETE CASCADE, або окремо
      $conn->prepare("DELETE FROM users WHERE id=? LIMIT 1")->execute([$id]);
      echo json_encode(['ok'=>true,'msg'=>'Видалено']); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;

  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Server error','debug'=>$e->getMessage()]); exit;
  }
}

/* ===== HTML UI ===== */
$me = session_user();
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <title>Адмінка — Користувачі</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#f5f8fd;--text:#253044;--blue:#2e90ff;--border:#e5e9f2;--hover:#f0f4ff}
    *{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,"Noto Sans",sans-serif;background:var(--bg);color:var(--text)}
    header.topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid var(--border)}
    .wrap{width:min(1200px,96vw);margin:0 auto;padding:12px 0}
    .row{display:flex;align-items:center;gap:12px}
    h1{font-size:18px;margin:0}
    .who{margin-left:auto;font-size:14px}
    .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--border);background:#fff;padding:8px 12px;border-radius:10px;cursor:pointer}
    .btn.primary{background:var(--blue);color:#fff;border-color:transparent}
    .btn:hover{filter:brightness(.98)}
    main{padding:18px 0}
    .card{background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:0 8px 24px rgba(32,74,128,.06)}
    .toolbar{display:flex;gap:10px;align-items:center;padding:12px;border-bottom:1px solid var(--border)}
    .toolbar input[type="search"]{flex:1;border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:#fff}
    table{width:100%;border-collapse:separate;border-spacing:0}
    th,td{padding:12px;border-bottom:1px solid var(--border);font-size:14px;text-align:left}
    th{background:#fafbff;position:sticky;top:60px;z-index:1}
    tr:hover td{background:var(--hover)}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid var(--border)}
    .grid{display:grid;grid-template-columns:1fr;gap:16px}
    @media(min-width:900px){.grid{grid-template-columns:1fr}}
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.25)}
    .modal.show{display:flex}
    .modal .box{background:#fff;border:1px solid var(--border);border-radius:16px;width:min(640px,96vw);padding:16px}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .field{display:flex;flex-direction:column;gap:6px}
    input,select{border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:#fff}
    .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
    .danger{color:#b00020}
    .muted{opacity:.7}
    .err{background:#ffecec;color:#b00020;border:1px solid #ffd0d0;padding:8px 12px;border-radius:10px;display:none;margin:0 12px 12px}
    .err.show{display:block}
    
    /* --- ВАРІАНТ 1: повністю прибрати «липку» шапку --- */
.users-table thead th{
  position: static !important;
  box-shadow: none !important;
}

/* --- ВАРІАНТ 2: залишити липку шапку, але правильно зафіксувати --- */
/* Закоментуй VAR-1 вище і розкоментуй це, якщо хочеш саме фіксовану шапку */
:root{ --header-h:60px; } /* якщо у тебе інша висота хедера — підстав свою */
.admin-tools{ position: sticky; top: var(--header-h); z-index: 20; background:#fff; }
.users-table{ border-collapse: separate; border-spacing: 0; }
.users-table thead th{
  position: sticky;
  /* 56px — приблизна висота панелі з пошуком. Підганяється за потреби */
  top: calc(var(--header-h) + 56px);
  background:#fff;
  z-index: 15;
}

/* висота верхнього топбару (якщо інша — підстав свою) */
:root { --topbar-h: 60px; }

/* робимо липкою ТІЛЬКИ панель з пошуком */
.card > .toolbar {
  position: sticky;
  top: var(--topbar-h);
  z-index: 5;
  background:#fff;
}

/* знімаємо липкість з заголовка таблиці, щоб «полоска» не заважала */
#tbl thead th {
  position: static !important;
  top: auto !important;
  z-index: 1 !important;
  box-shadow: none !important;
}


  </style>
</head>
<body>

<header class="topbar">
  <div class="wrap row">
    <h1>Користувачі</h1>
    <div class="who">Ви: <b><?=htmlspecialchars($me['username'])?></b> (admin)</div>
    <a class="btn" href="/">На головну</a>
    <a class="btn" href="/admin/logout">Вихід</a>
    <button class="btn primary" id="btnCreate">+ Новий</button>
  </div>
</header>

<main class="wrap grid">
  <section class="card">
    <div class="toolbar">
      <input id="q" type="search" placeholder="Пошук по username/email…">
      <button class="btn" id="btnSearch">Пошук</button>
    </div>
    <div id="err" class="err"></div>
    <div style="overflow:auto">
      <table id="tbl">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Username</th>
            <th>Email</th>
            <th style="width:120px">Role</th>
            <th style="width:120px">Status</th>
            <th style="width:120px">Balance</th>
            <th style="width:110px">Currency</th>
            <th style="width:180px">Created</th>
            <th style="width:180px">Дії</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="toolbar">
      <span class="muted" id="meta">0 записів</span>
      <div style="margin-left:auto; display:flex; gap:8px">
        <button class="btn" id="prev">« Попередня</button>
        <button class="btn" id="next">Наступна »</button>
      </div>
    </div>
  </section>
</main>

<!-- Modal -->
<div class="modal" id="modal">
  <div class="box">
    <h2 id="mTitle" style="margin:0 0 10px; font-size:18px">Користувач</h2>
    <form id="mForm">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>">
      <input type="hidden" name="id" value="0">
      <div class="grid2">
        <div class="field">
          <label>Username</label>
          <input name="username" required>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email">
        </div>
        <div class="field">
          <label>Role</label>
          <select name="role">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
        </div>
        <div class="field">
          <label>Status</label>
          <select name="status">
            <option value="active">active</option>
            <option value="blocked">blocked</option>
          </select>
        </div>
        <div class="field">
          <label>Balance</label>
          <input type="text" name="balance" placeholder="напр. 1234.56">
        </div>
        <div class="field">
          <label>Currency</label>
          <input type="text" name="currency" placeholder="PLN / USD / EUR / ...">
        </div>
        <div class="field" style="grid-column:1 / -1">
          <label>Пароль (залиш порожнім, щоб не змінювати)</label>
          <input type="password" name="password" placeholder="новий пароль…">
        </div>
      </div>
      <div class="actions">
        <button type="button" class="btn" id="mCancel">Скасувати</button>
        <button class="btn primary" id="mSave">Зберегти</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const qs = s => document.querySelector(s);
  const tblBody = qs('#tbl tbody');
  const meta = qs('#meta');
  const errBox = qs('#err');
  const inputQ = qs('#q');
  const btnSearch = qs('#btnSearch');
  const btnPrev = qs('#prev');
  const btnNext = qs('#next');
  const btnCreate = qs('#btnCreate');
  
  // ====== SAFE client-side search (фільтрація по завантаженому списку) ======
const debounce = (fn, d = 200) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; };
const escapeAttr = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

function buildSearchString(u){
  return [
    u.id, u.username, u.email, u.role, u.status,
    (u.balance != null ? String(u.balance) : ''), u.currency, u.created_at
  ].filter(Boolean).join(' ').toLowerCase();
}

function applyClientFilter(){
  const q = inputQ.value.trim().toLowerCase();
  let shown = 0;
  for (const tr of tblBody.querySelectorAll('tr')) {
    const hay = tr.getAttribute('data-q') || tr.innerText.toLowerCase();
    const ok = !q || hay.includes(q);
    tr.style.display = ok ? '' : 'none';
    if (ok) shown++;
  }
  // мета-рядок (показує, скільки наразі видно)
  meta.textContent = (total ? `${total} записів` : '') + (q ? ` • показано ${shown}` : (total ? '' : `${shown} записів`));
}

const applyClientFilterDebounced = debounce(applyClientFilter, 150);
// live-пошук під час введення
inputQ.addEventListener('input', applyClientFilterDebounced);


  // ВАЖЛИВО: API — поточний маршрут (ок і для /admin, і для /admin.php)
  const API = window.location.pathname || './';

  let page = 1, size = 20, total = 0, lastQ = '';

  function showErr(msg){ errBox.textContent = msg; errBox.classList.add('show'); }
  function clearErr(){ errBox.classList.remove('show'); errBox.textContent = ''; }
  function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  async function fetchJSON(url, opts={}){
    try{
      const res = await fetch(url, opts);
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); } catch { throw new Error('Bad JSON: '+text.slice(0,200)); }
      if (!res.ok || data.ok === false) throw new Error(data.msg || ('HTTP '+res.status));
      return data;
    } catch (e){
      showErr(e.message || String(e));
      throw e;
    }
  }

  function renderRows(items){
    tblBody.innerHTML = items.map(u => `
      <tr>
        <td>${u.id ?? ''}</td>
        <td>${escapeHtml(u.username)}</td>
        <td>${escapeHtml(u.email ?? '')}</td>
        <td><span class="badge">${escapeHtml(u.role ?? 'user')}</span></td>
        <td><span class="badge">${escapeHtml(u.status ?? 'active')}</span></td>
        <td>${u.balance !== null && u.balance !== undefined ? escapeHtml(String(u.balance)) : ''}</td>
        <td>${escapeHtml(u.currency ?? '')}</td>
        <td>${escapeHtml(u.created_at ?? '')}</td>
        <td>
          <button class="btn" data-act="edit" data-id="${u.id}">Редагувати</button>
          <button class="btn danger" data-act="del" data-id="${u.id}">Видалити</button>
        </td>
      </tr>
    `).join('');
  }

  async function load(){
    clearErr();
    const q = lastQ;
    const data = await fetchJSON(`${API}?action=list&q=${encodeURIComponent(q)}&page=${page}&size=${size}`);
    total = data.total || 0;
    renderRows(data.items || []);
    meta.textContent = `${total} записів • сторінка ${page}`;
  }

  btnSearch.addEventListener('click', ()=>{ lastQ = inputQ.value.trim(); page = 1; load(); });
  inputQ.addEventListener('keydown', e=>{ if(e.key==='Enter'){ lastQ = inputQ.value.trim(); page=1; load(); } });
  btnPrev.addEventListener('click', ()=>{ if (page>1){ page--; load(); } });
  btnNext.addEventListener('click', ()=>{ if ((page*size) < total){ page++; load(); } });

  // Modal
  const modal = qs('#modal');
  const mForm = qs('#mForm');
  const mTitle = qs('#mTitle');
  const mCancel = qs('#mCancel');

  function openModal(title, user=null){
    mTitle.textContent = title;
    mForm.reset();
    mForm.id.value = user?.id || 0;
    mForm.username.value = user?.username || '';
    if (mForm.email)    mForm.email.value    = user?.email    || '';
    if (mForm.role)     mForm.role.value     = user?.role     || 'user';
    if (mForm.status)   mForm.status.value   = user?.status   || 'active';
    if (mForm.balance)  mForm.balance.value  = (user && user.balance != null) ? user.balance : '';
    if (mForm.currency) mForm.currency.value = user?.currency || '';
    mForm.password.value = '';
    modal.classList.add('show');
  }
  function closeModal(){ modal.classList.remove('show'); }

  btnCreate.addEventListener('click', ()=> openModal('Новий користувач'));

  tblBody.addEventListener('click', async (e)=>{
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const id = btn.getAttribute('data-id');

    if (btn.dataset.act === 'edit') {
      clearErr();
      const data = await fetchJSON(`${API}?action=get&id=${id}`);
      openModal('Редагування', data.item);
      return;
    }
    if (btn.dataset.act === 'del') {
      if (!confirm('Видалити користувача?')) return;
      clearErr();
      const fd = new FormData();
      fd.append('action','delete');
      fd.append('id', id);
      fd.append('csrf', '<?=htmlspecialchars($_SESSION['csrf'])?>');
      await fetchJSON(API, {method:'POST', body: fd});
      load();
      return;
    }
  });

  mCancel.addEventListener('click', (e)=>{ e.preventDefault(); closeModal(); });

  mForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    clearErr();
    const fd = new FormData(mForm);
    fd.append('action','save');
    await fetchJSON(API, {method:'POST', body: fd});
    closeModal(); load();
  });

  modal.addEventListener('click', (e)=>{ if(e.target === modal) closeModal(); });

  // boot
  load();
})();
</script>

</body>
</html>
