-- Ejecutar una sola vez en la base actual del sitio.
ALTER TABLE `blog_posts`
  ADD COLUMN `video_file` VARCHAR(500) NULL AFTER `video_url`;
