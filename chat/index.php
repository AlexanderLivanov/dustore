<?php
declare(strict_types=1);
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// телефон → чат внутри мобильного приложения /m/chat (если человек сам не выбрал полную версию)
require_once __DIR__ . '/../swad/controllers/mobile_redirect.php';
mobile_redirect_if_needed();

if (empty($_SESSION['USERDATA'])) { header('Location: /login'); exit; }
$me   = $_SESSION['USERDATA'];
$myId = (int)($me['id'] ?? 0);

$studioIds  = get_user_studio_ids($db, $myId);
$hasStudio  = !empty($studioIds);
$openTo     = (int)($_GET['to'] ?? 0);
$openStudio = (int)($_GET['studio'] ?? 0);
$openConv   = (int)($_GET['conversation'] ?? 0);   // из пуш-уведомления
$openSystem = !empty($_GET['system']) ? 1 : 0;        // «Уведомления» — из пуша системного уведомления

require_once __DIR__ . '/_vapid.php';
$VAPID_PUBLIC = vapid_public_key();

require __DIR__ . '/../swad/static/elements/header.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" content="#0d0118">
<title>Чаты · Dustore</title>
<link rel="stylesheet" href="/swad/css/chat.css?v=<?= (int)@filemtime(__DIR__ . '/../swad/css/chat.css') ?>">
</head>
<body class="chat-page">

<?php require __DIR__ . '/_markup.php'; ?>

<script>
window.CHAT_CFG = {
  me: <?= (int)$myId ?>,
  api: '/chat/api.php',
  auto: { to: <?= $openTo ?>, studio: <?= $openStudio ?>, conversation: <?= $openConv ?>, system: <?= $openSystem ?> },
};
window.VAPID_PUBLIC = <?= json_encode($VAPID_PUBLIC) ?>;
</script>
<script src="/pwa/push-client.js?v=<?= (int)@filemtime(__DIR__ . '/../pwa/push-client.js') ?>"></script>
<script src="/chat/chat.js?v=<?= (int)@filemtime(__DIR__ . '/chat.js') ?>"></script>
</body>
</html>
