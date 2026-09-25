<?php
declare(strict_types=1);
/**
 * unsubscribe.php — отписка от рассылок (ссылка из писем /devs/notifications).
 *
 *   GET  /unsubscribe?u=ID&t=TOKEN  — страница с кнопкой. Сразу по GET не
 *        отписываем: почтовые сканеры (Outlook, антивирусы) сами открывают
 *        ссылки из писем, и люди «отписывались», ничего не нажав.
 *   POST List-Unsubscribe=One-Click — отписка в один клик из Gmail/Яндекса
 *        (RFC 8058, заголовок List-Unsubscribe-Post). Подлинность — подпись в ссылке.
 *   POST action=resubscribe          — «передумал».
 *
 * Отписка касается только рассылок: подтверждение почты и сброс пароля приходят всё равно.
 */
require_once __DIR__ . '/swad/config.php';
require_once __DIR__ . '/devs/broadcast_lib.php';

$uid = (int)($_GET['u'] ?? $_POST['u'] ?? 0);
$tok = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$valid = $uid > 0 && hash_equals(bc_unsub_token($uid), $tok);

$state = 'ask';
if (!$valid) {
    http_response_code(400);
    $state = 'bad';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = (new Database())->connect();
    $resub = ($_POST['action'] ?? '') === 'resubscribe';
    try {
        $db->prepare("UPDATE users SET email_optout = ? WHERE id = ?")->execute([$resub ? 0 : 1, $uid]);
        $state = $resub ? 'back' : 'done';
    } catch (Throwable $e) {
        error_log('[unsubscribe] ' . $e->getMessage());
        http_response_code(500);
        $state = 'error';
    }
    // one-click от почтового клиента: ему нужен только код ответа
    if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') { http_response_code($state === 'done' ? 200 : 500); exit; }
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$texts = [
    'ask'   => ['Отписаться от рассылок?', 'Больше не будем присылать новости и анонсы Dustore. Письма для входа, подтверждения почты и сброса пароля продолжат приходить.'],
    'done'  => ['Готово, вы отписаны', 'Рассылок больше не будет. Служебные письма — вход, подтверждение почты, сброс пароля — приходить продолжат.'],
    'back'  => ['Вы снова подписаны', 'Будем присылать новости Dustore. Отписаться можно по ссылке в любом письме.'],
    'bad'   => ['Ссылка устарела или повреждена', 'Откройте ссылку «Отписаться» из последнего письма ещё раз.'],
    'error' => ['Не получилось', 'Попробуйте ещё раз через минуту.'],
];
[$title, $text] = $texts[$state];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($title) ?> — Dustore</title>
<style>
  :root { color-scheme: dark; }
  body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 24px; box-sizing: border-box;
         background: radial-gradient(90% 60% at 50% 0%, rgba(195, 33, 120, .25), transparent 70%), #0d0118;
         color: #f6ecf9; font: 16px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .card { width: min(440px, 100%); padding: 32px 28px; border-radius: 22px; text-align: center;
          background: rgba(255, 255, 255, .05); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .09), 0 30px 60px -30px #000; }
  img { width: 72px; height: auto; margin-bottom: 14px; }
  h1 { margin: 0 0 10px; font-size: 22px; }
  p { margin: 0 0 22px; color: rgba(246, 236, 249, .68); font-size: 15px; }
  button, a.btn { display: inline-block; padding: 12px 22px; border: 0; border-radius: 14px; font: 700 15px/1 inherit; cursor: pointer; text-decoration: none; }
  .primary { background: linear-gradient(135deg, #e6379a, #c32178 45%, #74155d); color: #fff; }
  .ghost { background: transparent; color: #ff9fd0; margin-top: 8px; }
</style>
</head>
<body>
<main class="card">
  <img src="/swad/static/img/LogoV3-Appolo_mini.png" alt="Dustore">
  <h1><?= $h($title) ?></h1>
  <p><?= $h($text) ?></p>
  <?php if ($state === 'ask'): ?>
    <form method="post"><input type="hidden" name="u" value="<?= $uid ?>"><input type="hidden" name="t" value="<?= $h($tok) ?>">
      <button class="primary" type="submit">Отписаться</button></form>
    <a class="btn ghost" href="/">Остаться и перейти на сайт</a>
  <?php elseif ($state === 'done'): ?>
    <form method="post"><input type="hidden" name="u" value="<?= $uid ?>"><input type="hidden" name="t" value="<?= $h($tok) ?>"><input type="hidden" name="action" value="resubscribe">
      <button class="ghost" type="submit">Передумал — подписаться снова</button></form>
  <?php else: ?>
    <a class="btn primary" href="/">На Dustore</a>
  <?php endif; ?>
</main>
</body>
</html>
