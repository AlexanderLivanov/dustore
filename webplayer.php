<?php
/* ============================================================================
 * Dustore WebPlayer
 * (c) Dustore
 *
 * Раскладка на диске:
 *   webplayerdata/<id>/www/          ← ТОЛЬКО контент игры, отдаётся наружу
 *   webplayerdata/<id>/game.zip      ← архив, наружу не отдаётся (.htaccess)
 *   webplayerdata/<id>/.meta.json    ← состояние, точка входа, движок
 *   webplayerdata/<id>/.lock         ← flock от гонок
 *
 * Три режима на одном URL:
 *   webplayer.php?id=N             — страница плеера
 *   webplayer.php?id=N&status=1    — JSON состояния
 *   webplayer.php?id=N&prepare=1   — JSON, качает и распаковывает (под локом)
 * ==========================================================================*/

session_start();
require_once('swad/config.php');
require_once('swad/controllers/game.php');

/* ─────────────────────────── НАСТРОЙКИ ─────────────────────────── */

const WP_BASE_DIR       = __DIR__ . '/webplayerdata';
const WP_BASE_URL       = '/webplayerdata';
const WP_TTL_HOURS      = 6;            // через сколько чистим неиспользуемое
const WP_MAX_ZIP_BYTES  = 700 * 1024 * 1024;
const WP_MAX_UNPACKED   = 2000 * 1024 * 1024;
const WP_MAX_ENTRIES    = 20000;
const WP_DL_TIMEOUT     = 900;

/* Откуда вообще разрешено качать архивы. Без этого списка поле
   game_zip_url — это SSRF: студия пишет туда http://127.0.0.1:3001/
   и заставляет твой сервер ходить по своей внутренней сети. */
const WP_ALLOWED_HOSTS = [
    's3.regru.cloud',
    'dustore.ru',
    'dustore.gg',
];

/* Что не должно оказаться в веб-каталоге ни при каких условиях. */
const WP_FORBIDDEN_EXT = [
    'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'phtml', 'pht',
    'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'shtml', 'shtm', 'fcgi', 'fpl', 'jsp',
];

/* ─────────────────────────── ХЕЛПЕРЫ ФС ─────────────────────────── */

function wp_paths(int $id): array
{
    $dir = WP_BASE_DIR . '/' . $id;
    return [
        'dir'   => $dir,
        'www'   => $dir . '/www',
        'zip'   => $dir . '/game.zip',
        'meta'  => $dir . '/.meta.json',
        'lock'  => $dir . '/.lock',
        'stage' => $dir . '/.stage',
        'url'   => WP_BASE_URL . '/' . $id . '/www',
    ];
}

function wp_mkdir(string $d): bool
{
    return is_dir($d) || mkdir($d, 0775, true) || is_dir($d);
}

/** Рекурсивное удаление на PHP. exec("rm -rf") не работает на Windows/XAMPP
 *  и падает молча, если exec отключён в php.ini. */
function wp_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        if (is_file($dir) || is_link($dir)) @unlink($dir);
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

function wp_meta_read(int $id): array
{
    $p = wp_paths($id)['meta'];
    if (!is_file($p)) return ['state' => 'absent'];
    $j = json_decode((string)file_get_contents($p), true);
    return is_array($j) ? $j : ['state' => 'absent'];
}

function wp_meta_write(int $id, array $meta): void
{
    $p = wp_paths($id);
    wp_mkdir($p['dir']);
    $meta['updated_at'] = time();
    // tmp + rename: читатель никогда не увидит полузаписанный JSON
    $tmp = $p['meta'] . '.tmp';
    file_put_contents($tmp, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @rename($tmp, $p['meta']);
}

/* ─────────────────────────── ЗАГРУЗКА ─────────────────────────── */

function wp_url_ok(string $url, ?string &$err = null): bool
{
    $u = parse_url($url);
    if (!$u || empty($u['scheme']) || empty($u['host'])) {
        $err = 'Некорректный URL архива';
        return false;
    }
    if (!in_array(strtolower($u['scheme']), ['http', 'https'], true)) {
        $err = 'Разрешены только http/https';
        return false;
    }
    $host = strtolower($u['host']);
    foreach (WP_ALLOWED_HOSTS as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) return true;
    }
    $err = 'Хост ' . $host . ' не в списке разрешённых (WP_ALLOWED_HOSTS)';
    return false;
}

function wp_download(string $url, string $dest, ?string &$err = null): bool
{
    if (!wp_url_ok($url, $err)) return false;

    $part = $dest . '.part';
    $fp = fopen($part, 'w+b');
    if (!$fp) {
        $err = 'Не удалось создать файл';
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => WP_DL_TIMEOUT,
        CURLOPT_USERAGENT      => 'Dustore-WebPlayer/2.0',
        CURLOPT_FAILONERROR    => true,
        CURLOPT_NOPROGRESS     => false,
        // Обрываем качку, а не узнаём про размер после того, как диск кончился
        CURLOPT_PROGRESSFUNCTION => function ($res, $dlTotal, $dlNow) {
            return ($dlTotal > WP_MAX_ZIP_BYTES || $dlNow > WP_MAX_ZIP_BYTES) ? 1 : 0;
        },
    ]);

    $ok   = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$ok || ($code && $code >= 400)) {
        @unlink($part);
        $err = $cerr ?: ('HTTP ' . $code);
        return false;
    }
    if (filesize($part) < 22) {           // меньше пустого EOCD-заголовка zip
        @unlink($part);
        $err = 'Архив пустой или недокачан';
        return false;
    }

    // .part → game.zip только целиком: недокачанный файл никогда не выглядит готовым
    if (!@rename($part, $dest)) {
        @unlink($part);
        $err = 'Не удалось сохранить архив';
        return false;
    }
    return true;
}

/* ─────────────────────────── РАСПАКОВКА ─────────────────────────── */

/** Имя записи безопасно? Ловит Zip Slip и попытки протащить исполняемое. */
function wp_entry_ok(string $name): bool
{
    if ($name === '' || str_contains($name, "\0")) return false;
    $n = str_replace('\\', '/', $name);
    if ($n[0] === '/' || preg_match('~^[A-Za-z]:~', $n)) return false;   // абсолютный путь
    foreach (explode('/', $n) as $seg) {
        if ($seg === '..') return false;                                 // выход наверх
    }
    $base = basename($n);
    if ($base === '.htaccess' || $base === '.user.ini') return false;    // не дать вернуть PHP
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    return !in_array($ext, WP_FORBIDDEN_EXT, true);
}

function wp_unzip(string $zipFile, string $to, ?string &$err = null): bool
{
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        $err = 'Не удалось открыть архив (повреждён или не zip)';
        return false;
    }
    if ($zip->numFiles > WP_MAX_ENTRIES) {
        $zip->close();
        $err = 'В архиве слишком много файлов (' . $zip->numFiles . ')';
        return false;
    }

    // Сначала ВСЁ проверяем, только потом извлекаем: частично распакованная
    // игра с одним вредным файлом хуже, чем нераспакованная.
    $total = 0;
    $safe  = [];
    $skipped = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        if (!$st) continue;
        $name = $st['name'];
        if (substr($name, -1) === '/') continue;          // каталог
        if (!wp_entry_ok($name)) {
            $skipped++;
            continue;
        }
        $total += (int)$st['size'];
        if ($total > WP_MAX_UNPACKED) {                    // zip-бомба
            $zip->close();
            $err = 'Распакованный размер превышает лимит';
            return false;
        }
        $safe[] = $name;
    }
    if (!$safe) {
        $zip->close();
        $err = 'В архиве нет пригодных файлов';
        return false;
    }

    wp_mkdir($to);
    $ok = $zip->extractTo($to, $safe);
    $zip->close();

    if (!$ok) {
        $err = 'Ошибка распаковки';
        return false;
    }
    if ($skipped) error_log("[webplayer] пропущено небезопасных файлов: $skipped ($zipFile)");
    return true;
}

/** Спускаемся вниз, пока папка содержит ровно один каталог и ни одного файла.
 *  Это и есть «расплющивание»: MyGame/Build/... → отдаём MyGame. */
function wp_content_root(string $dir): string
{
    for ($depth = 0; $depth < 8; $depth++) {
        $items = array_values(array_diff(scandir($dir) ?: [], ['.', '..', '__MACOSX']));
        if (count($items) !== 1) return $dir;
        $only = $dir . '/' . $items[0];
        if (!is_dir($only)) return $dir;
        $dir = $only;
    }
    return $dir;
}

/* ─────────────────────────── ТОЧКА ВХОДА ─────────────────────────── */

/** Все .html/.htm в дереве, относительными путями. */
function wp_list_html(string $root): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $ext = strtolower($f->getExtension());
        if ($ext !== 'html' && $ext !== 'htm') continue;
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        if (str_starts_with($rel, '__MACOSX/')) continue;
        $out[] = $rel;
        if (count($out) > 500) break;
    }
    return $out;
}

/**
 * Ищем, что запускать. Порядок важен: сначала верим разработчику,
 * потом соглашениям, и только потом гадаем.
 */
function wp_find_entry(string $root, string $hint = ''): ?string
{
    $files = wp_list_html($root);
    if (!$files) return null;

    // 1. game_exec из БД — точное совпадение пути
    $hint = trim(str_replace('\\', '/', $hint), "/ \t\n\r");
    if ($hint !== '') {
        if (in_array($hint, $files, true)) return $hint;
        // ...или совпадение по имени файла где-то в глубине
        foreach ($files as $f) {
            if (strcasecmp(basename($f), basename($hint)) === 0) return $f;
        }
    }

    // 2. Скоринг. Меньше — лучше.
    $bad  = ['readme', 'license', 'licence', 'help', 'credits', 'changelog',
        'offline', '404', 'error', 'template', 'test', 'example', 'docs', 'manual'];
    $good = ['index', 'game', 'play', 'main', 'start', 'launcher'];

    $best = null;
    $bestScore = PHP_INT_MAX;
    foreach ($files as $f) {
        $depth = substr_count($f, '/');
        $name  = strtolower(pathinfo($f, PATHINFO_FILENAME));
        $lower = strtolower($f);

        $score = $depth * 100;                       // глубина решает в первую очередь
        if ($name === 'index')            $score -= 50;
        elseif (in_array($name, $good, true)) $score -= 30;
        foreach ($bad as $b) {
            if (str_contains($name, $b)) { $score += 500; break; }
        }
        // служебные каталоги движков — почти наверняка не точка входа
        foreach (['templatedata/', 'build/', 'docs/', 'doc/', '__macosx/'] as $d) {
            if (str_contains($lower, $d)) { $score += 200; break; }
        }
        $score += strlen($f) * 0.01;                 // тай-брейк: короче — лучше

        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $f;
        }
    }
    return $best;
}

/** Определяем движок — от этого зависят заголовки, которые надо отдать. */
function wp_detect_engine(string $root, string $entry): string
{
    $html = @file_get_contents($root . '/' . $entry, false, null, 0, 65536) ?: '';

    if (stripos($html, 'unity') !== false || is_dir($root . '/Build') || is_dir($root . '/TemplateData')) {
        return 'unity';
    }
    if (stripos($html, 'godot') !== false || glob($root . '/*.pck')) {
        return 'godot';
    }
    if (stripos($html, 'c2runtime') !== false || stripos($html, 'c3runtime') !== false) {
        return 'construct';
    }
    if (is_file($root . '/game.js') && is_dir($root . '/lib')) {
        return 'renpy';
    }
    return 'generic';
}

/**
 * Godot 4 и Unity со threads требуют cross-origin isolation ради
 * SharedArrayBuffer. Включать COEP глобально нельзя — он ломает игры,
 * которые тянут что-то с чужих CDN. Поэтому кладём заголовки
 * ПОГАМНО, в .htaccess конкретной игры, и только если движок их просит.
 */
function wp_write_game_htaccess(string $root, string $engine): void
{
    // Nowdoc, а не heredoc: внутри есть $ из регулярок Apache,
    // heredoc попытался бы их интерполировать.
    $rules = <<<'HT'
# Сгенерировано Dustore WebPlayer. Не редактировать вручную.
php_flag engine 0
RemoveHandler .php .phtml .php5 .php7 .cgi .pl .asp .aspx .shtml
AddType text/plain .php .phtml .php5 .php7 .cgi .pl .asp .aspx .shtml

AddType application/wasm .wasm
AddType application/octet-stream .data .pck .mem .bin .unityweb .bundle
AddType application/json .json .symbols.json
AddType text/javascript .js .mjs

# Unity/Godot кладут предсжатые файлы. Без Content-Encoding браузер
# получает бинарный мусор и падает с "invalid magic number".
<IfModule mod_mime.c>
  AddEncoding gzip .gz
  AddEncoding br .br
  <FilesMatch "\.(js|wasm|data|symbols\.json|pck)\.gz$">
    ForceType application/octet-stream
    Header set Content-Encoding gzip
  </FilesMatch>
  <FilesMatch "\.(js|wasm|data|symbols\.json|pck)\.br$">
    ForceType application/octet-stream
    Header set Content-Encoding br
  </FilesMatch>
</IfModule>

<IfModule mod_headers.c>
  Header set Cross-Origin-Resource-Policy "same-origin"
HT;

    if ($engine === 'godot' || $engine === 'unity') {
        $rules .= "\n  Header set Cross-Origin-Opener-Policy \"same-origin\"" .
            "\n  Header set Cross-Origin-Embedder-Policy \"require-corp\"";
    }
    $rules .= "\n</IfModule>\n";

    @file_put_contents($root . '/.htaccess', $rules);
}

/* ─────────────────────────── ПОДГОТОВКА ─────────────────────────── */

function wp_prepare(int $id, array $game): array
{
    $p = wp_paths($id);
    wp_mkdir($p['dir']);

    // Лок: два одновременных первых захода не должны качать архив дважды
    $lh = fopen($p['lock'], 'c+');
    if (!$lh || !flock($lh, LOCK_EX | LOCK_NB)) {
        if ($lh) fclose($lh);
        return ['state' => 'preparing', 'message' => 'Игра уже готовится'];
    }

    try {
        $meta = wp_meta_read($id);
        if (($meta['state'] ?? '') === 'ready' && is_file($p['www'] . '/' . ($meta['entry'] ?? ''))) {
            return $meta;                                    // кто-то успел раньше
        }

        wp_meta_write($id, ['state' => 'preparing', 'step' => 'download']);

        if (!is_file($p['zip'])) {
            $err = null;
            if (!wp_download((string)$game['game_zip_url'], $p['zip'], $err)) {
                $m = ['state' => 'error', 'error' => 'Загрузка архива: ' . $err];
                wp_meta_write($id, $m);
                return $m;
            }
        }

        wp_meta_write($id, ['state' => 'preparing', 'step' => 'unzip']);

        wp_rrmdir($p['stage']);
        $err = null;
        if (!wp_unzip($p['zip'], $p['stage'], $err)) {
            wp_rrmdir($p['stage']);
            @unlink($p['zip']);                              // битый архив не кэшируем
            $m = ['state' => 'error', 'error' => $err];
            wp_meta_write($id, $m);
            return $m;
        }

        // Расплющивание: не двигаем файлы по одному, а находим настоящий
        // корень контента и переносим его целиком одним rename().
        $contentRoot = wp_content_root($p['stage']);

        $entry = wp_find_entry($contentRoot, (string)($game['game_exec'] ?? ''));
        if ($entry === null) {
            wp_rrmdir($p['stage']);
            $m = ['state' => 'error', 'error' => 'В архиве не найден ни один .html — нечего запускать'];
            wp_meta_write($id, $m);
            return $m;
        }

        $engine = wp_detect_engine($contentRoot, $entry);
        wp_write_game_htaccess($contentRoot, $engine);

        // Атомарная публикация: старое www уезжает в сторону, новое встаёт
        // на место одним rename. Полураспакованного www не существует.
        $old = $p['dir'] . '/.old_' . bin2hex(random_bytes(4));
        if (is_dir($p['www']) && !@rename($p['www'], $old)) wp_rrmdir($p['www']);
        if (!@rename($contentRoot, $p['www'])) {
            wp_rrmdir($p['stage']);
            if (is_dir($old)) @rename($old, $p['www']);
            $m = ['state' => 'error', 'error' => 'Не удалось опубликовать распакованную игру'];
            wp_meta_write($id, $m);
            return $m;
        }
        wp_rrmdir($old);
        wp_rrmdir($p['stage']);

        $meta = [
            'state'    => 'ready',
            'entry'    => $entry,
            'engine'   => $engine,
            'isolated' => in_array($engine, ['godot', 'unity'], true),
            'built_at' => time(),
        ];
        wp_meta_write($id, $meta);
        return $meta;
    } finally {
        flock($lh, LOCK_UN);
        fclose($lh);
    }
}

/** Чистим то, к чему давно не обращались. Игру, которую прямо сейчас
 *  готовят, не трогаем — проверяем лок. */
function wp_cleanup(): void
{
    if (!is_dir(WP_BASE_DIR)) return;
    $ttl = time() - WP_TTL_HOURS * 3600;
    foreach (scandir(WP_BASE_DIR) ?: [] as $d) {
        if ($d === '.' || $d === '..' || !ctype_digit($d)) continue;
        $full = WP_BASE_DIR . '/' . $d;
        if (!is_dir($full)) continue;

        $stamp = $full . '/.last_access';
        $last  = is_file($stamp) ? (int)file_get_contents($stamp) : (int)@filemtime($full);
        if ($last >= $ttl) continue;

        $lock = $full . '/.lock';
        if (is_file($lock)) {
            $lh = fopen($lock, 'c+');
            if ($lh) {
                $free = flock($lh, LOCK_EX | LOCK_NB);
                if ($free) flock($lh, LOCK_UN);
                fclose($lh);
                if (!$free) continue;                        // занята — не трогаем
            }
        }
        wp_rrmdir($full);
    }
}

/* ─────────────────────────── РОУТИНГ ─────────────────────────── */

$game_id = (int)($_GET['id'] ?? 0);
$wantJson = isset($_GET['status']) || isset($_GET['prepare']);

function wp_json(array $d, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($game_id <= 0) {
    $wantJson ? wp_json(['state' => 'error', 'error' => 'Invalid game ID'], 400) : exit('Invalid game ID');
}

$game = (new Game())->getGameById($game_id);
if (!$game) {
    $wantJson ? wp_json(['state' => 'error', 'error' => 'Игра не найдена'], 404) : exit('Game not found');
}
if (empty($game['game_zip_url'])) {
    $wantJson ? wp_json(['state' => 'error', 'error' => 'У игры не загружена веб-сборка'], 404) : exit('Game archive missing');
}

wp_mkdir(WP_BASE_DIR);
$P = wp_paths($game_id);
wp_mkdir($P['dir']);
@file_put_contents($P['dir'] . '/.last_access', time());

if (isset($_GET['prepare'])) {
    @set_time_limit(WP_DL_TIMEOUT + 60);
    @ignore_user_abort(true);
    wp_json(wp_prepare($game_id, $game));
}

$meta = wp_meta_read($game_id);
$ready = ($meta['state'] ?? '') === 'ready'
    && !empty($meta['entry'])
    && is_file($P['www'] . '/' . $meta['entry']);

if (isset($_GET['status'])) {
    wp_json($ready ? $meta : (['state' => $meta['state'] ?? 'absent'] + $meta));
}

// Чистка — раз в ~20 заходов, а не на каждый запрос
if (random_int(1, 20) === 1) wp_cleanup();

/* Путь для iframe: кодируем каждый сегмент отдельно, иначе имена
   с пробелами и кириллицей дают 404. */
$entryUrl = '';
if ($ready) {
    $segs = array_map('rawurlencode', explode('/', $meta['entry']));
    $entryUrl = $P['url'] . '/' . implode('/', $segs);
}

/* Если игра требует изоляции — верхний документ тоже обязан быть
   изолированным, иначе SharedArrayBuffer не появится и в iframe. */
if ($ready && !empty($meta['isolated'])) {
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Embedder-Policy: require-corp');
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>WebPlayer — <?= htmlspecialchars($game['name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="shortcut icon" href="/swad/static/img/logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        :root {
            --p: #c32178;
            --bg: #0b0210;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            height: 100%;
            background: var(--bg);
            color: #f4eef8;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            overflow: hidden;
        }

        #stage {
            position: fixed;
            inset: 0;
        }

        #frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #000;
        }

        .veil {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            text-align: center;
            padding: 24px;
            background:
                radial-gradient(700px 420px at 50% 30%, rgba(195, 33, 120, .22), transparent 70%),
                linear-gradient(180deg, #0b0210, #1a0620);
            z-index: 10;
        }

        .veil.hidden {
            display: none;
        }

        .veil h1 {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: clamp(20px, 4vw, 30px);
            margin: 0;
            letter-spacing: -.02em;
        }

        .veil p {
            margin: 0;
            color: rgba(244, 238, 248, .6);
            font-size: 14px;
            max-width: 460px;
            line-height: 1.55;
        }

        .bar {
            width: min(340px, 76vw);
            height: 4px;
            background: rgba(255, 255, 255, .1);
            overflow: hidden;
        }

        .bar i {
            display: block;
            height: 100%;
            width: 34%;
            background: var(--p);
            animation: slide 1.15s ease-in-out infinite;
        }

        @keyframes slide {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(390%);
            }
        }

        .step {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: rgba(244, 238, 248, .45);
            letter-spacing: .05em;
        }

        .btn {
            appearance: none;
            border: 0;
            padding: 11px 22px;
            background: var(--p);
            color: #fff;
            font: 600 14px 'Inter', sans-serif;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #e8479b;
        }

        .btn--ghost {
            background: rgba(255, 255, 255, .1);
        }

        .row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .err {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: #ff8fa8;
            background: rgba(255, 95, 122, .1);
            padding: 10px 14px;
            max-width: 560px;
            word-break: break-word;
            text-align: left;
        }

        #hud {
            position: fixed;
            right: 12px;
            top: 12px;
            display: flex;
            gap: 8px;
            z-index: 20;
            opacity: .25;
            transition: opacity .18s;
        }

        #hud:hover {
            opacity: 1;
        }

        #hud button {
            appearance: none;
            border: 0;
            width: 34px;
            height: 34px;
            background: rgba(0, 0, 0, .6);
            color: #fff;
            cursor: pointer;
            font-size: 15px;
            line-height: 1;
        }

        #hud button:hover {
            background: var(--p);
        }
    </style>
</head>

<body>
    <div id="stage">
        <?php if ($ready): ?>
            <iframe id="frame"
                src="<?= htmlspecialchars($entryUrl, ENT_QUOTES, 'UTF-8') ?>"
                allow="autoplay; fullscreen; gamepad; accelerometer; gyroscope; xr-spatial-tracking; clipboard-write"
                allowfullscreen></iframe>
        <?php endif; ?>
    </div>

    <div id="hud" <?= $ready ? '' : 'style="display:none"' ?>>
        <button id="btn-full" title="На весь экран">⛶</button>
        <button id="btn-reload" title="Перезапустить">↻</button>
    </div>

    <div class="veil <?= $ready ? 'hidden' : '' ?>" id="veil">
        <h1><?= htmlspecialchars($game['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p id="veil-text">Готовим сборку. Первый запуск дольше — архив скачивается и распаковывается на сервере, дальше игра стартует сразу.</p>
        <div class="bar" id="veil-bar"><i></i></div>
        <div class="step" id="veil-step">инициализация</div>
        <div class="row" id="veil-actions" style="display:none">
            <button class="btn" id="btn-retry">Попробовать снова</button>
            <a class="btn btn--ghost" href="/g/<?= (int)$game_id ?>">К странице игры</a>
        </div>
        <div class="err" id="veil-err" style="display:none"></div>
    </div>

    <script>
        (function () {
            const READY = <?= $ready ? 'true' : 'false' ?>;
            const ID = <?= (int)$game_id ?>;

            const veil = document.getElementById('veil');
            const text = document.getElementById('veil-text');
            const step = document.getElementById('veil-step');
            const bar = document.getElementById('veil-bar');
            const acts = document.getElementById('veil-actions');
            const errBox = document.getElementById('veil-err');

            document.getElementById('btn-full').onclick = () => {
                const el = document.getElementById('stage');
                document.fullscreenElement ? document.exitFullscreen() : el.requestFullscreen?.();
            };
            document.getElementById('btn-reload').onclick = () => {
                const f = document.getElementById('frame');
                if (f) f.src = f.src;
            };
            document.getElementById('btn-retry').onclick = () => location.reload();

            if (READY) return;

            const STEPS = {
                download: 'скачиваем архив…',
                unzip: 'распаковываем…',
                '': 'готовим…'
            };

            function fail(msg) {
                bar.style.display = 'none';
                step.style.display = 'none';
                text.textContent = 'Не удалось запустить игру.';
                acts.style.display = 'flex';
                errBox.style.display = 'block';
                errBox.textContent = msg || 'Неизвестная ошибка';
            }

            // prepare делает работу, poll независимо следит за состоянием —
            // так вкладка переживает обрыв длинного запроса.
            fetch(`?id=${ID}&prepare=1`, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(d => {
                    if (d.state === 'ready') location.reload();
                    else if (d.state === 'error') fail(d.error);
                })
                .catch(() => { /* держимся на поллинге */ });

            let tries = 0;
            const poll = setInterval(() => {
                if (++tries > 240) { clearInterval(poll); fail('Слишком долго. Попробуй перезагрузить страницу.'); return; }
                fetch(`?id=${ID}&status=1`, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => {
                        step.textContent = STEPS[d.step || ''] || STEPS[''];
                        if (d.state === 'ready') { clearInterval(poll); location.reload(); }
                        else if (d.state === 'error') { clearInterval(poll); fail(d.error); }
                    })
                    .catch(() => { });
            }, 1500);
        })();
    </script>
</body>

</html>
