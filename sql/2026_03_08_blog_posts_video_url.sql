-- Ejecutar una sola vez en la base actual del sitio.
ALTER TABLE `blog_posts`
  ADD COLUMN `video_url` VARCHAR(500) NULL AFTER `cover_image`;
