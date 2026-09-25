<?php
/**
 * m/views/login.php — свой вход мобильной версии (та же БД, что у /login).
 * Уходить на десктопный /login из PWA нельзя: он живёт по своим правилам
 * редиректов, и на телефоне это превращалось в петлю.
 */
$back = (string)($_GET['back'] ?? '/m/profile');
if (!str_starts_with($back, '/') || str_starts_with($back, '//')) $back = '/m/profile';
if ($uid) { header('Location: ' . $back, true, 302); exit; }

$title    = 'Вход — Dustore';
$hideNav  = true;
$hideHead = true;

/* Telegram без всплывающего окна: полноэкранный редирект на oauth.telegram.org
   с возвратом сюда (#tgAuthResult=…). Виджет с popup в установленном PWA на
   iOS не возвращается в приложение. Бот и домен — те же, что у виджета. */
$tgBot = null;
try {
    require_once __DIR__ . '/../../swad/controllers/jwt.php';     // подтягивает pass.php с токенами
    $tok = ($_SERVER['HTTP_HOST'] ?? '') === 'dustore.ru' ? (defined('BOT_TOKEN') ? BOT_TOKEN : '') : (defined('LOCAL_BOT_TOKEN') ? LOCAL_BOT_TOKEN : '');
    if ($tok && str_contains($tok, ':')) $tgBot = explode(':', $tok, 2)[0];
} catch (Throwable $e) { }
$origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'dustore.ru');
$tgUrl  = $tgBot ? 'https://oauth.telegram.org/auth?' . http_build_query([
    'bot_id' => $tgBot, 'origin' => $origin, 'request_access' => 'write',
    'return_to' => $origin . '/m/login?back=' . rawurlencode($back),
]) : null;

$footExtra = '<script>window.M_BACK = ' . json_encode($back) . ';</script><script src="' . m_asset('/m/js/auth.js') . '" defer></script>';
?>
<section class="auth">
  <a class="ic-btn auth-x" href="<?= h(str_starts_with($back, '/m/') ? '/m/' : $back) ?>" aria-label="Закрыть"><i class="ti ti-x"></i></a>
  <img class="auth-logo" src="/m/icons/logo-appolo.png" alt="Dustore" draggable="false">
  <h1>Вход</h1>
  <p class="muted">Библиотека, чаты и вишлист — в одном аккаунте на всех устройствах</p>

  <button type="button" class="btn-big pk" id="pkBtn" hidden><i class="ti ti-fingerprint"></i><span>Войти по Face ID / отпечатку</span></button>

  <form class="auth-form" id="loginForm" novalidate>
    <label class="fld"><span>Почта</span>
      <input type="email" name="email" id="lgEmail" autocomplete="username webauthn" inputmode="email" autocapitalize="off" spellcheck="false" required>
    </label>
    <label class="fld"><span>Пароль</span>
      <input type="password" name="password" id="lgPass" autocomplete="current-password" required>
      <button type="button" class="eye" id="lgEye" aria-label="Показать пароль"><i class="ti ti-eye"></i></button>
    </label>
    <p class="auth-err" id="lgErr" role="alert" hidden></p>
    <button type="submit" class="btn-big" id="lgBtn">Войти</button>
  </form>

  <?php if ($tgUrl): ?>
    <div class="auth-or"><span>или</span></div>
    <a class="btn-big tg" href="<?= h($tgUrl) ?>" id="tgBtn"><i class="ti ti-brand-telegram"></i>Войти через Telegram</a>
  <?php endif; ?>

  <p class="auth-links">
    <a href="/login#forgot" data-desktop>Забыли пароль?</a>
    <a href="/login#register" data-desktop>Регистрация</a>
  </p>
</section>

<div class="sheet" id="pkOffer" hidden>
  <div class="sheet-card">
    <i class="ti ti-fingerprint sheet-ic"></i>
    <b>Входить без пароля?</b>
    <p>В следующий раз — по Face ID или отпечатку. Ключ хранится на телефоне и не передаётся нам.</p>
    <button type="button" class="btn-big" id="pkYes">Включить</button>
    <button type="button" class="link-btn" id="pkNo">Не сейчас</button>
  </div>
</div>
