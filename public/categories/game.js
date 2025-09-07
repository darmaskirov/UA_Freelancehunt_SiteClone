"use strict";

/* ====== ХАРД-ШЛЯХИ ====== */
const HARD_IMAGES = [
  "/dfbiu/out_images/images/100/All__灌篮高手.png",
  "/dfbiu/out_images/images/100/All__火影忍者.png",
  "/dfbiu/out_images/images/100/All__火辣辣.png",
  "/dfbiu/out_images/images/100/All__熊猫财富.png",
  "/dfbiu/out_images/images/100/All__爱尔兰精灵.png",
  "/dfbiu/out_images/images/100/All__疯狂7.png",
  "/dfbiu/out_images/images/100/All__疯狂假面.png",
  "/dfbiu/out_images/images/100/All__疯狂之神.png",
  "/dfbiu/out_images/images/100/All__熊猫.png",
  "/dfbiu/out_images/images/100/All__多福多寿.png",
];

/* ====== КОНФІГ ====== */
const PAGE_SIZE = 10;
const PROVIDERS = [
  ["CQ9电子", 28], ["DB电子", 5], ["FUN GAME", 13], ["PP电子", 50], ["TP电子", 29],
  ["BB电子", 7], ["JOKER电子", 23], ["KA电子", 70], ["MGPLUS", 38], ["PG电子", 11],
  ["PT电子", 13], ["JDB电子", 13], ["发财电子", 5], ["大满贯电子", 8], ["吉利电子", 8],
  ["PlayStar", 9], ["SW电子", 32], ["GPS(新)", 2], ["AceWin电子", 6], ["SG电子", 12],
  ["SPINIX电子", 6], ["CG电子", 13], ["RSG电子", 9], ["DG电子", 8], ["HW电子", 13],
  ["Gemini电子", 3], ["GD电子", 3], ["AG电子", 17],
];

/* ====== СТАН/DOM ====== */
let currentProvider = PROVIDERS[0][0];
let currentPage = 1;
const $providers = document.getElementById("providers");
const $grid = document.getElementById("grid");
const $pager = document.getElementById("pager");

function el(tag, cls, text){ const n=document.createElement(tag); if(cls) n.className=cls; if(text) n.textContent=text; return n; }
function providerIndex(name){ return Math.max(0, PROVIDERS.findIndex(p=>p[0]===name)); }
function totalPagesOf(name){ const row=PROVIDERS.find(p=>p[0]===name); return row ? row[1] : 1; }

/* ====== UI ====== */
function buildProviderChips(){
  $providers.replaceChildren();
  for (const [name] of PROVIDERS){
    const chip = el("button","chip",name);
    if (name===currentProvider) chip.classList.add("blue");
    chip.addEventListener("click", ()=>{
      if (currentProvider===name) return;
      currentProvider = name;
      currentPage = 1;
      $providers.querySelectorAll(".chip").forEach(c=>c.classList.remove("blue"));
      chip.classList.add("blue");
      renderGrid();
    });
    $providers.appendChild(chip);
  }
  const search = el("div","search");
  search.innerHTML = `<input placeholder="Please Input" />
  <svg width="18" height="18" viewBox="0 0 24 24"><path d="M21 21l-4.2-4.2M10.8 18a7.2 7.2 0 1 1 0-14.4 7.2 7.2 0 0 1 0 14.4z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>`;
  $providers.appendChild(search);
}

/* ====== ПАГІНАЦІЯ ====== */
function buildPager(){
  const totalPages = totalPagesOf(currentProvider);
  $pager.replaceChildren();
  const addBtn = (label, page, {active=false, ellipsis=false}={})=>{
    const n = el("div","page"+(active?" active":"")+(ellipsis?" ellipsis":""),label);
    if (!ellipsis){
      n.addEventListener("click",()=>{
        const max = Math.max(1,totalPages);
        currentPage = Math.min(Math.max(1,page), max);
        renderGrid();
      });
    }
    $pager.appendChild(n);
  };
  const win=3, start=Math.max(1,currentPage-win), end=Math.min(totalPages,currentPage+win);
  if (start>1){ addBtn("1",1); if (start>2) addBtn("…",0,{ellipsis:true}); }
  for (let p=start;p<=end;p++) addBtn(String(p),p,{active:p===currentPage});
  if (end<totalPages){ if (end<totalPages-1) addBtn("…",0,{ellipsis:true}); addBtn(String(totalPages),totalPages); }
}

/* ====== GRID ====== */
function createCard(imgUrl, i, provider){
  const card = el("article","card");
  const thumb = el("div","thumb");
  // ВАЖЛИВО: !important, щоб перекрити будь-який CSS фон
  thumb.style.setProperty("background", `#e9edf4 url("${imgUrl}") center / cover no-repeat`, "important");

  const badges = el("div","badges");
  const star = el("div","badge star","★");
  star.title = "收藏";
  star.addEventListener("click", e=> e.currentTarget.classList.toggle("active"));
  badges.appendChild(star);

  const title = el("div","title",`Game #${i+1}`);
  const meta  = el("div","meta",provider);
  card.append(thumb,badges,title,meta);
  return card;
}

function renderGrid(){
  const provIdx = providerIndex(currentProvider);
  const totalPages = totalPagesOf(currentProvider);

  let globalStart = 0;
  for (let i=0;i<provIdx;i++) globalStart += totalPagesOf(PROVIDERS[i][0]) * PAGE_SIZE;
  globalStart += (currentPage-1)*PAGE_SIZE;

  $grid.replaceChildren();
  const n = HARD_IMAGES.length || 1;
  for (let i=0;i<PAGE_SIZE;i++){
    const img = HARD_IMAGES[(globalStart + i) % n];
    $grid.appendChild(createCard(img, i, currentProvider));
  }
  buildPager();
}

/* ====== MAIN ====== */
function init(){
  if (!$providers || !$grid || !$pager){ console.warn("[game] DOM nodes missing"); return; }
  console.log("[game] HARD v3 loaded");
  buildProviderChips();
  renderGrid();
}
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
