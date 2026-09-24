(() => {
  const $ = (s, el = document) => el.querySelector(s);
  const csrf = $('meta[name="csrf-token"]')?.content;

  // Menu lateral no celular
  const sidebar = $('#sidebar'), backdrop = $('#backdrop');
  const toggleMenu = (open) => { sidebar?.classList.toggle('open', open); backdrop?.classList.toggle('open', open); };
  $('#menuBtn')?.addEventListener('click', () => toggleMenu(!sidebar.classList.contains('open')));
  backdrop?.addEventListener('click', () => toggleMenu(false));

  // Galerias e visualizador
  const viewer = $('#viewer');
  const openGallery = (id) => {
    const g = document.getElementById(id); if (!g) return;
    g.classList.toggle('open');
    if (g.classList.contains('open')) history.replaceState(null, '', '#' + id);
  };
  document.addEventListener('click', (e) => {
    const g = e.target.closest('[data-gallery]');
    if (g) openGallery(g.dataset.gallery);
    const p = e.target.closest('[data-view]');
    if (p && viewer) {
      $('img', viewer).src = p.dataset.view;
      $('.caption', viewer).textContent = p.dataset.caption || '';
      $('[data-original-link]', viewer).href = p.dataset.original;
      viewer.classList.add('open');
    }
    if (e.target.closest('[data-close]') || e.target === viewer) viewer?.classList.remove('open');
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') viewer?.classList.remove('open'); });
  if (location.hash.startsWith('#gallery-')) document.getElementById(location.hash.slice(1))?.classList.add('open');

  // Envio foto a foto: evita estourar o limite de POST do servidor
  // e mostra progresso real no celular.
  const sendOne = (form, file, onProgress) => new Promise((resolve, reject) => {
    const fd = new FormData();
    fd.append('_token', csrf);
    fd.append('stage', form.querySelector('[name=stage]').value);
    fd.append('photos[]', file, file.name);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.onprogress = (ev) => ev.lengthComputable && onProgress(ev.loaded / ev.total);
    xhr.onload = () => {
      if (xhr.status >= 200 && xhr.status < 300) return resolve();
      let msg = 'Erro ' + xhr.status;
      try { const j = JSON.parse(xhr.responseText); msg = j.message || Object.values(j.errors || {})[0]?.[0] || msg; } catch {}
      if (xhr.status === 413) msg = 'Arquivo maior que o limite do servidor.';
      if (xhr.status === 419) msg = 'Sessão expirada. Recarregue a página.';
      reject(new Error(msg));
    };
    xhr.onerror = () => reject(new Error('Falha de conexão'));
    xhr.send(fd);
  });

  document.querySelectorAll('form[data-upload]').forEach((form) => {
    const status = $('.upload-status', form), bar = $('.bar i', status), label = $('span', status);
    form.querySelectorAll('input[type=file]').forEach((input) => input.addEventListener('change', async () => {
      const files = [...input.files]; if (!files.length) return;
      form.querySelectorAll('input[type=file]').forEach((i) => (i.disabled = true));
      status.hidden = false;
      const failed = [];
      for (let i = 0; i < files.length; i++) {
        let tries = 0;
        while (true) {
          try {
            await sendOne(form, files[i], (p) => {
              bar.style.width = (((i + p) / files.length) * 100).toFixed(0) + '%';
              label.textContent = `Enviando ${i + 1} de ${files.length}…`;
            });
            break;
          } catch (err) {
            if (++tries < 3 && err.message === 'Falha de conexão') { await new Promise((r) => setTimeout(r, 1500 * tries)); continue; }
            failed.push(`${files[i].name}: ${err.message}`); break;
          }
        }
      }
      bar.style.width = '100%';
      if (failed.length) {
        label.textContent = `${files.length - failed.length} enviada(s), ${failed.length} com erro.`;
        alert('Algumas fotos não foram enviadas:\n\n' + failed.join('\n'));
      } else {
        label.textContent = 'Concluído!';
      }
      location.reload();
    }));
  });

  // PWA
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
})();
