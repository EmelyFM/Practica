<?php
defined('APP') || exit;

class ReservaController extends BaseController
{
    /** Margen para que una reserva que inicia en el minuto actual no cuente como pasada. */
    private const TOLERANCIA_SEG = 300;

    private ReservaModel $modelo;

    public function __construct()
    {
        parent::__construct();
        $this->modelo = new ReservaModel();
    }

    public function listar(): void
    {
        $idEmpleado = $this->esAdmin() ? null : $this->miEmpleadoOCero();
        $this->responder('Reservas obtenidas.', $this->modelo->listar($idEmpleado, isset($_GET['devoluciones'])));
    }

    public function obtener(int $id): void
    {
        $this->responder('Reserva obtenida.', $this->autorizada($id));
    }

    public function disponibilidad(): void
    {
        $v = new Validador($_GET);
        $excluir = $v->id('excluir', 'La reserva', false) ?? 0;
        if ($excluir && !$this->esAdmin()) {
            $this->autorizada($excluir);
        }
        [$inicio, $fin] = $this->validarPeriodo($v, $excluir === 0);
        $v->verificar();
        $vehiculos = (new VehiculoModel())->disponibilidad($inicio, $fin, $excluir);
        $this->responder('Disponibilidad consultada.', [
            'inicio' => $inicio,
            'fin' => $fin,
            'disponibles' => count(array_filter($vehiculos, fn ($f) => $f['disponible'])),
            'vehiculos' => $vehiculos,
        ]);
    }

    public function crear(): void
    {
        $entrada = $this->entrada();
        $v = new Validador($entrada);
        if ($this->esAdmin()) {
            $idEmpleado = $v->id('id_empleado', 'El empleado responsable');
            $estado = $v->opcion('estado', 'El estado', ['Pendiente', 'Confirmada'], false) ?? 'Pendiente';
        } else {
            $idEmpleado = $this->miEmpleado();
            if (isset($entrada['id_empleado']) && (int) $entrada['id_empleado'] !== $idEmpleado) {
                throw new AppException('Solo puedes registrar reservas a tu propio nombre.', 403);
            }
            $estado = 'Pendiente';
        }
        [$inicio, $fin] = $this->validarPeriodo($v, true);
        $d = [
            'id_vehiculo' => $v->id('id_vehiculo', 'El vehículo'),
            'id_empleado' => $idEmpleado,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'motivo' => $v->texto('motivo', 'El motivo', 250),
            'estado' => $estado,
            'observaciones' => $v->texto('observaciones', 'Las observaciones', 2000, false),
        ];
        $v->verificar();
        $creada = $this->modelo->crear($d, $this->idUsuario());
        $this->responder('Reserva registrada correctamente.', $creada, 201);
    }

    public function actualizar(int $id): void
    {
        $actual = $this->autorizada($id);
        $entrada = $this->entrada();
        $v = new Validador($entrada);
        if ($this->esAdmin()) {
            $idEmpleado = $v->id('id_empleado', 'El empleado responsable');
            $editables = ['Pendiente', 'Confirmada'];
        } else {
            $idEmpleado = (int) $actual['id_empleado'];
            if (isset($entrada['id_empleado']) && (int) $entrada['id_empleado'] !== $idEmpleado) {
                throw new AppException('No puedes reasignar una reserva a otro empleado.', 403);
            }
            $editables = ['Pendiente'];
        }
        [$inicio, $fin] = $this->validarPeriodo($v, false);
        if ($inicio !== null && $inicio !== $actual['fecha_inicio']) {
            $this->exigirNoPasada($v, $inicio);
        }
        if ($fin !== null && $fin <= $this->modelo->ahora()) {
            $v->error('fecha_fin', 'La fecha final no puede estar en el pasado.');
        }
        $d = [
            'id_vehiculo' => $v->id('id_vehiculo', 'El vehículo'),
            'id_empleado' => $idEmpleado,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'motivo' => $v->texto('motivo', 'El motivo', 250),
            'observaciones' => $v->texto('observaciones', 'Las observaciones', 2000, false),
        ];
        $v->verificar();
        $this->modelo->actualizar($id, $d, $editables, $this->idUsuario());
        $this->responder('Reserva actualizada correctamente.');
    }

    public function confirmar(int $id): void
    {
        $this->requerirAdmin();
        $this->autorizada($id);
        $this->modelo->confirmar($id, $this->idUsuario());
        $this->responder('Reserva confirmada.');
    }

    public function cancelar(int $id): void
    {
        $this->autorizada($id);
        $v = new Validador($this->entrada());
        $motivo = $v->texto('motivo', 'El motivo', 250, false);
        $v->verificar();
        $this->modelo->cancelar($id, $motivo, $this->idUsuario());
        $this->responder('Reserva cancelada.');
    }

    public function iniciar(int $id): void
    {
        $this->autorizada($id);
        $this->modelo->iniciar($id, $this->idUsuario());
        $this->responder('Uso del vehículo iniciado.');
    }

    public function devolver(int $id): void
    {
        $this->autorizada($id);
        $v = new Validador($this->entrada());
        $d = [
            'fecha_devolucion' => $v->fechaHora('fecha_devolucion', 'La fecha de devolución', false),
            'estado_devolucion' => $v->opcion('estado_devolucion', 'El estado de devolución', ['Devuelto', 'Con observaciones']),
            'observaciones' => $v->texto('observaciones', 'Las observaciones', 2000, false),
        ];
        if ($d['estado_devolucion'] === 'Con observaciones' && $d['observaciones'] === null) {
            $v->error('observaciones', 'Describe las observaciones de la devolución.');
        }
        $v->verificar();
        $this->modelo->devolver($id, $d, $this->idUsuario());
        $this->responder('Devolución registrada correctamente.');
    }

    /** Devuelve la reserva si existe y el usuario puede operar sobre ella. */
    private function autorizada(int $id): array
    {
        $r = $this->modelo->obtener($id);
        if (!$r) {
            throw $this->noEncontrado('La reserva no existe.');
        }
        if (!$this->esAdmin() && (int) $r['id_empleado'] !== $this->miEmpleadoOCero()) {
            throw new AppException('No tienes permiso sobre esta reserva.', 403);
        }
        return $r;
    }

    private function miEmpleadoOCero(): int
    {
        return (int) ($this->usuario['id_empleado'] ?? 0);
    }

    private function miEmpleado(): int
    {
        if (empty($this->usuario['id_empleado']) || $this->usuario['estado_empleado'] !== 'Activo') {
            throw new AppException('Tu usuario no tiene una ficha de empleado activa. Contacta al administrador.', 403);
        }
        return (int) $this->usuario['id_empleado'];
    }

    private function validarPeriodo(Validador $v, bool $exigirFuturo): array
    {
        $inicio = $v->fechaHora('fecha_inicio', 'La fecha de inicio');
        $fin = $v->fechaHora('fecha_fin', 'La fecha final');
        if ($inicio !== null && $fin !== null && $fin <= $inicio) {
            $v->error('fecha_fin', 'La fecha final debe ser posterior a la fecha de inicio.');
        }
        if ($exigirFuturo && $inicio !== null) {
            $this->exigirNoPasada($v, $inicio);
        }
        return [$inicio, $fin];
    }

    private function exigirNoPasada(Validador $v, string $inicio): void
    {
        $limite = date('Y-m-d H:i:s', strtotime($this->modelo->ahora()) - self::TOLERANCIA_SEG);
        if ($inicio < $limite) {
            $v->error('fecha_inicio', 'La fecha de inicio no puede estar en el pasado.');
        }
    }
}
