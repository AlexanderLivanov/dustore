<?php
// swad/controllers/game_changelog_timeline.php
// Партиал: временная цепочка изменений проекта для страницы экспертной модерации.
// Ожидает в области видимости: $pdo (PDO) и $gameId (int).
// Подключать одной строкой: include __DIR__ . '/../../swad/controllers/game_changelog_timeline.php';
// Ничего не выводит, если изменений нет.

if (!isset($pdo) || !isset($gameId)) return;

$__clStmt = $pdo->prepare("
    SELECT field, action, old_value, new_value, created_at
    FROM game_change_log
    WHERE game_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 60
");
$__clStmt->execute([(int)$gameId]);
$__changes = $__clStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$__changes) return;

if (!function_exists('clh')) {
    function clh($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }
}
if (!function_exists('cl_trim')) {
    function cl_trim($v, int $n = 56): string {
        $v = trim((string)$v);
        if ($v === '') return '—';
        return mb_strlen($v) > $n ? mb_substr($v, 0, $n) . '…' : $v;
    }
}

// field => [подпись, material-icon]
$__meta = [
    'name'              => ['Название', 'title'],
    'genre'             => ['Жанр', 'category'],
    'short_description' => ['Краткое описание', 'short_text'],
    'description'       => ['Описание', 'description'],
    'platforms'         => ['Платформы', 'devices'],
    'release_date'      => ['Дата релиза', 'event'],
    'game_website'      => ['Сайт', 'link'],
    'trailer_url'       => ['Трейлер', 'movie'],
    'game_exec'         => ['Исполняемый файл', 'terminal'],
    'languages'         => ['Языки', 'translate'],
    'age_rating'        => ['Возрастной рейтинг', 'shield'],
    'cover'             => ['Обложка', 'image'],
    'icon'              => ['Иконка', 'apps'],
    'screenshots'       => ['Скриншоты', 'photo_library'],
    'build'             => ['Файл игры', 'folder_zip'],
    'moderation'        => ['Модерация', 'gavel'],
];
?>
<div class="card">
    <div class="card-h">🕓 История изменений
        <span class="muted" style="font-weight:400;font-size:.78rem;">· <?= count($__changes) ?></span>
    </div>
    <div class="muted" style="font-size:.76rem;margin:-6px 0 14px;line-height:1.5;">
        Что разработчик менял в проекте. Вехи модерации отмечены цветом — правки между ними важны при переоценке билда.
    </div>

    <div style="position:relative;padding-left:22px;">
        <div style="position:absolute;left:6px;top:6px;bottom:6px;width:2px;background:var(--border);"></div>

        <?php foreach ($__changes as $c):
            [$label, $icon] = $__meta[$c['field']] ?? [$c['field'], 'edit'];
            $isMilestone = ($c['field'] === 'moderation');
            $dot = $isMilestone ? 'var(--brand2)' : 'var(--muted)';

            if ($c['field'] === 'moderation') {
                $txt = ($c['action'] === 'resubmitted')
                    ? 'Отправлено на повторную модерацию'
                    : 'Отправлено на модерацию';
            } elseif ($c['field'] === 'build') {
                $txt = 'Загружен новый билд';
            } elseif ($c['field'] === 'screenshots') {
                $txt = ($c['action'] === 'removed') ? 'Удалён скриншот' : 'Добавлены скриншоты';
            } elseif (in_array($c['field'], ['cover', 'icon'], true)) {
                $txt = 'Обновлено изображение';
            } elseif (in_array($c['field'], ['description', 'short_description'], true)) {
                $old = mb_strlen(trim((string)$c['old_value']));
                $new = mb_strlen(trim((string)$c['new_value']));
                $txt = "Изменён текст (было {$old} симв. → стало {$new})";
            } else {
                $txt = '«' . clh(cl_trim($c['old_value'])) . '» → «' . clh(cl_trim($c['new_value'])) . '»';
            }
        ?>
        <div style="position:relative;padding:0 0 16px 0;">
            <div style="position:absolute;left:-22px;top:2px;width:14px;height:14px;border-radius:50%;
                        background:var(--surface);border:2px solid <?= $dot ?>;"></div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="material-icons" style="font-size:15px;color:<?= $dot ?>;"><?= clh($icon) ?></span>
                <span style="font-size:.82rem;font-weight:600;<?= $isMilestone ? 'color:var(--brand2);' : '' ?>"><?= clh($label) ?></span>
                <span class="muted" style="font-size:.72rem;margin-left:auto;"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></span>
            </div>
            <div style="font-size:.8rem;color:var(--muted);margin-top:3px;line-height:1.5;"><?= $txt ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>