<?php

$id = $_GET['id'] ?? '';

if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $id)) {
    http_response_code(400);
    exit('Invalid video ID');
}

$proxy = 'http://LERmb4:WTT8gq@163.198.212.117:8000';
$ytDlp = '/usr/local/bin/yt-dlp';
$video = 'https://www.youtube.com/watch?v=' . $id;

function getUrl($ytDlp, $proxy, $format, $video)
{
    $cmd =
        escapeshellarg($ytDlp) . ' ' .
        '--proxy ' . escapeshellarg($proxy) . ' ' .
        '--no-warnings ' .
        '--no-playlist ' .
        '-f ' . escapeshellarg($format) . ' ' .
        '-g ' .
        escapeshellarg($video) .
        ' 2>&1';

    exec($cmd, $output, $code);

    if ($code !== 0 || empty($output)) {
        return null;
    }

    return trim($output[0]);
}

$videoUrl = getUrl(
    $ytDlp,
    $proxy,
    'bestvideo[ext=mp4]',
    $video
);

$audioUrl = getUrl(
    $ytDlp,
    $proxy,
    'bestaudio[ext=m4a]',
    $video
);

if (!$videoUrl || !$audioUrl) {
    http_response_code(502);
    exit('Could not get media streams');
}

header('Content-Type: video/mp4');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$cmd =
    'ffmpeg ' .
    '-loglevel error ' .
    '-http_proxy ' . escapeshellarg($proxy) . ' ' .
    '-i ' . escapeshellarg($videoUrl) . ' ' .
    '-i ' . escapeshellarg($audioUrl) . ' ' .
    '-map 0:v:0 ' .
    '-map 1:a:0 ' .
    '-c:v copy ' .
    '-c:a aac ' .
    '-b:a 128k ' .
    '-movflags frag_keyframe+empty_moov ' .
    '-f mp4 ' .
    'pipe:1';

passthru($cmd);