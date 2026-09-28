<?php
/* ======================================================================
   swad/fx/pages.php — тела страниц-хабов, общие для сайта и мобильного PWA.

   Раньше каждая страница (dev.php, player.php, fid/index.php) держала разметку у себя, и в /m
   пришлось бы писать вторую копию — а вторые копии разъезжаются. Теперь страницы сайта и /m/*
   зовут одни и те же функции; отличается только рамка (шапка сайта или оболочка приложения)
   и флаг $o['m'], который переключает адреса ссылок (Fx::mobile) и пару кнопок.

     FxPages::studio($pdo, $tiker, ['m' => bool])     → ['html', 'name', 'desc', 'image', …] | null
     FxPages::player($pdo, $username, ['m' => bool])  → ['html', 'modals', 'name', 'owner', …] | null
     FxPages::fid($viewer, ['m' => bool, 'tab' => '', 'open' => 0])  → ['html', 'tab']

   Функции возвращают готовый HTML (буфер), ничего не печатают и не делают exit —
   404 и редиректы остаются за вызывающей страницей.
   ====================================================================== */
require_once __DIR__ . '/page.php';

/* ── хелперы (были глобальными функциями в страницах; guard — чтобы не падать при повторном подключении) ── */
if (!function_exists('e')) {
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
}

/** Пусто? Ловит '', null, пробелы и мусорные даты MySQL. */
if (!function_exists('blank')) {
function blank($v): bool
{
    if ($v === null) return true;
    $v = trim((string)$v);
    return $v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00';
}
}

/** Запрос, который не роняет страницу, если таблицы/колонки нет. */
if (!function_exists('dq')) {
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
}

/** Добивает схему — иначе href уезжает на dustore.ru/example.com. */
if (!function_exists('ext_url')) {
function ext_url(string $u): string
{
    $u = trim($u);
    if ($u === '') return '';
    return preg_match('~^https?://~i', $u) ? $u : 'https://' . ltrim($u, '/');
}
}

if (!function_exists('ru_date')) {
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
}


/** Ссылка из профиля: пропускаем только http(s).
 *  htmlspecialchars экранирует кавычки, но схему не проверяет — website и vk
 *  задаёт сам пользователь, и javascript:... в href выполнялся бы у каждого,
 *  кто откроет чужой профиль и кликнет по ссылке. */
if (!function_exists('safe_link')) {
function safe_link(?string $u): string
{
    $u = trim((string)$u);
    return preg_match('~^https?://~i', $u) ? $u : '';
}
}

if (!function_exists('ngettext_ru')) {
function ngettext_ru(int $n, string $one, string $few, string $many): string
{
    $n  = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 19) return $many;
    if ($n1 === 1) return $one;
    if ($n1 >= 2 && $n1 <= 4) return $few;
    return $many;
}
}

if (!function_exists('format_last_seen')) {
function format_last_seen(int $ts): string
{
    $diff = time() - $ts;
    if ($diff < 60)     return 'только что';
    if ($diff < 120)    return 'минуту назад';
    if ($diff < 3600)   { $m = floor($diff/60);   return $m.' '.ngettext_ru($m,'минуту','минуты','минут').' назад'; }
    if ($diff < 7200)   return 'час назад';
    if ($diff < 86400)  { $h = floor($diff/3600);  return $h.' '.ngettext_ru($h,'час','часа','часов').' назад'; }
    if ($diff < 172800) return 'вчера в '.date('H:i', $ts);
    if (date('Y',$ts) === date('Y')) return date('d.m',$ts).' в '.date('H:i',$ts);
    return date('d.m.y',$ts).' в '.date('H:i',$ts);
}
}

final class FxPages
{
    /* ── куски хаба игры, общие для /g/N (game.php) и /m/game/N ── */

    /** Карточка «Статистика» бокового блока. $st — FxStats::game(). */
    public static function gameStats(array $st, bool $owner, int $id): string
    {
        $fxStats = $st; $fxOwner = $owner; $fxId = $id;
        $fxSpark = static fn(array $v, string $cls) => array_sum($v) > 0 ? FxRenderHub::spark($v, $cls) : '<div class="fx-cap">Пока нет данных</div>';
        ob_start();
        ?>
    <section class="fx-card">
        <div class="fx-card__h"><?= fx_icon('chart', 'ic--sm') ?> Статистика <?= $fxOwner ? FxRenderHub::gear(FxPage::console('analytics', ['game' => $fxId]), 'Полная аналитика', 'fx-gear--icon') : '' ?></div>
        <div class="fx-pair">
            <div>
                <div class="fx-big"><b><?= Fx::n($fxStats['downloads_total']) ?></b></div>
                <div class="fx-cap">скачиваний за 30 дней</div>
                <?= $fxSpark($fxStats['downloads'], '') ?>
            </div>
            <div>
                <div class="fx-big"><b><?= Fx::n($fxStats['players_30']) ?></b></div>
                <div class="fx-cap">играли за 30 дней</div>
                <?= $fxSpark($fxStats['players'], 'fx-spark--blue') ?>
            </div>
        </div>
        <div class="fx-kv" style="margin-top:10px"><span>Обновления · полгода</span><b><?= (int)$fxStats['updates_n'] ?></b></div>
        <?= FxRenderHub::bars($fxStats['updates']) ?>
        <div class="fx-cap"><?= $fxStats['last_update'] ? 'Последнее — ' . Fx::e(Fx::day($fxStats['last_update'])) : 'Обновлений пока не было' ?></div>
        <div class="fx-kv" style="margin-top:8px"><span>В библиотеках</span><b><?= Fx::n($fxStats['owners']) ?><?= $fxStats['owners_new'] ? ' <small class="fx-delta">+' . Fx::n($fxStats['owners_new']) . '</small>' : '' ?></b></div>
    </section>
        <?php
        return ob_get_clean();
    }

    /** Карточка «Модераторы игры». Владельцу показываем всегда (там же — шестерёнка в консоль), остальным — только если модераторы есть. */
    public static function gameMods(array $mods, bool $owner, int $id): string
    {
        $fxMods = $mods; $fxOwner = $owner; $fxId = $id;
        ob_start();
        ?>
    <?php if ($fxMods || $fxOwner): ?>
    <section class="fx-card">
        <div class="fx-card__h"><?= fx_icon('shield', 'ic--sm') ?> Модераторы игры <?= $fxOwner ? FxRenderHub::gear(FxPage::console('coop', ['game' => $fxId], 'mods'), 'Назначить модераторов', 'fx-gear--icon') : '' ?></div>
        <?php if ($fxMods): ?>
            <div class="fx-people">
                <?php foreach ($fxMods as $m): ?>
                    <a class="fx-person" href="<?= Fx::e($m['url']) ?>"><?= FxRender::avatar($m, 30, false) ?><span class="fx-person__n"><?= Fx::e($m['name']) ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="fx-note">Модераторы следят за стеной и обсуждениями этой игры. Назначаются в консоли, раздел «Соучастники проектов».</div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Содержимое панелей «Лента» (channel=wall) и «Обсуждения» (channel=forum): композер + первая страница SSR.
     * $wall — результат FxFeed::wall(); $team — viewer из команды студии (пишет от её имени, кросспост в общую ленту).
     */
    public static function gameFeed(array $game, int $id, string $channel, array $wall, int $viewer, bool $team): string
    {
        $fxId = $id; $gameStudio = (int)($game['developer'] ?? 0); $fxTeam = $team;
        $fxCtx = ['viewer' => $viewer, 'show_reason' => false, 'wall' => ['type' => 'game', 'id' => $id, 'channel' => 'wall'], 'wall_game' => $id];
        $fxWall = $fxForum = $wall;
        ob_start();
        if ($channel === 'forum') {
            ?>
    <?= FxRenderHub::composer([
        'wall_type' => 'game', 'wall_id' => $fxId, 'channel' => 'forum', 'viewer' => FxPage::viewer(), 'title' => true,
        'poll' => false, 'photo' => true, 'article' => false, 'submit' => 'Создать тему',
        'placeholder' => 'Расскажите подробнее, о чём хотите поговорить…', 'hint' => 'Новая тема обсуждения',
    ]) ?>
    <div class="fx-feed" data-ssr="1" data-feed='<?= Fx::e(json_encode(['scope' => 'wall', 'wall_type' => 'game', 'wall_id' => $fxId, 'channel' => 'forum'])) ?>'>
        <?= $fxForum['posts']
            ? FxRender::posts($fxForum['posts'], ['threads' => true] + $fxCtx) . FxRender::more($fxForum['next'])
            : FxRender::empty('branch', 'Обсуждений пока нет', 'Создайте тему — ветки раскрываются прямо в списке, а модераторы игры могут закреплять важное.') ?>
    </div>
            <?php
        } else {
            ?>
    <?= FxRenderHub::composer([
        'wall_type' => 'game', 'wall_id' => $fxId, 'channel' => 'wall', 'viewer' => FxPage::viewer(),
        'as' => $fxTeam ? [['id' => $gameStudio, 'name' => $game['studio_name'], 'type' => 'studio']] : [],
        'crosspost' => $fxTeam ? 'Показать и в общей ленте' : null,
        'poll' => true, 'photo' => true, 'article' => false,
        'placeholder' => 'Напишите на стене игры…', 'hint' => 'Стена игры — писать может любой игрок',
    ]) ?>
    <div class="fx-feed" data-ssr="1" data-feed='<?= Fx::e(json_encode(['scope' => 'wall', 'wall_type' => 'game', 'wall_id' => $fxId, 'channel' => 'wall'])) ?>'>
        <?= $fxWall['posts']
            ? FxRender::posts($fxWall['posts'], $fxCtx) . FxRender::more($fxWall['next'])
            : FxRender::empty('chat', 'На стене пока пусто', 'Напишите первым: отзыв о билде, находку, вопрос разработчикам.') ?>
    </div>
            <?php
        }
        return ob_get_clean();
    }

    /** Хаб студии (/d/<тикер> и /m/dev/<тикер>). null — студии нет. $o: ['m' => true] — разметка для PWA. */
    public static function studio(PDO $pdo, string $tiker, array $o = []): ?array
    {
        $m = !empty($o['m']);
        if ($m) Fx::mobile(true);
        $loginUrl = Fx::urlLogin();
        $tiker = trim($tiker);
$rows   = !empty($o['id'])
            ? dq($pdo, "SELECT * FROM studios WHERE id = ? LIMIT 1", [(int)$o['id']])
            : dq($pdo, "SELECT * FROM studios WHERE tiker = ? LIMIT 1", [$tiker]);
$studio = $rows[0] ?? null;

if (!$studio) return null;
$sid = (int)$studio['id'];

/* Публично — только опубликованные игры */
$projects = dq($pdo, "
    SELECT g.id, g.gqi,
           (SELECT COUNT(*) FROM library l WHERE l.game_id = g.id) AS installs
    FROM games g
    WHERE g.developer = ? AND g.status = 'published'
", [$sid]);
$games_count = count($projects);
$downloads = 0;
foreach ($projects as $p) $downloads += (int)$p['installs'];

/* Шкала оценок на Dustore — 1..10: «рекомендую» — от 7 */
$RECOMMEND_FROM = 7;
$rt = dq($pdo, "
    SELECT COUNT(*) total, AVG(r.rating) avg_rating, SUM(r.rating >= " . $RECOMMEND_FROM . ") good
    FROM ratings r JOIN games g ON g.id = r.game_id
    WHERE g.developer = ?
", [$sid])[0] ?? ['total' => 0, 'avg_rating' => null, 'good' => 0];
$rating_total = (int)$rt['total'];
$rating_avg   = $rating_total ? round((float)$rt['avg_rating'], 1) : null;
$recommend    = $rating_total ? round((int)$rt['good'] / $rating_total * 100) : null;

$awards = array_map(static fn($b) => [
    'name' => $b['badge_name'], 'description' => (string)$b['description'], 'icon_url' => (string)$b['icon_url'],
], dq($pdo, "
    SELECT b.icon_url, b.name AS badge_name, b.description
    FROM given_badges sb JOIN badges b ON sb.badge_id = b.id
    WHERE sb.studio_id = ? ORDER BY sb.awarded_at DESC
", [$sid]));

/* ── хаб: права, монетизация, статистика, команда, девблог ── */
$fxV       = Fx::uid();
$fxRole    = FxAuth::studioRole($sid, $fxV);                         // Владелец | Администратор | Модератор | Участник | null
$fxTeamMe  = $fxRole !== null || Fx::isAdmin($fxV);                  // пишет в девблог от имени студии
$fxOwner   = FxAuth::isStudioOwner($sid, $fxV);                      // видит шестерёнки в консоль
$fxS       = FxPeople::studio($sid);
$fxMonet   = FxMonet::get($sid);
$fxStats   = FxStats::studio($sid);
$fxTeam    = FxStats::team($sid);
$fxLinks   = FxStats::feedback($studio);
$fxGAwards = FxStats::gameAwards($sid);
$fxFollowing = FxFollow::is($fxV, 'studio', $sid);
if (!$fxTeamMe) FxStats::bump('studio', $sid);

$fxDev     = FxFeed::wall('studio', $sid, 'devlog', $fxV);
FxPosts::view(array_column($fxDev['posts'], 'id'));
$fxCtx     = ['viewer' => $fxV, 'show_reason' => false, 'wall' => ['type' => 'studio', 'id' => $sid, 'channel' => 'devlog']];

/* Вкладки основного блока. «Закрытая комната» — платный контент студии: место под неё уже есть,
   пока вкладка неактивна. Когда комната заработает — достаточно поставить $roomLive = true и отдать её содержимое. */
$roomLive = false;
$fxTabs   = ['devlog' => ['megaphone', 'Девблог']];
if (!empty($fxMonet['room_on'])) $fxTabs['room'] = ['coin', 'Закрытая комната'];

$fxGear = static fn(string $to, string $label, string $anchor = '', string $cls = 'fx-gear--icon') =>
    $fxOwner ? FxRenderHub::gear(FxPage::console($to, ['studio' => $sid], $anchor), $label, $cls) : '';

$fxBanner = (string)($studio['banner_link'] ?? '');
$fxLogo   = (string)($studio['avatar_link'] ?? '');
$initials = mb_strtoupper(mb_substr(trim((string)$studio['name']), 0, 2, 'UTF-8'), 'UTF-8');
$donateOn = !empty($fxMonet['donate_on']) && !empty($fxMonet['donate_url']);

$loc = array_filter([trim((string)($studio['city'] ?? '')), trim((string)($studio['country'] ?? ''))], fn($x) => $x !== '');
$foundYear = !blank($studio['foundation_date'] ?? null) ? substr((string)$studio['foundation_date'], 0, 4) : '';

$linkIcon = ['tg' => 'send-tg', 'mail' => 'mail', 'vk' => 'users', 'discord' => 'chat', 'link' => 'globe'];
$plain = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', (string)($studio['description'] ?? '')))));
$metaDesc = $plain !== '' ? mb_substr($plain, 0, 160) : 'Студия ' . $studio['name'] . ' на Dustore';

        ob_start();
        ?>
        <div class="fx fx-hub fx-hub--profile<?= $m ? ' fx-m' : '' ?>" data-studio="<?= $sid ?>">

            <!-- ══ БАННЕР ══ -->
            <section class="fx-banner">
                <div class="fx-banner__bg<?= $fxBanner !== '' ? '' : ' fx-banner__bg--soft' ?>" <?= ($fxBanner ?: $fxLogo) !== '' ? 'style="--bn:url(\'' . e($fxBanner ?: $fxLogo) . '\')"' : '' ?>></div>
                <?php if ($fxOwner): ?>
                    <div class="fx-gear-wrap"><?= FxRenderHub::gear(FxPage::console('mystudio', ['studio' => $sid]), 'Настроить страницу студии') ?></div>
                <?php endif; ?>
                <div class="fx-banner__in">
                    <div class="fx-banner__id">
                        <div class="fx-icon fx-px" role="img" aria-label="<?= e($studio['name']) ?>" <?= $fxLogo !== '' ? 'style="background-image:url(\'' . e($fxLogo) . '\')"' : '' ?>>
                            <?php if ($fxLogo === ''): ?><span class="fx-icon__ph"><?= e($initials) ?></span><?php endif; ?>
                        </div>
                        <div class="fx-banner__t">
                            <h1>
                                <?= e($studio['name']) ?>
                                <span class="fx-tick">[<?= e($studio['tiker']) ?>]</span>
                                <?= FxRender::verified($fxS['verified'] ?? null) ?>
                            </h1>
                            <div class="fx-banner__by">
                                <span><b data-followers="studio:<?= $sid ?>"><?= Fx::n($fxStats['followers']) ?></b>
                                    <?= Fx::plural((int)$fxStats['followers'], 'подписчик', 'подписчика', 'подписчиков') ?></span>
                            </div>
                            <?php if (!blank($studio['specialization'] ?? '') || $loc || $foundYear !== ''): ?>
                                <div class="fx-chips">
                                    <?php if (!blank($studio['specialization'] ?? '')): ?><span class="fx-chip"><?= e($studio['specialization']) ?></span><?php endif; ?>
                                    <?php if ($loc): ?><span class="fx-chip"><?= e(implode(', ', $loc)) ?></span><?php endif; ?>
                                    <?php if ($foundYear !== ''): ?><span class="fx-chip"><?= fx_icon('clock', 'ic--sm') ?>с <?= e($foundYear) ?></span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- действия вынесены на уровень сетки баннера: на телефоне они идут отдельной строкой под логотипом -->
                        <div class="fx-banner__act">
                            <?php if ($fxV): ?>
                                <button type="button" class="fx-btn fx-subscribe<?= $fxFollowing ? ' on' : '' ?>" data-fx="follow" data-t="studio" data-id="<?= $sid ?>">
                                    <?= $fxFollowing ? fx_icon('check', 'ic--sm') . ' Вы подписаны' : fx_icon('plus', 'ic--sm') . ' Подписаться' ?>
                                </button>
                            <?php else: ?>
                                <a class="fx-btn" href="<?= e($loginUrl) ?>"><?= fx_icon('plus', 'ic--sm') ?> Подписаться</a>
                            <?php endif; ?>
                            <button type="button" class="fx-btn fx-btn--ghost" data-fx="tpl" data-tpl="#fx-about"><?= fx_icon('studio', 'ic--sm') ?> О студии</button>
                            <?php if ($m && $fxV): ?>
                                <a class="fx-btn fx-btn--ghost fx-btn--icon" href="/m/chat?studio=<?= $sid ?>" title="Написать студии" aria-label="Написать студии"><?= fx_icon('chat', 'ic--sm') ?><span>Написать</span></a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="fx-banner__side fx-banner__side--studio">
                        <?= FxRenderHub::awardsStack($awards, 5) ?>
                        <?php if ($donateOn || !empty($fxMonet['room_on']) || $fxOwner): ?>
                            <div class="fx-mon">
                                <?php if ($donateOn): ?>
                                    <a class="fx-btn fx-btn--coin" href="<?= e($fxMonet['donate_url']) ?>" target="_blank" rel="noopener nofollow"><?= fx_icon('coin', 'ic--sm') ?> Задонатить</a>
                                    <?php if (!empty($fxMonet['donate_note'])): ?><small class="fx-mon__note"><?= e($fxMonet['donate_note']) ?></small><?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($fxMonet['room_on'])): ?>
                                    <button type="button" class="fx-btn fx-btn--ghost fx-btn--room" disabled title="Закрытая комната откроется позже">
                                        <?= fx_icon('coin', 'ic--sm') ?> Закрытая комната<?= $fxMonet['room_price'] !== null && (int)$fxMonet['room_price'] > 0 ? ' · ' . Fx::n($fxMonet['room_price']) : '' ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($fxOwner && !$donateOn && empty($fxMonet['room_on'])): ?>
                                    <a class="fx-mon__setup" href="<?= e(FxPage::console('monetization', ['studio' => $sid], 'donate')) ?>" target="_blank" rel="noopener">
                                        <?= fx_icon('coin', 'ic--sm') ?><span><b>Донаты и закрытая комната</b><small>Настроить в консоли — видно только вам</small></span>
                                    </a>
                                <?php elseif ($fxOwner): ?>
                                    <?= FxRenderHub::gear(FxPage::console('monetization', ['studio' => $sid], $donateOn ? 'donate' : 'room'), 'Настроить донаты и комнату', 'fx-gear--icon fx-mon__gear') ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="fx-cols">

                <!-- ══ МАЛЕНЬКИЙ БЛОК (на телефоне — сверху, горизонтальная прокрутка) ══ -->
                <aside class="fx-side">

                    <section class="fx-card">
                        <div class="fx-card__h"><?= fx_icon('chart', 'ic--sm') ?> Студия в цифрах <?= $fxGear('analytics', 'Полная аналитика') ?></div>
                        <div class="fx-pair">
                            <div>
                                <div class="fx-big"><b><?= Fx::n(count($fxTeam)) ?></b></div>
                                <div class="fx-cap"><?= Fx::plural(count($fxTeam), 'участник', 'участника', 'участников') ?> в команде</div>
                            </div>
                            <div>
                                <div class="fx-big"><b><?= Fx::n($fxStats['followers']) ?></b></div>
                                <div class="fx-cap"><?= Fx::plural((int)$fxStats['followers'], 'подписчик', 'подписчика', 'подписчиков') ?></div>
                            </div>
                        </div>
                        <div class="fx-kv" style="margin-top:10px"><span>Проектов</span><b><?= Fx::n($games_count) ?></b></div>
                        <div class="fx-kv"><span>Скачиваний</span><b><?= Fx::n($downloads) ?></b></div>
                        <div class="fx-kv"><span>Игроков в проектах</span><b><?= Fx::n($fxStats['players']) ?></b></div>
                        <?php if ($rating_avg !== null): ?>
                            <div class="fx-kv"><span>Рейтинг · <?= Fx::n($rating_total) ?> <?= Fx::plural($rating_total, 'оценка', 'оценки', 'оценок') ?></span><b><?= str_replace('.', ',', (string)$rating_avg) ?><small>/10</small></b></div>
                            <div class="fx-kv"><span>Рекомендуют</span><b><?= (int)$recommend ?>%</b></div>
                        <?php endif; ?>
                        <div class="fx-kv" style="margin-top:8px"><span>Просмотры профиля · 30 дн.</span><b><?= Fx::n($fxStats['views_30']) ?></b></div>
                        <?= array_sum($fxStats['views']) > 0 ? FxRenderHub::spark($fxStats['views'], 'fx-spark--blue') : '' ?>
                    </section>

                    <section class="fx-card">
                        <div class="fx-card__h"><?= fx_icon('users', 'ic--sm') ?> Команда <?= $fxGear('staff', 'Управлять командой') ?></div>
                        <?php if ($fxTeam): ?>
                            <div class="fx-people">
                                <?php foreach (array_slice($fxTeam, 0, 8) as $m): ?>
                                    <a class="fx-person" href="<?= e($m['url']) ?>">
                                        <?= FxRender::avatar($m, 34, false) ?>
                                        <span class="fx-person__b">
                                            <span class="fx-person__n"><?= e($m['name']) ?></span>
                                            <small><?= e($m['title'] !== '' ? $m['title'] : $m['role']) ?></small>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($fxTeam) > 8): ?><div class="fx-cap">и ещё <?= count($fxTeam) - 8 ?></div><?php endif; ?>
                        <?php else: ?>
                            <div class="fx-note">Состав команды пока не указан.</div>
                        <?php endif; ?>
                    </section>

                    <section class="fx-card">
                        <div class="fx-card__h"><?= fx_icon('chat', 'ic--sm') ?> Обратная связь <?= $fxGear('monetization', 'Ссылки обратной связи', 'links') ?></div>
                        <?php if ($fxLinks): ?>
                            <div class="fx-links">
                                <?php foreach ($fxLinks as $l): ?>
                                    <a class="fx-link" href="<?= e($l['url']) ?>" target="_blank" rel="noopener nofollow">
                                        <?= fx_icon($linkIcon[$l['kind']] ?? 'globe', 'ic--sm') ?><span><?= e($l['label']) ?></span><?= fx_icon('external', 'ic--sm') ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="fx-note">Студия пока не оставила ссылок для связи. Напишите ей в девблоге — команда увидит.</div>
                        <?php endif; ?>
                    </section>

                    <section class="fx-card fx-card--bubbles">
                        <div class="fx-card__h"><?= fx_icon('gamepad', 'ic--sm') ?> Проекты <?= $fxGear('projects', 'Проекты студии') ?></div>
                        <?php if ($fxStats['games']): ?>
                            <?= FxRenderHub::circles($fxStats['games']) ?>
                            <div class="fx-cap">Размер кружка — сколько людей играет</div>
                        <?php else: ?>
                            <div class="fx-note">Опубликованных проектов пока нет.</div>
                        <?php endif; ?>
                    </section>

                    <?php if ($fxGAwards): ?>
                        <section class="fx-card">
                            <div class="fx-card__h"><?= fx_icon('award', 'ic--sm') ?> Награды проектов</div>
                            <div class="fx-gawards">
                                <?php foreach ($fxGAwards as $a): ?>
                                    <a class="fx-gaward" href="<?= e(Fx::urlGame($a['game_id'])) ?>">
                                        <span class="fx-award"><span><?= fx_icon('award') ?></span></span>
                                        <span><b><?= e($a['title']) ?> · <?= e($a['game']) ?></b><small><?= e($a['note']) ?></small></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </aside>

                <!-- ══ ОСНОВНОЙ БЛОК ══ -->
                <div class="fx-main">
                    <nav class="fx-tabs" role="tablist">
                        <?php foreach ($fxTabs as $k => [$ic_, $label_]): ?>
                            <?php if ($k === 'room' && !$roomLive): ?>
                                <span class="fx-tab fx-tab--soon" role="tab" aria-disabled="true" title="Скоро"><?= fx_icon($ic_, 'ic--sm') ?><?= e($label_) ?> <em>скоро</em></span>
                            <?php else: ?>
                                <button type="button" class="fx-tab<?= $k === 'devlog' ? ' on' : '' ?>" role="tab" data-fx="tab" data-pane="<?= $k ?>"><?= fx_icon($ic_, 'ic--sm') ?><?= e($label_) ?></button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>

                    <!-- Девблог: пишут только сотрудники студии; посты из медиа от имени студии лежат здесь же -->
                    <div class="fx-pane" data-pane="devlog">
                        <?php if ($fxTeamMe): ?>
                            <?= FxRenderHub::composer([
                                'wall_type' => 'studio', 'wall_id' => $sid, 'channel' => 'devlog', 'viewer' => FxPage::viewer(),
                                'as_fixed' => ['name' => $studio['name'], 'role' => FxAuth::roleLabel($sid, $fxV) ?: ($fxRole ?: 'Администратор платформы')],
                                'crosspost' => 'Показать и в общей ленте',
                                'poll' => true, 'photo' => true, 'article' => true,
                                'placeholder' => 'Что нового в студии? Апдейты, планы, закулисье…', 'submit' => 'Опубликовать',
                            ]) ?>
                        <?php endif; ?>
                        <div class="fx-feed" data-ssr="1" data-feed='<?= Fx::e(json_encode(['scope' => 'wall', 'wall_type' => 'studio', 'wall_id' => $sid, 'channel' => 'devlog'])) ?>'>
                            <?= $fxDev['posts']
                                ? FxRender::posts($fxDev['posts'], $fxCtx) . FxRender::more($fxDev['next'])
                                : FxRender::empty('megaphone', 'В девблоге пока пусто', $fxTeamMe
                                    ? 'Расскажите, над чем работает студия: под записью автоматически появится ваша роль.'
                                    : 'Как только студия опубликует запись, она появится здесь. Подпишитесь — и она попадёт в вашу ленту.') ?>
                        </div>
                    </div>
                </div><!-- /fx-main -->
            </div>
        </div>

        <!-- «О студии» -->
        <template id="fx-about">
            <h2 class="fx-hm__t"><?= e($studio['name']) ?> <span class="fx-tick">[<?= e($studio['tiker']) ?>]</span></h2>
            <?php if (!blank($studio['description'] ?? '')): ?>
                <div class="fx-prose"><?= $studio['description'] /* HTML из консоли, уже прошёл strip_tags */ ?></div>
            <?php else: ?>
                <p class="fx-note">Студия пока не рассказала о себе.</p>
            <?php endif; ?>
            <?php
            $facts = [];
            if ($f = ru_date($studio['foundation_date'] ?? null)) $facts[] = ['Основана', $f];
            if ((int)($studio['team_size'] ?? 0) > 0) $facts[] = ['Команда', (int)$studio['team_size'] . ' ' . Fx::plural((int)$studio['team_size'], 'человек', 'человека', 'человек')];
            if (!blank($studio['specialization'] ?? '')) $facts[] = ['Специализация', $studio['specialization']];
            if ($loc) $facts[] = ['Где находится', implode(', ', $loc)];
            if (!blank($studio['website'] ?? '')) $facts[] = ['Сайт', '<a href="' . e(ext_url($studio['website'])) . '" target="_blank" rel="noopener nofollow">' . e(preg_replace('~^https?://(www\.)?~i', '', ext_url($studio['website']))) . '</a>'];
            ?>
            <?php if ($facts): ?>
                <div class="fx-facts">
                    <?php foreach ($facts as [$k, $v]): ?><div class="fx-kv"><span><?= e($k) ?></span><b><?= $k === 'Сайт' ? $v : e($v) ?></b></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </template>
        <?php
        return [
            'id' => $sid, 'name' => (string)$studio['name'], 'tiker' => (string)$studio['tiker'],
            'desc' => $metaDesc, 'image' => $fxLogo, 'html' => ob_get_clean(),
        ];
    }

    /** Хаб игрока (/player/<ник> и /m/player/<ник>). null — игрока нет. */
    public static function player(PDO $pdo, string $username, array $o = []): ?array
    {
        require_once __DIR__ . '/../controllers/user.php';
        require_once __DIR__ . '/../controllers/time.php';          // часовой пояс сайта (Europe/Moscow)
        require_once __DIR__ . '/../controllers/organization.php';
        $m = !empty($o['m']);
        if ($m) Fx::mobile(true);
        $loginUrl = Fx::urlLogin();
        if ($username === '') return null;
        Fx::use($pdo);

$stmt = $pdo->prepare("
    SELECT
        u.id, u.username, u.telegram_id, u.profile_picture, u.added,
        u.telegram_username, u.city, u.country, u.vk, u.website,
        u.first_name, u.last_name,
        COUNT(DISTINCT g.id) as games_count,
        COUNT(DISTINCT r.id) as reviews_count
    FROM users u
    LEFT JOIN games g ON g.developer = u.id
    LEFT JOIN game_reviews r ON r.user_id = u.id
    WHERE u.username = :username
    GROUP BY u.id
");
$stmt->execute([':username' => $username]);
$user = $stmt->fetch();

if (!$user) return null;

$uid    = (int)$user['id'];
$fxV    = Fx::uid();
$is_owner = false;
if (!empty($_SESSION['USERDATA']['id'])) {
    $is_owner = ((int)$_SESSION['USERDATA']['id'] == $uid);
}

$stmt = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM library WHERE player_id = :user_id) as library_count,
        (SELECT COUNT(*) FROM achievements WHERE player_id = :user_id) as achievements_count,
        (SELECT COUNT(*) FROM game_reviews WHERE user_id = :user_id) as reviews_count,
        (SELECT COUNT(*) FROM friends WHERE
            (player_id = :user_id OR friend_id = :user_id) AND status = 'accepted') as friends_count
");
$stmt->execute([':user_id' => $uid]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT
        g.id, g.name, g.description, g.path_to_cover, g.price, g.GQI, g.release_date,
        COALESCE(AVG(r.rating), 0) AS rating,
        MAX(l.date) AS last_added
    FROM library l
    JOIN games g ON g.id = l.game_id
    LEFT JOIN game_reviews r ON r.game_id = g.id
    WHERE l.player_id = :user_id AND l.purchased = 1
    GROUP BY g.id, g.name, g.description, g.path_to_cover, g.price, g.GQI, g.release_date
    ORDER BY last_added DESC
    LIMIT 50
");
$stmt->execute([':user_id' => $uid]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT r.*, g.name as game_title, g.path_to_cover as game_cover
    FROM game_reviews r
    JOIN games g ON g.id = r.game_id
    WHERE r.user_id = :user_id
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute([':user_id' => $uid]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT gub.*, b.name, b.description, b.icon_url
    FROM given_user_badges gub
    JOIN badges b ON gub.badge_id = b.id
    WHERE gub.user_id = :user_id
    ORDER BY gub.awarded_at DESC
");
$stmt->execute([':user_id' => $uid]);
$achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.first_name, u.last_name, u.profile_picture
    FROM friends f
    JOIN users u ON u.id = CASE WHEN f.player_id = :user_id THEN f.friend_id ELSE f.player_id END
    WHERE (f.player_id = :user_id OR f.friend_id = :user_id) AND f.status = 'accepted'
    ORDER BY u.first_name ASC
");
$stmt->execute([':user_id' => $uid]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

$incomingRequests = [];
if ($is_owner) {
    $curr_user = new User();
    $org       = new Organization();
    $user_data = $_SESSION['USERDATA'];
    $userID    = $uid;

    $stmt = $pdo->prepare("
        SELECT f.id, u.id as user_id, u.username, u.first_name, u.last_name, u.profile_picture
        FROM friends f
        JOIN users u ON u.id = f.player_id
        WHERE f.friend_id = :me AND f.status = 'pending'
        ORDER BY f.id DESC
    ");
    $stmt->execute([':me' => $uid]);
    $incomingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$status = null;   // статус дружбы зрителя с этим игроком
if (!empty($_SESSION['USERDATA']['id']) && !$is_owner) {
    $u      = new User();
    $status = $u->getFriendStatus($_SESSION['USERDATA']['id'], $uid);
}

/* ── статус «в сети» (как и раньше — только зарегистрированным зрителям) ── */
$presence = ['on' => false, 'text' => ''];
if (!empty($_SESSION['USERDATA']['id'])) {
    $stmt = $pdo->prepare("SELECT current_app, last_seen FROM user_activity WHERE user_id = ? ORDER BY last_seen DESC LIMIT 1");
    $stmt->execute([$uid]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT updated FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$uid]);
    $webactivity = $stmt->fetch(PDO::FETCH_ASSOC);

    $launcher_time = $activity    ? strtotime($activity['last_seen'])  : 0;
    $web_time      = $webactivity ? strtotime((string)$webactivity['updated']) : 0;
    $last_active   = max($launcher_time, $web_time);

    $is_online_launcher = $launcher_time && ($launcher_time + 60 > time());
    $is_online_web      = $web_time      && ($web_time      + 60 > time());

    if ($is_online_launcher || $is_online_web) {
        $app = $is_online_launcher ? trim((string)($activity['current_app'] ?? '')) : '';
        $presence = ['on' => true, 'text' => $app !== '' ? 'В сети · играет в ' . $app : 'В сети'];
    } elseif ($last_active > 0) {
        $presence = ['on' => false, 'text' => 'Был ' . format_last_seen($last_active)];
    } else {
        $presence = ['on' => false, 'text' => 'Не в сети'];
    }
}

/* ── хаб: стена, вкладки, награды ── */
$fxName   = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'];
$fxAvatar = !empty($user['profile_picture']) ? $user['profile_picture'] : '/swad/static/img/logo.svg';
$fxWall   = FxFeed::wall('user', $uid, 'wall', $fxV);
FxPosts::view(array_column($fxWall['posts'], 'id'));
$fxCtx    = ['viewer' => $fxV, 'show_reason' => false, 'wall' => ['type' => 'user', 'id' => $uid, 'channel' => 'wall']];

$fxTabs = ['wall' => ['chat', 'Стена'], 'games' => ['gamepad', 'Коллекция'], 'friends' => ['users', 'Друзья'], 'reviews' => ['star', 'Отзывы']];
if ($is_owner) $fxTabs['account'] = ['lock', 'Аккаунт'];
$fxTab = (string)($_GET['tab'] ?? '');
if (!isset($fxTabs[$fxTab])) $fxTab = 'wall';
$fxCount = ['friends' => count($friends), 'reviews' => count($reviews)];

$awards = array_map(static fn($a) => [
    'name' => (string)$a['name'], 'description' => (string)($a['description'] ?? ''), 'icon_url' => (string)($a['icon_url'] ?? ''),
], $achievements);

$fxLinks = [];
$fxLinks[] = ['L4T-профиль', '/l4t/' . rawurlencode($user['username']), 'rocket', 'internal'];
if (safe_link($user['website'])) $fxLinks[] = ['Сайт', safe_link($user['website']), 'globe', ''];
if (safe_link($user['vk']))      $fxLinks[] = ['ВКонтакте', safe_link($user['vk']), 'users', ''];
if (!empty($user['telegram_username'])) $fxLinks[] = ['Telegram', 'https://t.me/' . rawurlencode(ltrim((string)$user['telegram_username'], '@')), 'send-tg', ''];

$loc = array_filter([trim((string)$user['city']), trim((string)$user['country'])], fn($x) => $x !== '');

/* кнопка дружбы: текст, действие */
$btn = ['send', 'Добавить в друзья'];
if ($status) {
    if ($status['status'] === 'pending') {
        $btn = ((int)$status['player_id'] === (int)($_SESSION['USERDATA']['id'] ?? 0)) ? ['cancel', 'Отменить заявку'] : ['accept', 'Принять заявку'];
    } elseif ($status['status'] === 'accepted') {
        $btn = ['remove', 'Завершить дружбу'];
    }
}
$isFriend = $btn[0] === 'remove';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <div class="fx fx-hub fx-hub--profile<?= $m ? ' fx-m' : '' ?>" data-user="<?= $uid ?>">

            <!-- ══ БАННЕР ══ -->
            <section class="fx-banner">
                <div class="fx-banner__bg fx-banner__bg--soft" style="--bn:url('<?= $h($fxAvatar) ?>')"></div>
                <?php if ($is_owner): ?>
                    <div class="fx-gear-wrap">
                        <?php if ($m): ?>
                            <a class="fx-gear" href="/m/profile" title="Настройки" aria-label="Настройки"><?= fx_icon('settings', 'ic--sm') ?><span>Настройки</span></a>
                        <?php else: ?>
                            <button type="button" class="fx-gear" data-open-settings aria-haspopup="dialog" aria-controls="userSettings" aria-expanded="false" title="Настройки" aria-label="Настройки">
                            <?= fx_icon('settings', 'ic--sm') ?><span>Настройки</span>
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="fx-banner__in">
                    <div class="fx-banner__id">
                        <div class="fx-icon fx-icon--round" role="img" aria-label="<?= $h($fxName) ?>" style="background-image:url('<?= $h($fxAvatar) ?>')"></div>
                        <div class="fx-banner__t">
                            <h1><?= $h($fxName) ?></h1>
                            <div class="fx-banner__by">
                                <span class="fx-tick" style="text-transform:none;letter-spacing:0">@<?= $h($user['username']) ?></span>
                                <?php if ($presence['text'] !== ''): ?>
                                    <span class="fx-presence<?= $presence['on'] ? ' is-on' : '' ?>"><i></i><?= $h($presence['text']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="fx-chips">
                                <?php if ($loc): ?><span class="fx-chip"><?= $h(implode(', ', $loc)) ?></span><?php endif; ?>
                                <span class="fx-chip"><?= fx_icon('clock', 'ic--sm') ?>на платформе с <?= date('d.m.Y', strtotime($user['added'])) ?></span>
                                <?php if ((int)$user['games_count'] > 0): ?><span class="fx-chip"><?= fx_icon('wrench', 'ic--sm') ?>разработчик</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="fx-banner__act">
                            <?php if ($is_owner): ?>
                                <?php /* окно «Изменить профиль» — swad/static/elements/profile_edit.php; href — на случай, если скрипт не успел */ ?>
                                <a href="#edit-profile" class="fx-btn" role="button" data-open-profile-edit aria-haspopup="dialog" aria-controls="profileEdit"><?= fx_icon('edit', 'ic--sm') ?> Изменить профиль</a>
                            <?php elseif (!empty($_SESSION['USERDATA']['id'])): ?>
                                <button type="button" class="fx-btn fx-friend" id="friendActionBtn" data-user="<?= $uid ?>" data-action="<?= $btn[0] ?>">
                                    <span id="friendActionBtnText"><?= $h($btn[1]) ?></span>
                                </button>
                                <?php /* Написать можно только другу: /chat/?to=<id> создаёт или открывает личный диалог.
                                         Кнопка скрывается и появляется вместе со статусом дружбы (см. swad/js/fx-player.js). */ ?>
                                <a id="friendMsgBtn" class="fx-btn fx-btn--ghost fx-btn--icon<?= $isFriend ? '' : ' is-hidden' ?>" href="<?= $m ? '/m/chat?to=' : '/chat/?to=' ?><?= $uid ?>" title="Написать сообщение" aria-label="Написать сообщение">
                                    <?= fx_icon('chat', 'ic--sm') ?><span>Написать</span>
                                </a>
                            <?php else: ?>
                                <a class="fx-btn" href="<?= $h($loginUrl) ?>"><?= fx_icon('plus', 'ic--sm') ?> Добавить в друзья</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="fx-banner__side fx-banner__side--studio">
                        <?= FxRenderHub::awardsStack($awards, 5) ?>
                    </div>
                </div>
            </section>

            <div class="fx-cols">

                <!-- ══ МАЛЕНЬКИЙ БЛОК (на телефоне — сверху, горизонтальная прокрутка) ══ -->
                <aside class="fx-side">

                    <?php if ($incomingRequests): ?>
                        <section class="fx-card fx-card--alert" id="friendRequests">
                            <div class="fx-card__h"><?= fx_icon('users', 'ic--sm') ?> Заявки в друзья <span class="fx-tab__n"><?= count($incomingRequests) ?></span></div>
                            <div class="fx-people">
                                <?php foreach ($incomingRequests as $req): $rn = trim($req['first_name'] . ' ' . $req['last_name']) ?: $req['username']; ?>
                                    <div class="fx-person fx-person--req">
                                        <?= FxRender::avatar(['type' => 'user', 'name' => $rn, 'img' => $req['profile_picture'], 'url' => Fx::urlUser($req['username'])], 34) ?>
                                        <span class="fx-person__b">
                                            <a class="fx-person__n" href="<?= $h(Fx::urlUser($req['username'])) ?>"><?= $h($rn) ?></a>
                                            <small>@<?= $h($req['username']) ?></small>
                                        </span>
                                        <button type="button" class="fx-follow" data-friend-accept data-user="<?= (int)$req['user_id'] ?>">Принять</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="fx-card">
                        <div class="fx-card__h"><?= fx_icon('chart', 'ic--sm') ?> Игрок в цифрах</div>
                        <div class="fx-pair">
                            <div>
                                <div class="fx-big"><b><?= Fx::n($stats['library_count']) ?></b></div>
                                <div class="fx-cap"><?= Fx::plural((int)$stats['library_count'], 'игра', 'игры', 'игр') ?></div>
                            </div>
                            <div>
                                <div class="fx-big"><b><?= Fx::n($stats['friends_count']) ?></b></div>
                                <div class="fx-cap"><?= Fx::plural((int)$stats['friends_count'], 'друг', 'друга', 'друзей') ?></div>
                            </div>
                        </div>
                        <div class="fx-kv" style="margin-top:10px"><span>Отзывов</span><b><?= Fx::n($stats['reviews_count']) ?></b></div>
                        <div class="fx-kv"><span>Достижений</span><b><?= Fx::n(count($achievements)) ?></b></div>
                        <?php if ((int)$user['games_count'] > 0): ?><div class="fx-kv"><span>Своих проектов</span><b><?= Fx::n($user['games_count']) ?></b></div><?php endif; ?>
                    </section>

                    <?php if ($games): ?>
                        <section class="fx-card">
                            <div class="fx-card__h"><?= fx_icon('gamepad', 'ic--sm') ?> Последние игры</div>
                            <div class="fx-mini">
                                <?php foreach (array_slice($games, 0, 6) as $g): ?>
                                    <a href="<?= $h(Fx::urlGame((int)$g['id'])) ?>" title="<?= $h($g['name']) ?>" class="fx-mini__i" <?= !empty($g['path_to_cover']) ? 'style="background-image:url(\'' . $h($g['path_to_cover']) . '\')"' : '' ?>>
                                        <?= empty($g['path_to_cover']) ? $h(mb_strtoupper(mb_substr($g['name'], 0, 1))) : '' ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($games) > 6): ?><button type="button" class="fx-more" data-fx="tab" data-pane="games">Вся коллекция (<?= count($games) ?>)</button><?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <?php if ($friends): ?>
                        <section class="fx-card">
                            <div class="fx-card__h"><?= fx_icon('users', 'ic--sm') ?> Друзья · <?= count($friends) ?></div>
                            <div class="fx-faces">
                                <?php foreach (array_slice($friends, 0, 10) as $f): $fn = trim($f['first_name'] . ' ' . $f['last_name']) ?: $f['username']; ?>
                                    <span title="<?= $h($fn) ?>"><?= FxRender::avatar(['type' => 'user', 'name' => $fn, 'img' => $f['profile_picture'], 'url' => Fx::urlUser($f['username'])], 38) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($friends) > 10): ?><button type="button" class="fx-more" data-fx="tab" data-pane="friends">Все друзья</button><?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <section class="fx-card">
                        <div class="fx-card__h"><?= fx_icon('link', 'ic--sm') ?> Ссылки</div>
                        <div class="fx-links">
                            <?php foreach ($fxLinks as [$lab, $href, $ic, $tag]): $ext = str_starts_with($href, 'http'); ?>
                                <a class="fx-link" href="<?= $h($href) ?>"<?= $ext ? ' target="_blank" rel="noopener nofollow"' : '' ?>>
                                    <?= fx_icon($ic, 'ic--sm') ?><span><?= $h($lab) ?></span><?= $tag !== '' ? '<em class="fx-tagx">' . $h($tag) . '</em>' : fx_icon('external', 'ic--sm') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </aside>

                <!-- ══ ОСНОВНОЙ БЛОК ══ -->
                <div class="fx-main">
                    <nav class="fx-tabs" role="tablist">
                        <?php foreach ($fxTabs as $k => [$ic_, $label_]): ?>
                            <button type="button" class="fx-tab<?= $fxTab === $k ? ' on' : '' ?>" role="tab" data-fx="tab" data-pane="<?= $k ?>">
                                <?= fx_icon($ic_, 'ic--sm') ?><?= $h($label_) ?>
                                <?php if (!empty($fxCount[$k])): ?><span class="fx-tab__n"><?= (int)$fxCount[$k] ?></span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>

                    <!-- Стена: пишет любой игрок -->
                    <div class="fx-pane" data-pane="wall"<?= $fxTab === 'wall' ? '' : ' hidden' ?>>
                        <?= FxRenderHub::composer([
                            'wall_type' => 'user', 'wall_id' => $uid, 'channel' => 'wall', 'viewer' => FxPage::viewer(),
                            'poll' => true, 'photo' => true, 'article' => false,
                            'placeholder' => $is_owner ? 'Что у вас нового?' : 'Напишите на стене ' . $fxName . '…',
                            'hint' => $is_owner ? 'Ваша стена: запись увидят те, кто зайдёт в профиль' : 'Стена игрока — писать может любой',
                        ]) ?>
                        <div class="fx-feed" data-ssr="1" data-feed='<?= Fx::e(json_encode(['scope' => 'wall', 'wall_type' => 'user', 'wall_id' => $uid, 'channel' => 'wall'])) ?>'>
                            <?= $fxWall['posts']
                                ? FxRender::posts($fxWall['posts'], $fxCtx) . FxRender::more($fxWall['next'])
                                : FxRender::empty('chat', 'На стене пока пусто', $is_owner ? 'Напишите первую запись — её увидят друзья и гости профиля.' : 'Напишите первым: приветствие, вопрос или совет по играм.') ?>
                        </div>
                    </div>

                    <!-- Коллекция: игры и достижения -->
                    <div class="fx-pane" data-pane="games"<?= $fxTab === 'games' ? '' : ' hidden' ?>>
                        <?php if ($games): ?>
                            <div class="fx-gamegrid">
                                <?php foreach ($games as $g): ?>
                                    <a class="fx-gcard" href="<?= $h(Fx::urlGame((int)$g['id'])) ?>">
                                        <span class="fx-gcard__cover" <?= !empty($g['path_to_cover']) ? 'style="background-image:url(\'' . $h($g['path_to_cover']) . '\')"' : '' ?>><?= empty($g['path_to_cover']) ? $h(mb_strtoupper(mb_substr($g['name'], 0, 1))) : '' ?></span>
                                        <b><?= $h($g['name']) ?></b>
                                        <small><?= fx_icon('star', 'ic--sm') ?> <?= number_format((float)$g['rating'], 1) ?> · <?= $g['price'] > 0 ? (int)$g['price'] . ' ₽' : 'бесплатно' ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?= FxRender::empty('gamepad', 'В библиотеке пока нет игр', $is_owner ? 'Найдите что-нибудь в каталоге — игра появится здесь.' : '') ?>
                        <?php endif; ?>

                        <h2 class="fx-h2">Достижения<?= $achievements ? ' · ' . count($achievements) : '' ?></h2>
                        <?php if ($achievements): ?>
                            <div class="fx-achgrid">
                                <?php foreach ($achievements as $a): ?>
                                    <div class="fx-ach" title="<?= $h($a['description'] ?? '') ?>">
                                        <span class="fx-award"><span <?= !empty($a['icon_url']) ? 'style="background-image:url(\'' . $h($a['icon_url']) . '\')"' : '' ?>><?= empty($a['icon_url']) ? fx_icon('award') : '' ?></span></span>
                                        <b><?= $h($a['name']) ?></b>
                                        <?php if (!empty($a['awarded_at'])): ?><small><?= date('d.m.Y', strtotime($a['awarded_at'])) ?></small><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="fx-note">Достижений пока нет — играйте, исследуйте, зарабатывайте награды.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Друзья -->
                    <div class="fx-pane" data-pane="friends"<?= $fxTab === 'friends' ? '' : ' hidden' ?>>
                        <?php if ($friends): ?>
                            <div class="fx-search" id="friendsSearchWrap">
                                <?= fx_icon('search', 'ic--sm') ?>
                                <input type="text" id="friendsSearch" placeholder="Поиск среди друзей…" autocomplete="off">
                                <button type="button" id="friendsSearchClear" aria-label="Очистить">&times;</button>
                            </div>
                            <div class="fx-friendlist" id="friendsList">
                                <?php foreach ($friends as $f): $fn = trim($f['first_name'] . ' ' . $f['last_name']) ?: $f['username']; ?>
                                    <a href="<?= $h(Fx::urlUser($f['username'])) ?>" class="fx-frow"
                                       data-search="<?= $h(mb_strtolower(trim($f['first_name'] . ' ' . $f['last_name'] . ' ' . $f['username']))) ?>">
                                        <?= FxRender::avatar(['type' => 'user', 'name' => $fn, 'img' => $f['profile_picture']], 44, false) ?>
                                        <span class="fx-frow__t"><b><?= $h($fn) ?></b><small>@<?= $h($f['username']) ?></small></span>
                                        <?= fx_icon('arrow-rt', 'ic--sm') ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="fx-note" id="friendsEmpty" hidden>Никого не нашлось</div>
                            <a class="fx-more" href="<?= $m ? '/m/search' : '/users.php' ?>" style="margin-top:10px">Найти игроков</a>
                        <?php else: ?>
                            <?= FxRender::empty('users', $is_owner ? 'У вас пока нет друзей' : 'У игрока пока нет друзей', '', '<a class="fx-btn" href="' . ($m ? '/m/search' : '/users.php') . '">Найти игроков</a>') ?>
                        <?php endif; ?>
                    </div>

                    <!-- Отзывы -->
                    <div class="fx-pane" data-pane="reviews"<?= $fxTab === 'reviews' ? '' : ' hidden' ?>>
                        <?php if ($reviews): ?>
                            <div class="fx-reviews">
                                <?php foreach ($reviews as $r): ?>
                                    <a href="<?= $h(Fx::urlGame((int)$r['game_id'])) ?>" class="fx-rev">
                                        <span class="fx-rev__cover" <?= !empty($r['game_cover']) ? 'style="background-image:url(\'' . $h($r['game_cover']) . '\')"' : '' ?>></span>
                                        <span class="fx-rev__b">
                                            <span class="fx-rev__top"><b><?= $h($r['game_title']) ?></b><span class="fx-rev__score"><?= fx_icon('star', 'ic--sm') ?> <?= $h($r['rating']) ?>/10</span></span>
                                            <span class="fx-rev__t"><?= nl2br($h($r['text'])) ?></span>
                                            <small><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></small>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?= FxRender::empty('star', 'Отзывов пока нет', $is_owner ? 'Оставьте отзыв на странице игры из вашей библиотеки.' : 'Игрок пока не оставил ни одного отзыва.') ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_owner): ?>
                        <!-- Аккаунт: безопасность (только владелец) -->
                        <div class="fx-pane" data-pane="account"<?= $fxTab === 'account' ? '' : ' hidden' ?>>
                            <?php $owner_data = $_SESSION['USERDATA']; ?>
                            <?php /* Формы уходят в swad/controllers/account_security.php через fetch (swad/js/fx-player.js)
                                     и показывают ответ прямо в форме. */ ?>
                            <div class="fx-acc">
                                <section class="fx-card">
                                    <?php if (empty($owner_data['email'])): ?>
                                        <div class="fx-card__h"><?= fx_icon('mail', 'ic--sm') ?> Привязка почты</div>
                                        <p class="fx-note">Для тех, кто скучает по 2007</p>
                                        <form method="POST" action="/swad/controllers/account_security.php" data-account-form class="fx-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="bind_email">
                                            <p class="fx-msg" role="alert"></p>
                                            <input class="fx-input" type="email" name="email" required placeholder="Email" autocomplete="email">
                                            <input class="fx-input" type="password" name="password" required minlength="8" placeholder="Пароль" autocomplete="new-password">
                                            <input class="fx-input" type="password" name="confirm_password" required minlength="8" placeholder="Повторите пароль" autocomplete="new-password">
                                            <button class="fx-btn">Привязать почту</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="fx-card__h"><?= fx_icon('mail', 'ic--sm') ?> Почта и пароль</div>
                                        <p class="fx-note" style="padding:0 0 8px">Email: <b style="color:#fff"><?= $h($owner_data['email']) ?></b></p>
                                        <?php if (empty($owner_data['email_verified'])): ?>
                                            <div class="fx-warn">Почта не подтверждена</div>
                                        <?php endif; ?>
                                        <form method="POST" action="/swad/controllers/account_security.php" data-account-form class="fx-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="change_password">
                                            <p class="fx-msg" role="alert"></p>
                                            <input class="fx-input" type="password" name="current_password" required placeholder="Текущий пароль" autocomplete="current-password">
                                            <input class="fx-input" type="password" name="new_password" required minlength="8" placeholder="Новый пароль" autocomplete="new-password">
                                            <input class="fx-input" type="password" name="confirm_password" required minlength="8" placeholder="Повторите пароль" autocomplete="new-password">
                                            <button class="fx-btn">Обновить пароль</button>
                                        </form>
                                    <?php endif; ?>
                                </section>

                                <?php /* Перенесено со старой страницы /me — больше этих данных нигде не было */ ?>
                                <section class="fx-card">
                                    <div class="fx-card__h"><?= fx_icon('user', 'ic--sm') ?> Об аккаунте</div>
                                    <?php if (!empty($owner_data['telegram_id']) && (int)$owner_data['telegram_id'] > 0): ?>
                                        <?php /* Отрицательный telegram_id — старый temp_id анонима, показывать его незачем */ ?>
                                        <div class="fx-kv"><span>Telegram ID</span><b><?= $h($owner_data['telegram_id']) ?></b></div>
                                    <?php endif; ?>
                                    <?php if (!empty($owner_data['telegram_username'])): ?>
                                        <div class="fx-kv"><span>Telegram</span><b><a href="https://t.me/<?= rawurlencode((string)$owner_data['telegram_username']) ?>" target="_blank" rel="noopener" style="color:var(--accent)">@<?= $h($owner_data['telegram_username']) ?></a></b></div>
                                    <?php endif; ?>
                                    <div class="fx-kv"><span>Тип учётной записи</span><b>
                                        <?php
                                        /* printUserPrivileges() на неизвестной роли печатает «Неверный идентификатор» —
                                           для обычного пользователя это выглядит как ошибка в аккаунте */
                                        $roleName = $curr_user->getRoleName($curr_user->getUserRole($userID, "global"));
                                        if (in_array($roleName, ['creator', 'user', 'employee', 'owner', 'moder', 'admin'], true)) {
                                            $curr_user->printUserPrivileges($roleName);
                                        } else {
                                            echo 'Обычный пользователь';
                                        }
                                        ?>
                                    </b></div>
                                </section>

                                <section class="fx-card">
                                    <div class="fx-card__h"><?= fx_icon('wrench', 'ic--sm') ?> Разработчик</div>
                                    <?php if ($curr_user->getUO($userID)): ?>
                                        <p class="fx-note" style="padding:0 0 8px">Студия <b style="color:#fff"><?= $h($curr_user->getUO($userID)[0]['name']) ?></b></p>
                                        <a class="fx-btn fx-btn--ghost" href="/devs/select">Вход в консоль для разработчиков</a>
                                    <?php else: ?>
                                        <p class="fx-note" style="padding:0 0 8px">У вас ещё нет аккаунта разработчика</p>
                                        <a class="fx-btn" href="/devs/regorg">Зарегистрировать бесплатно</a>
                                    <?php endif; ?>
                                </section>

                                <section class="fx-card">
                                    <div class="fx-card__h"><?= fx_icon('lock', 'ic--sm') ?> Завершение сеанса</div>
                                    <p class="fx-note" style="padding:0 0 10px">Выход прекратит доступ на этом устройстве. Для повторного входа потребуется авторизация через Telegram или passphrase.</p>
                                    <form action="/swad/controllers/logout.php" method="POST" data-confirm="Вы уверены?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="fx-btn fx-btn--danger" style="width:100%">Выйти из аккаунта</button>
                                    </form>
                                </section>
                            </div>
                        </div>
                    <?php endif; ?>
                </div><!-- /fx-main -->
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        /* Окна владельца лежат вне .fx — у них свои стили (user-dialog.css). В PWA «Настройки» — страница /m/profile.
           Строим лениво (вызов $page['modals']()): им нужен asset_url() из шапки сайта, а шапка подключается позже данных. */
        $modals = static function () use ($is_owner, $m, $user): string {
            if (!$is_owner) return '';
            $dir = dirname(__DIR__, 2) . '/swad/static/elements/';
            ob_start();
            if (!$m) require_once $dir . 'user_settings.php';
            $pe_user = $user;
            require_once $dir . 'profile_edit.php';
            return (string)ob_get_clean();
        };
        return [
            'id' => $uid, 'username' => (string)$user['username'], 'name' => $fxName, 'image' => $fxAvatar,
            'owner' => $is_owner, 'html' => $html, 'modals' => $modals,
        ];
    }
    private static function empty(string $why, bool $m): string
    {
        switch ($why) {
            case 'guest':
                return FxRender::empty('users', 'Здесь будут записи друзей', 'Войдите, чтобы видеть записи друзей и студий, на которые вы подписаны.',
                    '<a class="fx-btn" href="' . Fx::urlLogin(Fx::urlMedia()) . '">Войти</a>');
            case 'nobody':
                return FxRender::empty('users', 'Пока не на кого смотреть', 'Добавьте друзей или подпишитесь на студии и игры — их записи появятся здесь. А пока загляните в общую ленту.',
                    '<button type="button" class="fx-btn fx-btn--ghost" data-fx="tab" data-pane="general">Открыть ленту</button>');
            default:
                return FxRender::empty('home', 'Тут пока тихо', 'Записи появятся здесь, как только кто-нибудь что-то опубликует.');
        }
    }

    private static function pane(string $name, string $tab, ?array $ssr, array $ctx, bool $m): void
    {
        $cfg = ['scope' => $name];
        $on  = $tab === $name;
        echo '<div class="fx-pane" data-pane="' . $name . '"' . ($on ? '' : ' hidden') . '>';
        echo "<div class=\"fx-feed\" data-feed='" . Fx::e(json_encode($cfg)) . "'" . ($on && $ssr !== null ? ' data-ssr="1"' : '') . '>';
        if ($on && $ssr !== null) {
            if ($ssr['posts']) {
                FxPosts::view(array_column($ssr['posts'], 'id'));
                echo FxRender::posts($ssr['posts'], $ctx) . FxRender::more($ssr['next']);
            } else {
                echo self::empty((string)($ssr['empty'] ?? ''), $m);
            }
        }
        foreach (['guest', 'nobody', ''] as $why) {
            echo '<template data-empty="' . $why . '">' . self::empty($why, $m) . '</template>';
        }
        echo '</div></div>';
    }

    /** Медиа-лента /fid и /m/media: вкладки «Друзья» / «Лента» / DustHunt. */
    public static function fid(int $viewer, array $o = []): array
    {
        $m = !empty($o['m']);
        if ($m) Fx::mobile(true);
        $openId = (int)($o['open'] ?? 0);
$TABS = ['friends' => ['users', 'Друзья'], 'general' => ['home', 'Лента'], 'hunt' => ['target', 'DustHunt']];
$want = (string)($o['tab'] ?? '');
if (!isset($TABS[$want])) $want = '';

/* Первую страницу активной вкладки отдаём сразу в HTML (без «мигания»), остальные подгружает fx.js. */
$ssr = null;
if ($want === '' || $want === 'friends') {
    $ssr = FxFeed::friends($viewer);
    // без явного выбора: у новичка записей друзей нет — открываем общую ленту
    $tab = $want !== '' ? $want : ($ssr['posts'] ? 'friends' : 'general');
} else {
    $tab = $want;
}
if ($tab === 'general') $ssr = FxFeed::general($viewer, 0);
elseif ($tab === 'hunt') $ssr = null;

$hunts = FxHunt::live($viewer, 30);
$ctx   = ['viewer' => $viewer, 'show_reason' => true];
        $studios = FxAuth::studiosOf($viewer);

        ob_start();
        ?>
    <div class="fx fx-fid<?= $m ? ' fx-m' : '' ?>" data-open="<?= $openId ?>">
        <div class="fx-fid__main">
            <?= FxRenderHub::composer([
                'wall_type' => 'media', 'wall_id' => 0, 'channel' => 'wall',
                'viewer' => FxPage::viewer(), 'as' => $studios,
                'poll' => true, 'photo' => true, 'article' => true,
                'hint' => 'Запись увидят в общей ленте',
            ]) ?>

            <nav class="fx-tabs" role="tablist">
                <?php foreach ($TABS as $key => [$ic, $label]): ?>
                    <button type="button" class="fx-tab<?= $tab === $key ? ' on' : '' ?>" role="tab" data-fx="tab" data-pane="<?= $key ?>">
                        <?= fx_icon($ic, 'ic--sm') ?><?= Fx::e($label) ?>
                        <?php if ($key === 'hunt' && $hunts): ?><span class="fx-tab__n"><?= count($hunts) ?></span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </nav>

            <div class="fx-panes">
                <?php self::pane('friends', $tab, $ssr, $ctx, $m); ?>
                <?php self::pane('general', $tab, $ssr, $ctx, $m); ?>

                <div class="fx-pane" data-pane="hunt" <?= $tab === 'hunt' ? '' : 'hidden' ?>>
                    <?php if ($hunts): ?>
                        <div class="fx-hunts">
                            <?php foreach ($hunts as $h): $g = $h['game']; ?>
                                <div class="fx-hcard">
                                    <div class="fx-hcard__cover" <?= $g && $g['icon'] ? 'style="background-image:url(\'' . Fx::e($g['icon']) . '\')"' : '' ?>></div>
                                    <div class="fx-hcard__b">
                                        <div class="fx-hcard__game"><?= $g ? Fx::e($g['name']) : '' ?></div>
                                        <div class="fx-hcard__t"><?= Fx::e($h['title']) ?></div>
                                        <div class="fx-hcard__m">
                                            <span><?= fx_icon('users', 'ic--sm') ?> <?= Fx::n($h['players']) ?></span>
                                            <span><?= fx_icon('clock', 'ic--sm') ?> до <?= Fx::e(Fx::day($h['ends_at'])) ?></span>
                                            <?php if ($h['prize'] !== ''): ?><span class="fx-hcard__prize"><?= fx_icon('star', 'ic--sm') ?> <?= Fx::e($h['prize']) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                    <button type="button" class="fx-btn<?= $h['joined'] ? ' fx-btn--ghost' : '' ?>" data-fx="hunt-open" data-id="<?= $h['id'] ?>">
                                        <?= $h['joined'] ? 'Вы участвуете' : 'Подробнее' ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?= FxRender::empty('target', 'Сейчас нет активных DustHunt', 'Студии запускают охоты из консоли разработчика — раздел «Соучастники проектов». Как только одна начнётся, она появится здесь.') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <aside class="fx-fid__side">
            <?php if ($hunts): ?>
                <section class="fx-card">
                    <div class="fx-card__h"><?= fx_icon('target', 'ic--sm') ?> Идёт DustHunt</div>
                    <?php foreach (array_slice($hunts, 0, 3) as $h): ?>
                        <button type="button" class="fx-sidehunt" data-fx="hunt-open" data-id="<?= $h['id'] ?>">
                            <b><?= Fx::e($h['title']) ?></b>
                            <small><?= $h['game'] ? Fx::e($h['game']['name']) . ' · ' : '' ?><?= Fx::n($h['players']) ?> <?= Fx::plural($h['players'], 'участник', 'участника', 'участников') ?></small>
                        </button>
                    <?php endforeach; ?>
                    <?php if (count($hunts) > 3): ?>
                        <button type="button" class="fx-more" data-fx="tab" data-pane="hunt">Все охоты (<?= count($hunts) ?>)</button>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="fx-card">
                <div class="fx-card__h"><?= fx_icon('home', 'ic--sm') ?> Как устроена лента</div>
                <div class="fx-howto">
                    <p><b>Друзья</b> — записи ваших друзей, их сообщения на страницах игр и студий, а также официальные посты тех, на кого вы подписаны.</p>
                    <p><b>Лента</b> — всё, что опубликовано прямо в медиа: посты, статьи и новости Dustore, плюс подборка того, что может вам понравиться.</p>
                    <p class="fx-note">Сообщения на личных стенах и в обсуждениях игр в общую ленту не попадают.</p>
                </div>
            </section>
        </aside>

        <?php if ($viewer > 0): ?>
            <button type="button" class="fx-fab" data-fx="new-post" aria-label="Новый пост"><?= fx_icon('plus') ?></button>
        <?php endif; ?>
    </div>
        <?php
        return ['html' => ob_get_clean(), 'tab' => $tab];
    }
}
