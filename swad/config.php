<?php
// 08.03.2025 (c) Alexander Livanov

// 26.04.2025

require_once('pass.php');


class Database {

    // Important Note: function use_pack() находится в файле secrets.php. Будущие программисты, извините
    // меня за такой костыль, просто мои текущие знания и отсутствие свободного времени не позволяют сделать это нормально.
    // Спасибо. (с) 10.05.2025 Alexander Livanov
    function get_creds(){
        if ($_SERVER['HTTP_HOST'] == '127.0.0.1') {
            return use_pack('LOCAL');
        } else if ($_SERVER['HTTP_HOST'] == 'dustore.ru') {
            return use_pack('PRODUCTION');
        }
    }

    private $conn;

    // DB Connect (PDO)
    public function connect()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->get_creds()[0] . ';dbname=' . $this->get_creds()[1],
                $this->get_creds()[2],
                $this->get_creds()[3]
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
        }

        return $this->conn;
    }

    // Execute Statement
    public function executeStatement($statement = "", $parameters = [])
    {
        try {
            $stmt = $this->connect()->prepare($statement);
            $stmt->execute($parameters);
            return $stmt;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    // Insert Row/Rows To Database - INSERT (Create)
    public function Insert($statement = "", $parameters = [])
    {
        try {
            $this->executeStatement($statement, $parameters);
            return $this->connect()->lastInsertId();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    // Select Row/Rows From Database - SELECT (Read)
    public function Select($statement = "", $parameters = [])
    {
        try {
            $stmt = $this->executeStatement($statement, $parameters);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    // Update Row/Rows From Database - UPDATE
    public function Update($statement = "", $parameters = [])
    {
        try {
            $this->executeStatement($statement, $parameters);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    // Delete Row/Rows From Database - DELETE  
    public function Remove($statement = "", $parameters = [])
    {
        try {
            $this->executeStatement($statement, $parameters);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}