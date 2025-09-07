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
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>APP Download</title>

  <style>
    /* ===== NAVBAR STYLES (твій блок) ===== */
    :root{
      --bg:#e6f1fb; --text:#2a3b4f; --blue:#2e90ff; --border:#dbe8f7; --hover:#f3f7ff;
      --shadow:0 12px 28px rgba(32,74,128,.10); --header-h:60px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Inter,system-ui,"Noto Sans SC",sans-serif;background:#f5f8fd;color:var(--text)}
    /* HEADER */
    .header{position:sticky;top:0;z-index:1000;background:var(--bg);border-bottom:1px solid #fff}
    .wrap{width:min(1240px,96vw);margin:0 auto}
    .header-inner{height:var(--header-h);display:flex;align-items:center;gap:16px;position:relative}
    .logo img{width:38px;height:24px;object-fit:contain}
    /* NAV (left) */
    .nav{display:flex;align-items:center;list-style:none;gap:24px;flex:1 1 auto;min-width:0;font-weight:700;color:#334a63}
    .nav>li{position:relative;flex:0 0 auto}
    .nav a{padding:10px 2px;text-decoration:none;color:inherit;font-size:15px;transition:.15s;display:inline-block;white-space:nowrap}
    .nav a:hover{color:#0a58ff}
    .nav a.active{color:#0a58ff;position:relative}
    .nav a.active::after{content:"";position:absolute;left:0;right:0;bottom:-6px;height:3px;background:var(--blue);border-radius:3px}
    /* THUMB SCROLLER dropdown (compact) */
    .has-dropdown{position:relative}
    .dropdown-scroll{position:absolute;top:100%;left:0;margin-top:8px;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);padding:20px 40px;width:1000px;max-width:calc(100vw - 24px);opacity:0;transform:translateY(10px);pointer-events:none;transition:all .25s ease;z-index:110}
    .has-dropdown:hover .dropdown-scroll{opacity:1;transform:translateY(0);pointer-events:auto}
    .scroll-container{display:flex;gap:20px;overflow:hidden}
    .scroll-btn{position:absolute;top:50%;transform:translateY(-50%);width:28px;height:28px;border:none;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.2);cursor:pointer}
    .scroll-prev{left:6px}.scroll-next{right:6px}
    .card{flex:0 0 auto;width:180px;height:240px;background:#f1f6ff;border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);display:flex;flex-direction:column;align-items:center;justify-content:space-between;padding:12px}
    .card img{max-width:100%;height:120px;object-fit:contain}
    .card .title{font-weight:700;margin:8px 0}
    .card .play{background:var(--blue);border:none;color:#fff;font-weight:700;padding:6px 14px;border-radius:20px;cursor:pointer}
    .card .play:hover{background:#1c6fe0}
    /* FULL-WIDTH MEGA DROPDOWN */
    .mega{position:static}
    .dropdown-full{position:absolute;left:50%;top:100%;transform:translate(-50%,10px);width:100vw;background:#fff;border-top:1px solid var(--border);box-shadow:var(--shadow);padding:40px 0;opacity:0;pointer-events:none;transition:all .25s ease;z-index:105}
    .has-dropdown.mega:hover .dropdown-full{opacity:1;transform:translate(-50%,0);pointer-events:auto}
    .dropdown-full .wrap-in{width:min(1240px,96vw);margin:0 auto;padding:0 20px}
    .grid-menu{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:30px}
    .grid-item{background:#f8fbff;border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);padding:16px;text-align:center;transition:transform .2s}
    .grid-item:hover{transform:translateY(-4px)}
    .grid-item img{width:100%;max-width:120px;height:80px;object-fit:contain;margin:0 auto 12px}
    .grid-item .title{font-weight:700;color:#334a63;font-size:14px}
    /* RIGHT (account / inputs / icons) */
    .right{display:flex;align-items:center;gap:12px;margin-left:auto}
    .nav-icons{display:flex;gap:12px;align-items:center;margin-right:12px}
    .nav-icons .icon svg{width:32px;height:32px;color:#333;cursor:pointer;transition:color .3s}
    .nav-icons .icon svg:hover{color:#007bff}
    .account{position:relative}
    .user-chip{display:flex;align-items:center;gap:10px;background:#fff;padding:6px 10px;border-radius:999px;border:1px solid var(--border);box-shadow:var(--shadow);font-weight:700;cursor:pointer;white-space:nowrap}
    .avatar{width:28px;height:28px;border-radius:50%;background:#d9e7ff url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=100&auto=format&fit=crop') center/cover}
    .user-balance{color:#0d6efd;font-weight:800}
    .acc-menu{position:absolute;right:0;top:calc(100% + 8px);min-width:220px;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.12);padding:6px 0;opacity:0;transform:translateY(10px);pointer-events:none;transition:all .25s ease;z-index:120}
    .acc-menu.open{opacity:1;transform:translateY(0);pointer-events:auto}
    .acc-item{display:block;width:100%;padding:10px 14px;font-size:14px;color:#334a63;text-decoration:none;cursor:pointer;transition:.2s}
    .acc-item:hover{background:var(--hover);color:#0a58ff}
    .acc-item.logout{border-top:1px solid #e9eef5;margin-top:4px;padding:12px 14px;font-weight:700;color:#cf3e3e}
    .acc-item.logout:hover{background:#fff2f2;color:#a82121}
    /* Гостьова форма */
    .guest-auth{display:flex;align-items:center;gap:8px;flex-wrap:nowrap}
    .guest-auth input{height:32px;width:60px;border:1px solid var(--border);border-radius:20px;padding:0 10px;font-size:14px;outline:none;background:#f5f8fd;color:#2a3b4f}
    .guest-auth input:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 4px rgba(46,144,255,.4)}
    .guest-auth .btn{padding:0 14px;height:32px;line-height:32px;border-radius:20px;border:1px solid var(--border);text-decoration:none;color:#2a3b4f;font-size:14px;background:#fff;white-space:nowrap}
    .guest-auth .btn.login{background:var(--blue);color:#fff;border-color:var(--blue)}
    .guest-auth .btn.login:hover{filter:brightness(1.05)}
    /* ВАРІАНТ №2 */
    #navLoginForm.nav-login-form{display:flex;align-items:center;gap:10px;flex-wrap:nowrap}
    #navLoginForm .nav-input.short{height:36px;min-width:160px;padding:0 12px;border:1px solid var(--border);border-radius:999px;background:#fff;color:var(--text);font-size:14px;line-height:36px;outline:none;box-shadow:0 2px 8px rgba(32,74,128,.08);transition:border-color .2s, box-shadow .2s, background .2s}
    #navLoginForm .nav-input.short::placeholder{color:#96a3b6}
    #navLoginForm .nav-input.short:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(46,144,255,.18);background:#fff}
    #navLoginForm .nav-btn{height:36px;padding:0 16px;border:0;border-radius:999px;background:var(--blue);color:#fff;font-weight:600;font-size:14px;cursor:pointer;white-space:nowrap;box-shadow:0 6px 16px rgba(46,144,255,.25);transition:transform .06s ease, box-shadow .2s, background .2s}
    #navLoginForm .nav-btn:hover{background:#1f7cf0;box-shadow:0 8px 20px rgba(31,124,240,.28)}
    #navLoginForm .nav-btn:active{transform:translateY(1px)}
    /* ICON TABS */
    .nav-icons-5{display:flex;align-items:center;gap:18px;margin-right:12px}
    .nav-icon-item{display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;color:#6b7688;line-height:1;position:relative}
    .nav-icon-item .icon-30{width:20px;height:20px}
    .nav-icon-item span{margin-top:6px;font-size:14px}
    .nav-icon-item::after{content:"";position:absolute;left:50%;transform:translateX(-50%);bottom:-8px;width:28px;height:3px;border-radius:3px;background:transparent}
    .nav-icon-item:hover,.nav-icon-item.is-active{color:#14b8a6}
    .nav-icon-item.is-active::after{background:#14b8a6}
    /* ДРІБНІ ФІКСИ */
    .header,.topbar,.site-header{will-change:transform}
    @media (max-width:900px){
      .nav{gap:14px}
      #navLoginForm .nav-input.short{min-width:120px}
      .right{gap:6px}
    }
    .menu,.dropdown,.dropdown-full,.acc-menu{--item-padding:10px 14px}
    .menu a,.dropdown a,.dropdown-full a,.acc-menu a,.menu .item,.dropdown .item,.dropdown-full .item,.acc-menu .item{display:block;width:100%;padding:var(--item-padding)}

    /* ===== PAGE STYLES ===== */
    body.page-bg::before{
      content:""; position:fixed; inset:0;
      background:url('./public/assets/img/app_download_bg-BfvE6kOh.png') center/cover no-repeat;
      opacity:1; z-index:-1; pointer-events:none;
    }
    main.download-page{min-height:calc(100vh - var(--header-h));padding:36px 0 80px}
    .dl-card{
      width:560px;margin-left:auto;background:#fff;border:1px solid var(--border);
      border-radius:18px;box-shadow:var(--shadow);padding:22px 24px;
    }
    .dl-tabs{display:flex;gap:10px;align-items:center}
    .dl-tab{
      appearance:none;border:0;cursor:pointer;font-weight:700;
      padding:8px 16px;border-radius:999px;background:#eaf3ff;color:#2a64c7;
      box-shadow:inset 0 0 0 1px #d4e4ff;
    }
    .dl-tab.is-active{background:#dff0ff;color:#174fb8;box-shadow:var(--shadow)}
    .dl-help{
      margin-left:auto;display:inline-block;font-size:12px;text-decoration:none;
      background:#2e90ff;color:#fff;border-radius:10px;padding:8px 12px;
    }
    .dl-help:hover{filter:brightness(1.05)}
    .dl-title{font-size:32px;color:#526d82;margin:14px 0 10px;font-weight:800}
    .dl-desc{font-size:14px;color:#6d7e95;line-height:1.75;margin-bottom:22px}
    .dl-box{display:flex;align-items:center;gap:28px}
    .dl-qr{width:190px;text-align:center}
    .dl-qr img{width:100%;height:auto;display:block}
    .dl-h5{width:120px;text-align:center}
    .dl-h5 img{width:100%;height:auto;display:block}
    .dl-meta{font-size:12px;color:#7c8da3;margin-top:6px;line-height:1.4}
    @media (max-width:900px){
      .dl-card{margin:20px auto;width:min(96vw,560px)}
      .dl-box{justify-content:space-between}
    }
  </style>
</head>
<body class="page-bg">

  <?php require_once $_SERVER['DOCUMENT_ROOT'].'/public/includes/navbar6.php'; ?>

  <main class="wrap download-page">
    <div class="dl-card" id="dlCard">
      <div class="dl-tabs">
        <button class="dl-tab is-active" data-os="ios">iOS App</button>
        <button class="dl-tab" data-os="android">Android App</button>
        <a class="dl-help" href="#">查看安装教程</a>
      </div>

      <h1 class="dl-title">iOS APP</h1>
      <p class="dl-desc">
        As a service based on the cloud platform, we attach more importance to the security performance of the product.
        Every piece of data is strictly encrypted and backed up in multiple scenarios to prevent crises.
        If data problems occur, they can be restored in time to make data more secure in the cloud.
      </p>

      <div class="dl-box">
        <div class="dl-qr">
          <img src="./public/assets/img/qr.png" alt="QR code">
          <div class="dl-meta">扫码下载<br>支持 iOS</div>
        </div>

        <div class="dl-h5">
          <img src="./public/assets/img/h5-DDnm2lPM.png" alt="H5">
          <div class="dl-meta">直接访问<br>无需下载，手机输入网址即可</div>
        </div>
      </div>
    </div>
  </main>

  <script>
    // перемикач вкладок iOS / Android (міняємо заголовок і підпис під QR)
    (function(){
      const tabs = document.querySelectorAll('.dl-tab');
      const title = document.querySelector('.dl-title');
      const metaQR = document.querySelector('.dl-qr .dl-meta');

      tabs.forEach(btn=>{
        btn.addEventListener('click', ()=>{
          tabs.forEach(b=>b.classList.remove('is-active'));
          btn.classList.add('is-active');
          const os = btn.dataset.os;
          if(os==='android'){
            title.textContent = 'Android APP';
            metaQR.innerHTML = '扫码下载<br>支持 Android';
          } else {
            title.textContent = 'iOS APP';
            metaQR.innerHTML = '扫码下载<br>支持 iOS';
          }
        });
      });
    })();
  </script>
</body>
</html>
