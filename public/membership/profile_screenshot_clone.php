<?php
// profile_screenshot_clone.php — look&feel ~ like screenshot (scoped, no navbar conflicts)
if (!isset($user)) {
  $user = [
    'username' => 'test228',
    'reg_date' => '2025-09-01',
    'realname' => '',
    'gender' => '',
    'phone' => '',
    'birthday' => '',
    'qq' => '',
    'telegram' => '',
    'email' => '',
    'phone_verified' => true
  ];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>用户信息</title>
  <style>
  /* ===== Scope all profile styles ===== */
  .pf-page{--bg:#eaf4ff;--card:#fff;--ink:#1b2430;--muted:#7e8aa6;--line:#e7eef8;--brand:#2e90ff;--chip:#eef6ff;--shadow:0 10px 24px rgba(32,74,128,.08);--sidebar:#f5faff}
  .pf-page{background:var(--bg);color:var(--ink);font-family:Inter,system-ui,"Noto Sans SC",sans-serif;min-height:100vh}
  .pf-wrap{width:min(1200px,96vw);margin:24px auto;display:grid;grid-template-columns:280px 1fr;gap:24px}
  .pf-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}
  .pf-h{padding:18px 20px 0}
  .pf-body{padding:16px 20px 20px}
  /* left top blue card */
  .pf-hero{background:linear-gradient(180deg,#0e8fff 0%,#2ea4ff 100%);color:#fff;overflow:hidden}
  .pf-hero .pf-body{padding:16px 16px 18px}
  .pf-uname{font-weight:600}
  .pf-ureg{opacity:.9;font-size:12px;margin-top:2px}
  .pf-quick{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}
  .pf-quick .item{display:flex;flex-direction:column;align-items:center;gap:6px}
  .pf-quick .qicon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;background:linear-gradient(180deg,#66b7ff 0%,#2c94ff 100%);box-shadow:inset 0 0 0 1px rgba(255,255,255,.35), 0 4px 10px rgba(0,0,0,.12);color:#fff}
  .pf-quick .qlabel{font-size:12px;color:#e8f3ff}
  /* left menu */
  .pf-side{position:sticky;top:16px;height:fit-content}
  .pf-menu{display:flex;flex-direction:column}
  .pf-menu .pf-item{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;color:#2a3b4f;text-decoration:none;border:1px solid transparent}
  .pf-menu .pf-item:hover{background:#f7fbff;border-color:var(--line)}
  .pf-menu .pf-item.active{background:#f5faff;border-color:var(--line)}
  .pf-ico{width:18px;height:18px;color:#579dff}
  .pf-sep{height:1px;background:var(--line);margin:10px 0}
  /* right card */
  .pf-title{font-size:18px;font-weight:700;color:#1a2b44}
  .pf-sub{font-size:13px;color:#6b7a96;margin-top:6px;margin-bottom:8px}
  .pf-form{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:14px;margin-top:8px}
  .pf-field{display:flex;flex-direction:column;gap:6px}
  .pf-label{font-size:12px;color:#6b7a96}
  .pf-input,.pf-select,.pf-date{height:38px;border:1px solid var(--line);border-radius:10px;background:#fff;padding:0 12px;outline:0;font-size:14px;transition:border-color .15s, box-shadow .15s;width:100%}
  .pf-input:focus,.pf-select:focus,.pf-date:focus{border-color:#cfe3ff;box-shadow:0 0 0 3px rgba(46,144,255,.15)}
  .pf-actions{text-align:center;margin-top:8px}
  .pf-btn{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 24px;border-radius:12px;border:1px solid transparent;background:var(--brand);color:#fff;font-weight:600;cursor:pointer}
  .pf-btn:hover{filter:brightness(1.03)}
  .pf-note{margin-top:8px;font-size:12px;color:#6b7a96;display:flex;align-items:center;gap:6px}
  .pf-note::before{content:"i";display:inline-grid;place-items:center;width:16px;height:16px;border-radius:50%;border:1px solid #a9c8ff;color:#579dff;font-weight:700;font-size:12px}
  /* security rows */
  .pf-sec{margin-top:12px;display:flex;flex-direction:column;gap:12px}
  .pf-row{display:flex;align-items:center;justify-content:space-between;border:1px solid var(--line);background:#fff;border-radius:12px;padding:12px 14px}
  .pf-row .l{display:flex;align-items:center;gap:10px}
  .pf-row .r{display:flex;align-items:center;gap:10px}
  .pf-tag{font-size:12px;background:#f1f7ff;border:1px solid #d9e9ff;color:#4288db;padding:4px 8px;border-radius:999px}
  .pf-btn-mini{height:30px;padding:0 16px;border-radius:16px;background:#2e90ff;color:#fff;border:0;font-weight:600;cursor:pointer}
  .pf-right-text{font-size:12px;color:#5a6f95}
  @media (max-width:980px){.pf-wrap{grid-template-columns:1fr}.pf-side{position:static}.pf-form{grid-template-columns:1fr}}
  </style>
</head>
<body class="pf-page">
  <main class="pf-wrap">
    <!-- Left -->
    <aside class="pf-side">
      <div class="pf-card pf-hero">
        <div class="pf-body">
          <div class="pf-uname"><?=htmlspecialchars($user['username'] ?? '')?></div>
          <div class="pf-ureg"><?=htmlspecialchars($user['reg_date'] ?? '')?></div>
          <div class="pf-quick">
            <a class="item" href="#recharge">
              <span class="qicon">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 5v14M5 12h14"/>
                </svg>
              </span>
              <span class="qlabel">充值</span>
            </a>
            <a class="item" href="#transfer">
              <span class="qicon">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M8 7h8l-3-3m3 3-3 3M16 17H8l3 3m-3-3 3-3"/>
                </svg>
              </span>
              <span class="qlabel">转帐</span>
            </a>
            <a class="item" href="#withdraw">
              <span class="qicon">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 3v12m0 0-4-4m4 4 4-4M4 21h16"/>
                </svg>
              </span>
              <span class="qlabel">提现</span>
            </a>
          </div>
        </div>
      </div>

      <div class="pf-card" style="margin-top:12px">
        <div class="pf-body pf-menu">
          <a class="pf-item active" href="#">
            <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            用户信息
          </a>
          <a class="pf-item" href="#vip">
            <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 7h18l-3 10H6L3 7z"/><path d="M7 7l5 6 5-6"/>
            </svg>
            VIP特权
          </a>
          <a class="pf-item" href="#cards">
            <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="5" width="20" height="14" rx="2"/>
              <path d="M2 10h20"/>
            </svg>
            我的卡包
          </a>
        </div>
      </div>
    </aside>

    <!-- Right -->
    <section class="pf-main">
      <div class="pf-card">
        <div class="pf-h">
          <div class="pf-title">用户信息</div>
          <div class="pf-sub">基本信息</div>
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
                <option value="">请选择</option>
                <option value="male" <?=($user['gender']??'')==='male'?'selected':''?>>男</option>
                <option value="female" <?=($user['gender']??'')==='female'?'selected':''?>>女</option>
              </select>
            </div>
            <div class="pf-field">
              <label class="pf-label">手机号码</label>
              <input class="pf-input" name="phone" placeholder="请输入手机号码" value="<?=htmlspecialchars($user['phone'] ?? '')?>">
            </div>
            <div class="pf-field">
              <label class="pf-label">出生日期</label>
              <input class="pf-date" type="date" name="birthday" value="<?=htmlspecialchars($user['birthday'] ?? '')?>">
            </div>
            <div class="pf-field">
              <label class="pf-label">QQ</label>
              <input class="pf-input" name="qq" placeholder="请输入QQ" value="<?=htmlspecialchars($user['qq'] ?? '')?>">
            </div>
            <div class="pf-field">
              <label class="pf-label">telegram</label>
              <input class="pf-input" name="telegram" placeholder="请输入Telegram" value="<?=htmlspecialchars($user['telegram'] ?? '')?>">
            </div>
            <div class="pf-field" style="grid-column:1/-1">
              <label class="pf-label">电子邮件</label>
              <input class="pf-input" name="email" placeholder="请输入电子邮箱" value="<?=htmlspecialchars($user['email'] ?? '')?>">
            </div>
          </form>

          <div class="pf-actions">
            <button class="pf-btn" type="submit">提交</button>
          </div>

          <div class="pf-note">其他用户将不可见您的基本信息</div>

          <div class="pf-sec">
            <div class="pf-row">
              <div class="l">
                <svg class="pf-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 5h18v14H3z"/><path d="M7 10h10M7 14h10"/>
                </svg>
                <div>手机号码</div>
              </div>
              <div class="r">
                <?php if (!empty($user['phone_verified'])): ?>
                  <span class="pf-right-text">已验证</span>
                <?php else: ?>
                  <button class="pf-btn-mini" type="button">验证</button>
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
                <button class="pf-btn-mini" type="button">绑定</button>
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
                <button class="pf-btn-mini" type="button">修改</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>