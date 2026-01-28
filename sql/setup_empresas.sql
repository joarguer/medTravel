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

-- 4. Asignar el primer proveedor al usuario admin principal (ajustar según tu caso)
-- Opción A: Asignar al usuario con ppal=1
UPDATE usuarios 
SET provider_id = (SELECT id FROM providers ORDER BY id ASC LIMIT 1)
WHERE ppal = 1 AND provider_id IS NULL;

-- Opción B: Asignar por nombre de usuario específico (descomenta y ajusta)
-- UPDATE usuarios 
-- SET provider_id = (SELECT id FROM providers ORDER BY id ASC LIMIT 1)
-- WHERE usuario = 'admin' AND provider_id IS NULL;

-- Opción C: Asignar por ID de usuario (descomenta y ajusta el ID)
-- UPDATE usuarios 
-- SET provider_id = (SELECT id FROM providers ORDER BY id ASC LIMIT 1)
-- WHERE id = 1 AND provider_id IS NULL;

-- Verificar resultado
SELECT u.id, u.nombre, u.usuario, u.ppal, u.provider_id, p.name as empresa_nombre
FROM usuarios u
LEFT JOIN providers p ON u.provider_id = p.id
WHERE u.ppal = 1 OR u.provider_id IS NOT NULL;
