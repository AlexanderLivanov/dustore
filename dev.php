<?php
session_start();
require_once('swad/config.php');
require_once('swad/controllers/organization.php');

$db  = new Database();
$pdo = $db->connect();

/* ─────────────────────────── ХЕЛПЕРЫ ─────────────────────────── */

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Пусто? Ловит '', null, пробелы и мусорные даты MySQL. */
function blank($v): bool
{
    if ($v === null) return true;
    $v = trim((string)$v);
    return $v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00';
}

/** Запрос, который не роняет страницу, если таблицы/колонки нет. */
function dq(PDO $pdo, string $sql, array $p = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ex) {
        error_log('[dev.php] ' . $ex->getMessage());
        return [];
    }
}

/** Добивает схему — иначе href уезжает на dustore.ru/example.com. */
function ext_url(string $u): string
{
    $u = trim($u);
    if ($u === '') return '';
    return preg_match('~^https?://~i', $u) ? $u : 'https://' . ltrim($u, '/');
}

function ru_date(?string $d): string
{
    if (blank($d)) return '';
    try {
        $dt = new DateTime($d);
    } catch (Throwable $ex) {
        return '';
    }
    $m = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    return $dt->format('j') . ' ' . $m[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

function plural(int $n, string $one, string $few, string $many): string
{
    $n10 = $n % 10;
    $n100 = $n % 100;
    if ($n10 === 1 && $n100 !== 11) return $one;
    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14)) return $few;
    return $many;
}

function nfmt($n): string
{
    return number_format((float)$n, 0, ',', ' ');
}

/* ─────────────────────────── ДАННЫЕ ─────────────────────────── */

$tiker = trim((string)($_GET['name'] ?? ''));
$rows  = dq($pdo, "SELECT * FROM studios WHERE tiker = ? LIMIT 1", [$tiker]);
$studio = $rows[0] ?? null;

if (!$studio) {
    header('Location: /explore');
    exit();
}

$sid = (int)$studio['id'];

/* Публично показываем только опубликованные игры.
   Если нужно видеть черновики — убери "AND g.status = 'published'". */
$projects = dq($pdo, "
    SELECT g.id, g.name, g.short_description, g.path_to_cover, g.gqi, g.genre,
           (SELECT COUNT(*) FROM library  l WHERE l.game_id = g.id) AS installs,
           (SELECT ROUND(AVG(r.rating), 1) FROM ratings r WHERE r.game_id = g.id) AS rating
    FROM games g
    WHERE g.developer = ? AND g.status = 'published'
    ORDER BY installs DESC, g.id DESC
", [$sid]);

$games_count = count($projects);
$downloads   = 0;
$gqi_sum = 0;
$gqi_cnt = 0;
foreach ($projects as $p) {
    $downloads += (int)$p['installs'];
    if (!blank($p['gqi'])) {
        $gqi_sum += (float)$p['gqi'];
        $gqi_cnt++;
    }
}
$gqi_avg = $gqi_cnt ? round($gqi_sum / $gqi_cnt, 1) : null;

/* Шкала оценок на Dustore — 1..10 (game.php показывает «X/10»),
   поэтому порог «рекомендую» — 7, а не 4. */
const RECOMMEND_FROM = 7;

$rt = dq($pdo, "
    SELECT COUNT(*) total, AVG(r.rating) avg_rating, SUM(r.rating >= " . RECOMMEND_FROM . ") good
    FROM ratings r JOIN games g ON g.id = r.game_id
    WHERE g.developer = ?
", [$sid])[0] ?? ['total' => 0, 'avg_rating' => null, 'good' => 0];

$rating_total = (int)$rt['total'];
$rating_avg   = $rating_total ? round((float)$rt['avg_rating'], 1) : null;
$recommend    = $rating_total ? round((int)$rt['good'] / $rating_total * 100) : null;

$badges = dq($pdo, "
    SELECT b.icon_url, b.name AS badge_name, b.description,
           b.multiplier AS coefficient, sb.awarded_at AS award_date
    FROM given_badges sb
    JOIN badges b ON sb.badge_id = b.id
    WHERE sb.studio_id = ?
    ORDER BY sb.awarded_at DESC
", [$sid]);

/* ── Контакты: собираем только заполненное ──────────────────────
   Раньше блок рисовался всегда и при пустых полях давал голые
   иконки и строку ", " из country + city. */
$contacts = [];

if (!blank($studio['website'] ?? '')) {
    $u = ext_url($studio['website']);
    $contacts[] = ['🌐', 'Сайт', preg_replace('~^https?://(www\.)?~i', '', $u), $u];
}
if (!blank($studio['contact_email'] ?? '')) {
    $contacts[] = ['✉️', 'Почта', $studio['contact_email'], 'mailto:' . $studio['contact_email']];
}
/* tg_link/vk_link заполняются только при регистрации (там пишутся пустыми),
   а консоль студии правит vk_public_id/tg_studio_id. Поэтому смотрим оба. */
if (!blank($studio['tg_link'] ?? '')) {
    $contacts[] = ['✈️', 'Telegram', 'Канал студии', ext_url($studio['tg_link'])];
}
$vk_id = preg_replace('/\D/', '', (string)($studio['vk_public_id'] ?? ''));
if (!blank($studio['vk_link'] ?? '')) {
    $contacts[] = ['🅥', 'VK', 'Сообщество', ext_url($studio['vk_link'])];
} elseif ($vk_id !== '') {
    $contacts[] = ['🅥', 'VK', 'Сообщество', 'https://vk.com/club' . $vk_id];
}
$loc = array_filter([trim((string)($studio['city'] ?? '')), trim((string)($studio['country'] ?? ''))], fn($x) => $x !== '');
if ($loc) {
    $contacts[] = ['📍', 'Локация', implode(', ', $loc), null];
}

/* ── Мета-строка шапки ── */
$meta = [];
if ($f = ru_date($studio['foundation_date'] ?? null)) $meta[] = ['Основана', $f];
if (!blank($studio['team_size'] ?? '') && (int)$studio['team_size'] > 0) {
    $n = (int)$studio['team_size'];
    $meta[] = ['Команда', $n . ' ' . plural($n, 'человек', 'человека', 'человек')];
}
if (!blank($studio['specialization'] ?? '')) $meta[] = ['Специализация', $studio['specialization']];

$has_banner = !blank($studio['banner_link'] ?? '');
$has_avatar = !blank($studio['avatar_link'] ?? '');
$initials   = mb_strtoupper(mb_substr(trim((string)$studio['name']), 0, 2, 'UTF-8'), 'UTF-8');
$is_active  = (($studio['status'] ?? '') === 'active');
?>
<?php require_once('swad/static/elements/header.php'); ?>

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/swad/css/devpage.css">
<script>document.documentElement.classList.add('dsp-dark');</script>

<main class="dsp">

    <!-- ─────────────── ШАПКА ─────────────── -->
    <header class="dsp-hero">
        <div class="dsp-hero__bg" <?= $has_banner ? 'style="background-image:url(\'' . e($studio['banner_link']) . '\')"' : '' ?>></div>
        <div class="dsp-hero__scrim"></div>

        <div class="dsp-wrap dsp-hero__inner">
            <div class="dsp-ava <?= $has_avatar ? '' : 'is-empty' ?>" data-initials="<?= e($initials) ?>">
                <?php if ($has_avatar): ?>
                    <img src="<?= e($studio['avatar_link']) ?>" alt="<?= e($studio['name']) ?>"
                        onerror="this.closest('.dsp-ava').classList.add('is-empty');this.remove();">
                <?php endif; ?>
            </div>

            <div class="dsp-hero__text">
                <div class="dsp-hero__titleline">
                    <h1 class="dsp-hero__name"><?= e($studio['name']) ?></h1>
                    <span class="dsp-ticker">[<?= e($studio['tiker']) ?>]</span>
                    <?php if ($is_active): ?>
                        <span class="dsp-verified" title="Студия подтверждена Dustore">✔ Проверено</span>
                    <?php endif; ?>
                </div>

                <?php if ($meta): ?>
                    <ul class="dsp-meta">
                        <?php foreach ($meta as [$k, $v]): ?>
                            <li><span><?= e($k) ?></span><?= e($v) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="dsp-cta">
                    <?php if (!blank($studio['website'] ?? '')): ?>
                        <a class="dsp-btn" href="<?= e(ext_url($studio['website'])) ?>" target="_blank" rel="noopener">Сайт студии</a>
                    <?php endif; ?>
                    <?php if (!blank($studio['donate_link'] ?? '')): ?>
                        <a class="dsp-btn dsp-btn--ghost" href="<?= e(ext_url($studio['donate_link'])) ?>" target="_blank" rel="noopener">💰 Поддержать</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Метрики: настоящие, а не заглушки -->
        <div class="dsp-wrap">
            <div class="dsp-stats">
                <div class="dsp-stat">
                    <b><?= $rating_avg !== null ? str_replace('.', ',', (string)$rating_avg) . '<small>/10</small>' : '—' ?></b>
                    <span><?= $rating_total ? 'Рейтинг · ' . nfmt($rating_total) . ' ' . plural($rating_total, 'оценка', 'оценки', 'оценок') : 'Рейтинг' ?></span>
                </div>
                <div class="dsp-stat">
                    <b><?= nfmt($games_count) ?></b>
                    <span><?= plural($games_count, 'Проект', 'Проекта', 'Проектов') ?></span>
                </div>
                <div class="dsp-stat">
                    <b><?= nfmt($downloads) ?></b>
                    <span><?= plural($downloads, 'Скачивание', 'Скачивания', 'Скачиваний') ?></span>
                </div>
                <div class="dsp-stat">
                    <b><?= $recommend !== null ? $recommend . '%' : '—' ?></b>
                    <span>Рекомендаций</span>
                </div>
                <?php if ($gqi_avg !== null): ?>
                    <div class="dsp-stat">
                        <b><?= str_replace('.', ',', (string)$gqi_avg) ?></b>
                        <span>Средний GQI</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ─────────────── КОНТЕНТ ─────────────── -->
    <div class="dsp-wrap dsp-grid">

        <div class="dsp-col">
            <?php if (!blank($studio['description'] ?? '')): ?>
                <section class="dsp-block">
                    <h2 class="dsp-h2">О студии</h2>
                    <div class="dsp-prose"><?= $studio['description'] /* HTML из консоли, уже прошёл strip_tags */ ?></div>
                </section>
            <?php endif; ?>

            <section class="dsp-block">
                <h2 class="dsp-h2">Проекты<?= $games_count ? ' <i>' . $games_count . '</i>' : '' ?></h2>

                <?php if (!$projects): ?>
                    <div class="dsp-empty">
                        <div class="dsp-empty__ico">🎮</div>
                        У студии пока нет опубликованных проектов
                    </div>
                <?php else: ?>
                    <div class="dsp-projects">
                        <?php foreach ($projects as $p): ?>
                            <a class="dsp-card" href="/g/<?= (int)$p['id'] ?>">
                                <div class="dsp-card__cover"
                                    <?= blank($p['path_to_cover']) ? '' : 'style="background-image:url(\'' . e($p['path_to_cover']) . '\')"' ?>>
                                    <?php if (blank($p['path_to_cover'])): ?><span>🎮</span><?php endif; ?>
                                    <?php if (!blank($p['gqi'])): ?>
                                        <span class="dsp-gqi">GQI <?= e($p['gqi']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="dsp-card__body">
                                    <h3><?= e($p['name']) ?></h3>
                                    <?php if (!blank($p['short_description'])): ?>
                                        <p><?= e($p['short_description']) ?></p>
                                    <?php endif; ?>
                                    <div class="dsp-card__foot">
                                        <?php if (!blank($p['genre'])): ?><span class="dsp-tag"><?= e($p['genre']) ?></span><?php endif; ?>
                                        <span class="dsp-card__num">
                                            <?php if (!blank($p['rating'])): ?>★ <?= e(str_replace('.', ',', (string)$p['rating'])) ?> · <?php endif; ?>
                                            <?= nfmt($p['installs']) ?> ↓
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="dsp-col dsp-col--side">

            <?php if ($contacts): ?>
                <section class="dsp-panel">
                    <h3 class="dsp-h3">Обратная связь</h3>
                    <ul class="dsp-contacts">
                        <?php foreach ($contacts as [$ico, $label, $text, $href]): ?>
                            <li>
                                <span class="dsp-contacts__ico"><?= $ico ?></span>
                                <span class="dsp-contacts__body">
                                    <span class="dsp-contacts__label"><?= e($label) ?></span>
                                    <?php if ($href): ?>
                                        <a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($text) ?></a>
                                    <?php else: ?>
                                        <span><?= e($text) ?></span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="dsp-panel">
                <h3 class="dsp-h3">Награды</h3>
                <?php if (!$badges): ?>
                    <div class="dsp-empty dsp-empty--sm">
                        <div class="dsp-empty__ico">🏆</div>
                        Трофеев пока нет
                    </div>
                <?php else: ?>
                    <ul class="dsp-badges">
                        <?php foreach ($badges as $b): ?>
                            <li title="<?= e($b['description']) ?>">
                                <?php if (!blank($b['icon_url'])): ?>
                                    <img src="<?= e($b['icon_url']) ?>" alt="" onerror="this.style.visibility='hidden'">
                                <?php else: ?>
                                    <span class="dsp-badges__ph">🏆</span>
                                <?php endif; ?>
                                <span>
                                    <b><?= e($b['badge_name']) ?></b>
                                    <?php if (!blank($b['description'])): ?><i><?= e($b['description']) ?></i><?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($vk_id !== ''): ?>
                <section class="dsp-panel dsp-panel--vk">
                    <h3 class="dsp-h3">Сообщество VK</h3>
                    <div id="vk_groups"></div>
                </section>
            <?php endif; ?>

            <section class="dsp-panel">
                <h3 class="dsp-h3">Статьи на Dustore.Media</h3>
                <div class="dsp-empty dsp-empty--sm">
                    <div class="dsp-empty__ico">📰</div>
                    Раздел пока недоступен
                </div>
            </section>

        </aside>
    </div>
</main>

<?php require_once('swad/static/elements/footer.php'); ?>

<?php if ($vk_id !== ''): ?>
    <!-- Виджет грузим только когда есть ID: раньше пустой vk_public_id
         давал VK.Widgets.Group(..., ) → SyntaxError и убивал весь скрипт. -->
    <script src="https://vk.com/js/api/openapi.js?168"></script>
    <script>
        window.addEventListener('load', function () {
            if (!window.VK || !VK.Widgets) return;
            try {
                VK.Widgets.Group('vk_groups', { mode: 3, width: 'auto', height: 400, color3: 'c32178' }, <?= (int)$vk_id ?>);
            } catch (e) { console.warn('VK widget:', e); }
        });
    </script>
<?php endif; ?>

<script>
    (function () {
        var els = document.querySelectorAll('.dsp-card, .dsp-stat, .dsp-panel');
        if (!('IntersectionObserver' in window)) return;
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
            });
        }, { rootMargin: '0px 0px -40px 0px' });
        els.forEach(function (el) { el.classList.add('dsp-anim'); io.observe(el); });
    })();
</script>
