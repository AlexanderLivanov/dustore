<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function response($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getS3()
{
    return new S3Client([
        'version' => 'latest',
        'region' => AWS_S3_REGION,
        'credentials' => [
            'key' => AWS_S3_KEY,
            'secret' => AWS_S3_SECRET,
        ],
        'endpoint' => AWS_S3_ENDPOINT,
        'use_path_style_endpoint' => true,
        'http' => [
            'timeout' => 300,
            'connect_timeout' => 30
        ]
    ]);
}

function currentUserKey()
{
    $id = $_SESSION['USERDATA']['id']
        ?? $_SESSION['USERDATA']['user_id']
        ?? $_SESSION['USERDATA']['uid']
        ?? $_SESSION['user_id']
        ?? null;

    if (!$id) {
        response(['ok' => false, 'error' => 'AUTH_REQUIRED'], 401);
    }

    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
}

function extensionFor($mime, $name)
{
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov'
    ];

    if (isset($map[$mime])) {
        return $map[$mime];
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
}

function allowedMime($mime)
{
    return in_array($mime, [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'video/mp4',
        'video/webm',
        'video/quicktime'
    ], true);
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    $s3 = getS3();
    $bucket = AWS_S3_BUCKET_USERCONTENT;

    if ($action === 'init') {
        $user = currentUserKey();

        $name = $_POST['name'] ?? '';
        $mime = $_POST['mime'] ?? '';
        $size = (int)($_POST['size'] ?? 0);

        if ($name === '' || !allowedMime($mime)) {
            response(['ok' => false, 'error' => 'UNSUPPORTED_MEDIA'], 422);
        }

        $maxImage = 25 * 1024 * 1024;
        $maxVideo = 500 * 1024 * 1024;
        $maxSize = str_starts_with($mime, 'video/') ? $maxVideo : $maxImage;

        if ($size <= 0 || $size > $maxSize) {
            response(['ok' => false, 'error' => 'FILE_TOO_LARGE'], 422);
        }

        $id = bin2hex(random_bytes(16));
        $ext = extensionFor($mime, $name);
        $key = 'posts/' . $user . '/' . date('Y/m') . '/' . $id . '.' . $ext;

        $command = $s3->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mime,
            'ACL' => 'public-read'
        ]);

        $request = $s3->createPresignedRequest($command, '+15 minutes');

        $url = (string)$request->getUri();
        $publicUrl = rtrim(AWS_S3_ENDPOINT, '/') . '/' . $bucket . '/' . $key;

        response([
            'ok' => true,
            'key' => $key,
            'url' => $url,
            'public_url' => $publicUrl,
            'mime' => $mime,
            'size' => $size
        ]);
    }

    if ($action === 'complete') {
        currentUserKey();

        $key = $_POST['key'] ?? '';

        if (!$key || strpos($key, 'posts/') !== 0) {
            response(['ok' => false, 'error' => 'INVALID_KEY'], 422);
        }

        $s3->headObject([
            'Bucket' => $bucket,
            'Key' => $key
        ]);

        $publicUrl = rtrim(AWS_S3_ENDPOINT, '/') . '/' . $bucket . '/' . $key;

        response([
            'ok' => true,
            'key' => $key,
            'url' => $publicUrl
        ]);
    }

    response(['ok' => false, 'error' => 'UNKNOWN_ACTION'], 400);

} catch (AwsException $e) {
    error_log('Media upload S3 error: ' . $e->getMessage());
    response(['ok' => false, 'error' => 'S3_ERROR'], 500);
} catch (Throwable $e) {
    error_log('Media upload error: ' . $e->getMessage());
    response(['ok' => false, 'error' => 'SERVER_ERROR'], 500);
}
