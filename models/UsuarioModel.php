<?php
defined('APP') || exit;

class UsuarioModel extends BaseModel
{
    /** Nunca incluye contrasena_hash: esta lista es lo único que sale hacia el controlador. */
    private const CAMPOS = 'u.id_usuario, u.nombre_usuario, u.correo, u.id_rol, u.fecha_registro, u.estado, r.nombre_rol';

    public function listar(): array
    {
        return $this->consultar(
            'SELECT ' . self::CAMPOS . ', e.id_empleado
             FROM usuarios u
             JOIN roles r ON r.id_rol = u.id_rol
             LEFT JOIN empleados e ON e.id_usuario = u.id_usuario
             ORDER BY u.nombre_usuario'
        );
    }

    public function obtener(int $id): ?array
    {
        return $this->fila(
            'SELECT ' . self::CAMPOS . ' FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?',
            [$id]
        );
    }

    /** Único método que devuelve el hash; lo usa exclusivamente el inicio de sesión. */
    public function buscarParaLogin(string $nombre): ?array
    {
        return $this->fila(
            'SELECT u.id_usuario, u.nombre_usuario, u.contrasena_hash, u.estado, r.estado AS estado_rol
             FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE u.nombre_usuario = ?',
            [$nombre]
        );
    }

    public function datosSesion(int $id): ?array
    {
        return $this->fila(
            "SELECT u.id_usuario, u.nombre_usuario, u.correo, u.estado, r.nombre_rol, r.estado AS estado_rol,
                    e.id_empleado, e.estado AS estado_empleado,
                    TRIM(CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, ''))) AS nombre_empleado
             FROM usuarios u
             JOIN roles r ON r.id_rol = u.id_rol
             LEFT JOIN empleados e ON e.id_usuario = u.id_usuario
             WHERE u.id_usuario = ?",
            [$id]
        );
    }

    public function total(): int
    {
        return (int) $this->valor('SELECT COUNT(*) FROM usuarios');
    }

    public function roles(): array
    {
        return $this->consultar("SELECT id_rol, nombre_rol, descripcion FROM roles WHERE estado = 'Activo' ORDER BY nombre_rol");
    }

    public function rolActivo(int $idRol): bool
    {
        return (bool) $this->valor("SELECT COUNT(*) FROM roles WHERE id_rol = ? AND estado = 'Activo'", [$idRol]);
    }

    public function existeNombre(string $nombre, int $excluir = 0): bool
    {
        return (bool) $this->valor('SELECT COUNT(*) FROM usuarios WHERE nombre_usuario = ? AND id_usuario <> ?', [$nombre, $excluir]);
    }

    public function existeCorreo(string $correo, int $excluir = 0): bool
    {
        return (bool) $this->valor('SELECT COUNT(*) FROM usuarios WHERE correo = ? AND id_usuario <> ?', [$correo, $excluir]);
    }

    public function crear(array $d, int $idSesion): int
    {
        return $this->transaccion(function () use ($d, $idSesion) {
            $this->ejecutar(
                'INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash, id_rol, estado) VALUES (?, ?, ?, ?, ?)',
                [$d['nombre_usuario'], $d['correo'], password_hash($d['contrasena'], PASSWORD_DEFAULT), $d['id_rol'], $d['estado']]
            );
            $id = $this->ultimoId();
            (new BitacoraModel())->registrar($idSesion, 'Registro de usuario', 'Usuarios', "Se registró el usuario {$d['nombre_usuario']}.", null, $this->obtener($id));
            return $id;
        });
    }

    public function actualizar(int $id, array $d, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $d, $idSesion) {
            $this->bloquear($id);
            $antes = $this->obtener($id);
            $rolNuevo = $this->valor('SELECT nombre_rol FROM roles WHERE id_rol = ?', [$d['id_rol']]);
            if ($antes['nombre_rol'] === ROL_ADMIN && $rolNuevo !== ROL_ADMIN && $antes['estado'] === 'Activo') {
                $this->exigirOtroAdministrador($id);
            }
            $this->ejecutar(
                'UPDATE usuarios SET nombre_usuario = ?, correo = ?, id_rol = ? WHERE id_usuario = ?',
                [$d['nombre_usuario'], $d['correo'], $d['id_rol'], $id]
            );
            (new BitacoraModel())->registrar($idSesion, 'Edición de usuario', 'Usuarios', "Se editó el usuario {$d['nombre_usuario']}.", $antes, $this->obtener($id));
        });
    }

    public function cambiarEstado(int $id, string $estado, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $estado, $idSesion) {
            $this->bloquear($id);
            $antes = $this->obtener($id);
            if ($estado === 'Inactivo' && $antes['nombre_rol'] === ROL_ADMIN && $antes['estado'] === 'Activo') {
                $this->exigirOtroAdministrador($id);
            }
            $this->ejecutar('UPDATE usuarios SET estado = ? WHERE id_usuario = ?', [$estado, $id]);
            (new BitacoraModel())->registrar($idSesion, 'Cambio de estado de usuario', 'Usuarios', "El usuario {$antes['nombre_usuario']} pasó a $estado.", ['estado' => $antes['estado']], ['estado' => $estado]);
        });
    }

    public function cambiarContrasena(int $id, string $contrasena, int $idSesion): void
    {
        $this->transaccion(function () use ($id, $contrasena, $idSesion) {
            $this->ejecutar('UPDATE usuarios SET contrasena_hash = ? WHERE id_usuario = ?', [password_hash($contrasena, PASSWORD_DEFAULT), $id]);
            $nombre = $this->valor('SELECT nombre_usuario FROM usuarios WHERE id_usuario = ?', [$id]);
            (new BitacoraModel())->registrar($idSesion, 'Cambio de contraseña', 'Usuarios', "Se actualizó la contraseña del usuario $nombre.");
        });
    }

    private function bloquear(int $id): void
    {
        // Lectura con bloqueo antes de cualquier lectura normal: fija el orden frente a otras transacciones.
        $this->consultar('SELECT id_usuario FROM usuarios WHERE id_usuario = ? FOR UPDATE', [$id]);
    }

    /** Bloquea a los demás administradores activos para que nadie quede sin acceso por una carrera. */
    private function exigirOtroAdministrador(int $idExcluir): void
    {
        $otros = $this->consultar(
            "SELECT u.id_usuario FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
             WHERE r.nombre_rol = ? AND u.estado = 'Activo' AND u.id_usuario <> ? FOR UPDATE",
            [ROL_ADMIN, $idExcluir]
        );
        if (!$otros) {
            throw new AppException('No puedes quitar este acceso: el sistema se quedaría sin administradores activos.', 409);
        }
    }

    /**
     * Primer arranque: crea los dos roles del sistema y el primer administrador.
     * GET_LOCK evita que dos peticiones simultáneas creen dos administradores.
     */
    public function crearPrimerAdministrador(array $d): void
    {
        $bloqueo = (int) $this->valor("SELECT GET_LOCK('grv_primer_admin', 5)");
        if ($bloqueo !== 1) {
            throw new AppException('Hay otra configuración en curso. Inténtalo de nuevo.', 409);
        }
        try {
            $this->transaccion(function () use ($d) {
                if ($this->total() > 0) {
                    throw new AppException('El sistema ya fue configurado.', 403);
                }
                $this->ejecutar(
                    "INSERT IGNORE INTO roles (nombre_rol, descripcion, estado) VALUES
                     (?, 'Administra usuarios, empleados, flota, reservas, reportes y bitácora', 'Activo'),
                     (?, 'Consulta disponibilidad y gestiona sus propias reservas', 'Activo')",
                    [ROL_ADMIN, ROL_EMPLEADO]
                );
                $idRol = (int) $this->valor('SELECT id_rol FROM roles WHERE nombre_rol = ?', [ROL_ADMIN]);
                $this->ejecutar(
                    "INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash, id_rol, estado) VALUES (?, ?, ?, ?, 'Activo')",
                    [$d['nombre_usuario'], $d['correo'], password_hash($d['contrasena'], PASSWORD_DEFAULT), $idRol]
                );
                (new BitacoraModel())->registrar($this->ultimoId(), 'Configuración inicial', 'Autenticación', 'Se creó el primer administrador y los roles del sistema.');
            });
        } finally {
            $this->valor("SELECT RELEASE_LOCK('grv_primer_admin')");
        }
    }
}
