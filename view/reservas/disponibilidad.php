<?php
defined('APP') || exit;
$sinFicha = !$esAdmin && empty($usuario['id_empleado']);
?>
<?php if ($sinFicha): ?>
    <div class="alert alert-warning" role="alert">Puedes consultar la disponibilidad, pero tu usuario no tiene una ficha de empleado activa y no podrás reservar. Contacta al administrador.</div>
<?php endif; ?>
<section class="panel">
    <header class="panel-encabezado"><h2>Periodo solicitado</h2></header>
    <form id="formDisponibilidad" class="row g-3 panel-cuerpo" novalidate>
        <div class="col-md-5">
            <label class="form-label" for="dispInicio">Fecha y hora de inicio</label>
            <input class="form-control" id="dispInicio" name="fecha_inicio" type="datetime-local">
        </div>
        <div class="col-md-5">
            <label class="form-label" for="dispFin">Fecha y hora de finalización</label>
            <input class="form-control" id="dispFin" name="fecha_fin" type="datetime-local">
        </div>
        <div class="col-md-2 alinear-boton">
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Consultar</button>
        </div>
    </form>
</section>

<section class="panel d-none" id="panelResultados">
    <header class="panel-encabezado">
        <h2 id="tituloResultados">Resultados</h2>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="verNoDisponibles" checked>
            <label class="form-check-label small" for="verNoDisponibles">Mostrar no disponibles</label>
        </div>
    </header>
    <div class="panel-cuerpo">
        <div class="rejilla-vehiculos" id="resultados"></div>
    </div>
</section>

<?php require __DIR__ . '/_modales.php'; ?>
