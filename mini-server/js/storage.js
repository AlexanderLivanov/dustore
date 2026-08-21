/* ============================================================
 * MINI · storage.js
 * Единственная точка доступа к IndexedDB.
 * Правило проекта: другие модули НЕ обращаются к IndexedDB напрямую.
 *
 * DB:    mini-db
 * Store: data
 * Ключи: token, publicKey, privateKey, signPublicKey, signPrivateKey,
 *        deviceId, settings (позже: cache, contacts, dialogs)
 * ============================================================ */

const Storage = (() => {
  const DB_NAME = 'mini-db';
  const DB_VERSION = 1;
  const STORE = 'data';

  let db = null;

  function init() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);

      req.onupgradeneeded = (e) => {
        const d = e.target.result;
        if (!d.objectStoreNames.contains(STORE)) {
          d.createObjectStore(STORE);
        }
      };

      req.onsuccess = (e) => {
        db = e.target.result;
        resolve(db);
      };

      req.onerror = () => reject(req.error);
    });
  }

  function tx(mode) {
    if (!db) throw new Error('Storage не инициализирован — вызовите Storage.init()');
    return db.transaction(STORE, mode).objectStore(STORE);
  }

  function get(key) {
    return new Promise((resolve, reject) => {
      const req = tx('readonly').get(key);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  function set(key, value) {
    return new Promise((resolve, reject) => {
      const req = tx('readwrite').put(value, key);
      req.onsuccess = () => resolve(true);
      req.onerror = () => reject(req.error);
    });
  }

  function del(key) {
    return new Promise((resolve, reject) => {
      const req = tx('readwrite').delete(key);
      req.onsuccess = () => resolve(true);
      req.onerror = () => reject(req.error);
    });
  }

  function keys() {
    return new Promise((resolve, reject) => {
      const req = tx('readonly').getAllKeys();
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  function isReady() { return !!db; }

  return { init, get, set, del, keys, isReady };
})();
