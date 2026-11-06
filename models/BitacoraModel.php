<?php
defined('APP') || exit;

class BitacoraModel extends BaseModel
{
    /** Cualquier clave con este patrón se elimina antes de guardar los datos. */
    private const SENSIBLE = '/contrasena|password|clave|hash|token|csrf|secret/i';

    public function registrar(int $idUsuario, string $accion, string $modulo, string $descripcion, ?array $antes = null, ?array $despues = null): void
    {
        $this->ejecutar(
            'INSERT INTO bitacora (id_usuario, accion, modulo, datos_anteriores, datos_nuevos, descripcion)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$idUsuario, $accion, $modulo, $this->serializar($antes), $this->serializar($despues), $descripcion]
        );
    }

    private function serializar(?array $datos): ?string
    {
        return $datos === null ? null : json_encode($this->limpiar($datos), JSON_UNESCAPED_UNICODE);
    }

    private function limpiar(array $datos): array
    {
        $limpio = [];
        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && preg_match(self::SENSIBLE, $clave)) {
                continue;
            }
            $limpio[$clave] = is_array($valor) ? $this->limpiar($valor) : $valor;
        }
        return $limpio;
    }

    public function listar(?string $desde, ?string $hasta, ?string $modulo, int $limite = 2000): array
    {
        $sql = 'SELECT b.id_bitacora, b.fecha_hora, b.accion, b.modulo, b.descripcion,
                       b.datos_anteriores, b.datos_nuevos, u.nombre_usuario
                FROM bitacora b JOIN usuarios u ON u.id_usuario = b.id_usuario WHERE 1 = 1';
        $params = [];
        if ($desde) {
            $sql .= ' AND b.fecha_hora >= ?';
            $params[] = "$desde 00:00:00";
        }
        if ($hasta) {
            $sql .= ' AND b.fecha_hora <= ?';
            $params[] = "$hasta 23:59:59";
        }
        if ($modulo) {
            $sql .= ' AND b.modulo = ?';
            $params[] = $modulo;
        }
        $sql .= ' ORDER BY b.id_bitacora DESC LIMIT ?';
        $params[] = $limite;
        return $this->consultar($sql, $params);
    }

    public function modulos(): array
    {
        return array_column($this->consultar('SELECT DISTINCT modulo FROM bitacora ORDER BY modulo'), 'modulo');
    }
}
