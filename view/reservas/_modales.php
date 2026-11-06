<?php defined('APP') || exit; ?>
<div class="modal fade" id="modalReserva" tabindex="-1" aria-labelledby="tituloReserva" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="formReserva" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloReserva">Nueva reserva</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="codigoReserva">Código de reserva</label>
                        <input class="form-control" id="codigoReserva" value="Se genera automáticamente" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="estadoReserva">Estado</label>
                        <select class="form-select" id="estadoReserva" name="estado"<?= $esAdmin ? '' : ' disabled' ?>>
                            <option>Pendiente</option>
                            <?php if ($esAdmin): ?><option>Confirmada</option><?php endif; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="empleadoReserva">Empleado responsable</label>
                        <?php if ($esAdmin): ?>
                            <select class="form-select" id="empleadoReserva" name="id_empleado"></select>
                        <?php else: ?>
                            <input class="form-control" id="empleadoReserva" value="<?= e($usuario['nombre_empleado']) ?>" readonly>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="inicioReserva">Fecha y hora de inicio</label>
                        <input class="form-control" id="inicioReserva" name="fecha_inicio" type="datetime-local">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="finReserva">Fecha y hora de finalización</label>
                        <input class="form-control" id="finReserva" name="fecha_fin" type="datetime-local">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="vehiculoReserva">Vehículo</label>
                        <select class="form-select" id="vehiculoReserva" name="id_vehiculo">
                            <option value="">Selecciona primero el periodo</option>
                        </select>
                        <div class="form-text">Solo aparecen los vehículos disponibles en el periodo indicado.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="motivoReserva">Motivo</label>
                        <input class="form-control" id="motivoReserva" name="motivo" maxlength="250">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="obsReserva">Observaciones <span class="text-secondary">(opcional)</span></label>
                        <textarea class="form-control" id="obsReserva" name="observaciones" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar reserva</button>
            </div>
        </form>
    </div>
</div>
