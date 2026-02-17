-- Ejecutar UNA sola vez en cPanel/MySQL.
-- Agrega FK de ownership complementario:
-- usuarios.service_provider_id -> service_providers.id

ALTER TABLE `usuarios`
ADD CONSTRAINT `fk_usuarios_service_provider`
FOREIGN KEY (`service_provider_id`)
REFERENCES `service_providers`(`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;
