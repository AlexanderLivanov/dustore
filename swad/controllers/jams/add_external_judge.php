<?php
/**
 * swad/controllers/jams/add_external_judge.php
 * Добавляет ВНЕШНЕГО судью (без аккаунта Dustore) в sprint_experts.
 * JSON: { sprint_id, name, company?, role?, avatar?, contact? }
 * Доступ — только админам.
 */

header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../config.php');

if (session_status() === PHP_SESSION_NONE) session_start();

function out($ok, $msg = '', $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

$allowedAdmins = ['TheCreator', 'asfasgag', 'Eshward_Williams', 'testuser'];
$username = $_SESSION['USERDATA']['username'] ?? '';
if (!in_array($username, $allowedAdmins, true)) {
    http_response_code(403);
    out(false, 'Нет доступа');
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];

$sprintId = (int)($data['sprint_id'] ?? 0);
$name     = trim($data['name'] ?? '');
$company  = trim($data['company'] ?? '');
$role     = trim($data['role'] ?? '');
$avatar   = trim($data['avatar'] ?? '');
$contact  = trim($data['contact'] ?? '');

if (!$sprintId)     out(false, 'Не указан спринт');
if ($name === '')   out(false, 'Имя судьи обязательно');

$db = (new Database())->connect();
if (!$db) out(false, 'Ошибка БД');

try {
    $stmt = $db->prepare("
        INSERT INTO sprint_experts
            (sprint_id, user_id, external_name, external_company, external_role, external_avatar, external_contact)
        VALUES (?, NULL, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$sprintId, $name, $company ?: null, $role ?: null, $avatar ?: null, $contact ?: null]);
    out(true, 'Внешний судья добавлен', ['id' => (int)$db->lastInsertId()]);
} catch (Exception $e) {
    out(false, 'Не удалось добавить судью (проверь, что миграция sprint_experts применена)');
}