<?php
// live.php — Live Casino clone page (categories with PNG sprite + providers grid)
require_once $_SERVER['DOCUMENT_ROOT'].'/dfbiu/app/boot_session.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/dfbiu/public/includes/navbar6.php';
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>真人娱乐场</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preload" as="image" href="/dfbiu/public/assets/img/category-CMHPLGhY.png">
<style>
:root{ --bg:#e6f1fb; --text:#2a3b4f; --blue:#2e90ff; --border:#dbe8f7; --hover:#f3f7ff; --shadow:0 12px 28px rgba(32,74,128,.10); --wrap: min(1200px,96vw); }
*{box-sizing:border-box}
body{margin:0;background:#eaf4ff;color:var(--text);font-family:Inter,system-ui,"Noto Sans SC",sans-serif}
.wrap{width:var(--wrap);margin:20px auto 40px}

/* categories bar */
.catbar{display:flex;justify-content:center;align-items:center;margin:8px auto 22px}
.cats{display:flex;gap:28px;flex-wrap:wrap;justify-content:center}
.cat{display:grid;place-items:center;gap:6px;text-decoration:none;color:var(--text);font-size:14px}
.cat .ico{width:54px;height:54px;border-radius:16px;background:#fff;border:1px solid var(--border);box-shadow:var(--shadow);display:grid;place-items:center}
.sprite{width:36px;height:36px;background-image:url("/dfbiu/public/assets/img/category-CMHPLGhY.png");background-repeat:no-repeat;background-size:auto;background-position:var(--x,0) var(--y,0)}
/* tune sprite offsets to your PNG (quickly in DevTools) */
.ic-bjl{--x:   0px; --y:  0px;} /* 百家乐 */
.ic-lp {--x: -40px; --y:  0px;} /* 轮盘 */
.ic-slt{--x: -80px; --y:  0px;} /* 老虎机 */
.ic-lhd{--x:-120px; --y:  0px;} /* 龙虎 */
.ic-nn {--x:-160px; --y:  0px;} /* 牛牛 */
.ic-zjh{--x:-200px; --y:  0px;} /* 炸金花 */
.ic-sg {--x:-240px; --y:  0px;} /* 三公 */

/* providers grid */
.panel{background:#f5f8fd;border:1px solid var(--border);border-radius:16px;padding:24px}
.grid{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:18px;justify-items:center}
.card{display:grid;gap:8px;justify-items:center;padding:10px}
.logo{width:84px;height:60px;border-radius:12px;border:1px solid var(--border);background:#fff;display:grid;place-items:center;box-shadow:var(--shadow)}
.logo img{max-width:76px;max-height:46px;display:block}
.card small{font-size:12px;color:#6b768f}
.card:hover{transform:translateY(-2px)}
.card.active{outline:2px solid #9c7bf5;outline-offset:4px;border-radius:14px}

/* CTA */
.actions{display:flex;justify-content:center;margin:26px 0}
.btn{border:0;border-radius:24px;padding:11px 26px;background:linear-gradient(#6ecbff,#2e90ff);color:#fff;font-weight:700;box-shadow:var(--shadow);cursor:pointer}
.btn:active{transform:translateY(1px)}

@media (max-width:900px){ .grid{grid-template-columns:repeat(3,1fr)} }
@media (max-width:560px){ .grid{grid-template-columns:repeat(2,1fr)} }
</style>
</head>
<body>

<div class="wrap">

  <!-- TOP categories from PNG sprite -->
  <nav class="catbar">
    <div class="cats">
      <a class="cat" href="#"><div class="ico"><i class="sprite ic-bjl"></i></div><span>百家乐</span></a>
      <a class="cat" href="#"><div class="ico"><i class="sprite ic-lp"></i></div><span>轮盘</span></a>
      <a class="cat" href="#"><div class="ico"><i class="sprite ic-slt"></i></div><span>老虎机</span></a>
      <a class="cat" href="#"><div class="ico"><i class="sprite ic-lhd"></i></div><span>龙虎</span></a>
      <a class="cat" href="#"><div class="ico"><i class="sprite ic-nn"></i></div><span>牛牛</span></a>
      <a class="cat" href="#"><div class="ico"><i class="sprite ic-zjh"></i></div><span>炸金花</span></a>
      <a class="cat" href="#"><div class="ico"><i class="sprite ic-sg"></i></div><span>三公</span></a>
    </div>
  </nav>

  <!-- Providers grid (put PNGs for each logo in these paths) -->
  <section class="panel">
    <div class="grid">
      <?php
      $providers = [
        ['DG真人','/dfbiu/public/assets/img/categories/live/dg.png'],
        ['欧博视讯','/dfbiu/public/assets/img/categories/live/ob.png'],
        ['AG视讯','/dfbiu/public/assets/img/categories/live/ag.png'],
        ['完美真人','/dfbiu/public/assets/img/categories/live/wm.png'],
        ['沙龙SA','/dfbiu/public/assets/img/categories/live/sa.png'],
        ['SEXY性感真人','/dfbiu/public/assets/img/categories/live/sexy.png'],
        ['BG真人','/dfbiu/public/assets/img/categories/live/bg.png'],
        ['WE真人','/dfbiu/public/assets/img/categories/live/we.png'],
        ['EVO真人','/dfbiu/public/assets/img/categories/live/evo.png'],
        ['BET真人','/dfbiu/public/assets/img/categories/live/bet.png'],
        ['DG视讯','/dfbiu/public/assets/img/categories/live/dg-edu.png'],
      ];
      foreach ($providers as [$title,$src]): ?>
        <div class="card">
          <div class="logo">
            <img src="<?=htmlspecialchars($src)?>" alt="<?=htmlspecialchars($title)?>" onerror="this.src='/dfbiu/public/assets/img/vue.png'">
          </div>
          <small><?=htmlspecialchars($title)?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="actions">
    <button class="btn" onclick="location.href='/dfbiu/games/live/launch.php'">进入游戏</button>
  </div>

  <p style="text-align:center;font-size:12px;color:#8a94a8">
    PNG спрайт іконок: <code>/dfbiu/public/assets/img/category-CMHPLGhY.png</code> – якщо відступи не збігаються,
    підкоригуй <code>--x/--y</code> у класах <code>.ic-*</code> в CSS (через DevTools).
  </p>
</div>

<script>
  // click to highlight selected provider
  document.querySelectorAll('.card').forEach(c=>{
    c.addEventListener('click', ()=>{
      document.querySelectorAll('.card').forEach(x=>x.classList.remove('active'));
      c.classList.add('active');
    });
  });
</script>
</body>
</html>
