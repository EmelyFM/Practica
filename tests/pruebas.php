<?php
/**
 * Pruebas de extremo a extremo. Uso:  C:\xampp\php\php.exe tests\pruebas.php [--conservar]
 *
 * Crea un clon temporal del esquema (gestion_reservas_vehiculos_test), levanta un servidor
 * PHP apuntando a ese clon mediante la variable DB_NAME que ya admite Conexion y ejercita
 * la API real por HTTP. La base de datos real no se modifica. Al terminar borra el clon,
 * salvo con --conservar, que lo deja para revisar la interfaz con datos.
 * Genera docs/evidencias/resultados_pruebas.md, respuestas_json.md y los PDF de muestra.
 */
PHP_SAPI === 'cli' || exit;

const BD_REAL = 'gestion_reservas_vehiculos';
const BD_TEST = 'gestion_reservas_vehiculos_test';
const PUERTO = 8099;
const URL_BASE = 'http://127.0.0.1:' . PUERTO;

define('RAIZ_T', dirname(__DIR__));
date_default_timezone_set('UTC');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once RAIZ_T . '/config/conexion.php';

$conservar = in_array('--conservar', $argv, true);
$dirEvid = RAIZ_T . '/docs/evidencias';
@mkdir($dirEvid . '/pdf', 0777, true);

/* ---------- Clon temporal ---------- */

$db = (new Conexion())->conectar();
if ($db->query('SELECT DATABASE()')->fetch_row()[0] !== BD_REAL) {
    exit("La conexión no apunta a la base esperada.\n");
}
$db->query('DROP DATABASE IF EXISTS `' . BD_TEST . '`');
$db->query('CREATE DATABASE `' . BD_TEST . '` DEFAULT CHARACTER SET utf8');
$definiciones = [];
foreach (['roles', 'usuarios', 'empleados', 'vehiculos', 'reservas', 'mantenimientos', 'bitacora'] as $t) {
    $definiciones[] = $db->query("SHOW CREATE TABLE `$t`")->fetch_row()[1];
}
$db->select_db(BD_TEST);
foreach ($definiciones as $sql) {
    $db->query($sql);
}

function sql(string $consulta): array
{
    global $db;
    $r = $db->query($consulta);
    return $r === true ? [] : $r->fetch_all(MYSQLI_ASSOC);
}

function ahora(string $mod = '+0 seconds'): string
{
    return date('Y-m-d H:i:s', strtotime($mod, strtotime(sql('SELECT NOW() AS n')[0]['n'])));
}

/* ---------- Servidor ---------- */

$entorno = array_merge(getenv(), ['DB_NAME' => BD_TEST]);
$registroServidor = sys_get_temp_dir() . '/grv_servidor_pruebas.log';
$servidor = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . PUERTO, '-t', RAIZ_T, '-d', 'error_log=' . $registroServidor],
    [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']],
    $pipes, RAIZ_T, $entorno
);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', PUERTO, $en, $es, 0.2); $i++) {
    usleep(100000);
}

/* ---------- Cliente HTTP y aserciones ---------- */

class Cliente
{
    public string $csrf = '';
    private string $cookies;

    public function __construct()
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'grv');
    }

    public function crudo(string $metodo, string $url, ?string $cuerpo = null, array $cabeceras = []): array
    {
        $c = curl_init(URL_BASE . $url);
        curl_setopt_array($c, [
            CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookies, CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_HTTPHEADER => $cabeceras, CURLOPT_TIMEOUT => 60,
        ]);
        if ($cuerpo !== null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, $cuerpo);
        }
        $r = curl_exec($c);
        $tam = curl_getinfo($c, CURLINFO_HEADER_SIZE);
        $estado = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return ['status' => $estado, 'headers' => substr($r, 0, $tam), 'body' => substr($r, $tam)];
    }

    public function pagina(string $q = ''): array
    {
        $r = $this->crudo('GET', '/index.php' . $q);
        if (preg_match('/name="csrf-token" content="([^"]*)"/', $r['body'], $m)) {
            $this->csrf = $m[1];
        }
        return $r;
    }

    public function api(string $metodo, string $ruta, ?array $datos = null, array $parametros = [], ?string $csrf = null): array
    {
        $url = '/api/index.php?' . http_build_query(['ruta' => $ruta] + $parametros);
        $cab = ['Accept: application/json'];
        $cuerpo = null;
        if ($metodo !== 'GET') {
            $cab[] = 'Content-Type: application/json';
            $cab[] = 'X-CSRF-Token: ' . ($csrf ?? $this->csrf);
            $cuerpo = json_encode($datos ?? []);
        }
        $r = $this->crudo($metodo, $url, $cuerpo, $cab);
        $r['json'] = json_decode($r['body'], true);
        return $r;
    }

    public function entrar(string $usuario, string $clave): array
    {
        $this->pagina();
        $r = $this->api('POST', 'auth/login', ['nombre_usuario' => $usuario, 'contrasena' => $clave]);
        if ($r['status'] === 200) {
            $this->pagina();
        }
        return $r;
    }

    public function cookie(): string
    {
        return preg_match('/GRV_SESION\s+(\S+)/', (string) @file_get_contents($this->cookies), $m) ? $m[1] : '';
    }
}

$resultados = [];
$seccion = '';
$fallos = 0;
$muestras = [];

function grupo(string $nombre): void
{
    global $seccion;
    $seccion = $nombre;
    echo "\n== $nombre ==\n";
}

function t(string $nombre, bool $ok, $detalle = ''): void
{
    global $resultados, $seccion, $fallos;
    $resultados[] = [$seccion, $nombre, $ok, $ok ? '' : (is_string($detalle) ? $detalle : json_encode($detalle, JSON_UNESCAPED_UNICODE))];
    if (!$ok) {
        $fallos++;
    }
    echo($ok ? '  ok    ' : '  FALLO ') . $nombre . ($ok ? '' : '  -> ' . end($resultados)[3]) . "\n";
}

/** Comprueba el código HTTP y que la respuesta sea JSON con el formato acordado. */
function res(string $nombre, array $r, int $http, ?bool $exito = null): bool
{
    $exito ??= $http < 400;
    $j = $r['json'] ?? null;
    $ok = $r['status'] === $http && is_array($j) && ($j['success'] ?? null) === $exito && is_string($j['message'] ?? null);
    t($nombre, $ok, "HTTP {$r['status']} " . mb_substr($r['body'], 0, 220));
    return $ok;
}

function muestra(string $titulo, string $peticion, array $r): void
{
    global $muestras;
    $muestras[] = [$titulo, $peticion, $r['status'], $r['json']];
}

function tiene_error(array $r, string $campo): bool
{
    return isset($r['json']['errors'][$campo]);
}

$clave = 'Pr-' . bin2hex(random_bytes(6)) . 'Az9!';

try {
    /* ---------- Primer arranque ---------- */
    grupo('Configuración inicial');
    $anon = new Cliente();
    $p = $anon->pagina();
    t('Sin usuarios se muestra la configuración inicial', $p['status'] === 200 && str_contains($p['body'], 'formSetup'));
    res('Creación sin token CSRF rechazada', $anon->api('POST', 'auth/primer-admin', ['nombre_usuario' => 'x'], [], ''), 403);
    $r = $anon->api('POST', 'auth/primer-admin', ['nombre_usuario' => 'admin', 'correo' => 'admin@empresa.test', 'contrasena' => '123']);
    res('Contraseña débil rechazada', $r, 422);
    t('El error llega por campo', tiene_error($r, 'contrasena'));
    $r = $anon->api('POST', 'auth/primer-admin', ['nombre_usuario' => 'admin', 'correo' => 'admin@empresa.test', 'contrasena' => $clave]);
    res('Primer administrador creado', $r, 201);
    t('Se crearon los dos roles', count(sql('SELECT id_rol FROM roles')) === 2);
    res('Una segunda creación queda bloqueada', $anon->api('POST', 'auth/primer-admin', ['nombre_usuario' => 'otro', 'correo' => 'otro@empresa.test', 'contrasena' => $clave]), 403);
    t('La contraseña se guarda con hash', password_verify($clave, sql("SELECT contrasena_hash h FROM usuarios WHERE nombre_usuario='admin'")[0]['h']));

    /* ---------- Autenticación ---------- */
    grupo('Autenticación');
    $anon = new Cliente();
    $p = $anon->pagina();
    t('Ya configurado se muestra el login', str_contains($p['body'], 'formLogin'));
    $r = $anon->api('POST', 'auth/login', ['nombre_usuario' => 'admin', 'contrasena' => 'incorrecta']);
    res('Contraseña incorrecta', $r, 401);
    muestra('Error de inicio de sesión', 'POST auth/login', $r);
    $msgMal = $r['json']['message'];
    $r = $anon->api('POST', 'auth/login', ['nombre_usuario' => 'noexiste', 'contrasena' => 'x']);
    res('Usuario inexistente', $r, 401);
    t('El mensaje no revela si el usuario existe', $r['json']['message'] === $msgMal);
    res('Campos vacíos', $anon->api('POST', 'auth/login', []), 422);
    res('Inyección SQL en el login rechazada', $anon->api('POST', 'auth/login', ['nombre_usuario' => "' OR '1'='1' -- ", 'contrasena' => "' OR '1'='1"]), 401);
    res('Login sin CSRF rechazado', $anon->api('POST', 'auth/login', ['nombre_usuario' => 'admin', 'contrasena' => $clave], [], 'malo'), 403);

    $admin = new Cliente();
    $admin->pagina();
    $antes = $admin->cookie();
    $r = $admin->api('POST', 'auth/login', ['nombre_usuario' => 'admin', 'contrasena' => $clave]);
    res('Inicio de sesión correcto', $r, 200);
    muestra('Inicio de sesión', 'POST auth/login', $r);
    t('Regenera el identificador de sesión', $antes !== '' && $admin->cookie() !== '' && $admin->cookie() !== $antes);
    t('Identifica el rol', ($r['json']['data']['rol'] ?? '') === 'Administrador');
    $p = $admin->pagina();
    t('Con sesión se abre el panel', $p['status'] === 200 && str_contains($p['body'], 'dashboard.js'));
    t('Páginas protegidas sin caché (botón Atrás)', (bool) preg_match('/Cache-Control:.*no-store/i', $p['headers']));

    $rutasProtegidas = ['dashboard', 'usuarios', 'usuarios/roles', 'usuarios/1', 'empleados', 'empleados/1', 'vehiculos', 'vehiculos/1', 'vehiculos/1/reservas', 'reservas', 'reservas/1', 'reservas/disponibilidad', 'mantenimientos', 'mantenimientos/proximos', 'reportes/opciones', 'reportes/pdf', 'bitacora'];
    $sin = new Cliente();
    $bien = true;
    foreach ($rutasProtegidas as $ruta) {
        $x = $sin->api('GET', $ruta);
        $bien = $bien && $x['status'] === 401 && $x['json']['success'] === false;
    }
    t('Acceso sin sesión: 401 en las ' . count($rutasProtegidas) . ' rutas de consulta', $bien);
    $sin->pagina();
    $x = $sin->api('POST', 'vehiculos', ['placa' => 'X']);
    res('Escritura sin sesión', $x, 401);
    muestra('Acceso sin sesión', 'POST vehiculos', $x);
    $p = $sin->pagina('?page=usuarios');
    t('Página protegida sin sesión muestra el login', str_contains($p['body'], 'formLogin') && !str_contains($p['body'], 'usuarios.js'));

    /* ---------- Usuarios ---------- */
    grupo('Usuarios');
    $rolAdmin = (int) sql("SELECT id_rol FROM roles WHERE nombre_rol='Administrador'")[0]['id_rol'];
    $rolEmp = (int) sql("SELECT id_rol FROM roles WHERE nombre_rol='Empleado'")[0]['id_rol'];
    $u = fn ($n, $extra = []) => $extra + ['nombre_usuario' => $n, 'correo' => "$n@empresa.test", 'contrasena' => $clave, 'id_rol' => $rolEmp];

    $r = $admin->api('POST', 'usuarios', $u('ana.lopez'));
    res('Registro válido', $r, 201);
    muestra('Usuario registrado', 'POST usuarios', $r);
    $idAna = $r['json']['data']['id_usuario'];
    $idLuis = $admin->api('POST', 'usuarios', $u('luis.mora'))['json']['data']['id_usuario'];
    $idPaz = $admin->api('POST', 'usuarios', $u('paz.rios'))['json']['data']['id_usuario'];
    $r = $admin->api('POST', 'usuarios', []);
    res('Campos vacíos', $r, 422);
    muestra('Error de validación por campo', 'POST usuarios', $r);
    t('Marca todos los obligatorios', tiene_error($r, 'nombre_usuario') && tiene_error($r, 'correo') && tiene_error($r, 'contrasena') && tiene_error($r, 'id_rol'));
    $r = $admin->api('POST', 'usuarios', $u('otro', ['correo' => 'ana.lopez@empresa.test']));
    res('Correo duplicado', $r, 409);
    t('Duplicado señalado en el campo correo', tiene_error($r, 'correo'));
    $r = $admin->api('POST', 'usuarios', $u('ANA.LOPEZ', ['correo' => 'nuevo@empresa.test']));
    res('Usuario duplicado (sin distinguir mayúsculas)', $r, 409);
    res('Correo inválido', $admin->api('POST', 'usuarios', $u('x1', ['correo' => 'no-es-correo'])), 422);
    res('Contraseña insegura', $admin->api('POST', 'usuarios', $u('x2', ['contrasena' => 'abcdefgh'])), 422);
    res('Rol inexistente', $admin->api('POST', 'usuarios', $u('x3', ['id_rol' => 9999])), 422);
    $l = $admin->api('GET', 'usuarios');
    res('Consulta de usuarios', $l, 200);
    t('Las respuestas no contienen contraseñas ni hashes', !preg_match('/contrasena|\$2y\$/', $l['body']));
    res('Actualización válida', $admin->api('PUT', "usuarios/$idAna", ['nombre_usuario' => 'ana.lopez', 'correo' => 'ana@empresa.test', 'id_rol' => $rolEmp]), 200);
    t('Se guardó el cambio', sql("SELECT correo FROM usuarios WHERE id_usuario=$idAna")[0]['correo'] === 'ana@empresa.test');
    res('Usuario inexistente', $admin->api('PUT', 'usuarios/99999', ['nombre_usuario' => 'zz1', 'correo' => 'z@empresa.test', 'id_rol' => $rolEmp]), 404);
    $idAdmin = (int) sql("SELECT id_usuario i FROM usuarios WHERE nombre_usuario='admin'")[0]['i'];
    $r = $admin->api('PATCH', "usuarios/$idAdmin/estado", ['estado' => 'Inactivo']);
    res('No se puede desactivar al último administrador', $r, 409);
    res('No se puede quitar el rol al último administrador', $admin->api('PUT', "usuarios/$idAdmin", ['nombre_usuario' => 'admin', 'correo' => 'admin@empresa.test', 'id_rol' => $rolEmp]), 409);
    $otroAdmin = $admin->api('POST', 'usuarios', $u('admin2', ['id_rol' => $rolAdmin]))['json']['data']['id_usuario'];
    res('Con otro administrador sí se puede desactivar', $admin->api('PATCH', "usuarios/$otroAdmin/estado", ['estado' => 'Inactivo']), 200);
    $nueva = 'Nv-' . bin2hex(random_bytes(5)) . 'Bz8!';
    res('Actualización de contraseña', $admin->api('PATCH', "usuarios/$idPaz/contrasena", ['contrasena' => $nueva]), 200);
    $paz = new Cliente();
    res('La contraseña anterior deja de servir', $paz->entrar('paz.rios', $clave), 401);
    res('La nueva contraseña funciona', $paz->entrar('paz.rios', $nueva), 200);
    $tmp = new Cliente();
    res('Usuario inactivo no puede entrar', $tmp->entrar('admin2', $clave), 403);
    res('Desactivar a un usuario', $admin->api('PATCH', "usuarios/$idPaz/estado", ['estado' => 'Inactivo']), 200);
    res('Su sesión abierta se invalida de inmediato', $paz->api('GET', 'reservas'), 401);
    $admin->api('PATCH', "usuarios/$idPaz/estado", ['estado' => 'Activo']);

    /* ---------- Empleados ---------- */
    grupo('Empleados');
    $e = fn ($idU, $extra = []) => $extra + ['id_usuario' => $idU, 'nombre' => 'Ana María', 'apellido_paterno' => 'López', 'apellido_materno' => 'Núñez', 'telefono' => '+52 55 1234-567', 'puesto' => 'Chofer', 'fecha_ingreso' => '2024-03-01'];
    $r = $admin->api('POST', 'empleados', $e($idAna));
    res('Registro válido', $r, 201);
    muestra('Empleado registrado', 'POST empleados', $r);
    $empAna = $r['json']['data']['id_empleado'];
    $empLuis = $admin->api('POST', 'empleados', $e($idLuis, ['nombre' => 'Luis', 'apellido_paterno' => 'Mora', 'apellido_materno' => '']))['json']['data']['id_empleado'];
    $r = $admin->api('POST', 'empleados', $e($idPaz, ['nombre' => 'Ana1']));
    res('Nombre con números', $r, 422);
    t('Error debajo del campo nombre', tiene_error($r, 'nombre'));
    res('Apellido con números', $admin->api('POST', 'empleados', $e($idPaz, ['apellido_paterno' => 'L0pez'])), 422);
    res('Teléfono inválido', $admin->api('POST', 'empleados', $e($idPaz, ['telefono' => 'abc'])), 422);
    res('Usuario relacionado obligatorio', $admin->api('POST', 'empleados', ['nombre' => 'Sin', 'apellido_paterno' => 'Usuario']), 422);
    res('Usuario relacionado inexistente', $admin->api('POST', 'empleados', $e(9999)), 422);
    res('Dos empleados no pueden compartir usuario', $admin->api('POST', 'empleados', $e($idAna, ['nombre' => 'Otra'])), 409);
    res('Campos vacíos', $admin->api('POST', 'empleados', []), 422);
    res('Actualización válida', $admin->api('PUT', "empleados/$empAna", $e($idAna, ['puesto' => 'Supervisora'])), 200);
    res('Consultar detalles', $d = $admin->api('GET', "empleados/$empAna"), 200);
    t('Conserva acentos y datos', ($d['json']['data']['nombre_completo'] ?? '') === 'Ana María López Núñez' && $d['json']['data']['puesto'] === 'Supervisora');
    res('Empleado inexistente', $admin->api('GET', 'empleados/99999'), 404);
    $disp = $admin->api('GET', 'empleados/usuarios-disponibles');
    t('Usuarios libres excluye a los ya asignados', !in_array($idAna, array_column($disp['json']['data'], 'id_usuario')));

    $ana = new Cliente();
    res('Empleado inicia sesión', $ana->entrar('ana.lopez', $clave), 200);
    $luis = new Cliente();
    $luis->entrar('luis.mora', $clave);
    $paz = new Cliente();
    $paz->entrar('paz.rios', $nueva);

    grupo('Permisos por rol');
    foreach (['usuarios', 'empleados', 'mantenimientos', 'bitacora', 'reportes/opciones'] as $ruta) {
        res("Empleado no accede a $ruta", $ana->api('GET', $ruta), 403);
    }
    $x = $ana->api('POST', 'usuarios', $u('colado'));
    res('Empleado no crea usuarios', $x, 403);
    muestra('Permiso denegado', 'POST usuarios (rol Empleado)', $x);
    res('Empleado no genera PDF', $ana->api('GET', 'reportes/pdf', null, ['tipo' => 'general']), 403);
    foreach (['usuarios', 'empleados', 'mantenimientos', 'reportes', 'bitacora'] as $pg) {
        $p = $ana->pagina("?page=$pg");
        t("Página $pg denegada al empleado (403)", $p['status'] === 403);
    }
    $ana->pagina();
    $p = $admin->pagina('?page=noexiste');
    t('Página inexistente (404)', $p['status'] === 404);
    $admin->pagina();

    /* ---------- Vehículos ---------- */
    grupo('Vehículos');
    $v = fn ($placa, $extra = []) => $extra + ['placa' => $placa, 'marca' => 'Volvo', 'modelo' => 'FH', 'anio' => 2022, 'tipo_vehiculo' => 'Tractocamión', 'color' => 'Blanco', 'capacidad_carga' => '18000.50', 'kilometraje' => 12000, 'observaciones' => 'Nota interna'];
    $r = $admin->api('POST', 'vehiculos', $v('abc-101'));
    res('Registro válido', $r, 201);
    muestra('Vehículo registrado', 'POST vehiculos', $r);
    $v1 = $r['json']['data']['id_vehiculo'];
    t('La placa se normaliza en mayúsculas', sql("SELECT placa FROM vehiculos WHERE id_vehiculo=$v1")[0]['placa'] === 'ABC-101');
    $crear = fn ($p) => $admin->api('POST', 'vehiculos', $v($p))['json']['data']['id_vehiculo'];
    $v2 = $crear('ABC-102'); $v3 = $crear('ABC-103'); $v4 = $crear('ABC-104'); $v5 = $crear('ABC-105'); $v6 = $crear('ABC-106');
    $r = $admin->api('POST', 'vehiculos', $v('ABC-101'));
    res('Placa duplicada', $r, 409);
    t('Duplicado señalado en el campo placa', tiene_error($r, 'placa'));
    $r = $admin->api('POST', 'vehiculos', $v('NEG-1', ['kilometraje' => -5]));
    res('Kilometraje negativo', $r, 422);
    t('Mensaje específico de negativo', str_contains($r['json']['errors']['kilometraje'] ?? '', 'negativo'), $r['json']);
    res('Capacidad negativa', $admin->api('POST', 'vehiculos', $v('NEG-2', ['capacidad_carga' => '-1'])), 422);
    res('Año inválido', $admin->api('POST', 'vehiculos', $v('NEG-3', ['anio' => 1800])), 422);
    res('Campos obligatorios', $admin->api('POST', 'vehiculos', []), 422);
    res('Edición correcta', $admin->api('PUT', "vehiculos/$v1", $v('ABC-101', ['kilometraje' => 13000])), 200);
    t('Se guardó la edición', (int) sql("SELECT kilometraje k FROM vehiculos WHERE id_vehiculo=$v1")[0]['k'] === 13000);
    res('Placa de otro vehículo al editar', $admin->api('PUT', "vehiculos/$v2", $v('ABC-101')), 409);
    res('Empleado no registra vehículos', $ana->api('POST', 'vehiculos', $v('EMP-1')), 403);
    res('Empleado no cambia estados', $ana->api('PATCH', "vehiculos/$v1/estado", ['estado' => 'Mantenimiento']), 403);
    res('Empleado no borra vehículos', $ana->api('DELETE', "vehiculos/$v1"), 403);
    res('Empleado no ve historiales', $ana->api('GET', "vehiculos/$v1/reservas"), 403);
    $cat = $ana->api('GET', 'vehiculos');
    res('Empleado consulta el catálogo', $cat, 200);
    t('El catálogo oculta observaciones internas', !str_contains($cat['body'], 'Nota interna'));
    res('Estado inválido', $admin->api('PATCH', "vehiculos/$v5/estado", ['estado' => 'Volando']), 422);
    res('Cambio de estado', $admin->api('PATCH', "vehiculos/$v5/estado", ['estado' => 'Fuera de servicio']), 200);
    $vb = $crear('DEL-1');
    res('Elimina un vehículo sin historial', $admin->api('DELETE', "vehiculos/$vb"), 200);
    res('Vehículo inexistente', $admin->api('GET', 'vehiculos/99999'), 404);

    /* ---------- Reservas ---------- */
    grupo('Reservas');
    $res = fn ($idV, $ini, $fin, $extra = []) => $extra + ['id_vehiculo' => $idV, 'fecha_inicio' => $ini, 'fecha_fin' => $fin, 'motivo' => 'Entrega a cliente'];
    $f1 = ahora('+2 days'); $f2 = ahora('+2 days +4 hours');

    $r = $ana->api('POST', 'reservas', $res($v1, $f1, $f2));
    res('Reserva válida', $r, 201);
    muestra('Reserva registrada', 'POST reservas', $r);
    $r1 = $r['json']['data']['id_reserva'];
    t('Código automático con formato', (bool) preg_match('/^RES-\d{8}-[0-9A-F]{6}$/', $r['json']['data']['codigo_reserva']));
    $fila = sql("SELECT * FROM reservas WHERE id_reserva=$r1")[0];
    t('Usuario tomado de la sesión y empleado propio', (int) $fila['id_usuario'] === $idAna && (int) $fila['id_empleado'] === $empAna);
    t('Queda Pendiente', $fila['estado'] === 'Pendiente');
    $x = $ana->api('POST', 'reservas', $res($v2, $f2, $f1));
    res('Fechas invertidas', $x, 422);
    t('Error en fecha_fin', tiene_error($x, 'fecha_fin'));
    res('Fechas iguales', $ana->api('POST', 'reservas', $res($v2, $f1, $f1)), 422);
    res('Fecha inválida', $ana->api('POST', 'reservas', $res($v2, '2026-02-31 10:00', $f2)), 422);
    res('Inicio en el pasado', $ana->api('POST', 'reservas', $res($v2, ahora('-2 days'), ahora('-1 day'))), 422);
    res('Motivo obligatorio', $ana->api('POST', 'reservas', ['id_vehiculo' => $v2, 'fecha_inicio' => $f1, 'fecha_fin' => $f2]), 422);
    $x = $ana->api('POST', 'reservas', $res(99999, $f1, $f2));
    res('Vehículo inexistente', $x, 404);
    $x = $luis->api('POST', 'reservas', $res($v1, ahora('+2 days +1 hour'), ahora('+2 days +2 hours')));
    res('Horario solapado (dentro)', $x, 409);
    muestra('Conflicto de disponibilidad', 'POST reservas', $x);
    res('Horario solapado (cubre el periodo)', $luis->api('POST', 'reservas', $res($v1, ahora('+2 days -1 hour'), ahora('+2 days +5 hours'))), 409);
    res('Horario solapado (por el inicio)', $luis->api('POST', 'reservas', $res($v1, ahora('+2 days -1 hour'), ahora('+2 days +1 hour'))), 409);
    res('Horario solapado (por el final)', $luis->api('POST', 'reservas', $res($v1, ahora('+2 days +3 hours'), ahora('+2 days +6 hours'))), 409);
    $x = $luis->api('POST', 'reservas', $res($v1, $f2, ahora('+2 days +6 hours')));
    res('Periodo contiguo: permitido (fin = inicio)', $x, 201);
    $luis->api('PATCH', "reservas/{$x['json']['data']['id_reserva']}/cancelar", []);
    res('Vehículo fuera de servicio', $ana->api('POST', 'reservas', $res($v5, $f1, $f2)), 409);
    $x = $ana->api('POST', 'reservas', $res($v2, $f1, $f2, ['id_empleado' => $empLuis]));
    res('Empleado no reserva a nombre de otro', $x, 403);
    $x = $ana->api('POST', 'reservas', $res($v2, ahora('+3 days'), ahora('+3 days +1 hour'), ['estado' => 'Confirmada']));
    res('Empleado envía estado Confirmada', $x, 201);
    t('El servidor ignora ese estado', sql("SELECT estado FROM reservas WHERE id_reserva={$x['json']['data']['id_reserva']}")[0]['estado'] === 'Pendiente');
    $r2 = $x['json']['data']['id_reserva'];
    res('Usuario sin ficha de empleado no reserva', $paz->api('POST', 'reservas', $res($v3, $f1, $f2)), 403);
    res('Administrador sin empleado indicado', $admin->api('POST', 'reservas', $res($v3, $f1, $f2)), 422);
    $x = $admin->api('POST', 'reservas', $res($v3, $f1, $f2, ['id_empleado' => $empLuis, 'estado' => 'Confirmada']));
    res('Administrador asigna a otro empleado y confirma', $x, 201);
    $r3 = $x['json']['data']['id_reserva'];
    t('Registrada por el administrador a nombre del empleado', (int) sql("SELECT id_usuario u FROM reservas WHERE id_reserva=$r3")[0]['u'] === $idAdmin && (int) sql("SELECT id_empleado u FROM reservas WHERE id_reserva=$r3")[0]['u'] === $empLuis);
    res('Empleado inexistente', $admin->api('POST', 'reservas', $res($v4, $f1, $f2, ['id_empleado' => 9999])), 422);

    grupo('Disponibilidad');
    $q = $ana->api('GET', 'reservas/disponibilidad', null, ['fecha_inicio' => $f1, 'fecha_fin' => $f2]);
    res('Consulta de disponibilidad', $q, 200);
    muestra('Disponibilidad', 'GET reservas/disponibilidad', ['status' => $q['status'], 'json' => ['success' => true, 'message' => $q['json']['message'], 'data' => ['inicio' => $q['json']['data']['inicio'], 'fin' => $q['json']['data']['fin'], 'disponibles' => $q['json']['data']['disponibles'], 'vehiculos' => array_slice($q['json']['data']['vehiculos'], 0, 2)]]]);
    $porId = array_column($q['json']['data']['vehiculos'], null, 'id_vehiculo');
    t('Excluye el vehículo reservado', $porId[$v1]['disponible'] === false && $porId[$v1]['motivo'] === 'Reservado en el periodo');
    t('Excluye el vehículo fuera de servicio', $porId[$v5]['disponible'] === false && $porId[$v5]['motivo'] === 'Fuera de servicio');
    t('Incluye un vehículo libre', $porId[$v4]['disponible'] === true);
    t('Cuenta correcta', $q['json']['data']['disponibles'] === count(array_filter($q['json']['data']['vehiculos'], fn ($f) => $f['disponible'])));
    t('No expone datos de otras reservas', !str_contains($q['body'], 'RES-'));
    res('Periodo invertido', $ana->api('GET', 'reservas/disponibilidad', null, ['fecha_inicio' => $f2, 'fecha_fin' => $f1]), 422);
    res('Periodo vacío', $ana->api('GET', 'reservas/disponibilidad'), 422);

    grupo('Reservas ajenas');
    res('Consultar una reserva ajena', $luis->api('GET', "reservas/$r1"), 403);
    $x = $luis->api('PUT', "reservas/$r1", $res($v1, $f1, $f2));
    res('Modificar una reserva ajena', $x, 403);
    muestra('Intento sobre reserva ajena', "PUT reservas/$r1", $x);
    res('Cancelar una reserva ajena', $luis->api('PATCH', "reservas/$r1/cancelar", []), 403);
    res('Iniciar una reserva ajena', $luis->api('PATCH', "reservas/$r1/iniciar", []), 403);
    res('Devolver una reserva ajena', $luis->api('PATCH', "reservas/$r1/devolver", ['estado_devolucion' => 'Devuelto']), 403);
    res('Empleado no confirma', $ana->api('PATCH', "reservas/$r1/confirmar", []), 403);
    $lista = $luis->api('GET', 'reservas');
    t('El listado solo trae sus reservas', count(array_filter($lista['json']['data'], fn ($f) => (int) $f['id_empleado'] !== $empLuis)) === 0);
    res('Reasignar la propia reserva a otro', $ana->api('PUT', "reservas/$r1", $res($v1, $f1, $f2, ['id_empleado' => $empLuis])), 403);
    res('Reserva inexistente', $admin->api('GET', 'reservas/99999'), 404);

    grupo('Estados y transiciones');
    $x = $ana->api('PUT', "reservas/$r1", $res($v1, $f1, $f2, ['motivo' => 'Ruta norte']));
    res('Edita su reserva pendiente', $x, 200);
    t('Editar no choca consigo misma', sql("SELECT motivo FROM reservas WHERE id_reserva=$r1")[0]['motivo'] === 'Ruta norte');
    res('Edición con solape rechazada', $admin->api('PUT', "reservas/$r2", $res($v1, $f1, $f2, ['id_empleado' => $empAna])), 409);
    $x = $admin->api('PATCH', "reservas/$r1/confirmar", []);
    res('Confirmación', $x, 200);
    muestra('Reserva confirmada', "PATCH reservas/$r1/confirmar", $x);
    res('Doble confirmación rechazada', $admin->api('PATCH', "reservas/$r1/confirmar", []), 409);
    res('Empleado no edita una reserva confirmada', $ana->api('PUT', "reservas/$r1", $res($v1, $f1, $f2)), 409);
    res('No inicia antes de su fecha', $ana->api('PATCH', "reservas/$r1/iniciar", []), 409);
    res('No se devuelve sin iniciar', $ana->api('PATCH', "reservas/$r1/devolver", ['estado_devolucion' => 'Devuelto']), 409);
    $x = $ana->api('PATCH', "reservas/$r1/cancelar", ['motivo' => 'Cliente reprogramó']);
    res('Cancelación', $x, 200);
    t('Conserva el motivo', str_contains(sql("SELECT observaciones o FROM reservas WHERE id_reserva=$r1")[0]['o'], 'Cliente reprogramó'));
    res('Cancelada no se confirma', $admin->api('PATCH', "reservas/$r1/confirmar", []), 409);
    res('Cancelada no se edita', $admin->api('PUT', "reservas/$r1", $res($v1, $f1, $f2, ['id_empleado' => $empAna])), 409);
    $x = $luis->api('POST', 'reservas', $res($v1, $f1, $f2));
    res('Una cancelada ya no bloquea el periodo', $x, 201);
    $luis->api('PATCH', "reservas/{$x['json']['data']['id_reserva']}/cancelar", []);

    grupo('Devoluciones');
    $ini = ahora('+0 seconds'); $fin = ahora('+4 hours');
    $x = $ana->api('POST', 'reservas', $res($v2, $ini, $fin));
    res('Reserva que inicia ahora', $x, 201);
    $rd = $x['json']['data']['id_reserva'];
    $admin->api('PATCH', "reservas/$rd/confirmar", []);
    res('Inicio de uso', $ana->api('PATCH', "reservas/$rd/iniciar", []), 200);
    res('En uso no se cancela', $ana->api('PATCH', "reservas/$rd/cancelar", []), 409);
    res('Vehículo en uso no sale de servicio', $admin->api('PATCH', "vehiculos/$v2/estado", ['estado' => 'Fuera de servicio']), 409);
    res('Devolución sin estado', $ana->api('PATCH', "reservas/$rd/devolver", []), 422);
    $x = $ana->api('PATCH', "reservas/$rd/devolver", ['estado_devolucion' => 'Con observaciones']);
    res('Con observaciones exige describirlas', $x, 422);
    t('Error en el campo observaciones', tiene_error($x, 'observaciones'));
    res('Devolución con fecha futura', $ana->api('PATCH', "reservas/$rd/devolver", ['estado_devolucion' => 'Devuelto', 'fecha_devolucion' => ahora('+1 hour')]), 422);
    res('Devolución anterior al inicio', $ana->api('PATCH', "reservas/$rd/devolver", ['estado_devolucion' => 'Devuelto', 'fecha_devolucion' => ahora('-1 day')]), 422);
    $x = $ana->api('PATCH', "reservas/$rd/devolver", ['estado_devolucion' => 'Con observaciones', 'observaciones' => 'Rayón en la puerta']);
    res('Devolución anticipada registrada', $x, 200);
    muestra('Devolución registrada', "PATCH reservas/$rd/devolver", $x);
    $fila = sql("SELECT * FROM reservas WHERE id_reserva=$rd")[0];
    t('Finalizada con fecha real y estado', $fila['estado'] === 'Finalizada' && $fila['fecha_devolucion'] !== null && $fila['estado_devolucion'] === 'Con observaciones');
    t('Conserva el fin reservado (anticipada no pierde datos)', $fila['fecha_fin'] === $fin && $fila['fecha_devolucion'] < $fila['fecha_fin']);
    t('Registra observaciones', str_contains($fila['observaciones'], 'Rayón en la puerta'));
    res('Finalizada no se devuelve otra vez', $ana->api('PATCH', "reservas/$rd/devolver", ['estado_devolucion' => 'Devuelto']), 409);
    res('Finalizada no se cancela', $admin->api('PATCH', "reservas/$rd/cancelar", []), 409);
    $x = $luis->api('POST', 'reservas', $res($v2, ahora('+1 hour'), ahora('+2 hours')));
    res('Tras devolver antes, el resto del periodo queda libre', $x, 201);
    $luis->api('PATCH', "reservas/{$x['json']['data']['id_reserva']}/cancelar", []);

    grupo('Reserva en uso con retraso');
    $x = $ana->api('POST', 'reservas', $res($v6, ahora(), ahora('+1 hour')));
    $rt = $x['json']['data']['id_reserva'];
    $admin->api('PATCH', "reservas/$rt/confirmar", []);
    $ana->api('PATCH', "reservas/$rt/iniciar", []);
    $sig = $admin->api('POST', 'reservas', $res($v6, ahora('+2 hours'), ahora('+3 hours'), ['id_empleado' => $empLuis, 'estado' => 'Confirmada']));
    $rs = $sig['json']['data']['id_reserva'];
    // Simula que la reserva venció hace una hora y el vehículo no ha regresado.
    sql("UPDATE reservas SET fecha_inicio = DATE_SUB(NOW(), INTERVAL 3 HOUR), fecha_fin = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id_reserva = $rt");
    res('Sigue bloqueando aunque ya venció', $luis->api('POST', 'reservas', $res($v6, ahora(), ahora('+30 minutes'))), 409);
    res('También bloquea periodos futuros hasta que regrese', $luis->api('POST', 'reservas', $res($v6, ahora('+5 days'), ahora('+6 days'))), 409);
    $q = $ana->api('GET', 'reservas/disponibilidad', null, ['fecha_inicio' => ahora('+5 days'), 'fecha_fin' => ahora('+6 days')]);
    t('Disponibilidad lo muestra como no disponible', array_column($q['json']['data']['vehiculos'], null, 'id_vehiculo')[$v6]['disponible'] === false);
    sql("UPDATE reservas SET fecha_inicio = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id_reserva = $rs");
    res('La siguiente reserva no inicia hasta la devolución', $luis->api('PATCH', "reservas/$rs/iniciar", []), 409);
    res('Devolución con retraso', $ana->api('PATCH', "reservas/$rt/devolver", ['estado_devolucion' => 'Devuelto']), 200);
    res('Ya devuelto, la siguiente reserva inicia', $luis->api('PATCH', "reservas/$rs/iniciar", []), 200);
    $luis->api('PATCH', "reservas/$rs/devolver", ['estado_devolucion' => 'Devuelto']);

    grupo('Dos reservas simultáneas');
    $trabajador = RAIZ_T . '/tests/worker_reserva.php';
    foreach ([$v4, $v3] as $ronda => $idV) {
        $iniC = ahora('+10 days'); $finC = ahora('+10 days +2 hours');
        $instante = microtime(true) + 1.5;
        $procesos = [];
        for ($w = 0; $w < 8; $w++) {
            $tuberias = [];
            $procesos[] = [proc_open([PHP_BINARY, $trabajador, $idV, $w % 2 ? $empLuis : $empAna, $idAna, $iniC, $finC, $instante], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, RAIZ_T, $entorno), $tuberias];
        }
        $salidas = [];
        foreach ($procesos as [$proc, $tub]) {
            $salidas[] = json_decode(stream_get_contents($tub[1]), true);
            proc_close($proc);
        }
        $exitos = count(array_filter($salidas, fn ($s) => $s['ok'] ?? false));
        $conflictos = count(array_filter($salidas, fn ($s) => ($s['http'] ?? 0) === 409));
        t('Ronda ' . ($ronda + 1) . ': 8 procesos a la vez, gana exactamente 1', $exitos === 1 && $conflictos === 7, $salidas);
        $enBd = (int) sql("SELECT COUNT(*) c FROM reservas WHERE id_vehiculo = $idV AND fecha_inicio = '$iniC' AND estado <> 'Cancelada'")[0]['c'];
        t('Ronda ' . ($ronda + 1) . ': una sola fila en MySQL', $enBd === 1, "filas: $enBd");
    }

    /* ---------- Mantenimientos ---------- */
    grupo('Mantenimientos');
    $vm = $crear('MAN-1');
    $m = fn ($idV, $ini, $fin, $extra = []) => $extra + ['id_vehiculo' => $idV, 'id_usuario' => $idAdmin, 'tipo_mantenimiento' => 'Preventivo', 'descripcion' => 'Cambio de aceite', 'fecha_inicio' => $ini, 'fecha_fin' => $fin, 'costo' => '1500.50'];
    $mi = ahora('+3 days'); $mf = ahora('+3 days +5 hours');
    $x = $admin->api('POST', 'mantenimientos', $m($vm, $mi, $mf));
    res('Registro válido', $x, 201);
    muestra('Mantenimiento registrado', 'POST mantenimientos', $x);
    $m1 = $x['json']['data']['id_mantenimiento'];
    res('Costo negativo', $admin->api('POST', 'mantenimientos', $m($vm, ahora('+20 days'), ahora('+21 days'), ['costo' => '-1'])), 422);
    res('Fechas inválidas', $admin->api('POST', 'mantenimientos', $m($vm, ahora('+21 days'), ahora('+20 days'))), 422);
    res('Tipo inválido', $admin->api('POST', 'mantenimientos', $m($vm, ahora('+20 days'), ahora('+21 days'), ['tipo_mantenimiento' => 'Otro'])), 422);
    res('Vehículo inexistente', $admin->api('POST', 'mantenimientos', $m(99999, ahora('+20 days'), ahora('+21 days'))), 404);
    res('Programado sin fecha final', $admin->api('POST', 'mantenimientos', $m($vm, ahora('+20 days'), '')), 422);
    res('Responsable inexistente', $admin->api('POST', 'mantenimientos', $m($vm, ahora('+20 days'), ahora('+21 days'), ['id_usuario' => 9999])), 422);
    res('Mantenimientos solapados', $admin->api('POST', 'mantenimientos', $m($vm, ahora('+3 days +1 hour'), ahora('+3 days +2 hours'))), 409);
    $x = $ana->api('POST', 'reservas', $res($vm, ahora('+3 days +1 hour'), ahora('+3 days +2 hours')));
    res('Reserva con mantenimiento coincidente', $x, 409);
    $q = $ana->api('GET', 'reservas/disponibilidad', null, ['fecha_inicio' => ahora('+3 days +1 hour'), 'fecha_fin' => ahora('+3 days +2 hours')]);
    t('Disponibilidad lo refleja', array_column($q['json']['data']['vehiculos'], null, 'id_vehiculo')[$vm]['motivo'] === 'Mantenimiento programado en el periodo');
    $xr = $ana->api('POST', 'reservas', $res($vm, ahora('+5 days'), ahora('+5 days +2 hours')));
    res('Mantenimiento incompatible con una reserva', $admin->api('POST', 'mantenimientos', $m($vm, ahora('+5 days +1 hour'), ahora('+5 days +3 hours'))), 409);
    $ana->api('PATCH', "reservas/{$xr['json']['data']['id_reserva']}/cancelar", []);
    $prox = $admin->api('GET', 'mantenimientos/proximos');
    res('Recordatorios', $prox, 200);
    t('Incluye el próximo con su alerta', ($prox['json']['data']['mantenimientos'][0]['alerta'] ?? '') === 'Próximo', $prox['json']);
    res('Edición', $admin->api('PUT', "mantenimientos/$m1", $m($vm, $mi, $mf, ['costo' => '1600'])), 200);
    res('Transición inválida (Programado a Finalizado)', $admin->api('PATCH', "mantenimientos/$m1/estado", ['estado' => 'Finalizado']), 409);
    res('Iniciar', $admin->api('PATCH', "mantenimientos/$m1/estado", ['estado' => 'En proceso']), 200);
    t('El vehículo pasa a Mantenimiento', sql("SELECT estado e FROM vehiculos WHERE id_vehiculo=$vm")[0]['e'] === 'Mantenimiento');
    t('Al adelantarse guarda el inicio real', sql("SELECT fecha_inicio f FROM mantenimientos WHERE id_mantenimiento=$m1")[0]['f'] <= ahora());
    res('Vehículo no vuelve a Disponible con mantenimiento abierto', $admin->api('PATCH', "vehiculos/$vm/estado", ['estado' => 'Disponible']), 409);
    res('Reserva con vehículo en mantenimiento', $ana->api('POST', 'reservas', $res($vm, ahora('+30 days'), ahora('+31 days'))), 409);
    res('Finalizar con fecha futura', $admin->api('PATCH', "mantenimientos/$m1/estado", ['estado' => 'Finalizado', 'fecha_fin' => ahora('+1 day')]), 422);
    sleep(1);
    res('Finalizar con costo final', $admin->api('PATCH', "mantenimientos/$m1/estado", ['estado' => 'Finalizado', 'costo' => '1750.25']), 200);
    $fila = sql("SELECT * FROM mantenimientos WHERE id_mantenimiento=$m1")[0];
    t('Guarda el costo y la fecha final real', $fila['costo'] === '1750.25' && $fila['fecha_fin'] <= ahora());
    t('El vehículo vuelve a Disponible', sql("SELECT estado e FROM vehiculos WHERE id_vehiculo=$vm")[0]['e'] === 'Disponible');
    res('Finalizado no se edita', $admin->api('PUT', "mantenimientos/$m1", $m($vm, $mi, $mf)), 409);
    res('Finalizado no cambia de estado', $admin->api('PATCH', "mantenimientos/$m1/estado", ['estado' => 'Cancelado']), 409);
    $x = $admin->api('POST', 'mantenimientos', $m($vm, ahora('+40 days'), ahora('+41 days')));
    res('Cancelar un programado', $admin->api('PATCH', "mantenimientos/{$x['json']['data']['id_mantenimiento']}/estado", ['estado' => 'Cancelado']), 200);
    res('Empleado sin acceso', $ana->api('POST', 'mantenimientos', $m($vm, ahora('+50 days'), ahora('+51 days'))), 403);
    $x = $admin->api('POST', 'mantenimientos', $m($vm, ahora('+6 days'), ahora('+6 days +3 hours'), ['tipo_mantenimiento' => 'Correctivo', 'descripcion' => 'Revisión de frenos']));
    res('Queda uno programado como muestra', $x, 201);

    grupo('Vehículo con historial');
    res('No se elimina físicamente con reservas', $admin->api('DELETE', "vehiculos/$v1"), 409);
    res('No se elimina físicamente con mantenimientos', $admin->api('DELETE', "vehiculos/$vm"), 409);
    t('Sigue existiendo', count(sql("SELECT id_vehiculo FROM vehiculos WHERE id_vehiculo=$v1")) === 1);
    $xf = $ana->api('POST', 'reservas', $res($v1, ahora('+60 days'), ahora('+61 days')));
    res('No sale de servicio con reservas vigentes', $admin->api('PATCH', "vehiculos/$v1/estado", ['estado' => 'Fuera de servicio']), 409);
    $ana->api('PATCH', "reservas/{$xf['json']['data']['id_reserva']}/cancelar", []);
    $hist = $admin->api('GET', "vehiculos/$v1/reservas");
    res('Historial de reservas', $hist, 200);
    t('Trae registros', count($hist['json']['data']) > 0);
    res('Historial de mantenimientos', $admin->api('GET', "vehiculos/$vm/mantenimientos"), 200);
    grupo('Empleados con reservas abiertas');
    $xo = $luis->api('POST', 'reservas', $res($v1, ahora('+70 days'), ahora('+71 days')));
    res('No se desactiva con reservas abiertas', $admin->api('PATCH', "empleados/$empLuis/estado", ['estado' => 'Inactivo']), 409);
    $luis->api('PATCH', "reservas/{$xo['json']['data']['id_reserva']}/cancelar", []);
    // Dos reservas de la prueba de concurrencia siguen abiertas: se cancelan para probar la desactivación.
    foreach (sql("SELECT id_reserva i FROM reservas WHERE id_empleado=$empLuis AND estado IN ('Pendiente','Confirmada','En uso')") as $f) {
        $admin->api('PATCH', "reservas/{$f['i']}/cancelar", []);
    }
    res('Desactivar sin reservas abiertas', $admin->api('PATCH', "empleados/$empLuis/estado", ['estado' => 'Inactivo']), 200);
    res('Empleado inactivo no reserva', $luis->api('POST', 'reservas', $res($v1, ahora('+80 days'), ahora('+81 days'))), 403);
    res('Administrador no asigna a un empleado inactivo', $admin->api('POST', 'reservas', $res($v1, ahora('+80 days'), ahora('+81 days'), ['id_empleado' => $empLuis])), 422);
    $admin->api('PATCH', "empleados/$empLuis/estado", ['estado' => 'Activo']);

    /* ---------- Reportes ---------- */
    grupo('Reportes PDF');
    $pdf = function (string $nombre, array $param, int $esperados = -1) use ($admin, $dirEvid) {
        $r = $admin->api('GET', 'reportes/pdf', null, $param);
        $esPdf = $r['status'] === 200 && str_starts_with($r['body'], '%PDF') && str_contains($r['headers'], 'application/pdf');
        t("$nombre: PDF real", $esPdf, "HTTP {$r['status']} " . substr($r['body'], 0, 150));
        if ($esPdf) {
            file_put_contents("$dirEvid/pdf/" . preg_replace('/\W+/u', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre)) . '.pdf', $r['body']);
            $desc = sql("SELECT descripcion d FROM bitacora WHERE accion='Generación de reporte' ORDER BY id_bitacora DESC LIMIT 1")[0]['d'];
            preg_match('/con (\d+) registro/', $desc, $m);
            if ($esperados >= 0) {
                t("$nombre: $esperados registro(s) según MySQL", (int) $m[1] === $esperados, $desc);
            }
        }
    };
    $cuenta = fn ($w = '1=1') => (int) sql("SELECT COUNT(*) c FROM reservas r WHERE $w")[0]['c'];
    $pdf('Reporte general', ['tipo' => 'general'], $cuenta());
    $pdf('General filtrado por estado', ['tipo' => 'general', 'estado' => 'Cancelada'], $cuenta("r.estado='Cancelada'"));
    $pdf('Por empleado', ['tipo' => 'empleado', 'id_empleado' => $empAna], $cuenta("r.id_empleado=$empAna"));
    $pdf('Por vehiculo', ['tipo' => 'vehiculo', 'id_vehiculo' => $v1], $cuenta("r.id_vehiculo=$v1"));
    $hoy = substr(ahora(), 0, 10); $hasta2 = substr(ahora('+2 days'), 0, 10);
    $pdf('Por periodo', ['tipo' => 'periodo', 'desde' => $hoy, 'hasta' => $hasta2], $cuenta("r.fecha_inicio <= '$hasta2 23:59:59' AND r.fecha_fin >= '$hoy 00:00:00'"));
    $pdf('Vehiculos disponibles y fuera de servicio', ['tipo' => 'flota'], (int) sql("SELECT COUNT(*) c FROM vehiculos WHERE estado IN ('Disponible','Fuera de servicio')")[0]['c']);
    $pdf('Mantenimientos', ['tipo' => 'mantenimientos'], (int) sql('SELECT COUNT(*) c FROM mantenimientos')[0]['c']);
    $pdf('Historial de uso', ['tipo' => 'uso'], $cuenta("r.estado IN ('En uso','Finalizada')"));
    $pdf('Filtro por fechas sin coincidencias', ['tipo' => 'general', 'desde' => '2001-01-01', 'hasta' => '2001-01-31'], 0);
    $x = $admin->api('GET', 'reportes/pdf', null, ['tipo' => 'periodo']);
    res('Periodo sin fechas', $x, 422);
    res('Empleado obligatorio en su reporte', $admin->api('GET', 'reportes/pdf', null, ['tipo' => 'empleado']), 422);
    res('Vehículo obligatorio en su reporte', $admin->api('GET', 'reportes/pdf', null, ['tipo' => 'vehiculo']), 422);
    res('Tipo inválido', $admin->api('GET', 'reportes/pdf', null, ['tipo' => 'nada']), 422);
    res('Rango invertido', $admin->api('GET', 'reportes/pdf', null, ['tipo' => 'general', 'desde' => '2026-02-01', 'hasta' => '2026-01-01']), 422);
    res('Vehículo inexistente', $admin->api('GET', 'reportes/pdf', null, ['tipo' => 'vehiculo', 'id_vehiculo' => 99999]), 404);
    res('Opciones de filtros', $admin->api('GET', 'reportes/opciones'), 200);

    /* ---------- Panel ---------- */
    grupo('Panel principal');
    $d = $admin->api('GET', 'dashboard');
    res('Panel del administrador', $d, 200);
    muestra('Panel del administrador', 'GET dashboard', ['status' => $d['status'], 'json' => ['success' => true, 'message' => $d['json']['message'], 'data' => ['indicadores' => $d['json']['data']['indicadores']]]]);
    $k = $d['json']['data']['indicadores'];
    $c = fn ($q) => (int) sql($q)[0]['c'];
    t('Indicadores iguales a MySQL', $k['total_vehiculos'] === $c('SELECT COUNT(*) c FROM vehiculos')
        && $k['vehiculos_operativos'] === $c("SELECT COUNT(*) c FROM vehiculos WHERE estado='Disponible'")
        && $k['reservas_pendientes'] === $c("SELECT COUNT(*) c FROM reservas WHERE estado='Pendiente'")
        && $k['reservas_activas'] === $c("SELECT COUNT(*) c FROM reservas WHERE estado IN ('Confirmada','En uso')")
        && $k['reservas_finalizadas'] === $c("SELECT COUNT(*) c FROM reservas WHERE estado='Finalizada'"), $k);
    $d = $ana->api('GET', 'dashboard', null, ['inicio' => ahora('+90 days'), 'fin' => ahora('+91 days')]);
    res('Panel del empleado', $d, 200);
    t('Muestra sus datos y vehículos disponibles', $d['json']['data']['indicadores']['mis_reservas'] === $c("SELECT COUNT(*) c FROM reservas WHERE id_empleado=$empAna") && is_int($d['json']['data']['vehiculos_disponibles']), $d['json']['data']['indicadores']);
    res('Panel con periodo invertido', $ana->api('GET', 'dashboard', null, ['inicio' => ahora('+2 days'), 'fin' => ahora('+1 day')]), 422);

    /* ---------- Bitácora ---------- */
    grupo('Bitácora');
    $b = $admin->api('GET', 'bitacora');
    res('Consulta', $b, 200);
    $acciones = array_unique(array_column(sql('SELECT accion FROM bitacora'), 'accion'));
    foreach (['Inicio de sesión', 'Registro de usuario', 'Edición de usuario', 'Registro de empleado', 'Edición de empleado', 'Registro de vehículo', 'Cambio de estado de vehículo', 'Creación de reserva', 'Cancelación de reserva', 'Confirmación de reserva', 'Devolución de vehículo', 'Registro de mantenimiento', 'Actualización de mantenimiento'] as $a) {
        t("Registra: $a", in_array($a, $acciones, true));
    }
    $todo = sql('SELECT datos_anteriores, datos_nuevos, descripcion FROM bitacora');
    $texto = json_encode($todo, JSON_UNESCAPED_UNICODE);
    t('Nunca guarda contraseñas ni hashes', !preg_match('/contrasena|\$2y|' . preg_quote($clave, '/') . '/', $texto));
    $invalidos = array_filter($todo, fn ($f) => ($f['datos_nuevos'] !== null && json_decode($f['datos_nuevos']) === null) || ($f['datos_anteriores'] !== null && json_decode($f['datos_anteriores']) === null));
    t('Las columnas LONGTEXT contienen JSON válido', !$invalidos);
    t('Guarda datos anteriores y nuevos en una edición', (bool) sql("SELECT 1 FROM bitacora WHERE accion='Edición de vehículo' AND datos_anteriores IS NOT NULL AND datos_nuevos IS NOT NULL LIMIT 1"));
    res('Filtro por módulo', $x = $admin->api('GET', 'bitacora', null, ['modulo' => 'Reportes']), 200);
    t('El filtro se aplica', count(array_filter($x['json']['data']['registros'], fn ($f) => $f['modulo'] !== 'Reportes')) === 0 && count($x['json']['data']['registros']) > 0);
    res('Filtro con fecha inválida', $admin->api('GET', 'bitacora', null, ['desde' => 'ayer']), 422);

    /* ---------- Protocolo y seguridad ---------- */
    grupo('Protocolo y seguridad');
    res('Ruta inexistente', $admin->api('GET', 'nada/que/ver'), 404);
    $x = $admin->api('DELETE', 'usuarios');
    res('Método no permitido', $x, 405);
    t('Indica los métodos válidos (cabecera Allow)', str_contains($x['headers'], 'Allow:'));
    $x = $admin->crudo('POST', '/api/index.php?ruta=vehiculos', '{no es json', ['Content-Type: application/json', 'X-CSRF-Token: ' . $admin->csrf]);
    t('JSON mal formado: 400', $x['status'] === 400 && json_decode($x['body'], true)['success'] === false, $x['body']);
    res('Escritura sin token CSRF', $admin->api('POST', 'vehiculos', $v('CSRF-1'), [], ''), 403);
    res('Escritura con token de otra sesión', $admin->api('POST', 'vehiculos', $v('CSRF-2'), [], $ana->csrf), 403);
    res('Inyección SQL en un filtro no rompe ni filtra', $admin->api('GET', 'bitacora', null, ['modulo' => "x' OR '1'='1"]), 200);
    $x = $admin->api('POST', 'vehiculos', $v('XSS-1', ['marca' => '<script>alert(1)</script>']));
    res('HTML se almacena como dato', $x, 201);
    $l = $admin->api('GET', 'vehiculos');
    t('La API devuelve el dato sin ejecutar nada', str_contains(json_encode($l['json'], JSON_UNESCAPED_SLASHES), '<script>alert(1)</script>'));
    $admin->api('DELETE', "vehiculos/{$x['json']['data']['id_vehiculo']}");
    res('Emoji rechazado (columnas utf8 de MySQL 5.5)', $admin->api('POST', 'vehiculos', $v('EMO-1', ['marca' => 'Volvo 🚚'])), 422);
    $x = $admin->api('POST', 'vehiculos', $v('LARGA-1', ['marca' => str_repeat('x', 200)]));
    res('Texto más largo que la columna', $x, 422);
    t('Respuestas sin detalles de SQL', !preg_match('/SQLSTATE|mysqli|syntax/i', $x['body']));
    $hdr = $admin->pagina()['headers'];
    t('Cabeceras de seguridad', str_contains($hdr, 'Content-Security-Policy') && str_contains($hdr, 'X-Frame-Options: DENY') && str_contains($hdr, 'nosniff'));
    t('Cookie de sesión HttpOnly', (bool) preg_match('/#HttpOnly_.*GRV_SESION/', file_get_contents((function ($c) { $r = new ReflectionProperty($c, 'cookies'); $r->setAccessible(true); return $r->getValue($c); })($admin))));

    /* ---------- Cierre ---------- */
    grupo('Cierre de sesión');
    $x = $ana->api('POST', 'auth/logout');
    res('Cierre de sesión', $x, 200);
    res('Después del cierre no hay acceso', $ana->api('GET', 'reservas'), 401);
    $p = $ana->pagina('?page=reservas');
    t('La página vuelve al login', str_contains($p['body'], 'formLogin'));
    t('Registró el cierre', (bool) sql("SELECT 1 FROM bitacora WHERE accion='Cierre de sesión' LIMIT 1"));

    $log = @file_get_contents($registroServidor) ?: '';
    $criticos = preg_grep('/PHP (Fatal|Warning|Notice|Deprecated)|\[GRV\]/', explode("\n", $log));
    t('Sin errores ni advertencias de PHP en el servidor', !$criticos, array_slice(array_values($criticos ?: []), 0, 5));
} catch (Throwable $ex) {
    t('La ejecución de pruebas terminó sin excepciones', false, get_class($ex) . ': ' . $ex->getMessage() . ' en línea ' . $ex->getLine());
}

/* ---------- Evidencias ---------- */

$total = count($resultados);
$md = "# Resultados de las pruebas automatizadas\n\nEjecutadas con `tests/pruebas.php` el " . ahora() . " (hora de MySQL) contra un clon temporal del esquema real.\n\n";
$md .= '**' . ($total - $fallos) . " de $total comprobaciones correctas.**\n";
$actual = '';
foreach ($resultados as [$sec, $nombre, $ok, $detalle]) {
    if ($sec !== $actual) {
        $md .= "\n## $sec\n\n";
        $actual = $sec;
    }
    $md .= ($ok ? '- [x] ' : '- [ ] **FALLO:** ') . $nombre . ($ok ? '' : " — `$detalle`") . "\n";
}
file_put_contents("$dirEvid/resultados_pruebas.md", $md);

$md = "# Evidencia de respuestas JSON\n\nRespuestas reales de la API capturadas durante las pruebas, con datos de prueba.\n";
foreach ($muestras as [$titulo, $peticion, $estado, $json]) {
    $md .= "\n## $titulo\n\n`$peticion` → HTTP $estado\n\n```json\n" . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n```\n";
}
file_put_contents("$dirEvid/respuestas_json.md", $md);

proc_terminate($servidor);
proc_close($servidor);
if (!$conservar) {
    $db->query('DROP DATABASE IF EXISTS `' . BD_TEST . '`');
} else {
    echo "\nClon conservado. Administrador de prueba: admin / $clave\n";
}

echo "\nTOTAL: " . ($total - $fallos) . " de $total correctas, $fallos con fallo.\n";
exit($fallos ? 1 : 0);
