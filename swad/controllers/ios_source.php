<?php
/**
 * swad/controllers/ios_source.php — iOS sideloading через AltStore.
 *
 * Разработчик грузит неподписанный .ipa → мы вынимаем из него Info.plist
 * (bundle id, версия, сборка, minOS) → пишем строку в ios_app_versions →
 * /source.json (ios/source.php) собирается из БД на лету. Руками JSON не правим.
 */

/* ───────── plist: binary (bplist00) + XML ───────── */

function ios_plist_parse(string $d, int $depth = 0)
{
    if (strncmp($d, 'bplist00', 8) === 0) return ios_bplist_parse($d);
    $x = @simplexml_load_string($d, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    if (!$x || !isset($x->dict)) return null;
    return ios_xplist_node($x->dict, 0);
}

function ios_xplist_node(SimpleXMLElement $n, int $depth)
{
    if ($depth > 32) return null;
    switch ($n->getName()) {
        case 'dict':
            $out = []; $key = null;
            foreach ($n->children() as $c) {
                if ($c->getName() === 'key') { $key = (string)$c; continue; }
                if ($key !== null) $out[$key] = ios_xplist_node($c, $depth + 1);
                $key = null;
            }
            return $out;
        case 'array':
            $out = [];
            foreach ($n->children() as $c) $out[] = ios_xplist_node($c, $depth + 1);
            return $out;
        case 'integer': return (int)$n;
        case 'real':    return (float)$n;
        case 'true':    return true;
        case 'false':   return false;
        default:        return (string)$n;
    }
}

function ios_bplist_parse(string $d)
{
    $len = strlen($d);
    if ($len < 40) return null;
    $t = unpack('CoffSize/CrefSize/JnumObjs/Jtop/JtableOff', substr($d, $len - 26, 26));
    [$offSize, $refSize, $num, $top, $tbl] = [$t['offSize'], $t['refSize'], $t['numObjs'], $t['top'], $t['tableOff']];
    if ($offSize < 1 || $offSize > 8 || $refSize < 1 || $refSize > 8 || $top >= $num || $tbl + $num * $offSize > $len) return null;

    $uint = function (int $pos, int $n) use ($d): int {
        $v = 0;
        for ($i = 0; $i < $n; $i++) $v = ($v << 8) | ord($d[$pos + $i]);
        return $v;
    };
    $parse = function (int $idx, int $depth) use (&$parse, $d, $len, $uint, $offSize, $refSize, $tbl) {
        if ($depth > 32) return null;
        $p = $uint($tbl + $idx * $offSize, $offSize);
        if ($p >= $len) return null;
        $m = ord($d[$p]); $hi = $m >> 4; $lo = $m & 0xF; $p++;
        if ($hi === 0) return $lo === 9 ? true : ($lo === 8 ? false : null);
        if ($hi === 1) return $uint($p, 1 << $lo);
        if ($hi === 2) return $lo === 3 ? unpack('E', substr($d, $p, 8))[1] : unpack('G', substr($d, $p, 4))[1];
        if ($hi === 3) return null;
        // длина в nibble или в следующем int-объекте
        if ($lo === 0xF) {
            $lm = ord($d[$p]) & 0xF; $p++;
            $lo = $uint($p, 1 << $lm); $p += 1 << $lm;
        }
        if ($lo > $len) return null;
        switch ($hi) {
            case 4: return substr($d, $p, $lo);
            case 5: return substr($d, $p, $lo);
            case 6: return mb_convert_encoding(substr($d, $p, $lo * 2), 'UTF-8', 'UTF-16BE');
            case 0xA:
                $a = [];
                for ($i = 0; $i < $lo; $i++) $a[] = $parse($uint($p + $i * $refSize, $refSize), $depth + 1);
                return $a;
            case 0xD:
                $o = [];
                for ($i = 0; $i < $lo; $i++) {
                    $k = $parse($uint($p + $i * $refSize, $refSize), $depth + 1);
                    $v = $parse($uint($p + ($lo + $i) * $refSize, $refSize), $depth + 1);
                    if (is_string($k)) $o[$k] = $v;
                }
                return $o;
        }
        return null;
    };
    return $parse($top, 0);
}

/* ───────── .ipa → метаданные ───────── */

/** @return array{bundle_id:string,name:string,version:string,build:string,min_os:string}|string  строка = текст ошибки */
function ios_read_ipa_meta(string $path)
{
    if (!class_exists('ZipArchive')) return 'На сервере не включено расширение php_zip';
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return 'Файл не похож на .ipa (это не zip-архив)';

    $plistName = null;
    for ($i = 0, $n = $zip->numFiles; $i < $n && $i < 20000; $i++) {
        $nm = $zip->getNameIndex($i);
        // строго Payload/<X>.app/Info.plist — без вложенных .app (расширения, watch)
        if (preg_match('#^Payload/[^/]+\.app/Info\.plist$#', $nm)) { $plistName = $nm; break; }
    }
    if (!$plistName) { $zip->close(); return 'В архиве нет Payload/*.app/Info.plist — это точно .ipa?'; }

    $stat = $zip->statName($plistName);
    if (!$stat || $stat['size'] > 2 * 1048576) { $zip->close(); return 'Info.plist подозрительно большой'; }
    $raw = $zip->getFromName($plistName);
    $zip->close();

    $pl = $raw === false ? null : ios_plist_parse($raw);
    if (!is_array($pl)) return 'Не удалось прочитать Info.plist';

    $bundle = trim((string)($pl['CFBundleIdentifier'] ?? ''));
    $ver    = trim((string)($pl['CFBundleShortVersionString'] ?? ''));
    $build  = trim((string)($pl['CFBundleVersion'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9.\-]{1,150}$/', $bundle)) return 'Некорректный CFBundleIdentifier в Info.plist';
    if ($ver === '')   $ver = $build;
    if ($build === '') $build = $ver;
    if ($ver === '' || strlen($ver) > 40 || strlen($build) > 40) return 'В Info.plist нет версии (CFBundleShortVersionString)';

    return [
        'bundle_id' => $bundle,
        'name'      => trim((string)($pl['CFBundleDisplayName'] ?? $pl['CFBundleName'] ?? '')),
        'version'   => $ver,
        'build'     => $build,
        'min_os'    => trim((string)($pl['MinimumOSVersion'] ?? '')) ?: '12.0',
    ];
}

/* ───────── БД ───────── */

function ios_ensure_tables(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $db->exec("
        CREATE TABLE IF NOT EXISTS ios_app_versions (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            game_id      INT NOT NULL,
            bundle_id    VARCHAR(160) NOT NULL,
            version      VARCHAR(40)  NOT NULL,
            build        VARCHAR(40)  NOT NULL,
            min_os       VARCHAR(16)  NOT NULL DEFAULT '12.0',
            size         BIGINT UNSIGNED NOT NULL,
            download_url VARCHAR(1024) NOT NULL,
            created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_bundle_ver (bundle_id, version, build),
            KEY idx_game (game_id),
            KEY idx_bundle (bundle_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/** Новее ли (v,b) чем (v0,b0)? Сравнение как у AltStore: сперва version, потом build. */
function ios_is_newer(string $v, string $b, string $v0, string $b0): bool
{
    $c = version_compare($v, $v0);
    return $c !== 0 ? $c > 0 : version_compare($b, $b0) > 0;
}

function ios_legacy_apps(): array
{
    $f = __DIR__ . '/../../ios/legacy_apps.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($j['apps'] ?? null) ? $j['apps'] : [];
}

/**
 * Регистрирует версию. Возвращает null при успехе или текст ошибки.
 * Правила: bundle id принадлежит первому, кто его загрузил (игре); версия
 * должна быть строго новее предыдущих; id из старого статичного source.json закрыты.
 */
function ios_register_version(PDO $db, int $gameId, array $meta, string $url, int $size): ?string
{
    ios_ensure_tables($db);

    foreach (ios_legacy_apps() as $a) {
        if (strcasecmp($a['bundleIdentifier'] ?? '', $meta['bundle_id']) === 0)
            return 'Этот bundle id уже занят приложением Dustore — свяжитесь с поддержкой.';
    }

    $q = $db->prepare("SELECT game_id, version, build FROM ios_app_versions WHERE bundle_id = ?");
    $q->execute([$meta['bundle_id']]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if ((int)$r['game_id'] !== $gameId) return 'Bundle id ' . $meta['bundle_id'] . ' уже принадлежит другому проекту.';
        if (!ios_is_newer($meta['version'], $meta['build'], $r['version'], $r['build']))
            return "Версия {$meta['version']} ({$meta['build']}) не новее уже загруженной {$r['version']} ({$r['build']}). Увеличьте CFBundleShortVersionString или CFBundleVersion.";
    }
    // у одной игры — один bundle id, иначе в сторе будет двойник
    $o = $db->prepare("SELECT bundle_id FROM ios_app_versions WHERE game_id = ? AND bundle_id <> ? LIMIT 1");
    $o->execute([$gameId, $meta['bundle_id']]);
    if ($other = $o->fetchColumn()) return "Для этого проекта уже зарегистрирован bundle id $other, а в .ipa — {$meta['bundle_id']}.";

    $db->prepare("INSERT INTO ios_app_versions (game_id, bundle_id, version, build, min_os, size, download_url)
                  VALUES (?,?,?,?,?,?,?)")
       ->execute([$gameId, $meta['bundle_id'], $meta['version'], $meta['build'], $meta['min_os'], $size, $url]);
    return null;
}

/* ───────── генерация source.json ───────── */

function ios_build_source(PDO $db): array
{
    ios_ensure_tables($db);
    $st = $db->query("
        SELECT v.*, g.name AS g_name, g.short_description, g.description, g.icon_url AS g_icon,
               s.name AS studio_name, b.icon_url AS b_icon
        FROM ios_app_versions v
        JOIN games g ON g.id = v.game_id AND g.status = 'published'
        LEFT JOIN studios s ON s.id = g.developer
        LEFT JOIN game_builds b ON b.game_id = v.game_id AND b.platform = 'iOS'
    ");
    $apps = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = $r['bundle_id'];
        if (!isset($apps[$id])) {
            $desc = trim((string)($r['short_description'] ?: $r['description']));
            $apps[$id] = [
                'name'                 => $r['g_name'],
                'bundleIdentifier'     => $id,
                'developerName'        => $r['studio_name'] ?: 'Dustore',
                'iconURL'              => $r['b_icon'] ?: $r['g_icon'] ?: 'https://dustore.ru/swad/static/img/dastyframe3.png',
                'tintColor'            => 'C32178',
                'localizedDescription' => mb_substr($desc, 0, 500) ?: $r['g_name'],
                'versions'             => [],
            ];
        }
        $apps[$id]['versions'][] = [
            'version'      => $r['version'],
            'buildVersion' => $r['build'],
            'date'         => substr((string)$r['created_at'], 0, 10),
            'downloadURL'  => $r['download_url'],
            'size'         => (int)$r['size'],
            'minOSVersion' => $r['min_os'],
        ];
    }
    foreach ($apps as &$a) {
        // AltStore считает последней первую версию в списке → новейшая сверху
        usort($a['versions'], fn($x, $y) => ios_is_newer($x['version'], $x['buildVersion'], $y['version'], $y['buildVersion']) ? -1 : 1);
    }
    unset($a);

    // старые приложения из статичного JSON — если их нет в БД
    $list = array_values($apps);
    foreach (ios_legacy_apps() as $la) {
        if (!isset($apps[$la['bundleIdentifier'] ?? ''])) $list[] = $la;
    }
    return ['name' => 'Dustore', 'identifier' => 'ru.dustore.mini.source', 'apps' => $list];
}
