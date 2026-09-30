<?php
// ==========================================
// Tablas de los modulos (Compras, RRHH, login, etc.)
// Se crean solas la primera vez. Si agregas una tabla nueva, agregala ACA
// y no dentro de la pagina: asi funciona en todas las PCs sin importar SQL.
// ==========================================

function naser_schema_modulos(PDO $pdo): void
{
    // ---------- COMPRAS ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS compras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sector_id INT NOT NULL,
        codigo VARCHAR(30) NOT NULL,
        sector VARCHAR(100),
        descripcion TEXT NOT NULL,
        cantidad INT DEFAULT 1,
        prioridad ENUM('Normal','Urgente','Critico') DEFAULT 'Normal',
        proveedor VARCHAR(150) DEFAULT 'Pendiente',
        monto DECIMAL(12,2) DEFAULT 0.00,
        moneda VARCHAR(5) DEFAULT 'ARS',
        etapa INT DEFAULT 1,
        estado_logistico VARCHAR(100) DEFAULT 'Pedido cargado',
        creado_por INT,
        actualizado_por INT,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS compras_documentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        compra_id INT NOT NULL,
        etapa INT NOT NULL,
        nombre_archivo VARCHAR(255) NOT NULL,
        ruta_archivo VARCHAR(255) NOT NULL,
        tipo_documento VARCHAR(100) DEFAULT 'Adjunto',
        creado_por INT,
        fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS compras_encuesta (
        id INT AUTO_INCREMENT PRIMARY KEY,
        compra_id INT NOT NULL,
        calidad VARCHAR(50) NOT NULL,
        estado_fisico VARCHAR(50) NOT NULL,
        cumplimiento_entrega VARCHAR(50) NOT NULL,
        observaciones TEXT,
        creado_por INT,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS compras_formularios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sector_id INT NOT NULL,
        codigo_form VARCHAR(100) NOT NULL,
        proveedor_nombre VARCHAR(150),
        solicitante VARCHAR(150),
        fecha_documento DATE,
        estado VARCHAR(50) DEFAULT 'en_edicion',
        creado_por INT,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Columnas agregadas despues (para bases creadas con una version vieja)
    if (!hasColumn($pdo, 'compras', 'monto')) {
        $pdo->exec("ALTER TABLE compras ADD COLUMN monto DECIMAL(12,2) DEFAULT 0.00 AFTER proveedor");
    }
    if (!hasColumn($pdo, 'compras', 'moneda')) {
        $pdo->exec("ALTER TABLE compras ADD COLUMN moneda VARCHAR(5) DEFAULT 'ARS' AFTER monto");
    }
    if (!hasColumn($pdo, 'compras_encuesta', 'fecha_registro')) {
        $pdo->exec("ALTER TABLE compras_encuesta ADD COLUMN fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP");
    }

    // ---------- RRHH: checklists / formularios ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS rrhh_formularios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sector_id INT NOT NULL,
        codigo_form VARCHAR(100) NOT NULL,
        empleado_nombre VARCHAR(150),
        legajo VARCHAR(50),
        fecha_documento DATE,
        estado VARCHAR(50) DEFAULT 'en_edicion',
        datos_json LONGTEXT,
        creado_por INT,
        actualizado_por INT,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ---------- LOGIN: intentos fallidos (bloqueo temporal) ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_intentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(160) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_fecha (email, creado_en),
        INDEX idx_ip_fecha (ip, creado_en)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
