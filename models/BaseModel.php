<?php
defined('APP') || exit;

/**
 * Acceso a datos común. Todos los modelos comparten una sola conexión por
 * petición, para que una transacción abarque varias tablas (incluida bitácora).
 */
abstract class BaseModel
{
    private static ?mysqli $db = null;

    protected static function db(): mysqli
    {
        return self::$db ??= (new Conexion())->conectar();
    }

    private function preparar(string $sql, array $params): mysqli_stmt
    {
        $stmt = self::db()->prepare($sql);
        if ($params) {
            $tipos = '';
            foreach ($params as $p) {
                $tipos .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
            $stmt->bind_param($tipos, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    protected function consultar(string $sql, array $params = []): array
    {
        $stmt = $this->preparar($sql, $params);
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $filas;
    }

    protected function fila(string $sql, array $params = []): ?array
    {
        return $this->consultar($sql, $params)[0] ?? null;
    }

    protected function valor(string $sql, array $params = [])
    {
        $fila = $this->fila($sql, $params);
        return $fila ? reset($fila) : null;
    }

    /** Ejecuta INSERT, UPDATE o DELETE y devuelve las filas afectadas. */
    protected function ejecutar(string $sql, array $params = []): int
    {
        $stmt = $this->preparar($sql, $params);
        $afectadas = $stmt->affected_rows;
        $stmt->close();
        return $afectadas;
    }

    protected function ultimoId(): int
    {
        return (int) self::db()->insert_id;
    }

    /** Ejecuta $fn dentro de una transacción: COMMIT si termina, ROLLBACK si lanza. */
    protected function transaccion(callable $fn)
    {
        $db = self::db();
        $db->begin_transaction();
        try {
            $resultado = $fn();
            $db->commit();
            return $resultado;
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Hora actual según MySQL. PHP y MySQL pueden tener zonas horarias distintas
     * en XAMPP, así que toda comparación de fechas usa un solo reloj: el de MySQL.
     */
    public function ahora(): string
    {
        return (string) self::db()->query('SELECT NOW()')->fetch_row()[0];
    }
}
