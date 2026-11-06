<?php defined('APP') || exit; ?>
<section class="panel">
    <header class="panel-encabezado">
        <h2>Usuarios del sistema</h2>
        <button class="btn btn-sm btn-primary" id="btnNuevo" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nuevo usuario</button>
    </header>
    <div class="panel-cuerpo">
        <div class="table-responsive">
            <table id="tabla" class="table table-hover align-middle w-100"></table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="tituloUsuario" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="formUsuario" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloUsuario">Nuevo usuario</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="nombre_usuario">Nombre de usuario</label>
                    <input class="form-control" id="nombre_usuario" name="nombre_usuario" maxlength="50" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="correo">Correo electrónico</label>
                    <input class="form-control" id="correo" name="correo" type="email" maxlength="100" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="id_rol">Rol</label>
                    <select class="form-select" id="id_rol" name="id_rol"></select>
                </div>
                <div class="mb-3" id="grupoContrasena">
                    <label class="form-label" for="contrasena">Contraseña</label>
                    <input class="form-control" id="contrasena" name="contrasena" type="password" autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalContrasena" tabindex="-1" aria-labelledby="tituloContrasena" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="formContrasena" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="tituloContrasena">Actualizar contraseña</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="nuevaContrasena">Nueva contraseña</label>
                    <input class="form-control" id="nuevaContrasena" name="contrasena" type="password" autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirmarContrasena">Confirmar contraseña</label>
                    <input class="form-control" id="confirmarContrasena" name="confirmacion" type="password" autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>
