-- NASER - Integración de módulos enviados por tu compañero
-- Ejecutar sobre la MISMA base que usa config/db.php (en Docker: naser_sgi_prueba)

CREATE TABLE IF NOT EXISTS rrhh_vencimientos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 empleado_nombre VARCHAR(150) NOT NULL,
 tipo VARCHAR(100) NOT NULL,
 fecha_inicio DATE NULL,
 fecha_vencimiento DATE NOT NULL,
 observaciones TEXT NULL,
 creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finanzas_trabajadores (
 id INT AUTO_INCREMENT PRIMARY KEY,
 legajo VARCHAR(20) UNIQUE NOT NULL,
 nombre_completo VARCHAR(150) NOT NULL,
 dni VARCHAR(20) NOT NULL,
 sector VARCHAR(100) NOT NULL,
 email VARCHAR(100) NULL,
 telefono VARCHAR(50) NULL,
 categoria_carnet VARCHAR(50) NULL,
 vencimiento_carnet DATE NOT NULL,
 curso_defensivo VARCHAR(100) NULL,
 vencimiento_defensivo DATE NOT NULL,
 estado ENUM('vigente','por_vencer','vencido') DEFAULT 'vigente',
 fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finanzas_indicadores (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nombre_indicador VARCHAR(100) NOT NULL,
 valor_porcentaje INT DEFAULT 0,
 fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finanzas_checklist (
 id INT AUTO_INCREMENT PRIMARY KEY,
 requisito VARCHAR(255) NOT NULL,
 categoria VARCHAR(100) NOT NULL,
 frecuencia VARCHAR(50),
 completado TINYINT(1) DEFAULT 0,
 archivo_adjunto VARCHAR(255),
 estado_auditoria VARCHAR(50) DEFAULT 'APROBADO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finanzas_alertas (
 id INT AUTO_INCREMENT PRIMARY KEY,
 trabajador_nombre VARCHAR(150) NOT NULL,
 email VARCHAR(150), telefono VARCHAR(50), canal VARCHAR(100),
 fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compras (
 id INT AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(20) NOT NULL UNIQUE,
 sector VARCHAR(50) NOT NULL,
 descripcion TEXT NOT NULL,
 cantidad INT DEFAULT 1,
 prioridad ENUM('Normal','Urgente','Critico') DEFAULT 'Normal',
 proveedor VARCHAR(100) DEFAULT 'Pendiente',
 etapa INT DEFAULT 1,
 estado_logistico VARCHAR(255) DEFAULT 'Pedido cargado',
 fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compras_documentos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 compra_id INT NOT NULL,
 etapa INT NOT NULL,
 nombre_archivo VARCHAR(255) NOT NULL,
 ruta_archivo VARCHAR(500) NOT NULL,
 fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_compra_doc FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compras_encuesta (
 id INT AUTO_INCREMENT PRIMARY KEY,
 compra_id INT NOT NULL,
 calidad VARCHAR(50) NOT NULL,
 estado_fisico VARCHAR(50) NOT NULL,
 cumplimiento_entrega VARCHAR(50) NOT NULL,
 observaciones TEXT,
 fecha_inspeccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_compra_enc FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
