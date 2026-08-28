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

/* ── Сортировка ──────────────────────────────────────────────────────
 * popularity — НЕ сырой счётчик. Сырой счётчик — это «зал славы»:
 * игра, скачанная год назад, вечно стоит выше свежей, у которой сейчас
 * всплеск. Поэтому берём взвешенную оценку: всё время + утроенное окно
 * за 30 дней + отзывы как признак живой аудитории.
 * price/date/updated — просто поля.
 */
$popScore = function (array $g): float {
    $all = (float)($g['downloads'] ?? 0);
    $recent = (float)($g['downloads_30d'] ?? 0);
    $rated = (float)($g['ratings_count'] ?? 0);
    return $all + $recent * 3 + $rated * 2;
};

/** Время в timestamp; пустые/битые даты уезжают в конец при любом dir. */
$ts = function ($v): ?int {
    if (empty($v) || str_starts_with((string)$v, '0000-')) return null;
    $t = strtotime((string)$v);
    return $t === false ? null : $t;
};

usort($games, function ($a, $b) use ($sort, $dir, $popScore, $ts) {
    $asc = ($dir === 'asc');

    if ($sort === 'date' || $sort === 'updated') {
        $field = $sort === 'date' ? 'release_date' : 'updated_at';
        $va = $ts($a[$field] ?? null);
        $vb = $ts($b[$field] ?? null);
        // Игры без даты всегда внизу, а не «в 1970-м».
        if ($va === null && $vb === null) return 0;
        if ($va === null) return 1;
        if ($vb === null) return -1;
        return $asc ? ($va <=> $vb) : ($vb <=> $va);
    }

    if ($sort === 'price') {
        $va = (float)($a['price'] ?? 0);
        $vb = (float)($b['price'] ?? 0);
        $cmp = $asc ? ($va <=> $vb) : ($vb <=> $va);
        // При равной цене (а бесплатных много) — по популярности,
        // иначе порядок выглядит случайным.
        return $cmp !== 0 ? $cmp : ($popScore($b) <=> $popScore($a));
    }

    // popularity
    $va = $popScore($a);
    $vb = $popScore($b);
    $cmp = $asc ? ($va <=> $vb) : ($vb <=> $va);
    if ($cmp !== 0) return $cmp;
    // Детерминированный тай-брейк: без него игры с нулём (а их большинство
    // в молодом каталоге) шли в произвольном порядке и «популярное»
    // выглядело сломанным.
    $ga = (float)($a['GQI'] ?? 0);
    $gb = (float)($b['GQI'] ?? 0);
    if ($ga !== $gb) return $gb <=> $ga;
    return ($ts($b['release_date'] ?? null) ?? 0) <=> ($ts($a['release_date'] ?? null) ?? 0);
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
        'downloads_30d' => (int)($game['downloads_30d'] ?? 0),
        'release_date'  => $game['release_date'] ?? '',
        'updated_at'    => $game['updated_at'] ?? '',
        'age_rating'    => (int)($game['age_rating'] ?? 0),
    ];
}

echo json_encode([
    'games'  => $result,
    'genres' => array_values($allGenres),
]);