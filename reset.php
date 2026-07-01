<?php
/**
 * reset.php — страница сброса пароля.
 * GET  ?token=XXX  → показывает форму нового пароля
 * POST             → меняет пароль, инвалидирует токен
 */
session_start();
require_once('swad/config.php');
require_once('swad/controllers/user.php');

// Если уже авторизован — на главную
if (isset($_COOKIE['auth_token'])) {
    header('Location: /');
    exit;
}

$db  = new Database();
$pdo = $db->connect();

$token     = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error     = '';
$success   = false;
$tokenValid = false;
$user      = null;

// ── Проверяем токен ───────────────────────────────────────────────────────────
if ($token) {
    $stmt = $pdo->prepare('
        SELECT id, email, reset_token_expires
        FROM users
        WHERE reset_token = ?
          AND reset_token_expires IS NOT NULL
          AND reset_token_expires > ?
        LIMIT 1
    ');
    $stmt->execute([$token, time()]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $tokenValid = (bool)$user;
}

// ── CSRF для этой формы ───────────────────────────────────────────────────────
if (empty($_SESSION['reset_csrf'])) {
    $_SESSION['reset_csrf'] = bin2hex(random_bytes(32));
}

// ── POST: меняем пароль ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {

    // CSRF
    if (
        empty($_POST['reset_csrf']) ||
        !hash_equals($_SESSION['reset_csrf'], $_POST['reset_csrf'])
    ) {
        http_response_code(403);
        die('Forbidden');
    }

    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $error = '❌ Пароль должен содержать минимум 8 символов.';
    } elseif ($password !== $password2) {
        $error = '❌ Пароли не совпадают.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Меняем пароль, инвалидируем токен, заодно подтверждаем email если не был подтверждён
        $pdo->prepare('
            UPDATE users
            SET password             = ?,
                reset_token          = NULL,
                reset_token_expires  = NULL,
                email_verified       = 1
            WHERE id = ?
        ')->execute([$hash, $user['id']]);

        // Инвалидируем CSRF чтобы форму нельзя было отправить повторно
        unset($_SESSION['reset_csrf']);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — Сброс пароля</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:     #0a0a0f;
            --bg3:    #18181f;
            --border: rgba(255,255,255,0.07);
            --accent: #c32178;
            --accent2:#e8279a;
            --text:   #e8e8f0;
            --muted:  #6b6b80;
            --error:  #ff4d6d;
            --ok:     #00e07a;
            --radius: 14px;
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

        .step-label { font-size: 11px; font-weight: 500; letter-spacing: 2px; color: var(--accent); text-transform: uppercase; margin-bottom: 6px; }
        .step-title { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; color: var(--text); margin-bottom: 4px; line-height: 1.2; }
        .step-subtitle { font-size: 13px; color: var(--muted); margin-bottom: 22px; line-height: 1.5; }

        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 500; color: var(--muted); margin-bottom: 6px; }
        .input-wrap { position: relative; }
        .input-wrap .icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 16px; pointer-events: none; }
        input[type="password"] {
            width: 100%; padding: 13px 44px 13px 40px;
            background: var(--bg3); border: 1px solid var(--border);
            border-radius: var(--radius); color: var(--text); font-size: 14px;
            font-family: 'Inter', sans-serif; outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus { border-color: rgba(195,33,120,0.5); box-shadow: 0 0 0 3px rgba(195,33,120,0.08); }
        input::placeholder { color: var(--muted); }
        input.input-error { border-color: rgba(255,77,109,0.6) !important; box-shadow: 0 0 0 3px rgba(255,77,109,0.1) !important; }

        .eye-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; font-size: 16px; padding: 0 4px; }

        .strength-bar  { height: 3px; border-radius: 2px; margin-top: 8px; background: var(--border); overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; transition: width 0.3s, background 0.3s; width: 0%; }
        .strength-text { font-size: 11px; color: var(--muted); margin-top: 4px; }

        .match-hint { font-size: 11px; margin-top: 5px; min-height: 16px; transition: color 0.2s; }

        .btn-primary { width: 100%; padding: 14px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border: none; border-radius: var(--radius); color: #fff; font-size: 15px; font-weight: 600; font-family: 'Rajdhani', sans-serif; letter-spacing: 0.5px; cursor: pointer; transition: all 0.2s; margin-top: 4px; }
        .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(195,33,120,0.3); }
        .btn-primary:disabled { opacity: 0.5; cursor: default; transform: none; filter: none; box-shadow: none; }
        .btn-ghost { width: 100%; padding: 12px; background: transparent; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-size: 14px; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; margin-top: 10px; text-decoration: none; display: block; text-align: center; }
        .btn-ghost:hover { border-color: rgba(195,33,120,0.4); color: var(--text); }

        .alert-error { background: rgba(255,77,109,0.1); border: 1px solid rgba(255,77,109,0.25); border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #ff8fa3; margin-bottom: 16px; }

        @keyframes check-pop { 0%{transform:scale(0);opacity:0;} 60%{transform:scale(1.2);} 100%{transform:scale(1);opacity:1;} }
        .check-icon    { display: block; font-size: 48px; text-align: center; margin: 8px 0 16px; animation: check-pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
        .success-title { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; text-align: center; margin-bottom: 8px; }
        .success-text  { text-align: center; color: var(--muted); font-size: 13px; line-height: 1.6; margin-bottom: 24px; }

        .card-footer { padding: 16px 36px 24px; text-align: center; font-size: 12px; color: var(--muted); border-top: 1px solid var(--border); }
        .card-footer a { color: var(--accent); text-decoration: none; }

        @media(max-width: 480px) {
            .card { width: 100%; min-height: 100dvh; border-radius: 0; }
            .card-body { padding: 28px 24px 24px; }
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

        <?php if ($success): ?>
        <!-- ── Успех ── -->
        <span class="check-icon">🔓</span>
        <div class="success-title">Пароль изменён!</div>
        <p class="success-text">Теперь вы можете войти с новым паролем.</p>
        <a href="/login" class="btn-ghost">Войти →</a>

        <?php elseif (!$token || !$tokenValid): ?>
        <!-- ── Токен невалидный или отсутствует ── -->
        <span class="check-icon">⏰</span>
        <div class="success-title" style="color:#ff8fa3;">Ссылка недействительна</div>
        <p class="success-text">
            Ссылка для сброса пароля устарела или уже была использована.<br>
            Ссылки действительны <strong>1 час</strong> с момента отправки.
        </p>
        <a href="/login" class="btn-ghost" onclick="event.preventDefault(); history.pushState(null,'','/login'); location.href='/login#forgot'">
            Запросить новую ссылку →
        </a>

        <?php else: ?>
        <!-- ── Форма нового пароля ── -->
        <div class="step-label">Восстановление доступа</div>
        <div class="step-title">Новый пароль</div>
        <div class="step-subtitle">
            Для аккаунта <strong style="color:var(--text);"><?= htmlspecialchars($user['email']) ?></strong>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="reset-form" novalidate>
            <input type="hidden" name="reset_csrf" value="<?= htmlspecialchars($_SESSION['reset_csrf']) ?>">
            <input type="hidden" name="token"      value="<?= htmlspecialchars($token) ?>">

            <div class="field">
                <label>Новый пароль</label>
                <div class="input-wrap">
                    <span class="icon">🔒</span>
                    <input type="password" id="new-pwd" name="password"
                           placeholder="Минимум 8 символов" autocomplete="new-password"
                           oninput="checkStrength(this.value); checkMatch();">
                    <button type="button" class="eye-btn" onclick="togglePwd('new-pwd',this)">👁</button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                <div class="strength-text" id="strength-text"></div>
            </div>

            <div class="field">
                <label>Повторите пароль</label>
                <div class="input-wrap">
                    <span class="icon">🔒</span>
                    <input type="password" id="new-pwd2" name="password2"
                           placeholder="Повторите пароль" autocomplete="new-password"
                           oninput="checkMatch();">
                    <button type="button" class="eye-btn" onclick="togglePwd('new-pwd2',this)">👁</button>
                </div>
                <div class="match-hint" id="match-hint"></div>
            </div>

            <button type="button" id="submit-btn" class="btn-primary" onclick="resetSubmit()" disabled>
                Сохранить новый пароль
            </button>
        </form>
        <?php endif; ?>

    </div>
    <div class="card-footer">
        <a href="/login">← Вернуться ко входу</a>
    </div>
</div>

<script>
function checkStrength(v) {
    const fill = document.getElementById('strength-fill');
    const text = document.getElementById('strength-text');
    if (!fill) return;
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

function checkMatch() {
    const p1   = document.getElementById('new-pwd')?.value  || '';
    const p2   = document.getElementById('new-pwd2')?.value || '';
    const hint = document.getElementById('match-hint');
    const btn  = document.getElementById('submit-btn');
    if (!hint || !btn) return;

    const longEnough = p1.length >= 8;
    const matches    = p1 === p2;

    if (!p2.length) {
        hint.textContent = ''; hint.style.color = '';
        btn.disabled = !longEnough;
        return;
    }

    if (matches && longEnough) {
        hint.textContent = '✓ Пароли совпадают'; hint.style.color = '#00e07a';
        btn.disabled = false;
    } else if (matches && !longEnough) {
        hint.textContent = '✓ Совпадают, но слишком короткий'; hint.style.color = '#ffa94d';
        btn.disabled = true;
    } else {
        hint.textContent = '✗ Пароли не совпадают'; hint.style.color = '#ff4d6d';
        btn.disabled = true;
    }
}

function resetSubmit() {
    const p1 = document.getElementById('new-pwd')?.value  || '';
    const p2 = document.getElementById('new-pwd2')?.value || '';
    if (p1.length < 8 || p1 !== p2) return;
    document.getElementById('reset-form').submit();
}

document.getElementById('new-pwd2')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); resetSubmit(); }
});

function togglePwd(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
    btn.textContent = el.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>