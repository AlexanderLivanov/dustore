<?php
header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/game.php');
$gameController = new Game();
$allGames = $gameController->getLatestGames();

// Параметры
$genre     = $_GET['genre']  ?? null;
$adult     = isset($_GET['adult']) ? (int)$_GET['adult'] : 0;
$sort      = $_GET['sort']   ?? 'popularity';
$dir       = $_GET['dir']    ?? 'desc';
$priceType = $_GET['price_type'] ?? 'all';
$priceMax  = isset($_GET['price_max']) ? (int)$_GET['price_max'] : 5000;

// Только опубликованные и НЕ скрытые
$games = array_filter($allGames, function ($game) {
    return isset($game['status']) && strtolower($game['status']) === 'published'
        && empty($game['hidden']);
});

// 18+
if ($adult) {
    $games = array_filter($games, fn($g) => isset($g['age_rating']) && (int)$g['age_rating'] >= 18);
} else {
    $games = array_filter($games, fn($g) => !isset($g['age_rating']) || (int)$g['age_rating'] < 18);
}

// Сбор жанров (до жанрового/ценового фильтра)
$allGenres = [];
foreach ($games as $game) {
    if (!empty($game['genre'])) {
        foreach (array_map('trim', explode(',', $game['genre'])) as $g) {
            if ($g !== '' && !in_array($g, $allGenres, true)) $allGenres[] = $g;
        }
    }
}
sort($allGenres);

// Жанровый фильтр
if ($genre) {
    $games = array_filter($games, function ($game) use ($genre) {
        if (empty($game['genre'])) return false;
        $genres = array_map('trim', explode(',', $game['genre']));
        return in_array(mb_strtolower($genre), array_map('mb_strtolower', $genres));
    });
}

// Цена
if ($priceType === 'free') {
    $games = array_filter($games, fn($g) => (float)($g['price'] ?? 0) == 0);
} elseif ($priceType === 'paid') {
    $games = array_filter($games, function ($g) use ($priceMax) {
        $p = (float)($g['price'] ?? 0);
        return $p > 0 && $p <= $priceMax;
    });
}

// Сортировка:
//   popularity → скачивания (downloads)
//   price      → цена
//   date       → дата релиза
//   updated    → дата обновления
$sortField = 'downloads';
if ($sort === 'price')   $sortField = 'price';
if ($sort === 'date')    $sortField = 'release_date';
if ($sort === 'updated') $sortField = 'updated_at';

usort($games, function ($a, $b) use ($sortField, $dir) {
    $va = $a[$sortField] ?? 0;
    $vb = $b[$sortField] ?? 0;
    if ($sortField === 'release_date' || $sortField === 'updated_at') {
        $va = $va ? strtotime((string)$va) : 0;
        $vb = $vb ? strtotime((string)$vb) : 0;
    } else {
        $va = (float)$va;
        $vb = (float)$vb;
    }
    return $dir === 'asc' ? ($va <=> $vb) : ($vb <=> $va);
});

// Ответ
$result = [];
foreach ($games as $game) {
    $result[] = [
        'id'            => $game['id'],
        'name'          => $game['name'],
        'path_to_cover' => $game['path_to_cover'] ?? '',
        'price'         => (float)($game['price'] ?? 0),
        'downloads'     => (int)($game['downloads'] ?? 0),
        'release_date'  => $game['release_date'] ?? '',
        'updated_at'    => $game['updated_at'] ?? '',
        'age_rating'    => (int)($game['age_rating'] ?? 0),
    ];
}

echo json_encode([
    'games'  => $result,
    'genres' => array_values($allGenres),
]);