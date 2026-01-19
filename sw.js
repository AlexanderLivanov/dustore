const CACHE_NAME = "pwa-version-1";

const assets = [
  "/manifest.json",
  "/index.php",
  "/me.php"
];

self.addEventListener("install", (evt) => {
  evt.waitUntil(
    self.skipWaiting().then(() => {
      return caches.open(CACHE_NAME).then((cache) => {
        return cache.addAll(assets).catch((err) => {
          console.log("Failed to cache assets:", err);
        });
      });
    })
  );
});

self.addEventListener("activate", (evt) => {
  evt.waitUntil(
    clients.claim().then(() => {
      return caches.keys().then((keys) => {
        return Promise.all(
          keys
            .filter((key) => key !== CACHE_NAME)
            .map((key) => caches.delete(key))
        );
      });
    })
  );
});

self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      // Если ресурс найден в кэше, возвращаем его
      if (response) {
        return response;
      }

      // Если ресурс не найден в кэше, пытаемся получить его из сети
      return fetch(event.request).catch(() => {
        // Если запрос не удался (например, оффлайн), возвращаем резервную страницу
        return caches.match("./index.php");
      });
    })
  );
});

self.addEventListener("push", (event) => {
  let data = {};

  if (event.data) {
    data = event.data.json();
  }

  const title = data.title || "Новое уведомление";
  const options = {
    body: data.body || "Есть обновление",
    icon: "/swad/static/img/logo_new.png",
    badge: "/swad/static/img/logo_new.png",
    data: {
      url: data.url || "/"
    }
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  event.waitUntil(
    clients.matchAll({ type: "window" }).then((clientsArr) => {
      for (const client of clientsArr) {
        if (client.url === event.notification.data.url && "focus" in client) {
          return client.focus();
        }
      }
      return clients.openWindow(event.notification.data.url);
    })
  );
});
