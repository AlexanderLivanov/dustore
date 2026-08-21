/* ============================================================
 * MINI Server · yandex.js
 * Яндекс OAuth (authorization code flow) + REST Яндекс.Диска
 * от имени пользователя. Токены сервер получает при входе на
 * сайт — в отличие от клиента-мессенджера, где токен вводится
 * вручную (verification_code).
 * ============================================================ */

const cfg = require('./config');

const OAUTH = 'https://oauth.yandex.ru';
const LOGIN = 'https://login.yandex.ru';
const DISK = 'https://cloud-api.yandex.net/v1/disk';

/* ---------- OAuth ---------- */

function authUrl(state) {
  const p = new URLSearchParams({
    response_type: 'code',
    client_id: cfg.CLIENT_ID,
    redirect_uri: cfg.BASE_URL + '/auth/callback',
    state,
  });
  return `${OAUTH}/authorize?${p}`;
}

async function exchangeCode(code) {
  const r = await fetch(`${OAUTH}/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      grant_type: 'authorization_code',
      code,
      client_id: cfg.CLIENT_ID,
      client_secret: cfg.CLIENT_SECRET,
    }),
  });
  if (!r.ok) throw new Error(`OAuth token: HTTP ${r.status}`);
  return r.json(); // { access_token, refresh_token, expires_in }
}

async function refreshToken(refresh) {
  const r = await fetch(`${OAUTH}/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      grant_type: 'refresh_token',
      refresh_token: refresh,
      client_id: cfg.CLIENT_ID,
      client_secret: cfg.CLIENT_SECRET,
    }),
  });
  if (!r.ok) throw new Error(`OAuth refresh: HTTP ${r.status}`);
  return r.json();
}

async function userInfo(accessToken) {
  const r = await fetch(`${LOGIN}/info?format=json`, {
    headers: { Authorization: `OAuth ${accessToken}` },
  });
  if (!r.ok) throw new Error(`login.yandex.ru: HTTP ${r.status}`);
  return r.json(); // { id, display_name, login, ... }
}

/* ---------- Диск от имени пользователя ---------- */

async function diskRequest(token, path, options = {}) {
  const r = await fetch(DISK + path, {
    ...options,
    headers: { Authorization: `OAuth ${token}`, ...(options.headers || {}) },
  });
  if (r.status === 204) return null;
  const data = await r.json().catch(() => null);
  if (!r.ok) {
    const err = new Error((data && data.message) || `HTTP ${r.status}`);
    err.status = r.status;
    throw err;
  }
  return data;
}

async function listFolder(token, folderPath) {
  try {
    const data = await diskRequest(
      token, `/resources?path=${encodeURIComponent(folderPath)}&limit=200`);
    return (data._embedded && data._embedded.items) || [];
  } catch (e) {
    if (e.status === 404) return []; // папки нет — значит пусто
    throw e;
  }
}

async function downloadJson(token, filePath) {
  const meta = await diskRequest(
    token, `/resources/download?path=${encodeURIComponent(filePath)}`);
  const r = await fetch(meta.href);
  if (!r.ok) throw new Error(`download: HTTP ${r.status}`);
  return r.json();
}

async function uploadJson(token, filePath, obj) {
  const meta = await diskRequest(
    token, `/resources/upload?path=${encodeURIComponent(filePath)}&overwrite=true`);
  const r = await fetch(meta.href, {
    method: meta.method || 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(obj),
  });
  if (!r.ok && r.status !== 201 && r.status !== 202) {
    throw new Error(`upload: HTTP ${r.status}`);
  }
}

async function deleteFile(token, filePath) {
  await diskRequest(
    token, `/resources?path=${encodeURIComponent(filePath)}&permanently=true`,
    { method: 'DELETE' });
}

async function ensureFolder(token, folderPath) {
  try {
    await diskRequest(token, `/resources?path=${encodeURIComponent(folderPath)}`, { method: 'PUT' });
  } catch (e) {
    if (e.status !== 409) throw e; // 409 = уже существует
  }
}

module.exports = {
  authUrl, exchangeCode, refreshToken, userInfo,
  listFolder, downloadJson, uploadJson, deleteFile, ensureFolder,
};
