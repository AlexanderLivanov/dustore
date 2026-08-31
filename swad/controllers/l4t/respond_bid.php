<?php
declare(strict_types=1);
/**
 * swad/controllers/l4t/respond_bid.php — отклик на заявку.
 *
 * Что поправлено:
 *   - вставка отклика и инкремент счётчика теперь в одной транзакции.
 *     Раньше при падении UPDATE (например, если колонки responses нет)
 *     отклик уже был записан, а пользователь видел «произошла ошибка»
 *     и жал ещё раз — получая «вы уже откликались»;
 *   - счётчик обновляется, только если колонка реально существует.
 *     В l4t/api/getall.php фигурируют views/favorites, а здесь responses —
 *     схема где-то разъехалась, поэтому проверяем, а не надеемся;
 *   - нельзя откликнуться на собственную заявку;
 *   - session_start() до подключения конфига: если config.php когда-нибудь
 *     что-то выведет, сессия уже не стартует.
 */

session_start();
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

function reply(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['USERDATA']['id'])) reply(['success' => false, 'message' => 'Не авторизован']);
$userId = (int)$_SESSION['USERDATA']['id'];

$data    = json_decode(file_get_contents('php://input'), true) ?: [];
$bidId   = (int)($data['bid_id'] ?? 0);
$message = mb_substr(trim((string)($data['message'] ?? '')), 0, 1000);

if (!$bidId)          reply(['success' => false, 'message' => 'Нет bid_id']);
if ($message === '')  reply(['success' => false, 'message' => 'Напишите пару слов о себе']);

$db  = new Database();
$pdo = $db->connect('desl4t');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** Есть ли колонка. Дешевле, чем ловить исключение уже внутри транзакции. */
function column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $k = "$table.$column";
    if (isset($cache[$k])) return $cache[$k];
    $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $st->execute([$table, $column]);
    return $cache[$k] = (bool)$st->fetchColumn();
}

try {
    $st = $pdo->prepare("SELECT id, stage, bidder_id FROM bids WHERE id = ? LIMIT 1");
    $st->execute([$bidId]);
    $bid = $st->fetch(PDO::FETCH_ASSOC);

    if (!$bid)                                 reply(['success' => false, 'message' => 'Заявка не найдена']);
    if ($bid['stage'] !== 'active')            reply(['success' => false, 'message' => 'Заявка закрыта']);
    if ((int)$bid['bidder_id'] === $userId)    reply(['success' => false, 'message' => 'Это ваша заявка']);

    $chk = $pdo->prepare("SELECT id FROM responds WHERE bid_id = ? AND user_id = ? LIMIT 1");
    $chk->execute([$bidId, $userId]);
    if ($chk->fetchColumn()) reply(['success' => false, 'message' => 'Вы уже откликались на эту заявку']);

    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO responds (bid_id, user_id, message, status, created_at)
                   VALUES (?, ?, ?, 'ожидает', NOW())")
        ->execute([$bidId, $userId, $message]);
    $respondId = (int)$pdo->lastInsertId();

    if (column_exists($pdo, 'bids', 'responses')) {
        $pdo->prepare("UPDATE bids SET responses = responses + 1 WHERE id = ?")->execute([$bidId]);
    }

    $pdo->commit();

    reply(['success' => true, 'respond_id' => $respondId, 'message' => 'Отклик отправлен']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[l4t/respond_bid] ' . $e->getMessage());
    reply(['success' => false, 'message' => 'Не удалось отправить отклик']);
}