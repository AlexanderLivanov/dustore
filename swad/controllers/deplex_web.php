<?php
/**
 * deplex_web.php — хелперы для интеграции deplex в основной сайт dustore.
 * Кладём в swad/ и подключаем в game.php и /devs/edit.
 *
 * Работает с той же БД, что и сайт (таблицы deplex_* + games). Через api.dustore.ru
 * ходить не нужно — всё локально по PDO.
 *
 * Требует $pdo (PDO к базе dustore).
 */

/** Публичный базовый URL API (для кнопки установщика). */
if (!defined('DEPLEX_API_BASE')) {
    define('DEPLEX_API_BASE', 'https://api.dustore.ru');
}

/**
 * Как игра раздаётся игрокам: 'deplex' | 'web' | 'none'.
 * Приоритет у deplex: если есть закоммиченный билд — раздаём установщиком.
 */
function deplex_dist_mode(PDO $pdo, int $gameId, ?array $game = null): string
{
    if (deplex_latest_build_id($pdo, $gameId) !== null) {
        return 'deplex';
    }
    if ($game === null) {
        $g = $pdo->prepare("SELECT game_zip_url FROM games WHERE id = :g LIMIT 1");
        $g->execute([':g' => $gameId]);
        $game = $g->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return !empty($game['game_zip_url']) ? 'web' : 'none';
}

/** id последнего готового deplex-билда игры (или null). */
function deplex_latest_build_id(PDO $pdo, int $gameId): ?int
{
    $st = $pdo->prepare(
        "SELECT b.id
         FROM deplex_builds b
         JOIN deplex_projects p ON p.id = b.project_id
         WHERE p.game_id = :g AND b.status IN ('committed','published')
         ORDER BY b.committed_at DESC LIMIT 1"
    );
    $st->execute([':g' => $gameId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? (int)$r['id'] : null;
}

/**
 * Данные для кнопки скачивания на game.php.
 * @return array{type:string,url:string,label:string}|null  null — качать нечего.
 */
function deplex_download_button(PDO $pdo, int $gameId, array $game, string $os = 'windows'): ?array
{
    switch (deplex_dist_mode($pdo, $gameId, $game)) {
        case 'deplex':
            return [
                'type'  => 'installer',
                'url'   => DEPLEX_API_BASE . "/v1/games/$gameId/installer?os=$os",
                'label' => 'Скачать (установщик)',
            ];
        case 'web':
            return [
                'type'  => 'zip',
                'url'   => (string)$game['game_zip_url'],
                'label' => 'Скачать (архив)',
            ];
        default:
            return null;
    }
}

/**
 * Инфо о последнем deplex-билде для /devs/edit (из БД + манифеста). null — билдов нет.
 * @return array{ulid:string,version:?string,total_size:int,chunk_count:int,committed_at:?string,files:array}|null
 */
function deplex_build_info(PDO $pdo, int $gameId): ?array
{
    $st = $pdo->prepare(
        "SELECT b.build_ulid, b.version_label, b.total_size, b.chunk_count, b.committed_at, b.manifest_json
         FROM deplex_builds b
         JOIN deplex_projects p ON p.id = b.project_id
         WHERE p.game_id = :g AND b.status IN ('committed','published')
         ORDER BY b.committed_at DESC LIMIT 1"
    );
    $st->execute([':g' => $gameId]);
    $b = $st->fetch(PDO::FETCH_ASSOC);
    if (!$b) {
        return null;
    }
    $man = json_decode($b['manifest_json'] ?? '[]', true) ?: [];
    $files = [];
    foreach (($man['files'] ?? []) as $f) {
        $files[] = ['path' => $f['path'] ?? '', 'size' => (int)($f['size'] ?? 0)];
    }
    // сортировка по пути для стабильного показа
    usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));

    return [
        'ulid'         => $b['build_ulid'],
        'version'      => $b['version_label'],
        'total_size'   => (int)$b['total_size'],
        'chunk_count'  => (int)$b['chunk_count'],
        'committed_at' => $b['committed_at'],
        'files'        => $files,
    ];
}

/** Есть ли у игры deplex-проект (был хоть раз init/build). */
function deplex_has_project(PDO $pdo, int $gameId): bool
{
    $st = $pdo->prepare("SELECT 1 FROM deplex_projects WHERE game_id = :g LIMIT 1");
    $st->execute([':g' => $gameId]);
    return (bool)$st->fetch();
}

/**
 * Объединённая квота студии: deplex-чанки + веб-архивы. Байты.
 * @return array{used:int,quota:int,deplex:int,web:int}
 */
function deplex_studio_quota(PDO $pdo, int $studioId): array
{
    $default = 10 * 1024 * 1024 * 1024;

    $u = $pdo->prepare("SELECT bytes_used, bytes_quota FROM deplex_usage WHERE studio_id = :s");
    $u->execute([':s' => $studioId]);
    $row = $u->fetch(PDO::FETCH_ASSOC);
    $deplexBytes = $row ? (int)$row['bytes_used'] : 0;
    $quota = $row ? (int)$row['bytes_quota'] : $default;

    // Веб-архивы игр студии, которые НЕ раздаются через deplex (чтобы не считать дважды).
    $z = $pdo->prepare(
        "SELECT COALESCE(SUM(g.game_zip_size),0) AS zip
         FROM games g
         WHERE g.developer = :s AND g.game_zip_size IS NOT NULL
           AND NOT EXISTS (
               SELECT 1 FROM deplex_projects p
               JOIN deplex_builds b ON b.project_id = p.id
               WHERE p.game_id = g.id AND b.status IN ('committed','published')
           )"
    );
    $z->execute([':s' => $studioId]);
    $zipBytes = (int)($z->fetch(PDO::FETCH_ASSOC)['zip'] ?? 0);

    return [
        'used'   => $deplexBytes + $zipBytes,
        'quota'  => $quota,
        'deplex' => $deplexBytes,
        'web'    => $zipBytes,
    ];
}

/** Влезет ли ещё $addBytes в квоту студии. */
function deplex_quota_fits(PDO $pdo, int $studioId, int $addBytes): bool
{
    $q = deplex_studio_quota($pdo, $studioId);
    return ($q['used'] + $addBytes) <= $q['quota'];
}

/** Человекочитаемый размер. */
function deplex_human(int $n): string
{
    $u = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    $i = 0;
    $v = (float)$n;
    while ($v >= 1024 && $i < count($u) - 1) {
        $v /= 1024;
        $i++;
    }
    return ($i === 0 ? $n : number_format($v, 1)) . ' ' . $u[$i];
}

/**
 * Строит дерево из плоского списка файлов манифеста (пути через '/').
 * У каждого узла: dirs (вложенные), files, агрегированные size и count.
 * @param array $files  [['path'=>'Data/x.pak','size'=>123], ...]
 */
function deplex_files_tree(array $files): array
{
    $root = ['dirs' => [], 'files' => [], 'size' => 0, 'count' => 0];
    foreach ($files as $f) {
        $size  = (int)($f['size'] ?? 0);
        $parts = explode('/', str_replace('\\', '/', (string)($f['path'] ?? '')));
        $fname = array_pop($parts);

        $ref = &$root;
        $ref['size'] += $size;
        $ref['count']++;
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (!isset($ref['dirs'][$p])) {
                $ref['dirs'][$p] = ['dirs' => [], 'files' => [], 'size' => 0, 'count' => 0];
            }
            $ref = &$ref['dirs'][$p];
            $ref['size'] += $size;
            $ref['count']++;
        }
        $ref['files'][] = ['name' => $fname, 'size' => $size];
        unset($ref);
    }
    return $root;
}

/**
 * Рендерит дерево: папки — сворачиваемые <details> (по умолчанию свёрнуты),
 * файлы — строки. Так вложенные подпапки не раздувают список.
 */
function deplex_render_tree(array $node): void
{
    ksort($node['dirs']);
    foreach ($node['dirs'] as $name => $child) {
        echo '<details style="margin:1px 0;">';
        echo '<summary style="cursor:pointer;padding:4px 0;color:var(--ts);">'
           . '📁 ' . htmlspecialchars($name)
           . ' <span style="color:var(--tm);font-size:11px;">('
           . (int)$child['count'] . ' · ' . deplex_human((int)$child['size']) . ')</span>'
           . '</summary>';
        echo '<div style="padding-left:16px;border-left:1px solid var(--elev);margin-left:5px;">';
        deplex_render_tree($child);
        echo '</div></details>';
    }
    usort($node['files'], fn($a, $b) => strcmp($a['name'], $b['name']));
    foreach ($node['files'] as $f) {
        echo '<div style="display:flex;justify-content:space-between;gap:12px;padding:3px 0;font-size:12px;color:var(--ts);">'
           . '<span style="word-break:break-all;">' . htmlspecialchars($f['name']) . '</span>'
           . '<span style="color:var(--tm);white-space:nowrap;">' . deplex_human((int)$f['size']) . '</span>'
           . '</div>';
    }
}