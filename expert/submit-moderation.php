<?php
// expert/submit-moderation.php — приём оценки эксперта + пересчёт вердикта игры.
// Форма из moderation-game.php постит сюда: score, review, verdict, checklist[].
// Правило: игра одобряется, когда «за» (recommend) набрано >= порога 51% экспертов.
// Когда проголосовали все, а порог не набран: если «на доработку» не меньше «отклонить»
// -> revision (+12ч право на ошибку), иначе -> rejected. До этого — остаётся pending.

session_start();
require_once __DIR__ . '/../../swad/config.php';

$pdo = (new Database())->connect();

$userId     = (int)($_SESSION['USERDATA']['id'] ?? 0);
$isAdmin    = ((int)($_SESSION['USERDATA']['global_role'] ?? 0)) === -1;
$gameId     = (int)($_GET['id'] ?? 0);

if (!$userId) { header('Location: /login'); exit; }
if (!$gameId) die('Игра не найдена');

// Голосует только одобренный эксперт (нужен expert_id для записи).
$ex = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved' LIMIT 1");
$ex->execute([$userId]);
$expertId = $ex->fetchColumn();
if (!$expertId) die('Оценивать могут только эксперты');

// Данные формы.
$score   = (int)($_POST['score'] ?? -1);
$review  = trim($_POST['review'] ?? '');
$verdict = $_POST['verdict'] ?? '';
if ($score < 0 || $score > 100)                        { header("Location: /expert/moderation-game?id=$gameId&err=score"); exit; }
if (mb_strlen($review) < 40)                           { header("Location: /expert/moderation-game?id=$gameId&err=review"); exit; }
if (!in_array($verdict, ['recommend','revision','reject'], true)) { header("Location: /expert/moderation-game?id=$gameId&err=verdict"); exit; }

// Игра должна существовать и быть на модерации.
$g = $pdo->prepare("SELECT id, name, developer, moderation_status FROM games WHERE id = ?");
$g->execute([$gameId]);
$game = $g->fetch(PDO::FETCH_ASSOC);
if (!$game) die('Игра не найдена');
if ($game['moderation_status'] !== 'pending') { header("Location: /expert/moderation-game?id=$gameId&done=closed"); exit; }

// Апсерт оценки (без предположений об уникальном ключе — два шага).
$chk = $pdo->prepare("SELECT id FROM moderation_reviews WHERE game_id = ? AND expert_id = ? LIMIT 1");
$chk->execute([$gameId, $expertId]);
if ($rid = $chk->fetchColumn()) {
    $pdo->prepare("UPDATE moderation_reviews SET score = ?, comment = ?, verdict = ? WHERE id = ?")
        ->execute([$score, $review, $verdict, $rid]);
} else {
    $pdo->prepare("INSERT INTO moderation_reviews (game_id, expert_id, score, comment, verdict) VALUES (?, ?, ?, ?, ?)")
        ->execute([$gameId, $expertId, $score, $review, $verdict]);
}

// Пересчёт вердикта.
$totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status = 'approved'")->fetchColumn();
$need = max(1, (int)ceil($totalExperts * 0.51));

$cnt = $pdo->prepare("
    SELECT COUNT(DISTINCT expert_id) AS voted,
           SUM(verdict = 'recommend') AS rec,
           SUM(verdict = 'revision')  AS rev,
           SUM(verdict = 'reject')    AS rej
    FROM moderation_reviews WHERE game_id = ?
");
$cnt->execute([$gameId]);
$c = $cnt->fetch(PDO::FETCH_ASSOC);
$recommend = (int)$c['rec']; $revision = (int)$c['rev']; $reject = (int)$c['rej']; $voted = (int)$c['voted'];

$newStatus = 'pending';
$revisionUntil = null;
if ($recommend >= $need) {
    $newStatus = 'approved';
} elseif ($voted >= $totalExperts) {
    // Все проголосовали, порог «за» не набран.
    $newStatus = ($revision >= $reject) ? 'revision' : 'rejected';
    if ($newStatus === 'revision') $revisionUntil = date('Y-m-d H:i:s', time() + 12 * 3600);
}

if ($newStatus !== 'pending') {
    $pdo->prepare("UPDATE games SET moderation_status = ?, revision_until = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$newStatus, $revisionUntil, $gameId]);
    notify_developer($pdo, (int)$game['developer'], $game['name'], $newStatus, $revisionUntil);
}

header("Location: /expert/moderation-game?id=$gameId&done=1");
exit;

/**
 * Письмо разработчику с вердиктом. Best-effort: пробуем существующий почтовый
 * помощник проекта, иначе PHP mail(). Замени на свой async-мейлер при желании.
 */
function notify_developer(PDO $pdo, int $studioId, string $gameName, string $status, ?string $revisionUntil): void
{
    // Адрес студии.
    $email = null;
    try {
        $q = $pdo->prepare("SELECT contact_email FROM studios WHERE id = ? LIMIT 1");
        $q->execute([$studioId]);
        $email = $q->fetchColumn() ?: null;
    } catch (Throwable $e) { /* нет колонки contact_email — просто без письма */ }
    if (!$email) return;

    $map = [
        'approved' => ['✅ Игра одобрена', "Ваша игра «{$gameName}» прошла экспертную проверку и допущена к джему."],
        'rejected' => ['❌ Игра отклонена', "К сожалению, «{$gameName}» не прошла экспертную проверку джема."],
        'revision' => ['🔄 Игра отправлена на доработку', "«{$gameName}» отправлена на доработку. У вас есть время до "
            . date('d.m.Y H:i', strtotime($revisionUntil)) . " (12 часов), чтобы перезалить билд в консоли."],
    ];
    [$subject, $body] = $map[$status] ?? ['Обновление по игре', "Статус «{$gameName}»: {$status}"];
    $html = "<div style='font-family:sans-serif;'><h2>{$subject}</h2><p>{$body}</p>"
          . "<p><a href='https://dustore.ru/devs/projects'>Открыть консоль разработчика</a></p></div>";

    if (function_exists('send_email'))      { @send_email($email, $subject, $html); return; }
    if (function_exists('sendMail'))        { @sendMail($email, $subject, $html);  return; }
    if (function_exists('send_mail'))       { @send_mail($email, $subject, $html);  return; }
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: Dustore <no-reply@dustore.ru>\r\n";
    @mail($email, $subject, $html, $headers);
}