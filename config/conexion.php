<?php
class Conexion {
    private string $host = "db.updrkugocmgyyeaauwue.supabase.co";
    private string $port = "5432";
    private string $user = "postgres";
    private string $pass = "1234567891";
    private string $db   = "gestion_reservas_vehiculos";
    
    protected ?PDO $conexion = null;

    public function conectar(): PDO {
        if ($this->conexion === null) {
            $host   = getenv('DB_HOST') ?: $this->host;
            $port   = getenv('DB_PORT') ?: $this->port;
            $user   = getenv('DB_USER') ?: $this->user;
            $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $this->pass;
            $dbName = getenv('DB_NAME') ?: $this->db;

            try {
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbName;sslmode=require";
                $this->conexion = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode([
                    'success' => false,
                    'message' => "Error de conexión a PostgreSQL: " . $e->getMessage()
                ], JSON_UNESCAPED_UNICODE));
            }
        }
        
        return $this->conexion;
    } 

    public function desconectar(): void {
        $this->conexion = null;
    }
}
?>
