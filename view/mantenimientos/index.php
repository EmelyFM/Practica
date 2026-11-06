<?php defined('APP') || exit; ?>
<div id="panelRecordatorios" class="d-none"></div>

<section class="panel">
    <header class="panel-encabezado">
        <h2>Mantenimientos</h2>
        <button class="btn btn-sm btn-primary" id="btnNuevo" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nuevo mantenimiento</button>
    </header>
    <div class="panel-cuerpo">
        <div class="table-responsive">
            <table id="tabla" class="table table-hover align-middle w-100"></table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalMantenimiento" tabindex="-1" aria-labelledby="tituloMantenimiento" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="formMantenimiento" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloMantenimiento">Nuevo mantenimiento</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="id_vehiculo">Vehículo</label>
                        <select class="form-select" id="id_vehiculo" name="id_vehiculo"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="id_usuario">Usuario responsable</label>
                        <select class="form-select" id="id_usuario" name="id_usuario"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tipo_mantenimiento">Tipo de mantenimiento</label>
                        <select class="form-select" id="tipo_mantenimiento" name="tipo_mantenimiento">
                            <option>Preventivo</option>
                            <option>Correctivo</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="estado">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option>Programado</option>
                            <option>En proceso</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="descripcion">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2" maxlength="2000"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fecha_inicio">Fecha de inicio</label>
                        <input class="form-control" id="fecha_inicio" name="fecha_inicio" type="datetime-local">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fecha_fin">Fecha final</label>
                        <input class="form-control" id="fecha_fin" name="fecha_fin" type="datetime-local">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="costo">Costo</label>
                        <input class="form-control" id="costo" name="costo" type="number" min="0" step="0.01">
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

<div class="modal fade" id="modalFinalizar" tabindex="-1" aria-labelledby="tituloFinalizar" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="formFinalizar" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloFinalizar">Finalizar mantenimiento</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="finReal">Fecha y hora de término</label>
                    <input class="form-control" id="finReal" name="fecha_fin" type="datetime-local">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="costoFinal">Costo final</label>
                    <input class="form-control" id="costoFinal" name="costo" type="number" min="0" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="obsFinal">Observaciones</label>
                    <textarea class="form-control" id="obsFinal" name="observaciones" rows="2" maxlength="2000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Finalizar</button>
            </div>
        </form>
    </div>
</div>
