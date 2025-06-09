<?php
// 02.06.2025 (c) Alexander Livanov
require_once('secrets.php');

class Database {
    private $conn;

    private function get_creds(){
        if ($_SERVER['HTTP_HOST'] == '127.0.0.1') {
            return use_pack('LOCAL');
        } else if ($_SERVER['HTTP_HOST'] == 'dustore.ru') {
            return use_pack('PRODUCTION');
        }
    }

    public function connect(){
        $this->conn = null;
        $host = $this->get_creds()[0];
        $db_name = $this->get_creds()[1];
        $user = $this->get_creds()[2];
        $passwd = $this->get_creds()[3];

        try {
            $this->conn = new PDO("mysql:host=$host;dbname=$db_name", $user, $passwd);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }

        return $this->conn;
    }

    private function executeStatement($statement = "", $parameters = [])
    {
        try {
            $stmt = $this->connect()->prepare($statement);
            $stmt->execute($parameters);
            return $stmt;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    // Public function
    public function EXEC($statement = "", $parameters = [])
    {
        try {
            $this->executeStatement($statement, $parameters);
            return $this->connect()->lastInsertId();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}