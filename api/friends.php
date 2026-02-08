<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/User.php';
require_once __DIR__ . '/../swad/controllers/NotificationCenter.php';

$user = new User();

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'not_auth'
    ]);
    exit;
}

$currentUser = (int)$_SESSION['USERDATA']['id'];
$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {

    switch ($action) {

        case 'send':

            $to = (int)($_POST['user_id'] ?? 0);

            $user->sendFriendRequest($currentUser, $to);

            echo json_encode([
                'success' => true,
                'message' => 'request_sent'
            ]);
            break;

        case 'accept':

            $from = (int)($_POST['user_id'] ?? 0);

            $user->acceptFriendRequest($currentUser, $from);

            echo json_encode([
                'success' => true,
                'message' => 'request_accepted'
            ]);
            break;

        case 'decline':

            $from = (int)($_POST['user_id'] ?? 0);

            $user->declineFriendRequest($currentUser, $from);

            echo json_encode([
                'success' => true,
                'message' => 'request_declined'
            ]);
            break;

        case 'list':

            $stmt = $user->db->prepare("
                SELECT u.id, u.username, u.profile_picture, f.status
                FROM friends f
                JOIN users u
                  ON (u.id = f.friend_id AND f.player_id = ?)
                  OR (u.id = f.player_id AND f.friend_id = ?)
                WHERE f.status = 'accepted'
            ");

            $stmt->execute([$currentUser, $currentUser]);

            echo json_encode([
                'success' => true,
                'friends' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'incoming':

            $stmt = $user->db->prepare("
                SELECT u.id, u.username, u.profile_picture, f.created_at
                FROM friends f
                JOIN users u ON u.id = f.player_id
                WHERE f.friend_id = ?
                  AND f.status = 'pending'
            ");

            $stmt->execute([$currentUser]);

            echo json_encode([
                'success' => true,
                'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'outgoing':

            $stmt = $user->db->prepare("
                SELECT u.id, u.username, u.profile_picture, f.created_at
                FROM friends f
                JOIN users u ON u.id = f.friend_id
                WHERE f.player_id = ?
                  AND f.status = 'pending'
            ");

            $stmt->execute([$currentUser]);

            echo json_encode([
                'success' => true,
                'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;


        default:
            echo json_encode([
                'success' => false,
                'error' => 'unknown_action'
            ]);
    }
} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
