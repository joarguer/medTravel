-- Ejecutar una sola vez en la base actual del sitio.
ALTER TABLE `blog_posts`
  ADD COLUMN `author_user_id` INT NULL DEFAULT NULL AFTER `provider_id`;

ALTER TABLE `blog_posts`
  ADD INDEX `idx_blog_author_user` (`author_user_id`);

UPDATE `blog_posts` bp
JOIN (
  SELECT MIN(u.id) AS user_id, u.provider_id, TRIM(u.nombre) COLLATE utf8mb4_unicode_ci AS author_name
  FROM `usuarios` u
  WHERE u.provider_id IS NOT NULL
    AND TRIM(COALESCE(u.nombre, '')) <> ''
  GROUP BY u.provider_id, TRIM(u.nombre)
) au
  ON au.provider_id = bp.provider_id
 AND au.author_name = TRIM(COALESCE(bp.author_name, '')) COLLATE utf8mb4_unicode_ci
SET bp.author_user_id = au.user_id
WHERE bp.author_user_id IS NULL
  AND bp.provider_id IS NOT NULL
  AND TRIM(COALESCE(bp.author_name, '')) <> '';
