<?php
// navbar.php — guest/auth switch (mysqli, sessions)
if (session_status() === PHP_SESSION_NONE) session_start();

$host = "127.0.0.1";
$db   = "dfbiu_clone";
$user = "root";
$pass = "";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Допоміжна: отримати поточного користувача за ID із сесії
function current_user(mysqli $conn): ?array {
  if (empty($_SESSION['user_id'])) return null;
  $uid = (int)$_SESSION['user_id'];

  $sql = "SELECT id, username, email, currency, role, status, created_at, updated_at
          FROM users WHERE id = ?";
  if (!$stmt = $conn->prepare($sql)) return null;
  $stmt->bind_param('i', $uid);
  if (!$stmt->execute()) { $stmt->close(); return null; }
  $res = $stmt->get_result();
  $user = $res->fetch_assoc() ?: null;
  $stmt->close();
  return $user;
}

$u = isset($conn) && $conn instanceof mysqli ? current_user($conn) : null;
?>
<link rel="stylesheet" href="navbar.css?v=2">
<header class="header" id="navbar">
  <div class="wrap header-inner">
    <div class="logo">
      <img src="broken-logo.png" alt="logo"/>
    </div>

    <!-- головне меню (залишаю як є) -->
    <ul class="nav" aria-label="Main">
      <li><a href="#" data-code="home">首页</a></li>
      <li class="has-dropdown">
        <a href="#" data-code="video">视讯</a>
        <div class="dropdown-scroll wide">
          <button class="scroll-btn scroll-prev">&#10094;</button>
          <div class="scroll-container">
            <div class="card">
              <img src="img/ag.png" alt="AG视讯"/>
              <div class="title">AG视讯</div>
              <button class="play">进入游戏</button>
            </div>
            <div class="card">
              <img src="img/ebet.png" alt="EBET"/>
              <div class="title">EBET</div>
              <button class="play">进入游戏</button>
            </div>
            <div class="card">
              <img src="img/dg.png" alt="DG"/>
              <div class="title">DG</div>
              <button class="play">进入游戏</button>
            </div>
            <!-- ...інші картки як у тебе... -->
          </div>
          <button class="scroll-btn scroll-next">&#10095;</button>
        </div>
      </li>
      <li><a href="#" data-code="slots">电子</a></li>
      <li><a href="#" data-code="fish">捕鱼</a></li>
      <li><a href="#" data-code="lottery">彩票</a></li>
      <li><a href="#" data-code="sports">体育</a></li>
      <li><a href="#" data-code="board">棋牌</a></li>
      <li><a href="#" data-code="esports">电竞</a></li>
    </ul>

    <!-- ПРАВИЙ БЛОК: гість або акаунт -->
    <div class="right">
      <?php if (!$u): ?>
        <!-- guest: інпути як у твоєму nav3_guest.html -->
        <input type="text" class="login-input" placeholder="账号" />
        <input type="password" class="login-input" placeholder="密码" />
        <button class="btn btn-login" onclick="location.href='/login'">登录</button>
        <button class="btn btn-register" onclick="location.href='/register'">注册</button>

        <!-- мови (як було) -->
        <div class="lang" id="langSelect">
          <span id="langVal">zh-CN</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M6 9l6 6 6-6" stroke="#6780a1" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <div class="lang-menu">
            <div data-lang="zh-CN">中文</div>
            <div data-lang="en-US">English</div>
            <div data-lang="ru-RU">Русский</div>
          </div>
        </div>
      <?php else: ?>
        <!-- authorized: акаунт-чіп + дропдаун (дані з БД) -->
        <div class="account" id="account">
          <button class="user-chip" id="userChip" aria-expanded="false">
            <span class="avatar"></span>
            <span class="user-name"><?=
              htmlspecialchars($u['username'] ?? 'user', ENT_QUOTES, 'UTF-8')
            ?></span>
            <!-- балансу в схемі немає, тож не показуємо .user-balance -->
          </button>

          <div class="acc-menu" id="accMenu">
            <div class="acc-head">
              <span class="avatar"></span>
              <div>
                <div class="acc-name" id="accName"><?=
                  htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8')
                ?></div>
                <div class="acc-balance" id="accBalance">
                  <?= htmlspecialchars($u['currency'] ?? 'USD', ENT_QUOTES, 'UTF-8') ?>
                </div>
              </div>
            </div>

            <a href="/profile" class="acc-item" data-action="profile">
              <span>个人资料</span>
            </a>
            <a href="/membership/deposit" class="acc-item" data-action="deposit">
              <span>充值</span>
            </a>
            <a href="/membership/transfer" class="acc-item" data-action="transfer">
              <span>转换</span>
            </a>
            <a href="/membership/withdraw" class="acc-item" data-action="withdraw">
              <span>提现</span>
            </a>
            <a href="/settings" class="acc-item" data-action="settings">
              <span>设置</span>
            </a>

            <div class="acc-item" style="pointer-events:none; opacity:.7">
              <span>
                <?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                • <?= htmlspecialchars($u['role'] ?? 'user', ENT_QUOTES, 'UTF-8') ?>
                • <?= htmlspecialchars($u['status'] ?? 'active', ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>

            <a href="/logout" class="acc-item" data-action="logout">
              <span>退出登录</span>
            </a>
          </div>
        </div>

        <!-- мови (залишаю як було) -->
        <div class="lang" id="langSelect">
          <span id="langVal">zh-CN</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M6 9l6 6 6-6" stroke="#6780a1" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <div class="lang-menu">
            <div data-lang="zh-CN">中文</div>
            <div data-lang="en-US">English</div>
            <div data-lang="ru-RU">Русский</div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- mobile sheet як було -->
  <div class="nav-sheet" id="sheet">
    <div class="sheet-head">
      <strong style="font-size:16px;">菜单</strong>
      <button class="sheet-close" id="sheetClose" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M6 6l12 12M18 6L6 18" stroke="#58729b" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    <a href="#" data-code="home">首页</a>
    <a href="#" data-code="video">视讯</a>
    <a href="#" data-code="slots">电子</a>
    <a href="#" data-code="fish">捕鱼</a>
    <a href="#" data-code="lottery">彩票</a>
    <a href="#" data-code="sports">体育</a>
    <a href="#" data-code="board">棋牌</a>
    <a href="#" data-code="esports">电竞</a>

    <div class="sheet-label">账户</div>
    <a href="/profile" data-action="profile">个人资料</a>
    <a href="/membership/deposit" data-action="deposit">充值</a>
    <a href="/membership/transfer" data-action="transfer">转换</a>
    <a href="/membership/withdraw" data-action="withdraw">提现</a>
    <a href="/settings" data-action="settings">设置</a>
    <a href="/logout" data-action="logout">退出登录</a>
  </div>
</header>
