<?php
/**
 * assetstore/admin.php
 * ---------------------------------------------------------------------------
 * Раньше это был байт-в-байт дубликат my_assets.php (видимо, скопировали
 * файл как заготовку под будущую админку и забыли). Реальная модерация
 * давно живёт в manage.php — эта страница осталась только как мёртвая
 * ссылка из edit_asset.php ("🛡️ В админку", ?tab=moderation).
 *
 * Вместо повторного дублирования markup — тонкий редирект на настоящие
 * страницы, чтобы старые ссылки и закладки не ловили сюрпризов.
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

$tab = $_GET['tab'] ?? 'library';
header('Location: ' . ($tab === 'moderation' ? '/assetstore/manage.php' : '/assetstore/my_assets.php'));
exit;
