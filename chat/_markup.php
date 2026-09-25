<?php
/**
 * chat/_markup.php — разметка мессенджера. Общая для /chat/ (десктоп) и /m/chat
 * (мобильное PWA): chat.js один на оба, значит и DOM должен быть один.
 * Ожидает $hasStudio.
 */
?>
<div class="app" id="app">
  <aside class="panel side">
    <div class="side-head">
      <div class="brand">
        <span class="glyph"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-5.1A8 8 0 1 1 21 12z"/></svg></span>
        <div>Чаты<small>личные · студии · уведомления</small></div>
        <button type="button" class="icon-btn side-more" id="sideMore" aria-label="Меню чатов">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
        </button>
      </div>
      <div class="tabs">
        <button type="button" class="tab active" data-tab="personal">Личные</button>
        <?php if ($hasStudio): ?><button type="button" class="tab" data-tab="studio">Студия</button><?php endif; ?>
      </div>
    </div>

    <div class="search-top" id="searchWrap">
      <span class="si"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
      <input id="searchInput" placeholder="Поиск или новый чат…" autocomplete="off" enterkeyhint="search">
      <button type="button" id="searchClear" aria-label="Очистить">&times;</button>
    </div>

    <div class="list" id="list"><div class="empty">Загрузка…</div></div>
    <div class="search-results" id="searchResults" hidden></div>
  </aside>

  <section class="panel room" id="room">
    <div class="room-empty" id="roomEmpty">
      <div class="re-art" aria-hidden="true">
        <svg width="132" height="104" viewBox="0 0 132 104" fill="none">
          <defs><linearGradient id="reG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e6379a"/><stop offset="1" stop-color="#74155d"/></linearGradient></defs>
          <rect x="4" y="10" width="78" height="50" rx="16" fill="url(#reG)" opacity=".9"/>
          <path d="M22 60v14l14-14" fill="url(#reG)" opacity=".9"/>
          <rect x="50" y="42" width="78" height="46" rx="16" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.18)"/>
          <path d="M110 88v12l-12-12" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.18)"/>
          <circle cx="28" cy="35" r="4" fill="#fff"/><circle cx="43" cy="35" r="4" fill="#fff" opacity=".75"/><circle cx="58" cy="35" r="4" fill="#fff" opacity=".5"/>
          <rect x="64" y="58" width="44" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/><rect x="64" y="69" width="28" height="5" rx="2.5" fill="rgba(255,255,255,.2)"/>
        </svg>
      </div>
      <div class="re-title">Выберите диалог</div>
      <div class="re-text">Откройте переписку слева или найдите собеседника через поиск</div>
      <button type="button" class="re-btn" id="emptyNew">Новый диалог</button>
    </div>

    <div class="room-head" id="roomHead" hidden>
      <button class="back" id="back" aria-label="Назад">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <button class="peer" id="peerHead">
        <div class="av" id="rhAv"></div>
        <div style="min-width:0">
          <div class="rh-name" id="rhName"></div>
          <div class="rh-sub" id="rhSub"></div>
        </div>
      </button>
      <div class="head-menu">
        <button class="icon-btn" id="menuBtn" aria-label="Меню">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
        </button>
        <div class="menu" id="menu" hidden>
          <button id="markRead">Отметить прочитанным</button>
          <button id="wpOpen">Обои чата</button>
          <button class="danger" id="delConv">Удалить переписку</button>
        </div>
      </div>
    </div>

    <button type="button" class="pinbar" id="pinbar" hidden>
      <span class="pb-line" id="pbLine"></span>
      <span class="pb-main"><span class="pb-title" id="pbTitle">Закреплённое</span><span class="pb-text" id="pbText"></span></span>
      <span class="pb-x" id="pbX" role="button" aria-label="Открепить">&times;</span>
    </button>

    <div class="thread" id="thread" hidden></div>

    <div class="composer-wrap" id="composer" hidden>
      <div class="reply-bar" id="replyBar" hidden>
        <svg class="rb-ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14L4 9l5-5"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
        <div class="rb-main"><div class="rb-name" id="rbName"></div><div class="rb-text" id="rbText"></div></div>
        <button type="button" class="rb-x" id="rbX" aria-label="Отменить ответ">&times;</button>
      </div>
      <div class="attach-tray" id="attachTray" hidden></div>
      <div class="composer">
        <button type="button" class="attach" id="attachBtn" aria-label="Прикрепить файл">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.4 11.1l-8.5 8.5a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.7 3.7 0 0 1 5.2 5.2l-8.5 8.5a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8"/></svg>
        </button>
        <input type="file" id="fileInput" multiple hidden>
        <textarea id="input" rows="1" placeholder="Написать сообщение…" enterkeyhint="send"></textarea>
        <button class="send" id="send" disabled aria-label="Отправить"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
      </div>
    </div>

    <div class="dropzone" id="dropzone" hidden><div>Отпустите, чтобы прикрепить</div></div>

    <div class="profile" id="profile">
      <div class="profile-head"><button class="icon-btn" id="profBack">‹</button><span>Профиль</span></div>
      <div class="profile-body" id="profileBody"></div>
    </div>
  </section>
</div>

<!-- контекстное меню: сообщения, беседы, сайдбар -->
<div class="ctx" id="ctx" hidden role="menu"></div>

<!-- настройки звука и уведомлений -->
<div class="modal" id="settings" hidden>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="stTitle">
    <div class="modal-head"><b id="stTitle">Звук и уведомления</b><button type="button" class="icon-btn" id="stClose" aria-label="Закрыть">&times;</button></div>
    <div class="st-section">Звук входящих</div>
    <div class="st-sounds" id="stSounds"></div>
    <label class="st-vol">Громкость <input type="range" id="stVolume" min="0" max="100" step="5"></label>
    <input type="file" id="soundInput" accept="audio/mpeg,audio/ogg,audio/wav,audio/webm,audio/aac,audio/mp4,.mp3,.ogg,.wav,.m4a" hidden>
    <div class="st-section">Уведомления на этом устройстве</div>
    <div class="st-push"><span id="pushState">…</span><button type="button" class="st-btn" id="pushBtn" hidden>Включить</button></div>
  </div>
</div>

<div class="modal" id="wpModal" hidden>
  <div class="modal-card wp-card" role="dialog" aria-modal="true" aria-labelledby="wpTitle">
    <div class="modal-head"><b id="wpTitle">Обои чата</b><button type="button" class="icon-btn" id="wpClose" aria-label="Закрыть">&times;</button></div>
    <div class="wp-preview" id="wpPreview">
      <div class="wp-b them">Как тебе новые обои?</div>
      <div class="wp-b mine">Огонь, оставляем 🔥</div>
    </div>
    <div class="wp-scope" role="tablist">
      <button type="button" data-scope="shared" class="on">Для обоих</button>
      <button type="button" data-scope="mine">Только для меня</button>
    </div>
    <div class="wp-grid" id="wpGrid"></div>
    <label class="st-vol">Затемнение <input type="range" id="wpDim" min="0" max="80" step="5" value="0"></label>
    <input type="file" id="wpInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
    <p class="wp-note" id="wpNote"></p>
    <div class="wp-foot">
      <button type="button" class="wp-link" id="wpResetMine" hidden>Вернуть общие</button>
      <button type="button" class="st-btn" id="wpApply">Применить</button>
    </div>
  </div>
</div>

<div class="lightbox" id="lightbox" hidden><img alt=""><a class="lb-dl" id="lbDl" href="#" download>Скачать</a></div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

