<?php
// devs/jam_attach.php — прикрепить / открепить проект студии к джему.
// JSON endpoint. Авторизация по членству в staff (telegram_id), а не по одной
// активной студии — работает и из консоли (edit.php), и из панели участника,
// где у юзера может быть несколько студий.
//
// POST: project_id, sprint_id (для attach), action = 'attach' | 'detach'
// Ответ: {success, attached, sprint_id, sprint_title, message}

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../swad/config.php');

header('Content-Type: application/json; charset=utf-8');

function jam_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jam_out(['success' => false, 'message' => 'Только POST'], 405);
}

$tgid       = (string)($_SESSION['USERDATA']['telegram_id'] ?? '');
$project_id = (int)($_POST['project_id'] ?? 0);
$sprint_id  = (int)($_POST['sprint_id'] ?? 0);
$action     = $_POST['action'] ?? 'attach';

if ($tgid === '')  jam_out(['success' => false, 'message' => 'Нужна авторизация'], 403);
if (!$project_id)  jam_out(['success' => false, 'message' => 'Не указан проект'], 400);

$db   = new Database();
$conn = $db->connect();

// Проект + его студия-владелец.
$g = $conn->prepare("SELECT id, name, sprint_id, developer FROM games WHERE id = ? LIMIT 1");
$g->execute([$project_id]);
$game = $g->fetch(PDO::FETCH_ASSOC);
if (!$game) jam_out(['success' => false, 'message' => 'Проект не найден'], 404);

// Авторизация: юзер должен состоять в студии-владельце проекта.
$auth = $conn->prepare("SELECT 1 FROM staff WHERE telegram_id = ? AND org_id = ? LIMIT 1");
$auth->execute([(int)$tgid, (int)$game['developer']]);
if (!$auth->fetchColumn()) {
    jam_out(['success' => false, 'message' => 'Проект не принадлежит вашей студии'], 403);
}

// Блокировка: если текущий джем ушёл в голосование / завершён — менять нельзя.
if (!empty($game['sprint_id'])) {
    $cs = $conn->prepare("SELECT status, voting_start FROM sprints WHERE id = ? LIMIT 1");
    $cs->execute([(int)$game['sprint_id']]);
    if ($cur = $cs->fetch(PDO::FETCH_ASSOC)) {
        $voting_live = !empty($cur['voting_start']) && strtotime($cur['voting_start']) <= time();
        if (($cur['status'] ?? '') === 'finished' || $voting_live) {
            jam_out(['success' => false, 'message' => 'Джем в стадии голосования или завершён — привязку менять нельзя'], 409);
        }
    }
}

if ($action === 'detach') {
    $conn->prepare("UPDATE games SET sprint_id = NULL, updated_at = NOW() WHERE id = ?")
         ->execute([$project_id]);
    jam_out(['success' => true, 'attached' => false, 'sprint_id' => null, 'message' => 'Проект откреплён от джема']);
}

// action === 'attach'
if (!$sprint_id) jam_out(['success' => false, 'message' => 'Не выбран джем'], 400);

$s = $conn->prepare("SELECT id, title, status, voting_start FROM sprints WHERE id = ? LIMIT 1");
$s->execute([$sprint_id]);
$sprint = $s->fetch(PDO::FETCH_ASSOC);
if (!$sprint) jam_out(['success' => false, 'message' => 'Джем не найден'], 404);

$voting_live = !empty($sprint['voting_start']) && strtotime($sprint['voting_start']) <= time();
$open = in_array($sprint['status'], ['registration', 'ongoing'], true) && !$voting_live;
if (!$open) {
    jam_out(['success' => false, 'message' => 'Этот джем сейчас не принимает работы'], 409);
}

// Билд обязателен — «сдать» пустой проект нельзя.
$zip = $conn->prepare("SELECT game_zip_url FROM games WHERE id = ?");
$zip->execute([$project_id]);
if (!$zip->fetchColumn()) {
    jam_out(['success' => false, 'message' => 'У проекта не загружен билд. Загрузите его в консоли, затем прикрепите к джему.'], 409);
}

$conn->prepare("UPDATE games SET sprint_id = ?, updated_at = NOW() WHERE id = ?")
     ->execute([$sprint_id, $project_id]);

jam_out([
    'success'      => true,
    'attached'     => true,
    'sprint_id'    => $sprint_id,
    'sprint_title' => $sprint['title'],
    'message'      => 'Проект прикреплён к джему «' . $sprint['title'] . '». Отправьте его на модерацию в консоли — эксперты проверят билд перед публикацией.',
]);