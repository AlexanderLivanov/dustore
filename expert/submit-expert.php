<?php
session_start();
require_once __DIR__ . '/../swad/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: expert-apply");
    exit;
}

if (!isset($_SESSION['USERDATA'])) {
    header("Location: /login");
    exit;
}

$user = $_SESSION['USERDATA'];

if (empty($user['email'])) {
    header("Location: expert-apply?err=no_email");
    exit;
}

$userId     = (int)$user['id'];
$experience = trim($_POST['experience'] ?? '');
$motivation = trim($_POST['motivation'] ?? '');

if (!$experience || !$motivation) {
    header("Location: expert-apply?err=empty_fields");
    exit;
}

$db  = new Database();
$pdo = $db->connect();

// Уже подавал заявку?
$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id = ?");
$stmt->execute([$userId]);
if ($stmt->fetch()) {
    header("Location: expert-apply");
    exit;
}

// Набор открыт?
$election = $pdo->query("
    SELECT id FROM expert_elections
    WHERE NOW() BETWEEN start_date AND end_date
    LIMIT 1
")->fetch();
if (!$election) {
    header("Location: expert-apply?err=closed");
    exit;
}

// Вставляем заявку
$pdo->prepare("
    INSERT INTO experts (user_id, status, experience, motivation)
    VALUES (?, 'new', ?, ?)
")->execute([$userId, $experience, $motivation]);

// ── Fire-and-forget: отправляем письмо через notify_worker асинхронно ─────
$payload = http_build_query([
    'type'     => 'expert_apply',
    'username' => $user['username'] ?? '',
    'email'    => $user['email']    ?? '',
    'user_id'  => $userId,
]);
require_once __DIR__ . '/../swad/controllers/loopback.php';
loopback_fire('/expert/notify_expert.php', $payload);
// Если сокет не открылся — просто идём дальше, письмо не критично

header("Location: thanks");
exit;
