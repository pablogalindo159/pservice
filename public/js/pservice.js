(() => {
  document.documentElement.classList.add('js');
  const $ = (s, el = document) => el.querySelector(s);
  const csrf = $('meta[name="csrf-token"]')?.content;

  // Menu lateral no celular
  const sidebar = $('#sidebar'), backdrop = $('#backdrop');
  const toggleMenu = (open) => { sidebar?.classList.toggle('open', open); backdrop?.classList.toggle('open', open); };
  $('#menuBtn')?.addEventListener('click', () => toggleMenu(!sidebar.classList.contains('open')));
  backdrop?.addEventListener('click', () => toggleMenu(false));

  // Abas de etapas: o endereço acompanha a aba (ex.: /os/1020#OS1020-entrada)
  const tabs = [...document.querySelectorAll('.stage-tab')];
  const panels = [...document.querySelectorAll('[data-panel]')];
  const tabBar = $('#stageTabs');
  const selectTab = (id) => {
    const p = panels.find((x) => x.id === id) || panels[0]; if (!p) return;
    panels.forEach((x) => x.classList.toggle('active', x === p));
    tabs.forEach((t) => {
      const on = t.dataset.tab === p.id;
      t.classList.toggle('on', on);
      if (on && tabBar) tabBar.scrollLeft = t.offsetLeft - (tabBar.clientWidth - t.offsetWidth) / 2;
    });
    history.replaceState(history.state, '', '#' + p.id);
  };
  tabs.forEach((t) => t.addEventListener('click', () => selectTab(t.dataset.tab)));
  if (panels.length) {
    const h = decodeURIComponent(location.hash.slice(1));
    // Sem âncora: abre a próxima etapa ainda sem fotos (ou a última)
    const next = panels.find((p) => !p.querySelector('.photo')) || panels[panels.length - 1];
    selectTab(panels.some((p) => p.id === h) ? h : next.id);
  }

  // Contadores: abas, rodapé da etapa e barra de progresso da OS
  const refreshCounts = () => {
    let done = 0;
    panels.forEach((p) => {
      const n = p.querySelectorAll('.photo[data-view]').length;
      const t = tabs.find((x) => x.dataset.tab === p.id);
      if (t) { const b = $('.n', t); b.textContent = n; b.hidden = !n; $('.ok', t).hidden = !n; }
      const c = $('[data-count]', p); if (c) c.textContent = n;
      const e = $('.empty', p); if (e) e.hidden = !!p.querySelector('.photo');
      const dl = $('[data-dl]', p); if (dl) dl.hidden = !n;
      if (n) done++;
    });
    const bar = $('#osProgress'), txt = $('#osProgressText');
    if (bar && panels.length) { bar.style.width = Math.round(done / panels.length * 100) + '%'; txt.textContent = `${done} de ${panels.length} etapas`; }
  };

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
    const delBtn = $('[data-delete-btn]', viewer), confirmBox = $('[data-confirm]', viewer), delForm = $('[data-delete-form]', viewer);
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
      if (delBtn) delBtn.hidden = !el.dataset.delete;
      if (confirmBox) confirmBox.hidden = true;
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
        if (origLink) origLink.style.display = 'none';
      } else {
        img.onload = img.onerror = null;
        viewer.classList.remove('loading');
        img.src = items[idx].dataset.view;
        if (origLink) origLink.style.display = '';
      }
      viewer.scrollTo?.(0, 0);
    };
    const hide = () => { setOriginal(false); viewer.classList.remove('open'); };

    // Ações do usuário. A tela muda na hora (não depende do navegador);
    // o histórico só acompanha, para o gesto "voltar" do celular funcionar também.
    let depth = 0; // quantas entradas nossas estão no histórico (0, 1 = foto, 2 = original)
    const openAt = (list, i) => { items = list; show(i); viewer.classList.add('open'); history.pushState({ pv: 'viewer' }, ''); depth = 1; };
    const openOriginal = () => { setOriginal(true); history.pushState({ pv: 'original' }, ''); depth = 2; };
    const goBack = () => { // sai do original, continua na foto
      setOriginal(false);
      if (depth === 2) { depth = 1; history.back(); }
    };
    const closeAll = () => { // fecha a foto de qualquer estado
      const n = depth; depth = 0; hide();
      if (n > 0) history.go(-n);
    };

    window.addEventListener('popstate', (e) => {
      const st = e.state?.pv;
      if (st === 'viewer') { depth = 1; if (!isOpen()) viewer.classList.add('open'); if (inOriginal()) setOriginal(false); }
      else if (st === 'original') { depth = 2; viewer.classList.add('open'); if (!inOriginal()) setOriginal(true); }
      else { depth = 0; if (isOpen()) hide(); }
    });

    prevBtn.addEventListener('click', (e) => { e.stopPropagation(); show(idx - 1); });
    nextBtn.addEventListener('click', (e) => { e.stopPropagation(); show(idx + 1); });
    backBtn.addEventListener('click', (e) => { e.stopPropagation(); goBack(); });
    origLink?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openOriginal(); });
    delBtn?.addEventListener('click', (e) => { e.stopPropagation(); if (items[idx]?.dataset.delete) confirmBox.hidden = false; });
    $('[data-confirm-no]', viewer)?.addEventListener('click', (e) => { e.stopPropagation(); confirmBox.hidden = true; });
    delForm?.addEventListener('submit', () => {
      const p = items[idx].closest('[data-panel]');
      delForm.action = items[idx].dataset.delete + (p ? '#' + p.id : '');
    });
    // No original, tocar na foto alterna entre caber na tela e zoom (com rolagem)
    img.addEventListener('click', (e) => { if (inOriginal()) { e.stopPropagation(); viewer.classList.toggle('zoomed'); } });

    document.addEventListener('click', (e) => {
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
      if (inOriginal() || e.touches.length !== 1 || (confirmBox && !confirmBox.hidden)) { x0 = null; return; }
      x0 = e.touches[0].clientX; y0 = e.touches[0].clientY;
    }, { passive: true });
    viewer.addEventListener('touchend', (e) => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
      x0 = null;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) show(idx + (dx < 0 ? 1 : -1));
      else if (dy > 90 && Math.abs(dy) > Math.abs(dx)) closeAll();
    }, { passive: true });
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
      if (xhr.status >= 200 && xhr.status < 300) { try { return resolve(JSON.parse(xhr.responseText)); } catch { return resolve({}); } }
      let msg = 'Erro ' + xhr.status;
      try { const j = JSON.parse(xhr.responseText); msg = j.message || Object.values(j.errors || {})[0]?.[0] || msg; } catch {}
      if (xhr.status === 413) msg = 'Arquivo maior que o limite do servidor.';
      if (xhr.status === 419) msg = 'Sessão expirada. Recarregue a página.';
      reject(new Error(msg));
    };
    xhr.onerror = () => reject(new Error('Falha de conexão'));
    xhr.send(fd);
  });

  const esc = (t) => String(t ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const fillPhoto = (el, v) => {
    el.className = 'photo';
    el.dataset.view = v.view; el.dataset.original = v.original; el.dataset.caption = v.caption;
    if (v.delete) el.dataset.delete = v.delete;
    el.innerHTML = `<img src="${esc(v.thumb)}" alt=""><span class="lb">${esc(v.label)}</span>`;
  };

  document.querySelectorAll('form[data-upload]').forEach((form) => {
    const grid = form.closest('[data-panel]')?.querySelector('.photo-grid');
    form.querySelectorAll('input[type=file]').forEach((input) => input.addEventListener('change', async () => {
      const files = [...input.files]; input.value = '';
      if (!files.length || !grid) return;
      const jobs = files.map((f) => {
        const el = document.createElement('div');
        const url = URL.createObjectURL(f);
        el.className = 'photo uploading';
        el.innerHTML = `<img src="${url}" alt=""><span class="ring">0%</span>`;
        grid.appendChild(el);
        return { f, el, url };
      });
      refreshCounts();
      const failed = [];
      for (const j of jobs) {
        let tries = 0;
        while (true) {
          try {
            const res = await sendOne(form, j.f, (p) => { $('.ring', j.el).textContent = Math.round(p * 100) + '%'; });
            if (res.photos?.[0]) fillPhoto(j.el, res.photos[0]); else j.el.classList.remove('uploading');
            URL.revokeObjectURL(j.url);
            break;
          } catch (err) {
            if (++tries < 3 && err.message === 'Falha de conexão') { await new Promise((r) => setTimeout(r, 1500 * tries)); continue; }
            j.el.classList.remove('uploading'); j.el.classList.add('failed');
            $('.ring', j.el).textContent = 'Falhou';
            failed.push(`${j.f.name}: ${err.message}`);
            break;
          }
        }
        refreshCounts();
      }
      if (failed.length) alert('Algumas fotos não foram enviadas:\n\n' + failed.join('\n'));
    }));
  });

  // PWA
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
})();
