<?php
$page_title = 'Отзывы';
$active_nav = 'replies';
require_once(__DIR__ . '/includes/header.php');

$conn = $db->connect();

// Все проекты студии
$stmt = $conn->prepare("SELECT id, name FROM games WHERE developer = ? ORDER BY id DESC");
$stmt->execute([$studio_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$project_ids = array_column($projects, 'id');
$reviews_by_game = [];

if (!empty($project_ids)) {
    $in   = implode(',', array_fill(0, count($project_ids), '?'));
    $sql  = "
        SELECT
            r.id AS review_id, r.game_id, r.rating, r.text AS review_text, r.created_at,
            u.username AS author_nick, u.profile_picture,
            rr.id AS reply_id, rr.text AS reply_text, rr.created_at AS reply_at
        FROM game_reviews r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN review_replies rr ON rr.review_id = r.id AND rr.studio_id = ?
        WHERE r.game_id IN ($in)
        ORDER BY r.game_id DESC, r.created_at DESC
    ";
    $params = array_merge([$studio_id], $project_ids);
    $stmt   = $conn->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $reviews_by_game[(int)$row['game_id']][] = $row;
    }
}

// Handle reply submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_text'], $_POST['review_id'])) {
    $review_id  = (int)$_POST['review_id'];
    $reply_text = trim($_POST['reply_text']);
    if ($reply_text) {
        $conn->prepare("
            INSERT INTO review_replies (review_id, studio_id, text, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE text=VALUES(text), created_at=NOW()
        ")->execute([$review_id, $studio_id, $reply_text]);
    }
    header('Location: /devs/replies');
    exit();
}

$total_reviews   = array_sum(array_map('count', $reviews_by_game));
$unanswered_count = 0;
foreach ($reviews_by_game as $reviews) {
    foreach ($reviews as $r) {
        if (empty($r['reply_id'])) $unanswered_count++;
    }
}
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">rate_review</span></div>
        <div class="stat-num"><?= $total_reviews ?></div>
        <div class="stat-label">Всего отзывов</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">mark_chat_unread</span></div>
        <div class="stat-num" style="color:<?= $unanswered_count > 0 ? 'var(--warn)' : 'var(--ok)' ?>"><?= $unanswered_count ?></div>
        <div class="stat-label">Без ответа</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">videogame_asset</span></div>
        <div class="stat-num"><?= count($projects) ?></div>
        <div class="stat-label">Игр с отзывами</div>
    </div>
</div>

<?php if (empty($project_ids)): ?>
    <div class="card" style="text-align:center;padding:40px;">
        <p style="color:var(--ts);">Нет проектов — отзывы появятся после публикации игры.</p>
    </div>
<?php else: ?>

    <?php foreach ($projects as $proj):
        $game_id = (int)$proj['id'];
        $reviews = $reviews_by_game[$game_id] ?? [];
        if (empty($reviews)) continue;
    ?>
        <div style="margin-bottom:24px;">
            <div class="sec-head">
                <div class="sec-title"><?= htmlspecialchars($proj['name']) ?> <span style="color:var(--tm);font-weight:400;">(<?= count($reviews) ?>)</span></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($reviews as $r):
                    $stars    = (int)$r['rating'];
                    $has_reply = !empty($r['reply_id']);
                ?>
                    <div class="card" style="padding:16px;">
                        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:10px;">
                            <div style="width:36px;height:36px;border-radius:9px;flex-shrink:0;background:var(--elev);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;">
                                <?php if (!empty($r['profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($r['profile_picture']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?><?= mb_strtoupper(mb_substr($r['author_nick'] ?? 'U', 0, 2)) ?><?php endif; ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($r['author_nick'] ?? 'Аноним') ?></div>
                                <div style="font-size:11px;color:var(--tm);"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></div>
                            </div>
                            <?php $stars = max(0, min(5, (int)$stars)); ?>
                            <div style="color:#ffaa00;font-size:14px;flex-shrink:0;">
                                <?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?>
                            </div>
                            <?php if (!$has_reply): ?>
                                <span class="badge badge-rev">Без ответа</span>
                            <?php else: ?>
                                <span class="badge badge-pub">Отвечено</span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size:13px;color:var(--ts);line-height:1.6;margin-bottom:12px;"><?= nl2br(htmlspecialchars($r['review_text'] ?? '')) ?></p>

                        <?php if ($has_reply): ?>
                            <div style="background:rgba(0,214,143,.07);border:1px solid rgba(0,214,143,.15);border-radius:8px;padding:10px 14px;margin-bottom:12px;">
                                <div style="font-size:11px;color:var(--ok);margin-bottom:4px;">Ваш ответ · <?= date('d.m.Y', strtotime($r['reply_at'])) ?></div>
                                <p style="font-size:13px;color:var(--ts);line-height:1.5;"><?= nl2br(htmlspecialchars($r['reply_text'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Reply form -->
                        <form method="POST" style="display:flex;gap:8px;align-items:flex-start;">
                            <input type="hidden" name="review_id" value="<?= (int)$r['review_id'] ?>">
                            <textarea name="reply_text" placeholder="<?= $has_reply ? 'Изменить ответ...' : 'Ответить на отзыв...' ?>"
                                style="flex:1;background:var(--elev);border:1px solid var(--bd);border-radius:8px;padding:8px 12px;color:#fff;font-size:13px;font-family:Inter,sans-serif;outline:none;resize:none;height:40px;transition:height .2s,border .15s;"
                                onfocus="this.style.height='80px';this.style.borderColor='var(--p)'"
                                onblur="if(!this.value)this.style.height='40px';this.style.borderColor=''"><?= htmlspecialchars($r['reply_text'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-p" style="padding:8px 14px;flex-shrink:0;">
                                <span class="material-icons" style="font-size:16px;">send</span>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>