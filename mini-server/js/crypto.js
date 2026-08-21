/* ============================================================
 * MINI · crypto.js
 * Криптография поверх WebCrypto API. Ничего самописного.
 *
 * Схема (по архитектурному документу):
 *  - Пара ключей ECDH P-256 на устройство (X25519 — когда станет
 *    доступен в WebCrypto, замена будет точечной: см. ALGO).
 *  - Общий секрет ECDH → AES-256-GCM для полезной нагрузки.
 *  - Отдельная пара ECDSA P-256 для sign()/verify().
 *  - Приватные ключи никогда не покидают устройство (IndexedDB).
 * ============================================================ */

const MiniCrypto = (() => {
  const ALGO = { name: 'ECDH', namedCurve: 'P-256' };
  const SIGN_ALGO = { name: 'ECDSA', namedCurve: 'P-256' };
  const SIGN_PARAMS = { name: 'ECDSA', hash: 'SHA-256' };
  const AES = { name: 'AES-GCM', length: 256 };

  const enc = new TextEncoder();
  const dec = new TextDecoder();

  /* ---------- утилиты кодирования ---------- */

  function bufToB64(buf) {
    const bytes = new Uint8Array(buf);
    let bin = '';
    for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
    return btoa(bin);
  }

  function b64ToBuf(b64) {
    const bin = atob(b64);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return bytes.buffer;
  }

  /* ---------- ключи устройства ---------- */

  /**
   * Гарантирует наличие постоянных пар ключей устройства.
   * Генерирует один раз; повторные и конкурентные вызовы получают
   * один и тот же промис (защита от TOCTOU-гонки при двойном boot).
   * Возвращает { created: boolean }.
   */
  let keysPromise = null;

  function generateKeys() {
    if (!keysPromise) {
      keysPromise = doGenerateKeys().catch((e) => {
        keysPromise = null; // при ошибке даём шанс повторить
        throw e;
      });
    }
    return keysPromise;
  }

  async function doGenerateKeys() {
    const existing = await Storage.get('privateKey');
    if (existing) return { created: false };

    const ecdh = await crypto.subtle.generateKey(ALGO, true, ['deriveKey', 'deriveBits']);
    const ecdsa = await crypto.subtle.generateKey(SIGN_ALGO, true, ['sign', 'verify']);

    await Storage.set('publicKey',      await crypto.subtle.exportKey('jwk', ecdh.publicKey));
    await Storage.set('privateKey',     await crypto.subtle.exportKey('jwk', ecdh.privateKey));
    await Storage.set('signPublicKey',  await crypto.subtle.exportKey('jwk', ecdsa.publicKey));
    await Storage.set('signPrivateKey', await crypto.subtle.exportKey('jwk', ecdsa.privateKey));

    if (!(await Storage.get('deviceId'))) {
      await Storage.set('deviceId', crypto.randomUUID());
    }

    return { created: true };
  }

  async function hasKeys() {
    return !!(await Storage.get('privateKey')) && !!(await Storage.get('publicKey'));
  }

  /** Публичный ключ устройства (JWK) — можно публиковать. */
  async function exportPublicKey() {
    return Storage.get('publicKey');
  }

  /* ---------- ECDH: общий секрет ---------- */

  /**
   * Выводит симметричный ключ AES-256-GCM из нашего приватного
   * ключа и публичного JWK собеседника/сервера.
   */
  async function deriveSharedKey(peerPublicJwk) {
    const privJwk = await Storage.get('privateKey');
    if (!privJwk) throw new Error('Нет приватного ключа — вызовите generateKeys()');

    const priv = await crypto.subtle.importKey('jwk', privJwk, ALGO, false, ['deriveKey']);
    const pub = await crypto.subtle.importKey('jwk', peerPublicJwk, ALGO, false, []);

    return crypto.subtle.deriveKey(
      { name: 'ECDH', public: pub },
      priv,
      AES,
      false,
      ['encrypt', 'decrypt']
    );
  }

  /* ---------- AES-256-GCM ---------- */

  /**
   * encrypt(plaintext: string, key: CryptoKey)
   * → { payload: base64, nonce: base64 }
   */
  async function encrypt(plaintext, key) {
    const nonce = crypto.getRandomValues(new Uint8Array(12));
    const ct = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: nonce },
      key,
      enc.encode(plaintext)
    );
    return { payload: bufToB64(ct), nonce: bufToB64(nonce.buffer) };
  }

  /** decrypt({ payload, nonce }, key) → string */
  async function decrypt({ payload, nonce }, key) {
    const pt = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: new Uint8Array(b64ToBuf(nonce)) },
      key,
      b64ToBuf(payload)
    );
    return dec.decode(pt);
  }

  /* ---------- подпись ---------- */

  /** sign(data: string) → base64-подпись приватным ключом устройства. */
  async function sign(data) {
    const jwk = await Storage.get('signPrivateKey');
    if (!jwk) throw new Error('Нет ключа подписи — вызовите generateKeys()');
    const key = await crypto.subtle.importKey('jwk', jwk, SIGN_ALGO, false, ['sign']);
    const sig = await crypto.subtle.sign(SIGN_PARAMS, key, enc.encode(data));
    return bufToB64(sig);
  }

  /** verify(data: string, signatureB64, publicJwk) → boolean */
  async function verify(data, signatureB64, publicJwk) {
    const key = await crypto.subtle.importKey('jwk', publicJwk, SIGN_ALGO, false, ['verify']);
    return crypto.subtle.verify(SIGN_PARAMS, key, b64ToBuf(signatureB64), enc.encode(data));
  }

  /* ---------- конверт сообщения (раздел 14 архдока) ---------- */

  /**
   * Собирает подписанный зашифрованный конверт:
   * { version, id, sender, receiver, timestamp, nonce, payload, signature }
   */
  async function buildEnvelope(plaintext, receiver, sharedKey) {
    const { payload, nonce } = await encrypt(plaintext, sharedKey);
    const envelope = {
      version: 1,
      id: crypto.randomUUID(),
      sender: await Storage.get('deviceId'),
      receiver,
      timestamp: Date.now(),
      nonce,
      payload,
    };
    envelope.signature = await sign(JSON.stringify({ ...envelope, signature: undefined }));
    return envelope;
  }

  return {
    generateKeys, hasKeys, exportPublicKey,
    deriveSharedKey, encrypt, decrypt,
    sign, verify, buildEnvelope,
    bufToB64, b64ToBuf,
  };
})();
