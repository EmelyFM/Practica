'use strict';

const formLogin = document.getElementById('formLogin');
const formSetup = document.getElementById('formSetup');

formLogin?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = validarFormulario(formLogin, {
        nombre_usuario: [V.req('El nombre de usuario es obligatorio.')],
        contrasena: [V.req('La contraseña es obligatoria.')],
    });
    if (!datos) return;
    await conBotonOcupado(formLogin, async () => {
        try {
            const r = await api('auth/login', { metodo: 'POST', datos });
            location.replace(r.data.redirect);
        } catch (error) {
            if (error.status === 401) {
                aviso('error', 'No se pudo iniciar sesión', error.message);
            } else {
                mostrarError(error, formLogin);
            }
        }
    });
});

formSetup?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = validarFormulario(formSetup, {
        nombre_usuario: [V.req('El nombre de usuario es obligatorio.'), V.usuario()],
        correo: [V.req('El correo electrónico es obligatorio.'), V.correo()],
        contrasena: [V.req('La contraseña es obligatoria.'), V.contrasena()],
        confirmacion: [V.igualA('contrasena', 'Las contraseñas no coinciden.')],
    });
    if (!datos) return;
    await conBotonOcupado(formSetup, async () => {
        try {
            const r = await api('auth/primer-admin', { metodo: 'POST', datos });
            await aviso('success', 'Administrador creado', r.message);
            location.replace(r.data.redirect);
        } catch (error) {
            mostrarError(error, formSetup);
        }
    });
});
