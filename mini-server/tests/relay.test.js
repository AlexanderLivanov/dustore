/* Тест релея MINI: in-memory мок Яндекс.Диска вместо сети.
 * Запуск: MINI_DB=':memory:' node tests/relay.test.js
 */
process.env.MINI_DB = ':memory:';
process.env.MINI_CLIENT_ID = 'test';
process.env.MINI_CLIENT_SECRET = 'test';

const assert = (cond, name) => {
  console.log((cond ? '  ✅ ' : '  ❌ ') + name);
  if (!cond) process.exitCode = 1;
};

/* ---------- мок yandex.js: диски в памяти, по одному на токен ---------- */
const disks = {}; // token → { 'MINI/outgoing/файл.json': {size, body} }

require.cache[require.resolve('../yandex.js')] = {
  exports: {
    async listFolder(token, folder) {
      const d = disks[token] || {};
      return Object.keys(d)
        .filter((p) => p.startsWith(folder + '/'))
        .map((p) => ({ type: 'file', name: p.slice(folder.length + 1), path: p, size: d[p].size }));
    },
    async downloadJson(token, path) {
      const f = (disks[token] || {})[path];
      if (!f) { const e = new Error('404'); e.status = 404; throw e; }
      return JSON.parse(JSON.stringify(f.body));
    },
    async uploadJson(token, path, obj) {
      disks[token] = disks[token] || {};
      disks[token][path] = { size: JSON.stringify(obj).length, body: obj };
    },
    async deleteFile(token, path) { delete (disks[token] || {})[path]; },
    async ensureFolder() {},
    async refreshToken() { throw new Error('не должен вызываться в тесте'); },
  },
};

const db = require('../db');
const relay = require('../relay');

/* ---------- фикстуры: два пользователя ---------- */
function makeUser(uid, username, token) {
  const u = db.upsertUserFromOAuth({
    uid, displayName: username, accessToken: token,
    refreshToken: null, expiresAt: Date.now() + 3600_000,
  });
  db.setUsername(u.id, username);
  db.setPublicKey(u.id, JSON.stringify({ kty: 'EC', crv: 'P-256', x: 'x_' + username, y: 'y' }));
  disks[token] = {};
  return db.userById(u.id);
}

const alex = makeUser('1', 'alex', 'tok_alex');
const vasya = makeUser('2', 'vasya', 'tok_vasya');

(async () => {
  console.log('— Валидация ников —');
  assert(!db.setUsername(alex.id, 'Иван').ok, 'кириллица отклонена');
  assert(!db.setUsername(alex.id, 'ab').ok, 'слишком короткий отклонён');
  assert(!db.setUsername(vasya.id, 'ALEX').ok, 'занятый ник отклонён (без учёта регистра)');
  db.setUsername(alex.id, 'alex'); // восстановить

  console.log('— Доставка конверта —');
  const msgId = '11111111-1111-4111-8111-111111111111';
  disks['tok_alex'][`MINI/outgoing/msg__vasya__${msgId}.json`] = {
    size: 200,
    body: { version: 1, id: msgId, sender: 'alex', receiver: 'vasya', payload: 'x', nonce: 'y', signature: 'z' },
  };
  await relay.tick();
  const delivered = disks['tok_vasya'][`MINI/incoming/msg__alex__${msgId}.json`];
  assert(!!delivered, 'конверт доставлен в incoming Васи');
  assert(delivered.body.payload === 'x', 'payload не тронут (сервер не расшифровывает)');
  assert(!disks['tok_alex'][`MINI/outgoing/msg__vasya__${msgId}.json`], 'исходник удалён из outgoing');
  assert(db.isProcessed(msgId), 'msg_id зафиксирован для идемпотентности');

  console.log('— Идемпотентность (повторный файл после сбоя) —');
  disks['tok_alex'][`MINI/outgoing/msg__vasya__${msgId}.json`] = {
    size: 200, body: { version: 1, id: msgId, sender: 'alex', receiver: 'vasya', payload: 'ДРУГОЙ' },
  };
  await relay.tick();
  assert(disks['tok_vasya'][`MINI/incoming/msg__alex__${msgId}.json`].body.payload === 'x',
    'повторный msg_id не перезаписал доставленное');
  assert(!disks['tok_alex'][`MINI/outgoing/msg__vasya__${msgId}.json`], 'хвост удалён');

  console.log('— Анти-спуфинг —');
  const spoofId = '22222222-2222-4222-8222-222222222222';
  disks['tok_alex'][`MINI/outgoing/msg__vasya__${spoofId}.json`] = {
    size: 200,
    body: { version: 1, id: spoofId, sender: 'vasya', receiver: 'vasya', payload: 'подделка' },
  };
  await relay.tick();
  assert(!disks['tok_vasya'][`MINI/incoming/msg__vasya__${spoofId}.json`], 'конверт с чужим sender не доставлен');
  assert(!disks['tok_alex'][`MINI/outgoing/msg__vasya__${spoofId}.json`], 'подделка удалена');

  console.log('— Несуществующий получатель —');
  const ghostId = '33333333-3333-4333-8333-333333333333';
  disks['tok_alex'][`MINI/outgoing/msg__ghost__${ghostId}.json`] = {
    size: 100, body: { version: 1, id: ghostId, sender: 'alex' },
  };
  await relay.tick();
  assert(!disks['tok_alex'][`MINI/outgoing/msg__ghost__${ghostId}.json`], 'письмо призраку удалено без падения');

  console.log('— Key request —');
  disks['tok_alex']['MINI/outgoing/keyreq__vasya.json'] = { size: 10, body: {} };
  await relay.tick();
  const key = disks['tok_alex']['MINI/incoming/key__vasya.json'];
  assert(!!key && key.body.publicKey.x === 'x_vasya', 'публичный ключ Васи доставлен Алексу');

  disks['tok_alex']['MINI/outgoing/keyreq__ghost.json'] = { size: 10, body: {} };
  await relay.tick();
  assert(disks['tok_alex']['MINI/incoming/key__ghost.json'].body.error === 'not_found',
    'на несуществующий ник — явный not_found, а не тишина');

  console.log('— Синк ключа с Диска —');
  disks['tok_alex']['MINI/keys/public.json'] = {
    size: 80, body: { kty: 'EC', crv: 'P-256', x: 'НОВЫЙ', y: 'y' },
  };
  await relay.tick();
  assert(JSON.parse(db.userByUsername('alex').public_key).x === 'НОВЫЙ',
    'обновлённый ключ с Диска синхронизирован в БД');

  console.log('\nГотово.');
  process.exit(process.exitCode || 0);
})().catch((e) => { console.error('❌ Тест упал:', e); process.exit(1); });
