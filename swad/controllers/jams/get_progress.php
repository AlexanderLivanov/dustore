<?php
/**
 * swad/controllers/jams/get_progress.php
 * GET ?sprint_id=N
 * Таблица прогресса: ВСЕ команды + ВСЕ соло-участники спринта,
 * LEFT JOIN на sprint_progress (кто не заполнял — 0% / «Идея» / —).
 *
 * Соло = есть в sprint_participants, нет ни в одной команде спринта
 * (participant_type ненадёжен — та же логика, что в deck.php).
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/phase_lib.php');
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$sprintId = (int)($_GET['sprint_id'] ?? 0);
if (!$sprintId) { echo json_encode(['success' => false, 'message' => 'Нет спринта']); exit; }

$pdo    = (new Database())->connect();
$stages = jam_dev_stages();
$rows   = [];

try {
    /* Команды */
    $t = $pdo->prepare("
        SELECT t.id, t.team_name,
               (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) AS members,
               p.percent, p.stage, p.engine, p.updated_at
        FROM sprint_teams t
        LEFT JOIN sprint_progress p ON p.sprint_id = t.sprint_id AND p.team_id = t.id
        WHERE t.sprint_id = ?
        ORDER BY COALESCE(p.percent, 0) DESC, t.team_name
    ");
    $t->execute([$sprintId]);
    foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'type'        => 'team',
            'name'        => $r['team_name'],
            'members'     => (int)$r['members'],
            'percent'     => (int)($r['percent'] ?? 0),
            'stage'       => $r['stage'] ?? 'idea',
            'stage_label' => $stages[$r['stage'] ?? 'idea'],
            'engine'      => $r['engine'] ?: null,
            'updated_at'  => $r['updated_at'],
        ];
    }

    /* Соло: участник без команды */
    $s = $pdo->prepare("
        SELECT sp.user_id, COALESCE(NULLIF(sp.alias, ''), u.username) AS name,
               p.percent, p.stage, p.engine, p.updated_at
        FROM sprint_participants sp
        JOIN users u ON u.id = sp.user_id
        LEFT JOIN sprint_progress p ON p.sprint_id = sp.sprint_id AND p.user_id = sp.user_id
        WHERE sp.sprint_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM team_members tm
              WHERE tm.sprint_id = sp.sprint_id AND tm.user_id = sp.user_id
          )
        ORDER BY COALESCE(p.percent, 0) DESC, name
    ");
    $s->execute([$sprintId]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'type'        => 'solo',
            'name'        => $r['name'],
            'members'     => 1,
            'percent'     => (int)($r['percent'] ?? 0),
            'stage'       => $r['stage'] ?? 'idea',
            'stage_label' => $stages[$r['stage'] ?? 'idea'],
            'engine'      => $r['engine'] ?: null,
            'updated_at'  => $r['updated_at'],
        ];
    }
} catch (Throwable $e) {
    error_log('get_progress: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка (миграция sprint_progress накатана?)']);
    exit;
}

/* Общая сортировка: по проценту убыв., команды при равенстве выше */
usort($rows, fn($a, $b) => [$b['percent'], $b['type'] === 'team'] <=> [$a['percent'], $a['type'] === 'team']);

echo json_encode(['success' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);