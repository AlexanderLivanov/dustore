<?php

/**
 * m/api/search.php
 * Возвращает: { games: [...], studios: [...] }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../swad/config.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['games' => [], 'studios' => []]);
    exit;
}

$db   = (new Database())->connect();
$like = '%' . $q . '%';

/* Игры */
$gstmt = $db->prepare("
    SELECT g.id, g.name, g.price, g.path_to_cover AS cover,
           s.name AS studio
    FROM games g
    JOIN studios s ON s.id = g.developer
    WHERE g.moderation_status = 'approved'
      AND (g.name LIKE :q OR s.name LIKE :q OR g.genre LIKE :q)
    ORDER BY g.name ASC
    LIMIT 15
");
$gstmt->execute([':q' => $like]);
$games = $gstmt->fetchAll(PDO::FETCH_ASSOC);

/* Студии */
$sstmt = $db->prepare("
    SELECT s.id, s.name, s.avatar_link AS avatar,
           COUNT(DISTINCT g.id) AS game_count
    FROM studios s
    LEFT JOIN games g ON g.developer = s.id AND g.moderation_status = 'approved'
    WHERE s.name LIKE :q
    GROUP BY s.id, s.name, s.avatar_link
    ORDER BY game_count DESC
    LIMIT 5
");
$sstmt->execute([':q' => $like]);
$studios = $sstmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(
    ['games' => $games, 'studios' => $studios],
    JSON_UNESCAPED_UNICODE
);
