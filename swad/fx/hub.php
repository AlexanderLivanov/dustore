<?php
declare(strict_types=1);

/**
 * swad/fx/hub.php — всё, что нужно хабам игры / студии / игрока, кроме самих постов:
 * DustHunt, модераторы игры, статистика, монетизация студии, упаковка кружков.
 */

require_once __DIR__ . '/feed.php';

/* ======================================================================
   DUSTHUNT — задание от студии на конкретной игре
   ====================================================================== */
final class FxHunt
{
    /** live / soon / ended — по датам, а status='ended' закрывает руками. */
    private static function state(array $h): string
    {
        if ($h['status'] === 'ended') return 'ended';
        $now = time();
        if (strtotime($h['starts_at']) > $now) return 'soon';
        if (strtotime($h['ends_at']) < $now) return 'ended';
        return 'live';
    }

    public static function shape(array $h, int $viewer): array
    {
        $pdo = Fx::pdo();
        $c = $pdo->prepare("SELECT COUNT(*) FROM fx_hunt_players WHERE hunt_id = ?");
        $c->execute([$h['id']]);
        $joined = false;
        if ($viewer > 0) {
            $j = $pdo->prepare("SELECT 1 FROM fx_hunt_players WHERE hunt_id = ? AND user_id = ?");
            $j->execute([$h['id'], $viewer]);
            $joined = (bool)$j->fetchColumn();
        }
        $state = self::state($h);
        $left = max(0, strtotime($h['ends_at']) - time());
        return [
            'id' => (int)$h['id'], 'game_id' => (int)$h['game_id'], 'title' => (string)$h['title'],
            'rules' => (string)($h['rules'] ?? ''), 'prize' => (string)($h['prize'] ?? ''),
            'starts_at' => $h['starts_at'], 'ends_at' => $h['ends_at'], 'max' => $h['max_players'] ? (int)$h['max_players'] : null,
            'state' => $state, 'players' => (int)$c->fetchColumn(), 'joined' => $joined,
            'left_days' => (int)floor($left / 86400), 'left_hours' => (int)floor(($left % 86400) / 3600),
        ];
    }

    /** Актуальный хант игры: идущий, иначе ближайший будущий, иначе null. */
    public static function current(int $gameId, int $viewer): ?array
    {
        $st = Fx::pdo()->prepare(
            "SELECT * FROM fx_hunts WHERE game_id = ? AND status <> 'draft' AND status <> 'ended' AND ends_at >= ?
             ORDER BY (starts_at <= ?) DESC, starts_at ASC LIMIT 1"
        );
        $now = Fx::now();
        $st->execute([$gameId, $now, $now]);
        $h = $st->fetch();
        return $h ? self::shape($h, $viewer) : null;
    }

    /** Для вкладки Dustore.Hunt в /fid: все идущие задания + мои. */
    public static function live(int $viewer, int $limit = 30): array
    {
        $st = Fx::pdo()->prepare(
            "SELECT * FROM fx_hunts WHERE status = 'live' AND starts_at <= ? AND ends_at >= ? ORDER BY ends_at ASC LIMIT " . (int)$limit
        );
        $now = Fx::now();
        $st->execute([$now, $now]);
        $out = [];
        $rows = $st->fetchAll();
        FxPeople::prime([], [], array_map(static fn($r) => (int)$r['game_id'], $rows));
        foreach ($rows as $h) {
            $s = self::shape($h, $viewer);
            $s['game'] = FxPeople::game((int)$h['game_id']);
            $out[] = $s;
        }
        return $out;
    }

    public static function join(int $uid, int $huntId, bool $leave = false): array
    {
        if ($uid <= 0) return ['ok' => false, 'error' => 'auth'];
        $pdo = Fx::pdo();
        $st = $pdo->prepare("SELECT * FROM fx_hunts WHERE id = ?");
        $st->execute([$huntId]);
        $h = $st->fetch();
        if (!$h) return ['ok' => false, 'error' => 'not_found'];
        if ($leave) {
            $pdo->prepare("DELETE FROM fx_hunt_players WHERE hunt_id = ? AND user_id = ?")->execute([$huntId, $uid]);
        } else {
            if (self::state($h) !== 'live') return ['ok' => false, 'error' => 'not_live'];
            if ($h['max_players']) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM fx_hunt_players WHERE hunt_id = ?");
                $c->execute([$huntId]);
                if ((int)$c->fetchColumn() >= (int)$h['max_players']) return ['ok' => false, 'error' => 'full'];
            }
            $pdo->prepare("INSERT IGNORE INTO fx_hunt_players (hunt_id, user_id, joined_at) VALUES (?,?,?)")->execute([$huntId, $uid, Fx::now()]);
        }
        return ['ok' => true, 'hunt' => self::shape($h, $uid)];
    }

    public static function players(int $huntId, int $limit = 24): array
    {
        $st = Fx::pdo()->prepare("SELECT user_id FROM fx_hunt_players WHERE hunt_id = ? ORDER BY joined_at ASC LIMIT " . (int)$limit);
        $st->execute([$huntId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        FxPeople::prime($ids);
        return array_values(array_filter(array_map(static fn($id) => FxPeople::user($id), $ids)));
    }

    /* --- консоль --- */

    public static function listForGame(int $gameId): array
    {
        $st = Fx::pdo()->prepare("SELECT * FROM fx_hunts WHERE game_id = ? ORDER BY starts_at DESC LIMIT 50");
        $st->execute([$gameId]);
        return array_map(static fn($h) => self::shape($h, 0), $st->fetchAll());
    }

    public static function save(int $uid, int $gameId, array $in): array
    {
        $role = FxAuth::gameRole($gameId, $uid);
        if (!in_array($role, ['admin', 'owner'], true)) return ['ok' => false, 'error' => 'forbidden'];
        $title = Fx::text($in['title'] ?? '', 120);
        $rules = Fx::text($in['rules'] ?? '', 2000);
        $prize = Fx::text($in['prize'] ?? '', 200);
        $from = strtotime((string)($in['starts_at'] ?? ''));
        $to = strtotime((string)($in['ends_at'] ?? ''));
        if ($title === '') return ['ok' => false, 'error' => 'title_required'];
        if (!$from || !$to || $to <= $from) return ['ok' => false, 'error' => 'bad_dates'];
        $max = (int)($in['max_players'] ?? 0) ?: null;
        $id = (int)($in['id'] ?? 0);
        $pdo = Fx::pdo();

        if ($id) {
            $own = $pdo->prepare("SELECT 1 FROM fx_hunts WHERE id = ? AND game_id = ?");
            $own->execute([$id, $gameId]);
            if (!$own->fetchColumn()) return ['ok' => false, 'error' => 'not_found'];
            $pdo->prepare("UPDATE fx_hunts SET title=?, rules=?, prize=?, starts_at=?, ends_at=?, max_players=?, status='live' WHERE id=?")
                ->execute([$title, $rules ?: null, $prize ?: null, date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $to), $max, $id]);
        } else {
            $pdo->prepare("INSERT INTO fx_hunts (game_id, title, rules, prize, starts_at, ends_at, max_players, status, created_by, created_at) VALUES (?,?,?,?,?,?,?,'live',?,?)")
                ->execute([$gameId, $title, $rules ?: null, $prize ?: null, date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $to), $max, $uid, Fx::now()]);
            $id = (int)$pdo->lastInsertId();
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function end(int $uid, int $gameId, int $huntId): array
    {
        $role = FxAuth::gameRole($gameId, $uid);
        if (!in_array($role, ['admin', 'owner'], true)) return ['ok' => false, 'error' => 'forbidden'];
        Fx::pdo()->prepare("UPDATE fx_hunts SET status = 'ended' WHERE id = ? AND game_id = ?")->execute([$huntId, $gameId]);
        return ['ok' => true];
    }
}

/* ======================================================================
   МОДЕРАТОРЫ ИГРЫ
   ====================================================================== */
final class FxMods
{
    public static function list(int $gameId): array
    {
        $st = Fx::pdo()->prepare("SELECT user_id, created_at FROM fx_game_mods WHERE game_id = ? ORDER BY created_at ASC");
        $st->execute([$gameId]);
        $rows = $st->fetchAll();
        FxPeople::prime(array_map(static fn($r) => (int)$r['user_id'], $rows));
        $out = [];
        foreach ($rows as $r) {
            $u = FxPeople::user((int)$r['user_id']);
            if ($u) $out[] = $u + ['since' => $r['created_at']];
        }
        return $out;
    }

    /** Кого назначаем: по username (без @) или числовому id. */
    public static function add(int $uid, int $gameId, string $who): array
    {
        $role = FxAuth::gameRole($gameId, $uid);
        if (!in_array($role, ['admin', 'owner'], true)) return ['ok' => false, 'error' => 'forbidden'];
        $who = ltrim(trim($who), '@');
        if ($who === '') return ['ok' => false, 'error' => 'empty'];
        $pdo = Fx::pdo();
        $st = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id = ? LIMIT 1");
        $st->execute([$who, ctype_digit($who) ? (int)$who : 0]);
        $id = (int)$st->fetchColumn();
        if (!$id) return ['ok' => false, 'error' => 'no_user'];
        $pdo->prepare("INSERT IGNORE INTO fx_game_mods (game_id, user_id, granted_by, created_at) VALUES (?,?,?,?)")
            ->execute([$gameId, $id, $uid, Fx::now()]);
        Fx::notify($id, 'Вас назначили модератором игры', 'Теперь вы следите за стеной и обсуждениями игры', Fx::urlGame($gameId));
        return ['ok' => true, 'user_id' => $id];
    }

    public static function remove(int $uid, int $gameId, int $userId): array
    {
        $role = FxAuth::gameRole($gameId, $uid);
        if (!in_array($role, ['admin', 'owner'], true)) return ['ok' => false, 'error' => 'forbidden'];
        Fx::pdo()->prepare("DELETE FROM fx_game_mods WHERE game_id = ? AND user_id = ?")->execute([$gameId, $userId]);
        return ['ok' => true];
    }
}

/* ======================================================================
   МОНЕТИЗАЦИЯ И ПОДПИСИ СТУДИИ
   ====================================================================== */
final class FxMonet
{
    public static function get(int $studioId): array
    {
        $st = Fx::pdo()->prepare("SELECT * FROM fx_studio_monet WHERE studio_id = ?");
        $st->execute([$studioId]);
        $r = $st->fetch();
        if ($r) return $r + ['_saved' => true];
        $s = Fx::pdo()->prepare("SELECT donate_link FROM studios WHERE id = ?");
        $s->execute([$studioId]);
        $legacy = (string)$s->fetchColumn();      // donate_link из старой карточки студии — не теряем
        return ['studio_id' => $studioId, 'donate_on' => $legacy !== '' ? 1 : 0, 'donate_url' => $legacy, 'donate_note' => '',
                'room_on' => 0, 'room_title' => '', 'room_desc' => '', 'room_price' => null, '_saved' => false];
    }

    public static function save(int $studioId, array $in): void
    {
        $url = trim((string)($in['donate_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) $url = '';
        Fx::pdo()->prepare(
            "INSERT INTO fx_studio_monet (studio_id, donate_on, donate_url, donate_note, room_on, room_title, room_desc, room_price, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE donate_on = VALUES(donate_on), donate_url = VALUES(donate_url), donate_note = VALUES(donate_note),
               room_on = VALUES(room_on), room_title = VALUES(room_title), room_desc = VALUES(room_desc), room_price = VALUES(room_price), updated_at = VALUES(updated_at)"
        )->execute([
            $studioId, !empty($in['donate_on']) ? 1 : 0, $url ?: null, Fx::text($in['donate_note'] ?? '', 160) ?: null,
            !empty($in['room_on']) ? 1 : 0, Fx::text($in['room_title'] ?? '', 80) ?: null, Fx::text($in['room_desc'] ?? '', 400) ?: null,
            ($in['room_price'] ?? '') !== '' ? max(0, (int)$in['room_price']) : null, Fx::now(),
        ]);
    }

    public static function links(int $studioId): array
    {
        $st = Fx::pdo()->prepare("SELECT id, label, url, kind FROM fx_studio_links WHERE studio_id = ? ORDER BY sort, id");
        $st->execute([$studioId]);
        return $st->fetchAll();
    }

    /** Полная замена набора ссылок (до 8). */
    public static function saveLinks(int $studioId, array $rows): void
    {
        $pdo = Fx::pdo();
        $pdo->prepare("DELETE FROM fx_studio_links WHERE studio_id = ?")->execute([$studioId]);
        $ins = $pdo->prepare("INSERT INTO fx_studio_links (studio_id, label, url, kind, sort) VALUES (?,?,?,?,?)");
        $i = 0;
        foreach ($rows as $r) {
            $label = Fx::text($r['label'] ?? '', 48);
            $url = trim((string)($r['url'] ?? ''));
            if ($label === '' || $url === '') continue;
            if (!preg_match('#^(https?://|mailto:)#i', $url)) $url = (str_contains($url, '@') && !str_contains($url, '/')) ? 'mailto:' . $url : 'https://' . $url;
            if (!preg_match('#^(https?://[^\s]+|mailto:[^\s]+)$#i', $url)) continue;
            $kind = 'link';
            if (preg_match('#t\.me/|telegram#i', $url)) $kind = 'tg';
            elseif (str_starts_with($url, 'mailto:')) $kind = 'mail';
            elseif (preg_match('#discord#i', $url)) $kind = 'discord';
            elseif (preg_match('#vk\.com#i', $url)) $kind = 'vk';
            $ins->execute([$studioId, $label, mb_substr($url, 0, 255), $kind, $i++]);
            if ($i >= 8) break;
        }
    }

    public static function titles(int $studioId): array
    {
        $st = Fx::pdo()->prepare("SELECT user_id, title FROM fx_studio_titles WHERE studio_id = ?");
        $st->execute([$studioId]);
        $o = [];
        foreach ($st->fetchAll() as $r) $o[(int)$r['user_id']] = (string)$r['title'];
        return $o;
    }

    public static function setTitle(int $studioId, int $userId, string $title): void
    {
        $title = Fx::text($title, 48);
        if ($title === '') {
            Fx::pdo()->prepare("DELETE FROM fx_studio_titles WHERE studio_id = ? AND user_id = ?")->execute([$studioId, $userId]);
            return;
        }
        Fx::pdo()->prepare(
            "INSERT INTO fx_studio_titles (studio_id, user_id, title) VALUES (?,?,?) ON DUPLICATE KEY UPDATE title = VALUES(title)"
        )->execute([$studioId, $userId, $title]);
    }
}

/* ======================================================================
   СТАТИСТИКА
   ====================================================================== */
final class FxStats
{
    /** Просмотр страницы: раз в 30 минут на сессию и объект. */
    public static function bump(string $subject, int $id): void
    {
        if (Fx::quiet()) return;
        Fx::session();
        $k = "fxv_{$subject}_$id";
        if (!empty($_SESSION[$k]) && time() - (int)$_SESSION[$k] < 1800) return;
        $_SESSION[$k] = time();
        try {
            Fx::pdo()->prepare(
                "INSERT INTO fx_views_daily (subject, subject_id, day, n) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE n = n + 1"
            )->execute([$subject, $id, date('Y-m-d')]);
        } catch (Throwable $e) {
            error_log('[fx] views: ' . $e->getMessage());
        }
    }

    /** [YYYY-mm-dd => n] за $days последних дней, пропуски — нулями. */
    private static function fill(array $byDay, int $days): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', time() - $i * 86400);
            $out[$d] = (int)($byDay[$d] ?? 0);
        }
        return $out;
    }

    private static function series(string $sql, array $params, int $days): array
    {
        try {
            $st = Fx::pdo()->prepare($sql);
            $st->execute($params);
            $by = [];
            foreach ($st->fetchAll() as $r) $by[(string)$r['d']] = (int)$r['c'];
            return self::fill($by, $days);
        } catch (Throwable $e) {
            error_log('[fx] stats: ' . $e->getMessage());
            return self::fill([], $days);
        }
    }

    public static function viewsSeries(string $subject, int $id, int $days = 30): array
    {
        return self::series(
            "SELECT day AS d, n AS c FROM fx_views_daily WHERE subject = ? AND subject_id = ? AND day >= ?",
            [$subject, $id, date('Y-m-d', time() - ($days - 1) * 86400)], $days
        );
    }

    /**
     * Хаб игры: скачивания и игроки за 30 дней, владельцы, лента обновлений.
     * События берём из общей analytics_events (subject_type='game').
     */
    public static function game(int $gameId, int $days = 30): array
    {
        $pdo = Fx::pdo();
        $from = date('Y-m-d 00:00:00', time() - ($days - 1) * 86400);
        $dl = self::series("SELECT DATE(created_at) d, COUNT(*) c FROM analytics_events WHERE subject_type = 'game' AND subject_id = ? AND event_type = 'download' AND created_at >= ? GROUP BY d", [$gameId, $from], $days);
        $pl = self::series("SELECT DATE(created_at) d, COUNT(DISTINCT CONCAT_WS(':', user_id, anon_id)) c FROM analytics_events WHERE subject_type = 'game' AND subject_id = ? AND event_type = 'launch' AND created_at >= ? GROUP BY d", [$gameId, $from], $days);

        $own = $pdo->prepare("SELECT COUNT(DISTINCT player_id) FROM library WHERE game_id = ?");
        $own->execute([$gameId]);
        $new = $pdo->prepare("SELECT COUNT(DISTINCT player_id) FROM library WHERE game_id = ? AND date >= ?");
        $new->execute([$gameId, $from]);
        $uniq = $pdo->prepare("SELECT COUNT(DISTINCT CONCAT_WS(':', user_id, anon_id)) FROM analytics_events WHERE subject_type = 'game' AND subject_id = ? AND event_type = 'launch' AND created_at >= ?");
        $uniq->execute([$gameId, $from]);

        /* обновления: смены билда за полгода, по неделям */
        $upFrom = date('Y-m-d 00:00:00', time() - 182 * 86400);
        $ups = [];
        try {
            $st = $pdo->prepare("SELECT created_at FROM game_change_log WHERE game_id = ? AND field IN ('build','version') AND created_at >= ? ORDER BY created_at ASC");
            $st->execute([$gameId, $upFrom]);
            $ups = $st->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            error_log('[fx] stats/updates: ' . $e->getMessage());
        }
        $weeks = [];
        for ($i = 25; $i >= 0; $i--) $weeks[date('o-W', time() - $i * 7 * 86400)] = 0;
        foreach ($ups as $t) {
            $k = date('o-W', strtotime((string)$t));
            if (isset($weeks[$k])) $weeks[$k]++;
        }
        $last = $ups ? (string)end($ups) : null;

        return [
            'downloads' => $dl, 'players' => $pl,
            'downloads_total' => array_sum($dl), 'players_30' => (int)$uniq->fetchColumn(),
            'owners' => (int)$own->fetchColumn(), 'owners_new' => (int)$new->fetchColumn(),
            'updates' => array_values($weeks), 'updates_n' => count($ups), 'last_update' => $last,
            'views' => self::viewsSeries('game', $gameId, $days),
        ];
    }

    /** Хаб студии: команда, игроки по играм (для кружков), просмотры профиля. */
    public static function studio(int $studioId): array
    {
        $pdo = Fx::pdo();
        $g = $pdo->prepare(
            "SELECT g.id, g.name, g.icon_url, g.path_to_cover, COUNT(DISTINCT l.player_id) AS players
             FROM games g LEFT JOIN library l ON l.game_id = g.id
             WHERE g.developer = ? AND g.status = 'published' AND (g.hidden = 0 OR g.hidden IS NULL)
             GROUP BY g.id, g.name, g.icon_url, g.path_to_cover ORDER BY players DESC, g.id ASC LIMIT 40"
        );
        $g->execute([$studioId]);
        $games = $g->fetchAll();

        $views = self::viewsSeries('studio', $studioId, 30);
        return [
            'games' => $games,
            'players' => array_sum(array_map(static fn($r) => (int)$r['players'], $games)),
            'views' => $views, 'views_30' => array_sum($views),
            'followers' => FxFollow::count('studio', $studioId),
        ];
    }

    /**
     * Награды проектов студии: «Выбор эксперта» на джемах. Таблицы джемов могут отсутствовать на конкретной
     * установке — тогда просто пусто, страница не падает.
     * @return list<array{title:string,game_id:int,game:string,note:string}>
     */
    public static function gameAwards(int $studioId): array
    {
        try {
            $st = Fx::pdo()->prepare(
                "SELECT g.id AS game_id, g.name AS game, s.title AS jam, COALESCE(se.external_name, u.username, 'эксперт') AS expert
                 FROM sprint_expert_picks p
                 JOIN games g ON g.id = p.game_id AND g.developer = ? AND g.status = 'published'
                 JOIN sprints s ON s.id = g.sprint_id
                 LEFT JOIN sprint_experts se ON se.id = p.expert_id
                 LEFT JOIN users u ON u.id = se.user_id
                 ORDER BY g.id DESC LIMIT 12"
            );
            $st->execute([$studioId]);
            $out = [];
            foreach ($st->fetchAll() as $r) {
                $out[] = ['title' => 'Выбор эксперта', 'game_id' => (int)$r['game_id'], 'game' => (string)$r['game'],
                          'note' => 'Джем «' . $r['jam'] . '» · ' . $r['expert']];
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Участники студии: владелец + staff (два запроса — типы telegram_id разные). */
    public static function team(int $studioId): array
    {
        $pdo = Fx::pdo();
        $o = $pdo->prepare("SELECT owner_id FROM studios WHERE id = ?");
        $o->execute([$studioId]);
        $owner = (int)$o->fetchColumn();

        $st = $pdo->prepare("SELECT telegram_id, role FROM staff WHERE org_id = ? ORDER BY id ASC LIMIT 60");
        $st->execute([$studioId]);
        $staff = $st->fetchAll();

        $byTg = [];
        if ($staff) {
            $tgs = array_values(array_unique(array_map(static fn($r) => (string)$r['telegram_id'], $staff)));
            $ph = implode(',', array_fill(0, count($tgs), '?'));
            $u = $pdo->prepare("SELECT id, telegram_id FROM users WHERE telegram_id IN ($ph)");
            $u->execute($tgs);
            foreach ($u->fetchAll() as $r) $byTg[(string)$r['telegram_id']] = (int)$r['id'];
        }
        $titles = FxMonet::titles($studioId);

        $members = [];
        if ($owner) $members[$owner] = 'Владелец';
        foreach ($staff as $r) {
            $uid = $byTg[(string)$r['telegram_id']] ?? 0;
            if ($uid && !isset($members[$uid])) $members[$uid] = (string)($r['role'] ?: 'Участник');
        }
        FxPeople::prime(array_keys($members));
        $out = [];
        foreach ($members as $uid => $role) {
            $u = FxPeople::user($uid);
            if ($u) $out[] = $u + ['role' => $role, 'title' => $titles[$uid] ?? ''];
        }
        return $out;
    }

    /** Публичные ссылки обратной связи: свои + то, что уже лежит в карточке студии. */
    public static function feedback(array $studioRow): array
    {
        $out = [];
        foreach (FxMonet::links((int)$studioRow['id']) as $l) $out[] = ['label' => $l['label'], 'url' => $l['url'], 'kind' => $l['kind']];
        if (!$out) {
            if (!empty($studioRow['tg_link']))       $out[] = ['label' => 'Telegram', 'url' => $studioRow['tg_link'], 'kind' => 'tg'];
            if (!empty($studioRow['vk_link']))       $out[] = ['label' => 'VK', 'url' => $studioRow['vk_link'], 'kind' => 'vk'];
            if (!empty($studioRow['contact_email'])) $out[] = ['label' => 'Почта', 'url' => 'mailto:' . $studioRow['contact_email'], 'kind' => 'mail'];
            if (!empty($studioRow['website']))       $out[] = ['label' => 'Сайт', 'url' => (preg_match('#^https?://#i', $studioRow['website']) ? '' : 'https://') . $studioRow['website'], 'kind' => 'link'];
        }
        return $out;
    }
}

/* ======================================================================
   УПАКОВКА КРУЖКОВ: чем больше игроков — тем крупнее кружок, кружки касаются краями.
   Жадный алгоритм: следующий круг ставится вплотную к паре уже стоящих
   (касается обоих) в точку, ближайшую к центру масс. Для ≤ 40 кружков — мгновенно.
   ====================================================================== */
final class FxPack
{
    /**
     * @param  array<int,float> $weights id => вес (число игроков)
     * @return array{items:array<int,array{id:int,x:float,y:float,r:float}>,ratio:float}
     *         координаты в процентах от ширины контейнера; ratio = высота/ширина
     */
    public static function layout(array $weights): array
    {
        if (!$weights) return ['items' => [], 'ratio' => 0.6];
        arsort($weights);
        $max = max(1.0, (float)max($weights));
        $c = [];
        foreach ($weights as $id => $w) {
            $r = 0.34 + 0.66 * sqrt(max(0.0, (float)$w) / $max);     // минимальный кружок — треть максимального
            $c[] = ['id' => (int)$id, 'x' => 0.0, 'y' => 0.0, 'r' => $r];
        }

        $placed = [];
        foreach ($c as $i => $ci) {
            if ($i === 0) { $placed[] = $ci; continue; }
            if ($i === 1) { $ci['x'] = $placed[0]['r'] + $ci['r']; $placed[] = $ci; continue; }

            [$cx, $cy] = self::centroid($placed);
            $best = null;
            $bestD = INF;
            $n = count($placed);
            for ($a = 0; $a < $n; $a++) {
                for ($b = $a + 1; $b < $n; $b++) {
                    foreach (self::tangent($placed[$a], $placed[$b], $ci['r']) as [$x, $y]) {
                        if (!self::free($placed, $x, $y, $ci['r'])) continue;
                        $d = ($x - $cx) ** 2 + ($y - $cy) ** 2;
                        if ($d < $bestD) { $bestD = $d; $best = [$x, $y]; }
                    }
                }
            }
            if ($best === null) {        // на всякий случай: ставим справа от всей группы
                $best = [max(array_map(static fn($p) => $p['x'] + $p['r'], $placed)) + $ci['r'], $cy];
            }
            $ci['x'] = $best[0];
            $ci['y'] = $best[1];
            $placed[] = $ci;
        }

        $minX = min(array_map(static fn($p) => $p['x'] - $p['r'], $placed));
        $maxX = max(array_map(static fn($p) => $p['x'] + $p['r'], $placed));
        $minY = min(array_map(static fn($p) => $p['y'] - $p['r'], $placed));
        $maxY = max(array_map(static fn($p) => $p['y'] + $p['r'], $placed));
        $w = max(0.0001, $maxX - $minX);
        $h = max(0.0001, $maxY - $minY);
        $items = [];
        foreach ($placed as $p) {
            $items[] = [
                'id' => $p['id'],
                'x' => round(($p['x'] - $minX) / $w * 100, 3),
                'y' => round(($p['y'] - $minY) / $w * 100, 3),     // и по Y делим на ширину: так круги остаются круглыми
                'r' => round($p['r'] / $w * 100, 3),
            ];
        }
        return ['items' => $items, 'ratio' => round($h / $w, 4)];
    }

    private static function centroid(array $p): array
    {
        $sx = $sy = $sw = 0.0;
        foreach ($p as $c) { $m = $c['r'] * $c['r']; $sx += $c['x'] * $m; $sy += $c['y'] * $m; $sw += $m; }
        return [$sx / $sw, $sy / $sw];
    }

    private static function free(array $placed, float $x, float $y, float $r): bool
    {
        foreach ($placed as $p) {
            if (sqrt(($p['x'] - $x) ** 2 + ($p['y'] - $y) ** 2) < $p['r'] + $r - 1e-7) return false;
        }
        return true;
    }

    /** Точки, где круг радиуса r касается сразу a и b. */
    private static function tangent(array $a, array $b, float $r): array
    {
        $dx = $b['x'] - $a['x'];
        $dy = $b['y'] - $a['y'];
        $d = sqrt($dx * $dx + $dy * $dy);
        $ra = $a['r'] + $r;
        $rb = $b['r'] + $r;
        if ($d < 1e-9 || $d > $ra + $rb || $d < abs($ra - $rb)) return [];
        $l = ($ra * $ra - $rb * $rb + $d * $d) / (2 * $d);
        $h2 = $ra * $ra - $l * $l;
        if ($h2 < 0) return [];
        $h = sqrt($h2);
        $mx = $a['x'] + $l * $dx / $d;
        $my = $a['y'] + $l * $dy / $d;
        return [[$mx + $h * $dy / $d, $my - $h * $dx / $d], [$mx - $h * $dy / $d, $my + $h * $dx / $d]];
    }
}
