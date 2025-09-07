<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>充值 - Deposit</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#eaf4ff; --text:#2b3b4f; --muted:#7f90a6;
  --white:#fff; --border:#e5eef8;
  --blue:#2f8eff; --blue2:#55bbff;
  --shadow:0 16px 32px rgba(36,92,160,.15);
  --r-lg:18px; --r-md:12px; --r-sm:10px;
}
*{box-sizing:border-box}
body{
  margin:0; color:var(--text);
  font-family:"Inter","Noto Sans SC",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
  background:radial-gradient(1200px 240px at 50% -120px, rgba(71,165,255,.20), transparent), var(--bg);
}
.wrap{width:min(1220px,96vw); margin:0 auto}

/* HEADER (коротка шапка як у проєкті) */
.top{height:66px; display:flex; align-items:center; gap:24px}
.logo{width:36px;height:22px;border-radius:6px;background:linear-gradient(180deg,#66c2ff,#2e90ff);box-shadow:0 6px 14px rgba(46,144,255,.25)}
.nav{display:flex;gap:20px;font-weight:700}
.nav a{color:#3a4f66;text-decoration:none}
.sp{flex:1}
.menu{display:flex;gap:10px;align-items:center}
.ico{width:26px;height:26px;display:grid;place-items:center;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow)}
.user-chip{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--border);border-radius:999px;padding:6px 12px;box-shadow:var(--shadow);font-weight:700}
.avatar{width:28px;height:28px;border-radius:50%;background:url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=100&auto=format&fit=crop') center/cover}
.lang{background:#fff;border:1px solid var(--border);border-radius:999px;padding:6px 10px;box-shadow:var(--shadow)}

/* LAYOUT */
.grid{display:grid; grid-template-columns:300px 1fr; gap:22px; align-items:start; margin-top:10px}
.left .card{
  background:linear-gradient(180deg,#1ea0ff,#1d79ff);
  color:#fff; border-radius:20px; box-shadow:var(--shadow);
  padding:18px; display:flex; flex-direction:column; align-items:center; gap:12px
}
.avatar-big{width:120px;height:120px;border-radius:14px;background:rgba(255,255,255,.16)}
.nick{font-weight:800}
.left .menu{background:#fff;border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);overflow:hidden;margin-top:12px}
.mi{display:flex;align-items:center;gap:10px;padding:14px 16px;border-top:1px solid var(--border);color:#2b3d51}
.mi:first-child{border-top:0}
.mi .dot{width:12px;height:12px;border-radius:4px;background:linear-gradient(180deg,var(--blue2),var(--blue))}
.left .section-title{margin:14px 0 6px 12px; color:#7f94aa; font-size:13px}

/* RIGHT PANEL */
.panel{background:#fff;border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);padding:18px}
.panel h2{margin:0 0 12px}

/* 支付类型 tabs */
.paytypes{display:flex; gap:10px; margin-bottom:14px}
.pill{
  height:34px; padding:0 16px; border-radius:999px; font-weight:700; cursor:pointer;
  border:1px solid #dbe9fb; background:linear-gradient(180deg,#f5f9ff,#eaf2ff); color:#5f7391;
}
.pill.active{background:linear-gradient(180deg,var(--blue2),var(--blue)); color:#fff; border-color:transparent; box-shadow:0 10px 18px rgba(47,142,255,.30)}

/* 支付渠道 channels */
.channels{
  min-height:140px; display:grid; place-items:center; margin:14px 0 18px;
  background:#f7fbff; border:1px dashed #cfe1f7; border-radius:14px; color:#9bb0c6;
}
.channels.empty:before{content:"暂无数据";}

/* 金额 presets */
.amounts{display:flex; gap:12px; flex-wrap:wrap; margin-bottom:10px}
.tag{
  height:42px; padding:0 20px; border-radius:12px; font-weight:800; cursor:pointer;
  background:#fff; border:1px solid var(--border); color:#45607a; box-shadow:0 8px 16px rgba(36,92,160,.08);
}
.tag.active{background:linear-gradient(180deg,var(--blue2),var(--blue)); color:#fff; border-color:transparent; box-shadow:0 12px 22px rgba(47,142,255,.28)}

/* amount input + submit */
.field{margin:10px 0}
.input{
  width:420px; max-width:96%; height:40px; border-radius:10px; border:1px solid var(--border); background:#fff; outline:none; padding:0 12px; font:inherit; color:var(--text)
}
.btn{
  width:420px; max-width:96%; height:44px; border:0; border-radius:10px; margin-top:10px; cursor:pointer;
  background:linear-gradient(180deg,var(--blue2),var(--blue)); color:#fff; font-weight:800;
  box-shadow:0 18px 30px rgba(18,147,255,.30)
}

@media (max-width: 1020px){ .grid{grid-template-columns:1fr} }
</style>
</head>
<body>

<header class="wrap top">
  <div class="logo"></div>
  <nav class="nav">
    <a href="#">首页</a><a href="#">视讯</a><a href="#">电子</a><a href="#">捕鱼</a>
    <a href="#">彩票</a><a href="#">体育</a><a href="#">棋牌</a><a href="#">电竞</a>
  </nav>
  <div class="sp"></div>
  <div class="menu">
    <div class="ico" title="活动">🎁</div>
    <div class="ico" title="合营">🏆</div>
    <div class="ico" title="APP">📱</div>
    <div class="ico" title="充值">💳</div>
    <div class="ico" title="转换">🔁</div>
    <div class="ico" title="提现">💸</div>
    <div class="user-chip"><div class="avatar"></div><span>test228</span><span style="color:#0d6efd">$0.0000</span></div>
    <div class="lang">zh-CN ▾</div>
  </div>
</header>

<div class="wrap grid">
  <!-- LEFT -->
  <aside class="left">
    <div class="card">
      <div class="avatar-big"></div>
      <div class="nick">test228</div>
    </div>

    <div class="menu">
      <div class="mi"><span class="dot"></span> 充值</div>
      <div class="mi"><span class="dot"></span> 转换</div>
      <div class="mi"><span class="dot"></span> 提现</div>
      <div class="left section-title">个人中心</div>
      <div class="mi"><span class="dot"></span> 用户信息</div>
      <div class="mi"><span class="dot"></span> VIP特权</div>
      <div class="mi"><span class="dot"></span> 我的卡包</div>
    </div>
  </aside>

  <!-- RIGHT -->
  <main class="panel">
    <h2>充值</h2>

    <!-- 支付类型 -->
    <div class="paytypes" id="paytypes">
      <button class="pill" data-type="card">银行卡支付</button>
      <button class="pill active" data-type="crypto">虚拟币支付</button>
      <button class="pill" data-type="third">支付宝支付</button>
    </div>

    <!-- 支付渠道 (поки пусто) -->
    <div class="channels empty" id="channels"></div>

    <!-- 支付金额 -->
    <div class="amounts" id="amounts">
      <button class="tag active" data-val="100">100</button>
      <button class="tag" data-val="300">300</button>
      <button class="tag" data-val="500">500</button>
      <button class="tag" data-val="800">800</button>
      <button class="tag" data-val="1000">1000</button>
      <button class="tag" data-val="1200">1200</button>
    </div>

    <div class="field">
      <input class="input" id="custom" type="number" placeholder="输入金额" />
    </div>

    <button class="btn" id="submit">确定提交</button>
  </main>
</div>

<script>
/* tabs */
const typeButtons = document.querySelectorAll('#paytypes .pill');
const channels = document.getElementById('channels');
typeButtons.forEach(b=>{
  b.addEventListener('click', ()=>{
    typeButtons.forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    // demo: для card/third показуємо пусто; для crypto — теж пусто (як на скріні)
    channels.classList.add('empty'); // тут можна наповнювати каналами з API
  });
});

/* amount presets */
const tags = document.querySelectorAll('#amounts .tag');
const custom = document.getElementById('custom');
tags.forEach(t=>{
  t.addEventListener('click', ()=>{
    tags.forEach(x=>x.classList.remove('active'));
    t.classList.add('active');
    custom.value = t.dataset.val || '';
  });
});
custom.addEventListener('input', ()=>{
  tags.forEach(x=>x.classList.remove('active'));
});

/* submit demo */
document.getElementById('submit').addEventListener('click', ()=>{
  const type = document.querySelector('#paytypes .pill.active')?.dataset.type || 'crypto';
  const amount = custom.value || document.querySelector('#amounts .tag.active')?.dataset.val || '';
  alert(`提交充值\n支付类型: ${type}\n金额: ${amount || '未填写'}`);
});
</script>
</body>
</html>
