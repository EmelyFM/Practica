# Evidencia de respuestas JSON

Respuestas reales de la API capturadas durante las pruebas, con datos de prueba.

## Error de inicio de sesión

`POST auth/login` → HTTP 401

```json
{
    "success": false,
    "message": "Usuario o contraseña incorrectos."
}
```

## Inicio de sesión

`POST auth/login` → HTTP 200

```json
{
    "success": true,
    "message": "Sesión iniciada correctamente.",
    "data": {
        "rol": "Administrador",
        "redirect": "/index.php?page=inicio"
    }
}
```

## Acceso sin sesión

`POST vehiculos` → HTTP 401

```json
{
    "success": false,
    "message": "Tu sesión no está activa. Inicia sesión para continuar."
}
```

## Usuario registrado

`POST usuarios` → HTTP 201

```json
{
    "success": true,
    "message": "Usuario registrado correctamente.",
    "data": {
        "id_usuario": 2
    }
}
```

## Error de validación por campo

`POST usuarios` → HTTP 422

```json
{
    "success": false,
    "message": "Revisa los campos marcados.",
    "errors": {
        "nombre_usuario": "El nombre de usuario es obligatorio.",
        "correo": "El correo electrónico es obligatorio.",
        "id_rol": "El rol es obligatorio.",
        "contrasena": "La contraseña es obligatoria."
    }
}
```

## Empleado registrado

`POST empleados` → HTTP 201

```json
{
    "success": true,
    "message": "Empleado registrado correctamente.",
    "data": {
        "id_empleado": 1
    }
}
```

## Permiso denegado

`POST usuarios (rol Empleado)` → HTTP 403

```json
{
    "success": false,
    "message": "No tienes permiso para realizar esta acción."
}
```

## Vehículo registrado

`POST vehiculos` → HTTP 201

```json
{
    "success": true,
    "message": "Vehículo registrado correctamente.",
    "data": {
        "id_vehiculo": 1
    }
}
```

## Reserva registrada

`POST reservas` → HTTP 201

```json
{
    "success": true,
    "message": "Reserva registrada correctamente.",
    "data": {
        "id_reserva": 1,
        "codigo_reserva": "RES-20261105-915E5E"
    }
}
```

## Conflicto de disponibilidad

`POST reservas` → HTTP 409

```json
{
    "success": false,
    "message": "El vehículo ABC-101 ya está reservado en ese periodo."
}
```

## Disponibilidad

`GET reservas/disponibilidad` → HTTP 200

```json
{
    "success": true,
    "message": "Disponibilidad consultada.",
    "data": {
        "inicio": "2026-11-07 23:04:32",
        "fin": "2026-11-08 03:04:32",
        "disponibles": 3,
        "vehiculos": [
            {
                "id_vehiculo": 1,
                "placa": "ABC-101",
                "marca": "Volvo",
                "modelo": "FH",
                "anio": 2022,
                "tipo_vehiculo": "Tractocamión",
                "color": "Blanco",
                "capacidad_carga": "18000.50",
                "kilometraje": 13000,
                "estado": "Disponible",
                "disponible": false,
                "motivo": "Reservado en el periodo"
            },
            {
                "id_vehiculo": 2,
                "placa": "ABC-102",
                "marca": "Volvo",
                "modelo": "FH",
                "anio": 2022,
                "tipo_vehiculo": "Tractocamión",
                "color": "Blanco",
                "capacidad_carga": "18000.50",
                "kilometraje": 12000,
                "estado": "Disponible",
                "disponible": true,
                "motivo": null
            }
        ]
    }
}
```

## Intento sobre reserva ajena

`PUT reservas/1` → HTTP 403

```json
{
    "success": false,
    "message": "No tienes permiso sobre esta reserva."
}
```

## Reserva confirmada

`PATCH reservas/1/confirmar` → HTTP 200

```json
{
    "success": true,
    "message": "Reserva confirmada."
}
```

## Devolución registrada

`PATCH reservas/6/devolver` → HTTP 200

```json
{
    "success": true,
    "message": "Devolución registrada correctamente."
}
```

## Mantenimiento registrado

`POST mantenimientos` → HTTP 201

```json
{
    "success": true,
    "message": "Mantenimiento registrado correctamente.",
    "data": {
        "id_mantenimiento": 1
    }
}
```

## Panel del administrador

`GET dashboard` → HTTP 200

```json
{
    "success": true,
    "message": "Panel obtenido.",
    "data": {
        "indicadores": {
            "total_vehiculos": 7,
            "vehiculos_operativos": 6,
            "vehiculos_mantenimiento": 0,
            "reservas_activas": 0,
            "reservas_pendientes": 3,
            "reservas_finalizadas": 3
        }
    }
}
```
