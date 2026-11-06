'use strict';
/* Utilidades comunes: cliente Fetch, alertas, validación, tablas y formato. */

const meta = (nombre) => document.querySelector(`meta[name="${nombre}"]`)?.content ?? '';

const APP = {
    base: meta('base-url'),
    csrf: meta('csrf-token'),
    rol: document.body.dataset.rol ?? '',
    pagina: document.body.dataset.pagina ?? '',
    idEmpleado: document.body.dataset.idEmpleado ?? '',
    idUsuario: document.body.dataset.idUsuario ?? '',
    get esAdmin() { return this.rol === 'Administrador'; },
};

/* ---------- Fetch API ---------- */

class ApiError extends Error {
    constructor(message, status, errors) {
        super(message);
        this.status = status;
        this.errors = errors ?? null;
    }
}

function urlApi(ruta, parametros = {}) {
    const q = new URLSearchParams({ ruta });
    Object.entries(parametros).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') q.append(k, v);
    });
    return `${APP.base}/api/index.php?${q}`;
}

async function peticion(ruta, { metodo = 'GET', datos, parametros } = {}) {
    const opciones = { method: metodo, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (metodo !== 'GET') {
        opciones.headers['X-CSRF-Token'] = APP.csrf;
        opciones.headers['Content-Type'] = 'application/json';
        opciones.body = JSON.stringify(datos ?? {});
    }
    try {
        return await fetch(urlApi(ruta, parametros), opciones);
    } catch {
        throw new ApiError('No se pudo conectar con el servidor. Revisa tu conexión.', 0);
    }
}

async function errorDeRespuesta(respuesta) {
    let cuerpo = {};
    try { cuerpo = await respuesta.json(); } catch { /* cuerpo no JSON: se usa el mensaje genérico */ }
    return new ApiError(cuerpo.message ?? 'Ocurrió un error inesperado.', respuesta.status, cuerpo.errors);
}

/** Devuelve el JSON de la respuesta; si no es correcta lanza ApiError. */
async function api(ruta, opciones = {}) {
    const respuesta = await peticion(ruta, opciones);
    if (!respuesta.ok) {
        const error = await errorDeRespuesta(respuesta);
        if (respuesta.status === 401 && !ruta.startsWith('auth/')) {
            await Swal.fire({ icon: 'info', title: 'Sesión finalizada', text: error.message, confirmButtonColor: '#1f5fbf' });
            location.href = `${APP.base}/index.php`;
        }
        throw error;
    }
    try {
        return await respuesta.json();
    } catch {
        throw new ApiError('El servidor envió una respuesta no válida.', respuesta.status);
    }
}

/* ---------- Alertas ---------- */

const aviso = (icono, titulo, texto = '') => Swal.fire({ icon: icono, title: titulo, text: texto, confirmButtonColor: '#1f5fbf' });

const exito = (titulo) => Swal.fire({
    icon: 'success', title: titulo, toast: true, position: 'top-end',
    showConfirmButton: false, timer: 2600, timerProgressBar: true,
});

async function confirmar({ titulo, texto, boton = 'Confirmar', color = '#1f5fbf', icono = 'question' }) {
    const r = await Swal.fire({
        icon: icono, title: titulo, text: texto, showCancelButton: true,
        confirmButtonText: boton, cancelButtonText: 'Volver', confirmButtonColor: color, cancelButtonColor: '#6c757d',
        reverseButtons: true, focusCancel: true,
    });
    return r.isConfirmed;
}

/** Muestra un error de la API; si trae errores por campo los marca en el formulario. */
function mostrarError(error, formulario = null) {
    if (error.status === 401) return;
    if (error.errors && formulario) {
        marcarErrores(formulario, error.errors);
    }
    if (error.status === 422) {
        return aviso('warning', 'Revisa los datos', error.message);
    }
    if (error.status === 409) {
        return aviso('warning', 'No se pudo completar', error.message);
    }
    if (error.status === 403) {
        return aviso('error', 'Acción no permitida', error.message);
    }
    return aviso('error', 'Error', error.message || 'Ocurrió un error inesperado.');
}

/* ---------- Formato seguro ---------- */

function esc(valor) {
    const mapa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(valor ?? '').replace(/[&<>"']/g, (c) => mapa[c]);
}

/** 'YYYY-MM-DD HH:MM:SS' a 'dd/mm/aaaa HH:MM', sin conversiones de zona horaria. */
function fechaHora(valor) {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(valor ?? '');
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : '—';
}

function fechaCorta(valor) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(valor ?? '');
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '—';
}

/** Valor para un input datetime-local a partir de una fecha de MySQL. */
const aInputFecha = (valor) => (valor ? String(valor).slice(0, 16).replace(' ', 'T') : '');

/** Date local a formato datetime-local. */
function dateAInput(d) {
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

const moneda = (v) => `$${Number(v ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const COLOR_ESTADO = {
    Disponible: 'verde', Mantenimiento: 'amarillo', 'Fuera de servicio': 'rojo',
    Pendiente: 'amarillo', Confirmada: 'azul', 'En uso': 'indigo', Finalizada: 'verde', Cancelada: 'rojo',
    Programado: 'azul', 'En proceso': 'amarillo', Finalizado: 'verde', Cancelado: 'rojo',
    Activo: 'verde', Inactivo: 'gris', Devuelto: 'verde', 'Con observaciones': 'amarillo',
    Atrasado: 'rojo', 'Próximo': 'amarillo',
};

const etiqueta = (estado) => `<span class="estado estado-${COLOR_ESTADO[estado] ?? 'gris'}">${esc(estado)}</span>`;

/** Una reserva cancelada o sin iniciar no tiene devolución que mostrar. */
function etiquetaDevolucion(r) {
    if (r.estado === 'Finalizada') return etiqueta(r.estado_devolucion);
    if (r.estado === 'En uso') return '<span class="estado estado-gris">Por devolver</span>';
    return '<span class="text-secondary">No aplica</span>';
}

const boton = (accion, icono, titulo, id, color = 'secondary') => `<button type="button" class="btn btn-sm btn-outline-${color}"
    data-accion="${accion}" data-id="${esc(id)}" title="${esc(titulo)}" aria-label="${esc(titulo)}"><i class="bi ${icono}" aria-hidden="true"></i></button>`;

function listaDetalle(pares) {
    return `<dl class="lista-detalle">${pares.map(([k, v, html]) => `<div><dt>${esc(k)}</dt><dd>${html ? v : esc(v ?? '—') || '—'}</dd></div>`).join('')}</dl>`;
}

function llenarSelect(select, opciones, { valor = 'id', texto = 'texto', vacio = null, seleccionado = '' } = {}) {
    const html = opciones.map((o) => `<option value="${esc(o[valor])}">${esc(typeof texto === 'function' ? texto(o) : o[texto])}</option>`);
    if (vacio !== null) html.unshift(`<option value="">${esc(vacio)}</option>`);
    select.innerHTML = html.join('');
    select.value = seleccionado ?? '';
}

/* ---------- DataTables ---------- */

const IDIOMA_TABLA = {
    search: 'Buscar:', lengthMenu: 'Mostrar _MENU_', info: '_START_ a _END_ de _TOTAL_ registros',
    infoEmpty: 'Sin registros', infoFiltered: '(de _MAX_ en total)', zeroRecords: 'No se encontraron coincidencias',
    emptyTable: 'Todavía no hay registros', loadingRecords: 'Cargando…', processing: 'Procesando…',
    paginate: { first: '«', previous: '‹', next: '›', last: '»' },
};

/** Crea una tabla. Todo valor sin render propio se escapa, así los datos nunca se interpretan como HTML. */
function crearTabla(selector, columnas, opciones = {}) {
    const defs = columnas.map((c) => ({
        defaultContent: '',
        ...c,
        render: c.render ?? ((d, tipo) => (tipo === 'display' ? esc(d) : d)),
    }));
    return new DataTable(selector, {
        data: [], columns: defs, language: IDIOMA_TABLA, pageLength: 10, autoWidth: false, order: [], ...opciones,
    });
}

const columnaFecha = (data, title) => ({
    data, title,
    render: (d, tipo) => (tipo === 'display' ? fechaHora(d) : d),
});

const columnaEstado = (data, title = 'Estado') => ({
    data, title,
    render: (d, tipo) => (tipo === 'display' ? etiqueta(d) : d),
});

function recargarTabla(tabla, filas) {
    tabla.clear().rows.add(filas).draw(false);
}

/** Enlaza los botones [data-accion] de una tabla (incluye filas de otras páginas). */
function accionesTabla(selector, manejadores) {
    document.querySelector(selector).addEventListener('click', (e) => {
        const b = e.target.closest('[data-accion]');
        if (b && manejadores[b.dataset.accion]) {
            manejadores[b.dataset.accion](Number(b.dataset.id));
        }
    });
}

/* ---------- Formularios y validación ---------- */

function leerFormulario(form) {
    const datos = {};
    new FormData(form).forEach((v, k) => { datos[k] = typeof v === 'string' ? v.trim() : v; });
    return datos;
}

function limpiarErrores(form) {
    form.querySelectorAll('.is-invalid').forEach((c) => c.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach((f) => { f.textContent = ''; });
}

function marcarCampo(form, nombre, mensaje) {
    const campo = form.elements[nombre];
    if (!campo || !campo.classList) return false;
    campo.classList.add('is-invalid');
    let retro = campo.nextElementSibling;
    if (!retro || !retro.classList.contains('invalid-feedback')) {
        retro = document.createElement('div');
        retro.className = 'invalid-feedback';
        campo.insertAdjacentElement('afterend', retro);
    }
    retro.textContent = mensaje;
    return true;
}

function marcarErrores(form, errores) {
    Object.entries(errores).forEach(([campo, mensaje]) => marcarCampo(form, campo, mensaje));
    form.querySelector('.is-invalid')?.focus();
}

/** Reglas: cada una devuelve un mensaje de error o null. Se repiten después en PHP. */
const V = {
    req: (m) => (v) => (String(v ?? '').trim() === '' ? m : null),
    nombre: (m = 'Solo puede contener letras.') => (v) => (v && !/^\p{L}[\p{L}\s'.-]*$/u.test(v) ? m : null),
    usuario: () => (v) => (v && !/^[A-Za-z0-9._-]{3,50}$/.test(v) ? 'Usa de 3 a 50 letras, números, punto, guion o guion bajo, sin espacios.' : null),
    correo: () => (v) => (v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? 'Escribe un correo electrónico válido.' : null),
    telefono: () => (v) => (v && (!/^\+?[0-9 ()-]+$/.test(v) || v.replace(/\D/g, '').length < 7) ? 'Escribe un teléfono válido: al menos 7 dígitos.' : null),
    contrasena: () => (v) => (v && !(v.length >= 8 && /[A-Z]/.test(v) && /[a-z]/.test(v) && /\d/.test(v) && /[^A-Za-z0-9]/.test(v))
        ? 'Debe tener al menos 8 caracteres, con mayúscula, minúscula, número y símbolo.' : null),
    igualA: (otro, m) => (v, d) => (v !== d[otro] ? m : null),
    noNegativo: (m) => (v) => (v !== '' && v !== undefined && (Number.isNaN(Number(v)) || Number(v) < 0) ? m : null),
    entre: (min, max, m) => (v) => (v !== '' && (!Number.isInteger(Number(v)) || Number(v) < min || Number(v) > max) ? m : null),
    posteriorA: (otro, m) => (v, d) => (v && d[otro] && v <= d[otro] ? m : null),
};

function validarFormulario(form, reglas) {
    limpiarErrores(form);
    const datos = leerFormulario(form);
    const errores = {};
    Object.entries(reglas).forEach(([campo, lista]) => {
        for (const regla of lista) {
            const mensaje = regla(datos[campo] ?? '', datos);
            if (mensaje) {
                errores[campo] = mensaje;
                break;
            }
        }
    });
    if (Object.keys(errores).length) {
        marcarErrores(form, errores);
        return null;
    }
    return datos;
}

/** Quita el error de un campo en cuanto se corrige. */
function activarLimpiezaCampos(form) {
    form.addEventListener('input', (e) => e.target.classList?.remove('is-invalid'));
    form.addEventListener('change', (e) => e.target.classList?.remove('is-invalid'));
}

async function conBotonOcupado(form, tarea) {
    const b = form.querySelector('[type="submit"]');
    if (b) b.disabled = true;
    try {
        return await tarea();
    } finally {
        if (b) b.disabled = false;
    }
}

/* ---------- Sesión ---------- */

// Si el navegador restaura la página desde su caché al pulsar Atrás, se vuelve a pedir al servidor.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) location.reload();
});

document.getElementById('btnCerrarSesion')?.addEventListener('click', async () => {
    if (!await confirmar({ titulo: '¿Cerrar sesión?', boton: 'Cerrar sesión' })) return;
    try {
        const r = await api('auth/logout', { metodo: 'POST' });
        location.replace(r.data.redirect);
    } catch (e) {
        mostrarError(e);
    }
});

document.querySelectorAll('form').forEach(activarLimpiezaCampos);
