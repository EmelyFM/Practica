# Problema 32: Sistema de Gestión de Reservas de Vehículos

Documento de evidencias de la práctica. Los datos que aparecen en capturas, PDF y respuestas JSON son **datos de prueba** generados por las pruebas automatizadas sobre un clon temporal del esquema; la base `gestion_reservas_vehiculos` no los contiene.

| Evidencia solicitada | Dónde está |
|---|---|
| Script SQL de la base de datos | [base_datos/esquema.sql](base_datos/esquema.sql) (solo estructura, sin `DROP`) |
| Diagrama entidad-relación | [base_datos/diagrama_er.png](base_datos/diagrama_er.png) y [.svg](base_datos/diagrama_er.svg) |
| Estructura de carpetas | [sección 1](#1-estructura-de-carpetas) |
| Capturas de la interfaz | [evidencias/capturas/](evidencias/capturas/) y guía en la [sección 5](#5-capturas) |
| Explicación del funcionamiento MVC | [sección 2](#2-funcionamiento-mvc) |
| Respuestas JSON | [evidencias/respuestas_json.md](evidencias/respuestas_json.md) |
| CRUD y validaciones | [evidencias/resultados_pruebas.md](evidencias/resultados_pruebas.md) |
| Reportes PDF | [evidencias/pdf/](evidencias/pdf/) |

## 1. Estructura de carpetas

```text
Practica/
├── index.php            Controlador frontal de páginas (sesión, rol, vista)
├── api/index.php        Enrutador JSON de todas las peticiones Fetch
├── config/              conexion.php (original) y app.php (arranque, sesión, seguridad)
├── controller/          BaseController, Validador y un controlador por módulo
├── models/              BaseModel y un modelo por módulo
├── view/
│   ├── layouts/         head, header, sidebar, footer, error
│   ├── auth/            login, setup (primer administrador)
│   ├── dashboard/  usuarios/  empleados/  vehiculos/  mantenimientos/  bitacora/
│   ├── reservas/        index, disponibilidad, _modales
│   └── reportes/        index (filtros), pdf (plantilla Dompdf)
├── js/                  funcion.js (común) y un archivo por módulo; lib/ con librerías
├── css/                 estilo.css; lib/ con Bootstrap, iconos y DataTables
├── reportes/            Carpeta temporal de Dompdf, sin acceso público
├── vendor/dompdf/       Dompdf 3.1.6
├── tests/               Pruebas automatizadas
└── docs/                Esta documentación y las evidencias
```

Las carpetas `config`, `controller`, `models`, `view`, `vendor`, `reportes`, `tests` y `docs` responden 403 si se piden por URL (`.htaccess`). Además, los archivos internos terminan con `exit` si se ejecutan sin pasar por `index.php` o `api/index.php`.

## 2. Funcionamiento MVC

Todo el sistema sigue el recorrido **Vista → Fetch API → Controlador → Modelo → MySQL → Modelo → Controlador → JSON → Vista**.

- **Modelo** (`models/`): solo acceso a datos. Consultas preparadas con `mysqli`, JOIN, transacciones y reglas que dependen de la base de datos. No genera HTML ni lee la sesión.
- **Vista** (`view/`, `js/`, `css/`): formularios, tablas y modales. No contiene SQL. Los datos llegan con Fetch y se pintan sin recargar.
- **Controlador** (`controller/`): recibe la petición, verifica sesión, CSRF y rol, valida los datos, llama al modelo y responde JSON con el código HTTP adecuado.

### Ejemplo: registrar una reserva

1. El empleado abre el formulario (`view/reservas/_modales.php`) y elige periodo y vehículo.
2. `js/reservas.js` valida los campos y `api()` de `js/funcion.js` envía `POST api/index.php?ruta=reservas` con el token CSRF.
3. `api/index.php` localiza la ruta y crea `ReservaController`, cuyo constructor exige sesión y CSRF.
4. `ReservaController::crear()` valida de nuevo y toma al empleado y al usuario de la sesión, no de lo que envió el navegador.
5. `ReservaModel::crear()` abre una transacción, bloquea la fila del vehículo con `SELECT ... FOR UPDATE`, comprueba estado, solapes y mantenimientos, inserta la reserva y la bitácora, y hace `COMMIT` (o `ROLLBACK` si algo falla).
6. El controlador responde `{"success": true, "message": "...", "data": {"id_reserva": 1, "codigo_reserva": "RES-..."}}` con HTTP 201.
7. JavaScript muestra SweetAlert2 y actualiza la tabla sin recargar.

### Códigos HTTP

| Código | Significado en la API |
|---|---|
| 200 / 201 | Operación correcta / registro creado |
| 400 | JSON mal formado |
| 401 | Sin sesión, o credenciales incorrectas |
| 403 | Sin permiso, o token CSRF inválido |
| 404 / 405 | Registro o ruta inexistente / método no permitido |
| 409 | Duplicado, conflicto de disponibilidad o transición no válida |
| 422 | Error de validación, con `errors` por campo |
| 500 / 503 | Error interno / sin conexión con MySQL (mensaje genérico; el detalle va al log) |

## 3. Reglas de negocio

**Disponibilidad.** Un vehículo está disponible si su estado es `Disponible`, no tiene reservas `Pendiente`, `Confirmada` o `En uso` que se solapen y no tiene mantenimientos `Programado` o `En proceso` que coincidan. El solape es `nueva_inicio < fin_existente AND nueva_fin > inicio_existente`, de modo que un periodo que empieza justo cuando otro termina sí se permite.

**Reserva vencida sin devolver.** Una reserva `En uso` cuyo fin ya pasó bloquea el vehículo para cualquier periodo hasta que se registre la devolución, porque no se sabe cuándo regresará.

**Transiciones de reserva.**

| Desde | Hacia | Quién |
|---|---|---|
| Pendiente | Confirmada | Administrador |
| Pendiente, Confirmada | Cancelada | Administrador o el empleado dueño |
| Confirmada | En uso | Administrador o el dueño, solo dentro del periodo reservado |
| En uso | Finalizada (devolución) | Administrador o el dueño |

**Devolución anticipada.** `fecha_fin` conserva el periodo reservado y `fecha_devolucion` guarda el momento real. Al finalizar, el resto del periodo queda libre y el reporte de uso calcula el tiempo con la fecha real.

**Transiciones de mantenimiento.** Programado → En proceso o Cancelado; En proceso → Finalizado o Cancelado. Mientras está en proceso, el vehículo pasa a estado `Mantenimiento`; al cerrarlo vuelve a `Disponible`.

**Eliminación.** Las claves foráneas borran en cascada, así que los usuarios y las reservas nunca se eliminan desde la interfaz, y un vehículo solo se elimina físicamente si no tiene historial.

## 4. Primer uso

La base de datos está vacía, incluida la tabla `roles`, por lo que no hay credenciales predefinidas.

1. Con Apache y MySQL en ejecución, abre desde el propio equipo `http://localhost/PROG_WEB_AVANZADA_M2_7mo_SEMESTRE/UNIDAD_3/Practica/`.
2. Aparece **Configuración inicial**. Crea el administrador con tu propia contraseña; el sistema crea también los roles `Administrador` y `Empleado`. Esta pantalla solo funciona desde el equipo servidor y se cierra sola en cuanto existe un usuario.
3. Inicia sesión y registra, en este orden: usuarios con rol Empleado (**Usuarios**), su ficha (**Empleados**) y la flota (**Vehículos**).

## 5. Capturas

[evidencias/capturas/](evidencias/capturas/) contiene las capturas tomadas automáticamente con datos de prueba. Si tu profesor pide capturas con tus propios datos, sigue estos pasos después de la sección 4:

| Evidencia | Pasos |
|---|---|
| Login y error | Abre el sistema y escribe una contraseña incorrecta |
| Panel por rol | Entra como administrador y luego como empleado |
| CRUD | En Usuarios, Empleados o Vehículos: crea, edita y desactiva un registro |
| Validaciones | Pulsa **Guardar** con el formulario vacío, o escribe un kilometraje negativo |
| Disponibilidad | Como empleado, consulta un periodo y pulsa **Reservar** |
| Solapamiento | Reserva un vehículo y repite el mismo periodo con otro empleado desde su formulario, o consulta Disponibilidad y observa que ya no se ofrece |
| Flujo de reserva | Como administrador: confirmar → iniciar (dentro del periodo) → devolver |
| Mantenimiento | Programa uno a menos de 7 días para ver el recordatorio |
| Bitácora | Abre Bitácora y pulsa el icono del ojo en un registro |
| JSON | Pulsa F12 → pestaña **Red** (Network) → repite una operación → abre la petición `index.php?ruta=...` → pestaña **Respuesta** |
| PDF | En Reportes, elige el tipo y los filtros y pulsa **Generar PDF** |

## 6. Volver a ejecutar las pruebas

```powershell
C:\xampp\php\php.exe tests\pruebas.php
```

Crea un clon temporal llamado `gestion_reservas_vehiculos_test`, lo prueba en un servidor propio en el puerto 8099, lo elimina al terminar y regenera `resultados_pruebas.md`, `respuestas_json.md` y los PDF. **No modifica la base de datos real.** Con `--conservar` deja el clon para revisarlo.
