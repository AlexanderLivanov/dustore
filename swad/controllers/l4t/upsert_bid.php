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
require_once(__DIR__ . '/_csrf.php');

/* Все ответы — редирект, поэтому до header() ничего не печатаем. */
function back(string $tab, string $status): void {
    header('Location: /l4t/?tab=' . rawurlencode($tab) . '&status=' . rawurlencode($status));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') back('my', 'bad_method');
if (!csrf_check())                          back('my', 'csrf');
if (empty($_SESSION['USERDATA']['id']))     back('my', 'auth');

$userId = (int)$_SESSION['USERDATA']['id'];

$db   = new Database();
$pdo  = $db->connect('desl4t');
$main = $db->connect();               // dustore — нужна для проверки студий
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ── Поля ───────────────────────────────────────────────────────────────── */
$cut = fn($k, $n) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);

/* Лимиты — ровно по колонкам bids (DESCRIBE):
     search_role varchar(100)   search_spec varchar(100)
     experience  varchar(50)    conditions  varchar(100)
     goal        varchar(150)   details     text
   В прошлой версии я резал длиннее, чем влезает, и в strict mode INSERT
   падал с 1406 Data too long — отсюда и был status=error. */
$bidId   = (int)($_POST['bid_id'] ?? 0);
$role    = $cut('role',    100);
$spec    = $cut('spec',    100);
$exp     = $cut('exp',      50);
$cond    = $cut('cond',    100);
$goal    = $cut('goal',    150);
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

/**
 * Навыки и рыночные поля заявки (бюджет, формат, срок) + запуск сведения
 * с предложениями. Без навыков заявку не видно в стакане и «Для тебя».
 */
function save_bid_skills(PDO $l4t, PDO $main, int $bidId): bool {
    require_once __DIR__ . '/../../../l4t/lib/market.php';
    try {
        $x = new L4TX($main, $l4t);
        $touched = false;
        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
            $x->saveBidSkills($bidId, array_map('strval', $_POST['skills']));
            $touched = true;
        }
        if (isset($_POST['pay_type'])) {
            (new L4TMarket($x, $main))->saveNeedMarket($bidId, $_POST);
            $touched = true;
        }
        return $touched;
    } catch (Throwable $e) {
        error_log('[l4t/upsert_bid] market: ' . $e->getMessage());
        return false;
    }
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
        $changed = $st->rowCount() > 0;
        $mine = $pdo->prepare("SELECT 1 FROM bids WHERE id = ? AND bidder_id = ?");
        $mine->execute([$bidId, $userId]);
        if ($mine->fetchColumn() && save_bid_skills($pdo, $main, $bidId)) $changed = true;
        back('my', $changed ? 'updated' : 'nothing_changed');
    }

    /* stage при создании — 'active'. В create_bid.php стояло 'open',
       из-за чего такие заявки не попадали в ленту (index.php фильтрует
       по stage='active') и на них нельзя было откликнуться. */
    $jamId = (int)($_POST['jam_id'] ?? 0) ?: null;

    /* Колонки берём из реальной схемы, а не из предположений: в боевой bids
       нет owner_id — и этот INSERT падал с 1054 на каждой новой заявке.
       Необязательные поля пишем, только если такая колонка есть. */
    $have = array_flip($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bids'")->fetchAll(PDO::FETCH_COLUMN));
    $row = [
        'bidder_id'   => $userId,
        'search_role' => $role,
        'search_spec' => $spec,
        'experience'  => $exp,
        'conditions'  => $cond,
        'goal'        => $goal,
        'details'     => $details,
        'stage'       => 'active',
    ];
    foreach (['owner_type' => $ownerType, 'owner_id' => $ownerId, 'jam_id' => $jamId] as $col => $val) {
        if (isset($have[$col])) $row[$col] = $val;
    }
    $cols = array_keys($row);
    $st = $pdo->prepare("INSERT INTO bids (" . implode(', ', $cols) . ", created_at)
                         VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ", NOW())");
    $st->execute(array_values($row));
    save_bid_skills($pdo, $main, (int)$pdo->lastInsertId());

    back('my', 'created');

} catch (PDOException $e) {
    error_log('[l4t/upsert_bid] ' . $e->getMessage());
    back('my', 'error');
}