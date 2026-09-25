<?php
/**
 * m/views/chat.php — мессенджер внутри мобильного PWA.
 *
 * Тот же chat.js и та же разметка (chat/_markup.php), что на десктопе: одна
 * кодовая база — фичи (файлы, ответы, обои, статусы) приезжают на телефон
 * автоматически. Отличается только рамка: шапка и нижнее меню приложения,
 * а открытая беседа разворачивается на весь экран поверх них.
 */
if (!$uid) { header('Location: /m/login?back=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/m/chat'), true, 302); exit; }
require_once __DIR__ . '/../../chat/_helpers.php';
require_once __DIR__ . '/../../chat/_vapid.php';

$title     = 'Чаты — Dustore';
$bodyClass = 'p-chat chat-page';
$hasStudio = (bool)get_user_studio_ids($db, $uid);

$auto = [
    'to'           => (int)($_GET['to'] ?? 0),
    'studio'       => (int)($_GET['studio'] ?? 0),
    'conversation' => (int)($_GET['conversation'] ?? 0),
    'system'       => !empty($_GET['system']) ? 1 : 0,
];
$headExtra = '<link rel="stylesheet" href="' . m_asset('/swad/css/chat.css') . '">';
$footExtra = '<script>window.CHAT_CFG = ' . json_encode(['me' => $uid, 'api' => '/chat/api.php', 'auto' => $auto, 'mobile' => true]) . ';'
           . 'window.VAPID_PUBLIC = ' . json_encode(vapid_public_key()) . ';</script>'
           . '<script src="' . m_asset('/pwa/push-client.js') . '"></script>'
           . '<script src="' . m_asset('/chat/chat.js') . '"></script>';

require __DIR__ . '/../../chat/_markup.php';
