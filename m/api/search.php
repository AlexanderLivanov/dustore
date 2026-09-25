<?php
declare(strict_types=1);
/** m/api/search.php?q= — живой поиск: отдаёт готовый HTML (как и подгрузка каталога). */
if (session_status() === PHP_SESSION_NONE) session_start();
session_write_close();
require_once __DIR__ . '/../../swad/config.php';
require_once __DIR__ . '/../lib.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=30');

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') exit;
$q  = mb_substr($q, 0, 80);
$db = (new Database())->connect();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require __DIR__ . '/search_results.php';
