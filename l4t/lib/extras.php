<?php
declare(strict_types=1);

/**
 * l4t/lib/extras.php — вторая волна L4T: навыки, опыт, титры, рекомендации,
 * статусы откликов, QR-пропуска, знакомства, мероприятия, ссылки на резюме.
 *
 * Принцип тот же, что в profile.php: каждая таблица проверяется через
 * information_schema. Миграция 007 не накатана — методы возвращают пустоту,
 * страница живёт дальше.
 *
 * Главная идея, ради которой всё это: L4T знает, что человек РЕАЛЬНО делал
 * на Dustore (команды джемов, студии, принятые отклики). Поэтому опыт в
 * студии может быть подтверждён, титры собираются сами, а рекомендации
 * могут писать только те, с кем ты правда работал.
 */

final class L4TX
{
    public const WORK_MODES = ['paid' => 'За деньги', 'share' => 'За долю', 'jam' => 'На джем', 'free' => 'Бесплатно / для портфолио'];
    public const RESP_STATUSES = ['ожидает', 'принят', 'в команде', 'отклонён'];
    public const GROUPS = ['code' => 'Код', 'art' => 'Арт', 'design' => 'Дизайн', 'audio' => 'Звук', 'prod' => 'Продакшн'];

    private PDO $main;
    private ?PDO $l4t;
    private array $cols = [];
    private ?array $skillCache = null;

    public function __construct(PDO $main, ?PDO $l4t)
    {
        $this->main = $main;
        $this->l4t  = $l4t;
        foreach (['main' => $main, 'l4t' => $l4t] as $k => $c) {
            if (!$c) continue;
            try {
                foreach ($c->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                                     WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $this->cols[$k][$r['t']][$r['c']] = true;
                }
            } catch (Throwable $e) {}
        }
    }

    public function has(string $table, ?string $col = null, string $db = 'l4t'): bool
    {
        return $col === null ? !empty($this->cols[$db][$table]) : !empty($this->cols[$db][$table][$col]);
    }

    public function l4t(): ?PDO { return $this->l4t; }

    /* ═════════════════════════════ НАВЫКИ ═════════════════════════════ */

    /** slug => [id, slug, name, kind, grp, aliases[]] */
    public function skills(): array
    {
        if ($this->skillCache !== null) return $this->skillCache;
        $this->skillCache = [];
        if (!$this->has('skills')) return [];
        foreach ($this->rows($this->l4t, "SELECT * FROM skills ORDER BY sort, name") as $s) {
            $s['aliases'] = array_values(array_filter(array_map('trim', explode(',', mb_strtolower((string)$s['aliases'])))));
            $this->skillCache[$s['slug']] = $s;
        }
        return $this->skillCache;
    }

    /** slug => level */
    public function userSkills(int $uid): array
    {
        if (!$this->has('user_skills')) return [];
        $out = [];
        foreach ($this->rows($this->l4t, "SELECT s.slug, us.level FROM user_skills us JOIN skills s ON s.id = us.skill_id
                                            WHERE us.user_id = ? ORDER BY us.level DESC, s.sort", [$uid]) as $r) {
            $out[$r['slug']] = (int)$r['level'];
        }
        return $out;
    }

    public function saveUserSkills(int $uid, array $map): array
    {
        $all = $this->skills();
        $clean = [];
        foreach ($map as $slug => $lvl) {
            if (isset($all[$slug]) && count($clean) < 15) $clean[$slug] = max(1, min(3, (int)$lvl));
        }
        $this->l4t->beginTransaction();
        $this->l4t->prepare("DELETE FROM user_skills WHERE user_id = ?")->execute([$uid]);
        $ins = $this->l4t->prepare("INSERT INTO user_skills (user_id, skill_id, level) VALUES (?, ?, ?)");
        foreach ($clean as $slug => $lvl) $ins->execute([$uid, $all[$slug]['id'], $lvl]);
        $this->l4t->commit();
        return $clean;
    }

    /** bid_id => [slug, ...] */
    public function bidSkills(array $bidIds): array
    {
        $bidIds = array_values(array_unique(array_filter(array_map('intval', $bidIds))));
        if (!$bidIds || !$this->has('bid_skills')) return [];
        $in = implode(',', array_fill(0, count($bidIds), '?'));
        $out = [];
        foreach ($this->rows($this->l4t, "SELECT bs.bid_id, s.slug FROM bid_skills bs JOIN skills s ON s.id = bs.skill_id
                                            WHERE bs.bid_id IN ($in)", $bidIds) as $r) {
            $out[(int)$r['bid_id']][] = $r['slug'];
        }
        return $out;
    }

    public function saveBidSkills(int $bidId, array $slugs): void
    {
        if (!$this->has('bid_skills')) return;
        $all = $this->skills();
        $this->l4t->prepare("DELETE FROM bid_skills WHERE bid_id = ?")->execute([$bidId]);
        $ins = $this->l4t->prepare("INSERT IGNORE INTO bid_skills (bid_id, skill_id) VALUES (?, ?)");
        foreach (array_slice(array_unique($slugs), 0, 8) as $s) if (isset($all[$s])) $ins->execute([$bidId, $all[$s]['id']]);
    }

    /**
     * Навыки из свободного текста по алиасам. Нужны для старых заявок,
     * у которых нет bid_skills: без этого лента «Для тебя» их бы не видела.
     * Сравниваем по началу слова: «анимац» ловит «аниматор» и «анимация».
     */
    public function inferSkills(string $text): array
    {
        $t = ' ' . mb_strtolower(preg_replace('/[^\p{L}\p{N}#+\'.]+/u', ' ', $text)) . ' ';
        $found = [];
        foreach ($this->skills() as $slug => $s) {
            foreach ($s['aliases'] as $a) {
                if ($a !== '' && mb_strpos($t, ' ' . $a) !== false) { $found[] = $slug; break; }
            }
        }
        return $found;
    }

    /** Основная группа человека: по ролям, иначе по инструментам. */
    public function primaryGroup(array $userSkills): ?string
    {
        $all = $this->skills();
        $score = [];
        foreach ($userSkills as $slug => $lvl) {
            if (!isset($all[$slug])) continue;
            $w = ($all[$slug]['kind'] === 'role' ? 3 : 1) * $lvl;
            $score[$all[$slug]['grp']] = ($score[$all[$slug]['grp']] ?? 0) + $w;
        }
        if (!$score) return null;
        arsort($score);
        return array_key_first($score);
    }

    /* ═════════════════════════════ СТУДИИ ═════════════════════════════ */

    /** id студий, где человек владелец или в staff. staff↔users — только через telegram_id, двумя запросами. */
    public function studiosOf(int $uid): array
    {
        static $memo = [];
        if (isset($memo[$uid])) return $memo[$uid];
        $ids = array_map('intval', array_column($this->rows($this->main, "SELECT id FROM studios WHERE owner_id = ?", [$uid]), 'id'));
        $tg = $this->val($this->main, "SELECT telegram_id FROM users WHERE id = ?", [$uid]);
        if ($tg !== null && $tg !== '' && $tg !== 0 && $tg !== '0') {
            foreach ($this->rows($this->main, "SELECT org_id FROM staff WHERE telegram_id = ?", [(string)$tg]) as $r) $ids[] = (int)$r['org_id'];
        }
        return $memo[$uid] = array_values(array_unique($ids));
    }

    private function inStaff(int $uid, int $studioId): bool
    {
        return in_array($studioId, $this->studiosOf($uid), true);
    }

    /* ═════════════════════════════ ОПЫТ ═════════════════════════════ */

    /** Гостям и в резюме — без неподтверждённых заявок на студии Dustore: «я работал в X» без согласия X не показываем. */
    public function experience(int $uid, bool $owner = false): array
    {
        if (!$this->has('work_experience')) return [];
        return $this->rows($this->l4t, "SELECT * FROM work_experience WHERE user_id = ? AND status <> 'rejected'" . ($owner ? '' : " AND status <> 'pending'") . "
                                         ORDER BY (end_ym IS NULL) DESC, COALESCE(end_ym, '9999') DESC, start_ym DESC", [$uid]);
    }

    /** Студии Dustore, где человек числится, а записи об опыте нет — предложим добавить в один клик. */
    public function suggestExperience(int $uid): array
    {
        if (!$this->has('work_experience')) return [];
        $have = array_map('intval', array_column($this->experience($uid, true), 'studio_id'));
        $ids = array_values(array_diff($this->studiosOf($uid), $have));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        return $this->rows($this->main, "SELECT id, name FROM studios WHERE id IN ($in)", $ids);
    }

    public function saveExperience(int $uid, array $d, int $id = 0): array
    {
        $org   = mb_substr(trim(strip_tags((string)($d['org_name'] ?? ''))), 0, 120);
        $title = mb_substr(trim(strip_tags((string)($d['title'] ?? ''))), 0, 120);
        $ym    = fn($v) => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$v) ? (string)$v : null;
        $start = $ym($d['start_ym'] ?? '');
        $end   = !empty($d['current']) ? null : $ym($d['end_ym'] ?? '');
        $desc  = mb_substr(trim(strip_tags((string)($d['description'] ?? ''))), 0, 1000);
        $sid   = (int)($d['studio_id'] ?? 0) ?: null;

        if ($sid) {
            $st = $this->row($this->main, "SELECT id, name, owner_id FROM studios WHERE id = ?", [$sid]);
            if (!$st) $sid = null; else $org = (string)$st['name'];
        }
        if ($org === '' || $title === '') throw new InvalidArgumentException('Укажите организацию и должность');

        /* Статус: в staff — подтверждено фактом членства; владелец студии — сам себе
           подтверждение; иначе ждём владельца. Внешние компании — «со слов». */
        $status = 'self'; $by = null;
        if ($sid) {
            if ((int)$st['owner_id'] === $uid || $this->inStaff($uid, $sid)) { $status = 'verified'; $by = 'staff'; }
            else $status = 'pending';
        }

        if ($id) {
            $own = $this->row($this->l4t, "SELECT studio_id, status FROM work_experience WHERE id = ? AND user_id = ?", [$id, $uid]);
            if (!$own) throw new InvalidArgumentException('Запись не найдена');
            // правка подтверждённой записи в той же студии не сбрасывает подтверждение
            if ($own['status'] === 'verified' && (int)$own['studio_id'] === (int)$sid) { $status = 'verified'; $by = null; }
            $this->l4t->prepare("UPDATE work_experience SET studio_id=?, org_name=?, title=?, start_ym=?, end_ym=?, description=?,
                                    status=?, verified_by=COALESCE(?, verified_by), verified_at=IF(?='verified', COALESCE(verified_at, NOW()), NULL)
                                  WHERE id=? AND user_id=?")
                ->execute([$sid, $org, $title, $start, $end, $desc ?: null, $status, $by, $status, $id, $uid]);
        } else {
            $this->l4t->prepare("INSERT INTO work_experience (user_id, studio_id, org_name, title, start_ym, end_ym, description, status, verified_by, verified_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, IF(?='verified', NOW(), NULL))")
                ->execute([$uid, $sid, $org, $title, $start, $end, $desc ?: null, $status, $by, $status]);
            $id = (int)$this->l4t->lastInsertId();
        }

        if ($status === 'pending') {
            $name = $this->userName($uid);
            $this->notify([(int)$st['owner_id']], 'Подтвердите опыт в студии',
                "$name указал(а) опыт в «{$org}»: $title", '/l4t/?tab=network');
        }
        return ['id' => $id, 'status' => $status];
    }

    public function deleteExperience(int $uid, int $id): void
    {
        $this->l4t->prepare("DELETE FROM work_experience WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
    }

    /** Запросы на подтверждение, которые ждут МЕНЯ как владельца студий. */
    public function pendingVerifications(int $ownerUid): array
    {
        if (!$this->has('work_experience')) return [];
        $mine = array_map('intval', array_column($this->rows($this->main, "SELECT id FROM studios WHERE owner_id = ?", [$ownerUid]), 'id'));
        if (!$mine) return [];
        $in = implode(',', array_fill(0, count($mine), '?'));
        $rows = $this->rows($this->l4t, "SELECT * FROM work_experience WHERE status = 'pending' AND studio_id IN ($in) ORDER BY created_at", $mine);
        $names = $this->users(array_column($rows, 'user_id'));
        foreach ($rows as &$r) $r['user'] = $names[(int)$r['user_id']] ?? null;
        return $rows;
    }

    public function verifyExperience(int $ownerUid, int $id, bool $ok): void
    {
        $r = $this->row($this->l4t, "SELECT * FROM work_experience WHERE id = ? AND status = 'pending'", [$id]);
        if (!$r) throw new InvalidArgumentException('Запрос не найден');
        $own = (int)$this->val($this->main, "SELECT owner_id FROM studios WHERE id = ?", [(int)$r['studio_id']]);
        if ($own !== $ownerUid) throw new InvalidArgumentException('Это не ваша студия');
        $this->l4t->prepare("UPDATE work_experience SET status = ?, verified_by = 'owner', verified_at = NOW() WHERE id = ?")
            ->execute([$ok ? 'verified' : 'rejected', $id]);
        $this->notify([(int)$r['user_id']], $ok ? 'Опыт подтверждён' : 'Опыт не подтверждён',
            "«{$r['org_name']}» — {$r['title']}", '/l4t/');
    }

    /* ═════════════════════════════ ТИТРЫ ═════════════════════════════ */

    /**
     * Собирает титры из фактов: команды джемов, игры студий, принятые отклики.
     * INSERT IGNORE по (user, source, ref) — повторный запуск ничего не дублирует,
     * а скрытые пользователем титры так и остаются скрытыми.
     */
    public function syncCredits(int $uid, string $role): void
    {
        if (!$this->has('credits')) return;
        $role = $role !== '' ? mb_substr($role, 0, 80) : null;
        $ins = $this->l4t->prepare("INSERT IGNORE INTO credits (user_id, game_id, title, role, year, source, ref_id, verified)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

        // джемы: команда = проект
        foreach ($this->rows($this->main, "SELECT tm.team_id, t.team_name, s.title, YEAR(COALESCE(tm.joined_at, t.created_at)) y
                                             FROM team_members tm
                                             JOIN sprint_teams t ON t.id = tm.team_id
                                             JOIN sprints s ON s.id = tm.sprint_id
                                            WHERE tm.user_id = ?", [$uid]) as $r) {
            $ins->execute([$uid, null, "{$r['team_name']} · джем «{$r['title']}»", $role, $r['y'] ?: null, 'jam', (int)$r['team_id']]);
        }

        // игры студий, где человек в команде
        $ids = $this->studiosOf($uid);
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach ($this->rows($this->main, "SELECT g.id, g.name, s.name studio, YEAR(COALESCE(g.release_date, g.created_at)) y
                                                 FROM games g JOIN studios s ON s.id = g.developer
                                                WHERE g.status = 'published' AND g.developer IN ($in)", $ids) as $g) {
                $ins->execute([$uid, (int)$g['id'], (string)$g['name'], $role ?? ('команда ' . $g['studio']), $g['y'] ?: null, 'studio', (int)$g['id']]);
            }
        }

        // L4T: принят «в команду» по отклику
        if ($this->has('responds')) {
            foreach ($this->rows($this->l4t, "SELECT r.id, b.search_role, b.goal, YEAR(r.created_at) y FROM responds r
                                               JOIN bids b ON b.id = r.bid_id
                                              WHERE r.user_id = ? AND r.status = 'в команде'", [$uid]) as $r) {
                $ins->execute([$uid, null, 'Проект через L4T: ' . mb_substr((string)$r['goal'], 0, 100), $r['search_role'], $r['y'], 'l4t', (int)$r['id']]);
            }
        }
    }

    public function credits(int $uid, bool $withHidden = false): array
    {
        if (!$this->has('credits')) return [];
        $rows = $this->rows($this->l4t, "SELECT * FROM credits WHERE user_id = ?" . ($withHidden ? '' : ' AND hidden = 0') .
                                         " ORDER BY COALESCE(year, 0) DESC, id DESC", [$uid]);
        $gids = array_filter(array_map('intval', array_column($rows, 'game_id')));
        $games = [];
        if ($gids) {
            $in = implode(',', array_fill(0, count($gids), '?'));
            foreach ($this->rows($this->main, "SELECT id, name, path_to_cover FROM games WHERE id IN ($in)", array_values($gids)) as $g) $games[(int)$g['id']] = $g;
        }
        foreach ($rows as &$r) $r['game'] = $games[(int)$r['game_id']] ?? null;
        return $rows;
    }

    public function addCredit(int $uid, array $d): int
    {
        $title = mb_substr(trim(strip_tags((string)($d['title'] ?? ''))), 0, 160);
        $gid = (int)($d['game_id'] ?? 0) ?: null;
        if ($gid) {
            $g = $this->row($this->main, "SELECT name FROM games WHERE id = ? AND status = 'published'", [$gid]);
            if ($g) $title = (string)$g['name']; else $gid = null;
        }
        if ($title === '') throw new InvalidArgumentException('Укажите проект');
        $this->l4t->prepare("INSERT INTO credits (user_id, game_id, title, role, year, source, ref_id, verified)
                             VALUES (?, ?, ?, ?, ?, 'manual', ?, 0)")
            ->execute([$uid, $gid, $title, mb_substr(trim(strip_tags((string)($d['role'] ?? ''))), 0, 80) ?: null,
                       ((int)($d['year'] ?? 0)) ?: null, random_int(1, 2000000000)]);
        return (int)$this->l4t->lastInsertId();
    }

    public function toggleCredit(int $uid, int $id, bool $hide): void
    {
        // ручные удаляем, автоматические — скрываем (иначе синк вернёт их обратно)
        $r = $this->row($this->l4t, "SELECT source FROM credits WHERE id = ? AND user_id = ?", [$id, $uid]);
        if (!$r) return;
        if ($r['source'] === 'manual' && $hide) $this->l4t->prepare("DELETE FROM credits WHERE id = ?")->execute([$id]);
        else $this->l4t->prepare("UPDATE credits SET hidden = ? WHERE id = ?")->execute([$hide ? 1 : 0, $id]);
    }

    /** Для страницы игры: кто её делал. Люди студии + ручные титры с этой игрой. */
    public function gameCredits(int $gameId): array
    {
        $people = [];
        if ($this->has('credits')) {
            foreach ($this->rows($this->l4t, "SELECT user_id, role, verified FROM credits WHERE game_id = ? AND hidden = 0", [$gameId]) as $r) {
                $people[(int)$r['user_id']] = ['role' => $r['role'], 'verified' => (int)$r['verified']];
            }
        }
        $dev = (int)$this->val($this->main, "SELECT developer FROM games WHERE id = ?", [$gameId]);
        if ($dev) {
            $owner = (int)$this->val($this->main, "SELECT owner_id FROM studios WHERE id = ?", [$dev]);
            if ($owner && !isset($people[$owner])) $people[$owner] = ['role' => 'основатель студии', 'verified' => 1];
            $uids = array_column($this->rows($this->main, "SELECT uid FROM staff WHERE org_id = ? AND uid IS NOT NULL", [$dev]), 'uid');
            foreach ($uids as $u) if (!isset($people[(int)$u])) $people[(int)$u] = ['role' => null, 'verified' => 1];
        }
        $names = $this->users(array_keys($people));
        $out = [];
        foreach ($people as $u => $p) if (isset($names[$u])) $out[] = $names[$u] + $p + ['id' => $u];
        return $out;
    }

    /* ═════════════════════════════ СОРАБОТА И РЕКОМЕНДАЦИИ ═════════════════════════════ */

    /**
     * С кем человек работал и где: [uid => [type, id, label]].
     * Общая команда джема, общая студия, принятый отклик L4T (в обе стороны).
     */
    public function coworkers(int $uid): array
    {
        $out = [];
        foreach ($this->rows($this->main, "SELECT tm2.user_id, t.id, t.team_name, s.title
                                             FROM team_members tm1
                                             JOIN team_members tm2 ON tm2.team_id = tm1.team_id AND tm2.user_id <> tm1.user_id
                                             JOIN sprint_teams t ON t.id = tm1.team_id
                                             JOIN sprints s ON s.id = t.sprint_id
                                            WHERE tm1.user_id = ?", [$uid]) as $r) {
            $out[(int)$r['user_id']] ??= ['team', (int)$r['id'], "Команда «{$r['team_name']}», джем «{$r['title']}»"];
        }
        foreach ($this->studiosOf($uid) as $sid) {
            $name = (string)$this->val($this->main, "SELECT name FROM studios WHERE id = ?", [$sid]);
            $mates = array_map('intval', array_column($this->rows($this->main, "SELECT owner_id u FROM studios WHERE id = ?", [$sid]), 'u'));
            foreach ($this->rows($this->main, "SELECT telegram_id FROM staff WHERE org_id = ?", [$sid]) as $r) {
                foreach ($this->rows($this->main, "SELECT id FROM users WHERE telegram_id = ?", [(string)$r['telegram_id']]) as $u) $mates[] = (int)$u['id'];
            }
            foreach (array_unique($mates) as $m) if ($m && $m !== $uid) $out[$m] ??= ['studio', $sid, "Студия «{$name}»"];
        }
        if ($this->has('responds')) {
            foreach ($this->rows($this->l4t, "SELECT r.id, r.user_id resp, b.bidder_id owner, b.search_role
                                               FROM responds r JOIN bids b ON b.id = r.bid_id
                                              WHERE r.status IN ('принят','в команде') AND (r.user_id = ? OR b.bidder_id = ?)", [$uid, $uid]) as $r) {
                $other = (int)$r['resp'] === $uid ? (int)$r['owner'] : (int)$r['resp'];
                $out[$other] ??= ['l4t', (int)$r['id'], "Через L4T: «{$r['search_role']}»"];
            }
        }
        return $out;
    }

    /** Тиммейты, которым я ещё не писал рекомендацию — для карточки «Оцени тиммейтов». */
    public function toRecommend(int $uid, int $limit = 6): array
    {
        if (!$this->has('recommendations')) return [];
        $cw = $this->coworkers($uid);
        if (!$cw) return [];
        $done = array_map('intval', array_column($this->rows($this->l4t, "SELECT target_id FROM recommendations WHERE author_id = ?", [$uid]), 'target_id'));
        $left = array_diff_key($cw, array_flip($done));
        $names = $this->users(array_slice(array_keys($left), 0, $limit));
        $out = [];
        foreach ($names as $id => $u) $out[] = $u + ['id' => $id, 'context' => $left[$id][2]];
        return $out;
    }

    public function saveRecommendation(int $author, int $target, array $d): void
    {
        if ($author === $target) throw new InvalidArgumentException('Себя рекомендовать нельзя');
        $cw = $this->coworkers($author);
        if (!isset($cw[$target])) throw new InvalidArgumentException('Рекомендовать можно только тех, с кем вы работали на Dustore');
        [$type, $cid, $label] = $cw[$target];

        $text = mb_substr(trim(strip_tags((string)($d['text'] ?? ''))), 0, 1500);
        if (mb_strlen($text) < 30) throw new InvalidArgumentException('Напишите хотя бы пару предложений — от 30 символов');
        $all = $this->skills();
        $skills = implode(',', array_slice(array_values(array_filter((array)($d['skills'] ?? []), fn($s) => isset($all[$s]))), 0, 2));

        $this->l4t->prepare("INSERT INTO recommendations (author_id, target_id, context_type, context_id, context_label, skills, text, again)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE skills = VALUES(skills), text = VALUES(text), again = VALUES(again)")
            ->execute([$author, $target, $type, $cid, $label, $skills ?: null, $text, !empty($d['again']) ? 1 : 0]);

        $this->notify([$target], 'Новая рекомендация', $this->userName($author) . ' написал(а) вам рекомендацию', '/l4t/');
    }

    public function recommendations(int $target, bool $owner): array
    {
        if (!$this->has('recommendations')) return [];
        $rows = $this->rows($this->l4t, "SELECT * FROM recommendations WHERE target_id = ?" . ($owner ? '' : ' AND hidden = 0') .
                                         " ORDER BY created_at DESC LIMIT 50", [$target]);
        $names = $this->users(array_column($rows, 'author_id'));
        foreach ($rows as &$r) { $r['author'] = $names[(int)$r['author_id']] ?? null; unset($r['again']); }
        return $rows;
    }

    /** «Поработали бы снова»: только агрегат и только от трёх ответов — иначе вычисляется, кто сказал «нет». */
    public function againScore(int $target): ?array
    {
        if (!$this->has('recommendations')) return null;
        $r = $this->row($this->l4t, "SELECT COUNT(*) n, SUM(again) y FROM recommendations WHERE target_id = ?", [$target]);
        $n = (int)($r['n'] ?? 0);
        return $n >= 3 ? ['pct' => (int)round((int)$r['y'] / $n * 100), 'n' => $n] : null;
    }

    public function hideRecommendation(int $target, int $id, bool $hide): void
    {
        $this->l4t->prepare("UPDATE recommendations SET hidden = ? WHERE id = ? AND target_id = ?")->execute([$hide ? 1 : 0, $id, $target]);
    }

    /* ═════════════════════════════ ОТКЛИКИ ═════════════════════════════ */

    public function setRespondStatus(int $ownerUid, int $respondId, string $status): void
    {
        if (!in_array($status, self::RESP_STATUSES, true)) throw new InvalidArgumentException('Неизвестный статус');
        $r = $this->row($this->l4t, "SELECT r.*, b.bidder_id, b.search_role FROM responds r JOIN bids b ON b.id = r.bid_id WHERE r.id = ?", [$respondId]);
        if (!$r || (int)$r['bidder_id'] !== $ownerUid) throw new InvalidArgumentException('Это не отклик на вашу заявку');

        $sql = "UPDATE responds SET status = ?" . ($this->has('responds', 'decided_at') ? ", decided_at = COALESCE(decided_at, IF(? <> 'ожидает', NOW(), NULL))" : '') . " WHERE id = ?";
        $this->l4t->prepare($sql)->execute($this->has('responds', 'decided_at') ? [$status, $status, $respondId] : [$status, $respondId]);

        $msg = [
            'принят'    => 'Ваш отклик приняли',
            'в команде' => 'Вас взяли в команду',
            'отклонён'  => 'По отклику ответили отказом',
        ][$status] ?? null;
        if ($msg) $this->notify([(int)$r['user_id']], $msg, "Заявка «{$r['search_role']}»", '/l4t/?tab=responses');
    }

    /** Медиана часов до ответа на отклик. null — если ответов меньше трёх. */
    public function responseSpeed(int $bidderId): ?float
    {
        if (!$this->has('responds', 'decided_at')) return null;
        $h = array_map('floatval', array_column($this->rows($this->l4t,
            "SELECT TIMESTAMPDIFF(MINUTE, r.created_at, r.decided_at) / 60 h
               FROM responds r JOIN bids b ON b.id = r.bid_id
              WHERE b.bidder_id = ? AND r.decided_at IS NOT NULL
              ORDER BY r.decided_at DESC LIMIT 30", [$bidderId]), 'h'));
        if (count($h) < 3) return null;
        sort($h);
        $m = intdiv(count($h), 2);
        return count($h) % 2 ? $h[$m] : ($h[$m - 1] + $h[$m]) / 2;
    }

    /* ═════════════════════════════ ПРОПУСК (QR) ═════════════════════════════ */

    private const PASS_WINDOW = 30;

    private static function secret(): string
    {
        if (defined('SECRET_KEY') && SECRET_KEY) return (string)SECRET_KEY;
        // Фолбэк: ключ в temp-файле. Переживает рестарты PHP, но не переезд сервера —
        // для пропуска с жизнью 30 секунд это приемлемо.
        $f = sys_get_temp_dir() . '/dustore_l4t_pass.key';
        if (!is_readable($f)) @file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
        return (string)@file_get_contents($f);
    }

    /**
     * Пропуск: uid.окно.подпись. Окно — 30 секунд, как в TOTP. Скриншот
     * пропуска живёт максимум минуту: принимаем текущее и прошлое окно.
     */
    public static function passToken(int $uid, ?int $win = null): string
    {
        $win ??= intdiv(time(), self::PASS_WINDOW);
        $sig = substr(hash_hmac('sha256', "pass|$uid|$win", self::secret()), 0, 20);
        return "DSTR1.$uid.$win.$sig";
    }

    public static function verifyPass(string $token): ?int
    {
        if (!preg_match('/^DSTR1\.(\d{1,10})\.(\d{1,12})\.([0-9a-f]{20})$/', trim($token), $m)) return null;
        $now = intdiv(time(), self::PASS_WINDOW);
        $win = (int)$m[2];
        if ($win !== $now && $win !== $now - 1) return null;
        return hash_equals(self::passToken((int)$m[1], $win), trim($token)) ? (int)$m[1] : null;
    }

    public static function passTtl(): int
    {
        return self::PASS_WINDOW - time() % self::PASS_WINDOW;
    }

    /* ═════════════════════════════ МЕРОПРИЯТИЯ ═════════════════════════════ */

    public function createEvent(int $host, array $d): int
    {
        $title = mb_substr(trim(strip_tags((string)($d['title'] ?? ''))), 0, 120);
        $s = strtotime((string)($d['starts_at'] ?? '')); $e = strtotime((string)($d['ends_at'] ?? ''));
        if ($title === '' || !$s || !$e || $e <= $s) throw new InvalidArgumentException('Название и корректные даты обязательны');
        $this->l4t->prepare("INSERT INTO l4t_events (host_id, title, place, starts_at, ends_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$host, $title, mb_substr(trim(strip_tags((string)($d['place'] ?? ''))), 0, 120) ?: null, date('Y-m-d H:i:s', $s), date('Y-m-d H:i:s', $e)]);
        return (int)$this->l4t->lastInsertId();
    }

    public function hostEvents(int $host): array
    {
        if (!$this->has('l4t_events')) return [];
        return $this->rows($this->l4t, "SELECT e.*, (SELECT COUNT(*) FROM event_checkins c WHERE c.event_id = e.id) checkins
                                          FROM l4t_events e WHERE e.host_id = ? ORDER BY e.starts_at DESC LIMIT 50", [$host]);
    }

    public function attended(int $uid): array
    {
        if (!$this->has('event_checkins')) return [];
        return $this->rows($this->l4t, "SELECT e.id, e.title, e.place, e.starts_at, c.checked_at FROM event_checkins c
                                          JOIN l4t_events e ON e.id = c.event_id WHERE c.user_id = ? ORDER BY c.checked_at DESC", [$uid]);
    }

    /** Отметка на входе. Возвращает карточку гостя для экрана сканера. */
    public function checkin(int $host, int $eventId, string $token): array
    {
        $ev = $this->row($this->l4t, "SELECT * FROM l4t_events WHERE id = ? AND host_id = ?", [$eventId, $host]);
        if (!$ev) throw new InvalidArgumentException('Мероприятие не найдено');
        $uid = self::verifyPass($token);
        if (!$uid) throw new InvalidArgumentException('Пропуск недействителен или устарел — попросите обновить QR');
        $ins = $this->l4t->prepare("INSERT IGNORE INTO event_checkins (event_id, user_id) VALUES (?, ?)");
        $ins->execute([$eventId, $uid]);
        $u = $this->users([$uid])[$uid] ?? ['name' => '#' . $uid, 'avatar' => '', 'handle' => ''];
        return $u + ['id' => $uid, 'repeat' => $ins->rowCount() === 0];
    }

    /* ═════════════════════════════ ЗНАКОМСТВА ═════════════════════════════ */

    /**
     * Обмен визитками: отсканировал QR — оба попадают друг другу в «Знакомства».
     * Если оба сегодня отмечены на одном мероприятии — оно запоминается как «где познакомились».
     */
    public function addContact(int $me, int $other): ?array
    {
        if ($me === $other || !$this->has('contacts')) return null;
        $ev = $this->has('event_checkins') ? $this->row($this->l4t,
            "SELECT e.id, e.title FROM event_checkins a JOIN event_checkins b ON b.event_id = a.event_id AND b.user_id = ?
               JOIN l4t_events e ON e.id = a.event_id
              WHERE a.user_id = ? AND DATE(a.checked_at) = CURDATE() ORDER BY a.checked_at DESC LIMIT 1", [$other, $me]) : null;
        $ins = $this->l4t->prepare("INSERT IGNORE INTO contacts (owner_id, contact_id, event_id) VALUES (?, ?, ?)");
        $ins->execute([$me, $other, $ev['id'] ?? null]);
        $new = $ins->rowCount() > 0;
        $ins->execute([$other, $me, $ev['id'] ?? null]);
        if ($new) $this->notify([$other], 'Новое знакомство', $this->userName($me) . ' добавил(а) вас в знакомства' . ($ev ? " на «{$ev['title']}»" : ''), '/l4t/?tab=network');
        return $ev;
    }

    public function contacts(int $uid): array
    {
        if (!$this->has('contacts')) return [];
        $rows = $this->rows($this->l4t, "SELECT c.*, e.title event_title FROM contacts c LEFT JOIN l4t_events e ON e.id = c.event_id
                                          WHERE c.owner_id = ? ORDER BY c.created_at DESC LIMIT 300", [$uid]);
        $u = $this->users(array_column($rows, 'contact_id'), true);
        foreach ($rows as &$r) $r['user'] = $u[(int)$r['contact_id']] ?? null;
        return array_values(array_filter($rows, fn($r) => $r['user']));
    }

    public function noteContact(int $uid, int $cid, string $note): void
    {
        $this->l4t->prepare("UPDATE contacts SET note = ? WHERE owner_id = ? AND contact_id = ?")
            ->execute([mb_substr(trim(strip_tags($note)), 0, 200) ?: null, $uid, $cid]);
    }

    public function isContact(int $uid, int $other): bool
    {
        return $this->has('contacts') && (bool)$this->val($this->l4t, "SELECT 1 FROM contacts WHERE owner_id = ? AND contact_id = ?", [$uid, $other]);
    }

    /* ═════════════════════════════ ССЫЛКИ НА РЕЗЮМЕ ═════════════════════════════ */

    public function shareLinks(int $uid): array
    {
        if (!$this->has('share_links')) return [];
        return $this->rows($this->l4t, "SELECT * FROM share_links WHERE user_id = ? ORDER BY revoked_at IS NULL DESC, created_at DESC", [$uid]);
    }

    public function createShareLink(int $uid, string $label): array
    {
        $label = mb_substr(trim(strip_tags($label)), 0, 80);
        if ($label === '') throw new InvalidArgumentException('Кому ссылка? Например, «Studio X»');
        $token = bin2hex(random_bytes(8));
        $this->l4t->prepare("INSERT INTO share_links (user_id, token, label) VALUES (?, ?, ?)")->execute([$uid, $token, $label]);
        return ['token' => $token, 'label' => $label];
    }

    public function revokeShareLink(int $uid, int $id): void
    {
        $this->l4t->prepare("UPDATE share_links SET revoked_at = NOW() WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
    }

    /** Открытие ссылки: возвращает uid владельца и увеличивает счётчик (если смотрит не он сам). */
    public function openShareLink(string $token, int $viewer): ?int
    {
        if (!preg_match('/^[0-9a-f]{16}$/', $token) || !$this->has('share_links')) return null;
        $r = $this->row($this->l4t, "SELECT id, user_id FROM share_links WHERE token = ? AND revoked_at IS NULL", [$token]);
        if (!$r) return null;
        if ((int)$r['user_id'] !== $viewer) {
            $this->l4t->prepare("UPDATE share_links SET views = views + 1, last_view_at = NOW() WHERE id = ?")->execute([$r['id']]);
        }
        return (int)$r['user_id'];
    }

    /* ═════════════════════════════ ПРОФИЛЬ: ДОП. ПОЛЯ ═════════════════════════════ */

    public function saveProfileExtra(int $uid, array $d): array
    {
        $modes = implode(',', array_values(array_intersect((array)($d['work_modes'] ?? []), array_keys(self::WORK_MODES))));
        $tz = isset($d['tz']) && $d['tz'] !== '' ? max(-720, min(840, (int)$d['tz'])) : null;
        $row = [
            'work_modes' => $modes ?: null,
            'rate'       => mb_substr(trim(strip_tags((string)($d['rate'] ?? ''))), 0, 60) ?: null,
            'tz'         => $tz,
        ];
        if (array_key_exists('manual', $d)) $row['manual'] = mb_substr(trim(strip_tags((string)$d['manual'])), 0, 3000) ?: null;
        $row = array_intersect_key($row, array_filter($this->cols['l4t']['profiles'] ?? []));
        if (!$row) return [];
        $c = array_keys($row);
        $this->l4t->prepare("INSERT INTO profiles (user_id, " . implode(',', $c) . ") VALUES (?" . str_repeat(',?', count($c)) . ")
                             ON DUPLICATE KEY UPDATE " . implode(',', array_map(fn($k) => "$k = VALUES($k)", $c)))
            ->execute(array_merge([$uid], array_values($row)));
        return $row;
    }

    /** Статус «ищу» живёт 30 дней. Продление — одной кнопкой. */
    public function renewAvailability(int $uid): void
    {
        if ($this->has('profiles', 'avail_until')) {
            $this->l4t->prepare("UPDATE profiles SET avail_until = CURDATE() + INTERVAL 30 DAY WHERE user_id = ?")->execute([$uid]);
        }
    }

    /* ═════════════════════════════ СОХРАНЁННЫЕ ПОИСКИ ═════════════════════════════ */

    public function saveSearch(int $uid, string $skill, string $q): void
    {
        $skill = isset($this->skills()[$skill]) ? $skill : '';
        $q = mb_substr(trim(strip_tags($q)), 0, 80);
        if ($skill === '' && $q === '') throw new InvalidArgumentException('Пустой поиск сохранять незачем');
        $n = (int)$this->val($this->l4t, "SELECT COUNT(*) FROM saved_searches WHERE user_id = ?", [$uid]);
        if ($n >= 10) throw new InvalidArgumentException('Не больше 10 сохранённых поисков');
        $this->l4t->prepare("INSERT INTO saved_searches (user_id, skill, q) VALUES (?, ?, ?)")->execute([$uid, $skill ?: null, $q ?: null]);
    }

    public function savedSearches(int $uid): array
    {
        return $this->has('saved_searches') ? $this->rows($this->l4t, "SELECT * FROM saved_searches WHERE user_id = ? ORDER BY id DESC", [$uid]) : [];
    }

    public function deleteSearch(int $uid, int $id): void
    {
        $this->l4t->prepare("DELETE FROM saved_searches WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
    }

    /**
     * Человек обновил навыки или открылся к предложениям — проверяем чужие
     * сохранённые поиски и шлём уведомление. Один человек по одному поиску —
     * один раз (saved_search_hits), иначе каждое сохранение профиля = спам.
     */
    public function matchSavedSearches(int $uid): void
    {
        if (!$this->has('saved_search_hits')) return;
        $p = $this->row($this->l4t, "SELECT availability, avail_until FROM profiles WHERE user_id = ?", [$uid]);
        if (!$p || !in_array($p['availability'], ['open', 'hiring', 'busy'], true)) return;
        $skills = array_keys($this->userSkills($uid));
        $text = mb_strtolower((string)$this->val($this->main, "SELECT CONCAT_WS(' ', l4t_role, l4t_about) FROM users WHERE id = ?", [$uid]));
        $name = $this->userName($uid);

        foreach ($this->rows($this->l4t, "SELECT * FROM saved_searches WHERE user_id <> ?", [$uid]) as $s) {
            $ok = ($s['skill'] === null || in_array($s['skill'], $skills, true))
               && ($s['q'] === null || mb_strpos($text, mb_strtolower((string)$s['q'])) !== false);
            if (!$ok) continue;
            $ins = $this->l4t->prepare("INSERT IGNORE INTO saved_search_hits (search_id, user_id) VALUES (?, ?)");
            $ins->execute([$s['id'], $uid]);
            if ($ins->rowCount()) {
                $what = $s['skill'] ? ($this->skills()[$s['skill']]['name'] ?? $s['skill']) : $s['q'];
                $this->notify([(int)$s['user_id']], 'Нашёлся специалист', "$name — по вашему поиску «{$what}»", '/l4t/' . rawurlencode($this->userHandle($uid)));
            }
        }
    }

    /* ═════════════════════════════ ХЕЛПЕРЫ ═════════════════════════════ */

    /** id => [name, avatar, handle, role] */
    public function users(array $ids, bool $withRole = false): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach ($this->rows($this->main, "SELECT id, username, telegram_username, profile_picture, l4t_role FROM users WHERE id IN ($in)", $ids) as $u) {
            $out[(int)$u['id']] = [
                'name'   => $u['username'] ?: '@' . $u['telegram_username'],
                'avatar' => (string)($u['profile_picture'] ?? ''),
                'handle' => (string)($u['username'] ?: $u['telegram_username']),
                'role'   => (string)($u['l4t_role'] ?? ''),
            ];
        }
        return $out;
    }

    public function userName(int $uid): string { return $this->users([$uid])[$uid]['name'] ?? 'Пользователь'; }
    public function userHandle(int $uid): string { return $this->users([$uid])[$uid]['handle'] ?? ''; }

    /** Уведомление в общий центр. Пишем напрямую: NotificationCenter тянет vendor/autoload ради почты. */
    public function notify(array $ids, string $title, string $msg, string $url): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return;
        try {
            $st = $this->main->prepare("INSERT INTO notifications (user_id, title, message, action, status, date) VALUES (?, ?, ?, ?, 'unread', NOW())");
            foreach ($ids as $id) $st->execute([$id, $title, $msg, $url]);
        } catch (Throwable $e) {
            error_log('[l4t/notify] ' . $e->getMessage());
        }
    }

    public function rows(?PDO $db, string $sql, array $p = []): array
    {
        if (!$db) return [];
        try { $st = $db->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { error_log('[l4t/x] ' . $e->getMessage()); return []; }
    }

    public function row(?PDO $db, string $sql, array $p = []): ?array
    {
        return $this->rows($db, $sql, $p)[0] ?? null;
    }

    public function val(?PDO $db, string $sql, array $p = [])
    {
        if (!$db) return null;
        try { $st = $db->prepare($sql); $st->execute($p); $v = $st->fetchColumn(); return $v === false ? null : $v; }
        catch (Throwable $e) { return null; }
    }
}
