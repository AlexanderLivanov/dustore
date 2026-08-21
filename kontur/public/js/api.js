// ============================================================
// API.JS — слой связи с бэкендом (PHP REST API)
// ============================================================
// Единственное место, где фронтенд знает про сеть.
// Все модули работают через API.*, а не через fetch напрямую.
// Это называется "инкапсуляция транспорта" — если завтра
// поменяется формат запросов, правим только здесь.
// ============================================================

const API = (() => {

  // Базовый путь. Если фронт и API на одном домене — просто /api.
  const BASE = '/api';

  /**
   * Низкоуровневый запрос.
   * credentials:'include' — КРИТИЧНО: заставляет браузер слать
   * cookie сессии PHP, иначе сервер не узнает залогиненного юзера.
   */
  async function request(method, path, body = null) {
    const opts = {
      method,
      credentials: 'include',
      headers: {},
    };
    if (body !== null) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }

    let res;
    try {
      res = await fetch(BASE + path, opts);
    } catch (networkErr) {
      // Сеть недоступна / сервер лежит
      throw new ApiError('Сервер недоступен. Проверьте подключение.', 0);
    }

    // Пытаемся распарсить JSON (даже у ошибок тело в JSON)
    let data = null;
    const text = await res.text();
    if (text) {
      try { data = JSON.parse(text); }
      catch { data = { raw: text }; }
    }

    if (!res.ok) {
      const msg = data?.error || `Ошибка ${res.status}`;
      throw new ApiError(msg, res.status, data?.detail);
    }

    return data;
  }

  // Типизированная ошибка — позволяет ловить по статусу
  class ApiError extends Error {
    constructor(message, status, detail = null) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.detail = detail;
    }
  }

  // ── Удобные обёртки ──────────────────────────────────────
  const get   = (path)       => request('GET', path);
  const post  = (path, body) => request('POST', path, body);
  const patch = (path, body) => request('PATCH', path, body);
  const del   = (path)       => request('DELETE', path);

  // ── Доменные методы (читаются как намерения) ─────────────
  return {
    ApiError,
    get, post, patch, del,

    // Auth
    register: (username, password) => post('/auth/register', { username, password }),
    login:    (username, password) => post('/auth/login',    { username, password }),
    logout:   ()                   => post('/auth/logout'),
    me:       ()                   => get('/auth/me'),

    // Articles
    listArticles: (status = 'approved', tag = null) => {
      const q = new URLSearchParams({ status });
      if (tag) q.set('tag', tag);
      return get('/articles?' + q.toString());
    },
    getArticle:    (id)              => get('/articles/' + id),
    createArticle: (title, tag, body) => post('/articles', { title, tag, body }),
    editArticle:   (id, fields)      => patch('/articles/' + id, fields),
    deleteArticle: (id)              => del('/articles/' + id),
    moderate:      (id, action)      => post(`/articles/${id}/moderate`, { action }),
    rate:          (id, rating)      => post(`/articles/${id}/rate`, { rating }),

    // Sections
    listSections:  ()                => get('/sections'),
    createSection: (name, kind)      => post('/sections', { name, kind }),
    deleteSection: (id)              => del('/sections/' + id),
  };
})();

window.API = API;
