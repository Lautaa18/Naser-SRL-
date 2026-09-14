-- Ajustes finales según solicitudes reales de Finanzas, RRHH y Compras
ALTER TABLE finanzas_alertas
 ADD COLUMN IF NOT EXISTS estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
 ADD COLUMN IF NOT EXISTS fecha_vencimiento DATE NULL;

-- Las demás estructuras ya existen. Esta migración es aditiva y no elimina datos.
