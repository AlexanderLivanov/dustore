<?php
// swad/controllers/auto_moderation.php
// КРОН: авто-разбор игр, висящих на модерации > 48 часов.
//
// Правило:
//   • есть хотя бы один голос «за» (verdict='recommend' ИЛИ score > 51) → APPROVED
//   • нет ни одного «за» за 48 ч (ноль голосов, только «против»,
//     только «на доработку», любая смесь)                            → возврат на доработку
//
// Так игра НИКОГДА не зависает в pending: после 48 ч она всегда уходит
// либо в approved, либо обратно разработчику.
//
// Запуск:
//   CLI:  php /path/to/swad/controllers/auto_moderation.php
//   HTTP: https://dustore.ru/swad/controllers/auto_moderation.php?key=СЕКРЕТ

require_once __DIR__ . '/../config.php';
@require_once __DIR__ . '/tg_bot.php'; // необязательное уведомление

const AUTO_MOD_HOURS           = 48;                       // часов на модерации
const AUTO_MOD_TOKEN           = 'change-me-cron-secret';  // токен для HTTP-запуска (смени!)
const AUTO_MOD_APPROVED_STATUS = 'approved';               // статус одобрения
const AUTO_MOD_REVISION_STATUS = 'revision';               // ⚠ ПОДТВЕРДИ по enum: SHOW COLUMNS FROM games LIKE 'moderation_status';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    if (($_GET['key'] ?? '') !== AUTO_MOD_TOKEN) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
        exit;
    }
}

$pdo = (new Database())->connect();

// Предикат «за» — используем в обеих ветках, чтобы условия были строго зеркальны.
$hasFor = "EXISTS (
    SELECT 1 FROM moderation_reviews mr
    WHERE mr.game_id = g.id AND (mr.verdict = 'recommend' OR mr.score > 51)
)";
// Предикат «висит дольше N часов».
$overdue = "COALESCE(g.moderation_submitted_at, g.created_at) <= (NOW() - INTERVAL :hrs HOUR)";

// ── 1. Кандидаты на АВТО-ОДОБРЕНИЕ: overdue + есть «за» ─────────────────────
$stApprove = $pdo->prepare("
    SELECT g.id, g.name, g.developer
    FROM games g
    WHERE g.moderation_status = 'pending'
      AND {$overdue}
      AND {$hasFor}
");
$stApprove->bindValue(':hrs', AUTO_MOD_HOURS, PDO::PARAM_INT);
$stApprove->execute();
$toApprove = $stApprove->fetchAll(PDO::FETCH_ASSOC);

// ── 2. Кандидаты на ВОЗВРАТ НА ДОРАБОТКУ: overdue + НЕТ «за» ────────────────
$stRevise = $pdo->prepare("
    SELECT g.id, g.name, g.developer
    FROM games g
    WHERE g.moderation_status = 'pending'
      AND {$overdue}
      AND NOT {$hasFor}
");
$stRevise->bindValue(':hrs', AUTO_MOD_HOURS, PDO::PARAM_INT);
$stRevise->execute();
$toRevise = $stRevise->fetchAll(PDO::FETCH_ASSOC);

// ── Применяем изменения (атомарный guard по moderation_status) ──────────────
$approved = [];
$updApprove = $pdo->prepare(
    "UPDATE games SET moderation_status = '" . AUTO_MOD_APPROVED_STATUS . "', updated_at = NOW()
     WHERE id = ? AND moderation_status = 'pending'"
);
foreach ($toApprove as $g) {
    $updApprove->execute([(int)$g['id']]);
    if ($updApprove->rowCount() > 0) {
        $approved[] = $g;
        auto_mod_notify($pdo, (int)$g['developer'], $g['name'], 'approved');
    }
}

$revised = [];
$updRevise = $pdo->prepare(
    "UPDATE games SET moderation_status = '" . AUTO_MOD_REVISION_STATUS . "', updated_at = NOW()
     WHERE id = ? AND moderation_status = 'pending'"
);
foreach ($toRevise as $g) {
    $updRevise->execute([(int)$g['id']]);
    if ($updRevise->rowCount() > 0) {
        $revised[] = $g;
        auto_mod_notify($pdo, (int)$g['developer'], $g['name'], 'revision');
    }
}

// ── Лог + уведомление в группу экспертов ───────────────────────────────────
$nA = count($approved);
$nR = count($revised);

if (($nA > 0 || $nR > 0) && function_exists('send_group_message')) {
    $parts = [];
    if ($nA > 0) {
        $names = implode(', ', array_map(fn($g) => $g['name'], array_slice($approved, 0, 10)));
        $parts[] = "✅ одобрено — {$nA} (" . htmlspecialchars($names) . ")";
    }
    if ($nR > 0) {
        $names = implode(', ', array_map(fn($g) => $g['name'], array_slice($revised, 0, 10)));
        $parts[] = "↩️ на доработку — {$nR} (" . htmlspecialchars($names) . ")";
    }
    @send_group_message(
        -1002916906978,
        "🤖 <b>Авто-модерация:</b> " . implode('; ', $parts),
        true,
        'https://dustore.ru/expert/admin/moderation'
    );
}
error_log("[auto_moderation] approved {$nA}, sent-to-revision {$nR} (>" . AUTO_MOD_HOURS . "h)");

// ── Ответ ──────────────────────────────────────────────────────────────────
$out = [
    'success'  => true,
    'approved' => $nA,
    'revised'  => $nR,
    'games'    => [
        'approved' => array_map(fn($g) => ['id' => (int)$g['id'], 'name' => $g['name']], $approved),
        'revised'  => array_map(fn($g) => ['id' => (int)$g['id'], 'name' => $g['name']], $revised),
    ],
];
if ($isCli) {
    echo "auto_moderation: одобрено {$nA}, на доработку {$nR}\n";
} else {
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
}

/**
 * Письмо разработчику (best-effort). $type: 'approved' | 'revision'.
 */
function auto_mod_notify(PDO $pdo, int $studioId, string $gameName, string $type): void
{
    $email = null;
    try {
        $q = $pdo->prepare("SELECT contact_email FROM studios WHERE id = ? LIMIT 1");
        $q->execute([$studioId]);
        $email = $q->fetchColumn() ?: null;
    } catch (Throwable $e) { return; }
    if (!$email) return;

    $safeName = htmlspecialchars($gameName);

    if ($type === 'approved') {
        $subject = '✅ Игра одобрена';
        $body = "<p>Ваша игра «{$safeName}» автоматически прошла модерацию "
              . "(находилась на проверке дольше " . AUTO_MOD_HOURS . " часов и получила положительные оценки).</p>"
              . "<p><a href='https://dustore.ru/devs/projects'>Открыть консоль разработчика</a></p>";
    } else { // revision
        $subject = '↩️ Игра возвращена на доработку';
        $body = "<p>Ваша игра «{$safeName}» возвращена на доработку: за " . AUTO_MOD_HOURS
              . " часов на модерации она не получила ни одной положительной оценки от экспертов.</p>"
              . "<p>Ознакомьтесь с замечаниями рецензентов, внесите правки и отправьте игру на повторную модерацию.</p>"
              . "<p><a href='https://dustore.ru/devs/projects'>Открыть консоль разработчика</a></p>";
    }

    $html = "<div style='font-family:sans-serif;'><h2>{$subject}</h2>{$body}</div>";

    if (function_exists('send_email')) { @send_email($email, $subject, $html); return; }
    if (function_exists('sendMail'))   { @sendMail($email, $subject, $html);  return; }
    if (function_exists('send_mail'))  { @send_mail($email, $subject, $html);  return; }
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: Dustore <no-reply@dustore.ru>\r\n";
    @mail($email, $subject, $html, $headers);
}