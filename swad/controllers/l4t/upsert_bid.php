<?php
declare(strict_types=1);
/**
 * swad/controllers/l4t/upsert_bid.php — создание и правка заявки.
 *
 * ГЛАВНОЕ, ЧТО БЫЛО СЛОМАНО: редактирование не работало вообще.
 *
 *   INSERT ... (bidder_id, owner_type, search_role, ...)   <- owner_id НЕ пишется
 *   UPDATE bids SET ... WHERE id = ? AND owner_id = ?      <- ищем по owner_id
 *
 * На создании owner_id оставался пустым, поэтому условие UPDATE не совпадало
 * никогда. execute() при этом отрабатывал успешно, 0 изменённых строк,
 * дальше redirect на /l4t/?tab=my — снаружи выглядело как «сохранилось».
 * Владелец заявки хранится в bidder_id, по нему и ищем.
 *
 * Второе: owner_type и owner_id приходили из POST без единой проверки.
 * Можно было отправить owner_type=studio с чужим owner_id и опубликовать
 * заявку от имени любой студии на платформе.
 */

session_start();
require_once(__DIR__ . '/../../config.php');

/* Все ответы — редирект, поэтому до header() ничего не печатаем. */
function back(string $tab, string $status): void {
    header('Location: /l4t/?tab=' . rawurlencode($tab) . '&status=' . rawurlencode($status));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') back('my', 'bad_method');
if (empty($_SESSION['USERDATA']['id']))     back('my', 'auth');

$userId = (int)$_SESSION['USERDATA']['id'];

$db   = new Database();
$pdo  = $db->connect('desl4t');
$main = $db->connect();               // dustore — нужна для проверки студий
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ── Поля ───────────────────────────────────────────────────────────────── */
$cut = fn($k, $n) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);

$bidId   = (int)($_POST['bid_id'] ?? 0);
$role    = $cut('role', 120);
$spec    = $cut('spec', 120);
$exp     = $cut('exp', 120);
$cond    = $cut('cond', 500);
$goal    = $cut('goal', 500);
$details = $cut('details', 5000);

if ($role === '') back('my', 'no_role');

/* ── Кто публикует ──────────────────────────────────────────────────────── */
$ownerType = ($_POST['owner_type'] ?? 'user') === 'studio' ? 'studio' : 'user';
$ownerId   = $userId;

if ($ownerType === 'studio') {
    $claimed = (int)($_POST['owner_id'] ?? 0);
    if (!$claimed || !user_owns_studio($main, $userId, $claimed)) back('my', 'not_your_studio');
    $ownerId = $claimed;
}

/** Владелец студии или её staff. Стык staff <-> users идёт через telegram_id
 *  двумя запросами: колонки разных типов (BIGINT vs VARCHAR), при джойне
 *  индекс на users.telegram_id не используется. */
function user_owns_studio(PDO $main, int $userId, int $studioId): bool {
    $o = $main->prepare("SELECT 1 FROM studios WHERE id = ? AND owner_id = ? LIMIT 1");
    $o->execute([$studioId, $userId]);
    if ($o->fetchColumn()) return true;

    $t = $main->prepare("SELECT telegram_id FROM users WHERE id = ? LIMIT 1");
    $t->execute([$userId]);
    $tg = $t->fetchColumn();
    if ($tg === false || $tg === null || $tg === '') return false;

    $s = $main->prepare("SELECT 1 FROM staff WHERE org_id = ? AND telegram_id = ? LIMIT 1");
    $s->execute([$studioId, $tg]);
    return (bool)$s->fetchColumn();
}

/* ── Запись ─────────────────────────────────────────────────────────────── */
try {
    if ($bidId > 0) {
        /* Правим только СВОЮ заявку. Условие по bidder_id, а не owner_id:
           именно туда пишется автор при создании. */
        $st = $pdo->prepare("
            UPDATE bids
               SET search_role = ?, search_spec = ?, experience = ?,
                   conditions = ?, goal = ?, details = ?
             WHERE id = ? AND bidder_id = ?");
        $st->execute([$role, $spec, $exp, $cond, $goal, $details, $bidId, $userId]);

        // rowCount() == 0 значит «не твоя заявка» либо ничего не изменилось —
        // раньше оба случая молча выглядели как успех.
        back('my', $st->rowCount() > 0 ? 'updated' : 'nothing_changed');
    }

    /* stage при создании — 'active'. В create_bid.php стояло 'open',
       из-за чего такие заявки не попадали в ленту (index.php фильтрует
       по stage='active') и на них нельзя было откликнуться. */
    $st = $pdo->prepare("
        INSERT INTO bids
            (bidder_id, owner_type, owner_id, search_role, search_spec,
             experience, conditions, goal, details, stage, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
    $st->execute([$userId, $ownerType, $ownerId, $role, $spec, $exp, $cond, $goal, $details]);

    back('my', 'created');

} catch (PDOException $e) {
    error_log('[l4t/upsert_bid] ' . $e->getMessage());
    back('my', 'error');
}