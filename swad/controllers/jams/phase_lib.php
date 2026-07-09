<?php
/**
 * swad/controllers/jams/phase_lib.php
 * Единый источник правды для фаз спринта, форматирования дат и таймера.
 *
 * Ключевая идея: фазы — НЕ одна ось. Есть основная стадия по таймлайну
 * джема/голосования и НЕЗАВИСИМЫЙ флаг «регистрация открыта».
 * Окна могут пересекаться (продлили регистрацию — джем уже стартовал).
 *
 * Подключение: require_once __DIR__.'/../../swad/controllers/jams/phase_lib.php';
 * (путь подгони под файл; из jams/*.php это '/../swad/controllers/jams/phase_lib.php')
 */
declare(strict_types=1);

const JAM_TZ = 'Europe/Moscow';

function jam_dt(?string $s): ?DateTime {
    return $s ? new DateTime($s, new DateTimeZone(JAM_TZ)) : null;
}

/**
 * Расширенная фаза спринта.
 * Возвращает:
 *   phase    — машинное значение (совместимо со старыми: upcoming/registration/pre_jam/jam/post_jam/voting/finished)
 *   label    — русская подпись основной стадии
 *   badges   — массив подписей для карточки; может быть ДВЕ: ['Уже идёт', 'Регистрация открыта']
 *   reg_open — bool, регистрация открыта прямо сейчас (независимо от стадии)
 */
function jam_phase_ex(array $s): array {
    $now  = new DateTime('now', new DateTimeZone(JAM_TZ));
    $regS = jam_dt($s['registration_start'] ?? null);
    $regE = jam_dt($s['registration_end']   ?? null);
    $jamS = jam_dt($s['jam_start']          ?? null);
    $jamE = jam_dt($s['jam_end']            ?? null);
    $votS = jam_dt($s['voting_start']       ?? null);
    $votE = jam_dt($s['voting_end']         ?? null);

    $regOpen = $regS && $regE && $now >= $regS && $now < $regE;

    // Основная стадия — только по таймлайну джема/голосования
    if ($jamS && $now < $jamS) {
        // до старта джема
        if ($regOpen)                    $phase = 'registration';
        elseif ($regS && $now < $regS)   $phase = 'upcoming';
        else                             $phase = 'pre_jam';
    } elseif ($jamS && $jamE && $now >= $jamS && $now < $jamE) {
        $phase = 'jam';
    } elseif ($votS && $votE && $now >= $votS && $now < $votE) {
        $phase = 'voting';
    } elseif ($jamE && $votS && $now >= $jamE && $now < $votS) {
        $phase = 'post_jam';
    } else {
        $phase = 'finished';
    }

    $labels = [
        'upcoming'     => 'Скоро',
        'registration' => 'Регистрация',
        'pre_jam'      => 'Скоро джем',
        'jam'          => 'Уже идёт',
        'post_jam'     => 'Джем окончен',
        'voting'       => 'Голосование',
        'finished'     => 'Завершён',
    ];

    $badges = [$labels[$phase]];
    // Двойной статус: джем идёт (или скоро), а регистрация всё ещё открыта
    if ($regOpen && $phase !== 'registration') $badges[] = 'Регистрация открыта';

    return [
        'phase'    => $phase,
        'label'    => $labels[$phase],
        'badges'   => $badges,
        'reg_open' => $regOpen,
    ];
}

/**
 * Диапазон дат в человеческом виде:
 *   один день            → «5 июл»
 *   один месяц           → «5–12 июл»
 *   разные месяцы        → «5 июл — 5 авг»
 *   разные годы          → «28 дек 2026 — 3 янв 2027»
 */
function jam_date_range(?string $start, ?string $end): string {
    static $m = [1=>'янв','фев','мар','апр','мая','июн','июл','авг','сен','окт','ноя','дек'];
    $a = jam_dt($start); $b = jam_dt($end);
    if (!$a && !$b) return '—';
    if (!$b) $b = $a;
    if (!$a) $a = $b;

    $d1 = (int)$a->format('j'); $m1 = (int)$a->format('n'); $y1 = (int)$a->format('Y');
    $d2 = (int)$b->format('j'); $m2 = (int)$b->format('n'); $y2 = (int)$b->format('Y');

    if ($y1 !== $y2)               return "{$d1} {$m[$m1]} {$y1} — {$d2} {$m[$m2]} {$y2}";
    if ($m1 !== $m2)               return "{$d1} {$m[$m1]} — {$d2} {$m[$m2]}";
    if ($d1 !== $d2)               return "{$d1}–{$d2} {$m[$m1]}";
    return "{$d1} {$m[$m1]}";
}

/**
 * Таймер до ближайшего события — с русскими подписями
 * (вместо «до registration end»).
 * Возвращает ['str' => '2д 14ч 05м', 'label' => 'до конца регистрации'] либо
 * ['str' => 'Завершён', 'label' => ''].
 */
function jam_countdown(array $s): array {
    $labels = [
        'registration_start' => 'до открытия регистрации',
        'registration_end'   => 'до конца регистрации',
        'jam_start'          => 'до старта джема',
        'jam_end'            => 'до конца джема',
        'voting_start'       => 'до начала голосования',
        'voting_end'         => 'до конца голосования',
    ];
    $now = new DateTime('now', new DateTimeZone(JAM_TZ));
    foreach (array_keys($labels) as $key) {
        $dt = jam_dt($s[$key] ?? null);
        if ($dt && $dt > $now) {
            $diff = $dt->getTimestamp() - $now->getTimestamp();
            $d = intdiv($diff, 86400);
            $h = intdiv($diff % 86400, 3600);
            $mi = intdiv($diff % 3600, 60);
            return [
                'str'   => ($d > 0 ? $d . 'д ' : '') . $h . 'ч ' . $mi . 'м',
                'label' => $labels[$key],
            ];
        }
    }
    return ['str' => 'Завершён', 'label' => ''];
}

/* Русские подписи стадий разработки (для вкладки «Прогресс») */
function jam_dev_stages(): array {
    return [
        'idea'      => 'Идея',
        'prototype' => 'Прототип',
        'core'      => 'Основная механика',
        'content'   => 'Контент',
        'polish'    => 'Полировка',
        'done'      => 'Готово',
    ];
}