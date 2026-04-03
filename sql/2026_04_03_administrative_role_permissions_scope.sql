-- =======================================================
-- MIGRACION: alinear scope real de role `administrative`
-- Fecha: 2026-04-03
-- Objetivo:
--   - dejar al rol `administrative` solo con permisos de coordinación CARE
--   - booking.view
--   - booking.assisted.create
-- Nota:
--   El hardening en PHP se mantiene como defensa adicional, pero la fuente
--   de verdad en BD debe reflejar este scope.
-- =======================================================

START TRANSACTION;

SET @administrative_role_id := (
  SELECT id
  FROM roles
  WHERE slug = 'administrative'
  LIMIT 1
);
SET @administrative_role_id := COALESCE(@administrative_role_id, 2);

SET @perm_booking_view := (
  SELECT id
  FROM permissions
  WHERE slug = 'booking.view'
  LIMIT 1
);

SET @perm_booking_assisted_create := (
  SELECT id
  FROM permissions
  WHERE slug = 'booking.assisted.create'
  LIMIT 1
);

DELETE FROM role_permissions
WHERE role_id = @administrative_role_id
  AND permission_id NOT IN (
    COALESCE(@perm_booking_view, -1),
    COALESCE(@perm_booking_assisted_create, -1)
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT @administrative_role_id, @perm_booking_view
WHERE @perm_booking_view IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM role_permissions
    WHERE role_id = @administrative_role_id
      AND permission_id = @perm_booking_view
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT @administrative_role_id, @perm_booking_assisted_create
WHERE @perm_booking_assisted_create IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM role_permissions
    WHERE role_id = @administrative_role_id
      AND permission_id = @perm_booking_assisted_create
  );

COMMIT;
