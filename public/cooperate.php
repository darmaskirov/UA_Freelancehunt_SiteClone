<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>合作伙伴 — pc.df clone</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#eaf4ff; --text:#2c3b4f; --muted:#8093a7;
  --blue:#2f8eff; --blue2:#55bbff;
  --border:#e4eef7; --shadow:0 14px 28px rgba(36,92,160,.15);
}
*{box-sizing:border-box}
body{
  margin:0; font-family:"Inter","Noto Sans SC",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
  background:radial-gradient(1200px 240px at 50% -120px, rgba(71,165,255,.20), transparent), var(--bg);
  color:var(--text);
}
.wrap{width:min(1220px,96vw); margin:0 auto}

/* HEADER */
.top{height:70px; display:flex; align-items:center; gap:24px}
.logo{width:38px;height:24px;border-radius:6px;background:linear-gradient(180deg,#66c2ff,#2e90ff);box-shadow:0 6px 16px rgba(46,144,255,.25)}
.nav{display:flex;gap:20px;font-weight:700}
.nav a{color:#354c66;text-decoration:none}
.sp{flex:1}
.right{display:flex;align-items:center;gap:10px}
.pill{height:32px;padding:0 14px;border-radius:999px;border:1px solid #dbe9fb;background:linear-gradient(180deg,#f5f9ff,#eaf2ff);color:#5f7aa0;font-weight:700}
.pill.primary{background:linear-gradient(180deg,var(--blue2),var(--blue));color:#fff;border-color:transparent;box-shadow:0 10px 18px rgba(47,142,255,.3)}
.pill.ghost{color:#7a8ea6}
.lang{background:#fff;border:1px solid var(--border);border-radius:999px;padding:6px 10px;box-shadow:var(--shadow)}

/* MAIN */
.hero{margin:40px auto;text-align:center}
.hero h1{font-size:32px;font-weight:700;margin:0;color:#7d8ea4}
.hero p{margin:16px auto;max-width:720px;line-height:1.6;color:#516579}

.cards{display:flex;gap:18px;justify-content:center;flex-wrap:wrap;margin:30px auto}
.card{
  width:260px;background:#fff;border:1px solid var(--border);border-radius:16px;
  box-shadow:var(--shadow);padding:20px;text-align:center;display:flex;flex-direction:column;gap:12px
}
.card .ico{width:42px;height:42px;border-radius:50%;margin:0 auto;background:#eaf4ff url('') center/cover no-repeat}
.card h3{margin:0;font-size:18px}
.btns{display:flex;gap:10px;justify-content:center}
.btn{
  height:32px;padding:0 14px;border-radius:999px;border:0;cursor:pointer;font-weight:700;
}
.btn.blue{background:linear-gradient(180deg,var(--blue2),var(--blue));color:#fff;box-shadow:0 6px 14px rgba(47,142,255,.3)}
.btn.ghost{background:#fff;border:1px solid #dce7f5;color:#56697f}

@media(max-width:800px){
  .cards{flex-direction:column;align-items:center}
}
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>
<link rel="stylesheet" href="/public/includes/navbar.css">

<main class="wrap">
  <section class="hero">
    <h1>Enterprise-level R&amp;D management tools</h1>
    <p>
      In the three years since its launch, Magic Cube has continuously helped Internet teams through continuous innovation and improved team collaboration efficiency. 
      At the same time, Magic Cube is also the earliest product design collaboration platform in China, and was the first to realize automatic cloud-based annotation of design drawings, online collaboration, and synchronization of resource files.
    </p>
  </section>

  <section class="cards">
    <div class="card">
      <div class="ico"></div>
      <h3>WeChat</h3>
      <div class="btns">
        <button class="btn blue">URL Button</button>
        <button class="btn ghost">Copy Button</button>
      </div>
    </div>
    <div class="card">
      <div class="ico"></div>
      <h3>WeChat</h3>
      <div class="btns">
        <button class="btn blue">URL Button</button>
        <button class="btn ghost">Copy Button</button>
      </div>
    </div>
    <div class="card">
      <div class="ico"></div>
      <h3>WeChat</h3>
      <div class="btns">
        <button class="btn blue">URL Button</button>
        <button class="btn ghost">Copy Button</button>
      </div>
    </div>
  </section>
</main>

</body>
</html>
