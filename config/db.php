<?php
// config/db.php

// --- CONFIGURACIÓN DE ERRORES ---
// Desactivamos el reporte de E_DEPRECATED. 
// Esto es necesario para que las librerías antiguas (como Dompdf 2.x o versiones no adaptadas a PHP 8.4) 
// no generen warnings que corrompan los archivos binarios (PDF, Excel) o llenen el log de errores.
error_reporting(E_ALL & ~E_DEPRECATED);

class Database {
    // Ajusta estos valores a tu entorno local
    private $host = 'localhost';
    private $db_name = 'obradoir_kahoot_gemini';
    private $username = 'obradoir_kahoot_gemini'; 
    private $password = '$kaho20A10b96C*11d01'; 

    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // En producción, esto debería ir a un archivo de log del servidor
            error_log("Connection error: " . $exception->getMessage());
            // Devolvemos un JSON de error si falla la conexión para que el frontend lo maneje
            die(json_encode(["error" => "Error de conexión a la base de datos."]));
        }
        return $this->conn;
    }
}
?>