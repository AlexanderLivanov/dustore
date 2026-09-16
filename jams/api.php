<?php
declare(strict_types=1);
/**
 * /jams/api.php — публичная статистика джемов Dustore.
 * Только агрегаты, без персональных данных. Авторизация не нужна.
 *
 *   GET /jams/api                          — список джемов
 *   GET /jams/api?id=12                    — полная статистика
 *   GET /jams/api?id=12&section=builds     — одна секция
 *
 * Секции: overview | builds | timeline | teams | tech | geo | activity | leaderboard
 */
require_once(__DIR__ . '/../swad/config.php');

date_default_timezone_set('Europe/Moscow');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=60');

const JAM_API_VERSION   = '1.1';
const JAM_API_CACHE_TTL = 60;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
function fail(int $code, string $msg): void {
    out(['success' => false, 'error' => $msg], $code);
}

if ($method !== 'GET') fail(405, 'Только GET');

function cache_file(string $key): string {
    return sys_get_temp_dir() . '/jamapi_' . preg_replace('/\W+/', '', $key) . '.json';
}
function cache_get(string $key, int $ttl): ?array {
    $f = cache_file($key);
    if (is_file($f) && (time() - filemtime($f)) < $ttl) {
        $d = json_decode((string)file_get_contents($f), true);
        if (is_array($d)) return $d;
    }
    return null;
}
function cache_put(string $key, array $d): void {
    @file_put_contents(cache_file($key), json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Свободный ввод города → канон. Мусор, шутки и названия регионов отбрасываем. */
function norm_city(?string $s): ?string {
    $s = trim((string)$s);
    if ($s === '' || mb_strlen($s, 'UTF-8') > 40) return null;
    $low = preg_replace('/[.!,\s]+$/u', '', mb_strtolower($s, 'UTF-8'));

    static $junk = ['.', 'зачем?', 'мухосранск', 'соска', 'бархан 9', 'бархан-9',
                    'россия', 'казахстан', 'тульская область', 'ram', 'снг'];
    if (in_array($low, $junk, true)) return null;

    static $map = [
        'moscow' => 'Москва', 'москва' => 'Москва',
        'спб' => 'Санкт-Петербург', 'saint-petersburg' => 'Санкт-Петербург',
        'saint petersburg, russian federation' => 'Санкт-Петербург',
        'санкт - петербург' => 'Санкт-Петербург', 'санкт петербург' => 'Санкт-Петербург',
        'санкт-петербург, россия' => 'Санкт-Петербург', 'санкт-питербург' => 'Санкт-Петербург',
        'санкт-петербург' => 'Санкт-Петербург', 'г санкт-петербург' => 'Санкт-Петербург',
        'yekaterinburg' => 'Екатеринбург', 'nsk' => 'Новосибирск', 'новосибрск' => 'Новосибирск',
        'kazan' => 'Казань', 'ufa' => 'Уфа', 'krasnodar' => 'Краснодар',
        'magnitogorsk' => 'Магнитогорск',
        'rostov' => 'Ростов-на-Дону', 'ростов' => 'Ростов-на-Дону',
        'ростов на дону' => 'Ростов-на-Дону', 'ростов-на-дону' => 'Ростов-на-Дону',
        'нижний новрогод' => 'Нижний Новгород',
        'almaty' => 'Алматы', 'kazakhstan, almaty' => 'Алматы',
        'bishkek' => 'Бишкек', 'minsk' => 'Минск',
        'королев' => 'Королёв', 'кишинев' => 'Кишинёв',
        'nicosia' => 'Никосия', 'warsaw' => 'Варшава', 'orsha' => 'Орша',
        'беларусь, брест' => 'Брест', 'тула (тульская область)' => 'Тула',
        'краснодарский край, г. геленджик' => 'Геленджик',
        'городской округ домодедово' => 'Домодедово',
    ];
    if (isset($map[$low])) return $map[$low];

    return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
}

$pdo = (new Database())->connect();
if (!$pdo) fail(500, 'БД недоступна');

/* ══════════════ Список джемов ══════════════ */
$sid = (int)($_GET['id'] ?? $_GET['sprint_id'] ?? 0);
if ($sid <= 0) {
    $rows = $pdo->query("
        SELECT s.id, s.title, s.status, s.jam_start, s.jam_end, s.voting_start, s.voting_end,
               (SELECT COUNT(*) FROM sprint_participants p WHERE p.sprint_id = s.id) AS participants,
               (SELECT COUNT(*) FROM games g
                 WHERE g.sprint_id = s.id AND g.moderation_status = 'approved') AS builds
        FROM sprints s
        WHERE s.status IN ('ongoing','finished') OR s.voting_start IS NOT NULL
        ORDER BY s.jam_start DESC, s.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id']           = (int)$r['id'];
        $r['participants'] = (int)$r['participants'];
        $r['builds']       = (int)$r['builds'];
    }
    unset($r);

    out(['success' => true, 'api_version' => JAM_API_VERSION,
         'generated_at' => date('c'), 'jams' => $rows]);
}

$section  = preg_replace('/[^a-z_]/', '', (string)($_GET['section'] ?? 'all'));
$cacheKey = "s{$sid}_{$section}";
if ($hit = cache_get($cacheKey, JAM_API_CACHE_TTL)) { header('X-Cache: HIT'); out($hit); }

$q = $pdo->prepare("SELECT id, title, status, theme, jam_start, jam_end,
                           voting_start, voting_end, max_participants
                    FROM sprints WHERE id = ? LIMIT 1");
$q->execute([$sid]);
$sprint = $q->fetch(PDO::FETCH_ASSOC);
if (!$sprint) fail(404, 'Джем не найден');

/* ══════════════ Сырьё ══════════════ */
$g = $pdo->prepare("
    SELECT g.id, g.name, g.genre, g.game_zip_url, g.game_zip_size, g.scan_started_at,
           g.moderation_status, g.status, s.owner_id
    FROM games g
    LEFT JOIN studios s ON s.id = g.developer
    WHERE g.sprint_id = ?");
$g->execute([$sid]);
$games = $g->fetchAll(PDO::FETCH_ASSOC);

// Последний committed deplex-билд каждой игры.
$d = $pdo->query("
    SELECT b.game_id, b.total_size, b.created_at
    FROM deplex_builds b
    JOIN (SELECT game_id, MAX(created_at) mx FROM deplex_builds
          WHERE status = 'committed' GROUP BY game_id) t
      ON t.game_id = b.game_id AND t.mx = b.created_at
    WHERE b.status = 'committed'")->fetchAll(PDO::FETCH_ASSOC);
$deplex = [];
foreach ($d as $r) {
    $deplex[(int)$r['game_id']] = ['size' => (int)$r['total_size'], 'at' => $r['created_at']];
}

// Фактический состав команд джема: user_id → размер команды.
$t = $pdo->prepare("
    SELECT tm.user_id,
           (SELECT COUNT(*) FROM team_members x WHERE x.team_id = tm.team_id) AS size
    FROM team_members tm WHERE tm.sprint_id = ?");
$t->execute([$sid]);
$userTeam = [];
foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $userTeam[(int)$r['user_id']] = (int)$r['size'];
}

/* Билды: zip либо deplex, всегда последняя версия. */
$builds = [];
foreach ($games as $row) {
    $gid = (int)$row['id'];
    if (!empty($row['game_zip_url'])) {
        $kind = 'zip';
        $size = (int)$row['game_zip_size'];
        $at   = $row['scan_started_at'];
    } elseif (isset($deplex[$gid])) {
        $kind = 'deplex';
        $size = $deplex[$gid]['size'];
        $at   = $deplex[$gid]['at'];
    } else {
        continue;
    }
    $teamSize = $userTeam[(int)$row['owner_id']] ?? 1;
    $builds[] = [
        'kind'       => $kind,
        'size'       => $size,
        'at'         => $at,
        'genre'      => $row['genre'],
        'moderation' => $row['moderation_status'],
        'mode'       => $teamSize > 1 ? 'team' : 'solo',
        'team_size'  => $teamSize,
    ];
}

$res = [
    'success'      => true,
    'api_version'  => JAM_API_VERSION,
    'generated_at' => date('c'),
    'sprint' => [
        'id'           => (int)$sprint['id'],
        'title'        => $sprint['title'],
        'status'       => $sprint['status'],
        'jam_start'    => $sprint['jam_start'],
        'jam_end'      => $sprint['jam_end'],
        'voting_start' => $sprint['voting_start'],
        'voting_end'   => $sprint['voting_end'],
    ],
];
$want = static fn(string $s): bool => $section === 'all' || $section === $s;

/* ══════════════ overview ══════════════ */
if ($want('overview')) {
    $p = $pdo->prepare("SELECT COUNT(*) FROM sprint_participants WHERE sprint_id = ?");
    $p->execute([$sid]);
    $tt = $pdo->prepare("SELECT COUNT(*) FROM sprint_teams WHERE sprint_id = ?");
    $tt->execute([$sid]);

    $res['overview'] = [
        'participants'    => (int)$p->fetchColumn(),
        'teams_created'   => (int)$tt->fetchColumn(),
        'projects'        => count($games),
        'builds'          => count($builds),
        'builds_approved' => count(array_filter($builds, static fn($b) => $b['moderation'] === 'approved')),
        'solo'            => count(array_filter($builds, static fn($b) => $b['mode'] === 'solo')),
        'by_teams'        => count(array_filter($builds, static fn($b) => $b['mode'] === 'team')),
        'devs_finished'   => array_sum(array_column($builds, 'team_size')),
    ];
}

/* ══════════════ builds ══════════════ */
if ($want('builds')) {
    $sizes = array_column($builds, 'size');
    sort($sizes);
    $n = count($sizes);
    $res['builds'] = [
        'count'   => $n,
        'by_kind' => [
            'zip'    => count(array_filter($builds, static fn($b) => $b['kind'] === 'zip')),
            'deplex' => count(array_filter($builds, static fn($b) => $b['kind'] === 'deplex')),
        ],
        'bytes_total'  => array_sum($sizes),
        'bytes_min'    => $n ? $sizes[0] : 0,
        'bytes_max'    => $n ? $sizes[$n - 1] : 0,
        'bytes_median' => $n ? $sizes[intdiv($n, 2)] : 0,
        'buckets' => [
            'lt_100mb'  => count(array_filter($sizes, static fn($s) => $s < 104857600)),
            '100_300mb' => count(array_filter($sizes, static fn($s) => $s >= 104857600 && $s < 314572800)),
            '300mb_1gb' => count(array_filter($sizes, static fn($s) => $s >= 314572800 && $s < 1073741824)),
            'gt_1gb'    => count(array_filter($sizes, static fn($s) => $s >= 1073741824)),
        ],
        'note' => 'zip и deplex вместе, по последней версии каждого проекта',
    ];
}

/* ══════════════ timeline ══════════════ */
if ($want('timeline')) {
    $byDay = $byHour = [];
    foreach ($builds as $b) {
        if (empty($b['at'])) continue;
        $day = substr((string)$b['at'], 0, 10);
        $hr  = (int)substr((string)$b['at'], 11, 2);
        $byDay[$day]  = ($byDay[$day]  ?? 0) + 1;
        $byHour[$hr]  = ($byHour[$hr]  ?? 0) + 1;
    }
    ksort($byDay);
    ksort($byHour);

    $reg = $pdo->prepare("SELECT DATE(joined_at) d, COUNT(*) c FROM sprint_participants
                          WHERE sprint_id = ? GROUP BY d ORDER BY d");
    $reg->execute([$sid]);

    $res['timeline'] = [
        'uploads_by_day'       => $byDay,
        'uploads_by_hour'      => $byHour,
        'registrations_by_day' => array_map('intval', $reg->fetchAll(PDO::FETCH_KEY_PAIR)),
        'note' => 'момент загрузки zip = запуск антивирус-скана; deplex = последний committed-билд',
    ];
}

/* ══════════════ teams ══════════════ */
if ($want('teams')) {
    $teamBuilds = array_values(array_filter($builds, static fn($b) => $b['mode'] === 'team'));
    $hist = [];
    foreach ($teamBuilds as $b) $hist[$b['team_size']] = ($hist[$b['team_size']] ?? 0) + 1;
    ksort($hist);

    $res['teams'] = [
        'with_build'     => count($teamBuilds),
        'size_histogram' => $hist,
        'avg_size'       => $teamBuilds
            ? round(array_sum(array_column($teamBuilds, 'team_size')) / count($teamBuilds), 2) : 0,
        'max_size'       => $teamBuilds ? max(array_column($teamBuilds, 'team_size')) : 0,
        'note' => 'состав берётся фактический, команда из одного человека считается соло',
    ];
}

/* ══════════════ tech ══════════════ */
if ($want('tech')) {
    $e = $pdo->prepare("SELECT engine FROM sprint_progress
                        WHERE sprint_id = ? AND engine IS NOT NULL AND engine <> ''");
    $e->execute([$sid]);

    $normEngine = ['Unreal Engine 5' => 'Unreal Engine', 'Unreal Engine 4' => 'Unreal Engine',
                   'godot 4.5 c#' => 'Godot', 'GDevelop5' => 'GDevelop'];
    $eng = [];
    foreach ($e->fetchAll(PDO::FETCH_COLUMN) as $v) {
        $v = $normEngine[$v] ?? $v;
        $eng[$v] = ($eng[$v] ?? 0) + 1;
    }
    arsort($eng);

    $gen = [];
    foreach ($builds as $b) {
        if (!empty($b['genre'])) $gen[$b['genre']] = ($gen[$b['genre']] ?? 0) + 1;
    }
    arsort($gen);

    $res['tech'] = [
        'engines' => $eng,
        'genres'  => $gen,
        'note'    => 'движки — самодекларация из анкеты прогресса, заполняют не все',
    ];
}

/* ══════════════ geo ══════════════ */
if ($want('geo')) {
    $c = $pdo->prepare("SELECT city FROM sprint_participants WHERE sprint_id = ?");
    $c->execute([$sid]);

    $cities = [];
    $answers = 0;
    foreach ($c->fetchAll(PDO::FETCH_COLUMN) as $raw) {
        $n = norm_city($raw);
        if ($n === null) continue;
        $answers++;
        $cities[$n] = ($cities[$n] ?? 0) + 1;
    }
    arsort($cities);

    $res['geo'] = [
        'cities_total' => count($cities),
        'answers'      => $answers,
        'top'          => array_slice($cities, 0, 15, true),
        'note'         => 'город — свободный ввод, написания и транслит объединены',
    ];
}

/* ══════════════ activity ══════════════
   Динамика голосования и скачиваний. Без разбивки по играм — чтобы во время
   голосования по эндпоинту нельзя было восстановить расстановку мест. */
if ($want('activity')) {
    $v = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) votes, COUNT(DISTINCT user_id) voters
                        FROM jam_votes WHERE sprint_id = ? GROUP BY d ORDER BY d");
    $v->execute([$sid]);
    $votesByDay = [];
    foreach ($v->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $votesByDay[$r['d']] = ['votes' => (int)$r['votes'], 'voters' => (int)$r['voters']];
    }

    $p = $pdo->prepare("SELECT DATE(first_download_at) d, COUNT(*) c FROM jam_plays
                        WHERE sprint_id = ? GROUP BY d ORDER BY d");
    $p->execute([$sid]);

    $tot = $pdo->prepare("SELECT COUNT(*) n, COUNT(DISTINCT user_id) u, COUNT(DISTINCT game_id) g
                          FROM jam_votes WHERE sprint_id = ?");
    $tot->execute([$sid]);
    $tr = $tot->fetch(PDO::FETCH_ASSOC);

    $dl = $pdo->prepare("SELECT COUNT(*) n, COUNT(DISTINCT user_id) u FROM jam_plays WHERE sprint_id = ?");
    $dl->execute([$sid]);
    $dr = $dl->fetch(PDO::FETCH_ASSOC);

    $res['activity'] = [
        'votes_total'        => (int)$tr['n'],
        'voters_total'       => (int)$tr['u'],
        'games_with_votes'   => (int)$tr['g'],
        'downloads_total'    => (int)$dr['n'],
        'downloaders_total'  => (int)$dr['u'],
        'votes_by_day'       => $votesByDay,
        'downloads_by_day'   => array_map('intval', $p->fetchAll(PDO::FETCH_KEY_PAIR)),
    ];
}

/* ══════════════ leaderboard ══════════════
   Открывается автоматически после voting_end. До этого — только объём активности. */
if ($want('leaderboard')) {
    $vEnd = $sprint['voting_end'] ? strtotime((string)$sprint['voting_end']) : null;

    if ($vEnd && time() < $vEnd) {
        $v = $pdo->prepare("SELECT COUNT(*) n, COUNT(DISTINCT user_id) u FROM jam_votes WHERE sprint_id = ?");
        $v->execute([$sid]);
        $row = $v->fetch(PDO::FETCH_ASSOC);
        $res['leaderboard'] = [
            'available_after' => $sprint['voting_end'],
            'votes_cast'      => (int)$row['n'],
            'voters'          => (int)$row['u'],
            'note'            => 'рейтинг закрыт до конца голосования — защита от накрутки',
        ];
    } else {
        $l = $pdo->prepare("
            SELECT g.id, g.name,
                   SUM(v.points) points,
                   SUM(v.points * v.weight) points_weighted,
                   COUNT(*) votes
            FROM jam_votes v
            JOIN games g ON g.id = v.game_id
            WHERE v.sprint_id = ?
            GROUP BY g.id, g.name
            ORDER BY points_weighted DESC, votes DESC
            LIMIT 20");
        $l->execute([$sid]);

        $top = [];
        foreach ($l->fetchAll(PDO::FETCH_ASSOC) as $i => $r) {
            $top[] = [
                'place'           => $i + 1,
                'id'              => (int)$r['id'],
                'name'            => $r['name'],
                'points'          => (int)$r['points'],
                'points_weighted' => round((float)$r['points_weighted'], 2),
                'votes'           => (int)$r['votes'],
            ];
        }
        $res['leaderboard'] = ['top' => $top];
    }
}

cache_put($cacheKey, $res);
header('X-Cache: MISS');
out($res);