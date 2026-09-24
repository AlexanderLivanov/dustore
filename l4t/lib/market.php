<?php
declare(strict_types=1);

/**
 * l4t/lib/market.php — L4T как рынок: спрос × предложение.
 *
 * ────────────────────────────────────────────────────────────────────────
 * МОДЕЛЬ
 *   Спрос (need)        = заявка bids: «нужен Unity-программист, 10–20k, неделя».
 *   Предложение (offer) = offers: «Unity / C#, 5–15k, свободен сейчас».
 *   Инструмент          = навык. Одна заявка с навыками Unity + C# видна
 *                         в стаканах обоих — как бумага в нескольких индексах.
 *
 * СТАКАН (order book) по инструменту
 *   слева спрос — по убыванию бюджета: лучший спрос = самый щедрый заказчик;
 *   справа предложение — по возрастанию цены: лучшее = самый доступный исполнитель.
 *   Спред = лучшая цена предложения − лучший бюджет спроса.
 *     спред ≤ 0  → стакан «сходится»: есть пары, где бюджет покрывает цену;
 *     спред > 0  → рынок ждёт: либо заказчики поднимут бюджет, либо появятся дешевле.
 *   Неденежные позиции (доля, бесплатно, джем) — отдельной секцией: у них нет цены,
 *   но это такой же спрос и предложение.
 *
 * СВЕДЕНИЕ (matching)
 *   После каждого размещения движок ищет пары на другой стороне и считает score:
 *   навыки 45 · деньги 25 · доступность 15 · уровень 10 · свежесть 5.
 *   Сделка = обе стороны сказали «да». Как на бирже: заявки исполняются,
 *   только когда встречаются — но здесь последнее слово за людьми, не за ценой.
 * ────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/extras.php';

final class L4TMarket
{
    public const KINDS = ['task' => 'Разовая задача', 'team' => 'В команду', 'jam' => 'На джем', 'consult' => 'Консультация'];
    public const PAY   = ['money' => 'За деньги', 'share' => 'За долю', 'free' => 'Бесплатно'];
    private const MIN_SCORE = 50;
    private const TTL_DAYS  = 30;
    /** Таймер позиции: через сколько дней она сама снимется с рынка. */
    public const LIFETIMES = [1 => '1 день', 7 => '7 дней', 14 => '14 дней', 30 => 'месяц'];

    public static function lifetime($v): int
    {
        $v = (int)$v;
        return isset(self::LIFETIMES[$v]) ? $v : self::TTL_DAYS;
    }

    /** SQL-фрагмент «ещё жива»: активна, таймер не истёк, не удалена. */
    private function aliveSql(string $t): string
    {
        return "stage = 'active' AND (expires_at IS NULL OR expires_at > NOW())"
             . ($this->x->has($t, 'deleted_at') ? ' AND deleted_at IS NULL' : '');
    }

    /** Колонки таймера и причины есть (миграция 010)? */
    private function v10(string $t): bool
    {
        return $this->x->has($t, 'lifetime_days') && $this->x->has($t, 'close_reason');
    }

    private L4TX $x;
    private PDO $main;
    private ?PDO $db;
    private ?array $needsCache = null;
    private ?array $offersCache = null;

    public function __construct(L4TX $x, PDO $main)
    {
        $this->x = $x;
        $this->main = $main;
        $this->db = $x->l4t();
    }

    public function ready(): bool
    {
        return $this->db !== null && $this->x->has('offers') && $this->x->has('matches') && $this->x->has('bids', 'budget_max');
    }

    /* ═════════════════════════════ ПОЗИЦИИ ═════════════════════════════ */

    /** Нормализация полей рынка из формы — общая для спроса и предложения. */
    public static function priceFields(array $d, string $pfx): array
    {
        $pay = (string)($d['pay_type'] ?? 'money');
        if (!isset(self::PAY[$pay])) $pay = 'money';
        $n = fn($v) => ($v === '' || $v === null) ? null : max(0, min(10_000_000, (int)preg_replace('/\D/', '', (string)$v)));
        $lo = $pay === 'money' ? $n($d[$pfx . '_min'] ?? null) : null;
        $hi = $pay === 'money' ? $n($d[$pfx . '_max'] ?? null) : null;
        if ($lo !== null && $hi !== null && $lo > $hi) [$lo, $hi] = [$hi, $lo];
        return [$pay, $lo, $hi];
    }

    /** Поля рынка у заявки (bids) — вызывается из upsert_bid.php после записи. */
    public function saveNeedMarket(int $bidId, array $post): void
    {
        if (!$this->ready()) return;
        $kind = isset(self::KINDS[$post['kind'] ?? '']) ? $post['kind'] : 'task';
        [$pay, $lo, $hi] = self::priceFields($post, 'budget');
        $dur = ($post['duration_days'] ?? '') !== '' ? max(1, min(365, (int)$post['duration_days'])) : null;
        $life = self::lifetime($post['lifetime_days'] ?? self::TTL_DAYS);
        $v10 = $this->v10('bids') ? ", lifetime_days = $life, close_reason = NULL" : '';
        $this->db->prepare("UPDATE bids SET kind = ?, pay_type = ?, budget_min = ?, budget_max = ?, duration_days = ?,
                                     expires_at = NOW() + INTERVAL $life DAY$v10 WHERE id = ?")
            ->execute([$kind, $pay, $lo, $hi, $dur, $bidId]);
        $this->needsCache = null;
        $this->matchNeed($bidId);
    }

    public function saveOffer(int $uid, array $d, int $id = 0): int
    {
        $title = mb_substr(trim(strip_tags((string)($d['title'] ?? ''))), 0, 120);
        if ($title === '') throw new InvalidArgumentException('Коротко: что вы делаете — «Unity / C#, мультиплеер»');
        $skills = array_values(array_filter((array)($d['skills'] ?? []), fn($s) => isset($this->x->skills()[$s])));
        if (!$skills) throw new InvalidArgumentException('Отметьте хотя бы один навык — по ним вас находят');

        $kind = (string)($d['kind'] ?? 'any');
        if ($kind !== 'any' && !isset(self::KINDS[$kind])) $kind = 'any';
        [$pay, $lo, $hi] = self::priceFields($d, 'price');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['available_from'] ?? '')) && $d['available_from'] > date('Y-m-d')
            ? (string)$d['available_from'] : null;
        $hours = ($d['hours_week'] ?? '') !== '' ? max(1, min(80, (int)$d['hours_week'])) : null;
        $details = mb_substr(trim(strip_tags((string)($d['details'] ?? ''))), 0, 2000) ?: null;
        $life = self::lifetime($d['lifetime_days'] ?? self::TTL_DAYS);

        if ($id) {
            $own = $this->x->val($this->db, "SELECT user_id FROM offers WHERE id = ?", [$id]);
            if ((int)$own !== $uid) throw new InvalidArgumentException('Это не ваше предложение');
            $this->db->prepare("UPDATE offers SET title=?, kind=?, pay_type=?, price_min=?, price_max=?, available_from=?, hours_week=?,
                                       details=?, stage='active', expires_at = NOW() + INTERVAL $life DAY" .
                                       ($this->v10('offers') ? ", lifetime_days = $life, close_reason = NULL" : '') . " WHERE id=?")
                ->execute([$title, $kind, $pay, $lo, $hi, $from, $hours, $details, $id]);
        } else {
            $n = (int)$this->x->val($this->db, "SELECT COUNT(*) FROM offers WHERE user_id = ? AND " . $this->aliveSql('offers'), [$uid]);
            if ($n >= 5) throw new InvalidArgumentException('Не больше пяти активных предложений — снимите лишнее');
            $this->db->prepare("INSERT INTO offers (user_id, title, kind, pay_type, price_min, price_max, available_from, hours_week, details, expires_at"
                                . ($this->v10('offers') ? ', lifetime_days' : '') . ")
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() + INTERVAL $life DAY" . ($this->v10('offers') ? ", $life" : '') . ")")
                ->execute([$uid, $title, $kind, $pay, $lo, $hi, $from, $hours, $details]);
            $id = (int)$this->db->lastInsertId();
        }

        $all = $this->x->skills();
        $this->db->prepare("DELETE FROM offer_skills WHERE offer_id = ?")->execute([$id]);
        $ins = $this->db->prepare("INSERT IGNORE INTO offer_skills (offer_id, skill_id) VALUES (?, ?)");
        foreach (array_slice($skills, 0, 8) as $s) $ins->execute([$id, $all[$s]['id']]);

        $this->offersCache = null;
        $this->matchOffer($id);
        return $id;
    }

    public function setOfferStage(int $uid, int $id, string $stage): void
    {
        if (!in_array($stage, ['active', 'paused', 'closed'], true)) throw new InvalidArgumentException('Неизвестный статус');
        $this->setStage('offers', 'user_id', $uid, $id, $stage === 'active' ? 'active' : 'closed', null);
        if ($stage === 'active') $this->matchOffer($id);
    }

    public function setNeedStage(int $uid, int $id, string $stage): void
    {
        if (!in_array($stage, ['active', 'closed'], true)) throw new InvalidArgumentException('Неизвестный статус');
        $this->setStage('bids', 'bidder_id', $uid, $id, $stage, null);
        if ($stage === 'active') $this->matchNeed($id);
    }

    /**
     * Смена состояния позиции. Возврат на рынок перезапускает таймер на тот же
     * срок, что выбрал автор; снятие запоминает причину («сам снял»).
     * Модератор снимает чужую позицию — тогда проверки владельца нет.
     */
    private function setStage(string $t, string $ownerCol, int $uid, int $id, string $stage, ?int $days, bool $asAdmin = false): void
    {
        $v10 = $this->v10($t);
        $set = ['stage = ?'];
        $args = [$stage];
        if ($stage === 'active') {
            $set[] = $days ? "expires_at = NOW() + INTERVAL " . self::lifetime($days) . " DAY"
                           : ($v10 ? "expires_at = NOW() + INTERVAL COALESCE(lifetime_days, " . self::TTL_DAYS . ") DAY"
                                   : "expires_at = NOW() + INTERVAL " . self::TTL_DAYS . " DAY");
            if ($days && $v10) $set[] = 'lifetime_days = ' . self::lifetime($days);
            if ($v10) { $set[] = 'close_reason = NULL'; }
        } elseif ($v10 && !$asAdmin) {
            $set[] = "close_reason = 'owner'";
        }
        $where = 'id = ?' . ($asAdmin ? '' : " AND $ownerCol = ?");
        $args[] = $id;
        if (!$asAdmin) $args[] = $uid;
        $st = $this->db->prepare("UPDATE $t SET " . implode(', ', $set) . " WHERE $where");
        $st->execute($args);
        $this->needsCache = $this->offersCache = null;
    }

    /* ═════════════════════════════ ЧТЕНИЕ СТОРОН ═════════════════════════════ */

    /** Активный спрос в нормализованном виде: id => [...] */
    public function needs(): array
    {
        if ($this->needsCache !== null) return $this->needsCache;
        $this->needsCache = [];
        if (!$this->ready()) return [];
        $rows = $this->x->rows($this->db, "SELECT * FROM bids WHERE " . $this->aliveSql('bids') . "
                                            ORDER BY created_at DESC LIMIT 600");
        $tags = $this->x->bidSkills(array_column($rows, 'id'));
        foreach ($rows as $b) {
            $id = (int)$b['id'];
            $lo = $b['budget_min'] !== null ? (int)$b['budget_min'] : ($b['budget_max'] !== null ? (int)$b['budget_max'] : null);
            $hi = $b['budget_max'] !== null ? (int)$b['budget_max'] : $lo;
            $this->needsCache[$id] = [
                'side'    => 'need',
                'id'      => $id,
                'user_id' => (int)$b['bidder_id'],
                'title'   => (string)$b['search_role'],
                'skills'  => $tags[$id] ?? $this->x->inferSkills($b['search_role'] . ' ' . $b['search_spec'] . ' ' . $b['details']),
                'kind'    => $b['kind'] ?: 'task',
                'pay'     => $b['pay_type'] ?: 'money',
                'lo'      => $lo, 'hi' => $hi,
                'days'    => $b['duration_days'] !== null ? (int)$b['duration_days'] : null,
                'details' => (string)$b['details'],
                'extra'   => trim(implode(' · ', array_filter([$b['search_spec'], $b['experience'], $b['conditions']]))),
                'created' => (string)$b['created_at'],
                'jam'     => !empty($b['jam_id']),
                'responses' => (int)($b['responses'] ?? 0),
                'expires' => $b['expires_at'] ?? null,
            ];
        }
        return $this->needsCache;
    }

    public function offers(): array
    {
        if ($this->offersCache !== null) return $this->offersCache;
        $this->offersCache = [];
        if (!$this->ready()) return [];
        $rows = $this->x->rows($this->db, "SELECT * FROM offers WHERE " . $this->aliveSql('offers') . "
                                            ORDER BY created_at DESC LIMIT 600");
        $tags = $this->offerSkills(array_column($rows, 'id'));
        foreach ($rows as $o) $this->offersCache[(int)$o['id']] = $this->normOffer($o, $tags[(int)$o['id']] ?? []);
        return $this->offersCache;
    }

    private function normOffer(array $o, array $skills): array
    {
        return [
            'side'    => 'offer',
            'id'      => (int)$o['id'],
            'user_id' => (int)$o['user_id'],
            'title'   => (string)$o['title'],
            'skills'  => $skills,
            'kind'    => (string)$o['kind'],
            'pay'     => (string)$o['pay_type'],
            'lo'      => $o['price_min'] !== null ? (int)$o['price_min'] : null,
            'hi'      => $o['price_max'] !== null ? (int)$o['price_max'] : null,
            'from'    => $o['available_from'],
            'hours'   => $o['hours_week'] !== null ? (int)$o['hours_week'] : null,
            'details' => (string)($o['details'] ?? ''),
            'stage'   => (string)$o['stage'],
            'created' => (string)$o['created_at'],
            'expires' => $o['expires_at'],
        ];
    }

    private function offerSkills(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach ($this->x->rows($this->db, "SELECT os.offer_id, s.slug FROM offer_skills os JOIN skills s ON s.id = os.skill_id
                                             WHERE os.offer_id IN ($in) ORDER BY s.sort", $ids) as $r) {
            $out[(int)$r['offer_id']][] = $r['slug'];
        }
        return $out;
    }

    public function myOffers(int $uid): array
    {
        if (!$this->ready()) return [];
        $rows = $this->x->rows($this->db, "SELECT * FROM offers WHERE user_id = ? AND stage <> 'closed' ORDER BY stage = 'active' DESC, created_at DESC", [$uid]);
        $tags = $this->offerSkills(array_column($rows, 'id'));
        return array_map(fn($o) => $this->normOffer($o, $tags[(int)$o['id']] ?? []) + ['views' => (int)$o['views']], $rows);
    }

    public function offerById(int $id): ?array
    {
        $o = $this->x->row($this->db, "SELECT * FROM offers WHERE id = ?", [$id]);
        return $o ? $this->normOffer($o, $this->offerSkills([$id])[$id] ?? []) : null;
    }

    public function needById(int $id): ?array
    {
        if (isset($this->needs()[$id])) return $this->needs()[$id];
        return null;
    }

    /* ═════════════════════════════ СВЕДЕНИЕ ═════════════════════════════ */

    /**
     * Насколько предложение закрывает спрос. null — пара невозможна
     * (нет общего навыка, несовместимый формат, деньги далеко).
     * @return array{0:int,1:string[]}|null
     */
    public function score(array $need, array $offer): ?array
    {
        if ($need['user_id'] === $offer['user_id']) return null;
        $all = $this->x->skills();

        /* 1. Навыки. Если в заявке есть роль — совпасть должна роль, а не только «C#». */
        $shared = array_values(array_intersect($need['skills'], $offer['skills']));
        if (!$shared) return null;
        $needRoles = array_filter($need['skills'], fn($s) => ($all[$s]['kind'] ?? '') === 'role');
        if ($needRoles && !array_intersect($needRoles, $offer['skills'])) {
            // роль не совпала, но совпала группа роли (3D vs 2D-художник) — допускаем с понижением
            $grp = array_map(fn($s) => $all[$s]['grp'] ?? '', $needRoles);
            $ogrp = array_map(fn($s) => $all[$s]['grp'] ?? '', array_filter($offer['skills'], fn($s) => ($all[$s]['kind'] ?? '') === 'role'));
            if (!array_intersect($grp, $ogrp)) return null;
        }
        $skill = min(1.0, count($shared) / max(1, count($need['skills'])));
        $why = [implode(', ', array_map(fn($s) => $all[$s]['name'] ?? $s, array_slice($shared, 0, 3)))];

        /* 2. Формат работы. */
        $ok = [
            'task'    => ['task', 'consult'],
            'consult' => ['consult', 'task'],
            'team'    => ['team', 'jam'],
            'jam'     => ['jam', 'team'],
        ];
        if ($offer['kind'] !== 'any' && !in_array($offer['kind'], $ok[$need['kind']] ?? [$need['kind']], true)) return null;

        /* 3. Деньги. Заказчик за долю не сойдётся с исполнителем «только за деньги».
              Обратное можно: кто готов за долю, от денег не откажется. */
        $money = 0.6;
        if ($need['pay'] !== 'money') {
            if ($offer['pay'] === 'money') return null;
            $money = 1.0; $why[] = self::PAY[$need['pay']] ?? '';
        } elseif ($offer['pay'] !== 'money') {
            $money = 1.0; $why[] = 'исполнитель готов и за ' . ($offer['pay'] === 'share' ? 'долю' : 'портфолио');
        } elseif ($need['hi'] !== null && $offer['lo'] !== null) {
            if ($need['hi'] >= $offer['lo'])          { $money = 1.0; $why[] = 'бюджет покрывает цену'; }
            elseif ($need['hi'] >= $offer['lo'] * .8) { $money = 0.4; $why[] = 'бюджет ниже цены на ' . (int)round((1 - $need['hi'] / $offer['lo']) * 100) . '%'; }
            else return null;
        } else {
            $why[] = 'цена по договорённости';
        }

        /* 4. Когда свободен. */
        $avail = 1.0;
        if ($offer['from'] ?? null) {
            $days = (strtotime((string)$offer['from']) - time()) / 86400;
            $avail = $days <= 7 ? 0.9 : ($days <= 30 ? 0.5 : 0.2);
            $why[] = 'свободен с ' . date('d.m', strtotime((string)$offer['from']));
        } else {
            $why[] = 'свободен сейчас';
        }

        /* 5. Уровень исполнителя по совпавшим навыкам. */
        $lv = $this->x->userSkills($offer['user_id']);
        $lvl = 0.0;
        foreach ($shared as $s) $lvl += ($lv[$s] ?? 1) / 3;
        $lvl /= count($shared);

        $fresh = (time() - (strtotime($need['created']) ?: time())) / 86400 < 7 ? 1.0 : 0.4;
        $score = (int)round(45 * $skill + 25 * $money + 15 * $avail + 10 * $lvl + 5 * $fresh);
        return [$score, array_values(array_filter($why))];
    }

    public function matchNeed(int $bidId): int
    {
        $need = $this->needs()[$bidId] ?? null;
        if (!$need) return 0;
        $found = [];
        foreach ($this->offers() as $o) if ($s = $this->score($need, $o)) $found[] = [$o, $s];
        return $this->storeMatches($found, fn($o) => [$bidId, $o['id']], 'need', $need);
    }

    public function matchOffer(int $offerId): int
    {
        $offer = $this->offers()[$offerId] ?? $this->offerById($offerId);
        if (!$offer || $offer['stage'] !== 'active') return 0;
        $found = [];
        foreach ($this->needs() as $n) if ($s = $this->score($n, $offer)) $found[] = [$n, $s];
        return $this->storeMatches($found, fn($n) => [$n['id'], $offerId], 'offer', $offer);
    }

    /**
     * Сохраняем топ-10 пар выше порога. Уведомляем только о НОВЫХ парах и не больше
     * трёх за раз — иначе одно размещение превращается в спам уведомлениями.
     */
    private function storeMatches(array $found, callable $pair, string $side, array $order): int
    {
        if (!$this->ready()) return 0;
        usort($found, fn($a, $b) => $b[1][0] <=> $a[1][0]);
        $ins = $this->db->prepare("INSERT INTO matches (bid_id, offer_id, score, reasons) VALUES (?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE score = VALUES(score), reasons = VALUES(reasons)");
        $new = 0; $notified = 0;
        foreach (array_slice($found, 0, 10) as [$other, [$score, $why]]) {
            if ($score < self::MIN_SCORE) continue;
            [$b, $o] = $pair($other);
            $ins->execute([$b, $o, $score, mb_substr(implode(' · ', $why), 0, 255)]);
            if ($ins->rowCount() === 1) {                   // 1 = вставка, 2 = обновление
                $new++;
                if ($notified++ < 3) {
                    $this->x->notify([$other['user_id']], $side === 'need' ? 'Под ваше предложение есть задача' : 'L4T нашёл исполнителя',
                        '«' . $order['title'] . '» — совпадение ' . $score . '%', '/l4t/?tab=bids');
                }
            }
        }
        if ($new) $this->x->notify([$order['user_id']], 'Новые совпадения на рынке',
            "«{$order['title']}»: найдено $new", '/l4t/?tab=bids');
        return $new;
    }

    /** Все пары, где я одна из сторон, с карточкой контрагента. */
    public function matchesFor(int $uid): array
    {
        if (!$this->ready()) return [];
        $rows = $this->x->rows($this->db, "SELECT m.*, b.bidder_id need_uid, b.search_role need_title, o.user_id offer_uid, o.title offer_title
                                             FROM matches m
                                             JOIN bids b   ON b.id = m.bid_id
                                             JOIN offers o ON o.id = m.offer_id
                                            WHERE (b.bidder_id = ? OR o.user_id = ?)
                                              AND m.need_state <> 'no' AND m.offer_state <> 'no'
                                              AND (m.dealt_at IS NOT NULL OR (b.stage = 'active' AND o.stage = 'active'))
                                            ORDER BY m.dealt_at IS NULL DESC, m.score DESC LIMIT 100", [$uid, $uid]);
        $uids = [];
        foreach ($rows as $r) { $uids[] = (int)$r['need_uid']; $uids[] = (int)$r['offer_uid']; }
        $users = $this->x->users($uids, true);
        $tg = [];
        if ($uids) {
            $in = implode(',', array_fill(0, count(array_unique($uids)), '?'));
            foreach ($this->x->rows($this->main, "SELECT id, telegram_username FROM users WHERE id IN ($in)", array_values(array_unique($uids))) as $u) $tg[(int)$u['id']] = (string)$u['telegram_username'];
        }
        $out = [];
        foreach ($rows as $r) {
            $mySide = (int)$r['need_uid'] === $uid ? 'need' : 'offer';
            $other = $mySide === 'need' ? (int)$r['offer_uid'] : (int)$r['need_uid'];
            $mine  = $mySide === 'need' ? $r['need_state'] : $r['offer_state'];
            $their = $mySide === 'need' ? $r['offer_state'] : $r['need_state'];
            $out[] = [
                'id' => (int)$r['id'], 'score' => (int)$r['score'], 'reasons' => (string)$r['reasons'],
                'side' => $mySide, 'mine' => $mine, 'their' => $their, 'deal' => $r['dealt_at'] !== null,
                'need' => ['id' => (int)$r['bid_id'], 'title' => $r['need_title']],
                'offer' => ['id' => (int)$r['offer_id'], 'title' => $r['offer_title']],
                'other' => ($users[$other] ?? ['name' => 'Пользователь', 'handle' => '', 'avatar' => '', 'role' => '']) + ['id' => $other],
                // контакты — только после сделки
                'tg' => $r['dealt_at'] !== null ? ($tg[$other] ?? '') : '',
                'initiator' => $r['initiator'],
            ];
        }
        return $out;
    }

    public function pendingCount(int $uid): int
    {
        return count(array_filter($this->matchesFor($uid), fn($m) => !$m['deal'] && $m['mine'] === 'new'));
    }

    /** Ответ на пару: да/нет. Два «да» — сделка. */
    public function answer(int $uid, int $matchId, bool $yes): array
    {
        $m = $this->x->row($this->db, "SELECT m.*, b.bidder_id need_uid, b.search_role, b.kind, o.user_id offer_uid, o.title offer_title
                                         FROM matches m JOIN bids b ON b.id = m.bid_id JOIN offers o ON o.id = m.offer_id WHERE m.id = ?", [$matchId]);
        if (!$m) throw new InvalidArgumentException('Совпадение не найдено');
        $col = (int)$m['need_uid'] === $uid ? 'need_state' : ((int)$m['offer_uid'] === $uid ? 'offer_state' : null);
        if (!$col) throw new InvalidArgumentException('Это не ваша пара');
        if ($m['dealt_at']) return ['deal' => true];

        $this->db->prepare("UPDATE matches SET $col = ? WHERE id = ?")->execute([$yes ? 'yes' : 'no', $matchId]);
        $other = $col === 'need_state' ? (int)$m['offer_uid'] : (int)$m['need_uid'];
        $m[$col] = $yes ? 'yes' : 'no';

        if ($yes && $m['need_state'] === 'yes' && $m['offer_state'] === 'yes') {
            $this->deal($m);
            return ['deal' => true];
        }
        if ($yes) {
            $this->x->notify([$other], 'Вас хотят в проект', $this->x->userName($uid) . ' готов(а) работать: «' . $m['search_role'] . '»', '/l4t/?tab=bids');
        }
        return ['deal' => false];
    }

    /**
     * Сделка. Пишем строку в responds со статусом «в команде»/«принят» — и
     * сделка автоматически становится титром, открывает рекомендации и
     * попадает в GPI «время до команды». Новую сущность «контракт» не заводим:
     * всё, что нужно, уже умеет отклик.
     */
    private function deal(array $m): void
    {
        $status = in_array($m['kind'], ['team', 'jam'], true) ? 'в команде' : 'принят';
        $rid = null;
        $ex = $this->x->row($this->db, "SELECT id FROM responds WHERE bid_id = ? AND user_id = ?", [(int)$m['bid_id'], (int)$m['offer_uid']]);
        if ($ex) {
            $rid = (int)$ex['id'];
            $this->db->prepare("UPDATE responds SET status = ?" . ($this->x->has('responds', 'decided_at') ? ", decided_at = COALESCE(decided_at, NOW())" : '') . " WHERE id = ?")
                ->execute([$status, $rid]);
        } else {
            $cols = "bid_id, user_id, message, status, created_at" . ($this->x->has('responds', 'decided_at') ? ', decided_at' : '');
            $vals = "?, ?, ?, ?, NOW()" . ($this->x->has('responds', 'decided_at') ? ', NOW()' : '');
            $this->db->prepare("INSERT INTO responds ($cols) VALUES ($vals)")
                ->execute([(int)$m['bid_id'], (int)$m['offer_uid'], 'Сделка на рынке L4T: «' . $m['offer_title'] . '»', $status]);
            $rid = (int)$this->db->lastInsertId();
            if ($this->x->has('bids', 'responses')) $this->db->prepare("UPDATE bids SET responses = responses + 1 WHERE id = ?")->execute([(int)$m['bid_id']]);
        }
        $this->db->prepare("UPDATE matches SET dealt_at = NOW(), respond_id = ? WHERE id = ?")->execute([$rid, (int)$m['id']]);
        $this->x->notify([(int)$m['need_uid'], (int)$m['offer_uid']], 'Сделка на L4T!',
            '«' . $m['search_role'] . '» — обе стороны согласны. Контакты открыты.', '/l4t/?tab=bids');
    }

    /** Предложить напрямую: заказчик — исполнителю (или наоборот). Своя сторона сразу «да». */
    public function propose(int $uid, int $bidId, int $offerId): array
    {
        $need = $this->needs()[$bidId] ?? null;
        $offer = $this->offers()[$offerId] ?? null;
        if (!$need || !$offer) throw new InvalidArgumentException('Одна из позиций уже снята с рынка');
        $side = $need['user_id'] === $uid ? 'need' : ($offer['user_id'] === $uid ? 'offer' : null);
        if (!$side) throw new InvalidArgumentException('Предлагать можно только от своей позиции');
        if ($need['user_id'] === $offer['user_id']) throw new InvalidArgumentException('Это ваши же позиции');

        $sc = $this->score($need, $offer);
        $score = $sc[0] ?? 40;
        $why = $sc ? implode(' · ', $sc[1]) : 'предложено напрямую';
        $col = $side === 'need' ? 'need_state' : 'offer_state';
        $this->db->prepare("INSERT INTO matches (bid_id, offer_id, score, reasons, initiator, $col) VALUES (?, ?, ?, ?, ?, 'yes')
                            ON DUPLICATE KEY UPDATE $col = 'yes'")
            ->execute([$bidId, $offerId, $score, mb_substr($why, 0, 255), $side]);
        $id = (int)$this->x->val($this->db, "SELECT id FROM matches WHERE bid_id = ? AND offer_id = ?", [$bidId, $offerId]);

        $m = $this->x->row($this->db, "SELECT need_state, offer_state FROM matches WHERE id = ?", [$id]);
        if ($m && $m['need_state'] === 'yes' && $m['offer_state'] === 'yes') return $this->answer($uid, $id, true);

        $to = $side === 'need' ? $offer['user_id'] : $need['user_id'];
        $this->x->notify([$to], $side === 'need' ? 'Вам предлагают задачу' : 'Исполнитель предлагает себя',
            '«' . $need['title'] . '» ↔ «' . $offer['title'] . '»', '/l4t/?tab=bids');
        return ['deal' => false, 'id' => $id];
    }

    /* ═════════════════════════════ ПОЧЕМУ ПОДХОДИТ ═════════════════════════════ */

    /** Проценты человеку ничего не говорят. Говорим словами. */
    public static function fitLabel(int $score): array
    {
        if ($score >= 85) return ['great', 'Отлично подходит'];
        if ($score >= 70) return ['good',  'Хорошо подходит'];
        return ['part', 'Подходит частично'];
    }

    /**
     * Разбор пары по пунктам — что совпало, чего не хватает.
     * @return array<int,array{0:string,1:string}>  [ok|warn|no, текст]
     */
    public function explain(array $need, array $offerOrSkills, int $uid): array
    {
        $all = $this->x->skills();
        $name = fn($s) => $all[$s]['name'] ?? $s;
        $offer = isset($offerOrSkills['side']) ? $offerOrSkills : null;
        $his = $offer ? $offer['skills'] : $offerOrSkills;
        $lv = $this->x->userSkills($uid);
        $out = [];

        $shared = array_values(array_intersect($need['skills'], $his));
        $missing = array_values(array_diff($need['skills'], $his));
        if ($shared) {
            $lvName = [1 => 'базовый', 2 => 'уверенный', 3 => 'эксперт'];
            $parts = array_map(fn($s) => $name($s) . (isset($lv[$s]) ? ' — ' . ($lvName[$lv[$s]] ?? '') : ''), array_slice($shared, 0, 4));
            $out[] = ['ok', 'Умеет то, что нужно: ' . implode(', ', $parts)];
        }
        if ($missing) $out[] = ['warn', 'В профиле не указано: ' . implode(', ', array_map($name, array_slice($missing, 0, 4)))];

        if ($offer) {
            if ($need['pay'] !== 'money') {
                $out[] = $offer['pay'] === 'money' ? ['no', 'Работает только за деньги'] : ['ok', 'Согласен на ' . (self::PAY[$need['pay']] ?? '')];
            } elseif ($offer['pay'] !== 'money') {
                $out[] = ['ok', 'Готов работать и без оплаты — за ' . ($offer['pay'] === 'share' ? 'долю' : 'портфолио')];
            } elseif ($need['hi'] !== null && $offer['lo'] !== null) {
                $out[] = $need['hi'] >= $offer['lo']
                    ? ['ok', 'Ваш бюджет покрывает его цену (' . self::priceLabel($offer) . ')']
                    : ['warn', 'Просит ' . self::priceLabel($offer) . ' — выше вашего бюджета'];
            } else {
                $out[] = ['ok', 'Цена: ' . self::priceLabel($offer)];
            }
            if ($offer['from'] && strtotime((string)$offer['from']) > time() + 86400) {
                $out[] = ['warn', 'Освободится ' . date('d.m', strtotime((string)$offer['from']))];
            } else {
                $out[] = ['ok', 'Свободен сейчас' . ($offer['hours'] ? ', ' . $offer['hours'] . ' ч в неделю' : '')];
            }
        } else {
            $out[] = ['warn', 'Своё предложение на рынок не выставлял — условия обсудите'];
        }

        /* Доверие: что о человеке известно, кроме его слов. */
        $recs = $this->x->has('recommendations') ? (int)$this->x->val($this->db, "SELECT COUNT(*) FROM recommendations WHERE target_id = ? AND hidden = 0", [$uid]) : 0;
        $deals = $this->ready() ? (int)$this->x->val($this->db, "SELECT COUNT(*) FROM matches m JOIN offers o ON o.id = m.offer_id WHERE o.user_id = ? AND m.dealt_at IS NOT NULL", [$uid]) : 0;
        $credits = $this->x->has('credits') ? (int)$this->x->val($this->db, "SELECT COUNT(*) FROM credits WHERE user_id = ? AND hidden = 0", [$uid]) : 0;
        $trust = array_filter([
            $credits ? $credits . ' ' . self::plural($credits, 'проект', 'проекта', 'проектов') . ' в титрах' : '',
            $deals ? $deals . ' ' . self::plural($deals, 'сделка', 'сделки', 'сделок') . ' на L4T' : '',
            $recs ? $recs . ' ' . self::plural($recs, 'рекомендация', 'рекомендации', 'рекомендаций') : '',
        ]);
        $out[] = $trust ? ['ok', implode(' · ', $trust)] : ['warn', 'Пока без рекомендаций и завершённых сделок'];
        return $out;
    }

    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100; $n1 = $n % 10;
        if ($n > 10 && $n < 20) return $many;
        if ($n1 > 1 && $n1 < 5) return $few;
        return $n1 === 1 ? $one : $many;
    }

    /* ═════════════════════════════ КАНДИДАТЫ ═════════════════════════════ */

    /**
     * Всё, что есть под мою заявку, одним списком — сразу после публикации:
     *   1) предложения с рынка (пары из matches и на лету, если их ещё не сохранили),
     *   2) люди, которые откликнулись сами,
     *   3) специалисты с нужными навыками без выставленного предложения — их можно пригласить.
     * Каждый кандидат приходит с объяснением и состоянием диалога.
     */
    public function candidates(int $uid, int $bidId): array
    {
        $need = $this->needById($bidId) ?? $this->needAnyState($bidId);
        if (!$need || $need['user_id'] !== $uid) return [];
        $users = [];
        $out = ['market' => [], 'responds' => [], 'people' => []];
        $seen = [$uid => true];

        /* 1. Рынок. Считаем свежо, а сохранённые пары дают состояние (кто что ответил). */
        $saved = [];
        if ($this->ready()) {
            foreach ($this->x->rows($this->db, "SELECT * FROM matches WHERE bid_id = ?", [$bidId]) as $m) $saved[(int)$m['offer_id']] = $m;
        }
        $pool = [];
        foreach ($this->offers() as $o) {
            $sc = $this->score($need, $o);
            if (!$sc && !isset($saved[$o['id']])) continue;
            if (isset($saved[$o['id']]) && $saved[$o['id']]['need_state'] === 'no') continue;   // я скрыл
            $pool[] = [$o, $sc ? $sc[0] : (int)($saved[$o['id']]['score'] ?? 40)];
        }
        usort($pool, fn($a, $b) => $b[1] <=> $a[1]);
        foreach (array_slice($pool, 0, 20) as [$o, $score]) {
            if (isset($seen[$o['user_id']])) continue;          // один человек — одна карточка, лучшее его предложение
            $seen[$o['user_id']] = true;
            $m = $saved[$o['id']] ?? null;
            $state = 'new';
            if ($m) {
                if ($m['dealt_at']) $state = 'deal';
                elseif ($m['need_state'] === 'yes') $state = 'invited';
                elseif ($m['offer_state'] === 'yes') $state = 'wants';
                elseif ($m['offer_state'] === 'no') $state = 'declined';
            }
            $out['market'][] = ['uid' => $o['user_id'], 'score' => $score, 'fit' => self::fitLabel($score), 'state' => $state,
                                'match_id' => $m ? (int)$m['id'] : null, 'offer' => $this->card($o), 'why' => $this->explain($need, $o, $o['user_id'])];
        }

        /* 2. Откликнулись сами. */
        $resp = $this->x->rows($this->db, "SELECT * FROM responds WHERE bid_id = ? ORDER BY created_at DESC LIMIT 50", [$bidId]);
        foreach ($resp as $r) {
            $ru = (int)$r['user_id'];
            if (isset($seen[$ru])) {
                // уже есть как предложение с рынка — просто помечаем, что он и сам откликнулся
                foreach ($out['market'] as &$c) if ($c['uid'] === $ru) $c['respond'] = ['id' => (int)$r['id'], 'message' => (string)$r['message'], 'status' => (string)$r['status']];
                unset($c);
                continue;
            }
            $seen[$ru] = true;
            $sk = array_keys($this->x->userSkills($ru));
            $out['responds'][] = ['uid' => $ru, 'state' => 'responded',
                                  'respond' => ['id' => (int)$r['id'], 'message' => (string)$r['message'], 'status' => (string)$r['status'], 'ago' => self::ago((string)$r['created_at'])],
                                  'fit' => $sk ? self::fitLabel($this->skillFit($need, $sk, $ru)) : ['part', 'Навыки не указаны'],
                                  'why' => $this->explain($need, $sk, $ru)];
        }

        /* 3. Можно пригласить: навыки совпадают, статус «ищу проект», предложения нет. */
        $inv = [];
        if ($this->x->has('invites')) {
            foreach ($this->x->rows($this->db, "SELECT user_id, state FROM invites WHERE bid_id = ?", [$bidId]) as $i) $inv[(int)$i['user_id']] = $i['state'];
        }
        foreach ($this->peopleFor($need, 30) as $pu => $sk) {
            if (isset($seen[$pu])) continue;
            $seen[$pu] = true;
            $fit = $this->skillFit($need, $sk, $pu);
            if ($fit < 45 && !isset($inv[$pu])) continue;
            $out['people'][] = ['uid' => $pu, 'fit' => self::fitLabel($fit), 'score' => $fit,
                                'state' => ['sent' => 'invited', 'accepted' => 'accepted', 'declined' => 'declined'][$inv[$pu] ?? ''] ?? 'new',
                                'why' => $this->explain($need, $sk, $pu)];
        }
        usort($out['people'], fn($a, $b) => $b['score'] <=> $a['score']);
        $out['people'] = array_slice($out['people'], 0, 12);

        /* Карточки людей одним запросом. */
        $ids = [];
        foreach ($out as $list) foreach ($list as $c) $ids[] = $c['uid'];
        $users = $this->x->users($ids, true);
        $prof = $this->profilesOf($ids);
        $tg = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count(array_unique($ids)), '?'));
            foreach ($this->x->rows($this->main, "SELECT id, telegram_username FROM users WHERE id IN ($in)", array_values(array_unique($ids))) as $u) $tg[(int)$u['id']] = (string)$u['telegram_username'];
        }
        foreach ($out as &$list) foreach ($list as &$c) {
            $c['user'] = ($users[$c['uid']] ?? ['name' => 'Пользователь', 'handle' => '', 'avatar' => '', 'role' => '']) + ['id' => $c['uid']]
                       + ['headline' => $prof[$c['uid']]['headline'] ?? '', 'skills' => array_map(fn($s) => $this->x->skills()[$s]['name'] ?? $s, array_slice(array_keys($this->x->userSkills($c['uid'])), 0, 6))];
            // контакты открываются только когда обе стороны сказали «да»
            $open = in_array($c['state'], ['deal', 'accepted'], true) || in_array($c['respond']['status'] ?? '', ['принят', 'в команде'], true);
            $c['tg'] = $open ? ($tg[$c['uid']] ?? '') : '';
        }
        unset($list, $c);
        return ['need' => $need, 'lists' => $out, 'total' => count($out['market']) + count($out['responds']) + count($out['people'])];
    }

    /** Под моё предложение: заявки, где я подхожу, и кто меня позвал. */
    public function offerCandidates(int $uid, int $offerId): array
    {
        $offer = $this->offerById($offerId);
        if (!$offer || $offer['user_id'] !== $uid) return [];
        $saved = [];
        if ($this->ready()) foreach ($this->x->rows($this->db, "SELECT * FROM matches WHERE offer_id = ?", [$offerId]) as $m) $saved[(int)$m['bid_id']] = $m;
        $pool = [];
        foreach ($this->needs() as $n) {
            $sc = $this->score($n, $offer);
            if (!$sc && !isset($saved[$n['id']])) continue;
            if (isset($saved[$n['id']]) && $saved[$n['id']]['offer_state'] === 'no') continue;
            $pool[] = [$n, $sc ? $sc[0] : (int)$saved[$n['id']]['score']];
        }
        usort($pool, fn($a, $b) => $b[1] <=> $a[1]);
        $out = [];
        $users = $this->x->users(array_map(fn($p) => $p[0]['user_id'], $pool), true);
        foreach (array_slice($pool, 0, 20) as [$n, $score]) {
            $m = $saved[$n['id']] ?? null;
            $state = 'new';
            if ($m) {
                if ($m['dealt_at']) $state = 'deal';
                elseif ($m['offer_state'] === 'yes') $state = 'invited';     // я предложил себя
                elseif ($m['need_state'] === 'yes') $state = 'wants';        // заказчик зовёт меня
                elseif ($m['need_state'] === 'no') $state = 'declined';
            }
            $card = $this->card($n);
            $out[] = ['uid' => $n['user_id'], 'score' => $score, 'fit' => self::fitLabel($score), 'state' => $state,
                      'match_id' => $m ? (int)$m['id'] : null, 'need' => $card, 'user' => $card['user'],
                      'why' => $this->explainForOffer($n, $offer)];
        }
        return ['offer' => $offer, 'lists' => ['needs' => $out], 'total' => count($out)];
    }

    /** То же объяснение, но с точки зрения исполнителя: чем заявка хороша для меня. */
    private function explainForOffer(array $need, array $offer): array
    {
        $all = $this->x->skills();
        $out = [];
        $shared = array_values(array_intersect($need['skills'], $offer['skills']));
        if ($shared) $out[] = ['ok', 'Нужны ваши навыки: ' . implode(', ', array_map(fn($s) => $all[$s]['name'] ?? $s, array_slice($shared, 0, 4)))];
        $extra = array_diff($need['skills'], $offer['skills']);
        if ($extra) $out[] = ['warn', 'Ещё хотят: ' . implode(', ', array_map(fn($s) => $all[$s]['name'] ?? $s, array_slice($extra, 0, 3)))];
        if ($need['pay'] === 'money' && $need['hi'] !== null && $offer['lo'] !== null && $need['hi'] < $offer['lo']) {
            $out[] = ['warn', 'Бюджет ниже вашей цены'];
        }
        $speed = $this->x->responseSpeed($need['user_id']);
        if ($speed !== null) $out[] = [$speed <= 48 ? 'ok' : 'warn', 'Заказчик отвечает в среднем за ' . ($speed < 24 ? max(1, (int)round($speed)) . ' ч' : (int)round($speed / 24) . ' дн')];
        return $out;
    }

    /** Совпадение по навыкам человека без предложения: грубее, чем score(). */
    private function skillFit(array $need, array $skills, int $uid): int
    {
        if (!$need['skills']) return 40;
        $shared = array_intersect($need['skills'], $skills);
        if (!$shared) return 0;
        $lv = $this->x->userSkills($uid);
        $lvl = 0.0;
        foreach ($shared as $s) $lvl += ($lv[$s] ?? 1) / 3;
        $lvl /= count($shared);
        return (int)round(60 * count($shared) / count($need['skills']) + 25 * $lvl + 15);
    }

    /** uid => [slug…] — люди с навыками из заявки и открытым статусом. */
    private function peopleFor(array $need, int $limit): array
    {
        if (!$need['skills'] || !$this->x->has('user_skills')) return [];
        $in = implode(',', array_fill(0, count($need['skills']), '?'));
        $where = '';
        if ($this->x->has('profiles')) {
            $where = " AND us.user_id IN (SELECT user_id FROM profiles WHERE availability IN ('open','hiring')"
                   . ($this->x->has('profiles', 'avail_until') ? ' AND (avail_until IS NULL OR avail_until >= CURDATE())' : '') . ')';
        }
        $rows = $this->x->rows($this->db, "SELECT us.user_id, s.slug FROM user_skills us JOIN skills s ON s.id = us.skill_id
                                            WHERE us.user_id IN (SELECT us2.user_id FROM user_skills us2 JOIN skills s2 ON s2.id = us2.skill_id WHERE s2.slug IN ($in))
                                            $where LIMIT 2000", $need['skills']);
        $out = [];
        foreach ($rows as $r) $out[(int)$r['user_id']][] = $r['slug'];
        return array_slice($out, 0, $limit * 3, true);
    }

    private function profilesOf(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids || !$this->x->has('profiles')) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach ($this->x->rows($this->db, "SELECT user_id, headline FROM profiles WHERE user_id IN ($in)", $ids) as $p) $out[(int)$p['user_id']] = $p;
        return $out;
    }

    /** Заявка в любом состоянии (для владельца: снята, истекла, скрыта модератором). */
    public function needAnyState(int $id): ?array
    {
        $b = $this->x->row($this->db, "SELECT * FROM bids WHERE id = ?" . ($this->x->has('bids', 'deleted_at') ? ' AND deleted_at IS NULL' : ''), [$id]);
        if (!$b) return null;
        $tags = $this->x->bidSkills([$id]);
        $lo = $b['budget_min'] !== null ? (int)$b['budget_min'] : ($b['budget_max'] !== null ? (int)$b['budget_max'] : null);
        return [
            'side' => 'need', 'id' => $id, 'user_id' => (int)$b['bidder_id'], 'title' => (string)$b['search_role'],
            'skills' => $tags[$id] ?? $this->x->inferSkills($b['search_role'] . ' ' . $b['search_spec'] . ' ' . $b['details']),
            'kind' => ($b['kind'] ?? '') ?: 'task', 'pay' => ($b['pay_type'] ?? '') ?: 'money',
            'lo' => $lo, 'hi' => $b['budget_max'] !== null ? (int)$b['budget_max'] : $lo,
            'days' => ($b['duration_days'] ?? null) !== null ? (int)$b['duration_days'] : null,
            'details' => (string)$b['details'], 'extra' => '', 'created' => (string)$b['created_at'],
            'jam' => !empty($b['jam_id']), 'responses' => (int)($b['responses'] ?? 0), 'expires' => $b['expires_at'] ?? null,
        ];
    }

    /* ═════════════════════════════ ПРИГЛАШЕНИЯ ═════════════════════════════ */

    /** Позвать человека без предложения. Не больше 20 приглашений в сутки — защита от рассылок. */
    public function invite(int $uid, int $bidId, int $to): void
    {
        if (!$this->x->has('invites')) throw new InvalidArgumentException('Приглашения появятся после миграции 010');
        $need = $this->needById($bidId);
        if (!$need || $need['user_id'] !== $uid) throw new InvalidArgumentException('Заявка не найдена или уже снята');
        if ($to === $uid) throw new InvalidArgumentException('Себя пригласить нельзя');
        $n = (int)$this->x->val($this->db, "SELECT COUNT(*) FROM invites i JOIN bids b ON b.id = i.bid_id WHERE b.bidder_id = ? AND i.created_at >= NOW() - INTERVAL 1 DAY", [$uid]);
        if ($n >= 20) throw new InvalidArgumentException('На сегодня приглашений достаточно — дождитесь ответов');
        $st = $this->db->prepare("INSERT IGNORE INTO invites (bid_id, user_id) VALUES (?, ?)");
        $st->execute([$bidId, $to]);
        if ($st->rowCount()) {
            $this->x->notify([$to], 'Вас зовут в проект', $this->x->userName($uid) . ': «' . $need['title'] . '». Посмотрите и ответьте.', '/l4t/?tab=bids&pos=inv-' . $bidId);
        }
    }

    /** Мне пришли приглашения. */
    public function invitesFor(int $uid): array
    {
        if (!$this->x->has('invites')) return [];
        $rows = $this->x->rows($this->db, "SELECT * FROM invites WHERE user_id = ? AND state = 'sent' ORDER BY created_at DESC LIMIT 20", [$uid]);
        $out = [];
        foreach ($rows as $r) {
            $n = $this->needById((int)$r['bid_id']);
            if (!$n) continue;
            $card = $this->card($n);
            $out[] = ['bid_id' => $n['id'], 'need' => $card, 'user' => $card['user'], 'ago' => self::ago((string)$r['created_at']),
                      'why' => $this->explainForOffer($n, ['skills' => array_keys($this->x->userSkills($uid)), 'lo' => null, 'pay' => 'money'])];
        }
        return $out;
    }

    /** Ответ на приглашение. «Да» = отклик на заявку, и контакты открываются обеим сторонам. */
    public function answerInvite(int $uid, int $bidId, bool $yes): void
    {
        $r = $this->x->row($this->db, "SELECT * FROM invites WHERE bid_id = ? AND user_id = ?", [$bidId, $uid]);
        if (!$r) throw new InvalidArgumentException('Приглашение не найдено');
        $this->db->prepare("UPDATE invites SET state = ?, answered_at = NOW() WHERE bid_id = ? AND user_id = ?")->execute([$yes ? 'accepted' : 'declined', $bidId, $uid]);
        $need = $this->needAnyState($bidId);
        if (!$need) return;
        if ($yes) {
            $this->respond($uid, $bidId, 'Принял(а) ваше приглашение', true);
            $this->x->notify([$need['user_id']], 'Приглашение принято', $this->x->userName($uid) . ' готов(а): «' . $need['title'] . '». Контакты открыты.', '/l4t/?tab=bids&pos=need-' . $bidId);
        }
    }

    /** Отклик на заявку. $accepted — сразу «принят» (по приглашению). */
    private function respond(int $uid, int $bidId, string $msg, bool $accepted = false): void
    {
        if ($this->x->val($this->db, "SELECT 1 FROM responds WHERE bid_id = ? AND user_id = ?", [$bidId, $uid])) {
            if ($accepted) $this->db->prepare("UPDATE responds SET status = 'принят' WHERE bid_id = ? AND user_id = ?")->execute([$bidId, $uid]);
            return;
        }
        $this->db->prepare("INSERT INTO responds (bid_id, user_id, message, status, created_at) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$bidId, $uid, mb_substr($msg, 0, 1000), $accepted ? 'принят' : 'ожидает']);
        if ($this->x->has('bids', 'responses')) $this->db->prepare("UPDATE bids SET responses = responses + 1 WHERE id = ?")->execute([$bidId]);
    }

    /* ═════════════════════════════ СВАЙПЫ ═════════════════════════════ */

    /**
     * Колода «Для тебя»: чужие живые заявки, которые я ещё не видел,
     * отсортированы по тому, насколько я подхожу. Сравниваем с моим лучшим
     * предложением, а если его нет — с навыками из профиля.
     */
    public function deck(int $uid, int $limit = 20): array
    {
        $skip = [];
        if ($this->x->has('swipes')) foreach ($this->x->rows($this->db, "SELECT bid_id FROM swipes WHERE user_id = ?", [$uid]) as $r) $skip[(int)$r['bid_id']] = true;
        foreach ($this->x->rows($this->db, "SELECT bid_id FROM responds WHERE user_id = ?", [$uid]) as $r) $skip[(int)$r['bid_id']] = true;

        $mine = array_values(array_filter($this->offers(), fn($o) => $o['user_id'] === $uid));
        $my = array_keys($this->x->userSkills($uid));
        $pseudo = ['side' => 'offer', 'id' => 0, 'user_id' => $uid, 'title' => '', 'skills' => $my, 'kind' => 'any', 'pay' => 'share',
                   'lo' => null, 'hi' => null, 'from' => null, 'hours' => null, 'details' => '', 'stage' => 'active', 'created' => date('Y-m-d H:i:s'), 'expires' => null];
        $cards = [];
        foreach ($this->needs() as $n) {
            if ($n['user_id'] === $uid || isset($skip[$n['id']])) continue;
            $best = null; $bestOffer = null;
            foreach ($mine as $o) if (($s = $this->score($n, $o)) && (!$best || $s[0] > $best[0])) { $best = $s; $bestOffer = $o; }
            if (!$best && $my) $best = ($f = $this->skillFit($n, $my, $uid)) ? [$f, []] : null;
            $score = $best[0] ?? 20;
            $cards[] = [$n, $score, $bestOffer];
        }
        usort($cards, fn($a, $b) => $b[1] <=> $a[1] ?: strcmp($b[0]['created'], $a[0]['created']));
        $out = [];
        foreach (array_slice($cards, 0, $limit) as [$n, $score, $o]) {
            $card = $this->card($n);
            $out[] = ['bid_id' => $n['id'], 'need' => $card, 'user' => $card['user'], 'fit' => self::fitLabel($score), 'score' => $score,
                      'offer_id' => $o['id'] ?? null, 'offer_title' => $o['title'] ?? null,
                      'why' => $this->explainForOffer($n, $o ?: $pseudo), 'expires' => self::left($n['expires'])];
        }
        return $out;
    }

    /** Свайп: влево — больше не показывать, вправо — откликнуться (от предложения, если оно есть). */
    public function swipe(int $uid, int $bidId, string $dir, string $msg = '', int $offerId = 0): array
    {
        if (!in_array($dir, ['skip', 'apply'], true)) throw new InvalidArgumentException('Неизвестное действие');
        $need = $this->needById($bidId);
        if (!$need) throw new InvalidArgumentException('Заявка уже снята');
        if ($need['user_id'] === $uid) throw new InvalidArgumentException('Это ваша заявка');
        if ($this->x->has('swipes')) {
            $this->db->prepare("INSERT INTO swipes (user_id, bid_id, dir) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE dir = VALUES(dir), created_at = NOW()")
                ->execute([$uid, $bidId, $dir]);
        }
        if ($dir === 'skip') return ['ok' => true];

        $offer = $offerId ? ($this->offers()[$offerId] ?? null) : null;
        if ($offer && $offer['user_id'] === $uid) {
            $r = $this->propose($uid, $bidId, $offerId);
            if (trim($msg) !== '') $this->respond($uid, $bidId, $msg);
            return $r;
        }
        $this->respond($uid, $bidId, trim($msg) !== '' ? $msg : 'Хочу присоединиться — посмотрите мой профиль');
        $this->x->notify([$need['user_id']], 'Новый отклик', $this->x->userName($uid) . ' откликнулся на «' . $need['title'] . '»', '/l4t/?tab=bids&pos=need-' . $bidId);
        return ['ok' => true];
    }

    /** Вернуть последний пропуск — промахнуться свайпом легко. */
    public function swipeUndo(int $uid, int $bidId): void
    {
        if ($this->x->has('swipes')) $this->db->prepare("DELETE FROM swipes WHERE user_id = ? AND bid_id = ? AND dir = 'skip'")->execute([$uid, $bidId]);
    }

    /** «Не подходит» по паре заявка × предложение — с моей стороны. Пара больше не всплывёт. */
    public function hidePair(int $uid, int $bidId, int $offerId): void
    {
        if (!$this->ready()) return;
        $b = (int)$this->x->val($this->db, "SELECT bidder_id FROM bids WHERE id = ?", [$bidId]);
        $o = (int)$this->x->val($this->db, "SELECT user_id FROM offers WHERE id = ?", [$offerId]);
        $col = $b === $uid ? 'need_state' : ($o === $uid ? 'offer_state' : null);
        if (!$col) throw new InvalidArgumentException('Это не ваша позиция');
        $this->db->prepare("INSERT INTO matches (bid_id, offer_id, score, reasons, initiator, $col) VALUES (?, ?, 0, '', 'engine', 'no')
                            ON DUPLICATE KEY UPDATE $col = 'no'")->execute([$bidId, $offerId]);
    }

    /* ═════════════════════════════ ТАЙМЕР И УДАЛЕНИЕ ═════════════════════════════ */

    /** «ещё 3 дн», «ещё 5 ч», «истекла». */
    public static function left(?string $ts): ?array
    {
        if (!$ts) return null;
        $d = strtotime($ts) - time();
        if ($d <= 0) return ['expired', 'истекла'];
        if ($d < 3600) return ['soon', 'ещё ' . max(1, intdiv($d, 60)) . ' мин'];
        if ($d < 86400) return ['soon', 'ещё ' . intdiv($d, 3600) . ' ч'];
        $n = intdiv($d, 86400);
        return [$n <= 2 ? 'soon' : 'ok', 'ещё ' . $n . ' ' . self::plural($n, 'день', 'дня', 'дней')];
    }

    /**
     * Снимаем истёкшие позиции и один раз говорим автору: «продлите, если ещё актуально».
     * Зовётся лениво при открытии страницы — крон не нужен. Дешёво: два UPDATE по индексу.
     */
    public function sweep(): int
    {
        if (!$this->ready() || !$this->v10('bids')) return 0;
        $total = 0;
        foreach (['bids' => ['bidder_id', 'search_role', 'need'], 'offers' => ['user_id', 'title', 'offer']] as $t => [$own, $title, $pfx]) {
            $rows = $this->x->rows($this->db, "SELECT id, $own uid, $title title FROM $t WHERE stage = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW() LIMIT 200");
            if (!$rows) continue;
            $ids = array_map('intval', array_column($rows, 'id'));
            $this->db->exec("UPDATE $t SET stage = 'closed', close_reason = 'expired' WHERE id IN (" . implode(',', $ids) . ")");
            foreach ($rows as $r) {
                $this->x->notify([(int)$r['uid']], 'Позиция снята по таймеру', '«' . $r['title'] . '» — время вышло. Если ещё актуально, продлите одной кнопкой.', '/l4t/?tab=bids&pos=' . $pfx . '-' . $r['id']);
            }
            $total += count($ids);
        }
        if ($total) $this->needsCache = $this->offersCache = null;
        return $total;
    }

    /** Продлить или вернуть на рынок на N дней. */
    public function extend(int $uid, string $side, int $id, int $days): void
    {
        [$t, $own] = $side === 'offer' ? ['offers', 'user_id'] : ['bids', 'bidder_id'];
        $row = $this->x->row($this->db, "SELECT * FROM $t WHERE id = ? AND $own = ?", [$id, $uid]);
        if (!$row) throw new InvalidArgumentException('Позиция не найдена');
        if (($row['close_reason'] ?? '') === 'admin') throw new InvalidArgumentException('Позицию скрыл модератор: ' . ($row['mod_reason'] ?? '') . '. Создайте новую, исправив причину.');
        $this->setStage($t, $own, $uid, $id, 'active', self::lifetime($days));
        $side === 'offer' ? $this->matchOffer($id) : $this->matchNeed($id);
    }

    /** Удаление автором. Мягкое: история сделок и титров не должна ломаться. */
    public function remove(int $uid, string $side, int $id): void
    {
        [$t, $own] = $side === 'offer' ? ['offers', 'user_id'] : ['bids', 'bidder_id'];
        if (!$this->x->has($t, 'deleted_at')) {
            $this->setStage($t, $own, $uid, $id, 'closed', null);
            return;
        }
        $this->db->prepare("UPDATE $t SET stage = 'closed', close_reason = 'deleted', deleted_at = NOW() WHERE id = ? AND $own = ?")->execute([$id, $uid]);
        $this->needsCache = $this->offersCache = null;
    }

    /* ═════════════════════════════ МОДЕРАЦИЯ ═════════════════════════════ */

    public const MOD_REASONS = ['stale' => 'Неактуально', 'rules' => 'Нарушает правила', 'dup' => 'Дубль', 'spam' => 'Спам'];

    /** hide — снять с рынка (автор увидит причину), delete — убрать совсем. Причина обязательна. */
    public function moderate(int $admin, string $side, int $id, string $action, string $reason): void
    {
        if (!in_array($action, ['hide', 'delete', 'restore'], true)) throw new InvalidArgumentException('Неизвестное действие');
        $reason = mb_substr(trim(strip_tags($reason)), 0, 300);
        if ($action !== 'restore' && mb_strlen($reason) < 3) throw new InvalidArgumentException('Напишите причину — автор её увидит');
        if (!$this->v10('bids')) throw new InvalidArgumentException('Нужна миграция 010');
        [$t, $own, $title] = $side === 'offer' ? ['offers', 'user_id', 'title'] : ['bids', 'bidder_id', 'search_role'];
        $row = $this->x->row($this->db, "SELECT $own uid, $title title FROM $t WHERE id = ?", [$id]);
        if (!$row) throw new InvalidArgumentException('Позиция не найдена');

        if ($action === 'restore') {
            $this->db->prepare("UPDATE $t SET stage = 'active', close_reason = NULL, mod_reason = NULL, mod_by = ?, mod_at = NOW(), deleted_at = NULL,
                                expires_at = GREATEST(COALESCE(expires_at, NOW()), NOW() + INTERVAL 1 DAY) WHERE id = ?")->execute([$admin, $id]);
            $this->x->notify([(int)$row['uid']], 'Позиция возвращена на рынок', '«' . $row['title'] . '»', '/l4t/?tab=bids');
        } else {
            $del = $action === 'delete' ? ', deleted_at = NOW()' : '';
            $this->db->prepare("UPDATE $t SET stage = 'closed', close_reason = ?, mod_reason = ?, mod_by = ?, mod_at = NOW()$del WHERE id = ?")
                ->execute([$action === 'delete' ? 'deleted' : 'admin', $reason, $admin, $id]);
            $this->x->notify([(int)$row['uid']], $action === 'delete' ? 'Модератор удалил позицию' : 'Модератор снял позицию с рынка',
                '«' . $row['title'] . '». Причина: ' . $reason, '/l4t/?tab=bids');
        }
        $this->needsCache = $this->offersCache = null;
    }

    /** Список для модератора. filter: all | old (старше 30 дн) | hidden */
    public function adminList(string $filter = 'all', string $q = ''): array
    {
        if (!$this->ready()) return [];
        $v10 = $this->v10('bids');
        $cond = [
            'all'    => "stage = 'active'",
            'old'    => "stage = 'active' AND created_at < NOW() - INTERVAL 30 DAY",
            'hidden' => $v10 ? "close_reason IN ('admin','deleted') AND mod_by IS NOT NULL" : '0',
        ][$filter] ?? "stage = 'active'";
        $out = [];
        foreach (['bids' => ['need', 'bidder_id', 'search_role'], 'offers' => ['offer', 'user_id', 'title']] as $t => [$side, $own, $title]) {
            $w = $cond . ($filter !== 'hidden' && $this->x->has($t, 'deleted_at') ? ' AND deleted_at IS NULL' : '');
            $p = [];
            if ($q !== '') { $w .= " AND $title LIKE ?"; $p[] = '%' . $q . '%'; }
            $extra = $v10 ? ', close_reason, mod_reason, mod_at, expires_at, deleted_at' : ', expires_at';
            foreach ($this->x->rows($this->db, "SELECT id, $own uid, $title title, stage, created_at$extra FROM $t WHERE $w ORDER BY created_at ASC LIMIT 150", $p) as $r) {
                $out[] = ['side' => $side, 'id' => (int)$r['id'], 'uid' => (int)$r['uid'], 'title' => (string)$r['title'], 'stage' => $r['stage'],
                          'age' => self::ago((string)$r['created_at']), 'created' => $r['created_at'], 'left' => self::left($r['expires_at'] ?? null),
                          'close_reason' => $r['close_reason'] ?? null, 'mod_reason' => $r['mod_reason'] ?? null, 'deleted' => !empty($r['deleted_at'])];
            }
        }
        usort($out, fn($a, $b) => strcmp((string)$a['created'], (string)$b['created']));
        $users = $this->x->users(array_column($out, 'uid'));
        foreach ($out as &$r) $r['user'] = $users[$r['uid']]['name'] ?? '—';
        return $out;
    }

    /* ═════════════════════════════ МОИ ПОЗИЦИИ ═════════════════════════════ */

    /**
     * Левая колонка рабочего места: все мои заявки и предложения
     * (живые и снятые за последние 60 дней) с таймером и числом кандидатов.
     */
    public function myPositions(int $uid): array
    {
        $out = [];
        $v10 = $this->v10('bids');
        $del = $this->x->has('bids', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $cols = $v10 ? ', close_reason, mod_reason, lifetime_days' : '';
        $bids = $this->x->rows($this->db, "SELECT id, search_role, stage, created_at, expires_at, responses$cols FROM bids
                                            WHERE bidder_id = ?$del AND (stage = 'active' OR created_at >= NOW() - INTERVAL 60 DAY)
                                            ORDER BY stage = 'active' DESC, created_at DESC LIMIT 50", [$uid]);
        $newCnt = [];
        if ($this->ready() && $bids) {
            $in = implode(',', array_map('intval', array_column($bids, 'id')));
            foreach ($this->x->rows($this->db, "SELECT bid_id, COUNT(*) n, SUM(offer_state = 'yes' AND need_state = 'new') w FROM matches
                                                 WHERE bid_id IN ($in) AND need_state <> 'no' GROUP BY bid_id") as $r) $newCnt[(int)$r['bid_id']] = $r;
        }
        foreach ($bids as $b) {
            $live = $b['stage'] === 'active' && (!$b['expires_at'] || strtotime($b['expires_at']) > time());
            $out[] = ['side' => 'need', 'id' => (int)$b['id'], 'title' => (string)$b['search_role'], 'live' => $live,
                      'left' => $live ? self::left($b['expires_at']) : null, 'reason' => $live ? null : ($b['close_reason'] ?? ($b['stage'] === 'active' ? 'expired' : 'owner')),
                      'mod_reason' => $b['mod_reason'] ?? null, 'lifetime' => (int)($b['lifetime_days'] ?? 30),
                      'count' => (int)($newCnt[(int)$b['id']]['n'] ?? 0) + (int)$b['responses'],
                      'attention' => (int)($newCnt[(int)$b['id']]['w'] ?? 0)];
        }
        $delO = $this->x->has('offers', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        foreach ($this->x->rows($this->db, "SELECT * FROM offers WHERE user_id = ?$delO AND (stage = 'active' OR created_at >= NOW() - INTERVAL 60 DAY)
                                             ORDER BY stage = 'active' DESC, created_at DESC LIMIT 20", [$uid]) as $o) {
            $live = $o['stage'] === 'active' && (!$o['expires_at'] || strtotime($o['expires_at']) > time());
            $n = $this->ready() ? $this->x->row($this->db, "SELECT COUNT(*) n, SUM(need_state = 'yes' AND offer_state = 'new') w FROM matches WHERE offer_id = ? AND offer_state <> 'no'", [(int)$o['id']]) : null;
            $out[] = ['side' => 'offer', 'id' => (int)$o['id'], 'title' => (string)$o['title'], 'live' => $live,
                      'left' => $live ? self::left($o['expires_at']) : null, 'reason' => $live ? null : ($o['close_reason'] ?? ($o['stage'] === 'active' ? 'expired' : 'owner')),
                      'mod_reason' => $o['mod_reason'] ?? null, 'lifetime' => (int)($o['lifetime_days'] ?? 30),
                      'count' => (int)($n['n'] ?? 0), 'attention' => (int)($n['w'] ?? 0)];
        }
        return $out;
    }

    /* ═════════════════════════════ СТАКАН ═════════════════════════════ */

    /** Котировки: по каждому навыку — глубина спроса и предложения, лучшие цены, спред. */
    public function quotes(): array
    {
        $all = $this->x->skills();
        $q = [];
        $add = function (array $o) use (&$q, $all) {
            foreach ($o['skills'] as $s) {
                if (!isset($all[$s])) continue;
                $r = &$q[$s];
                $r ??= ['slug' => $s, 'name' => $all[$s]['name'], 'grp' => $all[$s]['grp'], 'kind' => $all[$s]['kind'],
                        'needs' => 0, 'offers' => 0, 'best_need' => null, 'best_offer' => null, 'nonmoney' => 0];
                if ($o['side'] === 'need') {
                    $r['needs']++;
                    if ($o['pay'] === 'money' && $o['hi'] !== null) $r['best_need'] = max($r['best_need'] ?? 0, $o['hi']);
                } else {
                    $r['offers']++;
                    if ($o['pay'] === 'money' && $o['lo'] !== null) $r['best_offer'] = min($r['best_offer'] ?? PHP_INT_MAX, $o['lo']);
                }
                if ($o['pay'] !== 'money') $r['nonmoney']++;
                unset($r);
            }
        };
        foreach ($this->needs() as $n) $add($n);
        foreach ($this->offers() as $o) $add($o);

        foreach ($q as &$r) {
            $r['spread'] = ($r['best_need'] !== null && $r['best_offer'] !== null) ? $r['best_offer'] - $r['best_need'] : null;
            $r['crossed'] = $r['spread'] !== null && $r['spread'] <= 0;
            // дисбаланс: >0 — спроса больше (дефицит специалистов), <0 — избыток
            $r['balance'] = ($r['needs'] - $r['offers']) / max(1, $r['needs'] + $r['offers']);
        }
        unset($r);
        // роли — сверху (это «инструменты» рынка), движки и программы — ниже; внутри — по обороту
        uasort($q, fn($a, $b) => [$b['kind'] === 'role', $b['needs'] + $b['offers']] <=> [$a['kind'] === 'role', $a['needs'] + $a['offers']]);
        return array_values($q);
    }

    /**
     * Стакан по одному навыку. Денежные позиции группируются в ценовые уровни
     * (шаг зависит от порядка цены), для каждого уровня — количество и накопленная
     * глубина, как в биржевом стакане.
     */
    public function book(string $skill, int $viewer = 0): array
    {
        $side = function (array $list, string $which) use ($skill, $viewer) {
            $money = []; $other = []; $nego = [];
            foreach ($list as $o) {
                if (!in_array($skill, $o['skills'], true)) continue;
                $card = $this->card($o, $viewer);
                if ($o['pay'] !== 'money') { $other[] = $card; continue; }
                $price = $which === 'need' ? $o['hi'] : $o['lo'];
                if ($price === null) { $nego[] = $card; continue; }
                $card['price'] = $price;
                $money[] = $card;
            }
            usort($money, fn($a, $b) => $which === 'need' ? $b['price'] <=> $a['price'] : $a['price'] <=> $b['price']);

            // уровни цен
            $levels = [];
            foreach ($money as $c) {
                $p = $c['price'];
                $step = $p < 10000 ? 1000 : ($p < 50000 ? 5000 : ($p < 200000 ? 10000 : 50000));
                $lvl = $which === 'need' ? (int)(floor($p / $step) * $step) : (int)(ceil($p / $step) * $step);
                $levels[$lvl] ??= ['price' => $lvl, 'count' => 0, 'orders' => []];
                $levels[$lvl]['count']++;
                $levels[$lvl]['orders'][] = $c;
            }
            $levels = array_values($levels);
            $cum = 0;
            foreach ($levels as &$l) { $cum += $l['count']; $l['depth'] = $cum; }
            unset($l);
            return ['levels' => $levels, 'other' => $other, 'nego' => $nego, 'best' => $money[0]['price'] ?? null, 'total' => count($money) + count($other) + count($nego)];
        };

        $need = $side($this->needs(), 'need');
        $offer = $side($this->offers(), 'offer');
        $spread = ($need['best'] !== null && $offer['best'] !== null) ? $offer['best'] - $need['best'] : null;
        $maxDepth = max(1, end($need['levels'])['depth'] ?? 1, end($offer['levels'])['depth'] ?? 1);

        return [
            'skill'  => $skill,
            'name'   => $this->x->skills()[$skill]['name'] ?? $skill,
            'need'   => $need,
            'offer'  => $offer,
            'spread' => $spread,
            'crossed'=> $spread !== null && $spread <= 0,
            'mid'    => ($need['best'] !== null && $offer['best'] !== null) ? (int)round(($need['best'] + $offer['best']) / 2) : null,
            'max_depth' => $maxDepth,
        ];
    }

    /** Карточка позиции для стакана и модалки. */
    public function card(array $o, int $viewer = 0): array
    {
        static $users = [];
        $uid = $o['user_id'];
        $users[$uid] ??= $this->x->users([$uid], true)[$uid] ?? ['name' => 'Пользователь', 'handle' => '', 'avatar' => '', 'role' => ''];
        return [
            'side' => $o['side'], 'id' => $o['id'], 'title' => $o['title'], 'kind' => $o['kind'], 'pay' => $o['pay'],
            'lo' => $o['lo'], 'hi' => $o['hi'], 'price_label' => self::priceLabel($o),
            'skills' => array_map(fn($s) => $this->x->skills()[$s]['name'] ?? $s, $o['skills']),
            'details' => mb_substr($o['details'], 0, 600),
            'extra' => $o['extra'] ?? '',
            'days' => $o['days'] ?? null, 'from' => $o['from'] ?? null, 'hours' => $o['hours'] ?? null,
            'age' => self::ago($o['created']),
            'user' => $users[$uid] + ['id' => $uid],
            'mine' => $viewer && $viewer === $uid,
        ];
    }

    public static function priceLabel(array $o): string
    {
        if ($o['pay'] !== 'money') return self::PAY[$o['pay']] ?? '';
        $k = fn(int $v) => $v >= 1000 ? rtrim(rtrim(number_format($v / 1000, 1, ',', ''), '0'), ',') . 'k' : (string)$v;
        if ($o['lo'] !== null && $o['hi'] !== null) return $o['lo'] === $o['hi'] ? $k($o['lo']) . ' ₽' : $k($o['lo']) . '–' . $k($o['hi']) . ' ₽';
        if ($o['lo'] !== null) return ($o['side'] === 'offer' ? 'от ' : '') . $k($o['lo']) . ' ₽';
        if ($o['hi'] !== null) return 'до ' . $k($o['hi']) . ' ₽';
        return 'договорная';
    }

    public static function ago(string $ts): string
    {
        $d = time() - (strtotime($ts) ?: time());
        if ($d < 3600) return max(1, intdiv($d, 60)) . ' мин';
        if ($d < 86400) return intdiv($d, 3600) . ' ч';
        return intdiv($d, 86400) . ' дн';
    }

    /** Лента сделок — анонимно: инструмент, формат, цена, время. */
    public function tape(int $limit = 12): array
    {
        if (!$this->ready()) return [];
        $rows = $this->x->rows($this->db, "SELECT m.dealt_at, b.kind, b.pay_type, b.budget_min, b.budget_max, o.price_min, o.price_max, o.pay_type o_pay, m.offer_id, m.bid_id
                                             FROM matches m JOIN bids b ON b.id = m.bid_id JOIN offers o ON o.id = m.offer_id
                                            WHERE m.dealt_at IS NOT NULL ORDER BY m.dealt_at DESC LIMIT " . (int)$limit);
        $sk = $this->offerSkills(array_column($rows, 'offer_id'));
        $all = $this->x->skills();
        $out = [];
        foreach ($rows as $r) {
            $slug = $sk[(int)$r['offer_id']][0] ?? null;
            $price = $r['pay_type'] === 'money' && ($r['budget_max'] ?? $r['budget_min']) !== null
                ? self::priceLabel(['pay' => 'money', 'side' => 'need', 'lo' => $r['budget_min'] !== null ? (int)$r['budget_min'] : null, 'hi' => $r['budget_max'] !== null ? (int)$r['budget_max'] : null])
                : (self::PAY[$r['pay_type'] ?: 'money'] ?? '');
            $out[] = ['skill' => $slug ? ($all[$slug]['name'] ?? $slug) : '—', 'kind' => self::KINDS[$r['kind']] ?? '', 'price' => $price, 'ago' => self::ago((string)$r['dealt_at'])];
        }
        return $out;
    }

    /** Сводка рынка: для шапки стакана и для GPI. */
    public function summary(): array
    {
        $deals7 = $this->ready() ? (int)$this->x->val($this->db, "SELECT COUNT(*) FROM matches WHERE dealt_at >= NOW() - INTERVAL 7 DAY") : 0;
        return ['needs' => count($this->needs()), 'offers' => count($this->offers()), 'deals7' => $deals7];
    }
}
