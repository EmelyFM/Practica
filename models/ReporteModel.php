<?php
defined('APP') || exit;

/** Consultas de los indicadores del panel y de los reportes PDF. */
class ReporteModel extends BaseModel
{
    private const EMPLEADO = "TRIM(CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, '')))";

    public function indicadoresAdmin(): array
    {
        $flota = $this->fila(
            "SELECT COUNT(*) AS total_vehiculos,
                    COALESCE(SUM(estado = 'Disponible'), 0) AS vehiculos_operativos,
                    COALESCE(SUM(estado = 'Mantenimiento'), 0) AS vehiculos_mantenimiento
             FROM vehiculos"
        );
        $reservas = $this->fila(
            "SELECT COALESCE(SUM(estado IN ('Confirmada', 'En uso')), 0) AS reservas_activas,
                    COALESCE(SUM(estado = 'Pendiente'), 0) AS reservas_pendientes,
                    COALESCE(SUM(estado = 'Finalizada'), 0) AS reservas_finalizadas
             FROM reservas"
        );
        return array_map('intval', $flota + $reservas);
    }

    public function indicadoresEmpleado(int $idEmpleado): array
    {
        $fila = $this->fila(
            "SELECT COUNT(*) AS mis_reservas,
                    COALESCE(SUM(estado = 'Pendiente'), 0) AS pendientes,
                    COALESCE(SUM(estado = 'Confirmada'), 0) AS confirmadas,
                    COALESCE(SUM(estado = 'En uso'), 0) AS en_uso,
                    COALESCE(SUM(fecha_devolucion IS NOT NULL), 0) AS devoluciones
             FROM reservas WHERE id_empleado = ?",
            [$idEmpleado]
        );
        return array_map('intval', $fila);
    }

    public function reservasRecientes(?int $idEmpleado = null, int $limite = 8): array
    {
        $where = $idEmpleado !== null ? ' WHERE r.id_empleado = ?' : '';
        $params = $idEmpleado !== null ? [$idEmpleado, $limite] : [$limite];
        return $this->consultar(
            "SELECT r.id_reserva, r.codigo_reserva, r.fecha_inicio, r.fecha_fin, r.estado, r.estado_devolucion, r.fecha_devolucion,
                    CONCAT(v.placa, ' · ', v.marca, ' ', v.modelo) AS vehiculo, " . self::EMPLEADO . " AS empleado
             FROM reservas r
             JOIN vehiculos v ON v.id_vehiculo = r.id_vehiculo
             JOIN empleados e ON e.id_empleado = r.id_empleado" . $where . '
             ORDER BY r.fecha_registro DESC, r.id_reserva DESC LIMIT ?',
            $params
        );
    }

    public function devolucionesEmpleado(int $idEmpleado, int $limite = 8): array
    {
        return $this->consultar(
            "SELECT r.id_reserva, r.codigo_reserva, r.fecha_fin, r.fecha_devolucion, r.estado_devolucion,
                    CONCAT(v.placa, ' · ', v.marca, ' ', v.modelo) AS vehiculo
             FROM reservas r JOIN vehiculos v ON v.id_vehiculo = r.id_vehiculo
             WHERE r.id_empleado = ? AND r.fecha_devolucion IS NOT NULL
             ORDER BY r.fecha_devolucion DESC LIMIT ?",
            [$idEmpleado, $limite]
        );
    }

    /** Filtros: id_empleado, id_vehiculo, estado, desde, hasta (el periodo se cruza con el de la reserva). */
    public function reservas(array $f): array
    {
        [$where, $params] = $this->filtros($f, 'r');
        return $this->consultar(
            "SELECT r.codigo_reserva, r.fecha_inicio, r.fecha_fin, r.fecha_devolucion, r.estado, r.estado_devolucion, r.motivo, r.observaciones,
                    CONCAT(v.placa, ' · ', v.marca, ' ', v.modelo) AS vehiculo, " . self::EMPLEADO . " AS empleado, e.puesto
             FROM reservas r
             JOIN vehiculos v ON v.id_vehiculo = r.id_vehiculo
             JOIN empleados e ON e.id_empleado = r.id_empleado" . $where . '
             ORDER BY r.fecha_inicio DESC, r.id_reserva DESC',
            $params
        );
    }

    /** Uso real: reservas iniciadas; el periodo llega hasta la devolución, o hasta ahora si sigue en uso. */
    public function historialUso(array $f): array
    {
        [$where, $params] = $this->filtros($f, 'r');
        $where .= ($where ? ' AND ' : ' WHERE ') . "r.estado IN ('En uso', 'Finalizada')";
        return $this->consultar(
            "SELECT r.codigo_reserva, r.fecha_inicio, r.fecha_fin, r.fecha_devolucion, r.estado, r.estado_devolucion, r.observaciones,
                    CONCAT(v.placa, ' · ', v.marca, ' ', v.modelo) AS vehiculo, " . self::EMPLEADO . " AS empleado,
                    TIMESTAMPDIFF(MINUTE, r.fecha_inicio, COALESCE(r.fecha_devolucion, NOW())) AS minutos_uso
             FROM reservas r
             JOIN vehiculos v ON v.id_vehiculo = r.id_vehiculo
             JOIN empleados e ON e.id_empleado = r.id_empleado" . $where . '
             ORDER BY v.placa, r.fecha_inicio DESC',
            $params
        );
    }

    public function vehiculos(array $estados): array
    {
        $marcas = implode(', ', array_fill(0, count($estados), '?'));
        return $this->consultar(
            "SELECT placa, marca, modelo, anio, tipo_vehiculo, color, capacidad_carga, kilometraje, estado, observaciones
             FROM vehiculos WHERE estado IN ($marcas) ORDER BY estado, marca, modelo",
            $estados
        );
    }

    public function conteoFlota(): array
    {
        $filas = $this->consultar('SELECT estado, COUNT(*) AS total FROM vehiculos GROUP BY estado');
        return array_column($filas, 'total', 'estado');
    }

    public function mantenimientos(array $f): array
    {
        [$where, $params] = $this->filtros($f, 'm');
        return $this->consultar(
            "SELECT m.tipo_mantenimiento, m.descripcion, m.fecha_inicio, m.fecha_fin, m.costo, m.estado, m.observaciones,
                    CONCAT(v.placa, ' · ', v.marca, ' ', v.modelo) AS vehiculo, u.nombre_usuario AS responsable
             FROM mantenimientos m
             JOIN vehiculos v ON v.id_vehiculo = m.id_vehiculo
             JOIN usuarios u ON u.id_usuario = m.id_usuario" . $where . '
             ORDER BY m.fecha_inicio DESC',
            $params
        );
    }

    private function filtros(array $f, string $t): array
    {
        $c = [];
        $p = [];
        if (!empty($f['id_empleado']) && $t === 'r') {
            $c[] = 'r.id_empleado = ?';
            $p[] = (int) $f['id_empleado'];
        }
        if (!empty($f['id_vehiculo'])) {
            $c[] = "$t.id_vehiculo = ?";
            $p[] = (int) $f['id_vehiculo'];
        }
        if (!empty($f['estado'])) {
            $c[] = "$t.estado = ?";
            $p[] = $f['estado'];
        }
        if (!empty($f['hasta'])) {
            $c[] = "$t.fecha_inicio <= ?";
            $p[] = $f['hasta'] . ' 23:59:59';
        }
        if (!empty($f['desde'])) {
            // Un mantenimiento sin fecha final sigue abierto y entra en cualquier periodo posterior.
            $c[] = $t === 'm' ? '(m.fecha_fin IS NULL OR m.fecha_fin >= ?)' : 'r.fecha_fin >= ?';
            $p[] = $f['desde'] . ' 00:00:00';
        }
        return [$c ? ' WHERE ' . implode(' AND ', $c) : '', $p];
    }
}
