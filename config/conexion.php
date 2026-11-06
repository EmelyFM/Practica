<?php
class Conexion {
    private string $host = "localhost";
    private string $user = "root";
    private string $pass = "";
    private string $db = "gestion_reservas_vehiculos";
    
    protected ?mysqli $conexion = null;

    // Devuelve un objeto mysqli conectado a gestion_reservas_vehiculos
    public function conectar(): mysqli {
        if ($this->conexion === null) {
            $host = getenv('DB_HOST') ?: $this->host;
            $user = getenv('DB_USER') ?: $this->user;
            $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $this->pass;
            $dbName = getenv('DB_NAME') ?: $this->db;

            $this->conexion = @new mysqli($host, $user, $pass, $dbName, 3306);
            
            if ($this->conexion->connect_errno) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode([
                    'success' => false,
                    'message' => "Error al conectar a la base de datos: " . $this->conexion->connect_error
                ], JSON_UNESCAPED_UNICODE));
            }
            
            // Configurar codificación de caracteres UTF-8
            $this->conexion->set_charset("utf8mb4");
        }
        
        return $this->conexion;
    } 

    public function desconectar(): void {
        if ($this->conexion !== null) {
            $this->conexion->close();
            $this->conexion = null;
        }
    }
}
?>