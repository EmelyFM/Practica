<?php
defined('APP') || exit;

class ReservaModel extends BaseModel
{
    private const SELECT = "SELECT r.id_reserva, r.codigo_reserva, r.id_vehiculo, r.id_empleado, r.id_usuario,
                                   r.fecha_inicio, r.fecha_fin, r.motivo, r.estado, r.fecha_devolucion,
                                   r.estado_devolucion, r.observaciones, r.fecha_registro,
                                   CONCAT(v.placa, ' · ', v.marca, ' ', v.modelo) AS vehiculo,
                                   v.placa, v.estado AS estado_vehiculo,
                                   TRIM(CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, ''))) AS empleado,
                                   u.nombre_usuario AS registrado_por
                            FROM reservas r
                            JOIN vehiculos v ON v.id_vehiculo = r.id_vehiculo
                            JOIN empleados e ON e.id_empleado = r.id_empleado
                            JOIN usuarios u ON u.id_usuario = r.id_usuario";

    private VehiculoModel $vehiculos;
    private BitacoraModel $bitacora;

    public function __construct()
    {
        $this->vehiculos = new VehiculoModel();
        $this->bitacora = new BitacoraModel();
    }

    public function listar(?int $idEmpleado = null, bool $soloDevoluciones = false): array
    {
        $condiciones = [];
        $params = [];
        if ($idEmpleado !== null) {
            $condiciones[] = 'r.id_empleado = ?';
            $params[] = $idEmpleado;
        }
        if ($soloDevoluciones) {
            $condiciones[] = "r.estado IN ('En uso', 'Finalizada')";
        }
        $where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        return $this->consultar(self::SELECT . $where . ' ORDER BY r.fecha_inicio DESC, r.id_reserva DESC', $params);
    }

    public function obtener(int $id): ?array
    {
        return $this->fila(self::SELECT . ' WHERE r.id_reserva = ?', [$id]);
    }

    /**
     * Registro transaccional. El bloqueo FOR UPDATE de la fila del vehículo serializa
     * las reservas concurrentes del mismo vehículo: la segunda espera al COMMIT de la
     * primera y, al validar, ya ve su reserva y recibe el conflicto.
     */
    public function crear(array $d, int $idSesion): array
    {
        return $this->transaccion(function () use ($d, $idSesion) {
            $vehiculo = $this->vehiculos->bloquear($d['id_vehiculo']);
            $this->exigirVehiculoLibre($vehiculo, $d['fecha_inicio'], $d['fecha_fin']);
            $this->exigirEmpleado($d['id_empleado']);

            $codigo = $this->insertarConCodigoUnico($d, $idSesion);
            $id = $this->ultimoId();
            $nueva = $this->obtener($id);
            $this->bitacora->registrar($idSesion, 'Creación de reserva', 'Reservas', "Se creó la reserva $codigo para {$nueva['empleado']}.", null, $this->resumen($nueva));
            return ['id_reserva' => $id, 'codigo_reserva' => $codigo];
        });
    }

    private function insertarConCodigoUnico(array $d, int $idSesion): string
    {
        $fecha = date('Ymd', strtotime($this->ahora()));
        for ($intento = 0; ; $intento++) {
            $codigo = 'RES-' . $fecha . '-' . strtoupper(bin2hex(random_bytes(3)));
            try {
                $this->ejecutar(
                    'INSERT INTO reservas (codigo_reserva, id_vehiculo, id_empleado, id_usuario, fecha_inicio, fecha_fin, motivo, estado, observaciones)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$codigo, $d['id_vehiculo'], $d['id_empleado'], $idSesion, $d['fecha_inicio'], $d['fecha_fin'], $d['motivo'], $d['estado'], $d['observaciones']]
                );
                return $codigo;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062 || $intento >= 4) {
                    throw $e;
                }
            }
        }
    }

    public function actualizar(int $id, array $d, array $estadosEditables, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $d, $estadosEditables, $idSesion) {
            $actual = $this->bloquearReserva($id);
            if (!in_array($actual['estado'], $estadosEditables, true)) {
                throw new AppException("Una reserva en estado {$actual['estado']} ya no puede editarse.", 409);
            }
            // Orden fijo de bloqueo para evitar interbloqueos al cambiar de vehículo.
            $ids = array_unique([$actual['id_vehiculo'], $d['id_vehiculo']]);
            sort($ids);
            $bloqueados = [];
            foreach ($ids as $idV) {
                $bloqueados[$idV] = $this->vehiculos->bloquear($idV);
            }
            $this->exigirVehiculoLibre($bloqueados[$d['id_vehiculo']] ?? null, $d['fecha_inicio'], $d['fecha_fin'], $id);
            $this->exigirEmpleado($d['id_empleado']);

            $this->ejecutar(
                'UPDATE reservas SET id_vehiculo = ?, id_empleado = ?, fecha_inicio = ?, fecha_fin = ?, motivo = ?, observaciones = ? WHERE id_reserva = ?',
                [$d['id_vehiculo'], $d['id_empleado'], $d['fecha_inicio'], $d['fecha_fin'], $d['motivo'], $d['observaciones'], $id]
            );
            $this->bitacora->registrar($idSesion, 'Edición de reserva', 'Reservas', "Se editó la reserva {$actual['codigo_reserva']}.", $this->resumen($actual), $this->resumen($this->obtener($id)));
        });
    }

    public function confirmar(int $id, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $idSesion) {
            $r = $this->bloquearReserva($id);
            $this->exigirEstado($r, ['Pendiente'], 'confirmar');
            $vehiculo = $this->vehiculos->bloquear((int) $r['id_vehiculo']);
            if ($r['fecha_fin'] <= $this->ahora()) {
                throw new AppException('El periodo de esta reserva ya terminó; no puede confirmarse.', 409);
            }
            $this->exigirVehiculoLibre($vehiculo, $r['fecha_inicio'], $r['fecha_fin'], $id);
            $this->cambiarEstado($id, 'Confirmada');
            $this->bitacora->registrar($idSesion, 'Confirmación de reserva', 'Reservas', "Se confirmó la reserva {$r['codigo_reserva']}.", ['estado' => $r['estado']], ['estado' => 'Confirmada']);
        });
    }

    public function cancelar(int $id, ?string $motivo, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $motivo, $idSesion) {
            $r = $this->bloquearReserva($id);
            $this->exigirEstado($r, ['Pendiente', 'Confirmada'], 'cancelar');
            $observaciones = $this->anexar($r['observaciones'], 'Cancelación', $motivo);
            $this->ejecutar("UPDATE reservas SET estado = 'Cancelada', observaciones = ? WHERE id_reserva = ?", [$observaciones, $id]);
            $this->bitacora->registrar($idSesion, 'Cancelación de reserva', 'Reservas', "Se canceló la reserva {$r['codigo_reserva']}.", ['estado' => $r['estado']], ['estado' => 'Cancelada', 'motivo' => $motivo]);
        });
    }

    /** Entrega del vehículo: solo dentro del periodo reservado y con el vehículo físicamente libre. */
    public function iniciar(int $id, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $idSesion) {
            $r = $this->bloquearReserva($id);
            $this->exigirEstado($r, ['Confirmada'], 'iniciar');
            $vehiculo = $this->vehiculos->bloquear((int) $r['id_vehiculo']);
            $ahora = $this->ahora();
            if ($ahora < $r['fecha_inicio']) {
                throw new AppException('La reserva todavía no comienza; podrá iniciarse a partir de su fecha de inicio.', 409);
            }
            if ($ahora >= $r['fecha_fin']) {
                throw new AppException('El periodo de la reserva ya terminó; no puede iniciarse.', 409);
            }
            if ($vehiculo['estado'] !== 'Disponible') {
                throw new AppException("El vehículo no está operativo: estado {$vehiculo['estado']}.", 409);
            }
            $enUso = $this->fila(
                "SELECT codigo_reserva FROM reservas WHERE id_vehiculo = ? AND estado = 'En uso' AND id_reserva <> ? LIMIT 1",
                [$r['id_vehiculo'], $id]
            );
            if ($enUso) {
                throw new AppException("El vehículo aún no se devuelve de la reserva {$enUso['codigo_reserva']}.", 409);
            }
            $this->cambiarEstado($id, 'En uso');
            $this->bitacora->registrar($idSesion, 'Inicio de uso de vehículo', 'Reservas', "Inició el uso del vehículo de la reserva {$r['codigo_reserva']}.", ['estado' => $r['estado']], ['estado' => 'En uso']);
        });
    }

    /**
     * Devolución: conserva fecha_fin (periodo reservado) y guarda en fecha_devolucion el
     * momento real, de modo que una devolución anticipada no pierde ningún dato. Al pasar a
     * Finalizada el resto del periodo deja de bloquear al vehículo.
     */
    public function devolver(int $id, array $d, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $d, $idSesion) {
            $r = $this->bloquearReserva($id);
            $this->exigirEstado($r, ['En uso'], 'devolver');
            $fecha = $d['fecha_devolucion'] ?? $this->ahora();
            if ($fecha < $r['fecha_inicio']) {
                throw new AppException('Revisa los campos marcados.', 422, ['fecha_devolucion' => 'La devolución no puede ser anterior al inicio de la reserva.']);
            }
            if ($fecha > $this->ahora()) {
                throw new AppException('Revisa los campos marcados.', 422, ['fecha_devolucion' => 'La devolución no puede registrarse con una fecha futura.']);
            }
            $observaciones = $this->anexar($r['observaciones'], 'Devolución', $d['observaciones']);
            $this->ejecutar(
                "UPDATE reservas SET estado = 'Finalizada', fecha_devolucion = ?, estado_devolucion = ?, observaciones = ? WHERE id_reserva = ?",
                [$fecha, $d['estado_devolucion'], $observaciones, $id]
            );
            $tipo = $fecha < $r['fecha_fin'] ? 'anticipada' : ($fecha > $r['fecha_fin'] ? 'con retraso' : 'puntual');
            $this->bitacora->registrar(
                $idSesion, 'Devolución de vehículo', 'Reservas', "Devolución $tipo de la reserva {$r['codigo_reserva']}.",
                ['estado' => $r['estado'], 'fecha_fin' => $r['fecha_fin']],
                ['estado' => 'Finalizada', 'fecha_devolucion' => $fecha, 'estado_devolucion' => $d['estado_devolucion']]
            );
        });
    }

    private function bloquearReserva(int $id): array
    {
        $r = $this->fila('SELECT * FROM reservas WHERE id_reserva = ? FOR UPDATE', [$id]);
        if (!$r) {
            throw new AppException('La reserva no existe.', 404);
        }
        return $r;
    }

    private function exigirEstado(array $r, array $origen, string $accion): void
    {
        if (!in_array($r['estado'], $origen, true)) {
            throw new AppException("No se puede $accion una reserva en estado {$r['estado']}.", 409);
        }
    }

    /** Debe llamarse con la fila del vehículo ya bloqueada. */
    private function exigirVehiculoLibre(?array $vehiculo, string $inicio, string $fin, int $excluir = 0): void
    {
        if (!$vehiculo) {
            throw new AppException('El vehículo seleccionado no existe.', 404);
        }
        if ($vehiculo['estado'] !== 'Disponible') {
            throw new AppException("El vehículo {$vehiculo['placa']} no está operativo: estado {$vehiculo['estado']}.", 409);
        }
        $idV = (int) $vehiculo['id_vehiculo'];
        if ($this->vehiculos->reservaSolapada($idV, $inicio, $fin, $excluir)) {
            throw new AppException("El vehículo {$vehiculo['placa']} ya está reservado en ese periodo.", 409);
        }
        if ($this->vehiculos->mantenimientoSolapado($idV, $inicio, $fin)) {
            throw new AppException("El vehículo {$vehiculo['placa']} tiene un mantenimiento en ese periodo.", 409);
        }
    }

    /** El bloqueo compartido impide que el empleado se desactive mientras se registra la reserva. */
    private function exigirEmpleado(int $idEmpleado): void
    {
        $e = $this->fila(
            "SELECT e.id_empleado FROM empleados e JOIN usuarios u ON u.id_usuario = e.id_usuario
             WHERE e.id_empleado = ? AND e.estado = 'Activo' AND u.estado = 'Activo' LOCK IN SHARE MODE",
            [$idEmpleado]
        );
        if (!$e) {
            throw new AppException('Revisa los campos marcados.', 422, ['id_empleado' => 'El empleado no existe o no está activo.']);
        }
    }

    private function cambiarEstado(int $id, string $estado): void
    {
        $this->ejecutar('UPDATE reservas SET estado = ? WHERE id_reserva = ?', [$estado, $id]);
    }

    private function anexar(?string $actuales, string $etiqueta, ?string $texto): ?string
    {
        if ($texto === null) {
            return $actuales;
        }
        return trim(($actuales ?? '') . "\n[$etiqueta] $texto");
    }

    private function resumen(array $r): array
    {
        return array_intersect_key($r, array_flip(['codigo_reserva', 'id_vehiculo', 'id_empleado', 'fecha_inicio', 'fecha_fin', 'motivo', 'estado', 'observaciones']));
    }
}
