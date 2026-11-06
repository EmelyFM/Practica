<?php
defined('APP') || exit;

class VehiculoController extends BaseController
{
    private const ESTADOS = ['Disponible', 'Mantenimiento', 'Fuera de servicio'];
    private VehiculoModel $modelo;

    public function __construct()
    {
        parent::__construct();
        $this->modelo = new VehiculoModel();
    }

    /** Catálogo: visible para ambos roles; las observaciones internas solo para el administrador. */
    public function listar(): void
    {
        $filas = $this->modelo->listar();
        if (!$this->esAdmin()) {
            $filas = array_map(fn ($f) => $this->vistaEmpleado($f), $filas);
        }
        $this->responder('Vehículos obtenidos.', $filas);
    }

    public function obtener(int $id): void
    {
        $f = $this->exigirVehiculo($id);
        $this->responder('Vehículo obtenido.', $this->esAdmin() ? $f : $this->vistaEmpleado($f));
    }

    public function historialReservas(int $id): void
    {
        $this->requerirAdmin();
        $this->exigirVehiculo($id);
        $this->responder('Historial de reservas obtenido.', $this->modelo->historialReservas($id));
    }

    public function historialMantenimientos(int $id): void
    {
        $this->requerirAdmin();
        $this->exigirVehiculo($id);
        $this->responder('Historial de mantenimientos obtenido.', $this->modelo->historialMantenimientos($id));
    }

    public function crear(): void
    {
        $this->requerirAdmin();
        $v = new Validador($this->entrada());
        $d = $this->validar($v);
        $d['estado'] = $v->opcion('estado', 'El estado', self::ESTADOS, false) ?? 'Disponible';
        $this->comprobarPlaca($v, $d['placa']);
        $v->verificar();
        $id = $this->modelo->crear($d, $this->idUsuario());
        $this->responder('Vehículo registrado correctamente.', ['id_vehiculo' => $id], 201);
    }

    public function actualizar(int $id): void
    {
        $this->requerirAdmin();
        $this->exigirVehiculo($id);
        $v = new Validador($this->entrada());
        $d = $this->validar($v);
        $this->comprobarPlaca($v, $d['placa'], $id);
        $v->verificar();
        $this->modelo->actualizar($id, $d, $this->idUsuario());
        $this->responder('Vehículo actualizado correctamente.');
    }

    public function cambiarEstado(int $id): void
    {
        $this->requerirAdmin();
        $v = new Validador($this->entrada());
        $estado = $v->opcion('estado', 'El estado', self::ESTADOS);
        $v->verificar();
        $this->modelo->cambiarEstado($id, $estado, $this->idUsuario());
        $this->responder("Estado del vehículo actualizado a $estado.");
    }

    public function eliminar(int $id): void
    {
        $this->requerirAdmin();
        $this->modelo->eliminar($id, $this->idUsuario());
        $this->responder('Vehículo eliminado.');
    }

    private function exigirVehiculo(int $id): array
    {
        $f = $this->modelo->obtener($id);
        if (!$f) {
            throw $this->noEncontrado('El vehículo no existe.');
        }
        return $f;
    }

    private function vistaEmpleado(array $f): array
    {
        unset($f['observaciones'], $f['fecha_registro']);
        return $f;
    }

    private function validar(Validador $v): array
    {
        $placa = $v->texto('placa', 'La placa', 20);
        if ($placa !== null) {
            $placa = strtoupper($placa);
            if (!preg_match('/^[A-Z0-9][A-Z0-9 -]{2,19}$/', $placa)) {
                $v->error('placa', 'La placa solo admite letras, números, espacios y guiones (mínimo 3 caracteres).');
            }
        }
        $anioMax = (int) substr($this->modelo->ahora(), 0, 4) + 1;
        return [
            'placa' => $placa,
            'marca' => $v->texto('marca', 'La marca', 80),
            'modelo' => $v->texto('modelo', 'El modelo', 80),
            'anio' => $v->entero('anio', 'El año', 1950, $anioMax),
            'tipo_vehiculo' => $v->texto('tipo_vehiculo', 'El tipo de vehículo', 80),
            'color' => $v->texto('color', 'El color', 50, false),
            'capacidad_carga' => $v->decimal('capacidad_carga', 'La capacidad de carga'),
            'kilometraje' => $v->entero('kilometraje', 'El kilometraje', 0, 2147483647, false) ?? 0,
            'observaciones' => $v->texto('observaciones', 'Las observaciones', 2000, false),
        ];
    }

    private function comprobarPlaca(Validador $v, ?string $placa, int $excluir = 0): void
    {
        if ($placa !== null && !$v->tieneError('placa') && $this->modelo->existePlaca($placa, $excluir)) {
            throw new AppException('Ya existe un vehículo con esa placa.', 409, ['placa' => 'Ya existe un vehículo con esa placa.']);
        }
    }
}
