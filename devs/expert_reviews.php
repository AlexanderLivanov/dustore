<?php
$page_title = 'Экспертные рецензии';
$active_nav = 'expert_reviews';
require_once(__DIR__ . '/includes/header.php');

$conn = $db->connect();

// Игры студии с рецензиями экспертов
$stmt = $conn->prepare("
    SELECT g.id, g.name, g.moderation_status, g.status, g.path_to_cover,
           COUNT(mr.id)            AS review_count,
           ROUND(AVG(mr.score))   AS avg_score,
           SUM(mr.score > 51)     AS positive,
           SUM(mr.score <= 51)    AS negative
    FROM games g
    LEFT JOIN moderation_reviews mr ON mr.game_id = g.id
    WHERE g.developer = ?
    GROUP BY g.id
    HAVING review_count > 0 OR g.moderation_status = 'pending'
    ORDER BY g.updated_at DESC
");
$stmt->execute([$studio_id]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_experts = (int)$conn->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();

$selected_id = (int)($_GET['game_id'] ?? ($games[0]['id'] ?? 0));

// Рецензии выбранной игры
$reviews = [];
if ($selected_id) {
    $stmt = $conn->prepare("
        SELECT mr.score, mr.comment, mr.id AS review_id,
               u.username, u.profile_picture,
               e.rating AS expert_rating
        FROM moderation_reviews mr
        JOIN experts e ON e.id = mr.expert_id
        JOIN users u ON u.id = e.user_id
        WHERE mr.game_id = ?
        ORDER BY mr.id DESC
    ");
    $stmt->execute([$selected_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $game_info = $conn->prepare("SELECT name, moderation_status, status FROM games WHERE id=?");
    $game_info->execute([$selected_id]);
    $game_info = $game_info->fetch(PDO::FETCH_ASSOC);
}
?>

<div style="display:grid;grid-template-columns:260px 1fr;gap:16px;align-items:start;">

    <!-- Список игр -->
    <div>
        <div class="sec-title" style="margin-bottom:12px;">Ваши проекты</div>
        <?php if (empty($games)): ?>
            <div class="card" style="text-align:center;padding:24px;color:var(--tm);font-size:13px;">
                Нет игр с рецензиями
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <?php foreach ($games as $g):
                    $is_sel = ($g['id'] === $selected_id);
                    $mod    = $g['moderation_status'];
                    $cls    = $mod === 'pending' ? 'badge-rev' : ($g['status'] === 'published' ? 'badge-pub' : 'badge-draft');
                    $lbl    = $mod === 'pending' ? 'На модерации' : ($g['status'] === 'published' ? 'Опубликована' : 'Черновик');
                ?>
                    <a href="?game_id=<?= $g['id'] ?>"
                        style="display:flex;align-items:center;gap:10px;padding:12px;border-radius:10px;text-decoration:none;
                      background:<?= $is_sel ? 'rgba(195,33,120,.12)' : 'var(--surf)' ?>;
                      border:1px solid <?= $is_sel ? 'var(--p)' : 'var(--bd)' ?>;
                      transition:all .15s;">
                        <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;background:var(--elev);overflow:hidden;
                            <?= !empty($g['path_to_cover']) ? 'background-image:url(\'' . htmlspecialchars($g['path_to_cover']) . '\');background-size:cover;' : '' ?>"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                                color:<?= $is_sel ? 'var(--pl)' : '#fff' ?>;"><?= htmlspecialchars($g['name']) ?></div>
                            <div style="display:flex;align-items:center;gap:6px;margin-top:3px;">
                                <span class="badge <?= $cls ?>" style="font-size:9px;"><?= $lbl ?></span>
                                <?php if ($g['review_count'] > 0): ?>
                                    <span style="font-size:10px;color:var(--tm);"><?= $g['review_count'] ?> рецензий</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Рецензии -->
    <div>
        <?php if (!$selected_id || empty($game_info)): ?>
            <div class="card" style="text-align:center;padding:40px;color:var(--tm);">
                Выберите игру слева
            </div>
        <?php else:
            $positive = array_filter($reviews, fn($r) => $r['score'] > 51);
            $pct      = $total_experts > 0 && count($reviews) > 0
                ? round(count($positive) / $total_experts * 100) : 0;
            $avg      = count($reviews) > 0 ? round(array_sum(array_column($reviews, 'score')) / count($reviews)) : 0;
        ?>
            <!-- Шапка -->
            <div class="card" style="margin-bottom:14px;padding:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div>
                        <div style="font-size:18px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($game_info['name'] ?? '') ?></div>
                        <?php if ($game_info['moderation_status'] === 'pending'): ?>
                            <span class="badge badge-rev">На модерации</span>
                        <?php elseif ($game_info['status'] === 'published'): ?>
                            <span class="badge badge-pub">Опубликована</span>
                        <?php endif; ?>
                    </div>
                    <?php if (count($reviews) > 0): ?>
                        <div style="display:flex;gap:20px;text-align:center;">
                            <div>
                                <div style="font-size:22px;font-weight:700;color:<?= $pct >= 51 ? 'var(--ok)' : 'var(--warn)' ?>;"><?= $pct ?>%</div>
                                <div style="font-size:11px;color:var(--tm);">за публикацию</div>
                            </div>
                            <div>
                                <div style="font-size:22px;font-weight:700;"><?= $avg ?><span style="font-size:14px;color:var(--tm);">/100</span></div>
                                <div style="font-size:11px;color:var(--tm);">средняя оценка</div>
                            </div>
                            <div>
                                <div style="font-size:22px;font-weight:700;"><?= count($reviews) ?><span style="font-size:14px;color:var(--tm);">/<?= $total_experts ?></span></div>
                                <div style="font-size:11px;color:var(--tm);">проголосовало</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (count($reviews) > 0): ?>
                    <div style="margin-top:12px;height:8px;background:var(--elev);border-radius:4px;overflow:hidden;">
                        <div style="height:100%;width:<?= min(100, $pct) ?>%;background:<?= $pct >= 51 ? 'var(--ok)' : 'var(--warn)' ?>;border-radius:4px;transition:width .5s;"></div>
                    </div>
                    <div style="font-size:11px;color:var(--tm);margin-top:6px;">
                        Для публикации нужно 51% положительных оценок
                        (<?= ceil($total_experts * 0.51) ?>+ голосов «за» из <?= $total_experts ?>)
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($reviews)): ?>
                <div class="card" style="text-align:center;padding:40px;color:var(--tm);">
                    <span class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:var(--p);">pending</span>
                    Эксперты ещё не оставили рецензий
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($reviews as $r):
                        $positive_vote = $r['score'] > 51;
                    ?>
                        <div class="card" style="padding:16px;border-left:3px solid <?= $positive_vote ? 'var(--ok)' : 'var(--err)' ?>;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <div style="width:34px;height:34px;border-radius:8px;flex-shrink:0;background:var(--elev);overflow:hidden;
                                display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;">
                                    <?php if (!empty($r['profile_picture'])): ?>
                                        <img src="<?= htmlspecialchars($r['profile_picture']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                        <?php else: ?><?= mb_strtoupper(mb_substr($r['username'], 0, 2)) ?><?php endif; ?>
                                </div>
                                <div style="flex:1;">
                                    <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($r['username']) ?></div>
                                    <div style="font-size:11px;color:var(--tm);">Рейтинг эксперта: <?= round($r['expert_rating'], 1) ?></div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-size:20px;font-weight:700;color:<?= $positive_vote ? 'var(--ok)' : 'var(--err)' ?>;"><?= $r['score'] ?></div>
                                    <div style="font-size:10px;color:var(--tm);">/ 100</div>
                                </div>
                                <span class="badge <?= $positive_vote ? 'badge-pub' : 'badge-err' ?>" style="margin-left:4px;">
                                    <?= $positive_vote ? '👍 За' : '👎 Против' ?>
                                </span>
                            </div>
                            <?php if ($r['comment']): ?>
                                <p style="font-size:13px;color:var(--ts);line-height:1.6;margin:0;"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                            <?php else: ?>
                                <p style="font-size:13px;color:var(--tm);font-style:italic;margin:0;">Без комментария</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>