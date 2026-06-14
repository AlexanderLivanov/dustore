<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/s3.php'; // ваш S3 класс

header('Content-Type: application/json');
session_start();

$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) die(json_encode(['success'=>false,'message'=>'Не авторизован']));

$sprint_id = (int)($_POST['sprint_id'] ?? 0);
if (!$sprint_id) die(json_encode(['success'=>false,'message'=>'Нет ID']));

$db = (new Database())->connect();
$check = $db->prepare("SELECT host_user_id FROM sprints WHERE id = ?");
$check->execute([$sprint_id]);
if ($check->fetchColumn() != $userId) die(json_encode(['success'=>false,'message'=>'Нет прав']));

$title = trim($_POST['title'] ?? '');
$theme = trim($_POST['theme'] ?? '');
$start_at = $_POST['start_at'] ?? '';
$duration_value = (int)($_POST['duration_value'] ?? 1);
$duration_unit = $_POST['duration_unit'] ?? 'days';
$max_participants = (int)($_POST['max_participants'] ?? 100);

if (!$title || !$start_at) die(json_encode(['success'=>false,'message'=>'Заполните название и дату']));

$logo_url = null;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $uploader = new S3Uploader();
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $logo_url = $uploader->uploadFile($_FILES['logo']['tmp_name'], 'sprints/' . uniqid('', true) . '.' . $ext);
    if (!$logo_url) die(json_encode(['success'=>false,'message'=>'Ошибка загрузки логотипа']));
} else {
    $stmt = $db->prepare("SELECT logo_url FROM sprints WHERE id = ?");
    $stmt->execute([$sprint_id]);
    $logo_url = $stmt->fetchColumn();
}

$stmt = $db->prepare("UPDATE sprints SET title=?, theme=?, start_at=?, duration_value=?, duration_unit=?, max_participants=?, logo_url=? WHERE id=?");
$stmt->execute([$title, $theme, $start_at, $duration_value, $duration_unit, $max_participants, $logo_url, $sprint_id]);

echo json_encode(['success'=>true, 'logo_url'=>$logo_url]);