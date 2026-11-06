'use strict';

const form = document.getElementById('formUsuario');
const formClave = document.getElementById('formContrasena');
const modal = new bootstrap.Modal('#modalUsuario');
const modalClave = new bootstrap.Modal('#modalContrasena');
let usuarios = [];
let editando = null;
let idClave = null;

const tabla = crearTabla('#tabla', [
    { data: 'nombre_usuario', title: 'Usuario' },
    { data: 'correo', title: 'Correo' },
    { data: 'nombre_rol', title: 'Rol' },
    columnaEstado('estado'),
    { data: 'fecha_registro', title: 'Registro', render: (d, t) => (t === 'display' ? fechaCorta(d) : d) },
    {
        data: null, title: 'Acciones', orderable: false, searchable: false, className: 'acciones',
        render: (_, __, u) => boton('editar', 'bi-pencil', 'Editar', u.id_usuario, 'primary')
            + boton('clave', 'bi-key', 'Actualizar contraseña', u.id_usuario)
            + (u.estado === 'Activo'
                ? boton('desactivar', 'bi-person-slash', 'Desactivar', u.id_usuario, 'danger')
                : boton('activar', 'bi-person-check', 'Activar', u.id_usuario, 'success')),
    },
]);

async function cargar() {
    const { data } = await api('usuarios');
    usuarios = data;
    recargarTabla(tabla, usuarios);
}

async function cargarRoles() {
    const { data } = await api('usuarios/roles');
    llenarSelect(document.getElementById('id_rol'), data, { valor: 'id_rol', texto: 'nombre_rol', vacio: 'Selecciona un rol' });
}

function abrirFormulario(usuario = null) {
    editando = usuario;
    form.reset();
    limpiarErrores(form);
    document.getElementById('tituloUsuario').textContent = usuario ? 'Editar usuario' : 'Nuevo usuario';
    document.getElementById('grupoContrasena').classList.toggle('d-none', Boolean(usuario));
    if (usuario) {
        form.elements.nombre_usuario.value = usuario.nombre_usuario;
        form.elements.correo.value = usuario.correo;
        form.elements.id_rol.value = usuario.id_rol;
    }
    modal.show();
}

document.getElementById('btnNuevo').addEventListener('click', () => abrirFormulario());

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const reglas = {
        nombre_usuario: [V.req('El nombre de usuario es obligatorio.'), V.usuario()],
        correo: [V.req('El correo electrónico es obligatorio.'), V.correo()],
        id_rol: [V.req('Selecciona un rol.')],
    };
    if (!editando) reglas.contrasena = [V.req('La contraseña es obligatoria.'), V.contrasena()];
    const datos = validarFormulario(form, reglas);
    if (!datos) return;
    await conBotonOcupado(form, async () => {
        try {
            const r = editando
                ? await api(`usuarios/${editando.id_usuario}`, { metodo: 'PUT', datos })
                : await api('usuarios', { metodo: 'POST', datos });
            modal.hide();
            exito(r.message);
            await cargar();
        } catch (error) {
            mostrarError(error, form);
        }
    });
});

formClave.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = validarFormulario(formClave, {
        contrasena: [V.req('La contraseña es obligatoria.'), V.contrasena()],
        confirmacion: [V.igualA('contrasena', 'Las contraseñas no coinciden.')],
    });
    if (!datos) return;
    await conBotonOcupado(formClave, async () => {
        try {
            const r = await api(`usuarios/${idClave}/contrasena`, { metodo: 'PATCH', datos: { contrasena: datos.contrasena } });
            modalClave.hide();
            exito(r.message);
        } catch (error) {
            mostrarError(error, formClave);
        }
    });
});

async function cambiarEstado(id, estado) {
    const u = usuarios.find((x) => Number(x.id_usuario) === id);
    if (estado === 'Inactivo') {
        const propio = String(id) === APP.idUsuario;
        const ok = await confirmar({
            titulo: `¿Desactivar a ${u.nombre_usuario}?`,
            texto: propio ? 'Es tu propia cuenta: perderás el acceso de inmediato.' : 'No podrá iniciar sesión hasta que se reactive.',
            boton: 'Desactivar', color: '#c0392b', icono: 'warning',
        });
        if (!ok) return;
    }
    try {
        const r = await api(`usuarios/${id}/estado`, { metodo: 'PATCH', datos: { estado } });
        exito(r.message);
        await cargar();
    } catch (error) {
        mostrarError(error);
    }
}

accionesTabla('#tabla', {
    editar: (id) => abrirFormulario(usuarios.find((u) => Number(u.id_usuario) === id)),
    clave: (id) => {
        idClave = id;
        formClave.reset();
        limpiarErrores(formClave);
        modalClave.show();
    },
    desactivar: (id) => cambiarEstado(id, 'Inactivo'),
    activar: (id) => cambiarEstado(id, 'Activo'),
});

Promise.all([cargar(), cargarRoles()]).catch((e) => mostrarError(e));
