<?php
/**
 * Controlador frontal de las páginas. Decide qué vista cargar según la sesión y el rol;
 * los datos llegan después a la vista mediante Fetch API desde api/index.php.
 */
require_once __DIR__ . '/config/app.php';

cabeceras_seguridad();

$paginas = [
    // clave => [título, vista, scripts, roles (null = cualquier rol autenticado)]
    'inicio' => ['Inicio', 'dashboard/inicio.php', ['dashboard.js'], null],
    'usuarios' => ['Usuarios', 'usuarios/index.php', ['usuarios.js'], [ROL_ADMIN]],
    'empleados' => ['Empleados', 'empleados/index.php', ['empleados.js'], [ROL_ADMIN]],
    'vehiculos' => ['Vehículos', 'vehiculos/index.php', ['vehiculos.js'], null],
    'reservas' => ['Reservas', 'reservas/index.php', ['reservas.js'], null],
    'nueva-reserva' => ['Nueva reserva', 'reservas/index.php', ['reservas.js'], null],
    'devoluciones' => ['Mis devoluciones', 'reservas/index.php', ['reservas.js'], null],
    'disponibilidad' => ['Disponibilidad', 'reservas/disponibilidad.php', ['reservas.js'], null],
    'mantenimientos' => ['Mantenimientos', 'mantenimientos/index.php', ['mantenimientos.js'], [ROL_ADMIN]],
    'reportes' => ['Reportes', 'reportes/index.php', ['reportes.js'], [ROL_ADMIN]],
    'bitacora' => ['Bitácora', 'bitacora/index.php', ['bitacora.js'], [ROL_ADMIN]],
];

function pagina_error(int $http, string $titulo, string $mensaje): void
{
    http_response_code($http);
    require RAIZ . '/view/layouts/error.php';
}

try {
    iniciar_sesion();
    $usuario = usuario_actual();

    if (!$usuario) {
        $vistaPublica = (new UsuarioModel())->total() === 0 ? 'setup' : 'login';
        $puedeConfigurar = es_ip_local();
        require RAIZ . '/view/auth/' . ($vistaPublica === 'setup' ? 'setup.php' : 'login.php');
        exit;
    }

    $clave = (string) ($_GET['page'] ?? 'inicio');
    if (!isset($paginas[$clave])) {
        pagina_error(404, 'Página no encontrada', 'La página que buscas no existe.');
        exit;
    }
    [$titulo, $vista, $scripts, $roles] = $paginas[$clave];
    if ($roles !== null && !in_array($usuario['nombre_rol'], $roles, true)) {
        pagina_error(403, 'Acceso denegado', 'Tu rol no tiene permiso para abrir esta página.');
        exit;
    }

    $esAdmin = $usuario['nombre_rol'] === ROL_ADMIN;
    if ($clave === 'reservas' && !$esAdmin) {
        $titulo = 'Mis reservas';
    }
    if ($clave === 'vehiculos' && !$esAdmin) {
        $titulo = 'Catálogo de vehículos';
    }
    if ($clave === 'devoluciones' && $esAdmin) {
        $titulo = 'Devoluciones';
    }

    require RAIZ . '/view/layouts/header.php';
    require RAIZ . '/view/layouts/sidebar.php';
    require RAIZ . '/view/' . $vista;
    require RAIZ . '/view/layouts/footer.php';
} catch (Throwable $e) {
    error_log('[GRV] ' . get_class($e) . ': ' . $e->getMessage());
    if (!headers_sent()) {
        pagina_error(500, 'Servicio no disponible', 'No se pudo cargar la página. Verifica que MySQL esté en ejecución e inténtalo de nuevo.');
    }
}
