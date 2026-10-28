<?php
/**
 * finv2/create_payment_game.php — покупка игры. POST JSON {game_id} → {payment_id, payment_url, order_id}.
 * Платёж создаётся через агрегатор разработчика (ИП/ООО) или магазин платформы (самозанятый),
 * цена считается с инфраструктурным сбором и налоговой надбавкой — см. swad/controllers/mon/pay.php.
 */
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/mon/pay.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

function cpg_out(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

if (empty($_SESSION['USERDATA']['id'])) cpg_out(['error' => 'Необходима авторизация'], 401);
$userId    = (int)$_SESSION['USERDATA']['id'];
$userEmail = (string)($_SESSION['USERDATA']['email'] ?? '');

$body   = json_decode(file_get_contents('php://input'), true);
$gameId = (int)($body['game_id'] ?? 0);
if ($gameId <= 0) cpg_out(['error' => 'Некорректный game_id'], 400);

$pdo = (new Database())->connect();

$stmt = $pdo->prepare("SELECT id FROM library WHERE player_id = ? AND game_id = ?");
$stmt->execute([$userId, $gameId]);
if ($stmt->fetch()) cpg_out(['error' => 'Игра уже есть в вашей библиотеке'], 409);

if ($userEmail === '') cpg_out(['error' => 'Email обязателен. Укажите его в профиле.'], 422);

try {
    cpg_out(mon_game_checkout($pdo, $gameId, $userId, $userEmail));
} catch (MonPayError $e) {
    cpg_out(['error' => $e->getMessage()], $e->getCode() ?: 400);
}
