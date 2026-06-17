<?php
session_start();
header('Content-Type: application/json');
require_once('../../swad/config.php');

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_POST['asset_id'] ?? 0);
$user_id  = (int)$_SESSION['USERDATA']['id'];

if ($asset_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid asset_id']);
    exit;
}

try {
    // Проверяем что ассет реально бесплатный и опубликован
    $asset = $pdo->prepare("SELECT id, price FROM assets WHERE id = ? AND status = 'published' LIMIT 1");
    $asset->execute([$asset_id]);
    $row = $asset->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Ассет не найден']);
        exit;
    }
    if ($row['price'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Этот ассет платный']);
        exit;
    }

    // Проверяем что ещё не получен
    $exists = $pdo->prepare("SELECT id FROM asset_library WHERE player_id = ? AND asset_id = ? LIMIT 1");
    $exists->execute([$user_id, $asset_id]);
    if ($exists->fetch()) {
        echo json_encode(['success' => true, 'already_owned' => true]);
        exit;
    }

    // Добавляем в библиотеку
    $pdo->prepare("INSERT INTO asset_library (player_id, asset_id, date) VALUES (?, ?, NOW())")
        ->execute([$user_id, $asset_id]);

    // Увеличиваем счётчик скачиваний
    $pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?")
        ->execute([$asset_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
