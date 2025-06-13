<?php
// config/database.php - Configuración de base de datos

class Database {
    private static $instance = null;
    private $connection;
    
    private $host = 'localhost';
    private $database = 'xzorra_EARNVIDS';
    private $username = 'xzorra_EARNVIDS';
    private $password = '*gR*X5?QM:KlQ5q9';
    private $charset = 'utf8mb4';
    
    private function __construct() {
        try {
            $this->connection = new mysqli(
                $this->host,
                $this->username,
                $this->password,
                $this->database
            );
            
            if ($this->connection->connect_error) {
                throw new Exception('Error de conexión: ' . $this->connection->connect_error);
            }
            
            $this->connection->set_charset($this->charset);
        } catch (Exception $e) {
            die('Error de base de datos: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function affectedRows() {
        return $this->connection->affected_rows;
    }
    
    public function error() {
        return $this->connection->error;
    }
    
    public function close() {
        $this->connection->close();
    }
}