-- Backfill client_documents.client_id to use client user id (usuarios.id or usuarios.id_usuario)
-- instead of legacy clientes.id values.
-- Safe/idempotent: only updates rows where a mapping exists and the current client_id
-- is not already a valid usuarios id.
--
-- Before running in production:
-- 1) Review counts and samples.
-- 2) Run inside a transaction and COMMIT only after verification.
--
-- Expected schema:
-- - client_documents.client_id stores the client user id.
-- - clientes has client_user_id (preferred) or user_id (fallback) mapping to usuarios.id (or usuarios.id_usuario).

-- Detect mapping columns.
SELECT
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

-- Abort if mapping columns are missing.
SELECT @source_col AS source_col, @usuarios_id_col AS usuarios_id_col;

-- Count candidates (rows that look legacy and have a valid mapping).
SET @count_sql := CONCAT(
  'SELECT COUNT(*) AS candidates ',
  'FROM client_documents cd ',
  'JOIN clientes c ON c.id = cd.client_id ',
  'JOIN usuarios u ON u.', @usuarios_id_col, ' = c.', @source_col, ' ',
  'WHERE c.', @source_col, ' IS NOT NULL ',
  'AND c.', @source_col, ' > 0 ',
  'AND cd.client_id <> c.', @source_col, ' ',
  'AND NOT EXISTS (SELECT 1 FROM usuarios u2 WHERE u2.', @usuarios_id_col, ' = cd.client_id)'
);
PREPARE stmt_count FROM @count_sql;
EXECUTE stmt_count;
DEALLOCATE PREPARE stmt_count;

-- Sample rows for verification.
SET @sample_sql := CONCAT(
  'SELECT cd.id AS doc_id, cd.client_id AS old_client_id, c.id AS clientes_id, ',
  'c.', @source_col, ' AS new_client_user_id ',
  'FROM client_documents cd ',
  'JOIN clientes c ON c.id = cd.client_id ',
  'JOIN usuarios u ON u.', @usuarios_id_col, ' = c.', @source_col, ' ',
  'WHERE c.', @source_col, ' IS NOT NULL ',
  'AND c.', @source_col, ' > 0 ',
  'AND cd.client_id <> c.', @source_col, ' ',
  'AND NOT EXISTS (SELECT 1 FROM usuarios u2 WHERE u2.', @usuarios_id_col, ' = cd.client_id) ',
  'LIMIT 20'
);
PREPARE stmt_sample FROM @sample_sql;
EXECUTE stmt_sample;
DEALLOCATE PREPARE stmt_sample;

-- Apply update (run inside a transaction).
START TRANSACTION;

SET @update_sql := CONCAT(
  'UPDATE client_documents cd ',
  'JOIN clientes c ON c.id = cd.client_id ',
  'JOIN usuarios u ON u.', @usuarios_id_col, ' = c.', @source_col, ' ',
  'SET cd.client_id = c.', @source_col, ' ',
  'WHERE c.', @source_col, ' IS NOT NULL ',
  'AND c.', @source_col, ' > 0 ',
  'AND cd.client_id <> c.', @source_col, ' ',
  'AND NOT EXISTS (SELECT 1 FROM usuarios u2 WHERE u2.', @usuarios_id_col, ' = cd.client_id)'
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
