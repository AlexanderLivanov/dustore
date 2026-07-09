<?php
/**
 * swad/controllers/jams/add_external_judge.php  (v2)
 * Добавляет ВНЕШНЕГО члена жюри (без аккаунта Dustore) в sprint_experts.
 *
 * Принимает ДВА формата:
 *   1) multipart/form-data: sprint_id, name, company?, role?, contact?, photo? (файл → S3)
 *   2) старый JSON: { sprint_id, name, company?, role?, avatar?, contact? }
 *
 * Доступ — только админам.
 */
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../config.php');
if (session_status() === PHP_SESSION_NONE) session_start();

function out($ok, $msg = '', $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedAdmins = ['TheCreator', 'asfasgag', 'Eshward_Williams', 'testuser']; // CONFIRM: актуальный список
$username = $_SESSION['USERDATA']['username'] ?? '';
if (!in_array($username, $allowedAdmins, true)) {
    http_response_code(403);
    out(false, 'Нет доступа');
}

/* Источник данных: multipart или JSON */
if (!empty($_POST)) {
    $data = $_POST;
} else {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
}

$sprintId = (int)($data['sprint_id'] ?? 0);
$name     = mb_substr(trim($data['name']    ?? ''), 0, 120);
$company  = mb_substr(trim($data['company'] ?? ''), 0, 120);
$role     = mb_substr(trim($data['role']    ?? ''), 0, 120);
$contact  = mb_substr(trim($data['contact'] ?? ''), 0, 500);
$avatar   = trim($data['avatar'] ?? '');

if (!$sprintId)   out(false, 'Не указан спринт');
if ($name === '') out(false, 'Имя обязательно');

/* Ссылка на соцсеть — только http(s) */
if ($contact !== '' && !preg_match('~^https?://~i', $contact)) {
    out(false, 'Ссылка на соцсеть должна начинаться с http(s)://');
}

/* Фото файлом → S3 (та же сигнатура, что при загрузке лого спринта) */
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['photo'];
    if ($f['size'] > 4 * 1024 * 1024) out(false, 'Фото: максимум 4 МБ');
    $mime = mime_content_type($f['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) out(false, 'Фото: только JPG / PNG / WebP');

    $uploader = new S3Uploader();
    $avatar = $uploader->uploadFile($f['tmp_name'], 'sprints/judges/' . uniqid('', true) . '.' . $ext);
    if (!$avatar) out(false, 'Ошибка загрузки фото в хранилище');
}

$db = (new Database())->connect();
if (!$db) out(false, 'Ошибка БД');

try {
    $stmt = $db->prepare("
        INSERT INTO sprint_experts
            (sprint_id, user_id, external_name, external_company, external_role, external_avatar, external_contact)
        VALUES (?, NULL, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$sprintId, $name, $company ?: null, $role ?: null, $avatar ?: null, $contact ?: null]);
    out(true, 'Член жюри добавлен', [
        'id'     => (int)$db->lastInsertId(),
        'avatar' => $avatar ?: null,
    ]);
} catch (Exception $e) {
    error_log('add_external_judge: ' . $e->getMessage());
    out(false, 'Не удалось добавить (миграция sprint_experts накатана?)');
}
