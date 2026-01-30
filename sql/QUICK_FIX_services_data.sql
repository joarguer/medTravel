-- Ejecutar SOLO SI el panel admin muestra campos vacíos
-- Este archivo actualiza los registros existentes a activo='1' o los crea si no existen

-- Verificar si existe el registro, si no existe, insertarlo
INSERT INTO `services_page_header` (`id`, `title`, `subtitle`, `main_title`, `description`, `header_image`, `activo`) 
VALUES (1, 'Our Services', 'Comprehensive Services', 'Complete Coordination & Management', 'At MedTravel we connect patients from the United States with certified medical providers in Colombia, offering complete coordination service from planning to post-procedure follow-up.', 'img/carousel-1.jpg', '1')
ON DUPLICATE KEY UPDATE 
    activo = '1',
    title = IF(title = '', 'Our Services', title),
    subtitle = IF(subtitle = '', 'Comprehensive Services', subtitle),
    main_title = IF(main_title = '', 'Complete Coordination & Management', main_title),
    description = IF(description = '', 'At MedTravel we connect patients from the United States with certified medical providers in Colombia, offering complete coordination service from planning to post-procedure follow-up.', description),
    header_image = IF(header_image = '', 'img/carousel-1.jpg', header_image);

-- Actualizar servicios a activo='1' si existen
UPDATE coordination_services SET activo = '1' WHERE activo = '0';
