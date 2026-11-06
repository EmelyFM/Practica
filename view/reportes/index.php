<?php defined('APP') || exit; ?>
<section class="panel">
    <header class="panel-encabezado"><h2>Generar reporte en PDF</h2></header>
    <form id="formReporte" class="row g-3 panel-cuerpo" novalidate>
        <div class="col-lg-4">
            <label class="form-label" for="tipo">Tipo de reporte</label>
            <select class="form-select" id="tipo" name="tipo">
                <option value="general">Reporte general de reservas</option>
                <option value="empleado">Reservas por empleado</option>
                <option value="vehiculo">Reservas por vehículo</option>
                <option value="periodo">Reservas por periodo</option>
                <option value="flota">Vehículos disponibles y fuera de servicio</option>
                <option value="mantenimientos">Mantenimientos</option>
                <option value="uso">Historial de uso de vehículos</option>
            </select>
            <p class="small text-secondary mt-2 mb-0" id="ayudaReporte"></p>
        </div>
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-md-6" data-filtro="id_empleado">
                    <label class="form-label" for="id_empleado">Empleado</label>
                    <select class="form-select" id="id_empleado" name="id_empleado"></select>
                </div>
                <div class="col-md-6" data-filtro="id_vehiculo">
                    <label class="form-label" for="id_vehiculo">Vehículo</label>
                    <select class="form-select" id="id_vehiculo" name="id_vehiculo"></select>
                </div>
                <div class="col-md-6" data-filtro="estado">
                    <label class="form-label" for="estado">Estado</label>
                    <select class="form-select" id="estado" name="estado"></select>
                </div>
                <div class="col-md-3" data-filtro="desde">
                    <label class="form-label" for="desde">Desde</label>
                    <input class="form-control" id="desde" name="desde" type="date">
                </div>
                <div class="col-md-3" data-filtro="hasta">
                    <label class="form-label" for="hasta">Hasta</label>
                    <input class="form-control" id="hasta" name="hasta" type="date">
                </div>
            </div>
        </div>
        <div class="col-12 text-end">
            <button class="btn btn-primary" type="submit"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Generar PDF</button>
        </div>
    </form>
</section>
