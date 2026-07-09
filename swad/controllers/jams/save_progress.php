<?php
/**
 * swad/controllers/jams/save_progress.php
 * POST JSON: { sprint_id, percent (0-100), stage, engine }
 *
 * Права:
 *   - участник в команде → редактирует прогресс КОМАНДЫ, только если капитан;
 *   - соло-участник (нет строки в team_members) → редактирует свой прогресс.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/phase_lib.php');
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function out(bool $ok, string $msg = '', array $extra = []): never {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = (int)($_SESSION['USERDATA']['id'] ?? 0);
if (!$uid) out(false, 'Не авторизованы');

$data     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sprintId = (int)($data['sprint_id'] ?? 0);
$percent  = max(0, min(100, (int)($data['percent'] ?? 0)));
$stage    = (string)($data['stage'] ?? 'idea');
$engine   = mb_substr(trim((string)($data['engine'] ?? '')), 0, 64);

if (!$sprintId) out(false, 'Нет спринта');
if (!array_key_exists($stage, jam_dev_stages())) out(false, 'Неизвестная стадия');

$pdo = (new Database())->connect();

/* Зарегистрирован ли в спринте */
$chk = $pdo->prepare("SELECT 1 FROM sprint_participants WHERE sprint_id = ? AND user_id = ?");
$chk->execute([$sprintId, $uid]);
if (!$chk->fetch()) out(false, 'Вы не участвуете в этом спринте');

/* В команде? */
$tm = $pdo->prepare("
    SELECT t.id AS team_id, t.captain_id
    FROM team_members tm
    JOIN sprint_teams t ON t.id = tm.team_id
    WHERE tm.sprint_id = ? AND tm.user_id = ?
    LIMIT 1
");
$tm->execute([$sprintId, $uid]);
$team = $tm->fetch(PDO::FETCH_ASSOC);

try {
    if ($team) {
        if ((int)$team['captain_id'] !== $uid) out(false, 'Прогресс команды редактирует капитан');
        $pdo->prepare("
            INSERT INTO sprint_progress (sprint_id, team_id, percent, stage, engine)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE percent = VALUES(percent), stage = VALUES(stage), engine = VALUES(engine)
        ")->execute([$sprintId, (int)$team['team_id'], $percent, $stage, $engine ?: null]);
    } else {
        $pdo->prepare("
            INSERT INTO sprint_progress (sprint_id, user_id, percent, stage, engine)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE percent = VALUES(percent), stage = VALUES(stage), engine = VALUES(engine)
        ")->execute([$sprintId, $uid, $percent, $stage, $engine ?: null]);
    }
} catch (Throwable $e) {
    error_log('save_progress: ' . $e->getMessage());
    out(false, 'Ошибка сохранения (миграция sprint_progress накатана?)');
}

out(true, 'Прогресс обновлён', ['percent' => $percent, 'stage' => $stage, 'engine' => $engine]);