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

    public function getUserById($id)
    {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE id = :id LIMIT 1';
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


// TODO: сделать для возможности изменения юзернейма
    // public function updateUsername($data, )
    // {
    //     $query = 'UPDATE ' . $this->table . ' SET username = :username WHERE id = :id';
    //     $stmt = $this->db->prepare($query);
    //     $stmt->bindParam(':username', $newUsername);
    //     $stmt->bindParam(':id', $id);

    //     return $stmt->execute();
    // }

    public function createUser($username, $email, $password)
    {
        $query = 'INSERT INTO ' . $this->table . ' (username, email, password) VALUES (:username, :email, :password)';
        $stmt = $this->db->prepare($query);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);

        return $stmt->execute();
    }

    public function userExists($username)
    {
        $query = 'SELECT id FROM ' . $this->table . ' WHERE username = :username LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }


    // 11.05.2025: DEPRECATED
    // public function auth($user, $username){
    //     session_start();
    //     $_SESSION['user_id'] = $user['id'];
    //     $_SESSION['username'] = $username;
    // }
}