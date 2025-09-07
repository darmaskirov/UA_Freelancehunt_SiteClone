  // tabs iOS / Android
  const tabs = document.querySelectorAll('.tab');
  const title = document.getElementById('dl-title');
  const desc  = document.getElementById('dl-desc');
  tabs.forEach(t=>{
    t.addEventListener('click', ()=>{
      tabs.forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      if(t.dataset.tab==='ios'){
        title.textContent='iOS APP';
        desc.textContent='作为基于云平台的服务，我们更加重视产品的安全性能。每一条数据均加密并多场景备份，出现风险也能快速恢复。';
      }else{
        title.textContent='Android APP';
        desc.textContent='原生 Android 高性能构建，安全稳定，多场景数据加密备份，秒级恢复。';
      }
    });
  });