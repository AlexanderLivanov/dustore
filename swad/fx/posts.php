<?php
declare(strict_types=1);

/**
 * swad/fx/posts.php — посты, реакции, комментарии-ветки, опросы, подписки.
 * Один движок на все стены: media (/fid), user, game, studio.
 */

require_once __DIR__ . '/core.php';

/* ======================================================================
   САНИТАЙЗЕР ДЛЯ СТАТЕЙ
   Белый список тегов и атрибутов. Всё остальное разворачивается в текст или
   выкидывается вместе с содержимым (script/style/iframe…).
   ====================================================================== */
final class FxSanitize
{
    private const TAGS = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'blockquote' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'code' => [], 'pre' => [], 'hr' => [],
        'a' => ['href'], 'img' => ['src', 'alt'],
    ];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base', 'noscript', 'template'];

    public static function html(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="fx-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('fx-root');
        if (!$root) return '';
        self::clean($root);
        $out = '';
        foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
        return trim($out);
    }

    private static function clean(DOMNode $node): void
    {
        $kids = [];
        foreach ($node->childNodes as $c) $kids[] = $c;
        foreach ($kids as $c) {
            if ($c instanceof DOMComment) { $node->removeChild($c); continue; }
            if (!($c instanceof DOMElement)) continue;
            $tag = strtolower($c->tagName);
            if (in_array($tag, self::DROP, true)) { $node->removeChild($c); continue; }
            self::clean($c);
            if (!isset(self::TAGS[$tag])) {           // неизвестный тег — разворачиваем, содержимое остаётся
                while ($c->firstChild) $node->insertBefore($c->firstChild, $c);
                $node->removeChild($c);
                continue;
            }
            $allowed = self::TAGS[$tag];
            foreach (iterator_to_array($c->attributes) as $attr) {
                if (!in_array($attr->name, $allowed, true)) $c->removeAttribute($attr->name);
            }
            if ($tag === 'a') {
                $href = trim($c->getAttribute('href'));
                if (!preg_match('#^(https?://|/(?!/)|mailto:)#i', $href)) { $c->removeAttribute('href'); }
                else { $c->setAttribute('rel', 'noopener noreferrer nofollow ugc'); $c->setAttribute('target', '_blank'); }
            }
            if ($tag === 'img') {
                $src = trim($c->getAttribute('src'));
                if (!preg_match('#^(/media/fx/[\w/\-]+\.(jpe?g|png|webp|gif)|https://[^\s"\'<>]+)$#i', $src)) {
                    $node->removeChild($c);
                    continue;
                }
                $c->setAttribute('loading', 'lazy');
            }
        }
    }

    /** Текстовая выжимка для сниппета в ленте. */
    public static function excerpt(string $html, int $len = 260): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</h2>', '</h3>', '</li>'], ' ', $html)), ENT_QUOTES, 'UTF-8')) ?? '');
        return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len - 1)) . '…' : $t;
    }
}

/* ======================================================================
   ПОСТЫ
   ====================================================================== */
final class FxPosts
{
    public const WALLS    = ['media', 'user', 'game', 'studio'];
    public const BODY_MAX = 5000;

    /** @return array{ok:bool,id?:int,error?:string} */
    public static function create(int $uid, array $in): array
    {
        if ($uid <= 0) return ['ok' => false, 'error' => 'auth'];
        $pdo = Fx::pdo();

        $wt = (string)($in['wall_type'] ?? 'media');
        $wid = (int)($in['wall_id'] ?? 0);
        $ch = (string)($in['channel'] ?? 'wall');
        if (!in_array($wt, self::WALLS, true)) return ['ok' => false, 'error' => 'bad_wall'];

        $asStudio = (int)($in['as_studio_id'] ?? 0) ?: null;
        $crosspost = !empty($in['crosspost']);
        $inMedia = 0;
        $gameId = null;

        /* --- стена существует, и писать на неё можно --- */
        switch ($wt) {
            case 'media':
                $wid = 0; $ch = 'wall'; $inMedia = 1;
                if ($asStudio && !FxAuth::isStaff($asStudio, $uid) && !Fx::isAdmin($uid)) return ['ok' => false, 'error' => 'forbidden'];
                break;
            case 'user':
                $ch = 'wall'; $asStudio = null;
                if (!FxPeople::user($wid)) return ['ok' => false, 'error' => 'bad_wall'];
                break;
            case 'game':
                $g = FxPeople::game($wid);
                if (!$g) return ['ok' => false, 'error' => 'bad_wall'];
                if (!in_array($ch, ['wall', 'forum'], true)) $ch = 'wall';
                $gameId = $wid;
                if ($asStudio) {                                  // от имени студии — только команда игры
                    $role = FxAuth::gameRole($wid, $uid);
                    if (!in_array($role, ['admin', 'owner', 'staff'], true)) return ['ok' => false, 'error' => 'forbidden'];
                    $asStudio = $g['studio_id'];
                    if ($ch === 'wall') $inMedia = $crosspost ? 1 : 0;
                }
                break;
            case 'studio':
                $ch = 'devlog';
                if (!FxPeople::studio($wid)) return ['ok' => false, 'error' => 'bad_wall'];
                if (!FxAuth::isStaff($wid, $uid) && !Fx::isAdmin($uid)) return ['ok' => false, 'error' => 'forbidden'];
                $asStudio = $wid;
                $inMedia = $crosspost ? 1 : 0;
                break;
        }

        $kind = (string)($in['kind'] ?? 'post');
        if (!in_array($kind, ['post', 'article', 'news'], true)) $kind = 'post';
        if ($kind === 'news') {
            if (!Fx::isAdmin($uid) || $wt !== 'media') return ['ok' => false, 'error' => 'forbidden'];
            $asStudio = null;
        }
        if ($kind === 'article' && !in_array($wt, ['media', 'studio'], true)) $kind = 'post';

        /* --- содержимое --- */
        $title = Fx::text($in['title'] ?? '', 200);
        $body = Fx::text($in['body'] ?? '', self::BODY_MAX);
        $article = null;
        if ($kind === 'article') {
            $article = FxSanitize::html((string)($in['article'] ?? ''));
            if ($article === '' || $title === '') return ['ok' => false, 'error' => 'empty'];
            if (strlen($article) > 400000) return ['ok' => false, 'error' => 'too_long'];
            if ($body === '') $body = FxSanitize::excerpt($article, 320);
        }
        if ($ch === 'forum' && (mb_strlen($title) < 3)) return ['ok' => false, 'error' => 'title_required'];

        $media = self::cleanMedia($in['media'] ?? []);
        if ($media === null) return ['ok' => false, 'error' => 'bad_media'];
        $poll = self::cleanPoll($in['poll'] ?? null);
        if ($poll === null) return ['ok' => false, 'error' => 'bad_poll'];

        if ($body === '' && $title === '' && !$media && !$poll) return ['ok' => false, 'error' => 'empty'];

        /* --- игра: со стены игры или по первому @упоминанию --- */
        if (!$gameId && preg_match('/@\[game:(\d+)\|/u', $title . ' ' . $body, $m) && FxPeople::game((int)$m[1])) {
            $gameId = (int)$m[1];
        }

        /* --- антиспам: не больше 8 постов за 5 минут --- */
        $rl = $pdo->prepare("SELECT COUNT(*) FROM fx_posts WHERE author_id = ? AND created_at > ?");
        $rl->execute([$uid, date('Y-m-d H:i:s', time() - 300)]);
        if ((int)$rl->fetchColumn() >= 8) return ['ok' => false, 'error' => 'rate'];

        $role = $asStudio ? FxAuth::roleLabel($asStudio, $uid) : null;
        $tags = self::cleanTags($in['tags'] ?? '');
        $now = Fx::now();

        $pdo->prepare(
            "INSERT INTO fx_posts (wall_type, wall_id, channel, author_id, as_studio_id, role_label, kind, title, body, article,
                                   media, poll, tags, game_id, in_media, created_at, last_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $wt, $wid, $ch, $uid, $asStudio, $role, $kind, $title !== '' ? $title : null, $body !== '' ? $body : null, $article,
            $media ? json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $poll ? json_encode($poll, JSON_UNESCAPED_UNICODE) : null,
            $tags !== '' ? $tags : null, $gameId, $inMedia, $now, $now,
        ]);
        $id = (int)$pdo->lastInsertId();

        self::notifyNew($uid, $id, $wt, $wid, $ch, $title . ' ' . $body);
        return ['ok' => true, 'id' => $id];
    }

    private static function notifyNew(int $uid, int $id, string $wt, int $wid, string $ch, string $text): void
    {
        $me = FxPeople::user($uid)['name'] ?? 'Кто-то';
        $url = '/fid/post/' . $id;
        foreach (Fx::mentions($text) as [$type, $tid]) {
            if ($type === 'user') Fx::notify($tid, $me . ' упомянул(а) вас', mb_substr(preg_replace('/@\[\w+:\d+\|([^\]]+)\]/u', '$1', $text) ?? '', 0, 140), $url);
        }
        if ($wt === 'user' && $wid !== $uid) {
            Fx::notify($wid, $me . ' написал(а) на вашей стене', mb_substr(preg_replace('/@\[\w+:\d+\|([^\]]+)\]/u', '$1', $text) ?? '', 0, 140), $url);
        }
    }

    private static function cleanTags($t): string
    {
        $list = is_array($t) ? $t : explode(',', (string)$t);
        $out = [];
        foreach ($list as $x) {
            $x = mb_strtolower(trim(ltrim((string)$x, '#')));
            $x = preg_replace('/[^\p{L}\p{N}_\-]+/u', '', $x) ?? '';
            if ($x !== '' && mb_strlen($x) <= 24) $out[$x] = $x;
            if (count($out) >= 8) break;
        }
        return implode(',', $out);
    }

    /** null — невалидно, [] — пусто. */
    private static function cleanMedia($m): ?array
    {
        if (!is_array($m)) return [];
        $out = [];
        foreach (array_slice($m, 0, 10) as $x) {
            if (!is_array($x)) return null;
            $u = (string)($x['u'] ?? '');
            if (!preg_match('#^/media/fx/[\w/\-]+\.(jpe?g|png|webp|gif)$#i', $u)) return null;
            $out[] = ['t' => 'img', 'u' => $u, 'w' => max(0, min(10000, (int)($x['w'] ?? 0))), 'h' => max(0, min(10000, (int)($x['h'] ?? 0)))];
        }
        return $out;
    }

    private static function cleanPoll($p): ?array
    {
        if ($p === null || $p === '' || $p === []) return [];
        if (!is_array($p)) return null;
        $opts = [];
        foreach ((array)($p['opts'] ?? []) as $o) {
            $o = Fx::text($o, 80);
            if ($o !== '') $opts[] = $o;
        }
        if (count($opts) < 2 || count($opts) > 6) return null;
        $days = max(1, min(30, (int)($p['days'] ?? 7)));
        return ['opts' => $opts, 'ends' => date('Y-m-d H:i:s', time() + $days * 86400)];
    }

    /* ------------------------------------------------------------------ */

    public static function find(int $id): ?array
    {
        $st = Fx::pdo()->prepare("SELECT * FROM fx_posts WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function edit(int $uid, int $id, array $in): array
    {
        $p = self::find($id);
        if (!$p || (int)$p['status'] !== 1) return ['ok' => false, 'error' => 'not_found'];
        if ((int)$p['author_id'] !== $uid && !Fx::isAdmin($uid)) return ['ok' => false, 'error' => 'forbidden'];

        $title = array_key_exists('title', $in) ? Fx::text($in['title'], 200) : (string)$p['title'];
        $body = array_key_exists('body', $in) ? Fx::text($in['body'], self::BODY_MAX) : (string)$p['body'];
        $article = $p['article'];
        if ($p['kind'] === 'article' && array_key_exists('article', $in)) {
            $article = FxSanitize::html((string)$in['article']);
            if ($article === '') return ['ok' => false, 'error' => 'empty'];
        }
        if ($body === '' && $title === '' && empty($p['media']) && empty($p['poll'])) return ['ok' => false, 'error' => 'empty'];
        if ($p['channel'] === 'forum' && mb_strlen($title) < 3) return ['ok' => false, 'error' => 'title_required'];

        Fx::pdo()->prepare("UPDATE fx_posts SET title = ?, body = ?, article = ?, tags = ?, edited_at = ? WHERE id = ?")
            ->execute([$title !== '' ? $title : null, $body !== '' ? $body : null, $article,
                array_key_exists('tags', $in) ? (self::cleanTags($in['tags']) ?: null) : $p['tags'], Fx::now(), $id]);
        return ['ok' => true];
    }

    public static function canDelete(array $p, int $uid): bool
    {
        if ($uid <= 0) return false;
        if ((int)$p['author_id'] === $uid) return true;
        if (!empty($p['as_studio_id']) && FxAuth::isStudioOwner((int)$p['as_studio_id'], $uid)) return true;
        return FxAuth::canModerateWall((string)$p['wall_type'], (int)$p['wall_id'], $uid);
    }

    public static function delete(int $uid, int $id): array
    {
        $p = self::find($id);
        if (!$p || (int)$p['status'] !== 1) return ['ok' => false, 'error' => 'not_found'];
        if (!self::canDelete($p, $uid)) return ['ok' => false, 'error' => 'forbidden'];
        Fx::pdo()->prepare("UPDATE fx_posts SET status = 0, pinned = 0 WHERE id = ?")->execute([$id]);
        return ['ok' => true];
    }

    /** Закрепление: до 3 закреплённых на стену/канал. */
    public static function pin(int $uid, int $id, bool $on): array
    {
        $p = self::find($id);
        if (!$p || (int)$p['status'] !== 1) return ['ok' => false, 'error' => 'not_found'];
        if ($p['wall_type'] === 'media' || !FxAuth::canModerateWall((string)$p['wall_type'], (int)$p['wall_id'], $uid)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        $pdo = Fx::pdo();
        if ($on) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM fx_posts WHERE wall_type = ? AND wall_id = ? AND channel = ? AND pinned = 1 AND status = 1");
            $c->execute([$p['wall_type'], $p['wall_id'], $p['channel']]);
            if ((int)$c->fetchColumn() >= 3) return ['ok' => false, 'error' => 'pin_limit'];
        }
        $pdo->prepare("UPDATE fx_posts SET pinned = ? WHERE id = ?")->execute([$on ? 1 : 0, $id]);
        return ['ok' => true, 'pinned' => $on];
    }

    public static function lock(int $uid, int $id, bool $on): array
    {
        $p = self::find($id);
        if (!$p || (int)$p['status'] !== 1) return ['ok' => false, 'error' => 'not_found'];
        if ($p['wall_type'] === 'media' || !FxAuth::canModerateWall((string)$p['wall_type'], (int)$p['wall_id'], $uid)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        Fx::pdo()->prepare("UPDATE fx_posts SET locked = ? WHERE id = ?")->execute([$on ? 1 : 0, $id]);
        return ['ok' => true, 'locked' => $on];
    }

    /** Просмотры: раз за сессию на пост. */
    public static function view(array $ids): void
    {
        if (Fx::quiet()) return;
        Fx::session();
        $seen = $_SESSION['fx_seen'] ?? [];
        $fresh = [];
        foreach (array_slice(array_unique(array_map('intval', $ids)), 0, 40) as $id) {
            if ($id > 0 && empty($seen[$id])) { $fresh[] = $id; $seen[$id] = 1; }
        }
        if (!$fresh) return;
        if (count($seen) > 400) $seen = array_slice($seen, -200, null, true);
        $_SESSION['fx_seen'] = $seen;
        [$in, $p] = Fx::in($fresh);
        Fx::pdo()->prepare("UPDATE fx_posts SET n_views = n_views + 1 WHERE status = 1 AND id IN ($in)")->execute($p);
    }

    /* ------------------------------------------------------------------
       ГИДРАТАЦИЯ: строки БД → структуры для рендера (пакетно, без N+1)
       ------------------------------------------------------------------ */
    public static function hydrate(array $rows, int $viewer): array
    {
        if (!$rows) return [];
        $pdo = Fx::pdo();
        $ids = array_map(static fn($r) => (int)$r['id'], $rows);
        $users = array_map(static fn($r) => (int)$r['author_id'], $rows);
        $studios = array_filter(array_map(static fn($r) => (int)($r['as_studio_id'] ?? 0), $rows));
        foreach ($rows as $r) if ($r['wall_type'] === 'studio') $studios[] = (int)$r['wall_id'];
        foreach ($rows as $r) if ($r['wall_type'] === 'user') $users[] = (int)$r['wall_id'];
        $games = array_filter(array_map(static fn($r) => (int)($r['game_id'] ?? 0), $rows));
        foreach ($rows as $r) if ($r['wall_type'] === 'game') $games[] = (int)$r['wall_id'];
        FxPeople::prime($users, $studios, $games);

        [$in, $p] = Fx::in($ids);
        $mineRx = $minePoll = $counts = [];
        if ($viewer > 0) {
            $st = $pdo->prepare("SELECT post_id, kind FROM fx_reactions WHERE user_id = ? AND post_id IN ($in)");
            $st->execute(array_merge([$viewer], $p));
            foreach ($st->fetchAll() as $r) $mineRx[(int)$r['post_id']] = $r['kind'];
            $st = $pdo->prepare("SELECT post_id, opt FROM fx_poll_votes WHERE user_id = ? AND post_id IN ($in)");
            $st->execute(array_merge([$viewer], $p));
            foreach ($st->fetchAll() as $r) $minePoll[(int)$r['post_id']][] = (int)$r['opt'];
        }
        $pollIds = array_map(static fn($r) => (int)$r['id'], array_filter($rows, static fn($r) => !empty($r['poll'])));
        if ($pollIds) {
            [$pin, $pp] = Fx::in($pollIds);
            $st = $pdo->prepare("SELECT post_id, opt, COUNT(*) c FROM fx_poll_votes WHERE post_id IN ($pin) GROUP BY post_id, opt");
            $st->execute($pp);
            foreach ($st->fetchAll() as $r) $counts[(int)$r['post_id']][(int)$r['opt']] = (int)$r['c'];
        }
        $following = ['studio' => [], 'game' => []];
        if ($viewer > 0) {
            $st = $pdo->prepare("SELECT target_type, target_id FROM fx_follows WHERE user_id = ?");
            $st->execute([$viewer]);
            foreach ($st->fetchAll() as $r) $following[$r['target_type']][(int)$r['target_id']] = true;
        }

        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $isNews = $r['kind'] === 'news';
            $studio = !empty($r['as_studio_id']) ? FxPeople::studio((int)$r['as_studio_id']) : null;
            $user = FxPeople::user((int)$r['author_id']);
            $author = $isNews ? FxPeople::dustore() : ($studio ?: ($user ?: ['type' => 'user', 'id' => 0, 'name' => 'Удалённый пользователь', 'img' => '', 'url' => '#', 'verified' => null, 'frame' => null]));
            $byline = null;
            if ($studio && $user) $byline = ['name' => $user['name'], 'role' => (string)($r['role_label'] ?? ''), 'url' => $user['url']];
            if ($isNews) $byline = ['name' => 'Редакция', 'role' => '', 'url' => ''];

            $poll = null;
            if (!empty($r['poll'])) {
                $pj = json_decode((string)$r['poll'], true) ?: [];
                $c = $counts[$id] ?? [];
                $total = array_sum($c);
                $opts = [];
                foreach (($pj['opts'] ?? []) as $i => $label) {
                    $n = $c[$i] ?? 0;
                    $opts[] = ['i' => $i, 'label' => $label, 'n' => $n, 'pct' => $total ? (int)round($n / $total * 100) : 0];
                }
                $ended = !empty($pj['ends']) && strtotime((string)$pj['ends']) < time();
                $poll = ['opts' => $opts, 'total' => $total, 'ends' => $pj['ends'] ?? null, 'ended' => $ended, 'mine' => $minePoll[$id] ?? []];
            }

            $wallTitle = null;
            $game = !empty($r['game_id']) ? FxPeople::game((int)$r['game_id']) : null;
            $follow = null;
            if ($studio && !$isNews) $follow = ['type' => 'studio', 'id' => $studio['id'], 'on' => !empty($following['studio'][$studio['id']])];

            $out[] = [
                'id' => $id, 'wall_type' => $r['wall_type'], 'wall_id' => (int)$r['wall_id'], 'channel' => $r['channel'],
                'kind' => $r['kind'], 'title' => (string)($r['title'] ?? ''), 'body' => (string)($r['body'] ?? ''),
                'has_article' => $r['kind'] === 'article', 'media' => $r['media'] ? (json_decode((string)$r['media'], true) ?: []) : [],
                'poll' => $poll, 'tags' => $r['tags'] ? explode(',', (string)$r['tags']) : [],
                'game' => $game && $game['status'] === 'published' && !$game['hidden'] ? $game : null,
                'pinned' => (bool)$r['pinned'], 'locked' => (bool)$r['locked'],
                'stats' => ['up' => (int)$r['n_up'], 'down' => (int)$r['n_down'], 'comments' => (int)$r['n_cm'], 'views' => (int)$r['n_views'], 'mine' => $mineRx[$id] ?? null],
                'author' => $author, 'byline' => $byline, 'author_id' => (int)$r['author_id'],
                'origin' => self::origin($r),
                'time' => Fx::ago($r['created_at']), 'iso' => $r['created_at'], 'edited' => !empty($r['edited_at']),
                'follow' => $follow,
                'can' => [
                    'edit' => $viewer > 0 && ($viewer === (int)$r['author_id'] || Fx::isAdmin()),
                    'delete' => self::canDelete($r, $viewer),
                    'pin' => $viewer > 0 && $r['wall_type'] !== 'media' && FxAuth::canModerateWall((string)$r['wall_type'], (int)$r['wall_id'], $viewer),
                    'lock' => $viewer > 0 && $r['channel'] === 'forum' && FxAuth::canModerateWall((string)$r['wall_type'], (int)$r['wall_id'], $viewer),
                    'report' => $viewer > 0 && $viewer !== (int)$r['author_id'],
                ],
                'reason' => null,
            ];
        }
        return $out;
    }

    /** Откуда пост: метка + ссылка. Показывается в общих лентах; на «родной» стене прячется. */
    public static function origin(array $r): array
    {
        $wt = $r['wall_type'];
        $wid = (int)$r['wall_id'];
        if ($r['kind'] === 'news') return ['key' => 'news', 'label' => 'Новости Dustore', 'url' => Fx::urlMedia(), 'icon' => 'megaphone'];
        if ($wt === 'game') {
            $g = FxPeople::game($wid);
            $isForum = $r['channel'] === 'forum';
            return ['key' => $isForum ? 'forum' : 'game', 'icon' => $isForum ? 'chat' : 'gamepad',
                    'label' => ($isForum ? 'Обсуждения · ' : 'Стена игры · ') . ($g['name'] ?? 'игра'),
                    'url' => Fx::urlGame($wid) . ($isForum ? '?tab=forum' : '')];
        }
        if ($wt === 'studio') {
            $s = FxPeople::studio($wid);
            return ['key' => 'devlog', 'icon' => 'studio', 'label' => 'Девлог · ' . ($s['name'] ?? 'студия'), 'url' => $s['url'] ?? '#'];
        }
        if ($wt === 'user') {
            $u = FxPeople::user($wid);
            $own = (int)$r['author_id'] === $wid;
            return ['key' => 'wall', 'icon' => 'user', 'label' => $own ? 'Личная стена' : 'На стене · ' . ($u['name'] ?? 'игрока'), 'url' => $u['url'] ?? '#'];
        }
        return ['key' => 'media', 'icon' => 'flame', 'label' => 'Медиа', 'url' => Fx::urlMedia()];
    }
}

/* ======================================================================
   РЕАКЦИИ
   ====================================================================== */
final class FxReact
{
    public const UP   = ['heart' => 'Нравится', 'fire' => 'Огонь', 'clap' => 'Респект', 'laugh' => 'Смешно', 'mind' => 'Вынос мозга'];
    public const DOWN = ['thumbdown' => 'Не нравится', 'zzz' => 'Скучно', 'mask' => 'Кринж', 'meh' => 'Спорно'];

    /** Повторный клик по той же реакции снимает её. */
    public static function set(int $uid, int $postId, string $kind): array
    {
        if ($uid <= 0) return ['ok' => false, 'error' => 'auth'];
        $side = isset(self::UP[$kind]) ? 'u' : (isset(self::DOWN[$kind]) ? 'd' : null);
        if (!$side) return ['ok' => false, 'error' => 'bad_kind'];
        $p = FxPosts::find($postId);
        if (!$p || (int)$p['status'] !== 1) return ['ok' => false, 'error' => 'not_found'];

        $pdo = Fx::pdo();
        $cur = $pdo->prepare("SELECT kind FROM fx_reactions WHERE post_id = ? AND user_id = ?");
        $cur->execute([$postId, $uid]);
        $have = $cur->fetchColumn();

        if ($have === $kind) {
            $pdo->prepare("DELETE FROM fx_reactions WHERE post_id = ? AND user_id = ?")->execute([$postId, $uid]);
            $mine = null;
        } else {
            $pdo->prepare(
                "INSERT INTO fx_reactions (post_id, user_id, kind, side, created_at) VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE kind = VALUES(kind), side = VALUES(side), created_at = VALUES(created_at)"
            )->execute([$postId, $uid, $kind, $side, Fx::now()]);
            $mine = $kind;
        }
        $pdo->prepare(
            "UPDATE fx_posts SET
               n_up   = (SELECT COUNT(*) FROM fx_reactions WHERE post_id = ? AND side = 'u'),
               n_down = (SELECT COUNT(*) FROM fx_reactions WHERE post_id = ? AND side = 'd')
             WHERE id = ?"
        )->execute([$postId, $postId, $postId]);
        $c = $pdo->prepare("SELECT n_up, n_down FROM fx_posts WHERE id = ?");
        $c->execute([$postId]);
        $r = $c->fetch();

        if ($mine && $side === 'u' && $have === false) {
            $who = FxPeople::user($uid)['name'] ?? 'Кто-то';
            Fx::notify((int)$p['author_id'], $who . ' оценил(а) вашу запись', mb_substr((string)($p['title'] ?: $p['body']), 0, 100), '/fid/post/' . $postId);
        }
        return ['ok' => true, 'up' => (int)$r['n_up'], 'down' => (int)$r['n_down'], 'mine' => $mine];
    }
}

/* ======================================================================
   КОММЕНТАРИИ (ветки)
   ====================================================================== */
final class FxComments
{
    public const MAX_DEPTH = 5;

    public static function add(int $uid, int $postId, string $body, ?int $parentId): array
    {
        if ($uid <= 0) return ['ok' => false, 'error' => 'auth'];
        $body = Fx::text($body, 2000);
        if ($body === '') return ['ok' => false, 'error' => 'empty'];
        $p = FxPosts::find($postId);
        if (!$p || (int)$p['status'] !== 1) return ['ok' => false, 'error' => 'not_found'];
        if ($p['locked'] && !FxAuth::canModerateWall((string)$p['wall_type'], (int)$p['wall_id'], $uid)) return ['ok' => false, 'error' => 'locked'];

        $pdo = Fx::pdo();
        $rl = $pdo->prepare("SELECT COUNT(*) FROM fx_comments WHERE author_id = ? AND created_at > ?");
        $rl->execute([$uid, date('Y-m-d H:i:s', time() - 60)]);
        if ((int)$rl->fetchColumn() >= 12) return ['ok' => false, 'error' => 'rate'];

        $depth = 0;
        $parentAuthor = 0;
        if ($parentId) {
            $pc = $pdo->prepare("SELECT id, parent_id, depth, author_id FROM fx_comments WHERE id = ? AND post_id = ? AND status = 1");
            $pc->execute([$parentId, $postId]);
            $par = $pc->fetch();
            if (!$par) return ['ok' => false, 'error' => 'bad_parent'];
            $parentAuthor = (int)$par['author_id'];
            if ((int)$par['depth'] >= self::MAX_DEPTH) { $parentId = $par['parent_id'] ? (int)$par['parent_id'] : null; $depth = self::MAX_DEPTH; }
            else $depth = (int)$par['depth'] + 1;
        }

        $now = Fx::now();
        $pdo->prepare("INSERT INTO fx_comments (post_id, parent_id, depth, author_id, body, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$postId, $parentId, $depth, $uid, $body, $now]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE fx_posts SET n_cm = n_cm + 1, last_at = ? WHERE id = ?")->execute([$now, $postId]);

        $who = FxPeople::user($uid)['name'] ?? 'Кто-то';
        $url = '/fid/post/' . $postId;
        if ($parentAuthor) Fx::notify($parentAuthor, $who . ' ответил(а) вам', mb_substr($body, 0, 140), $url);
        if ((int)$p['author_id'] !== $parentAuthor) Fx::notify((int)$p['author_id'], $who . ' прокомментировал(а)', mb_substr($body, 0, 140), $url);
        foreach (Fx::mentions($body) as [$type, $tid]) {
            if ($type === 'user' && $tid !== $parentAuthor && $tid !== (int)$p['author_id']) Fx::notify($tid, $who . ' упомянул(а) вас', mb_substr($body, 0, 140), $url);
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function delete(int $uid, int $id): array
    {
        $st = Fx::pdo()->prepare("SELECT c.*, p.wall_type, p.wall_id FROM fx_comments c JOIN fx_posts p ON p.id = c.post_id WHERE c.id = ?");
        $st->execute([$id]);
        $c = $st->fetch();
        if (!$c || (int)$c['status'] !== 1) return ['ok' => false, 'error' => 'not_found'];
        if ((int)$c['author_id'] !== $uid && !FxAuth::canModerateWall((string)$c['wall_type'], (int)$c['wall_id'], $uid)) return ['ok' => false, 'error' => 'forbidden'];
        $pdo = Fx::pdo();
        $pdo->prepare("UPDATE fx_comments SET status = 0 WHERE id = ?")->execute([$id]);
        $pdo->prepare("UPDATE fx_posts SET n_cm = (SELECT COUNT(*) FROM fx_comments WHERE post_id = ? AND status = 1) WHERE id = ?")->execute([$c['post_id'], $c['post_id']]);
        return ['ok' => true];
    }

    public static function like(int $uid, int $id): array
    {
        if ($uid <= 0) return ['ok' => false, 'error' => 'auth'];
        $pdo = Fx::pdo();
        $ex = $pdo->prepare("SELECT 1 FROM fx_comments WHERE id = ? AND status = 1");
        $ex->execute([$id]);
        if (!$ex->fetchColumn()) return ['ok' => false, 'error' => 'not_found'];
        $has = $pdo->prepare("SELECT 1 FROM fx_comment_likes WHERE comment_id = ? AND user_id = ?");
        $has->execute([$id, $uid]);
        if ($has->fetchColumn()) {
            $pdo->prepare("DELETE FROM fx_comment_likes WHERE comment_id = ? AND user_id = ?")->execute([$id, $uid]);
            $on = false;
        } else {
            $pdo->prepare("INSERT INTO fx_comment_likes (comment_id, user_id, created_at) VALUES (?,?,?)")->execute([$id, $uid, Fx::now()]);
            $on = true;
        }
        $pdo->prepare("UPDATE fx_comments SET n_like = (SELECT COUNT(*) FROM fx_comment_likes WHERE comment_id = ?) WHERE id = ?")->execute([$id, $id]);
        $n = $pdo->prepare("SELECT n_like FROM fx_comments WHERE id = ?");
        $n->execute([$id]);
        return ['ok' => true, 'on' => $on, 'n' => (int)$n->fetchColumn()];
    }

    /** Дерево комментариев поста. sort: top | new. */
    public static function tree(array $post, int $viewer, string $sort = 'top'): array
    {
        $pdo = Fx::pdo();
        $st = $pdo->prepare("SELECT * FROM fx_comments WHERE post_id = ? AND status = 1 ORDER BY created_at ASC, id ASC LIMIT 600");
        $st->execute([$post['id']]);
        $rows = $st->fetchAll();
        if (!$rows) return [];

        FxPeople::prime(array_map(static fn($r) => (int)$r['author_id'], $rows));
        $likes = [];
        if ($viewer > 0) {
            [$in, $p] = Fx::in(array_map(static fn($r) => (int)$r['id'], $rows));
            $q = $pdo->prepare("SELECT comment_id FROM fx_comment_likes WHERE user_id = ? AND comment_id IN ($in)");
            $q->execute(array_merge([$viewer], $p));
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $cid) $likes[(int)$cid] = true;
        }
        $canMod = FxAuth::canModerateWall((string)$post['wall_type'], (int)$post['wall_id'], $viewer);

        $nodes = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $u = FxPeople::user((int)$r['author_id']) ?: ['type' => 'user', 'id' => 0, 'name' => 'Удалённый пользователь', 'img' => '', 'url' => '#', 'verified' => null, 'frame' => null];
            $nodes[$id] = [
                'id' => $id, 'parent' => $r['parent_id'] ? (int)$r['parent_id'] : null, 'depth' => (int)$r['depth'],
                'author' => $u, 'op' => (int)$r['author_id'] === (int)$post['author_id'],
                'body' => (string)$r['body'], 'likes' => (int)$r['n_like'], 'liked' => !empty($likes[$id]),
                'time' => Fx::ago($r['created_at']), 'iso' => $r['created_at'],
                'can_delete' => $viewer > 0 && ((int)$r['author_id'] === $viewer || $canMod),
                'kids' => [],
            ];
        }
        $roots = [];
        foreach ($nodes as $id => &$n) {
            if ($n['parent'] !== null && isset($nodes[$n['parent']])) $nodes[$n['parent']]['kids'][] = &$n;
            else $roots[] = &$n;
        }
        unset($n);
        if ($sort === 'top') usort($roots, static fn($a, $b) => [$b['likes'], $b['id']] <=> [$a['likes'], $a['id']]);
        else usort($roots, static fn($a, $b) => $b['id'] <=> $a['id']);
        return $roots;
    }
}

/* ======================================================================
   ОПРОСЫ
   ====================================================================== */
final class FxPoll
{
    public static function vote(int $uid, int $postId, int $opt): array
    {
        if ($uid <= 0) return ['ok' => false, 'error' => 'auth'];
        $p = FxPosts::find($postId);
        if (!$p || (int)$p['status'] !== 1 || empty($p['poll'])) return ['ok' => false, 'error' => 'not_found'];
        $pj = json_decode((string)$p['poll'], true) ?: [];
        if (!isset($pj['opts'][$opt])) return ['ok' => false, 'error' => 'bad_option'];
        if (!empty($pj['ends']) && strtotime((string)$pj['ends']) < time()) return ['ok' => false, 'error' => 'ended'];

        $pdo = Fx::pdo();
        $has = $pdo->prepare("SELECT 1 FROM fx_poll_votes WHERE post_id = ? AND user_id = ? LIMIT 1");
        $has->execute([$postId, $uid]);
        if ($has->fetchColumn()) return ['ok' => false, 'error' => 'voted'];
        $pdo->prepare("INSERT INTO fx_poll_votes (post_id, user_id, opt, created_at) VALUES (?,?,?,?)")->execute([$postId, $uid, $opt, Fx::now()]);

        $h = FxPosts::hydrate([$p], $uid);
        return ['ok' => true, 'poll' => $h[0]['poll']];
    }
}

/* ======================================================================
   ПОДПИСКИ (студии, игры)
   ====================================================================== */
final class FxFollow
{
    public static function toggle(int $uid, string $type, int $id): array
    {
        if ($uid <= 0) return ['ok' => false, 'error' => 'auth'];
        if ($type === 'studio' ? !FxPeople::studio($id) : ($type === 'game' ? !FxPeople::game($id) : true)) return ['ok' => false, 'error' => 'not_found'];
        $pdo = Fx::pdo();
        $has = $pdo->prepare("SELECT 1 FROM fx_follows WHERE user_id = ? AND target_type = ? AND target_id = ?");
        $has->execute([$uid, $type, $id]);
        if ($has->fetchColumn()) {
            $pdo->prepare("DELETE FROM fx_follows WHERE user_id = ? AND target_type = ? AND target_id = ?")->execute([$uid, $type, $id]);
            $on = false;
        } else {
            $pdo->prepare("INSERT INTO fx_follows (user_id, target_type, target_id, created_at) VALUES (?,?,?,?)")->execute([$uid, $type, $id, Fx::now()]);
            $on = true;
        }
        return ['ok' => true, 'on' => $on, 'count' => self::count($type, $id)];
    }

    public static function count(string $type, int $id): int
    {
        $st = Fx::pdo()->prepare("SELECT COUNT(*) FROM fx_follows WHERE target_type = ? AND target_id = ?");
        $st->execute([$type, $id]);
        return (int)$st->fetchColumn();
    }

    public static function is(int $uid, string $type, int $id): bool
    {
        if ($uid <= 0) return false;
        $st = Fx::pdo()->prepare("SELECT 1 FROM fx_follows WHERE user_id = ? AND target_type = ? AND target_id = ?");
        $st->execute([$uid, $type, $id]);
        return (bool)$st->fetchColumn();
    }
}
