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
    // ---------- PERMISOS: burbujas, sectores restringidos y rol por sector ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS burbujas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        descripcion VARCHAR(255) NULL,
        colaborativa TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = los responsables de la burbuja pueden editar y completar en todos sus sectores'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!hasColumn($pdo, 'sectores', 'burbuja_id')) {
        $pdo->exec("ALTER TABLE sectores ADD COLUMN burbuja_id INT NULL");
    }
    if (!hasColumn($pdo, 'sectores', 'restringido')) {
        // 1 = solo los usuarios asignados al sector pueden verlo (ej: Operaciones)
        $pdo->exec("ALTER TABLE sectores ADD COLUMN restringido TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!hasColumn($pdo, 'usuario_sector', 'rol_sector')) {
        $pdo->exec("ALTER TABLE usuario_sector ADD COLUMN rol_sector ENUM('responsable','operador','observador') NOT NULL DEFAULT 'operador'");
        // Migracion: quien podia editar pasa a ser responsable
        $pdo->exec("UPDATE usuario_sector SET rol_sector = IF(puede_editar = 1, 'responsable', 'operador')");
        // SGI lo ven todos: quienes solo tenian lectura quedan como observadores
        $pdo->exec("UPDATE usuario_sector us JOIN sectores s ON s.id = us.sector_id SET us.rol_sector = 'observador' WHERE s.slug = 'sgi' AND us.puede_editar = 0");
    }
    if (!hasColumn($pdo, 'usuarios', 'debe_cambiar_password')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!hasColumn($pdo, 'usuarios', 'ultimo_acceso')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN ultimo_acceso DATETIME NULL");
    }
    if (!hasColumn($pdo, 'usuarios', 'recibe_mails')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN recibe_mails TINYINT(1) NOT NULL DEFAULT 1");
    }

    // ---------- FORMULARIOS DIGITALES (checklists) ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS formularios_registros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        formulario VARCHAR(80) NOT NULL COMMENT 'codigo del catalogo (formularios_catalogo.php)',
        sector_id INT NOT NULL,
        referencia VARCHAR(200) NULL COMMENT 'Ej: nombre del trabajador, proveedor, pozo',
        estado ENUM('borrador','enviado','aprobado','rechazado') NOT NULL DEFAULT 'borrador',
        datos_json LONGTEXT NULL COMMENT 'valores de los campos del formulario',
        storage_json LONGTEXT NULL COMMENT 'estado interno del formulario (listas, filas agregadas)',
        creado_por INT NULL,
        actualizado_por INT NULL,
        enviado_por INT NULL,
        enviado_en DATETIME NULL,
        revisado_por INT NULL,
        revisado_en DATETIME NULL,
        comentario_revision TEXT NULL,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sector_estado (sector_id, estado),
        INDEX idx_formulario (formulario),
        INDEX idx_creado_por (creado_por)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS formularios_historial (
        id INT AUTO_INCREMENT PRIMARY KEY,
        registro_id INT NOT NULL,
        usuario_id INT NULL,
        accion VARCHAR(40) NOT NULL,
        comentario TEXT NULL,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_registro (registro_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ---------- NOTIFICACIONES ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo VARCHAR(40) NOT NULL DEFAULT 'info',
        titulo VARCHAR(200) NOT NULL,
        mensaje TEXT NULL,
        url VARCHAR(255) NULL,
        clave VARCHAR(120) NULL COMMENT 'evita avisos duplicados (ej: vencimientos)',
        leida TINYINT(1) NOT NULL DEFAULT 0,
        mail_enviado TINYINT(1) NOT NULL DEFAULT 0,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario_leida (usuario_id, leida),
        UNIQUE KEY uk_usuario_clave (usuario_id, clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ---------- CONFIGURACION DEL SISTEMA (tareas diarias, etc.) ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_config (
        clave VARCHAR(80) PRIMARY KEY,
        valor TEXT NULL,
        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // ---------- PERSONAL (legajos de empleados) ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS empleados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        legajo VARCHAR(20) NOT NULL,
        apellido_nombre VARCHAR(160) NOT NULL,
        dni VARCHAR(20) NULL,
        cuil VARCHAR(20) NULL,
        servicio VARCHAR(80) NULL,
        sector_id INT NULL,
        cargo VARCHAR(150) NULL,
        fecha_ingreso DATE NULL,
        fecha_nacimiento DATE NULL,
        obra_social VARCHAR(30) NULL,
        convenio VARCHAR(30) NULL,
        encuadre VARCHAR(80) NULL,
        categoria VARCHAR(40) NULL,
        nueva_categoria VARCHAR(40) NULL,
        domicilio VARCHAR(200) NULL,
        cp VARCHAR(10) NULL,
        localidad VARCHAR(80) NULL,
        provincia VARCHAR(80) NULL,
        telefono VARCHAR(60) NULL,
        estudios VARCHAR(150) NULL,
        sindicato VARCHAR(80) NULL,
        mutual VARCHAR(80) NULL,
        deposito VARCHAR(60) NULL,
        email_corporativo VARCHAR(160) NULL,
        email_personal VARCHAR(160) NULL,
        usuario_id INT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        fecha_baja DATE NULL,
        observaciones TEXT NULL,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_legajo (legajo),
        INDEX idx_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Licencias, cursos, examenes medicos y certificaciones con vencimiento
    $pdo->exec("CREATE TABLE IF NOT EXISTS empleados_habilitaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id INT NOT NULL,
        tipo VARCHAR(40) NOT NULL,
        detalle VARCHAR(120) NULL,
        fecha_realizacion DATE NULL,
        fecha_vencimiento DATE NULL,
        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_emp_tipo (empleado_id, tipo),
        INDEX idx_venc (fecha_vencimiento),
        CONSTRAINT fk_hab_emp FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Aplica los archivos de /sql (RRHH, Finanzas, Ventas...) de forma automatica.
 * Son scripts "CREATE TABLE IF NOT EXISTS" / "ADD COLUMN IF NOT EXISTS", asi que se pueden repetir sin romper nada.
 * Antes habia que importarlos a mano, y en una instalacion nueva fallaban RRHH, Finanzas y Ventas.
 */
function naser_schema_sql(PDO $pdo): void
{
    $dir = dirname(__DIR__, 2) . '/sql';
    $orden = ['modulos_integrados', 'integracion_nativa', 'ajustes_solicitudes_reales', 'ventas_integracion', 'rrhh_formularios'];
    foreach ($orden as $nombre) {
        $archivo = $dir . '/' . $nombre . '.sql';
        if (!is_file($archivo)) continue;
        $lineas = array_filter(
            preg_split('/\r\n|\r|\n/', (string)file_get_contents($archivo)),
            fn($l) => !preg_match('/^\s*--/', $l)
        );
        foreach (explode(';', implode("\n", $lineas)) as $sentencia) {
            $sentencia = trim($sentencia);
            if ($sentencia === '') continue;
            try {
                $pdo->exec($sentencia);
            } catch (Throwable $e) {
                error_log('[NASER] sql/' . $nombre . '.sql: ' . $e->getMessage());
            }
        }
    }
}
