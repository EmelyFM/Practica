<?php
defined('APP') || exit;

$menu = $esAdmin
    ? [
        'inicio' => ['Inicio', 'bi-speedometer2'],
        'usuarios' => ['Usuarios', 'bi-person-gear'],
        'empleados' => ['Empleados', 'bi-people'],
        'vehiculos' => ['Vehículos', 'bi-truck'],
        'reservas' => ['Reservas', 'bi-calendar-check'],
        'disponibilidad' => ['Disponibilidad', 'bi-search'],
        'mantenimientos' => ['Mantenimientos', 'bi-wrench-adjustable'],
        'reportes' => ['Reportes', 'bi-file-earmark-pdf'],
        'bitacora' => ['Bitácora', 'bi-journal-text'],
    ]
    : [
        'inicio' => ['Inicio', 'bi-speedometer2'],
        'vehiculos' => ['Catálogo de vehículos', 'bi-truck'],
        'disponibilidad' => ['Consultar disponibilidad', 'bi-search'],
        'nueva-reserva' => ['Nueva reserva', 'bi-calendar-plus'],
        'reservas' => ['Mis reservas', 'bi-calendar-check'],
        'devoluciones' => ['Mis devoluciones', 'bi-arrow-return-left'],
    ];
?>
<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="menuLateral" aria-label="Navegación principal">
    <div class="sidebar-marca">
        <i class="bi bi-truck-front" aria-hidden="true"></i>
        <div>
            <strong><?= e(APP_NOMBRE_CORTO) ?></strong>
            <small>Gestión logística</small>
        </div>
        <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral" aria-label="Cerrar menú"></button>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu as $destino => [$etiqueta, $icono]): ?>
            <a class="sidebar-link <?= $clave === $destino ? 'activo' : '' ?>" href="<?= e(base_url()) ?>/index.php?page=<?= e($destino) ?>"<?= $clave === $destino ? ' aria-current="page"' : '' ?>>
                <i class="bi <?= e($icono) ?>" aria-hidden="true"></i><span><?= e($etiqueta) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-pie">
        <button type="button" class="sidebar-link w-100" id="btnCerrarSesion">
            <i class="bi bi-box-arrow-left" aria-hidden="true"></i><span>Cerrar sesión</span>
        </button>
    </div>
</aside>
<div class="app-principal">
    <header class="app-topbar">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-controls="menuLateral" aria-label="Abrir menú">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <h1 class="topbar-titulo"><?= e($titulo) ?></h1>
        <div class="topbar-usuario">
            <div class="text-end">
                <div class="fw-semibold"><?= e($usuario['nombre_empleado'] ?: $usuario['nombre_usuario']) ?></div>
                <small class="text-secondary"><?= e($usuario['nombre_rol']) ?></small>
            </div>
            <span class="topbar-avatar" aria-hidden="true"><i class="bi bi-person"></i></span>
        </div>
    </header>
    <main class="app-contenido">
