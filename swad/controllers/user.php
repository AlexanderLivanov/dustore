<?php

require_once('jwt.php');

class User
{
    private $db;
    private $table = 'users';

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
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
    // Function to get user privileges
    public function getUserPrivileges($id){
        // TODO: get usernames from DB
        $creators = ['7107471254'];
        $moders = [];
        $admins = [];

        if(in_array($id, $creators, true)){
            return -1;
        }else if(in_array($id, $moders, true)){
            return 1;
        }else if(in_array($id, $admins, true)){
            return 2;
        }else{
            return 0;
        }
    }

    public function printUserPrivileges($id){
        $priv = $this->getUserPrivileges($id);
        switch($priv){
            case -1:
                echo "Создатель";
                break;
            case 0:
                echo "Пользователь";
                break;
            case 1:
                echo "Модератор";
                break;
            case 2:
                echo "Администратор";
            default:
                echo "Неверный идентификатор";
        }
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
}