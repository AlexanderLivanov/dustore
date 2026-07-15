<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../swad/config.php';   // CONFIRM
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_bridge.php';
if (is_file(__DIR__ . '/push_helpers.php')) require_once __DIR__ . '/push_helpers.php';
if (is_file(__DIR__ . '/ws_helpers.php')) require_once __DIR__ . '/ws_helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out($d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['USERDATA'])) out(['ok' => false, 'error' => 'auth']);
$me   = $_SESSION['USERDATA'];
$myId = (int)($me['id'] ?? 0);
if ($myId <= 0) out(['ok' => false, 'error' => 'auth']);

// last-seen: троттлим апдейт активности (не чаще раза в 30с)
$db->prepare("UPDATE users SET last_activity=NOW()
               WHERE id=? AND (last_activity IS NULL OR last_activity < (NOW() - INTERVAL 30 SECOND))")->execute([$myId]);

$myStudioIds = get_user_studio_ids($db, $myId);
$action      = $_POST['action'] ?? $_GET['action'] ?? '';

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
/** Единый вызов из любого контроллера: положить уведомление юзеру. */
function send_notification(PDO $db, int $userId, string $text): int {
    $cid=ensure_system_conv($db,$userId);
    $db->prepare("INSERT INTO messages(conversation_id,sender_id,body,created_at) VALUES(?,0,?,NOW())")->execute([$cid,$text]);
    $mid=(int)$db->lastInsertId();
    $db->prepare("UPDATE conversations SET last_message_id=?, last_message_at=NOW() WHERE id=?")->execute([$mid,$cid]);
    $db->prepare("UPDATE conversation_participants SET archived=0 WHERE conversation_id=?")->execute([$cid]);
    if (function_exists('push_enqueue_user')) push_enqueue_user($db, $userId, 'Уведомление · Dustore', $text);
    if (function_exists('ws_notify')) ws_notify($cid, [$userId]);
    return $mid;
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

/* =================== ACTION: list =================== */
if ($action === 'list') {
    $tab=($_GET['tab'] ?? 'personal')==='studio' ? 'studio':'personal';
    $cards=[];
    if ($tab==='studio') {
        if(!$myStudioIds) out(['ok'=>true,'conversations'=>[]]);
        $in=implode(',',array_fill(0,count($myStudioIds),'?'));
        $rows=$db->prepare("SELECT * FROM conversations WHERE type='studio' AND studio_id IN ($in) ORDER BY last_message_at DESC LIMIT 200");
        $rows->execute($myStudioIds); $rows=$rows->fetchAll(PDO::FETCH_ASSOC);
        $studios=get_studios_meta($db,array_map(fn($r)=>(int)$r['studio_id'],$rows));
        $convCust=[]; $custIds=[];
        foreach($rows as $r){ $convCust[(int)$r['id']]=customer_of($db,(int)$r['id']); $custIds[]=$convCust[(int)$r['id']]; }
        $users=get_users_meta($db,$custIds);
        foreach($rows as $r){
            $cid=(int)$r['id']; $cust=$convCust[$cid];
            $q=$db->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id=? AND id>? AND sender_id=? AND deleted_at IS NULL");
            $q->execute([$cid,(int)$r['studio_last_read_id'],$cust]); $unread=(int)$q->fetchColumn();
            $cards[]=build_card($db,$r,['kind'=>'user','id'=>$cust,'name'=>$users[$cust]['username'] ?? ('user#'.$cust),
                'avatar'=>$users[$cust]['avatar'] ?? null,'tag'=>$studios[(int)$r['studio_id']]['name'] ?? null],$myId,$unread);
        }
    } else {
        ensure_system_conv($db,$myId); // блок «Уведомления» всегда есть
        $rows=$db->prepare(
            "SELECT c.* FROM conversations c
               JOIN conversation_participants p ON p.conversation_id=c.id
              WHERE p.user_id=? AND p.archived=0
              ORDER BY (c.type='system') DESC, c.last_message_at DESC LIMIT 200");
        $rows->execute([$myId]); $rows=$rows->fetchAll(PDO::FETCH_ASSOC);
        $peerUserIds=[]; $peerStudio=[]; $peerMap=[];
        foreach($rows as $r){
            if($r['type']==='studio'){ $peerStudio[]=(int)$r['studio_id']; $peerMap[(int)$r['id']]=['studio',(int)$r['studio_id']]; }
            elseif($r['type']==='system'){ $peerMap[(int)$r['id']]=['system',0]; }
            else { $o=$db->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id=? AND user_id<>? LIMIT 1");
                   $o->execute([(int)$r['id'],$myId]); $peer=(int)$o->fetchColumn(); $peerUserIds[]=$peer; $peerMap[(int)$r['id']]=['user',$peer]; }
        }
        $users=get_users_meta($db,$peerUserIds); $studios=get_studios_meta($db,$peerStudio);
        foreach($rows as $r){
            $cid=(int)$r['id']; [$kind,$pid]=$peerMap[$cid];
            $lr=$db->prepare("SELECT last_read_message_id FROM conversation_participants WHERE conversation_id=? AND user_id=?");
            $lr->execute([$cid,$myId]); $lastRead=(int)$lr->fetchColumn();
            $unread=unread_count($db,$cid,$lastRead,$myId);
            if($kind==='studio') $peer=['kind'=>'studio','id'=>$pid,'name'=>$studios[$pid]['name'] ?? ('studio#'.$pid),'avatar'=>$studios[$pid]['logo'] ?? null];
            elseif($kind==='system') $peer=['kind'=>'system','id'=>0,'name'=>'Уведомления','avatar'=>null];
            else $peer=['kind'=>'user','id'=>$pid,'name'=>$users[$pid]['username'] ?? ('user#'.$pid),'avatar'=>$users[$pid]['avatar'] ?? null];
            $cards[]=build_card($db,$r,$peer,$myId,$unread);
        }
    }
    out(['ok'=>true,'conversations'=>$cards]);
}
function build_card(PDO $db, array $r, array $peer, int $myId, int $unread): array {
    $last=null;
    if($r['last_message_id']){
        $m=$db->prepare("SELECT sender_id, body, created_at, deleted_at FROM messages WHERE id=?");
        $m->execute([(int)$r['last_message_id']]);
        if($lm=$m->fetch(PDO::FETCH_ASSOC)){
            $body=$lm['deleted_at'] ? 'сообщение удалено' : $lm['body'];
            $last=['body'=>$body,'at'=>$lm['created_at'],'mine'=>(int)$lm['sender_id']===$myId];
        }
    }
    return ['id'=>(int)$r['id'],'type'=>$r['type'],'peer'=>$peer,'last'=>$last,'unread'=>$unread,'ts'=>$r['last_message_at']];
}

/* ── Уведомления как виртуальная беседа ──────────────────────────── */

/** Anti-corruption layer: сырая строка БД → фиксированный контракт для фронта */
function notif_dto(array $n): array {
    return [
        'id'     => (int)$n['id'],
        'title'  => $n['title'] ?? 'Уведомление',
        'body'   => $n['text']  ?? $n['message'] ?? '',
        'link'   => $n['link']  ?? null,
        'ts'     => $n['created_at'] ?? $n['date'] ?? null,
        'unread' => (($n['status'] ?? '') === 'unread'),
    ];
}

/* =================== ACTION: unread_total =================== */
if ($action === 'unread_total') {
    $rows=$db->prepare("SELECT c.id,(SELECT last_read_message_id FROM conversation_participants WHERE conversation_id=c.id AND user_id=?) AS my_read
                          FROM conversations c JOIN conversation_participants p ON p.conversation_id=c.id
                         WHERE p.user_id=? AND p.archived=0");
    $rows->execute([$myId,$myId]); $rows=$rows->fetchAll(PDO::FETCH_ASSOC);
    $total=0; foreach($rows as $r){ $total+=unread_count($db,(int)$r['id'],(int)($r['my_read']??0),$myId); }
    out(['ok'=>true,'total'=>$total]);
}

/* =================== ACTION: search_users =================== */
if ($action === 'search_users') {
    $q=trim((string)($_GET['q'] ?? '')); if(mb_strlen($q)<2) out(['ok'=>true,'users'=>[]]);
    $like='%'.$q.'%'; $starts=$q.'%';
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
    $after=(int)($_REQUEST['after_id'] ?? 0);
    $q=$db->prepare("SELECT id, sender_id, body, created_at, deleted_at FROM messages WHERE conversation_id=? AND id>? ORDER BY id ASC LIMIT 500");
    $q->execute([$cid,$after]); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $smeta=get_users_meta($db,array_map(fn($m)=>(int)$m['sender_id'],$rows));
    $msgs=[];
    foreach($rows as $m){
        $msgs[]=['id'=>(int)$m['id'],'mine'=>(int)$m['sender_id']===$myId,
            'sender'=>['id'=>(int)$m['sender_id'],'name'=>$smeta[(int)$m['sender_id']]['username'] ?? ('user#'.$m['sender_id']),
                       'avatar'=>$smeta[(int)$m['sender_id']]['avatar'] ?? null],
            'body'=>$m['deleted_at'] ? null : $m['body'],'deleted'=>(bool)$m['deleted_at'],'at'=>$m['created_at']];
    }
    // ФИКС прочтения: отмечаем по НАСТОЯЩЕМУ последнему id беседы, обе ветки указателя
    $trueMax=(int)$c['last_message_id'];
    if($trueMax>0){
        if($c['_part']) $db->prepare("UPDATE conversation_participants SET last_read_message_id=GREATEST(last_read_message_id,?)
                                       WHERE conversation_id=? AND user_id=?")->execute([$trueMax,$cid,$myId]);
        if($c['_isStudioStaff']) $db->prepare("UPDATE conversations SET studio_last_read_id=GREATEST(studio_last_read_id,?) WHERE id=?")->execute([$trueMax,$cid]);
    }
    out(['ok'=>true,'messages'=>$msgs,'header'=>thread_header($db,$c,$myId)]);
}
function thread_header(PDO $db, array $c, int $myId): array {
    if($c['type']==='system') return ['kind'=>'system','peer_id'=>0,'studio'=>false,'name'=>'Уведомления','avatar'=>null,'tag'=>null,'last_seen'=>null];
    if($c['type']==='studio'){
        $s=(get_studios_meta($db,[(int)$c['studio_id']]))[(int)$c['studio_id']] ?? [];
        if($c['_isStudioStaff']){
            $cust=customer_of($db,(int)$c['id']); $u=(get_users_meta($db,[$cust]))[$cust] ?? [];
            $la=$db->prepare("SELECT last_activity FROM users WHERE id=?"); $la->execute([$cust]); $seen=$la->fetchColumn() ?: null;
            return ['kind'=>'user','peer_id'=>$cust,'studio'=>true,'name'=>$u['username'] ?? ('user#'.$cust),'avatar'=>$u['avatar'] ?? null,'tag'=>$s['name'] ?? null,'last_seen'=>$seen];
        }
        return ['kind'=>'studio','peer_id'=>(int)$c['studio_id'],'studio'=>true,'name'=>$s['name'] ?? ('studio#'.$c['studio_id']),'avatar'=>$s['logo'] ?? null,'tag'=>null,'last_seen'=>null];
    }
    $o=$db->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id=? AND user_id<>? LIMIT 1");
    $o->execute([(int)$c['id'],$myId]); $peer=(int)$o->fetchColumn();
    $u=(get_users_meta($db,[$peer]))[$peer] ?? [];
    $la=$db->prepare("SELECT last_activity FROM users WHERE id=?"); $la->execute([$peer]); $seen=$la->fetchColumn() ?: null;
    return ['kind'=>'user','peer_id'=>$peer,'studio'=>false,'name'=>$u['username'] ?? ('user#'.$peer),'avatar'=>$u['avatar'] ?? null,'tag'=>null,'last_seen'=>$seen];
}

/* =================== ACTION: send =================== */
if ($action === 'send') {
    $body=trim((string)($_POST['body'] ?? '')); if($body==='') out(['ok'=>false,'error'=>'empty']);
    if(mb_strlen($body)>4000) out(['ok'=>false,'error'=>'too_long']);
    $cid=(int)($_POST['conversation_id'] ?? 0);
    if(!$cid){
        $toUser=(int)($_POST['to'] ?? 0); $toStudio=(int)($_POST['studio'] ?? 0);
        if($toUser>0 && $toUser!==$myId) $cid=resolve_dm($db,$myId,$toUser);
        elseif($toStudio>0) $cid=resolve_studio($db,$myId,$toStudio);
        else out(['ok'=>false,'error'=>'bad_target']);
    }
    $c=conv_access($db,$cid,$myId,$myStudioIds); if(!$c) out(['ok'=>false,'error'=>'forbidden']);
    if($c['type']==='system') out(['ok'=>false,'error'=>'readonly']); // в «Уведомления» не пишем руками

    $db->prepare("INSERT INTO messages(conversation_id,sender_id,body,created_at) VALUES(?,?,?,NOW())")->execute([$cid,$myId,$body]);
    $msgId=(int)$db->lastInsertId();
    $db->prepare("UPDATE conversations SET last_message_id=?, last_message_at=NOW() WHERE id=?")->execute([$msgId,$cid]);
    // ФИКС: любое новое сообщение возвращает беседу из архива всем участникам
    $db->prepare("UPDATE conversation_participants SET archived=0 WHERE conversation_id=?")->execute([$cid]);
    if($c['_isStudioStaff']) $db->prepare("UPDATE conversations SET studio_last_read_id=GREATEST(studio_last_read_id,?) WHERE id=?")->execute([$msgId,$cid]);
    else $db->prepare("UPDATE conversation_participants SET last_read_message_id=GREATEST(last_read_message_id,?) WHERE conversation_id=? AND user_id=?")->execute([$msgId,$cid,$myId]);

    if(is_file(__DIR__.'/../vk/vk_helpers.php')){ require_once __DIR__.'/../vk/vk_helpers.php';
        if(function_exists('vk_enqueue_for_conversation')) vk_enqueue_for_conversation($db,$cid,$myId,$body); }
    if(function_exists('push_enqueue_for_conversation'))
        push_enqueue_for_conversation($db,$cid,$myId, ($me['username'] ?? ($me['first_name'] ?? 'Новое сообщение')), $body);
    if(function_exists('ws_notify')) ws_notify($cid, ws_recipients($db, $cid, $myId));

    out(['ok'=>true,'conversation_id'=>$cid,'message'=>['id'=>$msgId,'mine'=>true,'body'=>$body,'deleted'=>false,'at'=>date('Y-m-d H:i:s'),
        'sender'=>['id'=>$myId,'name'=>$me['username'] ?? ($me['first_name'] ?? 'me'),'avatar'=>avatar_url($me['profile_picture'] ?? '')]]]);
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

out(['ok'=>false,'error'=>'unknown_action']);