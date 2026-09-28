<?php
declare(strict_types=1);

/**
 * swad/fx/feed.php — выборки лент.
 *
 *   wall()     — стена игры / студии / игрока (+ то, что «подтягивается» из медиа)
 *   friends()  — /fid, вкладка «Друзья»: друзья, стены друзей, мои подписки
 *   general()  — /fid, вкладка «Лента»: только посты, написанные именно в медиа
 *                (популярное, новости Dustore) + рекомендации
 *
 * Главное правило: записи на стенах игроков в общую ленту НЕ попадают никогда.
 * Единственный вход в неё — fx_posts.in_media = 1, а для wall_type='user' флаг
 * принудительно 0 в FxPosts::create().
 */

require_once __DIR__ . '/posts.php';

final class FxFeed
{
    public const PAGE = 15;

    public static function friendIds(int $uid): array
    {
        if ($uid <= 0) return [];
        $st = Fx::pdo()->prepare(
            "SELECT player_id, friend_id FROM friends WHERE status = 'accepted' AND (player_id = ? OR friend_id = ?)"
        );
        $st->execute([$uid, $uid]);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[] = (int)$r['player_id'] === $uid ? (int)$r['friend_id'] : (int)$r['player_id'];
        return array_values(array_unique($out));
    }

    /** @return array{studio:int[],game:int[]} */
    public static function followed(int $uid): array
    {
        $out = ['studio' => [], 'game' => []];
        if ($uid <= 0) return $out;
        $st = Fx::pdo()->prepare("SELECT target_type, target_id FROM fx_follows WHERE user_id = ?");
        $st->execute([$uid]);
        foreach ($st->fetchAll() as $r) if (isset($out[$r['target_type']])) $out[$r['target_type']][] = (int)$r['target_id'];
        return $out;
    }

    public static function ownedGames(int $uid): array
    {
        if ($uid <= 0) return [];
        $st = Fx::pdo()->prepare("SELECT DISTINCT game_id FROM library WHERE player_id = ? AND game_id > 0");
        $st->execute([$uid]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /* ------------------------------------------------------------------
       Курсор: «время|id». Ключ сортировки — created_at, а для форума last_at (свежие ответы поднимают ветку).
       ------------------------------------------------------------------ */
    private static function parseCursor(?string $c): ?array
    {
        if (!$c || !preg_match('/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\|(\d+)$/', $c, $m)) return null;
        return [$m[1], (int)$m[2]];
    }

    private static function page(string $where, array $params, string $col, ?string $cursor, int $limit): array
    {
        $cur = self::parseCursor($cursor);
        if ($cur) {
            $where .= " AND (p.$col < ? OR (p.$col = ? AND p.id < ?))";
            array_push($params, $cur[0], $cur[0], $cur[1]);
        }
        $st = Fx::pdo()->prepare("SELECT p.* FROM fx_posts p WHERE $where ORDER BY p.$col DESC, p.id DESC LIMIT " . ($limit + 1));
        $st->execute($params);
        $rows = $st->fetchAll();
        $next = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $last = $rows[count($rows) - 1];
            $next = $last[$col] . '|' . $last['id'];
        }
        return [$rows, $next];
    }

    /* ------------------------------------------------------------------ СТЕНЫ */

    public static function wall(string $wt, int $wid, string $ch, int $viewer, ?string $cursor = null, int $limit = self::PAGE): array
    {
        $col = 'created_at';
        if ($wt === 'game' && $ch === 'forum') {
            $own = "p.wall_type = 'game' AND p.wall_id = ? AND p.channel = 'forum'";
            $ownP = [$wid];
            $col = 'last_at';
            $where = "p.status = 1 AND p.pinned = 0 AND ($own)";
            $params = $ownP;
        } elseif ($wt === 'game') {
            $own = "(p.wall_type = 'game' AND p.wall_id = ? AND p.channel = 'wall') OR (p.wall_type = 'media' AND p.game_id = ? AND p.in_media = 1)";
            $ownP = [$wid, $wid];
            $where = "p.status = 1 AND p.pinned = 0 AND ($own)";
            $params = $ownP;
        } elseif ($wt === 'studio') {
            $own = "(p.wall_type = 'studio' AND p.wall_id = ? AND p.channel = 'devlog') OR (p.wall_type = 'media' AND p.as_studio_id = ? AND p.kind <> 'news')";
            $ownP = [$wid, $wid];
            $where = "p.status = 1 AND p.pinned = 0 AND ($own)";
            $params = $ownP;
        } else {   // user
            $own = "(p.wall_type = 'user' AND p.wall_id = ?) OR (p.wall_type = 'media' AND p.author_id = ? AND p.as_studio_id IS NULL AND p.kind <> 'news')";
            $ownP = [$wid, $wid];
            $where = "p.status = 1 AND p.pinned = 0 AND ($own)";
            $params = $ownP;
        }

        [$rows, $next] = self::page($where, $params, $col, $cursor, $limit);

        $pinned = [];
        if (!$cursor) {
            $st = Fx::pdo()->prepare(
                "SELECT p.* FROM fx_posts p WHERE p.status = 1 AND p.pinned = 1 AND p.wall_type = ? AND p.wall_id = ? AND p.channel = ?
                 ORDER BY p.last_at DESC, p.id DESC LIMIT 3"
            );
            $st->execute([$wt, $wid, $wt === 'studio' ? 'devlog' : ($ch === 'forum' ? 'forum' : 'wall')]);
            $pinned = $st->fetchAll();
        }
        return ['posts' => FxPosts::hydrate(array_merge($pinned, $rows), $viewer), 'next' => $next];
    }

    /* ------------------------------------------------------------------ ДРУЗЬЯ */

    public static function friends(int $viewer, ?string $cursor = null, int $limit = self::PAGE): array
    {
        if ($viewer <= 0) return ['posts' => [], 'next' => null, 'empty' => 'guest'];
        $friends = self::friendIds($viewer);
        $fol = self::followed($viewer);

        $parts = [];
        $params = [];
        if ($friends) {
            [$in, $p] = Fx::in($friends);
            $parts[] = "p.author_id IN ($in)";                       $params = array_merge($params, $p);
            $parts[] = "(p.wall_type = 'user' AND p.wall_id IN ($in))"; $params = array_merge($params, $p);
        }
        if ($fol['studio']) {
            [$in, $p] = Fx::in($fol['studio']);
            $parts[] = "p.as_studio_id IN ($in)";                        $params = array_merge($params, $p);
            $parts[] = "(p.wall_type = 'studio' AND p.wall_id IN ($in))"; $params = array_merge($params, $p);
        }
        if ($fol['game']) {
            // у подписанной игры в ленту идёт только «официалка» — посты команды; болтовня игроков остаётся на стене игры
            [$in, $p] = Fx::in($fol['game']);
            $parts[] = "(p.wall_type = 'game' AND p.wall_id IN ($in) AND p.channel = 'wall' AND p.as_studio_id IS NOT NULL)";
            $params = array_merge($params, $p);
        }
        if (!$parts) return ['posts' => [], 'next' => null, 'empty' => 'nobody'];

        $where = "p.status = 1 AND p.author_id <> ? AND (" . implode(' OR ', $parts) . ")";
        array_unshift($params, $viewer);

        [$rows, $next] = self::page($where, $params, 'created_at', $cursor, $limit);
        $posts = FxPosts::hydrate($rows, $viewer);

        $fset = array_flip($friends);
        $sset = array_flip($fol['studio']);
        $gset = array_flip($fol['game']);
        foreach ($posts as &$p) {
            $p['reason'] = self::reasonFor($p, $fset, $sset, $gset, []);
        }
        unset($p);
        return ['posts' => $posts, 'next' => $next];
    }

    private static function reasonFor(array $p, array $friends, array $studios, array $games, array $owned): ?array
    {
        if ($p['kind'] === 'news') return ['key' => 'news', 'label' => 'Новости Dustore'];
        if (isset($friends[$p['author_id']]) || ($p['wall_type'] === 'user' && isset($friends[$p['wall_id']]))) {
            return ['key' => 'friend', 'label' => ($p['wall_type'] === 'user' && !isset($friends[$p['author_id']])) ? 'На стене друга' : 'Друг'];
        }
        $sid = $p['author']['type'] === 'studio' ? $p['author']['id'] : 0;
        if (($sid && isset($studios[$sid])) || ($p['wall_type'] === 'studio' && isset($studios[$p['wall_id']]))
            || ($p['wall_type'] === 'game' && isset($games[$p['wall_id']]))) {
            return ['key' => 'follow', 'label' => 'Вы подписаны'];
        }
        if (!empty($p['game']) && isset($owned[$p['game']['id']])) return ['key' => 'library', 'label' => 'Из вашей библиотеки'];
        return null;
    }

    /* ------------------------------------------------------------------ ОБЩАЯ ЛЕНТА */

    /**
     * Ранжирование считаем в PHP по окну кандидатов: без тяжёлого SQL и без отдельной
     * таблицы рейтингов. Результат детерминирован (джиттер завязан на зрителя и день),
     * поэтому пагинация по номеру страницы не «прыгает» при подгрузке.
     */
    public static function general(int $viewer, int $page = 0, int $limit = self::PAGE): array
    {
        $pdo = Fx::pdo();
        $recent = $pdo->query(
            "SELECT * FROM fx_posts WHERE in_media = 1 AND status = 1 AND created_at > '" . date('Y-m-d H:i:s', time() - 45 * 86400) . "'
             ORDER BY created_at DESC, id DESC LIMIT 250"
        )->fetchAll();
        $top = $pdo->query(
            "SELECT * FROM fx_posts WHERE in_media = 1 AND status = 1 AND created_at > '" . date('Y-m-d H:i:s', time() - 180 * 86400) . "'
             ORDER BY (n_up * 2 + n_cm * 3 + n_views / 50) DESC LIMIT 60"
        )->fetchAll();
        $pool = [];
        foreach (array_merge($recent, $top) as $r) $pool[(int)$r['id']] = $r;
        if (!$pool) return ['posts' => [], 'next' => null];

        $friends = array_flip(self::friendIds($viewer));
        $fol = self::followed($viewer);
        $sset = array_flip($fol['studio']);
        $gset = array_flip($fol['game']);
        $owned = array_flip(self::ownedGames($viewer));
        $seed = crc32($viewer . ':' . date('Y-m-d'));
        $now = time();

        $scored = [];
        foreach ($pool as $id => $r) {
            $ageH = max(0.5, ($now - strtotime($r['created_at'])) / 3600);
            $eng = 1 + $r['n_up'] * 1.0 + $r['n_cm'] * 2.2 + log(1 + $r['n_views']) * 0.8 - $r['n_down'] * 0.6;
            $eng = max(0.2, $eng);
            $s = $eng / pow($ageH + 2, 1.25);

            if ($r['kind'] === 'news') $s *= 2.6;
            if (isset($friends[(int)$r['author_id']])) $s *= 1.3;
            if (!empty($r['as_studio_id']) && isset($sset[(int)$r['as_studio_id']])) $s *= 1.4;
            if (!empty($r['game_id'])) {
                if (isset($gset[(int)$r['game_id']])) $s *= 1.4;
                if (isset($owned[(int)$r['game_id']])) $s *= 1.5;
            }
            if ((int)$r['author_id'] === $viewer) $s *= 0.7;
            $s *= 0.9 + 0.2 * ((crc32($seed . ':' . $id) % 1000) / 1000);
            $scored[$id] = $s;
        }
        arsort($scored);
        $order = array_keys($scored);

        /* «Открытие»: каждый шестой слот — вещь не из головы выдачи (старше 3 дней, но живая). */
        $disc = [];
        foreach ($order as $id) {
            $r = $pool[$id];
            if (strtotime($r['created_at']) < $now - 3 * 86400 && ($r['n_up'] + $r['n_cm'] * 2) >= 5) $disc[] = $id;
        }
        usort($disc, static fn($a, $b) => crc32($seed . ':d' . $a) <=> crc32($seed . ':d' . $b));
        $discSet = [];
        $final = [];
        $di = 0;
        $used = [];
        foreach ($order as $i => $id) {
            if (isset($used[$id])) continue;
            if (count($final) % 6 === 5 && isset($disc[$di])) {
                $pick = $disc[$di++];
                if (!isset($used[$pick])) { $final[] = $pick; $used[$pick] = 1; $discSet[$pick] = 1; }
            }
            if (!isset($used[$id])) { $final[] = $id; $used[$id] = 1; }
        }

        $slice = array_slice($final, $page * $limit, $limit);
        $rows = array_map(static fn($id) => $pool[$id], $slice);
        $posts = FxPosts::hydrate($rows, $viewer);
        foreach ($posts as &$p) {
            $p['reason'] = isset($discSet[$p['id']])
                ? ['key' => 'discover', 'label' => 'Рекомендуем']
                : (self::reasonFor($p, $friends, $sset, $gset, $owned)
                    ?? (($p['stats']['up'] >= 20 || $p['stats']['comments'] >= 8) ? ['key' => 'popular', 'label' => 'Популярное'] : null));
        }
        unset($p);
        return ['posts' => $posts, 'next' => (($page + 1) * $limit < count($final)) ? (string)($page + 1) : null];
    }

    /** Один пост по id — для страницы поста и уведомлений. */
    public static function one(int $id, int $viewer): ?array
    {
        $r = FxPosts::find($id);
        if (!$r || (int)$r['status'] !== 1) return null;
        $h = FxPosts::hydrate([$r], $viewer);
        return $h[0] ?? null;
    }
}
