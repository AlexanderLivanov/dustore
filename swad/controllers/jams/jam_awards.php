<?php
/**
 * Итоги джема: когда объявлять победителей и какие номинации показывать.
 *
 * Единственное место, где это описано. Им пользуются и страница голосования
 * (jams/vote.php), и приём голосов (save_vote.php) — поэтому после момента
 * итогов проголосовать нельзя ни кнопкой, ни прямым запросом.
 *
 * КАК ДОБАВИТЬ ИТОГИ ДЛЯ НОВОГО ДЖЕМА — допишите элемент в jam_awards_config().
 *   reveal_at — момент (Europe/Moscow): до него идёт обратный отсчёт, с него
 *               голосование закрыто, победители видны, работы по убыванию баллов.
 *   Джем подбирается по sprints.voting_end: конфиг подходит, если voting_end
 *   отличается от reveal_at не более чем на сутки (или не задан).
 *   awards    — номинации по порядку:
 *     by = 'votes' — побеждает максимум баллов сообщества (без оценок жюри),
 *                    при равенстве — больше голосовавших, при полном равенстве
 *                    победу делят;
 *     by = 'game'  — выбор организаторов: работа задаётся названием (game_name)
 *                    или id (game_id), баллы не учитываются.
 * Джем без подходящего конфига ведёт себя как раньше: обычное голосование.
 */

function jam_awards_config(): array
{
    return [
        [
            'reveal_at' => '2026-10-01 12:00:00',
            'awards' => [
                ['key' => 'community', 'title' => 'Выбор сообщества Dustore', 'prize' => '70 000 ₽',
                 'by' => 'votes'],
                ['key' => 'kontur', 'title' => 'Выбор К.О.Н.Т.У.Р.', 'prize' => '16 000 ₽',
                 'by' => 'game', 'game_name' => 'Звоните 0-41'],
            ],
        ],
    ];
}

/** Конфиг итогов для джема с таким voting_end (unix или null) либо null. */
function jam_awards_for(?int $votingEnd): ?array
{
    $tz = date_default_timezone_get();
    date_default_timezone_set('Europe/Moscow');
    try {
        foreach (jam_awards_config() as $cfg) {
            $at = strtotime($cfg['reveal_at']);
            if (!$votingEnd || abs($votingEnd - $at) <= 86400) {
                $cfg['reveal_ts'] = $at;
                return $cfg;
            }
        }
    } finally {
        date_default_timezone_set($tz);
    }
    return null;
}

/** Итоги уже объявлены (голосование закрыто для всех, включая жюри). */
function jam_awards_out(?array $cfg, int $now): bool
{
    return $cfg !== null && $now >= $cfg['reveal_ts'];
}

/** Для сравнения названий: регистр, пробелы и знаки не важны. */
function jam_awards_norm(string $s): string
{
    return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s));
}
