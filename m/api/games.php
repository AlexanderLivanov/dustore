<?php
declare(strict_types=1);
/**
 * m/api/games.php — следующая страница каталога.
 * Отдаёт ГОТОВЫЙ HTML карточек (m_card) — разметка живёт в одном месте.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
session_write_close();                       // сессия не нужна — не держим блокировку
require_once __DIR__ . '/../../swad/config.php';
require_once __DIR__ . '/../lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

$f   = m_catalog_filters($_GET);
$res = m_games($f);
$html = '';
foreach ($res['items'] as $g) $html .= m_card($g);
$next = $f['offset'] + count($res['items']);
echo json_encode(['html' => $html, 'next' => $next, 'done' => $next >= $res['total'] || !$res['items']], JSON_UNESCAPED_UNICODE);
