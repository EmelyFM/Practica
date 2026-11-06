<?php defined('APP') || exit; ?>
<section class="panel">
    <header class="panel-encabezado">
        <h2>Empleados</h2>
        <button class="btn btn-sm btn-primary" id="btnNuevo" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nuevo empleado</button>
    </header>
    <div class="panel-cuerpo">
        <div class="table-responsive">
            <table id="tabla" class="table table-hover align-middle w-100"></table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalEmpleado" tabindex="-1" aria-labelledby="tituloEmpleado" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="formEmpleado" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloEmpleado">Nuevo empleado</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="id_usuario">Usuario relacionado</label>
                        <select class="form-select" id="id_usuario" name="id_usuario"></select>
                        <div class="form-text">Solo aparecen usuarios activos que aún no tienen ficha de empleado.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input class="form-control" id="nombre" name="nombre" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="apellido_paterno">Apellido paterno</label>
                        <input class="form-control" id="apellido_paterno" name="apellido_paterno" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="apellido_materno">Apellido materno <span class="text-secondary">(opcional)</span></label>
                        <input class="form-control" id="apellido_materno" name="apellido_materno" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="telefono">Teléfono</label>
                        <input class="form-control" id="telefono" name="telefono" maxlength="15" inputmode="tel">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="puesto">Puesto</label>
                        <input class="form-control" id="puesto" name="puesto" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fecha_ingreso">Fecha de ingreso</label>
                        <input class="form-control" id="fecha_ingreso" name="fecha_ingreso" type="date">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="tituloDetalle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloDetalle">Detalle del empleado</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="cuerpoDetalle"></div>
        </div>
    </div>
</div>
