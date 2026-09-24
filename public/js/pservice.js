(() => {
  const $ = (s, el = document) => el.querySelector(s);
  const csrf = $('meta[name="csrf-token"]')?.content;

  // Menu lateral no celular
  const sidebar = $('#sidebar'), backdrop = $('#backdrop');
  const toggleMenu = (open) => { sidebar?.classList.toggle('open', open); backdrop?.classList.toggle('open', open); };
  $('#menuBtn')?.addEventListener('click', () => toggleMenu(!sidebar.classList.contains('open')));
  backdrop?.addEventListener('click', () => toggleMenu(false));

  // Galerias
  const openGallery = (id) => {
    const g = document.getElementById(id); if (!g) return;
    g.classList.toggle('open');
    if (g.classList.contains('open')) history.replaceState(null, '', '#' + id);
  };
  if (location.hash.startsWith('#gallery-')) document.getElementById(location.hash.slice(1))?.classList.add('open');

  // Visualizador: navegação entre fotos da etapa + original em alta resolução.
  // Usa o histórico do navegador, então o "voltar" do celular sai do original
  // e depois fecha a foto, sem sair da página da OS.
  const viewer = $('#viewer');
  let items = [], idx = 0;
  if (viewer) {
    const mk = (cls, label, html) => { const b = document.createElement('button'); b.className = cls; b.type = 'button'; b.setAttribute('aria-label', label); b.innerHTML = html; viewer.appendChild(b); return b; };
    const prevBtn = mk('nav prev', 'Anterior', '‹'), nextBtn = mk('nav next', 'Próxima', '›');
    const backBtn = mk('back-original', 'Voltar', '← Voltar');
    const counter = document.createElement('span'); counter.className = 'counter'; viewer.appendChild(counter);
    const img = $('img', viewer);
    const origLink = $('[data-original-link]', viewer);
    origLink?.removeAttribute('target');
    const isOpen = () => viewer.classList.contains('open');
    const inOriginal = () => viewer.classList.contains('original');

    const preload = (i) => { if (items[i]) new Image().src = items[i].dataset.view; };
    const show = (i) => {
      if (!items.length) return;
      idx = (i + items.length) % items.length;
      const el = items[idx];
      img.src = el.dataset.view;
      $('.caption', viewer).textContent = el.dataset.caption || '';
      if (origLink) origLink.href = el.dataset.original;
      counter.textContent = items.length > 1 ? `${idx + 1} / ${items.length}` : '';
      viewer.classList.toggle('single', items.length < 2);
      preload(idx + 1); preload(idx - 1);
    };

    // Estados visuais (sem mexer no histórico)
    const setOriginal = (on) => {
      if (!items[idx]) return;
      viewer.classList.toggle('original', on);
      viewer.classList.remove('zoomed');
      if (on) {
        viewer.classList.add('loading');
        img.onload = img.onerror = () => viewer.classList.remove('loading');
        img.src = items[idx].dataset.original;
        if (origLink) origLink.hidden = true;
      } else {
        img.onload = img.onerror = null;
        viewer.classList.remove('loading');
        img.src = items[idx].dataset.view;
        if (origLink) origLink.hidden = false;
      }
      viewer.scrollTo?.(0, 0);
    };
    const hide = () => { setOriginal(false); viewer.classList.remove('open'); };

    // Ações do usuário (registram no histórico)
    const openAt = (list, i) => { items = list; show(i); viewer.classList.add('open'); history.pushState({ pv: 'viewer' }, ''); };
    const openOriginal = () => { setOriginal(true); history.pushState({ pv: 'original' }, ''); };
    const goBack = () => history.back();
    const closeAll = () => (inOriginal() ? history.go(-2) : history.back());

    window.addEventListener('popstate', (e) => {
      const st = e.state?.pv;
      if (st === 'viewer') { if (!isOpen()) viewer.classList.add('open'); setOriginal(false); }
      else if (st === 'original') { viewer.classList.add('open'); setOriginal(true); }
      else if (isOpen()) hide();
    });

    prevBtn.addEventListener('click', (e) => { e.stopPropagation(); show(idx - 1); });
    nextBtn.addEventListener('click', (e) => { e.stopPropagation(); show(idx + 1); });
    backBtn.addEventListener('click', (e) => { e.stopPropagation(); goBack(); });
    origLink?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openOriginal(); });
    // No original, tocar na foto alterna entre caber na tela e zoom (com rolagem)
    img.addEventListener('click', (e) => { if (inOriginal()) { e.stopPropagation(); viewer.classList.toggle('zoomed'); } });

    document.addEventListener('click', (e) => {
      const g = e.target.closest('[data-gallery]');
      if (g) openGallery(g.dataset.gallery);
      const p = e.target.closest('[data-view]');
      if (p) {
        const list = [...(p.closest('.thumbs') || document).querySelectorAll('[data-view]')];
        openAt(list, list.indexOf(p));
      }
      if (isOpen() && (e.target.closest('[data-close]') || (e.target === viewer && !inOriginal()))) closeAll();
    });
    document.addEventListener('keydown', (e) => {
      if (!isOpen()) return;
      if (e.key === 'Escape') inOriginal() ? goBack() : closeAll();
      if (!inOriginal() && e.key === 'ArrowLeft') show(idx - 1);
      if (!inOriginal() && e.key === 'ArrowRight') show(idx + 1);
    });

    // Deslizar: esquerda/direita troca a foto, para baixo fecha (desligado no original, onde o dedo move o zoom)
    let x0 = null, y0 = null;
    viewer.addEventListener('touchstart', (e) => {
      if (inOriginal() || e.touches.length !== 1) { x0 = null; return; }
      x0 = e.touches[0].clientX; y0 = e.touches[0].clientY;
    }, { passive: true });
    viewer.addEventListener('touchend', (e) => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
      x0 = null;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) show(idx + (dx < 0 ? 1 : -1));
      else if (dy > 90 && Math.abs(dy) > Math.abs(dx)) closeAll();
    }, { passive: true });
  } else {
    document.addEventListener('click', (e) => {
      const g = e.target.closest('[data-gallery]');
      if (g) openGallery(g.dataset.gallery);
    });
  }

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
