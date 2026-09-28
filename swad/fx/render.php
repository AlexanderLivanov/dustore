<?php
declare(strict_types=1);

/**
 * swad/fx/render.php — серверный рендер карточек Fid Core.
 *
 * Одна разметка на все стены: /fid, хаб игры, хаб студии, хаб игрока, мобильное PWA.
 * Клиент (swad/js/fx.js) разметку не строит — он получает готовый HTML из api/fx.php,
 * поэтому карточка гарантированно одинаковая при первой загрузке и при подгрузке.
 *
 * Классы — с префиксом fx-, стили в swad/css/fx.css.
 */

require_once __DIR__ . '/boot.php';

final class FxRender
{
    public static function e($s): string { return Fx::e($s); }
    public static function i(string $n, string $cls = ''): string { return fx_icon($n, $cls); }

    /** Спрайт иконок — один раз на страницу. */
    public static function sprite(): string
    {
        static $done = false;
        if ($done) return '';
        $done = true;
        ob_start();
        fx_icon_sprite();
        return (string)ob_get_clean();
    }

    /* ------------------------------------------------------------------
       ТЕКСТ: экранирование + @упоминания + ссылки + абзацы
       ------------------------------------------------------------------ */
    private const MENTION_ICON = ['user' => 'user', 'game' => 'gamepad', 'studio' => 'studio'];

    public static function mention(string $type, int $id, string $name): string
    {
        $href = '#';
        if ($type === 'game') $href = Fx::urlGame($id);
        elseif ($type === 'user') $href = Fx::urlUser($name);
        elseif ($type === 'studio') { $s = FxPeople::studio($id); $href = $s['url'] ?? '#'; }
        return '<a class="fx-mn fx-mn--' . $type . '" href="' . self::e($href) . '" data-m="' . $type . ':' . $id . '">'
            . self::i(self::MENTION_ICON[$type] ?? 'user') . '<span>' . self::e($name) . '</span></a>';
    }

    /** Строка в одну линию (заголовки): упоминания есть, абзацев нет. */
    public static function inline(string $s): string
    {
        $parts = preg_split('/(@\[(?:user|game|studio):\d+\|[^\]]+\])/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$s];
        $out = '';
        foreach ($parts as $x) {
            if (preg_match('/^@\[(user|game|studio):(\d+)\|([^\]]+)\]$/u', $x, $m)) $out .= self::mention($m[1], (int)$m[2], $m[3]);
            else $out .= self::e($x);
        }
        return $out;
    }

    public static function rich(string $s): string
    {
        $paras = preg_split('/\n{2,}/', trim($s)) ?: [];
        $out = '';
        foreach ($paras as $p) {
            if (trim($p) === '') continue;
            $parts = preg_split('/(@\[(?:user|game|studio):\d+\|[^\]]+\])/u', $p, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$p];
            $h = '';
            foreach ($parts as $x) {
                if (preg_match('/^@\[(user|game|studio):(\d+)\|([^\]]+)\]$/u', $x, $m)) { $h .= self::mention($m[1], (int)$m[2], $m[3]); continue; }
                $e = self::e($x);
                $e = preg_replace_callback('~(https?://[^\s<]+[^\s<.,;:!?)\]»"\'])~u', static function ($mm) {
                    $u = $mm[1];
                    $label = mb_strlen(html_entity_decode($u)) > 46 ? mb_substr(html_entity_decode($u), 0, 44) . '…' : html_entity_decode($u);
                    return '<a href="' . $u . '" target="_blank" rel="noopener noreferrer nofollow ugc">' . Fx::e($label) . '</a>';
                }, $e) ?? $e;
                $h .= $e;
            }
            $out .= '<p>' . nl2br($h, false) . '</p>';
        }
        return $out;
    }

    /* ------------------------------------------------------------------
       АВАТАР: форма = тип автора (круг — человек, квадрат — студия/канал),
       рамка и стикер — независимые оси.
       ------------------------------------------------------------------ */
    public static function avatar(array $a, int $size = 44, bool $link = true): string
    {
        $type = $a['type'] ?? 'user';
        $frame = !empty($a['frame']) ? ' fr-' . self::e($a['frame']) : '';
        $img = !empty($a['img']) ? ' style="background-image:url(\'' . self::e($a['img']) . '\')"' : '';
        $ini = empty($a['img']) ? self::e(mb_strtoupper(mb_substr((string)($a['name'] ?? '?'), 0, 1))) : '';
        $tag = ($link && !empty($a['url']) && $a['url'] !== '#') ? 'a' : 'span';
        $href = $tag === 'a' ? ' href="' . self::e($a['url']) . '"' : '';
        return '<' . $tag . $href . ' class="fx-av fx-av--' . self::e($type) . $frame . '" style="--s:' . $size . 'px">'
            . '<span class="fx-av__frame"></span><span class="fx-av__img"' . $img . '>' . $ini . '</span></' . $tag . '>';
    }

    public static function verified(?string $v): string
    {
        $map = ['official' => ['check', 'Официальный аккаунт'], 'dev' => ['wrench', 'Подтверждённая студия'], 'partner' => ['star', 'Партнёр Dustore']];
        if (!$v || !isset($map[$v])) return '';
        return '<span class="fx-vf fx-vf--' . self::e($v) . '" title="' . self::e($map[$v][1]) . '">' . self::i($map[$v][0]) . '</span>';
    }

    /* ------------------------------------------------------------------
       ПОСТ
       $ctx: viewer:int, hide_origin:bool (на «родной» стене метку не показываем),
             show_reason:bool, compact:bool
       ------------------------------------------------------------------ */
    public static function post(array $p, array $ctx = []): string
    {
        $a = $p['author'];
        $isArticle = $p['has_article'];
        $isThreadPost = $p['channel'] === 'forum';
        $h = '<article class="fx-post' . ($p['pinned'] ? ' is-pinned' : '') . '" data-id="' . $p['id'] . '" data-kind="' . self::e($p['kind']) . '"'
            . ' data-can="' . self::e(json_encode($p['can'])) . '" data-author="' . $p['author_id'] . '">';

        /* --- шапка --- */
        $h .= '<div class="fx-posthead">' . self::avatar($a);
        $h .= '<div class="fx-posthead__id"><div class="fx-author">';
        $h .= !empty($a['url']) && $a['url'] !== '#'
            ? '<a class="fx-author__name" href="' . self::e($a['url']) . '">' . self::e($a['name']) . '</a>'
            : '<span class="fx-author__name">' . self::e($a['name']) . '</span>';
        $h .= self::verified($a['verified'] ?? null);
        if ($p['pinned']) $h .= '<span class="fx-pin" title="Закреплено">' . self::i('pin', 'ic--sm') . '</span>';
        $h .= '</div><div class="fx-meta">';
        $bits = [];
        if (!empty($p['byline'])) {
            $b = $p['byline'];
            $bits[] = '<span class="fx-by">' . ($b['url'] ? '<a href="' . self::e($b['url']) . '">' . self::e($b['name']) . '</a>' : self::e($b['name']))
                . ($b['role'] !== '' ? ' <em>' . self::e($b['role']) . '</em>' : '') . '</span>';
        }
        $bits[] = '<time datetime="' . self::e($p['iso']) . '" title="' . self::e($p['iso']) . '">' . self::e($p['time']) . '</time>' . ($p['edited'] ? ' <span class="fx-edited">изм.</span>' : '');
        $own = !empty($ctx['wall']) && $p['wall_type'] === $ctx['wall']['type'] && $p['wall_id'] === (int)$ctx['wall']['id'];
        if (empty($ctx['hide_origin']) && !$own) {
            $o = $p['origin'];
            $bits[] = '<a class="fx-origin fx-origin--' . self::e($o['key']) . '" href="' . self::e($o['url']) . '">' . self::i($o['icon'], 'ic--sm') . '<span>' . self::e($o['label']) . '</span></a>';
        }
        $h .= implode('<i>·</i>', $bits);
        $h .= '</div></div><div class="fx-hb">';
        // на странице самой студии/игры кнопка «Подписаться» уже есть в баннере — под каждым постом её не дублируем
        $selfFollow = $p['follow'] && !empty($ctx['wall']) && $p['follow']['type'] === $ctx['wall']['type'] && (int)$p['follow']['id'] === (int)$ctx['wall']['id'];
        if ($p['follow'] && !$selfFollow) {
            $f = $p['follow'];
            $h .= '<button type="button" class="fx-follow' . ($f['on'] ? ' on' : '') . '" data-fx="follow" data-t="' . $f['type'] . '" data-id="' . $f['id'] . '">'
                . ($f['on'] ? self::i('check', 'ic--sm') . ' Подписаны' : 'Подписаться') . '</button>';
        }
        $h .= '<button type="button" class="fx-dots" data-fx="menu" aria-label="Ещё">' . self::i('dots') . '</button></div></div>';

        /* --- тело --- */
        $h .= '<div class="fx-body">';
        if (!empty($ctx['show_reason']) && !empty($p['reason'])) {
            $h .= '<div class="fx-reason fx-reason--' . self::e($p['reason']['key']) . '">' . self::e($p['reason']['label']) . '</div>';
        }
        if ($p['title'] !== '') {
            $h .= $isArticle
                ? '<h2><a href="' . Fx::urlPost((int)$p['id']) . '" data-fx="open" class="fx-h2link">' . self::inline($p['title']) . '</a></h2>'
                : '<h2>' . self::inline($p['title']) . '</h2>';
        }
        if ($p['body'] !== '') $h .= '<div class="fx-text' . ($isArticle ? ' fx-excerpt' : '') . '">' . self::rich($p['body']) . '</div>';
        if ($isArticle) $h .= '<button type="button" class="fx-readmore" data-fx="open">' . self::i('article') . ' Читать статью</button>';
        $h .= self::poll($p);
        if ($p['tags']) {
            $h .= '<div class="fx-tags">';
            foreach ($p['tags'] as $t) $h .= '<span class="fx-tag">#' . self::e($t) . '</span>';
            $h .= '</div>';
        }
        $h .= '</div>';

        $h .= self::media($p);
        if ($p['game'] && ($ctx['wall_game'] ?? 0) !== $p['game']['id']) $h .= self::gamebar($p['game']);

        /* --- действия --- */
        $s = $p['stats'];
        $h .= '<div class="fx-actions">' . self::reactions($s);
        $h .= '<button type="button" class="fx-act" data-fx="open" data-focus="comments" aria-label="Комментарии">' . self::i('comment')
            . '<span class="fx-cm-n">' . Fx::n($s['comments']) . '</span></button>';
        $h .= '<div class="fx-actions__end"><span class="fx-views" title="Просмотры">' . self::i('eye') . Fx::n($s['views']) . '</span>'
            . '<button type="button" class="fx-act" data-fx="share" aria-label="Поделиться">' . self::i('share') . '</button></div></div>';
        return $h . '</article>';
    }

    public static function posts(array $list, array $ctx = []): string
    {
        $out = '';
        foreach ($list as $p) $out .= $p['channel'] === 'forum' && !empty($ctx['threads']) ? self::thread($p, $ctx) : self::post($p, $ctx);
        return $out;
    }

    public static function poll(array $p): string
    {
        $poll = $p['poll'];
        if (!$poll) return '';
        $voted = !empty($poll['mine']);
        $show = $voted || $poll['ended'];
        $h = '<div class="fx-poll' . ($show ? ' voted' : '') . '" data-poll>';
        foreach ($poll['opts'] as $o) {
            $mine = in_array($o['i'], $poll['mine'], true);
            $h .= '<button type="button" class="fx-option' . ($mine ? ' mine' : '') . '" data-fx="vote" data-i="' . $o['i'] . '"' . ($show ? ' disabled' : '') . '>'
                . '<i style="width:' . ($show ? $o['pct'] : 0) . '%"></i><span>' . self::e($o['label']) . '<b>' . $o['pct'] . '%</b></span></button>';
        }
        $left = '';
        if ($poll['ended']) $left = ' · завершён';
        elseif ($poll['ends']) {
            $d = strtotime((string)$poll['ends']) - time();
            $left = $d > 86400 ? ' · ещё ' . (int)floor($d / 86400) . ' дн' : ' · ещё ' . max(1, (int)floor($d / 3600)) . ' ч';
        }
        return $h . '<div class="fx-poll__total"><span class="fx-poll__n">' . Fx::n($poll['total']) . '</span> '
            . Fx::plural($poll['total'], 'голос', 'голоса', 'голосов') . $left . '</div></div>';
    }

    public static function media(array $p): string
    {
        $m = $p['media'];
        if (!$m) return '';
        $n = count($m);
        $img = static function (array $x, string $cls = '') {
            $wh = ($x['w'] && $x['h']) ? ' width="' . $x['w'] . '" height="' . $x['h'] . '"' : '';
            return '<img class="fx-ph ' . $cls . '" src="' . Fx::e($x['u']) . '" alt="" loading="lazy" draggable="false"' . $wh . ' data-fx="zoom">';
        };
        if ($n === 1) return '<div class="fx-media" data-dbl>' . $img($m[0], 'fx-ph--one') . '<div class="fx-dbl">' . self::i('heart', 'ic--xl') . '</div></div>';
        $shown = min($n, 4);
        $h = '<div class="fx-media" data-dbl><div class="fx-collage fx-collage--' . $shown . '">';
        for ($i = 0; $i < $shown; $i++) $h .= $img($m[$i]);
        $h .= '</div>';
        if ($n > $shown) $h .= '<div class="fx-collage__more">+' . ($n - $shown) . '</div>';
        return $h . '<div class="fx-dbl">' . self::i('heart', 'ic--xl') . '</div></div>';
    }

    public static function gamebar(array $g): string
    {
        $cover = $g['icon'] ? ' style="background-image:url(\'' . self::e($g['icon']) . '\')"' : '';
        $h = '<a class="fx-gamebar" href="' . self::e($g['url']) . '"><span class="fx-gamebar__cover"' . $cover . '></span>';
        $h .= '<span class="fx-gamebar__id"><span class="fx-gamebar__name">' . self::e($g['name']) . '</span>';
        if ($g['rating'] !== null) $h .= '<span class="fx-gamebar__sub"><span class="fx-rate">' . self::i('star') . number_format($g['rating'], 1, ',', '') . '</span></span>';
        return $h . '</span><span class="fx-gamebar__play">Страница игры ' . self::i('arrow-rt') . '</span></a>';
    }

    public static function reactions(array $s): string
    {
        $mine = $s['mine'] ?? null;
        $group = static function (string $side, array $set, int $count) use ($mine) {
            $names = array_keys($set);
            $on = ($mine && in_array($mine, $names, true)) ? ' on' : '';
            $ic = $on ? $mine : $names[0];
            $h = '<div class="fx-rxg fx-rxg--' . $side . '" data-side="' . $side . '" data-def="' . $names[0] . '">';
            $h .= '<button type="button" class="fx-act fx-rx__main' . $on . '" data-fx="react" data-e="' . ($on ? $mine : $names[0]) . '" data-count="' . $count . '" aria-label="Реакция">'
                . fx_icon($ic) . '<span class="fx-rx__n">' . Fx::n($count) . '</span></button><div class="fx-rxpop" role="menu">';
            foreach ($set as $n => $title) {
                $h .= '<button type="button" class="fx-rxo" role="menuitem" data-fx="react" data-e="' . $n . '" title="' . Fx::e($title) . '">' . fx_icon($n) . '</button>';
            }
            return $h . '</div></div>';
        };
        return $group('up', FxReact::UP, $s['up']) . $group('down', FxReact::DOWN, $s['down']);
    }

    /* ------------------------------------------------------------------
       ФОРУМ: ветка как раскрывающаяся строка. Тело и ответы подгружаются при раскрытии.
       ------------------------------------------------------------------ */
    public static function thread(array $p, array $ctx = []): string
    {
        $a = $p['author'];
        $h = '<div class="fx-thr' . ($p['pinned'] ? ' is-pinned' : '') . ($p['locked'] ? ' is-locked' : '') . '" data-id="' . $p['id'] . '" data-can="' . self::e(json_encode($p['can'])) . '">';
        $h .= '<div class="fx-thr__h" role="button" tabindex="0" data-fx="thread">';
        $h .= self::avatar($a, 36, false);
        $h .= '<div class="fx-thr__id"><div class="fx-thr__t">';
        if ($p['pinned']) $h .= '<span class="fx-pin">' . self::i('pin', 'ic--sm') . '</span>';
        if ($p['locked']) $h .= '<span class="fx-lock">' . self::i('lock', 'ic--sm') . '</span>';
        $h .= self::inline($p['title']) . '</div><div class="fx-thr__m"><span>' . self::e($a['name']) . '</span><i>·</i><span>' . self::e($p['time']) . '</span></div></div>';
        $h .= '<div class="fx-thr__n" title="Ответов">' . self::i('comment', 'ic--sm') . '<span class="fx-cm-n">' . Fx::n($p['stats']['comments']) . '</span></div>';
        $h .= '<button type="button" class="fx-dots" data-fx="menu" aria-label="Ещё">' . self::i('dots') . '</button>';
        $h .= '<span class="fx-thr__chev">' . self::i('chev-down') . '</span></div>';
        $h .= '<div class="fx-thr__b" hidden></div></div>';
        return $h;
    }

    /** Раскрытая ветка: сообщение автора + дерево ответов + поле ответа. */
    public static function threadBody(array $p, array $tree, int $viewer): string
    {
        $h = '<div class="fx-thr__op">';
        if ($p['body'] !== '') $h .= '<div class="fx-text">' . self::rich($p['body']) . '</div>';
        $h .= self::poll($p) . self::media($p) . '</div>';
        $h .= '<div class="fx-cmts" data-post="' . $p['id'] . '">' . self::comments($tree, $p, $viewer, false) . '</div>';
        if ($p['locked']) $h .= '<div class="fx-note">' . self::i('lock', 'ic--sm') . ' Ветка закрыта модератором</div>';
        else $h .= self::replyBox($viewer, $p['id']);
        return $h;
    }

    /* ------------------------------------------------------------------ КОММЕНТАРИИ */

    public static function comments(array $roots, array $post, int $viewer, bool $head = true, string $sort = 'top'): string
    {
        $count = $post['stats']['comments'];
        $h = '';
        if ($head) {
            $h .= '<div class="fx-cmts__head"><h3>Комментарии <span class="fx-cm-n">' . Fx::n($count) . '</span></h3>'
                . '<button type="button" class="fx-cmts__sort" data-fx="sort" data-sort="' . ($sort === 'top' ? 'new' : 'top') . '">'
                . ($sort === 'top' ? 'Сначала популярные' : 'Сначала новые') . '</button></div>';
        }
        if (!$roots) return $h . '<div class="fx-empty fx-empty--sm">Пока никто не ответил. Будьте первым.</div>';
        foreach ($roots as $c) $h .= self::comment($c, $viewer);
        return $h;
    }

    public static function comment(array $c, int $viewer): string
    {
        $a = $c['author'];
        $h = '<div class="fx-cmt" data-id="' . $c['id'] . '"><div class="fx-cmt__av">' . self::avatar($a, 32) . '</div><div class="fx-cmt__b">';
        $h .= '<div class="fx-cmt__top"><a class="fx-cmt__name" href="' . self::e($a['url']) . '">' . self::e($a['name']) . '</a>';
        if ($c['op']) $h .= '<span class="fx-cmt__op">автор</span>';
        $h .= '<span class="fx-cmt__time">' . self::e($c['time']) . '</span></div>';
        $h .= '<div class="fx-cmt__txt">' . self::rich($c['body']) . '</div><div class="fx-cmt__acts">';
        $h .= '<button type="button" data-fx="clike" class="' . ($c['liked'] ? 'on' : '') . '">' . self::i('heart', 'ic--sm') . '<span>' . ($c['likes'] ?: '') . '</span></button>';
        if ($viewer > 0) $h .= '<button type="button" data-fx="reply" data-name="' . self::e($a['name']) . '">Ответить</button>';
        if ($c['can_delete']) $h .= '<button type="button" data-fx="cdel" class="fx-cmt__del">Удалить</button>';
        $h .= '</div>';
        if ($c['kids']) {
            $h .= '<div class="fx-cmt__kids"><span class="fx-cmt__thread" data-fx="collapse" title="Свернуть ветку"></span>';
            foreach ($c['kids'] as $k) $h .= self::comment($k, $viewer);
            $h .= '</div><button type="button" class="fx-cmt__expand" data-fx="collapse">Показать ответы (' . count($c['kids']) . ')</button>';
        }
        return $h . '</div></div>';
    }

    public static function replyBox(int $viewer, int $postId): string
    {
        if ($viewer <= 0) return '<div class="fx-note"><a href="' . Fx::urlLogin() . '">Войдите</a>, чтобы ответить</div>';
        return '<form class="fx-reply" data-fx-form="comment" data-post="' . $postId . '" autocomplete="off">'
            . '<div class="fx-reply__to hidden"><span></span><button type="button" data-fx="cancel-reply">' . self::i('close', 'ic--sm') . '</button></div>'
            . '<div class="fx-reply__row"><input type="text" name="body" maxlength="2000" placeholder="Написать ответ…" required>'
            . '<button class="fx-send" aria-label="Отправить">' . self::i('send', 'ic--sm') . '</button></div></form>';
    }

    /**
     * Содержимое оверлея: полная статья (если есть) + сам пост без обрезки + комментарии.
     */
    public static function overlay(array $p, string $articleHtml, array $tree, int $viewer, string $sort = 'top'): string
    {
        $h = '';
        if ($p['has_article']) {
            $a = $p['author'];
            $h .= '<div class="fx-reader"><h1>' . self::inline($p['title']) . '</h1></div>';
            $h .= '<div class="fx-reader__meta"><span>' . self::e($a['name']) . '</span><span>' . self::e($p['time']) . '</span><span>' . self::i('eye', 'ic--sm') . ' ' . Fx::n($p['stats']['views']) . '</span></div>';
            $h .= '<div class="fx-reader fx-reader--art">' . $articleHtml . '</div>';
            $h .= '<div class="fx-post fx-post--bare" data-id="' . $p['id'] . '" data-can="' . self::e(json_encode($p['can'])) . '">'
                . '<div class="fx-actions">' . self::reactions($p['stats']) . '<div class="fx-actions__end"><button type="button" class="fx-act" data-fx="share">' . self::i('share') . '</button></div></div></div>';
        } else {
            $h .= self::post(array_merge($p, ['has_article' => false]), ['hide_origin' => false]);
        }
        $h .= '<section class="fx-cmts" id="fx-cmts" data-post="' . $p['id'] . '">' . self::comments($tree, $p, $viewer, true, $sort) . '</section>';
        return $h;
    }

    /* ------------------------------------------------------------------ ПУСТЫЕ СОСТОЯНИЯ */

    public static function empty(string $icon, string $title, string $text = '', string $action = ''): string
    {
        return '<div class="fx-empty">' . self::i($icon) . '<b>' . self::e($title) . '</b>' . ($text !== '' ? '<span>' . $text . '</span>' : '') . $action . '</div>';
    }

    /** Кнопка «ещё» под лентой. */
    public static function more(?string $next): string
    {
        return $next === null ? '' : '<button type="button" class="fx-more" data-fx="more" data-next="' . self::e($next) . '">Показать ещё</button>';
    }
}
