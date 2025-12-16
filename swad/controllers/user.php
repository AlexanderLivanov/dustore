<?php

require_once('jwt.php');

class User
{
    public $db;
    public $table = 'users';

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function getID($telegram_id)
    {
        $query = 'SELECT id FROM ' . $this->table . ' WHERE telegram_id = :telegram_id LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':telegram_id', $telegram_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }

    public function getUsername($id)
    {
        $query = 'SELECT telegram_username FROM ' . $this->table . ' WHERE telegram_id = :id LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['telegram_username'] : null;
    }

    public function getUserByUsername($username)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM users WHERE username = :username LIMIT 1;"
            );
            $stmt->execute(['username' => $username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user by username: " . $e->getMessage());
            return false;
        }
    }

    public function printUserPrivileges($role)
    {
        switch ($role) {
            case 'creator':
                echo "Создатель этого мира";
                break;
            case 'user':
                echo "Обычный пользователь";
                break;
            case 'employee':
                echo "Сотрудник в студии";
                break;
            case 'owner':
                echo "Владелец студии";
                break;
            case 'moder':
                echo "Модератор платформы";
                break;
            case 'admin':
                echo "Администратор платформы";
                break;
            default:
                echo "Неверный идентификатор";
        }
    }

    public function getUserRole($id, $type)
    {
        if ($type == "in_company") {
            $stmt = $this->db->prepare("
                SELECT `role_id` FROM user_organization WHERE user_id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['role_id'] : null; 
        } else if ($type == "global") {
            $stmt = $this->db->prepare("
                SELECT `global_role` FROM users WHERE telegram_id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['global_role'] : null; 
        }
        return null;
    }

    public function getRoleName($role_id)
    {
        if (empty($role_id)) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT `name` FROM roles WHERE id = ?
        ");
        $stmt->execute([$role_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['name'] : null; 
    }

    public function userHasRole($userId, $organizationId, $requiredRole)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM user_organization 
            WHERE 
                user_id = ? AND 
                organization_id = ? AND 
                role_id = (SELECT id FROM roles WHERE name = ?) AND 
                status = 'active'
        ");
        $stmt->execute([$userId, $organizationId, $requiredRole]);
        return $stmt->fetchColumn() > 0;
    }

    public function checkAuth()
    {
        if (empty($_COOKIE['auth_token'])) {
            return 1;
        }

        $telegram_id = $this->auth();

        if (!$telegram_id) {
            setcookie('auth_token', '', time() - 3600, '/');
            return 2;
        }

        // get user info from DB
        $query = 'SELECT * FROM ' . $this->table . ' WHERE telegram_id = :telegram_id LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->execute(['telegram_id' => $telegram_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC); // ✅ Добавлен PDO::FETCH_ASSOC

        if (!$user) {
            return 3;
        }

        $_SESSION['USERDATA'] = $user;
        return 0;
    }

    public function auth()
    {
        if (empty($_SESSION['auth_token'])) {
            $_SESSION['auth_token'] = $_COOKIE['auth_token'];
            return validateToken($_COOKIE['auth_token']);
        }
        return validateToken($_COOKIE['auth_token']);
    }

    public function checkRole()
    {
        if ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] < 2) {
            echo ("<script>alert('У вас нет прав на использование этой функции');</script>");
            exit();
        } else {
            return True;
        }
    }

    public function addUserToOrganization($owner_id, $userId, $organizationId, $givenRoleId)
    {
        if (!$this->userHasRole($owner_id, $organizationId, 'owner')) {
            throw new Exception("Access denied");
        }

        $stmt = $this->db->prepare("
                INSERT INTO user_organization 
                (user_id, organization_id, role_id) 
                VALUES (?, ?, ?)
            ");
        $stmt->execute([$userId, $organizationId, $givenRoleId]);
    }

    public function getUO($user_id, $limit = "100")
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM studios WHERE owner_id = :id ORDER BY status DESC LIMIT $limit;"
        );
        $stmt->execute(['id' => $user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrgData($org_id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM studios WHERE id = :id LIMIT 1;"
        );
        $stmt->execute(['id' => $org_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getOrgInfo($org_id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM studios WHERE id = :id LIMIT 1;"
        );
        $stmt->execute(['id' => $org_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function checkUsernameExists($username)
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM users WHERE username = :username LIMIT 1;"
        );
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    public function updateUsername($userID, $new_username)
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET username = :username, updated = NOW() WHERE telegram_id = :user_id;"
            );
            $stmt->execute([
                'username' => $new_username,
                'user_id' => $userID
            ]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error updating username: " . $e->getMessage());
            return false;
        }
    }

    public function updatePassphrase($userID, $hashed_passphrase)
    {
        try {
            if ($hashed_passphrase === null) {
                $stmt = $this->db->prepare(
                    "UPDATE users SET passphrase = NULL, updated = NOW() WHERE telegram_id = :user_id;"
                );
                return $stmt->execute(['user_id' => $userID]);
            }

            $stmt = $this->db->prepare(
                "UPDATE users SET passphrase = :passphrase, updated = NOW() WHERE telegram_id = :user_id;"
            );
            return $stmt->execute([
                'passphrase' => $hashed_passphrase,
                'user_id' => $userID
            ]);
        } catch (PDOException $e) {
            error_log("Error updating passphrase: " . $e->getMessage());
            return false;
        }
    }

    public function hasPassphrase($userID)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT passphrase FROM users WHERE telegram_id = :user_id LIMIT 1;"
            );
            $stmt->execute(['user_id' => $userID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return !empty($result['passphrase']);
        } catch (PDOException $e) {
            error_log("Error checking passphrase: " . $e->getMessage());
            return false;
        }
    }

    public function verifyPassphrase($userID, $passphrase)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT passphrase FROM users WHERE telegram_id = :user_id AND passphrase IS NOT NULL LIMIT 1;"
            );
            $stmt->execute(['user_id' => $userID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || empty($result['passphrase'])) {
                return false;
            }

            return password_verify($passphrase, $result['passphrase']);
        } catch (PDOException $e) {
            error_log("Error verifying passphrase: " . $e->getMessage());
            return false;
        }
    }

    public function logout()
    {
        session_unset();
        session_destroy();

        setcookie('auth_token', '', time() - 3600, '/');

        header('Location: /');
        exit;
    }

    public function updateUserCart($game_id, $method)
    {
        if (!isset($_COOKIE['USERCART'])) {
            $cart = [];
        } else {
            $cart = json_decode($_COOKIE['USERCART'], true);
            if ($cart === null) {
                $cart = [];
            }
        }

        if (!empty($game_id) && !empty($method)) {
            if ($method == "ADD") {
                if (isset($cart[$game_id])) {
                    $cart[$game_id]['quantity'] += 1;
                } else {
                    $cart[$game_id] = [
                        'game_id' => $game_id,
                        'quantity' => 1,
                        'added_at' => time()
                    ];
                }
            } elseif ($method == "REMOVE") {
                if (isset($cart[$game_id])) {
                    unset($cart[$game_id]);
                }
            } elseif ($method == "DECREASE") {
                if (isset($cart[$game_id])) {
                    if ($cart[$game_id]['quantity'] > 1) {
                        $cart[$game_id]['quantity'] -= 1;
                    } else {
                        unset($cart[$game_id]);
                    }
                }
            }

            setcookie("USERCART", json_encode($cart), time() + 60 * 60 * 24 * 30, "/");

            return [
                'success' => true,
                'cart' => $cart,
                'count' => count($cart),
                'total_items' => array_sum(array_column($cart, 'quantity'))
            ];
        }

        return [
            'success' => false,
            'error' => 'Invalid parameters'
        ];
    }

    public function getUserItems($user_id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM user_items WHERE user_id = :id LIMIT 1;"
        );
        $stmt->execute(['id' => $user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateUserItems($user_id, $game_id)
    {
        $stmt = $this->db->prepare("
            INSERT INTO library (player_id, game_id, purchased, date)
            SELECT ?, ?, 1, NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM library WHERE player_id = ? AND game_id = ?
            )
        ");
        $stmt->execute([$user_id, $game_id, $user_id, $game_id]);
    }
}
