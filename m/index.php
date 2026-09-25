<?php
declare(strict_types=1);
/**
 * m/index.php — роутер мобильного PWA.
 *
 *   /m/               главная          /m/game/123     игра
 *   /m/catalog        каталог          /m/library      библиотека (+ ?tab=wishlist)
 *   /m/search         поиск            /m/profile      профиль
 *   /m/chat           чаты             /m/dev/<tiker> | /m/developer/<id>  студия
 *
 * Вьюха рендерится ДО оболочки (в буфер): так она успевает задать заголовок
 * страницы, класс body и признаки вроде «прятать нижнее меню». В старой
 * версии оболочка печаталась первой, и <title> на странице игры всегда был
 * «Игра — Dustore».
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/lib.php';
m_restore_session();

$path  = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/m/', PHP_URL_PATH), '/');
$parts = explode('/', $path);
array_shift($parts);                              // 'm'
$page  = ($parts[0] ?? '') ?: 'home';
$param = $parts[1] ?? null;
if ($page === 'dev') $page = 'developer';

$routes = ['home', 'catalog', 'game', 'library', 'profile', 'search', 'developer', 'chat', 'login'];
if (!in_array($page, $routes, true)) { header('Location: /m/', true, 302); exit; }

$db = (new Database())->connect();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$user = $_SESSION['USERDATA'] ?? null;
$uid  = (int)($user['id'] ?? 0);

/* что вьюха может переопределить */
$title     = 'Dustore';
$bodyClass = 'p-' . $page;
$hideNav   = false;       // страница игры: вместо меню — панель с кнопкой
$hideHead  = false;
$headExtra = '';          // доп. <link>/<meta> в <head>
$footExtra = '';          // доп. скрипты перед </body>

ob_start();
require __DIR__ . "/views/{$page}.php";
$content = ob_get_clean();

require __DIR__ . '/layout/shell.php';
