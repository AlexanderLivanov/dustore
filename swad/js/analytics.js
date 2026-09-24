/**
 * swad/js/analytics.js
 * Лёгкий клиентский трекер общего модуля аналитики. Подключать один раз на странице:
 *   <script src="/swad/js/analytics.js"></script>
 * Дальше доступен как window.DustoreAnalytics.
 */
(function (global) {
  'use strict';

  var ENDPOINT = '/swad/controllers/track_event.php';

  function send(payload) {
    var body = JSON.stringify(payload);
    try {
      if (navigator.sendBeacon) {
        var blob = new Blob([body], { type: 'application/json' });
        if (navigator.sendBeacon(ENDPOINT, blob)) return;
      }
    } catch (e) { /* падаем в fetch ниже */ }
    try {
      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        keepalive: true,
      }).catch(function () {});
    } catch (e) { /* тихо игнорируем — аналитика не должна ронять страницу */ }
  }

  function track(subjectType, subjectId, eventType, value, meta) {
    if (!subjectType || !subjectId || !eventType) return;
    send({
      subject_type: subjectType,
      subject_id: subjectId,
      event_type: eventType,
      value: value || null,
      meta: meta || null,
    });
  }

  /**
   * "Просмотр" (view) — элемент минимум наполовину виден ≥2с, ИЛИ на нём задержали
   * курсор ≥1с. Ровно то определение, которое просили: "навелись или смотрели
   * несколько секунд". Срабатывает один раз на элемент.
   */
  function observeView(el, subjectType, subjectId) {
    if (!el) return;
    var fired = false;
    var timer = null;

    function fire() {
      if (fired) return;
      fired = true;
      track(subjectType, subjectId, 'view');
    }

    if ('IntersectionObserver' in global) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
            if (!timer) timer = setTimeout(fire, 2000);
          } else if (timer) {
            clearTimeout(timer);
            timer = null;
          }
        });
      }, { threshold: [0, 0.5, 1] });
      io.observe(el);
    }

    el.addEventListener('mouseenter', function () {
      if (!timer && !fired) timer = setTimeout(fire, 1000);
    });
    el.addEventListener('mouseleave', function () {
      if (timer) { clearTimeout(timer); timer = null; }
    });
  }

  /**
   * Время в веб-игре. Считает реальное время с открытой/видимой вкладкой,
   * шлёт тики раз в ~20с и финальный тик через sendBeacon при уходе со страницы.
   * Работает и для анонимных игроков — track() сам подставит anon_id.
   */
  function trackPlaytime(gameId) {
    var acc = 0;
    var last = Date.now();
    var iv = setInterval(function () {
      if (document.visibilityState !== 'visible') { last = Date.now(); return; }
      var now = Date.now();
      acc += Math.round((now - last) / 1000);
      last = now;
      if (acc >= 20) {
        track('game', gameId, 'playtime', acc);
        acc = 0;
      }
    }, 5000);

    document.addEventListener('visibilitychange', function () { last = Date.now(); });

    window.addEventListener('pagehide', function () {
      var now = Date.now();
      acc += Math.round((now - last) / 1000);
      if (acc > 0) track('game', gameId, 'playtime', acc);
      clearInterval(iv);
    });
  }

  global.DustoreAnalytics = { track: track, observeView: observeView, trackPlaytime: trackPlaytime };
})(window);