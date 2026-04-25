
<?php

date_default_timezone_set('Europe/Moscow');

class Time
{
    public function getServerTime()
    {
        return date('d.m.Y H:i', time());
    }
}

function time_ago($datetime)
{
    $now = new DateTime();
    $now->setTimezone(new DateTimeZone('Europe/Moscow'));

    try {
        $ago = new DateTime($datetime);
        $ago->setTimezone(new DateTimeZone('Europe/Moscow'));
    } catch (Exception $e) {
        return 'неверная дата';
    }

    if ($ago > $now) {
        return 'в будущем';
    }

    $diff = $now->diff($ago);
    $intervalInSeconds = $now->getTimestamp() - $ago->getTimestamp();

    // Специальные случаи
    if ($intervalInSeconds < 5) {
        return 'только что';
    } elseif ($intervalInSeconds < 60) {
        return $intervalInSeconds . ' ' . plural_form($intervalInSeconds, ['секунда', 'секунды', 'секунд']) . ' назад';
    }

    // Рассчитываем недели
    $totalDays = $diff->days;
    $weeks = floor($totalDays / 7);
    $remainingDays = $totalDays % 7;

    // Если больше 4 недель (28 дней) - показываем в месяцах
    if ($totalDays > 28) {
        $months = $diff->y * 12 + $diff->m;
        if ($months > 0) {
            return $months . ' ' . plural_form($months, ['месяц', 'месяца', 'месяцев']) . ' назад';
        }
    }

    // Если больше 7 дней - показываем в неделях
    if ($weeks > 0) {
        return $weeks . ' ' . plural_form($weeks, ['неделю', 'недели', 'недель']) . ' назад';
    }

    // Обычные случаи
    $units = [
        'd' => ['день', 'дня', 'дней'],
        'h' => ['час', 'часа', 'часов'],
        'i' => ['минута', 'минуты', 'минут']
    ];

    foreach ($units as $unit => $titles) {
        if ($diff->$unit > 0) {
            $value = $diff->$unit;

            // Специальные случаи для дней
            if ($unit === 'd') {
                if ($value === 1) return 'вчера';
                if ($value === 2) return 'позавчера';
            }

            return $value . ' ' . plural_form($value, $titles) . ' назад';
        }
    }

    return 'только что';
}

function plural_form($number, $titles)
{
    $cases = [2, 0, 1, 1, 1, 2];
    $index = ($number % 100 > 4 && $number % 100 < 20)
        ? 2
        : $cases[min($number % 10, 5)];

    return $titles[$index];
}