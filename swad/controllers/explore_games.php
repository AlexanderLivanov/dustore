<?php
header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/game.php');
$gameController = new Game();
$allGames = $gameController->getLatestGames();

// Параметры
$genre  = $_GET['genre']  ?? null;
$adult  = isset($_GET['adult']) ? (int)$_GET['adult'] : 0;
$sort   = $_GET['sort']   ?? 'popularity';
$dir    = $_GET['dir']    ?? 'desc';

$priceType = $_GET['price_type'] ?? 'all';
$priceMax = isset($_GET['price_max']) ? (int)$_GET['price_max'] : 5000;

// Фильтрация
$games = array_filter($allGames, function ($game) {
    return isset($game['status']) && strtolower($game['status']) === 'published';
});

if ($adult) {
    $games = array_filter($games, function ($game) {
        return isset($game['age_rating']) && intval($game['age_rating']) >= 18;
    });
} else {
    $games = array_filter($games, function ($game) {
        return !isset($game['age_rating']) || intval($game['age_rating']) < 18;
    });
}

// Сбор жанров (до применения ценового и жанрового фильтра)
$allGenres = [];
foreach ($games as $game) {
    if (!empty($game['genre'])) {
        $genres = array_map('trim', explode(',', $game['genre']));
        foreach ($genres as $g) {
            if (!in_array($g, $allGenres)) {
                $allGenres[] = $g;
            }
        }
    }
}
sort($allGenres);

if ($genre) {
    $games = array_filter($games, function ($game) use ($genre) {
        if (empty($game['genre'])) return false;
        $genres = array_map('trim', explode(',', $game['genre']));
        return in_array(strtolower($genre), array_map('strtolower', $genres));
    });
}

// Фильтр по цене
if ($priceType === 'free') {
    $games = array_filter($games, function ($game) {
        return (float)($game['price'] ?? 0) == 0;
    });
} elseif ($priceType === 'paid') {
    $games = array_filter($games, function ($game) use ($priceMax) {
        $price = (float)($game['price'] ?? 0);
        return $price > 0 && $price <= $priceMax;
    });
}

// Сортировка
$sortField = $sort;
if ($sort === 'popularity') $sortField = 'rating';
if ($sort === 'date') $sortField = 'release_date';

usort($games, function ($a, $b) use ($sortField, $dir) {
    $valA = $a[$sortField] ?? 0;
    $valB = $b[$sortField] ?? 0;
    if ($sortField === 'release_date') {
        $valA = strtotime($valA);
        $valB = strtotime($valB);
    }
    if ($dir === 'asc') {
        return $valA <=> $valB;
    } else {
        return $valB <=> $valA;
    }
});

// Ответ
$result = [];
foreach ($games as $game) {
    $result[] = [
        'id'            => $game['id'],
        'name'          => $game['name'],
        'path_to_cover' => $game['path_to_cover'] ?? '',
        'price'         => (float)($game['price'] ?? 0),
        'rating'        => (float)($game['rating'] ?? 0),
        'release_date'  => $game['release_date'] ?? '',
        'age_rating'    => (int)($game['age_rating'] ?? 0),
    ];
}

echo json_encode([
    'games'  => $result,
    'genres' => $allGenres
]);