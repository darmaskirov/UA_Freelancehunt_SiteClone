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
  <meta charset="utf-8" />
  <title>游戏馆 — 硬编码分发</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Підключу твій CSS; при бажанні можеш прибрати цей рядок -->
  <link rel="stylesheet" href="/public/categories/game.css">
  <style>
    /* Мінімальні стилі, щоб точно виглядало як треба навіть без зовнішнього CSS */
    .wrap{width:min(1200px,96vw);margin:28px auto}
    .panel{background:#fff;border:1px solid #e7eef8;border-radius:16px;box-shadow:0 12px 28px rgba(32,74,128,.10);padding:18px}
    .providers{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}
    .chip{border:1px solid #e7eef8;background:#f7fbff;color:#294059;border-radius:999px;padding:8px 14px;font-size:14px;cursor:pointer}
    .chip.active{background:#e9f3ff;border-color:#cfe5ff;color:#0b5bd7;font-weight:600}
    .tabs{display:flex;gap:10px;margin:12px 0 14px}
    .tab{border:1px solid #e7eef8;background:#f7fbff;padding:8px 14px;border-radius:999px;cursor:pointer;font-size:14px}
    .tab.active{background:#e9f3ff;border-color:#cfe5ff;color:#0b5bd7;font-weight:600}

    .grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}
    .card{background:#fff;border:1px solid #e7eef8;border-radius:14px;box-shadow:0 8px 22px rgba(24,65,120,.08);padding:10px;position:relative}
    .thumb{width:100%;aspect-ratio:1/1;border-radius:10px;overflow:hidden;background:#e9edf4}
    .thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .badge.star{position:absolute;top:10px;right:10px;width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:#fff;border:1px solid #e7eef8;box-shadow:0 4px 14px rgba(0,0,0,.06);cursor:pointer}
    .title{margin-top:10px;font-weight:600}
    .meta{margin-top:4px;color:#7e8aa6;font-size:12px}

    .pager{display:flex;align-items:center;gap:8px;justify-content:center;margin:16px 0 4px}
    .page{min-width:32px;height:32px;display:grid;place-items:center;border:1px solid #e7eef8;border-radius:8px;background:#fff;cursor:pointer}
    .page.active{background:#0b5bd7;color:#fff;border-color:#0b5bd7}
    .page.ellipsis{cursor:default;background:transparent;border:0}
    #total{ text-align:center;color:#7e8aa6;font-size:12px;margin-bottom:4px }

    /* overlay з кнопкою при наведенні */
.card{position:relative}
.badge.star{z-index:2}

.hover-cta{
  position:absolute; inset:0; border-radius:14px;
  display:flex; align-items:center; justify-content:center;
  background:linear-gradient(to bottom, rgba(17,24,39,0) 0%, rgba(17,24,39,.35) 60%, rgba(17,24,39,.55) 100%);
  opacity:0; transition:opacity .2s ease;
  pointer-events:none;          /* щоб вся картка лишалась клікабельною */
}
.card:hover .hover-cta{ opacity:1; }

.btn-play{
  pointer-events:auto;          /* кнопка клікабельна */
  padding:10px 16px; border:0; border-radius:999px;
  font-weight:600; background:#0b5bd7; color:#fff;
  box-shadow:0 6px 18px rgba(11,91,215,.35); cursor:pointer;
}
.btn-play:hover{ filter:brightness(1.05); }
.btn-play:active{ transform:translateY(1px); }


    @media (max-width:900px){ .grid{grid-template-columns:repeat(3,minmax(0,1fr))} }
    @media (max-width:640px){ .grid{grid-template-columns:repeat(2,minmax(0,1fr))} }

    /* ==== FOOTER LOGOS ===== */
.footer-logos{
  display:grid;
  grid-template-columns: repeat(6, minmax(0,1fr));
  gap:28px 32px;                /* вертикальний × горизонтальний інтервали */
  align-items:center;
  justify-items:center;
  padding:32px 0 8px;
}

@media (max-width:1100px){ .footer-logos{ grid-template-columns:repeat(5,1fr); } }
@media (max-width:900px) { .footer-logos{ grid-template-columns:repeat(4,1fr); } }
@media (max-width:700px) { .footer-logos{ grid-template-columns:repeat(3,1fr); } }
@media (max-width:480px) { .footer-logos{ grid-template-columns:repeat(2,1fr); } }

/* Однакова “комірка” для кожного лого — вирівнює оптичний розмір */
.footer-logos .logo-box{
  height:64px;                  /* однакова висота рядка */
  width:100%;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:8px 10px;
  border-radius:12px;
  background:rgba(255,255,255,.03);       /* легкий підсвіт на темному фоні */
  border:1px solid rgba(255,255,255,.06);
  transition:transform .2s ease, background .2s ease, border-color .2s ease;
}

/* Саме зображення лого */
.footer-logos img,
.footer-logos svg{
  max-height:36px;              /* уніфікує висоту логотипів */
  width:auto;
  display:block;
  object-fit:contain;
  filter:grayscale(1) opacity(.75) contrast(1.05);
  transition:filter .2s ease, opacity .2s ease;
}

/* Hover — легкий підйом і повернення кольору */
.footer-logos .logo-box:hover{
  transform:translateY(-2px);
  background:rgba(255,255,255,.06);
  border-color:rgba(255,255,255,.12);
}
.footer-logos .logo-box:hover img,
.footer-logos .logo-box:hover svg{
  filter:none; 
  opacity:1;
}

/* Якщо якійсь логотип надто широкий — обмежити */
.footer-logos img.wide { max-width:160px; }

/* Якщо фон світлий — прибери сірування: додай клас no-gray до потрібних */
.footer-logos .no-gray img,
.footer-logos .no-gray svg{ filter:none; opacity:.9; }

  </style>
</head>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/public/includes/navbar6.php'; ?>
<link rel="stylesheet" href="/public/includes/navbar.css">
<body>
  <div class="wrap">
    <main class="panel">
      <div id="providers" class="providers"></div>

      <div class="tabs">
        <button class="tab active">全部馆</button>
        <button class="tab">热门玩法</button>
        <button class="tab">我的收藏</button>
      </div>

      <section id="grid" class="grid"></section>
      <nav id="pager" class="pager"></nav>
      <div id="total"></div>
    </main>
  </div>
  <footer class="footer">
    <div class="wrap">
      <div class="brands">
        <img src="/public/assets/img/footer1/1.png" alt="brand1" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/2.png" alt="brand2" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/3.png" alt="brand3" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/4.png" alt="brand4" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/5.png" alt="brand5" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/6.png" alt="brand6" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/7.png" alt="brand7" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/8.png" alt="brand8" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/9.png" alt="brand9" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/10.png" alt="brand10" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/11.png" alt="brand11" width='120px' height='60px'>
        <img src="/public/assets/img/footer1/12.png" alt="brand12" width='120px' height='60px'>
      </div><br>
      <div class="f-links" style='text-align: center;'>关于我们 | 帮助中心 | 售后服务 | 商务合作 | 友情链接</div>
      <div class="copy">版权所有 ©2010-2025 保留所有权</div>
    </div>
  </footer>
  <script>
    // ===== CONFIG =====
    const APP_BASE = "../";
    const PAGE_SIZE = 10;
    const PROVIDERS = [
      ["CQ9电子", 28], ["DB电子", 5], ["FUN GAME", 13], ["PP电子", 50], ["TP电子", 29],
      ["BB电子", 7], ["JOKER电子", 23], ["KA电子", 70], ["MGPLUS", 38], ["PG电子", 11],
      ["PT电子", 13], ["JDB电子", 13], ["发财电子", 5], ["大满贯电子", 8], ["吉利电子", 8],
      ["PlayStar", 9], ["SW电子", 32], ["GPS(新)", 2], ["AceWin电子", 6], ["SG电子", 12],
      ["SPINIX电子", 6], ["CG电子", 13], ["RSG电子", 9], ["DG电子", 8], ["HW电子", 13],
      ["Gemini电子", 3], ["GD电子", 3], ["AG电子", 17],
    ];

    // ===== DATA (з твого manifest.json, шляхи уніфіковано) =====
    const LIST = [
      ["灌篮高手","out_images/images/100/All__灌篮高手.png"],
      ["火影忍者","out_images/images/100/All__火影忍者.png"],
      ["火辣辣","out_images/images/100/All__火辣辣.png"],
      ["熊猫财富","out_images/images/100/All__熊猫财富.png"],
      ["爱尔兰精灵","out_images/images/100/All__爱尔兰精灵.png"],
      ["狂欢","out_images/images/100/All__狂欢.png"],
      ["狂野角斗士","out_images/images/100/All__狂野角斗士.png"],
      ["狩猎之王","out_images/images/100/All__狩猎之王.png"],
      ["王者野牛","out_images/images/100/All__王者野牛.png"],
      ["玛雅","out_images/images/100/All__玛雅.png"],
      ["现代战争","out_images/images/100/All__现代战争.png"],
      ["瑞狗迎春","out_images/images/100/All__瑞狗迎春.png"],
      ["甜入心扉","out_images/images/100/All__甜入心扉.png"],
      ["甜心盛宴圣诞","out_images/images/100/All__甜心盛宴圣诞.png"],
      ["甜水绿洲","out_images/images/100/All__甜水绿洲.png"],
      ["甜蜜蜜","out_images/images/100/All__甜蜜蜜.png"],
      ["疯狂七","out_images/images/100/All__疯狂七.png"],
      ["疯狂小玛莉","out_images/images/100/All__疯狂小玛莉.png"],
      ["白蛇传","out_images/images/100/All__白蛇传.png"],
      ["百变猴子","out_images/images/100/All__百变猴子.png"],
      ["百变猴子2","out_images/images/100/All__百变猴子2.png"],
      ["盗墓笔记","out_images/images/100/All__盗墓笔记.png"],
      ["盗墓笔记2","out_images/images/100/All__盗墓笔记2.png"],
      ["真金拉霸","out_images/images/100/All__真金拉霸.png"],
      ["矮人黄金豪华版","out_images/images/100/All__矮人黄金豪华版.png"],
      ["神庙探险","out_images/images/100/All__神庙探险.png"],
      ["神秘东方","out_images/images/100/All__神秘东方.png"],
      ["神雕侠侣","out_images/images/100/All__神雕侠侣.png"],
      ["福禄寿","out_images/images/100/All__福禄寿.png"],
      ["笑傲江湖","out_images/images/100/All__笑傲江湖.png"],
      ["粉红女郎","out_images/images/100/All__粉红女郎.png"],
      ["精灵翅膀","out_images/images/100/All__精灵翅膀.png"],
      ["红楼梦","out_images/images/100/All__红楼梦.png"],
      ["红火暴击","out_images/images/100/All__红火暴击.png"],
      ["经典拉霸","out_images/images/100/All__经典拉霸.png"],
      ["经典拉霸2","out_images/images/100/All__经典拉霸2.png"],
      ["维加斯之夜","out_images/images/100/All__维加斯之夜.png"],
      ["绿野仙踪","out_images/images/100/All__绿野仙踪.png"],
      ["群星闪耀","out_images/images/100/All__群星闪耀.png"],
      ["船长宝藏","out_images/images/100/All__船长宝藏.png"],
      ["芝加哥2","out_images/images/100/All__芝加哥2.png"],
      ["荒野大镖客","out_images/images/100/All__荒野大镖客.png"],
      ["荣耀王者","out_images/images/100/All__荣耀王者.png"],
      ["蜘蛛侠","out_images/images/100/All__蜘蛛侠.png"],
      ["街头霸王","out_images/images/100/All__街头霸王.png"],
      ["街机水浒传","out_images/images/100/All__街机水浒传.png"],
      ["西游","out_images/images/100/All__西游.png"],
      ["西游争霸","out_images/images/100/All__西游争霸.png"],
      ["西游记","out_images/images/100/All__西游记.png"],
      ["西部牛仔","out_images/images/100/All__西部牛仔.png"],
      ["角斗士","out_images/images/100/All__角斗士.png"],
      ["财富连连","out_images/images/100/All__财富连连.png"],
      ["财神到","out_images/images/100/All__财神到.png"],
      ["财神的宝藏","out_images/images/100/All__财神的宝藏.png"],
      ["财神运财","out_images/images/100/All__财神运财.png"],
      ["财神黄金","out_images/images/100/All__财神黄金.png"],
      ["超炫小丑","out_images/images/100/All__超炫小丑.png"],
      ["超级辣","out_images/images/100/All__超级辣.png"],
      ["达芬奇宝藏","out_images/images/100/All__达芬奇宝藏.png"],
      ["速度与激情","out_images/images/100/All__速度与激情.png"],
      ["酷猴战士","out_images/images/100/All__酷猴战士.png"],
      ["野狼黄金","out_images/images/100/All__野狼黄金.png"],
      ["野生动物园","out_images/images/100/All__野生动物园.png"],
      ["野精灵","out_images/images/100/All__野精灵.png"],
      ["金刚","out_images/images/100/All__金刚.png"],
      ["金吉报喜","out_images/images/100/All__金吉报喜.png"],
      ["金狗旺财","out_images/images/100/All__金狗旺财.png"],
      ["金瓶梅","out_images/images/100/All__金瓶梅.png"],
      ["金瓶梅2","out_images/images/100/All__金瓶梅2.png"],
      ["金碧辉煌","out_images/images/100/All__金碧辉煌.png"],
      ["金色道虎","out_images/images/100/All__金色道虎.png"],
      ["金色道龙","out_images/images/100/All__金色道龙.png"],
      ["金靴争霸","out_images/images/100/All__金靴争霸.png"],
      ["金鼓迎福","out_images/images/100/All__金鼓迎福.png"],
      ["金龙会","out_images/images/100/All__金龙会.png"],
      ["金龟子女王","out_images/images/100/All__金龟子女王.png"],
      ["钻石之恋","out_images/images/100/All__钻石之恋.png"],
      ["钻石永恒","out_images/images/100/All__钻石永恒.png"],
      ["钻石罢工","out_images/images/100/All__钻石罢工.png"],
      ["闹元宵","out_images/images/100/All__闹元宵.png"],
      ["阿兹特克","out_images/images/100/All__阿兹特克.png"],
      ["阿兹特克秘宝","out_images/images/100/All__阿兹特克秘宝.png"],
      ["阿凡达","out_images/images/100/All__阿凡达.png"],
      ["阿拉丁和巫师","out_images/images/100/All__阿拉丁和巫师.png"],
      ["陈师傅的财富","out_images/images/100/All__陈师傅的财富.png"],
      ["额外多汁","out_images/images/100/All__额外多汁.png"],
      ["马上有钱","out_images/images/100/All__马上有钱.png"],
      ["魔力维加斯","out_images/images/100/All__魔力维加斯.png"],
      ["鸟叔","out_images/images/100/All__鸟叔.png"],
      ["鹊桥会","out_images/images/100/All__鹊桥会.png"],
      ["鹿鼎记","out_images/images/100/All__鹿鼎记.png"],
      ["麻将来了3","out_images/images/100/All__麻将来了3.png"],
      ["黄金列车","out_images/images/100/All__黄金列车.png"],
      ["黄金右脚","out_images/images/100/All__黄金右脚.png"],
      ["黄金女王","out_images/images/100/All__黄金女王.png"],
      ["黄金矿工","out_images/images/100/All__黄金矿工.png"],
      ["黄金野马","out_images/images/100/All__黄金野马.png"],
      ["黑手党","out_images/images/100/All__黑手党.png"],
      ["黑珍珠号","out_images/images/100/All__黑珍珠号.png"],
      ["龙之财富","out_images/images/100/All__龙之财富.png"],
      ["龙凤呈祥","out_images/images/100/All__龙凤呈祥.png"],
      ["龙龙龙","out_images/images/100/All__龙龙龙.png"],
    ];

 // ===== helpers =====
const abs = p => p.startsWith("/") ? p : (p.startsWith("/") ? APP_BASE+p : APP_BASE+"/"+p);
function titleFromPath(p, fallback){ try{ const f = decodeURIComponent(p.split("/").pop()); return fallback || f.replace(/^All__/, "").replace(/\.[a-z0-9]+$/i, ""); }catch(_){ return fallback || "Game"; } }
// NEW: куди веде клік по картці / кнопці
const linkFor = (g) => `${APP_BASE}/play?title=${encodeURIComponent(g?.title || "")}`;

// ===== розподіл round-robin з урахуванням місткості (pages*PAGE_SIZE) =====
const names = PROVIDERS.map(p=>p[0]);
const capacity = Object.fromEntries(PROVIDERS.map(([n,pages])=>[n, pages*PAGE_SIZE]));
const assigned = Object.fromEntries(names.map(n=>[n, []]));
let idx = 0;
for (const [title, rel] of LIST){
  // знайти наступного провайдера з вільною місткістю
  let tries = 0;
  while (tries < names.length && capacity[names[idx]] <= 0){ idx = (idx + 1) % names.length; tries++; }
  const prov = names[idx];
  if (capacity[prov] <= 0) break; // усі заповнені
  assigned[prov].push({ title: title || titleFromPath(rel), img: abs(rel) });
  capacity[prov]--; idx = (idx + 1) % names.length;
}

// ===== UI state =====
let currentProvider = names[0];
let currentPage = 1;
const $providers = document.getElementById("providers");
const $grid = document.getElementById("grid");
const $pager = document.getElementById("pager");
const $total = document.getElementById("total");

function el(tag, cls, txt){ const n=document.createElement(tag); if(cls) n.className=cls; if(txt!=null) n.textContent=txt; return n; }
const pagesOf = name => (PROVIDERS.find(p=>p[0]===name)?.[1] ?? 1);

// NEW: інжект стилів для hover-оверлею і кнопки
(function injectHoverStyles(){
  const css = `
  .card{position:relative}
  .badge.star{z-index:2}
  .hover-cta{position:absolute;inset:0;border-radius:14px;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(to bottom, rgba(17,24,39,0) 0%, rgba(17,24,39,.35) 60%, rgba(17,24,39,.55) 100%);
    opacity:0;transition:opacity .2s ease;pointer-events:none}
  .card:hover .hover-cta{opacity:1}
  .btn-play{pointer-events:auto;padding:10px 16px;border:0;border-radius:999px;font-weight:600;background:#0b5bd7;color:#fff;
    box-shadow:0 6px 18px rgba(11,91,215,.35);cursor:pointer}
  .btn-play:hover{filter:brightness(1.05)} .btn-play:active{transform:translateY(1px)}
  `;
  const tag = document.createElement('style'); tag.textContent = css; document.head.appendChild(tag);
})();

function buildProviderChips(){
  $providers.replaceChildren();
  for (const n of names){
    const chip = el("button","chip",n);
    if (n===currentProvider) chip.classList.add("active");
    chip.addEventListener("click",()=>{
      if (currentProvider===n) return;
      currentProvider = n; currentPage = 1;
      buildProviderChips(); render();
    });
    $providers.appendChild(chip);
  }
}

function buildPager(){
  const totalPages = pagesOf(currentProvider);
  $pager.replaceChildren();

  const addBtn = (label, page, {active=false, ellipsis=false}={})=>{
    const b = el("div","page"+(active?" active":"")+(ellipsis?" ellipsis":""), label);
    if (!ellipsis){
      b.addEventListener("click",()=>{
        const max = Math.max(1,totalPages);
        currentPage = Math.min(Math.max(1,page), max);
        renderGrid();
      });
    }
    $pager.appendChild(b);
  };

  const win=3, start=Math.max(1,currentPage-win), end=Math.min(totalPages,currentPage+win);
  if (start>1){ addBtn("1",1); if (start>2) addBtn("…",0,{ellipsis:true}); }
  for (let p=start;p<=end;p++) addBtn(String(p),p,{active:p===currentPage});
  if (end<totalPages){ if (end<totalPages-1) addBtn("…",0,{ellipsis:true}); addBtn(String(totalPages), totalPages); }

  $total.textContent = `共 ${totalPages*PAGE_SIZE} 条`;
}

// UPDATED: тепер картка клікабельна; overlay з кнопкою на hover; ★ не веде по лінку
function createCard(g, i, prov){
  // робимо саму картку <a>, щоб усе було клікабельним
  const card = document.createElement("a");
  card.className = "card";
  card.href = linkFor(g);
  card.title = g?.title || "";

  const thumb = el("div","thumb");
  const img = document.createElement("img");
  img.src = g?.img || "";
  img.alt = g?.title || "";
  img.loading = "lazy";
  thumb.appendChild(img);

  const star = el("div","badge star","☆");
  star.title = "收藏";
  star.addEventListener("click", (e)=>{
    e.preventDefault(); e.stopPropagation();
    star.textContent = star.textContent.trim()==="☆" ? "★" : "☆";
  });

  const title = el("div","title", g?.title || `Game #${i+1}`);
  const meta  = el("div","meta", prov);

  // overlay з кнопкою
  const overlay = document.createElement("div");
  overlay.className = "hover-cta";
  const btn = document.createElement("button");
  btn.className = "btn-play";
  btn.type = "button";
  btn.textContent = "开始游戏";
  btn.addEventListener("click",(e)=>{
    e.preventDefault(); e.stopPropagation();
    window.location.href = card.href;
  });
  overlay.appendChild(btn);

  card.append(thumb, star, title, meta, overlay);
  return card;
}

function renderGrid(){
  const list = assigned[currentProvider] || [];
  const totalPages = pagesOf(currentProvider);
  const start = (currentPage-1)*PAGE_SIZE;
  $grid.replaceChildren();

  for (let i=0;i<PAGE_SIZE;i++){
    // щоб на будь-якій сторінці було 10 картинок, крутимо тільки в межах провайдера
    const g = list.length ? list[(start+i) % list.length] : null;
    $grid.appendChild(createCard(g, i, currentProvider));
  }
  buildPager();
}

function render(){
  // активні чипи
  $providers.querySelectorAll(".chip").forEach(c=>c.classList.toggle("active", c.textContent.trim()===currentProvider));
  renderGrid();
}

// init
buildProviderChips();
render();

  </script>
</body>
</html>
