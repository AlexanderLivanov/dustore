/* ============================================================
 * MINI Server · server.js
 * Сайт учёток: вход через Яндекс, выбор глобального @ника,
 * публичный API каталога ключей. Дизайн — токены MINI.
 * ============================================================ */

const express = require('express');
const crypto = require('crypto');
const cfg = require('./config');
const db = require('./db');
const ya = require('./yandex');

cfg.assertConfigured();

const app = express();
app.use(express.urlencoded({ extended: false }));
app.use(express.json({ limit: '64kb' }));
app.use('/static', express.static(__dirname + '/public'));

/* ---------- мини-хелперы: сессии и рендер ---------- */

function parseCookies(req) {
  const out = {};
  (req.headers.cookie || '').split(';').forEach((p) => {
    const i = p.indexOf('=');
    if (i > 0) out[p.slice(0, i).trim()] = decodeURIComponent(p.slice(i + 1));
  });
  return out;
}

function currentUser(req) {
  return db.userBySession(parseCookies(req).sid);
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function page(title, body) {
  return `<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)} · MINI ID</title>
<link rel="stylesheet" href="/static/style.css">
</head>
<body>
<div class="shell">
  <div class="brand">MINI</div>
  <div class="brand-sub">MINI ID · глобальные ники</div>
  ${body}
  <div class="footer">MINI · версия 1.0</div>
</div>
</body>
</html>`;
}

/* ---------- страницы ---------- */

app.get('/', (req, res) => {
  const user = currentUser(req);
  if (user) return res.redirect('/profile');
  res.send(page('Вход', `
    <div class="card">
      <h1>Один ник — на всех устройствах</h1>
      <p class="muted">Войдите через Яндекс, выберите глобальный ник —
      и собеседники смогут найти вас в мессенджере по <b>@нику</b>,
      а сообщения поедут через ваш Яндекс.Диск.</p>
      <a class="primary-btn" href="/auth/login">Войти через Яндекс</a>
    </div>`));
});

/* OAuth: редирект на Яндекс с anti-CSRF state */
const pendingStates = new Map(); // state → expires
app.get('/auth/login', (req, res) => {
  const state = crypto.randomBytes(16).toString('hex');
  pendingStates.set(state, Date.now() + 10 * 60 * 1000);
  res.redirect(ya.authUrl(state));
});

app.get('/auth/callback', async (req, res) => {
  const { code, state } = req.query;
  const exp = pendingStates.get(state);
  pendingStates.delete(state);
  if (!code || !exp || exp < Date.now()) {
    return res.status(400).send(page('Ошибка', `<div class="card"><h1>Не получилось</h1>
      <p class="muted">Код или state невалидны. <a href="/auth/login">Попробовать снова</a></p></div>`));
  }
  try {
    const tok = await ya.exchangeCode(code);
    const info = await ya.userInfo(tok.access_token);
    const user = db.upsertUserFromOAuth({
      uid: String(info.id),
      displayName: info.display_name || info.login || 'Пользователь',
      accessToken: tok.access_token,
      refreshToken: tok.refresh_token || null,
      expiresAt: Date.now() + (tok.expires_in || 0) * 1000,
    });
    const sid = db.createSession(user.id);
    res.setHeader('Set-Cookie',
      `sid=${sid}; HttpOnly; Path=/; Max-Age=${30 * 24 * 3600}; SameSite=Lax`);
    res.redirect('/profile');
  } catch (e) {
    res.status(500).send(page('Ошибка', `<div class="card"><h1>OAuth не удался</h1>
      <p class="muted">${esc(e.message)}</p></div>`));
  }
});

app.post('/auth/logout', (req, res) => {
  db.deleteSession(parseCookies(req).sid);
  res.setHeader('Set-Cookie', 'sid=; Path=/; Max-Age=0');
  res.redirect('/');
});

/* Профиль: имя + глобальный ник */
app.get('/profile', (req, res) => {
  const user = currentUser(req);
  if (!user) return res.redirect('/');
  const err = req.query.err ? `<div class="error">${esc(req.query.err)}</div>` : '';
  const ok = req.query.ok ? `<div class="ok">Сохранено</div>` : '';
  res.send(page('Профиль', `
    <div class="card">
      <div class="profile-row">
        <div class="profile-avatar">${esc((user.display_name || 'U')[0].toUpperCase())}</div>
        <div>
          <div class="profile-name">${esc(user.display_name)}</div>
          <div class="muted">${user.username ? '@' + esc(user.username) : 'ник ещё не выбран'}</div>
        </div>
      </div>
      ${err}${ok}
      <form method="post" action="/profile">
        <label class="field">
          <div class="field-label">Отображаемое имя</div>
          <input name="display_name" value="${esc(user.display_name)}" maxlength="60">
        </label>
        <label class="field">
          <div class="field-label">Глобальный ник</div>
          <input name="username" value="${esc(user.username || '')}"
                 placeholder="например: alex" pattern="[a-z0-9_]{3,20}"
                 title="3–20 символов: a-z, 0-9, _">
          <div class="hint">3–20 символов: строчные латинские, цифры, подчёркивание.
          По нику вас находят в мессенджере.</div>
        </label>
        <button class="primary-btn" type="submit">Сохранить</button>
      </form>
      <div class="divider"></div>
      <div class="muted small">Ключ шифрования: ${user.public_key
        ? '<b class="okc">получен с вашего Диска</b>'
        : 'ещё не синхронизирован — откройте мессенджер с токеном, ключ подтянется автоматически'}</div>
      <form method="post" action="/auth/logout" class="logout">
        <button class="outline-btn" type="submit">Выйти</button>
      </form>
    </div>`));
});

app.post('/profile', (req, res) => {
  const user = currentUser(req);
  if (!user) return res.redirect('/');
  db.setDisplayName(user.id, req.body.display_name);
  if (req.body.username || user.username) {
    const r = db.setUsername(user.id, req.body.username);
    if (!r.ok) return res.redirect('/profile?err=' + encodeURIComponent(r.error));
  }
  res.redirect('/profile?ok=1');
});

/* ---------- публичный API ---------- */

/** Каталог ключей: по нику отдаём публичный JWK. Приватного у сервера нет. */
app.get('/api/users/:username/key', (req, res) => {
  const u = db.userByUsername(req.params.username);
  if (!u || !u.public_key) return res.status(404).json({ error: 'not found' });
  res.json({ username: u.username, publicKey: JSON.parse(u.public_key) });
});

/** Существует ли ник. Отдаёт только факт наличия + display_name,
 *  без токенов и приватных полей. Используется поиском в мессенджере. */
app.get('/api/users/:username', (req, res) => {
  const u = db.userByUsername(req.params.username);
  if (!u) return res.status(404).json({ exists: false });
  res.json({
    exists: true,
    username: u.username,
    displayName: u.display_name,
    hasKey: !!u.public_key,
  });
});

app.get('/api/health', (_req, res) => res.json({ ok: true, ts: Date.now() }));

/* ---------- старт ---------- */

if (require.main === module) {
  app.listen(cfg.PORT, () => {
    console.log(`MINI ID: http://localhost:${cfg.PORT} (BASE_URL=${cfg.BASE_URL})`);
  });
}

module.exports = app;
