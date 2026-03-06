<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['USERDATA']) || ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] != 3)) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

$db  = new Database();
$pdo = $db->connect();

$action      = $_POST['action'] ?? $_GET['action'] ?? '';
$currentMonth = date('Y-m');
$SLOTS        = 10; // мест в месяц — меняй здесь

switch ($action) {

    // ── Добавить эксперта в очередь текущего месяца ──
    case 'enqueue':
        $expertId = (int)($_POST['expert_id'] ?? 0);
        if (!$expertId) {
            echo json_encode(['error' => 'Нет ID']);
            exit;
        }

        // Только одобренные эксперты
        $stmt = $pdo->prepare("SELECT id FROM experts WHERE id = ? AND status = 'approved'");
        $stmt->execute([$expertId]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Эксперт не одобрен']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO expert_monthly (expert_id, month, status)
                VALUES (?, ?, 'queued')
            ");
            $stmt->execute([$expertId, $currentMonth]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Уже в очереди этого месяца']);
        }
        break;

    // ── Убрать из очереди ──
    case 'dequeue':
        $expertId = (int)($_POST['expert_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM expert_monthly WHERE expert_id = ? AND month = ?");
        $stmt->execute([$expertId, $currentMonth]);
        echo json_encode(['success' => true]);
        break;

    // ── Запустить рандом и выбрать активных ──
    case 'roll':
        // Сбрасываем предыдущий результат этого месяца
        $stmt = $pdo->prepare("UPDATE expert_monthly SET status = 'queued' WHERE month = ?");
        $stmt->execute([$currentMonth]);

        // Берём всех из очереди в случайном порядке
        $stmt = $pdo->prepare("
            SELECT id FROM expert_monthly
            WHERE month = ? AND status = 'queued'
            ORDER BY RAND()
        ");
        $stmt->execute([$currentMonth]);
        $all = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $winners = array_slice($all, 0, $SLOTS);
        $losers  = array_slice($all, $SLOTS);

        if ($winners) {
            $in = implode(',', array_fill(0, count($winners), '?'));
            $stmt = $pdo->prepare("UPDATE expert_monthly SET status = 'active'  WHERE id IN ($in)");
            $stmt->execute($winners);
        }
        if ($losers) {
            $in = implode(',', array_fill(0, count($losers), '?'));
            $stmt = $pdo->prepare("UPDATE expert_monthly SET status = 'skipped' WHERE id IN ($in)");
            $stmt->execute($losers);
        }

        echo json_encode([
            'success' => true,
            'active'  => count($winners),
            'skipped' => count($losers),
        ]);
        break;

    // ── Получить данные текущего месяца (для вкладки) ──
    case 'get':
    default:
        $stmt = $pdo->prepare("
            SELECT em.id AS row_id, em.expert_id, em.status,
                   u.username, u.first_name, u.last_name, u.email,
                   e.rating, e.votes_count
            FROM expert_monthly em
            JOIN experts e ON em.expert_id = e.id
            JOIN users   u ON e.user_id    = u.id
            WHERE em.month = ?
            ORDER BY em.status ASC, em.created_at ASC
        ");
        $stmt->execute([$currentMonth]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Все одобренные эксперты, которых ещё нет в очереди
        $stmt = $pdo->prepare("
            SELECT e.id AS expert_id, u.username, u.first_name, u.last_name, u.email
            FROM experts e
            JOIN users u ON e.user_id = u.id
            WHERE e.status = 'approved'
              AND e.id NOT IN (
                SELECT expert_id FROM expert_monthly WHERE month = ?
              )
            ORDER BY u.username
        ");
        $stmt->execute([$currentMonth]);
        $available = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'month'     => $currentMonth,
            'slots'     => $SLOTS,
            'queue'     => $rows,
            'available' => $available,
        ]);
        break;
}

