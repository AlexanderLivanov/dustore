<?php
$token = "f9LHodD0cOJZzTOYrmOSPOmFafrLTZKMMe82Dd7Hq9Fg5rsyA9bzFu64NoN3mCPB9GvP0THipUc0uHJVYg-8";
$id    = trim($_GET['id'] ?? '135198179');

function req($url, $headers)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

$tests = [
    'Authorization: '       . $token,
    'Authorization: Bearer ' . $token,
    'Authorization: Token ' . $token,
    'X-Auth-Token: '        . $token,
    'access_token в URL'    => null, // handled separately
];

$results = [];

foreach (
    [
        'plain'   => "Authorization: $token",
        'bearer'  => "Authorization: Bearer $token",
        'token'   => "Authorization: Token $token",
        'x-auth'  => "X-Auth-Token: $token",
    ] as $key => $header
) {
    $results[$key] = [
        'header' => $header,
        'me'     => req("https://platform-api.max.ru/me", [$header]),
        'chats'  => req("https://platform-api.max.ru/chats?count=5", [$header]),
        'msgs'   => req("https://platform-api.max.ru/messages?chat_id={$id}&count=5", [$header]),
    ];
}

// URL param variant
$results['url_param'] = [
    'header' => 'access_token в URL',
    'me'     => req("https://platform-api.max.ru/me?access_token=$token", []),
    'chats'  => req("https://platform-api.max.ru/chats?count=5&access_token=$token", []),
    'msgs'   => req("https://platform-api.max.ru/messages?chat_id={$id}&count=5&access_token=$token", []),
];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MAX API Debug v2</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 16px
        }

        h1 {
            color: #ce9178
        }

        h2 {
            color: #569cd6;
            margin: 16px 0 6px
        }

        h3 {
            color: #9cdcfe;
            margin: 10px 0 4px
        }

        form {
            display: flex;
            gap: 8px;
            margin-bottom: 20px
        }

        input {
            flex: 1;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #555;
            background: #2d2d2d;
            color: #fff;
            font-size: 15px
        }

        button {
            padding: 10px 18px;
            background: #0e8a5f;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px
        }

        .block {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            border-left: 3px solid #555
        }

        .block.ok {
            border-color: #4ec9b0
        }

        .block.err {
            border-color: #f44747
        }

        .ok {
            color: #4ec9b0
        }

        .err {
            color: #f44747
        }

        pre {
            white-space: pre-wrap;
            word-break: break-all;
            font-size: 12px;
            margin-top: 6px;
            max-height: 200px;
            overflow-y: auto;
            background: #1a1a1a;
            padding: 8px;
            border-radius: 4px
        }

        .label {
            color: #808080;
            font-size: 11px
        }
    </style>
</head>

<body>
    <h1>MAX API Debug v2</h1>
    <form method="GET">
        <input name="id" value="<?= htmlspecialchars($id) ?>" placeholder="chat_id">
        <button>Проверить</button>
    </form>

    <?php foreach ($results as $key => $r):
        $meOk = $r['me']['status'] == 200;
    ?>
        <div class="block <?= $meOk ? 'ok' : 'err' ?>">
            <h2><?= $meOk ? '✅' : '❌' ?> <?= htmlspecialchars($r['header']) ?></h2>

            <h3>/me — <span class="<?= $meOk ? 'ok' : 'err' ?>"><?= $r['me']['status'] ?></span></h3>
            <pre><?= htmlspecialchars(json_encode(json_decode($r['me']['body']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

            <h3>/chats — <span class="<?= $r['chats']['status'] == 200 ? 'ok' : 'err' ?>"><?= $r['chats']['status'] ?></span></h3>
            <pre><?= htmlspecialchars(json_encode(json_decode($r['chats']['body']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

            <h3>/messages?chat_id=<?= htmlspecialchars($id) ?> — <span class="<?= $r['msgs']['status'] == 200 ? 'ok' : 'err' ?>"><?= $r['msgs']['status'] ?></span></h3>
            <pre><?= htmlspecialchars(json_encode(json_decode($r['msgs']['body']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
    <?php endforeach ?>
</body>

</html>