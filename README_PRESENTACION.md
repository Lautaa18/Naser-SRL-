# NASER SGI — Prueba funcional

Esta versión está preparada para ejecutar el sistema en Windows + WSL2 + Docker Desktop, manteniendo la estructura del proyecto NASER y dejando una base sólida para la futura conexión al servidor.

## Qué incluye

- Dashboard corporativo con banner NASER y las cuatro tarjetas: Trabajador, Camión Slickline, Unidad Liviana e Hidrogrúa.
- Sectores: HSEQ, Mantenimiento, Operaciones, Recursos Humanos, Finanzas, Compras, Ventas, Gerencia y SGI.
- Documentación por sector con carpetas y subcarpetas ilimitadas.
- Carga individual, múltiple, carpeta completa y ZIP conservando subcarpetas.
- Prevención de archivos temporales de Office y duplicados dentro de una misma carpeta.
- Vista previa para PDF/JPG/JPEG/PNG y apertura del resto de archivos permitidos.
- Eliminación de documentos físicos y eliminación recursiva de carpetas con subcarpetas/documentos.
- Roles y permisos: Administrador, Supervisor, Ventas y Operador.
- SGI visible para todos los usuarios autenticados.
- Administración de usuarios: alta, edición, contraseña, sector, activación/desactivación y eliminación.
- Gestión operativa, búsqueda global, reportes, auditoría y diagnóstico.
- Docker con Apache/PHP 8.3, MariaDB y phpMyAdmin.
- ZipArchive ya incluido en la imagen Docker.
- Base de datos auto-inicializable y migraciones no destructivas para la estructura anterior.
- Scripts de backup para PowerShell y Ubuntu/WSL.

## Importante sobre tus documentos actuales

El ZIP que usé como base no contenía tus PDFs/DOCX reales: `uploads` venía prácticamente vacío. Por eso esta entrega no puede inventar esos documentos. Si en tu proyecto actual `naser-docker/uploads` tenés archivos cargados, CONSERVÁ esa carpeta. Al copiar esta versión encima, no borres `uploads`.

La base de Docker también se conserva mientras uses `docker compose down` sin `-v`.

## Instalación recomendada sobre tu proyecto actual

1. Hacé un backup:

```bash
./backup-db.sh
```

O desde PowerShell:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\backup-db.ps1
```

2. Guardá aparte tu carpeta `uploads` si contiene documentos importantes.
3. Copiá los archivos de esta versión dentro de tu carpeta `naser-docker`.
4. NO ejecutes `docker compose down -v` porque `-v` elimina el volumen de la base.
5. Reconstruí:

```bash
docker compose down
docker compose up -d --build
```

6. Verificá:

```bash
docker compose ps
```

7. Abrí:

- Sistema: `http://localhost:8080/`
- Login directo: `http://localhost:8080/php/login.php`
- phpMyAdmin: `http://localhost:8081/`

## Cuenta principal

- Correo: `admin@naser.test`
- Contraseña: `Admin123!`

El resto está en `CUENTAS_PRUEBA.txt`.

## Prueba funcional antes de presentar

Entrá como administrador y verificá en este orden:

1. Dashboard y las 4 tarjetas de recursos.
2. HSEQ/Mantenimiento/Operaciones y resto de sectores.
3. SGI.
4. Crear carpeta y subcarpeta.
5. Subir 1 PDF.
6. Subir varios archivos.
7. Subir una carpeta completa con subcarpetas.
8. Importar un ZIP.
9. Abrir y previsualizar un PDF.
10. Buscar el documento cargado.
11. Entrar a Usuarios y probar un Supervisor.
12. Iniciar sesión como Operador y comprobar que no puede gestionar documentos.
13. Reportes y Auditoría.
14. `Administración > Diagnóstico`: todos los chequeos deben aparecer OK.

## Para trabajar desde Ubuntu/WSL

```bash
cd /mnt/c/Users/zebal/OneDrive/Documents/naser-docker
docker compose up -d
docker compose ps
git status
```

## Preparado para servidor

Las rutas ya no dependen de `/Naser2026`. El sistema usa `APP_BASE`.

En Docker local:

```env
APP_BASE=
```

Si más adelante NASER se publica dentro de un subdirectorio, por ejemplo `https://dominio.com/naser`, se puede configurar:

```env
APP_BASE=/naser
```

La conexión de base también se maneja por variables `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS`, por lo que no hace falta reescribir el PHP al moverlo al servidor.

## Seguridad y configuración (actualización 30/09/2026)

- **APP_ENV** en `.env`: `dev` muestra errores y la cuenta demo en el login; `production` los oculta y guarda los errores en `logs/php-errors.log`. En el servidor real usar `APP_ENV=production`.
- **`.htaccess` en la raíz**: bloquea el acceso web a `.env`, `.git`, `*.sql`, `backups/`, `sql/`, `docker/`, etc. Docker lo activa con `docker/apache-naser.conf` (requiere `docker compose up -d --build` la primera vez).
- **CSRF**: todos los formularios POST llevan `<?=csrf_field()?>` y cada página llama a `verify_csrf()`. Si agregás un formulario nuevo, agregale `<?=csrf_field()?>`.
- **Login**: se bloquea 15 minutos tras 5 intentos fallidos (tabla `login_intentos`).
- **Tablas nuevas**: se definen en `php/config/schema_modulos.php` (se crean solas). No crear tablas dentro de las páginas.
- **CSS**: usar `asset('/ruta.css')` en vez de `app_url()` para que el navegador baje la versión nueva al cambiar el archivo. Estilos compartidos en `css/modules.css` y `css/checklists.css`.
- **Páginas de módulo**: usar `require __DIR__ . '/config/modulo.php';` y `cargarModulo($pdo, 'slug')`.
- **Base de datos**: `base_naser_compartir.sql` ya no se sube a GitHub (el repo es público). Compartirlo por otro medio.

## Formularios digitales, permisos y avisos (actualización 01/10/2026)

### Formularios
- Los HTML originales están en `formularios/<sector>/` y se registran en `php/config/formularios_catalogo.php` (63 formularios: HSEQ, RRHH, Compras, Ventas, Operaciones y Mantenimiento).
- Se completan desde **Formularios** (menú) o desde la página de cada sector. Todo se guarda en la tabla `formularios_registros` (campos + estado interno del formulario) con historial en `formularios_historial`.
- Circuito: **Borrador → Enviado (pendiente de aprobación) → Aprobado / Rechazado**. Al enviar, los responsables del sector reciben aviso en la campanita y por mail. Al aprobar o rechazar, se avisa a quien lo cargó.
- Los botones propios de cada formulario (Guardar, Finalizar, Agregar...) también guardan en la base.
- Exportar a Excel: botón en la lista de formularios (filtrando por un formulario se exportan todos sus campos).
- Para sumar un formulario nuevo: copiar el HTML a `formularios/<sector>/` y agregar una línea en el catálogo.
- Operaciones incluye los 12 documentos nuevos. Las imágenes de Inicio abren los formularios relacionados con cada recurso; los accesos para completar y consultar respetan los permisos de cada usuario.
- Los formularios usan `css/formularios-responsive.css` al abrirse dentro del sistema: misma información en computadora y celular, campos adaptados y tablas con desplazamiento local. El diseño de impresión se conserva.
- **Mantenimiento** incluye los checklists Vehicular e Hidrogrúa y 11 formatos digitalizados: utilización del alambre, inspección operativa, elementos críticos de operación, unidad Slickline, lavaojos, izaje y guinche, polea de reenvío, apertura/cierre de BOP, herramientas de mano, extintores y elementos de izaje.
- Los 11 formatos usan `js/mantenimiento-formularios.js` y `css/mantenimiento-formularios.css`. Conservan los códigos, revisiones, criterios y opciones del documento de origen; cada HTML contiene su definición en `maintenance-config`. En las planillas se pueden agregar y eliminar filas, con identificadores estables para conservar los datos.
- En estos formatos, **Guardar** guarda un borrador en la base; **Finalizar checklist** valida y envía a aprobación; **Limpiar** vacía los campos sin borrar el registro guardado hasta volver a guardar; **Comenzar nuevo** abre un registro nuevo. Los botones anteriores de los demás formularios se mantienen. Las firmas y aclaraciones son campos de texto.
- Se verificaron los 11 formatos con una base temporal: guardado y recuperación, filas dinámicas, validación, envío/aprobación/reapertura, limpieza, nuevo registro y vistas a 320 y 1440 px.

### Permisos
- **Super usuario** (rol admin): ve, edita y aprueba todo; administra y elimina usuarios.
- Por sector: **Responsable** (edita, gestiona y aprueba), **Operador** (ve y completa formularios), **Observador** (ve).
- Todos ven todos los sectores, salvo los **restringidos** (Operaciones), que solo ven sus integrantes.
- **Burbuja colaborativa** (Burbuja 2: Finanzas, RRHH, Compras, Ventas): los responsables de cualquiera de esos sectores pueden editar y completar en todos, pero aprueba solo el responsable del área.
- La estructura de NASER (personas, responsables, observadores, burbujas) está en `php/config/estructura_naser.php`. Se aplica desde **Usuarios y permisos → Aplicar estructura NASER**. Crea los usuarios `nombre.apellido@gruponaser.com.ar` con contraseña temporal (se cambia en el primer ingreso).

### Avisos y mails
- Campanita en el menú + página **Notificaciones**. Cada usuario puede desactivar los mails en **Mi perfil**.
- Vencimientos (RRHH, Finanzas, documentos) se avisan solos a los responsables: 15 días antes, 3 días antes y el día del vencimiento.
- En Docker viene **Mailpit**: todos los mails del sistema se ven en http://localhost:8025. Para mandar mails reales, completar `SMTP_*` en `.env`.

### Copias de seguridad
- El servicio `backup` guarda la base todos los días (y `uploads` los domingos) en `./backups`, conservando 7 días.

### Prueba rápida antes de hacer push
```bash
bash tools/smoke_test.sh tu.usuario@gruponaser.com.ar 'tu-contraseña'
```


### Personal (legajos)
- Menú **Personal** (lo ven RRHH y el super usuario; los responsables de Operaciones, HSEQ, Mantenimiento y Gerencia ven solo las habilitaciones del personal operativo).
- Se carga subiendo el Excel "Listado de Empleados" (hojas PERSONAL y MAILS). Se actualiza por legajo, se puede repetir cada vez que cambie la planilla.
- Licencias de conducir, manejo defensivo, CNRT, izaje, trabajo en altura y exámenes médicos quedan con su vencimiento y se avisan solos.
- Los datos del personal NO se guardan en el repositorio (quedan solo en la base de datos).
- En **Usuarios y permisos → Aplicar estructura** se puede tildar "Crear operadores desde el Personal": crea un usuario operador para cada empleado operativo con mail @gruponaser.com.ar.
