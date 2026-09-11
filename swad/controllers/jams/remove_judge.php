<?php
declare(strict_types=1);

/**
 * swad/controllers/remove_judge.php — снять эксперта из пользователей.
 *
 * Пара к add_judge.php: работает с той же таблицей sprint_experts, куда
 * теперь пишутся судьи с аккаунтом. Внешних судей (без аккаунта) убирает
 * отдельный remove_external_judge.php — у них user_id = NULL, и удалять их
 * по user_id нельзя в принципе.
 *
 * Путь файла оставлен прежним (controllers/, а не controllers/jams/) —
 * именно его зовёт admin.php, менять разметку ради переезда незачем.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once(__DIR__ . '/../config.php');

function reply(array $d, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(['success' => false, 'message' => 'Только POST'], 405);
if (empty($_SESSION['USERDATA']['id']))     reply(['success' => false, 'message' => 'Не авторизован'], 401);

$data      = json_decode(file_get_contents('php://input'), true) ?: [];
$sprint_id = (int)($data['sprint_id'] ?? $data['jam_id'] ?? 0);
$user_id   = (int)($data['user_id'] ?? 0);

if ($sprint_id <= 0 || $user_id <= 0) reply(['success' => false, 'message' => 'Не хватает параметров']);

$db = (new Database())->connect();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $me         = (int)$_SESSION['USERDATA']['id'];
    $globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
    $username   = (string)($_SESSION['USERDATA']['username'] ?? '');
    $allowedAdmins = ['TheCreator', 'asfasgag', 'Eshward_Williams', 'testuser'];

    $s = $db->prepare("SELECT host_user_id FROM sprints WHERE id = ? LIMIT 1");
    $s->execute([$sprint_id]);
    $host = $s->fetchColumn();
    if ($host === false) reply(['success' => false, 'message' => 'Джем не найден']);

    $isAdmin = ($globalRole === -1) || in_array($username, $allowedAdmins, true);
    if ((int)$host !== $me && !$isAdmin) reply(['success' => false, 'message' => 'Недостаточно прав'], 403);

    $db->beginTransaction();

    /* Сначала снимаем пики этого эксперта, потом его самого.
       Если оставить пики, в results.php и admin_votes.php повиснут строки
       с JOIN на несуществующего эксперта — «выбор эксперта» просто исчезнет
       из выдачи, но данные останутся мусором в таблице. */
    $picks = $db->prepare("
        DELETE p FROM sprint_expert_picks p
          JOIN sprint_experts se ON se.id = p.expert_id
         WHERE p.sprint_id = ? AND se.user_id = ?
    ");
    $picks->execute([$sprint_id, $user_id]);

    $del = $db->prepare("DELETE FROM sprint_experts WHERE sprint_id = ? AND user_id = ?");
    $del->execute([$sprint_id, $user_id]);
    $removed = $del->rowCount();

    $db->commit();

    if (!$removed) reply(['success' => false, 'message' => 'Этот пользователь не в жюри']);

    /* Голоса, отданные экспертом, НЕ трогаем намеренно: они уже учтены
       в лидерборде с весом эксперта, и молча их стирать нельзя. Если нужно
       пересчитать — это отдельное осознанное действие в админке. */
    reply(['success' => true, 'message' => 'Эксперт снят']);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[remove_judge] ' . $e->getMessage());
    reply(['success' => false, 'message' => 'Не удалось снять эксперта'], 500);
}