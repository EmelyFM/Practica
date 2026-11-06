# Resultados de las pruebas automatizadas

Ejecutadas con `tests/pruebas.php` el 2026-11-05 23:04:45 (hora de MySQL) contra un clon temporal del esquema real.

**286 de 286 comprobaciones correctas.**

## Configuración inicial

- [x] Sin usuarios se muestra la configuración inicial
- [x] Creación sin token CSRF rechazada
- [x] Contraseña débil rechazada
- [x] El error llega por campo
- [x] Primer administrador creado
- [x] Se crearon los dos roles
- [x] Una segunda creación queda bloqueada
- [x] La contraseña se guarda con hash

## Autenticación

- [x] Ya configurado se muestra el login
- [x] Contraseña incorrecta
- [x] Usuario inexistente
- [x] El mensaje no revela si el usuario existe
- [x] Campos vacíos
- [x] Inyección SQL en el login rechazada
- [x] Login sin CSRF rechazado
- [x] Inicio de sesión correcto
- [x] Regenera el identificador de sesión
- [x] Identifica el rol
- [x] Con sesión se abre el panel
- [x] Páginas protegidas sin caché (botón Atrás)
- [x] Acceso sin sesión: 401 en las 17 rutas de consulta
- [x] Escritura sin sesión
- [x] Página protegida sin sesión muestra el login

## Usuarios

- [x] Registro válido
- [x] Campos vacíos
- [x] Marca todos los obligatorios
- [x] Correo duplicado
- [x] Duplicado señalado en el campo correo
- [x] Usuario duplicado (sin distinguir mayúsculas)
- [x] Correo inválido
- [x] Contraseña insegura
- [x] Rol inexistente
- [x] Consulta de usuarios
- [x] Las respuestas no contienen contraseñas ni hashes
- [x] Actualización válida
- [x] Se guardó el cambio
- [x] Usuario inexistente
- [x] No se puede desactivar al último administrador
- [x] No se puede quitar el rol al último administrador
- [x] Con otro administrador sí se puede desactivar
- [x] Actualización de contraseña
- [x] La contraseña anterior deja de servir
- [x] La nueva contraseña funciona
- [x] Usuario inactivo no puede entrar
- [x] Desactivar a un usuario
- [x] Su sesión abierta se invalida de inmediato

## Empleados

- [x] Registro válido
- [x] Nombre con números
- [x] Error debajo del campo nombre
- [x] Apellido con números
- [x] Teléfono inválido
- [x] Usuario relacionado obligatorio
- [x] Usuario relacionado inexistente
- [x] Dos empleados no pueden compartir usuario
- [x] Campos vacíos
- [x] Actualización válida
- [x] Consultar detalles
- [x] Conserva acentos y datos
- [x] Empleado inexistente
- [x] Usuarios libres excluye a los ya asignados
- [x] Empleado inicia sesión

## Permisos por rol

- [x] Empleado no accede a usuarios
- [x] Empleado no accede a empleados
- [x] Empleado no accede a mantenimientos
- [x] Empleado no accede a bitacora
- [x] Empleado no accede a reportes/opciones
- [x] Empleado no crea usuarios
- [x] Empleado no genera PDF
- [x] Página usuarios denegada al empleado (403)
- [x] Página empleados denegada al empleado (403)
- [x] Página mantenimientos denegada al empleado (403)
- [x] Página reportes denegada al empleado (403)
- [x] Página bitacora denegada al empleado (403)
- [x] Página inexistente (404)

## Vehículos

- [x] Registro válido
- [x] La placa se normaliza en mayúsculas
- [x] Placa duplicada
- [x] Duplicado señalado en el campo placa
- [x] Kilometraje negativo
- [x] Mensaje específico de negativo
- [x] Capacidad negativa
- [x] Año inválido
- [x] Campos obligatorios
- [x] Edición correcta
- [x] Se guardó la edición
- [x] Placa de otro vehículo al editar
- [x] Empleado no registra vehículos
- [x] Empleado no cambia estados
- [x] Empleado no borra vehículos
- [x] Empleado no ve historiales
- [x] Empleado consulta el catálogo
- [x] El catálogo oculta observaciones internas
- [x] Estado inválido
- [x] Cambio de estado
- [x] Elimina un vehículo sin historial
- [x] Vehículo inexistente

## Reservas

- [x] Reserva válida
- [x] Código automático con formato
- [x] Usuario tomado de la sesión y empleado propio
- [x] Queda Pendiente
- [x] Fechas invertidas
- [x] Error en fecha_fin
- [x] Fechas iguales
- [x] Fecha inválida
- [x] Inicio en el pasado
- [x] Motivo obligatorio
- [x] Vehículo inexistente
- [x] Horario solapado (dentro)
- [x] Horario solapado (cubre el periodo)
- [x] Horario solapado (por el inicio)
- [x] Horario solapado (por el final)
- [x] Periodo contiguo: permitido (fin = inicio)
- [x] Vehículo fuera de servicio
- [x] Empleado no reserva a nombre de otro
- [x] Empleado envía estado Confirmada
- [x] El servidor ignora ese estado
- [x] Usuario sin ficha de empleado no reserva
- [x] Administrador sin empleado indicado
- [x] Administrador asigna a otro empleado y confirma
- [x] Registrada por el administrador a nombre del empleado
- [x] Empleado inexistente

## Disponibilidad

- [x] Consulta de disponibilidad
- [x] Excluye el vehículo reservado
- [x] Excluye el vehículo fuera de servicio
- [x] Incluye un vehículo libre
- [x] Cuenta correcta
- [x] No expone datos de otras reservas
- [x] Periodo invertido
- [x] Periodo vacío

## Reservas ajenas

- [x] Consultar una reserva ajena
- [x] Modificar una reserva ajena
- [x] Cancelar una reserva ajena
- [x] Iniciar una reserva ajena
- [x] Devolver una reserva ajena
- [x] Empleado no confirma
- [x] El listado solo trae sus reservas
- [x] Reasignar la propia reserva a otro
- [x] Reserva inexistente

## Estados y transiciones

- [x] Edita su reserva pendiente
- [x] Editar no choca consigo misma
- [x] Edición con solape rechazada
- [x] Confirmación
- [x] Doble confirmación rechazada
- [x] Empleado no edita una reserva confirmada
- [x] No inicia antes de su fecha
- [x] No se devuelve sin iniciar
- [x] Cancelación
- [x] Conserva el motivo
- [x] Cancelada no se confirma
- [x] Cancelada no se edita
- [x] Una cancelada ya no bloquea el periodo

## Devoluciones

- [x] Reserva que inicia ahora
- [x] Inicio de uso
- [x] En uso no se cancela
- [x] Vehículo en uso no sale de servicio
- [x] Devolución sin estado
- [x] Con observaciones exige describirlas
- [x] Error en el campo observaciones
- [x] Devolución con fecha futura
- [x] Devolución anterior al inicio
- [x] Devolución anticipada registrada
- [x] Finalizada con fecha real y estado
- [x] Conserva el fin reservado (anticipada no pierde datos)
- [x] Registra observaciones
- [x] Finalizada no se devuelve otra vez
- [x] Finalizada no se cancela
- [x] Tras devolver antes, el resto del periodo queda libre

## Reserva en uso con retraso

- [x] Sigue bloqueando aunque ya venció
- [x] También bloquea periodos futuros hasta que regrese
- [x] Disponibilidad lo muestra como no disponible
- [x] La siguiente reserva no inicia hasta la devolución
- [x] Devolución con retraso
- [x] Ya devuelto, la siguiente reserva inicia

## Dos reservas simultáneas

- [x] Ronda 1: 8 procesos a la vez, gana exactamente 1
- [x] Ronda 1: una sola fila en MySQL
- [x] Ronda 2: 8 procesos a la vez, gana exactamente 1
- [x] Ronda 2: una sola fila en MySQL

## Mantenimientos

- [x] Registro válido
- [x] Costo negativo
- [x] Fechas inválidas
- [x] Tipo inválido
- [x] Vehículo inexistente
- [x] Programado sin fecha final
- [x] Responsable inexistente
- [x] Mantenimientos solapados
- [x] Reserva con mantenimiento coincidente
- [x] Disponibilidad lo refleja
- [x] Mantenimiento incompatible con una reserva
- [x] Recordatorios
- [x] Incluye el próximo con su alerta
- [x] Edición
- [x] Transición inválida (Programado a Finalizado)
- [x] Iniciar
- [x] El vehículo pasa a Mantenimiento
- [x] Al adelantarse guarda el inicio real
- [x] Vehículo no vuelve a Disponible con mantenimiento abierto
- [x] Reserva con vehículo en mantenimiento
- [x] Finalizar con fecha futura
- [x] Finalizar con costo final
- [x] Guarda el costo y la fecha final real
- [x] El vehículo vuelve a Disponible
- [x] Finalizado no se edita
- [x] Finalizado no cambia de estado
- [x] Cancelar un programado
- [x] Empleado sin acceso
- [x] Queda uno programado como muestra

## Vehículo con historial

- [x] No se elimina físicamente con reservas
- [x] No se elimina físicamente con mantenimientos
- [x] Sigue existiendo
- [x] No sale de servicio con reservas vigentes
- [x] Historial de reservas
- [x] Trae registros
- [x] Historial de mantenimientos

## Empleados con reservas abiertas

- [x] No se desactiva con reservas abiertas
- [x] Desactivar sin reservas abiertas
- [x] Empleado inactivo no reserva
- [x] Administrador no asigna a un empleado inactivo

## Reportes PDF

- [x] Reporte general: PDF real
- [x] Reporte general: 14 registro(s) según MySQL
- [x] General filtrado por estado: PDF real
- [x] General filtrado por estado: 8 registro(s) según MySQL
- [x] Por empleado: PDF real
- [x] Por empleado: 8 registro(s) según MySQL
- [x] Por vehiculo: PDF real
- [x] Por vehiculo: 5 registro(s) según MySQL
- [x] Por periodo: PDF real
- [x] Por periodo: 7 registro(s) según MySQL
- [x] Vehiculos disponibles y fuera de servicio: PDF real
- [x] Vehiculos disponibles y fuera de servicio: 7 registro(s) según MySQL
- [x] Mantenimientos: PDF real
- [x] Mantenimientos: 3 registro(s) según MySQL
- [x] Historial de uso: PDF real
- [x] Historial de uso: 3 registro(s) según MySQL
- [x] Filtro por fechas sin coincidencias: PDF real
- [x] Filtro por fechas sin coincidencias: 0 registro(s) según MySQL
- [x] Periodo sin fechas
- [x] Empleado obligatorio en su reporte
- [x] Vehículo obligatorio en su reporte
- [x] Tipo inválido
- [x] Rango invertido
- [x] Vehículo inexistente
- [x] Opciones de filtros

## Panel principal

- [x] Panel del administrador
- [x] Indicadores iguales a MySQL
- [x] Panel del empleado
- [x] Muestra sus datos y vehículos disponibles
- [x] Panel con periodo invertido

## Bitácora

- [x] Consulta
- [x] Registra: Inicio de sesión
- [x] Registra: Registro de usuario
- [x] Registra: Edición de usuario
- [x] Registra: Registro de empleado
- [x] Registra: Edición de empleado
- [x] Registra: Registro de vehículo
- [x] Registra: Cambio de estado de vehículo
- [x] Registra: Creación de reserva
- [x] Registra: Cancelación de reserva
- [x] Registra: Confirmación de reserva
- [x] Registra: Devolución de vehículo
- [x] Registra: Registro de mantenimiento
- [x] Registra: Actualización de mantenimiento
- [x] Nunca guarda contraseñas ni hashes
- [x] Las columnas LONGTEXT contienen JSON válido
- [x] Guarda datos anteriores y nuevos en una edición
- [x] Filtro por módulo
- [x] El filtro se aplica
- [x] Filtro con fecha inválida

## Protocolo y seguridad

- [x] Ruta inexistente
- [x] Método no permitido
- [x] Indica los métodos válidos (cabecera Allow)
- [x] JSON mal formado: 400
- [x] Escritura sin token CSRF
- [x] Escritura con token de otra sesión
- [x] Inyección SQL en un filtro no rompe ni filtra
- [x] HTML se almacena como dato
- [x] La API devuelve el dato sin ejecutar nada
- [x] Emoji rechazado (columnas utf8 de MySQL 5.5)
- [x] Texto más largo que la columna
- [x] Respuestas sin detalles de SQL
- [x] Cabeceras de seguridad
- [x] Cookie de sesión HttpOnly

## Cierre de sesión

- [x] Cierre de sesión
- [x] Después del cierre no hay acceso
- [x] La página vuelve al login
- [x] Registró el cierre
- [x] Sin errores ni advertencias de PHP en el servidor
