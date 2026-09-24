
const sidebar=document.getElementById('sidebar');
document.getElementById('menuBtn')?.addEventListener('click',()=>{
 if(!sidebar)return;
 sidebar.style.display=sidebar.style.display==='flex'?'none':'flex';
 sidebar.style.position='fixed';sidebar.style.zIndex='40';
});
document.addEventListener('click',e=>{
 const t=e.target.closest('[data-gallery]');
 if(t) document.getElementById(t.dataset.gallery)?.classList.toggle('open');
 const p=e.target.closest('[data-view]');
 if(p){const v=document.getElementById('viewer');v.querySelector('img').src=p.dataset.view;v.classList.add('open')}
 if(e.target.closest('[data-close]')) document.getElementById('viewer').classList.remove('open');
});
