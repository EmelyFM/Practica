# Sistema de Gestión de Reservas de Vehículos

Aplicación web en PHP y MySQL para administrar usuarios, empleados, vehículos, reservas, mantenimientos, reportes y bitácora.

## Requisitos

- PHP 8.0 o posterior con las extensiones `mysqli` y `mbstring`
- MySQL
- Apache (por ejemplo, XAMPP)

## Instalación local

1. Copia el proyecto en la carpeta pública de Apache.
2. Importa [`docs/base_datos/esquema.sql`](docs/base_datos/esquema.sql) en MySQL.
3. Configura la conexión con variables de entorno:
   - `DB_HOST` (opcional; por defecto `127.0.0.1`)
   - `DB_USER` (opcional; por defecto `root`)
   - `DB_PASS` (opcional; por defecto vacío para XAMPP local)
   - `DB_NAME` (opcional; por defecto `gestion_reservas_vehiculos`)
4. Abre el proyecto desde el navegador. Si la base de datos todavía no tiene usuarios, crea el primer administrador desde el equipo local.

No guardes contraseñas reales en el código ni en archivos versionados. En producción, configura un usuario de MySQL dedicado y una contraseña mediante variables de entorno.
