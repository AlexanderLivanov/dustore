<?php

/**
 * chatid.php — просто показывает chat_id всех кто писал боту
 * Положи рядом с webhook.php и открой в браузере.
 * Предварительно напиши боту любое сообщение в MAX.
 */

$store   = __DIR__ . '/messages_store.json';
$logfile = __DIR__ . '/webhook_log.txt';

$results = [];

// Из messages_store.json (ключ = chat_id)
if (file_exists($store)) {
    $data = json_decode(file_get_contents($store), true) ?: [];
    foreach ($data as $chat_id => $msgs) {
        $last = end($msgs);
        $results[$chat_id] = [
            'chat_id'  => $chat_id,
            'user_id'  => $last['user_id']  ?? '?',
            'name'     => $last['sender']   ?? '?',
            'source'   => 'messages_store.json',
        ];
    }
}

// Из webhook_log.txt — на случай если store пустой
if (file_exists($logfile)) {
    $lines = array_reverse(file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    foreach ($lines as $line) {
        $p = strpos($line, '{');
        if ($p === false) continue;
        $d = json_decode(substr($line, $p), true);
        if (!$d) continue;
        $msg     = $d['message']   ?? $d;
        $sender  = $msg['sender']  ?? [];
        $recip   = $msg['recipient'] ?? [];
        $chat_id = (string)($recip['chat_id'] ?? '');
        $user_id = (string)($sender['user_id'] ?? '');
        $name    = $sender['name'] ?? $sender['username'] ?? '';
        if ($chat_id && !isset($results[$chat_id])) {
            $results[$chat_id] = [
                'chat_id'  => $chat_id,
                'user_id'  => $user_id,
                'name'     => $name,
                'source'   => 'webhook_log.txt',
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Chat ID Finder</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: monospace;
            background: #0f0520;
            color: #e0d8f0;
            padding: 20px;
            min-height: 100vh;
        }

        h1 {
            color: #f0a500;
            margin-bottom: 6px;
            font-size: 20px;
        }

        p {
            color: #8870aa;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .card {
            background: #1a0a30;
            border: 1px solid #3a1a5e;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
        }

        .name {
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            margin-bottom: 10px;
        }

        .row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .label {
            color: #8870aa;
            font-size: 12px;
            min-width: 70px;
        }

        .val {
            color: #f0c060;
            font-size: 15px;
            font-weight: bold;
        }

        .copy {
            background: #e8521a;
            border: none;
            border-radius: 6px;
            color: #fff;
            padding: 4px 12px;
            cursor: pointer;
            font-size: 12px;
            font-family: monospace;
        }

        .copy:hover {
            background: #c04010;
        }

        .src {
            color: #6a50a0;
            font-size: 11px;
            margin-top: 6px;
        }

        .empty {
            color: #8870aa;
            text-align: center;
            padding: 40px;
            font-size: 15px;
        }

        .tip {
            background: #120428;
            border-left: 3px solid #f0a500;
            border-radius: 0 8px 8px 0;
            padding: 12px 14px;
            font-size: 13px;
            color: #c8b8e0;
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .tip b {
            color: #f0a500;
        }
    </style>
</head>

<body>
    <h1>🔍 Chat ID Finder</h1>
    <p>Все chat_id пользователей которые писали боту.</p>

    <div class="tip">
        <b>Не видишь себя?</b> Напиши боту любое сообщение в MAX — и обнови эту страницу.<br>
        <b>chat_id</b> — вводишь в поле <b>«chat_id бота»</b> в настройках приложения.<br>
        <b>user_id</b> — вводишь в <b>«Мой user_id»</b>.
    </div>

    <?php if (empty($results)): ?>
        <div class="empty">
            Нет данных.<br><br>
            Напиши боту любое сообщение в MAX, потом обнови страницу.
        </div>
    <?php else: ?>
        <?php foreach ($results as $r): ?>
            <div class="card">
                <div class="name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="row">
                    <span class="label">user_id</span>
                    <span class="val"><?= htmlspecialchars($r['user_id']) ?></span>
                    <button class="copy" onclick="copy('<?= $r['user_id'] ?>', this)">копировать</button>
                </div>
                <div class="row">
                    <span class="label">chat_id</span>
                    <span class="val"><?= htmlspecialchars($r['chat_id']) ?></span>
                    <button class="copy" onclick="copy('<?= $r['chat_id'] ?>', this)">копировать</button>
                </div>
                <div class="src">источник: <?= $r['source'] ?></div>
            </div>
        <?php endforeach ?>
    <?php endif ?>

    <button onclick="location.reload()" style="margin-top:10px;width:100%;padding:12px;
  background:#1a0a30;border:1.5px solid #3a1a5e;border-radius:8px;
  color:#c8b8e0;font-size:14px;cursor:pointer;">↻ Обновить</button>

    <script>
        function copy(val, btn) {
            navigator.clipboard.writeText(val).then(() => {
                btn.textContent = '✓';
                setTimeout(() => btn.textContent = 'копировать', 1500);
            }).catch(() => prompt('Скопируй вручную:', val));
        }
    </script>
</body>

</html>