<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

const DB_HOST = '127.0.0.1';
const DB_NAME = 'dustore';
const DB_USER = 'leo';
const DB_PASS = 'HR_o8V4TXI';

const MAX_FILE_SIZE = 500 * 1024 * 1024;

$baseDir = __DIR__;
$uploadDir = $baseDir . '/uploads';
$coverDir = $uploadDir . '/covers';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

if (!is_dir($coverDir)) {
    mkdir($coverDir, 0775, true);
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]
);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS music_tracks (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(500) NOT NULL,
        artist VARCHAR(500) NULL,
        album VARCHAR(500) NULL,
        album_artist VARCHAR(500) NULL,
        genre VARCHAR(255) NULL,
        year INT NULL,
        track_number INT NULL,
        disc_number INT NULL,
        duration DECIMAL(12,3) NULL,
        original_name VARCHAR(1000) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL,
        cover_url VARCHAR(1000) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_stored_name (stored_name),
        KEY idx_title (title(191)),
        KEY idx_artist (artist(191)),
        KEY idx_album (album(191)),
        KEY idx_album_artist (album_artist(191)),
        KEY idx_genre (genre(191))
    ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci
");

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function normalizeValue(mixed $value): ?string
{
    if (is_array($value)) {
        $value = $value[0] ?? null;
    }

    if ($value === null) {
        return null;
    }

    $value = trim((string)$value);

    return $value === '' ? null : $value;
}

function tagValue(array $tags, string ...$names): ?string
{
    foreach ($names as $name) {
        if (
            isset($tags[$name]) &&
            $tags[$name] !== ''
        ) {
            return normalizeValue(
                $tags[$name]
            );
        }
    }

    return null;
}

function integerValue(mixed $value): ?int
{
    if ($value === null) {
        return null;
    }

    if (is_array($value)) {
        $value = $value[0] ?? null;
    }

    if ($value === null || $value === '') {
        return null;
    }

    if (preg_match('/\d+/', (string)$value, $match)) {
        return (int)$match[0];
    }

    return null;
}

function getTrack(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            artist,
            album,
            album_artist,
            genre,
            year,
            track_number,
            disc_number,
            duration,
            original_name,
            stored_name,
            mime_type,
            file_size,
            cover_url,
            created_at
        FROM music_tracks
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $track = $stmt->fetch();

    if (!$track) {
        return null;
    }

    $track['file_url'] =
        'uploads/' .
        rawurlencode($track['stored_name']);

    $track['file_size_mb'] =
        round(
            ((int)$track['file_size']) /
            1024 /
            1024,
            2
        );

    $track['duration'] =
        $track['duration'] !== null
            ? (float)$track['duration']
            : null;

    return $track;
}

$action = $_GET['action'] ?? '';

if ($action === 'upload') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse([
            'success' => false,
            'error' => 'Method not allowed'
        ], 405);
    }

    if (!isset($_FILES['file'])) {
        jsonResponse([
            'success' => false,
            'error' => 'Файл не передан'
        ], 400);
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse([
            'success' => false,
            'error' => 'Ошибка загрузки: ' . $file['error']
        ], 400);
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        jsonResponse([
            'success' => false,
            'error' => 'Некорректный файл'
        ], 400);
    }

    if ((int)$file['size'] > MAX_FILE_SIZE) {
        jsonResponse([
            'success' => false,
            'error' => 'Файл слишком большой'
        ], 400);
    }

    $originalName =
        basename((string)$file['name']);

    $extension =
        strtolower(
            pathinfo(
                $originalName,
                PATHINFO_EXTENSION
            )
        );

    $allowedExtensions = [
        'mp3',
        'm4a',
        'wav',
        'ogg',
        'oga',
        'aac',
        'flac'
    ];

    if (!in_array(
        $extension,
        $allowedExtensions,
        true
    )) {
        jsonResponse([
            'success' => false,
            'error' => 'Неподдерживаемый формат'
        ], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mime =
        $finfo->file(
            $file['tmp_name']
        );

    $allowedMime = [
        'audio/mpeg',
        'audio/mp3',
        'audio/mp4',
        'audio/x-m4a',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
        'application/ogg',
        'audio/aac',
        'audio/flac',
        'application/octet-stream'
    ];

    if (!in_array(
        $mime,
        $allowedMime,
        true
    )) {
        jsonResponse([
            'success' => false,
            'error' => 'Файл не распознан как аудио'
        ], 400);
    }

    $getID3 = new getID3();

    try {
        $info =
            $getID3->analyze(
                $file['tmp_name']
            );
    } catch (Throwable $e) {
        jsonResponse([
            'success' => false,
            'error' => 'Не удалось прочитать метаданные'
        ], 400);
    }

    $tags = [];

    if (isset($info['tags'])) {
        foreach ($info['tags'] as $tagGroup) {
            if (!is_array($tagGroup)) {
                continue;
            }

            foreach ($tagGroup as $key => $value) {
                if (!isset($tags[$key])) {
                    $tags[$key] = $value;
                }
            }
        }
    }

    $title =
        tagValue(
            $tags,
            'title',
            'TITLE'
        );

    $artist =
        tagValue(
            $tags,
            'artist',
            'ARTIST'
        );

    $album =
        tagValue(
            $tags,
            'album',
            'ALBUM'
        );

    $albumArtist =
        tagValue(
            $tags,
            'album_artist',
            'albumartist',
            'ALBUMARTIST'
        );

    $genre =
        tagValue(
            $tags,
            'genre',
            'GENRE'
        );

    $year =
        integerValue(
            tagValue(
                $tags,
                'year',
                'date',
                'YEAR',
                'DATE'
            )
        );

    $trackNumber =
        integerValue(
            tagValue(
                $tags,
                'track_number',
                'track',
                'TRACKNUMBER'
            )
        );

    $discNumber =
        integerValue(
            tagValue(
                $tags,
                'disc_number',
                'disc',
                'DISCNUMBER'
            )
        );

    $duration =
        isset($info['playtime_seconds'])
            ? (float)$info['playtime_seconds']
            : null;

    if (!$title) {
        $title =
            pathinfo(
                $originalName,
                PATHINFO_FILENAME
            );
    }

    $storedName =
        bin2hex(
            random_bytes(24)
        ) . '.' . $extension;

    $destination =
        $uploadDir . '/' . $storedName;

    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {
        jsonResponse([
            'success' => false,
            'error' => 'Не удалось сохранить файл'
        ], 500);
    }

    $coverUrl = null;

    if (
        isset($info['comments']['picture']) &&
        is_array($info['comments']['picture']) &&
        count($info['comments']['picture']) > 0
    ) {
        $picture =
            $info['comments']['picture'][0];

        if (
            isset($picture['data']) &&
            isset($picture['image_mime']) &&
            is_string($picture['data'])
        ) {
            $imageMime =
                strtolower(
                    (string)$picture['image_mime']
                );

            $coverExtensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];

            if (
                isset(
                    $coverExtensions[$imageMime]
                )
            ) {
                $coverName =
                    bin2hex(
                        random_bytes(24)
                    ) .
                    '.' .
                    $coverExtensions[$imageMime];

                $coverPath =
                    $coverDir .
                    '/' .
                    $coverName;

                if (
                    file_put_contents(
                        $coverPath,
                        $picture['data']
                    ) !== false
                ) {
                    $coverUrl =
                        'uploads/covers/' .
                        rawurlencode($coverName);
                }
            }
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO music_tracks (
            title,
            artist,
            album,
            album_artist,
            genre,
            year,
            track_number,
            disc_number,
            duration,
            original_name,
            stored_name,
            mime_type,
            file_size,
            cover_url
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $title,
        $artist,
        $album,
        $albumArtist,
        $genre,
        $year,
        $trackNumber,
        $discNumber,
        $duration,
        $originalName,
        $storedName,
        $mime,
        (int)$file['size'],
        $coverUrl
    ]);

    $id =
        (int)$pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'track' => getTrack(
            $pdo,
            $id
        )
    ]);
}

if ($action === 'search') {
    $query =
        trim(
            (string)(
                $_GET['q'] ?? ''
            )
        );

    $limit =
        min(
            max(
                (int)(
                    $_GET['limit'] ?? 100
                ),
                1
            ),
            200
        );

    if ($query === '') {
        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                artist,
                album,
                album_artist,
                genre,
                year,
                track_number,
                disc_number,
                duration,
                original_name,
                stored_name,
                mime_type,
                file_size,
                cover_url,
                created_at
            FROM music_tracks
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
    } else {
        $search =
            '%' .
            $query .
            '%';

        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                artist,
                album,
                album_artist,
                genre,
                year,
                track_number,
                disc_number,
                duration,
                original_name,
                stored_name,
                mime_type,
                file_size,
                cover_url,
                created_at
            FROM music_tracks
            WHERE
                title LIKE ?
                OR artist LIKE ?
                OR album LIKE ?
                OR album_artist LIKE ?
                OR genre LIKE ?
                OR original_name LIKE ?
            ORDER BY
                artist ASC,
                album ASC,
                track_number ASC,
                title ASC
            LIMIT ?
        ");

        $stmt->execute([
            $search,
            $search,
            $search,
            $search,
            $search,
            $search,
            $limit
        ]);
    }

    $tracks = [];

    while ($track = $stmt->fetch()) {
        $track['file_url'] =
            'uploads/' .
            rawurlencode(
                $track['stored_name']
            );

        $track['file_size_mb'] =
            round(
                ((int)$track['file_size']) /
                1024 /
                1024,
                2
            );

        $track['duration'] =
            $track['duration'] !== null
                ? (float)$track['duration']
                : null;

        $tracks[] = $track;
    }

    jsonResponse([
        'success' => true,
        'tracks' => $tracks
    ]);
}

if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse([
            'success' => false,
            'error' => 'Method not allowed'
        ], 405);
    }

    $id =
        (int)(
            $_POST['id'] ?? 0
        );

    $track =
        getTrack(
            $pdo,
            $id
        );

    if (!$track) {
        jsonResponse([
            'success' => false,
            'error' => 'Трек не найден'
        ], 404);
    }

    $filePath =
        $uploadDir .
        '/' .
        $track['stored_name'];

    if (is_file($filePath)) {
        unlink($filePath);
    }

    if (
        !empty($track['cover_url'])
    ) {
        $coverPath =
            $baseDir .
            '/' .
            $track['cover_url'];

        if (is_file($coverPath)) {
            unlink($coverPath);
        }
    }

    $stmt =
        $pdo->prepare("
            DELETE FROM music_tracks
            WHERE id = ?
        ");

    $stmt->execute([$id]);

    jsonResponse([
        'success' => true
    ]);
}

if ($action !== '') {
    jsonResponse([
        'success' => false,
        'error' => 'Unknown action'
    ], 404);
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, viewport-fit=cover"
    >

    <meta
        name="theme-color"
        content="#08080c"
    >

    <meta
        name="apple-mobile-web-app-capable"
        content="yes"
    >

    <meta
        name="apple-mobile-web-app-status-bar-style"
        content="black-translucent"
    >

    <title>Music Player</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>

<body>

<div class="app">

    <header class="topbar">

        <div class="brand">

            <div class="brand-mark">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <div>
                <div class="brand-name">
                    PLAYER
                </div>

                <div class="brand-subtitle">
                    MUSIC LIBRARY
                </div>
            </div>

        </div>

        <button
            class="file-button"
            id="fileButton"
            type="button"
        >
            <span class="file-icon">＋</span>
            <span>Добавить музыку</span>
        </button>

        <input
            type="file"
            id="fileInput"
            accept=".mp3,.m4a,.wav,.ogg,.oga,.aac,.flac,audio/*"
            multiple
            hidden
        >

    </header>

    <main class="main">

        <section class="content">

            <div class="search-section">

                <div class="search-box">

                    <svg viewBox="0 0 24 24">
                        <circle
                            cx="11"
                            cy="11"
                            r="6"
                        />
                        <path d="M16 16l5 5" />
                    </svg>

                    <input
                        id="searchInput"
                        type="search"
                        placeholder="Поиск трека, исполнителя, альбома..."
                        autocomplete="off"
                    >

                </div>

            </div>

            <div class="library-header">

                <div>

                    <div class="section-title">
                        Медиатека
                    </div>

                    <div
                        class="section-subtitle"
                        id="libraryCount"
                    >
                        Загрузка...
                    </div>

                </div>

            </div>

            <div
                class="library"
                id="library"
            ></div>

        </section>

        <aside class="queue-panel">

            <div class="queue-header">

                <div>

                    <div class="section-title">
                        Очередь
                    </div>

                    <div
                        class="section-subtitle"
                        id="queueCount"
                    >
                        0 треков
                    </div>

                </div>

                <button
                    class="clear-button"
                    id="clearQueueButton"
                    type="button"
                >
                    Очистить
                </button>

            </div>

            <div
                class="queue"
                id="queue"
            ></div>

        </aside>

    </main>

    <div class="player-dock">

        <div class="dock-track">

            <div
                class="dock-cover"
                id="dockCover"
            >
                ♪
            </div>

            <div class="dock-info">

                <div
                    class="dock-title"
                    id="dockTitle"
                >
                    Ничего не играет
                </div>

                <div
                    class="dock-subtitle"
                    id="dockSubtitle"
                >
                    Выберите трек
                </div>

            </div>

        </div>

        <div class="dock-center">

            <div class="dock-controls">

                <button
                    class="dock-button"
                    id="prevButton"
                    type="button"
                >
                    <svg viewBox="0 0 24 24">
                        <path d="M6 5v14" />
                        <path d="M18 6l-8 6 8 6V6z" />
                    </svg>
                </button>

                <button
                    class="dock-play"
                    id="playButton"
                    type="button"
                >

                    <svg
                        class="play-icon"
                        viewBox="0 0 24 24"
                    >
                        <path d="M8 5v14l11-7L8 5z" />
                    </svg>

                    <svg
                        class="pause-icon"
                        viewBox="0 0 24 24"
                    >
                        <path d="M7 5h4v14H7z" />
                        <path d="M13 5h4v14h-4z" />
                    </svg>

                </button>

                <button
                    class="dock-button"
                    id="nextButton"
                    type="button"
                >
                    <svg viewBox="0 0 24 24">
                        <path d="M18 5v14" />
                        <path d="M6 6l8 6-8 6V6z" />
                    </svg>
                </button>

            </div>

            <div class="progress-row">

                <span id="currentTime">
                    0:00
                </span>

                <div
                    class="progress-container"
                    id="progressContainer"
                >

                    <div class="progress">

                        <div
                            class="progress-fill"
                            id="progressFill"
                        ></div>

                        <div
                            class="progress-thumb"
                            id="progressThumb"
                        ></div>

                    </div>

                </div>

                <span id="duration">
                    0:00
                </span>

            </div>

        </div>

        <div class="dock-right">

            <button
                class="dock-button"
                id="shuffleButton"
                type="button"
                title="Перемешать"
            >
                <svg viewBox="0 0 24 24">
                    <path d="M16 3h5v5" />
                    <path d="M4 20L21 3" />
                    <path d="M21 16v5h-5" />
                    <path d="M4 4l5 5" />
                    <path d="M14 14l7 7" />
                </svg>
            </button>

            <button
                class="dock-button"
                id="repeatButton"
                type="button"
                title="Повтор"
            >
                <svg viewBox="0 0 24 24">
                    <path d="M17 2l4 4-4 4" />
                    <path d="M3 11V9a3 3 0 0 1 3-3h15" />
                    <path d="M7 22l-4-4 4-4" />
                    <path d="M21 13v2a3 3 0 0 1-3 3H3" />
                </svg>
            </button>

            <button
                class="dock-button"
                id="volumeButton"
                type="button"
                title="Громкость"
            >
                <svg viewBox="0 0 24 24">
                    <path d="M4 9v6h4l5 4V5L8 9H4z" />
                    <path d="M16 9c1 1 1 5 0 6" />
                    <path d="M18.5 6.5c2.5 2.5 2.5 8.5 0 11" />
                </svg>
            </button>

            <input
                type="range"
                id="volumeSlider"
                min="0"
                max="1"
                step="0.01"
                value="1"
            >

        </div>

    </div>

</div>

<div
    class="upload-overlay"
    id="uploadOverlay"
>

    <div class="upload-box">

        <div class="upload-spinner"></div>

        <div class="upload-title">
            Загружаем музыку
        </div>

        <div
            class="upload-status"
            id="uploadStatus"
        >
            Подготовка...
        </div>

    </div>

</div>

<div
    class="toast"
    id="toast"
></div>

<audio
    id="audio"
    preload="metadata"
></audio>

<script src="player.js"></script>

</body>
</html>