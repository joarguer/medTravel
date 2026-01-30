-- Eliminar registros duplicados de home_como_funciona
-- Mantener solo los primeros 4 registros (IDs 1-4)
DELETE FROM `home_como_funciona` WHERE `id` > 4;

-- Eliminar registros duplicados de home_services
-- Mantener solo los primeros 6 registros (IDs 1-6)
DELETE FROM `home_services` WHERE `id` > 6;

-- Actualizar iconos de home_services para usar Font Awesome 4 (fa en lugar de fas)
UPDATE `home_services` SET `icon_class` = 'fa fa-heartbeat' WHERE `id` = 1;
UPDATE `home_services` SET `icon_class` = 'fa fa-plane' WHERE `id` = 2;
UPDATE `home_services` SET `icon_class` = 'fa fa-bed' WHERE `id` = 3;
UPDATE `home_services` SET `icon_class` = 'fa fa-car' WHERE `id` = 4;
UPDATE `home_services` SET `icon_class` = 'fa fa-cutlery' WHERE `id` = 5;
UPDATE `home_services` SET `icon_class` = 'fa fa-headphones' WHERE `id` = 6;

-- Actualizar textos a inglés para mercado USA (Florida)
-- Cómo Funciona
UPDATE `home_como_funciona` SET 
    `title` = 'Initial Consultation',
    `description` = 'Share your medical needs and travel preferences with us'
WHERE `id` = 1;

UPDATE `home_como_funciona` SET 
    `title` = 'Coordination',
    `description` = 'We connect you with certified providers and coordinate appointments'
WHERE `id` = 2;

UPDATE `home_como_funciona` SET 
    `title` = 'Logistics',
    `description` = 'We arrange flights, accommodation, transportation and meals'
WHERE `id` = 3;

UPDATE `home_como_funciona` SET 
    `title` = 'Support',
    `description` = '24/7 assistance during your stay and post-procedure follow-up'
WHERE `id` = 4;

-- Servicios Detallados
UPDATE `home_services` SET 
    `title` = 'Medical Coordination',
    `description` = 'We connect you with certified providers, coordinate appointments and provide specialized translation'
WHERE `id` = 1;

UPDATE `home_services` SET 
    `title` = 'Flight Management',
    `description` = 'We find the best flight options from USA to Colombia adapted to your medical schedule'
WHERE `id` = 2;

UPDATE `home_services` SET 
    `title` = 'Accommodation',
    `description` = 'Hotels near clinics, adapted to your recovery and budget'
WHERE `id` = 3;

UPDATE `home_services` SET 
    `title` = 'Local Transportation',
    `description` = 'Airport, clinic and hotel transfers with punctuality and comfort'
WHERE `id` = 4;

UPDATE `home_services` SET 
    `title` = 'Meals',
    `description` = 'Options that meet medical restrictions and post-operative diets'
WHERE `id` = 5;

UPDATE `home_services` SET 
    `title` = '24/7 Support',
    `description` = 'Permanent bilingual assistance and emergency management',
    `badge` = 'Always Available'
WHERE `id` = 6;
