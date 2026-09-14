-- NASER SGI - Integración nativa del módulo de Ventas
-- Adaptado de los archivos recibidos para usar la MISMA base naser_sgi_prueba.
-- No crea una base nueva y no borra datos existentes.

CREATE TABLE IF NOT EXISTS ventas_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    razon_social VARCHAR(150) NOT NULL,
    cuit VARCHAR(20) NULL,
    contacto_nombre VARCHAR(100) NULL,
    contacto_email VARCHAR(120) NULL,
    contacto_telefono VARCHAR(50) NULL,
    provincia VARCHAR(60) DEFAULT 'Neuquén',
    creado_por INT NULL,
    actualizado_por INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ventas_clientes_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas_contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    cliente_id INT NOT NULL,
    numero_contrato VARCHAR(80) NOT NULL,
    servicio_operativo VARCHAR(150) NOT NULL,
    monto_estimado_usd DECIMAL(14,2) NOT NULL DEFAULT 0,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    estado ENUM('Activo','Licitación','Finalizado') NOT NULL DEFAULT 'Activo',
    observaciones TEXT NULL,
    creado_por INT NULL,
    actualizado_por INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vc_cliente FOREIGN KEY (cliente_id) REFERENCES ventas_clientes(id) ON DELETE CASCADE,
    INDEX idx_ventas_contratos_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas_precios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    servicio_nombre VARCHAR(150) NOT NULL,
    unidad_medida VARCHAR(80) NOT NULL,
    modalidad VARCHAR(120) NULL,
    tarifa_base_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    ticket_promedio_tipo_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    creado_por INT NULL,
    actualizado_por INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ventas_precios_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas_costos_operativos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    linea_servicio VARCHAR(150) NOT NULL,
    ingreso_diario_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    costo_directo_finanzas_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    costo_mantenimiento_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    estado_rentabilidad VARCHAR(50) DEFAULT 'Sostenible',
    creado_por INT NULL,
    actualizado_por INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ventas_costos_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas_presentaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    empresa VARCHAR(80) NOT NULL DEFAULT 'Naser',
    categoria VARCHAR(80) DEFAULT 'General',
    archivo_path VARCHAR(500) NOT NULL,
    fecha_carga DATE NOT NULL DEFAULT (CURRENT_DATE),
    creado_por INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ventas_presentaciones_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas_cotizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    codigo_cotizacion VARCHAR(80) NOT NULL UNIQUE,
    cliente_id INT NOT NULL,
    monto_usd DECIMAL(14,2) NOT NULL DEFAULT 0,
    estado_kpi ENUM('Ganada','En Estudio','No Adjudicada') NOT NULL DEFAULT 'En Estudio',
    fecha_presentacion DATE NOT NULL,
    fecha_resolucion DATE NULL,
    creado_por INT NULL,
    actualizado_por INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vcot_cliente FOREIGN KEY (cliente_id) REFERENCES ventas_clientes(id) ON DELETE CASCADE,
    INDEX idx_ventas_cot_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas_crm (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    cliente_id INT NOT NULL,
    oportunidad_servicio VARCHAR(180) NOT NULL,
    etapa_pipeline ENUM('Prospecto','Cotizado','En Negociación','Cierre Ganado','Perdido') NOT NULL DEFAULT 'Prospecto',
    fecha_ultimo_contacto DATE NOT NULL,
    proxima_accion TEXT NULL,
    responsable_naser VARCHAR(120) NULL,
    creado_por INT NULL,
    actualizado_por INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vcrm_cliente FOREIGN KEY (cliente_id) REFERENCES ventas_clientes(id) ON DELETE CASCADE,
    INDEX idx_ventas_crm_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas_notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo_alerta ENUM('Alerta','Aviso','Finanzas','Vencimiento') NOT NULL DEFAULT 'Aviso',
    leido TINYINT(1) NOT NULL DEFAULT 0,
    creado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ventas_notif_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,
    sector_emisor VARCHAR(80) NOT NULL DEFAULT 'Ventas',
    sector_destino VARCHAR(80) NOT NULL,
    asunto VARCHAR(180) NOT NULL,
    mensaje_texto TEXT NOT NULL,
    creado_por INT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ventas_mensajes_sector (sector_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
