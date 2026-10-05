<?php

set_time_limit(0);

$url = $_GET['url'] ?? '';

if (!$url || !preg_match('~^https?://(www\.)?(youtube\.com/watch\?v=|youtu\.be/)[^&]+~', $url)) {
    http_response_code(400);
    exit('Invalid YouTube URL');
}

$ytDlp = '/usr/local/bin/yt-dlp';
$proxy = 'http://LERmb4:WTT8gq@163.198.212.117:8000';

$dir = '/tmp/ruyt';

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$file = $dir . '/' . bin2hex(random_bytes(16)) . '.mp4';

$cmd =
    escapeshellarg($ytDlp) . ' ' .
    '--proxy ' . escapeshellarg($proxy) . ' ' .
    '--no-playlist ' .
    '--no-warnings ' .
    '-f ' . escapeshellarg('bestvideo[vcodec^=avc1][ext=mp4][height<=720]+bestaudio[ext=m4a]') . ' ' .
    '--merge-output-format mp4 ' .
    '-o ' . escapeshellarg($file) . ' ' .
    escapeshellarg($url) .
    ' 2>&1';

exec($cmd, $output, $code);

if ($code !== 0 || !file_exists($file)) {
    http_response_code(500);
    echo "Download failed\n\n";
    echo implode("\n", $output);
    exit;
}

header('Content-Type: video/mp4');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache');
header('Accept-Ranges: bytes');

readfile($file);

unlink($file);