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

    // 19.05.2025 (c) Alexander Livanov
    public function printUserPrivileges($role){
        switch($role){
            case 'creator':
                echo "Создатель этого мира";
                break;
            case 'user':
                echo "Обычный ользователь";
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
                SELECT `global_role` FROM users WHERE id = ?
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

    public function checkAuth()
    {
        if (!isset($_COOKIE['auth_token'])) {
            // header('HTTP/1.1 401 Unauthorized');
            return 1;
        }

        $telegram_id = validateToken($_COOKIE['auth_token']);

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

        return 0;
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

    public function getUserOrgs($user_id, $limit="100")
    {
        $stmt = $this->db->prepare(
            "SELECT     
                            o.id AS organization_id,
                            o.name AS organization_name,
                            r.name AS user_role,
                            uo.status 
                        FROM user_organization uo
                        JOIN organizations o ON o.id = uo.organization_id
                        JOIN roles r ON r.id = uo.role_id
                        WHERE uo.user_id = :id ORDER BY status DESC LIMIT $limit;");
        $stmt->execute(['id' => $user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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