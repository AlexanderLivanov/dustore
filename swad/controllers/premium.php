<?php
/**
 * Dustore Premium — единая точка правды.
 *
 * Статус живёт в users.premium_until (DATETIME по Москве, NULL = нет).
 * «Навсегда» = 2099-12-31, особого случая нет. Срок проверяется на лету,
 * крон для снятия не нужен. Время сравниваем в APP_TZ — не через SQL NOW(),
 * чтобы часовой пояс сервера БД ничего не ломал.
 *
 * Значок рисуем инлайн-SVG без id/градиентов: так он одинаково работает
 * в шапке, в HTML-фрагментах ленты (fx api) и без спрайта на странице.
 * Рамка аватара — стили swad/css/premium.css (подключает header.php).
 * В chat.js тот же значок продублирован константой DP_BADGE.
 */

const DP_FOREVER = '2099-12-31 23:59:59';

function dp_tz(): DateTimeZone
{
    return new DateTimeZone(defined('APP_TZ') ? APP_TZ : 'Europe/Moscow');
}

/** Активен ли премиум по значению users.premium_until. */
function dp_is_premium($until): bool
{
    $until = trim((string)$until);
    if ($until === '' || str_starts_with($until, '0000')) return false;
    try {
        return new DateTimeImmutable($until, dp_tz()) > new DateTimeImmutable('now', dp_tz());
    } catch (Throwable $e) {
        return false;
    }
}

/** Синяя галочка. Пустая строка, если премиума нет. */
function dp_badge($until): string
{
    if (!dp_is_premium($until)) return '';
    return '<svg class="dp-badge" width="16" height="16" viewBox="0 0 24 24" role="img" aria-label="Dustore Premium">'
        . '<title>Dustore Premium</title><circle cx="12" cy="12" r="11" fill="#2aabee"/>'
        . '<path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.2" '
        . 'stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/** Класс рамки аватара: ' dp-ring' или ''. */
function dp_ring($until): string
{
    return dp_is_premium($until) ? ' dp-ring' : '';
}

/** Человекочитаемый срок для админки: «навсегда» / «до 12.11.2026». */
function dp_until_label($until): string
{
    if (!dp_is_premium($until)) return '—';
    $d = new DateTimeImmutable((string)$until, dp_tz());
    return $d->format('Y') >= 2099 ? 'навсегда' : 'до ' . $d->format('d.m.Y');
}
