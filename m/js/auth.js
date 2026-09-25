/* m/js/auth.js — вход (почта, passkey, Telegram) и управление ключами входа.
 *
 * Passkey = WebAuthn. Сервер присылает случайный challenge, телефон
 * подписывает его закрытым ключом из Secure Enclave / TPM после Face ID или
 * отпечатка, сервер проверяет подпись открытым ключом. Пароля нет вообще —
 * утечь нечему, а фишинговый сайт не получит подпись: ключ привязан к домену.
 */
(() => {
'use strict';
const $ = s => document.querySelector(s);
const API = '/m/api/auth.php';
const enc = buf => btoa(String.fromCharCode(...new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const dec = s => Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((s.length + 3) % 4)), c => c.charCodeAt(0));
const post = (action, data = {}) => fetch(API, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action, ...data }) })
  .then(r => r.json()).catch(() => ({ ok: false, error: 'network' }));
const toast = m => (window.mToast ? window.mToast(m) : alert(m));
const back = () => location.replace(window.M_BACK || '/m/profile');
const store = { get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };

const pkSupported = !!(window.PublicKeyCredential && navigator.credentials);
const deviceName = () => {
  const ua = navigator.userAgent;
  if (/iPhone/.test(ua)) return 'iPhone'; if (/iPad/.test(ua)) return 'iPad'; if (/Android/.test(ua)) return 'Android';
  if (/Mac/.test(ua)) return 'Mac'; if (/Windows/.test(ua)) return 'Windows'; return 'Устройство';
};
const ERR = {
  bad: 'Неверная почта или пароль', empty: 'Заполните почту и пароль', limit: 'Слишком много попыток — подождите 5 минут',
  unverified: 'Почта не подтверждена — проверьте письмо от Dustore', network: 'Нет сети', origin: 'Откройте страницу заново',
  unknown_key: 'Этот ключ не привязан к аккаунту — войдите по почте и добавьте его в профиле',
  expired: 'Время вышло, попробуйте ещё раз', cloned: 'Ключ отклонён из соображений безопасности',
};

/* ── Регистрация ключа (из профиля или предложения после входа) ── */
async function registerPasskey() {
  const o = await post('wa_reg_options');
  if (!o.ok) throw new Error(o.error || 'options');
  const cred = await navigator.credentials.create({ publicKey: {
    challenge: dec(o.challenge),
    rp: o.rp,
    user: { id: dec(o.user.id), name: o.user.name, displayName: o.user.displayName },
    pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],   // ES256, RS256 — их проверяет openssl
    // platform = ключ в самом телефоне (Face ID / отпечаток / Windows Hello), а не «другое устройство по QR».
    // Без этого браузер, у которого нет готового ключа, первым делом предлагает QR и USB-ключ.
    authenticatorSelection: { authenticatorAttachment: 'platform', residentKey: 'required', userVerification: 'required' },
    hints: ['client-device'],
    excludeCredentials: o.exclude.map(id => ({ type: 'public-key', id: dec(id) })),
    attestation: 'none',
    timeout: 120000,
  } });
  const r = cred.response;
  // getPublicKey()/getAuthenticatorData() избавляют сервер от разбора CBOR
  if (typeof r.getPublicKey !== 'function' || !r.getPublicKey()) throw new Error('old_browser');
  const res = await post('wa_register', {
    id: cred.id, clientDataJSON: enc(r.clientDataJSON), authenticatorData: enc(r.getAuthenticatorData()),
    publicKey: enc(r.getPublicKey()), alg: r.getPublicKeyAlgorithm(), transports: (r.getTransports?.() || []).join(','),
    name: deviceName(),
  });
  if (!res.ok) throw new Error(res.error);
  store.set('m_pk', '1');
  return true;
}
window.mPasskeyRegister = registerPasskey;

/* ── Вход по ключу. conditional — ключи в подсказке автозаполнения поля почты ── */
let pkAbort = null;
async function loginPasskey(conditional) {
  pkAbort?.abort(); pkAbort = new AbortController();
  const o = await post('wa_login_options');
  if (!o.ok) return;
  let cred;
  try {
    cred = await navigator.credentials.get({
      publicKey: { challenge: dec(o.challenge), rpId: o.rpId, userVerification: 'preferred', timeout: 120000, allowCredentials: [], hints: ['client-device'] },
      mediation: conditional ? 'conditional' : 'optional',
      signal: pkAbort.signal,
    });
  } catch (e) {
    if (!conditional && e.name !== 'AbortError' && e.name !== 'NotAllowedError') toast('Вход по ключу не удался');
    return;
  }
  if (!cred) return;
  const r = cred.response;
  const res = await post('wa_login', {
    id: cred.id, clientDataJSON: enc(r.clientDataJSON), authenticatorData: enc(r.authenticatorData),
    signature: enc(r.signature), userHandle: r.userHandle ? enc(r.userHandle) : '',
  });
  if (res.ok) { store.set('m_pk', '1'); back(); } else showErr(ERR[res.error] || 'Не удалось войти');
}

function showErr(text) { const e = $('#lgErr'); if (e) { e.textContent = text; e.hidden = !text; } }

/* ════ Страница входа ════ */
if ($('#loginForm')) {
  // возврат из Telegram: #tgAuthResult=<base64 JSON> → тот же серверный обработчик, что у виджета
  const m = location.hash.match(/tgAuthResult=([^&]+)/);
  if (m) {
    try {
      const data = JSON.parse(decodeURIComponent(escape(atob(m[1].replace(/-/g, '+').replace(/_/g, '/')))));
      history.replaceState(null, '', location.pathname + location.search);
      location.replace('/swad/controllers/auth.php?' + new URLSearchParams(data));
      return;
    } catch (e) { showErr('Telegram не подтвердил вход'); }
  }

  if (pkSupported) {
    /* Кнопку показываем, только если на ЭТОМ устройстве уже создан ключ. Иначе
       браузеру нечего предложить из телефона, и он честно открывает «QR-код /
       ключ безопасности» — вход с другого устройства. Ключ создаётся после
       первого входа по паролю (или в профиле). Если метку стёрли — ключ всё
       равно всплывёт в подсказке автозаполнения над полем почты. */
    $('#pkBtn').hidden = store.get('m_pk') !== '1';
    $('#pkBtn').addEventListener('click', () => loginPasskey(false));
    // Conditional UI: сохранённые ключи сами всплывают в подсказке над клавиатурой
    PublicKeyCredential.isConditionalMediationAvailable?.().then(ok => { if (ok) loginPasskey(true); }).catch(() => {});
  }

  $('#lgEye').addEventListener('click', () => {
    const p = $('#lgPass'); p.type = p.type === 'password' ? 'text' : 'password';
    $('#lgEye i').className = 'ti ti-eye' + (p.type === 'text' ? '-off' : '');
  });

  $('#loginForm').addEventListener('submit', async e => {
    e.preventDefault(); showErr('');
    const btn = $('#lgBtn'); btn.disabled = true; btn.classList.add('busy');
    pkAbort?.abort();
    const r = await post('login', { email: $('#lgEmail').value.trim(), password: $('#lgPass').value });
    btn.disabled = false; btn.classList.remove('busy');
    if (!r.ok) { showErr(ERR[r.error] || 'Не удалось войти'); return; }
    // вошли паролем — предложим passkey, если телефон умеет и ещё не предлагали
    const canPk = pkSupported && await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);
    if (canPk && !store.get('m_pk') && !store.get('m_pk_no')) { $('#pkOffer').hidden = false; return; }
    back();
  });

  $('#pkYes')?.addEventListener('click', async () => {
    try { await registerPasskey(); toast('Готово: теперь вход по Face ID / отпечатку'); setTimeout(back, 700); }
    catch (e) {
      if (e.name === 'InvalidStateError') { store.set('m_pk', '1'); back(); return; }   // ключ на этом устройстве уже есть
      toast(e.message === 'old_browser' ? 'Браузер слишком старый для ключей' : 'Не получилось — можно позже в профиле'); setTimeout(back, 1200);
    }
  });
  $('#pkNo')?.addEventListener('click', () => { store.set('m_pk_no', '1'); back(); });
}

/* ════ Профиль: мои ключи входа ════ */
const list = $('#pkList');
if (list) {
  const render = async () => {
    const r = await post('wa_list');
    const keys = r.ok ? r.keys : [];
    list.innerHTML = keys.map(k => `<div class="menu-i pk-row"><i class="ti ti-fingerprint"></i>
      <span><b>${k.name.replace(/[<&"]/g, '')}</b><small>${k.last_used_at ? 'вход ' + k.last_used_at.slice(0, 10) : 'добавлен ' + k.created_at.slice(0, 10)}</small></span>
      <button type="button" class="ic-btn" data-del="${k.id}" aria-label="Удалить ключ"><i class="ti ti-trash"></i></button></div>`).join('');
  };
  const noPk = t => { $('#pkAdd').disabled = true; $('#pkAdd').classList.add('off'); $('#pkHint').textContent = t; };
  if (!pkSupported) noPk('Этот браузер не поддерживает вход по ключу');
  else PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
    .then(ok => { if (!ok) noPk('На этом устройстве нет Face ID / отпечатка / Windows Hello'); }).catch(() => {});
  render();
  $('#pkAdd').addEventListener('click', async () => {
    try { await registerPasskey(); toast('Ключ добавлен'); render(); }
    catch (e) { if (e.name !== 'NotAllowedError') toast(e.message === 'old_browser' ? 'Браузер слишком старый для ключей' : e.message === 'exists' ? 'Этот ключ уже добавлен' : 'Не удалось добавить ключ'); }
  });
  list.addEventListener('click', async e => {
    const b = e.target.closest('[data-del]'); if (!b) return;
    if (!confirm('Удалить ключ? Вход с этого устройства будет по паролю.')) return;
    await post('wa_delete', { id: b.dataset.del }); render();
  });
}
})();
