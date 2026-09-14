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
