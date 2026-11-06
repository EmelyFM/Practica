'use strict';

const form = document.getElementById('formEmpleado');
const modal = new bootstrap.Modal('#modalEmpleado');
const modalDetalle = new bootstrap.Modal('#modalDetalle');
let empleados = [];
let editando = null;

const tabla = crearTabla('#tabla', [
    { data: 'nombre_completo', title: 'Empleado' },
    { data: 'nombre_usuario', title: 'Usuario' },
    { data: 'puesto', title: 'Puesto' },
    { data: 'telefono', title: 'Teléfono' },
    { data: 'fecha_ingreso', title: 'Ingreso', render: (d, t) => (t === 'display' ? fechaCorta(d) : d) },
    columnaEstado('estado'),
    {
        data: null, title: 'Acciones', orderable: false, searchable: false, className: 'acciones',
        render: (_, __, x) => boton('ver', 'bi-eye', 'Ver detalles', x.id_empleado)
            + boton('editar', 'bi-pencil', 'Editar', x.id_empleado, 'primary')
            + (x.estado === 'Activo'
                ? boton('desactivar', 'bi-person-slash', 'Desactivar', x.id_empleado, 'danger')
                : boton('activar', 'bi-person-check', 'Activar', x.id_empleado, 'success')),
    },
]);

async function cargar() {
    const { data } = await api('empleados');
    empleados = data;
    recargarTabla(tabla, empleados);
}

async function abrirFormulario(empleado = null) {
    editando = empleado;
    form.reset();
    limpiarErrores(form);
    document.getElementById('tituloEmpleado').textContent = empleado ? 'Editar empleado' : 'Nuevo empleado';
    try {
        const { data } = await api('empleados/usuarios-disponibles', { parametros: { id_empleado: empleado?.id_empleado } });
        llenarSelect(form.elements.id_usuario, data, {
            valor: 'id_usuario', texto: (u) => `${u.nombre_usuario} (${u.correo})`, vacio: 'Selecciona un usuario',
        });
        if (empleado) {
            ['id_usuario', 'nombre', 'apellido_paterno', 'apellido_materno', 'telefono', 'puesto', 'fecha_ingreso'].forEach((c) => {
                form.elements[c].value = empleado[c] ?? '';
            });
        } else if (!data.length) {
            aviso('info', 'No hay usuarios libres', 'Registra primero un usuario en el módulo de Usuarios para vincularlo al empleado.');
            return;
        }
        modal.show();
    } catch (error) {
        mostrarError(error);
    }
}

document.getElementById('btnNuevo').addEventListener('click', () => abrirFormulario());

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = validarFormulario(form, {
        id_usuario: [V.req('Selecciona el usuario relacionado.')],
        nombre: [V.req('El nombre es obligatorio.'), V.nombre('El nombre solo puede contener letras.')],
        apellido_paterno: [V.req('El apellido paterno es obligatorio.'), V.nombre('El apellido solo puede contener letras.')],
        apellido_materno: [V.nombre('El apellido solo puede contener letras.')],
        telefono: [V.telefono()],
    });
    if (!datos) return;
    await conBotonOcupado(form, async () => {
        try {
            const r = editando
                ? await api(`empleados/${editando.id_empleado}`, { metodo: 'PUT', datos })
                : await api('empleados', { metodo: 'POST', datos });
            modal.hide();
            exito(r.message);
            await cargar();
        } catch (error) {
            mostrarError(error, form);
        }
    });
});

async function cambiarEstado(id, estado) {
    const x = empleados.find((m) => Number(m.id_empleado) === id);
    if (estado === 'Inactivo') {
        const ok = await confirmar({
            titulo: `¿Desactivar a ${x.nombre_completo}?`,
            texto: 'No podrá recibir ni registrar nuevas reservas.',
            boton: 'Desactivar', color: '#c0392b', icono: 'warning',
        });
        if (!ok) return;
    }
    try {
        const r = await api(`empleados/${id}/estado`, { metodo: 'PATCH', datos: { estado } });
        exito(r.message);
        await cargar();
    } catch (error) {
        mostrarError(error);
    }
}

accionesTabla('#tabla', {
    ver: async (id) => {
        try {
            const { data: x } = await api(`empleados/${id}`);
            document.getElementById('cuerpoDetalle').innerHTML = listaDetalle([
                ['Nombre completo', x.nombre_completo],
                ['Usuario', x.nombre_usuario],
                ['Correo', x.correo],
                ['Teléfono', x.telefono],
                ['Puesto', x.puesto],
                ['Fecha de ingreso', fechaCorta(x.fecha_ingreso)],
                ['Estado', etiqueta(x.estado), true],
                ['Estado del usuario', etiqueta(x.estado_usuario), true],
            ]);
            modalDetalle.show();
        } catch (error) {
            mostrarError(error);
        }
    },
    editar: (id) => abrirFormulario(empleados.find((m) => Number(m.id_empleado) === id)),
    desactivar: (id) => cambiarEstado(id, 'Inactivo'),
    activar: (id) => cambiarEstado(id, 'Activo'),
});

cargar().catch((e) => mostrarError(e));
