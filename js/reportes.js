'use strict';

const form = document.getElementById('formReporte');
const selectTipo = document.getElementById('tipo');

const ESTADOS_RESERVA = ['Pendiente', 'Confirmada', 'En uso', 'Finalizada', 'Cancelada'];

const TIPOS = {
    general: { filtros: ['estado', 'desde', 'hasta'], estados: ESTADOS_RESERVA, ayuda: 'Todas las reservas; los filtros son opcionales.' },
    empleado: { filtros: ['id_empleado', 'estado', 'desde', 'hasta'], estados: ESTADOS_RESERVA, requeridos: ['id_empleado'], ayuda: 'Selecciona un empleado.' },
    vehiculo: { filtros: ['id_vehiculo', 'estado', 'desde', 'hasta'], estados: ESTADOS_RESERVA, requeridos: ['id_vehiculo'], ayuda: 'Selecciona un vehículo.' },
    periodo: { filtros: ['estado', 'desde', 'hasta'], estados: ESTADOS_RESERVA, requeridos: ['desde', 'hasta'], ayuda: 'Reservas que coinciden con el periodo indicado.' },
    flota: { filtros: ['estado'], estados: ['Disponible', 'Mantenimiento', 'Fuera de servicio'], ayuda: 'Por omisión incluye los vehículos disponibles y los fuera de servicio.' },
    mantenimientos: { filtros: ['id_vehiculo', 'estado', 'desde', 'hasta'], estados: ['Programado', 'En proceso', 'Finalizado', 'Cancelado'], ayuda: 'Incluye el costo total.' },
    uso: { filtros: ['id_vehiculo', 'id_empleado', 'desde', 'hasta'], ayuda: 'Periodos reales de uso, con la devolución y el tiempo utilizado.' },
};

function aplicarTipo() {
    const t = TIPOS[selectTipo.value];
    limpiarErrores(form);
    document.querySelectorAll('[data-filtro]').forEach((d) => {
        const visible = t.filtros.includes(d.dataset.filtro);
        d.classList.toggle('d-none', !visible);
        if (!visible) d.querySelector('select, input').value = '';
    });
    document.getElementById('ayudaReporte').textContent = t.ayuda;
    if (t.estados) {
        llenarSelect(form.elements.estado, t.estados.map((e) => ({ id: e, texto: e })), { vacio: 'Todos' });
    }
}

async function cargarOpciones() {
    const { data } = await api('reportes/opciones');
    llenarSelect(form.elements.id_empleado, data.empleados, { valor: 'id_empleado', texto: 'nombre_completo', vacio: 'Todos' });
    llenarSelect(form.elements.id_vehiculo, data.vehiculos, {
        valor: 'id_vehiculo', texto: (v) => `${v.placa} · ${v.marca} ${v.modelo}`, vacio: 'Todos',
    });
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const t = TIPOS[selectTipo.value];
    const reglas = { hasta: [(v, d) => (v && d.desde && v < d.desde ? 'La fecha final no puede ser anterior a la inicial.' : null)] };
    (t.requeridos ?? []).forEach((c) => {
        reglas[c] = [V.req('Este dato es obligatorio para este reporte.'), ...(reglas[c] ?? [])];
    });
    const datos = validarFormulario(form, reglas);
    if (!datos) return;

    await conBotonOcupado(form, async () => {
        try {
            const respuesta = await peticion('reportes/pdf', { parametros: datos });
            if (!respuesta.ok) throw await errorDeRespuesta(respuesta);
            const blob = await respuesta.blob();
            const nombre = /filename="([^"]+)"/.exec(respuesta.headers.get('Content-Disposition') ?? '')?.[1] ?? 'reporte.pdf';
            const url = URL.createObjectURL(blob);
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = nombre;
            document.body.appendChild(enlace);
            enlace.click();
            enlace.remove();
            setTimeout(() => URL.revokeObjectURL(url), 10000);
            exito('Reporte generado');
        } catch (error) {
            if (error.status === 401) {
                location.href = `${APP.base}/index.php`;
                return;
            }
            mostrarError(error, form);
        }
    });
});

selectTipo.addEventListener('change', aplicarTipo);
aplicarTipo();
cargarOpciones().catch((e) => mostrarError(e));
