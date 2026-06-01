<?php
// 08.03.2025 (c) Alexander Livanov

// 26.04.2025

require_once('pass.php');

define('REGISTRATION_ENABLED', true);

class Database {

    // Important Note: function use_pack() находится в файле secrets.php. Будущие программисты, извините
    // меня за такой костыль, просто мои текущие знания и отсутствие свободного времени не позволяют сделать это нормально.
    // Спасибо. (с) 10.05.2025 Alexander Livanov

    // (с) 02.06.2026 Здесь что-то менялось тем же человеком, и, возможно, стало еще сложнее...
    function get_creds()
    {
        $host = $_SERVER['HTTP_HOST'];
        // Отделяем порт (если есть)
        $host = strtolower(explode(':', $host)[0]);

        $isLocal = false;

        // localhost и 127.0.0.1
        if ($host === '127.0.0.1' || $host === 'localhost') {
            $isLocal = true;
        }
        // Проверка частных IP-адресов (192.168.x.x, 10.x.x.x, 172.16.x.x - 172.31.x.x)
        elseif (filter_var($host, FILTER_VALIDATE_IP)) {
            $parts = explode('.', $host);
            if (count($parts) === 4) {
                $first = (int)$parts[0];
                if ($first === 10) $isLocal = true;                                   // 10.0.0.0/8
                elseif ($first === 172 && (int)$parts[1] >= 16 && (int)$parts[1] <= 31) $isLocal = true; // 172.16.0.0/12
                elseif ($first === 192 && (int)$parts[1] === 168) $isLocal = true;    // 192.168.0.0/16
            }
        }

        if ($isLocal) {
            return use_pack('LOCAL');
        } elseif ($host === 'dustore.ru') {
            return use_pack('PRODUCTION');
        } else {
            // Неизвестный хост – используем LOCAL как fallback (для отладки)
            return use_pack('LOCAL');
        }
    }

    private $conn;

    // DB Connect (PDO)
    public function connect($db_name="dustore")
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->get_creds()[0] . ';dbname=' . $db_name ?? $this->get_creds()[1] .
                    ';charset=utf8mb4',
                $this->get_creds()[2],
                $this->get_creds()[3],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
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