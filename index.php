<?php require_once __DIR__ . '/public/includes/navbar.php'; ?>
<link rel="stylesheet" href="./public/includes/navbar.css">
<!-- <link rel="stylesheet" href="/public/includes/navbar.css"> -->


<style>


  :root{
    --bg:#e6f1fb;
    --text:#2a3b4f;
    --muted:#8093a7;
    --blue:#33a5ff;
    --blue-dark:#1786ff;
    --chip:#f2f7ff;
    --card:#fff;
    --card-shadow:0 15px 35px rgba(32,74,128,.12);
    --border:#e3edf7;
    --radius-lg:18px; --radius-md:14px; --radius-sm:10px;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0; color:var(--text);
    font-family:"Inter","Noto Sans SC",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
    background:
      radial-gradient(1200px 240px at 50% -120px, rgba(71,165,255,.20), transparent),
      var(--bg);
  }

  /* container */
  .wrap{ width:min(1220px,96vw); margin:0 auto; }

  /* HEADER */
  .header{
    position:sticky; top:0; z-index:30;
    background:rgba(230,241,251,.75); backdrop-filter:saturate(160%) blur(6px);
    border-bottom:1px solid rgba(255,255,255,.3);
  }
  .header-inner{
    height:74px; display:flex; align-items:center; gap:28px;
  }
  .logo{
    width:38px;height:24px;border-radius:6px; background:linear-gradient(180deg,#66c2ff,#2e90ff);
    box-shadow:0 6px 16px rgba(46,144,255,.25);
  }
  .nav{ display:flex; gap:26px; font-weight:700; color:#334a63; letter-spacing:.5px }
  .nav a{ text-decoration:none; color:inherit }
  .sp{ flex:1 }
  .icons{ display:flex; gap:12px; align-items:center; color:#4d5f74 }
  .icon{
    width:28px;height:28px; display:grid; place-items:center; border-radius:8px;
    background:#fff; border:1px solid var(--border); box-shadow:var(--card-shadow)
  }
  .user-chip{
    display:flex; align-items:center; gap:10px; background:#fff; padding:6px 12px; border-radius:999px;
    border:1px solid var(--border); box-shadow:var(--card-shadow); font-weight:700;
  }
  .avatar{width:30px;height:30px;border-radius:50%; background:url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=100&auto=format&fit=crop') center/cover}
  .lang{
    display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px;
    background:#fff; border:1px solid var(--border); box-shadow:var(--card-shadow);
  }

  /* SUB NAV (pill) */
  .subnav{
    margin:22px 0 4px;
    display:flex; align-items:center; gap:14px;
  }
  .horn{ width:46px;height:46px; border-radius:16px; background:linear-gradient(180deg,#66c2ff,#2e90ff);
         box-shadow:0 10px 18px rgba(46,144,255,.28) }
  .pill{
    flex:1; display:flex; align-items:center; justify-content:space-between;
    background:#ffffff; padding:8px; border-radius:999px; border:1px solid var(--border);
    box-shadow:var(--card-shadow);
  }
  .pill ul{display:flex; gap:36px; list-style:none; padding:0 16px; margin:0; color:#5d738a; font-weight:600}
  .pill .more{
    padding:8px 14px; border-radius:999px; background:linear-gradient(180deg,#f5f9ff,#eaf3ff);
    border:1px solid #d8e6fb; color:#5f7aa0; font-weight:700;
  }

  /* TITLE with arrows */
  .title{
    display:flex; align-items:center; justify-content:center; gap:26px; margin:22px 0 30px;
  }
  .title h1{ margin:0; font-size:40px; letter-spacing:2px; color:#0ea5ff; }
  .arrow{ width:220px; height:10px; border-top:2px solid #cfe6ff; border-right:14px solid transparent;
          position:relative; }
  .arrow:after{
    content:""; position:absolute; right:0; top:-6px; width:18px; height:12px;
    border-left:2px solid #cfe6ff; border-bottom:2px solid #cfe6ff; transform:skewX(-35deg);
  }

  /* MAIN GRID */
  .grid{ display:grid; grid-template-columns: 1fr 430px; gap:26px; align-items:start }
  .promo{
    height:360px; border-radius:12px; background:#99a0b4; opacity:.8;
    border:1px solid #c2cedd;
  }
  .download{
    background:#fff; border:1px solid var(--border); border-radius:22px; padding:18px;
    box-shadow:var(--card-shadow); position:relative;
  }
  .download .tabs{
    display:flex; gap:10px; align-items:center; margin-bottom:16px;
  }
  .tab{
    height:40px; padding:0 18px; border-radius:999px; border:1px solid #d9e6f7;
    background:linear-gradient(180deg,#f4f8ff,#eef4ff); color:#8aa0ba; font-weight:700; cursor:pointer;
  }
  .tab.active{
    background:linear-gradient(180deg,#55bbff,#2f8eff); color:#fff; border-color:#2f8eff;
    box-shadow:0 10px 18px rgba(47,142,255,.35);
  }
  .corner-cta{
    position:absolute; right:10px; top:12px; height:36px; padding:0 14px; border-radius:999px;
    background:linear-gradient(180deg,#55bbff,#2f8eff); color:#fff; display:flex; align-items:center; font-weight:700;
    box-shadow:0 12px 24px rgba(47,142,255,.35);
  }
  .download h2{ margin:10px 0 6px; font-size:28px; color:#6d7f92 }
  .download p{ margin:6px 0 14px; color:#7c90a6; line-height:1.6 }
  .dl-body{ display:flex; gap:18px; align-items:flex-start }
  .qr{ width:140px; height:140px; border-radius:12px; background:
        conic-gradient(#000 25%, #fff 0 50%, #000 0 75%, #fff 0) ;
        background-size: 20px 20px; background-position: 0 0, 10px 10px; border:3px solid #e7eef7 }
  .h5-badge{
    margin-left:auto; width:86px; height:86px; border-radius:18px; background:linear-gradient(180deg,#65c3ff,#3d9cff);
    display:grid; place-items:center; color:#fff; font-weight:900; font-size:28px; text-shadow:0 3px 10px rgba(0,0,0,.15);
  }

  /* FEATURES */
  .features{
    margin:34px 0 18px; display:grid; grid-template-columns:repeat(4,1fr); gap:18px;
  }
  .feature{
    background:#fff; border:1px solid var(--border); border-radius:20px; padding:26px; text-align:center;
    box-shadow:var(--card-shadow);
  }
  .feature .ico{ width:28px;height:28px;border-radius:8px; background:linear-gradient(180deg,#66c2ff,#2e90ff); margin:0 auto 10px }
  .feature h3{ margin:10px 0 8px; font-size:20px }
  .feature p{ margin:0; color:#7a8ea5; line-height:1.7 }

  /* BIG BANNER */
  .big-banner{
    margin:22px 0 32px; height:320px; border-radius:14px; background:#848a9d; opacity:.85;
    border:1px solid #c2cedd;
  }

  /* FOOTER */
  .footer{
    background:#333d45; color:#c7d0d6; padding:34px 0 60px;
  }
  .brands{ display:flex; flex-wrap:wrap; gap:22px; justify-content:center; opacity:.9 }
  .brand{ width:110px; height:28px; border-radius:6px; background:#54616b }
  .f-links{ margin-top:20px; text-align:center; font-size:14px; color:#aebdc9 }
  .copy{ margin-top:12px; text-align:center; opacity:.8 }

  /* responsive */
  @media (max-width: 1100px){
    .grid{ grid-template-columns:1fr }
    .promo{ height:240px }
    .features{ grid-template-columns:1fr 1fr }
  }
  @media (max-width: 640px){
    .features{ grid-template-columns:1fr }
    .title h1{ font-size:30px }
  }

  .spacer {
  height:440px; /* відступ 40px, можна міняти */
}

.brands img {
  max-height: 40px;
  object-fit: contain;
  filter: grayscale(1);
  opacity: 0.85;
  transition: opacity .3s;
}
.brands img:hover {
  opacity: 1;
  filter: grayscale(0);
}

.horn {
  width:46px;
  height:46px;
  border-radius:16px;
  overflow:hidden; /* щоб обрізало по радіусу */
  display:flex;
  align-items:center;
  justify-content:center;
  background:none; /* прибрати синій градієнт */
  box-shadow:0 10px 18px rgba(46,144,255,.28);
}

.horn img {
  max-width:100%;
  max-height:100%;
  object-fit:contain;
}
/* SUB NAV (pill) */
.subnav{ margin:22px 0 4px; }

.pill{
  position:relative;
  display:flex; align-items:center; 
  background:#fff;
  padding:14px 18px;                 /* більше «повітря» */
  border:1px solid var(--border);
  border-radius:999px;                /* ідеальна капсула */
  box-shadow:var(--card-shadow);
}

/* рупор всередині капсули з красивим оверлапом */
.pill-horn{
  position:absolute;
  left:-10px;                         /* легкий винос назовні */
  top:50%; transform:translateY(-50%);
  width:64px; height:64px;
  object-fit:contain;
  filter: drop-shadow(0 8px 14px rgba(46,144,255,.28));
  pointer-events:none;
}

/* список по центру */
.pill-nav{
  list-style:none; margin:0; padding:0;
  display:flex; align-items:center; justify-content:center;
  gap:36px;
  width:100%;
  padding-left:64px;                  /* місце під рупор */
  color:#5d738a; font-weight:600;
}

/* права кнопка рівно по центру по вертикалі */
.pill .more{
  margin-left:auto;
  height:40px; padding:0 16px;
  display:inline-flex; align-items:center; justify-content:center;
  border-radius:999px;
  border:1px solid #d8e6fb;
  background:linear-gradient(180deg,#f5f9ff,#eaf3ff);
  color:#5f7aa0; font-weight:700;
}

/* ==== PROMO: плейсхолдер «нема зображення» ==== */
.promo.empty{
  position:relative;
  background:
    repeating-linear-gradient(45deg, #cdd6e3 0 14px, #bfc8d6 14px 28px);
  border:1px dashed #9fb0c6;
  opacity:1;
}
.promo.empty::after{
  content:"图片暂缺";
  position:absolute; inset:auto auto 12px 14px;
  font-weight:700; font-size:14px; color:#7b8ea7;
  background:rgba(255,255,255,.8);
  padding:4px 8px; border-radius:8px; border:1px solid #e3edf7;
}

/* ==== DOWNLOAD: QR + HTML5 ==== */
.qr-img{
  width:140px; height:140px;
  border-radius:12px;
  border:3px solid #e7eef7;
  object-fit:cover;
  background:#fff;
  box-shadow:0 6px 14px rgba(0,0,0,.06);
}

/* прибираємо старий «шахматний» фон, якщо він був */
.qr{ display:none; }

.h5-badge{
  margin-left:auto;        /* штовхає значок вправо */
  width:86px; height:86px; 
  border-radius:18px;
  object-fit:contain;
  box-shadow:0 10px 18px rgba(47,142,255,.25);
}

/* якщо було .h5-badge як div — вимкнути старий стиль */
.download .h5-badge{ background:none; }

/* ==== FEATURES: іконки-картинки замість синіх кубиків ==== */
.feature .ico{
  width:40px; height:40px;
  display:block;
  margin:0 auto 10px;
  object-fit:contain;
  filter: drop-shadow(0 6px 10px rgba(46,144,255,.18));
  border-radius:10px;
}

/* на всяк випадок вимкнемо старий фон у .feature .ico, якщо лишився */
.feature .ico:not(img){
  background:none;
}
</style>



  <div class="spacer"></div>

  <main class="wrap">
    <!-- pill subnav -->
    <div class="subnav wrap">
      <div class="pill">
        <img class="pill-horn" src="/public/assets/img/notice-BFxQjxhm.png" alt="">
        <ul class="pill-nav">
          <li>视讯</li><li>电子</li><li>捕鱼</li><li>彩票</li><li>体育</li><li>棋牌</li><li>电竞</li>
        </ul>
        <button class="more">更多</button>
      </div>
    </div>

    <!-- title -->
    <div class="title">
      <div class="arrow"></div>
      <h1>APP下载</h1>
      <div class="arrow"></div>
    </div>

    <!-- main row -->
    <div class="grid">
      <div class="promo empty"></div>

      <aside class="download">
        <div class="corner-cta">查看安装教程</div>
        <div class="tabs">
          <button class="tab active" data-tab="ios">iOS App</button>
          <button class="tab" data-tab="android">Android App</button>
        </div>

        <h2 id="dl-title">iOS APP</h2>
        <p id="dl-desc">
          作为基于云平台的服务，我们更加重视产品的安全性能。每一条数据均加密并多场景备份，出现风险也能快速恢复。
        </p>

        <div class="dl-body">
  <img class="qr-img" src="/public/assets/img/qr.png" alt="QR code">

  <!-- інший контент праворуч (текст) як був -->

  <img class="h5-badge" src="/public/assets/img/h5-DDnm2lPM.png" alt="HTML5"> 
        </div>
      </aside>
    </div>

    <!-- features -->
    <section class="features">
      <article class="feature">
        <div class="ico"></div>
        <h3>更专业</h3>
        <p>每天为您提供近千场精彩体育、电竞赛事，多元玩法尽享受。</p>
      </article>
      <article class="feature">
        <div class="ico"></div>
        <h3>更安全</h3>
        <p>自研并采用高标准安全体系，保障资金与数据安全无忧。</p>
      </article>
      <article class="feature">
        <div class="ico"></div>
        <h3>更便捷</h3>
        <p>H5、iOS、Android 多端体验，7×24 小时在线客服。</p>
      </article>
      <article class="feature">
        <div class="ico"></div>
        <h3>更快速</h3>
        <p>金融级处理系统与网络优化，为您带来更好的游戏体验。</p>
      </article>
    </section>

    <!-- subtle framed banner -->
    <div class="big-banner" role="img" aria-label="Framed banner"></div>
  </main>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="wrap">
      <div class="brands">
        <img src="/public/assets/img/footer1/1.png" alt="brand1">
        <img src="/public/assets/img/footer1/2.png" alt="brand2">
        <img src="/public/assets/img/footer1/3.png" alt="brand3">
        <img src="/public/assets/img/footer1/4.png" alt="brand4">
        <img src="/public/assets/img/footer1/5.png" alt="brand5">
        <img src="/public/assets/img/footer1/6.png" alt="brand6">
        <img src="/public/assets/img/footer1/7.png" alt="brand7">
        <img src="/public/assets/img/footer1/8.png" alt="brand8">
        <img src="/public/assets/img/footer1/9.png" alt="brand9">
        <img src="/public/assets/img/footer1/10.png" alt="brand10">
        <img src="/public/assets/img/footer1/11.png" alt="brand11">
        <img src="/public/assets/img/footer1/12.png" alt="brand12">
      </div>
      <div class="f-links">关于我们 | 帮助中心 | 售后服务 | 商务合作 | 友情链接</div>
      <div class="copy">版权所有 ©2010-2025 保留所有权</div>
    </div>
  </footer>

<script src="index.js"></script>
<script src="/public/includes/navbar.js"></script>
