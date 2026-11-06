<?php defined('APP') || exit; ?>
<section class="panel">
    <header class="panel-encabezado"><h2>Registro de operaciones</h2></header>
    <form id="formFiltros" class="row g-3 panel-cuerpo pb-0" novalidate>
        <div class="col-md-3">
            <label class="form-label" for="desde">Desde</label>
            <input class="form-control" id="desde" name="desde" type="date">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="hasta">Hasta</label>
            <input class="form-control" id="hasta" name="hasta" type="date">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="modulo">Módulo</label>
            <select class="form-select" id="modulo" name="modulo"><option value="">Todos</option></select>
        </div>
        <div class="col-md-2 alinear-boton">
            <button class="btn btn-outline-primary w-100" type="submit">Filtrar</button>
        </div>
    </form>
    <div class="panel-cuerpo">
        <div class="table-responsive">
            <table id="tabla" class="table table-hover align-middle w-100"></table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="tituloDetalle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloDetalle">Detalle del registro</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="cuerpoDetalle"></div>
        </div>
    </div>
</div>
