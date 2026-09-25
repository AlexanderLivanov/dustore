<?php
/**
 * swad/static/elements/profile_edit.php
 *
 * Окно «Изменить профиль»: аватарка, ник, город, страна, ссылки.
 * Открывается любым элементом с data-open-profile-edit (кнопка с карандашом
 * под аватаркой в player.php) или адресом с #edit-profile.
 *
 * Ожидает:
 *   $pe_user       — массив пользователя (username, first_name, last_name,
 *                    profile_picture, city, country, vk, website);
 *   $pe_onboarding — true, если ника ещё нет (страница /me): окно открывается
 *                    сразу, закрыть его без ника нельзя, после сохранения —
 *                    переход в новый профиль.
 *
 * Сохранение: swad/controllers/profile_update.php (поля) и
 *             swad/controllers/upload_avatar.php (аватарка, сразу при выборе).
 * Каркас окна и поля — общие, swad/css/user-dialog.css.
 */
require_once(__DIR__ . '/../../controllers/csrf.php');

$pe_user       = $pe_user ?? [];
$pe_onboarding = !empty($pe_onboarding);
$pe_h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$pe_name = trim(($pe_user['first_name'] ?? '') . ' ' . ($pe_user['last_name'] ?? ''));
?>
<?php if (!defined('US_DIALOG_CSS')): define('US_DIALOG_CSS', true); ?>
    <link rel="stylesheet" href="<?= function_exists('asset_url') ? asset_url('/swad/css/user-dialog.css') : '/swad/css/user-dialog.css' ?>">
<?php endif; ?>
<?php /* На /me без ника окно — не модальное, а обычная карточка страницы (атрибут open):
         модальное поверх пустой страницы Chrome закрывает по Esc без спроса, и человек
         оставался бы ни с чем. */ ?>
<dialog class="us-dialog pe-dialog" id="profileEdit" aria-labelledby="peTitle"<?= $pe_onboarding ? ' data-onboarding open' : '' ?>>
    <form class="us-box" id="peForm" novalidate>
        <header class="us-head">
            <h2 id="peTitle"><?= $pe_onboarding ? 'Выберите ник' : 'Профиль' ?></h2>
            <?php if (!$pe_onboarding): ?>
                <button type="button" class="us-close" data-pe-close aria-label="Закрыть">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true">
                        <path d="M5 5l14 14M19 5L5 19" />
                    </svg>
                </button>
            <?php endif; ?>
        </header>

        <div class="us-body">
            <?php if ($pe_onboarding): ?>
                <p class="us-lead">По нику вас находят и по нему открывается ваш профиль. Его можно будет поменять.</p>
            <?php endif; ?>

            <div class="pe-avatar-row">
                <button type="button" class="pe-avatar" id="peAvatarBtn" aria-label="Сменить аватарку" aria-describedby="peAvatarHint">
                    <img id="peAvatarImg" alt=""
                         src="<?= !empty($pe_user['profile_picture']) ? $pe_h($pe_user['profile_picture']) : '/swad/static/img/logo.svg' ?>">
                    <span class="pe-avatar-cam" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9.4 4h5.2l1.5 2H19a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h2.9l1.5-2zM12 17.5a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zm0-2a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z" />
                        </svg>
                    </span>
                    <span class="pe-avatar-spin" aria-hidden="true"></span>
                </button>
                <input type="file" id="peAvatarFile" accept="image/jpeg,image/png,image/webp" hidden>
                <div class="pe-avatar-text">
                    <div class="pe-name"><?= $pe_h($pe_name !== '' ? $pe_name : 'Игрок Dustore') ?></div>
                    <div class="us-hint" id="peAvatarHint">Нажмите на аватарку, чтобы сменить: JPEG, PNG или WebP. Сохраняется сразу.</div>
                </div>
            </div>

            <p class="us-msg" id="peMsg" role="alert"></p>

            <section class="us-section">
                <h3>Основное</h3>
                <div class="us-field">
                    <label for="peUsername">Ник</label>
                    <input class="us-input" id="peUsername" name="username" required
                           value="<?= $pe_h($pe_user['username'] ?? '') ?>"
                           maxlength="32" autocomplete="username" spellcheck="false" autocapitalize="off"
                           aria-describedby="peUsernameHint">
                    <span class="us-hint" id="peUsernameHint"></span>
                </div>
                <div class="us-grid-2">
                    <div class="us-field">
                        <label for="peCity">Город</label>
                        <input class="us-input" id="peCity" name="city" maxlength="64" autocomplete="address-level2"
                               value="<?= $pe_h($pe_user['city'] ?? '') ?>" placeholder="Например, Казань">
                    </div>
                    <div class="us-field">
                        <label for="peCountry">Страна</label>
                        <input class="us-input" id="peCountry" name="country" maxlength="64" autocomplete="country-name"
                               value="<?= $pe_h($pe_user['country'] ?? '') ?>" placeholder="Россия">
                    </div>
                </div>
            </section>

            <section class="us-section">
                <h3>Ссылки</h3>
                <div class="us-grid-2">
                    <div class="us-field">
                        <label for="peVk">ВКонтакте</label>
                        <input class="us-input" id="peVk" name="vk" maxlength="255" inputmode="url" spellcheck="false"
                               value="<?= $pe_h($pe_user['vk'] ?? '') ?>" placeholder="vk.com/ваш_id" aria-describedby="peVkHint">
                        <span class="us-hint" id="peVkHint"></span>
                    </div>
                    <div class="us-field">
                        <label for="peWebsite">Сайт</label>
                        <input class="us-input" id="peWebsite" name="website" maxlength="255" inputmode="url" spellcheck="false"
                               value="<?= $pe_h($pe_user['website'] ?? '') ?>" placeholder="example.com" aria-describedby="peWebsiteHint">
                        <span class="us-hint" id="peWebsiteHint"></span>
                    </div>
                </div>
            </section>
        </div>

        <footer class="us-foot">
            <span class="us-status" id="peStatus"><?= $pe_onboarding ? '' : 'Изменений нет' ?></span>
            <div class="pe-actions">
                <?php if (!$pe_onboarding): ?>
                    <button type="button" class="us-btn" data-pe-close>Отмена</button>
                <?php endif; ?>
                <button type="submit" class="us-btn us-btn--primary" id="peSave" disabled>Сохранить</button>
            </div>
        </footer>
    </form>
</dialog>

<style>
    /* Страница выбора ника: окно стоит в потоке страницы, а не поверх неё */
    .us-dialog.pe-dialog[data-onboarding] {
        position: static;
        margin: 48px auto;
        max-height: none;
    }

    .pe-avatar-row {
        display: flex;
        align-items: center;
        gap: 16px;
        margin: 6px 0 16px;
    }

    .pe-avatar {
        position: relative;
        flex-shrink: 0;
        width: 84px;
        height: 84px;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: #1b0a26;
        cursor: pointer;
        box-shadow: 0 0 0 2px rgba(255, 255, 255, .12);
        transition: box-shadow .2s;
    }

    .pe-avatar:hover,
    .pe-avatar:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px var(--us-accent-hi), 0 0 22px rgba(var(--brand-rgb, 195, 33, 120), .45);
    }

    .pe-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        display: block;
    }

    /* Камера поверх аватарки: видна при наведении, на тач-экране — всегда */
    .pe-avatar-cam {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(10, 4, 16, .55);
        color: #fff;
        opacity: 0;
        transition: opacity .2s;
    }

    .pe-avatar:hover .pe-avatar-cam,
    .pe-avatar:focus-visible .pe-avatar-cam {
        opacity: 1;
    }

    @media (hover: none) {
        .pe-avatar-cam {
            opacity: 1;
            inset: auto -2px -2px auto;
            width: 30px;
            height: 30px;
            background: var(--us-accent);
        }

        .pe-avatar-cam svg {
            width: 16px;
            height: 16px;
        }
    }

    /* Загрузка: крутящееся кольцо по краю аватарки */
    .pe-avatar-spin {
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: var(--us-accent-hi);
        opacity: 0;
        pointer-events: none;
    }

    .pe-avatar.is-busy .pe-avatar-spin {
        opacity: 1;
        animation: peSpin .8s linear infinite;
    }

    .pe-avatar.is-busy .pe-avatar-cam {
        opacity: 1;
    }

    @keyframes peSpin {
        to { transform: rotate(360deg); }
    }

    .pe-name {
        font-weight: 700;
        font-size: 1.05rem;
        margin-bottom: 4px;
    }

    .pe-actions {
        display: flex;
        gap: 8px;
    }

    #peUsernameHint b {
        color: #f8f9fa;
        font-weight: 600;
    }
</style>

<script>
    (function () {
        const dlg = document.getElementById('profileEdit');
        if (!dlg) return;

        const CSRF = <?= json_encode(csrf_token()) ?>;
        const onboarding = dlg.hasAttribute('data-onboarding');
        const form = document.getElementById('peForm');
        const saveBtn = document.getElementById('peSave');
        const status = document.getElementById('peStatus');
        const msg = document.getElementById('peMsg');
        const fields = ['username', 'city', 'country', 'vk', 'website'].map((name) => form.elements[name]);
        const initial = Object.fromEntries(fields.map((f) => [f.name, f.value]));
        const hints = {
            username: document.getElementById('peUsernameHint'),
            vk: document.getElementById('peVkHint'),
            website: document.getElementById('peWebsiteHint'),
        };

        /* ── Открытие / закрытие ── */
        function open() {
            if (!dlg.open) dlg.showModal();
        }

        document.querySelectorAll('[data-open-profile-edit]').forEach((el) => el.addEventListener('click', (e) => {
            e.preventDefault();
            open();
        }));
        dlg.querySelectorAll('[data-pe-close]').forEach((el) => el.addEventListener('click', () => dlg.close()));
        dlg.addEventListener('click', (e) => { if (e.target === dlg && !onboarding) dlg.close(); });

        // #edit-profile в адресе открывает окно — и при загрузке, и при переходе по якорю
        const byHash = () => { if (!onboarding && location.hash === '#edit-profile') open(); };
        byHash();
        addEventListener('hashchange', byHash);

        /* ── Проверка на лету ── */
        const USER_RE = /^[a-zA-Z0-9_]{3,32}$/;

        function setHint(name, text, isError) {
            const h = hints[name];
            if (h) {
                h.innerHTML = text;
                h.classList.toggle('is-error', !!isError);
            }
            form.elements[name].setAttribute('aria-invalid', isError ? 'true' : 'false');
        }

        function checkUsername() {
            const v = form.elements.username.value.trim();
            if (!USER_RE.test(v)) {
                setHint('username', v.length < 3 ? 'Минимум 3 символа: латиница, цифры и _' : 'Только латиница, цифры и _', v.length > 0);
                return false;
            }
            const safe = v.replace(/[&<>"']/g, '');
            setHint('username', 'Адрес профиля: dustore.ru/player/<b>' + safe + '</b>', false);
            return true;
        }

        function checkUrl(name) {
            const v = form.elements[name].value.trim();
            const ok = v === '' || /^(https?:\/\/)?[^\s/$.?#]+\.[^\s]+$/i.test(v) || (name === 'vk' && /^@?[\w.]{2,64}$/.test(v));
            setHint(name, ok ? '' : (name === 'vk' ? 'Ссылка или id: vk.com/имя' : 'Ссылка вида example.com'), !ok);
            return ok;
        }

        function dirty() {
            return fields.some((f) => f.value.trim() !== initial[f.name].trim());
        }

        function refresh() {
            // Все три проверки — отдельно: подсказки должны обновиться у каждого поля
            const okUser = checkUsername();
            const okVk = checkUrl('vk');
            const okSite = checkUrl('website');
            const valid = okUser && okVk && okSite;
            const changed = dirty() || onboarding;
            saveBtn.disabled = !(valid && changed);
            if (!onboarding) status.textContent = changed ? 'Есть несохранённые изменения' : 'Изменений нет';
        }

        fields.forEach((f) => f.addEventListener('input', () => { msg.textContent = ''; refresh(); }));
        refresh();

        /* ── Сохранение полей ── */
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (saveBtn.disabled) return;
            saveBtn.disabled = true;
            status.textContent = 'Сохраняем…';
            msg.className = 'us-msg';
            msg.textContent = '';

            let data;
            try {
                const res = await fetch('/swad/controllers/profile_update.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF },
                    body: new FormData(form),
                });
                data = await res.json();
            } catch (err) {
                data = { ok: false, error: 'Нет связи с сервером, попробуйте ещё раз' };
            }

            if (!data.ok) {
                msg.className = 'us-msg is-error';
                msg.textContent = data.error || 'Не удалось сохранить';
                Object.entries(data.errors || {}).forEach(([name, text]) => setHint(name, text, true));
                status.textContent = '';
                saveBtn.disabled = false;
                return;
            }

            status.textContent = 'Сохранено ✓';
            status.classList.add('is-saved');
            // Новый ник = новый адрес профиля; иначе просто перерисовываем страницу с новыми полями
            const target = data.profile_url;
            setTimeout(() => {
                if (onboarding || location.pathname !== target) location.href = target;
                else location.replace(location.pathname);
            }, 450);
        });

        /* ── Аватарка: уменьшаем в браузере до 500px и сразу грузим ── */
        const avatarBtn = document.getElementById('peAvatarBtn');
        const avatarFile = document.getElementById('peAvatarFile');
        const avatarImg = document.getElementById('peAvatarImg');
        const avatarHint = document.getElementById('peAvatarHint');
        const AVATAR_HINT = avatarHint.textContent;

        avatarBtn.addEventListener('click', () => avatarFile.click());

        function shrink(file) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const max = 500;
                    const k = Math.min(1, max / Math.max(img.width, img.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * k);
                    canvas.height = Math.round(img.height * k);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    URL.revokeObjectURL(img.src);
                    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/jpeg', 0.85);
                };
                img.onerror = () => reject(new Error('decode'));
                img.src = URL.createObjectURL(file);
            });
        }

        avatarFile.addEventListener('change', async () => {
            const file = avatarFile.files[0];
            avatarFile.value = '';
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                avatarHint.textContent = 'Нужен JPEG, PNG или WebP';
                avatarHint.classList.add('is-error');
                return;
            }

            avatarBtn.classList.add('is-busy');
            avatarHint.classList.remove('is-error');
            avatarHint.textContent = 'Загружаем…';

            try {
                const blob = await shrink(file);
                const fd = new FormData();
                fd.append('avatar', blob, 'avatar.jpg');
                const res = await fetch('/swad/controllers/upload_avatar.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF },
                    body: fd,
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Не удалось загрузить');

                const src = data.url + '?v=' + Date.now();
                // Та же аватарка есть в профиле, в хедере и в меню — обновляем везде
                avatarImg.src = src;
                document.querySelectorAll('.user-avatar, .header-avatar, .user-chip__ava img').forEach((img) => { img.src = src; });
                avatarHint.textContent = 'Аватарка обновлена ✓';
                setTimeout(() => { avatarHint.textContent = AVATAR_HINT; }, 2500);
            } catch (err) {
                avatarHint.textContent = err.message === 'decode' ? 'Не удалось прочитать изображение' : err.message;
                avatarHint.classList.add('is-error');
            } finally {
                avatarBtn.classList.remove('is-busy');
            }
        });
    })();
</script>
