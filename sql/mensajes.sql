-- Mensajeria entre sectores (se aplica sola desde php/config/schema_modulos.php)
CREATE TABLE IF NOT EXISTS mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    de_usuario_id INT NOT NULL,
    de_sector_id INT NOT NULL,
    para_sector_id INT NOT NULL,
    asunto VARCHAR(150) NOT NULL,
    cuerpo TEXT NOT NULL,
    respuesta_a INT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_para (para_sector_id),
    INDEX idx_de (de_usuario_id),
    INDEX idx_resp (respuesta_a)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mensajes_leidos (
    mensaje_id INT NOT NULL,
    usuario_id INT NOT NULL,
    PRIMARY KEY (mensaje_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
