<?php
/**
 * Arranque común de la aplicación: constantes, autoload, errores, sesión y
 * cabeceras de seguridad. Lo incluyen únicamente index.php y api/index.php.
 */
if (!defined('APP')) {
    define('APP', true);
}

const APP_NOMBRE = 'Sistema de Gestión de Reservas de Vehículos';
const APP_NOMBRE_CORTO = 'Reservas de Flota';
const ROL_ADMIN = 'Administrador';
const ROL_EMPLEADO = 'Empleado';
const SESION_INACTIVIDAD_SEG = 1800;

define('RAIZ', dirname(__DIR__));

// Las horas de negocio vienen de MySQL (ver BaseModel::ahora). PHP solo las formatea
// y hace aritmética con ellas, por lo que usa UTC para no introducir cambios de horario.
date_default_timezone_set('UTC');

// Los errores se registran en el log del servidor; nunca se envían al navegador.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Con este modo mysqli lanza excepciones que el enrutador convierte en mensajes
// genéricos, de modo que ningún detalle de SQL o de conexión llega al usuario.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** Error previsto de la aplicación, con su código HTTP y errores por campo. */
class AppException extends RuntimeException
{
    public int $http;
    public array $errores;

    public function __construct(string $mensaje, int $http = 400, array $errores = [])
    {
        parent::__construct($mensaje);
        $this->http = $http;
        $this->errores = $errores;
    }
}

spl_autoload_register(static function (string $clase): void {
    if (!preg_match('/^[A-Za-z]+$/', $clase)) {
        return;
    }
    foreach (['controller', 'models'] as $carpeta) {
        $ruta = RAIZ . "/$carpeta/$clase.php";
        if (is_file($ruta)) {
            require_once $ruta;
            return;
        }
    }
});

// En Windows, «localhost» prueba primero IPv6 y MySQL solo escucha en IPv4: cada conexión
// tardaba 2 s (medido). Conexion ya admite DB_HOST, así que se aprovecha sin modificarla.
if (getenv('DB_HOST') === false) {
    putenv('DB_HOST=127.0.0.1');
}

require_once RAIZ . '/config/conexion.php';

/** Ruta pública de la aplicación; funciona en cualquier subcarpeta de htdocs. */
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir = preg_replace('#/api$#', '', $dir);
        $base = rtrim($dir, '/');
    }
    return $base;
}

/** Escapa un valor para imprimirlo en HTML. */
function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function es_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function iniciar_sesion(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('GRV_SESION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_url() . '/',
        'secure' => es_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function cerrar_sesion_php(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
        session_destroy();
    }
}

/**
 * Devuelve el usuario autenticado leyendo MySQL en cada petición: si fue
 * desactivado o cambió de rol, el cambio se aplica de inmediato.
 */
function usuario_actual(): ?array
{
    static $cargado = false;
    static $usuario = null;
    if ($cargado) {
        return $usuario;
    }
    $cargado = true;

    $id = $_SESSION['id_usuario'] ?? null;
    if (!$id) {
        return null;
    }
    if (time() - (int) ($_SESSION['ultima_actividad'] ?? 0) > SESION_INACTIVIDAD_SEG) {
        cerrar_sesion_php();
        return null;
    }
    $datos = (new UsuarioModel())->datosSesion((int) $id);
    if (!$datos || $datos['estado'] !== 'Activo' || $datos['estado_rol'] !== 'Activo') {
        cerrar_sesion_php();
        return null;
    }
    $_SESSION['ultima_actividad'] = time();
    $usuario = $datos;
    return $usuario;
}

function cabeceras_seguridad(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    // Impide recuperar páginas protegidas con el botón Atrás tras cerrar sesión.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function es_ip_local(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}
