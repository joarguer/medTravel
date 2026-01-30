-- ============================================================
-- AGREGAR CAMPO RAZÓN SOCIAL (legal_name) A TABLA providers
-- ============================================================
-- Ejecutar directamente en la base de datos de producción
-- NO incluir USE database; (ejecutar desde la BD seleccionada)
-- ============================================================

-- Versión SIMPLE: Ejecutar solo si NO existe el campo
ALTER TABLE providers 
ADD COLUMN legal_name VARCHAR(250) DEFAULT NULL 
COMMENT 'Razón social o nombre legal' 
AFTER name;

-- ============================================================
-- Si el campo ya existe, usar esta versión alternativa:
-- ALTER TABLE providers MODIFY COLUMN legal_name VARCHAR(250) DEFAULT NULL COMMENT 'Razón social o nombre legal';
-- ============================================================
