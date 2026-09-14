<?php
require_once __DIR__ . '/app.php';

$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'naser_sgi_prueba';
$user = getenv('DB_USER') ?: 'naser';
$pass = getenv('DB_PASS') ?: 'naser2026';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Error de conexión a la base de datos. Verificá que Docker esté iniciado y que el servicio db esté activo.');
}

require_once __DIR__ . '/bootstrap.php';
naser_bootstrap($pdo);
