<?php defined('APP') || exit; ?>
<?php if ($esAdmin): ?>
    <section class="rejilla-kpi" aria-label="Indicadores">
        <article class="kpi"><i class="bi bi-truck" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="total_vehiculos">—</span><span class="kpi-etiqueta">Total de vehículos</span></div></article>
        <article class="kpi"><i class="bi bi-check-circle text-success" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="vehiculos_operativos">—</span><span class="kpi-etiqueta">Físicamente operativos</span></div></article>
        <article class="kpi"><i class="bi bi-wrench-adjustable text-warning" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="vehiculos_mantenimiento">—</span><span class="kpi-etiqueta">En mantenimiento</span></div></article>
        <article class="kpi"><i class="bi bi-calendar-check text-primary" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="reservas_activas">—</span><span class="kpi-etiqueta">Reservas activas (confirmadas y en uso)</span></div></article>
        <article class="kpi"><i class="bi bi-hourglass-split text-warning" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="reservas_pendientes">—</span><span class="kpi-etiqueta">Reservas pendientes</span></div></article>
        <article class="kpi"><i class="bi bi-flag text-secondary" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="reservas_finalizadas">—</span><span class="kpi-etiqueta">Reservas finalizadas</span></div></article>
    </section>

    <div id="panelMantenimientos" class="alert alert-warning d-none" role="status"></div>

    <section class="panel">
        <header class="panel-encabezado">
            <h2>Reservas recientes</h2>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url()) ?>/index.php?page=reservas">Ver todas</a>
        </header>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Código</th><th>Vehículo</th><th>Empleado</th><th>Inicio</th><th>Fin</th><th>Estado</th></tr></thead>
                <tbody id="tablaRecientes"><tr><td colspan="6" class="text-center text-secondary py-4">Cargando…</td></tr></tbody>
            </table>
        </div>
    </section>
<?php else: ?>
    <div id="avisoSinFicha" class="alert alert-warning d-none" role="alert">
        Tu usuario aún no tiene una ficha de empleado activa, por lo que no puedes registrar reservas. Contacta al administrador.
    </div>

    <section class="panel">
        <header class="panel-encabezado"><h2>Vehículos disponibles para el periodo</h2></header>
        <form id="formPeriodoPanel" class="row g-3 align-items-start panel-cuerpo" novalidate>
            <div class="col-md-4">
                <label class="form-label" for="panelInicio">Inicio</label>
                <input class="form-control" type="datetime-local" id="panelInicio" name="inicio">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="panelFin">Fin</label>
                <input class="form-control" type="datetime-local" id="panelFin" name="fin">
            </div>
            <div class="col-md-4 kpi-periodo">
                <span class="kpi-valor" id="kpiDisponibles">—</span>
                <span class="kpi-etiqueta">vehículos disponibles</span>
                <a class="btn btn-sm btn-primary ms-auto" href="<?= e(base_url()) ?>/index.php?page=disponibilidad">Ver y reservar</a>
            </div>
        </form>
    </section>

    <section class="rejilla-kpi rejilla-kpi-cinco" aria-label="Mis indicadores">
        <article class="kpi"><i class="bi bi-calendar3" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="mis_reservas">—</span><span class="kpi-etiqueta">Mis reservas</span></div></article>
        <article class="kpi"><i class="bi bi-hourglass-split text-warning" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="pendientes">—</span><span class="kpi-etiqueta">Pendientes</span></div></article>
        <article class="kpi"><i class="bi bi-calendar-check text-primary" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="confirmadas">—</span><span class="kpi-etiqueta">Confirmadas</span></div></article>
        <article class="kpi"><i class="bi bi-truck text-primary" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="en_uso">—</span><span class="kpi-etiqueta">En uso</span></div></article>
        <article class="kpi"><i class="bi bi-arrow-return-left text-success" aria-hidden="true"></i><div><span class="kpi-valor" data-kpi="devoluciones">—</span><span class="kpi-etiqueta">Devoluciones registradas</span></div></article>
    </section>

    <div class="row g-3">
        <div class="col-xl-7">
            <section class="panel h-100">
                <header class="panel-encabezado"><h2>Mis reservas recientes</h2></header>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Código</th><th>Vehículo</th><th>Inicio</th><th>Fin</th><th>Estado</th></tr></thead>
                        <tbody id="tablaRecientes"><tr><td colspan="5" class="text-center text-secondary py-4">Cargando…</td></tr></tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="panel h-100">
                <header class="panel-encabezado"><h2>Historial de devoluciones</h2></header>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Vehículo</th><th>Devuelto</th><th>Estado</th></tr></thead>
                        <tbody id="tablaDevoluciones"><tr><td colspan="3" class="text-center text-secondary py-4">Cargando…</td></tr></tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
<?php endif; ?>
