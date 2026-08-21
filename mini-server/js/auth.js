/* ============================================================
 * MINI · auth.js
 * OAuth Яндекса без callback.
 * redirect_uri фиксирован: https://oauth.yandex.ru/verification_code
 * Пользователь получает access token и вставляет его вручную.
 * ============================================================ */

const Auth = (() => {
  const VERIFICATION_URL = 'https://oauth.yandex.ru/verification_code';

  async function getToken() {
    return Storage.get('token');
  }

  async function setToken(token) {
    const clean = (token || '').trim();
    if (!clean) throw new Error('Пустой токен');
    await Storage.set('token', clean);
    return clean;
  }

  async function clearToken() {
    await Storage.del('token');
  }

  async function hasToken() {
    return !!(await getToken());
  }

  /**
   * Лёгкая проверка живости токена: GET /v1/disk.
   * Возвращает { ok, status, error? }.
   */
  async function checkToken() {
    const token = await getToken();
    if (!token) return { ok: false, status: 0, error: 'Токен не задан' };
    try {
      const r = await fetch('https://cloud-api.yandex.net/v1/disk', {
        headers: { Authorization: `OAuth ${token}` },
      });
      return { ok: r.ok, status: r.status, error: r.ok ? null : `HTTP ${r.status}` };
    } catch (e) {
      return { ok: false, status: 0, error: e.message };
    }
  }

  return { getToken, setToken, clearToken, hasToken, checkToken, VERIFICATION_URL };
})();
