<?php
class User {
    private $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function auth(){
        if(empty($_COOKIE['auth_token'])){
            
        }
    }
}