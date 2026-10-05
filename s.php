<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$query = trim($_GET['q'] ?? '');

if ($query === '' || mb_strlen($query) < 2) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Search query is too short'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$proxy = 'http://LERmb4:WTT8gq@163.198.212.117:8000';
$ytDlp = '/usr/local/bin/yt-dlp';

$search = 'ytsearch8:' . $query;

$cmd =
    escapeshellarg($ytDlp) . ' ' .
    '--proxy ' . escapeshellarg($proxy) . ' ' .
    '--flat-playlist ' .
    '--no-warnings ' .
    '--dump-single-json ' .
    '--skip-download ' .
    escapeshellarg($search) .
    ' 2>/dev/null';

$output = [];
$code = 0;

exec($cmd, $output, $code);

if ($code !== 0 || empty($output)) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Search failed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(implode("\n", $output), true);

if (!$data || empty($data['entries'])) {
    echo json_encode([
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$results = [];

foreach ($data['entries'] as $item) {
    if (empty($item['id'])) {
        continue;
    }

    $id = $item['id'];

    $results[] = [
        'id' => $id,
        'title' => $item['title'] ?? 'Без названия',
        'channel' => $item['channel'] ?? $item['uploader'] ?? '',
        'duration' => $item['duration'] ?? null,
        'thumbnail' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg'
    ];
}

echo json_encode([
    'results' => $results
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);