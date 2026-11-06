<?php
defined('APP') || exit;
$esDevoluciones = $clave === 'devoluciones';
$sinFicha = !$esAdmin && empty($usuario['id_empleado']);
?>
<?php if ($sinFicha): ?>
    <div class="alert alert-warning" role="alert">Tu usuario aún no tiene una ficha de empleado activa, por lo que no puedes registrar reservas. Contacta al administrador.</div>
<?php endif; ?>
<section class="panel">
    <header class="panel-encabezado">
        <h2><?= $esDevoluciones ? 'Vehículos por devolver e historial' : ($esAdmin ? 'Todas las reservas' : 'Mis reservas') ?></h2>
        <?php if (!$esDevoluciones && !$sinFicha): ?>
            <button class="btn btn-sm btn-primary" id="btnNuevaReserva" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva reserva</button>
        <?php endif; ?>
    </header>
    <div class="panel-cuerpo">
        <div class="table-responsive">
            <table id="tablaReservas" class="table table-hover align-middle w-100"></table>
        </div>
    </div>
</section>

<?php require __DIR__ . '/_modales.php'; ?>

<div class="modal fade" id="modalDevolucion" tabindex="-1" aria-labelledby="tituloDevolucion" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="formDevolucion" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloDevolucion">Registrar devolución</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary" id="resumenDevolucion"></p>
                <div class="mb-3">
                    <label class="form-label" for="fechaDevolucion">Fecha y hora real de devolución</label>
                    <input class="form-control" id="fechaDevolucion" name="fecha_devolucion" type="datetime-local">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="estadoDevolucion">Estado de devolución</label>
                    <select class="form-select" id="estadoDevolucion" name="estado_devolucion">
                        <option>Devuelto</option>
                        <option>Con observaciones</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="obsDevolucion">Observaciones</label>
                    <textarea class="form-control" id="obsDevolucion" name="observaciones" rows="3" maxlength="2000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Registrar devolución</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="tituloDetalle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloDetalle">Detalle de la reserva</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="cuerpoDetalle"></div>
        </div>
    </div>
</div>
