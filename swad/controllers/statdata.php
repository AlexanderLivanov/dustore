<?php
require_once('../config.php');

$db = new Database();
$pdo = $db->connect();

$stats = [];

/* Счётчики */
$stats['games_total'] = (int)$pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
$stats['games_published'] = (int)$pdo->query("
    SELECT COUNT(*) FROM games WHERE status = 'published'
")->fetchColumn();
$stats['games_draft'] = (int)$pdo->query("
    SELECT COUNT(*) FROM games WHERE status = 'draft'
")->fetchColumn();

/* Жанры */
$stats['genres'] = $pdo->query("
    SELECT genre, COUNT(*) as count
    FROM games
    GROUP BY genre
")->fetchAll(PDO::FETCH_ASSOC);

/* Новые игры за 30 дней */
$stats['games_30d'] = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM games
    WHERE created_at >= NOW() - INTERVAL 30 DAY
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

/* Средний GQI */
$stats['avg_gqi'] = round(
    (float)$pdo->query("SELECT AVG(GQI) FROM games")->fetchColumn(),
    1
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($stats, JSON_UNESCAPED_UNICODE);
exit;
