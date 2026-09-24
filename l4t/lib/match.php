<?php
declare(strict_types=1);

/**
 * l4t/lib/match.php — подбор: лента «Для тебя», поиск людей, очередь джема
 * и автосборка команд.
 *
 * Лента — не ML, а честный скоринг, который можно объяснить человеку
 * («совпало: Unity, C#»). Для маленькой платформы объяснимость важнее
 * точности: пользователь видит, почему ему это показали, и дозаполняет
 * навыки, если лента мимо.
 */

require_once __DIR__ . '/extras.php';

final class L4TMatch
{
    private L4TX $x;
    private PDO $main;

    public function __construct(L4TX $x, PDO $main)
    {
        $this->x = $x;
        $this->main = $main;
    }

    /* ═════════════════════════════ «ДЛЯ ТЕБЯ» ═════════════════════════════ */

    /**
     * Смешанная лента: заявки под навыки + свободные места в командах джемов
     * + очередь автосборки. Каждый элемент несёт why[] — объяснение.
     */
    public function forYou(int $uid, array $userSkills, string $roleText): array
    {
        $x = $this->x; $l4t = $x->l4t();
        $all = $x->skills();
        $mine = array_keys($userSkills);
        if (!$mine && $roleText !== '') $mine = $x->inferSkills($roleText);   // навыки не заполнены — пробуем по роли
        $myGroups = [];
        foreach ($mine as $s) if (isset($all[$s])) $myGroups[$all[$s]['grp']] = true;

        $items = [];

        /* ── заявки ─────────────────────────────────────────────────── */
        if ($l4t) {
            $bids = $x->rows($l4t, "SELECT * FROM bids WHERE stage = 'active' AND bidder_id <> ?
                                      AND created_at >= NOW() - INTERVAL 90 DAY ORDER BY created_at DESC LIMIT 300", [$uid]);
            $answered = array_map('intval', array_column($x->rows($l4t, "SELECT bid_id FROM responds WHERE user_id = ?", [$uid]), 'bid_id'));
            $tagged = $x->bidSkills(array_column($bids, 'id'));
            foreach ($bids as $b) {
                if (in_array((int)$b['id'], $answered, true)) continue;
                $need = $tagged[(int)$b['id']] ?? $x->inferSkills($b['search_role'] . ' ' . $b['search_spec'] . ' ' . $b['details']);
                $hit = array_values(array_intersect($need, $mine));
                $grpHit = false;
                foreach ($need as $s) if (isset($all[$s]) && isset($myGroups[$all[$s]['grp']]) && $all[$s]['kind'] === 'role') $grpHit = true;
                if (!$hit && !$grpHit) continue;

                $age = (time() - (strtotime((string)$b['created_at']) ?: time())) / 86400;
                $score = count($hit) * 3 + ($grpHit ? 2 : 0) + ($age < 7 ? 2 : ($age < 30 ? 1 : 0)) + (!empty($b['jam_id']) ? 1 : 0)
                       - min(3, (int)($b['responses'] ?? 0) / 5);   // у заявки уже гора откликов — шансов меньше
                $why = array_map(fn($s) => $all[$s]['name'] ?? $s, $hit);
                if (!$why && $grpHit) $why[] = 'смежная роль в вашей области';
                $items[] = ['type' => 'bid', 'score' => $score, 'why' => $why, 'bid' => $b];
            }
        }

        /* ── джемы: свободные места и очередь ────────────────────────── */
        foreach ($this->activeJams() as $s) {
            $registered = (bool)$x->val($this->main, "SELECT 1 FROM sprint_participants WHERE sprint_id = ? AND user_id = ?", [$s['id'], $uid]);
            $inTeam     = (bool)$x->val($this->main, "SELECT 1 FROM team_members WHERE sprint_id = ? AND user_id = ?", [$s['id'], $uid]);
            if ($inTeam) continue;

            $queued = $x->has('jam_queue') ? $x->row($x->l4t(), "SELECT * FROM jam_queue WHERE sprint_id = ? AND user_id = ?", [$s['id'], $uid]) : null;
            if ($x->has('jam_queue')) {
                $inQueue = (int)$x->val($x->l4t(), "SELECT COUNT(*) FROM jam_queue WHERE sprint_id = ? AND team_id IS NULL", [$s['id']]);
                $items[] = ['type' => 'queue', 'score' => 6 + ($registered ? 2 : 0), 'jam' => $s, 'registered' => $registered,
                            'queued' => (bool)$queued, 'in_queue' => $inQueue, 'why' => ['вы без команды']];
            }

            $teams = $x->rows($this->main, "SELECT t.*, (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id) members
                                              FROM sprint_teams t WHERE t.sprint_id = ? AND t.visibility = 'public'
                                            HAVING members >= 1 AND members < t.team_limit ORDER BY t.created_at DESC LIMIT 12", [$s['id']]);
            foreach ($teams as $t) {
                $need = $x->inferSkills((string)$t['team_desc']);
                $hit = array_values(array_intersect($need, $mine));
                $items[] = ['type' => 'team', 'score' => 3 + count($hit) * 3 + ($registered ? 2 : 0), 'jam' => $s, 'team' => $t,
                            'registered' => $registered, 'why' => $hit ? array_map(fn($z) => $all[$z]['name'] ?? $z, $hit) : ['есть свободное место']];
            }
        }

        usort($items, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($items, 0, 30);
    }

    /** Джемы, где ещё можно собрать команду: регистрация, до старта или идёт. */
    public function activeJams(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        $rows = $this->x->rows($this->main, "SELECT * FROM sprints ORDER BY id DESC LIMIT 30");
        if (!$rows) return [];
        $lib = __DIR__ . '/../../swad/controllers/jams/phase_lib.php';
        if (is_readable($lib)) {
            require_once $lib;
        }
        foreach ($rows as $s) {
            $phase = function_exists('jam_phase_ex') ? jam_phase_ex($s)['phase'] : (string)($s['status'] ?? '');
            if (in_array($phase, ['registration', 'pre_jam', 'jam', 'upcoming'], true)) { $s['phase'] = $phase; $cache[] = $s; }
        }
        return $cache;
    }

    /* ═════════════════════════════ ЛЮДИ ═════════════════════════════ */

    /** Поиск специалистов: навык + текст, открытые к предложениям — первыми. */
    public function people(string $skill, string $q, int $viewer, int $limit = 40): array
    {
        $x = $this->x; $l4t = $x->l4t();
        if (!$l4t || !$x->has('profiles')) return [];
        $ids = null;
        if ($skill !== '' && isset($x->skills()[$skill]) && $x->has('user_skills')) {
            $ids = array_map('intval', array_column($x->rows($l4t, "SELECT us.user_id FROM user_skills us JOIN skills s ON s.id = us.skill_id
                                                                      WHERE s.slug = ? ORDER BY us.level DESC LIMIT 500", [$skill]), 'user_id'));
            if (!$ids) return [];
        }
        $where = ["(p.availability IN ('open','hiring','busy'))"];
        $params = [];
        if ($x->has('profiles', 'avail_until')) $where[] = '(p.avail_until IS NULL OR p.avail_until >= CURDATE())';
        if ($ids !== null) { $where[] = 'p.user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; $params = $ids; }
        $prof = $x->rows($l4t, "SELECT p.* FROM profiles p WHERE " . implode(' AND ', $where) . " ORDER BY p.updated_at DESC LIMIT 400", $params);
        if (!$prof) return [];

        $users = $x->users(array_column($prof, 'user_id'), true);
        $needle = mb_strtolower(trim($q));
        $out = [];
        foreach ($prof as $p) {
            $u = $users[(int)$p['user_id']] ?? null;
            if (!$u || (int)$p['user_id'] === $viewer) continue;
            if ($needle !== '' && mb_strpos(mb_strtolower($u['name'] . ' ' . $u['role'] . ' ' . $p['headline']), $needle) === false) continue;
            $out[] = $u + ['id' => (int)$p['user_id'], 'headline' => (string)$p['headline'], 'availability' => (string)$p['availability'],
                           'skills' => array_slice(array_keys($x->userSkills((int)$p['user_id'])), 0, 5)];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /* ═════════════════════════════ ОЧЕРЕДЬ ДЖЕМА ═════════════════════════════ */

    public function queueJoin(int $uid, int $sprintId, string $grp, ?int $tz, string $note): void
    {
        $x = $this->x;
        if (!isset(L4TX::GROUPS[$grp])) throw new InvalidArgumentException('Выберите роль');
        if (!$x->val($this->main, "SELECT 1 FROM sprint_participants WHERE sprint_id = ? AND user_id = ?", [$sprintId, $uid])) {
            throw new InvalidArgumentException('Сначала зарегистрируйтесь на джем');
        }
        if ($x->val($this->main, "SELECT 1 FROM team_members WHERE sprint_id = ? AND user_id = ?", [$sprintId, $uid])) {
            throw new InvalidArgumentException('Вы уже в команде на этот джем');
        }
        $x->l4t()->prepare("INSERT INTO jam_queue (sprint_id, user_id, grp, tz, note) VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE grp = VALUES(grp), tz = VALUES(tz), note = VALUES(note)")
            ->execute([$sprintId, $uid, $grp, $tz, mb_substr(trim(strip_tags($note)), 0, 200) ?: null]);
    }

    public function queueLeave(int $uid, int $sprintId): void
    {
        $this->x->l4t()->prepare("DELETE FROM jam_queue WHERE sprint_id = ? AND user_id = ? AND team_id IS NULL")->execute([$sprintId, $uid]);
    }

    public function queue(int $sprintId): array
    {
        $rows = $this->x->rows($this->x->l4t(), "SELECT * FROM jam_queue WHERE sprint_id = ? ORDER BY created_at", [$sprintId]);
        $u = $this->x->users(array_column($rows, 'user_id'));
        foreach ($rows as &$r) $r['user'] = $u[(int)$r['user_id']] ?? null;
        return $rows;
    }

    /**
     * Автосборка команд — как матчмейкинг в играх.
     *
     *   1. Считаем число команд T = ceil(N / size).
     *   2. Раздаём людей, начиная с САМОЙ РЕДКОЙ роли: трёх звукорежиссёров
     *      надо раскидать по разным командам первыми, программистов хватит всем.
     *   3. Каждого кладём в команду, где (а) его роли ещё нет, (б) людей меньше
     *      всего, (в) средний часовой пояс ближе всего.
     *
     * Жадный алгоритм, O(N·T) — для сотни человек мгновенно и предсказуемо.
     * Внутри роли сортируем по поясу: соседи по времени попадают вместе.
     */
    public static function planTeams(array $people, int $size): array
    {
        $n = count($people);
        if ($n < 2) return [];
        $size = max(2, min(8, $size));
        $T = max(1, (int)ceil($n / $size));

        $byGrp = [];
        foreach ($people as $p) $byGrp[$p['grp']][] = $p;
        uasort($byGrp, fn($a, $b) => count($a) <=> count($b));          // редкие роли — первыми
        foreach ($byGrp as &$g) usort($g, fn($a, $b) => ($a['tz'] ?? 180) <=> ($b['tz'] ?? 180));
        unset($g);

        $teams = array_fill(0, $T, ['members' => [], 'grps' => [], 'tz' => []]);
        foreach ($byGrp as $grp => $list) {
            foreach ($list as $p) {
                $best = null; $bestKey = null;
                foreach ($teams as $i => $t) {
                    if (count($t['members']) >= $size + 1) continue;       // +1 — мягкий лимит, чтобы хвост поместился
                    $tzAvg = $t['tz'] ? array_sum($t['tz']) / count($t['tz']) : ($p['tz'] ?? 180);
                    $key = [isset($t['grps'][$grp]) ? 1 : 0, count($t['members']), abs(($p['tz'] ?? 180) - $tzAvg)];
                    if ($bestKey === null || $key < $bestKey) { $bestKey = $key; $best = $i; }
                }
                if ($best === null) $best = 0;
                $teams[$best]['members'][] = $p;
                $teams[$best]['grps'][$grp] = true;
                if (isset($p['tz'])) $teams[$best]['tz'][] = (int)$p['tz'];
            }
        }
        return array_values(array_filter(array_map(fn($t) => $t['members'], $teams), fn($m) => count($m) >= 2));
    }

    /** Реально создаёт команды в таблицах джема. Только хост джема или админ. */
    public function formTeams(int $sprintId, int $actor, bool $isAdmin, int $size): array
    {
        $x = $this->x;
        $s = $x->row($this->main, "SELECT * FROM sprints WHERE id = ?", [$sprintId]);
        if (!$s) throw new InvalidArgumentException('Джем не найден');
        if (!$isAdmin && (int)($s['host_user_id'] ?? 0) !== $actor) throw new InvalidArgumentException('Собирать команды может только организатор джема');

        // из очереди выкидываем тех, кто за это время сам нашёл команду
        $q = array_values(array_filter($this->queue($sprintId), fn($r) => $r['team_id'] === null
            && !$x->val($this->main, "SELECT 1 FROM team_members WHERE sprint_id = ? AND user_id = ?", [$sprintId, (int)$r['user_id']])));
        $plan = self::planTeams(array_map(fn($r) => ['uid' => (int)$r['user_id'], 'grp' => $r['grp'], 'tz' => $r['tz'] !== null ? (int)$r['tz'] : null], $q), $size);
        if (!$plan) throw new InvalidArgumentException('В очереди меньше двух человек');

        $made = [];
        $pdo = $this->main;
        $pdo->beginTransaction();
        try {
            $n = (int)$x->val($pdo, "SELECT COUNT(*) FROM sprint_teams WHERE sprint_id = ?", [$sprintId]);
            foreach ($plan as $members) {
                // капитан — первый в редкой роли: у него меньше всего шансов потеряться
                $cap = $members[0]['uid'];
                do {
                    $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
                } while ($x->val($pdo, "SELECT 1 FROM sprint_teams WHERE invite_code = ?", [$code]));
                $name = 'Сборная #' . (++$n);
                $pdo->prepare("INSERT INTO sprint_teams (sprint_id, captain_id, team_name, team_desc, visibility, team_limit, invite_code, created_at)
                               VALUES (?, ?, ?, ?, 'private', ?, ?, NOW())")
                    ->execute([$sprintId, $cap, $name, 'Команда собрана автоматически через очередь L4T. Переименуйте её!', count($members) + 1, $code]);
                $tid = (int)$pdo->lastInsertId();
                $ins = $pdo->prepare("INSERT INTO team_members (team_id, user_id, sprint_id, member_role, joined_at) VALUES (?, ?, ?, ?, NOW())");
                foreach ($members as $m) $ins->execute([$tid, $m['uid'], $sprintId, $m['uid'] === $cap ? 'captain' : 'member']);
                $made[] = ['team_id' => $tid, 'name' => $name, 'members' => array_column($members, 'uid')];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $up = $x->l4t()->prepare("UPDATE jam_queue SET team_id = ? WHERE sprint_id = ? AND user_id = ?");
        foreach ($made as $t) {
            foreach ($t['members'] as $u) $up->execute([$t['team_id'], $sprintId, $u]);
            $x->notify($t['members'], 'Вы в команде!', "Джем «{$s['title']}»: {$t['name']}. Познакомьтесь с тиммейтами.", "/l4t/?action=create_team&jam_id=$sprintId");
        }
        return $made;
    }
}
