<?php
declare(strict_types=1);

/**
 * swad/fx/core.php — база Fid Core: контекст, права, люди/игры/студии.
 *
 * Всё в статических классах с префиксом Fx*: в проекте полно страниц с глобальными
 * e(), nfmt(), plural(), icon() — новые функции с такими именами добавлять нельзя.
 */

require_once __DIR__ . '/schema.php';

final class Fx
{
    private static ?PDO $pdo = null;

    /** Страницы, у которых уже есть свой $pdo, могут передать его сюда. */
    public static function use(PDO $pdo): PDO
    {
        self::$pdo = $pdo;
        FxSchema::ensure($pdo);
        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            require_once __DIR__ . '/../config.php';
            self::use((new Database())->connect());
        }
        return self::$pdo;
    }

    public static function session(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    public static function uid(): int
    {
        self::session();
        return (int)($_SESSION['USERDATA']['id'] ?? 0);
    }

    public static function isAdmin(?int $uid = null): bool
    {
        self::session();
        if ($uid !== null && $uid !== self::uid()) {
            $st = self::pdo()->prepare("SELECT global_role FROM users WHERE id = ?");
            $st->execute([$uid]);
            return (int)$st->fetchColumn() === -1;
        }
        return (int)($_SESSION['USERDATA']['global_role'] ?? 0) === -1;
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 12400 → «12,4 тыс.» */
    public static function n($n): string
    {
        $n = (int)$n;
        if ($n < 1000) return (string)$n;
        if ($n < 1000000) return rtrim(rtrim(number_format($n / 1000, 1, ',', ' '), '0'), ',') . ' тыс.';
        return rtrim(rtrim(number_format($n / 1000000, 1, ',', ' '), '0'), ',') . ' млн';
    }

    /** «5 голосов / 2 голоса / 1 голос» */
    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $m = abs($n) % 100;
        $d = $m % 10;
        if ($m > 10 && $m < 20) return $many;
        if ($d === 1) return $one;
        if ($d >= 2 && $d <= 4) return $few;
        return $many;
    }

    private const MONTHS = ['янв.', 'фев.', 'мар.', 'апр.', 'мая', 'июн.', 'июл.', 'авг.', 'сен.', 'окт.', 'ноя.', 'дек.'];

    public static function ago(?string $dt): string
    {
        $t = $dt ? strtotime($dt) : false;
        if (!$t) return '';
        $d = time() - $t;
        if ($d < 45) return 'только что';
        if ($d < 3600) return max(1, (int)round($d / 60)) . ' мин назад';
        if ($d < 86400) return (int)floor($d / 3600) . ' ч назад';
        if ($d < 172800) return 'вчера';
        if ($d < 86400 * 7) return (int)floor($d / 86400) . ' дн назад';
        $m = self::MONTHS[(int)date('n', $t) - 1];
        return date('j', $t) . ' ' . $m . (date('Y', $t) !== date('Y') ? ' ' . date('Y', $t) : '');
    }

    /** «до 12 окт., 18:00» */
    public static function when(?string $dt): string
    {
        $t = $dt ? strtotime($dt) : false;
        if (!$t) return '';
        return date('j', $t) . ' ' . self::MONTHS[(int)date('n', $t) - 1] . ', ' . date('H:i', $t);
    }

    public static function day(?string $dt): string
    {
        $t = $dt ? strtotime($dt) : false;
        if (!$t) return '';
        return date('j', $t) . ' ' . self::MONTHS[(int)date('n', $t) - 1];
    }

    /** Плейсхолдеры для IN(): [sql, params] — пустой список превращается в IN (0). */
    public static function in(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return ['0', []];
        return [implode(',', array_fill(0, count($ids), '?')), $ids];
    }

    /** Текст пользователя: без управляющих символов, \r\n → \n, обрезка по длине. */
    public static function text($s, int $max): string
    {
        $s = (string)$s;
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        $s = trim($s);
        if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
        return $s;
    }

    /** Упоминания: @[type:id|Имя] → [[type, id], ...] */
    public static function mentions(string $text): array
    {
        $out = [];
        if (preg_match_all('/@\[(user|game|studio):(\d+)\|([^\]]+)\]/u', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) $out[$x[1] . ':' . $x[2]] = [$x[1], (int)$x[2]];
        }
        return array_values($out);
    }

    /* Мобильное PWA (/m) живёт в своей оболочке: ссылки внутри карточек должны вести на его страницы,
       а не выкидывать человека на десктопные адреса. Режим включает вьюха /m (Fx::mobile(true)),
       а для ответов api/fx.php — параметр m=1, который fx.js добавляет сам (window.FX.m). */
    private static bool $mobile = false;

    private static bool $quiet = false;

    /** Тихий режим: не считаем просмотры (браузер предзагружает страницы /m/* — speculationrules). */
    public static function quiet(?bool $on = null): bool
    {
        if ($on !== null) self::$quiet = $on;
        return self::$quiet;
    }

    public static function mobile(?bool $on = null): bool
    {
        if ($on !== null) self::$mobile = $on;
        return self::$mobile;
    }

    public static function urlUser(string $username): string   { return (self::$mobile ? '/m/player/' : '/player/') . rawurlencode($username); }
    public static function urlStudio(string $tiker): string    { return (self::$mobile ? '/m/dev/' : '/d/') . rawurlencode($tiker); }
    public static function urlGame(int $id): string            { return (self::$mobile ? '/m/game/' : '/g/') . $id; }
    /** Вход с возвратом: back — куда вернуть (по умолчанию текущая страница). */
    public static function urlLogin(string $back = ''): string
    {
        if ($back === '') $back = (string)($_SERVER['REQUEST_URI'] ?? '/');
        return (self::$mobile ? '/m/login?back=' : '/login?backUrl=') . rawurlencode($back);
    }
    public static function urlMedia(): string                  { return self::$mobile ? '/m/media' : '/fid'; }
    public static function urlPost(int $id): string            { return self::$mobile ? '/m/media?post=' . $id : '/fid/post/' . $id; }

    /** Уведомление в существующую таблицу notifications. Ошибки не всплывают: уведомление — не критично. */
    public static function notify(int $userId, string $title, string $body, string $url): void
    {
        if ($userId <= 0 || $userId === self::uid()) return;
        try {
            self::pdo()->prepare(
                "INSERT INTO notifications (user_id, title, body, url, created_at, is_read) VALUES (?,?,?,?,?,0)"
            )->execute([$userId, mb_substr($title, 0, 120), mb_substr($body, 0, 250), $url, self::now()]);
        } catch (Throwable $e) {
            error_log('[fx] notify: ' . $e->getMessage());
        }
    }
}

/* ======================================================================
   ЛЮДИ / СТУДИИ / ИГРЫ — пакетная загрузка, чтобы не бегать в БД на каждый пост
   ====================================================================== */
final class FxPeople
{
    private static array $users = [];
    private static array $studios = [];
    private static array $games = [];

    public static function prime(array $userIds = [], array $studioIds = [], array $gameIds = []): void
    {
        $pdo = Fx::pdo();
        $need = array_diff(array_map('intval', $userIds), array_keys(self::$users), [0]);
        if ($need) {
            [$in, $p] = Fx::in($need);
            $st = $pdo->prepare("SELECT id, username, first_name, last_name, profile_picture, global_role FROM users WHERE id IN ($in)");
            $st->execute($p);
            foreach ($st->fetchAll() as $r) self::$users[(int)$r['id']] = self::mapUser($r);
            foreach ($need as $id) self::$users[$id] ??= null;
        }
        $need = array_diff(array_map('intval', $studioIds), array_keys(self::$studios), [0]);
        if ($need) {
            [$in, $p] = Fx::in($need);
            $st = $pdo->prepare("SELECT id, name, tiker, avatar_link, banner_link, status, owner_id FROM studios WHERE id IN ($in)");
            $st->execute($p);
            foreach ($st->fetchAll() as $r) self::$studios[(int)$r['id']] = self::mapStudio($r);
            foreach ($need as $id) self::$studios[$id] ??= null;
        }
        $need = array_diff(array_map('intval', $gameIds), array_keys(self::$games), [0]);
        if ($need) {
            [$in, $p] = Fx::in($need);
            $st = $pdo->prepare("SELECT id, name, developer, icon_url, path_to_cover, banner_url, GQI, status, hidden FROM games WHERE id IN ($in)");
            $st->execute($p);
            foreach ($st->fetchAll() as $r) self::$games[(int)$r['id']] = self::mapGame($r);
            foreach ($need as $id) self::$games[$id] ??= null;
        }
    }

    private static function mapUser(array $r): array
    {
        $name = trim((string)($r['username'] ?? '')) ?: trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        return [
            'type' => 'user', 'id' => (int)$r['id'], 'name' => $name !== '' ? $name : 'user#' . $r['id'],
            'img' => (string)($r['profile_picture'] ?? ''),
            'url' => $r['username'] ? Fx::urlUser((string)$r['username']) : '#',
            'verified' => null, 'frame' => null, 'admin' => (int)($r['global_role'] ?? 0) === -1,
        ];
    }

    private static function mapStudio(array $r): array
    {
        $ok = ($r['status'] ?? '') === 'active';
        return [
            'type' => 'studio', 'id' => (int)$r['id'], 'name' => (string)$r['name'], 'tiker' => (string)($r['tiker'] ?? ''),
            'img' => (string)($r['avatar_link'] ?? ''), 'banner' => (string)($r['banner_link'] ?? ''),
            'url' => Fx::urlStudio((string)($r['tiker'] ?? '')),
            'verified' => $ok ? 'dev' : null, 'frame' => $ok ? 'dev' : null, 'owner_id' => (int)($r['owner_id'] ?? 0),
        ];
    }

    private static function mapGame(array $r): array
    {
        return [
            'id' => (int)$r['id'], 'name' => (string)$r['name'], 'studio_id' => (int)$r['developer'],
            'icon' => (string)($r['icon_url'] ?: ($r['path_to_cover'] ?? '')),
            'cover' => (string)($r['path_to_cover'] ?? ''), 'banner' => (string)($r['banner_url'] ?? ''),
            'rating' => isset($r['GQI']) && $r['GQI'] !== null ? (float)$r['GQI'] : null,
            'status' => (string)($r['status'] ?? ''), 'hidden' => !empty($r['hidden']),
            'url' => Fx::urlGame((int)$r['id']),
        ];
    }

    public static function user(int $id): ?array   { self::prime([$id]);      return self::$users[$id] ?? null; }
    public static function studio(int $id): ?array { self::prime([], [$id]);  return self::$studios[$id] ?? null; }
    public static function game(int $id): ?array   { self::prime([], [], [$id]); return self::$games[$id] ?? null; }

    /** «Dustore» — официальный автор новостей платформы. */
    public static function dustore(): array
    {
        return ['type' => 'channel', 'id' => 0, 'name' => 'Dustore', 'img' => '/swad/static/img/applogo_dark.svg',
                'url' => '/fid', 'verified' => 'official', 'frame' => 'gold'];
    }
}

/* ======================================================================
   ПРАВА
   ====================================================================== */
final class FxAuth
{
    /** Роль пользователя в студии: 'Владелец' | 'Администратор' | 'Модератор' | 'Участник' | null. */
    public static function studioRole(int $studioId, int $uid): ?string
    {
        static $cache = [];
        $k = "$uid:$studioId";
        if (array_key_exists($k, $cache)) return $cache[$k];
        if ($uid <= 0 || $studioId <= 0) return $cache[$k] = null;

        $pdo = Fx::pdo();
        $o = $pdo->prepare("SELECT 1 FROM studios WHERE id = ? AND owner_id = ? LIMIT 1");
        $o->execute([$studioId, $uid]);
        if ($o->fetchColumn()) return $cache[$k] = 'Владелец';

        // staff.telegram_id BIGINT, users.telegram_id VARCHAR — два запроса, без JOIN по разным типам
        $t = $pdo->prepare("SELECT telegram_id FROM users WHERE id = ? LIMIT 1");
        $t->execute([$uid]);
        $tg = $t->fetchColumn();
        if ($tg === false || $tg === null || $tg === '') return $cache[$k] = null;

        $s = $pdo->prepare("SELECT role FROM staff WHERE org_id = ? AND telegram_id = ? LIMIT 1");
        $s->execute([$studioId, $tg]);
        $r = $s->fetchColumn();
        return $cache[$k] = ($r === false ? null : (string)($r ?: 'Участник'));
    }

    public static function isStaff(int $studioId, int $uid): bool
    {
        return self::studioRole($studioId, $uid) !== null;
    }

    /** Владелец-уровень: Владелец/Администратор студии или админ платформы. */
    public static function isStudioOwner(int $studioId, int $uid): bool
    {
        if (Fx::isAdmin($uid)) return true;
        return in_array(self::studioRole($studioId, $uid), ['Владелец', 'Администратор'], true);
    }

    public static function isGameMod(int $gameId, int $uid): bool
    {
        static $cache = [];
        $k = "$uid:$gameId";
        if (isset($cache[$k])) return $cache[$k];
        if ($uid <= 0) return false;
        $st = Fx::pdo()->prepare("SELECT 1 FROM fx_game_mods WHERE game_id = ? AND user_id = ? LIMIT 1");
        $st->execute([$gameId, $uid]);
        return $cache[$k] = (bool)$st->fetchColumn();
    }

    /**
     * Роль на странице игры: 'admin' | 'owner' | 'staff' | 'mod' | null.
     * owner — Владелец/Администратор студии; staff — остальной персонал студии;
     * mod — «модератор игры» (не модератор платформы: права только на стену и обсуждения этой игры).
     */
    public static function gameRole(int $gameId, int $uid): ?string
    {
        if ($uid <= 0) return null;
        if (Fx::isAdmin($uid)) return 'admin';
        $g = FxPeople::game($gameId);
        if (!$g) return null;
        $role = self::studioRole($g['studio_id'], $uid);
        if (in_array($role, ['Владелец', 'Администратор'], true)) return 'owner';
        if ($role !== null) return 'staff';
        return self::isGameMod($gameId, $uid) ? 'mod' : null;
    }

    /** Может ли пользователь модерировать стену (удалять чужое, закреплять, закрывать ветки). */
    public static function canModerateWall(string $wallType, int $wallId, int $uid): bool
    {
        if ($uid <= 0) return false;
        if (Fx::isAdmin($uid)) return true;
        switch ($wallType) {
            case 'user':   return $uid === $wallId;
            case 'game':   return self::gameRole($wallId, $uid) !== null;
            case 'studio': return self::isStaff($wallId, $uid);
            default:       return false;
        }
    }

    /** Студии, от имени которых пользователь может публиковать: владелец или сотрудник. [['id','name','type'=>'studio'],…] */
    public static function studiosOf(int $uid): array
    {
        if ($uid <= 0) return [];
        $pdo = Fx::pdo();
        $ids = [];
        $o = $pdo->prepare("SELECT id FROM studios WHERE owner_id = ?");
        $o->execute([$uid]);
        foreach ($o->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[(int)$id] = true;

        $t = $pdo->prepare("SELECT telegram_id FROM users WHERE id = ? LIMIT 1");   // staff.telegram_id BIGINT ≠ users.telegram_id VARCHAR
        $t->execute([$uid]);
        $tg = $t->fetchColumn();
        if ($tg !== false && $tg !== null && $tg !== '') {
            $s = $pdo->prepare("SELECT org_id FROM staff WHERE telegram_id = ?");
            $s->execute([$tg]);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[(int)$id] = true;
        }
        if (!$ids) return [];
        FxPeople::prime([], array_keys($ids));
        $out = [];
        foreach (array_keys($ids) as $id) {
            $st = FxPeople::studio($id);
            if ($st) $out[] = ['id' => $id, 'name' => $st['name'], 'img' => $st['img'], 'type' => 'studio'];
        }
        return $out;
    }

    /** Подпись роли под постом сотрудника студии: своя из fx_studio_titles, иначе роль из staff. */
    public static function roleLabel(int $studioId, int $uid): ?string
    {
        $st = Fx::pdo()->prepare("SELECT title FROM fx_studio_titles WHERE studio_id = ? AND user_id = ?");
        $st->execute([$studioId, $uid]);
        $t = $st->fetchColumn();
        if ($t !== false && $t !== '') return (string)$t;
        $r = self::studioRole($studioId, $uid);
        return ($r && $r !== 'Участник') ? $r : null;
    }
}
