<?php
declare(strict_types=1);

/**
 * l4t/api/action.php — единая точка записи для новых функций L4T.
 *
 * Один эндпоинт с диспетчером по op вместо двадцати файлов: одна проверка
 * авторизации, один CSRF, один формат ответа {ok, ...} / {ok:false, error}.
 * Бизнес-логика — в lib/extras.php и lib/match.php, здесь только маршрут.
 *
 * GET-операции (без побочных эффектов, без CSRF): pass, people, queue.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../swad/config.php';
require_once __DIR__ . '/../../swad/controllers/l4t/_csrf.php';
require_once __DIR__ . '/../lib/extras.php';
require_once __DIR__ . '/../lib/match.php';
require_once __DIR__ . '/../lib/market.php';

function out(array $d, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$uid     = !empty($_SESSION['USERDATA']['id']) ? (int)$_SESSION['USERDATA']['id'] : 0;
$isAdmin = ((int)($_SESSION['USERDATA']['global_role'] ?? 0)) === -1;
$isGet   = $_SERVER['REQUEST_METHOD'] === 'GET';
$in      = $isGet ? $_GET : (json_decode((string)file_get_contents('php://input'), true) ?: $_POST);
$op      = (string)($in['op'] ?? '');

$public = ['people', 'quotes', 'book', 'offer_view'];                                         // доступно гостям
if (!$uid && !in_array($op, $public, true)) out(['ok' => false, 'error' => 'Нужно войти в аккаунт'], 401);
if (!$isGet) csrf_guard_json($in);

$db   = new Database();
$main = $db->connect();
$l4t  = $db->connect('desl4t');
foreach ([$main, $l4t] as $c) $c->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$x = new L4TX($main, $l4t);
$m = new L4TMatch($x, $main);
$mk = new L4TMarket($x, $main);
$i = fn(string $k) => (int)($in[$k] ?? 0);
$s = fn(string $k) => (string)($in[$k] ?? '');

try {
    switch ($op) {

        /* ── чтение ─────────────────────────────────────────────── */
        case 'pass':
            out(['ok' => true, 'token' => L4TX::passToken($uid), 'ttl' => L4TX::passTtl()]);

        case 'people':
            out(['ok' => true, 'items' => $m->people($s('skill'), $s('q'), $uid), 'skills' => array_map(fn($z) => $z['name'], $x->skills())]);

        case 'queue':
            $sp = $x->row($main, "SELECT host_user_id FROM sprints WHERE id = ?", [$i('sprint_id')]);
            if (!$sp || (!$isAdmin && (int)$sp['host_user_id'] !== $uid)) out(['ok' => false, 'error' => 'Нет доступа'], 403);
            out(['ok' => true, 'items' => $m->queue($i('sprint_id'))]);

        /* ── рынок: чтение (публично) ────────────────────────────── */
        case 'quotes':
            out(['ok' => true, 'quotes' => $mk->quotes(), 'tape' => $mk->tape(), 'summary' => $mk->summary()]);

        case 'book':
            $slug = $s('skill');
            if (!isset($x->skills()[$slug])) out(['ok' => false, 'error' => 'Нет такого навыка'], 404);
            out(['ok' => true, 'book' => $mk->book($slug, $uid)]);

        case 'offer_view':                       // просмотр предложения из стакана: раз в сессию
            $oid = $i('id');
            if ($oid > 0 && empty($_SESSION['l4t_ov'][$oid])) {
                $_SESSION['l4t_ov'][$oid] = 1;
                $l4t->prepare("UPDATE offers SET views = views + 1 WHERE id = ? AND user_id <> ?")->execute([$oid, $uid]);
            }
            out(['ok' => true]);

        case 'my_orders':                        // для «Предложить задачу» / «Предложить себя»
            $needs = array_values(array_filter($mk->needs(), fn($n) => $n['user_id'] === $uid));
            $offs  = array_values(array_filter($mk->myOffers($uid), fn($o) => $o['stage'] === 'active'));
            out(['ok' => true, 'needs' => array_map(fn($n) => ['id' => $n['id'], 'title' => $n['title']], $needs),
                 'offers' => array_map(fn($o) => ['id' => $o['id'], 'title' => $o['title']], $offs)]);

        /* ── рынок: запись ──────────────────────────────────────── */
        case 'offer_save':
            $id = $mk->saveOffer($uid, $in, $i('id'));
            out(['ok' => true, 'id' => $id]);

        case 'offer_stage':
            $mk->setOfferStage($uid, $i('id'), $s('stage'));
            out(['ok' => true]);

        case 'need_stage':
            $mk->setNeedStage($uid, $i('id'), $s('stage'));
            out(['ok' => true]);

        case 'match_answer':
            out(['ok' => true] + $mk->answer($uid, $i('id'), !empty($in['yes'])));

        case 'propose':
            out(['ok' => true] + $mk->propose($uid, $i('bid_id'), $i('offer_id')));

        /* ── навыки и профиль ───────────────────────────────────── */
        case 'skills_save':
            $saved = $x->saveUserSkills($uid, (array)($in['skills'] ?? []));
            $x->matchSavedSearches($uid);
            out(['ok' => true, 'skills' => $saved]);

        case 'profile_extra':
            out(['ok' => true, 'data' => $x->saveProfileExtra($uid, $in)]);

        case 'avail_renew':
            $x->renewAvailability($uid);
            out(['ok' => true]);

        /* ── опыт ───────────────────────────────────────────────── */
        case 'exp_save':
            out(['ok' => true] + $x->saveExperience($uid, $in, $i('id')));

        case 'exp_delete':
            $x->deleteExperience($uid, $i('id'));
            out(['ok' => true]);

        case 'exp_verify':
            $x->verifyExperience($uid, $i('id'), !empty($in['approve']));
            out(['ok' => true]);

        /* ── титры ──────────────────────────────────────────────── */
        case 'credit_add':
            out(['ok' => true, 'id' => $x->addCredit($uid, $in)]);

        case 'credit_hide':
            $x->toggleCredit($uid, $i('id'), !empty($in['hide']));
            out(['ok' => true]);

        case 'games_search':                     // автодополнение игры для титра
            $q = trim($s('q'));
            if (mb_strlen($q) < 2) out(['ok' => true, 'items' => []]);
            out(['ok' => true, 'items' => $x->rows($main, "SELECT id, name FROM games WHERE status = 'published' AND name LIKE ? ORDER BY name LIMIT 8",
                ['%' . addcslashes($q, '%_\\') . '%'])]);

        /* ── рекомендации ───────────────────────────────────────── */
        case 'rec_save':
            $x->saveRecommendation($uid, $i('target_id'), $in);
            out(['ok' => true]);

        case 'rec_hide':
            $x->hideRecommendation($uid, $i('id'), !empty($in['hide']));
            out(['ok' => true]);

        /* ── отклики ────────────────────────────────────────────── */
        case 'respond_status':
            $x->setRespondStatus($uid, $i('id'), $s('status'));
            out(['ok' => true]);

        /* ── заявки: навыки ─────────────────────────────────────── */
        case 'bid_skills':
            $own = $x->val($l4t, "SELECT bidder_id FROM bids WHERE id = ?", [$i('bid_id')]);
            if ((int)$own !== $uid) out(['ok' => false, 'error' => 'Не ваша заявка'], 403);
            $x->saveBidSkills($i('bid_id'), (array)($in['skills'] ?? []));
            out(['ok' => true]);

        /* ── знакомства, ссылки ─────────────────────────────────── */
        case 'contact_add':
            $ev = $x->addContact($uid, $i('user_id'));
            out(['ok' => true, 'event' => $ev]);

        case 'contact_note':
            $x->noteContact($uid, $i('user_id'), $s('note'));
            out(['ok' => true]);

        case 'link_create':
            out(['ok' => true] + $x->createShareLink($uid, $s('label')));

        case 'link_revoke':
            $x->revokeShareLink($uid, $i('id'));
            out(['ok' => true]);

        case 'search_save':
            $x->saveSearch($uid, $s('skill'), $s('q'));
            out(['ok' => true]);

        case 'search_delete':
            $x->deleteSearch($uid, $i('id'));
            out(['ok' => true]);

        /* ── мероприятия ────────────────────────────────────────── */
        case 'event_create':
            out(['ok' => true, 'id' => $x->createEvent($uid, $in)]);

        case 'checkin':
            out(['ok' => true, 'guest' => $x->checkin($uid, $i('event_id'), $s('token'))]);

        /* ── очередь джема ──────────────────────────────────────── */
        case 'queue_join':
            $m->queueJoin($uid, $i('sprint_id'), $s('grp'), isset($in['tz']) && $in['tz'] !== '' ? (int)$in['tz'] : null, $s('note'));
            out(['ok' => true]);

        case 'queue_leave':
            $m->queueLeave($uid, $i('sprint_id'));
            out(['ok' => true]);

        case 'teams_form':
            out(['ok' => true, 'teams' => $m->formTeams($i('sprint_id'), $uid, $isAdmin, max(2, min(6, $i('size') ?: 4)))]);

        default:
            out(['ok' => false, 'error' => 'unknown op'], 400);
    }
} catch (InvalidArgumentException $e) {
    out(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('[l4t/action] ' . $op . ': ' . $e->getMessage());
    out(['ok' => false, 'error' => 'Ошибка сервера'], 500);
}
