// public/admin/modals.js
(function(){
  const root = document.getElementById('modals-root');
  const csrf = root?.dataset?.csrf || '';

  const open = id => document.getElementById('modal-'+id).hidden = false;
  const closeAll = () => document.querySelectorAll('.modal').forEach(m=>m.hidden=true);

  // Делегування кліків по таблиці
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('button,[data-open],[data-close]');
    if (!btn) return;

    if (btn.hasAttribute('data-close')) { closeAll(); return; }

    const which = btn.dataset.open;
    if (!which) return;

    const tr = btn.closest('tr');
    const user = tr ? JSON.parse(tr.dataset.user) : null;

    if (which==='edit') {
      const f = document.getElementById('form-edit');
      f.user_id.value = user.id;
      f.username.value = user.username;
      f.email.value = user.email;
      f.role.value = user.role;
      f.status.value = user.status;
      f.currency.value = user.currency;
      open('edit');
    }

    if (which==='password') {
      const f = document.getElementById('form-password');
      f.user_id.value = user.id;
      f.new_password.value = '';
      open('password');
    }

    if (which==='balance') {
      const f = document.getElementById('form-balance');
      f.user_id.value = user.id;
      f.amount.value = '';
      f.type.value = 'deposit';
      f.currency.value = user.currency || 'USD';
      open('balance');
    }

    if (which==='deact' || which==='act') {
      const f = document.getElementById('form-deact');
      const to = which==='act' ? 'active' : 'inactive';
      f.user_id.value = user.id;
      f.to_status.value = to;
      document.getElementById('deact-title').textContent =
        (to==='active' ? 'Активувати користувача?' : 'Деактивувати користувача?');
      open('deact');
    }

    if (which==='delete') {
      const f = document.getElementById('form-delete');
      f.user_id.value = user.id;
      open('delete');
    }
  });

  async function postForm(form) {
    const fd = new FormData(form);
    const res = await fetch('/admin/admin_api.php', { method: 'POST', body: fd, headers: { 'Accept':'application/json' } });
    const data = await res.json().catch(()=>({ok:false, err:'bad_json'}));
    if (!res.ok || !data.ok) throw new Error(data.err || 'request_failed');
    return data;
  }

  const bind = (id)=> {
    const form = document.getElementById(id);
    form?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      try {
        const btn = form.querySelector('button[type="submit"],button:not([type])');
        btn && (btn.disabled = true);
        await postForm(form);
        closeAll();
        location.reload();
      } catch(err) {
        alert('Помилка: ' + err.message);
      } finally {
        const btn = form.querySelector('button[type="submit"],button:not([type])');
        btn && (btn.disabled = false);
      }
    });
  };

  bind('form-edit');
  bind('form-password');
  bind('form-balance');
  bind('form-deact');
  bind('form-delete');

  // Закриття по бекдропу/ESC
  document.querySelectorAll('.modal').forEach(m=>{
    m.addEventListener('click', (e)=>{ if (e.target===m) closeAll(); });
  });
  document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeAll(); });
})();
