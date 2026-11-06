'use strict';
/* Reservas, devoluciones y disponibilidad comparten este módulo y el formulario de reserva. */

const formReserva = document.getElementById('formReserva');
const modalReserva = new bootstrap.Modal('#modalReserva');
let reservaEditando = null;
let despuesDeGuardar = () => {};

/* ---------- Formulario de reserva ---------- */

async function cargarVehiculosFormulario(seleccionado = '') {
    const select = formReserva.elements.id_vehiculo;
    const inicio = formReserva.elements.fecha_inicio.value;
    const fin = formReserva.elements.fecha_fin.value;
    if (!inicio || !fin || fin <= inicio) {
        llenarSelect(select, [], { vacio: 'Selecciona primero el periodo' });
        return;
    }
    try {
        const { data } = await api('reservas/disponibilidad', {
            parametros: { fecha_inicio: inicio, fecha_fin: fin, excluir: reservaEditando?.id_reserva },
        });
        const libres = data.vehiculos.filter((v) => v.disponible);
        const conservar = seleccionado || select.value;
        llenarSelect(select, libres, {
            valor: 'id_vehiculo', texto: (v) => `${v.placa} · ${v.marca} ${v.modelo} (${v.tipo_vehiculo})`,
            vacio: libres.length ? 'Selecciona un vehículo' : 'No hay vehículos disponibles en este periodo',
        });
        if (libres.some((v) => String(v.id_vehiculo) === String(conservar))) select.value = conservar;
    } catch (error) {
        llenarSelect(select, [], { vacio: 'Selecciona primero el periodo' });
        mostrarError(error, formReserva);
    }
}

async function abrirReserva({ reserva = null, inicio = '', fin = '', idVehiculo = '' } = {}) {
    reservaEditando = reserva;
    formReserva.reset();
    limpiarErrores(formReserva);
    document.getElementById('tituloReserva').textContent = reserva ? 'Editar reserva' : 'Nueva reserva';
    document.getElementById('codigoReserva').value = reserva?.codigo_reserva ?? 'Se genera automáticamente';

    const estado = formReserva.elements.estado;
    if (estado) {
        // El estado de una reserva existente solo cambia con sus acciones (confirmar, cancelar…).
        estado.disabled = Boolean(reserva) || !APP.esAdmin;
        estado.value = reserva?.estado === 'Confirmada' && APP.esAdmin ? 'Confirmada' : 'Pendiente';
    }
    try {
        if (APP.esAdmin) {
            const { data } = await api('empleados', { parametros: { asignables: 1 } });
            llenarSelect(formReserva.elements.id_empleado, data, {
                valor: 'id_empleado', texto: (x) => `${x.nombre_completo}${x.puesto ? ` · ${x.puesto}` : ''}`,
                vacio: data.length ? 'Selecciona un empleado' : 'No hay empleados activos',
                seleccionado: reserva?.id_empleado ?? '',
            });
        }
        formReserva.elements.fecha_inicio.value = reserva ? aInputFecha(reserva.fecha_inicio) : inicio;
        formReserva.elements.fecha_fin.value = reserva ? aInputFecha(reserva.fecha_fin) : fin;
        formReserva.elements.motivo.value = reserva?.motivo ?? '';
        formReserva.elements.observaciones.value = reserva?.observaciones ?? '';
        await cargarVehiculosFormulario(reserva?.id_vehiculo ?? idVehiculo);
        modalReserva.show();
    } catch (error) {
        mostrarError(error);
    }
}

['fecha_inicio', 'fecha_fin'].forEach((c) => {
    formReserva.elements[c].addEventListener('change', () => cargarVehiculosFormulario());
});

formReserva.addEventListener('submit', async (e) => {
    e.preventDefault();
    const reglas = {
        fecha_inicio: [V.req('La fecha de inicio es obligatoria.')],
        fecha_fin: [V.req('La fecha final es obligatoria.'), V.posteriorA('fecha_inicio', 'La fecha final debe ser posterior a la fecha de inicio.')],
        id_vehiculo: [V.req('Selecciona un vehículo.')],
        motivo: [V.req('El motivo es obligatorio.')],
    };
    if (APP.esAdmin) reglas.id_empleado = [V.req('Selecciona el empleado responsable.')];
    const datos = validarFormulario(formReserva, reglas);
    if (!datos) return;
    await conBotonOcupado(formReserva, async () => {
        try {
            const r = reservaEditando
                ? await api(`reservas/${reservaEditando.id_reserva}`, { metodo: 'PUT', datos })
                : await api('reservas', { metodo: 'POST', datos });
            modalReserva.hide();
            await aviso('success', r.message, r.data?.codigo_reserva ? `Código: ${r.data.codigo_reserva}` : '');
            await despuesDeGuardar();
        } catch (error) {
            if (error.status === 409) {
                aviso('warning', 'Vehículo no disponible', error.message);
                cargarVehiculosFormulario();
            } else {
                mostrarError(error, formReserva);
            }
        }
    });
});

/* ---------- Consulta de disponibilidad ---------- */

function iniciarDisponibilidad() {
    const form = document.getElementById('formDisponibilidad');
    const panel = document.getElementById('panelResultados');
    const contenedor = document.getElementById('resultados');
    const verNoDisponibles = document.getElementById('verNoDisponibles');
    const puedeReservar = APP.esAdmin || APP.idEmpleado !== '';
    let ultimo = null;

    const pintar = () => {
        if (!ultimo) return;
        const lista = ultimo.vehiculos.filter((v) => v.disponible || verNoDisponibles.checked);
        document.getElementById('tituloResultados').textContent = `${ultimo.disponibles} de ${ultimo.vehiculos.length} vehículos disponibles`;
        contenedor.innerHTML = lista.map((v) => `
            <article class="vehiculo-tarjeta ${v.disponible ? '' : 'no-disponible'}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h3>${esc(v.marca)} ${esc(v.modelo)}</h3>
                    <span class="vehiculo-placa">${esc(v.placa)}</span>
                </div>
                <p class="vehiculo-datos">${esc(v.tipo_vehiculo)} · ${esc(v.anio)}${v.color ? ` · ${esc(v.color)}` : ''}${v.capacidad_carga !== null ? `<br>Capacidad: ${Number(v.capacidad_carga).toLocaleString('es-MX')} kg` : ''}</p>
                ${v.disponible
                    ? (puedeReservar ? `<button type="button" class="btn btn-sm btn-primary" data-reservar="${esc(v.id_vehiculo)}">Reservar</button>` : '<span class="estado estado-verde">Disponible</span>')
                    : `<span class="vehiculo-motivo"><i class="bi bi-x-circle text-danger" aria-hidden="true"></i> ${esc(v.motivo)}</span>`}
            </article>`).join('') || '<p class="text-secondary mb-0">Ningún vehículo está disponible en este periodo.</p>';
    };

    const consultar = async () => {
        const reglas = {
            fecha_inicio: [V.req('La fecha de inicio es obligatoria.')],
            fecha_fin: [V.req('La fecha final es obligatoria.'), V.posteriorA('fecha_inicio', 'La fecha final debe ser posterior a la fecha de inicio.')],
        };
        const datos = validarFormulario(form, reglas);
        if (!datos) return;
        try {
            const { data } = await api('reservas/disponibilidad', { parametros: datos });
            ultimo = { ...data, ...datos };
            panel.classList.remove('d-none');
            pintar();
        } catch (error) {
            panel.classList.add('d-none');
            mostrarError(error, form);
        }
    };

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        consultar();
    });
    verNoDisponibles.addEventListener('change', pintar);
    contenedor.addEventListener('click', (e) => {
        const b = e.target.closest('[data-reservar]');
        if (b) abrirReserva({ inicio: ultimo.fecha_inicio, fin: ultimo.fecha_fin, idVehiculo: b.dataset.reservar });
    });
    despuesDeGuardar = consultar;

    const desde = new Date();
    desde.setMinutes(0, 0, 0);
    desde.setHours(desde.getHours() + 1);
    form.elements.fecha_inicio.value = dateAInput(desde);
    form.elements.fecha_fin.value = dateAInput(new Date(desde.getTime() + 8 * 3600 * 1000));
    consultar();
}

/* ---------- Listado de reservas y devoluciones ---------- */

function iniciarListado() {
    const modalDevolucion = new bootstrap.Modal('#modalDevolucion');
    const modalDetalle = new bootstrap.Modal('#modalDetalle');
    const formDevolucion = document.getElementById('formDevolucion');
    let reservas = [];
    let idDevolucion = null;

    const accionesDe = (r) => {
        const propia = APP.esAdmin || String(r.id_empleado) === APP.idEmpleado;
        const id = r.id_reserva;
        let html = boton('ver', 'bi-eye', 'Ver', id);
        if (r.estado === 'Pendiente' || (APP.esAdmin && r.estado === 'Confirmada')) {
            html += boton('editar', 'bi-pencil', 'Editar', id, 'primary');
        }
        if (APP.esAdmin && r.estado === 'Pendiente') html += boton('confirmar', 'bi-check-lg', 'Confirmar', id, 'success');
        if (propia && r.estado === 'Confirmada') html += boton('iniciar', 'bi-play-fill', 'Iniciar uso', id, 'primary');
        if (propia && r.estado === 'En uso') html += boton('devolver', 'bi-arrow-return-left', 'Registrar devolución', id, 'success');
        if (propia && ['Pendiente', 'Confirmada'].includes(r.estado)) html += boton('cancelar', 'bi-x-lg', 'Cancelar', id, 'danger');
        return html;
    };

    const tabla = crearTabla('#tablaReservas', [
        { data: 'codigo_reserva', title: 'Código', className: 'text-nowrap' },
        { data: 'vehiculo', title: 'Vehículo' },
        { data: 'empleado', title: 'Empleado' },
        columnaFecha('fecha_inicio', 'Inicio'),
        columnaFecha('fecha_fin', 'Fin'),
        columnaEstado('estado'),
        {
            data: 'estado_devolucion', title: 'Devolución',
            render: (d, t, r) => (t === 'display' ? etiquetaDevolucion(r) : d),
        },
        { data: null, title: 'Acciones', orderable: false, searchable: false, className: 'acciones', render: (_, __, r) => accionesDe(r) },
    ]);

    const cargar = async () => {
        const { data } = await api('reservas', { parametros: { devoluciones: APP.pagina === 'devoluciones' ? 1 : null } });
        reservas = data;
        recargarTabla(tabla, reservas);
    };
    despuesDeGuardar = cargar;
    const buscar = (id) => reservas.find((r) => Number(r.id_reserva) === id);

    const accionSimple = async (ruta, datos, tituloOk) => {
        try {
            await api(ruta, { metodo: 'PATCH', datos });
            await aviso('success', tituloOk);
            await cargar();
        } catch (error) {
            if (error.status === 409) aviso('warning', 'No se pudo completar', error.message);
            else mostrarError(error);
        }
    };

    accionesTabla('#tablaReservas', {
        ver: async (id) => {
            try {
                const { data: r } = await api(`reservas/${id}`);
                document.getElementById('cuerpoDetalle').innerHTML = listaDetalle([
                    ['Código', r.codigo_reserva],
                    ['Estado', etiqueta(r.estado), true],
                    ['Vehículo', r.vehiculo],
                    ['Empleado responsable', r.empleado],
                    ['Inicio reservado', fechaHora(r.fecha_inicio)],
                    ['Fin reservado', fechaHora(r.fecha_fin)],
                    ['Devolución real', fechaHora(r.fecha_devolucion)],
                    ['Estado de devolución', etiquetaDevolucion(r), true],
                    ['Registrada por', r.registrado_por],
                    ['Fecha de registro', fechaHora(r.fecha_registro)],
                    ['Motivo', r.motivo],
                    ['Observaciones', r.observaciones],
                ]);
                modalDetalle.show();
            } catch (error) {
                mostrarError(error);
            }
        },
        editar: (id) => abrirReserva({ reserva: buscar(id) }),
        confirmar: async (id) => {
            const r = buscar(id);
            if (await confirmar({ titulo: `¿Confirmar ${r.codigo_reserva}?`, texto: `${r.vehiculo} para ${r.empleado}.`, boton: 'Confirmar', color: '#198754' })) {
                accionSimple(`reservas/${id}/confirmar`, {}, 'Reserva confirmada');
            }
        },
        iniciar: async (id) => {
            const r = buscar(id);
            if (await confirmar({ titulo: `¿Iniciar ${r.codigo_reserva}?`, texto: `Se registrará la entrega de ${r.vehiculo}.`, boton: 'Iniciar uso' })) {
                accionSimple(`reservas/${id}/iniciar`, {}, 'Uso del vehículo iniciado');
            }
        },
        cancelar: async (id) => {
            const r = buscar(id);
            const res = await Swal.fire({
                icon: 'warning', title: `¿Cancelar ${r.codigo_reserva}?`, text: 'El vehículo volverá a estar libre en ese periodo.',
                input: 'text', inputLabel: 'Motivo de la cancelación (opcional)', inputAttributes: { maxlength: 250 },
                showCancelButton: true, confirmButtonText: 'Cancelar reserva', cancelButtonText: 'Volver',
                confirmButtonColor: '#c0392b', cancelButtonColor: '#6c757d', reverseButtons: true, focusCancel: true,
            });
            if (res.isConfirmed) accionSimple(`reservas/${id}/cancelar`, { motivo: res.value }, 'Reserva cancelada');
        },
        devolver: (id) => {
            const r = buscar(id);
            idDevolucion = id;
            formDevolucion.reset();
            limpiarErrores(formDevolucion);
            formDevolucion.elements.fecha_devolucion.value = dateAInput(new Date());
            document.getElementById('resumenDevolucion').textContent = `${r.codigo_reserva} · ${r.vehiculo}. Fin reservado: ${fechaHora(r.fecha_fin)}.`;
            modalDevolucion.show();
        },
    });

    formDevolucion.addEventListener('submit', async (e) => {
        e.preventDefault();
        const datos = validarFormulario(formDevolucion, {
            fecha_devolucion: [V.req('La fecha de devolución es obligatoria.')],
            estado_devolucion: [V.req('Selecciona el estado de devolución.')],
            observaciones: [(v, d) => (d.estado_devolucion === 'Con observaciones' && !v ? 'Describe las observaciones de la devolución.' : null)],
        });
        if (!datos) return;
        await conBotonOcupado(formDevolucion, async () => {
            try {
                const r = await api(`reservas/${idDevolucion}/devolver`, { metodo: 'PATCH', datos });
                modalDevolucion.hide();
                await aviso('success', r.message);
                await cargar();
            } catch (error) {
                mostrarError(error, formDevolucion);
            }
        });
    });

    document.getElementById('btnNuevaReserva')?.addEventListener('click', () => abrirReserva());
    cargar().catch((e) => mostrarError(e));
    if (APP.pagina === 'nueva-reserva' && document.getElementById('btnNuevaReserva')) abrirReserva();
}

if (document.getElementById('formDisponibilidad')) {
    iniciarDisponibilidad();
} else {
    iniciarListado();
}
