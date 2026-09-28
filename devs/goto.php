<?php
/**
 * devs/goto.php — переход из публичных страниц (игра, студия) в консоль на конкретную настройку.
 *
 *   /devs/goto?to=edit&game=12&a=description     → /devs/edit?id=12#description
 *   /devs/goto?to=coop&game=12&a=hunt            → /devs/coop?game=12#hunt
 *   /devs/goto?to=monetization&studio=3&a=room   → /devs/monetization#room
 *
 * Консоль работает от студии, выбранной в сессии ($_SESSION['studio_id']). Со страницы игры человек
 * приходит «с улицы» — студия в сессии может быть другой или пустой. Здесь мы проверяем, что он
 * владелец/сотрудник студии этой игры (или админ платформы), выбираем её и уводим на нужную страницу.
 * Открытые редиректы исключены: цель — только из белого списка, якорь — только [\w-].
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/fx/core.php';

$uid = Fx::uid();
if ($uid <= 0) {
    header('Location: /login?backUrl=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/devs/'));
    exit;
}

$pdo    = (new Database())->connect();
$gameId = (int)($_GET['game'] ?? 0);
$studio = (int)($_GET['studio'] ?? 0);

if ($gameId > 0) {
    $st = $pdo->prepare("SELECT developer FROM games WHERE id = ?");
    $st->execute([$gameId]);
    $dev = (int)$st->fetchColumn();
    if ($dev <= 0) { header('Location: /devs/projects'); exit; }
    $studio = $dev;                       // студия берётся из игры, а не из параметра
}

Fx::use($pdo);
if ($studio <= 0 || (!FxAuth::isStaff($studio, $uid) && !Fx::isAdmin($uid))) {
    header('Location: /devs/select?err=forbidden');
    exit;
}

if ((int)($_SESSION['studio_id'] ?? 0) !== $studio) {
    $_SESSION['studio_id'] = $studio;
    unset($_SESSION['STUDIODATA']);
}

$pages = [
    'edit'         => $gameId > 0 ? '/devs/edit?id=' . $gameId : '/devs/projects',
    'coop'         => '/devs/coop' . ($gameId > 0 ? '?game=' . $gameId : ''),
    'monetization' => '/devs/monetization',
    'mystudio'     => '/devs/mystudio',
    'staff'        => '/devs/staff',
    'analytics'    => '/devs/analytics',
    'projects'     => '/devs/projects',
];
$to = $pages[(string)($_GET['to'] ?? '')] ?? '/devs/';

$anchor = (string)($_GET['a'] ?? '');
if ($anchor !== '' && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $anchor)) $to .= '#' . $anchor;

header('Location: ' . $to);
exit;
