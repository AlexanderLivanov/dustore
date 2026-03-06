<?php
session_start();
require_once 'db.php'; // подключение PDO

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die('Неверный метод');

// Получаем ID игры
$gameId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$score = (int)$_POST['score'];
$bugs = (int)$_POST['bugs'];
$gameplay = (int)$_POST['gameplay'];
$graphics = (int)$_POST['graphics'];
$review = trim($_POST['review']);

// Эксперт по сессии
$expertId = $_SESSION['USERDATA']['id'] ?? null;
if (!$expertId) die('Нет авторизации');

// Проверка, что эксперт одобрен
$stmt = $pdo->prepare("SELECT * FROM experts WHERE user_id=? AND status='approved'");
$stmt->execute([$expertId]);
$expert = $stmt->fetch();
if (!$expert) die('Вы не одобрены как эксперт');

// Сохраняем рецензию
$stmt = $pdo->prepare("
    INSERT INTO reviews (game_id, expert_id, score, bugs, gameplay, graphics, review)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$gameId, $expert['id'], $score, $bugs, $gameplay, $graphics, $review]);

// Подсчёт GQI
$stmt = $pdo->prepare("
    SELECT r.*, e.rating AS expert_weight
    FROM reviews r
    JOIN experts e ON r.expert_id = e.id
    WHERE r.game_id = ?
");
$stmt->execute([$gameId]);
$allReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalScore = 0;
$totalWeight = 0;
foreach ($allReviews as $r) {
    $avg = ($r['score'] + $r['bugs'] + $r['gameplay'] + $r['graphics']) / 4;
    $weight = $r['expert_weight'] ?: 1;
    $totalScore += $avg * $weight;
    $totalWeight += $weight;
}
$gqi = $totalWeight ? round($totalScore / $totalWeight, 2) : 0;

// Количество экспертов
$votesCount = count($allReviews);

// Обновляем игру
$stmt = $pdo->prepare("UPDATE games SET GQI=?, rating_count=? WHERE id=?");
$stmt->execute([$gqi, $votesCount, $gameId]);

// Проверка публикации (51% экспертов)
$stmt = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'");
$totalExperts = $stmt->fetchColumn();
if ($votesCount >= ceil($totalExperts * 0.51)) {
    $stmt = $pdo->prepare("UPDATE games SET status='published' WHERE id=?");
    $stmt->execute([$gameId]);
}

header("Location: moderation-game?id=$gameId");
exit;
