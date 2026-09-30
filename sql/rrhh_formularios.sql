-- Tabla para los checklists / formularios de RRHH (usada por php/rrhh.php y php/api_rrhh_formulario.php)
CREATE TABLE IF NOT EXISTS rrhh_formularios (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
