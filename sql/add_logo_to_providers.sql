-- Agregar columna logo a la tabla providers
-- Esta migración agrega soporte para almacenar el nombre del archivo del logo

ALTER TABLE `providers` 
ADD COLUMN `logo` VARCHAR(255) NULL DEFAULT NULL AFTER `website`;
