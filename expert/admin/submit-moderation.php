<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

/* ── Email-уведомление разработчику ───────────────────────────────────── */
function notifyDeveloper(PDO $pdo, int $gameId, string $type, array $data = []): void
{
    // staff.telegram_id → users.telegram_id (uid часто NULL, поэтому join по telegram_id)
    $stmt = $pdo->prepare("
        SELECT u.email, u.username, g.name AS game_name
        FROM games g
        JOIN studios s ON s.id = g.developer
        JOIN staff st ON st.org_id = s.id AND st.role = 'Владелец'
        JOIN users u ON u.telegram_id = st.telegram_id
        WHERE g.id = ?
        LIMIT 1
    ");
    $stmt->execute([$gameId]);
    $dev = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dev || empty($dev['email'])) return;

    $gameName = htmlspecialchars($dev['game_name']);
    $devName  = htmlspecialchars($dev['username']);

    if ($type === 'vote') {
        $score    = (int)($data['score']   ?? 0);
        $verdict  = $data['verdict']       ?? 'reject';
        $positive = $data['positive']      ?? 0;
        $negative = $data['negative']      ?? 0;
        $need     = $data['need']          ?? 1;

        $vLabels = ['recommend' => '👍 Рекомендую к публикации', 'revision' => '🔄 Вернуть на доработку', 'reject' => '👎 Не рекомендую'];
        $vColors = ['recommend' => '#4ade80', 'revision' => '#fbbf24', 'reject' => '#f87171'];
        $vLabel  = $vLabels[$verdict] ?? $vLabels['reject'];
        $vColor  = $vColors[$verdict] ?? $vColors['reject'];

        $subject = "Новая оценка эксперта — «{$dev['game_name']}»";
        $body = "
        <div style='font-family:sans-serif;max-width:560px;margin:0 auto;background:#0b0e13;color:#e8edf5;border-radius:12px;overflow:hidden;border:1px solid #232b3a;'>
            <div style='background:linear-gradient(135deg,#131720,#1a2030);padding:28px 32px;border-bottom:1px solid #232b3a;'>
                <div style='font-size:22px;font-weight:800;color:#4ade80;letter-spacing:-.5px;'>Dustore</div>
                <div style='font-size:13px;color:#6b7a99;margin-top:4px;'>Уведомление о модерации</div>
            </div>
            <div style='padding:28px 32px;'>
                <div style='font-size:18px;font-weight:700;margin-bottom:8px;'>Эксперт оценил вашу игру</div>
                <div style='font-size:14px;color:#6b7a99;margin-bottom:24px;'>Игра: <b style='color:#e8edf5;'>{$gameName}</b></div>
                <div style='background:#131720;border:1px solid #232b3a;border-radius:10px;padding:20px;margin-bottom:20px;'>
                    <div style='display:flex;align-items:center;gap:16px;'>
                        <div style='font-size:40px;font-weight:800;color:{$vColor};font-family:monospace;'>{$score}</div>
                        <div>
                            <div style='font-size:13px;color:#6b7a99;'>из 100</div>
                            <div style='font-size:14px;font-weight:600;color:{$vColor};margin-top:4px;'>{$vLabel}</div>
                        </div>
                    </div>
                </div>
                <div style='font-size:12px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);border-radius:8px;padding:12px 14px;color:#fbbf24;'>
                    Текущий прогресс: {$positive} за · {$negative} против · нужно {$need} голосов «за»
                </div>
                <div style='margin-top:16px;font-size:13px;color:#6b7a99;'>
                    Текст рецензии будет раскрыт после завершения голосования всех экспертов.
                </div>
            </div>
            <div style='padding:16px 32px;border-top:1px solid #232b3a;font-size:12px;color:#6b7a99;'>
                <a href='https://dustore.ru/devs/expert_reviews?game_id={$gameId}' style='color:#4ade80;text-decoration:none;'>Посмотреть все рецензии →</a>
            </div>
        </div>";
    } elseif ($type === 'approved') {
        $subject = "🎉 Игра «{$dev['game_name']}» прошла модерацию!";
        $body = "
        <div style='font-family:sans-serif;max-width:560px;margin:0 auto;background:#0b0e13;color:#e8edf5;border-radius:12px;overflow:hidden;border:1px solid #232b3a;'>
            <div style='background:linear-gradient(135deg,#131720,#1a2030);padding:28px 32px;border-bottom:1px solid #232b3a;'>
                <div style='font-size:22px;font-weight:800;color:#4ade80;letter-spacing:-.5px;'>Dustore</div>
            </div>
            <div style='padding:28px 32px;'>
                <div style='font-size:36px;text-align:center;margin-bottom:16px;'>🎉</div>
                <div style='font-size:20px;font-weight:700;text-align:center;margin-bottom:8px;'>Поздравляем, {$devName}!</div>
                <div style='font-size:15px;color:#6b7a99;text-align:center;margin-bottom:24px;'>
                    Игра <b style='color:#4ade80;'>{$gameName}</b> успешно прошла модерацию
                </div>
                <div style='text-align:center;'>
                    <a href='https://dustore.ru/devs/edit?id={$gameId}'
                       style='display:inline-block;background:#4ade80;color:#0b0e13;font-weight:700;
                              padding:13px 28px;border-radius:10px;text-decoration:none;font-size:15px;'>
                        Опубликовать игру →
                    </a>
                </div>
            </div>
        </div>";
    } elseif ($type === 'rejected') {
        $subject = "Игра «{$dev['game_name']}» не прошла модерацию";
        $body = "
        <div style='font-family:sans-serif;max-width:560px;margin:0 auto;background:#0b0e13;color:#e8edf5;border-radius:12px;overflow:hidden;border:1px solid #232b3a;'>
            <div style='background:linear-gradient(135deg,#131720,#1a2030);padding:28px 32px;border-bottom:1px solid #232b3a;'>
                <div style='font-size:22px;font-weight:800;color:#4ade80;letter-spacing:-.5px;'>Dustore</div>
            </div>
            <div style='padding:28px 32px;'>
                <div style='font-size:36px;text-align:center;margin-bottom:16px;'>😔</div>
                <div style='font-size:20px;font-weight:700;text-align:center;margin-bottom:8px;'>Игра не прошла модерацию</div>
                <div style='font-size:15px;color:#6b7a99;text-align:center;margin-bottom:24px;'>
                    <b style='color:#e8edf5;'>{$gameName}</b> не набрала достаточно положительных оценок
                </div>
                <div style='background:#131720;border:1px solid #232b3a;border-radius:10px;padding:16px;margin-bottom:20px;font-size:13px;color:#6b7a99;line-height:1.6;'>
                    Не расстраивайтесь — вы можете доработать игру и отправить её на повторную модерацию.
                    Рекомендуем изучить рецензии экспертов и устранить замечания.
                </div>
                <div style='text-align:center;'>
                    <a href='https://dustore.ru/devs/edit?id={$gameId}'
                       style='display:inline-block;background:#1a2030;color:#e8edf5;font-weight:600;
                              border:1px solid #232b3a;padding:12px 24px;border-radius:10px;text-decoration:none;font-size:14px;'>
                        Доработать и отправить повторно →
                    </a>
                </div>
            </div>
            <div style='padding:16px 32px;border-top:1px solid #232b3a;font-size:12px;color:#6b7a99;'>
                <a href='https://dustore.ru/devs/expert_reviews?game_id={$gameId}' style='color:#22d3ee;text-decoration:none;'>Посмотреть рецензии экспертов →</a>
            </div>
        </div>";
    } elseif ($type === 'revision') {
        $subject = "📝 Игра «{$dev['game_name']}» — эксперты рекомендуют доработку";
        $body = "
        <div style='font-family:sans-serif;max-width:560px;margin:0 auto;background:#0b0e13;color:#e8edf5;border-radius:12px;overflow:hidden;border:1px solid #232b3a;'>
            <div style='background:linear-gradient(135deg,#131720,#1a2030);padding:28px 32px;border-bottom:1px solid #232b3a;'>
                <div style='font-size:22px;font-weight:800;color:#4ade80;letter-spacing:-.5px;'>Dustore</div>
            </div>
            <div style='padding:28px 32px;'>
                <div style='font-size:36px;text-align:center;margin-bottom:16px;'>🔄</div>
                <div style='font-size:20px;font-weight:700;text-align:center;margin-bottom:8px;'>Нужна доработка</div>
                <div style='font-size:15px;color:#6b7a99;text-align:center;margin-bottom:24px;'>
                    Эксперты видят потенциал в <b style='color:#e8edf5;'>{$gameName}</b>, но считают что игра ещё не готова
                </div>
                <div style='background:#131720;border:1px solid rgba(251,191,36,.2);border-radius:10px;padding:16px;margin-bottom:20px;font-size:13px;color:#fbbf24;line-height:1.6;'>
                    Это не окончательный отказ. Изучите рецензии, устраните замечания и отправьте повторно.
                </div>
                <div style='text-align:center;'>
                    <a href='https://dustore.ru/devs/expert_reviews?game_id={$gameId}'
                       style='display:inline-block;background:#fbbf24;color:#0b0e13;font-weight:700;
                              padding:13px 28px;border-radius:10px;text-decoration:none;font-size:15px;'>
                        Читать рецензии →
                    </a>
                </div>
            </div>
        </div>";
    } else {
        return;
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Dustore <noreply@dustore.ru>\r\n";
    $headers .= "Reply-To: noreply@dustore.ru\r\n";
    @mail($dev['email'], $subject, $body, $headers);
}

/* ── Пересчёт GQI ──────────────────────────────────────────────────────── */
function recalcGQI(PDO $pdo, int $gameId): void
{
    // Строим SELECT динамически — только существующие колонки
    $existingCols = array_flip(
        $pdo->query("SHOW COLUMNS FROM moderation_reviews")->fetchAll(PDO::FETCH_COLUMN)
    );

    $critFields = ['gameplay_score', 'visual_score', 'stability', 'originality', 'sound_score', 'content_depth'];
    $critSelect = '';
    foreach ($critFields as $col) {
        $alias = str_replace('_score', '', $col);
        $alias = str_replace('content_depth', 'content', $alias);
        $alias = "avg_$col";
        $critSelect .= isset($existingCols[$col]) ? ", AVG($col) AS $alias" : ", NULL AS $alias";
    }

    $stmt = $pdo->prepare("
        SELECT AVG(score) AS avg_score, COUNT(*) AS cnt $critSelect
        FROM moderation_reviews WHERE game_id = ?
    ");
    $stmt->execute([$gameId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r || $r['cnt'] == 0) return;

    $gqi = round(
        ($r['avg_score']                  ?? 0) * 0.40 +
            (($r['avg_gameplay_score']        ?? 0) / 10 * 100) * 0.20 +
            (($r['avg_visual_score']          ?? 0) / 10 * 100) * 0.12 +
            (($r['avg_stability']             ?? 0) / 10 * 100) * 0.12 +
            (($r['avg_originality']           ?? 0) / 10 * 100) * 0.06 +
            (($r['avg_sound_score']           ?? 0) / 10 * 100) * 0.05 +
            (($r['avg_content_depth']         ?? 0) / 10 * 100) * 0.05
    );

    $pdo->prepare("UPDATE games SET GQI=?, updated_at=NOW() WHERE id=?")
        ->execute([min(100, max(0, $gqi)), $gameId]);
}

/* ── Логика модерации ──────────────────────────────────────────────────── */
function checkModeration(PDO $pdo, int $gameId): void
{
    $stmt = $pdo->prepare("SELECT moderation_status FROM games WHERE id=?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    if (!$game || $game['moderation_status'] !== 'pending') return;

    $stmt = $pdo->prepare("
        SELECT COUNT(*)                    AS total,
               SUM(score > 51)            AS positive,
               SUM(score <= 51)           AS negative,
               SUM(verdict = 'revision')  AS revision_count
        FROM moderation_reviews WHERE game_id = ?
    ");
    $stmt->execute([$gameId]);
    $votes = $stmt->fetch();

    $totalExperts  = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
    $needVotes     = max(1, (int)ceil($totalExperts * 0.51));
    $positive      = (int)($votes['positive']       ?? 0);
    $negative      = (int)($votes['negative']       ?? 0);
    $revisionCount = (int)($votes['revision_count'] ?? 0);
    $total         = (int)($votes['total']          ?? 0);
    $remaining     = $totalExperts - $total;

    if ($positive >= $needVotes) {
        $pdo->prepare("UPDATE games SET moderation_status='approved', updated_at=NOW() WHERE id=?")
            ->execute([$gameId]);
        notifyDeveloper($pdo, $gameId, 'approved');
        return;
    }

    if (($positive + $remaining) < $needVotes) {
        $pureReject = $negative - $revisionCount;
        if ($revisionCount > 0 && $revisionCount >= $pureReject) {
            $pdo->prepare("UPDATE games SET moderation_status='revision', updated_at=NOW() WHERE id=?")
                ->execute([$gameId]);
            notifyDeveloper($pdo, $gameId, 'revision');
        } else {
            $pdo->prepare("UPDATE games SET moderation_status='rejected', updated_at=NOW() WHERE id=?")
                ->execute([$gameId]);
            notifyDeveloper($pdo, $gameId, 'rejected');
        }
    }
}

/* ── Основной код ──────────────────────────────────────────────────────── */
$db  = new Database();
$pdo = $db->connect();

$gameId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$gameId) die('invalid game');

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id=? AND status='approved'");
$stmt->execute([$userId]);
$expert = $stmt->fetch();
if (!$expert) die('no access');
$expertId = $expert['id'];

$score          = (int)($_POST['score']          ?? 0);
$comment        = trim($_POST['review']          ?? '');
$gameplay_score = (int)($_POST['gameplay_score'] ?? 0) ?: null;
$visual_score   = (int)($_POST['visual_score']   ?? 0) ?: null;
$stability      = (int)($_POST['stability']      ?? 0) ?: null;
$originality    = (int)($_POST['originality']    ?? 0) ?: null;
$sound_score    = (int)($_POST['sound_score']    ?? 0) ?: null;
$content_depth  = (int)($_POST['content_depth']  ?? 0) ?: null;

$verdict = $_POST['verdict'] ?? '';
if (!in_array($verdict, ['recommend', 'revision', 'reject'])) die('invalid verdict');

if ($score < 0 || $score > 100)   die('invalid score');
if (mb_strlen($comment) < 40)     die('review too short');

// Корректируем score по вердикту
if (in_array($verdict, ['revision', 'reject']) && $score > 51) $score = 51;
if ($verdict === 'recommend' && $score < 52)                  $score = 52;

// Проверяем какие опциональные колонки реально существуют
$existingCols = array_flip(
    $pdo->query("SHOW COLUMNS FROM moderation_reviews")->fetchAll(PDO::FETCH_COLUMN)
);

$critMap = [
    'gameplay_score' => $gameplay_score,
    'visual_score'   => $visual_score,
    'stability'      => $stability,
    'originality'    => $originality,
    'sound_score'    => $sound_score,
    'content_depth'  => $content_depth,
];

// Оставляем только те колонки, которые есть в БД
$activeCrits = array_filter($critMap, fn($_, $k) => isset($existingCols[$k]), ARRAY_FILTER_USE_BOTH);

$baseCols   = ['game_id', 'expert_id', 'score', 'verdict', 'comment'];
$baseVals   = [$gameId, $expertId, $score, $verdict, $comment];
$critKeys   = array_keys($activeCrits);
$critVals   = array_values($activeCrits);

$allCols    = array_merge($baseCols, $critKeys);
$allVals    = array_merge($baseVals, $critVals);
$colList    = implode(', ', $allCols);
$phList     = implode(', ', array_fill(0, count($allVals), '?'));

$updateBase = "score=VALUES(score), verdict=VALUES(verdict), comment=VALUES(comment)";
$updateCrit = implode(', ', array_map(fn($k) => "$k=VALUES($k)", $critKeys));
$updateSql  = $updateBase . ($updateCrit ? ", $updateCrit" : '');

// Сохраняем/обновляем — ON DUPLICATE KEY требует уникального индекса (game_id, expert_id)
$pdo->prepare("
    INSERT INTO moderation_reviews ($colList)
    VALUES ($phList)
    ON DUPLICATE KEY UPDATE $updateSql
")->execute($allVals);

recalcGQI($pdo, $gameId);

$totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
$needVotes    = max(1, (int)ceil($totalExperts * 0.51));
$stmt = $pdo->prepare("SELECT SUM(score>51) AS positive, SUM(score<=51) AS negative FROM moderation_reviews WHERE game_id=?");
$stmt->execute([$gameId]);
$vv = $stmt->fetch();

notifyDeveloper($pdo, $gameId, 'vote', [
    'score'   => $score,
    'verdict' => $verdict,
    'positive' => (int)($vv['positive'] ?? 0),
    'negative' => (int)($vv['negative'] ?? 0),
    'need'    => $needVotes,
]);

checkModeration($pdo, $gameId);

header("Location: moderation-game?id=" . $gameId);
exit;
