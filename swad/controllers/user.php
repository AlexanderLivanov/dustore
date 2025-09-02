<?php

require_once('jwt.php');

class User
{
    // 22.05.2025: Сделал эти поля публичными, иначе другие классы не могут обращаться к БД
    // (да, я не умю наследовать классы) (c) Alexander Livanov
    public $db;
    public $table = 'users';

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function getID($telegram_id){
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

    // 01.09.2025 (c) Alexander Livanov - new 

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

    // end new

    // 19.05.2025 (c) Alexander Livanov
    public function printUserPrivileges($role){
        switch($role){
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

    public function getUserRole($id, $type){
        if($type == "in_company"){
            $stmt = $this->db->prepare("
                SELECT `role_id` FROM user_organization WHERE user_id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC)['role_id'];
        }else if($type == "global"){
            $stmt = $this->db->prepare("
                SELECT `global_role` FROM users WHERE telegram_id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC)['global_role'];
        }
    }

    public function getRoleName($role_id){
        $stmt = $this->db->prepare("
            SELECT `name` FROM roles WHERE id = ?
        ");
        $stmt->execute([$role_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['name'];
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

    // (c) 01.06.2025 Alexander Livanov
    public function checkAuth()
    {
        if (empty($_COOKIE['auth_token'])) {
            return 1;
        }

        $telegram_id = $this->auth();

        if (!$telegram_id) {
            setcookie('auth_token', '', time() - 3600, '/');
            // header('HTTP/1.1 403 Forbidden');
            return 2;
        }

        // get user info from DB
        $query = 'SELECT * FROM ' . $this->table . ' WHERE telegram_id = :telegram_id LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->execute(['telegram_id' => $telegram_id]);
        $user = $stmt->fetch();

        if (!$user) {
            // header('HTTP/1.1 404 Not Found');
            return 3;
        }
        
        $_SESSION['USERDATA'] = $user;
        // $_SESSION['auth_token'] = $_COOKIE['auth_token'];
        return 0;
    }

    public function auth() {
        if(empty($_SESSION['auth_token'])){
            $_SESSION['auth_token'] = $_COOKIE['auth_token'];
            return validateToken($_COOKIE['auth_token']);
        }
        return validateToken($_COOKIE['auth_token']);
    }

    public function checkRole(){
        if ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] < 2) {
            echo ("<script>alert('У вас нет прав на использование этой функции');</script>");
            exit();
        }else{
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

        // TODO: сделать систему уведомлений
        // $this->sendNotification($userId, "You've been added to organization");
    }


    // 29.06.2025 (c) Alexander Livanov: function getUserOrgs will be deprecated soon. Use getUO() instead;
    public function getUserOrgs($user_id, $limit="100")
    {
        $stmt = $this->db->prepare(
            "SELECT * WHERE owner_id = :id ORDER BY status DESC LIMIT $limit;");
        $stmt->execute(['id' => $user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUO($user_id, $limit="100"){
        $stmt = $this->db->prepare(
            "SELECT * FROM studios WHERE owner_id = :id ORDER BY status DESC LIMIT $limit;"
        );
        $stmt->execute(['id' => $user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrgData($org_id){
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

    // 30.08.2025 (c) Alexander Livanov

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

    // 01.09.2025 (с) Alexander Livanov

    public function updatePassphrase($userID, $hashed_passphrase)
    {
        try {
            // Если передано null - очищаем passphrase (отключаем)
            if ($hashed_passphrase === null) {
                $stmt = $this->db->prepare(
                    "UPDATE users SET passphrase = NULL, updated = NOW() WHERE telegram_id = :user_id;"
                );
                return $stmt->execute(['user_id' => $userID]);
            }

            // Если передана passphrase - обновляем
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

    // 02.09.2025
    // Добавьте этот метод в класс User
    public function logout()
    {
        // Уничтожаем сессию
        session_unset();
        session_destroy();

        // Удаляем куку auth_token
        setcookie('auth_token', '', time() - 3600, '/');

        // Перенаправляем на главную
        header('Location: /');
        exit;
    }
}

class Moderator extends User 
{
    public function getAllPendingOrgs(){
        $stmt = $this->db->prepare(
            "SELECT     
                            o.id AS organization_id,
                            o.name AS organization_name,
                            o.created_at AS created_at
                            r.name AS user_role,
                            uo.status 
                        FROM user_organization uo
                        JOIN organizations o ON o.id = uo.organization_id
                        JOIN roles r ON r.id = uo.role_id
                        WHERE status = 'pending' ORDER BY created_at DESC;"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}