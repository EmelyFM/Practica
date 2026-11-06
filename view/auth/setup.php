<?php
defined('APP') || exit;
$titulo = 'Configuración inicial';
require RAIZ . '/view/layouts/head.php';
?>
<body class="pagina-acceso">
    <main class="tarjeta-acceso">
        <div class="acceso-marca">
            <i class="bi bi-truck-front" aria-hidden="true"></i>
            <div>
                <h1>Configuración inicial</h1>
                <p><?= e(APP_NOMBRE) ?></p>
            </div>
        </div>
        <?php if (!$puedeConfigurar): ?>
            <div class="alert alert-warning mb-0">
                El sistema aún no tiene usuarios. Por seguridad, el primer administrador solo puede crearse desde el equipo donde está instalado el servidor.
            </div>
        <?php else: ?>
            <p class="text-secondary small">
                La base de datos no tiene usuarios. Crea el primer administrador; esta pantalla desaparece en cuanto exista.
            </p>
            <form id="formSetup" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="nombre_usuario">Nombre de usuario</label>
                    <input class="form-control" id="nombre_usuario" name="nombre_usuario" autocomplete="username" maxlength="50">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="correo">Correo electrónico</label>
                    <input class="form-control" id="correo" name="correo" type="email" autocomplete="email" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="contrasena">Contraseña</label>
                    <input class="form-control" id="contrasena" name="contrasena" type="password" autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirmacion">Confirmar contraseña</label>
                    <input class="form-control" id="confirmacion" name="confirmacion" type="password" autocomplete="new-password">
                </div>
                <button class="btn btn-primary w-100" type="submit">Crear administrador</button>
            </form>
        <?php endif; ?>
    </main>
    <script src="<?= e(base_url()) ?>/js/lib/bootstrap.bundle.min.js"></script>
    <script src="<?= e(base_url()) ?>/js/lib/sweetalert2.all.min.js"></script>
    <script src="<?= e(base_url()) ?>/js/funcion.js"></script>
    <script src="<?= e(base_url()) ?>/js/auth.js"></script>
</body>
</html>
