<?php
declare(strict_types=1);
/**
 * chat/_push_config.php — единый источник VAPID-ключей для PHP и Node-воркера.
 *
 * Раньше ключи искались в разных местах: PHP — в /etc/dustore/push.env
 * (на Windows/XAMPP такого пути нет → window.VAPID_PUBLIC пустой → подписка
 * молча не создавалась), воркер — только в переменных окружения, старый
 * Express-сервер api/push — в своём .env. Три места = три шанса разъехаться.
 *
 * Теперь обе стороны читают один и тот же список, первый найденный побеждает:
 *   1. переменные окружения VAPID_PUBLIC / VAPID_PRIVATE / VAPID_SUBJECT / BRIDGE_SECRET
 *   2. файл из PUSH_ENV_FILE
 *   3. <папка над htdocs>/dustore-push.env   ← рекомендуемое место: вне webroot
 *   4. api/push/.env                         ← ключи старого push-сервера (подписки уже на них!)
 *   5. /etc/dustore/push.env
 *   6. константы VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY из swad/pass.php
 * Тот же порядок — в pwa/push-worker.js.
 */
function push_env_files(): array {
    return array_values(array_filter([
        getenv('PUSH_ENV_FILE') ?: null,
        dirname(__DIR__, 2) . '/dustore-push.env',
        dirname(__DIR__) . '/api/push/.env',
        '/etc/dustore/push.env',
    ]));
}

function push_parse_env(string $path): array {
    $out = [];
    foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $out[strtoupper(trim(preg_replace('/^export\s+/', '', $k)))] = trim($v, " \t\"'");
    }
    return $out;
}

/** ['public','private','subject','bridge','source'] — source для диагностики. */
function push_config(): array {
    static $c = null;
    if ($c !== null) return $c;
    $pick = fn(array $e, array $names) => array_reduce($names, fn($acc, $n) => $acc ?: (string)($e[$n] ?? ''), '');
    $c = ['public' => '', 'private' => '', 'subject' => '', 'bridge' => '', 'source' => ''];

    $env = ['VAPID_PUBLIC' => getenv('VAPID_PUBLIC') ?: '', 'VAPID_PRIVATE' => getenv('VAPID_PRIVATE') ?: '',
            'VAPID_SUBJECT' => getenv('VAPID_SUBJECT') ?: '', 'BRIDGE_SECRET' => getenv('BRIDGE_SECRET') ?: ''];
    $sources = [['env', $env]];
    foreach (push_env_files() as $f) if (is_readable($f)) $sources[] = [$f, push_parse_env($f)];
    if (defined('VAPID_PUBLIC_KEY')) $sources[] = ['swad/pass.php', [
        'VAPID_PUBLIC' => VAPID_PUBLIC_KEY, 'VAPID_PRIVATE' => defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : '',
        'VAPID_SUBJECT' => defined('VAPID_SUBJECT') ? VAPID_SUBJECT : '']];

    foreach ($sources as [$name, $e]) {
        $pub = $pick($e, ['VAPID_PUBLIC', 'VAPID_PUBLIC_KEY']);
        if ($pub === '') continue;                         // пара ключей берётся ЦЕЛИКОМ из одного источника
        $c = ['public' => $pub, 'private' => $pick($e, ['VAPID_PRIVATE', 'VAPID_PRIVATE_KEY']),
              'subject' => $pick($e, ['VAPID_SUBJECT']) ?: 'mailto:help@dustore.ru',
              'bridge' => $pick($e, ['BRIDGE_SECRET']), 'source' => $name];
        break;
    }
    return $c;
}
