/* Смоук-тест MINI: бутстрап + ключевые сценарии без браузера. */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
require('fake-indexeddb/auto'); // глобальный indexedDB

const ROOT = path.join(__dirname, '..');
const html = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8');

const dom = new JSDOM(html, {
  url: 'http://localhost:8000/',
  runScripts: 'outside-only',
  pretendToBeVisual: true,
});

const { window } = dom;

// --- окружение ---
window.indexedDB = indexedDB;                    // fake-indexeddb
// jsdom объявляет window.crypto как read-only геттер без subtle — подменяем жёстко
Object.defineProperty(window, 'crypto', {
  value: require('crypto').webcrypto,
  configurable: true,
});
window.TextEncoder = TextEncoder;
window.TextDecoder = TextDecoder;
// Мок сервера каталога: известные ники резолвятся, прочее — 404.
// Управляется через globalThis.__dirUsers из тела теста.
globalThis.__dirUsers = { vasya: { displayName: 'Вася', publicKey: { kty: 'EC', x: 'vx' } } };
window.fetch = async (url) => {
  const s = String(url);
  const m = s.match(/\/api\/users\/([a-z0-9_]+)(\/key)?$/);
  if (m) {
    const u = globalThis.__dirUsers[m[1]];
    if (!u) return { ok: false, status: 404, json: async () => ({ exists: false }) };
    if (m[2]) return { ok: true, status: 200, json: async () => ({ username: m[1], publicKey: u.publicKey }) };
    return { ok: true, status: 200, json: async () => ({ exists: true, username: m[1], displayName: u.displayName, hasKey: true }) };
  }
  throw new Error('fetch не замокан: ' + s);
};
window.AbortController = AbortController; // jsdom не даёт его из коробки
window.matchMedia = () => ({ matches: false, addListener() {}, removeListener() {} });
window.innerWidth = 1280;

// btoa/atob нужны crypto.js
window.btoa = (s) => Buffer.from(s, 'binary').toString('base64');
window.atob = (s) => Buffer.from(s, 'base64').toString('binary');

const errors = [];
window.addEventListener('error', (e) => errors.push('window.error: ' + e.message));

// --- загрузка модулей одним скоупом (в браузере <script> делят глобальный лексический скоуп, в eval — нет) ---
const scripts = ['storage.js','crypto.js','auth.js','disk.js','directory.js','network.js','modal.js','settings.js','messenger.js','app.js'];
const bundle = scripts
  .map((s) => fs.readFileSync(path.join(ROOT, 'js', s), 'utf8'))
  .join('\n;\n') + '\n;window.__test = { Storage, MiniCrypto, NetworkStatus, Messenger, Directory };';
dom.window.eval(bundle);

// --- запуск ---
(async () => {
  const assert = (cond, name) => {
    console.log((cond ? '  ✅ ' : '  ❌ ') + name);
    if (!cond) process.exitCode = 1;
  };

  // jsdom сам генерирует DOMContentLoaded после парсинга реального
  // index.html (readyState 'loading' → 'complete'); ручной повторный
  // dispatch здесь не нужен и приводил к двойному вызову boot().
  await new Promise((r) => setTimeout(r, 600)); // даём boot() отработать

  console.log('— Бутстрап —');
  assert(errors.length === 0, 'нет глобальных ошибок' + (errors.length ? ': ' + errors.join('; ') : ''));

  const S = dom.window.__test.Storage;
  assert(S.isReady(), 'IndexedDB инициализирована');

  const keys = await S.keys();
  assert(keys.includes('privateKey') && keys.includes('publicKey'), 'пара ECDH сгенерирована');
  assert(keys.includes('signPrivateKey'), 'пара подписи ECDSA сгенерирована');
  assert(keys.includes('deviceId'), 'deviceId создан');

  console.log('— UI —');
  const doc = dom.window.document;
  assert(doc.querySelector('.list-empty') !== null, 'пустой список чатов показывает empty-state (демо-фикстуры удалены)');
  assert(doc.querySelectorAll('.chat-row').length === 0, 'демо-чатов больше нет (0 строк)');
  assert(doc.querySelector('.panel-empty') !== null, 'панель переписки показывает empty-state без активного чата');
  assert(doc.getElementById('dbgVersion').textContent === '1.0.0', 'Debug: версия отображается');
  assert(doc.getElementById('dbgKeys').textContent === 'OK', 'Debug: Keys OK');
  assert(doc.getElementById('dbgIdb').textContent === 'OK', 'Debug: IndexedDB OK');

  console.log('— Сеть —');
  const N = dom.window.__test.NetworkStatus;
  await new Promise((r) => setTimeout(r, 250)); // даём checkNetwork() отработать
  assert(N.getStatus() === 'offline', `при недоступном fetch статус offline (получено: ${N.getStatus()})`);
  assert(doc.getElementById('networkIndicator').className.includes('offline'), 'индикатор окрашен в offline');
  doc.getElementById('networkIndicator').click();
  assert(!doc.getElementById('networkTooltip').classList.contains('hidden'), 'тултип открывается по клику');
  assert(doc.getElementById('networkTooltip').textContent.length > 10, 'тултип содержит пояснение статуса');
  doc.getElementById('networkIndicator').click();
  assert(doc.getElementById('networkTooltip').classList.contains('hidden'), 'повторный клик закрывает тултип');
  N.stop();

  console.log('— Сценарий: отправка без активного чата (no-op) —');
  doc.getElementById('draftInput').value = 'Эхо в пустоту';
  doc.getElementById('btnSend').click();
  assert(doc.getElementById('draftInput').value === 'Эхо в пустоту', 'без активного чата инпут не очищается — send() тихо вышел');

  const M = dom.window.__test.Messenger;
  const Dir = dom.window.__test.Directory;

  console.log('— Directory: резолв ника (мок сервера, online) —');
  // форсируем online, чтобы Directory шёл быстрым HTTP-путём
  dom.window.__test.NetworkStatus.getStatus = () => 'online';

  const foundRes = await Dir.resolve('vasya');
  assert(foundRes.exists === true, 'существующий ник резолвится (exists=true)');
  assert(foundRes.displayName === 'Вася', 'display_name пришёл с сервера');
  const missRes = await Dir.resolve('nobody');
  assert(missRes.exists === false, 'несуществующий ник → exists=false');
  const badRes = await Dir.resolve('AB');
  assert(badRes.invalid === true, 'невалидный ник отклонён без запроса');

  console.log('— Сценарий: создание DM по нику (verified) —');
  doc.getElementById('btnNewChat').click();
  assert(!doc.getElementById('modalBackdrop').classList.contains('hidden'), 'модалка «Новый чат» открылась');
  assert(doc.getElementById('newChatNick') !== null, 'поле ника отрисовано (DM по умолчанию)');

  doc.getElementById('newChatNick').value = 'vasya';
  doc.getElementById('btnFindNick').click();
  await new Promise((r) => setTimeout(r, 100));
  assert(doc.getElementById('nickStatus').classList.contains('ok'), 'статус «найден» после «Найти»');

  doc.getElementById('btnCreateChat').click();
  await new Promise((r) => setTimeout(r, 100));
  assert(doc.getElementById('modalBackdrop').classList.contains('hidden'), 'модалка закрылась после создания');
  assert(doc.querySelectorAll('.chat-row').length === 1, 'DM-чат появился в списке');
  assert(M.state.chats[0].username === 'vasya', 'username получателя записан');
  assert(M.state.chats[0].verified === true, 'чат помечен verified');
  assert(M.state.chats[0].publicKey && M.state.chats[0].publicKey.x === 'vx', 'публичный ключ получателя сохранён');
  assert(doc.querySelector('.sys-msg').textContent.includes('создан'), 'системное сообщение о создании');

  console.log('— Сценарий: DM по неизвестному нику (pending) —');
  doc.getElementById('btnNewChat').click();
  doc.getElementById('newChatNick').value = 'ghost';
  doc.getElementById('btnFindNick').click();
  await new Promise((r) => setTimeout(r, 100));
  assert(doc.getElementById('nickStatus').classList.contains('warn'), 'статус «не найден» — предупреждение, не блок');
  doc.getElementById('btnCreateChat').click();
  await new Promise((r) => setTimeout(r, 100));
  assert(M.state.chats[0].username === 'ghost', 'черновой чат с ghost создан');
  assert(M.state.chats[0].verified === false, 'черновой чат помечен как не подтверждённый');

  console.log('— Сценарий: создание группы по названию —');
  doc.getElementById('btnNewChat').click();
  doc.querySelector('.type-pill[data-type="group"]').click();
  assert(doc.querySelector('.type-pill[data-type="group"]').classList.contains('active'), 'pill группы активен');
  assert(!doc.getElementById('newChatName').closest('.field').classList.contains('hidden'), 'в режиме группы показано поле названия');
  doc.getElementById('newChatName').value = 'Команда';
  doc.getElementById('btnCreateChat').click();
  await new Promise((r) => setTimeout(r, 50));
  assert(M.state.chats[0].type === 'group' && M.state.chats[0].name === 'Команда', 'группа создана по названию');

  console.log('— Сценарий: отправка + персистентность —');
  M.openChat(M.state.chats.find((c) => c.username === 'vasya').id);

  console.log('— Сценарий: отправка + персистентность —');
  const before = doc.querySelectorAll('#messages .msg').length;
  doc.getElementById('draftInput').value = 'Тестовое сообщение';
  doc.getElementById('btnSend').click();
  const after = doc.querySelectorAll('#messages .msg').length;
  assert(after === before + 1, 'сообщение добавлено в ленту');
  assert(M.state.chats.find((c) => c.username === 'vasya').preview === 'Тестовое сообщение', 'превью активного чата обновилось');

  await new Promise((r) => setTimeout(r, 300)); // ждём дебаунс persist()
  const storedChats = await S.get('chats');
  assert(Array.isArray(storedChats) && storedChats.length === 3, 'все 3 чата сохранены в IndexedDB');
  const vasyaChat = storedChats.find((c) => c.username === 'vasya');
  assert(vasyaChat && vasyaChat.verified === true, 'verified-флаг сохранён в IndexedDB');
  assert(vasyaChat.publicKey && vasyaChat.publicKey.x === 'vx', 'публичный ключ пережил сохранение');
  assert(vasyaChat.msgs.some((m) => m.text === 'Тестовое сообщение'), 'сообщение сохранено — переживёт перезагрузку');

  console.log('— Сценарий: навигация в настройки —');
  doc.getElementById('tabSettings').click();
  assert(doc.body.dataset.view === 'settings', 'data-view переключился на settings');
  assert(doc.body.dataset.screen === 'settings', 'data-screen переключился на settings');
  assert(!doc.getElementById('panelSettings').classList.contains('hidden'),
    'на #panelSettings нет класса hidden (видимостью владеет только CSS-роутинг)');
  doc.getElementById('btnToChats').click();
  assert(doc.body.dataset.view === 'chat', 'кнопка «К чатам» возвращает обратно');

  console.log('— Сценарий: токен —');
  doc.getElementById('tokenInput').value = 'y0_test_token_123';
  doc.getElementById('btnSaveToken').click();
  await new Promise((r) => setTimeout(r, 300));
  const savedToken = await S.get('token');
  assert(savedToken === 'y0_test_token_123', 'токен сохранён в IndexedDB');

  console.log('— Сценарий: криптографический цикл —');
  const C = dom.window.__test.MiniCrypto;
  // эмулируем второе устройство: ещё одна пара ECDH
  const peer = await window.crypto.subtle.generateKey(
    { name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveKey']);
  const peerPubJwk = await window.crypto.subtle.exportKey('jwk', peer.publicKey);

  const shared = await C.deriveSharedKey(peerPubJwk);
  const env = await C.buildEnvelope('секретное сообщение', 'server', shared);
  assert(env.version === 1 && env.payload && env.nonce && env.signature, 'конверт собран (v1, payload, nonce, signature)');

  // проверяем подпись
  const myPub = await S.get('signPublicKey');
  const valid = await C.verify(
    JSON.stringify({ ...env, signature: undefined }), env.signature, myPub);
  assert(valid, 'подпись конверта верифицируется');

  // расшифровка тем же общим ключом
  const plain = await C.decrypt({ payload: env.payload, nonce: env.nonce }, shared);
  assert(plain === 'секретное сообщение', 'AES-256-GCM: расшифровка совпадает с исходником');

  console.log('\nГотово.');
  process.exit(process.exitCode || 0);
})().catch((e) => { console.error('❌ Тест упал:', e); process.exit(1); });
