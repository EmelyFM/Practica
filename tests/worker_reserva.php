<?php
/**
 * Proceso auxiliar de la prueba de concurrencia: llama a ReservaModel::crear() en el
 * instante acordado, para que varios procesos compitan por el mismo vehículo.
 */
PHP_SAPI === 'cli' || exit;
if (!str_ends_with((string) getenv('DB_NAME'), '_test')) {
    exit("Solo se ejecuta contra una base de datos de prueba.\n");
}
require_once dirname(__DIR__) . '/config/app.php';

[, $idVehiculo, $idEmpleado, $idUsuario, $inicio, $fin, $instante] = $argv;
time_sleep_until((float) $instante);
try {
    $r = (new ReservaModel())->crear([
        'id_vehiculo' => (int) $idVehiculo,
        'id_empleado' => (int) $idEmpleado,
        'fecha_inicio' => $inicio,
        'fecha_fin' => $fin,
        'motivo' => 'Prueba de concurrencia',
        'estado' => 'Pendiente',
        'observaciones' => null,
    ], (int) $idUsuario);
    echo json_encode(['ok' => true, 'codigo' => $r['codigo_reserva']]);
} catch (AppException $e) {
    echo json_encode(['ok' => false, 'http' => $e->http, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'http' => 500, 'mensaje' => get_class($e) . ' ' . $e->getCode()]);
}
