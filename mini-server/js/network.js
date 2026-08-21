/* ============================================================
 * MINI · network.js
 * Индикатор сети с тремя состояниями:
 *   online    — есть открытый интернет
 *   whitelist — доступен только Яндекс (типичный режим «белых списков»)
 *   offline   — недоступен даже Яндекс
 *
 * Три отличия от «наивной» версии проверки сети:
 *  1. AbortController-таймер снят через unref() — иначе он держит
 *     процесс живым в Node-тестах и на некоторых Android WebView.
 *  2. Тултип открывается по click/tap, а не только по hover —
 *     на телефоне hover не существует в принципе.
 *  3. Итог пишется в Storage, чтобы Settings/Debug могли увидеть
 *     последний известный статус без повторного пробника.
 * ============================================================ */

const NetworkStatus = (() => {
  const TOOLTIPS = {
    online:    'Есть выход в открытый интернет. Все функции доступны в полном объёме.',
    whitelist: 'Открыт только разрешённый сервис (Яндекс) — обычный интернет недоступен. Это ожидаемый режим «белых списков», не ошибка.',
    offline:   'Недоступен ни Яндекс, ни открытый интернет. Приложение работает из офлайн-кеша; сообщения отправятся при восстановлении связи.',
  };

  const LABELS = { online: 'Online', whitelist: 'Whitelist Mode', offline: 'Offline' };

  let indicator, dot, text, tooltip, timer;
  let current = null;

  function setStatus(status) {
    current = status;
    indicator.className = 'network-status ' + status;
    text.textContent = LABELS[status] || status;
    if (!tooltip.classList.contains('hidden')) renderTooltip(); // обновить текст, если открыт
    Storage.set('lastNetworkStatus', status).catch(() => {});
  }

  /** Пробник с таймаутом; unref — чтобы не мешать процессу/тестам жить. */
  function probe(url, options = {}) {
    return new Promise((resolve) => {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 3000);
      if (timeout.unref) timeout.unref();

      fetch(url, { cache: 'no-store', signal: controller.signal, ...options })
        .then(() => { clearTimeout(timeout); resolve(true); })
        .catch(() => { clearTimeout(timeout); resolve(false); });
    });
  }

  async function checkNetwork() {
    const yandex = await probe('https://cloud-api.yandex.net/v1/disk', { mode: 'cors' });
    if (!yandex) return setStatus('offline');

    const internet = await probe('https://www.google.com/generate_204', { mode: 'no-cors' });
    setStatus(internet ? 'online' : 'whitelist');
  }

  /* ---------- тултип ---------- */

  function renderTooltip() {
    tooltip.textContent = TOOLTIPS[current] || '';
  }

  function toggleTooltip() {
    renderTooltip();
    tooltip.classList.toggle('hidden');
  }

  function closeTooltip() {
    tooltip.classList.add('hidden');
  }

  function bindTooltip() {
    indicator.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleTooltip();
    });
    document.addEventListener('click', (e) => {
      if (!tooltip.contains(e.target) && e.target !== indicator) closeTooltip();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeTooltip();
    });
    /* на десктопе — заодно и по наведению, для скорости */
    indicator.addEventListener('mouseenter', () => { renderTooltip(); tooltip.classList.remove('hidden'); });
    indicator.addEventListener('mouseleave', closeTooltip);
  }

  /* ---------- инициализация ---------- */

  function init() {
    indicator = document.getElementById('networkIndicator');
    dot = indicator.querySelector('.dot');
    text = document.getElementById('networkText');
    tooltip = document.getElementById('networkTooltip');

    bindTooltip();

    window.addEventListener('online', checkNetwork);
    window.addEventListener('offline', () => setStatus('offline'));

    checkNetwork();
    timer = setInterval(checkNetwork, 15000);
    if (timer.unref) timer.unref();
  }

  function stop() {
    clearInterval(timer);
  }

  return { init, stop, checkNetwork, getStatus: () => current };
})();
