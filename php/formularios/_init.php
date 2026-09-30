<?php
// Carga comun de las paginas de formularios
require __DIR__ . '/../config/auth.php';
requireLogin();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/formularios_catalogo.php';
require_once __DIR__ . '/../config/notificaciones.php';

function cargarRegistro(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT r.*, a.nombre AS autor, e.nombre AS enviado_nombre, v.nombre AS revisor
                         FROM formularios_registros r
                         LEFT JOIN usuarios a ON a.id = r.creado_por
                         LEFT JOIN usuarios e ON e.id = r.enviado_por
                         LEFT JOIN usuarios v ON v.id = r.revisado_por
                         WHERE r.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
