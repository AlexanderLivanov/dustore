/**
 * swad/js/e2e.js
 * E2E-шифрование личных диалогов (DM, фаза 1).
 *
 * Модель: identity-keypair на юзера (ECDH P-256), приватный ключ живёт
 * только в браузере. Ключ переписки — детерминированный ECDH(my_priv, peer_pub)
 * + HKDF, отдельно не хранится (для DM ключ пары == ключ беседы).
 * Приватный ключ кэшируется как CryptoKey (extractable:false) в IndexedDB —
 * PIN нужен только на новом устройстве / после очистки хранилища браузера.
 */
const E2E = (() => {
    const enc = new TextEncoder();
    const dec = new TextDecoder();

    const b64 = {
        enc: (buf) => btoa(String.fromCharCode(...new Uint8Array(buf))),
        dec: (str) => Uint8Array.from(atob(str), c => c.charCodeAt(0)).buffer,
    };

    /* ---------- IndexedDB: кэш приватного identity-ключа ---------- */
    const IDB_NAME = 'dustore-e2e';
    const IDB_STORE = 'keys';

    function idbOpen() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(IDB_NAME, 1);
            req.onupgradeneeded = () => { req.result.createObjectStore(IDB_STORE); };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }
    async function idbGetPrivateKey(userId) {
        const db = await idbOpen();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(IDB_STORE, 'readonly');
            const rq = tx.objectStore(IDB_STORE).get('priv:' + userId);
            rq.onsuccess = () => resolve(rq.result || null);
            rq.onerror = () => reject(rq.error);
        });
    }
    async function idbSetPrivateKey(userId, cryptoKey) {
        const db = await idbOpen();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(IDB_STORE, 'readwrite');
            tx.objectStore(IDB_STORE).put(cryptoKey, 'priv:' + userId);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    /* ---------- 1. Identity keypair (ECDH P-256) ---------- */
    async function generateIdentityKeypair() {
        return crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' },
            true,
            ['deriveKey', 'deriveBits']
        );
    }
    async function exportPublicKey(publicKey) {
        return b64.enc(await crypto.subtle.exportKey('spki', publicKey));
    }
    async function importPublicKey(b64Str) {
        return crypto.subtle.importKey(
            'spki', b64.dec(b64Str),
            { name: 'ECDH', namedCurve: 'P-256' },
            true, []
        );
    }

    /* ---------- 2. PIN -> AES key (PBKDF2) ---------- */
    async function deriveKeyFromPin(pin, saltB64, iterations = 210000) {
        const pinKey = await crypto.subtle.importKey('raw', enc.encode(pin), 'PBKDF2', false, ['deriveKey']);
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt: b64.dec(saltB64), iterations, hash: 'SHA-256' },
            pinKey, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']
        );
    }

    /* ---------- 3. Wrap / unwrap приватного ключа PIN-ом ---------- */
    async function wrapPrivateKey(privateKey, pin) {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const saltB64 = b64.enc(salt.buffer);
        const wrapKey = await deriveKeyFromPin(pin, saltB64);
        const pkcs8 = await crypto.subtle.exportKey('pkcs8', privateKey);
        const nonce = crypto.getRandomValues(new Uint8Array(12));
        const ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: nonce }, wrapKey, pkcs8);
        return { encryptedPrivateKey: b64.enc(ct), nonce: b64.enc(nonce.buffer), salt: saltB64 };
    }
    async function unwrapPrivateKey(encryptedPrivateKeyB64, nonceB64, saltB64, pin, iterations) {
        const wrapKey = await deriveKeyFromPin(pin, saltB64, iterations);
        // Неверный PIN => AES-GCM провалит проверку целостности и кинет исключение.
        // Отдельно PIN нигде не сверяем — это встроенная authenticated-encryption проверка.
        const pkcs8 = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: b64.dec(nonceB64) }, wrapKey, b64.dec(encryptedPrivateKeyB64));
        return crypto.subtle.importKey('pkcs8', pkcs8, { name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveKey', 'deriveBits']);
    }

    /* ---------- 4. ECDH + HKDF -> ключ переписки ---------- */
    async function deriveConversationKey(myPrivateKey, peerPublicKey) {
        const sharedBits = await crypto.subtle.deriveBits({ name: 'ECDH', public: peerPublicKey }, myPrivateKey, 256);
        const hkdfKey = await crypto.subtle.importKey('raw', sharedBits, 'HKDF', false, ['deriveKey']);
        return crypto.subtle.deriveKey(
            { name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: enc.encode('dustore-dm-v1') },
            hkdfKey, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']
        );
    }

    /* ---------- 5. Сообщения ---------- */
    async function encryptMessage(conversationKey, plaintext) {
        const nonce = crypto.getRandomValues(new Uint8Array(12));
        const ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: nonce }, conversationKey, enc.encode(plaintext));
        return { body: b64.enc(ct), nonce: b64.enc(nonce.buffer) };
    }
    async function decryptMessage(conversationKey, bodyB64, nonceB64) {
        const pt = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: b64.dec(nonceB64) }, conversationKey, b64.dec(bodyB64));
        return dec.decode(pt);
    }

    return {
        generateIdentityKeypair, exportPublicKey, importPublicKey,
        wrapPrivateKey, unwrapPrivateKey,
        deriveConversationKey, encryptMessage, decryptMessage,
        idbGetPrivateKey, idbSetPrivateKey,
    };
})();