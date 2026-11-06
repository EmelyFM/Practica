<?php
defined('APP') || exit;

class UsuarioController extends BaseController
{
    private UsuarioModel $modelo;

    public function __construct()
    {
        parent::__construct();
        $this->requerirAdmin();
        $this->modelo = new UsuarioModel();
    }

    public function listar(): void
    {
        $this->responder('Usuarios obtenidos.', $this->modelo->listar());
    }

    public function roles(): void
    {
        $this->responder('Roles obtenidos.', $this->modelo->roles());
    }

    public function obtener(int $id): void
    {
        $this->responder('Usuario obtenido.', $this->exigirUsuario($id));
    }

    public function crear(): void
    {
        $v = new Validador($this->entrada());
        $d = $this->validarDatos($v);
        $d['contrasena'] = $v->contrasena();
        $d['estado'] = $v->opcion('estado', 'El estado', ['Activo', 'Inactivo'], false) ?? 'Activo';
        $this->comprobarUnicos($v, $d);
        $v->verificar();
        $id = $this->modelo->crear($d, $this->idUsuario());
        $this->responder('Usuario registrado correctamente.', ['id_usuario' => $id], 201);
    }

    public function actualizar(int $id): void
    {
        $this->exigirUsuario($id);
        $v = new Validador($this->entrada());
        $d = $this->validarDatos($v);
        $this->comprobarUnicos($v, $d, $id);
        $v->verificar();
        $this->modelo->actualizar($id, $d, $this->idUsuario());
        $this->responder('Usuario actualizado correctamente.');
    }

    public function cambiarEstado(int $id): void
    {
        $this->exigirUsuario($id);
        $v = new Validador($this->entrada());
        $estado = $v->opcion('estado', 'El estado', ['Activo', 'Inactivo']);
        $v->verificar();
        $this->modelo->cambiarEstado($id, $estado, $this->idUsuario());
        $this->responder($estado === 'Activo' ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function cambiarContrasena(int $id): void
    {
        $this->exigirUsuario($id);
        $v = new Validador($this->entrada());
        $clave = $v->contrasena();
        $v->verificar();
        $this->modelo->cambiarContrasena($id, $clave, $this->idUsuario());
        $this->responder('Contraseña actualizada correctamente.');
    }

    private function exigirUsuario(int $id): array
    {
        $u = $this->modelo->obtener($id);
        if (!$u) {
            throw $this->noEncontrado('El usuario no existe.');
        }
        return $u;
    }

    private function validarDatos(Validador $v): array
    {
        $d = [
            'nombre_usuario' => $v->nombreUsuario(),
            'correo' => $v->correo(),
            'id_rol' => $v->id('id_rol', 'El rol'),
        ];
        if ($d['id_rol'] !== null && !$this->modelo->rolActivo($d['id_rol'])) {
            $v->error('id_rol', 'El rol seleccionado no existe o no está activo.');
        }
        return $d;
    }

    private function comprobarUnicos(Validador $v, array $d, int $excluir = 0): void
    {
        // Primero se valida el formato: un duplicado (409) solo se informa con datos correctos.
        $v->verificar();
        $errores = [];
        if ($this->modelo->existeNombre($d['nombre_usuario'], $excluir)) {
            $errores['nombre_usuario'] = 'Ese nombre de usuario ya está registrado.';
        }
        if ($this->modelo->existeCorreo($d['correo'], $excluir)) {
            $errores['correo'] = 'Ese correo electrónico ya está registrado.';
        }
        if ($errores) {
            throw new AppException('Ya existe un usuario con esos datos.', 409, $errores);
        }
    }
}
