La versión actual inicializa y actualiza la estructura de base automáticamente desde php/config/bootstrap.php.
No hace falta importar SQL manualmente para la prueba Docker.
Las migraciones son no destructivas: crean tablas/columnas faltantes y no borran documentos existentes.
