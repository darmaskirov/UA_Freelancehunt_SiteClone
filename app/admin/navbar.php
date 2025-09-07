<?php
// app/admin/users.php — single-file admin (list + modals + API) на $conn
require_once $_SERVER['DOCUMENT_ROOT'].'/dfbiu/app/boot_session.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/dfbiu/app/db.php';
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
?>

<header class="topbar">
  <div class="logo">
    <h1>Адмінка</h1>
  </div>

  <div class="profile">
    <button class="profile-btn">
      <?=htmlspecialchars($me['username'])?> ▾
    </button>
    <div class="dropdown">
      <a href="/dfbiu/admin/logout">Вихід</a>
    </div>
  </div>
</header>

<style>
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 20px;background:var(--panel);border-bottom:1px solid var(--line)
}
.logo h1{font-size:18px;margin:0;color:var(--text)}
.nav{display:flex;gap:16px}
.nav a{color:var(--muted);text-decoration:none;padding:6px 0}
.nav a:hover{color:var(--text)}

.profile{position:relative}
.profile-btn{
  background:none;border:0;color:var(--text);cursor:pointer;
  font-weight:600;display:flex;align-items:center;gap:4px
}
.dropdown{
  position:absolute;right:0;top:100%;margin-top:6px;
  background:var(--panel);border:1px solid var(--line);border-radius:10px;
  display:none;flex-direction:column;min-width:160px;
  box-shadow:0 12px 24px rgba(0,0,0,.3);z-index:1000
}
.dropdown a{padding:10px 14px;color:var(--text);text-decoration:none}
.dropdown a:hover{background:#0e1627}

/* показуємо меню при hover */
.profile:hover .dropdown{display:flex}
</style>

<script>
document.addEventListener('click',(e)=>{
  const profile = document.getElementById('profile-menu');
  const btn = e.target.closest('.profile-btn');
  const drop = profile.querySelector('.dropdown');
  if(btn){ drop.hidden = !drop.hidden; return; }
  if(!e.target.closest('#profile-menu')) drop.hidden = true;
});
</script>
