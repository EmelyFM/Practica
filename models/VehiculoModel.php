<?php
defined('APP') || exit;

class VehiculoModel extends BaseModel
{
    private const ABIERTAS = "('Pendiente', 'Confirmada', 'En uso')";
    private const MANT_ACTIVOS = "('Programado', 'En proceso')";

    /**
     * Fin efectivo de una reserva bloqueante. Una reserva «En uso» vencida sigue ocupando
     * el vehículo: no ha vuelto y se ignora cuándo volverá, así que bloquea hasta su devolución.
     */
    private const FIN_RESERVA = "IF(r.estado = 'En uso' AND r.fecha_fin <= NOW(), '9999-12-31 23:59:59', r.fecha_fin)";
    /** Un mantenimiento sin fecha final permanece abierto indefinidamente. */
    private const FIN_MANT = "COALESCE(m.fecha_fin, '9999-12-31 23:59:59')";

    public function listar(): array
    {
        return $this->consultar('SELECT * FROM vehiculos ORDER BY marca, modelo, placa');
    }

    public function obtener(int $id): ?array
    {
        return $this->fila('SELECT * FROM vehiculos WHERE id_vehiculo = ?', [$id]);
    }

    public function existePlaca(string $placa, int $excluir = 0): bool
    {
        return (bool) $this->valor('SELECT COUNT(*) FROM vehiculos WHERE placa = ? AND id_vehiculo <> ?', [$placa, $excluir]);
    }

    /**
     * Bloquea la fila del vehículo hasta el fin de la transacción. Debe ser la primera
     * lectura de la transacción: en REPEATABLE READ la instantánea de los SELECT
     * posteriores se toma después de obtener el bloqueo y ve lo confirmado por otros.
     */
    public function bloquear(int $id): ?array
    {
        return $this->fila('SELECT * FROM vehiculos WHERE id_vehiculo = ? FOR UPDATE', [$id]);
    }

    /** Solapamiento: nueva_inicio < fin_existente AND nueva_fin > inicio_existente. */
    public function reservaSolapada(int $idVehiculo, string $inicio, ?string $fin, int $excluir = 0): ?array
    {
        return $this->fila(
            'SELECT r.id_reserva, r.codigo_reserva, r.fecha_inicio, r.fecha_fin, r.estado
             FROM reservas r
             WHERE r.id_vehiculo = ? AND r.id_reserva <> ? AND r.estado IN ' . self::ABIERTAS . '
               AND ? < ' . self::FIN_RESERVA . ' AND ? > r.fecha_inicio
             ORDER BY r.fecha_inicio LIMIT 1',
            [$idVehiculo, $excluir, $inicio, $fin ?? '9999-12-31 23:59:59']
        );
    }

    public function mantenimientoSolapado(int $idVehiculo, string $inicio, ?string $fin, int $excluir = 0): ?array
    {
        return $this->fila(
            'SELECT m.id_mantenimiento, m.tipo_mantenimiento, m.fecha_inicio, m.fecha_fin, m.estado
             FROM mantenimientos m
             WHERE m.id_vehiculo = ? AND m.id_mantenimiento <> ? AND m.estado IN ' . self::MANT_ACTIVOS . '
               AND ? < ' . self::FIN_MANT . ' AND ? > m.fecha_inicio
             ORDER BY m.fecha_inicio LIMIT 1',
            [$idVehiculo, $excluir, $inicio, $fin ?? '9999-12-31 23:59:59']
        );
    }

    /** Toda la flota con su disponibilidad real para el periodo solicitado. */
    public function disponibilidad(string $inicio, string $fin, int $excluirReserva = 0): array
    {
        $filas = $this->consultar(
            'SELECT v.id_vehiculo, v.placa, v.marca, v.modelo, v.anio, v.tipo_vehiculo, v.color, v.capacidad_carga, v.kilometraje, v.estado,
                    (SELECT COUNT(*) FROM reservas r
                      WHERE r.id_vehiculo = v.id_vehiculo AND r.id_reserva <> ? AND r.estado IN ' . self::ABIERTAS . '
                        AND ? < ' . self::FIN_RESERVA . ' AND ? > r.fecha_inicio) AS reservas_solapadas,
                    (SELECT COUNT(*) FROM mantenimientos m
                      WHERE m.id_vehiculo = v.id_vehiculo AND m.estado IN ' . self::MANT_ACTIVOS . '
                        AND ? < ' . self::FIN_MANT . ' AND ? > m.fecha_inicio) AS mantenimientos_solapados
             FROM vehiculos v
             ORDER BY v.marca, v.modelo, v.placa',
            [$excluirReserva, $inicio, $fin, $inicio, $fin]
        );
        foreach ($filas as &$f) {
            $motivo = null;
            if ($f['estado'] !== 'Disponible') {
                $motivo = $f['estado'] === 'Mantenimiento' ? 'En mantenimiento' : 'Fuera de servicio';
            } elseif ($f['mantenimientos_solapados'] > 0) {
                $motivo = 'Mantenimiento programado en el periodo';
            } elseif ($f['reservas_solapadas'] > 0) {
                $motivo = 'Reservado en el periodo';
            }
            $f['disponible'] = $motivo === null;
            $f['motivo'] = $motivo;
            unset($f['reservas_solapadas'], $f['mantenimientos_solapados']);
        }
        return $filas;
    }

    public function historialReservas(int $id): array
    {
        return $this->consultar(
            "SELECT r.id_reserva, r.codigo_reserva, r.fecha_inicio, r.fecha_fin, r.fecha_devolucion, r.estado, r.estado_devolucion,
                    TRIM(CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, ''))) AS empleado
             FROM reservas r JOIN empleados e ON e.id_empleado = r.id_empleado
             WHERE r.id_vehiculo = ? ORDER BY r.fecha_inicio DESC",
            [$id]
        );
    }

    public function historialMantenimientos(int $id): array
    {
        return $this->consultar(
            'SELECT id_mantenimiento, tipo_mantenimiento, descripcion, fecha_inicio, fecha_fin, costo, estado
             FROM mantenimientos WHERE id_vehiculo = ? ORDER BY fecha_inicio DESC',
            [$id]
        );
    }

    public function crear(array $d, int $idSesion): int
    {
        return $this->transaccion(function () use ($d, $idSesion) {
            $this->ejecutar(
                'INSERT INTO vehiculos (placa, marca, modelo, anio, tipo_vehiculo, color, capacidad_carga, kilometraje, estado, observaciones)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$d['placa'], $d['marca'], $d['modelo'], $d['anio'], $d['tipo_vehiculo'], $d['color'], $d['capacidad_carga'], $d['kilometraje'], $d['estado'], $d['observaciones']]
            );
            $id = $this->ultimoId();
            (new BitacoraModel())->registrar($idSesion, 'Registro de vehículo', 'Vehículos', "Se registró el vehículo {$d['placa']}.", null, $this->obtener($id));
            return $id;
        });
    }

    /** El estado físico no se cambia aquí: tiene sus propias reglas en cambiarEstado(). */
    public function actualizar(int $id, array $d, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $d, $idSesion) {
            $antes = $this->bloquear($id);
            $this->ejecutar(
                'UPDATE vehiculos SET placa = ?, marca = ?, modelo = ?, anio = ?, tipo_vehiculo = ?, color = ?, capacidad_carga = ?, kilometraje = ?, observaciones = ?
                 WHERE id_vehiculo = ?',
                [$d['placa'], $d['marca'], $d['modelo'], $d['anio'], $d['tipo_vehiculo'], $d['color'], $d['capacidad_carga'], $d['kilometraje'], $d['observaciones'], $id]
            );
            (new BitacoraModel())->registrar($idSesion, 'Edición de vehículo', 'Vehículos', "Se editó el vehículo {$d['placa']}.", $antes, $this->obtener($id));
        });
    }

    public function cambiarEstado(int $id, string $estado, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $estado, $idSesion) {
            $antes = $this->bloquear($id);
            if (!$antes) {
                throw new AppException('El vehículo no existe.', 404);
            }
            if ($estado !== 'Disponible') {
                if ($this->valor("SELECT COUNT(*) FROM reservas WHERE id_vehiculo = ? AND estado = 'En uso'", [$id]) > 0) {
                    throw new AppException('El vehículo está en uso. Registra primero su devolución.', 409);
                }
            }
            if ($estado === 'Fuera de servicio') {
                $futuras = (int) $this->valor(
                    "SELECT COUNT(*) FROM reservas WHERE id_vehiculo = ? AND estado IN ('Pendiente', 'Confirmada') AND fecha_fin > NOW()",
                    [$id]
                );
                if ($futuras > 0) {
                    throw new AppException("El vehículo tiene $futuras reserva(s) vigente(s). Cancélalas o reasígnalas antes de retirarlo del servicio.", 409);
                }
            }
            if ($estado === 'Disponible') {
                if ($this->valor("SELECT COUNT(*) FROM mantenimientos WHERE id_vehiculo = ? AND estado = 'En proceso'", [$id]) > 0) {
                    throw new AppException('El vehículo tiene un mantenimiento en proceso. Finalízalo para volver a ponerlo disponible.', 409);
                }
            }
            $this->ejecutar('UPDATE vehiculos SET estado = ? WHERE id_vehiculo = ?', [$estado, $id]);
            (new BitacoraModel())->registrar($idSesion, 'Cambio de estado de vehículo', 'Vehículos', "El vehículo {$antes['placa']} pasó de {$antes['estado']} a $estado.", ['estado' => $antes['estado']], ['estado' => $estado]);
        });
    }

    /** Eliminación física solo para vehículos sin historial; las claves foráneas borrarían en cascada. */
    public function eliminar(int $id, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $idSesion) {
            $antes = $this->bloquear($id);
            if (!$antes) {
                throw new AppException('El vehículo no existe.', 404);
            }
            $reservas = (int) $this->valor('SELECT COUNT(*) FROM reservas WHERE id_vehiculo = ?', [$id]);
            $mantenimientos = (int) $this->valor('SELECT COUNT(*) FROM mantenimientos WHERE id_vehiculo = ?', [$id]);
            if ($reservas + $mantenimientos > 0) {
                throw new AppException('El vehículo tiene historial de reservas o mantenimientos y no puede eliminarse. Puedes retirarlo del servicio.', 409);
            }
            $this->ejecutar('DELETE FROM vehiculos WHERE id_vehiculo = ?', [$id]);
            (new BitacoraModel())->registrar($idSesion, 'Eliminación de vehículo', 'Vehículos', "Se eliminó el vehículo {$antes['placa']}, que no tenía historial.", $antes);
        });
    }
}
