/* ============================================================
 * MINI · messenger.js
 * Список чатов, лента сообщений, отправка.
 * Данные — демо из дизайн-прототипа; когда появится транспорт
 * (disk.js + сервер), источником станет он.
 * ============================================================ */

const Messenger = (() => {
  /* ---------- демо-данные (из прототипа) ---------- */

  /* Демо-фикстуры прототипа выполнили свою роль — дизайн перенесён.
     Дальше список наполняется реальными данными через транспорт
     Яндекс.Диска (Disk.watchChanges, этап 6+). Пока список пуст —
     это ожидаемое состояние, не баг: см. renderEmptyState(). */
  const people = {};

  const state = {
    activeId: null,
    typing: false,
    live: null,
    chats: [],
  };

  /* Механика live/typing (state.live, state.typing) оставлена для
     будущего realtime через транспорт; фейковые автоответы прототипа
     удалены — в реальном приложении они выглядят как чужие сообщения
     из ниоткуда. */
  let liveTimer = null, doneTimer = null;

  /* ---------- персистентность ---------- */

  const CHATS_KEY = 'chats';
  let persistTimer = null;

  /** Отложенное сохранение: схлопывает частые мутации в одну запись. */
  function persist() {
    clearTimeout(persistTimer);
    persistTimer = setTimeout(() => {
      Storage.set(CHATS_KEY, state.chats).catch((e) => {
        Settings.logError('Сохранение чатов: ' + e.message);
      });
    }, 150);
  }

  async function loadChats() {
    try {
      const saved = await Storage.get(CHATS_KEY);
      if (Array.isArray(saved)) state.chats = saved;
    } catch (e) {
      Settings.logError('Загрузка чатов: ' + e.message);
    }
  }

  /* ---------- утилиты ---------- */

  const $ = (id) => document.getElementById(id);
  const now = () => {
    const d = new Date();
    return d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0');
  };
  const active = () => state.chats.find((c) => c.id === state.activeId);
  let search = '';

  /* ---------- рендер списка чатов ---------- */

  function renderChatList() {
    const list = $('chatList');
    list.innerHTML = '';

    if (!state.chats.length) {
      const empty = document.createElement('div');
      empty.className = 'list-empty';
      empty.innerHTML =
        '<div class="list-empty-mark">MINI</div>' +
        '<div class="list-empty-text">Здесь пока тихо.<br>Создайте первый чат — или дождитесь синхронизации через Яндекс.Диск.</div>';
      const btn = document.createElement('button');
      btn.className = 'primary-btn';
      btn.id = 'btnNewChatEmpty';
      btn.style.flex = 'none';
      btn.textContent = 'Создать чат';
      btn.addEventListener('click', openCreateDialog);
      empty.appendChild(btn);
      list.appendChild(empty);
      return;
    }

    const q = search.trim().toLowerCase();

    for (const c of state.chats) {
      if (q && !c.name.toLowerCase().includes(q)) continue;

      const row = document.createElement('button');
      row.className = 'chat-row' + (c.id === state.activeId ? ' active' : '');
      row.addEventListener('click', () => openChat(c.id));

      const wrap = document.createElement('div');
      wrap.className = 'avatar-wrap';
      const av = document.createElement('div');
      av.className = 'avatar' + (c.type !== 'dm' ? ' group' : '');
      av.textContent = c.init;
      wrap.appendChild(av);
      if (c.online) {
        const dot = document.createElement('div');
        dot.className = 'online-dot';
        wrap.appendChild(dot);
      }

      const bodyEl = document.createElement('div');
      bodyEl.className = 'row-body';

      const top = document.createElement('div');
      top.className = 'row-top';
      const name = document.createElement('div');
      name.className = 'row-name';
      if (c.type !== 'dm') {
        const b = document.createElement('span');
        b.className = 'badge';
        b.textContent = c.type === 'channel' ? '📢' : '👥';
        name.appendChild(b);
      }
      name.appendChild(document.createTextNode(c.name));
      const time = document.createElement('div');
      time.className = 'row-time';
      time.textContent = c.time || '';
      top.append(name, time);

      const bottom = document.createElement('div');
      bottom.className = 'row-bottom';
      const preview = document.createElement('div');
      preview.className = 'row-preview';
      preview.textContent = c.preview || '';
      bottom.appendChild(preview);
      if (c.unread > 0) {
        const u = document.createElement('div');
        u.className = 'unread';
        u.textContent = c.unread;
        bottom.appendChild(u);
      }

      bodyEl.append(top, bottom);
      row.append(wrap, bodyEl);
      list.appendChild(row);
    }
  }

  /* ---------- рендер переписки ---------- */

  function bubbleFor(m) {
    if (m.sticker) {
      const b = document.createElement('div');
      b.className = 'bubble sticker';
      b.textContent = m.sticker;
      return b;
    }
    if (m.photo || m.video) {
      const b = document.createElement('div');
      b.className = 'bubble media';
      b.textContent = m.photo || m.video;
      if (m.video) {
        const play = document.createElement('div');
        play.className = 'play';
        play.innerHTML = '<svg width="14" height="14" viewBox="0 0 16 16" fill="#fff"><path d="M5 3l8 5-8 5z"/></svg>';
        b.appendChild(play);
        if (m.dur) {
          const d = document.createElement('div');
          d.className = 'dur';
          d.textContent = m.dur;
          b.appendChild(d);
        }
      }
      return b;
    }
    const b = document.createElement('div');
    b.className = 'bubble';
    b.textContent = m.text || '';
    return b;
  }

  function renderMessages() {
    const box = $('messages');
    box.innerHTML = '';
    const chat = active();
    if (!chat) {
      const empty = document.createElement('div');
      empty.className = 'panel-empty';
      empty.innerHTML =
        '<div class="panel-empty-mark">MINI</div>' +
        '<div class="panel-empty-text">Выберите чат слева — или дождитесь первого сообщения.</div>';
      box.appendChild(empty);
      return;
    }

    let lastDate = null;
    const frag = document.createDocumentFragment();

    for (const m of chat.msgs) {
      if (m.date && m.date !== lastDate) {
        const sep = document.createElement('div');
        sep.className = 'date-sep';
        sep.textContent = m.date;
        frag.appendChild(sep);
        lastDate = m.date;
      }
      if (m.system) {
        const s = document.createElement('div');
        s.className = 'sys-msg';
        s.textContent = m.text;
        frag.appendChild(s);
        continue;
      }

      const mine = m.from === 'me';
      const wrap = document.createElement('div');
      wrap.className = 'msg ' + (mine ? 'mine' : 'their');

      const sender = m.senderId ? people[m.senderId] : null;
      const isGroupSender = chat.type === 'group' && !mine && sender;

      if (isGroupSender) {
        const nameEl = document.createElement('div');
        nameEl.className = 'sender-name';
        nameEl.textContent = sender.name;
        wrap.appendChild(nameEl);

        const pair = document.createElement('div');
        pair.className = 'msg-with-sender';
        const sAv = document.createElement('div');
        sAv.className = 'sender-avatar';
        sAv.textContent = sender.init;
        pair.append(sAv, bubbleFor(m));
        wrap.appendChild(pair);
      } else {
        wrap.appendChild(bubbleFor(m));
      }

      if (m.reactions && m.reactions.length) {
        const rr = document.createElement('div');
        rr.className = 'reactions';
        for (const r of m.reactions) {
          const chip = document.createElement('div');
          chip.className = 'react-chip' + (r.mine ? ' mine' : '');
          chip.textContent = `${r.e} ${r.n}`;
          rr.appendChild(chip);
        }
        wrap.appendChild(rr);
      }

      const t = document.createElement('div');
      t.className = 'msg-time';
      t.textContent = m.time || '';
      wrap.appendChild(t);

      frag.appendChild(wrap);
    }

    /* «печатает вживую» */
    if (state.live) {
      const wrap = document.createElement('div');
      wrap.className = 'msg their';
      const b = document.createElement('div');
      b.className = 'bubble';
      b.textContent = state.live.shown;
      const caret = document.createElement('span');
      caret.className = 'live-caret';
      b.appendChild(caret);
      const note = document.createElement('div');
      note.className = 'live-note';
      note.textContent = 'печатает вживую…';
      wrap.append(b, note);
      frag.appendChild(wrap);
    } else if (state.typing) {
      const tb = document.createElement('div');
      tb.className = 'typing-bubble';
      tb.innerHTML = '<span></span><span></span><span></span>';
      frag.appendChild(tb);
    }

    box.appendChild(frag);
    box.scrollTop = box.scrollHeight;
  }

  function renderHead() {
    const chat = active();
    const peer = $('chatPeer');

    if (!chat) {
      peer.classList.add('hidden');
      $('composer').classList.add('hidden');
      $('readonlyBar').classList.add('hidden');
      return;
    }
    peer.classList.remove('hidden');
    const av = $('chatAvatar');
    av.textContent = chat.init;
    av.className = 'avatar' + (chat.type !== 'dm' ? ' group' : '');

    $('chatName').textContent = chat.name;

    const st = $('chatStatus');
    if (chat.type === 'channel') {
      st.textContent = chat.subscribers || '';
      st.className = 'peer-status';
    } else if (chat.type === 'group') {
      st.textContent = 'Настя, Игорь, Лена и вы';
      st.className = 'peer-status';
    } else if (chat.username) {
      // DM с реальным получателем: статус подтверждения
      if (chat.verified) {
        st.textContent = '@' + chat.username + ' · подтверждён';
        st.className = 'peer-status online';
      } else {
        st.textContent = '@' + chat.username + ' · не подтверждён';
        st.className = 'peer-status pending';
      }
    } else {
      st.textContent = chat.lastSeen || '';
      st.className = 'peer-status';
    }

    const readonly = chat.type === 'channel';
    $('composer').classList.toggle('hidden', readonly);
    const bar = $('readonlyBar');
    bar.classList.toggle('hidden', !readonly);
    if (readonly) bar.textContent = 'Вы подписаны · комментарии открыты под постами';
  }

  /* ---------- создание чата ---------- */

  const TYPE_LABELS = { dm: 'Личный', group: 'Группа', channel: 'Канал' };

  /**
   * createChat(opts)
   *   name     — отображаемое имя чата
   *   type     — dm | group | channel
   *   username — @ник получателя (только dm)
   *   verified — подтверждён ли ник сервером
   *   publicKey— публичный ключ получателя, если уже получен
   */
  function createChat({ name, type, username = null, verified = false, publicKey = null }) {
    const clean = String(name || '').trim();
    if (!clean) return null;

    const sysText = type === 'dm' && username
      ? (verified ? `Чат с @${username} создан` : `Черновой чат с @${username} · получатель не подтверждён`)
      : `${TYPE_LABELS[type]} чат создан`;

    const chat = {
      id: crypto.randomUUID(),
      type,
      name: clean,
      init: clean[0].toUpperCase(),
      username,
      verified,
      publicKey,
      time: now(),
      preview: '',
      unread: 0,
      msgs: [{ system: true, text: sysText, date: 'Сегодня' }],
    };
    state.chats.unshift(chat); // новый — сверху
    persist();
    renderChatList();
    return chat;
  }

  function openCreateDialog() {
    let selectedType = 'dm';
    let resolved = null; // результат последнего resolve()

    const form = document.createElement('div');
    form.className = 'create-chat-form';

    /* переключатель типа */
    const pills = document.createElement('div');
    pills.className = 'type-pills';
    for (const [value, label] of Object.entries(TYPE_LABELS)) {
      const pill = document.createElement('button');
      pill.type = 'button';
      pill.className = 'type-pill' + (value === selectedType ? ' active' : '');
      pill.textContent = label;
      pill.dataset.type = value;
      pill.addEventListener('click', () => {
        selectedType = value;
        resolved = null;
        pills.querySelectorAll('.type-pill').forEach((p) =>
          p.classList.toggle('active', p === pill));
        syncMode();
      });
      pills.appendChild(pill);
    }

    /* поле DM: ник + «Найти» */
    const dmField = document.createElement('label');
    dmField.className = 'field';
    dmField.innerHTML = '<div class="field-label">Ник собеседника</div>';
    const nickRow = document.createElement('div');
    nickRow.className = 'nick-row';
    const at = document.createElement('span');
    at.className = 'nick-at';
    at.textContent = '@';
    const nickInput = document.createElement('input');
    nickInput.type = 'text';
    nickInput.id = 'newChatNick';
    nickInput.placeholder = 'например: vasya';
    nickInput.autocomplete = 'off';
    nickInput.spellcheck = false;
    nickInput.maxLength = 20;
    const findBtn = document.createElement('button');
    findBtn.type = 'button';
    findBtn.className = 'find-btn';
    findBtn.id = 'btnFindNick';
    findBtn.textContent = 'Найти';
    nickRow.append(at, nickInput, findBtn);
    dmField.appendChild(nickRow);

    /* статус резолва */
    const status = document.createElement('div');
    status.className = 'nick-status hidden';
    status.id = 'nickStatus';

    /* поле group/channel: название */
    const nameField = document.createElement('label');
    nameField.className = 'field hidden';
    nameField.innerHTML = '<div class="field-label">Название</div>';
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.id = 'newChatName';
    nameInput.placeholder = 'Например: Команда';
    nameInput.autocomplete = 'off';
    nameInput.maxLength = 60;
    nameField.appendChild(nameInput);

    const hint = document.createElement('div');
    hint.className = 'create-chat-hint';

    const submit = document.createElement('button');
    submit.type = 'button';
    submit.className = 'primary-btn';
    submit.id = 'btnCreateChat';

    function syncMode() {
      const isDm = selectedType === 'dm';
      dmField.classList.toggle('hidden', !isDm);
      status.classList.toggle('hidden', !isDm || !status.textContent);
      nameField.classList.toggle('hidden', isDm);
      hint.textContent = isDm
        ? 'Введите ник и нажмите «Найти». Если сервер недоступен, чат создастся черновым — получатель подтвердится позже.'
        : 'Групповые чаты и каналы пока хранятся только на этом устройстве.';
      submit.textContent = isDm ? 'Создать чат' : 'Создать';
    }

    function showStatus(kind, text) {
      status.className = 'nick-status ' + kind;
      status.textContent = text;
    }

    function shake(el) {
      el.classList.remove('invalid');
      void el.offsetWidth;
      el.classList.add('invalid');
      el.focus();
    }

    async function doFind() {
      const username = Directory.normalize(nickInput.value);
      if (!Directory.isValid(username)) {
        showStatus('err', 'Ник: 3–20 символов, a-z, 0-9, _');
        shake(nickInput);
        return;
      }
      findBtn.disabled = true;
      showStatus('loading', 'Проверяем…');
      try {
        const res = await Directory.resolve(username);
        resolved = res;
        if (res.exists === true) {
          showStatus('ok', `@${username} найден${res.displayName ? ' · ' + res.displayName : ''}`);
        } else if (res.exists === false) {
          showStatus('warn', `@${username} не найден — можно создать черновой чат`);
        } else if (res.needsToken) {
          showStatus('warn', 'Нет токена — проверка недоступна, но черновой чат создать можно');
        } else {
          showStatus('warn', 'Сервер не ответил вовремя — чат будет черновым');
        }
      } catch (e) {
        showStatus('warn', 'Не удалось проверить — чат будет черновым');
      } finally {
        findBtn.disabled = false;
      }
    }

    async function trySubmit() {
      if (selectedType !== 'dm') {
        const chat = createChat({ name: nameInput.value, type: selectedType });
        if (!chat) { shake(nameInput); return; }
        Modal.close();
        openChat(chat.id);
        return;
      }

      const username = Directory.normalize(nickInput.value);
      if (!Directory.isValid(username)) {
        showStatus('err', 'Введите корректный ник');
        shake(nickInput);
        return;
      }

      const res = resolved && resolved.username === username
        ? resolved
        : await Directory.resolve(username);

      const verified = res.exists === true;
      let publicKey = res.publicKey || null;
      if (verified && !publicKey) {
        const k = await Directory.requestKey(username).catch(() => null);
        if (k && k.found) publicKey = k.publicKey;
      }

      const chat = createChat({ name: '@' + username, type: 'dm', username, verified, publicKey });
      Modal.close();
      openChat(chat.id);
    }

    findBtn.addEventListener('click', doFind);
    nickInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') doFind(); });
    nickInput.addEventListener('input', () => {
      nickInput.classList.remove('invalid');
      resolved = null;
      status.classList.add('hidden');
    });
    nameInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') trySubmit(); });
    submit.addEventListener('click', trySubmit);

    form.append(pills, dmField, status, nameField, hint, submit);
    Modal.open('Новый чат');
    Modal.showNode(form);
    syncMode();
    nickInput.focus();
  }

  /* ---------- действия ---------- */

  function openChat(id) {
    stopLive();
    state.activeId = id;
    const chat = active();
    if (chat && chat.unread > 0) {
      chat.unread = 0;
      persist();
    }
    renderChatList();
    renderHead();
    renderMessages();
    document.body.dataset.view = 'chat';
    document.body.dataset.screen = 'chat';
    App.syncTabs();
    if (window.innerWidth >= 900) $('draftInput').focus();
  }

  function stopLive() {
    clearInterval(liveTimer);
    clearTimeout(doneTimer);
    state.live = null;
    state.typing = false;
  }

  function send() {
    const input = $('draftInput');
    const text = input.value.trim();
    const chat = active();
    if (!text || !chat || chat.type === 'channel') return;

    const t = now();
    chat.msgs.push({ from: 'me', text, time: t, date: 'Сегодня' });
    chat.preview = text;
    chat.time = t;
    input.value = '';

    persist();
    renderChatList();
    renderMessages();
    /* Здесь же (этап 6) сообщение уйдёт в MINI/outgoing через
       MiniCrypto.buildEnvelope() + Disk.upload(). */
  }

  /* ---------- инициализация ---------- */

  async function init() {
    $('btnSend').addEventListener('click', send);
    $('draftInput').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') send();
    });
    $('searchInput').addEventListener('input', (e) => {
      search = e.target.value;
      renderChatList();
    });
    $('btnNewChat').addEventListener('click', openCreateDialog);
    $('btnNewChatFab').addEventListener('click', openCreateDialog);

    await loadChats(); // грузим ДО первого рендера, чтобы не мигал empty-state

    renderChatList();
    renderHead();
    renderMessages();
    document.body.dataset.screen = 'chats';
  }

  return { init, openChat, createChat, openCreateDialog, state };
})();
