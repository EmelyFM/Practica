<?php
defined('APP') || exit;

use Dompdf\Dompdf;
use Dompdf\Options;

class ReporteController extends BaseController
{
    private const ESTADOS_RESERVA = ['Pendiente', 'Confirmada', 'En uso', 'Finalizada', 'Cancelada'];
    private const ESTADOS_MANT = ['Programado', 'En proceso', 'Finalizado', 'Cancelado'];
    private const ESTADOS_VEHICULO = ['Disponible', 'Mantenimiento', 'Fuera de servicio'];
    private const TIPOS = ['general', 'empleado', 'vehiculo', 'periodo', 'flota', 'mantenimientos', 'uso'];

    private ReporteModel $modelo;

    public function __construct()
    {
        parent::__construct();
        $this->modelo = new ReporteModel();
    }

    /** Datos del panel: el contenido depende del rol. */
    public function dashboard(): void
    {
        if ($this->esAdmin()) {
            $this->responder('Panel obtenido.', [
                'indicadores' => $this->modelo->indicadoresAdmin(),
                'recientes' => $this->modelo->reservasRecientes(),
                'mantenimientos_proximos' => (new MantenimientoModel())->proximos(),
            ]);
            return;
        }

        $idEmpleado = empty($this->usuario['id_empleado']) ? null : (int) $this->usuario['id_empleado'];
        $v = new Validador($_GET);
        $inicio = $v->fechaHora('inicio', 'La fecha de inicio', false);
        $fin = $v->fechaHora('fin', 'La fecha final', false);
        if ($inicio !== null && $fin !== null && $fin <= $inicio) {
            $v->error('fin', 'La fecha final debe ser posterior a la fecha de inicio.');
        }
        $v->verificar();

        $disponibles = null;
        if ($inicio !== null && $fin !== null) {
            $flota = (new VehiculoModel())->disponibilidad($inicio, $fin);
            $disponibles = count(array_filter($flota, fn ($f) => $f['disponible']));
        }
        $this->responder('Panel obtenido.', [
            'tiene_ficha' => $idEmpleado !== null,
            'vehiculos_disponibles' => $disponibles,
            'indicadores' => $idEmpleado !== null
                ? $this->modelo->indicadoresEmpleado($idEmpleado)
                : ['mis_reservas' => 0, 'pendientes' => 0, 'confirmadas' => 0, 'en_uso' => 0, 'devoluciones' => 0],
            'recientes' => $idEmpleado !== null ? $this->modelo->reservasRecientes($idEmpleado) : [],
            'devoluciones' => $idEmpleado !== null ? $this->modelo->devolucionesEmpleado($idEmpleado) : [],
        ]);
    }

    public function opciones(): void
    {
        $this->requerirAdmin();
        $this->responder('Opciones obtenidas.', [
            'empleados' => (new EmpleadoModel())->listar(),
            'vehiculos' => (new VehiculoModel())->listar(),
        ]);
    }

    public function pdf(): void
    {
        $this->requerirAdmin();
        $v = new Validador($_GET);
        $tipo = $v->opcion('tipo', 'El tipo de reporte', self::TIPOS);
        $f = [
            'desde' => $v->fecha('desde', 'La fecha inicial', $tipo === 'periodo'),
            'hasta' => $v->fecha('hasta', 'La fecha final', $tipo === 'periodo'),
            'id_empleado' => $v->id('id_empleado', 'El empleado', $tipo === 'empleado'),
            'id_vehiculo' => $v->id('id_vehiculo', 'El vehículo', $tipo === 'vehiculo'),
            'estado' => null,
        ];
        if ($tipo === 'mantenimientos') {
            $f['estado'] = $v->opcion('estado', 'El estado', self::ESTADOS_MANT, false);
        } elseif ($tipo === 'flota') {
            $f['estado'] = $v->opcion('estado', 'El estado', self::ESTADOS_VEHICULO, false);
        } elseif ($tipo !== 'uso') {
            $f['estado'] = $v->opcion('estado', 'El estado', self::ESTADOS_RESERVA, false);
        }
        if ($f['desde'] !== null && $f['hasta'] !== null && $f['hasta'] < $f['desde']) {
            $v->error('hasta', 'La fecha final no puede ser anterior a la inicial.');
        }
        $v->verificar();

        $reporte = $this->construir($tipo, $f);
        $ahora = $this->modelo->ahora();
        $reporte['generado'] = date('d/m/Y H:i', strtotime($ahora));
        $reporte['usuario'] = $this->usuario['nombre_usuario'];
        $reporte['sistema'] = APP_NOMBRE;

        ob_start();
        require RAIZ . '/view/reportes/pdf.php';
        $html = ob_get_clean();

        require_once RAIZ . '/vendor/dompdf/autoload.inc.php';
        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('chroot', RAIZ);
        $opciones->set('tempDir', RAIZ . '/reportes');
        $opciones->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->getCanvas()->page_text(760, 565, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.4, 0.45, 0.5]);
        $contenido = $dompdf->output();

        (new BitacoraModel())->registrar($this->idUsuario(), 'Generación de reporte', 'Reportes', "Se generó el reporte «{$reporte['titulo']}» con " . count($reporte['filas']) . ' registro(s).');

        if (ob_get_level()) {
            ob_clean();
        }
        $archivo = 'reporte_' . $tipo . '_' . date('Ymd_Hi', strtotime($ahora)) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . strlen($contenido));
        echo $contenido;
    }

    private function construir(string $tipo, array $f): array
    {
        switch ($tipo) {
            case 'general':
                return $this->reporteReservas('Reporte general de reservas', $f);
            case 'periodo':
                return $this->reporteReservas('Reservas por periodo', $f);
            case 'empleado':
                $empleado = (new EmpleadoModel())->obtener((int) $f['id_empleado']);
                if (!$empleado) {
                    throw $this->noEncontrado('El empleado seleccionado no existe.');
                }
                return $this->reporteReservas('Reservas por empleado', $f, [
                    'Empleado' => $empleado['nombre_completo'],
                    'Puesto' => $empleado['puesto'] ?: 'Sin puesto',
                    'Correo' => $empleado['correo'],
                ]);
            case 'vehiculo':
                $vehiculo = $this->exigirVehiculo((int) $f['id_vehiculo']);
                return $this->reporteReservas('Reservas por vehículo', $f, [
                    'Vehículo' => "{$vehiculo['placa']} · {$vehiculo['marca']} {$vehiculo['modelo']} ({$vehiculo['anio']})",
                    'Tipo' => $vehiculo['tipo_vehiculo'],
                    'Estado actual' => $vehiculo['estado'],
                ]);
            case 'flota':
                return $this->reporteFlota($f);
            case 'mantenimientos':
                return $this->reporteMantenimientos($f);
            default:
                return $this->reporteUso($f);
        }
    }

    private function reporteReservas(string $titulo, array $f, array $encabezado = []): array
    {
        $filas = $this->modelo->reservas($f);
        $conteo = array_count_values(array_column($filas, 'estado'));
        $resumen = ['Total de reservas' => count($filas)];
        foreach (self::ESTADOS_RESERVA as $estado) {
            $resumen[$estado] = $conteo[$estado] ?? 0;
        }
        if ($f['estado']) {
            $encabezado['Estado filtrado'] = $f['estado'];
        }
        return [
            'titulo' => $titulo,
            'periodo' => $this->textoPeriodo($f),
            'encabezado' => $encabezado + $this->filtroEntidades($f, $encabezado),
            'columnas' => ['Código', 'Empleado', 'Vehículo', 'Inicio', 'Fin', 'Estado', 'Devolución', 'Motivo / observaciones'],
            'anchos' => [11, 14, 15, 9, 9, 7, 11, 24],
            'filas' => array_map(fn ($r) => [
                $r['codigo_reserva'],
                $r['empleado'],
                $r['vehiculo'],
                $this->fh($r['fecha_inicio']),
                $this->fh($r['fecha_fin']),
                $r['estado'],
                $r['fecha_devolucion'] ? $r['estado_devolucion'] . ' ' . $this->fh($r['fecha_devolucion']) : '—',
                $r['motivo'] . ($r['observaciones'] ? "\n" . $r['observaciones'] : ''),
            ], $filas),
            'resumen' => $resumen,
        ];
    }

    private function reporteFlota(array $f): array
    {
        $estados = $f['estado'] ? [$f['estado']] : ['Disponible', 'Fuera de servicio'];
        $filas = $this->modelo->vehiculos($estados);
        $flota = $this->modelo->conteoFlota();
        return [
            'titulo' => 'Vehículos disponibles y fuera de servicio',
            'periodo' => null,
            'encabezado' => ['Estados incluidos' => implode(', ', $estados)],
            'columnas' => ['Placa', 'Marca y modelo', 'Año', 'Tipo', 'Color', 'Capacidad', 'Kilometraje', 'Estado', 'Observaciones'],
            'anchos' => [9, 17, 5, 11, 7, 8, 8, 10, 25],
            'filas' => array_map(fn ($r) => [
                $r['placa'],
                "{$r['marca']} {$r['modelo']}",
                $r['anio'],
                $r['tipo_vehiculo'],
                $r['color'] ?: '—',
                $r['capacidad_carga'] !== null ? number_format((float) $r['capacidad_carga'], 2) : '—',
                number_format((int) $r['kilometraje']) . ' km',
                $r['estado'],
                $r['observaciones'] ?: '—',
            ], $filas),
            'resumen' => [
                'Vehículos listados' => count($filas),
                'Flota: disponibles' => (int) ($flota['Disponible'] ?? 0),
                'Flota: en mantenimiento' => (int) ($flota['Mantenimiento'] ?? 0),
                'Flota: fuera de servicio' => (int) ($flota['Fuera de servicio'] ?? 0),
            ],
        ];
    }

    private function reporteMantenimientos(array $f): array
    {
        $filas = $this->modelo->mantenimientos($f);
        $encabezado = $this->filtroEntidades($f);
        if ($f['estado']) {
            $encabezado['Estado filtrado'] = $f['estado'];
        }
        $costo = array_sum(array_map(fn ($r) => (float) $r['costo'], $filas));
        $tipos = array_count_values(array_column($filas, 'tipo_mantenimiento'));
        return [
            'titulo' => 'Reporte de mantenimientos',
            'periodo' => $this->textoPeriodo($f),
            'encabezado' => $encabezado,
            'columnas' => ['Vehículo', 'Tipo', 'Descripción', 'Inicio', 'Fin', 'Responsable', 'Costo', 'Estado', 'Observaciones'],
            'anchos' => [14, 7, 21, 9, 9, 8, 7, 7, 18],
            'filas' => array_map(fn ($r) => [
                $r['vehiculo'],
                $r['tipo_mantenimiento'],
                $r['descripcion'],
                $this->fh($r['fecha_inicio']),
                $r['fecha_fin'] ? $this->fh($r['fecha_fin']) : 'Abierto',
                $r['responsable'],
                '$' . number_format((float) $r['costo'], 2),
                $r['estado'],
                $r['observaciones'] ?: '—',
            ], $filas),
            'resumen' => [
                'Total de mantenimientos' => count($filas),
                'Preventivos' => $tipos['Preventivo'] ?? 0,
                'Correctivos' => $tipos['Correctivo'] ?? 0,
                'Costo total' => '$' . number_format($costo, 2),
            ],
        ];
    }

    private function reporteUso(array $f): array
    {
        $filas = $this->modelo->historialUso($f);
        $minutos = array_sum(array_map(fn ($r) => (int) $r['minutos_uso'], $filas));
        $anticipadas = count(array_filter($filas, fn ($r) => $r['fecha_devolucion'] && $r['fecha_devolucion'] < $r['fecha_fin']));
        return [
            'titulo' => 'Historial de uso de vehículos',
            'periodo' => $this->textoPeriodo($f),
            'encabezado' => $this->filtroEntidades($f),
            'columnas' => ['Vehículo', 'Código', 'Empleado', 'Inicio', 'Fin reservado', 'Devolución real', 'Tiempo de uso', 'Estado', 'Observaciones'],
            'anchos' => [14, 11, 13, 8, 8, 8, 7, 8, 23],
            'filas' => array_map(fn ($r) => [
                $r['vehiculo'],
                $r['codigo_reserva'],
                $r['empleado'],
                $this->fh($r['fecha_inicio']),
                $this->fh($r['fecha_fin']),
                $r['fecha_devolucion'] ? $this->fh($r['fecha_devolucion']) : 'En uso',
                $this->duracion((int) $r['minutos_uso']),
                $r['estado_devolucion'],
                $r['observaciones'] ?: '—',
            ], $filas),
            'resumen' => [
                'Usos registrados' => count($filas),
                'Tiempo total de uso' => $this->duracion($minutos),
                'Devoluciones anticipadas' => $anticipadas,
            ],
        ];
    }

    private function exigirVehiculo(int $id): array
    {
        $v = (new VehiculoModel())->obtener($id);
        if (!$v) {
            throw $this->noEncontrado('El vehículo seleccionado no existe.');
        }
        return $v;
    }

    /** Hace visibles en el PDF los filtros opcionales aplicados. */
    private function filtroEntidades(array $f, array $yaIncluidos = []): array
    {
        $datos = [];
        if (!empty($f['id_vehiculo']) && !isset($yaIncluidos['Vehículo'])) {
            $v = $this->exigirVehiculo((int) $f['id_vehiculo']);
            $datos['Vehículo'] = "{$v['placa']} · {$v['marca']} {$v['modelo']}";
        }
        if (!empty($f['id_empleado']) && !isset($yaIncluidos['Empleado'])) {
            $e = (new EmpleadoModel())->obtener((int) $f['id_empleado']);
            if (!$e) {
                throw $this->noEncontrado('El empleado seleccionado no existe.');
            }
            $datos['Empleado'] = $e['nombre_completo'];
        }
        return $datos;
    }

    private function textoPeriodo(array $f): string
    {
        if ($f['desde'] && $f['hasta']) {
            return $this->fd($f['desde']) . ' a ' . $this->fd($f['hasta']);
        }
        if ($f['desde']) {
            return 'Desde ' . $this->fd($f['desde']);
        }
        if ($f['hasta']) {
            return 'Hasta ' . $this->fd($f['hasta']);
        }
        return 'Todos los registros';
    }

    private function fh(string $fecha): string
    {
        return date('d/m/Y H:i', strtotime($fecha));
    }

    private function fd(string $fecha): string
    {
        return date('d/m/Y', strtotime($fecha));
    }

    private function duracion(int $minutos): string
    {
        return sprintf('%d h %02d min', intdiv($minutos, 60), $minutos % 60);
    }
}
