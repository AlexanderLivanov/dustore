/* ============================================================
 * MINI · app.js
 * Точка входа: инициализация модулей, навигация, Service Worker.
 * Порядок: Storage → Crypto → UI-модули → SW.
 * ============================================================ */

const App = (() => {
  /* ---------- навигация ---------- */

  function goChats() {
    document.body.dataset.screen = 'chats';
    document.body.dataset.view = 'chat';
    syncTabs();
  }

  function goSettings() {
    document.body.dataset.screen = 'settings';
    document.body.dataset.view = 'settings';
    syncTabs();
    Settings.refreshDebug();
  }

  function syncTabs() {
    const screen = document.body.dataset.screen;
    document.getElementById('tabChats').classList.toggle('active', screen === 'chats' || screen === 'chat');
    document.getElementById('tabSettings').classList.toggle('active', screen === 'settings');
  }

  function bindNav() {
    document.getElementById('tabChats').addEventListener('click', goChats);
    document.getElementById('tabSettings').addEventListener('click', goSettings);
    document.getElementById('btnSettingsTop').addEventListener('click', goSettings);
    document.getElementById('btnToChats').addEventListener('click', goChats);
    document.getElementById('btnBack').addEventListener('click', goChats);
  }

  /* ---------- глобальный перехват ошибок → Debug ---------- */

  function bindErrorSink() {
    window.addEventListener('error', (e) => {
      Settings.logError(e.message || String(e.type));
    });
    window.addEventListener('unhandledrejection', (e) => {
      Settings.logError('Promise: ' + (e.reason && e.reason.message ? e.reason.message : String(e.reason)));
    });
  }

  /* ---------- Service Worker ---------- */

  function registerSW() {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('./sw.js').catch((e) => {
        Settings.logError('SW: ' + e.message);
      });
    }
  }

  /* ---------- запуск ---------- */

  async function boot() {
    bindErrorSink();
    Modal.init();
    bindNav();

    try {
      await Storage.init();
    } catch (e) {
      Settings.logError('IndexedDB: ' + e.message);
    }

    try {
      const { created } = await MiniCrypto.generateKeys();
      if (created) console.info('MINI: ключи устройства сгенерированы');
    } catch (e) {
      Settings.logError('Crypto: ' + e.message);
    }

    await Directory.init();
    await Settings.init();
    await Messenger.init();
    NetworkStatus.init();
    syncTabs();
    registerSW();
  }

  document.addEventListener('DOMContentLoaded', boot);

  return { goChats, goSettings, syncTabs };
})();
