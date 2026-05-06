<?php
$page_title = 'Обзор';
$active_nav = 'dashboard';
require_once(__DIR__ . '/includes/header.php');

$conn = $db->connect();

$all_projects = $org->getAllProjects($studio_id);
$all_staff    = $org->getAllStaff($studio_id);

// Игроки
$stmt = $conn->prepare("SELECT COUNT(*) FROM library l JOIN games g ON l.game_id=g.id WHERE g.developer=?");
$stmt->execute([$studio_id]);
$players_count = (int)$stmt->fetchColumn();

// Отзывы без ответа
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM game_reviews r
    JOIN games g ON r.game_id=g.id
    LEFT JOIN review_replies rr ON rr.review_id=r.id AND rr.studio_id=?
    WHERE g.developer=? AND rr.id IS NULL
");
$stmt->execute([$studio_id, $studio_id]);
$unanswered = (int)$stmt->fetchColumn();

// Средний рейтинг (шкала 1-10)
$stmt = $conn->prepare("SELECT ROUND(AVG(r.rating),1) FROM game_reviews r JOIN games g ON r.game_id=g.id WHERE g.developer=?");
$stmt->execute([$studio_id]);
$avg_rating = $stmt->fetchColumn() ?: '—';

// Последние 5 отзывов
$stmt = $conn->prepare("
    SELECT r.rating, r.created_at, g.name AS game_name, u.username
    FROM game_reviews r
    JOIN games g ON r.game_id=g.id
    LEFT JOIN users u ON u.id=r.user_id
    WHERE g.developer=?
    ORDER BY r.created_at DESC LIMIT 5
");
$stmt->execute([$studio_id]);
$recent_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_map = [
    'published' => ['badge-pub', 'Опубликован'],
    'draft'    => ['badge-draft', 'Черновик'],
    'closed'   => ['badge-err', 'Закрыт'],
];
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">videogame_asset</span></div>
        <div class="stat-num"><?= count($all_projects) ?></div>
        <div class="stat-label">Проекты</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">people</span></div>
        <div class="stat-num"><?= number_format($players_count) ?></div>
        <div class="stat-label">Игроков</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">star</span></div>
        <div class="stat-num"><?= $avg_rating ?></div>
        <div class="stat-label">Средний рейтинг</div>
        <?php if ($unanswered > 0): ?>
            <div class="stat-sub" style="color:var(--warn);"><?= $unanswered ?> без ответа</div>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">groups</span></div>
        <div class="stat-num"><?= count($all_staff) ?></div>
        <div class="stat-label">Сотрудники</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;">
    <div>
        <div class="sec-head">
            <div class="sec-title">Проекты</div>
            <a href="/devs/projects" class="sec-link">Все →</a>
        </div>
        <?php if (empty($all_projects)): ?>
            <div class="card" style="text-align:center;padding:40px;">
                <span class="material-icons" style="font-size:40px;color:var(--p);display:block;margin-bottom:10px;">add_circle_outline</span>
                <p style="color:var(--ts);margin-bottom:16px;">Проектов пока нет</p>
                <a href="/devs/new" class="btn btn-p"><span class="material-icons">add</span>Создать</a>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach (array_slice($all_projects, 0, 4) as $p):
                    [$cls, $lbl] = $status_map[$p['status'] ?? 'draft'] ?? ['badge-draft', 'Черновик'];
                ?>
                    <div class="card" style="display:flex;align-items:center;gap:14px;padding:14px;cursor:pointer;" onclick="location.href='/devs/edit?id=<?= (int)$p['id'] ?>'">
                        <div style="width:52px;height:52px;border-radius:10px;flex-shrink:0;background:var(--elev);<?= !empty($p['path_to_cover']) ? 'background-image:url(\'' . htmlspecialchars($p['path_to_cover']) . '\');background-size:cover;background-position:center;' : '' ?>"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($p['name']) ?></div>
                            <div style="font-size:11px;color:var(--ts);margin-top:2px;"><?= htmlspecialchars($p['genre'] ?? '') ?></div>
                        </div>
                        <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="sec-head">
            <div class="sec-title">Последние отзывы</div>
        </div>
        <div class="card" style="padding:0;overflow:hidden;">
            <?php if (empty($recent_reviews)): ?>
                <div style="padding:24px;text-align:center;color:var(--tm);font-size:13px;">Отзывов пока нет</div>
            <?php else: ?>
                <?php foreach ($recent_reviews as $r):
                    // Рейтинг 1-10 → 1-5 звёзд (округляем вверх)
                    $stars = (int)round(max(1, min(10, (int)$r['rating'])) / 2);
                    $stars = max(1, min(5, $stars));
                ?>
                    <div style="padding:12px 16px;border-bottom:1px solid var(--bd);display:flex;gap:10px;align-items:flex-start;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:12px;font-weight:500;"><?= htmlspecialchars($r['username'] ?? 'Игрок') ?></div>
                            <div style="font-size:11px;color:var(--tm);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($r['game_name']) ?></div>
                        </div>
                        <div style="color:#ffaa00;font-size:12px;flex-shrink:0;">
                            <?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?>
                            <span style="color:var(--tm);font-size:10px;"><?= $r['rating'] ?>/10</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="padding:10px 16px;">
                    <a href="/devs/replies" class="sec-link" style="font-size:12px;">Все отзывы →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>