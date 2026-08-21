/* ============================================================
 * MINI · directory.js
 * Каталог пользователей: резолв @ника и получение публичного ключа.
 *
 * Две дороги до сервера (см. NetworkStatus):
 *   online     → прямой HTTP GET /api/users/<ник> (мгновенно)
 *   whitelist  → только Яндекс доступен → идём через Диск:
 *                кладём keyreq__<ник>.json в outgoing, релей
 *                отвечает key__<ник>.json в incoming (задержка ~цикл).
 *
 * messenger.js не знает про эти детали — он зовёт resolve()/requestKey()
 * и получает результат. Граница ответственности как у storage.js.
 * ============================================================ */

const Directory = (() => {
  /* Базовый URL сервера MINI ID. Может быть переопределён в настройках
     (Storage 'serverUrl') — на случай смены домена без пересборки. */
  let SERVER_URL = 'https://mini.dustore.ru';

  async function init() {
    const saved = await Storage.get('serverUrl').catch(() => null);
    if (saved) SERVER_URL = saved.replace(/\/$/, '');
  }

  function setServerUrl(url) {
    SERVER_URL = url.replace(/\/$/, '');
    return Storage.set('serverUrl', SERVER_URL);
  }

  const USERNAME_RE = /^[a-z0-9_]{3,20}$/;
  function normalize(raw) {
    return String(raw || '').trim().replace(/^@/, '').toLowerCase();
  }
  function isValid(username) {
    return USERNAME_RE.test(username);
  }

  /* ---------- быстрый путь: прямой HTTP ---------- */

  async function httpResolve(username) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 4000);
    try {
      const r = await fetch(`${SERVER_URL}/api/users/${username}`, {
        signal: controller.signal,
        cache: 'no-store',
      });
      clearTimeout(timer);
      if (r.status === 404) return { exists: false, via: 'http' };
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const data = await r.json();
      return { ...data, via: 'http' };
    } catch (e) {
      clearTimeout(timer);
      return null; // сеть/таймаут — сигнал уйти на путь через Диск
    }
  }

  async function httpKey(username) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 4000);
    try {
      const r = await fetch(`${SERVER_URL}/api/users/${username}/key`, {
        signal: controller.signal,
        cache: 'no-store',
      });
      clearTimeout(timer);
      if (r.status === 404) return { found: false, via: 'http' };
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const data = await r.json();
      return { found: true, publicKey: data.publicKey, via: 'http' };
    } catch (e) {
      clearTimeout(timer);
      return null;
    }
  }

  /* ---------- путь через Диск: keyreq → key ---------- */

  /**
   * Кладёт запрос ключа в outgoing и опрашивает incoming, пока релей
   * не ответит (или не выйдет таймаут). Заодно резолвит существование:
   * если ключ пришёл — ник существует.
   */
  async function diskKey(username, { timeoutMs = 60000, pollMs = 5000 } = {}) {
    await Disk.createAppFolders(); // гарантируем структуру MINI/*
    await Disk.uploadJson(`${Disk.APP_ROOT}/outgoing/keyreq__${username}.json`, {
      requestedAt: Date.now(),
    });

    const inbox = `${Disk.APP_ROOT}/incoming/key__${username}.json`;
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
      await new Promise((r) => setTimeout(r, pollMs));
      const reply = await Disk.downloadJson(inbox);
      if (reply) {
        await Disk.remove(inbox, true).catch(() => {}); // прибираем ответ
        if (reply.error === 'not_found') return { found: false, via: 'disk' };
        return { found: true, publicKey: reply.publicKey, via: 'disk' };
      }
    }
    return { found: null, via: 'disk', timeout: true }; // null = «не знаем»
  }

  /* ---------- публичное API ---------- */

  /**
   * resolve(username) — существует ли ник.
   * Возвращает { exists: true|false|null, username, displayName?, via }.
   * exists=null означает «не смогли проверить» (whitelist без ответа релея).
   */
  async function resolve(rawUsername) {
    const username = normalize(rawUsername);
    if (!isValid(username)) {
      return { exists: false, invalid: true, username };
    }

    /* Пробуем быстрый путь, только если сеть это позволяет. */
    const net = window.NetworkStatus ? NetworkStatus.getStatus() : 'online';
    if (net === 'online') {
      const http = await httpResolve(username);
      if (http) return { ...http, username };
    }

    /* whitelist/offline или HTTP не ответил → путь через Диск.
       Существование проверяем через запрос ключа (существует ⇔ ключ есть). */
    const hasToken = await Auth.hasToken().catch(() => false);
    if (!hasToken) {
      return { exists: null, username, needsToken: true };
    }
    const key = await diskKey(username);
    if (key.found === true) return { exists: true, username, publicKey: key.publicKey, via: 'disk' };
    if (key.found === false) return { exists: false, username, via: 'disk' };
    return { exists: null, username, via: 'disk', timeout: true };
  }

  /**
   * requestKey(username) — получить публичный ключ (для шифрования).
   * Возвращает { found: true|false|null, publicKey?, via }.
   */
  async function requestKey(rawUsername) {
    const username = normalize(rawUsername);
    if (!isValid(username)) return { found: false, invalid: true };

    const net = window.NetworkStatus ? NetworkStatus.getStatus() : 'online';
    if (net === 'online') {
      const http = await httpKey(username);
      if (http) return http;
    }
    const hasToken = await Auth.hasToken().catch(() => false);
    if (!hasToken) return { found: null, needsToken: true };
    return diskKey(username);
  }

  return { init, setServerUrl, normalize, isValid, resolve, requestKey,
           get serverUrl() { return SERVER_URL; } };
})();
