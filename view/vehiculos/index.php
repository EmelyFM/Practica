<?php defined('APP') || exit; ?>
<section class="panel">
    <header class="panel-encabezado">
        <h2><?= $esAdmin ? 'Flota de vehículos' : 'Catálogo de vehículos' ?></h2>
        <?php if ($esAdmin): ?>
            <button class="btn btn-sm btn-primary" id="btnNuevo" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nuevo vehículo</button>
        <?php else: ?>
            <a class="btn btn-sm btn-primary" href="<?= e(base_url()) ?>/index.php?page=disponibilidad"><i class="bi bi-search" aria-hidden="true"></i> Consultar disponibilidad</a>
        <?php endif; ?>
    </header>
    <div class="panel-cuerpo">
        <p class="small text-secondary">El estado indica la condición física del vehículo. Si está libre o no en una fecha concreta se consulta en Disponibilidad.</p>
        <div class="table-responsive">
            <table id="tabla" class="table table-hover align-middle w-100"></table>
        </div>
    </div>
</section>

<?php if ($esAdmin): ?>
<div class="modal fade" id="modalVehiculo" tabindex="-1" aria-labelledby="tituloVehiculo" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="formVehiculo" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloVehiculo">Nuevo vehículo</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="placa">Placa</label>
                        <input class="form-control text-uppercase" id="placa" name="placa" maxlength="20" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="marca">Marca</label>
                        <input class="form-control" id="marca" name="marca" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="modelo">Modelo</label>
                        <input class="form-control" id="modelo" name="modelo" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="anio">Año</label>
                        <input class="form-control" id="anio" name="anio" type="number" min="1950" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="tipo_vehiculo">Tipo de vehículo</label>
                        <input class="form-control" id="tipo_vehiculo" name="tipo_vehiculo" maxlength="80" list="tiposVehiculo">
                        <datalist id="tiposVehiculo">
                            <option value="Automóvil"></option>
                            <option value="Camioneta"></option>
                            <option value="Furgoneta"></option>
                            <option value="Pick-up"></option>
                            <option value="Camión"></option>
                            <option value="Tractocamión"></option>
                        </datalist>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="color">Color</label>
                        <input class="form-control" id="color" name="color" maxlength="50">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="capacidad_carga">Capacidad de carga (kg)</label>
                        <input class="form-control" id="capacidad_carga" name="capacidad_carga" type="number" min="0" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="kilometraje">Kilometraje</label>
                        <input class="form-control" id="kilometraje" name="kilometraje" type="number" min="0" step="1">
                    </div>
                    <div class="col-md-4" id="grupoEstado">
                        <label class="form-label" for="estado">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option>Disponible</option>
                            <option>Mantenimiento</option>
                            <option>Fuera de servicio</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="observaciones">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" maxlength="2000"></textarea>
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
<?php endif; ?>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="tituloDetalle" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloDetalle">Detalle del vehículo</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="datosVehiculo"></div>
                <?php if ($esAdmin): ?>
                    <h3 class="h6 mt-4">Historial de reservas</h3>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Código</th><th>Empleado</th><th>Inicio</th><th>Fin</th><th>Devolución</th><th>Estado</th></tr></thead>
                            <tbody id="histReservas"></tbody>
                        </table>
                    </div>
                    <h3 class="h6 mt-4">Mantenimientos</h3>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Tipo</th><th>Descripción</th><th>Inicio</th><th>Fin</th><th>Costo</th><th>Estado</th></tr></thead>
                            <tbody id="histMantenimientos"></tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
