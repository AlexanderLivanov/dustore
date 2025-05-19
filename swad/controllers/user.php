<?php
class User
{
    private $db;
    private $table = 'users';

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function getIDByTelegramId($tel_id)
    {
        $query = 'SELECT id FROM ' . $this->table . ' WHERE telegram_id = :tel_id LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
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
}