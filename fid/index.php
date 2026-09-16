<?php
/* ======================================================================
   Dustore.Fid — лента
   Разметка поста генерируется из массива, а не копипастится.
   Заменить $posts на выборку из БД — и страница готова к бою.
   ====================================================================== */
require_once __DIR__ . '/icons.php';

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 12400 → «12,4 тыс.» */
function nfmt($n)
{
    if ($n < 1000) return (string)$n;
    if ($n < 1000000) return rtrim(rtrim(number_format($n / 1000, 1, ',', ' '), '0'), ',') . ' тыс.';
    return rtrim(rtrim(number_format($n / 1000000, 1, ',', ' '), '0'), ',') . ' млн';
}

/* ======================================================================
   СПРАВОЧНИК СУЩНОСТЕЙ — на него ссылаются @упоминания
   ====================================================================== */
$ENTITIES = [
    'game' => [
        ['id' => 'kontur', 'name' => 'К.О.Н.Т.У.Р.', 'rating' => 4.7, 'note' => 'ранний доступ'],
        ['id' => 'iron',   'name' => 'Iron Orchard', 'rating' => 4.3, 'note' => '2 491 в сети'],
        ['id' => 'neon',   'name' => 'Neon Harbour', 'rating' => 4.1, 'note' => 'скоро'],
    ],
    'user' => [
        ['id' => 'kettlehead', 'name' => 'Kettlehead'],
        ['id' => 'anton',      'name' => 'Антон К.'],
        ['id' => 'grumpy',     'name' => 'grumpy_dev'],
    ],
    'studio' => [
        ['id' => 'nuclear-muffin', 'name' => 'Nuclear Muffin'],
        ['id' => 'frog-factory',   'name' => 'Frog Factory'],
        ['id' => 'soda',           'name' => 'Soda Games'],
    ],
    'space' => [
        ['id' => 'jam',     'name' => 'Dustore Jam'],
        ['id' => 'konturs', 'name' => 'Космос К.О.Н.Т.У.Р.'],
    ],
];

const MENTION_ICON = ['user' => 'user', 'game' => 'gamepad', 'studio' => 'studio', 'space' => 'grid'];

/**
 * Канонический вид упоминания в тексте: @[тип:id|Отображаемое имя].
 * Хранится именно так — переименование сущности не ломает ссылку,
 * а отображаемое имя можно пересобрать при миграции.
 */
function render_text($text)
{
    return preg_replace_callback('/@\[(\w+):([\w\-]+)\|([^\]]+)\]/u', function ($m) {
        $ic = MENTION_ICON[$m[1]] ?? 'user';
        return '<a class="mn mn--' . e($m[1]) . '" data-m="' . e($m[1] . ':' . $m[2]) . '">'
            . icon($ic) . '<span>' . e($m[3]) . '</span></a>';
    }, e($text));
}

/** Первое упоминание игры в заголовке или тексте → баннер игры. */
function detect_game($p, $ENTITIES)
{
    $src = ($p['title'] ?? '') . ' ' . ($p['text'] ?? '');
    if (!preg_match('/@\[game:([\w\-]+)\|/u', $src, $m)) return null;
    foreach ($ENTITIES['game'] as $g) if ($g['id'] === $m[1]) return $g;
    return null;
}

function verified_badge($v)
{
    $map = [
        'official' => ['check',  'Официальный аккаунт'],
        'dev'      => ['wrench', 'Подтверждённый разработчик'],
        'partner'  => ['star',   'Партнёр Dustore'],
    ];
    if (!$v || !isset($map[$v])) return '';
    [$ic, $title] = $map[$v];
    return '<span class="vf vf--' . e($v) . '" title="' . e($title) . '">' . icon($ic) . '</span>';
}

/**
 * Аватар. Форма = тип автора, рамка = украшение, стикер = мелкий декор.
 * Это три независимых оси — не сливать в одно поле.
 */
function avatar($a, $size = 44)
{
    $type  = $a['type'] ?? 'user';
    $frame = !empty($a['frame']) ? ' fr-' . e($a['frame']) : '';
    $style = $size !== 44 ? ' style="width:' . (int)$size . 'px;height:' . (int)$size . 'px"' : '';
    $img   = !empty($a['img']) ? ' style="background-image:url(' . e($a['img']) . ')"' : '';

    $h  = '<div class="pav pav--' . e($type) . $frame . '"' . $style . '>';
    $h .= '<span class="pav__frame"></span>';
    $h .= '<span class="pav__img"' . $img . '>' . (!empty($a['img']) ? '' : e($a['initial'] ?? mb_substr($a['name'], 0, 1))) . '</span>';
    if (!empty($a['deco'])) {
        $pos = $a['deco'][1] ?? 'corner';
        $h .= '<span class="pav__deco pav__deco--' . e($pos) . '">' . $a['deco'][0] . '</span>';
    }
    return $h . '</div>';
}

function gamebar($g)
{
    if (!$g) return '';
    $h  = '<a class="gamebar" data-play="' . e($g['name']) . '">';
    $h .= '<span class="gamebar__cover"></span>';
    $h .= '<span class="gamebar__id"><span class="gamebar__name">' . e($g['name']) . '</span>';
    $h .= '<span class="gamebar__sub"><span class="rate">' . icon('star') . number_format($g['rating'], 1, ',', '') . '</span>';
    if (!empty($g['note'])) $h .= '<span>' . e($g['note']) . '</span>';
    $h .= '</span></span>';
    $h .= '<span class="gamebar__play">Играть ' . icon('arrow-rt') . '</span>';
    return $h . '</a>';
}

/* Наборы реакций: [иконка, подпись]. Первая — то, что ставится обычным тапом. */
const RX_UP   = [['heart', 'Нравится'], ['fire', 'Огонь'], ['clap', 'Респект'], ['laugh', 'Смешно'], ['mind', 'Вынос мозга']];
const RX_DOWN = [['thumbdown', 'Не нравится'], ['zzz', 'Скучно'], ['mask', 'Кринж'], ['meh', 'Спорно']];

function reactions($s)
{
    $mine = $s['reaction'] ?? null;
    $group = function ($side, $set, $count) use ($mine) {
        $names = array_column($set, 0);
        $on    = ($mine && in_array($mine, $names, true)) ? ' on' : '';
        $ic    = $on ? $mine : $set[0][0];
        $h  = '<div class="rxg rxg--' . $side . '" data-side="' . $side . '" data-def="' . $set[0][0] . '">';
        $h .= '<button class="act rx__main' . $on . '" data-count="' . (int)$count . '" aria-label="Реакция">'
            . icon($ic) . '<span class="rx__n">' . nfmt($count) . '</span></button>';
        $h .= '<div class="rxpop" role="menu">';
        foreach ($set as [$n, $title]) {
            $h .= '<button class="rxo" role="menuitem" data-e="' . $n . '" title="' . e($title) . '">' . icon($n) . '</button>';
        }
        return $h . '</div></div>';
    };
    return $group('up', RX_UP, $s['up'] ?? 0) . $group('down', RX_DOWN, $s['down'] ?? 0);
}

function post_media($p)
{
    $k = $p['kind'] ?? 'text';
    $m = $p['media'] ?? [];

    if ($k === 'photo') {
        return '<div class="media" data-dbl><div class="ph"></div><div class="dbl">' . icon('heart', 'ic--xl') . '</div></div>';
    }
    if ($k === 'collage') {
        $n = count($m);
        $shown = min($n, 4);
        $h = '<div class="media" data-dbl><div class="collage collage--' . $shown . '">';
        for ($i = 0; $i < $shown; $i++) $h .= '<div class="ph"></div>';
        $h .= '</div>';
        if ($n > $shown) $h .= '<div class="collage__more">+' . ($n - $shown) . '</div>';
        return $h . '<div class="dbl">' . icon('heart', 'ic--xl') . '</div></div>';
    }
    if ($k === 'gallery') {
        $h = '<div class="media" data-dbl><div class="carousel">';
        foreach ($m as $_) $h .= '<div class="ph"></div>';
        $h .= '</div><div class="dotsnav">';
        foreach ($m as $i => $_) $h .= '<i' . ($i === 0 ? ' class="on"' : '') . '></i>';
        return $h . '</div><div class="dbl">' . icon('heart', 'ic--xl') . '</div></div>';
    }
    if ($k === 'clip') {
        return '<div class="media" data-dbl><div class="clip">'
            . '<button class="play" aria-label="Воспроизвести">' . icon('play', 'ic--lg') . '</button>'
            . '<span class="clip__dur">' . e($p['duration'] ?? '0:42') . '</span>'
            . '<div class="clip__bar"><i></i></div></div><div class="dbl">' . icon('heart', 'ic--xl') . '</div></div>';
    }
    if ($k === 'files') {
        $h = '<div class="files">';
        foreach ($m as $f) {
            $h .= '<button class="file" data-file="' . e($f['name']) . '"><span class="file__ic">' . icon($f['icon'] ?? 'folder') . '</span>'
                . '<span><span class="file__n">' . e($f['name']) . '</span><span class="file__s">' . e($f['size']) . '</span></span>'
                . '<span class="file__dl">' . icon('download') . '</span></button>';
        }
        return $h . '</div>';
    }
    return '';
}

function post_poll($p)
{
    if (($p['kind'] ?? '') !== 'poll') return '';
    $h = '<div class="poll" data-poll>';
    foreach ($p['poll'] as $o) {
        $h .= '<button class="option" data-pct="' . (int)$o['pct'] . '"><i></i><span>' . e($o['label'])
            . '<b>' . (int)$o['pct'] . '%</b></span></button>';
    }
    return $h . '<div class="poll__total">' . nfmt($p['stats']['votes'] ?? 0) . ' голосов · до конца 2 дня</div></div>';
}

function render_post($p, $ENTITIES)
{
    $a = $p['author'];
    $isArticle = ($p['kind'] ?? '') === 'article';
    $g = detect_game($p, $ENTITIES);
?>
    <article class="post" data-id="<?= (int)$p['id'] ?>" data-kind="<?= e($p['kind'] ?? 'text') ?>" data-source="<?= e($p['source']) ?>">
        <div class="posthead">
            <?= avatar($a) ?>
            <div class="posthead__id">
                <div class="author">
                    <span class="author__name"><?= e($a['name']) ?></span>
                    <?= verified_badge($a['verified'] ?? null) ?>
                </div>
                <div class="meta">
                    <span><?= e($a['role'] ?? '') ?></span><i>·</i>
                    <span><?= e($p['time']) ?></span>
                    <?php if (!empty($p['space'])): ?><i>·</i><span><?= e($p['space']) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="headbtns">
                <button class="follow<?= !empty($p['following']) ? ' on' : '' ?>" data-follow>
                    <?= !empty($p['following']) ? icon('check') . ' Вы подписаны' : 'Подписаться' ?>
                </button>
                <button class="dots" data-menu aria-label="Ещё"><?= icon('dots') ?></button>
            </div>
        </div>

        <div class="body">
            <?php if (!empty($p['title'])): ?><h2><?= render_text($p['title']) ?></h2><?php endif; ?>
            <p class="<?= $isArticle ? 'excerpt' : '' ?>"><?= render_text($p['text']) ?></p>
            <?php if ($isArticle): ?>
                <button class="readmore" data-read><?= icon('article') ?> Читать статью · <?= e($p['readtime'] ?? '4 мин') ?></button>
            <?php endif; ?>
            <?= post_poll($p) ?>
            <?php if (!empty($p['tags'])): ?>
                <div class="tags"><?php foreach ($p['tags'] as $t): ?><span class="tag">#<?= e($t) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
        </div>

        <?= post_media($p) ?>
        <?= gamebar($g) ?>

        <div class="actions">
            <?= reactions($p['stats']) ?>
            <button class="act" data-comments><?= icon('comment') ?><span><?= nfmt($p['stats']['comments']) ?></span></button>
            <div class="actions__end">
                <span class="views" title="Просмотры"><?= icon('eye') ?><?= nfmt($p['stats']['views']) ?></span>
                <button class="act" data-share aria-label="Поделиться"><?= icon('share') ?></button>
            </div>
        </div>

        <?php if ($isArticle): ?><template class="post__full"><?= $p['article'] ?></template><?php endif; ?>
    </article>
<?php
}

/* ======================================================================
   КОМПОЗЕР
   Выбор игры убран: игра определяется по @упоминанию в тексте.
   ====================================================================== */
$targets = [
    ['id' => 'me',  'type' => 'user',    'name' => 'Alexander',      'role' => 'личный аккаунт',   'initial' => 'A',  'frame' => 'gold'],
    ['id' => 'nm',  'type' => 'studio',  'name' => 'Nuclear Muffin', 'role' => 'студия · админ',   'initial' => 'N',  'frame' => 'neon', 'verified' => 'dev'],
    ['id' => 'jam', 'type' => 'channel', 'name' => 'Dustore Jam',    'role' => 'канал · редактор', 'initial' => 'DJ', 'frame' => 'jam',  'verified' => 'official'],
];

function render_composer($targets)
{
    $t = $targets[0];
?>
    <div class="cc" id="composer">
        <div class="cc__head">
            <button class="cc__target" data-pick="target">
                <?= avatar($t, 26) ?>
                <span class="cc__hint">Публикация на стене:</span>
                <b class="cc__tname"><?= e($t['name']) ?></b>
                <?= icon('chev-down', 'ic--sm') ?>
            </button>
            <span class="cc__gametag hidden" data-gametag><?= icon('gamepad', 'ic--sm') ?><b></b></span>
            <button class="cc__drafts" data-opendrafts><?= icon('bookmark', 'ic--sm') ?> Черновики <i data-draftcount>0</i></button>
        </div>

        <textarea class="cc__field" rows="1" placeholder="Что нового? Напишите @ чтобы упомянуть игру, студию или человека."></textarea>

        <div class="cc__suggest hidden" data-suggest>
            <?= icon('article', 'ic--sm') ?>
            <span>Это уже похоже на статью — <b class="cc__words">0</b> слов.</span>
            <button class="cc__go" data-toeditor>Открыть редактор <?= icon('arrow-rt', 'ic--sm') ?></button>
        </div>

        <div class="cc__bar">
            <div class="cc__tools">
                <button title="Фото"><?= icon('image') ?></button>
                <button title="Видео"><?= icon('video') ?></button>
                <button title="Файл"><?= icon('clip') ?></button>
                <button title="Опрос"><?= icon('poll') ?></button>
                <button title="Статья" data-toeditor><?= icon('article') ?></button>
            </div>
            <span class="cc__count">0</span>
            <button class="cc__submit" disabled>Опубликовать</button>
        </div>
    </div>
<?php
}

/* ======================================================================
   ДАННЫЕ (заглушка — сюда подставляется выборка из БД)
   ====================================================================== */
$posts = [
    [
        'id' => 1,
        'source' => 'studio',
        'kind' => 'article',
        'author' => [
            'type' => 'studio',
            'name' => 'Nuclear Muffin',
            'role' => 'студия',
            'verified' => 'dev',
            'frame' => 'neon',
            'initial' => 'N'
        ],
        'time' => '24 мин назад',
        'space' => 'Space: К.О.Н.Т.У.Р.',
        'following' => false,
        'title' => 'Мы наконец поняли, зачем в @[game:kontur|К.О.Н.Т.У.Р.] нужен этот лифт',
        'text' => 'Спойлер: игроки нашли применение раньше нас. Мы полтора месяца считали его декорацией, а потом посмотрели двести часов записей плейтестов. Спасибо @[user:grumpy|grumpy_dev] за наводку.',
        'readtime' => '6 мин',
        'tags' => ['разработка', 'контур', 'левелдизайн'],
        'stats' => ['up' => 184, 'down' => 3, 'comments' => 32, 'views' => 12400, 'reaction' => null],
        'article' => <<<HTML
            <p>Лифт в секторе B появился в билде 0.3 как чистая декорация. Нам нужен был вертикальный ориентир, чтобы игрок не терялся в трёх одинаковых коридорах.</p>
            <h3>Что случилось дальше</h3>
            <p>Через неделю после релиза в раннем доступе мы заметили аномалию в телеметрии: медианное время в секторе B выросло с 4 до 19 минут. Мы решили, что где-то сломался триггер двери.</p>
            <blockquote>Оказалось, игроки использовали лифт как комнату ожидания. Кабина — единственное место на уровне, где нельзя получить урон.</blockquote>
            <p>Мы обсуждали фикс два дня. А потом решили не чинить, а достроить: добавили внутри доску объявлений, свет, который меняется от количества людей, и радио с треками сообщества.</p>
            <h3>Вывод, который мы записали на стену</h3>
            <p>Если игроки нашли своё применение вашей механике — сначала посмотрите, что они строят, и только потом решайте, ломать это или нет.</p>
HTML
    ],
    [
        'id' => 2,
        'source' => 'friend',
        'kind' => 'clip',
        'author' => [
            'type' => 'user',
            'name' => 'Антон К.',
            'role' => 'друг',
            'frame' => 'gold',
            'deco' => ['👑', 'top'],
            'initial' => 'А'
        ],
        'time' => '1 ч назад',
        'space' => null,
        'following' => true,
        'title' => 'Этот момент в @[game:iron|Iron Orchard] я пересматривал раз пять',
        'text' => 'Пытался пройти комнату без урона. Получилось случайно.',
        'duration' => '0:42',
        'tags' => ['клип'],
        'stats' => ['up' => 927, 'down' => 12, 'comments' => 71, 'views' => 31800, 'reaction' => 'fire'],
    ],
    [
        'id' => 3,
        'source' => 'channel',
        'kind' => 'collage',
        'author' => [
            'type' => 'channel',
            'name' => 'Dustore Jam',
            'role' => 'канал',
            'verified' => 'official',
            'frame' => 'jam',
            'deco' => ['🎪', 'corner'],
            'initial' => 'DJ'
        ],
        'time' => '2 ч назад',
        'space' => null,
        'following' => true,
        'title' => 'Финалисты осеннего джема',
        'text' => 'Шесть проектов из 214. Голосование открыто до воскресенья в @[space:jam|Dustore Jam].',
        'media' => [null, null, null, null, null, null],
        'tags' => ['джем', 'итоги'],
        'stats' => ['up' => 1420, 'down' => 31, 'comments' => 208, 'views' => 88300, 'reaction' => null],
    ],
    [
        'id' => 4,
        'source' => 'studio',
        'kind' => 'files',
        'author' => [
            'type' => 'studio',
            'name' => 'Frog Factory',
            'role' => 'студия',
            'verified' => 'partner',
            'frame' => 'dev',
            'initial' => 'F'
        ],
        'time' => '3 ч назад',
        'space' => null,
        'following' => true,
        'title' => '@[game:iron|Iron Orchard] 0.8.0 уже здесь',
        'text' => 'Новый район, 14 предметов, переработанная боёвка и, наконец, тот самый летающий гусь.',
        'media' => [
            ['name' => 'IronOrchard_0.8.0_win64.dpk', 'size' => '1,8 ГБ · патч 240 МБ', 'icon' => 'download'],
            ['name' => 'changelog_0.8.0.pdf', 'size' => '420 КБ', 'icon' => 'article'],
        ],
        'stats' => ['up' => 341, 'down' => 7, 'comments' => 48, 'views' => 9600, 'reaction' => null],
    ],
];

$popular = [
    [
        'id' => 5,
        'source' => 'studio',
        'kind' => 'poll',
        'author' => [
            'type' => 'studio',
            'name' => 'Soda Games',
            'role' => 'студия',
            'verified' => 'dev',
            'frame' => 'neon',
            'initial' => 'S'
        ],
        'time' => 'сейчас',
        'space' => null,
        'following' => false,
        'title' => 'Какую концовку @[game:neon|Neon Harbour] вы бы выбрали?',
        'text' => 'Мы не можем решить. Теперь решаете вы.',
        'poll' => [
            ['label' => 'Оставить город', 'pct' => 61],
            ['label' => 'Уехать ночью',   'pct' => 27],
            ['label' => 'Сжечь всё',      'pct' => 12],
        ],
        'tags' => ['опрос'],
        'stats' => ['up' => 2841, 'down' => 44, 'comments' => 386, 'views' => 124000, 'votes' => 18400, 'reaction' => 'heart'],
    ],
    [
        'id' => 6,
        'source' => 'friend',
        'kind' => 'photo',
        'author' => ['type' => 'user', 'name' => 'Kettlehead', 'role' => 'игрок', 'initial' => 'K'],
        'time' => '2 ч назад',
        'space' => null,
        'following' => false,
        'title' => 'В @[game:iron|Iron Orchard] можно вот так',
        'text' => 'Разработчик, пожалуйста, не фиксите. @[studio:frog-factory|Frog Factory], я серьёзно.',
        'tags' => ['баг', 'фича'],
        'stats' => ['up' => 4291, 'down' => 88, 'comments' => 211, 'views' => 402000, 'reaction' => null],
    ],
];

/* демо-данные боковых разделов */
$hunts = [
    ['game' => 'К.О.Н.Т.У.Р.', 'task' => 'Найти все 12 записей на плёнке', 'prize' => 'Рамка «Архивариус»', 'done' => 7, 'of' => 12],
    ['game' => 'Iron Orchard', 'task' => 'Пройти сад без урона',           'prize' => '500 ✦ на баланс',   'done' => 0, 'of' => 1],
    ['game' => 'Neon Harbour', 'task' => 'Собрать 3 концовки',             'prize' => 'Ранний доступ 1.0', 'done' => 2, 'of' => 3],
];
$spaces = [
    ['name' => 'К.О.Н.Т.У.Р.', 'members' => 12400, 'posts' => 318, 'hot' => true],
    ['name' => 'Iron Orchard', 'members' => 8720,  'posts' => 204, 'hot' => false],
    ['name' => 'Dustore Jam',  'members' => 31900, 'posts' => 1102, 'hot' => true],
    ['name' => 'Движкостроение', 'members' => 2140, 'posts' => 96, 'hot' => false],
];

$NAV = [
    ['view' => 'feed',    'icon' => 'home',     'label' => 'Лента'],
    ['view' => 'popular', 'icon' => 'flame',    'label' => 'Популярное'],
    ['view' => 'friends', 'icon' => 'users',    'label' => 'Друзья'],
    ['view' => 'hunt',    'icon' => 'target',   'label' => 'Dustore.Hunt'],
    ['view' => 'spaces',  'icon' => 'grid',     'label' => 'Spaces'],
    ['view' => 'drafts',  'icon' => 'bookmark', 'label' => 'Черновики'],
];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0d0911">
    <title>Dustore.Fid</title>

    <!-- Шрифты. Для РФ-аудитории лучше self-host: положите woff2 в /fonts
         и включите блок @font-face в начале style.css, а этот <link> уберите. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&family=Literata:ital,opsz,wght@0,7..72,400;0,7..72,600;1,7..72,400&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css?v=<?= rand(1, 999999) ?>">
</head>

<body>
    <?php icon_sprite(); ?>

    <div class="app">
        <?php require_once('../swad/static/elements/header.php'); ?>

        <main class="layout">
            <aside class="side">
                <?php foreach ($NAV as $i => $n): ?>
                    <a class="<?= $i === 0 ? 'active' : '' ?>" data-view="<?= e($n['view']) ?>">
                        <?= icon($n['icon']) ?><span><?= e($n['label']) ?></span>
                        <?php if ($n['view'] === 'drafts'): ?><i class="side__badge" data-draftcount>0</i><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <a class="new" data-toeditor><?= icon('plus') ?><span>Новый пост</span></a>
            </aside>

            <section>
                <?php render_composer($targets); ?>

                <div class="tabs" role="tablist">
                    <button class="tab active" data-tab="feed" role="tab">Лента</button>
                    <button class="tab" data-tab="popular" role="tab">Популярное</button>
                    <div class="tabs__ink"></div>
                </div>

                <div class="feeds">
                    <div class="ptr" data-ptr>Потяните, чтобы обновить</div>

                    <div id="feed" class="feed" data-pane="feed">
                        <?php foreach ($posts as $p) render_post($p, $ENTITIES); ?>
                    </div>

                    <div id="popular" class="feed hidden" data-pane="popular">
                        <?php foreach ($popular as $p) render_post($p, $ENTITIES); ?>
                    </div>

                    <!-- ---------- Друзья ---------- -->
                    <div class="feed hidden" data-pane="friends">
                        <div class="vhead"><?= icon('users') ?><div><b>Посты друзей</b><small>только те, кто у вас в друзьях</small></div>
                        </div>
                        <?php foreach (array_merge($posts, $popular) as $p) if ($p['source'] === 'friend') render_post($p, $ENTITIES); ?>
                    </div>

                    <!-- ---------- Dustore.Hunt ---------- -->
                    <div class="feed hidden" data-pane="hunt">
                        <div class="vhead"><?= icon('target') ?><div><b>Dustore.Hunt</b><small>задания от студий — награда за прохождение</small></div>
                        </div>
                        <?php foreach ($hunts as $h): $pct = (int)round($h['done'] / $h['of'] * 100); ?>
                            <div class="hunt">
                                <div class="hunt__cover"></div>
                                <div class="hunt__b">
                                    <div class="hunt__game"><?= e($h['game']) ?></div>
                                    <div class="hunt__task"><?= e($h['task']) ?></div>
                                    <div class="hunt__prize"><?= icon('star', 'ic--sm') ?><?= e($h['prize']) ?></div>
                                    <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
                                    <div class="hunt__pg"><?= $h['done'] ?> из <?= $h['of'] ?></div>
                                </div>
                                <button class="hunt__go"><?= $h['done'] ? 'Продолжить' : 'Начать' ?> <?= icon('arrow-rt', 'ic--sm') ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ---------- Spaces ---------- -->
                    <div class="feed hidden" data-pane="spaces">
                        <div class="vhead"><?= icon('grid') ?><div><b>Ваши Spaces</b><small>игра или тема — единица сообщества</small></div>
                        </div>
                        <div class="spgrid">
                            <?php foreach ($spaces as $s): ?>
                                <div class="sp">
                                    <div class="sp__cover"><?php if ($s['hot']): ?><span class="sp__hot"><?= icon('flame', 'ic--sm') ?>активен</span><?php endif; ?></div>
                                    <div class="sp__b">
                                        <b><?= e($s['name']) ?></b>
                                        <small><?= nfmt($s['members']) ?> участников · <?= $s['posts'] ?> постов</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ---------- Черновики ---------- -->
                    <div class="feed hidden" data-pane="drafts">
                        <div class="vhead"><?= icon('bookmark') ?><div><b>Черновики</b><small>хранятся в cookie этого браузера</small></div>
                        </div>
                        <div data-draftlist></div>
                    </div>
                </div>
            </section>

            <aside class="right">
                <h3>Сейчас обсуждают</h3>
                <div class="card">
                    <div class="game">
                        <div class="cover"></div>
                        <div><b>Iron Orchard</b><small>48 обсуждений</small></div>
                    </div>
                </div>
                <div class="card">
                    <div class="stat"><span>К.О.Н.Т.У.Р.</span><b>+18%</b></div>
                    <div class="stat"><span>новых игроков сегодня</span><b>2 491</b></div>
                </div>
                <h3>Ваши Spaces</h3>
                <div class="card">
                    <?php foreach (array_slice($spaces, 0, 3) as $s): ?>
                        <div class="stat"><span><?= e($s['name']) ?></span><?= icon('arrow-rt', 'ic--sm') ?></div>
                    <?php endforeach; ?>
                </div>
            </aside>
        </main>
    </div>

    <nav class="mobnav">
        <a class="active" data-view="feed"><?= icon('home') ?>Лента</a>
        <a data-view="hunt"><?= icon('target') ?>Хант</a>
        <a class="plus" data-toeditor><?= icon('plus') ?></a>
        <a data-view="spaces"><?= icon('grid') ?>Spaces</a>
        <a data-view="drafts"><?= icon('bookmark') ?>Черновики</a>
    </nav>

    <!-- общий оверлей: пост с комментариями / режим чтения -->
    <div class="ov" id="ov" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="ov__scrim" data-ovclose></div>
        <div class="ov__sheet">
            <div class="ov__grip"></div>
            <header class="ov__bar">
                <button class="ov__close" data-ovclose aria-label="Закрыть"><?= icon('close') ?></button>
                <div>
                    <div class="ov__title"></div>
                    <div class="ov__sub"></div>
                </div>
            </header>
            <div class="progress"></div>
            <div class="ov__scroll">
                <div class="ov__inner"></div>
            </div>
            <div class="rail">
                <span class="rail__views"></span>
                <button class="rail__cmts" aria-label="К комментариям"><?= icon('comment') ?><span class="rail__badge"></span></button>
                <button class="rail__up" aria-label="Наверх"><?= icon('arrow-up') ?></button>
            </div>
            <form class="composer" data-composer>
                <div class="composer__reply hidden"><span></span><button type="button" data-cancelreply><?= icon('close', 'ic--sm') ?></button></div>
                <input type="text" placeholder="Написать комментарий…" required>
                <button class="composer__send" aria-label="Отправить"><?= icon('send', 'ic--sm') ?></button>
            </form>
        </div>
    </div>

    <!-- ================= РЕДАКТОР СТАТЬИ ================= -->
    <div class="ed" id="ed" role="dialog" aria-modal="true" aria-hidden="true">
        <header class="ed__bar">
            <button class="ed__back" data-edclose aria-label="Закрыть"><?= icon('arrow-lt') ?></button>
            <div class="ed__who">
                <div class="ed__title">Редактор статьи</div>
                <div class="ed__saved" data-saved>черновик не сохранён</div>
            </div>
            <div class="ed__modes">
                <button class="on" data-mode="wys">Визуально</button>
                <button data-mode="md">Разметка</button>
            </div>
            <button class="ed__icon" data-opendrafts title="Черновики"><?= icon('bookmark') ?></button>
            <button class="ed__icon ed__opts" data-edopts title="Параметры"><?= icon('settings') ?></button>
            <button class="ed__pub" data-edpublish>Опубликовать</button>
        </header>

        <div class="ed__body">
            <main class="ed__main">
                <div class="ed__toolbar" data-toolbar>
                    <button data-md="h2" title="Подзаголовок">H2</button>
                    <button data-md="h3" title="Подзаголовок 3">H3</button>
                    <span class="ed__sep"></span>
                    <button data-md="b" title="Жирный"><?= icon('bold') ?></button>
                    <button data-md="i" title="Курсив"><?= icon('italic') ?></button>
                    <button data-md="code" title="Код"><?= icon('code') ?></button>
                    <span class="ed__sep"></span>
                    <button data-md="quote" title="Цитата"><?= icon('quote') ?></button>
                    <button data-md="ul" title="Список"><?= icon('list') ?></button>
                    <button data-md="ol" title="Нумерованный"><?= icon('list-ol') ?></button>
                    <span class="ed__sep"></span>
                    <button data-md="link" title="Ссылка"><?= icon('link') ?></button>
                    <button data-md="img" title="Картинка"><?= icon('image') ?></button>
                    <button data-md="hr" title="Разделитель"><?= icon('minus') ?></button>
                    <span class="ed__sep"></span>
                    <button data-md="at" title="Упоминание">@</button>
                </div>

                <div class="ed__paper">
                    <textarea class="ed__h1" data-edtitle rows="1" placeholder="Заголовок статьи"></textarea>
                    <div class="ed__wys" data-wys contenteditable="true" spellcheck="true"></div>
                    <textarea class="ed__md hidden" data-md-area spellcheck="false"></textarea>
                </div>

                <div class="ed__stats" data-stats></div>
            </main>

            <aside class="ed__side">
                <div class="ed__grip" data-edopts></div>
                <div class="ed__tabs">
                    <button class="on" data-pane="pub">Публикация</button>
                    <button data-pane="seo">SEO</button>
                    <button data-pane="cross">Кросспостинг</button>
                </div>

                <div class="ed__pane" data-pane-body="pub">
                    <label class="f">Куда публикуем</label>
                    <div class="seg" data-edtarget>
                        <?php foreach ($targets as $i => $t): ?>
                            <button class="<?= $i === 0 ? 'on' : '' ?>" data-tid="<?= e($t['id']) ?>"><?= e($t['name']) ?></button>
                        <?php endforeach; ?>
                    </div>

                    <label class="f">Игра</label>
                    <div class="autogame" data-autogame>
                        <?= icon('gamepad', 'ic--sm') ?>
                        <span>Не найдена — упомяните игру через @ в заголовке</span>
                    </div>

                    <label class="f">Обложка</label>
                    <div class="drop" data-drop>Перетащите файл или нажмите<br><small>1200×630, до 4 МБ</small></div>

                    <label class="f">Теги <small>через запятую</small></label>
                    <input class="inp" data-edtags placeholder="разработка, контур, левелдизайн">
                    <div class="chips" data-chips></div>

                    <label class="f">Как выглядит в ленте</label>
                    <div class="seg seg--v" data-edcard>
                        <button class="on" data-card="big">Крупная карточка с обложкой</button>
                        <button data-card="small">Компактная — обложка слева</button>
                        <button data-card="text">Только текст и заголовок</button>
                    </div>
                    <div class="cardprev" data-cardprev></div>

                    <label class="sw"><input type="checkbox" checked data-opt="comments"><span></span>Комментарии</label>
                    <label class="sw"><input type="checkbox" data-opt="adult"><span></span>Метка 18+</label>
                    <label class="sw"><input type="checkbox" data-opt="pin"><span></span>Закрепить в Space</label>
                </div>

                <div class="ed__pane hidden" data-pane-body="seo">
                    <label class="f">Адрес страницы</label>
                    <div class="slug"><span>dustore.ru/fid/</span><input data-edslug placeholder="kak-my-ponyali-zachem-lift"></div>

                    <label class="f">Title <b class="cnt" data-cnt="title">0/60</b></label>
                    <input class="inp" data-edseotitle maxlength="90" placeholder="Наследует заголовок статьи">

                    <label class="f">Description <b class="cnt" data-cnt="desc">0/160</b></label>
                    <textarea class="inp" rows="3" data-edseodesc maxlength="260" placeholder="Наследует первый абзац"></textarea>

                    <label class="f">Как увидят в поиске</label>
                    <div class="snippet">
                        <div class="snippet__url">dustore.ru › fid › <span data-snipslug>…</span></div>
                        <div class="snippet__t" data-snipt>Заголовок статьи</div>
                        <div class="snippet__d" data-snipd>Описание появится здесь.</div>
                    </div>

                    <label class="sw"><input type="checkbox" checked data-opt="index"><span></span>Разрешить индексацию</label>
                    <label class="sw"><input type="checkbox" checked data-opt="og"><span></span>og:image = обложка</label>
                    <label class="f">Каноническая ссылка <small>если статья дублируется</small></label>
                    <input class="inp" data-edcanon placeholder="https://…">
                </div>

                <div class="ed__pane hidden" data-pane-body="cross">
                    <p class="note">Текст пересобирается под формат площадки. Скопируйте и вставьте — или подключите токен, чтобы публиковать сразу.</p>
                    <div class="plats" data-plats></div>
                    <label class="f">Готовый текст</label>
                    <textarea class="inp mono" rows="10" data-crossout readonly></textarea>
                    <div class="crossbar">
                        <span data-crosslimit></span>
                        <button class="btn" data-crosscopy><?= icon('link', 'ic--sm') ?> Скопировать</button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div class="scrim" id="scrim"></div>
    <div class="menu" id="menu"></div>
    <div class="mnbox" id="mnbox"></div>
    <div id="toast" class="toast"></div>

    <script>
        window.DFID = {
            targets: <?= json_encode($targets, JSON_UNESCAPED_UNICODE) ?>,
            entities: <?= json_encode($ENTITIES, JSON_UNESCAPED_UNICODE) ?>,
            mentionIcon: <?= json_encode(MENTION_ICON, JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="js/feed.js?v=<?= rand(1, 999999) ?>"></script>
    <script src="js/editor.js?v=<?= rand(1, 999999) ?>"></script>
</body>

</html>