-- Script para agregar el campo header_image a la tabla services_page_header
-- Ejecutar solo si ya tienes la tabla creada sin este campo

-- Verificar si la columna no existe antes de agregarla
ALTER TABLE `services_page_header` 
ADD COLUMN IF NOT EXISTS `header_image` varchar(255) DEFAULT 'img/carousel-1.jpg' AFTER `description`;

-- Actualizar el registro existente con una imagen por defecto si no tiene
UPDATE `services_page_header` 
SET `header_image` = 'img/carousel-1.jpg' 
WHERE `header_image` IS NULL OR `header_image` = '';
