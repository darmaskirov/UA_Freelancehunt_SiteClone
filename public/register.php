<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/boot_session.php';
require_once __DIR__ . '/../config/config.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    die('DB connection ($conn) is not available.');
}
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $currency         = strtoupper(trim($_POST['currency'] ?? ''));

    // 校验
    if (mb_strlen($username) < 3) $errors[] = '用户名长度至少为 3 个字符';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '请输入有效的邮箱地址';
    if (strlen($password) < 6) $errors[] = '密码长度至少为 6 位';
    if ($password !== $password_confirm) $errors[] = '两次输入的密码不一致';
    if ($currency === '') $errors[] = '请选择货币';

    // 唯一性检查
    if (!$errors) {
        $st = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $st->execute([$username]);
        if ($st->fetchColumn() > 0) $errors[] = '该用户名已被占用';

        $st = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetchColumn() > 0) $errors[] = '该邮箱已被注册';
    }

    // 注册 -> 自动登录 -> 重定向到主页
    if (!$errors) {
        try {
            $conn->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $st = $conn->prepare(
                "INSERT INTO users (username, email, currency, password_hash, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $st->execute([$username, $email, $currency, $hash]);

            $user_id = (int)$conn->lastInsertId();

            // 初始余额
            $conn->prepare("INSERT INTO balances (user_id, currency, amount) VALUES (?,?,0.00)")
                 ->execute([$user_id, $currency]);

            $conn->commit();

            // ---- AUTO LOGIN ----
            // запобігання фіксації сесії
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            // мінімум, що очікує navbar — user_id
            $_SESSION['user_id']  = $user_id;
            
            // для navbar (current_user())
            $_SESSION['uid'] = $user_id;
// додатково кладемо корисні поля (раптом navbar їх читає з сесії)
            $_SESSION['username'] = $username;
            $_SESSION['currency'] = $currency;
            $_SESSION['auth']     = true;
// щоб дані точно збереглись перед редіректом
            session_write_close();

            // редірект на головну
            header('Location: /');
            exit;
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            if ($e->getCode() === '23000') {
                $msg = $e->getMessage();
                if (stripos($msg, 'username') !== false)      $errors[] = '该用户名已被占用';
                elseif (stripos($msg, 'email') !== false)     $errors[] = '该邮箱已被注册';
                else                                          $errors[] = '提交失败，请稍后再试';
            } else {
                $errors[] = '提交失败，请稍后再试';
            }
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $errors[] = '提交失败，请稍后再试';
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>注册 — pc.df clone</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  .reg-root{
    --bg:#e6f1fb; --panel:#ffffff; --text:#2a3b4f; --muted:#8aa0b8;
    --blue:#2e90ff; --blue2:#55bbff; --border:#e5edf7;
    --shadow:0 18px 40px rgba(36,92,160,.18);
    
  }
  .reg-root{min-height:100dvh;display:grid;place-items:center;padding:40px 16px;
    background:radial-gradient(1200px 260px at 50% -120px, rgba(71,165,255,.20), transparent), var(--bg);
    color:var(--text); font-family:"Inter","Noto Sans SC",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;}
  .card{width:min(520px,92vw);background:linear-gradient(180deg,#ffffff 0%, #ffffff 70%, #eef8ff 100%);
    border:1px solid #fff;border-radius:18px;box-shadow:var(--shadow);padding:24px;}
  .logo{display:block;margin:0 auto 12px;height:26px;opacity:.9}
  .title{margin:2px 0 8px;text-align:center;font-weight:700}
  .sub{margin:0 0 14px;text-align:center;font-size:12px;color:var(--muted)}
  .errors{background:#fff3f3;border:1px solid #ffd0d0;color:#b10000;padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:14px;}
  .field{position:relative;margin:12px 0}
  .input,.select{width:80%;height:50px;padding:0 14px 0 44px;border:1px solid var(--border);border-radius:12px;
    outline:none;background:#fff;color:var(--text);font:inherit;}
  .input::placeholder{color:#9bb0c6}
  .icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:#94a6bd;}
  .select{appearance:none;-webkit-appearance:none;-moz-appearance:none}
  .chev{position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:12px;color:#7f96b1;pointer-events:none}
  .input:focus,.select:focus{border-color:#bcd8ff;box-shadow:0 0 0 3px rgba(47,142,255,.12)}
  .btn{width:100%;height:50px;border:0;border-radius:12px;cursor:pointer;color:#fff;font-weight:800;letter-spacing:.4px;
    background:linear-gradient(180deg,var(--blue2),var(--blue));box-shadow:0 16px 28px rgba(47,142,255,.35);margin-top:6px}
  .links{text-align:center;margin-top:14px;color:var(--muted)}
  .links a{color:#1888ff;text-decoration:none;font-weight:700}
</style>
</head>
<body>
<div class="reg-root">
  <form class="card" action="" method="post" autocomplete="off">
    <img class="logo" src="/assets/img/logo.svg" alt="" onerror="this.style.display='none'">
    <h1 class="title">注册</h1>
    <p class="sub">请填写以下信息完成注册</p>

    <?php if (!empty($errors)): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?>
          <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <label class="field" for="username">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>
      </svg>
      <input class="input" id="username" type="text" name="username"
             placeholder="请输入用户名(必填)" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
    </label>

    <label class="field" for="email">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 7l9 7 9-7"/>
      </svg>
      <input class="input" id="email" type="email" name="email"
             placeholder="请输入邮箱(必填)" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </label>

    <label class="field" for="password">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V8a5 5 0 1 1 10 0v3"/>
      </svg>
      <input class="input" id="password" type="password" name="password"
             placeholder="请输入登录密码(必填)" required>
    </label>

    <label class="field" for="password_confirm">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V8a5 5 0 1 1 10 0v3"/>
      </svg>
      <input class="input" id="password_confirm" type="password" name="password_confirm"
             placeholder="确认登录密码(必填)" required>
    </label>

    <div class="field">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <path d="M12 2v20M6 8h12M6 16h12"/>
      </svg>
      <select class="select" id="currency" name="currency" required>
        <option value="" disabled <?= empty($_POST['currency']) ? 'selected':''; ?>>请选择货币(必填)</option>
        <option value="CNY" <?= (($_POST['currency']??'')==='CNY')?'selected':''; ?>>¥（人民币）</option>
        <option value="HKD" <?= (($_POST['currency']??'')==='HKD')?'selected':''; ?>>HK$（港币）</option>
        <option value="USD" <?= (($_POST['currency']??'')==='USD')?'selected':''; ?>>$（dollars/美元）</option>
        <option value="JPY" <?= (($_POST['currency']??'')==='JPY')?'selected':''; ?>>¥（日本円）</option>
        <option value="KRW" <?= (($_POST['currency']??'')==='KRW')?'selected':''; ?>>₩（韩元）</option>
        <option value="VND" <?= (($_POST['currency']??'')==='VND')?'selected':''; ?>>₫（đồng Việt Nam）</option>
        <option value="THB" <?= (($_POST['currency']??'')==='THB')?'selected':''; ?>>฿（泰铢）</option>
        <option value="BRL" <?= (($_POST['currency']??'')==='BRL')?'selected':''; ?>>R$（巴西雷亚尔）</option>
        <option value="EUR" <?= (($_POST['currency']??'')==='EUR')?'selected':''; ?>>€（欧元）</option>
        <option value="GBP" <?= (($_POST['currency']??'')==='GBP')?'selected':''; ?>>£（英镑）</option>
        <option value="IDR" <?= (($_POST['currency']??'')==='IDR')?'selected':''; ?>>Rp（印尼卢比）</option>
        <option value="PHP" <?= (($_POST['currency']??'')==='PHP')?'selected':''; ?>>₱（菲律宾比索）</option>
        <option value="MYR" <?= (($_POST['currency']??'')==='MYR')?'selected':''; ?>>RM（马来西亚林吉特）</option>
        <option value="TWD" <?= (($_POST['currency']??'')==='TWD')?'selected':''; ?>>NT$（新台币）</option>
        <option value="RUB" <?= (($_POST['currency']??'')==='RUB')?'selected':''; ?>>₽（俄罗斯卢布）</option>
        <option value="INR" <?= (($_POST['currency']??'')==='INR')?'selected':''; ?>>₹（印度卢比）</option>
        <option value="AUD" <?= (($_POST['currency']??'')==='AUD')?'selected':''; ?>>A$（澳大利亚元）</option>
        <option value="CAD" <?= (($_POST['currency']??'')==='CAD')?'selected':''; ?>>C$（加拿大元）</option>
        <option value="SGD" <?= (($_POST['currency']??'')==='SGD')?'selected':''; ?>>S$（新加坡元）</option>
        <option value="TRY" <?= (($_POST['currency']??'')==='TRY')?'selected':''; ?>>₺（土耳其里拉）</option>
        <option value="MXN" <?= (($_POST['currency']??'')==='MXN')?'selected':''; ?>>Mex$（墨西哥比索）</option>
        <option value="ARS" <?= (($_POST['currency']??'')==='ARS')?'selected':''; ?>>$（阿根廷比索）</option>
        <option value="COP" <?= (($_POST['currency']??'')==='COP')?'selected':''; ?>>$（哥伦比亚比索）</option>
        <option value="CLP" <?= (($_POST['currency']??'')==='CLP')?'selected':''; ?>>$（智利比索）</option>
        <option value="SAR" <?= (($_POST['currency']??'')==='SAR')?'selected':''; ?>>﷼（沙特里亚尔）</option>
        <option value="AED" <?= (($_POST['currency']??'')==='AED')?'selected':''; ?>>د.إ（阿联酋迪拉姆）</option>
        <option value="KZT" <?= (($_POST['currency']??'')==='KZT')?'selected':''; ?>>₸（哈萨克坚戈）</option>
        <option value="UAH" <?= (($_POST['currency']??'')==='UAH')?'selected':''; ?>>₴（乌克兰格里夫纳）</option>
        <option value="PLN" <?= (($_POST['currency']??'')==='PLN')?'selected':''; ?>>zł（波兰兹罗提）</option>
      </select>
      <span class="chev">▾</span>
    </div>

    <button class="btn" type="submit">注册</button>
    <div class="links">已有账号？ <a href="/login">立即登录</a></div>
  </form>
</div>
</body>
</html>
