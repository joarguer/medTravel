-- Add client_user_id column to client_documents, index it, and FK to usuarios.
-- Idempotent: checks schema before applying.

-- Detect usuarios id column.
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

-- Add column if missing.
SET @has_col := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'client_documents' AND column_name = 'client_user_id'
);
SET @ddl_add_col := IF(@has_col = 0, 'ALTER TABLE client_documents ADD COLUMN client_user_id INT NULL', 'SELECT ''client_user_id_exists'' AS info');
PREPARE stmt_add_col FROM @ddl_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

-- Add index if missing.
SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'client_documents' AND index_name = 'idx_client_documents_client_user_id'
);
SET @ddl_add_idx := IF(@has_idx = 0, 'CREATE INDEX idx_client_documents_client_user_id ON client_documents (client_user_id)', 'SELECT ''client_user_id_index_exists'' AS info');
PREPARE stmt_add_idx FROM @ddl_add_idx;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;

-- Add FK if possible and missing.
SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.referential_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'client_documents'
    AND constraint_name = 'fk_client_documents_client_user'
);
SET @ddl_add_fk := IF(
  (@has_fk = 0 AND @usuarios_id_col <> ''),
  CONCAT(
    'ALTER TABLE client_documents ',
    'ADD CONSTRAINT fk_client_documents_client_user ',
    'FOREIGN KEY (client_user_id) REFERENCES usuarios(', @usuarios_id_col, ') ',
    'ON DELETE SET NULL'
  ),
  'SELECT ''fk_client_documents_client_user_exists_or_missing_usuarios'' AS info'
);
PREPARE stmt_add_fk FROM @ddl_add_fk;
EXECUTE stmt_add_fk;
DEALLOCATE PREPARE stmt_add_fk;
