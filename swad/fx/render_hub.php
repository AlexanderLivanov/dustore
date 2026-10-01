<?php
declare(strict_types=1);

/**
 * swad/fx/render_hub.php — блоки хабов: композер, DustHunt, шестерёнки настройки,
 * спарклайны, кружки проектов, награды. Как и render.php — серверный рендер, классы fx-*.
 */

require_once __DIR__ . '/render.php';

final class FxRenderHub
{
    private static function e($s): string { return Fx::e($s); }
    private static function i(string $n, string $cls = ''): string { return fx_icon($n, $cls); }

    /* ------------------------------------------------------------------
       КОМПОЗЕР
       $cfg: wall_type, wall_id, channel, placeholder, viewer:[id,name,img], as:[{id,name,img,type:'studio'|'game'},…] (кроме личного профиля; пусто = выбора нет),
             title:bool (тема форума), poll:bool, photo:bool, article:bool, crosspost:?string (подпись галочки),
             submit:string, guest_url:string
       ------------------------------------------------------------------ */
    public static function composer(array $cfg): string
    {
        $v = $cfg['viewer'] ?? null;
        if (!$v || empty($v['id'])) {
            return '<div class="fx-cc fx-cc--guest">' . self::i('user') . '<span><a href="' . Fx::urlLogin() . '">Войдите</a>, чтобы писать и отвечать</span></div>';
        }
        $as = [];
        foreach (($cfg['as'] ?? []) as $a) {
            $a = array_is_list($a) ? ['id' => $a[0], 'name' => $a[1], 'img' => $a[2] ?? '', 'type' => $a[3] ?? 'studio'] : $a;
            $as[] = ['id' => (int)$a['id'], 'name' => (string)$a['name'], 'type' => (string)($a['type'] ?? 'studio')];
        }
        $jsCfg = [
            'wall_type' => $cfg['wall_type'] ?? 'media', 'wall_id' => (int)($cfg['wall_id'] ?? 0), 'channel' => $cfg['channel'] ?? 'wall',
            'title' => !empty($cfg['title']), 'as' => $as,
        ];
        $h = '<form class="fx-cc" data-fx-form="post" data-cfg="' . self::e(json_encode($jsCfg, JSON_UNESCAPED_UNICODE)) . '" autocomplete="off">';
        $h .= '<div class="fx-cc__head">' . FxRender::avatar(['type' => 'user', 'name' => $v['name'], 'img' => $v['img'] ?? ''], 30, false);
        $fixed = $cfg['as_fixed'] ?? null;   // ['name'=>…, 'role'=>…] — стена, где писать можно только от имени студии (девлог)
        if ($fixed) {
            $h .= '<span class="fx-cc__hint">Публикация от имени</span> <b class="fx-cc__asname">' . self::e($fixed['name']) . '</b>'
                . (!empty($fixed['role']) ? '<span class="fx-cc__hint">· ' . self::e($fixed['role']) . '</span>' : '');
        } elseif (count($as) > 0) {
            $h .= '<button type="button" class="fx-cc__as" data-fx="as-pick"><span class="fx-cc__hint">Публикация от:</span> <b class="fx-cc__asname">' . self::e($v['name']) . '</b>' . self::i('chev-down', 'ic--sm') . '</button>';
        } else {
            $h .= '<span class="fx-cc__hint">' . self::e($cfg['hint'] ?? 'Напишите что-нибудь') . '</span>';
        }
        $h .= '</div>';
        if (!empty($cfg['title'])) $h .= '<input class="fx-cc__title" name="title" maxlength="200" placeholder="Тема обсуждения" autocomplete="off">';
        $h .= '<textarea class="fx-cc__field" name="body" rows="1" maxlength="5000" placeholder="' . self::e($cfg['placeholder'] ?? 'Что нового? Напишите @, чтобы упомянуть игру, студию или человека.') . '"></textarea>';
        $h .= '<div class="fx-cc__thumbs hidden" data-thumbs></div>';
        if (!empty($cfg['poll'])) {
            $h .= '<div class="fx-cc__poll hidden" data-pollb><div class="fx-cc__pollh"><b>Опрос</b><button type="button" data-fx="poll-off" aria-label="Убрать">' . self::i('close', 'ic--sm') . '</button></div>'
                . '<input maxlength="80" placeholder="Вариант 1"><input maxlength="80" placeholder="Вариант 2">'
                . '<button type="button" class="fx-cc__addopt" data-fx="poll-add">' . self::i('plus', 'ic--sm') . ' Ещё вариант</button>'
                . '<label class="fx-cc__days">Длится <select name="days"><option value="1">1 день</option><option value="3">3 дня</option><option value="7" selected>7 дней</option><option value="30">30 дней</option></select></label></div>';
        }
        $h .= '<div class="fx-cc__bar"><div class="fx-cc__tools">';
        if (($cfg['photo'] ?? true)) $h .= '<button type="button" data-fx="photo" title="Фото">' . self::i('image') . '</button><input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden data-file>';
        if (!empty($cfg['poll'])) $h .= '<button type="button" data-fx="poll-on" title="Опрос">' . self::i('poll') . '</button>';
        if (!empty($cfg['article'])) $h .= '<button type="button" data-fx="article" title="Статья">' . self::i('article') . '</button>';
        $h .= '</div>';
        if (!empty($cfg['crosspost'])) $h .= '<label class="fx-sw fx-cc__cross' . ($as ? ' hidden' : '') . '" data-cross><input type="checkbox" name="crosspost" value="1"><span></span>' . self::e($cfg['crosspost']) . '</label>';
        $h .= '<span class="fx-cc__count">0</span><button class="fx-cc__submit" disabled>' . self::e($cfg['submit'] ?? 'Опубликовать') . '</button></div></form>';
        return $h;
    }

    /* ------------------------------------------------------------------
       DUSTHUNT
       ------------------------------------------------------------------ */
    private static function range(array $h): string
    {
        return Fx::day($h['starts_at']) . ' — ' . Fx::day($h['ends_at']);
    }

    private static function stateBadge(array $h): string
    {
        return match ($h['state']) {
            'live' => '<span class="fx-hstate fx-hstate--live">идёт</span>',
            'soon' => '<span class="fx-hstate fx-hstate--soon">скоро</span>',
            default => '<span class="fx-hstate fx-hstate--end">завершён</span>',
        };
    }

    /** Маленькое окошко в баннере игры. Клик → модалка. */
    public static function huntWidget(?array $h, array $opts = []): string
    {
        if (!$h) {
            if (!empty($opts['assign_url'])) {
                return '<a class="fx-hunt fx-hunt--empty" href="' . self::e($opts['assign_url']) . '" target="_blank" rel="noopener">' . self::i('target') . '<span><b>DustHunt не назначен</b><small>Назначить задание игрокам</small></span></a>';
            }
            return '';
        }
        $left = $h['state'] === 'live'
            ? ($h['left_days'] > 0 ? 'осталось ' . $h['left_days'] . ' дн' : 'осталось ' . max(1, $h['left_hours']) . ' ч')
            : ($h['state'] === 'soon' ? 'старт ' . Fx::day($h['starts_at']) : 'завершён');
        return '<button type="button" class="fx-hunt" data-fx="hunt-open" data-id="' . $h['id'] . '">'
            . '<span class="fx-hunt__top">' . self::i('target', 'ic--sm') . '<em>Текущий DustHunt</em>' . self::stateBadge($h) . '</span>'
            . '<b class="fx-hunt__t">' . self::e($h['title']) . '</b>'
            . '<span class="fx-hunt__row"><span>' . self::i('clock', 'ic--sm') . ' ' . self::e(self::range($h)) . '</span><span>' . self::e($left) . '</span></span>'
            . '<span class="fx-hunt__row"><span>' . self::i('users', 'ic--sm') . ' <span class="fx-hunt__pn">' . Fx::n($h['players']) . '</span> '
            . Fx::plural($h['players'], 'участник', 'участника', 'участников') . '</span><span class="fx-hunt__more">Подробнее ' . self::i('arrow-rt', 'ic--sm') . '</span></span></button>';
    }

    /** Содержимое модального окна DustHunt. */
    public static function huntModal(int $huntId, int $viewer): string
    {
        $st = Fx::pdo()->prepare("SELECT * FROM fx_hunts WHERE id = ?");
        $st->execute([$huntId]);
        $row = $st->fetch();
        if (!$row) return '';
        $h = FxHunt::current((int)$row['game_id'], $viewer);
        if (!$h || $h['id'] !== $huntId) {          // не «текущий» (завершён/будущий) — собираем по id
            $h = FxHunt::shape($row, $viewer);
        }
        $g = FxPeople::game((int)$h['game_id']);
        $players = FxHunt::players($huntId, 18);

        $o = '<div class="fx-hm" data-id="' . $h['id'] . '">';
        $o .= '<div class="fx-hm__game">' . ($g ? '<a href="' . self::e($g['url']) . '">' . self::e($g['name']) . '</a>' : '') . self::stateBadge($h) . '</div>';
        $o .= '<h2 class="fx-hm__t">' . self::e($h['title']) . '</h2>';
        $o .= '<div class="fx-hm__facts">'
            . '<div><small>Даты проведения</small><b>' . self::e(Fx::when($h['starts_at']) . ' — ' . Fx::when($h['ends_at'])) . '</b></div>'
            . '<div><small>Участников</small><b>' . Fx::n($h['players']) . ($h['max'] ? ' из ' . Fx::n($h['max']) : '') . '</b></div>'
            . ($h['prize'] !== '' ? '<div class="fx-hm__prize"><small>Награда</small><b>' . self::i('star', 'ic--sm') . ' ' . self::e($h['prize']) . '</b></div>' : '')
            . '</div>';
        if ($h['max']) {
            $pct = min(100, (int)round($h['players'] / max(1, $h['max']) * 100));
            $o .= '<div class="fx-bar"><i style="width:' . $pct . '%"></i></div>';
        }
        $o .= '<h3 class="fx-hm__h">Условия участия</h3>';
        $o .= $h['rules'] !== '' ? '<div class="fx-text">' . FxRender::rich($h['rules']) . '</div>' : '<div class="fx-note">Студия не указала отдельных условий — достаточно зарегистрироваться и играть.</div>';
        if ($players) {
            $o .= '<h3 class="fx-hm__h">Уже участвуют</h3><div class="fx-hm__people">';
            foreach ($players as $u) $o .= '<span title="' . self::e($u['name']) . '">' . FxRender::avatar($u, 30) . '</span>';
            if ($h['players'] > count($players)) $o .= '<span class="fx-hm__more">+' . Fx::n($h['players'] - count($players)) . '</span>';
            $o .= '</div>';
        }
        $o .= '<div class="fx-hm__foot">';
        if ($viewer <= 0) $o .= '<a class="fx-btn" href="' . Fx::urlLogin(Fx::urlGame((int)$h['game_id'])) . '">Войти и участвовать</a>';
        elseif ($h['joined']) $o .= '<button type="button" class="fx-btn fx-btn--ghost" data-fx="hunt-leave" data-id="' . $h['id'] . '">' . self::i('check', 'ic--sm') . ' Вы участвуете · выйти</button>';
        elseif ($h['state'] === 'live') $o .= '<button type="button" class="fx-btn" data-fx="hunt-join" data-id="' . $h['id'] . '">Участвовать</button>';
        else $o .= '<button type="button" class="fx-btn" disabled>' . ($h['state'] === 'soon' ? 'Регистрация откроется ' . Fx::day($h['starts_at']) : 'Завершён') . '</button>';
        return $o . '</div></div>';
    }

    /* ------------------------------------------------------------------
       ШЕСТЕРЁНКА — переход в консоль разработчика в НОВОЙ вкладке на конкретную настройку.
       Один такой значок на блок, а не десять одинаковых кнопок у каждого поля.
       $tips — что именно там настраивается (для подсказки).
       ------------------------------------------------------------------ */
    public static function gear(string $href, string $label, string $cls = ''): string
    {
        return '<a class="fx-gear ' . self::e($cls) . '" href="' . self::e($href) . '" target="_blank" rel="noopener" title="' . self::e($label) . '" aria-label="' . self::e($label) . '">'
            . self::i('settings', 'ic--sm') . '<span>' . self::e($label) . '</span></a>';
    }

    /* ------------------------------------------------------------------
       ГРАФИКИ
       ------------------------------------------------------------------ */

    /** Линия + заливка. $vals — числа по порядку. */
    public static function spark(array $vals, string $cls = ''): string
    {
        $vals = array_values(array_map('intval', $vals));
        $n = count($vals);
        if ($n < 2) return '';
        $max = max(1, max($vals));
        $w = 220; $h = 54; $pad = 3;
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = [round($i / ($n - 1) * $w, 1), round($h - $pad - ($v / $max) * ($h - $pad * 2), 1)];
        }
        $line = 'M' . implode(' L', array_map(static fn($p) => $p[0] . ' ' . $p[1], $pts));
        $area = $line . ' L' . $w . ' ' . $h . ' L0 ' . $h . ' Z';
        return '<svg class="fx-spark ' . self::e($cls) . '" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
            . '<path class="fx-spark__a" d="' . $area . '"/><path class="fx-spark__l" d="' . $line . '"/></svg>';
    }

    /** Столбики: график обновлений по неделям. */
    public static function bars(array $vals, string $cls = ''): string
    {
        $vals = array_values(array_map('intval', $vals));
        $max = max(1, max($vals ?: [1]));
        $h = '<div class="fx-bars ' . self::e($cls) . '" aria-hidden="true">';
        foreach ($vals as $v) {
            $pct = $v === 0 ? 6 : max(18, (int)round($v / $max * 100));
            $h .= '<i class="' . ($v ? 'on' : '') . '" style="height:' . $pct . '%"></i>';
        }
        return $h . '</div>';
    }

    /** Кружки проектов студии: размер ~ число игроков, касаются друг друга. */
    public static function circles(array $games): string
    {
        if (!$games) return '';
        $weights = [];
        foreach ($games as $g) $weights[(int)$g['id']] = (int)$g['players'];
        $lay = FxPack::layout($weights);
        $byId = [];
        foreach ($games as $g) $byId[(int)$g['id']] = $g;
        $h = '<div class="fx-bubbles' . (count($games) === 1 ? ' fx-bubbles--solo' : '') . '" style="padding-bottom:' . round($lay['ratio'] * 100, 2) . '%">';
        foreach ($lay['items'] as $it) {
            $g = $byId[$it['id']];
            $img = $g['icon_url'] ?: ($g['path_to_cover'] ?? '');
            $bg = $img ? ' style="background-image:url(\'' . self::e($img) . '\')"' : '';
            $size = 'left:' . ($it['x'] - $it['r']) . '%;top:' . ($it['y'] - $it['r']) . '%;width:' . ($it['r'] * 2) . '%;padding-bottom:' . ($it['r'] * 2) . '%';
            $big = $it['r'] > 17;
            $h .= '<a class="fx-bubble" href="' . self::e(Fx::urlGame((int)$g['id'])) . '" style="' . $size . '" title="' . self::e($g['name']) . ' · ' . Fx::n($g['players']) . ' ' . Fx::plural((int)$g['players'], 'игрок', 'игрока', 'игроков') . '">'
                . '<span class="fx-bubble__in"' . $bg . '>' . ($img ? '' : self::e(mb_strtoupper(mb_substr((string)$g['name'], 0, 1)))) . '</span>'
                . ($big ? '<span class="fx-bubble__n">' . Fx::n($g['players']) . '</span>' : '') . '</a>';
        }
        return $h . '</div>';
    }

    /**
     * Награды: стопка перекрывающихся кружков (до 5), остальное — «+N». Клик по стопке раскрывает список.
     * $awards: [[name, description, icon_url], …]
     */
    public static function awardsStack(array $awards, int $max = 5): string
    {
        if (!$awards) return '';
        $shown = array_slice($awards, 0, $max);
        $h = '<button type="button" class="fx-awards" data-fx="awards" data-list="' . self::e(json_encode(array_map(static fn($a) => [
            'name' => $a['name'], 'desc' => $a['description'] ?? '', 'img' => $a['icon_url'] ?? '',
        ], $awards), JSON_UNESCAPED_UNICODE)) . '" aria-label="Награды студии">';
        foreach ($shown as $i => $a) {
            $img = !empty($a['icon_url']) ? ' style="background-image:url(\'' . self::e($a['icon_url']) . '\')"' : '';
            $h .= '<span class="fx-award" style="z-index:' . (10 - $i) . '" title="' . self::e($a['name']) . '"><span' . $img . '>' . (empty($a['icon_url']) ? self::i('award') : '') . '</span></span>';
        }
        if (count($awards) > $max) $h .= '<span class="fx-award fx-award--more" style="z-index:1"><span>+' . (count($awards) - $max) . '</span></span>';
        return $h . '</button>';
    }
}
