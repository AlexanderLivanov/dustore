<?php
declare(strict_types=1);

/**
 * api/friends.php — действия с друзьями.
 *
 * Что было исправлено:
 *
 * 1. Обработчик ошибок отдавал КЛИЕНТУ полный путь до файла и номер строки:
 *        "PHP Error [2]: ... in /var/www/dustore/swad/controllers/user.php:461"
 *    Это карта файловой системы сервера, выданная любому желающему одним
 *    кривым запросом. Плюс catch отдавал наружу $e->getMessage() от PDO,
 *    то есть при ошибке SQL в ответ уезжал кусок запроса.
 *    Теперь подробности идут в error_log, клиент получает код ошибки.
 *
 * 2. Не было проверки CSRF: сторонний сайт мог от имени залогиненного
 *    посетителя отправлять заявки, принимать чужие и удалять друзей.
 *
 * 3. Изменяющие действия принимались и по GET:
 *        $action = $_POST['action'] ?? $_GET['action'] ?? null;
 *    При этом user_id читался только из POST. То есть
 *    <img src="/api/friends.php?action=send"> создавал строку в friends
 *    с friend_id = 0 — мусор в таблице с любой стороннней страницы.
 *
 * 4. Не проверялось, что адресат вообще существует и что это не ты сам.
 *
 * Формат ответов не изменился: success / error / message / friends / requests.
 */

ob_start();

/* Ошибки и исключения превращаем в JSON, но наружу отдаём только код.
   Подробности — в лог. */
function fail_json(string $code, string $logLine = ''): void
{
    if ($logLine !== '') error_log('[api/friends] ' . $logLine);
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $code], JSON_UNESCAPED_UNICODE);
    exit;
}

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    // Раньше любое предупреждение обрывало запрос. Notice и deprecated
    // пишем в лог и продолжаем — падать из-за них незачем.
    error_log("[api/friends] PHP [$no] $str in $file:$line");
    if (in_array($no, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_WARNING], true)) {
        return true;
    }
    fail_json('server_error');
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    fail_json('server_error', get_class($e) . ': ' . $e->getMessage());
});

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/user.php';
require_once __DIR__ . '/../swad/controllers/csrf.php';

$user = new User();

if (empty($_SESSION['USERDATA']['id'])) fail_json('not_auth');
$currentUser = (int)$_SESSION['USERDATA']['id'];

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

/* Изменяющие действия — только POST и только с валидным токеном.
   Читающие (list / incoming / outgoing) оставляем доступными по GET:
   они ничего не меняют и вызываются из нескольких мест. */
const MUTATING = ['send', 'accept', 'decline', 'remove', 'cancel'];

if (in_array($action, MUTATING, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail_json('method_not_allowed');
    if (!csrf_valid())                          fail_json('csrf');
}

/** Проверка адресата: существует, не ты сам. */
function target_id(User $user, int $currentUser): int
{
    $id = (int)($_POST['user_id'] ?? 0);
    if ($id <= 0)            fail_json('bad_user');
    if ($id === $currentUser) fail_json('self');

    $st = $user->db->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    if (!$st->fetchColumn()) fail_json('user_not_found');

    return $id;
}

/** Простой лимит на отправку заявок: не больше 20 в час на сессию. */
function throttle_send(): void
{
    $now = time();
    $log = array_values(array_filter(
        $_SESSION['friend_send_log'] ?? [],
        static fn($t) => $t > $now - 3600
    ));
    if (count($log) >= 20) fail_json('too_many');
    $log[] = $now;
    $_SESSION['friend_send_log'] = $log;
}

function ok(array $extra = []): void
{
    echo json_encode(['success' => true] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($action) {

        case 'send':
            $to = target_id($user, $currentUser);
            throttle_send();
            $user->sendFriendRequest($currentUser, $to);
            ok(['message' => 'request_sent']);

        case 'accept':
            $from = target_id($user, $currentUser);
            $user->acceptFriendRequest($currentUser, $from);
            ok(['message' => 'request_accepted']);

        case 'decline':
            $from = target_id($user, $currentUser);
            $user->declineFriendRequest($currentUser, $from);
            ok(['message' => 'request_declined']);

        case 'remove':
            $friendId = target_id($user, $currentUser);
            $user->removeFriend($currentUser, $friendId);
            ok(['message' => 'friend_removed']);

        case 'cancel':
            $to = target_id($user, $currentUser);
            $user->cancelFriendRequest($currentUser, $to);
            ok(['message' => 'request_cancelled']);

        case 'list':
            $stmt = $user->db->prepare("
                SELECT u.id, u.username, u.profile_picture, f.status
                  FROM friends f
                  JOIN users u
                    ON (u.id = f.friend_id AND f.player_id = ?)
                    OR (u.id = f.player_id AND f.friend_id = ?)
                 WHERE f.status = 'accepted'
                 LIMIT 500
            ");
            $stmt->execute([$currentUser, $currentUser]);
            ok(['friends' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        case 'incoming':
            $stmt = $user->db->prepare("
                SELECT u.id, u.username, u.profile_picture, f.created_at
                  FROM friends f
                  JOIN users u ON u.id = f.player_id
                 WHERE f.friend_id = ? AND f.status = 'pending'
                 LIMIT 200
            ");
            $stmt->execute([$currentUser]);
            ok(['requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        case 'outgoing':
            $stmt = $user->db->prepare("
                SELECT u.id, u.username, u.profile_picture, f.created_at
                  FROM friends f
                  JOIN users u ON u.id = f.friend_id
                 WHERE f.player_id = ? AND f.status = 'pending'
                 LIMIT 200
            ");
            $stmt->execute([$currentUser]);
            ok(['requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        default:
            fail_json('unknown_action');
    }
} catch (PDOException $e) {
    // Текст ошибки БД наружу не отдаём: там бывают куски запроса
    fail_json('db_error', $e->getMessage());
} catch (Exception $e) {
    // Осмысленные сообщения из User::* (например «вы уже друзья») показать можно
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}