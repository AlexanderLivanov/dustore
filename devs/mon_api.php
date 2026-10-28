<?php
/**
 * devs/mon_api.php — JSON для страницы монетизации: тестовая оплата при подключении агрегатора.
 * POST JSON {action: test_create | test_status, id?}, заголовок X-CSRF-Token. Только владелец студии.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/csrf.php';
require_once __DIR__ . '/../swad/controllers/mon/pay.php';

header('Content-Type: application/json; charset=utf-8');
function ma_out(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit(); }

$studio = (int)($_SESSION['studio_id'] ?? 0);
if (empty($_SESSION['USERDATA']) || !$studio) ma_out(['ok' => false, 'error' => 'Нет сессии — обновите страницу'], 401);
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (!csrf_valid($in)) ma_out(['ok' => false, 'error' => 'Сессия устарела — обновите страницу'], 403);

$db = (new Database())->connect();
mon_schema($db);
$r = $db->prepare("SELECT role FROM staff WHERE telegram_id = ? AND org_id = ? LIMIT 1");
$r->execute([(string)($_SESSION['USERDATA']['telegram_id'] ?? ''), $studio]);
if ($r->fetchColumn() !== 'Владелец' && (int)($_SESSION['USERDATA']['global_role'] ?? 0) !== -1) ma_out(['ok' => false, 'error' => 'Только владелец студии'], 403);

$p = mon_profile($db, $studio);
if ($p['status'] !== 'active' || $p['legal_type'] === 'npd') ma_out(['ok' => false, 'error' => 'Агрегатор подключается после одобрения анкеты ИП или ООО'], 409);

switch ($in['action'] ?? '') {
    case 'test_create':
        if (empty($p['agg_test'])) ma_out(['ok' => false, 'error' => 'Сначала сохраните тестовые ключи'], 409);
        try {
            ma_out(['ok' => true] + mon_test_checkout($db, $studio));
        } catch (MonPayError $e) {
            ma_out(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    case 'test_status':
        $row = mon_payment_row($db, (int)($in['id'] ?? 0));
        if (!$row || (int)$row['studio_id'] !== $studio || $row['purpose'] !== 'agg_test') ma_out(['ok' => false, 'error' => 'Платёж не найден'], 404);
        ma_out(['ok' => true, 'status' => mon_sync($db, $row)]);
}
ma_out(['ok' => false, 'error' => 'Неизвестное действие'], 400);
