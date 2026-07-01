<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/s3.php'; // ваш S3 класс

header('Content-Type: application/json');
session_start();

$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$sprint_id = (int)($_POST['sprint_id'] ?? 0);
if (!$sprint_id) {
    echo json_encode(['success' => false, 'message' => 'Нет ID спринта']);
    exit;
}

$db = (new Database())->connect();

// Проверка прав (только хост)
$check = $db->prepare("SELECT host_user_id FROM sprints WHERE id = ?");
$check->execute([$sprint_id]);
if ($check->fetchColumn() != $userId) {
    echo json_encode(['success' => false, 'message' => 'Нет прав на управление этим спринтом']);
    exit;
}

// Получаем данные из POST
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$theme = trim($_POST['theme'] ?? '');
$useful_links = trim($_POST['useful_links'] ?? '');
$rules = trim($_POST['rules'] ?? '');
$max_participants = (int)($_POST['max_participants'] ?? 100);

// Даты (ожидаем в формате "Y-m-d\TH:i" или "Y-m-d H:i:s")
$registration_start = $_POST['registration_start'] ?? null;
$registration_end   = $_POST['registration_end'] ?? null;
$jam_start          = $_POST['jam_start'] ?? null;
$jam_end            = $_POST['jam_end'] ?? null;
$voting_start       = $_POST['voting_start'] ?? null;
$voting_end         = $_POST['voting_end'] ?? null;

// Преобразуем даты в формат для БД (если не пустые)
function prepareDate($dateStr) {
    if (empty($dateStr)) return null;
    // если пришло с T (из datetime-local), заменяем на пробел
    $dateStr = str_replace('T', ' ', $dateStr);
    // если нет секунд, добавляем :00
    if (strlen($dateStr) == 16) {
        $dateStr .= ':00';
    }
    return $dateStr;
}

$registration_start = prepareDate($registration_start);
$registration_end   = prepareDate($registration_end);
$jam_start          = prepareDate($jam_start);
$jam_end            = prepareDate($jam_end);
$voting_start       = prepareDate($voting_start);
$voting_end         = prepareDate($voting_end);

// Обработка логотипа
$logo_url = null;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $uploader = new S3Uploader();
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $logo_url = $uploader->uploadFile($_FILES['logo']['tmp_name'], 'sprints/' . uniqid('', true) . '.' . $ext);
    if (!$logo_url) {
        echo json_encode(['success' => false, 'message' => 'Ошибка загрузки логотипа']);
        exit;
    }
} else {
    // Если файл не загружен, оставляем старый логотип (если он есть)
    $stmt = $db->prepare("SELECT logo_url FROM sprints WHERE id = ?");
    $stmt->execute([$sprint_id]);
    $logo_url = $stmt->fetchColumn();
}

// Формируем запрос на обновление
$sql = "
    UPDATE sprints SET
        title = ?,
        description = ?,
        theme = ?,
        useful_links = ?,
        rules = ?,
        max_participants = ?,
        registration_start = ?,
        registration_end = ?,
        jam_start = ?,
        jam_end = ?,
        voting_start = ?,
        voting_end = ?,
        logo_url = ?
    WHERE id = ?
";

$stmt = $db->prepare($sql);
$result = $stmt->execute([
    $title,
    $description,
    $theme,
    $useful_links,
    $rules,
    $max_participants,
    $registration_start,
    $registration_end,
    $jam_start,
    $jam_end,
    $voting_start,
    $voting_end,
    $logo_url,
    $sprint_id
]);

if ($result) {
    echo json_encode(['success' => true, 'logo_url' => $logo_url]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка обновления данных']);
}