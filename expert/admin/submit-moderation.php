<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

/* ── Email-уведомление разработчику ───────────────────────────────────── */
function notifyDeveloper(PDO $pdo, int $gameId, string $type, array $data = []): void
{
    // Получаем email владельца студии
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
        $score    = (int)($data['score'] ?? 0);
        $positive = $score > 51;
        $verdict  = $positive ? '👍 За публикацию' : '👎 Против публикации';
        $color    = $positive ? '#4ade80' : '#f87171';
        $subject  = "Новая оценка эксперта — «{$dev['game_name']}»";
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
                        <div style='font-size:40px;font-weight:800;color:{$color};font-family:monospace;'>{$score}</div>
                        <div>
                            <div style='font-size:13px;color:#6b7a99;'>из 100</div>
                            <div style='font-size:14px;font-weight:600;color:{$color};margin-top:4px;'>{$verdict}</div>
                        </div>
                    </div>
                </div>

                <div style='font-size:13px;color:#6b7a99;margin-bottom:20px;'>
                    Текст рецензии будет раскрыт после завершения голосования всех экспертов.
                </div>

                <div style='font-size:12px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);border-radius:8px;padding:12px 14px;color:#fbbf24;'>
                    Текущий прогресс: {$data['positive']} за · {$data['negative']} против · нужно {$data['need']} голосов «за»
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
                <div style='margin-top:20px;font-size:13px;color:#6b7a99;text-align:center;'>
                    Теперь вы можете опубликовать игру в каталоге Dustore
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
                    Не расстраивайтесь — вы можете доработать игру и отправить её на повторную модерацию.<br>
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
    } else {
        return;
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Dustore <noreply@dustore.ru>\r\n";
    $headers .= "Reply-To: noreply@dustore.ru\r\n";
    @mail($dev['email'], $subject, $body, $headers);
}

/* ── Логика модерации ──────────────────────────────────────────────────── */
function checkModeration(PDO $pdo, int $gameId): void
{
    $stmt = $pdo->prepare("SELECT moderation_status FROM games WHERE id=?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    if (!$game || $game['moderation_status'] !== 'pending') return;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total, SUM(score > 51) AS positive, SUM(score <= 51) AS negative
        FROM moderation_reviews WHERE game_id = ?
    ");
    $stmt->execute([$gameId]);
    $votes = $stmt->fetch();

    $totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
    $needVotes    = max(1, (int)ceil($totalExperts * 0.51));
    $positive     = (int)($votes['positive'] ?? 0);
    $total        = (int)($votes['total']    ?? 0);
    $remaining    = $totalExperts - $total;

    if ($positive >= $needVotes) {
        $pdo->prepare("UPDATE games SET moderation_status='approved', updated_at=NOW() WHERE id=?")
            ->execute([$gameId]);
        notifyDeveloper($pdo, $gameId, 'approved');
        return;
    }

    if (($positive + $remaining) < $needVotes) {
        $pdo->prepare("UPDATE games SET moderation_status='rejected', updated_at=NOW() WHERE id=?")
            ->execute([$gameId]);
        notifyDeveloper($pdo, $gameId, 'rejected');
        return;
    }
}

/* ── Основной код ──────────────────────────────────────────────────────── */
$db  = new Database();
$pdo = $db->connect();

$gameId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['USERDATA']['id'];

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id=? AND status='approved'");
$stmt->execute([$userId]);
$expert = $stmt->fetch();
if (!$expert) die('no access');
$expertId = $expert['id'];

$score          = (int)($_POST['score']          ?? 0);
$comment        = $_POST['review']               ?? '';
$gameplay_score = (int)($_POST['gameplay_score'] ?? 0);
$visual_score   = (int)($_POST['visual_score']   ?? 0);
$stability      = (int)($_POST['stability']      ?? 0);
$originality    = (int)($_POST['originality']    ?? 0);
$sound_score    = (int)($_POST['sound_score']    ?? 0);
$content_depth  = (int)($_POST['content_depth']  ?? 0);

// Сохраняем/обновляем рецензию
$pdo->prepare("
    INSERT INTO moderation_reviews
        (game_id, expert_id, score, comment, gameplay_score, visual_score, stability, originality, sound_score, content_depth)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        score=VALUES(score), comment=VALUES(comment),
        gameplay_score=VALUES(gameplay_score), visual_score=VALUES(visual_score),
        stability=VALUES(stability), originality=VALUES(originality),
        sound_score=VALUES(sound_score), content_depth=VALUES(content_depth)
")->execute([$gameId, $expertId, $score, $comment, $gameplay_score, $visual_score, $stability, $originality, $sound_score, $content_depth]);

// Пересчитываем GQI после каждого голоса
recalcGQI($pdo, $gameId);

// Уведомляем разработчика о новом голосе
$totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
$needVotes    = max(1, (int)ceil($totalExperts * 0.51));
$stmt = $pdo->prepare("SELECT SUM(score>51) AS positive, SUM(score<=51) AS negative FROM moderation_reviews WHERE game_id=?");
$stmt->execute([$gameId]);
$vv = $stmt->fetch();
notifyDeveloper($pdo, $gameId, 'vote', [
    'score'    => $score,
    'positive' => (int)($vv['positive'] ?? 0),
    'negative' => (int)($vv['negative'] ?? 0),
    'need'     => $needVotes,
]);

checkModeration($pdo, $gameId);

header("Location: moderation-game?id=" . $gameId);
exit;

/* ── Пересчёт GQI ──────────────────────────────────────────────────────── */
function recalcGQI(PDO $pdo, int $gameId): void
{
    $stmt = $pdo->prepare("
        SELECT
            AVG(score)          AS avg_score,
            AVG(gameplay_score) AS avg_gameplay,
            AVG(visual_score)   AS avg_visual,
            AVG(stability)      AS avg_stability,
            AVG(originality)    AS avg_originality,
            AVG(sound_score)    AS avg_sound,
            AVG(content_depth)  AS avg_content,
            COUNT(*)            AS cnt
        FROM moderation_reviews
        WHERE game_id = ?
    ");
    $stmt->execute([$gameId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r || $r['cnt'] == 0) return;

    // Веса: общая оценка 40%, геймплей 20%, визуал 12%, стабильность 12%, оригинальность 6%, звук 5%, глубина 5%
    // Подкритерии на шкале 1-10, нормируем к 100
    $gqi = round(
        $r['avg_score']      * 0.40 +
        ($r['avg_gameplay']  / 10 * 100) * 0.20 +
        ($r['avg_visual']    / 10 * 100) * 0.12 +
        ($r['avg_stability'] / 10 * 100) * 0.12 +
        ($r['avg_originality']/10 * 100) * 0.06 +
        ($r['avg_sound']     / 10 * 100) * 0.05 +
        ($r['avg_content']   / 10 * 100) * 0.05
    );

    $pdo->prepare("UPDATE games SET GQI=?, updated_at=NOW() WHERE id=?")->execute([min(100, max(0, $gqi)), $gameId]);
}