<?php
defined('APP') || exit;

class EmpleadoController extends BaseController
{
    private EmpleadoModel $modelo;

    public function __construct()
    {
        parent::__construct();
        $this->requerirAdmin();
        $this->modelo = new EmpleadoModel();
    }

    public function listar(): void
    {
        $this->responder('Empleados obtenidos.', $this->modelo->listar(isset($_GET['asignables'])));
    }

    public function usuariosDisponibles(): void
    {
        $this->responder('Usuarios disponibles obtenidos.', $this->modelo->usuariosDisponibles((int) ($_GET['id_empleado'] ?? 0)));
    }

    public function obtener(int $id): void
    {
        $this->responder('Empleado obtenido.', $this->exigirEmpleado($id));
    }

    public function crear(): void
    {
        $d = $this->validar(0);
        $d['estado'] = 'Activo';
        $id = $this->modelo->crear($d, $this->idUsuario());
        $this->responder('Empleado registrado correctamente.', ['id_empleado' => $id], 201);
    }

    public function actualizar(int $id): void
    {
        $this->exigirEmpleado($id);
        $this->modelo->actualizar($id, $this->validar($id), $this->idUsuario());
        $this->responder('Empleado actualizado correctamente.');
    }

    public function cambiarEstado(int $id): void
    {
        $this->exigirEmpleado($id);
        $v = new Validador($this->entrada());
        $estado = $v->opcion('estado', 'El estado', ['Activo', 'Inactivo']);
        $v->verificar();
        $this->modelo->cambiarEstado($id, $estado, $this->idUsuario());
        $this->responder($estado === 'Activo' ? 'Empleado activado.' : 'Empleado desactivado.');
    }

    private function exigirEmpleado(int $id): array
    {
        $e = $this->modelo->obtener($id);
        if (!$e) {
            throw $this->noEncontrado('El empleado no existe.');
        }
        return $e;
    }

    private function validar(int $idEmpleado): array
    {
        $v = new Validador($this->entrada());
        $d = [
            'id_usuario' => $v->id('id_usuario', 'El usuario relacionado'),
            'nombre' => $v->nombrePersona('nombre', 'El nombre', 100),
            'apellido_paterno' => $v->nombrePersona('apellido_paterno', 'El apellido paterno', 80),
            'apellido_materno' => $v->nombrePersona('apellido_materno', 'El apellido materno', 80, false),
            'telefono' => $v->telefono(),
            'puesto' => $v->texto('puesto', 'El puesto', 80, false),
            'fecha_ingreso' => $v->fecha('fecha_ingreso', 'La fecha de ingreso', false),
        ];
        if ($d['fecha_ingreso'] !== null && $d['fecha_ingreso'] > substr($this->modelo->ahora(), 0, 10)) {
            $v->error('fecha_ingreso', 'La fecha de ingreso no puede ser futura.');
        }
        if ($d['id_usuario'] !== null) {
            if (!$this->modelo->usuarioValido($d['id_usuario'])) {
                $v->error('id_usuario', 'El usuario relacionado no existe o no está activo.');
            } elseif ($this->modelo->usuarioOcupado($d['id_usuario'], $idEmpleado)) {
                throw new AppException('Ese usuario ya está asignado a otro empleado.', 409, ['id_usuario' => 'Ese usuario ya está asignado a otro empleado.']);
            }
        }
        $v->verificar();
        return $d;
    }
}
