<?php
// 08.03.2025 (c) Alexander Livanov

// 26.04.2025
class Database {
    private $host = 'localhost';
    private $db_name = 'dustore';
    private $username = 'root';
    private $password = '';
    private $conn;

    // DB Connect (PDO)
    public function connect()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
        }

        return $this->conn;
    }
}

class Time {
    
}