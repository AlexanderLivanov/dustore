/* ============================================================
 * MINI · settings.js
 * Экран настроек: авторизация (токен), файлы Яндекс.Диска, Debug.
 * ============================================================ */

const Settings = (() => {
  const APP_VERSION = '1.0.0';
  const MAX_LOG = 20;
  const errors = [];

  let tokenInput, dbg = {};

  /* ---------- журнал ошибок ---------- */

  function logError(message) {
    const time = new Date().toLocaleTimeString('ru-RU');
    errors.unshift(`[${time}] ${message}`);
    if (errors.length > MAX_LOG) errors.pop();
    renderLog();
  }

  function renderLog() {
    const el = document.getElementById('debugLog');
    if (el) el.textContent = errors.length ? errors.join('\n') : 'пусто';
  }

  /* ---------- Debug-панель ---------- */

  function mark(el, ok, okText = 'OK', failText = '—') {
    el.textContent = ok ? okText : failText;
    el.classList.toggle('ok', !!ok);
    el.classList.toggle('err', !ok);
  }

  async function refreshDebug() {
    dbg.version.textContent = APP_VERSION;
    mark(dbg.idb, Storage.isReady());
    mark(dbg.keys, await MiniCrypto.hasKeys().catch(() => false));

    const hasToken = await Auth.hasToken().catch(() => false);
    mark(dbg.token, hasToken, 'OK', 'нет');

    if (hasToken) {
      dbg.net.textContent = '…';
      dbg.net.className = '';
      const check = await Auth.checkToken();
      mark(dbg.net, check.ok, 'OK', check.error || 'ошибка');
      if (!check.ok && check.error) logError(`Проверка токена: ${check.error}`);
    } else {
      mark(dbg.net, false, 'OK', '—');
    }
  }

  /* ---------- действия ---------- */

  async function saveToken() {
    try {
      await Auth.setToken(tokenInput.value);
      tokenInput.value = await Auth.getToken();
      await refreshDebug();
    } catch (e) {
      logError(`Сохранение токена: ${e.message}`);
    }
  }

  async function showDiskFiles() {
    Modal.open('Файлы Яндекс.Диска');
    Modal.showLoading();
    try {
      const items = await Disk.getFiles(50);
      Modal.showFiles(items);
    } catch (e) {
      logError(`Яндекс.Диск: ${e.message}`);
      Modal.showEmpty(`Не удалось получить список: ${e.message}`);
    }
  }

  /* ---------- инициализация ---------- */

  async function init() {
    tokenInput = document.getElementById('tokenInput');
    dbg = {
      version: document.getElementById('dbgVersion'),
      token: document.getElementById('dbgToken'),
      keys: document.getElementById('dbgKeys'),
      idb: document.getElementById('dbgIdb'),
      net: document.getElementById('dbgNet'),
    };

    document.getElementById('btnSaveToken').addEventListener('click', saveToken);
    document.getElementById('btnDiskFiles').addEventListener('click', showDiskFiles);
    tokenInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') saveToken();
    });

    const serverInput = document.getElementById('serverUrlInput');
    const saveServer = async () => {
      const url = serverInput.value.trim();
      if (url) { await Directory.setServerUrl(url); serverInput.value = Directory.serverUrl; }
    };
    document.getElementById('btnSaveServer').addEventListener('click', saveServer);
    serverInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') saveServer(); });
    serverInput.value = Directory.serverUrl;

    const saved = await Auth.getToken().catch(() => null);
    if (saved) tokenInput.value = saved;

    renderLog();
    await refreshDebug();
  }

  return { init, refreshDebug, logError, APP_VERSION };
})();
