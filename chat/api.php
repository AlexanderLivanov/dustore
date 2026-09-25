<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../swad/config.php';   // CONFIRM
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_bridge.php';
if (is_file(__DIR__ . '/push_helpers.php')) require_once __DIR__ . '/push_helpers.php';
if (is_file(__DIR__ . '/ws_helpers.php')) require_once __DIR__ . '/ws_helpers.php';
require_once __DIR__ . '/_crypto.php';
require_once __DIR__ . '/_files.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Чат v2 (файлы, ответы, пины, звук) включается сам, как только прогнана
 * миграция chat/migrate_v2.php. До этого код работает по-старому и не падает.
 * Положительный результат кэшируем в сессии, отрицательный — нет: после
 * миграции фичи включатся на следующем же запросе.
 */
function chat_v2(PDO $db): bool {
    if (($_SESSION['chat_v2'] ?? false) === true) return true;
    try {
        $db->query("SELECT reply_to_id, file_id FROM messages LIMIT 0");
        $db->query("SELECT 1 FROM chat_files LIMIT 0");
        $db->query("SELECT 1 FROM conversation_pins LIMIT 0");
        $db->query("SELECT 1 FROM chat_user_settings LIMIT 0");
    } catch (PDOException $e) {
        return false;
    }
    return $_SESSION['chat_v2'] = true;
}
$V2 = chat_v2($db);

/** v3: «доставлено» + обои. Тот же приём, что и у v2: включается миграцией. */
function chat_v3(PDO $db): bool {
    if (($_SESSION['chat_v3'] ?? false) === true) return true;
    try {
        $db->query("SELECT last_delivered_message_id FROM conversation_participants LIMIT 0");
        $db->query("SELECT studio_last_delivered_id FROM conversations LIMIT 0");
        $db->query("SELECT 1 FROM conversation_wallpapers LIMIT 0");
    } catch (PDOException $e) {
        return false;
    }
    return $_SESSION['chat_v3'] = true;
}
$V3 = $V2 && chat_v3($db);

function out($d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

// сессия потерялась (PWA выгружено, PHPSESSID умер), а auth_token жив — восстанавливаем,
// как header.php на десктопе; иначе чат в приложении внезапно «разлогинивается»
if (empty($_SESSION['USERDATA']) && !empty($_COOKIE['auth_token'])) {
    try { require_once __DIR__ . '/../swad/controllers/user.php'; (new User())->checkAuth(); } catch (Throwable $e) { }
}
if (empty($_SESSION['USERDATA'])) out(['ok' => false, 'error' => 'auth']);
$me   = $_SESSION['USERDATA'];
$myId = (int)($me['id'] ?? 0);
if ($myId <= 0) out(['ok' => false, 'error' => 'auth']);

// last-seen: троттлим апдейт активности (не чаще раза в 30с)
$db->prepare("UPDATE users SET last_activity=NOW()
               WHERE id=? AND (last_activity IS NULL OR last_activity < (NOW() - INTERVAL 30 SECOND))")->execute([$myId]);

$myStudioIds = get_user_studio_ids($db, $myId);
$action      = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * «Доставлено» = клиент получателя скачал беседу до этого сообщения.
 * Двигаем указатель, когда получатель вообще онлайн на сайте: хедер опрашивает
 * unread_total, открыт список чатов или тред. Как в TG: две серые галочки —
 * «дошло до устройства», две яркие — «открыл и увидел».
 * Один UPDATE…JOIN на все беседы разом, трогает только отставшие строки.
 * Троттлинг через сессию — не чаще раза в 4 секунды на пользователя.
 */
function mark_delivered(PDO $db, int $myId, array $myStudioIds, bool $force = false): void {
    $now = time();
    if (!$force && ($_SESSION['chat_dlv_at'] ?? 0) > $now - 4) return;
    $_SESSION['chat_dlv_at'] = $now;
    $db->prepare("UPDATE conversation_participants p JOIN conversations c ON c.id = p.conversation_id
                     SET p.last_delivered_message_id = c.last_message_id
                   WHERE p.user_id = ? AND c.last_message_id IS NOT NULL
                     AND COALESCE(p.last_delivered_message_id, 0) < c.last_message_id")->execute([$myId]);
    if ($myStudioIds) {
        $in = implode(',', array_fill(0, count($myStudioIds), '?'));
        $db->prepare("UPDATE conversations SET studio_last_delivered_id = last_message_id
                       WHERE type='studio' AND studio_id IN ($in) AND last_message_id IS NOT NULL
                         AND COALESCE(studio_last_delivered_id, 0) < last_message_id")->execute($myStudioIds);
    }
}
if ($V3 && in_array($action, ['list', 'unread_total', 'thread'], true)) {
    mark_delivered($db, $myId, $myStudioIds, $action === 'thread');
}

/** Статус своего последнего сообщения для карточки списка: sent|delivered|read. */
function tick_state(int $msgId, int $delivered, int $read): string {
    if ($read >= $msgId) return 'read';
    if ($delivered >= $msgId) return 'delivered';
    return 'sent';
}

/* =================== резолверы бесед =================== */
function resolve_dm(PDO $db, int $a, int $b): int {
    $key = dm_key($a, $b);
    try {
        $db->prepare("INSERT INTO conversations(type,dm_key,created_at,last_message_at) VALUES('dm',?,NOW(),NOW())")->execute([$key]);
        $id=(int)$db->lastInsertId();
        $ins=$db->prepare("INSERT IGNORE INTO conversation_participants(conversation_id,user_id,role) VALUES(?,?, 'member')");
        $ins->execute([$id,$a]); $ins->execute([$id,$b]);
        return $id;
    } catch (PDOException $e) { $st=$db->prepare("SELECT id FROM conversations WHERE dm_key=? LIMIT 1"); $st->execute([$key]); return (int)$st->fetchColumn(); }
}
function resolve_studio(PDO $db, int $customerId, int $studioId): int {
    $key = studio_key($customerId, $studioId);
    try {
        $db->prepare("INSERT INTO conversations(type,studio_id,dm_key,created_at,last_message_at) VALUES('studio',?,?,NOW(),NOW())")->execute([$studioId,$key]);
        $id=(int)$db->lastInsertId();
        $db->prepare("INSERT IGNORE INTO conversation_participants(conversation_id,user_id,role) VALUES(?,?, 'customer')")->execute([$id,$customerId]);
        return $id;
    } catch (PDOException $e) { $st=$db->prepare("SELECT id FROM conversations WHERE dm_key=? LIMIT 1"); $st->execute([$key]); return (int)$st->fetchColumn(); }
}
/* системная беседа «Уведомления» (одна на юзера) */
function ensure_system_conv(PDO $db, int $userId): int {
    $key = "sys:u{$userId}";
    try {
        $db->prepare("INSERT INTO conversations(type,dm_key,created_at,last_message_at) VALUES('system',?,NOW(),NOW())")->execute([$key]);
        $id=(int)$db->lastInsertId();
        $db->prepare("INSERT IGNORE INTO conversation_participants(conversation_id,user_id,role) VALUES(?,?, 'member')")->execute([$id,$userId]);
        return $id;
    } catch (PDOException $e) { $st=$db->prepare("SELECT id FROM conversations WHERE dm_key=? LIMIT 1"); $st->execute([$key]); return (int)$st->fetchColumn(); }
}
/**
 * Единый вызов из любого контроллера: положить уведомление юзеру.
 * Пишем в ту же таблицу notifications, что и NotificationCenter, —
 * именно её читает вкладка «Уведомления» в чате и красная точка в хедере.
 * Раньше писали в messages системной беседы, и эти два мира не пересекались.
 */
function send_notification(PDO $db, int $userId, string $text, string $title = 'Dustore', ?string $link = null): int {
    $db->prepare("INSERT INTO notifications(user_id,title,message,action,status,date) VALUES(?,?,?,?,'unread',NOW())")
       ->execute([$userId, $title, $text, $link]);
    $nid=(int)$db->lastInsertId();
    if (function_exists('push_enqueue_user')) push_enqueue_user($db, $userId, $title !== 'Dustore' ? $title : 'Уведомление · Dustore', $text, '/chat/?system=1');
    if (function_exists('ws_notify')) ws_notify(ensure_system_conv($db,$userId), [$userId]);
    return $nid;
}

function conv_access(PDO $db, int $convId, int $myId, array $myStudioIds): ?array {
    $st=$db->prepare("SELECT * FROM conversations WHERE id=? LIMIT 1"); $st->execute([$convId]);
    $c=$st->fetch(PDO::FETCH_ASSOC); if(!$c) return null;
    $p=$db->prepare("SELECT role,last_read_message_id FROM conversation_participants WHERE conversation_id=? AND user_id=?");
    $p->execute([$convId,$myId]); $part=$p->fetch(PDO::FETCH_ASSOC) ?: null;
    $isStudioStaff=$c['type']==='studio' && in_array((int)$c['studio_id'],$myStudioIds,true);
    if(!$part && !$isStudioStaff) return null;
    $c['_part']=$part; $c['_isStudioStaff']=$isStudioStaff; return $c;
}
function customer_of(PDO $db, int $convId): int {
    $st=$db->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id=? AND role='customer' LIMIT 1");
    $st->execute([$convId]); return (int)$st->fetchColumn();
}
function unread_count(PDO $db, int $convId, int $afterId, int $excludeSender): int {
    $q=$db->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id=? AND id>? AND sender_id<>? AND deleted_at IS NULL");
    $q->execute([$convId,$afterId,$excludeSender]); return (int)$q->fetchColumn();
}

/* =================== ACTION: list ===================
 * Было: на каждую беседу отдельно customer_of(), last_read_message_id,
 * unread_count() и выборка последнего сообщения в build_card(). При 50
 * беседах — около 200 запросов, и это раз в 8 секунд на каждого открытого
 * пользователя. Стало: фиксированные 5 запросов независимо от их числа.
 */
if ($action === 'list') {
    $tab   = ($_GET['tab'] ?? 'personal') === 'studio' ? 'studio' : 'personal';
    $cards = [];
    $lFile = $V2 ? ', m.file_id AS l_file' : '';

    if ($tab === 'studio') {
        if (!$myStudioIds) out(['ok' => true, 'conversations' => []]);
        $in = implode(',', array_fill(0, count($myStudioIds), '?'));

        // 1 запрос: беседы + последнее сообщение одним LEFT JOIN
        $st = $db->prepare("
            SELECT c.id, c.type, c.studio_id, c.last_message_id, c.last_message_at, c.studio_last_read_id,
                   m.sender_id AS l_sender, m.body AS l_body, m.created_at AS l_at, m.deleted_at AS l_del{$lFile}
              FROM conversations c
              LEFT JOIN messages m ON m.id = c.last_message_id
             WHERE c.type='studio' AND c.studio_id IN ($in)
             ORDER BY c.last_message_at DESC LIMIT 200");
        $st->execute($myStudioIds);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) out(['ok' => true, 'conversations' => []]);
        $GLOBALS['lastFiles'] = $V2 ? chat_files_by_ids($db, array_column($rows, 'l_file')) : [];

        $convIds = array_map(fn($r) => (int)$r['id'], $rows);
        $inC = implode(',', array_fill(0, count($convIds), '?'));

        // 2 запрос: клиент каждой беседы
        $dlvCol = $GLOBALS['V3'] ? ', last_delivered_message_id AS dlv' : ', 0 AS dlv';
        $cst = $db->prepare("SELECT conversation_id, user_id, last_read_message_id AS rd{$dlvCol} FROM conversation_participants
                              WHERE conversation_id IN ($inC) AND role='customer'");
        $cst->execute($convIds);
        $custOf = []; $ptrOf = [];
        foreach ($cst->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $custOf[(int)$r['conversation_id']] = (int)$r['user_id'];
            $ptrOf[(int)$r['conversation_id']]  = [(int)$r['dlv'], (int)$r['rd']];
        }

        // 3 запрос: непрочитанное одним GROUP BY вместо запроса на беседу
        $ust = $db->prepare("
            SELECT m.conversation_id, COUNT(*) AS n
              FROM messages m
              JOIN conversations c ON c.id = m.conversation_id
              JOIN conversation_participants p
                ON p.conversation_id = m.conversation_id AND p.role='customer' AND p.user_id = m.sender_id
             WHERE m.conversation_id IN ($inC)
               AND m.id > c.studio_last_read_id
               AND m.deleted_at IS NULL
             GROUP BY m.conversation_id");
        $ust->execute($convIds);
        $unreadOf = [];
        foreach ($ust->fetchAll(PDO::FETCH_ASSOC) as $r) $unreadOf[(int)$r['conversation_id']] = (int)$r['n'];

        // 4 и 5: мета студий и клиентов, обе уже пакетные
        $studios = get_studios_meta($db, array_map(fn($r) => (int)$r['studio_id'], $rows));
        $users   = get_users_meta($db, array_values($custOf));

        foreach ($rows as $r) {
            $cid  = (int)$r['id'];
            $cust = $custOf[$cid] ?? 0;
            $cards[] = build_card($r, [
                'kind'   => 'user',
                'id'     => $cust,
                'name'   => $users[$cust]['username'] ?? ('user#' . $cust),
                'avatar' => $users[$cust]['avatar'] ?? null,
                'tag'    => $studios[(int)$r['studio_id']]['name'] ?? null,
            ], $myId, $unreadOf[$cid] ?? 0, $ptrOf[$cid] ?? [0, 0]);
        }
    } else {
        ensure_system_conv($db, $myId);   // блок «Уведомления» всегда есть
        $sDlv = $V3 ? ', c.studio_last_delivered_id' : ', 0 AS studio_last_delivered_id';

        $st = $db->prepare("
            SELECT c.id, c.type, c.studio_id, c.last_message_id, c.last_message_at,
                   p.last_read_message_id, c.studio_last_read_id{$sDlv},
                   m.sender_id AS l_sender, m.body AS l_body, m.created_at AS l_at, m.deleted_at AS l_del{$lFile}
              FROM conversations c
              JOIN conversation_participants p ON p.conversation_id = c.id AND p.user_id = ?
              LEFT JOIN messages m ON m.id = c.last_message_id
             WHERE p.archived = 0
             ORDER BY (c.type='system') DESC, c.last_message_at DESC LIMIT 200");
        $st->execute([$myId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) out(['ok' => true, 'conversations' => []]);
        $GLOBALS['lastFiles'] = $V2 ? chat_files_by_ids($db, array_column($rows, 'l_file')) : [];

        $convIds = array_map(fn($r) => (int)$r['id'], $rows);
        $inC = implode(',', array_fill(0, count($convIds), '?'));

        // собеседники всех личных бесед одним запросом
        // заодно их указатели «доставлено/прочитано» — для галочек в карточке
        $dlvCol = $V3 ? ', last_delivered_message_id AS dlv' : ', 0 AS dlv';
        $ost = $db->prepare("SELECT conversation_id, user_id, last_read_message_id AS rd{$dlvCol} FROM conversation_participants
                              WHERE conversation_id IN ($inC) AND user_id <> ?");
        $ost->execute([...$convIds, $myId]);
        $peerOf = []; $ptrOf = [];
        foreach ($ost->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $peerOf[(int)$r['conversation_id']] = (int)$r['user_id'];
            $ptrOf[(int)$r['conversation_id']]  = [(int)$r['dlv'], (int)$r['rd']];
        }

        // непрочитанное одним GROUP BY
        $ust = $db->prepare("
            SELECT m.conversation_id, COUNT(*) AS n
              FROM messages m
              JOIN conversation_participants p
                ON p.conversation_id = m.conversation_id AND p.user_id = ?
             WHERE m.conversation_id IN ($inC)
               AND m.id > p.last_read_message_id
               AND m.sender_id <> ?
               AND m.deleted_at IS NULL
             GROUP BY m.conversation_id");
        $ust->execute([$myId, ...$convIds, $myId]);
        $unreadOf = [];
        foreach ($ust->fetchAll(PDO::FETCH_ASSOC) as $r) $unreadOf[(int)$r['conversation_id']] = (int)$r['n'];

        $peerUserIds = []; $peerStudioIds = [];
        foreach ($rows as $r) {
            if ($r['type'] === 'studio')      $peerStudioIds[] = (int)$r['studio_id'];
            elseif ($r['type'] !== 'system')  $peerUserIds[]   = $peerOf[(int)$r['id']] ?? 0;
        }
        $users   = get_users_meta($db, $peerUserIds);
        $studios = get_studios_meta($db, $peerStudioIds);

        foreach ($rows as $r) {
            $cid = (int)$r['id'];
            if ($r['type'] === 'studio') {
                $pid  = (int)$r['studio_id'];
                $peer = ['kind' => 'studio', 'id' => $pid,
                         'name' => $studios[$pid]['name'] ?? ('studio#' . $pid),
                         'avatar' => $studios[$pid]['logo'] ?? null];
            } elseif ($r['type'] === 'system') {
                $peer = ['kind' => 'system', 'id' => 0, 'name' => 'Уведомления', 'avatar' => null];
            } else {
                $pid  = $peerOf[$cid] ?? 0;
                $peer = ['kind' => 'user', 'id' => $pid,
                         'name' => $users[$pid]['username'] ?? ('user#' . $pid),
                         'avatar' => $users[$pid]['avatar'] ?? null];
            }
            // «Уведомления» живут в таблице notifications, а не в messages
            if ($r['type'] === 'system') { $cards[] = system_card($db, $cid, $peer, $myId); continue; }
            // в беседе со студией «собеседник» — вся команда, её указатели в conversations
            $ptr = $r['type'] === 'studio'
                ? [(int)($r['studio_last_delivered_id'] ?? 0), (int)($r['studio_last_read_id'] ?? 0)]
                : ($ptrOf[$cid] ?? [0, 0]);
            $cards[] = build_card($r, $peer, $myId, $unreadOf[$cid] ?? 0, $ptr);
        }
    }
    out(['ok' => true, 'conversations' => $cards]);
}

/** Последнее сообщение уже приехало в строке ($r['l_*']) — БД больше не трогаем. */
function build_card(array $r, array $peer, int $myId, int $unread, array $ptr = [0, 0]): array {
    $last = null;
    if (!empty($r['last_message_id']) && $r['l_at'] !== null) {
        $f = $GLOBALS['lastFiles'][(int)($r['l_file'] ?? 0)] ?? null;
        $mine = (int)$r['l_sender'] === $myId;
        $last = [
            'body' => $r['l_del'] ? 'сообщение удалено' : preview_text((string)msg_decrypt((string)$r['l_body']), $f),
            'at'   => $r['l_at'],
            'mine' => $mine,
            'state'=> $mine ? tick_state((int)$r['last_message_id'], $ptr[0], $ptr[1]) : null,
        ];
    }
    return ['id' => (int)$r['id'], 'type' => $r['type'], 'peer' => $peer,
            'last' => $last, 'unread' => $unread, 'ts' => $r['last_message_at']];
}

/* ── Уведомления как виртуальная беседа ──────────────────────────── */

/** Anti-corruption layer: сырая строка БД → фиксированный контракт для фронта */
function notif_dto(array $n): array {
    return [
        'id'     => (int)$n['id'],
        'title'  => $n['title'] ?? 'Уведомление',
        'body'   => $n['text']  ?? $n['message'] ?? '',
        'link'   => $n['link']  ?? $n['action'] ?? null,
        'ts'     => $n['created_at'] ?? $n['date'] ?? null,
        'unread' => (($n['status'] ?? '') === 'unread'),
    ];
}

/** Карточка «Уведомления» в списке: последнее уведомление + число непрочитанных. */
function system_card(PDO $db, int $convId, array $peer, int $myId): array {
    $l = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $l->execute([$myId]);
    $n = $l->fetch(PDO::FETCH_ASSOC);
    $u = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND status='unread'");
    $u->execute([$myId]);
    $last = null; $ts = null;
    if ($n) {
        $d = notif_dto($n);
        $last = ['body' => trim($d['title'] . ' — ' . $d['body'], ' —'), 'at' => $d['ts'], 'mine' => false];
        $ts = $d['ts'];
    }
    return ['id' => $convId, 'type' => 'system', 'peer' => $peer,
            'last' => $last, 'unread' => (int)$u->fetchColumn(), 'ts' => $ts];
}

/** Уведомление в формате сообщения треда (фронт рендерит его карточкой). */
function notif_as_msg(array $n): array {
    $d = notif_dto($n);
    return ['id' => $d['id'], 'mine' => false, 'deleted' => false, 'at' => $d['ts'],
            'sender' => ['id' => 0, 'name' => 'Dustore', 'avatar' => null],
            'title' => $d['title'], 'body' => $d['body'], 'link' => $d['link'], 'unread' => $d['unread']];
}

/* =================== ACTION: unread_total =================== */
if ($action === 'unread_total') {
    // Один агрегат вместо запроса на каждую беседу.
    $st = $db->prepare("
        SELECT COUNT(*) FROM messages m
          JOIN conversation_participants p
            ON p.conversation_id = m.conversation_id AND p.user_id = ? AND p.archived = 0
         WHERE m.id > p.last_read_message_id AND m.sender_id <> ? AND m.deleted_at IS NULL");
    $st->execute([$myId, $myId]);
    $total = (int)$st->fetchColumn();
    // непрочитанные уведомления — отдельно: мобильное меню и значок приложения показывают сумму
    $nq = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND status='unread'");
    $nq->execute([$myId]);
    out(['ok' => true, 'total' => $total, 'notifications' => (int)$nq->fetchColumn()]);
}

/* =================== ACTION: search_users =================== */
if ($action === 'search_users') {
    $q=trim((string)($_GET['q'] ?? '')); if(mb_strlen($q)<2) out(['ok'=>true,'users'=>[]]);
    // % и _ — метасимволы LIKE. Без экранирования запрос «%» матчил всех.
    $esc = addcslashes($q, '%_\\');
    $like='%'.$esc.'%'; $starts=$esc.'%';
    $st=$db->prepare("SELECT id, username, first_name, last_name, profile_picture FROM users
                       WHERE id<>? AND ( username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR telegram_username LIKE ? )
                       ORDER BY (username LIKE ?) DESC, username ASC LIMIT 12");
    $st->execute([$myId,$like,$like,$like,$like,$starts]);
    out(['ok'=>true,'users'=>array_map('user_card_from_row',$st->fetchAll(PDO::FETCH_ASSOC))]);
}

/* =================== ACTION: resolve_handle =================== */
if ($action === 'resolve_handle') {
    $h=ltrim(trim((string)($_GET['h'] ?? '')),'@'); if($h==='') out(['ok'=>false,'error'=>'empty']);
    $st=$db->prepare("SELECT id FROM users WHERE username=? LIMIT 1"); $st->execute([$h]);
    $id=(int)$st->fetchColumn(); if(!$id) out(['ok'=>false,'error'=>'not_found']);
    out(['ok'=>true,'user_id'=>$id]);
}

/* =================== ACTION: user_profile =================== */
if ($action === 'user_profile') {
    $uid=(int)($_GET['user_id'] ?? 0); if($uid<=0) out(['ok'=>false,'error'=>'bad_id']);
    $st=$db->prepare("SELECT id, username, first_name, last_name, profile_picture, country, city,
                             votes_up, votes_down, profile_views, last_activity FROM users WHERE id=? LIMIT 1");
    $st->execute([$uid]); $u=$st->fetch(PDO::FETCH_ASSOC); if(!$u) out(['ok'=>false,'error'=>'not_found']);
    $card=user_card_from_row($u); $handle=trim((string)($u['username'] ?? ''));
    out(['ok'=>true,'profile'=>[
        'id'=>(int)$u['id'],'name'=>$card['username'],'handle'=>$handle,'avatar'=>$card['avatar'],
        'location'=>trim(trim((string)($u['city']??'')).' '.trim((string)($u['country']??''))),
        'votes_up'=>(int)$u['votes_up'],'votes_down'=>(int)$u['votes_down'],'views'=>(int)$u['profile_views'],
        'last_seen'=>$u['last_activity'],
    ]]);
}

/* =================== ACTION: start =================== */
if ($action === 'start') {
    $toUser=(int)($_REQUEST['to'] ?? 0); $toStudio=(int)($_REQUEST['studio'] ?? 0);
    if($toUser>0 && $toUser!==$myId) out(['ok'=>true,'conversation_id'=>resolve_dm($db,$myId,$toUser)]);
    if($toStudio>0) out(['ok'=>true,'conversation_id'=>resolve_studio($db,$myId,$toStudio)]);
    out(['ok'=>false,'error'=>'bad_target']);
}

/* =================== ACTION: thread =================== */
if ($action === 'thread') {
    $cid=(int)($_REQUEST['conversation_id'] ?? 0);
    $c=conv_access($db,$cid,$myId,$myStudioIds); if(!$c) out(['ok'=>false,'error'=>'forbidden']);
    $after  = (int)($_REQUEST['after_id']  ?? 0);
    $before = (int)($_REQUEST['before_id'] ?? 0);
    $LIMIT  = 60;
    $hasMore = false;
    /* seen=0 — вкладка в фоне / окно без фокуса. Раньше поллинг фоновой вкладки
       отмечал всё прочитанным: у собеседника загорались «прочитано», хотя
       человек ничего не видел. Теперь в фоне — только «доставлено». */
    $seen = (string)($_REQUEST['seen'] ?? '1') !== '0';

    /* «Уведомления»: тред собирается из таблицы notifications. Пагинация та же
       (after_id / before_id), только по notifications.id. Открыл вкладку —
       всё прочитано, как и на старой странице /notifications. */
    if ($c['type'] === 'system') {
        if ($after > 0) {
            $q = $db->prepare("SELECT * FROM notifications WHERE user_id=? AND id>? ORDER BY id ASC LIMIT 200");
            $q->execute([$myId, $after]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        } else {
            if ($before > 0) {
                $q = $db->prepare("SELECT * FROM notifications WHERE user_id=? AND id<? ORDER BY id DESC LIMIT " . ($LIMIT + 1));
                $q->execute([$myId, $before]);
            } else {
                $q = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT " . ($LIMIT + 1));
                $q->execute([$myId]);
            }
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > $LIMIT) { $hasMore = true; array_pop($rows); }
            $rows = array_reverse($rows);
        }
        $msgs = array_map('notif_as_msg', $rows);
        if ($seen) $db->prepare("UPDATE notifications SET status='read' WHERE user_id=? AND status='unread'")->execute([$myId]);
        out(['ok'=>true,'messages'=>$msgs,'has_more'=>$hasMore,'header'=>thread_header($db,$c,$myId)]);
    }

    /* Было: WHERE id > 0 ORDER BY id ASC LIMIT 500 — то есть при открытии
       беседы отдавались САМЫЕ СТАРЫЕ 500 сообщений, а свежие догружались
       только следующим поллингом. В длинной переписке человек открывал чат
       и три секунды смотрел на прошлогоднюю историю. */
    $v2cols = $V2 ? ', reply_to_id, file_id' : '';
    if ($after > 0) {
        // поллинг: только то, что появилось после известного нам id
        $q = $db->prepare("SELECT id, sender_id, body, created_at, deleted_at{$v2cols} FROM messages
                            WHERE conversation_id=? AND id>? ORDER BY id ASC LIMIT 200");
        $q->execute([$cid, $after]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // открытие или подгрузка истории вверх: берём хвост и разворачиваем
        if ($before > 0) {
            $q = $db->prepare("SELECT id, sender_id, body, created_at, deleted_at{$v2cols} FROM messages
                                WHERE conversation_id=? AND id<? ORDER BY id DESC LIMIT " . ($LIMIT + 1));
            $q->execute([$cid, $before]);
        } else {
            $q = $db->prepare("SELECT id, sender_id, body, created_at, deleted_at{$v2cols} FROM messages
                                WHERE conversation_id=? ORDER BY id DESC LIMIT " . ($LIMIT + 1));
            $q->execute([$cid]);
        }
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > $LIMIT) { $hasMore = true; array_pop($rows); }
        $rows = array_reverse($rows);
    }
    $msgs = enrich_messages($db, $cid, $rows, $myId, $V2);
    // ФИКС прочтения: отмечаем по НАСТОЯЩЕМУ последнему id беседы, обе ветки указателя
    $trueMax=(int)$c['last_message_id'];
    if($trueMax>0 && $seen){
        if($c['_part']) $db->prepare("UPDATE conversation_participants SET last_read_message_id=GREATEST(COALESCE(last_read_message_id,0),?)
                                       WHERE conversation_id=? AND user_id=?")->execute([$trueMax,$cid,$myId]);
        if($c['_isStudioStaff']) $db->prepare("UPDATE conversations SET studio_last_read_id=GREATEST(COALESCE(studio_last_read_id,0),?) WHERE id=?")->execute([$trueMax,$cid]);
    }
    out(['ok'=>true,'messages'=>$msgs,'has_more'=>$hasMore,'header'=>thread_header($db,$c,$myId),
         'pins'=>$V2 ? conv_pins($db,$cid) : [],
         'wallpaper'=>$V3 ? conv_wallpaper($db,$cid,$myId) : null]);
}
/**
 * Указатели собеседника: [доставлено, прочитано]. «Прочитано» всегда
 * подразумевает «доставлено», поэтому доставленное = max из двух.
 */
function peer_ptrs(PDO $db, int $cid, int $peerUser): array {
    $dlv = $GLOBALS['V3'] ? 'last_delivered_message_id' : '0';
    $q = $db->prepare("SELECT last_read_message_id, {$dlv} FROM conversation_participants WHERE conversation_id=? AND user_id=?");
    $q->execute([$cid, $peerUser]);
    $r = $q->fetch(PDO::FETCH_NUM) ?: [0, 0];
    return [max((int)$r[1], (int)$r[0]), (int)$r[0]];
}
function thread_header(PDO $db, array $c, int $myId): array {
    $cid = (int)$c['id'];
    if($c['type']==='system') return ['kind'=>'system','peer_id'=>0,'studio'=>false,'name'=>'Уведомления','avatar'=>null,'tag'=>null,'last_seen'=>null,'peer_last_read_id'=>0,'peer_last_delivered_id'=>0];
    if($c['type']==='studio'){
        $s=(get_studios_meta($db,[(int)$c['studio_id']]))[(int)$c['studio_id']] ?? [];
        if($c['_isStudioStaff']){
            $cust=customer_of($db,$cid); $u=(get_users_meta($db,[$cust]))[$cust] ?? [];
            $la=$db->prepare("SELECT last_activity FROM users WHERE id=?"); $la->execute([$cust]); $seen=$la->fetchColumn() ?: null;
            [$dlv,$rd]=peer_ptrs($db,$cid,$cust);
            return ['kind'=>'user','peer_id'=>$cust,'studio'=>true,'name'=>$u['username'] ?? ('user#'.$cust),'avatar'=>$u['avatar'] ?? null,'tag'=>$s['name'] ?? null,'last_seen'=>$seen,'peer_last_read_id'=>$rd,'peer_last_delivered_id'=>$dlv];
        }
        $rd=(int)($c['studio_last_read_id'] ?? 0);
        $dlv=max($rd,(int)($c['studio_last_delivered_id'] ?? 0));
        return ['kind'=>'studio','peer_id'=>(int)$c['studio_id'],'studio'=>true,'name'=>$s['name'] ?? ('studio#'.$c['studio_id']),'avatar'=>$s['logo'] ?? null,'tag'=>null,'last_seen'=>null,'peer_last_read_id'=>$rd,'peer_last_delivered_id'=>$dlv];
    }
    $o=$db->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id=? AND user_id<>? LIMIT 1");
    $o->execute([$cid,$myId]); $peer=(int)$o->fetchColumn();
    $u=(get_users_meta($db,[$peer]))[$peer] ?? [];
    $la=$db->prepare("SELECT last_activity FROM users WHERE id=?"); $la->execute([$peer]); $seen=$la->fetchColumn() ?: null;
    [$dlv,$rd]=peer_ptrs($db,$cid,$peer);
    return ['kind'=>'user','peer_id'=>$peer,'studio'=>false,'name'=>$u['username'] ?? ('user#'.$peer),'avatar'=>$u['avatar'] ?? null,'tag'=>null,'last_seen'=>$seen,'peer_last_read_id'=>$rd,'peer_last_delivered_id'=>$dlv];
}

/* =================== ACTION: send =================== */
if ($action === 'send') {
    $body=trim((string)($_POST['body'] ?? ''));
    $fileId  = $V2 ? (int)($_POST['file_id']  ?? 0) : 0;
    $replyTo = $V2 ? (int)($_POST['reply_to'] ?? 0) : 0;
    if($body==='' && $fileId<=0) out(['ok'=>false,'error'=>'empty']);
    if(mb_strlen($body)>4000) out(['ok'=>false,'error'=>'too_long']);
    $file = null;
    if ($fileId > 0) {
        // прикрепить можно только свой готовый файл (не звук уведомлений)
        $fs = $db->prepare("SELECT * FROM chat_files WHERE id=? AND owner_id=? AND status='ready' AND kind IN ('image','file') LIMIT 1");
        $fs->execute([$fileId, $myId]);
        $file = $fs->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$file) out(['ok'=>false,'error'=>'bad_file']);
    }
    // антифлуд: не больше 5 сообщений за 5 секунд. Раньше было «1 в секунду»,
    // и картинка, отправленная сразу после текста, отбивалась с ошибкой.
    $fl=$db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id=? AND created_at > NOW() - INTERVAL 5 SECOND");
    $fl->execute([$myId]);
    if((int)$fl->fetchColumn() >= 5) out(['ok'=>false,'error'=>'too_fast','message'=>'Слишком часто']);
    $cid=(int)($_POST['conversation_id'] ?? 0);
    if(!$cid){
        $toUser=(int)($_POST['to'] ?? 0); $toStudio=(int)($_POST['studio'] ?? 0);
        if($toUser>0 && $toUser!==$myId) $cid=resolve_dm($db,$myId,$toUser);
        elseif($toStudio>0) $cid=resolve_studio($db,$myId,$toStudio);
        else out(['ok'=>false,'error'=>'bad_target']);
    }
    $c=conv_access($db,$cid,$myId,$myStudioIds); if(!$c) out(['ok'=>false,'error'=>'forbidden']);
    if($c['type']==='system') out(['ok'=>false,'error'=>'readonly']); // в «Уведомления» не пишем руками

    if ($replyTo > 0) {
        // отвечать можно только на сообщение из этой же беседы
        $rq = $db->prepare("SELECT 1 FROM messages WHERE id=? AND conversation_id=? LIMIT 1");
        $rq->execute([$replyTo, $cid]);
        if (!$rq->fetchColumn()) $replyTo = 0;
    }
    if ($V2) {
        $db->prepare("INSERT INTO messages(conversation_id,sender_id,body,reply_to_id,file_id,created_at) VALUES(?,?,?,?,?,NOW())")
           ->execute([$cid,$myId,msg_encrypt($body),$replyTo ?: null,$fileId ?: null]);
    } else {
        $db->prepare("INSERT INTO messages(conversation_id,sender_id,body,created_at) VALUES(?,?,?,NOW())")->execute([$cid,$myId,msg_encrypt($body)]);
    }
    $msgId=(int)$db->lastInsertId();
    $pushText = preview_text($body, $file);
    $db->prepare("UPDATE conversations SET last_message_id=?, last_message_at=NOW() WHERE id=?")->execute([$msgId,$cid]);
    // ФИКС: любое новое сообщение возвращает беседу из архива всем участникам
    $db->prepare("UPDATE conversation_participants SET archived=0 WHERE conversation_id=?")->execute([$cid]);
    if($c['_isStudioStaff']) $db->prepare("UPDATE conversations SET studio_last_read_id=GREATEST(COALESCE(studio_last_read_id,0),?) WHERE id=?")->execute([$msgId,$cid]);
    else $db->prepare("UPDATE conversation_participants SET last_read_message_id=GREATEST(COALESCE(last_read_message_id,0),?) WHERE conversation_id=? AND user_id=?")->execute([$msgId,$cid,$myId]);

    if(is_file(__DIR__.'/../vk/vk_helpers.php')){ require_once __DIR__.'/../vk/vk_helpers.php';
        if(function_exists('vk_enqueue_for_conversation')) vk_enqueue_for_conversation($db,$cid,$myId,$pushText); }
    if(function_exists('push_enqueue_for_conversation'))
        push_enqueue_for_conversation($db,$cid,$myId, ($me['username'] ?? ($me['first_name'] ?? 'Новое сообщение')), $pushText);
    if(function_exists('ws_notify')) ws_notify($cid, ws_recipients($db, $cid, $myId));

    $row = ['id'=>$msgId,'sender_id'=>$myId,'body'=>msg_encrypt($body),'created_at'=>date('Y-m-d H:i:s'),'deleted_at'=>null,
            'reply_to_id'=>$replyTo ?: null,'file_id'=>$fileId ?: null];
    out(['ok'=>true,'conversation_id'=>$cid,'message'=>enrich_messages($db,$cid,[$row],$myId,$V2)[0]]);
}

/* =================== ACTION: delete_message =================== */
if ($action === 'delete_message') {
    $mid=(int)($_POST['message_id'] ?? 0); if($mid<=0) out(['ok'=>false,'error'=>'bad_id']);
    $st=$db->prepare("SELECT conversation_id, sender_id FROM messages WHERE id=? LIMIT 1"); $st->execute([$mid]);
    $m=$st->fetch(PDO::FETCH_ASSOC); if(!$m) out(['ok'=>false,'error'=>'not_found']);
    if((int)$m['sender_id']!==$myId) out(['ok'=>false,'error'=>'forbidden']);
    if(!conv_access($db,(int)$m['conversation_id'],$myId,$myStudioIds)) out(['ok'=>false,'error'=>'forbidden']);
    $db->prepare("UPDATE messages SET deleted_at=NOW() WHERE id=?")->execute([$mid]);
    out(['ok'=>true,'message_id'=>$mid]);
}

/* =================== ACTION: delete_conversation =================== */
if ($action === 'delete_conversation') {
    $cid=(int)($_POST['conversation_id'] ?? 0); if($cid<=0) out(['ok'=>false,'error'=>'bad_id']);
    $c=conv_access($db,$cid,$myId,$myStudioIds); if(!$c) out(['ok'=>false,'error'=>'forbidden']);
    $db->prepare("UPDATE conversation_participants SET archived=1 WHERE conversation_id=? AND user_id=?")->execute([$cid,$myId]);
    out(['ok'=>true,'conversation_id'=>$cid]);
}

/* ════════════════════════ ЧАТ v2 ════════════════════════════════════════ */

/** Короткая строка для превью в списке, пуша и цитаты ответа. */
function preview_text(string $body, ?array $file, int $max = 0): string {
    $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');
    if ($file) {
        $label = $file['kind'] === 'image' ? '🖼 Фото' : '📎 ' . $file['name'];
        $body  = $body === '' ? $label : ($file['kind'] === 'image' ? '🖼 ' . $body : '📎 ' . $body);
    }
    if ($max > 0 && mb_strlen($body) > $max) $body = mb_substr($body, 0, $max - 1) . '…';
    return $body;
}

/**
 * Строки messages → контракт фронта. Отправители, вложения и цитаты ответов
 * собираются пакетно (по одному запросу на вид данных, а не на сообщение).
 */
function enrich_messages(PDO $db, int $cid, array $rows, int $myId, bool $v2): array {
    $replyIds = $v2 ? array_filter(array_map(fn($m) => (int)($m['reply_to_id'] ?? 0), $rows)) : [];
    $replies  = [];
    if ($replyIds) {
        $in = implode(',', array_fill(0, count($replyIds), '?'));
        $rq = $db->prepare("SELECT id, sender_id, body, file_id, deleted_at FROM messages WHERE conversation_id=? AND id IN ($in)");
        $rq->execute([$cid, ...array_values($replyIds)]);
        foreach ($rq->fetchAll(PDO::FETCH_ASSOC) as $r) $replies[(int)$r['id']] = $r;
    }
    $senderIds = array_merge(array_map(fn($m) => (int)$m['sender_id'], $rows), array_map(fn($r) => (int)$r['sender_id'], $replies));
    $smeta = get_users_meta($db, $senderIds);
    $files = $v2 ? chat_files_by_ids($db, array_merge(array_column($rows, 'file_id'), array_column($replies, 'file_id'))) : [];

    $out = [];
    foreach ($rows as $m) {
        $sid  = (int)$m['sender_id'];
        $del  = (bool)$m['deleted_at'];
        $file = $files[(int)($m['file_id'] ?? 0)] ?? null;
        $msg  = [
            'id'      => (int)$m['id'],
            'mine'    => $sid === $myId,
            'sender'  => ['id' => $sid, 'name' => $smeta[$sid]['username'] ?? ('user#' . $sid), 'avatar' => $smeta[$sid]['avatar'] ?? null],
            'body'    => $del ? null : msg_decrypt($m['body']),
            'deleted' => $del,
            'at'      => $m['created_at'],
            'file'    => (!$del && $file) ? chat_file_dto($file) : null,
            'reply'   => null,
        ];
        $rid = (int)($m['reply_to_id'] ?? 0);
        if ($rid && isset($replies[$rid])) {
            $r = $replies[$rid]; $rs = (int)$r['sender_id'];
            $msg['reply'] = [
                'id'      => $rid,
                'name'    => $smeta[$rs]['username'] ?? ('user#' . $rs),
                'mine'    => $rs === $myId,
                'deleted' => (bool)$r['deleted_at'],
                'text'    => $r['deleted_at'] ? 'сообщение удалено'
                             : preview_text((string)msg_decrypt($r['body']), $files[(int)($r['file_id'] ?? 0)] ?? null, 120),
            ];
        } elseif ($rid) {
            $msg['reply'] = ['id' => $rid, 'name' => '', 'mine' => false, 'deleted' => true, 'text' => 'сообщение недоступно'];
        }
        $out[] = $msg;
    }
    return $out;
}

/** Закреплённые сообщения беседы, свежие сверху. */
function conv_pins(PDO $db, int $cid): array {
    $q = $db->prepare("SELECT p.message_id, p.created_at AS pinned_at, m.sender_id, m.body, m.file_id
                         FROM conversation_pins p JOIN messages m ON m.id = p.message_id
                        WHERE p.conversation_id=? AND m.deleted_at IS NULL
                        ORDER BY p.created_at DESC LIMIT 20");
    $q->execute([$cid]);
    $rows  = $q->fetchAll(PDO::FETCH_ASSOC);
    $meta  = get_users_meta($db, array_column($rows, 'sender_id'));
    $files = chat_files_by_ids($db, array_column($rows, 'file_id'));
    return array_map(fn($r) => [
        'id'   => (int)$r['message_id'],
        'name' => $meta[(int)$r['sender_id']]['username'] ?? '',
        'text' => preview_text((string)msg_decrypt($r['body']), $files[(int)($r['file_id'] ?? 0)] ?? null, 140),
    ], $rows);
}

function need_v2(bool $v2): void {
    if (!$v2) out(['ok' => false, 'error' => 'migration', 'message' => 'Нужно прогнать chat/migrate_v2.php']);
}

/** Сообщение + доступ к его беседе; иначе ответ с ошибкой. */
function message_with_access(PDO $db, int $mid, int $myId, array $myStudioIds): array {
    $st = $db->prepare("SELECT id, conversation_id, sender_id, deleted_at FROM messages WHERE id=? LIMIT 1");
    $st->execute([$mid]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m || $m['deleted_at']) out(['ok' => false, 'error' => 'not_found']);
    $c = conv_access($db, (int)$m['conversation_id'], $myId, $myStudioIds);
    if (!$c) out(['ok' => false, 'error' => 'forbidden']);
    return [$m, $c];
}

/* =================== ACTION: upload_init ===================
 * Шаг 1 загрузки: запись pending + подписанные PUT-ссылки (файл и превью). */
if ($action === 'upload_init') {
    need_v2($V2);
    $purpose = in_array($_POST['purpose'] ?? 'chat', ['sound', 'wallpaper'], true) ? $_POST['purpose'] : 'chat';
    $name = trim((string)($_POST['name'] ?? 'file'));
    $name = mb_substr($name !== '' ? $name : 'file', 0, 200);
    $size = (int)($_POST['size'] ?? 0);
    $mime = strtolower(trim((string)($_POST['mime'] ?? ''))) ?: 'application/octet-stream';
    $max  = ['sound' => CHAT_SOUND_MAX, 'wallpaper' => CHAT_WALLPAPER_MAX][$purpose] ?? CHAT_FILE_MAX;
    if ($size <= 0) out(['ok' => false, 'error' => 'empty']);
    if ($size > $max) out(['ok' => false, 'error' => 'too_big', 'max' => $max]);
    $kind = chat_kind_for($mime, $purpose);
    if (!$kind) out(['ok' => false, 'error' => 'bad_type']);

    // защита от засорения бакета: не больше 60 незавершённых загрузок за час
    $pc = $db->prepare("SELECT COUNT(*) FROM chat_files WHERE owner_id=? AND status='pending' AND created_at > NOW() - INTERVAL 1 HOUR");
    $pc->execute([$myId]);
    if ((int)$pc->fetchColumn() >= 60) out(['ok' => false, 'error' => 'too_many']);

    $key   = chat_new_key($name);
    $thumb = $kind === 'image' ? $key . '.thumb.webp' : null;
    $db->prepare("INSERT INTO chat_files(owner_id,kind,status,s3_key,thumb_key,name,mime,size) VALUES(?,?,'pending',?,?,?,?,?)")
       ->execute([$myId, $kind, $key, $thumb, $name, $mime, $size]);
    $fid = (int)$db->lastInsertId();

    out(['ok' => true, 'file_id' => $fid, 'kind' => $kind,
         'put_url'   => chat_presign_put($key, $mime),
         'thumb_url' => $thumb ? chat_presign_put($thumb, 'image/webp') : null]);
}

/** Своя pending-запись для шагов 2–3. */
function own_pending(PDO $db, int $fid, int $myId): array {
    $st = $db->prepare("SELECT * FROM chat_files WHERE id=? AND owner_id=? LIMIT 1");
    $st->execute([$fid, $myId]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    if (!$f) out(['ok' => false, 'error' => 'not_found']);
    return $f;
}

/** Финал загрузки: HEAD в бакет, сверка размера, статус ready. */
function commit_file(PDO $db, array $f, int $w, int $h, bool $hasThumb): array {
    $real = chat_head_size($f['s3_key']);
    if ($real === null) out(['ok' => false, 'error' => 'not_uploaded']);
    $max = $f['kind'] === 'sound' ? CHAT_SOUND_MAX : CHAT_FILE_MAX;
    if ($real > $max || $real !== (int)$f['size']) {
        // подменили файл или размер — убираем из бакета, запись не активируем
        chat_delete_key($f['s3_key']); chat_delete_key($f['thumb_key']);
        out(['ok' => false, 'error' => 'size_mismatch']);
    }
    $thumbKey = ($hasThumb && $f['thumb_key'] && chat_head_size($f['thumb_key']) !== null) ? $f['thumb_key'] : null;
    $w = $w > 0 && $w < 65536 ? $w : null;
    $h = $h > 0 && $h < 65536 ? $h : null;
    $db->prepare("UPDATE chat_files SET status='ready', thumb_key=?, width=?, height=? WHERE id=?")
       ->execute([$thumbKey, $w, $h, (int)$f['id']]);
    $f = array_merge($f, ['status' => 'ready', 'thumb_key' => $thumbKey, 'width' => $w, 'height' => $h]);
    return chat_file_dto($f);
}

/* =================== ACTION: upload_commit =================== */
if ($action === 'upload_commit') {
    need_v2($V2);
    $f = own_pending($db, (int)($_POST['file_id'] ?? 0), $myId);
    if ($f['status'] === 'ready') out(['ok' => true, 'file' => chat_file_dto($f)]);
    out(['ok' => true, 'file' => commit_file($db, $f, (int)($_POST['w'] ?? 0), (int)($_POST['h'] ?? 0), !empty($_POST['thumb']))]);
}

/* =================== ACTION: upload_proxy ===================
 * Запасной путь, если браузер не смог PUT'нуть в S3 (CORS на локалке и т.п.):
 * файл едет через PHP. Тип проверяем по содержимому, а не по заголовку. */
if ($action === 'upload_proxy') {
    need_v2($V2);
    $f = own_pending($db, (int)($_POST['file_id'] ?? 0), $myId);
    if ($f['status'] === 'ready') out(['ok' => true, 'file' => chat_file_dto($f)]);
    $up = $_FILES['file'] ?? null;
    if (!$up || $up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'])) out(['ok' => false, 'error' => 'no_file']);
    if ((int)$up['size'] !== (int)$f['size']) out(['ok' => false, 'error' => 'size_mismatch']);
    $real = (new finfo(FILEINFO_MIME_TYPE))->file($up['tmp_name']) ?: 'application/octet-stream';
    if ($f['kind'] === 'image' && !in_array($real, CHAT_IMAGE_MIME, true)) out(['ok' => false, 'error' => 'bad_type']);
    if ($f['kind'] === 'sound' && !chat_kind_for($real, 'sound') && !str_starts_with($real, 'audio/')) out(['ok' => false, 'error' => 'bad_type']);
    if (!chat_put_local($f['s3_key'], $up['tmp_name'], $f['mime'])) out(['ok' => false, 'error' => 'storage']);
    $hasThumb = false;
    $th = $_FILES['thumb'] ?? null;
    if ($f['thumb_key'] && $th && $th['error'] === UPLOAD_ERR_OK && is_uploaded_file($th['tmp_name']) && $th['size'] < 512 * 1024) {
        $hasThumb = chat_put_local($f['thumb_key'], $th['tmp_name'], 'image/webp');
    }
    out(['ok' => true, 'file' => commit_file($db, $f, (int)($_POST['w'] ?? 0), (int)($_POST['h'] ?? 0), $hasThumb)]);
}

/* =================== ACTION: pin / unpin =================== */
if ($action === 'pin' || $action === 'unpin') {
    need_v2($V2);
    [$m, $c] = message_with_access($db, (int)($_POST['message_id'] ?? 0), $myId, $myStudioIds);
    if ($c['type'] === 'system') out(['ok' => false, 'error' => 'readonly']);
    $cid = (int)$m['conversation_id'];
    if ($action === 'pin') {
        $db->prepare("INSERT IGNORE INTO conversation_pins(conversation_id,message_id,pinned_by) VALUES(?,?,?)")->execute([$cid, (int)$m['id'], $myId]);
    } else {
        $db->prepare("DELETE FROM conversation_pins WHERE conversation_id=? AND message_id=?")->execute([$cid, (int)$m['id']]);
    }
    if (function_exists('ws_notify')) ws_notify($cid, ws_recipients($db, $cid, $myId));
    out(['ok' => true, 'pins' => conv_pins($db, $cid)]);
}

/* =================== ACTION: mark_read =================== */
if ($action === 'mark_read') {
    $cid = (int)($_POST['conversation_id'] ?? 0);
    $c = conv_access($db, $cid, $myId, $myStudioIds); if (!$c) out(['ok' => false, 'error' => 'forbidden']);
    if ($c['type'] === 'system') {
        $db->prepare("UPDATE notifications SET status='read' WHERE user_id=? AND status='unread'")->execute([$myId]);
    } else {
        $last = (int)$c['last_message_id'];
        if ($c['_part']) $db->prepare("UPDATE conversation_participants SET last_read_message_id=GREATEST(COALESCE(last_read_message_id,0),?) WHERE conversation_id=? AND user_id=?")->execute([$last, $cid, $myId]);
        if ($c['_isStudioStaff']) $db->prepare("UPDATE conversations SET studio_last_read_id=GREATEST(COALESCE(studio_last_read_id,0),?) WHERE id=?")->execute([$last, $cid]);
    }
    out(['ok' => true]);
}

/* =================== ACTION: mark_all_read ===================
 * «Отметить всё как прочитанное» для текущей вкладки. Один UPDATE с JOIN,
 * а не цикл по беседам. */
if ($action === 'mark_all_read') {
    if (($_POST['tab'] ?? 'personal') === 'studio') {
        if ($myStudioIds) {
            $in = implode(',', array_fill(0, count($myStudioIds), '?'));
            $db->prepare("UPDATE conversations SET studio_last_read_id=GREATEST(COALESCE(studio_last_read_id,0),COALESCE(last_message_id,0))
                           WHERE type='studio' AND studio_id IN ($in)")->execute($myStudioIds);
        }
    } else {
        $db->prepare("UPDATE conversation_participants p JOIN conversations c ON c.id = p.conversation_id
                         SET p.last_read_message_id = GREATEST(COALESCE(p.last_read_message_id,0), COALESCE(c.last_message_id,0))
                       WHERE p.user_id=?")->execute([$myId]);
        $db->prepare("UPDATE notifications SET status='read' WHERE user_id=? AND status='unread'")->execute([$myId]);
    }
    out(['ok' => true]);
}

/* =================== ОБОИ БЕСЕДЫ ===================
 * Две записи на беседу максимум с точки зрения одного человека:
 *   user_id=0  — общие, их ставит любой участник и видят оба;
 *   user_id=me — личные, перекрывают общие только у меня.
 * Пресеты рисуются CSS-градиентами на клиенте (ноль байт трафика),
 * своё фото — обычный chat_files(kind=image), доступ через file.php.
 */
const CHAT_WP_PRESETS = ['aurora', 'dunes', 'synth', 'stars', 'mesh', 'noir', 'sunset', 'ocean', 'none'];

function conv_wallpaper(PDO $db, int $cid, int $myId): array {
    $q = $db->prepare("SELECT user_id, preset, file_id, dim, set_by, UNIX_TIMESTAMP(updated_at) AS ts
                         FROM conversation_wallpapers WHERE conversation_id=? AND user_id IN (0, ?)");
    $q->execute([$cid, $myId]);
    $rows = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[(int)$r['user_id'] === 0 ? 'shared' : 'mine'] = $r;
    $files = chat_files_by_ids($db, array_column($rows, 'file_id'));
    $dto = function (?array $r) use ($files, $myId): ?array {
        if (!$r) return null;
        $f = $files[(int)($r['file_id'] ?? 0)] ?? null;
        if (!$f && !$r['preset']) return null;   // файл пропал — считаем, что обоев нет
        return ['preset' => $f ? null : $r['preset'], 'file_id' => $f ? (int)$f['id'] : null, 'url' => $f ? '/chat/file.php?id=' . (int)$f['id'] : null,
                'thumb' => $f ? chat_file_dto($f)['thumb'] : null,
                'dim' => (int)$r['dim'], 'by_me' => (int)$r['set_by'] === $myId, 'ts' => (int)$r['ts']];
    };
    $mine = $dto($rows['mine'] ?? null); $shared = $dto($rows['shared'] ?? null);
    $active = $mine ?? $shared;
    return ['active' => ($active && $active['preset'] !== 'none') ? $active : null,
            'scope'  => $mine ? 'mine' : ($shared ? 'shared' : null),
            'mine'   => $mine, 'shared' => $shared];
}

function need_v3(bool $v3): void {
    if (!$v3) out(['ok' => false, 'error' => 'migration', 'message' => 'Нужно прогнать chat/migrate_v2.php']);
}

if ($action === 'wallpaper_set' || $action === 'wallpaper_reset') {
    need_v3($V3);
    $cid = (int)($_POST['conversation_id'] ?? 0);
    $c = conv_access($db, $cid, $myId, $myStudioIds); if (!$c) out(['ok' => false, 'error' => 'forbidden']);
    if ($c['type'] === 'system') out(['ok' => false, 'error' => 'readonly']);
    $shared = ($_POST['scope'] ?? 'shared') !== 'mine';
    $owner  = $shared ? 0 : $myId;

    if ($action === 'wallpaper_reset') {
        $db->prepare("DELETE FROM conversation_wallpapers WHERE conversation_id=? AND user_id=?")->execute([$cid, $owner]);
    } else {
        $preset = (string)($_POST['preset'] ?? '');
        $fileId = (int)($_POST['file_id'] ?? 0);
        $dim    = max(0, min(80, (int)($_POST['dim'] ?? 0)));
        if ($fileId > 0) {
            $fs = $db->prepare("SELECT 1 FROM chat_files WHERE id=? AND owner_id=? AND kind='image' AND status='ready'");
            $fs->execute([$fileId, $myId]);
            if (!$fs->fetchColumn()) out(['ok' => false, 'error' => 'bad_file']);
            $preset = null;
        } elseif (!in_array($preset, CHAT_WP_PRESETS, true)) {
            out(['ok' => false, 'error' => 'bad_preset']);
        } elseif ($preset === 'none' && $shared) {
            // «без обоев для обоих» — это просто удаление общей записи
            $db->prepare("DELETE FROM conversation_wallpapers WHERE conversation_id=? AND user_id=0")->execute([$cid]);
            $preset = null;
        }
        if ($preset !== null || $fileId > 0) {
            $db->prepare("REPLACE INTO conversation_wallpapers(conversation_id,user_id,preset,file_id,dim,set_by) VALUES(?,?,?,?,?,?)")
               ->execute([$cid, $owner, $preset, $fileId ?: null, $dim, $myId]);
        }
    }
    // общие обои меняют картинку у собеседника — толкнём его клиент
    if ($shared && function_exists('ws_notify')) ws_notify($cid, ws_recipients($db, $cid, $myId));
    out(['ok' => true, 'wallpaper' => conv_wallpaper($db, $cid, $myId)]);
}

/* =================== ACTION: settings / save_settings ===================
 * Звук уведомлений хранится на сервере — одинаковый на всех устройствах. */
const CHAT_SOUNDS = ['dust', 'drop', 'pop', 'pixel', 'custom', 'none'];

function load_settings(PDO $db, int $myId): array {
    $st = $db->prepare("SELECT sound, sound_file_id, volume FROM chat_user_settings WHERE user_id=?");
    $st->execute([$myId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['sound' => 'dust', 'sound_file_id' => null, 'volume' => 70];
    $custom = null;
    if ($r['sound_file_id']) {
        $f = chat_files_by_ids($db, [(int)$r['sound_file_id']]);
        $custom = isset($f[(int)$r['sound_file_id']]) ? chat_file_dto($f[(int)$r['sound_file_id']]) : null;
    }
    $sound = in_array($r['sound'], CHAT_SOUNDS, true) ? $r['sound'] : 'dust';
    if ($sound === 'custom' && !$custom) $sound = 'dust';
    return ['sound' => $sound, 'volume' => (int)$r['volume'], 'custom' => $custom];
}

if ($action === 'settings') {
    if (!$V2) out(['ok' => true, 'settings' => ['sound' => 'dust', 'volume' => 70, 'custom' => null], 'v2' => false]);
    out(['ok' => true, 'settings' => load_settings($db, $myId), 'v2' => true]);
}

if ($action === 'save_settings') {
    need_v2($V2);
    $sound  = (string)($_POST['sound'] ?? 'dust');
    if (!in_array($sound, CHAT_SOUNDS, true)) out(['ok' => false, 'error' => 'bad_sound']);
    $volume = max(0, min(100, (int)($_POST['volume'] ?? 70)));
    $fileId = isset($_POST['sound_file_id']) ? (int)$_POST['sound_file_id'] : null;
    if ($fileId) {
        $fs = $db->prepare("SELECT 1 FROM chat_files WHERE id=? AND owner_id=? AND kind='sound' AND status='ready'");
        $fs->execute([$fileId, $myId]);
        if (!$fs->fetchColumn()) out(['ok' => false, 'error' => 'bad_file']);
    }
    $db->prepare("INSERT INTO chat_user_settings(user_id,sound,sound_file_id,volume) VALUES(?,?,?,?)
                  ON DUPLICATE KEY UPDATE sound=VALUES(sound), volume=VALUES(volume),
                                          sound_file_id=COALESCE(VALUES(sound_file_id), sound_file_id)")
       ->execute([$myId, $sound, $fileId ?: null, $volume]);
    out(['ok' => true, 'settings' => load_settings($db, $myId)]);
}

out(['ok'=>false,'error'=>'unknown_action']);