<?php
class Conexion {
    private string $host = "db.updrkugocmgyyeaauwue.supabase.co";
    private string $port = "5432";
    private string $user = "postgres";
    private string $pass = "1234567891"; // Coloca tu contraseña real de Supabase
    private string $db   = "postgres";
    
    protected ?PDO $conexion = null;

    public function conectar(): PDO {
        if ($this->conexion === null) {
            $rawHost = getenv('DB_HOST') ?: $this->host;
            $port    = getenv('DB_PORT') ?: $this->port;
            $user    = getenv('DB_USER') ?: $this->user;
            $pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $this->pass;
            $dbName  = getenv('DB_NAME') ?: $this->db;

            // Si por error el host trae ":5432" al final, lo separa automáticamente
            if (str_contains($rawHost, ':')) {
                [$cleanHost, $extractedPort] = explode(':', $rawHost, 2);
                $host = $cleanHost;
                $port = $extractedPort;
            } else {
                $host = $rawHost;
            }

            try {
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};sslmode=require";
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
