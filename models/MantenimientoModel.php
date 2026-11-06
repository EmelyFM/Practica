<?php
defined('APP') || exit;

class MantenimientoModel extends BaseModel
{
    /** Días de anticipación de los recordatorios. */
    public const DIAS_AVISO = 7;

    private const SELECT = "SELECT m.id_mantenimiento, m.id_vehiculo, m.id_usuario, m.tipo_mantenimiento, m.descripcion,
                                   m.fecha_inicio, m.fecha_fin, m.costo, m.estado, m.observaciones, m.fecha_registro,
                                   CONCAT(v.placa, ' · ', v.marca, ' ', v.modelo) AS vehiculo, v.placa,
                                   u.nombre_usuario AS responsable,
                                   CASE WHEN m.estado <> 'Programado' THEN NULL
                                        WHEN m.fecha_inicio < NOW() THEN 'Atrasado'
                                        WHEN m.fecha_inicio <= DATE_ADD(NOW(), INTERVAL " . self::DIAS_AVISO . " DAY) THEN 'Próximo'
                                        ELSE NULL END AS alerta
                            FROM mantenimientos m
                            JOIN vehiculos v ON v.id_vehiculo = m.id_vehiculo
                            JOIN usuarios u ON u.id_usuario = m.id_usuario";

    private VehiculoModel $vehiculos;
    private BitacoraModel $bitacora;

    public function __construct()
    {
        $this->vehiculos = new VehiculoModel();
        $this->bitacora = new BitacoraModel();
    }

    public function listar(): array
    {
        return $this->consultar(self::SELECT . ' ORDER BY m.fecha_inicio DESC');
    }

    public function obtener(int $id): ?array
    {
        return $this->fila(self::SELECT . ' WHERE m.id_mantenimiento = ?', [$id]);
    }

    public function proximos(): array
    {
        return $this->consultar(
            self::SELECT . " WHERE m.estado = 'Programado' AND m.fecha_inicio <= DATE_ADD(NOW(), INTERVAL ? DAY) ORDER BY m.fecha_inicio",
            [self::DIAS_AVISO]
        );
    }

    /** Usuarios que pueden figurar como responsables. */
    public function responsables(): array
    {
        return $this->consultar("SELECT id_usuario, nombre_usuario FROM usuarios WHERE estado = 'Activo' ORDER BY nombre_usuario");
    }

    public function responsableValido(int $id): bool
    {
        return (bool) $this->valor("SELECT COUNT(*) FROM usuarios WHERE id_usuario = ? AND estado = 'Activo'", [$id]);
    }

    public function crear(array $d, int $idSesion): int
    {
        return $this->transaccion(function () use ($d, $idSesion) {
            $vehiculo = $this->vehiculos->bloquear($d['id_vehiculo']);
            if (!$vehiculo) {
                throw new AppException('El vehículo seleccionado no existe.', 404);
            }
            $this->exigirSinConflictos($vehiculo, $d['fecha_inicio'], $d['fecha_fin']);
            $this->ejecutar(
                'INSERT INTO mantenimientos (id_vehiculo, id_usuario, tipo_mantenimiento, descripcion, fecha_inicio, fecha_fin, costo, estado, observaciones)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$d['id_vehiculo'], $d['id_usuario'], $d['tipo_mantenimiento'], $d['descripcion'], $d['fecha_inicio'], $d['fecha_fin'], $d['costo'], $d['estado'], $d['observaciones']]
            );
            $id = $this->ultimoId();
            if ($d['estado'] === 'En proceso') {
                $this->poneEnMantenimiento($vehiculo);
            }
            $this->bitacora->registrar($idSesion, 'Registro de mantenimiento', 'Mantenimientos', "Mantenimiento {$d['tipo_mantenimiento']} registrado para el vehículo {$vehiculo['placa']}.", null, $this->resumen($this->obtener($id)));
            return $id;
        });
    }

    public function actualizar(int $id, array $d, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $d, $idSesion) {
            $actual = $this->bloquearMantenimiento($id);
            if (!in_array($actual['estado'], ['Programado', 'En proceso'], true)) {
                throw new AppException("Un mantenimiento en estado {$actual['estado']} ya no puede editarse.", 409);
            }
            if ($actual['estado'] === 'En proceso' && (int) $actual['id_vehiculo'] !== $d['id_vehiculo']) {
                throw new AppException('No se puede cambiar el vehículo de un mantenimiento en proceso.', 409);
            }
            $ids = array_unique([(int) $actual['id_vehiculo'], $d['id_vehiculo']]);
            sort($ids);
            $bloqueados = [];
            foreach ($ids as $idV) {
                $bloqueados[$idV] = $this->vehiculos->bloquear($idV);
            }
            if (!$bloqueados[$d['id_vehiculo']]) {
                throw new AppException('El vehículo seleccionado no existe.', 404);
            }
            $this->exigirSinConflictos($bloqueados[$d['id_vehiculo']], $d['fecha_inicio'], $d['fecha_fin'], $id);
            $this->ejecutar(
                'UPDATE mantenimientos SET id_vehiculo = ?, id_usuario = ?, tipo_mantenimiento = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, costo = ?, observaciones = ?
                 WHERE id_mantenimiento = ?',
                [$d['id_vehiculo'], $d['id_usuario'], $d['tipo_mantenimiento'], $d['descripcion'], $d['fecha_inicio'], $d['fecha_fin'], $d['costo'], $d['observaciones'], $id]
            );
            $this->bitacora->registrar($idSesion, 'Actualización de mantenimiento', 'Mantenimientos', "Se actualizó el mantenimiento #$id del vehículo {$bloqueados[$d['id_vehiculo']]['placa']}.", $this->resumen($actual), $this->resumen($this->obtener($id)));
        });
    }

    /**
     * Transiciones válidas: Programado → En proceso | Cancelado; En proceso → Finalizado | Cancelado.
     * El estado físico del vehículo acompaña al mantenimiento en proceso.
     */
    public function cambiarEstado(int $id, array $d, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $d, $idSesion) {
            $m = $this->bloquearMantenimiento($id);
            $validas = ['Programado' => ['En proceso', 'Cancelado'], 'En proceso' => ['Finalizado', 'Cancelado']];
            $destino = $d['estado'];
            if (!in_array($destino, $validas[$m['estado']] ?? [], true)) {
                throw new AppException("No se puede pasar de {$m['estado']} a $destino.", 409);
            }
            $vehiculo = $this->vehiculos->bloquear((int) $m['id_vehiculo']);
            $ahora = $this->ahora();
            $inicio = $m['fecha_inicio'];
            $fin = $m['fecha_fin'];

            if ($destino === 'En proceso') {
                if ($fin !== null && $fin <= $ahora) {
                    throw new AppException('La fecha final programada ya pasó. Edita el mantenimiento antes de iniciarlo.', 409);
                }
                // Si se adelanta, el periodo real empieza ahora y debe seguir libre de reservas.
                $inicio = min($inicio, $ahora);
                $this->exigirSinConflictos($vehiculo, $inicio, $fin, $id);
                $this->poneEnMantenimiento($vehiculo);
            }
            if ($destino === 'Finalizado') {
                $fin = $d['fecha_fin'] ?? $ahora;
                if ($fin <= $inicio) {
                    throw new AppException('Revisa los campos marcados.', 422, ['fecha_fin' => 'La fecha final debe ser posterior al inicio del mantenimiento.']);
                }
                if ($fin > $ahora) {
                    throw new AppException('Revisa los campos marcados.', 422, ['fecha_fin' => 'Un mantenimiento finalizado no puede terminar en el futuro.']);
                }
            }
            $costo = $d['costo'] ?? $m['costo'];
            $observaciones = $d['observaciones'] ?? $m['observaciones'];
            $this->ejecutar(
                'UPDATE mantenimientos SET estado = ?, fecha_inicio = ?, fecha_fin = ?, costo = ?, observaciones = ? WHERE id_mantenimiento = ?',
                [$destino, $inicio, $fin, $costo, $observaciones, $id]
            );
            if ($m['estado'] === 'En proceso') {
                $this->liberarVehiculo($vehiculo);
            }
            $this->bitacora->registrar($idSesion, 'Actualización de mantenimiento', 'Mantenimientos', "El mantenimiento #$id del vehículo {$vehiculo['placa']} pasó de {$m['estado']} a $destino.", ['estado' => $m['estado']], ['estado' => $destino, 'fecha_fin' => $fin, 'costo' => $costo]);
        });
    }

    private function exigirSinConflictos(array $vehiculo, string $inicio, ?string $fin, int $excluir = 0): void
    {
        $idV = (int) $vehiculo['id_vehiculo'];
        $reserva = $this->vehiculos->reservaSolapada($idV, $inicio, $fin);
        if ($reserva) {
            throw new AppException("El periodo coincide con la reserva {$reserva['codigo_reserva']} ({$reserva['estado']}).", 409);
        }
        if ($this->vehiculos->mantenimientoSolapado($idV, $inicio, $fin, $excluir)) {
            throw new AppException("El vehículo {$vehiculo['placa']} ya tiene otro mantenimiento en ese periodo.", 409);
        }
    }

    /** Un vehículo «Fuera de servicio» conserva su estado: el retiro es una decisión aparte. */
    private function poneEnMantenimiento(array $vehiculo): void
    {
        if ($vehiculo['estado'] === 'Disponible') {
            $this->ejecutar("UPDATE vehiculos SET estado = 'Mantenimiento' WHERE id_vehiculo = ?", [$vehiculo['id_vehiculo']]);
        }
    }

    private function liberarVehiculo(array $vehiculo): void
    {
        $abiertos = (int) $this->valor("SELECT COUNT(*) FROM mantenimientos WHERE id_vehiculo = ? AND estado = 'En proceso'", [$vehiculo['id_vehiculo']]);
        if ($abiertos === 0 && $vehiculo['estado'] === 'Mantenimiento') {
            $this->ejecutar("UPDATE vehiculos SET estado = 'Disponible' WHERE id_vehiculo = ?", [$vehiculo['id_vehiculo']]);
        }
    }

    private function bloquearMantenimiento(int $id): array
    {
        $m = $this->fila('SELECT * FROM mantenimientos WHERE id_mantenimiento = ? FOR UPDATE', [$id]);
        if (!$m) {
            throw new AppException('El mantenimiento no existe.', 404);
        }
        return $m;
    }

    private function resumen(array $m): array
    {
        return array_intersect_key($m, array_flip(['id_vehiculo', 'id_usuario', 'tipo_mantenimiento', 'descripcion', 'fecha_inicio', 'fecha_fin', 'costo', 'estado', 'observaciones']));
    }
}
