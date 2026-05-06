<?php
/**
 * devs/events.php — лента событий платформы
 * Для разработчиков: отзывы и рецензии их игр
 * Для модераторов: + новые студии и заявки экспертов
 */
$page_title = 'События';
$active_nav = 'events';
require_once(__DIR__ . '/includes/header.php');

$conn = $db->connect();

// Помечаем все события прочитанными при открытии страницы
if ($is_moder) {
    $conn->prepare("UPDATE platform_events SET is_read=1 WHERE is_read=0")
         ->execute();
} else {
    $conn->prepare("UPDATE platform_events SET is_read=1 WHERE studio_id=? AND is_read=0")
         ->execute([$studio_id]);
}

// ── Загрузка событий ──────────────────────────────────────────────
$limit  = 60;
$offset = (int)($_GET['page'] ?? 0) * $limit;

$allowedTypes = ['review_new', 'moderation_review'];
if ($is_moder) $allowedTypes = array_merge($allowedTypes, ['studio_new', 'expert_apply', 'game_published']);

$inTypes = implode(',', array_fill(0, count($allowedTypes), '?'));
$params  = $allowedTypes;

if ($is_moder) {
    $sql = "SELECT e.*, u.username, u.profile_picture
            FROM platform_events e
            LEFT JOIN users u ON u.id = e.actor_id
            WHERE e.type IN ($inTypes)
            ORDER BY e.created_at DESC LIMIT $limit OFFSET $offset";
} else {
    $sql = "SELECT e.*, u.username, u.profile_picture
            FROM platform_events e
            LEFT JOIN users u ON u.id = e.actor_id
            WHERE e.type IN ($inTypes) AND e.studio_id = ?
            ORDER BY e.created_at DESC LIMIT $limit OFFSET $offset";
    $params[] = $studio_id;
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Конфигурация отображения типов ────────────────────────────────
$eventConfig = [
    'review_new'        => ['icon' => 'rate_review',      'color' => '#c32178', 'label' => 'Новый отзыв'],
    'moderation_review' => ['icon' => 'workspace_premium','color' => '#7c3aed', 'label' => 'Рецензия эксперта'],
    'studio_new'        => ['icon' => 'domain_add',       'color' => '#0284c7', 'label' => 'Новая студия'],
    'expert_apply'      => ['icon' => 'verified_user',    'color' => '#059669', 'label' => 'Заявка эксперта'],
    'game_published'    => ['icon' => 'check_circle',     'color' => '#00d68f', 'label' => 'Игра опубликована'],
];

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'только что';
    if ($diff < 3600)   return floor($diff/60) . ' мин. назад';
    if ($diff < 86400)  return floor($diff/3600) . ' ч. назад';
    if ($diff < 604800) return floor($diff/86400) . ' дн. назад';
    return date('d.m.Y', strtotime($dt));
}
?>

<div class="sec-head" style="margin-bottom:20px;">
    <div class="sec-title">Лента событий</div>
    <?php if ($is_moder): ?>
    <span style="font-size:11px;color:var(--tm);">Все события платформы</span>
    <?php endif; ?>
</div>

<?php if (empty($events)): ?>
    <div class="card" style="text-align:center;padding:60px;color:var(--tm);">
        <span class="material-icons" style="font-size:48px;display:block;margin-bottom:12px;opacity:.3;">event_note</span>
        <div style="font-size:15px;">Событий пока нет</div>
        <div style="font-size:12px;margin-top:6px;">Здесь будут появляться отзывы, рецензии и другие события</div>
    </div>
<?php else: ?>
    <div style="display:flex;flex-direction:column;gap:6px;">
    <?php foreach ($events as $ev):
        $cfg     = $eventConfig[$ev['type']] ?? ['icon'=>'info','color'=>'#888','label'=>$ev['type']];
        $payload = json_decode($ev['payload'] ?? '{}', true) ?: [];
        $ago     = timeAgo($ev['created_at']);

        // Формируем ссылку и описание по типу
        $link = '#';
        $desc = '';
        switch ($ev['type']) {
            case 'review_new':
                $link = '/devs/replies';
                $game = htmlspecialchars($payload['game_name'] ?? 'игру');
                $rating = (int)($payload['rating'] ?? 0);
                $stars = str_repeat('★', min(5, (int)round($rating/2)));
                $desc = "Игрок <strong>" . htmlspecialchars($ev['username'] ?? 'Аноним') . "</strong> оценил «{$game}» на {$rating}/10 {$stars}";
                break;
            case 'moderation_review':
                $link = '/devs/expert_reviews?game_id=' . (int)$ev['target_id'];
                $game  = htmlspecialchars($payload['game_name'] ?? 'игру');
                $score = (int)($payload['score'] ?? 0);
                $desc  = "Эксперт оставил рецензию на «{$game}» — оценка {$score}/100";
                break;
            case 'studio_new':
                $link = '/devs/recentorgs';
                $sname = htmlspecialchars($payload['studio_name'] ?? 'Студия');
                $desc  = "Зарегистрирована новая студия <strong>{$sname}</strong> — ожидает модерации";
                break;
            case 'expert_apply':
                $link = '/devs/experts';
                $nick = htmlspecialchars($payload['nickname'] ?? $ev['username'] ?? 'Пользователь');
                $desc = "<strong>{$nick}</strong> подал заявку на роль эксперта";
                break;
            case 'game_published':
                $link = '/g/' . (int)$ev['target_id'];
                $game = htmlspecialchars($payload['game_name'] ?? 'Игра');
                $desc = "Игра <strong>{$game}</strong> опубликована на платформе";
                break;
            default:
                $desc = htmlspecialchars($ev['type']);
        }
    ?>
        <a href="<?= $link ?>" style="text-decoration:none;" class="event-row-link">
            <div class="card" style="display:flex;align-items:flex-start;gap:14px;padding:12px 16px;
                 transition:border-color .15s;cursor:pointer;"
                 onmouseover="this.style.borderColor='rgba(195,33,120,.3)'"
                 onmouseout="this.style.borderColor=''">

                <!-- Иконка -->
                <div style="width:36px;height:36px;border-radius:10px;flex-shrink:0;
                            background:<?= $cfg['color'] ?>22;
                            display:flex;align-items:center;justify-content:center;">
                    <span class="material-icons" style="font-size:18px;color:<?= $cfg['color'] ?>;"><?= $cfg['icon'] ?></span>
                </div>

                <!-- Контент -->
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
                        <span style="font-size:10px;font-weight:700;letter-spacing:.06em;color:<?= $cfg['color'] ?>;text-transform:uppercase;"><?= $cfg['label'] ?></span>
                        <span style="font-size:10px;color:var(--tm);"><?= $ago ?></span>
                    </div>
                    <div style="font-size:13px;color:var(--ts);line-height:1.5;"><?= $desc ?></div>
                </div>

                <!-- Аватар актора -->
                <?php if (!empty($ev['profile_picture']) || !empty($ev['username'])): ?>
                <div style="flex-shrink:0;display:flex;align-items:center;gap:6px;">
                    <?php if (!empty($ev['profile_picture'])): ?>
                        <img src="<?= htmlspecialchars($ev['profile_picture']) ?>"
                             style="width:24px;height:24px;border-radius:50%;object-fit:cover;" alt="">
                    <?php else: ?>
                        <div style="width:24px;height:24px;border-radius:50%;background:var(--p);
                                    display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;">
                            <?= mb_strtoupper(mb_substr($ev['username'] ?? '?', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>