'use strict';

const form = document.getElementById('formMantenimiento');
const formFinalizar = document.getElementById('formFinalizar');
const modal = new bootstrap.Modal('#modalMantenimiento');
const modalFinalizar = new bootstrap.Modal('#modalFinalizar');
let mantenimientos = [];
let responsables = [];
let editando = null;
let idFinalizar = null;

const tabla = crearTabla('#tabla', [
    { data: 'vehiculo', title: 'Vehículo' },
    { data: 'tipo_mantenimiento', title: 'Tipo' },
    { data: 'responsable', title: 'Responsable' },
    columnaFecha('fecha_inicio', 'Inicio'),
    { data: 'fecha_fin', title: 'Fin', render: (d, t) => (t === 'display' ? (d ? fechaHora(d) : 'Abierto') : d) },
    { data: 'costo', title: 'Costo', className: 'text-end', render: (d, t) => (t === 'display' ? moneda(d) : d) },
    {
        data: 'estado', title: 'Estado',
        render: (d, t, m) => (t === 'display' ? etiqueta(d) + (m.alerta ? ` ${etiqueta(m.alerta)}` : '') : d),
    },
    {
        data: null, title: 'Acciones', orderable: false, searchable: false, className: 'acciones',
        render: (_, __, m) => {
            const id = m.id_mantenimiento;
            let html = '';
            if (m.estado === 'Programado' || m.estado === 'En proceso') html += boton('editar', 'bi-pencil', 'Editar', id, 'primary');
            if (m.estado === 'Programado') html += boton('iniciar', 'bi-play-fill', 'Iniciar', id, 'primary');
            if (m.estado === 'En proceso') html += boton('finalizar', 'bi-check-lg', 'Finalizar', id, 'success');
            if (m.estado === 'Programado' || m.estado === 'En proceso') html += boton('cancelar', 'bi-x-lg', 'Cancelar', id, 'danger');
            return html || '<span class="text-secondary small">Cerrado</span>';
        },
    },
]);

function pintarRecordatorios(lista) {
    const panel = document.getElementById('panelRecordatorios');
    if (!lista.length) {
        panel.classList.add('d-none');
        return;
    }
    panel.classList.remove('d-none');
    panel.innerHTML = `<section class="panel">
        <header class="panel-encabezado"><h2><i class="bi bi-bell text-warning" aria-hidden="true"></i> Recordatorios</h2></header>
        <ul class="list-group list-group-flush">
            ${lista.map((m) => `<li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                <span><strong>${esc(m.vehiculo)}</strong> · ${esc(m.tipo_mantenimiento)}: ${esc(m.descripcion)}</span>
                <span class="text-nowrap">${fechaHora(m.fecha_inicio)} ${etiqueta(m.alerta)}</span>
            </li>`).join('')}
        </ul>
    </section>`;
}

async function cargar() {
    const [{ data }, recordatorios] = await Promise.all([api('mantenimientos'), api('mantenimientos/proximos')]);
    mantenimientos = data.mantenimientos;
    responsables = data.responsables;
    recargarTabla(tabla, mantenimientos);
    pintarRecordatorios(recordatorios.data.mantenimientos);
    return recordatorios.data;
}

async function abrirFormulario(m = null) {
    editando = m;
    form.reset();
    limpiarErrores(form);
    document.getElementById('tituloMantenimiento').textContent = m ? 'Editar mantenimiento' : 'Nuevo mantenimiento';
    try {
        const { data: vehiculos } = await api('vehiculos');
        llenarSelect(form.elements.id_vehiculo, vehiculos, {
            valor: 'id_vehiculo', texto: (v) => `${v.placa} · ${v.marca} ${v.modelo}`, vacio: 'Selecciona un vehículo',
        });
        llenarSelect(form.elements.id_usuario, responsables, {
            valor: 'id_usuario', texto: 'nombre_usuario', vacio: 'Selecciona un responsable', seleccionado: APP.idUsuario,
        });
        form.elements.estado.disabled = Boolean(m);
        form.elements.id_vehiculo.disabled = m?.estado === 'En proceso';
        if (m) {
            form.elements.id_vehiculo.value = m.id_vehiculo;
            form.elements.id_usuario.value = m.id_usuario;
            form.elements.tipo_mantenimiento.value = m.tipo_mantenimiento;
            form.elements.descripcion.value = m.descripcion;
            form.elements.fecha_inicio.value = aInputFecha(m.fecha_inicio);
            form.elements.fecha_fin.value = aInputFecha(m.fecha_fin);
            form.elements.costo.value = m.costo ?? '';
            form.elements.observaciones.value = m.observaciones ?? '';
            if (![...form.elements.estado.options].some((o) => o.value === m.estado)) form.elements.estado.value = 'Programado';
            else form.elements.estado.value = m.estado;
        }
        modal.show();
    } catch (error) {
        mostrarError(error);
    }
}

document.getElementById('btnNuevo').addEventListener('click', () => abrirFormulario());

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const programado = (editando?.estado ?? form.elements.estado.value) === 'Programado';
    const datos = validarFormulario(form, {
        id_usuario: [V.req('Selecciona el usuario responsable.')],
        tipo_mantenimiento: [V.req('Selecciona el tipo de mantenimiento.')],
        descripcion: [V.req('La descripción es obligatoria.')],
        fecha_inicio: [V.req('La fecha de inicio es obligatoria.')],
        fecha_fin: [
            (v) => (programado && !v ? 'La fecha final es obligatoria en un mantenimiento programado.' : null),
            V.posteriorA('fecha_inicio', 'La fecha final debe ser posterior a la fecha de inicio.'),
        ],
        costo: [V.noNegativo('El costo no puede ser negativo.')],
    });
    if (!datos) return;
    // Un select deshabilitado no viaja en el formulario.
    datos.id_vehiculo = form.elements.id_vehiculo.value;
    if (!datos.id_vehiculo) {
        marcarCampo(form, 'id_vehiculo', 'Selecciona un vehículo.');
        return;
    }
    await conBotonOcupado(form, async () => {
        try {
            const r = editando
                ? await api(`mantenimientos/${editando.id_mantenimiento}`, { metodo: 'PUT', datos })
                : await api('mantenimientos', { metodo: 'POST', datos });
            modal.hide();
            exito(r.message);
            await cargar();
        } catch (error) {
            mostrarError(error, form);
        }
    });
});

formFinalizar.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = validarFormulario(formFinalizar, {
        fecha_fin: [V.req('La fecha de término es obligatoria.')],
        costo: [V.noNegativo('El costo no puede ser negativo.')],
    });
    if (!datos) return;
    await conBotonOcupado(formFinalizar, async () => {
        try {
            const r = await api(`mantenimientos/${idFinalizar}/estado`, { metodo: 'PATCH', datos: { ...datos, estado: 'Finalizado' } });
            modalFinalizar.hide();
            exito(r.message);
            await cargar();
        } catch (error) {
            mostrarError(error, formFinalizar);
        }
    });
});

async function cambiarEstado(id, estado) {
    try {
        const r = await api(`mantenimientos/${id}/estado`, { metodo: 'PATCH', datos: { estado } });
        exito(r.message);
        await cargar();
    } catch (error) {
        mostrarError(error);
    }
}

const buscar = (id) => mantenimientos.find((m) => Number(m.id_mantenimiento) === id);

accionesTabla('#tabla', {
    editar: (id) => abrirFormulario(buscar(id)),
    iniciar: async (id) => {
        const m = buscar(id);
        const ok = await confirmar({ titulo: '¿Iniciar el mantenimiento?', texto: `${m.vehiculo} pasará a estado Mantenimiento.`, boton: 'Iniciar' });
        if (ok) cambiarEstado(id, 'En proceso');
    },
    finalizar: (id) => {
        const m = buscar(id);
        idFinalizar = id;
        formFinalizar.reset();
        limpiarErrores(formFinalizar);
        formFinalizar.elements.fecha_fin.value = dateAInput(new Date());
        formFinalizar.elements.costo.value = m.costo ?? '';
        formFinalizar.elements.observaciones.value = m.observaciones ?? '';
        modalFinalizar.show();
    },
    cancelar: async (id) => {
        const m = buscar(id);
        const ok = await confirmar({ titulo: '¿Cancelar el mantenimiento?', texto: m.vehiculo, boton: 'Cancelar mantenimiento', color: '#c0392b', icono: 'warning' });
        if (ok) cambiarEstado(id, 'Cancelado');
    },
});

cargar()
    .then((r) => {
        const n = r.mantenimientos.length;
        if (n) aviso('info', 'Mantenimientos próximos', `${n} mantenimiento(s) atrasados o programados para los próximos ${r.dias_aviso} días.`);
    })
    .catch((e) => mostrarError(e));
