/** ========= CONFIG ========= **/
const PAGE_SIZE = 10;

// Провайдеры и количество страниц
const PROVIDERS = [
  ["CQ9电子", 28],
  ["DB电子", 5],
  ["FUN GAME", 13],
  ["PP电子", 50],
  ["TP电子", 29],
  ["BB电子", 7],
  ["JOKER电子", 23],
  ["KA电子", 70],
  ["MGPLUS", 38],
  ["PG电子", 11],
  ["PT电子", 13],
  ["JDB电子", 13],
  ["发财电子", 5],
  ["大满贯电子", 8],
  ["吉利电子", 8],
  ["PlayStar", 9],
  ["SW电子", 32],
  ["GPS(新)", 2],
  ["AceWin电子", 6],
  ["SG电子", 12],
  ["SPINIX电子", 6],
  ["CG电子", 13],
  ["RSG电子", 9],
  ["DG电子", 8],
  ["HW电子", 13],
  ["Gemini电子", 3],
  ["GD电子", 3],
  ["AG电子", 17],
];

const assigned = {};   // { providerName: [gameObj, ...] }
let allImages = [];    // из manifest.json

let currentProvider = PROVIDERS[0][0];
let currentPage = 1;

/** ========= UI helpers ========= **/
const $providers = document.getElementById('providers');
const $grid = document.getElementById('grid');
const $pager = document.getElementById('pager');

function el(tag, cls, html){
  const n = document.createElement(tag);
  if (cls) n.className = cls;
  if (html != null) n.innerHTML = html;
  return n;
}
function humanCount(provider){
  const row = PROVIDERS.find(p=>p[0]===provider);
  return (row ? row[1] : 1) * PAGE_SIZE;
}

/** ========= BUILD PROVIDER CHIPS ========= **/
function buildProviderChips(){
  $providers.innerHTML = "";
  for (const [name] of PROVIDERS){
    const chip = el('button','chip', name);
    if (name === currentProvider) chip.classList.add('blue');
    chip.addEventListener('click', ()=>{
      currentProvider = name;
      currentPage = 1;
      document.querySelectorAll('.chip').forEach(c=>c.classList.remove('blue'));
      chip.classList.add('blue');
      render();
    });
    $providers.appendChild(chip);
  }
  const search = el('div','search',
    `<input placeholder="Please Input"/>
     <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
       <path d="M21 21l-4.2-4.2M10.8 18a7.2 7.2 0 1 1 0-14.4 7.2 7.2 0 0 1 0 14.4z"
             stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
     </svg>`);
  $providers.appendChild(search);
}

/** ========= PAGINATION (фикс от роста страниц) ========= **/
function buildPager(totalPages){
  $pager.replaceChildren(); // жёсткая очистка

  const totalItems = humanCount(currentProvider);
  const label = el('span', '', `共 ${totalItems} 条`);
  $pager.appendChild(label);

  const addBtn = (label, page, opts={})=>{
    const {disabled=false, active=false, ellipsis=false} = opts;
    const n = el('div', 'page' + (active?' active':'') + (ellipsis?' ellipsis':''), label);
    if (!ellipsis && !disabled){
      n.addEventListener('click', ()=>{
        const maxPage = Math.max(1, totalPages);
        currentPage = Math.min(Math.max(1, page), maxPage);
        renderGrid(); // пересбор сетки и пейджера
      });
    }
    $pager.appendChild(n);
  };

  const win = 3;
  const start = Math.max(1, currentPage - win);
  const end   = Math.min(totalPages, currentPage + win);

  if (start > 1){ addBtn('1', 1); if (start > 2) addBtn('…', null, {ellipsis:true}); }
  for (let p=start; p<=end; p++) addBtn(String(p), p, {active:p===currentPage});
  if (end < totalPages){ if (end < totalPages-1) addBtn('…', null, {ellipsis:true}); addBtn(String(totalPages), totalPages); }
}

/** ========= GRID RENDER ========= **/
function renderGrid(){
  const row = PROVIDERS.find(p=>p[0]===currentProvider);
  const totalPages = row ? row[1] : 1;         // фиксированное число страниц
  const total = totalPages * PAGE_SIZE;

  const startIdx = (currentPage-1)*PAGE_SIZE;
  const endIdx   = Math.min(startIdx + PAGE_SIZE, total);

  $grid.innerHTML = "";
  const list = assigned[currentProvider] || [];

  for (let i=startIdx; i<endIdx; i++){
    const g = list[i]; // undefined => заглушка
    const card = el('article', 'card' + (g ? '' : ' placeholder'));
    const title = g ? (g.title || 'Game') : `Game #${i+1}`;
    const meta  = g ? (g.provider || currentProvider) : currentProvider;
    const bg = g ? `style="background-image:url('${g.img}')" ` : "";
    card.innerHTML = `
      <div class="thumb" ${bg}></div>
      <div class="badges"><div class="badge star" title="收藏">★</div></div>
      <div class="title">${title}</div>
      <div class="meta">${meta}</div>
    `;
    $grid.appendChild(card);
  }

  $grid.querySelectorAll('.badge.star').forEach(b=>{
    b.addEventListener('click', e=> e.currentTarget.classList.toggle('active'));
  });

  buildPager(totalPages);
}

/** ========= DISTRIBUTION ========= **/
// Заполняем ТОЛЬКО 5 провайдеров полностью: CQ9电子, DB电子, FUN GAME, PG电子, PT电子
function distributeImages(){
  const caps = {};      // capacity per provider
  const buckets = {};
  const names = PROVIDERS.map(p=>p[0]);

  for (const [name, pages] of PROVIDERS){
    caps[name] = pages * PAGE_SIZE;
    buckets[name] = [];
  }

  const fillThese = ["CQ9电子","DB电子","FUN GAME","PG电子","PT电子"];
  let cursor = 0;

  for (const prov of fillThese){
    if (!caps[prov]) continue;
    const need = caps[prov];
    const take = Math.min(need, allImages.length - cursor);
    for (let i=0; i<take; i++){
      const g = allImages[cursor++];
      g.provider = prov;
      buckets[prov].push(g);
    }
    if (cursor >= allImages.length) break;
  }
  // Остальные провайдеры остаются пустыми — будут заглушки

  for (const n of names) assigned[n] = buckets[n];
}

/** ========= MAIN ========= **/
async function init(){
  buildProviderChips();
  try{
    const r = await fetch('/dfbiu/out_images/manifest.json', {cache:'no-store'});
    const data = await r.json();
    allImages = Array.isArray(data.games) ? data.games : [];
  }catch(e){
    console.warn('manifest load error, continue with placeholders', e);
    allImages = [];
  }
  distributeImages();
  render();
}
function render(){ renderGrid(); }

init();