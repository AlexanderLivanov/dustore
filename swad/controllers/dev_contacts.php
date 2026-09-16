<?php
/**
 * Контакты разработчика для страниц модерации.
 * Использование:
 *   require_once(__DIR__ . '/../swad/controllers/dev_contacts.php');
 *   echo dev_contacts_block($conn, (int)$game_id);
 * Показывать ТОЛЬКО экспертам/админам — внутри персональные данные.
 */

function dev_contacts_fetch(PDO $conn, int $game_id): array {
    $q = $conn->prepare("
        SELECT g.id AS game_id, g.sprint_id,
               s.id AS studio_id, s.name AS studio_name, s.subdomain,
               s.contact_email, s.website AS studio_site, s.tg_link, s.vk_link,
               u.id AS user_id, u.username, u.first_name, u.last_name,
               u.telegram_id, u.telegram_username, u.email, u.vk,
               sp.alias, sp.city, sp.links, sp.participant_type
        FROM games g
        LEFT JOIN studios s ON s.id = g.developer
        LEFT JOIN users   u ON u.id = s.owner_id
        LEFT JOIN sprint_participants sp
               ON sp.user_id = s.owner_id AND sp.sprint_id = g.sprint_id
        WHERE g.id = ? LIMIT 1");
    $q->execute([$game_id]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

function dev_contacts_team(PDO $conn, array $c): array {
    if (empty($c['sprint_id']) || empty($c['user_id'])) return [];
    $q = $conn->prepare("
        SELECT t.team_name, m.member_role, u.username, u.telegram_username, u.telegram_id
        FROM team_members tm
        JOIN sprint_teams  t ON t.id = tm.team_id
        JOIN team_members  m ON m.team_id = t.id
        JOIN users         u ON u.id = m.user_id
        WHERE tm.sprint_id = ? AND tm.user_id = ?
        ORDER BY (m.member_role = 'captain') DESC, u.username");
    $q->execute([(int)$c['sprint_id'], (int)$c['user_id']]);
    return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Free-text из анкеты → кликабельные ссылки. */
function dc_linkify(string $raw): string {
    $s = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    $s = preg_replace(
        '~(https?://[^\s<]+)~u',
        '<a href="$1" target="_blank" rel="noopener noreferrer nofollow" style="color:var(--p);">$1</a>',
        $s);
    $s = preg_replace(
        '~(?<![\w/@])@([A-Za-z0-9_]{4,32})~u',
        '<a href="https://t.me/$1" target="_blank" rel="noopener noreferrer nofollow" style="color:var(--p);">@$1</a>',
        $s);
    return nl2br($s);
}

function dc_row(string $icon, string $label, string $valueHtml): string {
    return '<div style="display:flex;gap:10px;align-items:flex-start;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.05);">'
         . '<span class="material-icons" style="font-size:16px;color:var(--tm);flex-shrink:0;margin-top:2px;">' . $icon . '</span>'
         . '<div style="min-width:0;"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--tm);">' . $label . '</div>'
         . '<div style="font-size:13px;color:var(--ts);word-break:break-word;">' . $valueHtml . '</div></div></div>';
}

function dev_contacts_block(PDO $conn, int $game_id): string {
    $c = dev_contacts_fetch($conn, $game_id);
    if (!$c) return '';
    $e    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $rows = '';

    $displayName = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    $who = $c['alias'] ?: ($c['username'] ?: $displayName);
    if ($who) {
        $extra = $c['username'] ? ' <span style="color:var(--tm);">(@' . $e($c['username']) . ' на сайте)</span>' : '';
        $rows .= dc_row('person', 'Автор заявки', $e($who) . $extra);
    }
    if (!empty($c['studio_name'])) {
        $link = !empty($c['subdomain'])
            ? '<a href="/d/' . $e($c['subdomain']) . '" target="_blank" style="color:var(--p);">' . $e($c['studio_name']) . '</a>'
            : $e($c['studio_name']);
        $rows .= dc_row('apartment', 'Студия', $link);
    }
    if (!empty($c['telegram_username'])) {
        $rows .= dc_row('send', 'Telegram',
            '<a href="https://t.me/' . $e($c['telegram_username']) . '" target="_blank" rel="noopener" style="color:var(--p);">@'
            . $e($c['telegram_username']) . '</a>');
    } elseif (!empty($c['telegram_id'])) {
        $rows .= dc_row('send', 'Telegram',
            '<span style="font-family:monospace;">ID ' . $e($c['telegram_id']) . '</span>'
            . '<span style="color:var(--tm);font-size:11px;"> — юзернейм не указан, писать через бота</span>');
    }
    foreach ([[$c['email'] ?? '', 'Email аккаунта'], [$c['contact_email'] ?? '', 'Email студии']] as [$mail, $lbl]) {
        if ($mail && $mail !== ($c['email'] ?? '') || ($mail && $lbl === 'Email аккаунта')) {
            $rows .= dc_row('mail', $lbl, '<a href="mailto:' . $e($mail) . '" style="color:var(--p);">' . $e($mail) . '</a>');
        }
    }
    foreach ([[$c['tg_link'] ?? '', 'Канал студии'], [$c['vk_link'] ?? '', 'VK студии'],
              [$c['vk'] ?? '', 'VK автора'], [$c['studio_site'] ?? '', 'Сайт']] as [$url, $lbl]) {
        if ($url) $rows .= dc_row('link', $lbl,
            '<a href="' . $e($url) . '" target="_blank" rel="noopener noreferrer nofollow" style="color:var(--p);">' . $e($url) . '</a>');
    }
    if (!empty($c['city'])) $rows .= dc_row('place', 'Город', $e($c['city']));
    if (!empty($c['links'])) $rows .= dc_row('public', 'Ссылки из анкеты джема', dc_linkify($c['links']));

    $team = dev_contacts_team($conn, $c);
    if (count($team) > 1) {
        $names = array_map(function ($m) use ($e) {
            $n = $m['telegram_username']
                ? '<a href="https://t.me/' . $e($m['telegram_username']) . '" target="_blank" rel="noopener" style="color:var(--p);">@' . $e($m['telegram_username']) . '</a>'
                : $e($m['username'] ?: ('id' . $m['telegram_id']));
            return $n . ($m['member_role'] === 'captain' ? ' <span style="font-size:10px;color:var(--tm);">капитан</span>' : '');
        }, $team);
        $rows .= dc_row('groups', 'Команда «' . $e($team[0]['team_name']) . '» · ' . count($team) . ' чел.',
                        implode(', ', $names));
    }

    if ($rows === '') return '';
    return '<div class="card" style="margin-bottom:16px;">'
         . '<div class="card-title"><span class="material-icons">contact_page</span>Контакты разработчика</div>'
         . '<div style="font-size:11px;color:var(--tm);margin-bottom:8px;">Служебная информация. Не пересылать за пределы модерации.</div>'
         . $rows . '</div>';
}