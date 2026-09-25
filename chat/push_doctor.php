<?php
declare(strict_types=1);

/**
 * chat/push_doctor.php — диагностика Web Push по всей цепочке. Только CLI.
 *
 *   sudo -u www-data php chat/push_doctor.php            проверить всё
 *   sudo -u www-data php chat/push_doctor.php --send=42  + тестовый пуш пользователю 42
 *
 * Запускай от пользователя веб-сервера: главный подозреваемый — права на
 * /etc/dustore/*, и root их проверку всегда проходит.
 *
 * Цепочка, которую проверяем по звеньям:
 *   браузер → push_subscribe.php → push_subscriptions
 *   api.php / NotificationCenter → push_outbox (pending)
 *   pwa/push-worker.js ← http://127.0.0.1/chat/push_outbox.php → push-сервис (FCM/Mozilla/Apple) → sw.js
 * Пуш рвётся молча в любом звене; скрипт говорит, в каком.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$_SERVER['HTTP_HOST'] = 'dustore.ru';          // как push_outbox.php: Database берёт боевые креды
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_bridge.php';
require_once __DIR__ . '/_vapid.php';
require_once __DIR__ . '/push_helpers.php';

$opts   = getopt('', ['send:']);
$sendTo = isset($opts['send']) ? (int)$opts['send'] : 0;
$fails  = 0;

function head(string $t): void { echo "\n\033[1m── {$t}\033[0m\n"; }
function ok(string $t): void   { echo "  \033[32m✔\033[0m {$t}\n"; }
function warn(string $t): void { echo "  \033[33m⚠\033[0m {$t}\n"; }
function bad(string $t): void  { global $fails; $fails++; echo "  \033[31m✘\033[0m {$t}\n"; }
function short(string $k): string { return $k === '' ? '(пусто)' : substr($k, 0, 16) . '…'; }
function b64u(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true); }

/** Публичный ключ из приватного: собираем DER ECPrivateKey (P-256) — OpenSSL сам досчитает точку. */
function vapid_public_from_private(string $priv): ?string {
    $d = b64u($priv);
    if (strlen($d) !== 32) return null;
    $der = "\x30\x31\x02\x01\x01\x04\x20" . $d . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    $k = @openssl_pkey_get_private($pem);
    $ec = $k ? (openssl_pkey_get_details($k)['ec'] ?? null) : null;
    if (!$ec || !isset($ec['x'], $ec['y'])) return null;
    $pt = "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    return rtrim(strtr(base64_encode($pt), '+/', '-_'), '=');
}

/** Кто может читать файл: владелец/группа/права — чтобы сразу было видно «root:root 600». */
function file_access(string $path): string {
    $st = @stat($path);
    if (!$st) return '';
    $own = function_exists('posix_getpwuid') ? (posix_getpwuid($st['uid'])['name'] ?? $st['uid']) : $st['uid'];
    $grp = function_exists('posix_getgrgid') ? (posix_getgrgid($st['gid'])['name'] ?? $st['gid']) : $st['gid'];
    return sprintf('%s:%s %o', $own, $grp, $st['mode'] & 0777);
}

$me = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : get_current_user();
echo "push_doctor — запущен от «{$me}»" . ($me === 'root' ? ' (root читает всё: права проверяй через sudo -u www-data)' : '') . "\n";

/* ─── 1. Ключи VAPID ─────────────────────────────────────────────────────── */
head('1. Ключи VAPID');

$envFile = getenv('PUSH_ENV_FILE') ?: '/etc/dustore/push.env';
$file = [];
if (!file_exists($envFile)) {
    warn("{$envFile} нет");
} elseif (!is_readable($envFile)) {
    bad("{$envFile} есть, но «{$me}» не может его прочитать (" . file_access($envFile) . ") — сайт возьмёт ключ из pass.php, а воркер из этого файла. "
        . "Дай группе веб-сервера чтение: chgrp www-data {$envFile} && chmod 640 {$envFile}");
} else {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#') continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $file[strtoupper(trim(preg_replace('/^export\s+/', '', $k)))] = trim($v, " \t\"'");
    }
    ok("{$envFile} читается (" . file_access($envFile) . ')');
}
$filePub  = $file['VAPID_PUBLIC'] ?? $file['VAPID_PUBLIC_KEY'] ?? '';
$filePriv = $file['VAPID_PRIVATE'] ?? $file['VAPID_PRIVATE_KEY'] ?? '';

$site  = vapid_public_key();            // ровно то, что chat/index.php и /m/ отдают браузеру
$const = defined('VAPID_PUBLIC_KEY') ? (string)VAPID_PUBLIC_KEY : '';
echo "    сайт отдаёт браузерам:  " . short($site) . "\n";
echo "    push.env VAPID_PUBLIC:  " . short($filePub) . "\n";
echo "    pass.php константа:     " . short($const) . "\n";
if (getenv('VAPID_PUBLIC')) warn('VAPID_PUBLIC задан в окружении этого шелла — у Apache/php-fpm окружение другое, не обманись');

if ($site === '') {
    bad('Сайт не отдаёт ключ: кнопка «Включить» ответит «не настроены на сервере»');
} elseif (strlen(b64u($site)) !== 65 || b64u($site)[0] !== "\x04") {
    bad('Ключ сайта не похож на публичный VAPID (нужны 65 байт, начало 0x04) — браузер не подпишется');
} else {
    ok('Ключ сайта по формату верный');
}

if ($filePriv !== '') {
    $derived = vapid_public_from_private($filePriv);
    if ($derived === null) bad('VAPID_PRIVATE в push.env не читается как ключ P-256');
    elseif ($filePub !== '' && $derived !== $filePub) bad('В push.env VAPID_PUBLIC и VAPID_PRIVATE — НЕ пара. Push-сервис ответит 403 на каждую отправку');
    else ok('В push.env публичный и приватный ключ — пара');
    if ($derived && $site !== '' && $derived !== $site) {
        bad('Сайт раздаёт ключ ' . short($site) . ', а воркер подписывает парой от ' . short($derived) . ' — каждая отправка получит 403. '
            . ($site === $filePub
                ? 'Сгенерируй пару заново (npx web-push generate-vapid-keys) и впиши обе строки из одного вывода'
                : 'PHP берёт ключ не из push.env (env веб-сервера или pass.php)'));
    } elseif ($derived && $site === $derived) {
        ok('Ключ сайта совпадает с приватным ключом воркера');
    }
} else {
    warn('VAPID_PRIVATE в push.env не нашёл — пару с ключом сайта сверить нечем. Воркер берёт ключи из своего env (systemd EnvironmentFile)');
}
if ($const !== '' && $site !== '' && $const !== $site) {
    warn('pass.php держит другой (старый?) ключ. Сейчас он не используется, но при потере доступа к push.env сайт молча переключится на него');
}

/* ─── 2. Секрет моста PHP ↔ Node ─────────────────────────────────────────── */
head('2. Секрет моста');
$secretFile = getenv('BRIDGE_SECRET_FILE') ?: '/etc/dustore/bridge.secret';
if (bridge_secret() === '') {
    bad("Секрета нет: ни {$secretFile} (" . (file_exists($secretFile) ? file_access($secretFile) . ', не читается' : 'нет файла') . '), ни env BRIDGE_SECRET. '
        . 'push_outbox.php отвечает воркеру 403');
} else {
    ok("Секрет есть (" . (is_file($secretFile) ? $secretFile . ' ' . file_access($secretFile) : 'env BRIDGE_SECRET') . ')');
}

/* ─── 3. База ────────────────────────────────────────────────────────────── */
head('3. Таблицы');
try {
    $db = (new Database())->connect('dustore');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    bad('Нет соединения с БД: ' . $e->getMessage());
    exit(1);
}

$tables = $db->query("SHOW TABLES LIKE 'push\\_%'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['push_subscriptions', 'push_outbox'] as $t) {
    in_array($t, $tables, true) ? ok("{$t} есть") : bad("{$t} нет — выполни миграцию");
}
if (!in_array('push_subscriptions', $tables, true) || !in_array('push_outbox', $tables, true)) exit(1);

$cols = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                      FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'push_subscriptions'")->fetchAll(PDO::FETCH_ASSOC);
$byName = array_column($cols, null, 'COLUMN_NAME');
$len = (int)($byName['endpoint']['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
if ($len && $len < 500) {
    warn("endpoint — {$byName['endpoint']['COLUMN_TYPE']}: адреса Mozilla/Apple бывают длиннее 255, строгий режим MySQL такую подписку не сохранит. ALTER TABLE push_subscriptions MODIFY endpoint VARCHAR(1024) NOT NULL");
}
foreach ($cols as $c) {
    // push_subscribe.php пишет только user_id, endpoint, p256dh, auth
    if (in_array($c['COLUMN_NAME'], ['id', 'user_id', 'endpoint', 'p256dh', 'auth'], true)) continue;
    if ($c['IS_NULLABLE'] === 'NO' && $c['COLUMN_DEFAULT'] === null && stripos($c['EXTRA'], 'auto_increment') === false) {
        bad("push_subscriptions.{$c['COLUMN_NAME']} NOT NULL без DEFAULT — INSERT из push_subscribe.php падает, подписки не сохраняются");
    }
}
$uniq = $db->query("SHOW INDEX FROM push_subscriptions WHERE Column_name = 'endpoint' AND Non_unique = 0")->fetchAll();
$dups = (int)$db->query("SELECT COUNT(*) - COUNT(DISTINCT endpoint) FROM push_subscriptions")->fetchColumn();
if ($uniq) {
    ok('Уникальный индекс по endpoint есть');
} else {
    // Полный индекс по VARCHAR(n) влезает в лимит InnoDB (3072 байта), только если n*4 <= 3072
    $type = strtolower((string)($byName['endpoint']['DATA_TYPE'] ?? ''));
    $key  = ($type === 'varchar' && $len && $len * 4 <= 3072) ? 'endpoint' : 'endpoint(' . min($len ?: 512, 512) . ')';
    warn("Нет UNIQUE по endpoint" . ($dups ? ", дублей уже {$dups}" : '') . '. Код больше не плодит копии, но индекс стоит добавить:');
    echo "      DELETE s1 FROM push_subscriptions s1 JOIN push_subscriptions s2 ON s1.endpoint = s2.endpoint AND s1.id < s2.id;\n";
    echo "      ALTER TABLE push_subscriptions ADD UNIQUE KEY uq_endpoint ({$key});\n";
}

head('4. Подписки и очередь');
$subs = $db->query("SELECT endpoint FROM push_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
$hosts = [];
foreach ($subs as $ep) { $h = parse_url((string)$ep, PHP_URL_HOST) ?: '?'; $hosts[$h] = ($hosts[$h] ?? 0) + 1; }
arsort($hosts);
$users = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM push_subscriptions")->fetchColumn();
count($subs) ? ok('Подписок: ' . count($subs) . ", пользователей: {$users} — " . implode(', ', array_map(fn($h, $n) => "{$h} ×{$n}", array_keys($hosts), $hosts)))
             : bad('Подписок нет ни одной — браузеры не доходят до push_subscribe.php (смотри консоль браузера: [push] …)');

// Возраст считаем в SQL: created_at пишется через NOW() того же соединения, часы одни
$st = $db->query("SELECT status, COUNT(*) n, MAX(created_at) newest, TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) age
                    FROM push_outbox GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$by = array_column($st, null, 'status');
echo '    очередь: ' . ($st ? implode(', ', array_map(fn($r) => "{$r['status']} {$r['n']}", $st)) : 'пусто') . "\n";
if (!empty($by['pending'])) {
    $age = (int)$by['pending']['age'];
    $age > 30 ? bad("Старейшая задача ждёт {$age} с — воркер не забирает очередь (не запущен или не достучался до outbox, см. п.5). "
                    . 'Задачи старше часа outbox при первом же опросе пометит failed — пачки старых пушей не будет')
              : ok('Очередь разбирается');
}
if (!empty($by['sent'])) ok('Последняя успешная отправка: ' . $by['sent']['newest']);
if (!empty($by['failed'])) {
    warn("Упавших задач: {$by['failed']['n']}, последняя {$by['failed']['newest']} — причину пишет воркер: journalctl -u <юнит воркера> | grep '\\[send\\]'");
}

/* ─── 5. Outbox глазами воркера ──────────────────────────────────────────── */
head('5. Outbox глазами воркера');
// Тот же маршрут, что у воркера: боевое имя, соединение прибито к петле
$url  = getenv('OUTBOX_URL') ?: 'https://dustore.ru/chat/push_outbox.php';
$pin  = getenv('OUTBOX_CONNECT');
$pin  = $pin === false ? '127.0.0.1' : $pin;
$uu   = parse_url($url);
$port = (int)($uu['port'] ?? (($uu['scheme'] ?? '') === 'https' ? 443 : 80));
echo "    как ходит воркер: {$url}" . ($pin !== '' ? " через {$pin}" : '') . "\n";
if (!function_exists('curl_init')) {
    warn('Нет ext-curl — проверь руками: curl -si "' . $url . '?secret=…"');
} else {
    $ch = curl_init($url . '?secret=' . rawurlencode(bridge_secret()));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 5, CURLOPT_HEADER => true,
                            CURLOPT_NOPROXY => '*']);          // воркер ходит напрямую, http_proxy из env ему не указ
    if ($pin !== '') {
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => ["{$uu['host']}:{$port}:{$pin}"],
            CURLOPT_SSL_VERIFYPEER => false,        // по петле, как у воркера
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
    }
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs   = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err  = curl_error($ch);
    if ($raw === false) {
        bad("{$url} не отвечает: {$err}");
    } else {
        $headers = substr((string)$raw, 0, $hs);
        $body = substr((string)$raw, $hs);
        $j = json_decode($body, true);
        $snip = mb_substr(preg_replace('/\s+/', ' ', $body), 0, 100);
        if ($code >= 300 && $code < 400) {
            preg_match('/^location:\s*(\S+)/mi', $headers, $m);
            bad("{$url} → {$code} на " . ($m[1] ?? '?') . '. Воркер пойдёт по редиректу уже не с 127.0.0.1 и получит 403. '
                . 'Убери этот адрес из редиректа (https, /m/) или задай воркеру OUTBOX_URL, который отвечает сразу');
        } elseif (!is_array($j)) {
            bad("{$url} → {$code}, но не JSON: {$snip} — на 127.0.0.1 отвечает другой vhost или PHP упал");
        } elseif (empty($j['ok'])) {
            bad("{$url} → {$code} {$snip} — " . ($code === 404
                ? 'отвечает не dustore: на этом адресе другой vhost. Задай воркеру OUTBOX_URL с именем сайта'
                : 'секрет не совпал или запрос пришёл не с 127.0.0.1'));
        } else {
            ok("{$url} отвечает воркеру, задач в выдаче: " . count($j['jobs'] ?? []));
            if (isset($j['vapid']) && $j['vapid'] !== $site) {
                bad('Веб-сервер отдаёт браузерам ключ ' . short((string)$j['vapid']) . ', а в CLI получилось ' . short($site) . ' — у Apache/php-fpm другие env или права на push.env');
            }
        }
    }
}

/* ─── 6. Процессы ────────────────────────────────────────────────────────── */
head('6. Процессы');
$ps = function_exists('shell_exec') ? (string)@shell_exec('ps -eo pid,user,args 2>/dev/null') : '';
if ($ps === '') {
    warn('Не могу посмотреть процессы (shell_exec выключен) — проверь: pgrep -af push-worker');
} else {
    $lines = array_filter(explode("\n", $ps), fn($l) => str_contains($l, 'node') && !str_contains($l, 'push_doctor'));
    $worker = array_filter($lines, fn($l) => str_contains($l, 'push-worker'));
    $legacy = array_filter($lines, fn($l) => str_contains($l, 'api/push') || preg_match('#push/index\.js#', $l));
    $worker ? ok('push-worker запущен: ' . trim((string)reset($worker))) : bad('push-worker.js не запущен — очередь никто не разбирает. systemctl status <юнит> / node pwa/push-worker.js');
    if ($worker && preg_match('/^\s*\d+\s+root\s/', (string)reset($worker))) {
        warn('Воркер запущен от root вручную: после перезагрузки сервера он не поднимется. Оформи службой systemd (README, раздел «Пуш-уведомления»)');
    }
    if ($legacy) warn('Жив старый сервер api/push/index.js (:3001): ' . trim((string)reset($legacy)) . ' — после этого обновления он больше не нужен');
}

/* ─── 7. Тестовый пуш ────────────────────────────────────────────────────── */
if ($sendTo > 0) {
    head("7. Тестовый пуш пользователю {$sendTo}");
    $mine = $db->prepare("SELECT id, endpoint, created_at FROM push_subscriptions WHERE user_id = ? ORDER BY id DESC");
    $mine->execute([$sendTo]);
    $rows = $mine->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        bad('У пользователя нет подписок — включи уведомления в чате или в профиле /m/ и повтори');
    } else {
        foreach ($rows as $r) echo "    #{$r['id']} " . parse_url($r['endpoint'], PHP_URL_HOST) . " от {$r['created_at']}\n";
        $job = push_enqueue_user($db, $sendTo, 'Проверка пушей', 'Если ты это видишь — вся цепочка работает ✔', '/chat/?system=1');
        echo "    задача #{$job} в очереди, жду воркер до 20 с";
        $q = $db->prepare("SELECT status, attempts FROM push_outbox WHERE id = ?");
        $res = ['status' => 'pending', 'attempts' => 0];
        for ($i = 0; $i < 20; $i++) {
            sleep(1); echo '.';
            $q->execute([$job]);
            $res = $q->fetch(PDO::FETCH_ASSOC) ?: $res;
            if ($res['status'] !== 'pending' || (int)$res['attempts'] > 0) break;
        }
        echo "\n";
        if ($res['status'] === 'sent') {
            ok('Push-сервис принял пуш. Нет баннера — дело в устройстве: открыт и в фокусе сам чат (тогда только звук), '
               . 'уведомления браузера выключены в ОС, «Не беспокоить», на iPhone сайт не добавлен на экран «Домой»');
        } elseif ((int)$res['attempts'] > 0 || $res['status'] === 'failed') {
            bad('Воркер взял задачу, но push-сервис отказал — причина в логе воркера строкой [send] (403 = ключи не пара с подпиской)');
        } else {
            bad('За 20 с воркер задачу не взял — он не запущен или не достучался до outbox (п.5, п.6)');
        }
    }
}

echo "\n" . ($fails ? "\033[31mНайдено проблем: {$fails}\033[0m" : "\033[32mПроблем не найдено\033[0m") . "\n";
exit($fails ? 1 : 0);
