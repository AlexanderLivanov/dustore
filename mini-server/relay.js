/* ============================================================
 * MINI Server · relay.js
 * Почтальон: опрашивает MINI/outgoing на Дисках пользователей
 * и разносит файлы. Понимает два вида:
 *
 *   msg__<получатель>__<uuid>.json  — зашифрованный конверт
 *   keyreq__<ник>.json              — запрос публичного ключа
 *
 * Инварианты безопасности:
 *  1. АНТИ-СПУФИНГ: поле sender внутри конверта обязано совпадать
 *     с ником владельца Диска, откуда файл взят. Иначе — отказ.
 *     Подделать отправителя нельзя, не взломав Яндекс-аккаунт.
 *  2. Сервер не может читать переписку: payload зашифрован ECDH-
 *     ключами устройств, у сервера только публичные ключи.
 *  3. ИДЕМПОТЕНТНОСТЬ: msg_id записывается в processed; повторный
 *     прогон того же файла (упали между upload и delete) не даст дубля.
 *
 * Попутно синхронизирует MINI/keys/public.json → БД.
 * ============================================================ */

const cfg = require('./config');
const db = require('./db');
const ya = require('./yandex');

const ROOT = 'MINI';
const OUT = `${ROOT}/outgoing`;
const IN = `${ROOT}/incoming`;
const KEYS = `${ROOT}/keys`;

const MSG_RE = /^msg__([a-z0-9_]{3,20})__([0-9a-f-]{36})\.json$/;
const KEYREQ_RE = /^keyreq__([a-z0-9_]{3,20})\.json$/;

const log = (...a) => console.log(new Date().toISOString(), '·', ...a);

/* ---------- токены с автообновлением ---------- */

async function freshToken(user) {
  if (user.token_expires && user.token_expires - Date.now() > 60_000) {
    return user.access_token;
  }
  if (!user.refresh_token) return user.access_token; // надеемся на лучшее
  const tok = await ya.refreshToken(user.refresh_token);
  const expires = Date.now() + (tok.expires_in || 0) * 1000;
  db.updateTokens(user.id, tok.access_token, tok.refresh_token || user.refresh_token, expires);
  log(`токен обновлён для @${user.username}`);
  return tok.access_token;
}

/* ---------- синк публичного ключа пользователя ---------- */

async function syncPublicKey(user, token) {
  try {
    const jwk = await ya.downloadJson(token, `${KEYS}/public.json`);
    if (jwk && jwk.kty) {
      const json = JSON.stringify(jwk);
      if (json !== user.public_key) {
        db.setPublicKey(user.id, json);
        log(`ключ @${user.username} синхронизирован`);
      }
    }
  } catch (e) {
    if (e.status !== 404) log(`ключ @${user.username}: ${e.message}`);
  }
}

/* ---------- обработка одного файла из outgoing ---------- */

async function handleMessage(sender, token, item, recipientName, msgId) {
  if (db.isProcessed(msgId)) {
    await ya.deleteFile(token, item.path); // хвост от прошлого падения
    return;
  }
  if (item.size > cfg.MAX_ENVELOPE_BYTES) {
    log(`отказ: конверт ${msgId} от @${sender.username} слишком большой (${item.size})`);
    await ya.deleteFile(token, item.path);
    return;
  }

  const recipient = db.userByUsername(recipientName);
  if (!recipient || !recipient.access_token) {
    log(`отказ: получатель @${recipientName} не найден (от @${sender.username})`);
    await ya.deleteFile(token, item.path);
    return; // TODO этап 7: класть отправителю уведомление об ошибке
  }

  const envelope = await ya.downloadJson(token, item.path);

  /* АНТИ-СПУФИНГ: конверт пришёл с Диска sender — внутри должно быть то же имя */
  if (envelope.sender !== sender.username) {
    log(`СПУФИНГ: файл с Диска @${sender.username}, в конверте sender=${envelope.sender}. Отброшено.`);
    await ya.deleteFile(token, item.path);
    return;
  }
  if (envelope.id !== msgId) {
    log(`отказ: id в конверте (${envelope.id}) не совпадает с именем файла (${msgId})`);
    await ya.deleteFile(token, item.path);
    return;
  }

  const rToken = await freshToken(recipient);
  await ya.ensureFolder(rToken, ROOT);
  await ya.ensureFolder(rToken, IN);
  await ya.uploadJson(rToken, `${IN}/msg__${sender.username}__${msgId}.json`, envelope);

  db.markProcessed(msgId);           // фиксируем ДО удаления исходника:
  await ya.deleteFile(token, item.path); // упадём здесь — повтор не создаст дубль

  log(`@${sender.username} → @${recipientName}: ${msgId}`);
}

async function handleKeyRequest(requester, token, item, targetName) {
  const target = db.userByUsername(targetName);
  await ya.deleteFile(token, item.path); // запрос одноразовый в любом случае

  const rToken = await freshToken(requester);
  await ya.ensureFolder(rToken, ROOT);
  await ya.ensureFolder(rToken, IN);

  if (!target || !target.public_key) {
    await ya.uploadJson(rToken, `${IN}/key__${targetName}.json`,
      { username: targetName, error: 'not_found' });
    log(`ключ @${targetName} для @${requester.username}: не найден`);
    return;
  }
  await ya.uploadJson(rToken, `${IN}/key__${targetName}.json`,
    { username: target.username, publicKey: JSON.parse(target.public_key) });
  log(`ключ @${targetName} доставлен @${requester.username}`);
}

/* ---------- цикл ---------- */

/* ---------- whoami: сообщаем пользователю его ник ---------- */

async function syncWhoami(user, token) {
  try {
    const path = `${ROOT}/config/whoami.json`;
    const current = await ya.downloadJson(token, path).catch(() => null);
    if (!current || current.username !== user.username) {
      await ya.ensureFolder(token, ROOT);
      await ya.ensureFolder(token, `${ROOT}/config`);
      await ya.uploadJson(token, path, {
        username: user.username,
        displayName: user.display_name,
        updatedAt: Date.now(),
      });
      log(`whoami для @${user.username} обновлён на Диске`);
    }
  } catch (e) {
    if (e.status !== 404) log(`whoami @${user.username}: ${e.message}`);
  }
}

async function processUser(user) {
  const token = await freshToken(user);
  await syncPublicKey(user, token);
  await syncWhoami(user, token);

  const items = await ya.listFolder(token, OUT);
  for (const item of items) {
    if (item.type !== 'file') continue;
    try {
      let m = item.name.match(MSG_RE);
      if (m) { await handleMessage(user, token, item, m[1], m[2]); continue; }
      m = item.name.match(KEYREQ_RE);
      if (m) { await handleKeyRequest(user, token, item, m[1]); continue; }
      log(`неизвестный файл в outgoing @${user.username}: ${item.name} — пропущен`);
    } catch (e) {
      log(`ошибка на ${item.name} (@${user.username}): ${e.message}`);
    }
  }
}

async function tick() {
  const users = db.allWithTokens();
  for (const user of users) {
    try { await processUser(user); }
    catch (e) { log(`пользователь @${user.username}: ${e.message}`); }
  }
}

if (require.main === module) {
  log(`Релей запущен, интервал ${cfg.RELAY_INTERVAL_MS} мс`);
  (async function loop() {
    await tick();
    setTimeout(loop, cfg.RELAY_INTERVAL_MS);
  })();
}

module.exports = { tick, processUser, MSG_RE, KEYREQ_RE };
