<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* --- bootstrap --- */
require_once __DIR__ . '/../swad/config.php';   // CONFIRM: путь к файлу с class Database
require_once __DIR__ . '/_helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db = (new Database())->connect('dustore');     // ваш паттерн доступа к БД
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out($d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['USERDATA'])) out(['ok' => false, 'error' => 'auth']);
$me   = $_SESSION['USERDATA'];
$myId = (int)($me['id'] ?? 0);                  // CONFIRM: ключ id внутри USERDATA
if ($myId <= 0) out(['ok' => false, 'error' => 'auth']);

$myStudioIds = get_user_studio_ids($db, $myId);
$action      = $_POST['action'] ?? $_GET['action'] ?? '';

/* ===================================================================
   Резолверы беседы (с защитой от гонки по UNIQUE(dm_key))
   =================================================================== */
function resolve_dm(PDO $db, int $a, int $b): int {
    $key = dm_key($a, $b);
    try {
        $db->prepare("INSERT INTO conversations(type,dm_key,created_at,last_message_at)
                      VALUES('dm',?,NOW(),NOW())")->execute([$key]);
        $id  = (int)$db->lastInsertId();
        $ins = $db->prepare("INSERT IGNORE INTO conversation_participants(conversation_id,user_id,role)
                             VALUES(?,?, 'member')");
        $ins->execute([$id, $a]);
        $ins->execute([$id, $b]);
        return $id;
    } catch (PDOException $e) {                  // дубль ключа -> беседа уже есть
        $st = $db->prepare("SELECT id FROM conversations WHERE dm_key=? LIMIT 1");
        $st->execute([$key]);
        return (int)$st->fetchColumn();
    }
}

function resolve_studio(PDO $db, int $customerId, int $studioId): int {
    $key = studio_key($customerId, $studioId);
    try {
        $db->prepare("INSERT INTO conversations(type,studio_id,dm_key,created_at,last_message_at)
                      VALUES('studio',?,?,NOW(),NOW())")->execute([$studioId, $key]);
        $id = (int)$db->lastInsertId();
        $db->prepare("INSERT IGNORE INTO conversation_participants(conversation_id,user_id,role)
                      VALUES(?,?, 'customer')")->execute([$id, $customerId]);
        return $id;
    } catch (PDOException $e) {
        $st = $db->prepare("SELECT id FROM conversations WHERE dm_key=? LIMIT 1");
        $st->execute([$key]);
        return (int)$st->fetchColumn();
    }
}

/** Доступ к беседе + контекст роли. null = нет доступа. */
function conv_access(PDO $db, int $convId, int $myId, array $myStudioIds): ?array {
    $st = $db->prepare("SELECT * FROM conversations WHERE id=? LIMIT 1");
    $st->execute([$convId]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) return null;

    $p = $db->prepare("SELECT role,last_read_message_id FROM conversation_participants
                       WHERE conversation_id=? AND user_id=?");
    $p->execute([$convId, $myId]);
    $part = $p->fetch(PDO::FETCH_ASSOC) ?: null;

    $isStudioStaff = $c['type'] === 'studio'
        && in_array((int)$c['studio_id'], $myStudioIds, true);

    if (!$part && !$isStudioStaff) return null;
    $c['_part']          = $part;
    $c['_isStudioStaff'] = $isStudioStaff;
    return $c;
}

function customer_of(PDO $db, int $convId): int {
    $st = $db->prepare("SELECT user_id FROM conversation_participants
                        WHERE conversation_id=? AND role='customer' LIMIT 1");
    $st->execute([$convId]);
    return (int)$st->fetchColumn();
}

/* ===================================================================
   ACTION: list  — карточки бесед для вкладки
   =================================================================== */
if ($action === 'list') {
    $tab   = ($_GET['tab'] ?? 'personal') === 'studio' ? 'studio' : 'personal';
    $cards = [];

    if ($tab === 'studio') {
        if (!$myStudioIds) out(['ok' => true, 'conversations' => []]);
        $in   = implode(',', array_fill(0, count($myStudioIds), '?'));
        $rows = $db->prepare("SELECT * FROM conversations
                               WHERE type='studio' AND studio_id IN ($in)
                               ORDER BY last_message_at DESC LIMIT 200");
        $rows->execute($myStudioIds);
        $rows = $rows->fetchAll(PDO::FETCH_ASSOC);

        $custIds   = [];
        $studioIds = [];
        foreach ($rows as $r) { $studioIds[] = (int)$r['studio_id']; }
        $studios = get_studios_meta($db, $studioIds);
        // вытащим клиентов
        $convCust = [];
        foreach ($rows as $r) { $convCust[(int)$r['id']] = customer_of($db, (int)$r['id']); $custIds[] = $convCust[(int)$r['id']]; }
        $users = get_users_meta($db, $custIds);

        foreach ($rows as $r) {
            $cid  = (int)$r['id'];
            $cust = $convCust[$cid];
            // непрочитанное для студии = сообщения клиента позже studio_last_read_id
            $u = $db->prepare("SELECT COUNT(*) FROM messages
                               WHERE conversation_id=? AND id>? AND sender_id=?");
            $u->execute([$cid, (int)$r['studio_last_read_id'], $cust]);
            $cards[] = build_card($db, $r, [
                'kind'   => 'user',
                'id'     => $cust,
                'name'   => $users[$cust]['username'] ?? ('user#' . $cust),
                'avatar' => $users[$cust]['avatar']   ?? null,
                'tag'    => $studios[(int)$r['studio_id']]['name'] ?? null, // в какую студию пишут
            ], $myId, (int)$u->fetchColumn());
        }
    } else {
        $rows = $db->prepare(
            "SELECT c.* FROM conversations c
               JOIN conversation_participants p ON p.conversation_id=c.id
              WHERE p.user_id=? AND p.archived=0
              ORDER BY c.last_message_at DESC LIMIT 200");
        $rows->execute([$myId]);
        $rows = $rows->fetchAll(PDO::FETCH_ASSOC);

        $peerUserIds = [];
        $peerStudio  = [];
        $peerMap     = [];
        foreach ($rows as $r) {
            if ($r['type'] === 'studio') {
                $peerStudio[] = (int)$r['studio_id'];
                $peerMap[(int)$r['id']] = ['studio', (int)$r['studio_id']];
            } else {
                $o = $db->prepare("SELECT user_id FROM conversation_participants
                                   WHERE conversation_id=? AND user_id<>? LIMIT 1");
                $o->execute([(int)$r['id'], $myId]);
                $peer = (int)$o->fetchColumn();
                $peerUserIds[] = $peer;
                $peerMap[(int)$r['id']] = ['user', $peer];
            }
        }
        $users   = get_users_meta($db, $peerUserIds);
        $studios = get_studios_meta($db, $peerStudio);

        // мой last_read по каждой беседе
        foreach ($rows as $r) {
            $cid = (int)$r['id'];
            [$kind, $pid] = $peerMap[$cid];
            $lr = $db->prepare("SELECT last_read_message_id FROM conversation_participants
                                WHERE conversation_id=? AND user_id=?");
            $lr->execute([$cid, $myId]);
            $lastRead = (int)$lr->fetchColumn();
            $u = $db->prepare("SELECT COUNT(*) FROM messages
                               WHERE conversation_id=? AND id>? AND sender_id<>?");
            $u->execute([$cid, $lastRead, $myId]);

            if ($kind === 'studio') {
                $peer = ['kind' => 'studio', 'id' => $pid,
                         'name' => $studios[$pid]['name'] ?? ('studio#' . $pid),
                         'avatar' => $studios[$pid]['logo'] ?? null];
            } else {
                $peer = ['kind' => 'user', 'id' => $pid,
                         'name' => $users[$pid]['username'] ?? ('user#' . $pid),
                         'avatar' => $users[$pid]['avatar'] ?? null];
            }
            $cards[] = build_card($db, $r, $peer, $myId, (int)$u->fetchColumn());
        }
    }
    out(['ok' => true, 'conversations' => $cards]);
}

function build_card(PDO $db, array $r, array $peer, int $myId, int $unread): array {
    $last = null;
    if ($r['last_message_id']) {
        $m = $db->prepare("SELECT sender_id, body, created_at FROM messages WHERE id=?");
        $m->execute([(int)$r['last_message_id']]);
        if ($lm = $m->fetch(PDO::FETCH_ASSOC)) {
            $last = ['body' => $lm['body'], 'at' => $lm['created_at'],
                     'mine' => (int)$lm['sender_id'] === $myId];
        }
    }
    return [
        'id'     => (int)$r['id'],
        'type'   => $r['type'],
        'peer'   => $peer,
        'last'   => $last,
        'unread' => $unread,
        'ts'     => $r['last_message_at'],
    ];
}

/* ===================================================================
   ACTION: start — открыть/создать беседу по адресату, вернуть id
   params: to (user_id)  |  studio (studio_id)
   =================================================================== */
if ($action === 'start') {
    $toUser   = (int)($_REQUEST['to'] ?? 0);
    $toStudio = (int)($_REQUEST['studio'] ?? 0);
    if ($toUser > 0 && $toUser !== $myId) {
        out(['ok' => true, 'conversation_id' => resolve_dm($db, $myId, $toUser)]);
    }
    if ($toStudio > 0) {
        out(['ok' => true, 'conversation_id' => resolve_studio($db, $myId, $toStudio)]);
    }
    out(['ok' => false, 'error' => 'bad_target']);
}

/* ===================================================================
   ACTION: thread — сообщения беседы + отметка прочитанным
   params: conversation_id, [after_id]
   =================================================================== */
if ($action === 'thread') {
    $cid = (int)($_REQUEST['conversation_id'] ?? 0);
    $c   = conv_access($db, $cid, $myId, $myStudioIds);
    if (!$c) out(['ok' => false, 'error' => 'forbidden']);

    $after = (int)($_REQUEST['after_id'] ?? 0);
    $q = $db->prepare("SELECT id, sender_id, body, created_at FROM messages
                       WHERE conversation_id=? AND id>? ORDER BY id ASC LIMIT 500");
    $q->execute([$cid, $after]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    $senderIds = array_map(fn($m) => (int)$m['sender_id'], $rows);
    $smeta     = get_users_meta($db, $senderIds);
    $msgs = [];
    $maxId = $after;
    foreach ($rows as $m) {
        $maxId = max($maxId, (int)$m['id']);
        $msgs[] = [
            'id'     => (int)$m['id'],
            'mine'   => (int)$m['sender_id'] === $myId,
            'sender' => [
                'id'     => (int)$m['sender_id'],
                'name'   => $smeta[(int)$m['sender_id']]['username'] ?? ('user#' . $m['sender_id']),
                'avatar' => $smeta[(int)$m['sender_id']]['avatar'] ?? null,
            ],
            'body' => $m['body'],
            'at'   => $m['created_at'],
        ];
    }

    // отметить прочитанным
    if ($maxId > $after && $rows) {
        if ($c['_isStudioStaff']) {
            $db->prepare("UPDATE conversations SET studio_last_read_id=GREATEST(studio_last_read_id,?)
                          WHERE id=?")->execute([$maxId, $cid]);
        } else {
            $db->prepare("UPDATE conversation_participants
                          SET last_read_message_id=GREATEST(last_read_message_id,?)
                          WHERE conversation_id=? AND user_id=?")->execute([$maxId, $cid, $myId]);
        }
    }

    // шапка беседы (с чьей стороны смотрим)
    $header = thread_header($db, $c, $myId);
    out(['ok' => true, 'messages' => $msgs, 'header' => $header]);
}

function thread_header(PDO $db, array $c, int $myId): array {
    if ($c['type'] === 'studio') {
        $studios = get_studios_meta($db, [(int)$c['studio_id']]);
        $s = $studios[(int)$c['studio_id']] ?? [];
        if ($c['_isStudioStaff']) {                 // я — студия, пишет клиент
            $cust  = customer_of($db, (int)$c['id']);
            $users = get_users_meta($db, [$cust]);
            return ['kind' => 'user', 'studio' => true,
                    'name' => $users[$cust]['username'] ?? ('user#' . $cust),
                    'avatar' => $users[$cust]['avatar'] ?? null,
                    'tag' => $s['name'] ?? null];
        }
        return ['kind' => 'studio', 'studio' => true,
                'name' => $s['name'] ?? ('studio#' . $c['studio_id']),
                'avatar' => $s['logo'] ?? null, 'tag' => null];
    }
    $o = $db->prepare("SELECT user_id FROM conversation_participants
                       WHERE conversation_id=? AND user_id<>? LIMIT 1");
    $o->execute([(int)$c['id'], $myId]);
    $peer  = (int)$o->fetchColumn();
    $users = get_users_meta($db, [$peer]);
    return ['kind' => 'user', 'studio' => false,
            'name' => $users[$peer]['username'] ?? ('user#' . $peer),
            'avatar' => $users[$peer]['avatar'] ?? null, 'tag' => null];
}

/* ===================================================================
   ACTION: send — отправить сообщение
   params: conversation_id | (to | studio), body
   =================================================================== */
if ($action === 'send') {
    $body = trim((string)($_POST['body'] ?? ''));
    if ($body === '')               out(['ok' => false, 'error' => 'empty']);
    if (mb_strlen($body) > 4000)    out(['ok' => false, 'error' => 'too_long']);

    $cid = (int)($_POST['conversation_id'] ?? 0);
    if (!$cid) {                                     // создать на лету
        $toUser   = (int)($_POST['to'] ?? 0);
        $toStudio = (int)($_POST['studio'] ?? 0);
        if ($toUser > 0 && $toUser !== $myId)  $cid = resolve_dm($db, $myId, $toUser);
        elseif ($toStudio > 0)                 $cid = resolve_studio($db, $myId, $toStudio);
        else out(['ok' => false, 'error' => 'bad_target']);
    }

    $c = conv_access($db, $cid, $myId, $myStudioIds);
    if (!$c) out(['ok' => false, 'error' => 'forbidden']);

    $db->prepare("INSERT INTO messages(conversation_id,sender_id,body,created_at)
                  VALUES(?,?,?,NOW())")->execute([$cid, $myId, $body]);
    $msgId = (int)$db->lastInsertId();

    $db->prepare("UPDATE conversations SET last_message_id=?, last_message_at=NOW() WHERE id=?")
       ->execute([$msgId, $cid]);

    // продвигаем свой read-указатель (своё сообщение прочитано)
    if ($c['_isStudioStaff']) {
        $db->prepare("UPDATE conversations SET studio_last_read_id=GREATEST(studio_last_read_id,?)
                      WHERE id=?")->execute([$msgId, $cid]);
    } else {
        $db->prepare("UPDATE conversation_participants
                      SET last_read_message_id=GREATEST(last_read_message_id,?)
                      WHERE conversation_id=? AND user_id=?")->execute([$msgId, $cid, $myId]);
    }

    out(['ok' => true, 'message' => [
        'id' => $msgId, 'mine' => true, 'body' => $body,
        'at' => date('Y-m-d H:i:s'),
        'sender' => ['id' => $myId,
                     'name' => $me['username'] ?? ($me['first_name'] ?? 'me'),
                     'avatar' => avatar_url($me['profile_picture'] ?? '')],
    ], 'conversation_id' => $cid]);
}

out(['ok' => false, 'error' => 'unknown_action']);