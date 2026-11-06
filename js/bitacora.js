'use strict';

const form = document.getElementById('formFiltros');
const modalDetalle = new bootstrap.Modal('#modalDetalle');
let registros = [];

const tabla = crearTabla('#tabla', [
    columnaFecha('fecha_hora', 'Fecha'),
    { data: 'nombre_usuario', title: 'Usuario' },
    { data: 'modulo', title: 'Módulo' },
    { data: 'accion', title: 'Acción' },
    { data: 'descripcion', title: 'Descripción' },
    {
        data: null, title: 'Datos', orderable: false, searchable: false, className: 'acciones',
        render: (_, __, r) => (r.datos_anteriores || r.datos_nuevos ? boton('ver', 'bi-eye', 'Ver datos', r.id_bitacora) : ''),
    },
], { order: [[0, 'desc']], pageLength: 25 });

function jsonLegible(texto) {
    if (!texto) return '<p class="text-secondary small mb-0">Sin datos.</p>';
    let contenido = texto;
    try {
        contenido = JSON.stringify(JSON.parse(texto), null, 2);
    } catch { /* se muestra el texto tal como está guardado */ }
    return `<pre class="bloque-json mb-0">${esc(contenido)}</pre>`;
}

async function cargar() {
    const parametros = leerFormulario(form);
    const { data } = await api('bitacora', { parametros });
    registros = data.registros;
    recargarTabla(tabla, registros);
    const select = form.elements.modulo;
    const actual = select.value;
    llenarSelect(select, data.modulos.map((m) => ({ id: m, texto: m })), { vacio: 'Todos', seleccionado: actual });
}

form.addEventListener('submit', (e) => {
    e.preventDefault();
    const datos = validarFormulario(form, {
        hasta: [(v, d) => (v && d.desde && v < d.desde ? 'La fecha final no puede ser anterior a la inicial.' : null)],
    });
    if (datos) cargar().catch((error) => mostrarError(error, form));
});

accionesTabla('#tabla', {
    ver: (id) => {
        const r = registros.find((x) => Number(x.id_bitacora) === id);
        document.getElementById('cuerpoDetalle').innerHTML = `
            <p>${esc(r.descripcion)}</p>
            <div class="row g-3">
                <div class="col-md-6"><h3 class="h6">Datos anteriores</h3>${jsonLegible(r.datos_anteriores)}</div>
                <div class="col-md-6"><h3 class="h6">Datos nuevos</h3>${jsonLegible(r.datos_nuevos)}</div>
            </div>`;
        modalDetalle.show();
    },
});

cargar().catch((e) => mostrarError(e));
