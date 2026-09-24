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
        $this->db->prepare("UPDATE bids SET kind = ?, pay_type = ?, budget_min = ?, budget_max = ?, duration_days = ?,
                                     expires_at = CURDATE() + INTERVAL " . self::TTL_DAYS . " DAY WHERE id = ?")
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

        if ($id) {
            $own = $this->x->val($this->db, "SELECT user_id FROM offers WHERE id = ?", [$id]);
            if ((int)$own !== $uid) throw new InvalidArgumentException('Это не ваше предложение');
            $this->db->prepare("UPDATE offers SET title=?, kind=?, pay_type=?, price_min=?, price_max=?, available_from=?, hours_week=?,
                                       details=?, stage='active', expires_at = CURDATE() + INTERVAL " . self::TTL_DAYS . " DAY WHERE id=?")
                ->execute([$title, $kind, $pay, $lo, $hi, $from, $hours, $details, $id]);
        } else {
            $n = (int)$this->x->val($this->db, "SELECT COUNT(*) FROM offers WHERE user_id = ? AND stage = 'active'", [$uid]);
            if ($n >= 5) throw new InvalidArgumentException('Не больше пяти активных предложений — снимите лишнее');
            $this->db->prepare("INSERT INTO offers (user_id, title, kind, pay_type, price_min, price_max, available_from, hours_week, details, expires_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE() + INTERVAL " . self::TTL_DAYS . " DAY)")
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
        $extra = $stage === 'active' ? ", expires_at = CURDATE() + INTERVAL " . self::TTL_DAYS . " DAY" : '';
        $this->db->prepare("UPDATE offers SET stage = ?$extra WHERE id = ? AND user_id = ?")->execute([$stage, $id, $uid]);
        if ($stage === 'active') $this->matchOffer($id);
    }

    public function setNeedStage(int $uid, int $id, string $stage): void
    {
        if (!in_array($stage, ['active', 'closed'], true)) throw new InvalidArgumentException('Неизвестный статус');
        $extra = $stage === 'active' && $this->x->has('bids', 'expires_at') ? ", expires_at = CURDATE() + INTERVAL " . self::TTL_DAYS . " DAY" : '';
        $this->db->prepare("UPDATE bids SET stage = ?$extra WHERE id = ? AND bidder_id = ?")->execute([$stage, $id, $uid]);
        if ($stage === 'active') $this->matchNeed($id);
    }

    /* ═════════════════════════════ ЧТЕНИЕ СТОРОН ═════════════════════════════ */

    /** Активный спрос в нормализованном виде: id => [...] */
    public function needs(): array
    {
        if ($this->needsCache !== null) return $this->needsCache;
        $this->needsCache = [];
        if (!$this->ready()) return [];
        $rows = $this->x->rows($this->db, "SELECT * FROM bids WHERE stage = 'active' AND (expires_at IS NULL OR expires_at >= CURDATE())
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
            ];
        }
        return $this->needsCache;
    }

    public function offers(): array
    {
        if ($this->offersCache !== null) return $this->offersCache;
        $this->offersCache = [];
        if (!$this->ready()) return [];
        $rows = $this->x->rows($this->db, "SELECT * FROM offers WHERE stage = 'active' AND (expires_at IS NULL OR expires_at >= CURDATE())
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
