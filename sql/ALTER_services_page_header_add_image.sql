-- Solo ejecutar este archivo si la tabla services_page_header ya existe y no tiene la columna header_image
-- Si obtienes error "Duplicate column name", ignóralo, significa que la columna ya existe

ALTER TABLE `services_page_header` 
ADD COLUMN `header_image` varchar(255) DEFAULT 'img/carousel-1.jpg' AFTER `description`;
