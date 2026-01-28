-- Script para configurar el sistema multiusuario con empresas

-- 1. Agregar columna logo a la tabla providers (si no existe)
ALTER TABLE `providers` 
ADD COLUMN IF NOT EXISTS `logo` VARCHAR(255) NULL DEFAULT NULL AFTER `website`;

-- 2. Agregar columna provider_id a la tabla usuarios (si no existe)
ALTER TABLE `usuarios` 
ADD COLUMN IF NOT EXISTS `provider_id` INT NULL DEFAULT NULL;

-- 3. Crear una empresa demo si no existe ninguna
INSERT INTO `providers` (type, name, slug, description, city, address, phone, email, website, is_verified, is_active)
SELECT 'clinica', 'MedTravel Clinic', 'medtravel-clinic', 'Clínica principal de servicios médicos', 'Buenos Aires', 'Av. Principal 123', '+54 11 1234-5678', 'info@medtravel.com', 'https://medtravel.com', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM providers LIMIT 1);

-- 4. IMPORTANTE: El admin principal NO debe tener provider_id
-- El admin es el dueño del sitio que registra empresas clientes.
-- Solo los usuarios prestadores deben tener provider_id asignado.
-- Para asignar provider_id a un usuario prestador, usar "Crear Usuarios" en el admin.

-- Ejemplo para asignar provider_id a un usuario específico (ajustar según necesidad):
-- UPDATE usuarios 
-- SET provider_id = (SELECT id FROM providers WHERE name = 'MedTravel Clinic' LIMIT 1)
-- WHERE usuario = 'email_prestador@example.com' AND provider_id IS NULL;

-- Verificar resultado: Ver usuarios prestadores y sus empresas asignadas
SELECT u.id, u.nombre, u.usuario, u.ppal, u.provider_id, p.name as empresa_nombre
FROM usuarios u
LEFT JOIN providers p ON u.provider_id = p.id
WHERE u.provider_id IS NOT NULL OR u.ppal = 1
ORDER BY u.ppal DESC, u.nombre ASC;
