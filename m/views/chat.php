<?php
/**
 * m/views/chat.php — мессенджер внутри мобильного PWA.
 *
 * Тот же chat.js и та же разметка (chat/_markup.php), что на десктопе: одна
 * кодовая база — фичи (файлы, ответы, обои, статусы) приезжают на телефон
 * автоматически. Отличается рамка и «кожа»: нижнее меню приложения, свой заголовок
 * вместо шапки сайта, а поверх chat.css лежит m/css/chat-m.css (палитра и формы из
 * дизайна «Dustore Mobile»). Открытая беседа разворачивается на весь экран.
 */
if (!$uid) { header('Location: /m/login?back=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/m/chat'), true, 302); exit; }
require_once __DIR__ . '/../../chat/_helpers.php';
require_once __DIR__ . '/../../chat/_vapid.php';

$title     = 'Чаты — Dustore';
$bodyClass = 'p-chat chat-page';
$hideHead  = true;      // своя шапка внутри мессенджера («Чаты», поиск, фильтры)
$mobileUI  = true;      // для chat/_markup.php: блоки, которые нужны только на телефоне
$hasStudio = (bool)get_user_studio_ids($db, $uid);

$auto = [
    'to'           => (int)($_GET['to'] ?? 0),
    'studio'       => (int)($_GET['studio'] ?? 0),
    'conversation' => (int)($_GET['conversation'] ?? 0),
    'system'       => !empty($_GET['system']) ? 1 : 0,
];
$headExtra = '<link rel="stylesheet" href="' . m_asset('/swad/css/chat.css') . '">'
           . '<link rel="stylesheet" href="' . m_asset('/m/css/chat-m.css') . '">';
$footExtra = '<script>window.CHAT_CFG = ' . json_encode(['me' => $uid, 'api' => '/chat/api.php', 'auto' => $auto, 'mobile' => true]) . ';'
           . 'window.VAPID_PUBLIC = ' . json_encode(vapid_public_key()) . ';</script>'
           . '<script src="' . m_asset('/pwa/push-client.js') . '"></script>'
           . '<script src="' . m_asset('/chat/chat.js') . '"></script>';

require __DIR__ . '/../../chat/_markup.php';
