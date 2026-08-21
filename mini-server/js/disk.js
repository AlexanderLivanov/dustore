/* ============================================================
 * MINI · disk.js
 * Яндекс.Диск как транспорт данных.
 * REST: https://cloud-api.yandex.net/v1/disk
 *
 * Структура на Диске (раздел 11 архдока):
 *   MINI/
 *     incoming/   outgoing/   cache/   keys/   config/
 * ============================================================ */

const Disk = (() => {
  const API = 'https://cloud-api.yandex.net/v1/disk';
  const APP_ROOT = 'MINI';
  const APP_DIRS = ['incoming', 'outgoing', 'cache', 'keys', 'config'];

  async function headers() {
    const token = await Auth.getToken();
    if (!token) throw new Error('Нет токена — задайте его в настройках');
    return { Authorization: `OAuth ${token}` };
  }

  async function request(path, options = {}) {
    const h = await headers();
    const r = await fetch(API + path, {
      ...options,
      headers: { ...h, ...(options.headers || {}) },
    });
    if (r.status === 204) return null;
    const data = await r.json().catch(() => null);
    if (!r.ok) {
      const msg = data && (data.message || data.description) || `HTTP ${r.status}`;
      const err = new Error(msg);
      err.status = r.status;
      throw err;
    }
    return data;
  }

  /* ---------- Этап 2: список файлов ---------- */

  /** Плоский список файлов пользователя (для отладки и модального окна). */
  async function getFiles(limit = 50) {
    const data = await request(`/resources/files?limit=${limit}`);
    return data.items || [];
  }

  /* ---------- Этап 3: папка приложения ---------- */

  async function createFolder(path) {
    try {
      await request(`/resources?path=${encodeURIComponent(path)}`, { method: 'PUT' });
      return true;
    } catch (e) {
      if (e.status === 409) return false; // уже существует — это нормально
      throw e;
    }
  }

  /** Создаёт MINI/ и все служебные подпапки. Идемпотентно. */
  async function createAppFolders() {
    await createFolder(APP_ROOT);
    for (const dir of APP_DIRS) {
      await createFolder(`${APP_ROOT}/${dir}`);
    }
    return `${APP_ROOT}/{${APP_DIRS.join(',')}}`;
  }

  /* ---------- Этапы 4–5: загрузка и скачивание ---------- */

  /** upload(path, blob) — двухшаговая загрузка через href. */
  async function upload(path, blob, overwrite = true) {
    const meta = await request(
      `/resources/upload?path=${encodeURIComponent(path)}&overwrite=${overwrite}`
    );
    const r = await fetch(meta.href, { method: meta.method || 'PUT', body: blob });
    if (!r.ok && r.status !== 201 && r.status !== 202) {
      throw new Error(`Загрузка не удалась: HTTP ${r.status}`);
    }
    return true;
  }

  /** download(path) → Blob */
  async function download(path) {
    const meta = await request(`/resources/download?path=${encodeURIComponent(path)}`);
    const r = await fetch(meta.href);
    if (!r.ok) throw new Error(`Скачивание не удалось: HTTP ${r.status}`);
    return r.blob();
  }

  /* ---------- JSON-хелперы (конверты, ключи, keyreq) ---------- */

  /** uploadJson(path, obj) — кладёт JSON-файл на Диск. */
  async function uploadJson(path, obj) {
    const blob = new Blob([JSON.stringify(obj)], { type: 'application/json' });
    return upload(path, blob, true);
  }

  /** downloadJson(path) → object | null (null, если файла нет). */
  async function downloadJson(path) {
    try {
      const blob = await download(path);
      return JSON.parse(await blob.text());
    } catch (e) {
      if (e.status === 404) return null;
      throw e;
    }
  }

  /** listFolder(path) → items[] (пустой массив, если папки нет). */
  async function listFolder(path) {
    try {
      const data = await request(
        `/resources?path=${encodeURIComponent(path)}&limit=200`);
      return (data._embedded && data._embedded.items) || [];
    } catch (e) {
      if (e.status === 404) return [];
      throw e;
    }
  }

  /** remove(path) — в корзину (permanently=false). */
  async function remove(path, permanently = false) {
    await request(
      `/resources?path=${encodeURIComponent(path)}&permanently=${permanently}`,
      { method: 'DELETE' }
    );
    return true;
  }

  /* ---------- watchChanges: поллинг входящих ---------- */

  /**
   * Опрашивает MINI/incoming с интервалом и вызывает cb(newItems).
   * Возвращает функцию остановки. Push в среде белых списков
   * недоступен, поэтому поллинг — честный компромисс.
   */
  function watchChanges(cb, intervalMs = 15000) {
    const known = new Set();
    let first = true;
    let stopped = false;

    async function tick() {
      if (stopped) return;
      try {
        const data = await request(
          `/resources?path=${encodeURIComponent(APP_ROOT + '/incoming')}&limit=100`
        );
        const items = (data._embedded && data._embedded.items) || [];
        const fresh = items.filter((i) => !known.has(i.path));
        fresh.forEach((i) => known.add(i.path));
        /* первый прогон — только строим срез, без уведомлений */
        if (!first && fresh.length) cb(fresh);
        first = false;
      } catch (e) {
        /* сеть могла мигнуть — молча ждём следующего тика */
      }
      if (!stopped) setTimeout(tick, intervalMs);
    }

    tick();
    return () => { stopped = true; };
  }

  return { getFiles, createFolder, createAppFolders, upload, download,
           uploadJson, downloadJson, listFolder, remove, watchChanges, APP_ROOT };
})();
