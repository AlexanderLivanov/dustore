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
        .orb-1 { width: 400px; height: 400px; background: rgba(195,33,120,0.15); top: -100px; left: -100px; }
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
        .logo-text { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; letter-spacing: 1px; }
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
        .dot.done   { background: var(--accent); }
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
        input.input-error { border-color: rgba(255,77,109,0.6) !important; box-shadow: 0 0 0 3px rgba(255,77,109,0.1) !important; }
        .field-error { font-size: 11px; color: var(--error); margin-top: 5px; display: none; }
        .field-error.visible { display: block; }

        .strength-bar  { height: 3px; border-radius: 2px; margin-top: 8px; background: var(--border); overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; transition: width 0.3s, background 0.3s; width: 0%; }
        .strength-text { font-size: 11px; color: var(--muted); margin-top: 4px; }

        .eye-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; font-size: 16px; padding: 0 4px; }

        .btn-primary { width: 100%; padding: 14px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border: none; border-radius: var(--radius); color: #fff; font-size: 15px; font-weight: 600; font-family: 'Rajdhani', sans-serif; letter-spacing: 0.5px; cursor: pointer; transition: all 0.2s; }
        .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(195,33,120,0.3); }
        .btn-primary:active { transform: none; }
        .btn-ghost { width: 100%; padding: 12px; background: transparent; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-size: 14px; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; margin-top: 10px; }
        .btn-ghost:hover { border-color: rgba(195,33,120,0.4); color: var(--text); }
        .btn-back { background: none; border: none; color: var(--muted); font-size: 12px; cursor: pointer; margin-bottom: 16px; padding: 0; display: flex; align-items: center; gap: 4px; font-family: 'Inter', sans-serif; transition: color 0.2s; }
        .btn-back:hover { color: var(--text); }

        /* Forgot password link */
        .forgot-link { display: block; text-align: right; font-size: 12px; color: var(--muted); cursor: pointer; margin-top: -6px; margin-bottom: 14px; transition: color 0.2s; background: none; border: none; font-family: 'Inter', sans-serif; }
        .forgot-link:hover { color: var(--accent2); }

        /* Unverified box */
        .unverified-box { background: rgba(255,170,0,0.07); border: 1px solid rgba(255,170,0,0.2); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        .unverified-box .uv-title { font-size: 13px; font-weight: 600; color: #ffcc60; margin-bottom: 4px; }
        .unverified-box .uv-text  { font-size: 12px; color: var(--muted); line-height: 1.5; margin-bottom: 12px; }
        .btn-resend { width: 100%; padding: 10px; background: transparent; border: 1px solid rgba(255,170,0,0.35); border-radius: var(--radius); color: #ffcc60; font-size: 13px; font-weight: 500; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; }
        .btn-resend:hover { background: rgba(255,170,0,0.08); border-color: rgba(255,170,0,0.6); }
        .btn-resend:disabled { opacity: 0.5; cursor: default; }

        .alert-error   { background: rgba(255,77,109,0.1);  border: 1px solid rgba(255,77,109,0.25);  border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #ff8fa3; margin-bottom: 16px; }
        .alert-success { background: rgba(0,224,122,0.08);  border: 1px solid rgba(0,224,122,0.2);    border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #00e07a; margin-bottom: 16px; }
        .alert-warn    { background: rgba(255,170,0,0.08);  border: 1px solid rgba(255,170,0,0.2);    border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #ffcc60; margin-bottom: 16px; }

        .tg-wrap   { display: flex; justify-content: center; padding: 16px 0; }
        .tg-notice { font-size: 11px; color: var(--muted); text-align: center; margin-top: 8px; line-height: 1.5; }

        .card-footer { padding: 16px 36px 24px; text-align: center; font-size: 12px; color: var(--muted); border-top: 1px solid var(--border); }
        .card-footer a { color: var(--accent); text-decoration: none; }

        @keyframes check-pop { 0%{transform:scale(0);opacity:0;} 60%{transform:scale(1.2);} 100%{transform:scale(1);opacity:1;} }
        .check-icon    { display: block; font-size: 48px; text-align: center; margin: 8px 0 16px; animation: check-pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
        .success-title { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; text-align: center; margin-bottom: 8px; }
        .success-text  { text-align: center; color: var(--muted); font-size: 13px; line-height: 1.6; }

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

            <?php if ($resend_status === 'ok'): ?>
                <div class="alert-success">📨 Письмо отправлено! Проверьте почту и папку «Спам».</div>
            <?php elseif ($resend_status === 'error'): ?>
                <div class="alert-error">❌ Не удалось отправить письмо. Попробуйте позже.</div>
            <?php elseif ($resend_status === 'already_verified'): ?>
                <div class="alert-success">✅ Ваш email уже подтверждён — просто войдите.</div>
            <?php elseif ($resend_status === 'limit'): ?>
                <div class="alert-warn">⏳ Слишком много попыток. Подождите 10 минут.</div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="backUrl" value="<?= htmlspecialchars($_GET['backUrl'] ?? '/') ?>">

                <div class="step active" id="ls-1">
                    <div class="step-label">Шаг 1 из 2</div>
                    <div class="step-title">Добро пожаловать</div>
                    <div class="step-subtitle">Введите ваш email для входа</div>
                    <?php if (!empty($login_error) && $login_error !== 'unverified'): ?>
                        <div class="alert-error"><?= $login_error ?></div>
                    <?php endif; ?>
                    <div class="field">
                        <label>Email</label>
                        <div class="input-wrap">
                            <span class="icon">✉</span>
                            <input type="email" id="l-email" name="email"
                                   placeholder="you@example.com" autocomplete="email">
                        </div>
                        <div class="field-error" id="l-email-error">Введите корректный email</div>
                    </div>
                    <button type="button" class="btn-primary" onclick="loginStep1()">Продолжить →</button>
                    
                </div>

                <div class="step" id="ls-2">
                    <button type="button" class="btn-back" onclick="goStep('ls-1','ls-2')">← Назад</button>
                    <div class="step-label">Шаг 2 из 2</div>
                    <div class="step-title">Введите пароль</div>
                    <div class="step-subtitle" id="l-email-display" style="word-break:break-all;"></div>

                    <?php if ($login_error === 'unverified'): ?>
                    <div class="unverified-box">
                        <div class="uv-title">📩 Почта не подтверждена</div>
                        <div class="uv-text">
                            Мы отправили письмо при регистрации. Проверьте папку «Спам».<br>
                            Если письмо не пришло — отправим ещё раз.
                        </div>
                        <button type="button" class="btn-resend" id="resend-btn" onclick="resendVerification()">
                            Отправить письмо повторно
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="field">
                        <label>Пароль</label>
                        <div class="input-wrap">
                            <span class="icon">🔒</span>
                            <input type="password" id="l-password" name="password"
                                   placeholder="••••••••" autocomplete="current-password">
                            <button type="button" class="eye-btn" onclick="togglePwd('l-password',this)">👁</button>
                        </div>
                        <div class="field-error" id="l-password-error">Введите пароль</div>
                    </div>
                    <button type="button" class="forgot-link" onclick="switchTab('forgot')">Забыли пароль?</button>
                    <button type="button" class="btn-primary" onclick="loginSubmit()">Войти</button>
                </div>
            </form>

            <button class="tab-btn" style="text-decoration: underline" onclick="switchTab('forgot')">Напомнить пароль?</button>

            <!-- Скрытая форма повторной отправки -->
            <form method="POST" id="resend-form" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="resend_verification">
                <input type="hidden" name="email" id="resend-email"
                       value="<?= htmlspecialchars($_SESSION['unverified_email'] ?? '') ?>">
            </form>
        </div>
        
        <!-- ────── REGISTER ────── -->
        <?php if (REGISTRATION_ENABLED): ?>
        <div id="panel-register" class="form-panel">
            <form method="POST" id="reg-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="register">
                <div class="hp-field" aria-hidden="true">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="progress-dots" id="reg-dots">
                    <div class="dot active" style="flex:2" id="d1"></div>
                    <div class="dot" id="d2"></div>
                    <div class="dot" id="d3"></div>
                </div>

                <?php if (!empty($register_error) && $register_error !== '🎉 Регистрация успешна!'): ?>
                    <div class="alert-error"><?= $register_error ?></div>
                <?php endif; ?>

                <div class="step active" id="rs-1">
                    <div class="step-label">Шаг 1 из 3</div>
                    <div class="step-title">Создать аккаунт</div>
                    <div class="step-subtitle">Укажите email и придумайте имя пользователя</div>
                    <div class="field">
                        <label>Email</label>
                        <div class="input-wrap">
                            <span class="icon">✉</span>
                            <input type="email" name="email" id="r-email"
                                   placeholder="you@example.com" autocomplete="email">
                        </div>
                        <div class="field-error" id="r-email-error">Введите корректный email</div>
                    </div>
                    <div class="field">
                        <label>Имя пользователя</label>
                        <div class="input-wrap">
                            <span class="icon">@</span>
                            <input type="text" name="username" id="r-username"
                                   placeholder="CoolPlayer" maxlength="32">
                        </div>
                        <div class="field-error" id="r-username-error">Только латинские буквы, точка и подчёркивание</div>
                    </div>
                    <button type="button" class="btn-primary" onclick="regStep1()">Продолжить →</button>
                </div>

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
                                   oninput="checkStrength(this.value)">
                            <button type="button" class="eye-btn" onclick="togglePwd('r-password',this)">👁</button>
                        </div>
                        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                        <div class="strength-text" id="strength-text"></div>
                        <div class="field-error" id="r-password-error">Пароль должен содержать минимум 8 символов</div>
                    </div>
                    <button type="button" class="btn-primary" onclick="regStep2()">Продолжить →</button>
                </div>

                <div class="step" id="rs-3">
                    <button type="button" class="btn-back" onclick="goStep('rs-2','rs-3');updateDots(2)">← Назад</button>
                    <div class="step-label">Шаг 3 из 3</div>
                    <div class="step-title">О себе</div>
                    <div class="step-subtitle">Необязательно — можно пропустить</div>
                    <div class="field">
                        <label>Имя</label>
                        <div class="input-wrap"><span class="icon">👤</span>
                            <input type="text" name="first_name" placeholder="Неопознанный">
                        </div>
                    </div>
                    <div class="field">
                        <label>Фамилия</label>
                        <div class="input-wrap"><span class="icon">👤</span>
                            <input type="text" name="last_name" placeholder="Игрок">
                        </div>
                    </div>
                    <div class="field">
                        <label>Страна</label>
                        <div class="input-wrap"><span class="icon">🌍</span>
                            <input type="text" name="country" placeholder="Россия">
                        </div>
                    </div>
                    <button type="button" class="btn-primary" onclick="regSubmit()">Создать аккаунт 🚀</button>
                    <button type="button" class="btn-ghost" onclick="regSubmit()">Пропустить и создать →</button>
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
            <div class="tg-notice">
                🔒 Кнопка не появляется? Telegram может быть заблокирован провайдером.<br>
                Попробуйте включить VPN.
            </div>
        </div>

        <!-- ────── FORGOT PASSWORD ────── -->
        <div id="panel-forgot" class="form-panel">

            <?php if ($forgot_status === 'ok'): ?>
                <span class="check-icon">📨</span>
                <div class="success-title">Письмо отправлено</div>
                <p class="success-text" style="margin-bottom:24px;">
                    Если аккаунт с таким email существует — ссылка для сброса пароля уже у вас в почте.<br>
                    Ссылка действительна <strong>1 час</strong>. Проверьте папку «Спам».
                </p>
                <button class="btn-ghost" onclick="switchTab('login')">← Вернуться ко входу</button>

            <?php elseif ($forgot_status === 'limit'): ?>
                <div class="alert-warn">⏳ Слишком много запросов. Подождите 15 минут.</div>
                <button class="btn-ghost" onclick="switchTab('login')">← Вернуться ко входу</button>

            <?php elseif ($forgot_status === 'error'): ?>
                <div class="alert-error">❌ Не удалось отправить письмо. Попробуйте позже.</div>
                <button class="btn-ghost" onclick="switchTab('login')">← Вернуться ко входу</button>

            <?php else: ?>
                <div class="step-label">Восстановление доступа</div>
                <div class="step-title">Забыли пароль?</div>
                <div class="step-subtitle">Введите email — пришлём ссылку для сброса пароля.</div>

                <form method="POST" id="forgot-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="forgot_password">

                    <div class="field">
                        <label>Email</label>
                        <div class="input-wrap">
                            <span class="icon">✉</span>
                            <input type="email" id="f-email" name="email"
                                   placeholder="you@example.com" autocomplete="email">
                        </div>
                        <div class="field-error" id="f-email-error">Введите корректный email</div>
                    </div>

                    <button type="button" class="btn-primary" onclick="forgotSubmit()">
                        Отправить ссылку
                    </button>
                    <button type="button" class="btn-ghost" onclick="switchTab('login')">
                        ← Вернуться ко входу
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- ────── SUCCESS (регистрация) ────── -->
        <?php if (!empty($register_error) && $register_error === '🎉 Регистрация успешна!'): ?>
        <div id="panel-success" class="form-panel" style="text-align:center;padding:16px 0;">
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
function setError(inputId, errorId, show) {
    const input = document.getElementById(inputId);
    const error = document.getElementById(errorId);
    if (!input || !error) return;
    input.classList.toggle('input-error', show);
    error.classList.toggle('visible', show);
}

function isValidEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim());
}

// ── Tabs ──
function switchTab(tab) {
    document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + tab)?.classList.add('active');
    const btns = [...document.querySelectorAll('.tab-btn')];
    const map  = {
        login:    0,
        register: <?= REGISTRATION_ENABLED ? 1 : -1 ?>,
        telegram: <?= REGISTRATION_ENABLED ? 2 : 1 ?>,
        forgot:   <?= REGISTRATION_ENABLED ? 3 : 2 ?>,
    };
    if (btns[map[tab]]) btns[map[tab]].classList.add('active');
}

// ── Steps ──
function goStep(show, hide) {
    document.getElementById(hide).classList.remove('active');
    const el = document.getElementById(show);
    el.classList.add('active');
    el.style.animation = 'none'; el.offsetHeight; el.style.animation = '';
}

// ── Login ──
function loginStep1() {
    const email = document.getElementById('l-email');
    const valid = isValidEmail(email.value);
    setError('l-email', 'l-email-error', !valid);
    if (!valid) { email.focus(); return; }
    const resendEmail = document.getElementById('resend-email');
    if (resendEmail) resendEmail.value = email.value;
    document.getElementById('l-email-display').textContent = email.value;
    goStep('ls-2', 'ls-1');
    setTimeout(() => document.getElementById('l-password').focus(), 100);
}

function loginSubmit() {
    const pwd = document.getElementById('l-password');
    if (!pwd.value.length) { setError('l-password', 'l-password-error', true); pwd.focus(); return; }
    pwd.closest('form').submit();
}

document.getElementById('l-email')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); loginStep1(); } });
document.getElementById('l-password')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); loginSubmit(); } });

// ── Resend ──
function resendVerification() {
    const btn = document.getElementById('resend-btn');
    if (!btn) return;
    btn.disabled = true; btn.textContent = 'Отправляем...';
    const emailEl = document.getElementById('l-email');
    const resendEl = document.getElementById('resend-email');
    if (emailEl?.value) resendEl.value = emailEl.value;
    document.getElementById('resend-form').submit();
}

// ── Forgot password ──
function forgotSubmit() {
    const email = document.getElementById('f-email');
    const valid = isValidEmail(email.value);
    setError('f-email', 'f-email-error', !valid);
    if (!valid) { email.focus(); return; }
    document.getElementById('forgot-form').submit();
}

document.getElementById('f-email')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); forgotSubmit(); }
});

// ── Register ──
function regStep1() {
    const email    = document.getElementById('r-email');
    const username = document.getElementById('r-username');
    const pat      = /^[A-Za-z](?:[A-Za-z._]*[A-Za-z])?$/;
    const emailOk  = isValidEmail(email.value);
    const unameOk  = !username.value || pat.test(username.value);
    setError('r-email',    'r-email-error',    !emailOk);
    setError('r-username', 'r-username-error', !unameOk);
    if (!emailOk)  { email.focus();    return; }
    if (!unameOk)  { username.focus(); return; }
    goStep('rs-2', 'rs-1'); updateDots(2);
    setTimeout(() => document.getElementById('r-password').focus(), 100);
}

function regStep2() {
    const pwd = document.getElementById('r-password');
    if (pwd.value.length < 8) { setError('r-password', 'r-password-error', true); pwd.focus(); return; }
    goStep('rs-3', 'rs-2'); updateDots(3);
}

function regSubmit() { document.getElementById('reg-form').submit(); }

function updateDots(active) {
    for (let i = 1; i <= 3; i++) {
        const d = document.getElementById('d' + i);
        if (!d) continue;
        if      (i < active)  { d.className = 'dot done';   d.style.flex = '1'; }
        else if (i === active) { d.className = 'dot active'; d.style.flex = '2'; }
        else                   { d.className = 'dot';        d.style.flex = '1'; }
    }
}

// ── Password strength ──
function checkStrength(v) {
    const fill = document.getElementById('strength-fill');
    const text = document.getElementById('strength-text');
    let s = 0;
    if (v.length >= 8)          s++;
    if (v.length >= 12)         s++;
    if (/[A-Z]/.test(v))        s++;
    if (/[0-9]/.test(v))        s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const lvl = [
        {pct:'10%', color:'#ff4d6d', label:'Очень слабый'},
        {pct:'25%', color:'#ff4d6d', label:'Слабый'},
        {pct:'50%', color:'#ffa94d', label:'Средний'},
        {pct:'75%', color:'#ffe066', label:'Хороший'},
        {pct:'100%',color:'#00e07a', label:'Надёжный'},
    ][Math.min(s, 4)];
    fill.style.width = lvl.pct; fill.style.background = lvl.color;
    text.textContent = v.length ? lvl.label : ''; text.style.color = lvl.color;
}

function togglePwd(id, btn) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
    btn.textContent = el.type === 'password' ? '👁' : '🙈';
}

// ── Clear errors on input ──
['l-email','l-password','r-email','r-username','r-password','f-email'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
        document.getElementById(id)?.classList.remove('input-error');
        document.getElementById(id + '-error')?.classList.remove('visible');
    });
});

// ── Auto-restore state after server response ──
<?php if ($login_error === 'unverified'): ?>
document.addEventListener('DOMContentLoaded', () => { switchTab('login'); goStep('ls-2','ls-1'); });
<?php endif; ?>
<?php if (!empty($register_error) && $register_error !== '🎉 Регистрация успешна!'): ?>
document.addEventListener('DOMContentLoaded', () => switchTab('register'));
<?php endif; ?>
<?php if (!empty($register_error) && $register_error === '🎉 Регистрация успешна!'): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-success').classList.add('active');
});
<?php endif; ?>
<?php if (in_array($resend_status, ['ok','error','already_verified','limit'])): ?>
document.addEventListener('DOMContentLoaded', () => switchTab('login'));
<?php endif; ?>
<?php if (in_array($forgot_status, ['ok','error','limit'])): ?>
document.addEventListener('DOMContentLoaded', () => switchTab('forgot'));
<?php endif; ?>
</script>
</body>
</html>