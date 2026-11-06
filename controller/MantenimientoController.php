<?php
defined('APP') || exit;

class MantenimientoController extends BaseController
{
    private MantenimientoModel $modelo;

    public function __construct()
    {
        parent::__construct();
        $this->requerirAdmin();
        $this->modelo = new MantenimientoModel();
    }

    public function listar(): void
    {
        $this->responder('Mantenimientos obtenidos.', [
            'mantenimientos' => $this->modelo->listar(),
            'responsables' => $this->modelo->responsables(),
        ]);
    }

    public function proximos(): void
    {
        $this->responder('Recordatorios obtenidos.', [
            'dias_aviso' => MantenimientoModel::DIAS_AVISO,
            'mantenimientos' => $this->modelo->proximos(),
        ]);
    }

    public function obtener(int $id): void
    {
        $this->responder('Mantenimiento obtenido.', $this->exigir($id));
    }

    public function crear(): void
    {
        $v = new Validador($this->entrada());
        $d = $this->validar($v);
        $d['estado'] = $v->opcion('estado', 'El estado', ['Programado', 'En proceso'], false) ?? 'Programado';
        $ahora = $this->modelo->ahora();
        if ($d['fecha_inicio'] !== null) {
            if ($d['estado'] === 'Programado' && $d['fecha_inicio'] < date('Y-m-d H:i:s', strtotime($ahora) - 300)) {
                $v->error('fecha_inicio', 'Un mantenimiento programado no puede iniciar en el pasado.');
            }
            if ($d['estado'] === 'En proceso' && $d['fecha_inicio'] > date('Y-m-d H:i:s', strtotime($ahora) + 300)) {
                $v->error('fecha_inicio', 'Un mantenimiento en proceso no puede iniciar en el futuro.');
            }
        }
        $this->exigirFinSiProgramado($v, $d['estado'], $d['fecha_fin']);
        $v->verificar();
        $id = $this->modelo->crear($d, $this->idUsuario());
        $this->responder('Mantenimiento registrado correctamente.', ['id_mantenimiento' => $id], 201);
    }

    public function actualizar(int $id): void
    {
        $actual = $this->exigir($id);
        $v = new Validador($this->entrada());
        $d = $this->validar($v);
        $this->exigirFinSiProgramado($v, $actual['estado'], $d['fecha_fin']);
        $v->verificar();
        $this->modelo->actualizar($id, $d, $this->idUsuario());
        $this->responder('Mantenimiento actualizado correctamente.');
    }

    public function cambiarEstado(int $id): void
    {
        $this->exigir($id);
        $v = new Validador($this->entrada());
        $d = [
            'estado' => $v->opcion('estado', 'El estado', ['En proceso', 'Finalizado', 'Cancelado']),
            'fecha_fin' => $v->fechaHora('fecha_fin', 'La fecha final', false),
            'costo' => $v->decimal('costo', 'El costo'),
            'observaciones' => $v->texto('observaciones', 'Las observaciones', 2000, false),
        ];
        $v->verificar();
        $this->modelo->cambiarEstado($id, $d, $this->idUsuario());
        $this->responder("Mantenimiento actualizado a {$d['estado']}.");
    }

    private function exigir(int $id): array
    {
        $m = $this->modelo->obtener($id);
        if (!$m) {
            throw $this->noEncontrado('El mantenimiento no existe.');
        }
        return $m;
    }

    private function exigirFinSiProgramado(Validador $v, string $estado, ?string $fin): void
    {
        // Sin fecha final el mantenimiento bloquearía al vehículo de forma indefinida.
        if ($estado === 'Programado' && $fin === null) {
            $v->error('fecha_fin', 'La fecha final es obligatoria en un mantenimiento programado.');
        }
    }

    private function validar(Validador $v): array
    {
        $d = [
            'id_vehiculo' => $v->id('id_vehiculo', 'El vehículo'),
            'id_usuario' => $v->id('id_usuario', 'El usuario responsable'),
            'tipo_mantenimiento' => $v->opcion('tipo_mantenimiento', 'El tipo de mantenimiento', ['Preventivo', 'Correctivo']),
            'descripcion' => $v->texto('descripcion', 'La descripción', 2000),
            'fecha_inicio' => $v->fechaHora('fecha_inicio', 'La fecha de inicio'),
            'fecha_fin' => $v->fechaHora('fecha_fin', 'La fecha final', false),
            'costo' => $v->decimal('costo', 'El costo') ?? '0.00',
            'observaciones' => $v->texto('observaciones', 'Las observaciones', 2000, false),
        ];
        if ($d['fecha_inicio'] !== null && $d['fecha_fin'] !== null && $d['fecha_fin'] <= $d['fecha_inicio']) {
            $v->error('fecha_fin', 'La fecha final debe ser posterior a la fecha de inicio.');
        }
        if ($d['id_usuario'] !== null && !$this->modelo->responsableValido($d['id_usuario'])) {
            $v->error('id_usuario', 'El usuario responsable no existe o no está activo.');
        }
        return $d;
    }
}
