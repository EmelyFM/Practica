<?php
defined('APP') || exit;

class AuthController extends BaseController
{
    protected bool $requiereSesion = false;
    private UsuarioModel $usuarios;

    public function __construct()
    {
        parent::__construct();
        $this->usuarios = new UsuarioModel();
    }

    public function login(): void
    {
        $v = new Validador($this->entrada());
        $nombre = $v->texto('nombre_usuario', 'El nombre de usuario', 50);
        $clave = $this->entrada()['contrasena'] ?? '';
        if (!is_string($clave) || $clave === '') {
            $v->error('contrasena', 'La contraseña es obligatoria.');
        }
        $v->verificar();

        $u = $this->usuarios->buscarParaLogin($nombre);
        // Con un hash ficticio bien formado el tiempo de respuesta no delata si el usuario existe.
        $hash = $u['contrasena_hash'] ?? ('$2y$10$' . str_repeat('a', 22) . str_repeat('b', 31));
        $correcta = password_verify($clave, $hash);
        $bitacora = new BitacoraModel();

        if (!$u || !$correcta) {
            if ($u) {
                $bitacora->registrar((int) $u['id_usuario'], 'Inicio de sesión fallido', 'Autenticación', 'Contraseña incorrecta.');
            }
            throw new AppException('Usuario o contraseña incorrectos.', 401);
        }
        if ($u['estado'] !== 'Activo' || $u['estado_rol'] !== 'Activo') {
            throw new AppException('Tu cuenta está inactiva. Contacta al administrador.', 403);
        }

        session_regenerate_id(true);
        $_SESSION['id_usuario'] = (int) $u['id_usuario'];
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));

        $bitacora->registrar((int) $u['id_usuario'], 'Inicio de sesión', 'Autenticación', "El usuario {$u['nombre_usuario']} inició sesión.");
        $datos = $this->usuarios->datosSesion((int) $u['id_usuario']);
        $this->responder('Sesión iniciada correctamente.', [
            'rol' => $datos['nombre_rol'],
            'redirect' => base_url() . '/index.php?page=inicio',
        ]);
    }

    public function logout(): void
    {
        if ($this->usuario) {
            (new BitacoraModel())->registrar($this->idUsuario(), 'Cierre de sesión', 'Autenticación', "El usuario {$this->usuario['nombre_usuario']} cerró sesión.");
        }
        cerrar_sesion_php();
        $this->responder('Sesión cerrada.', ['redirect' => base_url() . '/index.php']);
    }

    /**
     * Crea el primer administrador. Solo funciona con la tabla de usuarios vacía y desde
     * el propio equipo servidor; la contraseña la elige quien instala, no hay credenciales
     * fijas en el código y, en cuanto existe un usuario, este acceso queda cerrado.
     */
    public function primerAdmin(): void
    {
        if (!es_ip_local()) {
            throw new AppException('La configuración inicial solo puede hacerse desde el equipo servidor.', 403);
        }
        if ($this->usuarios->total() > 0) {
            throw new AppException('El sistema ya fue configurado.', 403);
        }
        $v = new Validador($this->entrada());
        $datos = [
            'nombre_usuario' => $v->nombreUsuario(),
            'correo' => $v->correo(),
            'contrasena' => $v->contrasena(),
        ];
        $v->verificar();
        $this->usuarios->crearPrimerAdministrador($datos);
        $this->responder('Administrador creado. Ya puedes iniciar sesión.', ['redirect' => base_url() . '/index.php'], 201);
    }
}
