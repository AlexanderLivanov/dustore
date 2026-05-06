<?php
session_start();
require_once('swad/config.php');
require_once('swad/controllers/user.php');

$curr_user = new User;

if (isset($_COOKIE['auth_token'])) {
    die(header('Location: ' . ($_GET['backUrl'] ?? '/')));
}

if ($_SERVER['HTTP_HOST'] == 'dustore.ru') {
    define('BOT_USERNAME', 'dustore_auth_bot');
} else {
    define('BOT_USERNAME', 'dustore_auth_local_bot');
}

require_once('swad/controllers/email_auth.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — Вход</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:      #0a0a0f;
            --bg2:     #111118;
            --bg3:     #18181f;
            --border:  rgba(255,255,255,0.07);
            --accent:  #c32178;
            --accent2: #e8279a;
            --text:    #e8e8f0;
            --muted:   #6b6b80;
            --success: #00e07a;
            --error:   #ff4d6d;
            --radius:  14px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'Inter', sans-serif; min-height: 100vh;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .bg-canvas {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(195,33,120,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 90%, rgba(100,20,80,0.10) 0%, transparent 60%),
                var(--bg);
        }
        .bg-grid {
            position: fixed; inset: 0; z-index: 0;
            background-image: linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
        }
        .orb { position: fixed; border-radius: 50%; filter: blur(80px); z-index: 0; pointer-events: none; animation: orb-drift 12s ease-in-out infinite alternate; }
        .orb-1 { width: 400px; height: 400px; background: rgba(195,33,120,0.15); top: -100px; left: -100px; animation-delay: 0s; }
        .orb-2 { width: 300px; height: 300px; background: rgba(100,0,60,0.1); bottom: -50px; right: -50px; animation-delay: -6s; }
        @keyframes orb-drift { from { transform: translate(0,0) scale(1); } to { transform: translate(30px,20px) scale(1.1); } }

        .card {
            position: relative; z-index: 1; width: 420px;
            background: rgba(17,17,24,0.85); border: 1px solid var(--border);
            border-radius: 24px; backdrop-filter: blur(24px);
            box-shadow: 0 0 0 1px rgba(195,33,120,0.08), 0 40px 80px rgba(0,0,0,0.6);
            overflow: hidden;
            animation: card-in 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes card-in { from { opacity: 0; transform: translateY(24px) scale(0.97); } to { opacity: 1; transform: none; } }

        .card-top-line { height: 2px; background: linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent); }
        .card-body { padding: 36px 36px 32px; }

        .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
        .logo-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .logo-text { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; letter-spacing: 1px; color: var(--text); }
        .logo-text span { color: var(--accent); }

        .tabs { display: flex; gap: 4px; background: var(--bg3); border-radius: 10px; padding: 4px; margin-bottom: 28px; }
        .tab-btn { flex: 1; padding: 8px 0; background: transparent; border: none; border-radius: 7px; color: var(--muted); font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; }
        .tab-btn.active { background: rgba(195,33,120,0.18); color: var(--accent2); }

        .form-panel { display: none; }
        .form-panel.active { display: block; }

        .step { display: none; animation: step-in 0.3s cubic-bezier(0.22,1,0.36,1) both; }
        .step.active { display: block; }
        @keyframes step-in { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: none; } }

        .step-label { font-size: 11px; font-weight: 500; letter-spacing: 2px; color: var(--accent); text-transform: uppercase; margin-bottom: 6px; }
        .step-title { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; color: var(--text); margin-bottom: 4px; line-height: 1.2; }
        .step-subtitle { font-size: 13px; color: var(--muted); margin-bottom: 22px; line-height: 1.5; }

        .progress-dots { display: flex; gap: 6px; margin-bottom: 26px; }
        .dot { height: 3px; border-radius: 2px; background: var(--border); transition: all 0.3s; }
        .dot.done  { background: var(--accent); }
        .dot.active { background: var(--accent2); flex: 2; }

        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 500; color: var(--muted); margin-bottom: 6px; letter-spacing: 0.5px; }
        .input-wrap { position: relative; }
        .input-wrap .icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 16px; pointer-events: none; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 13px 14px 13px 40px;
            background: var(--bg3); border: 1px solid var(--border);
            border-radius: var(--radius); color: var(--text); font-size: 14px;
            font-family: 'Inter', sans-serif; outline: none;
            transition: border-color 0.2s, box-shadow 0.2s; -webkit-appearance: none;
        }
        input:focus { border-color: rgba(195,33,120,0.5); box-shadow: 0 0 0 3px rgba(195,33,120,0.08); }
        input::placeholder { color: var(--muted); }

        .strength-bar { height: 3px; border-radius: 2px; margin-top: 8px; background: var(--border); overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; transition: width 0.3s, background 0.3s; width: 0%; }
        .strength-text { font-size: 11px; color: var(--muted); margin-top: 4px; }

        .eye-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; font-size: 16px; padding: 0 4px; }

        .btn-primary { width: 100%; padding: 14px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border: none; border-radius: var(--radius); color: #fff; font-size: 15px; font-weight: 600; font-family: 'Rajdhani', sans-serif; letter-spacing: 0.5px; cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden; }
        .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(195,33,120,0.3); }
        .btn-primary:active { transform: none; }
        .btn-ghost { width: 100%; padding: 12px; background: transparent; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-size: 14px; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; margin-top: 10px; }
        .btn-ghost:hover { border-color: rgba(195,33,120,0.4); color: var(--text); }
        .btn-back { background: none; border: none; color: var(--muted); font-size: 12px; cursor: pointer; margin-bottom: 16px; padding: 0; display: flex; align-items: center; gap: 4px; font-family: 'Inter', sans-serif; transition: color 0.2s; }
        .btn-back:hover { color: var(--text); }

        .alert-error { background: rgba(255,77,109,0.1); border: 1px solid rgba(255,77,109,0.25); border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #ff8fa3; margin-bottom: 16px; }

        .tg-wrap { display: flex; justify-content: center; padding: 16px 0; }

        .card-footer { padding: 16px 36px 24px; text-align: center; font-size: 12px; color: var(--muted); border-top: 1px solid var(--border); }
        .card-footer a { color: var(--accent); text-decoration: none; }
        .card-footer a:hover { text-decoration: underline; }

        @keyframes check-pop { 0%{transform:scale(0);opacity:0;} 60%{transform:scale(1.2);} 100%{transform:scale(1);opacity:1;} }
        .check-icon { display: block; font-size: 48px; text-align: center; margin: 8px 0 16px; animation: check-pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
        .success-title { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; text-align: center; margin-bottom: 8px; }
        .success-text { text-align: center; color: var(--muted); font-size: 13px; line-height: 1.6; }

        .hp-field { position: absolute; left: -9999px; opacity: 0; }

        @media(max-width: 480px) {
            .card { width: 100%; min-height: 100dvh; border-radius: 0; }
            .card-body { padding: 28px 24px 24px; }
            .card-footer { padding: 14px 24px 20px; }
        }
    </style>
</head>
<body>

<div class="bg-canvas"></div>
<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="card">
    <div class="card-top-line"></div>
    <div class="card-body">

        <div class="logo">
            <div class="logo-icon">🎮</div>
            <div class="logo-text">Du<span>store</span></div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Вход</button>
            <?php if (REGISTRATION_ENABLED): ?>
            <button class="tab-btn" onclick="switchTab('register')">Регистрация</button>
            <?php endif; ?>
            <button class="tab-btn" onclick="switchTab('telegram')">Telegram</button>
        </div>

        <!-- ────── LOGIN ────── -->
        <div id="panel-login" class="form-panel active">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="backUrl" value="<?= htmlspecialchars($_GET['backUrl'] ?? '/') ?>">

                <div class="step active" id="ls-1">
                    <div class="step-label">Шаг 1 из 2</div>
                    <div class="step-title">Добро пожаловать</div>
                    <div class="step-subtitle">Введите ваш email для входа</div>
                    <?php if (!empty($login_error)): ?>
                        <div class="alert-error"><?= $login_error ?></div>
                    <?php endif; ?>
                    <div class="field">
                        <label>Email</label>
                        <div class="input-wrap">
                            <span class="icon">✉</span>
                            <input type="email" id="l-email" name="email" placeholder="you@example.com" autocomplete="email" required>
                        </div>
                    </div>
                    <button type="button" class="btn-primary" onclick="loginStep1()">Продолжить →</button>
                </div>

                <div class="step" id="ls-2">
                    <button type="button" class="btn-back" onclick="goStep('ls-1','ls-2')">← Назад</button>
                    <div class="step-label">Шаг 2 из 2</div>
                    <div class="step-title">Введите пароль</div>
                    <div class="step-subtitle" id="l-email-display" style="word-break:break-all;"></div>
                    <div class="field">
                        <label>Пароль</label>
                        <div class="input-wrap">
                            <span class="icon">🔒</span>
                            <input type="password" id="l-password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                            <button type="button" class="eye-btn" onclick="togglePwd('l-password',this)">👁</button>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Войти</button>
                </div>
            </form>
        </div>

        <!-- ────── REGISTER ────── -->
        <?php if (REGISTRATION_ENABLED): ?>
        <div id="panel-register" class="form-panel">
            <form method="POST" id="reg-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="register">
                <div class="hp-field" aria-hidden="true">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <!-- Прогресс: 3 точки -->
                <div class="progress-dots" id="reg-dots">
                    <div class="dot active" style="flex:2" id="d1"></div>
                    <div class="dot" id="d2"></div>
                    <div class="dot" id="d3"></div>
                </div>

                <?php if (!empty($register_error) && $register_error !== '🎉 Регистрация успешна!'): ?>
                    <div class="alert-error"><?= $register_error ?></div>
                <?php endif; ?>

                <!-- Шаг 1: email + username -->
                <div class="step active" id="rs-1">
                    <div class="step-label">Шаг 1 из 3</div>
                    <div class="step-title">Создать аккаунт</div>
                    <div class="step-subtitle">Укажите email и придумайте имя пользователя</div>
                    <div class="field">
                        <label>Email</label>
                        <div class="input-wrap">
                            <span class="icon">✉</span>
                            <input type="email" name="email" id="r-email" placeholder="you@example.com" autocomplete="email" required>
                        </div>
                    </div>
                    <div class="field">
                        <label>Имя пользователя</label>
                        <div class="input-wrap">
                            <span class="icon">@</span>
                            <input type="text" name="username" id="r-username"
                                   placeholder="CoolPlayer"
                                   pattern="[A-Za-z](?:[A-Za-z._]*[A-Za-z])?"
                                   title="Только латинские буквы, точка и подчёркивание"
                                   maxlength="32">
                        </div>
                    </div>
                    <button type="button" class="btn-primary" onclick="regStep1()">Продолжить →</button>
                </div>

                <!-- Шаг 2: пароль -->
                <div class="step" id="rs-2">
                    <button type="button" class="btn-back" onclick="goStep('rs-1','rs-2');updateDots(1)">← Назад</button>
                    <div class="step-label">Шаг 2 из 3</div>
                    <div class="step-title">Придумайте пароль</div>
                    <div class="step-subtitle">Минимум 8 символов. Мы не храним пароли в открытом виде.</div>
                    <div class="field">
                        <label>Пароль</label>
                        <div class="input-wrap">
                            <span class="icon">🔒</span>
                            <input type="password" name="password" id="r-password"
                                   placeholder="••••••••" autocomplete="new-password"
                                   oninput="checkStrength(this.value)" required>
                            <button type="button" class="eye-btn" onclick="togglePwd('r-password',this)">👁</button>
                        </div>
                        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                        <div class="strength-text" id="strength-text"></div>
                    </div>
                    <button type="button" class="btn-primary" onclick="regStep2()">Продолжить →</button>
                </div>

                <!-- Шаг 3: о себе (необязательно) -->
                <div class="step" id="rs-3">
                    <button type="button" class="btn-back" onclick="goStep('rs-2','rs-3');updateDots(2)">← Назад</button>
                    <div class="step-label">Шаг 3 из 3</div>
                    <div class="step-title">О себе</div>
                    <div class="step-subtitle">Необязательно — можно пропустить</div>
                    <div class="field">
                        <label>Имя</label>
                        <div class="input-wrap">
                            <span class="icon">👤</span>
                            <input type="text" name="first_name" placeholder="Неопознанный">
                        </div>
                    </div>
                    <div class="field">
                        <label>Фамилия</label>
                        <div class="input-wrap">
                            <span class="icon">👤</span>
                            <input type="text" name="last_name" placeholder="Игрок">
                        </div>
                    </div>
                    <div class="field">
                        <label>Страна</label>
                        <div class="input-wrap">
                            <span class="icon">🌍</span>
                            <input type="text" name="country" placeholder="Россия">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Создать аккаунт 🚀</button>
                    <button type="submit" class="btn-ghost">Пропустить и создать →</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ────── TELEGRAM ────── -->
        <div id="panel-telegram" class="form-panel">
            <div class="step-title" style="margin-bottom:6px;">Войти через Telegram</div>
            <div class="step-subtitle">Используйте аккаунт Telegram для входа в экосистему Dustore</div>
            <div class="tg-wrap">
                <script async src="https://telegram.org/js/telegram-widget.js"
                    data-telegram-login="<?= BOT_USERNAME ?>"
                    data-size="large"
                    data-auth-url="swad/controllers/auth.php"></script>
            </div>
        </div>

        <!-- ────── SUCCESS ────── -->
        <?php if (!empty($register_error) && $register_error === '🎉 Регистрация успешна!'): ?>
        <div id="panel-success" class="form-panel active" style="text-align:center;padding:16px 0;">
            <span class="check-icon">🎉</span>
            <div class="success-title">Аккаунт создан!</div>
            <p class="success-text">Письмо с подтверждением отправлено на вашу почту.<br>Проверьте папку «Спам», если письмо не пришло.</p>
            <button class="btn-primary" style="margin-top:24px;" onclick="switchTab('login')">Войти</button>
        </div>
        <?php endif; ?>

    </div>
    <div class="card-footer">
        Продолжая, вы соглашаетесь с <a href="/privacy">политикой обработки данных</a>
    </div>
</div>

<script>
// ── Tab switching ──
function switchTab(tab) {
    document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const panel = document.getElementById('panel-' + tab);
    if (panel) panel.classList.add('active');
    const btns = [...document.querySelectorAll('.tab-btn')];
    const map = {login: 0, register: 1, telegram: <?= REGISTRATION_ENABLED ? 2 : 1 ?>};
    if (btns[map[tab]]) btns[map[tab]].classList.add('active');
}

// ── Step navigation ──
function goStep(show, hide) {
    document.getElementById(hide).classList.remove('active');
    document.getElementById(show).classList.add('active');
    const el = document.getElementById(show);
    el.style.animation = 'none'; el.offsetHeight; el.style.animation = '';
}

// ── Login steps ──
function loginStep1() {
    const email = document.getElementById('l-email');
    if (!email.reportValidity()) return;
    document.getElementById('l-email-display').textContent = email.value;
    goStep('ls-2', 'ls-1');
    setTimeout(() => document.getElementById('l-password').focus(), 100);
}
document.getElementById('l-email')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); loginStep1(); }
});

// ── Register steps (3 шага, без инвайт-кода) ──
function regStep1() {
    const email = document.getElementById('r-email');
    if (!email.reportValidity()) return;
    goStep('rs-2', 'rs-1');
    updateDots(2);
    setTimeout(() => document.getElementById('r-password').focus(), 100);
}

function regStep2() {
    const pwd = document.getElementById('r-password');
    if (pwd.value.length < 8) { pwd.focus(); return; }
    goStep('rs-3', 'rs-2');
    updateDots(3);
}

function updateDots(active) {
    for (let i = 1; i <= 3; i++) {
        const d = document.getElementById('d' + i);
        if (!d) continue;
        if (i < active)        { d.className = 'dot done';   d.style.flex = '1'; }
        else if (i === active) { d.className = 'dot active'; d.style.flex = '2'; }
        else                   { d.className = 'dot';        d.style.flex = '1'; }
    }
}

// ── Password strength ──
function checkStrength(v) {
    const fill = document.getElementById('strength-fill');
    const text = document.getElementById('strength-text');
    let score = 0;
    if (v.length >= 8)          score++;
    if (v.length >= 12)         score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [
        { pct: '10%',  color: '#ff4d6d', label: 'Очень слабый' },
        { pct: '25%',  color: '#ff4d6d', label: 'Слабый' },
        { pct: '50%',  color: '#ffa94d', label: 'Средний' },
        { pct: '75%',  color: '#ffe066', label: 'Хороший' },
        { pct: '100%', color: '#00e07a', label: 'Надёжный' },
    ];
    const l = levels[Math.min(score, 4)];
    fill.style.width = l.pct; fill.style.background = l.color;
    text.textContent = v.length ? l.label : '';
    text.style.color = l.color;
}

// ── Toggle password visibility ──
function togglePwd(id, btn) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
    btn.textContent = el.type === 'password' ? '👁' : '🙈';
}

// ── Restore register panel after server error ──
<?php if (!empty($register_error) && $register_error !== '🎉 Регистрация успешна!'): ?>
document.addEventListener('DOMContentLoaded', () => switchTab('register'));
<?php endif; ?>

<?php if (!empty($register_error) && $register_error === '🎉 Регистрация успешна!'): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-success').classList.add('active');
});
<?php endif; ?>
</script>
</body>
</html>