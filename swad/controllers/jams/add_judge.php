<?php
declare(strict_types=1);

/**
 * swad/controllers/jams/add_judge.php — назначить эксперта из пользователей.
 *
 * ЧТО БЫЛО НЕ ТАК
 *
 * 1. Падало с «Field 'created_at' doesn't have a default value». Колонка
 *    объявлена NOT NULL без DEFAULT, а INSERT её не передавал. На локалке
 *    MySQL часто работает без STRICT_TRANS_TABLES и молча подставляет ноль —
 *    поэтому на проде вылезло, а дома нет. Передаём NOW() явно; отдельно
 *    в миграции 005 колонке добавлен DEFAULT CURRENT_TIMESTAMP.
 *
 * 2. Даже без этой ошибки эксперт не получил бы прав. В админке живут ДВА
 *    несвязанных списка жюри:
 *        jam_judges      <- сюда писал этот контроллер
 *        sprint_experts  <- отсюда читают vote.php, results.php,
 *                           participant.php, admin_votes.php и jams/index.php
 *    Голосование про jam_judges не знает вовсе, поэтому назначенный судья
 *    получал обычные 10 баллов на весь джем вместо 10 на каждую работу.
 *    Пишем в sprint_experts — ту таблицу, которую читает вся платформа.
 *    Упоминание jam_judges во всём проекте осталось ровно одно (в admin.php),
 *    так что никакой другой код этим переездом не задет.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once(__DIR__ . '/../../config.php');

function reply(array $d, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(['success' => false, 'message' => 'Только POST'], 405);
if (empty($_SESSION['USERDATA']['id']))     reply(['success' => false, 'message' => 'Не авторизован'], 401);

$data      = json_decode(file_get_contents('php://input'), true) ?: [];
$sprint_id = (int)($data['jam_id'] ?? $data['sprint_id'] ?? 0);
$user_id   = (int)($data['user_id'] ?? 0);

if ($sprint_id <= 0) reply(['success' => false, 'message' => 'Не указан джем']);
if ($user_id   <= 0) reply(['success' => false, 'message' => 'Не выбран пользователь']);

$db = (new Database())->connect();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    /* Право назначать жюри: организатор джема либо глобальный администратор.
       Раньше проверки не было вовсе — достаточно было знать URL контроллера,
       чтобы назначить себя экспертом в чужом джеме и получить по 10 баллов
       на каждую работу. */
    $me         = (int)$_SESSION['USERDATA']['id'];
    $globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
    $username   = (string)($_SESSION['USERDATA']['username'] ?? '');
    $allowedAdmins = ['TheCreator', 'asfasgag', 'Eshward_Williams', 'testuser'];

    $s = $db->prepare("SELECT host_user_id, title FROM sprints WHERE id = ? LIMIT 1");
    $s->execute([$sprint_id]);
    $sprint = $s->fetch(PDO::FETCH_ASSOC);
    if (!$sprint) reply(['success' => false, 'message' => 'Джем не найден']);

    $isHost  = ((int)$sprint['host_user_id'] === $me);
    $isAdmin = ($globalRole === -1) || in_array($username, $allowedAdmins, true);
    if (!$isHost && !$isAdmin) reply(['success' => false, 'message' => 'Недостаточно прав'], 403);

    // Пользователь существует?
    $u = $db->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
    $u->execute([$user_id]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
    if (!$user) reply(['success' => false, 'message' => 'Пользователь не найден']);

    /* Организатор не может быть экспертом в своём джеме: он и так не голосует
       (vote.php запрещает), а в списке жюри это выглядело бы странно. */
    if ((int)$sprint['host_user_id'] === $user_id) {
        reply(['success' => false, 'message' => 'Организатор джема не может быть в жюри']);
    }

    // Уже назначен?
    $dup = $db->prepare("SELECT id FROM sprint_experts WHERE sprint_id = ? AND user_id = ? LIMIT 1");
    $dup->execute([$sprint_id, $user_id]);
    if ($dup->fetchColumn()) {
        reply(['success' => false, 'message' => 'Этот пользователь уже в жюри']);
    }

    /* created_at передаём явно — см. комментарий в шапке. */
    $ins = $db->prepare("
        INSERT INTO sprint_experts (sprint_id, user_id, created_at)
        VALUES (?, ?, NOW())
    ");
    $ins->execute([$sprint_id, $user_id]);

    reply([
        'success'   => true,
        'expert_id' => (int)$db->lastInsertId(),
        'username'  => $user['username'],
        'message'   => 'Эксперт добавлен',
    ]);

} catch (PDOException $e) {
    error_log('[jams/add_judge] ' . $e->getMessage());
    /* Текст ошибки БД наружу не отдаём: в нём бывают куски запроса.
       Раньше он показывался пользователю прямо в alert — именно так
       и выглядел скриншот с SQLSTATE. */
    reply(['success' => false, 'message' => 'Не удалось добавить эксперта'], 500);
}