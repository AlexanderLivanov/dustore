<?php
$page_title = 'Эксперты';
$active_nav = 'experts';
require_once(__DIR__ . '/includes/header.php');

// Только модераторы и выше
if (!$is_moder) {
    echo '<div class="alert alert-err"><span class="material-icons" style="font-size:16px;vertical-align:middle;">lock</span> Доступно только модераторам платформы.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$conn = $db->connect();

// ── Создаём таблицу выборов если не существует ────────────────────────────
$conn->exec("CREATE TABLE IF NOT EXISTS expert_elections (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    start_date  DATETIME NOT NULL,
    end_date    DATETIME NOT NULL,
    status      ENUM('scheduled','active','completed') DEFAULT 'scheduled',
    created_by  INT DEFAULT NULL,
    created_at  DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── POST: обработка действий ──────────────────────────────────────────────
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Одобрить / Отклонить эксперта
    if (in_array($action, ['approve', 'reject'])) {
        $eid = (int)($_POST['expert_id'] ?? 0);
        $new = $action === 'approve' ? 'approved' : 'rejected';
        if ($eid) {
            $conn->prepare("UPDATE experts SET status=?, updated_at=NOW() WHERE id=?")
                ->execute([$new, $eid]);
            $success = $action === 'approve' ? 'Эксперт одобрен.' : 'Заявка отклонена.';
        }

        // Создать период выборов
    } elseif ($action === 'create_election') {
        $start = $_POST['election_start'] ?? '';
        $end   = $_POST['election_end']   ?? '';
        if ($start && $end && $start < $end) {
            $conn->prepare("INSERT INTO expert_elections (start_date, end_date, status, created_by) VALUES (?,?,'scheduled',?)")
                ->execute([$start, $end, $user_id]);
            $success = 'Период выборов создан.';
        } else {
            $error = 'Проверьте даты — начало должно быть раньше конца.';
        }

        // Удалить период выборов
    } elseif ($action === 'delete_election' && $is_admin) {
        $eid = (int)($_POST['election_id'] ?? 0);
        if ($eid) {
            $conn->prepare("DELETE FROM expert_elections WHERE id=?")->execute([$eid]);
            $success = 'Период выборов удалён.';
        }
    }
}

// ── Загружаем данные ──────────────────────────────────────────────────────

// Заявки экспертов
$experts_new = $conn->query("
    SELECT e.id, e.status, e.rating, e.votes_count, e.experience, e.motivation, e.created_at,
           u.username, u.profile_picture, u.email
    FROM experts e JOIN users u ON u.id = e.user_id
    WHERE e.status = 'new'
    ORDER BY e.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$experts_approved = $conn->query("
    SELECT e.id, e.status, e.rating, e.votes_count, e.created_at,
           u.username, u.profile_picture, u.email
    FROM experts e JOIN users u ON u.id = e.user_id
    WHERE e.status = 'approved'
    ORDER BY e.rating DESC
")->fetchAll(PDO::FETCH_ASSOC);

$experts_rejected = $conn->query("
    SELECT e.id, e.created_at, u.username
    FROM experts e JOIN users u ON u.id = e.user_id
    WHERE e.status = 'rejected'
    ORDER BY e.updated_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Игры на модерации
$moderation_games = $conn->query("
    SELECT g.id, g.name, g.genre, g.created_at, g.updated_at AS submitted_at,
           s.name AS studio_name,
           COUNT(mr.id) AS reviews_count,
           ROUND(AVG(mr.score)) AS avg_score,
           SUM(mr.score > 51) AS positive_votes
    FROM games g
    LEFT JOIN studios s ON s.id = g.developer
    LEFT JOIN moderation_reviews mr ON mr.game_id = g.id
    WHERE g.moderation_status = 'pending'
    GROUP BY g.id
    ORDER BY g.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Периоды выборов
$elections = $conn->query("
    SELECT *, NOW() BETWEEN start_date AND end_date AS is_active
    FROM expert_elections
    ORDER BY start_date DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$total_experts = count($experts_approved);

// Вкладка из URL
$tab = $_GET['tab'] ?? 'applications';
?>

<?php if ($success): ?><div class="alert alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- KPI -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">pending</span></div>
        <div class="stat-num" style="color:var(--warn);"><?= count($experts_new) ?></div>
        <div class="stat-label">Новых заявок</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">verified_user</span></div>
        <div class="stat-num" style="color:var(--ok);"><?= $total_experts ?></div>
        <div class="stat-label">Активных экспертов</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">videogame_asset</span></div>
        <div class="stat-num" style="color:var(--p);"><?= count($moderation_games) ?></div>
        <div class="stat-label">Игр на модерации</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">how_to_vote</span></div>
        <div class="stat-num"><?= count($elections) > 0 && $elections[0]['is_active'] ? '<span style="color:var(--ok)">Идут</span>' : '—' ?></div>
        <div class="stat-label">Выборы</div>
    </div>
</div>

<!-- Вкладки -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:1px solid var(--bd);padding-bottom:0;">
    <?php foreach (
        [
            'applications' => ['pending', 'Заявки (' . count($experts_new) . ')'],
            'experts'      => ['verified_user', 'Эксперты (' . $total_experts . ')'],
            'moderation'   => ['videogame_asset', 'На модерации (' . count($moderation_games) . ')'],
            'elections'    => ['how_to_vote', 'Выборы'],
        ] as $tid => [$icon, $label]
    ): ?>
        <a href="?tab=<?= $tid ?>" style="display:flex;align-items:center;gap:6px;padding:10px 16px;
        font-size:13px;font-weight:500;text-decoration:none;border-radius:8px 8px 0 0;margin-bottom:-1px;
        border:1px solid <?= $tab === $tid ? 'var(--bd)' : 'transparent' ?>;
        border-bottom:1px solid <?= $tab === $tid ? 'var(--surf)' : 'transparent' ?>;
        background:<?= $tab === $tid ? 'var(--surf)' : 'transparent' ?>;
        color:<?= $tab === $tid ? 'var(--pl)' : 'var(--ts)' ?>;">
            <span class="material-icons" style="font-size:16px;"><?= $icon ?></span><?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- ═══ ЗАЯВКИ ═══ -->
<?php if ($tab === 'applications'): ?>
    <?php if (empty($experts_new)): ?>
        <div class="card" style="text-align:center;padding:40px;color:var(--tm);">
            <span class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;">check_circle</span>
            Новых заявок нет
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($experts_new as $e): ?>
                <div class="card" style="padding:18px;">
                    <div style="display:flex;align-items:flex-start;gap:14px;">
                        <div style="width:42px;height:42px;border-radius:10px;flex-shrink:0;background:var(--elev);overflow:hidden;display:flex;align-items:center;justify-content:center;font-weight:700;">
                            <?php if (!empty($e['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($e['profile_picture']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?><?= mb_strtoupper(mb_substr($e['username'], 0, 2)) ?><?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                                <span style="font-size:15px;font-weight:600;"><?= htmlspecialchars($e['username']) ?></span>
                                <span style="font-size:11px;color:var(--tm);"><?= htmlspecialchars($e['email'] ?? '') ?></span>
                                <span style="font-size:11px;color:var(--tm);"><?= date('d.m.Y', strtotime($e['created_at'])) ?></span>
                            </div>
                            <?php if ($e['experience']): ?>
                                <div style="margin-bottom:8px;">
                                    <div style="font-size:11px;color:var(--tm);margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px;">Опыт</div>
                                    <div style="font-size:13px;color:var(--ts);line-height:1.5;"><?= nl2br(htmlspecialchars($e['experience'])) ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($e['motivation']): ?>
                                <div>
                                    <div style="font-size:11px;color:var(--tm);margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px;">Мотивация</div>
                                    <div style="font-size:13px;color:var(--ts);line-height:1.5;"><?= nl2br(htmlspecialchars($e['motivation'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px;flex-shrink:0;">
                            <form method="POST">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="expert_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-p" style="padding:7px 16px;font-size:12px;">
                                    <span class="material-icons" style="font-size:15px;">check</span>Одобрить
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Отклонить заявку?')">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="expert_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-d" style="padding:7px 16px;font-size:12px;">
                                    <span class="material-icons" style="font-size:15px;">close</span>Отклонить
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ═══ ЭКСПЕРТЫ ═══ -->
<?php elseif ($tab === 'experts'): ?>
    <?php if (empty($experts_approved)): ?>
        <div class="card" style="text-align:center;padding:40px;color:var(--tm);">
            Активных экспертов нет
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($experts_approved as $e): ?>
                <div class="card" style="display:flex;align-items:center;gap:14px;padding:14px;">
                    <div style="width:38px;height:38px;border-radius:9px;flex-shrink:0;background:var(--elev);overflow:hidden;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;">
                        <?php if (!empty($e['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($e['profile_picture']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?><?= mb_strtoupper(mb_substr($e['username'], 0, 2)) ?><?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:14px;font-weight:600;"><?= htmlspecialchars($e['username']) ?></div>
                        <div style="font-size:11px;color:var(--tm);">
                            Рейтинг: <?= round($e['rating'], 1) ?>
                            · Проверено игр: <?= $e['votes_count'] ?>
                            · С <?= date('d.m.Y', strtotime($e['created_at'])) ?>
                        </div>
                    </div>
                    <span class="badge badge-pub">Активен</span>
                    <?php if ($is_admin): ?>
                        <form method="POST" onsubmit="return confirm('Отозвать статус эксперта?')" style="margin-left:8px;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="expert_id" value="<?= $e['id'] ?>">
                            <button type="submit" class="btn btn-d" style="padding:5px 10px;">
                                <span class="material-icons" style="font-size:14px;">person_remove</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($experts_rejected)): ?>
            <div style="margin-top:20px;">
                <div class="sec-title" style="margin-bottom:10px;color:var(--tm);">Отклонённые (последние 20)</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($experts_rejected as $e): ?>
                        <span style="background:var(--elev);padding:4px 12px;border-radius:6px;font-size:12px;color:var(--tm);">
                            <?= htmlspecialchars($e['username']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ═══ ИГРЫ НА МОДЕРАЦИИ ═══ -->
<?php elseif ($tab === 'moderation'): ?>
    <?php if (empty($moderation_games)): ?>
        <div class="card" style="text-align:center;padding:40px;color:var(--tm);">
            <span class="material-icons" style="font-size:40px;color:var(--ok);display:block;margin-bottom:10px;">done_all</span>
            Нет игр на модерации
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($moderation_games as $g):
                $pct = $total_experts > 0 ? round($g['positive_votes'] / $total_experts * 100) : 0;
                $need_pct = 51;
                $bar_color = $pct >= $need_pct ? 'var(--ok)' : ($pct > 30 ? 'var(--warn)' : 'var(--err)');
            ?>
                <div class="card" style="padding:16px;">
                    <div style="display:flex;align-items:flex-start;gap:14px;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
                                <span style="font-size:15px;font-weight:600;"><?= htmlspecialchars($g['name']) ?></span>
                                <span class="badge badge-rev">На модерации</span>
                                <?php if ($g['genre']): ?><span class="badge badge-draft"><?= htmlspecialchars($g['genre']) ?></span><?php endif; ?>
                            </div>
                            <div style="font-size:12px;color:var(--ts);margin-bottom:10px;">
                                <?= htmlspecialchars($g['studio_name'] ?? '—') ?>
                                · Отправлена на модерацию <?= date('d.m.Y H:i', strtotime($g['submitted_at'])) ?>
                            </div>
                            <!-- Прогресс голосования -->
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="flex:1;height:6px;background:var(--elev);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:<?= min(100, $pct) ?>%;background:<?= $bar_color ?>;border-radius:3px;transition:width .3s;"></div>
                                </div>
                                <span style="font-size:12px;color:<?= $bar_color ?>;font-weight:600;flex-shrink:0;">
                                    <?= $g['reviews_count'] ?>/<span style="color:var(--tm)"><?= $total_experts ?></span>
                                    голосов · <?= $pct ?>% за
                                </span>
                            </div>
                            <?php if ($g['reviews_count'] > 0 && $g['avg_score']): ?>
                                <div style="font-size:11px;color:var(--tm);margin-top:4px;">
                                    Средняя оценка: <?= $g['avg_score'] ?>/100
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="/expert/moderation-game?id=<?= $g['id'] ?>" target="_blank"
                            class="btn btn-g" style="padding:6px 14px;font-size:12px;flex-shrink:0;">
                            <span class="material-icons" style="font-size:14px;">open_in_new</span>Открыть
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ═══ ВЫБОРЫ ═══ -->
<?php elseif ($tab === 'elections'): ?>
    <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start;">

        <!-- История выборов -->
        <div>
            <div class="sec-head" style="margin-bottom:14px;">
                <div class="sec-title">Периоды выборов</div>
            </div>
            <?php if (empty($elections)): ?>
                <div class="card" style="text-align:center;padding:30px;color:var(--tm);">Выборов пока не было</div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($elections as $el):
                        $is_active = (bool)$el['is_active'];
                        $is_past   = $el['end_date'] < date('Y-m-d H:i:s');
                    ?>
                        <div class="card" style="padding:14px;display:flex;align-items:center;gap:14px;">
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                    <?php if ($is_active): ?>
                                        <span class="badge badge-pub">● Идут сейчас</span>
                                    <?php elseif ($is_past): ?>
                                        <span class="badge badge-draft">Завершены</span>
                                    <?php else: ?>
                                        <span class="badge badge-rev">Запланированы</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:13px;color:var(--ts);">
                                    <?= date('d.m.Y H:i', strtotime($el['start_date'])) ?>
                                    &nbsp;→&nbsp;
                                    <?= date('d.m.Y H:i', strtotime($el['end_date'])) ?>
                                </div>
                            </div>
                            <?php if ($is_admin && !$is_past): ?>
                                <form method="POST" onsubmit="return confirm('Удалить период выборов?')">
                                    <input type="hidden" name="action" value="delete_election">
                                    <input type="hidden" name="election_id" value="<?= $el['id'] ?>">
                                    <button type="submit" class="btn btn-d" style="padding:5px 10px;">
                                        <span class="material-icons" style="font-size:14px;">delete</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Создать новый период -->
        <div class="card" style="position:sticky;top:16px;">
            <div class="card-title"><span class="material-icons">add_circle_outline</span>Новый период выборов</div>
            <p style="font-size:13px;color:var(--ts);margin-bottom:16px;line-height:1.6;">
                Период выборов — это время, в течение которого эксперты могут голосовать за/против игр на модерации.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="create_election">
                <div class="field">
                    <label>Начало выборов</label>
                    <input type="datetime-local" name="election_start" required
                        value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="field">
                    <label>Конец выборов</label>
                    <input type="datetime-local" name="election_end" required
                        value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>">
                </div>
                <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;">
                    <span class="material-icons">how_to_vote</span>Создать период
                </button>
            </form>

            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--bd);">
                <div style="font-size:12px;color:var(--tm);line-height:1.7;">
                    <strong style="color:var(--ts);">Порог публикации:</strong> 51% положительных оценок (>51/100)<br>
                    <strong style="color:var(--ts);">Активных экспертов:</strong> <?= $total_experts ?><br>
                    <strong style="color:var(--ts);">Нужно голосов:</strong> <?= ceil($total_experts * 0.51) ?>+
                </div>
            </div>
        </div>

    </div>
<?php endif; ?>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>