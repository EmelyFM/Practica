'use strict';

function pintarIndicadores(indicadores) {
    Object.entries(indicadores).forEach(([clave, valor]) => {
        const el = document.querySelector(`[data-kpi="${clave}"]`);
        if (el) el.textContent = valor;
    });
}

function pintarFilas(idCuerpo, filas, columnas, vacio) {
    const cuerpo = document.getElementById(idCuerpo);
    if (!filas.length) {
        cuerpo.innerHTML = `<tr><td colspan="${columnas}" class="text-center text-secondary py-4">${esc(vacio)}</td></tr>`;
        return null;
    }
    return cuerpo;
}

async function cargarPanelAdmin() {
    const { data } = await api('dashboard');
    pintarIndicadores(data.indicadores);
    const cuerpo = pintarFilas('tablaRecientes', data.recientes, 6, 'Todavía no hay reservas registradas.');
    if (cuerpo) {
        cuerpo.innerHTML = data.recientes.map((r) => `<tr>
            <td class="text-nowrap">${esc(r.codigo_reserva)}</td><td>${esc(r.vehiculo)}</td><td>${esc(r.empleado)}</td>
            <td class="text-nowrap">${fechaHora(r.fecha_inicio)}</td><td class="text-nowrap">${fechaHora(r.fecha_fin)}</td>
            <td>${etiqueta(r.estado)}</td></tr>`).join('');
    }
    const proximos = data.mantenimientos_proximos;
    if (proximos.length) {
        const panel = document.getElementById('panelMantenimientos');
        panel.classList.remove('d-none');
        panel.innerHTML = `<i class="bi bi-wrench-adjustable" aria-hidden="true"></i>
            <strong>${proximos.length} mantenimiento(s) requieren atención.</strong>
            Próximo: ${esc(proximos[0].vehiculo)}, ${fechaHora(proximos[0].fecha_inicio)}.
            <a class="alert-link" href="${esc(APP.base)}/index.php?page=mantenimientos">Ver mantenimientos</a>`;
    }
}

async function cargarPanelEmpleado() {
    const inicio = document.getElementById('panelInicio');
    const fin = document.getElementById('panelFin');
    const form = document.getElementById('formPeriodoPanel');

    const consultar = async (primeraCarga = false) => {
        limpiarErrores(form);
        if (inicio.value && fin.value && fin.value <= inicio.value) {
            marcarCampo(form, 'fin', 'La fecha final debe ser posterior a la fecha de inicio.');
            document.getElementById('kpiDisponibles').textContent = '—';
            return;
        }
        const { data } = await api('dashboard', { parametros: { inicio: inicio.value, fin: fin.value } });
        document.getElementById('kpiDisponibles').textContent = data.vehiculos_disponibles ?? '—';
        if (!primeraCarga) return;
        pintarIndicadores(data.indicadores);
        document.getElementById('avisoSinFicha').classList.toggle('d-none', data.tiene_ficha);

        const recientes = pintarFilas('tablaRecientes', data.recientes, 5, 'Todavía no tienes reservas.');
        if (recientes) {
            recientes.innerHTML = data.recientes.map((r) => `<tr>
                <td class="text-nowrap">${esc(r.codigo_reserva)}</td><td>${esc(r.vehiculo)}</td>
                <td class="text-nowrap">${fechaHora(r.fecha_inicio)}</td><td class="text-nowrap">${fechaHora(r.fecha_fin)}</td>
                <td>${etiqueta(r.estado)}</td></tr>`).join('');
        }
        const devoluciones = pintarFilas('tablaDevoluciones', data.devoluciones, 3, 'Todavía no has registrado devoluciones.');
        if (devoluciones) {
            devoluciones.innerHTML = data.devoluciones.map((r) => `<tr>
                <td>${esc(r.vehiculo)}</td><td class="text-nowrap">${fechaHora(r.fecha_devolucion)}</td>
                <td>${etiqueta(r.estado_devolucion)}</td></tr>`).join('');
        }
    };

    const ahora = new Date();
    ahora.setMinutes(0, 0, 0);
    ahora.setHours(ahora.getHours() + 1);
    inicio.value = dateAInput(ahora);
    fin.value = dateAInput(new Date(ahora.getTime() + 8 * 3600 * 1000));

    const alCambiar = () => consultar().catch((e) => mostrarError(e, form));
    inicio.addEventListener('change', alCambiar);
    fin.addEventListener('change', alCambiar);
    form.addEventListener('submit', (e) => e.preventDefault());
    await consultar(true);
}

(APP.esAdmin ? cargarPanelAdmin() : cargarPanelEmpleado()).catch((e) => mostrarError(e));
