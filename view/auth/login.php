<?php
defined('APP') || exit;
$titulo = 'Iniciar sesión';
require RAIZ . '/view/layouts/head.php';
?>
<body class="pagina-acceso">
    <main class="tarjeta-acceso">
        <div class="acceso-marca">
            <i class="bi bi-truck-front" aria-hidden="true"></i>
            <div>
                <h1><?= e(APP_NOMBRE_CORTO) ?></h1>
                <p><?= e(APP_NOMBRE) ?></p>
            </div>
        </div>
        <form id="formLogin" novalidate>
            <div class="mb-3">
                <label class="form-label" for="nombre_usuario">Nombre de usuario</label>
                <input class="form-control" id="nombre_usuario" name="nombre_usuario" autocomplete="username" autofocus maxlength="50">
            </div>
            <div class="mb-3">
                <label class="form-label" for="contrasena">Contraseña</label>
                <input class="form-control" id="contrasena" name="contrasena" type="password" autocomplete="current-password">
            </div>
            <button class="btn btn-primary w-100" type="submit">Iniciar sesión</button>
        </form>
    </main>
    <script src="<?= e(base_url()) ?>/js/lib/bootstrap.bundle.min.js"></script>
    <script src="<?= e(base_url()) ?>/js/lib/sweetalert2.all.min.js"></script>
    <script src="<?= e(base_url()) ?>/js/funcion.js"></script>
    <script src="<?= e(base_url()) ?>/js/auth.js"></script>
</body>
</html>
