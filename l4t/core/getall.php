<?php
// core/getall.php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

$stmt = $pdo->prepare("
    SELECT * FROM bids ORDER BY created_at DESC
");

$stmt->execute();
$rows = $stmt->fetchAll();

$requests = [];

foreach ($rows as $row) {
    $requests[] = [
        'id'       => (int)$row['id'],
        'title'    => $row['title'],
        // 'author'   => '@' . $row['author'],
        'type'     => $row['stage'],
        'desc'     => $row['description'],
        'views'    => (int)$row['views'],
        // 'comments' => (int)$row['comments'],
        'fav'      => (int)$row['favorites'],
        'date'     => $row['created_at']
    ];
}

echo json_encode($requests, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
