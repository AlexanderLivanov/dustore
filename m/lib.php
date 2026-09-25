<?php
declare(strict_types=1);
/**
 * m/lib.php — общий слой мобильной версии.
 *
 * Главное правило: мобилка НЕ пишет свой SQL к играм. Каталог, главная и
 * поиск идут через тот же Game::queryGames(), что и десктопная витрина.
 * В старой /m каждая вьюха держала свои запросы — и все они разъехались со
 * схемой (moderation_status='approved', таблица wishlist, звёзды из 5 и т.д.).
 * Один источник правды = такие баги невозможны по построению.
 *
 * Карточки рендерит только PHP (m_card). Подгрузка каталога и поиск получают
 * готовый HTML с сервера — в JS нет второй копии разметки, которая бы
 * «чуть-чуть отличалась».
 */

require_once __DIR__ . '/../swad/controllers/game.php';

const M_COVER_FALLBACK = 'data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 320 200%22%3E%3Crect width=%22320%22 height=%22200%22 fill=%22%231b0a26%22/%3E%3Cpath d=%22M136 80h48v40h-48z%22 fill=%22none%22 stroke=%22%236b5478%22 stroke-width=%223%22/%3E%3Ccircle cx=%22150%22 cy=%2294%22 r=%226%22 fill=%22%236b5478%22/%3E%3C/svg%3E';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Путь к ассету с версией по mtime — кэш сбрасывается сам при каждом деплое. */
function m_asset(string $path): string {
    $f = __DIR__ . '/..' . $path;
    return $path . '?v=' . (is_file($f) ? filemtime($f) : 1);
}

function m_price($price): string {
    $p = (float)$price;
    return $p <= 0 ? 'Бесплатно' : number_format($p, 0, ',', ' ') . ' ₽';
}

function m_cover(array $g): string {
    $c = trim((string)($g['path_to_cover'] ?? ''));
    return $c !== '' ? $c : M_COVER_FALLBACK;
}

/** Скриншоты: JSON [{"path": "..."}] или ["..."] (как на десктопе). */
function m_shots($raw, int $max = 8): array {
    $arr = json_decode((string)$raw, true);
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $s) {
        $p = trim((string)(is_array($s) ? ($s['path'] ?? '') : $s));
        if ($p !== '') $out[] = $p;
        if (count($out) >= $max) break;
    }
    return $out;
}

/** Платформы → нормализованный список в нижнем регистре. */
function m_platforms($raw): array {
    return array_values(array_filter(array_map(fn($p) => strtolower(trim($p)), explode(',', (string)$raw))));
}

function m_genres($raw): array {
    return array_values(array_filter(array_map('trim', explode(',', (string)$raw))));
}

/** Рейтинг десятибалльный (game_reviews.rating 1–10). null — оценок нет. */
function m_rating($avg): ?string {
    return ($avg === null || (float)$avg <= 0) ? null : number_format((float)$avg, 1, '.', '');
}

/**
 * Карточка игры. Варианты:
 *   grid — плитка сетки каталога; rail — карточка горизонтальной ленты;
 *   row  — компактная строка списка (библиотека, поиск).
 * data-vt: JS по тапу вешает view-transition-name на обложку, и она
 * «перелетает» в шапку страницы игры (cross-document View Transitions).
 */
function m_card(array $g, string $variant = 'grid'): string {
    $id    = (int)$g['id'];
    $name  = h($g['name'] ?? '');
    $cover = h(m_cover($g));
    $price = m_price($g['price'] ?? 0);
    $free  = (float)($g['price'] ?? 0) <= 0;
    $rate  = m_rating($g['avg_rating'] ?? null);
    $genre = h(m_genres($g['genre'] ?? '')[0] ?? '');
    $studio = h($g['studio_name'] ?? '');

    if ($variant === 'row') {
        $meta = $studio . ($genre ? ' · ' . $genre : '');
        return <<<HTML
<a class="row" href="/m/game/{$id}" data-vt>
  <img class="row-cover" src="{$cover}" alt="" loading="lazy" decoding="async" draggable="false">
  <span class="row-main"><b>{$name}</b><small>{$meta}</small></span>
  <span class="row-side">{$price}</span>
</a>
HTML;
    }

    $rateHtml = $rate ? '<span class="gc-rate"><i class="ti ti-star-filled"></i>' . $rate . '</span>' : '';
    $cls = $variant === 'rail' ? 'gc rail-item' : 'gc';
    $priceCls = $free ? 'gc-price free' : 'gc-price';
    return <<<HTML
<a class="{$cls}" href="/m/game/{$id}" data-vt>
  <span class="gc-media"><img src="{$cover}" alt="" loading="lazy" decoding="async" draggable="false">{$rateHtml}</span>
  <span class="gc-name">{$name}</span>
  <span class="gc-meta"><span class="{$priceCls}">{$price}</span><span class="gc-genre">{$genre}</span></span>
</a>
HTML;
}

/** Выборка витрины через десктопный доменный слой. Ошибка БД → пусто, а не белый экран. */
function m_games(array $filters): array {
    try {
        return (new Game())->queryGames($filters + ['sort' => 'popularity', 'dir' => 'desc', 'limit' => 24, 'offset' => 0]);
    } catch (Throwable $e) {
        error_log('[m] queryGames: ' . $e->getMessage());
        return ['items' => [], 'total' => 0];
    }
}

/**
 * Главная кнопка на странице игры — с учётом того, что человек ДЕРЖИТ
 * ТЕЛЕФОН. Десктопный установщик на телефоне бесполезен, поэтому для
 * ПК-игр вместо «Скачать» — честное «Только для ПК» и вишлист/поделиться.
 * Возвращает ['label','href','kind' => primary|ghost|off,'icon','note'].
 */
function m_cta(array $g, bool $owned): array {
    $id   = (int)$g['id'];
    $plat = m_platforms($g['platforms'] ?? '');
    $file = !empty($g['game_zip_url']);
    $paid = (float)($g['price'] ?? 0) > 0;

    if (($g['vt_status'] ?? '') === 'flagged')
        return ['label' => 'Скачивание заблокировано', 'href' => null, 'kind' => 'off', 'icon' => 'shield-x', 'note' => 'Файл не прошёл антивирусную проверку'];
    if ($paid && !$owned)
        return ['label' => 'Купить · ' . m_price($g['price']), 'href' => '/g/' . $id, 'kind' => 'primary', 'icon' => 'shopping-bag', 'note' => 'Оплата откроется на сайте'];
    if ($file && in_array('web', $plat, true))
        return ['label' => 'Играть в браузере', 'href' => '/webplayer?id=' . $id, 'kind' => 'primary', 'icon' => 'player-play-filled', 'note' => null];
    if ($file && in_array('android', $plat, true))
        return ['label' => 'Скачать APK', 'href' => '/swad/controllers/download_apk.php?game_id=' . $id, 'kind' => 'primary', 'icon' => 'brand-android', 'note' => 'Перед установкой разрешите установку из браузера'];
    if (!$file)
        return ['label' => 'Файл ещё не загружен', 'href' => null, 'kind' => 'off', 'icon' => 'clock', 'note' => null];
    return ['label' => 'Только для ПК', 'href' => null, 'kind' => 'off', 'icon' => 'device-desktop',
            'note' => 'Добавьте в вишлист — скачаете с компьютера'];
}

/** Короткое «3 дня назад» для отзывов. */
function m_ago(?string $dt): string {
    if (!$dt) return '';
    $d = time() - strtotime($dt);
    if ($d < 3600)   return max(1, (int)($d / 60)) . ' мин назад';
    if ($d < 86400)  return (int)($d / 3600) . ' ч назад';
    if ($d < 86400 * 30) return (int)($d / 86400) . ' дн назад';
    return date('d.m.Y', strtotime($dt));
}

/** Склонение: m_plural(5, 'игра', 'игры', 'игр'). */
function m_plural(int $n, string $one, string $few, string $many): string {
    $n10 = $n % 10; $n100 = $n % 100;
    if ($n10 === 1 && $n100 !== 11) return $one;
    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) return $few;
    return $many;
}

/** Пустое состояние одной строкой. */
function m_empty(string $icon, string $title, string $sub = '', ?array $btn = null): string {
    $b = $btn ? '<a class="btn" href="' . h($btn[1]) . '">' . h($btn[0]) . '</a>' : '';
    $s = $sub !== '' ? '<p>' . $sub . '</p>' : '';
    return '<div class="empty"><i class="ti ti-' . h($icon) . '"></i><b>' . h($title) . '</b>' . $s . $b . '</div>';
}

const M_SORTS = ['popularity' => 'Популярные', 'date' => 'Новые', 'price' => 'Цена'];

/** GET-параметры каталога → фильтры Game::queryGames. Общие для страницы и API подгрузки. */
function m_catalog_filters(array $q): array {
    $sort = array_key_exists($q['sort'] ?? '', M_SORTS) ? $q['sort'] : 'popularity';
    return [
        'genre'      => trim((string)($q['genre'] ?? '')) ?: null,
        'sort'       => $sort,
        'dir'        => $sort === 'price' ? 'asc' : 'desc',
        'price_type' => in_array($q['price'] ?? '', ['free', 'paid'], true) ? $q['price'] : 'all',
        'web'        => !empty($q['web']),
        'limit'      => 24,
        'offset'     => max(0, (int)($q['offset'] ?? 0)),
    ];
}
