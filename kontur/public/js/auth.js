// ============================================================
// AUTH.JS — авторизация через API v3.0
// ============================================================
// Роль больше НЕ выбирается на клиенте. Она приходит с сервера
// после логина. Boot screen теперь — настоящий вход/регистрация.
// ============================================================

const Auth = (() => {

  const ROLE_LABELS = {
    user: 'Участник', moderator: 'Модератор', admin: 'Администратор',
  };

  function getArticleRank(n) { return S.ARTICLE_RANKS.find(r => n >= r.min) || S.ARTICLE_RANKS.at(-1); }
  function getFanRank(n)     { return S.FAN_RANKS.find(r => n >= r.min)     || S.FAN_RANKS.at(-1); }

  function getRankString(u) {
    if (!u) return '';
    const ar = getArticleRank(u.subs_articles || 0);
    const fr = getFanRank(u.subs_fan || 0);
    const parts = [];
    if (ar.name) parts.push(`${ar.icon} ${ar.name}`);
    if (fr.name) parts.push(`${fr.icon} ${fr.name}`);
    return parts.length ? parts.join(' · ') : '[ новый участник ]';
  }

  function roleLabel(r) { return ROLE_LABELS[r] || r; }

  // ── Boot screen ──────────────────────────────────────────
  function hideBootScreen() {
    const boot = document.getElementById('boot-screen');
    if (!boot) return;
    boot.style.animation = 'boot-fade-out 0.4s ease forwards';
    setTimeout(() => { boot.style.display = 'none'; }, 420);
  }
  function showBootScreen() {
    const boot = document.getElementById('boot-screen');
    if (boot) boot.style.display = 'flex';
  }

  // ── Проверка активной сессии при загрузке ────────────────
  async function checkSession() {
    try {
      const { user } = await API.me();
      if (user) {
        S.user = user;
        applyUserUI();
        return true;
      }
    } catch (e) { /* нет сессии — покажем boot screen */ }
    return false;
  }

  // ── Обновить профиль с сервера ───────────────────────────
  async function refresh() {
    try {
      const { user } = await API.me();
      S.user = user;
      applyUserUI();
    } catch (e) { /* ignore */ }
  }

  // ── Boot login ───────────────────────────────────────────
  async function doBootLogin() {
    const name = document.getElementById('boot-name').value.trim();
    const pass = document.getElementById('boot-pass').value;
    if (!name || !pass) { Toast.show('Введите имя и пароль', 'warn'); return; }

    _setBootBusy(true);
    try {
      const { user } = await API.login(name, pass);
      S.user = user;
      hideBootScreen();
      applyUserUI();
      await Articles.load();
      setTimeout(() => Toast.show(
        `Добро пожаловать, <b>${user.username}</b> [${roleLabel(user.role)}]`, 'success', 4000
      ), 300);
    } catch (e) {
      _bootError(e.status === 401 ? 'Неверное имя или пароль' : e.message);
    } finally {
      _setBootBusy(false);
    }
  }

  // ── Boot register ────────────────────────────────────────
  async function doBootRegister() {
    const name = document.getElementById('boot-name').value.trim();
    const pass = document.getElementById('boot-pass').value;
    if (!name || !pass) { Toast.show('Введите имя и пароль', 'warn'); return; }

    _setBootBusy(true);
    try {
      const { user } = await API.register(name, pass);
      S.user = user;
      hideBootScreen();
      applyUserUI();
      await Articles.load();
      setTimeout(() => Toast.show(
        `Регистрация успешна! Добро пожаловать, <b>${user.username}</b>`, 'success', 4000
      ), 300);
    } catch (e) {
      _bootError(e.message);
    } finally {
      _setBootBusy(false);
    }
  }

  // ── Guest ────────────────────────────────────────────────
  function loginAsGuest() {
    S.user = null;
    hideBootScreen();
    applyUserUI();
    Articles.load();
    setTimeout(() => Toast.show('Вход как гость. Просмотр разрешён.', 'info', 3000), 300);
  }

  function _bootError(msg) {
    const el = document.getElementById('boot-error');
    if (el) { el.textContent = msg; el.style.display = 'block'; }
    else Toast.show(msg, 'error');
  }
  function _setBootBusy(busy) {
    document.querySelectorAll('.boot-body button, .boot-body input').forEach(el => el.disabled = busy);
  }

  // ── In-app login dialog ──────────────────────────────────
  function openLogin() {
    document.getElementById('dlg-overlay').style.display = '';
    document.getElementById('dlg-login').classList.add('open');
  }

  async function doLogin() {
    const name = document.getElementById('l-name').value.trim();
    const pass = document.getElementById('l-pass').value;
    if (!name || !pass) { Toast.show('Введите имя и пароль', 'warn'); return; }
    try {
      const { user } = await API.login(name, pass);
      S.user = user;
      Dialogs.closeAll();
      applyUserUI();
      await Articles.load();
      Toast.show(`Вы вошли как <b>${user.username}</b>`, 'success');
    } catch (e) {
      Toast.show(e.status === 401 ? 'Неверное имя или пароль' : e.message, 'error');
    }
  }

  async function doRegister() {
    const name = document.getElementById('l-name').value.trim();
    const pass = document.getElementById('l-pass').value;
    if (!name || !pass) { Toast.show('Введите имя и пароль', 'warn'); return; }
    try {
      const { user } = await API.register(name, pass);
      S.user = user;
      Dialogs.closeAll();
      applyUserUI();
      await Articles.load();
      Toast.show(`Регистрация успешна, <b>${user.username}</b>!`, 'success');
    } catch (e) {
      Toast.show(e.message, 'error');
    }
  }

  async function doLogout() {
    const name = S.user?.username || '';
    try { await API.logout(); } catch (e) { /* ignore */ }
    S.user = null;
    S.myRatings = {};
    applyUserUI();
    await Articles.load();
    Toast.show(name ? `${name} вышел из системы.` : 'Выход выполнен.', 'info');
  }

  // ── Apply UI ─────────────────────────────────────────────
  function applyUserUI() {
    const u = S.user;

    const statusEl = document.getElementById('status-user');
    if (statusEl) {
      statusEl.textContent = !u
        ? 'Гость | Просмотр разрешён | Вход — для отправки материалов'
        : `${u.username} | ${roleLabel(u.role)}${getRankString(u) ? ' | ' + getRankString(u) : ''}`;
    }

    const loginBtn = document.getElementById('sb-login-btn');
    const regBtn   = document.getElementById('sb-reg-btn');
    const rankRow  = document.getElementById('sb-rank-row');

    if (loginBtn) {
      loginBtn.textContent = u ? 'Выйти' : 'Войти';
      loginBtn.onclick = u ? doLogout : openLogin;
    }
    if (regBtn) regBtn.style.display = u ? 'none' : '';

    if (rankRow) {
      rankRow.style.display = u ? 'flex' : 'none';
      if (u) {
        const ar = getArticleRank(u.subs_articles || 0);
        const fr = getFanRank(u.subs_fan || 0);
        const faction = S.FACTIONS.find(f => f.id === u.faction) || S.FACTIONS[0];
        let html = '';
        if (ar.name) html += `<div style="font-size:10px;padding:1px 0;">${ar.icon} ${ar.name} <span style="color:#808080;">(статьи)</span></div>`;
        if (fr.name) html += `<div style="font-size:10px;padding:1px 0;">${fr.icon} ${fr.name} <span style="color:#808080;">(творч.)</span></div>`;
        if (!ar.name && !fr.name) html += `<div style="font-size:10px;color:#808080;">[ новый участник ]</div>`;
        if (u.faction && u.faction !== 'none') {
          html += `<div style="font-size:10px;padding:2px 0;margin-top:2px;border-top:1px dotted #808080;">${faction.icon} <span style="color:#004040;">${faction.name}</span></div>`;
        }
        html += `<div style="font-size:10px;color:#808080;margin-top:2px;">${roleLabel(u.role)}</div>`;
        rankRow.innerHTML = html;
      }
    }

    const submitArea = document.getElementById('submit-area');
    const loginCta   = document.getElementById('login-cta');
    if (submitArea) submitArea.style.display = u ? '' : 'none';
    if (loginCta)   loginCta.style.display   = u ? 'none' : '';

    const tabMod = document.getElementById('tab-mod');
    if (tabMod) {
      const canMod = u && (u.role === 'moderator' || u.role === 'admin');
      tabMod.style.display = canMod ? '' : 'none';
    }

    document.querySelectorAll('.admin-only').forEach(el => {
      el.style.display = (u && u.role === 'admin') ? '' : 'none';
    });

    const loginIco = document.getElementById('login-ico');
    const loginLbl = document.getElementById('login-ico-lbl');
    if (loginIco) loginIco.textContent = u ? '👤' : '🔐';
    if (loginLbl) loginLbl.textContent = u ? u.username.substring(0, 8) : 'Вход';

    UI.updatePills();
  }

  return {
    showBootScreen, hideBootScreen, checkSession, refresh,
    doBootLogin, doBootRegister, loginAsGuest,
    openLogin, doLogin, doRegister, doLogout,
    applyUserUI, getArticleRank, getFanRank, getRankString, roleLabel,
  };
})();

window.Auth = Auth;
