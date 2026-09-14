-- NASER - Migración para integración NATIVA de RRHH / Finanzas / Compras
-- Ejecutar UNA vez en naser_sgi_prueba.
-- Conserva los datos existentes.

ALTER TABLE rrhh_vencimientos
 ADD COLUMN IF NOT EXISTS sector_id INT NULL,
 ADD COLUMN IF NOT EXISTS creado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

UPDATE rrhh_vencimientos r
JOIN sectores s ON s.slug='rrhh'
SET r.sector_id=s.id
WHERE r.sector_id IS NULL;

ALTER TABLE finanzas_trabajadores
 ADD COLUMN IF NOT EXISTS sector_id INT NULL,
 ADD COLUMN IF NOT EXISTS creado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE finanzas_indicadores
 ADD COLUMN IF NOT EXISTS sector_id INT NULL,
 ADD COLUMN IF NOT EXISTS creado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_por INT NULL;

ALTER TABLE finanzas_checklist
 ADD COLUMN IF NOT EXISTS sector_id INT NULL,
 ADD COLUMN IF NOT EXISTS creado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE finanzas_alertas
 ADD COLUMN IF NOT EXISTS sector_id INT NULL,
 ADD COLUMN IF NOT EXISTS creado_por INT NULL;

UPDATE finanzas_trabajadores f JOIN sectores s ON s.slug='finanzas' SET f.sector_id=s.id WHERE f.sector_id IS NULL;
UPDATE finanzas_indicadores f JOIN sectores s ON s.slug='finanzas' SET f.sector_id=s.id WHERE f.sector_id IS NULL;
UPDATE finanzas_checklist f JOIN sectores s ON s.slug='finanzas' SET f.sector_id=s.id WHERE f.sector_id IS NULL;
UPDATE finanzas_alertas f JOIN sectores s ON s.slug='finanzas' SET f.sector_id=s.id WHERE f.sector_id IS NULL;

ALTER TABLE compras
 ADD COLUMN IF NOT EXISTS sector_id INT NULL,
 ADD COLUMN IF NOT EXISTS creado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_por INT NULL,
 ADD COLUMN IF NOT EXISTS actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE compras_documentos
 ADD COLUMN IF NOT EXISTS creado_por INT NULL;

ALTER TABLE compras_encuesta
 ADD COLUMN IF NOT EXISTS creado_por INT NULL;

UPDATE compras c JOIN sectores s ON s.slug='compras' SET c.sector_id=s.id WHERE c.sector_id IS NULL;

-- Índices útiles (MariaDB no soporta ADD INDEX IF NOT EXISTS en todas las versiones,
-- por eso no se fuerzan aquí para evitar romper la migración).
