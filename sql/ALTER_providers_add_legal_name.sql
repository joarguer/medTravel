-- Agregar campo legal_name (razón social) a la tabla providers
-- Ejecutar este script en la base de datos de producción

USE bolsacar_medtravel;

-- Verificar si la columna existe antes de agregarla
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'bolsacar_medtravel' 
  AND TABLE_NAME = 'providers' 
  AND COLUMN_NAME = 'legal_name';

SET @query = IF(@col_exists = 0,
    'ALTER TABLE providers ADD COLUMN legal_name VARCHAR(250) DEFAULT NULL COMMENT ''Razón social o nombre legal'' AFTER name',
    'SELECT ''La columna legal_name ya existe'' AS message'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Script ejecutado correctamente' AS resultado;
