/* ============================================================
 * MINI Server · db.js
 * SQLite. Драйвер выбирается автоматически:
 *   - Node ≥ 22.5: встроенный node:sqlite (без зависимостей)
 *   - иначе:       better-sqlite3 (npm)
 * API у обоих совпадает: db.prepare(sql).get/run/all(), db.exec().
 * Единственная точка доступа к БД — зеркалит правило storage.js.
 * ============================================================ */

const path = require('path');

const DB_PATH = process.env.MINI_DB || path.join(__dirname, 'mini-server.db');

/* --- выбор драйвера --- */
function openDatabase(dbPath) {
  try {
    // Node ≥ 22.5 — встроенный модуль, ничего ставить не нужно
    const { DatabaseSync } = require('node:sqlite');
    return new DatabaseSync(dbPath);
  } catch (e) {
    if (e.code !== 'ERR_UNKNOWN_BUILTIN_MODULE') throw e;
    // Старый Node — берём npm-драйвер с совместимым API
    try {
      const Better = require('better-sqlite3');
      return new Better(dbPath);
    } catch (e2) {
      console.error(
        '\nMINI Server: нужен либо Node ≥ 22.5 (встроенный node:sqlite),\n' +
        'либо пакет better-sqlite3 для текущего Node ' + process.version + '.\n' +
        'Установите его:  npm install better-sqlite3\n');
      process.exit(1);
    }
  }
}

const db = openDatabase(DB_PATH);

db.exec(`
  PRAGMA journal_mode = WAL;

  CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    yandex_uid    TEXT UNIQUE NOT NULL,
    display_name  TEXT,
    username      TEXT UNIQUE,             -- глобальный @ник, NULL до выбора
    public_key    TEXT,                    -- JWK ECDH-ключа клиента (JSON)
    access_token  TEXT,                    -- OAuth-токен для Диска
    refresh_token TEXT,
    token_expires INTEGER,                 -- unix ms
    created_at    INTEGER NOT NULL
  );

  CREATE TABLE IF NOT EXISTS sessions (
    sid        TEXT PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id),
    expires_at INTEGER NOT NULL
  );

  -- идемпотентность релея: какие конверты уже перенесены
  CREATE TABLE IF NOT EXISTS processed (
    msg_id       TEXT PRIMARY KEY,
    processed_at INTEGER NOT NULL
  );
`);

/* ---------- users ---------- */

const qUserByUid = db.prepare('SELECT * FROM users WHERE yandex_uid = ?');
const qUserById = db.prepare('SELECT * FROM users WHERE id = ?');
const qUserByUsername = db.prepare('SELECT * FROM users WHERE username = ? COLLATE NOCASE');
const qInsertUser = db.prepare(`
  INSERT INTO users (yandex_uid, display_name, access_token, refresh_token, token_expires, created_at)
  VALUES (?, ?, ?, ?, ?, ?)
`);
const qUpdateTokens = db.prepare(
  'UPDATE users SET access_token = ?, refresh_token = ?, token_expires = ? WHERE id = ?');
const qUpdateUsername = db.prepare('UPDATE users SET username = ? WHERE id = ?');
const qUpdateDisplayName = db.prepare('UPDATE users SET display_name = ? WHERE id = ?');
const qUpdatePublicKey = db.prepare('UPDATE users SET public_key = ? WHERE id = ?');
const qAllWithTokens = db.prepare(
  'SELECT * FROM users WHERE access_token IS NOT NULL AND username IS NOT NULL');

function upsertUserFromOAuth({ uid, displayName, accessToken, refreshToken, expiresAt }) {
  const existing = qUserByUid.get(uid);
  if (existing) {
    qUpdateTokens.run(accessToken, refreshToken, expiresAt, existing.id);
    return qUserById.get(existing.id);
  }
  const info = qInsertUser.run(uid, displayName, accessToken, refreshToken, expiresAt, Date.now());
  return qUserById.get(info.lastInsertRowid);
}

const USERNAME_RE = /^[a-z0-9_]{3,20}$/;

function setUsername(userId, raw) {
  const username = String(raw || '').trim().toLowerCase();
  if (!USERNAME_RE.test(username)) {
    return { ok: false, error: 'Ник: 3–20 символов, только a-z, 0-9 и _' };
  }
  const taken = qUserByUsername.get(username);
  if (taken && taken.id !== userId) {
    return { ok: false, error: 'Этот ник уже занят' };
  }
  qUpdateUsername.run(username, userId);
  return { ok: true, username };
}

/* ---------- sessions ---------- */

const SESSION_TTL = 30 * 24 * 3600 * 1000; // 30 дней
const qInsertSession = db.prepare('INSERT INTO sessions (sid, user_id, expires_at) VALUES (?, ?, ?)');
const qGetSession = db.prepare('SELECT * FROM sessions WHERE sid = ?');
const qDeleteSession = db.prepare('DELETE FROM sessions WHERE sid = ?');
const qPruneSessions = db.prepare('DELETE FROM sessions WHERE expires_at < ?');

function createSession(userId) {
  const sid = require('crypto').randomBytes(32).toString('hex');
  qInsertSession.run(sid, userId, Date.now() + SESSION_TTL);
  return sid;
}

function userBySession(sid) {
  if (!sid) return null;
  qPruneSessions.run(Date.now());
  const s = qGetSession.get(sid);
  return s ? qUserById.get(s.user_id) : null;
}

/* ---------- relay ---------- */

const qMarkProcessed = db.prepare(
  'INSERT OR IGNORE INTO processed (msg_id, processed_at) VALUES (?, ?)');
const qIsProcessed = db.prepare('SELECT 1 FROM processed WHERE msg_id = ?');

module.exports = {
  db,
  upsertUserFromOAuth,
  userById: (id) => qUserById.get(id),
  userByUsername: (u) => qUserByUsername.get(u),
  allWithTokens: () => qAllWithTokens.all(),
  setUsername,
  setDisplayName: (id, name) => qUpdateDisplayName.run(String(name || '').slice(0, 60), id),
  setPublicKey: (id, jwkJson) => qUpdatePublicKey.run(jwkJson, id),
  updateTokens: (id, a, r, e) => qUpdateTokens.run(a, r, e, id),
  createSession,
  userBySession,
  deleteSession: (sid) => qDeleteSession.run(sid),
  markProcessed: (msgId) => qMarkProcessed.run(msgId, Date.now()),
  isProcessed: (msgId) => !!qIsProcessed.get(msgId),
  USERNAME_RE,
};
