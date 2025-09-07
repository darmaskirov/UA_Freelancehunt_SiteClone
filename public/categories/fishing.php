<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>电子 — Game Catalog</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<link rel="stylesheet" href="/public/includes/navbar.css">
<link rel="stylesheet" href="/public/categories/game.css">
<style>
  /* CARDS GRID */
.grid{
  display:grid;
  grid-template-columns: repeat(5, minmax(0,1fr));
  gap:16px;
}

@media (max-width: 1100px){
  .grid{ grid-template-columns: repeat(4, minmax(0,1fr)); }
}
@media (max-width: 900px){
  .grid{ grid-template-columns: repeat(3, minmax(0,1fr)); }
}
@media (max-width: 640px){
  .grid{ grid-template-columns: repeat(2, minmax(0,1fr)); }
}

</style>
<main class="panel">
  <!-- Provider chips + search -->
  <div class="providers" id="providers"></div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active">全部馆</button>
    <button class="tab">热门玩法</button>
    <button class="tab">我的收藏</button>
  </div>

  <!-- Cards grid -->
  <section class="grid" id="grid"></section>

  <!-- Pagination -->
  <nav class="pager" id="pager" aria-label="pagination"></nav>
</main>



<script src='../public/categories/game.js'></script>
<script src='../public/includes/navbar.js'></script>
</body>
</html>
