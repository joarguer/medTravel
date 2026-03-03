-- Backfill client_documents.client_user_id for historical rows.
-- client_documents.client_id remains clientes.id (FK intact).
--
-- Safe/idempotent: updates only rows with client_user_id IS NULL and valid mapping.
--
-- Expected schema:
-- - client_documents.client_user_id (new column) stores usuarios id.
-- - clientes has client_user_id (preferred) or user_id (fallback).

-- Detect mapping columns.
SELECT
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'client_documents' AND column_name = 'client_user_id') AS has_doc_client_user_id,
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'clientes' AND column_name = 'client_user_id') AS has_client_user_id,
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'clientes' AND column_name = 'user_id') AS has_clientes_user_id,
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'id') AS has_usuarios_id,
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'id_usuario') AS has_usuarios_id_usuario;

-- Resolve source/target columns for dynamic SQL.
SET @source_col := IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'clientes' AND column_name = 'client_user_id') = 1,
  'client_user_id',
  IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'clientes' AND column_name = 'user_id') = 1,
    'user_id',
    ''
  )
);

SET @usuarios_id_col := IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'id') = 1,
  'id',
  IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'id_usuario') = 1,
    'id_usuario',
    ''
  )
);

SET @has_doc_client_user_id := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'client_documents' AND column_name = 'client_user_id'
);

-- Abort if mapping columns are missing.
SELECT @source_col AS source_col, @usuarios_id_col AS usuarios_id_col, @has_doc_client_user_id AS has_doc_client_user_id;
SET @valid_mapping := (@source_col <> '' AND @usuarios_id_col <> '' AND @has_doc_client_user_id = 1);

-- Count candidates.
SET @count_sql := IF(
  @valid_mapping,
  CONCAT(
    'SELECT COUNT(*) AS candidates ',
    'FROM client_documents cd ',
    'JOIN clientes c ON c.id = cd.client_id ',
    'JOIN usuarios u ON u.', @usuarios_id_col, ' = c.', @source_col, ' ',
    'WHERE cd.client_user_id IS NULL ',
    'AND c.', @source_col, ' IS NOT NULL ',
    'AND c.', @source_col, ' > 0'
  ),
  "SELECT 'missing_mapping_columns' AS error"
);
PREPARE stmt_count FROM @count_sql;
EXECUTE stmt_count;
DEALLOCATE PREPARE stmt_count;

-- Sample rows for verification.
SET @sample_sql := IF(
  @valid_mapping,
  CONCAT(
    'SELECT cd.id AS doc_id, cd.client_id AS clientes_id, ',
    'c.', @source_col, ' AS new_client_user_id ',
    'FROM client_documents cd ',
    'JOIN clientes c ON c.id = cd.client_id ',
    'JOIN usuarios u ON u.', @usuarios_id_col, ' = c.', @source_col, ' ',
    'WHERE cd.client_user_id IS NULL ',
    'AND c.', @source_col, ' IS NOT NULL ',
    'AND c.', @source_col, ' > 0 ',
    'LIMIT 20'
  ),
  "SELECT 'missing_mapping_columns' AS error"
);
PREPARE stmt_sample FROM @sample_sql;
EXECUTE stmt_sample;
DEALLOCATE PREPARE stmt_sample;

-- Apply update (run inside a transaction).
START TRANSACTION;

SET @update_sql := IF(
  @valid_mapping,
  CONCAT(
    'UPDATE client_documents cd ',
    'JOIN clientes c ON c.id = cd.client_id ',
    'JOIN usuarios u ON u.', @usuarios_id_col, ' = c.', @source_col, ' ',
    'SET cd.client_user_id = c.', @source_col, ' ',
    'WHERE cd.client_user_id IS NULL ',
    'AND c.', @source_col, ' IS NOT NULL ',
    'AND c.', @source_col, ' > 0'
  ),
  "SELECT 'missing_mapping_columns' AS error"
);
PREPARE stmt_update FROM @update_sql;
EXECUTE stmt_update;
DEALLOCATE PREPARE stmt_update;

-- Rows affected in this session.
SELECT ROW_COUNT() AS rows_updated;

-- Verify candidates are now zero.
PREPARE stmt_count_after FROM @count_sql;
EXECUTE stmt_count_after;
DEALLOCATE PREPARE stmt_count_after;

-- If everything looks correct, COMMIT. Otherwise ROLLBACK.
-- COMMIT;
-- ROLLBACK;
