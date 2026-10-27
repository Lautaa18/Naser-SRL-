# Correcciones del 26/10/2026

Se corrigieron sesiones, respaldos, administrador inicial, permisos de carpetas, eliminación de archivos compartidos, migraciones, conversaciones y avisos diarios.

## Aplicación

- Las sesiones abiertas antes de esta actualización pedirán iniciar sesión nuevamente.
- La siguiente conexión aplica la actualización de esquema automáticamente. Los fallos impiden registrar una migración como terminada y permiten reintentar al resolver su causa.
- No se cambiaron contraseñas existentes ni se modificaron datos de la base real durante la aplicación de estos archivos.
- En una instalación nueva en producción, configurar INITIAL_ADMIN_EMAIL e INITIAL_ADMIN_PASSWORD (mínimo 12 caracteres) en .env. El primer ingreso exige reemplazar esa contraseña. Retirar estas variables tras crear el administrador.
- APP_ENV utiliza production por defecto. Para pruebas deliberadas usar APP_ENV=dev; ese modo crea cuentas de demostración.
- Si se usa Docker Compose, recrear los servicios web y backup para que tomen sus variables y scripts actualizados: docker compose up -d --force-recreate web backup. La base de datos conserva su volumen.
- Si ya existen cuentas de demostración con contraseñas conocidas, administrarlas desde Usuarios antes de exponer el sitio a Internet. El cambio no desactiva cuentas existentes automáticamente.

## Validación

Se realizaron pruebas en una base aislada con MariaDB 10.4 y PHP 8.5: revocación de sesiones, permisos, archivos, páginas HTTP, mensajería, instalaciones nuevas de producción, reintento de migraciones y avisos. Se probó un respaldo real y se restauró en otra base aislada. También se comprobó la sintaxis de PHP y del script de respaldos.

El despliegue declara PHP 8.3 y MariaDB 11; no se ejecutó su contenedor Docker ni se intervino un servidor publicado.
