SET @idx_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'blog_posts'
    AND INDEX_NAME = 'idx_blog_status_published_at'
);

SET @s = IF(
  @idx_exists = 0,
  'ALTER TABLE `blog_posts` ADD INDEX `idx_blog_status_published_at` (`status`, `published_at`)',
  'SELECT 1'
);

PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
