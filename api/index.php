<?php
/**
 * Enrutador JSON. Todas las peticiones Fetch pasan por aquí:
 *   api/index.php?ruta=recurso/{id}/accion
 *
 * Verbos: GET consulta, POST crea, PUT actualiza un registro completo, PATCH
 * cambia estado o realiza una acción puntual y DELETE elimina. No se usa POST
 * como sustituto: Apache de XAMPP entrega estos verbos a PHP sin configuración.
 * (login y logout son POST porque crean y destruyen una sesión.)
 */
require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json; charset=utf-8');
cabeceras_seguridad();
ob_start();

const RUTAS = [
    // Autenticación
    ['POST', 'auth/login', 'AuthController', 'login'],
    ['POST', 'auth/logout', 'AuthController', 'logout'],
    ['POST', 'auth/primer-admin', 'AuthController', 'primerAdmin'],
    // Panel
    ['GET', 'dashboard', 'ReporteController', 'dashboard'],
    // Usuarios
    ['GET', 'usuarios', 'UsuarioController', 'listar'],
    ['GET', 'usuarios/roles', 'UsuarioController', 'roles'],
    ['GET', 'usuarios/{id}', 'UsuarioController', 'obtener'],
    ['POST', 'usuarios', 'UsuarioController', 'crear'],
    ['PUT', 'usuarios/{id}', 'UsuarioController', 'actualizar'],
    ['PATCH', 'usuarios/{id}/estado', 'UsuarioController', 'cambiarEstado'],
    ['PATCH', 'usuarios/{id}/contrasena', 'UsuarioController', 'cambiarContrasena'],
    // Empleados
    ['GET', 'empleados', 'EmpleadoController', 'listar'],
    ['GET', 'empleados/usuarios-disponibles', 'EmpleadoController', 'usuariosDisponibles'],
    ['GET', 'empleados/{id}', 'EmpleadoController', 'obtener'],
    ['POST', 'empleados', 'EmpleadoController', 'crear'],
    ['PUT', 'empleados/{id}', 'EmpleadoController', 'actualizar'],
    ['PATCH', 'empleados/{id}/estado', 'EmpleadoController', 'cambiarEstado'],
    // Vehículos
    ['GET', 'vehiculos', 'VehiculoController', 'listar'],
    ['GET', 'vehiculos/{id}', 'VehiculoController', 'obtener'],
    ['GET', 'vehiculos/{id}/reservas', 'VehiculoController', 'historialReservas'],
    ['GET', 'vehiculos/{id}/mantenimientos', 'VehiculoController', 'historialMantenimientos'],
    ['POST', 'vehiculos', 'VehiculoController', 'crear'],
    ['PUT', 'vehiculos/{id}', 'VehiculoController', 'actualizar'],
    ['PATCH', 'vehiculos/{id}/estado', 'VehiculoController', 'cambiarEstado'],
    ['DELETE', 'vehiculos/{id}', 'VehiculoController', 'eliminar'],
    // Reservas
    ['GET', 'reservas', 'ReservaController', 'listar'],
    ['GET', 'reservas/disponibilidad', 'ReservaController', 'disponibilidad'],
    ['GET', 'reservas/{id}', 'ReservaController', 'obtener'],
    ['POST', 'reservas', 'ReservaController', 'crear'],
    ['PUT', 'reservas/{id}', 'ReservaController', 'actualizar'],
    ['PATCH', 'reservas/{id}/confirmar', 'ReservaController', 'confirmar'],
    ['PATCH', 'reservas/{id}/cancelar', 'ReservaController', 'cancelar'],
    ['PATCH', 'reservas/{id}/iniciar', 'ReservaController', 'iniciar'],
    ['PATCH', 'reservas/{id}/devolver', 'ReservaController', 'devolver'],
    // Mantenimientos
    ['GET', 'mantenimientos', 'MantenimientoController', 'listar'],
    ['GET', 'mantenimientos/proximos', 'MantenimientoController', 'proximos'],
    ['GET', 'mantenimientos/{id}', 'MantenimientoController', 'obtener'],
    ['POST', 'mantenimientos', 'MantenimientoController', 'crear'],
    ['PUT', 'mantenimientos/{id}', 'MantenimientoController', 'actualizar'],
    ['PATCH', 'mantenimientos/{id}/estado', 'MantenimientoController', 'cambiarEstado'],
    // Reportes
    ['GET', 'reportes/opciones', 'ReporteController', 'opciones'],
    ['GET', 'reportes/pdf', 'ReporteController', 'pdf'],
    // Bitácora
    ['GET', 'bitacora', 'BitacoraController', 'listar'],
];

function responder_error(string $mensaje, int $http, array $errores = []): void
{
    if (ob_get_level()) {
        ob_clean();
    }
    $cuerpo = ['success' => false, 'message' => $mensaje];
    if ($errores) {
        $cuerpo['errors'] = $errores;
    }
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
}

try {
    iniciar_sesion();

    $ruta = trim((string) ($_GET['ruta'] ?? ''), '/');
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $permitidos = [];

    foreach (RUTAS as [$verbo, $patron, $clase, $accion]) {
        $regex = '#^' . str_replace('\{id\}', '([1-9][0-9]{0,9})', preg_quote($patron, '#')) . '$#';
        if (!preg_match($regex, $ruta, $coincidencia)) {
            continue;
        }
        if ($verbo !== $metodo) {
            $permitidos[] = $verbo;
            continue;
        }
        $controlador = new $clase();
        isset($coincidencia[1])
            ? $controlador->$accion((int) $coincidencia[1])
            : $controlador->$accion();
        exit;
    }

    if ($permitidos) {
        header('Allow: ' . implode(', ', array_unique($permitidos)));
        throw new AppException('Método no permitido para esta ruta.', 405);
    }
    throw new AppException('La ruta solicitada no existe.', 404);
} catch (AppException $e) {
    responder_error($e->getMessage(), $e->http, $e->errores);
} catch (mysqli_sql_exception $e) {
    error_log('[GRV] SQL ' . $e->getCode() . ': ' . $e->getMessage());
    switch ($e->getCode()) {
        case 1062:
            responder_error('Ya existe un registro con esos datos.', 409);
            break;
        case 1451:
        case 1452:
            responder_error('La operación hace referencia a un registro inexistente o en uso.', 409);
            break;
        case 1205:
        case 1213:
            responder_error('El registro está siendo modificado por otro usuario. Inténtalo de nuevo.', 409);
            break;
        case 1044:
        case 1045:
        case 1049:
        case 2002:
        case 2006:
        case 2013:
            responder_error('No se pudo conectar con la base de datos.', 503);
            break;
        default:
            responder_error('Ocurrió un error interno en el servidor.', 500);
    }
} catch (Throwable $e) {
    error_log('[GRV] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    responder_error('Ocurrió un error interno en el servidor.', 500);
}
