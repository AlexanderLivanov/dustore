/* Dustore mobile PWA — app.js
 * Без фреймворка и без SPA-роутера: страницы рендерит PHP, а ощущение
 * «приложения» дают платформенные вещи — speculation rules (предзагрузка),
 * cross-document View Transitions (анимация переходов), общий service worker.
 */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const M = window.M || {};
  const isTouch = matchMedia('(pointer: coarse)').matches;
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const store = {   // localStorage может бросать (приватный режим) — не даём ему ронять страницу
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { } },
  };
  document.documentElement.classList.toggle('standalone', standalone);

  /* ── Service worker: один на весь сайт (/sw.js) ── */
  if ('serviceWorker' in navigator) {
    addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => { }));
    // звук пуша в открытом приложении (SW шлёт сюда вместо системного баннера)
    navigator.serviceWorker.addEventListener('message', e => {
      if (e.data?.type === 'chat-sound' && typeof window.chatPing === 'function') window.chatPing();
      if (e.data?.type === 'badge') pollBadge();
    });
  }

  /* ── Тост ── */
  let tt;
  function toast(msg) {
    const t = $('#mToast'); if (!t) return;
    t.textContent = msg; t.classList.add('show');
    clearTimeout(tt); tt = setTimeout(() => t.classList.remove('show'), 2400);
  }
  window.mToast = toast;

  /* ── «Как приложение»: долгий тап по ссылке/картинке не открывает системное
     превью и меню «Открыть в новой вкладке» (iOS гасим CSS-свойством
     -webkit-touch-callout, Android — этим обработчиком). Поля ввода и чат
     (у него своё меню по долгому тапу) не трогаем. ── */
  if (isTouch) {
    document.addEventListener('contextmenu', e => {
      if (e.target.closest('input, textarea, [contenteditable], .app')) return;
      if (e.target.closest('a, img, button')) e.preventDefault();
    });
  }
  document.addEventListener('dragstart', e => { if (e.target.closest('img, a')) e.preventDefault(); });

  /* ── Переход «обложка → шапка игры» (View Transitions между документами).
     Имя вешаем только на ту обложку, по которой тапнули: одинаковое имя на
     двух элементах сразу ломает анимацию, а одна игра бывает в нескольких лентах. ── */
  document.addEventListener('click', e => {
    const a = e.target.closest('a[data-vt]'); if (!a) return;
    $$('[style*="view-transition-name"]').forEach(el => { el.style.viewTransitionName = ''; });
    const img = a.querySelector('img'); if (img) img.style.viewTransitionName = 'gcover';
  });
  addEventListener('pageshow', () => $$('a[data-vt] img').forEach(el => { el.style.viewTransitionName = ''; }));

  /* ── Полная версия сайта ── */
  document.addEventListener('click', e => {
    if (!e.target.closest('[data-go-desktop]')) return;
    document.cookie = 'prefer_desktop=1;path=/;max-age=' + 60 * 60 * 24 * 30 + ';SameSite=Lax';
    location.href = '/';
  });

  /* ── Непрочитанное: бейдж в меню + значок на иконке приложения (Badging API) ── */
  async function pollBadge() {
    if (!M.user || document.hidden) return;
    try {
      const r = await fetch('/chat/api.php?action=unread_total', { credentials: 'same-origin' }).then(r => r.json());
      if (!r.ok) return;
      const n = (r.total || 0) + (r.notifications || 0);
      const b = $('#chatBadge');
      if (b) { b.hidden = !n; b.textContent = n > 99 ? '99+' : n; }
      if ('setAppBadge' in navigator) (n ? navigator.setAppBadge(n) : navigator.clearAppBadge()).catch(() => { });
    } catch (e) { }
  }
  if (M.user) {
    pollBadge();
    setInterval(pollBadge, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollBadge(); });
  }

  /* ── Установка на экран «Домой» ──
     Плашка предлагает раз в 14 дней, шторка #installSheet объясняет шаги под платформу.
     У iOS нет системного диалога установки — только «Поделиться → На экран Домой»,
     поэтому там шторка обязательна. Открыть её можно откуда угодно: [data-install]
     или window.mInstall() (например, из настроек уведомлений в чате). */
  const ua = navigator.userAgent;
  const isAndroid = /Android/i.test(ua);
  const platform = isIOS ? 'ios' : isAndroid ? 'android' : 'other';
  // in-app браузеры (Telegram, ВК…) не умеют «Домой»: у iOS в UA нет токена Safari/, у Android — «; wv)»
  const inApp = /Telegram|Instagram|FBAN|FBAV|VKAndroidApp|VKClient|Snapchat|; wv\)/i.test(ua) || (isIOS && !/Safari\//.test(ua));
  let deferred = null;
  const installEl = $('#install'), sheet = $('#installSheet');
  const installHidden = () => (+store.get('m_install_x') || 0) > Date.now() - 14 * 864e5;
  function showInstall(hint) {
    if (!installEl || standalone || installHidden() || ['game', 'chat'].includes(M.page)) return;
    if (hint) $('#installHint').textContent = hint;
    installEl.hidden = false;
  }
  function closeInstall() { if (!sheet) return; sheet.hidden = true; document.body.classList.remove('lock'); }
  function openInstall() {
    if (standalone) { toast('Dustore уже установлен'); return; }
    if (!sheet) return;
    $$('[data-for]', sheet).forEach(el => { el.hidden = el.dataset.for !== platform; });
    $$('[data-inapp]', sheet).forEach(el => { el.hidden = !inApp; });
    const native = $('[data-native]', sheet), manual = $('[data-manual]', sheet);
    if (native && manual) { native.hidden = !deferred; manual.hidden = !!deferred; }
    sheet.hidden = false; document.body.classList.add('lock');
    if (installEl) installEl.hidden = true;
  }
  window.mInstall = openInstall;
  async function nativeInstall() {
    if (!deferred) return openInstall();
    deferred.prompt();
    const { outcome } = await deferred.userChoice; deferred = null;
    if (outcome === 'accepted') { closeInstall(); if (installEl) installEl.hidden = true; toast('Dustore добавлен на экран'); }
  }
  addEventListener('beforeinstallprompt', e => { e.preventDefault(); deferred = e; showInstall(); });
  addEventListener('appinstalled', () => { if (installEl) installEl.hidden = true; closeInstall(); toast('Dustore добавлен на экран'); });
  if (isIOS && !standalone) setTimeout(() => showInstall('Нажмите «Поделиться» → «На экран Домой»'), 2500);
  // кнопки «Установить» на плашке: на Android с готовым диалогом — сразу он, иначе шторка с шагами
  $('#installBtn')?.addEventListener('click', () => (deferred ? nativeInstall() : openInstall()));
  $('#installNative')?.addEventListener('click', nativeInstall);
  $('#installClose')?.addEventListener('click', closeInstall);
  sheet?.addEventListener('click', e => { if (e.target === sheet) closeInstall(); });
  addEventListener('keydown', e => { if (e.key === 'Escape' && sheet && !sheet.hidden) closeInstall(); });
  document.addEventListener('click', e => { if (e.target.closest('[data-install]')) { e.preventDefault(); openInstall(); } });
  $('#installX')?.addEventListener('click', () => { installEl.hidden = true; store.set('m_install_x', Date.now()); });
  // в установленном приложении пункты «Установить» не нужны
  if (standalone) $$('[data-install]').forEach(el => { el.hidden = true; });

  /* ── Герой-карусель: точки + автопрокрутка, пауза на касании ── */
  const track = $('#heroTrack');
  if (track) {
    const dots = $$('#heroDots i');
    let idx = 0, paused = false;
    const io = new IntersectionObserver(es => es.forEach(en => {
      if (en.isIntersecting && en.intersectionRatio > .6) {
        idx = [...track.children].indexOf(en.target);
        dots.forEach((d, i) => d.classList.toggle('on', i === idx));
      }
    }), { root: track, threshold: [.6] });
    [...track.children].forEach(s => io.observe(s));
    ['pointerdown', 'touchstart'].forEach(ev => track.addEventListener(ev, () => { paused = true; }, { passive: true }));
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches && track.children.length > 1) {
      setInterval(() => {
        if (paused || document.hidden) return;
        const next = track.children[(idx + 1) % track.children.length];
        track.scrollTo({ left: next.offsetLeft - track.offsetLeft, behavior: 'smooth' });
      }, 5000);
    }
  }

  /* ── Каталог: бесконечная лента. Сервер отдаёт готовый HTML карточек. ── */
  const feed = $('#feed'), more = $('#feedMore');
  if (feed && more && !more.hidden) {
    let busy = false, done = false;
    const load = async () => {
      if (busy || done) return; busy = true;
      try {
        const url = feed.dataset.api + (feed.dataset.api.includes('?') ? '&' : '?') + 'offset=' + feed.dataset.offset;
        const r = await fetch(url, { credentials: 'same-origin' }).then(r => r.json());
        feed.insertAdjacentHTML('beforeend', r.html);
        feed.dataset.offset = r.next;
        if (r.done) { done = true; more.hidden = true; }
      } catch (e) { toast('Не удалось загрузить — проверьте сеть'); }
      busy = false;
    };
    new IntersectionObserver(es => { if (es[0].isIntersecting) load(); }, { rootMargin: '600px' }).observe(more);
  }

  /* ── Поиск: живой, с отменой устаревших запросов ── */
  const sq = $('#searchQ');
  if (sq) {
    const out = $('#searchOut'), idle = $('#searchIdle'), x = $('#searchX');
    let timer, ctrl;
    const run = async () => {
      const q = sq.value.trim();
      x.hidden = !q; idle.hidden = !!q;
      history.replaceState(null, '', q ? '/m/search?q=' + encodeURIComponent(q) : '/m/search');
      if (!q) { out.innerHTML = ''; return; }
      ctrl?.abort(); ctrl = new AbortController();
      out.classList.add('loading');
      try {
        out.innerHTML = await fetch('/m/api/search.php?q=' + encodeURIComponent(q), { signal: ctrl.signal }).then(r => r.text());
      } catch (e) { if (e.name !== 'AbortError') toast('Поиск недоступен'); }
      out.classList.remove('loading');
    };
    sq.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 250); });
    $('#searchForm').addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); run(); sq.blur(); });
    x.addEventListener('click', () => { sq.value = ''; run(); sq.focus(); });
  }

  /* ── Страница игры ── */
  // «Назад»: если пришли с нашего же сайта — настоящая история (сохранится скролл ленты)
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-back]'); if (!b) return;
    if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1) { e.preventDefault(); history.back(); }
  });
  document.addEventListener('click', async e => {
    if (!e.target.closest('[data-share]')) return;
    const data = { title: document.title, url: location.href.replace(/\/m\/game\//, '/g/') };
    try {
      if (navigator.share) await navigator.share(data);
      else { await navigator.clipboard.writeText(data.url); toast('Ссылка скопирована'); }
    } catch (err) { }
  });

  // Параллакс шапки: фон уезжает медленнее страницы. Только transform — без перерасчёта раскладки.
  const heroImg = $('#gpHeroImg');
  if (heroImg && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    let ticking = false;
    const hero = heroImg.parentElement;
    const move = () => { ticking = false; const y = Math.min(scrollY, hero.offsetHeight); hero.style.setProperty('--py', (y * 0.45).toFixed(1) + 'px'); };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(move); } }, { passive: true });
  }

  /* APK с процентом на кнопке. Качаем сами (fetch + поток), чтобы показать
     прогресс, затем отдаём файл системе — дальше обычный установщик Android.
     Второй тап во время загрузки — отмена. Очень большие файлы и браузеры без
     потоков качаются как раньше, штатной загрузкой. */
  const apk = $('#apkBtn');
  if (apk && window.ReadableStream && 'download' in HTMLAnchorElement.prototype) {
    const label = apk.querySelector('.buy-l'), orig = label.textContent, src = apk.href;
    const MAX = 600 * 1048576;
    let ctrl = null, blobUrl = null;
    apk.addEventListener('click', async e => {
      if (blobUrl) return;                         // уже скачано — ссылка ведёт на файл
      if ((+apk.dataset.size || 0) > MAX) return;
      e.preventDefault();
      if (ctrl) { ctrl.abort(); return; }
      ctrl = new AbortController();
      apk.classList.add('loading'); label.textContent = 'Скачивание… 0%';
      try {
        const r = await fetch(src, { signal: ctrl.signal, credentials: 'same-origin' });
        if (!r.ok || !r.body) throw new Error('HTTP ' + r.status);
        const total = +r.headers.get('Content-Length') || +apk.dataset.size || 0;
        const reader = r.body.getReader(), parts = [];
        let got = 0;
        for (;;) {
          const { done, value } = await reader.read();
          if (done) break;
          parts.push(value); got += value.length;
          if (total) {
            const pct = Math.min(100, Math.floor(got * 100 / total));
            apk.style.setProperty('--p', pct + '%'); label.textContent = 'Скачивание… ' + pct + '%';
          } else label.textContent = 'Скачивание… ' + (got / 1048576).toFixed(1) + ' МБ';
        }
        blobUrl = URL.createObjectURL(new Blob(parts, { type: 'application/vnd.android.package-archive' }));
        apk.href = blobUrl; apk.download = apk.dataset.name || 'game.apk';
        apk.classList.remove('loading'); apk.style.removeProperty('--p'); label.textContent = 'Установить';
        navigator.vibrate?.(12);
        apk.click();                               // файл уходит в «Загрузки», Android предложит установку
        toast('Скачано — откройте файл, чтобы установить');
      } catch (err) {
        apk.classList.remove('loading'); apk.style.removeProperty('--p'); label.textContent = orig;
        if (err.name === 'AbortError') toast('Загрузка отменена');
        else { toast('Качаю обычной загрузкой'); location.href = src; }
      } finally { ctrl = null; }
    });
  }

  // Описание: «Читать полностью», только если текст реально обрезан
  const desc = $('#gpDesc'), descMore = $('#gpMore');
  if (desc && descMore && desc.scrollHeight > desc.clientHeight + 4) {
    descMore.hidden = false;
    descMore.addEventListener('click', () => { desc.classList.remove('clamp'); descMore.hidden = true; });
  }

  // Вишлист: оптимистично + идемпотентно (шлём желаемое состояние, а не «переключи»)
  const wish = $('#wishBtn');
  wish?.addEventListener('click', async () => {
    if (!M.user) { location.href = '/login?backUrl=' + encodeURIComponent(location.pathname); return; }
    const on = !wish.classList.contains('on');
    const paint = v => { wish.classList.toggle('on', v); wish.setAttribute('aria-pressed', v); wish.querySelector('i').className = 'ti ti-heart' + (v ? '-filled' : ''); };
    paint(on); navigator.vibrate?.(8);
    try {
      const r = await fetch('/m/api/wishlist.php', {
        method: 'POST', credentials: 'same-origin',
        body: new URLSearchParams({ game_id: wish.dataset.game, on: on ? 1 : 0 })
      }).then(r => r.json());
      if (!r.ok) throw r;
      toast(on ? 'В вишлисте ♥' : 'Убрано из вишлиста');
    } catch (err) { paint(!on); toast('Не получилось — попробуйте ещё раз'); }
  });

  // Скриншоты: полноэкранный просмотр с листанием
  const viewer = $('#viewer');
  if (viewer) {
    const vt = viewer.querySelector('.viewer-track');
    const close = () => { viewer.hidden = true; document.body.classList.remove('lock'); };
    // и лента, и шапка (если в неё ушёл первый скриншот) открывают просмотр
    document.addEventListener('click', e => {
      const s = e.target.closest('.shot[data-i]'); if (!s) return;
      viewer.hidden = false; document.body.classList.add('lock');
      const img = vt.children[+s.dataset.i];
      vt.scrollTo({ left: img.offsetLeft, behavior: 'instant' });
    });
    viewer.querySelector('.viewer-x').addEventListener('click', close);
    viewer.addEventListener('click', e => { if (e.target === viewer || e.target === vt) close(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !viewer.hidden) close(); });
  }

  /* ── Профиль: уведомления на этом устройстве ── */
  const sw = $('#pushSwitch');
  if (sw) {
    const sub = $('#pushSub');
    const setUI = (on, text) => { sw.setAttribute('aria-checked', on); sw.classList.toggle('on', on); if (text) sub.textContent = text; };
    const current = async () => {
      if (!('serviceWorker' in navigator) || !('PushManager' in window)) return null;
      const reg = await navigator.serviceWorker.getRegistration('/');
      return reg ? reg.pushManager.getSubscription() : null;
    };
    (async () => {
      if (isIOS && !standalone) { setUI(false, 'На iPhone — только из приложения на экране «Домой»'); sw.disabled = true; return; }
      if (!('PushManager' in window)) { setUI(false, 'Браузер не поддерживает уведомления'); sw.disabled = true; return; }
      if (Notification.permission === 'denied') { setUI(false, 'Запрещены в настройках браузера'); return; }
      const s = await current().catch(() => null);
      setUI(!!s && Notification.permission === 'granted', s ? 'Приходят на это устройство' : null);
    })();
    sw.addEventListener('click', async () => {
      if (sw.disabled) return;
      const on = sw.getAttribute('aria-checked') === 'true';
      if (on) {
        const s = await current().catch(() => null);
        if (s) {
          await fetch('/chat/push_subscribe.php', {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ endpoint: s.endpoint, unsubscribe: 1 })
          }).catch(() => { });
          await s.unsubscribe().catch(() => { });
        }
        setUI(false, 'Выключены на этом устройстве');
        return;
      }
      if (typeof window.initPush !== 'function') { toast('Уведомления ещё не настроены на сервере'); return; }
      const r = await window.initPush();          // спрашивает разрешение — только по жесту
      if (r.ok) { setUI(true, 'Приходят на это устройство'); toast('Уведомления включены'); }
      else { setUI(false, r.reason); toast(r.reason); }
    });
  }
})();