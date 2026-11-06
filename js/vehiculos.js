'use strict';

const modalDetalle = new bootstrap.Modal('#modalDetalle');
const form = document.getElementById('formVehiculo');
const modal = form ? new bootstrap.Modal('#modalVehiculo') : null;
const ANIO_MAX = new Date().getFullYear() + 1;
let vehiculos = [];
let editando = null;

const columnas = [
    { data: 'placa', title: 'Placa' },
    { data: 'marca', title: 'Marca' },
    { data: 'modelo', title: 'Modelo' },
    { data: 'anio', title: 'Año' },
    { data: 'tipo_vehiculo', title: 'Tipo' },
    { data: 'color', title: 'Color' },
    { data: 'capacidad_carga', title: 'Capacidad (kg)', className: 'text-end', render: (d, t) => (t === 'display' ? (d === null ? '—' : Number(d).toLocaleString('es-MX')) : d) },
    { data: 'kilometraje', title: 'Kilometraje', className: 'text-end', render: (d, t) => (t === 'display' ? Number(d).toLocaleString('es-MX') : d) },
    columnaEstado('estado'),
    {
        data: null, title: 'Acciones', orderable: false, searchable: false, className: 'acciones',
        render: (_, __, v) => {
            let html = boton('ver', 'bi-eye', 'Ver detalles', v.id_vehiculo);
            if (APP.esAdmin) {
                html += boton('editar', 'bi-pencil', 'Editar', v.id_vehiculo, 'primary')
                    + boton('estado', 'bi-arrow-repeat', 'Cambiar estado', v.id_vehiculo, 'warning');
                if (v.estado !== 'Fuera de servicio') {
                    html += boton('retirar', 'bi-slash-circle', 'Retirar del servicio', v.id_vehiculo, 'danger');
                }
                html += boton('eliminar', 'bi-trash', 'Eliminar (solo sin historial)', v.id_vehiculo, 'danger');
            }
            return html;
        },
    },
];
const tabla = crearTabla('#tabla', columnas);

async function cargar() {
    const { data } = await api('vehiculos');
    vehiculos = data;
    recargarTabla(tabla, vehiculos);
}

function abrirFormulario(vehiculo = null) {
    editando = vehiculo;
    form.reset();
    limpiarErrores(form);
    document.getElementById('tituloVehiculo').textContent = vehiculo ? 'Editar vehículo' : 'Nuevo vehículo';
    // El estado tiene reglas propias: al editar se cambia con su botón.
    document.getElementById('grupoEstado').classList.toggle('d-none', Boolean(vehiculo));
    if (vehiculo) {
        ['placa', 'marca', 'modelo', 'anio', 'tipo_vehiculo', 'color', 'capacidad_carga', 'kilometraje', 'observaciones'].forEach((c) => {
            form.elements[c].value = vehiculo[c] ?? '';
        });
    }
    modal.show();
}

form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = validarFormulario(form, {
        placa: [V.req('La placa es obligatoria.')],
        marca: [V.req('La marca es obligatoria.')],
        modelo: [V.req('El modelo es obligatorio.')],
        anio: [V.req('El año es obligatorio.'), V.entre(1950, ANIO_MAX, `El año debe estar entre 1950 y ${ANIO_MAX}.`)],
        tipo_vehiculo: [V.req('El tipo de vehículo es obligatorio.')],
        capacidad_carga: [V.noNegativo('La capacidad de carga no puede ser negativa.')],
        kilometraje: [V.noNegativo('El kilometraje no puede ser negativo.')],
    });
    if (!datos) return;
    await conBotonOcupado(form, async () => {
        try {
            const r = editando
                ? await api(`vehiculos/${editando.id_vehiculo}`, { metodo: 'PUT', datos })
                : await api('vehiculos', { metodo: 'POST', datos });
            modal.hide();
            exito(r.message);
            await cargar();
        } catch (error) {
            mostrarError(error, form);
        }
    });
});

async function enviarEstado(id, estado) {
    try {
        const r = await api(`vehiculos/${id}/estado`, { metodo: 'PATCH', datos: { estado } });
        exito(r.message);
        await cargar();
    } catch (error) {
        mostrarError(error);
    }
}

const buscar = (id) => vehiculos.find((v) => Number(v.id_vehiculo) === id);

accionesTabla('#tabla', {
    ver: async (id) => {
        try {
            const { data: v } = await api(`vehiculos/${id}`);
            document.getElementById('datosVehiculo').innerHTML = listaDetalle([
                ['Placa', v.placa], ['Marca', v.marca], ['Modelo', v.modelo], ['Año', v.anio],
                ['Tipo', v.tipo_vehiculo], ['Color', v.color],
                ['Capacidad de carga (kg)', v.capacidad_carga], ['Kilometraje', v.kilometraje],
                ['Estado', etiqueta(v.estado), true],
                ...(APP.esAdmin ? [['Observaciones', v.observaciones]] : []),
            ]);
            if (APP.esAdmin) {
                const [reservas, mantenimientos] = await Promise.all([
                    api(`vehiculos/${id}/reservas`), api(`vehiculos/${id}/mantenimientos`),
                ]);
                document.getElementById('histReservas').innerHTML = reservas.data.map((r) => `<tr>
                    <td class="text-nowrap">${esc(r.codigo_reserva)}</td><td>${esc(r.empleado)}</td>
                    <td class="text-nowrap">${fechaHora(r.fecha_inicio)}</td><td class="text-nowrap">${fechaHora(r.fecha_fin)}</td>
                    <td class="text-nowrap">${fechaHora(r.fecha_devolucion)}</td><td>${etiqueta(r.estado)}</td></tr>`).join('')
                    || '<tr><td colspan="6" class="text-secondary">Sin reservas registradas.</td></tr>';
                document.getElementById('histMantenimientos').innerHTML = mantenimientos.data.map((m) => `<tr>
                    <td>${esc(m.tipo_mantenimiento)}</td><td>${esc(m.descripcion)}</td>
                    <td class="text-nowrap">${fechaHora(m.fecha_inicio)}</td><td class="text-nowrap">${fechaHora(m.fecha_fin)}</td>
                    <td>${moneda(m.costo)}</td><td>${etiqueta(m.estado)}</td></tr>`).join('')
                    || '<tr><td colspan="6" class="text-secondary">Sin mantenimientos registrados.</td></tr>';
            }
            modalDetalle.show();
        } catch (error) {
            mostrarError(error);
        }
    },
    editar: (id) => abrirFormulario(buscar(id)),
    estado: async (id) => {
        const v = buscar(id);
        const { value } = await Swal.fire({
            title: `Estado de ${v.placa}`,
            input: 'select',
            inputOptions: { Disponible: 'Disponible', Mantenimiento: 'Mantenimiento', 'Fuera de servicio': 'Fuera de servicio' },
            inputValue: v.estado,
            showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Volver', confirmButtonColor: '#1f5fbf',
        });
        if (value && value !== v.estado) enviarEstado(id, value);
    },
    retirar: async (id) => {
        const v = buscar(id);
        const ok = await confirmar({
            titulo: `¿Retirar ${v.placa} del servicio?`,
            texto: 'Dejará de poder reservarse. Su historial se conserva.',
            boton: 'Retirar', color: '#c0392b', icono: 'warning',
        });
        if (ok) enviarEstado(id, 'Fuera de servicio');
    },
    eliminar: async (id) => {
        const v = buscar(id);
        const ok = await confirmar({
            titulo: `¿Eliminar ${v.placa}?`,
            texto: 'Solo es posible si no tiene reservas ni mantenimientos. Esta acción no se puede deshacer.',
            boton: 'Eliminar', color: '#c0392b', icono: 'warning',
        });
        if (!ok) return;
        try {
            const r = await api(`vehiculos/${id}`, { metodo: 'DELETE' });
            exito(r.message);
            await cargar();
        } catch (error) {
            mostrarError(error);
        }
    },
});

document.getElementById('btnNuevo')?.addEventListener('click', () => abrirFormulario());

cargar().catch((e) => mostrarError(e));
