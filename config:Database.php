<?php
require_once 'db_config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        // Opciones de seguridad y rendimiento para PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva emulación, usa prepared statements reales
        ];

        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción, no mostrar $e->getMessage() directamente (evita revelar estructuras de carpetas)
            error_log($e->getMessage());
            die("Error crítico de conexión. Por favor, intente más tarde.");
        }
    }

    // Patrón Singleton para optimizar conexiones
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}