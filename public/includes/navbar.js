  const chip=document.getElementById('userChip');
  const menu=document.getElementById('accMenu');
  chip?.addEventListener('click',e=>{e.stopPropagation();menu.classList.toggle('open')});
  document.addEventListener('click',()=>menu.classList.remove('open'));
  document.querySelectorAll('.has-dropdown').forEach(dd=>{
    const prev=dd.querySelector('.scroll-prev');
    const next=dd.querySelector('.scroll-next');
    const cont=dd.querySelector('.scroll-container');
    prev?.addEventListener('click',()=>cont.scrollBy({left:-150,behavior:'smooth'}));
    next?.addEventListener('click',()=>cont.scrollBy({left:150,behavior:'smooth'}));
  });