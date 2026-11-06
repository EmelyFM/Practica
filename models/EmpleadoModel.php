<?php
defined('APP') || exit;

class EmpleadoModel extends BaseModel
{
    private const SELECT = "SELECT e.id_empleado, e.id_usuario, e.nombre, e.apellido_paterno, e.apellido_materno,
                                   e.telefono, e.puesto, e.fecha_ingreso, e.estado,
                                   TRIM(CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, ''))) AS nombre_completo,
                                   u.nombre_usuario, u.correo, u.estado AS estado_usuario
                            FROM empleados e JOIN usuarios u ON u.id_usuario = e.id_usuario";

    public function listar(bool $soloAsignables = false): array
    {
        $filtro = $soloAsignables ? " WHERE e.estado = 'Activo' AND u.estado = 'Activo'" : '';
        return $this->consultar(self::SELECT . $filtro . ' ORDER BY e.nombre, e.apellido_paterno');
    }

    public function obtener(int $id): ?array
    {
        return $this->fila(self::SELECT . ' WHERE e.id_empleado = ?', [$id]);
    }

    /** Usuarios activos sin ficha de empleado; al editar incluye al usuario ya vinculado. */
    public function usuariosDisponibles(int $idEmpleado = 0): array
    {
        return $this->consultar(
            "SELECT u.id_usuario, u.nombre_usuario, u.correo
             FROM usuarios u
             LEFT JOIN empleados e ON e.id_usuario = u.id_usuario
             WHERE (u.estado = 'Activo' AND e.id_empleado IS NULL) OR e.id_empleado = ?
             ORDER BY u.nombre_usuario",
            [$idEmpleado]
        );
    }

    public function usuarioValido(int $idUsuario): bool
    {
        return (bool) $this->valor("SELECT COUNT(*) FROM usuarios WHERE id_usuario = ? AND estado = 'Activo'", [$idUsuario]);
    }

    public function usuarioOcupado(int $idUsuario, int $excluir = 0): bool
    {
        return (bool) $this->valor('SELECT COUNT(*) FROM empleados WHERE id_usuario = ? AND id_empleado <> ?', [$idUsuario, $excluir]);
    }

    public function crear(array $d, int $idSesion): int
    {
        return $this->transaccion(function () use ($d, $idSesion) {
            $this->ejecutar(
                'INSERT INTO empleados (id_usuario, nombre, apellido_paterno, apellido_materno, telefono, puesto, fecha_ingreso, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$d['id_usuario'], $d['nombre'], $d['apellido_paterno'], $d['apellido_materno'], $d['telefono'], $d['puesto'], $d['fecha_ingreso'], $d['estado']]
            );
            $id = $this->ultimoId();
            $nuevo = $this->obtener($id);
            (new BitacoraModel())->registrar($idSesion, 'Registro de empleado', 'Empleados', "Se registró al empleado {$nuevo['nombre_completo']}.", null, $nuevo);
            return $id;
        });
    }

    public function actualizar(int $id, array $d, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $d, $idSesion) {
            $this->consultar('SELECT id_empleado FROM empleados WHERE id_empleado = ? FOR UPDATE', [$id]);
            $antes = $this->obtener($id);
            $this->ejecutar(
                'UPDATE empleados SET id_usuario = ?, nombre = ?, apellido_paterno = ?, apellido_materno = ?, telefono = ?, puesto = ?, fecha_ingreso = ?
                 WHERE id_empleado = ?',
                [$d['id_usuario'], $d['nombre'], $d['apellido_paterno'], $d['apellido_materno'], $d['telefono'], $d['puesto'], $d['fecha_ingreso'], $id]
            );
            $nuevo = $this->obtener($id);
            (new BitacoraModel())->registrar($idSesion, 'Edición de empleado', 'Empleados', "Se editó al empleado {$nuevo['nombre_completo']}.", $antes, $nuevo);
        });
    }

    public function cambiarEstado(int $id, string $estado, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $estado, $idSesion) {
            // El bloqueo espera a las reservas en curso de este empleado (ver ReservaModel::exigirEmpleado).
            $this->consultar('SELECT id_empleado FROM empleados WHERE id_empleado = ? FOR UPDATE', [$id]);
            if ($estado === 'Inactivo') {
                $abiertas = (int) $this->valor(
                    "SELECT COUNT(*) FROM reservas WHERE id_empleado = ? AND estado IN ('Pendiente', 'Confirmada', 'En uso')",
                    [$id]
                );
                if ($abiertas > 0) {
                    throw new AppException("El empleado tiene $abiertas reserva(s) abierta(s). Cancélalas o registra su devolución antes de desactivarlo.", 409);
                }
            }
            $antes = $this->obtener($id);
            $this->ejecutar('UPDATE empleados SET estado = ? WHERE id_empleado = ?', [$estado, $id]);
            (new BitacoraModel())->registrar($idSesion, 'Cambio de estado de empleado', 'Empleados', "El empleado {$antes['nombre_completo']} pasó a $estado.", ['estado' => $antes['estado']], ['estado' => $estado]);
        });
    }
}
